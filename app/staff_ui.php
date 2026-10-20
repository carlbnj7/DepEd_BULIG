<?php
/* The menus and the "where is it?" pages for teachers, the administrator and pupils.
   - Menus are grouped under yellow words (MY CLASS, TEACHING, PRINT; PEOPLE, REPORTS & PRINT, SYSTEM;
     LEARN, MY PROGRESS, ABOUT ME). Teachers and the administrator also get a Search box (assets/staff.js; v172: the box just says "Search").
   - Teacher pages: Home (what needs you first, then quick tasks), Print centre (every printout), Add pupils
     (one pupil or a whole class), and "Where things are now".
   - Administrator pages: Home, Print & download, Add teachers (one or many).
   Old page links keep working; the pages themselves did not change. */

/** The menu of each role: [page, icon, label, extra] or ['g', group word] or ['hr']. */
function staff_nav_list(array $u):array{
 if($u['role']==='pupil')return [['dashboard','home','Overview'],['g','LEARN'],['lessons','book','My lessons'],['board','ladder','My reading board'],['story','comic','Story time'],['g','MY PROGRESS'],['calendar','cal','My calendar'],['achievements','star','My achievements'],['g','ABOUT ME'],['notifications','bell','Notifications',staff_notif_badge($u)],['profile','user','My profile'],['settings','settings','Settings']];
 if($u['role']==='teacher'){$tid=(int)$u['id'];$rc=function_exists('rc_todo_count')?rc_todo_count($tid):0;
  return [['dashboard','home','Home'],['g','MY CLASS'],['manage','people','My pupils'],['accounts','useredit','Add pupils'],['rcheck','ladder','Reading check',$rc?['count'=>$rc]:(staff_seen($tid,'rcheck')?[]:['tag'=>'NEW'])],['sections','school','Sections'],
   ['g','TEACHING'],['class_demo','book','Class Demo'],['story','comic','Story time'],['qcheck','chart','Class results'],['g','PRINT'],['print','print','Print centre'],['hr'],['notifications','bell','Notifications',staff_notif_badge($u)],['profile','user','My profile']];}
 return [['dashboard','home','Home'],['g','PEOPLE'],['schools','school','Schools'],['accounts','people','Teachers'],['add_teachers','useredit','Add teachers'],['checkup','shield','Account check'],
  ['g','REPORTS & PRINT'],['reports','chart','School reports'],['ratings','star','App ratings'],['print','print','Print & download'],['g','SYSTEM'],['activity_log','clock','Activity log'],['settings','settings','Settings'],['hr'],['notifications','bell','Notifications',staff_notif_badge($u)],['profile','user','My profile']];
}
/** The number of new notifications beside Notifications in the menu. */
function staff_notif_badge(array $u):array{$n=function_exists('notif_count')?notif_count($u):0;return $n?['count'=>$n]:[];}
/** Which menu item a page belongs to. */
function staff_nav_on(string $role,string $page):string{
 if($role==='teacher')return ['pupil'=>'manage','progress'=>'manage','cards'=>'print','certificates'=>'print','report'=>'print','report_card'=>'print','section_report'=>'print','rcheck_pupil'=>'rcheck','rcheck_rules'=>'rcheck','rcheck_print'=>'rcheck','review'=>'qcheck','guide'=>'class_demo','moved'=>'dashboard','rate'=>'profile'][$page]??$page;
 if($role==='admin')return ['teacher_cards'=>'print','teacher_import'=>'add_teachers','guide'=>'dashboard','visual_audit'=>'dashboard','health'=>'print','moved'=>'dashboard','rcheck_rules'=>'dashboard'][$page]??$page;
 return $page;
}
function staff_nav_html(array $u,string $page):string{
 $on=staff_nav_on($u['role'],$page);$h='';
 foreach(staff_nav_list($u) as $it){
  if($it[0]==='g'){$h.='<p class="navlabel nv-g">'.e($it[1]).'</p>';continue;}
  if($it[0]==='hr'){$h.='<hr class="nv-hr">';continue;}
  [$k,$ic,$t]=$it;$x=$it[3]??[];$sel=$on===$k;
  $h.='<a class="navitem'.($sel?' selected':'').'" href="?page='.$k.'"'.($sel?' aria-current="page"':'').'><span class="nav-ic">'.icon($ic).(!empty($x['count'])?'<b class="nv-count" aria-label="'.(int)$x['count'].' waiting">'.((int)$x['count']>999?'999+':(int)$x['count']).'</b>':'').(!empty($x['tag'])?'<b class="nv-new">'.e($x['tag']).'</b>':'').'</span><span class="nav-tx">'.e($t).'</span></a>';}
 return $h;
}
/** Answers still waiting for the teacher's feedback (the number on "Check answers"). */
function staff_review_count(int $tid):int{static $c=[];return $c[$tid]??=(int)val("SELECT COUNT(*) FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id WHERE t.teacher_id=? AND c.status IN ('completed','approved') AND c.reviewed_at IS NULL",[$tid]);}
function staff_seen(int $uid,string $what):bool{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',['seen_'.$what.'_'.$uid]);}
function staff_mark_seen(int $uid,string $what):void{q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['seen_'.$what.'_'.$uid,date('Y-m-d H:i:s')]);}

