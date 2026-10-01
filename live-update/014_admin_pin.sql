-- BULIG: admin PIN (second sign-in step for administrators). Safe to import again.
CREATE TABLE IF NOT EXISTS admin_security (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 pin_hash VARCHAR(255) NULL,
 failed_pins TINYINT UNSIGNED NOT NULL DEFAULT 0,
 locked_until DATETIME NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id)
);
INSERT IGNORE INTO schema_migrations(version) VALUES('014_admin_pin');
