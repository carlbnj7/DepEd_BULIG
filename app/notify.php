<?php
/* Notifications: the bell on every dashboard.
   - Saved notifications (table notifications, database/migrations/024_notifications.sql): made when something happens
     (a teacher's note, "do it again", a new badge, a finished level, a new account). They can be marked as read.
   - "Needs you now" items are counted live when the page opens (answers waiting, pupils who need a check-in,
     System health). They go away by themselves once the work is done.
   Without the table the app works as before: the bell shows only the live items. */

function notif_ready():bool{static $r=null;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'notifications'");}catch(Throwable $e){$r=false;}}return $r;}

/** Save one notification for one account. */
function notify(int $uid,string $kind,string $title,string $body='',string $url=''):void{
 if($uid<=0||!notif_ready())return;
 try{q('INSERT INTO notifications(user_id,kind,title,body,url) VALUES(?,?,?,?,?)',[$uid,mb_substr($kind,0,30),mb_substr($title,0,200),mb_substr($body,0,300),mb_substr($url,0,250)]);}catch(Throwable $e){}
}
/** Every teacher of a pupil. */
function notify_teachers(int $pid,string $kind,string $title,string $body='',string $url=''):void{
 foreach(rows('SELECT teacher_id FROM teacher_pupils WHERE pupil_id=?',[$pid]) as $t)notify((int)$t['teacher_id'],$kind,$title,$body,$url);
}
/** After a lesson is finished: when it was the last lesson of a level, the certificate is ready (pupil and teacher). */
function notify_level_done(int $pid,int $lid):void{
 $lv=lesson_level($lid);if(!$lv||!function_exists('level_finished_at')||!level_finished_at($pid,$lv))return;
 if(notif_ready()&&val("SELECT 1 FROM notifications WHERE user_id=? AND kind='certificate' AND url=?",[$pid,'?page=certificate&level='.$lv]))return;
 $name=(string)val('SELECT name FROM users WHERE id=?',[$pid]);
 notify($pid,'certificate','Your '.level_label($lv).' certificate is ready','You finished every lesson. Print it or save it as a PDF.','?page=certificate&level='.$lv);
 notify_teachers($pid,'certificate',$name.' finished '.level_label($lv),'Their certificate is ready to print.','?page=certificates&level='.$lv);
}

/** Things that need the teacher or administrator now (counted live, cached for a minute). */
function notif_live(array $u):array{
 if($u['role']==='pupil')return [];
 $c=$_SESSION['notif_live']??null;if(is_array($c)&&($c['uid']??0)===(int)$u['id']&&($c['t']??0)>time()-60)return $c['items'];
 $items=[];
 if($u['role']==='teacher'){
  $tid=(int)$u['id'];
  $perfect=function_exists('key_perfect_ids')?count(key_perfect_ids($tid)):0;
  if($perfect)$items[]=['check',$perfect.' '.($perfect===1?'answer':'answers').' scored 100% by the answer key','Approve them all in one tap.','?page=review','Approve all'];
  $wait=(int)val("SELECT COUNT(*) FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id WHERE t.teacher_id=? AND c.status IN ('completed','approved') AND c.reviewed_at IS NULL",[$tid])-$perfect;
  if($wait>0)$items[]=['edit',$wait.' '.($wait===1?'answer is':'answers are').' waiting for your feedback','Written, drawn and spoken answers, newest pupils first.','?page=review','Open'];
  $need=[];foreach(rows('SELECT u.id,u.name,u.active FROM users u JOIN teacher_pupils t ON t.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1 ORDER BY u.name',[$tid]) as $p)if(function_exists('tf_needs_checkin')&&tf_needs_checkin($p,progress_stats((int)$p['id'])))$need[]=explode(' ',trim((string)$p['name']))[0];
  if($need)$items[]=['people',count($need).' '.(count($need)===1?'pupil needs':'pupils need').' a check-in',implode(', ',array_slice($need,0,4)).(count($need)>4?' and '.(count($need)-4).' more':'').' had no learning in the past 7 days.','?page=dashboard','See pupils'];
 }
 if($u['role']==='admin'){
  if(function_exists('admin_health_checks'))foreach(admin_health_checks() as [$label,$good,$detail])if(!$good)$items[]=['alert',$label,(string)$detail,'?page=health','System health'];
  $new=(int)val("SELECT COUNT(*) FROM users WHERE role='pupil' AND created_at>=?",[date('Y-m-d H:i:s',strtotime('-7 days'))]);
  if($new)$items[]=['people',$new.' new pupil '.($new===1?'account':'accounts').' this week','Teachers added them to their sections.','?page=reports','Reports'];
 }
 $_SESSION['notif_live']=['uid'=>(int)$u['id'],'t'=>time(),'items'=>$items];
 return $items;
}
function notif_count(array $u):int{
 $n=count(notif_live($u));if(notif_ready())$n+=(int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL',[(int)$u['id']]);return $n;
}
/** The bell: in the top bar on a computer, and at the top of the screen on a phone. */
function notif_bell(array $u,string $cls):string{
 $n=notif_count($u);
 return '<a class="notif-bell '.$cls.'" href="?page=notifications" data-notif aria-controls="notif-pop" aria-expanded="false" aria-label="Notifications'.($n?', '.$n.' new':'').'" title="Notifications"><svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>'.($n?'<span class="notif-n">'.($n>99?'99+':$n).'</span>':'').'</a>';
}

/** The round picture on the left of each notification. */
function notif_icon(string $k):string{
 $ic=['note'=>'<path d="M4 5h16v11H8l-4 4z"/>','again'=>'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>','badge'=>'<path d="M12 3l2.7 5.6 6.2.9-4.5 4.3 1.1 6.1L12 17l-5.5 2.9 1.1-6.1L3 9.5l6.2-.9z"/>','certificate'=>'<path d="M7 3h10v12l-5-3-5 3z"/><circle cx="12" cy="8" r="2"/>','check'=>'<path d="M5 12l4 4 10-10"/>','edit'=>'<path d="M4 20h4L19 9l-4-4L4 16z"/>','people'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5"/><circle cx="17" cy="9" r="2.5"/>','alert'=>'<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>','account'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>','info'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>','help'=>'<path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-1V4.5a1.5 1.5 0 0 1 3 0V10m0-4.5a1.5 1.5 0 0 1 3 0V11m0-3a1.5 1.5 0 0 1 3 0v6a7 7 0 0 1-7 7h-1a7 7 0 0 1-5.6-2.8L4 15.5a1.6 1.6 0 0 1 2.5-2L8 15"/>','schedule'=>'<rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0"/>','month'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="m12 12.5 1.2 2.4 2.6.4-1.9 1.8.5 2.6-2.4-1.3-2.4 1.3.5-2.6-1.9-1.8 2.6-.4z"/>'];
 return '<span class="nt-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.($ic[$k]??$ic['info']).'</svg></span>';
}
function notif_ago(string $t):string{
 $d=time()-strtotime($t);if($d<60)return 'Just now';if($d<3600)return intdiv($d,60).' min ago';if($d<86400)return intdiv($d,3600).' hr ago';
 if($d<172800)return 'Yesterday';return date('M j',strtotime($t));
}
/** The drop-down under the bell: the newest notifications, without leaving the page. */
function notif_panel(array $u):string{
 $live=notif_live($u);$saved=notif_ready()?rows('SELECT * FROM notifications WHERE user_id=? ORDER BY read_at IS NULL DESC,created_at DESC,id DESC LIMIT 8',[(int)$u['id']]):[];
 $unread=notif_ready()?(int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL',[(int)$u['id']]):0;$n=$unread+count($live);
 $back=(string)($_SERVER['QUERY_STRING']??'');$back=$back!==''?'?'.$back:'?page=dashboard';
 $h='<div class="notif-pop" id="notif-pop" role="dialog" aria-label="Notifications" hidden><div class="np-head"><div><b>Notifications</b><small>'.($n?$n.' new':'You are all caught up.').'</small></div>';
 if($unread)$h.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="notif_read_all"><input type="hidden" name="back" value="'.e($back).'"><button class="np-mark">'.icon('check').'Mark all as read</button></form>';
 $h.='</div><div class="np-list">';
 $item=fn(string $k,string $t,string $s,string $href,string $when,bool $new)=>'<a class="np-row'.($new?' new':'').'" href="'.e($href).'">'.notif_icon($k).'<span class="nt-tx"><b>'.e($t).'</b>'.($s!==''?'<small>'.e($s).'</small>':'').'<em>'.e($when).'</em></span>'.($new?'<span class="nt-dot" aria-label="New"></span>':'').'</a>';
 if($live){$h.='<p class="np-day">NEEDS YOU NOW</p>';foreach($live as [$k,$t,$s,$href])$h.=$item($k,$t,$s,$href,'Now',true);}
 if($saved){if($live)$h.='<p class="np-day">EARLIER</p>';foreach($saved as $r)$h.=$item((string)$r['kind'],(string)$r['title'],(string)$r['body'],$r['url']!==''?'?page=notifications&open='.(int)$r['id']:'?page=notifications',notif_ago((string)$r['created_at']),$r['read_at']===null);}
 if(!$live&&!$saved)$h.='<div class="np-empty">'.icon('bell').'<b>No notifications yet.</b><small>'.($u['role']==='pupil'?'Notes from your teacher, new badges and certificates will show here.':'Answers to check, finished levels and pupils who need you will show here.').'</small></div>';
 return $h.'</div><a class="np-all" href="?page=notifications">See all notifications'.icon('arrow').'</a></div>';
}
function notifications_view(array $u):void{
 $uid=(int)$u['id'];$live=notif_live($u);$saved=notif_ready()?rows('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 60',[$uid]):[];
 $unread=count(array_filter($saved,fn($r)=>$r['read_at']===null));$n=$unread+count($live);
 echo '<div class="pageheading"><div><span class="eyebrow">NOTIFICATIONS</span><h1>What’s new for you</h1><p class="muted">'.($n?$n.' new · Tap one to open it.':'You are all caught up.').'</p></div>'.($unread?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="notif_read_all"><button class="btn secondary">'.icon('check').'Mark all as read</button></form>':'').'</div>';
 $svg=fn($k)=>notif_icon($k);
 $row=function(string $k,string $t,string $s,string $href,string $btn,bool $new,string $id='')use($svg):string{
  return '<a class="card nt-row'.($new?' new':'').'" href="'.e($href!==''?($id!==''?'?page=notifications&open='.$id:$href):'#').'">'.$svg($k).'<span class="nt-tx"><b>'.e($t).'</b>'.($s!==''?'<small>'.e($s).'</small>':'').'</span>'.($btn!==''?'<span class="btn '.($new?'primary':'secondary').' nt-btn">'.e($btn).'</span>':'').($new?'<span class="nt-dot" aria-label="New"></span>':'').'</a>';};
 echo '<div class="nt-list">';
 if($live){echo '<p class="eyebrow nt-day">NEEDS YOU NOW</p>';foreach($live as [$k,$t,$s,$href,$btn])echo $row($k,$t,$s,$href,$btn,true);}
 $day='';$btns=['schedule'=>'Open','month'=>'See it','help'=>'See pupil','note'=>'Open','again'=>'Start','badge'=>'See it','certificate'=>$u['role']==='teacher'?'Print':'See it','account'=>'Open'];
 foreach($saved as $r){$d=date('Y-m-d',strtotime((string)$r['created_at']));$lab=$d===date('Y-m-d')?'TODAY':($d===date('Y-m-d',strtotime('-1 day'))?'YESTERDAY':strtoupper(date('F j',strtotime($d))));
  if($lab!==$day){$day=$lab;echo '<p class="eyebrow nt-day">'.e($lab).'</p>';}
  echo $row((string)$r['kind'],(string)$r['title'],(string)$r['body'],(string)$r['url'],$r['url']!==''?($btns[$r['kind']]??'Open'):'',$r['read_at']===null,(string)$r['id']);}
 if(!$live&&!$saved)echo '<section class="empty">'.icon('bell').'<h2>No notifications yet.</h2><p>'.($u['role']==='pupil'?'Notes from your teacher, new badges and certificates will show here.':'Answers to check, finished levels and pupils who need you will show here.').'</p></section>';
 echo '</div>';
}
/** Open one notification: mark it as read, then go where it points. */
function notif_open(array $u):void{
 $id=(int)($_GET['open']??0);if(!$id||!notif_ready())return;$r=one('SELECT * FROM notifications WHERE id=? AND user_id=?',[$id,(int)$u['id']]);if(!$r)return;
 q('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=?',[$id]);$url=(string)$r['url'];
 if($url!==''&&str_starts_with($url,'?page='))go($url);
}
