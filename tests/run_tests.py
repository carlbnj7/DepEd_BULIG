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
import csv, glob, http.cookiejar, io, json, os, re, socket, subprocess, sys, time, urllib.error, urllib.parse, urllib.request

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

# Every table a full install must have (the install file plus every update file).
EXPECTED_TABLES = sorted('''account_id_history activities activity_completion activity_drafts admin_security admins answers assessment_attempts
 assessment_questions assessments audit_log badges bulig_levels content_issues grade_levels help_requests id_sequences
 learning_days lessons login_attempts modules notifications pupil_badges pupil_cards pupil_details pupil_level_assignments
 pupil_progress pupil_rewards pupil_sections pupil_streaks pupil_xp pupils push_queue push_subscriptions questions
 reading_assessments reading_checks response_history app_ratings rewards saved_logins schema_migrations sections settings source_pages
 teacher_pupils teachers user_names users xp_transactions'''.split())
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
    _, _, text = anon.req('?page=login')
    m = re.search(r'data-lfx-pairs="([^"]+)"', text)
    pairs = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&')) if m else []
    pics = ' '.join(p['a'] + ' ' + p['b'] for p in pairs)
    check('Sign-in page: all 12 friends take turns, two at a time, with no name tags',
          len(pairs) == 6 and all(k in pics for k in ('george', 'toby', 'max', 'dracs', 'bruno', 'mia', 'zion', 'arah', 'roxanne', 'kai', 'sofie', 'claude')) and 'lfx-who' not in text, pics[:160])
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
    for pg in ('dashboard', 'lessons', 'achievements', 'board', 'calendar', 'profile', 'settings', 'notifications', 'offline', 'credits'):
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
        status, url, text = p.req(f"?page=lesson&id={first['lesson_id']}&activity={first['id']}")
        check('Lesson screen has the "I need help" button, for class and home', 'data-help-open' in text and 'in class or at home' in text)
        tok = p.token(f"?page=lesson&id={first['lesson_id']}&activity={first['id']}")
        back = f"?page=lesson&id={first['lesson_id']}&activity={first['id']}"
        status, url, text = p.req('index.php', {'action': 'help_request', 'reason': 'question', 'lesson_id': first['lesson_id'],
                                                 'activity_id': first['id'], 'back': back, 'csrf': tok})
        check('"I need help" tells the pupil their teacher got the message', 'Your teacher got your message' in text and 'come to you' not in text,
              f'status {status}, ended at {url}')
        if len(acts) > 1:
            nxt = acts[1]
            status, _, text = p.req('index.php', {'action': 'draft', 'activity_id': nxt['id'], 'response': 'half done', 'csrf': tok}, accept_json=True)
            check('Work in progress is saved as a draft', '"saved":true' in text.replace(' ', ''), f'status {status}: {text[:120]}')

    test_ratings_pupil(p)
    test_george(p)
    test_friends(p)
    test_notifications(p)
    test_level4_scoring()

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
            test_auto_check(p6, acc, acts6[0])

    section('7. Teacher')
    t = Client()
    status, url, _ = t.login('teacher', acc['teacher']['id'], acc['teacher']['pw'])
    if check('Teacher can sign in', 'dashboard' in url, url):
        uid = acc['pupil1']['uid']
        for pg in ('dashboard', 'manage', 'accounts', 'progress', 'review', 'sections', 'class_demo', 'notifications', 'cards',
                   'qcheck', 'guide', 'profile', f'pupil&id={uid}', f'report&id={uid}', f'report_card&id={uid}', 'certificates&level=1',
                   'print', 'moved', 'rcheck', 'rcheck_rules', 'rcheck_print', f'pupil&id={uid}&tab=answers', f'pupil&id={uid}&tab=rcheck',
                   f'pupil&id={uid}&tab=badges', f'pupil&id={uid}&tab=account', 'progress&section=0&level=1'):
            page_ok(t, f'?page={pg}', f'Teacher page opens: {pg}')
        status, _, text = t.req('?page=dashboard')
        check('Teacher Home has the grouped menu (MY CLASS, TEACHING, PRINT) and "Find a page"',
              all(g in text for g in ('>MY CLASS<', '>TEACHING<', '>PRINT<')) and 'data-find-input' in text and 'Needs you today' in text)
        check('Nothing waits for the teacher: no "Check answers", BULIG checks every answer, Class results in the menu',
              'Check answers' not in text and 'Answers to check' not in text and 'BULIG checks every answer' in text and 'href="?page=qcheck"' in text)
        _, url, _ = t.req(f"?page=review&pupil={acc['pupil1']['uid']}")
        check('Old "Check answers" links open the pupil’s Answers and scores', f"page=pupil&id={acc['pupil1']['uid']}&tab=answers" in url, url)
        _, _, ans = t.req(f"?page=pupil&id={acc['pupil1']['uid']}&tab=answers")
        check('A pupil’s Answers and scores lists their answers with BULIG’s score and the teacher’s tools',
              'Answers and scores' in ans and 'rv-item' in ans and 'Let the pupil do it again' in ans and not PHP_ERROR.search(ans))
        test_reading_check(t, acc)
        test_ratings_teacher(t)
        test_section_report(t)
        test_names(t)
        m = re.search(r'name="action" value="help_done"><input type="hidden" name="id" value="(\d+)"', text)
        check('Teacher Overview shows "Asked for help" with the pupil and "Mark as helped"', 'Asked for help' in text and 'Test needs help' in text and m)
        if m:
            tok = t.token('?page=dashboard')
            status, url, text = t.req('index.php', {'action': 'help_done', 'id': m.group(1), 'csrf': tok})
            check('"Mark as helped" marks the request as seen', 'Marked as helped' in text and ' · Seen' in text and 'value="help_done"' not in text,
                  f'status {status}, ended at {url}')
        blanks = php_cli('probe.php', 'blanks')
        check('Blanks: one line per missing letter ("n ___ t" with e is 1, "___ og" with fr is 2)', not blanks['wrong'], blanks['wrong'])
        h = blanks['html']
        check('Blanks: pupil card has letter lines the tiles fill, Class Demo shows the same lines',
              h['pupil'].count('<i></i>') == 1 and 'data-n="1"' in h['pupil'] and h['demo'].count('<i></i>') == 1, h)
        check('Blanks: a word blank is one long line, a blank inside a word stays as written', 'bl-w' in h['word'] and h['inword'] == 'technolog__ is', h)
        check('Blanks: teacher lists show "_ _ og" too', h['plain'] == '_ _ og', h['plain'])
        check(f"Blanks: every Level 2A, 2B and 3 card ({blanks['cards']}) shows one line per missing letter", blanks['cards'] and not blanks['left_count'],
              f"{blanks['left_count']} left: {blanks['left']}")
        status, _, text = t.req('?page=demo_offline')
        try:
            units = json.loads(text).get('units', [])
        except ValueError:
            units = []
        check('Class Demo can be saved for offline (list of levels)', status == 200 and units and all(u.get('pages') for u in units),
              f'status {status}, {len(units)} demos')
        status, _, _ = t.req('?page=health')
        check('A teacher cannot open an admin page', status == 403, f'status {status}')

    section('8. Admin (with PIN)')
    a = Client()
    a.login('admin', acc['admin']['id'], acc['admin']['pw'])
    tok = a.token('?page=login&role=admin&confirm=1')
    status, url, _ = a.req('index.php', {'action': 'admin_pin', 'pin': acc['admin']['pin'], 'csrf': tok})
    if check('Admin can sign in with password and PIN', 'dashboard' in url, url):
        for pg in ('dashboard', 'accounts', 'reports', 'activity_log', 'health', 'checkup', 'settings', 'teacher_cards',
                   'teacher_import', 'guide', 'profile', 'notifications', 'visual_audit', 'add_teachers',
                   'add_teachers&mode=many', 'print', 'moved'):
            page_ok(a, f'?page={pg}', f'Admin page opens: {pg}')
        _, url, text = a.req('?page=content')
        check('The Lesson studio is gone: its old link goes to Admin Home and the menu has no Lesson studio',
              'page=dashboard' in url and 'Lesson studio' not in text and 'href="?page=content"' not in text, url)
        _, _, text = a.req('index.php', {'action': 'save_activity', 'id': '1', 'title': 'Changed', 'csrf': a.token('?page=dashboard')})
        check('Module content cannot be changed by the administrator', 'Unknown action' in text or 'not found' in text.lower())
        status, _, text = a.req('?page=ratings&period=all')
        check('Reports › App ratings shows the pupils’ stars and the teachers’ evaluation',
              status == 200 and 'How do pupils and teachers rate BULIG?' in text and '1 pupil rated BULIG' in text and '1 teacher answered' in text
              and '4.2' in text and 'More practice for Level 3, please.' in text and not PHP_ERROR.search(text), f'status {status}')
        test_delete_teacher(a)
        test_account_ids(a, acc)


