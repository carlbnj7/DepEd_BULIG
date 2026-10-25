<?php
/* Section data report (Print centre): all the results of one section in one level, on A4 pages with the DepEd band
   of the report card (rc_logo, rc_tribal). The teacher ticks the pages:
   sum   Section Level Summary: lessons done, pre-test, post-test, gain, reading level, passed 80%, status
   pp    Pre-test and Post-test: a dot chart per pupil, and the reading levels (Level 4) or pass counts before and after
   irl   Phil-IRI Class Reading Profile (Level 4 only): miscues by type, oral reading score, level, words per minute
   rt    What to Re-teach: Level 4 = the section's miscues and hardest passages; other levels = the questions most
         pupils missed (answer keys) with the wrong answer chosen most
   help  Pupils Needing Help: under 80%, Frustration, not active for 7+ days, asked for help, with a next step
   act   Activity and Attendance: school days used, activities done, last sign-in and answers sent offline (one month)
   Pre-test and post-test: Level 4 = the oral reading score of the pre-test and post-test passages; other levels =
   right answers ÷ points of the level's pre-test and post-test activities (the post-test as pt_result()). */

const SR_PARTS=['sum'=>'Level summary','pp'=>'Pre-test and post-test','irl'=>'Phil-IRI reading profile','rt'=>'What to re-teach','help'=>'Pupils needing help','act'=>'Activity and attendance'];

/** Score % of one phase of a level for a pupil: null when nothing is answered (post-test: until every activity is). */
function sr_phase_pct(int $pid,int $level,int $grade,string $phase):?float{
 $acts=rows("SELECT a.id,a.type,a.lesson_id FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND a.phase=? AND a.published=1 AND l.published=1 AND IFNULL(a.response_mode,'answer')<>'none'".GRADE_SQL,[$level,$phase,$grade]);
 if(!$acts)return null;$right=0.0;$total=0.0;$done=0;
 foreach($acts as $a){$c=one("SELECT id,status,score,max_score,rubric_scores FROM activity_completion WHERE pupil_id=? AND activity_id=? AND status IN ('completed','approved')",[$pid,(int)$a['id']]);if(!$c)continue;$done++;
  if($level===5){$s=l4_saved_score($c);if($s){$right+=(float)$s['score'];$total+=100;}continue;}
  if($c['max_score']===null){key_save_score((int)$c['id']);$c=one('SELECT score,max_score FROM activity_completion WHERE id=?',[(int)$c['id']]);}
  if((float)($c['max_score']??0)>0){$right+=(float)$c['score'];$total+=(float)$c['max_score'];}}
 if(!$done||($phase==='post'&&$done<count($acts))||$total<=0)return null;
 return round($right/$total*100,1);
}
/** The Level 4 score saved on the pupil's reading of one phase (the last passage of that phase). */
function sr_l4(int $pid,int $grade,string $phase):?array{
 $c=one("SELECT c.rubric_scores FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? AND m.level_id=5 AND m.grade_level=? AND a.phase=? AND a.type='reading' AND c.status IN ('completed','approved') ORDER BY a.position DESC LIMIT 1",[$pid,$grade,$phase]);
 return $c?l4_saved_score($c):null;
}
/** Everything the pages need, for one section and level. */
function sr_data(array $sec,int $level,int $tid,string $month):array{
 $g=(int)$sec['grade_level'];$lessons=level_lessons($level,$g);$lids=array_map(fn($l)=>(int)$l['id'],$lessons);$n=count($lessons);
 $pupils=rows('SELECT u.id,u.name,d.lrn FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id AND t.teacher_id=? LEFT JOIN pupil_details d ON d.pupil_id=u.id WHERE ps.section_id=? AND u.active=1 ORDER BY u.name',[$tid,(int)$sec['id']]);
 $logins=function_exists('admin_last_logins')?admin_last_logins():[];
 [$y,$mo]=array_map('intval',explode('-',$month));$from=sprintf('%04d-%02d-01',$y,$mo);$to=date('Y-m-t',strtotime($from));$last=min($to,date('Y-m-d'));
 $days=[];for($d=strtotime($from);$d<=strtotime($last);$d+=86400)if((int)date('N',$d)<=5)$days[]=date('Y-m-d',$d);
 foreach($pupils as $i=>&$p){$pid=(int)$p['id'];$p['no']=$i+1;
  $p['done']=$lids?(int)val('SELECT COUNT(*) FROM pupil_progress WHERE pupil_id=? AND completed_at IS NOT NULL AND lesson_id IN ('.implode(',',$lids).')',[$pid]):0;$p['of']=$n;
  if($level===5){$a=sr_l4($pid,$g,'pre');$b=sr_l4($pid,$g,'post');$p['pre']=$a?(float)$a['score']:null;$p['post']=$b?(float)$b['score']:null;$p['preLv']=$a['level']??null;$p['lv']=$b['level']??null;$p['l4']=$b;}
  else{$p['pre']=sr_phase_pct($pid,$level,$g,'pre');$p['post']=sr_phase_pct($pid,$level,$g,'post');$p['preLv']=$p['lv']=null;$p['l4']=null;}
  $p['gain']=$p['pre']!==null&&$p['post']!==null?round($p['post']-$p['pre'],1):null;
  $p['finished']=$n>0&&$p['done']>=$n;
  $p['act']=array_flip(array_column(rows('SELECT day FROM learning_days WHERE pupil_id=? AND day BETWEEN ? AND ?',[$pid,$from,$last]),'day'));
  $p['lastday']=val('SELECT MAX(day) FROM learning_days WHERE pupil_id=?',[$pid]);
  $p['acts']=(int)val("SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND submitted_at BETWEEN ? AND ?",[$pid,$from.' 00:00:00',$last.' 23:59:59']);
  $p['login']=$logins[$pid]??null;
  $p['off']=(int)val("SELECT COUNT(*) FROM audit_log WHERE actor_id=? AND action='offline_answer' AND created_at BETWEEN ? AND ?",[$pid,$from.' 00:00:00',$last.' 23:59:59']);
  $p['help']=$lids?(int)val('SELECT COUNT(*) FROM help_requests WHERE pupil_id=? AND lesson_id IN ('.implode(',',$lids).')',[$pid]):0;}
 unset($p);
 return ['sec'=>$sec,'grade'=>$g,'level'=>$level,'lessons'=>$lessons,'pupils'=>$pupils,'days'=>$days,'from'=>$from,'last'=>$last];
}

