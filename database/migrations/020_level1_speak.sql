-- BULIG Level 1: spoken answers, module rubric scores, six activities given their right kind,
-- and the teacher's lesson-plan pages kept for teachers only. Safe to import again (MariaDB).
SET NAMES utf8mb4;

-- A pupil's voice recording (kept in storage/recordings, never in the public folder) and the teacher's rubric scores.
ALTER TABLE activity_completion ADD COLUMN IF NOT EXISTS audio_path VARCHAR(160) NULL AFTER drawing;
ALTER TABLE activity_completion ADD COLUMN IF NOT EXISTS rubric_scores TEXT NULL AFTER feedback;

-- Activities the module asks pupils to DO, not to say.
UPDATE activities SET type='physical',response_mode='perform',revision=revision+1 WHERE id=77 AND lesson_id=9 AND type='open';
UPDATE activities SET type='physical',response_mode='perform',revision=revision+1 WHERE id=87 AND lesson_id=10 AND type='open';
-- "Draw a picture of your favorite part of the poem": draw, then upload a photo.
UPDATE activities SET type='drawing',response_mode='drawing',revision=revision+1 WHERE id=82 AND lesson_id=9 AND type='open';
-- Done with a friend or with the group.
UPDATE activities SET type='group',response_mode='perform',revision=revision+1 WHERE id IN (85,86,88) AND lesson_id=10 AND type='open';

-- "Teacher-guided lesson checklist" and "Additional intervention materials": for the teacher only.
-- They stay in the Teacher's Guide; pupils and the Class Demo no longer show them.
UPDATE activities SET published=0 WHERE id IN (205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225)
 AND type='reference' AND title IN ('Teacher-guided lesson checklist','Additional intervention materials');

INSERT IGNORE INTO schema_migrations(version) VALUES('020_level1_speak');
