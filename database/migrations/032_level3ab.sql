-- BULIG v177: Level 3 becomes two levels, as in the Level 3 module: Level 3A and Level 3B.
--   Level 3A (level id 4): pre-assessment Tasks 1-2, short vowels CVC (lessons 1-5), word families (lessons 6-10), post-assessment Tasks 1-2.
--   Level 3B (level id 9): pre-assessment Tasks 1-2 (consonant blends, sight words), consonant blends (lessons 11-20),
--                          basic sight words (lessons 21-25), post-assessment Tasks 1-2.
-- Nothing is copied or deleted: the 3B lessons and test tasks are moved, so every pupil's answers, scores and progress stay.
-- Lesson positions do not change, so the cards and answer keys of every activity stay the same.
-- Pupils who already finished the Level 3 pre-test or post-test have the new 3B pre-test or post-test lesson marked finished too.
-- Safe to run more than once.
INSERT IGNORE INTO bulig_levels(id,title,published) VALUES(9,CONCAT('Word Recognition ',CONVERT(0xC2B7 USING utf8mb4),' Level 3B'),1);
UPDATE bulig_levels SET title=CONCAT('Word Recognition ',CONVERT(0xC2B7 USING utf8mb4),' Level 3A') WHERE id=4;
UPDATE bulig_levels SET title=CONCAT('Word Recognition ',CONVERT(0xC2B7 USING utf8mb4),' Level 3B') WHERE id=9;

INSERT INTO modules(id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),9,CONCAT('Word Recognition ',CONVERT(0xC2B7 USING utf8mb4),' Level 3B'),NULL FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM modules WHERE level_id=9);
SET @m3a=(SELECT id FROM modules WHERE level_id=4 AND grade_level IS NULL ORDER BY id LIMIT 1);
SET @m3b=(SELECT id FROM modules WHERE level_id=9 ORDER BY id LIMIT 1);
UPDATE modules SET title=CONCAT('Word Recognition ',CONVERT(0xC2B7 USING utf8mb4),' Level 3A') WHERE id=@m3a;

-- Consonant blends and basic sight words (lessons 12-26 in BULIG) belong to Level 3B.
UPDATE lessons SET module_id=@m3b WHERE module_id=@m3a AND position BETWEEN 12 AND 26;

-- Level 3B pre-assessment and post-assessment lessons (same places as in Level 3A: first and last).
INSERT INTO lessons(module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published)
 SELECT @m3b,1,title,subtitle,objectives,teacher_guide,source_pages,image_path,published FROM lessons
 WHERE module_id=@m3a AND position=1 AND NOT EXISTS(SELECT 1 FROM (SELECT id FROM lessons WHERE module_id=@m3b AND position=1) x);
INSERT INTO lessons(module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published)
 SELECT @m3b,27,title,subtitle,objectives,teacher_guide,source_pages,image_path,published FROM lessons
 WHERE module_id=@m3a AND position=27 AND NOT EXISTS(SELECT 1 FROM (SELECT id FROM lessons WHERE module_id=@m3b AND position=27) x);
SET @pre3a=(SELECT id FROM lessons WHERE module_id=@m3a AND position=1);
SET @pre3b=(SELECT id FROM lessons WHERE module_id=@m3b AND position=1);
SET @post3a=(SELECT id FROM lessons WHERE module_id=@m3a AND position=27);
SET @post3b=(SELECT id FROM lessons WHERE module_id=@m3b AND position=27);

-- Tasks 3 and 4 of the Level 3 tests (consonant blends, sight words) are the module's Level 3B Tasks 1 and 2.
UPDATE activities SET lesson_id=@pre3b WHERE lesson_id=@pre3a AND (title LIKE 'Task 3:%' OR title LIKE 'Task 4:%');
UPDATE activities SET lesson_id=@post3b WHERE lesson_id=@post3a AND (title LIKE 'Task 3:%' OR title LIKE 'Task 4:%');
UPDATE activities SET title=CONCAT('Task 1:',SUBSTRING(title,8)) WHERE lesson_id IN (@pre3b,@post3b) AND title LIKE 'Task 3:%';
UPDATE activities SET title=CONCAT('Task 2:',SUBSTRING(title,8)) WHERE lesson_id IN (@pre3b,@post3b) AND title LIKE 'Task 4:%';

-- Pupils who finished the Level 3 pre-test or post-test: the 3B one is finished too (they answered its tasks there).
INSERT IGNORE INTO pupil_progress(pupil_id,lesson_id,completed_at) SELECT pupil_id,@pre3b,completed_at FROM pupil_progress WHERE lesson_id=@pre3a AND completed_at IS NOT NULL;
INSERT IGNORE INTO pupil_progress(pupil_id,lesson_id,completed_at) SELECT pupil_id,@post3b,completed_at FROM pupil_progress WHERE lesson_id=@post3a AND completed_at IS NOT NULL;

INSERT IGNORE INTO schema_migrations(version) VALUES('032_level3ab');
