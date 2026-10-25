<?php
/* Reading Progress Board (pupil) and the printable Reading Progress Report Card (teacher).
   - The board is the school's paper "Reading Progress Board" as a staircase in the road map's meadow: one step for each
     level (Level 2 has 2a and 2b side by side), the pupil's character on the step they are on now, a ribbon on every level
     they finished (finished = every lesson done and the post-test at 80% or more, see app/post_gate.php). Drawn by
     app.js (data-lb) from rb_data().
   - The report card is the school's paper "Reading Progress Report Card" (design B: the reading ladder beside the table),
     A4, one page per pupil, for one pupil or a whole section. The LRN comes from pupil_details; the school name, school
     head and school year from Settings > School info; the Division and school logos are uploaded there too. */

/** The board's steps: board number => [[BULIG level id, part label, name], ...]. Level 2 is 2a and 2b; Level 3 is 3a and 3b (v177). */
const RB_STEPS=[1=>[[1,'','Oral Language']],2=>[[2,'2a','Phonological Awareness'],[3,'2b','Phonological Awareness']],3=>[[4,'3a','Word Recognition'],[9,'3b','Word Recognition']],4=>[[5,'','Fluency']],5=>[[6,'','Vocabulary Development & Listening Comprehension']],6=>[[7,'','Graded Reading Comprehension']],7=>[[8,'','Genuine Love for Reading & Writing']]];
/** Board colours of the steps (block, shade, text). */
const RB_COLORS=[1=>['#f6c02c','#c99400','#a77a00'],2=>['#f28c28','#c4650f','#d9690f'],3=>['#e2457a','#b3275a','#c92a62'],4=>['#3d9be0','#2470b0','#1f78c8'],5=>['#7a4bc0','#55308f','#6a3cb0'],6=>['#19a7a0','#0f7a75','#0f8f88'],7=>['#2fa04f','#1d7a3c','#1d8a40']];

/** Where the pupil is on every BULIG level: [level id => ['s'=>done|skip|cur|lock, 'date'=>finish date or null,
    'pct'=>lessons done %, 'post'=>post-test % or null, 'retry'=>true when every lesson is done but the post-test is below 80%]]. */
