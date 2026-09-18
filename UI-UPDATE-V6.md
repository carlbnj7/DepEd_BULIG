# BULIG v6 — pupil home, login, and grade-first teacher dashboard

For your existing v5 installation. No new SQL import is needed. Keep the database and config/database.php unchanged.

1. Download and extract BULIG-UI-Update-v6.zip.
2. Back up the matching files currently on Hostinger.
3. Upload app/views.php into public_html/app/ and replace the old views.php.
4. Upload public/index.php into public_html/public/ and replace its index.php. Do not replace the separate 41-byte index.php directly inside public_html.
5. Upload public/assets/app.js and public/assets/design.css into public_html/public/assets/ and replace matching files.
6. Upload public/assets/design/reading-garden.svg into public_html/public/assets/design/.
7. Open the website again. Teacher dashboard: choose Grade 1–6 first, then choose a section. Changing grade resets the section. Only sections and pupils for that grade appear; pupil count and completed-activity count follow the selection. Existing section links automatically select their grade.
8. Pupil home: use the prominent Continue learning card or open the learning path. The next unfinished Level 1 lesson is selected from saved progress. Completed pupils can review lessons. Future levels remain locked.

These destination paths match your screenshot: public_html contains app, config, and public. If you intentionally use a different layout, merge into those existing corresponding locations.

## Existing setup error

The login artwork no longer makes a database query. This removes an unnecessary dependency for displaying the sign-in form, but it does not repair database credentials or missing database tables. The supplied public/index.php displays a safe error category and code if an operation still fails, instead of labeling every PHP error as a database failure. Send that diagnostic message if the problem continues. It never displays passwords, SQL queries, or pupil records.

## Verified

PHP syntax checks; 38 pupil/account/avatar HTTP regression checks; grade-separated rosters; dependent section dropdown; selected-section roster; invalid grade/section rejection; live browser login, teacher dashboard, and pupil home at 320/390/1440 pixels; Continue learning opens a lesson; no broken images, horizontal overflow, or JavaScript page errors in those checks. Screenshots inspected. Tests used a disposable database, not your live hosting.

Original BULIG logo, avatars, photo upload, default pupil password, optional password changes, and learning content are preserved.
