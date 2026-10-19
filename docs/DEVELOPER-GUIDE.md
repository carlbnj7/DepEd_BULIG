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
- `app/ratings.php` (table `app_ratings`, update `029_app_ratings.sql`, script `public/assets/rate.js`): **app ratings**.
  Pupils rate BULIG with 1–5 stars after they finish a lesson (at most once a month); teachers answer a 5-question star
  evaluation (`RATE_QUESTIONS`) on Home once a quarter after 14 days with BULIG, or any time at `?page=rate` (My profile ›
  Rate BULIG). "Not now" waits 7 days (pupils) or 3 days (teachers), kept in `settings` as `rate_later_<user id>`. The
  administrator reads the results at `?page=ratings` (Reports › App ratings); each person counts once per period.
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
- **Level 4 scoring** (`app/level4.php`): while the pupil reads, `public/assets/l1.js` runs the browser's speech
  recognition and sends what it heard (`transcript`). `l4_miscues()` lines it up with the passage (`l4_words()`, the
  title too when the module counts it) and names each change with the module's 7 miscue types; `l4_auto()` saves the
  Oral Reading Score, Reading Level and Reading Speed in `rubric_scores` (`l4`, `auto` = 1) and `score` / 100. Under a
  third of the passage heard = no automatic score (the teacher scores from the recording). The teacher's table keeps
  BULIG's marks when numbers are changed. `php tests/probe.php l4` checks every passage.
- **Section data report** (`app/section_report.php`, Print centre card `#pc-sd`, `?page=section_report&section=&level=&p[]=&month=`):
  one section in one level on A4 pages with the report card band (`rc_logo`, `rc_tribal`). Pages: `sum`, `pp`, `irl`
  (Level 4 only), `rt` (Level 4 miscues and passages; other levels the answer-key questions most pupils missed),
  `help`, `act` (learning_days, logins and `offline_answer` from audit_log). Bar widths use `w0`–`w100` classes
  (no inline styles: the CSP blocks them).
- **Names in parts** (`app/names.php`, script `public/assets/names.js`, update `030_name_parts.sql`, table `user_names`):
  Add one pupil / Add one teacher ask for First, Middle, Last name and an Extension (`NAME_EXTS`). `users.name` keeps the
  shown name from `name_compose()` ("Juan S. Dela Cruz Jr."); the parts go in `user_names` (`name_parts_save()`, skipped
  when the table is missing). Editing a name as one line (Edit account, admin rename) removes the parts (`name_parts_forget()`).
  All-capital or all-small names are capitalised (`import_name_case()`).
- **A whole class** (`import_card()` in `app/pupil_import.php`): two tabs. "Type the list" (`import_typed_grid()`, action
  `import_typed`, fields `t[last|first|middle|ext|sex|lrn][row]`) runs the same `import_check()` as a file; any row to fix
  sends the list back with the notes and saves nothing, otherwise `import_create()` makes the accounts. "Upload a file"
  (`how=file`) is the CSV/Excel import; the templates for pupils and teachers have an Extension column.
- **Sections** (`app/sections.php`): the Sections page (Grade first, then the name, "Shows as …", one card per section with
  Add pupils `?page=accounts&section=`, View pupils, Report cards, Rename and Remove; Remove only when the section has no
  pupils, action `delete_section`). `section_select()` is the Section list of Add pupils with "+ Add a new section…"
  (`section_id=new`, `new_grade`, `new_section`); `section_from_post()` turns it into a section. A teacher cannot add the
  same section twice; another teacher's section with the same grade and name only shows a warning (`section_known()`).
  Type the list: `sec_mode` one (one Section for all) or many (`t[sec][row]`), and `t[level][row]` a starting level on each
  row ("Starting level for everyone" fills the rows not changed by hand).
- **Delete an account** (`app/account_delete.php`, v162): `delete_pupil` (the pupil's teacher, on the pupil's Account tab;
  the administrator, in Account check › turned-off pupils) and `delete_teacher` (administrator, Teachers page, only when the
  teacher has no pupils and no sections). The person types DELETE (`confirm`). A pupil's rows in every pupil table, their
  per-person settings, uploaded picture and `storage/recordings/<id>` go too; what others did keeps its record without the name.
