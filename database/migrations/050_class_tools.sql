-- v216 Class tools: the teacher's week plan, reading groups and private notes on pupils.
-- Import AFTER 049_fluency_log.sql. Safe to import again.
CREATE TABLE IF NOT EXISTS week_plans (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 teacher_id BIGINT UNSIGNED NOT NULL,
 section_id BIGINT UNSIGNED NOT NULL,
 week_start DATE NOT NULL,
 day TINYINT UNSIGNED NOT NULL,
 lesson_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY week_plans_day (section_id,week_start,day),
 KEY week_plans_teacher (teacher_id,week_start),
 CONSTRAINT week_plans_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pupil_groups (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 teacher_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(80) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY pupil_groups_teacher (teacher_id),
 CONSTRAINT pupil_groups_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pupil_group_members (
 group_id BIGINT UNSIGNED NOT NULL,
 pupil_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(group_id,pupil_id),
 KEY pupil_group_members_pupil (pupil_id),
 CONSTRAINT pupil_group_members_group_fk FOREIGN KEY(group_id) REFERENCES pupil_groups(id) ON DELETE CASCADE,
 CONSTRAINT pupil_group_members_pupil_fk FOREIGN KEY(pupil_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pupil_notes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 teacher_id BIGINT UNSIGNED NOT NULL,
 pupil_id BIGINT UNSIGNED NOT NULL,
 note VARCHAR(200) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY pupil_notes_pupil (teacher_id,pupil_id),
 CONSTRAINT pupil_notes_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT pupil_notes_pupil_fk FOREIGN KEY(pupil_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