/** "Find a page": every page and common task, searched in the browser (assets/staff.js). */
function staff_find_list(string $role):array{
 if($role==='teacher')return [
  ['Home','Things that need you, quick tasks','?page=dashboard','home'],['My pupils','Pupil list: open, edit, tick many','?page=manage','people'],['Add a pupil','Add pupils › One pupil','?page=accounts','useredit'],['Add a whole class','Add pupils › A whole class (Excel list, SF1)','?page=accounts&mode=class','excel'],
  ['Reading check','Starting levels from the reading check','?page=rcheck','ladder'],['How BULIG decides the starting level','Reading check › How BULIG decides','?page=rcheck_rules','info'],['Sections','Add or rename a section','?page=sections','school'],
  ['Change a starting level','My pupils › tick pupils › Change starting level','?page=manage','ladder'],['Reset a pupil’s password','My pupils › Edit › New starter password','?page=manage','key'],['Move pupils to another section','My pupils › tick pupils › Move to a section','?page=manage','move'],
  ['Class Demo','Show a lesson on the big screen','?page=class_demo','book'],['Story time','The comic How the BULIG Friends Met · full screen for the class','?page=story','comic'],['Mark lessons done in class','Open a pupil › Learning path, or Class Demo','?page=class_demo','check'],['Class results','Which questions the class finds hard (was Missed questions)','?page=qcheck','chart'],['Answers and scores','BULIG checks every answer · open a pupil › Answers and scores','?page=manage','check'],['Change a score or write a note','Open a pupil › Answers and scores','?page=manage','edit'],
  ['Class progress','My pupils › Class progress','?page=progress','chart'],['Print centre','Everything you can print','?page=print','print'],['Print sign-in tickets','Print centre › Sign-in tickets','?page=cards','print'],['Print report cards','Print centre › Report cards','?page=print#pc-reportcards','print'],
  ['Print progress reports','Print centre › Progress reports','?page=print#pc-progress','print'],['Print certificates','Print centre › Certificates','?page=print#pc-certs','print'],['Print class progress sheet','Print centre › Class progress sheet','?page=print#pc-sheet','print'],['Print reading check results','Print centre › Reading check results','?page=rcheck_print','print'],
  ['Notifications','Messages and help requests','?page=notifications','bell'],['My profile and password','Photo, character, dark mode, password','?page=profile','user'],['Rate BULIG','My profile › Rate BULIG · 5 quick questions','?page=rate','star'],['Where things are now','What moved in the new menu','?page=moved','info']];
 return [
  ['Home','Things that need you, quick tasks','?page=dashboard','home'],['Schools','Each school’s code, name and logo · Add a school','?page=schools','school'],['Teachers','Your teaching team','?page=accounts','people'],['Add a teacher','Add teachers › One teacher','?page=add_teachers','useredit'],['Add many teachers','Add teachers › Many teachers (Excel list)','?page=add_teachers&mode=many','excel'],
  ['Teacher Passes','Print & download › Teacher Passes','?page=teacher_cards','print'],['Reset a teacher’s password','Teachers › New starter password','?page=accounts','key'],['Move pupils to another teacher','Teachers › Move pupils','?page=accounts#move','move'],['Account check','Accounts to fix','?page=checkup','shield'],
  ['App ratings','How pupils and teachers rate BULIG','?page=ratings','star'],['School reports','Progress by grade and section','?page=reports','chart'],
  ['Print & download','Every print and download','?page=print','print'],['Download a backup','Print & download › Backup','?page=print#pc-backup','download'],['Activity log','Who did what, and when','?page=activity_log','clock'],
  ['Settings','Security, school info, announcements, school year','?page=settings','settings'],['School logos','Settings › School info','?page=settings&tab=school','image'],['Post an announcement','Settings › Announcements','?page=settings&tab=announcements','mega'],['End of school year','Settings › School year','?page=settings&tab=year','cal'],
  ['Account IDs','Settings › Account IDs','?page=settings&tab=ids','key'],['Admin PIN','Settings › Security','?page=settings&tab=security','lock'],['My profile and password','Photo, character, dark mode, password','?page=profile','user'],['Where things are now','What moved in the new menu','?page=moved','info']];
}
function staff_find_box(string $role):string{
 $items=array_map(fn($x)=>[$x[0],$x[1],$x[2],icon($x[3])],staff_find_list($role));
 return '<div class="nv-find" data-find=\''.e(json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'\'>'.icon('search').'<input type="search" placeholder="Search" aria-label="Search" autocomplete="off" data-find-input><div class="nv-results" role="listbox" aria-label="Pages found" data-find-out hidden></div></div>';
}

/* ---------------- shared pieces of the new pages ---------------- */
function hm_head(string $eyebrow,string $title,string $sub,string $buttons=''):string{return '<div class="hm-head"><div><span class="eyebrow">'.$eyebrow.'</span><h1>'.e($title).'</h1><p class="muted">'.$sub.'</p></div>'.($buttons!==''?'<div class="buttonrow">'.$buttons.'</div>':'').'</div>';}
function hm_todo(string $cls,string $ic,int $n,string $title,string $small,string $link,string $href,bool $hand=false):string{return '<a class="hm-t '.$cls.'" href="'.e($href).'"><span class="hm-ic">'.($hand?HELP_HAND:icon($ic)).'</span><b>'.number_format($n).'</b><span>'.e($title).'</span><small>'.e($small).'</small><em>'.e($link).' '.icon('arrow').'</em></a>';}
function hm_task(string $ic,string $title,string $small,string $href,bool $print=false):string{return '<a class="hm-task'.($print?' pr':'').'" href="'.e($href).'"><i>'.icon($ic).'</i><span>'.e($title).'<small>'.e($small).'</small></span></a>';}
/** "The menu has a new order" (once per person, until closed). */
function staff_moved_note(array $u):string{
 if(staff_seen((int)$u['id'],'moved'))return '';
 return '<div class="hm-moved" role="status"><i>'.icon('info').'</i><div><b>The menu has a new, simpler order.</b>'.($u['role']==='teacher'?'Everything you can print is now in one place: <b>Print centre</b>. Adding pupils is in the menu too.':'Teacher Passes, reports and backups are now in one place: <b>Print &amp; download</b>. Adding teachers is in the menu too.').'</div><a class="btn secondary" href="?page=moved">See what moved</a><form method="post" class="hm-x">'.csrf_field().'<input type="hidden" name="action" value="moved_seen"><button aria-label="Close this note" title="Close">'.icon('close').'</button></form></div>';
}

/* ---------------- teacher: Home ---------------- */
function teacher_home(array $u):void{
 $tid=(int)$u['id'];echo tf_defs().teacher_welcome($u);teacher_announcements();echo staff_moved_note($u);
 [$grade,$sid]=teacher_class_selection($u);
 $pupils=rows('SELECT u.*,p.grade_level,p.section,l.title level_title,a.level_id FROM users u JOIN pupils p ON p.user_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id LEFT JOIN bulig_levels l ON l.id=a.level_id WHERE t.teacher_id=? AND p.grade_level=? AND (?=0 OR u.id IN(SELECT pupil_id FROM pupil_sections WHERE section_id=?)) ORDER BY u.name',[$tid,$grade,$sid,$sid]);
 $pending=(int)val("SELECT COUNT(*) FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id JOIN pupils p ON p.user_id=c.pupil_id WHERE t.teacher_id=? AND p.grade_level=? AND (?=0 OR c.pupil_id IN(SELECT pupil_id FROM pupil_sections WHERE section_id=?)) AND c.status IN ('completed','approved')",[$tid,$grade,$sid,$sid]);
 $ids=array_map(fn($p)=>(int)$p['id'],$pupils);$stats=progress_stats_many($ids);
 $all=rows('SELECT u.id,u.name,u.active,u.avatar_path FROM users u JOIN teacher_pupils t ON t.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1',[$tid]);$allStats=progress_stats_many(array_map(fn($p)=>(int)$p['id'],$all));
 $need=array_values(array_filter($all,fn($p)=>tf_needs_checkin($p,$allStats[(int)$p['id']])));
 $badgeWeek=$ids?array_flip(array_map('intval',array_column(rows('SELECT DISTINCT pupil_id FROM pupil_badges WHERE pupil_id IN('.tf_in($ids).') AND earned_at>=?',[date('Y-m-d H:i:s',strtotime('-7 days'))]),'pupil_id'))):[];
 echo hm_head('TEACHER HOME','What would you like to do today?','Things that need you come first. Everything else is one tap away.','<a class="btn secondary" href="?page=print">'.icon('print').'Print centre</a><a class="btn secondary" href="?page=class_demo">'.icon('book').'Class Demo</a><a class="btn primary" href="?page=accounts">'.icon('useredit').'Add pupils</a>');
 $checked=(int)val("SELECT COUNT(*) FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id WHERE t.teacher_id=? AND c.status IN ('completed','approved')",[$tid]);$hw=date('Y-m-d H:i:s',strtotime('-7 days'));$help=help_ready()&&notif_ready()?(int)val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND kind='help' AND read_at IS NULL AND created_at>=?",[$tid,$hw]):0;
 $helpWeek=$help?:(help_ready()&&notif_ready()?(int)val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND kind='help' AND created_at>=?",[$tid,$hw]):0);/* helped ones stay listed for the week, marked Seen */
 $rcTodo=function_exists('rc_todo_count')?rc_todo_count($tid):0;$rcWait=rc_ready()?(int)val('SELECT COUNT(*) FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 LEFT JOIN pupil_level_assignments a ON a.pupil_id=t.pupil_id WHERE t.teacher_id=? AND a.level_id IS NULL',[$tid]):0;
 $names=array_map(fn($p)=>tf_first($p),array_slice($need,0,3));
 echo '<p class="hm-sub">Needs you today</p><div class="hm-todo" id="tf-todo">'
  .hm_todo($need?'hot':'calm','alert',count($need),'Need a check-in',$need?implode(', ',$names).(count($need)>3?' and '.(count($need)-3).' more':'').' · no learning in 7 days':'Everyone learned in the last 7 days','See who','#tf-class')
  .hm_todo($rcTodo?'hot':'calm','ladder',$rcTodo+$rcWait,'Reading checks',$rcTodo?$rcTodo.' to look at'.($rcWait?' · '.$rcWait.' not finished yet':''):($rcWait?$rcWait.' pupils not finished yet':'New pupils find their level here'),'Open reading check','?page=rcheck')
  .hm_todo($help?'hot':'calm','hand',$help,'Asked for help',$help?'Pupils tapped “I need help” this week':'No one asked for help this week',$help?'See who':($helpWeek?'See this week':'How it works'),$helpWeek?'#tf-help':'?page=moved#help',true).'</div>';
 if(strtotime((string)($u['created_at']??'now'))>=strtotime('-7 days'))echo '<div class="hm-auto"><span class="hm-auto-ic">'.icon('check').'</span><div><b>BULIG checks every answer for you</b><small>'.($checked?number_format($checked).' '.($checked===1?'answer':'answers').' checked so far. ':'').'Pupils see their score and the right answers at once. Nothing waits for you. You can still change a score or write a note on any pupil’s page.</small></div><a href="?page=qcheck">Class results '.icon('arrow').'</a></div>';
 if($helpWeek)echo help_teacher_card($u);
 if(function_exists('rate_due')&&rate_due($u))echo rate_teacher_form(true);/* the quarterly evaluation (v152) */
 month_recap_teacher($u);schedule_open_check_teacher($tid);
 echo '<p class="hm-sub">What do you want to do?</p><div class="hm-grid">'
  .hm_task('useredit','Add pupils','One pupil or a whole class list','?page=accounts').hm_task('print','Print sign-in tickets','Reading Pass with ID and QR','?page=cards',true)
  .hm_task('print','Print report cards','One pupil or a whole section','?page=print#pc-reportcards',true).hm_task('star','Print certificates','For pupils who finished a level','?page=print#pc-certs',true)
  .'</div>'.claude_tip($u);   // the other tasks are in the menu (My pupils, Class Demo, Class results)
 echo '<p class="hm-sub" id="tf-class">My class</p>';section_filter($u,'dashboard',$sid,$grade);
 echo tf_stats($ids,$pending,(int)val('SELECT COUNT(*) FROM lessons WHERE published=1'));$sn=$sid?(string)val('SELECT name FROM sections WHERE id=?',[$sid]):'';echo tf_leaderboard($ids,'Grade '.$grade.($sn!==''?' · '.$sn:''));
 echo '<section class="card tf-table tf-list"><div class="section-heading"><h2>My pupils</h2><a href="?page=manage">All pupils '.icon('arrow').'</a></div>';
 if(!$pupils)echo '<p class="muted">No pupils in this selection yet. Choose another section or add pupils.</p>';
 else{echo '<div class="cl-list">';
  foreach(array_slice($pupils,0,8) as $p){$s=$stats[(int)$p['id']];$inactive=!$s['last_activity_date']||$s['last_activity_date']<date('Y-m-d',strtotime('-7 days'));[$label,$tone]=!$p['active']?['Inactive','off']:(!$p['level_id']?['Reading check','warn']:($inactive?['Check in','warn']:['On track','ok']));
   $last=$s['last_activity_date']?($s['last_activity_date']===date('Y-m-d')?'learned today':'last learned '.date('M j',strtotime($s['last_activity_date']))):'not started yet';
   echo '<a class="cl-row" href="?page=pupil&amp;id='.(int)$p['id'].'">'.avatar($p).'<b>'.e($p['name']).'</b><small>'.e(($p['level_id']?level_label((int)$p['level_id']):'No level yet').' · '.($p['section']?:'—').' · '.$last).'</small><span class="status status-'.$tone.'">'.$label.'</span></a>';}
  echo '</div>'.(count($pupils)>8?'<a class="cl-more" href="?page=manage">See all '.count($pupils).' pupils '.icon('arrow').'</a>':'');}
 echo '<a class="cl-charts" href="?page=qcheck#glance">'.icon('chart').'<span>Class activity, reading tree and milestones are in <b>Class results</b></span>'.icon('arrow').'</a></section>';
}

/* ---------------- teacher: Print centre ---------------- */
function print_card(string $id,string $img,string $title,string $text,string $form,string $also='',string $tag=''):string{
 return '<section class="card pc-card" id="'.$id.'"><div class="pc-thumb'.($img===''||str_starts_with($img,'icon:')?' ic':'').'">'.($img!==''&&!str_starts_with($img,'icon:')?'<img src="'.e($img).'" alt="" loading="lazy">':'<span class="pc-ic">'.icon($img!==''?substr($img,5):'print').'</span>').($tag?'<span class="pc-tag'.($tag==='NEW'?' new':'').'">'.e($tag).'</span>':'').'</div><div class="pc-body"><h3>'.e($title).'</h3><p>'.$text.'</p>'.$form.($also!==''?'<p class="pc-also">'.icon('info').'<span>'.$also.'</span></p>':'').'</div></section>';
}
function print_select(string $label,string $name,array $opts):string{$h='<label class="pc-for">'.e($label).'<select name="'.e($name).'">';foreach($opts as $v=>$t)$h.='<option value="'.e((string)$v).'">'.e($t).'</option>';return $h.'</select></label>';}
function print_centre_view(array $u):void{
 $tid=(int)$u['id'];$secs=teacher_sections($tid);$pupils=rows('SELECT u.id,u.name,s.grade_level,s.name section FROM users u JOIN teacher_pupils t ON t.pupil_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN sections s ON s.id=ps.section_id WHERE t.teacher_id=? AND u.active=1 ORDER BY s.grade_level,s.name,u.name',[$tid]);
 $secOpts=[0=>'All my pupils · '.count($pupils)];foreach($secs as $s)$secOpts[(int)$s['id']]='Grade '.(int)$s['grade_level'].' · '.$s['name'];
 $pupOpts=[];foreach($pupils as $p)$pupOpts[(int)$p['id']]=$p['name'].($p['section']?' · Grade '.(int)$p['grade_level'].' · '.$p['section']:'');
 $rcOpts=[];foreach($secs as $s)$rcOpts['s'.(int)$s['id']]='Whole section: Grade '.(int)$s['grade_level'].' · '.$s['name'];foreach($pupils as $p)$rcOpts['p'.(int)$p['id']]='One pupil: '.$p['name'];
 $ready=[];foreach($pupils as $p)foreach(finished_levels((int)$p['id']) as $l=>$d)$ready[$l]=($ready[$l]??0)+1;
 $certOpts=[];foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id') as $l)$certOpts[(int)$l['id']]=level_label((int)$l['id']).' · '.($ready[(int)$l['id']]??0).' ready';
 $levOpts=[];foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id') as $l)$levOpts[(int)$l['id']]=level_label((int)$l['id']);
 $btn=fn($t)=>'<div class="pc-btns"><button class="btn primary">'.icon('print').e($t).'</button></div>';$f=fn($page,$inner)=>'<form method="get" action="" class="pc-form"><input type="hidden" name="page" value="'.$page.'">'.$inner.'</form>';
 $img=fn($n)=>is_file(__DIR__.'/../public/assets/images/print/'.$n.'.webp')?'assets/images/print/'.$n.'.webp':'icon:print';
 echo hm_head('PRINT CENTRE','Everything you can print','Choose what to print and for whom. Each one opens a preview first, with a green <b>Print or save as PDF</b> button.');
 if(!$pupils){echo '<section class="empty">'.icon('people').'<h2>No pupils yet.</h2><p>Add pupils first. Then their tickets, report cards and certificates are ready to print here.</p><a class="btn primary" href="?page=accounts">'.icon('useredit').'Add pupils</a></section>';return;}
 echo '<div class="pc-grid">'.section_report_card($secs,$img);
 echo print_card('pc-tickets',$img('tickets'),'Sign-in tickets','Reading Pass for each pupil: name, Pupil ID, starter password and a QR code to sign in.',$f('cards',print_select('For','section',$secOpts).$btn('Print tickets')),'Also in My pupils: tick pupils, then <b>Print sign-in tickets</b>.');
 echo print_card('pc-reportcards',$img('reportcard'),'Report cards','Reading Progress Report Card with the reading ladder. One A4 page for each pupil.','<form method="get" class="pc-form" data-pc-rc><input type="hidden" name="page" value="report_card">'.print_select('For','who',$rcOpts).$btn('Print report cards').'</form>','Also on each pupil’s page.');
 echo print_card('pc-progress',$img('progress'),'Progress reports','One pupil’s lessons, scores and your notes. Good for a parents’ meeting.',$f('report',print_select('Pupil','id',$pupOpts).$btn('Print progress report')),'Also on each pupil’s page.');
 echo print_card('pc-certs',$img('certificate'),'Certificates','Certificate of Completion for pupils who finished every lesson of a level.',$f('certificates',print_select('Level','level',$certOpts).print_select('For','section',$secOpts).$btn('Print certificates')),'',array_sum($ready)?array_sum($ready).' READY':'');
 echo print_card('pc-sheet',$img('sheet'),'Class progress sheet','Every pupil and every lesson of one level in one table: done, started and not yet.',$f('progress',print_select('Section','section',array_slice($secOpts,1,null,true)?:$secOpts).print_select('Level','level',$levOpts).'<input type="hidden" name="print" value="1">'.$btn('Open sheet')),'Use <b>Print</b> on the sheet to print it.');
 echo print_card('pc-rcheck',$img('rcheck'),'Reading check results','Each pupil’s starting level, the parts of the reading check and the official CRLA or Phil-IRI result.',$f('rcheck_print',print_select('For','section',$secOpts).$btn('Print results')),'','NEW');
 echo '</div><div class="pc-tip"><i>'.icon('info').'</i><div><b>Tip:</b> To keep a copy on your phone or computer, choose <b>Save as PDF</b> in the print window instead of a printer.</div></div>';
}

/* ---------------- teacher: Add pupils (one pupil, or a whole class) ---------------- */
function ap_choice(string $on,array $a,array $b,string $page):string{
 $h='<nav class="ap-choice" aria-label="How do you want to add?">';
 foreach([[$a,'one'],[$b,$page==='add_teachers'?'many':'class']] as [$o,$k]){$sel=$on===$k;$h.='<a class="ap-opt'.($sel?' on':'').'" href="?page='.$page.($k==='one'?'':'&amp;mode='.$k).'"'.($sel?' aria-current="page"':'').'><i>'.icon($o[0]).'</i><span><b>'.e($o[1]).'</b><small>'.e($o[2]).'</small></span>'.($sel?'<em>'.icon('check').'</em>':'').'</a>';}
 return $h.'</nav>';
}
function add_pupils_view(array $u):void{
 $np=(int)($_SESSION['new_pupil']??0);unset($_SESSION['new_pupil']);if($np&&val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$u['id'],$np]))echo new_pupil_overlay($np);
 $mode=($_GET['mode']??'')==='class'||isset($_GET['step'])||isset($_GET['import'])?'class':'one';$secs=teacher_sections((int)$u['id']);
 echo pupils_tabs('accounts',(int)val('SELECT COUNT(*) FROM teacher_pupils WHERE teacher_id=?',[$u['id']]));
 echo ap_choice($mode,['useredit','One pupil','Type the name and choose the section'],['excel','A whole class','Type the list or upload Excel · DepEd SF1 works'],'accounts');
 if($mode==='class'){import_card($u);return;}
 echo '<section class="card"><h2>Add one pupil</h2>';
 if(!$secs)echo '<p class="ap-need">'.icon('info').'<span>No section yet. In Section below, choose “+ Add a new section…”, or open <a href="?page=sections">Sections</a>.</span></p>';
 echo '<form class="formgrid" method="post">'.csrf_field().'<input type="hidden" name="action" value="create_account">'.name_fields().'<div class="default-password"><strong>Starter password</strong><p>Made for each pupil</p><small>BULIG makes an easy password like mango4721. You see it after saving, and it is printed on the pupil’s sign-in ticket. The pupil may change it in Settings.</small></div>';
 echo grade_fields(['level_id'=>rc_ready()?0:1,'section_id'=>(int)($_GET['section']??0)],$secs).'<div class="formend"><small>A unique Pupil ID is made automatically. After saving, BULIG shows the pupil’s sign-in ticket, ready to print.</small><button class="btn primary" type="submit">'.icon('check').'Create account</button></div></form></section>'.names_script();
}

