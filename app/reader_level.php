<?php
/* Reader level: a game level from 1 to 100 that grows with the pupil's XP. Early levels come quickly; later levels need
   more XP (Level 2 needs 20 XP, Level 100 needs 5,473 XP). Every grade can reach Level 100 by finishing BULIG.
   Head start: a pupil whose teacher started them at a higher BULIG level gets the XP of every published activity in the
   levels they skipped (for their grade). Activities they already did there are left out, so no XP is counted twice.
   No new table: the head start is worked out from the starting level, and the level-up popups use the pupil's "seen"
   record (settings key pupil_seen_<id>, see pupil_celebrations()). */

const RL_MAX=100;
/* A new title every 10 levels: [first level, title, icon]. */
const RL_TIERS=[[1,'Little Reader','sprout'],[10,'Word Finder','find'],[20,'Sound Explorer','sound'],[30,'Story Seeker','map'],[40,'Page Turner','page'],[50,'Book Buddy','book'],[60,'Bright Reader','sun'],[70,'Story Star','star'],[80,'Reading Hero','shield'],[90,'Reading Champion','trophy'],[100,'Master Reader','crown']];

/** XP needed to go from level $n to level $n+1. */
function rl_step(int $n):int{return 20+(int)round(.72*($n-1));}
/** Total XP needed to reach level $l. */
function rl_need(int $l):int{static $t=null;if($t===null){$t=[1=>0];for($n=1;$n<RL_MAX;$n++)$t[$n+1]=$t[$n]+rl_step($n);}return $t[max(1,min(RL_MAX,$l))];}
/** Level, XP inside the level (cur) and XP the level needs (need) for an XP total. */
function rl_from_xp(int $xp):array{$l=1;while($l<RL_MAX&&$xp>=rl_need($l+1))$l++;$max=$l>=RL_MAX;return ['level'=>$l,'xp'=>$xp,'cur'=>$max?0:$xp-rl_need($l),'need'=>$max?0:rl_step($l),'max'=>$max];}
function rl_tier(int $l):array{$t=RL_TIERS[0];foreach(RL_TIERS as $x)if($l>=$x[0])$t=$x;return $t;}
function rl_pct(array $r):int{return $r['max']?100:(int)floor(100*$r['cur']/max(1,$r['need']));}

/** Head-start XP for each BULIG level below the pupil's starting level: [level id => XP]. */
function rl_headstart(int $pid,bool $fresh=false):array{
 static $c=[];if(!$fresh&&isset($c[$pid]))return $c[$pid];$out=[];
 $start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);
 if($start>1)foreach(rows('SELECT m.level_id,COALESCE(SUM(a.xp_reward),0) xp FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id LEFT JOIN xp_transactions x ON x.activity_id=a.id AND x.pupil_id=? WHERE a.published=1 AND l.published=1 AND m.level_id<? AND x.activity_id IS NULL'.GRADE_SQL.' GROUP BY m.level_id ORDER BY m.level_id',[$pid,$start,pupil_grade($pid)]) as $r)if((int)$r['xp']>0)$out[(int)$r['level_id']]=(int)$r['xp'];
 return $c[$pid]=$out;
}
/** All the pupil's XP for the reader level: earned XP plus head-start XP. */
function rl_xp(int $pid,bool $fresh=false):int{return (int)val('SELECT total FROM pupil_xp WHERE pupil_id=?',[$pid])+array_sum(rl_headstart($pid,$fresh));}
function rl_info(int $pid,bool $fresh=false):array{return rl_from_xp(rl_xp($pid,$fresh));}

/** After XP is added: tell the pupil (bell) when they reach a new reader level. */
function rl_after_xp(int $pid,int $before):void{
 if(!function_exists('notify'))return;$r=rl_info($pid,true);if($r['level']<=$before)return;$t=rl_tier($r['level']);
 notify($pid,'rlevel','You reached Level '.$r['level'].'!',(rl_tier($before)[0]!==$t[0]?'New title: '.$t[1].'. ':'').($r['max']?'This is the highest level!':($r['need']-$r['cur']).' XP to Level '.($r['level']+1).'.'),'?page=achievements#reader-level');
}

/** For the teacher's pupil page: "Reader level 93 (Reading Champion)". */
function rl_pupil_line(int $pid):string{$r=rl_info($pid);return 'Reader level '.$r['level'].' ('.rl_tier($r['level'])[1].')';}

