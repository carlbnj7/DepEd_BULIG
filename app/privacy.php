<?php
/* v209 Data privacy (Data Privacy Act of 2012, RA 10173).
   - The first time someone signs in, Claude the Guard explains what BULIG keeps and asks them to agree (privacy_pop).
     The answer is kept in settings as privacy_ok_<user id> (no new table, no SQL update).
   - The Privacy Notice page (?page=privacy) opens for everyone, also before signing in.
   - "My Data and Privacy" card: on a pupil's Settings and on a teacher's or administrator's Profile. Download My Data
     gives a JSON file of the person's own records. Ask to Delete tells the administrator (and a pupil's teacher).
   - Voice recordings are deleted 60 days after they are made, except readings that still wait for the teacher. */

const PRIVACY_ID='v1';
const PRIVACY_KEEP_DAYS=60;

function privacy_key(int $uid):string{return 'privacy_ok_'.$uid;}
function privacy_agreed(int $uid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',[privacy_key($uid)]);}catch(Throwable $e){return true;}}

/** The four things everyone should know, in words for pupils or for adults. [icon, title, text] */
function privacy_points(string $role):array{
 if($role==='pupil')return [
  ['user','What we keep','Your name, your class and your scores. Not where you live. Not your phone number.'],
  ['mic','Your voice','Max records you when you read. Your teacher listens to help you. After '.PRIVACY_KEEP_DAYS.' days, it is deleted.'],
  ['lock','Who can see it','Only you, your teacher and your school. Never strangers.'],
  ['shield','You can ask','Want to see, fix or delete your data? Ask your teacher.']];
 return [
  ['user','What we keep','Names, IDs, LRN and sex of pupils, schools, sections, reading levels, answers, scores and XP.'],
  ['mic','Voice recordings','Kept only so teachers can check readings. Deleted after '.PRIVACY_KEEP_DAYS.' days; readings still waiting to be checked stay until they are checked.'],
  ['lock','Who can see it','Teachers see only their own pupils. The administrator sees their school. Passwords are stored locked (hashed). Nothing is shared or sold.'],
  ['shield','Your rights','You can see, download, fix or ask to delete your data at any time, from My Data and Privacy on your profile.']];
}
function privacy_list(array $pts):string{$h='<ul class="wn-list">';foreach($pts as [$i,$t,$d])$h.='<li><span class="wn-ic">'.icon($i).'</span><span><b>'.e($t).'</b><small>'.e($d).'</small></span></li>';return $h.'</ul>';}

/** The popup after signing in, or '' when this person already agreed. */
function privacy_pop(?array $u):string{
 if(!$u||($_GET['page']??'')==='privacy'||privacy_agreed((int)$u['id']))return '';   // the notice page itself is read first, without the popup on top
 if(function_exists('teacher_must_change')&&teacher_must_change($u))return '';
 $kid=$u['role']==='pupil';$page=(string)($_GET['page']??'dashboard');$back=preg_match('~^[a-z_]{1,30}$~',$page)?$page:'dashboard';
 return '<div class="wn-shade pv-shade" data-pv role="dialog" aria-modal="true" aria-labelledby="pv-title"><div class="wn-box pv-box">'
  .'<div class="wn-tape" aria-hidden="true"></div>'
  .'<img class="wn-bob" src="'.e(friend_img('claude','shield')).'" alt="Claude the Guard" width="150" height="326">'
  .'<form method="post" class="wn-body">'.csrf_field().'<input type="hidden" name="action" value="privacy_agree"><input type="hidden" name="back" value="'.e($back).'">'
  .'<span class="wn-badge">'.icon('shield').'DATA PRIVACY</span><h2 id="pv-title">Your Data Is Safe With BULIG</h2>'
  .'<p class="wn-say"><b>Claude the Guard:</b> '.($kid?'I keep your data safe. Here is what you need to know.':'Before you start, here is how BULIG keeps personal information safe.').'</p>'
  .privacy_list(privacy_points((string)$u['role']))
  .'<label class="pv-check"><input type="checkbox" name="agree" value="1" required data-pv-tick><span>'.($kid?'I understand, and I agree to the <a href="?page=privacy" data-pv-more>Privacy Notice</a>.':'I have read the <a href="?page=privacy" data-pv-more>Privacy Notice</a> and I agree to how BULIG uses my data.').'</span></label>'
  .'<p class="pv-note">'.icon('shield').($kid?'BULIG follows the law that keeps your data safe (RA 10173)':'Following the Data Privacy Act of 2012 (RA 10173)').'</p>'
  .'<button class="btn primary wn-ok" data-pv-ok disabled>'.icon('check').($kid?'I Agree, Let’s Go':'I Agree').'</button>'
  .'<div class="pv-full" data-pv-full hidden><span class="wn-badge">'.icon('info').'PRIVACY NOTICE</span><h2>'.($kid?'All About Your Data':'BULIG Privacy Notice').'</h2>'.privacy_list(privacy_notice_parts($kid)).'<button type="button" class="btn secondary wn-ok" data-pv-back>'.icon('arrow','flip').'Back</button></div></form>'
  .'<div class="wn-tape wn-tape-b" aria-hidden="true"></div></div></div>';
}

/** The full notice: [icon, title, text] for each part. */
function privacy_notice_parts(bool $kid=false):array{if($kid)return [
 ['user','What we keep','Your name, your LRN, your class and school, your scores, XP and badges, and your reading voice. Not where you live. Not your phone number.'],
 ['book','Why we keep it','So your teacher can help you read better, and see how much you grow.'],
 ['mic','Your voice','Max records you only when you read. Your teacher listens to help you. After '.PRIVACY_KEEP_DAYS.' days, it is deleted.'],
 ['lock','How we keep it safe','Your password is locked, so no one can read it. Only you, your teacher and your school can open your work.'],
 ['people','Who can see it','Only you, your teacher and your school. BULIG never gives your data to strangers or to other companies.'],
 ['clock','How long we keep it','While you study in your school.'],
 ['shield','You can ask','You can see your data, get a copy, fix it, or ask to delete it. Just ask your teacher.'],
 ['info','Need help?','Ask your teacher or your school. They can talk to the people in charge of keeping data safe.']];
 return [
 ['user','What BULIG collects','Pupils: name, LRN, sex, grade, section, school, reading levels, answers, scores, XP, badges, learning days and voice recordings of readings. Teachers and administrators: name, ID, school and sex. No home address, phone number or photo is required.'],
 ['book','Why we collect it','To teach reading with the official DepEd BULIG modules, to check pupils’ readings, to track progress and to print report cards and certificates. Nothing is used for advertising.'],
 ['mic','Voice recordings','Max records a pupil’s reading only while the pupil reads. Teachers listen to check it. Recordings are deleted '.PRIVACY_KEEP_DAYS.' days after they are made; a reading that still waits for its teacher stays until it is checked.'],
 ['lock','How we protect it','Passwords are stored locked (hashed). Every page checks who is signed in and what their role allows: teachers see only their own pupils. Recordings are kept outside the public web folder. Wrong passwords are slowed down, and every change is written in the activity log.'],
 ['people','Who can see it','The pupil, the pupil’s teacher and the school administrator. BULIG does not share, sell or send personal data to other companies.'],
 ['clock','How long we keep it','Records stay while the pupil studies in the school. The administrator can clear old records after making a backup, and can delete an account with all its work.'],
 ['shield','Your rights','You may be informed, see your data, download it, correct it, object, and ask for it to be deleted. Use My Data and Privacy on your profile or settings, or ask your teacher or the school administrator.'],
 ['info','Contact','For questions or complaints, contact the Data Protection Officer of the DepEd Schools Division of Bukidnon, or the National Privacy Commission (privacy.gov.ph).']];}

function privacy_notice_html(bool $guest,bool $kid=false):string{
 $h='<section class="card pv-page"><span class="eyebrow">DATA PRIVACY</span><h1>'.icon('shield').($kid?'All About Your Data':'BULIG Privacy Notice').'</h1>'
  .'<p class="muted">'.($kid?'BULIG follows the law that keeps your data safe (RA 10173).':'How BULIG follows the Data Privacy Act of 2012 (Republic Act 10173). Version '.e(PRIVACY_ID).'.').'</p><div class="pv-grid pv-grid-1">';
 foreach(privacy_notice_parts($kid) as [$i,$t,$d])$h.='<div class="pv-item">'.icon($i).'<span><b>'.e($t).'</b><small>'.e($d).'</small></span></div>';
 return $h.'</div>'.($guest?'<a class="btn primary" href="?page=login">'.icon('arrow').'Back to sign in</a>':'').'</section>';
}
function privacy_view(?array $u):void{echo privacy_notice_html(!$u,($u['role']??'')==='pupil');}

/** "My Data and Privacy" on the profile (teachers, administrators) or the settings page (pupils). */
function privacy_card(array $u):string{
 $kid=$u['role']==='pupil';$h='<section class="card pv-card" id="my-data"><span class="eyebrow">MY DATA</span><h2>'.icon('shield').'My Data and Privacy<span class="pv-law">RA 10173</span></h2>'
  .'<p class="muted">'.($kid?'Here is what BULIG keeps about you, and what you can do with it.':'What BULIG keeps about you, and what you can do with it.').'</p><div class="pv-grid">';
 foreach(privacy_points((string)$u['role']) as [$i,$t,$d])$h.='<div class="pv-item">'.icon($i).'<span><b>'.e($t).'</b><small>'.e($d).'</small></span></div>';
 $h.='</div><div class="pv-acts"><a class="btn primary" href="?page=my_data">'.icon('download').'Download My Data</a><a class="btn secondary" href="?page=privacy">'.icon('info').'Read the Privacy Notice</a>';
 if($u['role']==='teacher')$h.='<form method="post" class="pv-del">'.csrf_field().'<input type="hidden" name="action" value="privacy_delete_ask"><button class="btn quiet" data-confirm="'.e($kid?'Ask to delete your account? Your teacher and the school administrator will be told.':'Ask the school administrator to delete your account?').'">'.icon('trash').'Ask to Delete My Account</button></form>';
 $ok=one('SELECT setting_value FROM settings WHERE setting_key=?',[privacy_key((int)$u['id'])]);
 return $h.'</div><p class="pv-dpo">'.($ok?'You agreed to the Privacy Notice on '.e(date('F j, Y',strtotime((string)$ok['setting_value']))).'. ':'').'Questions? Contact the Data Protection Officer, DepEd Schools Division of Bukidnon.</p></section>';
}

function privacy_action(string $action):never{
 $u=current_user();if(!$u)fail('Please sign in again.',401);
 if($action==='privacy_agree'){
  if(empty($_POST['agree']))fail('Tick the box to agree to the Privacy Notice.');
  q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[privacy_key((int)$u['id']),date('Y-m-d H:i:s')]);
  audit('privacy_agree','Privacy Notice '.PRIVACY_ID);
  $b=(string)($_POST['back']??'dashboard');go('?page='.(preg_match('~^[a-z_]{1,30}$~',$b)?$b:'dashboard'));}
 if($action==='privacy_delete_ask'){
  if($u['role']!=='teacher')fail($u['role']==='pupil'?'Please ask your teacher or the school administrator.':'Administrators delete accounts on the Teachers page.',403);   // v209 pupils cannot send this request
  $title=$u['name'].' asked to delete their account';$body=$u['public_id'].' · '.ucfirst((string)$u['role']).' · Data Privacy Act request. Check, then delete the account if it is right.';
  $url=$u['role']==='pupil'?'?page=pupil&id='.(int)$u['id'].'&tab=account':'?page=accounts';
  foreach(rows("SELECT id FROM users WHERE role='admin' AND active=1") as $a)notify((int)$a['id'],'privacy',$title,$body,$u['role']==='pupil'?'?page=accounts':$url);
  if($u['role']==='pupil'&&function_exists('notify_teachers'))notify_teachers((int)$u['id'],'privacy',$title,$body,$url);
  audit('privacy_delete_ask',$u['public_id']);
  flash($u['role']==='pupil'?'Your request was sent to your teacher and the school administrator.':'Your request was sent to the school administrator.');
  go('?page='.($u['role']==='pupil'?'settings':'profile').'#my-data');}
 fail('Unknown action.');
}

