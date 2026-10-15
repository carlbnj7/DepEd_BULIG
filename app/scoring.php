<?php
/* Auto-checking for every level.
   - database/answer-keys.json (Levels 5-7, the module answer keys checked against the stories) is built from
     database/keys/*.txt by tools/keys/build.php. Where the module's own key is wrong, the right answer is used and the
     card is marked "fix".
   - database/answer-keys-2.json (Levels 2A, 2B and 3, and the Level 5-7 cards the module key left out) is built from
     database/keys2/*.txt by tools/keys/build2.php. The modules have no key for these, so BULIG judged each answer from
     the pictures, the words and the stories.
   - A card with a key is right or wrong (a matching board scores 1 point per pair, numbering events 1 point per event).
   - A card without a single right answer (speaking, drawing, writing your own ideas, reading aloud) counts as right
     when the pupil answers it. Levels 1 and 4 are spoken: an answered activity counts as right.
   - The teacher can always change the score (and Level 4 readings are scored by the teacher). */

function answer_keys():array{static $k;if($k===null){$k=['keys'=>[],'notes'=>[]];
 foreach(['answer-keys.json','answer-keys-2.json'] as $f){$f=__DIR__.'/../database/'.$f;$j=is_file($f)?(json_decode((string)file_get_contents($f),true)??[]):[];
  foreach($j['keys']??[] as $id=>$cards)foreach($cards as $n=>$c)$k['keys'][$id][$n]=$c;foreach($j['notes']??[] as $id=>$x)$k['notes'][$id]=array_merge($k['notes'][$id]??[],$x);}}return $k;}

/** The key of one activity ("level:grade:lesson position:phase:position", grade 0 for levels shared by every grade). */
function activity_key_id(array $a):?string{
 static $c=[];$id=(int)$a['id'];if(array_key_exists($id,$c))return $c[$id];
 $r=one('SELECT a.phase,a.position,l.position lpos,IFNULL(m.grade_level,0) g,m.level_id lv FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE a.id=?',[$id]);
 return $c[$id]=$r?$r['lv'].':'.$r['g'].':'.$r['lpos'].':'.$r['phase'].':'.$r['position']:null;
}
function activity_key(array $a):?array{$id=activity_key_id($a);return $id?(answer_keys()['keys'][$id]??null):null;}

/** The pupil's answer for each card, from "BULIG card answers / Card n (no. t): ...". */
function card_answers(string $response):array{
 $r=str_replace(["\r\n","\r"],"\n",$response);$out=[];if(!str_starts_with($r,"BULIG card answers\n"))return $out;
 foreach(preg_split('/\n(?=Card [0-9]+(?: \(no\. [0-9]+\))?:)/',substr($r,19)) as $line)if(preg_match('/^Card ([0-9]+)(?: \(no\. [0-9]+\))?:\s?(.*)$/s',$line,$m))$out[(int)$m[1]]=trim($m[2]);
 return $out;
}
function key_norm(string $s):string{return mb_strtolower(trim(preg_replace('~\s+~u',' ',str_replace(['’','‘','“','”'],["'","'",'"','"'],$s))));}
/** Letters and numbers only: "/m/ /a/ /t/" and "m a t" both give "mat". */
function key_letters(string $s):string{return preg_replace('~[^\p{L}\p{N}\']+~u','',mb_strtolower(str_replace(['’','‘'],"'",$s)));}

