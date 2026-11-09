"""Level 4 (Fluency) for Junior High School, Grades 7-10 (one module for all four grades).

The JHS module has a pre-test passage, 25 practice passages (10 poems, 15 stories) and two
post-tests (Grades 7-8, Grades 9-10). Each grade 7-10 gets its own module row with the same
lessons, so the existing per-grade code works unchanged; only the post-test differs.

Usage:  python3 tools/level4/build_jhs.py
Reads:  storage/level4/jhs.pdf
Writes: database/migrations/042_level4_jhs.sql, database/level4-meta.json (grades 7-10),
        storage/level4/jhs/page-NNN.webp (needs ImageMagick `convert` for WebP).
"""
import json, os, re, subprocess, sys
import pymupdf
sys.path.insert(0, os.path.dirname(__file__))
from build_content import GUIDE, h, LEVEL_ID, POST_POSITION

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
PDF = os.path.join(ROOT, 'storage', 'level4', 'jhs.pdf')
GRADES = (7, 8, 9, 10)
SKIP = {'PART I', 'PART II', 'PART III', 'FLUENCY POEMS', 'ORIGINAL FLUENCY STORIES', 'POST-ASSESSMENT'}


def parse(doc):
    lines, page_of = [], []
    for pno, page in enumerate(doc, 1):
        for l in page.get_text().split('\n'):
            lines.append(l.rstrip()); page_of.append(pno)
    start = next(i for i, l in enumerate(lines) if l.strip() == 'PRE-ASSESSMENT')
    items, cur = [], None
    for i in range(start, len(lines)):
        s = lines[i].strip()
        m = re.match(r'^Activity (\d+)$', s)
        if s in ('PRE-ASSESSMENT', 'POST-ASSESSMENT') or m or re.match(r'^Grade \d+ and \d+ Posttest Passage$', s):
            if s == 'POST-ASSESSMENT':
                continue
            kind = 'pre' if s == 'PRE-ASSESSMENT' else 'learn' if m else 'post'
            cur = dict(kind=kind, n=int(m.group(1)) if m else 0, raw=[], pages=[], label=s)
            items.append(cur); continue
        if cur is None or 'stated' in cur:
            continue
        w = re.match(r'^Word Count:\s*(\d+)', s)
        if w:
            cur['stated'] = int(w.group(1)); continue
        if re.fullmatch(r'\d{1,3}', s) or s in SKIP:
            continue
        cur['raw'].append(lines[i]);
        if s and page_of[i] not in cur['pages']:
            cur['pages'].append(page_of[i])
    out = []
    for it in items:
        raw = it.pop('raw')
        while raw and not raw[0].strip(): raw.pop(0)
        it['title'] = raw.pop(0).strip()
        while raw and not raw[0].strip(): raw.pop(0)
        it['byline'] = raw.pop(0).split(':', 1)[1].strip() if raw and raw[0].strip().startswith('Author:') else ''
        if it['kind'] == 'learn' and it['n'] <= 10:
            it['format'] = 'lines'
            it['paragraphs'] = [x.strip() for x in raw if x.strip()]
        else:
            it['format'] = 'prose'
            paras, buf = [], []
            for x in raw:
                if not x.strip():
                    if buf: paras.append(' '.join(buf)); buf = []
                else:
                    buf.append(x.strip())
            if buf: paras.append(' '.join(buf))
            # pdftotext gives no blank lines between paragraphs: start a new one after a line that ends a sentence well short of the margin
            it['paragraphs'] = paras
        it['words'] = len(' '.join(it['paragraphs']).split())
        out.append(it)
    return out


def body(p):
    return ('\n\n' if p['format'] == 'prose' else '\n').join(p['paragraphs'])


