<?php
/* The "I need help" button on every lesson screen. Table: database/migrations/025_help_requests.sql (without it the button stays hidden). */

function help_ready():bool{static $r=null;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'help_requests'");}catch(Throwable $e){$r=false;}}return $r;}
const HELP_REASONS=['question'=>'I don’t understand the question','sound'=>'I can’t hear the sound','answer'=>'I don’t know how to answer','bug'=>'Something is not working'];   // bug: goes to the administrator (Bug reports), v176
/** The button and its sheet on every lesson screen (added by foot()). */
function help_widget():string{
 $u=current_user();if(!$u||$u['role']!=='pupil'||($_GET['page']??'')!=='lesson'||!help_ready())return '';
 $lid=(int)($_GET['id']??0);$aid=(int)($_GET['activity']??0);if(!$lid)return '';
 $ic=['question'=>'<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6M12 17h.01"/>','sound'=>'<path d="m11 4-5 4H3v8h3l5 4zm5 5 6 6m0-6-6 6"/>','answer'=>'<path d="M4 20h4L19 9l-4-4L4 16z"/>','bug'=>'<path d="M8 8a4 4 0 0 1 8 0v1H8z"/><rect x="7" y="9" width="10" height="11" rx="5"/><path d="M12 13v7M3 13h4m10 0h4M4 7l3 2m13-2-3 2M4 20l3-3m13 3-3-3"/>'];
 $h='';if(isset($_SESSION['help_toast'])){$h.='<div class="hp-toast" role="status">'.icon('check').'<span>'.e((string)$_SESSION['help_toast']).'</span></div>';unset($_SESSION['help_toast']);}
 $h.='<button type="button" class="hp-open" data-help-open aria-label="I need help">'.'<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-1V4.5a1.5 1.5 0 0 1 3 0V10m0-4.5a1.5 1.5 0 0 1 3 0V11m0-3a1.5 1.5 0 0 1 3 0v6a7 7 0 0 1-7 7h-1a7 7 0 0 1-5.6-2.8L4 15.5a1.6 1.6 0 0 1 2.5-2L8 15"/></svg>'.'<span>I need help</span></button>';
 $h.='<dialog class="hp-sheet" aria-labelledby="hp-title"><form method="post" class="hp-card">'.csrf_field().'<input type="hidden" name="action" value="help_request"><input type="hidden" name="lesson_id" value="'.$lid.'"><input type="hidden" name="activity_id" value="'.$aid.'"><input type="hidden" name="back" value="'.e('?'.(string)($_SERVER['QUERY_STRING']??'')).'">';
 $h.='<h2 id="hp-title">I need help</h2><p>Tell your teacher what is hard. Your teacher gets your message, in class or at home.</p>';
 foreach(HELP_REASONS as $k=>$t)$h.='<label class="hp-opt'.($k==='bug'?' hp-bug':'').'"><input type="radio" name="reason" value="'.$k.'"'.($k==='question'?' checked':'').'><i><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic[$k].'</svg></i><span>'.e($t).'</span></label>';
 $h.='<label class="hp-what" data-bug-what hidden>What is not working? (you can skip this)<textarea name="note" maxlength="200" rows="2" placeholder="Example: the button does not work, the picture is missing"></textarea></label>';
 $h.='<p class="hp-off" data-help-off hidden>You are offline, so your message can’t be sent yet. In class, raise your hand. At home, ask a grown-up near you.</p><button class="btn primary hp-send">Tell my teacher</button><button type="button" class="btn quiet hp-cancel" data-help-close>Cancel</button></form></dialog>';
 return $h;
}
function help_request(array $u):void{
 $pid=(int)$u['id'];$back=(string)($_POST['back']??'');$back=preg_match('/^\?page=lesson(&[A-Za-z0-9_=%&.-]*)?$/',$back)?$back:'?page=dashboard';
 $r=(string)($_POST['reason']??'');if(!isset(HELP_REASONS[$r])||!help_ready())go($back);
 $lid=(int)($_POST['lesson_id']??0);$aid=(int)($_POST['activity_id']??0);
 if($lid&&!lesson_available($pid,$lid))go($back);
 /* One request per lesson every 5 minutes, so a tap-happy pupil does not flood the teacher. */
 if($r==='bug'){help_bug($u,$pid,$lid,$aid,$back);return;}
 if(val('SELECT 1 FROM help_requests WHERE pupil_id=? AND COALESCE(lesson_id,0)=? AND reason<>? AND created_at>?',[$pid,$lid,'bug',date('Y-m-d H:i:s',time()-300)])){$_SESSION['help_toast']='Your teacher already got your message. They will help you as soon as they can.';go($back);}
 q('INSERT INTO help_requests(pupil_id,lesson_id,activity_id,reason) VALUES(?,?,?,?)',[$pid,$lid?:null,$aid?:null,$r]);
 /* where: the lesson and the activity ("Beginning blends › Your turn · sheet 2") */
 $l=$lid?one('SELECT title,subtitle FROM lessons WHERE id=?',[$lid]):null;$ln=$l?(trim((string)$l['subtitle'])!==''?trim((string)$l['subtitle']):(string)$l['title']):'';
 $where='';if($aid&&($a=one('SELECT title FROM activities WHERE id=?',[$aid])))$where=($ln!==''?$ln.' › ':'').(string)$a['title'];elseif($l)$where=lesson_home_name($l);
 $first=explode(' ',trim((string)$u['name']))[0];
 notify_teachers($pid,'help',$first.' needs help',HELP_REASONS[$r].($where!==''?' · '.$where:'').($lid?' · '.level_label(lesson_level($lid)):''),'?page=pupil&id='.$pid);
 $_SESSION['help_toast']='Your teacher got your message. They will help you as soon as they can. While you wait, tap the speaker to listen again, or ask a grown-up near you.';go($back);
}

/** "Something is not working": saved for the administrator's Bug reports page (one notification per administrator,
   so "Mark as fixed" works like the bell). It says where it happened and on which device. */
function help_bug(array $u,int $pid,int $lid,int $aid,string $back):never{
 if(val('SELECT COUNT(*) FROM help_requests WHERE pupil_id=? AND reason=? AND created_at>?',[$pid,'bug',date('Y-m-d H:i:s',time()-600)])>=3){if(!empty($_POST['ajax'])){header('Content-Type: application/json');echo json_encode(['ok'=>true,'dup'=>true]);exit;}$_SESSION['help_toast']='Thank you! We already got your reports. Your teacher can tell us more.';go($back);}
 $card=max(0,min(999,(int)($_POST['card']??0)));$ajax=!empty($_POST['ajax']);   // card: the page of a card activity; only that page is skipped (in the browser)
 if($lid&&!$aid&&($na=next_activity($pid,$lid)))$aid=(int)$na['id'];   // on the lesson screen: the activity the pupil is on now
 q('INSERT INTO help_requests(pupil_id,lesson_id,activity_id,reason) VALUES(?,?,?,?)',[$pid,$lid?:null,$aid?:null,'bug']);
 $skipped=$lid&&$aid&&!$card?help_bug_skip($pid,$lid,$aid):false;
 $l=$lid?one('SELECT title,subtitle FROM lessons WHERE id=?',[$lid]):null;$a=$aid?one('SELECT title FROM activities WHERE id=?',[$aid]):null;
 $where=($lid?level_label(lesson_level($lid)).' · ':'').($l?lesson_home_name($l):'').($a?' › '.$a['title']:'').($card?' › page '.$card:'').($lid?' (lesson '.$lid.($aid?', activity '.$aid:'').')':'');
 $note=trim(preg_replace('/\s+/',' ',(string)($_POST['note']??'')));$note=mb_substr($note,0,200);
 $body=($note!==''?'“'.$note.'” · ':'').$where.' · '.help_device((string)($_SERVER['HTTP_USER_AGENT']??'')).($skipped?' · Skipped for this pupil':($card?' · Page '.$card.' skipped for this pupil':''));
 $title='Bug report from '.$u['name'].' ('.$u['public_id'].')';
 if(function_exists('bugflow_ready')&&bugflow_ready()){db()->beginTransaction();try{bugflow_create($u,$note!==''?$note:'Something is not working',$body);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}}else foreach(rows("SELECT id FROM users WHERE role='admin' AND active=1") as $ad)notify((int)$ad['id'],'bug',$title,$body,'?page=bugs');
 audit('bug_report',mb_substr($where.($note!==''?' · '.$note:''),0,250));
 if($ajax){header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;}
 if($skipped){$n=next_activity($pid,$lid);$_SESSION['help_toast']='Thank you! You told the BULIG team. You can skip this part for now. Here is the next one.';go('?page=lesson&id='.$lid.($n?'&activity='.(int)$n['id']:''));}
 $_SESSION['help_toast']='Thank you! You told the BULIG team. They will fix it. You can try again, or go to another activity.';go($back);
}
/** A bug report skips the activity for this pupil, so they can go on: it counts as done, with no score (it does not lower their results). */
function help_bug_skip(int $pid,int $lid,int $aid):bool{
 $a=one('SELECT * FROM activities WHERE id=? AND lesson_id=? AND published=1',[$aid,$lid]);if(!$a||!lesson_available($pid,$lid))return false;
 if(completion_ok(one('SELECT status FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid])))return false;
 q("INSERT INTO activity_completion(pupil_id,activity_id,response,prompt_snapshot,activity_revision,status,score,max_score,feedback,submitted_at,reviewed_at) VALUES(?,?,?,?,?,'completed',NULL,NULL,?,NOW(),NOW())
    ON DUPLICATE KEY UPDATE status='completed',score=NULL,max_score=NULL,feedback=VALUES(feedback),reviewed_at=NOW()",[$pid,$aid,'[Skipped: bug reported]',(string)($a['prompt']??''),(int)($a['revision']??1),'Skipped because the pupil reported that something was not working.']);
 return true;
}
/** "Android phone · Chrome" from the browser's user agent. */
function help_device(string $ua):string{
 $os=preg_match('/iPhone|iPad|iPod/i',$ua)?(stripos($ua,'iPad')!==false?'iPad':'iPhone'):(preg_match('/Android/i',$ua)?(preg_match('/Mobile/i',$ua)?'Android phone':'Android tablet'):(preg_match('/Windows/i',$ua)?'Windows computer':(preg_match('/Macintosh/i',$ua)?'Mac':(preg_match('/CrOS/i',$ua)?'Chromebook':(preg_match('/Linux/i',$ua)?'Linux computer':'Device')))));
 $br=preg_match('/Edg\//',$ua)?'Edge':(preg_match('/SamsungBrowser/',$ua)?'Samsung Internet':(preg_match('/OPR\/|Opera/',$ua)?'Opera':(preg_match('/Firefox|FxiOS/',$ua)?'Firefox':(preg_match('/Chrome|CriOS/',$ua)?'Chrome':(preg_match('/Safari/',$ua)?'Safari':'browser')))));
 return $os.' · '.$br;
}
/* v178: a report stays open until the administrator taps Fixed. Reading it on the bell or in Notifications
   (or "Mark all as read") no longer moves it to Fixed: Fixed changes its kind to 'bugfixed', and the day it was
   fixed is kept in settings (bug_fixed_<id>). */
function bug_open_count(int $uid):int{if(function_exists('bugflow_ready')&&bugflow_ready())return (int)val("SELECT COUNT(*) FROM bug_reports WHERE status IN ('open','processing')")+(notif_ready()?(int)val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND kind='bug' AND id NOT IN (SELECT notification_id FROM bug_report_links)",[$uid]):0);return notif_ready()?(int)val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND kind='bug'",[$uid]):0;}
function bug_fixed_on(array $n):string{$d='';try{$d=(string)val('SELECT setting_value FROM settings WHERE setting_key=?',['bug_fixed_'.(int)$n['id']]);}catch(Throwable $e){}return $d!==''?$d:(string)($n['read_at']??'');}
/** Administrator: Bug reports from pupils (?page=bugs). */
function bugs_view(array $u):void{
 if(function_exists('bugflow_ready')&&bugflow_ready()){bugflow_admin($u);if(!in_array($_GET['show']??'open',['open','fixed'],true))return;}
 $f=($_GET['show']??'')==='fixed'?'fixed':'open';
 $legacyFilter=function_exists('bugflow_ready')&&bugflow_ready()?' AND id NOT IN (SELECT notification_id FROM bug_report_links)':'';
 $list=notif_ready()?rows("SELECT * FROM notifications WHERE user_id=? AND kind=?".$legacyFilter." ORDER BY created_at DESC,id DESC LIMIT 200",[(int)$u['id'],$f==='open'?'bug':'bugfixed']):[];
 $open=bug_open_count((int)$u['id']);
 $dl='<a class="btn secondary" href="?page=bugs_csv&amp;show='.$f.'">'.icon('arrow').'Download '.($f==='open'?'open':'fixed').' reports (CSV)</a><a class="btn quiet" href="?page=bugs_csv&amp;show=all">Download all</a>';
 if(function_exists('bugflow_ready')&&bugflow_ready())echo '<h2>Earlier reports</h2>';else admin_heading('SYSTEM · BUG REPORTS','Bug reports from pupils.','When something does not work, pupils tap I need help › Something is not working. Each report says where it happened and on which device. The pupil skips that activity and goes on to the next one (it gets no score).','<div class="bug-acts">'.$dl.($open?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_fixed_all"><button class="btn secondary" data-confirm="Mark all '.$open.' open reports as fixed?">'.icon('check').'Mark all as fixed</button></form>':'').'</div>');
 echo '<section class="card bug-card"><nav class="bk-tabs" aria-label="Show"><a href="?page=bugs"'.($f==='open'?' class="on" aria-current="page"':'').'>'.icon('alert').'Open ('.$open.')</a><a href="?page=bugs&amp;show=fixed"'.($f==='fixed'?' class="on" aria-current="page"':'').'>'.icon('check').'Fixed</a></nav>';
 if(!$list){echo '<p class="muted bug-empty">'.($f==='open'?'No open bug reports. Well done!':'No fixed reports yet.').'</p></section>';return;}
 echo '<div class="tablewrap"><table class="bug-table"><thead><tr><th>When</th><th>Pupil</th><th>What and where</th><th></th></tr></thead><tbody>';
 foreach($list as $n){$who=preg_replace('/^Bug report from /','',(string)$n['title']);
  echo '<tr><td>'.e(date('M j, g:i A',strtotime((string)$n['created_at']))).'</td><td>'.e($who).'</td><td>'.e((string)$n['body']).'</td><td>'.((function_exists('bugflow_ready')&&bugflow_ready())?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_adopt"><input type="hidden" name="id" value="'.(int)$n['id'].'"><button class="btn secondary small">Review / reply</button></form>':'').($f==='open'?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_fixed"><input type="hidden" name="id" value="'.(int)$n['id'].'"><button class="btn primary small">'.icon('check').'Fixed</button></form>':'<small class="muted">Fixed '.e(date('M j',strtotime(bug_fixed_on($n)?:'now'))).'</small>').'</td></tr>';}
 echo '</tbody></table></div></section>';
}
/** Bug reports as a CSV file (opens in Excel): the open ones, the fixed ones or all. */
function bugs_csv(array $u):never{
 $f=(string)($_GET['show']??'all');$w=$f==='open'?" AND kind='bug'":($f==='fixed'?" AND kind='bugfixed'":" AND kind IN ('bug','bugfixed')");
 $list=notif_ready()?rows("SELECT * FROM notifications WHERE user_id=?".$w." ORDER BY created_at DESC,id DESC",[(int)$u['id']]):[];
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-bug-reports-'.date('Y-m-d').'.csv"');
 $h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,['Date and time','Pupil','What the pupil wrote','Where','Device','Status','Fixed on'],',','"','\\');
 foreach($list as $n){$b=(string)$n['body'];$note='';if(preg_match('/^“(.*?)” · (.*)$/su',$b,$m)){$note=$m[1];$b=$m[2];}
  $b=preg_replace('/ · Skipped for this pupil$/','',$b,1,$sk);$parts=explode(' · ',$b);$dev=count($parts)>=2?implode(' · ',array_splice($parts,-2)):'';
  fputcsv($h,[date('Y-m-d H:i',strtotime((string)$n['created_at'])),preg_replace('/^Bug report from /','',(string)$n['title']),$note,implode(' · ',$parts).($sk?' (skipped for this pupil)':''),$dev,$n['kind']==='bugfixed'?'Fixed':'Open',$n['kind']==='bugfixed'&&($fd=bug_fixed_on($n))!==''?date('Y-m-d',strtotime($fd)):''],',','"','\\');}
 exit;
}
function bug_fixed(array $u,bool $all):never{
 if(function_exists('bugflow_legacy_fixed'))bugflow_legacy_fixed($u,$all);
 if(notif_ready()){$ids=$all?array_map('intval',array_column(rows("SELECT id FROM notifications WHERE user_id=? AND kind='bug'",[(int)$u['id']]),'id'))
   :array_map('intval',array_column(rows("SELECT id FROM notifications WHERE id=? AND user_id=? AND kind='bug'",[(int)($_POST['id']??0),(int)$u['id']]),'id'));
  foreach($ids as $id){q("UPDATE notifications SET kind='bugfixed',read_at=COALESCE(read_at,NOW()) WHERE id=?",[$id]);
   q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['bug_fixed_'.$id,date('Y-m-d H:i:s')]);}}
 flash($all?'All reports marked as fixed.':'Marked as fixed.');go('?page=bugs');
}

/** Teacher Overview: "Asked for help". The pupils who tapped "I need help" in the past 7 days, waiting ones first.
    It uses the teacher's help notifications, so "Mark as helped" also clears them from the bell (no new table). */
const HELP_HAND='<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-1V4.5a1.5 1.5 0 0 1 3 0V10m0-4.5a1.5 1.5 0 0 1 3 0V11m0-3a1.5 1.5 0 0 1 3 0v6a7 7 0 0 1-7 7h-1a7 7 0 0 1-5.6-2.8L4 15.5a1.6 1.6 0 0 1 2.5-2L8 15"/></svg>';
function help_teacher_card(array $u):string{
 if(!help_ready()||!notif_ready())return '';$tid=(int)$u['id'];$since=date('Y-m-d H:i:s',strtotime('-7 days'));
 $list=rows("SELECT * FROM notifications WHERE user_id=? AND kind='help' AND created_at>=? ORDER BY read_at IS NULL DESC,created_at DESC,id DESC LIMIT 6",[$tid,$since]);
 if(!$list)return '<section class="card tf-help none" id="tf-help"><p class="tf-empty"><span class="tfh-ic" aria-hidden="true">'.HELP_HAND.'</span><span><b>Asked for help</b> · No one asked for help this week. When a pupil taps “I need help” in a lesson, at school or at home, it shows here and in the bell.</span></p></section>';
 $wait=(int)val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND kind='help' AND read_at IS NULL AND created_at>=?",[$tid,$since]);
 $pids=[];foreach($list as $r)if(preg_match('~[?&]id=(\d+)~',(string)$r['url'],$m))$pids[]=(int)$m[1];
 $who=[];if($pids)foreach(rows('SELECT * FROM users WHERE id IN('.implode(',',array_unique($pids)).')') as $p)$who[(int)$p['id']]=$p;
 $h='<section class="card tf-help'.($wait?' waiting':'').'" id="tf-help"><div class="section-heading"><h2>Asked for help</h2><span class="tfh-sum">'.($wait?'<b>'.$wait.' waiting</b> · ':'').'Last 7 days</span>';
 if($wait>1)$h.='<form method="post" class="tfh-all">'.csrf_field().'<input type="hidden" name="action" value="help_done_all"><button class="btn secondary small">'.icon('check').'Mark all as helped</button></form>';
 $h.='</div><ul class="tfh-list">';
 foreach($list as $r){$new=$r['read_at']===null;$p=preg_match('~[?&]id=(\d+)~',(string)$r['url'],$m)?($who[(int)$m[1]]??null):null;$open='?page=notifications&open='.(int)$r['id'];
  $h.='<li class="tfh-row'.($new?' new':'').'">'.($p?avatar($p):'<span class="tfh-ic" aria-hidden="true">'.HELP_HAND.'</span>').'<span class="tfh-tx"><b>'.e((string)$r['title']).'</b>'.((string)$r['body']!==''?'<small>'.e((string)$r['body']).'</small>':'').'<em>'.e(notif_ago((string)$r['created_at'])).($new?'':' · Seen').'</em></span><span class="tfh-btns">';
  $h.='<a class="btn secondary small" href="'.e($open).'">'.icon('people').'See pupil</a>';
  if($new)$h.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="help_done"><input type="hidden" name="id" value="'.(int)$r['id'].'"><button class="btn primary small">'.icon('check').'Mark as helped</button></form>';
  $h.='</span></li>';}
 return $h.'</ul><p class="tfh-note">Pupils can ask at school or at home. They also show in the bell.</p></section>';
}
/** "Mark as helped" (one, or all of them): the help notification is read, so it also leaves the bell. */
function help_done(array $u,bool $all=false):void{
 if(notif_ready()){if($all)q("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND kind='help' AND read_at IS NULL",[(int)$u['id']]);
  else q("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=? AND kind='help'",[(int)($_POST['id']??0),(int)$u['id']]);}
 flash($all?'All help requests are marked as helped.':'Marked as helped.');go('?page=dashboard#tf-help');
}

function lesson_home_name(array $l):string{$t=(string)$l['title'];$s=trim((string)($l['subtitle']??''));return str_starts_with($t,'Activity')&&$s!==''?$s.' ('.$t.')':$t;}