/** One part of a judged card (answer-keys-2.json) against one piece of the pupil's answer. */
function key_part_ok(array $k,string $g):bool{
 switch($k['k']){
  case 'any':return true;
  case 'eq':return $g!==''&&(key_norm($g)===$k['v']||key_letters($g)===$k['v']);
  case 'has':$t=key_letters($g);$n=0;foreach($k['v'] as $w)if($w!==''&&str_contains($t,$w))$n++;return $t!==''&&$n>=$k['n'];
  case 'build':return $g!==''&&key_letters(preg_replace('~\s*\(tries:.*$~u','',$g))===$k['v']&&!str_starts_with($g,'tries:');
  case 'onset':return str_replace(' ','',mb_strtolower($g))===$k['v'];
  case 'syl':$p=array_filter(array_map('trim',explode('/',mb_strtolower($g))),'strlen');if(count($p)!==$k['n']||substr_count($g,'/')!==$k['n']-1)return false;foreach($p as $x)if(!preg_match('~[aeiouy]~u',$x))return false;return true;
  case 'pairs':if(!preg_match_all('~(\d+)\s*[–-]\s*(\d+)~u',$g,$mm,PREG_SET_ORDER))return false;$have=[];foreach($mm as $m){$x=[(int)$m[1],(int)$m[2]];sort($x);$have[]=implode('-',$x);}$want=array_map(fn($x)=>implode('-',$x),$k['v']);sort($have);sort($want);return $have===$want;
  case 'set':
   if(!empty($k['pics'])){preg_match_all('~Picture (\d+)~',$g,$mm);$have=array_map('intval',$mm[1]);}else $have=array_map('key_norm',array_filter(array_map('trim',explode(',',$g)),'strlen'));
   if(!$have)return false;$allowed=array_merge($k['req'],$k['opt']);foreach($have as $x)if(!in_array($x,$allowed,true))return false;
   if(!empty($k['min']))return count(array_unique($have))>=$k['min'];foreach($k['req'] as $x)if(!in_array($x,$have,true))return false;return true;
 }
 return false;
}
/** A judged card: right when one of its answers fits every part (a part matches any piece of a multi-part answer). */
function key_card_ok(array $k,string $g):bool{
 $pieces=$g===''?[]:explode(' · ',$g);
 foreach($k['alts'] as $alt){$all=true;
  if(count($alt)===1){if(!key_part_ok($alt[0],$g))$all=false;}
  else foreach($alt as $part){if($part['k']==='any')continue;$hit=false;foreach($pieces as $pc)if(key_part_ok($part,trim($pc))){$hit=true;break;}if(!$hit){$all=false;break;}}
  if($all)return true;}
 return false;
}
/** The right answer to show the teacher. */
function key_want(array $k):string{
 if($k['t']==='card')return (string)$k['w'];
 if($k['t']==='choice')return implode(' or ',array_merge([$k['a']],$k['alt']??[]));
 if($k['t']==='match')return implode(', ',array_map(fn($i,$l)=>$i.'-'.$l,array_keys($k['a']),$k['a']));
 return implode(' · ',array_map(fn($l,$v)=>$l.' = '.$v,array_keys($k['a']),$k['a']));
}
/** True when the pupil gave an answer to a card (spoken, drawn, written or tapped). */
function key_answered(string $g):bool{return $g!==''&&$g!=='(seen)';}

/** Score one answer: ['right'=>, 'total'=>, 'cards'=>[n=>['got','want','pts','of','t','fix']]], or null for an activity
    with nothing to answer. Cards with a key are marked; every other card the pupil answers counts as right (t "open"). */
function key_score(array $a,string $response):?array{
 if(!isset($a['lesson_id']))$a=one('SELECT * FROM activities WHERE id=?',[(int)$a['id']])??$a;
 $lv=isset($a['lesson_id'])?lesson_level((int)$a['lesson_id']):0;$key=activity_key($a)??[];
 $set=in_array($lv,[2,3,4,6,7,8],true)?l2a_set($a):null;
 if(!$set){if(!$key)return ($a['response_mode']??'answer')==='none'?null:['right'=>key_answered(trim($response))?1:0,'total'=>1,'cards'=>[]];$set=['cards'=>[]];}
 $got=card_answers($response);$right=0;$total=0;$cards=[];
 $ask=[];foreach($set['cards'] as $i=>$cd)if(array_diff($cd['ui']['answer']??[],['look']))$ask[]=$i+1;
 $nums=array_values(array_unique(array_merge(array_map('intval',array_keys($key)),$ask)));sort($nums);
 foreach($nums as $n){$k=$key[$n]??null;$g=(string)($got[$n]??'');$pts=0;$of=1;$want='';$t=$k['t']??'open';
  if(!$k){$pts=key_answered($g)?1:0;$want='Any answer';}
  elseif($t==='card'){$pts=key_card_ok($k,$g)?1:0;$want=key_want($k);}
  elseif($t==='choice'){$ok=array_merge([$k['a']],$k['alt']??[]);$want=key_want($k);$pts=$g!==''&&in_array(key_norm($g),array_map('key_norm',$ok),true)?1:0;}
  elseif($t==='match'){$of=count($k['a']);$have=[];if(preg_match_all('~(\d+)\s*-\s*([a-z])~i',$g,$mm,PREG_SET_ORDER))foreach($mm as $m)$have[$m[1]]=strtolower($m[2]);
   foreach($k['a'] as $i=>$l)if(($have[(string)$i]??'')===$l)$pts++;$want=key_want($k);}
  elseif($t==='order'){$of=count($k['a']);$have=[];foreach(explode(' · ',$g) as $part)if(($p=strrpos($part,' = '))!==false)$have[key_norm(substr($part,0,$p))]=trim(substr($part,$p+3));
   foreach($k['a'] as $lab=>$num)if(($have[key_norm((string)$lab)]??'')===(string)$num)$pts++;$want=implode(' · ',array_map(fn($l,$v)=>$v,array_keys($k['a']),$k['a']));}
  $right+=$pts;$total+=$of;$cards[$n]=['got'=>$g,'want'=>$want,'pts'=>$pts,'of'=>$of,'t'=>$t,'fix'=>!empty($k['fix'])];}
 return $total?['right'=>$right,'total'=>$total,'cards'=>$cards]:null;
}