def jget(text):
    try:
        return json.loads(text)
    except ValueError:
        return {}


def test_auto_check(p6, acc, act):
    """BULIG checks every answer: the pupil sees the score and right answers at once and can try again (best score kept, no XP)."""
    section('6b. Automatic checking')
    ca = php_cli('probe.php', 'card_answers', act['id'])
    if not check('The first Level 6 activity has an answer key, so BULIG can mark it', ca.get('keyed') and ca.get('cards'), ca):
        return
    aid, page = str(act['id']), f"?page=lesson&id={act['lesson_id']}&activity={act['id']}"
    _, _, text = p6.req('index.php', {'action': 'submit', 'activity_id': aid, 'response': ca['wrong'], 'csrf': p6.token(page)}, accept_json=True)
    j = jget(text)
    check('Right after "Done", BULIG shows the score, the right answers and "Try it again"',
          j.get('saved') and all(x in j.get('check', '') for x in ('ac-panel', 'LOOK AGAIN', 'Try it again')), text[:200])
    xp = php_cli('probe.php', 'xp', acc['pupil6']['uid'])['xp']
    status, _, text = p6.req(page + '&again=1')
    check('"Try it again" opens a fresh copy for practice', 'name="again" value="1"' in text and 'ac-practice' in text, f'status {status}')
    _, _, text = p6.req('index.php', {'action': 'submit', 'activity_id': aid, 'again': '1', 'response': ca['right'], 'csrf': p6.token(page)}, accept_json=True)
    j = jget(text)
    check('A better practice try becomes the new best score', 'Better!' in j.get('message', '') and j.get('xp') == 0, text[:200])
    _, _, text = p6.req('index.php', {'action': 'submit', 'activity_id': aid, 'again': '1', 'response': ca['wrong'], 'csrf': p6.token(page)}, accept_json=True)
    check('A worse practice try keeps the best score', 'stays' in jget(text).get('message', ''), text[:200])
    check('Practice gives no extra XP', php_cli('probe.php', 'xp', acc['pupil6']['uid'])['xp'] == xp)
    _, _, text = p6.req(page)
    check('The finished activity shows BULIG’s result card with the best score', 'Perfect! Every answer is right.' in text and 'ac-panel' in text)


