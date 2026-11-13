-- v215 Administrator IDs without the year, like teacher IDs: DA260001 → DA0001, MA260001 → MA0001, A260001 → A0001.
-- Back up the database first. Import AFTER 045_content_studio.sql.
--  - Only administrator accounts whose ID is letters + year + number change (DV, MA, MV, DA, DS, SA, SP, CD, A).
--    IDs typed by hand (for example "demoadmin") stay as they are. Pupil and teacher IDs do not change.
--  - Each kind of role is numbered again from 0001, oldest account first.
--  - The OLD ID still signs in (account_id_history), and BULIG tells the person their new ID.
--    Passwords, PINs, roles and everything else stay the same. Print new ID cards for the administrators.
--  - Safe to import twice: an ID that already has no year is not changed again.

CREATE TEMPORARY TABLE admin_id_map AS
SELECT x.id user_id, x.public_id old_id,
 CONCAT(x.p, LPAD(ROW_NUMBER() OVER (PARTITION BY x.p ORDER BY x.id), 4, '0')) new_id
FROM (SELECT id, public_id, REGEXP_SUBSTR(public_id, '^[A-Z]+') p FROM users
      WHERE role = 'admin' AND public_id REGEXP '^(DV|MA|MV|DA|DS|SA|SP|CD|A)[0-9]{6,}$') x;

-- if a new ID is somehow taken already, that account keeps its old ID
DELETE m FROM admin_id_map m JOIN users u ON u.public_id = m.new_id;
DELETE m FROM admin_id_map m JOIN account_id_history h ON h.old_id = m.new_id;

INSERT IGNORE INTO account_id_history(user_id, old_id, new_id) SELECT user_id, old_id, new_id FROM admin_id_map;
UPDATE users u JOIN admin_id_map m ON m.user_id = u.id SET u.public_id = m.new_id;

-- the Content Developer's hidden test pupil follows its owner's ID (XCD0001)
UPDATE users t JOIN studio_testers s ON s.pupil_id = t.id JOIN users d ON d.id = s.dev_id SET t.public_id = CONCAT('X', d.public_id) WHERE t.role = 'tester';

-- the next new ID of each kind continues after the highest number
INSERT INTO id_sequences(kind, next_value)
SELECT IF(p = 'A', 'a', CONCAT('r:', p)), MAX(n) + 1 FROM
 (SELECT REGEXP_SUBSTR(new_id, '^[A-Z]+') p, CAST(REGEXP_SUBSTR(new_id, '[0-9]+$') AS UNSIGNED) n FROM admin_id_map) y
GROUP BY p
ON DUPLICATE KEY UPDATE next_value = GREATEST(next_value, VALUES(next_value));

DROP TEMPORARY TABLE admin_id_map;
