<?php
/* Offline mode: pupils save a level on their device, answer without internet,
   and the saved answers are sent when the device is back online. */

/** True when an offline copy of a lesson page is requested (?off=1). Lesson order is relaxed
    inside a level the pupil can already open, so later lessons can be answered offline. */
function offline_view(int $pid,int $lid):bool{
 if(($_GET['off']??'')!=='1'||$_SERVER['REQUEST_METHOD']!=='GET')return false;
 $l=one('SELECT m.level_id,m.grade_level FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=? AND l.published=1',[$lid]);
 if(!$l||($l['grade_level']!==null&&(int)$l['grade_level']!==pupil_grade($pid)))return false;
 return level_available($pid,(int)$l['level_id']);
}

/** The time an offline answer was really given, or null when the answer was sent online. */
function offline_answer_time():?string{
 $t=(string)($_POST['offline_at']??'');if($t==='')return null;
 $ts=strtotime($t);if(!$ts)return null;
 if($ts>time()+300||$ts<time()-60*86400)return null;
 return date('Y-m-d H:i:s',$ts);
}

/** JSON list of the pages a pupil needs to use one level offline. */
function offline_manifest(array $u):never{
 $pid=(int)$u['id'];$level=(int)($_GET['level']??0);$g=pupil_grade($pid);
 header('Content-Type: application/json');header('Cache-Control: no-store');
 if(!level_available($pid,$level)){http_response_code(403);echo json_encode(['error'=>'This level is not open yet.']);exit;}
 $lessons=[];
 foreach(rows('SELECT l.id,l.position,l.subtitle FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1'.GRADE_SQL.' ORDER BY l.position,l.id',[$level,$g]) as $l){
  $acts=[];foreach(activities((int)$l['id'],$pid) as $a)$acts[]=['id'=>(int)$a['id'],'done'=>completion_ok($a),'mode'=>$a['response_mode']];
  $lessons[]=['id'=>(int)$l['id'],'complete'=>(bool)val('SELECT completed_at FROM pupil_progress WHERE pupil_id=? AND lesson_id=?',[$pid,(int)$l['id']]),'title'=>lesson_display_label($level,(int)$l['position']).' · '.$l['subtitle'],'acts'=>$acts];
 }
 echo json_encode(['uid'=>$pid,'level'=>$level,'title'=>level_label($level),'lessons'=>$lessons,'pages'=>['?page=dashboard','?page=lessons','?page=lessons&level='.$level,'?page=profile','?page=calendar','?page=achievements']]);exit;
}

/** Who is signed in, and a fresh form token, so saved answers can be sent. */
function offline_sync_info():never{
 header('Content-Type: application/json');header('Cache-Control: no-store');
 $u=current_user();echo json_encode(['uid'=>$u&&$u['role']==='pupil'?(int)$u['id']:0,'csrf'=>$u?csrf():'']);exit;
}
