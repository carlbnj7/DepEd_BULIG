<?php
/* v216 More for pupils, teachers and families (no new tables):
   - Class Star Race: the sections of the same grade in the same school, by the stars their pupils earned this week.
   - Zion's weekly mission: earn 10 stars this week for +30 XP (given once a week).
   - Reading Buddy: the pupil hears their first and their latest Level 4 reading (their own recordings).
   - Needs help (Teacher Home): below 2 stars on several activities, a post-test below 80%, or much slower than usual.
   - Answering hours: the administrator sets when pupils may answer. Outside them, lessons stay open to look at.
   - Parent progress card: a printable card per pupil, with a QR code to a read-only page the teacher can turn off.
   A star this week = the stars of a lesson activity (3 at 95%, 2 at 80%, 1 at 50%) answered this week. */

const QX_MISSION=10;const QX_MISSION_XP=30;
function qx_week():string{return date('Y-m-d 00:00:00',strtotime('monday this week'));}
function qx_star_sql():string{return "CASE WHEN c.max_score>0 AND c.score/c.max_score>=0.95 THEN 3 WHEN c.max_score>0 AND c.score/c.max_score>=0.8 THEN 2 WHEN c.max_score>0 AND c.score/c.max_score>=0.5 THEN 1 ELSE 0 END";}
function qx_week_stars(int $pid):int{
 return (int)val("SELECT COALESCE(SUM(".qx_star_sql()."),0) FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id=? AND a.phase='learn' AND c.status IN ('completed','approved') AND c.submitted_at>=?",[$pid,qx_week()]);
}
function qx_section(int $pid):?array{return one('SELECT s.* FROM sections s JOIN pupil_sections ps ON ps.section_id=s.id WHERE ps.pupil_id=?'.(function_exists('sections_archive_supported')&&sections_archive_supported()?' AND s.archived_at IS NULL':'').' LIMIT 1',[$pid]);}
function qx_jeepy():string{return 'assets/images/characters/jeepy-boy-driving.webp';}

/* ---------- Class Star Race ---------- */
function star_race_card(int $pid):string{
 $me=qx_section($pid);if(!$me)return '';
 $school=(int)val('SELECT u.school_id FROM users u WHERE u.id=?',[(int)$me['teacher_id']]);
 $secs=rows('SELECT s.id,s.name,s.grade_level FROM sections s JOIN users t ON t.id=s.teacher_id WHERE s.grade_level=?'.($school?' AND t.school_id='.$school:' AND s.teacher_id='.(int)$me['teacher_id']).(sections_archive_supported()?' AND s.archived_at IS NULL':'').' ORDER BY s.name LIMIT 8',[(int)$me['grade_level']]);
 if(!$secs)return '';$ids=array_map(fn($s)=>(int)$s['id'],$secs);
 $sum=[];foreach(rows("SELECT ps.section_id sid,COALESCE(SUM(".qx_star_sql()."),0) n FROM pupil_sections ps JOIN users u ON u.id=ps.pupil_id AND u.active=1 JOIN activity_completion c ON c.pupil_id=ps.pupil_id JOIN activities a ON a.id=c.activity_id AND a.phase='learn' WHERE ps.section_id IN (".implode(',',$ids).") AND c.status IN ('completed','approved') AND c.submitted_at>=? GROUP BY ps.section_id",[qx_week()]) as $r)$sum[(int)$r['sid']]=(int)$r['n'];
 $max=max(1,max($sum?:[0]));$mine=qx_week_stars($pid);
 usort($secs,fn($a,$b)=>($sum[(int)$b['id']]??0)<=>($sum[(int)$a['id']]??0));
 $h='<section class="card qx-card qx-race" aria-label="Class Star Race"><div class="qx-hd"><div><span class="eyebrow">THIS WEEK · CLASS STAR RACE</span><h2>Which section earns the most stars?</h2><p class="muted">Every star you earn this week moves your section’s Jeepy. A new race starts every Monday.</p></div></div><div class="qx-lanes">';
 foreach($secs as $s){$n=$sum[(int)$s['id']]??0;$you=(int)$s['id']===(int)$me['id'];$p=(int)(round($n/$max*18)*5);
  $h.='<div class="qx-lane'.($you?' you':'').'"><span class="qx-ln">'.e('Grade '.(int)$s['grade_level'].' · '.$s['name']).($you?' <em>(you)</em>':'').'</span><span class="qx-track"><img class="qx-car p'.$p.'" src="'.e(qx_jeepy()).'" alt="" width="54" height="42"></span><span class="qx-n">'.icon('star').number_format($n).'</span></div>';}
 return $h.'</div><div class="qx-chips"><span class="o">You added '.$mine.' '.($mine===1?'star':'stars').' this week</span>'.(count($secs)<2?'<span>Only your section is racing for now</span>':'').'</div></section>';
}

