<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/actions.php';require __DIR__.'/../app/views.php';require __DIR__.'/../app/teacher_demo.php';
ob_start();
try{
 if($_SERVER['REQUEST_METHOD']==='POST')action();
 $page=(string)($_GET['page']??'dashboard');$u=current_user();
 if(!$u){if($page!=='login')go('?page=login');login_view();}
 elseif($page==='login'){go('?page=dashboard');}
 elseif($page==='source'){
  $level=(int)($_GET['level']??1);
  if(in_array($level,[2,3],true)){
   $n=(int)($_GET['n']??1);$meta=level2_manifest()['levels'][(string)$level];$key=$level===2?'2a':'2b';
   if($n<1||$n>$meta['page_count'])fail('Page not found.',404);
   if($u['role']==='pupil'){
    if(!in_array($n,$meta['pupil_pages'],true)||!level_available((int)$u['id'],$level))fail('This source page is reserved for your teacher or is not available yet.',403);
   }
   if(isset($_GET['image'])){header('Content-Type: image/webp');readfile(__DIR__.'/../storage/'.$key.'/page-'.sprintf('%03d',$n).'.webp');exit;}
   head('Official '.level_label($level).' · page '.$n,'source-page');echo '<main><a class="btn secondary" href="?page=dashboard">Back to dashboard</a><h1>Official '.e(level_label($level)).' · PDF page '.$n.'</h1><img src="?page=source&amp;level='.$level.'&amp;n='.$n.'&amp;image=1" alt="Original module page '.$n.'"></main>';foot();
  }else{
  $n=(int)($_GET['n']??1);$p=one('SELECT * FROM source_pages WHERE page_number=?',[$n]);if(!$p)fail('Page not found.',404);head('Official module · page '.$n,'source-page');echo '<main><a class="btn secondary" href="?page=dashboard">Back to dashboard</a><h1>Official Level 1 module · PDF page '.$n.'</h1><img src="'.e($p['image_path']).'" alt="Original module page '.$n.'"><details><summary>Extracted text</summary><pre class="source-text">'.e($p['text_content']).'</pre></details></main>';foot();
  }
 }
 elseif($page==='download_source'){require_role('admin','teacher');$level=(int)($_GET['level']??1);$key=[1=>'1',2=>'2a',3=>'2b'][$level]??null;if(!$key)fail('Module not found.',404);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="BULIG-Level-'.$key.'-Original.pdf"');readfile(__DIR__.'/../storage/level'.$key.'-original.pdf');}
 elseif($page==='class_demo'){require_role('teacher');class_demo_view($u);}
 elseif($page==='lesson'){require_role('pupil');lesson_view($u);}
 else{
  $permissions=['visual_audit'=>['admin'],'sections'=>['teacher'],'accounts'=>['admin','teacher'],'review'=>['teacher'],'pupil'=>['teacher'],'guide'=>['teacher','admin'],'content'=>['admin'],'edit_activity'=>['admin'],'media'=>['admin'],'issues'=>['admin'],'settings'=>['admin'],'achievements'=>['pupil'],'profile'=>['admin','teacher','pupil'],'dashboard'=>['admin','teacher','pupil']];if(!isset($permissions[$page]))fail('Page not found.',404);require_role(...$permissions[$page]);shell($u,$page);
  switch($page){case 'visual_audit':visual_audit_view();break;case 'dashboard':if($u['role']==='pupil')pupil_dashboard($u);elseif($u['role']==='teacher')teacher_dashboard($u);else admin_dashboard();break;case 'sections':sections_view($u);break;case 'accounts':accounts_view($u);break;case 'review':review_view($u);break;case 'pupil':pupil_detail();break;case 'guide':guide_view();break;case 'content':content_view();break;case 'edit_activity':edit_activity_view();break;case 'media':media_view();break;case 'issues':issues_view();break;case 'settings':settings_view();break;case 'achievements':achievements_view($u);break;case 'profile':profile_view($u);break;}
  shell_end();
 }
 ob_end_flush();
}catch(Throwable $ex){
 if(db_in_transaction_safe())db()->rollBack();ob_end_clean();$isPublic=$ex instanceof RuntimeException&&!($ex instanceof PDOException);$code=$isPublic&&in_array($ex->getCode(),[400,401,403,404,429],true)?$ex->getCode():($isPublic?400:500);http_response_code($code);error_log((string)$ex);
 $message=$isPublic?$ex->getMessage():bulig_setup_diagnostic($ex);
 if(($_POST['action']??'')==='draft'||str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['saved'=>false,'error'=>$message]);exit;}
 head('Let’s try again','error-page');echo '<main class="card"><h1>'.($code===500?'A little setup is needed.':'Let’s check that.').'</h1><p>'.e($message).'</p><a class="btn primary" href="?page=dashboard">Return to BULIG '.icon('arrow').'</a><p class="muted">If you were completing an activity, return to its lesson to recover your saved draft.</p></main>';foot();
}
function db_in_transaction_safe():bool{try{return db()->inTransaction();}catch(Throwable $e){return false;}}

// Only safe categories and code locations are shown; no SQL, credentials, or records.
function bulig_setup_diagnostic(Throwable $error):string{
 $location=basename($error->getFile()).':'.$error->getLine();
 if($error instanceof PDOException){
  $number=(int)($error->errorInfo[1]??0);
  $state=(string)($error->errorInfo[0]??$error->getCode());
  if(!preg_match('/^[A-Z0-9]{1,8}$/D',$state))$state='unknown';
  $reasons=[1045=>'Database login was rejected. Check the database password and user permissions.',1044=>'The database user does not have access to this database.',1049=>'The configured database does not exist.',1146=>'A required database table is missing. The database and application versions do not match.',1054=>'A required database column is missing. The database and application versions do not match.',2002=>'PHP could not reach the database server. Check the database host and connection settings.',2006=>'The database connection was closed.',1064=>'A database query has a syntax error.'];
  $reason=$reasons[$number]??'A database operation failed.';
  if(str_contains(strtolower($error->getMessage()),'could not find driver'))$reason='PDO MySQL is not enabled for this website PHP configuration.';
  return 'BULIG diagnostic: SQLSTATE '.$state.' / MySQL '.$number.'. '.$reason.' Location: '.$location;
 }
 $reason='A PHP application error occurred.';
 if(preg_match('/Call to undefined function ([a-zA-Z0-9_]+)\(/',$error->getMessage(),$m))$reason='A required PHP function is unavailable: '.$m[1].'.';
 return 'BULIG diagnostic: '.get_class($error).'. '.$reason.' Location: '.$location;
}
