<?php
/* What's new (v178, list v181): Bob the Builder's popup. Each account sees it once, on the first page after signing in, after
   BULIG is updated: a construction sign with Bob and the new things for their role. Closing it keeps a copy on the
   bell (Notifications), and ?whatsnew=1 opens it again. Seen is kept in settings (whatsnew_<update>_<id>), no SQL.
   For a new update: change WHATS_NEW_ID and the lists below. */
const WHATS_NEW_ID='v181';
const WHATS_NEW=[
 'pupil'=>[
  ['star','Win stars!','Every activity gives you up to 3 stars. Get 2 stars to go on to the next one. See your stars floating over each stop on your road map!'],
  ['star','Post-test stars','Post-tests give up to 5 stars. Get 4 stars to open the next level.'],
  ['edit','Draw and type in BULIG','Puzzles, word boxes, crosswords and drawings are now done right here, not on paper.'],
  ['check','Save me when you sign out','Saving your account now asks when you sign out, not when you sign in.'],
  ['comic','Bob is on the comic cover','Find Bob the Builder with all the BULIG friends in Story time.'],
 ],
 'teacher'=>[
  ['star','Stars and the 80% rule','Every lesson activity gives up to 3 stars, shown over each stop on the pupil\'s road map. A pupil needs 2 stars (80%) to go on; below that the activity opens again. Post-tests give 5 stars and still need 80% to open the next level. Pre-tests have no stars.'],
  ['ladder','Leaderboard page','The top 10 of your class has its own page in the menu (My class › Leaderboard). Choose one level or all levels.'],
  ['edit','No more “on paper” cards','Word boxes, crosswords, word searches, letter boxes, drawings and “underline the answer” are all answered in BULIG and checked.'],
  ['match','Matching boards','Pupils see how many pairs are right and can go on, so no one is stuck.'],
  ['check','A pupil is stuck?','If the microphone misheard, raise the score to 80% or more on the pupil\'s answer: the activity opens for them.'],
 ],
 'admin'=>[
  ['star','Stars and the 80% rule','Lesson activities give up to 3 stars (2 stars = 80% needed to go on). Post-tests give 5 stars and still need 80%.'],
  ['edit','Rename and delete teachers','Teachers › Edit name & sex has First, Middle, Last and Extension. Delete works for every teacher: choose who gets their pupils.'],
  ['ladder','Teacher leaderboard','Teachers have a Leaderboard page with a level filter.'],
  ['edit','No more “on paper” cards','Every activity is answered in BULIG now, with drawings saved for the teacher.'],
  ['check','Fixes','Print page clicks, phone layout of long answers, and pupil pop-ups.'],
 ],
];
function wn_key(int $uid):string{return 'whatsnew_'.WHATS_NEW_ID.'_'.$uid;}
function wn_seen(int $uid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',[wn_key($uid)]);}catch(Throwable $e){return true;}}
function wn_icon(string $k):string{
 $d=['ladder'=>'<path d="M7 3v18M17 3v18M7 7h10M7 12h10M7 17h10"/>','match'=>'<circle cx="5" cy="7" r="2"/><circle cx="19" cy="17" r="2"/><path d="M7 7c6 0 4 10 10 10"/>',
  'edit'=>'<path d="M4 20h4L19 9l-4-4L4 16z"/>','comic'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 12h18M12 4v8"/>','book'=>'<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
  'check'=>'<path d="M5 12l4 4 10-10"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>','alert'=>'<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>','phone'=>'<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>','star'=>'<path d="M12 3l2.7 5.6 6.2.9-4.5 4.3 1.1 6.1L12 17l-5.5 2.9 1.1-6.1L3 9.5l6.2-.9z"/>'][$k]??'';
 return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
}
/** The popup, or '' when this account has seen it (or another first-time screen is showing). */
function whats_new_pop(?array $u):string{
 if(!$u||empty(WHATS_NEW[$u['role']]))return '';
 $again=($_GET['whatsnew']??'')==='1';
 if(!$again){
  if(wn_seen((int)$u['id']))return '';
  if(function_exists('teacher_must_change')&&teacher_must_change($u))return '';
  if($u['role']==='pupil'&&function_exists('friends_meta')&&friends_meta($u)!=='')return '';   // the friends say hello first; Bob comes next time
  try{q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',[wn_key((int)$u['id']),date('Y-m-d H:i:s')]);}catch(Throwable $e){}
  if(function_exists('notify'))notify((int)$u['id'],'update','BULIG has a new update!','Bob the Builder built new things for you. Tap to see them again.','?page=dashboard&whatsnew=1');
 }
 $first=explode(' ',trim((string)$u['name']))[0];
 $li='';foreach(WHATS_NEW[$u['role']] as [$ic,$t,$d])$li.='<li><span class="wn-ic">'.wn_icon($ic).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';
 return '<div class="wn-shade" data-wn role="dialog" aria-modal="true" aria-labelledby="wn-title"><div class="wn-box">'
  .'<div class="wn-tape" aria-hidden="true"></div>'
  .'<img class="wn-bob" src="'.e(friend_img('bob','blueprint')).'" alt="Bob the Builder" width="150" height="326">'
  .'<div class="wn-body"><span class="wn-badge">'.wn_icon('star').'NEW UPDATE</span><h2 id="wn-title">There’s a new update, '.e($first).'!</h2>'
  .'<p class="wn-say"><b>Bob the Builder:</b> I built new things in BULIG for you. Here is what is new!</p><ul class="wn-list">'.$li.'</ul>'
  .'<button type="button" class="btn primary wn-ok" data-wn-close>'.icon('check').'Got it, Bob!</button></div>'
  .'<div class="wn-tape wn-tape-b" aria-hidden="true"></div></div></div>';
}
