<?php
/** Administrator workspace: Overview, Teachers, Reports, Lesson studio, Activity log, System health, Settings. */

/* ---------------------------------------------------------------- small helpers */
function admin_setting(string $key,string $default=''):string{
 try{$v=val('SELECT setting_value FROM settings WHERE setting_key=?',[$key]);}catch(Throwable $e){return $default;}
 return $v===null||$v===false?$default:(string)$v;
}
function admin_set(string $key,string $value):void{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);}
function admin_when(?string $dt):string{
 if(!$dt)return 'Never';$t=strtotime($dt);$d=(int)floor((strtotime(date('Y-m-d'))-strtotime(date('Y-m-d',$t)))/86400);
 if($d===0)return 'Today, '.date('g:i A',$t);if($d===1)return 'Yesterday, '.date('g:i A',$t);if($d<7)return date('D, g:i A',$t);return date('M j, Y',$t);
}
function admin_days_since(?string $dt):?int{return $dt?(int)floor((time()-strtotime($dt))/86400):null;}
function admin_kpis(array $items):string{
 $s='<div class="ws-kpis">';foreach($items as [$icon,$value,$label,$note,$bad]){$s.='<div class="ws-kpi">'.icon($icon).'<b>'.e((string)$value).'</b><span>'.e($label).'</span>'.($note!==''?'<em class="'.($bad?'bad':'').'">'.e($note).'</em>':'').'</div>';}
 return $s.'</div>';
}
function admin_heading(string $eyebrow,string $title,string $sub,string $buttons=''):void{
 echo '<div class="pageheading ws-head"><div><span class="eyebrow">'.e($eyebrow).'</span><h1>'.e($title).'</h1><p class="muted">'.e($sub).'</p></div>'.($buttons?'<div class="ws-btns">'.$buttons.'</div>':'').'</div>';
}
function admin_last_logins():array{static $m;if($m===null){$m=[];foreach(rows("SELECT actor_id,MAX(created_at) t FROM audit_log WHERE action='login' AND actor_id IS NOT NULL GROUP BY actor_id") as $r)$m[(int)$r['actor_id']]=$r['t'];}return $m;}
function admin_live_sections_sql():string{return sections_archive_supported()?' AND s.archived_at IS NULL':'';}
function admin_school_year():string{$y=(int)date('Y');if((int)date('n')<6)$y--;$s=trim(admin_setting('school_year',''));return $s!==''?$s:$y.'–'.($y+1);}
function admin_inactive_pupils(int $days=7):int{
 return (int)val("SELECT COUNT(*) FROM users u WHERE u.role='pupil' AND u.active=1 AND NOT EXISTS(SELECT 1 FROM learning_days d WHERE d.pupil_id=u.id AND d.day>=?)",[date('Y-m-d',strtotime('-'.($days-1).' days'))]);
}
function admin_announcements(bool $activeOnly=false):array{
 $list=json_decode(admin_setting('announcements','[]'),true);$list=is_array($list)?$list:[];
 if($activeOnly)$list=array_values(array_filter($list,fn($a)=>($a['until']??'')===''||$a['until']>=date('Y-m-d')));
 return $list;
}
/** Banner shown to teachers on their Overview. */
function teacher_announcements():void{
 foreach(admin_announcements(true) as $a)echo '<div class="ws-ann ws-ann-teacher" role="status">'.icon('mega').'<div><b>'.e($a['title']).'</b>'.($a['message']!==''?'<span>'.e($a['message']).'</span>':'').'</div></div>';
}

/* ---------------------------------------------------------------- Overview */
function admin_overview():void{
 $pupils=(int)val("SELECT COUNT(*) FROM users WHERE role='pupil' AND active=1");$newPupils=(int)val("SELECT COUNT(*) FROM users WHERE role='pupil' AND created_at>=?",[date('Y-m-01')]);
 $teachers=rows("SELECT id,name,active FROM users WHERE role='teacher' ORDER BY name");$logins=admin_last_logins();
 $idle=array_values(array_filter($teachers,fn($t)=>$t['active']&&(admin_days_since($logins[(int)$t['id']]??null)??999)>=14));
 $sections=(int)val('SELECT COUNT(*) FROM sections s WHERE 1'.admin_live_sections_sql());$grades=rows('SELECT DISTINCT grade_level g FROM sections s WHERE 1'.admin_live_sections_sql().' ORDER BY g');
 $week=(int)val('SELECT COUNT(*) FROM activity_completion WHERE submitted_at>=?',[date('Y-m-d H:i:s',strtotime('-7 days'))]);$prev=(int)val('SELECT COUNT(*) FROM activity_completion WHERE submitted_at>=? AND submitted_at<?',[date('Y-m-d H:i:s',strtotime('-14 days')),date('Y-m-d H:i:s',strtotime('-7 days'))]);
 $trend=$prev?(($week>=$prev?'+':'').round(($week-$prev)/$prev*100).'% vs last week'):($week?'first week of activity':'');
 admin_heading('ADMINISTRATOR OVERVIEW','Make room for learning.','School-wide view · School year '.admin_school_year().' · Updated '.date('g:i A'),'<a class="btn secondary" href="?page=settings&amp;tab=announcements">'.icon('mega').'Post announcement</a><a class="btn primary" href="?page=reports">'.icon('chart').'Open reports</a>');
 echo admin_kpis([['people',number_format($pupils),'Pupils',$newPupils?'+'.$newPupils.' this month':'',false],['user',count($teachers),'Teachers',$idle?count($idle).' inactive 14+ days':'all active',(bool)$idle],['book',$sections,'Sections',$grades?'Grades '.implode(', ',array_column($grades,'g')):'none yet',false],['check',number_format($week),'Activities this week',$trend,$prev&&$week<$prev]]);
 // pupils at each level: the highest level with work, or the starting level
 $levels=[];foreach(rows("SELECT GREATEST(a.level_id,COALESCE((SELECT MAX(m.level_id) FROM activity_completion c JOIN activities ac ON ac.id=c.activity_id JOIN lessons l ON l.id=ac.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=u.id),0)) lv FROM users u JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE u.role='pupil' AND u.active=1") as $r)$levels[(int)$r['lv']]=($levels[(int)$r['lv']]??0)+1;
 $max=max([1]+$levels);
 echo '<div class="ws-g2"><section class="card"><div class="ws-hd"><h2>Pupils at each level</h2><a href="?page=reports">View report '.icon('arrow').'</a></div><div class="ws-bars">';
 foreach(rows('SELECT id FROM bulig_levels ORDER BY id') as $l){$n=$levels[(int)$l['id']]??0;echo '<div class="ws-bar"><span>'.e(level_label((int)$l['id'])).'</span><u><i class="w-'.(int)(round($n/$max*20)*5).'"></i></u><b>'.$n.'</b></div>';}
 echo '</div></section><section class="card"><div class="ws-hd"><h2>Needs attention</h2></div><div class="ws-list">';
 $inactive=admin_inactive_pupils(7);$top=one("SELECT CONCAT('Grade ',s.grade_level,' · ',s.name) n,COUNT(*) c FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN sections s ON s.id=ps.section_id WHERE u.role='pupil' AND u.active=1 AND NOT EXISTS(SELECT 1 FROM learning_days d WHERE d.pupil_id=u.id AND d.day>=?) GROUP BY s.id ORDER BY c DESC LIMIT 1",[date('Y-m-d',strtotime('-6 days'))]);
 $failed=(int)val("SELECT COUNT(*) FROM audit_log WHERE action IN ('login_failed','admin_pin_failed') AND created_at>=?",[date('Y-m-d 00:00:00')]);$failedPin=(int)val("SELECT COUNT(*) FROM audit_log WHERE action='admin_pin_failed' AND created_at>=?",[date('Y-m-d 00:00:00')]);
 $health=admin_health_checks();$bad=array_filter($health,fn($c)=>!$c[1]);
 $item=fn($cls,$icon,$html,$link,$href)=>'<div class="ws-li '.$cls.'">'.icon($icon).'<span>'.$html.'</span><a class="ws-sp" href="'.$href.'">'.$link.'</a></div>';
 echo $idle?$item('warn','user','<b>'.count($idle).' teacher'.(count($idle)===1?'':'s').'</b> haven’t signed in for 14+ days<small>'.e(implode(' · ',array_slice(array_column($idle,'name'),0,3))).(count($idle)>3?' …':'').'</small>','View','?page=accounts&amp;status=idle'):$item('','check','<b>All teachers</b> signed in within 14 days','View','?page=accounts');
 echo $inactive?$item('warn','people','<b>'.$inactive.' pupil'.($inactive===1?'':'s').'</b> inactive for 7+ days'.($top?'<small>Most in '.e($top['n']).'</small>':''),'View','?page=reports'):$item('','check','<b>All pupils</b> learned in the last 7 days','View','?page=reports');
 echo $item($failed?'bad':'','lock','<b>'.$failed.' wrong password'.($failed===1?'':'s').'</b> today'.($failedPin?'<small>'.$failedPin.' on admin PIN</small>':''),'Log','?page=activity_log&amp;tab=security&amp;days=1');
 echo checkup_overview_item();
 echo $item($bad?'warn':'','heart','<b>System health: '.($bad?count($bad).' to check':'all good').'</b><small>'.e(admin_backup_note()).'</small>','Open','?page=health');
 echo '</div></section></div><div class="ws-g3">';
 echo '<section class="card"><div class="ws-hd"><h2>Most active sections</h2><small class="muted">last 30 days</small></div><div class="ws-list">';
 $act=rows("SELECT CONCAT('Grade ',s.grade_level,' · ',s.name) n,COUNT(*) c FROM activity_completion c JOIN pupil_sections ps ON ps.pupil_id=c.pupil_id JOIN sections s ON s.id=ps.section_id WHERE c.submitted_at>=? GROUP BY s.id ORDER BY c DESC LIMIT 3",[date('Y-m-d',strtotime('-30 days'))]);
 foreach($act as $a)echo '<div class="ws-li">'.icon('star').'<span>'.e($a['n']).'</span><span class="ws-sp ws-pill ok">'.number_format((int)$a['c']).'</span></div>';if(!$act)echo '<p class="muted">No activity in the last 30 days yet.</p>';
 echo '</div></section><section class="card"><div class="ws-hd"><h2>Announcement</h2><a href="?page=settings&amp;tab=announcements">Edit</a></div>';
 $ann=admin_announcements(true);if($ann){$a=$ann[0];echo '<div class="ws-ann"><b>'.e($a['title']).'</b>'.e($a['message']).'<small> · Shown to all teachers'.($a['until']?' until '.e(date('M j',strtotime($a['until']))):'').'</small></div>';}else echo '<p class="muted">No announcement. Post one for all teachers in Settings.</p>';
 echo '</section><section class="card"><div class="ws-hd"><h2>Quick actions</h2></div><div class="ws-list"><a class="ws-li link" href="?page=accounts&amp;add=1">'.icon('user').'<span>Add a teacher</span><span class="ws-sp">'.icon('arrow').'</span></a><a class="ws-li link" href="?page=health#backup">'.icon('download').'<span>Download backup</span><span class="ws-sp">'.icon('arrow').'</span></a><a class="ws-li link" href="?page=settings&amp;tab=year">'.icon('cal').'<span>End of school year</span><span class="ws-sp">'.icon('arrow').'</span></a></div></section></div>';
}

