<?php
/* Cycles (v182): inside a level, the administrator chooses when groups of lessons open, e.g. Cycle 1 = Lessons 1-5 from
   Nov 3, Cycle 2 = Lessons 6-10 from Nov 17. Lessons are numbered as on the road map (the pre-test and post-test have no
   number): the pre-test is always open and the post-test opens with the last cycle. A lesson of a cycle that has not
   opened yet stays locked, and the road map shows its date. Levels a pupil skipped (free practice) are never locked.
   Saved in the settings table (key lesson_cycles) as JSON: {"on":true,"levels":{"2":[{"from":1,"to":5,"date":"2026-11-03"}]}}. No SQL. */
const CYCLE_ROWS=8;

function cycles_get():array{
 static $s=null;if($s!==null)return $s;
 $raw=val("SELECT setting_value FROM settings WHERE setting_key='lesson_cycles'");$j=$raw?json_decode((string)$raw,true):null;$lv=[];
 foreach((array)($j['levels']??[]) as $k=>$list)foreach((array)$list as $c)
  if(isset($c['from'],$c['to'],$c['date'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$c['date']))$lv[(int)$k][]=['from'=>(int)$c['from'],'to'=>(int)$c['to'],'date'=>(string)$c['date']];
 return $s=['on'=>!empty($j['on']),'levels'=>$lv];
}
/** Each lesson of a module: [lesson id => number on the road map (0 = pre-test or post-test), 'post' => post-test ids]. */
function cycle_numbers(int $module):array{
 static $c=[];if(isset($c[$module]))return $c[$module];
 $out=['no'=>[],'post'=>[]];$n=0;
 foreach(rows("SELECT l.id,GROUP_CONCAT(DISTINCT a.phase) ph FROM lessons l LEFT JOIN activities a ON a.lesson_id=l.id AND a.published=1 WHERE l.module_id=? AND l.published=1 GROUP BY l.id ORDER BY l.position,l.id",[$module]) as $r){
  $ph=explode(',',(string)$r['ph']);
  if(in_array('learn',$ph,true)||$r['ph']===null)$out['no'][(int)$r['id']]=++$n;
  else{$out['no'][(int)$r['id']]=0;if(in_array('post',$ph,true))$out['post'][]=(int)$r['id'];}
 }
 return $c[$module]=$out;
}
/** The most lessons any grade has in a level (for the admin's choices). */
function cycle_level_size(int $level):int{
 $m=0;foreach(rows('SELECT id FROM modules WHERE level_id=?',[$level]) as $r)$m=max($m,count(array_filter(cycle_numbers((int)$r['id'])['no'])));return $m;
}
/** The date a lesson opens, when that date is still in the future (null: open). */
function lesson_cycle_opens(int $lid):?string{
 $s=cycles_get();if(!$s['on'])return null;
 $l=one('SELECT l.id,l.module_id,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?',[$lid]);if(!$l)return null;
 $list=$s['levels'][(int)$l['level_id']]??[];if(!$list)return null;
 $nums=cycle_numbers((int)$l['module_id']);$no=$nums['no'][$lid]??0;$today=date('Y-m-d');$d=null;
 if($no>0){foreach($list as $c)if($no>=$c['from']&&$no<=$c['to']){$d=$c['date'];break;}}
 elseif(in_array($lid,$nums['post'],true))$d=max(array_column($list,'date'));   // the post-test opens with the last cycle
 return $d!==null&&$d>$today?$d:null;
}
/** Locked by a cycle for this pupil (never in a level the pupil skipped). */
function lesson_cycle_locked(int $pid,int $lid,int $level):bool{
 return level_rank($level)>=level_rank(pupil_start_level($pid))&&lesson_cycle_opens($lid)!==null;
}

/* ---------- Administrator: Settings › Schedule & cycles ---------- */
function cycle_admin_view():void{
 $s=cycles_get();$today=date('Y-m-d');
 $levels=rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY '.level_order_sql('id'));$ids=array_map(fn($r)=>(int)$r['id'],$levels);
 $lv=(int)($_GET['cyc']??($ids[0]??1));if(!in_array($lv,$ids,true))$lv=$ids[0]??1;$size=cycle_level_size($lv);$list=$s['levels'][$lv]??[];
 echo '<section class="card sch-card cyc-card" id="cycles"><div class="sch-head"><div><h2>'.icon('cal').' Cycles inside a level</h2><p class="muted">Choose when groups of lessons open. Lessons are numbered as on the pupils’ road map. The pre-test is always open; the post-test opens with the last cycle.</p></div></div>';
 echo '<nav class="cyc-levels" aria-label="Choose a level">';foreach($ids as $id){$n=count($s['levels'][$id]??[]);echo '<a class="'.($id===$lv?'on':'').'" href="?page=settings&amp;tab=schedule&amp;cyc='.$id.'#cycles">'.e(level_label($id)).($n?'<small>'.$n.' '.($n===1?'cycle':'cycles').'</small>':'').'</a>';}echo '</nav>';
 echo '<form method="post" class="sch-form" data-cyc-form data-cyc-size="'.$size.'">'.csrf_field().'<input type="hidden" name="action" value="admin_lesson_cycles"><input type="hidden" name="level" value="'.$lv.'">';
 echo '<div class="cyc-top"><p><b>'.e(level_label($lv)).'</b> has <b>'.$size.'</b> '.($size===1?'lesson':'lessons').' (not counting the pre-test and post-test).</p><label class="sch-switch"><input type="checkbox" name="on" value="1"'.($s['on']?' checked':'').'><i aria-hidden="true"></i><span>Cycles on (all levels)</span></label></div>';
 echo '<div class="sch-fill"><label>Lessons in each cycle<input type="number" min="1" max="'.max(1,$size).'" value="'.max(1,min(5,$size)).'" data-cyc-per></label><label>Cycle 1 opens<input type="date" data-cyc-start value="'.e($list[0]['date']??date('Y-m-d',strtotime('monday this week'))).'"></label><label>A new cycle every<select data-cyc-weeks><option value="1">1 week</option><option value="2" selected>2 weeks</option><option value="3">3 weeks</option><option value="4">4 weeks</option></select></label><button type="button" class="btn secondary" data-cyc-fill>'.icon('cal').'Fill the cycles for me</button></div>';
 echo '<div class="tablewrap"><table class="sch-table cyc-table"><thead><tr><th>Cycle</th><th>From lesson</th><th>To lesson</th><th>Opens on</th><th>Now</th></tr></thead><tbody>';
 for($i=0;$i<CYCLE_ROWS;$i++){$c=$list[$i]??null;$d=$c['date']??'';
  $st=!$c?['off','—']:(!$s['on']?['off','Cycles off']:($d<=$today?['o','Open']:['l','Locked until '.date('M j',strtotime($d))]));
  echo '<tr data-cyc-row><td><span class="sch-wk">C'.($i+1).'</span></td><td><input type="number" min="1" max="'.max(1,$size).'" name="from[]" value="'.($c['from']??'').'" aria-label="Cycle '.($i+1).' from lesson"></td><td><input type="number" min="1" max="'.max(1,$size).'" name="to[]" value="'.($c['to']??'').'" aria-label="Cycle '.($i+1).' to lesson"></td><td><input type="date" name="date[]" value="'.e($d).'" aria-label="Cycle '.($i+1).' opens on"></td><td><span class="sch-st sch-'.$st[0].'">'.e($st[1]).'</span></td></tr>';}
 echo '</tbody></table></div><p class="sch-note">'.icon('info').'<span>Leave a row empty to not use it. Lessons not in any cycle are open. A pupil still finishes the lessons in order, and levels a pupil skipped are never locked.</span></p>';
 echo '<div class="sch-actions"><button type="button" class="btn quiet" data-cyc-clear>Clear this level</button><button class="btn primary">'.icon('check').'Save cycles</button></div></form></section>';
}
function cycle_admin_save():void{
 require_role('admin');$lv=(int)($_POST['level']??0);if(!in_array($lv,LEVEL_ORDER,true))fail('Choose a level.');$size=cycle_level_size($lv);
 $from=(array)($_POST['from']??[]);$to=(array)($_POST['to']??[]);$date=(array)($_POST['date']??[]);$list=[];
 for($i=0;$i<min(CYCLE_ROWS,count($from));$i++){
  $f=trim((string)$from[$i]);$t=trim((string)($to[$i]??''));$d=trim((string)($date[$i]??''));
  if($f===''&&$t===''&&$d==='')continue;
  if($f===''||$t===''||$d===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)||!strtotime($d))fail('Cycle '.($i+1).': fill in the first lesson, the last lesson and the date.');
  $f=(int)$f;$t=(int)$t;if($f<1||$t<$f||$t>$size)fail('Cycle '.($i+1).': choose lessons from 1 to '.$size.', the first one not after the last one.');
  $list[]=['from'=>$f,'to'=>$t,'date'=>$d];
 }
 usort($list,fn($a,$b)=>$a['from']<=>$b['from']);
 for($i=1;$i<count($list);$i++){if($list[$i]['from']<=$list[$i-1]['to'])fail('Two cycles share lessons '.$list[$i]['from'].' to '.min($list[$i]['to'],$list[$i-1]['to']).'. Each lesson can be in one cycle only.');
  if($list[$i]['date']<$list[$i-1]['date'])fail('Cycle with lessons '.$list[$i]['from'].'–'.$list[$i]['to'].' cannot open before the cycle with earlier lessons.');}
 $s=cycles_get();$levels=$s['levels'];if($list)$levels[$lv]=$list;else unset($levels[$lv]);$on=!empty($_POST['on']);
 q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['lesson_cycles',json_encode(['on'=>$on,'levels'=>(object)$levels])]);
 audit('admin_lesson_cycles',level_label($lv).': '.count($list).' cycles'.($on?'':' (off)'));
 flash(!$list?level_label($lv).' has no cycles now: its lessons are open.':($on?level_label($lv).' cycles saved. Lessons open on their dates.':level_label($lv).' cycles saved. Turn "Cycles on" to use them.'));
 go('?page=settings&tab=schedule&cyc='.$lv.'#cycles');
}

