"""Level 5 (Listening Comprehension and Vocabulary Development) content build, one module per grade.

The six grade modules are mostly worksheets (matching to pictures, puzzles, word searches,
listening questions).  Each pupil activity shows the module page itself so wording and
pictures stay exactly as printed; the page text is the Listen narration, and pupils type or
speak their numbered answers.  Answer keys and lesson guides are teacher-only.

Usage:  python3 tools/level5/build_content.py
Reads:  storage/level5/grade-N.pdf
Writes: public/assets/images/level5/gN/pNNN.webp (pupil pages), storage/level5/gN/page-NNN.webp
        (every page, teacher view), database/level5-meta.json, database/migrations/011_level5_content.sql
Level 5 is bulig_levels id 6.  Requires PyMuPDF, Pillow, NumPy, tesseract-ocr.
"""
import json, os, re, subprocess, tempfile
import hashlib, sys
import numpy as np
import pymupdf
from PIL import Image
sys.path.insert(0, os.path.dirname(__file__))
from layout import page_layout

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LEVEL_ID = 6
PUB = os.path.join(ROOT, 'public', 'assets', 'images', 'level5')

# Lesson plan per grade, in module order: (kind, title, pages).  kind: pre / lesson / post.
# 'auto' groups pages that start with "Activity N" (continuation pages join the activity before).
G1 = [('pre', 'Pre-assessment', list(range(7, 14))),
      ('lesson', 'My Body', [15, 16, 17, 18]), ('lesson', 'My Toys', [19, 20, 21, 22]), ('lesson', 'My Pets', [23, 24, 25]),
      ('lesson', 'My Things', [26, 27, 28]), ('lesson', 'My Food', [29, 30, 31]), ('lesson', 'My Family', [32, 33, 34, 35]),
      ('lesson', 'My Relatives', [36, 37, 38]), ('lesson', 'My Home', [39, 40, 41]), ('lesson', 'Our Tools', [42, 43, 44]),
      ('lesson', 'Our Kitchen', [45, 46, 47, 48]), ('lesson', 'Me and My School', [49, 50, 51, 52]),
      ('lesson', 'In the Classroom', [53, 54, 55, 56]), ('lesson', 'In the Garden', [57, 58, 59, 60]),
      ('lesson', 'In the Canteen', [61, 62, 63, 64]), ('lesson', 'In the Library', [65, 66, 67, 68]),
      ('lesson', 'Keeping Our School Clean', [69, 70, 71, 72]), ('lesson', 'The Community Helpers', [73, 74, 75, 76, 77]),
      ('lesson', 'In the Plaza', [78, 79, 80, 81]), ('lesson', 'In the Market', [82, 83, 84, 85]),
      ('lesson', 'In the Farm', [86, 87, 88, 89]), ('lesson', 'Buildings Around Me', [90, 91, 92, 93]),
      ('post', 'Post-assessment', list(range(95, 104)))]
# A page may be (page, show-from heading, show-until heading) when an answer key shares it.
G2 = [('pre', 'Pre-test (Lessons 1–10)', [5, 6, (7, r'LISTENING COMPREHENSION PRETEST', None), 8, 9]),
      ('pre', 'Comprehensive Pre-test (Lessons 11–20)', [(10, r'COMPREHENSIVE PRETEST', None), 11, 12, 13, (14, None, r'ANSWER KEY')]),
      ('lesson', 'Flowers', [18, 19, 20]), ('lesson', 'Vegetables', [21, 22]), ('lesson', 'Fruits', [23, 24]),
      ('lesson', 'Trees', [25, 26]), ('lesson', 'Herbs', [27, 28]), ('lesson', 'Animals in the Grassland', [29, 30, 31]),
      ('lesson', 'Animals in the Woodland', [32, 33]), ('lesson', 'Animals in the Meadow', [34, 35]),
      ('lesson', 'Animals in the Forest', [36, 37]), ('lesson', 'Animals in the Sea', [38, 39]),
      ('lesson', 'Things Found in the Kitchen', [40, 41, 42]), ('lesson', 'Things Found in the Living Room', [43, 44]),
      ('lesson', 'Things Found in the Bedroom', [45, 46]), ('lesson', 'Things Found in the Bathroom', [47, 48]),
      ('lesson', 'Things Found in the Garage', [49, 50]), ('lesson', 'Landforms', [51, 52, 53]),
      ('lesson', 'Bodies of Water', [54, 55]), ('lesson', 'Mineral Resources', [56, 57]),
      ('lesson', 'Transportation', [58, 59]), ('lesson', 'Famous Locations', [60, 61, 62]),
      ('post', 'Post-test (Lessons 1–10)', [64, 65, 66, (67, None, r'ANSWER KEY')]),
      ('post', 'Comprehensive Post-test (Lessons 11–20)', [(68, r'COMPREHENSIVE POSTTEST', None), 69, 70, 71, 72])]
