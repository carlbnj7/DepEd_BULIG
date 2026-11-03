<?php
/* v209 Chat between a teacher and their pupils (database/migrations/039_teacher_chat.sql).
   - A pupil chats only with their own teachers (teacher_pupils); a teacher only with their own pupils.
   - Text, one photo or one voice message per message (the same as Messages: app/messages.php media_*).
   - A teacher can send the same message to the whole class or one section: each pupil gets it in their own chat.
   - The administrator can read every chat (read only), to keep children safe.
   - The other person gets a bell notification; the menu shows how many messages are new. */

const CHAT_MAX=1000;

function chat_ready():bool{static $ok;if($ok===null){try{$ok=(bool)one("SHOW TABLES LIKE 'chat_messages'");}catch(Throwable $e){$ok=false;}}return $ok;}
function chat_allowed(int $teacher,int $pupil):bool{return (bool)val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$teacher,$pupil]);}
/** The chat of a teacher and a pupil (made when the first message is sent). */
function chat_id(int $teacher,int $pupil,bool $make=false):int{
 $id=(int)val('SELECT id FROM chats WHERE teacher_id=? AND pupil_id=?',[$teacher,$pupil]);
 if(!$id&&$make){q('INSERT IGNORE INTO chats(teacher_id,pupil_id) VALUES(?,?)',[$teacher,$pupil]);$id=(int)val('SELECT id FROM chats WHERE teacher_id=? AND pupil_id=?',[$teacher,$pupil]);}
 return $id;
}
/** New messages for the menu: what the other person wrote that this person has not seen yet. */
function chat_unread(array $u):int{
 if(!chat_ready()||!in_array($u['role'],['teacher','pupil'],true))return 0;$col=$u['role']==='teacher'?'teacher_id':'pupil_id';
 try{return (int)val('SELECT COUNT(*) FROM chat_messages m JOIN chats c ON c.id=m.chat_id WHERE c.'.$col.'=? AND m.read_at IS NULL AND (m.author_id IS NULL OR m.author_id<>?)',[(int)$u['id'],(int)$u['id']]);}catch(Throwable $e){return 0;}
}
/** A pupil's "Chat with my teacher" card on My profile (pupils have no menu on a phone). */
function chat_link_card(array $u):string{$n=chat_unread($u);
 return '<a class="card ch-link" href="?page=chat#msg-end"><span class="ch-link-ic">'.icon('comic').'</span><span><b>Chat with my teacher</b><small>'.($n?$n.' new '.($n===1?'message':'messages').' from your teacher':'Send a message, a photo or a voice message').'</small></span>'.($n?'<span class="msg-nr">'.$n.'</span>':'').icon('chev').'</a>';}
function chat_name(string $n):string{return trim(explode(' ',trim($n))[0]??$n);}

