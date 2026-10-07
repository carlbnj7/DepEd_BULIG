<?php
/* Level schedule: the administrator chooses the date each level opens, so the whole class moves together.
   A pupil who finishes a level early waits for the next level's date. A pupil's starting level is always open.
   Saved in the settings table (key level_schedule) as JSON: {"on":true,"dates":{"2":"2026-10-12",...}}. No new table. */

function schedule_get():array{
 static $s=null;if($s!==null)return $s;
 $raw=val("SELECT setting_value FROM settings WHERE setting_key='level_schedule'");$j=$raw?json_decode((string)$raw,true):null;
 $dates=[];foreach((array)($j['dates']??[]) as $k=>$v)if(preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v))$dates[(int)$k]=(string)$v;
 return $s=['on'=>!empty($j['on']),'dates'=>$dates];
}
/** The date a level opens, when that date is still in the future (otherwise null: the level is open). */
function level_opens_on(int $level):?string{$s=schedule_get();if(!$s['on'])return null;$d=$s['dates'][$level]??null;return $d!==null&&$d>date('Y-m-d')?$d:null;}
/** Locked by date for this pupil (never the starting level or a level before it). */
function level_date_locked(int $pid,int $level):bool{
 if(level_opens_on($level)===null)return false;
 $start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);
 return $start>0&&$level>$start;
}
function schedule_date_label(string $d):string{$t=strtotime($d);$days=(int)round(($t-strtotime(date('Y-m-d')))/86400);return ($days===1?'tomorrow, ':'on ').date('l, M j',$t);}
function schedule_days_left(string $d):int{return max(1,(int)ceil((strtotime($d)-time())/86400));}

/** The level this pupil finished early and is now waiting for (null when they still have lessons to do). */
function schedule_wait(int $pid):?array{
 $s=schedule_get();if(!$s['on'])return null;
 $start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);if(!$start)return null;
 $prev=$start;
 for($l=$start+1;$l<=8;$l++){
  if(!level_progress_ready($pid,$l))return null;
  if(($d=level_opens_on($l))!==null)return ['level'=>$l,'prev'=>$prev,'opens'=>$d];
  $prev=$l;
 }
 return null;
}
/** The small card on the pupil's home page and My lessons while they wait. */
function schedule_wait_card(array $u):string{
 $pid=(int)$u['id'];$w=schedule_wait($pid);if(!$w)return '';
 $first=explode(' ',trim((string)$u['name']))[0];$days=schedule_days_left($w['opens']);
 return '<section class="card sw-card" id="schedule-wait"><span class="sw-lock">'.icon('lock').'</span><div class="sw-text"><b>'.e(level_label($w['level'])).' opens '.e(schedule_date_label($w['opens'])).'</b><small>Great job, '.e($first).'! You finished '.e(level_label($w['prev'])).' early. Your class starts '.e(level_label($w['level'])).' together.</small></div>'
  .'<span class="sw-count"><b>'.$days.'</b>'.($days===1?'day':'days').'</span><a class="btn secondary sw-btn" href="?page=lessons&amp;level='.$w['prev'].'#level'.$w['prev'].'-path">'.icon('replay').'Practise '.e(level_label($w['prev'])).'</a></section>';
}

/* ---------- Notifications ---------- */
/** After a pupil finishes a lesson: if they are now waiting for a level's date, tell them and their teachers (once per level). */
function schedule_after_finish(int $pid):void{
 if(!function_exists('notif_ready')||!notif_ready())return;$w=schedule_wait($pid);if(!$w)return;
 $title='You finished '.level_label($w['prev']).' early!';
 if(val("SELECT 1 FROM notifications WHERE user_id=? AND kind='schedule' AND title=?",[$pid,$title]))return;
 $when=schedule_date_label($w['opens']);$name=(string)val('SELECT name FROM users WHERE id=?',[$pid]);$first=explode(' ',trim($name))[0];
 notify($pid,'schedule',$title,level_label($w['level']).' opens '.$when.'. You can practise '.level_label($w['prev']).' again while you wait.','?page=lessons&level='.$w['prev']);
 notify_teachers($pid,'schedule',$first.' finished '.level_label($w['prev']).' early',level_label($w['level']).' opens '.$when.'. '.$first.' will start it with the class.','?page=pupil&id='.$pid);
}
/** When a pupil opens BULIG: tell them once when a level they were waiting for is open. */
function schedule_open_check_pupil(int $pid):void{
 $s=schedule_get();if(!$s['on']||!function_exists('notif_ready')||!notif_ready())return;$today=date('Y-m-d');
 foreach($s['dates'] as $l=>$d){
  if($d>$today||$d<date('Y-m-d',strtotime('-14 days')))continue;
  $title=level_label($l).' is open now!';
  if(val("SELECT 1 FROM notifications WHERE user_id=? AND kind='schedule' AND title=?",[$pid,$title]))continue;
  $start=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);if(!$start||$l<=$start||!level_progress_ready($pid,$l))continue;
  if(val('SELECT 1 FROM pupil_progress p JOIN lessons ls ON ls.id=p.lesson_id JOIN modules m ON m.id=ls.module_id WHERE p.pupil_id=? AND m.level_id=? AND p.completed_at IS NOT NULL',[$pid,$l]))continue;
  notify($pid,'schedule',$title,'Your class starts '.level_label($l).' today. Let’s go!','?page=lessons&level='.$l.'#level'.$l.'-path');
 }
}
/** When a teacher opens BULIG: once per level, say it opened and how many of their pupils can start it. */
function schedule_open_check_teacher(int $tid):void{
 $s=schedule_get();if(!$s['on']||!function_exists('notif_ready')||!notif_ready())return;$today=date('Y-m-d');
 foreach($s['dates'] as $l=>$d){
  if($d>$today||$d<date('Y-m-d',strtotime('-14 days')))continue;
  $title=level_label($l).' opened '.date('M j',strtotime($d));
  if(val("SELECT 1 FROM notifications WHERE user_id=? AND kind='schedule' AND title=?",[$tid,$title]))continue;
  $n=0;foreach(rows('SELECT t.pupil_id,a.level_id FROM teacher_pupils t JOIN pupil_level_assignments a ON a.pupil_id=t.pupil_id JOIN users u ON u.id=t.pupil_id AND u.active=1 WHERE t.teacher_id=?',[$tid]) as $r)if((int)$r['level_id']<$l&&level_progress_ready((int)$r['pupil_id'],$l))$n++;
  if(!$n)continue;
  notify($tid,'schedule',$title,$n.' of your '.($n===1?'pupils is':'pupils are').' ready to start '.level_label($l).' now.','?page=progress');
 }
}
/** For the teacher's pupil page: the date the pupil is waiting for. */
function schedule_pupil_note(int $pid):string{
 $w=schedule_wait($pid);if(!$w)return '';
 return '<section class="notice sw-note">'.icon('lock').'<span>Finished '.e(level_label($w['prev'])).' early. '.e(level_label($w['level'])).' opens '.e(schedule_date_label($w['opens'])).' (set by the administrator).</span></section>';
}

