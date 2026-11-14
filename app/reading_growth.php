<?php
/* v216 Reading Growth: how each pupil's reading changed, from real data only (nothing is estimated).
   - Pre-test → post-test: the same level's pre-test and post-test, marked with the module answer keys
     (Level 4 = the oral reading score of the pre-test and post-test passages), as the Section report counts them.
   - Started at → now: the starting level (reading check or the teacher's choice) and the level of the pupil's next lesson.
   - Words per minute and accuracy (oral reading score): the pupil's first and latest Level 4 Fluency reading. Every
     reading is kept in fluency_log (049_fluency_log.sql); readings saved before that come from the answers themselves.
   A pupil without both numbers shows "not yet". Pages: ?page=growth (a section), ?page=pupil&tab=growth (one pupil),
   ?page=growth_print (A4 for the panel) and ?page=growth_csv (Excel). */

function rg_log_ready():bool{static $r;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'fluency_log'");}catch(Throwable $e){$r=false;}}return $r;}
/** Keep one Level 4 reading (called when BULIG Quest scores it). */
function rg_log_reading(int $pid,int $aid,array $s):void{
 if(!rg_log_ready()||!isset($s['wpm'],$s['score']))return;
 q('INSERT INTO fluency_log(pupil_id,activity_id,wpm,accuracy,reading_level,secs) VALUES(?,?,?,?,?,?)',[$pid,$aid,(int)round((float)$s['wpm']),round((float)$s['score'],1),mb_substr((string)($s['level']??''),0,20),max(0,(int)($s['secs']??0))]);
}
/** The pupil's Level 4 readings, oldest first: [when, wpm, accuracy, level]. */
function rg_readings(int $pid):array{
 $out=[];
 if(rg_log_ready())foreach(rows('SELECT wpm,accuracy,reading_level,created_at FROM fluency_log WHERE pupil_id=? ORDER BY id',[$pid]) as $r)$out[]=['t'=>(string)$r['created_at'],'wpm'=>(int)$r['wpm'],'acc'=>(float)$r['accuracy'],'lv'=>(string)$r['reading_level']];
 if($out)return $out;
 foreach(rows("SELECT c.rubric_scores,c.submitted_at FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? AND m.level_id=5 AND c.status IN ('completed','approved') AND c.rubric_scores IS NOT NULL ORDER BY c.submitted_at,c.id",[$pid]) as $r){
  $s=function_exists('l4_saved_score')?l4_saved_score($r):null;if(!$s||!isset($s['wpm'],$s['score']))continue;
  $out[]=['t'=>(string)$r['submitted_at'],'wpm'=>(int)round((float)$s['wpm']),'acc'=>round((float)$s['score'],1),'lv'=>(string)($s['level']??'')];}
 return $out;
}
/** Levels from the starting level on, with the pupil's pre-test and post-test % (null = not yet). */
function rg_levels(int $pid):array{
 $g=pupil_grade($pid);$start=pupil_start_level($pid);$out=[];
 foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY '.level_order_sql('id')) as $l){$L=(int)$l['id'];
  if($start&&level_rank($L)<level_rank($start))continue;
  $pre=sr_phase_pct($pid,$L,$g,'pre');$post=sr_phase_pct($pid,$L,$g,'post');if($pre===null&&$post===null)continue;
  $out[]=['level'=>$L,'pre'=>$pre,'post'=>$post];}
 return $out;
}
/** Everything about one pupil. */
function rg_pupil(int $pid):array{
 $start=pupil_start_level($pid);$nx=next_learning_lesson($pid);$now=$nx?(int)$nx['level_id']:0;
 if(!$now){$r=one('SELECT m.level_id FROM pupil_progress p JOIN lessons l ON l.id=p.lesson_id JOIN modules m ON m.id=l.module_id WHERE p.pupil_id=? AND p.completed_at IS NOT NULL ORDER BY p.completed_at DESC LIMIT 1',[$pid]);$now=$r?(int)$r['level_id']:$start;}
 $lv=rg_levels($pid);$both=array_values(array_filter($lv,fn($x)=>$x['pre']!==null&&$x['post']!==null));
 $rd=rg_readings($pid);$first=$rd[0]??null;$last=count($rd)>1?$rd[count($rd)-1]:null;
 return ['start'=>$start,'now'=>$now,'up'=>$start&&$now?max(0,level_rank($now)-level_rank($start)):null,'levels'=>$lv,
  'pre'=>$both?round(array_sum(array_column($both,'pre'))/count($both),1):null,'post'=>$both?round(array_sum(array_column($both,'post'))/count($both),1):null,'nboth'=>count($both),
  'reads'=>$rd,'wpm0'=>$first['wpm']??null,'wpm1'=>$last['wpm']??null,'acc0'=>$first['acc']??null,'acc1'=>$last['acc']??null,'lv0'=>$first['lv']??null,'lv1'=>$last['lv']??null];
}
/** A section's pupils with their growth. */
function rg_section(array $sec,int $tid):array{
 $P=rows('SELECT u.id,u.name,d.lrn FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id AND t.teacher_id=? LEFT JOIN pupil_details d ON d.pupil_id=u.id WHERE ps.section_id=? AND u.active=1 ORDER BY u.name',[$tid,(int)$sec['id']]);
 foreach($P as $i=>&$p){$p['no']=$i+1;$p+=rg_pupil((int)$p['id']);}unset($p);return $P;
}
function rg_avg(array $P,string $k):?float{$v=array_values(array_filter(array_column($P,$k),fn($x)=>$x!==null));return $v?round(array_sum($v)/count($v),1):null;}
/** Averages over the pupils who have both numbers (so "before" and "after" are the same pupils). */
function rg_pair(array $P,string $a,string $b):array{$Q=array_values(array_filter($P,fn($p)=>$p[$a]!==null&&$p[$b]!==null));return [rg_avg($Q,$a),rg_avg($Q,$b),count($Q)];}
function rg_n($v,string $u=''):string{return $v===null?'—':rtrim(rtrim(number_format((float)$v,1),'0'),'.').$u;}
function rg_lv(int $id):string{return $id?str_replace('Level ','',level_label($id)):'—';}
function rg_arrow($a,$b,string $u=''):string{
 if($a===null||$b===null)return '<span class="rg-na">not yet</span>';$d=round((float)$b-(float)$a,1);
 return '<span class="rg-ar">'.e(rg_n($a,$u)).'<i aria-hidden="true">→</i><b>'.e(rg_n($b,$u)).'</b><span class="'.($d>=0?'rg-up':'rg-dn').'">'.($d>=0?'+':'').e(rg_n($d,$u)).'</span></span>';}
