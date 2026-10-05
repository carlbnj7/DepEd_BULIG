-- BULIG saved sign-ins: pupils and teachers can keep up to 3 accounts on a device and sign in with one tap.
-- Only a hash of each device key is stored. Safe to import again (MariaDB).
CREATE TABLE IF NOT EXISTS saved_logins(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 last_used_at DATETIME NULL,
 expires_at DATETIME NOT NULL,
 UNIQUE KEY saved_logins_token(token_hash),
 KEY saved_logins_user(user_id),
 CONSTRAINT saved_logins_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT IGNORE INTO schema_migrations(version) VALUES('016_saved_logins');
