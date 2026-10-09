<?php
/* Sign-in page parts (v133): the "try again" popup with tries left, the short-break popup after too many tries,
   the picture choice (pupil or teacher), the greeting for the time of day, the eye button on the password,
   "Forgot your password?" and the "Scan my Reading Pass" button (phones only; login.js opens the camera). No new tables. */

const LOGIN_MAX_TRIES=8;
const LOGIN_LOCK_MINUTES=15;

function login_tries_used(string $key):int{return (int)val('SELECT COUNT(*) FROM login_attempts WHERE identifier_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL '.LOGIN_LOCK_MINUTES.' MINUTE)',[$key]);}
/** Seconds until this ID may try again (0 when it is not locked). */
function login_lock_seconds(string $key):int{
 if(login_tries_used($key)<LOGIN_MAX_TRIES)return 0;
 $s=val('SELECT TIMESTAMPDIFF(SECOND,NOW(),DATE_ADD(attempted_at,INTERVAL '.LOGIN_LOCK_MINUTES.' MINUTE)) FROM login_attempts WHERE identifier_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL '.LOGIN_LOCK_MINUTES.' MINUTE) ORDER BY attempted_at DESC LIMIT 1 OFFSET '.(LOGIN_MAX_TRIES-1),[$key]);
 return max(1,(int)$s);
}
/** A wrong ID or password (or too many tries): back to the sign-in form with the ID kept and a popup. */
function login_back(string $role,string $id,string $key):void{
 $role=in_array($role,['pupil','teacher','admin'],true)?$role:'pupil';$id=preg_match('/^[A-Za-z0-9-]{1,30}$/',$id)?$id:'';
 $used=login_tries_used($key);$secs=login_lock_seconds($key);
 $_SESSION['login_fail']=['role'=>$role,'id'=>$id,'used'=>min(LOGIN_MAX_TRIES,$used),'until'=>$secs?time()+$secs:0];
 go($role==='admin'?'?page=login&role=admin&confirm=1':'?page=login&role='.$role.'&form=1');
}
function login_fail_take(string $role):?array{$f=$_SESSION['login_fail']??null;unset($_SESSION['login_fail']);return is_array($f)&&($f['role']??'')===$role?$f:null;}