function rg_kpis(array $P):string{
 [$a,$b,$n]=rg_pair($P,'pre','post');[$w0,$w1,$wn]=rg_pair($P,'wpm0','wpm1');[$c0,$c1,$cn]=rg_pair($P,'acc0','acc1');
 $ups=array_values(array_filter(array_column($P,'up'),fn($x)=>$x!==null));$moved=count(array_filter($ups,fn($x)=>$x>0));
 $k=fn($big,$lab,$sub)=>'<div class="rg-kpi"><b>'.$big.'</b><small>'.e($lab).'</small><span>'.$sub.'</span></div>';
 $sg=fn($x)=>$x===null?'—':(($x>0?'+':'').rg_n($x));
 return '<div class="rg-kpis">'
  .$k($a===null?'—':e($sg(round($b-$a,1)).' pts'),'Pre-test → post-test',$a===null?'Not yet: no pupil has both tests of a level':e(rg_n($a,'%').' → '.rg_n($b,'%').' · '.$n.' '.($n===1?'pupil':'pupils')))
  .$k($ups?e($sg(round(array_sum($ups)/count($ups),1))):'—','Levels moved up (average)',$ups?e($moved.' of '.count($ups).' '.(count($ups)===1?'pupil':'pupils').' moved up'):'Not yet')
  .$k($w0===null?'—':e($sg(round($w1-$w0,1))),'Words correct per minute',$w0===null?'Not yet: needs two Level 4 readings':e(rg_n($w0).' → '.rg_n($w1).' · '.$wn.' '.($wn===1?'pupil':'pupils')))
  .$k($c0===null?'—':e($sg(round($c1-$c0,1)).'%'),'Reading accuracy',$c0===null?'Not yet: needs two Level 4 readings':e(rg_n($c0,'%').' → '.rg_n($c1,'%')))
  .'</div>';
}
function rg_pick_section(array $u):?array{
 $secs=teacher_sections((int)$u['id']);if(!$secs)return null;$sid=(int)($_GET['section']??0);
 foreach($secs as $s)if((int)$s['id']===$sid)return $s;return $secs[0];
}
function rg_note():string{return 'Pre-test and post-test: the same level’s pre-test and post-test, marked by BULIG Quest with the module answer keys (Level 4: the oral reading score). Started at: the reading check or the level you chose. Now: the level of the pupil’s next lesson. Words per minute and accuracy: the first and the latest Level 4 Fluency reading, heard by BULIG Quest (speech recognition; check them in Verify readings). “Not yet” means the pupil does not have both numbers yet.';}

