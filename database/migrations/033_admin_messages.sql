-- Admin-only messages; safe to import again.
CREATE TABLE IF NOT EXISTS admin_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sender_id BIGINT UNSIGNED NULL,
 send_token CHAR(48) NOT NULL UNIQUE,
 subject VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY messages_sender(sender_id,id),
 CONSTRAINT messages_sender_fk FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS message_recipients (
 message_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 read_at DATETIME NULL,
 PRIMARY KEY(message_id,user_id),
 KEY messages_inbox(user_id,message_id),
 CONSTRAINT message_recipient_message_fk FOREIGN KEY(message_id) REFERENCES admin_messages(id) ON DELETE CASCADE,
 CONSTRAINT message_recipient_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT IGNORE INTO schema_migrations(version) VALUES('033_admin_messages');