/* ---------------------------------------------------------------- Teachers */
function admin_teachers_view(array $u):void{
 $logins=admin_last_logins();$q=trim((string)($_GET['q']??''));$status=(string)($_GET['status']??'all');$grade=(int)($_GET['grade']??0);
 $list=rows("SELECT u.*,(SELECT COUNT(*) FROM teacher_pupils t JOIN users p ON p.id=t.pupil_id AND p.active=1 WHERE t.teacher_id=u.id) pupils FROM users u WHERE u.role='teacher' ORDER BY u.name");
 $secs=[];foreach(rows('SELECT s.*,(SELECT COUNT(*) FROM pupil_sections ps WHERE ps.section_id=s.id) n FROM sections s WHERE 1'.admin_live_sections_sql().' ORDER BY s.grade_level,s.name') as $s)$secs[(int)$s['teacher_id']][]=$s;
 $cards=cards_ready();$starter=teacher_starter_ids();$nt=(int)($_SESSION['new_teacher']??0);unset($_SESSION['new_teacher']);if($nt)echo new_teacher_overlay($nt);
 admin_heading('TEACHERS','Your teaching team.','Add teachers, print their Teacher Pass, give a new starter password, and move pupils when a teacher transfers.','<a class="btn secondary" href="?page=teacher_cards">'.icon('key').'Teacher Passes</a><a class="btn secondary" href="?page=teacher_import">'.icon('people').'Add many teachers</a><a class="btn primary" href="?page=accounts&amp;add=1#add-teacher">'.icon('user').'Add a teacher</a>');
 echo '<details class="card ws-add" id="add-teacher" '.(isset($_GET['add'])?'open':'').'><summary>'.icon('user').'Add a teacher</summary><form class="ws-formrow" method="post">'.csrf_field().'<input type="hidden" name="action" value="create_account"><label>Full name<input name="name" required maxlength="150"></label>'.($cards?'':'<label>Password (8–72 characters)<input name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"></label>').'<label>Sex<select name="teacher_sex" required><option value="">Choose Male or Female</option><option value="female">Female</option><option value="male">Male</option></select></label><button class="btn primary">Create teacher account</button></form>'
  .($cards?'<p class="tc-info">'.icon('key').'<span><b>No password to type.</b> BULIG makes the Teacher ID and a starter password, and shows the Teacher Pass to print. The teacher must make their own password the first time they sign in.</span></p>':'<p class="muted">A unique Teacher ID is made automatically. Give the ID and password to the teacher privately. To use Teacher Passes and starter passwords, import database/migrations/018_pupil_cards.sql.</p>').'</details>';
 echo '<form class="ws-filters" method="get"><input type="hidden" name="page" value="accounts"><input name="q" value="'.e($q).'" placeholder="Search name or ID"><select name="grade"><option value="0">All grades</option>';for($g=1;$g<=6;$g++)echo '<option value="'.$g.'" '.($grade===$g?'selected':'').'>Grade '.$g.'</option>';
 echo '</select><select name="status">';foreach(['all'=>'All statuses','active'=>'Active','idle'=>'Inactive 14+ days','off'=>'Turned off'] as $k=>$v)echo '<option value="'.$k.'" '.($status===$k?'selected':'').'>'.$v.'</option>';echo '</select><button class="btn secondary">Filter</button>';
 $shown=array_values(array_filter($list,function($t)use($q,$status,$grade,$logins,$secs){
  if($q!==''&&stripos($t['name'].' '.$t['public_id'],$q)===false)return false;
  $idle=(admin_days_since($logins[(int)$t['id']]??null)??999)>=14;
  if($status==='active'&&(!$t['active']||$idle))return false;if($status==='idle'&&(!$t['active']||!$idle))return false;if($status==='off'&&$t['active'])return false;
  if($grade&&!array_filter($secs[(int)$t['id']]??[],fn($s)=>(int)$s['grade_level']===$grade))return false;return true;}));
 echo '<span class="ws-sp muted">'.count($shown).' of '.count($list).' teachers</span></form>';
 echo '<section class="card"><div class="tablewrap"><table class="ws-table"><thead><tr><th>Teacher</th><th>Sections</th><th>Pupils</th><th>Last sign-in</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
 foreach($shown as $t){$last=$logins[(int)$t['id']]??null;$days=admin_days_since($last);
  [$cls,$label]=!$t['active']?['off','Turned off']:(($days??999)>=14?['warn',$last?'Inactive '.$days.' days':'Never signed in']:['ok','Active']);
  $sl=$secs[(int)$t['id']]??[];
  echo '<tr><td><strong>'.e($t['name']).'</strong><small>'.e($t['public_id']).'</small></td><td>'.($sl?e(implode(', ',array_map(fn($s)=>'Grade '.$s['grade_level'].' · '.$s['name'],$sl))):'—').'</td><td><b>'.(int)$t['pupils'].'</b></td><td>'.e(admin_when($last)).'</td><td><span class="ws-pill '.$cls.'">'.$label.'</span>'.(in_array((int)$t['id'],$starter,true)?'<span class="ws-pill warn tc-st">Starter password</span>':'').'</td><td><div class="ws-acts">';
  if($cards)echo '<a href="?page=teacher_cards&amp;t='.(int)$t['id'].'">'.icon('key').'Teacher Pass</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_teacher_starter"><input type="hidden" name="id" value="'.$t['id'].'"><button type="submit" data-confirm="Make a new starter password for '.e($t['name']).'? Their old password and QR code stop working, and they make a new password at the next sign-in.">'.icon('replay').'New starter password</button></form>';
  else echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_reset_password"><input type="hidden" name="id" value="'.$t['id'].'"><button type="submit" data-confirm="Make a new password for '.e($t['name']).'?">'.icon('key').'Reset password</button></form>';
  echo '<a href="?page=accounts&amp;from=t'.$t['id'].'#move">'.icon('swap').'Move pupils</a>';
  echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_toggle_teacher"><input type="hidden" name="id" value="'.$t['id'].'"><button type="submit" data-confirm="'.($t['active']?'Turn off '.e($t['name']).'? They will not be able to sign in.':'Turn on '.e($t['name']).'?').'">'.icon('lock').($t['active']?'Turn off':'Turn on').'</button></form>';
  echo '<details class="ws-more"><summary>'.icon('edit').'Edit name &amp; sex</summary><form method="post" class="ws-inline">'.csrf_field().'<input type="hidden" name="action" value="admin_rename_teacher"><input type="hidden" name="id" value="'.$t['id'].'"><input name="name" value="'.e($t['name']).'" required maxlength="150"><select name="teacher_sex" aria-label="Sex">'.(function($m){return '<option value="female"'.(!$m?' selected':'').'>Female</option><option value="male"'.($m?' selected':'').'>Male</option>';})(tf_is_male((int)$t['id'])).'</select><button class="btn primary small">Save</button></form></details>';
  echo '</div></td></tr>';}
 if(!$shown)echo '<tr><td colspan="6">No teachers match this filter.</td></tr>';
 echo '</tbody></table></div></section>';
 // move pupils
 $from=(string)($_GET['from']??'');
 echo '<section class="card" id="move"><div class="ws-hd"><h2>'.icon('swap').' Move pupils to another teacher</h2></div><form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_move_pupils"><div class="ws-formrow"><label>From<select name="from" required><option value="">Choose a class</option>';
 foreach($list as $t){$sl=$secs[(int)$t['id']]??[];if(!$t['pupils']&&!$sl)continue;echo '<optgroup label="'.e($t['name']).'"><option value="t'.$t['id'].'" '.($from==='t'.$t['id']?'selected':'').'>All of '.e($t['name']).'’s pupils ('.(int)$t['pupils'].')</option>';foreach($sl as $s)echo '<option value="s'.$s['id'].'" '.($from==='s'.$s['id']?'selected':'').'>Grade '.$s['grade_level'].' · '.e($s['name']).' ('.(int)$s['n'].' pupils)</option>';echo '</optgroup>';}
 echo '</select></label><label>To<select name="to" required><option value="">Choose a teacher</option>';foreach($list as $t)if($t['active'])echo '<option value="'.$t['id'].'">'.e($t['name']).' · '.e($t['public_id']).'</option>';
 echo '</select></label><button class="btn primary" data-confirm="Move these pupils to the chosen teacher?">'.icon('swap').'Move pupils</button></div><p class="muted">Pupils keep their progress, XP and badges. Their sections move with them.</p></form></section>';
}
function admin_new_password():string{$c='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';$p='';for($i=0;$i<10;$i++)$p.=$c[random_int(0,strlen($c)-1)];return $p;}
function admin_teacher(int $id):array{$t=one("SELECT * FROM users WHERE id=? AND role='teacher'",[$id]);if(!$t)fail('Teacher not found.',404);return $t;}
/** Move one section (and its pupils) to another teacher; merges into a same-named section if the teacher already has one. */
function admin_move_section(array $s,int $to):int{
 $pupils=array_map('intval',array_column(rows('SELECT pupil_id FROM pupil_sections WHERE section_id=?',[$s['id']]),'pupil_id'));
 $same=one('SELECT id FROM sections WHERE teacher_id=? AND grade_level=? AND name=?',[$to,$s['grade_level'],$s['name']]);
 if($same){q('UPDATE pupil_sections SET section_id=? WHERE section_id=?',[$same['id'],$s['id']]);q('DELETE FROM sections WHERE id=?',[$s['id']]);}
 else q('UPDATE sections SET teacher_id=? WHERE id=?',[$to,$s['id']]);
 foreach($pupils as $p)q('UPDATE teacher_pupils SET teacher_id=? WHERE pupil_id=?',[$to,$p]);
 return count($pupils);
}

