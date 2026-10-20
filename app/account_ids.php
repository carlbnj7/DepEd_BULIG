<?php
/* Account IDs (v2)
   Pupil    PCES260001   school code + year the account was made + number (a new count each year)
   Teacher  PCEST0001    school code + T + number
   Admin    A260001      A + year + number
   The school code has 3–10 letters (Settings › Account IDs). Numbers have at least 4 digits and simply grow
   longer after 9999. Each school code keeps its own numbers for pupils and for teachers in id_sequences
   (kind "p:PCES:26", "t:PCES", "a:26"). A number is only taken inside a transaction, so two people never get the
   same ID, and the users.public_id UNIQUE key is the final guard.
   An ID never changes after it is made: not when a pupil moves up a grade, to another section or teacher, or when
   the school code is changed later.
   Accounts made before v2 are switched by the administrator (Settings › Account IDs) after a backup copy of the
   database is saved in storage/backups. Their old ID stays in account_id_history and still signs in. Passwords,
   progress, scores, badges and levels are untouched: only users.public_id changes. */

const ACCOUNT_ID_ACTIONS=['admin_school_code','admin_ids_switch','admin_ids_undo','admin_ids_backup'];

/** True once 027_account_ids.sql has been imported (needed to switch old accounts and for old-ID sign-in). */
function account_ids_ready():bool{
 static $ok=null;if($ok!==null)return $ok;
 try{$ok=(bool)val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='account_id_history'");}catch(Throwable $e){$ok=false;}
 return $ok;
}

/* ---------- school code ---------- */
function valid_school_code(string $c):bool{return (bool)preg_match('/^[A-Z]{3,10}$/D',$c);}
/** "Pangantucan Central Elementary School" → "PCES". Small words (of, and, the, de, ng…) are skipped. */
function school_code_from_name(string $name):string{
 $s=strtoupper(strtr($name,['ñ'=>'n','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U']));
 $words=array_values(array_filter(preg_split('/[^A-Z]+/',$s)?:[],fn($w)=>$w!==''&&!in_array($w,['OF','AND','THE','DE','DEL','NG','SA','AT','FOR','IN','ON','INC'],true)));
 $code=implode('',array_map(fn($w)=>$w[0],$words));
 if(strlen($code)<3&&$words)$code=substr(implode('',$words),0,max(3,strlen($code)));
 return substr($code,0,10);
}
/** The code at the start of new pupil and teacher IDs: the saved school code, else the school name's initials. */
function school_code():string{
 $c=strtoupper(trim((string)admin_setting('school_code','')));if(valid_school_code($c))return $c;
 $g=school_code_from_name((string)admin_setting('school_name',''));return valid_school_code($g)?$g:'BULIG';
}

/* ---------- making a new ID ---------- */
function account_id_format(string $role,string $code,string $yy,int $n):string{
 $num=str_pad((string)$n,4,'0',STR_PAD_LEFT);
 return match($role){'pupil'=>$code.$yy.$num,'teacher'=>$code.'T'.$num,default=>'A'.$yy.$num};
}
function account_id_kind(string $role,string $code,string $yy):string{return match($role){'pupil'=>'p:'.$code.':'.$yy,'teacher'=>'t:'.$code,default=>'a:'.$yy};}
/** True when the ID belongs to an account now, or was an account's old ID (old IDs still sign in). */
function account_id_taken(string $id):bool{
 if(val('SELECT 1 FROM users WHERE public_id=?',[$id]))return true;
 return account_ids_ready()&&(bool)val('SELECT 1 FROM account_id_history WHERE old_id=?',[$id]);
}
/** The highest number an account made before v2 will keep when it is switched (pupil 20260005 → PCES260005,
    teacher T2026001 → PCEST0001), so accounts made before the switch never take that number. */
function account_id_reserved(string $role,string $yy,?string $code=null):int{
 $m=0;$in='';$a=[];/* v174: only accounts of the school with this code keep their number (each school counts from 1) */
 if($code!==null&&function_exists('schools_ready')&&schools_ready()){$in=' AND school_id IN (SELECT id FROM schools WHERE code=?)';$a=[$code];}
 if($role==='pupil')foreach(rows("SELECT public_id FROM users WHERE role='pupil' AND public_id LIKE ?$in",array_merge(['20'.$yy.'%'],$a)) as $r){if(preg_match('/^20'.$yy.'(\d{4,})$/D',$r['public_id'],$x))$m=max($m,(int)$x[1]);}
 if($role==='teacher')foreach(rows("SELECT public_id FROM users WHERE role='teacher' AND public_id LIKE 'T20%'$in",$a) as $r){if(preg_match('/^T20\d\d(\d+)$/D',$r['public_id'],$x))$m=max($m,(int)$x[1]);}
 return $m;
}
/** A new, unique ID for a pupil, teacher or admin. Call inside the transaction that creates the account. */
function new_account_id(string $role,?string $code=null,?string $yy=null):string{
 if(!in_array($role,['pupil','teacher','admin'],true))throw new InvalidArgumentException('Unknown role.');
 if(!db()->inTransaction())throw new LogicException('new_account_id() must run inside a transaction.');
 $code=$code??school_code();$yy=$yy??date('y');$kind=account_id_kind($role,$code,$yy);
 q('INSERT IGNORE INTO id_sequences(kind,next_value) VALUES(?,?)',[$kind,account_id_reserved($role,$yy,$code)+1]);
 $n=max(1,(int)val('SELECT next_value FROM id_sequences WHERE kind=? FOR UPDATE',[$kind]));
 while(account_id_taken(account_id_format($role,$code,$yy,$n)))$n++;
 q('UPDATE id_sequences SET next_value=? WHERE kind=?',[$n+1,$kind]);
 return account_id_format($role,$code,$yy,$n);
}
/** True when an ID already has the v2 shape for its role. */
function account_id_is_v2(string $id,string $role):bool{
 return match($role){'pupil'=>(bool)preg_match('/^[A-Z]{3,10}\d{6,}$/D',$id),'teacher'=>(bool)preg_match('/^[A-Z]{3,10}T\d{4,}$/D',$id),default=>(bool)preg_match('/^A\d{6,}$/D',$id)};
}

/* ---------- old IDs ---------- */
/** The account an old (switched) ID belongs to, or null. */
function account_id_moved(string $typed):?array{
 if($typed===''||!account_ids_ready())return null;
 $u=one('SELECT u.* FROM account_id_history h JOIN users u ON u.id=h.user_id WHERE h.old_id=? ORDER BY h.id DESC LIMIT 1',[$typed]);
 return $u?:null;
}
/** Accounts that still have an ID from before v2. */
function account_ids_old():array{return array_values(array_filter(rows('SELECT id,public_id,role,name,created_at FROM users ORDER BY created_at,id'),fn($u)=>!account_id_is_v2((string)$u['public_id'],(string)$u['role'])));}

/** The new ID for every account that still has an old one. Old numbers are kept where they are free
    (pupil 20260005 → PCES260005, teacher T2026001 → PCEST0001); the rest get the lowest free number. */
function account_ids_plan(string $code):array{
 $taken=[];foreach(rows('SELECT public_id FROM users') as $r)$taken[strtoupper((string)$r['public_id'])]=1;
 if(account_ids_ready())foreach(rows('SELECT old_id FROM account_id_history') as $r)$taken[strtoupper((string)$r['old_id'])]=1;
 $plan=[];
 foreach(account_ids_old() as $u){$old=(string)$u['public_id'];$yy=null;$pref=0;
  if($u['role']==='pupil'&&preg_match('/^20(\d\d)(\d{4,})$/D',$old,$m)){$yy=$m[1];$pref=(int)$m[2];}
  elseif($u['role']==='teacher'&&preg_match('/^T20\d\d(\d+)$/D',$old,$m))$pref=(int)$m[1];
  $yy=$yy??date('y',strtotime((string)$u['created_at'])?:time());
  $sc=function_exists('school_row')?school_row(user_school_id((int)$u['id'])):null;/* v174: each account takes its own school's code */
  $plan[]=['id'=>(int)$u['id'],'role'=>(string)$u['role'],'name'=>(string)$u['name'],'old'=>$old,'yy'=>$yy,'pref'=>$pref,'n'=>0,'new'=>'','code'=>$sc&&$u['role']!=='admin'?(string)$sc['code']:$code];}
 foreach($plan as &$p){if($p['pref']<1)continue;$c=account_id_format($p['role'],$p['code'],$p['yy'],$p['pref']);if(!isset($taken[$c])){$p['new']=$c;$p['n']=$p['pref'];$taken[$c]=1;}}unset($p);
 $next=[];
 foreach($plan as &$p){if($p['new']!=='')continue;$k=account_id_kind($p['role'],$p['code'],$p['yy']);$n=$next[$k]??1;
  while(isset($taken[account_id_format($p['role'],$p['code'],$p['yy'],$n)]))$n++;
  $p['n']=$n;$p['new']=account_id_format($p['role'],$p['code'],$p['yy'],$n);$taken[$p['new']]=1;$next[$k]=$n+1;}unset($p);
 return $plan;
}

/* ---------- backups (storage/backups, never reachable from the web) ---------- */
function account_ids_backup_dir():string{return __DIR__.'/../storage/backups';}
/** Save the whole database as a .sql.gz file in storage/backups. Returns its file name, or throws. */
function account_ids_backup(string $label):string{
 @set_time_limit(600);$dir=account_ids_backup_dir();
 if(!is_dir($dir)&&!@mkdir($dir,0750,true))throw new RuntimeException('The storage folder cannot be written to.');
 if(!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
 $gz=function_exists('gzopen');$name='bulig-'.$label.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql'.($gz?'.gz':'');$path=$dir.'/'.$name;
 $h=$gz?@gzopen($path,'wb6'):@fopen($path,'wb');if(!$h)throw new RuntimeException('The backup file could not be made.');
 $ok=true;bulig_sql_dump(function(string $t)use($h,$gz,&$ok){if(($gz?gzwrite($h,$t):fwrite($h,$t))===false)$ok=false;});
 $gz?gzclose($h):fclose($h);
 if(!$ok||filesize($path)<200){@unlink($path);throw new RuntimeException('The backup file could not be written completely.');}
 admin_set('last_backup_at',date('Y-m-d H:i:s'));audit('backup','saved '.$name);
 return $name;
}
function account_ids_backups():array{
 $out=[];foreach(glob(account_ids_backup_dir().'/bulig-*.sql*')?:[] as $f)$out[]=['name'=>basename($f),'size'=>filesize($f),'at'=>filemtime($f)];
 usort($out,fn($a,$b)=>$b['at']<=>$a['at']);return $out;
}

/* ---------- switching ---------- */
/** account_id_history must compare text the same way as users.public_id. 027 first made it utf8mb4_general_ci, while
    databases made on a host whose default is utf8mb4_unicode_ci have users in that collation (MySQL error 1267
    "Illegal mix of collations"). The table is changed to match; if the host does not allow that, nothing breaks,
    because BULIG never compares the two columns directly. */
function account_ids_match_collation():void{
 try{
  $col=fn($t,$c)=>val('SELECT COLLATION_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$t,$c]);
  $want=(string)$col('users','public_id');$have=(string)$col('account_id_history','old_id');
  if($want!==''&&$have!==''&&$want!==$have&&preg_match('/^(utf8mb4|utf8mb3|utf8)_[a-z0-9_]+$/D',$want,$m))
   db()->exec('ALTER TABLE account_id_history CONVERT TO CHARACTER SET '.($m[1]==='utf8'?'utf8mb3':$m[1]).' COLLATE '.$want);
 }catch(Throwable $e){}
}
/** Give every account with an old ID its new ID, after saving a backup copy of the database. */
function account_ids_switch(string $code,bool $ownBackup):array{
 if(!account_ids_ready())throw new RuntimeException('Import 027_account_ids.sql in phpMyAdmin first.');
 $backup='';
 account_ids_match_collation();
 try{$backup=account_ids_backup('before-new-ids');}catch(Throwable $e){if(!$ownBackup)throw new RuntimeException('BULIG could not save a backup copy ('.$e->getMessage().'), so nothing was changed. Download a backup from Print & download › Backup (or export the database in phpMyAdmin), then tick “I already saved a backup” and try again.');}
 db()->beginTransaction();
 try{
  /* rows left from a switch that was later reversed by restoring a backup: that account has its old ID again */
  foreach(rows('SELECT h.id,h.old_id,u.public_id cur FROM account_id_history h JOIN users u ON u.id=h.user_id') as $r)if(strcasecmp((string)$r['old_id'],(string)$r['cur'])===0)q('DELETE FROM account_id_history WHERE id=?',[(int)$r['id']]);
  $plan=account_ids_plan($code);$max=[];
  foreach($plan as $p){
   if(account_id_taken($p['new']))throw new RuntimeException('The ID '.$p['new'].' is already in use. Nothing was changed.');
   if(val('SELECT 1 FROM account_id_history WHERE old_id=? AND user_id<>?',[$p['old'],$p['id']]))throw new RuntimeException('The old ID '.$p['old'].' was used by another account before. Nothing was changed.');
   q('DELETE FROM account_id_history WHERE old_id=? AND user_id=?',[$p['old'],$p['id']]);
   if(!q('UPDATE users SET public_id=? WHERE id=? AND public_id=?',[$p['new'],$p['id'],$p['old']])->rowCount())throw new RuntimeException('An account changed while switching. Nothing was changed; please try again.');
   q('INSERT INTO account_id_history(user_id,old_id,new_id) VALUES(?,?,?)',[$p['id'],$p['old'],$p['new']]);
   $k=account_id_kind($p['role'],$p['code']??$code,$p['yy']);$max[$k]=max($max[$k]??0,$p['n']);}
  /* new accounts continue after the numbers now in use */
  foreach($max as $k=>$n)q('INSERT INTO id_sequences(kind,next_value) VALUES(?,?) ON DUPLICATE KEY UPDATE next_value=GREATEST(next_value,VALUES(next_value))',[$k,$n+1]);
  admin_set('school_code',$code);
  admin_set('account_ids_v2',json_encode(['at'=>date('Y-m-d H:i:s'),'count'=>count($plan),'backup'=>$backup,'code'=>$code]));
  audit('account_ids_v2',count($plan).' accounts · '.$code.($backup?' · backup '.$backup:' · own backup'));
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 /* teachers hear about it on their Overview; their pupils' old IDs keep working */
 if(function_exists('notify'))foreach(rows("SELECT id FROM users WHERE role='teacher' AND active=1") as $t)notify((int)$t['id'],'account','Pupil IDs have a new look','IDs now start with '.$code.' (for example '.account_id_format('pupil',$code,date('y'),1).'). Old IDs still work. You can print new sign-in cards anytime.','?page=cards');
 return ['count'=>count($plan),'backup'=>$backup];
}
/** Put the old IDs back (only for accounts that still have the ID they were switched to). */
function account_ids_undo():int{
 $n=0;db()->beginTransaction();
 try{
  foreach(rows('SELECT h.id,h.user_id,h.old_id,h.new_id,u.public_id cur FROM account_id_history h JOIN users u ON u.id=h.user_id ORDER BY h.id DESC') as $h){
   if($h['cur']===$h['new_id']&&!val('SELECT 1 FROM users WHERE public_id=? AND id<>?',[$h['old_id'],$h['user_id']])){q('UPDATE users SET public_id=? WHERE id=?',[$h['old_id'],$h['user_id']]);$n++;}
   q('DELETE FROM account_id_history WHERE id=?',[$h['id']]);}
  admin_set('account_ids_v2','');audit('account_ids_undo',$n.' accounts');db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 return $n;
}

/* ---------- admin: actions, downloads and the Settings › Account IDs tab ---------- */
function account_ids_action(string $action):never{
 $u=require_role('admin');$back='?page=settings&tab=ids';
 if($action==='admin_school_code'){
  $c=strtoupper(trim((string)($_POST['school_code']??'')));
  if(!valid_school_code($c)){flash('Use 3 to 10 letters (A–Z) for the school code, for example PCES.');go($back);}
  admin_set('school_code',$c);audit('school_code',$c);flash('School code saved: '.$c.'. New pupils get IDs like '.account_id_format('pupil',$c,date('y'),1).'.');go($back);}
 if($action==='admin_ids_backup'){
  try{$f=account_ids_backup('manual');flash('Backup saved: '.$f.'.');}catch(Throwable $e){flash('The backup could not be saved here: '.$e->getMessage().' Use “Download backup” in Print & download › Backup instead.');}go($back.'#ids-backups');}
 if($action==='admin_ids_switch'){
  $c=strtoupper(trim((string)($_POST['school_code']??'')));if(!valid_school_code($c)){flash('Save a school code (3 to 10 letters) first.');go($back);}
  try{$r=account_ids_switch($c,isset($_POST['own_backup']));}catch(Throwable $e){flash($e->getMessage());go($back.'#switch-ids');}
  $me=one('SELECT public_id FROM users WHERE id=?',[(int)$u['id']]);
  flash('Done! '.$r['count'].' '.($r['count']===1?'account now has its':'accounts now have their').' new ID. Your admin ID is now '.$me['public_id'].' (your old ID still works).');go($back);}
 if($action==='admin_ids_undo'){
  try{$n=account_ids_undo();flash($n.' '.($n===1?'account has':'accounts have').' the old ID again.');}catch(Throwable $e){flash('Nothing was changed: '.$e->getMessage());}go($back);}
 fail('Unknown action.',400);
}
function account_ids_download(string $what):never{
 require_role('admin');
 if($what==='backup'){
  $f=basename((string)($_GET['f']??''));$path=account_ids_backup_dir().'/'.$f;
  if(!preg_match('/^bulig-[a-z0-9-]+\.sql(\.gz)?$/D',$f)||!is_file($path))fail('Backup not found.',404);
  audit('backup','download '.$f);header('Content-Type: '.(str_ends_with($f,'.gz')?'application/gzip':'application/sql'));header('Content-Disposition: attachment; filename="'.$f.'"');header('Content-Length: '.filesize($path));readfile($path);exit;}
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-account-IDs-'.date('Y-m-d').'.csv"');
 $h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,['Role','Name','Grade','Section','ID','Old ID'],',','"','\\');
 $old=[];if(account_ids_ready())foreach(rows('SELECT user_id,old_id FROM account_id_history ORDER BY id') as $r)$old[(int)$r['user_id']]=$r['old_id'];
 foreach(rows("SELECT u.id,u.role,u.name,u.public_id,p.grade_level,p.section FROM users u LEFT JOIN pupils p ON p.user_id=u.id WHERE u.active=1 ORDER BY FIELD(u.role,'admin','teacher','pupil'),p.grade_level,p.section,u.name") as $r)
  fputcsv($h,[ucfirst($r['role']),$r['name'],$r['grade_level']?'Grade '.$r['grade_level']:'',(string)$r['section'],$r['public_id'],$old[(int)$r['id']]??''],',','"','\\');
 fclose($h);exit;
}
/** A short note on the admin Overview while some accounts still have an old ID. */
function account_ids_admin_notice():string{
 if(!account_ids_ready())return '';$n=count(account_ids_old());if(!$n)return '';
 return '<a class="ids-notice" href="?page=settings&amp;tab=ids#switch-ids">'.icon('key').'<span><b>New account IDs are ready</b><small>'.$n.' '.($n===1?'account still uses':'accounts still use').' an old ID. Open Settings › Account IDs to switch them.</small></span>'.icon('arrow').'</a>';
}
function account_ids_settings_view(array $u):void{
 $code=school_code();$saved=valid_school_code(strtoupper(trim((string)admin_setting('school_code',''))));$yy=date('y');
 $sample=fn($c)=>'<div class="ids-formats"><div><span>Pupil</span><b>'.e(account_id_format('pupil',$c,$yy,1)).'</b><small>school code + year + number</small></div><div><span>Teacher</span><b>'.e(account_id_format('teacher',$c,$yy,1)).'</b><small>school code + T + number</small></div><div><span>Admin</span><b>'.e(account_id_format('admin',$c,$yy,1)).'</b><small>A + year + number</small></div></div>';
 echo '<div class="ws-g2 ids-page"><section class="card" id="school-code"><div class="ws-hd"><h2>'.icon('school').' School code</h2><small class="muted">The start of every new pupil and teacher ID</small></div>';
 echo '<form method="post" class="ws-formrow">'.csrf_field().'<input type="hidden" name="action" value="admin_school_code"><label>School code (3–10 letters)<input name="school_code" required minlength="3" maxlength="10" pattern="[A-Za-z]{3,10}" autocapitalize="characters" autocomplete="off" spellcheck="false" value="'.e($code).'"></label><button class="btn primary">Save school code</button></form>';
 if(!$saved)echo '<p class="muted ids-hint">'.icon('info').'Suggested from the school name in School info. Check it, then choose Save.</p>';
 echo $sample($code).'<p class="muted ids-hint">'.icon('lock').'IDs never change once they are made — not when a pupil moves up a grade, to another section or teacher, or if the school code is changed later. Passwords are made automatically: teachers make their own at the first sign-in; pupils keep theirs.</p></section>';

 echo '<div class="ws-stack">';
 if(!account_ids_ready()){echo '<section class="card" id="switch-ids"><div class="ws-hd"><h2>'.icon('swap').' Switch existing accounts</h2></div><p>'.icon('alert').' Import <b>027_account_ids.sql</b> in phpMyAdmin first. Until then, new accounts already get the new IDs, and existing accounts keep their old IDs.</p></section>';}
 else{
  $old=account_ids_old();$done=json_decode((string)admin_setting('account_ids_v2',''),true);
  if($old){
   $plan=account_ids_plan($code);$cnt=['pupil'=>0,'teacher'=>0,'admin'=>0];foreach($plan as $p)$cnt[$p['role']]++;$mine=null;foreach($plan as $p)if($p['id']===(int)$u['id'])$mine=$p['new'];
   echo '<section class="card" id="switch-ids"><div class="ws-hd"><h2>'.icon('swap').' Switch existing accounts to the new IDs</h2><small class="muted">'.count($plan).' '.(count($plan)===1?'account':'accounts').' still '.(count($plan)===1?'uses':'use').' an old ID</small></div>';
   echo '<p class="ids-counts">'.implode(' · ',array_filter([$cnt['pupil']?$cnt['pupil'].' '.($cnt['pupil']===1?'pupil':'pupils'):'',$cnt['teacher']?$cnt['teacher'].' '.($cnt['teacher']===1?'teacher':'teachers'):'',$cnt['admin']?$cnt['admin'].' '.($cnt['admin']===1?'admin':'admins'):''])).'</p>';
   echo '<ul class="ids-safe"><li>'.icon('db').'A backup copy of the whole database is saved first.</li><li>'.icon('check').'Passwords, progress, scores, badges and levels do not change.</li><li>'.icon('key').'Old IDs still sign in, so nobody is locked out.</li>'.($mine?'<li>'.icon('user').'Your admin ID becomes <b>'.e($mine).'</b>.</li>':'').'</ul>';
   echo '<div class="tablewrap ids-tablewrap"><table class="ids-table"><thead><tr><th>Account</th><th>Old ID</th><th aria-hidden="true"></th><th>New ID</th></tr></thead><tbody>';
   foreach(array_slice($plan,0,80) as $p)echo '<tr><td><b>'.e($p['name']).'</b><small>'.e(ucfirst($p['role'])).'</small></td><td class="ids-old">'.e($p['old']).'</td><td class="ids-arrow" aria-hidden="true">'.icon('arrow').'</td><td class="ids-new">'.e($p['new']).'</td></tr>';
   echo '</tbody></table></div>'.(count($plan)>80?'<p class="muted">… and '.(count($plan)-80).' more. The full list is in the download after switching.</p>':'');
   echo '<form method="post" class="ids-go">'.csrf_field().'<input type="hidden" name="action" value="admin_ids_switch"><input type="hidden" name="school_code" value="'.e($code).'">'
    .'<label class="ids-own"><input type="checkbox" name="own_backup" value="1"> I already saved a backup myself (only needed if BULIG cannot save one)</label>'
    .'<button class="btn primary" data-confirm="Save a backup copy and switch '.count($plan).' '.(count($plan)===1?'account':'accounts').' to the new IDs with school code '.e($code).'? Old IDs will keep working.">'.icon('swap').'Save a backup and switch '.count($plan).' '.(count($plan)===1?'account':'accounts').'</button></form></section>';
  }else{
   echo '<section class="card" id="switch-ids"><div class="ws-hd"><h2>'.icon('check').' Every account has a new ID</h2>'.($done?'<small class="muted">Switched '.e(date('M j, Y',strtotime((string)$done['at']))).' · '.(int)$done['count'].' accounts</small>':'').'</div>';
   $me=one('SELECT public_id FROM users WHERE id=?',[(int)$u['id']]);
   echo '<p class="ids-me">'.icon('user').'<span>Your admin ID: <b>'.e((string)$me['public_id']).'</b></span></p><p>Old IDs still sign in. Teachers can print new sign-in cards with the new IDs anytime.</p><div class="ids-btns"><a class="btn secondary" href="?page=ids_csv">'.icon('download').'Download the list of IDs</a>';
   if($done&&!empty($done['backup']))echo '<a class="btn secondary" href="?page=ids_backup&amp;f='.rawurlencode((string)$done['backup']).'">'.icon('db').'Backup from before the switch</a>';
   echo '</div>';
   if((int)val('SELECT COUNT(*) FROM account_id_history'))echo '<form method="post" class="ids-undo">'.csrf_field().'<input type="hidden" name="action" value="admin_ids_undo"><button class="linkbutton" data-confirm="Give every switched account its old ID back? You can switch again afterwards.">Undo the switch (old IDs come back)</button></form>';
   echo '</section>';}
 }
 $bks=account_ids_backups();
 echo '<section class="card" id="ids-backups"><div class="ws-hd"><h2>'.icon('db').' Saved backups</h2><small class="muted">Kept in the protected storage folder</small></div>';
 if(!$bks)echo '<p class="muted">No saved backups yet.</p>';
 foreach(array_slice($bks,0,5) as $b)echo '<div class="ids-bk"><span><b>'.e(date('M j, Y · g:i A',$b['at'])).'</b><small>'.e($b['name']).' · '.e(number_format($b['size']/1048576,1)).' MB</small></span><a class="btn secondary" href="?page=ids_backup&amp;f='.rawurlencode($b['name']).'">'.icon('download').'Download</a></div>';
 echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_ids_backup"><button class="btn secondary">'.icon('db').'Save a backup now</button></form></section></div></div>';
}
