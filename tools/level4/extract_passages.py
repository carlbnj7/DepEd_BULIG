"""Level 4 (Fluency) passage extraction, one module per grade.

Reads storage/level4/grade-N.pdf (N = 1..6) and writes database/level4-passages.json:
passages in module order with title, text lines, the module's stated word count,
source pages and the passage picture.  Titles and some lines are printed as
images in the modules; those are read with Tesseract OCR.  Each passage ends
at the Phil-IRI scoring table ("Types of Miscues ... Number of Words").
"""
import io, json, os, re, subprocess, sys, tempfile
import pymupdf
from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
FOOTER = (1448, 193)


def ocr(img):
    with tempfile.NamedTemporaryFile(suffix='.png') as f:
        img.save(f.name)
        out = subprocess.run(['tesseract', f.name, '-', '--psm', '7'], capture_output=True, text=True).stdout
    return ' '.join(out.split())


def page_items(doc, pn):
    """Text lines and OCR'd text-images on a page, above the scoring table, in reading order."""
    page = doc[pn - 1]
    stop = page.rect.height
    for b in page.get_text('dict')['blocks']:
        if b['type'] == 0 and re.search(r'Types of Mis|Mispronun|Number of Words in', ''.join(s['text'] for l in b['lines'] for s in l['spans'])):
            stop = min(stop, b['bbox'][1])
    items = []
    for b in page.get_text('dict')['blocks']:
        if b['type'] != 0 or b['bbox'][1] >= stop:
            continue
        for l in b['lines']:
            t = ''.join(s['text'] for s in l['spans']).strip()
            size = max((s['size'] for s in l['spans']), default=0)
            bold = any('Bold' in s['font'] for s in l['spans'])
            if t:
                items.append(dict(y=l['bbox'][1], x=l['bbox'][0], text=t, size=size, bold=bold, kind='text'))
    pix = page.get_pixmap(dpi=300)
    full = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
    s = 300 / 72
    for info in page.get_image_info(xrefs=True):
        x0, y0, x1, y1 = info['bbox']
        if (info['width'], info['height']) == FOOTER or y0 >= stop:
            continue
        w, h = x1 - x0, y1 - y0
        if h < 45 and w / max(h, 1) > 3.5:   # a line of text stored as an image
            t = ocr(full.crop((int(x0 * s), int(y0 * s), int(x1 * s), int(y1 * s))))
            if t:
                items.append(dict(y=y0, x=x0, text=t, size=h * 0.75, bold=h > 28, kind='ocr'))
    return sorted(items, key=lambda i: (round(i['y'] / 4), i['x'])), stop < page.rect.height


