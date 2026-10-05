<?php
/* Reaching pupils outside the lesson: the "Offline lessons" page, and phone reminders (Web Push).
   Push messages are encrypted here (RFC 8291 aes128gcm, VAPID RFC 8292) with PHP's own openssl, no library needed.
   Reminders are sent by cron/notify.php (a scheduled task every 15 minutes). */

/** Changes whenever BULIG's files are updated, so lessons saved for offline can be marked "Needs update". */
function bulig_build():string{
 static $b=null;if($b!==null)return $b;$s='';$r=__DIR__.'/../';
 foreach(['app/views.php','app/presentation.php','app/level2_cards.php','public/assets/app.js','public/assets/theme.css','public/assets/learn.js','public/assets/delight.js','public/assets/offline.js'] as $f)$s.=$f.@filemtime($r.$f).'-'.@filesize($r.$f).';';
 return $b=substr(md5($s),0,10);
}
/** Extra tags in <head> for pupils: build, push key, and the once-per-sign-in download offer. */
function reach_head(?array $cu):string{
 if(!$cu||$cu['role']!=='pupil')return '';$h='<meta name="bulig-build" content="'.bulig_build().'">';
 if(push_ready())$h.='<meta name="bulig-vapid" content="'.e(vapid_keys()['public']).'">';
 if(!empty($_SESSION['dl_offer'])){$h.='<meta name="bulig-dl-offer" content="1">';unset($_SESSION['dl_offer']);}
 return $h;
}
/** Pupil page: every level they can open, what is saved on this device, and "Download everything". Drawn by offline.js. */
function offline_page_view(array $u):void{
 echo '<div class="pageheading"><div><span class="eyebrow">MY OFFLINE LESSONS</span><h1>Learn without internet</h1><p class="muted">Save your lessons on this device. They open even when there is no internet.</p></div></div><section class="off-page" data-off-page><div class="card off-sum"><p class="muted">Checking your saved lessons…</p></div></section>';
}