function chat_view(array $u):void{
 if(!chat_ready()){echo '<section class="card"><h1>Chat is being set up</h1><p>Please tell your school administrator to import database/migrations/039_teacher_chat.sql.</p></section>';return;}
 $role=$u['role'];$uid=(int)$u['id'];
 if($role==='admin'){chat_admin_view($u);return;}
 $with=(int)($_GET['with']??0);$teacher=$role==='teacher';
 /* the people this person can chat with, newest chat first */
 if($teacher)$people=rows('SELECT u.id,u.name,u.public_id,u.avatar_path,c.id chat,c.last_at,(SELECT COUNT(*) FROM chat_messages m WHERE m.chat_id=c.id AND m.read_at IS NULL AND (m.author_id IS NULL OR m.author_id<>?)) nr,(SELECT m.body FROM chat_messages m WHERE m.chat_id=c.id ORDER BY m.id DESC LIMIT 1) lastb,(SELECT m.media_kind FROM chat_messages m WHERE m.chat_id=c.id ORDER BY m.id DESC LIMIT 1) lastk FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 LEFT JOIN chats c ON c.teacher_id=t.teacher_id AND c.pupil_id=u.id WHERE t.teacher_id=? ORDER BY c.last_at IS NULL,c.last_at DESC,u.name',[$uid,$uid]);
 else $people=rows('SELECT u.id,u.name,u.public_id,u.avatar_path,c.id chat,c.last_at,(SELECT COUNT(*) FROM chat_messages m WHERE m.chat_id=c.id AND m.read_at IS NULL AND (m.author_id IS NULL OR m.author_id<>?)) nr,(SELECT m.body FROM chat_messages m WHERE m.chat_id=c.id ORDER BY m.id DESC LIMIT 1) lastb,(SELECT m.media_kind FROM chat_messages m WHERE m.chat_id=c.id ORDER BY m.id DESC LIMIT 1) lastk FROM teacher_pupils t JOIN users u ON u.id=t.teacher_id AND u.active=1 LEFT JOIN chats c ON c.teacher_id=u.id AND c.pupil_id=t.pupil_id WHERE t.pupil_id=? ORDER BY c.last_at IS NULL,c.last_at DESC,u.name',[$uid,$uid]);
 if(!$teacher&&!$with&&count($people)===1)$with=(int)$people[0]['id'];   // a pupil with one teacher goes straight to the chat
 $sel=null;foreach($people as $p)if((int)$p['id']===$with)$sel=$p;if($with&&!$sel)fail('You can chat only with '.($teacher?'your own pupils':'your teacher').'.',404);
 $class=$teacher&&($_GET['class']??'')==='1';$q=mb_strtolower(trim((string)($_GET['q']??'')));
 echo '<div class="msg-shell ch-shell'.($sel||$class?' has-selection':'').'"><aside class="msg-list card" aria-label="Chats"><div class="msg-list-head"><div><span class="eyebrow">'.($teacher?'MY PUPILS':'MY TEACHER').'</span><h1>'.($teacher?'Chat with pupils':'Chat with my teacher').'</h1></div>'.($teacher?'<a class="btn primary small" href="?page=chat&amp;class=1">'.icon('people').'Message the class</a>':'').'</div>';
 if($teacher)echo '<form method="get" class="msg-search"><input type="hidden" name="page" value="chat"><label>Find a pupil<input name="q" maxlength="60" value="'.e($q).'" placeholder="Type a name"></label><button class="btn secondary small">Find</button></form>';
 echo '<nav class="msg-items" aria-label="'.($teacher?'Pupils':'Teachers').'">';$shown=0;
 foreach($people as $p){if($q!==''&&!str_contains(mb_strtolower((string)$p['name']),$q))continue;$shown++;$on=$sel&&(int)$p['id']===(int)$sel['id'];$nr=(int)$p['nr'];
  $last=$p['lastk']==='photo'?'Photo':($p['lastk']==='voice'?'Voice message':mb_substr((string)$p['lastb'],0,70));
  echo '<a class="msg-item'.($on?' active':'').($nr?' unread':'').'" href="?page=chat&amp;with='.(int)$p['id'].'#msg-end"'.($on?' aria-current="page"':'').'><span class="msg-avatar" aria-hidden="true">'.e(mb_strtoupper(mb_substr(trim((string)$p['name']),0,1))).'</span><span class="msg-summary"><span class="msg-item-top"><strong>'.e($p['name']).'</strong>'.($nr?'<span class="msg-nr">'.$nr.' new</span>':'').'</span><small>'.($p['chat']&&$last!==''?e($last):($teacher?'No messages yet':'Say hello to your teacher!')).'</small>'.($p['last_at']&&$p['chat']?'<time>'.e(date('M j, g:i A',strtotime((string)$p['last_at']))).'</time>':'').'</span></a>';}
 if(!$shown)echo '<p class="msg-empty">'.($q!==''?'No pupil with that name.':($teacher?'Add pupils to your class first.':'You do not have a teacher in BULIG yet.')).'</p>';
 echo '</nav></aside><section class="msg-panel card" aria-label="Chat">';
 if($class)echo chat_class_form($u);
 elseif($sel)echo chat_box($u,$sel);
 else echo '<div class="msg-welcome">'.icon('comic').'<h2>'.($teacher?'Chat with your pupils':'Chat with your teacher').'</h2><p>'.($teacher?'Choose a pupil to chat, or send one message to the whole class.':'Choose your teacher to start.').'</p><p>Send words, a photo or a voice message. Only the two of you and your school administrator can see the chat.</p></div>';
 echo '</section></div>';
}

