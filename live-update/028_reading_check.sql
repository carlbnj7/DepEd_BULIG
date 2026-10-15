-- BULIG: the reading check at first sign-in and the starting level it gives (Grades 1-6).
-- One row per pupil: the parts taken, the answers, the level BULIG chose, the teacher's changes and the official
-- CRLA / Phil-IRI result the teacher typed. Recordings stay in storage/recordings like other recordings.
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS reading_checks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pupil_id BIGINT UNSIGNED NOT NULL,
 grade TINYINT UNSIGNED NOT NULL,
 status ENUM('running','done') NOT NULL DEFAULT 'running',
 state LONGTEXT NULL,
 bulig_level INT UNSIGNED NULL,
 placed_level INT UNSIGNED NULL,
 changed_by BIGINT UNSIGNED NULL,
 teacher_note TEXT NULL,
 talk_rating VARCHAR(20) NULL,
 official_tool VARCHAR(10) NULL,
 official_result VARCHAR(30) NULL,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY reading_checks_pupil(pupil_id),
 KEY reading_checks_status(status),
 CONSTRAINT reading_checks_pupil_fk FOREIGN KEY(pupil_id) REFERENCES users(id) ON DELETE CASCADE);
INSERT IGNORE INTO schema_migrations(version) VALUES('028_reading_check');