/* ---------------------------------------------------------------- Reports */
function admin_period():array{
 $p=(string)($_GET['period']??'year');$y=(int)date('Y');if((int)date('n')<6)$y--;
 $map=['month'=>[date('Y-m-01'),'This month'],'quarter'=>[date('Y-m-d',strtotime('-3 months')),'Last 3 months'],'year'=>[$y.'-06-01','This school year'],'all'=>['2000-01-01','All time']];
 if(!isset($map[$p]))$p='year';return [$p,$map[$p][0],$map[$p][1]];
}
/** Lesson ids of pre-tests and post-tests (by their display label). */
function admin_test_lessons():array{
 static $r;if($r!==null)return $r;$r=['pre'=>[],'post'=>[]];
 foreach(rows('SELECT l.id,l.position,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id') as $l){$lab=lesson_display_label((int)$l['level_id'],(int)$l['position']);if(str_starts_with($lab,'Pre-'))$r['pre'][]=(int)$l['id'];elseif(str_starts_with($lab,'Post-'))$r['post'][]=(int)$l['id'];}
 return $r;
}
function admin_report_rows(string $start,int $grade,int $section):array{
 $tests=admin_test_lessons();$pre=$tests['pre']?implode(',',$tests['pre']):'0';$post=$tests['post']?implode(',',$tests['post']):'0';$week=date('Y-m-d',strtotime('-6 days'));
 $where='1'.admin_live_sections_sql();$args=[];if($grade){$where.=' AND s.grade_level=?';$args[]=$grade;}if($section){$where.=' AND s.id=?';$args[]=$section;}
 $out=[];
 foreach(rows("SELECT s.*,u.name teacher FROM sections s JOIN users u ON u.id=s.teacher_id WHERE $where ORDER BY s.grade_level,s.name",$args) as $s){
  $sid=(int)$s['id'];$base="FROM activity_completion c JOIN pupil_sections ps ON ps.pupil_id=c.pupil_id AND ps.section_id=$sid";
  $out[]=['section'=>'Grade '.$s['grade_level'].' · '.$s['name'],'teacher'=>$s['teacher'],
   'pupils'=>(int)val("SELECT COUNT(*) FROM pupil_sections ps JOIN users u ON u.id=ps.pupil_id AND u.active=1 WHERE ps.section_id=?",[$sid]),
   'activities'=>(int)val("SELECT COUNT(*) $base WHERE c.submitted_at>=?",[$start]),
   'avg'=>val("SELECT ROUND(AVG(c.score/c.max_score)*100) $base WHERE c.max_score>0 AND c.submitted_at>=?",[$start]),
   'pre'=>val("SELECT ROUND(AVG(c.score/c.max_score)*100) $base JOIN activities a ON a.id=c.activity_id WHERE c.max_score>0 AND a.lesson_id IN ($pre)"),
   'post'=>val("SELECT ROUND(AVG(c.score/c.max_score)*100) $base JOIN activities a ON a.id=c.activity_id WHERE c.max_score>0 AND a.lesson_id IN ($post)"),
   'lessons'=>(int)val("SELECT COUNT(*) FROM pupil_progress p JOIN pupil_sections ps ON ps.pupil_id=p.pupil_id AND ps.section_id=$sid WHERE p.completed_at>=?",[$start]),
   'inactive'=>(int)val("SELECT COUNT(*) FROM pupil_sections ps JOIN users u ON u.id=ps.pupil_id AND u.active=1 WHERE ps.section_id=$sid AND NOT EXISTS(SELECT 1 FROM learning_days d WHERE d.pupil_id=u.id AND d.day>=?)",[$week])];
 }
 return $out;
}
function admin_pct($v):string{return $v===null||$v===false?'—':(int)$v.'%';}
function admin_reports_view():void{
 [$period,$start,$plabel]=admin_period();$grade=(int)($_GET['grade']??0);$section=(int)($_GET['section']??0);$rows=admin_report_rows($start,$grade,$section);
 $qs='period='.$period.'&amp;grade='.$grade.'&amp;section='.$section;
 admin_heading('SCHOOL REPORTS','See the whole school at a glance.','Progress by grade and section · '.$plabel.' · ready to print for the principal or the division.','<a class="btn secondary" href="?page=report_csv&amp;'.$qs.'">'.icon('download').'Download Excel (CSV)</a><button type="button" class="btn primary" data-print>'.icon('book').'Print / Save as PDF</button>');
 $school=admin_setting('school_name','');echo '<div class="ws-print-head"><strong>'.e($school?:'BULIG reading program').'</strong><span>'.e(trim(admin_setting('school_district','').' · School year '.admin_school_year(),' ·')).' · '.e($plabel).' · Printed '.date('M j, Y').'</span></div>';
 echo '<form class="ws-filters" method="get"><input type="hidden" name="page" value="reports"><select name="period">';foreach(['month'=>'This month','quarter'=>'Last 3 months','year'=>'This school year','all'=>'All time'] as $k=>$v)echo '<option value="'.$k.'" '.($period===$k?'selected':'').'>'.$v.'</option>';
 echo '</select><select name="grade"><option value="0">All grades</option>';for($g=1;$g<=6;$g++)echo '<option value="'.$g.'" '.($grade===$g?'selected':'').'>Grade '.$g.'</option>';echo '</select><select name="section"><option value="0">All sections</option>';
 foreach(rows('SELECT s.* FROM sections s WHERE 1'.admin_live_sections_sql().($grade?' AND s.grade_level='.$grade:'').' ORDER BY s.grade_level,s.name') as $s)echo '<option value="'.$s['id'].'" '.($section===(int)$s['id']?'selected':'').'>Grade '.$s['grade_level'].' · '.e($s['name']).'</option>';
 echo '</select><button class="btn secondary">Show</button></form>';
 $pupils=array_sum(array_column($rows,'pupils'));$avgs=array_filter(array_column($rows,'avg'),fn($v)=>$v!==null&&$v!==false);
 echo admin_kpis([['people',number_format($pupils),'Pupils in report','',false],['check',$avgs?round(array_sum($avgs)/count($avgs)).'%':'—','Average score','',false],['book',number_format(array_sum(array_column($rows,'lessons'))),'Lessons completed',$plabel,false],['alert',array_sum(array_column($rows,'inactive')),'Need a check-in','no learning in 7 days',true]]);
 echo '<section class="card"><div class="tablewrap"><table class="ws-table"><thead><tr><th>Grade · Section</th><th>Teacher</th><th>Pupils</th><th>Activities</th><th>Avg score</th><th>Pre → Post test</th><th>Lessons completed</th><th>Inactive 7+ days</th></tr></thead><tbody>';
 foreach($rows as $r)echo '<tr><td><strong>'.e($r['section']).'</strong></td><td>'.e($r['teacher']).'</td><td>'.$r['pupils'].'</td><td>'.number_format($r['activities']).'</td><td>'.admin_pct($r['avg']).'</td><td>'.(($r['pre']===null||$r['pre']===false)&&($r['post']===null||$r['post']===false)?'—':admin_pct($r['pre']).' → '.admin_pct($r['post'])).'</td><td>'.$r['lessons'].'</td><td>'.($r['inactive']>5?'<span class="ws-pill warn">'.$r['inactive'].'</span>':$r['inactive']).'</td></tr>';
 if(!$rows)echo '<tr><td colspan="8">No sections match this filter yet.</td></tr>';
 echo '</tbody></table></div><p class="muted ws-note">Average score uses activities that have a score. Pre → Post compares each section’s pre-test and post-test results (Levels 2–6).</p></section>';
}
function admin_report_csv():never{
 require_role('admin');[$period,$start,$plabel]=admin_period();$rows=admin_report_rows($start,(int)($_GET['grade']??0),(int)($_GET['section']??0));
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-school-report-'.$period.'-'.date('Y-m-d').'.csv"');$h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");
 fputcsv($h,[admin_setting('school_name','BULIG'),'School year '.admin_school_year(),$plabel],',','"','\\');fputcsv($h,[],',','"','\\');
 fputcsv($h,['Grade · Section','Teacher','Pupils','Activities','Average score %','Pre-test %','Post-test %','Lessons completed','Inactive 7+ days'],',','"','\\');
 foreach($rows as $r)fputcsv($h,[$r['section'],$r['teacher'],$r['pupils'],$r['activities'],$r['avg']??'',$r['pre']??'',$r['post']??'',$r['lessons'],$r['inactive']],',','"','\\');exit;
}

