<?php
/* What's new (v178, list v181): Bob the Worker's popup. Each account sees it once, on the first page after signing in, after
   BULIG is updated: a construction sign with Bob and the new things for their role. Closing it keeps a copy on the
   bell (Notifications), and ?whatsnew=1 opens it again. Seen is kept in settings (whatsnew_<update>_<id>), no SQL.
   For a new update: change WHATS_NEW_ID and the lists below. */
const WHATS_NEW_ID='v211';
const WHATS_NEW=[
 /* shown before signing in too (on the login page, after the opening screen), once on each device */
 'guest'=>[
  ['school','One BULIG for the whole Division','Municipalities, districts and schools now work together, each with its own administrators.'],
  ['user','One account, more than one role','If you have several roles, BULIG asks which one to use after you sign in.'],
  ['shield','Your data is safe','BULIG follows the Data Privacy Act (RA 10173). Read the Privacy Notice under the Let’s go button.'],
 ],
 'pupil'=>[
  ['comic','Chat with your teacher','Send your teacher a message, a photo or a voice message in Messages.'],
  ['star','A reward for real bugs','Found a problem and the administrator fixed it? You earn XP. No XP is taken away for other reports.'],
  ['shield','Your data is safe','Claude the Guard tells you what BULIG keeps and who can see it. See My Data and Privacy in Settings.'],
 ],
 'teacher'=>[
  ['check','Verify all readings','On Verify Readings, tick the readings you listened to and verify them all at once.'],
  ['swap','More than one role','If you are also a School Admin or Principal, choose your role after signing in, or switch from the menu.'],
  ['print','A new Teacher ID card','Your pass is now an ID card with your ID, starting password and QR code.'],
  ['comic','Chats with pupils','Messages shows your pupils’ chats, newest first, with photos and voice messages.'],
 ],
 'admin'=>[
  ['school','Organization','Municipalities, districts and schools in one tree. Add a person to a role: a new account or one that already exists.'],
  ['shield','Roles and limits','Division, Municipal, District and School admins each see their own area. Viewers, Supervisors and Principals only look.'],
  ['print','ID cards','Print ID cards for teachers and for the administrators you manage, each with a QR code.'],
  ['key','IDs never change','The ID switch is gone. Lost a password? Use New password on the Organization page.'],
 ],
];
function wn_key(int $uid):string{return 'whatsnew_'.WHATS_NEW_ID.'_'.$uid;}
function wn_seen(int $uid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',[wn_key($uid)]);}catch(Throwable $e){return true;}}
function wn_icon(string $k):string{
 $d=['school'=>'<path d="M3 21h18M5 21V10l7-5 7 5v11"/><path d="M10 21v-5h4v5"/>','user'=>'<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>','swap'=>'<path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>','print'=>'<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>','key'=>'<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9m-4 4 3 3"/>','jeep'=>'<rect x="3" y="6" width="18" height="10" rx="2"/><path d="M3 11h18M8 6V4h8v2"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="16.5" cy="18" r="1.8"/>','ladder'=>'<path d="M7 3v18M17 3v18M7 7h10M7 12h10M7 17h10"/>','match'=>'<circle cx="5" cy="7" r="2"/><circle cx="19" cy="17" r="2"/><path d="M7 7c6 0 4 10 10 10"/>',
  'edit'=>'<path d="M4 20h4L19 9l-4-4L4 16z"/>','comic'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 12h18M12 4v8"/>','book'=>'<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
  'check'=>'<path d="M5 12l4 4 10-10"/>','shield'=>'<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>','mic'=>'<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>','alert'=>'<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>','phone'=>'<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>','star'=>'<path d="M12 3l2.7 5.6 6.2.9-4.5 4.3 1.1 6.1L12 17l-5.5 2.9 1.1-6.1L3 9.5l6.2-.9z"/>'][$k]??'';
 return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
}
/** The popup, or '' when this account has seen it (or another first-time screen is showing). */
function whats_new_pop(?array $u):string{
 if(!$u||empty(WHATS_NEW[$u['role']]))return '';
 $again=($_GET['whatsnew']??'')==='1';
 if(!$again){
  if(wn_seen((int)$u['id']))return '';
  $guestSaw=($_COOKIE['bulig_wn']??'')===WHATS_NEW_ID;
  if(function_exists('teacher_must_change')&&teacher_must_change($u))return '';
  if($u['role']==='pupil'&&function_exists('friends_meta')&&friends_meta($u)!=='')return '';   // the friends say hello first; Bob comes next time
  try{q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',[wn_key((int)$u['id']),date('Y-m-d H:i:s')]);}catch(Throwable $e){}
  if(function_exists('notify'))notify((int)$u['id'],'update','BULIG has a new update!','Bob the Worker made new things for you. Tap to see them again.','?page=dashboard&whatsnew=1');
  if($guestSaw)return '';   // seen on the login page of this device: Bob does not come twice
 }
 $first=explode(' ',trim((string)$u['name']))[0];
 $li='';foreach(WHATS_NEW[$u['role']] as [$ic,$t,$d])$li.='<li><span class="wn-ic">'.wn_icon($ic).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';
 return '<div class="wn-shade" data-wn role="dialog" aria-modal="true" aria-labelledby="wn-title"><div class="wn-box">'
  .'<div class="wn-tape" aria-hidden="true"></div>'
  .'<img class="wn-bob" src="'.e(friend_img('bob','blueprint')).'" alt="Bob the Worker" width="150" height="326">'
  .'<div class="wn-body"><span class="wn-badge">'.wn_icon('star').'NEW UPDATE</span><h2 id="wn-title">There’s a new update, '.e($first).'!</h2>'
  .'<p class="wn-say"><b>Bob the Worker:</b> I made new things in BULIG for you. Here is what is new!</p><ul class="wn-list">'.$li.'</ul>'
  .'<button type="button" class="btn primary wn-ok" data-wn-close>'.icon('check').'Got it, Bob!</button></div>'
  .'<div class="wn-tape wn-tape-b" aria-hidden="true"></div></div></div>';
}

/** v203: the update on the login page (before signing in). Hidden until the opening screen is gone; login.js shows it
    once on each device (and remembers it in a cookie, so Bob does not come again right after signing in). */
function whats_new_guest():string{
 if(empty(WHATS_NEW['guest']))return '';
 $li='';foreach(WHATS_NEW['guest'] as [$ic,$t,$d])$li.='<li><span class="wn-ic">'.wn_icon($ic).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';
 return '<div class="wn-shade" data-wn-guest="'.e(WHATS_NEW_ID).'" hidden role="dialog" aria-modal="true" aria-labelledby="wn-guest-title"><div class="wn-box">'
  .'<div class="wn-tape" aria-hidden="true"></div>'
  .'<img class="wn-bob" src="'.e(friend_img('bob','blueprint')).'" alt="Bob the Worker" width="150" height="326">'
  .'<div class="wn-body"><span class="wn-badge">'.wn_icon('star').'NEW UPDATE</span><h2 id="wn-guest-title">BULIG has a new update!</h2>'
  .'<p class="wn-say"><b>Bob the Worker:</b> I made new things in BULIG. Here is what is new!</p><ul class="wn-list">'.$li.'</ul>'
  .'<button type="button" class="btn primary wn-ok" data-wn-close>'.icon('check').'Got it, Bob!</button></div>'
  .'<div class="wn-tape wn-tape-b" aria-hidden="true"></div></div></div>';
}
