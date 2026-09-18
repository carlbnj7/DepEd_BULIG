# Hostinger update — version 3

This is an update to the BULIG PHP/MySQL package from this conversation. It does not replace your database or credentials.

## 1. Back up

Export the existing Hostinger database in phpMyAdmin and keep a copy of your existing project files.

## 2. Upload the updated files

Merge the new package into the SAME project location on Hostinger. Replace matching application files, but keep `config/database.php`, uploaded profile/content pictures, and your hosting configuration.

Required folders/files:

- `app/` — all files
- `public/index.php`
- `public/assets/app.js` and `public/assets/style.css`
- `public/assets/images/` — the complete folder, including every lesson subfolder
- `public/assets/module/` — the complete original-image folder
- `database/visual-manifest.json`
- `database/migrations/004_visuals.sql` and `005_level7.sql`
- `tools/upgrade_v2.php` if you use the PHP terminal runner

**The assets folder must be beside the public `index.php` that serves BULIG.** If you moved the contents of `public/` directly into `public_html/`, upload the contents of the new `public/` into that same `public_html/`; do not create an extra nested `public/` directory. Keep `app/`, `database/` and `config/` in the locations your working installation already uses.

An SQL import cannot upload a picture. Both the image files and their database assignments must be updated.

## 3. Import the new migrations on Hostinger

If you already successfully installed v2 migrations 001–003:

1. Select the EXISTING BULIG database in Hostinger phpMyAdmin.
2. Import `database/migrations/004_visuals.sql`.
3. Import `database/migrations/005_level7.sql`.

These two files can be rerun. They contain no table drops and do not reset accounts, passwords, responses, grades, starting-level assignments or XP. Original activities are repaired only where `revision=1`; administrator-edited activities remain unchanged and should be reviewed in the image check.

If you have not installed v2, follow `UPGRADE.md` for migrations 001–003 first. Do not repeat the structural migration blindly. Stop on an SQL error and retain its full message.

Do not import `schema.sql`, `content.sql`, or `fresh_install_v2.sql` into your existing database.

If using a PHP terminal ON HOSTINGER, run `php tools/upgrade_v2.php` in the hosted project instead; it applies all outstanding migrations. Running it in XAMPP only changes the laptop's database.

## 4. Verify on the live site

1. Refresh with Ctrl+F5. If your hosting/CDN caches assets, clear the site's cache too.
2. Sign in as administrator → Official visuals → Run image check.
3. Review the file/assignment issues, then open each lesson number on that page. The browser reports loaded and failed pictures. Missing-file messages show the exact relative path needed on this server.
4. Check Lesson 5's dog discussion and Lesson 8's picture assessment as a pupil.
5. On a phone, tap Menu. Navigation must open vertically from the left, with a close button and backdrop.
6. As teacher, create a pupil: default password is `12345678`; choose a section and a separate starting level.
7. Confirm Grade 6 / Level 1 works, and future starting levels remain locked.

Five exact picture compositions mentioned in the PDF are not supplied as illustrations: activity 48 (rainbow), 57 (apple tree), 58 (boy with ball), 59 (book on table), 60 (rainbow over lake). The image check identifies them explicitly. An administrator can upload approved classroom pictures in Official visuals and attach their paths in Lesson Studio. These are content inputs still needed, not missing files hidden by a generic placeholder.

No live Hostinger URL or authenticated server access was supplied for this repair. The included verification was performed against the packaged application; run the server image check to verify your uploaded copy.