/** Save the auto score on a completion (score / max_score), unless the teacher has already set one. */
function key_save_score(int $cid):void{
 $c=one('SELECT c.id,c.response,c.activity_id,c.reviewed_at,c.rubric_scores FROM activity_completion c WHERE c.id=?',[$cid]);if(!$c)return;
 $j=json_decode((string)($c['rubric_scores']??''),true);if(is_array($j)&&(isset($j['teacher_score'])||isset($j['l4'])))return;
 $s=key_score(['id'=>(int)$c['activity_id']],(string)$c['response']);if(!$s||!$s['total'])return;
 q('UPDATE activity_completion SET score=?,max_score=? WHERE id=?',[$s['right'],$s['total'],$cid]);
}

/** Teacher review: each marked card with the pupil's answer, a check or a cross, and the right answer. */
function key_review(array $r):string{
 $a=one('SELECT * FROM activities WHERE id=?',[(int)$r['activity_id']]);if(!$a)return '';$s=key_score($a,(string)$r['response']);if(!$s||!$s['cards'])return '';$set=l2a_set($a);$bc=$set?l2a_blank_counts($a,$set,lesson_level((int)$a['lesson_id'])):[];
 $open=count(array_filter($s['cards'],fn($c)=>$c['t']==='open'));
 $h='<div class="ks"><p class="ks-sum">'.l1_icon('ok').'<b>Auto score: '.$s['right'].' / '.$s['total'].'</b><span>'.($open?'Answers with one right answer are marked with the answer key. Spoken, drawn and written answers count when the pupil answered; change the score if you need to.':'Every answer is marked with the answer key.').'</span></p><ol class="ks-list">';
 foreach($s['cards'] as $n=>$c){$q=$set['cards'][$n-1]??[];$text=blank_plain(trim(preg_replace('~\s+~u',' ',(string)($q['text']??''))),$bc[$n-1]??0);if($text===''&&$c['t']==='match')$text='Matching';if($text===''&&$c['t']==='order')$text='Number the events';if($text===''&&($ch=array_values($q['ui']['opts'][0]??$q['choices']??[])))$text=implode(' / ',array_map('strval',$ch));if($text==='')$text=($q['images']??[])?'Picture card':'Card';
  $ok=$c['pts']===$c['of'];$h.='<li class="'.($ok?'ok':($c['pts']?'part':'no')).'"><span class="ks-q">Card '.$n.' · '.e(mb_strimwidth($text,0,110,'…')).'</span><span class="ks-a">'.($c['got']!==''?e(mb_strimwidth($c['got'],0,160,'…')):'<i>No answer</i>').'</span><span class="ks-r">'.($ok?l1_icon('ok').($c['t']==='open'?'Answered':'Right'):($c['of']>1?$c['pts'].' / '.$c['of']:($c['t']==='open'?'No answer':'Wrong'))).(!$ok&&$c['t']!=='open'?' · Answer: '.e($c['want']):'').'</span></li>';}
 return $h.'</ol></div>';
}
function key_score_text(array $r):string{if($r['max_score']===null||(float)$r['max_score']<=0)return '';return rtrim(rtrim(number_format((float)$r['score'],2),'0'),'.').' / '.rtrim(rtrim(number_format((float)$r['max_score'],2),'0'),'.');}

