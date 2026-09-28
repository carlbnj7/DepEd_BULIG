<?php
/** Shared native Level 2 content. Original page images are source references only. */
function level2_card_manifest():array{
 static $m=null;
 if($m===null){$f=__DIR__.'/../database/level2-cards.json';$m=is_file($f)?json_decode(file_get_contents($f),true):[];}
 return $m['pages']??[];
}
function level2_activity_context(array $a):?array{
 static $lessons=null;
 if($lessons===null){$lessons=[];foreach(rows('SELECT l.id,l.position,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id IN (2,3)') as $l)$lessons[(int)$l['id']]=$l;}
 return $lessons[(int)($a['lesson_id']??0)]??null;
}
function level2_card_set(array $a):?array{
 if(($a['response_mode']??'none')==='none')return null;
 $l=level2_activity_context($a);if(!$l)return null;
 $key=($l['level_id']==2?'2a':'2b').':'.(int)$a['source_page'];
 if($l['level_id']==3&&(int)$l['position']===12&&(int)$a['source_page']===42)$key.=':continuation';
 return level2_card_manifest()[$key]??null;
}
function level2_native_images(array $a):?array{
 $l=level2_activity_context($a);if(!$l)return null;
 $set=level2_card_set($a);
 if(!$set){
  $key=$l['level_id']==2?'2a':'2b';$meta=level2_manifest()['levels'][(string)$l['level_id']];
  foreach($meta['lessons'] as $lesson)if((int)$lesson['position']===(int)$l['position']){
   foreach($lesson['worksheet_pages'] as $page){$s=level2_card_manifest()[$key.':'.$page]??[];foreach($s['cards']??[] as $c)if($c['images'])return [$c['images'][0]['src']];}
  }
  return [];
 }
 $imgs=[];foreach($set['cards'] as $c)foreach($c['images'] as $im)$imgs[]=$im['src'];return array_values(array_unique($imgs));
}
function level2_prompt_snapshot(array $a):string{
 $set=level2_card_set($a);if(!$set)return $a['prompt'];
 $s=$set['title']."\n".$set['instruction'];foreach($set['cards'] as $i=>$c)$s.="\nCard ".($i+1).': '.$c['text'].($c['choices']?' Choices: '.implode(', ',$c['choices']):'');return $s;
}
function render_level2_card(array $card,bool $lazy=false):void{
 if($card['text']!=='')echo '<div class="native-question">'.nl2br(e($card['text'])).'</div>';
 if($card['images']){
  echo '<div class="native-pictures '.(count($card['images'])===1?'single-picture':'').'">';
  foreach($card['images'] as $i=>$im){$src=resolved_image_path($im['src']);echo '<figure>';
   if($src)echo '<a href="'.e($src).'" target="_blank" rel="noopener" aria-label="Enlarge picture '.($i+1).'"><img '.($lazy?'data-src':'src').'="'.e($src).'" alt="'.e($im['alt']).'"'.($lazy?'':' loading="lazy"').' data-activity-image="native"></a>';
   else echo '<p class="visual-notice" role="alert">Picture unavailable. Please ask your teacher to upload the complete update.</p>';
   echo '<figcaption>'.e($im['label']?:((count($card['images'])>1?'Picture '.($i+1):''))).'</figcaption></figure>';
  }echo '</div>';
 }
 if($card['choices']){echo '<ul class="native-choices" aria-label="Choices">';foreach($card['choices'] as $ch)echo '<li>'.e($ch).'</li>';echo '</ul>';}
}
function render_level2_pupil(array $set,string $response,bool $readonly,string $mode):void{
 $response=str_replace(["\r\n","\r"],"\n",$response);
 $answers=[];$legacy=$response;
 if(str_starts_with($response,"BULIG card answers\n")){
  $parts=explode("\nEarlier answer: ",substr($response,19),2);$legacy=$parts[1]??'';foreach(preg_split('/\n(?=Card [0-9]+:)/',$parts[0]) as $line)if(preg_match('/^Card ([0-9]+):\s?(.*)$/s',$line,$m))$answers[(int)$m[1]]=$m[2];
 }
 echo '<section class="native-deck" data-native-deck data-readonly="'.($readonly?'yes':'no').'" data-earlier-answer="'.e($legacy).'"><div class="native-deck-heading"><span class="eyebrow">LOOK · LISTEN · TRY</span><span class="native-counter" role="status" aria-live="polite">Card 1 of '.count($set['cards']).'</span></div><p class="native-direction">'.e($set['instruction']).'</p>';
 foreach($set['cards'] as $i=>$c){echo '<section class="native-card" data-card="'.$i.'" data-narration="'.e($c['narration']).'" '.($i?'hidden':'').'><span class="native-item-label">'.e($c['title']).'</span>';render_level2_card($c);
  if($mode!=='perform')echo '<label class="native-answer-label" for="native-answer-'.($i+1).'">Your answer<textarea id="native-answer-'.($i+1).'" class="native-answer" data-number="'.($i+1).'" rows="2" maxlength="1000" placeholder="Type your answer or use Speak Answer" '.($readonly?'readonly':'').'>'.e($answers[$i+1]??'').'</textarea></label>';
  echo '</section>';
 }
 echo '<nav class="native-card-nav" aria-label="Activity cards"><button type="button" class="btn secondary" data-native-prev disabled>Previous picture / question</button><button type="button" class="btn primary" data-native-next>Next picture / question '.icon('arrow').'</button></nav><p class="native-save-hint">'.($readonly?'Your saved answers are shown with each card.':'Move between cards at your own pace. Submit the activity when you have finished.').'</p></section>';
 echo '<details class="native-all-answers" '.($legacy!==''?'open':'').'><summary>'.($legacy!==''?'Previously saved answer':'All answers').'</summary><label for="response">Your complete response<textarea id="response" name="response" rows="4" maxlength="12000" '.($readonly?'readonly':'').'>'.e($response).'</textarea></label><small>Answers entered on the cards are collected here.</small></details>';
}
