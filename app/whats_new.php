<?php
/* What's new (v178): Bob the Builder's popup. Each account sees it once, on the first page after signing in, after
   BULIG is updated: a construction sign with Bob and the new things for their role. Closing it keeps a copy on the
   bell (Notifications), and ?whatsnew=1 opens it again. Seen is kept in settings (whatsnew_<update>_<id>), no SQL.
   For a new update: change WHATS_NEW_ID and the lists below. */
const WHATS_NEW_ID='v178';
const WHATS_NEW=[
 'pupil'=>[
  ['phone','New BULIG app icon','Ask a grown-up to delete BULIG from your home screen, then add it again to see the new icon.'],
  ['ladder','Level 3 is now Level 3A and Level 3B','Just like your module. Your progress is kept.'],
  ['match','Matching is easier','Tap a word, then tap its answer. No more dragging.'],
  ['edit','Your writing stays','What you type is kept, even if you close the page.'],
  ['comic','Meet Bob the Builder!','Read his new page in Story time.'],
 ],
 'teacher'=>[
  ['phone','New BULIG app icon','BULIG has a new app icon. If BULIG is on your home screen, delete it, then add BULIG to your home screen again to get the new icon.'],
  ['ladder','Level 3A and Level 3B','Level 3 is split like the module. Every pupil keeps their answers and progress.'],
  ['edit','Full answers','See your pupils\' whole editorials and long answers, with their paragraphs.'],
  ['book','Copyreading','The article is already in the answer box, ready to correct.'],
  ['match','Tap matching','Pupils tap a word, then its answer. Long choices connect now.'],
  ['check','Level 5 fixes','Pre-tests and post-tests of Grades 1, 5 and 6 answer and score correctly.'],
 ],
 'admin'=>[
  ['phone','New BULIG app icon','BULIG has a new app icon. If BULIG is on your home screen, delete it, then add BULIG to your home screen again to get the new icon.'],
  ['search','School directory','Every pupil and teacher, with search and filters, under People.'],
  ['ladder','Level 3A and Level 3B','Level 3 is split like the module. Progress is kept.'],
  ['star','New Gamified BULIG logo','On the sign-in page and the loading screen.'],
  ['alert','Bug reports stay open','A report stays in Open until you tap Fixed, even after you read it on the bell.'],
  ['check','Fixes','Level 5 tests, Level 7 writing and copyreading, and matching.'],
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
