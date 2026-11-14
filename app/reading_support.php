<?php
declare(strict_types=1);
/* v204 Reading support: struggling readers, My practice words, the class reading challenge, offline sync status and
   the administrator's data cleanup. Everything is kept in the settings table, so no new SQL is needed. */

function rs_get(string $key,string $default=''):string{try{$v=val('SELECT setting_value FROM settings WHERE setting_key=?',[$key]);}catch(Throwable $e){return $default;}return $v===null||$v===false?$default:(string)$v;}
function rs_put(string $key,string $value):void{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);}
function rs_has_rubric():bool{static $ok=null;if($ok===null){try{db()->query('SELECT rubric_scores FROM activity_completion LIMIT 0');$ok=true;}catch(Throwable $e){$ok=false;}}return $ok;}

/** Word Coach readings of some pupils since a date: [pupil_id => [['p'=>%,'w'=>[words],'at'=>date,'aid'=>id,'n'=>card]]]. */
function rs_readings(array $pids,string $since):array{
 $pids=array_values(array_filter(array_map('intval',$pids)));if(!$pids)return [];
 $r=rs_has_rubric()?'c.rubric_scores':'NULL rubric_scores';
 $sql='SELECT c.pupil_id,c.activity_id,c.response,'.$r.',c.submitted_at FROM activity_completion c WHERE c.pupil_id IN('.implode(',',$pids).') AND c.submitted_at>=? AND (c.response LIKE ?'.(rs_has_rubric()?' OR c.rubric_scores LIKE ?':'').') ORDER BY c.submitted_at';
 $out=[];foreach(rows($sql,rs_has_rubric()?[$since,'%Coach: %','%"wc"%']:[$since,'%Coach: %']) as $c)
  foreach(wc_result($c) as $n=>$x)$out[(int)$c['pupil_id']][]=['p'=>$x['p'],'w'=>$x['w'],'at'=>(string)$c['submitted_at'],'aid'=>(int)$c['activity_id'],'n'=>(int)$n];
 return $out;
}

/** Struggling readers: 3 or more scored readings in 30 days with an average under 60%. Newest first, lowest first. */
const RS_MIN_READS=3;const RS_LOW=60;
function rs_struggling(int $tid):array{
 $ps=rows('SELECT u.id,u.name,u.avatar_path,p.grade_level,p.section,a.level_id FROM users u JOIN pupils p ON p.user_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1',[$tid]);
 if(!$ps)return [];$by=array_column($ps,null,'id');$reads=rs_readings(array_keys($by),date('Y-m-d H:i:s',strtotime('-30 days')));$out=[];
 foreach($reads as $pid=>$list){$sc=array_values(array_filter($list,fn($x)=>$x['p']!==null));if(count($sc)<RS_MIN_READS)continue;
  $last=array_slice($sc,-5);$avg=(int)round(array_sum(array_column($last,'p'))/count($last));if($avg>=RS_LOW)continue;
  $words=[];foreach($list as $x)foreach($x['w'] as $w){$k=mb_strtolower($w);$words[$k]=($words[$k]??0)+1;}arsort($words);
  $out[]=$by[$pid]+['avg'=>$avg,'reads'=>count($sc),'words'=>array_slice(array_keys($words),0,5)];}
 usort($out,fn($a,$b)=>$a['avg']<=>$b['avg']);return $out;
}
/** Pupils on the same level who all need help: read with them as a small group. */
function rs_groups(array $struggling):array{
 $g=[];foreach($struggling as $p)if($p['level_id'])$g[(int)$p['level_id']][]=$p;
 return array_filter($g,fn($x)=>count($x)>=3);
}
function rs_teacher_card(int $tid):string{
 $s=rs_struggling($tid);$groups=rs_groups($s);$inGroup=[];foreach($groups as $l)foreach($l as $p)$inGroup[(int)$p['id']]=1;
 $h='<section class="card rs-card" id="rs-readers"><div class="ws-hd"><h2>'.icon('mic').'Readers who need help</h2><span class="rs-sub">Word Coach scores in the last 30 days</span></div>';
 if(!$s)return $h.'<p class="rs-ok">'.icon('check').'No one is struggling. BULIG Quest lists a pupil here when their last '.RS_MIN_READS.' or more Word Coach readings average under '.RS_LOW.'%.</p></section>';
 foreach($groups as $lv=>$list)$h.='<div class="rs-group">'.icon('people').'<div><b>Small group idea · '.e(level_label((int)$lv)).'</b><small>'.e(implode(', ',array_map(fn($p)=>tf_first($p),$list))).' find the same reading hard. Read the story with them together, then let each one read a part.</small></div></div>';
 $h.='<div class="rs-list">';
 foreach(array_slice($s,0,8) as $p)$h.='<a class="rs-row" href="?page=pupil&amp;id='.(int)$p['id'].'">'.avatar($p).'<span class="rs-tx"><b>'.e($p['name']).speech_mark((int)$p['id']).'</b><small>'.e(($p['level_id']?level_label((int)$p['level_id']):'No level yet').' · '.$p['reads'].' readings').'</small>'
  .($p['words']?'<span class="rs-words">'.implode('',array_map(fn($w)=>'<i>'.e($w).'</i>',$p['words'])).'</span>':'').'<em>'.(isset($inGroup[(int)$p['id']])?'Small group':'Reread together').': '.(isset($inGroup[(int)$p['id']])?'join the group above.':'sit with them and read the story again, slowly.').'</em></span><strong class="rs-pct">'.$p['avg'].'%</strong></a>';
 return $h.'</div>'.(count($s)>8?'<p class="muted">'.(count($s)-8).' more pupils. Open My pupils to see everyone.</p>':'').'</section>';
}

/* ---------------- My practice words (pupils) ---------------- */
function rs_practice_words(int $pid):array{
 $w=[];foreach(rs_readings([$pid],date('Y-m-d H:i:s',strtotime('-60 days')))[$pid]??[] as $x)foreach($x['w'] as $word){$k=mb_strtolower(trim($word,".,!?;:\"'“”‘’()"));if($k===''||mb_strlen($k)>24)continue;$w[$k]=($w[$k]??0)+1;}
 arsort($w);return array_slice($w,0,40,true);
}
function practice_view(array $u):void{
 $pid=(int)$u['id'];$w=rs_practice_words($pid);
 echo '<div class="pageheading"><div><span class="eyebrow">MY PRACTICE WORDS</span><h1>My practice words</h1><p class="muted">Words that were hard when you read with Word Coach. Tap a word to hear Max say it. Then say it too.</p></div></div>';
 if(!$w){echo friend_say('max','thumbs','You have no practice words yet. When a word is hard in Word Coach, I will keep it here for you.').'<a class="btn primary" href="?page=lessons">'.icon('book').'Go to my lessons</a>';return;}
 echo friend_say('max','mic','Tap the speaker to hear the word. Then tap Say it and say the word to me. You are doing great!');
 echo '<div class="rs-practice"><section class="card rs-pw"><div class="ws-hd"><h2>'.count($w).' '.(count($w)===1?'word':'words').' to practise</h2><span class="rs-sub" data-rs-done></span></div><ul class="rs-pwl">';
 foreach($w as $word=>$n)echo '<li data-word="'.e((string)$word).'">'.l1_speaker((string)$word,'Hear “'.$word.'”','rs-spk').'<b>'.e((string)$word).'</b><span class="rs-msg">'.($n>1?$n.' times':'').'</span><button type="button" class="rs-mic" aria-pressed="false" aria-label="Say “'.e((string)$word).'” to Max">'.icon('mic').'Say it</button></li>';
 echo '</ul><p class="muted rs-note">'.icon('info').'This is practice only. Nothing is recorded or sent to your teacher.</p></section></div>';
}
function rs_pupil_link(int $pid):string{
 $n=count(rs_practice_words($pid));if(!$n)return '';
 return '<a class="card rs-plink" href="?page=practice"><span class="rs-pic">'.icon('sound').'</span><span><b>My practice words</b><small>'.$n.' '.($n===1?'word':'words').' to say with Max</small></span>'.icon('chev').'</a>';
}

