-- Repair installed Level 2 availability only. No progress or account changes.
START TRANSACTION;
UPDATE bulig_levels SET published=1
WHERE id IN (2,3)
AND (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=bulig_levels.id AND l.published=1)>=CASE id WHEN 2 THEN 22 ELSE 24 END
AND (SELECT COUNT(*) FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=bulig_levels.id AND l.published=1 AND a.published=1)>=CASE id WHEN 2 THEN 103 ELSE 66 END;
INSERT IGNORE INTO schema_migrations(version) VALUES('008_level2_availability');
COMMIT;
