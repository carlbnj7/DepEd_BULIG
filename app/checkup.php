<?php
/* Account check-up (admin): finds account problems and offers a one-tap fix for each.
   Locked sign-ins, possible duplicate pupils, pupils with no section, sections whose teacher is turned off,
   accounts that never signed in, teachers with no section and admins without a PIN. Runs each time the page opens. */
const CHECKUP_ACTIONS=['admin_ck_unlock','admin_ck_place','admin_ck_toggle_pupil','admin_ck_not_dupe','admin_ck_tell'];
const CHECKUP_NEVER_DAYS=30;

function ck_name_key(string $n):string{$n=mb_strtolower(trim($n));$n=preg_replace('/[^\p{L}\p{N} ]+/u','',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);}
function ck_live_sql(string $a='s'):string{return sections_archive_supported()?' AND '.$a.'.archived_at IS NULL':'';}
function ck_section_label(?array $s):string{return $s&&$s['sid']?'Grade '.(int)$s['grade_level'].' – '.$s['sname']:'No section';}
function ck_not_dupes():array{$v=json_decode(admin_setting('checkup_not_dupes','[]'),true);return is_array($v)?array_values(array_filter($v,'is_string')):[];}

/** Everything the check-up found. Each issue: [id, level bad|warn, icon, title, text, data]. */
function checkup_data():array{
 $issues=[];$good=[];$logins=admin_last_logins();
 // 1. Locked sign-ins (too many wrong passwords in the last 15 minutes)
 $locked=rows('SELECT u.id,u.public_id,u.name,u.role,la.h FROM (SELECT identifier_hash h,COUNT(*) c FROM login_attempts WHERE attempted_at>DATE_SUB(NOW(),INTERVAL '.LOGIN_LOCK_MINUTES.' MINUTE) GROUP BY identifier_hash HAVING c>='.LOGIN_MAX_TRIES.') la JOIN users u ON SHA2(LOWER(u.public_id),256)=la.h ORDER BY u.name');
 foreach($locked as &$l)$l['secs']=login_lock_seconds((string)$l['h']);unset($l);
 $locked=array_values(array_filter($locked,fn($l)=>$l['secs']>0));
 if($locked)$issues[]=['locked','bad','lock',count($locked).' account'.(count($locked)===1?' is':'s are').' locked right now','Too many wrong passwords. They unlock by themselves after '.LOGIN_LOCK_MINUTES.' minutes, or you can unlock them now.',$locked];
 else $good[]='No account is locked';
 // 2. Sections whose teacher is turned off
 $orphan=rows("SELECT s.id,s.grade_level,s.name,t.id tid,t.name tname,(SELECT COUNT(*) FROM pupil_sections ps JOIN users p ON p.id=ps.pupil_id AND p.active=1 WHERE ps.section_id=s.id) pupils FROM sections s JOIN users t ON t.id=s.teacher_id WHERE t.active=0".ck_live_sql().' ORDER BY s.grade_level,s.name');
 $orphan=array_values(array_filter($orphan,fn($s)=>(int)$s['pupils']>0));
 if($orphan)$issues[]=['orphan','bad','people',count($orphan).' section'.(count($orphan)===1?' has':'s have').' a turned-off teacher','Nobody can check these pupils’ work. Move the pupils to another teacher, or turn the teacher on again.',$orphan];
 else $good[]='Every section has a teacher who can sign in';
 // 3. Possible duplicate pupils (same name, both accounts turned on)
 $pupils=rows('SELECT u.id,u.public_id,u.name,u.created_at,pp.grade_level pgrade,s.id sid,s.grade_level,s.name sname,t.name tname,d.lrn FROM users u LEFT JOIN pupils pp ON pp.user_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN sections s ON s.id=ps.section_id'.ck_live_sql().' LEFT JOIN users t ON t.id=s.teacher_id LEFT JOIN pupil_details d ON d.pupil_id=u.id WHERE u.role=\'pupil\' AND u.active=1 ORDER BY u.name,u.id');
 $by=[];foreach($pupils as $p){$k=ck_name_key((string)$p['name']);if($k!=='')$by[$k][]=$p;}
 $skip=ck_not_dupes();$dupes=[];foreach($by as $k=>$g)if(count($g)>1&&!in_array($k,$skip,true))$dupes[$k]=$g;
 if($dupes){$n=count($dupes);$issues[]=['dupes','warn','people',$n.' pupil'.($n===1?' may have':'s may have').' two accounts','Same name on two accounts. Their progress may be split. Compare them, then turn off the extra account, or mark them as different pupils.',$dupes];}
 else $good[]='No pupil seems to have two accounts';
 // 4. Pupils with no section
 $nosec=array_values(array_filter($pupils,fn($p)=>!$p['sid']));
 if($nosec)$issues[]=['nosec','warn','school',count($nosec).' pupil'.(count($nosec)===1?' has':'s have').' no section','Their teacher cannot see them until they are placed in a section.',$nosec];
 else $good[]='Every pupil is in a section';
 // 5. Never signed in (accounts older than 30 days)
 $old=date('Y-m-d H:i:s',strtotime('-'.CHECKUP_NEVER_DAYS.' days'));
 $never=rows("SELECT u.id,u.public_id,u.name,u.role,u.created_at,tp.teacher_id tid,t.name tname FROM users u LEFT JOIN teacher_pupils tp ON tp.pupil_id=u.id LEFT JOIN users t ON t.id=tp.teacher_id WHERE u.role IN ('pupil','teacher') AND u.active=1 AND u.created_at<? ORDER BY u.role DESC,t.name,u.name",[$old]);
 $never=array_values(array_filter($never,fn($r)=>!isset($logins[(int)$r['id']])));
 if($never){$np=count(array_filter($never,fn($r)=>$r['role']==='pupil'));$nt=count($never)-$np;
  $issues[]=['never','warn','clock',trim(($np?$np.' pupil'.($np===1?'':'s'):'').($np&&$nt?' and ':'').($nt?$nt.' teacher'.($nt===1?'':'s'):'')).' never signed in','Their accounts are more than '.CHECKUP_NEVER_DAYS.' days old. A printed Reading Pass helps pupils sign in. Teachers can print one from My pupils.',$never];}
 else $good[]='Everyone has signed in at least once';
 // 6. Teachers with no section
 $nosecT=rows("SELECT u.id,u.name,u.public_id,u.created_at FROM users u WHERE u.role='teacher' AND u.active=1 AND NOT EXISTS(SELECT 1 FROM sections s WHERE s.teacher_id=u.id".ck_live_sql().') ORDER BY u.name');
 if($nosecT)$issues[]=['nosect','warn','user',count($nosecT).' teacher'.(count($nosecT)===1?' has':'s have').' no section','A teacher adds sections on the My sections page. You can also move a section to them from the Teachers page.',$nosecT];
 else $good[]='Every teacher has a section';
 // 7. Administrators without a PIN
 try{$nopin=rows("SELECT u.id,u.name FROM users u LEFT JOIN admin_security a ON a.user_id=u.id WHERE u.role='admin' AND u.active=1 AND (a.pin_hash IS NULL OR a.pin_hash='')");}catch(Throwable $e){$nopin=[];}
 if($nopin)$issues[]=['nopin','warn','key',count($nopin).' administrator'.(count($nopin)===1?' has':'s have').' no PIN yet','BULIG asks for a PIN the next time they sign in.',$nopin];
 else $good[]='Every administrator has a PIN';
 $off=(int)val("SELECT COUNT(*) FROM users WHERE role IN ('pupil','teacher') AND active=0");
 $nP=count($pupils);$nT=(int)val("SELECT COUNT(*) FROM users WHERE role='teacher' AND active=1");
 return ['issues'=>$issues,'good'=>$good,'off'=>$off,'pupils'=>$nP,'teachers'=>$nT,'bad'=>count(array_filter($issues,fn($i)=>$i[1]==='bad')),'warn'=>count(array_filter($issues,fn($i)=>$i[1]==='warn'))];
}

