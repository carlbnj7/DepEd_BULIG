<?php
/* Administrator: one school's own page (v190), opened with "View school" on the Schools page (?page=school&id=N).
   Like a social profile: a cover with the school logo, name and details, then the numbers, and three tabs:
   Overview (about the school, pupils per grade, top readers, lessons just finished), Teachers and Pupils. Read-only. */
const SP_MAX=600;

function school_page_view():void{
 if(!schools_ready())go('?page=schools');
 $sid=(int)($_GET['id']??0);$s=school_row($sid);if(!$s)fail('School not found.',404);
 $tab=in_array($_GET['tab']??'',['teachers','pupils'],true)?$_GET['tab']:'overview';
 $i=array_search($sid,array_map(fn($r)=>(int)$r['id'],school_rows()),true);$c='c'.((int)$i%4);
 $cnt=school_counts()[$sid]??['t'=>0,'p'=>0,'s'=>0];$week=date('Y-m-d',strtotime('-6 days'));
 $done=(int)val('SELECT COUNT(*) FROM pupil_progress pp JOIN users u ON u.id=pp.pupil_id AND u.school_id=? WHERE pp.completed_at IS NOT NULL',[$sid]);
 $act=(int)val('SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN users u ON u.id=d.pupil_id AND u.active=1 AND u.school_id=? WHERE d.day>=?',[$sid,$week]);
 $logo=$s['logo']!==''&&is_file(__DIR__.'/../public/'.$s['logo']);$name=$s['name']!==''?$s['name']:$s['code'];

 echo '<nav class="crumb sp-crumb"><a href="?page=schools">Schools</a>'.icon('chev').'<span>'.e($s['code']).'</span></nav>';
 echo '<section class="sp-head card"><div class="sp-cover"><img src="assets/images/covers/school-cover.webp" alt=""></div><div class="sp-id">'
  .($logo?'<img class="sp-logo" src="'.e($s['logo']).'" alt="'.e($name).' logo">':'<span class="sp-logo '.$c.'">'.e($s['code']).'</span>')
  .'<div class="sp-name"><h1>'.e($name).'</h1>'
  .'<div class="sp-acts"><a class="btn primary small" href="?page=accounts&amp;school='.$sid.'">'.icon('people').'Teachers page</a><a class="btn secondary small" href="?page=dashboard&amp;school='.$sid.'">'.icon('chart').'Home for this school</a><a class="btn quiet small" href="?page=schools#school-'.$sid.'">'.icon('edit').'Edit</a></div></div></div>';
 $t=fn($k,$ic,$l)=>'<a href="?page=school&amp;id='.$sid.($k==='overview'?'':'&amp;tab='.$k).'"'.($tab===$k?' class="on" aria-current="page"':'').'>'.icon($ic).$l.'</a>';
 echo '<nav class="sp-tabs" aria-label="School page">'.$t('overview','home','Overview').$t('teachers','useredit','Teachers <b>'.$cnt['t'].'</b>').$t('pupils','people','Pupils <b>'.number_format($cnt['p']).'</b>').'</nav></section>';

 echo '<div class="sp-stats">';
 foreach([['useredit',$cnt['t'],$cnt['t']===1?'Teacher':'Teachers'],['people',number_format($cnt['p']),$cnt['p']===1?'Pupil':'Pupils'],['book',$cnt['s'],$cnt['s']===1?'Section':'Sections'],['check',number_format($done),$done===1?'Lesson finished':'Lessons finished'],['fire',number_format($act),'Learned this week']] as [$ic,$n,$l])
  echo '<div class="sp-stat">'.icon($ic).'<b>'.$n.'</b><span>'.$l.'</span></div>';
 echo '</div>';

 if($tab==='teachers'){sp_teachers($sid);return;}
 if($tab==='pupils'){sp_pupils($sid);return;}

 /* Overview: About + pupils per grade on the left; top readers and lessons just finished on the right */
 echo '<div class="sp-grid"><div class="sp-side">';
 echo '<section class="card sp-card"><h2>'.icon('info').'About</h2><ul class="sp-about">'
  .'<li>'.icon('school').'<span><small>School</small>'.e($name).'</span></li>'
  .($s['school_no']!==''?'<li>'.icon('key').'<span><small>School ID</small>'.e($s['school_no']).'</span></li>':'')
  .($s['district']!==''?'<li>'.icon('flag').'<span><small>District</small>'.e($s['district']).'</span></li>':'')
  .($s['head']!==''?'<li>'.icon('user').'<span><small>School head</small>'.e($s['head']).'</span></li>':'')
  .'<li>'.icon('people').'<span><small>New IDs</small>Pupils '.e(account_id_format('pupil',$s['code'],date('y'),1)).' · Teachers '.e(account_id_format('teacher',$s['code'],date('y'),1)).'</span></li></ul></section>';
 $g=rows("SELECT p.grade_level g,COUNT(*) n,SUM(EXISTS(SELECT 1 FROM pupil_progress pp WHERE pp.pupil_id=u.id AND pp.completed_at IS NOT NULL)) d FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.role='pupil' AND u.active=1 AND u.school_id=? GROUP BY p.grade_level ORDER BY p.grade_level",[$sid]);
 echo '<section class="card sp-card"><h2>'.icon('chart').'Pupils per grade</h2>';
 if(!$g)echo '<p class="muted">No pupils yet.</p>';
 foreach($g as $r){$pct=(int)$r['n']?round((int)$r['d']/(int)$r['n']*100):0;
  echo '<div class="sp-grade"><span><b>'.((int)$r['g']?'Grade '.(int)$r['g']:'No grade').'</b> · '.(int)$r['n'].' '.((int)$r['n']===1?'pupil':'pupils').'</span><span class="sc-bar"><i class="w-'.(int)(round($pct/5)*5).'"></i></span><small>'.$pct.'% finished at least one lesson</small></div>';}
 echo '</section></div><div class="sp-main">';

 $top=rows("SELECT u.id,u.name,u.avatar_path,p.grade_level,COALESCE(x.total,0) xp,(SELECT COUNT(*) FROM pupil_progress pp WHERE pp.pupil_id=u.id AND pp.completed_at IS NOT NULL) done FROM users u JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_xp x ON x.pupil_id=u.id WHERE u.role='pupil' AND u.active=1 AND u.school_id=? ORDER BY xp DESC,done DESC,u.name LIMIT 5",[$sid]);
 echo '<section class="card sp-card"><h2>'.icon('star').'Top readers</h2>';
 if(!$top||!(int)$top[0]['xp'])echo '<p class="muted">No XP yet. Top readers show here once pupils start their lessons.</p>';
 else{echo '<ol class="sp-top">';foreach($top as $k=>$r)if((int)$r['xp'])echo '<li><span class="sp-rank r'.($k+1).'">'.($k+1).'</span>'.avatar($r).'<span class="sp-who"><b>'.e($r['name']).'</b><small>Grade '.(int)$r['grade_level'].' · '.(int)$r['done'].' '.((int)$r['done']===1?'lesson':'lessons').' finished</small></span><b class="sp-xp">'.number_format((int)$r['xp']).' XP</b></li>';echo '</ol>';}
 echo '</section>';

 $feed=rows("SELECT u.name,u.avatar_path,p.grade_level,l.title,m.level_id,pp.completed_at FROM pupil_progress pp JOIN users u ON u.id=pp.pupil_id AND u.school_id=? JOIN pupils p ON p.user_id=u.id JOIN lessons l ON l.id=pp.lesson_id JOIN modules m ON m.id=l.module_id WHERE pp.completed_at IS NOT NULL ORDER BY pp.completed_at DESC LIMIT 8",[$sid]);
 echo '<section class="card sp-card"><h2>'.icon('check').'Lessons just finished</h2>';
 if(!$feed)echo '<p class="muted">No lessons finished yet.</p>';
 else{echo '<ul class="sp-feed">';foreach($feed as $r)echo '<li>'.avatar($r).'<span><b>'.e($r['name']).'</b> finished <b>'.e($r['title']).'</b><small>'.e(level_label((int)$r['level_id'])).' · Grade '.(int)$r['grade_level'].' · '.e(sp_when((string)$r['completed_at'])).'</small></span>'.icon('check','sp-ok').'</li>';echo '</ul>';}
 echo '</section></div></div>';
}

