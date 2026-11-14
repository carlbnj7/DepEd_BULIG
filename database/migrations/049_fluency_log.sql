-- v216 Reading growth: keep every Level 4 Fluency reading (words per minute, accuracy, reading level), so the first and
-- the latest reading can be compared. Import AFTER 048_activity_time.sql. Safe to import again.
CREATE TABLE IF NOT EXISTS fluency_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 pupil_id BIGINT UNSIGNED NOT NULL,
 activity_id INT UNSIGNED NOT NULL,
 wpm SMALLINT UNSIGNED NOT NULL,
 accuracy DECIMAL(5,1) NOT NULL,
 reading_level VARCHAR(20) NOT NULL DEFAULT '',
 secs INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY fluency_log_pupil (pupil_id,id),
 CONSTRAINT fluency_log_pupil_fk FOREIGN KEY(pupil_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
