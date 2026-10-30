CREATE TABLE IF NOT EXISTS reading_verifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 completion_id BIGINT UNSIGNED NOT NULL,
 pupil_id BIGINT UNSIGNED NOT NULL,
 activity_id INT UNSIGNED NOT NULL,
 state ENUM('pending','missing','verified','rejected','superseded') NOT NULL,
 audio_paths MEDIUMTEXT NULL,
 prompt_snapshot MEDIUMTEXT NULL,
 response_snapshot MEDIUMTEXT NULL,
 score DECIMAL(10,2) NULL,
 max_score DECIMAL(10,2) NULL,
 reason VARCHAR(3000) NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 xp_reversed INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY rv_teacher_queue (pupil_id,state,id),
 KEY rv_completion (completion_id,id),
 FOREIGN KEY (completion_id) REFERENCES activity_completion(id) ON DELETE CASCADE,
 FOREIGN KEY (pupil_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations(version) VALUES('035_reading_verification');
