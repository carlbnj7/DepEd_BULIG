<?php
/* "Done in class": a teacher marks activities, lessons or a whole level as finished
   for pupils who did the work together in class (for example during Class Demo). */
const CLASS_DONE_MARK='[Done in class]';

/** Published activity ids covered by a scope, limited to the pupil's grade module. */
function class_done_activities(int $pid,string $scope,int $id):array{
 $g=pupil_grade($pid);
 if($scope==='activity')$sql='a.id=?';elseif($scope==='lesson')$sql='l.id=?';elseif($scope==='level')$sql='m.level_id=?';else return [];
 return array_map('intval',array_column(rows('SELECT a.id FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE '.$sql.' AND a.published=1 AND l.published=1'.GRADE_SQL,[$id,$g]),'id'));
}

/** Marks the activities as done for one pupil. Work the pupil already finished is kept. Returns how many were newly marked. */
function class_done_apply(int $tid,int $pid,array $aids):int{
 if(!$aids)return 0;$n=0;$lessons=[];
 foreach(array_unique($aids) as $aid){
  $a=one('SELECT id,lesson_id,revision,xp_reward FROM activities WHERE id=? AND published=1',[$aid]);if(!$a)continue;$lessons[(int)$a['lesson_id']]=true;
  $row=one('SELECT id,status FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);
  if($row&&completion_ok($row))continue;
  if($row)q("UPDATE activity_completion SET status='approved',reviewed_by=?,reviewed_at=NOW(),feedback=COALESCE(NULLIF(feedback,''),'Done in class with your teacher.') WHERE id=?",[$tid,$row['id']]);
  else q("INSERT INTO activity_completion(pupil_id,activity_id,response,prompt_snapshot,activity_revision,status,feedback,reviewed_by,submitted_at,reviewed_at) VALUES(?,?,?,?,?,'completed',?,?,NOW(),NOW())",[$pid,$aid,'Done in class with your teacher.',CLASS_DONE_MARK,(int)$a['revision'],'Done in class with your teacher.',$tid]);
  if(q('INSERT IGNORE INTO xp_transactions(pupil_id,activity_id,amount) VALUES(?,?,?)',[$pid,$aid,(int)$a['xp_reward']])->rowCount())q('INSERT INTO pupil_xp(pupil_id,total) VALUES(?,?) ON DUPLICATE KEY UPDATE total=total+VALUES(total)',[$pid,(int)$a['xp_reward']]);
  $n++;
 }
 foreach(array_keys($lessons) as $lid){
  if(!val("SELECT COUNT(*) FROM activities a LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.lesson_id=? AND a.published=1 AND (c.status IS NULL OR c.status NOT IN ('approved','completed'))",[$pid,$lid]))
   q('INSERT INTO pupil_progress(pupil_id,lesson_id,completed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE completed_at=COALESCE(completed_at,NOW())',[$pid,$lid]);
  sync_assessments($pid,$lid);
 }
 award_badges($pid);return $n;
}

/** Removes "Done in class" marks from one lesson (answers the pupil gave themselves stay). */
function class_done_undo(int $pid,int $lid):int{
 $marks=rows('SELECT c.id,c.activity_id FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id=? AND a.lesson_id=? AND c.prompt_snapshot=?',[$pid,$lid,CLASS_DONE_MARK]);
 foreach($marks as $m){
  $xp=(int)val('SELECT amount FROM xp_transactions WHERE pupil_id=? AND activity_id=?',[$pid,$m['activity_id']]);
  q('DELETE FROM xp_transactions WHERE pupil_id=? AND activity_id=?',[$pid,$m['activity_id']]);
  if($xp)q('UPDATE pupil_xp SET total=GREATEST(0,CAST(total AS SIGNED)-?) WHERE pupil_id=?',[$xp,$pid]);
  q('DELETE FROM response_history WHERE completion_id=?',[$m['id']]);q('DELETE FROM activity_completion WHERE id=?',[$m['id']]);
 }
 if($marks)q('UPDATE pupil_progress SET completed_at=NULL WHERE pupil_id=? AND lesson_id=?',[$pid,$lid]);
 return count($marks);
}

/** A one-time note for the pupil after their teacher marks work as done in class. */
function class_done_note_set(int $pid,string $text):void{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['class_done_note_'.$pid,$text]);}
function class_done_note(int $pid):string{
 if(($_GET['off']??'')==='1')return '';
 try{$t=val('SELECT setting_value FROM settings WHERE setting_key=?',['class_done_note_'.$pid]);}catch(Throwable $e){return '';}
 if(!$t)return '';q('DELETE FROM settings WHERE setting_key=?',['class_done_note_'.$pid]);
 return '<div class="class-done-note" role="status"><span class="cdn-icon">'.icon('check').'</span><div><strong>Great job in class!</strong><span>'.e((string)$t).'</span></div></div>';
}