def test_ratings_pupil(p):
    """App ratings: a pupil rates BULIG with stars; "Not now" is kept."""
    section('5b. App ratings (pupil)')
    tok = p.token('?page=dashboard')
    _, _, text = p.req('index.php', {'action': 'rate_app', 'stars': '9', 'csrf': tok}, accept_json=True)
    check('Stars must be 1 to 5', '"ok":true' not in text.replace(' ', ''), text[:120])
    _, _, text = p.req('index.php', {'action': 'rate_app', 'stars': '4', 'csrf': tok}, accept_json=True)
    j = jget(text)
    check('A pupil can rate BULIG with stars', j.get('ok') and j.get('stars') == 4 and j.get('word') == 'I like it!', text[:160])
    _, _, text = p.req('index.php', {'action': 'rate_later', 'csrf': tok}, accept_json=True)
    check('"Not now" is saved', jget(text).get('ok'), text[:120])
    status, _, _ = p.req('?page=ratings')
    check('A pupil cannot open the administrator’s App ratings', status == 403, f'status {status}')


def test_george(p):
    """George the Explorer: the pupil's buddy and the tour guide; she says hello once."""
    section('5c. George the Explorer')
    for n in ('hello', 'thumbs', 'point', 'map', 'cheer'):
        check(f'George picture "{n}" is in place', os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', f'george-{n}.webp')))
    _, _, text = p.req('?page=dashboard')
    check('George is the reading buddy on the pupil Overview', 'pf-buddy-george' in text and 'george-hello.webp' in text, '')
    m = re.search(r'<meta name="bulig-tour" content="([^"]+)"', text)
    meta = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&')) if m else {}
    check('The tour has George’s poses', set((meta.get('guide') or {}).keys()) >= {'hello', 'point', 'map', 'thumbs', 'cheer'}, str(meta)[:160])
    tok = p.token('?page=dashboard')
    _, _, text = p.req('index.php', {'action': 'george_met', 'csrf': tok}, accept_json=True)
    _, _, page = p.req('?page=dashboard')
    m = re.search(r'<meta name="bulig-tour" content="([^"]+)"', page)
    met = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&')).get('met') if m else None
    check('George says hello only once (it is remembered)', jget(text).get('ok') and met is True, text[:120])


def test_friends(p):
    """Meet your BULIG friends: George and 8 friends, shown once; Claude's safety tip on Home."""
    section('5c2. Meet your BULIG friends')
    poses = {'max': 'mic', 'dracs': 'book', 'bruno': 'tryagain', 'mia': 'blocks', 'toby': 'flag', 'zion': 'emblem', 'arah': 'calendar', 'roxanne': 'time', 'kai': 'letter', 'sofie': 'mirror'}
    missing = [f'{k}-{x}' for k, sp in poses.items() for x in ('hello', 'point', 'thumbs', 'cheer', sp)
               if not os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', f'{k}-{x}.webp'))]
    missing += [f'claude-{x}' for x in ('hello', 'thumbs', 'shield', 'worried', 'sad')
                if not os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', f'claude-{x}.webp'))]
    check('All 55 pictures of the 11 friends are in place', not missing, missing)
    _, _, text = p.req('?page=dashboard')
    m = re.search(r'<meta name="bulig-friends" content="([^"]+)"', text)
    data = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&').replace('&#039;', "'")) if m else {}
    names = [f.get('name') for f in data.get('friends', [])]
    check('A pupil who has not met the friends gets the introduction: George first, then 11 friends', names[:1] == ['George'] and len(names) == 12 and 'Kai' in names and 'Sofie' in names and 'assets/friends' in text, names)
    check('Claude the Protector gives a safety tip on the pupil Home page', 'class="cl-tip"' in text and 'claude-' in text)
    check('Roxanne the Timekeeper is on Today’s goal', 'class="rx-goal"' in text and 'roxanne-' in text)
    m = re.search(r'<meta name="bulig-tour" content="([^"]+)"', text)
    tour = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&')) if m else {}
    pics = ' '.join([str(tour.get('char')), str(tour.get('hi'))] + list((tour.get('pals') or {}).values()))
    check('Outside the ladder and the lesson map, friends stand in for the pupil’s boy or girl picture',
          'george-' in pics and 'toby-' in pics and 'claude-' in pics and 'roxanne-' in pics and 'boy' not in pics and 'girl' not in pics, pics[:200])
    _, _, cal = p.req('?page=calendar')
    _, _, ach = p.req('?page=achievements')
    _, _, brd = p.req('?page=board')
    _, _, st = p.req('?page=settings')
    check('Each friend is on their own page: Arah on My calendar, Zion and Toby on My achievements, Toby on the board, Claude in Settings, Kai on Notifications, Sofie on My profile',
          'fs-say fs-arah' in cal and 'fs-say fs-zion' in ach and 'fs-say fs-toby' in ach and 'fs-say fs-toby' in brd and 'fs-say fs-claude' in st and 'fs-say fs-kai' in p.req('?page=notifications')[2] and 'fs-say fs-sofie' in p.req('?page=profile')[2])
    check('The rank emblems are the new ones (loaded fresh)', 'images/ranks/' in ach and '.webp?v=2' in ach)
    missing = [x for x in ('roxanne', 'arah', 'george', 'bruno', 'zion', 'dracs', 'claude', 'max', 'toby', 'kai', 'sofie') if not os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters/push', x + '.png'))]
    sw = open(os.path.join(ROOT, 'public/sw.js')).read()
    check('Phone notifications show the friend: 11 friend icons, and the service worker uses the icon sent', not missing and 'icon:d.icon||' in sw, missing)
    _, _, r = p.req('index.php', {'action': 'friends_met', 'csrf': p.token('?page=dashboard')}, accept_json=True)
    _, _, text = p.req('?page=dashboard')
    check('The introduction shows only once (it is remembered)', jget(r).get('ok') and 'name="bulig-friends"' not in text, r[:120])


def test_notifications(p):
    """Notifications page: All / Unread, mark one as read or unread, delete one, mark all as read, delete all read."""
    section('5c3. Notifications page')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    uid = q("SELECT id FROM users WHERE role='pupil' ORDER BY id LIMIT 1")
    _, _, text = p.req('?page=dashboard')
    me = re.search(r'name="bulig-uid" content="(\d+)"', text)
    uid = me.group(1) if me else uid
    q(f"DELETE FROM notifications WHERE user_id={int(uid)}")
    q(f"INSERT INTO notifications(user_id,kind,title) VALUES({int(uid)},'badge','Test one'),({int(uid)},'note','Test two'),({int(uid)},'note','Test three')")
    ids = q(f"SELECT GROUP_CONCAT(id ORDER BY id) FROM notifications WHERE user_id={int(uid)}").split(',')
    _, _, text = p.req('?page=notifications')
    check('Notifications is in the pupil menu, with All and Unread', 'href="?page=notifications"' in text and 'class="nt-tabs"' in text and 'Mark as read' in text and 'Delete' in text)
    tok = lambda: p.token('?page=notifications')
    p.req('index.php', {'action': 'notif_mark', 'id': ids[0], 'to': 'read', 'csrf': tok()})
    check('Mark as read', q(f'SELECT read_at IS NOT NULL FROM notifications WHERE id={int(ids[0])}') == '1')
    p.req('index.php', {'action': 'notif_mark', 'id': ids[0], 'to': 'unread', 'csrf': tok()})
    check('Mark as unread', q(f'SELECT read_at IS NULL FROM notifications WHERE id={int(ids[0])}') == '1')
    p.req('index.php', {'action': 'notif_delete', 'id': ids[1], 'csrf': tok()})
    check('Delete one', q(f'SELECT COUNT(*) FROM notifications WHERE id={int(ids[1])}') == '0')
    other = q("SELECT id FROM notifications WHERE user_id<>%d LIMIT 1" % int(uid))
    if other:
        p.req('index.php', {'action': 'notif_delete', 'id': other, 'csrf': tok()})
        check('Someone else’s notification cannot be deleted', q(f'SELECT COUNT(*) FROM notifications WHERE id={int(other)}') == '1')
    p.req('index.php', {'action': 'notif_read_all', 'csrf': tok()})
    check('Mark all as read', q(f'SELECT COUNT(*) FROM notifications WHERE user_id={int(uid)} AND read_at IS NULL') == '0')
    p.req('index.php', {'action': 'notif_delete_read', 'csrf': tok()})
    check('Delete all read', q(f'SELECT COUNT(*) FROM notifications WHERE user_id={int(uid)}') == '0')


def test_delete_pupil(t):
    """A teacher deletes a pupil for good (typing DELETE); their work goes with them."""
    section('7g. Delete a pupil')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    pid = q("SELECT id FROM users WHERE name='Gina Uy'")
    _, _, text = t.req(f'?page=pupil&id={pid}&tab=account')
    check('The pupil Account tab has "Delete this pupil"', 'Delete this pupil' in text and 'value="delete_pupil"' in text)
    _, _, text = t.req('index.php', {'action': 'delete_pupil', 'id': pid, 'confirm': 'yes', 'csrf': t.token('?page=dashboard')})
    check('Without typing DELETE nothing is deleted', q(f'SELECT COUNT(*) FROM users WHERE id={int(pid)}') == '1' and 'Type DELETE' in text)
    q(f"INSERT INTO learning_days(pupil_id,day) VALUES({int(pid)},CURDATE())")
    _, _, text = t.req('index.php', {'action': 'delete_pupil', 'id': pid, 'confirm': 'delete', 'csrf': t.token('?page=dashboard')})
    left = q(f"SELECT (SELECT COUNT(*) FROM users WHERE id={int(pid)})+(SELECT COUNT(*) FROM pupils WHERE user_id={int(pid)})+(SELECT COUNT(*) FROM pupil_sections WHERE pupil_id={int(pid)})+(SELECT COUNT(*) FROM teacher_pupils WHERE pupil_id={int(pid)})+(SELECT COUNT(*) FROM learning_days WHERE pupil_id={int(pid)})")
    check('Typing DELETE deletes the pupil and everything that belongs to them', left == '0' and 'was deleted' in text, left)


def test_delete_teacher(a):
    """The administrator deletes a teacher only when they have no pupils and no sections."""
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    busy = q("SELECT teacher_id FROM teacher_pupils LIMIT 1")
    _, _, text = a.req('index.php', {'action': 'delete_teacher', 'id': busy, 'confirm': 'DELETE', 'csrf': a.token('?page=accounts')})
    check('A teacher with pupils cannot be deleted', q(f'SELECT COUNT(*) FROM users WHERE id={int(busy)}') == '1' and 'still has pupils' in text)
    a.req('index.php', {'action': 'create_account', 'role': 'teacher', 'first_name': 'Temp', 'last_name': 'Delete', 'teacher_sex': 'male', 'password': 'TempDelete42!', 'back': 'add_teachers', 'csrf': a.token('?page=add_teachers')})
    tid = q("SELECT id FROM users WHERE name='Temp Delete' AND role='teacher'")
    _, _, text = a.req('index.php', {'action': 'delete_teacher', 'id': tid, 'confirm': 'DELETE', 'csrf': a.token('?page=accounts')})
    check('A teacher with no pupils and no sections can be deleted', tid != '' and q(f"SELECT COUNT(*) FROM users WHERE name='Temp Delete'") == '0' and 'was deleted' in text, tid)


def test_level4_scoring():
    """Level 4: BULIG counts the module's miscues from what the speech recognizer heard."""
    section('5d. Level 4 reading scored by BULIG')
    r = php_cli('probe.php', 'l4', 0)
    check('A sample reading gives the right miscues (2 mispronunciations, 1 omission, 1 substitution, 1 repetition, 1 transposition)', r.get('sample') == [2, 1, 1, 0, 1, 1, 0], str(r.get('sample')))
    check('"was" for "saw" is a reversal', r.get('reversal') == 1, str(r.get('reversal')))
    check('Too little heard: the teacher scores it instead', r.get('too_little') is True, str(r.get('too_little')))
    check('A perfect reading of every Level 4 passage (Grades 1-6) scores 100%', r.get('passages', 0) >= 60 and not r.get('not_100'), f"{r.get('passages')} passages, not 100%: {r.get('not_100')}")


def test_section_report(t):
    """Section data report: a teacher prints all the results of one section in one level (Print centre)."""
    section('7d. Section data report')
    status, _, text = t.req('?page=print')
    m = re.search(r'id="pc-sd".*?<select name="section"><option value="(\d+)"', text, re.S)
    check('Print centre has the Section data report card', status == 200 and m is not None, f'status {status}')
    if not m:
        return
    sid = m.group(1)
    status, _, text = t.req(f'?page=section_report&section={sid}&level=5&p[]=sum&p[]=pp&p[]=irl&p[]=rt&p[]=help&p[]=act')
    check('Level 4 report: six pages with the summary and the Phil-IRI profile', status == 200 and text.count('class="sd-page"') == 6 and 'Section Level Summary' in text and 'Phil-IRI Class Reading Profile' in text, f'status {status}')
    status, _, text = t.req(f'?page=section_report&section={sid}&level=6&p[]=sum&p[]=irl&p[]=rt')
    check('Other levels skip the Phil-IRI page and list the questions to re-teach', status == 200 and text.count('class="sd-page"') == 2 and 'Phil-IRI Class Reading Profile' not in text, f'status {status}')
    status, _, _ = t.req('?page=section_report&section=999999&level=5')
    check('A section of another teacher cannot be printed', status in (403, 404), f'status {status}')


def test_names(t):
    """Names in parts: First, Middle, Last and Extension for one pupil, and "Type the list" for a whole class."""
    section('7e. Names in parts and "Type the list"')
    status, _, text = t.req('?page=accounts')
    check('Add one pupil asks for First, Middle, Last name and Extension', status == 200 and all(f'name="{n}"' in text for n in ('first_name', 'middle_name', 'last_name', 'name_ext')) and 'Shows as' in text and 'name="name"' not in text, f'status {status}')
    status, _, text = t.req('?page=accounts&mode=class')
    m = re.search(r'class="bk-onesec"><label>Section<select name="section_id" required data-sx-select><option value="">Choose a section</option><option value="(\d+)"', text)
    check('A whole class opens on "Type the list" (no paste option) with the Upload a file tab', status == 200 and m is not None and 'data-bk-row' in text and 'Upload a file' in text and 'Paste' not in text, f'status {status}')
    if not m:
        return
    sid = m.group(1)
    tok = t.token('?page=accounts')
    t.req('index.php', {'action': 'create_account', 'first_name': 'rosa', 'middle_name': 'Lim', 'last_name': 'DE LA PAZ', 'name_ext': 'Jr.', 'sex': 'female', 'section_id': sid, 'level_id': '1', 'csrf': tok})
    got = mysql('-N', '-e', "SELECT CONCAT_WS('|',u.name,n.first_name,n.middle_name,n.last_name,n.name_ext) FROM users u JOIN user_names n ON n.user_id=u.id WHERE n.last_name='De La Paz'", db=DB['name']).stdout.strip()
    check('One pupil is saved as "Rosa L. De La Paz Jr." and the name parts are kept', got == 'Rosa L. De La Paz Jr.|Rosa|Lim|De La Paz|Jr.', got)
    rows = {'action': 'import_typed', 'section_id': sid, 'level_id': '1', 'csrf': t.token('?page=accounts&mode=class')}
    for i, r in enumerate([('Tan', 'Leo', 'Uy', 'sr', 'M', ''), ('Tan', 'Leo', 'Uy', 'sr', 'M', ''), ('', '', '', '', 'F', '')]):
        for k, v in zip(('last', 'first', 'middle', 'ext', 'sex', 'lrn'), r):
            rows[f't[{k}][{i}]'] = v
    _, url, text = t.req('index.php', rows)
    made = mysql('-N', '-e', "SELECT COUNT(*) FROM users WHERE name='Leo U. Tan Sr.'", db=DB['name']).stdout.strip()
    check('A typed list with a row to fix comes back with the rows and the note; nothing is saved', 'how=type' in url and 'value="Tan"' in text and 'row 1 of this list' in text and made == '0', url)
    rows['t[first][1]'] = 'Lea'
    rows['t[sex][1]'] = 'F'
    rows['csrf'] = t.token('?page=accounts&mode=class')
    _, url, text = t.req('index.php', rows)
    made = mysql('-N', '-e', "SELECT GROUP_CONCAT(CONCAT(u.name,'/',IFNULL(n.name_ext,'')) ORDER BY u.name) FROM users u JOIN user_names n ON n.user_id=u.id WHERE n.last_name='Tan'", db=DB['name']).stdout.strip()
    check('Fixed, the typed list makes the accounts (an empty row with only F picked is skipped)', 'import=done' in url and '2 pupil accounts created' in text and made == 'Lea U. Tan Sr./Sr.,Leo U. Tan Sr./Sr.', made or url)
    _, _, text = t.req('?page=import_template')
    check('The class list template has an Extension column', text.lstrip('\ufeff').startswith('Last name,First name,Middle name,Extension,'), text[:80])
    test_sections(t, sid)
    test_delete_pupil(t)


def test_sections(t, sid):
    """Sections: add (Grade first), no doubles, remove only when empty, "+ Add a new section" from Add pupils,
    and the typed list with a section on each row and a starting level on each row."""
    section('7f. Sections')
    status, _, text = t.req('?page=sections')
    check('Sections page: Grade first, then the name, and one card per section with Add pupils and Remove',
          status == 200 and text.index('data-sx-grade') < text.index('data-sx-name') and 'class="sx-card"' in text and 'class="sx-go"' in text and 'Remove' in text, f'status {status}')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    tok = t.token('?page=sections')
    t.req('index.php', {'action': 'save_section', 'grade_level': '4', 'name': 'acacia', 'csrf': tok})
    new = q("SELECT id FROM sections WHERE grade_level=4 AND name='Acacia'")
    check('A new section is saved with a capital letter (Grade 4 · Acacia)', new != '', new)
    _, _, text = t.req('index.php', {'action': 'save_section', 'grade_level': '4', 'name': 'ACACIA', 'csrf': t.token('?page=sections')})
    check('The same section cannot be added twice', q("SELECT COUNT(*) FROM sections WHERE grade_level=4 AND LOWER(name)='acacia'") == '1' and 'You already have Grade 4' in text)
    busy = q(f"SELECT section_id FROM pupil_sections LIMIT 1")
    _, _, text = t.req('index.php', {'action': 'delete_section', 'id': busy, 'csrf': t.token('?page=sections')})
    check('A section with pupils cannot be removed', q(f"SELECT COUNT(*) FROM sections WHERE id={int(busy)}") == '1' and 'still has pupils' in text)
    t.req('index.php', {'action': 'delete_section', 'id': new, 'csrf': t.token('?page=sections')})
    check('An empty section can be removed', q(f"SELECT COUNT(*) FROM sections WHERE id={int(new)}") == '0')
    t.req('index.php', {'action': 'create_account', 'first_name': 'Gina', 'last_name': 'Uy', 'sex': 'female', 'section_id': 'new', 'new_grade': '2', 'new_section': 'narra', 'level_id': '1', 'csrf': t.token('?page=accounts')})
    got = q("SELECT CONCAT(s.grade_level,' ',s.name) FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN sections s ON s.id=ps.section_id WHERE u.name='Gina Uy'")
    check('"+ Add a new section" in Add one pupil makes the section and puts the pupil in it', got == '2 Narra', got)
    narra = q("SELECT id FROM sections WHERE grade_level=2 AND name='Narra'")
    rows = {'action': 'import_typed', 'sec_mode': 'many', 'level_id': '1', 'csrf': t.token('?page=accounts&mode=class')}
    for i, r in enumerate([('Vega', 'Ivy', 'F', sid, '4'), ('Wong', 'Jay', 'M', narra, '1')]):
        for k, v in zip(('last', 'first', 'sex', 'sec', 'level'), r):
            rows[f't[{k}][{i}]'] = v
    _, url, _ = t.req('index.php', rows)
    got = q("SELECT GROUP_CONCAT(CONCAT(u.name,'/',ps.section_id,'/',a.level_id) ORDER BY u.name) FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN pupil_level_assignments a ON a.pupil_id=u.id WHERE u.name IN ('Ivy Vega','Jay Wong')")
    check('Type the list, pupils in different sections: each pupil gets their own section and starting level',
          'import=done' in url and got == f'Ivy Vega/{sid}/4,Jay Wong/{narra}/1', got or url)


def test_ratings_teacher(t):
    """App ratings: a teacher answers the 5-question evaluation from My profile › Rate BULIG."""
    section('7c. App ratings (teacher)')
    status, _, text = t.req('?page=profile')
    check('My profile has "Rate BULIG"', 'href="?page=rate"' in text, f'status {status}')
    status, _, text = t.req('?page=rate')
    check('Rate BULIG shows the five questions with stars', status == 200 and text.count('data-rt-stars') == 5 and 'Easy to use' in text, f'status {status}')
    tok = t.token('?page=rate')
    _, _, text = t.req('index.php', {'action': 'rate_app', 'q1': '5', 'q2': '4', 'q3': '5', 'csrf': tok}, accept_json=True)
    check('Every question needs stars', '"ok":true' not in text.replace(' ', ''), text[:120])
    _, _, text = t.req('index.php', {'action': 'rate_app', 'q1': '5', 'q2': '4', 'q3': '5', 'q4': '4', 'q5': '3', 'comment': 'More practice for Level 3, please.', 'csrf': tok}, accept_json=True)
    j = jget(text)
    check('A teacher can send the evaluation (overall = the average, 4.2)', j.get('ok') and float(j.get('overall', 0)) == 4.2, text[:160])
    _, _, text = t.req('?page=rate')
    check('Rate BULIG then shows when the teacher last rated', 'You last rated BULIG on' in text and '4.2' in text)


def test_reading_check(t, acc):
    """Reading check: a pupil with no level takes it at the first sign-in, BULIG places them, the teacher can change the level."""
    section('7b. Reading check')
    r = acc.get('pupilR')
    if not check('Test pupil with no starting level was added', r and r.get('uid'), r):
        return
    p = Client()
    _, url, text = p.login('pupil', r['id'], r['pw'])
    check('A pupil with no starting level goes to the reading check first', 'rcheck' in url and 'reading level' in text, url)
    _, url, _ = p.req('?page=lessons')
    check('Other pupil pages wait until the reading check is done', 'rcheck' in url, url)
    _, url, text = p.req('index.php', {'action': 'rc_begin', 'csrf': p.token('?page=rcheck')})
    tok = re.search(r'name="rc_token" value="([^"]+)"', text)
    check('Part 1 (Talk and listen) opens', 'TALK AND LISTEN' in text and tok, url)
    if not tok:
        return
    talk = 'BULIG card answers\n' + '\n'.join(f'Card {n}: (voice recording)' for n in range(1, 6))
    _, _, text = p.req('index.php', {'action': 'rc_submit', 'rc_token': tok.group(1), 'response': talk, 'csrf': p.token('?page=rcheck')}, accept_json=True)
    _, _, text = p.req('?page=rcheck')
    tok = re.search(r'name="rc_token" value="([^"]+)"', text)
    check('After Part 1 comes Part 2 (Sound games)', 'SOUND GAMES' in text and tok, text[:0])
    if not tok:
        return
    wrong = 'BULIG card answers\n' + '\n'.join(f'Card {n}: zzz' for n in range(1, 6))
    p.req('index.php', {'action': 'rc_submit', 'rc_token': tok.group(1), 'response': wrong, 'csrf': p.token('?page=rcheck')}, accept_json=True)
    _, url, text = p.req('?page=rcheck')
    check('Five wrong sound games end the check at Level 2A (the pupil cannot reach 8 of 10)', 'YOU START AT' in text and 'Level 2A' in text, url)
    lv = mysql('-N', '-e', f"SELECT level_id FROM pupil_level_assignments WHERE pupil_id={int(r['uid'])}", db=DB['name']).stdout.strip()
    check('BULIG saved the starting level (Level 2A)', lv == '2', lv)
    _, url, _ = p.req('?page=lessons')
    check('After the check the pupil can open the lessons', 'page=lessons' in url and 'rcheck' not in url, url)
    status, _, text = t.req('?page=rcheck')
    check('Teacher sees the pupil and the level on Reading check', status == 200 and 'Test Pupil Check' in text and 'Level 2A' in text, f'status {status}')
    page_ok(t, f"?page=rcheck_pupil&id={r['uid']}", 'Teacher opens one pupil’s reading check')
    t.req('index.php', {'action': 'rc_level', 'id': str(r['uid']), 'level_id': '3', 'note': 'Test note', 'csrf': t.token(f"?page=rcheck_pupil&id={r['uid']}")})
    lv = mysql('-N', '-e', f"SELECT level_id FROM pupil_level_assignments WHERE pupil_id={int(r['uid'])}", db=DB['name']).stdout.strip()
    check('Teacher can change the starting level (now Level 2B)', lv == '3', lv)
    status, _, text = t.req('?page=rcheck_pupil&id=' + str(acc['pupil6']['uid']))
    check('A pupil who did not take the check shows a clear message, not an error', status in (200, 302, 303) and not PHP_ERROR.search(text), f'status {status}')


def test_account_ids(a, acc):
    """New account IDs: new accounts get them, old accounts are switched after a backup, old IDs still sign in, undo works."""
    section('9. Account IDs')
    status, _, text = a.req('?page=settings&tab=ids')
    check('Settings › Account IDs opens', status == 200 and 'School code' in text and not PHP_ERROR.search(text), f'status {status}')
    pid = acc['pupil1']['id']
    check('A new pupil gets a new-style ID (school code + year + number)', re.fullmatch(r'[A-Z]{3,10}\d{6,}', pid or '') is not None, pid)
    a.req('index.php', {'action': 'admin_school_code', 'school_code': 'tces', 'csrf': a.token('?page=settings&tab=ids')})
    # Like many live servers: users and the history table compare text differently (MySQL error 1267 before the fix).
    coll = "SELECT collation_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='%s' AND column_name='%s'"
    users_coll = mysql('-N', '-e', coll % ('users', 'public_id'), db=DB['name']).stdout.strip()
    other = 'utf8mb4_unicode_ci' if users_coll != 'utf8mb4_unicode_ci' else 'utf8mb4_general_ci'
    mysql('-e', f'ALTER TABLE account_id_history CONVERT TO CHARACTER SET utf8mb4 COLLATE {other}', db=DB['name'])
    a.req('index.php', {'action': 'admin_ids_switch', 'school_code': 'TCES', 'csrf': a.token('?page=settings&tab=ids')})
    hist_coll = mysql('-N', '-e', coll % ('account_id_history', 'old_id'), db=DB['name']).stdout.strip()
    check('The switch works when the two tables compare text differently (and makes them match)', hist_coll == users_coll, f'{users_coll} / {hist_coll}')
    _, _, listing = a.req('?page=ids_csv')
    rows = {r[1]: r for r in csv.reader(io.StringIO(listing.lstrip('\ufeff'))) if len(r) >= 6}
    teacher = rows.get('Test Teacher', ['', '', '', '', '', ''])
    check('Old accounts were switched: teacher T2099001 is now TCEST0001', teacher[4] == 'TCEST0001' and teacher[5] == 'T2099001', teacher)
    admin = rows.get('Test Admin', ['', '', '', '', '', ''])
    check('The admin got an ID like A260001', re.fullmatch(r'A\d{6,}', admin[4]) is not None, admin)
    backups = sorted(glob.glob(os.path.join(ROOT, 'storage', 'backups', 'bulig-before-new-ids-*.sql*')))
    ok = False
    if backups:
        import gzip
        with (gzip.open if backups[-1].endswith('.gz') else open)(backups[-1], 'rt', encoding='utf-8', errors='replace') as f:
            ok = 'CREATE TABLE `users`' in f.read()
    check('A backup copy of the database was saved before the switch', ok, backups[-1:] if backups else 'no backup file')
    for label, who, pw in (('new teacher ID', 'TCEST0001', acc['teacher']['pw']), ('new ID in small letters', 'tcest0001', acc['teacher']['pw']),
                           ('old teacher ID', 'T2099001', acc['teacher']['pw'])):
        _, url, _ = Client().login('teacher', who, pw)
        check(f'Teacher signs in with the {label}', 'dashboard' in url, url)
    _, url, _ = Client().login('pupil', pid, acc['pupil1']['pw'])
    check('Pupil still signs in with the same ID and password', 'dashboard' in url, url)
    a.req('index.php', {'action': 'admin_ids_undo', 'csrf': a.token('?page=settings&tab=ids')})
    _, url_old, _ = Client().login('teacher', 'T2099001', acc['teacher']['pw'])
    _, url_new, _ = Client().login('teacher', 'TCEST0001', acc['teacher']['pw'])
    check('Undo gives the old IDs back', 'dashboard' in url_old and 'dashboard' not in url_new, f'{url_old} / {url_new}')
    for f in glob.glob(os.path.join(ROOT, 'storage', 'backups', 'bulig-*.sql*')):
        os.remove(f)


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