/* ---------------- administrator: Add teachers (one, or many) ---------------- */
function add_teachers_view(array $u):void{
 $mode=($_GET['mode']??'')==='many'||isset($_GET['step'])||($_GET['page']??'')==='teacher_import'?'many':'one';
 admin_heading('PEOPLE','Add teachers','Add one teacher, or the whole teaching team at once: type the list or upload an Excel file. BULIG makes each Teacher ID and starter password.','<a class="btn secondary" href="?page=teacher_cards">'.icon('print').'Print Teacher Passes</a>');
 echo ap_choice($mode,['useredit','One teacher','Type the name of the teacher'],['excel','Many teachers','Type the list or upload Excel'],'add_teachers');
 if($mode==='many'){ob_start();teacher_import_view();$h=(string)ob_get_clean();echo preg_replace('~^.*?(<section)~s','$1',$h,1);return;}
 $nt=(int)($_SESSION['new_teacher']??0);unset($_SESSION['new_teacher']);if($nt)echo new_teacher_overlay($nt);
 echo '<section class="card ap-one"><h2>Add one teacher</h2><form class="ws-formrow" method="post">'.csrf_field().'<input type="hidden" name="action" value="create_account"><input type="hidden" name="back" value="add_teachers">'.school_pick_field().name_fields().''.(cards_ready()?'':'<label>Password (8–72 characters)<input name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"></label>').'<label>Sex<select name="teacher_sex" required><option value="">Choose Male or Female</option><option value="female">Female</option><option value="male">Male</option></select></label><button class="btn primary">'.icon('check').'Create teacher account</button></form><p class="muted ap-note">'.icon('info').'After saving, BULIG shows the new Teacher Pass with the Teacher ID and starter password, ready to print.</p></section>'.names_script();
}