/* ---------- Web Push: keys ---------- */
function b64u(string $s):string{return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}
function b64u_dec(string $s):string{return (string)base64_decode(strtr($s,'-_','+/').str_repeat('=',(4-strlen($s)%4)%4));}
function push_ready():bool{static $ok=null;if($ok!==null)return $ok;try{$ok=function_exists('openssl_pkey_derive')&&function_exists('curl_init')&&(bool)val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='push_subscriptions'");}catch(Throwable $e){$ok=false;}return $ok;}
/** The site's VAPID key pair, made once and kept in settings. */
function vapid_keys():array{
 static $k=null;if($k)return $k;$pem=val("SELECT setting_value FROM settings WHERE setting_key='vapid_private'");
 if(!$pem){$key=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);openssl_pkey_export($key,$pem);
  q("INSERT INTO settings(setting_key,setting_value) VALUES('vapid_private',?) ON DUPLICATE KEY UPDATE setting_value=setting_value",[$pem]);$pem=val("SELECT setting_value FROM settings WHERE setting_key='vapid_private'");}
 $d=openssl_pkey_get_details(openssl_pkey_get_private($pem));$pub="\x04".str_pad($d['ec']['x'],32,"\0",STR_PAD_LEFT).str_pad($d['ec']['y'],32,"\0",STR_PAD_LEFT);
 return $k=['pem'=>$pem,'public'=>b64u($pub)];
}
/** Raw P-256 point (65 bytes) to a PEM public key. */
function ec_point_pem(string $point):string{return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point),64,"\n")."-----END PUBLIC KEY-----\n";}
/** DER ECDSA signature to the 64-byte r||s form JWT needs. */
function der_to_raw(string $der):string{$o=3;$rl=ord($der[$o]);$r=substr($der,$o+1,$rl);$o+=1+$rl+1;$sl=ord($der[$o]);$s=substr($der,$o+1,$sl);$f=fn($x)=>str_pad(ltrim($x,"\0"),32,"\0",STR_PAD_LEFT);return $f($r).$f($s);}
function vapid_header(string $endpoint):string{
 $p=parse_url($endpoint);$aud=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');$k=vapid_keys();
 $sub=(string)(val("SELECT setting_value FROM settings WHERE setting_key='push_contact'")?:'https://'.($_SERVER['HTTP_HOST']??'localhost'));
 $data=b64u('{"typ":"JWT","alg":"ES256"}').'.'.b64u(json_encode(['aud'=>$aud,'exp'=>time()+43200,'sub'=>$sub],JSON_UNESCAPED_SLASHES));
 openssl_sign($data,$der,$k['pem'],OPENSSL_ALGO_SHA256);return 'vapid t='.$data.'.'.b64u(der_to_raw($der)).', k='.$k['public'];
}
/** Encrypt one message for one subscription (RFC 8291, aes128gcm). */
function push_encrypt(string $payload,string $uaPublicB64,string $authB64):string{
 $ua=b64u_dec($uaPublicB64);$auth=b64u_dec($authB64);if(strlen($ua)!==65||strlen($auth)<16)throw new RuntimeException('Bad subscription keys.');
 $eph=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);$d=openssl_pkey_get_details($eph);
 $as="\x04".str_pad($d['ec']['x'],32,"\0",STR_PAD_LEFT).str_pad($d['ec']['y'],32,"\0",STR_PAD_LEFT);
 $secret=openssl_pkey_derive(openssl_pkey_get_public(ec_point_pem($ua)),$eph,32);if($secret===false)throw new RuntimeException('Key agreement failed.');
 $ikm=hash_hkdf('sha256',$secret,32,"WebPush: info\0".$ua.$as,$auth);$salt=random_bytes(16);
 $cek=hash_hkdf('sha256',$ikm,16,"Content-Encoding: aes128gcm\0",$salt);$nonce=hash_hkdf('sha256',$ikm,12,"Content-Encoding: nonce\0",$salt);
 $ct=openssl_encrypt($payload."\x02",'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag);
 return $salt.pack('N',4096).chr(65).$as.$ct.$tag;
}
/** Send to one subscription row. Returns the HTTP status (0 when it could not connect). Gone subscriptions are removed. */
function push_send(array $s,array $msg):int{
 $body=push_encrypt(json_encode($msg),(string)$s['p256dh'],(string)$s['auth']);
 $c=curl_init((string)$s['endpoint']);curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/octet-stream','Content-Encoding: aes128gcm','TTL: 86400','Urgency: normal','Authorization: '.vapid_header((string)$s['endpoint'])]]);
 curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if(in_array($code,[404,410],true))q('DELETE FROM push_subscriptions WHERE id=?',[(int)$s['id']]);
 return $code;
}
/** Remember a note for a pupil; cron/notify.php sends it (at most one note a day, daytime only). */
function push_queue(int $pid,string $kind,string $title,string $body,string $url):void{
 if(!push_ready()||!val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=?',[$pid]))return;
 q('INSERT INTO push_queue(user_id,kind,title,body,url) VALUES(?,?,?,?,?)',[$pid,$kind,mb_substr($title,0,120),mb_substr($body,0,200),mb_substr($url,0,200)]);
}
const PUSH_DEFAULT=['daily'=>1,'hour'=>16,'teacher'=>1,'lessons'=>1,'weekends'=>0];
function push_prefs(array $row):array{$p=json_decode((string)($row['prefs']??''),true);return array_merge(PUSH_DEFAULT,is_array($p)?$p:[]);}
/** Pupil actions from the phone: subscribe, change choices, turn off. JSON in, JSON out. */
function push_action(string $action):void{
 $u=require_role('pupil');header('Content-Type: application/json');if(!push_ready()){echo json_encode(['ok'=>false,'error'=>'Reminders are not set up on this site yet.']);exit;}
 $ep=trim((string)($_POST['endpoint']??''));if(!preg_match('~^https://[^\s]{10,480}$~',$ep)){echo json_encode(['ok'=>false,'error'=>'Bad subscription.']);exit;}
 $hash=hash('sha256',$ep);if(!empty($_SERVER['HTTP_HOST']))q("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('push_contact',?)",['https://'.$_SERVER['HTTP_HOST']]);
 if($action==='push_subscribe'){$k1=(string)($_POST['p256dh']??'');$k2=(string)($_POST['auth']??'');if(strlen(b64u_dec($k1))!==65||strlen(b64u_dec($k2))<16){echo json_encode(['ok'=>false,'error'=>'Bad keys.']);exit;}
  q('INSERT INTO push_subscriptions(user_id,endpoint_hash,endpoint,p256dh,auth,prefs) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),endpoint=VALUES(endpoint),p256dh=VALUES(p256dh),auth=VALUES(auth)',[(int)$u['id'],$hash,$ep,$k1,$k2,json_encode(PUSH_DEFAULT)]);}
 $row=one('SELECT * FROM push_subscriptions WHERE endpoint_hash=? AND user_id=?',[$hash,(int)$u['id']]);
 if($action==='push_off'){q('DELETE FROM push_subscriptions WHERE endpoint_hash=? AND user_id=?',[$hash,(int)$u['id']]);echo json_encode(['ok'=>true]);exit;}
 if(!$row){echo json_encode(['ok'=>false,'error'=>'Turn reminders on first.']);exit;}
 if($action==='push_prefs'){$p=push_prefs($row);foreach(['daily','teacher','lessons','weekends'] as $k)if(isset($_POST[$k]))$p[$k]=$_POST[$k]==='1'?1:0;if(isset($_POST['hour']))$p['hour']=max(6,min(20,(int)$_POST['hour']));
  q('UPDATE push_subscriptions SET prefs=? WHERE id=?',[json_encode($p),(int)$row['id']]);$row['prefs']=json_encode($p);}
 if($action==='push_test'){$code=push_send($row,['title'=>'BULIG reminders are on!','body'=>'This is how a reminder looks. See you at reading time!','url'=>'?page=dashboard','tag'=>'bulig-test']);echo json_encode(['ok'=>$code>=200&&$code<300,'status'=>$code,'prefs'=>push_prefs($row)]);exit;}
 echo json_encode(['ok'=>true,'prefs'=>push_prefs($row)]);exit;
}
