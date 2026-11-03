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
function chat_link_card(array $u):string{$n=chat_unread($u)+(function_exists('messages_unread')?messages_unread($u):0);
 return '<a class="card ch-link" href="?page=messages&amp;tab=chat#msg-end"><span class="ch-link-ic">'.icon('comic').'</span><span><b>Messages</b><small>'.($n?$n.' new '.($n===1?'message':'messages').' from your teacher':'Chat with your teacher and your school').'</small></span>'.($n?'<span class="msg-nr">'.$n.'</span>':'').icon('chev').'</a>';}
function chat_name(string $n):string{return trim(explode(' ',trim($n))[0]??$n);}

/* ---------- v209 Messenger look: a row of faces, then the chats with pictures; the chat with small faces and Seen ---------- */
/** Online means BULIG was open in the last 5 minutes (kept at most once a minute, in settings). */
function chat_touch(array $u):void{
 if(($_SESSION['online_at']??0)>time()-60)return;$_SESSION['online_at']=time();
 try{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['online_'.(int)$u['id'],(string)time()]);}catch(Throwable $e){}
}
function chat_online(int $id):bool{static $c=[];if(!isset($c[$id]))$c[$id]=(int)val('SELECT setting_value FROM settings WHERE setting_key=?',['online_'.$id])>time()-300;return $c[$id];}
/** A round picture: the person's photo, or their BULIG character (teachers and administrators), or the default picture. */
function chat_pic(array $p,string $size='56',bool $dot=false):string{
 $path=(string)($p['avatar_path']??'');$role=(string)($p['role']??'');$char=false;
 if($path!==''&&local_image_file($path))$src=$path.(str_starts_with($path,'assets/avatars/')?'?v=2':'');
 elseif($role==='teacher'||$role==='admin'){$m=function_exists('tf_is_male')&&tf_is_male((int)$p['id']);$src=char_src(($role==='admin'?'admin-':'teacher-').($m?'male':'female'),'hello');$char=true;}
 else $src='assets/design/default-avatar.svg';
 return '<span class="mz-avw"><span class="mz-av mz-'.$size.($char?' mz-char':'').'"><img src="'.e($src).'" alt="" loading="lazy"></span>'.($dot&&!empty($p['id'])&&chat_online((int)$p['id'])?'<span class="mz-dot" title="Online"></span>':'').'</span>';
}
function chat_ago(string $t):string{$x=strtotime($t);if(!$x)return '';if(date('Y-m-d',$x)===date('Y-m-d'))return date('g:i A',$x);if($x>time()-6*86400)return date('D',$x);return date('M j',$x);}
function chat_admin_user():?array{static $a=false;if($a===false)$a=one("SELECT id,name,role,avatar_path FROM users WHERE role='admin' AND active=1 ORDER BY id LIMIT 1");return $a?:null;}
/** The School row: the newest school message for a pupil or teacher. */
function chat_school_row(array $u,bool $on=false):string{
 if(!function_exists('messages_ready')||!messages_ready())return '';
 $m=one('SELECT m.subject,m.body,m.created_at FROM message_recipients r JOIN admin_messages m ON m.id=r.message_id WHERE r.user_id=? ORDER BY m.id DESC LIMIT 1',[(int)$u['id']]);$n=messages_unread($u);$ad=chat_admin_user()??['id'=>0,'role'=>'admin'];
 return '<a class="mz-row'.($n?' new':'').($on?' on':'').'" href="?page=messages&amp;tab=school#msg-end" data-name="school administrator">'.chat_pic($ad).'<span class="mz-t"><b><span class="mz-n">School administrator</span><span class="mz-tag mz-tag-s">School</span></b><small>'.($m?e(mb_substr((string)$m['subject'],0,60)).' · '.e(chat_ago((string)$m['created_at'])):'Messages from your school show here').'</small></span>'.($n?'<span class="mz-badge">'.$n.'</span>':'').'</a>';
}
function chat_face(string $href,string $pic,string $name,bool $on=false):string{return '<a class="mz-face'.($on?' on':'').'" href="'.$href.'"><span class="mz-ring">'.$pic.'</span><span>'.e($name).'</span></a>';}

