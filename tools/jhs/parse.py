"""Junior High School modules (Levels 5-7, Grades 7-10): PDF text -> lessons, activities and cards (drafts).

Each module PDF becomes lessons (Pre-test, Day/Activity N, Post-test). A lesson is split into activities at each
"Direction(s):" line. In an activity: the passage (if any) is a Read card; every numbered item is a card; items with
a. b. c. d. options are tapped (choice); the rest are written. The answers are in tools/jhs/keys/<module>.txt.

Usage: python3 tools/jhs/parse.py   -> tools/jhs/drafts/<module>.json and tools/jhs/drafts/<module>.txt (for keying)
"""
import json, os, re, subprocess

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
SRC = os.path.join(ROOT, 'storage', 'jhs')
OUT = os.path.join(os.path.dirname(__file__), 'drafts')

# module id -> (pdf, level id, grades, lesson header regex)
MODULES = {
    'l5-g78': ('level5-g7-8.pdf', 6, [7, 8], 'day'),
    'l5-g9': ('level5-g9.pdf', 6, [9], 'day'),
    'l5-g10': ('level5-g10.pdf', 6, [10], 'day'),
    'l6-g7': ('level6-g7.pdf', 7, [7], 'activity'),
    'l6-g8': ('level6-g8.pdf', 7, [8], 'activity'),
    'l6-g9': ('level6-g9.pdf', 7, [9], 'activity'),
    'l6-g10': ('level6-g10.pdf', 7, [10], 'activity'),
    'l7-g7': ('level7-g7.pdf', 8, [7], 'activity'),
    'l7-g8': ('level7-g8.pdf', 8, [8], 'activity'),
    'l7-g9': ('level7-g9.pdf', 8, [9], 'activity'),
    'l7-g10': ('level7-g10.pdf', 8, [10], 'activity'),
}

# Level 6 Grades 8-10: the pre- and post-tests start with a picture banner (no heading in the text), so these modules are
# split by page: (first page, phase, label). The pages run until the next entry; None skips a page (the Intervention banner).
def _acts(first, step=3):
    return [(first + k * step, 'learn', f'Activity {k + 1}') for k in range(10)]
PAGE_PLAN = {
    'l6-g8': [(6, 'pre', 'Pre-test'), (10, None, ''), *_acts(11), (41, 'post', 'Post-test'), (45, None, '')],
    'l6-g9': [(6, 'pre', 'Pre-test'), (12, None, ''), *_acts(13), (43, 'post', 'Post-test'), (49, None, '')],
    'l6-g10': [(5, 'pre', 'Pre-test'), (10, None, ''), *_acts(11), (42, 'post', 'Post-test'), (47, None, '')],
}
KEYLINE = re.compile(r'^\s*(\d{1,2}\s*\.\s*[A-Da-d]\b|[A-Da-d]\s*$|(\d{1,2}\s*\.\s*[A-Da-d]\W*\s*)+$|\d{1,2}\s*\.\s*[a-d]\)\s)')


PAGE_RE = re.compile(r'^\s*([0-9]{1,3}|[ivxl]{1,6})\s*$', re.I)
NAME_RE = re.compile(r'^\s*Name\s*:?_*', re.I)
OPT_RE = re.compile(r'(?:(?<=\s)|^)([a-dA-D])[\.\)]\s+(.+?)(?=\s+[a-dA-D][\.\)]\s|$)')
ITEM_NARROW = re.compile(r'^\s{0,6}(\d{1,2})[\.\)](?:\s+|(?=[A-Z“"]))(.*\S)')
ITEM_WIDE = re.compile(r'^\s{0,14}(?:Question\s+)?(\d{1,2})[\.\):](?:\s+|(?=[A-Z“"]))(.*\S)')   # Level 6: questions indented further, or "Question 1: ..."
ITEM_RE = ITEM_NARROW   # "3.What ..." (no space) is a question too
DIR_RE = re.compile(r'^\s*(Directions?|Instructions?)\s*[:\.]', re.I)


def text_pages(pdf):
    out = subprocess.run(['pdftotext', '-layout', pdf, '-'], capture_output=True, text=True).stdout
    return out.split('\f')


