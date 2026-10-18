<?php
/* Reader level: a game level from 1 to 100 that grows with the pupil's XP. Early levels come quickly; later levels need
   more XP (Level 2 needs 20 XP, Level 100 needs 5,473 XP). Every grade can reach Level 100 by finishing BULIG.
   Head start: a pupil whose teacher started them at a higher BULIG level gets the XP of every published activity in the
   levels they skipped (for their grade). Activities they already did there are left out, so no XP is counted twice.
   A quarter of it comes right away; the rest comes a little at a time as they finish the activities of their own levels,
   all of it by the time they finish. (Giving it all at once put a Level 6 starter straight at reader Level 100.)
   No new table: the head start is worked out from the starting level, and the level-up popups use the pupil's "seen"
   record (settings key pupil_seen_<id>, see pupil_celebrations()). */

const RL_MAX=100;
/* Share of the head-start XP given right away. */
const RL_HS_NOW=.25;
/* Ranks: 10 ranks of 10 reader levels each (Levels 1-10 Beginner, 11-20 Learner, ... 91-100 Mythic). Every 2 reader levels
   give a star, 5 stars in each rank; the level after the 5th star gives the next rank. XP never goes down, so stars and ranks
   are never taken away. Emblems: public/assets/images/ranks/<key>.webp (small copies in ranks/sm/ for chips). */
const RK_RANKS=[['beginner','Beginner'],['learner','Learner'],['explorer','Explorer'],['achiever','Achiever'],['champion','Champion'],['guardian','Guardian'],['elite','Elite'],['master','Master'],['legend','Legend'],['mythic','Mythic']];
const RK_STARS=5;

/** XP needed to go from level $n to level $n+1. */
function rl_step(int $n):int{return 20+(int)round(.72*($n-1));}
/** Total XP needed to reach level $l. */
function rl_need(int $l):int{static $t=null;if($t===null){$t=[1=>0];for($n=1;$n<RL_MAX;$n++)$t[$n+1]=$t[$n]+rl_step($n);}return $t[max(1,min(RL_MAX,$l))];}
/** Level, XP inside the level (cur) and XP the level needs (need) for an XP total. */
function rl_from_xp(int $xp):array{$l=1;while($l<RL_MAX&&$xp>=rl_need($l+1))$l++;$max=$l>=RL_MAX;return ['level'=>$l,'xp'=>$xp,'cur'=>$max?0:$xp-rl_need($l),'need'=>$max?0:rl_step($l),'max'=>$max];}
function rl_pct(array $r):int{return $r['max']?100:(int)floor(100*$r['cur']/max(1,$r['need']));}
/** The rank of a reader level: i (0-9), k (emblem file), name, from / to (its levels) and stars (0-5). */
function rk_of(int $level):array{$level=max(1,min(RL_MAX,$level));$i=min(count(RK_RANKS)-1,intdiv($level-1,10));$from=$i*10+1;return ['i'=>$i,'k'=>RK_RANKS[$i][0],'name'=>RK_RANKS[$i][1],'from'=>$from,'to'=>$from+9,'stars'=>min(RK_STARS,intdiv($level-$from+1,2))];}
/** A rank emblem picture ($small: the light copy for chips). */
function rk_img(int $i,string $cls,bool $small=false,string $alt=''):string{
 $k=RK_RANKS[$i][0];$f='assets/images/ranks/'.($small?'sm/':'').$k.'.webp';if(!is_file(__DIR__.'/../public/'.$f))$f='assets/images/ranks/'.$k.'.webp';
 return '<img class="'.$cls.'" src="'.e($f).'" alt="'.e($alt).'" decoding="async">';
}
/** The 5 rank stars, $n of them filled. */
function rk_stars(int $n,string $cls=''):string{
 $s='';for($x=0;$x<RK_STARS;$x++)$s.='<svg viewBox="0 0 24 24" class="'.($x<$n?'on':'off').'"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5-4.8-4.6 6.6-.9z" stroke-width="1.3" stroke-linejoin="round"/></svg>';
 return '<span class="rk-stars'.($cls?' '.$cls:'').'" role="img" aria-label="'.$n.' of '.RK_STARS.' stars">'.$s.'</span>';
}
/** Teacher lists: "Lv 39 [emblem] Achiever 4/5". */
function rk_chip(int $pid):string{$r=rl_info($pid);$k=rk_of($r['level']);return '<span class="rk-inl" title="Reader level '.$r['level'].' · '.e($k['name']).', '.$k['stars'].' of '.RK_STARS.' stars"><span class="rk-lvn">Lv '.$r['level'].'</span>'.rk_img($k['i'],'',true).e($k['name']).'<span class="rk-n">'.$k['stars'].'/'.RK_STARS.' '.rk_star1().'</span></span>';}
/** "an Achiever", "a Champion". */
function rk_a(string $name):string{return (preg_match('~^[AEIOU]~',$name)?'an ':'a ').$name;}
function rk_star1():string{return '<svg class="rk-s1" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5-4.8-4.6 6.6-.9z"/></svg>';}