function chat_view(array $u):void{
 if(!chat_ready()){echo '<section class="card"><h1>Chat is being set up</h1><p>Please tell your school administrator to import database/migrations/039_teacher_chat.sql.</p></section>';return;}
 $role=$u['role'];$uid=(int)$u['id'];chat_touch($u);
 if($role==='admin'){chat_admin_view($u);return;}
 $with=(int)($_GET['with']??0);$teacher=$role==='teacher';
 $sql='SELECT u.id,u.name,u.public_id,u.avatar_path,u.role,c.id chat,c.last_at,(SELECT COUNT(*) FROM chat_messages m WHERE m.chat_id=c.id AND m.read_at IS NULL AND (m.author_id IS NULL OR m.author_id<>?)) nr,(SELECT CONCAT(IF(m.author_id=?,\'You: \',\'\'),IF(m.media_kind=\'photo\',\'Photo\',IF(m.media_kind=\'voice\',\'Voice message\',m.body))) FROM chat_messages m WHERE m.chat_id=c.id ORDER BY m.id DESC LIMIT 1) lastb FROM teacher_pupils t ';
 $people=$teacher?rows($sql.'JOIN users u ON u.id=t.pupil_id AND u.active=1 LEFT JOIN chats c ON c.teacher_id=t.teacher_id AND c.pupil_id=u.id WHERE t.teacher_id=? ORDER BY c.last_at IS NULL,c.last_at DESC,u.name',[$uid,$uid,$uid])
  :rows($sql.'JOIN users u ON u.id=t.teacher_id AND u.active=1 LEFT JOIN chats c ON c.teacher_id=u.id AND c.pupil_id=t.pupil_id WHERE t.pupil_id=? ORDER BY c.last_at IS NULL,c.last_at DESC,u.name',[$uid,$uid,$uid]);
 $sel=null;foreach($people as $p)if((int)$p['id']===$with)$sel=$p;if($with&&!$sel)fail('You can chat only with '.($teacher?'your own pupils':'your teacher').'.',404);
 $class=$teacher&&($_GET['class']??'')==='1';$school=!$sel&&!$class&&(($_GET['tab']??'')==='school'||isset($_GET['id']));
 if(isset($_GET['id'])&&!val('SELECT 1 FROM message_recipients WHERE message_id=? AND user_id=?',[(int)$_GET['id'],$uid]))fail('Message not found.',404);   // only your own school messages   // v209 the School chat opens here too, in the same look
 echo '<div class="mz-shell'.($sel||$class||$school?' has-sel':'').'"><aside class="mz-inbox card" aria-label="Chats"><div class="mz-top">'.chat_pic($u,'38').'<h1>Messages</h1></div>'
  .'<label class="mz-search">'.icon('search').'<span class="sr-only">Search</span><input type="search" placeholder="Search" data-mz-search autocomplete="off"></label><div class="mz-faces">';
 if($teacher)echo '<a class="mz-face" href="?page=messages&amp;tab=chat&amp;class=1"><span class="mz-ring mz-ring-y"><span class="mz-av mz-56 mz-ic">'.icon('people').'</span></span><span>Message the class</span></a>';
 foreach(array_slice($people,0,$teacher?8:6) as $p)echo chat_face('?page=messages&amp;tab=chat&amp;with='.(int)$p['id'].'#msg-end',chat_pic($p,'56',true),chat_name((string)$p['name']),$sel&&(int)$p['id']===(int)$sel['id']);
 $ad=chat_admin_user();if($ad)echo chat_face('?page=messages&amp;tab=school#msg-end',chat_pic($ad),'School',$school);
 echo '</div><p class="mz-sec">CHATS</p><nav class="mz-list" aria-label="Chats">';
 $rows='';$more='';
 foreach($people as $p){$nr=(int)$p['nr'];$has=$p['chat']&&$p['lastb']!==null;
  $r='<a class="mz-row'.($nr?' new':'').($sel&&(int)$p['id']===(int)$sel['id']?' on':'').'" href="?page=messages&amp;tab=chat&amp;with='.(int)$p['id'].'#msg-end" data-name="'.e(mb_strtolower((string)$p['name'])).'">'.chat_pic($p,'56',true).'<span class="mz-t"><b><span class="mz-n">'.e($p['name']).'</span>'.(!$teacher?'<span class="mz-tag">'.(count($people)>1?'Teacher':'My teacher').'</span>':'').'</b><small>'.($has?e(mb_substr((string)$p['lastb'],0,70)).' · '.e(chat_ago((string)$p['last_at'])):($teacher?'No messages yet':'Say hello to your teacher!')).'</small></span>'.($nr?'<span class="mz-badge">'.$nr.'</span>':'').'</a>';
  if($has||!$teacher)$rows.=$r;else $more.=$r;}
 echo $rows.chat_school_row($u,$school);
 if($more!=='')echo '<p class="mz-sec">MY PUPILS</p>'.$more;
 if(!$people)echo '<p class="mz-empty">'.($teacher?'Add pupils to your class first.':'You do not have a teacher in BULIG yet.').'</p>';
 echo '<p class="mz-empty" data-mz-none hidden>No one with that name.</p></nav></aside><section class="mz-pane card" aria-label="Chat">';
 if($class)echo chat_class_form($u);
 elseif($sel)echo chat_box($u,$sel);
 elseif($school)echo chat_school_box($u);
 else echo '<div class="mz-hello">'.icon('comic').'<h2>'.($teacher?'Chat with your pupils':'Chat with your teacher').'</h2><p>'.($teacher?'Choose a pupil, or send one message to the whole class.':'Tap your teacher’s picture to start.').'</p><p class="muted">Send words, a photo or a voice message. Only the two of you and your school administrator can see the chat.</p></div>';
 echo '</section></div>';
}