PLAN = {
    1: dict(plan=G1, teacher=[]),
    2: dict(plan=G2, teacher=[7, 10, 15, 68, 73]),
    3: dict(plan=[('pre', 'Pre-test', [6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 20]), ('auto', None, list(range(22, 43))), ('post', 'Post-test', [48, 49, 50, 51, 52, 54, 55, 56, 57])],
            teacher=[13, 43, 44, 45, 46, 47, 53, 58]),
    4: dict(plan=[('pre', 'Pre-assessment (Activities 1–10)', [8, 9, 10]), ('pre', 'Pre-assessment (Activities 11–20)', [11, 12, 13, 14, 15, 16]),
                  ('auto', None, list(range(18, 31))), ('post', 'Post-assessment (Activities 1–10)', [31, 32]),
                  ('auto', None, list(range(33, 43))), ('post', 'Post-assessment (Activities 11–20)', [44, 45, 46, 47, 48])],
            teacher=list(range(50, 70))),
    5: dict(plan=[('pre', 'Pre-test', [6, 7, 8, 9]), ('auto', None, list(range(11, 35))), ('post', 'Post-test', [36, 37, 38, 39]),
                  ('auto2', None, list(range(41, 63)))],
            teacher=list(range(63, 70))),
    6: dict(plan=[('pre', 'Pre-test', [6, 7, 8]), ('auto', None, list(range(10, 30))), ('post', 'Post-test', [31, 32])], teacher=[]),
}
# Activity names from each module's table of contents (auto-detected names are used for Grades 3 and 5).
TITLES = {
    4: ['Point Act Game', 'Find and Color It', 'Naming Me', 'Hidden Letters', 'Jumbled Letters', 'Link the Letters', 'Spiral Puzzle',
        'Crosswords', 'Antonym', 'Prep Paired', 'Fill in the Blank', 'Synonyms', 'Antonyms', 'Word Puzzle', 'Arranging Letters',
        'Correctly Spelled', 'Word Roll', 'Pair a Word', 'Be Bingo', 'Sentence Completion'],
    6: ['Act Game', 'Match and Color', 'Word Search', 'Hidden Letters', 'Jumbled Letters', 'Link the Letters', 'Spin Act', 'Crossword',
        'Opposite Words', 'Prep Paired', 'Fill in the Blank', 'Synonyms', 'Replace a Word', 'Word Puzzle', 'Arrange a Word',
        'Correctly Spelled', 'Roll Word Game', 'Word Match', 'Sentence Complete', 'Paragraph Filling'],
}
FIX_TITLE = {'6: Set A': 'Summer Fun', '9: Set B': 'A Gift from Uncle', 'Ramon’S Cake': 'Ramon’s Cake', 'Taking Care Of Animals': 'Taking Care of Animals',
             'The Magical Tree house Adventure': 'The Magical Tree House Adventure'}
PERFORM = re.compile(r'Pick a (square )?word card|Roll the dice|spinner|Be Bingo|Point Act|Act Game|Roll Word', re.I)
QUESTION = re.compile(r'Instruction|Direction|Question|Assessment|Listening Comprehension|Column|\n\s*[a-dA-D][.)]\s|_{3,}', re.I)
NOISE = re.compile(r'^(Name|Date|Score|Section|Teacher)\s*:?\s*_*\s*$|^\d{1,3}$|^PAGE\s|MERGEFORMAT|^[ivx]+$', re.I)