/* ---------------- Class reading challenge ---------------- */
function rs_goal(int $tid):int{$g=(int)rs_get('class_goal_'.$tid,'500');return max(50,min(20000,$g?:500));}
function rs_week_start():string{return date('Y-m-d 00:00:00',strtotime('monday this week'));}
/** Words read right this week by a teacher's pupils: each reading's words × its Word Coach score. */
function rs_class_words(int $tid):array{
 $pids=array_map('intval',array_column(rows('SELECT t.pupil_id FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 WHERE t.teacher_id=?',[$tid]),'pupil_id'));
 $total=0;$by=[];$texts=[];
 foreach(rs_readings($pids,rs_week_start()) as $pid=>$list)foreach($list as $x){
  if(!isset($texts[$x['aid']])){$a=one('SELECT * FROM activities WHERE id=?',[$x['aid']]);$texts[$x['aid']]=$a?rv_reading_texts($a):[];}
  $t=(string)($texts[$x['aid']][$x['n']]??'');$n=$t!==''?count(preg_split('~\s+~u',trim($t))):0;
  $right=(int)round($n*($x['p']===null?100:$x['p'])/100);$total+=$right;$by[$pid]=($by[$pid]??0)+$right;}
 return ['total'=>$total,'goal'=>rs_goal($tid),'readers'=>count($by)];
}
function rs_bar(int $total,int $goal):string{return '<progress class="rs-bar" max="'.$goal.'" value="'.min($total,$goal).'" aria-label="Words read this week">'.min($total,$goal).'</progress>';}
function rs_challenge_teacher(int $tid):string{
 $c=rs_class_words($tid);$done=$c['total']>=$c['goal'];
 return '<section class="card rs-card rs-ch" id="rs-challenge"><div class="ws-hd"><h2>'.icon('flag').'Class reading challenge</h2><span class="rs-sub">This week · Monday to Sunday</span></div>'
  .'<p class="rs-big"><b>'.number_format($c['total']).'</b> of '.number_format($c['goal']).' words read'.($done?' <span class="status status-ok">Goal reached</span>':'').'</p>'.rs_bar($c['total'],$c['goal'])
  .'<p class="muted">'.$c['readers'].' '.($c['readers']===1?'pupil has':'pupils have').' read with Word Coach this week. Pupils see this bar on their Home.</p>'
  .'<form method="post" class="rs-goal">'.csrf_field().'<input type="hidden" name="action" value="class_goal"><label>Weekly goal (words)<input type="number" name="goal" min="50" max="20000" step="50" value="'.$c['goal'].'" required></label><button class="btn secondary">Save goal</button></form></section>';
}
function rs_challenge_pupil(int $pid):string{
 $tid=(int)val('SELECT teacher_id FROM teacher_pupils WHERE pupil_id=?',[$pid]);if(!$tid)return '';
 $c=rs_class_words($tid);$done=$c['total']>=$c['goal'];
 return '<section class="card rs-ch rs-chp"><img class="rs-chc" src="'.e(function_exists('friend_img')?friend_img('toby',$done?'cheer':'flag'):'').'" alt="" width="70" height="90" loading="lazy"><div class="rs-chh"><span class="rs-pic">'.icon('book').'</span><div><b>Our class reading challenge</b><small>'.($done?'We did it! Our class reached the goal this week.':'Read with Word Coach to help our class reach the goal this week.').'</small></div></div>'
  .'<p class="rs-big"><b>'.number_format($c['total']).'</b> of '.number_format($c['goal']).' words</p>'.rs_bar($c['total'],$c['goal']).'</section>';
}

/* ---------------- Offline sync status ---------------- */
function rs_offline_report(array $u):never{
 header('Content-Type: application/json');header('Cache-Control: no-store');
 if($u['role']!=='pupil'){echo json_encode(['ok'=>false]);exit;}
 $n=max(0,min(999,(int)($_POST['n']??0)));$k='offline_rep_'.(int)$u['id'];
 if($n===0)q('DELETE FROM settings WHERE setting_key=?',[$k]);
 else{$old=preg_replace('~[^0-9T:.Z-]~','',(string)($_POST['oldest']??''));rs_put($k,json_encode(['n'=>$n,'oldest'=>mb_substr($old,0,30),'at'=>date('Y-m-d H:i:s'),'why'=>preg_replace('~[^a-z]~','',(string)($_POST['why']??''))]));}
 echo json_encode(['ok'=>true]);exit;
}
/** Pupils of a teacher with answers still on their device: [['id','name','n','at','why']]. */
function rs_offline_waiting(int $tid):array{
 $out=[];foreach(rows("SELECT u.id,u.name,u.avatar_path,s.setting_value v FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 JOIN settings s ON s.setting_key=CONCAT('offline_rep_',u.id) WHERE t.teacher_id=? ORDER BY u.name",[$tid]) as $r){
  $j=json_decode((string)$r['v'],true);if(!is_array($j)||(int)($j['n']??0)<1)continue;
  if(strtotime((string)($j['at']??''))<strtotime('-21 days'))continue;
  $out[]=['id'=>(int)$r['id'],'name'=>$r['name'],'avatar_path'=>$r['avatar_path'],'n'=>(int)$j['n'],'at'=>(string)$j['at'],'why'=>(string)($j['why']??'')];}
 return $out;
}
function rs_offline_card(int $tid):string{
 $w=rs_offline_waiting($tid);if(!$w)return '';$why=['signin'=>'needs to sign in again','order'=>'must finish an earlier activity','failed'=>'the upload stopped','wait'=>'waiting for internet'];
 $h='<section class="card rs-card" id="rs-offline"><div class="ws-hd"><h2>'.icon('download').'Work waiting to upload</h2><span class="rs-sub">Answers made offline, still on the pupil’s phone</span></div><div class="rs-list">';
 foreach($w as $p)$h.='<a class="rs-row" href="?page=pupil&amp;id='.$p['id'].'">'.avatar($p).'<span class="rs-tx"><b>'.e($p['name']).speech_mark((int)$p['id']).'</b><small>'.$p['n'].' '.($p['n']===1?'answer':'answers').' · '.e($why[$p['why']]??'waiting for internet').' · seen '.e(date('M j, g:i a',strtotime($p['at']))).'</small><em>Ask them to open BULIG Quest with internet. The answers upload by themselves.</em></span><strong class="rs-pct">'.$p['n'].'</strong></a>';
 return $h.'</div></section>';
}

