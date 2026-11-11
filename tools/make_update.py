#!/usr/bin/env python3
"""Builds a BULIG update ZIP for the live site, and tests it before you install it.

    python3 tools/make_update.py --from <version on the live site> --name BULIG-Something

  --from   the git commit (or tag) the live site has now. Every file changed since then goes in the ZIP,
           including work that is not committed yet.
  --name   the ZIP's name; it is saved in live-update/.
  --no-test  skip the test install (not recommended).

What it does:
  1. Lists every changed file and leaves out what must never go to the live site (config/database.php,
     tests, tools, live-update, notes). Files the app loads are checked, so a ZIP can never miss one again.
  2. Checks that public/bulig-check.php has up-to-date fingerprints (tools/update_checker.py fixes them).
  3. Test install: copies the --from version to a temporary folder, unpacks the ZIP over it and runs
     tests/run_tests.py on that copy. The ZIP is kept only if every test passes.
  4. Prints what to upload and which SQL files (if any) to import in phpMyAdmin.
"""
import argparse, os, re, shutil, subprocess, sys, tempfile, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
NEVER = ('config/database.php',)
SKIP_PREFIXES = ('live-update/', 'tests/', 'tools/', 'docs/', 'storage/', '.github/', '.claude/')
SKIP_RE = re.compile(r'(^[^/]+\.(md|bat)$|^\.gitignore$|-[0-9a-f]{10}\.(css|js)$)')


def git(*args):
    r = subprocess.run(['git', *args], cwd=ROOT, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit('git ' + ' '.join(args) + ' failed: ' + r.stderr.strip())
    return r.stdout


def changed_files(base):
    names = set(git('diff', '--name-only', base).split())
    names |= set(git('ls-files', '--others', '--exclude-standard').split())
    keep, deleted = [], []
    for n in sorted(names):
        if n in NEVER or n.startswith(SKIP_PREFIXES) or SKIP_RE.search(n):
            continue
        (keep if os.path.isfile(os.path.join(ROOT, n)) else deleted).append(n)
    return keep, deleted


def main():
    ap = argparse.ArgumentParser(description='Build and test a BULIG update ZIP.')
    ap.add_argument('--from', dest='base', required=True)
    ap.add_argument('--name', required=True)
    ap.add_argument('--no-test', action='store_true')
    a = ap.parse_args()
    git('rev-parse', '--verify', a.base + '^{commit}')

    files, deleted = changed_files(a.base)
    if not files:
        sys.exit('Nothing changed since ' + a.base + '.')
    r = subprocess.run([sys.executable, os.path.join(ROOT, 'tools', 'update_checker.py'), '--check'], cwd=ROOT, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit('public/bulig-check.php is out of date. Run: python3 tools/update_checker.py\n' + r.stdout)
    if 'public/bulig-check.php' not in files and any(f.startswith(('app/', 'public/', 'database/')) for f in files):
        files.append('public/bulig-check.php')
    sql = [f for f in files if f.startswith('database/migrations/') and f.endswith('.sql')]

    os.makedirs(os.path.join(ROOT, 'live-update'), exist_ok=True)
    out = os.path.join(ROOT, 'live-update', a.name + '.zip')
    tmp_zip = out + '.tmp'
    with zipfile.ZipFile(tmp_zip, 'w', zipfile.ZIP_DEFLATED) as z:
        for f in files:
            z.write(os.path.join(ROOT, f), f)

    if not a.no_test:
        sim = tempfile.mkdtemp(prefix='bulig-update-test-')
        try:
            archive = subprocess.run(['git', 'archive', a.base], cwd=ROOT, capture_output=True).stdout
            subprocess.run(['tar', '-x', '-C', sim], input=archive, check=True)
            with zipfile.ZipFile(tmp_zip) as z:
                z.extractall(sim)
            print(f'Test install: {a.base} + this ZIP, in {sim}')
            t = subprocess.run([sys.executable, os.path.join(ROOT, 'tests', 'run_tests.py'), '--root', sim], cwd=ROOT, stdin=subprocess.DEVNULL)
            if t.returncode != 0:
                os.remove(tmp_zip)
                sys.exit('\nThe test install failed, so no ZIP was made. Fix the problems above and run this again.')
        finally:
            shutil.rmtree(sim, ignore_errors=True)
    os.replace(tmp_zip, out)

    print(f'\nMade {os.path.relpath(out, ROOT)} with {len(files)} files:')
    for f in files:
        print('  ' + f)
    print('\nSQL to import in phpMyAdmin: ' + (', '.join(sql) if sql else 'none'))
    if deleted:
        print('Removed in this version (delete them on the server if you like; leaving them is harmless): ' + ', '.join(deleted))


if __name__ == '__main__':
    main()
