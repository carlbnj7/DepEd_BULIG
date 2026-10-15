-- BULIG: app ratings. Pupils rate BULIG with stars; teachers answer a 5-question star evaluation (and may add a comment).
-- The administrator reads the results in Reports › App ratings. "Not now" is kept in settings (rate_later_<user id>).
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS app_ratings(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 role ENUM('pupil','teacher') NOT NULL,
 stars TINYINT UNSIGNED NOT NULL,
 overall DECIMAL(3,1) NOT NULL,
 answers VARCHAR(40) NULL,
 comment TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY app_ratings_role(role,created_at),
 KEY app_ratings_user(user_id,created_at),
 CONSTRAINT app_ratings_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
INSERT IGNORE INTO schema_migrations(version) VALUES('029_app_ratings');
