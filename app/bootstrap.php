<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/database.php';
date_default_timezone_set($config['timezone']);
if(PHP_SAPI!=='cli'){
 session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
 ini_set('session.use_strict_mode','1'); session_start();
 if(isset($_SESSION['uid'])&&isset($_SESSION['last_active'])&&time()-(int)$_SESSION['last_active']>7200){$_SESSION=[];session_regenerate_id(true);}
 $_SESSION['last_active']=time();
 header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');
 header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
 header('Cache-Control: no-store');
}
function db():PDO{global $config;static $pdo;if(!$pdo){$pdo=new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",$config['user'],$config['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$pdo->exec("SET time_zone = '".date('P')."'");}return $pdo;}
function q(string $sql,array $p=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($p);return $s;}
function rows(string $sql,array $p=[]):array{return q($sql,$p)->fetchAll();}
function one(string $sql,array $p=[]):?array{return q($sql,$p)->fetch()?:null;}
function val(string $sql,array $p=[]){return q($sql,$p)->fetchColumn();}
function e($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function fail(string $message,int $code=400):never{throw new RuntimeException($message,$code);}
function go(string $url):never{header('Location: '.$url);exit;}
function flash(string $s):void{$_SESSION['flash']=$s;}
function csrf():string{return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function csrf_field():string{return '<input type="hidden" name="csrf" value="'.csrf().'">';}
function check_csrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))fail('Your session changed. Reload the page and try again.',403);}
function current_user():?array{if(empty($_SESSION['uid']))return null;$u=one('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']]);if(!$u){unset($_SESSION['uid']);return null;}return $u;}
function require_role(string ...$roles):array{$u=current_user();if(!$u)fail('Please sign in again.',401);if(!in_array($u['role'],$roles,true))fail('You do not have access to this page.',403);return $u;}
function own_pupil(int $pid):void{$u=require_role('teacher');if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$u['id'],$pid]))fail('This pupil is not assigned to you.',403);}
function audit(string $action,string $detail):void{q('INSERT INTO audit_log(actor_id,action,details) VALUES(?,?,?)',[current_user()['id']??null,$action,$detail]);}
function normalize_answer(string $s):string{$s=strtolower(trim($s));return preg_replace('/[^\pL\pN]+/u',' ',$s)??'';}
function word_match(string $expected,string $heard):array{
 $a=preg_split('/\s+/',trim(normalize_answer($expected)),-1,PREG_SPLIT_NO_EMPTY);$b=preg_split('/\s+/',trim(normalize_answer($heard)),-1,PREG_SPLIT_NO_EMPTY);
 if(count($a)>300||count($b)>300)fail('Use a reading passage and transcript of at most 300 words.');
 // Word-level edit distance: insertions, substitutions and omissions count as errors.
 $d=[];for($i=0;$i<=count($a);$i++)$d[$i]=[$i];for($j=0;$j<=count($b);$j++)$d[0][$j]=$j;
 for($i=1;$i<=count($a);$i++)for($j=1;$j<=count($b);$j++)$d[$i][$j]=min($d[$i-1][$j]+1,$d[$i][$j-1]+1,$d[$i-1][$j-1]+($a[$i-1]===$b[$j-1]?0:1));
 $i=count($a);$j=count($b);$detail=[];
 while($i||$j){if($i&&$j&&$d[$i][$j]===$d[$i-1][$j-1]+($a[$i-1]===$b[$j-1]?0:1)){$detail[]=['expected'=>$a[$i-1],'heard'=>$b[$j-1],'match'=>$a[$i-1]===$b[$j-1]];$i--;$j--;}elseif($i&&$d[$i][$j]===$d[$i-1][$j]+1){$detail[]=['expected'=>$a[$i-1],'heard'=>'','match'=>false];$i--;}else{$detail[]=['expected'=>'','heard'=>$b[$j-1],'match'=>false];$j--;}}
 return ['percent'=>count($a)?round(max(0,1-$d[count($a)][count($b)]/count($a))*100,2):0,'words'=>array_reverse($detail)];
}
function activities(int $lid,int $pid=0):array{return rows("SELECT a.*,c.status,c.response,c.feedback,c.drawing,c.id completion_id FROM activities a LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.lesson_id=? AND a.published=1 ORDER BY FIELD(a.phase,'pre','learn','post'),a.position",[$pid,$lid]);}
function lesson_available(int $pid,int $lid):bool{
 $l=one('SELECT l.*,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=? AND l.published=1',[$lid]);if(!$l)return false;
 if(!(int)val('SELECT published FROM bulig_levels WHERE id=?',[$l['level_id']]))return false;
 if(!level_available($pid,(int)$l['level_id']))return false;
 return !val('SELECT COUNT(*) FROM lessons l LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE l.module_id=? AND l.position<? AND l.published=1 AND p.completed_at IS NULL',[$pid,$l['module_id'],$l['position']]);
}
function completion_ok(?array $row):bool{return $row&&in_array($row['status'],['approved','completed'],true);}
function phase_available(int $pid,int $lid,string $phase):bool{
 $order=['pre'=>0,'learn'=>1,'post'=>2];if(!isset($order[$phase]))return false;
 $earlier=array_slice(array_keys($order),0,$order[$phase]);
 foreach($earlier as $stage)if(val("SELECT COUNT(*) FROM activities a LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.lesson_id=? AND a.phase=? AND a.published=1 AND (c.status IS NULL OR c.status NOT IN ('approved','completed'))",[$pid,$lid,$stage]))return false;
 return true;
}
function allowed_activity(int $pid,int $aid):array{
 $a=one('SELECT * FROM activities WHERE id=? AND published=1',[$aid]);if(!$a)fail('Activity not found.',404);
 if(!lesson_available($pid,(int)$a['lesson_id'])||!phase_available($pid,(int)$a['lesson_id'],$a['phase']))fail('Complete the earlier learning steps first.',403);
 $before=val("SELECT COUNT(*) FROM activities a LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.lesson_id=? AND a.phase=? AND a.position<? AND a.published=1 AND (c.status IS NULL OR c.status NOT IN ('approved','completed'))",[$pid,$a['lesson_id'],$a['phase'],$a['position']]);
 if($before)fail('Please finish the earlier activity first.',403);return $a;
}
function next_activity(int $pid,int $lid):?array{foreach(activities($lid,$pid) as $a)if(!completion_ok($a))return $a;return null;}
function owned_section(int $sid,int $teacher):array{$s=one('SELECT * FROM sections WHERE id=? AND teacher_id=?',[$sid,$teacher]);if(!$s)fail('Choose a section that belongs to you.',403);return $s;}
function teacher_sections(int $teacher):array{return rows('SELECT * FROM sections WHERE teacher_id=? ORDER BY grade_level,name',[$teacher]);}
function finish_lesson(int $pid,int $lid):void{
 if(!lesson_available($pid,$lid))fail('Lesson not available.',403);
 if(next_activity($pid,$lid))fail('Complete every activity before finishing this lesson.');
 q('INSERT INTO pupil_progress(pupil_id,lesson_id,completed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE completed_at=COALESCE(completed_at,NOW())',[$pid,$lid]);award_badges($pid);
}
function update_learning(int $pid,int $aid):void{
 $a=one('SELECT * FROM activities WHERE id=?',[$aid]);
 $insert=q('INSERT IGNORE INTO xp_transactions(pupil_id,activity_id,amount) VALUES(?,?,?)',[$pid,$aid,$a['xp_reward']]);
 if($insert->rowCount()){
  q('INSERT INTO pupil_xp(pupil_id,total) VALUES(?,?) ON DUPLICATE KEY UPDATE total=total+VALUES(total)',[$pid,$a['xp_reward']]);
  $date=substr((string)val('SELECT submitted_at FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]),0,10);
  if($a['response_mode']!=='none')q('INSERT IGNORE INTO learning_days VALUES(?,?)',[$pid,$date]);
 }
 $days=rows('SELECT day FROM learning_days WHERE pupil_id=? ORDER BY day',[$pid]);$run=0;$long=0;$prev=null;
 foreach($days as $day){$d=$day['day'];$run=$prev&&date('Y-m-d',strtotime($prev.' +1 day'))===$d?$run+1:1;$long=max($long,$run);$prev=$d;}
 if($prev&&$prev<date('Y-m-d',strtotime('-1 day')))$run=0;
 q('INSERT INTO pupil_streaks VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE current_streak=VALUES(current_streak),longest_streak=VALUES(longest_streak),last_activity_date=VALUES(last_activity_date)',[$pid,$run,$long,$prev]);
 sync_assessments($pid,(int)$a['lesson_id']);award_badges($pid);
}
function sync_assessments(int $pid,int $lid):void{
 foreach(rows('SELECT id FROM assessments WHERE lesson_id=?',[$lid]) as $assessment){
  $id=(int)$assessment['id'];$items=rows('SELECT c.status,c.score,c.max_score FROM activities a LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.assessment_id=? AND a.published=1',[$pid,$id]);
  if(!$items)continue;$complete=true;$graded=true;$score=0;$max=0;
  foreach($items as $item){if(!completion_ok($item))$complete=false;if($item['score']===null)$graded=false;else{$score+=(float)$item['score'];$max+=(float)$item['max_score'];}}
  if($complete)q("INSERT INTO assessment_attempts(pupil_id,assessment_id,status,score,max_score,completed_at) VALUES(?,?,'submitted',?,?,NOW()) ON DUPLICATE KEY UPDATE status=IF(status='reviewed',status,VALUES(status)),score=IF(reviewed_by IS NULL,VALUES(score),score),max_score=IF(reviewed_by IS NULL,VALUES(max_score),max_score),completed_at=COALESCE(completed_at,NOW())",[$pid,$id,$graded?$score:null,$graded?$max:null]);
 }
}
function refresh_progress(int $pid,int $lid):void{sync_assessments($pid,$lid);}
function award_badges(int $pid):void{
 $v=['activity'=>(int)val("SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND status IN ('approved','completed')",[$pid]),'lesson'=>(int)val('SELECT COUNT(*) FROM pupil_progress WHERE pupil_id=? AND completed_at IS NOT NULL',[$pid]),'xp'=>(int)val('SELECT total FROM pupil_xp WHERE pupil_id=?',[$pid]),'streak'=>(int)val('SELECT longest_streak FROM pupil_streaks WHERE pupil_id=?',[$pid]),'reading'=>(int)val("SELECT COUNT(*) FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id=? AND c.status IN ('approved','completed') AND a.type='reading'",[$pid]),'perfect'=>(int)val("SELECT COUNT(*) FROM assessment_attempts WHERE pupil_id=? AND status IN ('reviewed','submitted') AND score=max_score AND max_score>0",[$pid])];$v['level']=(int)val('SELECT COUNT(*) FROM pupil_progress p JOIN lessons l ON l.id=p.lesson_id JOIN modules m ON m.id=l.module_id WHERE p.pupil_id=? AND p.completed_at IS NOT NULL AND m.level_id=1',[$pid]);
 foreach(rows('SELECT * FROM badges WHERE active=1') as $b)if(($v[$b['rule_type']]??0)>=$b['threshold_value'])q('INSERT IGNORE INTO pupil_badges(pupil_id,badge_id) VALUES(?,?)',[$pid,$b['id']]);
 q('INSERT IGNORE INTO pupil_rewards(pupil_id,reward_id) SELECT ?,id FROM rewards WHERE active=1 AND required_xp<=?',[$pid,$v['xp']]);
}
function progress_stats(int $pid):array{
 $s=one('SELECT * FROM pupil_streaks WHERE pupil_id=?',[$pid])??['current_streak'=>0,'longest_streak'=>0,'last_activity_date'=>null];
 if(($s['last_activity_date']??'')<date('Y-m-d',strtotime('-1 day')))$s['current_streak']=0;
 return $s+['xp'=>(int)val('SELECT total FROM pupil_xp WHERE pupil_id=?',[$pid]),'completed'=>(int)val('SELECT COUNT(*) FROM pupil_progress WHERE pupil_id=? AND completed_at IS NOT NULL',[$pid]),'approved'=>(int)val("SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND status IN ('approved','completed')",[$pid]),'pending'=>(int)val("SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND status='submitted'",[$pid])];
}
function checked_image(string $path):string{
 if(!preg_match('~^assets/(module/|uploads/|avatars/|images/level1/lesson[0-9]{2}/|images/level2[ab]/)?[a-zA-Z0-9_.-]+\.(png|jpe?g|webp)$~',$path)||!is_file(__DIR__.'/../public/'.$path))fail('Choose an existing image from the media library.');return $path;
}
function save_uploaded_image(string $field):string{
 if(empty($_FILES[$field])||$_FILES[$field]['error']!==UPLOAD_ERR_OK)fail('Choose an image smaller than 4 MB.');$f=$_FILES[$field];if($f['size']>4*1024*1024)fail('Image must be smaller than 4 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
 if(!$ext||!getimagesize($f['tmp_name']))fail('Upload a PNG, JPEG, or WebP image.');
 $dir=__DIR__.'/../public/assets/uploads';if(!is_dir($dir))mkdir($dir,0755,true);
 $name=bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))fail('Upload could not be saved.');return 'assets/uploads/'.$name;
}

// Keep the gallery and single-image fields compatible with existing imports.
function activity_images(array $a):array{
 $paths=json_decode($a['image_paths']??'[]',true);
 if(!is_array($paths))$paths=[];
 $paths=array_values(array_filter($paths,fn($v)=>is_string($v)&&trim($v)!==''));
 if(!$paths&&!empty($a['image_path']))$paths=[(string)$a['image_path']];
 return array_values(array_unique($paths));
}
function visual_manifest():array{
 static $m=null;if($m===null){$f=__DIR__.'/../database/visual-manifest.json';$m=is_file($f)?json_decode(file_get_contents($f),true):[];}return $m?:[];
}
function local_image_file(string $path):?string{
 if(!preg_match('~^assets/(?:module/|uploads/|avatars/|images/level1/lesson[0-9]{2}/|images/level2[ab]/)?[a-zA-Z0-9_.-]+\.(?:png|jpe?g|webp)$~D',$path))return null;
 $root=realpath(__DIR__.'/../public');$f=realpath($root.'/'.$path);
 return $f&&str_starts_with($f,$root.DIRECTORY_SEPARATOR)&&is_file($f)&&is_readable($f)?$f:null;
}
function resolved_image_path(string $path):?string{
 if(local_image_file($path))return $path;
 // Original module copies have byte-identical filenames. Never substitute custom uploads.
 if(preg_match('~^assets/images/level1/lesson[0-9]{2}/((?:image-[0-9]+|page-[0-9]+)\.(?:jpeg|jpg|png))$~D',$path,$m)){
  $old='assets/module/'.$m[1];if(local_image_file($old))return $old;
 }
 return null;
}
function level_label(int $id):string{return [1=>'Level 1',2=>'Level 2A',3=>'Level 2B',4=>'Level 3',5=>'Level 4',6=>'Level 5',7=>'Level 6',8=>'Level 7'][$id]??'Level '.$id;}
function selected_start_level(int $fallback=1):int{
 $id=(int)($_POST['level_id']??$fallback);
 if(!val('SELECT id FROM bulig_levels WHERE id=?',[$id]))fail('Choose a valid starting level.');
 return $id;
}

function pupil_details_input(int $pupilId=0):array{
 $old=$pupilId?one('SELECT sex,lrn FROM pupil_details WHERE pupil_id=?',[$pupilId]):null;
 $sex=trim((string)($_POST['sex']??($old['sex']??'')));
 $lrn=trim((string)($_POST['lrn']??($old['lrn']??'')));
 if(!in_array($sex,['male','female'],true)&&!($pupilId&&$sex===''))fail('Choose Male or Female for the pupil.');
 if($lrn!==''&&!preg_match('/^[0-9]{12}$/D',$lrn))fail('Enter a 12-digit LRN, or leave it blank if it is not available yet.');
 if($lrn!==''&&val('SELECT pupil_id FROM pupil_details WHERE lrn=? AND pupil_id<>?',[$lrn,$pupilId]))fail('This LRN is already assigned to a pupil.');
 return [$sex?:null,$lrn?:null];
}
function save_pupil_details(int $id,array $details):void{
 try{if(val('SELECT pupil_id FROM pupil_details WHERE pupil_id=?',[$id]))q('UPDATE pupil_details SET sex=?,lrn=? WHERE pupil_id=?',[$details[0],$details[1],$id]);else q('INSERT INTO pupil_details(pupil_id,sex,lrn) VALUES(?,?,?)',[$id,$details[0],$details[1]]);}
 catch(PDOException $e){if(($e->errorInfo[1]??0)===1062)fail('This LRN is already assigned to a pupil.');throw $e;}
}
function profile_avatar_choices():array{
 return ['boy-1'=>['Boy 1 · Side-part hair','male'],'boy-2'=>['Boy 2 · Curly hair','male'],'boy-3'=>['Boy 3 · Glasses','male'],'girl-1'=>['Girl 1 · Bob haircut','female'],'girl-2'=>['Girl 2 · Braids','female'],'girl-3'=>['Girl 3 · Ponytail and glasses','female']];
}

function level_available(int $pid,int $level):bool{
 if(!(int)val('SELECT published FROM bulig_levels WHERE id=?',[$level]))return false;
 $start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);
 if(!$start||$level<$start)return false;
 if(!val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1',[$level]))return false;
 foreach(rows('SELECT id,published FROM bulig_levels WHERE id>=? AND id<? ORDER BY id',[$start,$level]) as $prior){
  if(!$prior['published'])return false;
  $counts=one('SELECT COUNT(*) total,COUNT(p.completed_at) done FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1',[$pid,$prior['id']]);
  if(!$counts['total']||$counts['total']!=$counts['done'])return false;
 }
 return true;
}
function lesson_level(int $lid):int{return (int)val('SELECT m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?',[$lid]);}
function level2_manifest():array{static $m;return $m??=json_decode(file_get_contents(__DIR__.'/../database/level2-manifest.json'),true);}
function next_learning_lesson(int $pid):?array{
 foreach(rows('SELECT l.*,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE l.published=1 AND p.completed_at IS NULL ORDER BY m.level_id,l.position',[$pid]) as $l)if(lesson_available($pid,(int)$l['id']))return $l;
 return null;
}