/** One chat with the person $o (a pupil for a teacher, a teacher for a pupil). */
function chat_box(array $u,array $o):string{
 $teacher=$u['role']==='teacher';$uid=(int)$u['id'];$cid=$teacher?chat_id($uid,(int)$o['id']):chat_id((int)$o['id'],$uid);
 $rows=$cid?rows('SELECT * FROM chat_messages WHERE chat_id=? ORDER BY id DESC LIMIT 200',[$cid]):[];$rows=array_reverse($rows);
 if($cid)q('UPDATE chat_messages SET read_at=NOW() WHERE chat_id=? AND read_at IS NULL AND (author_id IS NULL OR author_id<>?)',[$cid,$uid]);
 $h='<div class="mc"><header class="mc-head"><a class="mc-back" href="?page=chat" aria-label="Back to chats">'.icon('arrow').'</a><span class="mc-av" aria-hidden="true">'.e(mb_strtoupper(mb_substr(trim((string)$o['name']),0,1))).'</span><div><b>'.e($o['name']).'</b><small>'.e($teacher?$o['public_id'].' · Pupil':'Your teacher').'</small></div></header><div class="mc-log" role="log" aria-label="Conversation">';
 if(!$rows)$h.='<div class="mc-day">'.($teacher?'Say hello to '.e(chat_name((string)$o['name'])).'!':'Say hello to your teacher!').'</div>';
 $day='';foreach($rows as $r){$d=date('F j, Y',strtotime((string)$r['created_at']));if($d!==$day){$day=$d;$h.='<div class="mc-day">'.e($d).'</div>';}
  $mine=(int)$r['author_id']===$uid;
  $h.='<div class="mc-row '.($mine?'me':'them').'"><div class="mc-bub'.(!empty($r['media_kind'])?' mc-media':'').'">'.media_bubble($r,'?page=chat_media&amp;id='.(int)$r['id']).((string)$r['body']!==''?'<p>'.nl2br(e($r['body'])).'</p>':'').'<time datetime="'.e(date('c',strtotime((string)$r['created_at']))).'">'.e(date('g:i A',strtotime((string)$r['created_at']))).'</time>'.($mine&&$r['read_at']?'<span class="mc-seen">Seen</span>':'').'</div></div>';}
 $h.='<span id="msg-end"></span></div>';
 $m=function_exists('media_ready')&&media_ready();
 $h.='<form method="post" class="mc-send">'.csrf_field().'<input type="hidden" name="action" value="chat_send"><input type="hidden" name="with" value="'.(int)$o['id'].'">'
  .($m?'<div class="mc-chip" data-mc-chip hidden><img alt="" data-mc-thumb><small data-mc-what>1 photo ready to send</small><button type="button" class="mc-ic" data-mc-clear aria-label="Remove">'.icon('close').'</button></div><button type="button" class="mc-ic" data-mc-photo aria-label="Send a photo">'.icon('image').'</button><input type="file" accept="image/*" data-mc-file hidden><button type="button" class="mc-ic" data-mc-mic aria-label="Record a voice message">'.icon('mic').'</button><div class="mc-rec" data-mc-rec hidden><span class="mc-dot"></span><b data-mc-time>0:00</b><span class="mc-recl">Recording…</span><button type="button" class="mc-ic" data-mc-cancel aria-label="Cancel recording">'.icon('trash').'</button></div>':'')
  .'<label class="sr-only" for="mc-body">Write a message</label><textarea id="mc-body" name="body" rows="1" maxlength="'.CHAT_MAX.'" required placeholder="Message…"></textarea><button class="mc-go" aria-label="Send">'.icon('arrow').'</button><p class="mc-err" role="status" data-mc-err></p></form>';
 return $h.'</div>';
}

/** A teacher sends one message to every pupil, or to one section. */
function chat_class_form(array $u):string{
 $secs=rows('SELECT s.id,s.name,s.grade_level,(SELECT COUNT(*) FROM pupil_sections ps JOIN teacher_pupils t ON t.pupil_id=ps.pupil_id AND t.teacher_id=s.teacher_id WHERE ps.section_id=s.id) n FROM sections s WHERE s.teacher_id=?'.(function_exists('sections_archive_supported')&&sections_archive_supported()?' AND s.archived_at IS NULL':'').' ORDER BY s.grade_level,s.name',[(int)$u['id']]);
 $all=(int)val('SELECT COUNT(*) FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 WHERE t.teacher_id=?',[(int)$u['id']]);
 $h='<div class="ch-class"><a class="btn secondary small" href="?page=chat">'.icon('arrow','flip').'Back to chats</a><span class="eyebrow">ONE MESSAGE FOR MANY</span><h2>'.icon('people').'Message the class</h2><p class="muted">Each pupil gets this message in their own chat with you, and can reply only to you.</p><form method="post" class="stack">'.csrf_field().'<input type="hidden" name="action" value="chat_class"><label>Send to<select name="section"><option value="0">All my pupils ('.$all.')</option>';
 foreach($secs as $s)$h.='<option value="'.(int)$s['id'].'">Grade '.e($s['grade_level']).' · '.e($s['name']).' ('.(int)$s['n'].')</option>';
 return $h.'</select></label><label>Message<textarea name="body" rows="4" maxlength="'.CHAT_MAX.'" required placeholder="Example: Good morning, class! Please finish Lesson 3 today."></textarea></label><button class="btn primary" data-confirm="Send this message to every pupil you chose?">'.icon('arrow').'Send to the class</button></form></div>';
}

