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
 /* Schools (v174): the next IDs a school gives (nothing is saved): php tests/probe.php school_ids <school id> */
 'school_ids'=>(function()use($pid):array{school_use($pid);db()->beginTransaction();$o=['pupil'=>new_account_id('pupil'),'teacher'=>new_account_id('teacher'),'name'=>admin_setting('school_name',''),'code'=>school_code()];db()->rollBack();return $o;})(),
 'xp'=>['xp'=>(int)val('SELECT COALESCE(total,0) FROM pupil_xp WHERE pupil_id=?',[$pid]),'rows'=>(int)val('SELECT COUNT(*) FROM xp_transactions WHERE pupil_id=?',[$pid])],
 /* The first activities of the first lesson of a level (and grade): php tests/probe.php first_activities <level id> <grade> */
 'first_activities'=>rows("SELECT a.id,a.lesson_id,a.xp_reward,a.response_mode FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) AND a.published=1 AND l.published=1 AND l.id=(SELECT l2.id FROM lessons l2 JOIN modules m2 ON m2.id=l2.module_id WHERE m2.level_id=? AND (m2.grade_level IS NULL OR m2.grade_level=?) AND l2.published=1 ORDER BY l2.position,l2.id LIMIT 1) ORDER BY FIELD(a.phase,'pre','learn','post'),a.position LIMIT 3",[$pid,(int)($argv[3]??1),$pid,(int)($argv[3]??1)]),
 /* Right and wrong answers for a card activity, from its answer key: php tests/probe.php card_answers <activity id> */
 'card_answers'=>(function()use($pid):array{$a=one('SELECT * FROM activities WHERE id=?',[$pid]);$key=$a?activity_key($a)??[]:[];$right=[];$wrong=[];
  foreach($key as $n=>$k){$w=match($k['t']){'card'=>(string)$k['w'],'choice'=>(string)$k['a'],'match'=>implode(', ',array_map(fn($i,$l)=>$i.'-'.$l,array_keys($k['a']),$k['a'])),default=>implode(' · ',array_map(fn($l,$v)=>$l.' = '.$v,array_keys($k['a']),$k['a']))};
   $right[]='Card '.$n.': '.$w;$wrong[]='Card '.$n.': zzz';}
  return ['keyed'=>$a&&ac_keyed($a),'right'=>"BULIG card answers\n".implode("\n",$right),'wrong'=>"BULIG card answers\n".implode("\n",$wrong),'cards'=>count($key)];})(),
 /* Level 4: BULIG's miscue count on a sample reading, and a perfect reading of every Level 4 passage (php tests/probe.php l4) */
 /* Cycles (v182): lessons 2 and up of the pupil's level open in 10 days; lesson 1 stays open. Settings are put back after. */
 'cycle_check'=>(function()use($pid):array{$L=pupil_start_level($pid);$old=val("SELECT setting_value FROM settings WHERE setting_key='lesson_cycles'");
  $d=date('Y-m-d',strtotime('+10 days'));q("INSERT INTO settings(setting_key,setting_value) VALUES('lesson_cycles',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[json_encode(['on'=>true,'levels'=>[(string)$L=>[['from'=>2,'to'=>cycle_level_size($L),'date'=>$d]]]])]);
  $mod=(int)val('SELECT m.id FROM modules m WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) ORDER BY m.id LIMIT 1',[$L,pupil_grade($pid)]);$no=cycle_numbers($mod)['no'];$one=array_search(1,$no,true);$two=array_search(2,$no,true);
  $o=['one'=>lesson_cycle_opens((int)$one),'two'=>lesson_cycle_opens((int)$two),'date'=>$d,'locked'=>lesson_cycle_locked($pid,(int)$two,$L)];
  if($old===null||$old===false)q("DELETE FROM settings WHERE setting_key='lesson_cycles'");else q("UPDATE settings SET setting_value=? WHERE setting_key='lesson_cycles'",[$old]);return $o;})(),
 /* Reading Castle (v182): mark every Level 7 lesson finished for the pupil and forget that the castle was seen */
 'castle_setup'=>(function()use($pid):array{$n=0;foreach(level_lessons(CASTLE_LEVEL,pupil_grade($pid)) as $l){q('INSERT INTO pupil_progress(pupil_id,lesson_id,completed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE completed_at=NOW()',[$pid,$l['id']]);$n++;}
  q('DELETE FROM settings WHERE setting_key=?',[castle_key($pid)]);friends_mark_met($pid);return ['lessons'=>$n,'open'=>castle_open($pid)];})(),
 /* Stars (v181): a lesson activity with an answer key the pupil can open now (earlier steps marked done), with right and wrong answers */
 'star_setup'=>(function()use($pid):array{$L=pupil_start_level($pid);
  foreach(rows("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) AND a.phase='learn' AND a.published=1 AND l.published=1 ORDER BY l.position,a.position",[$L,pupil_grade($pid)]) as $a){
   if(!key_checks_cards($a))continue;$lid=(int)$a['lesson_id'];$mod=(int)val('SELECT module_id FROM lessons WHERE id=?',[$lid]);$pos=(int)val('SELECT position FROM lessons WHERE id=?',[$lid]);
   foreach(rows('SELECT id FROM lessons WHERE module_id=? AND position<? AND published=1',[$mod,$pos]) as $l)q('INSERT INTO pupil_progress(pupil_id,lesson_id,completed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE completed_at=NOW()',[$pid,$l['id']]);
   foreach(rows("SELECT id FROM activities WHERE lesson_id=? AND published=1 AND (phase='pre' OR (phase='learn' AND position<?))",[$lid,$a['position']]) as $x)q("INSERT INTO activity_completion(pupil_id,activity_id,response,status,submitted_at,score,max_score) VALUES(?,?,'done','completed',NOW(),1,1) ON DUPLICATE KEY UPDATE status='completed',score=1,max_score=1",[$pid,$x['id']]);
   if(!lesson_available($pid,$lid)||!phase_available($pid,$lid,'learn'))continue;
   $key=activity_key($a)??[];$right=[];$wrong=[];foreach(range(1,count(l2a_set($a)['cards'])) as $n){$k=$key[$n]??null;$w=!$k?'(done)':match($k['t']){'card'=>explode(' or ',key_want($k))[0],'choice'=>(string)$k['a'],'match'=>implode(', ',array_map(fn($i,$l)=>$i.'-'.$l,array_keys($k['a']),$k['a'])),default=>implode(' · ',array_map(fn($l,$v)=>$l.' = '.$v,array_keys($k['a']),$k['a']))};$right[]='Card '.$n.': '.$w;$wrong[]='Card '.$n.': '.($k?'zzz':'(done)');}
   return ['id'=>(int)$a['id'],'lesson_id'=>$lid,'right'=>"BULIG card answers\n".implode("\n",$right),'wrong'=>"BULIG card answers\n".implode("\n",$wrong)];}
  return [];})(),
 /* Wrong tries (v172): card 1 answered right after 0, 1, 2 and 3 wrong tries */
 'tries'=>(function():array{
  foreach(rows("SELECT * FROM activities WHERE phase='learn' ORDER BY id") as $a){if(!key_checks_cards($a))continue;$k=(activity_key($a)??[])[1]??null;if(!$k||!in_array($k['t']??'',['choice','card'],true))continue;
   $want=explode(' or ',key_want($k))[0];$base="BULIG card answers\nCard 1: ".$want;if(!key_check_card($a,1,$want)['ok'])continue;$pts=[];
   foreach(['',"\nTries: 1=1","\nTries: 1=2","\nTries: 1=3"] as $t)$pts[]=key_score($a,$base.$t)['cards'][1]['pts'];
   return ['ok'=>true,'aid'=>(int)$a['id'],'pts'=>$pts];}
  return ['ok'=>false];})(),
 'l4'=>(function():array{
  $w=preg_split('/\s+/','The Cat The cat is small and white. It likes to sleep on the mat. The cat plays with a ball. It runs fast in the yard.');
  $r=l4_miscues($w,'the cat the cat is small and with it like to sleep on the the mat the cat play with ball it runs in fast the yard');
  $rev=l4_miscues(['I','saw','a','cat'],'i was a cat');$n=0;$bad=[];
  foreach(rows("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND a.type='reading' AND a.published=1 AND l.published=1") as $a){
   $ws=l4_words($a);if(!$ws)continue;$n++;$s=l4_auto($a,implode(' ',$ws),60);if(!$s||$s['score']!=100)$bad[]=$a['id'];}
  return ['sample'=>$r['m'],'reversal'=>$rev['m'][6],'too_little'=>(function(){$a=one("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND a.type='reading' LIMIT 1")?:[];$one=l4_auto($a,'the',30);return l4_auto($a,'',30)===null&&$one!==null&&$one['score']<30;})(),'passages'=>$n,'not_100'=>$bad];})(),
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
 /* DepEd SF1 as LIS gives it (.xls): php tests/probe.php sf1 0 */
 'sf1'=>(function():array{$rows=import_read_file(__DIR__.'/fixtures/sf1-sample.xls','sf1-sample.xls');$h=4;
  return ['class'=>import_sheet_class($rows,$h),'cols'=>import_columns($rows[$h]),'names'=>array_values(array_filter(array_map(fn($r)=>($r[2]??'')!==''&&!str_starts_with($r[2],'<==')&&preg_match('/^\d{12}$/',$r[0]??'')?[$r[0],import_sf1_name($r[2])]:null,array_slice($rows,$h+1))))];})(),
 /* Level 3A and 3B (v177): order, labels and where the lessons are. php tests/probe.php level3ab 0 */
 'level3ab'=>['order'=>array_map('intval',array_column(rows('SELECT id FROM bulig_levels ORDER BY '.level_order_sql('id')),'id')),'labels'=>[level_label(4),level_label(9)],'next4'=>level_next(4),'next9'=>level_next(9),
  'l3a'=>array_map('intval',array_column(rows('SELECT l.position FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=4 ORDER BY l.position'),'position')),
  'l3b'=>array_map('intval',array_column(rows('SELECT l.position FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=9 ORDER BY l.position'),'position')),
  'pre3b'=>array_column(rows("SELECT a.title FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=9 AND l.position=1 ORDER BY a.position"),'title'),
  'key3b'=>(function(){foreach(rows("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=9 AND l.position=12 ORDER BY a.position") as $a)if(($s=l2a_set($a))&&$s['cards']&&activity_key($a))return ['key'=>activity_key_id($a),'cards'=>count($s['cards'])];return null;})()],
 default=>['error'=>'unknown probe']
};
echo json_encode($out),"\n";