def prose_paragraphs(doc, p):
    """Rebuild paragraphs from line positions: a bigger gap between lines (about 24pt, not 16pt) starts a new paragraph."""
    if p['format'] != 'prose':
        return p['paragraphs']
    words = ' '.join(p['paragraphs']).split()
    lines = []
    for pno in p['pages']:
        prev = None
        for b in doc[pno - 1].get_text('dict')['blocks']:
            for l in b.get('lines', []):
                t = ''.join(s['text'] for s in l['spans']).strip()
                y = l['bbox'][1]
                gap = (y - prev) if prev is not None else 99
                prev = y
                if t and not re.fullmatch(r'\d{1,3}', t):   # page numbers
                    lines.append((t, gap))
    # find where the passage text starts
    head = ' '.join(words[:4])
    k = next((n for n, (t, _) in enumerate(lines) if t.startswith(head[:min(len(head), len(t))]) and t.split()[:2] == words[:2]), None)
    if k is None:
        return p['paragraphs']
    paras, buf, count = [], [], 0
    for t, gap in lines[k:]:
        if buf and gap > 20:
            paras.append(' '.join(buf)); count += len(paras[-1].split()); buf = []
        buf.append(t)
        if count + len(' '.join(buf).split()) >= len(words):
            paras.append(' '.join(buf)); break
    if ' '.join(paras).split() == words:
        return paras
    return p['paragraphs']


def render_pages(doc):
    out = os.path.join(ROOT, 'storage', 'level4', 'jhs')
    os.makedirs(out, exist_ok=True)
    for i, page in enumerate(doc, 1):
        dst = os.path.join(out, f'page-{i:03d}.webp')
        if os.path.exists(dst):
            continue
        png = dst[:-5] + '.png'
        page.get_pixmap(dpi=110).save(png)
        subprocess.run(['convert', png, '-quality', '72', dst], check=True)
        os.remove(png)