function sp_teachers(int $sid):void{
 $live=sections_archive_supported()?' AND s.archived_at IS NULL':'';
 $list=rows("SELECT u.id,u.name,u.public_id,u.avatar_path,u.active,(SELECT COUNT(*) FROM teacher_pupils tp WHERE tp.teacher_id=u.id) pupils"
  .",(SELECT COUNT(*) FROM teacher_pupils tp JOIN pupil_progress pp ON pp.pupil_id=tp.pupil_id AND pp.completed_at IS NOT NULL WHERE tp.teacher_id=u.id) done"
  .",(SELECT GROUP_CONCAT(CONCAT('Grade ',s.grade_level,' · ',s.name) ORDER BY s.grade_level,s.name SEPARATOR ', ') FROM sections s WHERE s.teacher_id=u.id".$live.") secs"
  ." FROM users u WHERE u.role='teacher' AND u.school_id=? ORDER BY u.name LIMIT ".SP_MAX,[$sid]);
 if(!$list){echo '<section class="card"><p class="muted">No teachers in this school yet. <a href="?page=add_teachers">Add teachers</a></p></section>';return;}
 echo '<div class="sp-people">';
 foreach($list as $t)echo '<article class="card sp-person">'.avatar($t).'<h3>'.e($t['name']).'</h3><small>'.e($t['public_id']).((int)$t['active']?'':' · Off').'</small>'
  .'<p class="sp-secs">'.e((string)$t['secs']?:'No sections yet').'</p>'
  .'<div class="sp-mini"><span><b>'.(int)$t['pupils'].'</b>'.((int)$t['pupils']===1?'Pupil':'Pupils').'</span><span><b>'.number_format((int)$t['done']).'</b>Lessons finished</span></div>'
  .'<a class="btn secondary small" href="?page=directory&amp;tab=pupils&amp;school='.$sid.'&amp;teacher='.(int)$t['id'].'">'.icon('people').'Their pupils</a></article>';
 echo '</div>';
}