/* ---------- page pieces ---------- */
function sr_head(array $D,string $title,string $sub,string $teacher):string{
 $set=fn($k,$d='')=>function_exists('admin_setting')?admin_setting($k,$d):$d;$div=trim($set('division_name','Division of Bukidnon'))?:'Division of Bukidnon';
 return '<div class="rc-band">'.rc_logo('division').'<div class="rc-mid">Republic of the Philippines<b>Department of Education · Region X</b>'.e($div).'</div>'.rc_logo('school').'</div>'.rc_tribal(10,'srt'.substr(md5($title),0,6))
  .'<div class="sd-title"><img src="assets/brand/bulig-gamified.webp" alt="BULIG"><div><h2>'.e($title).'</h2><p>'.e($sub).'</p></div></div>'
  .'<div class="sd-info"><span><small>LEVEL</small><b>'.e(level_label($D['level'])).'</b></span><span><small>GRADE &amp; SECTION</small><b>Grade '.$D['grade'].' – '.e($D['sec']['name']).'</b></span><span><small>READING TEACHER</small><b>'.e($teacher).'</b></span><span><small>SCHOOL YEAR</small><b>'.e(function_exists('admin_school_year')?admin_school_year():'').'</b></span><span><small>PRINTED</small><b>'.date('F j, Y').'</b></span></div>';
}
function sr_page(string $inner,array $D,string $title,string $sub,string $teacher):string{return '<section class="sd-page">'.sr_head($D,$title,$sub,$teacher).$inner.'<div class="sd-foot"><span>BULIG · Building Up Literacy, Inspiring Growth</span><span class="sd-pn"></span></div></section>';}
function sr_pct($v):string{return $v===null?'—':rtrim(rtrim(number_format((float)$v,1),'0'),'.').'%';}
function sr_avg(array $a):?float{$a=array_values(array_filter($a,fn($x)=>$x!==null));return $a?round(array_sum($a)/count($a),1):null;}
function sr_lv(?string $l):string{return $l?'<span class="sd-lv '.l4_level_class($l).'">'.($l==='Independent'?'Ind':($l==='Instructional'?'Ins':'Frus')).'</span>':'<span class="sd-na">—</span>';}
function sr_passed(?float $post):string{
 if($post===null)return '<span class="sd-st wait">Not yet</span>';
 return $post>=PT_PASS?'<span class="sd-st yes">'.icon('check').'Yes</span>':'<span class="sd-st no">'.icon('close').'No</span>';
}
function sr_signs(string $teacher):string{$head=function_exists('admin_setting')?trim(admin_setting('school_head','')):'';return '<div class="sd-signs"><div><i></i><b>'.e($teacher).'</b><small>Reading Teacher</small></div><div><i></i><b>'.($head!==''?e($head):'&nbsp;').'</b><small>School Head</small></div></div>';}
function sr_tiles(array $t,string $cls=''):string{$h='<div class="sd-tiles'.$cls.'">';foreach($t as [$a,$b,$c])$h.='<div><small>'.e($a).'</small><b>'.$b.'</b><span>'.e($c).'</span></div>';return $h.'</div>';}

