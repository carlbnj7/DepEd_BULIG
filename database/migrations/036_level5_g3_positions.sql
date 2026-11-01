-- BULIG Level 5 Grade 3 answer-key fix (November 2026). Safe to import more than once.
-- PDF pages 13 and 53 are answer keys. Fresh installs left them out, so every later activity moved up one place
-- and the answer keys (written for the module order) checked the wrong activity. This puts them back in module order.
SET NAMES utf8mb4;
UPDATE activities SET position=position+1
WHERE lesson_id IN (SELECT l.id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=6 AND m.grade_level=3 AND l.position=1100)
  AND phase='pre' AND source_page>=14 AND position=source_page-6
ORDER BY position DESC;
UPDATE activities SET position=position+1
WHERE lesson_id IN (SELECT l.id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=6 AND m.grade_level=3 AND l.position=22300)
  AND phase='post' AND source_page>=54 AND position=source_page-48
ORDER BY position DESC;
INSERT IGNORE INTO schema_migrations(version) VALUES('036_level5_g3_positions');
