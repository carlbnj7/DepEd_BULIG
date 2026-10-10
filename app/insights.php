<?php
/* Teacher insights:
   - Question check (Levels 5-7): for one lesson, how many of the teacher's pupils got each question right,
     and the wrong answer they chose most. Built from the answer keys (app/scoring.php).
   - Progress report: one printable page per pupil (level, lessons, scores, pre-test and post-test, oral reading, notes). */

/** One card's question text, short. */
function qc_text(array $set,int $n,string $t):string{
 $c=$set['cards'][$n-1]??[];$x=trim(preg_replace('~\s+~u',' ',(string)($c['text']??'')));
 if($x===''&&$t==='match')$x='Matching';if($x===''&&$t==='order')$x='Number the events';if($x==='')$x='Card '.$n;
 return mb_strimwidth($x,0,140,'…');
}

function qcheck_view(array $u):void{
 $tid=(int)$u['id'];
 echo '<div class="pageheading"><div><span class="eyebrow">QUESTION CHECK · LEVELS 5–7</span><h1>Which questions did the class miss?</h1><p class="muted">For each lesson, see how many of your pupils got each question right and the wrong answer they chose most. Use it to plan what to read again together.</p></div></div>';
 $levels=[6,7,8];$level=(int)($_GET['level']??0);$grade=(int)($_GET['grade']??0);$lid=(int)($_GET['lesson']??0);
 /* Start with the lesson your pupils answered most recently. */
 if(!$lid&&!$level){$r=one("SELECT l.id,m.level_id,m.grade_level FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id AND t.teacher_id=? JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id IN (6,7,8) AND c.status IN ('completed','approved') ORDER BY c.submitted_at DESC LIMIT 1",[$tid]);if($r){$lid=(int)$r['id'];$level=(int)$r['level_id'];$grade=(int)$r['grade_level'];}}
 if(!in_array($level,$levels,true))$level=6;if($grade<1||$grade>6)$grade=1;
 $keys=answer_keys()['keys']??[];
 $lessons=[];foreach(rows('SELECT l.id,l.position,l.title,l.subtitle FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND m.grade_level=? AND l.published=1 ORDER BY l.position',[$level,$grade]) as $l){
  $has=false;foreach(array_keys($keys) as $k)if(str_starts_with($k,$level.':'.$grade.':'.$l['position'].':')){$has=true;break;}if($has)$lessons[(int)$l['id']]=$l;}
 if(!isset($lessons[$lid]))$lid=$lessons?(int)array_key_first($lessons):0;
 $answered=[];if($lessons)foreach(rows("SELECT a.lesson_id,COUNT(DISTINCT c.pupil_id) n FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id AND t.teacher_id=? JOIN activities a ON a.id=c.activity_id WHERE a.lesson_id IN (".implode(',',array_keys($lessons)).") AND c.status IN ('completed','approved') GROUP BY a.lesson_id",[$tid]) as $r)$answered[(int)$r['lesson_id']]=(int)$r['n'];
 echo '<form method="get" class="card pg-filter qc-filter"><input type="hidden" name="page" value="qcheck"><label>Level<select name="level" data-autosubmit>';foreach($levels as $l)echo '<option value="'.$l.'"'.($l===$level?' selected':'').'>'.e(level_label($l)).'</option>';echo '</select></label>';
 echo '<label>Grade<select name="grade" data-autosubmit>';for($g=1;$g<=6;$g++)echo '<option value="'.$g.'"'.($g===$grade?' selected':'').'>Grade '.$g.'</option>';echo '</select></label>';
 echo '<label class="qc-lesson">Lesson<select name="lesson" data-autosubmit>';foreach($lessons as $id=>$l)echo '<option value="'.$id.'"'.($id===$lid?' selected':'').'>'.e(lesson_display_label($level,(int)$l['position']).' · '.$l['subtitle'].' ('.($answered[$id]??0).' answered)').'</option>';echo '</select></label><button class="btn secondary pg-go">Show</button></form>';
 if(!$lid){echo '<section class="empty">'.icon('chart').'<h2>No questions to check here yet.</h2><p>This grade has no lessons with an answer key.</p></section>';return;}
 $acts=rows("SELECT a.* FROM activities a WHERE a.lesson_id=? AND a.published=1 ORDER BY FIELD(a.phase,'pre','learn','post'),a.position",[$lid]);$shown=0;$pupils=0;
 foreach($acts as $a){$key=activity_key($a);if(!$key)continue;$set=l2a_set($a);if(!$set)continue;
  $done=rows("SELECT c.* FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id AND t.teacher_id=? WHERE c.activity_id=? AND c.status IN ('completed','approved')",[$tid,(int)$a['id']]);
  $pupils=max($pupils,count($done));
  $stat=[];foreach($key as $n=>$k)$stat[(int)$n]=['pts'=>0,'of'=>0,'n'=>0,'wrong'=>[]];
  foreach($done as $c){$s=key_score($a,(string)$c['response']);if(!$s)continue;foreach($s['cards'] as $n=>$x){if(!isset($stat[$n]))continue;$st=&$stat[$n];$st['n']++;$st['pts']+=$x['pts'];$st['of']+=$x['of'];if($x['t']==='choice'&&!$x['pts']&&$x['got']!=='')$st['wrong'][$x['got']]=($st['wrong'][$x['got']]??0)+1;unset($st);}}
  $shown++;
  echo '<section class="card qc-act"><div class="qc-head"><h2>'.e($set['module_title']).'</h2><span class="muted">'.e(l1_phase_label($a['phase'])).' · '.count($done).' '.(count($done)===1?'pupil':'pupils').' answered</span></div>';
  if(!$done){echo '<p class="muted">None of your pupils has answered this activity yet.</p></section>';continue;}
  echo '<div class="tablewrap"><table class="qc-table"><thead><tr><th>Question</th><th>Pupils right</th><th>Wrong answer chosen most</th></tr></thead><tbody>';
  foreach($stat as $n=>$st){$k=$key[$n];$pct=$st['of']?(int)round(100*$st['pts']/$st['of']):0;$cls=$pct>=75?'ok':($pct>=50?'part':'no');
   arsort($st['wrong']);$w=$st['wrong']?array_key_first($st['wrong']):null;
   $right=$k['t']==='choice'?$st['pts'].' of '.$st['n']:$pct.'% of '.($k['t']==='match'?'pairs':'events');
   echo '<tr class="qc-'.$cls.'"><td><b>'.$n.'.</b> '.e(qc_text($set,$n,$k['t'])).'<small class="qc-ans">Answer: '.e($k['t']==='choice'?implode(' or ',array_merge([$k['a']],$k['alt']??[])):($k['t']==='match'?implode(', ',array_map(fn($i,$l)=>$i.'-'.$l,array_keys($k['a']),$k['a'])):implode(' · ',array_map(fn($l,$v)=>$l.' = '.$v,array_keys($k['a']),$k['a'])))).'</small></td>';
   echo '<td><span class="qc-bar" role="img" aria-label="'.$pct.' percent right"><i class="qc-fill w'.(int)(round($pct/5)*5).'"></i></span><small>'.e($right).'</small></td>';
   echo '<td>'.($w!==null?'<span class="qc-wrong">'.e($w).' ('.$st['wrong'][$w].')</span>':'<span class="muted">—</span>').($cls==='no'&&$st['n']?'<small class="qc-tip">Read this part of the story again together.</small>':'').'</td></tr>';}
  echo '</tbody></table></div></section>';}
 if(!$shown)echo '<section class="empty">'.icon('chart').'<h2>No marked questions in this lesson.</h2><p>Its answers are written, drawn or read aloud, so you check them in Activity history.</p></section>';
 elseif($pupils)echo '<p class="muted qc-note">'.icon('info').'If almost every pupil “misses” the same question, check the module: the answer key may need a second look.</p>';
}

