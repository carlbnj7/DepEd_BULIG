<?php
/* Verified against Level 2A PDF pp. 9,34; Level 3 pp. 76-77;
   Level 5 Grade 1 p.18. Card indexes are retained for saved answers. */
/* v211 The words on each Level 3 blend chart, copied from the chart pictures (assets/images/level3/l12–l18), in chart order.
   Max (Word Coach) listens for these words. Words printed twice on a chart are read twice. */
const MODULE_BLEND_CHARTS=[
 '13:learn:2'=>['cr- and cl-','crab crack cracker craft crane crash crawl crayon crazy cream creature creep crew cricket crocodile cross crow crowd crown crust claim clam clamp clap clarify clash clasp class clatter claw clay clean clear clerk clever click cliff climb clip clock close closet cloud clown club clue'],
 '14:learn:2'=>['dr-','dress drag dry dragon dream drama drink draw drill drift drop drool drum drain drank'],
 '15:learn:2'=>['fr- and fl-','fry fresh from frog front friend frank free frost fruit frame frail fright Friday frame flag flat flu flip fly floor flower flush flake flip flesh flock flute flea flavor'],
 '16:learn:2'=>['gr- and gl-','grab grip grow grace grand grill grade graph growl grape greed graft group green grass glee glum glory glitter globe glad glass glance glare glove glue glitch glow gloss glossy gloom glider glasses glacier gladiator'],
 '17:post:2'=>['pl- and pr-','plum plan play please place plane plate plot plug plus prize press price pride proud prince prank pray print prove'],
 '19:post:2'=>['sh- and sl-','sheep sheet shoot shook shoe shield shout show should shore share shared shark sharp shirt shred shrimp shrink shrub shrug slab slam slip slap sly slang slippers sleep slice slump slim slot slit slide sled slug slop slept slow slime'],
];
function module_activity_scope(array $a):string{
 $lid=(int)($a['lesson_id']??0);$lv=lesson_level($lid);$page=(int)($a['source_page']??0);
 if($lv===2&&in_array($page,[9,34],true))return '2a:'.$page;
 if($lv===6&&lesson_grade($lid)===1&&$page===18)return 'body';
 if(in_array($lv,[4,9],true)&&isset(MODULE_BLEND_CHARTS[l3_key($a)]))return 'blends:'.l3_key($a);   // v211 every blend word chart (Level 3 lessons 13 to 19)
 return '';
}
/* v211 Cards the module printed with no right answer (or a word box that does not fit), fixed so every tapped card has
   one correct choice and an answer key (database/keys2). Key: activity key id => card number (1 = first card) => changes. */
