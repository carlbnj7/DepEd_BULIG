-- BULIG v212: Junior High School grades (Grades 7-10). Select your EXISTING database first.
-- Adds four grade rows only. Nothing else changes. Safe to import again.
INSERT IGNORE INTO grade_levels(id,name) VALUES(7,'Grade 7'),(8,'Grade 8'),(9,'Grade 9'),(10,'Grade 10');
INSERT IGNORE INTO schema_migrations(version) VALUES('041_jhs_grades');
