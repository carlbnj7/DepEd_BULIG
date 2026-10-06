<?php
/** Teacher-only presentation of the current published pupil activities. No writes. */
function class_demo_view(array $u):void{
 require_role('teacher');
 $level=(int)($_GET['level']??0);
 if(!$level){
  shell($u,'class_demo');
  echo '<div class="pageheading"><div><span class="eyebrow">LEARN TOGETHER</span><h1>Class Demo</h1><p>Choose a level to present on your TV or projector. Every available activity is open. Discuss answers together, then choose Next.</p></div></div><div class="level-card-grid demo-catalog">';
  foreach(rows('SELECT b.*,COUNT(a.id) slide_count FROM bulig_levels b LEFT JOIN modules m ON m.level_id=b.id LEFT JOIN lessons l ON l.module_id=m.id AND l.published=1 LEFT JOIN activities a ON a.lesson_id=l.id AND a.published=1 GROUP BY b.id,b.title,b.published ORDER BY b.id') as $l){
   $ready=(bool)$l['published']&&(int)$l['slide_count']>0;
   echo '<article class="level-card '.($ready?'available':'unavailable').'"><div class="level-cover has-cover cover-'.(int)$l['id'].'"><span class="level-number">'.e(str_replace('Level ','',level_label((int)$l['id']))).'</span><span class="cover-symbol">'.icon($ready?'book':'clock').'</span></div><div class="level-card-content"><h2>'.e(level_label((int)$l['id'])).'</h2><p>'.e($ready?$l['title']:'Content is being prepared.').'</p>';
   if($ready)echo '<small>'.(per_grade_level((int)$l['id'])?'A separate module for each grade · Choose a grade':$l['slide_count'].' activities · All activities open').'</small><a class="btn primary" href="?page=class_demo&amp;level='.$l['id'].'">'.(per_grade_level((int)$l['id'])?'Choose a grade':'Start class demo').' '.icon('arrow').'</a>';
   else echo '<span class="level-lock">Coming soon</span>';
   echo '</div></article>';
  }
  echo '</div><section class="card"><h2>Ready for the big screen</h2><p>Connect your computer to the TV, open a level, then choose Full screen. Use the arrow keys or Previous and Next. You can jump to any lesson or slide. Listen uses the same narration as the pupil activity. You decide how to discuss and check answers.</p><p>Demo mode does not save pupil answers, scores, XP, or progress. Levels 4 and 5 have a different module for each grade; you choose the grade after opening them. Levels 6–7 will appear here when their pupil content is published.</p></section>';shell_end();return;
 }
 $info=one('SELECT * FROM bulig_levels WHERE id=? AND published=1',[$level]);
 if(!$info)fail('This level has no published demo content yet.',404);
 if($level>=2&&$level<=4){l2a_demo_view($u,$level);return;}
 $grade=(int)($_GET['grade']??0);
 if(per_grade_level($level)&&!$grade){
  shell($u,'class_demo');
  echo '<div class="pageheading"><div><span class="eyebrow">LEARN TOGETHER · '.e(level_label($level)).'</span><h1>Choose a grade</h1><p>'.e(level_label($level)).' · '.e($info['title']).' has a different module for each grade. Pick the grade of the class you are teaching.</p></div></div><div class="level-card-grid demo-catalog demo-grade-grid">';
  foreach(rows('SELECT m.grade_level,COUNT(l.id) n FROM modules m JOIN lessons l ON l.module_id=m.id AND l.published=1 WHERE m.level_id=? AND m.grade_level IS NOT NULL GROUP BY m.grade_level ORDER BY m.grade_level',[$level]) as $g)echo '<article class="level-card available"><div class="level-cover has-cover cover-'.$level.'"><span class="level-number">G'.(int)$g['grade_level'].'</span><span class="cover-symbol">'.icon('book').'</span></div><div class="level-card-content"><h2>Grade '.(int)$g['grade_level'].'</h2><small>'.e(lesson_count_label($level,(int)$g['n'])).'</small><a class="btn primary" href="?page=class_demo&amp;level='.$level.'&amp;grade='.(int)$g['grade_level'].'">Start class demo '.icon('arrow').'</a></div></article>';
  echo '</div><a class="btn secondary" href="?page=class_demo">Back to all levels</a>';shell_end();return;
 }
 $slides=rows("SELECT a.*,l.title lesson_title,l.subtitle,l.position lesson_position FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1 AND a.published=1".GRADE_SQL." ORDER BY l.position,l.id,FIELD(a.phase,'pre','learn','post'),a.position,a.id",[$level,$grade]);
 if(!$slides)fail('This level has no published demo content yet.',404);
 $expanded=[];foreach($slides as $a){$native=level2_card_set($a);if(!$native){$expanded[]=$a;continue;}foreach($native['cards'] as $j=>$c){$slide=$a;$slide['native_card']=$c;$slide['card_index']=$j;$slide['title']=$native['title'].' · '.$c['title'];$slide['instructions']=$native['instruction'];$slide['narration']=$c['narration'];$expanded[]=$slide;}}$slides=$expanded;
 if($level===1){l1_demo_view($u,$slides);return;}
 $index=0;$requested=(int)($_GET['activity']??0);
 if($requested){$found=false;foreach($slides as $i=>$a)if((int)$a['id']===$requested&&(int)($a['card_index']??0)===max(0,(int)($_GET['card']??0))){$index=$i;$found=true;break;}if(!$found)fail('That slide does not belong to this published level.',404);}
 $choices=[];foreach(rows('SELECT an.activity_id,ans.content FROM answers ans JOIN questions an ON an.id=ans.question_id JOIN activities a ON a.id=an.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND a.type=\'choice\''.GRADE_SQL.' ORDER BY ans.id',[$level,$grade]) as $c)$choices[(int)$c['activity_id']][]=$c['content'];
 $mlabel=module_label($level,per_grade_level($level)?$grade:null);head('Class Demo · '.$mlabel,'demo-page');
 echo '<div id="class-demo" data-level="'.$level.'" data-start="'.$index.'"><header class="demo-header"><a class="demo-logo" href="?page=class_demo"><img src="assets/bulig-logo.png" alt="BULIG"></a><div><strong>'.e($mlabel).' · Class Demo</strong><span>Listen, discuss, and learn together</span></div><button class="btn cd-plan" type="button" data-plan-open aria-haspopup="dialog">'.icon('plan').'Lesson plan</button><button class="btn cd-open" type="button" data-cd-open>'.icon('check').'Mark as done</button><a class="btn quiet" href="?page=class_demo">Exit demo</a><button class="btn secondary" type="button" id="demo-fullscreen">Full screen</button></header><div class="demo-controls"><label>Lesson<select id="demo-lesson">';
 $seen=[];foreach($slides as $i=>$a){if(isset($seen[$a['lesson_id']]))continue;$seen[$a['lesson_id']]=true;echo '<option value="'.$i.'">'.e(lesson_display_label($level,(int)$a['lesson_position']).' · '.$a['subtitle']).'</option>';}
 echo '</select></label><label>Jump to slide<select id="demo-jump">';foreach($slides as $i=>$a)echo '<option value="'.$i.'">'.e(($i+1).' · '.lesson_display_label($level,(int)$a['lesson_position']).' · '.$a['subtitle'].' · '.ucfirst($a['phase']).' · '.$a['title']).'</option>';echo '</select></label><button type="button" class="btn quiet" id="demo-zoom" aria-pressed="false">Enlarge pictures</button></div>';
 if(isset($_SESSION['flash'])){echo '<div class="notice demo-notice" role="status">'.icon('check').'<span>'.e($_SESSION['flash']).'</span></div>';unset($_SESSION['flash']);}
 echo class_done_demo_panel($u,$level,per_grade_level($level)?$grade:0,$mlabel).demo_lesson_plans($slides,$level,per_grade_level($level)?$grade:0,$mlabel);
 narration_controls($slides[$index]['narration']);
 echo '<main id="demo-stage" class="demo-stage" tabindex="-1" aria-label="Class activity slide"></main><footer class="demo-footer"><button class="btn secondary" id="demo-prev" type="button">Previous</button><div><strong id="demo-counter" role="status" aria-live="polite"></strong><small id="demo-help">Arrow keys move between slides · F for full screen</small></div><button class="btn primary" id="demo-next" type="button">Next '.icon('arrow').'</button></footer>';
 foreach($slides as $i=>$a){
  $worksheet=false;
  $articleClass=isset($a['native_card'])?'demo-native':(level4_passage_parts($a)?'demo-fluency-slide':(false?'':(activity_images($a)?'demo-has-visual':'demo-text-slide')));
  $instructions=$a['instructions'];if($instructions==='Share your answer by speaking or typing. Then choose Submit Answer.')$instructions='Discuss your answers together. Your teacher will choose Next.';if(level4_passage_parts($a))$instructions='Read the passage aloud together, or listen as one pupil reads. Use the scoring helper below for an individual reading check.';
  echo '<template class="demo-template" data-card="'.(int)($a['card_index']??0).'" data-id="'.$a['id'].'" data-lesson="'.$a['lesson_id'].'" data-narration="'.e($a['narration']).'"><article class="'.$articleClass.'"><div class="demo-slide-meta">'.e(lesson_display_label($level,(int)$a['lesson_position']).' · '.$a['subtitle'].' · '.['pre'=>'Before we begin','learn'=>'Let’s practice','post'=>'Show what you learned'][$a['phase']]).'</div><h1>'.e($a['title']).'</h1><p class="demo-instructions">'.e($instructions).'</p>';
  if(isset($a['native_card'])){render_level2_card($a['native_card'],true);echo '</article></template>';continue;}
  // Same shared image resolver used by the pupil view.
  $passage=level4_passage($a);
  if($passage!==null){$words=level4_passage_parts($a)['words'];echo '<div class="demo-fluency">'.str_replace('<img src=','<img data-src=',$passage).'</div><details class="fluency-score" data-words="'.$words.'"><summary>Teacher’s scoring helper (Phil-IRI) · not saved</summary><div class="score-grid"><label>Number of miscues<input type="number" min="0" max="'.$words.'" inputmode="numeric" data-score="miscues"></label><label>Reading time (seconds)<input type="number" min="1" inputmode="numeric" data-score="seconds"></label><div>Oral Reading Score<output data-score="percent">—</output></div><div>Reading level<output data-score="level">—</output></div><div>Reading speed<output data-score="wpm">—</output></div></div><p><small>Score = ('.$words.' − miscues) ÷ '.$words.' × 100 · Independent 97–100%, Instructional 90–96%, Frustration 89% and below · Speed = '.$words.' ÷ seconds × 60.</small></p></details></article></template>';continue;}
  $sheet=null;
  if($sheet!==null){echo '<div class="demo-l5">'.str_replace('<img src=','<img data-src=',$sheet).'</div></article></template>';continue;}
  ob_start();render_activity_visuals($a);$visual=ob_get_clean();echo str_replace('<img src=','<img data-src=',$visual);
  if($worksheet)echo '<details class="demo-worksheet-directions"><summary>Activity directions</summary>';
  $grid=level3_reading_grid($a);echo $grid!==null?'<div class="demo-word-grid">'.$grid.'</div>':'<div class="demo-prompt">'.nl2br(e($level===6?level5_reflow($a['prompt']):$a['prompt'])).'</div>';
  if($worksheet)echo '</details>';
  if($a['type']==='choice'&&!empty($choices[(int)$a['id']])){echo '<ul class="demo-choices">';foreach($choices[(int)$a['id']] as $choice)echo '<li>'.e($choice).'</li>';echo '</ul>';}
  echo '</article></template>';
 }
 echo '</div>';foot();
}

