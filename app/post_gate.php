<?php
/* Post-test pass mark: a pupil opens the next level only after scoring at least 80% on the post-test of the level before.
   - A level's post-test is every published post-test activity of that level (for the pupil's grade). Answers are marked by
     the answer keys (app/scoring.php); the teacher can change any score.
   - Below 80%, the pupil answers again the post-test activities that were not fully right ("Try again"), then the score is
     worked out again. Levels below the pupil's starting level are not checked, and a level with no post-test has no pass mark. */

const PT_PASS=80;

/** The post-test activities of a level for the pupil's grade. */
function pt_acts(int $pid,int $level):array{static $c=[];return $c[$pid.':'.$level]??=rows("SELECT a.id,a.lesson_id,a.title,a.position FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND a.phase='post' AND a.published=1 AND l.published=1".GRADE_SQL.' ORDER BY l.position,a.position',[$level,pupil_grade($pid)]);}

/** The pupil's post-test result for a level: n activities, done (all answered), right / total, pct, and the activities
    that were not fully right (missed). An answer saved before auto-checking existed is marked now. */
function pt_result(int $pid,int $level,bool $fresh=false):array{
 static $c=[];$ck=$pid.':'.$level;if(!$fresh&&isset($c[$ck]))return $c[$ck];
 if(function_exists('done_scores_repair'))done_scores_repair();
 $acts=pt_acts($pid,$level);$out=['n'=>count($acts),'done'=>0,'right'=>0.0,'total'=>0.0,'pct'=>100,'missed'=>[]];if(!$acts)return $c[$ck]=$out;
 $ids=array_map(fn($a)=>(int)$a['id'],$acts);$rows=[];
 foreach(rows('SELECT id,activity_id,status,score,max_score FROM activity_completion WHERE pupil_id=? AND activity_id IN ('.implode(',',$ids).')',[$pid]) as $r)$rows[(int)$r['activity_id']]=$r;
 foreach($acts as $a){$r=$rows[(int)$a['id']]??null;if(!completion_ok($r))continue;$out['done']++;
  if($r['max_score']===null){key_save_score((int)$r['id']);$r=one('SELECT id,activity_id,status,score,max_score FROM activity_completion WHERE id=?',[(int)$r['id']]);}
  $mx=(float)($r['max_score']??0);if($mx<=0)continue;$out['right']+=(float)$r['score'];$out['total']+=$mx;if((float)$r['score']<$mx)$out['missed'][]=(int)$a['id'];}
 $out['pct']=$out['total']>0?(int)floor(100*$out['right']/$out['total']+1e-9):100;
 return $c[$ck]=$out;
}
/** True when the pupil may go past this level: no post-test, not finished yet (the level itself is not finished then),
    or at least 80%. */
function pt_passed(int $pid,int $level):bool{$r=pt_result($pid,$level);return !$r['n']||$r['done']<$r['n']||$r['pct']>=PT_PASS;}
/** Levels the pupil finished but did not pass (from the starting level up). */
function pt_blocked(int $pid):array{
 $start=pupil_start_level($pid);if(!$start)return [];$out=[];
 foreach(rows('SELECT id FROM bulig_levels WHERE '.level_order_sql('id').'>='.level_order_sql('?').' AND published=1 ORDER BY '.level_order_sql('id'),[$start]) as $l){$L=(int)$l['id'];$r=pt_result($pid,$L);if($r['n']&&$r['done']>=$r['n']&&$r['pct']<PT_PASS)$out[$L]=$r;}
 return $out;
}
function pt_next_level(int $level):?int{$n=val('SELECT id FROM bulig_levels WHERE '.level_order_sql('id').'>'.level_order_sql('?').' AND published=1 ORDER BY '.level_order_sql('id').' LIMIT 1',[$level]);return $n?(int)$n:null;}

/** After a post-test answer is saved: tell the pupil the result once the whole post-test is answered. */
function pt_after_answer(int $pid,array $a):void{
 if(($a['phase']??'')!=='post'||!function_exists('notify'))return;$L=lesson_level((int)$a['lesson_id']);$r=pt_result($pid,$L,true);if(!$r['n']||$r['done']<$r['n'])return;
 $next=pt_next_level($L);
 if($r['pct']>=PT_PASS)notify($pid,'post','You passed the '.level_label($L).' post-test!','Your score is '.$r['pct'].'%.'.($next?' '.level_label($next).' is ready for you.':''),'?page=lessons');
 else notify($pid,'post',level_label($L).' post-test: '.$r['pct'].'%','You need '.PT_PASS.'% to open '.($next?level_label($next):'the next level').'. Try the questions you missed again.','?page=lessons');
}

