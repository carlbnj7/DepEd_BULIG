#!/usr/bin/env python3
"""BULIG automatic tests. Run before every update:  python3 tests/run_tests.py

What it does, in order:
  1. Checks every PHP file for syntax errors, and that every file the app loads is present.
  2. Builds a fresh TEST database from database/fresh_install_v2.sql and every update in database/migrations.
  3. Adds test accounts (tests/seed.php), starts BULIG on http://127.0.0.1:8099 and signs in as a pupil,
     a teacher and an admin. Every page must open with no PHP errors, and the security rules and XP rules
     must hold.
  4. Stops the server and deletes the test database (keep it with --keep).
  --root DIR tests another copy of BULIG (tools/make_update.py uses it to test an update before you install it).

It never touches the live database. Settings (environment variables, all optional):
  BULIG_TEST_DB_NAME  test database name (default bulig_test; it is dropped and made again)
  BULIG_TEST_DB_HOST / BULIG_TEST_DB_PORT / BULIG_TEST_DB_USER / BULIG_TEST_DB_PASS
                      a MySQL/MariaDB account that may create and drop that database (default root, no password)
  BULIG_TEST_PORT     web port for the test server (default 8099)
Needs: PHP 8 command line, the mysql command line client, Python 3 (standard library only).
"""
import glob, http.cookiejar, json, os, re, socket, subprocess, sys, time, urllib.error, urllib.parse, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
if '--root' in sys.argv:
    ROOT = os.path.abspath(sys.argv[sys.argv.index('--root') + 1])
DB = {
    'name': os.environ.get('BULIG_TEST_DB_NAME', 'bulig_test'),
    'host': os.environ.get('BULIG_TEST_DB_HOST', '127.0.0.1'),
    'port': os.environ.get('BULIG_TEST_DB_PORT', '3306'),
    'user': os.environ.get('BULIG_TEST_DB_USER', 'root'),
    'pass': os.environ.get('BULIG_TEST_DB_PASS', ''),
}
PORT = int(os.environ.get('BULIG_TEST_PORT', '8099'))
BASE = f'http://127.0.0.1:{PORT}/'
KEEP = '--keep' in sys.argv
LOG = os.path.join(HERE, 'last-run-server.log')

# Every table a full install must have (the install file plus all 26 update files).
EXPECTED_TABLES = sorted('''activities activity_completion activity_drafts admin_security admins answers assessment_attempts
 assessment_questions assessments audit_log badges bulig_levels content_issues grade_levels help_requests id_sequences
 learning_days lessons login_attempts modules notifications pupil_badges pupil_cards pupil_details pupil_level_assignments
 pupil_progress pupil_rewards pupil_sections pupil_streaks pupil_xp pupils push_queue push_subscriptions questions
 reading_assessments response_history rewards saved_logins schema_migrations sections settings source_pages
 teacher_pupils teachers users xp_transactions'''.split())
# MySQL errors an update file may meet when its change is already in place (the files are safe to run again).
ALREADY_DONE = {1050, 1060, 1061, 1062, 1091, 1826, 1022}

results = []
if DB['name'] in ('', 'DEPED_BULIG'):
    sys.exit('Refusing to run: BULIG_TEST_DB_NAME must be a separate test database.')


def check(name, ok, detail=''):
    results.append((name, bool(ok), detail))
    print(('  ok    ' if ok else '  FAIL  ') + name + ('' if ok or not detail else '  —  ' + str(detail)[:300]))
    return bool(ok)


def section(title):
    print('\n' + title)


# ---------------- 1. Files ----------------
def php_files():
    out = []
    for d in ('app', 'public', 'cron', 'config', 'tools'):
        out += sorted(glob.glob(os.path.join(ROOT, d, '*.php')))
    return [f for f in out if not re.search(r'-[0-9a-f]{10}\.php$', f)]


