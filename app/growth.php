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
 /* A level with a post-test is finished only at 80% or more (app/post_gate.php). */
 return $r&&(int)$r['total']>0&&(int)$r['total']===(int)$r['done']&&(!function_exists('pt_passed')||pt_passed($pid,$level))?(string)$r['last']:null;
}
/** Every level the pupil has finished: [level id => finish date]. */
function finished_levels(int $pid):array{
 /* One query for all levels (it was one per level, for every pupil on the teacher's pages). */
 $out=[];foreach(rows('SELECT m.level_id,COUNT(*) total,COUNT(p.completed_at) done,MAX(p.completed_at) last FROM lessons l JOIN modules m ON m.id=l.module_id JOIN bulig_levels b ON b.id=m.level_id AND b.published=1 LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE l.published=1'.GRADE_SQL.' GROUP BY m.level_id ORDER BY '.level_order_sql('m.level_id'),[$pid,pupil_grade($pid)]) as $r)if((int)$r['total']>0&&(int)$r['total']===(int)$r['done']&&(!function_exists('pt_passed')||pt_passed($pid,(int)$r['level_id'])))$out[(int)$r['level_id']]=(string)$r['last'];
 return $out;
}
function level_cover(int $level):string{if(function_exists('studio_level_cover')&&($sc=studio_level_cover($level))!=='')return $sc;$f='assets/images/covers/level-'.$level.'.webp';return is_file(__DIR__.'/../public/'.$f)?$f:'assets/bulig-quest-logo.png';}

/* ---------- certificates ---------- */
/** v209 A name as it should print: "JUAN DELA CRUZ" or "juan dela cruz" → "Juan Dela Cruz". A word already written in
    mixed letters (McDonald, MAEd) stays as typed; initials, "Jr." and degrees like "Ed.D." are kept right; II, III, IV stay capital. */
function name_case(string $s):string{
 $s=trim(preg_replace('~\s+~u',' ',$s));if($s==='')return '';
 return preg_replace_callback('~[^\s\-]+~u',function($m){$w=$m[0];
  if(preg_match('~^(i{1,3}|iv|v|vi{0,3}|ix|x)[.,]?$~iu',$w))return mb_strtoupper($w);   // roman numerals
  $low=mb_strtolower($w);if($w!==mb_strtoupper($w)&&$w!==$low)return $w;                // mixed case: as typed
  return preg_replace_callback('~(^|[.\'’])(\p{L})~u',fn($x)=>$x[1].mb_strtoupper($x[2]),$low);   // Ed.D., O'Neil
 },$s);
}
/** The school of a pupil (or the default school): [school name, principal, school logo]. */
function cert_school(int $pid):array{
 $s=function_exists('schools_ready')&&schools_ready()?(school_row(user_school_id($pid))??school_row(school_default_id())):null;
 $set=fn($k)=>function_exists('admin_setting')?admin_setting($k,''):'';
 $name=trim((string)($s['name']??''))?:$set('school_name');$head=trim((string)($s['head']??''))?:$set('school_head');
 $logo=(string)($s['logo']??'');if($logo===''||!is_file(__DIR__.'/../public/'.$logo)){$logo=$set('logo_school');if($logo===''||!is_file(__DIR__.'/../public/'.$logo))$logo='';}
 return [$name,name_case($head),$logo];
}
/** v209 The background of the certificate's white part: soft coloured dots, gold stars, faint letters and a green
    wave, so the page is not plain white (light enough to print and read). */