/* ---------------- administrator: Home ---------------- */
function admin_home(array $u):void{
 $su=school_users_sql();$pupils=(int)val("SELECT COUNT(*) FROM users u WHERE role='pupil' AND active=1$su");$newPupils=(int)val("SELECT COUNT(*) FROM users u WHERE role='pupil' AND created_at>=?$su",[date('Y-m-01')]);
 $teachers=rows("SELECT id,name,active FROM users u WHERE role='teacher'$su ORDER BY name");$logins=admin_last_logins();
 $idle=array_values(array_filter($teachers,fn($t)=>$t['active']&&(admin_days_since($logins[(int)$t['id']]??null)??999)>=14));
 $sections=(int)val('SELECT COUNT(*) FROM sections s WHERE 1'.admin_live_sections_sql());$grades=rows('SELECT DISTINCT grade_level g FROM sections s WHERE 1'.admin_live_sections_sql().' ORDER BY g');
 $sp=school_filter_id()?' AND pupil_id IN (SELECT id FROM users WHERE school_id='.school_filter_id().')':'';$week=(int)val('SELECT COUNT(*) FROM activity_completion WHERE submitted_at>=?'.$sp,[date('Y-m-d H:i:s',strtotime('-7 days'))]);$prev=(int)val('SELECT COUNT(*) FROM activity_completion WHERE submitted_at>=? AND submitted_at<?'.$sp,[date('Y-m-d H:i:s',strtotime('-14 days')),date('Y-m-d H:i:s',strtotime('-7 days'))]);
 $trend=$prev?(($week>=$prev?'+':'').round(($week-$prev)/$prev*100).'% vs last week'):($week?'first week of activity':'');
 echo staff_moved_note($u);
 admin_heading('ADMINISTRATOR HOME','What would you like to do today?','School year '.admin_school_year().' · Things that need you come first.','<a class="btn secondary" href="?page=print">'.icon('print').'Print &amp; download</a><a class="btn secondary" href="?page=settings&amp;tab=announcements">'.icon('mega').'Post announcement</a><a class="btn primary" href="?page=add_teachers">'.icon('useredit').'Add teachers</a>');
 echo function_exists('account_ids_admin_notice')?account_ids_admin_notice():'';
 echo school_filter_bar('dashboard').school_compare_cards();
 $inactive=admin_inactive_pupils(7);$top=one("SELECT CONCAT('Grade ',s.grade_level,' · ',s.name) n,COUNT(*) c FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN sections s ON s.id=ps.section_id WHERE u.role='pupil' AND u.active=1".school_users_sql()." AND NOT EXISTS(SELECT 1 FROM learning_days d WHERE d.pupil_id=u.id AND d.day>=?) GROUP BY s.id ORDER BY c DESC LIMIT 1",[date('Y-m-d',strtotime('-6 days'))]);
 $bk=admin_setting('last_backup_at','');$oldBackup=$bk===''||admin_days_since($bk)>7;
 $failed=(int)val("SELECT COUNT(*) FROM audit_log WHERE action IN ('login_failed','admin_pin_failed') AND created_at>=?",[date('Y-m-d 00:00:00')]);
 echo '<p class="hm-sub">Needs you today</p><div class="hm-todo">'
  .hm_todo($oldBackup?'hot':'calm','download',$oldBackup?1:0,'Backup to download',admin_backup_note(),'Open Backup','?page=print#pc-backup')
  .hm_todo($inactive?'hot':'calm','people',$inactive,'Pupils inactive 7+ days',$top?'Most in '.$top['n']:'All pupils learned this week','See the report','?page=reports')
  .hm_todo($idle?'hot':'calm','user',count($idle),'Teachers not signed in 14+ days',$idle?implode(', ',array_slice(array_column($idle,'name'),0,2)).(count($idle)>2?' …':''):'All teachers signed in within 14 days','Open Teachers','?page=accounts'.($idle?'&amp;status=idle':''))
  .hm_todo($failed?'hot':'calm','lock',$failed,'Wrong passwords today',$failed?'Check the activity log':'No wrong passwords today','Open the log','?page=activity_log&amp;tab=security&amp;days=1').'</div>';
 echo '<div class="hm-checkup">'.checkup_overview_item().'</div>';
 echo '<p class="hm-sub">What do you want to do?</p><div class="hm-grid">'
  .hm_task('useredit','Add teachers','One teacher or a whole Excel list','?page=add_teachers').hm_task('print','Print Teacher Passes','Teacher ID, password and QR','?page=teacher_cards',true)
  .hm_task('key','Reset a teacher’s password','Teachers › New starter password','?page=accounts').hm_task('move','Move pupils to a teacher','When a class changes teacher','?page=accounts#move')
  .hm_task('download','Download a backup','Keep a copy of all records','?page=print#pc-backup',true).hm_task('print','Print school report','For the principal or division','?page=reports',true)
  .hm_task('mega','Post an announcement','All teachers see it','?page=settings&tab=announcements').'</div>'.claude_tip($u);
 echo '<p class="hm-sub">School at a glance</p>';
 echo admin_kpis([['people',number_format($pupils),'Pupils',$newPupils?'+'.$newPupils.' this month':'',false],['user',count($teachers),'Teachers',$idle?count($idle).' inactive 14+ days':'all active',(bool)$idle],['book',$sections,'Sections',$grades?'Grades '.implode(', ',array_column($grades,'g')):'none yet',false],['check',number_format($week),'Activities this week',$trend,$prev&&$week<$prev]]);
 $levels=[];foreach(rows("SELECT GREATEST(a.level_id,COALESCE((SELECT MAX(m.level_id) FROM activity_completion c JOIN activities ac ON ac.id=c.activity_id JOIN lessons l ON l.id=ac.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=u.id),0)) lv FROM users u JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE u.role='pupil' AND u.active=1".school_users_sql()) as $r)$levels[(int)$r['lv']]=($levels[(int)$r['lv']]??0)+1;$max=max([1]+$levels);
 echo '<div class="ws-g3 hm-g3"><section class="card"><div class="ws-hd"><h2>Pupils at each level</h2><a href="?page=reports">View report '.icon('arrow').'</a></div><div class="ws-bars">';
 foreach(rows('SELECT id FROM bulig_levels ORDER BY id') as $l){$n=$levels[(int)$l['id']]??0;echo '<div class="ws-bar"><span>'.e(level_label((int)$l['id'])).'</span><u><i class="w-'.(int)(round($n/$max*20)*5).'"></i></u><b>'.$n.'</b></div>';}
 echo '</div></section><section class="card"><div class="ws-hd"><h2>Most active sections</h2><small class="muted">last 30 days</small></div><div class="ws-list">';
 $act=rows("SELECT CONCAT('Grade ',s.grade_level,' · ',s.name) n,COUNT(*) c FROM activity_completion c JOIN pupil_sections ps ON ps.pupil_id=c.pupil_id JOIN sections s ON s.id=ps.section_id WHERE c.submitted_at>=?".admin_live_sections_sql()." GROUP BY s.id ORDER BY c DESC LIMIT 3",[date('Y-m-d',strtotime('-30 days'))]);
 foreach($act as $a)echo '<div class="ws-li">'.icon('star').'<span>'.e($a['n']).'</span><span class="ws-sp ws-pill ok">'.number_format((int)$a['c']).'</span></div>';if(!$act)echo '<p class="muted">No activity in the last 30 days yet.</p>';
 echo '</div></section><section class="card"><div class="ws-hd"><h2>Announcement</h2><a href="?page=settings&amp;tab=announcements">Edit</a></div>';
 $ann=admin_announcements(true);if($ann){$a=$ann[0];echo '<div class="ws-ann"><b>'.e($a['title']).'</b>'.e($a['message']).'<small> · Shown to all teachers'.($a['until']?' until '.e(date('M j',strtotime($a['until']))):'').'</small></div>';}else echo '<p class="muted">No announcement. Post one for all teachers in Settings.</p>';
 echo '</section></div>';
}