/* ---------- Zion's weekly mission ---------- */
function zion_mission_card(int $pid):string{
 $n=qx_week_stars($pid);$wk=date('o-\WW');$got=(bool)val("SELECT 1 FROM audit_log WHERE actor_id=? AND action='zion_mission' AND details=? LIMIT 1",[$pid,$wk]);
 if(!$got&&$n>=QX_MISSION){q('INSERT INTO pupil_xp(pupil_id,total) VALUES(?,?) ON DUPLICATE KEY UPDATE total=total+VALUES(total)',[$pid,QX_MISSION_XP]);q('INSERT INTO audit_log(actor_id,action,details) VALUES(?,?,?)',[$pid,'zion_mission',$wk]);$got=true;$fresh=true;}
 $left=max(0,(int)ceil((strtotime('monday next week')-time())/86400));$pc=min(100,(int)(round(min($n,QX_MISSION)/QX_MISSION*20)*5));
 return '<section class="card qx-card qx-mission" aria-label="Zion’s weekly mission"><img class="qx-ch" src="'.e(friend_img('zion',$got?'cheer':'point')).'" alt="" width="90" height="90"><div class="qx-mt"><span class="eyebrow">ZION THE STAR KEEPER · WEEKLY MISSION</span><h2>'.($got?'Mission done! +'.QX_MISSION_XP.' XP':'Earn '.QX_MISSION.' stars this week!').'</h2>'
  .'<p class="qx-mp"><b>'.min($n,QX_MISSION).' of '.QX_MISSION.' stars</b> · '.($got?'a new mission starts on Monday':$left.' '.($left===1?'day':'days').' left').'</p><div class="qx-bar" role="progressbar" aria-valuemin="0" aria-valuemax="'.QX_MISSION.'" aria-valuenow="'.min($n,QX_MISSION).'"><i class="p'.$pc.'"></i></div>'
  .'<p class="muted qx-tip">'.($got?(!empty($fresh)?'Zion added '.QX_MISSION_XP.' XP to your total. Great work!':'You got the reward this week. Keep reading!'):'Tip: Play again an activity with 1 or 2 stars to get more stars.').'</p></div></section>';
}

/* ---------- Reading Buddy ---------- */
function reading_buddy_card(int $pid):string{
 $r=rows("SELECT c.id,c.submitted_at,c.rubric_scores,a.title FROM activity_completion c JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE c.pupil_id=? AND m.level_id=5 AND c.audio_path IS NOT NULL AND c.audio_path<>'' AND c.status IN ('completed','approved') ORDER BY c.submitted_at,c.id",[$pid]);
 if(!$r)return '';$pick=count($r)>1?[$r[0],$r[count($r)-1]]:[$r[0]];
 $row=function($c,$label,$old){$s=function_exists('l4_saved_score')?l4_saved_score($c):null;$t=strtotime((string)$c['submitted_at']);
  return '<div class="qx-play'.($old?' old':'').'"><span class="qx-pl"><b>'.e($label).'</b><small>'.e($c['title']).' · '.e($t?date('M j',$t):'').(!empty($s['wpm'])?' · '.(int)round((float)$s['wpm']).' words a minute':'').'</small></span><audio controls preload="none" src="?page=recording&amp;id='.(int)$c['id'].'"></audio></div>';};
 $h='<section class="card qx-card qx-buddy" aria-label="Reading Buddy"><img class="qx-ch" src="'.e(friend_img('max','mic')).'" alt="" width="90" height="90"><div class="qx-mt"><span class="eyebrow">MAX THE READING BUDDY</span><h2>'.(count($pick)>1?'Listen to how you grew!':'Listen to your reading').'</h2>';
 $h.=$row($pick[0],count($pick)>1?'First reading':'Your reading',count($pick)>1);if(count($pick)>1)$h.=$row($pick[1],'Latest reading',false);
 return $h.'<p class="muted qx-tip">Only you and your teacher can hear these.</p></div></section>';
}
function pupil_quest_cards(int $pid):string{
 $a=star_race_card($pid);$b=zion_mission_card($pid);
 return '<div class="qx-row'.($a===''?' one':'').'">'.$b.$a.'</div>'.reading_buddy_card($pid);
}

