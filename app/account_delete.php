<?php
/** Delete an account for good: a pupil (by their teacher or the administrator) or a teacher (by the administrator).
   A pupil's work, recordings, badges, XP and sign-in tickets go with them. A teacher can be deleted only when they have
   no pupils and no sections left (move them first); the pupils they checked keep their scores.
   The person deleting types DELETE to confirm. Turning an account off (it can be turned on again) stays the gentle choice. */

const DELETE_WORD='DELETE';

function del_has_table(string $t):bool{static $c=[];if(!isset($c[$t]))$c[$t]=(bool)val('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);return $c[$t];}
function del_q(string $table,string $sql,array $args):void{if(del_has_table($table))q($sql,$args);}

/** Settings that belong to one person (seen pop-ups, tours, ratings, teacher picture …). */
function del_settings(int $id):void{
 q("DELETE FROM settings WHERE setting_key REGEXP ?",['^(cardpw|pupil_seen|teacher_char|tour_seen|george_met|jarvis_met|friends_met|rate_later|class_done_note|seen_[a-z0-9_]+)_'.$id.'$']);
}
function del_files(?string $avatar,int $id,bool $pupil):void{
 if($avatar&&preg_match('~^assets/uploads/[A-Za-z0-9._-]+$~',$avatar))@unlink(__DIR__.'/../public/'.$avatar);
 if(!$pupil)return;$dir=realpath(__DIR__.'/../storage/recordings/'.$id);
 if($dir&&str_starts_with($dir,(string)realpath(__DIR__.'/../storage/recordings'))){
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $f)$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());@rmdir($dir);}
}
/** Things other people did that point to this account: keep them, without the name. */
function del_unlink_actor(int $id):void{
 q('UPDATE audit_log SET actor_id=NULL WHERE actor_id=?',[$id]);
 q('UPDATE activity_completion SET reviewed_by=NULL WHERE reviewed_by=?',[$id]);
 q('UPDATE assessment_attempts SET reviewed_by=NULL WHERE reviewed_by=?',[$id]);
 del_q('content_issues','UPDATE content_issues SET acknowledged_by=NULL WHERE acknowledged_by=?',[$id]);
 del_q('reading_checks','UPDATE reading_checks SET changed_by=NULL WHERE changed_by=?',[$id]);
}

function delete_pupil(int $id):void{
 $p=one("SELECT id,avatar_path FROM users WHERE id=? AND role='pupil'",[$id]);if(!$p)fail('Pupil not found.',404);
 $done='SELECT id FROM activity_completion WHERE pupil_id=?';
 q('DELETE FROM response_history WHERE completion_id IN('.$done.') OR actor_id=?',[$id,$id]);
 foreach(['activity_completion','activity_drafts','assessment_attempts','reading_assessments','pupil_progress','pupil_badges','pupil_rewards','pupil_xp','xp_transactions','pupil_streaks','learning_days','pupil_level_assignments','pupil_sections','teacher_pupils','pupil_details','help_requests','reading_checks'] as $t)
  del_q($t,'DELETE FROM '.$t.' WHERE pupil_id=?',[$id]);
 del_user_rows($id);q('DELETE FROM pupils WHERE user_id=?',[$id]);
 del_unlink_actor($id);del_settings($id);q('DELETE FROM users WHERE id=?',[$id]);
}
function delete_teacher(int $id,int $adminId):void{
 $t=one("SELECT id,avatar_path FROM users WHERE id=? AND role='teacher'",[$id]);if(!$t)fail('Teacher not found.',404);
 if(val('SELECT COUNT(*) FROM teacher_pupils WHERE teacher_id=?',[$id]))fail('This teacher still has pupils. Move them to another teacher first.');
 if(val('SELECT COUNT(*) FROM sections WHERE teacher_id=?',[$id]))fail('This teacher still has sections. Move their pupils, then remove the empty sections first.');
 // a starting level this teacher chose stays; it is now under the pupil's own teacher
 foreach(rows('SELECT pupil_id FROM pupil_level_assignments WHERE assigned_by=?',[$id]) as $r){
  $now=(int)(val('SELECT teacher_id FROM teacher_pupils WHERE pupil_id=? LIMIT 1',[$r['pupil_id']])?:0);
  if($now)q('UPDATE pupil_level_assignments SET assigned_by=? WHERE pupil_id=? AND assigned_by=?',[$now,$r['pupil_id'],$id]);
  else q('DELETE FROM pupil_level_assignments WHERE pupil_id=? AND assigned_by=?',[$r['pupil_id'],$id]);}
 q('UPDATE response_history SET actor_id=? WHERE actor_id=?',[$adminId,$id]);   // the history of answers stays whole
 del_unlink_actor($id);del_user_rows($id);q('DELETE FROM teachers WHERE user_id=?',[$id]);
 del_settings($id);q('DELETE FROM users WHERE id=?',[$id]);
}
function del_user_rows(int $id):void{
 foreach(['notifications','saved_logins','push_subscriptions','push_queue','pupil_cards','account_id_history','app_ratings','user_names'] as $t)del_q($t,'DELETE FROM '.$t.' WHERE user_id=?',[$id]);
}

/** The red "Delete" box: type DELETE, then press the button. */
function delete_box(string $action,int $id,string $name,string $what,bool $small=false):string{
 return '<details class="del-box'.($small?' small':'').'"><summary>'.icon('close').'Delete'.($small?'':' this '.$what).'</summary><form method="post" class="del-form">'.csrf_field()
  .'<input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'">'
  .'<p><b>Delete '.e($name).' for good?</b> '.($what==='pupil'?'Their answers, recordings, badges, XP and sign-in ticket are removed too.':'Their account and Teacher Pass are removed. The pupils they checked keep their scores.').' This cannot be undone.</p>'
  .'<label>Type '.DELETE_WORD.' to confirm<input name="confirm" required autocomplete="off" autocapitalize="characters" pattern="[Dd][Ee][Ll][Ee][Tt][Ee]" placeholder="'.DELETE_WORD.'"></label>'
  .'<button class="btn danger">'.icon('close').'Delete '.e($name).'</button></form></details>';
}

function account_delete_action(string $action):void{
 $u=require_role('teacher','admin');$id=(int)($_POST['id']??0);
 if(strtoupper(trim((string)($_POST['confirm']??'')))!==DELETE_WORD)fail('Type '.DELETE_WORD.' in the box to confirm.');
 if($action==='delete_pupil'){
  if($u['role']==='teacher')own_pupil($id);
  $p=one("SELECT name,public_id,avatar_path FROM users WHERE id=? AND role='pupil'",[$id]);if(!$p)fail('Pupil not found.',404);
  db()->beginTransaction();try{delete_pupil($id);audit('delete_pupil',$p['public_id'].' '.$p['name']);db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
  del_files($p['avatar_path'],$id,true);flash($p['name'].' was deleted.');
  go($u['role']==='admin'?'?page=checkup#ck-off':'?page=manage');
 }
 if($action==='delete_teacher'){
  $u=require_role('admin');$t=admin_teacher($id);
  db()->beginTransaction();try{delete_teacher($id,(int)$u['id']);audit('delete_teacher',$t['public_id'].' '.$t['name']);db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
  del_files($t['avatar_path'],$id,false);flash($t['name'].' was deleted.');go('?page=accounts');
 }
}
