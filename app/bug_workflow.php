<?php
function bugflow_ready():bool{static $r;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'bug_report_updates'");}catch(Throwable $e){$r=false;}}return $r;}
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
 if(mb_strlen($reply)>2000||$requested<0||$requested>100)fail('Replies may contain 2,000 characters and deductions must be between 0 and 100 XP.');
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
  if($status==='rejected'&&$r['status']!=='rejected')$reply.=' XP deducted: '.$deducted.'.';
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
 $u=require_role('pupil','teacher');$event=bugflow_ready()?one('SELECT e.id,e.report_id,e.status,e.body,e.deducted_xp FROM bug_report_updates e JOIN bug_reports b ON b.id=e.report_id WHERE b.reporter_id=? AND e.popup_seen_at IS NULL ORDER BY e.id LIMIT 1',[$u['id']]):null;
 header('Cache-Control: no-store');header('Content-Type: application/json');echo json_encode(['update'=>$event],JSON_UNESCAPED_UNICODE);exit;
}
function bugflow_admin(array $u):void{
 $show=(string)($_GET['show']??'open');if(!isset(BUG_STATES[$show]))$show='open';
 echo '<div class="pagehead"><div><span class="eyebrow">SYSTEM</span><h1>Bug reports</h1><p>Review reports, reply to the reporter, or update their status.</p></div></div><nav class="buttonrow">';foreach(BUG_STATES as $k=>$label)echo '<a class="btn '.($show===$k?'primary':'secondary').'" href="?page=bugs&show='.$k.'">'.$label.'</a>';echo '</nav>';
 echo '<div class="buttonrow"><a class="btn secondary" href="?page=bugs_csv&show='.e($show).'">Download '.e(BUG_STATES[$show]).' reports (CSV)</a><a class="btn secondary" href="?page=bugs_csv&show=all">Download all</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_fixed_all"><button class="btn secondary" data-confirm="Mark all open and processing reports as fixed and notify their reporters?">Mark all as fixed</button></form></div>';
 $p=max(1,min(100000,(int)($_GET['p']??1)));$list=rows('SELECT b.*,u.name,u.public_id FROM bug_reports b JOIN users u ON u.id=b.reporter_id WHERE b.status=? ORDER BY b.id DESC LIMIT 30 OFFSET '.(($p-1)*30),[$show]);
 foreach($list as $r)echo '<article class="card"><span class="eyebrow">Report #'.(int)$r['id'].' · '.e($r['created_at']).'</span><h2>'.e($r['summary']).'</h2><p>'.e($r['name'].' · '.$r['public_id']).'</p><a class="btn secondary" href="?page=bug_report&id='.(int)$r['id'].'">Review report</a></article>';
 if(!$list)echo '<p>No '.$show.' reports.</p>';echo '<div class="buttonrow">'.($p>1?'<a class="btn secondary" href="?page=bugs&show='.$show.'&p='.($p-1).'">Previous</a>':'').(count($list)===30?'<a class="btn secondary" href="?page=bugs&show='.$show.'&p='.($p+1).'">Next</a>':'').'</div>';
}
function bugflow_view(array $u):void{
 if(!bugflow_ready()){echo '<section class="card"><h1>Bug reports are being updated</h1><p>Please check back shortly.</p></section>';return;}
 $admin=$u['role']==='admin';$id=(int)($_GET['id']??0);
 if(!$id){
  if($admin){go('?page=bugs');}
  echo '<section class="card"><h1>My bug reports</h1><form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_report_submit"><label>What is not working?<textarea name="note" rows="4" maxlength="2000" required></textarea></label><button class="btn primary">Report a problem</button></form></section>';
  foreach(rows('SELECT id,summary,status FROM bug_reports WHERE reporter_id=? ORDER BY id DESC LIMIT 100',[$u['id']]) as $r)echo '<article class="card"><span class="eyebrow">'.e(BUG_STATES[$r['status']]).'</span><h2><a href="?page=bug_report&id='.(int)$r['id'].'">#'.(int)$r['id'].' · '.e($r['summary']).'</a></h2></article>';return;
 }
 $r=one('SELECT b.*,u.name,u.role FROM bug_reports b JOIN users u ON u.id=b.reporter_id WHERE b.id=?'.($admin?'':' AND b.reporter_id=?'),$admin?[$id]:[$id,$u['id']]);if(!$r)fail('Report not found.',404);
 echo '<section class="card"><span class="eyebrow">Report #'.$id.' · '.e(BUG_STATES[$r['status']]).'</span><h1>'.e($r['summary']).'</h1><p>'.nl2br(e($r['context'])).'</p><p>Reported by '.e($r['name']).'</p>'.((int)$r['deducted_xp']?'<p>XP deducted for this report: '.(int)$r['deducted_xp'].'</p>':'').'</section>';
 $last=(int)val('SELECT COALESCE(MAX(id),0) FROM bug_report_updates WHERE report_id=?',[$id]);
 foreach(rows('SELECT * FROM bug_report_updates WHERE report_id=? ORDER BY id DESC LIMIT 100',[$id]) as $e)echo '<article class="card"><span class="eyebrow">School administrator · '.e(BUG_STATES[$e['status']]).' · '.e($e['created_at']).'</span><p>'.nl2br(e($e['body'])).'</p></article>';
 if($admin){echo '<section class="card"><h2>Update or reply</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="bug_report_update"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="version" value="'.$last.'"><label>Status<select name="status">';foreach(BUG_STATES as $k=>$label)if($r['status']!=='rejected'||$k==='rejected')echo '<option value="'.$k.'"'.($r['status']===$k?' selected':'').'>'.$label.'</option>';
  echo '</select></label><label>Reply or rejection reason<textarea name="reply" rows="4" maxlength="2000"></textarea></label>';
  if($r['status']!=='rejected')echo '<label>XP to deduct only if rejecting a confirmed false report (0–100)<input name="deduct_xp" type="number" min="0" max="100" value="0"'.($r['role']!=='pupil'?' readonly':'').'></label><p>Deductions apply once, up to available earned XP. Teachers have no pupil XP. Reader level and rank may decrease because they use XP. Activity scores and answer history are unchanged.</p><label><input type="checkbox" name="confirm_false" value="1"> I reviewed this report and confirm the rejection and selected XP deduction.</label>';
  echo '<button class="btn primary" data-confirm="Notify the reporter of this update? A confirmed rejection may deduct the selected XP once.">Save and notify</button></form></section>';}
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