const MODULE_CARD_FIXES=[
 '2:0:1:pre:16'=>[4=>['choices'=>['/t/','/d/','/r/']]],                                   // tee + /r/ = tree (the picture is a tree)
 '2:0:9:learn:3'=>[6=>['text'=>'way · key · day','choices'=>['way','key','day']]],          // way and day share /ā/; key is different
 '3:0:21:learn:2'=>[4=>['text'=>'Seven, eight, I am ___.','choices'=>['all','late','you']]],
 '3:0:23:learn:2'=>[1=>['choices'=>['van','bob','can','pan']],2=>['choices'=>['fin','win','hot','pin']],3=>['choices'=>['hot','pot','rat','cot']]],
 '4:0:9:learn:4'=>[1=>['say'=>'pack'],2=>['say'=>'peck'],3=>['say'=>'neck'],4=>['say'=>'hack'],5=>['say'=>'deck']],
 '4:0:16:post:4'=>[6=>['text'=>'Which blend does this picture begin with? The boy grips his fishing rod: grip.'],8=>['text'=>'Which blend does this picture begin with? He gives money as a grant: grant.']],
 '6:1:23300:post:4'=>[3=>['text'=>"(Read Aloud) “My Things” These are my things. I will take care of my things. I will keep it clean.\nWhat should you do with your things?",'choices'=>['a. Break them','b. Take care of them','c. Make it tidy']]],
 '6:4:23220:learn:1'=>[9=>['text'=>'______ farmers are not interested in the new farming.']],
 '6:4:24300:post:5'=>[2=>['text'=>'What should people NOT do to animals?','choices'=>['A. Feed them','B. Kill them','C. Care for them','D. Watch them']]],
 '6:6:12211:learn:1'=>[3=>['text'=>'The ______ of 2 and 3 is 5.'],5=>['text'=>'I have a great ______ in reading books.']],
 '6:6:20219:learn:1'=>[2=>['text'=>'My mother bought ______ fruits: oranges, mangoes and bananas.'],3=>['text'=>'The ants’ ______ is under the ground.'],4=>['text'=>'It is hard to ______ to a new school, but we can do it.'],5=>['text'=>'Seeing a rainbow at night is a rare ______ .'],6=>['text'=>'He looks at the ______ to count the days of the month.'],7=>['text'=>'You are so ______ ; think first before you act.'],8=>['text'=>'The book has a ______ about plants and another about animals.'],9=>['text'=>'Farmers ______ new ways of farming.'],10=>['text'=>'I was ______ when I saw my name on the blackboard.'],11=>['text'=>'Please ______ the seven colors of the rainbow.'],12=>['text'=>'Some good ______ in our body help us digest food.'],13=>['text'=>'The thief tried to ______ the bag from her hand.'],14=>['text'=>'A ______ is a tiny blood vessel in my arm.'],15=>['text'=>'Please ______ the sound of the echo.'],16=>['text'=>'Oh my! I don’t have ______ to the internet.']],
 '6:6:22300:post:2'=>[2=>['choices'=>['a) tail','b) nose','c) ears']]],
 '8:1:33233:learn:1'=>[4=>['choices'=>['A. They helped Marina water the plants.','B. They gave a party in school for Marina.','C. They smelled the flowers Marina gave them.']]],
];
function module_card_fixes(array $a,array $s):array{
 $id=function_exists('activity_key_id')?activity_key_id($a):null;if(!$id||!isset(MODULE_CARD_FIXES[$id]))return $s;
 foreach(MODULE_CARD_FIXES[$id] as $n=>$f){$i=$n-1;if(!isset($s['cards'][$i]))continue;$c=&$s['cards'][$i];
  if(isset($f['text']))$c['text']=$f['text'];
  if(isset($f['choices'])){$c['choices']=$f['choices'];if(isset($c['ui']['choices']))$c['ui']['choices']=$f['choices'];}
  if(isset($f['say'])){$c['text']='Listen. Tap the word you hear.';$c['speak']=$f['say'].'. '.$f['say'].'.';$c['narration']=$c['speak'];}
  if(isset($f['text'])||isset($f['say'])){if(isset($c['ui']['text']))$c['ui']['text']=$c['text'];if(empty($f['say'])){$c['speak']=$c['text'];$c['narration']=$c['text'];}}
  unset($c);}
 return $s;
}
function module_activity_set(array $a,?array $s):?array{
 if(!$s)return $s;$s=module_card_fixes($a,$s);$scope=module_activity_scope($a);
 if($scope==='body'){
  $s['instruction']='Listen to the body-part name. Tap that part on the picture. Then tap Next card.';
  foreach($s['cards'] as &$c){$c['ui']=['answer'=>['chip'],'body'=>true];$c['choices']=['Nose','Head','Arm','Eye','Mouth'];$c['speak']=$c['text'];}unset($c);
 }elseif($scope==='2a:9'){
  $s['instruction']='Name each picture. Follow the direction on each card and tap one answer.';
  $names=['sun, sack, soap','fan, foot, fin','bus, bat, bed','head, hat, ham','soup, seed, sip','pit','jam','pen'];
  foreach($s['cards'] as $i=>&$c){$c['text']=$i<5?'Part A: Beginning sounds. What sound starts all three picture names? Tap its letter.':'Part B: Middle sounds. Say the picture name. Tap the letter sound you hear in the middle.';
   $c['ui']=['answer'=>['chip']];$c['choices']=$i<5?str_split('abcdefghijklmnopqrstuvwxyz'):['/a/','/e/','/i/','/o/','/u/'];
   $c['speak']=$c['text'].' Picture'.($i<5?'s':'').': '.$names[$i].'.';$c['narration']=$c['speak'];
  }unset($c);
 }elseif($scope==='2a:34'){
  $s['instruction']='The middle sound is the sound between the first and last sounds. Look at the example. Then name each picture and tap its middle sound.';
  $names=['mat','pan','bat','rat','lap','tap','bat','cat','can','mat'];
  foreach($s['cards'] as $i=>&$c){
   if($i===0){$c['title']='Example';$c['text']='Example: mat. The sounds are /m/ /a/ /t/. The middle sound is /a/.';$c['choices']=[];$c['ui']=['answer'=>['look']];}
   else{$c['text']='Say “'.$names[$i].'”. What sound do you hear in the middle?';$c['choices']=['/a/','/e/','/i/','/o/','/u/'];$c['ui']=['answer'=>['chip']];}
   $c['speak']=$c['text'];$c['narration']=$c['text'];
  }unset($c);
 }elseif(str_starts_with($scope,'blends:')){
  [$sound,$words]=MODULE_BLEND_CHARTS[substr($scope,7)];$words=explode(' ',$words);
  $s['instruction']='Read the '.$sound.' words aloud. Scroll down to see every word. Tap the microphone when you are ready.';
  $s['cards']=[['title'=>'1','text'=>implode("\n",$words),'images'=>[],'choices'=>[],'narration'=>implode(', ',$words),'ui'=>['answer'=>['say'],'grid'=>$words,'module_chart'=>true]]];
 }
 return $s;
}
function module_activity_keys(array $a,array $keys):array{
 $scope=module_activity_scope($a);$answers=[];
 if($scope==='body')$answers=[1=>'Nose',2=>'Head',3=>'Arm',4=>'Eye',5=>'Mouth'];
 if($scope==='2a:34'){$answers=array_fill(2,9,'/a/');unset($keys[1]);}
 foreach($answers as $n=>$v)$keys[$n]=['t'=>'card','alts'=>[[['k'=>'eq','v'=>strtolower($v)]]],'w'=>$v];
 return $keys;
}
/* Standard chip buttons preserve the existing answer saving and feedback. */
function module_body_answer(int $part):string{
 $h='<div class="body-answer" data-kind="chip" data-part="'.$part.'" role="group" aria-label="Choose a body part"><div class="body-map">';
 $h.='<img src="assets/images/characters/boy.webp" width="337" height="600" alt="Boy pupil in a white shirt and green school uniform" draggable="false">';
 foreach(['head'=>'Head','eye-left'=>'Eye','eye-right'=>'Eye','nose'=>'Nose','mouth'=>'Mouth','arm-left'=>'Arm','arm-right'=>'Arm'] as $pos=>$name)$h.='<button type="button" class="body-hit body-'.$pos.'" data-val="'.$name.'" aria-label="'.$name.'" aria-pressed="false"><span class="sr-only">'.$name.'</span></button>';
 return $h.'</div><p class="l2-how">Tap a body part. Your selection gets a blue outline.</p></div>';
}