function chat_action(string $action):never{
 $u=current_user();if(!$u||!in_array($u['role'],['teacher','pupil'],true))fail('Please sign in again.',403);if(!chat_ready())fail('Chat is not set up yet. Please tell your school administrator.');
 $uid=(int)$u['id'];$body=trim(str_replace(["\r\n","\r"],"\n",(string)($_POST['body']??'')));$ajax=!empty($_POST['ajax']);
 if(mb_strlen($body)>CHAT_MAX)fail('Please keep a message under '.number_format(CHAT_MAX).' characters.');
 if((int)val('SELECT COUNT(*) FROM chat_messages WHERE author_id=? AND created_at>=NOW()-INTERVAL 1 HOUR',[$uid])>=($u['role']==='teacher'?300:40))fail('You sent many messages in the last hour. Please wait a little.');
 if($action==='chat_class'){if($u['role']!=='teacher')fail('Only teachers can message the class.',403);if($body==='')fail('Write a message first.');
  $sec=(int)($_POST['section']??0);
  $ids=array_map('intval',array_column($sec?rows('SELECT t.pupil_id FROM teacher_pupils t JOIN pupil_sections ps ON ps.pupil_id=t.pupil_id JOIN sections s ON s.id=ps.section_id AND s.teacher_id=t.teacher_id JOIN users u ON u.id=t.pupil_id AND u.active=1 WHERE t.teacher_id=? AND s.id=?',[$uid,$sec]):rows('SELECT t.pupil_id FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 WHERE t.teacher_id=?',[$uid]),'pupil_id'));
  if(!$ids)fail('There are no pupils to send to.');
  db()->beginTransaction();try{foreach($ids as $p){$c=chat_id($uid,$p,true);q('INSERT INTO chat_messages(chat_id,author_id,body) VALUES(?,?,?)',[$c,$uid,$body]);q('UPDATE chats SET last_at=NOW() WHERE id=?',[$c]);notify($p,'chat','New message from '.$u['name'],mb_substr($body,0,200),'?page=chat&with='.$uid.'#msg-end');}
   audit('chat_class',count($ids).' pupils');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Sent to '.count($ids).' '.(count($ids)===1?'pupil':'pupils').'. Each one can reply in their own chat.');go('?page=chat');}
 /* one message in one chat */
 $with=(int)($_POST['with']??0);[$t,$p]=$u['role']==='teacher'?[$uid,$with]:[$with,$uid];
 if(!$with||!chat_allowed($t,$p))fail('You can chat only with '.($u['role']==='teacher'?'your own pupils':'your teacher').'.',404);
 $hasMedia=function_exists('media_ready')&&media_ready()&&(!empty($_FILES['photo']['tmp_name'])||!empty($_FILES['voice']['tmp_name']));
 if($body===''&&!$hasMedia)fail('Write a message first.');
 $media=null;db()->beginTransaction();try{
  $c=chat_id($t,$p,true);
  if(!$hasMedia&&one('SELECT 1 FROM chat_messages WHERE chat_id=? AND author_id=? AND body=? AND created_at>=NOW()-INTERVAL 30 SECOND',[$c,$uid,$body])){db()->commit();if($ajax){header('Content-Type: application/json');echo '{"ok":true}';exit;}go('?page=chat&with='.$with.'#msg-end');}   // a double tap never sends twice
  $media=$hasMedia?media_save($p):null;
  q('INSERT INTO chat_messages(chat_id,author_id,body,media_kind,media_path,media_secs) VALUES(?,?,?,?,?,?)',[$c,$uid,$body,$media[0]??null,$media[1]??null,$media[2]??null]);q('UPDATE chats SET last_at=NOW() WHERE id=?',[$c]);
  $say=$body!==''?$body:(($media[0]??'')==='photo'?'Sent a photo':'Sent a voice message');
  notify($with,'chat',($u['role']==='teacher'?'New message from ':'Message from ').$u['name'],mb_substr($say,0,200),'?page=chat&with='.$uid.'#msg-end');
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!empty($media[1]))@unlink(media_dir().'/'.$media[1]);throw $e;}
 if($ajax){header('Content-Type: application/json');echo '{"ok":true}';exit;}
 go('?page=chat&with='.$with.'#msg-end');
}

/** A photo or voice message in a chat: the teacher and the pupil of that chat, and the administrator. */
function chat_media(?array $u):never{
 if(!$u||!chat_ready())fail('Not found.',404);$r=one('SELECT m.*,c.teacher_id,c.pupil_id FROM chat_messages m JOIN chats c ON c.id=m.chat_id WHERE m.id=?',[(int)($_GET['id']??0)]);
 if(!$r||empty($r['media_path'])||!in_array((int)$u['id'],[(int)$r['teacher_id'],(int)$r['pupil_id']],true)&&$u['role']!=='admin')fail('Not found.',404);
 $root=realpath(media_dir());$f=realpath(media_dir().'/'.$r['media_path']);if(!$root||!$f||!str_starts_with($f,$root.DIRECTORY_SEPARATOR)||!is_file($f))fail('This file was deleted after '.MEDIA_KEEP_DAYS.' days.',404);
 $type=['jpg'=>'image/jpeg','webm'=>'audio/webm','ogg'=>'audio/ogg','m4a'=>'audio/mp4','mp3'=>'audio/mpeg'][strtolower(pathinfo($f,PATHINFO_EXTENSION))]??'application/octet-stream';
 header('Content-Type: '.$type);header('Content-Length: '.filesize($f));header('Cache-Control: private, max-age=86400');header('X-Content-Type-Options: nosniff');readfile($f);exit;
}

/** The administrator reads the chats (read only), newest first. */
function chat_admin_view(array $u):void{
 $id=(int)($_GET['id']??0);$sch=function_exists('school_sql')?'':'';
 $list=rows('SELECT c.id,c.last_at,t.name tname,p.name pname,p.public_id ppid,(SELECT COUNT(*) FROM chat_messages m WHERE m.chat_id=c.id) n FROM chats c JOIN users t ON t.id=c.teacher_id JOIN users p ON p.id=c.pupil_id ORDER BY c.last_at DESC LIMIT 100');
 echo '<div class="msg-shell ch-shell'.($id?' has-selection':'').'"><aside class="msg-list card" aria-label="Chats"><div class="msg-list-head"><div><span class="eyebrow">KEEPING CHILDREN SAFE</span><h1>Class chats</h1></div></div><p class="muted ch-note">'.icon('shield').'Chats between teachers and their pupils. You can read them; only the teacher and the pupil write.</p><nav class="msg-items" aria-label="Chats">';
 foreach($list as $c)echo '<a class="msg-item'.((int)$c['id']===$id?' active':'').'" href="?page=chat&amp;id='.(int)$c['id'].'#msg-end"><span class="msg-avatar" aria-hidden="true">'.e(mb_strtoupper(mb_substr(trim((string)$c['pname']),0,1))).'</span><span class="msg-summary"><span class="msg-item-top"><strong>'.e($c['pname']).'</strong></span><small>with '.e($c['tname']).' · '.(int)$c['n'].' '.((int)$c['n']===1?'message':'messages').'</small><time>'.e(date('M j, g:i A',strtotime((string)$c['last_at']))).'</time></span></a>';
 if(!$list)echo '<p class="msg-empty">No chats yet.</p>';
 echo '</nav></aside><section class="msg-panel card" aria-label="Chat">';
 $c=$id?one('SELECT c.*,t.name tname,p.name pname FROM chats c JOIN users t ON t.id=c.teacher_id JOIN users p ON p.id=c.pupil_id WHERE c.id=?',[$id]):null;
 if($c){$rows=array_reverse(rows('SELECT * FROM chat_messages WHERE chat_id=? ORDER BY id DESC LIMIT 300',[$id]));
  echo '<div class="mc"><header class="mc-head"><a class="mc-back" href="?page=chat" aria-label="Back to chats">'.icon('arrow').'</a><span class="mc-av" aria-hidden="true">'.icon('people').'</span><div><b>'.e($c['pname']).' and '.e($c['tname']).'</b><small>Read only</small></div></header><div class="mc-log" role="log">';
  $day='';foreach($rows as $r){$d=date('F j, Y',strtotime((string)$r['created_at']));if($d!==$day){$day=$d;echo '<div class="mc-day">'.e($d).'</div>';}
   $t=(int)$r['author_id']===(int)$c['teacher_id'];
   echo '<div class="mc-row '.($t?'me':'them').'"><div class="mc-bub'.(!empty($r['media_kind'])?' mc-media':'').'"><b class="ch-who">'.e($t?$c['tname']:$c['pname']).'</b>'.media_bubble($r,'?page=chat_media&amp;id='.(int)$r['id']).((string)$r['body']!==''?'<p>'.nl2br(e($r['body'])).'</p>':'').'<time>'.e(date('g:i A',strtotime((string)$r['created_at']))).'</time></div></div>';}
  echo '<span id="msg-end"></span></div></div>';
 }else echo '<div class="msg-welcome">'.icon('shield').'<h2>Class chats</h2><p>Choose a chat to read it.</p></div>';
 echo '</section></div>';
}
