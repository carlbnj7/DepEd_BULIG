<?php
/* Jeepy the Jeepney (v199): BULIG friend #14. The pupil's own boy or girl rides in Jeepy along every road map:
   on My lessons (one stop for each level) and inside each level (one stop for each lesson, assets/app.js).
   Jeepy parks on the road next to the current stop, never on top of it. Four ride poses for each pupil:
   driving (moving to the new stop), arriving (at the current stop), finished (all done) and waiting (nothing open yet).
   Pictures: assets/images/characters/jeepy-<boy|girl>-<pose>.webp, and jeepy-hello/thumbs/cheer for the friends intro. */

const JEEPY_POSES=['drive'=>'driving','stop'=>'arriving','done'=>'finished','wait'=>'waiting'];
const JEEPY_LEVEL_NAMES=[1=>'Oral Language',2=>'Phonological Awareness',3=>'Phonological Awareness',4=>'Word Recognition',9=>'Word Recognition',5=>'Fluency',6=>'Listening and Vocabulary',7=>'Reading Comprehension',8=>'Love for Reading'];

/** The four ride pictures for the signed-in pupil (the girl rides for female pupils, the boy otherwise). */
function jeepy_rides(?bool $girl=null):array{
 $g=($girl??(function_exists('pf_is_girl')&&pf_is_girl()))?'girl':'boy';
 return array_map(fn($p)=>'assets/images/characters/jeepy-'.$g.'-'.$p.'.webp?v=1',JEEPY_POSES);
}

/** My lessons: the reading journey, one stop for each level, with Jeepy at the pupil's level and the
    Reading Castle from the BULIG comic at the end of the road (Level 7). */
function jeepy_journey(int $pid):string{
 $assign=one('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);$start=(int)($assign['level_id']??0);
 $next=next_learning_lesson($pid);$nextLevel=(int)($next['level_id']??0);$items=[];$done=0;
 foreach(rows('SELECT id,title,published FROM bulig_levels ORDER BY '.level_order_sql('id')) as $l){
  $id=(int)$l['id'];$c=one('SELECT COUNT(*) total,COUNT(p.completed_at) done FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$pid,$id,pupil_grade($pid)]);
  $n=(int)$c['total'];$complete=$n>0&&(int)$c['done']===$n;$available=level_available($pid,$id);$skipped=$start>0&&level_rank($id)<level_rank($start);
  $st=$complete?'done':($skipped?'skip':($available&&$id===$nextLevel?'cur':($available?'open':'lock')));
  if($complete||$skipped)$done++;
  $items[]=['id'=>$id,'c'=>'assets/images/covers/level-'.$id.'.webp','title'=>stripos((string)$l['title'],'Level')!==false?(JEEPY_LEVEL_NAMES[$id]??''):(string)$l['title'],'d'=>(int)$c['done'],'all'=>$n,'n'=>str_replace('Level ','',level_label($id)),'t'=>JEEPY_LEVEL_NAMES[$id]??'','s'=>$st,'p'=>$n?(int)round(100*(int)$c['done']/$n):0,
   'h'=>$available?'?page=lessons&level='.$id.'#level'.$id.'-path':'','x'=>$next&&$id===$nextLevel?(string)($next['subtitle']?:$next['title']):''];
 }
 if(!$items)return '';
 $cur=null;foreach($items as $it)if($it['s']==='cur')$cur=$it;
 $first=explode(' ',trim((string)current_user()['name']))[0];
 $chip=$cur?'Next stop: Level '.$cur['n'].' · '.$cur['t']:($done===count($items)?'You finished every level!':'Jeepy is waiting for your next level');
 /* v200: tapping a stop pops up that level's card (its picture, progress, Continue and Save for offline); no separate level cards */
 $pops='';foreach($items as $i=>$it){$lk=$it['s']==='lock';
  $tag=['done'=>'COMPLETED','skip'=>'SKIPPED · FREE PRACTICE','cur'=>'YOUR LEVEL','open'=>'OPEN','lock'=>'LOCKED'][$it['s']];
  $btn=$it['h']?'<a class="btn primary" href="'.e($it['h']).'">'.($it['s']==='done'?'Review lessons':($it['d']?'Continue learning':($it['s']==='skip'?'Explore this level':'Start'))).' '.icon('arrow').'</a><div class="off-slot" data-off-level="'.(int)$it['id'].'" data-off-title="'.e('Level '.$it['n']).'"></div>'
   :'<p class="jy-pop-msg">'.icon('lock').($cur?'Finish Level '.e($cur['n']).' first, then Jeepy drives you here.':'Your teacher opens this level for you.').'</p>';
  $pops.='<div class="jy-pop jy-pop-'.$it['s'].'" data-jy-pop="'.$i.'" hidden role="dialog" aria-modal="true" aria-label="'.e('Level '.$it['n']).'"><button type="button" class="jy-pop-x" data-jy-x aria-label="Close">'.icon('close').'</button>'
   .'<img src="'.e($it['c']).'" alt="" width="480" height="300" loading="lazy"><div class="jy-pop-b"><span class="jy-now-tag">'.$tag.'</span><b>Level '.e($it['n']).' · '.e($it['title']?:$it['t']).'</b><small>'.$it['d'].' of '.$it['all'].' done'.($it['x']?' · Next: <strong>'.e($it['x']).'</strong>':'').'</small>'.$btn.'</div></div>';}
 $bar='<div class="jy-pops" hidden>'.$pops.'</div>';
 return '<section class="card jy-card" aria-label="My reading journey"><div class="jy-head"><div><span class="eyebrow">MY BULIG JOURNEY</span><h2>'.e($first).'’s road map</h2><span class="jy-chip">'.e($chip).'</span></div>'
  .'<div class="jy-side"><div class="jy-links"><a class="btn secondary off-pagelink" href="?page=board">'.icon('ladder').'My reading board</a><a class="btn secondary off-pagelink" href="?page=offline">'.icon('download').'Offline lessons</a></div>'
  .'<div class="jy-prog"><b>'.$done.' of '.count($items).' levels done</b><progress max="'.count($items).'" value="'.$done.'" aria-label="Levels done"></progress></div></div></div>'
  .'<div class="jy-map" data-jy-map="'.e(json_encode(['items'=>$items,'jy'=>jeepy_rides(),'cd'=>function_exists('castle_open')&&castle_open($pid)?1:0])).'">'.(function_exists('castle_art')?'<template class="jy-castle">'.castle_art('jy').'</template>':'').'</div>'
  .'<ol class="jy-list">'.implode('',array_map(fn($it)=>'<li class="jy-'.$it['s'].'">'.($it['h']?'<a href="'.e($it['h']).'">':'<span>').'Level '.e($it['n']).' · '.e($it['t']).($it['h']?'</a>':'</span>').'</li>',$items)).'</ol>'.$bar.'</section>';
}