/* ---------- Needs help (Teacher Home) ---------- */
function needs_help_rows(int $tid):array{
 $out=[];$since=date('Y-m-d H:i:s',strtotime('-14 days'));
 $P=rows('SELECT u.id,u.name FROM users u JOIN teacher_pupils t ON t.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1 ORDER BY u.name',[$tid]);if(!$P)return [];
 $ids=implode(',',array_map(fn($p)=>(int)$p['id'],$P));
 $low=[];foreach(rows("SELECT c.pupil_id,COUNT(*) n FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE c.pupil_id IN ($ids) AND a.phase='learn' AND c.status='retry' AND c.submitted_at>=? GROUP BY c.pupil_id",[$since]) as $r)$low[(int)$r['pupil_id']]=(int)$r['n'];
 $slow=[];if(function_exists('rx_ready')&&rx_ready())foreach(rows("SELECT c.pupil_id,c.activity_id,c.time_secs FROM activity_completion c WHERE c.pupil_id IN ($ids) AND c.time_secs>=60 AND c.submitted_at>=?",[$since]) as $r){$avg=rx_class_avg((int)$r['activity_id']);if($avg&&(int)$r['time_secs']>2*$avg)$slow[(int)$r['pupil_id']]=($slow[(int)$r['pupil_id']]??0)+1;}
 foreach($P as $p){$pid=(int)$p['id'];$why=[];
  if(($low[$pid]??0)>=2)$why[]=['r','Below 2 stars on '.$low[$pid].' activities','Read those activities together'];
  $nx=next_learning_lesson($pid);$L=$nx?(int)$nx['level_id']:0;if($L&&function_exists('pt_result')){$pt=pt_result($pid,$L);if($pt['n']&&$pt['done']>=$pt['n']&&$pt['pct']<PT_PASS)$why[]=['r','Post-test '.$pt['pct'].'% (below '.PT_PASS.'%)','The missed activities are open again'];}
  if(($slow[$pid]??0)>=2)$why[]=['o','Roxanne: much slower than the class on '.$slow[$pid].' activities','Check if the words are too hard'];
  if($why)$out[]=['id'=>$pid,'name'=>(string)$p['name'],'why'=>$why];}
 return $out;
}
function needs_help_card(int $tid):string{
 $R=needs_help_rows($tid);if(!$R)return '';
 $h='<section class="card qx-card qx-help" id="qx-help"><div class="ws-hd"><h2>'.icon('hand').count($R).' '.(count($R)===1?'pupil may need you':'pupils may need you').'</h2><span class="rs-sub">From the last 14 days</span></div><div class="qx-hl">';
 foreach(array_slice($R,0,12) as $r){$h.='<a class="qx-hrow" href="?page=pupil&amp;id='.$r['id'].'&amp;tab=answers"><b>'.e($r['name']).'</b><span class="qx-chips">';foreach($r['why'] as $w)$h.='<span class="'.$w[0].'">'.e($w[1]).'</span>';$h.='</span><small>'.e($r['why'][0][2]).'</small>'.icon('chev').'</a>';}
 return $h.'</div></section>';
}

