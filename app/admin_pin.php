<?php
/** Admin PIN: after the administrator password, a private 6-digit PIN is asked (3 tries, then a 15-minute lock). */
const ADMIN_PIN_TRIES=3;
const ADMIN_PIN_LOCK_MINUTES=15;
const ADMIN_PIN_STEP_SECONDS=300;

function admin_security(int $uid):array{
 return one('SELECT * FROM admin_security WHERE user_id=?',[$uid])??['user_id'=>$uid,'pin_hash'=>null,'failed_pins'=>0,'locked_until'=>null];
}
function admin_pin_locked(int $uid):?int{
 $s=admin_security($uid);if(!$s['locked_until'])return null;$left=strtotime((string)$s['locked_until'])-time();return $left>0?$left:null;
}
function valid_pin(string $pin):bool{return (bool)preg_match('/^[0-9]{4}$/D',$pin)&&!preg_match('/^(\d)\1{3}$/D',$pin)&&!in_array($pin,['1234','4321','0123','3210','1212','2580'],true);}
function save_admin_pin(int $uid,string $pin):void{
 if(!valid_pin($pin))fail('Choose 4 digits that are not all the same and not 1234.');
 q('INSERT INTO admin_security(user_id,pin_hash,failed_pins,locked_until) VALUES(?,?,0,NULL) ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash),failed_pins=0,locked_until=NULL',[$uid,password_hash($pin,PASSWORD_DEFAULT)]);
}
/** The admin passed the password: hold them at the PIN step (not signed in yet). */
function admin_pin_start(array $u):never{
 if($left=admin_pin_locked((int)$u['id'])){$_SESSION['login_error']='Admin sign-in is locked. Try again in '.ceil($left/60).' minute'.(ceil($left/60)==1?'':'s').'.';go('?page=login&role=admin&confirm=1');}
 session_regenerate_id(true);$_SESSION['pin_pending']=['uid'=>(int)$u['id'],'until'=>time()+ADMIN_PIN_STEP_SECONDS];$_SESSION['csrf']=bin2hex(random_bytes(32));
 go('?page=login&role=admin&confirm=1');
}
function admin_pin_pending():?int{
 $p=$_SESSION['pin_pending']??null;if(!$p)return null;
 if(($p['until']??0)<time()){unset($_SESSION['pin_pending']);$_SESSION['login_error']='The PIN step timed out. Sign in again.';return null;}
 return (int)$p['uid'];
}
function admin_pin_actions(string $action):void{
 if($action==='admin_pin_cancel'){unset($_SESSION['pin_pending']);go('?page=login');}
 if($action==='admin_pin'||$action==='admin_pin_create'){
  $uid=admin_pin_pending();if(!$uid)go('?page=login&role=admin&confirm=1');
  $u=one("SELECT * FROM users WHERE id=? AND role='admin' AND active=1",[$uid]);if(!$u){unset($_SESSION['pin_pending']);go('?page=login');}
  $pin=preg_replace('/\D/','',(string)($_POST['pin']??''));$s=admin_security($uid);
  if($action==='admin_pin_create'){
   if($s['pin_hash'])go('?page=login&role=admin&confirm=1');
   if($pin!==preg_replace('/\D/','',(string)($_POST['pin2']??''))){$_SESSION['login_error']='The two PINs are not the same. Try again.';go('?page=login&role=admin&confirm=1');}
   if(!valid_pin($pin)){$_SESSION['login_error']='Choose 4 digits that are not all the same and not 1234.';go('?page=login&role=admin&confirm=1');}
   save_admin_pin($uid,$pin);admin_pin_audit($uid,'admin_pin_created','');admin_pin_finish($u);
  }
  if($left=admin_pin_locked($uid)){unset($_SESSION['pin_pending']);$_SESSION['login_error']='Admin sign-in is locked. Try again in '.ceil($left/60).' minutes.';go('?page=login&role=admin&confirm=1');}
  if($s['pin_hash']&&password_verify($pin,(string)$s['pin_hash'])){q('UPDATE admin_security SET failed_pins=0,locked_until=NULL WHERE user_id=?',[$uid]);admin_pin_finish($u);}
  $failed=(int)$s['failed_pins']+1;admin_pin_audit($uid,'admin_pin_failed',(string)$failed);
  if($failed>=ADMIN_PIN_TRIES){q('UPDATE admin_security SET failed_pins=0,locked_until=DATE_ADD(NOW(),INTERVAL '.ADMIN_PIN_LOCK_MINUTES.' MINUTE) WHERE user_id=?',[$uid]);unset($_SESSION['pin_pending']);
   $_SESSION['login_error']='Too many wrong PINs. Admin sign-in is locked for '.ADMIN_PIN_LOCK_MINUTES.' minutes.';go('?page=login&role=admin&confirm=1');}
  q('UPDATE admin_security SET failed_pins=? WHERE user_id=?',[$failed,$uid]);$left=ADMIN_PIN_TRIES-$failed;
  $_SESSION['login_error']='Wrong PIN. '.$left.' '.($left===1?'try':'tries').' left.';go('?page=login&role=admin&confirm=1');
 }
 if($action==='change_admin_pin'){
  $u=require_role('admin');$s=admin_security((int)$u['id']);
  if($s['pin_hash']&&!password_verify(preg_replace('/\D/','',(string)($_POST['current_pin']??'')),(string)$s['pin_hash']))fail('Your current PIN is not right.');
  $pin=preg_replace('/\D/','',(string)($_POST['pin']??''));if($pin!==preg_replace('/\D/','',(string)($_POST['pin2']??'')))fail('The two new PINs are not the same.');
  save_admin_pin((int)$u['id'],$pin);audit('admin_pin_changed','');flash('Your admin PIN is changed.');go('?page=settings#admin-pin');
 }
}
/** Audit with the admin as actor (they are not signed in yet during the PIN step). */
function admin_pin_audit(int $uid,string $action,string $detail):void{q('INSERT INTO audit_log(actor_id,action,details) VALUES(?,?,?)',[$uid,$action,$detail]);}
function admin_pin_finish(array $u):never{
 unset($_SESSION['pin_pending']);session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));audit('login','admin');go('?page=dashboard');
}
function admin_pin_card(int $uid):void{
 $s=admin_security($uid);$create=!$s['pin_hash'];
 if(!empty($_SESSION['login_error'])){echo '<div class="pin-error" role="alert">'.icon('alert').'<span>'.e($_SESSION['login_error']).'</span></div>';unset($_SESSION['login_error']);}
 echo '<span class="admin-badge">'.icon('shield').'Admin mode</span><div class="pin-steps"><span class="on"></span><span class="on"></span></div><span class="pin-step">STEP 2 OF 2 · ADMIN PIN</span>';
 if($create)echo '<h2>Create your admin PIN.</h2><p class="muted pin-sub">Password accepted. Choose a private 4-digit PIN for every admin sign-in.</p>';
 else echo '<h2>Enter your admin PIN.</h2><p class="muted pin-sub">Password accepted. Enter your 4-digit PIN.</p>';
 echo '<form method="post" class="pin-form" data-pin-form>'.csrf_field().'<input type="hidden" name="action" value="'.($create?'admin_pin_create':'admin_pin').'">';
 $field=fn($name,$label,$auto)=>'<div class="pin-field" data-pin-field><label class="pin-label">'.$label.'<input class="pin-input" name="'.$name.'" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" required data-pin-input'.($auto?' autofocus':'').'></label><div class="pin-boxes" aria-hidden="true" data-pin-boxes><b></b><b></b><b></b><b></b></div></div>';
 echo $field('pin',$create?'New PIN':'PIN',true);if($create)echo $field('pin2','Type the PIN again',false);
 echo '<div class="pin-pad" data-pin-pad aria-label="Number pad">';foreach(['1','2','3','4','5','6','7','8','9'] as $d)echo '<button type="button" data-digit="'.$d.'">'.$d.'</button>';
 echo '<button type="button" data-digit="back" aria-label="Delete">⌫</button><button type="button" data-digit="0">0</button><button type="button" data-digit="clear" aria-label="Clear">C</button></div>';
 echo '<button class="btn primary full" type="submit">'.icon('shield').($create?'Save PIN and continue':'Verify and continue').'</button></form>';
 $left=ADMIN_PIN_TRIES-(int)$s['failed_pins'];
 echo '<div class="pin-note">'.icon('lock').'<span>'.($create?'Avoid easy PINs like 1234. You can change it later in Settings.':$left.' '.($left===1?'try':'tries').' left · '.ADMIN_PIN_TRIES.' wrong PINs lock admin sign-in for '.ADMIN_PIN_LOCK_MINUTES.' minutes.').'</span></div>';
 echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_pin_cancel"><button class="admin-entry pin-cancel" type="submit">Cancel and go back</button></form>';
}
function admin_pin_settings(array $u):void{
 $has=(bool)admin_security((int)$u['id'])['pin_hash'];
 echo '<section class="card" id="admin-pin"><h2>'.icon('shield').' Admin PIN</h2><p class="muted">Your 4-digit PIN is asked after your password every time you sign in as administrator.</p><form method="post" class="formgrid">'.csrf_field().'<input type="hidden" name="action" value="change_admin_pin">';
 if($has)echo '<label>Current PIN<input name="current_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="off"></label>';
 echo '<label>New PIN (4 digits)<input name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="off"></label><label>Type the new PIN again<input name="pin2" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="off"></label><button class="btn primary" type="submit">Change PIN</button></form></section>';
}