function sr_summary(array $D,string $t):string{
 $P=$D['pupils'];$l4=$D['level']===5;$fin=array_filter($P,fn($p)=>$p['post']!==null);$pass=count(array_filter($fin,fn($p)=>$p['post']>=PT_PASS));
 $unit=$l4?'oral reading score':'right answers';$gain=sr_avg(array_column($P,'gain'));
 $h=sr_tiles([['Pupils',count($P),''],['Finished the level',count(array_filter($P,fn($p)=>$p['finished'])),'of '.count($P)],['Passed the post-test',$pass,PT_PASS.'% or higher'],['Average pre-test',sr_pct(sr_avg(array_column($P,'pre'))),$unit],['Average post-test',sr_pct(sr_avg(array_column($P,'post'))),$unit],['Average gain',$gain===null?'—':($gain>=0?'+':'').$gain,'points']]);
 $h.='<table class="sd-tbl"><thead><tr><th>No.</th><th class="l">Name of pupil</th><th>LRN</th><th>Lessons done</th><th>Pre-test</th><th>Post-test</th><th>Gain</th>'.($l4?'<th>Reading level</th>':'').'<th>Passed ('.PT_PASS.'%)</th><th>Status</th></tr></thead><tbody>';
 foreach($P as $p){$w=$p['of']?round($p['done']/$p['of']*100):0;
  $h.='<tr><td>'.$p['no'].'</td><td class="l"><b>'.e($p['name']).'</b></td><td class="mono">'.e((string)($p['lrn']??'')).'</td><td><span class="sd-bar"><i class="w'.(int)(round($w/10)*10).'"></i></span>'.$p['done'].'/'.$p['of'].'</td><td>'.sr_pct($p['pre']).'</td><td>'.sr_pct($p['post']).'</td><td>'.($p['gain']===null?'—':($p['gain']>=0?'+':'').$p['gain']).'</td>'.($l4?'<td>'.sr_lv($p['lv']).'</td>':'').'<td>'.sr_passed($p['post']).'</td><td>'.($p['finished']?'Finished':($p['done']?'In progress':'Not started')).'</td></tr>';}
 if(!$P)$h.='<tr><td colspan="10">No pupils in this section yet.</td></tr>';
 $h.='</tbody></table><p class="sd-note">'.($l4?'Pre-test and post-test are the oral reading scores of the module’s pre-test and post-test passages (Phil-IRI). Reading level: Ind = Independent (98–100), Ins = Instructional (90–96), Frus = Frustration (89 and below).':'Pre-test and post-test are the right answers on the level’s pre-test and post-test, marked by BULIG with the answer keys.').' Passed = '.PT_PASS.'% or higher on the post-test.</p>';
 return sr_page($h.sr_signs($t),$D,'Section Level Summary','Every pupil of the section in this level',$t);
}
function sr_prepost(array $D,string $t):string{
 $P=$D['pupils'];$l4=$D['level']===5;$fin=array_filter($P,fn($p)=>$p['post']!==null);
 $better=count(array_filter($fin,fn($p)=>$p['gain']!==null&&$p['gain']>0.5));$same=count(array_filter($fin,fn($p)=>$p['gain']!==null&&abs($p['gain'])<=0.5));$lower=count(array_filter($fin,fn($p)=>$p['gain']!==null&&$p['gain']<-0.5));
 $h=sr_tiles([['Improved',$better,'pupils'],['About the same',$same,'pupils'],['Lower',$lower,'pupils'],['No post-test yet',count($P)-count($fin),'pupils']],' four');
 $vals=array_filter(array_merge(array_column($P,'pre'),array_column($P,'post')),fn($x)=>$x!==null);$lo=$vals?max(0,min(PT_PASS-5,(int)floor(min($vals)/5)*5)):0;$lo=min($lo,75);
 $X0=150;$X1=650;$sx=fn($v)=>round($X0+($v-$lo)/(100-$lo)*($X1-$X0),1);$RH=26;$H=34+max(1,count($P))*$RH;
 $s='';for($v=$lo;$v<=100;$v+=($lo>=50?5:10)){$pm=(int)$v===PT_PASS;$s.='<line x1="'.$sx($v).'" y1="18" x2="'.$sx($v).'" y2="'.($H-14).'" class="'.($pm?'ch-pass':'ch-grid').'"/><text x="'.$sx($v).'" y="'.$H.'" text-anchor="middle" class="ch-ax">'.$v.'%</text>';}
 if($l4)$s.='<rect x="'.$sx(90).'" y="10" width="'.($sx(96)-$sx(90)).'" height="6" rx="3" class="ch-ins"/><rect x="'.$sx(98).'" y="10" width="'.($sx(100)-$sx(98)).'" height="6" rx="3" class="ch-ind"/>';
 $s.='<text x="'.$sx(PT_PASS).'" y="10" text-anchor="middle" class="ch-ax">pass mark</text>';
 foreach(array_values($P) as $i=>$p){$y=34+$i*$RH;$s.='<text x="'.($X0-10).'" y="'.($y+4).'" text-anchor="end" class="ch-name">'.e(mb_strimwidth($p['name'],0,24,'…')).'</text>';
  if($p['pre']!==null)$s.=($p['post']!==null?'<line x1="'.$sx($p['pre']).'" y1="'.$y.'" x2="'.$sx($p['post']).'" y2="'.$y.'" class="ch-link"/>':'').'<circle cx="'.$sx($p['pre']).'" cy="'.$y.'" r="5.5" class="ch-pre"/>';
  if($p['post']!==null)$s.='<circle cx="'.$sx($p['post']).'" cy="'.$y.'" r="6" class="ch-post"/>';
  $txt=$p['post']!==null?($p['pre']!==null?$p['pre'].' → '.$p['post']:'post-test '.$p['post']):($p['pre']!==null?'no post-test yet':'not tested yet');
  $s.='<text x="'.($p['pre']!==null||$p['post']!==null?max($p['pre']!==null?$sx($p['pre']):0,$p['post']!==null?$sx($p['post']):0)+12:$X0+4).'" y="'.($y+4).'" class="ch-val">'.e($txt).'</text>';}
 $h.='<h3 class="sd-h">'.($l4?'Oral reading score':'Score').': pre-test to post-test</h3><div class="sd-legend"><span><i class="o"></i>Pre-test</span><span><i class="f"></i>Post-test</span><span><i class="d"></i>'.PT_PASS.'% pass mark</span>'.($l4?'<span><i class="b ins"></i>Instructional 90–96</span><span><i class="b ind"></i>Independent 98–100</span>':'').'</div>';
 $h.='<svg class="sd-chart" viewBox="0 0 760 '.($H+12).'" role="img" aria-label="Pre-test and post-test of each pupil">'.$s.'</svg>';
 if($l4){$stack=function(string $lab,array $arr,int $tot):string{$o='<div class="sd-stk"><b>'.e($lab).'</b><div class="sd-stkbar">';foreach(['Independent','Instructional','Frustration'] as $l){$c=count(array_filter($arr,fn($x)=>$x===$l));if($c)$o.='<span class="'.l4_level_class($l).' w'.(int)(round($c/max(1,$tot)*20)*5).'">'.$c.'</span>';}return $o.'</div><small>'.$tot.' pupils</small></div>';};
  $pre=array_values(array_filter(array_column($P,'preLv')));$post=array_values(array_filter(array_column($P,'lv')));
  $h.='<h3 class="sd-h">Reading levels: before and after</h3>'.$stack('Pre-test',$pre,count($pre)).$stack('Post-test',$post,count($post)).'<div class="sd-legend"><span><i class="b ind"></i>Independent</span><span><i class="b ins"></i>Instructional</span><span><i class="b frus"></i>Frustration</span></div>';}
 else{$pa=count(array_filter($P,fn($p)=>$p['pre']!==null&&$p['pre']>=PT_PASS));$pb=count(array_filter($fin,fn($p)=>$p['post']>=PT_PASS));
  $h.='<h3 class="sd-h">Pupils at '.PT_PASS.'% or higher</h3>'.sr_tiles([['Pre-test',$pa,'of '.count(array_filter($P,fn($p)=>$p['pre']!==null)).' who took it'],['Post-test',$pb,'of '.count($fin).' who took it']],' two');}
 return sr_page($h,$D,'Pre-test and Post-test','How much each pupil improved in this level',$t);
}
function sr_irl(array $D,string $t):string{
 $P=$D['pupils'];$MIS=['Mispron.','Omission','Substit.','Insertion','Repetition','Transpos.','Reversal'];$tested=array_filter($P,fn($p)=>$p['l4']!==null);
 $post=one("SELECT a.prompt,a.type,a.lesson_id FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND m.grade_level=? AND a.phase='post' AND a.type='reading' AND a.published=1 ORDER BY a.position DESC LIMIT 1",[$D['grade']]);$pp=$post?level4_passage_parts($post):null;
 $h='<p class="sd-lead">Post-test passage: '.($pp?'<b>“'.e($pp['title']).'”</b> · '.(int)$pp['words'].' words · ':'').'scored by BULIG with the module’s miscue table and checked by the teacher.</p>';
 $h.='<table class="sd-tbl sm"><thead><tr><th>No.</th><th class="l">Name of pupil</th>';foreach($MIS as $m)$h.='<th class="v">'.$m.'</th>';$h.='<th>Total</th><th>Oral reading score</th><th>Reading level</th><th>Words per minute</th></tr></thead><tbody>';
 $tot=array_fill(0,7,0);
 foreach($P as $p){$s=$p['l4'];$h.='<tr><td>'.$p['no'].'</td><td class="l"><b>'.e($p['name']).'</b></td>';
  for($k=0;$k<7;$k++){$c=(int)($s['m'][$k]??0);$tot[$k]+=$s?$c:0;$h.='<td>'.($s?$c:'').'</td>';}
  $h.='<td><b>'.($s?(int)$s['total']:'').'</b></td><td>'.($s?sr_pct((float)$s['score']):'—').'</td><td>'.sr_lv($s['level']??null).'</td><td>'.($s&&$s['wpm']?(int)$s['wpm']:'—').'</td></tr>';}
 $h.='<tr class="sum"><td></td><td class="l">Class total</td>';foreach($tot as $c)$h.='<td>'.$c.'</td>';$h.='<td>'.array_sum($tot).'</td><td>'.sr_pct(sr_avg(array_map(fn($p)=>(float)$p['l4']['score'],$tested))).'</td><td></td><td>'.(($w=sr_avg(array_filter(array_map(fn($p)=>$p['l4']['wpm']?:null,$tested))))!==null?round($w):'—').'</td></tr></tbody></table>';
 $cnt=fn($l)=>count(array_filter($tested,fn($p)=>($p['l4']['level']??'')===$l));
 $h.='<div class="sd-prof"><div class="ind"><b>'.$cnt('Independent').'</b><span>Independent</span><small>98–100%</small></div><div class="ins"><b>'.$cnt('Instructional').'</b><span>Instructional</span><small>90–96%</small></div><div class="frus"><b>'.$cnt('Frustration').'</b><span>Frustration</span><small>89% and below</small></div><div class="na"><b>'.(count($P)-count($tested)).'</b><span>Not yet tested</span><small>post-test not done</small></div></div>';
 return sr_page($h,$D,'Phil-IRI Class Reading Profile','Oral reading of the post-test passage',$t);
}
function sr_reteach(array $D,string $t):string{
 $ids=array_map(fn($p)=>(int)$p['id'],$D['pupils']);$h='';
 if(!$ids)return sr_page('<p class="sd-lead">No pupils in this section yet.</p>',$D,'What to Re-teach','Least-learned skills of the section in this level',$t);
 if($D['level']===5){
  $names=['Mispronunciation','Omission','Substitution','Insertion','Repetition','Transposition','Reversal'];$tot=array_fill(0,7,0);$pas=[];
  $tip=['Practise the hard words aloud before reading; sound them out in parts.','Read with a finger or the reading ruler under each word.','Look at the whole word, not only the first letter; re-read the sentence.','Read slowly; check that every word said is on the page.','Read the passage twice: once slowly, once smoothly.','Point to each word in order while reading.','Practise word pairs like was/saw and on/no.'];
  foreach(rows("SELECT c.rubric_scores,a.id,a.title,a.phase,l.position FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND m.grade_level=? AND a.type='reading' AND c.status IN ('completed','approved') AND c.pupil_id IN (".implode(',',$ids).')',[$D['grade']]) as $r){
   $s=l4_saved_score($r);if(!$s)continue;foreach($s['m'] as $k=>$c)$tot[$k]+=(int)$c;$k=(int)$r['id'];$pas[$k]??=['t'=>preg_replace('~^Read aloud:\s*~','',(string)$r['title']),'ph'=>$r['phase'],'s'=>[],'f'=>0];$pas[$k]['s'][]=(float)$s['score'];if($s['level']==='Frustration')$pas[$k]['f']++;}
  arsort($tot);$mx=max(1,max($tot));
  $h.='<h3 class="sd-h">Most common miscues in the section</h3><div class="sd-hb">';foreach($tot as $k=>$c)$h.='<div><span>'.$names[$k].'</span><span class="sd-hbar"><i class="w'.(int)(round($c/$mx*20)*5).'"></i></span><b>'.$c.'</b><small>'.e($tip[$k]).'</small></div>';$h.='</div>';
  foreach($pas as &$x)$x['avg']=sr_avg($x['s']);unset($x);uasort($pas,fn($a,$b)=>$a['avg']<=>$b['avg']);
  $h.='<h3 class="sd-h">Hardest passages first (average oral reading score)</h3>';
  if(!$pas)$h.='<p class="sd-lead">No readings scored yet.</p>';
  else{$h.='<table class="sd-tbl"><thead><tr><th class="l">Passage</th><th>Pupils who read it</th><th>Average score</th><th>At Frustration</th><th class="l">Suggestion</th></tr></thead><tbody>';
   foreach(array_slice($pas,0,8,true) as $x)$h.='<tr><td class="l"><b>'.e(($x['ph']==='pre'?'Pre-test · ':($x['ph']==='post'?'Post-test · ':'')).$x['t']).'</b></td><td>'.count($x['s']).'</td><td>'.sr_pct($x['avg']).'</td><td>'.$x['f'].'</td><td class="l">'.($x['avg']<90?'Read it together again in class (Class Demo), then pair reading.':($x['avg']<96?'Pair reading; let pupils try it again.':'Quick review of the hard words.')).'</td></tr>';
   $h.='</tbody></table>';}
 }else{
  /* the questions most pupils missed (answer keys), lowest first */
  $items=[];foreach(rows("SELECT a.*,l.position lpos FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND a.published=1 AND l.published=1".GRADE_SQL.' ORDER BY l.position,a.position',[$D['level'],$D['grade']]) as $a){
   $key=activity_key($a);if(!$key)continue;$set=l2a_set($a);if(!$set)continue;
   $done=rows("SELECT response FROM activity_completion WHERE activity_id=? AND status IN ('completed','approved') AND pupil_id IN (".implode(',',$ids).')',[(int)$a['id']]);if(!$done)continue;
   $st=[];foreach($done as $c){$s=key_score($a,(string)$c['response']);if(!$s)continue;foreach($s['cards'] as $n=>$x){if($x['t']==='open')continue;$st[$n]??=['p'=>0,'o'=>0,'n'=>0,'w'=>[],'t'=>$x['t'],'want'=>$x['want']];$st[$n]['p']+=$x['pts'];$st[$n]['o']+=$x['of'];$st[$n]['n']++;if(!$x['pts']&&$x['got']!==''&&in_array($x['t'],['choice','card'],true))$st[$n]['w'][$x['got']]=($st[$n]['w'][$x['got']]??0)+1;}}
   foreach($st as $n=>$x){if(!$x['o'])continue;arsort($x['w']);$items[]=['pct'=>round($x['p']/$x['o']*100),'n'=>$x['n'],'q'=>qc_text($set,(int)$n,$x['t']),'where'=>lesson_display_label($D['level'],(int)$a['lpos']).' · '.$set['module_title'],'want'=>$x['want'],'wrong'=>(string)(array_key_first($x['w'])??'')];}}
  usort($items,fn($a,$b)=>$a['pct']<=>$b['pct']);
  $h.='<p class="sd-lead">The questions the fewest pupils got right, from the level’s lessons, pre-test and post-test. Read these again together.</p>';
  if(!$items)$h.='<p class="sd-lead">No answers to check yet.</p>';
  else{$h.='<table class="sd-tbl"><thead><tr><th class="l">Lesson</th><th class="l">Question</th><th>Pupils right</th><th class="l">Right answer</th><th class="l">Wrong answer chosen most</th></tr></thead><tbody>';
   foreach(array_slice($items,0,14) as $x)$h.='<tr><td class="l">'.e(mb_strimwidth($x['where'],0,40,'…')).'</td><td class="l wrap"><b>'.e(mb_strimwidth($x['q'],0,90,'…')).'</b></td><td><span class="sd-bar"><i class="w'.(int)(round($x['pct']/10)*10).'"></i></span>'.$x['pct'].'%</td><td class="l wrap">'.e(mb_strimwidth((string)$x['want'],0,40,'…')).'</td><td class="l wrap">'.($x['wrong']!==''?e(mb_strimwidth($x['wrong'],0,40,'…')):'—').'</td></tr>';
   $h.='</tbody></table>';}
 }
 return sr_page($h,$D,'What to Re-teach','Least-learned skills of the section in this level',$t);
}
function sr_help(array $D,string $t):string{
 $need=[];$week=date('Y-m-d',strtotime('-7 days'));
 foreach($D['pupils'] as $p){$r=[];
  if($p['post']!==null&&$p['post']<PT_PASS)$r[]='Post-test '.sr_pct($p['post']).' · needs '.PT_PASS.'%';
  if(($p['lv']??'')==='Frustration')$r[]='Frustration level';
  if(!$p['finished'])$r[]=$p['done'].' of '.$p['of'].' lessons';
  if(!$p['finished']&&(!$p['lastday']||$p['lastday']<$week))$r[]=$p['lastday']?'Not active since '.date('M j',strtotime((string)$p['lastday'])):'Not started yet';
  if($p['help'])$r[]='Asked for help '.$p['help'].'×';
  if(count($r)===1&&!$p['finished']&&$p['lastday']&&$p['lastday']>=$week&&!$p['help'])continue;/* still working this week: not behind */
  if($r)$need[]=[$p,$r];}
 $h='<p class="sd-lead"><b>'.count($need).' of '.count($D['pupils']).'</b> pupils need attention in this level.</p><div class="sd-need">';
 foreach($need as [$p,$r]){$next=$p['post']!==null?'Re-read the passages or questions in class, then let the pupil retake the post-test.':(!$p['done']?'Call or visit the parent; sit with the pupil for the first lessons.':'Remind the pupil to finish; check the lesson they are stuck on.');
  $h.='<div><span class="sd-num">'.$p['no'].'</span><div><b>'.e($p['name']).'</b><p>'.implode('',array_map(fn($x)=>'<span class="sd-chip">'.e($x).'</span>',$r)).'</p><small><b>Next step:</b> '.e($next).'</small></div><span class="sd-done">Done <i></i></span></div>';}
 if(!$need)$h.='<p class="sd-lead">Every pupil is on track.</p>';
 return sr_page($h.'</div>'.sr_signs($t),$D,'Pupils Needing Help','Intervention list for this level',$t);
}
function sr_activity(array $D,string $t):string{
 $days=$D['days'];$n=count($days);
 $h='<p class="sd-lead">School days <b>'.date('F j',strtotime($D['from'])).'–'.date('j, Y',strtotime($D['last'])).'</b> (Monday to Friday) · a filled box = the pupil used BULIG that day.</p>';
 $h.='<table class="sd-tbl sm"><thead><tr><th>No.</th><th class="l">Name of pupil</th><th class="l">School days ('.$n.')</th><th>Days active</th><th>Activities done</th><th>Last sign-in</th><th>Sent offline</th></tr></thead><tbody>';
 $act=[];$idle=0;$week=date('Y-m-d',strtotime('-7 days'));
 foreach($D['pupils'] as $p){$a=count(array_filter($days,fn($d)=>isset($p['act'][$d])));$act[]=$a;if(!$p['lastday']||$p['lastday']<$week)$idle++;
  $h.='<tr><td>'.$p['no'].'</td><td class="l"><b>'.e($p['name']).'</b></td><td class="l"><span class="sd-days">'.implode('',array_map(fn($d)=>'<i'.(isset($p['act'][$d])?' class="on"':'').'></i>',$days)).'</span></td><td>'.$a.'</td><td>'.$p['acts'].'</td><td>'.($p['login']?date('M j',strtotime((string)$p['login'])):'—').'</td><td>'.$p['off'].'</td></tr>';}
 $h.='</tbody></table>'.sr_tiles([['Average days active',$act?round(array_sum($act)/count($act),1):0,'of '.$n],['Activities done',array_sum(array_column($D['pupils'],'acts')),'this month'],['Answers sent offline',array_sum(array_column($D['pupils'],'off')),'synced to BULIG'],['Not active 7+ days',$idle,'pupils']],' four');
 return sr_page($h,$D,'Activity and Attendance','How often each pupil used BULIG',$t);
}

