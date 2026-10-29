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
EXPECTED_TABLES = sorted('''admin_messages message_recipients account_id_history activities activity_completion activity_drafts admin_security admins answers assessment_attempts
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
        body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
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
    _, _, page = Client().req('?page=login')
    check('Sign-in page: one form for everyone (no pupil or teacher cards, no Admin button)',
          'name="role" value="auto"' in page and 'lgn-roles' not in page and 'admin-pill' not in page)
    _, url, text = Client().login('auto', acc['teacher']['id'], acc['teacher']['pw'])
    check('One form: BULIG knows a teacher from the ID', 'dashboard' in url and 'role-teacher' in text, url)
    _, url, text = Client().login('auto', acc['admin']['id'], acc['admin']['pw'])
    check('One form: BULIG knows the administrator from the ID and asks for the PIN', 'data-pin-form' in text, url)
    r = php_cli('probe.php', 'tries', 0)
    check('Each wrong try takes away a third of the card (1 wrong try: 0.67, 2: 0.33, 3: 0)', r.get('ok') and r.get('pts') == [1, 0.67, 0.33, 0], str(r))
    r = php_cli('probe.php', 'level3ab', 0)
    check('Levels climb 1, 2A, 2B, 3A, 3B, 4, 5, 6, 7 (Level 3B comes before Level 4)', r.get('order') == [1, 2, 3, 4, 9, 5, 6, 7, 8] and r.get('labels') == ['Level 3A', 'Level 3B'] and r.get('next4') == 9 and r.get('next9') == 5, str(r.get('order')))
    check('Level 3A has the pre-test, CVC and word family lessons and its post-test; 3B has the blends and sight words', r.get('l3a') == list(range(1, 12)) + [27] and r.get('l3b') == [1] + list(range(12, 27)) + [27], str(r.get('l3a')) + ' / ' + str(r.get('l3b')))
    check('Level 3B pre-test: Task 1 Consonant Blends, Task 2 Sight Words; its cards and answer keys are found', r.get('pre3b') == ['Task 1: Consonant Blends', 'Task 2: Sight Words'] and (r.get('key3b') or {}).get('key', '').startswith('4:0:12:') and (r.get('key3b') or {}).get('cards', 0) > 0, str(r.get('pre3b')) + ' ' + str(r.get('key3b')))
    r = php_cli('probe.php', 'sf1', 0)
    check('DepEd SF1 (.xls from LIS): grade and section are read from above the table', r.get('class') == {'grade': 3, 'section': 'SAMPAGUITA'}, str(r.get('class')))
    check('DepEd SF1: the LRN, Name and Sex columns are found', r.get('cols', {}).get('lrn') == 0 and r['cols'].get('name') == 2 and r['cols'].get('sex') == 6, str(r.get('cols')))
    check('DepEd SF1: "LAST,FIRST JR, MIDDLE" names are split; total lines and the legend are left out',
          r.get('names') == [['100000000001', ['Juan', 'Santos', 'Dela Cruz', 'Jr.']], ['100000000002', ['Pedro', 'Reyes', 'Santos', '']], ['100000000003', ['Maria Clara', '', 'Cañon', '']]], str(r.get('names')))

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
    test_jeepy(p)
    test_story(p)
    test_notifications(p)
    test_whats_new(p)
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
            test_star_gate(p6, acc)
            test_done_scores()
            test_word_coach()
            test_castle(p6, acc)
            test_cycles(acc)

    section('7. Teacher')
    t = Client()
    status, url, _ = t.login('teacher', acc['teacher']['id'], acc['teacher']['pw'])
    if check('Teacher can sign in', 'dashboard' in url, url):
        uid = acc['pupil1']['uid']
        for pg in ('dashboard', 'manage', 'accounts', 'progress', 'review', 'sections', 'class_demo', 'notifications', 'cards',
                   'qcheck', 'guide', 'profile', f'pupil&id={uid}', f'report&id={uid}', f'report_card&id={uid}', 'certificates&level=1',
                   'print', 'moved', 'rcheck', 'rcheck_rules', 'rcheck_print', f'pupil&id={uid}&tab=answers', f'pupil&id={uid}&tab=rcheck',
                   f'pupil&id={uid}&tab=badges', f'pupil&id={uid}&tab=account', 'progress&section=0&level=1', 'dashboard&lb_level=2&lb_period=week'):
            page_ok(t, f'?page={pg}', f'Teacher page opens: {pg}')
        status, _, text = t.req('?page=dashboard')
        check('Teacher Home has the grouped menu (MY CLASS, TEACHING, PRINT) and "Find a page"',
              all(g in text for g in ('>MY CLASS<', '>TEACHING<', '>PRINT<')) and 'data-find-input' in text and 'Needs you today' in text)
        check('Teacher Home shows the Top 10 pupils', 'Top 10 pupils' in text and 'id="tf-top"' in text)
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
        status, _, _ = t.req('?page=checkup')
        check('A teacher cannot open an admin page', status == 403, f'status {status}')

    section('8. Admin (with PIN)')
    a = Client()
    a.login('admin', acc['admin']['id'], acc['admin']['pw'])
    tok = a.token('?page=login&role=admin&confirm=1')
    status, url, _ = a.req('index.php', {'action': 'admin_pin', 'pin': acc['admin']['pin'], 'csrf': tok})
    if check('Admin can sign in with password and PIN', 'dashboard' in url, url):
        for pg in ('dashboard', 'accounts', 'reports', 'activity_log', 'checkup', 'settings', 'teacher_cards',
                   'teacher_import', 'guide', 'profile', 'notifications', 'visual_audit', 'add_teachers',
                   'add_teachers&mode=many', 'print', 'moved', 'admin_pass', 'story'):
            page_ok(a, f'?page={pg}', f'Admin page opens: {pg}')
        _, url, _ = a.req('?page=health')
        _, _, text = a.req('?page=settings')
        _, _, home = a.req('?page=dashboard')
        check('System health and Badges & rewards are gone: old link goes to Print & download, no menu link, no Settings tab',
              'page=print' in url and 'href="?page=health' not in home and 'System health' not in home and 'tab=badges' not in text, url)
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
        test_schools(a, acc)
        test_bug_reports(a)
    test_messages(acc)


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


def test_star_gate(p6, acc):
    """Stars (v181): a lesson activity below 2 stars (80%) is not finished; the pupil sees the stars and tries again."""
    section('6c. Stars and the 80% rule')
    st = php_cli('probe.php', 'star_setup', acc['pupil6']['uid'])
    if not check('A lesson activity with an answer key is open for the pupil', st.get('id'), st):
        return
    aid, page = str(st['id']), f"?page=lesson&id={st['lesson_id']}&activity={st['id']}"
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    _, _, text = p6.req('index.php', {'action': 'submit', 'activity_id': aid, 'response': st['wrong'], 'csrf': p6.token(page)}, accept_json=True)
    j = jget(text)
    check('Below 2 stars the activity is not finished: stars, score and Try again', j.get('saved') is False and 'sg-panel' in j.get('check', '') and 'Try again' in j.get('check', '') and '2 stars' in j.get('message', ''), text[:300])
    check('The activity stays open (not finished) after a low score', q(f"SELECT status FROM activity_completion WHERE pupil_id={acc['pupil6']['uid']} AND activity_id={aid}") == 'retry')
    _, _, text = p6.req('index.php', {'action': 'submit', 'activity_id': aid, 'response': st['right'], 'csrf': p6.token(page)}, accept_json=True)
    j = jget(text)
    check('With 80% or more the activity is finished and shows 3 stars', j.get('saved') is True and 'aria-label="3 of 3 stars"' in j.get('check', ''), text[:300])
    check('The finished activity counts as done', q(f"SELECT status FROM activity_completion WHERE pupil_id={acc['pupil6']['uid']} AND activity_id={aid}") == 'completed')


def test_done_scores():
    """v183: Level 1 "Done" activities (actions, games, pair work) and drawings have nothing to mark: no 0-of-1 score, so no 0 stars."""
    section('6c-2. Done activities count as done')
    r = php_cli('probe.php', 'done_score', 0)
    check('A "Done" activity, a drawing and a page to read get no score (not 0 stars)', r.get('perform') is None and r.get('drawing') is None and r.get('none') is None, r)
    check('Level 1 shows its Post-test card (post-assessments inside the lessons)', r.get('card') is True, r)


def test_word_coach():
    """v184: Word Coach: Max reads first, then word by word; fewer tries, more points. Its result rides in the answer."""
    section('6c-3. Word Coach')
    r = php_cli('probe.php', 'wc_check', 0)
    check('The coaching result is saved in the answer before the tries line', r.get('line') is True, r)
    check('BULIG reads the coaching back (score and words to practise)', r.get('p1') == 40 and r.get('w1') == ['cat'] and r.get('p2') is None, r)
    check('The card answers stay the same with the coaching line', r.get('cards') == {'1': '(voice recording) said: cat hat', '2': 'x'} and r.get('strip') is True, r)
    check('Lessons, pre-tests and post-tests all get the coach', r.get('learn_on') is True and r.get('test_off') is True, r)


def test_castle(p6, acc):
    """Reading Castle (v182): after Level 7 the castle scene opens once; ?castle=1 opens it again."""
    section('6d. Reading Castle after Level 7')
    st = php_cli('probe.php', 'castle_setup', acc['pupil6']['uid'])
    if not check('Level 7 can be marked finished for the pupil', st.get('lessons') and st.get('open'), st):
        return
    _, _, text = p6.req('?page=dashboard')
    check('The next page opens the Reading Castle scene', 'data-rc' in text and 'Open the gates' in text and 'Welcome to the Reading Castle!' in text)
    _, _, text = p6.req('?page=dashboard')
    check('The castle opens only once', 'data-rc' not in text)
    _, _, text = p6.req('?page=dashboard&castle=1')
    check('?castle=1 (the castle on the Level 7 road map) opens it again', 'data-rc' in text and 'See my certificate' in text)


def test_cycles(acc):
    """Cycles (v182): a cycle that has not opened keeps its lessons locked; lessons outside it stay open."""
    section('6e. Cycles inside a level')
    st = php_cli('probe.php', 'cycle_check', acc['pupil6']['uid'])
    check('A lesson outside the cycle is open', st.get('one') is None, st)
    check('A lesson in a cycle that opens later shows its date and is locked', st.get('two') == st.get('date') and st.get('locked') is True, st)


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
    check('Jarvis the Robot guides the tour', set((meta.get('guide') or {}).keys()) >= {'hello', 'point', 'map', 'thumbs', 'cheer'} and 'jarvis-' in str(meta.get('guide')) and 'jarvis-head' in str(meta.get('helpface')), str(meta)[:160])
    for n in ('hello', 'point', 'idea', 'thumbs', 'cheer', 'head'):
        check(f'Jarvis picture "{n}" is in place', os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', f'jarvis-{n}.webp')))
    tok = p.token('?page=dashboard')
    _, _, text = p.req('index.php', {'action': 'jarvis_met', 'csrf': tok}, accept_json=True)
    _, _, page = p.req('?page=dashboard')
    m = re.search(r'<meta name="bulig-tour" content="([^"]+)"', page)
    met = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&')).get('met') if m else None
    check('Jarvis says hello only once (it is remembered)', jget(text).get('ok') and met is True, text[:120])


def test_story(p):
    """Story time: the comic How the BULIG Friends Met, and its XP (once per page, bonus at the end)."""
    section('5c1. Story time')
    page_ok(p, '?page=story', 'Story time opens (cover)')
    _, _, text = p.req('?page=story&p=6')
    check('A story page shows its picture, lines and page turns', 'Bruno the Helper' in text and '<svg class="cm-scene"' in text and 'data-story-go="7"' in text and 'class="cm-script"' in text, '')
    _, _, text = p.req('?page=story_data')
    try:
        data = json.loads(text)
    except ValueError:
        data = []
    check('All 18 story pages load for turning pages (Bob is page 15, Jeepy page 16)', len(data) == 18 and all('<svg' in d.get('html', '') for d in data), f'{len(data)} pages')
    page_ok(p, '?page=story&print=1', 'The print version of the story opens')
    _, _, home = p.req('?page=dashboard')
    check('The pupil Overview has the Story time card', 'class="cm-card' in home and 'href="?page=story' in home, '')
    tok = p.token('?page=story')
    gains = []
    for n in list(range(18)) + [3]:
        _, _, r = p.req('index.php', {'action': 'story_seen', 'p': str(n), 'csrf': tok}, accept_json=True)
        gains.append(jget(r).get('gained', -1))
    check('Story XP: +3 for each new page, +15 on the last one, nothing for a page seen again', gains[:17] == [3] * 17 and gains[17] == 15 and gains[18] == 0, str(gains))
    _, _, text = p.req('?page=story')
    check('The story shows it is finished', 'Story finished' in text, '')


def test_friends(p):
    """Meet your BULIG friends: George and 8 friends, shown once; Claude's safety tip on Home."""
    section('5c2. Meet your BULIG friends')
    poses = {'max': 'mic', 'dracs': 'book', 'bruno': 'tryagain', 'mia': 'blocks', 'toby': 'flag', 'zion': 'emblem', 'arah': 'calendar', 'roxanne': 'time', 'kai': 'letter', 'sofie': 'mirror', 'bob': 'blueprint'}
    missing = [f'{k}-{x}' for k, sp in poses.items() for x in ('hello', 'point', 'thumbs', 'cheer', sp)
               if not os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', f'{k}-{x}.webp'))]
    missing += [f'claude-{x}' for x in ('hello', 'thumbs', 'shield', 'worried', 'sad')
                if not os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', f'claude-{x}.webp'))]
    check('All 60 pictures of the 12 friends are in place (Bob the Worker too)', not missing, missing)
    _, _, text = p.req('?page=dashboard')
    m = re.search(r'<meta name="bulig-friends" content="([^"]+)"', text)
    data = json.loads(m.group(1).replace('&quot;', '"').replace('&amp;', '&').replace('&#039;', "'")) if m else {}
    names = [f.get('name') for f in data.get('friends', [])]
    check('A pupil who has not met the friends gets the introduction: George first, then 13 friends (Jeepy last)', names[:1] == ['George'] and len(names) == 14 and names[-1] == 'Jeepy' and 'Bob' in names and 'Kai' in names and 'Sofie' in names and 'assets/friends' in text, names)
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


def test_jeepy(p):
    """Jeepy the Jeepney (v199): friend #14, the journey map on My lessons, Jeepy on each level's road map, and a
    Jeepy-only introduction for pupils who met the friends before."""
    section('5c2b. Jeepy the Jeepney')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    pics = [f'jeepy-{x}' for x in ('hello', 'thumbs', 'cheer')] + [f'jeepy-{k}-{x}' for k in ('boy', 'girl') for x in ('driving', 'arriving', 'finished', 'waiting')]
    missing = [x for x in pics if not os.path.isfile(os.path.join(ROOT, 'public/assets/images/characters', x + '.webp'))]
    check('All 11 Jeepy pictures are in place (3 for the introduction, 4 rides for the boy and 4 for the girl)', not missing, missing)
    unq = lambda v: json.loads(v.replace('&quot;', '"').replace('&amp;', '&').replace('&#039;', "'"))
    _, _, text = p.req('?page=lessons')
    m = re.search(r'data-jy-map="([^"]+)"', text)
    data = unq(m.group(1)) if m else {}
    levels = int(q('SELECT COUNT(*) FROM bulig_levels') or 0)
    items = data.get('items', [])
    check('My lessons shows the journey map: one stop for each level, with Jeepy’s 4 ride pictures', 'class="card jy-card"' in text and len(items) == levels and sorted((data.get('jy') or {}).keys()) == ['done', 'drive', 'stop', 'wait'], (len(items), levels))
    check('The Reading Castle from the BULIG comic stands at the end of the journey road (no school)', 'class="jy-castle"' in text and 'READING CASTLE' in text)
    check('The journey map has a plain list of the levels too (for screen readers and if the map cannot draw)', text.count('<li class="jy-') == levels)
    check('Exactly one level is “you are here” or none (all done or waiting)', sum(1 for i in items if i.get('s') == 'cur') <= 1, [i.get('s') for i in items])
    sex = q("SELECT d.sex FROM pupil_details d JOIN users u ON u.id=d.pupil_id WHERE u.id=" + re.search(r'name="bulig-uid" content="(\d+)"', text).group(1))
    check('The pupil rides in Jeepy as their own boy or girl', all(('jeepy-girl-' if sex == 'female' else 'jeepy-boy-') in v for v in data.get('jy', {}).values()), data.get('jy'))
    lv = next((i for i in items if i.get('h')), None)
    if lv:
        _, _, page = p.req(lv['h'].split('#')[0])
        m = re.search(r'data-pf-map="([^"]+)"', page)
        check('Each level’s road map has Jeepy too', bool(m) and 'jeepy-' in unq(m.group(1)).get('jy', {}).get('stop', ''))
    js = open(os.path.join(ROOT, 'public/assets/app.js')).read()
    check('Jeepy parks clear of the current stop, its stars, flag and name (and drives to a new lesson)', 'function jeepy(' in js and 'function drive(' in js and 'hit(box2(' in js)
    me = re.search(r'name="bulig-uid" content="(\d+)"', text)
    uid = int(me.group(1))
    q(f"INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('friends_met_{uid}',NOW())")
    q(f"DELETE FROM settings WHERE setting_key='jeepy_met_{uid}'")
    _, _, text = p.req('?page=dashboard')
    m = re.search(r'<meta name="bulig-friends" content="([^"]+)"', text)
    data = unq(m.group(1)) if m else {}
    check('A pupil who met the friends before meets only the new friend, Jeepy', [f.get('name') for f in data.get('friends', [])] == ['Jeepy'] and data.get('only') == 1, data.get('friends', [])[:1])
    _, _, r = p.req('index.php', {'action': 'friends_met', 'csrf': p.token('?page=dashboard')}, accept_json=True)
    _, _, text = p.req('?page=dashboard')
    check('Jeepy says hello only once', 'name="bulig-friends"' not in text and q(f"SELECT COUNT(*) FROM settings WHERE setting_key='jeepy_met_{uid}'") == '1')


def test_whats_new(p):
    """Bob the Worker's "There's a new update" popup: once per account, then on the bell, and again with ?whatsnew=1."""
    section('5c4. New update popup (Bob the Worker)')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    _, _, text = p.req('?page=dashboard')
    me = re.search(r'name="bulig-uid" content="(\d+)"', text)
    uid = int(me.group(1)) if me else int(q("SELECT id FROM users WHERE role='pupil' ORDER BY id LIMIT 1"))
    q(f"INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('friends_met_{uid}',NOW()),('jeepy_met_{uid}',NOW())")
    q(f"DELETE FROM settings WHERE setting_key LIKE 'whatsnew_%\\_{uid}'")
    q(f"DELETE FROM notifications WHERE user_id={uid} AND kind='update'")
    _, _, text = p.req('?page=dashboard')
    check('The first page after the update shows Bob the Worker’s popup with the new things', 'data-wn' in text and 'There’s a new update' in text and 'bob-blueprint' in text and 'Ride with Jeepy!' in text)
    _, _, text = p.req('?page=dashboard')
    check('The popup comes only once', 'data-wn' not in text)
    check('A copy of the update is on the bell', q(f"SELECT COUNT(*) FROM notifications WHERE user_id={uid} AND kind='update'") == '1')
    _, _, text = p.req('?page=dashboard&whatsnew=1')
    check('The bell link opens the popup again', 'data-wn' in text)


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
    # batch delete: tick several pupils on My pupils, then "Delete ticked pupils"
    sid = q("SELECT ps.section_id FROM pupil_sections ps JOIN users u ON u.id=ps.pupil_id WHERE u.name LIKE '% Tan Sr.' LIMIT 1")
    rows = {'action': 'import_typed', 'section_id': sid, 'level_id': '1', 'csrf': t.token('?page=accounts&mode=class')}
    for i, (f, x) in enumerate([('Bea', 'F'), ('Ben', 'M')]):
        rows.update({f't[last][{i}]': 'Batchdel', f't[first][{i}]': f, f't[sex][{i}]': x})
    t.req('index.php', rows)
    ids = q("SELECT GROUP_CONCAT(id) FROM users WHERE name LIKE '% Batchdel'").split(',')
    _, _, text = t.req('?page=manage')
    check('My pupils has a "Delete ticked pupils" button', 'value="delete_pupils"' in text)
    _, _, text = t.req('index.php', {'action': 'delete_pupils', 'pupils[]': ids, 'csrf': t.token('?page=manage')})
    check('Ticking 2 pupils and pressing Delete removes both at once', len(ids) == 2 and q("SELECT COUNT(*) FROM users WHERE name LIKE '% Batchdel'") == '0' and '2 pupils were deleted' in text, f'{ids} {text[:0]}')


def test_my_name(t):
    """v194: a teacher changes their own name on My profile (first, middle, last, extension)."""
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    tid = q("SELECT id FROM users WHERE role='teacher' ORDER BY id LIMIT 1")
    old = q(f'SELECT name FROM users WHERE id={int(tid)}')
    _, _, text = t.req('?page=profile')
    check('My profile has "My name" with first, middle and last name', 'value="my_name"' in text and 'name="first_name"' in text and 'name="last_name"' in text)
    check('A teacher has no character picker: the character follows the sex on the account', 'char-pick' not in text and 'value="teacher_character"' not in text)
    t.req('index.php', {'action': 'teacher_character', 'character': 'male', 'csrf': t.token('?page=profile')})
    check('A teacher cannot change the character by sending the form', q(f"SELECT IFNULL((SELECT setting_value FROM settings WHERE setting_key='teacher_char_{int(tid)}'),'')") != 'male')
    t.req('index.php', {'action': 'my_name', 'first_name': 'maria', 'middle_name': 'luna', 'last_name': 'santos', 'name_ext': '', 'csrf': t.token('?page=profile')})
    got = q(f'SELECT name FROM users WHERE id={int(tid)}')
    t.req('index.php', {'action': 'my_name', 'first_name': '', 'last_name': 'X', 'csrf': t.token('?page=profile')})
    kept = q(f'SELECT name FROM users WHERE id={int(tid)}')
    check('A teacher can change their own name (saved as Maria L. Santos); a name without a first name is refused', got == 'Maria L. Santos' and kept == got, f'{got} / {kept}')
    q(f"UPDATE users SET name='{old}' WHERE id={int(tid)}")


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
    # batch delete: ticked teachers go together; a teacher who still has pupils is skipped and named
    for f in ('Batch', 'Batcha'):
        a.req('index.php', {'action': 'create_account', 'role': 'teacher', 'first_name': f, 'last_name': 'Gone', 'teacher_sex': 'male', 'password': 'TempDelete42!', 'back': 'add_teachers', 'csrf': a.token('?page=add_teachers')})
    ids = q("SELECT GROUP_CONCAT(id) FROM users WHERE role='teacher' AND name LIKE '% Gone'").split(',')
    _, _, text = a.req('?page=accounts')
    check('The Teachers page has tick boxes and "Delete ticked teachers"', 'name="teachers[]"' in text and 'value="delete_teachers"' in text)
    _, _, text = a.req('index.php', {'action': 'delete_teachers', 'teachers[]': ids + [busy], 'csrf': a.token('?page=accounts')})
    check('Ticked teachers are deleted together; one who still has pupils is kept and named', len(ids) == 2 and q("SELECT COUNT(*) FROM users WHERE name LIKE '% Gone'") == '0' and q(f'SELECT COUNT(*) FROM users WHERE id={int(busy)}') == '1' and '2 teachers were deleted' in text and 'still have pupils' in text, ids)
    # a teacher with pupils: the administrator chooses who gets them, and the pupils move before the delete
    a.req('index.php', {'action': 'create_account', 'role': 'teacher', 'first_name': 'Leaving', 'last_name': 'Teacher', 'teacher_sex': 'female', 'password': 'TempDelete42!', 'back': 'add_teachers', 'csrf': a.token('?page=add_teachers')})
    lid = q("SELECT id FROM users WHERE name='Leaving Teacher'")
    pid = q(f'SELECT pupil_id FROM teacher_pupils WHERE teacher_id={int(busy)} LIMIT 1')
    q(f'UPDATE teacher_pupils SET teacher_id={int(lid)} WHERE pupil_id={int(pid)}')
    _, _, text = a.req('?page=accounts')
    check('Delete on a teacher with pupils asks who gets them', 'Give their 1 pupil and sections to' in text and 'name="to"' in text)
    _, _, text = a.req('index.php', {'action': 'delete_teacher', 'id': lid, 'to': busy, 'confirm': 'DELETE', 'csrf': a.token('?page=accounts')})
    check('Deleting a teacher with pupils moves them to the chosen teacher first', q(f'SELECT COUNT(*) FROM users WHERE id={int(lid)}') == '0' and q(f'SELECT teacher_id FROM teacher_pupils WHERE pupil_id={int(pid)}') == busy and '1 pupil moved to' in text, text[:0])
    # v187: delete a teacher together with their pupils and sections
    a.req('index.php', {'action': 'create_account', 'role': 'teacher', 'first_name': 'Taking', 'last_name': 'Pupils', 'teacher_sex': 'female', 'password': 'TempDelete42!', 'back': 'add_teachers', 'csrf': a.token('?page=add_teachers')})
    kid = q("SELECT id FROM users WHERE name='Taking Pupils'")
    q("INSERT INTO users(public_id,role,name,password_hash) VALUES('TSTDEL0001','pupil','Gone Pupil','x')")
    gp = q("SELECT id FROM users WHERE public_id='TSTDEL0001'")
    q(f"INSERT INTO pupils(user_id,grade_level,section) VALUES({int(gp)},1,'Del'); INSERT INTO teacher_pupils(teacher_id,pupil_id) VALUES({int(kid)},{int(gp)}); INSERT INTO sections(teacher_id,grade_level,name) VALUES({int(kid)},1,'Del Section')")
    _, _, text = a.req('?page=accounts')
    check('Delete on a teacher with pupils also offers "Delete their pupils too"', 'name="pupils" value="delete"' in text and 'too <small>' in text)
    _, _, text = a.req('index.php', {'action': 'delete_teacher', 'id': kid, 'pupils': 'delete', 'confirm': 'DELETE', 'csrf': a.token('?page=accounts')})
    left = q(f"SELECT CONCAT((SELECT COUNT(*) FROM users WHERE id IN ({int(kid)},{int(gp)})),'/',(SELECT COUNT(*) FROM sections WHERE teacher_id={int(kid)}),'/',(SELECT COUNT(*) FROM pupils WHERE user_id={int(gp)}))")
    check('Deleting a teacher with "Delete their pupils too" removes the teacher, their pupils and their sections', left == '0/0/0' and 'deleted too' in text, left)
    # Edit name & sex: First, Middle, Last and Extension, filled in from the saved name
    old = q(f'SELECT name FROM users WHERE id={int(busy)}')
    _, _, text = a.req('?page=accounts')
    check('Edit name shows First, Middle, Last and Extension boxes', 'class="ws-inline tn-edit"' in text and 'name="middle_name"' in text and 'name="name_ext"' in text)
    a.req('index.php', {'action': 'admin_rename_teacher', 'id': busy, 'first_name': 'maria', 'middle_name': 'Lopez', 'last_name': 'SANTOS', 'name_ext': 'Jr.', 'teacher_sex': 'female', 'csrf': a.token('?page=accounts')})
    check('Renaming a teacher saves the parts and shows "Maria L. Santos Jr."', q(f'SELECT name FROM users WHERE id={int(busy)}') == 'Maria L. Santos Jr.' and q(f'SELECT CONCAT(first_name,"/",middle_name,"/",last_name,"/",name_ext) FROM user_names WHERE user_id={int(busy)}') == 'Maria/Lopez/Santos/Jr.')
    q(f"UPDATE users SET name='{old}' WHERE id={int(busy)}")
    q(f'DELETE FROM user_names WHERE user_id={int(busy)}')
    # Edit grade & sections (v183): the administrator adds a section, changes its grade, and removes it when empty
    _, _, text = a.req('?page=accounts')
    check('Teachers page has "Edit grade & sections"', 'Edit grade &amp; sections' in text and 'name="action" value="admin_section_add"' in text)
    a.req('index.php', {'action': 'admin_section_add', 'teacher': busy, 'grade_level': '4', 'name': 'sampaguita', 'csrf': a.token('?page=accounts')})
    sid = q(f"SELECT id FROM sections WHERE teacher_id={int(busy)} AND name='Sampaguita' AND grade_level=4")
    if check('The administrator adds Grade 4 · Sampaguita for the teacher', sid != ''):
        pp = q(f'SELECT pupil_id FROM teacher_pupils WHERE teacher_id={int(busy)} LIMIT 1')
        was = q(f'SELECT CONCAT_WS("|",IFNULL((SELECT section_id FROM pupil_sections WHERE pupil_id={int(pp)}),0),grade_level,IFNULL(section,"")) FROM pupils WHERE user_id={int(pp)}').split('|')
        q(f'DELETE FROM pupil_sections WHERE pupil_id={int(pp)}')
        q(f'INSERT INTO pupil_sections(pupil_id,section_id) VALUES({int(pp)},{int(sid)})')
        a.req('index.php', {'action': 'admin_section_save', 'id': sid, 'grade_level': '5', 'name': 'Sampaguita', 'csrf': a.token('?page=accounts')})
        check('Changing the grade moves the section and its pupils to Grade 5', q(f'SELECT grade_level FROM sections WHERE id={int(sid)}') == '5' and q(f'SELECT grade_level FROM pupils WHERE user_id={int(pp)}') == '5')
        _, _, text = a.req('index.php', {'action': 'admin_section_delete', 'id': sid, 'csrf': a.token('?page=accounts')})
        check('A section with pupils cannot be removed', q(f'SELECT COUNT(*) FROM sections WHERE id={int(sid)}') == '1')
        q(f'DELETE FROM pupil_sections WHERE section_id={int(sid)}')
        if was[0] != '0':
            q(f'INSERT INTO pupil_sections(pupil_id,section_id) VALUES({int(pp)},{int(was[0])})')
        q(f"UPDATE pupils SET grade_level={int(was[1])},section='{was[2]}' WHERE user_id={int(pp)}")
        a.req('index.php', {'action': 'admin_section_delete', 'id': sid, 'csrf': a.token('?page=accounts')})
        check('An empty section is removed', q(f'SELECT COUNT(*) FROM sections WHERE id={int(sid)}') == '0')


def test_level4_scoring():
    """Level 4: BULIG counts the module's miscues from what the speech recognizer heard."""
    section('5d. Level 4 reading scored by BULIG')
    r = php_cli('probe.php', 'l4', 0)
    check('A sample reading gives the right miscues (2 mispronunciations, 1 omission, 1 substitution, 1 repetition, 1 transposition)', r.get('sample') == [2, 1, 1, 0, 1, 1, 0], str(r.get('sample')))
    check('"was" for "saw" is a reversal', r.get('reversal') == 1, str(r.get('reversal')))
    check('Part of a reading heard: BULIG still scores it (unheard words are omissions); nothing heard: no score', r.get('too_little') is True, str(r.get('too_little')))
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
    test_my_name(t)


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


def test_bug_reports(a):
    """Bug reports stay open until the administrator taps Fixed, even when they were read on the bell (v178)."""
    section('9c. Bug reports')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    aid = int(q("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1"))
    q(f"DELETE FROM notifications WHERE user_id={aid} AND kind IN ('bug','bugfixed')")
    q(f"INSERT INTO notifications(user_id,kind,title,body,url) VALUES({aid},'bug','Bug report from Test Pupil (T1)','Level 5 · test page','?page=bugs')")
    nid = q(f"SELECT id FROM notifications WHERE user_id={aid} AND kind='bug' ORDER BY id DESC LIMIT 1")
    a.req('?page=notifications')
    a.req('index.php', {'action': 'notif_read_all', 'csrf': a.token('?page=notifications')})
    table = lambda h: m.group(0) if (m := re.search(r'<section class="card bug-card">.*?</section>', h, re.S)) else ''
    _, _, text = a.req('?page=bugs')
    check('A bug report read on the bell (Mark all as read) is still open on Bug reports', 'Test Pupil' in table(text) and 'value="bug_fixed"' in table(text), '')
    a.req('index.php', {'action': 'bug_fixed', 'id': nid, 'csrf': a.token('?page=bugs')})
    _, _, text = a.req('?page=bugs')
    _, _, fixed = a.req('?page=bugs&show=fixed')
    text, fixed = table(text), table(fixed)
    check('Only Fixed moves it to Fixed', 'Test Pupil' not in text and 'Test Pupil' in fixed and 'Fixed ' in fixed,
          'kind=' + q(f"SELECT kind FROM notifications WHERE id={int(nid)}") + ' open-has=' + str('Test Pupil' in text) + ' fixed-has=' + str('Test Pupil' in fixed))


def test_schools(a, acc):
    """More than one school (v174): each school's own IDs, the school filter, moving a teacher, Kai's phone tip."""
    section('9b. Schools')
    q = lambda sql: mysql('-N', '-e', sql, db=DB['name']).stdout.strip()
    page_ok(a, '?page=schools', 'Admin page opens: schools')
    a.req('index.php', {'action': 'school_add', 'name': 'Tall Wide Oak Elementary School', 'code': 'twoes', 'school_no': '999001', 'district': 'Test District', 'head': 'Head Two', 'csrf': a.token('?page=schools')})
    two = q("SELECT id FROM schools WHERE code='TWOES'")
    check('An administrator can add a school (the code is saved in capital letters)', two != '', two)
    ids = php_cli('probe.php', 'school_ids', two)
    yy = time.strftime('%y')
    check('A new school gives its own IDs from number 1: pupils TWOES' + yy + '0001, teachers TWOEST0001',
          ids['pupil'] == f'TWOES{yy}0001' and ids['teacher'] == 'TWOEST0001' and ids['name'] == 'Tall Wide Oak Elementary School', str(ids))
    first = q('SELECT MIN(id) FROM schools')
    one = php_cli('probe.php', 'school_ids', first)
    check('The first school keeps its own code and name', one['code'] != 'TWOES' and one['name'] != 'Tall Wide Oak Elementary School', str(one))
    a.req('index.php', {'action': 'school_add', 'name': 'Tall Wide Oak Elementary School', 'code': 'TWOES', 'csrf': a.token('?page=schools')})
    copy = q("SELECT code FROM schools WHERE name='Tall Wide Oak Elementary School' AND id<>%s" % int(two))
    check('Two schools cannot share a code (the same name again gets TAWOES)', q("SELECT COUNT(*) FROM schools WHERE code='TWOES'") == '1' and copy == 'TAWOES', copy)
    q("DELETE FROM schools WHERE name='Tall Wide Oak Elementary School' AND id<>%s" % int(two))
    # v189: the code is made from the name (Tall Wood Oak Elementary School → TWOES). TWOES is taken, so more letters
    # of the first word are used: TAWOES. A typed code is not used.
    a.req('index.php', {'action': 'school_add', 'name': 'Tall Wood Oak Elementary School', 'code': 'HACK', 'csrf': a.token('?page=schools')})
    auto = q("SELECT code FROM schools WHERE name='Tall Wood Oak Elementary School'")
    _, _, sp = a.req('?page=schools')
    check('A new school gets its code from its name, never one already used (TWOES is taken → TAWOES)',
          auto == 'TAWOES' and q("SELECT code FROM schools WHERE id=%s" % int(two)) == 'TWOES' and 'data-sc-auto' in sp and 'sc-viewrow' in sp and 'value="school_save"' not in sp and 'Teachers of this school' not in sp, auto)
    q("DELETE FROM schools WHERE name='Tall Wood Oak Elementary School'")
    a.req('index.php', {'action': 'create_account', 'role': 'teacher', 'school_id': two, 'first_name': 'Tess', 'last_name': 'Two', 'teacher_sex': 'female', 'password': 'TessTwo4242!', 'back': 'add_teachers', 'csrf': a.token('?page=add_teachers')})
    got = q("SELECT CONCAT(public_id,'/',school_id) FROM users WHERE name='Tess Two'")
    check('A teacher added to the second school gets its code and belongs to it', got == f'TWOEST0001/{two}', got)
    _, _, text = a.req(f'?page=accounts&school={two}')
    others = q("SELECT public_id FROM users WHERE role='teacher' AND (school_id IS NULL OR school_id<>%s) LIMIT 1" % int(two))
    at = text.find(others) if others else -1
    check('The school filter shows only that school\'s teachers', 'Tess Two' in text and others != '' and at < 0 and 'sc-filter' in text,
          f"others={others!r} tess={'Tess Two' in text} filter={'sc-filter' in text} near={text[max(0, at - 160):at + 40]!r}")
    # v190: View school: the school's own page (cover, logo, numbers, Overview / Teachers / Pupils)
    _, _, sl = a.req('?page=schools')
    _, _, sp = a.req(f'?page=school&id={two}')
    _, _, spt = a.req(f'?page=school&id={two}&tab=teachers')
    _, _, spp = a.req(f'?page=school&id={two}&tab=pupils')
    check('Each school has "View school", which opens its own page with the logo, numbers and the three tabs',
          f'?page=school&amp;id={two}' in sl and 'class="sp-head' in sp and 'Tall Wide Oak Elementary School' in sp and 'Top readers' in sp
          and 'Lessons just finished' in sp and 'Tess Two' in spt and ('sp-tbl' in spp or 'No pupils match' in spp))
    # v191: the cover is always the BULIG reading picture (it cannot be changed); no details line under the name
    _, _, cv = a.req(f'?page=school&id={two}')
    check('A school page has the fixed cover (no upload) and no details line under the name',
          'name="cover"' not in cv and 'School head:' not in cv)
    _, _, home = a.req('?page=dashboard&school=0')
    check('Home with all schools shows each school side by side', home.count('class="sc-cmp') == 2)
    tid = q("SELECT id FROM users WHERE name='Tess Two'")
    a.req('index.php', {'action': 'teacher_school', 'id': tid, 'school_id': first, 'csrf': a.token('?page=accounts')})
    check('A teacher can be moved to another school (their ID stays)', q(f'SELECT CONCAT(public_id,"/",school_id) FROM users WHERE id={int(tid)}') == f'TWOEST0001/{first}')
    # Add many teachers › Type the list (like the pupils' list): a row with a mistake saves nothing, a right list makes every account
    _, _, tl = a.req('?page=accounts')
    _, _, ta = a.req('?page=add_teachers')
    check('Add teachers is a tab of the Teachers page, not a separate menu item',
          'tt-tabs' in tl and 'tt-tabs' in ta and 'Your teaching team.' in ta and 'Add one teacher' in ta and 'nav-add_teachers' not in tl and '>Add teachers</span>' not in tl)
    _, _, grid = a.req('?page=add_teachers&mode=many')
    check('Add many teachers has the two ways: Type the list and Upload a file', 'data-who="teacher"' in grid and 'how=file' in grid and 'name="t[grade][0]"' in grid)
    typed = {'action': 'timport_typed', 'school_id': two, 'csrf': a.token('?page=add_teachers&mode=many'),
             't[last][0]': 'Lista', 't[first][0]': 'Ana', 't[sex][0]': 'F', 't[grade][0]': '2', 't[section][0]': 'Typed Sampaguita',
             't[last][1]': 'Lista', 't[first][1]': 'Ben', 't[section][1]': 'No Grade'}
    a.req('index.php', typed)
    check('A typed teacher list with a mistake saves nothing (no sex, section without a grade)', q("SELECT COUNT(*) FROM users WHERE name LIKE '% Lista'") == '0')
    typed.update({'csrf': a.token('?page=add_teachers&mode=many'), 't[sex][1]': 'M', 't[section][1]': ''})
    a.req('index.php', typed)
    made = q("SELECT GROUP_CONCAT(CONCAT(public_id,'/',school_id) ORDER BY name) FROM users WHERE role='teacher' AND name LIKE '% Lista'")
    sec = q("SELECT COUNT(*) FROM sections s JOIN users u ON u.id=s.teacher_id WHERE u.name='Ana Lista' AND s.name='Typed Sampaguita' AND s.grade_level=2")
    check('The typed teacher list makes each account with the school\'s IDs, and the section typed for a teacher', made == f'TWOEST0002/{two},TWOEST0003/{two}' and sec == '1', f'{made} sec={sec}')
    for n in ('Ana Lista', 'Ben Lista'):
        a.req('index.php', {'action': 'delete_teacher', 'id': q(f"SELECT id FROM users WHERE name='{n}'"), 'confirm': 'DELETE', 'csrf': a.token('?page=accounts')})
    pupils_ok = q("SELECT COUNT(*) FROM users WHERE role='pupil' AND (school_id IS NULL OR school_id NOT IN (SELECT id FROM schools))")
    check('Every pupil belongs to a school', pupils_ok == '0', pupils_ok)
    a.req('index.php', {'action': 'delete_teacher', 'id': tid, 'confirm': 'DELETE', 'csrf': a.token('?page=accounts')})
    # v187: delete a whole school: its teachers, pupils and sections go with it (type the school code to confirm)
    a.req('index.php', {'action': 'school_add', 'name': 'Dark East Lake Elementary School', 'code': '', 'csrf': a.token('?page=schools')})
    ds = q("SELECT id FROM schools WHERE code='DELES'")
    a.req('index.php', {'action': 'create_account', 'role': 'teacher', 'school_id': ds, 'first_name': 'Closing', 'last_name': 'Teacher', 'teacher_sex': 'male', 'password': 'Closing4242!', 'back': 'add_teachers', 'csrf': a.token('?page=add_teachers')})
    ct = q("SELECT id FROM users WHERE name='Closing Teacher'")
    q(f"INSERT INTO users(public_id,role,name,password_hash,school_id) VALUES('DELES0001','pupil','Closing Pupil','x',{int(ds)})")
    cp = q("SELECT id FROM users WHERE public_id='DELES0001'")
    q(f"INSERT INTO pupils(user_id,grade_level,section) VALUES({int(cp)},2,'C'); INSERT INTO teacher_pupils(teacher_id,pupil_id) VALUES({int(ct)},{int(cp)}); INSERT INTO sections(teacher_id,grade_level,name) VALUES({int(ct)},2,'Closing Section')")
    _, _, text = a.req('?page=schools')
    _, _, text = a.req(f'?page=school&id={ds}&edit=1')
    check('Edit and "Delete this school" are on the school\'s own page (View school), not on the Schools list',
          'value="school_delete"' in text and 'Delete this school' in text and 'value="school_save"' in text and 'id="sp-manage"' in text and '<details class="sc-edit" open' in text)
    a.req('index.php', {'action': 'school_delete', 'id': ds, 'confirm': 'WRONG', 'csrf': a.token('?page=schools')})
    check('A school is not deleted without typing its code', q(f'SELECT COUNT(*) FROM schools WHERE id={int(ds)}') == '1')
    _, _, text = a.req('index.php', {'action': 'school_delete', 'id': ds, 'confirm': 'deles', 'csrf': a.token('?page=schools')})
    left = q(f"SELECT CONCAT((SELECT COUNT(*) FROM schools WHERE id={int(ds)}),'/',(SELECT COUNT(*) FROM users WHERE id IN ({int(ct)},{int(cp)})),'/',(SELECT COUNT(*) FROM sections WHERE teacher_id={int(ct)}))")
    check('Deleting a school removes it with its teachers, pupils and sections', left == '0/0/0' and 'was deleted, with 1 teacher and 1 pupil' in text, left)
    k = Client()
    _, _, p1 = k.login('auto', acc['teacher']['id'], acc['teacher']['pw'])
    _, _, p2 = k.req('?page=dashboard')
    check("Kai's \"use a laptop or computer\" tip comes once after signing in (the browser shows it on phones only)",
          'id="pc-tip"' in p1 and 'kai-letter' in p1 and 'id="pc-tip"' not in p2)


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


def test_messages(acc):
    section('Admin messaging')
    a, p, other, t = Client(), Client(), Client(), Client()
    a.login('admin', acc['admin']['id'], acc['admin']['pw'])
    prepin = a.req('index.php', {'action':'message_preview','csrf':a.token(), 'scope':'teachers','subject':'Blocked','body':'Blocked'})
    check('Messages cannot be sent before admin PIN verification', prepin[0] in (401,403))
    a.req('index.php', {'action':'admin_pin','csrf':a.token(), 'pin':acc['admin']['pin']})
    p.login('pupil', acc['pupil1']['id'], acc['pupil1']['pw'])
    other.login('pupil', acc['pupil6']['id'], acc['pupil6']['pw'])
    t.login('teacher', acc['teacher']['id'], acc['teacher']['pw'])
    page='?page=messages'
    for c,label in [(a,'Admin'),(p,'Pupil'),(t,'Teacher')]:
        check(label+' can open Messages', c.req(page)[0] == 200)
    payload={'action':'message_preview','scope':'person','person':acc['pupil1']['uid'],'subject':'Reading reminder','body':'Hello <script>alert(1)</script>\nPlease read today.'}
    check('Message preview rejects missing CSRF', a.req('index.php', payload)[0] == 403)
    for c,label in [(p,'Pupil'),(t,'Teacher')]:
        check(label+' cannot send messages', c.req('index.php', dict(payload,csrf=c.token(page)))[0] == 403)
    status,_,text=a.req('index.php',dict(payload,csrf=a.token(page)))
    check('Preview escapes text and shows recipients',status==200 and 'Check before sending' in text and '&lt;script&gt;' in text and '<script>alert(1)</script>' not in text)
    token=re.search(r'name="token" value="([0-9a-f]+)"',text).group(1)
    status,url,text=a.req('index.php',{'action':'message_send','csrf':a.token(page),'token':token})
    mid=int(urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)['id'][0])
    check('Admin sends message and sees read status',status==200 and '0 of 1 recipients' in text)
    q=lambda sql:mysql('-N','-e',sql,db=DB['name']).stdout.strip()
    check('Exactly one recipient is recorded',q(f'SELECT COUNT(*) FROM message_recipients WHERE message_id={mid}')=='1')
    check('Recipient bell includes message', 'Reading reminder' in p.req('?page=notifications')[2])
    check('Another pupil cannot open private message',other.req(page+'&id='+str(mid))[0]==404)
    check('Teacher cannot open private pupil message',t.req(page+'&id='+str(mid))[0]==404)
    check('Opening notifications alone does not set message read receipt',q(f'SELECT COUNT(*) FROM message_recipients WHERE message_id={mid} AND read_at IS NOT NULL')=='0')
    status,_,text=p.req(page+'&id='+str(mid))
    check('Pupil reads escaped complete message',status==200 and 'Please read today.' in text and '&lt;script&gt;' in text)
    check('Read receipt is recorded',q(f'SELECT COUNT(*) FROM message_recipients WHERE message_id={mid} AND read_at IS NOT NULL')=='1')
    check('Admin sees updated read count','1 of 1 recipients' in a.req(page+'&id='+str(mid))[2])
    a.req('index.php',{'action':'message_send','csrf':a.token(page),'token':token})
    check('Repeated confirmation does not duplicate message',q('SELECT COUNT(*) FROM admin_messages')=='1')
    for scope in ['pupils','teachers','everyone']:
        status,_,text=a.req('index.php',dict(payload,scope=scope,csrf=a.token(page)))
        check('Admin can preview '+scope,status==200 and 'Check before sending' in text)
    check('Group previews do not send messages',q('SELECT COUNT(*) FROM admin_messages')=='1')
    check('Invalid recipient is rejected',a.req('index.php',dict(payload,person=0,csrf=a.token(page)))[0]==400)
    check('Empty message is rejected',a.req('index.php',dict(payload,body='',csrf=a.token(page)))[0]==400)
    status,_,text=a.req('index.php',dict(payload,person=acc['teacher']['uid'],subject='Teacher note',csrf=a.token(page)))
    token2=re.search(r'name="token" value="([0-9a-f]+)"',text).group(1)
    _,url,_=a.req('index.php',{'action':'message_send','csrf':a.token(page),'token':token2})
    teacher_mid=int(urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)['id'][0])
    check('Teacher receives and opens an addressed message','Teacher note' in t.req(page+'&id='+str(teacher_mid))[2])
    check('Pupil cannot read a teacher message',p.req(page+'&id='+str(teacher_mid))[0]==404)
    q(f"INSERT INTO sections(teacher_id,grade_level,name) VALUES({int(acc['teacher']['uid'])},1,'Message test class')")
    sid=q("SELECT id FROM sections WHERE name='Message test class' ORDER BY id DESC LIMIT 1")
    q(f"INSERT INTO pupil_sections(pupil_id,section_id) VALUES({int(acc['pupil1']['uid'])},{int(sid)}) ON DUPLICATE KEY UPDATE section_id=VALUES(section_id)")
    status,_,text=a.req('index.php',dict(payload,scope='class',section=sid,csrf=a.token(page)))
    check('Class message previews its pupil recipients',status==200 and '1 recipients' in text and 'Check before sending' in text)
    check('Admin accounts are not valid message recipients',a.req('index.php',dict(payload,person=acc['admin']['uid'],csrf=a.token(page)))[0]==400)



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

