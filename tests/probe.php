<?php
/* Reads values from the TEST database for tests/run_tests.py: php tests/probe.php <what> <pupil id>. Prints JSON. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$name=(string)getenv('BULIG_DB_NAME');
if($name===''||$name==='DEPED_BULIG'){fwrite(STDERR,"Set BULIG_DB_NAME to the test database first.\n");exit(1);}
require (getenv('BULIG_APP_ROOT')?:__DIR__.'/..').'/app/bootstrap.php';
[$what,$pid]=[$argv[1]??'',(int)($argv[2]??0)];
$out=match($what){
 'reader'=>rl_info($pid,true)+['headstart_all'=>array_sum(rl_headstart($pid,true)),'headstart_now'=>rl_headstart_now($pid,true)],
 'xp'=>['xp'=>(int)val('SELECT COALESCE(total,0) FROM pupil_xp WHERE pupil_id=?',[$pid]),'rows'=>(int)val('SELECT COUNT(*) FROM xp_transactions WHERE pupil_id=?',[$pid])],
 /* The first activities of the first lesson of a level (and grade): php tests/probe.php first_activities <level id> <grade> */
 'first_activities'=>rows("SELECT a.id,a.lesson_id,a.xp_reward,a.response_mode FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND (m.grade_level IS NULL OR m.grade_level=?) AND a.published=1 AND l.published=1 AND l.id=(SELECT l2.id FROM lessons l2 JOIN modules m2 ON m2.id=l2.module_id WHERE m2.level_id=? AND (m2.grade_level IS NULL OR m2.grade_level=?) AND l2.published=1 ORDER BY l2.position,l2.id LIMIT 1) ORDER BY FIELD(a.phase,'pre','learn','post'),a.position LIMIT 3",[$pid,(int)($argv[3]??1),$pid,(int)($argv[3]??1)]),
 'migrations'=>array_column(rows('SELECT version FROM schema_migrations ORDER BY version'),'version'),
 'tables'=>array_column(rows('SHOW TABLES'),array_key_first(rows('SHOW TABLES')[0]??['x'=>0])),
 default=>['error'=>'unknown probe']
};
echo json_encode($out),"\n";
