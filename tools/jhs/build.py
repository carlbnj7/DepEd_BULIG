"""Junior High School Levels 5-7 (Grades 7-10): drafts + answer keys -> SQL, cards, keys, module pages.

Reads:  tools/jhs/drafts/<module>.json (tools/jhs/parse.py), tools/jhs/keys/<module>.txt
Writes: database/migrations/043_jhs_levels5_7.sql, database/jhs-cards.json, database/answer-keys-jhs.json,
        database/jhs-meta.json, storage/jhs/<module>/page-NNN.webp

Key files, one line per card ("L.A.N" = lesson.activity.item as in drafts/<module>.txt):
  3.1.4 = b                 the letter of the right choice
  15.1.M = 1b 2a 3c 4d 5e   a matching table
  7.2.3 = =puzzle           a written answer that must be this word (or several: =puzzle|riddle)
Lines starting with # are notes. A choice card with no key line takes the module's own answer key (pre/post tests).
"""
import json, os, re, subprocess, sys
sys.path.insert(0, os.path.dirname(__file__))
from parse import MODULES

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
HERE = os.path.dirname(__file__)
PICTURES = json.load(open(os.path.join(HERE, 'pictures.json'))) if os.path.exists(os.path.join(HERE, 'pictures.json')) else {}   # redrawn pictures by module and PDF page
OVERRIDES = json.load(open(os.path.join(HERE, 'overrides.json'), encoding='utf-8')) if os.path.exists(os.path.join(HERE, 'overrides.json')) else {}
LEVEL_NAME = {6: 'Listening Comprehension and Vocabulary Development · Level 5', 7: 'Graded Reading Comprehension · Level 6', 8: 'Genuine Love for Reading · Level 7'}


def h(s):
    if s is None:
        return 'NULL'
    return f"CONVERT(0x{str(s).encode('utf-8').hex()} USING utf8mb4)" if s != '' else "''"


def module_keys(raw):
    out = {}
    last = 0
    for n, x in re.findall(r'(\d{1,2})\s*\.\s*([a-dA-D])\b', raw):
        n = int(n)
        if n <= last:      # a typo in the module's key ("2. B 12. C" for "11. B 12. C"): it follows the last number
            n = last + 1
        out[n] = x.lower(); last = n
    m = re.match(r'\s*([a-dA-D])\s*,\s*2\s*\.', raw)   # a key whose "1." is missing: "b, 2. b, ..."
    if m and 1 not in out:
        out[1] = m.group(1).lower()
    return out


HDR = re.compile(r'(Pre-?\s?test|Post-?\s?test|Pre-?\s?Assessment|Post-?\s?Assessment|Activity\s+\d+)', re.I)


def is_key_lesson(les):
    raw = les['label'] + '\n' + les['raw']
    n = len(re.findall(r'\b\d{1,2}\s*\.\s*[A-D]\b', raw))
    return 'answer key' in les['label'].lower() or (n >= 10 and len(raw.split()) < 4 * n)


def norm_label(t):
    t = t.lower().replace(' ', '').replace('-', '')
    return t.replace('assessment', 'test')


def table_keys(raw):
    """An answer-key page laid out in columns: a header row (Pre-test  Post-test / Activity 1  Activity 2 ...), then rows "1. B  1. C"."""
    out, heads = {}, []
    for line in raw.split('\n'):
        hs = HDR.findall(line)
        if hs:
            heads = [norm_label(x) for x in hs]
            continue
        es = re.findall(r'(\d{1,2})\s*\.\s*([A-Da-d])\b', line)
        for i, (n, x) in enumerate(es):
            if i < len(heads):
                out.setdefault(heads[i], {})[int(n)] = x.lower()
    return out


def load_keys(mid):
    f = os.path.join(HERE, 'keys', mid + '.txt')
    k = {}
    if os.path.exists(f):
        for line in open(f, encoding='utf-8'):
            line = line.split('#', 1)[0].strip() if not line.lstrip().startswith('#') else ''
            m = re.match(r'^(\d+)\.(\d+)\.(\w+)\s*=\s*(.+)$', line)
            if m:
                k[(int(m.group(1)), int(m.group(2)), m.group(3))] = m.group(4).strip()
    return k


BOILER = re.compile(r'^(Name\s*:|Grade\s*&\s*Section|Worksheet\s*#|Score\s*:|Date\s*:)', re.I)


