<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/coach.php';
$sets=json_decode(file_get_contents(__DIR__.'/../database/level7-cards.json'),true)['cards'];
$n=0;$grades=[];
foreach($sets as $key=>$set)foreach($set['cards'] as $c){
 if($c['title']!=='Read'||str_word_count($c['text'])<10)continue;
 $text=trim($c['text']);$c['ui']=['answer'=>['look'],'module_reading'=>$text];
 $out=wc_story(['cards'=>[$c]])['cards'][0];
 if(($out['ui']['wct']??'')!==$text||wc_display($text,$out['ui']['wct'])!==$text)throw new Exception("Truncated $key");
 $grades[explode(':',$key)[0]]=true;$n++;
}
if(count($grades)!==6||$n<100)throw new Exception('Incomplete grade coverage');
echo "PASS: $n complete reading cards across all six grades\n";
