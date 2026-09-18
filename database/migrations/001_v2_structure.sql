-- Select YOUR existing BULIG database in phpMyAdmin first. No USE / DROP / DELETE.
-- Run once before 002_content.sql and 003_existing_progress.sql.
CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(80) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS sections(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, teacher_id BIGINT UNSIGNED NOT NULL, grade_level TINYINT UNSIGNED NOT NULL, name VARCHAR(80) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(teacher_id,grade_level,name), FOREIGN KEY(teacher_id) REFERENCES teachers(user_id), FOREIGN KEY(grade_level) REFERENCES grade_levels(id));
CREATE TABLE IF NOT EXISTS pupil_sections(pupil_id BIGINT UNSIGNED PRIMARY KEY, section_id BIGINT UNSIGNED NOT NULL, INDEX(section_id), FOREIGN KEY(pupil_id) REFERENCES pupils(user_id), FOREIGN KEY(section_id) REFERENCES sections(id));
ALTER TABLE activities ADD COLUMN response_mode ENUM('answer','none','perform','drawing') NOT NULL DEFAULT 'answer';
ALTER TABLE activity_completion MODIFY status ENUM('submitted','approved','retry','completed') NOT NULL DEFAULT 'submitted', ADD outcome ENUM('correct','recorded','viewed','performed','legacy') NULL;
ALTER TABLE activity_drafts ADD transcript TEXT NULL;
INSERT IGNORE INTO sections(teacher_id,grade_level,name) SELECT t.teacher_id,p.grade_level,p.section FROM pupils p JOIN teacher_pupils t ON t.pupil_id=p.user_id WHERE p.section<>'';
INSERT IGNORE INTO pupil_sections(pupil_id,section_id) SELECT p.user_id,s.id FROM pupils p JOIN teacher_pupils t ON t.pupil_id=p.user_id JOIN sections s ON s.teacher_id=t.teacher_id AND s.grade_level=p.grade_level AND s.name=p.section;
INSERT IGNORE INTO schema_migrations(version) VALUES('001_v2_structure');
