<?php
/* BULIG phone reminders. Run every 15 minutes from the server's scheduled tasks (cron):
     php /home/YOUR-USER/public_html/cron/notify.php
   Rules: daytime only (7:00 to 20:00), at most one note per phone per day, weekends only if the pupil chose them.
   Order of importance: a note from the teacher, then a newly opened lesson or level, then the daily reading reminder. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('This runs from the server\'s scheduled tasks only.');}
require __DIR__.'/../app/bootstrap.php';
if(!push_ready()){fwrite(STDERR,"Reminders are not set up: import 017_push_reminders.sql, and check that PHP has openssl and curl.\n");exit(1);}
$now=new DateTimeImmutable('now');$hour=(int)$now->format('G');$today=$now->format('Y-m-d');$weekend=(int)$now->format('N')>=6;
q('DELETE FROM push_queue WHERE created_at<NOW()-INTERVAL 3 DAY');
if($hour<7||$hour>=20){echo "Quiet hours. Nothing sent.\n";exit(0);}
$sent=0;$failed=0;$byUser=[];
foreach(rows('SELECT s.*,u.name FROM push_subscriptions s JOIN users u ON u.id=s.user_id AND u.active=1 ORDER BY s.user_id,s.id') as $s)$byUser[(int)$s['user_id']][]=$s;
foreach($byUser as $pid=>$subs){
 if(in_array($today,array_map(fn($x)=>(string)$x['last_sent_on'],$subs),true))continue;
 $first=explode(' ',trim((string)$subs[0]['name']))[0];$queue=rows('SELECT * FROM push_queue WHERE user_id=? AND sent_at IS NULL ORDER BY FIELD(kind,\'teacher\',\'lessons\'),id',[$pid]);
 $daily=null;$any=false;$used=[];
 foreach($subs as $s){$p=push_prefs($s);if($weekend&&!$p['weekends'])continue;$msg=null;
  foreach($queue as $n)if(($n['kind']==='teacher'&&$p['teacher'])||($n['kind']==='lessons'&&$p['lessons'])||!in_array($n['kind'],['teacher','lessons'],true)){$msg=['title'=>$n['title'],'body'=>$n['body'],'url'=>$n['url'],'tag'=>'bulig-'.$n['kind']];$used[(int)$n['id']]=1;break;}
  if(!$msg&&$p['daily']&&$hour===(int)$p['hour']){
   if($daily===null){$daily=false;if(!(int)val('SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND submitted_at>=CURDATE()',[$pid])){$streak=(int)progress_stats($pid)['current_streak'];
    $daily=['title'=>'Time to read, '.$first.'!','body'=>$streak>1?'You’re on a '.$streak.'-day streak. One activity keeps it going.':'A little practice today makes a brighter day. Your next lesson is ready.','url'=>'?page=dashboard','tag'=>'bulig-daily'];}}
   $msg=$daily?:null;}
  if(!$msg)continue;
  try{$code=push_send($s,$msg);}catch(Throwable $e){$code=0;}
  if($code>=200&&$code<300){$sent++;$any=true;}else $failed++;}
 if($any){q('UPDATE push_subscriptions SET last_sent_on=? WHERE user_id=?',[$today,$pid]);foreach(array_keys($used) as $id)q('UPDATE push_queue SET sent_at=NOW() WHERE id=?',[$id]);}
 /* Notes the pupil turned off on every phone are dropped. */
 foreach($queue as $n)if(!isset($used[(int)$n['id']])&&!array_filter($subs,fn($s)=>push_prefs($s)[$n['kind']]??1))q('UPDATE push_queue SET sent_at=NOW() WHERE id=?',[(int)$n['id']]);
}
echo date('Y-m-d H:i')." · sent $sent".($failed?" · could not send $failed":'')."\n";
