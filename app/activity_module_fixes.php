<?php
/* Verified against Level 2A PDF pp. 9,34; Level 3 pp. 76-77;
   Level 5 Grade 1 p.18. Card indexes are retained for saved answers. */
function module_activity_scope(array $a):string{
 $lid=(int)($a['lesson_id']??0);$lv=lesson_level($lid);$page=(int)($a['source_page']??0);
 if($lv===2&&in_array($page,[9,34],true))return '2a:'.$page;
 if($lv===6&&lesson_grade($lid)===1&&$page===18)return 'body';
 if(in_array($lv,[4,9],true)&&l3_key($a)==='13:learn:2')return 'blends';
 return '';
}
function module_activity_set(array $a,?array $s):?array{
 if(!$s)return $s;$scope=module_activity_scope($a);
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
 }elseif($scope==='blends'){
  $words=explode(' ','crab crack cracker craft crane crash crawl crayon crazy cream creature creep crew cricket crocodile cross crow crowd crown crust claim clam clamp clap clarify clash clasp class clatter claw clay clean clear clerk clever click cliff climb clip clock close closet cloud clown club clue');
  $s['instruction']='Read the cr- and cl- words aloud. Scroll down to see every word. Tap the microphone when you are ready.';
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
