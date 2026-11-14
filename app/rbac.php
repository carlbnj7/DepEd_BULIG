<?php
/* v210–v211 Organizational hierarchy and multiple roles (database/migrations/040_hierarchy_roles.sql).
   Central Admin → Municipalities → Districts → Schools → Teachers → Pupils, with nine roles.
   - One account, one sign-in. A person with several roles chooses one after signing in and can switch roles
     from the menu without signing out (?page=role). The active role is kept in the session.
   - Each role maps to the part of BULIG it uses: admin roles use the administrator pages, limited to their
     municipality, district or school; Teacher and Pupil keep their pages.
   - Viewer roles (Municipal Viewer, District Supervisor, School Principal) only look: every change is refused on
     the server, except messages, bug reports and their own profile.
   - Nothing about existing accounts changes: administrators become Central Admin, teachers stay Teacher and
     pupils stay Pupil. Without the SQL update BULIG works exactly as before. */

const ROLES=[
 'central_admin'=>['Division Admin','Entire division','admin','system'],
 'division_viewer'=>['Division Viewer','Entire division · view only','admin','system'],   // v216 looks at every school; changes nothing
 'municipal_admin'=>['Municipal Admin','Municipality management','admin','municipality'],
 'municipal_viewer'=>['Municipal Viewer','Municipality monitoring · view only','admin','municipality'],
 'district_admin'=>['District Admin','District management','admin','district'],
 'district_supervisor'=>['District Supervisor','District monitoring · view only','admin','district'],
 'school_admin'=>['School Admin','School management','admin','school'],
 'principal'=>['School Principal','School monitoring · view only','admin','school'],
 'content_dev'=>['Content Developer','Lesson content · drafts for review','studio','system'],   // v215 Content Studio: lessons only, no people (app/studio.php)
 'teacher'=>['Teacher','Classes and reading verification','teacher','school'],
 'pupil'=>['Pupil','Lessons and activities','pupil','school']];
const ROLE_VIEWERS=['division_viewer','municipal_viewer','district_supervisor','principal'];
/** Who may appoint whom (a role can only appoint roles below it, inside its own scope). */
const ROLE_APPOINTS=['central_admin'=>['division_viewer','municipal_admin','municipal_viewer','content_dev'],
 'municipal_admin'=>['district_admin','district_supervisor'],'district_admin'=>['school_admin','principal']];
/** v216 Every role under this one (the roles it appoints, and theirs, and so on). A role manages all of them inside its
    own area: remove them from the role, make a new password, print their pass. Appointing stays one level down. */
function role_below(string $role):array{$out=[];foreach(ROLE_APPOINTS[$role]??[] as $x){$out[]=$x;foreach(role_below($x) as $y)$out[]=$y;}return array_values(array_unique($out));}
/** v211 School Admin creates Teacher and Pupil accounts for their own school (Organization page). */
const ROLE_CREATES=['school_admin'=>['teacher','pupil']];
/** Actions that make teacher accounts: the Central Admin (first setup) and the School Admin only. */
const ROLE_TEACHER_MAKERS=['central_admin','school_admin'];
/** v211 ID letters for each administrative role: DV0001 = Division Admin, MA = Municipal Admin, and so on (v215: no year, like teacher IDs). */
const ROLE_ID_PREFIX=['central_admin'=>'DV','municipal_admin'=>'MA','municipal_viewer'=>'MV','district_admin'=>'DA','district_supervisor'=>'DS','school_admin'=>'SA','principal'=>'SP','content_dev'=>'CD','division_viewer'=>'DW'];
/** A new ID for an administrative account (inside a transaction): letters + number, e.g. MA0001 (v215: no year, like teacher IDs). */
function rbac_account_id(string $role):string{
 $pre=ROLE_ID_PREFIX[$role]??'A';$yy='';$kind='r:'.$pre;
 q('INSERT IGNORE INTO id_sequences(kind,next_value) VALUES(?,1)',[$kind]);
 $n=max(1,(int)val('SELECT next_value FROM id_sequences WHERE kind=? FOR UPDATE',[$kind]));
 while(account_id_taken($pre.$yy.str_pad((string)$n,4,'0',STR_PAD_LEFT)))$n++;
 q('UPDATE id_sequences SET next_value=? WHERE kind=?',[$n+1,$kind]);return $pre.$yy.str_pad((string)$n,4,'0',STR_PAD_LEFT);
}
/** Actions a viewer may still do (their own account, messages and bug reports). */
const ROLE_VIEWER_ACTIONS=['logout','role_switch','message_reply','message_preview','message_send','chat_send','bug_report_submit','bug_popup_ack','profile','choose_avatar','change_password','my_name','mode','a11y','privacy_agree','rate_app','rate_later','notif_read','notif_all_read','notif_delete','push_subscribe','push_prefs','push_off','push_test','moved_seen'];
/** Actions only the Central Admin may do (the whole system). */
const ROLE_CENTRAL_ACTIONS=['bug_report_update','bug_adopt','bug_fixed','bug_fixed_all','cleanup_merge','cleanup_archive','cleanup_clear','school_delete','muni_add','muni_delete','org_muni_add','org_muni_delete'];

function rbac_ready():bool{static $ok;if($ok===null){try{$ok=(bool)one("SHOW TABLES LIKE 'user_roles'")&&(bool)one("SHOW COLUMNS FROM schools LIKE 'district_id'");}catch(Throwable $e){$ok=false;}}return $ok;}

/** Every role of an account: [['role'=>…,'scope_id'=>…], …]. Teacher and Pupil come from the account itself. */
function rbac_roles(array $u):array{
 $out=[];$base=(string)$u['role'];
 if($base==='teacher'||$base==='pupil')$out[]=['role'=>$base,'scope_id'=>(int)($u['school_id']??0)];
 if(rbac_ready())foreach(rows('SELECT role,scope_id FROM user_roles WHERE user_id=? ORDER BY FIELD(role,'."'".implode("','",array_keys(ROLES))."'".'),scope_id',[(int)$u['id']]) as $r)if(isset(ROLES[$r['role']]))$out[]=['role'=>$r['role'],'scope_id'=>(int)$r['scope_id']];
 if(!$out&&$base==='admin')$out[]=['role'=>'central_admin','scope_id'=>0];   // an administrator from before the update
 return $out;
}
function rbac_key(array $r):string{return $r['role'].':'.(int)$r['scope_id'];}
/** The schools a role can see: null = every school. */
function rbac_scope_schools(string $role,int $scope):?array{
 $level=ROLES[$role][3]??'system';if($level==='system')return null;
 if(!rbac_ready())return [];
 if($level==='municipality')return array_map('intval',array_column(rows('SELECT s.id FROM schools s JOIN districts d ON d.id=s.district_id WHERE d.municipality_id=?',[$scope]),'id'));
 if($level==='district')return array_map('intval',array_column(rows('SELECT id FROM schools WHERE district_id=?',[$scope]),'id'));
 return $scope?[$scope]:[];
}
/** The name of a role's place: "Manolo Fortich", "Libona District I", a school name. */
function rbac_scope_name(string $role,int $scope):string{
 $level=ROLES[$role][3]??'system';if($level==='system'||!$scope)return $level==='system'?'All schools':'';
 if(!rbac_ready())return '';
 if($level==='municipality')return (string)(val('SELECT name FROM municipalities WHERE id=?',[$scope])?:'');
 if($level==='district')return (string)(val('SELECT name FROM districts WHERE id=?',[$scope])?:'');
 return (string)(val("SELECT COALESCE(NULLIF(name,''),code) FROM schools WHERE id=?",[$scope])?:'');
}

/** Called by current_user(): sets the active role. $u['role'] becomes admin, teacher or pupil for the pages,
    and $u['active_role'], $u['scope_id'], $u['scope_schools'] and $u['view_only'] tell what this role may do. */
function rbac_apply(array $u):array{
 if(($u['role']??'')==='tester')return ['base_role'=>'tester','role'=>'pupil','is_tester'=>true,'roles'=>[],'active_role'=>'pupil','scope_id'=>0,'scope_schools'=>null,'view_only'=>false]+$u;   // v215 the Content Developer's hidden test pupil uses the pupil pages
 $u['base_role']=$u['role'];$roles=rbac_roles($u);$u['roles']=$roles;
 $want=(string)($_SESSION['active_role']??'');$act=null;
 foreach($roles as $r)if(rbac_key($r)===$want)$act=$r;
 if(!$act&&$roles){$act=$roles[0];if(count($roles)===1)$_SESSION['active_role']=rbac_key($act);}
 if(!$act)return $u+['active_role'=>$u['role'],'scope_id'=>0,'scope_schools'=>null,'view_only'=>false];
 $u['active_role']=$act['role'];$u['scope_id']=$act['scope_id'];$u['role']=ROLES[$act['role']][2];
 $u['scope_schools']=$u['role']==='admin'?rbac_scope_schools($act['role'],$act['scope_id']):null;
 $u['view_only']=in_array($act['role'],ROLE_VIEWERS,true);
 return $u;
}
/** The active role's schools for the school filters: null = all. */
function rbac_my_schools():?array{$u=function_exists('current_user')?current_user():null;return $u&&($u['role']??'')==='admin'?($u['scope_schools']??null):null;}
/** True when the person has several roles and has not chosen one in this session yet. */
function rbac_must_choose(?array $u):bool{return $u&&count($u['roles']??[])>1&&empty($_SESSION['active_role']);}

/** v211 What each administrative role may do (the Division Admin keeps everything, like the administrator before the update).
    Municipal and District roles organize accounts and look at progress; the School Admin runs the school. */
const ROLE_CAPS=[
 'teachers'=>['central_admin','school_admin'],        // add, edit, reset, deactivate, delete teachers; Teacher Passes; move pupils; sections
 'announce'=>['central_admin','municipal_admin','district_admin','school_admin'],   // v213 each posts to their own area
 'school_info'=>['central_admin','municipal_admin','district_admin','school_admin'],
 'year'=>['central_admin'],                            // school year, schedule and lesson cycles
 'admin_passes'=>['central_admin','municipal_admin','district_admin']];
const ROLE_CAP_ACTIONS=['teachers'=>['create_account','update_account','timport_preview','timport_typed','timport_confirm','timport_cancel','admin_reset_password','admin_toggle_teacher','admin_rename_teacher','admin_move_pupils','admin_teacher_starter','delete_teacher','delete_teachers','admin_section_save','admin_section_add','admin_section_delete','delete_pupil','delete_pupils','delete_section','move_section'],
 'announce'=>['admin_announcement_add','admin_announcement_delete'],'school_info'=>['admin_school_info','admin_logo','admin_logo_remove'],'year'=>['admin_school_year','admin_level_schedule','admin_lesson_cycles']];
