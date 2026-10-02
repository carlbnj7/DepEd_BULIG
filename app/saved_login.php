<?php
/* Saved sign-ins: a pupil or teacher can keep up to 3 accounts on one device and sign in with a tap.
   The device keeps a random key; the server keeps only its SHA-256 hash (table saved_logins, 016_saved_logins.sql).
   Keys expire after 60 days without use and are removed when the password changes. Administrators are never saved. */
const SAVED_LOGIN_DAYS=60;
function saved_login_ready():bool{static $r=null;if($r===null){try{db()->query('SELECT 1 FROM saved_logins LIMIT 1');$r=true;}catch(Throwable $e){$r=false;}}return $r;}
/** Picture shown on the login page: the account's photo, or its BULIG character. */
function saved_login_profile(array $u):array{
 $first=explode(' ',trim((string)$u['name']))[0];$img=(string)($u['avatar_path']??'');$char=false;
 if(!$img||!local_image_file($img)){$char=true;$img='assets/images/characters/'.($u['role']==='teacher'?(tf_is_male((int)$u['id'])?'teacher-male':'teacher-female'):((string)val('SELECT sex FROM pupil_details WHERE pupil_id=?',[(int)$u['id']])==='female'?'girl':'boy')).'.webp?v=1';}
 return ['uid'=>(int)$u['id'],'name'=>(string)$u['name'],'first'=>$u['role']==='teacher'?'Teacher '.$first:$first,'role'=>$u['role'],'pid'=>(string)$u['public_id'],'img'=>$img,'char'=>$char];
}
function saved_login_create(array $u):string{
 $t=bin2hex(random_bytes(32));
 q('DELETE FROM saved_logins WHERE expires_at<NOW()');
 q('INSERT INTO saved_logins(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL '.SAVED_LOGIN_DAYS.' DAY))',[(int)$u['id'],hash('sha256',$t)]);
 /* Keep at most 10 devices per account. */
 $old=array_column(rows('SELECT id FROM saved_logins WHERE user_id=? ORDER BY COALESCE(last_used_at,created_at) DESC LIMIT 100 OFFSET 10',[(int)$u['id']]),'id');
 if($old)q('DELETE FROM saved_logins WHERE id IN ('.implode(',',array_map('intval',$old)).')');
 return $t;
}
function saved_login_use(string $t):?array{
 if(!preg_match('/^[0-9a-f]{64}$/',$t))return null;$h=hash('sha256',$t);
 $u=one("SELECT u.* FROM saved_logins s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>NOW() AND u.active=1 AND u.role IN ('pupil','teacher')",[$h]);
 if(!$u){q('DELETE FROM saved_logins WHERE token_hash=?',[$h]);return null;}
 q('UPDATE saved_logins SET last_used_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL '.SAVED_LOGIN_DAYS.' DAY) WHERE token_hash=?',[$h]);return $u;
}
function saved_login_forget(string $t):void{if(preg_match('/^[0-9a-f]{64}$/',$t))q('DELETE FROM saved_logins WHERE token_hash=?',[hash('sha256',$t)]);}
function saved_login_clear(int $uid):void{if(saved_login_ready())q('DELETE FROM saved_logins WHERE user_id=?',[$uid]);}
function saved_login_json(array $data):never{header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode($data);exit;}
/** Hidden marker on the first page after a password sign-in, so the device can offer to save the account. */
function saved_login_offer(array $u):string{
 if(empty($_SESSION['offer_save'])||!in_array($u['role'],['pupil','teacher'],true))return '';unset($_SESSION['offer_save']);
 if(!saved_login_ready())return '';$p=saved_login_profile($u);
 return '<div data-save-offer hidden data-uid="'.$p['uid'].'" data-name="'.e($p['name']).'" data-first="'.e($p['first']).'" data-role="'.e($p['role']).'" data-pid="'.e($p['pid']).'" data-img="'.e($p['img']).'" data-char="'.($p['char']?1:0).'"></div>';
}
