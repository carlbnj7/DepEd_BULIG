<?php
/* Level 4 (Fluency, level id 5; one module per grade): the same clean screens as Levels 1 to 3.
   - Each passage is shown as in the module (picture, title, author, the words, "Number of words in the passage").
   - The pupil taps the microphone, reads the whole passage aloud, and taps again to stop. The reading time is kept.
     "Done" is for reading at home or without a microphone.
   - BULIG scores the reading itself with the module's own table: while the pupil reads, the browser's speech recognition
     writes down what it hears; l4_miscues() lines that up with the passage and counts the miscues by type, then the
     Oral Reading Score, Reading Level (Ind 98-100, Ins 90-96, Frus 89 below) and Reading Speed (words per minute).
     The pupil sees the result at once; the teacher sees the same table filled in, listens, and can change any number.
     Without speech recognition (some phones, no internet) the teacher scores from the recording, as before. */

const L4_MISCUES=['Mispronunciation','Omission','Substitution','Insertion','Repetition','Transposition','Reversal'];

function l4_is(int $lid):bool{return lesson_level($lid)===5;}
function l4_tag(array $a,array $lesson):string{
 if($a['phase']==='pre')return 'PRE-TEST PASSAGE';if($a['phase']==='post')return 'POST-TEST PASSAGE';
 return 'PASSAGE '.max(1,(int)$lesson['position']-1);
}
/** Reading time saved with the answer: "time 1:32". */
function l4_secs(string $response):int{return preg_match('~time (\d+):(\d\d)~',$response,$m)?(int)$m[1]*60+(int)$m[2]:0;}

/* ---------------- BULIG listens: miscues from what the speech recognizer heard ---------------- */
const L4_KINDS=['mis','om','sub','ins','rep','tr','rev'];
/** The passage words in reading order, the title too when the module's word count includes it (as Phil-IRI does). */
function l4_words(array $a,int &$titleN=0):array{
 $p=level4_passage_parts($a);$titleN=0;if(!$p)return [];$w=preg_split('/\s+/u',trim(implode(' ',$p['blocks'])),-1,PREG_SPLIT_NO_EMPTY);
 $t=preg_split('/\s+/u',trim((string)$p['title']),-1,PREG_SPLIT_NO_EMPTY);if($t&&count($w)+count($t)===(int)$p['words']){$w=array_merge($t,$w);$titleN=count(array_filter($t,fn($x)=>l4_norm($x)!==''));}
 return array_values(array_filter($w,fn($x)=>l4_norm($x)!==''));
}
function l4_norm(string $w):string{return preg_replace('~[^a-z0-9\']+~','',mb_strtolower(str_replace(['’','‘'],"'",$w)));}
/** A near miss (said almost right): same sound key, one or two letters off, or the same start of a long word. */
function l4_similar(string $a,string $b):bool{
 if($a===''||$b==='')return false;if(metaphone($a)===metaphone($b))return true;
 $n=max(strlen($a),strlen($b));return levenshtein($a,$b)<=max(1,intdiv($n,3))||(strlen($a)>=4&&strlen($b)>=4&&substr($a,0,3)===substr($b,0,3));
}
/** Phil-IRI miscues: the passage and the heard words are lined up (fewest changes), then each change is named:
    a near miss = Mispronunciation, another word = Substitution, a missed word = Omission, an added word = Insertion,
    words said again = Repetition (one per repeated run), two words swapped = Transposition, a word said backwards
    (was / saw) = Reversal. Returns the counts in L4_MISCUES order, a mark for each missed or changed passage word,
    and the added or repeated words with the passage word they came before. */