def read_text(lead, direction):
    lines = [l for l in lead.split('\n') if not BOILER.match(l.strip())]
    t = '\n'.join(lines)
    if direction:
        t = re.sub(r'(Directions?|Instructions?)\s*[:\.]\s*.+?(\n\n|$)', '\n\n', t, count=1, flags=re.S | re.I)
    t = re.sub(r'\n{3,}', '\n\n', t).strip()
    # rejoin the PDF's short lines into paragraphs (a heading in capitals, a list item or a blank line keeps its break)
    out = []
    for line in t.split('\n'):
        ln = line.strip()
        if out and out[-1] and ln and not re.match(r'^(\d{1,2}[.)]|[•●▪\-–]|[A-Da-d][.)]\s)', ln) \
                and not (out[-1].isupper() and not ln.isupper()) and not out[-1].endswith(':') \
                and (ln[0].islower() or not re.search(r'[.!?"”’)]$', out[-1])):
            out[-1] += ' ' + ln
        else:
            out.append(ln)
    return '\n'.join(out)


def subtitle(les):
    for a in les['acts']:
        m = re.search(r'(?:Passage|Title)\s*:\s*(.+)', a['lead']) or re.search(r'read the (?:short )?story\s*["“]\s*(.+?)\s*["”]', a['lead'], re.I)
        if m:
            return ' '.join(m.group(1).split())[:120]
    for a in les['acts']:
        for l in a['lead'].split('\n'):
            s = l.strip()
            if s.isupper() and 3 < len(s) < 80 and not re.search(r'DIRECTION|ACTIVITY|PRE|POST', s):
                return s.title()
    return ''


def render(mid, pdf):
    out = os.path.join(ROOT, 'storage', 'jhs', mid)
    os.makedirs(out, exist_ok=True)
    import pymupdf
    doc = pymupdf.open(pdf)
    for i, page in enumerate(doc, 1):
        dst = os.path.join(out, f'page-{i:03d}.webp')
        if os.path.exists(dst):
            continue
        png = dst[:-5] + '.png'
        page.get_pixmap(dpi=110).save(png)
        subprocess.run(['convert', png, '-quality', '70', dst], check=True)
        os.remove(png)
    return len(doc)


