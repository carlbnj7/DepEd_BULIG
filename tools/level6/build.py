"""Level 6 (Graded Reading Comprehension) build, one module per grade (bulig_levels id 7).

Reads the hand-checked card scripts tools/level6/script/gN.txt (first drafted by draft.py from the PDF
text, then corrected against the module pages) and writes:
  public/assets/images/level6/gN/pNNN-k.webp   story pictures, cut out one by one (no page text inside)
  database/level6-cards.json                     cards per activity, key "grade:lessonpos:activitypos"
  database/level6-meta.json                      per-grade lessons, pupil/teacher pages
  database/migrations/012_level6_content.sql     modules, lessons, activities (safe to import again)
Page renders for teachers (storage/level6/gN/page-NNN.webp) are made once by render_pages().

Script format (one activity = one "=== L.U | title | skill | pages ..." block):
  pics: p010-1 p010-2   pictures cut from the unit's pages (shown on the first story card unless img: is used)
  ins: text             directions (before the first card: the deck instruction; later: lead line of the next card)
  read: Title           story card (lines joined into paragraphs; a line starting with ¶ starts a paragraph)
  read*: Title          story card that keeps its line breaks (poems, short primary stories)
  img: p010-1 [label]   picture on the current card
  speed: 115            reading-speed timer on the story card (number of words)
  q 1: Question         question card; following "| a. … | b. …" lines are its choices
  rf 1: Sentence        Reality-or-Fantasy card (choices R / F)
  seq: lead             sequencing card; following "- event" lines are the events
  match: Cause | Effect matching card; "A: 1. …" lines are column A, "B: A. …" lines are column B
  lead: text            lead line on the next card
  # comment             ignored
Plain lines continue the current card (question text, or the last choice after choices).
"""
import hashlib, json, os, re, sys
import numpy as np
import pymupdf
from PIL import Image

sys.path.insert(0, os.path.dirname(__file__))
from extract import page_pictures
from plan import plan

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
HERE = os.path.dirname(__file__)
LEVEL_ID = 7
PUB = os.path.join(ROOT, 'public', 'assets', 'images', 'level6')
CODE = {'pre': 1, 'lesson': 2, 'post': 3, 'check': 5}
RF = ['R – Reality', 'F – Fantasy']


def h(s):
    if s is None:
        return 'NULL'
    return f"CONVERT(0x{str(s).encode('utf-8').hex()} USING utf8mb4)" if s != '' else "''"


# ---------------------------------------------------------------- script parser
KEYS = {'pics', 'ins', 'lead', 'read', 'read*', 'img', 'speed', 'q', 'rf', 'seq', 'match', 'A', 'B', 'vocab'}


