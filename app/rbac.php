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
 'central_admin'=>['Central Admin','Entire BULIG system','admin','system'],
 'municipal_admin'=>['Municipal Admin','Municipality management','admin','municipality'],
 'municipal_viewer'=>['Municipal Viewer','Municipality monitoring · view only','admin','municipality'],
 'district_admin'=>['District Admin','District management','admin','district'],
 'district_supervisor'=>['District Supervisor','District monitoring · view only','admin','district'],
 'school_admin'=>['School Admin','School management','admin','school'],
 'principal'=>['School Principal','School monitoring · view only','admin','school'],
 'teacher'=>['Teacher','Classes and reading verification','teacher','school'],
 'pupil'=>['Pupil','Lessons and activities','pupil','school']];
const ROLE_VIEWERS=['municipal_viewer','district_supervisor','principal'];
/** Who may appoint whom (a role can only appoint roles below it, inside its own scope). */
const ROLE_APPOINTS=['central_admin'=>['municipal_admin','municipal_viewer'],
 'municipal_admin'=>['district_admin','district_supervisor'],'district_admin'=>['school_admin','principal']];
/** v211 School Admin creates Teacher and Pupil accounts for their own school (Organization page). */
const ROLE_CREATES=['school_admin'=>['teacher','pupil']];
/** Actions that make teacher accounts: the Central Admin (first setup) and the School Admin only. */
const ROLE_TEACHER_MAKERS=['central_admin','school_admin'];
/** Actions a viewer may still do (their own account, messages and bug reports). */
const ROLE_VIEWER_ACTIONS=['logout','role_switch','message_reply','message_preview','message_send','chat_send','bug_report_submit','bug_popup_ack','profile','choose_avatar','change_password','my_name','mode','a11y','privacy_agree','rate_app','rate_later','notif_read','notif_all_read','notif_delete','push_subscribe','push_prefs','push_off','push_test','moved_seen'];
/** Actions only the Central Admin may do (the whole system). */
const ROLE_CENTRAL_ACTIONS=['cleanup_merge','cleanup_archive','cleanup_clear','school_delete','muni_add','muni_delete','org_muni_add','org_muni_delete'];

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
 $level=ROLES[$role][3]??'system';if($level==='system'||!$scope)return $level==='system'?'All of BULIG':'';
 if(!rbac_ready())return '';
 if($level==='municipality')return (string)(val('SELECT name FROM municipalities WHERE id=?',[$scope])?:'');
 if($level==='district')return (string)(val('SELECT name FROM districts WHERE id=?',[$scope])?:'');
 return (string)(val("SELECT COALESCE(NULLIF(name,''),code) FROM schools WHERE id=?",[$scope])?:'');
}

/** Called by current_user(): sets the active role. $u['role'] becomes admin, teacher or pupil for the pages,
    and $u['active_role'], $u['scope_id'], $u['scope_schools'] and $u['view_only'] tell what this role may do. */