def h(s):
    if s is None:
        return 'NULL'
    return f"CONVERT(0x{str(s).encode('utf-8').hex()} USING utf8mb4)" if s != '' else "''"


def footer_top(page):
    """Top of the repeating banner at the page bottom (so it can be cropped off), else page height."""
    top = page.rect.height
    for info in page.get_image_info():
        x0, y0, x1, y1 = info['bbox']
        if y0 > page.rect.height * 0.8 and (x1 - x0) > page.rect.width * 0.6:
            top = min(top, y0)
    return top


def heading_y(page, pattern):
    pat = re.sub(r'\\? ', r'\\s*', pattern)
    for b in page.get_text('dict')['blocks']:
        if b['type'] == 0:
            for l in b['lines']:
                t = ''.join(s['text'] for s in l['spans'])
                if re.search(pat, t, re.I):
                    return l['bbox'][1] - 2
    raise SystemExit(f'heading {pattern!r} not found on page {page.number + 1}')


def text_between(page, y0, y1):
    out = []
    for b in sorted(page.get_text('dict')['blocks'], key=lambda b: (round(b['bbox'][1]), b['bbox'][0])):
        if b['type'] != 0 or b['bbox'][1] < y0 - 1 or b['bbox'][1] >= y1:
            continue
        for l in b['lines']:
            t = re.sub(r'\s+', ' ', ''.join(s['text'] for s in l['spans'])).strip()
            if t and not NOISE.match(t):
                out.append(t)
    return out


OCR_CACHE_FILE = os.path.join(os.path.dirname(__file__), 'ocr-cache.json')
OCR_CACHE = json.load(open(OCR_CACHE_FILE)) if os.path.exists(OCR_CACHE_FILE) else {}


def ocr_lines(pix, psm=3):
    key = hashlib.sha1(pix.samples).hexdigest() + ('' if psm == 3 else f'-{psm}')
    if key not in OCR_CACHE:
        OCR_CACHE[key] = _ocr(pix, psm)
        json.dump(OCR_CACHE, open(OCR_CACHE_FILE, 'w'))
    return OCR_CACHE[key]


def ocr_image(im, psm=7):
    """OCR a PIL image. psm='tsv' returns [(line text, [x0, y0, x1, y1] in pixels)] for a whole page."""
    key = hashlib.sha1(im.tobytes()).hexdigest() + f'-img{psm}'
    if key not in OCR_CACHE:
        with tempfile.TemporaryDirectory() as d:
            f = os.path.join(d, 'p.png'); im.save(f)
            if psm == 'tsv':
                r = subprocess.run(['tesseract', f, '-', '--psm', '3', 'tsv'], capture_output=True, text=True).stdout
                groups = {}
                for row in r.splitlines()[1:]:
                    c = row.split('\t')
                    if len(c) < 12 or not c[11].strip() or float(c[10]) < 30:
                        continue
                    k = (c[2], c[3], c[4])
                    x, y, w, h_ = map(int, c[6:10])
                    g = groups.setdefault(k, [[], [x, y, x + w, y + h_]])
                    g[0].append(c[11]); b = g[1]; g[1] = [min(b[0], x), min(b[1], y), max(b[2], x + w), max(b[3], y + h_)]
                OCR_CACHE[key] = [(' '.join(ws), bx) for ws, bx in groups.values()]
            else:
                r = subprocess.run(['tesseract', f, '-', '--psm', str(psm)], capture_output=True, text=True).stdout
                OCR_CACHE[key] = [re.sub(r'\s+', ' ', l).strip() for l in r.splitlines() if l.strip()]
        json.dump(OCR_CACHE, open(OCR_CACHE_FILE, 'w'))
    return OCR_CACHE[key]


