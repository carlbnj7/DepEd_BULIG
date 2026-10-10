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
 if(!level_downloadable($pid,$level)){http_response_code(403);echo json_encode(['error'=>'This level cannot be saved.']);exit;}
 /* A locked level saves only its pictures (the big part). Its lesson pages are added once it opens. */
 if(!level_available($pid,$level)){$files=[level_cover($level)=>1];
  foreach(rows('SELECT a.* FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1 AND a.published=1'.GRADE_SQL,[$level,$g]) as $a){foreach(activity_images($a) as $src){$f=resolved_image_path((string)$src);if($f)$files[$f]=1;}
   /* The pictures on its cards too (matching pictures, story pictures). */
   $set=level2_card_set($a);foreach($set['cards']??[] as $c){foreach(array_merge(array_column($c['images']??[],'src'),array_values($c['choice_images']??[])) as $src){$f=resolved_image_path((string)$src);if($f)$files[$f]=1;}}}
  echo json_encode(['uid'=>$pid,'level'=>$level,'title'=>level_label($level),'locked'=>true,'lessons'=>[],'pages'=>[],'assets'=>array_keys($files)]);exit;}
 $lessons=[];
 foreach(rows('SELECT l.id,l.position,l.subtitle FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1'.GRADE_SQL.' ORDER BY l.position,l.id',[$level,$g]) as $l){
  $acts=[];foreach(activities((int)$l['id'],$pid) as $a)$acts[]=['id'=>(int)$a['id'],'done'=>completion_ok($a),'mode'=>$a['response_mode']];
  $lessons[]=['id'=>(int)$l['id'],'complete'=>(bool)val('SELECT completed_at FROM pupil_progress WHERE pupil_id=? AND lesson_id=?',[$pid,(int)$l['id']]),'title'=>lesson_display_label($level,(int)$l['position']).' · '.$l['subtitle'],'acts'=>$acts];
 }
 echo json_encode(['uid'=>$pid,'level'=>$level,'title'=>level_label($level),'lessons'=>$lessons,'pages'=>['?page=dashboard','?page=lessons','?page=lessons&level='.$level,'?page=profile','?page=calendar','?page=achievements','?page=offline']]);exit;
}

/** Who is signed in, and a fresh form token, so saved answers can be sent
    (the token also lets a saved account sign in again before uploading). */
function offline_sync_info():never{
 header('Content-Type: application/json');header('Cache-Control: no-store');
 $u=current_user();echo json_encode(['uid'=>$u&&$u['role']==='pupil'?(int)$u['id']:0,'csrf'=>csrf()]);exit;
}

/** Levels a pupil may save on the device: every published level that has lessons for their grade.
    Levels that are still locked can be saved ahead; they open only after the levels before them are finished. */
function level_downloadable(int $pid,int $level):bool{
 if(!(int)val('SELECT published FROM bulig_levels WHERE id=?',[$level]))return false;
 if(!pupil_start_level($pid))return false;
 return (bool)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$level,pupil_grade($pid)]);
}

/** The levels a pupil can save (open now or locked for now), with an estimate of how much each one takes on the device,
    for the "Learn even without internet" download offer after signing in. */
function offline_levels(array $u):never{
 $pid=(int)$u['id'];$g=pupil_grade($pid);header('Content-Type: application/json');header('Cache-Control: no-store');
 $next=next_learning_lesson($pid);$cur=(int)($next['level_id']??0);$out=[];$root=__DIR__.'/../public/';
 foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY id') as $lv){$id=(int)$lv['id'];if(!level_downloadable($pid,$id))continue;
  $acts=rows('SELECT a.*,l.position lesson_position FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1 AND a.published=1'.GRADE_SQL,[$id,$g]);if(!$acts)continue;
  $files=[];foreach($acts as $a)foreach(activity_images($a) as $src){$p=resolved_image_path((string)$src);if($p)$files[strtok($p,'?')]=1;}
  $bytes=0;foreach(array_keys($files) as $f)if(is_file($root.$f))$bytes+=filesize($root.$f);
  $lessons=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$id,$g]);
  $out[]=['level'=>$id,'title'=>level_label($id),'lessons'=>$lessons,'activities'=>count($acts),'bytes'=>$bytes+count($acts)*45000+$lessons*40000+600000,'current'=>$id===$cur,'locked'=>!level_available($pid,$id),'cover'=>level_cover($id)];}
 echo json_encode(['uid'=>$pid,'first'=>explode(' ',trim((string)$u['name']))[0],'levels'=>$out]);exit;
}
