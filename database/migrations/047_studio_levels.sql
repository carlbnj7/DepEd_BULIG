-- v216 Content Studio: new levels made by the Content Developer (after Level 7 on the road map), and module upload.
-- Back up the database first. Import AFTER 045_content_studio.sql. This file only ADDS tables; nothing existing changes.
--  - studio_levels: each new level (its name, cover picture, and draft / waiting for review / published).
--    The level itself is a row in bulig_levels, hidden from pupils and teachers until the Division Admin approves it.
--  - studio_pages: the pages of an uploaded module (the text read from the PDF and a picture of each page),
--    used to make the draft lessons and activities.

CREATE TABLE IF NOT EXISTS studio_levels (
 level_id INT UNSIGNED NOT NULL PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 short_name VARCHAR(60) NOT NULL,
 cover_path VARCHAR(255) NULL,
 status ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NULL,
 note TEXT NULL,
 sent_at DATETIME NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT studio_levels_level_fk FOREIGN KEY(level_id) REFERENCES bulig_levels(id) ON DELETE CASCADE,
 CONSTRAINT studio_levels_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_pages (
 level_id INT UNSIGNED NOT NULL,
 page_no INT UNSIGNED NOT NULL,
 text_content MEDIUMTEXT NOT NULL,
 image_path VARCHAR(255) NULL,
 PRIMARY KEY(level_id,page_no),
 CONSTRAINT studio_pages_level_fk FOREIGN KEY(level_id) REFERENCES bulig_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