/** The chat log, Messenger style: their messages with a small face, mine in green, a time line when time passes, Seen. */
function chat_log(array $rows,int $me,array $faceOf,bool $readOnly=false,array $names=[]):string{
 $h='';$prevT=0;$prevA=null;$n=count($rows);$lastMine=-1;foreach($rows as $i=>$r)if((int)$r['author_id']===$me)$lastMine=$i;
 foreach($rows as $i=>$r){$t=strtotime((string)$r['created_at']);$a=(int)$r['author_id'];
  if($t-$prevT>900){$h.='<div class="mz-time">'.e(date('Y-m-d',$t)===date('Y-m-d')?'Today '.date('g:i A',$t):date('M j, g:i A',$t)).'</div>';$prevA=null;}
  $next=$rows[$i+1]??null;$endGroup=!$next||(int)$next['author_id']!==$a||strtotime((string)$next['created_at'])-$t>900;$mine=$a===$me;
  $body=(!empty($r['title'])?'<b class="mz-subj">'.e($r['title']).'</b>':'').media_bubble($r,$r['src']??'?page=chat_media&amp;id='.(int)$r['id']).((string)$r['body']!==''?'<p>'.nl2br(e($r['body'])).'</p>':'');
  $h.='<div class="mz-m '.($mine?'me':'them').($endGroup?' end':'').'">'.(!$mine?($endGroup?($faceOf[$a]??''):'<span class="mz-sp"></span>'):'').'<div class="mz-b'.(!empty($r['media_kind'])?' mz-media':'').'" title="'.e(date('M j, g:i A',$t)).'">'.($readOnly&&$prevA!==$a?'<b class="mz-who">'.e($names[$a]??'').'</b>':'').$body.'</div></div>';
  if($i===$lastMine&&!$readOnly&&$r['read_at'])$h.='<div class="mz-seen" title="Seen">'.($faceOf['other']??'').'</div>';
  $prevT=$t;$prevA=$a;}
 return $h;
}

