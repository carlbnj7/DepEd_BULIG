-- Adds only the missing future card. Keeps existing levels and assignments.
INSERT IGNORE INTO bulig_levels(id,title,published) VALUES(8,'Level 7 — Content not installed',0);
INSERT IGNORE INTO schema_migrations(version) VALUES('005_level7');