/* ---------------------------------------------------------------- Lesson studio */
function admin_studio_view():void{
 $levels=rows('SELECT id,title,published FROM bulig_levels ORDER BY id');$lid=(int)($_GET['id']??0);$sel=$lid?one('SELECT l.*,m.level_id,m.grade_level mg FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?',[$lid]):null;
 $level=(int)($_GET['level']??($sel['level_id']??1));$per=per_grade_level($level);$grade=$per?(int)($_GET['grade']??($sel['mg']??1)):0;if($per&&($grade<1||$grade>6))$grade=1;
 $lessons=rows('SELECT l.*,m.grade_level mg FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=?'.($per?' AND m.grade_level=?':'').' ORDER BY l.position',$per?[$level,$grade]:[$level]);
 if(!$sel||(int)$sel['level_id']!==$level||($per&&(int)$sel['mg']!==$grade))$sel=$lessons?$lessons[0]+['level_id'=>$level]:null;
 admin_heading('LESSON STUDIO','Thoughtfully prepared. Ready to learn.','Choose a level, grade and lesson. Show or hide an activity, or fix its text. The official module page stays one click away.');
 echo '<nav class="ws-levels">';foreach($levels as $l){$id=(int)$l['id'];$short=[1=>'Oral Language',2=>'Sounds in Words',3=>'Sounds in Words',4=>'Word Recognition',5=>'Fluency',6=>'Listening & Vocabulary',7=>'Comprehension',8=>'Love for Reading'][$id]??$l['title'];echo '<a class="'.($id===$level?'on':'').'" href="?page=content&amp;level='.$id.'" title="'.e($l['title']).'"><b>'.e(level_label($id)).'</b>'.e($short).'</a>';}echo '</nav>';
 echo '<form class="ws-filters" method="get"><input type="hidden" name="page" value="content"><input type="hidden" name="level" value="'.$level.'">';
 if($per){echo '<select name="grade" data-autosubmit>';for($g=1;$g<=6;$g++)echo '<option value="'.$g.'" '.($grade===$g?'selected':'').'>Grade '.$g.'</option>';echo '</select>';}
 echo '<select name="id" data-autosubmit>';foreach($lessons as $l)echo '<option value="'.$l['id'].'" '.($sel&&(int)$sel['id']===(int)$l['id']?'selected':'').'>'.e(lesson_display_label($level,(int)$l['position']).($l['subtitle']?' · '.$l['subtitle']:'')).'</option>';
 echo '</select><button class="btn secondary">Open</button><span class="ws-sp muted">'.count($lessons).' lessons in '.e(module_label($level,$per?$grade:null)).'</span></form>';
 if(!$sel){echo '<section class="card"><p class="muted">No lessons are installed for this level yet.</p></section>';return;}
 $hasWork=(bool)val('SELECT COUNT(*) FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE a.lesson_id=?',[$sel['id']]);
 echo '<section class="card"><div class="ws-hd"><h2>'.e(lesson_display_label($level,(int)$sel['position']).($sel['subtitle']?' · '.$sel['subtitle']:'')).'</h2>'.($sel['source_pages']&&($pg=json_decode((string)$sel['source_pages'],true))?source_link((int)$pg[0],(int)$sel['id']):'').'</div>';
 if($hasWork)echo '<p class="ws-note muted">'.icon('lock').' Pupils already did this lesson, so activities can’t be hidden or shown (it would change their progress). Text can still be fixed.</p>';
 echo '<div class="tablewrap"><table class="ws-table"><thead><tr><th>#</th><th>Activity</th><th>Type</th><th>Shown to pupils</th><th>Actions</th></tr></thead><tbody>';
 foreach(rows("SELECT * FROM activities WHERE lesson_id=? ORDER BY FIELD(phase,'pre','learn','post'),position",[$sel['id']]) as $a){
  echo '<tr><td>'.(int)$a['position'].'</td><td><strong>'.e($a['title']).'</strong></td><td>'.e(ucfirst($a['type'])).'</td><td><span class="ws-pill '.($a['published']?'ok':'off').'">'.($a['published']?'Shown':'Hidden').'</span></td><td><div class="ws-acts"><a href="?page=edit_activity&amp;id='.$a['id'].'">'.icon('edit').'Fix text</a>';
  if(!$hasWork)echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_toggle_activity"><input type="hidden" name="id" value="'.$a['id'].'"><button type="submit">'.icon($a['published']?'eye':'eye').($a['published']?'Hide':'Show').'</button></form>';
  echo '</div></td></tr>';}
 echo '</tbody></table></div></section>';
 foreach(rows('SELECT * FROM assessments WHERE lesson_id=?',[$sel['id']]) as $a){echo '<details class="card"><summary>'.e($a['title']).' · Assessment rubric</summary><form method="post" class="stack">'.csrf_field().'<input type="hidden" name="action" value="save_assessment"><input type="hidden" name="id" value="'.$a['id'].'"><input type="hidden" name="lesson_id" value="'.$sel['id'].'"><label>Original rubric / assessment<textarea name="rubric" rows="10">'.e($a['rubric']).'</textarea></label><label>Numeric rubric criteria (one per line; leave empty if none is supplied)<textarea name="criteria">'.e(implode("\n",json_decode($a['rubric_criteria']??'[]',true)?:[])).'</textarea></label><button class="btn primary">Save rubric</button></form></details>';}
}