const ROLE_CAP_PAGES=['teachers'=>['teacher_cards','teacher_import','add_teachers','moved'],'admin_passes'=>['admin_passes']];
/** True when the active role may do this (always true for teachers, pupils, and before the SQL update). */
function rbac_can(string $cap):bool{$u=function_exists('current_user')?current_user():null;if(!$u||($u['role']??'')!=='admin'||empty($u['active_role']))return true;return in_array($u['active_role'],ROLE_CAPS[$cap]??[],true);}
/** Server-side check for every form: viewers only look; system-wide actions are for the Central Admin. */
function rbac_guard_action(string $action):void{
 $u=current_user();if(!$u||empty($u['active_role']))return;
 if(($u['active_role']??'')==='content_dev'&&!in_array($action,array_merge(ROLE_VIEWER_ACTIONS,defined('STUDIO_ACTIONS')?STUDIO_ACTIONS:[]),true))fail('The Content Developer role works on lessons only.',403);   // v215
 if(!empty($u['is_tester'])&&in_array($action,['rate_app','rate_later','message_send','message_preview','message_reply','chat_send','chat_class','privacy_delete_ask'],true))fail('This is not available in test mode.',403);
 if(!empty($u['view_only'])&&!in_array($action,ROLE_VIEWER_ACTIONS,true))fail('Your role ('.ROLES[$u['active_role']][0].') can view but not change this.',403);
 if(($u['role']??'')==='admin')foreach(ROLE_CAP_ACTIONS as $cap=>$acts)if(in_array($action,$acts,true)&&!rbac_can($cap))fail('Your role ('.ROLES[$u['active_role']][0].') cannot do this. '.($cap==='teachers'?'Teachers are managed by the School Admin.':'Ask the Division Admin.'),403);
 if(in_array($action,ROLE_CENTRAL_ACTIONS,true)&&$u['active_role']!=='central_admin')fail('Only the Division Admin can do this.',403);
 if(in_array($action,['create_account','timport_preview','timport_typed'],true)&&($u['role']??'')==='admin'){
  if(!in_array($u['active_role'],ROLE_TEACHER_MAKERS,true))fail('Teacher accounts are made by the School Admin.',403);
  if($u['active_role']==='school_admin')$_POST['school_id']=(string)(int)$u['scope_id'];}
 if($action==='school_add'&&!in_array($u['active_role'],['central_admin','municipal_admin','district_admin'],true))fail('Ask your District Admin to add a school.',403);
}
/** v211 The accounts that get pupils' and teachers' bug reports: the Division Admin (and administrators from before the update). */
function rbac_bug_admins():array{
 if(!rbac_ready())return array_column(rows("SELECT id FROM users WHERE role='admin' AND active=1"),'id');
 return array_column(rows("SELECT u.id FROM users u WHERE u.role='admin' AND u.active=1 AND (EXISTS(SELECT 1 FROM user_roles r WHERE r.user_id=u.id AND r.role='central_admin') OR NOT EXISTS(SELECT 1 FROM user_roles r WHERE r.user_id=u.id))"),'id');
}
/** Pages only the Central Admin opens (the whole system's data). */
function rbac_guard_page(string $page):void{
 $u=current_user();if(!$u||($u['role']??'')!=='admin')return;
 foreach(ROLE_CAP_PAGES as $cap=>$pgs)if(in_array($page,$pgs,true)&&!rbac_can($cap))fail('Your role ('.(ROLES[$u['active_role']??'central_admin'][0]??'').') cannot open this page.'.($cap==='teachers'?' Teachers are managed by the School Admin.':''),403);
 if(in_array($page,['studio_review','bugs','bugs_csv','cleanup','activity_log','backup_csv','backup_sql','log_csv','ids_backup'],true)&&($u['active_role']??'central_admin')!=='central_admin')fail('Only the Division Admin can open this page.',403);
}

/** v211 Keeps existing data: the municipalities and districts already saved in Settings (Schools page) become
    real municipalities and districts, and each school joins its district when its typed district names exactly one
    district ("District 1", "Division 1" and "District I" are the same). Unclear schools wait in the review list. */
function rbac_norm(string $d):string{$d=mb_strtolower(trim(preg_replace('/\s+/',' ',$d)));$d=preg_replace('/\bdivision\b/','district',$d);
 return preg_replace_callback('/\b(\d{1,2})\b/',fn($m)=>mb_strtolower(roman((int)$m[1])),$d);}
function rbac_sync_legacy():void{
 if(!rbac_ready())return;
 foreach(munis() as $m=>$n){$m=trim((string)$m);if($m==='')continue;q('INSERT IGNORE INTO municipalities(name) VALUES(?)',[$m]);$mid=(int)val('SELECT id FROM municipalities WHERE name=?',[$m]);
  foreach(muni_districts($m,(int)$n) as $d)q('INSERT IGNORE INTO districts(municipality_id,name) VALUES(?,?)',[$mid,$d]);}
 $ds=rows('SELECT d.id,d.name,m.name mn FROM districts d JOIN municipalities m ON m.id=d.municipality_id');
 foreach(rows("SELECT id,district FROM schools WHERE district_id IS NULL AND district<>''") as $s){$t=rbac_norm((string)$s['district']);$hit=[];
  foreach($ds as $d){$full=rbac_norm($d['name']);$short=trim(mb_substr($full,mb_strlen(rbac_norm($d['mn']))));
   if($t===$full||($short!==''&&$t===$short))$hit[]=(int)$d['id'];}
  if(count($hit)===1)q('UPDATE schools SET district_id=? WHERE id=? AND district_id IS NULL',[$hit[0],$s['id']]);}
}

