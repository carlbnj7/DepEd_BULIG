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
 /* v202 content audit: every keyed multiple-choice answer is one of its card's choices, no key points past the last card,
    and Level 5 Grade 3 keeps module order (answer-key pages 13 and 53 leave a gap): php tests/probe.php key_audit */
 'key_audit'=>(function():array{$n=fn($s)=>key_norm(preg_replace('~^\s*[a-z]\s*[.)]\s*~i','',(string)$s));$bad=[];$checked=0;
  foreach(rows('SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id WHERE a.published=1 AND l.published=1') as $a){$key=activity_key($a);if(!$key)continue;$s=l2a_set($a);$cards=$s['cards']??[];
   foreach($key as $i=>$k){$c=$cards[(int)$i-1]??null;if($cards&&!$c){$bad[]=activity_key_id($a).' card '.$i.' missing';continue;}
    if(!$c||($k['t']??'')!=='choice'||empty($c['choices']))continue;$checked++;$ch=array_map($n,$c['choices']);$ok=false;
    foreach(array_merge([$k['a']],$k['alt']??[]) as $w)if(in_array($n($w),$ch,true))$ok=true;if(!$ok)$bad[]=activity_key_id($a).' card '.$i;}}
  $g3=rows("SELECT a.phase,a.position,a.source_page FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=6 AND m.grade_level=3 AND a.phase IN ('pre','post') AND a.published=1");
  $order=true;foreach($g3 as $r)if((int)$r['position']!==(int)$r['source_page']-($r['phase']==='pre'?5:47))$order=false;
  return ['checked'=>$checked,'bad'=>array_slice($bad,0,10),'g3'=>count($g3),'g3_order'=>$order];})(),
 /* v203 Word Coach only where the module asks pupils to read: php tests/probe.php wc_module */
 'wc_module'=>(function():array{$f=function(string $title,int $lv,int $g):?bool{$a=one('SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE a.title=? AND m.level_id=? AND m.grade_level=? AND a.published=1 LIMIT 1',[$title,$lv,$g]);if(!$a)return null;
   foreach(l2a_set($a)['cards']??[] as $c)if(in_array('read',$c['ui']['answer']??[],true))return true;return false;};
  return ['editorial'=>$f('A Dirty Cafeteria',8,6),'read_story'=>$f('My Friend',7,1)];})(),
 'wc_check'=>(function():array{$r="BULIG card answers\nCard 1: (voice recording) said: cat hat\nCard 2: x";$_POST['wc']=['1'=>'40|cat','2'=>'na|'];$m=wc_merge($r."\nTries: 2=1");unset($_POST['wc']);
  $c=card_coach($m);return ['line'=>str_contains($m,"\nCoach: 1=40|cat; 2=na|\nTries: 2=1"),'p1'=>$c[1]['p']??-1,'w1'=>$c[1]['w']??[],'p2'=>isset($c[2])&&array_key_exists('p',$c[2])?$c[2]['p']:-1,'cards'=>card_answers($m),'strip'=>card_strip_tries($m)===$r,
   'test_off'=>wc_on(['phase'=>'post'])||wc_on(['phase'=>'pre']),'learn_on'=>wc_on(['phase'=>'learn'])];})(),
 'done_score'=>(function():array{$o=[];foreach(['perform','drawing','none'] as $m){$a=one("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=1 AND a.published=1 AND a.response_mode=? LIMIT 1",[$m]);$o[$m]=$a?key_score($a,''):'none';}
  $o['card']=pt_inlesson_card(0,1)!=='';return $o;})(),
 'cycle_check'=>(function()use($pid):array{$L=pupil_start_level($pid);$old=val("SELECT setting_value FROM settings WHERE setting_key='lesson_cycles'");
  $d=date('Y-m-d',strtotime('+10 days'));q("INSERT INTO settings(setting_key,setting_value) VALUES('lesson_cycles',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[json_encode(['on'=>true,'levels'=>[(string)$L=>[['from'=>2,'to'=>cycle_level_size($L),'date'=>$d]]]])]);
  $mod=(int)val('SELECT m.id FROM modules m WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) ORDER BY m.id LIMIT 1',[$L,pupil_grade($pid)]);$no=cycle_numbers($mod)['no'];$one=array_search(1,$no,true);$two=array_search(2,$no,true);
  $o=['one'=>lesson_cycle_opens((int)$one),'two'=>lesson_cycle_opens((int)$two),'date'=>$d,'locked'=>lesson_cycle_locked($pid,(int)$two,$L)];
  if($old===null||$old===false)q("DELETE FROM settings WHERE setting_key='lesson_cycles'");else q("UPDATE settings SET setting_value=? WHERE setting_key='lesson_cycles'",[$old]);return $o;})(),
 /* v209 bug #139: a matching board keeps the pairs right after many Checks; a one-answer card still loses a third per wrong try */
 'board_tries'=>(function():array{$o=['board'=>null,'card'=>null];
  foreach(rows("SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id IN (5,6,7) AND a.phase='learn' ORDER BY a.id") as $a){$k=activity_key($a);if(!$k)continue;
   foreach($k as $n=>$c){if($o['board']===null&&$c['t']==='match'&&count($c['a'])>=4){$p=[];$i=0;foreach($c['a'] as $x=>$l)$p[]=$x.'-'.(++$i===1?'z':$l);$s=key_score($a,"BULIG card answers\nCard $n: ".implode(', ',$p)."\nTries: $n=4");$c2=$s['cards'][$n]??[];$o['board']=['pts'=>$c2['pts']??null,'of'=>$c2['of']??null];}
    if($o['card']===null&&$c['t']==='choice'){$s=key_score($a,"BULIG card answers\nCard $n: ".$c['a']."\nTries: $n=1");$c2=$s['cards'][$n]??[];$o['card']=['pts'=>$c2['pts']??null,'of'=>$c2['of']??null];}}
   if($o['board']!==null&&$o['card']!==null)break;}
  return $o;})(),
 /* v209 certificate: names print with capital first letters; the principal comes from the school details */
 'cert_check'=>(function()use($pid):array{$o=[];foreach(['JUAN DELA CRUZ','ROBERTO M. SANTOS, ED.D.','Ma. Luisa McDonald','JOSE RIZAL III'] as $t)$o['names'][]=name_case($t);
  $sid=(int)val('SELECT school_id FROM users WHERE id=?',[$pid]);$old=$sid?val('SELECT head FROM schools WHERE id=?',[$sid]):null;
  if($sid)q('UPDATE schools SET head=? WHERE id=?',['MARIA P. REYES',$sid]);else q("INSERT INTO settings(setting_key,setting_value) VALUES('school_head','MARIA P. REYES') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
  $p=one('SELECT u.id,u.name,p.grade_level,p.section FROM users u JOIN pupils p ON p.user_id=u.id WHERE u.id=?',[$pid]);$p['name']='PEDRO PENDUKO';
  $h=certificate_html($p,1,date('Y-m-d'),'ANA SANTOS');
  $o['principal']=str_contains($h,'Maria P. Reyes')&&str_contains($h,'School Principal');$o['pupil']=str_contains($h,'Pedro Penduko');$o['teacher']=str_contains($h,'Ana Santos')&&str_contains($h,'Class Adviser');$o['jarvis']=str_contains($h,'jarvis-cheer');$o['division']=str_contains($h,'Department of Education');
  if($sid)q('UPDATE schools SET head=? WHERE id=?',[$old,$sid]);return $o;})(),
 /* v209: recordings older than 60 days go; new ones and readings still waiting for the teacher stay */
 'privacy_purge'=>(function():array{$dir=l1_recordings_dir().'/999999';@mkdir($dir,0750,true);$old=$dir.'/old.webm';$new=$dir.'/new.webm';$wait=$dir.'/wait.webm';
  foreach([$old,$new,$wait] as $f)file_put_contents($f,'x');touch($old,time()-61*86400);touch($wait,time()-90*86400);
  $c=one('SELECT id,pupil_id,activity_id FROM activity_completion LIMIT 1');$rid=0;
  if($c){q("INSERT INTO reading_verifications(completion_id,pupil_id,activity_id,state,audio_paths) VALUES(?,?,?,'pending',?)",[$c['id'],$c['pupil_id'],$c['activity_id'],json_encode(['0'=>'999999/wait.webm'])]);$rid=(int)db()->lastInsertId();}
  $n=privacy_purge(true);$o=['deleted'=>$n,'old_gone'=>!is_file($old),'new_kept'=>is_file($new),'waiting_kept'=>$c?is_file($wait):true];
  if($rid)q('DELETE FROM reading_verifications WHERE id=?',[$rid]);foreach([$old,$new,$wait] as $f)@unlink($f);@rmdir($dir);return $o;})(),
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
