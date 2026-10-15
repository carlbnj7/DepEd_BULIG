<?php
/* App ratings (v152): pupils rate BULIG with stars, teachers answer a short star evaluation, and the administrator reads
   the results in Reports › App ratings. Pupils are asked on their Overview (or on the "Lesson complete" screen) once they have finished a lesson, at most once a month; teachers once a
   quarter (after 14 days with BULIG) and any time from My profile › Rate BULIG. "Not now" waits 7 days (pupils) or 3 days
   (teachers). rate.js draws the stars, sends the rating and shows the thank-you. Table: app_ratings (029_app_ratings.sql). */
const RATE_QUESTIONS=[['Easy to use','Finding pages, adding pupils, printing'],['Helps my pupils read better','Lessons, practice and feedback'],
 ['Follows the DepEd modules','Same lessons, pictures and tests'],['Saves me time','Automatic checking, reading check, printing'],['Works on our devices and internet','Phones, computers, offline mode']];
const RATE_WORDS=['','Not really','A little','It’s okay','I like it!','I love it!'];

function rate_ready():bool{static $ok=null;if($ok===null){try{db()->query('SELECT 1 FROM app_ratings LIMIT 1');$ok=true;}catch(Throwable $e){$ok=false;}}return $ok;}
function rate_last(int $uid):?array{return rate_ready()?one('SELECT * FROM app_ratings WHERE user_id=? ORDER BY id DESC LIMIT 1',[$uid]):null;}
/** True when BULIG may ask this person for a rating now. */
function rate_due(array $u):bool{
 if(!rate_ready()||!in_array($u['role'],['pupil','teacher'],true))return false;$uid=(int)$u['id'];$now=date('Y-m-d H:i:s');
 $later=(string)(val('SELECT setting_value FROM settings WHERE setting_key=?',['rate_later_'.$uid])?:'');if($later!==''&&$later>$now)return false;
 $last=rate_last($uid);if($last&&$last['created_at']>date('Y-m-d H:i:s',strtotime($u['role']==='pupil'?'-30 days':'-90 days')))return false;
 if($u['role']==='teacher')return (string)($u['created_at']??'')!==''&&$u['created_at']<=date('Y-m-d H:i:s',strtotime('-14 days'));
 return (bool)val('SELECT 1 FROM pupil_progress WHERE pupil_id=? AND completed_at IS NOT NULL LIMIT 1',[$uid]);
}
/** Five stars to tap (radio buttons, so they also work with the keyboard). */
function rate_stars(string $name,string $label):string{
 $h='<div class="rt-stars" role="radiogroup" aria-label="'.e($label).'" data-rt-stars>';
 for($i=1;$i<=5;$i++)$h.='<label class="rt-star"><input type="radio" name="'.e($name).'" value="'.$i.'" required><span class="sr-only">'.$i.' '.($i===1?'star':'stars').'</span><i aria-hidden="true"></i></label>';
 return $h.'</div>';
}
function rate_script():string{return '<script defer src="'.asset_url('rate.js').'"></script>';}
/** Pupils: "Do you like learning with BULIG?" with five stars. Shown after a finished lesson when rate_due(). */
function rate_pupil_popup(array $u):string{
 $first=explode(' ',trim((string)$u['name']))[0];$q='Do you like learning with BULIG?';
 return '<div class="rt-ov" data-rt hidden role="dialog" aria-modal="true" aria-labelledby="rt-title"><form method="post" class="rt-card" data-rt-form data-rt-name="'.e($first).'">'.csrf_field()
  .'<input type="hidden" name="action" value="rate_app"><button type="button" class="rt-x" data-rt-later aria-label="Not now">'.icon('close').'</button>'
  .'<div class="rt-hero" aria-hidden="true"><i></i></div><span class="rt-eb">YOUR TURN</span><div class="rt-q"><h2 id="rt-title">'.e($q).'</h2><button type="button" class="rt-spk" data-rt-say="'.e($q.' Tap the stars.').'" aria-label="Hear the question">'.icon('sound').'</button></div>'
  .'<p class="rt-sub">Tap the stars, '.e($first).'.</p>'.rate_stars('stars','Your stars').'<div class="rt-word" data-rt-word aria-live="polite"></div>'
  .'<button class="rt-btn" data-rt-send>Send</button><button type="button" class="rt-later" data-rt-later>Not now</button></form></div>'.rate_script();
}
/** Teachers: the five-question evaluation. $popup: on Teacher Home; otherwise the Rate BULIG page. */
function rate_teacher_form(bool $popup):string{
 $h=($popup?'<div class="rt-ov" data-rt hidden role="dialog" aria-modal="true" aria-labelledby="rt-title">':'').'<form method="post" class="rt-card wide'.($popup?'':' rt-inline').'" data-rt-form data-rt-teacher>'.csrf_field()
  .'<input type="hidden" name="action" value="rate_app">'.($popup?'<button type="button" class="rt-x" data-rt-later aria-label="Remind me later">'.icon('close').'</button>':'')
  .'<span class="rt-eb">QUICK EVALUATION · 1 MINUTE</span><h2 id="rt-title">How is BULIG working for you?</h2><p class="rt-sub">Rate each one from 1 to 5 stars. It helps the Division improve BULIG.</p><div class="rt-rows">';
 foreach(RATE_QUESTIONS as $i=>[$q,$hint])$h.='<div class="rt-row"><div><b>'.($i+1).'. '.e($q).'</b><small>'.e($hint).'</small></div>'.rate_stars('q'.($i+1),$q).'</div>';
 $h.='</div><div class="rt-sum">Your overall rating <span data-rt-avg>–</span></div><label class="rt-lab">What should we improve? (optional)<textarea name="comment" maxlength="1000" rows="3"></textarea></label>'
  .'<button class="rt-btn" data-rt-send>Send evaluation</button>'.($popup?'<button type="button" class="rt-later" data-rt-later>Remind me later</button>':'').'</form>'.($popup?'</div>':'');
 return $h.rate_script();
}
/** My profile › Rate BULIG (teachers). */
function rate_page_view(array $u):void{
 echo hm_head('MY PROFILE · RATE BULIG','Rate BULIG','Tell the Division how BULIG is working for you and your pupils. It takes one minute.');
 if(!rate_ready()){echo '<section class="empty">'.icon('star').'<h2>Ratings are not installed yet.</h2><p>Ask your administrator to import 029_app_ratings.sql.</p></section>';return;}
 if($last=rate_last((int)$u['id']))echo '<p class="rt-lastnote">'.icon('check').'You last rated BULIG on '.e(date('F j, Y',strtotime((string)$last['created_at']))).': <b>'.e(rtrim(rtrim((string)$last['overall'],'0'),'.')).' ★</b>. You can rate it again any time.</p>';
 echo rate_teacher_form(false);
}
/** "Rate BULIG" on a teacher's My profile. */
function rate_profile_card():string{return rate_ready()?'<section class="card rt-pcard"><div><h2>'.icon('star').'Rate BULIG</h2><p class="muted">Five quick questions with stars. Your answers help the Division improve BULIG.</p></div><a class="btn secondary" href="?page=rate">Rate BULIG '.icon('arrow').'</a></section>':'';}

