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
function messages_content(array $u):void{
 $admin=$u['role']==='admin';$uid=(int)$u['id'];
 if(!messages_ready()){echo '<section class="card"><h2>Messages are being set up</h2><p>'.($admin?'Import database/migrations/033_admin_messages.sql to enable messages.':'Please check back later.').'</p></section>';return;}
 $id=(int)($_GET['id']??0);
 if($id){
  $m=$admin?one('SELECT * FROM admin_messages WHERE id=? AND sender_id=?',[$id,$uid]):one('SELECT m.* FROM admin_messages m JOIN message_recipients r ON r.message_id=m.id WHERE m.id=? AND r.user_id=?',[$id,$uid]);
  if(!$m)fail('Message not found.',404);
  if(!$admin){q('UPDATE message_recipients SET read_at=COALESCE(read_at,NOW()) WHERE message_id=? AND user_id=?',[$id,$uid]);q("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND url LIKE ?",[$uid,'?page=messages&id='.$id.'%']);echo message_chat($m,$uid,false,[]);return;}
  $with=(int)($_GET['with']??0);
  if($with){$person=one('SELECT u.id,u.name,u.public_id,u.role FROM message_recipients r JOIN users u ON u.id=r.user_id WHERE r.message_id=? AND r.user_id=?',[$id,$with]);if(!$person)fail('Message not found.',404);
   q("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND url LIKE ?",[$uid,'?page=messages&id='.$id.'&with='.$with.'%']);echo message_chat($m,$with,true,$person);return;}
  $stats=one('SELECT COUNT(*) total,SUM(read_at IS NOT NULL) opened FROM message_recipients WHERE message_id=?',[$id]);
  echo '<header class="mc-head"><a class="mc-back" href="?page=messages&amp;tab=school" aria-label="Back to sent messages">'.icon('arrow').'</a><span class="mc-av" aria-hidden="true">'.icon('comic').'</span><div><b>'.e($m['subject']).'</b><small>Sent '.e(date('M j, Y · g:i A',strtotime((string)$m['created_at']))).' · '.(int)$stats['opened'].' of '.(int)$stats['total'].' recipients opened it</small></div></header>';
  echo '<div class="mc-log"><div class="mc-row me"><div class="mc-bub mc-first"><b>'.e($m['subject']).'</b><p>'.nl2br(e($m['body'])).'</p></div></div></div>';
  if(replies_ready()){$th=rows('SELECT u.id,u.name,u.public_id,u.role,COUNT(*) n,SUM(x.author_id=x.user_id AND x.read_at IS NULL) unread,MAX(x.id) last FROM message_replies x JOIN users u ON u.id=x.user_id WHERE x.message_id=? GROUP BY u.id,u.name,u.public_id,u.role ORDER BY last DESC LIMIT 200',[$id]);
   echo '<section class="mc-threads"><h2>Replies <small>'.count($th).'</small></h2>';
   foreach($th as $t){$last=one('SELECT body,created_at FROM message_replies WHERE id=?',[$t['last']]);echo '<a class="msg-item'.((int)$t['unread']?' unread':'').'" href="?page=messages&amp;id='.$id.'&amp;with='.(int)$t['id'].'#msg-end"><span class="msg-avatar" aria-hidden="true">'.e(mb_strtoupper(mb_substr(trim((string)$t['name']),0,1))).'</span><span class="msg-summary"><span class="msg-item-top"><strong>'.e($t['name']).'</strong>'.((int)$t['unread']?'<span class="msg-dot" aria-label="Unread"></span>':'').'</span><small>'.e(mb_substr((string)$last['body'],0,80)).'</small><time>'.e(date('M j, g:i A',strtotime((string)$last['created_at']))).'</time></span></a>';}
   if(!$th)echo '<p class="msg-empty">No replies yet. When someone replies, their chat shows here.</p>';echo '</section>';}
  echo '<details class="mc-status"><summary>Read status</summary>';
  $p=max(1,(int)($_GET['p']??1));foreach(rows('SELECT u.id,u.name,u.public_id,u.role,r.read_at FROM message_recipients r JOIN users u ON u.id=r.user_id WHERE r.message_id=? ORDER BY u.name,u.id LIMIT 50 OFFSET '.(($p-1)*50),[$id]) as $r)echo '<p><strong>'.e($r['name']).'</strong> · '.e($r['public_id']).' · '.e($r['role']).' — '.($r['read_at']?'Opened '.e($r['read_at']):'Not opened yet').(replies_ready()?' · <a href="?page=messages&amp;id='.$id.'&amp;with='.(int)$r['id'].'#msg-end">Chat</a>':'').'</p>';
  messages_pager($p,(int)$stats['total'],50,'?page=messages&id='.$id);echo '</details>';
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
  echo '</select></label><label>Subject<input name="subject" maxlength="150" required></label><label>Message<textarea name="body" rows="8" maxlength="5000" required></textarea></label><p>Plain text only. Each person can reply to you in a private chat.</p><button class="btn primary">Preview message</button> <a class="btn secondary" href="?page=messages">Cancel</a></form></section>';return;
 }
 if($admin)echo '<p><a class="btn primary" href="?page=messages&compose=1">New message</a></p>';
 $p=max(1,(int)($_GET['p']??1));$total=(int)val($admin?'SELECT COUNT(*) FROM admin_messages WHERE sender_id=?':'SELECT COUNT(*) FROM message_recipients WHERE user_id=?',[$uid]);
 $list=rows(($admin?'SELECT m.*,NULL read_at FROM admin_messages m WHERE sender_id=?':'SELECT m.*,r.read_at FROM message_recipients r JOIN admin_messages m ON m.id=r.message_id WHERE r.user_id=?').' ORDER BY m.id DESC LIMIT 25 OFFSET '.(($p-1)*25),[$uid]);
 if(!$list)echo '<section class="card"><h2>No messages yet</h2></section>';
 foreach($list as $m)echo '<article class="card"><span class="eyebrow">'.e($m['created_at']).(!$admin&&$m['read_at']===null?' · NEW':'').'</span><h2><a href="?page=messages&id='.(int)$m['id'].'">'.e($m['subject']).'</a></h2><p>'.e(mb_substr($m['body'],0,140)).'</p></article>';
 messages_pager($p,$total,25,'?page=messages');
}
function messages_pager(int $p,int $total,int $size,string $base):void{echo '<nav class="buttonrow" aria-label="Message pages">'.($p>1?'<a class="btn secondary" href="'.e($base.'&p='.($p-1)).'">Previous</a>':'').($p*$size<$total?'<a class="btn secondary" href="'.e($base.'&p='.($p+1)).'">Next</a>':'').'</nav>';}