/* ---------- the section page ---------- */
function growth_view(array $u):void{
 $tid=(int)$u['id'];$sec=rg_pick_section($u);
 if(!$sec){echo hm_head('TEACHING · READING GROWTH','Reading growth','How your pupils’ reading changes over time.').'<section class="card"><p class="muted">Add a section and pupils first (My class › Sections). Reading growth shows here when your pupils answer pre-tests and post-tests.</p></section>';return;}
 $P=rg_section($sec,$tid);$q='section='.(int)$sec['id'];
 $opts='';foreach(teacher_sections($tid) as $s)$opts.='<option value="'.(int)$s['id'].'"'.((int)$s['id']===(int)$sec['id']?' selected':'').'>Grade '.(int)$s['grade_level'].' · '.e($s['name']).'</option>';
 echo hm_head('TEACHING · READING GROWTH','Reading growth','How each pupil’s reading changed: the same pre-test and post-test of a level, the level they started at and the level they are on now, and their reading speed and accuracy in Level 4 Fluency (first and latest reading).',
  '<a class="btn secondary" href="?page=growth_print&amp;'.$q.'">'.icon('print').'Print for the panel</a><a class="btn secondary" href="?page=growth_csv&amp;'.$q.'">'.icon('excel').'Download Excel</a>');
 echo '<section class="card rg-card"><form method="get" class="rg-filter"><input type="hidden" name="page" value="growth"><label>Section<select name="section" data-autosubmit>'.$opts.'</select></label><noscript><button class="btn secondary small">Show</button></noscript></form>';
 echo rg_kpis($P);
 echo '<div class="tablewrap"><table class="rg-t"><thead><tr><th>Pupil</th><th>Started at → now</th><th>Pre-test → post-test</th><th>Words per minute</th><th>Accuracy</th></tr></thead><tbody>';
 foreach($P as $p)echo '<tr><td><a href="?page=pupil&amp;id='.(int)$p['id'].'&amp;tab=growth">'.e($p['name']).'</a></td><td><span class="rg-lv">'.e(rg_lv((int)$p['start'])).'</span><i class="rg-to" aria-hidden="true">→</i><span class="rg-lv now">'.e(rg_lv((int)$p['now'])).'</span>'.($p['up']?'<span class="rg-up">+'.(int)$p['up'].'</span>':'').'</td><td>'.rg_arrow($p['pre'],$p['post'],'%').'</td><td>'.($p['wpm0']===null?'<span class="rg-na">'.($p['reads']?'one reading so far':'no Level 4 reading yet').'</span>':rg_arrow($p['wpm0'],$p['wpm1'])).'</td><td>'.($p['acc0']===null?'<span class="rg-na">'.($p['reads']?'one reading so far':'no Level 4 reading yet').'</span>':rg_arrow($p['acc0'],$p['acc1'],'%')).'</td></tr>';
 if(!$P)echo '<tr><td colspan="5" class="rg-na">No pupils in this section yet.</td></tr>';
 echo '</tbody></table></div><p class="muted rg-src">'.e(rg_note()).'</p></section>';
}