function ck_btn(string $action,array $fields,string $label,string $icon,string $cls='secondary',string $confirm=''):string{
 $h='<form method="post" class="ck-form">'.csrf_field().'<input type="hidden" name="action" value="'.e($action).'">';foreach($fields as $k=>$v)$h.='<input type="hidden" name="'.e($k).'" value="'.e((string)$v).'">';
 return $h.'<button type="submit" class="btn '.$cls.'"'.($confirm!==''?' data-confirm="'.e($confirm).'"':'').'>'.icon($icon).e($label).'</button></form>';
}
function ck_chips(array $list,callable $fmt,int $max=8):string{$h='<div class="ck-chips">';foreach(array_slice($list,0,$max) as $r)$h.='<span>'.e($fmt($r)).'</span>';if(count($list)>$max)$h.='<span class="ck-more-n">and '.(count($list)-$max).' more</span>';return $h.'</div>';}
function ck_mins(int $s):string{$m=max(1,(int)ceil($s/60));return $m.' min left';}

function checkup_issue_html(array $i,array $sections,array $logins):string{
 [$id,$lv,$ic,$title,$text,$data]=$i;$act='';$extra='';
 if($id==='locked'){
  $extra=ck_chips($data,fn($l)=>$l['name'].' · '.$l['role'].' · '.ck_mins((int)$l['secs']));
  $act=ck_btn('admin_ck_unlock',['id'=>'all'],count($data)>1?'Unlock all':'Unlock','lock','primary');
 }elseif($id==='orphan'){
  $extra=ck_chips($data,fn($s)=>'Grade '.(int)$s['grade_level'].' – '.$s['name'].' · '.$s['tname'].' · '.(int)$s['pupils'].' pupil'.((int)$s['pupils']===1?'':'s'));
  $act='<a class="btn primary" href="?page=accounts&amp;from=s'.(int)$data[0]['id'].'#move">'.icon('swap').'Move pupils</a>';
 }elseif($id==='dupes'){
  $extra='<details class="ck-details"><summary class="btn secondary">'.icon('eye').'Compare</summary><div class="ck-dupes">';
  foreach(array_slice($data,0,20,true) as $k=>$g){
   $extra.='<div class="ck-dupe"><div class="ck-dupe-hd"><b>'.e($g[0]['name']).'</b>'.ck_btn('admin_ck_not_dupe',['key'=>$k],'These are different pupils','check','secondary').'</div><div class="ck-dupe-grid">';
   foreach($g as $p){$last=$logins[(int)$p['id']]??null;$done=(int)val('SELECT COUNT(*) FROM activity_completion WHERE pupil_id=?',[$p['id']]);
    $extra.='<div class="ck-acc"><dl><dt>Pupil ID</dt><dd>'.e($p['public_id']).'</dd><dt>Section</dt><dd>'.e(ck_section_label($p)).'</dd><dt>Teacher</dt><dd>'.e($p['tname']?:'—').'</dd><dt>LRN</dt><dd>'.e($p['lrn']?:'—').'</dd><dt>Activities done</dt><dd>'.$done.'</dd><dt>Last sign-in</dt><dd>'.e(admin_when($last)).'</dd><dt>Made on</dt><dd>'.e(date('M j, Y',strtotime((string)$p['created_at']))).'</dd></dl>'
     .ck_btn('admin_ck_toggle_pupil',['id'=>$p['id']],'Turn off this account','close','secondary','Turn off '.$p['name'].' ('.$p['public_id'].')? This account will not be able to sign in. Its work stays saved, and you can turn it on again from this page.').'</div>';}
   $extra.='</div></div>';}
  $extra.='</div></details>';
 }elseif($id==='nosec'){
  $opts='';foreach($sections as $s)$opts.='<option value="'.(int)$s['id'].'" data-g="'.(int)$s['grade_level'].'">Grade '.(int)$s['grade_level'].' – '.e($s['name']).' · '.e($s['tname']).'</option>';
  $extra='<details class="ck-details"'.(count($data)<=3?' open':'').'><summary class="btn secondary">'.icon('school').'Place them</summary><div class="ck-place">';
  if(!$sections)$extra.='<p class="muted">There are no sections yet. Teachers add sections on their My sections page.</p>';
  foreach(array_slice($data,0,40) as $p){
   $sel=preg_replace('/(data-g="'.(int)$p['pgrade'].'")/','$1 selected',$opts,1);
   $extra.='<form method="post" class="ck-place-row">'.csrf_field().'<input type="hidden" name="action" value="admin_ck_place"><input type="hidden" name="id" value="'.(int)$p['id'].'"><span><b>'.e($p['name']).'</b><small>'.e($p['public_id']).($p['pgrade']?' · Grade '.(int)$p['pgrade']:'').'</small></span><label class="sr-only" for="ck-s'.(int)$p['id'].'">Section for '.e($p['name']).'</label><select id="ck-s'.(int)$p['id'].'" name="section_id" required>'.$sel.'</select><button type="submit" class="btn primary"'.($sections?'':' disabled').'>'.icon('check').'Place</button></form>';}
  if(count($data)>40)$extra.='<p class="muted">Showing 40 of '.count($data).'. Place these first, then check again.</p>';
  $extra.='</div></details>';
 }elseif($id==='never'){
  $extra=ck_chips($data,fn($r)=>$r['name'].' · '.($r['role']==='teacher'?'teacher':($r['tname']?:'no teacher')));
  $told=admin_setting('checkup_told_on','')===date('Y-m-d');
  $hasPupils=(bool)array_filter($data,fn($r)=>$r['role']==='pupil'&&$r['tid']);
  if($hasPupils)$act=$told?'<span class="ws-pill ok">'.icon('check').'Teachers told today</span>':ck_btn('admin_ck_tell',[],'Tell their teachers','bell','secondary');
 }elseif($id==='nosect'){
  $extra=ck_chips($data,fn($t)=>$t['name'].' · added '.date('M j',strtotime((string)$t['created_at'])));
  $act='<a class="btn secondary" href="?page=accounts">'.icon('people').'Open Teachers</a>';
 }elseif($id==='nopin'){
  $extra=ck_chips($data,fn($a)=>$a['name']);
 }
 return '<div class="ck-issue ck-'.$lv.'" id="ck-'.$id.'"><span class="ck-ic">'.icon($ic).'</span><div class="ck-body"><b>'.e($title).'</b><span>'.e($text).'</span>'.$extra.'</div>'.($act!==''?'<div class="ck-act">'.$act.'</div>':'').'</div>';
}

