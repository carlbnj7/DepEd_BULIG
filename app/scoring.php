<?php
/* Auto-scoring for Levels 5, 6 and 7 (level ids 6, 7, 8).
   - database/answer-keys.json is built from database/keys/*.txt by tools/keys/build.php.
     Each answer was checked against the story; where the module's own answer key is wrong, the right answer is used
     and the card is marked "fix" (the note says what the module key had).
   - A tapped answer is right or wrong (1 point). A matching board scores 1 point per pair, numbering events 1 point per event.
   - Written, drawn and recorded answers are not marked: the teacher still reads them. */

function answer_keys():array{static $k;if($k===null){$f=__DIR__.'/../database/answer-keys.json';$k=is_file($f)?(json_decode((string)file_get_contents($f),true)??[]):[];}return $k;}

/** The key of one activity ("level:grade:lesson position:phase:position"), or null when it has none. */
function activity_key_id(array $a):?string{
 static $c=[];$id=(int)$a['id'];if(array_key_exists($id,$c))return $c[$id];
 $r=one('SELECT a.phase,a.position,l.position lpos,m.grade_level g,m.level_id lv FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE a.id=?',[$id]);
 return $c[$id]=$r&&in_array((int)$r['lv'],[6,7,8],true)?$r['lv'].':'.$r['g'].':'.$r['lpos'].':'.$r['phase'].':'.$r['position']:null;
}
function activity_key(array $a):?array{$id=activity_key_id($a);return $id?(answer_keys()['keys'][$id]??null):null;}

/** The pupil's answer for each card, from "BULIG card answers / Card n (no. t): ...". */
function card_answers(string $response):array{
 $r=str_replace(["\r\n","\r"],"\n",$response);$out=[];if(!str_starts_with($r,"BULIG card answers\n"))return $out;
 foreach(preg_split('/\n(?=Card [0-9]+(?: \(no\. [0-9]+\))?:)/',substr($r,19)) as $line)if(preg_match('/^Card ([0-9]+)(?: \(no\. [0-9]+\))?:\s?(.*)$/s',$line,$m))$out[(int)$m[1]]=trim($m[2]);
 return $out;
}
function key_norm(string $s):string{return mb_strtolower(trim(preg_replace('~\s+~u',' ',str_replace(['’','‘','“','”'],["'","'",'"','"'],$s))));}

/** Score one answer against the key: ['right'=>, 'total'=>, 'cards'=>[n=>['got','want','pts','of']]], or null when there is no key. */
function key_score(array $a,string $response):?array{
 $key=activity_key($a);if(!$key)return null;$got=card_answers($response);$right=0;$total=0;$cards=[];
 foreach($key as $n=>$k){$n=(int)$n;$g=(string)($got[$n]??'');$pts=0;$of=1;$want='';
  if($k['t']==='choice'){$ok=array_merge([$k['a']],$k['alt']??[]);$want=implode(' or ',$ok);$pts=$g!==''&&in_array(key_norm($g),array_map('key_norm',$ok),true)?1:0;}
  elseif($k['t']==='match'){$of=count($k['a']);$have=[];if(preg_match_all('~(\d+)\s*-\s*([a-z])~i',$g,$mm,PREG_SET_ORDER))foreach($mm as $m)$have[$m[1]]=strtolower($m[2]);
   foreach($k['a'] as $i=>$l)if(($have[(string)$i]??'')===$l)$pts++;$want=implode(', ',array_map(fn($i,$l)=>$i.'-'.$l,array_keys($k['a']),$k['a']));}
  elseif($k['t']==='order'){$of=count($k['a']);$have=[];foreach(explode(' · ',$g) as $part)if(($p=strrpos($part,' = '))!==false)$have[key_norm(substr($part,0,$p))]=trim(substr($part,$p+3));
   foreach($k['a'] as $lab=>$num)if(($have[key_norm((string)$lab)]??'')===(string)$num)$pts++;$want=implode(' · ',array_map(fn($l,$v)=>$v,array_keys($k['a']),$k['a']));}
  $right+=$pts;$total+=$of;$cards[$n]=['got'=>$g,'want'=>$want,'pts'=>$pts,'of'=>$of,'t'=>$k['t'],'fix'=>!empty($k['fix'])];}
 return ['right'=>$right,'total'=>$total,'cards'=>$cards];
}

/** Save the auto score on a completion (score / max_score), unless the teacher has already set one. */
function key_save_score(int $cid):void{
 $c=one('SELECT c.id,c.response,c.activity_id,c.reviewed_at,c.rubric_scores FROM activity_completion c WHERE c.id=?',[$cid]);if(!$c)return;
 $s=key_score(['id'=>(int)$c['activity_id']],(string)$c['response']);if(!$s||!$s['total'])return;
 $j=json_decode((string)($c['rubric_scores']??''),true);if(is_array($j)&&isset($j['teacher_score']))return;
 q('UPDATE activity_completion SET score=?,max_score=? WHERE id=?',[$s['right'],$s['total'],$cid]);
}

/** Teacher review: each marked card with the pupil's answer, a check or a cross, and the right answer. */
function key_review(array $r):string{
 $s=key_score(['id'=>(int)$r['activity_id']],(string)$r['response']);if(!$s)return '';$set=l2a_set(one('SELECT * FROM activities WHERE id=?',[(int)$r['activity_id']]));
 $h='<div class="ks"><p class="ks-sum">'.l1_icon('ok').'<b>Auto score: '.$s['right'].' / '.$s['total'].'</b><span>Tapped answers are marked with the answer key. Read any written or drawn answers yourself.</span></p><ol class="ks-list">';
 foreach($s['cards'] as $n=>$c){$q=$set['cards'][$n-1]??[];$text=trim(preg_replace('~\s+~u',' ',(string)($q['text']??'')));if($text===''&&$c['t']==='match')$text='Matching';if($text===''&&$c['t']==='order')$text='Number the events';
  $ok=$c['pts']===$c['of'];$h.='<li class="'.($ok?'ok':($c['pts']?'part':'no')).'"><span class="ks-q">Card '.$n.' · '.e(mb_strimwidth($text,0,110,'…')).'</span><span class="ks-a">'.($c['got']!==''?e($c['got']):'<i>No answer</i>').'</span><span class="ks-r">'.($ok?l1_icon('ok').'Right':($c['of']>1?$c['pts'].' / '.$c['of']:'Wrong')).(!$ok?' · Answer: '.e($c['want']):'').'</span></li>';}
 return $h.'</ol></div>';
}
function key_score_text(array $r):string{if($r['max_score']===null||(float)$r['max_score']<=0||!activity_key_id(['id'=>(int)$r['activity_id']]))return '';return rtrim(rtrim(number_format((float)$r['score'],2),'0'),'.').' / '.rtrim(rtrim(number_format((float)$r['max_score'],2),'0'),'.');}