/* ---------- one pupil (My pupils › pupil › Reading growth) ---------- */
function growth_pupil_tab(int $pid,string $first):string{
 $p=rg_pupil($pid);
 $h='<section class="card rg-card"><div class="section-heading"><div><h2>Reading growth</h2><p class="muted">Started at '.e($p['start']?level_label($p['start']):'—').' · now at '.e($p['now']?level_label($p['now']):'—').($p['up']?' · '.(int)$p['up'].' '.($p['up']===1?'level':'levels').' up':'').'</p></div></div><div class="rg-two">';
 $h.='<div class="rg-box"><h3>Pre-test and post-test</h3>';
 if(!$p['levels'])$h.='<p class="rg-na">Not yet. '.e($first).' has not answered a pre-test.</p>';
 else{$h.='<div class="rg-bars">';foreach($p['levels'] as $l){$a=$l['pre'];$b=$l['post'];
   $h.='<div class="rg-row"><b>'.e(level_label($l['level'])).'</b><div class="rg-bar">'.($a!==null?'<span class="pre w'.(int)(round($a/5)*5).'">'.e(rg_n($a,'%')).'</span>':'').($b!==null?'<span class="post w'.(int)(round($b/5)*5).'">'.e(rg_n($b,'%')).'</span>':'').'</div>'.($a!==null&&$b!==null?'<span class="'.($b>=$a?'rg-up':'rg-dn').'">'.($b>=$a?'+':'').e(rg_n(round($b-$a,1))).'</span>':'<span class="rg-na">'.($a===null?'pre-test not yet':'post-test not yet').'</span>').'</div>';}
  $h.='</div><div class="rg-leg"><span><i class="pre"></i>Pre-test</span><span><i class="post"></i>Post-test</span></div>';}
 $h.='</div><div class="rg-box"><h3>Level 4 Fluency: words per minute</h3>';
 if(!$p['reads'])$h.='<p class="rg-na">Not yet. Words per minute show after '.e($first).' reads in Level 4 Fluency.</p>';
 else{$R=array_slice($p['reads'],-8);$mx=max(1,max(array_column($R,'wpm')));$h.='<div class="rg-flu">';
  foreach($R as $r)$h.='<div class="h'.(int)(round($r['wpm']/$mx*20)*5).'" title="'.e(date('M j, Y',strtotime($r['t'])).' · '.$r['wpm'].' words per minute · '.rg_n($r['acc'],'%')).'"><span>'.(int)$r['wpm'].'</span></div>';
  $h.='</div><div class="rg-flux">';foreach($R as $i=>$r)$h.='<span>'.e(date('M j',strtotime($r['t']))).'</span>';$h.='</div>';
  $h.='<p class="rg-acc"><b>Accuracy:</b> '.($p['acc0']===null?e(rg_n($p['reads'][0]['acc'],'%')).' <span class="rg-na">(one reading so far)</span>':rg_arrow($p['acc0'],$p['acc1'],'%')).($p['lv1']?' · <b>Reading level:</b> '.e((string)$p['lv0']).' → <b>'.e((string)$p['lv1']).'</b>':'').'</p>';}
 return $h.'</div></div><p class="muted rg-src">'.e(rg_note()).'</p></section>';
}

