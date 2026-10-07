-- BULIG notifications: the bell on every dashboard. Each row is one notification for one account.
-- Safe to import again (MariaDB).
CREATE TABLE IF NOT EXISTS notifications(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(30) NOT NULL,
 title VARCHAR(200) NOT NULL,
 body VARCHAR(300) NOT NULL DEFAULT '',
 url VARCHAR(250) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 read_at DATETIME NULL,
 KEY notifications_user(user_id,read_at),
 KEY notifications_time(created_at),
 CONSTRAINT notifications_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT IGNORE INTO schema_migrations(version) VALUES('024_notifications');
