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
 $h.='<svg viewBox="0 0 320 520" aria-hidden="true" focusable="false"><g stroke="#2a1d16" stroke-width="4" stroke-linejoin="round"><path fill="#ad7552" d="M139 187h42v55h-42z"/><path fill="#cc946b" d="M91 232Q72 224 58 249L24 338q-7 24 12 29 17 4 24-16l48-91M229 232q19-8 33 17l34 89q7 24-12 29-17 4-24-16l-48-91"/><path fill="#cc946b" d="M104 359h44v118h-44zm68 0h44v118h-44z"/><path fill="#1686ce" d="M111 219l28-10q21 17 42 0l28 10 31 23-24 41-15-9 7 83h-96l7-83-15 9-24-41z"/><path fill="#164c78" d="M112 347h96l9 60h-51l-6-34-6 34h-51z"/><path fill="#ffcd42" d="M105 465h43v30H84q-6-23 21-30zm67 0h43q27 7 21 30h-64z"/><ellipse fill="#cc946b" cx="160" cy="118" rx="83" ry="91"/><path fill="#332820" d="M78 102Q65 22 133 20q53-32 99 22l13 62-34-42q-26 22-66 14l-30-10z"/><ellipse fill="white" cx="122" cy="117" rx="18" ry="23"/><ellipse fill="white" cx="198" cy="117" rx="18" ry="23"/><ellipse fill="#2a1d16" cx="124" cy="119" rx="9" ry="14"/><ellipse fill="#2a1d16" cx="196" cy="119" rx="9" ry="14"/><path fill="none" d="M154 141q-11 17 10 15"/><path fill="white" d="M133 173q27 11 54 0-27 39-54 0z"/></g><g fill="white"><circle cx="121" cy="113" r="4"/><circle cx="193" cy="113" r="4"/></g></svg>';
 foreach(['head'=>'Head','eye-left'=>'Eye','eye-right'=>'Eye','nose'=>'Nose','mouth'=>'Mouth','arm-left'=>'Arm','arm-right'=>'Arm'] as $pos=>$name)$h.='<button type="button" class="body-hit body-'.$pos.'" data-val="'.$name.'" aria-label="'.$name.'" aria-pressed="false"><span class="sr-only">'.$name.'</span></button>';
 return $h.'</div><p class="l2-how">Tap a body part. Your selection gets a blue outline.</p></div>';
}