/** The teacher's pupils, grouped by grade and section, for the Class Demo "Mark as done" panel. */
function class_done_pupils(int $tid,int $level,int $grade):array{
 $args=[$level,$tid];$where='';if($grade){$where=' AND p.grade_level=?';$args[]=$grade;}
 $list=rows('SELECT u.id,u.name,u.avatar_path,p.grade_level,p.section,(SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id JOIN pupil_progress pp ON pp.lesson_id=l.id AND pp.pupil_id=u.id AND pp.completed_at IS NOT NULL WHERE m.level_id=? AND l.published=1 AND (m.grade_level IS NULL OR m.grade_level=p.grade_level)) done FROM teacher_pupils tp JOIN users u ON u.id=tp.pupil_id JOIN pupils p ON p.user_id=u.id WHERE tp.teacher_id=?'.$where.' ORDER BY p.grade_level,p.section,u.name',$args);
 $groups=[];foreach($list as $p)$groups['Grade '.$p['grade_level'].' · '.$p['section']][]=$p;return $groups;
}

/** Class Demo: gold "Mark as done" button and its panel. */
function class_done_demo_panel(array $u,int $level,int $grade,string $label):string{
 $groups=class_done_pupils((int)$u['id'],$level,$grade);
 $total=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1'.($grade?' AND (m.grade_level IS NULL OR m.grade_level=?)':' AND m.grade_level IS NULL'),$grade?[$level,$grade]:[$level]);
 $back='?page=class_demo&level='.$level.($grade?'&grade='.$grade:'');
 $h='<div class="cd-ov" id="cd-panel" hidden><form method="post" class="cd-modal" role="dialog" aria-modal="true" aria-labelledby="cd-title">'.csrf_field().'<input type="hidden" name="action" value="class_done"><input type="hidden" name="level" value="'.$level.'"><input type="hidden" name="back" value="'.e($back).'" data-base="'.e($back).'"><input type="hidden" name="aid" value="0" data-cd-aid><input type="hidden" name="lid" value="0" data-cd-lid>'
  .'<button type="button" class="iconbutton cd-x" data-cd-close aria-label="Close">'.icon('close').'</button><h2 id="cd-title">Mark as done in class</h2><p class="muted">'.e($label).'</p>'
  .'<fieldset class="cd-scope"><legend>What did you finish?</legend>'
  .'<label class="cd-choice"><input type="radio" name="scope" value="activity"><span><b>Only this activity</b><small data-cd-act>The slide on the screen now</small></span></label>'
  .'<label class="cd-choice"><input type="radio" name="scope" value="lesson" checked><span><b>This whole lesson</b><small data-cd-les>The lesson on the screen now</small></span></label>'
  .'<label class="cd-choice"><input type="radio" name="scope" value="level"><span><b>The whole level</b><small>'.e($label).': all '.$total.' lessons. The next level opens for these pupils.</small></span></label></fieldset>'
  .'<fieldset class="cd-who"><legend>Who was in class?</legend>';
 if(!$groups)$h.='<p class="muted">You have no pupils'.($grade?' in Grade '.$grade:'').' yet.</p>';
 $one=count($groups)===1;
 foreach($groups as $name=>$list){$h.='<div class="cd-group" data-cd-group><div class="cd-gh"><strong>'.e($name).'</strong><button type="button" class="linkbutton" data-cd-all>'.($one?'Clear all':'Select all').'</button></div>';
  foreach($list as $p)$h.='<label class="cd-pupil"><input type="checkbox" name="pupils[]" value="'.(int)$p['id'].'"'.($one?' checked':'').'>'.avatar($p).'<span>'.e($p['name']).'</span><small>'.(int)$p['done'].' of '.$total.' lessons done</small></label>';
  $h.='</div>';}
 return $h.'</fieldset><p class="cd-note">'.icon('heart').'Answers a pupil already gave on their own are kept. Only unfinished parts are marked "Done in class". You can undo it on the pupil’s progress page.</p><div class="cd-foot"><button type="button" class="btn secondary" data-cd-close>Cancel</button><button class="btn primary" data-cd-submit>'.icon('check').'<span>Mark as done</span></button></div></form></div>';
}

