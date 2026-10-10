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
- **Pictures:** keep them small. `python3 tools/shrink_images.py` shrinks PNG/JPG files without changing names.

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
