-- BULIG: more badges, including badges for the reader level (Level 1 to 100) and for a head start.
-- New badge rules: rlevel (reader level reached), levels (whole BULIG levels finished), drawing, voice,
-- headstart (the teacher started the pupil at a higher level) and weekend (learned on a Saturday or Sunday).
-- Safe to run more than once.
ALTER TABLE badges MODIFY rule_type ENUM('activity','lesson','xp','streak','level','reading','perfect','rlevel','levels','drawing','voice','headstart','weekend') NOT NULL;

INSERT INTO badges(title,description,rule_type,threshold_value)
SELECT n.title,n.description,n.rule_type,n.threshold_value FROM (
 SELECT 'Three-day spark' title,'Learn three days in a row' description,'streak' rule_type,3 threshold_value UNION ALL
 SELECT 'Two-week reader','Learn fourteen days in a row','streak',14 UNION ALL
 SELECT 'Busy reader','Finish 25 activities','activity',25 UNION ALL
 SELECT 'Activity star','Finish 100 activities','activity',100 UNION ALL
 SELECT 'Super learner','Finish 250 activities','activity',250 UNION ALL
 SELECT 'Five lessons done','Finish five lessons','lesson',5 UNION ALL
 SELECT 'Lesson leader','Finish 25 lessons','lesson',25 UNION ALL
 SELECT 'Story reader','Finish ten reading activities','reading',10 UNION ALL
 SELECT 'Perfect five','Earn full marks on five assessments','perfect',5 UNION ALL
 SELECT 'Little artist','Finish five drawing activities','drawing',5 UNION ALL
 SELECT 'Brave voice','Record your voice ten times','voice',10 UNION ALL
 SELECT 'Weekend reader','Learn on a Saturday or a Sunday','weekend',1 UNION ALL
 SELECT 'Level finisher','Finish every lesson in one BULIG level','levels',1 UNION ALL
 SELECT 'Three levels strong','Finish three BULIG levels','levels',3 UNION ALL
 SELECT 'Head start','Your teacher started you at a higher level','headstart',1 UNION ALL
 SELECT 'Reader level 10','Reach reader level 10','rlevel',10 UNION ALL
 SELECT 'Reader level 50','Reach reader level 50','rlevel',50 UNION ALL
 SELECT 'Reader level 100','Reach reader level 100, the highest level','rlevel',100
) n WHERE NOT EXISTS(SELECT 1 FROM badges b WHERE b.title=n.title);

INSERT IGNORE INTO schema_migrations(version) VALUES('026_more_badges');
