<?php
/* v215 Content Studio (database/migrations/045_content_studio.sql).
   - The Content Developer (role content_dev, made only by the Division Admin) adds new lessons and activities as DRAFTS.
     Drafts are hidden from every pupil. The Division Admin approves a lesson, and only then pupils see it.
   - New lessons go at the END of a level and use the same Level 1 screens pupils already use, so they look and work the
     same (XP, badges, the road map). Studio lessons never re-lock a level a pupil already finished.
   - "Test as Pupil": each Content Developer has one hidden test pupil (users.role 'tester'). It answers for real, with every
     level and every draft open. It has no school, class or teacher, so it never appears in reports, lists or leaderboards.
   - Every "Send for review" and "Publish" saves a copy of the lesson (studio_revisions); a draft can be restored to a copy.
   - Existing (official) lessons are never changed here: the studio shows them read-only. */

const STUDIO_TEMPLATES=[
 'speak'=>['Speak the answer','mic','open','answer','Share your answer by speaking or typing. Then choose Submit Answer.','The pupil answers by speaking (the microphone) or typing.'],
 'read'=>['Read aloud with Word Coach','sound','reading','answer','Share your answer by speaking or typing. Then choose Submit Answer.','The pupil reads the words aloud. Word Coach turns each word green when it hears it.'],
 'look'=>['Look and listen','eye','reference','none','Read or listen, then choose Next when you are ready.','Max reads it aloud. The pupil looks, listens and chooses Next.'],
 'do'=>['Do it!','hand','physical','perform','Try the activity with your partner or teacher, then choose Done when you have finished.','The pupil does an action, then chooses Done.'],
 'pair'=>['With a partner','people','group','perform','Try the activity with your partner or teacher, then choose Done when you have finished.','Partners or a group do it together, then choose Done.'],
 'draw'=>['Draw','edit','drawing','drawing','Draw your answer, or describe your paper drawing. Then submit your work.','The pupil draws on the screen, or takes a photo of a paper drawing.']];
const STUDIO_ACTIONS=['studio_level_add','studio_level_save','studio_level_delete','studio_level_send','studio_import_page','studio_import_build','studio_lesson_add','studio_lesson_save','studio_lesson_delete','studio_act_add','studio_act_save','studio_act_delete','studio_act_move','studio_send','studio_restore','studio_test_start','studio_test_reset'];
const STUDIO_ADMIN_ACTIONS=['studio_approve','studio_sendback','studio_hide','studio_level_approve','studio_level_sendback','studio_level_hide'];

function studio_ready():bool{static $ok;if($ok===null){try{$ok=(bool)one("SHOW TABLES LIKE 'studio_lessons'");}catch(Throwable $e){$ok=false;}}return $ok;}
/** A Content Developer's hidden test pupil. */
function is_tester(int $pid):bool{static $c=[];if(!$pid)return false;return $c[$pid]??=studio_ready()&&(string)val('SELECT role FROM users WHERE id=?',[$pid])==='tester';}
/** A lesson made in the studio that pupils cannot see yet (draft or waiting for review). */
function studio_draft_lesson(int $lid):bool{static $c=[];if(!studio_ready())return false;return $c[$lid]??=(bool)val("SELECT 1 FROM studio_lessons WHERE lesson_id=? AND status<>'published'",[$lid]);}
function studio_row(int $lid):?array{return studio_ready()?(one('SELECT * FROM studio_lessons WHERE lesson_id=?',[$lid])?:null):null;}
/** The test pupil sees the studio's draft activities in a draft lesson. */
function studio_test_draft(int $pid,int $lid):bool{return $pid>0&&is_tester($pid)&&studio_draft_lesson($lid);}
/** Which lessons the test pupil opens: every published lesson and every studio draft, for its grade's module. */
function studio_tester_lesson_ok(int $pid,int $lid):bool{
 $l=one('SELECT l.published,m.level_id,m.grade_level g FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?',[$lid]);if(!$l)return false;
 if(!(int)$l['published']&&!studio_draft_lesson($lid))return false;
 if($l['g']!==null&&(int)$l['g']!==pupil_grade($pid))return false;
 return level_published((int)$l['level_id'])||studio_draft_level((int)$l['level_id']);
}
/** Studio lessons are extra: they never decide whether a level is finished (so publishing one never re-locks a level). */
function studio_not_extra_sql(string $l='l'):string{if(!studio_ready())return '';return ' AND '.$l.'.id NOT IN (SELECT sx.lesson_id FROM studio_lessons sx JOIN lessons lx ON lx.id=sx.lesson_id JOIN modules mx ON mx.id=lx.module_id'.(studio_levels_ready()?' WHERE mx.level_id NOT IN (SELECT level_id FROM studio_levels)':'').')';}   // a new level's own lessons do count for that level

/* ---------- v216 new levels (database/migrations/047_studio_levels.sql) ---------- */
function studio_levels_ready():bool{static $ok;if($ok===null){try{$ok=(bool)one("SHOW TABLES LIKE 'studio_levels'");}catch(Throwable $e){$ok=false;}}return $ok;}
/** Every new level (any status), in the order they were made: they come after Level 7. */
function studio_levels_all():array{static $c=null;if($c===null){$c=[];if(studio_levels_ready())foreach(rows('SELECT * FROM studio_levels ORDER BY level_id') as $r)$c[(int)$r['level_id']]=$r;}return $c;}
function studio_extra_levels():array{return array_keys(studio_levels_all());}
function studio_level(int $id):?array{return studio_levels_all()[$id]??null;}
function studio_draft_level(int $id):bool{$l=studio_level($id);return $l!==null&&$l['status']!=='published';}
/** "Level 8", "Level 9" … in order after Level 7. */
function studio_level_label(int $id):string{$i=array_search($id,studio_extra_levels(),true);return $i===false?'Level '.$id:'Level '.(8+$i);}
function studio_level_cover(int $id):string{$l=studio_level($id);return $l&&$l['cover_path']&&resolved_image_path((string)$l['cover_path'])?(string)resolved_image_path((string)$l['cover_path']):'';}
/** Hides draft new levels from pupils and teachers (the Content Developer's test pupil and the studio still see them). */
function studio_hide_levels_sql(string $col):string{
 if(!studio_levels_ready())return '';$u=function_exists('current_user')?current_user():null;
 if($u&&(!empty($u['is_tester'])||($u['role']??'')==='studio'))return '';
 return ' AND '.$col." NOT IN (SELECT level_id FROM studio_levels WHERE status<>'published')";
}
/** Lessons a pupil sees: published ones; the test pupil also sees the studio's drafts. */
function studio_pub_sql(string $l,int $pid):string{return is_tester($pid)&&studio_ready()?'('.$l.".published=1 OR ".$l.".id IN (SELECT lesson_id FROM studio_lessons WHERE status<>'published'))":$l.'.published=1';}
/** Levels where the studio can add lessons: Level 1 and every new level (they use the Level 1 screens). */
function studio_lessons_allowed(int $level):bool{return $level===1||studio_level($level)!==null;}
function studio_level_module(int $level):int{return (int)(val('SELECT id FROM modules WHERE level_id=? ORDER BY id LIMIT 1',[$level])?:0);}
function studio_css_link():string{$u=function_exists('current_user')?current_user():null;return ($u&&(($u['role']??'')==='studio'||($u['active_role']??'')==='central_admin'||!empty($_SESSION['studio_dev'])))?'<link rel="stylesheet" href="'.asset_url('studio.css').'">':'';}
function studio_is_dev(?array $u):bool{return $u&&($u['role']??'')==='studio';}

/* ---------- the yellow TEST MODE bar (on every page while a Content Developer tests as a pupil) ---------- */
function studio_test_bar():string{
 if(empty($_SESSION['studio_dev']))return '';$u=current_user();if(!$u||empty($u['is_tester']))return '';
 return '<div class="st-testbar" role="status"><span class="st-tb-tag">'.icon('eye').'TEST MODE</span><span class="st-tb-tx">You are testing as a pupil. All levels are open and nothing here counts in any report.</span><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_test_back"><button class="st-tb-btn">'.icon('arrow','flip').'Back to <span class="st-tb-long">Content </span>Studio</button></form></div>';
}

