<?php
/* Test accounts for tests/run_tests.py. Runs from the command line against the TEST database only
   (BULIG_DB_NAME must be set and must not be the live database name). Prints the accounts as JSON. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$name=(string)getenv('BULIG_DB_NAME');
if($name===''||$name==='DEPED_BULIG'){fwrite(STDERR,"Set BULIG_DB_NAME to the test database first.\n");exit(1);}
require (getenv('BULIG_APP_ROOT')?:__DIR__.'/..').'/app/bootstrap.php';

const T_ADMIN_PW='AdminTest42!';const T_ADMIN_PIN='2468';const T_TEACHER_PW='TeacherTest42!';const T_PUPIL_PW='PupilTest42!';

function t_user(string $public,string $role,string $name,string $pw):int{
 q('INSERT INTO users(public_id,role,name,password_hash) VALUES(?,?,?,?)',[$public,$role,$name,password_hash($pw,PASSWORD_DEFAULT)]);return (int)db()->lastInsertId();
}
db()->beginTransaction();
$admin=t_user('testadmin','admin','Test Admin',T_ADMIN_PW);q('INSERT INTO admins VALUES(?)',[$admin]);save_admin_pin($admin,T_ADMIN_PIN);
$teacher=t_user('T2099001','teacher','Test Teacher',T_TEACHER_PW);q('INSERT INTO teachers VALUES(?)',[$teacher]);
$t=one('SELECT * FROM users WHERE id=?',[$teacher]);
$sections=[];
foreach([1=>'Rizal',3=>'Mabini'] as $grade=>$sname){q('INSERT INTO sections(teacher_id,grade_level,name) VALUES(?,?,?)',[$teacher,$grade,$sname]);$sections[$grade]=one('SELECT * FROM sections WHERE id=?',[(int)db()->lastInsertId()]);}
/* A Grade 1 pupil who starts at Level 1, and a Grade 3 pupil the teacher started at Level 6 (bulig_levels id 7). */
$p1=create_pupil_account($t,'Test Pupil One',$sections[1],1,[]);
$p6=create_pupil_account($t,'Test Pupil Six',$sections[3],7,[]);
foreach([$p1,$p6] as $p)q('UPDATE users SET password_hash=? WHERE public_id=?',[password_hash(T_PUPIL_PW,PASSWORD_DEFAULT),$p]);
db()->commit();
echo json_encode(['admin'=>['id'=>'testadmin','pw'=>T_ADMIN_PW,'pin'=>T_ADMIN_PIN],'teacher'=>['id'=>'T2099001','pw'=>T_TEACHER_PW],
 'pupil1'=>['id'=>$p1,'pw'=>T_PUPIL_PW,'uid'=>(int)val('SELECT id FROM users WHERE public_id=?',[$p1])],
 'pupil6'=>['id'=>$p6,'pw'=>T_PUPIL_PW,'uid'=>(int)val('SELECT id FROM users WHERE public_id=?',[$p6])]]),"\n";
