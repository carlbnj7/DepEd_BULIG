<?php
/* Reading check and starting level (Grades 1-6).
   A pupil with no starting level takes the reading check the first time they sign in. Pupils who already have a
   level never see it. The check has 7 parts, one for each BULIG level, made from the module pre-tests:
     1 Talk and listen ......... Level 1 pre-test (5 questions, recorded; the teacher rates it with the module rubric)
     2 Sound games ............. Level 2A pretests (one item from each of the 10 pretest skills)
     3 Word parts .............. Level 2B "Pre-Test: Phonological Awareness" (30 items, module pages 14-17)
     4 Read the words .......... Level 3 Pre-Assessment Toolkit (4 word lists)
     5 Read a story ............ the grade's Level 4 pre-test passage
     6 Listen and learn words .. the grade's Level 5 pre-test (its first questions, about 30 points)
     7 Read and understand ..... the grade's Level 6 reading comprehension test
   Pass marks come only from the modules and Phil-IRI (rc_rules_view() shows them to teachers):
     Part 1 "Good" on the Level 1 rubric (9 of 12), Part 2 80% (Level 2B module, Annex A), Part 3 24 of 30
     (Level 2B module, "Interpretation – 30 items test"), Part 4 8/10, 8/10, 15/20, 8/10 (Level 3 toolkit),
     Part 5 Phil-IRI word reading 97-100% Independent (Level 4 module, Phil-IRI profile), Parts 6 and 7 Phil-IRI
     comprehension Independent, 80-100%. A part also stops after 5 wrong answers in a row (Level 2B module, Annex A).
   Grades 1-3 start at Part 1 and go up. Grades 4-6 start at Part 5 (the grade's story, like Phil-IRI) and go down
   when it is hard or up when it is easy. The first part not passed gives the starting level; all parts passed: Level 7.
   Reading aloud is scored with the browser's speech recognition; the recording is kept so the teacher can listen.
   When BULIG cannot hear the reading, the part is "Please listen" and the pupil starts at that part's level for now.
   Table: database/migrations/028_reading_check.sql. */

const RC_PARTS=[
 1=>['name'=>'Talk and listen','what'=>'Answer questions about yourself','level'=>1],
 2=>['name'=>'Sound games','what'=>'Listen for sounds in words','level'=>2],
 3=>['name'=>'Word parts','what'=>'Rhymes, first sounds and word parts','level'=>3],
 4=>['name'=>'Read the words','what'=>'Read words out loud','level'=>4],
 5=>['name'=>'Read a story','what'=>'Read a short story out loud','level'=>5],
 6=>['name'=>'Listen and learn words','what'=>'Listen to stories, then answer','level'=>6],
 7=>['name'=>'Read and understand','what'=>'Read stories, then answer','level'=>7],
];
/* Part 2: one item from each Level 2A pretest skill: [activity position in "Before we begin", card number, direction, words read aloud]. */
const RC_SOUNDS=[
 [2,1,'Look at the picture. Tap the letter for the missing beginning sound.','Listen. dog. What is the beginning sound?'],
 [3,2,'Look at the picture. Tap the letter for the missing middle sound.','Listen. bag. What is the middle sound?'],
 [4,1,'Look at the picture. Tap the letter for the missing ending sound.','Listen. cat. What is the ending sound?'],
 [6,3,'Tap the picture that has the same ending sound as the first picture.','Which picture has the same ending sound as the first picture?'],
 [7,1,'Which word does not belong? Listen to the first sounds.','mat. cam. mop. Which word does not belong?'],
 [9,2,'Blend the sounds to make a word. Tap the letters of the word.','Blend the sounds. s. t. a. r. What is the word?'],
 [11,1,'Listen to the sounds. Tap the picture that matches.','j. a. m. Tap the picture.'],
 [13,1,'Say the name of the first picture. Take away the first sound. Tap the picture of the new word.','Take away the m sound. Which picture is the new word?'],
 [16,3,'Add a sound to make a new word. Tap the sound, then tap the letters of the new word.','at. Add a sound to make a new word.'],
 [17,1,'Was the first sound changed (✓) or the ending sound (x)?','mug. bug. Was the first sound changed, or the ending sound?'],
];
/* Part 3: Level 2B module, "Pre-Test: Phonological Awareness (Oral Assessment)", items 1-30 and its answer key. */
const RC_DIAG=[
 ['rhyme','cat – hat','Yes'],['rhyme','dog – log','Yes'],['rhyme','sun – bed','No'],['rhyme','hop – top','Yes'],['rhyme','pen – hen','Yes'],['rhyme','book – look','Yes'],
 ['first','man',['m']],['first','fish',['f']],['first','dog',['d']],['first','cup',['c','k']],['first','sun',['s']],['first','zip',['z']],
 ['blend','rain – bow','rainbow'],['blend','cup – cake','cupcake'],['blend','wa – ter','water'],['blend','air – plane','airplane'],['blend','el – e – phant','elephant'],['blend','ham – bur – ger','hamburger'],
 ['same','bat, ball, boy','Yes'],['same','cat, sun, cup','No'],['same','fish, fan, fun','Yes'],['same','moon, star, sun','No'],['same','pen, pig, pan','Yes'],['same','red, run, rag','Yes'],
 ['count','I run.',2],['count','She sits.',2],['count','The cat jumps.',3],['count','We eat cake.',3],['count','My mom reads.',3],['count','The big dog barks.',4],
];
const RC_DIAG_DIR=['rhyme'=>'Do the two words rhyme? Tap Yes or No.','first'=>'What is the first sound in the word? Tap the letter.','blend'=>'Put the parts together. Say the word.','same'=>'Do the words start with the same sound? Tap Yes or No.','count'=>'How many words are in the sentence?'];
/* Part 4: Level 3 Pre-Assessment Toolkit: tasks (activity positions 2-5) and how many words pass each task. */
const RC_WORD_TASKS=[[2,8,'Short vowel words'],[3,8,'Word family words'],[4,15,'Words with blends'],[5,8,'Sight words']];
/* Official results the teacher may type, and the BULIG levels the guide puts with each (BULIG Assessment and Grouping). */
const RC_OFFICIAL=[
 'CRLA'=>['Low Emerging'=>[1,2,3],'High Emerging'=>[1,2,3],'Developing'=>[2,3,4],'Transitioning'=>[4,5,6],'Grade Ready'=>[6,7],'Advanced'=>[8]],
 'Phil-IRI'=>['Frustration'=>[1,2,3,4],'Instructional'=>[5,6],'Independent'=>[6,7],'Advanced'=>[8]],
];

function rc_ready():bool{static $r;if($r===null){try{db()->query('SELECT 1 FROM reading_checks LIMIT 1');$r=true;}catch(Throwable $e){$r=false;}}return $r;}
function rc_row(int $pid):?array{return rc_ready()?one('SELECT * FROM reading_checks WHERE pupil_id=?',[$pid]):null;}
function rc_state(?array $row):array{$s=$row?json_decode((string)$row['state'],true):null;return is_array($s)?$s:[];}
function rc_save(int $id,array $s):void{q('UPDATE reading_checks SET state=? WHERE id=?',[json_encode($s,JSON_UNESCAPED_UNICODE),$id]);}
/** A pupil with no starting level (Grades 1-6) takes the reading check. */
function rc_needed(array $u):bool{if($u['role']!=='pupil'||!rc_ready())return false;$pid=(int)$u['id'];$g=pupil_grade($pid);return $g>=1&&$g<=6&&!(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);}
function rc_tool(int $grade):string{return $grade<=3?'CRLA':'Phil-IRI';}
function rc_level_name(int $id):string{static $t=null;$t??=array_column(rows('SELECT id,title FROM bulig_levels'),'title','id');return trim(preg_replace('~\s*·\s*Level \w+$~u','',(string)($t[$id]??'')));}

/* ---------------- the questions ---------------- */

