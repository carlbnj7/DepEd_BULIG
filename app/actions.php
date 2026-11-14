<?php
/* After feedback, go back to the same Activity history tab and pupil filter. */
/* Drawing answers (draw in the app or upload a photo) are only for the real drawing activities of Level 1. */
function draw_allowed(array $a):bool{return ($a['response_mode']??'')==='drawing'&&l1_screens((int)$a['lesson_id']);}
/** v216 Level 1 and the new levels made in the Content Studio share the Level 1 screens and scoring (stars, Victory). */
function l1_screens(int $lid):bool{$lv=lesson_level($lid);return $lv===1||(function_exists('studio_level')&&studio_level($lv)!==null);}
/* v216 answering hours and the family page switch */
function qx_actions(string $action):void{
 if($action==='answer_hours_save'){$u=require_role('admin');if(!empty($_SESSION['pin_pending']))fail('Verify your admin PIN first.',403);if(function_exists('rbac_can')&&!rbac_can('year'))fail('Not allowed.',403);
  $d=(string)($_POST['days']??'weekdays');$f=(string)($_POST['from']??'07:00');$t=(string)($_POST['to']??'20:00');
  if(!isset(QX_DAYS[$d])||!preg_match('/^\d{2}:\d{2}$/',$f)||!preg_match('/^\d{2}:\d{2}$/',$t)||$f===$t)fail('Choose the days and two different times.');
  admin_set('ans_on',!empty($_POST['on'])?'1':'0');admin_set('ans_days',$d);admin_set('ans_from',$f);admin_set('ans_to',$t);
  q('INSERT INTO audit_log(actor_id,action,details) VALUES(?,?,?)',[(int)$u['id'],'answer_hours',(!empty($_POST['on'])?'on ':'off ').$d.' '.$f.'-'.$t]);
  flash('Answering hours saved.');go('?page=settings&tab=schedule#answer-hours');}
 if($action==='parent_link'){$u=require_role('teacher');$pid=(int)($_POST['id']??0);if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[(int)$u['id'],$pid]))fail('Pupil not found.',404);
  $off=array_values(array_filter(explode(',',admin_setting('parent_off','')),fn($x)=>$x!==''&&$x!==(string)$pid));if(($_POST['off']??'')==='1')$off[]=(string)$pid;admin_set('parent_off',implode(',',$off));
  flash(($_POST['off']??'')==='1'?'The family page is turned off. The QR code no longer opens it.':'The family page is on again.');go('?page=parent_cards&id='.$pid);}
}
function review_back():string{$b=(string)($_POST['back']??'');return preg_match('~^\?page=(review&tab=(todo|done|all)(&pupil=[0-9]+)?|pupil&id=[0-9]+&tab=answers(&p=[0-9]+)?)$~',$b)?$b:'?page=qcheck';}
function action():void{
 check_csrf();$action=(string)($_POST['action']??'');unset($_SESSION['notif_live']);
 if(function_exists('rbac_guard_action'))rbac_guard_action($action);   // v210 viewer roles only look; system-wide actions are for the Central Admin
 if($action==='role_switch')role_switch_action();
 if(str_starts_with($action,'org_'))org_action($action);
 if(str_starts_with($action,'studio_'))studio_action($action);
 if($action==='tk_design'){$t=require_role('teacher');$d=(string)($_POST['design']??'b');if(!function_exists('id_design')||!isset(ID_THEMES[$d]))$d='b';q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['tk_design_'.(int)$t['id'],$d]);flash('Ticket design saved.');go('?page=cards'.(isset($_POST['section'])?'&section='.(int)$_POST['section']:''));}   // v216   // v215 Content Studio (app/studio.php)
 if(!in_array($action,['first_password','logout','login','key_login','quick_login'],true)&&teacher_must_change(current_user()))fail('Make your own password first.',403);
 if(in_array($action,['bug_report_update','bug_report_submit','bug_popup_ack','bug_adopt'],true))bugflow_action($action);
 if($action==='answer_hours_save'||$action==='parent_link')qx_actions($action);   // v216
 if(in_array($action,['week_plan_save','group_save','group_delete','group_message','pupil_note_add','pupil_note_delete'],true))ct_action($action);   // v216 class tools
 if($action==='reading_decision'||$action==='reading_verify_batch')rv_action();
 if($action==='streak_revive'||$action==='streak_lost_ok')streak_action($action);
 if($action==='offline_report')rs_offline_report(require_role('pupil'));
 if($action==='cleanup_merge'||$action==='cleanup_archive'||$action==='cleanup_clear')cleanup_action($action);
 if($action==='class_goal'){$t=require_role('teacher');rs_put('class_goal_'.(int)$t['id'],(string)max(50,min(20000,(int)($_POST['goal']??500))));flash('Weekly reading goal saved.');go('?page=dashboard#rs-challenge');}
 if(in_array($action,['message_preview','message_send'],true))messages_action($action);
 if($action==='message_reply')message_reply_action();
 if(in_array($action,['push_subscribe','push_prefs','push_off','push_test'],true))push_action($action);
 if(str_starts_with($action,'rc_'))rc_action($action);
 if($action==='chat_send'||$action==='chat_class')chat_action($action);   // v209 teacher and pupil chat
 if($action==='privacy_agree'||$action==='privacy_delete_ask')privacy_action($action);   // v209 data privacy
 if($action==='rate_app'||$action==='rate_later')rate_action($action);/* app ratings (v152) */
 if($action==='moved_seen')staff_action($action);
 if($action==='move_section')pupils_move_section(require_role('teacher'));
 if($action==='login'){
  $id=trim((string)($_POST['public_id']??''));$role=(string)($_POST['role']??'pupil');$key=hash('sha256',strtolower($id));
  if(login_tries_used($key)>=LOGIN_MAX_TRIES){audit('login_locked',mb_substr($role.' · '.$id,0,60));login_back($role,$id,$key);}
  /* One sign-in form for everyone (v172): BULIG finds the account by its ID and knows if it is a pupil, a teacher or
     the administrator (an old link may still send a role; then only that role is tried). */
  $pw=(string)($_POST['password']??'');$any=!in_array($role,['pupil','teacher','admin'],true);
  $cands=$any?rows('SELECT * FROM users WHERE public_id=? AND active=1',[$id]):rows('SELECT * FROM users WHERE public_id=? AND role=? AND active=1',[$id,$role]);
  if(!$cands&&($moved=account_id_moved($id))&&($any||$moved['role']===$role)&&(int)$moved['active'])$cands=[$moved];   // an ID from before the switch to the new IDs
  $u=null;foreach($cands as $c)if(password_verify($pw,$c['password_hash'])){$u=$c;break;}
  if($u)$role=$u['role'];elseif($any)$role='auto';
  if(!$u){q('INSERT INTO login_attempts(identifier_hash) VALUES(?)',[$key]);audit('login_failed',mb_substr($role.' · '.$id,0,60));login_back($role,$id,$key);}
  q('DELETE FROM login_attempts WHERE identifier_hash=?',[$key]);if(strcasecmp($id,(string)$u['public_id'])!==0)flash('Your '.$role.' ID is now '.$u['public_id'].'. Your old ID still works, but please use the new one.');session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));if($role==='pupil')$_SESSION['pupil_welcome']=1;if($role==='teacher'){$_SESSION['teacher_welcome']=1;$_SESSION['offer_save']=1;}$_SESSION['hello']=1;   /* v211 admins get the welcome too (the PIN step that showed it is gone) */audit('login',$role);go('?page=dashboard');
 }
 if($action==='key_login'){
  /* One scan of a sign-in ticket: the QR code opens a "Hi, NAME!" page, and this button signs the pupil in. */
  $u=card_user_any((string)($_POST['key']??''));if(!$u){audit('login_failed','ticket');fail('This sign-in ticket no longer works. Ask for a new one, or sign in with your ID and password.');}
  /* v211 a QR code signs in every time, even after the password is changed (a new password from the administrator makes a new QR code) */
  session_regenerate_id(true);$_SESSION=[];$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));
  if($u['role']==='pupil')$_SESSION['pupil_welcome']=1;else $_SESSION['teacher_welcome']=1;   // pupils: no save / download pop-ups after signing in (v180); saving is offered when signing out
  $_SESSION['hello']=1;audit('login',$u['role']==='pupil'?'pupil · ticket':($u['role']==='admin'?'admin · ID card':'teacher · pass'));go('?page=dashboard');
 }
 if($action==='card_new'||$action==='cards_starter'){
  $u=require_role('teacher');if(!cards_ready())fail('Ask the administrator to import 018_pupil_cards.sql first.');$sec=(int)($_POST['section']??0);$back='?page=cards'.($sec?'&section='.$sec:'');
  if($action==='card_new'){$id=(int)($_POST['id']??0);own_pupil($id);$b=(string)($_POST['back']??'');if(preg_match('~^\?page=pupil&id=[0-9]+&tab=account$~',$b))$back=$b;$n=card_issue($id,true);$p=one('SELECT name,public_id FROM users WHERE id=?',[$id]);audit('card_new',(string)$p['public_id']);
   flash('New ticket for '.$p['name'].'. Starter password: '.$n['pw'].'. The old QR code no longer works.');go($back);}
  $ids=array_map('intval',array_column(rows('SELECT t.pupil_id FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id WHERE t.teacher_id=? AND u.active=1'.($sec?' AND EXISTS(SELECT 1 FROM pupil_sections ps WHERE ps.pupil_id=u.id AND ps.section_id=?)':''),$sec?[$u['id'],$sec]:[$u['id']]),'pupil_id'));$done=0;
  foreach($ids as $id){$h=(string)val('SELECT password_hash FROM users WHERE id=?',[$id]);if(password_verify('12345678',$h)){card_issue($id,true);$done++;}}
  audit('cards_starter',(string)$done);flash($done?$done.' '.($done===1?'pupil has':'pupils have').' a new starter password. Print their tickets now.':'Every pupil here already has their own password.');go($back);
 }
 if($action==='quick_login'){
  if(!saved_login_ready())saved_login_json(['ok'=>false,'error'=>'Saved sign-in is not ready yet. Please sign in with your ID.']);
  $u=saved_login_use((string)($_POST['token']??''));
  if(!$u){audit('login_failed','saved sign-in');saved_login_json(['ok'=>false,'error'=>'This saved sign-in has expired. Please sign in with your ID and password.']);}
  session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));if($u['role']==='pupil')$_SESSION['pupil_welcome']=1;else $_SESSION['teacher_welcome']=1;$_SESSION['hello']=1;
  audit('login',$u['role']);saved_login_json(['ok'=>true,'url'=>'?page=dashboard','profile'=>saved_login_profile($u)]);
 }
 if($action==='save_login'){
  $u=current_user();if(!$u||!in_array($u['role'],['pupil','teacher'],true))saved_login_json(['ok'=>false,'error'=>'Only pupils and teachers can be saved.']);
  if(!saved_login_ready())saved_login_json(['ok'=>false,'error'=>'Saved sign-in is not ready yet.']);
  $old=(string)($_POST['replace']??'');if($old!=='')saved_login_forget($old);
  $t=saved_login_create($u);audit('saved_login','device');saved_login_json(['ok'=>true,'token'=>$t,'profile'=>saved_login_profile($u)]);
 }
 if($action==='forget_login'){if(saved_login_ready())saved_login_forget((string)($_POST['token']??''));saved_login_json(['ok'=>true]);}
 if($action==='friends_met'){$u=current_user();if($u)friends_mark_met((int)$u['id']);header('Content-Type: application/json');echo '{"ok":true}';exit;}
 if($action==='tour_seen'||$action==='george_met'||$action==='jarvis_met'){$u=current_user();if($u)q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[($action==='jarvis_met'?'jarvis_met_':($action==='george_met'?'george_met_':'tour_seen_')).(int)$u['id'],date('Y-m-d H:i:s')]);saved_login_json(['ok'=>(bool)$u]);}
 if($action==='logout'){$_SESSION=[];session_destroy();go('?page=login');}
 if($action==='delete_section'){sections_action($action);return;}
 if($action==='delete_pupil'||$action==='delete_teacher'){account_delete_action($action);return;}
 if($action==='delete_pupils'||$action==='delete_teachers')account_delete_many($action);
 if($action==='save_section'){
  $u=require_role('teacher');$sid=(int)($_POST['id']??0);$name=import_name_case(mb_substr((string)($_POST['name']??''),0,80));$grade=(int)($_POST['grade_level']??0);
  if(!$name||strlen($name)>80||!grade_ok($grade))fail('Enter a section name and grade from 1 to 10.');
  db()->beginTransaction();try{
   q('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);if($sid){$old=owned_section($sid,(int)$u['id']);if($grade!=(int)$old['grade_level']&&val('SELECT COUNT(*) FROM pupil_sections WHERE section_id=?',[$sid]))fail('Move pupils to another section before changing this section’s grade.');}
   if(val('SELECT id FROM sections WHERE teacher_id=? AND grade_level=? AND LOWER(name)=LOWER(?) AND id<>?'.(sections_archive_supported()?' AND archived_at IS NULL':''),[$u['id'],$grade,$name,$sid]))fail('You already have Grade '.$grade.' · '.$name.'.');
   if($sid){q('UPDATE sections SET name=?,grade_level=? WHERE id=?',[$name,$grade,$sid]);q('UPDATE pupils p JOIN pupil_sections ps ON ps.pupil_id=p.user_id SET p.section=?,p.grade_level=? WHERE ps.section_id=?',[$name,$grade,$sid]);}
   else{q('INSERT INTO sections(teacher_id,grade_level,name) VALUES(?,?,?)',[$u['id'],$grade,$name]);$sid=(int)db()->lastInsertId();}audit('save_section',(string)$sid);db()->commit();flash('Grade '.$grade.' · '.$name.' is saved. Next: add its pupils.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=sections#section-'.$sid);
 }
 if(str_starts_with($action,'import_')){import_actions($action);return;}
 if(in_array($action,CHECKUP_ACTIONS,true))checkup_action($action);
 if(in_array($action,ACCOUNT_ID_ACTIONS,true))account_ids_action($action);
 if(in_array($action,SCHOOL_ACTIONS,true))school_actions($action);
 if(in_array($action,TEACHER_ACCOUNT_ACTIONS,true))teacher_account_action($action);
 if(in_array($action,ADMIN_ACTIONS,true)){admin_actions($action);return;}
 if(in_array($action,['admin_pin','admin_pin_create','admin_pin_cancel','change_admin_pin'],true)){admin_pin_actions($action);return;}
 if($action==='create_account'){
  $u=require_role('admin','teacher');$role=$u['role']==='admin'?'teacher':'pupil';$nm=name_input();
  if($role==='teacher'&&isset($_POST['school_id']))school_use((int)$_POST['school_id']);/* v174: the new teacher's school */$name=$nm['name'];if(function_exists('dupe_guard'))dupe_guard($name,$role,$role==='teacher'?(int)(school_ctx_id()?:0):(int)($u['school_id']??0),$role==='teacher'?'?page=add_teachers':'?page=accounts');   /* v211 same name already in BULIG? */$password=$role==='pupil'?'12345678':(string)($_POST['password']??'');
  if($role==='teacher'&&cards_ready()){
   $sx=(string)($_POST['teacher_sex']??'');if(mb_strlen($name)<2||mb_strlen($name)>150)fail('Use a name of 2–150 characters.');if(!in_array($sx,['female','male'],true))fail('Choose Male or Female for the teacher.');
   db()->beginTransaction();try{$c=teacher_create($name,$sx);name_parts_save($c['id'],$nm['parts']);audit('create_teacher',$c['public']);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
   $_SESSION['new_teacher']=$c['id'];go('?page=add_teachers');
  }
  if(strlen($name)<2||strlen($name)>150||strlen($password)<8||strlen($password)>72)fail('Use a name of 2–150 characters and a password of 8–72 characters.');
  db()->beginTransaction();try{
   $public=new_account_id($role);
   q('INSERT INTO users(public_id,role,name,password_hash) VALUES(?,?,?,?)',[$public,$role,$name,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();name_parts_save($id,$nm['parts']);school_mark_user($id);
   if($role==='teacher'){$sx=(string)($_POST['teacher_sex']??'');if(!in_array($sx,['female','male'],true))fail('Choose Male or Female for the teacher.');q('INSERT INTO teachers VALUES(?)',[$id]);tf_set_sex($id,$sx);notify($id,'account','Welcome to BULIG Quest, '.explode(' ',trim($name))[0].'!','Start by adding a section, then your pupils.','?page=sections');}else{
    $section=section_from_post((int)$u['id']);$grade=(int)$section['grade_level'];$level=selected_start_level(1,true);$details=pupil_details_input();
    q('INSERT INTO pupils(user_id,grade_level,section) VALUES(?,?,?)',[$id,$grade,$section['name']]);save_pupil_details($id,$details);q('INSERT INTO pupil_sections VALUES(?,?)',[$id,$section['id']]);q('INSERT INTO teacher_pupils VALUES(?,?)',[$u['id'],$id]);if(function_exists('speech_set'))speech_set($id,(string)($_POST['speech_mode']??'standard'));if($level)q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?)',[$id,$level,$u['id']]);$password=new_pupil_password($id);
   }audit('create_'.$role,$public);db()->commit();if($role==='pupil')$_SESSION['new_pupil']=$id;else flash('Account created. '.$role.' ID: '.$public.'. Share the password you set privately.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=accounts');
 }
 if($action==='update_account'){
  $u=require_role('admin','teacher');$id=(int)($_POST['id']??0);$target=one('SELECT * FROM users WHERE id=?',[$id]);if(!$target)fail('Account not found.',404);
  if($u['role']==='teacher'){own_pupil($id);}elseif($target['role']!=='teacher')fail('Administrators manage teacher accounts here.',403);
  $name=trim((string)($_POST['name']??''));if(!$name||strlen($name)>150)fail('Enter a valid name.');$password=(string)($_POST['password']??'');
  if($password!==''&&(strlen($password)<8||strlen($password)>72))fail('Use a password of 8–72 characters.');
  db()->beginTransaction();try{
   if($name!==$target['name'])name_parts_forget($id);q('UPDATE users SET name=?,active=? WHERE id=?',[$name,isset($_POST['active'])?1:0,$id]);if($password!==''||!isset($_POST['active']))saved_login_clear($id);if($password!==''){q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);card_forget_starter($id);}
   if($u['role']==='teacher'){
    $section=section_from_post((int)$u['id']);
    save_pupil_details($id,pupil_details_input($id));
    q('UPDATE pupils SET grade_level=?,section=? WHERE user_id=?',[$section['grade_level'],$section['name'],$id]);q('INSERT INTO pupil_sections VALUES(?,?) ON DUPLICATE KEY UPDATE section_id=VALUES(section_id)',[$id,$section['id']]);$level=selected_start_level((int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$id])?:1,true);if($level)q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE level_id=VALUES(level_id),assigned_by=VALUES(assigned_by),assigned_at=NOW()',[$id,$level,$u['id']]);
   }audit('update_account',(string)$id);db()->commit();flash('Account updated.');
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go($u['role']==='teacher'?'?page=pupil&id='.$id.'&tab=account':'?page=accounts');
 }
 if($action==='change_password'){
  $u=require_role('pupil','teacher','admin');$old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');
  if(!password_verify($old,$u['password_hash']))fail('Your current password is incorrect.');
  if(strlen($new)<8||strlen($new)>72)fail('Use a new password of 8–72 characters.');
  if($new!==(string)($_POST['confirm_password']??''))fail('The new passwords do not match.');
  q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);saved_login_clear((int)$u['id']);card_forget_starter((int)$u['id']);session_regenerate_id(true);audit('change_password','');flash('Password changed. Use your new password next time.');go($u['role']==='pupil'?'?page=settings':'?page=profile');
 }
 if($action==='undo_last'){undo_run(require_role('teacher'));}   // v208 Undo bar
 if($action==='speech_mode'){speech_save(require_role('teacher'));}   // v208 how BULIG listens to a pupil
 if($action==='set_start_level'){
  $u=require_role('teacher');$tid=(int)$u['id'];$back=(string)($_POST['back']??'');if(!preg_match('~^\?page=(manage(&id=[0-9]+)?|pupil&id=[0-9]+)$~',$back))$back='?page=manage';
  $pupils=isset($_POST['pupil'])?[(int)$_POST['pupil']]:array_map('intval',(array)($_POST['pupils']??[]));$pupils=array_values(array_unique(array_filter($pupils)));
  if(!$pupils){flash('Tick at least one pupil first.');go($back);}
  $level=selected_start_level();if(!(int)val('SELECT published FROM bulig_levels WHERE id=?',[$level]))fail('Choose a level that is available.');
  foreach($pupils as $pid)if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]))fail('This pupil is not assigned to you.',403);
  $prev=[];foreach($pupils as $pid)$prev[$pid]=val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);   // v208 kept for Undo
  foreach($pupils as $pid)q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE level_id=VALUES(level_id),assigned_by=VALUES(assigned_by),assigned_at=NOW()',[$pid,$level,$tid]);
  foreach($pupils as $pid)push_queue($pid,'lessons',level_label($level).' is open!','Your teacher chose '.level_label($level).' for you. Your next adventure is waiting.','?page=lessons&level='.$level);
  audit('start_level',level_label($level).' for '.count($pupils).' pupils');$msg=count($pupils)===1?(string)val('SELECT name FROM users WHERE id=?',[$pupils[0]]).' now starts at '.level_label($level).'.':'Starting level set to '.level_label($level).' for '.count($pupils).' pupils.';flash($msg);undo_set('level',['prev'=>$prev],$msg,$back);go($back);
 }
 if($action==='class_done'){
  $u=require_role('teacher');$tid=(int)$u['id'];$back=(string)($_POST['back']??'');if(!preg_match('~^\?page=(class_demo|pupil|manage)(&[a-zA-Z0-9_=]+)*(#learning-path)?$~',$back))$back='?page=dashboard';
  $level=(int)($_POST['level']??0);$scope=(string)($_POST['scope']??'');$undo=(int)($_POST['undo']??0);
  $pupils=isset($_POST['pupil'])?[(int)$_POST['pupil']]:array_map('intval',(array)($_POST['pupils']??[]));$pupils=array_values(array_unique(array_filter($pupils)));
  if(!$pupils){flash('Choose at least one pupil who was in class.');go($back);}
  foreach($pupils as $pid)if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]))fail('This pupil is not assigned to you.',403);
  db()->beginTransaction();try{
   if($undo){$n=class_done_undo($pupils[0],$undo);audit('class_done_undo','Pupil '.$pupils[0].', lesson '.$undo.', '.$n.' activities');db()->commit();flash($n?'The "Done in class" mark was removed from that lesson.':'Nothing to undo in that lesson.');go($back);}
   if(!in_array($scope,['activity','lesson','level','lessons'],true))fail('Choose what you finished.');$t0=date('Y-m-d H:i:s');$madeAids=[];
   $marked=0;$what=$scope==='level'?level_label($level):($scope==='activity'?'this activity':($scope==='lesson'?'this lesson':'the chosen work'));
   $lids=$scope==='lessons'?array_map('intval',(array)($_POST['lessons']??[])):[];$acts=$scope==='lessons'?array_map('intval',(array)($_POST['activities']??[])):[];if($scope==='lessons'&&!$lids&&!$acts){db()->rollBack();flash('Tick at least one lesson or activity first.');go($back);}
   foreach($pupils as $pid){
    $aids=[];if($scope==='lessons'){foreach($lids as $lid)$aids=array_merge($aids,class_done_activities($pid,'lesson',$lid));foreach($acts as $aid)$aids=array_merge($aids,class_done_activities($pid,'activity',$aid));}
    else $aids=class_done_activities($pid,$scope,$scope==='level'?$level:(int)($_POST[$scope==='activity'?'aid':'lid']??0));
    $n=class_done_apply($tid,$pid,$aids);$marked+=$n;if($n)$madeAids[$pid]=array_values(array_unique(array_map('intval',$aids)));
    if($n)push_queue($pid,'lessons','Your next lesson is open!','Your class did it together. Your next step in BULIG Quest is ready.','?page=dashboard');
    if($n)class_done_note_set($pid,$scope==='level'?'Your teacher marked '.level_label($level).' as done.'.(($nx=level_next($level))&&level_available($pid,$nx)?' '.level_label($nx).' is open now.':''):'Your teacher marked '.($scope==='activity'?'an activity':'your lesson').' as done. Your next step is ready.');
   }
   audit('class_done',$what.' for '.count($pupils).' pupils, '.$marked.' activities');db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  $msg='Marked as done in class: '.$what.' for '.count($pupils).' '.(count($pupils)===1?'pupil':'pupils').'.';flash($msg);if($madeAids)undo_set('done',['aids'=>$madeAids,'t0'=>$t0],$msg,$back);go($back);
 }
 /* v194: teachers and administrators change their own name (My profile › My name) */
 if($action==='my_name'){
  $u=require_role('teacher','admin');$n=name_input();if(mb_strlen($n['name'])>150)fail('The name is too long.');
  if($n['name']!==(string)$u['name']){q('UPDATE users SET name=? WHERE id=?',[$n['name'],$u['id']]);audit('my_name',$u['public_id'].': '.$u['name'].' → '.$n['name']);}
  name_parts_save((int)$u['id'],$n['parts']);flash('Your name is saved: '.$n['name'].'.');go('?page=profile#my-name');
 }
 if($action==='teacher_character'){
  $u=require_role('admin');$c=(string)($_POST['character']??'');if(!in_array($c,['female','male'],true))fail('Choose a character.');
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
  $u=require_role('pupil');$lid=(int)($_POST['lesson_id']??0);db()->beginTransaction();try{q('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);finish_lesson((int)$u['id'],$lid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['saved'=>true]);exit;}go('?page=lesson&id='.$lid.'&finished=1');
 }
 /* Story time: a pupil looked at a page of the comic (XP the first time, app/story.php) */
 if($action==='story_seen'){$u=require_role('pupil');header('Content-Type: application/json');echo json_encode(story_mark((int)$u['id'],(int)($_POST['p']??-1)));exit;}
 /* BULIG checks one card right away (v172): right, or one more wrong try (a third of the card's points each, no limit) */
 if($action==='card_check'){
  $u=require_role('pupil');$aid=(int)($_POST['activity_id']??0);$n=(int)($_POST['card']??0);$g=mb_substr(trim((string)($_POST['answer']??'')),0,2000);
  $a=allowed_activity((int)$u['id'],$aid);header('Content-Type: application/json');
  if(!key_checks_cards($a)||$n<1){echo json_encode(['keyed'=>false,'ok'=>true]);exit;}
  $r=key_check_card($a,$n,$g);$t=(int)($_SESSION['ck'][$aid][$n]??0);
  if($r['keyed']&&!$r['ok']&&$t<999&&($_POST['auto']??'')!=='1')$_SESSION['ck'][$aid][$n]=++$t;   // v216 a quiet auto check never counts as a wrong try
  echo json_encode(['keyed'=>$r['keyed'],'ok'=>$r['ok'],'tries'=>$t,'pts'=>$r['pts']??null,'of'=>$r['of']??null,'max'=>KEY_TRIES],JSON_UNESCAPED_UNICODE);exit;
 }
 if(in_array($action,['draft','submit'],true)){
  $u=require_role('pupil');$pid=(int)$u['id'];$aid=(int)($_POST['activity_id']??0);$answer=trim((string)($_POST['response']??''));$drawing=(string)($_POST['drawing']??'');$transcript=trim((string)($_POST['transcript']??''));
  /* v177: room for long writing (editorials, copyreading); the column holds 65,535 bytes */if(strlen($answer)>60000||strlen($transcript)>12000||strlen($drawing)>1500000)fail('This response is too large.');
  if($drawing!==''&&!preg_match('~^data:image/(png|jpeg);base64,[a-zA-Z0-9+/=]+$~',$drawing))fail('Invalid drawing.');
  /* v216 answering hours: outside them a new answer waits (work made on a phone without internet is always kept) */
  if($action==='submit'&&function_exists('answering_open')&&!offline_answer_time()&&!(function_exists('is_tester')&&is_tester($pid))&&!answering_open()){$nx=answer_hours_next();fail('Tick, tock! Answering opens '.($nx!==''?'at '.$nx:'later').'. You can still look at your lessons now.',403);}
  $audio=$action==='submit'?(string)($_POST['audio']??''):'';if(isset($_POST['audio_card'])&&!is_array($_POST['audio_card']))fail('Invalid recording.');$heard=($_POST['heard']??'')==='1';if(strlen($audio)>12500000)fail('This recording is too long. Please record again.');
  db()->beginTransaction();try{
   q('SELECT id FROM users WHERE id=? FOR UPDATE',[$pid]);$a=allowed_activity($pid,$aid);if(!draw_allowed($a)&&!l2a_draw_ok($a))$drawing='';if($action==='submit'&&function_exists('key_merge_tries'))$answer=key_merge_tries($answer,$aid);if($action==='submit'&&function_exists('wc_merge'))$answer=wc_merge($answer);$old=one('SELECT * FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);
   $replay=($action==='submit'&&!empty($_POST['replay'])&&completion_ok($old)&&($a['phase']??'')==='learn')?$old:null;   // v216 Play again: scored like a first answer, the best is kept
   if(completion_ok($old)&&!$replay){
    /* "Try it again" (v151): practice on a finished card activity; BULIG keeps the best score, no extra XP */
    if($action==='submit'&&!empty($_POST['again'])&&function_exists('ac_keyed')&&ac_keyed($a)&&$answer!==''){$pr=ac_practice($pid,$a,$old,$answer);db()->commit();submission_reply($a,true,$pr['message'],0,$pr['card']);}
    db()->commit();submission_reply($a,true,'Already saved. You can continue.',0);}
   if($action==='draft'){
    q('INSERT INTO activity_drafts(pupil_id,activity_id,response,drawing,transcript) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response),drawing=VALUES(drawing),transcript=VALUES(transcript)',[$pid,$aid,$answer,$drawing?:null,$transcript?:null]);db()->commit();header('Content-Type: application/json');echo json_encode(['saved'=>true]);exit;
   }
   $question=one('SELECT * FROM questions WHERE activity_id=?',[$aid]);$exact=$question&&$question['grading']==='exact'&&lesson_level((int)$a['lesson_id'])!==1;/* Level 1 answers are spoken: the teacher checks them */$mode=$exact||($a['response_mode']==='drawing'&&!draw_allowed($a))?'answer':$a['response_mode'];
   /* Level 1 is oral: the answer is a voice recording, or the teacher heard it in class. */
   /* Level 4 is read aloud the same way: the reading is recorded and its time is kept. */
   $l4read=$mode==='answer'&&lesson_level((int)$a['lesson_id'])===5&&level4_passage_parts($a)!==null;
   $oral=$mode==='answer'&&!$exact&&(l1_screens((int)$a['lesson_id'])||$l4read);$apath=null;
   if($oral){if($audio===''&&!$heard)fail('Record your answer first.');$secs=max(0,min(3600,(int)($_POST['secs']??0)));
    /* v173: on iPhones BULIG only listens (no recording), so the words it heard are the answer */
    $heardOnly=$audio===''&&$transcript!=='';
    if($l4read)$answer=($audio!==''?'Read aloud (voice recording)':($heardOnly?'Read aloud (BULIG listened)':'Answered out loud. Done.')).(($audio!==''||$heardOnly)&&$secs?' · time '.intdiv($secs,60).':'.sprintf('%02d',$secs%60):'');
    elseif($answer==='')$answer=$audio!==''?'Spoken answer (voice recording)':($heardOnly?'Said: '.mb_substr($transcript,0,2000):'Answered out loud. Done.');if($audio!==''&&l1_audio_supported())$apath=l1_save_audio($audio,$pid,$aid);}
   if(in_array($mode,['answer','drawing'],true)&&$answer===''&&$drawing==='')fail('Draw it, upload a photo, or tell about your drawing before continuing.');
   if($mode==='answer'&&$answer==='')fail('Speak or type an answer first.');
   $ok=true;$score=null;$max=null;$outcome=['none'=>'viewed','perform'=>'performed','drawing'=>'recorded','answer'=>'recorded'][$mode];
   if($exact){
    $correct=rows('SELECT content FROM answers WHERE question_id=? AND correct=1',[$question['id']]);if(!$correct)fail('This question needs an answer key. Please let your teacher know.');
    $ok=false;foreach($correct as $c)if(trim(normalize_answer($answer))===trim(normalize_answer($c['content'])))$ok=true;
    $score=$ok?1:0;$max=1;$outcome=$ok?'correct':'recorded';
   }
   $test=$exact&&in_array($a['phase']??'learn',['pre','post'],true);   // v216 a test question: a wrong answer is kept (the score shows it); the post-test has its own Try again
   $status=$ok||$test?'completed':'retry';$when=offline_answer_time()??date('Y-m-d H:i:s');
   q('INSERT INTO activity_completion(pupil_id,activity_id,response,drawing,transcript,prompt_snapshot,activity_revision,status,submitted_at,outcome,score,max_score) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response),drawing=VALUES(drawing),transcript=VALUES(transcript),prompt_snapshot=VALUES(prompt_snapshot),activity_revision=VALUES(activity_revision),status=VALUES(status),submitted_at=VALUES(submitted_at),outcome=VALUES(outcome),score=VALUES(score),max_score=VALUES(max_score),audio_path=NULL,audio_paths=NULL,rubric_scores=NULL,reviewed_by=NULL,reviewed_at=NULL',[$pid,$aid,$answer,$drawing?:null,$transcript?:null,level2_prompt_snapshot($a),$a['revision'],$status,$when,$outcome,$score,$max]);
   if($test)$ok=true;
   if(!empty($_POST['audio_card'])&&is_array($_POST['audio_card'])&&in_array(lesson_level((int)$a['lesson_id']),[2,3,4,9,7],true)&&l2a_audio_supported()&&($ap=l2a_save_audios($_POST['audio_card'],$pid,$aid)))q('UPDATE activity_completion SET audio_paths=? WHERE pupil_id=? AND activity_id=?',[json_encode($ap),$pid,$aid]);
   if($apath)q('UPDATE activity_completion SET audio_path=? WHERE pupil_id=? AND activity_id=?',[$apath,$pid,$aid]);
   if(offline_answer_time())q('INSERT INTO audit_log(actor_id,action,details) VALUES(?,?,?)',[$pid,'offline_answer','activity '.$aid.' at '.$when]);
   $cid=(int)val('SELECT id FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);$GLOBALS['bulig_vowel_lesson']=function_exists('vowel_lesson')&&vowel_lesson((int)$a['lesson_id']);if(!$exact)key_save_score($cid);/* v216 an answer marked with its key keeps that score */
   if($oral&&!$l4read&&function_exists('l1_auto_score'))q('UPDATE activity_completion SET score=?,max_score=1 WHERE id=?',[l1_auto_score($a,$transcript,$apath!==null||$audio!==''),$cid]);/* Level 1: BULIG checks the talk and pronunciation (v172) */
   /* Level 4: BULIG scores the reading from what the speech recognizer heard (the module's miscue table). */
   $l4s=$l4read&&$transcript!==''?l4_auto($a,$transcript,(int)($_POST['secs']??0)):null;if($l4s){q('UPDATE activity_completion SET rubric_scores=?,score=?,max_score=100 WHERE id=?',[json_encode(['l4'=>$l4s],JSON_UNESCAPED_UNICODE),$l4s['score'],$cid]);if(function_exists('rg_log_reading')&&lesson_level((int)$a['lesson_id'])===5)rg_log_reading($pid,$aid,$l4s);}if($oral&&function_exists('wc_apply_single'))wc_apply_single($cid);/* v184: Word Coach */q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[$cid,$answer,$status,$pid]);
   q('INSERT INTO pupil_progress(pupil_id,lesson_id,last_activity_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE last_activity_id=VALUES(last_activity_id)',[$pid,$a['lesson_id'],$aid]);
   if($a['expected_text']&&$transcript!==''){$match=word_match($a['expected_text'],$transcript);q('INSERT INTO reading_assessments(pupil_id,activity_id,transcript,expected_text,word_match_percent,detail) VALUES(?,?,?,?,?,?)',[$pid,$aid,$transcript,$a['expected_text'],$match['percent'],json_encode($match['words'])]);}
   /* Stars (v181): a lesson activity below 2 stars (80%) is not finished. It opens again for a fresh try (wrong tries reset). */
   $gate=null;if($ok&&($a['phase']??'')==='learn'&&function_exists('completion_frac')&&($fr=completion_frac(one('SELECT score,max_score FROM activity_completion WHERE id=?',[$cid])))!==null&&round($fr*100,6)<STAR_PASS){
    $gate=$fr;$ok=false;q("UPDATE activity_completion SET status='retry' WHERE id=?",[$cid]);unset($_SESSION['ck'][$aid]);}
   if(!$replay)rv_capture($cid,$a);
   if($replay){$rp=replay_settle($pid,$a,$replay,$cid);$ok=true;$gate=null;if($rp['kept_new'])rv_capture($cid,$a);}
   if($ok&&!$replay){q('DELETE FROM activity_drafts WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);update_learning($pid,$aid);$awardedXp=(int)val('SELECT amount FROM xp_transactions WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);}
   db()->commit();$message=$ok?($test?'Your answer is saved.':($exact?'Correct! Great work.':($mode==='none'?'You’re ready for the next step.':($mode==='perform'?'Activity completed. Well done!':($oral?'Your answer is saved. Great speaking!':'Your response is saved. Thank you for sharing!'))))):'Not quite yet. Listen again and give it another try.';
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  if(function_exists('rx_save'))rx_save($pid,$aid,(int)($_POST['time_secs']??0));   // v216 Roxanne: how long this activity took
  if($replay){$GLOBALS['bulig_replay']=$rp;submission_reply($a,true,$rp['message'],$rp['xp'],'');}   // v216 Play again: its own popup (new best, practice or keep trying)
  if($ok)$a['xp_reward']=$awardedXp??0;
  if($ok&&!empty($test)&&($a['phase']??'')==='pre'){$want=array_column(rows('SELECT content FROM answers WHERE question_id=? AND correct=1',[(int)($question['id']??0)]),'content');
   $check='<div class="ac-panel ac-pre" role="status"><div class="ac-top"><span class="ac-badge" aria-hidden="true">'.l1_icon('ok').'</span><div><b>Thank you! Your answer is saved.</b><small>The pre-test shows what you know now. There is no wrong answer here.</small></div></div>'.($want?'<p class="ac-lab">THE ANSWER</p><ul class="ac-again ac-learn"><li><span><b>'.e(implode(' or ',$want)).'</b></span></li></ul>':'').'</div>';
   submission_reply($a,true,'Thank you! Your answer is saved.',0,$check);}   // v216 pre-test: no "wrong", the answer is shown
  if($ok&&function_exists('pt_after_answer'))pt_after_answer($pid,$a);
  $check=$ok&&function_exists('ac_keyed')&&ac_keyed($a)?ac_card($a,['response'=>$answer],['fresh'=>true,'xp'=>(int)$a['xp_reward']]):'';/* BULIG's result card (v151) */
  if($ok&&!empty($l4s))$check=l4_result_card($a,$l4s);
  /* Stars (v181): every finished lesson activity shows its stars (card activities show them on BULIG's result card). */
  if($ok&&$check===''&&($a['phase']??'')==='learn'&&function_exists('completion_frac')&&($fr=completion_frac(one('SELECT score,max_score FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid])))!==null){$st=star_count($fr,'learn');$check='<div class="ac-panel sg-ok" role="status"><div class="ac-score"><strong>'.($st===3?'Perfect!':'Well done!').' <span>'.(int)floor($fr*100+1e-9).'%</span></strong>'.stars_html($st,3).'</div>'.($a['xp_reward']?'<span class="ac-xp">+'.(int)$a['xp_reward'].' XP</span>':'').'</div>';}
  if($gate!==null){$st=star_count($gate,'learn');$message=($st?'You got '.$st.' star'.($st>1?'s':'').'. ':'').'You need 2 stars ('.STAR_PASS.'%) to go on. Try this activity again!';$check=star_gate_card($a,$gate);}
  submission_reply($a,$ok,$message,$ok?(int)$a['xp_reward']:0,$check);
 }
 if($action==='post_retake'){pt_retake(require_role('pupil'),(int)($_POST['level']??0));}
 if($action==='help_request'){help_request(require_role('pupil'));}
 if($action==='bug_fixed'||$action==='bug_fixed_all')bug_fixed(require_role('admin'),$action==='bug_fixed_all');
 if($action==='help_done'||$action==='help_done_all'){help_done(require_role('teacher'),$action==='help_done_all');}
 if($action==='notif_mark'||$action==='notif_delete'||$action==='notif_delete_read')notif_action($action);
 if($action==='notif_read_all'){$u=current_user();if(!$u)fail('Please sign in.',403);if(notif_ready())q('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL',[(int)$u['id']]);$b=(string)($_POST['back']??'');go(preg_match('/^\?page=[a-z_]+(&[A-Za-z0-9_=%&.-]*)?$/',$b)?$b:'?page=notifications');}
 /* Teacher review: approve every answer the answer key marked fully right (nothing written, drawn or recorded to read). */
 if($action==='review_approve_all'){
  $u=require_role('teacher');$fp=(int)($_POST['pupil']??0);if($fp)own_pupil($fp);$ids=key_perfect_ids((int)$u['id'],$fp);
  foreach($ids as $id)q('UPDATE activity_completion SET reviewed_by=?,reviewed_at=NOW() WHERE id=? AND reviewed_at IS NULL',[$u['id'],$id]);
  audit('approve_all_correct',(string)count($ids));flash(count($ids)===1?'1 fully correct answer approved.':count($ids).' fully correct answers approved.');go(review_back());
 }
 /* Teacher review: let the pupil do an activity again. Its answer stays in the history; the lesson opens again at that activity. */
 if($action==='review_redo'){
  $u=require_role('teacher');$cid=(int)($_POST['completion_id']??0);$c=one('SELECT * FROM activity_completion WHERE id=?',[$cid]);if(!$c)fail('Response not found.',404);own_pupil((int)$c['pupil_id']);
  $note=trim((string)($_POST['feedback']??''));if(strlen($note)>3000)fail('Enter feedback of up to 3,000 characters.');$fa=one('SELECT title,lesson_id FROM activities WHERE id=?',[(int)$c['activity_id']]);
  $j=json_decode((string)($c['rubric_scores']??''),true);if(is_array($j))unset($j['teacher_score']);
  q("UPDATE activity_completion SET status='retry',feedback=?,reviewed_by=?,reviewed_at=NULL,rubric_scores=? WHERE id=?",[$note!==''?$note:'Your teacher asked you to do this activity again.',$u['id'],is_array($j)&&$j?json_encode($j):null,$cid]);
  q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[$cid,$note!==''?$note:'Do it again','redo',$u['id']]);if($fa)notify((int)$c['pupil_id'],'again','Try “'.$fa['title'].'” again',$note!==''?'Your teacher says: “'.$note.'”':'Your teacher asked you to do it one more time.','?page=lesson&id='.(int)$fa['lesson_id'].'&activity='.(int)$c['activity_id']);
  if($fa)q('UPDATE pupil_progress SET completed_at=NULL WHERE pupil_id=? AND lesson_id=?',[(int)$c['pupil_id'],(int)$fa['lesson_id']]);
  if($fa)push_queue((int)$c['pupil_id'],'teacher','Try “'.$fa['title'].'” again','Your teacher asked you to do it one more time.','?page=lesson&id='.(int)$fa['lesson_id'].'&activity='.(int)$c['activity_id']);
  audit('redo_activity',(string)$cid);flash('The pupil can do this activity again. It is back on their lesson path.');go(review_back());
 }
 if($action==='review'){
  $u=require_role('teacher');$cid=(int)($_POST['completion_id']??0);$c=one('SELECT * FROM activity_completion WHERE id=?',[$cid]);if(!$c)fail('Response not found.',404);own_pupil((int)$c['pupil_id']);
  $feedback=trim((string)($_POST['feedback']??''));$fa=one('SELECT title,lesson_id FROM activities WHERE id=?',[(int)$c['activity_id']]);
  /* Level 1: the module rubric (or the lesson-plan checklist) for this lesson. */
  $rv=null;$rub=$fa&&isset($_POST['rubric'])&&l1_audio_supported()?l1_rubric(l1_position((int)$fa['lesson_id'])):null;
  if($rub){$rv=l1_rubric_values($rub,(array)$_POST['rubric']);if($rv===null)fail($rub['kind']==='rubric'?'Choose a score for each part of the rubric.':'Choose one rating first.');}
  /* Level 4: the module's scoring table (miscues, Oral Reading Score, Reading Level, Reading Speed). */
  $l4v=null;if($fa&&isset($_POST['miscue'])&&lesson_level((int)$fa['lesson_id'])===5){$act=one('SELECT * FROM activities WHERE id=?',[(int)$c['activity_id']]);$l4v=$act?l4_review_values($act,$_POST,l4_saved_score($c)):null;if($l4v){$rv=$l4v;}}
  /* Levels 5-7: the auto score can be changed by the teacher (for example after reading a written answer). */
  $ks=null;if(isset($_POST['key_score'])&&$c['max_score']!==null&&activity_key_id(['id'=>(int)$c['activity_id']])){$ks=max(0,min((float)$c['max_score'],round((float)$_POST['key_score'],2)));}
  if(strlen($feedback)>3000||($feedback===''&&$rv===null&&$ks===null))fail('Enter feedback of up to 3,000 characters.');
  if($ks!==null&&abs($ks-(float)$c['score'])>0.001){$j=json_decode((string)($c['rubric_scores']??''),true);$j=is_array($j)?$j:[];$j['teacher_score']=$ks;q('UPDATE activity_completion SET score=?,rubric_scores=? WHERE id=?',[$ks,json_encode($j),$cid]);}
  if($rv!==null)q('UPDATE activity_completion SET rubric_scores=? WHERE id=?',[json_encode($l4v!==null?['l4'=>$l4v]:['v'=>$rv]),$cid]);
  if($l4v!==null)q('UPDATE activity_completion SET score=?,max_score=? WHERE id=?',[$l4v['score'],100,$cid]);
  /* Stars (v181): a lesson activity held back below 2 stars opens when the teacher gives it 80% or more (for example when the microphone misheard). */
  $nc=one('SELECT a.phase,c.status,c.score,c.max_score FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.id=?',[$cid]);
  if($nc&&$nc['phase']==='learn'&&$nc['status']==='retry'&&($fr=completion_frac($nc))!==null&&round($fr*100,6)>=STAR_PASS){q("UPDATE activity_completion SET status='completed' WHERE id=?",[$cid]);update_learning((int)$c['pupil_id'],(int)$c['activity_id']);}
  q('UPDATE activity_completion SET feedback=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$feedback!==''?$feedback:$c['feedback'],$u['id'],$cid]);if($feedback!=='')q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[$cid,$feedback,'feedback',$u['id']]);audit($rv!==null?'score_response':'feedback_response',(string)$cid);if($fa&&$feedback!=='')push_queue((int)$c['pupil_id'],'teacher','Teacher '.explode(' ',trim((string)$u['name']))[0].' left you a note','Open “'.$fa['title'].'” to read it.','?page=lesson&id='.(int)$fa['lesson_id'].'&activity='.(int)$c['activity_id']);if($fa&&$feedback!=='')notify((int)$c['pupil_id'],'note','Teacher '.explode(' ',trim((string)$u['name']))[0].' left you a note','“'.mb_strimwidth($feedback,0,160,'…').'” · '.$fa['title'],'?page=lesson&id='.(int)$fa['lesson_id'].'&activity='.(int)$c['activity_id']);$_SESSION['tf_feedback']=explode(' ',trim((string)val('SELECT name FROM users WHERE id=?',[$c['pupil_id']])))[0];if($fa&&$feedback!=='')notify((int)$c['pupil_id'],'note','Teacher '.explode(' ',trim((string)$u['name']))[0].' left you a note','“'.mb_strimwidth($feedback,0,120,'…').'” · '.$fa['title'],'?page=lesson&id='.(int)$fa['lesson_id'].'&activity='.(int)$c['activity_id']);flash($rv!==null?($feedback!==''?'Score and feedback saved.':'Score saved.'):'Feedback saved. Pupil progression is unchanged.');go(review_back());
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
 if($action==='save_reward'){
  require_role('admin');$id=(int)($_POST['id']??0);$title=trim((string)$_POST['title']);$xp=(int)$_POST['required_xp'];if(!$title||strlen($title)>100||$xp<0)fail('Check reward details.');q('UPDATE rewards SET title=?,required_xp=?,active=? WHERE id=?',[$title,$xp,isset($_POST['active'])?1:0,$id]);flash('Reward updated.');go('?page=settings&tab=badges');
 }
 if($action==='save_badge'){
  require_role('admin');$id=(int)$_POST['id'];$threshold=(int)$_POST['threshold_value'];$title=trim((string)$_POST['title']);if(!$title||strlen($title)>100||$threshold<1)fail('Check badge details.');q('UPDATE badges SET title=?,description=?,threshold_value=?,active=? WHERE id=?',[$title,substr((string)$_POST['description'],0,255),$threshold,isset($_POST['active'])?1:0,$id]);flash('Badge updated.');go('?page=settings&tab=badges');
 }
 fail('Unknown action.',400);
}

function submission_reply(array $a,bool $ok,string $message,int $xp,string $check=''):never{
 $url='?page=lesson&id='.$a['lesson_id'].'&activity='.$a['id'].'&feedback=1';if(!empty($GLOBALS['bulig_rx'])&&function_exists('rx_card'))$check.=rx_card($GLOBALS['bulig_rx']);
 if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['saved'=>$ok,'message'=>$message,'xp'=>$xp,'url'=>$url]+($check!==''?['check'=>$check]:[])+(($v=vx_result($a,$xp))?['vx'=>$v]:[]));exit;}
 flash($message);go($url);
}
/** v213 Victory or Try again (the popup after a lesson activity): its stars (2 or 3 = victory, 80%), score, XP and first name. */
function vx_result(array $a,int $xp):?array{
 if(!function_exists('completion_frac'))return null;$u=current_user();if(!$u||($u['role']??'')!=='pupil')return null;$pid=(int)$u['id'];
 $name=explode(' ',trim((string)$u['name']))[0];$lid=(int)$a['lesson_id'];$lv=lesson_level($lid);
 $jar=function_exists('star_jar')?star_jar($pid,$lv):null;$fr=function_exists('vx_friend')?vx_friend($a):'mia';$again='?page=lesson&id='.$lid.'&activity='.(int)$a['id'].'&again=1';
 /* v216 Play again: a new best, practice, or the best stays */
 if(!empty($GLOBALS['bulig_replay'])){$r=$GLOBALS['bulig_replay'];
  return ['stars'=>$r['kind']==='keep'?$r['to']:$r['stars'],'best'=>$r['stars'],'pct'=>$r['kind']==='keep'?$r['now']:$r['pct'],'win'=>$r['kind']!=='keep','xp'=>$xp,'name'=>$name,'pass'=>STAR_PASS,'kind'=>$r['kind'],'from'=>$r['from'],'left'=>$r['left'],'jar'=>$jar,'friend'=>$fr,'again'=>$again,'retry'=>$again];}
 /* v216 The Victory popup comes once, when the whole lesson (the stop on the road map) is finished. Its stars are the
    lesson's stars on the road map. While the lesson is going on, each activity shows only its small result card. */
 foreach(activities($lid,$pid) as $x)if(!completion_ok($x))return null;
 $ms=function_exists('map_stars')?(map_stars($pid,[$lid])[$lid]??null):null;if(!$ms||empty($ms['of']))return null;
 $of=(int)$ms['of'];$st=(int)$ms['n'];$win=$of===5?$st>=4:$st>=2;$jar=function_exists('star_jar')?star_jar($pid,$lv):$jar;
 $out=['stars'=>min(3,$of===5?(int)round($st*3/5):$st),'of'=>$of,'lesson_stars'=>$st,'pct'=>$of?(int)round($st/$of*100):0,'win'=>$win,'xp'=>$xp,'name'=>$name,'pass'=>STAR_PASS,'lesson'=>1,'retry'=>'?page=lesson&id='.$lid,'jar'=>$jar,'friend'=>$fr];
 if(!$win)$out['retry']='?page=lesson&id='.$lid;
 /* v216 Jeepy drives to the next lesson of this level (the "Lesson done" road) */
 if($win&&function_exists('level_lessons')){$L=level_lessons($lv,function_exists('pupil_grade')?pupil_grade($pid):0);foreach($L as $i=>$l)if((int)$l['id']===$lid){$out['this_n']=$i+1;if(isset($L[$i+1])){$n=$L[$i+1];$out['next_n']=$i+2;$out['next_title']=trim((string)($n['subtitle']??''))!==''?(string)$n['subtitle']:(string)$n['title'];$out['next_url']='?page=lesson&id='.(int)$n['id'];}break;}}
 /* v216 Jeepy: the last lesson of a level is done */
 if($win&&function_exists('level_done_now')&&level_done_now($pid,$lv)){$out['level']=level_label($lv);$nx=null;$seq=function_exists('level_sequence')?level_sequence():[];$i=array_search($lv,$seq,true);if($i!==false&&isset($seq[$i+1]))$nx=$seq[$i+1];if($nx){$out['next_level']=level_label($nx);$out['next_url']='?page=lessons&level='.$nx;}}
 return $out;
}
/** True when every lesson of the level is finished, the last one just now (Jeepy's level-done ride). */
function level_done_now(int $pid,int $lv):bool{
 $g=function_exists('pupil_grade')?pupil_grade($pid):null;
 $r=one('SELECT COUNT(*) n,COUNT(p.completed_at) d,MAX(p.completed_at) last FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$pid,$lv,$g]);
 return $r&&(int)$r['n']>0&&(int)$r['n']===(int)$r['d']&&$r['last']&&strtotime((string)$r['last'])>=time()-120;
}