/* ---------- Answering hours ---------- */
const QX_DAYS=['weekdays'=>'Monday to Friday','monsat'=>'Monday to Saturday','all'=>'Every day'];
function answer_hours():array{static $c=null;if($c!==null)return $c;
 $g=fn($k,$d)=>function_exists('admin_setting')?admin_setting($k,$d):$d;
 $d=$g('ans_days','weekdays');$f=$g('ans_from','07:00');$t=$g('ans_to','20:00');
 return $c=['on'=>$g('ans_on','0')==='1','days'=>isset(QX_DAYS[$d])?$d:'weekdays','from'=>preg_match('/^\d{2}:\d{2}$/',$f)?$f:'07:00','to'=>preg_match('/^\d{2}:\d{2}$/',$t)?$t:'20:00'];
}
/** True when pupils may answer at this moment (or at $when, for answers made offline). */
function answering_open(?int $when=null):bool{
 $h=answer_hours();if(!$h['on'])return true;$w=$when??time();$dow=(int)date('N',$w);
 if($h['days']==='weekdays'&&$dow>5)return false;if($h['days']==='monsat'&&$dow>6)return false;
 $m=date('H:i',$w);return $h['from']<=$h['to']?($m>=$h['from']&&$m<$h['to']):($m>=$h['from']||$m<$h['to']);
}
function answer_hours_text():string{$h=answer_hours();$f=fn($x)=>date('g:i A',strtotime('2000-01-01 '.$x));return QX_DAYS[$h['days']].', '.$f($h['from']).' to '.$f($h['to']);}
function answer_hours_next():string{
 $t=(int)(ceil(time()/60)*60);for($i=0;$i<8*1440;$i++){$t2=$t+$i*60;if(answering_open($t2))return date(date('Y-m-d',$t2)===date('Y-m-d')?'g:i A':'l, g:i A',$t2);}return '';
}
/** Roxanne's "not yet" sheet on an activity page outside the hours. */
function answer_hours_sheet():string{
 if(answering_open())return '';$n=answer_hours_next();
 return '<div class="qx-ov" role="dialog" aria-modal="true" aria-labelledby="qx-ov-t" data-qx-ov><div class="qx-ovp"><img src="assets/images/characters/roxanne-time.webp?v=2" alt="" width="140" height="140"><h2 id="qx-ov-t">Tick, tock! Not yet.</h2><p>Answering opens '.($n!==''?'at <b>'.e($n).'</b>':'later').'. You can still look at your lessons now.</p><p class="muted">'.e(answer_hours_text()).'</p><a class="btn primary" href="?page=lessons">Back to my lessons</a><button type="button" class="btn quiet" data-qx-ov-close>Just look</button></div></div>';
}
function answer_hours_admin():void{
 $h=answer_hours();
 echo '<section class="card qx-hours" id="answer-hours"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="answer_hours_save"><div class="sch-head"><div><h2>'.icon('clock').' Answering hours</h2><p class="muted">Choose when pupils may answer activities. Outside these hours they can still open and look at their lessons. Roxanne the Timekeeper tells them when answering opens.</p></div><label class="sch-switch"><input type="checkbox" name="on" value="1"'.($h['on']?' checked':'').'><i aria-hidden="true"></i><span>Hours on</span></label></div>'
  .'<div class="qx-hgrid"><label>Days<select name="days">';foreach(QX_DAYS as $k=>$v)echo '<option value="'.$k.'"'.($h['days']===$k?' selected':'').'>'.e($v).'</option>';
 echo '</select></label><label>From<input type="time" name="from" value="'.e($h['from']).'" required></label><label>Until<input type="time" name="to" value="'.e($h['to']).'" required></label></div><p class="muted">Work a pupil made on a phone without internet is always kept and uploads later.</p><button class="btn primary">'.icon('check').'Save answering hours</button></form></section>';
}