/** Morning 5-11, afternoon 12-16, evening otherwise (the same hours as the pupil home page sky). */
function login_daypart():string{$h=(int)date('G');return $h>=5&&$h<12?'m':($h>=12&&$h<17?'a':'e');}
function login_greeting():string{
 $d=login_daypart();$ic=['m'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>','a'=>'<path d="M17 18a5 5 0 0 0-10 0"/><path d="M12 9V2M4.2 10.2l1.4 1.4M1 18h2M21 18h2M18.4 11.6l1.4-1.4M23 22H1"/>','e'=>'<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>'][$d];
 return '<span class="lgn-greet lgn-greet-'.$d.'"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic.'</svg>'.['m'=>'GOOD MORNING','a'=>'GOOD AFTERNOON','e'=>'GOOD EVENING'][$d].'</span>';
}
function login_pupil_headline():string{return ['m'=>'Ready to read <br>today?','a'=>'One more story <br>today?','e'=>'A bedtime story <br>before sleep?'][login_daypart()];}

/** Pupil or teacher, with pictures. */
function login_role_cards(string $role):string{
 $card=fn($r,$a,$b,$t,$s)=>'<a class="lgn-role'.($role===$r?' active':'').'" href="?page=login&amp;role='.$r.'"'.($role===$r?' aria-current="page"':'').'><span class="lgn-role-pics" aria-hidden="true"><img src="'.e($a).'" alt=""><img src="'.e($b).'" alt=""></span><span class="lgn-role-t"><b>'.$t.'</b><small>'.$s.'</small></span></a>';
 return '<nav class="lgn-roles" aria-label="Who is signing in?">'.$card('pupil',char_src('boy','hello'),char_src('girl','hello'),'I am a pupil','I learn with BULIG').$card('teacher',char_src('teacher-female','hello'),char_src('teacher-male','hello'),'I am a teacher','I guide my class').'</nav>';
}
/** Phones with a camera only: login.js shows it. */
function login_scan_block(string $role='pupil'):string{
 if($role!=='pupil'&&$role!=='teacher')return '';$t=$role==='teacher';
 return '<div class="lgn-scan-wrap" data-lgn-scan-wrap hidden><button type="button" class="lgn-scan" data-lgn-scan data-pass="'.($t?'Teacher Pass':'Reading Pass').'" data-jsqr="'.e(asset_url('jsqr.js')).'"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3v3M21 14v.5M14 21h3M21 18v3h-1"/></svg>Scan my '.($t?'Teacher Pass':'Reading Pass').'</button><div class="lgn-or">or type your '.($t?'Teacher':'Pupil').' ID</div></div>';
}
function login_eye_button():string{
 return '<button type="button" class="show-password lgn-eye" data-password="password" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="lgn-eye-on" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="lgn-eye-off" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button>';
}
/** "Forgot your password?", right under the password box. */
function login_forgot_link():string{return '<button type="button" class="lgn-forgot-link lgn-under-pw" data-lgn-forgot>'.icon('key').'Forgot your password?</button>';}
/** The Credits link, and the sheet that "Forgot your password?" opens. */
function login_foot(string $role):string{
 $pupil=$role==='pupil';
 $steps=$pupil?['Tell your teacher.','Your teacher prints a new Reading Pass with a new password.','Scan the pass with a phone, or type your Pupil ID and the new password.']:['Tell your BULIG administrator.','The administrator makes a new password for you.','Sign in, then change it in My profile.'];
 $li='';foreach($steps as $i=>$t)$li.='<li><b>'.($i+1).'</b><span>'.e($t).'</span></li>';
 return '<div class="lgn-foot"><a class="login-credits" href="?page=credits">'.icon('star').'Credits</a></div>'
  .'<dialog class="lgn-sheet" data-lgn-sheet aria-labelledby="lgn-sheet-t"><form method="dialog"><span class="lgn-grab" aria-hidden="true"></span><div class="lgn-sheet-hd"><img src="'.e(char_src($pupil?'teacher-female':'admin-female','hello')).'" alt=""><div><h3 id="lgn-sheet-t">Forgot your password?</h3><p>'.($pupil?'That’s okay! Your teacher can help.':'That’s okay! Your administrator can help.').'</p></div></div><ol class="lgn-steps">'.$li.'</ol><button class="btn primary full lgn-ok">Okay!</button></form></dialog>';
}

/** The popup after a wrong ID or password, or after too many tries. */
function login_fail_popup(?array $f,string $role):string{
 if(!$f)return '';$who=['pupil'=>'Pupil ID','teacher'=>'Teacher ID','admin'=>'administrator ID'][$role];$helper=$role==='pupil'?'your teacher':'your administrator';
 $until=(int)($f['until']??0);$left=max(0,LOGIN_MAX_TRIES-(int)$f['used']);$back=$role==='admin'?'?page=login&role=admin&confirm=1':'?page=login&role='.$role.'&form=1'.($f['id']!==''?'&id='.rawurlencode($f['id']):'');
 $close='<a class="btn primary full lgn-again" href="'.e($back).'" data-lgn-close>'.icon('replay').'Try again</a>';
 if($until>time()){
  $secs=$until-time();$c=2*M_PI*64;
  return '<div class="lgn-pop-wrap lgn-locked" data-lgn-pop role="alertdialog" aria-modal="true" aria-labelledby="lgn-pop-t" aria-describedby="lgn-pop-d"><div class="lgn-pop-bg"></div><div class="lgn-pop">'
   .'<div class="lgn-clock" data-lgn-secs="'.$secs.'" data-lgn-total="'.(LOGIN_LOCK_MINUTES*60).'"><svg viewBox="0 0 150 150" aria-hidden="true"><circle cx="75" cy="75" r="64" class="lgn-clock-bg"/><circle cx="75" cy="75" r="64" class="lgn-clock-fill" stroke-dasharray="'.round($c*$secs/(LOGIN_LOCK_MINUTES*60),1).' '.round($c,1).'" transform="rotate(-90 75 75)"/></svg><b><small>TRY AGAIN IN</small><span data-lgn-clock>'.sprintf('%d:%02d',intdiv($secs,60),$secs%60).'</span></b></div>'
   .'<h2 id="lgn-pop-t">Time for a short break!</h2><p id="lgn-pop-d">There were too many tries. You can sign in again at '.e(date('g:i A',$until)).'.</p>'
   .'<ul class="lgn-tips"><li>'.icon('people').'<span>Ask '.$helper.' to help you with your '.e($who).' and password.</span></li></ul>'
   .'<button type="button" class="btn primary full lgn-wait" disabled data-lgn-wait data-lgn-href="'.e($back).'">Try again at '.e(date('g:i A',$until)).'</button></div></div>';
 }
 $dots='';for($i=0;$i<LOGIN_MAX_TRIES;$i++)$dots.='<i'.($i<LOGIN_MAX_TRIES-$left?' class="u"':'').'></i>';
 $key='<svg class="lgn-key" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="52" r="44" fill="#fff4cf"/><g transform="rotate(-35 50 52)"><circle cx="34" cy="52" r="15" fill="none" stroke="#f0b400" stroke-width="8"/><path d="M48 52h34M70 52v10M78 52v7" stroke="#f0b400" stroke-width="8" stroke-linecap="round"/></g><circle cx="74" cy="26" r="15" fill="#1c7a50"/><text x="74" y="32.5" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="800" font-size="19" fill="#fff">?</text></svg>';
 return '<div class="lgn-pop-wrap" data-lgn-pop role="alertdialog" aria-modal="true" aria-labelledby="lgn-pop-t" aria-describedby="lgn-pop-d"><div class="lgn-pop-bg"></div><div class="lgn-pop">'.$key
  .'<h2 id="lgn-pop-t">Oops! That did not match.</h2><p id="lgn-pop-d">Check your '.e($who).', then type your password again.'.($f['id']!==''?' Your '.e($who).' is still there.':'').'</p>'
  .'<div class="lgn-tries" aria-hidden="true">'.$dots.'</div><small class="lgn-left'.($left<=2?' low':'').'">'.($left===1?'Only 1 try left':($left<=2?'Only '.$left.' tries left':$left.' tries left')).'</small>'
  .$close.($role==='admin'?'':'<button type="button" class="lgn-forgot-link" data-lgn-forgot>Forgot it? Ask '.$helper.'.</button>').'</div></div>';
}

/** login.js asks who a scanned Reading Pass belongs to, to say "Found you, NAME!" before signing in. */
function key_peek(string $key):void{
 $p=card_user_any($key);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 if(!$p){echo json_encode(['ok'=>false]);exit;}
 /* A teacher who already made their own password: the pass only fills in the Teacher ID. */
 if($p['role']==='teacher'){echo json_encode(['ok'=>true,'role'=>'teacher','first'=>explode(' ',trim((string)$p['name']))[0],'img'=>$p['avatar_path']?:teacher_char((int)$p['id'])]+(card_has_starter((int)$p['id'])?[]:['fill'=>$p['public_id']]));exit;}
 $sex=(string)(val('SELECT sex FROM pupil_details WHERE pupil_id=?',[$p['id']])?:'');
 echo json_encode(['ok'=>true,'role'=>'pupil','first'=>explode(' ',trim((string)$p['name']))[0],'img'=>$p['avatar_path']?:char_src($sex==='female'?'girl':'boy','hello')]);exit;
}
