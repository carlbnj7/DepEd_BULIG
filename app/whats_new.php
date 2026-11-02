<?php
/* What's new (v178, list v181): Bob the Worker's popup. Each account sees it once, on the first page after signing in, after
   BULIG is updated: a construction sign with Bob and the new things for their role. Closing it keeps a copy on the
   bell (Notifications), and ?whatsnew=1 opens it again. Seen is kept in settings (whatsnew_<update>_<id>), no SQL.
   For a new update: change WHATS_NEW_ID and the lists below. */
const WHATS_NEW_ID='v206';
const WHATS_NEW=[
 /* shown before signing in too (on the login page, after the opening screen), once on each device */
 'guest'=>[
  ['mic','Reading Coach in every level','Read the story, and BULIG stops by itself at the last word. You see your stars, then it is sent to your teacher.'],
  ['book','Easier to read','Settings has Bigger text and Easy-read letters, a plain letter style that helps many readers.'],
  ['star','My practice words','Hard words from Word Coach are kept in one list. Max says each word, then you say it.'],
  ['phone','Faster pictures','Pictures are smaller now, so pages open faster on phones.'],
 ],
 'pupil'=>[
  ['mic','Read, and it sends itself','Read the story to the last word. BULIG stops by itself, shows your stars, and sends it to your teacher.'],
  ['star','My practice words','Your hard words are in My practice words. Tap to hear Max say a word, then say it to him.'],
  ['ladder','Class reading challenge','Your Home shows how many words your class read this week. Read to help your class reach the goal!'],
  ['book','Bigger text and easy letters','In Settings, turn on Bigger text or Easy-read letters if reading on the screen is hard.'],
 ],
 'teacher'=>[
  ['alert','Readers who need help','Home lists pupils whose Word Coach scores stay low, with their hard words, and suggests a reread or a small group.'],
  ['ladder','Class reading challenge','Set a weekly words goal on Home. Pupils see the class progress bar on their Home.'],
  ['phone','Work waiting to upload','Home shows which pupils have answers still on their phone that did not upload yet.'],
  ['mic','Reading Coach in every level','Pupils read the story; BULIG stops at the last word and sends the reading to you by itself.'],
 ],
 'admin'=>[
  ['search','Data cleanup','A new page joins pupils who have two accounts, and archives last year\'s sections in one step.'],
  ['alert','Readers who need help','Teachers now see pupils with low Word Coach scores, with a reread or small group idea.'],
  ['book','Easier to read','My profile and pupil Settings have Bigger text and Easy-read letters.'],
  ['phone','Faster pictures','Large pictures are smaller now, so pages open faster on phones.'],
 ],
];
function wn_key(int $uid):string{return 'whatsnew_'.WHATS_NEW_ID.'_'.$uid;}
function wn_seen(int $uid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',[wn_key($uid)]);}catch(Throwable $e){return true;}}
function wn_icon(string $k):string{
 $d=['jeep'=>'<rect x="3" y="6" width="18" height="10" rx="2"/><path d="M3 11h18M8 6V4h8v2"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="16.5" cy="18" r="1.8"/>','ladder'=>'<path d="M7 3v18M17 3v18M7 7h10M7 12h10M7 17h10"/>','match'=>'<circle cx="5" cy="7" r="2"/><circle cx="19" cy="17" r="2"/><path d="M7 7c6 0 4 10 10 10"/>',
  'edit'=>'<path d="M4 20h4L19 9l-4-4L4 16z"/>','comic'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 12h18M12 4v8"/>','book'=>'<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
  'check'=>'<path d="M5 12l4 4 10-10"/>','mic'=>'<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>','alert'=>'<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>','phone'=>'<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>','star'=>'<path d="M12 3l2.7 5.6 6.2.9-4.5 4.3 1.1 6.1L12 17l-5.5 2.9 1.1-6.1L3 9.5l6.2-.9z"/>'][$k]??'';
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