def test_files():
    section('1. Files')
    bad = []
    files = php_files()
    for f in files:
        r = subprocess.run(['php', '-l', f], stdin=subprocess.DEVNULL, capture_output=True, text=True)
        if r.returncode != 0:
            bad.append(os.path.relpath(f, ROOT) + ': ' + (r.stdout + r.stderr).strip().splitlines()[0])
    check(f'All {len(files)} PHP files have no syntax errors', not bad, '; '.join(bad))
    missing = []
    for src in ('app/bootstrap.php', 'public/index.php'):
        text = open(os.path.join(ROOT, src), encoding='utf-8').read()
        base = os.path.dirname(os.path.join(ROOT, src))
        for m in re.finditer(r"require(?:_once)?\s*\(?\s*__DIR__\s*\.\s*'([^']+\.php)'", text):
            if not os.path.isfile(os.path.normpath(base + m.group(1))):
                missing.append(src + ' needs ' + m.group(1))
        for m in re.finditer(r"const BULIG_PARTS=\[([^\]]*)\]", text):
            for part in re.findall(r"'([a-z0-9_]+)'", m.group(1)):
                if not os.path.isfile(os.path.join(ROOT, 'app', part + '.php')):
                    missing.append(src + ' needs app/' + part + '.php')
    check('Every file the app loads is present (no HTTP 500 from a missing file)', not missing, '; '.join(missing))


# ---------------- 2. Database ----------------
def mysql_cmd(*extra, db=None):
    cmd = ['mysql', '-h', DB['host'], '-P', DB['port'], '-u', DB['user'], '--default-character-set=utf8mb4']
    if DB['pass']:
        cmd.append('-p' + DB['pass'])
    cmd += list(extra)
    if db:
        cmd.append(db)
    return cmd


def mysql(*extra, db=None):
    return subprocess.run(mysql_cmd(*extra, db=db), stdin=subprocess.DEVNULL, capture_output=True, text=True)


def test_database():
    section('2. Database built from the install file and every update file')
    r = mysql('-e', f"DROP DATABASE IF EXISTS `{DB['name']}`; CREATE DATABASE `{DB['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;")
    if not check('Test database can be made', r.returncode == 0, r.stderr.strip()):
        return False
    files = [os.path.join(ROOT, 'database', 'fresh_install_v2.sql')] + sorted(glob.glob(os.path.join(ROOT, 'database', 'migrations', '*.sql')))
    problems = []
    for f in files:
        with open(f, 'rb') as fh:
            r = subprocess.run(mysql_cmd('--force', db=DB['name']), stdin=fh, capture_output=True)
        for line in r.stderr.decode('utf-8', 'replace').splitlines():
            m = re.match(r'ERROR (\d+)', line)
            if m and int(m.group(1)) not in ALREADY_DONE:
                problems.append(os.path.basename(f) + ': ' + line)
    check(f'The install file and all {len(files) - 1} update files run without errors', not problems, '; '.join(problems[:5]))
    r = mysql('-N', '-e', 'SHOW TABLES', db=DB['name'])
    tables = sorted(r.stdout.split())
    missing = sorted(set(EXPECTED_TABLES) - set(tables))
    check(f'All {len(EXPECTED_TABLES)} tables are present', not missing, 'missing: ' + ', '.join(missing))
    return True


# ---------------- 3. The running app ----------------
def env():
    e = dict(os.environ)
    e.update({'BULIG_DB_NAME': DB['name'], 'BULIG_DB_HOST': DB['host'], 'BULIG_DB_PORT': DB['port'],
              'BULIG_DB_USER': DB['user'], 'BULIG_DB_PASS': DB['pass'], 'BULIG_APP_ROOT': ROOT})
    return e


def php_cli(script, *args):
    r = subprocess.run(['php', '-d', 'opcache.enable_cli=0', os.path.join(HERE, script), *map(str, args)],
                       stdin=subprocess.DEVNULL, capture_output=True, text=True, env=env(), cwd=ROOT)
    if r.returncode != 0:
        raise RuntimeError(script + ' failed: ' + (r.stderr or r.stdout).strip()[:400])
    return json.loads(r.stdout.strip().splitlines()[-1])


PHP_ERROR = re.compile(r'<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:|^(PHP )?(Fatal error|Warning|Notice|Deprecated|Parse error):|Uncaught ', re.M)


class Client:
    def __init__(self):
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def req(self, path, data=None, accept_json=False):
        headers = {'Accept': 'application/json'} if accept_json else {}
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        r = urllib.request.Request(BASE + path, data=body, headers=headers)
        try:
            resp = self.op.open(r, timeout=60)
        except urllib.error.HTTPError as e:
            resp = e
        return resp.status if hasattr(resp, 'status') else resp.code, resp.geturl(), resp.read().decode('utf-8', 'replace')

    def token(self, path='?page=login'):
        _, _, text = self.req(path)
        m = re.search(r'name="csrf" value="([0-9a-f]+)"', text)
        return m.group(1) if m else ''

    def login(self, role, public_id, password):
        tok = self.token(f'?page=login&role={role}&form=1&confirm=1')
        return self.req('index.php', {'action': 'login', 'role': role, 'public_id': public_id, 'password': password, 'csrf': tok})