/** Head-start XP for each BULIG level below the pupil's starting level: [level id => XP]. */
function rl_headstart(int $pid,bool $fresh=false):array{
 static $c=[];if(!$fresh&&isset($c[$pid]))return $c[$pid];$out=[];
 $start=pupil_start_level($pid);
 if($start>1)foreach(rows('SELECT m.level_id,COALESCE(SUM(a.xp_reward),0) xp FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id LEFT JOIN xp_transactions x ON x.activity_id=a.id AND x.pupil_id=? WHERE a.published=1 AND l.published=1 AND m.level_id<? AND x.activity_id IS NULL'.GRADE_SQL.' GROUP BY m.level_id ORDER BY m.level_id',[$pid,$start,pupil_grade($pid)]) as $r)if((int)$r['xp']>0)$out[(int)$r['level_id']]=(int)$r['xp'];
 return $c[$pid]=$out;
}
/** How far the pupil is through their own levels (the starting level and up), from 0 to 1: the XP of the activities they
    finished there, out of the XP of all of them. */
function rl_path_share(int $pid,bool $fresh=false):float{
 static $c=[];if(!$fresh&&isset($c[$pid]))return $c[$pid];
 $start=max(1,pupil_start_level($pid));
 $r=one('SELECT COALESCE(SUM(a.xp_reward),0) total,COALESCE(SUM(IF(x.activity_id IS NULL,0,a.xp_reward)),0) done FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id JOIN bulig_levels b ON b.id=m.level_id AND b.published=1 LEFT JOIN xp_transactions x ON x.activity_id=a.id AND x.pupil_id=? WHERE a.published=1 AND l.published=1 AND m.level_id>=?'.GRADE_SQL,[$pid,$start,pupil_grade($pid)]);
 return $c[$pid]=(int)$r['total']>0?min(1.0,(int)$r['done']/(int)$r['total']):1.0;
}
/** Head-start XP the pupil has so far: a quarter right away, the rest bit by bit as they finish their own activities. */
function rl_headstart_now(int $pid,bool $fresh=false):int{
 $all=array_sum(rl_headstart($pid,$fresh));
 return $all?(int)floor($all*(RL_HS_NOW+(1-RL_HS_NOW)*rl_path_share($pid,$fresh))):0;
}
/** All the pupil's XP for the reader level: earned XP plus the head-start XP they have so far. */
function rl_xp(int $pid,bool $fresh=false):int{return (int)val('SELECT total FROM pupil_xp WHERE pupil_id=?',[$pid])+rl_headstart_now($pid,$fresh);}
function rl_info(int $pid,bool $fresh=false):array{return rl_from_xp(rl_xp($pid,$fresh));}

/** After XP is added: tell the pupil (bell) when they reach a new reader level. */
function rl_after_xp(int $pid,int $before):void{
 if(!function_exists('notify'))return;$r=rl_info($pid,true);if($r['level']<=$before)return;
 $k=rk_of($r['level']);$k0=rk_of($before);notify($pid,'rlevel',$k['i']>$k0['i']?'Rank up! You are now '.rk_a($k['name']).'!':'You reached Level '.$r['level'].'!',($k['i']>$k0['i']?'Level '.$r['level'].'. ':($k['stars']>$k0['stars']?'New star: '.$k['stars'].' of '.RK_STARS.'. ':'')).($r['max']?'This is the highest level!':($r['need']-$r['cur']).' XP to Level '.($r['level']+1).'.'),'?page=achievements#reader-level');
}

