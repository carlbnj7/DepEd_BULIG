<?php
/** Shared native Level 2 content. Original page images are source references only. */
function level2_card_manifest():array{
 static $m=null;
 if($m===null){$f=__DIR__.'/../database/level2-cards.json';$m=is_file($f)?json_decode(file_get_contents($f),true):[];}
 return $m['pages']??[];
}
function level2_activity_context(array $a):?array{
 static $lessons=null;
 if($lessons===null){$lessons=[];foreach(rows('SELECT l.id,l.position,m.level_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id IN (2,3,4,6,7)') as $l)$lessons[(int)$l['id']]=$l;}
 return $lessons[(int)($a['lesson_id']??0)]??null;
}
function level2_card_set(array $a):?array{
 $l=level2_activity_context($a);if(!$l)return null;
 // Level 5 story pages (nothing to answer) are shown as cards too: the pictures one by one, the text as text
 if(($a['response_mode']??'none')==='none'&&(int)$l['level_id']!==6)return null;
 if((int)$l['level_id']===4)return level3_manifest()['cards'][(int)$l['position'].':'.$a['phase'].':'.(int)$a['position']]??null;
 if((int)$l['level_id']===7){$d=level6_cards()[lesson_grade((int)$a['lesson_id']).':'.(int)$l['position'].':'.(int)$a['position']]??null;return $d?['title'=>$a['title'],'instruction'=>$d['instruction']?:$a['instructions']]+$d:null;}
 if((int)$l['level_id']===6){$d=level5_cards()[lesson_grade((int)$a['lesson_id']).':'.(int)$a['source_page']]??null;return $d?['title'=>$a['title'],'instruction'=>$d['instruction']?:$a['instructions']]+$d:null;}
 $key=($l['level_id']==2?'2a':'2b').':'.(int)$a['source_page'];
 if($l['level_id']==3&&(int)$l['position']===12&&(int)$a['source_page']===42)$key.=':continuation';
 return level2_card_manifest()[$key]??null;
}
function level2_native_images(array $a):?array{
 $l=level2_activity_context($a);if(!$l)return null;
 $set=level2_card_set($a);
 if(in_array((int)$l['level_id'],[4,6,7],true)){if(!$set)return null;$imgs=[];foreach($set['cards'] as $c)foreach($c['images'] as $im)$imgs[]=$im['src'];return array_values(array_unique($imgs));}
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
/** Matching-type card: Column A (numbered items with pictures) beside Column B (lettered meanings). */
function render_matching_card(array $card,bool $lazy,bool $answer):void{
 $items=[];foreach(preg_split('/\n/',$card['text']) as $l){$l=trim($l);if($l===''||strcasecmp($l,'Column A')===0)continue;$items[]=$l;}
 $pics=[];foreach($card['images'] as $im)$pics[trim($im['label'])][]=$im;
 $letters=[];foreach($card['choices'] as $c)if(preg_match('/^\s*([a-zA-Z])\s*[.)]/',$c,$m))$letters[]=strtolower($m[1]);
 echo '<p class="match-help">'.($answer?'Tap a word in Column A, then tap its match in Column B to draw a line. Tap a line’s word again to remove it.':'Tap a word in Column A, then its match in Column B to draw a line together.').'</p><div class="match-board" data-match-board'.($answer?' data-answer="on"':'').'><svg class="match-lines" aria-hidden="true"></svg><div class="match-col match-a"><h3>'.e($card['col_a']??'Column A').'</h3>';
 foreach($items as $i=>$it){preg_match('/^(\d+)/',$it,$n);$num=$n[1]??(string)($i+1);$word=trim(preg_replace('/^\d+\s*[.)]\s*/','',$it));
  echo '<div class="match-item" data-num="'.e($num).'" tabindex="0" role="button" aria-label="Item '.e($num).($word!==''?' '.e($word):'').'"><span class="match-num">'.e($num).'</span>';
  foreach($pics[$it]??[] as $im){$src=resolved_image_path($im['src']);if($src)echo '<img '.($lazy?'data-src':'src').'="'.e($src).'" alt="'.e($word!==''?$word:'Picture '.$num).'"'.($lazy?'':' loading="lazy"').'>';}
  if($word!=='')echo '<span class="match-word">'.e($word).'</span>';
  if($answer&&$letters){echo '<label class="match-pick"><span class="sr-only">Answer for '.e($num).'</span><select data-match="'.e($num).'"><option value="">?</option>';foreach($letters as $L)echo '<option value="'.$L.'">'.$L.'</option>';echo '</select></label>';}
  echo '</div>';}
 echo '</div><div class="match-col match-b"><h3>'.e($card['col_b']??'Column B').'</h3>';
 foreach($card['choices'] as $c){$ci=$card['choice_images'][trim(preg_replace('/^\s*([a-zA-Z][.)]).*$/s','$1',$c))]??null;$src=$ci?resolved_image_path($ci):null;
  $L=strtolower(trim(preg_replace('/^\s*([a-zA-Z])[.)].*$/s','$1',$c)));
  echo '<div class="match-choice'.($src?' has-pic':'').'" data-letter="'.e($L).'" tabindex="0" role="button" aria-label="Choice '.e($c).'">'.($src?'<img '.($lazy?'data-src':'src').'="'.e($src).'" alt="Picture '.e($c).'">':'').'<span>'.e($c).'</span></div>';}
 echo '</div></div>';
}
function render_level2_card(array $card,bool $lazy=false,bool $answer=false):void{
 if(($card['lead']??'')!=='')echo '<p class="native-lead">'.nl2br(e($card['lead'])).'</p>';
 if(($card['title']??'')==='Matching'){render_matching_card($card,$lazy,$answer);return;}
 // Level 6 story cards: picture on the left, the story (and its key words, timer) on the right
 $split=!empty($card['split'])&&$card['images'];
 if($split){echo '<div class="story-split"><div class="story-pic">';render_card_pictures($card,$lazy,true);echo '</div><div class="story-text">';}
 // a story, passage or long direction is read at normal size; a short question stays large
 $passage=($card['title']??'')==='Read'||mb_strlen($card['text'])>230||substr_count($card['text'],"\n")>=4;
 if(($card['heading']??'')!==''||$card['text']!==''){
  echo '<div class="'.($passage?'native-passage':'native-question').'">';
  if(($card['heading']??'')!=='')echo '<h3 class="native-heading">'.e($card['heading']).'</h3>';
  if($card['text']!=='')echo $passage?'<p>'.nl2br(e($card['text'])).'</p>':nl2br(e($card['text']));
  echo '</div>';
 }
 if(!empty($card['grid'])){
  echo '<div class="letter-grid-wrap"><table class="letter-grid" data-letter-grid aria-label="Letter puzzle: tap letters to mark a word">';
  foreach($card['grid'] as $row){echo '<tr>';foreach(preg_split('//u',$row,-1,PREG_SPLIT_NO_EMPTY) as $ch)echo $ch===' '||$ch==='.'?'<td class="blank"></td>':'<td><button type="button" class="grid-cell">'.e($ch).'</button></td>';echo '</tr>';}
  echo '</table></div>';
 }
 if(!empty($card['speed']))echo '<div class="speed-timer" data-speed-timer data-words="'.(int)$card['speed'].'"><p class="speed-help">Reading for speed · '.(int)$card['speed'].' words. Tap <strong>Start</strong> when your teacher says “Go”, read the story aloud, then tap <strong>Done</strong>.</p><button type="button" class="btn primary" data-speed-start>Start</button><button type="button" class="btn secondary" data-speed-stop hidden>Done</button><output class="speed-clock" aria-live="polite">0:00</output><span class="speed-result" role="status"></span></div>';
 if(!empty($card['words'])&&!empty($card['words_label']))echo '<p class="word-bank-label">'.e($card['words_label']).'</p>';
 if(!empty($card['words'])){echo '<ul class="word-bank" aria-label="Words">';foreach($card['words'] as $w)echo '<li>'.e($w).'</li>';echo '</ul>';}
 if($split)echo '</div></div>';
 elseif($card['images'])render_card_pictures($card,$lazy,false);
 if($card['choices']){echo '<ul class="native-choices" aria-label="Choices">';foreach($card['choices'] as $ch)echo '<li>'.e($ch).'</li>';echo '</ul>';}
}
function render_card_pictures(array $card,bool $lazy,bool $split):void{
 echo '<div class="native-pictures '.(count($card['images'])===1?'single-picture':'').(!empty($card['figure'])?' worksheet-figure':'').'">';
 foreach($card['images'] as $i=>$im){$src=resolved_image_path($im['src']);echo '<figure>';
  if($src)echo '<a href="'.e($src).'" target="_blank" rel="noopener" aria-label="Enlarge picture '.($i+1).'"><img '.($lazy?'data-src':'src').'="'.e($src).'" alt="'.e($im['alt']).'"'.($lazy?'':' loading="lazy"').' data-activity-image="native"></a>';
  else echo '<p class="visual-notice" role="alert">Picture unavailable. Please ask your teacher to upload the complete update.</p>';
  $cap=$im['label']?:($split?'':(count($card['images'])>1?'Picture '.($i+1):''));
  if($cap!=='')echo '<figcaption>'.e($cap).'</figcaption>';
  echo '</figure>';
 }echo '</div>';
}
function render_level2_pupil(array $set,string $response,bool $readonly,string $mode):void{
 $response=str_replace(["\r\n","\r"],"\n",$response);
 $answers=[];$legacy=$response;
 if(str_starts_with($response,"BULIG card answers\n")){
  $parts=explode("\nEarlier answer: ",substr($response,19),2);$legacy=$parts[1]??'';foreach(preg_split('/\n(?=Card [0-9]+:)/',$parts[0]) as $line)if(preg_match('/^Card ([0-9]+):\s?(.*)$/s',$line,$m))$answers[(int)$m[1]]=$m[2];
 }
 echo '<section class="native-deck" data-native-deck data-readonly="'.($readonly?'yes':'no').'" data-earlier-answer="'.e($legacy).'"><div class="native-deck-heading"><span class="eyebrow">LOOK · LISTEN · TRY</span><span class="native-counter" role="status" aria-live="polite">Card 1 of '.count($set['cards']).'</span></div><p class="native-direction">'.e($set['instruction']).'</p>';
 foreach($set['cards'] as $i=>$c){echo '<section class="native-card" data-card="'.$i.'" data-narration="'.e($c['narration']).'" '.($i?'hidden':'').'><span class="native-item-label">'.e($c['title']).'</span>';render_level2_card($c,false,!$readonly&&$mode!=='perform');
  if($mode!=='perform'&&$mode!=='none')echo '<label class="native-answer-label" for="native-answer-'.($i+1).'">Your answer<textarea id="native-answer-'.($i+1).'" class="native-answer" data-number="'.($i+1).'" rows="2" maxlength="1000" placeholder="Type your answer or use Speak Answer" '.($readonly?'readonly':'').'>'.e($answers[$i+1]??'').'</textarea></label>';
  echo '</section>';
 }
 echo '<nav class="native-card-nav" aria-label="Activity cards"><button type="button" class="btn secondary" data-native-prev disabled>Previous picture / question</button><button type="button" class="btn primary" data-native-next>Next picture / question '.icon('arrow').'</button></nav><p class="native-save-hint">'.($readonly?'Your saved answers are shown with each card.':'Move between cards at your own pace. Submit the activity when you have finished.').'</p></section>';
 if($mode!=='none')echo '<details class="native-all-answers" '.($legacy!==''?'open':'').'><summary>'.($legacy!==''?'Previously saved answer':'All answers').'</summary><label for="response">Your complete response<textarea id="response" name="response" rows="4" maxlength="12000" '.($readonly?'readonly':'').'>'.e($response).'</textarea></label><small>Answers entered on the cards are collected here.</small></details>';
}
/** Level 6: a story card, then one card per question (tools/level6/build.py). */
function level6_cards():array{static $m;if($m===null){$f=__DIR__.'/../database/level6-cards.json';$m=is_file($f)?(json_decode(file_get_contents($f),true)['cards']??[]):[];}return $m;}
/** Level 5: one card per numbered item of each module page (tools/level5/build_cards.py). */
function level5_cards():array{static $m;if($m===null){$f=__DIR__.'/../database/level5-cards.json';$m=is_file($f)?(json_decode(file_get_contents($f),true)['cards']??[]):[];}return $m;}