def lesson_header(line, kind):
    s = re.sub(r'\s{3,}\S{1,4}$', '', line.strip())   # a page number at the end of the line
    if re.match(r'^(PRE[- ]?TEST|PRE[- ]?ASSESSMENT|Pre[- ]?test|Pre[- ]?Assessment)\b', s, re.I) and len(s) < 60:
        return ('pre', s)
    if re.match(r'^(POST[- ]?TEST|POST[- ]?ASSESSMENT|Post[- ]?test|Post[- ]?Assessment)\b', s, re.I) and len(s) < 60:
        return ('post', s)
    m = re.match(r'^(Day|DAY|Activity|ACTIVITY|Lesson|LESSON)\s*#?\s*(\d{1,2})\s*(\(.*\)|[:–-].*)?$', s)
    if m:
        return ('learn', m.group(1).title() + ' ' + m.group(2))
    return None


def parse_opts(line):
    return [(m.group(1).lower(), m.group(2).strip()) for m in OPT_RE.finditer(line)]


MATCH_RE = re.compile(r'^\s*_{2,}\s*(\d{1,2})\s*[\.\)]\s*(.+?)\s{2,}([A-Ja-j])[\.\)]\s*(.+?)\s*$')


def split_items(lines):
    """lines of one activity -> (lead text lines, items[{n,text,opts}] or a matching item {match:{left,right}})"""
    lead, items, cur, match = [], [], None, None
    for l in lines:
        mm = MATCH_RE.match(l)
        if mm:
            if match is None:
                match = dict(n=0, text='', opts=[], match=dict(left=[], right=[]))
                items.append(match)
            match['match']['left'].append([int(mm.group(1)), mm.group(2).strip()])
            match['match']['right'].append([mm.group(3).lower(), mm.group(4).strip()])
            continue
        m = ITEM_RE.match(l)
        o = parse_opts(l) if re.match(r'^\s*[a-dA-D][\.\)]\s', l) else []
        if m and not o:
            cur = dict(n=int(m.group(1)), text=m.group(2).strip(), opts=[])
            items.append(cur)
        elif o and cur is not None:
            cur['opts'].extend(o)
        elif cur is not None and l.strip():
            if cur['opts'] and len(l.strip()) < 45 and not re.search(r'[.?!,;:]$', l.strip()) and re.match(r'^[A-Z]', l.strip()) and not l.startswith('        '):
                continue   # a section heading ("Homophones", "CONTEXT CLUES") after the last option
            if cur['opts']:
                # option text continuing on the next line
                cur['opts'][-1] = (cur['opts'][-1][0], cur['opts'][-1][1] + ' ' + l.strip())
            else:
                cur['text'] += ' ' + l.strip()
        elif cur is None:
            lead.append(l)
    for it in items:
        if not it.get('match') and len(it['opts']) < 2:   # choices written on the question's own line: "... ? A.one B.two C.three D.four"
            whole = it['text'] + ' ' + ' '.join(f'{k.upper()}.{v}' for k, v in it['opts'])
            parts = re.split(r'\s+([A-D])\s*\.\s*(?=\S)', whole)
            if len(parts) >= 7 and [x.upper() for x in parts[1::2]][:3] == ['A', 'B', 'C']:
                it['text'] = parts[0].strip(); it['opts'] = [(parts[i].lower(), parts[i + 1].strip()) for i in range(1, len(parts) - 1, 2)]
        it['text'] = re.sub(r'\s*Worksheet\s*#\s*\d+.*$', '', it['text']).strip()
        it['opts'] = [(k, re.sub(r'\s*Worksheet\s*#\s*\d+.*$', '', v).strip()) for k, v in it['opts']]
        it['opts'] = sorted({k: v for k, v in it['opts']}.items())
        if it.get('match'):
            it['match']['right'].sort()
    return lead, items


