# BULIG developer guide

Read this before changing BULIG. It explains how the project is laid out, how to test a change, and how to
send an update to the live site without breaking it.

## What BULIG is built with

- **PHP 8** (no framework) and **MySQL / MariaDB**.
- Hand-written **HTML, CSS and JavaScript** (no framework, no build step).
- Two small libraries: `jsQR` (reads pass QR codes) and `qrcode-generator` (prints them).
- Hosted on **Hostinger**. The project folder is `public_html`; the app itself is served from `public/`.

## Where things are

| Folder | What is in it |
|---|---|
| `app/` | All PHP code. `bootstrap.php` loads the parts listed in `BULIG_PARTS`. `views.php` draws pages, `actions.php` handles forms. One file per feature (for example `reader_level.php`, `checkup.php`, `teacher_accounts.php`). |
| `public/` | What the browser can reach: `index.php` (every page), `assets/` (CSS, JS, pictures, fonts), `sw.js` (offline mode), `bulig-check.php` (update checker). |
| `database/` | `fresh_install_v2.sql` (a new install) and `migrations/` (update files, numbered, safe to run again). |
| `cron/` | `notify.php`, the scheduled task (phone reminders); run every 15 minutes. |
| `config/database.php` | Database settings. **Never put the live one in an update ZIP.** |
| `tests/` | Automatic tests (`run_tests.py`). |
| `tools/` | Helpers: update builder, checker refresh, picture shrinker, admin creation. |
| `live-update/` | Update ZIPs and SQL files given to the live site, plus preview pictures. |

**Answer keys, post-test pass mark and ranks**

- `database/answer-keys.json` holds the module answer keys (Levels 5–7). `database/answer-keys-2.json` holds the keys
  BULIG judged itself for cards the modules leave unkeyed (Levels 2A, 2B, 3 and 5–7). Edit `database/keys2/*.txt`
  (the format is at the top of `tools/keys/build2.php`), then run `php tools/keys/build2.php`; it checks every answer
  against the real card and lists any mistake. `app/scoring.php` marks answers with both files; cards with no single
  right answer (speaking, drawing, writing, opinions) count as right when answered, and the teacher can change any score.
- `app/post_gate.php`: a pupil opens the next level only after scoring at least 80% (`PT_PASS`) on the level's
  post-test. Below that, "Try again" reopens only the post-test activities that were not fully right. The
  certificate also waits for the pass (`level_finished_at()` in `app/growth.php`).
- `app/reading_board.php`: the pupil's **My reading board** (the school's Reading Progress Board as a staircase; drawn by
  `app.js` from `data-lb`) and the teacher's printable **Reading Progress Report Card** (`?page=report_card&id=` for one
  pupil, `&section=` for a section, A4, one page each). The LRN comes from `pupil_details`; school name, school head and
  school year from Settings → School info, where the admin also uploads the Division and school logos.
- `app/reader_level.php`: the reader level (1–100) and the 10 ranks on top of it (`RK_RANKS`, 10 levels each,
  a star every 2 levels). Emblems are in `public/assets/images/ranks/` (small copies in `ranks/sm/`).
- `app/reading_check.php` (table `reading_checks`, update `028_reading_check.sql`): the **reading check** a Grade 1–6
  pupil with no starting level takes at the first sign-in (`rc_needed()`; `public/index.php` sends them to
  `?page=rcheck`). Seven parts (`RC_PARTS`), each drawn from the levels' own module pre-tests: Grades 1–3 start at
  Part 1 and go up; Grades 4–6 start with Part 5 (a story) and go up or down. A part stops early once the result
  is decided, and Read the words stops after 5 wrong words in a row. Part 1 is rated by the teacher with the Level 1
  rubric (Good, 9 of 12); Parts 6 and 7 pass at 80%; the story uses the Phil-IRI word-reading bands (97% / 90%).
  The levels for an official CRLA or Phil-IRI result the teacher types are in `RC_OFFICIAL`. Do not change these
  numbers without a DepEd source. `public/assets/rcheck.js` listens (speech recognition) and records at the same
  time; recordings stay in `storage/recordings` and only the pupil's teacher can play them (`?page=rc_audio`).
  Teachers see and change levels on `?page=rcheck` and `?page=rcheck_pupil&id=`; a starting level of `0` when
  adding or importing pupils means "let the reading check find it".