/* ---------- the test pupil ---------- */
function studio_tester_of(int $dev):int{return (int)(val('SELECT pupil_id FROM studio_testers WHERE dev_id=?',[$dev])?:0);}
function studio_tester_make(array $dev,int $grade):int{
 $tid=studio_tester_of((int)$dev['id']);
 if($tid){q('UPDATE pupils SET grade_level=? WHERE user_id=?',[$grade,$tid]);return $tid;}
 db()->beginTransaction();try{
  $pid='X'.$dev['public_id'];$first=explode(' ',trim((string)$dev['name']))[0];
  q('INSERT INTO users(public_id,role,name,password_hash,school_id) VALUES(?,?,?,?,NULL)',[$pid,'tester','Test Pupil ('.$first.')',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$tid=(int)db()->lastInsertId();
  q('INSERT INTO pupils(user_id,grade_level,section) VALUES(?,?,?)',[$tid,$grade,'Test mode']);
  q('INSERT IGNORE INTO teachers(user_id) VALUES(?)',[$tid]);   // only so the level row below has an "assigned by" (no teacher page reads this table)
  q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?)',[$tid,LEVEL_ORDER[0],$tid]);
  q('INSERT INTO studio_testers(dev_id,pupil_id) VALUES(?,?)',[$dev['id'],$tid]);
  db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 return $tid;
}
/** Removes everything the test pupil did (answers, recordings, XP, badges, progress) and the test pupil itself. */
function studio_tester_wipe(int $tid):void{
 if(!is_tester($tid))return;
 $done='SELECT id FROM activity_completion WHERE pupil_id=?';
 q('DELETE FROM response_history WHERE completion_id IN('.$done.') OR actor_id=?',[$tid,$tid]);
 if(function_exists('rv_ready')&&rv_ready())q('DELETE FROM reading_verifications WHERE pupil_id=?',[$tid]);
 foreach(['activity_completion','activity_drafts','assessment_attempts','reading_assessments','pupil_progress','pupil_badges','pupil_rewards','pupil_xp','xp_transactions','pupil_streaks','learning_days','pupil_level_assignments','pupil_sections','teacher_pupils','pupil_details','help_requests','reading_checks'] as $t)
  del_q($t,'DELETE FROM '.$t.' WHERE pupil_id=?',[$tid]);
 foreach(studio_pupil_tables() as $t)del_q($t,'DELETE FROM '.$t.' WHERE pupil_id=?',[$tid]);
 del_user_rows($tid);q('DELETE FROM studio_testers WHERE pupil_id=?',[$tid]);q('DELETE FROM pupils WHERE user_id=?',[$tid]);q('DELETE FROM teachers WHERE user_id=?',[$tid]);
 del_unlink_actor($tid);del_settings($tid);q("DELETE FROM users WHERE id=? AND role='tester'",[$tid]);
}
/** Any other table with a pupil_id column (added by later versions), so a reset never leaves rows behind. */
function studio_pupil_tables():array{return array_column(rows("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='pupil_id' AND TABLE_NAME NOT IN ('pupils','studio_testers')"),'t');}

/* ---------- lessons ---------- */
function studio_modules(int $level):array{return rows('SELECT * FROM modules WHERE level_id=? ORDER BY grade_level,id',[$level]);}
function studio_lesson(int $lid):array{$l=one('SELECT l.*,m.level_id,m.grade_level module_grade,m.title module_title FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?',[$lid]);if(!$l)fail('Lesson not found.',404);return $l;}
function studio_acts(int $lid):array{return rows("SELECT a.*,q.grading FROM activities a LEFT JOIN questions q ON q.activity_id=a.id WHERE a.lesson_id=? ORDER BY FIELD(a.phase,'pre','learn','post'),a.position",[$lid]);}
function studio_template_of(array $a):string{foreach(STUDIO_TEMPLATES as $k=>$t)if($t[2]===$a['type']&&$t[3]===$a['response_mode'])return $k;return $a['type']==='sentence'?'speak':'look';}
function studio_status_tag(?array $s):string{
 if(!$s)return '<span class="st-tag st-off">'.icon('lock').'Official</span>';
 return ['draft'=>'<span class="st-tag st-draft">'.icon('edit').'Draft</span>','review'=>'<span class="st-tag st-rev">'.icon('clock').'Waiting for review</span>','published'=>'<span class="st-tag st-pub">'.icon('check').'Published</span>'][$s['status']]??'';
}
/** Only a draft can be changed (a lesson waiting for review or published is locked; the Division Admin can send it back). */
function studio_editable(int $lid):array{$s=studio_row($lid);if(!$s)fail('Official lessons cannot be changed in the Content Studio.',403);if($s['status']!=='draft')fail($s['status']==='review'?'This lesson is waiting for review. The Division Admin can send it back to you for changes.':'This lesson is published. Ask the Division Admin to hide it first if it needs changes.',403);return $s;}
function studio_snapshot(int $lid,string $what):void{
 $l=one('SELECT title,subtitle,objectives,image_path FROM lessons WHERE id=?',[$lid]);$acts=[];
 foreach(studio_acts($lid) as $a)$acts[]=array_intersect_key($a,array_flip(['phase','position','title','type','instructions','prompt','image_path','image_paths','narration','expected_text','xp_reward','response_mode']));
 $n=(int)val('SELECT COALESCE(MAX(revision),0)+1 FROM studio_revisions WHERE lesson_id=?',[$lid]);
 q('INSERT INTO studio_revisions(lesson_id,revision,what,snapshot,made_by) VALUES(?,?,?,?,?)',[$lid,$n,mb_substr($what,0,120),json_encode(['lesson'=>$l,'activities'=>$acts],JSON_UNESCAPED_UNICODE),current_user()['id']??null]);
}
/** Clears every row that points at these rows (found from the database's own foreign keys, so tables added later are
    covered too): a link that may be empty is emptied, otherwise the pointing row is removed (after its own pointers). */
function studio_clear_refs(string $table,array $ids,int $depth=0):void{
 $ids=array_values(array_filter(array_map('intval',$ids)));if(!$ids||$depth>4)return;$in=implode(',',$ids);
 foreach(rows("SELECT k.TABLE_NAME t,k.COLUMN_NAME c,col.IS_NULLABLE n FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.COLUMNS col ON col.TABLE_SCHEMA=k.TABLE_SCHEMA AND col.TABLE_NAME=k.TABLE_NAME AND col.COLUMN_NAME=k.COLUMN_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME=? AND k.REFERENCED_COLUMN_NAME='id'",[$table]) as $r){
  $t=preg_replace('/[^a-z0-9_]/i','',(string)$r['t']);$c=preg_replace('/[^a-z0-9_]/i','',(string)$r['c']);if($t===''||$c===''||$t===$table)continue;
  if($r['n']==='YES'&&!in_array($c,['activity_id','lesson_id','question_id','completion_id'],true)){q("UPDATE `$t` SET `$c`=NULL WHERE `$c` IN ($in)");continue;}
  if(one("SHOW COLUMNS FROM `$t` LIKE 'id'"))studio_clear_refs($t,array_column(rows("SELECT id FROM `$t` WHERE `$c` IN ($in)"),'id'),$depth+1);
  q("DELETE FROM `$t` WHERE `$c` IN ($in)");}
}
/** Removes a studio activity and every row that points at it (only test pupils can have such rows while it was never published). */
function studio_act_wipe(int $aid):void{
 if(function_exists('rv_ready')&&rv_ready())del_q('reading_verifications','DELETE FROM reading_verifications WHERE activity_id=?',[$aid]);
 studio_clear_refs('activities',[$aid]);
 foreach(rows("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='activity_id' AND TABLE_NAME NOT IN ('activities')") as $r)q('DELETE FROM `'.preg_replace('/[^a-z0-9_]/i','',(string)$r['t']).'` WHERE activity_id=?',[$aid]);   // tables without a foreign key
 q('DELETE FROM activities WHERE id=?',[$aid]);
}
function studio_act_insert(int $lid,array $f):int{
 $pos=(int)val("SELECT COALESCE(MAX(position),0)+1 FROM activities WHERE lesson_id=? AND phase='learn'",[$lid]);
 q('INSERT INTO activities(lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) VALUES(?,NULL,?,?,?,?,?,?,?,?,?,?,?,0,?,0,1,?)',
  [$lid,'learn',$pos,$f['title'],$f['type'],$f['instructions'],$f['prompt'],$f['image_path'],$f['image_paths'],$f['narration'],$f['expected_text'],$f['xp_reward'],mb_substr($f['prompt'],0,500),$f['response_mode']]);
 $aid=(int)db()->lastInsertId();q('INSERT INTO questions(activity_id,content,grading) VALUES(?,?,?)',[$aid,$f['prompt'],'teacher']);return $aid;
}
/** The fields of an activity from the form (pictures: keep, remove or add up to three). */
function studio_act_fields(?array $old):array{
 $k=(string)($_POST['template']??'speak');$t=STUDIO_TEMPLATES[$k]??null;if(!$t)fail('Choose a template.');
 $prompt=trim(mb_substr((string)($_POST['prompt']??''),0,4000));if(mb_strlen($prompt)<2)fail($k==='read'?'Type the words the pupil will read.':'Type the question or the directions for the pupil.');
 $title=trim(mb_substr((string)($_POST['title']??''),0,120));if($title==='')$title=$t[0];
 $narr=trim(mb_substr((string)($_POST['narration']??''),0,4000));if($narr==='')$narr=$prompt;
 $xp=max(0,min(50,(int)($_POST['xp']??10)));
 $imgs=$old?array_values(array_filter(json_decode((string)($old['image_paths']??'[]'),true)?:[],'is_string')):[];
 $drop=array_map('strval',(array)($_POST['drop_img']??[]));$imgs=array_values(array_filter($imgs,fn($p)=>!in_array($p,$drop,true)));
 foreach(['pic1','pic2','pic3'] as $fld)if(!empty($_FILES[$fld])&&($_FILES[$fld]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE&&count($imgs)<3)$imgs[]=save_uploaded_image($fld);
 return ['title'=>$title,'type'=>$t[2],'response_mode'=>$t[3],'instructions'=>$t[4],'prompt'=>$prompt,'narration'=>$narr,'expected_text'=>$k==='read'?$prompt:null,'xp_reward'=>$t[3]==='none'?0:$xp,'image_paths'=>json_encode($imgs,JSON_UNESCAPED_SLASHES),'image_path'=>$imgs[0]??null];
}

/* ---------- actions ---------- */
function studio_action(string $action):never{
 if(!studio_ready())fail('Back up the database, then import database/migrations/045_content_studio.sql to turn on the Content Studio.');
 $u=current_user();if(!$u)fail('Please sign in again.',401);
 if($action==='studio_test_back'){
  $dev=(int)($_SESSION['studio_dev']??0);if(!$dev||empty($u['is_tester'])||studio_tester_of($dev)!==(int)$u['id'])fail('Not in test mode.',403);
  session_regenerate_id(true);$_SESSION['uid']=$dev;unset($_SESSION['studio_dev']);$_SESSION['active_role']='content_dev:0';$_SESSION['csrf']=bin2hex(random_bytes(32));
  go('?page='.(($b=(string)($_SESSION['studio_back']??''))!==''?$b:'studio_test'));}
 if(in_array($action,STUDIO_ADMIN_ACTIONS,true)){
  if(($u['active_role']??'')!=='central_admin'||($u['role']??'')!=='admin')fail('Only the Division Admin reviews lessons.',403);
  if(str_starts_with($action,'studio_level_'))studio_level_admin_action($action,$u);
  $lid=(int)($_POST['lesson']??0);$s=studio_row($lid);if(!$s)fail('Lesson not found.',404);$l=studio_lesson($lid);
  if($action==='studio_approve'){if($s['status']!=='review')fail('This lesson is not waiting for review.');
   db()->beginTransaction();try{q('UPDATE activities SET published=1 WHERE lesson_id=?',[$lid]);q('UPDATE lessons SET published=1 WHERE id=?',[$lid]);
    q("UPDATE studio_lessons SET status='published',note=NULL,reviewed_by=?,published_at=NOW() WHERE lesson_id=?",[$u['id'],$lid]);studio_snapshot($lid,'Published');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
   if($s['created_by'])notify((int)$s['created_by'],'note','Your lesson is published','“'.$l['title'].'” is approved. Pupils can see it now.','?page=studio_lesson&id='.$lid);
   audit('studio_approve',(string)$lid);flash('Published. Pupils can see “'.$l['title'].'” now.');go('?page=studio_review');}
  if($action==='studio_sendback'){if($s['status']!=='review')fail('This lesson is not waiting for review.');$note=trim(mb_substr((string)($_POST['note']??''),0,1000));if(mb_strlen($note)<3)fail('Write a short note: what should be changed?');
   q("UPDATE studio_lessons SET status='draft',note=?,reviewed_by=? WHERE lesson_id=?",[$note,$u['id'],$lid]);
   if($s['created_by'])notify((int)$s['created_by'],'note','Your lesson needs changes','“'.$l['title'].'”: '.$note,'?page=studio_lesson&id='.$lid);
   audit('studio_sendback',(string)$lid);flash('Sent back with your note.');go('?page=studio_review');}
  if($action==='studio_hide'){if($s['status']!=='published')fail('This lesson is not published.');
   db()->beginTransaction();try{q('UPDATE lessons SET published=0 WHERE id=?',[$lid]);q('UPDATE activities SET published=0 WHERE lesson_id=?',[$lid]);q("UPDATE studio_lessons SET status='draft',note=? WHERE lesson_id=?",['Hidden from pupils by the Division Admin. Pupils’ saved work is kept.',$lid]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
   if($s['created_by'])notify((int)$s['created_by'],'note','A lesson was hidden','“'.$l['title'].'” is hidden from pupils and back in your drafts.','?page=studio_lesson&id='.$lid);
   audit('studio_hide',(string)$lid);flash('Hidden from pupils. It is a draft again.');go('?page=studio_review');}
 }
 if(!studio_is_dev($u))fail('Only a Content Developer can do this.',403);
 if(str_starts_with($action,'studio_level_')||str_starts_with($action,'studio_import_'))studio_level_action($action,$u);
 if($action==='studio_test_start'){
  $grade=max(1,min(10,(int)($_POST['grade']??1)));$tid=studio_tester_make($u,$grade);$to=(int)($_POST['lesson']??0);
  $_SESSION['studio_back']=$to?'studio_lesson&id='.$to:'studio_test';
  session_regenerate_id(true);$_SESSION['uid']=$tid;$_SESSION['studio_dev']=(int)$u['id'];unset($_SESSION['active_role'],$_SESSION['role_pop'],$_SESSION['hello']);$_SESSION['csrf']=bin2hex(random_bytes(32));
  if($to){$a=(int)(val("SELECT id FROM activities WHERE lesson_id=? ORDER BY FIELD(phase,'pre','learn','post'),position LIMIT 1",[$to])?:0);go('?page=lesson&id='.$to.($a?'&activity='.$a:''));}
  go('?page=lessons');}
 if($action==='studio_test_reset'){$tid=studio_tester_of((int)$u['id']);if($tid)studio_tester_wipe($tid);audit('studio_test_reset','');flash('Your test pupil is fresh again: no answers, XP or badges.');go('?page=studio_test');}
 if($action==='studio_lesson_add'){
  $level=(int)($_POST['level']??0);if(!studio_lessons_allowed($level))fail('New lessons can be added to Level 1 and to the new levels.');if(($lv=studio_level($level))&&$lv['status']==='review')fail('This level is waiting for review.');
  $mod=(int)($_POST['module']??0);if(!val('SELECT 1 FROM modules WHERE id=? AND level_id=?',[$mod,$level]))fail('Choose the module.');
  $title=trim(mb_substr((string)($_POST['title']??''),0,200));$sub=trim(mb_substr((string)($_POST['subtitle']??''),0,80));if(mb_strlen($title)<3)fail('Type the lesson title.');if($sub==='')$sub=mb_substr($title,0,60);
  $obj=trim(mb_substr((string)($_POST['objectives']??''),0,3000));
  db()->beginTransaction();try{$pos=(int)val('SELECT COALESCE(MAX(position),0)+1 FROM lessons WHERE module_id=?',[$mod]);
   q('INSERT INTO lessons(module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) VALUES(?,?,?,?,?,?,?,NULL,0)',[$mod,$pos,$title,$sub,$obj,'','[]']);$lid=(int)db()->lastInsertId();
   q("INSERT INTO studio_lessons(lesson_id,status,created_by) VALUES(?,'draft',?)",[$lid,$u['id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  audit('studio_lesson_add',(string)$lid);flash('Lesson added as a draft. Now add its activities.');go('?page=studio_lesson&id='.$lid.'#st-add');}
 $lid=(int)($_POST['lesson']??0);
 if($action==='studio_restore'){$es=studio_editable($lid);if($es['published_at'])fail('This lesson was published before, so pupils may have answers in it. A saved copy cannot replace it; edit the activities instead.',403);$r=one('SELECT * FROM studio_revisions WHERE id=? AND lesson_id=?',[(int)($_POST['rev']??0),$lid]);if(!$r)fail('Saved copy not found.',404);$snap=json_decode((string)$r['snapshot'],true);if(!is_array($snap))fail('This saved copy cannot be read.');
  db()->beginTransaction();try{studio_snapshot($lid,'Before restoring copy '.$r['revision']);foreach(studio_acts($lid) as $a)studio_act_wipe((int)$a['id']);
   $L=$snap['lesson']??[];q('UPDATE lessons SET title=?,subtitle=?,objectives=? WHERE id=?',[(string)($L['title']??''),(string)($L['subtitle']??''),(string)($L['objectives']??''),$lid]);
   foreach($snap['activities']??[] as $a)studio_act_insert($lid,['title'=>(string)$a['title'],'type'=>(string)$a['type'],'response_mode'=>(string)$a['response_mode'],'instructions'=>(string)$a['instructions'],'prompt'=>(string)$a['prompt'],'narration'=>(string)$a['narration'],'expected_text'=>$a['expected_text']??null,'xp_reward'=>(int)$a['xp_reward'],'image_paths'=>(string)($a['image_paths']??'[]'),'image_path'=>$a['image_path']??null]);
   db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  audit('studio_restore',$lid.' → '.$r['revision']);flash('Restored copy '.$r['revision'].'. The version before it was saved too.');go('?page=studio_lesson&id='.$lid);}
 if($action==='studio_lesson_save'){studio_editable($lid);$title=trim(mb_substr((string)($_POST['title']??''),0,200));if(mb_strlen($title)<3)fail('Type the lesson title.');$sub=trim(mb_substr((string)($_POST['subtitle']??''),0,80))?:mb_substr($title,0,60);
  q('UPDATE lessons SET title=?,subtitle=?,objectives=? WHERE id=?',[$title,$sub,trim(mb_substr((string)($_POST['objectives']??''),0,3000)),$lid]);flash('Lesson saved.');go('?page=studio_lesson&id='.$lid);}
 if($action==='studio_lesson_delete'){$s=studio_editable($lid);if($s['published_at'])fail('This lesson was published before, so pupils may have answers in it. Keep it hidden instead of deleting it.',403);
  db()->beginTransaction();try{foreach(studio_acts($lid) as $a)studio_act_wipe((int)$a['id']);q('DELETE FROM studio_revisions WHERE lesson_id=?',[$lid]);q('DELETE FROM studio_lessons WHERE lesson_id=?',[$lid]);studio_clear_refs('lessons',[$lid]);
   $pos=one('SELECT module_id,position FROM lessons WHERE id=?',[$lid]);q('DELETE FROM lessons WHERE id=?',[$lid]);
   if($pos)q('UPDATE lessons SET position=position-1 WHERE module_id=? AND position>? ORDER BY position',[$pos['module_id'],$pos['position']]);   // no gap in the lesson numbers
   db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  audit('studio_lesson_delete',(string)$lid);flash('Draft lesson deleted.');go('?page=studio_lessons');}
 if($action==='studio_act_add'){studio_editable($lid);$f=studio_act_fields(null);studio_act_insert($lid,$f);flash('Activity added. Choose Test this lesson to answer it as a pupil.');go('?page=studio_lesson&id='.$lid.'#st-acts');}
 $aid=(int)($_POST['activity']??0);$a=one('SELECT * FROM activities WHERE id=? AND lesson_id=?',[$aid,$lid]);
 if(in_array($action,['studio_act_save','studio_act_delete','studio_act_move'],true)){$es=studio_editable($lid);if(!$a)fail('Activity not found.',404);if($action==='studio_act_delete'&&$es['published_at'])fail('This lesson was published before, so pupils may have answers in this activity. Edit it instead of deleting it.',403);}
 if($action==='studio_act_save'){$f=studio_act_fields($a);
  q('UPDATE activities SET title=?,type=?,response_mode=?,instructions=?,prompt=?,narration=?,expected_text=?,xp_reward=?,image_paths=?,image_path=?,source_excerpt=?,revision=revision+1 WHERE id=?',[$f['title'],$f['type'],$f['response_mode'],$f['instructions'],$f['prompt'],$f['narration'],$f['expected_text'],$f['xp_reward'],$f['image_paths'],$f['image_path'],mb_substr($f['prompt'],0,500),$aid]);
  q('UPDATE questions SET content=? WHERE activity_id=?',[$f['prompt'],$aid]);flash('Activity saved.');go('?page=studio_lesson&id='.$lid.'#st-a'.$aid);}
 if($action==='studio_act_delete'){db()->beginTransaction();try{studio_act_wipe($aid);$i=1;foreach(rows("SELECT id FROM activities WHERE lesson_id=? AND phase='learn' ORDER BY position",[$lid]) as $r)q('UPDATE activities SET position=? WHERE id=?',[-($i++),$r['id']]);q("UPDATE activities SET position=-position WHERE lesson_id=? AND phase='learn'",[$lid]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Activity deleted.');go('?page=studio_lesson&id='.$lid.'#st-acts');}
 if($action==='studio_act_move'){$dir=(string)($_POST['dir']??'')==='up'?-1:1;$ids=array_map('intval',array_column(rows("SELECT id FROM activities WHERE lesson_id=? AND phase='learn' ORDER BY position",[$lid]),'id'));$i=array_search($aid,$ids,true);$j=$i===false?false:$i+$dir;
  if($i!==false&&isset($ids[$j])){[$ids[$i],$ids[$j]]=[$ids[$j],$ids[$i]];db()->beginTransaction();try{foreach($ids as $n=>$id)q('UPDATE activities SET position=? WHERE id=?',[-($n+1),$id]);q("UPDATE activities SET position=-position WHERE lesson_id=? AND phase='learn'",[$lid]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}}
  go('?page=studio_lesson&id='.$lid.'#st-a'.$aid);}
 if($action==='studio_send'){studio_editable($lid);if(($lv=studio_level(lesson_level($lid)))&&$lv['status']!=='published')fail('This lesson is in a new level. Send the whole level for review from the level page.');if(!val('SELECT COUNT(*) FROM activities WHERE lesson_id=?',[$lid]))fail('Add at least one activity first.');
  if(empty($_POST['checked']))fail('Tick the box to confirm you tested the lesson and checked it against the module.');
  q("UPDATE studio_lessons SET status='review',sent_at=NOW(),note=NULL WHERE lesson_id=?",[$lid]);studio_snapshot($lid,'Sent for review');$l=studio_lesson($lid);
  foreach(rows("SELECT DISTINCT u.id FROM users u JOIN user_roles r ON r.user_id=u.id AND r.role='central_admin' WHERE u.active=1") as $x)notify((int)$x['id'],'note','A lesson is waiting for your review','“'.$l['title'].'” from '.name_case((string)$u['name']).'.','?page=studio_review&id='.$lid);
  audit('studio_send',(string)$lid);flash('Sent to the Division Admin for review.');go('?page=studio_lesson&id='.$lid);}
 fail('Unknown action.');
}

/* ---------- pages: Content Developer ---------- */
function studio_setup_card():bool{if(studio_ready())return false;echo '<section class="card"><h1>Content Studio</h1><p>'.icon('info').' Back up the database, then import <b>database/migrations/045_content_studio.sql</b> in phpMyAdmin to turn on the Content Studio.</p></section>';return true;}
function studio_home(array $u):void{
 if(studio_setup_card())return;$mine=rows('SELECT s.*,l.title,l.subtitle,l.position,m.level_id,(SELECT COUNT(*) FROM activities a WHERE a.lesson_id=l.id) acts FROM studio_lessons s JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id ORDER BY s.updated_at DESC LIMIT 8');
 $n=fn($st)=>(int)val('SELECT COUNT(*) FROM studio_lessons WHERE status=?',[$st]);
 echo '<section class="st-hero"><div><span class="eyebrow">CONTENT DEVELOPER</span><h1>Hi, '.e(explode(' ',name_case(trim((string)$u['name'])))[0]).'! Ready to build a lesson?</h1><p>Add lessons and activities from the official BULIG modules. Nothing reaches pupils until the Division Admin approves it.</p><div class="buttonrow"><a class="btn primary" href="?page=studio_lessons#st-new">'.icon('book').'New lesson</a><a class="btn secondary" href="?page=studio_test">'.icon('eye').'Test as Pupil</a></div></div><img src="'.e(friend_img('bob','blueprint')).'" alt=""></section>';
 echo admin_kpis([['book',number_format((int)val('SELECT COUNT(*) FROM lessons WHERE published=1')),'Lessons pupils see','',false],['edit',$n('draft'),'Drafts','',false],['clock',$n('review'),'Waiting for review','',$n('review')>0],['check',$n('published'),'Published from the studio','',false]]);
 echo '<div class="st-two"><section class="card"><div class="ws-hd"><h2>'.icon('clock').'Recent work</h2><a class="btn secondary small" href="?page=studio_lessons">All lessons</a></div>';
 foreach($mine as $r){echo '<a class="st-row" href="?page=studio_lesson&amp;id='.(int)$r['lesson_id'].'"><span class="st-ic">'.icon('book').'</span><span class="st-rt"><small>'.e(level_label((int)$r['level_id'])).' · Lesson '.(int)$r['position'].'</small><b>'.e($r['title']).'</b>'.($r['note']&&$r['status']==='draft'?'<em>'.icon('alert').e($r['note']).'</em>':'').'</span><span class="st-n">'.(int)$r['acts'].' '.((int)$r['acts']===1?'activity':'activities').'</span>'.studio_status_tag($r).'</a>';}
 if(!$mine)echo '<p class="muted">No lessons yet. Choose <b>New lesson</b> to start.</p>';
 echo '</section><section class="card"><h2>'.icon('shield').'What this role can do</h2><ul class="st-can">';
 foreach([[1,'Add lessons and activities as drafts'],[1,'Upload pictures for activities'],[1,'Test every level as a pupil, answering for real'],[1,'Send a lesson to the Division Admin for review'],[0,'Publish without the Division Admin’s approval'],[0,'Change the official module lessons'],[0,'See pupils, teachers, scores or recordings'],[0,'Create or change any account']] as [$ok,$t])echo '<li class="'.($ok?'ok':'no').'">'.icon($ok?'check':'lock').($ok?'':'Cannot: ').e($t).'</li>';
 echo '</ul></section></div>';
}
function studio_lessons_view(array $u):void{
 if(studio_setup_card())return;$level=(int)($_GET['level']??1);if(!val('SELECT 1 FROM bulig_levels WHERE id=?',[$level]))$level=1;
 echo hm_head('CONTENT STUDIO','Levels and Lessons','Official lessons are read-only. New lessons are added at the end of a level, as drafts.');
 echo '<nav class="st-levels" aria-label="Levels">';$newL=isset($_GET['new_level'])&&studio_levels_ready();foreach(level_sequence() as $lv)echo '<a class="'.(!$newL&&$lv===$level?'on':'').(studio_draft_level($lv)?' st-lv-draft':'').'" href="?page=studio_lessons&amp;level='.$lv.'">'.e(level_label($lv)).(studio_draft_level($lv)?' · draft':'').'</a>';if(studio_levels_ready())echo '<a class="st-lv-new'.($newL?' on':'').'" href="?page=studio_lessons&amp;new_level=1">'.icon('book').'New level</a>';echo '</nav>';
 if($newL){studio_new_level_form();return;}
 if(studio_level($level))studio_level_card($level);
 $mods=studio_modules($level);$S=studio_ready()?array_column(rows('SELECT * FROM studio_lessons'),null,'lesson_id'):[];
 echo '<section class="card"><div class="ws-hd"><h2>'.icon('book').e(level_label($level)).' · '.e((string)val('SELECT title FROM bulig_levels WHERE id=?',[$level])).'</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_test_start"><input type="hidden" name="grade" value="'.(int)($mods[0]['grade_level']??1).'"><button class="btn secondary small">'.icon('eye').'Test this level as a pupil</button></form></div>';
 foreach($mods as $m){$ls=rows('SELECT l.*,(SELECT COUNT(*) FROM activities a WHERE a.lesson_id=l.id) acts FROM lessons l WHERE l.module_id=? ORDER BY l.position',[$m['id']]);
  echo '<details class="st-mod"'.(count($mods)===1?' open':'').'><summary><b>'.e($m['title']).'</b><span>'.count($ls).' lessons</span></summary><div class="st-list">';
  foreach($ls as $l){$s=$S[(int)$l['id']]??null;if(!$s&&!$l['published'])continue;
   echo '<div class="st-row'.($s?' st-mine':'').'"><span class="st-num">'.(int)$l['position'].'</span><span class="st-rt"><b>'.e($l['subtitle']?:$l['title']).'</b><small>'.e($l['title']).'</small></span><span class="st-n">'.(int)$l['acts'].' activities</span>'.studio_status_tag($s).($s?'<a class="btn secondary small" href="?page=studio_lesson&amp;id='.(int)$l['id'].'">'.icon('edit').'Open</a>':'').'</div>';}
  echo '</div></details>';}
 echo '</section>';
 echo '<section class="card" id="st-new"><div class="ws-hd"><h2>'.icon('book').'Add a new lesson</h2></div>';
 if(studio_lessons_allowed($level)){
  echo '<p class="muted">The lesson goes at the end of '.e(level_label($level)).', after the official lessons. It stays a draft, hidden from pupils, until the Division Admin approves it.</p><form method="post" class="st-form">'.csrf_field().'<input type="hidden" name="action" value="studio_lesson_add"><input type="hidden" name="level" value="'.$level.'">'
   .'<label>Module<select name="module" required>';foreach($mods as $m)echo '<option value="'.(int)$m['id'].'">'.e($m['title']).'</option>';
  echo '</select></label><label>Lesson title<input name="title" maxlength="200" required placeholder="e.g. Using Rhyming Words"></label><label>Short name on the road map<input name="subtitle" maxlength="80" placeholder="e.g. Rhyming Words"></label><label>What pupils will learn (one per line)<textarea name="objectives" rows="3" maxlength="3000" placeholder="1. Say words that rhyme.&#10;2. Find rhyming pairs in a poem."></textarea></label><button class="btn primary">'.icon('check').'Add lesson as a draft</button></form>';
 }else echo '<p class="st-soon">'.icon('info').' In this version, new lessons can be added to <b>Level 1</b>, whose screens are built from the database. '.e(level_label($level)).' uses activity cards made from the module; adding cards here comes in a later update. You can still <b>test</b> every '.e(level_label($level)).' activity as a pupil.</p>';
 echo '</section>';
}
function studio_lesson_view(array $u):void{
 if(studio_setup_card())return;$lid=(int)($_GET['id']??0);$l=studio_lesson($lid);$s=studio_row($lid);if(!$s)fail('This is an official lesson. Official lessons are read-only.',403);
 $acts=studio_acts($lid);$ed=$s['status']==='draft';$was=(bool)$s['published_at'];   // published before: pupils may have answers, so nothing is deleted or replaced
 echo hm_head(e(mb_strtoupper(level_label((int)$l['level_id']))).' · LESSON '.(int)$l['position'],$l['title'],e($l['module_title']).' · '.studio_status_tag($s),'<a class="btn secondary" href="?page=studio_lessons&amp;level='.(int)$l['level_id'].'">'.icon('arrow','flip').'All lessons</a>');
 if($s['note']&&$ed)echo '<div class="st-note">'.icon('alert').'<div><b>Note from the Division Admin</b><p>'.e($s['note']).'</p></div></div>';
 if($s['status']==='review')echo '<div class="st-note st-blue">'.icon('clock').'<div><b>Waiting for the Division Admin</b><p>This lesson is locked while it is reviewed. You can still test it as a pupil.</p></div></div>';
 if($s['status']==='published')echo '<div class="st-note st-green">'.icon('check').'<div><b>Published</b><p>Pupils see this lesson at the end of '.e(level_label((int)$l['level_id'])).'. It is locked; the Division Admin can hide it if it needs changes.</p></div></div>';
 echo '<div class="st-two st-two-lesson"><div>';
 /* the lesson */
 echo '<section class="card"><div class="ws-hd"><h2>'.icon('book').'The lesson</h2></div>';
 if($ed)echo '<form method="post" class="st-form">'.csrf_field().'<input type="hidden" name="action" value="studio_lesson_save"><input type="hidden" name="lesson" value="'.$lid.'"><label>Lesson title<input name="title" maxlength="200" required value="'.e($l['title']).'"></label><label>Short name on the road map<input name="subtitle" maxlength="80" value="'.e($l['subtitle']).'"></label><label>What pupils will learn (one per line)<textarea name="objectives" rows="3" maxlength="3000">'.e($l['objectives']).'</textarea></label><button class="btn secondary">'.icon('check').'Save lesson</button></form>';
 else echo '<dl class="st-dl"><dt>Short name</dt><dd>'.e($l['subtitle']).'</dd><dt>Pupils will learn</dt><dd>'.nl2br(e($l['objectives']?:'—')).'</dd></dl>';
 echo '</section>';
 /* activities */
 echo '<section class="card" id="st-acts"><div class="ws-hd"><h2>'.icon('plan').'Activities</h2><span class="rs-sub">'.count($acts).'</span></div>';
 foreach($acts as $i=>$a){$k=studio_template_of($a);$t=STUDIO_TEMPLATES[$k];$imgs=activity_images($a);
  echo '<article class="st-act" id="st-a'.(int)$a['id'].'"><header><span class="st-anum">'.($i+1).'</span><span class="st-tpl">'.icon($t[1]).e($t[0]).'</span><b>'.e($a['title']).'</b><span class="xpchip">'.icon('bolt').(int)$a['xp_reward'].' XP</span></header><p class="st-prompt">'.nl2br(e($a['prompt'])).'</p>';
  if($imgs){echo '<div class="st-pics">';foreach($imgs as $p)if($rp=resolved_image_path((string)$p))echo '<img src="'.e($rp).'" alt="">';echo '</div>';}
  if($ed){echo '<div class="st-abtns">';foreach(['up'=>'Move up','down'=>'Move down'] as $d=>$tx)echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_act_move"><input type="hidden" name="lesson" value="'.$lid.'"><input type="hidden" name="activity" value="'.(int)$a['id'].'"><input type="hidden" name="dir" value="'.$d.'"><button class="btn secondary small"'.(($d==='up'&&$i===0)||($d==='down'&&$i===count($acts)-1)?' disabled':'').'>'.icon('arrow',$d==='up'?'up':'down').$tx.'</button></form>';
   if(!$was)echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_act_delete"><input type="hidden" name="lesson" value="'.$lid.'"><input type="hidden" name="activity" value="'.(int)$a['id'].'"><button class="btn secondary small st-del" data-confirm="Delete this activity? Your test pupil’s answers to it are deleted too.">'.icon('trash').'Delete</button></form>';echo '</div>';
   echo '<details class="st-edit"><summary>'.icon('edit').'Edit this activity</summary>'.studio_act_form($lid,$a).'</details>';}
  echo '</article>';}
 if(!$acts)echo '<p class="muted">No activities yet.</p>';
 echo '</section>';
 if($ed)echo '<section class="card" id="st-add"><div class="ws-hd"><h2>'.icon('book').'Add an activity</h2></div>'.studio_act_form($lid,null).'</section>';
 echo '</div><aside>';
 /* test, send, history */
 echo '<section class="card st-side"><h2>'.icon('eye').'Test it as a pupil</h2><p class="muted">Answer every activity for real: speak, read with Word Coach, draw, earn XP. Your test pupil is hidden from every report.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_test_start"><input type="hidden" name="lesson" value="'.$lid.'"><input type="hidden" name="grade" value="'.(int)($l['module_grade']?:1).'"><button class="btn primary"'.($acts?'':' disabled').'>'.icon('play').'Test this lesson</button></form></section>';
 $inDraftLevel=($lvR=studio_level((int)$l['level_id']))&&$lvR['status']!=='published';
 if($ed&&$inDraftLevel)echo '<section class="card st-side"><h2>'.icon('check').'Send for review</h2><p class="muted">This lesson is in the new level <b>'.e(level_label((int)$l['level_id'])).' · '.e($lvR['name']).'</b>. The whole level is sent for review together, from the level page.</p><a class="btn primary" href="?page=studio_lessons&amp;level='.(int)$l['level_id'].'">'.icon('arrow').'Go to the level</a></section>';
 elseif($ed)echo '<section class="card st-side"><h2>'.icon('check').'Send for review</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_send"><input type="hidden" name="lesson" value="'.$lid.'"><label class="st-check"><input type="checkbox" name="checked" value="1" required><span>I tested this lesson as a pupil and checked every activity against the official module.</span></label><button class="btn primary"'.($acts?'':' disabled').'>'.icon('arrow').'Send to the Division Admin</button></form></section>';
 $revs=rows('SELECT r.*,u.name FROM studio_revisions r LEFT JOIN users u ON u.id=r.made_by WHERE r.lesson_id=? ORDER BY r.revision DESC LIMIT 12',[$lid]);
 echo '<section class="card st-side"><h2>'.icon('clock').'Saved copies</h2><p class="muted">A copy is saved each time the lesson is sent for review or published.'.($was?' This lesson was published before, so pupils may have answers in it: activities can be edited, but not deleted or replaced by a copy.':'').'</p>';
 foreach($revs as $r)echo '<div class="st-hist"><span><b>Copy '.(int)$r['revision'].'</b><small>'.e($r['what']).' · '.e(date('M j, g:i A',strtotime((string)$r['made_at']))).'</small></span>'.($ed&&!$was?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_restore"><input type="hidden" name="lesson" value="'.$lid.'"><input type="hidden" name="rev" value="'.(int)$r['id'].'"><button class="btn secondary small" data-confirm="Restore copy '.(int)$r['revision'].'? The lesson now is saved as a copy first.">'.icon('replay').'Restore</button></form>':'').'</div>';
 if(!$revs)echo '<p class="muted">No copies yet.</p>';
 echo '</section>';
 if($ed&&!$s['published_at'])echo '<section class="card st-side"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_lesson_delete"><input type="hidden" name="lesson" value="'.$lid.'"><button class="linkbutton st-del" data-confirm="Delete this draft lesson and all its activities?">'.icon('trash').'Delete this draft lesson</button></form></section>';
 echo '</aside></div>';
}
function studio_act_form(int $lid,?array $a):string{
 $k=$a?studio_template_of($a):'speak';$imgs=$a?array_values(array_filter(json_decode((string)($a['image_paths']??'[]'),true)?:[],'is_string')):[];
 $h='<form method="post" enctype="multipart/form-data" class="st-form st-aform">'.csrf_field().'<input type="hidden" name="action" value="'.($a?'studio_act_save':'studio_act_add').'"><input type="hidden" name="lesson" value="'.$lid.'">'.($a?'<input type="hidden" name="activity" value="'.(int)$a['id'].'">':'');
 $h.='<fieldset class="st-tpls"><legend>1 · Choose a template</legend>';foreach(STUDIO_TEMPLATES as $key=>$t)$h.='<label class="st-tplc"><input type="radio" name="template" value="'.$key.'"'.($key===$k?' checked':'').' required><span>'.icon($t[1]).'<b>'.e($t[0]).'</b><small>'.e($t[5]).'</small></span></label>';
 $h.='</fieldset><label>2 · Question or directions (from the module)<textarea name="prompt" rows="3" maxlength="4000" required placeholder="e.g. Say a word that rhymes with cat.">'.e($a['prompt']??'').'</textarea><small>For Read aloud: type the exact words the pupil will read.</small></label>';
 $h.='<label>Title<input name="title" maxlength="120" value="'.e($a['title']??'').'" placeholder="Leave empty to use the template name"></label><label>What Max says aloud<textarea name="narration" rows="2" maxlength="4000" placeholder="Leave empty: Max reads the question">'.e($a&&$a['narration']!==$a['prompt']?$a['narration']:'').'</textarea></label>';
 $h.='<div class="st-grid"><label>XP reward<input type="number" name="xp" min="0" max="50" value="'.(int)($a['xp_reward']??10).'"></label></div>';
 $h.='<fieldset><legend>3 · Pictures (up to 3: PNG, JPEG or WebP)</legend>';
 if($imgs){$h.='<div class="st-pics">';foreach($imgs as $p)if($rp=resolved_image_path((string)$p))$h.='<label class="st-pic"><img src="'.e($rp).'" alt=""><span><input type="checkbox" name="drop_img[]" value="'.e($p).'">Remove</span></label>';$h.='</div>';}
 for($i=1;$i<=max(1,3-count($imgs));$i++)$h.='<input type="file" name="pic'.$i.'" accept="image/png,image/jpeg,image/webp">';
 $h.='<small>Use pictures redrawn for BULIG, the same style as the other lessons.</small></fieldset><button class="btn primary">'.icon('check').($a?'Save activity':'Add activity').'</button></form>';
 return $h;
}
function studio_test_view(array $u):void{
 if(studio_setup_card())return;$tid=studio_tester_of((int)$u['id']);$g=$tid?(int)val('SELECT grade_level FROM pupils WHERE user_id=?',[$tid]):1;
 $st=$tid?one('SELECT (SELECT COALESCE(total,0) FROM pupil_xp WHERE pupil_id=?) xp,(SELECT COUNT(*) FROM activity_completion WHERE pupil_id=?) acts,(SELECT COUNT(*) FROM pupil_progress WHERE pupil_id=? AND completed_at IS NOT NULL) lessons',[$tid,$tid,$tid]):null;
 echo hm_head('CONTENT STUDIO','Test as Pupil','Open BULIG exactly as a pupil sees it, and answer for real.');
 echo '<div class="st-two"><section class="card st-testcard"><img src="'.e(friend_img('george','map')).'" alt=""><div><h2>Your test pupil</h2><ul class="st-can"><li class="ok">'.icon('check').'Every level and lesson is open, in any order</li><li class="ok">'.icon('check').'Your draft lessons are open too</li><li class="ok">'.icon('check').'Speak, read with Word Coach, draw, earn XP and badges</li><li class="ok">'.icon('check').'Hidden: no school, class or teacher, never in reports or leaderboards</li></ul>';
 echo '<form method="post" class="st-form st-inline">'.csrf_field().'<input type="hidden" name="action" value="studio_test_start"><label>Test as a pupil in<select name="grade">';for($i=1;$i<=10;$i++)echo '<option value="'.$i.'"'.($i===$g?' selected':'').'>Grade '.$i.($i>=7?' (Junior High)':'').'</option>';
 echo '</select></label><button class="btn primary">'.icon('play').'Start testing</button></form><p class="muted st-small">Levels 4 to 7 have a different module for each grade. Choose the grade to test.</p></div></section>';
 echo '<section class="card"><h2>'.icon('chart').'What your test pupil did</h2>'.($st?admin_kpis([['bolt',(int)$st['xp'],'XP','',false],['check',(int)$st['acts'],'Activities answered','',false],['book',(int)$st['lessons'],'Lessons finished','',false]]):'<p class="muted">Nothing yet.</p>')
  .'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_test_reset"><button class="btn secondary"'.($tid?'':' disabled').' data-confirm="Clear everything your test pupil did? Answers, recordings, XP and badges are deleted.">'.icon('replay').'Reset test pupil</button></form><p class="muted st-small">Reset clears answers, recordings, XP and badges, so you can test again from the start.</p></section></div>';
}

/* ---------- page: the Division Admin's review ---------- */
function studio_review_view(array $u):void{
 if(studio_setup_card())return;if(($u['active_role']??'')!=='central_admin')fail('Only the Division Admin reviews lessons.',403);if(($lvq=(int)($_GET['level']??0))&&studio_levels_ready()){studio_review_level($lvq);return;}$lid=(int)($_GET['id']??0);
 if($lid){$l=studio_lesson($lid);$s=studio_row($lid);if(!$s)fail('Lesson not found.',404);$by=$s['created_by']?one('SELECT name,public_id FROM users WHERE id=?',[$s['created_by']]):null;
  echo hm_head('CONTENT REVIEW',$l['title'],e(level_label((int)$l['level_id'])).' · Lesson '.(int)$l['position'].' · from '.e($by?name_case((string)$by['name']).' · '.$by['public_id']:'—').' · '.studio_status_tag($s),'<a class="btn secondary" href="?page=studio_review">'.icon('arrow','flip').'All lessons</a>');
  echo '<div class="st-two st-two-lesson"><section class="card"><h2>'.icon('plan').'Activities</h2><p class="muted">Pupils will learn: '.e($l['objectives']?:'—').'</p>';
  foreach(studio_acts($lid) as $i=>$a){$t=STUDIO_TEMPLATES[studio_template_of($a)];echo '<article class="st-act"><header><span class="st-anum">'.($i+1).'</span><span class="st-tpl">'.icon($t[1]).e($t[0]).'</span><b>'.e($a['title']).'</b><span class="xpchip">'.icon('bolt').(int)$a['xp_reward'].' XP</span></header><p class="st-prompt">'.nl2br(e($a['prompt'])).'</p>';
   $imgs=activity_images($a);if($imgs){echo '<div class="st-pics">';foreach($imgs as $p)if($rp=resolved_image_path((string)$p))echo '<img src="'.e($rp).'" alt="">';echo '</div>';}echo '</article>';}
  echo '</section><aside>';
  if($s['status']==='review')echo '<section class="card st-side"><h2>'.icon('check').'Approve this lesson?</h2><p class="muted">Approving publishes it: pupils see it at the end of '.e(level_label((int)$l['level_id'])).'. It never re-locks a level a pupil already finished.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_approve"><input type="hidden" name="lesson" value="'.$lid.'"><button class="btn primary" data-confirm="Publish this lesson to pupils?">'.icon('check').'Approve and publish</button></form>'
   .'<form method="post" class="st-form">'.csrf_field().'<input type="hidden" name="action" value="studio_sendback"><input type="hidden" name="lesson" value="'.$lid.'"><label>Or send it back with a note<textarea name="note" rows="3" maxlength="1000" required placeholder="What should be changed?"></textarea></label><button class="btn secondary">'.icon('arrow','flip').'Send back</button></form></section>';
  if($s['status']==='published')echo '<section class="card st-side"><h2>'.icon('eye').'Published</h2><p class="muted">Hide it if it needs changes. Pupils’ saved work is kept.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_hide"><input type="hidden" name="lesson" value="'.$lid.'"><button class="btn secondary" data-confirm="Hide this lesson from pupils? It goes back to the Content Developer as a draft.">'.icon('lock').'Hide from pupils</button></form></section>';
  if($s['status']==='draft')echo '<section class="card st-side"><p class="muted">The Content Developer is still working on this draft.</p></section>';
  echo '</aside></div>';return;}
 echo hm_head('CONTENT REVIEW','Content Review','Lessons and new levels made by the Content Developers. Approve them to publish them to pupils.');
 if(studio_levels_ready()&&studio_levels_all()){echo '<section class="card"><div class="ws-hd"><h2>'.icon('book').'New levels</h2><span class="rs-sub">'.count(studio_levels_all()).'</span></div>';
  foreach(studio_levels_all() as $id=>$L)echo '<a class="st-row" href="?page=studio_review&amp;level='.$id.'"><span class="st-ic">'.icon('book').'</span><span class="st-rt"><small>'.e(level_label($id)).'</small><b>'.e($L['name']).'</b></span><span class="st-n">'.(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=?',[$id]).' lessons</span>'.studio_status_tag($L).'</a>';
  echo '</section>';}
 $groups=['review'=>'Waiting for your review','published'=>'Published','draft'=>'Drafts (still being made)'];
 foreach($groups as $st=>$tt){$ls=rows('SELECT s.*,l.title,l.position,m.level_id,u.name,(SELECT COUNT(*) FROM activities a WHERE a.lesson_id=l.id) acts FROM studio_lessons s JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id LEFT JOIN users u ON u.id=s.created_by WHERE s.status=?'.(studio_levels_ready()?" AND m.level_id NOT IN (SELECT level_id FROM studio_levels WHERE status<>'published')":'').' ORDER BY s.updated_at DESC',[$st]);
  echo '<section class="card"><div class="ws-hd"><h2>'.icon($st==='review'?'clock':($st==='published'?'check':'edit')).e($tt).'</h2><span class="rs-sub">'.count($ls).'</span></div>';
  foreach($ls as $r)echo '<a class="st-row" href="?page=studio_review&amp;id='.(int)$r['lesson_id'].'"><span class="st-ic">'.icon('book').'</span><span class="st-rt"><small>'.e(level_label((int)$r['level_id'])).' · Lesson '.(int)$r['position'].' · '.e(name_case((string)($r['name']??''))).'</small><b>'.e($r['title']).'</b></span><span class="st-n">'.(int)$r['acts'].' activities</span>'.studio_status_tag($r).'</a>';
  if(!$ls)echo '<p class="muted">None.</p>';echo '</section>';}
}
function studio_review_count():int{return (studio_ready()?(int)val("SELECT COUNT(*) FROM studio_lessons s JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id WHERE s.status='review'".(studio_levels_ready()?" AND m.level_id NOT IN (SELECT level_id FROM studio_levels WHERE status<>'published')":'')):0)+(studio_levels_ready()?(int)val("SELECT COUNT(*) FROM studio_levels WHERE status='review'"):0);}

/* ---------- v216 the pages for a new level ---------- */
function studio_new_level_form():void{
 $n=count(studio_extra_levels())+8;
 echo '<section class="card st-newlevel"><div class="ws-hd"><h2>'.icon('book').'Make a new level: Level '.$n.'</h2></div><p class="muted">The new level comes after Level 7 on every pupil’s road map, in the same design. It stays a draft, hidden from pupils and teachers, until the Division Admin approves it. Its activities use the same screens as Level 1: Max, the microphone, Word Coach, XP and badges.</p>'
  .'<form method="post" enctype="multipart/form-data" class="st-form">'.csrf_field().'<input type="hidden" name="action" value="studio_level_add">'
  .'<label>Level name<input name="name" maxlength="150" required placeholder="e.g. Reading for Fun"></label>'
  .'<label>Short name on the road map<input name="short_name" maxlength="60" placeholder="e.g. Reading for Fun"></label>'
  .'<label>Cover picture (optional: PNG, JPEG or WebP)<input type="file" name="cover" accept="image/png,image/jpeg,image/webp"><small>Leave it empty to use the first page of the module you upload.</small></label>'
  .'<button class="btn primary">'.icon('check').'Make Level '.$n.' as a draft</button></form></section>';
}
function studio_level_card(int $level):void{
 $L=studio_level($level);if(!$L)return;$ed=$L['status']==='draft';$cover=level_cover($level);
 $les=rows('SELECT s.status,COUNT(a.id) acts FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN studio_lessons s ON s.lesson_id=l.id LEFT JOIN activities a ON a.lesson_id=l.id WHERE m.level_id=? GROUP BY l.id,s.status',[$level]);
 $nl=count($les);$na=array_sum(array_column($les,'acts'));$pages=(int)val('SELECT COUNT(*) FROM studio_pages WHERE level_id=?',[$level]);
 echo '<section class="card st-levelcard"><div class="st-lc-top"><img src="'.e($cover).'" alt=""><div><span class="eyebrow">NEW LEVEL · '.e(mb_strtoupper(level_label($level))).'</span><h2>'.e($L['name']).'</h2><p class="muted">'.$nl.' '.($nl===1?'lesson':'lessons').' · '.$na.' '.($na===1?'activity':'activities').($pages?' · module: '.$pages.' pages':'').'</p>'.studio_status_tag($L).'</div></div>';
 if($L['note']&&$ed)echo '<div class="st-note">'.icon('alert').'<div><b>Note from the Division Admin</b><p>'.e($L['note']).'</p></div></div>';
 if($L['status']==='review')echo '<div class="st-note st-blue">'.icon('clock').'<div><b>Waiting for the Division Admin</b><p>The level and its lessons are locked while they are reviewed. You can still test them as a pupil.</p></div></div>';
 if($L['status']==='published')echo '<div class="st-note st-green">'.icon('check').'<div><b>Published</b><p>Pupils see this level after Level 7 on the road map. New lessons you add here are reviewed one by one.</p></div></div>';
 echo '<div class="st-lc-grid">';
 if($ed){
  echo '<div class="st-lc-box"><h3>'.icon('download').'Upload the module</h3><p class="muted">Choose the module PDF. BULIG reads every page and makes draft lessons and activities from it, with the page pictures. Then you check and fix each one.</p>'
   .'<form class="st-form st-import" data-st-import data-level="'.$level.'"><input type="file" accept="application/pdf" required data-st-pdf><label class="st-check"><input type="checkbox" data-st-replace'.($pages?'':' disabled').'><span>Replace the lessons made from an earlier upload</span></label><button class="btn primary">'.icon('download').'Upload and make the lessons</button>'
   .'<div class="st-progress" hidden data-st-prog><progress max="100" value="0"></progress><span data-st-msg>Reading the module…</span></div></form>'
   .'<script src="'.asset_url('pdfjs/pdf.min.js').'" defer></script><script src="'.asset_url('studio.js').'" defer data-worker="'.e(asset_url('pdfjs/pdf.worker.min.js')).'"></script></div>';
  echo '<div class="st-lc-box"><h3>'.icon('edit').'Name and cover</h3><form method="post" enctype="multipart/form-data" class="st-form">'.csrf_field().'<input type="hidden" name="action" value="studio_level_save"><input type="hidden" name="level" value="'.$level.'"><label>Level name<input name="name" maxlength="150" required value="'.e($L['name']).'"></label><label>Short name on the road map<input name="short_name" maxlength="60" value="'.e($L['short_name']).'"></label><label>New cover picture<input type="file" name="cover" accept="image/png,image/jpeg,image/webp"></label><button class="btn secondary">'.icon('check').'Save</button></form></div>';
  echo '<div class="st-lc-box"><h3>'.icon('check').'Send the level for review</h3><p class="muted">The level and all its draft lessons go to the Division Admin together.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_level_send"><input type="hidden" name="level" value="'.$level.'"><label class="st-check"><input type="checkbox" name="checked" value="1" required><span>I tested this level as a pupil and checked every lesson against the official module.</span></label><button class="btn primary"'.($na?'':' disabled').'>'.icon('arrow').'Send to the Division Admin</button></form>'
   .(!$L['published_at']?'<form method="post" class="st-lc-del">'.csrf_field().'<input type="hidden" name="action" value="studio_level_delete"><input type="hidden" name="level" value="'.$level.'"><button class="linkbutton st-del" data-confirm="Delete this draft level, all its lessons, activities and the uploaded module pages?">'.icon('trash').'Delete this draft level</button></form>':'').'</div>';}
 echo '</div></section>';
}

/* ---------- v216 level actions ---------- */
function studio_level_action(string $action,array $u):never{
 if(!studio_levels_ready())fail('Back up the database, then import database/migrations/047_studio_levels.sql to turn on new levels.');
 if($action==='studio_level_add'){
  $name=trim(mb_substr((string)($_POST['name']??''),0,150));if(mb_strlen($name)<3)fail('Type the level name.');$short=trim(mb_substr((string)($_POST['short_name']??''),0,60))?:mb_substr($name,0,60);
  $cover=(!empty($_FILES['cover'])&&($_FILES['cover']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)?save_uploaded_image('cover'):null;
  db()->beginTransaction();try{$id=max(10,(int)val('SELECT COALESCE(MAX(id),0)+1 FROM bulig_levels'));$mid=(int)val('SELECT COALESCE(MAX(id),0)+1 FROM modules');
   q('INSERT INTO bulig_levels(id,title,published) VALUES(?,?,0)',[$id,$name]);q('INSERT INTO modules(id,level_id,title,grade_level) VALUES(?,?,?,NULL)',[$mid,$id,$name]);
   q("INSERT INTO studio_levels(level_id,name,short_name,cover_path,status,created_by) VALUES(?,?,?,?,'draft',?)",[$id,$name,$short,$cover,$u['id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  audit('studio_level_add',(string)$id);flash('New level made as a draft. Upload the module, or add lessons one by one.');go('?page=studio_lessons&level='.$id);}
 $level=(int)($_POST['level']??0);$L=studio_level($level);if(!$L)fail('Level not found.',404);
 if(in_array($action,['studio_level_save','studio_level_delete','studio_level_send','studio_import_page','studio_import_build'],true)&&$L['status']!=='draft')fail($L['status']==='review'?'This level is waiting for review.':'This level is published. Ask the Division Admin to hide it first if it needs changes.',403);
 if($action==='studio_level_save'){$name=trim(mb_substr((string)($_POST['name']??''),0,150));if(mb_strlen($name)<3)fail('Type the level name.');$short=trim(mb_substr((string)($_POST['short_name']??''),0,60))?:mb_substr($name,0,60);
  $cover=(!empty($_FILES['cover'])&&($_FILES['cover']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)?save_uploaded_image('cover'):$L['cover_path'];
  q('UPDATE studio_levels SET name=?,short_name=?,cover_path=? WHERE level_id=?',[$name,$short,$cover,$level]);q('UPDATE bulig_levels SET title=? WHERE id=?',[$name,$level]);q('UPDATE modules SET title=? WHERE level_id=?',[$name,$level]);
  flash('Level saved.');go('?page=studio_lessons&level='.$level);}
 if($action==='studio_level_delete'){if($L['published_at'])fail('This level was published before. Keep it hidden instead of deleting it.',403);
  if(val('SELECT COUNT(*) FROM pupil_level_assignments WHERE level_id=? AND pupil_id NOT IN (SELECT pupil_id FROM studio_testers)',[$level]))fail('Pupils are assigned to this level, so it cannot be deleted.',403);
  db()->beginTransaction();try{foreach(rows('SELECT l.id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=?',[$level]) as $l){foreach(studio_acts((int)$l['id']) as $a)studio_act_wipe((int)$a['id']);q('DELETE FROM studio_revisions WHERE lesson_id=?',[$l['id']]);q('DELETE FROM studio_lessons WHERE lesson_id=?',[$l['id']]);studio_clear_refs('lessons',[(int)$l['id']]);q('DELETE FROM lessons WHERE id=?',[$l['id']]);}
   q('UPDATE pupil_level_assignments SET level_id=? WHERE level_id=?',[LEVEL_ORDER[0],$level]);studio_clear_refs('modules',array_column(rows('SELECT id FROM modules WHERE level_id=?',[$level]),'id'));q('DELETE FROM modules WHERE level_id=?',[$level]);studio_clear_refs('bulig_levels',[$level]);q('DELETE FROM bulig_levels WHERE id=?',[$level]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  audit('studio_level_delete',(string)$level);flash('Draft level deleted.');go('?page=studio_lessons');}
 if($action==='studio_level_send'){if(empty($_POST['checked']))fail('Tick the box to confirm you tested the level.');
  $ls=array_column(rows("SELECT s.lesson_id FROM studio_lessons s JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND s.status='draft'",[$level]),'lesson_id');
  if(!$ls||!val('SELECT COUNT(*) FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=?',[$level]))fail('Add lessons with activities first.');
  foreach($ls as $lid){if(!val('SELECT COUNT(*) FROM activities WHERE lesson_id=?',[$lid]))fail('Every lesson needs at least one activity: “'.val('SELECT title FROM lessons WHERE id=?',[$lid]).'” has none. Add one, or delete the lesson.');}
  db()->beginTransaction();try{foreach($ls as $lid){q("UPDATE studio_lessons SET status='review',sent_at=NOW(),note=NULL WHERE lesson_id=?",[$lid]);studio_snapshot((int)$lid,'Sent for review with the level');}
   q("UPDATE studio_levels SET status='review',sent_at=NOW(),note=NULL WHERE level_id=?",[$level]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  foreach(rows("SELECT DISTINCT u.id FROM users u JOIN user_roles r ON r.user_id=u.id AND r.role='central_admin' WHERE u.active=1") as $x)notify((int)$x['id'],'note','A new level is waiting for your review','“'.level_label($level).' · '.$L['name'].'” from '.name_case((string)$u['name']).'.','?page=studio_review&level='.$level);
  audit('studio_level_send',(string)$level);flash('The level and its lessons are sent to the Division Admin.');go('?page=studio_lessons&level='.$level);}
 if($action==='studio_import_page')studio_import_page($level);
 if($action==='studio_import_build')studio_import_build($level,$u);
 fail('Unknown action.');
}
function studio_level_admin_action(string $action,array $u):never{
 $level=(int)($_POST['level']??0);$L=studio_level($level);if(!$L)fail('Level not found.',404);$back='?page=studio_review';
 if($action==='studio_level_approve'){if($L['status']!=='review')fail('This level is not waiting for review.');
  db()->beginTransaction();try{foreach(rows("SELECT s.lesson_id FROM studio_lessons s JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND s.status='review'",[$level]) as $r){$lid=(int)$r['lesson_id'];
    q('UPDATE activities SET published=1 WHERE lesson_id=?',[$lid]);q('UPDATE lessons SET published=1 WHERE id=?',[$lid]);q("UPDATE studio_lessons SET status='published',note=NULL,reviewed_by=?,published_at=NOW() WHERE lesson_id=?",[$u['id'],$lid]);studio_snapshot($lid,'Published with the level');}
   q('UPDATE bulig_levels SET published=1 WHERE id=?',[$level]);q("UPDATE studio_levels SET status='published',note=NULL,reviewed_by=?,published_at=NOW() WHERE level_id=?",[$u['id'],$level]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  if($L['created_by'])notify((int)$L['created_by'],'note','Your new level is published','“'.level_label($level).' · '.$L['name'].'” is approved. Pupils see it after Level 7.','?page=studio_lessons&level='.$level);
  audit('studio_level_approve',(string)$level);flash('Published. Pupils see '.level_label($level).' on their road map after Level 7.');go($back);}
 if($action==='studio_level_sendback'){if($L['status']!=='review')fail('This level is not waiting for review.');$note=trim(mb_substr((string)($_POST['note']??''),0,1000));if(mb_strlen($note)<3)fail('Write a short note: what should be changed?');
  q("UPDATE studio_lessons s JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id SET s.status='draft' WHERE m.level_id=? AND s.status='review'",[$level]);
  q("UPDATE studio_levels SET status='draft',note=?,reviewed_by=? WHERE level_id=?",[$note,$u['id'],$level]);
  if($L['created_by'])notify((int)$L['created_by'],'note','Your new level needs changes','“'.$L['name'].'”: '.$note,'?page=studio_lessons&level='.$level);
  audit('studio_level_sendback',(string)$level);flash('Sent back with your note.');go($back);}
 if($action==='studio_level_hide'){if($L['status']!=='published')fail('This level is not published.');
  if(val('SELECT COUNT(*) FROM pupil_level_assignments WHERE level_id=? AND pupil_id NOT IN (SELECT pupil_id FROM studio_testers)',[$level]))fail('Some pupils start at this level, so it cannot be hidden. Move them to another starting level first.',403);
  q('UPDATE bulig_levels SET published=0 WHERE id=?',[$level]);q("UPDATE studio_levels SET status='draft',note=? WHERE level_id=?",['Hidden from pupils by the Division Admin. Pupils’ saved work is kept.',$level]);
  if($L['created_by'])notify((int)$L['created_by'],'note','A level was hidden','“'.$L['name'].'” is hidden from pupils and back in your drafts.','?page=studio_lessons&level='.$level);
  audit('studio_level_hide',(string)$level);flash('Hidden from pupils. It is a draft again.');go($back);}
 fail('Unknown action.');
}
/** The Division Admin's page for one new level. */
function studio_review_level(int $level):void{
 $L=studio_level($level);if(!$L)fail('Level not found.',404);$by=$L['created_by']?one('SELECT name,public_id FROM users WHERE id=?',[$L['created_by']]):null;
 echo hm_head('CONTENT REVIEW · NEW LEVEL',level_label($level).' · '.$L['name'],'From '.e($by?name_case((string)$by['name']).' · '.$by['public_id']:'—').' · '.studio_status_tag($L),'<a class="btn secondary" href="?page=studio_review">'.icon('arrow','flip').'All lessons</a>');
 $ls=rows('SELECT l.id,l.position,l.title,s.status,(SELECT COUNT(*) FROM activities a WHERE a.lesson_id=l.id) acts FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN studio_lessons s ON s.lesson_id=l.id WHERE m.level_id=? ORDER BY l.position',[$level]);
 echo '<div class="st-two st-two-lesson"><section class="card"><div class="st-lc-top"><img src="'.e(level_cover($level)).'" alt=""><div><h2>'.e($L['name']).'</h2><p class="muted">Short name on the road map: '.e($L['short_name']).'</p></div></div><h2>'.icon('plan').'Lessons</h2>';
 foreach($ls as $r)echo '<a class="st-row" href="?page=studio_review&amp;id='.(int)$r['id'].'"><span class="st-num">'.(int)$r['position'].'</span><span class="st-rt"><b>'.e($r['title']).'</b></span><span class="st-n">'.(int)$r['acts'].' activities</span>'.studio_status_tag($r['status']?['status'=>$r['status']]:null).'</a>';
 if(!$ls)echo '<p class="muted">No lessons.</p>';echo '</section><aside>';
 if($L['status']==='review')echo '<section class="card st-side"><h2>'.icon('check').'Approve this level?</h2><p class="muted">Approving publishes the level and its lessons. Pupils see it after Level 7 on the road map. It never re-locks anything a pupil already finished.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_level_approve"><input type="hidden" name="level" value="'.$level.'"><button class="btn primary" data-confirm="Publish this level and its lessons to pupils?">'.icon('check').'Approve and publish</button></form>'
  .'<form method="post" class="st-form">'.csrf_field().'<input type="hidden" name="action" value="studio_level_sendback"><input type="hidden" name="level" value="'.$level.'"><label>Or send it back with a note<textarea name="note" rows="3" maxlength="1000" required placeholder="What should be changed?"></textarea></label><button class="btn secondary">'.icon('arrow','flip').'Send back</button></form></section>';
 if($L['status']==='published')echo '<section class="card st-side"><h2>'.icon('eye').'Published</h2><p class="muted">Hide it if it needs changes. Pupils’ saved work is kept.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="studio_level_hide"><input type="hidden" name="level" value="'.$level.'"><button class="btn secondary" data-confirm="Hide this level from pupils?">'.icon('lock').'Hide from pupils</button></form></section>';
 if($L['status']==='draft')echo '<section class="card st-side"><p class="muted">The Content Developer is still working on this level.</p></section>';
 echo '</aside></div>';
}

/* ---------- v216 upload the module: the browser reads the PDF (assets/studio.js with pdf.js) and sends each page:
   its text and a picture. Then BULIG makes draft lessons and activities from the pages. ---------- */
function studio_json(array $a):never{header('Content-Type: application/json');echo json_encode($a);exit;}
function studio_import_page(int $level):never{
 $n=(int)($_POST['n']??0);$total=(int)($_POST['total']??0);if($n<1||$n>400||$total<$n||$total>400)fail('The module can have up to 400 pages.');
 if($n===1){foreach(rows('SELECT image_path FROM studio_pages WHERE level_id=?',[$level]) as $r)studio_unlink_upload((string)$r['image_path']);q('DELETE FROM studio_pages WHERE level_id=?',[$level]);}
 $img=save_uploaded_image('page');$text=mb_substr(str_replace("\r",'',(string)($_POST['text']??'')),0,60000);
 q('INSERT INTO studio_pages(level_id,page_no,text_content,image_path) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE text_content=VALUES(text_content),image_path=VALUES(image_path)',[$level,$n,$text,$img]);
 studio_json(['ok'=>true,'n'=>$n]);
}
function studio_unlink_upload(string $p):void{if($p!==''&&preg_match('~^assets/uploads/[a-f0-9]{32}\.(jpg|png|webp)$~',$p)&&!val('SELECT 1 FROM activities WHERE image_path=? OR image_paths LIKE ?',[$p,'%'.$p.'%']))@unlink(__DIR__.'/../public/'.$p);}
/** The lines of a page, without empty ones. */
function studio_lines(string $t):array{return array_values(array_filter(array_map(fn($x)=>trim(preg_replace('/\s+/u',' ',$x)),explode("\n",$t)),fn($x)=>$x!==''));}
/** "Lesson 3: Using Rhyming Words", "LESSON PLAN 3", "Aralin 3" → [3, "Using Rhyming Words"]. */
function studio_lesson_heading(string $line):?array{
 if(!preg_match('/^(?:LESSON|Lesson|ARALIN|Aralin)\s*(?:PLAN\s*|Plan\s*)?(\d{1,2})\b\s*[:.\-–—]?\s*(.*)$/u',$line,$m))return null;
 $t=trim($m[2]);if(preg_match('/^[.\s\d]+$/u',$t)||preg_match('/\.{4,}/',$t))$t='';   // a table of contents line ("Lesson 3 ........ 12")
 return [(int)$m[1],$t];
}
/** Which template fits a numbered item from the module (the words stay the module's own). */
function studio_guess_template(string $item):string{
 if(preg_match('/[“"]([^”"]{3,200})[”"]/u',$item)&&preg_match('/\b(read|basahin)\b/iu',$item))return 'read';
 if(preg_match('/\b(draw|iguhit|colou?r|kulayan)\b/iu',$item))return 'draw';
 if(preg_match('/^(clap|jump|stand|sit|walk|point|touch|act|show|stomp|wave|turn|raise|hop|march|tap)\b/iu',$item))return 'do';
 if(preg_match('/\b(partner|pairs?|group|classmates?|kapareha)\b/iu',$item))return 'pair';
 return 'speak';
}
function studio_import_build(int $level,array $u):never{
 $pages=rows('SELECT * FROM studio_pages WHERE level_id=? ORDER BY page_no',[$level]);if(!$pages)fail('Upload the module first.');
 $mod=studio_level_module($level);if(!$mod)fail('This level has no module.');
 if(!empty($_POST['replace']))foreach(rows("SELECT l.id FROM lessons l JOIN studio_lessons s ON s.lesson_id=l.id WHERE l.module_id=? AND s.status='draft' AND s.published_at IS NULL AND l.source_pages<>'[]'",[$mod]) as $l){
  foreach(studio_acts((int)$l['id']) as $a)studio_act_wipe((int)$a['id']);q('DELETE FROM studio_revisions WHERE lesson_id=?',[$l['id']]);q('DELETE FROM studio_lessons WHERE lesson_id=?',[$l['id']]);studio_clear_refs('lessons',[(int)$l['id']]);q('DELETE FROM lessons WHERE id=?',[$l['id']]);}
 /* 1. find where each lesson starts (a page with "Lesson N"); a contents page (3 or more lesson lines) is skipped */
 $starts=[];$cur=null;$seen=[];
 foreach($pages as $i=>$p){$hs=[];foreach(studio_lines((string)$p['text_content']) as $k=>$line)if($h=studio_lesson_heading($line)){$h[]=$k;$hs[$h[0]]??=$h;}
  if(count($hs)>=3)continue;
  foreach($hs as $num=>$h){if($cur!==$num){$cur=$num;if(isset($seen[$num])){$starts[]=['i'=>$i,'num'=>$num,'skip'=>true,'title'=>''];break;}$seen[$num]=1;$t=$h[1];if($t===''){$ls=studio_lines((string)$p['text_content']);for($j=$h[2]+1;$j<min(count($ls),$h[2]+4);$j++)if(mb_strlen($ls[$j])>=4&&!preg_match('/^\d+$/',$ls[$j])){$t=$ls[$j];break;}}
   $starts[]=['i'=>$i,'num'=>$num,'title'=>mb_substr($t?:'Lesson '.$num,0,190)];}break;}}
 if(!$starts){for($i=0;$i<count($pages);$i+=4)$starts[]=['i'=>$i,'num'=>intdiv($i,4)+1,'title'=>'Part '.(intdiv($i,4)+1).' · module pages '.$pages[$i]['page_no'].'–'.$pages[min(count($pages)-1,$i+3)]['page_no']];}
 /* 2. each lesson: a "Look and listen" for each module page (its picture), then an activity for each numbered item */
 $nl=0;$na=0;
 db()->beginTransaction();try{
  foreach($starts as $si=>$st){$from=$st['i'];$to=isset($starts[$si+1])?$starts[$si+1]['i']-1:count($pages)-1;$range=array_slice($pages,$from,$to-$from+1);if(!$range||!empty($st['skip']))continue;
   $pos=(int)val('SELECT COALESCE(MAX(position),0)+1 FROM lessons WHERE module_id=?',[$mod]);$title=$st['title'];
   q('INSERT INTO lessons(module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) VALUES(?,?,?,?,?,?,?,?,0)',[$mod,$pos,$title,mb_substr($title,0,60),'','',json_encode(array_map(fn($p)=>(int)$p['page_no'],$range)),$range[0]['image_path']]);$lid=(int)db()->lastInsertId();
   q("INSERT INTO studio_lessons(lesson_id,status,created_by) VALUES(?,'draft',?)",[$lid,$u['id']]);$nl++;
   foreach($range as $p){$ls=studio_lines((string)$p['text_content']);$head='';foreach($ls as $x)if(mb_strlen($x)>=6&&!preg_match('/^\d+$/',$x)){$head=$x;break;}
    $prompt=mb_substr($head?:'Look at this page of the module.',0,300);
    studio_act_insert($lid,['title'=>'Module page '.(int)$p['page_no'],'type'=>'reference','response_mode'=>'none','instructions'=>STUDIO_TEMPLATES['look'][4],'prompt'=>$prompt,'narration'=>$prompt,'expected_text'=>null,'xp_reward'=>0,'image_paths'=>json_encode($p['image_path']?[$p['image_path']]:[],JSON_UNESCAPED_SLASHES),'image_path'=>$p['image_path']]);$na++;}
   $items=0;foreach($range as $p)foreach(studio_lines((string)$p['text_content']) as $x){if($items>=12)break 2;
    if(!preg_match('/^\s*(\d{1,2})[.)]\s+(.{8,})$/u',$x,$m)||preg_match('/\.{4,}|\s\d{1,3}$/u',$m[2]))continue;
    $k=studio_guess_template($m[2]);$t=STUDIO_TEMPLATES[$k];$expected=null;if($k==='read'&&preg_match('/[“"]([^”"]{3,200})[”"]/u',$m[2],$q))$expected=$q[1];
    studio_act_insert($lid,['title'=>$t[0],'type'=>$t[2],'response_mode'=>$t[3],'instructions'=>$t[4],'prompt'=>mb_substr($k==='read'&&$expected?$expected:$m[2],0,1000),'narration'=>mb_substr($m[2],0,1000),'expected_text'=>$expected,'xp_reward'=>10,'image_paths'=>'[]','image_path'=>null]);$items++;$na++;}
  }
  $L=studio_level($level);if($L&&!$L['cover_path']&&$pages[0]['image_path'])q('UPDATE studio_levels SET cover_path=? WHERE level_id=?',[$pages[0]['image_path'],$level]);
  db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 audit('studio_import',$level.' · '.count($pages).' pages · '.$nl.' lessons');flash('Made '.$nl.' draft '.($nl===1?'lesson':'lessons').' with '.$na.' activities from '.count($pages).' module pages. Check each one, then test the level as a pupil.');
 studio_json(['ok'=>true,'lessons'=>$nl,'activities'=>$na,'msg'=>'Made '.$nl.' lessons and '.$na.' activities.','url'=>'?page=studio_lessons&level='.$level]);
}