def parse(g):
    units, cur, card, ins_pending, in_ins = {}, None, None, [], False
    lines = open(os.path.join(HERE, 'script', f'g{g}.txt'), encoding='utf-8').read().split('\n')

    def new_card(**kw):
        nonlocal card, ins_pending
        c = dict(title='', text='', images=[], choices=[], lead='', heading='')
        c.update(kw)
        if ins_pending:
            c['lead'] = (' '.join(ins_pending) + (' · ' + c['lead'] if c['lead'] else '')).strip()
            ins_pending = []
        cur['cards'].append(c)
        card = c
        return c

    for raw in lines:
        line = raw.rstrip()
        s = line.strip()
        if not s or s.startswith('#'):
            continue
        if s.startswith('==='):
            parts = [p.strip() for p in s[3:].split('|')]
            cur = dict(id=parts[0], title=parts[1], skill=parts[2], instruction='', cards=[], pics=[], explicit_img=False)
            for p in parts[3:]:
                if p.startswith('pages'):
                    cur['pages'] = [int(x) for x in p.split()[1].split(',')]
            units[parts[0]] = cur; card = None; ins_pending = []
            continue
        if cur is None:
            continue
        m = re.match(r'^(\w+\*?)(?:\s+(\d{1,2}))?\s*:\s?(.*)$', s)
        key = m.group(1) if m else None
        if key not in KEYS:
            m, key = None, None
        last_ins = cur['instruction'] if in_ins == 'deck' else (ins_pending[-1] if ins_pending else '') if in_ins else ''
        if key is None and in_ins and not s.startswith(('|', '- ')) and not re.search(r'[.:!?)]\s*$', last_ins):   # directions on several lines
            t = s.lstrip('¶ ').strip()
            if in_ins == 'deck':
                cur['instruction'] += ' ' + t
            else:
                ins_pending[-1] += ' ' + t
            continue
        in_ins = False
        if key == 'pics':
            cur['pics'] = m.group(3).split()
        elif key == 'ins':
            if not any(c['title'] != 'Read' for c in cur['cards']):
                cur['instruction'] = (cur['instruction'] + ' ' + m.group(3)).strip(); in_ins = 'deck'
            else:
                ins_pending.append(m.group(3)); in_ins = 'lead'
        elif key == 'lead':
            ins_pending.append(m.group(3))
        elif key in ('read', 'read*'):
            new_card(title='Read', heading=m.group(3), keep=key == 'read*', paras=[])
        elif key == 'img':
            pid, _, label = m.group(3).partition(' ')
            if card is None:
                new_card(title='Read', keep=False, paras=[])
            card['images'].append(dict(id=pid, label=label.strip()))
            cur['explicit_img'] = True
        elif key == 'vocab':
            cur.setdefault('vocab', []).extend(w.strip() for w in re.split(r'\s*[,;]\s*', m.group(3)) if w.strip())
        elif key == 'speed':
            rd = [c for c in cur['cards'] if c['title'] == 'Read']
            (rd[-1] if rd else card)['speed'] = int(m.group(3))
        elif key in ('q', 'rf') and m.group(2):
            new_card(title=m.group(2), text=m.group(3), choices=list(RF) if key == 'rf' else [], kind=key)
        elif key == 'seq':
            new_card(title='Order', text=m.group(3) or 'Write 1, 2, 3, 4, 5 to show the order of the events in the story.', kind='seq')
        elif key == 'match':
            a, _, b = m.group(3).partition('|')
            new_card(title='Matching', text='', kind='match', col_a=a.strip() or 'Column A', col_b=b.strip() or 'Column B', items=[])
        elif key in ('A', 'B') and card is not None and card.get('kind') == 'match':
            if key == 'A':
                card['items'].append(m.group(3))
            else:
                card['choices'].append(m.group(3))
        elif s.startswith('|'):
            for c in [x.strip() for x in s[1:].split(' | ')]:
                if c:
                    card['choices'].append(c.lstrip('| ').strip())
        elif s.startswith('- ') and card is not None and card.get('kind') == 'seq':
            card['choices'].append(s[2:].strip())
        else:   # continuation
            if card is None:
                new_card(title='Read', keep=False, paras=[])
            if card['title'] == 'Read':
                para = s.startswith('¶')
                t = s.lstrip('¶ ').strip()
                title_line = len(card['paras']) == 1 and not card.get('heading') and len(card['paras'][0]) < 60
                if para and card['paras'] and not card.get('keep') and not title_line and not re.search(r'[.!?”"’)\]]\s*$', card['paras'][-1]):
                    para = False            # a line wrap, not a new paragraph
                if card.get('keep') or para or not card['paras']:
                    card['paras'].append(t)
                else:
                    card['paras'][-1] += ' ' + t
            elif s.startswith('¶') and card['title'] != 'Read':
                s = s.lstrip('¶ ').strip()
                if card['choices'] and card.get('kind') not in ('seq',):
                    card['choices'][-1] += ' ' + s
                else:
                    card['text'] = (card['text'] + ' ' + s).strip()
            elif card.get('kind') == 'match':
                if card['choices']:
                    card['choices'][-1] += ' ' + s
                elif card['items']:
                    card['items'][-1] += ' ' + s
            elif card['choices'] and card.get('kind') not in ('seq',):
                card['choices'][-1] += ' ' + s
            elif card.get('kind') == 'seq' and card['choices']:
                card['choices'][-1] += ' ' + s
            else:
                card['text'] = (card['text'] + ' ' + s).strip()
    for u in units.values():
        if u.get('vocab'):
            rd = next((c for c in u['cards'] if c['title'] == 'Read'), None)
            if rd is not None:
                rd['words'] = u['vocab']; rd['words_label'] = 'Key words'
        for c in u['cards']:
            if c['title'] == 'Read':
                paras = c.pop('paras')
                if not c.get('heading') and paras and len(paras[0]) < 60 and not re.search(r'[.!?,”"]$', paras[0]):
                    c['heading'] = paras.pop(0)         # the story title printed above the story
                if c.get('heading', '').isupper():
                    c['heading'] = titlecase(c['heading'])
                c['text'] = ('\n' if c.get('keep') else '\n\n').join(paras)
            elif c.get('kind') not in ('seq', 'match') and not any(x.startswith(('☐', '✓')) for x in c['choices']):
                resplit(c)
            if c.get('kind') == 'match':
                c['text'] = 'Column A\n' + '\n'.join(c.pop('items'))
            c['text'] = re.sub(r'[ \t]+', ' ', c['text']).strip()
            c['text'] = re.sub(r'\s-\s\.$', ' ______.', c['text'])
            c['choices'] = [re.sub(r'\s+', ' ', x).strip() for x in c['choices']]
    return units


