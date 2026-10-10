#!/usr/bin/env python3
"""Refreshes the file fingerprints in public/bulig-check.php after files change.

    python3 tools/update_checker.py          update the fingerprints
    python3 tools/update_checker.py --check  only report; exit code 1 if any fingerprint is out of date

bulig-check.php compares every listed file on the live server with these fingerprints, so after an update
it shows which files did not upload. New app files must be added to its list once (this tool warns about them).
"""
import hashlib, os, re, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CHECKER = os.path.join(ROOT, 'public', 'bulig-check.php')
ENTRY = re.compile(r"'((?:app|public|database)/[^']+)'=>'([0-9a-f]{40})'")


def sha1(path):
    with open(os.path.join(ROOT, path), 'rb') as f:
        return hashlib.sha1(f.read()).hexdigest()


def main():
    only_check = '--check' in sys.argv
    text = open(CHECKER, encoding='utf-8').read()
    stale, missing = [], []

    def fix(m):
        path, old = m.group(1), m.group(2)
        if not os.path.isfile(os.path.join(ROOT, path)):
            missing.append(path)
            return m.group(0)
        new = sha1(path)
        if new != old:
            stale.append(path)
        return f"'{path}'=>'{new}'"

    updated = ENTRY.sub(fix, text)
    listed = set(m.group(1) for m in ENTRY.finditer(text))
    tracked = subprocess.run(['git', 'ls-files', 'app', 'public/*.php', 'public/assets/*.js', 'public/assets/*.css'],
                             cwd=ROOT, capture_output=True, text=True).stdout.split()
    unlisted = [p for p in tracked if p.endswith(('.php', '.js', '.css')) and p not in listed
                and p != 'public/bulig-check.php' and not re.search(r'-[0-9a-f]{10}\.(js|css)$', p)]
    if missing:
        print('Listed in the checker but not in the project:', ', '.join(missing))
    if unlisted:
        print('Not checked yet (add them to $expected in public/bulig-check.php):', ', '.join(unlisted))
    if only_check:
        print('Out of date:', ', '.join(stale) if stale else 'none')
        sys.exit(1 if stale or missing else 0)
    if stale:
        open(CHECKER, 'w', encoding='utf-8').write(updated)
    print(f'Updated {len(stale)} fingerprint(s)' + (': ' + ', '.join(stale) if stale else '.'))


if __name__ == '__main__':
    main()
