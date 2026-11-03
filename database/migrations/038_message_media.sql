-- BULIG photos and voice messages in Messages (November 2026). Safe to import more than once. Needs 037_message_replies.sql first.
-- A reply can carry one photo or one voice message. The file is kept privately in storage/messages and deleted after 60 days.
SET NAMES utf8mb4;
ALTER TABLE message_replies
 ADD COLUMN IF NOT EXISTS media_kind VARCHAR(8) NULL AFTER body,
 ADD COLUMN IF NOT EXISTS media_path VARCHAR(255) NULL AFTER media_kind,
 ADD COLUMN IF NOT EXISTS media_secs SMALLINT UNSIGNED NULL AFTER media_path;
INSERT IGNORE INTO schema_migrations(version) VALUES('038_message_media');
