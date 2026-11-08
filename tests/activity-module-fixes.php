<?php
function lesson_level(int $id):int{return [13=>2,15=>2,75=>9,160=>6,161=>6][$id]??1;}
function lesson_grade(int $id):int{return $id===161?2:1;}
function val(string $sql,array $args=[]){return 13;}
function e($s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function l1_icon($s):string{return '';}
require __DIR__.'/../app/level2a.php';
require __DIR__.'/../app/scoring.php';
function verify($ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function source_set(string $file,string $key):array{$j=json_decode(file_get_contents(__DIR__.'/../database/'.$file),true);return ($j['cards']??$j['pages'])[$key]+['title'=>'Test','module_title'=>'Test'];}
$a=['id'=>16018,'lesson_id'=>160,'source_page'=>18];$s=module_activity_set($a,source_set('level5-cards.json','1:18'));$keys=module_activity_keys($a,[]);
verify(count($keys)===5,'Five body answers');
foreach(['Nose','Head','Arm','Eye','Mouth'] as $i=>$word){verify($s['cards'][$i]['text']===$word,'Module order');verify($s['cards'][$i]['ui']['answer']===['chip'],'Tap answer');verify(key_card_ok($keys[$i+1],$word),'Correct tap accepted');verify(!key_card_ok($keys[$i+1],$word==='Head'?'Nose':'Head'),'Wrong tap rejected');}
$html=l2a_answer($s['cards'][0],1,false);verify(substr_count($html,'class="body-hit ')===7,'Accessible targets');verify(!str_contains($html,'data-key='),'No keyboard');verify(!str_contains($html,'style=')&&!str_contains($html,'onclick='),'CSP');
verify(module_activity_keys(['lesson_id'=>161,'source_page'=>18],[])===[],'Other grades unchanged');
$a=['lesson_id'=>15,'source_page'=>34];$s=module_activity_set($a,source_set('level2-cards.json','2a:34'));verify(count($s['cards'])===10,'Stable card indexes');verify($s['cards'][0]['ui']['answer']===['look'],'Unscored example');$keys=module_activity_keys($a,[]);verify(!isset($keys[1])&&count($keys)===9,'Nine scored exercises');foreach($keys as $key){verify(key_card_ok($key,'/a/'),'Middle sound accepted');verify(!key_card_ok($key,'/i/'),'Wrong vowel rejected');}
$s=module_activity_set(['lesson_id'=>13,'source_page'=>9],source_set('level2-cards.json','2a:9'));verify(count($s['cards'])===8,'Pretest retained');foreach($s['cards'] as $i=>$c){verify($c['ui']['answer']===['chip'],'Single answer');verify(str_starts_with($c['text'],$i<5?'Part A:':'Part B:'),'Specific directions');verify(count($c['choices'])===($i<5?26:5),'Choice count');}
$s=module_activity_set(['lesson_id'=>75,'phase'=>'learn','position'=>2],['cards'=>[]]);$words=$s['cards'][0]['ui']['grid'];verify(count($words)===46,'Complete charts');verify($s['cards'][0]['images']===[],'No tiny charts');verify(say_expected($s['cards'][0],$s['instruction'])===$words,'Reading checker gets words');
echo "PASS: scoped module adaptations, body scoring, pretest directions, unscored example, nine vowel answers, all 46 blend words, and strict CSP markup.\n";
