-- BULIG sign-in tickets: each pupil's one-scan sign-in key (for the QR code) and starter password.
-- The key and the starter password are kept encrypted; the starter password is removed when the pupil makes their own.
-- Safe to import again (MariaDB).
CREATE TABLE IF NOT EXISTS pupil_cards(
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 key_hash CHAR(64) NOT NULL,
 key_enc VARCHAR(255) NOT NULL,
 starter_enc VARCHAR(255) NULL,
 issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY pupil_cards_key(key_hash),
 CONSTRAINT pupil_cards_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT IGNORE INTO schema_migrations(version) VALUES('018_pupil_cards');
