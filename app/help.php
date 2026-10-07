<?php
/* The "I need help" button on every lesson screen. Table: database/migrations/025_help_requests.sql (without it the button stays hidden). */

function help_ready():bool{static $r=null;if($r===null){try{$r=(bool)one("SHOW TABLES LIKE 'help_requests'");}catch(Throwable $e){$r=false;}}return $r;}
const HELP_REASONS=['question'=>'I don’t understand the question','sound'=>'I can’t hear the sound','answer'=>'I don’t know how to answer'];
/** The button and its sheet on every lesson screen (added by foot()). */
function help_widget():string{
 $u=current_user();if(!$u||$u['role']!=='pupil'||($_GET['page']??'')!=='lesson'||!help_ready())return '';
 $lid=(int)($_GET['id']??0);$aid=(int)($_GET['activity']??0);if(!$lid)return '';
 $ic=['question'=>'<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6M12 17h.01"/>','sound'=>'<path d="m11 4-5 4H3v8h3l5 4zm5 5 6 6m0-6-6 6"/>','answer'=>'<path d="M4 20h4L19 9l-4-4L4 16z"/>'];
 $h='';if(isset($_SESSION['help_toast'])){$h.='<div class="hp-toast" role="status">'.icon('check').'<span>'.e((string)$_SESSION['help_toast']).'</span></div>';unset($_SESSION['help_toast']);}
 $h.='<button type="button" class="hp-open" data-help-open aria-label="I need help">'.'<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-1V4.5a1.5 1.5 0 0 1 3 0V10m0-4.5a1.5 1.5 0 0 1 3 0V11m0-3a1.5 1.5 0 0 1 3 0v6a7 7 0 0 1-7 7h-1a7 7 0 0 1-5.6-2.8L4 15.5a1.6 1.6 0 0 1 2.5-2L8 15"/></svg>'.'<span>I need help</span></button>';
 $h.='<dialog class="hp-sheet" aria-labelledby="hp-title"><form method="post" class="hp-card">'.csrf_field().'<input type="hidden" name="action" value="help_request"><input type="hidden" name="lesson_id" value="'.$lid.'"><input type="hidden" name="activity_id" value="'.$aid.'"><input type="hidden" name="back" value="'.e('?'.(string)($_SERVER['QUERY_STRING']??'')).'">';
 $h.='<h2 id="hp-title">I need help</h2><p>Tell your teacher what is hard. They will come to you.</p>';
 foreach(HELP_REASONS as $k=>$t)$h.='<label class="hp-opt"><input type="radio" name="reason" value="'.$k.'"'.($k==='question'?' checked':'').'><i><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic[$k].'</svg></i><span>'.e($t).'</span></label>';
 $h.='<p class="hp-off" data-help-off hidden>You are offline. Raise your hand so your teacher can see you.</p><button class="btn primary hp-send">Tell my teacher</button><button type="button" class="btn quiet hp-cancel" data-help-close>Cancel</button></form></dialog>';
 return $h;
}
function help_request(array $u):void{
 $pid=(int)$u['id'];$back=(string)($_POST['back']??'');$back=preg_match('/^\?page=lesson(&[A-Za-z0-9_=%&.-]*)?$/',$back)?$back:'?page=dashboard';
 $r=(string)($_POST['reason']??'');if(!isset(HELP_REASONS[$r])||!help_ready())go($back);
 $lid=(int)($_POST['lesson_id']??0);$aid=(int)($_POST['activity_id']??0);
 if($lid&&!lesson_available($pid,$lid))go($back);
 /* One request per lesson every 5 minutes, so a tap-happy pupil does not flood the teacher. */
 if(val('SELECT 1 FROM help_requests WHERE pupil_id=? AND COALESCE(lesson_id,0)=? AND created_at>?',[$pid,$lid,date('Y-m-d H:i:s',time()-300)])){$_SESSION['help_toast']='Your teacher already knows. They will come to you soon.';go($back);}
 q('INSERT INTO help_requests(pupil_id,lesson_id,activity_id,reason) VALUES(?,?,?,?)',[$pid,$lid?:null,$aid?:null,$r]);
 $where='';if($aid&&($a=one('SELECT title FROM activities WHERE id=?',[$aid])))$where=(string)$a['title'];elseif($lid&&($l=one('SELECT title,subtitle FROM lessons WHERE id=?',[$lid])))$where=lesson_home_name($l);
 $first=explode(' ',trim((string)$u['name']))[0];
 notify_teachers($pid,'help',$first.' needs help',HELP_REASONS[$r].($where!==''?' · '.$where:'').($lid?' · '.level_label(lesson_level($lid)):''),'?page=pupil&id='.$pid);
 $_SESSION['help_toast']='Your teacher was told. They will come to you soon.';go($back);
}


function lesson_home_name(array $l):string{$t=(string)$l['title'];$s=trim((string)($l['subtitle']??''));return str_starts_with($t,'Activity')&&$s!==''?$s.' ('.$t.')':$t;}
