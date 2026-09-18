<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/actions.php';require __DIR__.'/../app/views.php';
ob_start();
try{
 if($_SERVER['REQUEST_METHOD']==='POST')action();
 $page=(string)($_GET['page']??'dashboard');$u=current_user();
 if(!$u){if($page!=='login')go('?page=login');login_view();}
 elseif($page==='login'){go('?page=dashboard');}
 elseif($page==='source'){
  $n=(int)($_GET['n']??1);$p=one('SELECT * FROM source_pages WHERE page_number=?',[$n]);if(!$p)fail('Page not found.',404);head('Official module · page '.$n,'source-page');echo '<main><a class="btn secondary" href="?page=dashboard">Back to dashboard</a><h1>Official Level 1 module · PDF page '.$n.'</h1><img src="'.e($p['image_path']).'" alt="Original module page '.$n.'"><details><summary>Extracted text</summary><pre class="source-text">'.e($p['text_content']).'</pre></details></main>';foot();
 }
 elseif($page==='download_source'){require_role('admin','teacher');header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="BULIG-Level-1-Original.pdf"');readfile(__DIR__.'/../storage/level1-original.pdf');}
 elseif($page==='lesson'){require_role('pupil');lesson_view($u);}
 else{
  $permissions=['visual_audit'=>['admin'],'sections'=>['teacher'],'accounts'=>['admin','teacher'],'review'=>['teacher'],'pupil'=>['teacher'],'guide'=>['teacher','admin'],'content'=>['admin'],'edit_activity'=>['admin'],'media'=>['admin'],'issues'=>['admin'],'settings'=>['admin'],'achievements'=>['pupil'],'profile'=>['admin','teacher','pupil'],'dashboard'=>['admin','teacher','pupil']];if(!isset($permissions[$page]))fail('Page not found.',404);require_role(...$permissions[$page]);shell($u,$page);
  switch($page){case 'visual_audit':visual_audit_view();break;case 'dashboard':if($u['role']==='pupil')pupil_dashboard($u);elseif($u['role']==='teacher')teacher_dashboard($u);else admin_dashboard();break;case 'sections':sections_view($u);break;case 'accounts':accounts_view($u);break;case 'review':review_view($u);break;case 'pupil':pupil_detail();break;case 'guide':guide_view();break;case 'content':content_view();break;case 'edit_activity':edit_activity_view();break;case 'media':media_view();break;case 'issues':issues_view();break;case 'settings':settings_view();break;case 'achievements':achievements_view($u);break;case 'profile':profile_view($u);break;}
  shell_end();
 }
 ob_end_flush();
}catch(Throwable $ex){
 if(db_in_transaction_safe())db()->rollBack();ob_end_clean();$isPublic=$ex instanceof RuntimeException&&!($ex instanceof PDOException);$code=$isPublic&&in_array($ex->getCode(),[400,401,403,404,429],true)?$ex->getCode():($isPublic?400:500);http_response_code($code);error_log((string)$ex);
 $message=$isPublic?$ex->getMessage():'The app could not load its database. Please check the setup instructions and database configuration.';
 if(($_POST['action']??'')==='draft'||str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['saved'=>false,'error'=>$message]);exit;}
 head('Let’s try again','error-page');echo '<main class="card"><h1>'.($code===500?'A little setup is needed.':'Let’s check that.').'</h1><p>'.e($message).'</p><a class="btn primary" href="?page=dashboard">Return to BULIG '.icon('arrow').'</a><p class="muted">If you were completing an activity, return to its lesson to recover your saved draft.</p></main>';foot();
}
function db_in_transaction_safe():bool{try{return db()->inTransaction();}catch(Throwable $e){return false;}}