def _ocr(pix, psm=3):
    with tempfile.TemporaryDirectory() as d:
        f = os.path.join(d, 'p.png'); pix.save(f)
        r = subprocess.run(['tesseract', f, '-', '--psm', str(psm)], capture_output=True, text=True).stdout
    return [re.sub(r'\s+', ' ', l).strip() for l in r.splitlines() if len(re.sub(r'[^A-Za-z]', '', l)) >= 2]


def trim(im, pad=16, thr=245):
    a = np.asarray(im.convert('L'))
    ys, xs = np.where(a < thr)
    if not len(xs):
        return im
    return im.crop((max(0, xs.min() - pad), max(0, ys.min() - pad), min(im.width, xs.max() + pad), min(im.height, ys.max() + pad)))


def narration(lines):
    t = ' '.join(lines)
    t = re.sub(r'_{2,}', ' blank ', t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t[:6000]


def clean_page(page):
    """Remove the stray Word field code ("PAGE \\* MERGEFORMAT n") printed on some module pages."""
    hit = False
    for b in page.get_text('dict')['blocks']:
        for l in b.get('lines', []):
            if 'MERGEFORMAT' in ''.join(s['text'] for s in l['spans']):
                page.add_redact_annot(pymupdf.Rect(l['bbox']) + (-2, -2, 2, 2)); hit = True
    if hit:
        page.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_NONE)


def page_asset(doc, g, pn, y0=0, y1=None, tag=''):
    page = doc[pn - 1]
    clean_page(page)
    y1 = min(y1 or page.rect.height, footer_top(page))
    clip = pymupdf.Rect(0, y0, page.rect.width, y1)
    pix = page.get_pixmap(dpi=150, clip=clip)
    im = trim(Image.frombytes('RGB', [pix.width, pix.height], pix.samples))
    if im.width > 1100:
        im = im.resize((1100, round(im.height * 1100 / im.width)), Image.LANCZOS)
    name = f'g{g}/p{pn:03d}{tag}.webp'
    os.makedirs(os.path.join(PUB, f'g{g}'), exist_ok=True)
    im.save(os.path.join(PUB, name), 'WEBP', quality=80)
    lines = text_between(page, y0, y1)
    if len(''.join(lines)) < 25:        # picture-only page: read the lettering from the image
        lines = ocr_lines(page.get_pixmap(dpi=200, clip=clip))
    return 'assets/images/level5/' + name, lines


def expand(g, plan, doc):
    """Resolve 'auto' groups into activities titled from their "Activity N" heading."""
    out = []
    for kind, title, pages in plan:
        if kind not in ('auto', 'auto2'):
            out.append((kind, title, pages, 0)); continue
        cur = None
        for pn in pages:
            t = doc[pn - 1].get_text()
            m = re.search(r'Activity\s*(\d+)\s*[:–—-]?\s*(?:Set\s*[AB]\s*)?', t, re.I)
            if len(t.strip()) < 15 and cur is None:
                continue
            if m and (cur is None or int(m.group(1)) != cur['n'] or re.search(r'Set\s*B', t[m.start():m.end() + 12], re.I)):
                cur = dict(n=int(m.group(1)), pages=[], title=activity_title(t, m)); out.append(cur)
            if cur is not None and len(t.strip()) >= 15:
                cur['pages'].append(pn)
        for i, c in enumerate(out):
            if isinstance(c, dict):
                t = TITLES[g][c['n'] - 1] if g in TITLES else FIX_TITLE.get(c['title'], c['title'])
                out[i] = ('lesson2' if kind == 'auto2' else 'lesson', t, c['pages'], c['n'])
    return out


