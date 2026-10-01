"""Level 4 (Fluency) content build: one module per grade (Grades 1-6).

Every grade's module has a pre-test passage, practice passages and a post-test
passage, each with a picture and a Phil-IRI oral reading scoring table.  Each
passage becomes one lesson with a read-aloud activity.

Usage:  python3 tools/level4/build_content.py
Reads:  database/level4-passages.json, database/level4-images.json, storage/level4/grade-N.pdf
Writes: database/level4-meta.json and database/migrations/010_level4_content.sql
Level 4 is bulig_levels id 5.
"""
import json, os, re
import pymupdf

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LEVEL_ID = 5
POST_POSITION = 100      # post-test lesson sorts last in every grade

PASSAGES = json.load(open(os.path.join(ROOT, 'database', 'level4-passages.json'), encoding='utf-8'))
IMAGES = json.load(open(os.path.join(ROOT, 'database', 'level4-images.json'), encoding='utf-8'))

GUIDE = """HOW TO ADMINISTER THE FLUENCY PASSAGE (module pages 6–8, Phil-IRI)

1. Both you and the pupil need a copy of the passage. The pupil reads it aloud once. Record the time the pupil starts and stops reading.

2. Mark every miscue while the pupil reads:
   • Mispronunciation – underline the word and write how it was said above it. 1 error each (dialect variation is not an error).
   • Omission – circle the word or phrase left out. 1 error each word or phrase.
   • Substitution – underline the word and write the word said above it. 1 error each.
   • Insertion – use a caret (^) and write the added word/s above it. 1 error each word or phrase.
   • Repetition – underline the part repeated. 1 error each word or phrase.
   • Transposition – mark the words or letters that changed places. 1 error each.
   • Reversal – write the word/non-word said above the correct word. 1 error each.
   • Self-correction – write S above the word. NOT an error.

3. Oral Reading Score = (number of words − number of miscues) ÷ number of words × 100
   Independent 97–100% · Instructional 90–96% · Frustration 89% and below
   Example: 65 words, 15 miscues → (65 − 15) ÷ 65 × 100 = 76.9%

4. Reading Speed = number of words ÷ reading time in seconds × 60 = words per minute
   Example: 103 words read in 90 seconds → 69 words per minute

In BULIG the pupil can press Start reading and I’m done to time the reading; the time and words per minute are saved with the pupil’s response. Speak Answer shows a word-match guide only. Count the miscues yourself from listening."""


def h(s):
    if s is None:
        return 'NULL'
    return f"CONVERT(0x{str(s).encode('utf-8').hex()} USING utf8mb4)" if s != '' else "''"


def body_text(p):
    return ('\n\n' if p['format'] == 'prose' else '\n').join(p['paragraphs'])