/** One chat with the person $o (a pupil for a teacher, a teacher for a pupil). */
function chat_box(array $u,array $o):string{
 $teacher=$u['role']==='teacher';$uid=(int)$u['id'];$cid=$teacher?chat_id($uid,(int)$o['id']):chat_id((int)$o['id'],$uid);
 $rows=$cid?array_reverse(rows('SELECT * FROM chat_messages WHERE chat_id=? ORDER BY id DESC LIMIT 200',[$cid])):[];
 if($cid)q('UPDATE chat_messages SET read_at=NOW() WHERE chat_id=? AND read_at IS NULL AND (author_id IS NULL OR author_id<>?)',[$cid,$uid]);
 $on=chat_online((int)$o['id']);
 $h='<div class="mc mz-chat"><header class="mc-head mz-hd"><a class="mc-back" href="?page=messages&amp;tab=chat" aria-label="Back to chats">'.icon('arrow').'</a>'.chat_pic($o,'40',true).'<div><b>'.e($o['name']).'</b><small class="'.($on?'mz-on':'').'">'.($on?'Online now':e($teacher?'Pupil · '.$o['public_id']:'Your teacher')).'</small></div></header><div class="mc-log mz-log" role="log" aria-label="Conversation">';
 if(!$rows)$h.='<div class="mz-start">'.chat_pic($o,'72').'<b>'.e($o['name']).'</b><small>'.($teacher?'Say hello to '.e(chat_name((string)$o['name'])).'!':'Say hello to your teacher!').'</small></div>';
 $h.=chat_log($rows,$uid,[(int)$o['id']=>chat_pic($o,'28'),'other'=>chat_pic($o,'16')]).'<span id="msg-end"></span></div>';
 $m=function_exists('media_ready')&&media_ready();
 $h.='<form method="post" class="mc-send mz-send">'.csrf_field().'<input type="hidden" name="action" value="chat_send"><input type="hidden" name="with" value="'.(int)$o['id'].'">'
  .($m?'<div class="mc-chip" data-mc-chip hidden><img alt="" data-mc-thumb><small data-mc-what>1 photo ready to send</small><button type="button" class="mc-ic" data-mc-clear aria-label="Remove">'.icon('close').'</button></div><button type="button" class="mc-ic" data-mc-photo aria-label="Send a photo">'.icon('image').'</button><input type="file" accept="image/*" data-mc-file hidden><button type="button" class="mc-ic" data-mc-mic aria-label="Record a voice message">'.icon('mic').'</button><div class="mc-rec" data-mc-rec hidden><span class="mc-dot"></span><b data-mc-time>0:00</b><span class="mc-recl">Recording…</span><button type="button" class="mc-ic" data-mc-cancel aria-label="Cancel recording">'.icon('trash').'</button></div>':'')
  .'<label class="sr-only" for="mc-body">Write a message</label><textarea id="mc-body" name="body" rows="1" maxlength="'.CHAT_MAX.'" required placeholder="Message…"></textarea><button class="mc-go" aria-label="Send">'.icon('arrow').'</button><p class="mc-err" role="status" data-mc-err></p></form>';
 return $h.'</div>';
}

/** v209 The School chat for a pupil or teacher: every message from the administrator and the replies, as one chat.
    A reply goes under the newest message (each message keeps its own private replies, as before). */