/* ---------- choosing and switching roles ---------- */
function role_switch_action():never{
 $u=current_user();if(!$u)fail('Please sign in again.',401);$k=(string)($_POST['role']??'');
 foreach($u['roles'] as $r)if(rbac_key($r)===$k){
  session_regenerate_id(true);$_SESSION['active_role']=$k;unset($_SESSION['school_filter']);$_SESSION['hello']=1;
  try{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['last_role_'.(int)$u['id'],$k]);}catch(Throwable $e){}
  audit('role_switch',$k);go('?page=dashboard');}
 fail('This role is not assigned to your account.',403);
}
function role_cards(array $u):string{
 $last=(string)(val('SELECT setting_value FROM settings WHERE setting_key=?',['last_role_'.(int)$u['id']])?:'');$cur=(string)($_SESSION['active_role']??'');$h='<div class="role-grid">';
 foreach($u['roles'] as $r){$k=rbac_key($r);[$t,$d]=ROLES[$r['role']];$place=rbac_scope_name($r['role'],$r['scope_id']);
  $h.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="role_switch"><input type="hidden" name="role" value="'.e($k).'"><button class="role-card'.($k===$cur?' on':'').'">'.icon(in_array($r['role'],ROLE_VIEWERS,true)?'eye':($r['role']==='pupil'?'book':($r['role']==='teacher'?'people':'shield'))).'<b>'.e($t).'</b><small>'.e($d).($place!==''?' · '.e($place):'').'</small>'.($k===$cur?'<span class="role-tag">Now</span>':($k===$last?'<span class="role-tag">Last used</span>':'')).'</button></form>';}
 return $h.'</div>';
}
function role_view(array $u):void{
 echo '<section class="card role-pick"><span class="eyebrow">ONE ACCOUNT, MORE THAN ONE ROLE</span><h1>Choose Your Role</h1><p class="muted">'.e(name_case((string)$u['name'])).', choose how you want to use BULIG Quest now. You can switch any time from the menu, without signing out.</p>'.role_cards($u).'</section>';
}
/** v211 Right after signing in, a person with several roles chooses in a popup over their last-used dashboard. */
function rbac_start_popup(array $u):void{
 $last=(string)(val('SELECT setting_value FROM settings WHERE setting_key=?',['last_role_'.(int)$u['id']])?:'');$keys=array_map('rbac_key',$u['roles']);
 $_SESSION['active_role']=in_array($last,$keys,true)?$last:$keys[0];$_SESSION['role_pop']=1;
}
function role_popup(array $u):string{
 if(empty($_SESSION['role_pop'])||count($u['roles']??[])<2)return '';unset($_SESSION['role_pop']);$t=ROLES[$u['active_role']][0]??'';
 return '<div class="role-pop" role="dialog" aria-modal="true" aria-labelledby="rp-t" data-role-pop><div class="role-pop-box card"><span class="eyebrow">ONE ACCOUNT, MORE THAN ONE ROLE</span><h2 id="rp-t">Choose Your Role</h2><p class="muted">Hello, '.e(explode(' ',name_case(trim((string)$u['name'])))[0]).'! How do you want to use BULIG Quest now? You can switch any time from the menu.</p>'.role_cards($u).'<button type="button" class="btn secondary role-pop-x" data-role-pop-close>Continue as '.e($t).'</button></div></div>';
}
/** The role switcher in the menu (only for people with more than one role). */
function role_switcher(?array $u):string{
 if(!$u||count($u['roles']??[])<2)return '';$t=ROLES[$u['active_role']][0]??'';
 return '<a class="role-switch" href="?page=role">'.icon('swap').'<span><small>Role</small><b>'.e($t).'</b></span></a>';
}

/* ---------- the Organization page: municipalities, districts, schools and appointments ---------- */
function org_my_munis(array $u):array{
 $r=$u['active_role'];$s=(int)$u['scope_id'];
 if($r==='central_admin'||(ROLES[$r][3]??'')==='system')return rows('SELECT * FROM municipalities ORDER BY id');   // v216 the Division Viewer sees the whole division
 if(ROLES[$r][3]==='municipality')return rows('SELECT * FROM municipalities WHERE id=?',[$s]);
 if(ROLES[$r][3]==='district')return rows('SELECT m.* FROM municipalities m JOIN districts d ON d.municipality_id=m.id WHERE d.id=?',[$s]);
 return [];
}
function org_view(array $u):void{
 if(!rbac_ready()){echo '<section class="card"><h1>Organization</h1><p>'.icon('info').' Back up the database, then import <b>database/migrations/040_hierarchy_roles.sql</b> in phpMyAdmin to turn on municipalities, districts and roles.</p></section>';return;}
 if($u['active_role']==='central_admin')rbac_sync_legacy();
 $r=$u['active_role'];$central=$r==='central_admin';$view=!empty($u['view_only']);$munis=org_my_munis($u);
 echo hm_head('ORGANIZATION','Organization','Municipalities, districts and schools, and who manages each one. '.(ROLES[$r][0]??'').($u['scope_id']?' · '.rbac_scope_name($r,(int)$u['scope_id']):''));
 echo org_chart($u,$munis);
 $sch=rbac_my_schools();
 /* schools: the review list (no district yet) and moving a school to a district */
 if(in_array($r,['central_admin','municipal_admin','municipal_viewer','district_admin','district_supervisor'],true)){
  $dopts=rows('SELECT d.id,d.name FROM districts d WHERE 1'.($central||ROLES[$r][3]==='system'?'':(ROLES[$r][3]==='municipality'?' AND d.municipality_id='.(int)$u['scope_id']:' AND d.id='.(int)$u['scope_id'])).' ORDER BY d.name');
  $list=rows('SELECT s.id,s.code,s.name,s.district,s.district_id FROM schools s WHERE '.($sch===null?'1':($sch?'s.id IN ('.implode(',',$sch).')':'0')).($central?'':'').' ORDER BY s.district_id IS NOT NULL,s.name');
  if($central)$list=rows('SELECT s.id,s.code,s.name,s.district,s.district_id FROM schools s ORDER BY s.district_id IS NOT NULL,s.name');
  $wait=array_filter($list,fn($x)=>!$x['district_id']);
  echo '<section class="card org-schools" id="org-review"><div class="ws-hd"><h2>'.icon('alert').'Schools waiting for a district</h2><span class="rs-sub">'.count($wait).' to review</span></div><p class="muted">BULIG Quest never guesses. Choose each school’s district from DepEd’s official list. The school, its teachers and pupils stay the same.</p>';
  foreach($list as $x){if($x['district_id']&&!$wait)break;
   echo '<form method="post" class="org-srow'.($x['district_id']?' ok':'').'">'.csrf_field().'<input type="hidden" name="action" value="org_school_district"><input type="hidden" name="school" value="'.(int)$x['id'].'"><span><b>'.e($x['name']?:$x['code']).'</b><small>'.e($x['code']).($x['district']!==''?' · typed: '.e($x['district']):'').'</small></span>';
   if($view)echo '<span class="rs-sub">'.e($x['district_id']?(string)val('SELECT name FROM districts WHERE id=?',[$x['district_id']]):'Not set').'</span></form>';
   else{echo '<select name="district"><option value="0">Choose a district</option>';foreach($dopts as $d)echo '<option value="'.(int)$d['id'].'"'.((int)$x['district_id']===(int)$d['id']?' selected':'').'>'.e($d['name']).'</option>';echo '</select><button class="btn secondary small">Save</button></form>';}}
  if(!$list)echo '<p class="muted">No schools in your area yet.</p>';
  echo '</section>';
 }
 /* appointments */
 $can=ROLE_APPOINTS[$r]??[];$below=role_below($r);
 $rows=rows('SELECT ur.*,u.name,u.public_id FROM user_roles ur JOIN users u ON u.id=ur.user_id ORDER BY FIELD(ur.role,'."'".implode("','",array_keys(ROLES))."'".'),u.name');
 $rows=array_values(array_filter($rows,fn($x)=>org_in_my_scope($u,$x['role'],(int)$x['scope_id'])));
 echo '<section class="card org-people" id="org-people"><div class="ws-hd"><h2>'.icon('useredit').'Add a person to a role</h2></div>';
 if(!empty($_SESSION['org_new'])){$n=$_SESSION['org_new'];   // v211 stays until "I saved them" (a reload does not lose it)
  $card='';if(!empty($n['uid'])&&($n['rk']??'')!==''&&($n['rk']==='teacher'||isset(ROLES[$n['rk']]))){$pl=$n['rk']==='teacher'?[(int)$n['scope'],0]:role_place_ids($n['rk'],(int)$n['scope']);[$cs,$cm]=$pl;$cd=(int)($pl[2]??0);
   $card='<div class="org-newcard">'.staff_id_card(['school'=>$cs,'muni'=>$cm,'district'=>$cd,'name'=>(string)$n['name'],'role'=>ROLES[$n['rk']][0]??'Teacher','place'=>rbac_scope_name($n['rk']==='teacher'?'school_admin':$n['rk'],(int)$n['scope']),'id'=>$n['id'],'pw'=>$n['pw'],'qr'=>$n['rk']==='teacher'&&function_exists('card_info')?(($ci=card_info((int)$n['uid']))['key']?'?page=login&key='.rawurlencode($ci['key']):'?page=login&id='.rawurlencode((string)$n['id'])):staff_qr((int)$n['uid'],(string)$n['id']),'pic'=>staff_pic((int)$n['uid'],$n['rk']==='teacher'?'teacher':'admin'),'scan'=>'Scan to sign in','n'=>1]).'</div><script src="'.asset_url('qrcode.js').'" defer></script><script src="'.asset_url('cards.js').'" defer></script>';}
  echo '<div class="role-pop org-newpop" role="dialog" aria-modal="true" aria-labelledby="on-t"><div class="role-pop-box card org-new'.($card!==''?' has-card':'').'">'.$card.'<div class="org-newtx">'.icon('check').'<h2 id="on-t">'.e($n['title']??'Account created').'</h2><p><b>'.e($n['name']).'</b> · '.e($n['role']).'</p>'.($card!==''?'<p>Their ID and starting password are on the ID card. Print it, or write them down now.</p>':'<p>Give these to them. Write them down now.</p><dl><dt>ID</dt><dd>'.e($n['id']).'</dd><dt>Password</dt><dd>'.e($n['pw']).'</dd></dl>').'<p class="muted">They can change the password after signing in.</p><div class="org-newbtns">'.(isset(ROLES[$n['rk']??''])?'<a class="btn secondary" href="?page=admin_passes&amp;id='.e($n['id']).'">'.icon('print').'Print their ID card</a>':(($n['rk']??'')==='teacher'?'<a class="btn secondary" href="?page=teacher_cards&amp;t='.(int)$n['uid'].'">'.icon('print').'Print their ID card</a>':'')).'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="org_new_ok"><button class="btn primary">'.icon('check').'I saved them</button></form></div></div></div></div>';}
 $mk=array_merge($can,ROLE_CREATES[$r]??[]);
 if($mk&&!$view){$scopes=org_scope_options($u);if(isset(ROLE_CREATES[$r]))$scopes=['school'=>[(int)$u['scope_id']=>rbac_scope_name($r,(int)$u['scope_id'])]];
  echo '<form method="post" class="org-addp" id="org-create">'.csrf_field().'<input type="hidden" name="action" value="org_add">'
   .'<p class="org-step"><span>1</span>Choose the role and where</p><div class="org-grid"><label>Role<select name="role" required data-org-role>';
  foreach($mk as $k)echo '<option value="'.$k.'" data-level="'.ROLES[$k][3].'" data-new="'.(in_array($k,ROLE_CREATES[$r]??[],true)?$k:'admin').'">'.e(ROLES[$k][0]).(in_array($k,ROLE_VIEWERS,true)?' (view only)':'').'</option>';
  echo '</select></label><label>For<select name="scope" required data-org-scope>';foreach($scopes as $lv=>$opts)foreach($opts as $id=>$n)echo '<option value="'.$lv.':'.$id.'" data-level="'.$lv.'">'.e($n).'</option>';
  echo '</select></label></div><p class="org-step"><span>2</span>Who is it?</p>';
  if($can)echo '<div class="org-who"><label class="org-opt"><input type="radio" name="mode" value="new" checked><span><b>A new person</b><small>BULIG Quest makes their ID and password.</small></span></label><label class="org-opt"><input type="radio" name="mode" value="existing"><span><b>Already has a BULIG Quest account</b><small>For example, a teacher who will also be School Admin. They keep one account.</small></span></label></div>';
  else echo '<input type="hidden" name="mode" value="new">';
  echo '<div class="org-f-new">'.str_replace(' required','',name_fields()).'<div class="org-grid"><label data-for-new>Male or female<select name="sex"><option value="female">Female</option><option value="male">Male</option></select></label></div>';
  if(isset(ROLE_CREATES[$r])){$secs=rows("SELECT s.id,s.grade_level,s.name,t.name tn FROM sections s JOIN users t ON t.id=s.teacher_id AND t.active=1 WHERE s.archived_at IS NULL AND t.school_id=? ORDER BY s.grade_level,s.name",[(int)$u['scope_id']]);
   echo '<div class="org-grid"><label data-for="pupil">Pupil’s class<select name="section"><option value="0">Choose a class</option>';foreach($secs as $x)echo '<option value="'.(int)$x['id'].'">Grade '.(int)$x['grade_level'].' · '.e($x['name']).' · '.e(name_case((string)$x['tn'])).'</option>';echo '</select></label></div>';}
  echo '</div>';
  if($can)echo '<div class="org-f-ex"><label>Their BULIG Quest ID or name<input name="public_id" maxlength="30" placeholder="Type an ID or a name, e.g. TCEST0001" autocapitalize="characters" autocomplete="off" data-org-lookup role="combobox" aria-expanded="false" aria-controls="org-lk" aria-autocomplete="list"></label><div class="org-lk" id="org-lk" role="listbox" hidden></div></div>';
  echo '<button class="btn primary">'.icon('check').'Add to this role</button></form>';}
 echo '<div class="ws-hd org-ph"><h2>'.icon('people').'People and their roles</h2>'.($below&&!$view?'<a class="btn secondary small" href="?page=admin_passes">'.icon('print').'Print passes</a>':'<span class="rs-sub">'.count($rows).'</span>').'</div><p class="muted">One person can hold several roles with one account. They choose a role after signing in, and switch from the menu.</p><div class="org-list">';
 $by=[];foreach($rows as $x)$by[(int)$x['user_id']][]=$x;
 foreach($by as $uid=>$xs){$self=$uid===(int)$u['id'];
  $mine=!$view&&!$self&&(bool)array_filter($xs,fn($x)=>in_array($x['role'],$below,true)&&org_in_my_scope($u,$x['role'],(int)$x['scope_id']));
  echo '<div class="org-prow"><span class="org-pn"><b>'.e(name_case((string)$xs[0]['name'])).'</b><small>'.e($xs[0]['public_id']).'</small>'.($mine?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="org_reset_pw"><input type="hidden" name="user" value="'.$uid.'"><button class="linkbutton org-pw" data-confirm="Make a new password for '.e(name_case((string)$xs[0]['name'])).'? The old password stops working.">'.icon('key').'New password</button></form>':'').'</span><span class="org-rc">';
  foreach($xs as $x){$rm=!$view&&!$self&&in_array($x['role'],$below,true)&&org_in_my_scope($u,$x['role'],(int)$x['scope_id']);
   echo '<span class="org-chip'.(in_array($x['role'],ROLE_VIEWERS,true)?' v':'').'">'.e(ROLES[$x['role']][0]).($x['scope_id']?'<small>'.e(rbac_scope_name($x['role'],(int)$x['scope_id'])).'</small>':'')
    .($rm?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="org_unappoint"><input type="hidden" name="id" value="'.(int)$x['id'].'"><button class="org-x" aria-label="Remove this role" title="Remove this role" data-confirm="Remove this role from '.e($x['name']).'? Their account and data stay.">'.icon('close').'</button></form>':'').'</span>';}
  echo '</span></div>';}
 if(!$by)echo '<p class="muted">No one yet.</p>';
 echo '</div></section>';
}
/** v211 The hierarchy as a tree: Division Admin → municipalities → districts → schools, with who holds each role
    and how many teachers and pupils each school has. Each role sees its own part. Adding and removing
    municipalities and districts happens right in the tree. */
function org_chart(array $u,array $munis):string{
 $who=[];foreach(rows('SELECT ur.role,ur.scope_id,u.name FROM user_roles ur JOIN users u ON u.id=ur.user_id AND u.active=1') as $x)$who[$x['role'].':'.$x['scope_id']][]=name_case((string)$x['name']);
 foreach(rows("SELECT u.name FROM users u WHERE u.role='admin' AND u.active=1 AND NOT EXISTS(SELECT 1 FROM user_roles x WHERE x.user_id=u.id)") as $x)$who['central_admin:0'][]=name_case((string)$x['name']);   // administrators from before the update
 $names=fn($k)=>isset($who[$k])?e(implode(', ',array_unique($who[$k]))):'<i>Not yet appointed</i>';
 $cnt=[];foreach(rows("SELECT school_id,SUM(role='teacher') t,SUM(role='pupil') p FROM users WHERE active=1 AND school_id IS NOT NULL GROUP BY school_id") as $x)$cnt[(int)$x['school_id']]=$x;
 $sch=rbac_my_schools();$r=$u['active_role'];$view=!empty($u['view_only']);$central=$r==='central_admin';
 $h='<section class="card oc" aria-label="Organization chart"><div class="oc-root">'.icon('shield').'<div><small>Division Admin</small><b>Entire Division</b><span>'.$names('central_admin:0').'</span></div></div><div class="oc-tree">';
 foreach($munis as $m){$mid=(int)$m['id'];
  $ds=rows('SELECT * FROM districts WHERE municipality_id=?'.(ROLES[$r][3]==='district'?' AND id='.(int)$u['scope_id']:'').' ORDER BY name',[$mid]);
  $dh='';$ns=0;$alsHead=false;
  usort($ds,fn($a,$b)=>(is_als_district((string)$a['name'])<=>is_als_district((string)$b['name']))?:strnatcasecmp((string)$a['name'],(string)$b['name']));   // v212 regular districts, then the ALS districts
  foreach($ds as $d){$did=(int)$d['id'];
   if(!$alsHead&&is_als_district((string)$d['name'])){$alsHead=true;$dh.='<h3 class="oc-als-h">'.icon('book').'ALS districts <small>Alternative Learning System</small></h3>';}$ss=rows('SELECT id,code,name FROM schools WHERE district_id=? AND active=1'.($sch===null?'':($sch?' AND id IN ('.implode(',',$sch).')':' AND 0')).' ORDER BY name',[$did]);$ns+=count($ss);
   $short=trim(preg_replace('/^'.preg_quote($m['name'],'/').'\s*/u','',$d['name']))?:$d['name'];
   $dh.='<article class="oc-d"><header><b>'.e($short).'</b><span>'.count($ss).' '.(count($ss)===1?'school':'schools').'</span></header><dl class="oc-ppl"><dt>District Admin</dt><dd>'.$names('district_admin:'.$did).'</dd><dt>Supervisor '.icon('eye').'</dt><dd>'.$names('district_supervisor:'.$did).'</dd></dl>'
    .(!$view&&($central||($r==='municipal_admin'&&(int)$u['scope_id']===$mid)||($r==='district_admin'&&(int)$u['scope_id']===$did))?'<form method="post" enctype="multipart/form-data" class="oc-logo oc-dlogo">'.csrf_field().'<input type="hidden" name="action" value="org_district_logo"><input type="hidden" name="id" value="'.$did.'">'.(district_logo($did)!==''?'<img src="'.e(district_logo($did)).'" alt="">':'').'<label>District logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required></label><button class="btn secondary small">'.icon('image').'Upload</button></form>':'').'<ul class="oc-ss">';
   foreach($ss as $x){$sid=(int)$x['id'];$c=$cnt[$sid]??['t'=>0,'p'=>0];
    $dh.='<li><a href="?page=school&amp;id='.$sid.'"><b>'.e($x['name']?:$x['code']).'</b>'.(function_exists('school_type')?'<span class="sc-lv sc-lv-'.school_type($sid).'">'.e(school_type_label(school_type($sid))).'</span>':'').'<small>School Admin: '.$names('school_admin:'.$sid).'</small><small>Principal: '.$names('principal:'.$sid).'</small><span class="oc-n"><em>'.icon('people').(int)$c['t'].'</em><em>'.icon('user').number_format((int)$c['p']).'</em></span></a></li>';}
   if(!$ss)$dh.='<li class="oc-none">No schools yet</li>';
   $dh.='</ul></article>';}
  if(!$view&&($central||($r==='municipal_admin'&&(int)$u['scope_id']===$mid)))$dh.='<form method="post" class="oc-d oc-add">'.csrf_field().'<input type="hidden" name="action" value="org_district_add"><input type="hidden" name="municipality" value="'.$mid.'"><label>New district<input name="name" maxlength="120" required placeholder="'.e($m['name']).' District '.roman(count($ds)+1).'"></label><button class="btn secondary small">'.icon('check').'Add district</button></form>';
  $h.='<details class="oc-m" open><summary>'.icon('school').'<span class="oc-mt"><b>'.e($m['name']).'</b><small>'.count($ds).' '.(count($ds)===1?'district':'districts').' · '.$ns.' '.($ns===1?'school':'schools').'</small></span><span class="oc-chips"><span><small>Municipal Admin</small>'.$names('municipal_admin:'.$mid).'</span><span><small>Viewer</small>'.$names('municipal_viewer:'.$mid).'</span></span></summary><div class="oc-ds">'.($ds||$dh?$dh:'<p class="oc-none">No districts yet.</p>').'</div>'
   .(!$view&&($central||($r==='municipal_admin'&&(int)$u['scope_id']===$mid))?'<form method="post" enctype="multipart/form-data" class="oc-logo">'.csrf_field().'<input type="hidden" name="action" value="org_muni_logo"><input type="hidden" name="id" value="'.$mid.'">'.(muni_logo($mid)!==''?'<img src="'.e(muni_logo($mid)).'" alt="">':'').'<label>'.e($m['name']).' logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required></label><button class="btn secondary small">'.icon('image').'Upload logo</button></form>':'')
   .($central&&!$view?'<form method="post" class="oc-rm">'.csrf_field().'<input type="hidden" name="action" value="org_muni_delete"><input type="hidden" name="id" value="'.$mid.'"><button class="linkbutton" data-confirm="Remove '.e($m['name']).'? Its districts are removed too. Schools stay; they go back to the review list.">'.icon('trash').'Remove '.e($m['name']).'</button></form>':'').'</details>';}
 if(!$munis)$h.='<p class="oc-none">No municipality in your area yet.</p>';
 $h.='</div>';
 if($central&&!$view)$h.='<form method="post" class="oc-addm">'.csrf_field().'<input type="hidden" name="action" value="org_muni_add"><b>'.icon('school').'Add a municipality</b><label>Municipality<input name="name" maxlength="80" required placeholder="e.g. Sumilao"></label><label>Districts<input name="n" type="number" min="1" max="10" value="1" required></label><button class="btn primary small">'.icon('check').'Add</button></form>';
 return $h.'</section>';
}
/** Is a role at this place inside the active role's area? */
function org_in_my_scope(array $u,string $role,int $scope):bool{
 $r=$u['active_role'];if($r==='central_admin'||(ROLES[$r][3]??'')==='system')return true;$lv=ROLES[$role][3]??'';$me=(int)$u['scope_id'];   // v216 the Division Viewer looks at the whole division (view only: it changes nothing)
 if(ROLES[$r][3]==='municipality'){if($lv==='municipality')return $scope===$me;if($lv==='district')return (int)val('SELECT municipality_id FROM districts WHERE id=?',[$scope])===$me;return in_array($scope,$u['scope_schools']??[],true);}
 if(ROLES[$r][3]==='district'){if($lv==='district')return $scope===$me;if($lv==='school')return in_array($scope,$u['scope_schools']??[],true);return false;}
 if(ROLES[$r][3]==='school')return $lv==='school'&&$scope===$me;
 return false;
}
/** The places the active role may appoint people to, by level. */
function org_scope_options(array $u):array{
 $o=['system'=>$u['active_role']==='central_admin'?[0=>'Entire division']:[],'municipality'=>[],'district'=>[],'school'=>[]];
 foreach(org_my_munis($u) as $m)if($u['active_role']==='central_admin')$o['municipality'][(int)$m['id']]=$m['name'];
 $ds=rows('SELECT d.id,d.name,d.municipality_id FROM districts d ORDER BY d.name');foreach($ds as $d)if(org_in_my_scope($u,'district_admin',(int)$d['id']))$o['district'][(int)$d['id']]=$d['name'];
 foreach(rows('SELECT id,name,code FROM schools WHERE active=1 ORDER BY name') as $s)if(org_in_my_scope($u,'school_admin',(int)$s['id']))$o['school'][(int)$s['id']]=$s['name']?:$s['code'];
 return array_filter($o);
}
function org_action(string $action):never{
 $u=current_user();if(!$u||($u['role']??'')!=='admin')fail('Not allowed.',403);$r=(string)($u['active_role']??'');
 if($action==='org_district_logo'){$did=(int)($_POST['id']??0);$mid=(int)(val('SELECT municipality_id FROM districts WHERE id=?',[$did])?:0);if(!$mid)fail('District not found.',404);
  if(!($r==='central_admin'||($r==='municipal_admin'&&(int)$u['scope_id']===$mid)||($r==='district_admin'&&(int)$u['scope_id']===$did)))fail('Not allowed.',403);
  $f=save_uploaded_image('logo');$old=district_logo($did);admin_set('district_logo_'.$did,$f);if($old!==''&&str_starts_with($old,'assets/uploads/')&&$old!==$f)@unlink(__DIR__.'/../public/'.$old);
  audit('org_district_logo',(string)$did);flash('District logo saved. It prints on the ID cards.');go('?page=org');}
 if($action==='org_muni_logo'){$mid=(int)($_POST['id']??0);if(!($r==='central_admin'||($r==='municipal_admin'&&(int)$u['scope_id']===$mid)))fail('Not allowed.',403);
  if(!val('SELECT 1 FROM municipalities WHERE id=?',[$mid]))fail('Municipality not found.',404);$f=save_uploaded_image('logo');$old=muni_logo($mid);
  admin_set('muni_logo_'.$mid,$f);if($old!==''&&str_starts_with($old,'assets/uploads/')&&$old!==$f)@unlink(__DIR__.'/../public/'.$old);audit('org_muni_logo',(string)$mid);flash('Municipality logo saved. It prints on the ID cards.');go('?page=org');}
 if($action==='org_id_design'){if($r!=='central_admin')fail('Only the Division Admin chooses the ID design.',403);$d=(string)($_POST['design']??'b');if(!isset(ID_THEMES[$d])&&$d!=='r')$d='b';admin_set('id_design',$d);flash('ID card design saved.');go((string)($_POST['back']??'')==='teacher_cards'?'?page=teacher_cards':'?page=admin_passes');}
 if($action==='org_new_ok'){unset($_SESSION['org_new']);go('?page=org#org-people');}
 if($action==='org_reset_pw'){$t=(int)($_POST['user']??0);if($t===(int)$u['id'])fail('Change your own password in My profile.',403);
  $ok=false;foreach(rows('SELECT role,scope_id FROM user_roles WHERE user_id=?',[$t]) as $x)if(in_array($x['role'],role_below($r),true)&&org_in_my_scope($u,$x['role'],(int)$x['scope_id']))$ok=true;
  $p=one("SELECT id,name,public_id FROM users WHERE id=? AND role IN ('admin','teacher') AND active=1",[$t]);if(!$ok||!$p)fail('You can make a new password only for the people you manage.',403);
  $pw=substr(str_replace(['+','/','=','0','O','l','I'],'',base64_encode(random_bytes(12))),0,10);
  q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$t]);try{q('DELETE FROM remember_tokens WHERE user_id=?',[$t]);}catch(Throwable $e){}if(function_exists('saved_login_clear'))saved_login_clear($t);
  rbac_keep_starter($t,$pw);audit('org_reset_pw',(string)$p['public_id']);$fr=one('SELECT role,scope_id FROM user_roles WHERE user_id=? ORDER BY id LIMIT 1',[$t]);$_SESSION['org_new']=['title'=>'New password made','name'=>name_case((string)$p['name']),'role'=>$fr?ROLES[$fr['role']][0].' · '.rbac_scope_name($fr['role'],(int)$fr['scope_id']):'BULIG Quest account','id'=>$p['public_id'],'pw'=>$pw,'uid'=>$t,'rk'=>$fr['role']??'','scope'=>(int)($fr['scope_id']??0)];go('?page=org#org-people');}
 if($action==='org_add')$action=((string)($_POST['mode']??'new'))==='existing'?'org_appoint':'org_create';   // v211 one form: a new person or an existing account
 $u=current_user();if(!$u||($u['role']??'')!=='admin')fail('Not allowed.',403);if(!rbac_ready())fail('Import 040_hierarchy_roles.sql first.');
 $r=$u['active_role'];$name=fn($k)=>name_case(mb_substr(trim((string)($_POST[$k]??'')),0,120));
 if($action==='org_muni_add'){$m=$name('name');if($m==='')fail('Type the municipality.');q('INSERT IGNORE INTO municipalities(name) VALUES(?)',[$m]);$id=(int)val('SELECT id FROM municipalities WHERE name=?',[$m]);
  $n=max(1,min(10,(int)($_POST['n']??1)));foreach(muni_districts($m,$n) as $d)q('INSERT IGNORE INTO districts(municipality_id,name) VALUES(?,?)',[$id,$d]);audit('org_muni_add',$m);flash($m.' is added with '.$n.' '.($n===1?'district':'districts').'.');go('?page=org');}
 if($action==='org_muni_delete'){$id=(int)($_POST['id']??0);q('UPDATE schools SET district_id=NULL WHERE district_id IN (SELECT id FROM districts WHERE municipality_id=?)',[$id]);q("DELETE FROM user_roles WHERE role IN ('municipal_admin','municipal_viewer') AND scope_id=?",[$id]);q("DELETE FROM user_roles WHERE role IN ('district_admin','district_supervisor') AND scope_id IN (SELECT id FROM districts WHERE municipality_id=?)",[$id]);q('DELETE FROM municipalities WHERE id=?',[$id]);audit('org_muni_delete',(string)$id);flash('Municipality removed. Its schools are back in the review list.');go('?page=org');}
 if($action==='org_district_add'){$m=(int)($_POST['municipality']??0);if(!($r==='central_admin'||($r==='municipal_admin'&&(int)$u['scope_id']===$m)))fail('Not allowed.',403);$d=$name('name');if($d==='')fail('Type the district.');q('INSERT IGNORE INTO districts(municipality_id,name) VALUES(?,?)',[$m,$d]);audit('org_district_add',$d);flash($d.' is added.');go('?page=org');}
 if($action==='org_school_district'){$s=(int)($_POST['school']??0);$d=(int)($_POST['district']??0);
  if(!in_array($r,['central_admin','municipal_admin','district_admin'],true))fail('Not allowed.',403);
  if($r!=='central_admin'&&!org_in_my_scope($u,'district_admin',$d))fail('Choose a district in your area.',403);
  if($r!=='central_admin'&&!in_array($s,$u['scope_schools']??[],true)&&!(int)val('SELECT 1 FROM schools WHERE id=? AND district_id IS NULL',[$s]))fail('This school is not in your area.',403);
  $dn=$d?(string)val('SELECT name FROM districts WHERE id=?',[$d]):'';if($d&&$dn==='')fail('District not found.',404);
  q('UPDATE schools SET district_id=?,district=? WHERE id=?',[$d?:null,$dn!==''?$dn:(string)val('SELECT district FROM schools WHERE id=?',[$s]),$s]);audit('org_school_district',$s.' → '.($dn?:'none'));flash('Saved.');go('?page=org#org-review');}
 if($action==='org_appoint'){$role=(string)($_POST['role']??'');if(!in_array($role,ROLE_APPOINTS[$r]??[],true))fail('You cannot appoint this role.',403);
  [$lv,$sid]=array_pad(explode(':',(string)($_POST['scope']??'')),2,'0');$sid=(int)$sid;if($lv!==ROLES[$role][3])fail('Choose a '.ROLES[$role][3].' for this role.');
  if(!org_in_my_scope($u,$role,$sid))fail('Choose a place inside your area.',403);
  $p=one("SELECT id,name,role,active FROM users WHERE public_id=? AND (active=1 OR role='admin')",[strtoupper(trim((string)($_POST['public_id']??'')))]);if(!$p)fail('No active account has that ID.',404);
  if($p['role']==='pupil')fail('Pupils cannot hold administrative roles.',403);if((int)$p['id']===(int)$u['id'])fail('You cannot appoint yourself.',403);
  q('INSERT IGNORE INTO user_roles(user_id,role,scope_id,appointed_by) VALUES(?,?,?,?)',[$p['id'],$role,$sid,$u['id']]);
  if(!(int)$p['active'])q('UPDATE users SET active=1 WHERE id=?',[$p['id']]);   // v216 an administrator account turned off when its last role was removed is turned back on
  notify((int)$p['id'],'note','You have a new role in BULIG Quest','You are now '.ROLES[$role][0].' · '.rbac_scope_name($role,$sid).'. Choose it from the menu (Switch role).','?page=role');
  audit('org_appoint',$p['name'].' → '.$role.':'.$sid);flash(name_case((string)$p['name']).' is now '.ROLES[$role][0].'.');go('?page=org#org-people');}
 if($action==='org_create'&&in_array((string)($_POST['role']??''),ROLE_CREATES[$r]??[],true))org_create_school_account($u);
 if($action==='org_create'){$role=(string)($_POST['role']??'');if(!in_array($role,ROLE_APPOINTS[$r]??[],true))fail('You cannot create this role.',403);
  [$lv,$sid]=array_pad(explode(':',(string)($_POST['scope']??'')),2,'0');$sid=(int)$sid;if($lv!==ROLES[$role][3])fail('Choose a '.ROLES[$role][3].' for this role.');
  if(!org_in_my_scope($u,$role,$sid))fail('Choose a place inside your area.',403);
  $ni=name_input();$nm=name_case(mb_substr($ni['name'],0,150));if(mb_strlen($nm)<3)fail('Type the person’s full name.');
  dupe_guard($nm,$role,0,'?page=org#org-people');   // v211 same name already in BULIG?
  $pw=substr(str_replace(['+','/','=','0','O','l','I'],'',base64_encode(random_bytes(12))),0,10);
  db()->beginTransaction();try{$pid=rbac_account_id($role);
   q('INSERT INTO users(public_id,role,name,password_hash,school_id) VALUES(?,?,?,?,?)',[$pid,'admin',$nm,password_hash($pw,PASSWORD_DEFAULT),$lv==='school'?$sid:null]);$id=(int)db()->lastInsertId();
   q('INSERT INTO admins VALUES(?)',[$id]);name_parts_save($id,$ni['parts']);rbac_keep_starter($id,$pw);if(function_exists('tf_set_sex'))tf_set_sex($id,(string)($_POST['sex']??''));q('INSERT INTO user_roles(user_id,role,scope_id,appointed_by) VALUES(?,?,?,?)',[$id,$role,$sid,$u['id']]);
   audit('org_create',$pid.' '.$role.':'.$sid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  $_SESSION['org_new']=['name'=>$nm,'role'=>ROLES[$role][0].' · '.rbac_scope_name($role,$sid),'id'=>$pid,'pw'=>$pw,'uid'=>$id,'rk'=>$role,'scope'=>$sid];go('?page=org#org-people');}
 if($action==='org_unappoint'){$x=one('SELECT * FROM user_roles WHERE id=?',[(int)($_POST['id']??0)]);if(!$x)fail('Not found.',404);
  if((int)$x['user_id']===(int)$u['id']||!in_array($x['role'],role_below($r),true)||!org_in_my_scope($u,$x['role'],(int)$x['scope_id']))fail('You cannot remove this role.',403);
  q('DELETE FROM user_roles WHERE id=?',[$x['id']]);audit('org_unappoint',$x['user_id'].' '.$x['role'].':'.$x['scope_id']);
  /* v216 an administrator account with no role left is turned off (an admin account with no roles would otherwise act
     like an administrator from before the roles update, with full access). A teacher keeps the teacher account. */
  $acc=one('SELECT role,name FROM users WHERE id=?',[$x['user_id']]);
  if($acc&&$acc['role']==='admin'&&!val('SELECT COUNT(*) FROM user_roles WHERE user_id=?',[$x['user_id']])){q('UPDATE users SET active=0 WHERE id=?',[$x['user_id']]);try{q('DELETE FROM remember_tokens WHERE user_id=?',[$x['user_id']]);}catch(Throwable $e){}if(function_exists('saved_login_clear'))saved_login_clear((int)$x['user_id']);audit('org_account_off',(string)$x['user_id']);
   flash(name_case((string)$acc['name']).' has no other role, so their account is turned off. Their records stay. Give them a role again with “Already has a BULIG Quest account” to turn it back on.');}
  else flash('Role removed. The account and its data stay.');
  go('?page=org#org-people');}
 if($action==='org_my_id')fail('IDs never change in BULIG Quest.',403);   // v211 removed
 fail('Unknown action.');
}

/** v211 School Admin: a new Teacher or Pupil account in their own school. Pupils join a teacher's class. */
function org_create_school_account(array $u):never{
 $role=(string)$_POST['role'];$sid=(int)$u['scope_id'];if(!$sid)fail('Your role has no school.',403);
 $ni=name_input();$nm=name_case(mb_substr($ni['name'],0,150));if(mb_strlen($nm)<3)fail('Type the person’s full name.');
 dupe_guard($nm,$role,$sid,'?page=org#org-people');   // v211 same name already in BULIG?
 db()->beginTransaction();try{
  if($role==='teacher'){if(!cards_ready())fail('Import database/migrations/018_pupil_cards.sql first.');$sx=(string)($_POST['sex']??'');if(!in_array($sx,['female','male'],true))fail('Choose Male or Female for the teacher.');
   $c=teacher_create($nm,$sx);name_parts_save($c['id'],$ni['parts']);school_mark_user($c['id'],$sid);$pid=$c['public'];$pw=$c['pw'];}
  else{$sec=one('SELECT s.* FROM sections s JOIN users t ON t.id=s.teacher_id WHERE s.id=? AND s.archived_at IS NULL AND t.school_id=?',[(int)($_POST['section']??0),$sid]);if(!$sec)fail('Choose a class in your school for the pupil.');
   $pid=new_account_id('pupil');q('INSERT INTO users(public_id,role,name,password_hash,school_id) VALUES(?,?,?,?,?)',[$pid,'pupil',$nm,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$sid]);$id=(int)db()->lastInsertId();
   name_parts_save($id,$ni['parts']);q('INSERT INTO pupils(user_id,grade_level,section) VALUES(?,?,?)',[$id,(int)$sec['grade_level'],$sec['name']]);q('INSERT INTO pupil_sections VALUES(?,?)',[$id,$sec['id']]);q('INSERT INTO teacher_pupils VALUES(?,?)',[$sec['teacher_id'],$id]);$pw=new_pupil_password($id);}
  audit('org_create',$pid.' '.$role.':'.$sid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 $_SESSION['org_new']=['name'=>$nm,'role'=>ROLES[$role][0].' · '.rbac_scope_name('school_admin',$sid),'id'=>$pid,'pw'=>$pw,'uid'=>$role==='teacher'?(int)$c['id']:0,'rk'=>$role,'scope'=>$sid];go('?page=org#org-people');
}

/* ---------- v210 view-only dashboard: Municipal Viewer, District Supervisor, School Principal ---------- */
function monitor_dashboard(array $u):void{
 $S=$u['scope_schools']??array_map('intval',array_column(rows('SELECT id FROM schools WHERE active=1'),'id'));$in=$S?implode(',',array_map('intval',$S)):'0';$r=$u['active_role'];$place=rbac_scope_name($r,(int)$u['scope_id']);$wk=date('Y-m-d',strtotime('-6 days'));
 $n=fn($sql)=>(int)(val($sql)?:0);
 $pupils=$n("SELECT COUNT(*) FROM users WHERE role='pupil' AND active=1 AND school_id IN ($in)");$teachers=$n("SELECT COUNT(*) FROM users WHERE role='teacher' AND active=1 AND school_id IN ($in)");
 $active=$n("SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN users u ON u.id=d.pupil_id WHERE u.school_id IN ($in) AND d.day>='$wk'");
 $lessons=$n("SELECT COUNT(*) FROM pupil_progress p JOIN users u ON u.id=p.pupil_id WHERE u.school_id IN ($in) AND p.completed_at>='$wk'");
 $wait=rv_ready()?$n("SELECT COUNT(*) FROM reading_verifications r JOIN users u ON u.id=r.pupil_id WHERE u.school_id IN ($in) AND r.state IN ('pending','missing')"):0;
 $vo=!empty($u['view_only']);
 echo '<div class="mon-head"><div><span class="eyebrow">'.e(mb_strtoupper(ROLES[$r][0])).($vo?' · VIEW ONLY':' · DASHBOARD').'</span><h1>'.e($place!==''?$place:'My area').'</h1><p class="muted">'.($vo?'Reading progress in your area. You can look and compare; changes are made by the teachers and administrators.':'Create and manage the accounts under you, and follow reading progress in your area.').'</p></div>'.($vo?'<span class="mon-badge">'.icon('eye').'View only</span>':'').'</div>';
 if(function_exists('announcements_board')&&($ann=announcements_for($u))||(!$vo&&rbac_can('announce')))echo announcements_board($ann,!$vo&&rbac_can('announce')?'?page=settings&tab=announcements':'');   // v212 bulletin board
 if(!$vo)echo role_manage_panel($u);
 echo admin_kpis([['school',count($S),count($S)===1?'School':'Schools','',false],['user',$teachers,'Teachers','',false],['people',number_format($pupils),'Pupils','',false],['fire',number_format($active),'Learned this week',$pupils?round($active/$pupils*100).'% of pupils':'',false],['book',number_format($lessons),'Lessons finished this week','',false],['mic',number_format($wait),'Readings waiting for teachers','',false]]);
 /* pupils by reading level */
 $lv=rows("SELECT a.level_id,COUNT(*) n FROM pupil_level_assignments a JOIN users u ON u.id=a.pupil_id AND u.active=1 WHERE u.school_id IN ($in) GROUP BY a.level_id");$tot=array_sum(array_column($lv,'n'))?:1;
 usort($lv,fn($a,$b)=>level_rank((int)$a['level_id'])<=>level_rank((int)$b['level_id']));
 echo '<section class="card mon-lv"><h2>'.icon('ladder').'Pupils by reading level</h2>';
 foreach($lv as $x){$p=round($x['n']/$tot*100);echo '<div class="mon-bar"><span>'.e((int)$x['level_id']?level_label((int)$x['level_id']):'Reading check').'</span><i><b data-w="'.$p.'"></b></i><em>'.number_format((int)$x['n']).'</em></div>';}
 if(!$lv)echo '<p class="muted">No pupils yet.</p>';echo '</section>';
 /* comparison: schools (municipal, district) or teachers (principal) */
 if($r==='principal'||$r==='school_admin'){
  $rows=rows("SELECT t.name,(SELECT COUNT(*) FROM teacher_pupils tp WHERE tp.teacher_id=t.id) pupils,(SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN teacher_pupils tp ON tp.pupil_id=d.pupil_id WHERE tp.teacher_id=t.id AND d.day>='$wk') act,(SELECT COUNT(*) FROM pupil_progress p JOIN teacher_pupils tp ON tp.pupil_id=p.pupil_id WHERE tp.teacher_id=t.id AND p.completed_at IS NOT NULL) done".(rv_ready()?",(SELECT COUNT(*) FROM reading_verifications rv JOIN teacher_pupils tp ON tp.pupil_id=rv.pupil_id WHERE tp.teacher_id=t.id AND rv.state IN ('pending','missing')) wait":",0 wait")." FROM users t WHERE t.role='teacher' AND t.active=1 AND t.school_id IN ($in) ORDER BY t.name");
  $head=['Teacher','Pupils','Learned this week','Lessons finished','Readings waiting'];
 }else{
  $rows=rows("SELECT COALESCE(NULLIF(s.name,''),s.code) name,(SELECT COUNT(*) FROM users u WHERE u.school_id=s.id AND u.role='pupil' AND u.active=1) pupils,(SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN users u ON u.id=d.pupil_id WHERE u.school_id=s.id AND d.day>='$wk') act,(SELECT COUNT(*) FROM pupil_progress p JOIN users u ON u.id=p.pupil_id WHERE u.school_id=s.id AND p.completed_at IS NOT NULL) done".(rv_ready()?",(SELECT COUNT(*) FROM reading_verifications rv JOIN users u ON u.id=rv.pupil_id WHERE u.school_id=s.id AND rv.state IN ('pending','missing')) wait":",0 wait").",(SELECT d.name FROM districts d WHERE d.id=s.district_id) district FROM schools s WHERE s.id IN ($in) ORDER BY district,name");
  $head=['School','Pupils','Learned this week','Lessons finished','Readings waiting'];
 }
 echo '<section class="card mon-cmp"><h2>'.icon('chart').(in_array($r,['principal','school_admin'],true)?'Teachers':'Schools').' compared</h2><div class="tablewrap"><table class="sp-tbl mon-tbl"><thead><tr>';foreach($head as $h)echo '<th>'.$h.'</th>';echo '</tr></thead><tbody>';
 foreach($rows as $x){$pct=(int)$x['pupils']?round($x['act']/$x['pupils']*100):0;
  echo '<tr><td><b>'.e(in_array($r,['principal','school_admin'],true)?name_case((string)$x['name']):(string)$x['name']).'</b>'.(!empty($x['district'])?'<small class="muted"> · '.e($x['district']).'</small>':'').'</td><td data-l="Pupils">'.number_format((int)$x['pupils']).'</td><td data-l="Learned this week">'.number_format((int)$x['act']).' <small class="muted">('.$pct.'%)</small></td><td data-l="Lessons finished">'.number_format((int)$x['done']).'</td><td data-l="Readings waiting">'.number_format((int)$x['wait']).'</td></tr>';}
 if(!$rows)echo '<tr><td colspan="5" class="muted">Nothing to show yet.</td></tr>';
 echo '</tbody></table></div></section>';
}
/** v211 The top of an admin role's dashboard: the accounts this role creates and manages, with counts and buttons. */
function role_manage_panel(array $u):string{
 $r=$u['active_role'];$mk=array_merge(ROLE_APPOINTS[$r]??[],ROLE_CREATES[$r]??[]);$S=$u['scope_schools'];$in=$S===null?'1':($S?'school_id IN ('.implode(',',array_map('intval',$S)).')':'0');
 $kp=[];foreach($mk as $k){
  if($k==='teacher'||$k==='pupil')$c=(int)val("SELECT COUNT(*) FROM users WHERE role=? AND active=1 AND $in",[$k]);
  else{$c=0;foreach(rows('SELECT scope_id FROM user_roles ur JOIN users x ON x.id=ur.user_id AND x.active=1 WHERE ur.role=?',[$k]) as $x)if(org_in_my_scope($u,$k,(int)$x['scope_id']))$c++;}
  $kp[]=[in_array($k,ROLE_VIEWERS,true)?'eye':($k==='pupil'?'user':($k==='teacher'?'people':'shield')),$c,ROLES[$k][0].($c===1?'':'s'),in_array($k,ROLE_VIEWERS,true)?'view only':'',false];}
 $wait=in_array($r,['central_admin','municipal_admin','district_admin'],true)?(int)val('SELECT COUNT(*) FROM schools WHERE active=1 AND district_id IS NULL'.($r==='central_admin'?'':' AND 0')):0;
 if($wait)$kp[]=['alert',$wait,'Schools waiting for a district','choose their district',true];
 $who=['central_admin'=>'Division Viewers, Municipal Admins, Municipal Viewers and Content Developers','municipal_admin'=>'District Admins and District Supervisors','district_admin'=>'School Admins and School Principals','school_admin'=>'Teachers and Pupils'][$r]??'';
 $idnote='';   // v211 IDs never change (the Get my ID button is removed)
 return '<section class="card mon-manage">'.$idnote.'<div class="ws-hd"><h2>'.icon('useredit').'Accounts you manage</h2></div><p class="muted">You create the '.e($who).' for your area. Each person gets an ID and a password from BULIG Quest.</p>'.admin_kpis($kp)
  .'<div class="ws-btns"><a class="btn primary" href="?page=org#org-create">'.icon('useredit').'Create an account</a><a class="btn secondary" href="?page=org#org-people">'.icon('people').'Give a role to an existing account</a>'.($r==='school_admin'?'<a class="btn secondary" href="?page=add_teachers">'.icon('people').'Add many teachers</a>':'').($wait?'<a class="btn secondary" href="?page=org#org-review">'.icon('school').'Review schools</a>':'').'<a class="btn secondary" href="?page=org">'.icon('school').'Organization chart</a></div></section>';
}

/* ---------- v211 Admin Passes: printable tickets for the accounts a role manages ---------- */
/** Keeps the starting password (encrypted, like Teacher Passes) so the pass can be printed later. */
function rbac_keep_starter(int $uid,string $pw):void{
 if(!function_exists('cards_ready')||!cards_ready())return;$key=rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'=');
 q('INSERT INTO pupil_cards(user_id,key_hash,key_enc,starter_enc,issued_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE key_hash=VALUES(key_hash),key_enc=VALUES(key_enc),starter_enc=VALUES(starter_enc),issued_at=NOW()',[$uid,hash('sha256',$key),cards_enc($key),cards_enc($pw)]);   // a new password also makes a new QR code: a lost card stops working
}
/** v211 The sign-in key in an administrator's QR code (made once, kept until a new password is made). */
function staff_card_key(int $uid):?string{
 if(!function_exists('cards_ready')||!cards_ready())return null;$enc=val('SELECT key_enc FROM pupil_cards WHERE user_id=?',[$uid]);
 if($enc){try{$k=cards_dec((string)$enc);if($k)return $k;}catch(Throwable $e){}}
 $k=rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'=');q('INSERT INTO pupil_cards(user_id,key_hash,key_enc,starter_enc,issued_at) VALUES(?,?,?,NULL,NOW()) ON DUPLICATE KEY UPDATE key_hash=VALUES(key_hash),key_enc=VALUES(key_enc)',[$uid,hash('sha256',$k),cards_enc($k)]);return $k;
}
function staff_qr(int $uid,string $pid):string{$k=staff_card_key($uid);return $k?'?page=login&key='.rawurlencode($k):'?page=login&id='.rawurlencode($pid);}
/** The starting password, only while it still works (after the person changes it, the pass says so). */
function rbac_starter(int $uid):?string{
 if(!function_exists('cards_ready')||!cards_ready())return null;$enc=val('SELECT starter_enc FROM pupil_cards WHERE user_id=?',[$uid]);if(!$enc)return null;
 try{$pw=cards_dec((string)$enc);}catch(Throwable $e){return null;}
 return $pw!==''&&password_verify($pw,(string)val('SELECT password_hash FROM users WHERE id=?',[$uid]))?$pw:null;
}
function admin_passes_view(array $u):void{
 $r=$u['active_role']??'';$can=role_below($r);$one=strtoupper(trim((string)($_GET['id']??'')));
 echo '<div class="pageheading ws-head no-print"><div><span class="eyebrow">ORGANIZATION</span><h1>Admin Passes</h1><p class="muted">A pass for each person you manage, with their ID, starting password and a QR code. Print, cut along the dashed lines and give each one their pass.</p></div><div class="ws-btns"><a class="btn secondary" href="?page=org">'.icon('arrow','flip').'Back to Organization</a></div></div>';
 $rows=$can?rows("SELECT ur.role,ur.scope_id,u.id,u.name,u.public_id FROM user_roles ur JOIN users x ON x.id=ur.user_id JOIN users u ON u.id=ur.user_id WHERE u.active=1 AND u.role IN ('admin','teacher') AND ur.role IN ('".implode("','",$can)."') ORDER BY FIELD(ur.role,'".implode("','",array_keys(ROLES))."'),u.name"):[];
 $rows=array_values(array_filter($rows,fn($x)=>org_in_my_scope($u,$x['role'],(int)$x['scope_id'])&&(int)$x['id']!==(int)$u['id']&&($one===''||strtoupper((string)$x['public_id'])===$one)));
 if(!$rows){echo '<section class="empty no-print">'.icon('people').'<h2>No passes yet.</h2><p>Add a person to a role on the Organization page, then come back to print their pass.</p></section>';return;}
 echo '<div class="lc-tools no-print card"><label class="lc-opt"><input type="checkbox" data-lc-toggle="lc-hide-qr" checked> QR code</label><label class="lc-opt"><input type="checkbox" data-lc-toggle="lc-hide-pass" checked> Starting password</label><button type="button" class="btn primary" data-lc-print>'.icon('download').'<span data-lc-print-label>Print passes</span></button></div>';
 echo id_design_picker('admin_passes').'<p class="lc-note no-print">The QR code signs the person in, even after they change their password. Lost a card? Use New password on the Organization page: the old password and the old QR code stop working.</p><div class="lc-sheet tk-sheet idc-sheet" data-lc-sheet data-tk-word="pass">';$n=0;
 foreach($rows as $a){$n++;$pw=rbac_starter((int)$a['id']);$qr=staff_qr((int)$a['id'],(string)$a['public_id']);$place=rbac_scope_name($a['role'],(int)$a['scope_id']);
  echo '<div class="tk-wrap idc-wrap" data-tk>'.staff_id_card(['school'=>role_place_ids($a['role'],(int)$a['scope_id'])[0],'muni'=>role_place_ids($a['role'],(int)$a['scope_id'])[1],'district'=>role_place_ids($a['role'],(int)$a['scope_id'])[2]??0,'name'=>name_case((string)$a['name']),'role'=>ROLES[$a['role']][0],'place'=>$place,'id'=>$a['public_id'],'pw'=>$pw,'qr'=>$qr,'pic'=>staff_pic((int)$a['id'],'admin'),'scan'=>'Scan to sign in','n'=>$n]).'<div class="tk-bar no-print"><label class="tk-sel"><input type="checkbox" data-tk-pick checked> Print</label></div></div>';}
 echo '</div><script src="'.asset_url('qrcode.js').'" defer></script><script src="'.asset_url('cards.js').'" defer></script>';
}

/** v211 The staff ID card (portrait, like a school ID) for administrators and teachers. Pupils keep their tickets.
    $o: name, role, place, id, pw (null = their own password), qr, pic, scan, n */
function staff_id_card(array $o):string{
 $d=id_design();if($d==='r')$d=role_theme((string)($o['role']??''));$logos=id_logos((int)($o['school']??0),(int)($o['muni']??0),(int)($o['district']??0));
 return '<article class="idc idc-b'.id_theme_class($d).(!empty($o['kid'])?' idc-kid':'').'"><header class="idc-top"><span class="idc-logos">'.$logos.'</span><span class="idc-org">Republic of the Philippines<br><b>Department of Education</b><br>Schools Division of Bukidnon</span><img class="idc-bulig" src="assets/brand/bulig-quest.webp" alt="BULIG Quest"></header>'
  .'<div class="idc-photo"><img src="'.e($o['pic']).'" alt=""></div><strong class="idc-name'.(mb_strlen((string)$o['name'])>24?' sm':'').'">'.e($o['name']).'</strong>'
  .'<span class="idc-role">'.e(mb_strtoupper((string)$o['role'])).'</span>'.(($o['place']??'')!==''?'<span class="idc-place">'.e($o['place']).'</span>':'')
  .'<div class="idc-row"><span class="lc-qr tk-qr idc-qr" data-qr="'.e($o['qr']).'" aria-hidden="true"></span><dl><dt>'.e($o['idlab']??'ID').'</dt><dd class="idc-id">'.e($o['id']).'</dd><span class="tk-pass"><dt>'.($o['pw']!==null?'Starting password':'Password').'</dt><dd class="idc-pw'.($o['pw']===null?' own':'').'">'.e($o['pw']??'Your own password').'</dd></span></dl></div>'
  .'<footer class="idc-foot"><span class="tk-scan">'.e($o['scan']).'</span><span>No. '.str_pad((string)$o['n'],3,'0',STR_PAD_LEFT).'</span></footer></article>';
}

/** v211 The municipality's logo (Organization page), kept in settings; no SQL needed. */
function muni_logo(int $mid):string{if(!$mid||!function_exists('admin_setting'))return '';$f=(string)admin_setting('muni_logo_'.$mid,'');return $f!==''&&is_file(__DIR__.'/../public/'.$f)?$f:'';}
function district_logo(int $did):string{if(!$did||!function_exists('admin_setting'))return '';$f=(string)admin_setting('district_logo_'.$did,'');return $f!==''&&is_file(__DIR__.'/../public/'.$f)?$f:'';}
/** ID cards per A4 page for each size (Small, Medium, Large); the landscape design fits fewer. */
function idc_per():array{return id_design()==='c'?['s'=>12,'m'=>8,'l'=>8]:['s'=>16,'m'=>9,'l'=>6];}
/** v216 Card designs: the Green layout in different colours (ID cards for staff, and the pupils' Reading Pass tickets). */
const ID_THEMES=['b'=>'Green','d'=>'Ocean Blue','e'=>'Maroon and Gold','f'=>'Sunset','g'=>'Royal Violet','h'=>'Teal Lagoon','i'=>'Night Sky'];
function id_design():string{return 'r';}   // v216 one design: ID cards are coloured by role
/** v216 "By role": each level has its own colour, so a card shows at a glance who it belongs to. */
const ROLE_THEMES=['central_admin'=>'i','division_viewer'=>'i','municipal_admin'=>'e','municipal_viewer'=>'e','district_admin'=>'g','district_supervisor'=>'g','school_admin'=>'d','principal'=>'d','content_dev'=>'f','teacher'=>'b'];
/** The colour for a role, from its key or its name on the card (Green when unknown). */
function role_theme(string $role):string{if(isset(ROLE_THEMES[$role]))return ROLE_THEMES[$role];foreach(ROLES as $k=>$x)if(strcasecmp($x[0],$role)===0)return ROLE_THEMES[$k]??'b';return 'b';}
/** The theme class for a design ('' for Green). */
function id_theme_class(string $d):string{return $d!=='b'&&isset(ID_THEMES[$d])?' idt-'.$d:'';}
/** The design of the pupils' Reading Pass tickets: each teacher chooses for their own pupils. */
function ticket_design():string{$u=function_exists('current_user')?current_user():null;$d=$u&&($u['role']??'')==='teacher'?(string)(val('SELECT setting_value FROM settings WHERE setting_key=?',['tk_design_'.(int)$u['id']])?:'b'):'b';return isset(ID_THEMES[$d])?$d:'b';}
/** The row of design choices, each with a small colour sample. */
function id_theme_choices(string $cur,bool $byRole=false):string{$h=$byRole?'<label class="org-opt idt-opt"><input type="radio" name="design" value="r"'.($cur==='r'?' checked':'').'><span><i class="idt-sw idt-sw-r" aria-hidden="true"></i><b>By role</b></span></label>':'';foreach(ID_THEMES as $k=>$t)$h.='<label class="org-opt idt-opt"><input type="radio" name="design" value="'.$k.'"'.($k===$cur?' checked':'').'><span><i class="idt-sw idt-sw-'.$k.'" aria-hidden="true"></i><b>'.e($t).'</b></span></label>';return $h;}
/** The logos on an ID card: Division, municipality and school (a school with no logo shows its code). */
function id_logos(int $school,int $muni,int $district=0):string{
 $h='';$div=division_logo();if($div!=='')$h.='<img src="'.e($div).'" alt="Division logo">';
 if(!$district&&$school&&rbac_ready())$district=(int)(val('SELECT district_id FROM schools WHERE id=?',[$school])?:0);
 if(!$muni&&$district&&rbac_ready())$muni=(int)(val('SELECT municipality_id FROM districts WHERE id=?',[$district])?:0);
 $ml=muni_logo($muni);if($ml!=='')$h.='<img src="'.e($ml).'" alt="Municipality logo">';
 $dl=district_logo($district);if($dl!=='')$h.='<img src="'.e($dl).'" alt="District logo">';   // v211
 if($school){$s=one('SELECT code,name,logo FROM schools WHERE id=?',[$school]);if($s)$h.=((string)$s['logo']!==''&&is_file(__DIR__.'/../public/'.$s['logo']))?'<img src="'.e($s['logo']).'" alt="'.e($s['code']).' logo">':'<span class="idc-code">'.e($s['code']).'</span>';}
 return $h;
}
/** The municipality and school of a role's place, for the logos on its ID card. */
function role_place_ids(string $role,int $scope):array{
 $lv=ROLES[$role][3]??'system';if(!rbac_ready()||!$scope)return [0,0];
 if($lv==='municipality')return [0,$scope];
 if($lv==='district')return [0,(int)(val('SELECT municipality_id FROM districts WHERE id=?',[$scope])?:0),$scope];
 return [$scope,0];
}
/** The Division Admin chooses the ID card design (three designs). */
function id_design_picker(string $back):string{
 $lg='';foreach(['central_admin'=>'Division','municipal_admin'=>'Municipal','district_admin'=>'District','school_admin'=>'School','content_dev'=>'Content Developer','teacher'=>'Teacher'] as $k=>$t)$lg.='<span><i class="idt-sw idt-sw-'.ROLE_THEMES[$k].'" aria-hidden="true"></i>'.e($t).'</span>';
 return '<div class="idc-pick no-print card"><p class="idt-legend muted"><b>ID colours by role:</b> '.$lg.' <small>Admins and view-only roles at the same level share a colour; the role is printed under the name.</small></p></div>';
}

/** v211 The picture on an ID card: the person's own photo or avatar, or else the male or female BULIG character. */
function staff_pic(int $uid,string $kind):string{
 $p=(string)(val('SELECT avatar_path FROM users WHERE id=?',[$uid])?:'');
 if($p!==''&&function_exists('local_image_file')&&local_image_file($p))return $p;
 $m=function_exists('tf_is_male')&&tf_is_male($uid);
 return char_src(($kind==='teacher'?'teacher-':'admin-').($m?'male':'female'),'hello');
}

/** v211 The people an administrator can give a role to, for the ID lookup (teachers and administrators in their area). */
function org_lookup():never{
 $u=current_user();$q=trim((string)($_GET['q']??''));header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 if(mb_strlen($q)<2||empty(ROLE_APPOINTS[$u['active_role']??'']??[])){echo '[]';exit;}
 $like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';$S=$u['scope_schools']??null;
 $rows=rows("SELECT u.id,u.public_id,u.name,u.role,u.school_id FROM users u WHERE u.active=1 AND u.role IN ('teacher','admin') AND u.id<>? AND (u.public_id LIKE ? OR u.name LIKE ?) ORDER BY u.public_id=? DESC,u.name LIMIT 40",[(int)$u['id'],$like,$like,strtoupper($q)]);
 $out=[];
 foreach($rows as $r){
  if($r['role']==='teacher'){if($S!==null&&!in_array((int)$r['school_id'],$S,true))continue;$what='Teacher'.(($sn=(string)(val("SELECT COALESCE(NULLIF(name,''),code) FROM schools WHERE id=?",[(int)$r['school_id']])?:''))!==''?' · '.$sn:'');}
  else{$rs=rows('SELECT role,scope_id FROM user_roles WHERE user_id=?',[(int)$r['id']]);$mine=array_filter($rs,fn($x)=>org_in_my_scope($u,$x['role'],(int)$x['scope_id']));
   if(($u['active_role']??'')!=='central_admin'&&!$mine)continue;$x=$rs[0]??null;$what=$x?ROLES[$x['role']][0].(($pl=rbac_scope_name($x['role'],(int)$x['scope_id']))!==''?' · '.$pl:''):'Administrator';}
  $out[]=['id'=>$r['public_id'],'name'=>name_case((string)$r['name']),'what'=>$what,'pic'=>staff_pic((int)$r['id'],$r['role']==='teacher'?'teacher':'admin')];
  if(count($out)>=8)break;}
 echo json_encode($out,JSON_UNESCAPED_UNICODE);exit;
}

/* ---------- v211 Same name check: before a new account is made, BULIG shows anyone with the same name ---------- */
/** Stops the form when an active account has the same name (pupils and teachers: in the same school), and asks
    "Is this the same person?" in a popup. "Create a new account anyway" sends the form again with confirm_new. */
function dupe_guard(string $name,string $role,int $school,string $back):void{
 if(!empty($_POST['confirm_new']))return;$n=mb_strtolower(preg_replace('/\s+/u',' ',trim($name)));if($n==='')return;
 if($role==='pupil'||$role==='teacher'){$sql="role=?".($school?' AND school_id='.(int)$school:'');$args=[$role];}
 else{$sql="role IN ('admin','teacher')";$args=[];}
 $m=rows("SELECT id,name,public_id,role,school_id FROM users WHERE active=1 AND $sql AND LOWER(TRIM(name))=? ORDER BY id LIMIT 5",array_merge($args,[$n]));
 if(!$m)return;
 $post=$_POST;unset($post['csrf']);$out=[];
 foreach($m as $r){$what=ucfirst((string)$r['role']);
  if($r['role']==='admin'&&rbac_ready()){$x=one('SELECT role,scope_id FROM user_roles WHERE user_id=? ORDER BY id LIMIT 1',[(int)$r['id']]);if($x)$what=ROLES[$x['role']][0].(($pl=rbac_scope_name($x['role'],(int)$x['scope_id']))!==''?' · '.$pl:'');}
  elseif($r['school_id']&&($sn=(string)(val("SELECT COALESCE(NULLIF(name,''),code) FROM schools WHERE id=?",[(int)$r['school_id']])?:''))!=='')$what.=' · '.$sn;
  $out[]=['id'=>$r['public_id'],'name'=>name_case((string)$r['name']),'what'=>$what,'role'=>$r['role'],'pic'=>$r['role']==='pupil'?(function_exists('chat_pic')?'':''):staff_pic((int)$r['id'],$r['role']==='teacher'?'teacher':'admin')];}
 $_SESSION['dupe']=['post'=>$post,'matches'=>$out,'name'=>$name];go($back);
}
function dupe_hidden(array $a,string $pre=''):string{$h='';foreach($a as $k=>$v){$n=$pre===''?(string)$k:$pre.'['.$k.']';$h.=is_array($v)?dupe_hidden($v,$n):'<input type="hidden" name="'.e($n).'" value="'.e((string)$v).'">';}return $h;}
function dupe_popup(?array $u):string{
 if(!$u||empty($_SESSION['dupe']))return '';$d=$_SESSION['dupe'];unset($_SESSION['dupe']);$p=$d['post'];
 $org=in_array($p['action']??'',['org_add','org_create'],true)&&isset(ROLES[$p['role']??''])&&!in_array($p['role'],['teacher','pupil'],true);
 $h='<div class="role-pop dupe-pop" role="dialog" aria-modal="true" aria-labelledby="dp-t" data-role-pop><div class="role-pop-box card">'.icon('alert').'<h2 id="dp-t">'.e($d['name']).' is already in BULIG</h2><p class="muted">Is this the same person? Using their account keeps one account and one ID.</p><div class="dupe-list">';
 foreach($d['matches'] as $m){$h.='<div class="dupe-row">'.($m['pic']!==''?'<img src="'.e($m['pic']).'" alt="">':'<span class="dupe-ic">'.icon('user').'</span>').'<span><b>'.e($m['name']).'</b><small>'.e($m['id']).' · '.e($m['what']).'</small></span>';
  if($org&&$m['role']!=='pupil')$h.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="org_add"><input type="hidden" name="mode" value="existing"><input type="hidden" name="public_id" value="'.e($m['id']).'"><input type="hidden" name="role" value="'.e($p['role']).'"><input type="hidden" name="scope" value="'.e((string)($p['scope']??'')).'"><button class="btn primary small">'.icon('check').'Yes, use this account</button></form>';
  $h.='</div>';}
 $h.='</div><form method="post" class="dupe-new">'.csrf_field().dupe_hidden($p).'<input type="hidden" name="confirm_new" value="1"><button class="btn secondary">'.icon('useredit').'No, create a new account anyway</button></form><button type="button" class="btn quiet role-pop-x" data-role-pop-close>Cancel</button></div></div>';
 return $h;
}
