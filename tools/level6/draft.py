"""First draft of the Level 6 card scripts (tools/level6/script/gN.txt) from the PDF text layer.
The drafts are then checked and corrected by hand against the module pages; build.py reads the scripts.
Usage: python3 tools/level6/draft.py [grade ...]   (refuses to overwrite an existing script unless --force)
"""
import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from extract import page_lines, page_pictures
from plan import plan

HERE = os.path.dirname(__file__)
NOISE = re.compile(r'^(Name\s*:|Grade\s*&|Date\s*_|Score|_{6,}$)|^\d{1,3}$|^Lesson Guide$', re.I)
NUM = re.compile(r'^(\d{1,2})\s*[.)]\s*(.*)$')
CH = re.compile(r'^([A-Da-d])\s*[.)]\s*(.*)$')
DIR = re.compile(r'^(Directions?|Instructions?|Direction)\s*:?|^(Read the|Write the|Put the|Draw a|Choose the|Copy the|After reading|Read and check|In the space|Fill in|Select the)', re.I)
SKILL = re.compile(r'^(SKILL|Skill)\s*[A-E]\b|^ACTIVITY\s*\d|^Activity\s*\d|^Exercise\s*\d|^Pre-?\s*Assessment$|^Post\s*-?\s*Assessment$|^Pre-?test$|^Post Test$|^TEST QUESTIONS', re.I)


def split_choices(t):
    parts = re.split(r'\s+(?=[A-Da-d]\s*[.)]\s)', t)
    return [p.strip() for p in parts if p.strip()]


def draft_unit(g, pages):
    out, pics = [], []
    for pn in pages:
        for k, b in enumerate(page_pictures(g, pn), 1):
            pics.append(f'p{pn:03d}-{k}')
        lines = [l for l in page_lines(g, pn) if not NOISE.search(l['t'])]
        plain = [l['x0'] for l in lines if not NUM.match(l['t']) and not CH.match(l['t']) and not l['bold'] and len(l['t']) > 40]
        left = min(plain) if plain else 0
        prev = None
        for l in lines:
            t = l['t']
            m = NUM.match(t)
            if l['bold'] and l['size'] >= 13 and not m and not DIR.match(t) and len(t) < 70 and not SKILL.match(t):
                out.append(f'read: {t}')
            elif SKILL.match(t):
                out.append(f'# {t}')
            elif DIR.match(t):
                out.append(f'ins: {t}')
            elif m and not CH.match(t):
                out.append(f'q {m.group(1)}: {m.group(2)}')
            elif CH.match(t):
                out.append('| ' + ' | '.join(split_choices(t)))
            elif t.startswith('[ ]') or t.startswith('✓'):
                out.append('| ' + t.lstrip('[ ]✓ ').strip())
            elif re.match(r'^_{2,}', t):
                mm = re.match(r'^_{2,}\s*(\d{1,2})\s*[.)]\s*(.*)$', t)
                out.append(f'rf {mm.group(1)}: {mm.group(2)}' if mm else f'- {t.lstrip("_ ").strip()}')
            else:
                gap = prev is not None and l['y0'] - prev['y1'] > (l['y1'] - l['y0']) * 0.9
                para = (l['x0'] > left + 8 and l['x0'] < 300) or gap
                out.append(f'{"  " if l["x0"] > 300 else ""}{"¶ " if para else ""}{t}')
            prev = l
    return out, pics


def main(grades, force=False):
    P = plan()
    os.makedirs(os.path.join(HERE, 'script'), exist_ok=True)
    for g in grades:
        path = os.path.join(HERE, 'script', f'g{g}.txt')
        if os.path.exists(path) and not force:
            print('exists, skipped:', path); continue
        out = [f'# Level 6 Grade {g} cards. Edited by hand against the module pages; see README in build.py.']
        for li, les in enumerate(P[g]['lessons'], 1):
            out.append(f'\n######## LESSON {li} · {les["kind"]} · {les["title"]}')
            for ui, u in enumerate(les['units'], 1):
                body, pics = draft_unit(g, u['pages'])
                out.append(f'\n=== {li}.{ui} | {u["title"]} | {u["skill"]} | pages {",".join(map(str, u["pages"]))}' + (f' | part {u["part"]}' if u.get('part') else ''))
                out.append('pics: ' + ' '.join(pics))
                out += body
        open(path, 'w').write('\n'.join(out) + '\n')
        print('wrote', path, len(out), 'lines')


if __name__ == '__main__':
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    main([int(a) for a in args] or range(1, 7), '--force' in sys.argv)