/* ---------- printed (A4, DepEd band like the Section report) ---------- */
function growth_print_view(array $u):void{
 $tid=(int)$u['id'];$sec=rg_pick_section($u);if(!$sec)fail('Add a section first.',400);$P=rg_section($sec,$tid);$t=(string)$u['name'];
 $set=fn($k,$d='')=>function_exists('admin_setting')?admin_setting($k,$d):$d;$div=trim($set('division_name','Division of Bukidnon'))?:'Division of Bukidnon';
 echo '<div class="no-print rc-actions"><div><span class="eyebrow">READING GROWTH REPORT</span><h1>Grade '.(int)$sec['grade_level'].' – '.e($sec['name']).'</h1><p class="muted">'.count($P).' '.(count($P)===1?'pupil':'pupils').'. Print or save as PDF, then sign.</p></div><div class="rc-btns"><a class="btn secondary" href="?page=growth&amp;section='.(int)$sec['id'].'">'.icon('arrow','flip').'Back</a><button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button></div></div>';
 [$a,$b,$n]=rg_pair($P,'pre','post');[$w0,$w1]=rg_pair($P,'wpm0','wpm1');[$c0,$c1]=rg_pair($P,'acc0','acc1');$ups=array_values(array_filter(array_column($P,'up'),fn($x)=>$x!==null));
 $h='<div class="rc-band">'.rc_logo('division').'<div class="rc-mid">Republic of the Philippines<b>Department of Education · Region X</b>'.e($div).'</div>'.rc_logo('school').'</div>'.rc_tribal(10,'rgt'.(int)$sec['id'])
  .'<div class="sd-title"><img src="assets/brand/bulig-quest.webp" alt="BULIG Quest"><div><h2>Reading Growth Report</h2><p>How each pupil’s reading changed</p></div></div>'
  .'<div class="sd-info"><span><small>GRADE &amp; SECTION</small><b>Grade '.(int)$sec['grade_level'].' – '.e($sec['name']).'</b></span><span><small>READING TEACHER</small><b>'.e($t).'</b></span><span><small>SCHOOL YEAR</small><b>'.e(function_exists('admin_school_year')?admin_school_year():'').'</b></span><span><small>PRINTED</small><b>'.date('F j, Y').'</b></span></div>';
 $h.=sr_tiles([['Pre-test → post-test',$a===null?'—':e(rg_n($a,'%').' → '.rg_n($b,'%')),$a===null?'not yet':'average · '.$n.' '.($n===1?'pupil':'pupils')],['Levels moved up',$ups?e(rg_n(round(array_sum($ups)/count($ups),1))):'—','average'],['Words correct per minute',$w0===null?'—':e(rg_n($w0).' → '.rg_n($w1)),'first → latest (Level 4)'],['Reading accuracy',$c0===null?'—':e(rg_n($c0,'%').' → '.rg_n($c1,'%')),'first → latest (Level 4)']],' rg-4');
 $h.='<table class="sd-tbl rg-ptbl"><thead><tr><th>No.</th><th class="l">Name of pupil</th><th>Started at</th><th>Now at</th><th>Pre-test</th><th>Post-test</th><th>Change</th><th>WPM first</th><th>WPM latest</th><th>Accuracy first</th><th>Accuracy latest</th></tr></thead><tbody>';
 foreach($P as $p){$d=$p['pre']!==null&&$p['post']!==null?round($p['post']-$p['pre'],1):null;
  $h.='<tr><td>'.$p['no'].'</td><td class="l"><b>'.e($p['name']).'</b></td><td>'.e(rg_lv((int)$p['start'])).'</td><td>'.e(rg_lv((int)$p['now'])).'</td><td>'.e(rg_n($p['pre'],'%')).'</td><td>'.e(rg_n($p['post'],'%')).'</td><td>'.($d===null?'—':($d>=0?'+':'').e(rg_n($d))).'</td><td>'.e(rg_n($p['wpm0'])).'</td><td>'.e(rg_n($p['wpm1'])).'</td><td>'.e(rg_n($p['acc0'],'%')).'</td><td>'.e(rg_n($p['acc1'],'%')).'</td></tr>';}
 if(!$P)$h.='<tr><td colspan="11">No pupils in this section yet.</td></tr>';
 $h.='</tbody></table><p class="sd-note">'.e(rg_note()).' Pre-test and post-test are averages over the levels where the pupil has both. “—” = not yet.</p>'.sr_signs($t);
 echo '<div class="sd-wrap" data-pages="1"><section class="sd-page rg-sd">'.$h.'<div class="sd-foot"><span>BULIG Quest · Read, Play, Grow</span><span class="sd-pn"></span></div></section></div>';
}
function growth_csv(array $u):never{
 $tid=(int)$u['id'];$sec=rg_pick_section($u);if(!$sec)fail('Add a section first.',400);$P=rg_section($sec,$tid);
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="reading-growth-grade'.(int)$sec['grade_level'].'-'.preg_replace('/[^A-Za-z0-9]+/','-',(string)$sec['name']).'-'.date('Y-m-d').'.csv"');
 $h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");
 fputcsv($h,['No.','Pupil','LRN','Started at','Now at','Levels up','Pre-test %','Post-test %','Change','Levels with both tests','WPM first','WPM latest','Accuracy first %','Accuracy latest %','Reading level first','Reading level latest','Level 4 readings'],',','"','\\');
 foreach($P as $p)fputcsv($h,[$p['no'],$p['name'],(string)($p['lrn']??''),rg_lv((int)$p['start']),rg_lv((int)$p['now']),$p['up']??'',$p['pre']??'',$p['post']??'',$p['pre']!==null&&$p['post']!==null?round($p['post']-$p['pre'],1):'',$p['nboth'],$p['wpm0']??'',$p['wpm1']??'',$p['acc0']??'',$p['acc1']??'',$p['lv0']??'',$p['lv1']??'',count($p['reads'])],',','"','\\');
 fclose($h);exit;
}

