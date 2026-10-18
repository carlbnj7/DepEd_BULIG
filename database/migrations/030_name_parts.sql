-- BULIG: names in parts. The "Add one pupil", "Add one teacher" and class-list forms ask for First name, Middle name,
-- Last name and an Extension (Jr., Sr., II, III, IV, V). users.name keeps the name BULIG shows ("Juan S. Dela Cruz Jr.");
-- this table keeps the parts. Accounts made before this file have no row here and keep working.
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS user_names(
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 first_name VARCHAR(60) NOT NULL,
 middle_name VARCHAR(60) NULL,
 last_name VARCHAR(60) NOT NULL,
 name_ext VARCHAR(4) NULL,
 KEY user_names_last(last_name,first_name),
 CONSTRAINT user_names_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
INSERT IGNORE INTO schema_migrations(version) VALUES('030_name_parts');