function checkup_view():void{
 $d=checkup_data();$logins=admin_last_logins();
 $sections=rows('SELECT s.id,s.grade_level,s.name,t.name tname FROM sections s JOIN users t ON t.id=s.teacher_id AND t.active=1 WHERE 1'.ck_live_sql().' ORDER BY s.grade_level,s.name');
 admin_heading('ACCOUNTS · CHECK-UP','Keep accounts tidy.','BULIG checked '.number_format($d['pupils']).' pupil'.($d['pupils']===1?'':'s').' and '.$d['teachers'].' teacher'.($d['teachers']===1?'':'s').' just now.','<a class="btn secondary" href="?page=checkup">'.icon('replay').'Check again</a>');
 echo admin_kpis([['alert',$d['bad'],'Need action now',$d['bad']?'Fix these first':'Nothing urgent',(bool)$d['bad']],['clock',$d['warn'],'Worth a look','',false],['check',count($d['good']),'All good','',false],['shield',date('g:i A'),'Last check','Today',false]]);
 echo '<div class="ws-g2 ck-grid"><section class="card ck-card"><div class="ws-hd"><h2>'.icon('alert').' Things to fix</h2><small class="muted">Most urgent first</small></div>';
 if(!$d['issues'])echo '<div class="ck-clear">'.icon('check').'<div><b>Everything looks good.</b><span>No account problems right now.</span></div></div>';
 foreach($d['issues'] as $i)echo checkup_issue_html($i,$sections,$logins);
 echo '</section><section class="card ck-card"><div class="ws-hd"><h2>'.icon('check').' All good</h2></div><div class="ck-good">';
 foreach($d['good'] as $g)echo '<div>'.icon('check').'<span>'.e($g).'</span></div>';
 if(!$d['good'])echo '<p class="muted">Nothing here yet.</p>';
 echo '</div>'.($d['off']?'<p class="ck-note">'.icon('info').'<span>'.$d['off'].' account'.($d['off']===1?' is':'s are').' turned off. They cannot sign in. Their work stays saved for reports.</span></p>':'').checkup_off_pupils().'<p class="ck-note">'.icon('replay').'<span>The check runs each time you open this page. Problems also show on the Overview page.</span></p></section></div>';
}