/* ---------- v216 the whole school or area: pre-test to post-test by grade (administrators) ---------- */
/** $where limits pupils (alias u), for example " AND u.school_id IN (1,2)". Averages of every answered pre-test and
    post-test activity (right answers ÷ points; Level 4 = the oral reading score), by the pupil's grade. */
function growth_by_grade_card(string $where,string $link=''):string{
 $R=[];foreach(rows("SELECT p.grade_level g,a.phase,SUM(c.score) s,SUM(c.max_score) m,COUNT(DISTINCT c.pupil_id) n FROM activity_completion c JOIN activities a ON a.id=c.activity_id AND a.phase IN ('pre','post') JOIN users u ON u.id=c.pupil_id AND u.active=1 AND u.role='pupil' JOIN pupils p ON p.user_id=u.id WHERE c.status IN ('completed','approved') AND c.max_score>0".$where." GROUP BY p.grade_level,a.phase") as $r){$g=(int)$r['g'];$R[$g][$r['phase']]=['pct'=>round((float)$r['s']/(float)$r['m']*100),'n'=>(int)$r['n']];}
 ksort($R);$rows='';$both=0;$tp=0;
 foreach($R as $g=>$x){$a=$x['pre']['pct']??null;$b=$x['post']['pct']??null;$tp+=$x['post']['n']??0;if($a!==null&&$b!==null)$both++;
  $rows.='<div class="rg-row"><b>Grade '.$g.'</b><div class="rg-bar">'.($a!==null?'<span class="pre w'.(int)(round($a/5)*5).'">'.$a.'%</span>':'').($b!==null?'<span class="post w'.(int)(round($b/5)*5).'">'.$b.'%</span>':'').'</div>'.($a!==null&&$b!==null?'<span class="'.($b>=$a?'rg-up':'rg-dn').'">'.($b>=$a?'+':'').($b-$a).'</span>':'<span class="rg-na">'.($a===null?'no pre-test yet':'no post-test yet').'</span>').'</div>';}
 $h='<section class="card rg-card rg-school"><div class="ws-hd"><h2>'.icon('chart').'Reading growth by grade</h2>'.($link!==''?'<a class="btn secondary small" href="'.$link.'">'.icon('print').'Reports</a>':'').'</div>';
 if(!$R)return $h.'<p class="rg-na">Not yet. This fills in when pupils answer pre-tests and post-tests.</p></section>';
 return $h.'<div class="rg-bars">'.$rows.'</div><div class="rg-leg"><span><i class="pre"></i>Pre-test</span><span><i class="post"></i>Post-test</span></div><div class="qx-chips"><span>'.number_format($tp).' '.($tp===1?'pupil':'pupils').' took a post-test</span></div><p class="muted rg-src">Averages of every answered pre-test and post-test, marked with the module answer keys. Pupils who answered only one of them still count in that bar.</p></section>';
}