function chat_school_box(array $u):string{
 $uid=(int)$u['id'];$ad=chat_admin_user()??['id'=>0,'role'=>'admin','name'=>'School administrator'];
 $msgs=function_exists('messages_ready')&&messages_ready()?rows('SELECT m.id,m.sender_id,m.subject,m.body,m.created_at,r.read_at FROM message_recipients r JOIN admin_messages m ON m.id=r.message_id WHERE r.user_id=? ORDER BY m.id DESC LIMIT 60',[$uid]):[];
 $items=[];foreach($msgs as $m)$items[]=['id'=>0,'author_id'=>(int)$m['sender_id'],'title'=>$m['subject'],'body'=>(string)$m['body'],'created_at'=>$m['created_at'],'read_at'=>$m['read_at']];
 $ids=array_map(fn($m)=>(int)$m['id'],$msgs);$rep=[];
 if($ids&&function_exists('replies_ready')&&replies_ready()){
  $rep=rows('SELECT * FROM message_replies WHERE user_id=? AND message_id IN ('.implode(',',$ids).') ORDER BY id',[$uid]);
  foreach($rep as $r){$r['src']='?page=msg_media&amp;id='.(int)$r['id'];$items[]=$r;}
  q('UPDATE message_replies SET read_at=NOW() WHERE user_id=? AND message_id IN ('.implode(',',$ids).') AND (author_id IS NULL OR author_id<>user_id) AND read_at IS NULL',[$uid]);
  q('UPDATE message_recipients SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND message_id IN ('.implode(',',$ids).')',[$uid]);
  q("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND url LIKE '?page=messages%' AND read_at IS NULL",[$uid]);}
 usort($items,fn($a,$b)=>strtotime((string)$a['created_at'])<=>strtotime((string)$b['created_at']));
 /* the administrator may be another admin account than the first one: their face is the admin face */
 $face=[];foreach($items as $it)if((int)$it['author_id']!==$uid)$face[(int)$it['author_id']]=chat_pic($ad,'28');$face['other']=chat_pic($ad,'16');
 $h='<div class="mc mz-chat"><header class="mc-head mz-hd"><a class="mc-back" href="?page=messages&amp;tab=chat" aria-label="Back to chats">'.icon('arrow').'</a>'.chat_pic($ad,'40',true).'<div><b>School administrator</b><small>Messages from your school</small></div></header><div class="mc-log mz-log" role="log" aria-label="Conversation">';
 if(!$items)$h.='<div class="mz-start">'.chat_pic($ad,'72').'<b>School administrator</b><small>Messages from your school will show here.</small></div>';
 $h.=chat_log($items,$uid,$face).'<span id="msg-end"></span></div>';
 if($msgs&&function_exists('replies_ready')&&replies_ready()){$m=function_exists('media_ready')&&media_ready();
  $h.='<form method="post" class="mc-send mz-send">'.csrf_field().'<input type="hidden" name="action" value="message_reply"><input type="hidden" name="message_id" value="'.(int)$msgs[0]['id'].'">'
   .($m?'<div class="mc-chip" data-mc-chip hidden><img alt="" data-mc-thumb><small data-mc-what>1 photo ready to send</small><button type="button" class="mc-ic" data-mc-clear aria-label="Remove">'.icon('close').'</button></div><button type="button" class="mc-ic" data-mc-photo aria-label="Send a photo">'.icon('image').'</button><input type="file" accept="image/*" data-mc-file hidden><button type="button" class="mc-ic" data-mc-mic aria-label="Record a voice message">'.icon('mic').'</button><div class="mc-rec" data-mc-rec hidden><span class="mc-dot"></span><b data-mc-time>0:00</b><span class="mc-recl">Recording…</span><button type="button" class="mc-ic" data-mc-cancel aria-label="Cancel recording">'.icon('trash').'</button></div>':'')
   .'<label class="sr-only" for="mc-body">Write a reply</label><textarea id="mc-body" name="body" rows="1" maxlength="2000" required placeholder="Message…"></textarea><button class="mc-go" aria-label="Send">'.icon('arrow').'</button><p class="mc-err" role="status" data-mc-err></p></form>';}
 elseif(!$msgs)$h.='<p class="mz-ro">'.icon('info').'You can reply after your school sends you a message.</p>';
 return $h.'</div>';
}

/** A teacher sends one message to every pupil, or to one section. */
function chat_class_form(array $u):string{
 $secs=rows('SELECT s.id,s.name,s.grade_level,(SELECT COUNT(*) FROM pupil_sections ps JOIN teacher_pupils t ON t.pupil_id=ps.pupil_id AND t.teacher_id=s.teacher_id WHERE ps.section_id=s.id) n FROM sections s WHERE s.teacher_id=?'.(function_exists('sections_archive_supported')&&sections_archive_supported()?' AND s.archived_at IS NULL':'').' ORDER BY s.grade_level,s.name',[(int)$u['id']]);
 $all=(int)val('SELECT COUNT(*) FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 WHERE t.teacher_id=?',[(int)$u['id']]);
 $h='<div class="ch-class"><a class="btn secondary small" href="?page=messages&amp;tab=chat">'.icon('arrow','flip').'Back to chats</a><span class="eyebrow">ONE MESSAGE FOR MANY</span><h2>'.icon('people').'Message the class</h2><p class="muted">Each pupil gets this message in their own chat with you, and can reply only to you.</p><form method="post" class="stack">'.csrf_field().'<input type="hidden" name="action" value="chat_class"><label>Send to<select name="section"><option value="0">All my pupils ('.$all.')</option>';
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
  db()->beginTransaction();try{foreach($ids as $p){$c=chat_id($uid,$p,true);q('INSERT INTO chat_messages(chat_id,author_id,body) VALUES(?,?,?)',[$c,$uid,$body]);q('UPDATE chats SET last_at=NOW() WHERE id=?',[$c]);notify($p,'chat','New message from '.$u['name'],mb_substr($body,0,200),'?page=messages&tab=chat&with='.$uid.'#msg-end');}
   audit('chat_class',count($ids).' pupils');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Sent to '.count($ids).' '.(count($ids)===1?'pupil':'pupils').'. Each one can reply in their own chat.');go('?page=messages&tab=chat');}
 /* one message in one chat */
 $with=(int)($_POST['with']??0);[$t,$p]=$u['role']==='teacher'?[$uid,$with]:[$with,$uid];
 if(!$with||!chat_allowed($t,$p))fail('You can chat only with '.($u['role']==='teacher'?'your own pupils':'your teacher').'.',404);
 $hasMedia=function_exists('media_ready')&&media_ready()&&(!empty($_FILES['photo']['tmp_name'])||!empty($_FILES['voice']['tmp_name']));
 if($body===''&&!$hasMedia)fail('Write a message first.');
 $media=null;db()->beginTransaction();try{
  $c=chat_id($t,$p,true);
  if(!$hasMedia&&one('SELECT 1 FROM chat_messages WHERE chat_id=? AND author_id=? AND body=? AND created_at>=NOW()-INTERVAL 30 SECOND',[$c,$uid,$body])){db()->commit();if($ajax){header('Content-Type: application/json');echo '{"ok":true}';exit;}go('?page=messages&tab=chat&with='.$with.'#msg-end');}   // a double tap never sends twice
  $media=$hasMedia?media_save($p):null;
  q('INSERT INTO chat_messages(chat_id,author_id,body,media_kind,media_path,media_secs) VALUES(?,?,?,?,?,?)',[$c,$uid,$body,$media[0]??null,$media[1]??null,$media[2]??null]);q('UPDATE chats SET last_at=NOW() WHERE id=?',[$c]);
  $say=$body!==''?$body:(($media[0]??'')==='photo'?'Sent a photo':'Sent a voice message');
  notify($with,'chat',($u['role']==='teacher'?'New message from ':'Message from ').$u['name'],mb_substr($say,0,200),'?page=messages&tab=chat&with='.$uid.'#msg-end');
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!empty($media[1]))@unlink(media_dir().'/'.$media[1]);throw $e;}
 if($ajax){header('Content-Type: application/json');echo '{"ok":true}';exit;}
 go('?page=messages&tab=chat&with='.$with.'#msg-end');
}