/** A module activity by place: level, lesson position, phase and position (per-grade levels use the pupil's grade). */
function rc_act(int $level,int $grade,int $lpos,string $phase,int $pos):?array{return one('SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) AND l.position=? AND a.phase=? AND a.position=? ORDER BY m.grade_level DESC LIMIT 1',[$level,$grade,$lpos,$phase,$pos]);}
/** Part 1: the Level 1 pre-test questions (lesson 1, "Tell me your name and age." ...). */
function rc_talk_items(int $g):array{$out=[];for($i=1;$i<=5;$i++){$a=rc_act(1,$g,1,'pre',$i);if($a)$out[]=trim((string)$a['prompt'],"“”\" \n");}return $out;}
/** Parts 6 and 7: the activities of the grade's pre-test, in order, up to about 30 points (whole activities). */
function rc_test_acts(int $level,int $g):array{
 $lid=(int)val('SELECT l.id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND m.grade_level=? AND l.position>=1100 AND l.position<2200 AND l.published=1 ORDER BY l.position LIMIT 1',[$level,$g]);if(!$lid)return [];
 $out=[];$pts=0;foreach(rows("SELECT * FROM activities WHERE lesson_id=? AND published=1 ORDER BY FIELD(phase,'pre','learn','post'),position",[$lid]) as $a){$p=rc_points($a);if(!$p)continue;$out[]=['aid'=>(int)$a['id'],'pts'=>$p];$pts+=$p;if($pts>=30)break;}
 return $out;
}
/** Points the answer key gives an activity (a matching board or number-the-events card counts one point per pair or event). */
function rc_points(array $a):int{$n=0;foreach(activity_key($a)??[] as $k)$n+=in_array($k['t']??'',['match','order'],true)?count($k['a']):1;return $n;}
/** One keyed card: [points, out of], or null when the card has no key. */
function rc_card_score(array $a,int $n,string $g):?array{
 $k=(activity_key($a)??[])[$n]??null;if(!$k)return null;$t=$k['t'];
 if($t==='card')return [key_card_ok($k,$g)?1:0,1];
 if($t==='choice'){$ok=array_merge([$k['a']],$k['alt']??[]);return [$g!==''&&in_array(key_norm($g),array_map('key_norm',$ok),true)?1:0,1];}
 if($t==='match'){$have=[];if(preg_match_all('~(\d+)\s*-\s*([a-z])~i',$g,$mm,PREG_SET_ORDER))foreach($mm as $m)$have[$m[1]]=strtolower($m[2]);$p=0;foreach($k['a'] as $i=>$l)if(($have[(string)$i]??'')===$l)$p++;return [$p,count($k['a'])];}
 if($t==='order'){$have=[];foreach(explode(' · ',$g) as $part)if(($p=strrpos($part,' = '))!==false)$have[key_norm(substr($part,0,$p))]=trim(substr($part,$p+3));$p=0;foreach($k['a'] as $lab=>$num)if(($have[key_norm((string)$lab)]??'')===(string)$num)$p++;return [$p,count($k['a'])];}
 return null;
}
/** Part 3 cards in the same shape as module cards, so they use the same answer screens. */
function rc_diag_card(int $i):array{
 [$kind,$text]=RC_DIAG[$i];
 $ui=['rhyme'=>['answer'=>['chip'],'opts'=>[['Yes','No']]],'same'=>['answer'=>['chip'],'opts'=>[['Yes','No']]],'first'=>['answer'=>['tiles'],'tiles'=>str_split('abcdefghijklmnopqrstuvwxyz')],'blend'=>['answer'=>['write'],'rcsay'=>1],'count'=>['answer'=>['num'],'max'=>6]][$kind];
 $say=['rhyme'=>'Do these words rhyme? '.str_replace(' – ',', ',$text).'.','same'=>'Do these words start with the same sound? '.$text,'first'=>'What is the first sound in '.$text.'?','blend'=>'Put the parts together. '.str_replace(' – ',', ',$text).'. What is the word?','count'=>'How many words? '.$text][$kind];
 return ['title'=>(string)($i+1),'text'=>$text,'images'=>[],'choices'=>['Yes','No'],'ui'=>$ui,'speak'=>$say,'dir'=>RC_DIAG_DIR[$kind]];
}
function rc_diag_ok(int $i,string $g,string $heard):?bool{
 $d=RC_DIAG[$i];
 switch($d[0]){
  case 'rhyme':case 'same':return $g!==''?key_norm($g)===key_norm($d[2]):false;
  case 'first':return in_array(key_letters($g),$d[2],true);
  case 'count':return (int)$g===$d[2];
  case 'blend':if($heard==='')return str_contains($g,'voice recording')?null:false;foreach(explode('|',$heard) as $alt)if(str_contains(key_letters($alt),$d[2]))return true;return false;
 }
 return false;
}
/** A Level 4 pre-test passage: [activity, words]. */
function rc_story(int $g):?array{$a=rc_act(5,$g,1,'pre',2);$p=$a?level4_passage_parts($a):null;if(!$p)return null;$w=preg_split('/\s+/u',trim(implode(' ',$p['blocks'])),-1,PREG_SPLIT_NO_EMPTY);
 /* The module's word count includes the title when the numbers say so; then the title is read and counted too (Phil-IRI). */
 $t=preg_split('/\s+/u',trim((string)$p['title']),-1,PREG_SPLIT_NO_EMPTY);if($t&&count($w)+count($t)===(int)$p['words'])$w=array_merge($t,$w);return [$a,$w];}
/** Phil-IRI word reading: words read right ÷ words in the passage (each word matched once, in order). */
function rc_accuracy(array $words,string $heard):float{
 $a=array_values(array_filter(array_map('key_letters',$words),'strlen'));$b=array_values(array_filter(array_map('key_letters',preg_split('/\s+/u',$heard)),'strlen'));
 if(!$a)return 0.0;$n=count($a);$m=count($b);$prev=array_fill(0,$m+1,0);
 for($i=1;$i<=$n;$i++){$cur=[0];for($j=1;$j<=$m;$j++)$cur[$j]=$a[$i-1]===$b[$j-1]?$prev[$j-1]+1:max($prev[$j],$cur[$j-1]);$prev=$cur;}
 return round($prev[$m]/$n*100,1);
}
function rc_band(float $pct):string{return $pct>=97?'Independent':($pct>=90?'Instructional':'Frustration');}

/* ---------------- moving through the check ---------------- */

function rc_begin(array $u):array{
 $pid=(int)$u['id'];$g=max(1,min(6,pupil_grade($pid)));$first=$g<=3?1:5;
 $s=['v'=>1,'grade'=>$g,'path'=>$g<=3?'up':'mid','part'=>$first,'step'=>0,'parts'=>[],'order'=>[],'token'=>bin2hex(random_bytes(6))];
 $s['parts'][$first]=rc_part_new($first,$g);
 q('INSERT INTO reading_checks(pupil_id,grade,status,state) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE grade=VALUES(grade),status=VALUES(status),state=VALUES(state),bulig_level=NULL,placed_level=NULL,changed_by=NULL,finished_at=NULL,started_at=NOW()',[$pid,$g,'running',json_encode($s,JSON_UNESCAPED_UNICODE)]);
 return rc_row($pid);
}
function rc_part_new(int $p,int $g):array{
 $r=['st'=>'run','right'=>0,'total'=>0,'unknown'=>0,'streak'=>0,'ans'=>[]];
 if($p===6||$p===7){$acts=rc_test_acts($p,$g);$r['acts']=$acts;$r['need']=(int)ceil(array_sum(array_column($acts,'pts'))*0.8);$r['max']=array_sum(array_column($acts,'pts'));}
 return $r;
}
/** How many pages a part has (the page index is the state's step). */
function rc_pages(int $p,array $part):int{return match($p){1=>1,2=>2,3=>5,4=>count(RC_WORD_TASKS),5=>1,default=>count($part['acts']??[])};}

/** Record one answer and keep the "5 wrong in a row" count (an unknown answer does not count either way). */
function rc_mark(array &$P,array $a):void{$P['ans'][]=$a;if($a['ok']===null){$P['unknown']+=$a['of']??1;return;}$pts=$a['pts']??($a['ok']?1:0);$P['right']+=$pts;$P['total']+=$a['of']??1;$P['streak']=$pts>0?0:$P['streak']+1;}

/** Decide a part when it can be decided; returns true when the part is over. */
function rc_decide(int $p,array &$P,bool $last,array $s):bool{
 if($p===1){$P['st']='rate';return true;}
 if($P['streak']>=5){$P['st']='fail';$P['note']='Stopped after 5 wrong answers in a row.';return true;}
 if($p===2){$need=8;$max=count(RC_SOUNDS);}elseif($p===3){$need=24;$max=count(RC_DIAG);}elseif($p===6||$p===7){$need=$P['need'];$max=$P['max'];}else return false;
 $done=$P['total']+$P['unknown'];$left=$max-$done;
 if($P['right']>=$need){$P['st']='pass';return true;}
 if($P['right']+$P['unknown']+$left<$need){$P['st']='fail';return true;}
 if($last){$P['st']=$P['right']+$P['unknown']>=$need?'listen':'fail';return true;}
 return false;
}