function cert_main_art():string{
 $dots='';foreach([[760,60,40,'#ffd23f'],[790,140,18,'#4dabf7'],[700,30,14,'#ff7a1a'],[60,740,46,'#2f9e44'],[140,770,20,'#da77f2'],[30,660,14,'#ffd23f'],[780,720,30,'#ff7a1a'],[720,770,16,'#2f9e44']] as [$x,$y,$r,$c])$dots.='<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="'.$c.'" opacity=".18"/>';
 $stars='';foreach([[640,90,1.2],[760,230,.9],[90,600,1],[40,500,.7],[700,640,1.1],[380,740,.8],[560,30,.7]] as [$x,$y,$k])$stars.='<path transform="translate('.$x.' '.$y.') scale('.$k.')" d="M12 2l3 6.6 7.2.8-5.4 4.9 1.5 7.1L12 17.8 5.7 21.4l1.5-7.1L1.8 9.4 9 8.6z" fill="#e2b93b" opacity=".35"/>';
 $abc='';foreach([[690,330,60,12,'A'],[740,470,48,-10,'b'],[40,420,52,-12,'c'],[560,720,44,8,'D']] as [$x,$y,$z,$r,$t])$abc.='<text x="'.$x.'" y="'.$y.'" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="'.$z.'" fill="#17633a" opacity=".07" transform="rotate('.$r.' '.$x.' '.$y.')">'.$t.'</text>';
 return '<svg class="cz-bg" viewBox="0 0 823 794" preserveAspectRatio="none" aria-hidden="true"><rect width="823" height="794" fill="#fffbec"/>'.$dots.$stars.$abc
  .'<path d="M0 794V700Q120 660 250 700T520 720T823 690V794Z" fill="#2f9e44" opacity=".1"/></svg>';
}
/** The pictures behind the green band: soft sun rays, sparkles, floating letters, little books and a hill. */
function cert_band_art():string{
 $sp=fn($x,$y,$r,$o)=>'<path d="M'.$x.' '.($y-$r).'l'.($r*.28).' '.($r*.72).' '.($r*.72).' '.($r*.28).'-'.($r*.72).' '.($r*.28).'-'.($r*.28).' '.($r*.72).'-'.($r*.28).'-'.($r*.72).'-'.($r*.72).'-'.($r*.28).' '.($r*.72).'-'.($r*.28).'z" fill="#ffd23f" opacity="'.$o.'"/>';
 $lt=fn($x,$y,$t,$sz,$rot,$o)=>'<text x="'.$x.'" y="'.$y.'" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="'.$sz.'" fill="#fff" opacity="'.$o.'" transform="rotate('.$rot.' '.$x.' '.$y.')">'.$t.'</text>';
 $bk=fn($x,$y,$c,$rot)=>'<g transform="translate('.$x.' '.$y.') rotate('.$rot.')" opacity=".22"><path d="M0 0h16v22H2a2 2 0 0 1-2-2z" fill="'.$c.'"/><path d="M16 0h16v20a2 2 0 0 1-2 2H16z" fill="#fff"/><path d="M16 0v22" stroke="#0a4a26" stroke-width="1"/></g>';
 $rays='';for($i=0;$i<12;$i++)$rays.='<path d="M150 300 L'.round(150+420*cos(deg2rad($i*30)),1).' '.round(300+420*sin(deg2rad($i*30)),1).' L'.round(150+420*cos(deg2rad($i*30+12)),1).' '.round(300+420*sin(deg2rad($i*30+12)),1).'Z" fill="#fff" opacity=".035"/>';
 return '<svg class="cz-art" viewBox="0 0 300 794" preserveAspectRatio="xMidYMid slice" aria-hidden="true">'.$rays
  .'<circle cx="150" cy="300" r="120" fill="#ffd23f" opacity=".07"/><circle cx="150" cy="300" r="70" fill="#ffd23f" opacity=".07"/>'
  .$sp(40,70,10,.7).$sp(262,150,8,.6).$sp(30,420,7,.5).$sp(270,470,11,.65).$sp(60,560,6,.5).$sp(240,40,6,.5).$sp(250,610,7,.45)
  .$lt(18,200,'A',34,-14,.10).$lt(250,250,'b',30,12,.10).$lt(28,330,'c',26,8,.09).$lt(240,380,'D',28,-10,.09).$lt(205,120,'e',22,10,.08).$lt(70,480,'F',22,-8,.08)
  .$bk(232,520,'#ff7a1a',-12).$bk(36,250,'#4dabf7',10).$bk(200,330,'#da77f2',6)
  .'<path d="M0 690 Q80 650 150 672 T300 660 V794 H0Z" fill="#2f9e44" opacity=".45"/><path d="M0 720 Q90 690 170 708 T300 700 V794 H0Z" fill="#46b46b" opacity=".35"/>'
  .'<g fill="#ffd23f" opacity=".5"><circle cx="40" cy="712" r="3"/><circle cx="96" cy="700" r="2.5"/><circle cx="210" cy="716" r="3"/><circle cx="262" cy="704" r="2.5"/></g></svg>';
}
function certificate_html(array $p,int $level,string $date,string $teacher):string{
 $title=(string)val('SELECT title FROM bulig_levels WHERE id=?',[$level]);$title=preg_replace('~\s*·\s*Level \w+$~u','',$title);
 $code=preg_replace('~^Level\s*~','',level_label($level));$pid=(int)($p['id']??0);
 [$school,$head,$slogo]=cert_school($pid);$dlogo=function_exists('division_logo')?division_logo():'';
 $kid=friend_img('jarvis','cheer');   // Jarvis the Robot cheers on every certificate
 $seal='<svg viewBox="0 0 160 160" aria-hidden="true"><g transform="translate(80 80)">'.implode('',array_map(fn($i)=>'<path d="M0-76l9 14H-9z" fill="#c9a227" transform="rotate('.($i*15).')"/>',range(0,23))).'</g>'
  .'<circle cx="80" cy="80" r="62" fill="#e2b93b" stroke="#fff8dc" stroke-width="4"/><circle cx="80" cy="80" r="52" fill="none" stroke="#8a5a00" stroke-width="1.5" stroke-dasharray="3 4"/>'
  .'<text x="80" y="66" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="15" fill="#5a3c00" letter-spacing="2">BULIG</text>'
  .'<text x="80" y="95" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="'.(mb_strlen($code)>2?20:24).'" fill="#0e3b28">LEVEL '.e($code).'</text>'
  .'<text x="80" y="114" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="10" fill="#5a3c00" letter-spacing="1.5">COMPLETED</text></svg>';
 $logo=fn($f,$alt)=>$f!==''?'<img class="cz-lg" src="'.e($f).'" alt="'.e($alt).'">':'<span class="cz-lg cz-slot" aria-hidden="true"></span>';
 $grade='Grade '.e((string)$p['grade_level']).((string)($p['section']??'')!==''?' · '.e(name_case((string)$p['section'])):'');
 $sign=fn($n,$r)=>'<div class="cz-sg"><b>'.($n!==''?e($n):'&nbsp;').'</b><i></i><span>'.$r.'</span></div>';
 return '<article class="cert-page"><div class="cert cz">'
  .'<aside class="cz-side">'.cert_band_art().'<img class="cz-bulig" src="assets/brand/bulig-quest.webp" alt="BULIG Quest"><div class="cz-lvl"><b>'.e($code).'</b><span>LEVEL COMPLETED</span><small>'.e($title).'</small></div><img class="cz-kid" src="'.e($kid).'" alt=""></aside>'
  .'<div class="cz-main">'.cert_main_art().'<img class="cz-mark" src="assets/brand/bulig-quest-mark.webp" alt="" aria-hidden="true">'
  .'<header class="cz-hd"><span class="cz-logos">'.$logo($dlogo,'Division logo').($slogo!==''?$logo($slogo,'School logo'):'').'</span><p>Republic of the Philippines<br><b>Department of Education</b><br>Schools Division of Bukidnon'.($school!==''?'<br><strong>'.e($school).'</strong>':'').'</p></header>'
  .'<h2 class="cz-t">Certificate of Completion</h2><i class="cz-gold"></i>'
  .'<p class="cz-s">This certificate is proudly presented to</p><h3 class="cert-name cz-name">'.e(name_case((string)$p['name'])).'</h3>'
  .'<p class="cz-tx">'.$grade.', for successfully completing <b>'.e(level_label($level)).' · '.e($title).'</b> of the BULIG Quest reading program. Given on '.e(date('F j, Y',strtotime($date))).'.</p>'
  .'<p class="cz-stars" aria-hidden="true">'.str_repeat('<svg viewBox="0 0 24 24"><path d="M12 2l3 6.6 7.2.8-5.4 4.9 1.5 7.1L12 17.8 5.7 21.4l1.5-7.1L1.8 9.4 9 8.6z" fill="#e2b93b" stroke="#c9a227" stroke-width="1"/></svg>',5).'</p>'
  .'<p class="cz-cheer">Congratulations! Keep reading, and keep growing.</p>'
  .'<div class="cz-sigs">'.$sign(name_case($teacher),'Class Adviser').$sign($head,'School Principal').'</div>'
  .'<span class="cz-seal">'.$seal.'</span></div></div></article>';
}
function pupil_teacher_name(int $pid):string{return (string)(val('SELECT u.name FROM teacher_pupils t JOIN users u ON u.id=t.teacher_id WHERE t.pupil_id=? LIMIT 1',[$pid])?:'');}
/** Pupil: their own certificate for one finished level. */
function certificate_view(array $u):void{
 $pid=(int)$u['id'];$level=(int)($_GET['level']??0);$date=level_finished_at($pid,$level);
 if(!$date)fail('Finish every lesson of this level to get its certificate.',404);
 $p=one('SELECT u.id,u.name,p.grade_level,p.section FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.id=?',[$pid]);
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
 $h.='</ul>'.roxanne_goal($n,DAILY_GOAL);
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
 $levels=rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY '.level_order_sql('id'));
 $level=(int)($_GET['level']??0);if(!$level){$starts=array_filter(array_map(fn($p)=>(int)$p['level_id'],$pupils));$level=1;if($starts){usort($starts,fn($x,$y)=>level_rank($x)<=>level_rank($y));$level=$starts[0];}}
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
  echo '<tr><th class="pg-name"><a href="?page=pupil&amp;id='.$id.'">'.e($p['name']).'</a>'.(level_rank((int)$p['level_id'])>level_rank($level)?'<small>starts at '.e(level_label((int)$p['level_id'])).'</small>':'').'</th>'.$cells.'<td class="pg-sum">'.$n.'/'.count($lessons).'</td></tr>';}
 echo '</tbody></table></div><div class="pg-legend"><span><i class="pg-d"></i>Done</span><span><i class="pg-h"></i>Started</span><span><i class="pg-s"></i>No work for 7+ days</span><span><i class="pg-z"></i>Not yet</span></div></section>';
 $table=ob_get_clean();
 echo '<div class="pg-acts"><button type="button" class="btn secondary" data-print>'.icon('print').'Print</button>'.($finished?'<a class="btn secondary" href="?page=certificates&amp;level='.$level.'&amp;section='.$sec.'">'.icon('download').'Certificates ('.$finished.')</a>':'').'</div></div>'.$table;
 echo '<p class="pg-printed">Printed '.e(date('F j, Y')).' · '.e($u['name']).'</p>';
}
