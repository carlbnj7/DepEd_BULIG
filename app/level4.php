<?php
/* Level 4 (Fluency, level id 5; one module per grade): the same clean screens as Levels 1 to 3.
   - Each passage is shown as in the module (picture, title, author, the words, "Number of words in the passage").
   - The pupil taps the microphone, reads the whole passage aloud, and taps again to stop. The reading time is kept.
     "Done" is for reading at home or without a microphone.
   - The teacher scores the reading with the module's own table: miscues by type, Oral Reading Score,
     Reading Level (Ind 98-100, Ins 90-96, Frus 89 below) and Reading Speed (words per minute). */

const L4_MISCUES=['Mispronunciation','Omission','Substitution','Insertion','Repetition','Transposition','Reversal'];

function l4_is(int $lid):bool{return lesson_level($lid)===5;}
function l4_tag(array $a,array $lesson):string{
 if($a['phase']==='pre')return 'PRE-TEST PASSAGE';if($a['phase']==='post')return 'POST-TEST PASSAGE';
 return 'PASSAGE '.max(1,(int)$lesson['position']-1);
}
/** Reading time saved with the answer: "time 1:32". */
function l4_secs(string $response):int{return preg_match('~time (\d+):(\d\d)~',$response,$m)?(int)$m[1]*60+(int)$m[2]:0;}