def ocr_page(doc, pn, stop):
    """OCR the passage area of a page (pictures blanked); drop captions/URLs by line height."""
    page = doc[pn - 1]
    tmp = pymupdf.open()
    tmp.insert_pdf(doc, from_page=pn - 1, to_page=pn - 1)
    tp = tmp[0]
    for info in tp.get_image_info():
        x0, y0, x1, y1 = info['bbox']
        if (x1 - x0) > 60 and (y1 - y0) > 50 and info['width'] > 150 and info['height'] > 150:
            tp.add_redact_annot(pymupdf.Rect(info['bbox']))
    tp.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_REMOVE, graphics=pymupdf.PDF_REDACT_LINE_ART_NONE, text=pymupdf.PDF_REDACT_TEXT_NONE)
    pix = tp.get_pixmap(dpi=300)
    im = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
    from PIL import ImageDraw
    dr = ImageDraw.Draw(im); s = 300 / 72
    dr.rectangle([0, stop * s - 2, im.width, im.height], fill='white')
    dr.rectangle([0, 0, im.width, 50 * s], fill='white')   # page number strip
    with tempfile.NamedTemporaryFile(suffix='.png') as f:
        im.save(f.name)
        tsv = subprocess.run(['tesseract', f.name, '-', '--psm', '3', 'tsv'], capture_output=True, text=True).stdout
    lines = {}
    for row in tsv.splitlines()[1:]:
        c = row.split('\t')
        if len(c) < 12 or c[0] != '5' or not c[11].strip() or float(c[10]) < 20:
            continue
        key = (int(c[2]), int(c[3]), int(c[4]))
        L = lines.setdefault(key, dict(words=[], top=int(c[7]), h=[], left=int(c[6])))
        L['left'] = min(L['left'], int(c[6]))
        L['words'].append(c[11]); L['h'].append(int(c[9]))
    out = []
    for key in sorted(lines):
        L = lines[key]
        out.append(dict(key=key, text=' '.join(L['words']), h=sorted(L['h'])[len(L['h']) // 2], top=L['top'], left=L['left']))
    if not out:
        return []
    med = sorted(o['h'] for o in out)[len(out) // 2]
    URL = re.compile(r'https?:|www\.|\.com|\.jpg|\.png|file:|Downloads/|AI-generated|image generator|openai', re.I)
    junk = lambda t: URL.search(t) or re.search(r'\S{22,}', t) or re.search(r'\S*[=&]\S{6,}', t) or re.fullmatch(r'(pre|post)[- ]?t?est ?\S*', t.strip(), re.I)
    keep = [o for o in out if o['h'] >= med * 0.72 and not junk(o['text'])]
    vocab = {}
    for w in re.findall(r"[A-Za-z’'][A-Za-z’'-]*", page.get_text()):
        vocab.setdefault(w.lower(), w)
    import difflib
    keys = list(vocab)
    def fix(tok):
        core = re.match(r"^([^A-Za-z’']*)([A-Za-z’'][A-Za-z’'-]*)(.*)$", tok)
        if not core or core.group(2).lower() in vocab or len(core.group(2)) < 3:
            return tok
        m = difflib.get_close_matches(core.group(2).lower(), keys, n=1, cutoff=0.8)
        if not m:
            return tok
        w = vocab[m[0]]
        if core.group(2)[:1].isupper():
            w = w[:1].upper() + w[1:]
        return core.group(1) + w + core.group(3)
    for o in keep:
        o['text'] = ' '.join(fix(t) for t in re.sub(r'\s_+\s', ' ', o['text']).split())
    return keep


def text_layer_lines(doc, pn, stop):
    """Body-size text-layer lines in document order; same-row fragments joined."""
    page = doc[pn - 1]
    spans = []
    for bi, b in enumerate(page.get_text('dict')['blocks']):
        if b['type'] != 0 or b['bbox'][1] >= stop - 1:
            continue
        for l in b['lines']:
            t = ''.join(s['text'] for s in l['spans']).strip()
            if not t or l['bbox'][1] < 45:
                continue
            size = max(s['size'] for s in l['spans'])
            spans.append(dict(block=bi, y=l['bbox'][1], x=l['bbox'][0], text=t, size=size))
    if not spans:
        return []
    import collections
    c = collections.Counter()
    for sp in spans:
        c[round(sp['size'])] += len(sp['text'])
    body = c.most_common(1)[0][0]
    URL = re.compile(r'https?:|www\.|\.com|\.jpg|\.png|file:|Downloads/|AI-generated|image generator|openai|Adobe Stock', re.I)
    keep = [sp for sp in spans if body * 0.8 <= sp['size'] <= body * 1.25 and not URL.search(sp['text']) and not re.search(r'\S{22,}', sp['text'])]
    rows = []
    for sp in keep:
        if rows and rows[-1]['block'] == sp['block'] and abs(rows[-1]['y'] - sp['y']) < 2.5:
            rows[-1]['text'] += ' ' + sp['text']
        else:
            rows.append(dict(sp, page=pn))
    return rows


def stated_count(doc, pn):
    t = doc[pn - 1].get_text()
    m = re.search(r'Number of Words in the Passage\s*(\d+)', t)
    if m:
        return int(m.group(1))
    m = re.search(r'Oral Reading Score\s*=\s*(\d+)', t)
    return int(m.group(1)) if m else None


def pictures(doc, pn):
    out = []
    for info in doc[pn - 1].get_image_info(xrefs=True):
        x0, y0, x1, y1 = info['bbox']
        if (info['width'], info['height']) == FOOTER:
            continue
        w, h = x1 - x0, y1 - y0
        if w > 60 and h > 60 and info['width'] > 250 and info['height'] > 250:
            out.append(dict(page=pn, bbox=[round(v, 1) for v in info['bbox']], xref=info['xref']))
    return out


SKIP = re.compile(r'^(Pre-?test Passage|Post-?\s?test Passage|Postt est Passage|\d+|[ivx]+)$', re.I)


def extract(grade, toc):
    doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level4', f'grade-{grade}.pdf'))
    passages = []
    for pages in GROUPS[grade]:
        cur = dict(pages=pages, lines=[], pictures=[])
        for pn in pages:
            items, ends = page_items(doc, pn)
            if grade >= 3:
                page = doc[pn - 1]; stop = page.rect.height
                for b in page.get_text('dict')['blocks']:
                    if b['type'] == 0 and re.search(r'Types of Mis|Mispronun|Number of Words in', ''.join(s['text'] for l in b['lines'] for s in l['spans'])):
                        stop = min(stop, b['bbox'][1])
                items = [dict(y=o['top'], x=o['left'], text=o['text'], size=o['h'], bold=False, kind='ocr', block=o['key'][0], page=pn) for o in ocr_page(doc, pn, stop)]
            cur['lines'] += [i for i in items if not SKIP.match(i['text'])]
            cur['pictures'] += pictures(doc, pn)
            c = stated_count(doc, pn)
            if c:
                cur['count'] = c
        # Prefer the PDF text layer when it holds the whole passage.
        if grade >= 3:
            tl = []
            for pn in pages:
                page = doc[pn - 1]; stop = page.rect.height
                for b in page.get_text('dict')['blocks']:
                    if b['type'] == 0 and re.search(r'Types of Mis|Mispronun|Number of Words in', ''.join(s['text'] for l in b['lines'] for s in l['spans'])):
                        stop = min(stop, b['bbox'][1])
                tl += [dict(r, kind='text') for r in text_layer_lines(doc, pn, stop)]
            cnt = lambda ls: len(re.findall(r"[A-Za-z0-9’'-]+", ' '.join(l['text'] for l in ls)))
            want = cur.get('count') or 0
            if tl and want and abs(cnt(tl) - want) <= abs(cnt(cur['lines']) - want):
                cur['lines'] = [l for l in tl if not SKIP.match(l['text'])]
                cur['source'] = 'text layer'
            else:
                cur['source'] = 'OCR'
        passages.append(cur)
    out = []
    for p, title in zip(passages, toc):
        body, found, byline = [], False, ''
        norm = lambda s: re.sub(r'[^a-z]', '', s.lower())
        tnorm = norm(re.sub(r'\s+II$', '', title))
        for i, l in enumerate(p['lines']):
            n = norm(l['text'])
            if n and not body and (n == tnorm or n == norm(title) or (len(n) > 4 and tnorm.startswith(n))):
                found = True
                continue
            ws = l['text'].split()
            if found and not body and not byline and 2 <= len(ws) <= 4 and all(w[:1].isupper() for w in ws) and ws[0] not in ('I', 'You', 'He', 'She', 'It', 'We', 'They', 'The', 'A', 'My', 'Once') and not re.search(r'[.!?,]$', l['text']):
                byline = l['text']
                continue
            body.append(l)
        body, byline = tidy(title, body, byline)
        body, byline = fix(grade, len(out), body, byline)
        words = len(re.findall(r"[A-Za-z0-9’'’-]+", ' '.join(b['text'] for b in body)))
        fmt, paras = layout(grade, title, body)
        src = p.get('source', 'text layer')
        out.append(dict(title=title, source=src, title_found=found, byline=byline, format=fmt, paragraphs=paras, lines=[b['text'] for b in body], words=words, stated=p.get('count'),
                        pages=p['pages'], pictures=p['pictures']))
    return out


import difflib as _dl
WORDS = {w.strip().lower() for w in open('/usr/share/dict/words', encoding='utf-8', errors='ignore')} | {'i', 'a'}
AUTHOR = re.compile(r"^(song\s+)?by\s+\S|\b1[5-9]\d\d\s*-\s*1[5-9]\d\d\b|^author:", re.I)
KNOWN = ('William Butler Yeats', 'Shel Silverstein', 'Randy Ryan', 'Henry Joyce', 'Aesop Fable', 'Edgar Allan Poe', 'Jack Prelutsky')


def tidy(title, body, byline):
    body = [b if isinstance(b, dict) else dict(text=b) for b in body]
    """Drop decorative-title OCR noise at the top; pick up author lines as the byline."""
    letters = lambda t: re.sub(r'[^a-z]', '', t.lower())
    t = letters(re.sub(r'\s+II$|\(continued\)', '', title))
    out = list(body)
    for _ in range(3):
        if not out:
            break
        first = out[0]['text']
        if AUTHOR.search(first) or any(first.strip().startswith(k) for k in KNOWN):
            byline = byline or re.sub(r'^by\s+', '', first.strip(), flags=re.I)
            out.pop(0); continue
        sim = _dl.SequenceMatcher(None, letters(first), t).ratio()
        ws = re.findall(r"[A-Za-z]+", first)
        real = sum(w.lower() in WORDS for w in ws) / max(len(ws), 1)
        if (sim >= 0.45 and real < 0.75) or (len(ws) <= 4 and real < 0.5) or len(letters(first)) <= 2:
            out.pop(0); continue
        break
    byline = re.sub(r'^(song\s+)?by\s+', '', byline, flags=re.I).replace('Author: Unknown', 'Author unknown')
    return out, byline

# Hand corrections checked against the printed pages: (drop first N lines, byline, replacements, drop last N, add lines).
FIXES = {
    (3, 0): dict(typed='ant-fly'), (3, 11): dict(typed='ant-fly'),
    (3, 2): dict(drop_last=1, sub=[('mag-ma', 'magma'), ('at tached', 'attached')]),
    (3, 3): dict(sub=[('upsidedown', 'upside-down'), ('because — she', 'because she')]),
    (3, 4): dict(sub=[('she knows serious', 'she knows you’re serious'), ('“T don’t', '“I don’t'), ('Stay safe', 'stay safe'),
                      ('no for this,’', 'no for this,”'), ('that Love her', 'that I love her')]),
    (3, 5): dict(sub=[('Satisfied', 'satisfied')]),
    (3, 7): dict(drop=1, byline='James Hampshire'),
    (3, 10): dict(drop_last=1),
    (4, 0): dict(typed='baseball-1'),
    (4, 5): dict(drop_last=2),
    (4, 6): dict(typed='baseball-2'),
    (4, 10): dict(add=['In all I do', '(Repeat Chorus)']),
    (4, 11): dict(typed='baseball-1'),
    (5, 0): dict(drop=1), (5, 8): dict(drop=1), (5, 9): dict(drop=1),
    (5, 5): dict(drop=2, byline='Shel Silverstein', add=['We all sit around and watch him.']),
    (5, 6): dict(sub=[('My Fair Lad y', 'My Fair Lady')]),
    (6, 10): dict(sub=[('teen ager', 'teenager')]),
    (6, 3): dict(drop=1, byline='Edwin Arlington Robinson'),
    (6, 6): dict(drop=2, byline='Jack Prelutsky'),
    (6, 9): dict(typed='baseball-1'),
}
JUNK = re.compile(r'^\S*\.pdf$|^[A-Za-z0-9+/]{6,}=+$|^https?://|^www\.')


TYPED = json.load(open(os.path.join(os.path.dirname(__file__), 'typed.json'), encoding='utf-8'))


def fix(grade, idx, body, byline):
    f = FIXES.get((grade, idx), {})
    if 'typed' in f:
        return [dict(text=t, typed=True) for t in TYPED[f['typed']]], f.get('byline', byline)
    body = body[f.get('drop', 0):len(body) - f.get('drop_last', 0)]
    body = [b for b in body if not JUNK.match(b['text'].strip())]
    for a, z in f.get('sub', []):
        body = [dict(b, text=re.sub(r'\s+', ' ', b['text']).replace(a, z)) for b in body]
    body += [dict(text=t, x=0, page=None, block=None) for t in f.get('add', [])]
    return body, f.get('byline', byline)


VERSE = {'I Am', 'Verb To Be', 'Verb To Be II', 'Daisies', 'Songs of the Witches', 'A Day at the Beach', 'I Wanna Do Right',
         'Fight Song', 'You Can', 'When You Are Old', 'Jimmy Jet and His TV Set', 'Old McDonald had a Farm', '"Bad Girl"',
         'It is Raining', 'You Learn', 'The House on the Hill', 'To the River', 'Be Glad Your Nose is on Your Face'}


def clean(t):
    t = re.sub(r'(^|\s)\|(\s|$)', r'\1I\2', t)
    return re.sub(r'\s+', ' ', t).strip()


def layout(grade, title, body):
    """Grades 1-2 and verse keep printed lines; prose is rejoined into paragraphs at first-line indents."""
    if body and body[0].get('typed'):
        return 'prose', [b['text'] for b in body]
    if grade <= 2 or title in VERSE:
        return 'lines', [clean(b['text']) for b in body]
    lefts = {}
    for b in body:
        k = (b.get('page'), b.get('block'))
        lefts[k] = min(lefts.get(k, 10**6), b.get('x', 0))
    paras, cur = [], ''
    for i, b in enumerate(body):
        t = clean(b['text'])
        k = (b.get('page'), b.get('block'))
        indent = b.get('x', 0) - lefts[k] > 45
        if cur and indent and re.search(r'[.!?”"’)]$', cur):
            paras.append(cur); cur = ''
        if cur.endswith('-') and t[:1].islower():
            cur = cur[:-1] + t
        elif cur.endswith('- ') :
            cur = cur[:-2] + t
        else:
            cur = (cur + ' ' + t).strip()
    if cur:
        paras.append(cur)
    return 'prose', [re.sub(r'([a-z])- ([a-z])', r'\1\2', x) for x in paras]

GROUPS = {
    1: [[9]] + [[p] for p in range(11, 21)] + [[22]],
    2: [[9], [11, 12, 13], [14], [15], [16, 17], [18], [19], [20], [21], [22, 23, 24], [25, 26, 27], [29]],
    3: [[9]] + [[p] for p in range(11, 20)] + [[20, 21], [23]],
    4: [[9]] + [[p] for p in range(12, 22)] + [[23]],
    5: [[9]] + [[p] for p in range(11, 19)] + [[20]],
    6: [[9]] + [[p] for p in range(11, 20)] + [[21]],
}

TOC = {
    1: ['The Cat', 'My Dog', 'The Sun', 'My Hat', 'The Bird', 'Rainy Day', 'The Market', 'Tim’s Ball', 'The Dog and Cat', 'My Family', 'My School', 'The Cat'],
    2: ['The Picnic', 'The Farm', 'The Birthday Party', 'My School Bag', 'The Zoo', 'The Beach', 'My Bike', 'The Train', 'The Library', 'The Raincoat', 'The Bakery', 'The Picnic'],
    3: ['The Ant and The Fly', 'Max’s Good Habit', 'Max’s Good Habit II', 'Amusement Park Problem', 'Amusement Park Problem II', 'The generosity of Rantideva', 'I Am', 'Verb To Be', 'Verb To Be II', 'Daisies', 'A Robot Dog', 'The Ant and The Fly'],
    4: ['My First Baseball Game', 'The Singing Plant', 'The Singing Plant II', 'A Glass of Cold Water', 'The Elephant and the Crocodile', 'Songs of the Witches', 'Songs of the Witches II', 'The Boy Who Cried Wolf', 'The Boy Who Cried Wolf II', 'A Day at the Beach', 'I Wanna Do Right', 'My First Baseball Game'],
    5: ['The Crocodile and the Monkey', 'Fight Song', 'You Can', 'Print Your Own Medicine', 'When You Are Old', 'Jimmy Jet and His TV Set', 'Pygmalion: Henry and Eliza', 'Old McDonald had a Farm', 'The Crocodile and the Monkey', 'The Crocodile and the Monkey'],
    6: ['"Bad Girl"', 'It is Raining', 'You Learn', 'The House on the Hill', 'The Golden Egg', 'To the River', 'Be Glad Your Nose is on Your Face', 'The Crocodile and the Monkey', 'Pygmalion: Henry and Eliza', 'My First Baseball Game', '"Bad Girl"'],
}

if __name__ == '__main__':
    grades = [int(g) for g in sys.argv[1:]] or list(range(1, 7))
    path = os.path.join(ROOT, 'database', 'level4-passages.json')
    data = json.load(open(path)) if os.path.exists(path) else {}
    for g in grades:
        data[str(g)] = extract(g, TOC[g])
        for p in data[str(g)]:
            flag = '' if p['stated'] == p['words'] else f'  <-- module says {p["stated"]}'
            print(f'G{g} {p["title"][:34]:34} {p.get("source",""):10} pages {p["pages"]} words {p["words"]}{flag}')
    json.dump(data, open(path, 'w'), ensure_ascii=False, indent=1)