/** For the teacher's pupil page: "Reader level 93 · Mythic rank, 1 of 5 stars". */
function rl_pupil_line(int $pid):string{$r=rl_info($pid);$k=rk_of($r['level']);return 'Reader level '.$r['level'].' · '.$k['name'].' rank, '.$k['stars'].' of '.RK_STARS.' stars';}

/** Reader-level badges above the pupil's level are taken back (the old head start gave them all at once). Called by
    award_badges(); rl_celebration() also forgets them in the "seen" record so each pops up again when really reached. */
function rl_fix_badges(int $pid,int $level):void{q("DELETE pb FROM pupil_badges pb JOIN badges b ON b.id=pb.badge_id WHERE pb.pupil_id=? AND b.rule_type='rlevel' AND b.threshold_value>?",[$pid,$level]);}

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
 $r=rl_info($pid);$k=rk_of($r['level']);$next=$r['level']+1;
 $h='<a class="pupil-stat xp-stat rl-stat rk-card" href="?page=achievements#reader-level" aria-label="My level and rank: '.e($k['name']).', '.$k['stars'].' of '.RK_STARS.' stars, Level '.$r['level'].'. '.($r['max']?'The highest level.':$r['cur'].' of '.$r['need'].' XP to Level '.$next.'.').'"><span class="stat-label">'.icon('star').'My level and rank</span>'
  .'<span class="rl-row">'.rk_img($k['i'],'rk-emr').'<span class="rk-rk"><b>'.e($k['name']).'</b>'.rk_stars($k['stars']).'<span class="rk-meta"><span class="rk-lvpill"><small>LV</small>'.$r['level'].'</span>'.$k['stars'].' of '.RK_STARS.' stars</span></span></span>';
 if($r['max'])return $h.'<span class="stat-foot">You reached the highest level!</span></a>';
 return $h.'<span class="pfx-xp" data-pfx-xp="'.$pid.'" data-xp="'.$r['xp'].'"><progress data-pf-fill value="'.$r['cur'].'" max="'.$r['need'].'"></progress><span class="pfx-shine" aria-hidden="true"></span></span><span class="stat-foot">'.$r['cur'].' / '.$r['need'].' XP to Level '.$next.'</span></a>';
}

/* ---------- My achievements: the level road from Level 1 to 100 ---------- */
function rl_road(int $pid):string{
 $r=rl_info($pid);$k=rk_of($r['level']);$hs=rl_headstart_now($pid);$later=array_sum(rl_headstart($pid))-$hs;$lv=$r['level'];
 $h='<section class="card rl-road rk-road" id="reader-level" aria-label="My rank and reader level"><div class="rk-head">'.rk_img($k['i'],'rk-big',false,$k['name'].' rank').'<div class="rk-htext"><h2>My rank</h2><div class="rk-rline"><span class="rk-title">'.e($k['name']).'</span>'.rk_stars($k['stars']).'</div>'
  .'<div class="rk-meta"><span class="rk-lvpill"><small>LEVEL</small>'.$lv.' of '.RL_MAX.'</span>'.number_format($r['xp']).' XP in all'.($hs?' (with '.number_format($hs).' head-start XP)':'').'</div>'.($later>0?'<p class="rl-hs-more">'.rl_icon('up').number_format($later).' more head-start XP comes as you finish your activities.</p>':'');
 if($r['max'])$h.='<p class="rl-top">'.rl_icon('crown').'You reached Level 100, the highest level. You are a Mythic reader!</p>';
 else{$tips=[$r['cur'].' / '.$r['need'].' XP to Level '.($lv+1)];
  if($k['stars']<RK_STARS){$sl=$k['from']+2*($k['stars']+1)-1;$tips[]='Level '.$sl.' gives your '.rk_ord($k['stars']+1).' star';}
  if($k['i']<count(RK_RANKS)-1)$tips[]='Level '.($k['to']+1).' makes you '.rk_a(RK_RANKS[$k['i']+1][1]).'!';
  $h.='<progress class="rl-bar rk-bar" value="'.$r['cur'].'" max="'.$r['need'].'" aria-label="XP toward Level '.($lv+1).'"></progress><p class="rk-next">'.e(implode(' · ',$tips)).'</p>';}
 $h.='</div></div><ol class="rk-ladder">';
 foreach(RK_RANKS as $i=>$x){$st=$i<$k['i']?'done':($i===$k['i']?'now':'lock');
  $h.='<li class="rk-step '.$st.'">'.($st==='now'?'<span class="rk-here">YOU ARE HERE</span>':'').($st==='lock'?'<span class="rk-lk">'.rl_icon('lock').'</span>':'').rk_img($i,'',false,$x[1].($st==='lock'?' (not yet)':'')).'<b>'.e($x[1]).'</b><small>Levels '.($i*10+1).'–'.($i*10+10).'</small></li>';}
 return $h.'</ol><div class="rk-how"><span>'.icon('check').'Finish activities to earn XP</span><span>'.icon('star').'Every 2 reader levels = 1 star</span><span>'.rl_icon('up').'Fill 5 stars to rank up</span><span>'.rl_icon('shield').'Stars are never taken away</span></div></section>';
}
/** 1st, 2nd, 3rd, 4th, 5th. */
function rk_ord(int $n):string{return $n.([1=>'st',2=>'nd',3=>'rd'][$n]??'th');}