/* ---------------- administrator: Print & download ---------------- */
function admin_print_view(array $u):void{
 $img=fn($n)=>is_file(__DIR__.'/../public/assets/images/print/'.$n.'.webp')?'assets/images/print/'.$n.'.webp':'icon:print';
 $tc=(int)val("SELECT COUNT(*) FROM users WHERE role='teacher' AND active=1");
 admin_heading('PRINT & DOWNLOAD','Everything you can print or download','Choose one. Print items open a preview first; download items save an Excel file you can open on any computer.');
 echo '<div class="pc-grid">';
 echo print_card('pc-passes',$img('teacher-passes'),'Teacher Passes','Each teacher’s ID, starter password and a QR code to sign in. Up to 10 on one A4 sheet.','<div class="pc-btns"><a class="btn primary" href="?page=teacher_cards">'.icon('print').'Print '.$tc.' passes</a></div>','Also on the Teachers page.');
 echo print_card('pc-apass','icon:shield','My Admin Pass','Your administrator ID with a QR code. Scan it on a phone’s sign-in page to fill in your ID; you still type your password and PIN.','<div class="pc-btns"><a class="btn primary" href="?page=admin_pass">'.icon('print').'Print my pass</a></div>');
 echo print_card('pc-report',$img('school-report'),'School report','Progress by grade and section, ready for the principal or the division.','<form method="get" class="pc-form"><input type="hidden" name="page" value="reports">'.print_select('Period','period',['year'=>'This school year','month'=>'This month','3m'=>'Last 3 months','all'=>'All time']).'<div class="pc-btns"><button class="btn primary">'.icon('print').'Open to print</button><a class="btn secondary" href="?page=report_csv">'.icon('excel').'Excel</a></div></form>','Also on the School reports page.');
 echo print_card('pc-backup','icon:db','Backup','A copy of all BULIG records. Keep it on a USB drive or in Google Drive once a month.','<div class="pc-btns"><a class="btn primary" href="?page=backup_csv">'.icon('download').'Excel backup</a><a class="btn secondary" href="?page=backup_sql">'.icon('db').'Full database</a></div>','Also in Settings › School year.',admin_setting('last_backup_at','')===''?'NOT DONE YET':'');
 echo print_card('pc-log','icon:clock','Activity log','Sign-ins, wrong passwords and every change to accounts and lessons.','<div class="pc-btns"><a class="btn primary" href="?page=log_csv">'.icon('excel').'Download Excel</a></div>','Also on the Activity log page.');
 echo print_card('pc-ids','icon:key','Account ID list','Every teacher and pupil ID, with old IDs next to the new ones.','<div class="pc-btns"><a class="btn primary" href="?page=ids_csv">'.icon('excel').'Download Excel</a></div>','Also in Settings › Account IDs.');
 echo print_card('pc-tlist','icon:people','Teacher list template','Fill in this Excel file to add the whole teaching team at once.','<div class="pc-btns"><a class="btn primary" href="?page=teacher_template">'.icon('excel').'Download template</a></div>','Upload it in Add teachers › Many teachers.');
 echo '</div><div class="pc-tip"><i>'.icon('info').'</i><div><b>Tip:</b> To keep a copy instead of printing, choose <b>Save as PDF</b> in the print window.</div></div>';
}

