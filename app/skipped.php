<?php
/* Skipped levels (below a pupil's starting level) are free practice: every lesson and every activity is open,
   and the pupil chooses where to start. Required levels keep the one-step-at-a-time order. */

function lesson_is_skipped(int $pid,int $lid):bool{
 static $c=[];$k=$pid.':'.$lid;if(isset($c[$k]))return $c[$k];
 $start=pupil_start_level($pid);
 return $c[$k]=$start>0&&lesson_level($lid)<$start;
}
/** On the lesson start screen: the three parts, and in a skipped level a list to start anywhere. */
function lesson_parts(array $lesson,array $all,array $count):string{
 $u=current_user();$lid=(int)$lesson['id'];$free=$u&&$u['role']==='pupil'&&lesson_is_skipped((int)$u['id'],$lid);
 if(!$free){$h='<div class="l1-steps">';foreach($count as $p=>$n)if($n)$h.='<span><b>'.$n.'</b>'.e(l1_phase_label($p)).'</span>';return $h.'</div>';}
 $firstOf=[];foreach($all as $x)$firstOf[$x['phase']]??=$x;
 $h='<div class="sk-pick" id="sk-pick"><p class="sk-lab">'.icon('star').'<span><b>Free practice</b> · This level was skipped for you, so everything is open. Start anywhere you like.</span></p><div class="l1-steps sk-parts">';
 foreach($count as $p=>$n)if($n){$x=$firstOf[$p];$done=count(array_filter($all,fn($a)=>$a['phase']===$p&&completion_ok($a)));$h.='<a href="?page=lesson&amp;id='.$lid.'&amp;activity='.(int)$x['id'].'"><b>'.$n.'</b>'.e(l1_phase_label($p)).'<small>'.($done?$done.' done':'Start here').'</small></a>';}
 $h.='</div><details class="sk-list"><summary>'.icon('book').'Pick any activity<span>'.count($all).'</span></summary><ol>';
 foreach($all as $i=>$x){$ok=completion_ok($x);$h.='<li><a href="?page=lesson&amp;id='.$lid.'&amp;activity='.(int)$x['id'].'" class="'.($ok?'done':'').'"><span class="sk-n">'.($ok?icon('check'):($i+1)).'</span><span class="sk-t">'.e((string)$x['title']).'<small>'.e(l1_phase_label((string)$x['phase'])).'</small></span>'.icon('chev').'</a></li>';}
 return $h.'</ol></details></div>';
}
