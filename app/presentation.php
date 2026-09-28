<?php
/** Display-only labels and reviewed picture mappings; no database writes. */
function lesson_display_label(int $level,int $position):string{
 if(in_array($level,[2,3],true)){
  $lessons=level2_manifest()['levels'][(string)$level]['lessons']??[];
  foreach($lessons as $lesson)if((int)$lesson['position']===$position){
   if($lesson['phase']==='pre')return 'Pre-assessment';
   if($lesson['phase']==='post')return 'Post-assessment';
   $number=0;foreach($lessons as $item)if($item['phase']==='learn'&&(int)$item['position']<=$position)$number++;
   return 'Lesson '.$number;
  }
 }
 return 'Lesson '.$position;
}
function lesson_count_label(int $level,int $total):string{
 return in_array($level,[2,3],true)&&$total>2?($total-2).' lessons + pre/post assessments':$total.' lessons';
}
function level1_visual_manifest():array{
 static $m=null;if($m===null){$f=__DIR__.'/../database/level1-visuals-v11.json';$m=is_file($f)?json_decode(file_get_contents($f),true):[];}return $m??[];
}
function level1_visual_override(array $a):?array{
 $m=level1_visual_manifest()['activities'][(string)($a['id']??0)]??null;
 if(!$m||(int)($a['lesson_id']??0)!==(int)$m['lesson_id']||(int)($a['revision']??1)>1)return null;
 // Do not attach a stock picture to changed/custom text.
 if(!hash_equals($m['prompt_sha256'],hash('sha256',(string)($a['prompt']??''))))return null;
 return $m;
}
