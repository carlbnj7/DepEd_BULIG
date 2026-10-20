<?php
/* More than one school in one BULIG (v174, database/migrations/031_schools.sql).
   - Table schools: code (PCES, MFCES), name, School ID, district, head and the school logo. users.school_id says which
     school a teacher or pupil belongs to. Administrators belong to no school and see every school.
   - The "school in use" for a request: the signed-in teacher's or pupil's school; for an administrator, the school
     chosen in the filter ("Showing: All schools | PCES | MFCES", kept in the session) or, when adding a teacher, the
     school chosen for them (school_use()). admin_setting() reads school_name, school_id, school_district,
     school_head, school_code and logo_school from that school, so report cards, reports, the school code of new IDs
     and the school logo follow the right school without other changes. With no school in use, the first school.
   - Without the update file BULIG works as before (one school, from Settings). */

const SCHOOL_KEYS=['school_name'=>'name','school_id'=>'school_no','school_district'=>'district','school_head'=>'head','school_code'=>'code','logo_school'=>'logo'];
const SCHOOL_ACTIONS=['school_add','school_save','school_logo','school_logo_remove','teacher_school'];

function schools_ready():bool{static $r=null;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'schools'")&&(bool)one("SHOW COLUMNS FROM users LIKE 'school_id'");}catch(Throwable $e){$r=false;}}return $r;}
function school_rows(bool $activeOnly=false):array{static $c=null;if(!schools_ready())return [];if($c===null)$c=rows('SELECT * FROM schools ORDER BY id');return $activeOnly?array_values(array_filter($c,fn($s)=>(int)$s['active']===1)):$c;}
function school_row(int $id):?array{foreach(school_rows() as $s)if((int)$s['id']===$id)return $s;return null;}
function school_default_id():int{$r=school_rows();return $r?(int)$r[0]['id']:0;}
function school_label(array $s):string{return $s['code'].($s['name']!==''?' · '.$s['name']:'');}
/** Use this school for the rest of the request (new IDs, school info). */
function school_use(int $id):void{if($id&&school_row($id))$GLOBALS['bulig_school']=$id;}
/** The school of one account (teachers and pupils; 0 for administrators). */
function user_school_id(int $uid):int{if(!schools_ready())return 0;static $c=[];return $c[$uid]??=(int)val('SELECT school_id FROM users WHERE id=?',[$uid]);}
/** The administrator's school filter: 0 = all schools. */
function school_filter_id():int{if(!schools_ready()||count(school_rows())<2)return 0;$f=(int)($_SESSION['school_filter']??0);return school_row($f)?$f:0;}
/** The school in use for this request, or 0 (then the first school). */
function school_ctx_id():int{
 if(!schools_ready())return 0;if(!empty($GLOBALS['bulig_school']))return (int)$GLOBALS['bulig_school'];
 $u=function_exists('current_user')?current_user():null;if(!$u)return 0;
 if($u['role']==='admin')return school_filter_id();
 return (int)($u['school_id']??user_school_id((int)$u['id']));
}
function school_ctx_row():?array{if(!schools_ready())return null;return school_row(school_ctx_id())??school_row(school_default_id());}
/** admin_setting() asks here first for the school keys. null = not a school key, or no schools yet. */
function school_setting(string $key):?string{if(!isset(SCHOOL_KEYS[$key]))return null;$s=school_ctx_row();return $s===null?null:(string)$s[SCHOOL_KEYS[$key]];}
function school_setting_save(string $key,string $value):bool{
 if(!isset(SCHOOL_KEYS[$key]))return false;$s=school_ctx_row();if($s===null)return false;
 if($key==='school_code'){$value=strtoupper(trim($value));if(!valid_school_code($value)||val('SELECT 1 FROM schools WHERE code=? AND id<>?',[$value,$s['id']]))return true;}
 q('UPDATE schools SET '.SCHOOL_KEYS[$key].'=? WHERE id=?',[$value,$s['id']]);return true;
}
/** Put a new teacher or pupil in the school in use (a pupil: their teacher's school). */
function school_mark_user(int $uid,?int $school=null):void{if(!schools_ready())return;$sid=$school??(school_ctx_id()?:school_default_id());if($sid)q('UPDATE users SET school_id=? WHERE id=?',[$sid,$uid]);}
/** SQL limiting accounts to the administrator's school filter (alias of the users table). */
function school_users_sql(string $a='u'):string{$f=school_filter_id();return $f?' AND '.$a.'.school_id='.$f:'';}
/** SQL limiting sections (alias s) to the filter: sections of that school's teachers. */
function school_sections_sql():string{$f=school_filter_id();return $f?' AND s.teacher_id IN (SELECT id FROM users WHERE role=\'teacher\' AND school_id='.$f.')':'';}

/* ---------- the filter bar on the administrator's pages ---------- */
function school_filter_bar(string $page,string $extra=''):string{
 if(count(school_rows())<2)return '';$f=school_filter_id();
 $h='<nav class="sc-filter" aria-label="Choose a school"><span>Showing:</span><div class="sc-seg"><a class="'.($f?'':'on').'" href="?page='.e($page).$extra.'&amp;school=0">All schools</a>';
 foreach(school_rows() as $s)$h.='<a class="'.($f===(int)$s['id']?'on':'').'" href="?page='.e($page).$extra.'&amp;school='.(int)$s['id'].'" title="'.e($s['name']).'">'.e($s['code']).'</a>';
 return $h.'</div></nav>';
}
/** The pick from ?school= (administrators): kept for the next pages. */
function school_filter_take():void{if(isset($_GET['school'])&&schools_ready()){$v=(int)$_GET['school'];$_SESSION['school_filter']=$v&&school_row($v)?$v:0;}}

function school_counts():array{
 static $c=null;if($c!==null)return $c;$c=[];if(!schools_ready())return $c;
 foreach(rows("SELECT school_id,SUM(role='teacher') t,SUM(role='pupil' AND active=1) p FROM users WHERE school_id IS NOT NULL GROUP BY school_id") as $r)$c[(int)$r['school_id']]=['t'=>(int)$r['t'],'p'=>(int)$r['p'],'s'=>0];
 foreach(rows('SELECT u.school_id,COUNT(*) n FROM sections s JOIN users u ON u.id=s.teacher_id WHERE 1'.admin_live_sections_sql(false).' GROUP BY u.school_id') as $r)if($r['school_id']!==null)$c[(int)$r['school_id']]['s']=(int)$r['n'];
 return $c;
}
/** Side-by-side numbers for each school on the administrator's Home (all schools shown). */
function school_compare_cards():string{
 if(count(school_rows())<2||school_filter_id())return '';$cnt=school_counts();$week=date('Y-m-d',strtotime('-6 days'));
 $h='<div class="sc-compare">';
 foreach(school_rows() as $i=>$s){$sid=(int)$s['id'];$p=$cnt[$sid]['p']??0;
  $done=(int)val("SELECT COUNT(DISTINCT pp.pupil_id) FROM pupil_progress pp JOIN users u ON u.id=pp.pupil_id AND u.active=1 AND u.school_id=? WHERE pp.completed_at IS NOT NULL",[$sid]);
  $act=(int)val('SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN users u ON u.id=d.pupil_id AND u.active=1 AND u.school_id=? WHERE d.day>=?',[$sid,$week]);
  $pct=$p?round($done/$p*100):0;
  $h.='<a class="sc-cmp c'.($i%4).'" href="?page=dashboard&amp;school='.$sid.'"><h4><span class="sc-tag c'.($i%4).'">'.e($s['code']).'</span>'.number_format($p).' '.($p===1?'pupil':'pupils').'</h4><small>Finished at least one lesson</small><span class="sc-bar"><i class="w-'.(int)(round($pct/5)*5).'"></i></span><b>'.$pct.'%</b><p>Learned this week: '.number_format($act).' · Teachers: '.(int)($cnt[$sid]['t']??0).'</p></a>';}
 return $h.'</div>';
}

/* ---------- the Schools page ---------- */
function schools_view():void{
 if(!schools_ready()){admin_heading('PEOPLE','Schools','More than one school in one BULIG.');echo '<section class="card"><p>'.icon('info').' Import <b>live-update/031_schools.sql</b> in phpMyAdmin first. Then each school gets its own code, name and logo here.</p></section>';return;}
 $cnt=school_counts();$all=school_rows();$yy=date('y');
 admin_heading('PEOPLE','Schools','Each school has its own code, name and logo. Teachers belong to a school, and their pupils get IDs with that school’s code.','<a class="btn primary" href="#add-school">'.icon('useredit').'Add a school</a>');
 echo admin_kpis([['school',count($all),count($all)===1?'School':'Schools','',false],['user',array_sum(array_column($cnt,'t')),'Teachers','',false],['people',number_format(array_sum(array_column($cnt,'p'))),'Pupils','',false],['book',array_sum(array_column($cnt,'s')),'Sections','',false]]);
 echo '<div class="sc-grid">';
 foreach($all as $i=>$s){$sid=(int)$s['id'];$c=$cnt[$sid]??['t'=>0,'p'=>0,'s'=>0];$logo=$s['logo']!==''&&is_file(__DIR__.'/../public/'.$s['logo']);
  echo '<section class="card sc-card" id="school-'.$sid.'"><div class="sc-top">'.($logo?'<img class="sc-logo" src="'.e($s['logo']).'" alt="">':'<span class="sc-logo c'.($i%4).'">'.e($s['code']).'</span>').'<div><h3>'.e($s['name']!==''?$s['name']:$s['code']).'</h3><small>'.e(trim(($s['school_no']!==''?'School ID '.$s['school_no']:'').($s['district']!==''?' · '.$s['district']:''),' ·')?:'Add the School ID and district below').'</small></div><span class="sc-tag c'.($i%4).'">'.e($s['code']).'</span></div>';
  echo '<div class="sc-stats"><div><b>'.$c['t'].'</b><span>Teachers</span></div><div><b>'.number_format($c['p']).'</b><span>Pupils</span></div><div><b>'.$c['s'].'</b><span>Sections</span></div></div>';
  echo '<div class="sc-ids">New pupil IDs: <code>'.e(account_id_format('pupil',$s['code'],$yy,1)).'</code>, <code>'.e(account_id_format('pupil',$s['code'],$yy,2)).'</code>…<br>New teacher IDs: <code>'.e(account_id_format('teacher',$s['code'],$yy,1)).'</code>, <code>'.e(account_id_format('teacher',$s['code'],$yy,2)).'</code>…</div>';
  echo '<div class="ws-btns"><a class="btn primary small" href="?page=accounts&amp;school='.$sid.'">'.icon('people').'Teachers of this school</a><a class="btn secondary small" href="?page=dashboard&amp;school='.$sid.'">'.icon('chart').'Home for this school</a></div>';
  echo '<details class="sc-edit"><summary>'.icon('edit').'Edit name, code and logo</summary><form method="post" class="sc-form">'.csrf_field().'<input type="hidden" name="action" value="school_save"><input type="hidden" name="id" value="'.$sid.'">'.school_fields($s)
   .'<p class="muted sc-full">Changing the code only changes the IDs of new accounts. Accounts made before keep their IDs.</p><button class="btn primary">Save</button></form>';
  echo '<div class="sc-logorow"><b>School logo</b> <small class="muted">Printed on report cards of this school’s pupils</small><form method="post" enctype="multipart/form-data" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="school_logo"><input type="hidden" name="id" value="'.$sid.'"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required><button class="btn secondary small">Upload</button></form>'
   .($logo?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="school_logo_remove"><input type="hidden" name="id" value="'.$sid.'"><button class="btn quiet small">Remove logo</button></form>':'').'</div></details></section>';}
 echo '</div>';
 echo '<section class="card sc-add" id="add-school"><h2>'.icon('useredit').' Add a school</h2><form method="post" class="sc-form">'.csrf_field().'<input type="hidden" name="action" value="school_add">'.school_fields(['code'=>'','name'=>'','school_no'=>'','district'=>'','head'=>''])
  .'<p class="sc-full sc-prev" data-sc-prev>Pupils of this school will get IDs like <b>CODE'.$yy.'0001</b>. Teachers like <b>CODET0001</b>.</p><button class="btn primary">'.icon('check').'Save school</button></form></section>';
 echo '<p class="muted">The Division logo is the same for every school: Settings › School info.</p>';
}
function school_fields(array $s):string{
 return '<label class="sc-full">School name<input name="name" required maxlength="150" value="'.e($s['name']).'" placeholder="e.g. Manolo Fortich Central Elementary School"></label>'
  .'<label>School code<input name="code" required maxlength="10" pattern="[A-Za-z]{3,10}" value="'.e($s['code']).'" placeholder="e.g. MFCES" data-sc-code></label>'
  .'<label>School ID<input name="school_no" maxlength="40" value="'.e($s['school_no']).'" placeholder="e.g. 128001"></label>'
  .'<label>District<input name="district" maxlength="150" value="'.e($s['district']).'" placeholder="e.g. Manolo Fortich District"></label>'
  .'<label>School head<input name="head" maxlength="150" value="'.e($s['head']).'" placeholder="Name of the principal"></label>';
}
function school_actions(string $action):void{
 require_role('admin');if(!schools_ready())fail('Import live-update/031_schools.sql in phpMyAdmin first.');
 $id=(int)($_POST['id']??0);
 if($action==='teacher_school'){$t=admin_teacher($id);$sid=(int)($_POST['school_id']??0);if(!school_row($sid))fail('Choose a school.');
  db()->beginTransaction();try{q('UPDATE users SET school_id=? WHERE id=?',[$sid,$id]);q('UPDATE users SET school_id=? WHERE id IN (SELECT pupil_id FROM teacher_pupils WHERE teacher_id=?)',[$sid,$id]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  audit('teacher_school',$t['public_id'].' → '.school_row($sid)['code']);flash($t['name'].' and their pupils are now in '.school_row($sid)['code'].'. Their IDs stay the same.');go('?page=accounts');}
 if($action==='school_logo'||$action==='school_logo_remove'){$s=school_row($id);if(!$s)fail('School not found.',404);$old=(string)$s['logo'];
  $f=$action==='school_logo'?save_uploaded_image('logo'):'';q('UPDATE schools SET logo=? WHERE id=?',[$f,$id]);
  if($old!==''&&str_starts_with($old,'assets/uploads/')&&is_file(__DIR__.'/../public/'.$old))@unlink(__DIR__.'/../public/'.$old);
  audit('school_logo',$s['code'].($f?' uploaded':' removed'));flash($f?'School logo saved.':'School logo removed.');go('?page=schools#school-'.$id);}
 $code=strtoupper(trim((string)($_POST['code']??'')));$name=mb_substr(trim((string)($_POST['name']??'')),0,150);
 if(!valid_school_code($code))fail('Use 3 to 10 letters (A–Z) for the school code, for example MFCES.');if(mb_strlen($name)<3)fail('Type the name of the school.');
 if(val('SELECT 1 FROM schools WHERE code=? AND id<>?',[$code,$id]))fail('Another school already uses the code '.$code.'. Each school needs its own code.');
 $vals=[$code,$name,mb_substr(trim((string)($_POST['school_no']??'')),0,40),mb_substr(trim((string)($_POST['district']??'')),0,150),mb_substr(trim((string)($_POST['head']??'')),0,150)];
 if($action==='school_add'){q('INSERT INTO schools(code,name,school_no,district,head) VALUES(?,?,?,?,?)',$vals);$id=(int)db()->lastInsertId();audit('school_add',$code);flash($name.' is added. New teachers of this school get IDs like '.account_id_format('teacher',$code,date('y'),1).'.');}
 else{if(!school_row($id))fail('School not found.',404);q('UPDATE schools SET code=?,name=?,school_no=?,district=?,head=? WHERE id=?',array_merge($vals,[$id]));audit('school_save',$code);flash('School saved.');}
 go('?page=schools#school-'.$id);
}
/** "School" choice for a new teacher (Add teachers), set to the filter's school. */
function school_pick_field(string $label='School',int $sel=0):string{
 $all=school_rows(true);if(count($all)<2)return '';$f=$sel?:(school_filter_id()?:(int)$all[0]['id']);
 $h='<label class="sc-pick">'.e($label).'<select name="school_id" required data-sc-pick>';foreach($all as $s)$h.='<option value="'.(int)$s['id'].'" data-code="'.e($s['code']).'"'.((int)$s['id']===$f?' selected':'').'>'.e(school_label($s)).'</option>';
 return $h.'</select><small class="sc-idprev" data-sc-idprev data-yy="'.date('y').'"></small></label>';
}
/** A teacher's school, in the Teachers list, with a way to move them. */
function school_teacher_cell(array $t):string{
 if(count(school_rows())<2)return '';$s=school_row((int)($t['school_id']??0));$i=$s?array_search($s,school_rows(),true):0;
 return '<td>'.($s?'<span class="sc-tag c'.((int)$i%4).'">'.e($s['code']).'</span>':'—').'</td>';
}
function school_teacher_move(array $t):string{
 if(count(school_rows())<2)return '';$h='<details class="ws-more"><summary>'.icon('school').'Change school</summary><form method="post" class="ws-inline">'.csrf_field().'<input type="hidden" name="action" value="teacher_school"><input type="hidden" name="id" value="'.(int)$t['id'].'"><select name="school_id" aria-label="School">';
 foreach(school_rows(true) as $s)$h.='<option value="'.(int)$s['id'].'"'.((int)$s['id']===(int)($t['school_id']??0)?' selected':'').'>'.e($s['code']).'</option>';
 return $h.'</select><button class="btn primary small" data-confirm="Move '.e($t['name']).' and all their pupils to this school? Their IDs stay the same.">Move</button></form></details>';
}
/** The logos on a Reading Pass, Teacher Pass or Admin Pass (v174): the Division logo and the school's logo. A school
    with no logo yet shows its code in a round badge. Administrators belong to every school: all their logos. */
/** The Division logo: the one uploaded in Settings › School info, or else the Sangay ng Bukidnon logo that comes with BULIG. */
const DIVISION_LOGO='assets/brand/division-bukidnon.webp';
function division_logo():string{
 $f=function_exists('admin_setting')?admin_setting('logo_division',''):'';
 return $f!==''&&is_file(__DIR__.'/../public/'.$f)?$f:(is_file(__DIR__.'/../public/'.DIVISION_LOGO)?DIVISION_LOGO:'');
}
function pass_logos(int $uid,string $role):string{
 $out='';$div=division_logo();
 if($div!=='')$out.='<img class="tk-sl" src="'.e($div).'" alt="Division logo">';
 $list=[];if($role==='admin')$list=array_slice(school_rows(true),0,3);elseif(schools_ready()){$s=school_row(user_school_id($uid))??school_row(school_default_id());if($s)$list=[$s];}
 else{$f=admin_setting('logo_school','');if($f!==''&&is_file(__DIR__.'/../public/'.$f))$out.='<img class="tk-sl" src="'.e($f).'" alt="School logo">';}
 foreach($list as $s){$all=school_rows();$i=(int)array_search($s,$all,true);
  $out.=$s['logo']!==''&&is_file(__DIR__.'/../public/'.$s['logo'])?'<img class="tk-sl" src="'.e($s['logo']).'" alt="'.e($s['code']).' logo" title="'.e($s['name']).'">':'<span class="tk-sl tk-scode c'.($i%4).'" title="'.e($s['name']).'">'.e($s['code']).'</span>';}
 return $out!==''?'<span class="tk-logos">'.$out.'</span>':'';
}