/** Messenger-style split pane; existing sending, access checks and read receipts are unchanged. */
/** v209 The tabs on Messages: the chat with the teacher (pupils) or with the pupils (teachers) or the class chats
    (administrator), and the school messages. */
function messages_tab(array $u):string{
 if(!function_exists('chat_ready')||!chat_ready())return 'school';if($u['role']==='admin')return 'school';   // v211 no class chats for administrators$t=(string)($_GET['tab']??'');if($t==='chat'||$t==='school')return $t;
 if(isset($_GET['id'])&&$u['role']!=='admin'||!empty($_GET['compose'])||!empty($_GET['preview']))return 'school';
 if($u['role']==='admin'&&isset($_GET['id'])&&$t==='')return 'school';
 return 'chat';
}
function messages_tabs(array $u,string $on):string{
 if(!function_exists('chat_ready')||!chat_ready())return '';$cn=chat_unread($u);$sn=messages_unread($u);
 $chat=['pupil'=>'My teacher','teacher'=>'My pupils','admin'=>'Class chats'][$u['role']]??'Chats';$school=$u['role']==='admin'?'Sent messages':'School';
 $a=fn($k,$l,$n,$ic)=>'<a class="bg-tab'.($on===$k?' on':'').'" href="?page=messages&amp;tab='.$k.'"'.($on===$k?' aria-current="page"':'').'>'.icon($ic).$l.($n?'<b>'.$n.'</b>':'').'</a>';
 return '<nav class="bg-tabs msg-tabs" aria-label="Messages">'.$a('chat',$chat,$cn,$u['role']==='admin'?'shield':'comic').$a('school',$school,$sn,'mega').'</nav>';
}
function messages_view(array $u):void{
 $tab=messages_tab($u);
 if($tab==='chat'||($u['role']!=='admin'&&function_exists('chat_ready')&&chat_ready()&&empty($_GET['compose'])&&empty($_GET['preview']))){chat_view($u);return;}   // v209 pupils and teachers: the School chat opens in the Messenger look too
 if(function_exists('chat_ready')&&chat_ready()&&$u['role']!=='admin')echo '<a class="btn secondary small mz-backall" href="?page=messages&amp;tab=chat">'.icon('arrow','flip').'All chats</a>';   // v209 the school messages open from the School row of the inbox
 if(!messages_ready()){messages_content($u);return;}
 $admin=$u['role']==='admin';$uid=(int)$u['id'];$id=(int)($_GET['id']??0);$selected=$id||!empty($_GET['compose'])||!empty($_GET['preview']);$search=mb_substr(trim((string)($_GET['search']??'')),0,100);$p=max(1,min(100000,(int)($_GET['listpage']??1)));
 $where=$admin?'m.sender_id=?':'r.user_id=?';$params=[$uid];if($search!==''){$where.=' AND (m.subject LIKE ? OR m.body LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
 $rr=replies_ready();
 $sql=$admin?'SELECT m.*,NULL read_at'.($rr?',(SELECT COUNT(*) FROM message_replies x WHERE x.message_id=m.id AND x.author_id=x.user_id AND x.read_at IS NULL) nr,(SELECT MAX(x.created_at) FROM message_replies x WHERE x.message_id=m.id) lastr':',0 nr,NULL lastr').' FROM admin_messages m':'SELECT m.*,r.read_at'.($rr?',(SELECT COUNT(*) FROM message_replies x WHERE x.message_id=m.id AND x.user_id=r.user_id AND (x.author_id IS NULL OR x.author_id<>x.user_id) AND x.read_at IS NULL) nr,(SELECT MAX(x.created_at) FROM message_replies x WHERE x.message_id=m.id AND x.user_id=r.user_id) lastr':',0 nr,NULL lastr').' FROM message_recipients r JOIN admin_messages m ON m.id=r.message_id';
 $list=rows($sql.' WHERE '.$where.' ORDER BY GREATEST(m.created_at,COALESCE(lastr,m.created_at)) DESC,m.id DESC LIMIT 25 OFFSET '.(($p-1)*25),$params);
 if($admin&&function_exists('chat_ready')&&chat_ready()){messages_sent_mz($u,$list,$id,$selected,$search,$p);return;}   // v211 Sent messages open in the same Messenger look as the chats
 echo '<div class="msg-shell'.($selected?' has-selection':'').'"><aside class="msg-list card" aria-label="Message list"><div class="msg-list-head"><div><span class="eyebrow">'.($admin?'SENT MESSAGES':'SCHOOL INBOX').'</span><h1>Messages</h1></div>'.($admin?'<a class="btn primary small" href="?page=messages&compose=1">New message</a>':'').'</div><form method="get" class="msg-search"><input type="hidden" name="page" value="messages"><label>Search messages<input name="search" maxlength="100" value="'.e($search).'" placeholder="Search subject or message"></label><button class="btn secondary small">Search</button></form><nav class="msg-items" aria-label="Messages">';
 foreach($list as $m){$active=(int)$m['id']===$id;$new=(!$admin&&$m['read_at']===null)||(int)$m['nr']>0;echo '<a class="msg-item'.($active?' active':'').($new?' unread':'').'" href="?page=messages&id='.(int)$m['id'].'"'.($active?' aria-current="page"':'').'><span class="msg-avatar" aria-hidden="true">'.icon('comic').'</span><span class="msg-summary"><span class="msg-item-top"><strong>'.e($m['subject']).'</strong>'.($new?'<span class="msg-dot" aria-label="Unread"></span>':'').'</span><small>'.e(mb_substr($m['body'],0,80)).'</small><time>'.e(date('M j, g:i A',strtotime((string)($m['lastr']?:$m['created_at'])))).'</time>'.((int)$m['nr']?'<span class="msg-nr">'.(int)$m['nr'].' new '.((int)$m['nr']===1?'reply':'replies').'</span>':'').'</span></a>';}
 if(!$list)echo '<p class="msg-empty">'.($search!==''?'No matching messages.':'No messages yet.').'</p>';
 echo '</nav><div class="buttonrow msg-pages">'.($p>1?'<a class="btn secondary small" href="?page=messages&listpage='.($p-1).'&search='.e(urlencode($search)).'">Previous</a>':'').(count($list)===25?'<a class="btn secondary small" href="?page=messages&listpage='.($p+1).'&search='.e(urlencode($search)).'">Next</a>':'').'</div></aside><section class="msg-panel card'.($admin?' msg-sent':' msg-received').'" aria-label="Selected message">';
 if($selected)messages_content($u);else echo '<div class="msg-welcome">'.icon('comic').'<h2>Your BULIG Quest messages</h2><p>'.($admin?'Choose a sent message to check its read status, or start a new message.':'Choose a message from your school administrator.').'</p><p>'.(replies_ready()?'Open a message to chat. Replies are private between you and '.($admin?'each person':'the administrator').'.':'Pupils and teachers can read messages.').'</p>'.($admin?'<a class="btn primary" href="?page=messages&compose=1">New message</a>':'').'</div>';
 echo '</section></div>';
}

/** v211 The administrator's Sent messages in the Messenger look: the list on the left, the message (read status
    and private replies) or a new message on the right. On a phone, one side shows at a time. */
function messages_sent_mz(array $u,array $list,int $id,bool $selected,string $search,int $p):void{
 echo '<div class="mz-shell mz-sentbox'.($selected?' has-sel':'').'"><aside class="mz-inbox card" aria-label="Sent messages"><div class="mz-top">'.chat_pic($u,'38').'<h1>Messages</h1></div><p class="mz-sec">SENT MESSAGES · CLASS CHATS ARE PRIVATE</p>'
  .'<form method="get" class="mz-search">'.icon('search').'<input type="hidden" name="page" value="messages"><input type="hidden" name="tab" value="school"><label class="sr-only" for="mz-sq">Search messages</label><input id="mz-sq" type="search" name="search" maxlength="100" value="'.e($search).'" placeholder="Search sent messages"></form>'
  .'<a class="btn primary mz-new" href="?page=messages&amp;tab=school&amp;compose=1">'.icon('edit').'New message</a><nav class="mz-list" aria-label="Sent messages">';
 foreach($list as $m){$on=(int)$m['id']===$id;$nr=(int)$m['nr'];
  echo '<a class="mz-row'.($on?' on':'').($nr?' new':'').'" href="?page=messages&amp;tab=school&amp;id='.(int)$m['id'].'"'.($on?' aria-current="page"':'').'><span class="mz-av mz-56 mz-ic mz-ic-b">'.icon('mega').'</span><span class="mz-t"><b><span class="mz-n">'.e($m['subject']).'</span></b><small>'.e(mb_substr((string)$m['body'],0,70)).'</small></span><span class="mz-time">'.e(date('M j',strtotime((string)($m['lastr']?:$m['created_at'])))).($nr?'<span class="mz-badge">'.$nr.'</span>':'').'</span></a>';}
 if(!$list)echo '<p class="mz-empty">'.($search!==''?'No matching messages.':'No sent messages yet.').'</p>';
 echo '</nav>';
 if($p>1||count($list)===25)echo '<div class="mz-pages">'.($p>1?'<a class="btn secondary small" href="?page=messages&amp;tab=school&amp;listpage='.($p-1).'&amp;search='.e(urlencode($search)).'">Previous</a>':'').(count($list)===25?'<a class="btn secondary small" href="?page=messages&amp;tab=school&amp;listpage='.($p+1).'&amp;search='.e(urlencode($search)).'">Next</a>':'').'</div>';
 echo '</aside><section class="mz-pane card msg-panel msg-sent" aria-label="Selected message">';
 if($selected)messages_content($u);
 else echo '<div class="mz-hello">'.icon('mega').'<h2>Sent messages</h2><p>Choose a message to see who read it and to reply. Replies are private between you and each person.</p><a class="btn primary" href="?page=messages&amp;tab=school&amp;compose=1">New message</a></div>';
 echo '</section></div>';
}

/* ---------- v209 photos and voice messages in replies (database/migrations/038_message_media.sql) ----------
   One photo or one voice message per reply. Photos are made smaller and saved again as JPEG (this also removes the
   hidden location data a phone puts in a photo). Files are private: only the two people in the chat can open them,
   through ?page=msg_media. They are deleted after 60 days (privacy_purge), like reading recordings. */
const MEDIA_KEEP_DAYS=60;
function media_ready():bool{static $ok;if($ok===null){try{$ok=replies_ready()&&(bool)one("SHOW COLUMNS FROM message_replies LIKE 'media_kind'");}catch(Throwable $e){$ok=false;}}return $ok;}
function media_dir():string{return __DIR__.'/../storage/messages';}
/** Save the uploaded photo or voice message for the chat with $with: [kind, path, seconds]. */
function media_save(int $with):array{
 $dir=media_dir().'/'.$with;if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))fail('Photos and voice messages cannot be saved right now. Please try again later.',500);
 $name=date('YmdHis').'-'.bin2hex(random_bytes(6));
 if(!empty($_FILES['photo']['tmp_name'])){$f=$_FILES['photo'];
  if(($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))fail('The photo could not be sent. Please try again.');
  if($f['size']>8*1024*1024)fail('This photo is too big. Please choose a smaller one (up to 8 MB).');
  $info=@getimagesize($f['tmp_name']);if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP,IMAGETYPE_GIF],true))fail('Please send a photo (JPEG, PNG or WebP).');
  $path=$with.'/'.$name.'.jpg';
  if(function_exists('imagecreatefromstring')&&($im=@imagecreatefromstring((string)file_get_contents($f['tmp_name'])))){
   $w=imagesx($im);$h=imagesy($im);$k=min(1,1600/max($w,$h));if($k<1){$n=imagecreatetruecolor((int)round($w*$k),(int)round($h*$k));imagefill($n,0,0,imagecolorallocate($n,255,255,255));imagecopyresampled($n,$im,0,0,0,0,imagesx($n),imagesy($n),$w,$h);imagedestroy($im);$im=$n;}
   else{$n=imagecreatetruecolor($w,$h);imagefill($n,0,0,imagecolorallocate($n,255,255,255));imagecopy($n,$im,0,0,0,0,$w,$h);imagedestroy($im);$im=$n;}
   $ok=imagejpeg($im,media_dir().'/'.$path,85);imagedestroy($im);if(!$ok)fail('The photo could not be saved. Please try again.',500);
  }elseif($info[2]===IMAGETYPE_JPEG){if(!@move_uploaded_file($f['tmp_name'],media_dir().'/'.$path))fail('The photo could not be saved. Please try again.',500);}
  else fail('Please send a JPEG photo.');
  return ['photo',$path,null];}
 $f=$_FILES['voice'];
 if(($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))fail('The voice message could not be sent. Please try again.');
 if($f['size']>6*1024*1024)fail('This voice message is too long. Please keep it under 2 minutes.');
 $head=(string)file_get_contents($f['tmp_name'],false,null,0,16);
 if(str_starts_with($head,"\x1A\x45\xDF\xA3"))$ext='webm';elseif(str_starts_with($head,'OggS'))$ext='ogg';elseif(substr($head,4,4)==='ftyp')$ext='m4a';elseif(str_starts_with($head,'ID3')||(strlen($head)>1&&ord($head[0])===0xFF&&(ord($head[1])&0xE0)===0xE0))$ext='mp3';else fail('This voice message could not be read. Please record again.');
 $path=$with.'/'.$name.'.'.$ext;if(!@move_uploaded_file($f['tmp_name'],media_dir().'/'.$path))fail('The voice message could not be saved. Please try again.',500);
 return ['voice',$path,max(1,min(600,(int)($_POST['voice_secs']??0)))];
}
/** The photo or voice message inside a chat bubble. */
function media_bubble(array $r,string $src=''):string{
 if(empty($r['media_kind']))return '';$src=$src!==''?$src:'?page=msg_media&amp;id='.(int)$r['id'];$gone=!is_file(media_dir().'/'.(string)$r['media_path']);
 if($r['media_kind']==='photo')return $gone?'<p class="mc-gone">'.icon('image').'This photo was deleted after '.MEDIA_KEEP_DAYS.' days.</p>':'<a class="mc-photo" href="'.$src.'" target="_blank" rel="noopener"><img src="'.$src.'" alt="Photo" loading="lazy"></a>';
 $s=(int)$r['media_secs'];$t=floor($s/60).':'.str_pad((string)($s%60),2,'0',STR_PAD_LEFT);
 if($gone)return '<p class="mc-gone">'.icon('mic').'This voice message was deleted after '.MEDIA_KEEP_DAYS.' days.</p>';
 $bars='';for($k=0;$k<24;$k++)$bars.='<i class="mc-b'.(int)(abs(sin($k*1.7+(int)$r['id'])*cos($k*.6))*4).'"></i>';
 return '<div class="mc-voice" data-mc-voice><button type="button" class="mc-play" aria-label="Play the voice message">'.icon('play','mc-pl').icon('pause','mc-pa').'</button><span class="mc-wave">'.$bars.'</span><span class="mc-dur" data-mc-dur>'.$t.'</span><audio preload="none" src="'.$src.'"></audio></div>';
}
/** Open a photo or voice message: only the person in the chat and the administrator who sent the message. */
function media_open(?array $u):never{
 if(!$u||!media_ready())fail('Not found.',404);$r=one('SELECT x.*,m.sender_id FROM message_replies x JOIN admin_messages m ON m.id=x.message_id WHERE x.id=?',[(int)($_GET['id']??0)]);
 if(!$r||empty($r['media_path'])||((int)$r['user_id']!==(int)$u['id']&&!($u['role']==='admin'&&(int)$r['sender_id']===(int)$u['id'])))fail('Not found.',404);
 $root=realpath(media_dir());$f=realpath(media_dir().'/'.$r['media_path']);if(!$root||!$f||!str_starts_with($f,$root.DIRECTORY_SEPARATOR)||!is_file($f))fail('This file was deleted after '.MEDIA_KEEP_DAYS.' days.',404);
 $type=['jpg'=>'image/jpeg','webm'=>'audio/webm','ogg'=>'audio/ogg','m4a'=>'audio/mp4','mp3'=>'audio/mpeg'][strtolower(pathinfo($f,PATHINFO_EXTENSION))]??'application/octet-stream';
 header('Content-Type: '.$type);header('Content-Length: '.filesize($f));header('Cache-Control: private, max-age=86400');header('X-Content-Type-Options: nosniff');readfile($f);exit;
}