function sp_pupils(int $sid):void{
 $q=trim(mb_substr((string)($_GET['q']??''),0,60));$grade=(int)($_GET['grade']??0);$w='';$args=[$sid];
 if($q!==''){$w.=' AND (u.name LIKE ? OR u.public_id LIKE ?)';$like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';array_push($args,$like,$like);}
 if($grade){$w.=' AND p.grade_level=?';$args[]=$grade;}
 $sql="SELECT u.id,u.name,u.public_id,u.avatar_path,u.active,p.grade_level,p.section,a.level_id,COALESCE(x.total,0) xp"
  .",(SELECT COUNT(*) FROM pupil_progress pp WHERE pp.pupil_id=u.id AND pp.completed_at IS NOT NULL) done"
  .",(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM teacher_pupils tp JOIN users t ON t.id=tp.teacher_id WHERE tp.pupil_id=u.id) teachers"
  ." FROM users u JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id LEFT JOIN pupil_xp x ON x.pupil_id=u.id WHERE u.role='pupil' AND u.school_id=?".$w;
 $n=(int)val('SELECT COUNT(*) FROM ('.$sql.') z',$args);$list=rows($sql.' ORDER BY p.grade_level,p.section,u.name LIMIT '.SP_MAX,$args);
 echo '<section class="card sp-card"><form class="dir-filters" method="get"><input type="hidden" name="page" value="school"><input type="hidden" name="id" value="'.$sid.'"><input type="hidden" name="tab" value="pupils">'
  .'<label class="dir-q">'.icon('search').'<span class="sr-only">Search</span><input type="search" name="q" value="'.e($q).'" placeholder="Name or pupil ID"></label><label>Grade<select name="grade"><option value="0">All grades</option>';
 for($g=1;$g<=6;$g++)echo '<option value="'.$g.'"'.($grade===$g?' selected':'').'>Grade '.$g.'</option>';
 echo '</select></label><button class="btn primary">'.icon('search').'Show</button>'.($q!==''||$grade?'<a class="btn quiet" href="?page=school&amp;id='.$sid.'&amp;tab=pupils">Clear</a>':'').'</form>';
 echo '<p class="dir-count"><b>'.number_format($n).'</b> '.($n===1?'pupil':'pupils').($n>SP_MAX?' · showing the first '.SP_MAX.'. Search or filter to see the rest.':'').'</p>';
 if(!$list){echo '<p class="muted dir-empty">No pupils match.</p></section>';return;}
 echo '<div class="tablewrap"><table class="sp-tbl"><thead><tr><th>Pupil</th><th>Grade · section</th><th>Teacher</th><th>Level</th><th>Lessons finished</th><th>XP</th></tr></thead><tbody>';
 foreach($list as $r){$lv=$r['level_id']===null?'—':((int)$r['level_id']?level_label((int)$r['level_id']):'Reading check');
  echo '<tr><td><span class="sp-pp">'.avatar($r).'<span><b>'.e($r['name']).'</b><small>'.e($r['public_id']).((int)$r['active']?'':' · Off').'</small></span></span></td><td>'.((int)$r['grade_level']?'Grade '.(int)$r['grade_level']:'—').($r['section']!==null&&$r['section']!==''?' · '.e($r['section']):'').'</td><td>'.e((string)$r['teachers']?:'—').'</td><td>'.e($lv).'</td><td><b>'.(int)$r['done'].'</b></td><td>'.number_format((int)$r['xp']).'</td></tr>';}
 echo '</tbody></table></div></section>';
}

/** "Today, 2:15 PM", "Yesterday", "3 days ago", or the date. */
function sp_when(string $at):string{
 $t=strtotime($at);if(!$t)return '';$d=(int)floor((strtotime('today')-strtotime(date('Y-m-d',$t)))/86400);
 return $d<=0?'Today, '.date('g:i A',$t):($d===1?'Yesterday':($d<7?$d.' days ago':date('M j, Y',$t)));
}
