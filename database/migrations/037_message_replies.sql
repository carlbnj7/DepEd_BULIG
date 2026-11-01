-- BULIG message replies (November 2026). Safe to import more than once. Needs 033_admin_messages.sql first.
-- Each person who got a message has a private chat with the administrator under that message.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS message_replies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 message_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 author_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 read_at DATETIME NULL,
 KEY message_replies_thread(message_id,user_id,id),
 KEY message_replies_author(author_id),
 CONSTRAINT message_replies_recipient_fk FOREIGN KEY(message_id,user_id) REFERENCES message_recipients(message_id,user_id) ON DELETE CASCADE,
 CONSTRAINT message_replies_author_fk FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations(version) VALUES('037_message_replies');