/** Learning path with "Done in class" marking and undo. With $acts, each lesson also lists its activities. */
function class_done_path(int $pid,string $back='',bool $acts=false):string{
 $g=pupil_grade($pid);$start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);if($back==='')$back='?page=pupil&id='.$pid.'#learning-path';
 $levels=rows('SELECT b.id,b.title FROM bulig_levels b WHERE b.published=1 AND EXISTS(SELECT 1 FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=b.id AND l.published=1'.GRADE_SQL.') ORDER BY b.id',[$g]);
 $h='<section class="card cd-path" id="learning-path"><h2>Learning path</h2><p class="muted">Tick the '.($acts?'lessons or activities':'lessons').' you did together in class, then choose <strong>Mark as done in class</strong>. The pupil’s next lesson or level opens right away.</p>';
 $openDone=false;
 foreach($levels as $lv){$lvid=(int)$lv['id'];
  $ls=rows('SELECT l.id,l.position,l.subtitle,p.completed_at,(SELECT COUNT(*) FROM activities a WHERE a.lesson_id=l.id AND a.published=1) total,(SELECT COUNT(*) FROM activities a JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.lesson_id=l.id AND a.published=1 AND c.status IN (\'approved\',\'completed\')) ok,(SELECT COUNT(*) FROM activities a JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE a.lesson_id=l.id AND c.prompt_snapshot=?) marked FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1'.GRADE_SQL.' ORDER BY l.position,l.id',[$pid,$pid,CLASS_DONE_MARK,$pid,$lvid,$g]);
  $done=count(array_filter($ls,fn($l)=>(bool)$l['completed_at']));$all=count($ls);$avail=level_available($pid,$lvid);$skipped=$start&&$lvid<$start;
  $open=!$openDone&&$avail&&!$skipped&&$done<$all;if($open)$openDone=true;
  $state=$done===$all?'Complete':($skipped?'Skipped · optional':($avail?'Open':'Locked'));
  $acRows=[];if($acts&&$ls){foreach(rows("SELECT a.id,a.lesson_id,a.phase,a.title,c.status,c.prompt_snapshot FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id LEFT JOIN activity_completion c ON c.activity_id=a.id AND c.pupil_id=? WHERE m.level_id=? AND a.published=1 AND l.published=1".GRADE_SQL." ORDER BY FIELD(a.phase,'pre','learn','post'),a.position,a.id",[$pid,$lvid,$g]) as $a)$acRows[(int)$a['lesson_id']][]=$a;}
  $h.='<details class="cd-level'.($done===$all?' is-done':'').'"'.($open?' open':'').'><summary><span class="cd-num">'.e(str_replace('Level ','',level_label($lvid))).'</span><span class="cd-lt"><b>'.e(level_label($lvid).' · '.preg_replace('~\s*·\s*Level \w+$~u','',(string)$lv['title'])).'</b><small>'.$done.' of '.$all.' lessons done · '.$state.'</small><span class="cd-bar"><i data-w="'.($all?round(100*$done/$all):0).'"></i></span></span>'.icon('arrow').'</summary>'
   .'<form method="post" class="cd-lessons">'.csrf_field().'<input type="hidden" name="action" value="class_done"><input type="hidden" name="pupil" value="'.$pid.'"><input type="hidden" name="level" value="'.$lvid.'"><input type="hidden" name="back" value="'.e($back).'">';
  foreach($ls as $l){$c=(bool)$l['completed_at'];$chip=$c?((int)$l['marked']?'<span class="cd-chip class">Done in class</span>':'<span class="cd-chip done">Done by pupil</span>'):((int)$l['ok']?'<span class="cd-chip cur">Working on it · '.(int)$l['ok'].' of '.(int)$l['total'].'</span>':'<span class="cd-chip none">Not started</span>');
   $undo=(int)$l['marked']?'<button class="linkbutton cd-undo" name="undo" value="'.(int)$l['id'].'">Undo</button>':'';
   $h.='<div class="cd-lesson"><label class="cd-row'.($c?' is-done':'').'"><input type="checkbox" name="lessons[]" value="'.(int)$l['id'].'"'.($c?' disabled checked':'').'><span>'.e(lesson_display_label($lvid,(int)$l['position']).' · '.$l['subtitle']).'</span>'.$chip.$undo.'</label>';
   if($acts&&!empty($acRows[(int)$l['id']])){$list=$acRows[(int)$l['id']];
    $h.='<details class="cd-acts"><summary>Activities · '.(int)$l['ok'].' of '.count($list).' done</summary>';
    foreach($list as $a){$ok=in_array($a['status'],['approved','completed'],true);$mk=$a['prompt_snapshot']===CLASS_DONE_MARK;
     $h.='<label class="cd-arow'.($ok?' is-done':'').'"><input type="checkbox" name="activities[]" value="'.(int)$a['id'].'"'.($ok?' disabled checked':'').'><span><small>'.e(['pre'=>'Before we begin','learn'=>'Let’s practice','post'=>'Show what you learned'][$a['phase']]).'</small>'.e($a['title']).'</span>'.($ok?($mk?'<span class="cd-chip class">In class</span>':'<span class="cd-chip done">Done</span>'):($a['status']==='submitted'?'<span class="cd-chip cur">Waiting for review</span>':($a['status']==='retry'?'<span class="cd-chip cur">Try again</span>':''))).'</label>';}
    $h.='</details>';}
   $h.='</div>';}
  $h.=$done<$all?'<div class="cd-actions"><button class="btn primary" name="scope" value="lessons">'.icon('check').'Mark chosen as done in class</button><button class="btn secondary" name="scope" value="level" data-confirm="Mark every lesson in '.e(level_label($lvid)).' as done in class for this pupil?">Mark whole level as done</button></div>':'';
  $h.='</form></details>';
 }
 return $h.'</section>';
}

