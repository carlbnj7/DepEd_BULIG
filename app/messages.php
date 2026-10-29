<?php
/* Admin-only sending; immutable text messages and per-recipient read receipts. */
function messages_ready():bool{static $ready;if($ready===null){try{$ready=(bool)one("SHOW TABLES LIKE 'message_recipients'");}catch(Throwable $e){$ready=false;}}return $ready;}
function message_targets(string $scope,int $target=0):array{
 if($scope==='person')return rows("SELECT id,name,public_id,role FROM users WHERE id=? AND active=1 AND role IN ('pupil','teacher')",[$target]);
 if($scope==='class')return rows("SELECT DISTINCT u.id,u.name,u.public_id,u.role FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN sections s ON s.id=ps.section_id WHERE s.id=? AND u.active=1 AND u.role='pupil'".(sections_archive_supported()?" AND s.archived_at IS NULL":""),[$target]);
 $roles=['pupils'=>['pupil'],'teachers'=>['teacher'],'everyone'=>['pupil','teacher']][$scope]??[];
 if(!$roles)return [];
 return rows('SELECT id,name,public_id,role FROM users WHERE active=1 AND role IN ('.implode(',',array_fill(0,count($roles),'?')).') ORDER BY name,id',$roles);
}
function messages_action(string $action):void{
 $u=require_role('admin');if(!empty($_SESSION['pin_pending']))fail('Verify your admin PIN first.',403);if(!messages_ready()||!notif_ready())fail('Import 033_admin_messages.sql before sending messages.');
 if($action==='message_preview'){
  $subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));
  if($subject===''||mb_strlen($subject)>150||$body===''||mb_strlen($body)>5000)fail('Enter a subject (up to 150 characters) and a message (up to 5,000 characters).');
  $scope=(string)($_POST['scope']??'');$target=(int)($_POST[$scope==='class'?'section':'person']??0);$people=message_targets($scope,$target);
  if(!$people)fail('Choose at least one active pupil or teacher.');
  $token=bin2hex(random_bytes(24));$_SESSION['message_draft']=['token'=>$token,'sender'=>(int)$u['id'],'subject'=>$subject,'body'=>$body,'ids'=>array_map('intval',array_column($people,'id')),'scope'=>$scope,'at'=>time()];go('?page=messages&preview=1');
 }
 if($action==='message_send'){
  $d=$_SESSION['message_draft']??null;$token=(string)($_POST['token']??'');
  if(!$d||!hash_equals($d['token'],$token)||(int)$d['sender']!==(int)$u['id']||$d['at']<time()-1800)fail('This preview expired. Please compose your message again.');
  db()->beginTransaction();try{
   q('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);
   $existing=one('SELECT id FROM admin_messages WHERE send_token=? AND sender_id=?',[$token,$u['id']]);
   if($existing){db()->commit();go('?page=messages&id='.(int)$existing['id']);}
   // Revalidate the preview snapshot without adding any newly joined recipients.
   q('INSERT INTO admin_messages(sender_id,send_token,subject,body) VALUES(?,?,?,?)',[$u['id'],$token,$d['subject'],$d['body']]);$id=(int)db()->lastInsertId();
   foreach(array_chunk($d['ids'],500) as $chunk)q("INSERT INTO message_recipients(message_id,user_id) SELECT ?,id FROM users WHERE active=1 AND role IN ('pupil','teacher') AND id IN (".implode(',',array_fill(0,count($chunk),'?')).")",array_merge([$id],$chunk));
   $sent=(int)val('SELECT COUNT(*) FROM message_recipients WHERE message_id=?',[$id]);
   if(!$sent)fail('Those recipients are no longer active. Nothing was sent.');
   q("INSERT INTO notifications(user_id,kind,title,body,url) SELECT user_id,'note',?,?,? FROM message_recipients WHERE message_id=?",[$d['subject'],'A message from your school administrator.','?page=messages&id='.$id,$id]);
   audit('admin_message','Message '.$id.' to '.$sent.' accounts');db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Message sent to '.$sent.' recipients.');go('?page=messages&id='.$id);
 }
 fail('Unknown message action.');
}
function messages_view(array $u):void{
 $admin=$u['role']==='admin';$uid=(int)$u['id'];echo '<div class="pagehead"><div><span class="eyebrow">BULIG</span><h1>Messages</h1><p>'.($admin?'Send a school message and see who has opened it.':'Messages from your school administrator.').'</p></div></div>';
 if(!messages_ready()){echo '<section class="card"><h2>Messages are being set up</h2><p>'.($admin?'Import database/migrations/033_admin_messages.sql to enable messages.':'Please check back later.').'</p></section>';return;}
 $id=(int)($_GET['id']??0);
 if($id){
  $m=$admin?one('SELECT * FROM admin_messages WHERE id=? AND sender_id=?',[$id,$uid]):one('SELECT m.* FROM admin_messages m JOIN message_recipients r ON r.message_id=m.id WHERE m.id=? AND r.user_id=?',[$id,$uid]);
  if(!$m)fail('Message not found.',404);
  if(!$admin){q('UPDATE message_recipients SET read_at=COALESCE(read_at,NOW()) WHERE message_id=? AND user_id=?',[$id,$uid]);q("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND url=?",[$uid,'?page=messages&id='.$id]);}
  echo '<p><a class="btn secondary" href="?page=messages">'.icon('arrow').'Back to messages</a></p><article class="card"><span class="eyebrow">School administrator · '.e($m['created_at']).'</span><h2>'.e($m['subject']).'</h2><p>'.nl2br(e($m['body'])).'</p></article>';
  if($admin){$stats=one('SELECT COUNT(*) total,SUM(read_at IS NOT NULL) opened FROM message_recipients WHERE message_id=?',[$id]);echo '<section class="card"><h2>Read status</h2><p>'.(int)$stats['opened'].' of '.(int)$stats['total'].' recipients opened this message.</p>';
   $p=max(1,(int)($_GET['p']??1));foreach(rows('SELECT u.name,u.public_id,u.role,r.read_at FROM message_recipients r JOIN users u ON u.id=r.user_id WHERE r.message_id=? ORDER BY u.name,u.id LIMIT 50 OFFSET '.(($p-1)*50),[$id]) as $r)echo '<p><strong>'.e($r['name']).'</strong> · '.e($r['public_id']).' · '.e($r['role']).' — '.($r['read_at']?'Opened '.e($r['read_at']):'Not opened yet').'</p>';
   messages_pager($p,(int)$stats['total'],50,'?page=messages&id='.$id);echo '</section>';}
  return;
 }
 if($admin&&!empty($_GET['preview'])){
  $d=$_SESSION['message_draft']??null;if(!$d||$d['sender']!==$uid||$d['at']<time()-1800)fail('This preview expired. Please compose your message again.');
  echo '<section class="card"><h2>Check before sending</h2><p><strong>'.count($d['ids']).' recipients</strong> · '.e($d['scope']).'</p><p>This sends to the selected recipients across the school accounts. Inactive recipients will be skipped.</p><h3>'.e($d['subject']).'</h3><p>'.nl2br(e($d['body'])).'</p>';
  foreach(array_slice($d['ids'],0,20) as $rid){$r=one('SELECT name,public_id FROM users WHERE id=?',[$rid]);if($r)echo '<p>'.e($r['name']).' · '.e($r['public_id']).'</p>';}
  if(count($d['ids'])>20)echo '<p>And '.(count($d['ids'])-20).' more recipients.</p>';
  echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="message_send"><input type="hidden" name="token" value="'.e($d['token']).'"><button class="btn primary">Send message</button> <a class="btn secondary" href="?page=messages&compose=1">Cancel</a></form></section>';return;
 }
 if($admin&&!empty($_GET['compose'])){
  $search=trim((string)($_GET['find']??''));echo '<section class="card"><h2>New message</h2><form method="get"><input type="hidden" name="page" value="messages"><input type="hidden" name="compose" value="1"><label>Find a pupil or teacher<input name="find" maxlength="150" value="'.e($search).'" placeholder="Name or account ID"></label><button class="btn secondary">Find</button></form><form method="post">'.csrf_field().'<input type="hidden" name="action" value="message_preview"><label>Send to<select name="scope"><option value="person">One pupil or teacher</option><option value="class">A class (pupils)</option><option value="pupils">All pupils</option><option value="teachers">All teachers</option><option value="everyone">All pupils and teachers</option></select></label><label>Person (for one recipient)<select name="person"><option value="">Choose a person</option>';
  $people=$search!==''?rows("SELECT id,name,public_id,role FROM users WHERE active=1 AND role IN ('pupil','teacher') AND (name LIKE ? OR public_id LIKE ?) ORDER BY name,id LIMIT 50",['%'.$search.'%','%'.$search.'%']):[];
  foreach($people as $r)echo '<option value="'.(int)$r['id'].'">'.e($r['name'].' · '.$r['public_id'].' · '.$r['role']).'</option>';
  echo '</select></label><small>Search above first. Up to 50 matching accounts are shown.</small><label>Class (for a class message)<select name="section"><option value="">Choose a class</option>';
  foreach(rows('SELECT s.id,s.name,s.grade_level,u.name teacher FROM sections s JOIN users u ON u.id=s.teacher_id'.(sections_archive_supported()?' WHERE s.archived_at IS NULL':'').' ORDER BY s.grade_level,s.name,u.name') as $s)echo '<option value="'.(int)$s['id'].'">'.e('Grade '.$s['grade_level'].' · '.$s['name'].' · '.$s['teacher']).'</option>';
  echo '</select></label><label>Subject<input name="subject" maxlength="150" required></label><label>Message<textarea name="body" rows="8" maxlength="5000" required></textarea></label><p>Plain text only. Recipients can read but cannot reply.</p><button class="btn primary">Preview message</button> <a class="btn secondary" href="?page=messages">Cancel</a></form></section>';return;
 }
 if($admin)echo '<p><a class="btn primary" href="?page=messages&compose=1">New message</a></p>';
 $p=max(1,(int)($_GET['p']??1));$total=(int)val($admin?'SELECT COUNT(*) FROM admin_messages WHERE sender_id=?':'SELECT COUNT(*) FROM message_recipients WHERE user_id=?',[$uid]);
 $list=rows(($admin?'SELECT m.*,NULL read_at FROM admin_messages m WHERE sender_id=?':'SELECT m.*,r.read_at FROM message_recipients r JOIN admin_messages m ON m.id=r.message_id WHERE r.user_id=?').' ORDER BY m.id DESC LIMIT 25 OFFSET '.(($p-1)*25),[$uid]);
 if(!$list)echo '<section class="card"><h2>No messages yet</h2></section>';
 foreach($list as $m)echo '<article class="card"><span class="eyebrow">'.e($m['created_at']).(!$admin&&$m['read_at']===null?' · NEW':'').'</span><h2><a href="?page=messages&id='.(int)$m['id'].'">'.e($m['subject']).'</a></h2><p>'.e(mb_substr($m['body'],0,140)).'</p></article>';
 messages_pager($p,$total,25,'?page=messages');
}
function messages_pager(int $p,int $total,int $size,string $base):void{echo '<nav class="buttonrow" aria-label="Message pages">'.($p>1?'<a class="btn secondary" href="'.e($base.'&p='.($p-1)).'">Previous</a>':'').($p*$size<$total?'<a class="btn secondary" href="'.e($base.'&p='.($p+1)).'">Next</a>':'').'</nav>';}