/** Save the answers of one page (action rc_submit) and move on. */
function rc_submit(array $u):void{
 $pid=(int)$u['id'];$row=rc_row($pid);if(!$row||$row['status']!=='running')rc_reply(true,'Saved.');
 $s=rc_state($row);if(($s['token']??'')!==(string)($_POST['rc_token']??''))rc_reply(true,'Saved.');
 $p=(int)$s['part'];$P=&$s['parts'][$p];$step=(int)$s['step'];$g=(int)$s['grade'];$got=card_answers((string)($_POST['response']??''));
 $audio=function(string $key)use($pid,$row):?string{$d=(string)($_POST['audio_card'][$key]??$_POST['audio'][$key]??'');if($d==='')return null;try{return l1_save_audio($d,$pid,(int)$row['id']);}catch(Throwable $e){return null;}};
 $heard=fn(string $key)=>mb_substr(trim((string)($_POST['heard'][$key]??'')),0,4000);
 if($p===1){foreach(rc_talk_items($g) as $i=>$q){$n=$i+1;$P['ans'][]=['q'=>$q,'got'=>(string)($got[$n]??''),'ok'=>null,'audio'=>$audio((string)$n)];}}
 elseif($p===2){foreach(array_slice(RC_SOUNDS,$step*5,5) as $i=>[$pos,$cn,$dir]){$n=$i+1;$a=rc_act(2,$g,1,'pre',$pos);$gv=(string)($got[$n]??'');$sc=$a?rc_card_score($a,$cn,$gv):null;$k=$a?(activity_key($a)??[])[$cn]??null:null;
   rc_mark($P,['q'=>$dir,'got'=>$gv,'want'=>$k?key_want($k):'','ok'=>$sc?$sc[0]===$sc[1]:false,'pts'=>$sc[0]??0,'of'=>$sc[1]??1]);}}
 elseif($p===3){foreach(range($step*6,$step*6+5) as $j=>$i){$n=$j+1;$gv=(string)($got[$n]??'');$h=$heard((string)$n);$ok=rc_diag_ok($i,$gv,$h);$d=RC_DIAG[$i];
   rc_mark($P,['q'=>$d[1],'got'=>$h!==''?'said: '.explode('|',$h)[0]:$gv,'want'=>is_array($d[2])?implode(' or ',$d[2]):(string)$d[2],'ok'=>$ok,'audio'=>$d[0]==='blend'?$audio((string)$n):null]);}}
 elseif($p===4){[$pos,$need,$label]=RC_WORD_TASKS[$step];$a=rc_act(4,$g,1,'pre',$pos);$words=$a?l3_list((string)$a['expected_text']):[];$right=0;$unk=0;$seen=0;
  foreach($words as $i=>$w){$k=(string)$i;if(!isset($_POST['heard'][$k])&&!isset($_POST['audio'][$k]))break;$seen++;$h=$heard($k);$ok=null;
   if($h==='-'){$ok=false;$h='';}elseif($h!==''){$ok=false;foreach(explode('|',$h) as $alt)if(in_array(key_letters($w),array_map('key_letters',preg_split('/\s+/u',$alt)),true)){$ok=true;break;}}elseif(($_POST['sr']??'off')==='ok')$ok=false;
   $au=$ok===true?null:$audio($k);
   rc_mark($P,['q'=>$w,'task'=>$step,'got'=>$h!==''?explode('|',$h)[0]:'','want'=>$w,'ok'=>$ok,'audio'=>$au]);if($ok)$right++;elseif($ok===null)$unk++;if($P['streak']>=5)break;}
  $P['tasks'][$step]=['label'=>$label,'right'=>$right,'unknown'=>$unk,'of'=>count($words),'need'=>$need];
  if($P['streak']>=5){$P['st']='fail';$P['note']='Stopped after 5 wrong words in a row.';}
  elseif($right>=$need){if($step+1>=count(RC_WORD_TASKS))$P['st']='pass';}
  elseif($right+$unk+(count($words)-$seen)>=$need&&$unk)$P['st']='listen';else $P['st']='fail';}
 elseif($p===5){$st=rc_story($g);$h=$heard('0');$au=$audio('0');$secs=max(0,min(1800,(int)($_POST['secs']??0)));$sr=(string)($_POST['sr']??'off');
  if($h===''&&$sr!=='ok'){$P['st']='listen';$P['ans'][]=['q'=>'Story','got'=>'','ok'=>null,'audio'=>$au,'secs'=>$secs];}
  else{$pct=$st?rc_accuracy($st[1],$h):0.0;$band=rc_band($pct);$P['pct']=$pct;$P['band']=$band;$P['secs']=$secs;$P['words']=$st?count($st[1]):0;$P['st']=$band==='Independent'?'pass':'fail';
   $P['ans'][]=['q'=>'Story','got'=>$h,'ok'=>$band==='Independent','audio'=>$au,'secs'=>$secs];}}
 else{$act=$P['acts'][$step]??null;$a=$act?one('SELECT * FROM activities WHERE id=?',[$act['aid']]):null;
  if($a)foreach(activity_key($a)??[] as $n=>$k){$gv=(string)($got[(int)$n]??'');$sc=rc_card_score($a,(int)$n,$gv)??[0,1];$set=l2a_set($a);$q=trim(preg_replace('~\s+~u',' ',(string)($set['cards'][(int)$n-1]['text']??'')));
   rc_mark($P,['q'=>$q!==''?mb_strimwidth($q,0,140,'…'):'Card '.$n,'got'=>$gv,'want'=>key_want($k),'ok'=>$sc[0]===$sc[1],'pts'=>$sc[0],'of'=>$sc[1]]);}}
 $last=$step+1>=rc_pages($p,$P);
 $over=$P['st']!=='run'||rc_decide($p,$P,$last,$s);
 if(!$over&&$last){$P['st']='fail';$over=true;}
 unset($P);
 if($over)rc_after_part($s,$p);else $s['step']=$step+1;
 $s['token']=bin2hex(random_bytes(6));rc_save((int)$row['id'],$s);
 if(!empty($s['result']))rc_finish(rc_row($pid),$s);
 rc_reply(true,'Saved!');
}
function rc_reply(bool $ok,string $msg):never{if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['saved'=>$ok,'message'=>$msg]);exit;}go('?page=rcheck');}

/** After a part: the next part, or the starting level. */
function rc_after_part(array &$s,int $p):void{
 $s['order'][]=$p;$st=$s['parts'][$p]['st'];$ok=in_array($st,['pass','rate'],true);
 $next=function(int $q)use(&$s){$s['part']=$q;$s['step']=0;$s['parts'][$q]=rc_part_new($q,(int)$s['grade']);};
 if($s['path']==='mid'){
  if($ok){$s['path']='up';$next(6);return;}
  if($st==='fail'&&($s['parts'][5]['band']??'')==='Frustration'){$s['path']='down';$next(4);return;}
  $s['result']=5;return;
 }
 if($s['path']==='down'){
  if($ok){$s['result']=$p+1;return;}if($st==='listen'||$p===1){$s['result']=$p;return;}$next($p-1);return;
 }
 if($ok){if($p>=7){$s['result']=8;return;}$next($p+1);return;}
 $s['result']=$p;
}
/** The check is over: save the level and give it to the pupil. */
function rc_finish(array $row,array $s):void{
 $pid=(int)$row['pupil_id'];$lv=(int)$s['result'];$s['part']=0;
 q("UPDATE reading_checks SET status='done',state=?,bulig_level=?,placed_level=?,finished_at=NOW() WHERE id=?",[json_encode($s,JSON_UNESCAPED_UNICODE),$lv,$lv,(int)$row['id']]);
 $tid=(int)val('SELECT teacher_id FROM teacher_pupils WHERE pupil_id=? LIMIT 1',[$pid]);
 if($tid&&!val('SELECT 1 FROM pupil_level_assignments WHERE pupil_id=?',[$pid]))q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?)',[$pid,$lv,$tid]);
 $name=(string)val('SELECT name FROM users WHERE id=?',[$pid]);
 if(function_exists('notify_teachers'))notify_teachers($pid,'reading',$name.' finished the reading check','BULIG placed '.explode(' ',trim($name))[0].' at '.level_label($lv).'.'.(rc_flags(rc_row($pid))?' Please take a look.':''),'?page=rcheck_pupil&id='.$pid);
 audit('reading_check',$pid.' → '.level_label($lv));
}

/** What the teacher still needs to do: 'listen' (recordings to hear or Part 1 to rate) and 'check' (official result does not match). */
function rc_flags(?array $row):array{
 if(!$row||$row['status']!=='done')return [];$s=rc_state($row);$f=[];
 if(empty($s['confirmed'])){foreach($s['parts'] as $p=>$P){if($P['st']==='listen'||($p===1&&$P['st']==='rate'&&$row['talk_rating']===null)){$f[]='listen';break;}}}
 if($row['official_result']&&!in_array((int)$row['placed_level'],RC_OFFICIAL[$row['official_tool']][$row['official_result']]??[],true)&&empty($s['official_ok']))$f[]='check';
 return $f;
}
/** Status chip for the teacher: [class, text]. */
function rc_status(?array $row,int $level):array{
 if(!$row)return $level?['tch','Level chosen by you']:['wait','Waiting · takes it at first sign-in'];
 if($row['status']==='running'){$s=rc_state($row);return ['run','Taking the check · Part '.(int)$s['part'].' of 7'];}
 $f=rc_flags($row);if(in_array('check',$f,true))return ['chk','Please check'];if($f)return ['chk','Please listen'];
 return $row['changed_by']?['tch','Changed by you']:['auto','Placed by BULIG'];
}
/** "Why this level" in a few words, for the pupil and the teacher. */
function rc_why(array $s,bool $pupil=true):string{
 $lv=(int)($s['result']??0);$passed=[];$failed='';$listen='';
 foreach($s['order'] as $p){$st=$s['parts'][$p]['st'];if(in_array($st,['pass','rate'],true))$passed[]=RC_PARTS[$p]['name'];elseif($st==='fail')$failed=RC_PARTS[$p]['name'];elseif($st==='listen')$listen=RC_PARTS[$p]['name'];}
 if($lv===8)return $pupil?'You did well in every part. You start at Level 7, where you read for fun and share stories.':'Passed every part.';
 $skill=rc_level_name($lv);
 if($pupil)return ($passed?'You did well in '.implode(', ',array_slice($passed,0,-1)).(count($passed)>1?' and ':'').end($passed).'. ':'').'Next, you will practise '.mb_strtolower($skill).'.';
 return trim(($passed?'Passed: '.implode(', ',$passed).'. ':'').($failed?'Not yet: '.$failed.'. ':'').($listen?'Please listen: BULIG could not hear the pupil in “'.$listen.'”, so it stopped there. Listen to the recording, then keep or change the level.':''));
}
/** Short result of each part for chips: [class, label]. */
function rc_part_chip(int $p,array $P):array{
 $label=['Talk','Sounds','Word parts','Words','Story','Listen','Understand'][$p-1];
 $score=match(true){$p===1=>'',$p===5&&isset($P['pct'])=>$P['pct'].'% '.$P['band'],$p===4&&!empty($P['tasks'])=>array_sum(array_column($P['tasks'],'right')).'/'.array_sum(array_column($P['tasks'],'of')),default=>$P['total']+$P['unknown']?$P['right'].'/'.($p===2?count(RC_SOUNDS):($p===3?count(RC_DIAG):($P['max']??$P['total']))):''};
 $cls=['pass'=>'ok','fail'=>'no','listen'=>'ear','rate'=>'ear','run'=>'na'][$P['st']]??'na';
 if($p===1)$score=$P['st']==='rate'?'please rate':'';
 if($P['st']==='listen')$score='please listen';
 return [$cls,$label.($score!==''?' · '.$score:'')];
}

