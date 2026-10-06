<?php
/* Builds database/answer-keys.json from database/keys/*.txt and checks every answer against the real card.
   Run: php tools/keys/build.php   (needs the database, like the app) */
require __DIR__.'/../../app/bootstrap.php';
$keys=[];$errors=[];$notes=[];$count=0;
$acts=[];foreach(rows("SELECT a.*,l.position lpos,m.grade_level g,m.level_id lv FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id IN (6,7,8) AND a.published=1 AND l.published=1") as $a)$acts[$a['lv'].':'.$a['g'].':'.$a['lpos'].':'.$a['phase'].':'.$a['position']]=$a;
foreach(glob(__DIR__.'/../../database/keys/*.txt') as $file)foreach(file($file) as $ln=>$line){
 $line=rtrim($line);if($line===''||$line[0]==='#')continue;
 $note='';if(preg_match('~\s#\s~',$line,$mm,PREG_OFFSET_CAPTURE)){$p=$mm[0][1];$note=trim(substr($line,$p+2));$line=trim(substr($line,0,$p));}
 [$k,$rest]=array_map('trim',explode('|',$line,2)+[1=>'']);$where=basename($file).':'.($ln+1)." $k";
 if(!isset($acts[$k])){$errors[]="$where: no such activity";continue;}
 $set=l2a_set($acts[$k]);if(!$set){$errors[]="$where: no cards";continue;}
 foreach(preg_split('~\s+~',$rest,-1,PREG_SPLIT_NO_EMPTY) as $tok){
  if(!preg_match('~^(\d+)=(.+)$~',$tok,$m)){$errors[]="$where: cannot read '$tok'";continue;}
  $n=(int)$m[1];$v=$m[2];$flag=str_ends_with($v,'!');$v=rtrim($v,'!');$c=$set['cards'][$n-1]??null;
  if(!$c){$errors[]="$where: card $n does not exist";continue;}
  $kinds=$c['ui']['answer']??[];
  if(in_array('match',$kinds,true)){
   $ids=[];if(preg_match_all('~^\s*(\d+)\.~mu',(string)$c['match']['text'],$mm))$ids=$mm[1];
   $letters=array_map(fn($x)=>strtolower(preg_replace('~^\s*([A-Za-z])[.)].*$~su','$1',$x)),$c['match']['choices']);
   $pairs=[];foreach(explode(',',$v) as $pr){if(!preg_match('~^(\d+)([a-z])$~i',$pr,$q)){$errors[]="$where: card $n bad pair '$pr'";continue 2;}
    if(!in_array($q[1],$ids,true))$errors[]="$where: card $n item {$q[1]} is not in Column A";if(!in_array(strtolower($q[2]),$letters,true))$errors[]="$where: card $n letter {$q[2]} is not in Column B";$pairs[$q[1]]=strtolower($q[2]);}
   if(count($pairs)!==count($ids))$errors[]="$where: card $n has ".count($ids)." items but ".count($pairs)." answers";
   $keys[$k][$n]=['t'=>'match','a'=>$pairs];
  }elseif(!empty($c['ui']['labels'])&&count($kinds)>1){
   $nums=explode(',',$v);if(count($nums)!==count($c['ui']['labels']))$errors[]="$where: card $n needs ".count($c['ui']['labels'])." numbers";
   $keys[$k][$n]=['t'=>'order','a'=>array_combine(array_slice($c['ui']['labels'],0,count($nums)),array_slice($nums,0,count($c['ui']['labels'])))];
  }elseif($kinds===['chip']&&!empty($c['choices'])){
   $ch=$c['choices'];$picks=[];
   foreach(explode('/',$v) as $one){$pick=null;
    if($one!==''&&$one[0]==='#'){$pick=$ch[(int)substr($one,1)-1]??null;}
    else foreach($ch as $x){if(preg_match('~^\s*'.preg_quote($one,'~').'(?:[.)\s–-]|$)~iu',$x)||strcasecmp(trim($x),$one)===0){$pick=$x;break;}}
    if($pick===null){$errors[]="$where: card $n answer '$one' is not one of: ".implode(' | ',$ch);continue 2;}$picks[]=$pick;}
   $keys[$k][$n]=['t'=>'choice','a'=>$picks[0]]+(count($picks)>1?['alt'=>array_slice($picks,1)]:[]);
  }else{$errors[]="$where: card $n is not auto-markable";continue;}
  if($flag)$keys[$k][$n]['fix']=1;$count++;
 }
 if($note!=='')$notes[$k][]=$note;
}
ksort($keys,SORT_NATURAL);
file_put_contents(__DIR__.'/../../database/answer-keys.json',json_encode(['version'=>date('Y-m-d'),'keys'=>$keys,'notes'=>$notes],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo "answers $count, activities ".count($keys).", errors ".count($errors)."\n";foreach($errors as $e)echo "  ! $e\n";