def build():
    meta, out = {'grades': {}}, [
        '-- BULIG Level 4 (Fluency): additive content migration, one module per grade. Select your EXISTING database first.',
        '-- No accounts, progress, responses or existing lessons are changed. Safe to import again.',
        'SET NAMES utf8mb4;', 'START TRANSACTION;']
    total_lessons = total_acts = 0
    for g in range(1, 7):
        doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level4', f'grade-{g}.pdf'))
        ps, pics = PASSAGES[str(g)], IMAGES[str(g)]
        mt = f'Fluency · Level 4 · Grade {g}'
        out.append(f"INSERT INTO modules (id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),{LEVEL_ID},{h(mt)},{g} WHERE NOT EXISTS (SELECT 1 FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g});")
        out.append(f'SET @module_id=(SELECT id FROM modules WHERE level_id={LEVEL_ID} AND grade_level={g} ORDER BY id LIMIT 1);')
        lessons = []
        for i, p in enumerate(ps):
            if (g, i) == (4, 6):      # PDF p17 holds the baseball story's second part (see ISSUES)
                p = dict(p, title='My First Baseball Game II', byline='Randy Ryan')
            kind = 'pre' if i == 0 else 'post' if i == len(ps) - 1 else 'learn'
            pos = 1 if kind == 'pre' else POST_POSITION if kind == 'post' else i + 1
            title = {'pre': 'Pre-test Passage', 'post': 'Post-test Passage'}.get(kind, f'Practice Passage {i}')
            words = p['stated'] or p['words']
            files = ['assets/images/' + x['file'] for x in pics[i]]
            pages = p['pages']
            info = f"{title}: “{p['title']}”" + (f" by {p['byline']}" if p['byline'] else '') + f"\nModule PDF page{'s' if len(pages) > 1 else ''} {', '.join(map(str, pages))} · Number of words in the passage (module): {words}\n\n"
            guide = info + GUIDE + '\n\nPASSAGE TEXT\n\n' + p['title'] + '\n\n' + body_text(p)
            objective = 'Read the passage aloud accurately and at a good speed.' if kind == 'learn' else 'Read the passage aloud so your teacher can see how you read now.' if kind == 'pre' else 'Read the passage aloud again to show how much you have grown.'
            out.append(f"INSERT INTO lessons (module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) SELECT @module_id,{pos},{h(title)},{h(p['title'])},{h(objective)},{h(guide)},{h(json.dumps(pages))},{h(files[0] if files else None)},1 WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE module_id=@module_id AND position={pos});")
            out.append(f'SET @lesson_id=(SELECT id FROM lessons WHERE module_id=@module_id AND position={pos});')
            if kind in ('pre', 'post'):
                t = f"Grade {g} · {'Pre-test' if kind == 'pre' else 'Post-test'} · {p['title']}"
                rubric = f'Oral Reading Score = ({words} − miscues) ÷ {words} × 100. Independent 97–100%, Instructional 90–96%, Frustration 89% and below. Reading speed = {words} ÷ seconds × 60 words per minute.'
                out.append(f"INSERT INTO assessments (lesson_id,kind,title,rubric,source_page,source_note) SELECT @lesson_id,'{kind}',{h(t)},{h(rubric)},{pages[0]},{h('Phil-IRI oral reading passage. The teacher counts the miscues while listening; the app records reading time and a word-match guide.')} WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE lesson_id=@lesson_id AND kind='{kind}');")
            acts = []
            if kind in ('pre', 'post'):
                ready = ('Your teacher will listen as you read a story aloud. Read at your own pace, the way you talk. '
                         'If you do not know a word, try your best and keep going. Tap Start reading when you begin and I’m done when you finish.')
                acts.append(dict(title='Let’s get ready', type='reference', mode='none', instructions='Read or listen, then choose Next.', prompt=ready, narration=ready, expected=None, images=files[:1], xp=5))
            prompt = f"Title: {p['title']}\n" + (f"Author: {p['byline']}\n" if p['byline'] else '') + f"Words: {words}\n\n" + body_text(p)
            plain = ' '.join(p['paragraphs'])
            instr = 'Tap Start reading and read the whole passage aloud. Tap I’m done when you finish. Your teacher listens for accuracy and speed.'
            acts.append(dict(title=f"Read aloud: {p['title']}", type='reading', mode='answer', instructions=instr, prompt=prompt,
                             narration=instr if kind != 'learn' else f"{p['title']}. " + plain, expected=plain, images=files, xp=20))
            for n, a in enumerate(acts, 1):
                total_acts += 1
                assessment = f"(SELECT id FROM assessments WHERE lesson_id=@lesson_id AND kind='{kind}')" if kind in ('pre', 'post') else 'NULL'
                excerpt = doc[pages[0] - 1].get_text().strip()[:1500]
                out.append('INSERT INTO activities (lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) '
                           f"SELECT @lesson_id,{assessment},'{kind}',{n},{h(a['title'])},'{a['type']}',{h(a['instructions'])},{h(a['prompt'])},{h(a['images'][0] if a['images'] else None)},{h(json.dumps(a['images']))},{h(a['narration'])},{h(a['expected'])},{a['xp']},{pages[0]},{h(excerpt)},1,1,'{a['mode']}' "
                           f"WHERE NOT EXISTS (SELECT 1 FROM activities WHERE lesson_id=@lesson_id AND phase='{kind}' AND position={n});")
                out.append(f"SET @activity_id=(SELECT id FROM activities WHERE lesson_id=@lesson_id AND phase='{kind}' AND position={n});")
                out.append(f"INSERT INTO questions (activity_id,content,grading) SELECT @activity_id,{h(a['prompt'])},'teacher' WHERE NOT EXISTS (SELECT 1 FROM questions WHERE activity_id=@activity_id);")
                if kind in ('pre', 'post'):
                    out.append("INSERT IGNORE INTO assessment_questions(assessment_id,question_id) SELECT a.assessment_id,q.id FROM activities a JOIN questions q ON q.activity_id=a.id WHERE a.id=@activity_id AND a.assessment_id IS NOT NULL;")
            lessons.append(dict(position=pos, phase=kind, title=p['title'], pages=pages, words=words))
            total_lessons += 1
        pupil_pages = sorted({n for p in ps for n in p['pages']})
        meta['grades'][str(g)] = dict(page_count=len(doc), pupil_pages=pupil_pages, lessons=lessons, folder=f'level4/g{g}', pdf=f'level4/grade-{g}.pdf')
    for d, r in ISSUES:
        out.append(f'INSERT INTO content_issues (description,resolution) SELECT {h(d)},{h(r)} WHERE NOT EXISTS (SELECT 1 FROM content_issues WHERE BINARY description=BINARY {h(d)});')
    out.append(f"UPDATE bulig_levels SET title={h('Fluency')},published=1 WHERE id={LEVEL_ID} AND (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id={LEVEL_ID} AND l.published=1)>={total_lessons};")
    out.append("INSERT IGNORE INTO schema_migrations(version) VALUES('010_level4_content');")
    out.append('COMMIT;')
    with open(os.path.join(ROOT, 'database', 'migrations', '010_level4_content.sql'), 'w') as f:
        f.write('\n'.join(out) + '\n')
    json.dump(meta, open(os.path.join(ROOT, 'database', 'level4-meta.json'), 'w'), indent=1)
    return total_lessons, total_acts