def build():
    doc = pymupdf.open(PDF)
    items = parse(doc)
    for p in items:
        p['paragraphs'] = prose_paragraphs(doc, p)
    pre = [p for p in items if p['kind'] == 'pre'][0]
    learn = [p for p in items if p['kind'] == 'learn']
    posts = [p for p in items if p['kind'] == 'post']
    assert len(learn) == 25 and len(posts) == 2, (len(learn), len(posts))
    out = ['-- BULIG Level 4 (Fluency) for Junior High School, Grades 7-10. Additive. Select your EXISTING database first.',
           '-- Adds one module per grade 7-10 with the same passages; no accounts, progress or existing lessons are changed. Safe to import again.',
           'SET NAMES utf8mb4;', "INSERT IGNORE INTO grade_levels(id,name) VALUES(7,'Grade 7'),(8,'Grade 8'),(9,'Grade 9'),(10,'Grade 10');", 'START TRANSACTION;']
    meta = json.load(open(os.path.join(ROOT, 'database', 'level4-meta.json'), encoding='utf-8'))
    for g in GRADES:
        post = posts[0] if g <= 8 else posts[1]
        ps = [pre] + learn + [post]
        mt = f'Fluency · Level 4 · Grade {g} (Junior High School)'
        out.append(f"INSERT INTO modules (id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),{LEVEL_ID},{h(mt)},{g} WHERE NOT EXISTS (SELECT 1 FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g});")
        out.append(f'SET @module_id=(SELECT id FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g} ORDER BY id LIMIT 1);')
        lessons = []
        for i, p in enumerate(ps):
            kind = p['kind']
            pos = 1 if kind == 'pre' else POST_POSITION if kind == 'post' else i + 1
            title = {'pre': 'Pre-test Passage', 'post': 'Post-test Passage'}.get(kind, f"{'Fluency Poem' if p['format'] == 'lines' else 'Fluency Story'} {p['n']}")
            words = p['stated']
            pages = p['pages']
            info = f"{title}: “{p['title']}”" + (f" by {p['byline']}" if p['byline'] else '') + f"\nModule PDF page{'s' if len(pages) > 1 else ''} {', '.join(map(str, pages))} · Number of words in the passage (module): {words}\n\n"
            guide = info + GUIDE.replace('module pages 6–8', 'module pages 6–7') + '\n\nPASSAGE TEXT\n\n' + p['title'] + '\n\n' + body(p)
            objective = {'learn': 'Read the passage aloud accurately, at a good speed and with expression.', 'pre': 'Read the passage aloud so your teacher can see how you read now.', 'post': 'Read the passage aloud again to show how much you have grown.'}[kind]
            out.append(f"INSERT INTO lessons (module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) SELECT @module_id,{pos},{h(title)},{h(p['title'])},{h(objective)},{h(guide)},{h(json.dumps(pages))},NULL,1 WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE module_id=@module_id AND position={pos});")
            out.append(f'SET @lesson_id=(SELECT id FROM lessons WHERE module_id=@module_id AND position={pos});')
            if kind in ('pre', 'post'):
                t = f"Grade {g} · {'Pre-test' if kind == 'pre' else 'Post-test'} · {p['title']}"
                rubric = f'Oral Reading Score = ({words} − miscues) ÷ {words} × 100. Independent 97–100%, Instructional 90–96%, Frustration 89% and below. Reading speed = {words} ÷ seconds × 60 words per minute.'
                out.append(f"INSERT INTO assessments (lesson_id,kind,title,rubric,source_page,source_note) SELECT @lesson_id,'{kind}',{h(t)},{h(rubric)},{pages[0]},{h('Phil-IRI oral reading passage. The teacher counts the miscues while listening; the app records reading time and a word-match guide.')} WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE lesson_id=@lesson_id AND kind='{kind}');")
            acts = []
            if kind in ('pre', 'post'):
                ready = ('Your teacher will listen as you read a passage aloud. Read at your own pace, clearly and with expression. '
                         'If you do not know a word, try your best and keep going. Tap Start reading when you begin and I’m done when you finish.')
                acts.append(dict(title='Let’s get ready', type='reference', mode='none', instructions='Read or listen, then choose Next.', prompt=ready, narration=ready, expected=None, xp=5))
            prompt = f"Title: {p['title']}\n" + (f"Author: {p['byline']}\n" if p['byline'] else '') + f"Words: {words}\n\n" + body(p)
            plain = ' '.join(p['paragraphs'])
            instr = 'Tap Start reading and read the whole passage aloud. Tap I’m done when you finish. Your teacher listens for accuracy, speed and expression.'
            acts.append(dict(title=f"Read aloud: {p['title']}", type='reading', mode='answer', instructions=instr, prompt=prompt,
                             narration=instr if kind != 'learn' else f"{p['title']}. " + plain, expected=plain, xp=20))
            excerpt = doc[pages[0] - 1].get_text().strip()[:1500]
            for n, a in enumerate(acts, 1):
                assessment = f"(SELECT id FROM assessments WHERE lesson_id=@lesson_id AND kind='{kind}')" if kind in ('pre', 'post') else 'NULL'
                out.append('INSERT INTO activities (lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) '
                           f"SELECT @lesson_id,{assessment},'{kind}',{n},{h(a['title'])},'{a['type']}',{h(a['instructions'])},{h(a['prompt'])},NULL,'[]',{h(a['narration'])},{h(a['expected'])},{a['xp']},{pages[0]},{h(excerpt)},1,1,'{a['mode']}' "
                           f"WHERE NOT EXISTS (SELECT 1 FROM activities WHERE lesson_id=@lesson_id AND phase='{kind}' AND position={n});")
                out.append(f"SET @activity_id=(SELECT id FROM activities WHERE lesson_id=@lesson_id AND phase='{kind}' AND position={n});")
                out.append(f"INSERT INTO questions (activity_id,content,grading) SELECT @activity_id,{h(a['prompt'])},'teacher' WHERE NOT EXISTS (SELECT 1 FROM questions WHERE activity_id=@activity_id);")
                if kind in ('pre', 'post'):
                    out.append("INSERT IGNORE INTO assessment_questions(assessment_id,question_id) SELECT a.assessment_id,q.id FROM activities a JOIN questions q ON q.activity_id=a.id WHERE a.id=@activity_id AND a.assessment_id IS NOT NULL;")
            lessons.append(dict(position=pos, phase=kind, title=p['title'], pages=pages, words=words))
        pupil_pages = sorted({n for p in ps for n in p['pages']})
        meta['grades'][str(g)] = dict(page_count=len(doc), pupil_pages=pupil_pages, lessons=lessons, folder='level4/jhs', pdf='level4/jhs.pdf')
    out.append("INSERT IGNORE INTO schema_migrations(version) VALUES('042_level4_jhs');")
    out.append('COMMIT;')
    open(os.path.join(ROOT, 'database', 'migrations', '042_level4_jhs.sql'), 'w').write('\n'.join(out) + '\n')
    json.dump(meta, open(os.path.join(ROOT, 'database', 'level4-meta.json'), 'w'), indent=1)
    render_pages(doc)
    return items


if __name__ == '__main__':
    its = build()
    bad = [(p['title'], p['stated'], p['words']) for p in its if abs(p['stated'] - p['words']) > 1]
    print(len(its), 'passages; word-count differences >1:', bad)
    print('paragraphs per story:', [len(p['paragraphs']) for p in its if p['format'] == 'prose'])
