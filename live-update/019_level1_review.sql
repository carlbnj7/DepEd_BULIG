-- Level 1 content review (checked against the Level 1 module, Lesson Plan 3, pages 53-55 and page 17).
-- Safe to import again: each change only applies to the original text.
SET NAMES utf8mb4;
-- Lesson 3: the teacher's two model sentences were shown as questions to answer. The module has the class say
-- the model direction, then make their own 2-step direction.
UPDATE activities SET prompt='Say this kind direction: “Can you please keep your toys away and put them in the toy box?”',
 narration='Say this kind direction: Can you please keep your toys away and put them in the toy box?',revision=revision+1
 WHERE id=129 AND prompt='Can you please keep your toys away and put them in the toy box?';
UPDATE activities SET prompt='Now make your own 2-step direction for the messy room. Example: “Can you pick up your cars and put them on the shelf?”',
 narration='Now make your own 2-step direction for the messy room. Example: Can you pick up your cars and put them on the shelf?',revision=revision+1
 WHERE id=130 AND prompt='Can you pick up your cars and put them on the shelf?';
-- Lesson 3: this is Scenario Card 1 of the module (the next activity is Scenario card 2).
UPDATE activities SET title='Scenario card 1',revision=revision+1 WHERE id=134 AND title='Role-Play Activity';
INSERT IGNORE INTO schema_migrations(version) VALUES('019_level1_review');