/** "Lesson plan" side panel in Class Demo: the goals, the official module pages and the PDF for the lesson on screen.
    One small template per lesson; the full lesson-plan text opens on its own page (text version). */
function demo_lesson_plans(array $slides,int $level,int $grade,string $mlabel):string{
 $ids=array_values(array_unique(array_map(fn($a)=>(int)$a['lesson_id'],$slides)));if(!$ids)return '';
 $hasSrc=$level===1||module_source_meta($level,$grade?:null)!==null;
 $rows=rows('SELECT id,title,subtitle,position,objectives,source_pages FROM lessons WHERE id IN ('.implode(',',$ids).')');
 $h='<div class="plan-ov" data-plan hidden><aside class="plan-panel" role="dialog" aria-modal="true" aria-labelledby="plan-title"><button type="button" class="plan-x" data-plan-close aria-label="Close the lesson plan">'.icon('close').'</button><div class="plan-body"></div></aside></div>';
 foreach($rows as $l){$obj=trim((string)$l['objectives']);
  $items=[];
  if(preg_match_all('~(?:^|\s)\d{1,2}\.\s+~',$obj)>=2){$pos=strpos($obj,':');$items=array_map(fn($x)=>rtrim(trim($x),' ;.'),preg_split('~(?:^|\s)\d{1,2}\.\s+~',$pos!==false&&$pos<160?substr($obj,$pos+1):$obj,-1,PREG_SPLIT_NO_EMPTY));}
  $h.='<template class="plan-tpl" data-lesson="'.(int)$l['id'].'"><span class="eyebrow">LESSON PLAN · '.e(strtoupper($mlabel.' · '.lesson_display_label($level,(int)$l['position']))).'</span><h2 id="plan-title">'.e((string)$l['subtitle']).'</h2><p class="plan-sub">'.e((string)$l['title']).'</p>';
  if($obj!==''){$h.='<section class="plan-sec"><h3>'.icon('flag').($items?'By the end of the lesson, pupils can:':'Lesson goal').'</h3>';$h.=$items?'<ol>'.implode('',array_map(fn($x)=>'<li>'.e($x).'</li>',$items)).'</ol>':'<p>'.e($obj).'</p>';$h.='</section>';}
  $pages=$hasSrc?(json_decode((string)$l['source_pages'],true)?:[]):[];
  if($pages){$h.='<section class="plan-sec"><h3>'.icon('book').'Official module pages</h3><div class="plan-pages">';foreach($pages as $n)$h.='<a class="plan-pg" href="?page=source&amp;level='.$level.($grade?'&amp;grade='.$grade:'').'&amp;n='.(int)$n.'" target="_blank" rel="noopener">Page '.(int)$n.'</a>';$h.='</div><p class="plan-note">Opens the real page of the DepEd module, with its tables and pictures.</p></section>';}
  $h.=($hasSrc?'<a class="btn primary plan-dl" href="?page=download_source&amp;level='.$level.($grade?'&amp;grade='.$grade:'').'">'.icon('download').'Download the '.e($mlabel).' module (PDF)</a>':'').'<a class="btn secondary plan-txt" href="?page=guide&amp;id='.(int)$l['id'].'" target="_blank" rel="noopener">'.icon('book').'Text version of the lesson plan</a></template>';}
 return $h;
}