/** True when every card a pupil answers is marked by the answer key: nothing written, drawn or recorded for the teacher to read. */
function key_fully_auto(int $aid):bool{
 static $c=[];if(isset($c[$aid]))return $c[$aid];$a=one('SELECT * FROM activities WHERE id=?',[$aid]);$key=$a?activity_key($a):null;$set=$key?l2a_set($a):null;if(!$set)return $c[$aid]=false;
 foreach($set['cards'] as $i=>$card){if(!array_diff($card['ui']['answer']??[],['look']))continue;if(!isset($key[$i+1]))return $c[$aid]=false;}
 return $c[$aid]=true;
}
/** Answers waiting for review that the answer key marked fully right, with nothing else to read. */
function key_perfect_ids(int $tid,int $pupil=0):array{
 $out=[];foreach(rows("SELECT c.id,c.activity_id FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id JOIN activities a ON a.id=c.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE t.teacher_id=? AND m.level_id IN (2,3,4,6,7,8) AND c.status IN ('completed','approved') AND c.reviewed_at IS NULL AND c.max_score>0 AND c.score>=c.max_score AND c.drawing IS NULL".($pupil?' AND c.pupil_id=?':''),$pupil?[$tid,$pupil]:[$tid]) as $r)if(key_fully_auto((int)$r['activity_id']))$out[]=(int)$r['id'];
 return $out;
}

/* ---------- Automatic checking (v151) ----------
   BULIG checks every answer the moment the pupil sends it, so nothing waits for the teacher. Cards with an answer key
   are marked right or wrong; spoken, drawn and written answers count as done. Right after "Done" (and whenever the
   activity is opened again) the pupil sees the score, stars and the right answer for anything missed, and can try the
   activity again for practice: the best score is kept and no extra XP is given. Teachers can still look at every
   answer, change a score or write a note on the pupil's page (Answers and scores). */
/** True when the activity is a card activity with an answer key, so BULIG can show a score. */
function ac_keyed(array $a):bool{static $c=[];$id=(int)$a['id'];if(isset($c[$id]))return $c[$id];
 if(!isset($a['lesson_id'],$a['title'],$a['prompt']))$a=one('SELECT * FROM activities WHERE id=?',[$id])??$a;
 return $c[$id]=isset($a['lesson_id'])&&in_array(lesson_level((int)$a['lesson_id']),[2,3,4,6,7,8],true)&&activity_key($a)&&l2a_set($a);}
/** What the pupil missed, in words a child can read: [[number, what, the right answer, 'goes with'|'is'], ...]. */
function ac_misses(array $a,array $s):array{
 $set=l2a_set($a);$key=activity_key($a)??[];if(!$set)return [];$bc=l2a_blank_counts($a,$set,lesson_level((int)$a['lesson_id']));$out=[];
 foreach($s['cards'] as $n=>$c){if($c['t']==='open'||$c['pts']>=$c['of'])continue;$q=$set['cards'][$n-1]??[];$k=$key[$n]??null;if(!$k)continue;
  if($c['t']==='match'){$m=$q['match']??$q;$left=[];foreach(preg_split('/\n/',(string)($m['text']??'')) as $l)if(preg_match('/^\s*(\d+)\s*[.)]\s*(.*)$/',trim($l),$mm))$left[$mm[1]]=trim($mm[2]);
   $right=[];foreach(($m['choices']??[]) as $ch)if(preg_match('/^\s*([a-zA-Z])\s*[.)]\s*(.*)$/s',(string)$ch,$mm))$right[strtolower($mm[1])]=strtolower($mm[1]).'. '.trim($mm[2]);
   $have=[];if(preg_match_all('~(\d+)\s*-\s*([a-z])~i',$c['got'],$g,PREG_SET_ORDER))foreach($g as $x)$have[$x[1]]=strtolower($x[2]);
   foreach($k['a'] as $i=>$l)if(($have[(string)$i]??'')!==$l)$out[]=[(string)$i,($left[(string)$i]??'')!==''?$left[(string)$i]:'Number '.$i,$right[$l]??$l,'goes with'];}
  elseif($c['t']==='order')$out[]=[(string)$n,'The right order',implode(', ',array_map(fn($lab,$v)=>$lab.' = '.$v,array_keys($k['a']),$k['a'])),'is'];
  else{$t=blank_plain(trim(preg_replace('~\s+~u',' ',(string)($q['text']??''))),$bc[$n-1]??0);$out[]=[(string)$n,$t!==''?mb_strimwidth($t,0,90,'…'):'Card '.$n,$c['want'],'is']; }}
 return $out;
}
/** The result card. $c: the saved answer (response, score, max_score, rubric_scores, submitted_at).
    $o: fresh (just sent), xp, practice (a "try again" answer), best (the score that is kept). */