def page_ok(c, path, label, signed_in=True):
    status, url, text = c.req(path)
    err = PHP_ERROR.search(text)
    sent_to_login = urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('page') == ['login']
    ok = status == 200 and not err and (not signed_in or not sent_to_login)
    return check(label, ok, f'status {status}, ended at {url}' + (f', PHP error: {text[err.start():err.start() + 200]}' if err else ''))


def wait_for_server():
    for _ in range(60):
        try:
            socket.create_connection(('127.0.0.1', PORT), timeout=1).close()
            return True
        except OSError:
            time.sleep(0.25)
    return False


def test_app():
    section('3. Test accounts')
    acc = php_cli('seed.php')
    check('Test admin, teacher and two pupils were added', acc.get('pupil1', {}).get('uid'))
    log = open(LOG, 'w')
    server = subprocess.Popen(['php', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-d', 'log_errors=1', '-d', 'opcache.enable=0',
                               '-S', f'127.0.0.1:{PORT}', '-t', 'public'], cwd=ROOT, env=env(), stdin=subprocess.DEVNULL, stdout=log, stderr=log)
    try:
        if not check('Test web server started', wait_for_server()):
            return
        run_pages(acc)
    finally:
        server.terminate()
        server.wait(10)
        log.close()
    lines = [l for l in open(LOG, encoding='utf-8', errors='replace') if re.search(r'PHP (Warning|Notice|Deprecated|Fatal error|Parse error)', l)]
    check('No PHP warnings or errors in the server log', not lines, ''.join(lines[:3]))