/* ---------------- pupil screens ---------------- */

function rc_head(string $sub,array $s=[]):string{
 $bar='';if($s){$bar='<div class="ap-seg rk-seg" aria-hidden="true">';for($i=1;$i<=7;$i++)$bar.='<i class="'.(in_array($i,$s['order'],true)?'d':($i===(int)$s['part']?'c':'')).'"></i>';$bar.='</div>';}
 return '<header class="activity-header has-seg rk-header"><span class="iconbutton rk-logo" aria-hidden="true">'.l1_icon('book').'</span><div><strong>Reading check</strong><span>'.e($sub).'</span></div>'.($s?'<div class="activity-progress"><span>Part '.(int)$s['part'].' of 7</span>'.$bar.'</div>':'').'</header>';
}
function rc_pupil_view(array $u):void{
 $row=rc_row((int)$u['id']);
 if($row&&$row['status']==='done'){rc_result_page($u,$row);return;}
 if(!$row){if(!rc_needed($u))go('?page=dashboard');rc_intro_page($u);return;}
 $s=rc_state($row);$p=(int)$s['part'];
 if(in_array($p,[1,2,3,6,7],true))rc_deck_page($u,$row,$s);elseif($p===4)rc_words_page($u,$row,$s);else rc_story_page($u,$row,$s);
}
function rc_intro_page(array $u):void{
 $first=explode(' ',trim((string)$u['name']))[0];$g=pupil_grade((int)$u['id']);
 head('Reading check','activity-page l1-page rk-page');
 echo '<div class="l1-app l1-start-app">'.rc_head('Let’s find your reading level').'<main class="l1-main l1-start activity-main"><div class="l1-start-in"><div class="l1-start-top"><img class="l1-cover" src="assets/images/covers/level-2.webp" alt=""><div><span class="l1-tag">READING CHECK</span><h1>Hi '.e($first).'! Let’s find your reading level.</h1></div></div>';
 $say='Hi '.$first.'! Let’s find your reading level. The check has short parts. Just do your best!';
 echo '<div class="l1-goals"><p class="l1-lab">THE PARTS OF THE CHECK'.l1_speaker($say,'Hear about the check','sm').'</p><ul class="rk-parts">';
 foreach(RC_PARTS as $n=>$x)echo '<li'.($g>=4&&$n<5?' class="maybe"':'').'><i>'.$n.'</i><span>'.e($x['name']).'<small>'.e($x['what']).'</small></span></li>';
 echo '</ul></div><div class="rk-note">'.l1_icon('ear').'<span>About 15 to 20 minutes. You can rest between parts. Just do your best! BULIG stops when it knows where you should start, so you may not do every part. A quiet place helps BULIG hear you.</span></div>';
 echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="rc_begin"><button class="l1-big l1-go" type="submit">Let’s start! '.l1_icon('next').'</button></form></div></main></div>';foot();
}
function rc_result_page(array $u,array $row):void{
 $s=rc_state($row);$lv=(int)($row['placed_level']?:$row['bulig_level']);$first=explode(' ',trim((string)$u['name']))[0];
 head('Reading check','activity-page l1-page rk-page');
 echo '<div class="l1-app l1-start-app">'.rc_head('All done!').'<main class="l1-main l1-start activity-main"><div class="l1-start-in"><div class="l1-start-top"><img class="l1-cover" src="assets/images/covers/level-'.min(8,max(1,$lv)).'.webp" alt=""><div><span class="l1-tag">READING CHECK DONE</span><h1>Great reading, '.e($first).'!</h1></div></div>';
 echo '<div class="rk-big"><span class="l1-lab">YOU START AT</span><span class="lvl">'.e(level_label($lv)).'</span><span class="lvlname">'.e(rc_level_name($lv)).'</span></div>';
 echo '<div class="rk-why"><b>Why '.e(level_label($lv)).'?</b>'.e($row['changed_by']?'Your teacher chose this level for you.':rc_why($s)).'</div><ul class="rk-parts">';
 for($i=1;$i<=8;$i++){if($i>$lv+1&&$i<8)continue;if($i===8&&$lv<7){echo '<li class="lock"><i>'.l1_icon('star').'</i><span>'.e(level_label($lv+2<=8?$lv+2:8)).' to Level 7<small>Open one by one</small></span></li>';break;}
  echo '<li class="'.($i<$lv?'done':($i===$lv?'now':'lock')).'"><i>'.($i<$lv?l1_icon('ok'):e(str_replace('Level ','',level_label($i)))).'</i><span>'.e(level_label($i).' · '.rc_level_name($i)).'<small>'.($i<$lv?'Done well · you can still practise':($i===$lv?'Start here':'Opens after '.level_label($lv))).'</small></span></li>';}
 echo '</ul><a class="l1-big l1-go" href="?page=lessons">Go to my lessons '.l1_icon('next').'</a></div></main></div>';foot();
}
/** Card screens (Parts 1, 2, 3, 6 and 7): the same cards as the lessons, answered on the card. */
function rc_deck_page(array $u,array $row,array $s):void{
 $p=(int)$s['part'];$g=(int)$s['grade'];$step=(int)$s['step'];$P=$s['parts'][$p];$cards=[];$dir='';$title=RC_PARTS[$p]['name'];
 if($p===1){$dir='Listen to the question. Tap the microphone and answer in full sentences. Tap it again when you finish.';foreach(rc_talk_items($g) as $i=>$q)$cards[]=['c'=>['title'=>'','text'=>$q,'images'=>[],'choices'=>[],'ui'=>['answer'=>['say']],'speak'=>$q],'dir'=>'','bl'=>0];}
 elseif($p===2){$dir='Listen and look at each card. Tap the speaker to hear it again.';foreach(array_slice(RC_SOUNDS,$step*5,5) as [$pos,$cn,$d,$say]){$a=rc_act(2,$g,1,'pre',$pos);$set=$a?l2a_set($a):null;$c=$set['cards'][$cn-1]??null;if(!$c)continue;$c['speak']=$say;$bc=l2a_blank_counts($a,$set,2);$cards[]=['c'=>$c,'dir'=>$d,'bl'=>$bc[$cn-1]??0];}}
 elseif($p===3){$dir='Listen to each card. Tap the speaker to hear it again.';foreach(range($step*6,$step*6+5) as $i){$c=rc_diag_card($i);$cards[]=['c'=>$c,'dir'=>$c['dir'],'bl'=>0];}}
 else{$act=$P['acts'][$step]??null;$a=$act?one('SELECT * FROM activities WHERE id=?',[$act['aid']]):null;$set=$a?l2a_set($a):null;if(!$set){rc_skip_page($row,$s);return;}
  $dir=trim((string)$set['instruction']);$bc=l2a_blank_counts($a,$set,lesson_level((int)$a['lesson_id']));foreach($set['cards'] as $i=>$c)$cards[]=['c'=>$c,'dir'=>'','bl'=>$bc[$i]??0];}
 $pages=rc_pages($p,$P);$N=count($cards);
 head('Reading check','activity-page l1-page l2-page rk-page');
 echo '<div class="l1-app l2-app">'.rc_head(RC_PARTS[$p]['name'],$s).'<main class="l1-main activity-main">';notice();
 echo '<form method="post" id="activity-form" class="activity-form l1-form l2-form rk-form" data-mode="none" data-kind="cards" data-draft="off">'.csrf_field().'<input type="hidden" name="action" value="rc_submit"><input type="hidden" name="rc_token" value="'.e($s['token']).'"><input type="hidden" name="transcript" id="transcript" value=""><input type="hidden" name="drawing" id="drawing-data" value=""><input type="hidden" name="response" id="response" value=""><span id="draft-status" hidden></span>';
 echo '<span class="l1-tag">'.e(mb_strtoupper($title)).($pages>1?' · '.($step+1).' / '.$pages:'').'</span>'.($dir!==''?'<div class="l2-dir"><p>'.e($dir).'</p>'.l1_speaker($dir).'</div>':'');
 echo '<div class="l2-deck" data-l2-deck data-total="'.$N.'"'.(in_array($p,[1,2,3,6],true)?' data-autosay="1"':'').'>';
 foreach($cards as $i=>$x){$c=$x['c'];$n=$i+1;$kinds=$c['ui']['answer']??[];$tiles=in_array('tiles',$kinds,true);
  echo '<section class="l2-card" data-n="'.$n.'" data-kinds="'.e(implode(' ',$kinds)).'"'.($i?' hidden':'').'><div class="l2-cardtop"><span class="l2-cnum">'.($N>1?'Question '.$n.' of '.$N:'').'</span>'.($p===7?'':l1_speaker(trim((string)($c['speak']??(($c['text']??'')!==''?$c['text']:$dir))),'Hear this card','sm')).'</div>';
  if($x['dir']!=='')echo '<p class="rk-dir">'.e($x['dir']).'</p>';
  if(!empty($c['story']))echo '<h3 class="l2-story">'.e($c['story']).'</h3>';
  if(!empty($c['ui']['grid'])){$gr=$c['ui']['grid'];echo '<div class="l3-grid">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$gr)).'</div>'.l2a_pictures($c,$n);}
  elseif(in_array('match',$kinds,true))echo ((string)$c['text']!==''?l2a_text((string)$c['text'],false,'',$x['bl']):'').l2a_match_board($c,false,'');
  else echo ($p===1?'<p class="rk-q">'.e((string)$c['text']).'</p>':l2a_text((string)$c['text'],$tiles,(string)($c['underline']??''),$x['bl'])).l2a_pictures($c,$n);
  if(!empty($c['words']))echo '<p class="l2-banklab">'.e($c['words_label']??'Words').'</p><div class="l2-bank">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['words'])).'</div>';
  if(!empty($c['ui']['rcsay']))echo '<div class="l2-ans"><div class="rk-say" data-rc-say data-n="'.$n.'"><button type="button" class="l1-mic" data-rc-go aria-label="Tap and say the word">'.l1_icon('mic').'</button><b class="l1-rl" data-rc-label>Tap and say the word</b><small class="l1-rs" data-rc-sub role="status"></small><input type="hidden" name="heard['.$n.']" data-rc-heard value=""><input type="hidden" name="audio_card['.$n.']" data-rc-audio value=""></div><label class="l2-write rk-hide" data-part="0" data-kind="write"><span class="sr-only">Your answer</span><textarea rows="1" tabindex="-1"></textarea></label></div>';
  else echo l2a_answer($c,$n,false);
  echo '</section>';}
 echo '</div><div class="l1-end"><div id="answer-feedback" role="status" aria-live="polite" class="answer-feedback l1-fb"></div><div class="l1-btns"><button type="button" class="l1-btn2" data-l2-prev hidden>'.l1_icon('prev').'Back</button><button type="button" class="l1-big" data-l2-next'.($N<2?' hidden':'').'>Next '.l1_icon('next').'</button><button id="submit-answer" class="l1-big" type="submit"'.($N>1?' hidden':'').'>Done '.l1_icon('ok').'</button><a id="next-activity" class="l1-big" href="?page=rcheck" hidden>Next '.l1_icon('next').'</a></div></div></form></main></div>';
 echo '<script defer src="'.asset_url('rcheck.js').'"></script>';foot();
}
/** A page with nothing to answer (a test page without an answer key) is skipped. */
function rc_skip_page(array $row,array $s):never{$p=(int)$s['part'];$P=&$s['parts'][$p];$last=(int)$s['step']+1>=rc_pages($p,$P);if(rc_decide($p,$P,$last,$s)||$last){if($P['st']==='run')$P['st']='fail';unset($P);rc_after_part($s,$p);}else{unset($P);$s['step']++;}rc_save((int)$row['id'],$s);if(!empty($s['result']))rc_finish(rc_row((int)$row['pupil_id']),$s);go('?page=rcheck');}
/** Part 4: read each word aloud. BULIG listens to one word at a time (rcheck.js), then the next word comes. */
function rc_words_page(array $u,array $row,array $s):void{
 $g=(int)$s['grade'];$step=(int)$s['step'];[$pos,$need,$label]=RC_WORD_TASKS[$step];$a=rc_act(4,$g,1,'pre',$pos);$words=$a?l3_list((string)$a['expected_text']):[];
 head('Reading check','activity-page l1-page l2-page rk-page');
 echo '<div class="l1-app l2-app">'.rc_head('Read the words',$s).'<main class="l1-main activity-main">';notice();
 $dir='Here are some words I would like you to read. Do not spell the words, just read them.';
 echo '<form method="post" class="rk-form" data-rc-words data-total="'.count($words).'">'.csrf_field().'<input type="hidden" name="action" value="rc_submit"><input type="hidden" name="rc_token" value="'.e($s['token']).'"><input type="hidden" name="sr" value="" data-rc-sr>';
 echo '<span class="l1-tag">READ THE WORDS · '.($step+1).' / '.count(RC_WORD_TASKS).'</span><div class="l2-dir"><p>'.e($dir).'</p>'.l1_speaker($dir).'</div>';
 echo '<section class="l2-card rk-wordcard"><div class="l2-cardtop"><span class="l2-cnum" data-rc-count>Word 1 of '.count($words).'</span><span class="rk-task">'.e($label).'</span></div>';
 foreach($words as $i=>$w)echo '<div class="rk-word" data-rc-w="'.$i.'"'.($i?' hidden':'').'>'.e($w).'<input type="hidden" name="heard['.$i.']" value="" disabled><input type="hidden" name="audio['.$i.']" value="" disabled></div>';
 echo '<div class="l2-ans"><div class="l2-say l1-rec rk-say" data-rc-say data-word><button type="button" class="l1-mic" data-rc-go aria-label="Tap and read the word">'.l1_icon('mic').'</button><b class="l1-rl" data-rc-label>Tap and read the word</b><small class="l1-rs" data-rc-sub role="status"></small></div></div>';
 echo '<p class="rk-hear">BULIG marks the word. Your teacher can listen again later.</p></section>';
 echo '<div class="l1-end"><div class="l1-btns"><button type="button" class="l1-btn2" data-rc-skip>I don’t know this word '.l1_icon('next').'</button><button class="l1-big" type="submit" data-rc-send hidden>Done '.l1_icon('ok').'</button></div></div></form></main></div>';
 echo '<script defer src="'.asset_url('rcheck.js').'"></script>';foot();
}
/** Part 5: the grade's Level 4 pre-test passage, read aloud once. */
function rc_story_page(array $u,array $row,array $s):void{
 $g=(int)$s['grade'];$st=rc_story($g);if(!$st){$s['parts'][5]['st']='fail';rc_after_part($s,5);rc_save((int)$row['id'],$s);if(!empty($s['result']))rc_finish(rc_row((int)$row['pupil_id']),$s);go('?page=rcheck');}
 head('Reading check','activity-page l1-page l4-page rk-page');
 echo '<div class="l1-app l4-app">'.rc_head('Read a story',$s).'<main class="l1-main activity-main">';notice();
 $dir='Tap the microphone, then read the title and the story out loud. Tap it again when you finish.';
 echo '<form method="post" class="rk-form" data-rc-story data-words="'.count($st[1]).'">'.csrf_field().'<input type="hidden" name="action" value="rc_submit"><input type="hidden" name="rc_token" value="'.e($s['token']).'"><input type="hidden" name="sr" value="" data-rc-sr><input type="hidden" name="heard[0]" value="" data-rc-heard><input type="hidden" name="audio[0]" value="" data-rc-audio><input type="hidden" name="secs" value="" data-rc-secs>';
 echo '<span class="l1-tag">READ A STORY</span><div class="l1-q sm l4-dir"><p>'.e($dir).'</p>'.l1_speaker($dir).'</div>';
 /* The microphone (and Send) sit at the top and stay on screen, so the pupil taps it and reads straight down the story. */
 echo '<div class="rk-storybar"><div class="l1-rec l4-rec rk-say" data-rc-say data-story><button type="button" class="l1-mic" data-rc-go aria-label="Tap and read the story">'.l1_icon('mic').'</button><b class="l1-rl" data-rc-label>Tap and read the title and the story</b><small class="l1-rs" data-rc-sub role="status"></small></div><button class="l1-big rk-send" type="submit" data-rc-send hidden>Send '.l1_icon('next').'</button></div>';
 echo '<section class="l2-card l1-card l4-card">'.level4_passage($st[0]).'</section></form></main></div>';
 echo '<script defer src="'.asset_url('rcheck.js').'"></script>';foot();
}

/* ---------------- actions ---------------- */

function rc_action(string $action):void{
 if($action==='rc_begin'){$u=require_role('pupil');if(!rc_ready())fail('The reading check is not installed yet. Please tell your teacher.');$row=rc_row((int)$u['id']);if(!$row||$row['status']!=='running'){if(!rc_needed($u))go('?page=dashboard');rc_begin($u);}go('?page=rcheck');}
 if($action==='rc_submit'){rc_submit(require_role('pupil'));}
 $t=require_role('teacher');$pid=(int)($_POST['id']??0);own_pupil($pid);$row=rc_row($pid);$back='?page=rcheck_pupil&id='.$pid;
 if($action==='rc_level'){
  $lv=(int)($_POST['level_id']??0);if(!val('SELECT id FROM bulig_levels WHERE id=? AND published=1',[$lv]))fail('Choose a starting level.');$note=mb_substr(trim((string)($_POST['note']??'')),0,1000);
  q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE level_id=VALUES(level_id),assigned_by=VALUES(assigned_by),assigned_at=NOW()',[$pid,$lv,$t['id']]);
  if($row){$s=rc_state($row);$s['confirmed']=true;q('UPDATE reading_checks SET placed_level=?,changed_by=?,teacher_note=?,state=? WHERE id=?',[$lv,$lv===(int)$row['bulig_level']&&$row['changed_by']===null?null:(int)$t['id'],$note!==''?$note:$row['teacher_note'],json_encode($s,JSON_UNESCAPED_UNICODE),(int)$row['id']]);}
  push_queue($pid,'lessons',level_label($lv).' is open!','Your teacher chose '.level_label($lv).' for you. Your next adventure is waiting.','?page=lessons&level='.$lv);
  audit('reading_check_level',$pid.' → '.level_label($lv));flash('Starting level saved: '.level_label($lv).'.');go($back);
 }
 if(!$row)fail('This pupil has not taken the reading check.');$s=rc_state($row);
 if($action==='rc_confirm'){$s['confirmed']=true;$s['official_ok']=true;rc_save((int)$row['id'],$s);flash('Marked as checked. The level stays '.level_label((int)$row['placed_level']).'.');go($back);}
 if($action==='rc_official'){
  $tool=rc_tool((int)$row['grade']);$res=(string)($_POST['official']??'');if($res!==''&&!isset(RC_OFFICIAL[$tool][$res]))fail('Choose a result from the list.');unset($s['official_ok']);rc_save((int)$row['id'],$s);
  q('UPDATE reading_checks SET official_tool=?,official_result=? WHERE id=?',[$res===''?null:$tool,$res===''?null:$res,(int)$row['id']]);flash($res===''?'Official result removed.':'Official '.$tool.' result saved: '.$res.'.');go($back);
 }
 if($action==='rc_rate'){
  $r=l1_rubric(1);$v=l1_rubric_values($r,(array)($_POST['rubric']??[]));if(!$v)fail('Choose one box in each row of the rubric.');
  q('UPDATE reading_checks SET talk_rating=? WHERE id=?',[implode(',',$v),(int)$row['id']]);flash('Talk and listen rated: '.array_sum($v).' of 12.');go($back.'#rc-talk');
 }
 if($action==='rc_reset'){
  if(empty($_POST['sure']))fail('Tick the box to start the check again.');q('DELETE FROM reading_checks WHERE id=?',[(int)$row['id']]);
  q('DELETE FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);audit('reading_check_reset',(string)$pid);flash('The reading check starts again the next time this pupil signs in.');go('?page=rcheck');
 }
 fail('Unknown action.');
}

/* ---------------- teacher screens ---------------- */

function rc_teacher_rows(int $tid,int $section=0):array{
 $rc=rc_ready();
 return rows('SELECT u.id,u.name,u.public_id,u.avatar_path,u.role,p.grade_level,ps.section_id,s.name section,a.level_id,'.($rc?'rc.id':'NULL').' rc_id FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id AND u.active=1 JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN sections s ON s.id=ps.section_id LEFT JOIN pupil_level_assignments a ON a.pupil_id=u.id'.($rc?' LEFT JOIN reading_checks rc ON rc.pupil_id=u.id':'').' WHERE t.teacher_id=? AND ('.($rc?'rc.id IS NOT NULL OR ':'').'a.level_id IS NULL)'.($section?' AND ps.section_id=?':'').' ORDER BY p.grade_level,s.name,u.name',$section?[$tid,$section]:[$tid]);
}
/** Pupils whose reading check needs the teacher (Home "Reading checks to look at"). */
function rc_todo_count(int $tid):int{if(!rc_ready())return 0;$n=0;foreach(rows("SELECT rc.* FROM reading_checks rc JOIN teacher_pupils t ON t.pupil_id=rc.pupil_id WHERE t.teacher_id=? AND rc.status='done'",[$tid]) as $r)if(rc_flags($r))$n++;return $n;}
function rc_chips(array $s):string{$h='';foreach($s['order']??[] as $p){[$c,$l]=rc_part_chip($p,$s['parts'][$p]);$h.='<span class="rk-pt '.$c.'">'.rc_chip_icon($c).e($l).'</span>';}if(!empty($s['part'])&&isset($s['parts'][$s['part']])&&!in_array($s['part'],$s['order']??[],true)){$h.='<span class="rk-pt na">'.e(RC_PARTS[$s['part']]['name']).' · now</span>';}return $h;}
function rc_chip_icon(string $c):string{return ['ok'=>l1_icon('ok'),'no'=>l1_icon('x'),'ear'=>l1_icon('ear')][$c]??'';}

function rc_teacher_view(array $u):void{
 $tid=(int)$u['id'];$sid=(int)($_GET['section']??0);$list=rc_teacher_rows($tid,$sid);
 echo '<div class="hm-head"><div><span class="eyebrow">MY CLASS · READING CHECK</span><h1>Where does each pupil start?</h1><p class="muted">Pupils with no starting level take the reading check the first time they sign in. BULIG places them with the BULIG Assessment and Grouping guide and the module pre-tests. You can change any level.</p></div><div class="buttonrow"><a class="btn secondary" href="?page=rcheck_rules">'.icon('info').'How BULIG decides</a><a class="btn secondary" href="?page=rcheck_print'.($sid?'&amp;section='.$sid:'').'">'.icon('print').'Print results</a></div></div>';
 if(!rc_ready()){echo '<section class="card"><h2>'.icon('alert').' One database file is needed first</h2><p>Import <b>database/migrations/028_reading_check.sql</b> once in phpMyAdmin. Until then, choose each new pupil’s starting level yourself.</p></section>';return;}
 $secs=teacher_sections($tid);
 if(count($secs)>1){echo '<form method="get" class="rk-filter"><input type="hidden" name="page" value="rcheck"><label>Section<select name="section" data-autosubmit><option value="0">All my pupils</option>';foreach($secs as $x)echo '<option value="'.(int)$x['id'].'"'.($sid===(int)$x['id']?' selected':'').'>Grade '.(int)$x['grade_level'].' · '.e($x['name']).'</option>';echo '</select></label><noscript><button class="btn quiet">Show</button></noscript></form>';}
 $cnt=['auto'=>0,'tch'=>0,'chk'=>0,'wait'=>0,'run'=>0];$rowsH='';
 foreach($list as $r){$row=$r['rc_id']?rc_row((int)$r['id']):null;$s=rc_state($row);[$cls,$txt]=rc_status($row,(int)$r['level_id']);$cnt[$cls]=($cnt[$cls]??0)+1;$lv=(int)$r['level_id'];
  $off=$row&&$row['official_result']?'<small class="rk-off">'.e($row['official_tool'].': '.$row['official_result']).'</small>':'';
  $rowsH.='<tr><td><div class="rk-who">'.avatar($r).'<span><b>'.e($r['name']).'</b><small>Grade '.(int)$r['grade_level'].($r['section']?' · '.e($r['section']):'').'</small></span></div></td>'
   .'<td>'.($row?rc_chips($s):'<span class="rk-pt na">Not taken yet · takes it at first sign-in</span>').'</td>'
   .'<td>'.($lv?'<span class="rk-lv">'.e(level_label($lv)).'<small>'.e(rc_level_name($lv)).($row&&$row['bulig_level']&&(int)$row['bulig_level']!==$lv?' · BULIG said '.e(str_replace('Level ','',level_label((int)$row['bulig_level']))):'').'</small></span>':'<span class="muted">—</span>').$off.'</td>'
   .'<td><span class="rk-chip '.$cls.'">'.e($txt).'</span></td><td><div class="rk-btns"><a class="btn '.($lv?'secondary':'primary').' small" href="?page=rcheck_pupil&amp;id='.(int)$r['id'].'">'.($row?'Open':'Set level').'</a></div></td></tr>';}
 echo '<div class="rk-sum"><span><b>'.$cnt['auto'].'</b> placed by BULIG</span><span><b>'.$cnt['tch'].'</b> changed by you</span><span><b>'.$cnt['chk'].'</b> please check</span><span><b>'.($cnt['wait']+$cnt['run']).'</b> not finished yet</span></div>';
 echo '<section class="card"><div class="tablewrap"><table class="rk-tbl"><thead><tr><th>Pupil</th><th>Parts of the check</th><th>Starting level · official result</th><th>Status</th><th></th></tr></thead><tbody>'.($rowsH?:'<tr class="empty-row"><td colspan="5">No pupils are waiting for the reading check. When you add a pupil with “Let BULIG find it”, the pupil shows here.</td></tr>').'</tbody></table></div><p class="rk-src">The check stops at the first part not passed. That part’s level is the starting level. “Please listen”: BULIG could not hear the reading, or Talk and listen needs your rating. “Please check”: BULIG’s level is not one the guide gives for the official CRLA / Phil-IRI result you typed. Pupils whose level you chose yourself are not listed.</p></section>';
}

function rc_teacher_pupil(array $u):void{
 $pid=(int)($_GET['id']??0);own_pupil($pid);$p=one('SELECT u.id,u.name,u.public_id,p.grade_level,s.name section FROM users u JOIN pupils p ON p.user_id=u.id LEFT JOIN pupil_sections ps ON ps.pupil_id=u.id LEFT JOIN sections s ON s.id=ps.section_id WHERE u.id=?',[$pid]);if(!$p)fail('Pupil not found.',404);
 $row=rc_row($pid);$s=rc_state($row);$lv=(int)val('SELECT level_id FROM pupil_level_assignments WHERE pupil_id=?',[$pid]);[$cls,$txt]=rc_status($row,$lv);
 echo '<p class="crumb"><a href="?page=rcheck">Reading check</a>'.icon('chev').'<span>'.e($p['name']).'</span></p>';
 echo '<div class="hm-head"><div><span class="eyebrow">READING CHECK · GRADE '.(int)$p['grade_level'].($p['section']?' · '.e(mb_strtoupper($p['section'])):'').'</span><h1>'.e($p['name']).'</h1><p class="muted">'.($lv?'Starts at <b>'.e(level_label($lv).' · '.rc_level_name($lv)).'</b>':'No starting level yet').($row&&$row['bulig_level']?' · BULIG chose '.e(level_label((int)$row['bulig_level'])):'').'</p></div><div class="buttonrow"><span class="rk-chip '.$cls.'">'.e($txt).'</span><a class="btn secondary" href="?page=pupil&amp;id='.$pid.'">'.icon('user').'Pupil page</a></div></div>';
 if($row&&$row['status']==='done'){echo '<div class="rk-whybox">'.icon('info').'<div><b>Why '.e(level_label((int)$row['bulig_level'])).'?</b> '.e(rc_why($s,false)).'</div></div>';}
 $flags=rc_flags($row);
 echo '<div class="rk-cols"><section class="card"><h2>'.icon('ladder').' Starting level</h2><form method="post" class="formgrid rk-levelform">'.csrf_field().'<input type="hidden" name="action" value="rc_level"><input type="hidden" name="id" value="'.$pid.'">';
 if($row&&$row['bulig_level']){$tool=$row['official_tool'];echo '<p class="rk-hint"><b>BULIG check:</b> '.e(level_label((int)$row['bulig_level']).' · '.rc_level_name((int)$row['bulig_level'])).'.'.($row['official_result']?'<br><b>Guide:</b> '.e($tool.' '.$row['official_result']).' → '.e(implode(', ',array_map(fn($x)=>str_replace('Level ','',level_label($x)),RC_OFFICIAL[$tool][$row['official_result']]??[]))).' (BULIG Assessment and Grouping).':'').'</p>';}
 echo '<label>New starting level<select name="level_id">';foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id') as $l)echo '<option value="'.(int)$l['id'].'"'.((int)$l['id']===($lv?:(int)($row['bulig_level']??1))?' selected':'').'>'.e(level_label((int)$l['id']).' · '.rc_level_name((int)$l['id'])).'</option>';echo '</select></label>';
 echo '<label>Your observation (why)<textarea name="note" rows="3" maxlength="1000" placeholder="For example: has difficulty blending two consonant sounds: reads “flag” as “falag”.">'.e((string)($row['teacher_note']??'')).'</textarea></label><p class="muted">Work already done is kept. Levels before the new starting level become free practice.</p><button class="btn primary">'.icon('check').'Save level</button></form>';
 if($flags)echo '<form method="post" class="rk-confirm">'.csrf_field().'<input type="hidden" name="action" value="rc_confirm"><input type="hidden" name="id" value="'.$pid.'"><button class="btn secondary">'.icon('check').'I checked it · keep '.e(level_label($lv)).'</button></form>';
 echo '</section>';
 if($row){$tool=rc_tool((int)$row['grade']);echo '<section class="card"><h2>'.icon('chart').' Official result (start of the year)</h2><p class="muted">Type the pupil’s BOSY '.$tool.' result. BULIG compares it with the table in the BULIG Assessment and Grouping guide.</p><form method="post" class="formgrid">'.csrf_field().'<input type="hidden" name="action" value="rc_official"><input type="hidden" name="id" value="'.$pid.'"><label>'.$tool.' result<select name="official"><option value="">Not typed</option>';
  foreach(RC_OFFICIAL[$tool] as $k=>$lvls)echo '<option value="'.e($k).'"'.($row['official_result']===$k?' selected':'').'>'.e($k.($k==='Advanced'?' pupils (guide)':'')).' → '.e(implode(', ',array_map(fn($x)=>str_replace('Level ','L',level_label($x)),$lvls))).'</option>';
  echo '</select></label><button class="btn secondary">'.icon('check').'Save result</button></form></section>';}
 echo '</div>';
 if(!$row){echo '<section class="card"><p>'.e($p['name']).' has not taken the reading check'.($lv?'. You chose the starting level yourself.':'. It starts the first time they sign in.').'</p></section>';return;}
 /* each part */
 foreach($s['order'] as $pn){$P=$s['parts'][$pn];[$c,$l]=rc_part_chip($pn,$P);
  echo '<section class="card rk-part" id="'.($pn===1?'rc-talk':'rc-part'.$pn).'"><div class="section-heading"><h2>Part '.$pn.' · '.e(RC_PARTS[$pn]['name']).' <small class="muted">'.e(level_label(RC_PARTS[$pn]['level']).' · '.rc_level_name(RC_PARTS[$pn]['level'])).'</small></h2><span class="rk-pt '.$c.'">'.rc_chip_icon($c).e($l).'</span></div>';
  if(!empty($P['note']))echo '<p class="muted">'.e($P['note']).'</p>';
  if($pn===5&&isset($P['pct']))echo '<p class="rk-hint"><b>Word reading:</b> '.e((string)$P['pct']).'% of '.(int)$P['words'].' words · Phil-IRI '.e($P['band']).($P['secs']?' · '.intdiv((int)$P['secs'],60).':'.sprintf('%02d',(int)$P['secs']%60).' minutes':'').'. BULIG counts the words its speech recognition heard; listen to the recording to check.</p>';
  if($pn===4&&!empty($P['tasks'])){echo '<p class="rk-hint">';foreach($P['tasks'] as $tk)echo '<b>'.e($tk['label']).':</b> '.(int)$tk['right'].' of '.(int)$tk['of'].' (pass '.(int)$tk['need'].')'.($tk['unknown']?' · '.(int)$tk['unknown'].' to listen to':'').'<br>';echo '</p>';}
  echo '<ol class="ks-list rk-ans">';foreach($P['ans'] as $i=>$a){$ok=$a['ok'];
   echo '<li class="'.($ok===true?'ok':($ok===false?'no':'part')).'"><span class="ks-q">'.e(mb_strimwidth((string)$a['q'],0,160,'…')).'</span><span class="ks-a">'.($a['got']!==''?e(mb_strimwidth((string)$a['got'],0,300,'…')):'<i>'.($a['audio']??null?'Recording':'No answer').'</i>').'</span><span class="ks-r">'.($ok===true?l1_icon('ok').'Right':($ok===false?'Not yet'.(!empty($a['want'])?' · Answer: '.e((string)$a['want']):''):l1_icon('ear').'Please listen')).'</span>'.(!empty($a['audio'])?'<audio controls preload="none" src="?page=rc_audio&amp;id='.(int)$row['id'].'&amp;p='.$pn.'&amp;i='.$i.'"></audio>':'').'</li>';}
  echo '</ol>';
  if($pn===1){$r=l1_rubric(1);$v=$row['talk_rating']!==null?array_map('intval',explode(',',(string)$row['talk_rating'])):[];
   echo '<form method="post" class="rk-rate">'.csrf_field().'<input type="hidden" name="action" value="rc_rate"><input type="hidden" name="id" value="'.$pid.'"><fieldset class="rv-rub"><legend>'.l1_icon('list').'Rate with the Level 1 module rubric · passed at Good (9 of 12)</legend>';
   foreach($r['criteria'] as $i=>[$name,$cells]){echo '<div class="rv-rub-row" role="radiogroup" aria-label="'.e($name).'"><b>'.e($name).'</b><div class="rv-rub-cells">';foreach($cells as $pts=>$desc)echo '<label class="rv-rub-cell"><input type="radio" name="rubric['.$i.']" value="'.$pts.'"'.(($v[$i]??0)===$pts?' checked':'').'><span><strong>'.$pts.' · '.e(L1_SCALE[$pts]).'</strong><small>'.e($desc).'</small></span></label>';echo '</div></div>';}
   echo '</fieldset><button class="btn primary">'.icon('check').'Save rating</button></form>';
   if($v&&array_sum($v)<9&&$lv>1)echo '<div class="rk-suggest">'.icon('alert').'<div><b>Talk and listen is below Good ('.array_sum($v).' of 12).</b> With the module rubric, this pupil starts at Level 1 · Oral Language.<form method="post">'.csrf_field().'<input type="hidden" name="action" value="rc_level"><input type="hidden" name="id" value="'.$pid.'"><input type="hidden" name="level_id" value="1"><input type="hidden" name="note" value="Talk and listen rated '.array_sum($v).' of 12 on the Level 1 rubric."><button class="btn secondary">Move to Level 1</button></form></div></div>';}
  echo '</section>';}
 if($row['status']==='running')echo '<section class="card"><p>'.icon('clock').' '.e($p['name']).' is taking the check now (Part '.(int)$s['part'].').</p></section>';
 echo '<section class="card rk-reset"><h2>'.icon('replay').' Take the check again</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="rc_reset"><input type="hidden" name="id" value="'.$pid.'"><label class="checkbox"><input type="checkbox" name="sure" value="1"> Remove this result and the starting level. The pupil takes the reading check again at the next sign-in. Lessons already done are kept.</label><button class="btn quiet">Start again</button></form></section>';
}

function rc_rules_view():void{
 $Q='<span class="rk-q2">teacher</span>';
 echo '<p class="crumb"><a href="?page=rcheck">Reading check</a>'.icon('chev').'<span>How BULIG decides</span></p><div class="hm-head"><div><span class="eyebrow">READING CHECK</span><h1>How BULIG decides the starting level</h1><p class="muted">From the BULIG Assessment and Grouping guide (L. M. P. Solijon), the BULIG module pre-tests and Phil-IRI. BULIG adds no pass marks of its own.</p></div></div>';
 echo '<section class="card"><h2>1 · The parts of the check</h2><div class="tablewrap"><table class="rk-tbl"><thead><tr><th>Part</th><th>Checks</th><th>Questions from</th><th>Passed when</th></tr></thead><tbody>'
  .'<tr><td>1 Talk and listen</td><td>Level 1 · Oral Language</td><td>Level 1 pre-test, 5 questions (recorded)</td><td>“Good” or better on the module rubric: 9 of 12 '.$Q.' <span class="rk-src">(Level 1 module rubric; you rate the recordings)</span></td></tr>'
  .'<tr><td>2 Sound games</td><td>Level 2A · Phonological Awareness</td><td>Level 2A pretests, one item from each of the 10 skills</td><td>80–100% correct (8 of 10) <span class="rk-src">(Level 2B module, Annex A: proficient)</span></td></tr>'
  .'<tr><td>3 Word parts</td><td>Level 2B · Phonological Awareness</td><td>Level 2B Pre-Test: Phonological Awareness, 30 items</td><td>24 of 30 <span class="rk-src">(Level 2B module, “Interpretation – 30 items test”: passed and recommended for the next reading level)</span></td></tr>'
  .'<tr><td>4 Read the words</td><td>Level 3 · Word Recognition</td><td>Level 3 Pre-Assessment Toolkit, 4 word lists</td><td>8 of 10 short-vowel words, 8 of 10 word families, 15 of 20 blends, 8 of 10 sight words <span class="rk-src">(Level 3 module, toolkit)</span></td></tr>'
  .'<tr><td>5 Read a story</td><td>Level 4 · Fluency</td><td>The grade’s Level 4 pre-test passage</td><td>Phil-IRI word reading 97–100% Independent; 90–96% Instructional; 89% and below Frustration <span class="rk-src">(Level 4 module, Phil-IRI profile table)</span></td></tr>'
  .'<tr><td>6 Listen and learn words</td><td>Level 5 · Listening Comprehension and Vocabulary</td><td>The grade’s Level 5 pre-test (its first pages, about 30 points)</td><td>Phil-IRI comprehension Independent: 80–100% <span class="rk-src">(Phil-IRI; the module has no pass mark)</span></td></tr>'
  .'<tr><td>7 Read and understand</td><td>Level 6 · Graded Reading Comprehension</td><td>The grade’s Level 6 reading comprehension test (about 30 points)</td><td>Phil-IRI comprehension Independent: 80–100% <span class="rk-src">(Phil-IRI; the module has no pass mark)</span></td></tr>'
  .'</tbody></table></div><p class="muted">Grades 1–3 start at Part 1 and go up. Grades 4–6 start at Part 5 (the grade’s story, like Phil-IRI): Independent goes up to Parts 6 and 7; Instructional starts at Level 4; Frustration goes down to Parts 4, 3, 2 and 1. The first part not passed gives the starting level. All parts passed: Level 7. A part also stops after 5 wrong answers in a row (Level 2B module, Annex A: discontinue rule).</p>'
  .'<p class="muted">Reading aloud (Parts 3, 4 and 5) is scored with the browser’s speech recognition and recorded. When BULIG cannot hear the reading (no internet, no microphone), the part shows “Please listen” and the pupil starts at that part’s level for now. Part 1 is always rated by you; until then the check goes on.</p></section>';
 echo '<section class="card"><h2>2 · Checked against the official result</h2><div class="rk-rules"><div><h3>Grades 1–3 · CRLA</h3><table><tr><th>CRLA profile</th><th>BULIG level</th></tr><tr><td>Low / High Emerging</td><td>Level 1 or 2</td></tr><tr><td>Developing</td><td>Level 2 or 3</td></tr><tr><td>Transitioning</td><td>Level 3, 4 or 5</td></tr><tr><td>Grade Ready</td><td>Level 5 or 6</td></tr><tr><td>Advanced pupils</td><td>Level 7</td></tr></table></div><div><h3>Grades 4–6 · Phil-IRI</h3><table><tr><th>Phil-IRI level</th><th>BULIG level</th></tr><tr><td>Frustration</td><td>Level 1, 2 or 3</td></tr><tr><td>Instructional</td><td>Level 4 or 5</td></tr><tr><td>Independent</td><td>Level 5 or 6</td></tr><tr><td>Advanced pupils</td><td>Level 7</td></tr></table></div></div><p class="muted">Type each pupil’s BOSY CRLA or Phil-IRI result on their reading check page. If BULIG’s level is not in the matching row, the pupil shows “Please check”. The guide says the group is the official result plus the teacher’s observation, so you can always change the level.</p></section>';
}

function rc_print_view(array $u):void{
 $tid=(int)$u['id'];$sid=(int)($_GET['section']??0);$list=rc_teacher_rows($tid,$sid);$title=$sid?(function($s){return 'Grade '.(int)$s['grade_level'].' · '.$s['name'];})(owned_section($sid,$tid)):'All my pupils';
 echo '<div class="no-print rk-actions"><div><span class="eyebrow">READING CHECK RESULTS</span><h1>'.e($title).'</h1><p class="muted">Each pupil’s starting level, the parts of the check and the official result.</p></div><div class="rk-btns"><a class="btn quiet" href="?page=rcheck">'.icon('arrow','flip').'Back</a><button type="button" class="btn primary" data-print>'.icon('print').'Print or save as PDF</button></div></div>';
 echo '<section class="card rk-printsheet"><h2>Reading check results · '.e($title).'</h2><p class="muted">Teacher: '.e($u['name']).' · Printed '.e(date('F j, Y')).'</p><table class="rk-tbl"><thead><tr><th>Pupil</th><th>Grade</th><th>Parts of the check</th><th>Starting level</th><th>Official result</th><th>Status</th></tr></thead><tbody>';
 foreach($list as $r){$row=$r['rc_id']?rc_row((int)$r['id']):null;$s=rc_state($row);[$cls,$txt]=rc_status($row,(int)$r['level_id']);$lv=(int)$r['level_id'];
  $parts=[];foreach($s['order']??[] as $p)$parts[]=rc_part_chip($p,$s['parts'][$p])[1];
  echo '<tr><td><b>'.e($r['name']).'</b><br><small>'.e($r['public_id']).'</small></td><td>'.(int)$r['grade_level'].'</td><td>'.e($parts?implode('; ',$parts):'Not taken yet').'</td><td>'.($lv?e(level_label($lv).' · '.rc_level_name($lv)):'—').'</td><td>'.e($row&&$row['official_result']?$row['official_tool'].': '.$row['official_result']:'—').'</td><td>'.e($txt).'</td></tr>';}
 echo '</tbody></table><p class="rk-src">BULIG reading check: module pre-tests and the BULIG Assessment and Grouping guide. Standard assessment results + teacher’s observation = BULIG grouping.</p></section>';
}

/** Play a reading check recording (the pupil's teacher only). */
function rc_audio(array $u):never{
 require_role('teacher');$row=one('SELECT * FROM reading_checks WHERE id=?',[(int)($_GET['id']??0)]);if(!$row)fail('Recording not found.',404);own_pupil((int)$row['pupil_id']);
 $s=rc_state($row);$path=(string)($s['parts'][(int)($_GET['p']??0)]['ans'][(int)($_GET['i']??-1)]['audio']??'');
 if(!preg_match('~^\d+/\d+-\d{14}-[0-9a-f]{8}\.(webm|ogg|m4a|mp3|aac)$~',$path,$m))fail('Recording not found.',404);
 $f=l1_recordings_dir().'/'.$path;if(!is_file($f))fail('This recording is no longer on the server.',404);
 while(ob_get_level())ob_end_clean();
 header('Content-Type: '.['webm'=>'audio/webm','ogg'=>'audio/ogg','m4a'=>'audio/mp4','mp3'=>'audio/mpeg','aac'=>'audio/aac'][$m[1]]);header('Cache-Control: private, max-age=3600');header('X-Content-Type-Options: nosniff');header('Accept-Ranges: bytes');
 $size=filesize($f);$start=0;$end=$size-1;
 if(preg_match('~^bytes=(\d*)-(\d*)$~',(string)($_SERVER['HTTP_RANGE']??''),$r)){if($r[1]===''&&$r[2]!=='')$start=max(0,$size-(int)$r[2]);else{$start=(int)$r[1];if($r[2]!=='')$end=min($end,(int)$r[2]);}if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}
 header('Content-Length: '.($end-$start+1));$h=fopen($f,'rb');fseek($h,$start);$left=$end-$start+1;while($left>0&&!feof($h)){$c=fread($h,min(65536,$left));echo $c;$left-=strlen($c);}fclose($h);exit;
}