function ac_card(array $a,array $c,array $o=[]):string{
 if(!ac_keyed($a))return '';$s=key_score($a,(string)($c['response']??''));if(!$s||!$s['total'])return '';
 $right=$s['right'];$total=$s['total'];$j=json_decode((string)($c['rubric_scores']??''),true);$teacher=is_array($j)&&isset($j['teacher_score'])&&empty($o['fresh']);
 if($teacher&&(float)($c['max_score']??0)>0){$right=(float)$c['score'];$total=(float)$c['max_score'];}
 $num=fn($x)=>rtrim(rtrim(number_format((float)$x,1),'0'),'.');$pct=$total?$right/$total:0;$stars=(int)round(5*$pct);if($right>0&&$stars<1)$stars=1;
 $title=!empty($o['practice'])?'Practice checked!':($pct>=1?'Perfect! Every answer is right.':($pct>=.8?'Great work!':($pct>=.5?'Good try!':'Let’s look again together.')));
 $sub=!empty($o['practice'])?(string)($o['best']??''):(!empty($o['fresh'])?'BULIG checked your answers.':($teacher?'Your teacher checked this.':'BULIG checked this'.(!empty($c['submitted_at'])?' on '.date('F j',strtotime((string)$c['submitted_at'])):'').'.'));
 $h='<div class="ac-panel'.($pct>=1?' ac-perfect':'').'" role="status"><div class="ac-top"><span class="ac-badge" aria-hidden="true">'.l1_icon('ok').'</span><div><b>'.e($title).'</b><small>'.e($sub).'</small></div></div>';
 $h.='<div class="ac-score"><strong>'.$num($right).' <span>of '.$num($total).' right</span></strong><span class="ac-stars" role="img" aria-label="'.$stars.' of 5 stars">'.str_repeat('<i></i>',$stars).str_repeat('<i class="off"></i>',5-$stars).'</span></div>';
 if(!empty($o['xp']))$h.='<span class="ac-xp">+'.(int)$o['xp'].' XP</span>';
 if(!$teacher&&$pct<1&&($miss=ac_misses($a,$s))){$h.='<p class="ac-lab">LOOK AGAIN</p><ul class="ac-again">';
  foreach(array_slice($miss,0,6) as [$n,$what,$want,$how])$h.='<li><em>'.e($n).'</em><span>'.($how==='goes with'?e($what).' goes with <b>'.e($want).'</b>':e($what).'<br>The answer: <b>'.e($want).'</b>').'</span></li>';
  if(count($miss)>6)$h.='<li class="ac-more"><span>and '.(count($miss)-6).' more</span></li>';$h.='</ul>';}
 if($pct<1&&!$teacher)$h.='<div class="ac-btns"><a class="ac-btn" href="?page=lesson&amp;id='.(int)$a['lesson_id'].'&amp;activity='.(int)$a['id'].'&amp;again=1">'.l1_icon('again').'Try it again</a></div>';
 return $h.'</div>';
}
/** "Try it again": mark the new answer, keep the best one. No XP and no change to the pupil's progress. */
function ac_practice(int $pid,array $a,array $old,string $answer):array{
 $new=key_score($a,$answer);$j=json_decode((string)($old['rubric_scores']??''),true);$locked=is_array($j)&&isset($j['teacher_score']);
 $prev=$old['score']!==null&&$old['max_score']!==null?['right'=>(float)$old['score'],'total'=>(float)$old['max_score']]:key_score($a,(string)$old['response']);/* answers saved before scores were kept are marked now */
 $was=(float)($prev['right']??0);$better=!$locked&&$new&&$new['right']>$was;
 if($better){q('UPDATE activity_completion SET response=?,score=?,max_score=? WHERE id=?',[$answer,$new['right'],$new['total'],(int)$old['id']]);}
 q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[(int)$old['id'],$answer,'practice',$pid]);
 $num=fn($x)=>rtrim(rtrim(number_format((float)$x,1),'0'),'.');$best=$better?$new['right']:$was;$of=$better?$new['total']:(float)($prev['total']??($new['total']??0));
 $msg=$better?'Better! Your new best score is '.$num($best).' of '.$num($of).'.':'Your best score stays '.$num($best).' of '.$num($of).'.';
 return ['message'=>$msg,'card'=>ac_card($a,['response'=>$answer],['fresh'=>true,'practice'=>true,'best'=>$msg])];
}
