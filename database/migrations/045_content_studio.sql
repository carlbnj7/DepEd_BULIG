-- v215 Content Studio: the Content Developer role, a hidden test pupil, and draft lessons with review.
-- Back up the database first. This file only ADDS: nothing existing is changed or deleted.
--  - users.role gets one more value, 'tester' (the Content Developer's own test pupil). Existing accounts keep their role.
--    A tester is never counted as a pupil: every list, report and leaderboard looks for role='pupil'.
--  - studio_testers: which test pupil belongs to which Content Developer.
--  - studio_lessons: lessons made in the Content Studio (draft, waiting for review, published) and the review notes.
--  - studio_revisions: a saved copy of a lesson each time it is sent for review or published (for Restore).
-- The Content Developer role itself is a row in user_roles (role 'content_dev'), made by the Division Admin.

ALTER TABLE users MODIFY role ENUM('admin','teacher','pupil','tester') NOT NULL;

CREATE TABLE IF NOT EXISTS studio_testers (
 dev_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 pupil_id BIGINT UNSIGNED NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT studio_testers_dev_fk FOREIGN KEY(dev_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_lessons (
 lesson_id INT UNSIGNED NOT NULL PRIMARY KEY,
 status ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NULL,
 note TEXT NULL,
 sent_at DATETIME NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY studio_lessons_status(status),
 CONSTRAINT studio_lessons_lesson_fk FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
 CONSTRAINT studio_lessons_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lesson_id INT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 what VARCHAR(120) NOT NULL DEFAULT '',
 snapshot LONGTEXT NOT NULL,
 made_by BIGINT UNSIGNED NULL,
 made_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY studio_revisions_one(lesson_id,revision),
 CONSTRAINT studio_revisions_lesson_fk FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
 CONSTRAINT studio_revisions_by_fk FOREIGN KEY(made_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
