<?php
/* Teacher accounts, like pupil accounts: BULIG makes the Teacher ID and a starter password and prints a Teacher Pass
   with a QR code. The first scan signs the teacher in; then BULIG asks for their own password before anything else.
   After that the QR code only fills in the Teacher ID, so a lost pass cannot open a teacher's pupils.
   Admins can also add a whole list of teachers from a CSV or Excel file. Uses the table from 018_pupil_cards.sql. */
const TEACHER_ACCOUNT_ACTIONS=['first_password','admin_teacher_starter','timport_preview','timport_confirm','timport_cancel'];

/** Still on the starter password: the teacher makes their own before using BULIG. */
function teacher_must_change(?array $u):bool{return $u!==null&&$u['role']==='teacher'&&card_has_starter((int)$u['id']);}
/** The pupil or teacher a scanned sign-in key belongs to. */
function card_user_any(string $key):?array{
 if(!cards_ready()||!preg_match('~^[A-Za-z0-9_-]{20,40}$~',$key))return null;
 return one("SELECT u.* FROM pupil_cards c JOIN users u ON u.id=c.user_id WHERE c.key_hash=? AND u.role IN ('pupil','teacher') AND u.active=1",[hash('sha256',$key)])?:null;
}
/** The teacher sign-in form with the Teacher ID filled in. */
function teacher_login_url(array $t):string{return '?page=login&role=teacher&form=1&id='.rawurlencode((string)$t['public_id']);}
function teacher_char(int $id,string $pose='hello'):string{return char_src(tf_is_male($id)?'teacher-male':'teacher-female',$pose);}
/** "Grade 4 · Silang" (and "+1 more") for a teacher's pass. */
function teacher_sections_label(int $id):string{
 $s=rows('SELECT grade_level,name FROM sections s WHERE s.teacher_id=?'.(sections_archive_supported()?' AND s.archived_at IS NULL':'').' ORDER BY grade_level,name',[$id]);
 return $s?'Grade '.(int)$s[0]['grade_level'].' · '.$s[0]['name'].(count($s)>1?' +'.(count($s)-1).' more':''):'';
}