/* ---------------- Data cleanup (administrator) ---------------- */
/** Pupils who look like the same child: the same name and grade, both active. */
function cleanup_duplicates():array{
 $sch=function_exists('school_users_sql')?school_users_sql('u'):'';
 $list=rows("SELECT u.id,u.public_id,u.name,u.avatar_path,u.created_at,p.grade_level,p.section,(SELECT COUNT(*) FROM activity_completion c WHERE c.pupil_id=u.id) work,(SELECT COALESCE(MAX(total),0) FROM pupil_xp x WHERE x.pupil_id=u.id) xp,(SELECT t.name FROM teacher_pupils tp JOIN users t ON t.id=tp.teacher_id WHERE tp.pupil_id=u.id) teacher FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.role='pupil' AND u.active=1".$sch." ORDER BY u.id");
 $g=[];foreach($list as $p){$k=$p['grade_level'].'|'.preg_replace('~[^a-z]~','',mb_strtolower(preg_replace('~\s+~u','',$p['name'])));$g[$k][]=$p;}
 return array_values(array_filter($g,fn($x)=>count($x)>1));
}
/** Move everything of the duplicate to the pupil we keep, then switch the duplicate off. */
function cleanup_merge(int $keep,int $dup):array{
 if($keep===$dup||!$keep||!$dup)fail('Choose two different pupils.');
 foreach([$keep,$dup] as $id)if(!val("SELECT 1 FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.id=? AND u.role='pupil'",[$id]))fail('Pupil not found.',404);
 $tables=array_column(rows("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='pupil_id'"),'t');
 $moved=0;db()->beginTransaction();try{
  foreach($tables as $t){if(!preg_match('~^[A-Za-z0-9_]+$~',$t)||in_array($t,['pupil_xp'],true))continue;$st=q('UPDATE IGNORE `'.$t.'` SET pupil_id=? WHERE pupil_id=?',[$keep,$dup]);$moved+=$st->rowCount();}
  /* XP: the sum of the moved XP records */
  if(in_array('pupil_xp',$tables,true)){$x=(int)val('SELECT COALESCE(SUM(amount),0) FROM xp_transactions WHERE pupil_id=?',[$keep]);q('INSERT INTO pupil_xp(pupil_id,total) VALUES(?,?) ON DUPLICATE KEY UPDATE total=GREATEST(total,VALUES(total))',[$keep,$x]);}
  foreach(rows("SELECT setting_key k FROM settings WHERE setting_key LIKE ?",['%\_'.$dup]) as $r)if(preg_match('~^(offline_rep|class_done_note)_'.$dup.'$~',$r['k']))q('DELETE FROM settings WHERE setting_key=?',[$r['k']]);
  q('UPDATE users SET active=0 WHERE id=?',[$dup]);
  audit('pupil_merge','#'.$dup.' into #'.$keep.' · '.$moved.' records');db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 return ['moved'=>$moved];
}
function cleanup_school_year_start():string{$y=(int)date('Y');if((int)date('n')<6)$y--;return $y.'-06-01 00:00:00';}
function cleanup_old_sections():array{
 if(!sections_archive_supported())return [];
 return rows('SELECT s.id,s.name,s.grade_level,s.created_at,u.name teacher,(SELECT COUNT(*) FROM pupil_sections ps WHERE ps.section_id=s.id) pupils FROM sections s JOIN users u ON u.id=s.teacher_id WHERE s.archived_at IS NULL AND s.created_at<?'.(function_exists('school_sections_sql')?school_sections_sql():'').' ORDER BY s.created_at,u.name',[cleanup_school_year_start()]);
}
function cleanup_action(string $action):never{
 $u=require_role('admin');
 if($action==='cleanup_merge'){$keep=(int)($_POST['keep']??0);$dup=(int)($_POST['dup']??0);
  if(empty($_POST['sure']))fail('Tick the box to confirm the merge.');$r=cleanup_merge($keep,$dup);
  flash('Merged. '.number_format($r['moved']).' records moved to the account you kept. The other account is switched off.');go('?page=cleanup');}
 if($action==='cleanup_archive'){
  if(!sections_archive_supported())fail('Import 015_admin_tools.sql first.');if(empty($_POST['sure']))fail('Tick the box to confirm.');
  $ids=array_map(fn($s)=>(int)$s['id'],cleanup_old_sections());if(!$ids){flash('There are no sections from last year to archive.');go('?page=cleanup');}
  db()->beginTransaction();try{$in=implode(',',$ids);
   q('UPDATE pupils p JOIN pupil_sections ps ON ps.pupil_id=p.user_id SET p.section=\'Not placed yet\' WHERE ps.section_id IN('.$in.')');
   q('DELETE FROM pupil_sections WHERE section_id IN('.$in.')');q('UPDATE sections SET archived_at=NOW() WHERE id IN('.$in.')');
   audit('sections_archive',count($ids).' sections');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash(count($ids).' '.(count($ids)===1?'section':'sections').' from last year archived. Pupils and their work are kept. Teachers can place them in new sections.');go('?page=cleanup');}
 if($action==='cleanup_clear'){$k=(string)($_POST['kind']??'');$age=(string)($_POST['age']??'');$K=cleanup_kinds();
  if(!isset($K[$k])||!isset(CLEANUP_AGES[$age]))fail('Choose what to clear.');if(empty($_POST['sure']))fail('Tick the box to confirm.');
  $lb=function_exists('admin_setting')?admin_setting('last_backup_at',''):'';if($lb===''||!function_exists('admin_days_since')||admin_days_since($lb)>=1)fail('Download a backup first (step 1), then delete.');
  [$label,$table,$col,$where]=$K[$k];$cut=$age==='all'?null:date('Y-m-d H:i:s',strtotime('-'.$age.' days'));
  $n=(int)q('DELETE FROM '.$table.' WHERE '.$where.($cut?' AND '.$col.'<?':''),$cut?[$cut]:[])->rowCount();
  audit('cleanup_clear',$label.' · '.CLEANUP_AGES[$age].' · '.$n.' removed');
  flash(number_format($n).' '.($n===1?'record':'records').' removed from '.$label.'.');go('?page=cleanup#cu-clear');}
 fail('Unknown action.');
}
/* v208 Clear old data: records that pile up and are not needed for learning (the activity log, read notifications,
   sign-in attempts, old help requests, earlier copies of changed answers, old messages). Pupils' answers, scores and XP
   are never touched. [label, table, date column, rows that may go] */
const CLEANUP_AGES=['30'=>'older than 30 days','90'=>'older than 3 months','180'=>'older than 6 months','365'=>'older than 1 year','all'=>'everything'];
function cleanup_kinds():array{
 $k=['log'=>['Activity log','audit_log','created_at','1=1','Every action in BULIG Quest (sign-ins, changes, deletions). The cleanup itself is still written in the log.'],
  'notif'=>['Read notifications','notifications','created_at','read_at IS NOT NULL','Bell notifications that were already read. Unread ones stay.'],
  'login'=>['Sign-in attempts','login_attempts','attempted_at','1=1','Records of wrong passwords, used to slow down guessing.'],
  'help'=>['“I need help” requests','help_requests','created_at','1=1','Old requests from pupils to their teachers.'],
  'hist'=>['Earlier copies of answers','response_history','created_at','1=1','Older versions of answers that were changed or rechecked. The current answer and its score stay.'],
  'msg'=>['Messages','admin_messages','created_at','1=1','Messages from the administrator, for everyone who got them.']];
 static $ok=null;if($ok===null){$ok=[];foreach($k as $id=>$x){try{one('SELECT 1 FROM '.$x[1].' LIMIT 1');$ok[$id]=true;}catch(Throwable $e){$ok[$id]=false;}}}
 return array_filter($k,fn($id)=>$ok[$id],ARRAY_FILTER_USE_KEY);
}
function cleanup_clear_card():string{
 $b=function_exists('admin_setting')?admin_setting('last_backup_at',''):'';$fresh=$b!==''&&function_exists('admin_days_since')&&admin_days_since($b)<1;$note=function_exists('admin_backup_note')?admin_backup_note():'';
 $h='<section class="card rs-card cu-clear" id="cu-clear"><div class="ws-hd"><h2>'.icon('trash').'Clear old data</h2><span class="rs-sub">Pupils’ answers, scores and XP are never removed</span></div><p class="muted">Old records make BULIG Quest slower and backups bigger. Deleting cannot be undone, so BULIG Quest asks for a backup first.</p>'
  .'<div class="cu-step'.($fresh?' done':'').'" data-cu-step><b class="cu-sn">1</b><div><b>Make a backup</b><small data-cu-note>'.e($fresh?'Done · '.$note:$note).'</small></div><span class="cu-bbtns"><a class="btn primary small" href="?page=backup_csv" data-cu-backup>'.icon('download').'Download backup now</a><a class="btn secondary small" href="?page=backup_sql" data-cu-backup>Full database (.sql)</a></span></div>'
  .'<p class="cu-step2"><b class="cu-sn">2</b>Choose how old, tick the box, then tap Delete.'.($fresh?'':' <span data-cu-wait>The Delete buttons open after you make a backup today.</span>').'</p><div class="cu-kinds">';
 foreach(cleanup_kinds() as $id=>[$label,$table,$col,$where,$note]){
  try{$all=(int)val('SELECT COUNT(*) FROM '.$table.' WHERE '.$where);$old=(int)val('SELECT COUNT(*) FROM '.$table.' WHERE '.$where.' AND '.$col.'<?',[date('Y-m-d H:i:s',strtotime('-90 days'))]);}catch(Throwable $e){continue;}
  $h.='<form method="post" class="cu-kind">'.csrf_field().'<input type="hidden" name="action" value="cleanup_clear"><input type="hidden" name="kind" value="'.$id.'"><div class="cu-kh"><b>'.e($label).'</b><span class="cu-n">'.number_format($all).' '.($all===1?'record':'records').($old?' · '.number_format($old).' older than 3 months':'').'</span></div><small>'.e($note).'</small>'
   .'<div class="cu-krow"><label><span class="sr-only">How old</span><select name="age">';
  foreach(CLEANUP_AGES as $a=>$t)$h.='<option value="'.$a.'"'.((string)$a==='90'?' selected':'').'>'.e(ucfirst($t)).'</option>';
  $h.='</select></label><label class="cu-sure"><input type="checkbox" name="sure" value="1" required>I want to delete these</label><button class="btn danger small" data-needs-backup'.($all&&$fresh?'':' disabled').($all?'':' data-empty').'>'.icon('trash').'Delete</button></div></form>';}
 return $h.'</div></section>';
}
function cleanup_view(array $u):void{
 echo hm_head('DATA CLEANUP','Data cleanup','Join pupils who have two accounts, put away last year’s sections, and clear old records like the activity log.');
 $d=cleanup_duplicates();
 echo '<section class="card rs-card"><div class="ws-hd"><h2>'.icon('people').'Pupils with two accounts</h2><span class="rs-sub">Same name and grade</span></div>';
 if(!$d)echo '<p class="rs-ok">'.icon('check').'No duplicate pupils found.</p>';
 foreach(array_slice($d,0,30) as $grp){usort($grp,fn($a,$b)=>[$b['work'],$b['xp']]<=>[$a['work'],$a['xp']]);$keep=$grp[0];
  echo '<div class="cu-grp"><div class="cu-list">';
  foreach($grp as $i=>$p)echo '<div class="cu-p'.($i?'':' keep').'">'.avatar($p).'<span><b>'.e($p['name']).'</b><small>'.e($p['public_id'].' · Grade '.$p['grade_level'].' · '.($p['section']?:'—').' · '.($p['teacher']?:'no teacher')).'</small><small>'.number_format((int)$p['work']).' answers · '.number_format((int)$p['xp']).' XP · made '.date('M j, Y',strtotime((string)$p['created_at'])).'</small></span>'.($i?'':'<span class="status status-ok">Keep</span>').'</div>';
  echo '</div>';
  foreach(array_slice($grp,1) as $p)echo '<form method="post" class="cu-form">'.csrf_field().'<input type="hidden" name="action" value="cleanup_merge"><input type="hidden" name="keep" value="'.(int)$keep['id'].'"><input type="hidden" name="dup" value="'.(int)$p['id'].'"><label class="cu-sure"><input type="checkbox" name="sure" value="1" required>Move the work of '.e($p['public_id']).' into '.e($keep['public_id']).', then switch '.e($p['public_id']).' off</label><button class="btn primary">'.icon('swap').'Merge</button></form>';
  echo '</div>';}
 echo '</section>';
 $old=cleanup_old_sections();$yr=date('F j, Y',strtotime(cleanup_school_year_start()));
 echo '<section class="card rs-card"><div class="ws-hd"><h2>'.icon('school').'Last year’s sections</h2><span class="rs-sub">Made before '.e($yr).'</span></div>';
 if(!sections_archive_supported())echo '<p class="muted">Import 015_admin_tools.sql first.</p>';
 elseif(!$old)echo '<p class="rs-ok">'.icon('check').'No sections from last year. Everything is up to date.</p>';
 else{echo '<ul class="cu-secs">';foreach(array_slice($old,0,40) as $s)echo '<li><b>Grade '.(int)$s['grade_level'].' · '.e($s['name']).'</b><small>'.e($s['teacher']).' · '.(int)$s['pupils'].' pupils · made '.date('M Y',strtotime((string)$s['created_at'])).'</small></li>';echo '</ul>'.(count($old)>40?'<p class="muted">and '.(count($old)-40).' more</p>':'');
  echo '<form method="post" class="cu-form">'.csrf_field().'<input type="hidden" name="action" value="cleanup_archive"><label class="cu-sure"><input type="checkbox" name="sure" value="1" required>Archive '.(count($old)===1?'this section':'these '.count($old).' sections').'. Pupils, scores and work stay. Pupils show as “Not placed yet”.</label><button class="btn primary">'.icon('download').'Archive in one step</button></form>';}
 echo '<p class="muted">To move every pupil up a grade as well, use Settings › New school year.</p></section>';
 echo cleanup_clear_card();
}

/* ---------------- v206: pupil wording ----------------
   Module directions written for the teacher ("Listen carefully as your teacher reads the passage", "Teacher reads:")
   confuse a pupil who learns alone in BULIG: here the speaker reads it. Only these teacher-directed phrases change;
   the story words stay as the module wrote them. */
function pupil_wording(string $t):string{
 static $r=null;
 if($r===null)$r=[
  '~\s*\(?Teacher may (?:read aloud|show pictures)[^)]*\)\.?~iu'=>'',
  '~Instructions for (?:the )?Teacher:\s*Read the story aloud slowly to the students\.?~iu'=>'Instructions: Tap the speaker to hear the story.',
  '~Instructions for (?:the )?Teacher:~iu'=>'Instructions:',
  '~Story for the teacher to read:~iu'=>'Story:',
  '~\s*Teacher reads the passages? assigned to each lesson\.?~iu'=>'',
  '~Teacher reads the passages? before learner.s answer\.?~iu'=>'Listen to the passage before you answer.',
  '~(?<!your )(?<!the )Teacher reads the (short story|story|poem|passage)(?: aloud)?\.?~iu'=>'Tap the speaker to hear the $1.',
  '~\(Teacher reads:\s*~iu'=>'(Listen: ',
  '~Teacher reads:~iu'=>'Listen:',
  '~Listen carefully to the teacher as the story is read aloud~iu'=>'Tap the speaker and listen carefully to the story',
  '~Listen carefully to (?:your|the) teacher as (?:she|he) reads~iu'=>'Tap the speaker and listen carefully to',
  '~Listen carefully to (?:your|the) teacher as (?:she|he) gives~iu'=>'Listen carefully to',
  '~Listen (carefully )?as (?:your|the) teacher reads~iu'=>'Tap the speaker and listen $1to',
  '~Listen to (?:your|the) teacher as (?:she|he) reads~iu'=>'Tap the speaker. Listen as BULIG Quest reads',
  '~Listen to the sounds your teacher is saying~iu'=>'Tap the speaker and listen to the sounds',
  '~Listen to (?:the|your) teacher (carefully|attentively)~iu'=>'Listen $1',
  '~Listen to your teacher and look~iu'=>'Listen and look',
  '~Listen to your teacher say each word~iu'=>'Tap the speaker to hear each word',
  '~Listen to your teacher read the words~iu'=>'Tap the speaker to hear the words',
  '~When the teacher says a word~iu'=>'When you hear a word',
  '~\bas the teacher says?\b~iu'=>'that you hear',
  '~Follow the jumping-and-sounds instructions shown with your teacher~iu'=>'Follow the jumping-and-sounds instructions',
  '~Your teacher will (?:listen and )?check the words you sound out correctly~iu'=>'BULIG Quest checks the words you sound out',
  '~Teacher sound out or blend the sample word/s~iu'=>'Listen as BULIG Quest sounds out and blends the sample word/s',
  '~The teacher will present and read the following words, then ask the learners to sound out~iu'=>'Listen to the following words, then sound out',
  '~Teacher read the words and let learners identify~iu'=>'Listen to the words and find',
  '~The teacher shows a picture~iu'=>'Look at the picture',
  '~The teacher flashes word cards, and the learners read them quickly~iu'=>'Read each word card quickly',
  '~\s*The teacher may include variation activities\.?~iu'=>'',
  '~Your teacher flashes the word cards~iu'=>'Read the word cards quickly',
  '~Teacher will introduce the words and show pictures?~iu'=>'Look at the pictures and read the words',
  '~Teacher:\s*Read each word aloud twice\.?\s*Learner:\s*~iu'=>'Listen to each word two times. ',
  '~Your teacher reads each word aloud twice~iu'=>'Tap the speaker to hear each word two times',
  '~Your teacher reads each word aloud~iu'=>'Tap the speaker to hear each word',
  '~Your teacher reads a word aloud twice~iu'=>'Tap the speaker to hear the word two times',
  '~Repeat after (?:your|the) teacher~iu'=>'Repeat after BULIG Quest',
  '~after the teacher\b~iu'=>'after BULIG Quest',
  '~Learner:\s*Circle the words that matche?s? the words teacher reads~iu'=>'Circle the words that match the words you hear',
  '~Your teacher listens for accuracy and speed~iu'=>'BULIG Quest listens for accuracy and speed',
  '~Listen as the teacher gives the meaning of~iu'=>'Listen to the meaning of',
  '~as the teacher picks? and announces? them~iu'=>'as you hear them',
  '~Your teacher will read (?:a|the) (definition/clue|word) aloud~iu'=>'Listen to the $1',
  '~\s*read by your teacher~iu'=>'',
  '~as dictated by the teacher~iu'=>'that you hear',
  '~Start when your teacher says\s*[“"]Go\.?[”"]~iu'=>'Tap Start when you are ready.',
  '~\s*The teacher (?:helps each student|circulates)[^.]*\.~iu'=>'',
  '~Teacher says a word~iu'=>'Listen to a word',
  '~Teacher gives a starting word~iu'=>'Start with a word',
  '~Circle the words that matche?s? the words (?:the )?teacher reads~iu'=>'Circle the words that match the words you hear',
  '~\.?\s*If\s+(?:none|no\s+volunteer),?\s+the\s+teacher\s+will\s+demonstrate(?:\s+the)?(?:\s+(?:activity|blending))?\.?~iu'=>'.',
  '~Teacher will let the learners read more words with~iu'=>'Read more words with',
  '~Students will act as tourist guides by identifying and leading the teacher to specific~iu'=>'Act as a tourist guide: find the',
  '~The teacher prepares the Word Hunt Table and posts it on the board, with~iu'=>'Look at the Word Hunt Table, with',
  '~Show pictures with consonant~iu'=>'Look at the pictures with consonant',
  '~Ask any learner/s who can blend~iu'=>'Blend',
  '~\.{2,}~u'=>'.',
 ];
 if(stripos($t,'teacher')===false)return $t;
 $o=preg_replace(array_keys($r),array_values($r),$t)??$t;
 return preg_replace('~[ \t]{2,}~u',' ',$o)??$o;
}

/* ---------------- v207: directions that match how the pupil answers in BULIG ----------------
   The modules were written for paper ("Encircle the letter", "Write it on your answer sheet", "Draw a line to match").
   In BULIG the pupil taps a button, taps a picture, types in a box or uses the letter keys. The words are changed to
   the way this card is answered ($kinds: chip, chips, pick, picks, num, words, split, tiles, write, match, lgrid ...). */
function app_wording(string $t,array $kinds):string{
 if(trim($t)==='')return $t;
 $has=fn(string ...$k)=>(bool)array_intersect($k,$kinds);
 $tap=$has('chip','chips','pick','picks','num','words','pairs');
 $multi=count(array_filter($kinds,fn($k)=>str_starts_with((string)$k,'chip+')))>0;
 $r=[];
 /* everywhere: paper things that are not in BULIG */
 $r+=['~\s*Write your answers? on your answer sheets?\.?~iu'=>'','~\s*on your answer sheets?(?=\.|$)~iu'=>'','~Draw the following in your notebook and (?:box|tap) the one~iu'=>'Look at the following and tap the one','~\s*Answer sheets are provided\.?~iu'=>'','~\s*Use colou?r pens? or crayons to answer\.?~iu'=>'',
  '~\s+with (?:a )?pencil\b~iu'=>'','~\s+(?:or )?colou?r the words with crayons~iu'=>'','~\s+in the (?:activity sheet|worksheet)\b~iu'=>'','~\b(?:in|on) your (?:English )?notebook\b~iu'=>'in the answer box','~when I say them~iu'=>'when you hear them'];
 if($has('match')){$r+=[
  '~Write the correct letter to match~iu'=>'Match','~\s*Write your answers? on the space provided(?: for)?\.?~iu'=>'','~Connect the words(?! with a line)~iu'=>'Match the words',
  '~Draw a line to match~iu'=>'Match','~Draw a line that match(?:es)? to~iu'=>'Match',
  '~\s*by (?:drawing a line|connecting (?:it|them) with a line|drawing a line connecting them)~iu'=>'',
  '~\s*Connect the words with a line\.?~iu'=>'','~Connect the words in (Set A) to its antonym in (Set B) by drawing a line~iu'=>'Match the words in $1 with their antonyms in $2',
  '~\s*Write (?:the|your) (?:correct )?(?:letter|answer)s?(?: of (?:your|the correct) answer| of the definition)?(?: on the line| in the blank| next to (?:each word|the number))?(?: before each number| provided)?\.?~iu'=>'',
  '~connect the missing letters in column B~iu'=>'match the missing letters in column B'];}
 if($tap||$multi){$r+=[
  '~(?:In the space provided, )?put a \(✓\) in the box if it is (\w+) and \(X\) for (\w+)~iu'=>'Tap ✓ if it is $1 and X if it is $2',
  '~Copy the letter of your answer and (?:encircle|tap) it~iu'=>'Tap the letter of your answer','~Copy and underline your answer~iu'=>'Tap your answer',
  '~\s*and write it in the box provided same as the given example~iu'=>'','~and write the number of syllables~iu'=>'and tap the number of syllables',
  '~Enter the number in the box and copy the sentence on the line~iu'=>'Tap the number of words','~ and copy the sentence on the line~iu'=>'',
  '~Colou?r the box with a word~iu'=>'Tap a word','~Colou?r the box blue with correct~iu'=>'Tap the correct',
  '~Find and encircle the words in the worksheet~iu'=>'Find the words',
  '~(?:Put|Place) (?:a )?(?:check|\(✓\)|✓)(?: mark)?\s*(?:\(✓\)\s*)?(?:on|to|in) (?:the box (?:of|for) )?~iu'=>'Tap ',
  '~Put check \(✓\) if~iu'=>'Tap ✓ if','~\band \(x\) if in~iu'=>'and X if in',
  '~Check the box of (?:your|the) correct answer~iu'=>'Tap the correct answer','~Read and check~iu'=>'Read and tap',
  '~Check the names of~iu'=>'Tap the names of','~Check the dots as you read each word, colou?r the star if done reading the sentence~iu'=>'Tap each word as you read it',
  '~(?:Colou?r|Check) the (heart|star|moon|dots?) as you read (?:each word|the sentences twice)~iu'=>'Tap each word as you read it',
  '~(?<![\w-])(?:Encircle|Circle|Box|Underline|Colou?r|Shade|Highlight)( the| each| all| your| two| three| word)\b~u'=>'Tap$1',
  '~(?<=\band |\bthen |\bThen, )(?:encircle|circle|box|underline|colou?r|shade)( the| each| all| your| two| three)\b~u'=>'tap$1',
  '~(?:Choose the best answer and )?write (?:only )?(?:the|its) (?:corresponding )?letter(?: of (?:the|your) (?:correct|best) answer| of your choice)?(?: on the line| in the blank| on the blank)?~iu'=>'tap the letter of the correct answer',
  '~Write (?:only )?the (?:corresponding )?letter(?: of (?:the|your) (?:correct|best) answer| of your choice)?(?: on the line| in the blank| on the blank)?~iu'=>'Tap the letter of the correct answer',
  '~Select the letter of your answer and write~iu'=>'Tap the letter of your answer',
  '~Copy the letter of the correct answer~iu'=>'Tap the letter of the correct answer',
  '~(?:On the space provided, )?write (R) (?:for|if it is) (Reality),? (?:and )?(F) (?:for|if it is) (Fantasy)(?: on the space provided)?~iu'=>'tap $1 for $2 or $3 for $4',
  '~Write (R) (?:for|if it is) (Reality),? (?:and )?(F) (?:for|if it is) (Fantasy)(?: on the space provided)?~iu'=>'Tap $1 for $2 or $3 for $4',
  '~Write (True|FACT) if (?:the statement|it) is (correct),? (?:and )?(False|BLUFF) if (?:it is )?(incorrect|not)~iu'=>'Tap $1 if the statement is $2, or $3 if it is $4',
  '~write (?:the )?(?:numbers? )?1,? 2,? 3,? 4,?(?: and)? 5 (?:on|in) the blank(?: provided| before each event)?~iu'=>'tap 1, 2, 3, 4 or 5 for each event',
  '~Write (?:the )?(?:numbers? )?1,? 2,? 3,? 4,?(?: and)? 5 (?:on|in|before) (?:the blank(?: provided| before each event)?|each event)~iu'=>'Tap 1, 2, 3, 4 or 5 for each event',
  '~write (?:the )?(?:numbers? )?1,? 2,? 3,? 4,?(?: and)? 5 before each event~iu'=>'tap 1, 2, 3, 4 or 5 for each event',
  '~Write numbers 1- ?4 on the line~iu'=>'Tap 1, 2, 3 or 4 for each fact','~Write 1, 2, 3, 4 on the line~iu'=>'Tap 1, 2, 3 or 4',
  '~draw a (star \(★\))~iu'=>'tap the $1','~draw a (circle \(●\))~iu'=>'tap the $1',
  '~\s*Write your answer on the (?:given )?line(?: provided)?\.?~iu'=>' Tap your answer.',
  '~\s*Write the word on the line\.?~iu'=>' Tap the word.','~\s*and write (?:it|your answer|the correct answer|the letter) (?:in|on) the (?:blank|line)(?: provided)?~iu'=>'',
  '~\s*Write the correct answer in the blank\.?~iu'=>' Tap the correct answer.',
  '~write your answer on the proper column~iu'=>'tap noun or verb',
  '~Fill in the blanks? with the (?:missing |correct )?(blends?|consonant blend|word)~iu'=>'Tap the missing $1',
  '~Fill in the correct beginning blend~iu'=>'Tap the correct beginning blend',
  '~Fill in the blanks in each sentence with the correct answer~iu'=>'Tap the correct answer for each sentence',
  '~Read, choose and write the missing consonant blend~iu'=>'Read, then tap the missing consonant blend',
  '~Choose the correct consonant blend and write it in the blank~iu'=>'Tap the correct consonant blend',
  '~\s*Print the blend on the lines\.?~iu'=>'',
  '~Match (?:each picture|the pictures?) with the correct (word|cl- and cr- blend)(?: by drawing a line connecting them)?~iu'=>'Tap the correct $1 for each picture',
  '~Match the dr words to the correct picture~iu'=>'Tap the dr word that matches the picture',
  '~Put it in the correct box~iu'=>'Tap the correct box',
  '~Draw a line to connect the onset to its rime~iu'=>'Tap the onset, then tap its rime',
  '~Draw a line that match(?:es)? to each onset, rime and picture~iu'=>'Tap the onset and the rime that match the picture',
  '~Connect the letter/s of the onset to the rime by writing your word answer in the line provided~iu'=>'Tap the onset, then tap the rime to make the word',
  '~Make words by joining the onset to the rime\. Write your answer on the line provided~iu'=>'Make words by joining the onset to the rime. Tap the onset and the rime',
  '~Draw a line along the letters that blend to form a word\. Write your answer in the box~iu'=>'Tap the letters that blend to form a word, one in each row',
  '~Match the sound I make to the correct picture~iu'=>'Match the sound you hear to the correct picture',
  '~Put the sounds together for each group of sounds below that I say~iu'=>'Put the sounds you hear together',
  '~Write the number under each picture that matches the word~iu'=>'Tap the number of the picture that matches the word',
  '~(?:Write|Enter) the (?:answer|number(?: of sounds/phonemes)?) (?:on the space given|in the space provided|in the box|inside the cloud)~iu'=>'Tap the number',
  '~Write your answer in the space provided~iu'=>'Tap the number','~Color one circle for every sound that you say~iu'=>'Tap the number of sounds that you say',
  '~Match the number to the picture~iu'=>'Tap the number of the picture',
  '~Count the words in each sentence and place the answer in the box~iu'=>'Count the words in each sentence and tap the number',
  '~Enter the number in the box and copy the sentence on the line~iu'=>'Tap the number of words',
  '~Put a star to the picture~iu'=>'Tap the picture','~Choose the letter of the correct answer~iu'=>'Tap the letter of the correct answer','~Draw a line under~iu'=>'Tap'];}
 if($has('tiles','xcells','build')){$r+=[
  '~Cut the phoneme below and paste it beside the picture~iu'=>'Use the letter keys to write the phonemes',
  '~\s*(?:on|in) the (?:blank|box|line|space)(?: space)?(?: provided| below| given)?(?= ?[.:,]|$)~iu'=>' with the letter keys',
  '~\s*inside the cloud~iu'=>' with the letter keys','~\s*in the box below~iu'=>' with the letter keys',
  '~Choose the letter from the jungle box~iu'=>'Choose the letter from the letter keys',
  '~Write T if it is true and F if it is false~iu'=>'Tap T if it is true and F if it is false',
  '~draw a line from square to square to link~iu'=>'link','~in the activity sheet~iu'=>''];}
 if($has('write')){$r+=[
  '~\s*(?:on|in) (?:the|your) (?:space|spaces|blank|line|lines)(?: provided| given)?(?: for)?(?= ?[.:,]|$)~iu'=>' in the answer box',
  '~Fill in the boxes with each number~iu'=>'Type the answer for each number',
  '~Write the word and encircle it\.\s+Copy the name of the plant mentioned in the selection\s+and draw a box around it~iu'=>'Write the name of the plant mentioned in the selection',
  '~Write the word “boil”\. Cross out its,? first letter\. In its place, write the third letter of the alphabet~iu'=>'Take the word “boil”. Change its first letter to the third letter of the alphabet. Write the new word',
  '~Write the word “boil”\. Cross out its,? first letter~iu'=>'Write the word “boil” without its first letter',
  '~\. Write and encircle it~iu'=>'. Write it','~(?:Copy|Write) and (?:underline|encircle)~iu'=>'Write','~Write your answer and underline it~iu'=>'Write your answer',
  '~Write inside a box~iu'=>'Write',
  '~\s*and (?:encircle|underline|draw a box around) (?:it|his name|the word)\b~iu'=>'','~\s*(?:Underline|Encircle) (?:its first letter|the word)\.~iu'=>'',
  '~Draw an editorial based on~iu'=>'Write an editorial based on','~Draw an improvement on the editorial cartoon~iu'=>'Describe an improvement on the editorial cartoon'];}
 if($has('lgrid')){$r+=['~Encircle the words found in the puzzle with word clues~iu'=>'Find the words in the puzzle that match the word clues','~(?:Find and )?(?:en)?circle the (words?|vocabulary words)~iu'=>'Find the $1','~Search for each word in the puzzle and encircle it,?~iu'=>'Find each word in the puzzle','~Colou?r the words with crayons\.?~iu'=>'Tap each word you find.','~\s*Fill in the puzzle by using a line to connect and make a word\.?~iu'=>''];}
 if($has('split')){$r+=['~Encircle the onset and underline the rime on the name of each picture~iu'=>'Tap where the rime starts in the name of each picture','~Copy and (?:divide|segment) the words? by putting (?:a )?(?:slash )?(?:/|\(/\))(?: in each syllable)?~iu'=>'Tap between the letters to put a slash (/) between the syllables','~Copy the word, segment it with a slash \(/\)~iu'=>'Segment the word: tap between the letters to put a slash (/)','~Write the onsets and rimes on the box~iu'=>'Tap where the rime starts'];}
 $r+=['~Write only the letter of the correct answer\.?~iu'=>'Answer each question.'];
 if(!$r)return $t;
 $o=preg_replace(array_keys($r),array_values($r),$t)??$t;
 if($o===$t)return $t;
 $o=preg_replace(['~[ \t]{2,}~u','~(\w) +\.(?!\.)~u','~(?<!\.)\.\s+\.(?!\.)~u','~(^|[.!?:]\s+|·\s+)tap\b~u','~\b(and|then|or) Tap\b~u','~listed with the letter keys~u','~(answer|question)\s+(Make sure)~u','~(?<!\.)\.\.(?!\.)~u','~Before each event, tap (1, 2, 3, 4 or 5) for each event~u','~tap (1, 2, 3, 4 or 5) for each event to show the order~iu','~, Tap\b~u'],[' ','$1.','.','$1Tap','$1 tap','listed in the box','$1. $2','.','Tap $1 for each event','Tap $1 for each event to show the order',', tap'],$o)??$o;
 return $o;
}

/** v207: the green tag above already names the page ("PRE-ASSESSMENT · PAGE 1"), so the instruction box starts with
    the direction itself: headings before "Instructions:" / "Directions:" (PRETEST (Activity 1-10) Vocabulary
    Development (1-10) A., SET A PRE-TEST Day 1-10, Activity 2.1 ·, Part A — …) and the label are left out.
    Real explaining sentences ("A cause is WHY something happens.") stay. */
function instruction_tidy(string $t):string{
 $t=trim($t);if($t==='')return $t;
 if(!preg_match_all('~(?:^|[\s·.:—-])(Instructions?(?:/s)?(?: for (?:the )?(?:pupils?|learners?))?|Directions?)\s*:\s*~iu',$t,$m,PREG_OFFSET_CAPTURE))return $t;
 $last=end($m[0]);$cut=$last[1]+strlen($last[0]);$head=substr($t,0,$last[1]);$body=trim(substr($t,$cut));if($body==='')return $t;
 /* keep explaining sentences of the head: 6 words or more, mostly small letters, not a heading */
 $keep=[];foreach(preg_split('~\s*·\s*|(?<=[.!?])\s+~u',$head) as $s){$s=trim($s," \t·—-");if($s==='')continue;
  $w=preg_split('~\s+~u',$s);$lower=count(array_filter($w,fn($x)=>preg_match('~^[a-z]~u',$x)));
  if(count($w)>=6&&$lower>=4&&!preg_match('~^(?:PRE|POST|SET|Part|Activity|Lesson|Assessment|Formative|Exercise)\b~u',$s)&&preg_match('~[.!?]$~u',$s))$keep[]=$s;
  elseif(count($w)>=8&&$lower>=5&&preg_match('~[.!?]$~u',$s))$keep[]=$s;}
 /* a word bank in the heading is part of the direction ("Word Bank: stove, napkin, …") */
 if(preg_match('~\b(Word Bank|Words to use|Choices)\s*:\s*(.+)$~iu',$head,$wb))$body=rtrim($body).' '.$wb[1].': '.trim($wb[2]," \t·.").'.';
 return trim(implode(' ',$keep).' '.$body);
}

/** v207: instructions fixed one by one (database/instruction-fixes.json): the exact text BULIG showed => the clear
    direction for a pupil who answers in BULIG (teacher procedures, classroom games, headings with no direction). */
function instruction_fixes():array{static $m=null;if($m===null){$f=__DIR__.'/../database/instruction-fixes.json';$m=is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[];}return $m;}

/* v208 Speech support, chosen by the teacher for each pupil (kept in settings as speech_<pupil id>, no SQL):
   - standard: BULIG listens as before.
   - accent:   Accent-friendly. Common sound changes in Philippine and Lumad accents count as right: i/e, o/u, p/f, b/v,
               th as t or d, s/z/sh, and a last consonant that is not said ("des" for "desk").
   - support:  Speech support (for example cleft palate). BULIG never marks a spoken word wrong and takes no hearts.
               The pupil reads with Max and taps each word as they say it; the recording goes to the teacher, who
               listens and scores it. Pupils and classmates never see the setting. */
const SPEECH_MODES=['standard'=>['Standard','BULIG Quest listens and checks each word.'],
 'accent'=>['Accent-friendly','For a different accent (for example pupils from the mountains or Lumad communities). Sounds like i and e, o and u, p and f, b and v, and a last sound that is not said count as right.'],
 'support'=>['Speech support','For a pupil with a speech need, such as cleft palate. BULIG Quest never marks a spoken word wrong and takes no hearts. The pupil taps each word as they read it, and you listen to the recording and score it.']];
function speech_mode(int $pid):string{static $c=[];if(isset($c[$pid]))return $c[$pid];
 try{$v=(string)val('SELECT setting_value FROM settings WHERE setting_key=?',['speech_'.$pid]);}catch(Throwable $e){$v='';}return $c[$pid]=isset(SPEECH_MODES[$v])?$v:'standard';}
/** The signed-in pupil's speech setting ('standard' for everyone else). */
function speech_me():string{try{$u=current_user();}catch(Throwable $e){$u=null;}return $u&&($u['role']??'')==='pupil'?speech_mode((int)$u['id']):'standard';}
/** Accent-friendly key of a word (th is said as t): the sound changes above give the same key. */
/** v208 A lesson that teaches a vowel sound ("CVC Short “e”", "long a", "vowels"): Accent-friendly checks vowels strictly there. */
function vowel_lesson(int $lid):bool{static $c=[];if(isset($c[$lid]))return $c[$lid];$l=$lid?one('SELECT title,subtitle FROM lessons WHERE id=?',[$lid]):null;
 return $c[$lid]=$l&&(bool)preg_match('~\b(short|long)\s*[“"\'‘]?[aeiou]\b|\bvowels?\b~iu',$l['title'].' '.$l['subtitle']);}
function accent_key(string $w):string{
 $strict=!empty($GLOBALS['bulig_vowel_lesson']);
 $w=say_sound(str_replace('th','t',strtolower($w)));$w=preg_replace($strict?['~sh~','~z~','~S$~','~f~','~v~','~T$~','~K$~','~P$~']:['~sh~','~z~','~S$~','~f~','~v~','~ee~','~oo~','~e~','~u~','~T$~','~K$~','~P$~'],$strict?['s','s','s','p','b','t','k','p']:['s','s','s','p','b','i','o','i','o','t','k','p'],$w)??$w;
 return preg_replace(['~([^aeiou])s$~','~([aeiou][^aeiou])[^aeiou]$~'],['$1','$1'],$w)??$w;}
/** v208 the small icon beside the name of a pupil with a speech setting (teacher and admin pages only, never on class
    screens): a red plus for Speech support, a blue speech bubble for Accent-friendly. */
function speech_icon(string $m):string{
 if($m==='support')return '<span class="sp-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9.5 4h5v5.5H20v5h-5.5V20h-5v-5.5H4v-5h5.5z"/></svg></span>';
 if($m==='accent')return '<span class="sp-mark acc" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-8l-5 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg></span>';
 return '';}
function speech_mark(int $pid):string{
 $m=speech_mode($pid);if($m==='standard')return '';
 $t=$m==='support'?'Speech support: BULIG Quest does not mark spoken words wrong. You listen and score.':'Accent-friendly: BULIG Quest accepts common accent sounds.';
 return str_replace('aria-hidden="true">','title="'.e($t).'" aria-label="'.e($t).'" role="img">',speech_icon($m));}
/** v208 Reading support written on a class list: "Accent", "Accent-friendly", "Speech", "Speech support", "Cleft palate"... */
function speech_parse(string $v):string{$v=strtolower(trim($v));if($v==='')return 'standard';if(isset(SPEECH_MODES[$v]))return $v;
 if(str_contains($v,'accent'))return 'accent';if(str_contains($v,'speech')||str_contains($v,'cleft')||str_contains($v,'support'))return 'support';return 'standard';}
/** Save a pupil's speech setting ('standard' removes it). */
function speech_set(int $pid,string $m):void{if(!isset(SPEECH_MODES[$m]))$m='standard';
 if($m==='standard')q('DELETE FROM settings WHERE setting_key=?',['speech_'.$pid]);
 else q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['speech_'.$pid,$m]);}
/** v208 Add one pupil: Reading support, chosen when the pupil is registered (Standard unless the teacher picks another). */
function speech_reg_field():string{
 $h='<fieldset class="sp-reg"><legend>Reading support</legend><p class="muted">How BULIG Quest listens when this pupil reads aloud. Only you see it, and you can change it later on the pupil’s page.</p><div class="sp-form">';
 foreach(SPEECH_MODES as $k=>[$t,$d])$h.='<label class="sp-opt"><input type="radio" name="speech_mode" value="'.$k.'"'.($k==='standard'?' checked':'').'><span><b>'.e($t).($k!=='standard'?' '.speech_icon($k):'').'</b><small>'.e($d).'</small></span></label>';
 return $h.'</div></fieldset>';}
function speech_save(array $u):void{
 $pid=(int)($_POST['pupil']??0);own_pupil($pid);$m=(string)($_POST['mode']??'');if(!isset(SPEECH_MODES[$m]))fail('Choose how BULIG Quest listens.');
 speech_set($pid,$m);
 audit('speech_mode','Pupil '.$pid.': '.$m);flash('Saved: '.SPEECH_MODES[$m][0].'.');go('?page=pupil&id='.$pid.'#speech');
}
