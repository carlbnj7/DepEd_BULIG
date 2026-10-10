<?php
/* Growth features: level completion, printable certificates, the pupil's daily goal ring,
   and the teacher's class progress grid. No new tables: everything is read from progress already saved. */

const DAILY_GOAL=3;

/** Lessons of a level that a pupil of this grade sees, in order. */
function level_lessons(int $level,int $grade):array{
 return rows('SELECT l.id,l.position,l.subtitle,l.title FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1'.GRADE_SQL.' ORDER BY l.position,l.id',[$level,$grade]);
}
/** Date the pupil finished every lesson of a level, or null if the level is not finished. */
function level_finished_at(int $pid,int $level):?string{
 $r=one('SELECT COUNT(*) total,COUNT(p.completed_at) done,MAX(p.completed_at) last FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$pid,$level,pupil_grade($pid)]);
 return $r&&(int)$r['total']>0&&(int)$r['total']===(int)$r['done']?(string)$r['last']:null;
}
/** Every level the pupil has finished: [level id => finish date]. */
function finished_levels(int $pid):array{
 /* One query for all levels (it was one per level, for every pupil on the teacher's pages). */
 $out=[];foreach(rows('SELECT m.level_id,COUNT(*) total,COUNT(p.completed_at) done,MAX(p.completed_at) last FROM lessons l JOIN modules m ON m.id=l.module_id JOIN bulig_levels b ON b.id=m.level_id AND b.published=1 LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE l.published=1'.GRADE_SQL.' GROUP BY m.level_id ORDER BY m.level_id',[$pid,pupil_grade($pid)]) as $r)if((int)$r['total']>0&&(int)$r['total']===(int)$r['done'])$out[(int)$r['level_id']]=(string)$r['last'];
 return $out;
}
function level_cover(int $level):string{$f='assets/images/covers/level-'.$level.'.webp';return is_file(__DIR__.'/../public/'.$f)?$f:'assets/bulig-logo.png';}

/* ---------- certificates ---------- */
function certificate_html(array $p,int $level,string $date,string $teacher):string{
 $title=(string)val('SELECT title FROM bulig_levels WHERE id=?',[$level]);$title=preg_replace('~\s*·\s*Level \w+$~u','',$title);
 $code=preg_replace('~^Level\s*~','',level_label($level));
 /* corner ornament (drawn once, turned for each corner) */
 $corner='<svg viewBox="0 0 120 120" aria-hidden="true"><path d="M6 114V30Q6 6 30 6h84" fill="none" stroke="#c9a227" stroke-width="5"/><path d="M18 114V40q0-22 22-22h74" fill="none" stroke="#176444" stroke-width="2.5"/><circle cx="30" cy="30" r="9" fill="#c9a227"/><circle cx="30" cy="30" r="4" fill="#fffdf5"/><path d="M52 12l4 7 8 1-6 5 2 8-8-4-7 4 2-8-6-5 8-1z" fill="#c9a227"/><path d="M12 52l4 7 8 1-6 5 2 8-8-4-7 4 2-8-6-5 8-1z" fill="#c9a227"/></svg>';
 $star='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 6.6 7.2.8-5.4 4.9 1.5 7.1L12 17.8 5.7 21.4l1.5-7.1L1.8 9.4 9 8.6z" fill="#e2b93b" stroke="#c9a227" stroke-width="1"/></svg>';
 $seal='<svg viewBox="0 0 160 190" aria-hidden="true"><path d="M48 112l-22 70 24-12 14 22 16-62z" fill="#176444"/><path d="M112 112l22 70-24-12-14 22-16-62z" fill="#0e3b28"/>'
  .'<g transform="translate(80 80)">'.implode('',array_map(fn($i)=>'<path d="M0-74l9 14H-9z" fill="#c9a227" transform="rotate('.($i*15).')"/>',range(0,23))).'</g>'
  .'<circle cx="80" cy="80" r="62" fill="#e2b93b" stroke="#fff8dc" stroke-width="4"/><circle cx="80" cy="80" r="52" fill="none" stroke="#8a5a00" stroke-width="1.5" stroke-dasharray="3 4"/>'
  .'<text x="80" y="66" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="800" font-size="15" fill="#5a3c00" letter-spacing="2">BULIG</text>'
  .'<text x="80" y="96" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="800" font-size="26" fill="#0e3b28">LEVEL '.e($code).'</text>'
  .'<text x="80" y="116" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="10" fill="#5a3c00" letter-spacing="1.5">COMPLETED</text></svg>';
 return '<article class="cert-page"><div class="cert cert2">'
  .'<span class="c2-corner tl">'.$corner.'</span><span class="c2-corner tr">'.$corner.'</span><span class="c2-corner bl">'.$corner.'</span><span class="c2-corner br">'.$corner.'</span>'
  .'<div class="cert-in"><img class="c2-mark" src="assets/bulig-logo.png" alt="" aria-hidden="true">'
  .'<div class="c2-top"><img class="cert-logo" src="assets/bulig-logo.png" alt="BULIG"><span class="c2-org">Bukidnon’s Unified Literacy and Intervention Gateway</span></div>'
  .'<div class="c2-ribbon"><span>Certificate of Completion</span></div>'
  .'<p class="cert-small">This certificate is proudly given to</p><h2 class="cert-name">'.e($p['name']).'</h2>'
  .'<p class="cert-small">for successfully completing</p><p class="cert-level">'.e(level_label($level)).' · '.e($title).'</p>'
  .'<p class="c2-stars">'.$star.$star.$star.$star.$star.'</p>'
  .'<p class="cert-meta">Grade '.e((string)$p['grade_level']).((string)$p['section']!==''?' · '.e((string)$p['section']):'').' · Given on '.e(date('F j, Y',strtotime($date))).'</p>'
  .'<div class="cert-sign"><span><b>'.e($teacher!==''?$teacher:' ').'</b>Teacher</span><span><b>&nbsp;</b>School Head</span></div></div>'
  .'<img class="c2-kids" src="'.e(level_cover($level)).'" alt=""><span class="c2-seal">'.$seal.'</span></div></article>';
}
function pupil_teacher_name(int $pid):string{return (string)(val('SELECT u.name FROM teacher_pupils t JOIN users u ON u.id=t.teacher_id WHERE t.pupil_id=? LIMIT 1',[$pid])?:'');}
/** Pupil: their own certificate for one finished level. */
function certificate_view(array $u):void{
 $pid=(int)$u['id'];$level=(int)($_GET['level']??0);$date=level_finished_at($pid,$level);
 if(!$date)fail('Finish every lesson of this level to get its certificate.',404);
 $p=one('SELECT u.name,p.grade_level,p.section FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.id=?',[$pid]);
 echo '<div class="pageheading no-print"><div><span class="eyebrow">MY CERTIFICATE</span><h1>Well done, '.e(explode(' ',trim($u['name']))[0]).'!</h1><p class="muted">Print it or save it as a PDF.</p></div></div><div class="cert-tools no-print"><button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button><a class="btn secondary" href="?page=achievements">'.icon('star').'Back to my achievements</a></div>';
 echo certificate_html($p,$level,$date,pupil_teacher_name($pid));
}
/** Teacher: certificates for every pupil (of one section, or one pupil) who finished a level. */
function certificates_view(array $u):void{
 $tid=(int)$u['id'];$level=(int)($_GET['level']??0);$sec=(int)($_GET['section']??0);$one=(int)($_GET['pupil']??0);
 $sql='SELECT u.id,u.name,p.grade_level,p.section FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1';$args=[$tid];
 if($sec){$sql.=' AND ps.section_id=?';$args[]=$sec;}if($one){$sql.=' AND u.id=?';$args[]=$one;}
 $list=[];foreach(rows($sql.' ORDER BY p.grade_level,p.section,u.name',$args) as $p){$d=level_finished_at((int)$p['id'],$level);if($d)$list[]=[$p,$d];}
 echo '<div class="pageheading no-print"><div><span class="eyebrow">MY PUPILS · CERTIFICATES</span><h1>'.e(level_label($level)).' certificates</h1><p class="muted">'.count($list).' '.(count($list)===1?'pupil has':'pupils have').' finished this level. One certificate per page.</p></div></div><div class="cert-tools no-print">'.($list?'<button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button>':'').'<a class="btn secondary" href="?page=progress'.($sec?'&amp;section='.$sec:'').'&amp;level='.$level.'">'.icon('arrow','flip').'Back to progress</a></div>';
 if(!$list){echo '<section class="card empty-state"><p>No pupil has finished '.e(level_label($level)).' yet.</p></section>';return;}
 foreach($list as [$p,$d])echo certificate_html($p,$level,$d,$u['name']);
}
/** Pupil achievements page: a card with a certificate for each finished level. */
function certificates_card(int $pid):string{
 $done=finished_levels($pid);
 $h='<section class="card cert-card"><div class="section-heading"><h2>'.icon('star').'My certificates</h2></div>';
 if(!$done)return $h.'<p class="muted">Finish every lesson of a level to get a certificate you can print.</p></section>';
 $h.='<div class="cert-list">';foreach($done as $l=>$d)$h.='<a class="cert-item" href="?page=certificate&amp;level='.$l.'"><img src="'.e(level_cover($l)).'" alt=""><span><b>'.e(level_label($l)).'</b><small>Finished '.e(date('M j, Y',strtotime($d))).'</small></span>'.icon('download').'</a>';
 return $h.'</div></section>';
}

/* ---------- daily goal ring (pupil home) ---------- */
function daily_goal_card(int $pid):string{
 $rows=rows('SELECT a.title FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id=? AND c.submitted_at>=CURDATE() AND c.prompt_snapshot<>? ORDER BY c.submitted_at DESC',[$pid,CLASS_DONE_MARK]);
 $n=count($rows);$pct=min(100,(int)round($n/DAILY_GOAL*100));$done=$n>=DAILY_GOAL;
 $h='<section class="card goal-card'.($done?' goal-done':'').'" data-goal="'.date('Y-m-d').'" aria-label="Today’s goal"><div class="goal-ring" data-p="'.$pct.'"><div><b>'.min($n,99).'/'.DAILY_GOAL.'</b><small>today</small></div></div><div class="goal-text"><span class="eyebrow">TODAY’S GOAL</span><h2>'.($done?'Goal reached! Great reading today.':($n?'Keep going! '.(DAILY_GOAL-$n).' more to reach your goal.':'Finish '.DAILY_GOAL.' activities today.')).'</h2><ul>';
 foreach(array_slice($rows,0,DAILY_GOAL) as $r)$h.='<li class="ok">'.icon('check').e($r['title']).'</li>';
 $h.='</ul>';
 if(!$done){$nx=function_exists('next_learning_lesson')?next_learning_lesson($pid):null;
  $h.=$nx?'<a class="btn primary goal-go" href="?page=lesson&amp;id='.(int)$nx['id'].'" data-level="'.(int)$nx['level_id'].'" data-title="'.e($nx['subtitle']?:$nx['title']).'">'.($n?'One more! Keep going':'Start my next lesson').icon('arrow').'</a>'
   :'<a class="btn primary goal-go" href="?page=lessons">'.($n?'One more! Keep going':'Choose a lesson').icon('arrow').'</a>';}
 return $h.'</div></section>';
}

/* ---------- teacher: class progress grid ---------- */
function progress_view(array $u):void{
 $tid=(int)$u['id'];$sections=teacher_sections($tid);
 $count=(int)val('SELECT COUNT(*) FROM teacher_pupils WHERE teacher_id=?',[$tid]);
 echo pupils_tabs('progress',$count);
 if(!$sections){echo '<section class="card empty-state"><p>Add a section and pupils first. Then their progress shows here.</p></section>';return;}
 $sec=(int)($_GET['section']??0);$S=null;foreach($sections as $s)if((int)$s['id']===$sec)$S=$s;if(!$S)$S=$sections[0];$sec=(int)$S['id'];$grade=(int)$S['grade_level'];
 $pupils=rows('SELECT u.id,u.name,a.level_id FROM pupil_sections ps JOIN users u ON u.id=ps.pupil_id JOIN teacher_pupils t ON t.pupil_id=u.id AND t.teacher_id=? LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE ps.section_id=? AND u.active=1 ORDER BY u.name',[$tid,$sec]);
 $levels=rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id');
 $level=(int)($_GET['level']??0);if(!$level){$starts=array_filter(array_map(fn($p)=>(int)$p['level_id'],$pupils));$level=$starts?min($starts):1;}
 $lessons=level_lessons($level,$grade);
 echo '<form method="get" class="card pg-filter"><input type="hidden" name="page" value="progress"><label>Section<select name="section" data-autosubmit>';foreach($sections as $s)echo '<option value="'.(int)$s['id'].'"'.((int)$s['id']===$sec?' selected':'').'>Grade '.(int)$s['grade_level'].' · '.e($s['name']).'</option>';
 echo '</select></label><label>Level<select name="level" data-autosubmit>';foreach($levels as $l)echo '<option value="'.(int)$l['id'].'"'.((int)$l['id']===$level?' selected':'').'>'.e(level_label((int)$l['id'])).'</option>';echo '</select></label><button class="btn secondary pg-go">Show</button></form>';
 if(!$pupils||!$lessons){echo '<section class="card empty-state"><p>'.(!$pupils?'This section has no pupils yet.':'This level has no lessons for Grade '.$grade.' yet.').'</p></section>';return;}
 $pids=array_map(fn($p)=>(int)$p['id'],$pupils);$lids=array_map(fn($l)=>(int)$l['id'],$lessons);$in=fn($a)=>implode(',',$a);
 $done=[];foreach(rows('SELECT pupil_id,lesson_id,completed_at FROM pupil_progress WHERE pupil_id IN ('.$in($pids).') AND lesson_id IN ('.$in($lids).')') as $r)if($r['completed_at'])$done[$r['pupil_id'].'-'.$r['lesson_id']]=1;
 $last=[];foreach(rows('SELECT c.pupil_id,a.lesson_id,MAX(c.submitted_at) t FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id IN ('.$in($pids).') AND a.lesson_id IN ('.$in($lids).') GROUP BY c.pupil_id,a.lesson_id') as $r)$last[$r['pupil_id'].'-'.$r['lesson_id']]=strtotime((string)$r['t']);
 $stale=time()-7*86400;$finished=0;
 echo '<section class="card pg-card"><div class="section-heading"><div><h2>'.e(level_label($level)).' · Grade '.$grade.' · '.e($S['name']).'</h2><p class="muted">'.count($pupils).' pupils × '.count($lessons).' lessons. Tap a pupil to open their learning path.</p></div>';
 ob_start();
 echo '<div class="pg-scroll"><table class="pg-table"><thead><tr><th class="pg-name">Pupil</th>';foreach($lessons as $i=>$l)echo '<th title="'.e(lesson_display_label($level,(int)$l['position']).' · '.$l['subtitle']).'">'.($i+1).'</th>';echo '<th class="pg-sum">Done</th></tr></thead><tbody>';
 foreach($pupils as $p){$id=(int)$p['id'];$n=0;$cells='';
  foreach($lessons as $l){$k=$id.'-'.(int)$l['id'];
   if(isset($done[$k])){$c='d';$t='Done';$n++;}elseif(isset($last[$k])){$c=$last[$k]<$stale?'s':'h';$t=$c==='s'?'Started, no work for 7+ days':'Started';}else{$c='z';$t='Not yet';}
   $cells.='<td class="pg-c pg-'.$c.'" title="'.e($p['name'].' · '.$l['subtitle'].': '.$t).'"><span class="sr-only">'.e($t).'</span></td>';}
  if($n===count($lessons))$finished++;
  echo '<tr><th class="pg-name"><a href="?page=pupil&amp;id='.$id.'">'.e($p['name']).'</a>'.((int)$p['level_id']>$level?'<small>starts at '.e(level_label((int)$p['level_id'])).'</small>':'').'</th>'.$cells.'<td class="pg-sum">'.$n.'/'.count($lessons).'</td></tr>';}
 echo '</tbody></table></div><div class="pg-legend"><span><i class="pg-d"></i>Done</span><span><i class="pg-h"></i>Started</span><span><i class="pg-s"></i>No work for 7+ days</span><span><i class="pg-z"></i>Not yet</span></div></section>';
 $table=ob_get_clean();
 echo ($finished?'<a class="btn secondary" href="?page=certificates&amp;level='.$level.'&amp;section='.$sec.'">'.icon('download').'Certificates ('.$finished.')</a>':'').'</div>'.$table;
}