function l4_miscues(array $words,string $heard):array{
 $P=array_values(array_map('l4_norm',$words));$H=array_values(array_filter(array_map('l4_norm',preg_split('/\s+/u',$heard)),'strlen'));
 $n=count($P);$m=count($H);$INF=1e9;$D=array_fill(0,$n+1,array_fill(0,$m+1,$INF));$B=$D;$D[0][0]=0;
 for($i=0;$i<=$n;$i++)for($j=0;$j<=$m;$j++){$c=$D[$i][$j];if($c>=$INF)continue;
  if($i<$n&&$j<$m){$x=$P[$i]===$H[$j]?0:(l4_similar($P[$i],$H[$j])||strrev($P[$i])===$H[$j]?0.6:1);if($c+$x<$D[$i+1][$j+1]){$D[$i+1][$j+1]=$c+$x;$B[$i+1][$j+1]='s';}}
  if($i<$n&&$c+1<$D[$i+1][$j]){$D[$i+1][$j]=$c+1;$B[$i+1][$j]='d';}
  if($j<$m){$rep=($j>0&&$H[$j]===$H[$j-1])||($i>0&&in_array($H[$j],array_slice($P,max(0,$i-3),min(3,$i)),true));$x=$rep?0.9:1;if($c+$x<$D[$i][$j+1]){$D[$i][$j+1]=$c+$x;$B[$i][$j+1]=$rep?'r':'i';}}
  if($i+1<$n&&$j+1<$m&&$P[$i]!==$P[$i+1]&&$P[$i]===$H[$j+1]&&$P[$i+1]===$H[$j]&&$c+1<$D[$i+2][$j+2]){$D[$i+2][$j+2]=$c+1;$B[$i+2][$j+2]='t';}
 }
 $ops=[];for($i=$n,$j=$m;$i>0||$j>0;){$b=$B[$i][$j];
  if($b==='s'){$ops[]=['s',$i-1,$j-1];$i--;$j--;}elseif($b==='d'){$ops[]=['d',$i-1,0];$i--;}elseif($b==='t'){$ops[]=['t',$i-2,$j-2];$i-=2;$j-=2;}else{$ops[]=[$b,$i,$j-1];$j--;}}
 $cnt=array_fill(0,7,0);$marks=[];$extra=[];$lastRep=-2;
 foreach(array_reverse($ops) as [$o,$i,$j]){
  if($o==='s'){if($P[$i]===$H[$j])continue;
   if(strlen($P[$i])>1&&strrev($P[$i])===$H[$j]){$cnt[6]++;$marks[$i]=['k'=>'rev','h'=>$H[$j]];}
   elseif(l4_similar($P[$i],$H[$j])){$cnt[0]++;$marks[$i]=['k'=>'mis','h'=>$H[$j]];}else{$cnt[2]++;$marks[$i]=['k'=>'sub','h'=>$H[$j]];}}
  elseif($o==='d'){$cnt[1]++;$marks[$i]=['k'=>'om'];}
  elseif($o==='t'){$cnt[5]++;$marks[$i]=['k'=>'tr'];$marks[$i+1]=['k'=>'tr'];}
  elseif($o==='r'){if($j!==$lastRep+1)$cnt[4]++;$lastRep=$j;$extra[]=['k'=>'rep','at'=>$i,'h'=>$H[$j]];}
  else{$cnt[3]++;$extra[]=['k'=>'ins','at'=>$i,'h'=>$H[$j]];}
 }
 return ['m'=>$cnt,'marks'=>$marks,'extra'=>$extra,'heard'=>$m];
}
/** BULIG's score for one reading, in the shape the teacher's table saves ('auto' => 1). Null when the recognizer heard
    too little to judge (under a third of the passage): then the teacher scores it from the recording. */
