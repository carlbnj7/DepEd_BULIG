-- BULIG streak flames: the weekly streak revive (app/streak.php). Run once in phpMyAdmin.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS streak_revives (
 pupil_id BIGINT UNSIGNED NOT NULL,
 day DATE NOT NULL,
 used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (pupil_id,day),
 KEY streak_revives_used (pupil_id,used_at),
 CONSTRAINT streak_revives_pupil_fk FOREIGN KEY (pupil_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations(version) VALUES('040_streak_revives');