function rate_action(string $action):void{
 $u=current_user();if(!$u||!in_array($u['role'],['pupil','teacher'],true))fail('Only pupils and teachers rate BULIG.',403);if(!rate_ready())fail('Ratings are not installed yet.');
 $json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');$uid=(int)$u['id'];
 if($action==='rate_later'){
  q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['rate_later_'.$uid,date('Y-m-d H:i:s',strtotime($u['role']==='pupil'?'+7 days':'+3 days'))]);
  if($json){header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;}go('?page=dashboard');
 }
 if($action!=='rate_app')fail('Unknown action.');
 if($u['role']==='pupil'){$n=(int)($_POST['stars']??0);if($n<1||$n>5)fail('Tap the stars first.');$stars=$n;$overall=(float)$n;$answers=null;$comment=null;}
 else{$vals=[];foreach(RATE_QUESTIONS as $i=>$x){$v=(int)($_POST['q'.($i+1)]??0);if($v<1||$v>5)fail('Give stars for every question.');$vals[]=$v;}
  $overall=round(array_sum($vals)/count($vals),1);$stars=(int)round($overall);$answers=implode(',',$vals);$comment=mb_substr(trim((string)($_POST['comment']??'')),0,1000);if($comment==='')$comment=null;}
 q('INSERT INTO app_ratings(user_id,role,stars,overall,answers,comment) VALUES(?,?,?,?,?,?)',[$uid,$u['role'],$stars,$overall,$answers,$comment]);
 q('DELETE FROM settings WHERE setting_key=?',['rate_later_'.$uid]);audit('app_rating',$u['role'].' '.$overall);
 if($json){header('Content-Type: application/json');echo json_encode(['ok'=>true,'stars'=>$stars,'overall'=>$overall,'word'=>RATE_WORDS[$stars]??'']);exit;}
 flash('Thank you! Your rating was sent.');go($u['role']==='teacher'?'?page=rate':'?page=dashboard');
}

