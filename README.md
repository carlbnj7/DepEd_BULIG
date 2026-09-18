# BULIG Level 1 · Version 2

This updates the PHP/MySQL BULIG project built in this conversation. It keeps the official logo and original module, removes teacher-approval barriers, separates content pages from answer activities, fixes image mappings, and adds teacher-owned sections.

**Already running BULIG? Read `UPGRADE.md` first. Do not import a fresh schema over your existing database.**

## Existing installation

1. Back up your project and database.
2. Copy the updated `app/` and `public/` files into your current BULIG folder. Merge folders; keep your existing `public/assets/uploads/`.
3. Copy `database/migrations/`, `tools/upgrade_v2.php`, and `UPGRADE-V2.bat`.
4. Keep your existing **`config/database.php`**. Do not replace your hosting credentials with the local example.
5. Double-click `UPGRADE-V2.bat`, or run `php tools/upgrade_v2.php` from the project directory. This migrates the selected configured database without deleting accounts, responses, or XP.
6. Refresh the browser with Ctrl+F5 and sign in again.

For Hostinger without a PHP terminal, use the three SQL files in `database/migrations/` in order. Select your actual database in phpMyAdmin first. Details are in `UPGRADE.md`.

## New XAMPP installation

1. Put the `bulig` folder in `C:\xampp\htdocs\bulig`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin`. Create an empty database named `DEPED_BULIG`, using `utf8mb4_unicode_ci`.
4. Select that database and import **`database/fresh_install_v2.sql`**. This single file installs schema, all 12 lessons, and the v2 changes. Do not additionally import the older `schema.sql` or `content.sql`.
5. Check `config/database.php`. Local defaults are host `127.0.0.1`, port `3306`, database `DEPED_BULIG`, user `root`, blank password.
6. Double-click `CREATE-ADMIN.bat` and choose an admin ID, name, and password of 8–72 characters. No shared default accounts are included.
7. Open `http://localhost/bulig/public/?page=login&role=admin`.
8. As admin, create a teacher. Sign in as that teacher, open **My sections**, and add a section with its grade.
9. Open **My pupils**, create a pupil, and choose that section. Save the generated pupil ID and password you set.
10. Sign in as pupil at `http://localhost/bulig/public/`.

Requires PHP 8.1+ with PDO MySQL and fileinfo, and MySQL 8+ or MariaDB 10.6+. Node.js and Python are not needed to run the app. For production, serve `public/` through PHP hosting with HTTPS.

## Learning flow

- **Information / example / source page:** read or listen → Next. No typing or microphone required.
- **Practical / group activity:** do the activity → Done. This records participation, not automated verification of the physical action.
- **Answer:** Speak Answer or Type Answer → Submit/Check Answer → immediate feedback → Next.
- **Drawing:** draw on the canvas or describe a paper drawing → Submit Answer → Next.
- **Lesson end:** Finish Lesson → Done → completion screen → learning path. The next lesson opens after Done; the backend checks every required activity.

Open-ended responses are recorded as completed with supportive feedback. They are not falsely labeled correct or assigned an invented score. Five describing-word pre-test questions have explicit answers in the module and are checked automatically. Admins can configure additional exact-answer content only when an official answer key exists.

Teachers can read work, add optional feedback, and record observational rubric scores. These actions never gate pupil progression. Old submitted and returned-for-review work becomes legacy completion during migration; its response and review history are retained.

XP is granted once for each meaningful completed activity. Pure informational screens earn zero XP and do not create learning streak days. Existing earned XP is retained. Logging in does not count as learning.

## Sections

Teachers create and rename their own database-backed sections. Grade is part of each section. Selecting a section when creating or editing a pupil sets that pupil’s grade and section consistently.

The dashboard’s **Select section** dropdown filters the database query itself. Teachers cannot read or edit another teacher’s sections or pupils. A section’s grade cannot change while pupils are assigned; move the pupils first. Existing free-text section assignments are imported per teacher and grade.

## Content and visuals

The original 95-page PDF, 210 extracted images, lesson IDs, and 24 assessments are retained. Per-lesson assets are organized under `public/assets/images/level1/lesson01/` through `lesson12/`. Old asset paths remain available for existing content and historical references.

See `docs/CONTENT-AUDIT.md` and `docs/V2-CHANGES.md`. The PDF has a known Lesson 11/12 post-test heading reversal; the existing mapping by competency is retained and flagged. Certain prompts require a teacher-supplied example picture or real classroom prop that the PDF does not include as an exact image. Those are identified explicitly rather than displaying unrelated artwork.

Source-page reference checklists preserve classroom procedures, evaluations, and supplementary material. Pair work, movement, and real-world props still happen with teachers or peers; a browser cannot verify those actions.

## Voice

Listen and Speak Answer remain separate, pupil-initiated controls. Narration uses installed browser voices, prefers commonly named female English voices, and permits voice selection. Availability and voice quality vary by device.

Speech recognition may use the browser’s online service and generally requires localhost or HTTPS. A typed fallback is always available for answer pages. Check the detected text before submitting. Matching recognized words is not a pronunciation assessment. The app stores transcripts, not microphone audio recordings.

## Validation

See `docs/VALIDATION.md` for the current results and remaining device-dependent checks. The v2 regression suite is `tests/integration_v2.py`; it is for disposable test databases only.