def activity_title(t, m):
    lines = [re.sub(r'\s+', ' ', l).strip(' “”"') for l in t.splitlines()]
    lines = [l for l in lines if l and not NOISE.match(l) and not re.match(r'^(Name|Date)\b', l, re.I) and not re.match(r'^PAGE', l)]
    head = re.sub(r'\s+', ' ', m.group(0)).strip(' :–—-')
    # The activity name is the line just before or after "Activity N" that is not directions.
    idx = next((i for i, l in enumerate(lines) if re.search(r'Activity\s*\d+', l, re.I)), 0)
    rest = re.sub(r'.*Activity\s*\d+\s*[:–—-]?\s*', '', lines[idx], flags=re.I).strip(' :–—-')
    rest = re.sub(r'^Set\s*[AB]\s*[:–—-]?\s*', '', rest, flags=re.I)
    cand = [rest] if len(rest) > 2 else []
    cand += [lines[j] for j in (idx + 1, idx + 2, idx - 1) if 0 <= j < len(lines)]
    for c in cand:
        c = re.sub(r'^Set\s*[AB]\s*[:–—-]?\s*', '', c, flags=re.I).strip(' :–—-“”"')
        if 2 < len(c) < 70 and not re.match(r'(Direction|Instruction|Name|Date|Activity)', c, re.I):
            return c.title() if c.isupper() else c
    return head