/* ---------- Administrator: Settings › Level schedule ---------- */
function schedule_admin_view():void{
 $s=schedule_get();$today=date('Y-m-d');$levels=rows('SELECT id,title FROM bulig_levels ORDER BY id');
 $first=$s['dates'][1]??($s['dates']?min($s['dates']):date('Y-m-d',strtotime('monday this week')));
 echo '<section class="card sch-card"><form method="post" class="sch-form" data-sch-form>'.csrf_field().'<input type="hidden" name="action" value="admin_level_schedule">';
 echo '<div class="sch-head"><div><h2>'.icon('cal').' Level schedule</h2><p class="muted">Choose when each level opens. Pupils who finish early wait for the opening date, so the whole class moves together.</p></div><label class="sch-switch"><input type="checkbox" name="on" value="1"'.($s['on']?' checked':'').'><i aria-hidden="true"></i><span>Schedule on</span></label></div>';
 echo '<div class="sch-fill"><label>First day of Week 1<input type="date" data-sch-start value="'.e($first).'"></label><label>Each level lasts<select data-sch-weeks><option value="1">1 week</option><option value="2">2 weeks</option><option value="3">3 weeks</option><option value="4">4 weeks</option></select></label><button type="button" class="btn secondary" data-sch-fillbtn>'.icon('cal').'Fill the dates for me</button></div>';
 echo '<div class="tablewrap"><table class="sch-table"><thead><tr><th>Week</th><th>Level</th><th>Opens on</th><th>Now</th></tr></thead><tbody>';
 $i=0;foreach($levels as $l){$i++;$id=(int)$l['id'];$d=$s['dates'][$id]??'';
  $st=!$s['on']?['off','Schedule off']:($d===''?['o','Open (no date)']:($d<=$today?['o','Open']:((int)ceil((strtotime($d)-time())/86400)<=7?['n','Opens in '.max(1,(int)ceil((strtotime($d)-time())/86400)).' '.(ceil((strtotime($d)-time())/86400)<=1?'day':'days')]:['l','Locked until '.date('M j',strtotime($d))])));
  echo '<tr><td><span class="sch-wk">W'.$i.'</span></td><td><b>'.e(level_label($id)).'</b><small>'.e(trim((string)preg_replace('/\s*\S\s*Level 2[AB]\s*$/u','',(string)$l['title']))).'</small></td><td><input type="date" name="date['.$id.']" value="'.e($d).'" data-sch-date aria-label="'.e(level_label($id)).' opens on"></td><td><span class="sch-st sch-'.$st[0].'">'.e($st[1]).'</span></td></tr>';}
 echo '</tbody></table></div><p class="sch-note">'.icon('info').'<span>A pupil’s starting level is always open. Leave a date empty to keep that level open. Pupils and teachers get a notification when someone finishes early and when a level opens.</span></p>';
 echo '<div class="sch-actions"><button class="btn primary">'.icon('check').'Save schedule</button></div></form></section>';
}
function schedule_admin_save():void{
 require_role('admin');$dates=[];
 foreach((array)($_POST['date']??[]) as $k=>$v){$k=(int)$k;$v=trim((string)$v);if($k>=1&&$k<=8&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)&&strtotime($v))$dates[$k]=$v;}
 ksort($dates);$prev='';foreach($dates as $k=>$v){if($prev!==''&&$v<$prev)fail(level_label($k).' cannot open before the level above it. Check the dates.');$prev=$v;}
 $on=!empty($_POST['on']);
 q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['level_schedule',json_encode(['on'=>$on,'dates'=>$dates])]);
 audit('admin_level_schedule',$on?'on':'off');flash($on?'Level schedule saved. Levels open on their dates.':'Level schedule saved and turned off. Levels open as soon as pupils finish the level before.');go('?page=settings&tab=schedule');
}