/* ---------------------------------------------------------------- Activity log */
function admin_log_labels():array{
 return ['login'=>['ok','Signed in'],'saved_login'=>['ok','Saved sign-in on a device'],'login_failed'=>['bad','Wrong password'],'login_locked'=>['bad','Sign-in locked'],'admin_pin_failed'=>['bad','Wrong admin PIN'],'admin_pin_created'=>['warn','Created admin PIN'],'admin_pin_changed'=>['warn','Changed admin PIN'],
  'create_pupil'=>['ok','Added pupil'],'create_teacher'=>['ok','Added teacher'],'update_account'=>['warn','Changed account'],'import_pupils'=>['ok','Imported pupils'],'admin_reset_password'=>['warn','Reset password'],'admin_toggle_teacher'=>['warn','Turned account on/off'],'admin_rename_teacher'=>['warn','Renamed teacher'],'admin_move_pupils'=>['warn','Moved pupils'],
  'save_section'=>['ok','Saved section'],'edit_activity'=>['warn','Edited activity'],'admin_toggle_activity'=>['warn','Showed/hid activity'],'upload_media'=>['ok','Uploaded picture'],'backup'=>['ok','Downloaded backup'],'school_info'=>['ok','Saved school info'],'announcement'=>['ok','Announcement'],'school_year_rollover'=>['warn','New school year'],'password_change'=>['warn','Changed own password']];
}
function admin_log_query():array{
 $tab=(string)($_GET['tab']??'all');$days=(int)($_GET['days']??7);if(!in_array($days,[1,7,30,365],true))$days=7;$q=trim((string)($_GET['q']??''));
 $groups=['signins'=>['login','login_failed','login_locked'],'security'=>['login_failed','login_locked','admin_pin_failed','admin_pin_created','admin_pin_changed','admin_reset_password','password_change'],'accounts'=>['create_pupil','create_teacher','update_account','import_pupils','admin_reset_password','admin_toggle_teacher','admin_rename_teacher','admin_move_pupils','save_section','school_year_rollover'],'content'=>['edit_activity','admin_toggle_activity','upload_media','save_assessment','announcement','school_info']];
 if(!isset($groups[$tab]))$tab='all';
 $where='a.created_at>=?';$args=[date('Y-m-d H:i:s',strtotime('-'.$days.' days'))];
 if($tab!=='all'){$where.=' AND a.action IN ('.implode(',',array_fill(0,count($groups[$tab]),'?')).')';$args=array_merge($args,$groups[$tab]);}
 if($q!==''){$where.=' AND (u.name LIKE ? OR u.public_id LIKE ? OR a.details LIKE ?)';$like='%'.$q.'%';array_push($args,$like,$like,$like);}
 return [$tab,$days,$q,$where,$args];
}
function admin_log_view():void{
 [$tab,$days,$q,$where,$args]=admin_log_query();$page=max(1,(int)($_GET['p']??1));$labels=admin_log_labels();
 $total=(int)val("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id=a.actor_id WHERE $where",$args);
 $list=rows("SELECT a.*,u.name,u.public_id,u.role FROM audit_log a LEFT JOIN users u ON u.id=a.actor_id WHERE $where ORDER BY a.id DESC LIMIT 50 OFFSET ".(($page-1)*50),$args);
 $qs='tab='.$tab.'&amp;days='.$days.'&amp;q='.rawurlencode($q);
 admin_heading('ACTIVITY LOG','Who did what, and when.','Sign-ins, wrong passwords and PINs, and account changes. Newest first.','<a class="btn secondary" href="?page=log_csv&amp;'.$qs.'">'.icon('download').'Download CSV</a>');
 echo '<nav class="ws-tabs">';foreach(['all'=>'All','signins'=>'Sign-ins','security'=>'Wrong passwords / PINs','accounts'=>'Accounts','content'=>'Content'] as $k=>$v)echo '<a class="'.($tab===$k?'on':'').'" href="?page=activity_log&amp;tab='.$k.'&amp;days='.$days.'">'.$v.'</a>';echo '</nav>';
 echo '<form class="ws-filters" method="get"><input type="hidden" name="page" value="activity_log"><input type="hidden" name="tab" value="'.e($tab).'"><input name="q" value="'.e($q).'" placeholder="Search name, ID or details"><select name="days">';foreach([1=>'Today',7=>'Last 7 days',30=>'Last 30 days',365=>'Last 12 months'] as $k=>$v)echo '<option value="'.$k.'" '.($days===$k?'selected':'').'>'.$v.'</option>';echo '</select><button class="btn secondary">Search</button><span class="ws-sp muted">'.number_format($total).' entries</span></form>';
 echo '<section class="card"><div class="tablewrap"><table class="ws-table"><thead><tr><th>When</th><th>Who</th><th>What happened</th><th>Details</th></tr></thead><tbody>';
 foreach($list as $r){[$cls,$lab]=$labels[$r['action']]??['off',ucfirst(str_replace('_',' ',$r['action']))];
  $who=$r['name']?e($r['name']).' · '.e($r['public_id']):'<span class="muted">Not signed in</span>';
  echo '<tr><td>'.e(admin_when($r['created_at'])).'</td><td><strong>'.$who.'</strong>'.($r['role']?'<small>'.e(ucfirst($r['role'])).'</small>':'').'</td><td><span class="ws-pill '.$cls.'">'.e($lab).'</span></td><td>'.e(admin_log_detail($r)).'</td></tr>';}
 if(!$list)echo '<tr><td colspan="4">Nothing recorded for this filter.</td></tr>';
 echo '</tbody></table></div>';
 if($total>50){$last=(int)ceil($total/50);echo '<nav class="buttonrow ws-pager">'.($page>1?'<a class="btn quiet" href="?page=activity_log&amp;'.$qs.'&amp;p='.($page-1).'">Newer</a>':'').'<span>Page '.$page.' of '.$last.'</span>'.($page<$last?'<a class="btn quiet" href="?page=activity_log&amp;'.$qs.'&amp;p='.($page+1).'">Older</a>':'').'</nav>';}
 echo '</section>';
}
function admin_log_detail(array $r):string{
 $d=(string)$r['details'];
 if($r['action']==='login')return $d==='admin'?'Administrator · password and PIN':ucfirst($d);
 if($r['action']==='admin_pin_failed')return 'Wrong try number '.$d;
 if(in_array($r['action'],['update_account','edit_activity','admin_toggle_activity','admin_reset_password','admin_toggle_teacher','admin_rename_teacher','new_pictures'],true)&&ctype_digit($d)){
  $u=one('SELECT name,public_id FROM users WHERE id=?',[(int)$d]);if($u&&$r['action']!=='edit_activity'&&$r['action']!=='admin_toggle_activity')return $u['name'].' · '.$u['public_id'];
  $a=one('SELECT title FROM activities WHERE id=?',[(int)$d]);if($a)return 'Activity '.$d.' · '.$a['title'];}
 return $d;
}
function admin_log_csv():never{
 require_role('admin');[$tab,$days,$q,$where,$args]=admin_log_query();$labels=admin_log_labels();
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-activity-log-'.date('Y-m-d').'.csv"');$h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");
 fputcsv($h,['When','Name','ID','Role','What happened','Details'],',','"','\\');
 foreach(rows("SELECT a.*,u.name,u.public_id,u.role FROM audit_log a LEFT JOIN users u ON u.id=a.actor_id WHERE $where ORDER BY a.id DESC LIMIT 20000",$args) as $r)fputcsv($h,[$r['created_at'],$r['name']??'',$r['public_id']??'',$r['role']??'',$labels[$r['action']][1]??$r['action'],admin_log_detail($r)],',','"','\\');exit;
}

