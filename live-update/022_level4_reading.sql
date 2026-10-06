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

-- Grade 3 "Verb To Be": the module prints "You'll show the" between "Here today" and "Whole world" (over the scoring table).
UPDATE activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id
 SET a.prompt=REPLACE(a.prompt,'Here today\nWhole world','Here today\nYou\'ll show the\nWhole world'),
     a.narration=REPLACE(a.narration,'Here today Whole world','Here today You\'ll show the Whole world'),
     a.expected_text=REPLACE(a.expected_text,'Here today Whole world','Here today You\'ll show the Whole world')
 WHERE m.level_id=5 AND m.grade_level=3 AND a.title='Read aloud: Verb To Be';
UPDATE questions q JOIN activities a ON a.id=q.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id
 SET q.content=REPLACE(q.content,'Here today\nWhole world','Here today\nYou\'ll show the\nWhole world')
 WHERE m.level_id=5 AND m.grade_level=3 AND a.title='Read aloud: Verb To Be';

-- Grade 4 "Songs of the Witches": "Boil thou first i' the charmed pot." (the module has the period).
UPDATE activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id
 SET a.prompt=REPLACE(a.prompt,'charmed pot\n','charmed pot.\n'),a.narration=REPLACE(a.narration,'charmed pot Double','charmed pot. Double'),a.expected_text=REPLACE(a.expected_text,'charmed pot Double','charmed pot. Double')
 WHERE m.level_id=5 AND m.grade_level=4 AND a.title='Read aloud: Songs of the Witches';
UPDATE questions q JOIN activities a ON a.id=q.activity_id JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id
 SET q.content=REPLACE(q.content,'charmed pot\n','charmed pot.\n')
 WHERE m.level_id=5 AND m.grade_level=4 AND a.title='Read aloud: Songs of the Witches';

-- Grade 2: the "Official module" link opens the page with the passage's title (these three were one page early, on a blank page).
UPDATE activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id
 SET a.source_page=CASE a.title WHEN 'Read aloud: The Farm' THEN 12 WHEN 'Read aloud: The Raincoat' THEN 23 WHEN 'Read aloud: The Bakery' THEN 26 END
 WHERE m.level_id=5 AND m.grade_level=2 AND ((a.title='Read aloud: The Farm' AND a.source_page=11) OR (a.title='Read aloud: The Raincoat' AND a.source_page=22) OR (a.title='Read aloud: The Bakery' AND a.source_page=25));

-- "Let's get ready" pages (text written by the app, not the module): the lesson opening screen replaces them.
UPDATE activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id SET a.published=0 WHERE m.level_id=5 AND a.type='reference' AND a.title LIKE 'Let%s get ready';

INSERT IGNORE INTO schema_migrations(version) VALUES('022_level4_reading');