- **Automatic checking** (`app/scoring.php`, v151): BULIG checks every answer the moment it is sent, so there is no
  "Check answers" page for teachers. Card activities with an answer key get a result card (`ac_card()`: score, stars,
  the right answer for anything missed) right after "Done" and whenever the activity is opened again. "Try it again"
  (`&again=1`, `ac_practice()`) is practice: the best score is kept and no XP is given. Spoken, drawn and written answers
  count as done. Teachers see every answer on a pupil's page › Answers and scores (`review_view($u,$pupil)`), where they
  can still listen, look, change a score, write a note or let the pupil do it again. Old `?page=review` links redirect
  there or to Class results (`?page=qcheck`).
- `app/staff_ui.php`: the teacher and admin menus (`staff_nav_list()`, grouped with yellow labels), "Find a page"
  (`staff_find_list()`, filtered by `public/assets/staff.js`), Teacher Home, Print centre, Add pupils / Add teachers,
  the pupil list, the one-pupil page with tabs, Admin Home, Print & download and "Where things are now"
  (`?page=moved`). When a page moves, add a line to `moved_view()` and to the tour in `public/assets/tour.js`.

The root `.htaccess` blocks `app/`, `config/`, `database/`, `storage/`, `tools/`, `tests/` and `docs/` from the web.

## Rules the code depends on

- **No inline styles or scripts.** The pages send a strict security policy (`script-src 'self'`). Put CSS in
  `public/assets/theme.css` and JavaScript in a file under `public/assets/`.
- **Load CSS and JS with `asset_url('file.js')`.** It gives the file a new name whenever it changes, so browsers
  can keep it for a year (see `public/.htaccess`) and still get updates at once.
- **New PHP file?** Add its name to `BULIG_PARTS` in `app/bootstrap.php`. If an update forgets to upload it,
  BULIG then shows "BULIG is being updated" instead of an HTTP 500 error.
- **Every form** includes `csrf_field()`, and every action calls `check_csrf()` (done centrally in `actions.php`).
- **Database changes** go in a new numbered file in `database/migrations/`. Write it so it can run twice
  (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, guarded `ALTER`) and end it with
  `INSERT IGNORE INTO schema_migrations(version) VALUES('0NN_name');`. Add the table to `EXPECTED_TABLES` in
  `tests/run_tests.py` and to the database list in `public/bulig-check.php`.
- **Button colours** (`public/assets/theme.css`, poster palette): green buttons and selected tabs or answers use
  `var(--btn-green)` (hover `var(--btn-green-hover)`), yellow buttons `var(--btn-yellow)`. White buttons: white, a
  `var(--y300)` edge and `var(--g700)` writing; light green buttons: `var(--g100)` with a `var(--g200)` edge. Use these
  instead of new hex colours, so every button keeps the same green.
- **Liquid Glass** (`theme.css`, sections v130, v131 and v150; script in `app.js`): only the navigation layer is glass
  (sidebar, top bar, pupil tab bar, round buttons, popups, segmented tabs). Cards with words stay solid. Behind words
  the glass keeps enough tint to read. `html.lg-lite` (slow phones, "Reduce transparency", or Glass look off in
  Settings) turns it all back to solid colours, so every glass rule starts with `html:not(.lg-lite)`.
- **Pictures:** keep them small. `python3 tools/shrink_images.py` shrinks PNG/JPG files without changing names.
- **Account IDs** (`app/account_ids.php`): make every new ID with `new_account_id('pupil'|'teacher'|'admin')`
  inside the transaction that creates the account (Pupil PCES260001, Teacher PCEST0001, Admin A260001; numbers per
  school code in `id_sequences`). IDs never change after they are made. Old IDs live in `account_id_history` and
  still sign in; the admin switches old accounts in Settings › Account IDs after a backup is saved in `storage/backups`.