def titlecase(t):
    small = {'a', 'an', 'and', 'the', 'of', 'in', 'on', 'to', 'for', 'at', 'from', 'with'}
    w = t.lower().split()
    return ' '.join(x if i and x in small else x[:1].upper() + x[1:] for i, x in enumerate(w))


def resplit(c):
    """Choices broken over lines ("B. … C." / "The workers …") or left in the question: split them A, B, C, D in order."""
    text = c['text']
    m = re.search(r'(?:^|\s)([Aa])\s*[.)]\s+\S', text)
    tail = ''
    if m and (not c['choices'] or re.match(r'[Bb]\s*[.)]', c['choices'][0]) or re.search(r'\b[Bb]\s*[.)]', text[m.start():])):
        tail, text = text[m.start():].strip(), text[:m.start()].strip()
    whole = ' '.join(([tail] if tail else []) + c['choices']).strip()
    if not whole:
        return
    upper = bool(re.match(r'\s*[A-D]', whole))
    letters = 'ABCD' if upper else 'abcd'
    parts, rest = [], whole
    for i, L in enumerate(letters):
        if i == 0:
            mm = re.match(r'\s*' + L + r'\s*[.)]+\s*', rest)
            if not mm:
                return
            rest = rest[mm.end():]
            continue
        mm = re.search(r'(?:^|\s)' + L + r'\s*[.)]+\s*', rest)
        if not mm:
            break
        parts.append(rest[:mm.start()].strip()); rest = rest[mm.end():]
    parts.append(rest.strip())
    parts = [re.sub(r'^[A-Da-d]\s*[.)]\s+', '', x) for x in parts]      # "C. C. angered" 
    c['text'] = text
    c['choices'] = [f'{letters[i]}. {p}' for i, p in enumerate(parts)]


# ---------------------------------------------------------------- pictures
_picdocs = {}


def pic_doc(g):
    if g not in _picdocs:
        d = pymupdf.open(os.path.join(ROOT, 'storage', 'level6', f'grade-{g}.pdf'))
        for p in d:          # pictures are cut without any text printed over them
            p.add_redact_annot(p.rect)
            p.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_REMOVE_IF_TOUCHED,
                               text=pymupdf.PDF_REDACT_TEXT_REMOVE)
        _picdocs[g] = d
    return _picdocs[g]


def trim(im, pad=6, thr=246):
    a = np.asarray(im.convert('L'))
    ys, xs = np.where(a < thr)
    if not len(xs):
        return None
    return im.crop((max(0, xs.min() - pad), max(0, ys.min() - pad), min(im.width, xs.max() + pad + 1), min(im.height, ys.max() + pad + 1)))


def cut_picture(g, pid, boxes_override):
    pn, k = int(pid[1:4]), int(pid.split('-')[1])
    out = os.path.join(PUB, f'g{g}', f'{pid}.webp')
    rel = f'assets/images/level6/g{g}/{pid}.webp'
    box = boxes_override.get(f'{g}:{pid}')
    if box is None:
        boxes = page_pictures(g, pn)
        if k > len(boxes):
            raise SystemExit(f'G{g}: picture {pid} not on page')
        box = boxes[k - 1]
    if not os.path.exists(out) or os.path.getmtime(out) < os.path.getmtime(__file__):
        page = pic_doc(g)[pn - 1]
        pix = page.get_pixmap(dpi=160, clip=pymupdf.Rect(*box))
        im = trim(Image.frombytes('RGB', (pix.width, pix.height), pix.samples)) or Image.frombytes('RGB', (pix.width, pix.height), pix.samples)
        if im.width > 900:
            im = im.resize((900, int(im.height * 900 / im.width)), Image.LANCZOS)
        os.makedirs(os.path.dirname(out), exist_ok=True)
        im.save(out, 'WEBP', quality=82)
    return rel


