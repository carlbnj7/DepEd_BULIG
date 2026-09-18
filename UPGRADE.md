# Version 3 on Hostinger

If v2 is already installed, use `HOSTINGER-UPDATE.md` and import only 004_visuals.sql and 005_level7.sql. The instructions below describe the older v1 → v2 prerequisite. The PHP runner now applies all five migrations.

# Upgrade an existing BULIG installation to v2

This upgrade targets the original PHP/MySQL package supplied in this chat. If you have independently changed the database schema or application code, merge those changes before replacing files. Your actual Hostinger or local database credentials belong in your current `config/database.php`; keep that file.

## 1. Back up and replace application files

Export the current database from phpMyAdmin and copy your current project folder somewhere safe. Update during a quiet period when pupils are not submitting work.

Merge these folders from the new package into the corresponding existing folders:

- `app/`
- `public/` — preserve any existing `public/assets/uploads/` files.
- `database/migrations/`

Copy `tools/upgrade_v2.php` and `UPGRADE-V2.bat` as well.

Do not delete or replace your database, `config/database.php`, existing profile uploads, or customized hosting configuration. Do not run `fresh_install_v2.sql`, `schema.sql`, or `content.sql` on the existing database.

## 2. Run the migration

### XAMPP / PHP terminal — recommended

Double-click `UPGRADE-V2.bat`, or use:

```bat
cd C:\xampp\htdocs\bulig
C:\xampp\php\php.exe tools\upgrade_v2.php
```

The script reads your existing database configuration. Successful output ends with **Upgrade complete**. It records applied versions and can be safely rerun after a successful upgrade. If a query fails, resolve that error and rerun; do not reset the database.

### Hostinger / phpMyAdmin without a PHP terminal

Select your **existing BULIG database** in phpMyAdmin. Import these files, in order:

1. `database/migrations/001_v2_structure.sql`
2. `database/migrations/002_content.sql`
3. `database/migrations/003_existing_progress.sql`

The files do not specify a database name, so they work with your existing hosting database name. The first structural file is a one-time migration. If it reports a duplicate column after an interrupted/manual reimport, do not keep reimporting it; use the PHP migration runner or inspect which statements already succeeded.

The content update preserves administrator-edited activities where `revision > 1`. Check their new **Interaction** setting in Lesson Studio, because the upgrade deliberately avoids overwriting custom content. The default for existing custom activities is “Speak or type an answer.” Set informational pages to “Read / listen, then Next.”

## 3. Verify the upgrade

1. Sign out and refresh with Ctrl+F5.
2. Teacher: open **My sections**. Previously used section names should already appear under the right grade.
3. Teacher: select a section in the dashboard, and confirm only your pupils in that section appear.
4. Pupil: open the current lesson. There should be no approval-wait screen.
5. Complete an answer, choose Next, and test a content-only page without entering text.
6. Finish the lesson with Done and confirm that the next lesson opens.
7. Compare old account details, saved responses, and XP to your backup.

## What the migration changes

- Adds teacher-owned `sections` and `pupil_sections`, backfilling existing assignments.
- Adds explicit activity interaction mode, completion outcome, and saved transcript fields.
- Preserves pupil IDs, teacher IDs, passwords, lesson/activity IDs, answers, prior scores, XP, and history.
- Releases former approval queues as legacy completions, retaining original response and decision history.
- Adds missing XP transactions once and retains already earned totals.
- Keeps historical earned XP from informational pages. New informational completions earn no XP.
- Corrects known image mappings and copies the original image bytes into lesson folders.
- Keeps completed lessons completed. If an old lesson has all responses but was waiting for a final review, the pupil now finishes it using Done.

If the new pages report a database error, confirm all three migrations succeeded and that your existing configuration still points to the correct database. Do not replace MySQL connection details with FTP host/port values.