/** Turned-off pupils, so a pupil turned off by mistake can be turned on again here. Teachers are turned on from the Teachers page. */
function checkup_off_pupils():string{
 $off=rows("SELECT u.id,u.name,u.public_id,s.grade_level,s.name sname FROM users u LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN sections s ON s.id=ps.section_id WHERE u.role='pupil' AND u.active=0 ORDER BY u.name LIMIT 60");
 if(!$off)return '';
 $h='<details class="ck-details ck-off" id="ck-off"><summary class="btn secondary">'.icon('eye').'Show turned-off pupils</summary><div class="ck-place">';
 foreach($off as $p)$h.='<div class="ck-place-row ck-off-row"><span><b>'.e($p['name']).'</b><small>'.e($p['public_id']).' · '.e($p['grade_level']?'Grade '.(int)$p['grade_level'].' – '.$p['sname']:'No section').'</small></span>'.ck_btn('admin_ck_toggle_pupil',['id'=>$p['id']],'Turn on','check','secondary','Turn on '.$p['name'].' ('.$p['public_id'].')? They will be able to sign in again.').'</div>';
 return $h.'</div></details>';
}

/** One line for the Overview page's "Needs attention" list. */
function checkup_overview_item():string{
 try{$d=checkup_data();}catch(Throwable $e){return '';}
 $n=$d['bad']+$d['warn'];
 if($d['bad'])return '<div class="ws-li bad">'.icon('shield').'<span><b>Accounts: '.$d['bad'].' need'.($d['bad']===1?'s':'').' action now</b><small>'.e($d['issues'][0][3]).'</small></span><a class="ws-sp" href="?page=checkup">Check-up</a></div>';
 if($n)return '<div class="ws-li warn">'.icon('shield').'<span><b>Accounts: '.$n.' thing'.($n===1?'':'s').' worth a look</b><small>'.e($d['issues'][0][3]).'</small></span><a class="ws-sp" href="?page=checkup">Check-up</a></div>';
 return '<div class="ws-li">'.icon('check').'<span><b>Accounts: all good</b></span><a class="ws-sp" href="?page=checkup">Check-up</a></div>';
}

