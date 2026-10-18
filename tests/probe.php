<?php
/* Reads values from the TEST database for tests/run_tests.py: php tests/probe.php <what> <pupil id>. Prints JSON. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$name=(string)getenv('BULIG_DB_NAME');
if($name===''||$name==='DEPED_BULIG'){fwrite(STDERR,"Set BULIG_DB_NAME to the test database first.\n");exit(1);}
require (getenv('BULIG_APP_ROOT')?:__DIR__.'/..').'/app/bootstrap.php';
[$what,$pid]=[$argv[1]??'',(int)($argv[2]??0)];
$out=match($what){
 'reader'=>rl_info($pid,true)+['headstart_all'=>array_sum(rl_headstart($pid,true)),'headstart_now'=>rl_headstart_now($pid,true)],
 'xp'=>['xp'=>(int)val('SELECT COALESCE(total,0) FROM pupil_xp WHERE pupil_id=?',[$pid]),'rows'=>(int)val('SELECT COUNT(*) FROM xp_transactions WHERE pupil_id=?',[$pid])],
 /* The first activities of the first lesson of a level (and grade): php tests/probe.php first_activities <level id> <grade> */
 'first_activities'=>rows("SELECT a.id,a.lesson_id,a.xp_reward,a.response_mode FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) AND a.published=1 AND l.published=1 AND l.id=(SELECT l2.id FROM lessons l2 JOIN modules m2 ON m2.id=l2.module_id WHERE m2.level_id=? AND (m2.grade_level IS NULL OR m2.grade_level=?) AND l2.published=1 ORDER BY l2.position,l2.id LIMIT 1) ORDER BY FIELD(a.phase,'pre','learn','post'),a.position LIMIT 3",[$pid,(int)($argv[3]??1),$pid,(int)($argv[3]??1)]),
 /* Right and wrong answers for a card activity, from its answer key: php tests/probe.php card_answers <activity id> */
 'card_answers'=>(function()use($pid):array{$a=one('SELECT * FROM activities WHERE id=?',[$pid]);$key=$a?activity_key($a)??[]:[];$right=[];$wrong=[];
  foreach($key as $n=>$k){$w=match($k['t']){'card'=>(string)$k['w'],'choice'=>(string)$k['a'],'match'=>implode(', ',array_map(fn($i,$l)=>$i.'-'.$l,array_keys($k['a']),$k['a'])),default=>implode(' · ',array_map(fn($l,$v)=>$l.' = '.$v,array_keys($k['a']),$k['a']))};
   $right[]='Card '.$n.': '.$w;$wrong[]='Card '.$n.': zzz';}
  return ['keyed'=>$a&&ac_keyed($a),'right'=>"BULIG card answers\n".implode("\n",$right),'wrong'=>"BULIG card answers\n".implode("\n",$wrong),'cards'=>count($key)];})(),
 /* Level 4: BULIG's miscue count on a sample reading, and a perfect reading of every Level 4 passage (php tests/probe.php l4) */
 'l4'=>(function():array{
  $w=preg_split('/\s+/','The Cat The cat is small and white. It likes to sleep on the mat. The cat plays with a ball. It runs fast in the yard.');
  $r=l4_miscues($w,'the cat the cat is small and with it like to sleep on the the mat the cat play with ball it runs in fast the yard');
  $rev=l4_miscues(['I','saw','a','cat'],'i was a cat');$n=0;$bad=[];
  foreach(rows("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND a.type='reading' AND a.published=1 AND l.published=1") as $a){
   $ws=l4_words($a);if(!$ws)continue;$n++;$s=l4_auto($a,implode(' ',$ws),60);if(!$s||$s['score']!=100)$bad[]=$a['id'];}
  return ['sample'=>$r['m'],'reversal'=>$rev['m'][6],'too_little'=>l4_auto(one("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND a.type='reading' LIMIT 1")?:[],'the',30)===null,'passages'=>$n,'not_100'=>$bad];})(),
 'migrations'=>array_column(rows('SELECT version FROM schema_migrations ORDER BY version'),'version'),
 'tables'=>array_column(rows('SHOW TABLES'),array_key_first(rows('SHOW TABLES')[0]??['x'=>0])),
 /* Blanks: one line per missing letter in Levels 2A, 2B and 3 (blank_letters, blank_html), and every such card in the content. */
 'blanks'=>(function():array{
  $cases=[['n ___ t','e',1],['n ___ t','net',1],['t ___ n','/e/',1],['___ og','fr',2],['___ og','frog',2],['___ eam','scr',3],['bl + ack = ____','black',5],
   ['p + ail = ____','pail',4],['Beginning sound: ___','c or k',1],['can → ___ an','m',1],['bat → ___','bag',3],['One two, tie my ___.','shoe',0],['___ and ___','x',0]];
  $wrong=[];foreach($cases as [$t,$a,$n])if(($got=blank_letters($t,$a))!==$n)$wrong[]="$t ($a): $got, not $n";
  $h=['pupil'=>blank_html('n ___ t',1,true),'demo'=>blank_html('n ___ t',1),'word'=>blank_html('tie my ___.',0),'inword'=>blank_html('technolog__ is',0),'plain'=>blank_plain('___ og',2)];
  $left=[];$cards=0;
  foreach(rows("SELECT a.*,m.level_id FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id IN (2,3,4) AND a.published=1 AND l.published=1") as $a){
   $set=l2a_set($a);if(!$set)continue;$bc=l2a_blank_counts($a,$set,(int)$a['level_id']);
   foreach($set['cards'] as $i=>$c){$t=(string)$c['text'];if(preg_match_all('~_{2,}~',$t)!==1||preg_match('~[.,!?;"“”]~u',$t))continue;$cards++;if(($bc[$i]??0)<1)$left[]=$a['id'].'#'.($i+1).' '.$t;}}
  return ['wrong'=>$wrong,'html'=>$h,'cards'=>$cards,'left'=>array_slice($left,0,5),'left_count'=>count($left)];
 })(),
 default=>['error'=>'unknown probe']
};
echo json_encode($out),"\n";
