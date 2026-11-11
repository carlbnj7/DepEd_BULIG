"""Level 3 (Word Recognition): titles and directions in the module's own words, and how each card is answered.
Writes database/level3-ui.json. Run: python3 tools/level3/build_ui.py <folder with the module's page text p001.txt..p144.txt>
Keys are "lesson position:phase:activity position" (the same keys as database/level3-cards.json)."""
import json, re, sys, subprocess, csv, io, os
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
TXT = sys.argv[1]

def page(p):
    try: return open(f'{TXT}/p{p:03d}.txt', encoding='utf-8').read()
    except FileNotFoundError: return ''
def flat(s): return ' '.join(s.replace('’', "’").split())

rows = subprocess.run(['mysql', '-B', 'DEPED_BULIG', '-e',
    "SELECT a.id,l.position lpos,a.phase,a.position apos,a.type,a.title,a.prompt,a.source_page sp,l.subtitle FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=4 AND a.published=1 ORDER BY l.position,a.phase,a.position"],
    capture_output=True, text=True, check=True).stdout
acts = list(csv.DictReader(io.StringIO(rows), delimiter='\t', quoting=csv.QUOTE_NONE))
for a in acts: a['prompt'] = a['prompt'].replace('\\n', '\n')

def teacher_steps(p, upto):
    """The numbered steps of the module's Teacher's Activity, steps 1..upto-1, plus the blend lines."""
    t = page(p); i = t.find('Teacher’s Activity')
    if i < 0: return ''
    body = t[i:].split('\n', 1)[1]
    m = re.search(r'\n\s*%d\.' % upto, body)
    body = body[:m.start()] if m else body
    lines = [flat(x) for x in body.split('\n') if flat(x)]
    return '\n'.join(lines)

def step(p, n):
    t = page(p); i = t.find('Teacher’s Activity')
    body = t[i:] if i >= 0 else t
    m = re.search(r'\n\s*%d\.(.*?)(?=\n\s*%d\.|\n\s*Note:|\n\s*Short|\n\s*Station|\Z)' % (n, n + 1), body, re.S)
    return flat(m.group(1)) if m else ''

def directions(p):
    out = []
    for q in (p,):
        for m in re.finditer(r'Directions?:(.*?)(?:\n\s*\n|Procedure:|\Z)', page(q), re.S):
            out.append(flat(m.group(1)))
    return out

def best(cands, ref):
    w = set(re.findall(r'[a-z]+', ref.lower()))
    score = lambda c: len(w & set(re.findall(r'[a-z]+', c.lower())))
    c = max(cands, key=score, default='')
    return c if c and score(c) >= 3 else ''

READ = 'Here are some words I would like you to read. (Do not spell the words, just read them).'
first = {}
for a in acts:
    if a['phase'] == 'learn' and a['apos'] == '1': first[a['lpos']] = int(a['sp'] or 0)
pages = {}
for a in acts:
    k = f"{a['lpos']}:{a['phase']}:{a['apos']}"; t = a['title']; sp = int(a['sp'] or 0); u = {}
    if t.startswith('Task '): u = {'instruction': READ}
    elif t.startswith('Let'):
        u = {'title': 'Pre-Assessment' if a['phase'] == 'pre' else 'Post-Assessment', 'instruction': READ}
    elif t.startswith('Meet the short') or t.startswith('Meet the word family') or t.startswith('Meet the blend'):
        n = 4 if 'short' in t or 'blend' in t else 3
        u = {'title': 'Teacher’s Activity', 'instruction': teacher_steps(sp, n), 'say': True}
    elif t.startswith('Read the short'): u = {'instruction': step(sp, 4)}
    elif t.startswith('Read and repeat'): u = {'instruction': step(sp, 3)}
    elif t.startswith('Read more blend'): u = {'instruction': step(first.get(a['lpos'], sp), 4)}
    elif t.startswith('Meet the sight words'):
        words = [w.strip() for w in re.split(r'\s{2,}', a['prompt'].split('\n')[1]) if w.strip()] if '\n' in a['prompt'] else []
        u = {'title': 'List of words', 'instruction': 'Preliminaries: Review the words previously learned.', 'grid': words}
    elif 'Build a Word' in t: u = {'instruction': 'Build a word out from the picture. Say it. Connect it. Write it on the blank.', 'kind': 'tiles'}
    elif 'Odd Word Out' in t: u = {'instruction': 'Teacher read the words and let learners identify the word that doesn’t belong.'}
    elif 'Tell Me the Word' in t: u = {'title': 'Activity 3: Tell me the Word', 'instruction': 'The teacher shows a picture. The learner says the CVC word that matches the picture.', 'kind': 'say'}
    elif 'Speed Read' in t: u = {'instruction': 'The teacher flashes word cards, and the learners read them quickly. The teacher may include variation activities.'}
    elif t.startswith('Assessment'): u = {'instruction': 'Read the following words correctly.'}
    elif 'Word Sorting' in t:
        words = re.split(r'\s{2,}', a['prompt'].split('\n')[0].replace('Words:', '').strip())
        cols = re.findall(r'/\s*[a-z-]+\s*/', a['prompt']) or re.findall(r'“([^”]+)”', a['subtitle'])
        cols = [re.sub(r'\s', '', c) for c in cols]
        u = {'title': 'Station 1: Word Sorting Worksheet', 'instruction': 'Sort the words into the correct word family columns.', 'sort': {'words': [w for w in words if w], 'cols': cols}}
    elif 'Listen & Match' in t:
        bank = a['prompt'][a['prompt'].find('Words:'):] if 'Words:' in a['prompt'] else ''
        circle = 'Circle' in page(sp) and 'Write the number' not in page(sp)
        u = {'title': 'Station 2: Listen & Match Worksheet', 'instruction': ('Teacher: Read each word aloud twice. Learner: Circle the words that matches the words teacher reads.' if circle else 'Teacher: Read each word aloud twice. Learner: Write the number under each picture that matches the word. ' + bank).strip()}
    elif 'Blend & Say' in t:
        ex = re.search(r'Example:.*', a['prompt'])
        u = {'title': 'Station 3: Blend & Say Worksheet', 'instruction': 'Build, blend, and say each word aloud. Complete the word: ' + (ex.group(0) if ex else '')}
    elif 'Spell & Complete' in t: u = {'title': 'Station 4: Spell & Complete Worksheet', 'instruction': 'Write the missing letters.'}
    elif 'Reading Phrases' in t or 'Reading Simple Sentences' in t: u = {'instruction': ''}
    else:
        d = best(directions(sp), a['prompt'])
        if d: u = {'instruction': d}
    if 'Color My Word' in t: u['kind'] = 'chips'
    # These module pictures already have the word with the blank printed on them.
    if t.startswith('Activity') and ('Fill-in-the-blank' in t or 'Complete My Name' in t): u['notext'] = True
    pages[k] = u
out = {'version': 1, 'pages': pages}
json.dump(out, open(f'{ROOT}/database/level3-ui.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
for k, u in pages.items(): print(k, '|', u.get('title', ''), '|', (u.get('instruction') or '')[:150].replace('\n', ' / '), '|', {x: y for x, y in u.items() if x not in ('title', 'instruction')})