# ---------------------------------------------------------------- build
def narration(c):
    return ' '.join(x for x in [c.get('lead', ''), c.get('heading', ''), c['text'].replace('Column A\n', '')] + c['choices'] if x)


def build():
    P = plan()
    boxes_override = json.load(open(os.path.join(HERE, 'boxes.json'))) if os.path.exists(os.path.join(HERE, 'boxes.json')) else {}
    cards_out, meta = {}, dict(grades={})
    sql = ['-- BULIG Level 6: Graded Reading Comprehension, one module per grade (Grades 1–6). Safe to import again.',
           'SET NAMES utf8mb4;', 'START TRANSACTION;']
    total_l = total_a = 0
    problems = []
    for g in range(1, 7):
        doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level6', f'grade-{g}.pdf'))
        units = parse(g)
        cfg = P[g]
        mt = f'Graded Reading Comprehension · Level 6 · Grade {g}'
        sql.append(f"INSERT INTO modules (id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),{LEVEL_ID},{h(mt)},{g} WHERE NOT EXISTS (SELECT 1 FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g});")
        sql.append(f'SET @module_id=(SELECT id FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g} ORDER BY id LIMIT 1);')
        keytext = '\n\n'.join(f'PDF page {n}\n' + re.sub(r'\n\s*\n+', '\n', doc[n - 1].get_text()).strip() for n in cfg['teacher'] if n not in cfg.get('guides', {}).values())
        lessons_meta, pupil_pages, n_act = [], set(), 0
        for seq, les in enumerate(cfg['lessons'], 1):
            code = CODE[les['kind']]
            num = 0
            if les['kind'] == 'lesson':
                n_act += 1; num = n_act
            pos = seq * 1000 + code * 100 + num
            phase = {'pre': 'pre', 'post': 'post', 'check': 'post'}.get(les['kind'], 'learn')
            label = {'pre': les['title'], 'post': les['title'], 'check': les['title']}.get(les['kind']) or f'Activity {num}'
            acts = []
            for ui, u in enumerate(les['units'], 1):
                uid = f'{seq}.{ui}'
                su = units.get(uid)
                if not su or not su['cards']:
                    problems.append(f'G{g} {uid} {u["title"]}: no cards'); continue
                pics = []
                for c in su['cards']:
                    c['images'] = [dict(src=cut_picture(g, im['id'], boxes_override), alt=im['label'] or f'Picture for {u["title"]}', label=im['label'])
                                   for im in c['images']]
                    pics += [im['src'] for im in c['images']]
                if not su['explicit_img'] and su['pics']:
                    first = next((c for c in su['cards'] if c['title'] == 'Read'), su['cards'][0])
                    first['images'] = [dict(src=cut_picture(g, pid, boxes_override), alt=f'Picture for {u["title"]}', label='') for pid in su['pics']]
                    pics += [im['src'] for im in first['images']]
                for c in list(su['cards']):          # a title-only story card (direction pages): the title goes on the first question
                    if c['title'] == 'Read' and not c['text'] and not c['images'] and not c.get('words'):
                        i = su['cards'].index(c)
                        if i + 1 < len(su['cards']):
                            nx = su['cards'][i + 1]
                            nx['lead'] = (c.get('heading', '') + (' · ' + nx['lead'] if nx.get('lead') else '')).strip(' ·')
                            su['cards'].remove(c)
                if 'Speed' in u['skill']:        # reading-speed story: timer with the number of words
                    rd = next((c for c in su['cards'] if c['title'] == 'Read' and c['text']), None)
                    if rd is not None and not rd.get('speed'):
                        rd['speed'] = len(re.findall(r"[A-Za-z0-9’']+", rd['text']))
                for c in su['cards']:
                    if c['title'] == 'Read' and c['images'] and c['text']:
                        c['split'] = True           # picture left, story right
                    c['narration'] = narration(c)
                    for k in ('keep', 'kind'):
                        c.pop(k, None)
                    if not (c['text'] or c['choices'] or c['images'] or c.get('words')):
                        problems.append(f'G{g} {uid}: empty card {c["title"]}')
                instruction = su['instruction'] or 'Read the story carefully, then answer the questions.'
                key = f'{g}:{pos}:{ui}'
                cards_out[key] = dict(instruction=instruction, cards=su['cards'])
                text = '\n\n'.join(filter(None, [instruction] + [((c.get('heading') or '') + '\n' + c['text'] + ('\n' + '\n'.join(c['choices']) if c['choices'] else '')).strip() for c in su['cards']]))
                speed = next((c['speed'] for c in su['cards'] if c.get('speed')), None)
                acts.append(dict(title=su['title'], skill=su['skill'], pages=u['pages'], text=text, image=pics[0] if pics else None, images=pics,
                                 instruction=instruction, speed=speed, cards=len(su['cards'])))
                pupil_pages.update(u['pages'])
            if not acts:
                continue
            guide_pages = sorted({p for a in acts for p in a['pages']})
            guides = cfg.get('guides', {})
            guide_text = ''.join(f'\n\nLESSON GUIDE (module PDF page {guides[p]})\n' + re.sub(r'\n\s*\n+', '\n', doc[guides[p] - 1].get_text()).strip()
                                 for p in guide_pages if p in guides)
            skill = les.get('skill') or acts[0]['skill']
            guide = (f'{mt}\n{label}: {les["title"]} · skill: {skill} · module PDF page{"s" if len(guide_pages) > 1 else ""} {", ".join(map(str, guide_pages))}\n\n'
                     'Pupils read the story on its own card (Listen reads it aloud), then answer one question per card. '
                     'Choices, Reality/Fantasy, ordering of events and cause-and-effect matching follow the module. '
                     'Speed-reading stories have a timer that records the reading time and words per minute.'
                     + guide_text
                     + '\n\n' + '\n\n'.join(f'{a["title"]} (PDF page{"s" if len(a["pages"]) > 1 else ""} {", ".join(map(str, a["pages"]))})\n{a["text"]}' for a in acts)
                     + (f'\n\nANSWER KEY (teacher only)\n\n{keytext}' if keytext else ''))
            objective = {'pre': 'Show how well you understand what you read before the lessons.',
                         'post': 'Show how much your reading comprehension has grown.',
                         'check': 'Check what you have learned so far.'}.get(les['kind'], f'{skill}: read the story and answer the questions.')
            sql.append(f"INSERT INTO lessons (module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) SELECT @module_id,{pos},{h(label)},{h(les['title'] if les['kind'] == 'lesson' else skill if len(acts) == 1 else 'Reading comprehension test')},{h(objective)},{h(guide)},{h(json.dumps(guide_pages))},{h(acts[0]['image'])},1 WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE module_id=@module_id AND position={pos});")
            sql.append(f'SET @lesson_id=(SELECT id FROM lessons WHERE module_id=@module_id AND position={pos});')
            if phase in ('pre', 'post'):
                sql.append(f"INSERT INTO assessments (lesson_id,kind,title,rubric,source_page,source_note) SELECT @lesson_id,'{phase}',{h('Grade ' + str(g) + ' · ' + les['title'])},{h('Check the answers with the module answer key (in the teaching guide).')},{acts[0]['pages'][0]},{h('Pupils answer one question per card; the teacher checks with the answer key.')} WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE lesson_id=@lesson_id AND kind='{phase}');")
            for n, a in enumerate(acts, 1):
                total_a += 1
                assessment = f"(SELECT id FROM assessments WHERE lesson_id=@lesson_id AND kind='{phase}')" if phase in ('pre', 'post') else 'NULL'
                sql.append('INSERT INTO activities (lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) '
                           f"SELECT @lesson_id,{assessment},'{phase}',{n},{h(a['title'])},'open',{h(a['instruction'])},{h(a['text'])},{h(a['image'])},{h(json.dumps(a['images']))},{h(a['text'][:3000])},NULL,10,{a['pages'][0]},{h(a['text'][:1500])},1,1,'answer' "
                           f"WHERE NOT EXISTS (SELECT 1 FROM activities WHERE lesson_id=@lesson_id AND phase='{phase}' AND position={n});")
                sql.append(f"SET @activity_id=(SELECT id FROM activities WHERE lesson_id=@lesson_id AND phase='{phase}' AND position={n});")
                sql.append(f"INSERT INTO questions (activity_id,content,grading) SELECT @activity_id,{h(a['text'])},'teacher' WHERE NOT EXISTS (SELECT 1 FROM questions WHERE activity_id=@activity_id);")
                if phase in ('pre', 'post'):
                    sql.append("INSERT IGNORE INTO assessment_questions(assessment_id,question_id) SELECT a.assessment_id,q.id FROM activities a JOIN questions q ON q.activity_id=a.id WHERE a.id=@activity_id AND a.assessment_id IS NOT NULL;")
            lessons_meta.append(dict(position=pos, kind=les['kind'], label=label, title=les['title'], skill=skill, pages=guide_pages,
                                     activities=[dict(title=a['title'], pages=a['pages'], cards=a['cards'], speed=a['speed']) for a in acts]))
            total_l += 1
        meta['grades'][str(g)] = dict(page_count=len(doc), pupil_pages=sorted(pupil_pages), teacher_pages=cfg['teacher'], lessons=lessons_meta,
                                      folder=f'level6/g{g}', pdf=f'level6/grade-{g}.pdf')
        print(f'G{g}: {len(lessons_meta)} lessons, {sum(len(l["activities"]) for l in lessons_meta)} activities, '
              f'{sum(a["cards"] for l in lessons_meta for a in l["activities"])} cards')
    for d, r in ISSUES:
        sql.append(f'INSERT INTO content_issues (description,resolution) SELECT {h(d)},{h(r)} WHERE NOT EXISTS (SELECT 1 FROM content_issues WHERE BINARY description=BINARY {h(d)});')
    sql.append(f"UPDATE bulig_levels SET title={h('Graded Reading Comprehension')},published=1 WHERE id={LEVEL_ID} AND (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id={LEVEL_ID} AND l.published=1)>={total_l};")
    sql.append("INSERT IGNORE INTO schema_migrations(version) VALUES('012_level6_content');")
    sql.append('COMMIT;')
    open(os.path.join(ROOT, 'database', 'migrations', '012_level6_content.sql'), 'w').write('\n'.join(sql) + '\n')
    json.dump(meta, open(os.path.join(ROOT, 'database', 'level6-meta.json'), 'w'), indent=1, ensure_ascii=False)
    json.dump(dict(version='2026-10-01', cards=cards_out), open(os.path.join(ROOT, 'database', 'level6-cards.json'), 'w'), ensure_ascii=False, separators=(',', ':'))
    os.makedirs(os.path.join(HERE, 'proof'), exist_ok=True)
    for g in range(1, 7):
        with open(os.path.join(HERE, 'proof', f'g{g}.txt'), 'w') as f:
            for k, d in cards_out.items():
                if not k.startswith(f'{g}:'):
                    continue
                f.write(f'\n##### {k} | {d["instruction"]}\n')
                for c in d['cards']:
                    f.write(f'[{c["title"]}]' + (f' <{c["lead"]}>' if c.get('lead') else '') + (f' «{c["heading"]}»' if c.get('heading') else '')
                            + (f' {{img {len(c["images"])}}}' if c['images'] else '') + (f' (speed {c["speed"]})' if c.get('speed') else '') + '\n')
                    if c['text']:
                        f.write('   ' + c['text'].replace('\n', '\n   ') + '\n')
                    for x in c['choices']:
                        f.write('     - ' + x + '\n')
    print('\n'.join(problems) or 'no problems')
    return total_l, total_a