function rbac_apply(array $u):array{
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

/** Server-side check for every form: viewers only look; system-wide actions are for the Central Admin. */
function rbac_guard_action(string $action):void{
 $u=current_user();if(!$u||empty($u['active_role']))return;
 if(!empty($u['view_only'])&&!in_array($action,ROLE_VIEWER_ACTIONS,true))fail('Your role ('.ROLES[$u['active_role']][0].') can view but not change this.',403);
 if(in_array($action,ROLE_CENTRAL_ACTIONS,true)&&$u['active_role']!=='central_admin')fail('Only the Central Admin can do this.',403);
 if(in_array($action,['create_account','timport_preview','timport_typed'],true)&&($u['role']??'')==='admin'){
  if(!in_array($u['active_role'],ROLE_TEACHER_MAKERS,true))fail('Teacher accounts are made by the School Admin.',403);
  if($u['active_role']==='school_admin')$_POST['school_id']=(string)(int)$u['scope_id'];}
 if($action==='school_add'&&!in_array($u['active_role'],['central_admin','municipal_admin','district_admin'],true))fail('Ask your District Admin to add a school.',403);
}
/** Pages only the Central Admin opens (the whole system's data). */
function rbac_guard_page(string $page):void{
 $u=current_user();if(!$u||($u['role']??'')!=='admin')return;
 if(in_array($page,['cleanup','activity_log','backup_csv','backup_sql','log_csv','ids_backup'],true)&&($u['active_role']??'central_admin')!=='central_admin')fail('Only the Central Admin can open this page.',403);
}

/* ---------- choosing and switching roles ---------- */
function role_switch_action():never{
 $u=current_user();if(!$u)fail('Please sign in again.',401);$k=(string)($_POST['role']??'');
 foreach($u['roles'] as $r)if(rbac_key($r)===$k){
  session_regenerate_id(true);$_SESSION['active_role']=$k;unset($_SESSION['school_filter']);
  try{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['last_role_'.(int)$u['id'],$k]);}catch(Throwable $e){}
  audit('role_switch',$k);go('?page=dashboard');}
 fail('This role is not assigned to your account.',403);
}
function role_view(array $u):void{
 $last=(string)(val('SELECT setting_value FROM settings WHERE setting_key=?',['last_role_'.(int)$u['id']])?:'');$cur=(string)($_SESSION['active_role']??'');
 echo '<section class="card role-pick"><span class="eyebrow">ONE ACCOUNT, MORE THAN ONE ROLE</span><h1>Choose Your Role</h1><p class="muted">'.e(name_case((string)$u['name'])).', choose how you want to use BULIG now. You can switch any time from the menu, without signing out.</p><div class="role-grid">';
 foreach($u['roles'] as $r){$k=rbac_key($r);[$t,$d]=ROLES[$r['role']];$place=rbac_scope_name($r['role'],$r['scope_id']);
  echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="role_switch"><input type="hidden" name="role" value="'.e($k).'"><button class="role-card'.($k===$cur?' on':'').'">'.icon(in_array($r['role'],ROLE_VIEWERS,true)?'eye':($r['role']==='pupil'?'book':($r['role']==='teacher'?'people':'shield'))).'<b>'.e($t).'</b><small>'.e($d).($place!==''?' · '.e($place):'').'</small>'.($k===$cur?'<span class="role-tag">Now</span>':($k===$last?'<span class="role-tag">Last used</span>':'')).'</button></form>';}
 echo '</div></section>';
}
/** The role switcher in the menu (only for people with more than one role). */
function role_switcher(?array $u):string{
 if(!$u||count($u['roles']??[])<2)return '';$t=ROLES[$u['active_role']][0]??'';
 return '<a class="role-switch" href="?page=role">'.icon('swap').'<span><small>Role</small><b>'.e($t).'</b></span></a>';
}

/* ---------- the Organization page: municipalities, districts, schools and appointments ---------- */
function org_my_munis(array $u):array{
 $r=$u['active_role'];$s=(int)$u['scope_id'];
 if($r==='central_admin')return rows('SELECT * FROM municipalities ORDER BY id');
 if(ROLES[$r][3]==='municipality')return rows('SELECT * FROM municipalities WHERE id=?',[$s]);
 if(ROLES[$r][3]==='district')return rows('SELECT m.* FROM municipalities m JOIN districts d ON d.municipality_id=m.id WHERE d.id=?',[$s]);
 return [];
}
function org_view(array $u):void{
 if(!rbac_ready()){echo '<section class="card"><h1>Organization</h1><p>'.icon('info').' Back up the database, then import <b>database/migrations/040_hierarchy_roles.sql</b> in phpMyAdmin to turn on municipalities, districts and roles.</p></section>';return;}
 $r=$u['active_role'];$central=$r==='central_admin';$view=!empty($u['view_only']);$munis=org_my_munis($u);
 echo hm_head('ORGANIZATION','Organization','Municipalities, districts and schools, and who manages each one. '.(ROLES[$r][0]??'').($u['scope_id']?' · '.rbac_scope_name($r,(int)$u['scope_id']):''));
 echo org_chart($u,$munis);
 $sch=rbac_my_schools();
 foreach($munis as $m){$ds=rows('SELECT d.*,(SELECT COUNT(*) FROM schools s WHERE s.district_id=d.id) n FROM districts d WHERE d.municipality_id=?'.(ROLES[$r][3]==='district'?' AND d.id='.(int)$u['scope_id']:'').' ORDER BY d.name',[$m['id']]);
  echo '<section class="card org-muni"><div class="ws-hd"><h2>'.icon('school').e($m['name']).'</h2>'.($central&&!$view?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="org_muni_delete"><input type="hidden" name="id" value="'.(int)$m['id'].'"><button class="btn quiet small" data-confirm="Remove '.e($m['name']).'? Its districts are removed too. Schools stay; they go back to the review list.">'.icon('trash').'Remove</button></form>':'').'</div><div class="org-ds">';
  foreach($ds as $d)echo '<div class="org-d"><b>'.e($d['name']).'</b><small>'.(int)$d['n'].' '.((int)$d['n']===1?'school':'schools').'</small></div>';
  echo '</div>';
  if(!$view&&($central||$r==='municipal_admin'))echo '<form method="post" class="org-add">'.csrf_field().'<input type="hidden" name="action" value="org_district_add"><input type="hidden" name="municipality" value="'.(int)$m['id'].'"><label>New district<input name="name" maxlength="120" required placeholder="e.g. '.e($m['name']).' District '.roman(count($ds)+1).'"></label><button class="btn secondary small">'.icon('check').'Add district</button></form>';
  echo '</section>';}
 if($central&&!$view)echo '<section class="card org-muni"><h2>'.icon('school').'Add a municipality</h2><form method="post" class="org-add">'.csrf_field().'<input type="hidden" name="action" value="org_muni_add"><label>Municipality<input name="name" maxlength="80" required placeholder="e.g. Sumilao"></label><label>Number of districts<input name="n" type="number" min="1" max="10" value="1" required></label><button class="btn primary small">'.icon('check').'Add municipality</button></form></section>';
 /* schools: the review list (no district yet) and moving a school to a district */
 if(in_array($r,['central_admin','municipal_admin','municipal_viewer','district_admin','district_supervisor'],true)){
  $dopts=rows('SELECT d.id,d.name FROM districts d WHERE 1'.($central||ROLES[$r][3]==='system'?'':(ROLES[$r][3]==='municipality'?' AND d.municipality_id='.(int)$u['scope_id']:' AND d.id='.(int)$u['scope_id'])).' ORDER BY d.name');
  $list=rows('SELECT s.id,s.code,s.name,s.district,s.district_id FROM schools s WHERE '.($sch===null?'1':($sch?'s.id IN ('.implode(',',$sch).')':'0')).($central?'':'').' ORDER BY s.district_id IS NOT NULL,s.name');
  if($central)$list=rows('SELECT s.id,s.code,s.name,s.district,s.district_id FROM schools s ORDER BY s.district_id IS NOT NULL,s.name');
  $wait=array_filter($list,fn($x)=>!$x['district_id']);
  echo '<section class="card org-schools" id="org-review"><div class="ws-hd"><h2>'.icon('alert').'Schools waiting for a district</h2><span class="rs-sub">'.count($wait).' to review</span></div><p class="muted">BULIG never guesses. Choose each school’s district from DepEd’s official list. The school, its teachers and pupils stay the same.</p>';
  foreach($list as $x){if($x['district_id']&&!$wait)break;
   echo '<form method="post" class="org-srow'.($x['district_id']?' ok':'').'">'.csrf_field().'<input type="hidden" name="action" value="org_school_district"><input type="hidden" name="school" value="'.(int)$x['id'].'"><span><b>'.e($x['name']?:$x['code']).'</b><small>'.e($x['code']).($x['district']!==''?' · typed: '.e($x['district']):'').'</small></span>';
   if($view)echo '<span class="rs-sub">'.e($x['district_id']?(string)val('SELECT name FROM districts WHERE id=?',[$x['district_id']]):'Not set').'</span></form>';
   else{echo '<select name="district"><option value="0">Choose a district</option>';foreach($dopts as $d)echo '<option value="'.(int)$d['id'].'"'.((int)$x['district_id']===(int)$d['id']?' selected':'').'>'.e($d['name']).'</option>';echo '</select><button class="btn secondary small">Save</button></form>';}}
  if(!$list)echo '<p class="muted">No schools in your area yet.</p>';
  echo '</section>';
 }
 /* appointments */
 $can=ROLE_APPOINTS[$r]??[];
 $rows=rows('SELECT ur.*,u.name,u.public_id FROM user_roles ur JOIN users u ON u.id=ur.user_id ORDER BY FIELD(ur.role,'."'".implode("','",array_keys(ROLES))."'".'),u.name');
 $rows=array_values(array_filter($rows,fn($x)=>org_in_my_scope($u,$x['role'],(int)$x['scope_id'])));
 echo '<section class="card org-people" id="org-people"><div class="ws-hd"><h2>'.icon('people').'Appointed roles</h2><span class="rs-sub">'.count($rows).'</span></div><p class="muted">One person can hold several roles with one account. They choose a role after signing in, and switch from the menu.</p><div class="org-list">';
 foreach($rows as $x){$self=(int)$x['user_id']===(int)$u['id'];$rm=!$view&&!$self&&in_array($x['role'],$can,true);
  echo '<div class="org-prow"><span><b>'.e(name_case((string)$x['name'])).'</b><small>'.e($x['public_id']).' · '.e(ROLES[$x['role']][0]).($x['scope_id']?' · '.e(rbac_scope_name($x['role'],(int)$x['scope_id'])):'').'</small></span>'
   .($rm?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="org_unappoint"><input type="hidden" name="id" value="'.(int)$x['id'].'"><button class="btn quiet small" data-confirm="Remove this role from '.e($x['name']).'? Their account and data stay.">'.icon('trash').'Remove role</button></form>':'').'</div>';}
 echo '</div>';
 if($can&&!$view){
  $scopes=org_scope_options($u);
  echo '<form method="post" class="org-appoint">'.csrf_field().'<input type="hidden" name="action" value="org_appoint"><h3>Appoint someone</h3><label>Their BULIG ID<input name="public_id" maxlength="30" required placeholder="e.g. PCEST0001" autocapitalize="characters"></label><label>Role<select name="role" required data-org-role>';
  foreach($can as $k)echo '<option value="'.$k.'" data-level="'.ROLES[$k][3].'">'.e(ROLES[$k][0]).'</option>';
  echo '</select></label><label>For<select name="scope" required>';foreach($scopes as $lv=>$opts)foreach($opts as $id=>$n)echo '<option value="'.$lv.':'.$id.'" data-level="'.$lv.'">'.e($n).'</option>';
  echo '</select></label><p class="muted org-hint">Give a role to someone who already has a BULIG account (a teacher can also be a School Admin). No account yet? Use Create a new account below. Public sign-up never gives admin access.</p><button class="btn primary">'.icon('check').'Appoint</button></form>';
 }
 $mk=array_merge($can,ROLE_CREATES[$r]??[]);
 if($mk&&!$view){$scopes=org_scope_options($u);if(isset(ROLE_CREATES[$r]))$scopes=['school'=>[(int)$u['scope_id']=>rbac_scope_name($r,(int)$u['scope_id'])]];$can=$mk;
  echo '<form method="post" class="org-appoint org-create" id="org-create">'.csrf_field().'<input type="hidden" name="action" value="org_create"><h3>Create a new account</h3><p class="muted org-hint">For a person with no BULIG account yet. BULIG makes their ID and a starting password, and shows them once so you can give them to the person.</p><label>Full name<input name="name" maxlength="150" required placeholder="e.g. Maria Santos"></label><label>Role<select name="role" required>';
  foreach($can as $k)echo '<option value="'.$k.'">'.e(ROLES[$k][0]).'</option>';
  echo '</select></label><label>For<select name="scope" required>';foreach($scopes as $lv=>$opts)foreach($opts as $id=>$n)echo '<option value="'.$lv.':'.$id.'">'.e($n).'</option>';
  echo '</select></label>';
  if(isset(ROLE_CREATES[$r])){$secs=rows("SELECT s.id,s.grade_level,s.name,t.name tn FROM sections s JOIN users t ON t.id=s.teacher_id AND t.active=1 WHERE s.archived_at IS NULL AND t.school_id=? ORDER BY s.grade_level,s.name",[(int)$u['scope_id']]);
   echo '<label>Teacher: male or female<select name="sex"><option value="female">Female</option><option value="male">Male</option></select></label><label>Pupil: class<select name="section"><option value="0">Choose a class (pupils only)</option>';foreach($secs as $x)echo '<option value="'.(int)$x['id'].'">Grade '.(int)$x['grade_level'].' · '.e($x['name']).' · '.e(name_case((string)$x['tn'])).'</option>';echo '</select></label>';}
  echo '<button class="btn primary">'.icon('useredit').'Create account</button></form>';}
 if(!empty($_SESSION['org_new'])){$n=$_SESSION['org_new'];unset($_SESSION['org_new']);
  echo '<div class="org-new" role="status">'.icon('check').'<div><b>Account created for '.e($n['name']).' · '.e($n['role']).'</b><p>Give these to them. They are shown only once.</p><dl><dt>ID</dt><dd>'.e($n['id']).'</dd><dt>Password</dt><dd>'.e($n['pw']).'</dd></dl><p class="muted">They can change the password after signing in.</p></div></div>';}
 echo '</section>';
}
/** v210 The hierarchy as a chart: Central Admin → municipalities → districts → schools, with who holds each role
    and how many teachers and pupils each school has. Each role sees its own part. */
function org_chart(array $u,array $munis):string{
 $who=[];foreach(rows('SELECT ur.role,ur.scope_id,u.name FROM user_roles ur JOIN users u ON u.id=ur.user_id AND u.active=1') as $x)$who[$x['role'].':'.$x['scope_id']][]=name_case((string)$x['name']);
 foreach(rows("SELECT u.name FROM users u WHERE u.role='admin' AND u.active=1 AND NOT EXISTS(SELECT 1 FROM user_roles x WHERE x.user_id=u.id)") as $x)$who['central_admin:0'][]=name_case((string)$x['name']);   // administrators from before the update
 $names=fn($k)=>isset($who[$k])?e(implode(', ',$who[$k])):'<i>Not yet appointed</i>';
 $cnt=[];foreach(rows("SELECT school_id,SUM(role='teacher') t,SUM(role='pupil') p FROM users WHERE active=1 AND school_id IS NOT NULL GROUP BY school_id") as $x)$cnt[(int)$x['school_id']]=$x;
 $sch=rbac_my_schools();$r=$u['active_role'];
 $h='<section class="card oc" aria-label="Organization chart"><div class="oc-top"><span class="oc-box oc-c"><b>Central Admin</b><small>Entire BULIG System</small><em>'.$names('central_admin:0').'</em></span></div><div class="oc-munis">';
 foreach($munis as $m){$mid=(int)$m['id'];
  $ds=rows('SELECT * FROM districts WHERE municipality_id=?'.(ROLES[$r][3]==='district'?' AND id='.(int)$u['scope_id']:'').' ORDER BY name',[$mid]);
  $h.='<details class="oc-muni" open><summary class="oc-box oc-m"><b>Municipal Admin</b><small>'.e($m['name']).'</small><em>'.$names('municipal_admin:'.$mid).'</em><span class="oc-view">Viewer: '.$names('municipal_viewer:'.$mid).'</span></summary><div class="oc-ds">';
  foreach($ds as $d){$did=(int)$d['id'];$ss=rows('SELECT id,code,name FROM schools WHERE district_id=? AND active=1'.($sch===null?'':($sch?' AND id IN ('.implode(',',$sch).')':' AND 0')).' ORDER BY name',[$did]);
   $h.='<details class="oc-d" open><summary class="oc-box oc-da"><b>District Admin</b><small>'.e($d['name']).'</small><em>'.$names('district_admin:'.$did).'</em></summary><div class="oc-box oc-sup"><b>District Supervisor</b><small>View only</small><em>'.$names('district_supervisor:'.$did).'</em></div><div class="oc-ss">';
   foreach($ss as $x){$sid=(int)$x['id'];$c=$cnt[$sid]??['t'=>0,'p'=>0];
    $h.='<a class="oc-s" href="?page=school&amp;id='.$sid.'"><b>'.e($x['name']?:$x['code']).'</b><span class="oc-r oc-sa">School Admin: '.$names('school_admin:'.$sid).'</span><span class="oc-r oc-pr">Principal (view only): '.$names('principal:'.$sid).'</span><span class="oc-r oc-t">'.icon('people').(int)$c['t'].' '.((int)$c['t']===1?'teacher':'teachers').'</span><span class="oc-r oc-p">'.icon('user').number_format((int)$c['p']).' '.((int)$c['p']===1?'pupil':'pupils').'</span></a>';}
   if(!$ss)$h.='<p class="oc-none">No schools in this district yet.</p>';
   $h.='</div></details>';}
  if(!$ds)$h.='<p class="oc-none">No districts yet.</p>';
  $h.='</div></details>';}
 if(!$munis)$h.='<p class="oc-none">No municipality in your area yet.</p>';
 return $h.'</div></section>';
}
/** Is a role at this place inside the active role's area? */
function org_in_my_scope(array $u,string $role,int $scope):bool{
 $r=$u['active_role'];if($r==='central_admin')return true;$lv=ROLES[$role][3]??'';$me=(int)$u['scope_id'];
 if(ROLES[$r][3]==='municipality'){if($lv==='municipality')return $scope===$me;if($lv==='district')return (int)val('SELECT municipality_id FROM districts WHERE id=?',[$scope])===$me;return in_array($scope,$u['scope_schools']??[],true);}
 if(ROLES[$r][3]==='district'){if($lv==='district')return $scope===$me;if($lv==='school')return in_array($scope,$u['scope_schools']??[],true);return false;}
 if(ROLES[$r][3]==='school')return $lv==='school'&&$scope===$me;
 return false;
}
/** The places the active role may appoint people to, by level. */
function org_scope_options(array $u):array{
 $o=['municipality'=>[],'district'=>[],'school'=>[]];
 foreach(org_my_munis($u) as $m)if($u['active_role']==='central_admin')$o['municipality'][(int)$m['id']]=$m['name'];
 $ds=rows('SELECT d.id,d.name,d.municipality_id FROM districts d ORDER BY d.name');foreach($ds as $d)if(org_in_my_scope($u,'district_admin',(int)$d['id']))$o['district'][(int)$d['id']]=$d['name'];
 foreach(rows('SELECT id,name,code FROM schools WHERE active=1 ORDER BY name') as $s)if(org_in_my_scope($u,'school_admin',(int)$s['id']))$o['school'][(int)$s['id']]=$s['name']?:$s['code'];
 return array_filter($o);
}
function org_action(string $action):never{
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
  $p=one('SELECT id,name,role FROM users WHERE public_id=? AND active=1',[strtoupper(trim((string)($_POST['public_id']??'')))]);if(!$p)fail('No active account has that ID.',404);
  if($p['role']==='pupil')fail('Pupils cannot hold administrative roles.',403);if((int)$p['id']===(int)$u['id'])fail('You cannot appoint yourself.',403);
  q('INSERT IGNORE INTO user_roles(user_id,role,scope_id,appointed_by) VALUES(?,?,?,?)',[$p['id'],$role,$sid,$u['id']]);
  notify((int)$p['id'],'note','You have a new role in BULIG','You are now '.ROLES[$role][0].' · '.rbac_scope_name($role,$sid).'. Choose it from the menu (Switch role).','?page=role');
  audit('org_appoint',$p['name'].' → '.$role.':'.$sid);flash(name_case((string)$p['name']).' is now '.ROLES[$role][0].'.');go('?page=org#org-people');}
 if($action==='org_create'&&in_array((string)($_POST['role']??''),ROLE_CREATES[$r]??[],true))org_create_school_account($u);
 if($action==='org_create'){$role=(string)($_POST['role']??'');if(!in_array($role,ROLE_APPOINTS[$r]??[],true))fail('You cannot create this role.',403);
  [$lv,$sid]=array_pad(explode(':',(string)($_POST['scope']??'')),2,'0');$sid=(int)$sid;if($lv!==ROLES[$role][3])fail('Choose a '.ROLES[$role][3].' for this role.');
  if(!org_in_my_scope($u,$role,$sid))fail('Choose a place inside your area.',403);
  $nm=name_case(mb_substr(trim((string)($_POST['name']??'')),0,150));if(mb_strlen($nm)<3)fail('Type the person’s full name.');
  $pw=substr(str_replace(['+','/','=','0','O','l','I'],'',base64_encode(random_bytes(12))),0,10);
  db()->beginTransaction();try{$pid=new_account_id('admin');
   q('INSERT INTO users(public_id,role,name,password_hash,school_id) VALUES(?,?,?,?,?)',[$pid,'admin',$nm,password_hash($pw,PASSWORD_DEFAULT),$lv==='school'?$sid:null]);$id=(int)db()->lastInsertId();
   q('INSERT INTO admins VALUES(?)',[$id]);q('INSERT INTO user_roles(user_id,role,scope_id,appointed_by) VALUES(?,?,?,?)',[$id,$role,$sid,$u['id']]);
   audit('org_create',$pid.' '.$role.':'.$sid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  $_SESSION['org_new']=['name'=>$nm,'role'=>ROLES[$role][0].' · '.rbac_scope_name($role,$sid),'id'=>$pid,'pw'=>$pw];go('?page=org#org-people');}
 if($action==='org_unappoint'){$x=one('SELECT * FROM user_roles WHERE id=?',[(int)($_POST['id']??0)]);if(!$x)fail('Not found.',404);
  if((int)$x['user_id']===(int)$u['id']||!in_array($x['role'],ROLE_APPOINTS[$r]??[],true)||!org_in_my_scope($u,$x['role'],(int)$x['scope_id']))fail('You cannot remove this role.',403);
  q('DELETE FROM user_roles WHERE id=?',[$x['id']]);audit('org_unappoint',$x['user_id'].' '.$x['role'].':'.$x['scope_id']);flash('Role removed. The account and its data stay.');go('?page=org#org-people');}
 fail('Unknown action.');
}

/** v211 School Admin: a new Teacher or Pupil account in their own school. Pupils join a teacher's class. */
function org_create_school_account(array $u):never{
 $role=(string)$_POST['role'];$sid=(int)$u['scope_id'];if(!$sid)fail('Your role has no school.',403);
 $nm=name_case(mb_substr(trim((string)($_POST['name']??'')),0,150));if(mb_strlen($nm)<3)fail('Type the person’s full name.');
 db()->beginTransaction();try{
  if($role==='teacher'){if(!cards_ready())fail('Import database/migrations/018_pupil_cards.sql first.');$sx=(string)($_POST['sex']??'');if(!in_array($sx,['female','male'],true))fail('Choose Male or Female for the teacher.');
   $c=teacher_create($nm,$sx);school_mark_user($c['id'],$sid);$pid=$c['public'];$pw=$c['pw'];}
  else{$sec=one('SELECT s.* FROM sections s JOIN users t ON t.id=s.teacher_id WHERE s.id=? AND s.archived_at IS NULL AND t.school_id=?',[(int)($_POST['section']??0),$sid]);if(!$sec)fail('Choose a class in your school for the pupil.');
   $pid=new_account_id('pupil');q('INSERT INTO users(public_id,role,name,password_hash,school_id) VALUES(?,?,?,?,?)',[$pid,'pupil',$nm,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$sid]);$id=(int)db()->lastInsertId();
   q('INSERT INTO pupils(user_id,grade_level,section) VALUES(?,?,?)',[$id,(int)$sec['grade_level'],$sec['name']]);q('INSERT INTO pupil_sections VALUES(?,?)',[$id,$sec['id']]);q('INSERT INTO teacher_pupils VALUES(?,?)',[$sec['teacher_id'],$id]);$pw=new_pupil_password($id);}
  audit('org_create',$pid.' '.$role.':'.$sid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 $_SESSION['org_new']=['name'=>$nm,'role'=>ROLES[$role][0].' · '.rbac_scope_name('school_admin',$sid),'id'=>$pid,'pw'=>$pw];go('?page=org#org-people');
}

/* ---------- v210 view-only dashboard: Municipal Viewer, District Supervisor, School Principal ---------- */
function monitor_dashboard(array $u):void{
 $S=$u['scope_schools']??[];$in=$S?implode(',',array_map('intval',$S)):'0';$r=$u['active_role'];$place=rbac_scope_name($r,(int)$u['scope_id']);$wk=date('Y-m-d',strtotime('-6 days'));
 $n=fn($sql)=>(int)(val($sql)?:0);
 $pupils=$n("SELECT COUNT(*) FROM users WHERE role='pupil' AND active=1 AND school_id IN ($in)");$teachers=$n("SELECT COUNT(*) FROM users WHERE role='teacher' AND active=1 AND school_id IN ($in)");
 $active=$n("SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN users u ON u.id=d.pupil_id WHERE u.school_id IN ($in) AND d.day>='$wk'");
 $lessons=$n("SELECT COUNT(*) FROM pupil_progress p JOIN users u ON u.id=p.pupil_id WHERE u.school_id IN ($in) AND p.completed_at>='$wk'");
 $wait=rv_ready()?$n("SELECT COUNT(*) FROM reading_verifications r JOIN users u ON u.id=r.pupil_id WHERE u.school_id IN ($in) AND r.state IN ('pending','missing')"):0;
 echo '<div class="mon-head"><div><span class="eyebrow">'.e(mb_strtoupper(ROLES[$r][0])).' · VIEW ONLY</span><h1>'.e($place!==''?$place:'My area').'</h1><p class="muted">Reading progress in your area. You can look and compare; changes are made by the teachers and administrators.</p></div><span class="mon-badge">'.icon('eye').'View only</span></div>';
 echo admin_kpis([['school',count($S),count($S)===1?'School':'Schools','',false],['user',$teachers,'Teachers','',false],['people',number_format($pupils),'Pupils','',false],['fire',number_format($active),'Learned this week',$pupils?round($active/$pupils*100).'% of pupils':'',false],['book',number_format($lessons),'Lessons finished this week','',false],['mic',number_format($wait),'Readings waiting for teachers','',false]]);
 /* pupils by reading level */
 $lv=rows("SELECT a.level_id,COUNT(*) n FROM pupil_level_assignments a JOIN users u ON u.id=a.pupil_id AND u.active=1 WHERE u.school_id IN ($in) GROUP BY a.level_id");$tot=array_sum(array_column($lv,'n'))?:1;
 usort($lv,fn($a,$b)=>level_rank((int)$a['level_id'])<=>level_rank((int)$b['level_id']));
 echo '<section class="card mon-lv"><h2>'.icon('ladder').'Pupils by reading level</h2>';
 foreach($lv as $x){$p=round($x['n']/$tot*100);echo '<div class="mon-bar"><span>'.e((int)$x['level_id']?level_label((int)$x['level_id']):'Reading check').'</span><i><b data-w="'.$p.'"></b></i><em>'.number_format((int)$x['n']).'</em></div>';}
 if(!$lv)echo '<p class="muted">No pupils yet.</p>';echo '</section>';
 /* comparison: schools (municipal, district) or teachers (principal) */
 if($r==='principal'){
  $rows=rows("SELECT t.name,(SELECT COUNT(*) FROM teacher_pupils tp WHERE tp.teacher_id=t.id) pupils,(SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN teacher_pupils tp ON tp.pupil_id=d.pupil_id WHERE tp.teacher_id=t.id AND d.day>='$wk') act,(SELECT COUNT(*) FROM pupil_progress p JOIN teacher_pupils tp ON tp.pupil_id=p.pupil_id WHERE tp.teacher_id=t.id AND p.completed_at IS NOT NULL) done".(rv_ready()?",(SELECT COUNT(*) FROM reading_verifications rv JOIN teacher_pupils tp ON tp.pupil_id=rv.pupil_id WHERE tp.teacher_id=t.id AND rv.state IN ('pending','missing')) wait":",0 wait")." FROM users t WHERE t.role='teacher' AND t.active=1 AND t.school_id IN ($in) ORDER BY t.name");
  $head=['Teacher','Pupils','Learned this week','Lessons finished','Readings waiting'];
 }else{
  $rows=rows("SELECT COALESCE(NULLIF(s.name,''),s.code) name,(SELECT COUNT(*) FROM users u WHERE u.school_id=s.id AND u.role='pupil' AND u.active=1) pupils,(SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN users u ON u.id=d.pupil_id WHERE u.school_id=s.id AND d.day>='$wk') act,(SELECT COUNT(*) FROM pupil_progress p JOIN users u ON u.id=p.pupil_id WHERE u.school_id=s.id AND p.completed_at IS NOT NULL) done".(rv_ready()?",(SELECT COUNT(*) FROM reading_verifications rv JOIN users u ON u.id=rv.pupil_id WHERE u.school_id=s.id AND rv.state IN ('pending','missing')) wait":",0 wait").",(SELECT d.name FROM districts d WHERE d.id=s.district_id) district FROM schools s WHERE s.id IN ($in) ORDER BY district,name");
  $head=['School','Pupils','Learned this week','Lessons finished','Readings waiting'];
 }
 echo '<section class="card mon-cmp"><h2>'.icon('chart').($r==='principal'?'Teachers':'Schools').' compared</h2><div class="tablewrap"><table class="sp-tbl mon-tbl"><thead><tr>';foreach($head as $h)echo '<th>'.$h.'</th>';echo '</tr></thead><tbody>';
 foreach($rows as $x){$pct=(int)$x['pupils']?round($x['act']/$x['pupils']*100):0;
  echo '<tr><td><b>'.e($r==='principal'?name_case((string)$x['name']):(string)$x['name']).'</b>'.(!empty($x['district'])?'<small class="muted"> · '.e($x['district']).'</small>':'').'</td><td data-l="Pupils">'.number_format((int)$x['pupils']).'</td><td data-l="Learned this week">'.number_format((int)$x['act']).' <small class="muted">('.$pct.'%)</small></td><td data-l="Lessons finished">'.number_format((int)$x['done']).'</td><td data-l="Readings waiting">'.number_format((int)$x['wait']).'</td></tr>';}
 if(!$rows)echo '<tr><td colspan="5" class="muted">Nothing to show yet.</td></tr>';
 echo '</tbody></table></div></section>';
 echo '<div class="ws-btns"><a class="btn secondary" href="?page=org">'.icon('school').'Organization</a><a class="btn secondary" href="?page=reports">'.icon('chart').'School reports</a><a class="btn secondary" href="?page=messages">'.icon('comic').'Messages</a></div>';
}