def run_pages(acc):
    section('4. Signed-out pages and security')
    anon = Client()
    for role in ('pupil', 'teacher', 'admin'):
        page_ok(anon, f'?page=login&role={role}', f'Sign-in page opens ({role})', signed_in=False)
    page_ok(anon, '?page=credits', 'Credits page opens')
    status, url, _ = anon.req('?page=dashboard')
    check('Signed-out visitor is sent to the sign-in page', 'page=login' in url or status in (302, 401, 403), url)
    _, _, text = anon.req('bulig-check.php')
    bad = len(re.findall(r'class="bad"', text))
    check('bulig-check.php finds no missing or changed files and no missing tables', bad == 0 and 'BULIG' in text, f'{bad} problems listed')
    lock = Client()
    for _ in range(8):
        lock.login('pupil', 'nobody-test', 'wrong-password')
    _, _, text = lock.login('pupil', 'nobody-test', 'wrong-password')
    check('8 wrong passwords lock the sign-in for 15 minutes', re.search(r'locked|15 minutes|wait', text, re.I))

    section('5. Pupil (Level 1, Grade 1)')
    p = Client()
    status, url, _ = p.login('pupil', acc['pupil1']['id'], acc['pupil1']['pw'])
    if not check('Pupil can sign in', 'dashboard' in url and status == 200, url):
        return
    for pg in ('dashboard', 'lessons', 'achievements', 'calendar', 'profile', 'settings', 'notifications', 'offline', 'credits'):
        page_ok(p, f'?page={pg}', f'Pupil page opens: {pg}')
    acts = php_cli('probe.php', 'first_activities', 1, 1)
    if check('Level 1 has lessons and activities', acts, acts):
        first = acts[0]
        page_ok(p, f"?page=lesson&id={first['lesson_id']}", 'Lesson start page opens')
        page_ok(p, f"?page=lesson&id={first['lesson_id']}&activity={first['id']}", 'Activity page opens')
        status, _, text = p.req('index.php', {'action': 'draft', 'activity_id': first['id'], 'response': 'test'})
        check('A form sent without its security code is refused (CSRF)', status == 403 or 'session changed' in text.lower(), f'status {status}')
        status, _, text = p.req('?page=review')
        check('A pupil cannot open a teacher page', status == 403 and 'Class overview' not in text, f'status {status}')
        before = php_cli('probe.php', 'xp', acc['pupil1']['uid'])
        tok = p.token(f"?page=lesson&id={first['lesson_id']}&activity={first['id']}")
        sub = {'action': 'submit', 'activity_id': first['id'], 'response': 'I did it', 'heard': '1', 'csrf': tok}
        p.req('index.php', sub, accept_json=True)
        mid = php_cli('probe.php', 'xp', acc['pupil1']['uid'])
        check('Finishing an activity gives its XP', mid['xp'] == before['xp'] + int(first['xp_reward']), f"{before} -> {mid}, reward {first['xp_reward']}")
        p.req('index.php', sub, accept_json=True)
        after = php_cli('probe.php', 'xp', acc['pupil1']['uid'])
        check('Sending the same activity again gives no extra XP', after == mid, f'{mid} -> {after}')
        if len(acts) > 1:
            nxt = acts[1]
            status, _, text = p.req('index.php', {'action': 'draft', 'activity_id': nxt['id'], 'response': 'half done', 'csrf': tok}, accept_json=True)
            check('Work in progress is saved as a draft', '"saved":true' in text.replace(' ', ''), f'status {status}: {text[:120]}')

    section('6. Pupil started at Level 6 (Grade 3): reader level head start')
    rl = php_cli('probe.php', 'reader', acc['pupil6']['uid'])
    check('Head start does not jump the pupil to reader Level 100', 1 < rl['level'] < 90, rl)
    check('A quarter of the head-start XP is given at the start', rl['headstart_now'] == rl['headstart_all'] // 4, rl)
    p6 = Client()
    status, url, _ = p6.login('pupil', acc['pupil6']['id'], acc['pupil6']['pw'])
    if check('Level 6 pupil can sign in', 'dashboard' in url, url):
        page_ok(p6, '?page=lessons', 'Level 6 pupil: My lessons opens')
        acts6 = php_cli('probe.php', 'first_activities', 7, 3)
        if check('Level 6 Grade 3 has lessons', acts6, acts6):
            page_ok(p6, f"?page=lesson&id={acts6[0]['lesson_id']}", 'Level 6 lesson start page opens')
            page_ok(p6, f"?page=lesson&id={acts6[0]['lesson_id']}&activity={acts6[0]['id']}", 'Level 6 activity page opens')

    section('7. Teacher')
    t = Client()
    status, url, _ = t.login('teacher', acc['teacher']['id'], acc['teacher']['pw'])
    if check('Teacher can sign in', 'dashboard' in url, url):
        uid = acc['pupil1']['uid']
        for pg in ('dashboard', 'manage', 'accounts', 'progress', 'review', 'sections', 'class_demo', 'notifications', 'cards',
                   'qcheck', 'guide', 'profile', f'pupil&id={uid}', f'report&id={uid}', 'certificates&level=1'):
            page_ok(t, f'?page={pg}', f'Teacher page opens: {pg}')
        status, _, _ = t.req('?page=health')
        check('A teacher cannot open an admin page', status == 403, f'status {status}')

    section('8. Admin (with PIN)')
    a = Client()
    a.login('admin', acc['admin']['id'], acc['admin']['pw'])
    tok = a.token('?page=login&role=admin&confirm=1')
    status, url, _ = a.req('index.php', {'action': 'admin_pin', 'pin': acc['admin']['pin'], 'csrf': tok})
    if check('Admin can sign in with password and PIN', 'dashboard' in url, url):
        for pg in ('dashboard', 'accounts', 'reports', 'content', 'activity_log', 'health', 'checkup', 'settings', 'teacher_cards',
                   'teacher_import', 'issues', 'media', 'guide', 'profile', 'notifications', 'visual_audit'):
            page_ok(a, f'?page={pg}', f'Admin page opens: {pg}')


def main():
    os.chdir(ROOT)
    print('BULIG automatic tests' + ('' if ROOT == os.path.dirname(HERE) else ' for ' + ROOT))
    test_files()
    built = test_database()
    try:
        if built:
            test_app()
    except Exception as e:  # a broken copy must end in a clear failure, not a crash
        check('BULIG could start and every test could run', False, e)
    finally:
        if built and not KEEP:
            mysql('-e', f"DROP DATABASE IF EXISTS `{DB['name']}`")
    failed = [r for r in results if not r[1]]
    print(f'\n{len(results) - len(failed)} passed, {len(failed)} failed' + ('' if not KEEP else f"  (test database `{DB['name']}` kept)"))
    sys.exit(1 if failed else 0)


if __name__ == '__main__':
    main()