def build():
    meta, out = {'grades': {}}, [
        '-- BULIG Level 5 (Listening Comprehension and Vocabulary Development): additive content migration, one module per grade.',
        '-- Select your EXISTING database first. No accounts, progress, responses or existing lessons are changed. Safe to import again.',
        'SET NAMES utf8mb4;', 'START TRANSACTION;']
    total_l = total_a = 0
    for g in range(1, 7):
        doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf'))
        pic_doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf'))
        cleaned = set()
        cfg = PLAN[g]
        plan = expand(g, cfg['plan'], doc)
        mt = f'Listening Comprehension and Vocabulary Development · Level 5 · Grade {g}'
        out.append(f"INSERT INTO modules (id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),{LEVEL_ID},{h(mt)},{g} WHERE NOT EXISTS (SELECT 1 FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g});")
        out.append(f'SET @module_id=(SELECT id FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g} ORDER BY id LIMIT 1);')
        keytext = '\n'.join(f'PDF page {n}\n' + doc[n - 1].get_text().strip() for n in cfg['teacher'])
        lessons, pupil_pages, n_act = [], set(), {'lesson': 0, 'lesson2': 0}
        for seq, (kind, title, pages, modnum) in enumerate(plan, 1):
            code = {'pre': 1, 'lesson': 2, 'post': 3, 'lesson2': 4}[kind]
            num = 0
            if kind in n_act:
                n_act[kind] += 1; num = modnum or n_act[kind]
            pos = seq * 1000 + code * 100 + num
            phase = {'pre': 'pre', 'post': 'post'}.get(kind, 'learn')
            acts, guide_pages = [], []
            for i, spec in enumerate(pages):
                pn, top, bottom = spec if isinstance(spec, tuple) else (spec, None, None)
                page = doc[pn - 1]
                y0 = heading_y(page, top) if top else 0
                y1 = heading_y(page, bottom) if bottom else None
                tag = ('b' if top else '') + ('a' if bottom else '')
                if pn not in cleaned:     # pictures are cut without the text printed over them
                    pp = pic_doc[pn - 1]; pp.add_redact_annot(pp.rect)
                    pp.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_NONE, text=pymupdf.PDF_REDACT_TEXT_REMOVE)
                    cleaned.add(pn)
                clean_page(page)
                os.makedirs(os.path.join(PUB, f'g{g}'), exist_ok=True)
                rows, pics, lines = page_layout(doc, pic_doc, pn, y0, y1 or page.rect.height, footer_top(page), os.path.join(PUB, f'g{g}'),
                                                f'assets/images/level5/g{g}', f'p{pn:03d}{tag}', ocr_image)
                if len(''.join(lines)) < 25:        # picture-only page: read the lettering for Listen
                    lines = ocr_lines(page.get_pixmap(dpi=200, clip=pymupdf.Rect(0, y0, page.rect.width, min(y1 or page.rect.height, footer_top(page)))))
                    rows = [[[{'t': 'img', 'src': x, 'cap': '', 'grid': False}] for x in pics]] if pics else rows
                pupil_pages.add(pn); guide_pages.append(pn)
                text = '\n'.join(lines)
                img = pics[0] if pics else None
                q = bool(QUESTION.search('\n' + text))
                perform = bool(PERFORM.search(text))
                if not q and not perform:
                    a = dict(title=f'Listen: {title}', type='reference', mode='none', xp=5,
                             instructions='Listen as the page is read aloud. Look at the pictures and say the words.')
                elif perform:
                    a = dict(title=f'{title} · game', type='group', mode='perform', xp=10,
                             instructions='Play this activity with your teacher and classmates, then choose Done.')
                else:
                    part = sum(1 for x in acts if x['mode'] == 'answer') + 1
                    a = dict(title=f'{title} · page {part}' if part > 1 or len(pages) > 2 else f'{title} · questions', type='open', mode='answer', xp=10,
                             instructions='Listen and look at the page. Type or say your answers with their numbers, for example 1-b, 2-a.')
                a.update(page=pn, image=img, images=pics, text=text, rows=rows)
                acts.append(a)
            if not acts:
                continue
            guide = (f'{mt}\n{title} · module PDF page{"s" if len(guide_pages) > 1 else ""} {", ".join(map(str, guide_pages))}\n\n'
                     'Pupils see each module page with its text and pictures. Listen reads the page aloud; for listening items read the passage yourself if you prefer. '
                     'Pupils type or say their numbered answers; check them with the answer key below and give feedback.\n\n'
                     + '\n\n'.join(f'PDF page {a["page"]}\n{a["text"]}' for a in acts)
                     + (f'\n\nANSWER KEY (teacher only)\n\n{keytext}' if keytext else '\n\nThe module has no answer key for this grade.'))
            objective = {'pre': 'Show what you already know about these words and stories.', 'post': 'Show how much your listening and vocabulary have grown.'}.get(kind, f'Listen, learn and use new words about {title.lower()}.')
            out.append(f"INSERT INTO lessons (module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) SELECT @module_id,{pos},{h(label(kind, num))},{h(title)},{h(objective)},{h(guide)},{h(json.dumps(guide_pages))},{h(acts[0]['image'])},1 WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE module_id=@module_id AND position={pos});")
            out.append(f'SET @lesson_id=(SELECT id FROM lessons WHERE module_id=@module_id AND position={pos});')
            if phase in ('pre', 'post'):
                out.append(f"INSERT INTO assessments (lesson_id,kind,title,rubric,source_page,source_note) SELECT @lesson_id,'{phase}',{h(f'Grade {g} · {title}')},{h('Check the numbered answers with the module answer key.')},{acts[0]['page']},{h('Pupils answer on the module pages; the teacher checks with the answer key.')} WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE lesson_id=@lesson_id AND kind='{phase}');")
            for n, a in enumerate(acts, 1):
                total_a += 1
                assessment = f"(SELECT id FROM assessments WHERE lesson_id=@lesson_id AND kind='{phase}')" if phase in ('pre', 'post') else 'NULL'
                prompt = a['text'] or title
                PAGES[f"{g}:{a['page']}"] = dict(sha=hashlib.sha256(prompt.encode()).hexdigest(), rows=a['rows'])
                out.append('INSERT INTO activities (lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) '
                           f"SELECT @lesson_id,{assessment},'{phase}',{n},{h(a['title'])},'{a['type']}',{h(a['instructions'])},{h(prompt)},{h(a['image'])},{h(json.dumps(a['images']))},{h(narration(a['text'].splitlines()) or a['instructions'])},NULL,{a['xp']},{a['page']},{h(a['text'][:1500])},1,1,'{a['mode']}' "
                           f"WHERE NOT EXISTS (SELECT 1 FROM activities WHERE lesson_id=@lesson_id AND phase='{phase}' AND position={n});")
                out.append(f"SET @activity_id=(SELECT id FROM activities WHERE lesson_id=@lesson_id AND phase='{phase}' AND position={n});")
                out.append(f"INSERT INTO questions (activity_id,content,grading) SELECT @activity_id,{h(prompt)},'teacher' WHERE NOT EXISTS (SELECT 1 FROM questions WHERE activity_id=@activity_id);")
                if phase in ('pre', 'post'):
                    out.append("INSERT IGNORE INTO assessment_questions(assessment_id,question_id) SELECT a.assessment_id,q.id FROM activities a JOIN questions q ON q.activity_id=a.id WHERE a.id=@activity_id AND a.assessment_id IS NOT NULL;")
            lessons.append(dict(position=pos, kind=kind, title=title, pages=guide_pages, activities=[dict(page=a['page'], title=a['title'], mode=a['mode']) for a in acts]))
            total_l += 1
        folder = os.path.join(ROOT, 'storage', 'level5', f'g{g}')
        os.makedirs(folder, exist_ok=True)
        for i, page in enumerate(doc, 1):
            if os.path.exists(os.path.join(folder, f'page-{i:03d}.webp')) and 'MERGEFORMAT' not in page.get_text():
                continue
            clean_page(page)
            pix = page.get_pixmap(dpi=110)
            Image.frombytes('RGB', [pix.width, pix.height], pix.samples).save(os.path.join(folder, f'page-{i:03d}.webp'), 'WEBP', quality=70)
        meta['grades'][str(g)] = dict(page_count=len(doc), pupil_pages=sorted(pupil_pages), teacher_pages=cfg['teacher'], lessons=lessons, folder=f'level5/g{g}', pdf=f'level5/grade-{g}.pdf')
        print(f'G{g}: {len(lessons)} lessons, pupil pages {len(pupil_pages)}')
    for d, r in ISSUES:
        out.append(f'INSERT INTO content_issues (description,resolution) SELECT {h(d)},{h(r)} WHERE NOT EXISTS (SELECT 1 FROM content_issues WHERE BINARY description=BINARY {h(d)});')
    out.append(f"UPDATE bulig_levels SET title={h('Listening Comprehension and Vocabulary Development')},published=1 WHERE id={LEVEL_ID} AND (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id={LEVEL_ID} AND l.published=1)>={total_l};")
    out.append("INSERT IGNORE INTO schema_migrations(version) VALUES('011_level5_content');")
    out.append('COMMIT;')
    open(os.path.join(ROOT, 'database', 'migrations', '011_level5_content.sql'), 'w').write('\n'.join(out) + '\n')
    json.dump(meta, open(os.path.join(ROOT, 'database', 'level5-meta.json'), 'w'), indent=1, ensure_ascii=False)
    json.dump(PAGES, open(os.path.join(ROOT, 'database', 'level5-pages.json'), 'w'), ensure_ascii=False, separators=(',', ':'))
    return total_l, total_a