ISSUES = [
    ('Level 4 Grades 1–2: the “Number of Words in the Passage” includes the title words.', 'Kept the module’s numbers for scoring; the passage text is shown without the title.'),
    ('Level 4 Grade 2: several passage pages (PDF p12, p16, p23, p26) have a stray grey drop-shadow box beside the picture.', 'The grey boxes are left out of the pictures.'),
    ('Level 4 Grade 3 “A Robot Dog” (PDF p20) starts mid-story: “walked and walked…”. The opening sentence is not printed.', 'Kept as printed.'),
    ('Level 4 Grade 4 “My First Baseball Game” prints “(251 Words)” in the text, but its table says 255.', 'The table’s 255 is used for scoring, as printed.'),
    ('Level 4 Grade 4 PDF p10 repeats the baseball story’s continuation; the table of contents lists PDF p17 (the same continuation) as “Songs of the Witches II”.', 'PDF p10 is not used. PDF p17 is shown as “My First Baseball Game II”, the text it actually contains.'),
    ('Level 4 Grade 5 “Jimmy Jet and His TV Set” table says 150 words; the poem has 144.', 'The table’s number is used for scoring, as printed.'),
    ('Level 4 Grade 6 “The Crocodile and the Monkey” (PDF p17) has about 490 words; its table says 435.', 'The table’s number is used for scoring, as printed.'),
    ('Level 4 Grade 6 “Be Glad Your Nose is on Your Face” table says 140 words; the poem has 132.', 'The table’s number is used for scoring, as printed.'),
    ('Level 4 Grade 6 pre/post-test “Bad Girl” mentions smoking and gambling; several passages are song lyrics (Fight Song, You Learn, I Wanna Do Right, Verb To Be).', 'Included as in the module. Review with your school before use; ownership of borrowed texts stays with their holders.'),
]

if __name__ == '__main__':
    print('lessons, activities:', build())
