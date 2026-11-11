"""Level 7 lesson plan: one lesson per module activity, read from the card scripts (script/gN.txt).
Each "=== N.1 | title | skill | pages a,b" block is one activity (Level 7 has no pre/post tests)."""
import os, re

HERE = os.path.dirname(__file__)


def plan():
    P = {}
    for g in range(1, 7):
        lessons = []
        path = os.path.join(HERE, 'script', f'g{g}.txt')
        if os.path.exists(path):
            for line in open(path, encoding='utf-8'):
                m = re.match(r'^===\s*(\d+)\.(\d+)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*pages\s+([\d,]+)', line)
                if m:
                    pages = [int(x) for x in m.group(5).split(',')]
                    lessons.append(dict(kind='lesson', title=m.group(3), skill=m.group(4),
                                        units=[dict(title=m.group(3), skill=m.group(4), pages=pages)]))
        P[g] = dict(teacher=[], lessons=lessons)
    return P
