-- BULIG Level 5 fixes (October 2026). Safe to import more than once.
-- Grade 3 PDF pages 13, 53 and 58 are ANSWER KEYS: hide them from pupils (teachers still see them in the Teaching guide).
SET NAMES utf8mb4;
UPDATE activities a
  JOIN lessons l ON l.id=a.lesson_id
  JOIN modules m ON m.id=l.module_id
SET a.published=0
WHERE m.level_id=6 AND m.grade_level=3 AND a.source_page IN (13,53,58);