/* ---------- Parent progress card ---------- */
function parent_secret():string{$s=admin_setting('parent_secret','');if(strlen($s)<32){$s=bin2hex(random_bytes(24));admin_set('parent_secret',$s);}return $s;}
function parent_token(int $pid):string{return $pid.'-'.substr(hash_hmac('sha256','parent|'.$pid,parent_secret()),0,24);}
function parent_off(int $pid):bool{return in_array((string)$pid,explode(',',admin_setting('parent_off','')),true);}
function parent_url(int $pid):string{$b=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').strtok((string)($_SERVER['REQUEST_URI']??'/'),'?');return $b.'?page=family&k='.rawurlencode(parent_token($pid));}
function parent_data(int $pid):array{
 $u=one('SELECT u.name,p.grade_level,p.section FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.id=?',[$pid])??[];
 $g=function_exists('rg_pupil')?rg_pupil($pid):[];$s=function_exists('progress_stats')?progress_stats($pid):[];$jar=function_exists('star_jar')?star_jar($pid,0):['n'=>0];
 return ['name'=>(string)($u['name']??''),'grade'=>(int)($u['grade_level']??0),'section'=>(string)($u['section']??''),'now'=>(int)($g['now']??0),'start'=>(int)($g['start']??0),'stars'=>(int)($jar['n']??0),'streak'=>(int)($s['current_streak']??0),'lessons'=>(int)($s['completed']??0),'pre'=>$g['pre']??null,'post'=>$g['post']??null,'wpm0'=>$g['wpm0']??null,'wpm1'=>$g['wpm1']??null];
}
function parent_card_html(int $pid,bool $qr):string{
 $d=parent_data($pid);$first=explode(' ',trim($d['name']))[0];$n=fn($v,$u='')=>$v===null?'—':rtrim(rtrim(number_format((float)$v,1),'0'),'.').$u;
 $tile=fn($b,$s)=>'<div><b>'.$b.'</b><small>'.e($s).'</small></div>';
 $h='<article class="fc-card"><header><div><h2>'.e($d['name']).'’s progress</h2><p>Grade '.$d['grade'].($d['section']!==''?' · '.e($d['section']):'').' · for the family · '.date('F j, Y').'</p></div><img src="assets/brand/bulig-quest.webp" alt="BULIG Quest" width="120" height="50"></header><div class="fc-g">'
  .$tile($d['now']?e(level_label($d['now'])):'—',$d['start']?'Reading now · started at '.str_replace('Level ','',level_label($d['start'])):'Reading now')
  .$tile(icon('star').number_format($d['stars']),'Stars in the Star Jar').$tile(e($d['streak'].' '.($d['streak']===1?'day':'days')),'Learning streak')
  .$tile($d['pre']===null?'—':e($n($d['pre'],'%').' → '.$n($d['post'],'%')),'Pre-test → post-test').$tile($d['wpm0']===null?'—':e($n($d['wpm0']).' → '.$n($d['wpm1'])),'Words read per minute').$tile(e((string)$d['lessons']),'Lessons finished').'</div>';
 $h.='<footer>'.($qr&&!parent_off($pid)?'<span class="fc-qr" data-qr="'.e(parent_url($pid)).'" aria-label="QR code"></span>':'').'<p>'.($qr&&!parent_off($pid)?'Scan to see '.e($first).'’s progress any time (read only, no password). ':'').'<b>How to help at home:</b> read one short story together for 10 minutes a day, and let '.e($first).' read to you.</p></footer></article>';
 return $h;
}
function parent_cards_view(array $u):void{
 $tid=(int)$u['id'];$sid=(int)($_GET['section']??0);$pid=(int)($_GET['id']??0);$gid=(int)($_GET['group']??0);
 if($pid){if(!val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]))fail('Pupil not found.',404);$ids=[$pid];$title=(string)val('SELECT name FROM users WHERE id=?',[$pid]);}
 elseif($gid&&function_exists('ct_ready')&&ct_ready()){$g=one('SELECT * FROM pupil_groups WHERE id=? AND teacher_id=?',[$gid,$tid]);if(!$g)fail('Group not found.',404);$ids=array_map('intval',array_column(rows('SELECT u.id FROM pupil_group_members m JOIN users u ON u.id=m.pupil_id AND u.active=1 JOIN teacher_pupils t ON t.pupil_id=u.id AND t.teacher_id=? WHERE m.group_id=? ORDER BY u.name',[$tid,$gid]),'id'));$title=(string)$g['name'];}
 else{$sec=owned_section($sid,$tid);$ids=array_map('intval',array_column(rows('SELECT u.id FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id AND t.teacher_id=? WHERE ps.section_id=? AND u.active=1 ORDER BY u.name',[$tid,(int)$sec['id']]),'id'));$title='Grade '.(int)$sec['grade_level'].' – '.$sec['name'];}
 echo '<div class="no-print rc-actions"><div><span class="eyebrow">PARENT PROGRESS CARDS</span><h1>'.e($title).'</h1><p class="muted">'.count($ids).' '.(count($ids)===1?'card':'cards').'. The QR code opens a read-only page for the family.</p></div><div class="rc-btns"><a class="btn secondary" href="'.($pid?'?page=pupil&amp;id='.$pid:($gid?'?page=groups':'?page=print#pc-parent')).'">'.icon('arrow','flip').'Back</a><button type="button" class="btn primary" data-print>'.icon('download').'Print or save as PDF</button></div></div>';
 if($pid){$off=parent_off($pid);echo '<form method="post" class="no-print fc-switch">'.csrf_field().'<input type="hidden" name="action" value="parent_link"><input type="hidden" name="id" value="'.$pid.'"><input type="hidden" name="off" value="'.($off?'0':'1').'"><span>'.icon($off?'lock':'eye').($off?'The family page is turned off.':'The family page is on (read only).').'</span><button class="btn secondary small">'.($off?'Turn on':'Turn off').'</button></form>';}
 echo '<div class="fc-sheet">';foreach($ids as $i)echo parent_card_html($i,true);echo '</div><script src="'.asset_url('qrcode.js').'" defer></script><script src="'.asset_url('cards.js').'" defer></script>';
}
/** The family's read-only page (no sign-in). */
function family_view():void{
 $k=(string)($_GET['k']??'');$pid=(int)strtok($k,'-');
 if(!$pid||!hash_equals(parent_token($pid),$k)||parent_off($pid)||!val("SELECT 1 FROM users WHERE id=? AND role='pupil' AND active=1",[$pid])){http_response_code(404);echo '<section class="card fc-none"><h1>This page is not available</h1><p class="muted">Ask the teacher for a new progress card.</p></section>';return;}
 echo '<div class="fc-fam">'.parent_card_html($pid,false).'</div>';
}

