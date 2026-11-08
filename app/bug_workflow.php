<?php
function bugflow_ready():bool{static $r;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'bug_report_updates'");}catch(Throwable $e){$r=false;}}return $r;}
const BUG_FALSE_REPORT_XP=0;   // v210: rejected reports no longer take XP away
const BUG_VALID_REPORT_XP=10;   // v209: a pupil whose report is fixed earns XP once (a real bug, found and told)
const BUG_STATES=['open'=>'Open','processing'=>'Processing','fixed'=>'Fixed','rejected'=>'Rejected'];
/** New reports have a stable reporter ID; legacy notifications are never guessed from names. */
function bugflow_create(array $u,string $summary,string $context):int{
 q('INSERT INTO bug_reports(reporter_id,summary,context) VALUES(?,?,?)',[$u['id'],mb_substr($summary,0,300),$context]);$id=(int)db()->lastInsertId();
 foreach(rows("SELECT id FROM users WHERE role='admin' AND active=1") as $ad){q("INSERT INTO notifications(user_id,kind,title,body,url) VALUES(?,'bug',?,?,?)",[$ad['id'],mb_substr('Bug report from '.$u['name'].' ('.$u['public_id'].')',0,200),mb_substr($context,0,300),'?page=bug_report&id='.$id]);q('INSERT INTO bug_report_links(notification_id,report_id) VALUES(?,?)',[(int)db()->lastInsertId(),$id]);}
 return $id;
}
function bugflow_notify(int $report,int $admin,string $status,string $body,int $deducted):void{
 $pid=(int)val('SELECT reporter_id FROM bug_reports WHERE id=?',[$report]);
 q('INSERT INTO bug_report_updates(report_id,admin_id,status,body,deducted_xp) VALUES(?,?,?,?,?)',[$report,$admin,$status,$body,$deducted]);$event=(int)db()->lastInsertId();
 q("INSERT INTO notifications(user_id,kind,title,body,url) VALUES(?,'note',?,?,?)",[$pid,'Bug report #'.$report.' · '.BUG_STATES[$status],mb_substr($body,0,300),'?page=bug_report&id='.$report]);
 q('UPDATE bug_report_updates SET notification_id=? WHERE id=?',[(int)db()->lastInsertId(),$event]);
}
/** Caller is an authenticated admin. Each status update uses an optimistic version and report row lock. */
function bugflow_apply(array $u,int $id,string $status,string $reply,int $requested=0,?int $version=null):void{
 if(!isset(BUG_STATES[$status]))fail('Choose a valid report status.');
 if(mb_strlen($reply)>2000)fail('Replies may contain up to 2,000 characters.');
 // The server determines the penalty; submitted amounts cannot disable or increase it.
 $requested=BUG_FALSE_REPORT_XP;
 db()->beginTransaction();try{
  $r=one('SELECT * FROM bug_reports WHERE id=? FOR UPDATE',[$id]);if(!$r)fail('Report not found.',404);
  $last=(int)val('SELECT COALESCE(MAX(id),0) FROM bug_report_updates WHERE report_id=?',[$id]);
  if($version!==null&&$version!==$last)fail('This report changed. Reload it before submitting another update.');
  if($r['status']==='rejected'&&$status!=='rejected')fail('A rejected report is closed. You can still send an explanatory reply.');
  $deducted=0;
  if($status==='rejected'&&$r['status']!=='rejected'){
   if($reply==='')fail('Explain why the report is rejected.');
   if(($_POST['confirm_false']??'')!=='1')fail('Confirm that you reviewed the report before rejecting it.');
   if(!(int)$r['penalty_applied']){
    q('SELECT id FROM users WHERE id=? FOR UPDATE',[$r['reporter_id']]);
    if(val('SELECT role FROM users WHERE id=?',[$r['reporter_id']])==='pupil'){
     $available=max(0,(int)val('SELECT total FROM pupil_xp WHERE pupil_id=? FOR UPDATE',[$r['reporter_id']]));$deducted=min($requested,$available);
     if($deducted)q('UPDATE pupil_xp SET total=total-? WHERE pupil_id=?',[$deducted,$r['reporter_id']]);
    }
    q('UPDATE bug_reports SET penalty_applied=1,deducted_xp=? WHERE id=?',[$deducted,$id]);
   }
  }
  if($r['status']===$status&&$reply===''){db()->commit();return;}
  if($reply==='')$reply=['open'=>'Your report is open.','processing'=>'We are checking your report.','fixed'=>'The reported issue has been marked as fixed.','rejected'=>'Your report has been rejected.'][$status];
  if($status==='rejected'&&$r['status']!=='rejected'&&$deducted)$reply.=' XP deducted: '.$deducted.'.';
  /* v209: a valid report (marked Fixed) gives the pupil XP, only once for each report */
  if($status==='fixed'&&$r['status']!=='fixed'&&!(int)$r['penalty_applied']&&val('SELECT role FROM users WHERE id=?',[$r['reporter_id']])==='pupil'&&!val('SELECT 1 FROM settings WHERE setting_key=?',['bug_reward_'.$id])){
   q('INSERT INTO pupil_xp(pupil_id,total) VALUES(?,?) ON DUPLICATE KEY UPDATE total=total+VALUES(total)',[$r['reporter_id'],BUG_VALID_REPORT_XP]);
   q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)',['bug_reward_'.$id,(string)BUG_VALID_REPORT_XP]);
   $reply.=' You earned '.BUG_VALID_REPORT_XP.' XP for this helpful report!';}
  q('UPDATE bug_reports SET status=?,updated_at=NOW() WHERE id=?',[$status,$id]);
  $kind=['open'=>'bug','processing'=>'bugprocessing','fixed'=>'bugfixed','rejected'=>'bugrejected'][$status];
  q('UPDATE notifications n JOIN bug_report_links l ON l.notification_id=n.id SET n.kind=?,n.read_at=COALESCE(n.read_at,NOW()) WHERE l.report_id=?',[$kind,$id]);
  if($status==='fixed')foreach(rows('SELECT notification_id FROM bug_report_links WHERE report_id=?',[$id]) as $n)q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['bug_fixed_'.$n['notification_id'],date('Y-m-d H:i:s')]);
  bugflow_notify($id,(int)$u['id'],$status,$reply,$deducted);audit('bug_report_update','Report '.$id.' · '.$status.' · deducted '.$deducted.' XP');db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function bugflow_action(string $action):void{
 if(!bugflow_ready())fail('Import 034_bug_workflow.sql to enable report updates.');
 if($action==='bug_popup_ack'){
  $u=require_role('pupil','teacher');$event=(int)($_POST['event']??0);
  $r=one('SELECT e.notification_id FROM bug_report_updates e JOIN bug_reports b ON b.id=e.report_id WHERE e.id=? AND b.reporter_id=?',[$event,$u['id']]);if(!$r)fail('Update not found.',404);
  q('UPDATE bug_report_updates SET popup_seen_at=COALESCE(popup_seen_at,NOW()) WHERE id=?',[$event]);if($r['notification_id'])q('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?',[$r['notification_id'],$u['id']]);
  header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;
 }
 if($action==='bug_report_submit'){
  $u=require_role('pupil','teacher');$note=trim((string)($_POST['note']??''));if($note===''||mb_strlen($note)>2000)fail('Describe the problem in up to 2,000 characters.');
  if((int)val('SELECT COUNT(*) FROM bug_reports WHERE reporter_id=? AND created_at>?',[$u['id'],date('Y-m-d H:i:s',time()-600)])>=3)fail('We already received your reports. Please wait before sending another.');
  db()->beginTransaction();try{$id=bugflow_create($u,mb_substr($note,0,300),$note.' · '.help_device((string)($_SERVER['HTTP_USER_AGENT']??'')));db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=bug_report&id='.$id);
 }
 $u=require_role('admin');if(!empty($_SESSION['pin_pending']))fail('Verify your admin PIN first.',403);
 if($action==='bug_adopt'){
  $n=one("SELECT * FROM notifications WHERE id=? AND user_id=? AND kind IN ('bug','bugfixed')",[(int)($_POST['id']??0),$u['id']]);if(!$n)fail('Report not found.',404);
  if(!preg_match('/\(([^()]+)\)$/',(string)$n['title'],$match))fail('This older report has no verifiable account ID. No points were deducted.');
  $reporter=one("SELECT * FROM users WHERE public_id=? AND role IN ('pupil','teacher')",[$match[1]]);if(!$reporter&&function_exists('account_id_moved'))$reporter=account_id_moved($match[1]);
  if(!$reporter||!in_array($reporter['role'],['pupil','teacher'],true))fail('The reporter account cannot be verified. No points were deducted.');
  db()->beginTransaction();try{
   $matches=rows("SELECT id FROM notifications WHERE title=? AND body=? AND ABS(TIMESTAMPDIFF(SECOND,created_at,?))<=3 AND kind IN ('bug','bugfixed') ORDER BY id FOR UPDATE",[$n['title'],$n['body'],$n['created_at']]);
   $ids=array_column($matches,'id');$existing=$ids?one('SELECT report_id FROM bug_report_links WHERE notification_id IN ('.implode(',',array_fill(0,count($ids),'?')).') LIMIT 1',$ids):null;
   if($existing)$report=(int)$existing['report_id'];else{$report=bugflow_create($reporter,mb_substr((string)$n['body'],0,300),(string)$n['body']);q('UPDATE bug_reports SET created_at=?,status=? WHERE id=?',[$n['created_at'],$n['kind']==='bugfixed'?'fixed':'open',$report]);}
   foreach($ids as $nid)q('INSERT IGNORE INTO bug_report_links(notification_id,report_id) VALUES(?,?)',[$nid,$report]);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}go('?page=bug_report&id='.$report);
 }
 bugflow_apply($u,(int)($_POST['id']??0),(string)($_POST['status']??''),trim((string)($_POST['reply']??'')),(int)($_POST['deduct_xp']??0),(int)($_POST['version']??-1));flash('Report updated. The reporter has been notified.');go('?page=bug_report&id='.(int)$_POST['id']);
}
function bugflow_poll():never{
 $u=require_role('pupil','teacher');$event=bugflow_ready()?one('SELECT e.id,e.report_id,e.status,e.body,e.deducted_xp,b.summary FROM bug_report_updates e JOIN bug_reports b ON b.id=e.report_id WHERE b.reporter_id=? AND e.popup_seen_at IS NULL ORDER BY e.id LIMIT 1',[$u['id']]):null;
 header('Cache-Control: no-store');header('Content-Type: application/json');echo json_encode(['update'=>$event],JSON_UNESCAPED_UNICODE);exit;
}
/** v209 how a date shows on the bug pages: "Nov 3, 2026 · 12:26 PM" */
function bug_when(string $t):string{$x=strtotime($t);return $x?date('M j, Y · g:i A',$x):$t;}
/** v209 the person who sent a report: a round initial, the name, and their role and ID */
function bug_who(string $name,string $role,string $pid):string{
 return '<span class="bg-who"><span class="bg-av bg-av-'.e($role).'" aria-hidden="true">'.e(mb_strtoupper(mb_substr(trim($name),0,1))).'</span><span><b>'.e($name).'</b><small>'.e(ucfirst($role)).' · '.e($pid).'</small></span></span>';
}
/** v209 the report's details: the reporter's words as a quote, then where and on which device, as short lines */
function bug_context(string $c):string{
 $parts=array_values(array_filter(array_map('trim',explode(' · ',$c)),'strlen'));if(!$parts)return '';$h='';
 if(preg_match('~^[“"](.*)[”"]$~su',$parts[0],$m)){$h.='<blockquote class="bg-quote">'.icon('comic').'<span>'.nl2br(e($m[1])).'</span></blockquote>';array_shift($parts);}
 if($parts){$h.='<ul class="bg-facts">';foreach($parts as $p){$ic=preg_match('~iPhone|iPad|Android|Windows|Mac|Chrome|Safari|Firefox|Edge|phone|computer|tablet~i',$p)?'phone':(preg_match('~skipped~i',$p)?'flag':(preg_match('~^Level|lesson|activity|page~i',$p)?'book':'info'));$h.='<li>'.icon($ic==='phone'?'school':$ic).'<span>'.e($p).'</span></li>';}$h.='</ul>';}
 return '<div class="bug-report-context">'.$h.'</div>';
}
function bugflow_admin(array $u):void{
 $show=(string)($_GET['show']??'open');if(!isset(BUG_STATES[$show]))$show='open';
 $n=[];foreach(rows('SELECT status,COUNT(*) n FROM bug_reports GROUP BY status') as $r)$n[$r['status']]=(int)$r['n'];
 echo hm_head('SYSTEM','Bug reports','Reports from teachers and pupils. Choose a report to reply or change its status. BULIG tells the reporter right away.');
 echo '<nav class="bug-filters bg-tabs" aria-label="Report status">';foreach(BUG_STATES as $k=>$label)echo '<a class="bg-tab bg-'.$k.($show===$k?' on':'').'" href="?page=bugs&show='.$k.'"'.($show===$k?' aria-current="page"':'').'><span class="bg-dot"></span>'.$label.'<b>'.number_format($n[$k]??0).'</b></a>';echo '</nav>';
 $p=max(1,min(100000,(int)($_GET['p']??1)));$list=rows('SELECT b.*,u.name,u.public_id,u.role,(SELECT COUNT(*) FROM bug_report_updates x WHERE x.report_id=b.id) replies FROM bug_reports b JOIN users u ON u.id=b.reporter_id WHERE b.status=? ORDER BY b.id DESC LIMIT 30 OFFSET '.(($p-1)*30),[$show]);
 echo '<section class="card bg-list"><div class="bg-list-hd"><h2>'.e(BUG_STATES[$show]).' reports</h2><div class="buttonrow bug-toolbar"><a class="btn secondary small" href="?page=bugs_csv&show='.e($show).'">'.icon('download').'Download this list</a><a class="btn secondary small" href="?page=bugs_csv&show=all">'.icon('download').'Download all</a>'
  .(($n['open']??0)+($n['processing']??0)?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_fixed_all"><button class="btn quiet small" data-confirm="Mark all open and processing reports as fixed and notify their reporters?">'.icon('check').'Mark all as fixed</button></form>':'').'</div></div>';
 foreach($list as $r)echo '<a class="bug-report-tile br-item bg-row" href="?page=bug_report&id='.(int)$r['id'].'"><span class="bug-status status-'.e($r['status']).'">'.e(BUG_STATES[$r['status']]).'</span><span class="br-txt"><b>'.e($r['summary']).'</b><small>Report #'.(int)$r['id'].' · '.e($r['name']).' · '.e(ucfirst((string)$r['role'])).' · '.e(bug_when((string)$r['created_at'])).((int)$r['replies']?' · '.(int)$r['replies'].' '.((int)$r['replies']===1?'reply':'replies'):'').'</small></span>'.icon('chev').'</a>';
 if(!$list)echo '<div class="bug-empty bg-empty"><img src="'.e(friend_img('bob','thumbs')).'" alt="" width="70" height="152"><div><b>'.($show==='open'?'No open reports. Everything is working!':'No '.mb_strtolower(BUG_STATES[$show]).' reports.').'</b><small>'.($show==='open'?'New reports from teachers and pupils show here.':'Reports move here when you change their status.').'</small></div></div>';
 echo '</section><div class="buttonrow">'.($p>1?'<a class="btn secondary" href="?page=bugs&show='.$show.'&p='.($p-1).'">Previous</a>':'').(count($list)===30?'<a class="btn secondary" href="?page=bugs&show='.$show.'&p='.($p+1).'">Next</a>':'').'</div>';
}
function bugflow_view(array $u):void{
 if(!bugflow_ready()){echo '<section class="card"><h1>Bug reports are being updated</h1><p>Please check back shortly.</p></section>';return;}
 $admin=$u['role']==='admin';$id=(int)($_GET['id']??0);
 if(!$id){
  if($admin)go('?page=bugs');
  /* v203 tidy page: a short heading, the report form in its own card, then each report as one row with its status and replies. */
  echo '<div class="pagehead"><div><span class="eyebrow">HELP</span><h1>My bug reports</h1><p>Something not working in BULIG? Tell us here. The BULIG team reads every report and replies on this page.</p></div></div>';
  echo '<section class="card br-new"><span class="br-ic" aria-hidden="true">'.icon('alert').'</span><form method="post" class="br-form">'.csrf_field().'<input type="hidden" name="action" value="bug_report_submit"><label for="br-note">What is not working?</label><textarea id="br-note" name="note" rows="3" maxlength="2000" required placeholder="What happened? Which page were you on? Example: The microphone does not start in My lessons."></textarea><div class="br-row"><small>Your device type is sent with your report, so we can check it.</small><button class="btn primary">'.icon('check').'Send report</button></div></form></section>';
  $mine=rows('SELECT b.id,b.summary,b.status,b.created_at,b.updated_at,(SELECT COUNT(*) FROM bug_report_updates x WHERE x.report_id=b.id) replies FROM bug_reports b WHERE b.reporter_id=? ORDER BY b.id DESC LIMIT 100',[$u['id']]);
  echo '<section class="card br-list"><div class="ws-hd"><h2>Your reports</h2><small class="muted">'.count($mine).' '.(count($mine)===1?'report':'reports').'</small></div>';
  if(!$mine)echo '<p class="muted br-none">No reports yet. If something does not work, write it above.</p>';
  foreach($mine as $r){$when=strtotime((string)$r['created_at']);
   echo '<a class="br-item" href="?page=bug_report&amp;id='.(int)$r['id'].'"><span class="bug-status status-'.e($r['status']).'">'.e(BUG_STATES[$r['status']]).'</span><span class="br-txt"><b>'.e($r['summary']).'</b><small>Report #'.(int)$r['id'].' · '.e($when?date('M j, Y',$when):'').((int)$r['replies']?' · '.(int)$r['replies'].' '.((int)$r['replies']===1?'reply':'replies'):'').'</small></span>'.icon('chev').'</a>';}
  echo '</section>';return;
 }
 $r=one('SELECT b.*,u.name,u.role,u.public_id FROM bug_reports b JOIN users u ON u.id=b.reporter_id WHERE b.id=?'.($admin?'':' AND b.reporter_id=?'),$admin?[$id]:[$id,$u['id']]);if(!$r)fail('Report not found.',404);
 $last=(int)val('SELECT COALESCE(MAX(id),0) FROM bug_report_updates WHERE report_id=?',[$id]);$updates=array_reverse(rows('SELECT * FROM bug_report_updates WHERE report_id=? ORDER BY id DESC LIMIT 100',[$id]));   // v209: oldest first, like a chat
 echo '<div class="bug-detail-header"><a class="btn secondary small" href="'.($admin?'?page=bugs&show='.e($r['status']):'?page=bug_report').'">'.icon('arrow','flip').'Back to reports</a><span class="bug-status status-'.e($r['status']).'">'.e(BUG_STATES[$r['status']]).'</span></div><div class="bug-detail-layout'.($admin?'':' reporter-view').'"><div class="bug-conversation">'
  .'<section class="card bug-report-summary"><span class="eyebrow">REPORT #'.$id.' · '.e(mb_strtoupper(bug_when((string)$r['created_at']))).'</span><h1>'.e($r['summary']).'</h1>'.bug_who((string)$r['name'],(string)$r['role'],(string)$r['public_id']).bug_context((string)$r['context'])
  .((int)$r['deducted_xp']?'<p class="bug-penalty-notice">XP deducted for this report: <strong>'.(int)$r['deducted_xp'].'</strong></p>':'').'</section><section class="card bug-thread"><h2>'.icon('comic').'Updates and replies</h2>';
 if(!$updates)echo '<p class="muted bg-none">No updates yet. Replies and status changes will show here.</p>';
 foreach($updates as $e)echo '<article class="bug-thread-entry"><img class="bg-bob" src="'.e(friend_img('bob','hello')).'" alt="" width="40" height="87"><div class="bg-bub"><div class="bug-update-meta"><strong>BULIG team</strong><span class="bug-status status-'.e($e['status']).'">'.e(BUG_STATES[$e['status']]).'</span></div><p>'.nl2br(e($e['body'])).'</p><time class="muted">'.e(bug_when((string)$e['created_at'])).'</time></div></article>';
 echo '</section></div>';
 if($admin){echo '<aside class="card bug-moderation" aria-label="Update report"><h2>Update report</h2><p class="muted">Change the status or send a reply.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_report_update"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="version" value="'.$last.'"><label for="bug-status">Status<select id="bug-status" name="status">';foreach(BUG_STATES as $k=>$label)if($r['status']!=='rejected'||$k==='rejected')echo '<option value="'.$k.'"'.($r['status']===$k?' selected':'').'>'.$label.'</option>';
  echo '</select></label><label for="bug-reply">Reply or rejection reason<textarea id="bug-reply" name="reply" rows="6" maxlength="2000" placeholder="Explain the update to the reporter." data-bug-auto="'.e(json_encode(bugflow_auto_replies((string)$r['name'],(string)$r['role']),JSON_UNESCAPED_UNICODE)).'"></textarea></label><p class="bug-auto-note muted" data-bug-auto-note hidden>'.icon('edit').'<span>A message is ready for this status. You can change it before you send.</span><button type="button" class="btn quiet small" data-bug-auto-reset>Use the ready message again</button></p>';
  if($r['status']!=='rejected'){
   if($r['role']==='pupil'&&!val('SELECT 1 FROM settings WHERE setting_key=?',['bug_reward_'.$id]))echo '<div class="bug-reward-notice" data-bug-for="fixed"'.($r['status']==='fixed'?'':' hidden').'>'.icon('star').'<span><strong>Helpful report: +'.BUG_VALID_REPORT_XP.' XP</strong> When you mark it Fixed, the pupil gets '.BUG_VALID_REPORT_XP.' XP once, for finding a real problem.</span></div>';
   echo '<div data-bug-for="rejected" hidden>';
   if($r['role']==='pupil'&&BUG_FALSE_REPORT_XP)echo '<div class="bug-penalty-notice"><strong>Automatic false-report deduction: '.BUG_FALSE_REPORT_XP.' XP</strong><p>Applies only when you reject a confirmed false report. It is capped at available earned XP and applied once. XP-based reader level/rank may decrease; lesson scores stay unchanged.</p></div>';
   else echo '<div class="bug-penalty-notice">This reporter is a teacher. No pupil XP will be deducted.</div>';
   echo '<label class="bug-confirm"><input type="checkbox" name="confirm_false" value="1"><span>I reviewed this report and confirm the rejection'.($r['role']==='pupil'&&BUG_FALSE_REPORT_XP?' and the automatic XP deduction':'').'.</span></label></div>';
  }else echo '<div class="bug-penalty-notice"><strong>Report closed</strong><p>Recorded deduction: '.(int)$r['deducted_xp'].' XP. Further replies will not deduct again.</p></div>';
  echo '<button class="btn primary" data-bug-save>Save and notify</button></form></aside>';
 }
 echo '</div>';
}

/** v209: a ready reply for each status, with the reporter's first name. The administrator can change it before sending. */
function bugflow_auto_replies(string $name,string $role):array{
 $n=trim(explode(' ',trim($name))[0]??'');$hi=$n!==''?'Hi '.$n.'! ':'Hi! ';$kid=$role==='pupil';
 return [
  'open'=>$hi.'We got your report. The BULIG team will check it soon. Thank you for telling us!',
  'processing'=>$hi.'We are checking your report now. We will tell you as soon as it is fixed. Thank you for waiting!',
  'fixed'=>'Thank you'.($n!==''?', '.$n:'').'! You were right, and we fixed it. Please try it again.'.($kid?' Keep reading!':' Thank you for helping make BULIG better.'),
  'rejected'=>$hi.'We checked your report carefully, but we did not find a problem this time.'.($kid?' Please send a report only when something is really not working.':' If it happens again, please send a picture of the screen.')];
}
function bugflow_legacy_fixed(array $u,bool $all):bool{
 if(!bugflow_ready())return false;
 if($all){foreach(rows("SELECT id FROM bug_reports WHERE status IN ('open','processing')") as $r)bugflow_apply($u,(int)$r['id'],'fixed','The reported issue has been marked as fixed.');return false;}
 $r=one('SELECT l.report_id FROM bug_report_links l JOIN notifications n ON n.id=l.notification_id WHERE n.id=? AND n.user_id=?',[(int)($_POST['id']??0),$u['id']]);if(!$r)return false;bugflow_apply($u,(int)$r['report_id'],'fixed','The reported issue has been marked as fixed.');flash('Marked as fixed. The reporter has been notified.');go('?page=bugs');
}

/** Export canonical and older reports without losing processing/rejected states. */
function bugflow_csv(array $u):never{
 $show=(string)($_GET['show']??'all');$filter=isset(BUG_STATES[$show]);
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-bug-reports-'.date('Y-m-d').'.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
 $write=function(array $cells)use($out){$safe=array_map(fn($v)=>preg_match('/^[=+@\\-]/',(string)$v)?"'".$v:(string)$v,$cells);fputcsv($out,$safe,',','"','\\');};
 $write(['When','Reporter','Status','Description','Context','XP deducted','Report']);
 foreach(rows('SELECT b.*,u.name,u.public_id FROM bug_reports b JOIN users u ON u.id=b.reporter_id'.($filter?' WHERE b.status=?':'').' ORDER BY b.id DESC',$filter?[$show]:[]) as $b)$write([$b['created_at'],$b['name'].' ('.$b['public_id'].')',BUG_STATES[$b['status']],$b['summary'],$b['context'],$b['deducted_xp'],'#'.$b['id']]);
 if(!$filter||in_array($show,['open','fixed'],true))foreach(rows("SELECT * FROM notifications WHERE user_id=? AND kind IN ('bug','bugfixed') AND id NOT IN (SELECT notification_id FROM bug_report_links)".($filter?' AND kind=?':'').' ORDER BY id DESC',$filter?[$u['id'],$show==='fixed'?'bugfixed':'bug']:[$u['id']]) as $n)$write([$n['created_at'],$n['title'],$n['kind']==='bugfixed'?'Fixed':'Open',$n['body'],'Earlier report',0,'Notification #'.$n['id']]);
 fclose($out);exit;
}