/* ---------------------------------------------------------------- System health + backup */
const ADMIN_EXPECTED_MIGRATIONS=['007_level2_content','009_level3_content','010_level4_content','011_level5_content','012_level6_content','013_level7_content','014_admin_pin','015_admin_tools','016_saved_logins','024_notifications','025_help_requests','026_more_badges'];
function admin_backup_note():string{$b=admin_setting('last_backup_at','');if(!$b)return 'No backup downloaded yet';$d=admin_days_since($b);return 'Last backup '.($d===0?'today':($d===1?'yesterday':$d.' days ago'));}
function admin_count_files(string $dir,string $pattern):int{
 if(!is_dir($dir))return 0;$n=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f)if(preg_match($pattern,$f->getFilename()))$n++;return $n;
}
/** [label, ok, detail] for each check. */
function admin_health_checks():array{
 static $c;if($c!==null)return $c;$c=[];$root=__DIR__.'/..';
 $c[]=['Database connection',true,'Connected · PHP '.PHP_VERSION];
 $mig=array_column(rows('SELECT version FROM schema_migrations'),'version');$missing=array_values(array_diff(ADMIN_EXPECTED_MIGRATIONS,$mig));
 $c[]=['Database updates',!$missing,$missing?'Import: '.implode(', ',$missing):count($mig).' applied · newest '.end($mig)];
 $empty=[];$total=0;foreach(rows('SELECT b.id,b.published,(SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=b.id AND l.published=1) n FROM bulig_levels b ORDER BY b.id') as $l){$total+=(int)$l['n'];if($l['published']&&!$l['n'])$empty[]=level_label((int)$l['id']);}
 $c[]=['Level 1–7 content installed',!$empty,$empty?'No lessons in '.implode(', ',$empty):number_format($total).' published lessons'];
 $pics=admin_count_files($root.'/public/assets/images','/\.(webp|png|jpe?g)$/i');$c[]=['Activity pictures',$pics>0,number_format($pics).' picture files · use Picture check for missing ones'];
 $pdf=admin_count_files($root.'/storage','/\.pdf$/i');$pages=admin_count_files($root.'/storage','/^page-\d+\.webp$/');$c[]=['Module PDFs and page images',$pdf>0,$pdf.' PDFs · '.number_format($pages).' page images'];
 $b=admin_setting('last_backup_at','');$c[]=['Recent backup',$b!==''&&admin_days_since($b)<=7,admin_backup_note().($b===''||admin_days_since($b)>7?' · download one below':'')];
 try{$noPin=(int)val("SELECT COUNT(*) FROM users u LEFT JOIN admin_security s ON s.user_id=u.id WHERE u.role='admin' AND u.active=1 AND (s.pin_hash IS NULL)");$c[]=['Admin PIN',$noPin===0,$noPin?$noPin.' admin(s) will create a PIN at next sign-in':'Set for all admins'];}catch(Throwable $e){$c[]=['Admin PIN',false,'Import 014_admin_pin.sql'];}
 $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';$c[]=['HTTPS (secure connection)',$https,$https?'On':'Off · turn on SSL in hPanel'];
 $chk=is_file($root.'/public/bulig-check.php');$c[]=['Setup check file removed',!$chk,$chk?'Delete public/bulig-check.php after installing':'Removed'];
 $c[]=['Excel import support',class_exists('ZipArchive'),class_exists('ZipArchive')?'Available':'Ask hosting to enable the PHP zip extension'];
 return $c;
}
function admin_health_view():void{
 $checks=admin_health_checks();$ok=count(array_filter($checks,fn($c)=>$c[1]));
 admin_heading('SYSTEM HEALTH',$ok===count($checks)?'Everything in good shape.':'A few things to check.','Install checks, pictures, database updates and backups in one place.','<a class="btn primary" href="?page=health">'.icon('heart').'Run all checks</a>');
 echo '<div class="ws-g2"><section class="card"><div class="ws-hd"><h2>Checks</h2><span class="ws-pill '.($ok===count($checks)?'ok':'warn').'">'.$ok.' of '.count($checks).' OK</span></div>';
 foreach($checks as [$label,$good,$detail])echo '<div class="ws-chk '.($good?'y':'n').'">'.icon($good?'check':'alert').'<span>'.e($label).'</span><span class="ws-sp">'.e($detail).'</span></div>';
 echo '</section><div class="ws-stack"><section class="card" id="backup"><div class="ws-hd"><h2>'.icon('db').' Backup</h2><small class="muted">'.e(admin_backup_note()).'</small></div><p class="muted">Download pupils, teachers, sections and results. Keep a copy on a USB drive or Google Drive every week.</p><div class="ws-btns"><a class="btn primary" href="?page=backup_csv">'.icon('download').'Download backup (Excel)</a><a class="btn secondary" href="?page=backup_sql">'.icon('db').'Full database (SQL)</a></div></section>';
 echo '<section class="card"><div class="ws-hd"><h2>'.icon('image').' Picture check</h2></div><p class="muted">Finds activities whose pictures are missing on the server.</p><div class="ws-btns"><a class="btn secondary" href="?page=visual_audit">Run picture check</a><a class="btn quiet" href="?page=media">Level 1 module pictures</a><a class="btn quiet" href="?page=issues">Level 1 content notes</a></div></section></div></div>';
}
function admin_backup_csv():never{
 $u=require_role('admin');@set_time_limit(300);
 $sheets=[
  'pupils.csv'=>[['Pupil ID','Name','LRN','Sex','Grade','Section','Teacher','Starting level','XP','Active','Created'],"SELECT p.public_id,p.name,d.lrn,d.sex,pu.grade_level,pu.section,t.name teacher,CASE a.level_id WHEN 1 THEN 'Level 1' WHEN 2 THEN 'Level 2A' WHEN 3 THEN 'Level 2B' ELSE CONCAT('Level ',a.level_id-1) END,COALESCE(x.total,0) xp,p.active,p.created_at FROM users p JOIN pupils pu ON pu.user_id=p.id LEFT JOIN pupil_details d ON d.pupil_id=p.id LEFT JOIN teacher_pupils tp ON tp.pupil_id=p.id LEFT JOIN users t ON t.id=tp.teacher_id LEFT JOIN pupil_level_assignments a ON a.pupil_id=p.id LEFT JOIN pupil_xp x ON x.pupil_id=p.id ORDER BY pu.grade_level,pu.section,p.name"],
  'teachers.csv'=>[['Teacher ID','Name','Active','Created'],"SELECT public_id,name,active,created_at FROM users WHERE role='teacher' ORDER BY name"],
  'sections.csv'=>[['Grade','Section','Teacher','Pupils'],"SELECT s.grade_level,s.name,t.name,(SELECT COUNT(*) FROM pupil_sections ps WHERE ps.section_id=s.id) FROM sections s JOIN users t ON t.id=s.teacher_id ORDER BY s.grade_level,s.name"],
  'activity-results.csv'=>[['Pupil ID','Pupil','Level','Grade module','Lesson','Activity','Status','Score','Out of','Submitted'],"SELECT p.public_id,p.name,CASE m.level_id WHEN 1 THEN 'Level 1' WHEN 2 THEN 'Level 2A' WHEN 3 THEN 'Level 2B' ELSE CONCAT('Level ',m.level_id-1) END,m.grade_level,l.position,a.title,c.status,c.score,c.max_score,c.submitted_at FROM activity_completion c JOIN users p ON p.id=c.pupil_id JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id ORDER BY p.public_id,c.submitted_at"]];
 $csv=function(array $head,string $sql):string{$h=fopen('php://temp','r+');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,$head,',','"','\\');foreach(rows($sql) as $r){$r=array_values($r);foreach($r as $i=>$v)if(is_string($v)&&preg_match('/^\d{12}$/',$v))$r[$i]='="'.$v.'"';fputcsv($h,$r,',','"','\\');}rewind($h);return stream_get_contents($h);};
 admin_set('last_backup_at',date('Y-m-d H:i:s'));audit('backup','excel');
 $name='BULIG-backup-'.date('Y-m-d');
 if(class_exists('ZipArchive')){$tmp=tempnam(sys_get_temp_dir(),'bulig');$z=new ZipArchive();$z->open($tmp,ZipArchive::OVERWRITE);foreach($sheets as $f=>[$head,$sql])$z->addFromString($f,$csv($head,$sql));$z->close();
  header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$name.'.zip"');header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;}
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$name.'-pupils.csv"');echo $csv(...$sheets['pupils.csv']);exit;
}
function admin_backup_sql():never{
 require_role('admin');@set_time_limit(600);admin_set('last_backup_at',date('Y-m-d H:i:s'));audit('backup','sql');
 header('Content-Type: application/sql; charset=utf-8');header('Content-Disposition: attachment; filename="BULIG-database-'.date('Y-m-d').'.sql"');
 echo "-- BULIG full database backup · ".date('Y-m-d H:i:s')."\n-- Restore: phpMyAdmin → select an EMPTY database → Import this file.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
 foreach(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){
  $create=db()->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];echo "DROP TABLE IF EXISTS `$t`;\n$create;\n";
  for($off=0;;$off+=500){$rs=db()->query('SELECT * FROM `'.$t.'` LIMIT 500 OFFSET '.$off)->fetchAll(PDO::FETCH_NUM);if(!$rs)break;
   echo 'INSERT INTO `'.$t.'` VALUES ';$parts=[];foreach($rs as $r)$parts[]='('.implode(',',array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),$r)).')';echo implode(",\n",$parts).";\n";flush();if(count($rs)<500)break;}
  echo "\n";}
 echo "SET FOREIGN_KEY_CHECKS=1;\n";exit;
}

