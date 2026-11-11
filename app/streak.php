<?php
/* Streak flames. A day counts when the pupil answers a lesson activity (learning_days), never for signing in.
   Days 1-2 warm up (grey flame); the flame lights on day 3 and changes colour: red 3-10, blue 11-30, violet 31-50,
   pink 51 and up. Missing one day cuts the streak; once a week (Monday to Sunday) the pupil can revive it, which fills
   the missed day (table streak_revives, database/migrations/044_streak_revives.sql; without it there is no revive).
   Arah the Streak Keeper says it in every popup. What was already shown is kept in settings (no other table). */

const STREAK_TIERS=[[0,'Warming up','grey'],[3,'Red flame','red'],[11,'Blue flame','blue'],[31,'Violet flame','violet'],[51,'Pink flame','pink']];
function streak_tier(int $n):int{$t=0;foreach(STREAK_TIERS as $i=>$r)if($n>=$r[0])$t=$i;return $t;}
function streak_range(int $t):string{$lo=STREAK_TIERS[$t][0];$hi=isset(STREAK_TIERS[$t+1])?STREAK_TIERS[$t+1][0]-1:0;return $t===0?'':($hi?$lo.'–'.$hi:$lo.'+');}
function streak_revive_ready():bool{static $r=null;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'streak_revives'");}catch(Throwable $e){$r=false;}}return $r;}
function streak_set(string $k,string $v):void{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$k,$v]);}
function streak_get(string $k):string{return (string)(val('SELECT setting_value FROM settings WHERE setting_key=?',[$k])??'');}

/** Learned days plus revived days, as a set of 'Y-m-d'. */
function streak_days(int $pid):array{
 $d=array_map(fn($x)=>substr((string)$x,0,10),array_column(rows('SELECT day FROM learning_days WHERE pupil_id=?',[$pid]),'day'));
 if(streak_revive_ready())foreach(rows('SELECT day FROM streak_revives WHERE pupil_id=?',[$pid]) as $r)$d[]=substr((string)$r['day'],0,10);
 $d=array_values(array_unique($d));sort($d);return $d;
}
/** The run of days in a row that ends on $end (0 if $end is not a counted day). */
function streak_run_to(array $set,string $end):int{$n=0;while(isset($set[$end])){$n++;$end=date('Y-m-d',strtotime($end.' -1 day'));}return $n;}
/** Recount the streak (current, longest, last day) and save it; used after each answer and after a revive. */
function streak_recount(int $pid):void{
 $days=streak_days($pid);$run=0;$long=0;$prev=null;
 foreach($days as $d){$run=$prev&&date('Y-m-d',strtotime($prev.' +1 day'))===$d?$run+1:1;$long=max($long,$run);$prev=$d;}
 if($prev&&$prev<date('Y-m-d',strtotime('-1 day')))$run=0;
 q('INSERT INTO pupil_streaks VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE current_streak=VALUES(current_streak),longest_streak=VALUES(longest_streak),last_activity_date=VALUES(last_activity_date)',[$pid,$run,$long,$prev]);
}
function streak_week_start():string{return date('Y-m-d',strtotime('monday this week'));}
function streak_revive_left(int $pid):bool{return streak_revive_ready()&&!val('SELECT 1 FROM streak_revives WHERE pupil_id=? AND used_at>=?',[$pid,streak_week_start().' 00:00:00']);}

/** A streak of 3 or more that was cut. Revivable when only yesterday is missing and the weekly revive is free. */
function streak_lost(int $pid):?array{
 $set=array_flip(streak_days($pid));$today=date('Y-m-d');$y=date('Y-m-d',strtotime('-1 day'));$y2=date('Y-m-d',strtotime('-2 days'));
 if(isset($set[$y]))return null;
 if(isset($set[$y2])){$n=streak_run_to($set,$y2);$end=$y2;$one=true;}
 else{$before=array_filter(array_keys($set),fn($d)=>$d<$y);if(!$before)return null;$end=max($before);$n=streak_run_to($set,$end);$one=false;}
 if($n<3||(!$one&&$end<date('Y-m-d',strtotime('-14 days'))))return null;
 return ['n'=>$n,'end'=>$end,'tier'=>streak_tier($n),'can'=>$one&&streak_revive_left($pid),'used'=>$one&&streak_revive_ready()&&!streak_revive_left($pid)];
}