- **George's friends** (`app/friends.php`, `public/assets/friends.js`, v163): `BULIG_FRIENDS` lists George, Max, Dracs,
  Bruno, Mia, Toby, Zion, Arah and Claude (pictures `assets/images/characters/<id>-<pose>.webp`). "Meet your BULIG friends"
  shows once on the pupil Home (`<meta name="bulig-friends">` until `friends_met_<id>`; action `friends_met` also sets
  `george_met_<id>`); app.js opens it after the welcome panels (`window.buligFriendsStart`) and the tour offer waits for it.
  Each friend speaks with their own voice (female for girls, male for boys, own pitch and speed). Claude the Protector:
  `claude_tip()` on every Home page, `claude_pop()` worried/sad in the sign-in popups.
  Roxanne the Timekeeper (v164, pictures `roxanne-*`): `roxanne_timer()` beside the Level 4 recorder (l1.js `rxSay()`),
  `roxanne_goal()` on Today's goal, and a break note after 30 minutes of learning (friends.js, sessionStorage
  `bulig-rest-<id>`). `friends_tour_fix()` gives pupils friends instead of their boy/girl picture (meta `char`, `hi`,
  `pals`: dl, dlDone, save, bye, remind, rest); the boy/girl picture stays on the reading ladder and the lesson map.
- **Sign-in page friends** (v165): `login_friend_pairs()` (friends.php) gives the 5 pairs in `data-lfx-pairs`; login.js
  swaps the two pictures every 4.2 seconds (classes `lfx-swap`, `lfx-in`). The teacher sign-in keeps the teacher characters.
- **Friends on their pages** (v166): `friend_say($id,$pose,$text)` (friends.php, class `fs-say fs-<id>`): Arah on My calendar
  (`arah_say`), Zion and Toby on My achievements (`toby_say`), Toby on the reading board, Claude in pupil Settings, Bruno
  in the result card after a wrong answer (scoring.php), and `level_friend($lv)` on each lesson's first screen (Max L1,
  Mia L2A-L3, Roxanne L4, Dracs L5-L7). Rank emblems were redrawn (v166); `rk_img()` adds `?v=2`.
- **Notifications page** (`app/notify.php`, v164): All / Unread tabs, actions `notif_mark` (`to` read|unread),
  `notif_delete`, `notif_delete_read` and `notif_read_all`, only on the person's own rows; Notifications is in every menu.
- **No Lesson studio** (v161): the administrator cannot change module content. The pages `content`, `edit_activity`, `media`
  and `issues` and the actions `save_lesson`, `save_activity`, `save_assessment`, `upload_media`, `acknowledge` and
  `admin_toggle_activity` were removed; old links go to Admin Home. Picture check (System health › `visual_audit`) stays, read-only.
- **Easier on phones** (v161): the ? of the tours docks in the top bar on phones (`dock()` in `tour.js`: beside the hand in a lesson,
  beside the bell or the menu button elsewhere). George sits in the pupil's greeting card on phones. Teacher Home shows a short
  "My pupils" list (`.tf-list`); Class at a glance moved to the end of Class results (`class_glance()`). The tour offer and
  George's hello wait while the "menu has a new order" note is showing.
- **Redrawn pictures** keep their file names and are saved as WebP (quality 82, longest side at most 1200 px).
  Browsers keep pictures for up to 7 days, so `public/sw.js` fetches each redrawn picture fresh once (the
  `level(2[ab]|[3-7])` rule, cache `bulig-fresh-3`). Add a level to that rule when its pictures are redrawn. Change the cache name (bulig-fresh-N) whenever more pictures are redrawn, so devices fetch them again.
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

## George the Explorer (BULIG's guide)

George is one character for everyone, with five poses in `public/assets/images/characters/george-*.webp`
(hello, thumbs, point, map, cheer). Use `char_src('george', $pose)` in PHP.

- Pupil Overview: the reading buddy in the corner (`pf_helper`).
- Tours on every dashboard (`tour.js`): she guides each step, introduces herself in the first offer, and says hello once to
  people who already took the tour (remembered as `george_met_<user id>` in `settings`).
- Finished lessons, finished levels, the new-level gate, level up, rank up and head start (`pupil_fun.php`, `reader_level.php`).

The pupil's own boy or girl stays on the activity screens, the reading ladder and the hello after signing in.