/* ---------------------------------------------------------------- Settings */
function admin_settings_view(array $u):void{
 $tab=(string)($_GET['tab']??'security');$tabs=['security'=>'Security','school'=>'School info','announcements'=>'Announcements','badges'=>'Badges & rewards','year'=>'School year','schedule'=>'Level schedule'];if(!isset($tabs[$tab]))$tab='security';
 admin_heading('SETTINGS','Your BULIG workspace.','Security, school details, announcements, badges and the end of the school year.');
 echo '<nav class="ws-tabs">';foreach($tabs as $k=>$v)echo '<a class="'.($tab===$k?'on':'').'" href="?page=settings&amp;tab='.$k.'">'.e($v).'</a>';echo '</nav>';
 if($tab==='security'){echo '<div class="ws-g2">';admin_pin_settings($u);
  echo '<section class="card"><div class="ws-hd"><h2>'.icon('lock').' Sign-in rules</h2></div><div class="ws-chk y">'.icon('check').'<span>Lock an ID after 8 wrong passwords</span><span class="ws-sp">15 minutes</span></div><div class="ws-chk y">'.icon('check').'<span>Lock admin sign-in after 3 wrong PINs</span><span class="ws-sp">15 minutes</span></div><div class="ws-chk y">'.icon('check').'<span>Sign out after no activity</span><span class="ws-sp">2 hours</span></div><div class="ws-chk y">'.icon('check').'<span>Pupil passwords</span><span class="ws-sp">'.(cards_ready()?'Own starter password + QR ticket':'12345678').'</span></div></section></div>';}
 if($tab==='school'){echo '<section class="card"><div class="ws-hd"><h2>'.icon('book').' School info</h2><small class="muted">Printed at the top of reports</small></div><form method="post" class="ws-formrow">'.csrf_field().'<input type="hidden" name="action" value="admin_school_info">';
  foreach(['school_name'=>['School name','e.g. Malaybalay Central Elementary School'],'school_id'=>['School ID','e.g. 128001'],'school_district'=>['District / Division','e.g. Malaybalay City Division'],'school_head'=>['School head','Name of the principal'],'school_year'=>['School year',admin_school_year()]] as $k=>[$l,$ph])echo '<label>'.$l.'<input name="'.$k.'" maxlength="150" value="'.e(admin_setting($k,'')).'" placeholder="'.e($ph).'"></label>';
  echo '<button class="btn primary">Save school info</button></form></section>';}
 if($tab==='announcements'){echo '<div class="ws-g2"><section class="card"><div class="ws-hd"><h2>'.icon('mega').' Announcements</h2><small class="muted">Shown on every teacher’s Overview</small></div>';
  $list=admin_announcements();if(!$list)echo '<p class="muted">No announcements yet.</p>';
  foreach($list as $a){$live=($a['until']??'')===''||$a['until']>=date('Y-m-d');echo '<div class="ws-ann '.($live?'':'old').'"><b>'.e($a['title']).'</b>'.e($a['message']).'<small> · '.($live?($a['until']?'until '.e(date('M j, Y',strtotime($a['until']))):'no end date'):'ended').'</small><form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_announcement_delete"><input type="hidden" name="id" value="'.e($a['id']).'"><button class="ws-link" type="submit">Remove</button></form></div>';}
  echo '</section><section class="card"><div class="ws-hd"><h2>Post an announcement</h2></div><form method="post" class="stack">'.csrf_field().'<input type="hidden" name="action" value="admin_announcement_add"><label>Title<input name="title" required maxlength="120" placeholder="e.g. Reading Month starts Monday"></label><label>Message<textarea name="message" rows="3" maxlength="400" placeholder="Short details for teachers"></textarea></label><label>Show until (optional)<input type="date" name="until" min="'.date('Y-m-d').'"></label><button class="btn primary">'.icon('mega').'Post announcement</button></form></section></div>';}
 if($tab==='badges')settings_view();
 if($tab==='schedule')schedule_admin_view();
 if($tab==='year'){$b=admin_setting('last_backup_at','');$fresh=$b!==''&&admin_days_since($b)<1;
  $counts=[];foreach(rows("SELECT pu.grade_level g,COUNT(*) n FROM pupils pu JOIN users u ON u.id=pu.user_id AND u.active=1 GROUP BY pu.grade_level") as $r)$counts[(int)$r['g']]=(int)$r['n'];
  echo '<div class="ws-g2"><section class="card"><div class="ws-hd"><h2>'.icon('cal').' End of school year</h2><small class="muted">Current: '.e(admin_school_year()).'</small></div><div class="ws-steps">';
  echo '<div class="ws-step '.($fresh?'done':'').'"><b class="n">1</b><span><b>Download a backup</b><small>'.($fresh?'Done · '.e(admin_backup_note()):'Required first. Keep this year’s records safe.').'</small></span>'.(!$fresh?'<a class="btn secondary small" href="?page=backup_csv">Download</a>':'').'</div>';
  echo '<div class="ws-step"><b class="n">2</b><span><b>Move pupils up one grade</b><small>Grades 1–5 move up ('.array_sum(array_intersect_key($counts,array_flip([1,2,3,4,5]))).' pupils). Grade 6 pupils ('.($counts[6]??0).') finish BULIG: their accounts are turned off, their records are kept.</small></span></div>';
  echo '<div class="ws-step"><b class="n">3</b><span><b>Archive this year’s sections</b><small>Pupils keep their teacher, progress, XP and badges. Teachers make new sections and place their pupils.</small></span></div></div>';
  echo '<form method="post" class="ws-year">'.csrf_field().'<input type="hidden" name="action" value="admin_school_year"><label>New school year<input name="new_year" required maxlength="20" value="'.e(admin_next_year()).'"></label><label>Type NEW SCHOOL YEAR to confirm<input name="confirm" required autocomplete="off"></label><button class="btn primary" '.($fresh?'':'disabled').'>'.icon('cal').'Start the new school year</button>'.(!$fresh?'<small class="muted">Download a backup first (step 1).</small>':'').'</form></section>';
  echo '<section class="card"><div class="ws-hd"><h2>Pupils by grade now</h2></div><div class="ws-bars">';$mx=max([1]+$counts);for($g=1;$g<=6;$g++){$n=$counts[$g]??0;echo '<div class="ws-bar"><span>Grade '.$g.'</span><u><i class="w-'.(int)(round($n/$mx*20)*5).'"></i></u><b>'.$n.'</b></div>';}echo '</div></section></div>';}
}
function admin_next_year():string{$cur=admin_school_year();if(preg_match('/(\d{4})\D+(\d{4})/',$cur,$m))return ($m[1]+1).'–'.($m[2]+1);$y=(int)date('Y');return $y.'–'.($y+1);}