/** One printable page per pupil for parents and the school head. */
function pupil_report_view(array $u):void{
 $pid=(int)($_GET['id']??0);own_pupil($pid);$p=one('SELECT u.*,pp.grade_level FROM users u JOIN pupils pp ON pp.user_id=u.id WHERE u.id=?',[$pid]);if(!$p)fail('Pupil not found.',404);
 $sec=one('SELECT s.name FROM pupil_sections ps JOIN sections s ON s.id=ps.section_id WHERE ps.pupil_id=? LIMIT 1',[$pid]);$grade=(int)$p['grade_level'];
 $s=progress_stats($pid);$assigned=pupil_start_level($pid);$next=next_learning_lesson($pid);$now=$next?(int)$next['level_id']:$assigned;
 $auto=one("SELECT SUM(c.score) s,SUM(c.max_score) m,COUNT(*) n FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? AND m.level_id IN (6,7,8) AND c.max_score>0 AND c.status IN ('completed','approved')",[$pid]);
 $avg=$auto&&(float)$auto['m']>0?(int)round(100*(float)$auto['s']/(float)$auto['m']):null;
 echo '<div class="no-print rp-actions"><a class="btn quiet" href="?page=pupil&amp;id='.$pid.'">'.'Back to '.e(explode(' ',trim((string)$p['name']))[0]).'</a><button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button></div>';
 echo '<article class="card rp-page"><header class="rp-top"><img src="assets/bulig-logo.png" alt="BULIG"><div><strong>Progress report</strong><span>'.e(date('F j, Y')).'</span></div></header>';
 echo '<h1 class="rp-name">'.e($p['name']).'</h1><p class="muted rp-who">Grade '.$grade.($sec?' · '.e($sec['name']):'').' · Pupil ID '.e($p['public_id']).' · Teacher: '.e($u['name']).'</p>';
 echo '<div class="rp-kpis"><div><small>Learning now</small><b>'.e($now?level_label($now):'—').'</b>'.($now?'<span>'.e((string)val('SELECT title FROM bulig_levels WHERE id=?',[$now])).'</span>':'').'</div><div><small>Lessons finished</small><b>'.(int)$s['completed'].'</b><span>'.(int)$s['xp'].' XP earned</span></div><div><small>Average score</small><b>'.($avg!==null?$avg.'%':'—').'</b><span>'.($avg!==null?'marked by the answer key':'no marked answers yet').'</span></div></div>';
 /* Lessons finished in each level the pupil has worked in. */
 $lv=rows('SELECT DISTINCT m.level_id FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? ORDER BY m.level_id',[$pid]);
 if($lv){echo '<h2 class="rp-h">Lessons</h2><table class="rp-table"><thead><tr><th>Level</th><th>Finished</th><th>Status</th></tr></thead><tbody>';
  foreach($lv as $r){$L=(int)$r['level_id'];$ls=level_lessons($L,$grade);if(!$ls)continue;$ids=implode(',',array_map(fn($x)=>(int)$x['id'],$ls));$d=(int)val('SELECT COUNT(*) FROM pupil_progress WHERE pupil_id=? AND completed_at IS NOT NULL AND lesson_id IN ('.$ids.')',[$pid]);
   echo '<tr><td>'.e(level_label($L).' · '.(string)val('SELECT title FROM bulig_levels WHERE id=?',[$L])).'</td><td>'.$d.' of '.count($ls).'</td><td>'.($d>=count($ls)?'Finished':($d?'In progress':'Started')).'</td></tr>';}
  echo '</tbody></table>';}
 /* Pre-test and post-test (marked by the answer key). */
 $tests=rows("SELECT m.level_id,a.phase,SUM(c.score) s,SUM(c.max_score) mx FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? AND m.level_id IN (6,7,8) AND a.phase IN ('pre','post') AND c.max_score>0 AND c.status IN ('completed','approved') GROUP BY m.level_id,a.phase ORDER BY m.level_id",[$pid]);
 if($tests){$by=[];foreach($tests as $t)$by[(int)$t['level_id']][$t['phase']]=$t;$n=fn($x)=>rtrim(rtrim(number_format((float)$x,2),'0'),'.');
  echo '<h2 class="rp-h">Pre-test and post-test</h2><table class="rp-table"><thead><tr><th>Level</th><th>Pre-test</th><th>Post-test</th><th>Change</th></tr></thead><tbody>';
  foreach($by as $L=>$t){$pre=$t['pre']??null;$post=$t['post']??null;$pp=$pre?100*$pre['s']/$pre['mx']:null;$qp=$post?100*$post['s']/$post['mx']:null;
   echo '<tr><td>'.e(level_label($L)).'</td><td>'.($pre?$n($pre['s']).' / '.$n($pre['mx']).' ('.round($pp).'%)':'Not yet').'</td><td>'.($post?$n($post['s']).' / '.$n($post['mx']).' ('.round($qp).'%)':'Not yet').'</td><td>'.($pp!==null&&$qp!==null?(($qp-$pp)>=0?'+':'').round($qp-$pp).' points':'—').'</td></tr>';}
  echo '</tbody></table>';}
 /* Level 4: oral reading scored with the module's Phil-IRI table. */
 $reads=[];foreach(rows("SELECT c.rubric_scores,l.subtitle,a.phase FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? AND m.level_id=5 AND c.rubric_scores IS NOT NULL ORDER BY l.position,a.position",[$pid]) as $r)if($v=l4_saved_score($r))$reads[]=[$r,$v];
 if($reads){echo '<h2 class="rp-h">Oral reading (Phil-IRI)</h2><table class="rp-table"><thead><tr><th>Passage</th><th>Oral reading score</th><th>Reading level</th></tr></thead><tbody>';
  foreach($reads as [$r,$v])echo '<tr><td>'.e($r['subtitle'].($r['phase']==='pre'?' (pre-test)':($r['phase']==='post'?' (post-test)':''))).'</td><td>'.e((string)$v['score']).'%</td><td>'.e((string)$v['level']).'</td></tr>';
  echo '</tbody></table>';}
 /* The teacher's latest notes. */
 $notes=rows("SELECT c.feedback,a.title,c.reviewed_at FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id=? AND c.feedback IS NOT NULL AND c.feedback<>'' AND c.reviewed_at IS NOT NULL ORDER BY c.reviewed_at DESC LIMIT 4",[$pid]);
 echo '<h2 class="rp-h">Teacher’s notes</h2>';
 if($notes){echo '<ul class="rp-notes">';foreach($notes as $nt)echo '<li><b>'.e($nt['title']).':</b> '.e($nt['feedback']).'</li>';echo '</ul>';}
 echo '<div class="rp-lines" aria-hidden="true"><span></span><span></span></div>';
 echo '<footer class="rp-sign"><div><span></span>Teacher</div><div><span></span>Parent or guardian</div></footer></article>';
}