/** "Try again": the post-test activities that were not fully right open again on the pupil's lesson path. */
function pt_retake(array $u,int $level):void{
 $pid=(int)$u['id'];$r=pt_result($pid,$level,true);if(!$r['n']||$r['done']<$r['n']||$r['pct']>=PT_PASS||!$r['missed'])fail('There is nothing to try again here.',400);
 $note='Try this post-test activity again. You need '.PT_PASS.'% to open the next level.';$lessons=[];
 foreach($r['missed'] as $aid){$c=one('SELECT id,activity_id FROM activity_completion WHERE pupil_id=? AND activity_id=?',[$pid,$aid]);if(!$c)continue;
  q("UPDATE activity_completion SET status='retry',feedback=?,reviewed_at=NULL WHERE id=?",[$note,(int)$c['id']]);
  q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[(int)$c['id'],'Post-test try again ('.$r['pct'].'%)','redo',$pid]);
  $lessons[]=(int)val('SELECT lesson_id FROM activities WHERE id=?',[$aid]);}
 foreach(array_unique($lessons) as $lid)q('UPDATE pupil_progress SET completed_at=NULL WHERE pupil_id=? AND lesson_id=?',[$pid,$lid]);
 audit('post_retake',level_label($level).' '.$r['pct'].'%');
 $first=$r['missed'][0];go('?page=lesson&id='.(int)val('SELECT lesson_id FROM activities WHERE id=?',[$first]).'&activity='.$first);
}

/** Pupil: a card for each finished level whose post-test is below 80%, with a Try again button. */
function pt_card(int $pid,?int $only=null):string{
 $h='';foreach(pt_blocked($pid) as $L=>$r){if($only&&$L!==$only)continue;$next=pt_next_level($L);$n=count($r['missed']);
  $h.='<section class="card pg-gate" aria-label="'.e(level_label($L)).' post-test"><div class="pg-gate-top"><span class="pg-gate-ic" aria-hidden="true">'.icon('star').'</span><div><span class="eyebrow">POST-TEST · '.e(strtoupper(level_label($L))).'</span><h2>Almost there! You got '.$r['pct'].'%.</h2>'.(function_exists('stars_html')?stars_html(star_count($r['pct']/100,'post'),5,'pg-stars'):'')
   .'<p class="muted">You need 4 stars ('.PT_PASS.'%) to open '.e($next?level_label($next):'the next level').'. Answer the '.($n===1?'activity':$n.' activities').' you missed again. You can do it!</p></div></div>'
   .'<div class="pg-bar" role="img" aria-label="Your score '.$r['pct'].' percent. Pass mark '.PT_PASS.' percent."><i class="pg-fill w'.(int)(round(min(100,$r['pct'])/5)*5).'"></i><span class="pg-mark" title="Pass mark"></span></div><div class="pt-legend"><span>Your score: <b>'.$r['pct'].'%</b></span><span>Pass mark: <b>'.PT_PASS.'%</b></span></div>'
   .'<form method="post" class="pg-go">'.csrf_field().'<input type="hidden" name="action" value="post_retake"><input type="hidden" name="level" value="'.$L.'"><button class="btn primary">'.icon('arrow').'Try again</button></form></section>';}
 return $h;
}

/** The end screen of a post-test lesson: the score once every post-test activity of the level is answered. */
function pt_lesson_note(int $pid,int $lid):string{
 $L=lesson_level($lid);$acts=pt_acts($pid,$L);if(!$acts||!in_array($lid,array_map(fn($a)=>(int)$a['lesson_id'],$acts),true))return '';
 $r=pt_result($pid,$L,true);if($r['done']<$r['n'])return '';if($r['pct']<PT_PASS)return pt_card($pid,$L);$next=pt_next_level($L);
 return '<section class="card pg-gate pg-pass" aria-label="'.e(level_label($L)).' post-test"><div class="pg-gate-top"><span class="pg-gate-ic" aria-hidden="true">'.icon('check').'</span><div><span class="eyebrow">POST-TEST · '.e(strtoupper(level_label($L))).'</span><h2>You passed! You got '.$r['pct'].'%.</h2>'.(function_exists('stars_html')?stars_html(star_count($r['pct']/100,'post'),5,'pg-stars'):'')
  .'<p class="muted">'.($next?(val('SELECT completed_at FROM pupil_progress WHERE pupil_id=? AND lesson_id=?',[$pid,$lid])?e(level_label($next)).' is open now. Great reading!':'Tap Done, and '.e(level_label($next)).' opens for you.'):'Great reading!').'</p></div></div>'
  .'<div class="pg-bar" role="img" aria-label="Your score '.$r['pct'].' percent. Pass mark '.PT_PASS.' percent."><i class="pg-fill w'.(int)(round(min(100,$r['pct'])/5)*5).'"></i><span class="pg-mark" title="Pass mark"></span></div><div class="pt-legend"><span>Your score: <b>'.$r['pct'].'%</b></span><span>Pass mark: <b>'.PT_PASS.'%</b></span></div></section>';
}