def parse_module(mid):
    global ITEM_RE
    pdf, level, grades, kind = MODULES[mid]
    ITEM_RE = ITEM_WIDE if level == 7 else ITEM_NARROW
    pages = text_pages(os.path.join(SRC, pdf))
    lines = []
    for pno, p in enumerate(pages, 1):
        for l in p.split('\n'):
            lines.append((pno, l.rstrip()))
    # the TOC lists the same headers: start after the last TOC line (a header followed by a page number on the line)
    lessons, cur, keys_text = [], None, []
    in_key = False
    for pno, l in lines:
        s = l.strip()
        if re.search(r'answer key', s, re.I) and len(s) < 45:
            in_key = True
        if in_key:
            keys_text.append((pno, l))
            if not lesson_header(l, kind):
                continue
            in_key = False
        h = lesson_header(l, kind)
        if h:
            cur = dict(phase=h[0], label=h[1], lines=[], pages=[])
            lessons.append(cur)
            continue
        if cur is None:
            continue
        if PAGE_RE.match(l) or NAME_RE.match(l):
            continue
        cur['lines'].append(l)
        if s and pno not in cur['pages']:
            cur['pages'].append(pno)
    if mid in PAGE_PLAN:   # split by page (see PAGE_PLAN)
        plan = PAGE_PLAN[mid]; lessons = []
        for k, (start, ph, label) in enumerate(plan):
            if ph is None:
                continue
            end = plan[k + 1][0] - 1 if k + 1 < len(plan) else len(pages)
            les = dict(phase=ph, label=label, lines=[], pages=[]); in_k = False
            for pno, l in lines:
                if not (start <= pno <= end):
                    continue
                t = l.strip()
                if re.search(r'answer keys?\s*:?$', t, re.I) and len(t) < 45:
                    in_k = True; continue
                if in_k:
                    if not t or KEYLINE.match(l):
                        continue
                    in_k = False
                if PAGE_RE.match(l) or NAME_RE.match(l) or lesson_header(l, kind) and re.match(r'^(Activity|ACTIVITY)\s*\d', t):
                    continue
                les['lines'].append(l)
                if t and pno not in les['pages']:
                    les['pages'].append(pno)
            lessons.append(les)
    # merge duplicates of the same pre/post label split by sets
    out = []
    for les in lessons:
        acts, buf = [], []
        for l in les['lines']:
            if DIR_RE.match(l) and buf and any(ITEM_RE.match(x) for x in buf):
                acts.append(buf); buf = []
            buf.append(l)
        if buf:
            acts.append(buf)
        A = []
        for b in acts:
            lead, items = split_items(b)
            lead_txt = '\n'.join(x.strip() for x in lead).strip()
            lead_txt = re.sub(r'\n{3,}', '\n\n', lead_txt)
            direction = ''
            m = re.search(r'(Directions?|Instructions?)\s*[:\.]\s*(.+?)(\n\n|$)', lead_txt, re.S | re.I)
            if m:
                direction = ' '.join(m.group(2).split())
            A.append(dict(direction=direction, lead=lead_txt, items=items))
        short = sum(len(a['lead']) for a in A)
        if not any(a['items'] for a in A) and 'answer key' not in les['label'].lower() and (short < 60 or (short < 400 and not (les['phase'] == 'learn' and les['label'].startswith('Activity')))):
            continue   # a table-of-contents line or an empty heading (a short Activity with its own task is kept)
        out.append(dict(phase=les['phase'], label=les['label'], pages=les['pages'], acts=A, raw='\n'.join(les['lines'])))
    return dict(id=mid, level=level, grades=grades, pdf=pdf, lessons=out,
                keys_text='\n'.join(l for _, l in keys_text))


def review_text(m):
    t = [f"# {m['id']}  level {m['level']} grades {m['grades']}"]
    for li, les in enumerate(m['lessons'], 1):
        t.append(f"\n## L{li} {les['phase']} {les['label']} (pages {les['pages'][:1]}..)")
        for ai, a in enumerate(les['acts'], 1):
            t.append(f"### L{li}.A{ai} dir: {a['direction'][:160]}")
            if a['lead']:
                t.append('    lead: ' + ' '.join(a['lead'].split())[:300])
            for it in a['items']:
                o = '  '.join(f"{k}) {v}" for k, v in it['opts'])
                t.append(f"  {li}.{ai}.{it['n']} {it['text'][:220]}" + (f"  || {o}" if o else ''))
    return '\n'.join(t)


if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    for mid in MODULES:
        m = parse_module(mid)
        json.dump(m, open(os.path.join(OUT, mid + '.json'), 'w'), indent=1, ensure_ascii=False)
        open(os.path.join(OUT, mid + '.txt'), 'w').write(review_text(m))
        n = sum(len(a['items']) for l in m['lessons'] for a in l['acts'])
        c = sum(1 for l in m['lessons'] for a in l['acts'] for i in a['items'] if i['opts'])
        print(f"{mid}: {len(m['lessons'])} lessons, {sum(len(l['acts']) for l in m['lessons'])} activities, {n} items ({c} with choices)")