/** Download My Data: the person's own records as JSON. Passwords, keys and tokens are never included. */
function privacy_my_data(array $u):never{
 $id=(int)$u['id'];$safe=function(string $sql,array $a=[]){try{return rows($sql,$a);}catch(Throwable $e){return [];}};
 $d=['about'=>'BULIG · My data · '.date('Y-m-d H:i'),'law'=>'Data Privacy Act of 2012 (RA 10173): your right to see your data.',
  'account'=>one('SELECT public_id,role,name,active,created_at FROM users WHERE id=?',[$id]),
  'name_parts'=>$safe('SELECT first_name,middle_name,last_name,name_ext FROM user_names WHERE user_id=?',[$id]),
  'school'=>$safe('SELECT s.name FROM schools s JOIN users u ON u.school_id=s.id WHERE u.id=?',[$id]),
  'privacy_agreed_on'=>(string)(val('SELECT setting_value FROM settings WHERE setting_key=?',[privacy_key($id)])?:''),
  'app_ratings'=>$safe('SELECT stars,overall,comment,created_at FROM app_ratings WHERE user_id=?',[$id]),
  'my_chat_messages'=>$safe('SELECT m.created_at,m.body,m.media_kind FROM chat_messages m WHERE m.author_id=? ORDER BY m.id',[$id])];
 if($u['role']==='pupil'){
  $d+=['pupil'=>$safe('SELECT grade_level,section FROM pupils WHERE user_id=?',[$id]),'details'=>$safe('SELECT sex,lrn FROM pupil_details WHERE pupil_id=?',[$id]),
   'teachers'=>$safe('SELECT u.name FROM teacher_pupils t JOIN users u ON u.id=t.teacher_id WHERE t.pupil_id=?',[$id]),
   'xp'=>$safe('SELECT total FROM pupil_xp WHERE pupil_id=?',[$id]),'streak'=>$safe('SELECT current_streak,longest_streak,last_activity_date FROM pupil_streaks WHERE pupil_id=?',[$id]),
   'badges'=>$safe('SELECT badge_id,earned_at FROM pupil_badges WHERE pupil_id=?',[$id]),'learning_days'=>array_column($safe('SELECT day FROM learning_days WHERE pupil_id=? ORDER BY day',[$id]),'day'),
   'answers'=>$safe('SELECT activity_id,status,score,max_score,response,transcript,feedback,submitted_at,reviewed_at FROM activity_completion WHERE pupil_id=? ORDER BY submitted_at',[$id]),
   'tests'=>$safe('SELECT assessment_id,status,score,max_score,completed_at FROM assessment_attempts WHERE pupil_id=?',[$id]),
   'reading_checks'=>$safe('SELECT grade,status,bulig_level,placed_level,teacher_note,started_at,finished_at FROM reading_checks WHERE pupil_id=?',[$id]),
   'reading_recordings'=>$safe('SELECT activity_id,state,score,max_score,reason,created_at,reviewed_at FROM reading_verifications WHERE pupil_id=?',[$id]),
   'help_requests'=>$safe('SELECT lesson_id,activity_id,reason,created_at FROM help_requests WHERE pupil_id=?',[$id])];
 }elseif($u['role']==='teacher'){
  $d+=['sections'=>$safe('SELECT grade_level,name,created_at,archived_at FROM sections WHERE teacher_id=?',[$id]),'pupils'=>(int)val('SELECT COUNT(*) FROM teacher_pupils WHERE teacher_id=?',[$id])];
 }
 audit('privacy_download',$u['public_id']);
 header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-My-Data-'.preg_replace('~[^A-Za-z0-9]~','',(string)$u['public_id']).'.json"');header('Cache-Control: no-store');
 echo json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}