function l4_auto(array $a,string $said,int $secs):?array{
 $w=l4_words($a);$p=level4_passage_parts($a);if(!$w||!$p)return null;$words=(int)$p['words']?:count($w);
 $said=trim(mb_substr($said,0,8000));$heard=count(array_filter(array_map('l4_norm',preg_split('/\s+/u',$said)),'strlen'));
 if($heard<max(3,count($w)/3))return null;
 $r=l4_miscues($w,$said);$tot=min($words,array_sum($r['m']));$score=round(($words-$tot)/$words*100,1);
 return ['m'=>$r['m'],'total'=>$tot,'words'=>$words,'score'=>$score,'level'=>l4_level($score),'secs'=>$secs,'wpm'=>$secs?(int)round(($words-$tot)/$secs*60):0,
  'auto'=>1,'marks'=>$r['marks'],'extra'=>$r['extra'],'said'=>$said];
}
/** The passage with each miscue marked (and, for the teacher, what was said instead). */
function l4_marked(array $a,array $s,bool $teacher):string{
 $nt=0;$w=l4_words($a,$nt);if(!$w)return '';$marks=$s['marks']??[];$extra=[];foreach($s['extra']??[] as $x)$extra[(int)$x['at']][]=$x;$hasTitle=$nt>0;
 $h='<div class="l4a-text">';
 foreach($w as $i=>$x){
  foreach($extra[$i]??[] as $e)$h.='<ins class="k-'.$e['k'].'">'.($e['k']==='rep'?'↺ ':'+ ').e($e['h']).'</ins> ';
  $mk=$marks[$i]??$marks[(string)$i]??null;
  $word=$mk?'<em class="k-'.e($mk['k']).'"'.(!empty($mk['h'])?' data-h="'.e(($teacher?'said: ':'').$mk['h']).'"':'').'>'.e($x).'</em>':e($x);
  if($hasTitle&&$i===0)$h.='<span class="t">';$h.=$word;if($hasTitle&&$i===$nt-1)$h.='</span>';else $h.=' ';
 }
 foreach($extra[count($w)]??[] as $e)$h.=' <ins class="k-'.$e['k'].'">'.($e['k']==='rep'?'↺ ':'+ ').e($e['h']).'</ins>';
 return $h.'</div><div class="l4a-key">'.implode('',array_map(fn($k,$n)=>'<span class="k-'.$k.'"><i></i>'.e($n).'</span>',L4_KINDS,L4_MISCUES)).'</div>';
}
function l4_level_class(string $lv):string{return $lv==='Independent'?'lv-ind':($lv==='Instructional'?'lv-ins':'lv-frus');}
/** The pupil's result card: score ring, reading level, reading speed, the miscues and the marked passage. */
function l4_result_card(array $a,array $s):string{
 $p=level4_passage_parts($a);$lv=(string)$s['level'];$cls=l4_level_class($lv);$pct=max(0,min(100,(float)$s['score']));
 $say=['lv-ind'=>'You read it on your own. Great job!','lv-ins'=>'Good reading! A little practice makes it great.','lv-frus'=>'Let’s practise this one more time.'][$cls];
 $h='<section class="l4a"><div class="l4a-head"><img src="'.e(char_src('george','cheer')).'" alt="" width="64" height="64"><div><span class="l4a-eye">BULIG LISTENED TO YOUR READING</span><h3>Here is how you read “'.e((string)($p['title']??'')).'”</h3></div></div>';
 $h.='<div class="l4a-stats"><div class="l4a-ring '.$cls.'"><svg viewBox="0 0 36 36" aria-hidden="true"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fg" cx="18" cy="18" r="15.9155" stroke-dasharray="'.$pct.' 100"/></svg><b>'.round($pct).'%</b><small>Oral reading score</small></div>';
 $h.='<div class="l4a-stat"><small>Reading level</small><b class="lv '.$cls.'">'.e($lv).'</b><span>'.e($say).'</span></div>';
 $h.='<div class="l4a-stat"><small>Reading speed</small><b>'.($s['wpm']?(int)$s['wpm']:'—').'</b><span>words per minute</span></div></div>';
 $h.='<div class="l4a-mis">';foreach(L4_MISCUES as $i=>$n){$c=(int)($s['m'][$i]??0);$h.='<span class="k-'.L4_KINDS[$i].($c?' on':'').'"><b>'.$c.'</b>'.e($n).'</span>';}$h.='</div>';
 return $h.l4_marked($a,$s,false).'<p class="l4a-note">'.l1_icon('teacher').'BULIG checked your reading. Your teacher can listen too.</p></section>';
}

