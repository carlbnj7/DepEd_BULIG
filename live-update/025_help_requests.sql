-- BULIG: "I need help" requests from pupils on lesson screens (the teacher also gets a notification).
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS help_requests(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pupil_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NULL, activity_id BIGINT UNSIGNED NULL,
 reason VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY help_requests_pupil(pupil_id,created_at),
 CONSTRAINT help_requests_pupil_fk FOREIGN KEY(pupil_id) REFERENCES users(id) ON DELETE CASCADE);
INSERT IGNORE INTO schema_migrations(version) VALUES('025_help_requests');