- **Offline mode** (`public/sw.js`, `public/assets/offline.js`, `app/offline.php`): pupils save levels
  (`?page=offline_manifest`) into caches named `bulig-off-<pupil>-L<level>`; teachers save Class Demo levels
  (`?page=demo_offline`, one per level or per grade for Levels 4–7) into `bulig-off-<teacher>-D<level>[-<grade>]`.
  A page that loads pictures from a script (not from its HTML) must list them in `offline_extra_assets()`.
  Answers and "Mark as done" given offline wait in IndexedDB (`bulig-offline`) and are sent when back online.
- **Blanks in card words** (`app/level2a.php`): write them as `___` in the content. `blank_html()` draws them on the
  pupil's card and on Class Demo slides. In Levels 2A, 2B and 3 a blank shows one short line per missing letter,
  counted from the card's answer key by `blank_letters()` ("n ___ t" with the answer "e" shows n _ t); the letter
  tiles fill those lines. A blank for a word in a sentence is one long line.
- **"I need help"** (`app/help.php`): the pupil's request is saved in `help_requests` and sent to every teacher of the
  pupil as a `help` notification. The teacher sees it in the bell and in "Asked for help" on the Overview;
  "Mark as helped" marks that notification as read.

## Run BULIG on your computer

1. Install PHP 8, MariaDB (or MySQL) and Python 3.
2. Make an empty database, import `database/fresh_install_v2.sql`, then every file in `database/migrations/`
   in number order.
3. Create the first admin: `php tools/create_admin.php`.
4. Start it: `cd public && php -S 127.0.0.1:8080`, then open http://127.0.0.1:8080/.
   Database settings can be given as `BULIG_DB_HOST`, `BULIG_DB_NAME`, `BULIG_DB_USER`, `BULIG_DB_PASS`.

## Test every change

```
python3 tests/run_tests.py
```

It checks every PHP file, builds a fresh test database from the install file and all update files, adds test
accounts, starts BULIG on port 8099, and signs in as a pupil, a teacher and an admin. It checks that every page
opens without PHP errors and that the main rules hold (security code on forms, page access by role, sign-in lock,
XP given once, reader level head start, drafts). It takes about 20 seconds and never touches your real database.

It needs a MySQL account that may create and drop the test database, for example:

```
CREATE USER 'bulig_test'@'127.0.0.1' IDENTIFIED BY 'choose-a-password';
GRANT ALL ON `bulig_test`.* TO 'bulig_test'@'127.0.0.1';
```

then `BULIG_TEST_DB_USER=bulig_test BULIG_TEST_DB_PASS=choose-a-password python3 tests/run_tests.py`.
All checks must pass before an update goes out.

## Send an update to the live site

1. Refresh the checker fingerprints: `python3 tools/update_checker.py`.
2. Build the ZIP, giving the version the live site has now:
   ```
   python3 tools/make_update.py --from <commit on the live site> --name BULIG-What-Changed
   ```
   It collects every file changed since that version, leaves out what must never be uploaded, **installs the ZIP
   on a copy of that version and runs all the tests on it**. If anything fails, no ZIP is made.
3. It prints the files and the SQL files to import (if any).
4. On Hostinger: back up the database (System health → Backups), extract the ZIP into `public_html`, import any
   SQL files in phpMyAdmin, then open `bulig-check.php` (or System health) to confirm every file arrived.
5. Commit and push, so the next update knows where the live site is.

## Where to look when something breaks

- **"BULIG is being updated" page:** a file is missing on the server; the page names it. Upload the full ZIP again.
- **A database error page:** a table or column is missing; import the update SQL named in System health.
- **A page looks old after an update:** press Ctrl+F5. Style and script files change name on every update, so
  this is rare.
- `tests/last-run-server.log` holds the PHP log of the last test run.
