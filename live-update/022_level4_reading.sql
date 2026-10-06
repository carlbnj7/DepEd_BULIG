-- BULIG Level 4 (Fluency): fixes from checking every grade's module, and the new reading screens.
-- Safe to import again (MariaDB).
SET NAMES utf8mb4;

-- Grade 2 "The Raincoat": the module's first line (under a grey box on module page 19) is "It is raining outside."
UPDATE activities SET prompt=REPLACE(prompt,'It is raining optsid','It is raining outside.'),narration=REPLACE(narration,'It is raining optsid','It is raining outside.'),source_excerpt=REPLACE(source_excerpt,'It is raining optsid','It is raining outside.') WHERE prompt LIKE '%It is raining optsid%';
UPDATE questions SET content=REPLACE(content,'It is raining optsid','It is raining outside.') WHERE content LIKE '%It is raining optsid%';

-- Grade 4 pre-test: the module's pre-test is two pages, each with its own scoring table
-- (page 9: 255 words; page 10, "He sent back three hot dogs...": 237 words). Add part 2.
SET @l4g4pre=(SELECT l.id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND m.grade_level=4 AND l.position=1 LIMIT 1);
SET @l4g4src=(SELECT a.id FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND m.grade_level=4 AND a.type='reading' AND a.title='Read aloud: My First Baseball Game II' LIMIT 1);
INSERT INTO activities(lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode)
SELECT @l4g4pre,assessment_id,'pre',3,'Read aloud: My First Baseball Game (part 2)',type,instructions,REPLACE(prompt,'Title: My First Baseball Game II','Title: My First Baseball Game'),image_path,image_paths,narration,expected_text,xp_reward,10,source_excerpt,1,1,response_mode
FROM activities WHERE id=@l4g4src AND @l4g4pre IS NOT NULL AND NOT EXISTS(SELECT 1 FROM activities x WHERE x.lesson_id=@l4g4pre AND x.phase='pre' AND x.source_page=10);
SET @l4g4new=(SELECT id FROM activities WHERE lesson_id=@l4g4pre AND phase='pre' AND source_page=10 LIMIT 1);
INSERT INTO questions(activity_id,content,grading) SELECT @l4g4new,REPLACE(q.content,'Title: My First Baseball Game II','Title: My First Baseball Game'),q.grading FROM questions q WHERE q.activity_id=@l4g4src AND @l4g4new IS NOT NULL AND NOT EXISTS(SELECT 1 FROM questions x WHERE x.activity_id=@l4g4new);

-- "Let's get ready" pages (text written by the app, not the module): the lesson opening screen replaces them.
UPDATE activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id SET a.published=0 WHERE m.level_id=5 AND a.type='reference' AND a.title LIKE 'Let%s get ready';

INSERT IGNORE INTO schema_migrations(version) VALUES('022_level4_reading');
