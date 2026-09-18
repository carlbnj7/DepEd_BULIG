<?php
// Non-destructive migration for the original BULIG package. Run once from a terminal.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
try{
 db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(80) PRIMARY KEY,applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
 foreach(['001_v2_structure','002_content','003_existing_progress','004_visuals','005_level7'] as $version){
  if(val('SELECT 1 FROM schema_migrations WHERE version=?',[$version])){echo "$version already applied.\n";continue;}
  $sql=file_get_contents(__DIR__.'/../database/migrations/'.$version.'.sql');
  if($version==='001_v2_structure'){
   if(val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='activities' AND column_name='response_mode'"))$sql=preg_replace('/ALTER TABLE activities[^;]+;/','',$sql);
   if(val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='activity_completion' AND column_name='outcome'"))$sql=preg_replace('/ALTER TABLE activity_completion[^;]+;/','',$sql);
   if(val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='activity_drafts' AND column_name='transcript'"))$sql=preg_replace('/ALTER TABLE activity_drafts[^;]+;/','',$sql);
  }
  db()->exec($sql);q('INSERT IGNORE INTO schema_migrations(version) VALUES(?)',[$version]);echo "$version applied.\n";
 }
 foreach(rows('SELECT DISTINCT pupil_id FROM activity_completion') as $p){$pid=(int)$p['pupil_id'];foreach(rows("SELECT activity_id FROM activity_completion WHERE pupil_id=? AND status IN ('completed','approved')",[$pid]) as $c)update_learning($pid,(int)$c['activity_id']);}
 echo "Upgrade complete. Accounts, original responses, and existing XP were preserved.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,"Upgrade stopped: ".$e->getMessage()."\nDo not reset the database. Resolve the error and rerun this migration.\n");exit(1);}