/** Teacher: the pupil's post-test score for each level they finished (pupil page). */
function pt_teacher_rows(int $pid):string{
 $start=pupil_start_level($pid);if(!$start)return '';$h='';
 foreach(rows('SELECT id FROM bulig_levels WHERE '.level_order_sql('id').'>='.level_order_sql('?').' AND published=1 ORDER BY '.level_order_sql('id'),[$start]) as $l){$L=(int)$l['id'];$r=pt_result($pid,$L);if(!$r['n']||!$r['done'])continue;
  $fin=$r['done']>=$r['n'];$h.='<tr><td>'.e(level_label($L)).'</td><td>'.$r['done'].' of '.$r['n'].'</td><td>'.($fin?'<b>'.$r['pct'].'%</b>':'—').'</td><td>'.($fin?($r['pct']>=PT_PASS?'<span class="pg-ok">'.icon('check').'Passed</span>':'<span class="pg-no">Below '.PT_PASS.'% · trying again</span>'):'In progress').'</td></tr>';}
 return $h?'<section class="card pg-teacher"><h2>Post-tests</h2><p class="muted">A pupil opens the next level after scoring at least '.PT_PASS.'% on the post-test. Below that, they answer the missed activities again.</p><div class="tablewrap"><table><thead><tr><th>Level</th><th>Answered</th><th>Score</th><th>Result</th></tr></thead><tbody>'.$h.'</tbody></table></div></section>':'';
}

/** v183: Level 1 keeps its pre-assessment and post-assessment inside every lesson (as in the module). This card on the
    level's road map shows the post-test: every lesson's post-assessment together, 5 stars, 80% to open the next level. */
function pt_inlesson_card(int $pid,int $level):string{
 $acts=pt_acts($pid,$level);if(!$acts)return '';
 if(val("SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1 AND EXISTS(SELECT 1 FROM activities a WHERE a.lesson_id=l.id AND a.published=1) AND NOT EXISTS(SELECT 1 FROM activities a WHERE a.lesson_id=l.id AND a.published=1 AND a.phase<>'post')",[$level]))return '';   // a level with its own post-test stop
 $r=pt_result($pid,$level);$all=$r['done']>=$r['n'];$st=$r['done']?star_count($r['pct']/100,'post'):0;$nx=level_next($level);$nl=$nx?level_label($nx):'the next level';
 $msg=!$r['done']?'Each lesson ends with a short Post-assessment. Together they are your '.level_label($level).' Post-test. Get 80% to open '.$nl.'.'
  :(!$all?'So far '.$r['pct'].'%. Keep going: '.($r['n']-$r['done']).' more to answer.'
  :($r['pct']>=PT_PASS?'You passed with '.$r['pct'].'%! '.$nl.' is open.':'You got '.$r['pct'].'%. You need 80% to open '.$nl.'. Try the missed ones again.'));
 $cls=$all?($r['pct']>=PT_PASS?' pt-ok':' pt-low'):'';
 $trophy='<svg viewBox="-16 -16 32 32" aria-hidden="true"><path d="M-9 -12h18v6a9 9 0 0 1-18 0Z" fill="currentColor"/><path d="M-9 -9h-5a5 5 0 0 0 6 7M9 -9h5a5 5 0 0 1-6 7" stroke="currentColor" stroke-width="2.4" fill="none"/><rect x="-2" y="2" width="4" height="6" fill="currentColor"/><rect x="-7" y="8" width="14" height="4" rx="1.5" fill="currentColor"/></svg>';
 return '<section class="card pt-in'.$cls.'" aria-label="'.e(level_label($level)).' Post-test"><span class="pt-in-ic">'.$trophy.'</span><div class="pt-in-tx"><span class="eyebrow">'.e(mb_strtoupper(level_label($level))).' POST-TEST</span><h3>Show what you learned</h3><p>'.e($msg).'</p></div><div class="pt-in-st">'.stars_html($st,5).'<b>'.($r['done']?$r['pct'].'%':'—').'</b><small>'.$r['done'].' of '.$r['n'].' answered</small></div></section>';
}
