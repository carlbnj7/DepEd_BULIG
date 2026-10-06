<?php
/* After feedback, go back to the same Activity history tab and pupil filter. */
/* Drawing answers (draw in the app or upload a photo) are only for the real drawing activities of Level 1. */
function draw_allowed(array $a):bool{return ($a['response_mode']??'')==='drawing'&&lesson_level((int)$a['lesson_id'])===1;}
function review_back():string{$b=(string)($_POST['back']??'');return preg_match('~^\?page=review&tab=(todo|done|all)(&pupil=[0-9]+)?$~',$b)?$b:'?page=review';}
function action():void{
 check_csrf();$action=(string)($_POST['action']??'');
 if(in_array($action,['push_subscribe','push_prefs','push_off','push_test'],true))push_action($action);
 if($action==='login'){
  $id=trim((string)($_POST['public_id']??''));$role=(string)($_POST['role']??'pupil');$key=hash('sha256',strtolower($id));
  if(val('SELECT COUNT(*) FROM login_attempts WHERE identifier_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$key])>=8){audit('login_locked',mb_substr($role.' · '.$id,0,60));fail('Too many attempts. Please wait 15 minutes.',429);}
  $u=one('SELECT * FROM users WHERE public_id=? AND role=? AND active=1',[$id,$role]);
  if(!$u||!password_verify((string)($_POST['password']??''),$u['password_hash'])){q('INSERT INTO login_attempts(identifier_hash) VALUES(?)',[$key]);audit('login_failed',mb_substr($role.' · '.$id,0,60));fail('The ID or password is incorrect.');}
  q('DELETE FROM login_attempts WHERE identifier_hash=?',[$key]);if($role==='admin')admin_pin_start($u);session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));if($role==='pupil'){$_SESSION['pupil_welcome']=1;$_SESSION['dl_offer']=1;}if($role==='teacher')$_SESSION['teacher_welcome']=1;if($role!=='admin'){$_SESSION['offer_save']=1;$_SESSION['hello']=1;}audit('login',$role);go('?page=dashboard');
 }
 if($action==='key_login'){
  /* One scan of a sign-in ticket: the QR code opens a "Hi, NAME!" page, and this button signs the pupil in. */
  $u=card_user((string)($_POST['key']??''));if(!$u){audit('login_failed','ticket');fail('This sign-in ticket no longer works. Ask your teacher for a new ticket, or sign in with your Pupil ID and password.');}
  session_regenerate_id(true);$_SESSION=[];$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['pupil_welcome']=1;$_SESSION['dl_offer']=1;$_SESSION['offer_save']=1;$_SESSION['hello']=1;audit('login','pupil · ticket');go('?page=dashboard');
 }
 if($action==='card_new'||$action==='cards_starter'){
  $u=require_role('teacher');if(!cards_ready())fail('Ask the administrator to import 018_pupil_cards.sql first.');$sec=(int)($_POST['section']??0);$back='?page=cards'.($sec?'&section='.$sec:'');
  if($action==='card_new'){$id=(int)($_POST['id']??0);own_pupil($id);$n=card_issue($id,true);$p=one('SELECT name,public_id FROM users WHERE id=?',[$id]);audit('card_new',(string)$p['public_id']);
   flash('New ticket for '.$p['name'].'. Starter password: '.$n['pw'].'. The old QR code no longer works.');go($back);}
  $ids=array_map('intval',array_column(rows('SELECT t.pupil_id FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id WHERE t.teacher_id=? AND u.active=1'.($sec?' AND EXISTS(SELECT 1 FROM pupil_sections ps WHERE ps.pupil_id=u.id AND ps.section_id=?)':''),$sec?[$u['id'],$sec]:[$u['id']]),'pupil_id'));$done=0;
  foreach($ids as $id){$h=(string)val('SELECT password_hash FROM users WHERE id=?',[$id]);if(password_verify('12345678',$h)){card_issue($id,true);$done++;}}
  audit('cards_starter',(string)$done);flash($done?$done.' '.($done===1?'pupil has':'pupils have').' a new starter password. Print their tickets now.':'Every pupil here already has their own password.');go($back);
 }
 if($action==='quick_login'){
  if(!saved_login_ready())saved_login_json(['ok'=>false,'error'=>'Saved sign-in is not ready yet. Please sign in with your ID.']);
  $u=saved_login_use((string)($_POST['token']??''));
  if(!$u){audit('login_failed','saved sign-in');saved_login_json(['ok'=>false,'error'=>'This saved sign-in has expired. Please sign in with your ID and password.']);}
  session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));if($u['role']==='pupil'){$_SESSION['pupil_welcome']=1;$_SESSION['dl_offer']=1;}else $_SESSION['teacher_welcome']=1;$_SESSION['hello']=1;
  audit('login',$u['role']);saved_login_json(['ok'=>true,'url'=>'?page=dashboard','profile'=>saved_login_profile($u)]);
 }
 if($action==='save_login'){
  $u=current_user();if(!$u||!in_array($u['role'],['pupil','teacher'],true))saved_login_json(['ok'=>false,'error'=>'Only pupils and teachers can be saved.']);
  if(!saved_login_ready())saved_login_json(['ok'=>false,'error'=>'Saved sign-in is not ready yet.']);
  $old=(string)($_POST['replace']??'');if($old!=='')saved_login_forget($old);
  $t=saved_login_create($u);audit('saved_login','device');saved_login_json(['ok'=>true,'token'=>$t,'profile'=>saved_login_profile($u)]);
 }
 if($action==='forget_login'){if(saved_login_ready())saved_login_forget((string)($_POST['token']??''));saved_login_json(['ok'=>true]);}
 if($action==='tour_seen'){$u=current_user();if($u)q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['tour_seen_'.(int)$u['id'],date('Y-m-d H:i:s')]);saved_login_json(['ok'=>(bool)$u]);}
 if($action==='logout'){$_SESSION=[];session_destroy();go('?page=login');}
 if($action==='save_section'){
  $u=require_role('teacher');$sid=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$grade=(int)($_POST['grade_level']??0);
  if(!$name||strlen($name)>80||$grade<1||$grade>6)fail('Enter a section name and grade from 1 to 6.');
  db()->beginTransaction();try{
   q('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);if($sid){$old=owned_section($sid,(int)$u['id']);if($grade!=(int)$old['grade_level']&&val('SELECT COUNT(*) FROM pupil_sections WHERE section_id=?',[$sid]))fail('Move pupils to another section before changing this section’s grade.');}
   if(val('SELECT id FROM sections WHERE teacher_id=? AND grade_level=? AND name=? AND id<>?',[$u['id'],$grade,$name,$sid]))fail('You already have this section for this grade.');
   if($sid){q('UPDATE sections SET name=?,grade_level=? WHERE id=?',[$name,$grade,$sid]);q('UPDATE pupils p JOIN pupil_sections ps ON ps.pupil_id=p.user_id SET p.section=?,p.grade_level=? WHERE ps.section_id=?',[$name,$grade,$sid]);}
   else{q('INSERT INTO sections(teacher_id,grade_level,name) VALUES(?,?,?)',[$u['id'],$grade,$name]);$sid=(int)db()->lastInsertId();}audit('save_section',(string)$sid);db()->commit();flash('Section saved.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=sections');
 }
 if(str_starts_with($action,'import_')){import_actions($action);return;}
 if(in_array($action,ADMIN_ACTIONS,true)){admin_actions($action);return;}
 if(in_array($action,['admin_pin','admin_pin_create','admin_pin_cancel','change_admin_pin'],true)){admin_pin_actions($action);return;}
 if($action==='create_account'){
  $u=require_role('admin','teacher');$role=$u['role']==='admin'?'teacher':'pupil';$name=trim((string)($_POST['name']??''));$password=$role==='pupil'?'12345678':(string)($_POST['password']??'');
  if(strlen($name)<2||strlen($name)>150||strlen($password)<8||strlen($password)>72)fail('Use a name of 2–150 characters and a password of 8–72 characters.');
  db()->beginTransaction();try{
   $next=(int)val('SELECT next_value FROM id_sequences WHERE kind=? FOR UPDATE',[$role]);q('UPDATE id_sequences SET next_value=next_value+1 WHERE kind=?',[$role]);$public=($role==='teacher'?'T':'').$next;
   q('INSERT INTO users(public_id,role,name,password_hash) VALUES(?,?,?,?)',[$public,$role,$name,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();
   if($role==='teacher'){$sx=(string)($_POST['teacher_sex']??'');if(!in_array($sx,['female','male'],true))fail('Choose Male or Female for the teacher.');q('INSERT INTO teachers VALUES(?)',[$id]);tf_set_sex($id,$sx);}else{
    $section=owned_section((int)($_POST['section_id']??0),(int)$u['id']);$grade=(int)$section['grade_level'];$level=selected_start_level();$details=pupil_details_input();
    q('INSERT INTO pupils(user_id,grade_level,section) VALUES(?,?,?)',[$id,$grade,$section['name']]);save_pupil_details($id,$details);q('INSERT INTO pupil_sections VALUES(?,?)',[$id,$section['id']]);q('INSERT INTO teacher_pupils VALUES(?,?)',[$u['id'],$id]);q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?)',[$id,$level,$u['id']]);$password=new_pupil_password($id);
   }audit('create_'.$role,$public);db()->commit();if($role==='pupil')$_SESSION['new_pupil']=$id;else flash('Account created. '.$role.' ID: '.$public.'. Share the password you set privately.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=accounts');
 }
 if($action==='update_account'){
  $u=require_role('admin','teacher');$id=(int)($_POST['id']??0);$target=one('SELECT * FROM users WHERE id=?',[$id]);if(!$target)fail('Account not found.',404);
  if($u['role']==='teacher'){own_pupil($id);}elseif($target['role']!=='teacher')fail('Administrators manage teacher accounts here.',403);
  $name=trim((string)($_POST['name']??''));if(!$name||strlen($name)>150)fail('Enter a valid name.');$password=(string)($_POST['password']??'');
  if($password!==''&&(strlen($password)<8||strlen($password)>72))fail('Use a password of 8–72 characters.');
  db()->beginTransaction();try{
   q('UPDATE users SET name=?,active=? WHERE id=?',[$name,isset($_POST['active'])?1:0,$id]);if($password!==''||!isset($_POST['active']))saved_login_clear($id);if($password!==''){q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);card_forget_starter($id);}
   if($u['role']==='teacher'){
    $section=owned_section((int)($_POST['section_id']??0),(int)$u['id']);
    save_pupil_details($id,pupil_details_input($id));
    q('UPDATE pupils SET grade_level=?,section=? WHERE user_id=?',[$section['grade_level'],$section['name'],$id]);q('INSERT INTO pupil_sections VALUES(?,?) ON DUPLICATE KEY UPDATE section_id=VALUES(section_id)',[$id,$section['id']]);$level=selected_start_level((int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$id])?:1);q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE level_id=VALUES(level_id),assigned_by=VALUES(assigned_by),assigned_at=NOW()',[$id,$level,$u['id']]);
   }audit('update_account',(string)$id);db()->commit();flash('Account updated.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=accounts');
 }
 if($action==='change_password'){
  $u=require_role('pupil','teacher','admin');$old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');
  if(!password_verify($old,$u['password_hash']))fail('Your current password is incorrect.');
  if(strlen($new)<8||strlen($new)>72)fail('Use a new password of 8–72 characters.');
  if($new!==(string)($_POST['confirm_password']??''))fail('The new passwords do not match.');
  q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);saved_login_clear((int)$u['id']);card_forget_starter((int)$u['id']);session_regenerate_id(true);audit('change_password','');flash('Password changed. Use your new password next time.');go($u['role']==='pupil'?'?page=settings':'?page=profile');
 }
 if($action==='set_start_level'){
  $u=require_role('teacher');$tid=(int)$u['id'];$back=(string)($_POST['back']??'');if(!preg_match('~^\?page=manage(&id=[0-9]+)?$~',$back))$back='?page=manage';
  $pupils=isset($_POST['pupil'])?[(int)$_POST['pupil']]:array_map('intval',(array)($_POST['pupils']??[]));$pupils=array_values(array_unique(array_filter($pupils)));
  if(!$pupils){flash('Tick at least one pupil first.');go($back);}
  $level=selected_start_level();if(!(int)val('SELECT published FROM bulig_levels WHERE id=?',[$level]))fail('Choose a level that is available.');
  foreach($pupils as $pid)if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]))fail('This pupil is not assigned to you.',403);
  foreach($pupils as $pid)q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE level_id=VALUES(level_id),assigned_by=VALUES(assigned_by),assigned_at=NOW()',[$pid,$level,$tid]);
  foreach($pupils as $pid)push_queue($pid,'lessons',level_label($level).' is open!','Your teacher chose '.level_label($level).' for you. Your next adventure is waiting.','?page=lessons&level='.$level);
  audit('start_level',level_label($level).' for '.count($pupils).' pupils');flash('Starting level set to '.level_label($level).' for '.count($pupils).' '.(count($pupils)===1?'pupil':'pupils').'.');go($back);
 }
 if($action==='class_done'){
  $u=require_role('teacher');$tid=(int)$u['id'];$back=(string)($_POST['back']??'');if(!preg_match('~^\?page=(class_demo|pupil|manage)(&[a-zA-Z0-9_=]+)*(#learning-path)?$~',$back))$back='?page=dashboard';
  $level=(int)($_POST['level']??0);$scope=(string)($_POST['scope']??'');$undo=(int)($_POST['undo']??0);
  $pupils=isset($_POST['pupil'])?[(int)$_POST['pupil']]:array_map('intval',(array)($_POST['pupils']??[]));$pupils=array_values(array_unique(array_filter($pupils)));
  if(!$pupils){flash('Choose at least one pupil who was in class.');go($back);}
  foreach($pupils as $pid)if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]))fail('This pupil is not assigned to you.',403);
  db()->beginTransaction();try{
   if($undo){$n=class_done_undo($pupils[0],$undo);audit('class_done_undo','Pupil '.$pupils[0].', lesson '.$undo.', '.$n.' activities');db()->commit();flash($n?'The "Done in class" mark was removed from that lesson.':'Nothing to undo in that lesson.');go($back);}
   if(!in_array($scope,['activity','lesson','level','lessons'],true))fail('Choose what you finished.');
   $marked=0;$what=$scope==='level'?level_label($level):($scope==='activity'?'this activity':($scope==='lesson'?'this lesson':'the chosen work'));
   $lids=$scope==='lessons'?array_map('intval',(array)($_POST['lessons']??[])):[];$acts=$scope==='lessons'?array_map('intval',(array)($_POST['activities']??[])):[];if($scope==='lessons'&&!$lids&&!$acts){db()->rollBack();flash('Tick at least one lesson or activity first.');go($back);}
   foreach($pupils as $pid){
    $aids=[];if($scope==='lessons'){foreach($lids as $lid)$aids=array_merge($aids,class_done_activities($pid,'lesson',$lid));foreach($acts as $aid)$aids=array_merge($aids,class_done_activities($pid,'activity',$aid));}
    else $aids=class_done_activities($pid,$scope,$scope==='level'?$level:(int)($_POST[$scope==='activity'?'aid':'lid']??0));
    $n=class_done_apply($tid,$pid,$aids);$marked+=$n;
    if($n)push_queue($pid,'lessons','Your next lesson is open!','Your class did it together. Your next step in BULIG is ready.','?page=dashboard');
    if($n)class_done_note_set($pid,$scope==='level'?'Your teacher marked '.level_label($level).' as done.'.(level_available($pid,$level+1)?' '.level_label($level+1).' is open now.':''):'Your teacher marked '.($scope==='activity'?'an activity':'your lesson').' as done. Your next step is ready.');
   }
   audit('class_done',$what.' for '.count($pupils).' pupils, '.$marked.' activities');db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Marked as done in class: '.$what.' for '.count($pupils).' '.(count($pupils)===1?'pupil':'pupils').'.');go($back);
 }
 if($action==='teacher_character'){
  $u=require_role('teacher','admin');$c=(string)($_POST['character']??'');if(!in_array($c,['female','male'],true))fail('Choose a character.');
  tf_set_sex((int)$u['id'],$c);flash('Your '.($u['role']==='admin'?'admin':'teacher').' character is saved.');go('?page=profile');
 }
 if($action==='choose_avatar'){
  $u=require_role('pupil');$key=(string)($_POST['avatar_key']??'');
  if(!isset(profile_avatar_choices()[$key]))fail('Choose one of the available profile pictures.');
  $path='assets/avatars/'.$key.'.png';if(!local_image_file($path))fail('This avatar has not been uploaded to the website yet. Please tell your teacher.');
  q('UPDATE users SET avatar_path=? WHERE id=?',[$path,$u['id']]);flash('Your profile picture is updated.');go('?page=profile');
 }
 if($action==='profile'){$u=require_role('pupil','teacher','admin');$path=save_uploaded_image('avatar');q('UPDATE users SET avatar_path=? WHERE id=?',[$path,$u['id']]);flash('Profile photo updated.');go('?page=profile');}
 if($action==='finish_lesson'){
  $u=require_role('pupil');$lid=(int)($_POST['lesson_id']??0);db()->beginTransaction();try{q('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);finish_lesson((int)$u['id'],$lid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=lesson&id='.$lid.'&finished=1');
 }
 if(in_array($action,['draft','submit'],true)){
  $u=require_role('pupil');$pid=(int)$u['id'];$aid=(int)($_POST['activity_id']??0);$answer=trim((string)($_POST['response']??''));$drawing=(string)($_POST['drawing']??'');$transcript=trim((string)($_POST['transcript']??''));
  if(strlen($answer)>12000||strlen($transcript)>12000||strlen($drawing)>1500000)fail('This response is too large.');
  if($drawing!==''&&!preg_match('~^data:image/(png|jpeg);base64,[a-zA-Z0-9+/=]+$~',$drawing))fail('Invalid drawing.');
  $audio=$action==='submit'?(string)($_POST['audio']??''):'';if(isset($_POST['audio_card'])&&!is_array($_POST['audio_card']))fail('Invalid recording.');$heard=($_POST['heard']??'')==='1';if(strlen($audio)>12500000)fail('This recording is too long. Please record again.');
  db()->beginTransaction();try{
   q('SELECT id FROM users WHERE id=? FOR UPDATE',[$pid]);$a=allowed_activity($pid,$aid);if(!draw_allowed($a))$drawing='';$old=one('SELECT * FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);
   if(completion_ok($old)){db()->commit();submission_reply($a,true,'Already saved. You can continue.',0);}
   if($action==='draft'){
    q('INSERT INTO activity_drafts(pupil_id,activity_id,response,drawing,transcript) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response),drawing=VALUES(drawing),transcript=VALUES(transcript)',[$pid,$aid,$answer,$drawing?:null,$transcript?:null]);db()->commit();header('Content-Type: application/json');echo json_encode(['saved'=>true]);exit;
   }
   $question=one('SELECT * FROM questions WHERE activity_id=?',[$aid]);$exact=$question&&$question['grading']==='exact'&&lesson_level((int)$a['lesson_id'])!==1;/* Level 1 answers are spoken: the teacher checks them */$mode=$exact||($a['response_mode']==='drawing'&&!draw_allowed($a))?'answer':$a['response_mode'];
   /* Level 1 is oral: the answer is a voice recording, or the teacher heard it in class. */
   /* Level 4 is read aloud the same way: the reading is recorded and its time is kept. */
   $l4read=$mode==='answer'&&lesson_level((int)$a['lesson_id'])===5&&level4_passage_parts($a)!==null;
   $oral=$mode==='answer'&&!$exact&&(lesson_level((int)$a['lesson_id'])===1||$l4read);$apath=null;
   if($oral){if($audio===''&&!$heard)fail('Record your answer first.');$secs=max(0,min(3600,(int)($_POST['secs']??0)));
    if($l4read)$answer=($audio!==''?'Read aloud (voice recording)':'Answered out loud. Done.').($audio!==''&&$secs?' · time '.intdiv($secs,60).':'.sprintf('%02d',$secs%60):'');
    elseif($answer==='')$answer=$audio!==''?'Spoken answer (voice recording)':'Answered out loud. Done.';if($audio!==''&&l1_audio_supported())$apath=l1_save_audio($audio,$pid,$aid);}
   if(in_array($mode,['answer','drawing'],true)&&$answer===''&&$drawing==='')fail('Draw it, upload a photo, or tell about your drawing before continuing.');
   if($mode==='answer'&&$answer==='')fail('Speak or type an answer first.');
   $ok=true;$score=null;$max=null;$outcome=['none'=>'viewed','perform'=>'performed','drawing'=>'recorded','answer'=>'recorded'][$mode];
   if($exact){
    $correct=rows('SELECT content FROM answers WHERE question_id=? AND correct=1',[$question['id']]);if(!$correct)fail('This question needs an answer key. Please let your teacher know.');
    $ok=false;foreach($correct as $c)if(trim(normalize_answer($answer))===trim(normalize_answer($c['content'])))$ok=true;
    $score=$ok?1:0;$max=1;$outcome=$ok?'correct':'recorded';
   }
   $status=$ok?'completed':'retry';$when=offline_answer_time()??date('Y-m-d H:i:s');
   q('INSERT INTO activity_completion(pupil_id,activity_id,response,drawing,transcript,prompt_snapshot,activity_revision,status,submitted_at,outcome,score,max_score) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response),drawing=VALUES(drawing),transcript=VALUES(transcript),prompt_snapshot=VALUES(prompt_snapshot),activity_revision=VALUES(activity_revision),status=VALUES(status),submitted_at=VALUES(submitted_at),outcome=VALUES(outcome),score=VALUES(score),max_score=VALUES(max_score)',[$pid,$aid,$answer,$drawing?:null,$transcript?:null,level2_prompt_snapshot($a),$a['revision'],$status,$when,$outcome,$score,$max]);
   if(!empty($_POST['audio_card'])&&is_array($_POST['audio_card'])&&in_array(lesson_level((int)$a['lesson_id']),[2,3,4,7],true)&&l2a_audio_supported()&&($ap=l2a_save_audios($_POST['audio_card'],$pid,$aid)))q('UPDATE activity_completion SET audio_paths=? WHERE pupil_id=? AND activity_id=?',[json_encode($ap),$pid,$aid]);
   if($apath)q('UPDATE activity_completion SET audio_path=? WHERE pupil_id=? AND activity_id=?',[$apath,$pid,$aid]);
   if(offline_answer_time())q('INSERT INTO audit_log(actor_id,action,details) VALUES(?,?,?)',[$pid,'offline_answer','activity '.$aid.' at '.$when]);
   $cid=(int)val('SELECT id FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[$cid,$answer,$status,$pid]);
   q('INSERT INTO pupil_progress(pupil_id,lesson_id,last_activity_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE last_activity_id=VALUES(last_activity_id)',[$pid,$a['lesson_id'],$aid]);
   if($a['expected_text']&&$transcript!==''){$match=word_match($a['expected_text'],$transcript);q('INSERT INTO reading_assessments(pupil_id,activity_id,transcript,expected_text,word_match_percent,detail) VALUES(?,?,?,?,?,?)',[$pid,$aid,$transcript,$a['expected_text'],$match['percent'],json_encode($match['words'])]);}
   if($ok){q('DELETE FROM activity_drafts WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);update_learning($pid,$aid);}
   db()->commit();$message=$ok?($exact?'Correct! Great work.':($mode==='none'?'You’re ready for the next step.':($mode==='perform'?'Activity completed. Well done!':($oral?'Your answer is saved. Great speaking!':'Your response is saved. Thank you for sharing!')))):'Not quite yet. Listen again and give it another try.';
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  submission_reply($a,$ok,$message,$ok?(int)$a['xp_reward']:0);
 }
 if($action==='review'){
  $u=require_role('teacher');$cid=(int)($_POST['completion_id']??0);$c=one('SELECT * FROM activity_completion WHERE id=?',[$cid]);if(!$c)fail('Response not found.',404);own_pupil((int)$c['pupil_id']);
  $feedback=trim((string)($_POST['feedback']??''));$fa=one('SELECT title,lesson_id FROM activities WHERE id=?',[(int)$c['activity_id']]);
  /* Level 1: the module rubric (or the lesson-plan checklist) for this lesson. */
  $rv=null;$rub=$fa&&isset($_POST['rubric'])&&l1_audio_supported()?l1_rubric(l1_position((int)$fa['lesson_id'])):null;
  if($rub){$rv=l1_rubric_values($rub,(array)$_POST['rubric']);if($rv===null)fail($rub['kind']==='rubric'?'Choose a score for each part of the rubric.':'Choose one rating first.');}
  /* Level 4: the module's scoring table (miscues, Oral Reading Score, Reading Level, Reading Speed). */
  $l4v=null;if($fa&&isset($_POST['miscue'])&&lesson_level((int)$fa['lesson_id'])===5){$act=one('SELECT * FROM activities WHERE id=?',[(int)$c['activity_id']]);$l4v=$act?l4_review_values($act,$_POST):null;if($l4v){$rv=$l4v;}}
  if(strlen($feedback)>3000||($feedback===''&&$rv===null))fail('Enter feedback of up to 3,000 characters.');
  if($rv!==null)q('UPDATE activity_completion SET rubric_scores=? WHERE id=?',[json_encode($l4v!==null?['l4'=>$l4v]:['v'=>$rv]),$cid]);
  if($l4v!==null)q('UPDATE activity_completion SET score=?,max_score=? WHERE id=?',[$l4v['score'],100,$cid]);
  q('UPDATE activity_completion SET feedback=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$feedback!==''?$feedback:$c['feedback'],$u['id'],$cid]);if($feedback!=='')q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[$cid,$feedback,'feedback',$u['id']]);audit($rv!==null?'score_response':'feedback_response',(string)$cid);if($fa&&$feedback!=='')push_queue((int)$c['pupil_id'],'teacher','Teacher '.explode(' ',trim((string)$u['name']))[0].' left you a note','Open “'.$fa['title'].'” to read it.','?page=lesson&id='.(int)$fa['lesson_id'].'&activity='.(int)$c['activity_id']);$_SESSION['tf_feedback']=explode(' ',trim((string)val('SELECT name FROM users WHERE id=?',[$c['pupil_id']])))[0];flash($rv!==null?($feedback!==''?'Score and feedback saved.':'Score saved.'):'Feedback saved. Pupil progression is unchanged.');go(review_back());
 }
 if($action==='review_assessment'){
  $u=require_role('teacher');$pid=(int)($_POST['pupil_id']??0);own_pupil($pid);$aid=(int)($_POST['assessment_id']??0);
  db()->beginTransaction();try{
   q('SELECT id FROM users WHERE id=? FOR UPDATE',[$pid]);$a=one('SELECT * FROM assessments WHERE id=?',[$aid]);if(!$a)fail('Assessment not found.',404);
   $missing=val("SELECT COUNT(*) FROM activities a LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.assessment_id=? AND a.published=1 AND (c.status IS NULL OR c.status NOT IN ('approved','completed'))",[$pid,$aid]);if($missing)fail('Complete every assessment response first.');
   $criteria=json_decode($a['rubric_criteria']??'[]',true)?:[];$scores=[];$sum=0;foreach($criteria as $i=>$label){$s=(int)($_POST['scores'][$i]??0);if($s<1||$s>4)fail('Rate each official criterion from 1 to 4.');$scores[$label]=$s;$sum+=$s;}
   q("INSERT INTO assessment_attempts(pupil_id,assessment_id,status,score,max_score,completed_at,rubric_scores,feedback,reviewed_by) VALUES(?,?,'reviewed',?,?,NOW(),?,?,?) ON DUPLICATE KEY UPDATE status='reviewed',score=VALUES(score),max_score=VALUES(max_score),completed_at=NOW(),rubric_scores=VALUES(rubric_scores),feedback=VALUES(feedback),reviewed_by=VALUES(reviewed_by)",[$pid,$aid,$criteria?$sum:null,$criteria?count($criteria)*4:null,json_encode($scores),substr((string)($_POST['feedback']??''),0,3000),$u['id']]);
   refresh_progress($pid,(int)$a['lesson_id']);award_badges($pid);audit('review_assessment',$pid.':'.$aid);db()->commit();flash('Optional assessment feedback saved. Learning progression is unchanged.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go(review_back());
 }
 if($action==='save_lesson'){
  require_role('admin');$id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));if(!$title||strlen($title)>255)fail('Enter a lesson title.');$image=trim((string)($_POST['image_path']??''));if($image)checked_image($image);
  if(val('SELECT published FROM lessons WHERE id=?',[$id])!=(isset($_POST['published'])?1:0)&&val('SELECT COUNT(*) FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE a.lesson_id=?',[$id]))fail('This lesson already has pupil work. Its publication status must remain stable.');
  q('UPDATE lessons SET title=?,subtitle=?,objectives=?,image_path=?,published=? WHERE id=?',[$title,substr((string)$_POST['subtitle'],0,255),substr((string)$_POST['objectives'],0,12000),$image?:null,isset($_POST['published'])?1:0,$id]);audit('edit_lesson',(string)$id);flash('Lesson saved.');go('?page=content&id='.$id);
 }
 if($action==='save_activity'){
  require_role('admin');$id=(int)($_POST['id']??0);$a=one('SELECT * FROM activities WHERE id=?',[$id]);if(!$a)fail('Activity not found.',404);
  $title=trim((string)($_POST['title']??''));$prompt=trim((string)($_POST['prompt']??''));$type=(string)($_POST['type']??'');$xp=(int)($_POST['xp_reward']??10);$grading=(string)($_POST['grading']??'teacher');$mode=(string)($_POST['response_mode']??'answer');if(!in_array($mode,['answer','none','perform','drawing'],true))fail('Choose an interaction mode.');if($grading==='exact'||$mode==='drawing')$mode='answer';
  if(!$title||strlen($title)>255||!$prompt||strlen($prompt)>30000||!in_array($type,['open','sentence','reading','physical','group','drawing','choice','exact','reference'],true)||$xp<0||$xp>1000||!in_array($grading,['teacher','exact'],true))fail('Check the activity fields.');
  $imgs=array_values(array_filter(array_map('trim',explode("\n",(string)($_POST['image_paths']??'')))));foreach($imgs as $image)checked_image($image);
  $answers=array_values(array_filter(array_map('trim',explode("\n",(string)($_POST['answers']??'')))));$options=array_values(array_filter(array_map('trim',explode("\n",(string)($_POST['options']??'')))));
  if($grading==='exact'&&!$answers)fail('Exact grading requires at least one accepted answer.');if($type==='choice'&&!$options)fail('Add answer choices.');
  db()->beginTransaction();try{
   // Publishing changes after progress would silently change completion requirements.
   if((int)$a['published']!==(isset($_POST['published'])?1:0)&&val('SELECT COUNT(*) FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE a.lesson_id=?',[$a['lesson_id']]))fail('This lesson already has pupil work. Keep its published activity set stable.');
   q('UPDATE activities SET response_mode=?,title=?,type=?,instructions=?,prompt=?,image_path=?,image_paths=?,narration=?,expected_text=?,xp_reward=?,published=?,revision=revision+1 WHERE id=?',[$mode,$title,$type,substr((string)$_POST['instructions'],0,12000),$prompt,$imgs[0]??null,json_encode($imgs),substr((string)$_POST['narration'],0,30000),substr((string)($_POST['expected_text']??''),0,12000)?:null,$xp,isset($_POST['published'])?1:0,$id]);
   $qid=(int)val('SELECT id FROM questions WHERE activity_id=?',[$id]);q('UPDATE questions SET content=?,grading=? WHERE id=?',[$prompt,$grading,$qid]);q('DELETE FROM answers WHERE question_id=?',[$qid]);foreach(array_unique(array_merge($options,$answers)) as $answer)q('INSERT INTO answers(question_id,content,correct) VALUES(?,?,?)',[$qid,$answer,in_array($answer,$answers,true)?1:0]);
   audit('edit_activity',(string)$id);db()->commit();flash('Activity saved. Existing responses retain their original prompt.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=edit_activity&id='.$id);
 }
 if($action==='upload_media'){require_role('admin');$path=save_uploaded_image('media');audit('upload_media',$path);flash('Image uploaded: '.$path);go('?page=media');}
 if($action==='acknowledge'){$u=require_role('admin');q('UPDATE content_issues SET resolution=?,acknowledged_by=?,acknowledged_at=NOW() WHERE id=?',[substr((string)$_POST['resolution'],0,3000),$u['id'],(int)$_POST['id']]);flash('Content decision recorded.');go('?page=issues');}
 if($action==='save_reward'){
  require_role('admin');$id=(int)($_POST['id']??0);$title=trim((string)$_POST['title']);$xp=(int)$_POST['required_xp'];if(!$title||strlen($title)>100||$xp<0)fail('Check reward details.');q('UPDATE rewards SET title=?,required_xp=?,active=? WHERE id=?',[$title,$xp,isset($_POST['active'])?1:0,$id]);flash('Reward updated.');go('?page=settings&tab=badges');
 }
 if($action==='save_badge'){
  require_role('admin');$id=(int)$_POST['id'];$threshold=(int)$_POST['threshold_value'];$title=trim((string)$_POST['title']);if(!$title||strlen($title)>100||$threshold<1)fail('Check badge details.');q('UPDATE badges SET title=?,description=?,threshold_value=?,active=? WHERE id=?',[$title,substr((string)$_POST['description'],0,255),$threshold,isset($_POST['active'])?1:0,$id]);flash('Badge updated.');go('?page=settings&tab=badges');
 }
 if($action==='save_assessment'){
  require_role('admin');$id=(int)$_POST['id'];$criteria=array_values(array_filter(array_map('trim',explode("\n",(string)$_POST['criteria']))));if(count($criteria)>12)fail('Use up to 12 criteria.');q('UPDATE assessments SET rubric=?,rubric_criteria=? WHERE id=?',[substr((string)$_POST['rubric'],0,30000),json_encode($criteria),$id]);audit('edit_assessment',(string)$id);flash('Assessment rubric updated.');go('?page=content&id='.(int)$_POST['lesson_id']);
 }
 fail('Unknown action.',400);
}

function submission_reply(array $a,bool $ok,string $message,int $xp):never{
 $url='?page=lesson&id='.$a['lesson_id'].'&activity='.$a['id'].'&feedback=1';
 if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['saved'=>$ok,'message'=>$message,'xp'=>$xp,'url'=>$url]);exit;}
 flash($message);go($url);
}
