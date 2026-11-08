-- BULIG organizational hierarchy and multiple roles (November 2026). ADDITIVE ONLY: nothing is deleted or reset.
-- Safe to import more than once. Back up the database first (Print & download › Full database).
-- Central Admin → Municipalities → Districts → Schools → Teachers → Pupils.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS municipalities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY municipalities_name(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS districts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 municipality_id INT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY districts_name(municipality_id,name),
 CONSTRAINT districts_muni_fk FOREIGN KEY(municipality_id) REFERENCES municipalities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- A school keeps its id, code, name and every account; it only gains a district link (empty = waiting for review).
ALTER TABLE schools ADD COLUMN IF NOT EXISTS district_id INT UNSIGNED NULL AFTER district;
-- One account can hold several roles. Teachers and pupils keep their role from users.role (no rows needed).
-- scope_id: the municipality, district or school the role is for (0 = the whole system, for Central Admin).
CREATE TABLE IF NOT EXISTS user_roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(30) NOT NULL,
 scope_id INT UNSIGNED NOT NULL DEFAULT 0,
 appointed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY user_roles_one(user_id,role,scope_id),
 KEY user_roles_scope(role,scope_id),
 CONSTRAINT user_roles_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT user_roles_by_fk FOREIGN KEY(appointed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Existing administrators keep full access as Central Admin.
INSERT IGNORE INTO user_roles(user_id,role,scope_id) SELECT id,'central_admin',0 FROM users WHERE role='admin';
-- First municipalities and districts.
INSERT IGNORE INTO municipalities(name) VALUES('Manolo Fortich'),('Libona');
INSERT IGNORE INTO districts(municipality_id,name) SELECT id,CONCAT('Manolo Fortich District ',d) FROM municipalities JOIN (SELECT 'I' d UNION SELECT 'II' UNION SELECT 'III' UNION SELECT 'IV') x WHERE name='Manolo Fortich';
INSERT IGNORE INTO districts(municipality_id,name) SELECT id,CONCAT('Libona District ',d) FROM municipalities JOIN (SELECT 'I' d UNION SELECT 'II') x WHERE name='Libona';
-- Link a school to a district only when its typed district matches a district name exactly. Others wait for review.
UPDATE schools s JOIN districts d ON CONVERT(d.name USING utf8mb4) COLLATE utf8mb4_general_ci=CONVERT(s.district USING utf8mb4) COLLATE utf8mb4_general_ci SET s.district_id=d.id WHERE s.district_id IS NULL;
INSERT IGNORE INTO schema_migrations(version) VALUES('040_hierarchy_roles');
