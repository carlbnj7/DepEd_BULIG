-- Import into the existing BULIG database. Safe to rerun.
-- Existing pupils may keep details empty until a teacher supplies them.
CREATE TABLE IF NOT EXISTS pupil_details (
 pupil_id BIGINT UNSIGNED PRIMARY KEY,
 sex ENUM('male','female') NULL,
 lrn VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
 UNIQUE KEY uq_pupil_lrn (lrn),
 FOREIGN KEY (pupil_id) REFERENCES pupils(user_id)
);
INSERT IGNORE INTO schema_migrations(version) VALUES('006_pupil_details');
