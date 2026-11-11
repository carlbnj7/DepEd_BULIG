<?php
require __DIR__.'/activity-module-fixes.php';
function one($sql,$args=[]){return null;}
$j=json_decode(file_get_contents(__DIR__.'/../database/level5-cards.json'),true);$total=0;
foreach(['5:6','5:7','5:23','5:25','5:26','5:36','5:37'] as $id){foreach($j['cards'][$id]['cards'] as $c){
 $word=$c['underline'];verify(str_contains($c['text'],$word),'Target occurs in sentence');
 verify(str_contains(l2a_text($c['text'],false,$word),'<u>'.$word.'</u>'),'Pupil underline rendered');
 $set=['module_title'=>'Vocabulary','instruction'=>'Find the underlined word'];
 verify(str_contains(l5_demo_card(['id'=>999999,'lesson_id'=>161],$set,$c,1,''),'<u>'.$word.'</u>'),'Teacher demo underline rendered');$total++;
}}
verify($total===57,'57 restored targets');
verify(l2a_underlined_text('<script>word</script>','word')==='&lt;script&gt;<u>word</u>&lt;/script&gt;','HTML safely escaped');
$j=json_decode(file_get_contents(__DIR__.'/../database/level3-cards.json'),true);
$c=$j['cards']['14:post:2']['cards'][1];verify($c['images'][0]['src']==='assets/images/level3/l13-assoc-06.webp','Drawing picture selected');verify(in_array('draw',$c['choices'],true),'Draw answer available');
$j=json_decode(file_get_contents(__DIR__.'/../database/level2-cards.json'),true);verify($j['pages']['2b:23']['cards'][3]['underline']==='toy','Rhyme target is toy');
echo "PASS: drawing picture/choice, 57 pupil and teacher underlines, HTML escaping, and toy rhyme target.\n";