/* ---------- Pictures ---------- */
function rl_icon(string $k):string{
 $p=['sprout'=>'<path d="M12 21v-8"/><path d="M12 13c0-4-3-6-7-6 0 4 3 6 7 6z"/><path d="M12 11c0-4 3-6 7-6 0 4-3 6-7 6z"/>','find'=>'<circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/>','sound'=>'<path d="M4 10v4h4l5 4V6L8 10z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>','map'=>'<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>','page'=>'<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 13h7M9 17h5"/>','book'=>'<path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v14c-3-1-6-1-8 1-2-2-5-2-8-1z"/><path d="M12 6v14"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>','star'=>'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>','shield'=>'<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>','trophy'=>'<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4"/><path d="M12 13v4M9 21h6"/>','crown'=>'<path d="M3 8l4 4 5-7 5 7 4-4-2 11H5z"/>','lock'=>'<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>','up'=>'<path d="m6 11 6-6 6 6"/><path d="M12 5v14"/>','next'=>'<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>'];
 return '<svg class="icon rl-ic" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($p[$k]??$p['star']).'</svg>';
}
/** The round level badge: a gold ring shows how far the pupil is into the level. Sizes: rl-ring (card), -md, -lg. */
function rl_ring(int $level,int $pct,string $cls=''):string{
 $pct=max(0,min(100,$pct));
 return '<span class="rl-ring '.$cls.'" aria-hidden="true"><svg viewBox="0 0 44 44"><circle class="rl-ring-bg" cx="22" cy="22" r="19.5" fill="none"/><circle class="rl-ring-fill" cx="22" cy="22" r="19.5" fill="none" pathLength="100" stroke-dasharray="'.$pct.' 100" transform="rotate(-90 22 22)"/></svg><b><small>LV</small>'.$level.'</b></span>';
}

/* ---------- Pupil home: "My reader level" card ---------- */
function rl_card(int $pid):string{
 $r=rl_info($pid);$t=rl_tier($r['level']);$next=$r['level']+1;
 $h='<a class="pupil-stat xp-stat rl-stat" href="?page=achievements#reader-level" aria-label="My reader level: Level '.$r['level'].', '.e($t[1]).'. '.($r['max']?'The highest level.':$r['cur'].' of '.$r['need'].' XP to Level '.$next.'.').'"><span class="stat-label">'.icon('star').'My reader level</span><span class="rl-row">'.rl_ring($r['level'],rl_pct($r)).'<span class="rl-name"><b>'.rl_icon($t[2]).e($t[1]).'</b><small>'.number_format($r['xp']).' XP in all</small></span></span>';
 if($r['max'])return $h.'<span class="stat-foot">You reached the highest level!</span></a>';
 return $h.'<span class="pfx-xp" data-pfx-xp="'.$pid.'" data-xp="'.$r['xp'].'"><progress data-pf-fill value="'.$r['cur'].'" max="'.$r['need'].'"></progress><span class="pfx-shine" aria-hidden="true"></span></span><span class="stat-foot">'.$r['cur'].' / '.$r['need'].' XP to Level '.$next.'</span></a>';
}

/* ---------- My achievements: the level road from Level 1 to 100 ---------- */
function rl_road(int $pid):string{
 $r=rl_info($pid);$t=rl_tier($r['level']);$hs=array_sum(rl_headstart($pid));
 $h='<section class="card rl-road" id="reader-level" aria-label="My reader level"><div class="rl-rhead">'.rl_ring($r['level'],rl_pct($r),'rl-ring-md').'<div class="rl-rtext"><h2>My reader level</h2><p class="muted">Level '.$r['level'].' · '.e($t[1]).' · '.number_format($r['xp']).' XP in all'.($hs?' (with '.number_format($hs).' head-start XP)':'').'</p>';
 $h.=$r['max']?'<p class="rl-top">'.rl_icon('crown').'You reached Level 100, the highest level. You are a Master Reader!</p>':'<progress class="rl-bar" value="'.$r['cur'].'" max="'.$r['need'].'" aria-label="XP toward Level '.($r['level']+1).'"></progress><small>'.$r['cur'].' / '.$r['need'].' XP to Level '.($r['level']+1).'. A new title every 10 levels!</small>';
 $h.='</div></div><ol class="rl-stops">';
 foreach(RL_TIERS as $x){$st=$x[0]===$t[0]?'now':($x[0]<$t[0]?'done':'');$h.='<li class="rl-stop'.($st?' rl-'.$st:'').'">'.($st==='now'?'<span class="rl-here">YOU ARE HERE</span>':'').'<span class="rl-sic">'.rl_icon($st===''?'lock':$x[2]).'</span><small>LV '.$x[0].'</small><b>'.e($x[1]).'</b></li>';}
 return $h.'</ol><div class="rl-how"><span>'.icon('check').'Finish an activity: +5 to +20 XP</span><span>'.rl_icon('up').'Skipped levels give XP too</span><span>'.icon('star').'Highest level: 100</span></div></section>';
}

