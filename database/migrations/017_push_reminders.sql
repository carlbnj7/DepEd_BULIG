-- BULIG phone reminders (Web Push): which pupil phones said yes, their choices, and notes waiting to be sent.
-- Safe to import again (MariaDB).
CREATE TABLE IF NOT EXISTS push_subscriptions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 endpoint_hash CHAR(64) NOT NULL,
 endpoint VARCHAR(500) NOT NULL,
 p256dh VARCHAR(120) NOT NULL,
 auth VARCHAR(60) NOT NULL,
 prefs VARCHAR(255) NULL,
 last_sent_on DATE NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY push_subscriptions_endpoint(endpoint_hash),
 KEY push_subscriptions_user(user_id),
 CONSTRAINT push_subscriptions_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS push_queue(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(20) NOT NULL,
 title VARCHAR(120) NOT NULL,
 body VARCHAR(200) NOT NULL,
 url VARCHAR(200) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 KEY push_queue_user(user_id,sent_at),
 CONSTRAINT push_queue_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT IGNORE INTO schema_migrations(version) VALUES('017_push_reminders');
