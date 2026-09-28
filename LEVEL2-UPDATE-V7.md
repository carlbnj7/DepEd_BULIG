# BULIG v7 — install Levels 2A and 2B on your existing Hostinger site

Use **BULIG-Level-2A-2B-Update-v7.zip** for the existing v6 installation. This update contains no database password file and no pupil uploads.

## What is included

- Level 2A: 20 numbered activities across eight skills, plus before/after assessment worksheets (22 learning steps).
- Level 2B: 22 numbered activity sheets, plus before/after assessment worksheets (24 learning steps).
- 169 activity screens, including short introductions and original worksheets. 122 distinct worksheet-page images are included; a continued sheet is reused where needed.
- Original module pictures, worksheet enlargement, scroll/pen modes, typed or voice-to-text numbered answers, saved drawing drafts and teacher review with the original sheet behind the marks.
- Completing Level 1 opens 2A; completing 2A opens 2B. Teachers can choose 2A or 2B as the starting level regardless of grade. Levels 3–7 remain locked because their content is not installed.
- Original PDFs and all 329 source-page renders are retained for teacher/admin reference. Oral-assessment protocols and answer keys are teacher-only.

Online worksheet completion is **not an automatic correctness score**. The app saves responses and awards completion XP. It does not verify every numbered answer or assess pronunciation. The original timed, teacher-led oral tests remain separate from these untimed online worksheets. Module inconsistencies are documented in `docs/LEVEL2-CONTENT-NOTES.md`.

## Step 1 — make a backup

Download a copy of your current website files. In phpMyAdmin, select your existing BULIG database and use Export to save an SQL backup. Keep both backups until the updated site is working.

## Step 2 — upload and extract the update

Your earlier screenshot shows this project layout: `public_html/app`, `public_html/config`, and `public_html/public`.

1. Download the update ZIP to your computer.
2. Open the website's File Manager and enter `public_html`.
3. Upload the update ZIP there and extract it there.
4. Merge the extracted `app`, `public`, `database`, `storage`, `tools`, and `docs` folders into the corresponding existing folders. Replace matching files. If extraction creates a wrapper folder, move its contents into `public_html`.
5. Check these exact destination examples:
   - `public_html/app/bootstrap.php`
   - `public_html/public/index.php`
   - `public_html/public/assets/images/level2a/page-006.webp`
   - `public_html/public/assets/images/level2b/page-012.webp`
   - `public_html/database/level2-manifest.json`
   - `public_html/database/migrations/007_level2_content.sql`
   - `public_html/storage/level2a-original.pdf`
   - `public_html/storage/level2b-original.pdf`
6. Keep your existing `config/database.php`, `public/assets/uploads`, and the separate root `public_html/index.php`. None is included in this patch.

Do not put the whole update inside `public_html/public`; the `app`, `database`, and `storage` folders belong beside `public`, not inside it.

## Step 3 — import the ONE new SQL file

1. Unzip the same download on your computer so you can select its SQL file.
2. Open phpMyAdmin for the **existing database used by your BULIG site**.
3. Select that database in the left panel.
4. Click Import, choose `database/migrations/007_level2_content.sql` from the extracted update, and run the import.
5. Wait for the successful-import message.

This adds the new content and marks Levels 2A and 2B as published. It does not reset accounts, existing lessons, responses, XP, or section assignments. Reimporting this migration preserves existing activity edits and saved work; this was tested.

**Do not import `schema.sql`, `content.sql`, or a `fresh_install` file into your existing database.** This patch assumes v6 with migration 006 already installed. If you are running an older package, use the project's CLI upgrade tool to apply missing earlier migrations in order before this one.

## Step 4 — choose a pupil's starting level

1. Sign in as teacher.
2. Open My pupils.
3. Open the pupil's account details.
4. Set Starting level to Level 2A or Level 2B when appropriate, then save.
5. Sign in as that pupil. The assigned level opens immediately, beginning with its before-assessment worksheets. Other pupils keep their existing starting levels and progress.

A pupil assigned Level 1 completes its 12 lessons before 2A opens. At the end of each learning step, use Finish Lesson / Done to record completion and open the next step. Teacher feedback is optional and does not block progression.

## Step 5 — check a worksheet

Open an available level and its first learning step. Use Next on the introduction, then check that the original sheet appears. Use Enlarge sheet for small print, Use pen to mark answers, and Move sheet to scroll. Numbered text answers and Speak Answer are also available. Check voice-to-text before submitting.

Sign in as teacher and open Activity history to see saved responses. The Teaching guide selector includes the new levels and offers the original PDF download.

## If the setup-error screen appears

Copy its full `BULIG diagnostic: ...` line. It identifies whether PHP cannot connect, a table/column is missing, or another operation failed. A cache refresh does not repair a database connection. This package was tested locally; it does not change or verify your live Hostinger credentials.

## Optional local / command-line update

With the existing project configured for the correct database, run from its root:

```bat
C:\xampp\php\php.exe tools\upgrade_v2.php
```

This applies only missing registered migrations, including 007. It uses `config/database.php`; running on XAMPP does not update your separate Hostinger database.
