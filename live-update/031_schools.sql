-- BULIG: more than one school in one BULIG (for example PCES and MFCES).
-- Each school has its own code, name, School ID, district, head and logo. Every teacher and pupil belongs to one
-- school (users.school_id): new pupils get the code of their teacher's school (PCES260001 / MFCES260001), new
-- teachers the code of the school chosen for them (PCEST0001 / MFCEST0001). Administrators see every school.
-- The school that BULIG already has (Settings > School info and the school code) becomes the first school, and every
-- teacher and pupil already in BULIG is placed in it. Nothing else changes.
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS schools(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(10) NOT NULL,
 name VARCHAR(150) NOT NULL DEFAULT '',
 school_no VARCHAR(40) NOT NULL DEFAULT '',
 district VARCHAR(150) NOT NULL DEFAULT '',
 head VARCHAR(150) NOT NULL DEFAULT '',
 logo VARCHAR(255) NOT NULL DEFAULT '',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY schools_code(code));
ALTER TABLE users ADD COLUMN IF NOT EXISTS school_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS users_school(school_id);
INSERT INTO schools(code,name,school_no,district,head,logo)
 SELECT COALESCE(NULLIF(UPPER(TRIM((SELECT setting_value FROM settings WHERE setting_key='school_code'))),''),'BULIG'),
  COALESCE((SELECT setting_value FROM settings WHERE setting_key='school_name'),''),
  COALESCE((SELECT setting_value FROM settings WHERE setting_key='school_id'),''),
  COALESCE((SELECT setting_value FROM settings WHERE setting_key='school_district'),''),
  COALESCE((SELECT setting_value FROM settings WHERE setting_key='school_head'),''),
  COALESCE((SELECT setting_value FROM settings WHERE setting_key='logo_school'),'')
 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM schools);
UPDATE users SET school_id=(SELECT MIN(id) FROM schools) WHERE school_id IS NULL AND role IN('teacher','pupil');
INSERT IGNORE INTO schema_migrations(version) VALUES('031_schools');
