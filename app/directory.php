<?php
/* Administrator: School directory (v177). Every pupil and teacher of the schools the administrator is looking at
   ("Showing: All schools | PCES | MFCES", the same school pick as Teachers and Reports): read-only lists with search and filters.
   Pupils: name, ID, LRN, grade, section, teacher, starting level, school. Teachers: name, ID, school, sections and their pupils. */
const DIR_MAX=600;

function directory_view(array $u):void{
 $tab=($_GET['tab']??'')==='teachers'?'teachers':'pupils';$q=trim(mb_substr((string)($_GET['q']??''),0,60));$grade=(int)($_GET['grade']??0);$tid=(int)($_GET['teacher']??0);$lv=(int)($_GET['level']??-1);
 $sc=schools_ready();$like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';
 $teachers=rows("SELECT u.id,u.name FROM users u WHERE u.role='teacher'".school_users_sql()." ORDER BY u.name");
 admin_heading('PEOPLE · SCHOOL DIRECTORY','School directory.','Every pupil and teacher in '.($sc&&($f=school_filter_id())?e(school_label(school_row($f))):'your schools').'. Search by name, ID or LRN, or filter by grade, teacher and level.');
 echo school_filter_bar('directory','&amp;tab='.$tab);
 $base=['page'=>'directory','tab'=>$tab];
 echo '<section class="card dir-card"><nav class="bk-tabs" aria-label="Show"><a href="?'.e(http_build_query(['page'=>'directory','tab'=>'pupils'])).'"'.($tab==='pupils'?' class="on" aria-current="page"':'').'>'.icon('people').'Pupils</a><a href="?'.e(http_build_query(['page'=>'directory','tab'=>'teachers'])).'"'.($tab==='teachers'?' class="on" aria-current="page"':'').'>'.icon('useredit').'Teachers</a></nav>';
 echo '<form class="dir-filters" method="get"><input type="hidden" name="page" value="directory"><input type="hidden" name="tab" value="'.$tab.'"><label class="dir-q">'.icon('search').'<span class="sr-only">Search</span><input type="search" name="q" value="'.e($q).'" placeholder="'.($tab==='pupils'?'Name, pupil ID or LRN':'Name or teacher ID').'"></label>';
 if($tab==='pupils'){
  echo '<label>Grade<select name="grade"><option value="0">All grades</option>';for($g=1;$g<=6;$g++)echo '<option value="'.$g.'"'.($grade===$g?' selected':'').'>Grade '.$g.'</option>';echo '</select></label>';
  echo '<label>Teacher<select name="teacher"><option value="0">All teachers</option>';foreach($teachers as $t)echo '<option value="'.(int)$t['id'].'"'.($tid===(int)$t['id']?' selected':'').'>'.e($t['name']).'</option>';echo '</select></label>';
  echo '<label>Starting level<select name="level"><option value="-1">All levels</option>'.(function_exists('rc_ready')&&rc_ready()?'<option value="0"'.($lv===0?' selected':'').'>Reading check</option>':'');foreach(rows('SELECT id FROM bulig_levels ORDER BY '.level_order_sql('id')) as $l)echo '<option value="'.(int)$l['id'].'"'.($lv===(int)$l['id']?' selected':'').'>'.e(level_label((int)$l['id'])).'</option>';echo '</select></label>';
 }
 echo '<button class="btn primary">'.icon('search').'Show</button>'.($q!==''||$grade||$tid||$lv>=0?'<a class="btn quiet" href="?'.e(http_build_query($base)).'">Clear</a>':'').'</form>';
 if($tab==='pupils'){
  $w='';$args=[];
  if($q!==''){$w.=' AND (u.name LIKE ? OR u.public_id LIKE ?'.(dir_has('pupil_details')?' OR d.lrn LIKE ?':'').')';array_push($args,$like,$like);if(dir_has('pupil_details'))$args[]=$like;}
  if($grade){$w.=' AND p.grade_level=?';$args[]=$grade;}
  if($tid){$w.=' AND EXISTS(SELECT 1 FROM teacher_pupils x WHERE x.pupil_id=u.id AND x.teacher_id=?)';$args[]=$tid;}
  if($lv>=0){$w.=$lv?' AND a.level_id=?':' AND (a.level_id IS NULL OR a.level_id=0)';if($lv)$args[]=$lv;}
  $sql="SELECT u.id,u.public_id,u.name,u.active,p.grade_level,p.section,a.level_id".(dir_has('pupil_details')?',d.lrn':",'' lrn").($sc?',s.code school':",'' school")
   .",(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM teacher_pupils tp JOIN users t ON t.id=tp.teacher_id WHERE tp.pupil_id=u.id) teachers"
   ." FROM users u JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id".(dir_has('pupil_details')?' LEFT JOIN pupil_details d ON d.pupil_id=u.id':'').($sc?' LEFT JOIN schools s ON s.id=u.school_id':'')
   ." WHERE u.role='pupil'".school_users_sql().$w;
  $n=(int)val('SELECT COUNT(*) FROM ('.$sql.') x',$args);$list=rows($sql.' ORDER BY p.grade_level,p.section,u.name LIMIT '.DIR_MAX,$args);
  echo '<p class="dir-count"><b>'.number_format($n).'</b> '.($n===1?'pupil':'pupils').($n>DIR_MAX?' · showing the first '.DIR_MAX.'. Search or filter to see the rest.':'').'</p>';
  if(!$list){echo '<p class="muted dir-empty">No pupils match.</p></section>';return;}
  echo '<div class="tablewrap"><table class="dir-table"><thead><tr><th>Pupil</th><th>LRN</th><th>Grade · section</th><th>Teacher</th><th>Starting level</th>'.($sc?'<th>School</th>':'').'<th>Status</th></tr></thead><tbody>';
  foreach($list as $r)echo '<tr><td><b>'.e($r['name']).'</b><small>'.e($r['public_id']).'</small></td><td>'.e((string)$r['lrn']?:'—').'</td><td>'.($r['grade_level']?'Grade '.(int)$r['grade_level']:'—').($r['section']!==null&&$r['section']!==''?' · '.e($r['section']):'').'</td><td>'.e((string)$r['teachers']?:'—').'</td><td>'.($r['level_id']===null?'—':((int)$r['level_id']?e(level_label((int)$r['level_id'])):'Reading check')).'</td>'.($sc?'<td>'.e((string)$r['school']?:'—').'</td>':'').'<td>'.((int)$r['active']?'<span class="pill ok">Active</span>':'<span class="pill skip">Off</span>').'</td></tr>';
  echo '</tbody></table></div></section>';return;
 }
 $w='';$args=[];if($q!==''){$w=' AND (u.name LIKE ? OR u.public_id LIKE ?)';$args=[$like,$like];}
 $live=sections_archive_supported()?' AND s2.archived_at IS NULL':'';
 $list=rows("SELECT u.id,u.public_id,u.name,u.active".($sc?',s.code school':",'' school")
  .",(SELECT COUNT(*) FROM teacher_pupils tp WHERE tp.teacher_id=u.id) pupils"
  .",(SELECT GROUP_CONCAT(CONCAT('Grade ',s2.grade_level,' · ',s2.name) ORDER BY s2.grade_level,s2.name SEPARATOR '; ') FROM sections s2 WHERE s2.teacher_id=u.id".$live.") secs"
  ." FROM users u".($sc?' LEFT JOIN schools s ON s.id=u.school_id':'')." WHERE u.role='teacher'".school_users_sql().$w." ORDER BY u.name LIMIT ".DIR_MAX,$args);
 echo '<p class="dir-count"><b>'.count($list).'</b> '.(count($list)===1?'teacher':'teachers').'</p>';
 if(!$list){echo '<p class="muted dir-empty">No teachers match.</p></section>';return;}
 echo '<div class="dir-teachers">';
 foreach($list as $t){$pp=(int)$t['pupils'];
  echo '<details class="dir-t"><summary><span class="dir-tn"><b>'.e($t['name']).'</b><small>'.e($t['public_id']).($sc&&$t['school']?' · '.e($t['school']):'').((int)$t['active']?'':' · Off').'</small></span><span class="dir-ts">'.e((string)$t['secs']?:'No sections yet').'</span><span class="pill ok">'.$pp.' '.($pp===1?'pupil':'pupils').'</span></summary>';
  $pl=$pp?rows("SELECT u.name,u.public_id,p.grade_level,p.section FROM teacher_pupils tp JOIN users u ON u.id=tp.pupil_id JOIN pupils p ON p.user_id=u.id WHERE tp.teacher_id=? ORDER BY p.grade_level,p.section,u.name LIMIT 300",[(int)$t['id']]):[];
  echo $pl?'<ol class="dir-pl">'.implode('',array_map(fn($r)=>'<li><b>'.e($r['name']).'</b> <small>'.e($r['public_id']).' · Grade '.(int)$r['grade_level'].($r['section']!==''&&$r['section']!==null?' · '.e($r['section']):'').'</small></li>',$pl)).'</ol>':'<p class="muted">No pupils yet.</p>';
  echo '<a class="btn quiet small" href="?page=directory&amp;tab=pupils&amp;teacher='.(int)$t['id'].'">'.icon('arrow').'Open as a pupil list</a></details>';}
 echo '</div></section>';
}
function dir_has(string $t):bool{static $c=[];return $c[$t]??=(bool)one('SHOW TABLES LIKE '.db()->quote($t));}