/* ---------- v203 replies: a private chat for each person under each message (Messenger style) ---------- */
function replies_ready():bool{static $ok;if($ok===null){try{$ok=messages_ready()&&(bool)one("SHOW TABLES LIKE 'message_replies'");}catch(Throwable $e){$ok=false;}}return $ok;}
/** Unread items for the menu: new messages and administrator replies (pupils and teachers) or new replies (administrators). */
function messages_unread(array $u):int{
 if(!messages_ready())return 0;$uid=(int)$u['id'];
 try{if($u['role']==='admin')return replies_ready()?(int)val('SELECT COUNT(*) FROM message_replies x JOIN admin_messages m ON m.id=x.message_id WHERE m.sender_id=? AND x.author_id=x.user_id AND x.read_at IS NULL',[$uid]):0;
  return (int)val('SELECT COUNT(*) FROM message_recipients WHERE user_id=? AND read_at IS NULL',[$uid])+(replies_ready()?(int)val('SELECT COUNT(*) FROM message_replies WHERE user_id=? AND (author_id IS NULL OR author_id<>user_id) AND read_at IS NULL',[$uid]):0);}catch(Throwable $e){return 0;}
}
function message_reply_action():never{
 $u=current_user();if(!$u)fail('Please sign in again.',403);$admin=$u['role']==='admin';if($admin&&!empty($_SESSION['pin_pending']))fail('Verify your admin PIN first.',403);
 if(!replies_ready())fail('Replies are not set up yet. Please tell your school administrator.');
 $mid=(int)($_POST['message_id']??0);$body=trim(str_replace(["\r\n","\r"],"\n",(string)($_POST['body']??'')));$ajax=!empty($_POST['ajax']);
 $hasMedia=media_ready()&&(!empty($_FILES['photo']['tmp_name'])||!empty($_FILES['voice']['tmp_name']));   // v209 a photo or a voice message
 if(($body===''&&!$hasMedia)||mb_strlen($body)>2000)fail('Write a reply (up to 2,000 characters).');
 $m=one('SELECT * FROM admin_messages WHERE id=?',[$mid]);if(!$m)fail('Message not found.',404);
 if($admin){if((int)$m['sender_id']!==(int)$u['id'])fail('Message not found.',404);$with=(int)($_POST['with']??0);}else $with=(int)$u['id'];
 if(!one('SELECT 1 FROM message_recipients WHERE message_id=? AND user_id=?',[$mid,$with]))fail('Message not found.',404);
 db()->beginTransaction();try{
  q('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);
  /* a double tap or a resent form never posts the same reply twice; at most 30 replies an hour */
  if(!$hasMedia&&one('SELECT 1 FROM message_replies WHERE message_id=? AND user_id=? AND author_id=? AND body=? AND created_at>=NOW()-INTERVAL 30 SECOND',[$mid,$with,$u['id'],$body])){db()->commit();if($ajax){header('Content-Type: application/json');echo '{"ok":true}';exit;}go('?page=messages&id='.$mid.($admin?'&with='.$with:'').'#msg-end');}
  if((int)val('SELECT COUNT(*) FROM message_replies WHERE author_id=? AND created_at>=NOW()-INTERVAL 1 HOUR',[$u['id']])>=30)fail('You sent many replies in the last hour. Please wait a little.');
  $media=$hasMedia?media_save($with):null;
  if($media)q('INSERT INTO message_replies(message_id,user_id,author_id,body,media_kind,media_path,media_secs) VALUES(?,?,?,?,?,?,?)',[$mid,$with,$u['id'],$body,$media[0],$media[1],$media[2]]);
  else q('INSERT INTO message_replies(message_id,user_id,author_id,body) VALUES(?,?,?,?)',[$mid,$with,$u['id'],$body]);
  $say=$body!==''?$body:($media&&$media[0]==='photo'?'Sent a photo':'Sent a voice message');
  if($admin)notify($with,'note','Reply: '.mb_substr((string)$m['subject'],0,150),'Your school administrator replied.','?page=messages&id='.$mid.'#msg-end');
  elseif($m['sender_id'])notify((int)$m['sender_id'],'note','Reply from '.$u['name'],mb_substr($say,0,200),'?page=messages&id='.$mid.'&with='.$with.'#msg-end');
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!empty($media[1]))@unlink(media_dir().'/'.$media[1]);throw $e;}
 if($ajax){header('Content-Type: application/json');echo '{"ok":true}';exit;}
 go('?page=messages&id='.$mid.($admin?'&with='.$with:'').'#msg-end');
}
/** One chat: the message first, then the replies, then the box to write a reply. */
function message_chat(array $m,int $with,bool $admin,array $person):string{
 $uid=(int)current_user()['id'];$mid=(int)$m['id'];
 $rows=replies_ready()?rows('SELECT * FROM message_replies WHERE message_id=? AND user_id=? ORDER BY id',[$mid,$with]):[];
 /* what the other side wrote is now read */
 if(replies_ready())q($admin?'UPDATE message_replies SET read_at=NOW() WHERE message_id=? AND user_id=? AND author_id=user_id AND read_at IS NULL':'UPDATE message_replies SET read_at=NOW() WHERE message_id=? AND user_id=? AND (author_id IS NULL OR author_id<>user_id) AND read_at IS NULL',[$mid,$with]);
 $when=fn($t)=>'<time datetime="'.e(date('c',strtotime((string)$t))).'">'.e(date('M j, g:i A',strtotime((string)$t))).'</time>';
 $name=$admin?(string)$person['name']:'School administrator';$ini=mb_strtoupper(mb_substr(trim($name),0,1));
 $h='<header class="mc-head">'.($admin?'<a class="mc-back" href="?page=messages&amp;tab=school&amp;id='.$mid.'" aria-label="Back to this message">'.icon('arrow').'</a>':'<a class="mc-back" href="?page=messages&amp;tab=school" aria-label="Back to sent messages">'.icon('arrow').'</a>').'<span class="mc-av" aria-hidden="true">'.($admin?e($ini):icon('comic')).'</span><div><b>'.e($name).'</b><small>'.e($admin?($person['public_id'].' · '.$person['role']):$m['subject']).'</small></div></header>';
 $h.='<div class="mc-log" role="log" aria-label="Conversation">';
 $h.='<div class="mc-day">'.e(date('F j, Y',strtotime((string)$m['created_at']))).'</div>';
 $h.='<div class="mc-row '.($admin?'me':'them').'"><div class="mc-bub mc-first"><b>'.e($m['subject']).'</b><p>'.nl2br(e($m['body'])).'</p>'.$when($m['created_at']).'</div></div>';
 foreach($rows as $r){$mine=(int)$r['author_id']===$uid||($admin&&(int)$r['author_id']!==(int)$r['user_id']);
  $h.='<div class="mc-row '.($mine?'me':'them').'"><div class="mc-bub'.(!empty($r['media_kind'])?' mc-media':'').'">'.media_bubble($r).((string)$r['body']!==''?'<p>'.nl2br(e($r['body'])).'</p>':'').$when($r['created_at']).($mine&&$r['read_at']?'<span class="mc-seen">Seen</span>':'').'</div></div>';}
 $h.='<span id="msg-end"></span></div>';
 if(replies_ready())$h.='<form method="post" class="mc-send">'.csrf_field().'<input type="hidden" name="action" value="message_reply"><input type="hidden" name="message_id" value="'.$mid.'">'.($admin?'<input type="hidden" name="with" value="'.$with.'">':'').''.(media_ready()?'<div class="mc-chip" data-mc-chip hidden><img alt="" data-mc-thumb><small data-mc-what>1 photo ready to send</small><button type="button" class="mc-ic" data-mc-clear aria-label="Remove">'.icon('close').'</button></div><button type="button" class="mc-ic" data-mc-photo aria-label="Send a photo">'.icon('image').'</button><input type="file" accept="image/*" data-mc-file hidden><button type="button" class="mc-ic" data-mc-mic aria-label="Record a voice message">'.icon('mic').'</button><div class="mc-rec" data-mc-rec hidden><span class="mc-dot"></span><b data-mc-time>0:00</b><span class="mc-recl">Recording…</span><button type="button" class="mc-ic" data-mc-cancel aria-label="Cancel recording">'.icon('trash').'</button></div>':'').'<label class="sr-only" for="mc-body">Write a reply</label><textarea id="mc-body" name="body" rows="1" maxlength="2000" required placeholder="Write a reply…"></textarea><button class="mc-go" aria-label="Send">'.icon('arrow').'</button><p class="mc-err" role="status" data-mc-err></p></form>';
 else $h.='<p class="mc-off">Replies are not set up yet.</p>';
 return '<div class="mc">'.$h.'</div>';
}