/* ---------------- Check answers tabs (teacher; not shown since v151, BULIG checks every answer) ---------------- */
function check_tabs(string $on):string{
 $n=staff_review_count((int)current_user()['id']);
 return '<nav class="pupil-tabs ca-tabs" aria-label="Check answers"><a class="pt-tab'.($on==='review'?' on':'').'" href="?page=review"'.($on==='review'?' aria-current="page"':'').'>'.icon('check').'Pupils’ answers'.($n?'<b>'.$n.'</b>':'').'</a><a class="pt-tab'.($on==='qcheck'?' on':'').'" href="?page=qcheck"'.($on==='qcheck'?' aria-current="page"':'').'>'.icon('chart').'Missed questions</a></nav>';
}

/* ---------------- "Where things are now" ---------------- */
function moved_view(array $u):void{
 if(!staff_seen((int)$u['id'],'moved'))staff_mark_seen((int)$u['id'],'moved');
 $r=fn($a,$b,$s='',$new=false)=>'<tr><td>'.$a.'</td><td>'.icon('arrow').'</td><td>'.$b.($new?'<span class="wm-new">NEW</span>':'').($s!==''?'<small>'.$s.'</small>':'').'</td></tr>';
 $tbl=fn($rows)=>'<div class="tablewrap"><table class="wm-tbl"><thead><tr><th>Before</th><th></th><th>Now</th></tr></thead><tbody>'.$rows.'</tbody></table></div>';
 echo hm_head('WHAT MOVED','Where things are now','The pages are the same. Only the menu order and some names changed. Old links still work and open the new place. Type in <b>Search</b> at the top of the menu if you cannot find something.');
 $t='<section class="card"><h2>'.icon('people').' Teachers</h2>'.$tbl($r('Overview','Home','Things that need you are first, then quick tasks')
  .$r('“Certificates” button on Overview','Print centre › Certificates').$r('Print sign-in tickets (bottom of My pupils › Accounts)','Print centre › Sign-in tickets','Also: My pupils, tick pupils › Print sign-in tickets')
  .$r('Report card (pupil page, My sections)','Print centre › Report cards','Also: each pupil’s page').$r('Progress report (Activity history, pupil page)','Print centre › Progress reports','Also: each pupil’s page')
  .$r('My pupils › Learning and › Accounts (two lists)','My pupils › Pupil list','One list: open, edit, tick many').$r('My pupils › Accounts (create, import)','Add pupils','In the menu: one pupil, or a whole class from Excel')
  .$r('My pupils › Progress','My pupils › Class progress').$r('My sections','Sections','Under MY CLASS').$r('Check answers (and Activity history)','Nothing to check','BULIG checks every answer. To see one, change a score or write a note: open a pupil › Answers and scores',true).$r('Check answers › Missed questions (and Question check)','Class results','Under TEACHING')
  .$r('—','Reading check','Starting levels from the reading check',true).$r('—','Search','Type what you need, for example “print”',true)).'</section>';
 $a='<section class="card"><h2>'.icon('shield').' Administrator</h2>'.$tbl($r('Overview','Home','Things that need you are first, then quick tasks')
  .$r('Teacher Passes (Teachers page)','Print &amp; download › Teacher Passes','Also: Teachers page').$r('Reports › Print / Excel','Print &amp; download › School report','Also: School reports')
  .$r('Download backup (System health)','Print &amp; download › Backup','System health was removed').$r('Activity log › Download CSV','Print &amp; download › Activity log','Also: Activity log')
  .$r('Teachers › Add a teacher / Add many teachers','Add teachers','In the menu: one teacher, or the whole team from Excel')
  .$r('Reports','School reports','Under REPORTS &amp; PRINT').$r('—','Print &amp; download','Every print and download in one place',true).$r('—','Search','Type what you need, for example “backup”',true)).'</section>';
 $p='<section class="card"><h2>'.icon('star').' Pupils</h2><p class="muted">Pupils keep the same pages and names. Their menu now has yellow group words: <b>Learn</b>, <b>My progress</b> and <b>About me</b>. On the phone, pupils keep their bottom bar. A new pupil with no starting level takes the <b>reading check</b> at first sign-in.</p></section>';
 echo '<div class="wm-cols">'.($u['role']==='admin'?$a.$t:$t.$a).'</div>'.$p;
 if($u['role']==='teacher')echo '<section class="card" id="help"><h2>'.HELP_HAND.' Asked for help</h2><p class="muted">When a pupil taps “I need help” in a lesson, at school or at home, it shows on Home under <b>Needs you today</b> and in the bell. Tap <b>See pupil</b>, help them, then <b>Mark as helped</b>.</p></section>';
}

/** One-time notes and small menu actions (closing the "new order" note). */
function staff_action(string $action):void{
 $u=current_user();if(!$u)fail('Please sign in again.',401);
 if($action==='moved_seen'){staff_mark_seen((int)$u['id'],'moved');go('?page=dashboard');}
}