function streak_flame(int $t,string $cls=''):string{
 return '<svg viewBox="0 0 80 100" class="pf-flame flm-t'.$t.' '.$cls.'" aria-hidden="true"><ellipse cx="40" cy="70" rx="40" ry="34" fill="url(#pf-fglow)"/><path class="pf-fo" d="M40 4c8 22-18 24-10 44 3-3 6-8 6-12 22 14 20 56-4 56S2 72 22 46c0 10 5 12 9 12-7-16 11-28 9-54z" fill="url(#pf-fo)"/><path class="pf-fi" d="M40 52c3 10-8 12-5 20 2-1 3-3 3-5 10 6 8 24-2 24s-14-11-6-19c0 4 2 5 4 5-3-8 6-13 6-25z" fill="url(#pf-fi)"/><circle class="pf-em pf-e1" cx="30" cy="30" r="2.4" fill="#ffb347"/><circle class="pf-em pf-e2" cx="52" cy="26" r="2" fill="#ffd447"/><circle class="pf-em pf-e3" cx="44" cy="18" r="1.8" fill="#ff8a1f"/></svg>';
}
/** The colour tag (and the warming-up dots) under the number on the streak card. */
function streak_card_tag(int $n):string{
 $t=streak_tier($n);
 if($t===0){$dots='';for($i=0;$i<3;$i++)$dots.='<i'.($i<$n?' class="on"':'').'></i>';$left=3-$n;
  return '<span class="flm-tag t0">Warming up</span><span class="flm-warm">'.$dots.'<span>'.$left.' more '.($left===1?'day':'days').' to light your flame</span></span>';}
 return '<span class="flm-tag t'.$t.'">'.e(STREAK_TIERS[$t][1]).' <small>'.streak_range($t).'</small></span>';
}

function streak_pop(string $kind,string $eyebrow,int $t,string $num,string $title,string $text,string $extra,string $pose,string $say,bool $right):string{
 $up=str_starts_with($kind,'up');$conf='';if($up){for($i=0;$i<26;$i++)$conf.='<i class="c'.($i%5).' p'.$i.'"></i>';$conf='<div class="flm-conf" aria-hidden="true">'.$conf.'</div>';}
 return '<div class="flm-ov" role="dialog" aria-modal="true" aria-labelledby="flm-title"><div class="flm-wrap"><div class="flm-pop '.$kind.' flm-c'.$t.'">'.$conf
  .'<span class="eyebrow">'.e($eyebrow).'</span><div class="flm-big">'.($up?'<span class="flm-rays"></span>':'').streak_flame($kind==='lost'?0:$t,'flm-bigflame').'<span class="num">'.e($num).'</span></div>'
  .'<h2 id="flm-title">'.$title.'</h2><p>'.$text.'</p>'.$extra.'</div>'
  .'<img class="flm-char'.($right?' r':'').'" src="'.e(friend_img('arah',$pose)).'" alt="Arah the Streak Keeper" width="170" height="260"><span class="flm-say'.($right?' r':'').'">'.e($say).'</span></div></div>';
}
function streak_btn(string $action,string $cls,string $label,string $ic=''):string{
 return '<form method="post" class="flm-f">'.csrf_field().'<input type="hidden" name="action" value="'.$action.'"><button class="flm-btn '.$cls.'">'.$ic.e($label).'</button></form>';
}

