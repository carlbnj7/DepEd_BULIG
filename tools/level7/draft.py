"""First draft of the Level 7 card scripts from the PDF text (then corrected by hand against the pages).
Activities are split at their "Activity N" headings (several may share a page, one may run over pages);
each picture goes to the activity printed above it. Usage: python3 tools/level7/draft.py [grade ...] [--force]
"""
import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from extract import page_lines, page_pictures, doc

HERE = os.path.dirname(__file__)
TOC = {1: {1: 'Independence Day', 2: 'Money from America', 3: 'School Needs', 4: 'Playmates', 5: 'Going to the Doctor', 6: 'School Materials', 7: 'Meat and Milk', 8: 'Party Dresses', 9: 'Andres Bonifacio', 10: 'At the Zoo', 11: 'Care of Books', 12: 'An Honest Boy', 13: 'Selling Rice Cakes', 14: 'Going to a Picnic', 15: 'Norma', 16: 'Razon’s Children', 17: 'The Vendor', 18: 'Lazy Carlito', 19: 'The Storm', 20: 'Lolita Had a Fever', 21: 'Lola’s White Hair', 22: 'The Wedding Day', 23: 'Honesty', 24: 'In School', 25: 'Everybody Was Busy', 26: 'Playing in the Rain', 27: 'Watching Birds', 28: 'The Raincoat', 29: 'Late in Church', 30: 'Danilo’s School Things', 31: 'A Tree', 32: 'Playing at Night', 33: 'Flowers', 34: 'A Christmas Gift', 35: 'Playing Soldiers', 36: 'Paper Boats', 37: 'At the School Canteen', 38: 'Antonyms', 39: 'A Happy Family', 40: 'Trees', 41: 'Water', 42: 'A-1 Child', 43: 'Telling Time', 44: 'Thank You', 45: 'Schoolbags', 46: 'Jeepneys'}, 2: {1: 'Fowls', 2: 'The First Monkey', 3: 'No Carnival for Ben', 4: 'The Butterfly', 5: 'The Moth', 6: 'Keeping Healthy', 7: 'The Truthful Boy', 8: 'The Lantern Show', 9: 'The Chinese Boy', 10: 'The Monkey and the Crocodiles', 22: 'Forgetful Lucia', 23: 'Just Pray', 24: 'An Early Morning Fight', 25: 'The King’s Little Girl', 26: 'Three Friends', 27: 'Tired Boy Scouts', 28: 'The Program', 29: 'Berto Jokes No More', 30: 'The Green Revolution', 31: 'A Dog’s Bite', 32: 'A Bus Ride', 33: 'Sets', 34: 'Opposites', 35: 'Our National Symbols', 36: 'How Nestor Earns Money', 37: 'Fiesta Time', 38: 'First Elevator Ride', 39: 'Share a Toy', 40: 'The Toys', 41: 'Growing Roses', 42: 'The Fishermen', 43: 'Days of the Week'}, 3: {1: 'Jumbled Letters', 2: 'True or False', 3: 'Star or Circle', 4: 'Writing Campus Newsletter', 5: 'Writing Simple News', 6: 'Find It!', 7: 'Fact or Bluff', 8: 'Match Me!', 9: 'Search, Write and Identify', 10: 'Scope or Origin', 11: 'Think for Me', 12: 'Discern the Content', 13: 'Guess the Treat', 14: 'Connect Me!', 15: 'Fill Me!'}, 4: {1: 'Opinion or Understanding', 2: 'Writing Paragraph', 3: 'Learners News Report', 4: 'Arranging Facts', 5: 'Writing News Report', 6: 'Feature Writing Exercises', 7: 'Copyreading and Headline Writing', 8: 'Sports Vocabulary Matching', 9: 'Sports Writing', 10: 'Find It!', 11: 'Arranging the Story', 12: 'Sports Writing', 13: 'Sports Writing', 14: 'Mystery Matching Type', 15: 'Editorial Cartooning'}, 5: {1: 'Writing a News Article', 2: 'Journalistic Writing', 3: 'Matching Type', 4: 'Writing Activity', 5: 'Mix Exercises', 6: 'Writing a News Story', 7: 'Do What Is Asked', 8: 'Editorial Log', 9: 'Writing Articles', 10: 'Writing Articles', 11: 'Writing a Paragraph', 12: 'Editorial Writing', 13: 'Copyreading and Headline Writing', 14: 'News Writing', 15: 'Feature Writing'}, 6: {1: 'Story Sequencing', 2: 'Scrambled Information', 3: 'Scrambled Information', 4: 'A Dirty Cafeteria', 5: 'Solution: Return to the Basics', 6: 'The Way of Most Desks', 7: 'Writing a Sports Coverage', 8: 'Writing a Sports Game Story', 9: 'Summarizing a Story', 10: 'Copyreading', 11: 'Copyreading', 12: 'Copyreading', 13: 'Feature Writing', 14: 'Feature Writing', 15: 'Feature Writing', 16: 'Write a Column', 17: 'Draw an Editorial', 18: 'Editorial Discussion', 19: 'Draw', 20: 'Mystery Matching Type'}}
RANGE = {1: (6, 51), 2: (6, 33), 3: (5, 24), 4: (6, 29), 5: (5, 34), 6: (6, 38)}
ACT = re.compile(r'^Activity\s*(\d+)\s*[:.\-–]?\s*(.*)$', re.I)
NOISE = re.compile(r'^(Name\s*:|Grade\s*&|Date\s*:?\s*_|Score)|^\d{1,3}$|^_{3,}[\s_.]*$|^[-–_.\s]{6,}$', re.I)
NUM = re.compile(r'^(\d{1,2})\s*[.)]\s*(.*)$')
CHM = re.compile(r'(?:^|\s)([a-dA-D])\s*[.)]\s+')
DIR = re.compile(r'^(Directions?|Instructions?(/s)?)\s*:', re.I)


