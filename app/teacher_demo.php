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
   echo '<article class="level-card '.($ready?'available':'unavailable').'"><div class="level-cover"><span class="level-number">'.e(str_replace('Level ','',level_label((int)$l['id']))).'</span><span class="cover-symbol">'.icon($ready?'book':'clock').'</span></div><div class="level-card-content"><h2>'.e(level_label((int)$l['id'])).'</h2><p>'.e($ready?$l['title']:'Content is being prepared.').'</p>';
   if($ready)echo '<small>'.$l['slide_count'].' slides · All activities open</small><a class="btn primary" href="?page=class_demo&amp;level='.$l['id'].'">Start class demo '.icon('arrow').'</a>';
   else echo '<span class="level-lock">Coming soon</span>';
   echo '</div></article>';
  }
  echo '</div><section class="card"><h2>Ready for the big screen</h2><p>Connect your computer to the TV, open a level, then choose Full screen. Use the arrow keys or Previous and Next. You can jump to any learning step or slide. Listen uses the same narration as the pupil activity. You decide how to discuss and check answers.</p><p>Demo mode does not save pupil answers, scores, XP, or progress. Levels 3–7 will appear here when their pupil content is published.</p></section>';shell_end();return;
 }
 $info=one('SELECT * FROM bulig_levels WHERE id=? AND published=1',[$level]);
 if(!$info)fail('This level has no published demo content yet.',404);
 $slides=rows("SELECT a.*,l.title lesson_title,l.subtitle,l.position lesson_position FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1 AND a.published=1 ORDER BY l.position,l.id,FIELD(a.phase,'pre','learn','post'),a.position,a.id",[$level]);
 if(!$slides)fail('This level has no published demo content yet.',404);
 $index=0;$requested=(int)($_GET['activity']??0);
 if($requested){$found=false;foreach($slides as $i=>$a)if((int)$a['id']===$requested){$index=$i;$found=true;break;}if(!$found)fail('That slide does not belong to this published level.',404);}
 $choices=[];foreach(rows('SELECT an.activity_id,ans.content FROM answers ans JOIN questions an ON an.id=ans.question_id JOIN activities a ON a.id=an.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND a.type=\'choice\' ORDER BY ans.id',[$level]) as $c)$choices[(int)$c['activity_id']][]=$c['content'];
 head('Class Demo · '.level_label($level),'demo-page');
 echo '<div id="class-demo" data-level="'.$level.'" data-start="'.$index.'"><header class="demo-header"><a class="demo-logo" href="?page=class_demo"><img src="assets/bulig-logo.png" alt="BULIG"></a><div><strong>'.e(level_label($level)).' · Class Demo</strong><span>Listen, discuss, and learn together</span></div><a class="btn quiet" href="?page=class_demo">Exit demo</a><button class="btn secondary" type="button" id="demo-fullscreen">Full screen</button></header><div class="demo-controls"><label>Learning step<select id="demo-lesson">';
 $seen=[];foreach($slides as $i=>$a){if(isset($seen[$a['lesson_id']]))continue;$seen[$a['lesson_id']]=true;echo '<option value="'.$i.'">'.e($a['lesson_position'].' · '.$a['subtitle']).'</option>';}
 echo '</select></label><label>Jump to slide<select id="demo-jump">';foreach($slides as $i=>$a)echo '<option value="'.$i.'">'.e(($i+1).' · '.$a['subtitle'].' · '.ucfirst($a['phase']).' · '.$a['title']).'</option>';echo '</select></label><button type="button" class="btn quiet" id="demo-zoom" aria-pressed="false">Enlarge pictures</button></div>';
 narration_controls($slides[$index]['narration']);
 echo '<main id="demo-stage" class="demo-stage" tabindex="-1" aria-label="Class activity slide"></main><footer class="demo-footer"><button class="btn secondary" id="demo-prev" type="button">Previous</button><div><strong id="demo-counter" role="status" aria-live="polite"></strong><small id="demo-help">Arrow keys move between slides · F for full screen</small></div><button class="btn primary" id="demo-next" type="button">Next '.icon('arrow').'</button></footer>';
 foreach($slides as $i=>$a){
  $worksheet=in_array($level,[2,3],true)&&$a['response_mode']==='drawing'&&count(activity_images($a))===1;
  $articleClass=$worksheet?'demo-worksheet':(activity_images($a)?'demo-has-visual':'demo-text-slide');
  $instructions=$a['instructions'];if($instructions==='Share your answer by speaking or typing. Then choose Submit Answer.')$instructions='Discuss your answers together. Your teacher will choose Next.';
  echo '<template class="demo-template" data-id="'.$a['id'].'" data-lesson="'.$a['lesson_id'].'" data-narration="'.e($a['narration']).'"><article class="'.$articleClass.'"><div class="demo-slide-meta">'.e($a['subtitle'].' · '.['pre'=>'Before we begin','learn'=>'Let’s practice','post'=>'Show what you learned'][$a['phase']]).'</div><h1>'.e($a['title']).'</h1><p class="demo-instructions">'.e($instructions).'</p>';
  // Same shared image resolver/renderer used by the pupil view, including missing-image notices.
  ob_start();render_activity_visuals($a);$visual=ob_get_clean();echo str_replace('<img src=','<img data-src=',$visual);
  if($worksheet)echo '<details class="demo-worksheet-directions"><summary>Activity directions</summary>';
  echo '<div class="demo-prompt">'.nl2br(e($a['prompt'])).'</div>';
  if($worksheet)echo '</details>';
  if($a['type']==='choice'&&!empty($choices[(int)$a['id']])){echo '<ul class="demo-choices">';foreach($choices[(int)$a['id']] as $choice)echo '<li>'.e($choice).'</li>';echo '</ul>';}
  echo '</article></template>';
 }
 echo '</div>';foot();
}