PAGES = {}


def label(kind, num):
    return {'pre': 'Pre-test', 'post': 'Post-test'}.get(kind) or (f'Activity {num}' if kind == 'lesson' else f'Activity {num} (second set)')


ISSUES = [
    ('Level 5 modules are mostly paper worksheets (matching lines, puzzles, crosswords, word searches).', 'Each pupil activity shows the module page as printed; pupils type or say their numbered answers for the teacher to check.'),
    ('Level 5 Grades 1 and 2 cover pages say “Level 4 – Vocabulary Development”.', 'Shown as Level 5, as named on the zip file and the other grades.'),
    ('Level 5 Grade 2 answer keys are printed on the same pages as the tests (PDF p7, p10, p15, p68, p73).', 'The keys are cut off the pupil pages and shown only in the teacher guide.'),
    ('Level 5 Grade 1 table of contents page numbers do not match the PDF pages after “In the Garden”.', 'Lessons follow the PDF pages.'),
    ('Level 5 Grade 3 skips Activity 8 and has two Activity 14 pages (Set A and Set B).', 'Kept as printed.'),
    ('Level 5 Grade 5 has a second set of Activities 1–20 after the post-test.', 'Included after the post-test as “second set”.'),
    ('Level 5 Grade 1 and Grade 6 modules have no answer key.', 'Teachers check answers from the pages.'),
]

if __name__ == '__main__':
    print('lessons, activities:', build())
