<?php
/** Delete an account for good: a pupil (by their teacher or the administrator) or a teacher (by the administrator).
   A pupil's work, recordings, badges, XP and sign-in tickets go with them. A teacher who still has pupils or sections
   is deleted from their own row: the administrator chooses who gets them, and they move first, or (v187) chooses to
   delete those pupils and sections too. The ticked-teachers button skips such teachers unless "also delete their pupils"
   is ticked. A whole school can be deleted too (the Schools page): its teachers, pupils and sections go with it.
   The pupils they checked keep their scores.
   The person deleting types DELETE to confirm. Turning an account off (it can be turned on again) stays the gentle choice. */

const DELETE_WORD='DELETE';

function del_has_table(string $t):bool{static $c=[];if(!isset($c[$t]))$c[$t]=(bool)val('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);return $c[$t];}
function del_q(string $table,string $sql,array $args):void{if(del_has_table($table))q($sql,$args);}

/** Settings that belong to one person (seen pop-ups, tours, ratings, teacher picture …). */
function del_settings(int $id):void{
 q("DELETE FROM settings WHERE setting_key REGEXP ?",['^(cardpw|pupil_seen|teacher_char|tour_seen|george_met|jarvis_met|jeepy_met|friends_met|rate_later|class_done_note|seen_[a-z0-9_]+)_'.$id.'$']);
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
/** v187: a teacher's pupils and sections go with them. A pupil who also has another teacher stays (only this teacher is
    removed from them). Returns the deleted pupils (id, avatar_path) so their files can be removed after the save. */
function delete_teacher_pupils(int $tid):array{
 $gone=[];
 foreach(rows('SELECT u.id,u.avatar_path FROM teacher_pupils tp JOIN users u ON u.id=tp.pupil_id WHERE tp.teacher_id=?',[$tid]) as $p){
  if((int)val('SELECT COUNT(*) FROM teacher_pupils WHERE pupil_id=? AND teacher_id<>?',[$p['id'],$tid])){q('DELETE FROM teacher_pupils WHERE pupil_id=? AND teacher_id=?',[$p['id'],$tid]);continue;}
  delete_pupil((int)$p['id']);$gone[]=$p;}
 q('DELETE FROM pupil_sections WHERE section_id IN (SELECT id FROM sections WHERE teacher_id=?)',[$tid]);
 q('DELETE FROM sections WHERE teacher_id=?',[$tid]);
 return $gone;
}
/** v187: a whole school: every teacher (with their pupils and sections), then any pupil left in the school, then the school. */
function delete_school(int $sid,int $adminId):array{
 $out=['teachers'=>[],'pupils'=>[]];
 foreach(rows("SELECT id,avatar_path FROM users WHERE role='teacher' AND school_id=?",[$sid]) as $t){
  $out['pupils']=array_merge($out['pupils'],delete_teacher_pupils((int)$t['id']));delete_teacher((int)$t['id'],$adminId);$out['teachers'][]=$t;}
 foreach(rows("SELECT id,avatar_path FROM users WHERE role='pupil' AND school_id=?",[$sid]) as $p){delete_pupil((int)$p['id']);$out['pupils'][]=$p;}
 q('DELETE FROM schools WHERE id=?',[$sid]);
 return $out;
}
function del_user_rows(int $id):void{
 foreach(['notifications','saved_logins','push_subscriptions','push_queue','pupil_cards','account_id_history','app_ratings','user_names'] as $t)del_q($t,'DELETE FROM '.$t.' WHERE user_id=?',[$id]);
}

/** The red "Delete" box: type DELETE, then press the button. */
/** $extra: more fields before the DELETE box (a teacher with pupils: who gets them). */
function delete_box(string $action,int $id,string $name,string $what,bool $small=false,string $extra=''):string{
 return '<details class="del-box'.($small?' small':'').'"><summary>'.icon('close').'Delete'.($small?'':' this '.$what).'</summary><form method="post" class="del-form">'.csrf_field()
  .'<input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'">'
  .'<p><b>Delete '.e($name).' for good?</b> '.($what==='pupil'?'Their answers, recordings, badges, XP and sign-in ticket are removed too.':'Their account and Teacher Pass are removed. The pupils they checked keep their scores.').' This cannot be undone.</p>'.$extra
  .'<label>Type '.DELETE_WORD.' to confirm<input name="confirm" required autocomplete="off" autocapitalize="characters" pattern="[Dd][Ee][Ll][Ee][Tt][Ee]" placeholder="'.DELETE_WORD.'"></label>'
  .'<button class="btn danger">'.icon('close').'Delete '.e($name).'</button></form></details>';
}

/** Batch delete: the ticked pupils (their teacher, or the administrator) or the ticked teachers (the administrator).
   Teachers who still have pupils or sections are skipped and named, so nothing is lost by mistake. */
function account_delete_many(string $action):never{
 $u=require_role('teacher','admin');$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST[$action==='delete_pupils'?'pupils':'teachers']??[])))));
 $back=$action==='delete_pupils'?($u['role']==='admin'?'?page=checkup#ck-off':'?page=manage'):'?page=accounts';
 if(!$ids){flash('Tick at least one '.($action==='delete_pupils'?'pupil':'teacher').' first.');go($back);}
 if($action==='delete_pupils'){
  if($u['role']==='teacher')foreach($ids as $id)own_pupil($id);
  $list=rows("SELECT id,name,public_id,avatar_path FROM users WHERE role='pupil' AND id IN(".implode(',',$ids).")");
  db()->beginTransaction();try{foreach($list as $p)delete_pupil((int)$p['id']);audit('delete_pupil',count($list).' pupils: '.implode(', ',array_column($list,'public_id')));db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  foreach($list as $p)del_files($p['avatar_path'],(int)$p['id'],true);
  flash(count($list).' '.(count($list)===1?'pupil was':'pupils were').' deleted.');go($back);
 }
 $u=require_role('admin');$list=rows("SELECT u.id,u.name,u.public_id,u.avatar_path,(SELECT COUNT(*) FROM teacher_pupils tp WHERE tp.teacher_id=u.id)+(SELECT COUNT(*) FROM sections s WHERE s.teacher_id=u.id) busy FROM users u WHERE u.role='teacher' AND u.id IN(".implode(',',$ids).")");
 $all=!empty($_POST['with_pupils']);   // v187: "Also delete their pupils and sections"
 $go=array_values(array_filter($list,fn($t)=>$all||!(int)$t['busy']));$kept=array_values(array_filter($list,fn($t)=>!$all&&(int)$t['busy']));$gone=[];
 db()->beginTransaction();try{foreach($go as $t){if($all)$gone=array_merge($gone,delete_teacher_pupils((int)$t['id']));delete_teacher((int)$t['id'],(int)$u['id']);}
  if($go)audit('delete_teacher',count($go).' teachers'.($gone?' and '.count($gone).' pupils':'').': '.implode(', ',array_column($go,'public_id')));db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 foreach($go as $t)del_files($t['avatar_path'],(int)$t['id'],false);foreach($gone as $p)del_files($p['avatar_path'],(int)$p['id'],true);
 flash(count($go).' '.(count($go)===1?'teacher was':'teachers were').' deleted'.($gone?', with '.count($gone).' '.(count($gone)===1?'pupil':'pupils'):'').'.'.($kept?' Not deleted, because they still have pupils or sections (move them first, or tick "Also delete their pupils"): '.implode(', ',array_column($kept,'name')).'.':''));go($back);
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
  $u=require_role('admin');$t=admin_teacher($id);$all=($_POST['pupils']??'')==='delete';$to=$all?0:(int)($_POST['to']??0);$moved=0;$gone=[];
  if(($_POST['pupils']??'')==='move'&&!$to)fail('Choose the teacher who gets the pupils, or choose to delete the pupils too.');
  db()->beginTransaction();try{if($to)$moved=admin_move_all($t,admin_move_target($t,$to));if($all)$gone=delete_teacher_pupils($id);delete_teacher($id,(int)$u['id']);audit('delete_teacher',$t['public_id'].' '.$t['name'].($gone?' with '.count($gone).' pupils':''));db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
  del_files($t['avatar_path'],$id,false);foreach($gone as $p)del_files($p['avatar_path'],(int)$p['id'],true);
  flash($t['name'].' was deleted.'.($moved?' Their '.$moved.' '.($moved===1?'pupil':'pupils').' moved to '.one('SELECT name FROM users WHERE id=?',[$to])['name'].'.':'').($gone?' Their '.count($gone).' '.(count($gone)===1?'pupil was':'pupils were').' deleted too.':''));go('?page=accounts');
 }
}