function l4_activity_page(array $u,array $lesson,array $all,array $a):void{
 $lid=(int)$lesson['id'];$lv=lesson_level($lid);$readonly=completion_ok($a);$reading=$a['type']==='reading'&&level4_passage_parts($a);
 $c=$a['completion_id']?one('SELECT id,audio_path,response FROM activity_completion WHERE id=?',[(int)$a['completion_id']]):null;
 $doneAll=count(array_filter($all,fn($x)=>completion_ok($x)));
 head(level_label($lv).' · '.$lesson['subtitle'],'activity-page l1-page l4-page');
 echo '<div class="l1-app l4-app">'.lesson_head($lesson,$all,$a,'?page=lessons&level='.$lv.'#level'.$lv.'-path').'<main class="l1-main activity-main">';notice();
 echo '<form method="post" id="activity-form" class="activity-form l1-form l4-form" data-mode="'.($reading?'answer':'none').'" data-kind="'.($reading?'read':'look').'" data-draft="off">'.csrf_field().'<input type="hidden" name="action" value="submit"><input type="hidden" name="activity_id" value="'.$a['id'].'"><input type="hidden" name="transcript" id="transcript" value=""><input type="hidden" name="drawing" id="drawing-data" value=""><input type="hidden" name="audio" id="l1-audio" value=""><input type="hidden" name="heard" id="l1-heard" value=""><input type="hidden" name="secs" id="l1-secs" value=""><span id="draft-status" hidden></span>';
 if($a['feedback'])echo '<div class="l1-note">'.l1_icon('teacher').'<div><b>A note from your teacher</b><p>'.e($a['feedback']).'</p></div></div>';
 echo '<span class="l1-tag">'.e(l4_tag($a,$lesson)).'</span>';
 if($reading){
  $say='Read the following passage aloud. Tap the microphone when you start, and tap it again when you finish.';
  echo '<div class="l1-q sm l4-dir"><p>'.e($say).'</p>'.l1_speaker($say).'</div>';ob_start();echo level4_passage($a);
 }else{
  echo '<div class="l1-q sm"><p>'.nl2br(e((string)$a['prompt'])).'</p>'.l1_speaker((string)$a['prompt']).'</div>';ob_start();echo l1_pictures($a);
 }
 if($readonly){
  $secs=$c?l4_secs((string)$c['response']):0;
  echo '<div class="l1-done">'.l1_icon('ok').'<b>'.($reading?'Your reading is saved.'.($secs?' Time: '.intdiv($secs,60).':'.sprintf('%02d',$secs%60):''):'You saw this page.').'</b></div>';
  if($reading&&$c&&$c['audio_path'])echo '<div class="l1-mine"><span>Your reading</span><audio controls preload="none" src="?page=recording&amp;id='.(int)$c['id'].'"></audio></div>';
 }elseif($reading){
  echo '<div class="l1-rec l4-rec" data-l1-rec data-max="600"><button type="button" class="l1-mic" data-rec-go aria-label="Tap and read the passage">'.l1_icon('mic').'</button><b class="l1-rl" data-rec-label>Tap and read the whole passage</b><small class="l1-rs" data-rec-sub role="status"></small>'
   .'<div class="l1-play" data-rec-play hidden><button type="button" class="l1-pbtn" data-rec-listen aria-label="Play my reading">'.l1_icon('play').'</button><span class="l1-bar"><i></i></span><span class="l1-len" data-rec-len></span></div>'
   .'<button type="button" class="l1-heard" data-rec-heard>'.l1_icon('ok').'Done</button></div>';
 }
 $card=trim((string)ob_get_clean());if($card!=='')echo '<section class="l2-card l1-card l4-card">'.$card.'</section>';
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
 $p=level4_passage_parts($r+['prompt'=>(string)($r['prompt_snapshot']??'')]);if(!$p)return '';$words=(int)$p['words'];$s=l4_saved_score($r);$secs=(int)($s['secs']??l4_secs((string)$r['response']));
 $h='<fieldset class="rv-rub l4-score" data-words="'.$words.'"><legend>'.l1_icon('list').'Score with the module · '.e($p['title']).'</legend>';
 $h.='<p class="rv-rub-note">Listen to the reading. Count each miscue by type.</p><div class="l4-mis">';
 foreach(L4_MISCUES as $i=>$m)$h.='<label><span>'.e($m).'</span><input type="number" inputmode="numeric" min="0" max="'.$words.'" name="miscue['.$i.']" value="'.(int)($s['m'][$i]??0).'"></label>';
 $h.='<label class="l4-tot"><span>Total</span><output data-l4-total>'.array_sum(array_map('intval',$s['m']??[])).'</output></label></div>';
 $h.='<div class="l4-res"><p><span>Number of words in the passage</span><b>'.$words.'</b></p>';
 $h.='<p><span>Oral Reading Score = ('.$words.' − miscues) / '.$words.' × 100</span><b data-l4-score>'.($s?e($s['score'].'%'):'').'</b></p>';
 $h.='<p><span>Reading Level (Ind 98–100, Ins 90–96, Frus 89 below)</span><b data-l4-level>'.($s?e($s['level']):'').'</b></p>';
 $h.='<p><span>Reading time in seconds</span><input type="number" min="0" max="3600" name="l4secs" value="'.($secs?:'').'" data-l4-secs></p>';
 $h.='<p><span>Reading Speed = words read / reading time in seconds × 60</span><b data-l4-wpm>'.($s&&$s['wpm']?e($s['wpm'].' words per minute'):'').'</b></p></div></fieldset>';
 return $h;
}
/** Save the teacher's table: returns the stored values, or null when this is not a Level 4 reading. */
function l4_review_values(array $act,array $post):?array{
 if(!isset($post['miscue'])||!level4_passage_parts($act))return null;
 $words=(int)level4_passage_parts($act)['words'];$m=[];foreach(L4_MISCUES as $i=>$x)$m[$i]=max(0,min($words,(int)($post['miscue'][$i]??0)));
 $tot=min($words,array_sum($m));$score=$words?round(($words-$tot)/$words*100,1):0;$secs=max(0,min(3600,(int)($post['l4secs']??0)));
 return ['m'=>$m,'total'=>$tot,'words'=>$words,'score'=>$score,'level'=>l4_level($score),'secs'=>$secs,'wpm'=>$secs?(int)round(($words-$tot)/$secs*60):0];
}
