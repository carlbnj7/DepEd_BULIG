<?php
/* Pupil sign-in tickets: an easy starter password for each pupil, and a one-scan sign-in key for the QR code.
   Both are kept encrypted so the ticket can be printed again; the starter password is removed once the pupil
   makes their own. The key never contains the password, and "New ticket" cancels the old QR code at once. */

const STARTER_WORDS=['apple','mango','tiger','zebra','panda','koala','melon','camel','bunny','puppy','grape','peach','berry','candy','happy','sunny','cloud','robin','eagle','shark','whale','horse','sheep','bread','cake','kite','boat','drum','frog','duck','bear','lion','star','moon','rain','tree','leaf','corn','rice','fish'];

function cards_ready():bool{static $r=null;if($r===null){try{db()->query('SELECT 1 FROM pupil_cards LIMIT 1');$r=true;}catch(Throwable $e){$r=false;}}return $r;}
/** An easy password a young pupil can type: a word and four numbers (no 0 or 1, so they are not mixed up with O and l). */
function starter_password():string{$d='';for($i=0;$i<4;$i++)$d.=(string)random_int(2,9);return STARTER_WORDS[random_int(0,count(STARTER_WORDS)-1)].$d;}
/** Encryption key: the site's secret (from config, not only the database) mixed with a random value kept in settings. */
function cards_key():string{
 static $k=null;if($k!==null)return $k;
 $salt=val("SELECT setting_value FROM settings WHERE setting_key='card_salt'");
 if(!$salt){q("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('card_salt',?)",[bin2hex(random_bytes(16))]);$salt=val("SELECT setting_value FROM settings WHERE setting_key='card_salt'");}
 $c=require __DIR__.'/../config/database.php';
 return $k=hash_hkdf('sha256',(string)($c['pass']??'').'|'.(string)($c['name']??'').'|bulig-cards',32,'bulig pupil cards',(string)$salt);
}
function cards_enc(string $s):string{$iv=random_bytes(12);$ct=openssl_encrypt($s,'aes-256-gcm',cards_key(),OPENSSL_RAW_DATA,$iv,$tag);return base64_encode($iv.$tag.$ct);}
function cards_dec(?string $s):?string{if(!$s)return null;$b=base64_decode($s,true);if($b===false||strlen($b)<29)return null;$r=openssl_decrypt(substr($b,28),'aes-256-gcm',cards_key(),OPENSSL_RAW_DATA,substr($b,0,12),substr($b,12,16));return $r===false?null:$r;}
/** Make a new ticket for a pupil: a new QR key, and (when asked) a new starter password that replaces the old password. */
function card_issue(int $pid,bool $newPassword):array{
 $key=rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'=');$pw=$newPassword?starter_password():null;
 if($pw!==null){q('UPDATE users SET password_hash=? WHERE id=? AND role=?',[password_hash($pw,PASSWORD_DEFAULT),$pid,'pupil']);saved_login_clear($pid);}
 $old=one('SELECT starter_enc FROM pupil_cards WHERE user_id=?',[$pid]);$starter=$pw!==null?cards_enc($pw):($old['starter_enc']??null);
 q('INSERT INTO pupil_cards(user_id,key_hash,key_enc,starter_enc,issued_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE key_hash=VALUES(key_hash),key_enc=VALUES(key_enc),starter_enc=VALUES(starter_enc),issued_at=NOW()',[$pid,hash('sha256',$key),cards_enc($key),$starter]);
 return ['key'=>$key,'pw'=>$pw];
}
/** What a printed ticket needs: the QR key (made now if the pupil has none) and the starter password, if still in use. */
function card_info(int $pid):array{
 $r=one('SELECT key_enc,starter_enc FROM pupil_cards WHERE user_id=?',[$pid]);$key=$r?cards_dec($r['key_enc']):null;
 if(!$key){$n=card_issue($pid,false);return ['key'=>$n['key'],'pw'=>$r?cards_dec($r['starter_enc']):null];}
 return ['key'=>$key,'pw'=>cards_dec($r['starter_enc'])];
}
/** The pupil (or teacher) set a new password: the ticket shows "Your own password" from now on. */
function card_forget_starter(int $pid):void{if(cards_ready())q('UPDATE pupil_cards SET starter_enc=NULL WHERE user_id=?',[$pid]);}
function card_has_starter(int $pid):bool{return cards_ready()&&(bool)val('SELECT COUNT(*) FROM pupil_cards WHERE user_id=? AND starter_enc IS NOT NULL',[$pid]);}
/** The pupil a scanned key belongs to. */
function card_user(string $key):?array{
 if(!cards_ready()||!preg_match('~^[A-Za-z0-9_-]{20,40}$~',$key))return null;
 return one("SELECT u.* FROM pupil_cards c JOIN users u ON u.id=c.user_id WHERE c.key_hash=? AND u.role='pupil' AND u.active=1",[hash('sha256',$key)])?:null;
}
/** New pupil accounts: a starter password and a ticket. Falls back to the old shared password if the table is missing. */
function new_pupil_password(int $pid):string{
 if(!cards_ready()){q('UPDATE users SET password_hash=? WHERE id=?',[password_hash('12345678',PASSWORD_DEFAULT),$pid]);return '12345678';}
 return card_issue($pid,true)['pw'];
}