/* ---------- the page ---------- */
function section_report_view(array $u):void{
 $tid=(int)$u['id'];$sec=owned_section((int)($_GET['section']??0),$tid);$level=(int)($_GET['level']??0);
 if(!val('SELECT id FROM bulig_levels WHERE id=? AND published=1',[$level]))fail('Choose a level.',400);
 $parts=array_values(array_intersect(array_keys(SR_PARTS),(array)($_GET['p']??array_keys(SR_PARTS))));if(!$parts)$parts=['sum'];
 if($level!==5)$parts=array_values(array_diff($parts,['irl']));
 $month=preg_match('~^\d{4}-\d{2}$~',(string)($_GET['month']??''))?(string)$_GET['month']:date('Y-m');
 $D=sr_data($sec,$level,$tid,$month);$t=(string)$u['name'];
 $logos=division_logo()!==''&&admin_setting('logo_school','')!=='';
 echo '<div class="no-print rc-actions"><div><span class="eyebrow">SECTION DATA REPORT</span><h1>Grade '.$D['grade'].' – '.e($sec['name']).' · '.e(level_label($level)).'</h1><p class="muted">'.count($D['pupils']).' '.(count($D['pupils'])===1?'pupil':'pupils').' · '.count($parts).' '.(count($parts)===1?'page':'pages').'. Check the pages, then print or save as PDF. Sign the summary after printing.</p>'
  .(!$logos?'<p class="rc-tip">'.icon('info').'The Division and school logos print in the circles once your administrator uploads them in Settings → School info.</p>':'').'</div>'
  .'<div class="rc-btns"><a class="btn quiet" href="?page=print#pc-sd">'.icon('arrow','flip').'Back</a><button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button></div></div>';
 $make=['sum'=>'sr_summary','pp'=>'sr_prepost','irl'=>'sr_irl','rt'=>'sr_reteach','help'=>'sr_help','act'=>'sr_activity'];
 echo '<div class="sd-wrap" data-pages="'.count($parts).'">';foreach($parts as $k)echo $make[$k]($D,$t);echo '</div>';
}
/** The Print centre card. */
function section_report_card(array $secs,callable $img):string{
 if(!$secs)return '';
 $lv='';foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY '.level_order_sql('id')) as $l)$lv.='<option value="'.(int)$l['id'].'">'.e(level_label((int)$l['id'])).'</option>';
 $sc='';foreach($secs as $s)$sc.='<option value="'.(int)$s['id'].'">Grade '.(int)$s['grade_level'].' · '.e($s['name']).'</option>';
 $mo='';for($i=0;$i<3;$i++){$m=date('Y-m',strtotime(date('Y-m-01').' -'.$i.' month'));$mo.='<option value="'.$m.'">'.date('F Y',strtotime($m.'-01')).'</option>';}
 $help=['sum'=>'Every pupil: lessons, pre-test, post-test, passed','pp'=>'How much each pupil improved','irl'=>'Level 4 only: miscues, score, reading level','rt'=>'Least-learned skills and hardest lessons','help'=>'Intervention list with next steps','act'=>'Days active, activities, last sign-in'];
 $ch='';foreach(SR_PARTS as $k=>$label)$ch.='<label><input type="checkbox" name="p[]" value="'.$k.'"'.($k!=='act'?' checked':'').'><span>'.e($label).'<small>'.e($help[$k]).'</small></span></label>';
 return print_card('pc-sd',$img('section-report'),'Section data report','All the results of one section in one level, for the school head, LAC sessions or parents’ meetings.',
  '<form method="get" class="pc-form"><input type="hidden" name="page" value="section_report"><label class="pc-for">Section<select name="section">'.$sc.'</select></label><label class="pc-for">Level<select name="level">'.$lv.'</select></label><div class="sd-pick">'.$ch.'</div><label class="pc-for">Month for activity and attendance<select name="month">'.$mo.'</select></label><div class="pc-btns"><button class="btn primary">'.icon('print').'Print report</button></div></form>','','NEW');
}