function checkup_action(string $action):never{
 $u=require_role('admin');
 if($action==='admin_ck_unlock'){
  $id=(string)($_POST['id']??'');$d=checkup_data();$locked=[];foreach($d['issues'] as $i)if($i[0]==='locked')$locked=$i[5];
  $n=0;foreach($locked as $l){if($id!=='all'&&(int)$id!==(int)$l['id'])continue;q('DELETE FROM login_attempts WHERE identifier_hash=?',[hash('sha256',strtolower((string)$l['public_id']))]);audit('admin_unlock_account',$l['public_id']);$n++;}
  flash($n?($n===1?'Unlocked. They can sign in now.':$n.' accounts unlocked. They can sign in now.'):'Nothing to unlock. The lock may have ended already.');go('?page=checkup#ck-locked');
 }
 if($action==='admin_ck_place'){
  $pid=(int)($_POST['id']??0);$sid=(int)($_POST['section_id']??0);
  $p=one("SELECT u.id,u.name,u.public_id FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.id=? AND u.role='pupil'",[$pid]);if(!$p)fail('Choose a pupil from the list.');
  $s=one('SELECT s.id,s.grade_level,s.name,s.teacher_id FROM sections s JOIN users t ON t.id=s.teacher_id AND t.active=1 WHERE s.id=?'.ck_live_sql(),[$sid]);if(!$s)fail('Choose a section from the list.');
  db()->beginTransaction();
  try{
   q('UPDATE pupils SET grade_level=?,section=? WHERE user_id=?',[$s['grade_level'],$s['name'],$pid]);
   q('INSERT INTO pupil_sections VALUES(?,?) ON DUPLICATE KEY UPDATE section_id=VALUES(section_id)',[$pid,$s['id']]);
   q('INSERT INTO teacher_pupils VALUES(?,?) ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)',[$s['teacher_id'],$pid]);
   if(!val('SELECT 1 FROM pupil_level_assignments WHERE pupil_id=?',[$pid]))q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,1,?)',[$pid,$s['teacher_id']]);
   audit('admin_place_pupil',mb_substr($p['public_id'].' → Grade '.$s['grade_level'].' '.$s['name'],0,200));db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  notify((int)$s['teacher_id'],'people','A pupil joined your section',$p['name'].' is now in Grade '.$s['grade_level'].' – '.$s['name'].'.','?page=manage');
  flash($p['name'].' is now in Grade '.$s['grade_level'].' – '.$s['name'].'.');go('?page=checkup#ck-nosec');
 }
 if($action==='admin_ck_toggle_pupil'){
  $p=one("SELECT id,name,public_id,active FROM users WHERE id=? AND role='pupil'",[(int)($_POST['id']??0)]);if(!$p)fail('Choose a pupil from the list.');
  q('UPDATE users SET active=1-active WHERE id=?',[$p['id']]);if($p['active'])saved_login_clear((int)$p['id']);audit('admin_toggle_pupil',$p['public_id'].($p['active']?' off':' on'));
  flash($p['name'].' ('.$p['public_id'].') is now '.($p['active']?'turned off.':'turned on.'));go('?page=checkup'.($p['active']?'#ck-dupes':'#ck-off'));
 }
 if($action==='admin_ck_not_dupe'){
  $k=ck_name_key((string)($_POST['key']??''));if($k==='')fail('Choose a name from the list.');
  $list=ck_not_dupes();if(!in_array($k,$list,true))$list[]=$k;admin_set('checkup_not_dupes',json_encode(array_slice($list,-500)));audit('admin_not_duplicate',mb_substr($k,0,150));
  flash('Got it. BULIG will not ask about these pupils again.');go('?page=checkup#ck-dupes');
 }
 if($action==='admin_ck_tell'){
  if(admin_setting('checkup_told_on','')===date('Y-m-d')){flash('Teachers were already told today.');go('?page=checkup#ck-never');}
  $d=checkup_data();$never=[];foreach($d['issues'] as $i)if($i[0]==='never')$never=$i[5];
  $by=[];foreach($never as $r)if($r['role']==='pupil'&&$r['tid'])$by[(int)$r['tid']][]=$r['name'];
  foreach($by as $tid=>$names){$n=count($names);notify($tid,'people',$n.' pupil'.($n===1?' has':'s have').' never signed in','Print a Reading Pass for '.implode(', ',array_slice($names,0,6)).($n>6?' and '.($n-6).' more':'').'.','?page=cards');}
  admin_set('checkup_told_on',date('Y-m-d'));audit('admin_tell_teachers',count($by).' teachers');
  flash($by?'Sent to '.count($by).' teacher'.(count($by)===1?'':'s').'.':'No teacher to tell.');go('?page=checkup#ck-never');
 }
 fail('Unknown action.');
}
