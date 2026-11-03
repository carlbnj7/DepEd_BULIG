-- BULIG chat between a teacher and their pupils (November 2026). Safe to import more than once.
-- One private chat for each teacher and pupil. A pupil can chat only with their own teachers; the administrator can read the chats.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS chats (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 teacher_id BIGINT UNSIGNED NOT NULL,
 pupil_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY chats_pair(teacher_id,pupil_id),
 KEY chats_pupil(pupil_id),
 KEY chats_last(last_at),
 CONSTRAINT chats_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT chats_pupil_fk FOREIGN KEY(pupil_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 chat_id BIGINT UNSIGNED NOT NULL,
 author_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 media_kind VARCHAR(8) NULL,
 media_path VARCHAR(255) NULL,
 media_secs SMALLINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 read_at DATETIME NULL,
 KEY chat_messages_chat(chat_id,id),
 KEY chat_messages_author(author_id,created_at),
 CONSTRAINT chat_messages_chat_fk FOREIGN KEY(chat_id) REFERENCES chats(id) ON DELETE CASCADE,
 CONSTRAINT chat_messages_author_fk FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations(version) VALUES('039_teacher_chat');