def render_pages():
    for g in range(1, 7):
        d = pymupdf.open(os.path.join(ROOT, 'storage', 'level6', f'grade-{g}.pdf'))
        folder = os.path.join(ROOT, 'storage', 'level6', f'g{g}')
        os.makedirs(folder, exist_ok=True)
        for i, p in enumerate(d, 1):
            f = os.path.join(folder, f'page-{i:03d}.webp')
            if not os.path.exists(f):
                pix = p.get_pixmap(dpi=110)
                Image.frombytes('RGB', (pix.width, pix.height), pix.samples).save(f, 'WEBP', quality=72)


ISSUES = [
    ('Level 6 Grade 2 prints a lesson guide page before every activity sheet.', 'The guides are teacher-only and appear in each lesson’s teaching guide.'),
    ('Level 6 Grade 2 uses one test for the pretest and the post test.', 'The same items are used for both.'),
    ('Level 6 Grade 3 has 35 stories for 20 days (Days 6–20 have two stories each).', 'Each day is one lesson with its stories as separate activities.'),
    ('Level 6 Grade 5 has formative assessments after Activity 10 and after Activity 20 instead of a single post-test.', 'Both are included in that order.'),
    ('Level 6 answer keys are printed at the end of each module.', 'They are teacher-only, in the teaching guide of each lesson.'),
]

if __name__ == '__main__':
    render_pages()
    print('lessons, activities:', build())
