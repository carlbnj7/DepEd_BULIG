<?php
/* Private recordings of actual activity attempts. Teacher decisions never identify a speaker automatically. */
function rv_ready():bool{static $ok;if($ok===null){try{$ok=(bool)one("SHOW TABLES LIKE 'reading_verifications'");}catch(Throwable $e){$ok=false;}}return $ok;}
function rv_latest(int $cid):?array{return rv_ready()?one('SELECT * FROM reading_verifications WHERE completion_id=? ORDER BY id DESC LIMIT 1',[$cid]):null;}
function rv_reading_texts(array $a):array{
 $out=[];$text=trim((string)($a['expected_text']??''));if($text!=='')$out[0]=$text;
 if(in_array(lesson_level((int)$a['lesson_id']),[2,3,4,9,6,7,8],true)&&($set=l2a_set($a))){
  foreach($set['cards'] as $i=>$c){$k=$c['ui']['answer']??[];$text=in_array('read',$k,true)?trim((string)($c['ui']['wct']??'')):'';
   if($text===''&&in_array('say',$k,true))$text=wc_card_text($c,(string)($set['instruction']??''));if($text!=='')$out[$i+1]=$text;
  }
 }return $out;
}
function rv_snapshot(array $a,string $instructions):string{return json_encode(['bulig_reading_snapshot'=>1,'instructions'=>$instructions,'readings'=>rv_reading_texts($a)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
function rv_reference(array $r):array{
 $raw=(string)$r['prompt_snapshot'];$j=json_decode($raw,true);
 if(is_array($j)&&($j['bulig_reading_snapshot']??null)===1&&is_array($j['readings']??null))return ['instructions'=>(string)($j['instructions']??''),'readings'=>$j['readings'],'historic'=>true];
 $a=one('SELECT * FROM activities WHERE id=?',[$r['activity_id']]);return ['instructions'=>$raw,'readings'=>$a?rv_reading_texts($a):[],'historic'=>false];
}

/** Called inside the pupil/user transaction, after scoring. Historical recordings remain attached to their attempt. */
function rv_capture(int $cid,array $a):void{
 if(!rv_ready())return;
 $c=one('SELECT * FROM activity_completion WHERE id=?',[$cid]);$wc=wc_post();$input=$_POST['coach_audio']??[];
 if(!is_array($input))fail('Invalid reading recording.');
 $paths=[];$total=0;foreach($input as $n=>$data){if(!ctype_digit((string)$n)||(int)$n>60||!is_string($data))fail('Invalid reading recording.');if($data==='')continue;$total+=strlen($data);if($total>9000000)fail('These recordings are too large. Please keep each reading short.');$paths[(string)(int)$n]=l1_save_audio($data,(int)$c['pupil_id'],(int)$a['id']);}
 if(!empty($c['audio_path'])&&!isset($paths['0']))$paths['0']=$c['audio_path'];
 $paths+=json_decode((string)($c['audio_paths']??''),true)?:[];
 // Some later-level cards use the shared recorder but not the legacy audio_paths whitelist.
 foreach((array)($_POST['audio_card']??[]) as $n=>$data){if(!ctype_digit((string)$n)||(int)$n<1||(int)$n>60||!is_string($data))fail('Invalid card recording.');if($data===''||isset($paths[(string)$n]))continue;$total+=strlen($data);if($total>9000000)fail('These recordings are too large.');$paths[(string)$n]=l1_save_audio($data,(int)$c['pupil_id'],(int)$a['id']);}
 if(empty($paths['0'])&&!empty($_POST['audio']))$paths['0']=l1_save_audio((string)$_POST['audio'],(int)$c['pupil_id'],(int)$a['id']);
 if(!$wc&&!$paths&&!rv_latest($cid)&&empty($_POST['heard'])&&!preg_match('~\(spoken answer|\(read aloud|\(read with Word Coach|Said:~i',(string)$c['response']))return;
 // A retry must carry new audio. Never accept a previous attempt's stored paths as a fresh recording.
 $prev=rv_latest($cid);if($prev&&$prev['state']==='rejected'&&!$input&&empty($_POST['audio'])&&empty($_POST['audio_card']))fail('Please record your reading again before sending.');
 if($prev&&$prev['state']==='rejected'&&!$paths)fail('Please record your reading again before sending.');
 $complete=(bool)$paths;foreach($wc as $n=>$x)if(empty($paths[(string)$n]))$complete=false;
 if($prev&&$prev['state']==='rejected'&&!$complete)fail('Please record every reading card again before sending.');
 q("UPDATE reading_verifications SET state='superseded' WHERE completion_id=? AND state IN ('pending','missing')",[$cid]);
 q('INSERT INTO reading_verifications(completion_id,pupil_id,activity_id,state,audio_paths,prompt_snapshot,response_snapshot,score,max_score) VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$c['pupil_id'],$a['id'],$complete?'pending':'missing',json_encode($paths),rv_snapshot($a,(string)$c['prompt_snapshot']),$c['response'],$c['score'],$c['max_score']]);
}
function rv_count(int $tid):int{return rv_ready()?(int)val("SELECT COUNT(*) FROM reading_verifications r JOIN teacher_pupils t ON t.pupil_id=r.pupil_id WHERE t.teacher_id=? AND r.state IN ('pending','missing')",[$tid]):0;}
/** Same pupil lock as answer submission; row version = immutable attempt ID, so stale decisions cannot reject a reread. */
function rv_decide(array $u,int $id,string $decision,string $reason):void{
 if(!rv_ready())fail('Import the reading verification update first.');
 if(!in_array($decision,['verify','reject'],true))fail('Choose a review decision.');
 $r=one('SELECT * FROM reading_verifications WHERE id=?',[$id]);if(!$r)fail('Reading not found.',404);own_pupil((int)$r['pupil_id']);
 if(mb_strlen($reason)>3000||($decision==='reject'&&trim($reason)===''))fail('Enter a reason for rereading (up to 3,000 characters).');
 db()->beginTransaction();try{
  q('SELECT id FROM users WHERE id=? FOR UPDATE',[$r['pupil_id']]);$r=one('SELECT * FROM reading_verifications WHERE id=? FOR UPDATE',[$id]);$c=one('SELECT * FROM activity_completion WHERE id=? FOR UPDATE',[$r['completion_id']]);
  $last=rv_latest((int)$r['completion_id']);if(!$c||!$last||(int)$last['id']!==$id||!in_array($r['state'],['pending','missing'],true))fail('This attempt was already reviewed or replaced. Refresh the page.');
  if($decision==='verify'&&$r['state']==='missing')fail('There is no complete recording to verify. Request a recorded rereading instead.');
  $deduct=0;$a=one('SELECT * FROM activities WHERE id=?',[$r['activity_id']]);
  if($decision==='reject'){
   $x=one('SELECT amount FROM xp_transactions WHERE pupil_id=? AND activity_id=? FOR UPDATE',[$r['pupil_id'],$r['activity_id']]);
   $balance=(int)val('SELECT total FROM pupil_xp WHERE pupil_id=? FOR UPDATE',[$r['pupil_id']]);$deduct=min(max(0,(int)($x['amount']??0)),max(0,$balance));
   if($x){q('DELETE FROM xp_transactions WHERE pupil_id=? AND activity_id=?',[$r['pupil_id'],$r['activity_id']]);if($deduct)q('UPDATE pupil_xp SET total=total-? WHERE pupil_id=?',[$deduct,$r['pupil_id']]);}
   q("UPDATE activity_completion SET status='retry',score=0,feedback=?,reviewed_by=?,reviewed_at=NULL WHERE id=?",[$reason,$u['id'],$c['id']]);
   q('DELETE FROM activity_drafts WHERE pupil_id=? AND activity_id=?',[$r['pupil_id'],$r['activity_id']]);
   q('UPDATE pupil_progress SET completed_at=NULL,last_activity_id=? WHERE pupil_id=? AND lesson_id=?',[$r['activity_id'],$r['pupil_id'],$a['lesson_id']]);
   if(!empty($a['assessment_id']))q("UPDATE assessment_attempts SET status='in_progress',score=NULL,max_score=NULL,completed_at=NULL,reviewed_by=NULL WHERE pupil_id=? AND assessment_id=?",[$r['pupil_id'],$a['assessment_id']]);
   sync_assessments((int)$r['pupil_id'],(int)$a['lesson_id']);
   if(function_exists('rl_fix_badges'))rl_fix_badges((int)$r['pupil_id'],rl_info((int)$r['pupil_id'],true)['level']);
  }
  q('UPDATE reading_verifications SET state=?,reason=?,reviewed_by=?,reviewed_at=NOW(),xp_reversed=? WHERE id=?',[$decision==='verify'?'verified':'rejected',$reason,$u['id'],$deduct,$id]);
  $body=$decision==='verify'?'Your teacher verified your reading.':$reason.' XP removed: '.$deduct.'. Record this activity again to earn its points back.';
  q('INSERT INTO response_history(completion_id,response,status,actor_id) VALUES(?,?,?,?)',[$c['id'],$body,$decision==='verify'?'verified':'redo',$u['id']]);
  notify((int)$r['pupil_id'],$decision==='verify'?'note':'again',$decision==='verify'?'Reading verified':'Please read this activity again',$body,'?page=lesson&id='.(int)$a['lesson_id'].'&activity='.(int)$a['id']);
  audit('reading_'.$decision,(string)$id);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function rv_action():never{
 $u=require_role('teacher');rv_decide($u,(int)($_POST['attempt_id']??0),(string)($_POST['decision']??''),trim((string)($_POST['reason']??'')));flash('Reading review saved. The pupil has been notified.');go('?page=reading_reviews');
}
function rv_view(array $u):void{
 echo '<div class="pagehead"><div><span class="eyebrow">TEACHING</span><h1>Reading verification</h1><p>Listen to the pupil’s actual activity attempt. Scores and XP are awarded immediately; rejection reverses only that activity’s earned XP and requests rereading.</p></div></div>';
 if(!rv_ready()){echo '<section class="card"><p>Import 035_reading_verification.sql to enable recording reviews.</p></section>';return;}
 $state=(string)($_GET['show']??'pending');if(!in_array($state,['pending','missing','verified','rejected','superseded'],true))$state='pending';
 echo '<nav class="buttonrow">';foreach(['pending'=>'To verify','missing'=>'Missing audio','verified'=>'Verified','rejected'=>'Rereading requested','superseded'=>'Replaced attempts'] as $k=>$v)echo '<a class="btn '.($state===$k?'primary':'secondary').'" href="?page=reading_reviews&amp;show='.$k.'">'.$v.'</a>';echo '</nav>';
 $p=max(1,min(100000,(int)($_GET['p']??1)));
 $list=rows('SELECT r.*,u.name,a.title,a.lesson_id,m.level_id FROM reading_verifications r JOIN teacher_pupils t ON t.pupil_id=r.pupil_id JOIN users u ON u.id=r.pupil_id JOIN activities a ON a.id=r.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE t.teacher_id=? AND r.state=? ORDER BY r.id DESC LIMIT 20 OFFSET '.(($p-1)*20),[$u['id'],$state]);
 foreach($list as $r){$ref=rv_reference($r);echo '<section class="card rv-review"><span class="eyebrow">'.e(level_label((int)$r['level_id'])).' · '.e($r['created_at']).'</span><h2>'.e($r['name']).' · '.e($r['title']).'</h2><p>Score for this attempt: '.e(key_score_text($r)?:'Not automatically scored').'</p>';
  $paths=json_decode((string)$r['audio_paths'],true)?:[];foreach($paths as $n=>$path){$text=trim((string)($ref['readings'][(int)$n]??''));$label=(int)$n?'Card '.(int)$n:'Activity reading';
   echo '<div class="rv-recording"><div class="rv-reference"><span class="eyebrow">'.e($label).'</span><h3>'.($text!==''?'Words to read':'Activity instructions').'</h3><div class="rv-passage" tabindex="0" role="region" aria-label="Reading reference">'.nl2br(e($text!==''?$text:$ref['instructions'])).'</div>'.(!$ref['historic']?'<small class="muted">Current activity reference · this older recording has no saved text snapshot.</small>':'').'</div><div class="rv-player"><h3>Listen to the pupil</h3><p>Follow the words as you listen.</p><audio controls preload="none" aria-label="'.e($label).' recording" src="?page=reading_audio&amp;id='.(int)$r['id'].'&amp;n='.(int)$n.'"></audio></div></div>';
  }
  echo '<details><summary>Activity instructions and pupil answer</summary><p class="preserve">'.e($ref['instructions']).'</p><p class="preserve">'.e($r['response_snapshot']).'</p></details>';
  if($state==='missing')echo '<p>No complete audio evidence. This does not prove misconduct. Arrange a rereading or a supervised check.</p>';
  if(in_array($state,['pending','missing'],true))echo '<form method="post" class="stack">'.csrf_field().'<input type="hidden" name="action" value="reading_decision"><input type="hidden" name="attempt_id" value="'.(int)$r['id'].'"><label>Reason or note<textarea name="reason" rows="2" maxlength="3000"></textarea></label><div class="buttonrow">'.($state==='pending'?'<button class="btn primary" name="decision" value="verify">Verify reading</button>':'').'<button class="btn secondary" name="decision" value="reject" data-confirm="Reject this attempt, reverse its earned activity XP once, and require a new recording?">Reject and request rereading</button></div></form>';
  else echo '<p>'.e($r['reason']).'</p><p>XP reversed: '.(int)$r['xp_reversed'].'</p>';echo '</section>';
 }
 if(!$list)echo '<section class="card"><p>No recordings in this category.</p></section>';
 echo '<div class="buttonrow">'.($p>1?'<a class="btn secondary" href="?page=reading_reviews&amp;show='.$state.'&amp;p='.($p-1).'">Previous</a>':'').(count($list)===20?'<a class="btn secondary" href="?page=reading_reviews&amp;show='.$state.'&amp;p='.($p+1).'">Next</a>':'').'</div>';
}
function rv_pupil_notice(int $pid):string{
 if(!rv_ready())return '';$r=one("SELECT r.reason,a.title,a.lesson_id,a.id FROM reading_verifications r JOIN activity_completion c ON c.id=r.completion_id JOIN activities a ON a.id=r.activity_id WHERE r.pupil_id=? AND r.state='rejected' AND c.status='retry' AND r.id=(SELECT MAX(v.id) FROM reading_verifications v WHERE v.completion_id=r.completion_id) ORDER BY r.id LIMIT 1",[$pid]);
 return $r?'<section class="card"><h2>Please read this activity again</h2><p>'.e($r['reason']).'</p><a class="btn primary" href="?page=lesson&amp;id='.(int)$r['lesson_id'].'&amp;activity='.(int)$r['id'].'">Open '.e($r['title']).'</a></section>':'';
}