/* ---------------------------------------------------------------- actions */
function admin_actions(string $action):void{
 $u=require_role('admin');
 if($action==='admin_level_schedule')schedule_admin_save();
 if($action==='admin_reset_password'){$t=admin_teacher((int)($_POST['id']??0));$p=admin_new_password();q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($p,PASSWORD_DEFAULT),$t['id']]);saved_login_clear((int)$t['id']);try{q("DELETE FROM remember_tokens WHERE user_id=?",[$t['id']]);}catch(Throwable $e){}
  audit('admin_reset_password',(string)$t['id']);flash('New password for '.$t['name'].' ('.$t['public_id'].'): '.$p.' — give it to the teacher privately. They can change it in My profile.');go('?page=accounts');}
 if($action==='admin_toggle_teacher'){$t=admin_teacher((int)($_POST['id']??0));q('UPDATE users SET active=1-active WHERE id=?',[$t['id']]);audit('admin_toggle_teacher',(string)$t['id']);flash($t['name'].' is now '.($t['active']?'turned off. They cannot sign in.':'turned on.'));go('?page=accounts');}
 if($action==='admin_rename_teacher'){$t=admin_teacher((int)($_POST['id']??0));$name=trim((string)($_POST['name']??''));if(mb_strlen($name)<2||mb_strlen($name)>150)fail('Enter a name of 2–150 characters.');q('UPDATE users SET name=? WHERE id=?',[$name,$t['id']]);tf_set_sex((int)$t['id'],(string)($_POST['teacher_sex']??''));audit('admin_rename_teacher',(string)$t['id']);flash('Teacher details saved.');go('?page=accounts');}
 if($action==='admin_move_pupils'){
  $to=admin_teacher((int)($_POST['to']??0));if(!$to['active'])fail('Choose an active teacher.');$from=(string)($_POST['from']??'');$moved=0;
  db()->beginTransaction();try{
   if(preg_match('/^s(\d+)$/',$from,$m)){$s=one('SELECT * FROM sections WHERE id=?',[(int)$m[1]]);if(!$s)fail('Section not found.',404);if((int)$s['teacher_id']===(int)$to['id'])fail('That section already belongs to '.$to['name'].'.');$moved=admin_move_section($s,(int)$to['id']);$label='Grade '.$s['grade_level'].' · '.$s['name'];}
   elseif(preg_match('/^t(\d+)$/',$from,$m)){$src=admin_teacher((int)$m[1]);if((int)$src['id']===(int)$to['id'])fail('Choose a different teacher.');
    foreach(rows('SELECT * FROM sections WHERE teacher_id=?',[$src['id']]) as $s)$moved+=admin_move_section($s,(int)$to['id']);
    $moved+=(int)db()->exec('UPDATE teacher_pupils SET teacher_id='.(int)$to['id'].' WHERE teacher_id='.(int)$src['id']);$label='all of '.$src['name'].'’s pupils';}
   else fail('Choose which pupils to move.');
   audit('admin_move_pupils',$label.' → '.$to['name']);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Moved '.$label.' to '.$to['name'].'.');go('?page=accounts');}
 if($action==='admin_toggle_activity'){$a=one('SELECT * FROM activities WHERE id=?',[(int)($_POST['id']??0)]);if(!$a)fail('Activity not found.',404);
  if(val('SELECT COUNT(*) FROM activity_completion c JOIN activities x ON x.id=c.activity_id WHERE x.lesson_id=?',[$a['lesson_id']]))fail('Pupils already did this lesson, so its activities can’t be hidden or shown.');
  q('UPDATE activities SET published=1-published,revision=revision+1 WHERE id=?',[$a['id']]);audit('admin_toggle_activity',(string)$a['id']);flash('“'.$a['title'].'” is now '.($a['published']?'hidden from pupils.':'shown to pupils.'));go('?page=content&id='.$a['lesson_id']);}
 if($action==='admin_school_info'){foreach(['school_name','school_id','school_district','school_head','school_year'] as $k)admin_set($k,mb_substr(trim((string)($_POST[$k]??'')),0,150));audit('school_info','saved');flash('School info saved.');go('?page=settings&tab=school');}
 if($action==='admin_announcement_add'){$title=trim((string)($_POST['title']??''));if($title===''||mb_strlen($title)>120)fail('Enter a title of up to 120 characters.');$until=(string)($_POST['until']??'');if($until!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$until))$until='';
  $list=admin_announcements();array_unshift($list,['id'=>bin2hex(random_bytes(6)),'title'=>$title,'message'=>mb_substr(trim((string)($_POST['message']??'')),0,400),'until'=>$until,'by'=>$u['name'],'at'=>date('Y-m-d H:i')]);admin_set('announcements',json_encode(array_slice($list,0,20),JSON_UNESCAPED_UNICODE));audit('announcement',$title);flash('Announcement posted. Teachers see it on their Overview.');go('?page=settings&tab=announcements');}
 if($action==='admin_announcement_delete'){$id=(string)($_POST['id']??'');admin_set('announcements',json_encode(array_values(array_filter(admin_announcements(),fn($a)=>$a['id']!==$id)),JSON_UNESCAPED_UNICODE));flash('Announcement removed.');go('?page=settings&tab=announcements');}
 if($action==='admin_school_year'){
  if(trim((string)($_POST['confirm']??''))!=='NEW SCHOOL YEAR')fail('Type NEW SCHOOL YEAR (in capital letters) to confirm.');
  $b=admin_setting('last_backup_at','');if($b===''||admin_days_since($b)>=1)fail('Download a backup first (step 1), then start the new school year.');
  if(!sections_archive_supported())fail('Import 015_admin_tools.sql first.');
  $year=mb_substr(trim((string)($_POST['new_year']??'')),0,20)?:admin_next_year();
  db()->beginTransaction();try{
   $done=(int)val("SELECT COUNT(*) FROM pupils pu JOIN users u ON u.id=pu.user_id AND u.active=1 WHERE pu.grade_level=6");
   q("UPDATE users u JOIN pupils pu ON pu.user_id=u.id SET u.active=0 WHERE pu.grade_level=6 AND u.role='pupil'");
   $up=(int)val("SELECT COUNT(*) FROM pupils pu JOIN users u ON u.id=pu.user_id AND u.active=1 WHERE pu.grade_level<6");
   q("UPDATE pupils pu JOIN users u ON u.id=pu.user_id SET pu.grade_level=pu.grade_level+1,pu.section='Not placed yet' WHERE pu.grade_level<6 AND u.active=1");
   q('DELETE ps FROM pupil_sections ps JOIN sections s ON s.id=ps.section_id WHERE s.archived_at IS NULL');q('UPDATE sections SET archived_at=NOW() WHERE archived_at IS NULL');
   admin_set('school_year',$year);audit('school_year_rollover',$year.' · '.$up.' moved up · '.$done.' finished');db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('School year '.$year.' started. '.$up.' pupils moved up a grade and '.$done.' Grade 6 pupils finished BULIG. Teachers can now make new sections.');go('?page=settings&tab=year');}
}
const ADMIN_ACTIONS=['admin_reset_password','admin_toggle_teacher','admin_rename_teacher','admin_move_pupils','admin_toggle_activity','admin_school_info','admin_announcement_add','admin_announcement_delete','admin_school_year','admin_level_schedule'];