/** A photo or voice message in a chat: the teacher and the pupil of that chat, and the administrator. */
function chat_media(?array $u):never{
 if(!$u||!chat_ready())fail('Not found.',404);$r=one('SELECT m.*,c.teacher_id,c.pupil_id FROM chat_messages m JOIN chats c ON c.id=m.chat_id WHERE m.id=?',[(int)($_GET['id']??0)]);
 if(!$r||empty($r['media_path'])||!in_array((int)$u['id'],[(int)$r['teacher_id'],(int)$r['pupil_id']],true)&&$u['role']!=='admin')fail('Not found.',404);
 $root=realpath(media_dir());$f=realpath(media_dir().'/'.$r['media_path']);if(!$root||!$f||!str_starts_with($f,$root.DIRECTORY_SEPARATOR)||!is_file($f))fail('This file was deleted after '.MEDIA_KEEP_DAYS.' days.',404);
 $type=['jpg'=>'image/jpeg','webm'=>'audio/webm','ogg'=>'audio/ogg','m4a'=>'audio/mp4','mp3'=>'audio/mpeg'][strtolower(pathinfo($f,PATHINFO_EXTENSION))]??'application/octet-stream';
 header('Content-Type: '.$type);header('Content-Length: '.filesize($f));header('Cache-Control: private, max-age=86400');header('X-Content-Type-Options: nosniff');readfile($f);exit;
}