/** Teacher page: manage pupils (starting level, done in class by level, lesson or activity). */
function manage_pupils_view(array $u):void{
 $tid=(int)$u['id'];$sel=(int)($_GET['id']??0);
 $pupils=rows('SELECT u.id,u.name,u.public_id,u.avatar_path,p.grade_level,p.section,a.level_id,(SELECT COUNT(*) FROM pupil_progress pp WHERE pp.pupil_id=u.id AND pp.completed_at IS NOT NULL) done FROM teacher_pupils tp JOIN users u ON u.id=tp.pupil_id JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE tp.teacher_id=? ORDER BY p.grade_level,p.section,u.name',[$tid]);
 if($sel&&!in_array($sel,array_map('intval',array_column($pupils,'id')),true))fail('This pupil is not assigned to you.',403);
 $levels=rows('SELECT id,title FROM bulig_levels WHERE published=1 ORDER BY id');
 $opts=fn(int $cur)=>implode('',array_map(fn($l)=>'<option value="'.(int)$l['id'].'"'.((int)$l['id']===$cur?' selected':'').'>'.e(level_label((int)$l['id']).' · '.preg_replace('~\s*·\s*Level \w+$~u','',(string)$l['title'])).'</option>',$levels));
 $groups=[];foreach($pupils as $p)$groups['Grade '.$p['grade_level'].' · '.$p['section']][]=$p;
 echo '<div class="pageheading"><div><span class="eyebrow">MANAGE PUPILS</span><h1>Manage pupils</h1><p class="muted">Change a pupil’s starting level, or mark a level, lesson or activity as done in class.</p></div></div>';
 echo '<div class="mp-grid'.($sel?' has-pick':'').'"><section class="card mp-list"><h2>Your pupils</h2>';
 if(!$pupils){echo '<p class="muted">You have no pupils yet. Add pupils in My pupils.</p></section></div>';return;}
 echo '<div class="mp-tools"><input type="search" placeholder="Search pupils" aria-label="Search pupils" data-mp-search><select aria-label="Show section" data-mp-section><option value="">All sections</option>';foreach(array_keys($groups) as $g)echo '<option>'.e($g).'</option>';echo '</select></div>';
 echo '<form method="post" class="mp-bulk">'.csrf_field().'<input type="hidden" name="action" value="set_start_level"><input type="hidden" name="back" value="?page=manage'.($sel?'&amp;id='.$sel:'').'">';
 foreach($groups as $g=>$list){echo '<div class="mp-group" data-mp-group="'.e($g).'"><div class="mp-gh"><strong>'.e($g).'</strong><button type="button" class="linkbutton" data-mp-all>Select all</button></div>';
  foreach($list as $p){$id=(int)$p['id'];echo '<div class="mp-item'.($id===$sel?' on':'').'" data-mp-name="'.e(strtolower($p['name'].' '.$p['public_id'])).'"><input type="checkbox" name="pupils[]" value="'.$id.'" aria-label="Choose '.e($p['name']).'"><a href="?page=manage&amp;id='.$id.'#mp-pupil"'.($id===$sel?' aria-current="true"':'').'>'.avatar($p).'<span><b>'.e($p['name']).'</b><small>Starts at '.e($p['level_id']?level_label((int)$p['level_id']):'not set').' · '.(int)$p['done'].' lessons done</small></span>'.icon('arrow').'</a></div>';}
  echo '</div>';}
 echo '<p class="mp-empty muted" data-mp-empty hidden>No pupils match your search.</p><div class="mp-bulkbar"><label>Set starting level for the ticked pupils<select name="level_id">'.$opts(1).'</select></label><button class="btn secondary" data-confirm="Change the starting level for the ticked pupils?">Apply to ticked</button></div></form></section>';
 echo '<div class="mp-detail" id="mp-pupil">';
 if(!$sel){echo '<section class="card mp-pick">'.icon('people').'<h2>Choose a pupil</h2><p class="muted">Pick a pupil from the list to change their starting level or mark work as done in class.</p></section></div></div>';return;}
 $p=one('SELECT u.*,p.grade_level,p.section,a.level_id FROM users u JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE u.id=?',[$sel]);$s=progress_stats($sel);
 echo '<section class="card mp-head">'.avatar($p).'<div><span class="eyebrow">'.e($p['public_id']).' · Grade '.(int)$p['grade_level'].' · '.e($p['section']).'</span><h2>'.e($p['name']).'</h2><p class="muted">'.(int)$s['xp'].' XP · '.(int)$s['completed'].' lessons complete · '.(int)$s['current_streak'].' day streak</p></div><a class="btn quiet" href="?page=pupil&amp;id='.$sel.'">See answers</a></section>';
 echo '<section class="card mp-start"><h2>Starting level</h2><p class="muted">Levels before the starting level become optional practice. The pupil can begin right away at the level you choose.</p><form method="post" class="mp-startform">'.csrf_field().'<input type="hidden" name="action" value="set_start_level"><input type="hidden" name="pupil" value="'.$sel.'"><input type="hidden" name="back" value="?page=manage&amp;id='.$sel.'"><label>Starting level<select name="level_id">'.$opts((int)$p['level_id']).'</select></label><button class="btn primary">'.icon('check').'Save starting level</button></form></section>';
 echo class_done_path($sel,'?page=manage&id='.$sel.'#learning-path',true).'</div></div>';
}