/* ---------------- teacher: My pupils · Pupil list (one list for everything) ---------------- */
function pupil_list_view(array $u):void{
 $tid=(int)$u['id'];if(isset($_GET['id']))go('?page=pupil&id='.(int)$_GET['id'].'#learning-path');
 $cards=cards_ready();
 $pupils=rows('SELECT u.id,u.name,u.public_id,u.avatar_path,u.active,COALESCE(s.grade_level,p.grade_level) grade_level,s.name section,ps.section_id,a.level_id,(SELECT COUNT(*) FROM pupil_progress pp WHERE pp.pupil_id=u.id AND pp.completed_at IS NOT NULL) done,(SELECT MAX(d.day) FROM learning_days d WHERE d.pupil_id=u.id) last_day'.($cards?',(SELECT c.starter_enc IS NOT NULL FROM pupil_cards c WHERE c.user_id=u.id) starter':',NULL starter').' FROM teacher_pupils tp JOIN users u ON u.id=tp.pupil_id JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN sections s ON s.id=ps.section_id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE tp.teacher_id=? ORDER BY p.grade_level,s.name,u.name',[$tid]);
 echo pupils_tabs('manage',count($pupils));
 if(!$pupils){echo '<section class="empty">'.icon('people').'<h2>No pupils yet.</h2><p>Add one pupil, or a whole class from an Excel list.</p><a class="btn primary" href="?page=accounts">'.icon('useredit').'Add pupils</a></section>';return;}
 $groups=[];foreach($pupils as $p)$groups[$p['section']?'Grade '.$p['grade_level'].' · '.$p['section']:'No section']['rows'][]=$p;
 $secs=teacher_sections($tid);$levels=rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id');
 echo '<section class="card mp-list"><div class="mp-bar"><select aria-label="Show section" data-mp-section><option value="">All sections</option>';foreach(array_keys($groups) as $g)echo '<option>'.e($g).'</option>';echo '</select><input type="search" placeholder="Search a name or Pupil ID" aria-label="Search pupils" data-mp-search></div>';
 echo '<form method="post" class="mp-form">'.csrf_field().'<input type="hidden" name="back" value="?page=manage">';
 echo '<div class="mp-bulk" role="group" aria-label="With the ticked pupils"><span>With the ticked pupils:</span>';
 echo '<label class="mp-bsel"><span class="sr-only">Starting level</span><select name="level_id">';foreach($levels as $l)echo '<option value="'.(int)$l['id'].'">'.e(level_label((int)$l['id']).' · '.(function_exists('rc_level_name')?rc_level_name((int)$l['id']):'')).'</option>';echo '</select></label><button class="btn secondary" name="action" value="set_start_level" data-confirm="Change the starting level for the ticked pupils?">'.icon('ladder').'Change starting level</button>';
 if(count($secs)>1){echo '<label class="mp-bsel"><span class="sr-only">Section</span><select name="section_id">';foreach($secs as $s)echo '<option value="'.(int)$s['id'].'">Grade '.(int)$s['grade_level'].' · '.e($s['name']).'</option>';echo '</select></label><button class="btn secondary" name="action" value="move_section" data-confirm="Move the ticked pupils to this section?">'.icon('move').'Move to a section</button>';}
 echo '<button class="btn secondary" formmethod="get" formaction="index.php" name="page" value="cards">'.icon('print').'Print sign-in tickets</button></div>';
 echo '<div class="tablewrap"><table class="mp-tbl"><thead><tr><th class="mp-c"><span class="sr-only">Tick</span></th><th>Pupil</th><th>Section</th><th>Level now</th><th>Last learned</th><th><span class="sr-only">Open</span></th></tr></thead>';
 foreach($groups as $g=>$x){echo '<tbody data-mp-group="'.e($g).'"><tr class="mp-gh"><th colspan="6"><span>'.e($g).' · '.count($x['rows']).'</span><button type="button" class="linkbutton" data-mp-all>Select all</button></th></tr>';
  foreach($x['rows'] as $p){$id=(int)$p['id'];$lv=(int)$p['level_id'];
   echo '<tr class="mp-item'.($p['active']?'':' off').'" data-mp-name="'.e(mb_strtolower($p['name'].' '.$p['public_id'])).'"><td class="mp-c"><input type="checkbox" name="pupils[]" value="'.$id.'" aria-label="Tick '.e($p['name']).'"></td>'
    .'<td><a class="mp-who" href="?page=pupil&amp;id='.$id.'">'.avatar($p).'<span>'.e($p['name']).'<small>'.e($p['public_id']).($p['starter']!==null?' · <span class="mp-pill'.($p['starter']?' y':'').'">'.($p['starter']?'Starter password':'Own password').'</span>':'').(!$p['active']?' · <span class="mp-pill n">Turned off</span>':'').'</small></span></a></td>'
    .'<td>'.e($p['section']?:'—').'</td><td>'.($lv?'<b>'.e(level_label($lv)).'</b><small class="mp-sm">'.(int)$p['done'].' lessons done</small>':'<a class="mp-pill y" href="?page=rcheck_pupil&amp;id='.$id.'">Reading check</a>').'</td><td>'.e($p['last_day']?date('M j',strtotime((string)$p['last_day'])):'Not yet').'</td>'
    .'<td><div class="mp-act"><a class="btn secondary small" href="?page=pupil&amp;id='.$id.'">'.icon('eye').'Open</a><a class="btn quiet small" href="?page=pupil&amp;id='.$id.'&amp;tab=account">'.icon('edit').'Edit</a></div></td></tr>';}
  echo '</tbody>';}
 echo '</table></div><p class="mp-empty muted" data-mp-empty hidden>No pupils match your search.</p></form></section>';
}
/** Move the ticked pupils to another of the teacher's sections. */
function pupils_move_section(array $u):void{
 $tid=(int)$u['id'];$s=owned_section((int)($_POST['section_id']??0),$tid);$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['pupils']??[])))));
 if(!$ids){flash('Tick at least one pupil first.');go('?page=manage');}
 foreach($ids as $pid)if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]))fail('This pupil is not assigned to you.',403);
 db()->beginTransaction();try{foreach($ids as $pid){q('UPDATE pupils SET grade_level=?,section=? WHERE user_id=?',[$s['grade_level'],$s['name'],$pid]);q('INSERT INTO pupil_sections VALUES(?,?) ON DUPLICATE KEY UPDATE section_id=VALUES(section_id)',[$pid,$s['id']]);}audit('move_section',count($ids).' pupils → Grade '.$s['grade_level'].' '.$s['name']);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 flash(count($ids).' '.(count($ids)===1?'pupil is':'pupils are').' now in Grade '.$s['grade_level'].' · '.$s['name'].'.');go('?page=manage');
}