def choices_of(text):
    """'a. Plant c. Animals b. Trees' -> {'a': 'Plant', 'b': 'Trees', 'c': 'Animals'} (None if not a choice line)."""
    ms = list(CHM.finditer(text))
    if not ms or ms[0].start() > 1:
        return None
    out = {}
    for i, m in enumerate(ms):
        end = ms[i + 1].start() if i + 1 < len(ms) else len(text)
        out[m.group(1)] = text[m.end():end].strip()
    return out


def units(g):
    a, b = RANGE[g]
    acts, cur = [], None
    for pn in range(a, b + 1):
        lines = [l for l in page_lines(g, pn) if not NOISE.search(l['t'])]
        carry = cur                       # the activity running on from the page before
        heads = []                        # (y, activity) for headings on this page
        for l in lines:
            m = ACT.match(l['t'])
            if m:
                cur = dict(num=int(m.group(1)), title=m.group(2).strip(' :!'), pages=[], lines=[], pics=[])
                acts.append(cur); heads.append((l['y0'], cur))
            if cur is None:
                continue
            if pn not in cur['pages']:
                cur['pages'].append(pn)
            if not m:
                cur['lines'].append(l)
        for k, box in enumerate(page_pictures(g, pn), 1):
            above = [act for y, act in heads if y <= box[1] + 5]
            owner = above[-1] if above else (carry or (heads[0][1] if heads else cur))
            if owner is None:
                continue
            if pn not in owner['pages']:
                owner['pages'].append(pn)
            owner['pics'].append(f'p{pn:03d}-{k}')
    return acts


def render(act):
    out, choices, in_q = [], None, False
    lines = act['lines']
    title = act['title']
    if not title and lines and lines[0]['bold'] and len(lines[0]['t']) < 70 and not NUM.match(lines[0]['t']):
        title = lines.pop(0)['t']
    out.append(('title', title))
    longs = [l['x0'] for l in lines if len(l['t']) > 40 and not NUM.match(l['t'])]
    left = min(longs) if longs else 0
    prev = None
    for l in lines:
        t = l['t']
        ch = choices_of(t)
        gap = prev is not None and l['y0'] - prev['y1'] > (l['y1'] - l['y0']) * 0.9
        prev = l
        m = NUM.match(t)
        if ch is not None and in_q:
            if choices is None:
                choices = {}
                out.append(('choices', choices))
            choices.update(ch)
            continue
        if m and not ch:
            parts = re.split(r'\s+(?=\d{1,2}\.\s+[A-Z])', m.group(2))      # "… table. 5. Write the number …"
            out.append(('q', f'q {m.group(1)}: {parts[0]}')); in_q = True; choices = None
            for extra in parts[1:]:
                mm = NUM.match(extra)
                out.append(('q', f'q {mm.group(1)}: {mm.group(2)}'))
            continue
        if DIR.match(t):
            out.append(('line', 'ins: ' + t)); continue
        if in_q:
            if choices is not None and choices:
                k = sorted(choices)[-1]; choices[k] += ' ' + t
            else:
                out.append(('line', t))
            continue
        if l['bold'] and len(t) < 70 and not t.endswith(('.', '?', '"', '”')):
            out.append(('line', 'read: ' + t)); continue
        keep = re.search(r'\.{4,}|^(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)$', t)
        out.append(('line', ('¶ ' if (l['x0'] > left + 8 and l['x0'] < 300) or gap or keep else '') + t))
    res = []
    head = title.strip() if title else ''
    if head.isupper():
        small = {'a', 'an', 'and', 'the', 'of', 'in', 'on', 'to', 'for', 'at', 'from', 'with'}
        head = ' '.join(w if i and w in small else w[:1].upper() + w[1:] for i, w in enumerate(head.lower().split()))
    act['head'] = head
    for kind, v in out:
        if kind == 'title':
            continue
        if kind == 'choices':
            res.append('| ' + ' | '.join(f'{k}. {v[k]}' for k in sorted(v)))
        elif kind in ('q', 'line'):
            res.append(v)
    return title, res


def main(grades, force=False):
    os.makedirs(os.path.join(HERE, 'script'), exist_ok=True)
    for g in grades:
        path = os.path.join(HERE, 'script', f'g{g}.txt')
        if os.path.exists(path) and not force:
            print('exists, skipped:', path); continue
        out = [f'# Level 7 Grade {g} cards (Genuine Love for Reading). Checked by hand against the module pages; format in tools/level6/build.py.']
        for i, act in enumerate(units(g), 1):
            title, body = render(act)
            title = TOC.get(g, {}).get(act['num']) or title or f'Activity {act["num"]}'
            out.append(f'\n=== {i}.1 | {title} | Activity {act["num"]} | pages {",".join(map(str, sorted(act["pages"])))}')
            out.append('pics: ' + ' '.join(act['pics']))
            if g in (1, 2) and body and not body[0].startswith(('read', 'ins', 'q ')):
                body = ['read: ' + (act.get('head') if act.get('head') and act['head'].lower() not in ('', ) and not act['head'].lower().startswith('activity') else title)] + body
            out += body
        open(path, 'w').write('\n'.join(out) + '\n')
        print('wrote', path, len(out), 'lines')


if __name__ == '__main__':
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    main([int(a) for a in args] or range(1, 7), '--force' in sys.argv)