/* ---------- Popups: head start and level up (shown once each, from pupil_celebrations) ---------- */
function rl_level_name(int $level,int $pid):string{
 $title=[1=>'Oral Language',2=>'Sounds in Words',3=>'Sounds in Words',4=>'Word Recognition',5=>'Fluency',6=>'Listening & Vocabulary',7=>'Comprehension',8=>'Love for Reading'][$level]??'';
 $graded=(bool)val('SELECT 1 FROM modules WHERE level_id=? AND grade_level IS NOT NULL LIMIT 1',[$level]);
 return level_label($level).($title!==''?' · '.$title:'').($graded?' (Grade '.pupil_grade($pid).')':'');
}
function rl_celebration(array $u,array &$seen,int &$shown):string{
 $pid=(int)$u['id'];$r=rl_info($pid);$hs=rl_headstart($pid);$sum=array_sum($hs);$now=rl_headstart_now($pid);
 $h0=(int)($seen['h']??0);$r0=(int)($seen['r']??0);if($r0>$r['level'])$seen['b']=array_values(array_diff(array_map('intval',$seen['b']??[]),array_map('intval',array_column(rows("SELECT id FROM badges WHERE rule_type='rlevel' AND threshold_value>?",[$r['level']]),'id'))));$first=explode(' ',trim((string)$u['name']))[0];$out='';
 $conf='<div class="lc-confetti" aria-hidden="true">'.str_repeat('<i></i>',18).'</div>';$close='<button type="button" class="pw-close" data-welcome-close aria-label="Close">'.icon('close').'</button>';
 if($sum>$h0&&$shown<3){$shown++;
  $from=rl_from_xp(max(0,$r['xp']-$now))['level'];$start=pupil_start_level($pid);$li='';
  foreach($hs as $lv=>$xp)$li.='<li><span>'.e(rl_level_name($lv,$pid)).'</span><b>+'.number_format($xp).' XP</b></li>';
  $out.='<div class="pw-overlay pf-cel rl-cel" data-pf-overlay hidden role="dialog" aria-modal="true" aria-labelledby="rl-hs"><div class="pw-panel pf-cel-panel">'.$close.$conf.'<div class="rl-stage rl-stage-hs" aria-hidden="true"><img class="cel-george cel-george-solo" src="'.e(char_src('george','point')).'" alt=""></div>'.pf_ribbon('HEAD START!',200,'pf-green').'<h2 id="rl-hs">You skipped ahead, '.e($first).'!</h2><p class="pw-msg">Your teacher started you at '.e(level_label($start)).'. You still get the XP for the levels you skipped.</p>'
   .'<ul class="rl-hs-list">'.$li.'<li class="rl-hs-total"><span>Head-start XP</span><b>+'.number_format($sum).' XP</b></li>'
   .'<li class="rl-hs-now"><span>You get now</span><b>+'.number_format($now).' XP</b></li>'.($sum>$now?'<li class="rl-hs-later"><span>Comes as you finish your activities</span><b>+'.number_format($sum-$now).' XP</b></li>':'').'</ul>'
   .'<div class="rl-jump">'.rl_ring($from,0).'<span class="rl-arrow">'.rl_icon('next').'</span>'.rl_ring($r['level'],rl_pct($r),'rl-ring-md').'</div><p class="rl-title-chip rk-chip-pop">'.rk_img(rk_of($r['level'])['i'],'',true).'Your rank: <b>'.e(rk_of($r['level'])['name']).'</b>'.rk_stars(rk_of($r['level'])['stars']).'</p>'
   .'<div class="pw-actions"><button type="button" class="btn primary" data-welcome-close>Let’s go!</button><a class="btn secondary" href="?page=achievements#reader-level">See my level road</a></div></div></div>';
 }elseif($r0>0&&$r['level']>$r0&&$shown<3){$shown++;
  $k=rk_of($r['level']);$k0=rk_of($r0);
  if($k['i']>$k0['i'])$out.='<div class="pw-overlay pf-cel rl-cel rk-cel" data-pf-overlay hidden role="dialog" aria-modal="true" aria-labelledby="rk-up"><div class="pw-panel pf-cel-panel">'.$close.$conf.'<div class="rk-stage" aria-hidden="true"><div class="pf-burst"></div>'.rk_img($k['i'],'rk-em2').'<img class="cel-george" src="'.e(char_src('george','cheer')).'" alt="">'.'</div>'.pf_ribbon('RANK UP!',190,'pf-green')
   .'<span class="rk-lvchip">Level '.$r['level'].'</span><h2 id="rk-up">You are now '.e(rk_a($k['name'])).', '.e($first).'!</h2>'.rk_stars($k['stars'])
   .'<div class="rk-from"><span>'.rk_img($k0['i'],'',true).e($k0['name']).'</span>'.rl_icon('next').'<span>'.rk_img($k['i'],'',true).e($k['name']).'</span></div>'
   .'<p class="pw-msg">'.($r['max']?'This is the highest level. You are a Mythic reader!':'You reached Level '.$r['level'].'. Keep reading to fill all '.RK_STARS.' stars!').'</p>'
   .'<div class="pw-actions"><button type="button" class="btn primary" data-welcome-close>Yay!</button><a class="btn secondary" href="?page=achievements#reader-level">See my ranks</a></div></div></div>';
  else $out.='<div class="pw-overlay pf-cel rl-cel" data-pf-overlay hidden role="dialog" aria-modal="true" aria-labelledby="rl-up"><div class="pw-panel pf-cel-panel">'.$close.$conf.'<div class="rl-stage" aria-hidden="true"><div class="pf-burst"></div>'.rl_ring($r['level'],100,'rl-ring-lg').'<img class="cel-george" src="'.e(char_src('george','cheer')).'" alt="">'.'</div>'.pf_ribbon('LEVEL UP!',190,'pf-green')
   .'<h2 id="rl-up">You reached Level '.$r['level'].', '.e($first).'!</h2><p class="rl-title-chip rk-chip-pop">'.rk_img($k['i'],'',true).($k['stars']>$k0['stars']?'New star! ':'').'<b>'.e($k['name']).'</b>'.rk_stars($k['stars']).'</p>'
   .'<p class="pw-msg">'.($r['max']?'This is the highest level. You are a Mythic reader!':($r['need']-$r['cur']).' XP to Level '.($r['level']+1).'. Keep reading!').'</p>'
   .'<div class="pw-actions"><button type="button" class="btn primary" data-welcome-close>Yay!</button><a class="btn secondary" href="?page=achievements#reader-level">See my rank</a></div></div></div>';
 }
 $seen['h']=$sum;$seen['r']=$r['level'];return $out;
}