/** Once a day: delete voice recordings older than PRIVACY_KEEP_DAYS days, except those of readings still waiting. */
function privacy_purge(bool $force=false):int{
 try{
  $today=date('Y-m-d');if(!$force&&val("SELECT setting_value FROM settings WHERE setting_key='privacy_purge_on'")===$today)return 0;
  q("INSERT INTO settings(setting_key,setting_value) VALUES('privacy_purge_on',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[$today]);
  $root=realpath(l1_recordings_dir());if(!$root)return 0;$keep=[];
  if(rv_ready())foreach(rows("SELECT audio_paths FROM reading_verifications WHERE state IN ('pending','missing')") as $r)foreach((array)json_decode((string)$r['audio_paths'],true) as $p)if(is_string($p))$keep[str_replace('\\','/',$p)]=1;
  $cut=time()-PRIVACY_KEEP_DAYS*86400;$n=0;
  foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $f){
   if(!$f->isFile()||$f->getMTime()>=$cut||$f->getFilename()==='.htaccess')continue;
   $rel=ltrim(str_replace('\\','/',substr($f->getPathname(),strlen($root))),'/');if(isset($keep[$rel]))continue;
   if(@unlink($f->getPathname()))$n++;}
  /* v209: photos and voice messages in Messages, after the same number of days */
  $mroot=function_exists('media_dir')?realpath(media_dir()):false;$m=0;
  if($mroot)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mroot,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile()&&$f->getMTime()<$cut&&$f->getFilename()!=='.htaccess'&&@unlink($f->getPathname()))$m++;
  if($m)audit('privacy_purge',$m.' message photos and voice messages older than '.PRIVACY_KEEP_DAYS.' days deleted');$n+=$m;
  if($n)audit('privacy_purge',$n.' voice recordings older than '.PRIVACY_KEEP_DAYS.' days deleted');
  return $n;
 }catch(Throwable $e){return 0;}
}