/* ---------------- teacher: one pupil, in tabs ---------------- */
function pupil_page_view(array $u):void{
 $pid=(int)($_GET['id']??0);own_pupil($pid);$p=one('SELECT u.*,p.grade_level,p.section,ps.section_id,a.level_id,pd.sex,pd.lrn FROM users u JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id LEFT JOIN pupil_details pd ON pd.pupil_id=u.id WHERE u.id=?',[$pid]);if(!$p)fail('Pupil not found.',404);
 $tab=(string)($_GET['tab']??'path');$tabs=['path'=>['ladder','Learning path'],'answers'=>['check','Answers and scores'],'rcheck'=>['chart','Reading check'],'badges'=>['star','Badges'],'account'=>['useredit','Account']];if(!isset($tabs[$tab]))$tab='path';
 $s=progress_stats($pid);$first=explode(' ',trim((string)$p['name']))[0];$lv=(int)$p['level_id'];
 $empty=fn(string $ic,string $title,string $text)=>'<div class="pd-empty"><span class="pd-ic">'.icon($ic).'</span><div><strong>'.e($title).'</strong><span>'.e($text).'</span></div></div>';
 echo '<p class="crumb"><a href="?page=manage">My pupils</a>'.icon('chev').'<span>'.e($p['name']).'</span></p>';
 echo hm_head('PUPIL · '.e($p['public_id']),(string)$p['name'],e(rl_pupil_line($pid)).' · '.$s['xp'].' XP · '.$s['completed'].' lessons complete'.($p['section']?' · Grade '.(int)$p['grade_level'].' · '.e($p['section']):''),'<a class="btn secondary" href="?page=report_card&amp;id='.$pid.'">'.icon('print').'Report card</a><a class="btn secondary" href="?page=report&amp;id='.$pid.'">'.icon('print').'Progress report</a><a class="btn secondary" href="?page=cards&amp;pupils[]='.$pid.'">'.icon('print').'Sign-in ticket</a><a class="btn quiet" href="?page=pupil&amp;id='.$pid.'&amp;tab=account">'.icon('edit').'Edit account</a>');
 echo '<nav class="pupil-tabs pp-tabs" aria-label="'.e($p['name']).'">';foreach($tabs as $k=>[$ic,$label])echo '<a class="pt-tab'.($tab===$k?' on':'').'" href="?page=pupil&amp;id='.$pid.($k==='path'?'':'&amp;tab='.$k).'"'.($tab===$k?' aria-current="page"':'').'>'.icon($ic).$label.'</a>';echo '</nav>';
 if($tab==='path'){
  echo (function_exists('schedule_pupil_note')?schedule_pupil_note($pid):'');
  $levels=rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id');
  echo '<section class="card mp-start"><div class="section-heading"><h2>'.icon('ladder').' Starting level</h2>'.($lv?'<span class="status">'.e(level_label($lv)).'</span>':'<span class="status status-warn">Reading check</span>').'</div><p class="muted">Levels before the starting level become free practice. The pupil can begin right away at the level you choose.'.(!$lv?' '.e($first).' takes the reading check at the next sign-in, or choose a level now.':'').'</p><form method="post" class="mp-startform">'.csrf_field().'<input type="hidden" name="action" value="set_start_level"><input type="hidden" name="pupil" value="'.$pid.'"><input type="hidden" name="back" value="?page=pupil&amp;id='.$pid.'"><label>Starting level<select name="level_id">';
  foreach($levels as $l)echo '<option value="'.(int)$l['id'].'"'.((int)$l['id']===$lv?' selected':'').'>'.e(level_label((int)$l['id']).' · '.(function_exists('rc_level_name')?rc_level_name((int)$l['id']):'')).'</option>';
  echo '</select></label><button class="btn primary">'.icon('check').'Save starting level</button></form></section>';
  if(function_exists('pt_teacher_rows'))echo pt_teacher_rows($pid);
  echo class_done_path($pid);return;}
 if($tab==='answers'){
  $att=rows('SELECT aa.*,a.title FROM assessment_attempts aa JOIN assessments a ON a.id=aa.assessment_id WHERE aa.pupil_id=? ORDER BY a.lesson_id,a.id',[$pid]);
  echo '<section class="card"><div class="section-heading"><h2>Assessment results</h2></div>';
  if($att){echo '<div class="tablewrap"><table><thead><tr><th>Assessment</th><th>Status</th><th>Score</th><th>Teacher feedback</th></tr></thead><tbody>';foreach($att as $a)echo '<tr><td>'.e($a['title']).'</td><td>'.e($a['status']).'</td><td>'.($a['score']!==null?e($a['score'].' / '.$a['max_score']):'Observation-based').'</td><td>'.e($a['feedback']).'</td></tr>';echo '</tbody></table></div>';}
  else echo $empty('chart','No assessment results yet','Results show here after '.$first.' finishes a pre-test, post-test or level assessment.');
  echo '</section>';
  echo '<section class="card ans-card"><div class="section-heading"><div><h2>Answers and scores</h2><p class="muted">BULIG checks every answer with the module answer keys. Spoken, drawn and written answers count as done when '.e($first).' answers. Open one to listen, look, change the score or write a note.</p></div></div>';
  review_view($u,$pid);echo '</section>';
  $reads=rows('SELECT r.*,a.title FROM reading_assessments r JOIN activities a ON a.id=r.activity_id WHERE r.pupil_id=? ORDER BY r.id DESC LIMIT 20',[$pid]);
  echo '<section class="card"><h2>Reading word matches</h2>';
  if($reads){echo '<p class="muted">Recognized-text similarity is not a pronunciation score.</p>';foreach($reads as $r)echo '<details><summary>'.e($r['title'].' · '.$r['word_match_percent'].'%').'</summary><p>'.e($r['transcript']).'</p></details>';}
  else echo $empty('sound','No reading recordings yet','Word matches appear when '.$first.' reads aloud with Speak Answer. They count recognized words, not pronunciation.');
  echo '</section>';return;}
 if($tab==='rcheck'){
  $row=function_exists('rc_row')?rc_row($pid):null;
  echo '<section class="card"><h2>'.icon('chart').' Reading check</h2>';
  if(!$row)echo $empty('ladder',$lv?'No reading check':'Waiting for the reading check',$lv?'You chose '.$first.'’s starting level yourself.':$first.' takes the reading check the next time they sign in.');
  else{$st=rc_state($row);[$c,$t]=rc_status($row,$lv);echo '<p><span class="rk-chip '.$c.'">'.e($t).'</span> '.rc_chips($st).'</p>'.($row['bulig_level']?'<p class="muted">BULIG placed '.e($first).' at '.e(level_label((int)$row['bulig_level'])).'. '.e(rc_why($st,false)).'</p>':'');}
  echo '<a class="btn primary" href="?page=rcheck_pupil&amp;id='.$pid.'">'.icon('eye').'Open the reading check</a></section>';return;}
 if($tab==='badges'){
  $badges=rows('SELECT b.title FROM pupil_badges p JOIN badges b ON b.id=p.badge_id WHERE p.pupil_id=?',[$pid]);
  echo '<section class="card"><h2>Earned badges</h2>';
  if($badges){echo '<div class="pd-badges">';foreach($badges as $b)echo '<span class="status">'.icon('star').e($b['title']).'</span>';echo '</div>';}
  else echo $empty('star','No badges yet','The first badge comes after '.$first.' finishes a first activity.');
  echo '</section>';return;}
 /* account */
 $p['level_id']=$p['level_id']??(function_exists('rc_ready')&&rc_ready()?0:1);
 echo '<section class="card"><h2>'.icon('useredit').' Account</h2><form method="post" class="formgrid">'.csrf_field().'<input type="hidden" name="action" value="update_account"><input type="hidden" name="id" value="'.$pid.'"><label>Full name<input name="name" value="'.e($p['name']).'" required maxlength="150"></label><label>New password (optional)<input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></label>'.grade_fields($p,teacher_sections((int)$u['id'])).'<label class="checkbox"><input type="checkbox" name="active" '.($p['active']?'checked':'').'>Account active</label><div class="formend"><small>Pupil ID: '.e($p['public_id']).'</small><button class="btn primary" type="submit">'.icon('check').'Save changes</button></div></form></section>';
 if(cards_ready())echo '<section class="card"><h2>'.icon('key').' Sign-in ticket and starter password</h2><p class="muted">Forgot the password? Make a new ticket: BULIG makes a new starter password and a new QR code. The old QR code stops working.</p><div class="buttonrow"><a class="btn secondary" href="?page=cards&amp;pupils[]='.$pid.'">'.icon('print').'Print sign-in ticket</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="card_new"><input type="hidden" name="id" value="'.$pid.'"><input type="hidden" name="back" value="?page=pupil&amp;id='.$pid.'&amp;tab=account"><button class="btn primary" data-confirm="Make a new ticket and starter password for '.e($p['name']).'?">'.icon('key').'New starter password</button></form></div></section>';
 echo '<section class="card del-card"><h2>'.icon('alert').' Delete this pupil</h2><p class="muted">Only for a pupil added by mistake, or added twice. A pupil who moved away can be turned off instead (Account active, above): their work stays, and you can turn them on again.</p>'.delete_box('delete_pupil',$pid,$p['name'],'pupil').'</section>';
}

/** Class at a glance (class activity, reading tree, milestones): at the end of Class results since v161. */
function class_glance(array $u):void{
 $tid=(int)$u['id'];$pupils=rows('SELECT u.*,p.grade_level,p.section,a.level_id FROM users u JOIN pupils p ON p.user_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1 ORDER BY u.name',[$tid]);
 if(!$pupils)return;$ids=array_map(fn($p)=>(int)$p['id'],$pupils);$stats=progress_stats_many($ids);
 $badgeWeek=array_flip(array_map('intval',array_column(rows('SELECT DISTINCT pupil_id FROM pupil_badges WHERE pupil_id IN('.tf_in($ids).') AND earned_at>=?',[date('Y-m-d H:i:s',strtotime('-7 days'))]),'pupil_id')));
 echo tf_defs().'<p class="hm-sub" id="glance">Class at a glance</p>'.tf_chart($ids).'<div class="tf-grid">'.tf_tree($pupils,$stats,$badgeWeek).tf_feed($ids).'</div>';
}

/** Once after each sign-in, on a phone: Kai the Messenger reminds teachers and administrators that BULIG is easier
    on a laptop or computer (v174). The browser shows it only on small touch screens (assets/staff.js). */
function staff_pc_tip(array $u):string{
 if(!empty($_SESSION['pc_tip']))return '';$_SESSION['pc_tip']=1;$first=explode(' ',trim((string)$u['name']))[0];
 return '<template id="pc-tip" data-img="'.e(friend_img('kai','letter')).'" data-hi="'.e(($u['role']==='admin'?'Hi, ':'Hi, Teacher ').$first.'!').'"></template>';
}