function l4_activity_page(array $u,array $lesson,array $all,array $a):void{
 $lid=(int)$lesson['id'];$lv=lesson_level($lid);$readonly=completion_ok($a);$reading=$a['type']==='reading'&&level4_passage_parts($a);
 $c=$a['completion_id']?one('SELECT id,audio_path,response,rubric_scores FROM activity_completion WHERE id=?',[(int)$a['completion_id']]):null;
 $doneAll=count(array_filter($all,fn($x)=>completion_ok($x)));
 head(level_label($lv).' · '.$lesson['subtitle'],'activity-page l1-page l4-page');
 echo '<div class="l1-app l4-app">'.lesson_head($lesson,$all,$a,'?page=lessons&level='.$lv.'#level'.$lv.'-path').'<main class="l1-main activity-main">';notice();
 echo '<form method="post" id="activity-form" class="activity-form l1-form l4-form" data-mode="'.($reading?'answer':'none').'" data-kind="'.($reading?'read':'look').'" data-draft="off">'.csrf_field().'<input type="hidden" name="action" value="submit"><input type="hidden" name="activity_id" value="'.$a['id'].'"><input type="hidden" name="transcript" id="transcript" value=""><input type="hidden" name="drawing" id="drawing-data" value=""><input type="hidden" name="audio" id="l1-audio" value=""><input type="hidden" name="heard" id="l1-heard" value=""><input type="hidden" name="secs" id="l1-secs" value=""><span id="draft-status" hidden></span>';
 if($a['feedback'])echo '<div class="l1-note">'.l1_icon('teacher').'<div><b>A note from your teacher</b><p>'.e($a['feedback']).'</p></div></div>';
 echo '<span class="l1-tag">'.e(l4_tag($a,$lesson)).'</span>';
 if($reading){
  $say='Read the following passage aloud. Tap the microphone when you start, and tap it again when you finish. BULIG listens and scores your reading.';
  echo '<div class="l1-q sm l4-dir"><p>'.e($say).'</p>'.l1_speaker($say).'</div>';ob_start();echo level4_passage($a);
 }else{
  echo '<div class="l1-q sm"><p>'.nl2br(e((string)$a['prompt'])).'</p>'.l1_speaker((string)$a['prompt']).'</div>';ob_start();echo l1_pictures($a);
 }
 if($readonly){
  $secs=$c?l4_secs((string)$c['response']):0;
  echo '<div class="l1-done">'.l1_icon('ok').'<b>'.($reading?'Your reading is saved.'.($secs?' Time: '.intdiv($secs,60).':'.sprintf('%02d',$secs%60):''):'You saw this page.').'</b></div>';
  if($reading&&$c&&$c['audio_path'])echo '<div class="l1-mine"><span>Your reading</span><audio controls preload="none" src="?page=recording&amp;id='.(int)$c['id'].'"></audio></div>';
  $sc=$reading&&$c?l4_saved_score($c):null;if($sc&&!empty($sc['auto']))echo l4_result_card($a,$sc);
 }elseif($reading){
  echo roxanne_timer().'<div class="l1-rec l4-rec" data-l1-rec data-max="600"><button type="button" class="l1-mic" data-rec-go aria-label="Tap and read the passage">'.l1_icon('mic').'</button><b class="l1-rl" data-rec-label>Tap and read the whole passage</b><small class="l1-rs" data-rec-sub role="status"></small>'
   .'<div class="l1-play" data-rec-play hidden><button type="button" class="l1-pbtn" data-rec-listen aria-label="Play my reading">'.l1_icon('play').'</button><span class="l1-bar"><i></i></span><span class="l1-len" data-rec-len></span></div>'
   .'<button type="button" class="l1-heard" data-rec-heard>'.l1_icon('ok').'Done</button></div>';
 }
 $card=trim((string)ob_get_clean());if($card!=='')echo '<section class="l2-card l1-card l4-card"'.($reading&&$a['phase']==='learn'&&!$readonly?' data-readalong="1"':'').'>'.$card.'</section>';
 echo '<div class="l1-end"><div id="answer-feedback" role="status" aria-live="polite" class="answer-feedback l1-fb"></div><div class="l1-btns">';
 if(!$readonly){
  if($reading)echo '<button type="button" class="l1-btn2" data-rec-again hidden>'.l1_icon('again').'Try again</button><button id="submit-answer" class="l1-big" type="submit" hidden>Send '.l1_icon('next').'</button>';
  else echo '<button id="submit-answer" class="l1-big" type="submit">Next '.l1_icon('next').'</button>';
 }
 echo '<a id="next-activity" class="l1-big" href="?page=lesson&amp;id='.$lid.'"'.($readonly?'':' hidden').'>'.($doneAll===count($all)?'Finish lesson':'Next').' '.l1_icon('next').'</a></div></div></form></main></div>';
 foot();
}

