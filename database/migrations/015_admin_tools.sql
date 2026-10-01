-- BULIG admin tools: sections can be archived at the end of a school year. Safe to import again (MariaDB).
ALTER TABLE sections ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL;
-- The admin PIN changed from 6 to 4 digits: clear old PINs once, so each admin creates a new 4-digit PIN at next sign-in.
UPDATE admin_security SET pin_hash=NULL,failed_pins=0,locked_until=NULL WHERE NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version='015_admin_tools');
INSERT IGNORE INTO schema_migrations(version) VALUES('015_admin_tools');