function rb_levels(int $pid):array{
 static $c=[];if(isset($c[$pid]))return $c[$pid];
 $start=pupil_start_level($pid);$fin=function_exists('finished_levels')?finished_levels($pid):[];$grade=pupil_grade($pid);$out=[];$cur=0;
 foreach(LEVEL_ORDER as $L){$pub=level_published($L);
  $n=$pub?one('SELECT COUNT(*) total,COUNT(p.completed_at) done FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$pid,$L,$grade]):['total'=>0,'done'=>0];
  $pct=(int)$n['total']?(int)floor(100*(int)$n['done']/(int)$n['total']):0;
  $pt=function_exists('pt_result')&&$pub?pt_result($pid,$L):['n'=>0];$post=$pt['n']&&($pt['done']??0)>=$pt['n']?(int)$pt['pct']:null;
  if(isset($fin[$L]))$s='done';elseif($start&&level_rank($L)<level_rank($start))$s='skip';elseif($start&&!$cur&&$pub&&(int)$n['total']){$s='cur';$cur=$L;}else $s='lock';
  $out[$L]=['s'=>$s,'date'=>$fin[$L]??null,'pct'=>$pct,'post'=>$post,'retry'=>$s==='cur'&&(int)$n['total']&&(int)$n['done']>=(int)$n['total']&&$post!==null&&$post<(function_exists('pt_passed')?PT_PASS:80)];}
 return $c[$pid]=$out;
}
/** The board for app.js: steps with their parts, the pupil's character, and a short summary. */
function rb_data(int $pid):array{
 $lv=rb_levels($pid);$tiers=[];$ribbons=0;$left=0;$now=null;
 foreach(RB_STEPS as $n=>$parts){$t=['n'=>$n,'parts'=>[]];
  foreach($parts as [$L,$k,$name]){$x=$lv[$L];$d=$x['date']?strtotime((string)$x['date']):0;
   $p=['k'=>$k,'t'=>$name,'s'=>$x['s'],'pct'=>$x['pct']];
   if($x['s']==='done'){$ribbons++;$p['date']=date('M j',$d);$p['note']='Finished '.date('M j',$d).($x['post']!==null?' · Post-test '.$x['post'].'%':'');$p['short']=date('M j',$d).($x['post']!==null?' · '.$x['post'].'%':'');}
   if($x['s']==='skip'){$ribbons++;$p['note']='Started higher · ribbon earned';$p['short']='Started higher';}
   if($x['s']==='cur'){$now=['n'=>$n,'k'=>$k,'t'=>$name,'pct'=>$x['pct'],'retry'=>$x['retry'],'post'=>$x['post']];if($x['retry']){$p['sub']='Post-test '.$x['post'].'% · try again';$p['subshort']='Try the post-test again';}}
   if($x['s']==='cur'||$x['s']==='lock')$left++;
   if($x['s']!=='lock')$p['h']='?page=lessons&level='.$L.'#level'.$L.'-path';
   $t['parts'][]=$p;}
  $tiers[]=$t;}
 return ['tiers'=>$tiers,'kid'=>char_src((string)val('SELECT sex FROM pupil_details WHERE pupil_id=?',[$pid])==='female'?'girl':'boy','cheer'),'ribbons'=>$ribbons,'left'=>$left,'now'=>$now];
}
function rb_label(array $now):string{return 'Level '.(strtoupper($now['k'])?:$now['n']).' · '.$now['t'];}

/** A colour mixed with white (0 = the colour, 1 = white). */
function rb_mix(string $hex,float $w):string{$h=ltrim($hex,'#');$o='#';for($i=0;$i<3;$i++){$v=hexdec(substr($h,$i*2,2));$o.=sprintf('%02x',(int)round($v+(255-$v)*$w));}return $o;}
/* ---------- Pupil Overview: a small reading ladder (it replaced the reading garden) ---------- */
/** The board in small: seven steps in the board's colours, a ribbon on every finished or skipped level, the pupil's character on the
    level they are on now and the Reading Castle at the top. It links to My reading board. */
function rb_mini(int $pid):string{
 $d=rb_data($pid);$now=$d['now'];$svg='<defs><linearGradient id="lbm-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#bfe8ff"/><stop offset="1" stop-color="#eaf8ff"/></linearGradient><linearGradient id="lbm-grass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d9f2c4"/><stop offset="1" stop-color="#a9d98f"/></linearGradient><radialGradient id="lbm-sun" cx="40%" cy="35%" r="65%"><stop offset="0" stop-color="#fff6b0"/><stop offset=".6" stop-color="#ffd23f"/><stop offset="1" stop-color="#f6a700"/></radialGradient><linearGradient id="lbm-castle" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff8e8"/><stop offset="1" stop-color="#ead9b4"/></linearGradient></defs>'
  .'<rect width="300" height="260" fill="url(#lbm-grass)"/><rect width="300" height="150" fill="url(#lbm-sky)"/><path d="M0 150L46 98L84 132L132 86L176 138L214 104L254 130L300 102V170H0Z" fill="#b9dfae"/><path d="M0 160Q80 128 150 150T300 146V176H0Z" fill="#c8eab0"/>'
  .'<g transform="translate(36 34)"><g class="pfm-sunr">'.implode('',array_map(fn($k)=>'<rect x="-2" y="-30" width="4" height="9" rx="2" fill="#ffd23f" transform="rotate('.($k*30).')"/>',range(0,11))).'</g><circle r="17" fill="url(#lbm-sun)"/><circle cx="-5" cy="-2" r="1.8" fill="#5a3a12"/><circle cx="5" cy="-2" r="1.8" fill="#5a3a12"/><path d="M-6 4q6 6 12 0" stroke="#5a3a12" stroke-width="1.8" fill="none" stroke-linecap="round"/></g>'
  .'<g class="pfm-cloud"><path transform="translate(104 22) scale(.8)" d="M0 14a10 10 0 0 1 16-9 13 13 0 0 1 24 3 8 8 0 0 1 2 16H4a9 9 0 0 1-4-10z" fill="#fff"/></g>';
 $x0=16;$sw=36;$bw=34;$base=236;$rise=24;$kid='';
 foreach($d['tiers'] as $i=>$t){$x=$x0+$i*$sw;$y=$base-$rise*($i+1)-8;$c=RB_COLORS[$t['n']];$ss=array_column($t['parts'],'s');
  $lock=!array_diff($ss,['lock']);$done=!array_diff($ss,['done','skip']);$cur=in_array('cur',$ss,true);
  if($lock)$c=array_map(fn($h)=>rb_mix($h,.55),$c);
  $svg.='<g><path d="M'.$x.' '.$y.'l5-5h'.$bw.'l-5 5z" fill="'.$c[0].'" opacity=".7"/><path d="M'.($x+$bw).' '.$y.'l5-5v'.($base-$y).'l-5 5z" fill="'.$c[1].'"/><rect x="'.$x.'" y="'.$y.'" width="'.$bw.'" height="'.($base-$y).'" fill="'.$c[0].'"/><rect x="'.$x.'" y="'.$y.'" width="'.$bw.'" height="6" fill="#fff" opacity=".22"/><text x="'.($x+$bw/2).'" y="'.($y+20).'" text-anchor="middle" font-weight="800" font-size="15" fill="#fff">'.$t['n'].'</text></g>';
  if($done){$cx=$x+$bw/2;$cy=$y-11;$g=['#ffd23f','#c99400',$c[0]];$pts=[];for($a=0;$a<16;$a++){$r=$a%2?6.2:7.6;$pts[]=round($cx+$r*cos($a*M_PI/8),1).','.round($cy+$r*sin($a*M_PI/8),1);}
   $svg.='<path d="M'.($cx-4).' '.($cy+4).'l-3 9 3-2 2 3 2-9z" fill="'.$g[2].'"/><path d="M'.($cx+4).' '.($cy+4).'l3 9-3-2-2 3-2-9z" fill="'.$g[2].'"/><polygon points="'.implode(' ',$pts).'" fill="'.$g[0].'" stroke="'.$g[1].'"/><circle cx="'.$cx.'" cy="'.$cy.'" r="3.6" fill="'.$g[2].'"/>';}
  if($cur)$kid='<ellipse cx="'.($x+$bw/2).'" cy="'.($y-2).'" rx="12" ry="3" fill="#000" opacity=".16"/><image href="'.e($d['kid']).'" x="'.($x-3).'" y="'.($y-56).'" width="40" height="56" preserveAspectRatio="xMidYMax meet"/><g class="pfm-flag"><rect x="'.($x+$bw/2-18).'" y="'.($y-73).'" width="36" height="15" rx="7.5" fill="#0f5a2f" stroke="#fff" stroke-width="1.5"/><text x="'.($x+$bw/2).'" y="'.($y-62).'" text-anchor="middle" font-weight="800" font-size="8.5" fill="#fff">YOU</text></g>';}
 /* the Reading Castle on the top step */
 $svg.='<g transform="translate(249 38) scale(.3)"><rect x="-56" y="-10" width="112" height="80" fill="url(#lbm-castle)" stroke="#c9b48a" stroke-width="3"/><rect x="-70" y="-40" width="34" height="110" fill="url(#lbm-castle)" stroke="#c9b48a" stroke-width="3"/><rect x="36" y="-40" width="34" height="110" fill="url(#lbm-castle)" stroke="#c9b48a" stroke-width="3"/><path d="M-74 -40 L-53 -72 L-32 -40Z M32 -40 L53 -72 L74 -40Z" fill="#2a6cc0"/><path d="M-60 -10 L0 -50 L60 -10Z" fill="#d6452f"/><rect x="-14" y="30" width="28" height="40" rx="14" fill="#8a5527"/><path d="M0 -50 V-82" stroke="#6b5a45" stroke-width="5"/><path class="pfm-flagwave" d="M1 -82h24l-6 6 6 6H1z" fill="#f6c02c"/></g>';
 $svg.='<rect x="0" y="'.$base.'" width="300" height="'.(260-$base).'" fill="#b7e09c"/><path d="M'.($x0-4).' '.$base.'H280" stroke="#7a5a2a" stroke-opacity=".2" stroke-width="5" stroke-linecap="round"/>';
 foreach([[30,250,'#ff8fab'],[96,252,'#ffd23f'],[170,249,'#9ad0ff'],[250,252,'#c3a2ff']] as [$fx,$fy,$fc])$svg.='<g transform="translate('.$fx.' '.$fy.')"><path d="M0 0v-7" stroke="#3e9a4a" stroke-width="2"/><circle cx="0" cy="-9" r="3.6" fill="'.$fc.'"/><circle cx="0" cy="-9" r="1.5" fill="#fff"/></g>';
 $svg.=$kid;
 $where=$now?'Level '.(strtoupper($now['k'])?:$now['n']):($d['left']?'Not started':'All done!');
 $label='My reading board. '.$d['ribbons'].' '.($d['ribbons']===1?'ribbon':'ribbons').'. '.($now?'You are on '.rb_label($now).'.':'').' Open my reading board.';
 return '<a class="adventure-art lb-mini-art" href="?page=board" aria-label="'.e($label).'"><span class="lb-mini"><svg viewBox="0 0 300 260" font-family="Poppins,sans-serif" aria-hidden="true">'.$svg.'</svg></span><span class="lb-mini-cap"><b>'.$d['ribbons'].' '.($d['ribbons']===1?'ribbon':'ribbons').'</b><span>'.e($where).'</span><span class="lb-mini-go">My reading board '.icon('arrow').'</span></span></a>';
}

/* ---------- Pupil Overview: "My level now" (it replaced the "Starting level" card) ---------- */
/** The level the pupil is on now: its cover, name, how much is done and where they started. Links to that level. */
function rb_level_now(int $pid):string{
 $lv=rb_levels($pid);$start=pupil_start_level($pid);$names=[];foreach(RB_STEPS as $n=>$parts)foreach($parts as [$L,$k,$name])$names[$L]=[$k?:(string)$n,$name];
 $label='<span class="stat-label">'.icon('ladder').'My level now</span>';
 if(!$start)return '<div class="pupil-stat level-stat lvnow">'.$label.'<strong>—</strong><span class="stat-foot">Your teacher will choose your starting level soon.</span></div>';
 $cur=0;foreach($lv as $L=>$x)if($x['s']==='cur'){$cur=$L;break;}
 $from='Started at '.level_label($start);
 if(!$cur)return '<a class="pupil-stat level-stat lvnow" href="?page=board" aria-label="My level now: every level finished. Open my reading board.">'.$label.'<span class="lvn-cover"><img src="'.e(level_cover(8)).'" alt="" loading="lazy"><b>7</b></span><span class="lvn-name">You finished every level!</span><progress class="lvn-bar" value="100" max="100"></progress><span class="stat-foot">'.e($from).' · Reading Castle reached</span></a>';
 $x=$lv[$cur];[$num,$name]=$names[$cur];
 $foot=$x['retry']?'Post-test '.$x['post'].'% · try again':$x['pct'].'% done · '.$from;
 return '<a class="pupil-stat level-stat lvnow" href="?page=lessons&amp;level='.$cur.'#level'.$cur.'-path" aria-label="My level now: Level '.e($num).', '.e($name).'. '.e($foot).'.">'.$label
  .'<span class="lvn-cover"><img src="'.e(level_cover($cur)).'" alt="" loading="lazy"><b>'.e($num).'</b></span><span class="lvn-name">'.e($name).'</span>'
  .'<progress class="lvn-bar" value="'.$x['pct'].'" max="100" aria-hidden="true"></progress><span class="stat-foot">'.e($foot).'</span></a>';
}

/* ---------- Pupil: My reading board ---------- */
function rb_view(array $u):void{
 $pid=(int)$u['id'];$d=rb_data($pid);$first=explode(' ',trim((string)$u['name']))[0];$now=$d['now'];
 echo pf_defs().'<div class="pageheading"><div><span class="eyebrow">MY READING PROGRESS BOARD</span><h1>Climb the reading ladder, '.e($first).'!</h1><p class="muted">'
  .($now?'You are on '.e(rb_label($now)).'. '.($now['retry']?'Answer the post-test again to earn its ribbon.':'Finish a level to earn its ribbon!'):($d['left']?'Your teacher will choose your starting level soon.':'You finished every level. You reached the Reading Castle!')).'</p></div></div>'.friend_say('toby','flag',$d['left']?'Every level you finish, we climb higher together. Keep going!':'You reached the top! I planted our flag at the Reading Castle!');
 echo '<div class="lb-sum"><div><b>'.$d['ribbons'].'</b><small>'.($d['ribbons']===1?'ribbon':'ribbons').' earned</small></div><div><b>'.($now?'Level '.e(strtoupper($now['k'])?:(string)$now['n']):($d['left']?'—':'Done!')).'</b><small>where you are now</small></div><div><b>'.$d['left'].'</b><small>'.($d['left']===1?'level':'levels').' to the castle</small></div></div>';
 echo '<div class="lb-box" data-lb="'.e(json_encode($d,JSON_UNESCAPED_SLASHES)).'"><ol class="lb-list">';
 foreach($d['tiers'] as $t)foreach($t['parts'] as $p)echo '<li class="lb-'.e($p['s']).'"><b>Level '.$t['n'].e($p['k']?' '.$p['k']:'').'</b> '.e($p['t']).' · '.e(['done'=>$p['note']??'Finished','skip'=>$p['note']??'Started higher','cur'=>'You are here · '.$p['pct'].'% done','lock'=>'Locked'][$p['s']]).'</li>';
 echo '</ol></div><div class="lb-legend"><span><i class="g"></i>Finished</span><span><i class="y"></i>You are here</span><span><i class="k"></i>Locked</span><span><i class="r"></i>Ribbon: a level you finished'.(in_array('skip',array_merge(...array_map(fn($t)=>array_column($t['parts'],'s'),$d['tiers'])),true)?' or started above':'').'</span></div>';
}

/* ---------- Teacher: Reading Progress Report Card ---------- */
/** A Bukidnon woven border (red band, black and yellow saw-teeth, white diamonds). */
function rc_tribal(int $h,string $id):string{
 return '<svg class="rc-tribal" viewBox="0 0 960 '.$h.'" preserveAspectRatio="none" aria-hidden="true"><defs><pattern id="'.$id.'" width="48" height="'.$h.'" patternUnits="userSpaceOnUse"><rect width="48" height="'.$h.'" fill="#c8202f"/><path d="M0 0L12 '.($h*.32).'L24 0L36 '.($h*.32).'L48 0Z" fill="#1d1d1b"/><path d="M0 '.$h.'L12 '.($h*.68).'L24 '.$h.'L36 '.($h*.68).'L48 '.$h.'Z" fill="#f6c02c"/><path d="M24 '.($h*.5-5).'L31 '.($h*.5).'L24 '.($h*.5+5).'L17 '.($h*.5).'Z" fill="#fff"/><path d="M0 '.($h*.5-3).'L4 '.($h*.5).'L0 '.($h*.5+3).'ZM48 '.($h*.5-3).'L44 '.($h*.5).'L48 '.($h*.5+3).'Z" fill="#fff"/><path d="M6 '.($h*.5).'H12M36 '.($h*.5).'H42" stroke="#1d1d1b" stroke-width="2"/></pattern></defs><rect width="960" height="'.$h.'" fill="url(#'.$id.')"/></svg>';
}
/** A logo uploaded in Settings > School info ('division' or 'school'), or an empty circle. */
function rc_logo(string $which):string{
 $f=$which==='division'?division_logo():(function_exists('admin_setting')?admin_setting('logo_'.$which,''):'');
 if($f!==''&&is_file(__DIR__.'/../public/'.$f))return '<img class="rc-logo" src="'.e($f).'" alt="'.($which==='division'?'Division':'School').' logo">';
 return '<span class="rc-slot"><span>'.($which==='division'?'Division':'School').'<br>logo</span></span>';
}
/** One A4 report card. */
function rc_card(int $pid,string $teacher):string{
 static $k=0;$k++;
 $p=one('SELECT u.name,u.public_id,pp.grade_level,pp.section,d.lrn,d.sex FROM users u JOIN pupils pp ON pp.user_id=u.id LEFT JOIN pupil_details d ON d.pupil_id=u.id WHERE u.id=?',[$pid]);if(!$p)return '';
 $lv=rb_levels($pid);$set=fn($key,$def='')=>function_exists('admin_setting')?admin_setting($key,$def):$def;$div=trim($set('school_district',''))?:'Division of Bukidnon';
 $kid=char_src(($p['sex']??'')==='female'?'girl':'boy','cheer');
 /* rows from Level 7 (top) down to Level 1, so they line up with the ladder */
 $rows=[];foreach(array_reverse(RB_STEPS,true) as $n=>$parts){$st=array_map(fn($x)=>$lv[$x[0]],$parts);$ss=array_column($st,'s');
  $state=in_array('cur',$ss,true)?'cur':(count(array_filter($ss,fn($s)=>$s==='done'))===count($ss)?'done':(count(array_filter($ss,fn($s)=>$s==='skip'||$s==='done'))===count($ss)?'skip':'lock'));
  $dates=array_filter(array_column($st,'date'));$date=$dates?date('M j, Y',max(array_map('strtotime',$dates))):'';
  $sub='';if(count($parts)>1&&count(array_unique($ss))+count(array_filter(array_column($st,'post'),fn($v)=>$v!==null))>1){$bits=[];foreach($parts as $i=>$x){$y=$st[$i];$bits[]=$x[1].($y['s']==='done'?($y['post']!==null?' · '.$y['post'].'%':' · passed'):($y['s']==='skip'?' · started higher':($y['s']==='cur'?' · '.$y['pct'].'%':'')));}$sub=implode('  ·  ',$bits);}
  if($state==='done'){$one=count($st)===1&&$st[0]['post']!==null;$rem='<span class="rm pass">'.icon('check').'Passed'.($one?' · '.$st[0]['post'].'%':'').'</span>';}
  elseif($state==='cur'){$c=$st[array_search('cur',$ss,true)];$rem=$c['retry']?'<span class="rm now">Post-test '.$c['post'].'% · retaking</span>':'<span class="rm now">In progress · '.$c['pct'].'%</span>';}
  elseif($state==='skip')$rem='<span class="rm skip">Started higher</span>';
  else $rem='<span class="rm no">Not yet</span>';
  $rows[]=['n'=>$n,'name'=>$parts[0][2],'sub'=>$sub,'state'=>$state,'rem'=>$rem,'date'=>$date];}
 /* the reading ladder beside the table */
 $RH=92;$N=count($rows);$svg='';
 foreach($rows as $i=>$r){$y=$i*$RH;$x=($N-1-$i)*14;$col=RB_COLORS[$r['n']];
  $svg.='<g'.($r['state']==='lock'?' opacity=".45"':'').'><rect x="'.$x.'" y="'.($y+8).'" width="62" height="'.($RH-10).'" rx="8" fill="'.$col[0].'"/><rect x="'.$x.'" y="'.($y+8).'" width="62" height="16" rx="8" fill="#fff" opacity=".22"/><text x="'.($x+31).'" y="'.($y+52).'" text-anchor="middle" font-weight="700" font-size="28" fill="#fff">'.$r['n'].'</text><text x="'.($x+31).'" y="'.($y+70).'" text-anchor="middle" font-weight="700" font-size="9" letter-spacing="1.5" fill="#fff">LEVEL</text></g>';
  if($r['state']==='done'||$r['state']==='skip'){$cx=$x+84;$cy=$y+28;$pts=[];for($a=0;$a<16;$a++){$rr=$a%2?12:14;$pts[]=round($cx+$rr*cos($a*M_PI/8),1).','.round($cy+$rr*sin($a*M_PI/8),1);}
   $gold=['#ffd23f','#c99400',$col[0]];
   $svg.='<path d="M'.($cx-7).' '.($cy+6).'l-5 16 5-3 4 5 3-17z" fill="'.$gold[2].'"/><path d="M'.($cx+7).' '.($cy+6).'l5 16-5-3-4 5-3-17z" fill="'.$gold[2].'"/><polygon points="'.implode(' ',$pts).'" fill="'.$gold[0].'" stroke="'.$gold[1].'"/><circle cx="'.$cx.'" cy="'.$cy.'" r="8" fill="'.$gold[2].'"/><path d="M'.($cx-3.5).' '.$cy.'l2.5 2.5 5-5.5" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round"/>';}
  if($r['state']==='cur')$svg.='<g transform="translate('.($x+70).' '.($y+6).')"><rect width="62" height="20" rx="10" fill="#0e3b28"/><text x="31" y="14" text-anchor="middle" font-weight="700" font-size="9" fill="#ffd966">CURRENT</text></g><image href="'.e($kid).'" x="'.($x+66).'" y="'.($y+22).'" width="46" height="64" preserveAspectRatio="xMidYMax meet"/>';}
 $tr='';foreach($rows as $r)$tr.='<tr class="'.$r['state'].'"><td class="ds"><b>'.e($r['name']).'</b>'.($r['sub']!==''?'<small>'.e($r['sub']).'</small>':'').'</td><td>'.$r['rem'].'</td><td class="dt">'.e($r['date']).'</td><td><span class="sig"></span></td></tr>';
 $now=null;foreach(RB_STEPS as $n=>$parts)foreach($parts as [$L,$kk,$name])if($lv[$L]['s']==='cur')$now=['n'=>$n,'k'=>$kk,'t'=>$name,'pct'=>$lv[$L]['pct']];
 $allDone=!array_filter($lv,fn($x)=>$x['s']==='cur'||$x['s']==='lock');
 $nowText=$now?'Now on <b>Level '.e(strtoupper($now['k'])?:(string)$now['n']).' · '.e($now['t']).'</b> ('.$now['pct'].'% of its lessons done).':($allDone?'<b>Finished every level of BULIG.</b>':'The starting level is not set yet.');
 $head=trim($set('school_head',''));
 return '<article class="rc-page"><div class="rc-band">'.rc_logo('division').'<div class="rc-mid">Republic of the Philippines<b>Department of Education · Region X</b>'.e($div).'</div>'.rc_logo('school').'</div>'.rc_tribal(14,'rct'.$k.'a')
  .'<div class="rc-title"><img src="assets/bulig-logo.png" alt="BULIG"><h1>Reading Progress Report Card<small>BEGINNING READING · ENGLISH</small></h1></div>'
  .'<div class="rc-info"><div><label>Name</label><span>'.e($p['name']).'</span></div><div><label>LRN</label><span>'.e((string)($p['lrn']??'')).'</span></div><div><label>Grade &amp; Section</label><span>Grade '.(int)$p['grade_level'].' – '.e((string)$p['section']).'</span></div>'
  .'<div><label>School</label><span>'.e($set('school_name','')).'</span></div><div><label>School Year</label><span>'.e(function_exists('admin_school_year')?admin_school_year():'').'</span></div><div><label>Reading Teacher</label><span>'.e($teacher).'</span></div></div>'
  .'<div class="rc-grid"><div class="rc-ladder"><div class="rc-lh">THE READING LADDER</div><svg viewBox="0 0 200 '.($N*$RH).'" font-family="Poppins,sans-serif" aria-hidden="true">'.$svg.'</svg></div>'
  .'<table><thead><tr><th>Description</th><th>Remarks</th><th>Date of Assessment</th><th>Assessor’s Signature</th></tr></thead><tbody>'.$tr.'</tbody></table></div>'
  .'<div class="rc-bottom"><div class="rc-now">'.$nowText.' Passed = 80% or higher on the level’s post-test.</div><div class="rc-signs"><div><b>'.e($teacher).'</b>Reading Teacher</div><div><b>'.($head!==''?e($head):'&nbsp;').'</b>School Head</div><div><b>&nbsp;</b>Parent / Guardian</div></div>'.rc_tribal(14,'rct'.$k.'b').'</div></article>';
}
/** Teacher page: the card of one pupil (?id=) or of every pupil in a section (?section=). */
function rc_view(array $u):void{
 $who=(string)($_GET['who']??'');if($who!==''){if($who[0]==='s')$_GET['section']=(int)substr($who,1);elseif($who[0]==='p')$_GET['id']=(int)substr($who,1);}
 $tid=(int)$u['id'];$sid=(int)($_GET['section']??0);$pid=(int)($_GET['id']??0);
 if($sid){$s=owned_section($sid,$tid);$ids=array_map('intval',array_column(rows('SELECT u.id FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id AND t.teacher_id=? WHERE ps.section_id=? AND u.active=1 ORDER BY u.name',[$tid,$sid]),'id'));$title='Grade '.(int)$s['grade_level'].' – '.$s['name'];$back='?page=sections';}
 else{own_pupil($pid);$ids=[$pid];$title=(string)val('SELECT name FROM users WHERE id=?',[$pid]);$back='?page=pupil&id='.$pid;}
 $logos=division_logo()!==''&&admin_setting('logo_school','')!=='';
 echo '<div class="no-print rc-actions"><div><span class="eyebrow">READING PROGRESS REPORT CARD</span><h1>'.e($title).'</h1><p class="muted">'.($sid?count($ids).' '.(count($ids)===1?'card':'cards').', one page each.':'One page, A4.').' Remarks, dates and the ladder come from BULIG. Sign the cards after printing.</p>'
  .(!$logos?'<p class="rc-tip">'.icon('info').'The Division and school logos print in the circles once your administrator uploads them in Settings → School info.</p>':'').'</div>'
  .'<div class="rc-btns"><a class="btn quiet" href="'.e($back).'">'.icon('arrow','flip').'Back</a>'.($ids?'<button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button>':'').'</div></div>';
 if(!$ids){echo '<section class="card"><p class="muted">No pupils in this section yet.</p></section>';return;}
 echo '<div class="rc-sheets">';foreach($ids as $id)echo rc_card($id,(string)$u['name']);echo '</div>';
}

/* ---------- Admin: Division and school logos (Settings > School info) ---------- */
function rc_logo_settings():string{
 $h='<section class="card rc-logoset"><div class="ws-hd"><h2>'.icon('image').' Report card logos</h2><small class="muted">Printed at the top of every Reading Progress Report Card</small></div><div class="rc-logo-grid">';
 foreach(['division'=>'Division logo','school'=>'School logo'] as $w=>$label){$f=admin_setting('logo_'.$w,'');$has=$f!==''&&is_file(__DIR__.'/../public/'.$f);
  $own=!$has&&$w==='division'&&division_logo()!=='';   // no upload: the Sangay ng Bukidnon logo that comes with BULIG
  $h.='<div class="rc-logo-row">'.($has||$own?'<img src="'.e($has?$f:division_logo()).'" alt="'.e($label).'">':'<span class="rc-slot"><span>'.e($label).'</span></span>').'<div><b>'.e($label).'</b><form method="post" enctype="multipart/form-data" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="admin_logo"><input type="hidden" name="which" value="'.$w.'"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required><button class="btn secondary small">'.($has?'Replace':'Upload').'</button></form>'
   .($has?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_logo_remove"><input type="hidden" name="which" value="'.$w.'"><button class="btn quiet small">Remove</button></form>':'<small class="muted">'.($own?'Using the Sangay ng Bukidnon logo that comes with BULIG. Upload only to use another one. ':'').'PNG, JPEG or WebP, smaller than 4 MB. A square picture looks best.</small>').'</div></div>';}
 return $h.'</div></section>';
}
function rc_logo_action(string $action):void{
 $w=(string)($_POST['which']??'');if(!in_array($w,['division','school'],true))fail('Choose which logo.',400);
 $old=admin_setting('logo_'.$w,'');
 if($action==='admin_logo'){$f=save_uploaded_image('logo');admin_set('logo_'.$w,$f);audit('report_card_logo',$w.' uploaded');flash(($w==='division'?'Division':'School').' logo saved.');}
 else{admin_set('logo_'.$w,'');audit('report_card_logo',$w.' removed');flash(($w==='division'?'Division':'School').' logo removed.');}
 if($old!==''&&str_starts_with($old,'assets/uploads/')&&is_file(__DIR__.'/../public/'.$old))@unlink(__DIR__.'/../public/'.$old);
 go('?page=settings&tab=school');
}