/** The administrator reads the chats (read only), newest first; Sent messages is the first row. */
function chat_admin_view(array $u):void{
 $id=(int)($_GET['id']??0);
 $list=rows('SELECT c.id,c.last_at,c.teacher_id,c.pupil_id,t.name tname,p.name pname,p.avatar_path,p.role,p.id pid,(SELECT COUNT(*) FROM chat_messages m WHERE m.chat_id=c.id) n,(SELECT IF(m.media_kind=\'photo\',\'Photo\',IF(m.media_kind=\'voice\',\'Voice message\',m.body)) FROM chat_messages m WHERE m.chat_id=c.id ORDER BY m.id DESC LIMIT 1) lastb FROM chats c JOIN users t ON t.id=c.teacher_id JOIN users p ON p.id=c.pupil_id ORDER BY c.last_at DESC LIMIT 100');
 $sent=function_exists('messages_ready')&&messages_ready()?one('SELECT subject,created_at FROM admin_messages WHERE sender_id=? ORDER BY id DESC LIMIT 1',[(int)$u['id']]):null;$nr=function_exists('messages_unread')?messages_unread($u):0;
 echo '<div class="mz-shell'.($id?' has-sel':'').'"><aside class="mz-inbox card" aria-label="Chats"><div class="mz-top">'.chat_pic($u,'38').'<h1>Messages</h1></div><label class="mz-search">'.icon('search').'<span class="sr-only">Search</span><input type="search" placeholder="Search" data-mz-search autocomplete="off"></label>'
  .'<div class="mz-faces"><a class="mz-face" href="?page=messages&amp;tab=school&amp;compose=1"><span class="mz-ring mz-ring-y"><span class="mz-av mz-56 mz-ic">'.icon('edit').'</span></span><span>New message</span></a><a class="mz-face" href="?page=messages&amp;tab=school"><span class="mz-ring"><span class="mz-av mz-56 mz-ic mz-ic-b">'.icon('mega').'</span></span><span>Sent messages</span></a></div>'
  .'<nav class="mz-list" aria-label="Chats"><a class="mz-row'.($nr?' new':'').'" href="?page=messages&amp;tab=school" data-name="sent messages school">'.chat_pic($u).'<span class="mz-t"><b><span class="mz-n">Sent messages</span><span class="mz-tag mz-tag-s">School</span></b><small>'.($sent?e(mb_substr((string)$sent['subject'],0,60)).' · '.e(chat_ago((string)$sent['created_at'])):'Send a message to pupils and teachers').'</small></span>'.($nr?'<span class="mz-badge">'.$nr.'</span>':'').'</a>'
  .'<p class="mz-sec">CLASS CHATS · READ ONLY</p>';
 foreach($list as $c)echo '<a class="mz-row'.((int)$c['id']===$id?' on':'').'" href="?page=messages&amp;tab=chat&amp;id='.(int)$c['id'].'#msg-end" data-name="'.e(mb_strtolower($c['pname'].' '.$c['tname'])).'">'.chat_pic(['id'=>$c['pid'],'avatar_path'=>$c['avatar_path'],'role'=>'pupil']).'<span class="mz-t"><b>'.e($c['pname']).'</b><small>with '.e($c['tname']).' · '.e(mb_substr((string)$c['lastb'],0,50)).' · '.e(chat_ago((string)$c['last_at'])).'</small></span></a>';
 if(!$list)echo '<p class="mz-empty">No class chats yet.</p>';
 echo '<p class="mz-empty" data-mz-none hidden>No one with that name.</p></nav></aside><section class="mz-pane card" aria-label="Chat">';
 $c=$id?one('SELECT c.*,t.name tname,t.avatar_path tav,p.name pname,p.avatar_path pav FROM chats c JOIN users t ON t.id=c.teacher_id JOIN users p ON p.id=c.pupil_id WHERE c.id=?',[$id]):null;
 if($c){$rows=array_reverse(rows('SELECT * FROM chat_messages WHERE chat_id=? ORDER BY id DESC LIMIT 300',[$id]));$T=['id'=>$c['teacher_id'],'avatar_path'=>$c['tav'],'role'=>'teacher'];$P=['id'=>$c['pupil_id'],'avatar_path'=>$c['pav'],'role'=>'pupil'];
  echo '<div class="mc mz-chat"><header class="mc-head mz-hd"><a class="mc-back" href="?page=messages&amp;tab=chat" aria-label="Back to chats">'.icon('arrow').'</a>'.chat_pic($P,'40').'<div><b>'.e($c['pname']).' and '.e($c['tname']).'</b><small>Read only</small></div></header><div class="mc-log mz-log" role="log">'
   .chat_log($rows,(int)$c['teacher_id'],[(int)$c['pupil_id']=>chat_pic($P,'28')],true,[(int)$c['teacher_id']=>$c['tname'],(int)$c['pupil_id']=>$c['pname']]).'<span id="msg-end"></span></div><p class="mz-ro">'.icon('shield').'Read only. Only the teacher and the pupil write in this chat.</p></div>';
 }else echo '<div class="mz-hello">'.icon('shield').'<h2>Class chats</h2><p>Choose a chat to read it. Only the teacher and the pupil write in it.</p></div>';
 echo '</section></div>';
}
