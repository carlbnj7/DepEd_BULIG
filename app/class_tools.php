<?php
/* v216 Class tools for teachers (needs 050_class_tools.sql; without it these parts stay hidden):
   - Week plan: the teacher picks a lesson for each school day of a section; pupils see "This week" on their Overview.
   - Reading groups: a pupil can be in several groups; message a group or print its parent cards.
   - Quick notes: short private notes on a pupil that only the teacher who wrote them sees. */

function ct_ready():bool{static $r;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'pupil_notes'");}catch(Throwable $e){$r=false;}}return $r;}
const CT_DAYS=[1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'];
function ct_week(int $off=0):string{return date('Y-m-d',strtotime('monday this week '.($off>=0?'+':'').$off.' week'));}
function ct_owned_pupil(int $tid,int $pid):bool{return (bool)val('SELECT 1 FROM teacher_pupils WHERE teacher_id=? AND pupil_id=?',[$tid,$pid]);}

/* ---------- Week plan ---------- */
function ct_plan(int $sid,string $week):array{$o=[];foreach(rows('SELECT w.day,w.lesson_id,l.title,l.subtitle,m.level_id FROM week_plans w JOIN lessons l ON l.id=w.lesson_id JOIN modules m ON m.id=l.module_id WHERE w.section_id=? AND w.week_start=? ORDER BY w.day',[$sid,$week]) as $r)$o[(int)$r['day']]=$r;return $o;}
function ct_lname(array $r):string{return level_label((int)$r['level_id']).' · '.(trim((string)($r['subtitle']??''))!==''?$r['subtitle']:$r['title']);}
function week_plan_view(array $u):void{
 $tid=(int)$u['id'];if(!ct_ready()){echo '<section class="card"><h1>Week plan</h1><p class="muted">Ask your administrator to import 050_class_tools.sql.</p></section>';return;}
 $secs=teacher_sections($tid);if(!$secs){echo hm_head('TEACHING · WEEK PLAN','Week plan','Choose the lessons for each day of the week.').'<section class="card"><p class="muted">Add a section first (My class › Sections).</p></section>';return;}
 $sid=(int)($_GET['section']??0);$sec=$secs[0];foreach($secs as $s)if((int)$s['id']===$sid)$sec=$s;$sid=(int)$sec['id'];
 $off=max(-4,min(4,(int)($_GET['w']??0)));$week=ct_week($off);$plan=ct_plan($sid,$week);
 $opts='';foreach($secs as $s)$opts.='<option value="'.(int)$s['id'].'"'.((int)$s['id']===$sid?' selected':'').'>Grade '.(int)$s['grade_level'].' · '.e($s['name']).'</option>';
 echo hm_head('TEACHING · WEEK PLAN','Week plan','Pick a lesson for each school day. Your pupils see “This week” on their Overview, and you see how many finished each lesson.');
 echo '<section class="card wp-card"><form method="get" class="rg-filter"><input type="hidden" name="page" value="week_plan"><label>Section<select name="section" data-autosubmit>'.$opts.'</select></label><input type="hidden" name="w" value="'.$off.'"></form>';
 $wk=strtotime($week);echo '<div class="wp-week"><a class="btn secondary small" href="?page=week_plan&amp;section='.$sid.'&amp;w='.($off-1).'" aria-label="Last week">'.icon('arrow','flip').'</a><b>'.e(date('F j',$wk).' – '.date('F j, Y',strtotime('+4 days',$wk))).($off===0?' · this week':'').'</b><a class="btn secondary small" href="?page=week_plan&amp;section='.$sid.'&amp;w='.($off+1).'" aria-label="Next week">'.icon('arrow').'</a></div>';
 $les=rows('SELECT l.id,l.title,l.subtitle,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.published=1 AND (m.grade_level IS NULL OR m.grade_level=?) ORDER BY '.level_order_sql('m.level_id').',l.position,l.id',[(int)$sec['grade_level']]);
 $grp=[];foreach($les as $l)$grp[(int)$l['level_id']][]=$l;
 $pids=array_map('intval',array_column(rows('SELECT ps.pupil_id FROM pupil_sections ps JOIN teacher_pupils t ON t.pupil_id=ps.pupil_id AND t.teacher_id=? JOIN users u ON u.id=ps.pupil_id AND u.active=1 WHERE ps.section_id=?',[$tid,$sid]),'pupil_id'));
 echo '<form method="post" class="wp-form">'.csrf_field().'<input type="hidden" name="action" value="week_plan_save"><input type="hidden" name="section" value="'.$sid.'"><input type="hidden" name="week" value="'.e($week).'"><input type="hidden" name="w" value="'.$off.'"><div class="tablewrap"><table class="rg-t"><thead><tr><th>Day</th><th>Lesson</th><th>Pupils done</th></tr></thead><tbody>';
 foreach(CT_DAYS as $d=>$dn){$cur=(int)($plan[$d]['lesson_id']??0);$sel='<select name="day['.$d.']" aria-label="'.e($dn).' lesson"><option value="0">No lesson</option>';
  foreach($grp as $lv=>$ls){$sel.='<optgroup label="'.e(level_label($lv)).'">';foreach($ls as $l)$sel.='<option value="'.(int)$l['id'].'"'.($cur===(int)$l['id']?' selected':'').'>'.e(trim((string)$l['subtitle'])!==''?$l['subtitle']:$l['title']).'</option>';$sel.='</optgroup>';}
  $done=$cur&&$pids?(int)val('SELECT COUNT(*) FROM pupil_progress WHERE lesson_id=? AND completed_at IS NOT NULL AND pupil_id IN ('.implode(',',$pids).')',[$cur]):0;
  echo '<tr><td><b>'.e($dn).'</b><br><small class="muted">'.e(date('M j',strtotime('+'.($d-1).' days',$wk))).'</small></td><td>'.$sel.'</select></td><td>'.($cur?'<div class="qx-chips"><span class="'.($pids&&$done>=count($pids)?'':'o').'">'.$done.' of '.count($pids).'</span></div>':'<span class="rg-na">—</span>').'</td></tr>';}
 echo '</tbody></table></div><button class="btn primary">'.icon('check').'Save the week plan</button></form></section>';
}
/** The pupil's "This week" card (only when the teacher made a plan for this week). */
function week_plan_pupil(int $pid):string{
 if(!ct_ready())return '';$sid=(int)val('SELECT section_id FROM pupil_sections WHERE pupil_id=? LIMIT 1',[$pid]);if(!$sid)return '';
 $plan=ct_plan($sid,ct_week());if(!$plan)return '';$today=(int)date('N');
 $h='<section class="card wp-pupil" aria-label="This week"><div class="ws-hd"><h2>'.icon('cal').'This week</h2><span class="rs-sub">From your teacher</span></div><div class="wp-days">';
 foreach($plan as $d=>$r){$done=(bool)val('SELECT 1 FROM pupil_progress WHERE pupil_id=? AND lesson_id=? AND completed_at IS NOT NULL',[$pid,(int)$r['lesson_id']]);$open=function_exists('level_available')?level_available($pid,(int)$r['level_id']):true;
  $h.='<a class="wp-day'.($done?' done':'').($d===$today?' today':'').'" href="'.($open?'?page=lesson&amp;id='.(int)$r['lesson_id']:'?page=lessons').'"><span class="wp-dn">'.e(substr(CT_DAYS[$d]??'',0,3)).($d===$today?' · today':'').'</span><b>'.e(trim((string)$r['subtitle'])!==''?$r['subtitle']:$r['title']).'</b><small>'.($done?icon('check').'Done':e(level_label((int)$r['level_id']))).'</small></a>';}
 return $h.'</div></section>';
}

/* ---------- Reading groups ---------- */
function groups_view(array $u):void{
 $tid=(int)$u['id'];if(!ct_ready()){echo '<section class="card"><h1>Reading groups</h1><p class="muted">Ask your administrator to import 050_class_tools.sql.</p></section>';return;}
 $pupils=rows('SELECT u.id,u.name FROM users u JOIN teacher_pupils t ON t.pupil_id=u.id WHERE t.teacher_id=? AND u.active=1 ORDER BY u.name',[$tid]);
 $groups=rows('SELECT g.*,(SELECT COUNT(*) FROM pupil_group_members m JOIN users x ON x.id=m.pupil_id AND x.active=1 WHERE m.group_id=g.id) n FROM pupil_groups g WHERE g.teacher_id=? ORDER BY g.name',[$tid]);
 $edit=(int)($_GET['edit']??0);$eg=null;foreach($groups as $g)if((int)$g['id']===$edit)$eg=$g;
 $in=$eg?array_flip(array_map('intval',array_column(rows('SELECT pupil_id FROM pupil_group_members WHERE group_id=?',[$edit]),'pupil_id'))):[];
 echo hm_head('MY CLASS · READING GROUPS','Reading groups','Put pupils in small groups, like “Frustration readers” or “Friday buddies”. A pupil can be in more than one group. Send a group a message or print their parent cards.');
 echo '<section class="card gr-form" id="gr-form"><div class="ws-hd"><h2>'.($eg?'Change '.e($eg['name']):'New group').'</h2>'.($eg?'<a class="btn quiet small" href="?page=groups">Cancel</a>':'').'</div><form method="post">'.csrf_field().'<input type="hidden" name="action" value="group_save"><input type="hidden" name="id" value="'.(int)($eg['id']??0).'"><label>Group name<input name="name" maxlength="80" required value="'.e((string)($eg['name']??'')).'" placeholder="Example: Frustration readers"></label>';
 if($pupils){echo '<fieldset class="gr-pick"><legend>Pupils in this group</legend>';foreach($pupils as $p)echo '<label><input type="checkbox" name="pupils[]" value="'.(int)$p['id'].'"'.(isset($in[(int)$p['id']])?' checked':'').'><span>'.e($p['name']).'</span></label>';echo '</fieldset>';}
 else echo '<p class="muted">Add pupils first.</p>';
 echo '<button class="btn primary">'.icon('check').($eg?'Save the group':'Make the group').'</button></form></section>';
 if(!$groups){echo '<section class="card"><p class="muted">No groups yet. Make your first group above.</p></section>';return;}
 echo '<div class="gr-list">';foreach($groups as $g){$m=rows('SELECT u.id,u.name FROM pupil_group_members m JOIN users u ON u.id=m.pupil_id AND u.active=1 WHERE m.group_id=? ORDER BY u.name',[(int)$g['id']]);
  echo '<section class="card gr-card"><div class="ws-hd"><h2>'.e($g['name']).'</h2><span class="rs-sub">'.count($m).' '.(count($m)===1?'pupil':'pupils').'</span></div><div class="qx-chips">';foreach($m as $p)echo '<span>'.e($p['name']).'</span>';if(!$m)echo '<span class="o">No pupils yet</span>';echo '</div>';
  echo '<details class="gr-msg"><summary class="btn secondary small">'.icon('comic').'Message this group</summary><form method="post">'.csrf_field().'<input type="hidden" name="action" value="group_message"><input type="hidden" name="id" value="'.(int)$g['id'].'"><label class="sr-only" for="gm'.(int)$g['id'].'">Message</label><textarea id="gm'.(int)$g['id'].'" name="body" rows="3" maxlength="2000" required placeholder="Example: Reading group, we meet on Friday after lunch."></textarea><button class="btn primary small" data-confirm="Send this message to every pupil in the group?">'.icon('arrow').'Send</button></form></details>';
  echo '<div class="buttonrow"><a class="btn secondary small" href="?page=parent_cards&amp;group='.(int)$g['id'].'">'.icon('print').'Print parent cards</a><a class="btn secondary small" href="?page=groups&amp;edit='.(int)$g['id'].'#gr-form">'.icon('edit').'Change</a><form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="group_delete"><input type="hidden" name="id" value="'.(int)$g['id'].'"><button class="btn quiet small" data-confirm="Delete this group? The pupils stay in your class.">'.icon('trash').'Delete</button></form></div></section>';}
 echo '</div>';
}

/* ---------- Quick notes ---------- */
function pupil_notes_card(int $tid,int $pid):string{
 if(!ct_ready())return '';$n=rows('SELECT id,note,created_at FROM pupil_notes WHERE teacher_id=? AND pupil_id=? ORDER BY id DESC LIMIT 30',[$tid,$pid]);
 $h='<section class="card pn-card" id="my-notes"><div class="ws-hd"><h2>'.icon('edit').'My notes</h2><span class="rs-sub">Only you see these</span></div>';
 if($n){$h.='<ul class="pn-list">';foreach($n as $x)$h.='<li><span>'.e($x['note']).'<small>'.e(date('M j, Y',strtotime((string)$x['created_at']))).'</small></span><form method="post">'.csrf_field().'<input type="hidden" name="action" value="pupil_note_delete"><input type="hidden" name="id" value="'.(int)$x['id'].'"><input type="hidden" name="pupil" value="'.$pid.'"><button class="btn quiet small" aria-label="Delete this note">'.icon('trash').'</button></form></li>';$h.='</ul>';}
 return $h.'<form method="post" class="pn-add">'.csrf_field().'<input type="hidden" name="action" value="pupil_note_add"><input type="hidden" name="pupil" value="'.$pid.'"><label class="sr-only" for="pn-new">New note</label><input id="pn-new" name="note" maxlength="200" required placeholder="Example: Needs glasses · reads better with a buddy"><button class="btn secondary small">'.icon('check').'Add note</button></form></section>';
}

/* ---------- actions ---------- */
function ct_action(string $action):never{
 $u=require_role('teacher');$tid=(int)$u['id'];if(!ct_ready())fail('Ask your administrator to import 050_class_tools.sql.');
 if($action==='week_plan_save'){$sec=owned_section((int)($_POST['section']??0),$tid);$week=(string)($_POST['week']??'');if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$week)||date('N',strtotime($week))!=='1')fail('Choose a week.');
  db()->beginTransaction();try{foreach(CT_DAYS as $d=>$dn){$l=(int)($_POST['day'][$d]??0);
   if($l&&!val('SELECT 1 FROM lessons WHERE id=? AND published=1',[$l]))$l=0;
   if($l)q('INSERT INTO week_plans(teacher_id,section_id,week_start,day,lesson_id) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE lesson_id=VALUES(lesson_id),teacher_id=VALUES(teacher_id)',[$tid,(int)$sec['id'],$week,$d,$l]);
   else q('DELETE FROM week_plans WHERE section_id=? AND week_start=? AND day=?',[(int)$sec['id'],$week,$d]);}db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Week plan saved. Your pupils see it on their Overview.');go('?page=week_plan&section='.(int)$sec['id'].'&w='.(int)($_POST['w']??0));}
 if($action==='group_save'){$name=trim(preg_replace('/\s+/',' ',(string)($_POST['name']??'')));if($name===''||mb_strlen($name)>80)fail('Give the group a short name.');$id=(int)($_POST['id']??0);
  if($id&&!val('SELECT 1 FROM pupil_groups WHERE id=? AND teacher_id=?',[$id,$tid]))fail('Group not found.',404);
  $mine=array_flip(array_map('intval',array_column(rows('SELECT pupil_id FROM teacher_pupils WHERE teacher_id=?',[$tid]),'pupil_id')));$pick=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['pupils']??[])),fn($p)=>isset($mine[$p]))));
  db()->beginTransaction();try{if($id)q('UPDATE pupil_groups SET name=? WHERE id=?',[$name,$id]);else{q('INSERT INTO pupil_groups(teacher_id,name) VALUES(?,?)',[$tid,$name]);$id=(int)db()->lastInsertId();}
   q('DELETE FROM pupil_group_members WHERE group_id=?',[$id]);foreach($pick as $p)q('INSERT IGNORE INTO pupil_group_members(group_id,pupil_id) VALUES(?,?)',[$id,$p]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Group saved: '.$name.' ('.count($pick).' '.(count($pick)===1?'pupil':'pupils').').');go('?page=groups');}
 if($action==='group_delete'){q('DELETE FROM pupil_groups WHERE id=? AND teacher_id=?',[(int)($_POST['id']??0),$tid]);flash('Group deleted. The pupils stay in your class.');go('?page=groups');}
 if($action==='group_message'){$id=(int)($_POST['id']??0);if(!val('SELECT 1 FROM pupil_groups WHERE id=? AND teacher_id=?',[$id,$tid]))fail('Group not found.',404);
  $body=trim(str_replace(["\r\n","\r"],"\n",(string)($_POST['body']??'')));if($body===''||mb_strlen($body)>2000)fail('Write a message of up to 2,000 characters.');if(!function_exists('chat_ready')||!chat_ready())fail('Chat is not set up yet.');
  $ids=array_map('intval',array_column(rows('SELECT m.pupil_id FROM pupil_group_members m JOIN teacher_pupils t ON t.pupil_id=m.pupil_id AND t.teacher_id=? JOIN users u ON u.id=m.pupil_id AND u.active=1 WHERE m.group_id=?',[$tid,$id]),'pupil_id'));if(!$ids)fail('There are no pupils in this group.');
  db()->beginTransaction();try{foreach($ids as $p){$c=chat_id($tid,$p,true);q('INSERT INTO chat_messages(chat_id,author_id,body) VALUES(?,?,?)',[$c,$tid,$body]);q('UPDATE chats SET last_at=NOW() WHERE id=?',[$c]);notify($p,'chat','New message from '.$u['name'],mb_substr($body,0,200),'?page=messages&tab=chat&with='.$tid.'#msg-end');}audit('chat_group',count($ids).' pupils');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Sent to '.count($ids).' '.(count($ids)===1?'pupil':'pupils').'. Each one can reply in their own chat.');go('?page=groups');}
 if($action==='pupil_note_add'){$pid=(int)($_POST['pupil']??0);if(!ct_owned_pupil($tid,$pid))fail('Pupil not found.',404);$n=trim(preg_replace('/\s+/',' ',(string)($_POST['note']??'')));if($n===''||mb_strlen($n)>200)fail('Write a note of up to 200 characters.');
  q('INSERT INTO pupil_notes(teacher_id,pupil_id,note) VALUES(?,?,?)',[$tid,$pid,$n]);go('?page=pupil&id='.$pid.'#my-notes');}
 if($action==='pupil_note_delete'){$pid=(int)($_POST['pupil']??0);q('DELETE FROM pupil_notes WHERE id=? AND teacher_id=?',[(int)($_POST['id']??0),$tid]);go('?page=pupil&id='.$pid.'#my-notes');}
 fail('Unknown action.');
}