/** Reports › App ratings (administrator). Each person counts once in a period: their latest rating. */
function ratings_view():void{
 $per=in_array($_GET['period']??'',['quarter','year','all'],true)?$_GET['period']:'quarter';$since=match($per){'quarter'=>date('Y-m-d H:i:s',strtotime('-90 days')),'year'=>date('Y-m-d H:i:s',strtotime('-365 days')),default=>'1970-01-01'};
 $label=['quarter'=>'the last 3 months','year'=>'the last 12 months','all'=>'all time'][$per];
 echo hm_head('REPORTS · APP RATINGS','How do pupils and teachers rate BULIG?','Pupils rate with stars after they finish a lesson (at most once a month). Teachers answer a 5-question evaluation once each quarter, or any time from My profile. Each person counts once: their latest rating.');
 if(!rate_ready()){echo '<section class="empty">'.icon('star').'<h2>Ratings are not installed yet.</h2><p>Import live-update/029_app_ratings.sql in phpMyAdmin.</p></section>';return;}
 echo '<nav class="pupil-tabs rt-per" aria-label="Period">';foreach(['quarter'=>'3 months','year'=>'12 months','all'=>'All time'] as $k=>$t)echo '<a class="pt-tab'.($per===$k?' on':'').'" href="?page=ratings&amp;period='.$k.'"'.($per===$k?' aria-current="page"':'').'>'.$t.'</a>';echo '</nav>';
 $latest=fn(string $role)=>rows('SELECT r.* FROM app_ratings r JOIN (SELECT user_id,MAX(id) id FROM app_ratings WHERE role=? AND created_at>=? GROUP BY user_id) x ON x.id=r.id ORDER BY r.created_at DESC',[$role,$since]);
 $p=$latest('pupil');$t=$latest('teacher');$num=fn($x)=>rtrim(rtrim(number_format((float)$x,1),'0'),'.');
 $stars=function(float $avg):string{$n=(int)round($avg);return '<span class="rt-stars rt-show" aria-label="'.$n.' of 5 stars">'.str_repeat('<i class="on"></i>',$n).str_repeat('<i></i>',5-$n).'</span>';};
 echo '<div class="rt-grid"><section class="card rt-box"><h2>Pupils</h2>';
 if($p){$avg=array_sum(array_column($p,'overall'))/count($p);$by=array_count_values(array_map('intval',array_column($p,'stars')));
  echo '<div class="rt-big"><strong>'.$num($avg).'</strong><div>'.$stars($avg).'<small>'.count($p).' '.(count($p)===1?'pupil':'pupils').' rated BULIG in '.$label.'</small></div></div>';
  for($i=5;$i>=1;$i--){$c=$by[$i]??0;echo '<div class="rt-bar"><span>'.$i.' ★</span><div><i data-w="'.round(100*$c/count($p)).'"></i></div><span>'.$c.'</span></div>';}}
 else echo '<p class="muted">No pupil ratings in '.$label.' yet. Pupils are asked after they finish a lesson.</p>';
 echo '</section><section class="card rt-box"><h2>Teachers</h2>';
 if($t){$avg=array_sum(array_column($t,'overall'))/count($t);$q=array_fill(0,count(RATE_QUESTIONS),0.0);foreach($t as $r)foreach(explode(',',(string)$r['answers']) as $i=>$v)if(isset($q[$i]))$q[$i]+=(int)$v;
  echo '<div class="rt-big"><strong>'.$num($avg).'</strong><div>'.$stars($avg).'<small>'.count($t).' '.(count($t)===1?'teacher':'teachers').' answered in '.$label.'</small></div></div><div class="rt-crit">';
  foreach(RATE_QUESTIONS as $i=>[$qq])echo '<span>'.($i+1).'. '.e($qq).'</span><em>'.$num($q[$i]/count($t)).' ★</em>';echo '</div>';
  $notes=array_values(array_filter($t,fn($r)=>trim((string)$r['comment'])!==''));
  if($notes){echo '<p class="eyebrow rt-said">WHAT TEACHERS SAID</p><ul class="rt-notes">';
   foreach(array_slice($notes,0,20) as $r){$g=array_values(array_unique(array_map(fn($s)=>(int)$s['grade_level'],teacher_sections((int)$r['user_id']))));sort($g);
    echo '<li>'.nl2br(e((string)$r['comment'])).'<small>'.($g?'Grade '.implode(' / ',$g).' teacher':'Teacher').' · '.e(date('M j',strtotime((string)$r['created_at']))).'</small></li>';}
   echo '</ul>';}}
 else echo '<p class="muted">No teacher evaluations in '.$label.' yet. Teachers are asked once each quarter.</p>';
 echo '</section></div>'.rate_script();
}