/** One popup on the pupil Home at most: a lost streak first, then a lit or upgraded flame. */
function streak_popup(int $pid):string{
 if(isset($_SESSION['st_shown']))return '';
 if(($l=streak_lost($pid))&&streak_get('streak_lost_'.$pid)!==$l['end']){
  $_SESSION['st_shown']=1;$name=strtolower(STREAK_TIERS[$l['tier']][1]);$n=$l['n'];
  $fire='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2c2 5-4 6-2 11 1-1 2-2 2-4 4 3 4 11-1 11s-8-5-5-10c0 2 1 3 2 3"/></svg>';
  if($l['can'])return streak_pop('lost','STREAK LOST',$l['tier'],$n.' days','Oh no! Your flame went out.','You did not answer a lesson yesterday, so your <b>'.$n.'-day '.e($name).'</b> stopped.',
   '<div class="flm-note">'.icon('star').'You have 1 revive this week</div><div class="flm-btns">'.streak_btn('streak_revive','revive','Revive my '.$n.'-day streak',$fire).streak_btn('streak_lost_ok','quiet','Start a new streak').'</div>','hello','I kept your flame warm! You can still save it.',false);
  return streak_pop('lost','STREAK LOST',$l['tier'],$n.' days','Your flame went out.',($l['used']?'You already used this week’s revive. ':'You missed more than one day. ').'Answer a lesson today to start a new flame. Answer 3 days in a row to light it again!',
   ($l['used']?'<div class="flm-note">Your next revive comes on Monday</div>':'').'<div class="flm-btns">'.streak_btn('streak_lost_ok','go','Start a lesson').'</div>','point','Read a lesson today and we light a new flame!',false);
 }
 $s=progress_stats($pid);$n=(int)$s['current_streak'];$t=streak_tier($n);$k='streak_tier_'.$pid;$seen=(int)streak_get($k);
 if($t<$seen){streak_set($k,(string)$t);return '';}
 if($t<=$seen)return '';
 streak_set($k,(string)$t);$_SESSION['st_shown']=1;
 if($t===1)return streak_pop('up flm-lit','STREAK STARTED',1,$n.' days','You lit your flame!',$n.' days in a row! Your streak is now on. Learn every day to make it grow.','<div class="flm-btns"><a class="flm-btn go" href="?page=dashboard">Let’s go!</a></div>','cheer','Hooray! I lit your flame!',true);
 $name=strtoupper(STREAK_TIERS[$t][2]);$next=isset(STREAK_TIERS[$t+1])?' to reach the '.STREAK_TIERS[$t+1][2].' flame':' to keep your pink flame bright';
 $lad='';for($i=1;$i<count(STREAK_TIERS);$i++)$lad.='<span class="'.($i===$t?'now':($i>$t?'off':'')).'">'.streak_flame($i,'flm-mini').STREAK_TIERS[$i][0].'+</span>';
 return streak_pop('up','FLAME UPGRADE',$t,$n.' days','Your flame turned '.$name.'!',$n.' days in a row! Keep answering a lesson every day'.$next.'.','<div class="flm-ladder">'.$lad.'</div><div class="flm-btns"><a class="flm-btn go" href="?page=dashboard">Keep it burning!</a></div>','thumbs','Your flame is '.STREAK_TIERS[$t][2].' now! Keep going!',true);
}

function streak_action(string $action):void{
 $u=require_role('pupil');$pid=(int)$u['id'];$l=streak_lost($pid);
 if($action==='streak_revive'&&$l&&$l['can']){
  q('INSERT IGNORE INTO streak_revives(pupil_id,day) VALUES(?,?)',[$pid,date('Y-m-d',strtotime('-1 day'))]);streak_recount($pid);
  streak_set('streak_lost_'.$pid,$l['end']);streak_set('streak_tier_'.$pid,(string)$l['tier']);
  if(function_exists('audit'))audit('streak_revive',(string)$l['n']);flash('Your '.$l['n'].'-day streak is back! Answer a lesson today to keep it burning.');go('?page=dashboard');
 }
 if($l)streak_set('streak_lost_'.$pid,$l['end']);
 go($action==='streak_lost_ok'&&$l&&!$l['can']?'?page=lessons':'?page=dashboard');
}