def build(render_pages=True):
    sql = ['-- BULIG Levels 5, 6 and 7 for Junior High School (Grades 7-10). Additive. Select your EXISTING database first.',
           '-- Needs 041_jhs_grades.sql. Adds the Junior High modules; no accounts, progress or existing lessons are changed. Safe to import again.',
           'SET NAMES utf8mb4;', "INSERT IGNORE INTO grade_levels(id,name) VALUES(7,'Grade 7'),(8,'Grade 8'),(9,'Grade 9'),(10,'Grade 10');", 'START TRANSACTION;']
    cards, keys, meta, report = {}, {}, {}, []
    for mid, (pdf, level, grades, _) in MODULES.items():
        d = json.load(open(os.path.join(HERE, 'drafts', mid + '.json'), encoding='utf-8'))
        K = load_keys(mid)
        lessons = d['lessons']
        ov = OVERRIDES.get(mid, {})
        for ref, o in ov.items():   # tables the parser cannot read, typed in from the module
            li_, ai_ = map(int, ref.split('.'))
            act = lessons[li_ - 1]['acts'][ai_ - 1]
            for k_, v_ in o.items():
                if k_ == 'item_fix':      # fix one question: {"2": {"text": ..., "opts": [[letter, text], ...]}}
                    for n_, f_ in v_.items():
                        for it_ in act['items']:
                            if str(it_['n']) == n_:
                                it_.update({kk_: (vv_ if kk_ != 'opts' else [tuple(x) for x in vv_]) for kk_, vv_ in f_.items()})
                else:
                    act[k_] = v_
        modkey, tkeys = {}, {}
        for les in lessons:
            if is_key_lesson(les):
                for k_, v_ in table_keys(les['label'] + '\n' + les['raw']).items():
                    tkeys.setdefault(k_, {}).update(v_)
        for i, les in enumerate(lessons):
            if 'answer key' in les['label'].lower():
                j = i - 1
                while j >= 0 and 'answer key' in lessons[j]['label'].lower():
                    j -= 1
                if j >= 0:
                    modkey[j] = module_keys(les['raw'])
        pdfpath = os.path.join(ROOT, 'storage', 'jhs', pdf)
        pages = render(mid, pdfpath) if render_pages else 0
        missing = 0
        counters = {'pre': 0, 'learn': 0, 'post': 0}
        plan = []
        for li, les in enumerate(lessons, 1):
            if is_key_lesson(les):
                continue
            ph = les['phase']
            counters[ph] += 1
            pos = {'pre': 0, 'learn': 100, 'post': 900}[ph] + counters[ph]
            plan.append((li, les, ph, pos))
        for k_, (li_, les_, ph_, pos_) in enumerate(plan):   # a lesson owns its pages up to the next lesson's first page (pictures on a page of their own)
            nxt = plan[k_ + 1][1]['pages'][0] if k_ + 1 < len(plan) and plan[k_ + 1][1]['pages'] else (les_['pages'][-1] + 1 if les_['pages'] else 0)
            if les_['pages']:
                les_['pages'] = list(range(les_['pages'][0], max(les_['pages'][-1], nxt - 1) + 1))
        for g in grades:
            mt = f"{LEVEL_NAME[level]} · Grade {g} (Junior High School)"
            sql.append(f"INSERT INTO modules (id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),{level},{h(mt)},{g} WHERE NOT EXISTS (SELECT 1 FROM modules WHERE level_id={level} AND grade_level={g});")
            sql.append(f'SET @module_id=(SELECT id FROM modules WHERE level_id={level} AND grade_level={g} ORDER BY id LIMIT 1);')
            mlessons = []
            for li, les, ph, pos in plan:
                les.pop('_pics_done', None)
                st = subtitle(les)
                if ph != 'learn' or not st:   # "PRE-TEST Day 1-10 grade 7-8" -> "Pre-test · Days 1–10" (the grade is the module's)
                    st = re.sub(r'\s*\(?\s*grade\s*\d.*$', '', st or les['label'], flags=re.I).strip()
                    m_ = re.search(r'Days?\s*(\d+)\s*-\s*(\d+)', les['label'])
                    if ph != 'learn':
                        st = {'pre': 'Pre-test', 'post': 'Post-test'}[ph] + (f' · Days {m_.group(1)}–{m_.group(2)}' if m_ else '')
                title = {'pre': 'Pre-test', 'post': 'Post-test'}.get(ph, les['label'])
                if ph != 'learn' and re.search(r'Day\s*\d+\s*-\s*\d+|Days?\s*\d+\s*-\s*\d+', les['label']):
                    title += ' · ' + re.search(r'Days?\s*\d+\s*-\s*\d+', les['label'].replace('Day ', 'Days ')).group(0)
                obj = {'pre': 'Show what you know before the lessons.', 'post': 'Show how much you have learned.', 'learn': 'Read carefully, then answer each question.'}[ph]
                pg = les['pages']
                guide = f"{title}{' · ' + st if st else ''}\nModule PDF pages {', '.join(map(str, pg[:6]))}\n\nThe cards follow the module. Choices are checked with BULIG's answer key; written answers are checked by you."
                sql.append(f"INSERT INTO lessons (module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) SELECT @module_id,{pos},{h(title)},{h(st or les['label'])},{h(obj)},{h(guide)},{h(json.dumps(pg))},NULL,1 WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE module_id=@module_id AND position={pos});")
                sql.append(f'SET @lesson_id=(SELECT id FROM lessons WHERE module_id=@module_id AND position={pos});')
                sql.append(f"UPDATE lessons SET title={h(title)},subtitle={h(st or les['label'])},objectives={h(obj)},teacher_guide={h(guide)},source_pages={h(json.dumps(pg))} WHERE id=@lesson_id;")   # an earlier import is corrected
                if ph in ('pre', 'post'):
                    sql.append(f"INSERT INTO assessments (lesson_id,kind,title,rubric,source_page,source_note) SELECT @lesson_id,'{ph}',{h(f'Grade {g} · {title}')},{h('One point for each right answer.')},{pg[0] if pg else 1},{h('Module test, checked with the answer key.')} WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE lesson_id=@lesson_id AND kind='{ph}');")
                apos = 0
                for ai, a in enumerate(les['acts'], 1):
                    cs, kk = [], {}
                    rt = read_text(a['lead'], a['direction'])
                    pics = []
                    if not les.get('_pics_done'):
                        used = {i_['img'] for l_ in lessons for a_ in l_['acts'] for i_ in a_['items'] if i_.get('img')}   # puzzle pictures stay on the puzzle
                        pics = [x for pg_ in les['pages'] for x in PICTURES.get(mid, {}).get(str(pg_), []) if x not in used]
                        les['_pics_done'] = True
                    imgs = [{'src': x, 'alt': 'Picture for ' + (st or les['label']), 'label': ''} for x in pics]
                    if len(rt) > 60 or imgs:   # the story (and its pictures) is read first
                        cs.append(dict(title='Read', text=rt if len(rt) > 60 else 'Look at the picture.', images=imgs, choices=[]))
                    vocab = [it for it in a['items'] if not it['opts'] and not it.get('match') and re.match(r'^[A-Za-z][A-Za-z ]{1,25}\s*[-–]\s*\S.*\bExample', it['text'])]
                    if vocab:   # a vocabulary list (word - meaning. Example: ...): read, nothing to answer
                        cs.append(dict(title='Read', text='Vocabulary\n' + '\n'.join(f"{it['n']}. {it['text']}" for it in vocab), images=[], choices=[]))
                    for it in a['items']:
                        if it in vocab:
                            continue
                        if it.get('match'):
                            mm = it['match']
                            cs.append(dict(title='Matching', text='Column A\n' + '\n'.join(f'{n}. {t}' for n, t in mm['left']), images=[], choices=[f'{k}. {t}' for k, t in mm['right']], ref=f'{mid} {li}.{ai}.M'))
                            v = K.get((li, ai, 'M'))
                            if v:
                                kk[len(cs)] = {'t': 'match', 'a': {m.group(1): m.group(2).lower() for m in re.finditer(r'(\d+)\s*([a-jA-J])', v)}}
                            else:
                                missing += 1
                            continue
                        n = it['n']
                        if it['opts']:
                            ch = [f'{k}. {v}' for k, v in it['opts']]
                            cs.append(dict(title='Question', text=f"{n}. {it['text']}", images=[], choices=ch, ref=f'{mid} {li}.{ai}.{n}'))
                            v = K.get((li, ai, str(n)))
                            if v is None and li - 1 in modkey and ph != 'learn':
                                v = modkey[li - 1].get(n)
                            if v is None and len(les['acts']) == 1:
                                v = tkeys.get(norm_label(les['label']), {}).get(n) or tkeys.get({'pre': 'pretest', 'post': 'posttest'}.get(ph, '-'), {}).get(n)
                            if v and re.fullmatch(r'[a-hA-H](\|[a-hA-H])+', v):   # more than one right choice
                                hit = [c for c in ch for x in v.lower().split('|') if c.startswith(x + '.')]
                                kk[len(cs)] = {'t': 'card', 'alts': [[{'k': 'eq', 'v': c.lower()}] for c in hit], 'w': ' or '.join(hit)}
                                continue
                            if v and len(v) == 1 and v.lower() in 'abcdefgh':
                                hit = [c for c in ch if c.startswith(v.lower() + '.')]
                                if hit:
                                    kk[len(cs)] = {'t': 'choice', 'a': hit[0]}
                                    continue
                            missing += 1
                        else:
                            t_ = it['text']
                            pair = re.search(r'\(\s*([^()/]{1,30}?)\s*/\s*([^()/]{1,30}?)\s*\)', t_)
                            circ = re.search(r'circle the answer that best defines', a['direction'], re.I)
                            bank = a.get('bank') or []
                            ch = []
                            many = re.search(r'\(\s*([^(),]{1,25}(?:\s*,\s*[^(),]{1,25}){1,4})\s*\)', t_)
                            if level != 6:   # Levels 6-7: written answers stay written (Level 7 prompts list examples in parentheses)
                                pair = many = None
                            if pair:
                                ch = [pair.group(1).strip(), pair.group(2).strip()]
                            elif many:
                                ch = [x.strip() for x in many.group(1).split(',')]
                            elif circ and len(re.split(r'\s{2,}', t_.strip())) >= 3:
                                parts = re.split(r'\s{2,}', t_.strip()); t_ = parts[0]; ch = parts[1:]
                            elif bank and '__' in t_:
                                ch = list(bank)
                            v = K.get((li, ai, str(n)))
                            if ch:
                                cs.append(dict(title='Question', text=f"{n}. {t_}", images=[], choices=ch, ref=f'{mid} {li}.{ai}.{n}'))
                                hit = [c for c in ch if v and v.startswith('=') and c.lower() == v[1:].strip().lower()]
                                if hit:
                                    kk[len(cs)] = {'t': 'choice', 'a': hit[0]}
                                else:
                                    missing += 1
                                continue
                            cs.append(dict(title='Write', text=f"{n}. {it['text']}", images=[{'src': it['img'], 'alt': it['text'], 'label': ''}] if it.get('img') else [], choices=[], ref=f'{mid} {li}.{ai}.{n}w'))
                            if v and v.startswith('~'):   # a sentence answer: it must use one of these words
                                ws = [re.sub(r'[^a-z0-9]', '', x.lower()) for x in v[1:].split('|') if x.strip()]
                                kk[len(cs)] = {'t': 'card', 'alts': [[{'k': 'has', 'v': ws, 'n': 1}]], 'w': 'An answer that mentions ' + ' or '.join(x.strip() for x in v[1:].split('|'))}
                            if v and v.startswith('='):
                                alts = [x.strip() for x in v[1:].split('|') if x.strip()]
                                kk[len(cs)] = {'t': 'card', 'alts': [[{'k': 'eq', 'v': x.lower()}] for x in alts], 'w': ' or '.join(alts)}
                    if not cs:
                        continue
                    if not any(c['title'] != 'Read' for c in cs):
                        cs.append(dict(title='Write', text=(a['direction'] or 'Write your answer.'), images=[], choices=[]))
                    apos += 1
                    instr = a['direction'] or ('Read the passage, then answer the questions.' if cs[0]['title'] == 'Read' else 'Answer each question.')
                    cards[f'{level}:{g}:{pos}:{apos}'] = dict(instruction=a['direction'], cards=cs)
                    if kk:
                        keys[f'{level}:{g}:{pos}:{ph}:{apos}'] = kk
                    assessment = f"(SELECT id FROM assessments WHERE lesson_id=@lesson_id AND kind='{ph}')" if ph in ('pre', 'post') else 'NULL'
                    prompt = '\n\n'.join(c['text'] for c in cs)[:6000]
                    atitle = (les['label'] if ph == 'learn' else title) + (f' · Part {apos}' if len(les['acts']) > 1 else '')
                    sql.append('INSERT INTO activities (lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) '
                               f"SELECT @lesson_id,{assessment},'{ph}',{apos},{h(atitle)},'open',{h(instr)},{h(prompt)},NULL,'[]',{h(instr)},NULL,10,{pg[0] if pg else 1},'',1,1,'answer' "
                               f"WHERE NOT EXISTS (SELECT 1 FROM activities WHERE lesson_id=@lesson_id AND phase='{ph}' AND position={apos});")
                    sql.append(f"SET @activity_id=(SELECT id FROM activities WHERE lesson_id=@lesson_id AND phase='{ph}' AND position={apos});")
                    sql.append(f"UPDATE activities SET title={h(atitle)},instructions={h(instr)},prompt={h(prompt)},narration={h(instr)},source_page={pg[0] if pg else 1},published=1 WHERE id=@activity_id;")
                    sql.append(f"INSERT INTO questions (activity_id,content,grading) SELECT @activity_id,{h(prompt)},'teacher' WHERE NOT EXISTS (SELECT 1 FROM questions WHERE activity_id=@activity_id);")
                    if ph in ('pre', 'post'):
                        sql.append("INSERT IGNORE INTO assessment_questions(assessment_id,question_id) SELECT a.assessment_id,q.id FROM activities a JOIN questions q ON q.activity_id=a.id WHERE a.id=@activity_id AND a.assessment_id IS NOT NULL;")
                mlessons.append(dict(position=pos, phase=ph, title=title, pages=les['pages']))
            meta.setdefault(str(level), {})[str(g)] = dict(page_count=pages, pupil_pages=sorted({p for _, les, _, _ in plan for p in les['pages']}), lessons=mlessons, folder=f'jhs/{mid}', pdf=f'jhs/{pdf}')
        report.append(f'{mid}: {len(plan)} lessons, {missing // len(grades) if grades else missing} choice/matching cards without a key')
    sql.append("INSERT IGNORE INTO schema_migrations(version) VALUES('043_jhs_levels5_7');")
    sql.append('COMMIT;')
    open(os.path.join(ROOT, 'database', 'migrations', '043_jhs_levels5_7.sql'), 'w').write('\n'.join(sql) + '\n')
    json.dump({'version': 'jhs-1', 'cards': cards}, open(os.path.join(ROOT, 'database', 'jhs-cards.json'), 'w'), ensure_ascii=False)
    json.dump({'keys': keys, 'notes': {}}, open(os.path.join(ROOT, 'database', 'answer-keys-jhs.json'), 'w'), ensure_ascii=False)
    json.dump(meta, open(os.path.join(ROOT, 'database', 'jhs-meta.json'), 'w'), indent=1)
    return report


if __name__ == '__main__':
    for r in build(render_pages='--no-pages' not in sys.argv):
        print(r)