/* ---------------- teacher review: the module's scoring table ---------------- */
/** The teacher's score in the review list, e.g. "92.5% · Instructional". */
function l4_score_text(?string $json):string{$j=json_decode((string)$json,true);return isset($j['l4'])?$j['l4']['score'].'% · '.$j['l4']['level']:'';}
function l4_saved_score(array $r):?array{$j=json_decode((string)($r['rubric_scores']??''),true);return is_array($j)&&isset($j['l4'])?$j['l4']:null;}
function l4_level(float $score):string{return $score>=98?'Independent':($score>=90?'Instructional':'Frustration');}
/** The scoring table for one Level 4 reading (shown in Teacher review). */
function l4_review_form(array $r):string{
 $act=$r+['prompt'=>(string)($r['prompt_snapshot']??'')];$p=level4_passage_parts($act);if(!$p)return '';$words=(int)$p['words'];$s=l4_saved_score($r);$secs=(int)($s['secs']??l4_secs((string)$r['response']));
 $auto=!empty($s['auto']);$tot=(int)($s['total']??0);$lv=$s?(string)$s['level']:'';
 $hy=['Mispronunciation'=>'Mispronun&shy;ciation','Substitution'=>'Substi&shy;tution','Repetition'=>'Repe&shy;tition','Transposition'=>'Transpo&shy;sition'];
 $h='<fieldset class="rv-rub l4-score l4a-t" data-words="'.$words.'"><legend>'.l1_icon('list').'Score with the module · '.e($p['title']).($auto?' <span class="l4a-auto">FILLED IN BY BULIG</span>':'').'</legend>';
 $h.='<p class="rv-rub-note">'.($auto?'BULIG listened to the reading and counted the miscues. Listen too, and change any number if needed.':'Listen to the reading. Count each miscue by type.').'</p>';
 $h.='<div class="l4a-tbl"><div class="l4a-row hd"><span>Types of Miscues</span>';foreach(L4_MISCUES as $m)$h.='<span>'.($hy[$m]??e($m)).'</span>';$h.='<span>Total</span></div>';
 $h.='<div class="l4a-row num l4-mis"><span>Number of Miscues</span>';foreach(L4_MISCUES as $i=>$m)$h.='<label data-n="'.e($m).'"><input'.($auto?' class="auto"':'').' type="number" inputmode="numeric" min="0" max="'.$words.'" name="miscue['.$i.']" value="'.(int)($s['m'][$i]??0).'" aria-label="'.e($m).'"></label>';
 $h.='<span data-n="Total"><output data-l4-total>'.$tot.'</output></span></div>';
 $row=fn($a,$b)=>'<div class="l4a-w"><div>'.$a.'</div><div>'.$b.'</div></div>';
 $h.=$row('Number of Words in the Passage','<b>'.$words.'</b>');
 $h.=$row('Oral Reading Score = <span data-l4-right>'.($words-$tot).'</span> / '.$words.' × 100','<b data-l4-score>'.($s?e($s['score'].'%'):'').'</b>');
 $h.=$row('Reading Level <small>(Ind 98–100, Ins 90–96, Frus 89 below)</small>','<b class="lv '.($lv!==''?l4_level_class($lv):'').'" data-l4-level>'.e($lv).'</b>');
 $h.=$row('Reading time in seconds','<input type="number" min="0" max="3600" name="l4secs" value="'.($secs?:'').'" data-l4-secs aria-label="Reading time in seconds">');
 $h.=$row('Reading Speed = Words Read / Reading time in seconds × 60','<b data-l4-wpm>'.($s&&$s['wpm']?e($s['wpm'].' words per minute'):'').'</b>').'</div>';
 if($auto)$h.=l4_marked($act,$s,true).(trim((string)($s['said']??''))!==''?'<details class="l4a-heard"><summary>What BULIG heard</summary><p>“'.e((string)$s['said']).'”</p></details>':'');
 return $h.'</fieldset>';
}
/** Save the teacher's table: returns the stored values, or null when this is not a Level 4 reading. */
function l4_review_values(array $act,array $post,?array $saved=null):?array{
 if(!isset($post['miscue'])||!level4_passage_parts($act))return null;
 $words=(int)level4_passage_parts($act)['words'];$m=[];foreach(L4_MISCUES as $i=>$x)$m[$i]=max(0,min($words,(int)($post['miscue'][$i]??0)));
 $tot=min($words,array_sum($m));$score=$words?round(($words-$tot)/$words*100,1):0;$secs=max(0,min(3600,(int)($post['l4secs']??0)));
 $v=['m'=>$m,'total'=>$tot,'words'=>$words,'score'=>$score,'level'=>l4_level($score),'secs'=>$secs,'wpm'=>$secs?(int)round(($words-$tot)/$secs*60):0];
 /* BULIG's marks and what it heard stay with the reading; the teacher's numbers are the score. */
 if($saved&&!empty($saved['auto']))$v+=['auto'=>1,'marks'=>$saved['marks']??[],'extra'=>$saved['extra']??[],'said'=>$saved['said']??'','teacher'=>1];
 return $v;
}
