-- Release old approval queues without erasing their original decision history.
START TRANSACTION;
SET @bulig_v2_backfill = NOT EXISTS(SELECT 1 FROM schema_migrations WHERE version='003_existing_progress');
INSERT INTO response_history(completion_id,response,status,actor_id) SELECT c.id,'Migrated: teacher approval no longer blocks learning.',c.status,c.pupil_id FROM activity_completion c WHERE @bulig_v2_backfill AND c.status IN('submitted','retry');
UPDATE activity_completion SET status='completed',outcome='legacy' WHERE @bulig_v2_backfill AND status IN('submitted','retry');
UPDATE activity_completion SET outcome='legacy' WHERE @bulig_v2_backfill AND outcome IS NULL;
-- Only insert missing XP transactions. Existing values are never changed.
INSERT IGNORE INTO xp_transactions(pupil_id,activity_id,amount,created_at) SELECT c.pupil_id,c.activity_id,a.xp_reward,c.submitted_at FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE @bulig_v2_backfill AND c.status IN('approved','completed');
INSERT INTO pupil_xp(pupil_id,total) SELECT pupil_id,SUM(amount) FROM xp_transactions GROUP BY pupil_id ON DUPLICATE KEY UPDATE total=GREATEST(total,VALUES(total));
INSERT IGNORE INTO learning_days(pupil_id,day) SELECT c.pupil_id,DATE(c.submitted_at) FROM activity_completion c JOIN activities a ON a.id=c.activity_id WHERE @bulig_v2_backfill AND c.status IN('approved','completed') AND a.response_mode<>'none';
INSERT INTO schema_migrations(version) VALUES('003_existing_progress') ON DUPLICATE KEY UPDATE version=VALUES(version);
COMMIT;
-- Recompute streaks from preserved learning-day rows. No login-only days are added.
INSERT INTO pupil_streaks(pupil_id,current_streak,longest_streak,last_activity_date)
SELECT pupil_id,MAX(CASE WHEN last_day>=DATE_SUB(CURDATE(),INTERVAL 1 DAY) THEN run_days ELSE 0 END),MAX(run_days),MAX(last_day)
FROM (SELECT pupil_id,day_group,COUNT(*) run_days,MAX(day) last_day FROM
(SELECT pupil_id,day,DATEDIFF(day,'2000-01-01')-ROW_NUMBER() OVER(PARTITION BY pupil_id ORDER BY day) day_group FROM learning_days) grouped_days
GROUP BY pupil_id,day_group) runs GROUP BY pupil_id
ON DUPLICATE KEY UPDATE current_streak=VALUES(current_streak),longest_streak=GREATEST(longest_streak,VALUES(longest_streak)),last_activity_date=VALUES(last_activity_date);