/* v216 Right under the pupil's name panel: "Continue where I stopped" when an activity was left half done (answers saved
   as a draft in the last 14 days), otherwise "Next up" with the next lesson (shown on phones, where it is far down). */
function continue_card(int $pid):string{
 $d=one("SELECT d.activity_id,d.updated_at,a.title,a.lesson_id,l.title ltitle,l.subtitle lsub FROM activity_drafts d JOIN activities a ON a.id=d.activity_id JOIN lessons l ON l.id=a.lesson_id LEFT JOIN activity_completion c ON c.pupil_id=d.pupil_id AND c.activity_id=d.activity_id WHERE d.pupil_id=? AND d.updated_at>=? AND (c.id IS NULL OR c.status='retry') AND (TRIM(COALESCE(d.response,''))<>'' OR TRIM(COALESCE(d.drawing,''))<>'') ORDER BY d.updated_at DESC LIMIT 1",[$pid,date('Y-m-d H:i:s',strtotime('-14 days'))]);
 if($d&&function_exists('allowed_activity')){try{allowed_activity($pid,(int)$d['activity_id']);}catch(Throwable $e){$d=null;}}
 if($d){$t=strtotime((string)$d['updated_at']);$when=$t?(date('Y-m-d',$t)===date('Y-m-d')?'today '.date('g:i A',$t):(date('Y-m-d',$t)===date('Y-m-d',strtotime('-1 day'))?'yesterday '.date('g:i A',$t):date('M j, g:i A',$t))):'';
  $lt=trim((string)$d['lsub'])!==''?(string)$d['lsub']:(string)$d['ltitle'];
  return '<section class="card nx-card nx-cont" aria-label="Continue where I stopped"><img class="nx-ch" src="'.e(friend_img('george','point')).'" alt="" width="70" height="90"><div class="nx-t"><span class="eyebrow">YOU STOPPED HERE'.($when!==''?' · '.e(mb_strtoupper($when)):'').'</span><h2>'.e($lt.' · '.$d['title']).'</h2><p class="muted">Your answers are saved. Pick up right where you left off.</p></div><a class="btn primary nx-go" href="?page=lesson&amp;id='.(int)$d['lesson_id'].'&amp;activity='.(int)$d['activity_id'].'">Continue where I stopped '.icon('arrow').'</a></section>';}
 $nx=function_exists('next_learning_lesson')?next_learning_lesson($pid):null;if(!$nx)return '';
 $t=trim((string)($nx['subtitle']??''))!==''?(string)$nx['subtitle']:(string)$nx['title'];
 return '<section class="card nx-card nx-next" aria-label="Next up"><div class="nx-t"><span class="eyebrow">NEXT UP · '.e(mb_strtoupper(level_label((int)$nx['level_id']))).'</span><h2>'.e($t).'</h2></div><a class="btn primary nx-go" href="?page=lesson&amp;id='.(int)$nx['id'].'">Start my next lesson '.icon('arrow').'</a></section>';
}

/** v216 Jarvis reminds administrators to download a backup once a month (shown after 30 days, or if there is none). */
function backup_remind_card():string{
 $u=current_user();if(!$u||($u['role']??'')!=='admin'||!empty($u['view_only']))return '';
 $b=admin_setting('last_backup_at','');$d=$b===''?null:admin_days_since($b);if($d!==null&&$d<30)return '';
 return '<section class="card qx-card qx-mission bk-remind"><img class="qx-ch" src="'.e(friend_img('jarvis','idea')).'" alt="" width="90" height="90"><div class="qx-mt"><span class="eyebrow">JARVIS · BACKUP REMINDER</span><h2>'.($d===null?'No backup downloaded yet':'Last backup: '.$d.' days ago').'</h2><p class="muted qx-tip">Download a backup once a month, and keep it on a USB drive or in Google Drive.</p><div class="buttonrow"><a class="btn primary" href="?page=print#pc-backup">'.icon('download').'Download a backup</a></div></div></section>';
}