/** A new teacher: Teacher ID from the sequence, a starter password and a Teacher Pass key. Call inside a transaction. */
function teacher_create(string $name,string $sex):array{
 $next=(int)val('SELECT next_value FROM id_sequences WHERE kind=? FOR UPDATE',['teacher']);q('UPDATE id_sequences SET next_value=next_value+1 WHERE kind=?',['teacher']);$public='T'.$next;
 q('INSERT INTO users(public_id,role,name,password_hash) VALUES(?,?,?,?)',[$public,'teacher',$name,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();
 q('INSERT INTO teachers VALUES(?)',[$id]);tf_set_sex($id,$sex);
 $card=card_issue($id,true);
 notify($id,'account','Welcome to BULIG, '.explode(' ',trim($name))[0].'!','Start by adding a section, then your pupils.','?page=sections');
 return ['id'=>$id,'public'=>$public,'pw'=>$card['pw']];
}

/** One Teacher Pass: the same ticket as the pupils' Reading Pass, for a teacher. */
function teacher_ticket_html(array $t,?string $key,?string $pw,int $n):string{
 $qr=$key?'?page=login&key='.rawurlencode($key):teacher_login_url($t);$sec=(string)($t['sections']??'');
 return '<article class="tk tk-teacher"><div class="tk-main"><img class="tk-bgimg" src="assets/images/ticket-bg.webp?v=1" alt="" aria-hidden="true"><div class="tk-band"><img class="tk-logo" src="assets/bulig-logo.png" alt="BULIG"><b>TEACHER PASS</b></div><div class="tk-info"><strong class="tk-name">'.e($t['name']).'</strong><span class="tk-sec">'.($sec!==''?'<span>'.e($sec).'</span>':'').'<span class="tk-lvl">TEACHER</span></span><span class="tk-lab">Teacher ID</span><span class="tk-id">'.e($t['public_id']).'</span>'
  .'<span class="tk-pass">'.($pw!==null?'<span class="tk-lab">Starter password</span><span class="tk-pw">'.e($pw).'</span>':'<span class="tk-lab">Password</span><span class="tk-pw tk-own">Your own password</span>').'</span></div><img class="tk-kid" src="'.teacher_char((int)$t['id']).'" alt=""></div>'
  .'<div class="tk-stub"><span class="lc-qr tk-qr" data-qr="'.e($qr).'" aria-hidden="true"></span><span class="tk-scan">'.($key&&$pw!==null?'SCAN TO<br>SIGN IN':'SCAN TO<br>OPEN BULIG').'</span>'.($pw!==null?'<span class="tk-must">Make your own password after you sign in</span>':'').'<span class="tk-no">No. '.str_pad((string)$n,3,'0',STR_PAD_LEFT).'</span></div></article>';
}

/** Right after the admin adds a teacher: a window with the new account and its Teacher Pass, ready to print. */
function new_teacher_overlay(int $tid):string{
 $t=one("SELECT id,name,public_id FROM users WHERE id=? AND role='teacher'",[$tid]);if(!$t||!cards_ready())return '';
 $t['sections']=teacher_sections_label($tid);$info=card_info($tid);$pw=$info['pw']??null;$first=explode(' ',trim((string)$t['name']))[0];
 return '<div class="np-ov" data-np role="dialog" aria-modal="true" aria-labelledby="np-title"><div class="np-box"><button type="button" class="np-x" data-np-close aria-label="Close">'.icon('close').'</button>'
  .'<div class="np-top"><span class="np-ok">'.icon('check').'</span><div><h2 id="np-title">'.e($first).' is ready!</h2><p class="muted">Give this Teacher Pass to '.e($first).'. One scan of the QR code signs them in, then BULIG asks them to make their own password right away.</p></div></div>'
  .'<div class="np-ticket">'.teacher_ticket_html($t,$info['key'],$pw,1).'</div>'
  .'<div class="np-facts"><div><span>Teacher ID</span><b>'.e($t['public_id']).'</b></div>'.($pw!==null?'<div><span>Starter password</span><b>'.e($pw).'</b></div>':'').'</div>'
  .'<div class="np-acts"><button type="button" class="btn primary" data-np-print>'.icon('download').'Print this pass</button><button type="button" class="btn secondary" data-np-add>'.icon('user').'Add another teacher</button><a class="btn secondary" href="?page=teacher_cards">'.icon('key').'All Teacher Passes</a><button type="button" class="btn quiet" data-np-close>Done</button></div></div></div>'
  .'<script src="'.asset_url('qrcode.js').'" defer></script><script src="'.asset_url('cards.js').'" defer></script>';
}

/** Teachers who still use a starter password (their pass shows it). */
function teacher_starter_ids():array{if(!cards_ready())return [];return array_map('intval',array_column(rows("SELECT c.user_id FROM pupil_cards c JOIN users u ON u.id=c.user_id AND u.role='teacher' WHERE c.starter_enc IS NOT NULL"),'user_id'));}
function teacher_needs_018():string{return '<section class="card"><div class="ws-hd"><h2>'.icon('alert').' One database file is needed first</h2></div><p>Teacher Passes and starter passwords use the same table as the pupils’ Reading Pass. Import <b>database/migrations/018_pupil_cards.sql</b> once in phpMyAdmin, then come back to this page.</p></section>';}

/** Printable Teacher Passes, and who still uses a starter password. */
function teacher_cards_view():void{
 $one=(int)($_GET['t']??0);$show=(string)($_GET['show']??'all');$new=isset($_GET['new'])?array_map('intval',array_column($_SESSION['teacher_import_done']??[],'uid')):[];
 echo '<div class="pageheading ws-head no-print"><div><span class="eyebrow">TEACHERS</span><h1>Teacher Passes</h1><p class="muted">Print, cut along the dashed lines and give each teacher their pass. Up to 10 fit on one A4 sheet.</p></div><div class="ws-btns"><a class="btn secondary" href="?page=accounts">'.icon('arrow','flip').'Back to teachers</a></div></div>';
 if(!cards_ready()){echo teacher_needs_018();return;}
 $starter=teacher_starter_ids();$logins=admin_last_logins();
 $all=rows("SELECT id,name,public_id FROM users WHERE role='teacher' AND active=1 ORDER BY name");
 $list=array_values(array_filter($all,fn($t)=>$one?(int)$t['id']===$one:($new?in_array((int)$t['id'],$new,true):($show!=='starter'||in_array((int)$t['id'],$starter,true)))));
 echo '<div class="lc-tools no-print card"><form method="get" class="lc-filter"><input type="hidden" name="page" value="teacher_cards"><label>Teachers<select name="show" data-autosubmit><option value="all"'.($show==='all'&&!$one&&!$new?' selected':'').'>Everyone · '.count($all).' teacher'.(count($all)===1?'':'s').'</option><option value="starter"'.($show==='starter'&&!$one&&!$new?' selected':'').'>Still on a starter password · '.count(array_intersect(array_map('intval',array_column($all,'id')),$starter)).'</option>'.($one||$new?'<option value="pick" selected>'.($one?'One teacher':'Just added · '.count($new)).'</option>':'').'</select></label><noscript><button class="btn quiet">Show</button></noscript></form>';
 echo '<label class="lc-opt"><input type="checkbox" data-lc-toggle="lc-hide-qr" checked> QR code</label><label class="lc-opt"><input type="checkbox" data-lc-toggle="lc-hide-pass" checked> Starter password</label><button type="button" class="btn primary" data-lc-print>'.icon('download').'<span data-lc-print-label>Print passes</span></button></div>';
 if(!$list){echo '<section class="empty no-print">'.icon('people').'<h2>No teachers here.</h2><p>'.($show==='starter'?'Every teacher has made their own password.':'Add teachers on the Teachers page, then come back to print their passes.').'</p></section>';}
 else{
  echo '<p class="lc-note no-print">The first scan signs the teacher in, and BULIG asks them to make their own password. After that, the QR code only fills in the Teacher ID. Lost a pass? Use “New starter password”: the old password and QR code stop working.</p>';
  echo '<div class="lc-pick no-print card"><span class="lc-count" data-tk-count>'.count($list).' of '.count($list).' passes selected</span><button type="button" class="btn secondary" data-tk-all>Select all</button><button type="button" class="btn secondary" data-tk-none>Select none</button>'
   .'<span class="lc-size">Pass size<span class="st-seg" role="radiogroup" aria-label="Pass size"><button type="button" class="st-seg-b" role="radio" aria-checked="false" data-tk-size="s">Small<small>21 per page</small></button><button type="button" class="st-seg-b" role="radio" aria-checked="false" data-tk-size="m">Medium<small>10 per page</small></button><button type="button" class="st-seg-b" role="radio" aria-checked="false" data-tk-size="l">Large<small>8 per page</small></button></span></span></div>';
  echo '<div class="lc-sheet tk-sheet" data-lc-sheet data-tk-word="pass">';$n=0;
  foreach($list as $t){$n++;$tid=(int)$t['id'];$t['sections']=teacher_sections_label($tid);$info=card_info($tid);
   echo '<div class="tk-wrap" data-tk>'.teacher_ticket_html($t,$info['key'],$info['pw'],$n).'<div class="tk-bar no-print"><label class="tk-sel"><input type="checkbox" data-tk-pick checked> Print</label>'
    .'<form method="post" class="tk-new">'.csrf_field().'<input type="hidden" name="action" value="admin_teacher_starter"><input type="hidden" name="id" value="'.$tid.'"><input type="hidden" name="back" value="cards"><button class="btn quiet" data-confirm="Make a new starter password for '.e($t['name']).'? Their old password and QR code stop working, and they make a new password at the next sign-in.">'.icon('replay').'New starter password</button></form></div></div>';}
  echo '</div>';
 }
 echo '<section class="card no-print tc-pw"><div class="ws-hd"><h2>'.icon('key').' Passwords</h2><small class="muted">Who still uses a starter password</small></div><div class="tablewrap"><table class="ws-table"><thead><tr><th>Teacher</th><th>Password</th><th>Last sign-in</th></tr></thead><tbody>';
 foreach($all as $t){$st=in_array((int)$t['id'],$starter,true);echo '<tr><td><strong>'.e($t['name']).'</strong><small>'.e($t['public_id']).'</small></td><td><span class="ws-pill '.($st?'warn':'ok').'">'.($st?'Starter · must change':'Own password').'</span></td><td>'.e(admin_when($logins[(int)$t['id']]??null)).'</td></tr>';}
 if(!$all)echo '<tr><td colspan="3">No teachers yet.</td></tr>';
 echo '</tbody></table></div></section><script src="'.asset_url('qrcode.js').'" defer></script><script src="'.asset_url('cards.js').'" defer></script>';
}

/* ---------- Add many teachers from a CSV or Excel file ---------- */
function teacher_import_check(array $rows):array{
 $hi=null;foreach(array_slice($rows,0,10,true) as $i=>$r){$c=import_columns($r);if(isset($c['name'])||isset($c['last'])||isset($c['first'])){$hi=$i;$cols=$c;break;}}
 if($hi===null)fail('No header row found. The first row must have column names: Last name, First name, Middle name and Sex. Download the template to see the format.');
 $have=[];foreach(rows("SELECT public_id,name FROM users WHERE role='teacher'") as $t)$have[ck_name_key((string)$t['name'])]=$t['public_id'];
 $taken=[];foreach(rows('SELECT s.grade_level,s.name,u.name tname FROM sections s JOIN users u ON u.id=s.teacher_id WHERE 1'.(sections_archive_supported()?' AND s.archived_at IS NULL':'')) as $s)$taken[mb_strtolower(trim((string)$s['name'])).'|'.(int)$s['grade_level']]=$s['tname'];
 $out=[];$seen=[];
 foreach(array_slice($rows,$hi+1) as $n=>$r){
  $get=fn($k)=>isset($cols[$k])?trim((string)($r[$cols[$k]]??'')):'';
  if(!array_filter($r,fn($c)=>trim((string)$c)!==''))continue;
  if(count($out)>=IMPORT_MAX_ROWS)fail('A teacher list can have up to '.IMPORT_MAX_ROWS.' teachers per upload. Split the file and upload it in parts.');
  $name=$get('name');
  if($name!==''&&substr_count($name,',')===1){[$l,$f]=array_map('trim',explode(',',$name));if($l!==''&&$f!=='')$name=$f.' '.$l;}
  if($name===''){$name=trim(import_name_case($get('first')).' '.import_name_case($get('middle')).' '.import_name_case($get('last')));}else $name=import_name_case($name);
  $name=trim(preg_replace('/\s+/',' ',$name));$sexRaw=strtolower($get('sex'));
  $sex=in_array($sexRaw,['m','male','lalaki','l'],true)?'male':(in_array($sexRaw,['f','female','babae','b'],true)?'female':null);
  $row=['line'=>$hi+$n+2,'name'=>$name,'sex'=>$sex,'grade'=>0,'section'=>'','status'=>'ok','notes'=>[]];
  $err=function(string $m)use(&$row){$row['status']='error';$row['notes'][]=$m;};
  if(mb_strlen($name)<2||mb_strlen($name)>150)$err('Name is missing or too long.');
  if(!$sex)$err($sexRaw===''?'Sex is missing (M or F).':'Sex must be M or F.');
  $grade=$get('grade');$grade=$grade!==''?(int)preg_replace('/\D/','',$grade):0;$sec=$get('section');
  if($sec!==''){
   if($grade>=1&&$grade<=6){$row['grade']=$grade;$row['section']=mb_substr(import_name_case($sec),0,80);$k=mb_strtolower($row['section']).'|'.$grade;if(isset($taken[$k]))$row['notes'][]='Another teacher ('.$taken[$k].') already has Grade '.$grade.' – '.$row['section'].'.';}
   else $err('Add a Grade from 1 to 6 for the section, or leave Section blank.');
  }elseif($grade)$row['notes'][]='Grade without a Section is left out.';
  if($row['status']==='ok'){$k=ck_name_key($name);
   if(isset($have[$k])){$row['status']='skip';$row['notes']=['Already in BULIG ('.$have[$k].').'];}
   elseif(isset($seen[$k]))$err('Same name as line '.$seen[$k].' in this file.');
   $seen[$k]=$row['line'];}
  $out[]=$row;
 }
 if(!$out)fail('The file has a header row but no teachers under it.');
 return $out;
}

function teacher_import_view():void{
 $p=$_SESSION['teacher_import']??null;$done=$_SESSION['teacher_import_done']??null;$step=(string)($_GET['step']??'');
 admin_heading('TEACHERS · ADD MANY','Add the whole team at once.','Upload a list from Excel. BULIG makes every Teacher ID and starter password.','<a class="btn secondary" href="?page=accounts">'.icon('arrow','flip').'Back to teachers</a>');
 if(!cards_ready()){echo teacher_needs_018();return;}
 echo '<section class="card import-card" id="import">';
 if($step==='check'&&$p){
  $ok=count(array_filter($p['rows'],fn($r)=>$r['status']==='ok'));$skip=count(array_filter($p['rows'],fn($r)=>$r['status']==='skip'));$bad=count(array_filter($p['rows'],fn($r)=>$r['status']==='error'));
  echo '<div class="section-heading"><div><span class="eyebrow">STEP 2</span><h2>Check the list</h2></div></div><p class="import-summary"><strong>'.e($p['file']).'</strong> · <span class="pill ok">'.$ok.' ready</span> <span class="pill skip">'.$skip.' already in BULIG</span> <span class="pill bad">'.$bad.' need fixing</span></p>';
  if($bad)echo '<p class="muted">Rows that need fixing are left out. Fix them in your file and upload again, or make the ready accounts now and add the rest later.</p>';
  echo '<div class="tablewrap import-preview"><table><thead><tr><th>Line</th><th>Name</th><th>Sex</th><th>First section</th><th>Check</th></tr></thead><tbody>';
  foreach($p['rows'] as $r)echo '<tr class="row-'.$r['status'].'"><td>'.$r['line'].'</td><td>'.e($r['name']?:'—').'</td><td>'.e($r['sex']?ucfirst($r['sex']):'—').'</td><td>'.e($r['section']!==''?'Grade '.$r['grade'].' – '.$r['section']:'—').'</td><td><span class="pill '.($r['status']==='ok'?'ok':($r['status']==='skip'?'skip':'bad')).'">'.($r['status']==='ok'?'Ready':($r['status']==='skip'?'Skip':'Fix')).'</span>'.($r['notes']?'<small>'.e(implode(' ',$r['notes'])).'</small>':'').'</td></tr>';
  echo '</tbody></table></div><div class="import-actions">';
  if($ok)echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="timport_confirm"><input type="hidden" name="token" value="'.e($p['token']).'"><button class="btn primary">'.icon('check').'Create '.$ok.' teacher account'.($ok===1?'':'s').'</button></form>';
  echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="timport_cancel"><button class="btn secondary">'.icon('replay').'Upload again</button></form></div></section>';return;
 }
 if($step==='done'&&$done){
  echo '<div class="section-heading"><div><span class="eyebrow">STEP 3</span><h2>'.count($done).' teacher account'.(count($done)===1?'':'s').' made</h2></div></div><p class="import-summary">Print a Teacher Pass for each teacher, or download the sign-in list. Each starter password works only until the teacher makes their own.</p>'
   .'<div class="import-actions"><a class="btn primary" href="?page=teacher_cards&amp;new=1">'.icon('download').'Print '.count($done).' Teacher Pass'.(count($done)===1?'':'es').'</a><a class="btn secondary" href="?page=teacher_logins">'.icon('download').'Download sign-in list (CSV)</a><a class="btn secondary" href="?page=teacher_import">Upload another list</a></div>';
  echo '<div class="tablewrap import-preview"><table><thead><tr><th>Teacher ID</th><th>Name</th><th>First section</th></tr></thead><tbody>';foreach($done as $d)echo '<tr><td><strong>'.e($d['id']).'</strong></td><td>'.e($d['name']).'</td><td>'.e($d['section']?:'—').'</td></tr>';echo '</tbody></table></div></section>';return;
 }
 echo '<div class="section-heading"><div><span class="eyebrow">STEP 1</span><h2>Upload a teacher list</h2></div><a class="btn quiet small" href="?page=teacher_template">'.icon('download').'Download template</a></div>'
  .'<ol class="import-steps"><li>Open the template in Excel. Fill in <strong>Last name</strong>, <strong>First name</strong>, <strong>Middle name</strong> and <strong>Sex</strong> (M or F). <strong>Grade</strong> and <strong>Section</strong> are optional: BULIG adds that section for the teacher.</li><li>Upload the .xlsx or .csv file. Nothing is saved yet. You will see every row first.</li><li>Press <strong>Create accounts</strong>, then print the Teacher Passes.</li></ol>'
  .'<div class="tablewrap import-preview tc-sample"><table><thead><tr><th>Last name</th><th>First name</th><th>Middle name</th><th>Sex</th><th>Grade <small>optional</small></th><th>Section <small>optional</small></th></tr></thead><tbody><tr><td>Fernandez</td><td>Liza</td><td>Cruz</td><td>F</td><td>4</td><td>Silang</td></tr><tr><td>Ramos</td><td>Jose</td><td>Dizon</td><td>M</td><td>1</td><td>Rizal</td></tr><tr><td>Lopez</td><td>Carmen</td><td>Reyes</td><td>F</td><td></td><td></td></tr></tbody></table></div>'
  .'<form method="post" enctype="multipart/form-data" class="formgrid import-form">'.csrf_field().'<input type="hidden" name="action" value="timport_preview"><input type="hidden" name="MAX_FILE_SIZE" value="2097152"><label class="import-file">Teacher list file (.xlsx or .csv)<input type="file" name="teacher_list" accept=".csv,.xlsx,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>'
  .'<div class="formend"><small>Up to '.IMPORT_MAX_ROWS.' teachers, 2 MB. Teachers already in BULIG (same name) are skipped.</small><button class="btn primary">'.icon('check').'Check the list</button></div></form></section>';
}

function teacher_import_download(string $page):never{
 require_role('admin');
 if($page==='teacher_template'){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-teacher-list-template.csv"');
  echo "\xEF\xBB\xBF".'Last name,First name,Middle name,Sex,Grade,Section'."\r\n".'Fernandez,Liza,Cruz,F,4,Silang'."\r\n".'Ramos,Jose,Dizon,M,1,Rizal'."\r\n".'Lopez,Carmen,Reyes,F,,'."\r\n";exit;}
 $done=$_SESSION['teacher_import_done']??[];if(!$done)fail('There is no finished import to download.',404);
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-new-teacher-logins.csv"');$h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");
 fputcsv($h,['Teacher ID','Name','First section','Starter password'],',','"','\\');foreach($done as $d)fputcsv($h,[$d['id'],$d['name'],$d['section'],$d['pw']],',','"','\\');exit;
}

/* ---------- "Make your own password" (a teacher's first sign-in) ---------- */
function teacher_first_password_view(array $u):void{
 $first=explode(' ',trim((string)$u['name']))[0];$err=(string)($_SESSION['tp_err']??'');unset($_SESSION['tp_err']);
 $hello=$_SESSION['hello']??null;unset($_SESSION['hello']);head('Make your password','login-page tp-page lsky-'.login_daypart());if($hello)$_SESSION['hello']=$hello;  // the welcome screen waits for the dashboard
 $eye=fn($id)=>str_replace('data-password="password"','data-password="'.$id.'"',login_eye_button());
 echo login_shell_open(false,'teacher');notice();
 echo '<div class="login-top"><span class="tp-lock">'.icon('lock').'ONE MORE STEP</span><form method="post" class="tp-out">'.csrf_field().'<input type="hidden" name="action" value="logout"><button type="submit" class="admin-pill">'.icon('logout').'Sign out</button></form></div>'
  .'<img class="tp-who" src="'.teacher_char((int)$u['id']).'" alt=""><h2>Welcome to BULIG, <br>'.e($first).'!</h2><p class="muted tp-why">Before you start, make your own password. Only you will know it.</p>';
 if($err!=='')echo '<div class="pin-error" role="alert">'.icon('alert').'<span>'.e($err).'</span></div>';
 echo '<form method="post" class="stack" data-tp-form>'.csrf_field().'<input type="hidden" name="action" value="first_password">'
  .'<label>New password<span class="password-field"><input id="tp-new" name="new_password" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="Letters and numbers">'.$eye('tp-new').'</span></label>'
  .'<label>Type it again<span class="password-field"><input id="tp-again" name="confirm_password" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="The same password">'.$eye('tp-again').'</span></label>'
  .'<ul class="tp-rules" data-tp-rules><li data-r="len">'.icon('check').'At least 8 characters</li><li data-r="mix">'.icon('check').'Letters and numbers</li><li data-r="same">'.icon('check').'Both boxes match</li></ul>'
  .'<button class="btn primary full" type="submit">Save my password '.icon('arrow').'</button></form>'
  .'<p class="tp-note">'.icon('info').'<span>Next time, scan your Teacher Pass to fill in your Teacher ID, then type this password.</span></p>';
 echo login_shell_close();
}

function teacher_account_action(string $action):never{
 if($action==='first_password'){
  $u=require_role('teacher');if(!teacher_must_change($u))go('?page=dashboard');
  $back=function(string $m):never{$_SESSION['tp_err']=$m;go('?page=dashboard');};
  $new=(string)($_POST['new_password']??'');
  if(strlen($new)<8||strlen($new)>72)$back('Use 8 to 72 characters.');
  if(!preg_match('/[A-Za-z]/',$new)||!preg_match('/[0-9]/',$new))$back('Use both letters and numbers.');
  if($new!==(string)($_POST['confirm_password']??''))$back('The two passwords are not the same. Type them again.');
  if(password_verify($new,(string)$u['password_hash']))$back('Make a new password. The starter password cannot be used again.');
  q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);card_forget_starter((int)$u['id']);saved_login_clear((int)$u['id']);
  session_regenerate_id(true);$_SESSION['teacher_welcome']=1;audit('first_password','teacher');flash('Your password is saved. Welcome to BULIG!');go('?page=dashboard');
 }
 $u=require_role('admin');
 if($action==='admin_teacher_starter'){
  if(!cards_ready())fail('Import database/migrations/018_pupil_cards.sql first.');
  $t=admin_teacher((int)($_POST['id']??0));$n=card_issue((int)$t['id'],true);saved_login_clear((int)$t['id']);try{q('DELETE FROM remember_tokens WHERE user_id=?',[$t['id']]);}catch(Throwable $e){}
  audit('admin_teacher_starter',(string)$t['public_id']);flash('New starter password for '.$t['name'].' ('.$t['public_id'].'): '.$n['pw'].'. The old password and QR code no longer work. Print the new Teacher Pass.');
  go(($_POST['back']??'')==='cards'?'?page=teacher_cards&t='.(int)$t['id']:'?page=accounts');
 }
 if($action==='timport_preview'){
  if(!cards_ready())fail('Import database/migrations/018_pupil_cards.sql first.');
  $f=$_FILES['teacher_list']??null;
  if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)fail(($f['error']??0)===UPLOAD_ERR_INI_SIZE||($f['error']??0)===UPLOAD_ERR_FORM_SIZE?'The file is too big. Keep it under 2 MB.':'Choose a CSV or Excel file to upload.');
  if($f['size']>2*1024*1024)fail('The file is too big. Keep it under 2 MB.');
  $checked=teacher_import_check(import_read_file($f['tmp_name'],(string)$f['name']));
  $_SESSION['teacher_import']=['token'=>bin2hex(random_bytes(16)),'file'=>mb_substr(basename((string)$f['name']),0,120),'rows'=>$checked];unset($_SESSION['teacher_import_done']);
  go('?page=teacher_import&step=check');
 }
 if($action==='timport_cancel'){unset($_SESSION['teacher_import']);flash('Cancelled. No accounts were made.');go('?page=teacher_import');}
 if($action==='timport_confirm'){
  $p=$_SESSION['teacher_import']??null;if(!$p||!hash_equals($p['token'],(string)($_POST['token']??'')))fail('This list has expired. Upload it again.');
  if(!cards_ready())fail('Import database/migrations/018_pupil_cards.sql first.');
  $done=[];db()->beginTransaction();try{
   $have=[];foreach(rows("SELECT name FROM users WHERE role='teacher'") as $t)$have[ck_name_key((string)$t['name'])]=1;
   foreach($p['rows'] as $r){if($r['status']!=='ok'||isset($have[ck_name_key($r['name'])]))continue;
    $c=teacher_create($r['name'],(string)$r['sex']);$have[ck_name_key($r['name'])]=1;$label='';
    if($r['section']!==''){q('INSERT INTO sections(teacher_id,grade_level,name) VALUES(?,?,?)',[$c['id'],$r['grade'],$r['section']]);$label='Grade '.$r['grade'].' – '.$r['section'];}
    $done[]=['uid'=>$c['id'],'id'=>$c['public'],'name'=>$r['name'],'section'=>$label,'pw'=>$c['pw']];}
   audit('import_teachers',count($done).' from '.$p['file']);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  unset($_SESSION['teacher_import']);$_SESSION['teacher_import_done']=$done;
  flash(count($done).' teacher account'.(count($done)===1?'':'s').' made. Print their Teacher Passes.');go('?page=teacher_import&step=done');
 }
 fail('Unknown action.');
}