/* ---------- Popups: head start and level up (shown once each, from pupil_celebrations) ---------- */
function rl_level_name(int $level,int $pid):string{
 $title=[1=>'Oral Language',2=>'Sounds in Words',3=>'Sounds in Words',4=>'Word Recognition',5=>'Fluency',6=>'Listening & Vocabulary',7=>'Comprehension',8=>'Love for Reading'][$level]??'';
 $graded=(bool)val('SELECT 1 FROM modules WHERE level_id=? AND grade_level IS NOT NULL LIMIT 1',[$level]);
 return level_label($level).($title!==''?' · '.$title:'').($graded?' (Grade '.pupil_grade($pid).')':'');
}
function rl_celebration(array $u,array &$seen,int &$shown):string{
 $pid=(int)$u['id'];$r=rl_info($pid);$t=rl_tier($r['level']);$hs=rl_headstart($pid);$sum=array_sum($hs);
 $h0=(int)($seen['h']??0);$r0=(int)($seen['r']??0);$first=explode(' ',trim((string)$u['name']))[0];$out='';
 $conf='<div class="lc-confetti" aria-hidden="true">'.str_repeat('<i></i>',18).'</div>';$close='<button type="button" class="pw-close" data-welcome-close aria-label="Close">'.icon('close').'</button>';
 if($sum>$h0&&$shown<3){$shown++;
  $from=rl_from_xp(max(0,$r['xp']-($sum-$h0)))['level'];$start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);$li='';
  foreach($hs as $lv=>$xp)$li.='<li><span>'.e(rl_level_name($lv,$pid)).'</span><b>+'.number_format($xp).' XP</b></li>';
  $out.='<div class="pw-overlay pf-cel rl-cel" data-pf-overlay hidden role="dialog" aria-modal="true" aria-labelledby="rl-hs"><div class="pw-panel pf-cel-panel">'.$close.$conf.pf_ribbon('HEAD START!',200,'pf-green').'<h2 id="rl-hs">You skipped ahead, '.e($first).'!</h2><p class="pw-msg">Your teacher started you at '.e(level_label($start)).'. You still get the XP for the levels you skipped.</p>'
   .'<ul class="rl-hs-list">'.$li.'<li class="rl-hs-total"><span>Head-start XP</span><b>+'.number_format($sum).' XP</b></li></ul>'
   .'<div class="rl-jump">'.rl_ring($from,0).'<span class="rl-arrow">'.rl_icon('next').'</span>'.rl_ring($r['level'],rl_pct($r),'rl-ring-md').'</div><p class="rl-title-chip">'.rl_icon($t[2]).'Your title: <b>'.e($t[1]).'</b></p>'
   .'<div class="pw-actions"><button type="button" class="btn primary" data-welcome-close>Let’s go!</button><a class="btn secondary" href="?page=achievements#reader-level">See my level road</a></div></div></div>';
 }elseif($r0>0&&$r['level']>$r0&&$shown<3){$shown++;
  $newTitle=rl_tier($r0)[0]!==$t[0];
  $out.='<div class="pw-overlay pf-cel rl-cel" data-pf-overlay hidden role="dialog" aria-modal="true" aria-labelledby="rl-up"><div class="pw-panel pf-cel-panel">'.$close.$conf.'<div class="rl-stage" aria-hidden="true"><div class="pf-burst"></div>'.rl_ring($r['level'],100,'rl-ring-lg').'</div>'.pf_ribbon('LEVEL UP!',190,'pf-green')
   .'<h2 id="rl-up">You reached Level '.$r['level'].', '.e($first).'!</h2>'.($newTitle?'<p class="rl-title-chip">'.rl_icon($t[2]).'New title: <b>'.e($t[1]).'</b></p>':'')
   .'<p class="pw-msg">'.($r['max']?'This is the highest level. You are a Master Reader!':($r['need']-$r['cur']).' XP to Level '.($r['level']+1).'. Keep reading!').'</p>'
   .'<div class="pw-actions"><button type="button" class="btn primary" data-welcome-close>Yay!</button><a class="btn secondary" href="?page=achievements#reader-level">See my level road</a></div></div></div>';
 }
 $seen['h']=$sum;$seen['r']=$r['level'];return $out;
}
