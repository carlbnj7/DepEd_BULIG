<?php
/* What's new (v178, list v181): Bob the Worker's popup. Each account sees it once, on the first page after signing in, after
   BULIG is updated: a construction sign with Bob and the new things for their role. Closing it keeps a copy on the
   bell (Notifications), and ?whatsnew=1 opens it again. Seen is kept in settings (whatsnew_<update>_<id>), no SQL.
   For a new update: change WHATS_NEW_ID and the lists below. */
const WHATS_NEW_ID='v216';
/* v216: the earlier update (v214) stays in the popup, under "Earlier update" */
const WHATS_NEW_PREV=[
 /* shown before signing in too (on the login page, after the opening screen), once on each device */
 'guest'=>[
  ['star','Victory!','Get 2 or 3 stars on an activity for a Victory celebration. Below that, try again!'],
  ['star','A new look: BULIG Quest','BULIG Quest has a new logo and a new tagline: Read, Play, Grow.'],
  ['school','BULIG Quest is now in Junior High','Grades 7 to 10 can now use BULIG Quest: Levels 4, 5, 6 and 7, each with its own Junior High module.'],
  ['book','New pictures, every answer checked','The Junior High stories have new pictures, and every question has an answer key.'],
  ['star','More levels are coming','Look at the road map: Levels 9 to 14 are on the way.'],
 ],
 'pupil'=>[
  ['star','Victory!','Get 2 or 3 stars on an activity and you win a Victory! 0 or 1 star means try again.'],
  ['star','A new look: BULIG Quest','BULIG Quest has a new logo and a new tagline: Read, Play, Grow.'],
  ['school','Junior High is here','Grades 7 to 10 now have their own lessons: fluency, listening and vocabulary, reading comprehension and love for reading.'],
  ['book','New pages before each test','A Pre-Assessment page before every pre-test, and a Post-Assessment page before every post-test.'],
  ['star','More levels are coming','Look past Level 8 on your road map: Levels 9 to 14 are on the way!'],
 ],
 'teacher'=>[
  ['star','Fairer leaderboard','The Top 10 now ranks by score and activities finished, so a few lucky activities cannot take first place.'],
  ['check','Victory and Try again','Pupils see a Victory popup at 2 or 3 stars (80%), and Try again below that.'],
  ['star','A new look: BULIG Quest','BULIG Quest has a new logo and a new tagline: Read, Play, Grow.'],
  ['school','Junior High is here','Make Grade 7 to 10 sections: choose Elementary or Junior High School, then the grade.'],
  ['book','Levels 4 to 7 for Junior High','Each Junior High module is in BULIG Quest with pictures, and every tap question is checked with an answer key.'],
  ['check','Class demo for every grade','The class demo shows each level’s lessons for the grade you choose, Grade 1 to Grade 10.'],
  ['comic','Announcements board','The administrator’s announcements are now pinned on a bulletin board.'],
 ],
 'admin'=>[
  ['star','Victory and a fairer leaderboard','Pupils see a Victory or Try again popup after each activity, and the Top 10 ranks by score and activities finished.'],
  ['star','A new look: BULIG Quest','BULIG Quest has a new logo and a new tagline: Read, Play, Grow.'],
  ['school','Elementary and Junior High','BULIG Quest finds each school’s type from its School ID or name: Elementary, Junior High or Integrated. You can change it.'],
  ['book','Junior High modules','Levels 4, 5, 6 and 7 for Grades 7 to 10, with answer keys and pictures. Import the update SQL first.'],
  ['comic','Bulletin board','Your announcements are pinned on a bulletin board for everyone in your area: Division, town, district or school.'],
  ['star','More levels are coming','The road map now shows Levels 9 to 14 ahead.'],
 ],
];
const WHATS_NEW=[
 'guest'=>[
  ['star','Star Jars','Every star you earn goes in a Star Jar. Each level has one, and the Big Star Jar holds them all.'],
  ['check','Play again','Got 1 or 2 stars? Play the activity again for 3 stars and more XP. Your best score is kept.'],
  ['star','Class Star Race and Zion’s mission','Your section races other sections for stars each week, and Zion gives +30 XP when you earn 10 stars in a week. Max lets you hear your first and latest reading.'],
  ['clock','Roxanne times each activity','After each activity, Roxanne the Timekeeper tells how long it took and your fastest time.'],
  ['user','Your BULIG friends have new jobs','Zion keeps your stars, Mia cheers the words you build, and Jeepy drives you to the next level.'],
 ],
 'pupil'=>[
  ['star','Star Jars','Every star goes in your Star Jar. Tap the jar on your road map to see it. Zion the Star Keeper keeps them!'],
  ['check','Play again for 3 stars','Got 1 or 2 stars? Tap Play again. A new star gives +5 XP. Already 3 stars? Practice for +2 XP. Your best is always kept.'],
  ['star','Weekly mission and Star Race','Earn 10 stars this week for +30 XP from Zion! Help your section win the Class Star Race. Listen to your first and latest reading with Max.'],
  ['clock','Roxanne the Timekeeper','After each activity, Roxanne tells you how long it took. Try to beat your fastest time!'],
  ['user','Friends with new jobs','Mia cheers every word you build, Max cheers your reading, and Jeepy honks when you finish a level.'],
  ['edit','New keyboard','The letter tiles look like a phone keyboard. When there are letter clues, just tap the clue.'],
  ['mic','Easier reading','A big Start reading button, and Roxanne’s little clock times you while you read.'],
 ],
 'teacher'=>[
  ['star','Star Jars and Play again','Pupils can play a finished activity again for 3 stars. BULIG Quest keeps the best score and still saves the first try for you.'],
  ['people','Parent cards and Needs help','Print a progress card for each family, with a QR code to a read-only page. Home now lists pupils who may need you. The administrator can set answering hours.'],
  ['chart','Reading growth','Teaching › Reading growth shows how each pupil’s reading changed: pre-test to post-test, started at to now, and Level 4 words per minute and accuracy. Print it for your panel.'],
  ['clock','Time on each activity','Roxanne the Timekeeper shows how long each answer took (pupil › Answers and scores). Red means much slower than the class usually takes.'],
  ['check','Fair XP','+5 XP for each new star, +2 XP for practice (3 times a day). Pre-tests and post-tests cannot be played again.'],
  ['edit','Letter tiles','A phone-style keyboard, letter clues as buttons, and blanks like “_og” stay one word.'],
  ['user','Friends with new jobs','Zion keeps the stars, Mia cheers built words, Jeepy drives to the next level, Bruno helps with bug reports.'],
 ],
 'admin'=>[
  ['star','Star Jars and Play again','Pupils can play a finished activity again for 3 stars. The best score is kept, and the first try is saved for teachers.'],
  ['clock','Answering hours','Settings › Schedule: choose the days and times pupils may answer. Outside them, Roxanne tells pupils when answering opens. Work made without internet is always kept.'],
  ['print','Admin passes','Print admin passes from the Print Centre. ID cards are coloured by role.'],
  ['shield','Division Viewer','A new view-only role that sees every school in the division.'],
  ['book','Content Studio','Content Developers make new levels from a module, with answer keys and real pre-tests and post-tests.'],
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
 if(!$u||empty(WHATS_NEW[$u['role']]))return '';$prev=WHATS_NEW_PREV[$u['role']]??[];
 $again=($_GET['whatsnew']??'')==='1';
 if(!$again){
  if(wn_seen((int)$u['id']))return '';
  $guestSaw=($_COOKIE['bulig_wn']??'')===WHATS_NEW_ID;
  if(function_exists('teacher_must_change')&&teacher_must_change($u))return '';
  if($u['role']==='pupil'&&function_exists('friends_meta')&&friends_meta($u)!=='')return '';   // the friends say hello first; Bob comes next time
  try{q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',[wn_key((int)$u['id']),date('Y-m-d H:i:s')]);}catch(Throwable $e){}
  if(function_exists('notify'))notify((int)$u['id'],'update','BULIG Quest has a new update!','Bob the Worker made new things for you. Tap to see them again.','?page=dashboard&whatsnew=1');
  if($guestSaw)return '';   // seen on the login page of this device: Bob does not come twice
 }
 $first=explode(' ',trim((string)$u['name']))[0];
 $li='';foreach(WHATS_NEW[$u['role']] as [$ic,$t,$d])$li.='<li><span class="wn-ic">'.wn_icon($ic).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';
 return '<div class="wn-shade" data-wn role="dialog" aria-modal="true" aria-labelledby="wn-title"><div class="wn-box">'
  .'<div class="wn-tape" aria-hidden="true"></div>'
  .'<img class="wn-bob" src="'.e(friend_img('bob','blueprint')).'" alt="Bob the Worker" width="150" height="326">'
  .'<div class="wn-body"><span class="wn-badge">'.wn_icon('star').'NEW UPDATE</span><h2 id="wn-title">There’s a new update, '.e($first).'!</h2>'
  .'<p class="wn-say"><b>Bob the Worker:</b> I made new things in BULIG Quest for you. Here is what is new!</p><ul class="wn-list">'.$li.'</ul>'.wn_prev_html($prev)
  .'<button type="button" class="btn primary wn-ok" data-wn-close>'.icon('check').'Got it, Bob!</button></div>'
  .'<div class="wn-tape wn-tape-b" aria-hidden="true"></div></div></div>';
}

/** v203: the update on the login page (before signing in). Hidden until the opening screen is gone; login.js shows it
    once on each device (and remembers it in a cookie, so Bob does not come again right after signing in). */
function whats_new_guest():string{
 if(empty(WHATS_NEW['guest']))return '';
 $li='';foreach(WHATS_NEW['guest'] as [$ic,$t,$d])$li.='<li><span class="wn-ic">'.wn_icon($ic).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';$li.='</ul>'.wn_prev_html(WHATS_NEW_PREV['guest']??[]).'<ul class="wn-list" hidden>';
 return '<div class="wn-shade" data-wn-guest="'.e(WHATS_NEW_ID).'" hidden role="dialog" aria-modal="true" aria-labelledby="wn-guest-title"><div class="wn-box">'
  .'<div class="wn-tape" aria-hidden="true"></div>'
  .'<img class="wn-bob" src="'.e(friend_img('bob','blueprint')).'" alt="Bob the Worker" width="150" height="326">'
  .'<div class="wn-body"><span class="wn-badge">'.wn_icon('star').'NEW UPDATE</span><h2 id="wn-guest-title">BULIG Quest has a new update!</h2>'
  .'<p class="wn-say"><b>Bob the Worker:</b> I made new things in BULIG Quest. Here is what is new!</p><ul class="wn-list">'.$li.'</ul>'
  .'<button type="button" class="btn primary wn-ok" data-wn-close>'.icon('check').'Got it, Bob!</button></div>'
  .'<div class="wn-tape wn-tape-b" aria-hidden="true"></div></div></div>';
}

/** v216 The earlier update, folded under the new one (tap to open). */
function wn_prev_html(array $prev):string{if(!$prev)return '';$li='';foreach($prev as [$ic,$t,$d])$li.='<li><span class="wn-ic">'.wn_icon($ic).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';
 return '<details class="wn-prev"><summary>Earlier update</summary><ul class="wn-list">'.$li.'</ul></details>';}
