-- v216 Roxanne the Timekeeper: how long each activity took (seconds the page was open and in front of the pupil).
-- Import AFTER 047_studio_levels.sql. Safe to import again: it only adds two empty columns.
--  - time_secs: the time of the latest try.  best_secs: the pupil's fastest finished try.
ALTER TABLE activity_completion ADD COLUMN IF NOT EXISTS time_secs INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE activity_completion ADD COLUMN IF NOT EXISTS best_secs INT UNSIGNED NULL DEFAULT NULL;
