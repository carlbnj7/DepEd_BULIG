"""Level 5 question cards: split each module page into one card per numbered item (like Level 3).

Reads database/level5-pages.json (text rows and separately cut pictures per page, made by
build_content.py) and writes database/level5-cards.json keyed "grade:pdfpage".
A story or passage before the questions becomes its own first card; directions become the
deck instruction; a./b./c. lines under a question are that card's choices; a matching
column (a./b./c. printed beside the numbered items) is shown with every card.
Usage: python3 tools/level5/build_cards.py
"""
import json, os, re

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
NUM = re.compile(r'^\s*(?:_+\s*)?(\d{1,2})\s*[.)]\s*(.*)$')
CHOICE = re.compile(r'^\s*(?:[a-e]\s*[.)]\s*.+|[A-E]\s*[.)]\s*(?!Direction|Instruction|Listening|Vocabulary|Matching)[^:]{1,60})$')
TITLE = re.compile(r"^(Activity|Set|Lesson)\b|^[A-Z0-9 :’'!?,.-]+$")
DIRECTION = re.compile(r'^(Directions?|Instructions?)\b', re.I)


def split_choices(s):
    """'a. Toys b. nutrients c. noise' -> ['a. Toys', 'b. nutrients', 'c. noise']"""
    parts = re.split(r'\s+(?=[a-eA-E]\s*[.)]\s)', s.strip())
    return [p.strip() for p in parts if p.strip()]


def flush(cards, bank):
    """Show a matching column with each numbered item of its section (back to the last Read card)."""
    if not bank:
        return
    for c in reversed(cards):
        if c['title'] == 'Read':
            break
        c['matched'] = True
        if not c['choices']:
            c['choices'] = list(bank)


def keep_matching_together(cards, ins):
    """A matching-type section stays ONE activity: Column A items (with pictures) and Column B choices."""
    out, section, context = [], [], ins
    def close():
        if not section:
            return
        matching = re.search(r'\bmatch|\bcolumn\b', context, re.I) and not re.search(r'choose the word|which animal|circle', context, re.I)
        if matching and len(section) > 1:
            items, imgs, bank = [], [], []
            for c in section:
                bits = re.split(r'\s*(?=(?<![A-Za-z])[a-e]\s*[.)]\s)', c['text'])
                word = ' '.join(b for b in bits if b and not CHOICE.match(b)).strip(' .')
                for b in bits:
                    if b and CHOICE.match(b) and b.strip() not in bank:
                        c['choices'].append(b.strip())
                label = c['title'] + '. ' + word if word else c['title'] + '.'
                items.append(label)
                for im in c['images']:
                    imgs.append(dict(im, label=label, alt=label))
                for ch in c['choices']:
                    if ch not in bank:
                        bank.append(ch)
            bank.sort(key=lambda x: x.strip().lower()[:1])
            out.append(dict(title='Matching', text='Column A\n' + '\n'.join(items), images=imgs, choices=bank))
        else:
            out.extend(section)
    for c in cards:
        if c['title'] == 'Read':
            close(); section = []; out.append(c); context = c['text']
        else:
            section.append(c)
    close()
    for c in out:
        c.pop('matched', None)
    return out


def build_page(rows):
    instruction, lead_text, lead_imgs, cards, pending, bank = [], [], [], [], [], []
    cur = None
    for row in rows:
        items = [it for col in row for it in col]
        texts = [it for it in items if it['t'] == 'text']
        imgs = [dict(src=it['src'], alt=it['cap'] or 'Picture from the module', label=it['cap']) for it in items if it['t'] == 'img']
        # a row may hold two numbered items side by side (1. … 9. …): split them apart
        segs = []
        for t in texts:
            for piece in re.split(r'\s+(?=\d{1,2}\s*[.)](?:\s|$))', t['s']):
                if piece.strip():
                    segs.append(dict(t, s=piece.strip()))
        if len([x for x in segs if NUM.match(x['s'])]) > 1:
            for x in segs:
                mm = NUM.match(x['s'])
                if mm:
                    if len(' '.join(pending)) > 160:
                        flush(cards, bank); bank = []
                        cards.append(dict(title='Read', text='\n'.join(pending), images=[], choices=[])); pending = []
                    cur = dict(title=mm.group(1), text='\n'.join(pending + ([mm.group(2)] if mm.group(2) else [])).strip(), images=[], choices=[])
                    pending = []; cards.append(cur)
                elif cur is not None:
                    if CHOICE.match(x['s']):
                        bank.extend(split_choices(x['s']))
                    else:
                        cur['text'] = (cur['text'] + ' ' + x['s']).strip()
            if imgs and cur is not None:
                cur['images'] += imgs
            continue
        first = texts[0]['s'] if texts else ''
        m = NUM.match(first)
        if m:
            if len(' '.join(pending)) > 160:          # a passage/story between questions: its own card
                flush(cards, bank); bank = []
                cards.append(dict(title='Read', text='\n'.join(pending), images=[], choices=[])); pending = []
            rest = [m.group(2)] if m.group(2) else []
            cur = dict(title=m.group(1), text='', images=list(imgs), choices=[])
            cards.append(cur)
            parts = rest + [t['s'] for t in texts[1:]]
            words, last = [], None
            for part in parts:            # a word beside the number, or a matching column (maybe run together)
                bits = re.split(r'\s+(?=[a-e]\s*[.)]\s)', part)
                for b in bits:
                    if CHOICE.match(b):
                        bank.extend(split_choices(b)); last = 'bank'
                    elif last == 'bank' and re.match(r'^[a-z(]', b):
                        bank[-1] += ' ' + b         # the matching phrase wrapped onto the next line
                    else:
                        words.append(b); last = 'word'
            cur['text'] = '\n'.join(pending + [' '.join(words)]).strip()
            pending = []
            continue
        if cur is None:
            for t in texts:
                (instruction if DIRECTION.match(t['s']) or (instruction and not lead_text and len(t['s']) < 90 and not t['s'].endswith('.')) else lead_text).append(t['s'])
            lead_imgs += imgs
            continue
        if texts and re.match(r'^[a-z(]', first) and not CHOICE.match(first) and (bank or cur['choices']) and not cur['text'].endswith('?') and not pending:
            tgt = cur['choices'] if cur['choices'] else bank      # a choice that wrapped onto the next line
            tgt[-1] = tgt[-1] + ' ' + ' '.join(t['s'] for t in texts)
            continue
        if texts and cur['choices'] and re.search(r'[a-eA-E]\s*[.)]\s*$', cur['choices'][-1]) and not pending and len(texts) == 1 and len(first) < 30:
            cur['choices'][-1] += ' ' + first; continue
        if texts and CHOICE.match(first) and not pending:
            for t in texts:
                part, _, rest = t['s'].partition('(Read Aloud)')   # the next item's read-aloud line ran into this row
                cur['choices'].extend(split_choices(part))
                if rest:
                    pending.append('(Read Aloud)' + rest)
            cur['images'] += imgs
            continue
        if texts and (pending or cur['choices'] or re.match(r'^(Part|Section|[A-D]\.\s|Activity|Story|Teacher)', first)):
            pending += [t['s'] for t in texts]      # a heading/passage for the next question
            lead_extra = imgs
            if imgs:
                cur['images'] += imgs
            continue
        for t in texts:
            cur['text'] = (cur['text'] + '\n' + t['s']).strip()
        cur['images'] += imgs
    if pending and cards:
        cards[-1]['text'] = (cards[-1]['text'] + '\n' + '\n'.join(pending)).strip()
    if len(cards) < 2:
        return None
    # instruction: directions plus short heading lines; a long lead (story/passage) or lead pictures become card 0
    head = []
    if len(' '.join(lead_text)) > 160:
        for l in lead_text:
            if TITLE.match(l) and len(l) < 60:
                head.append(l)
            else:
                break
    body = lead_text[len(head):]
    if len(' '.join(body)) > 160 or lead_imgs:
        cards.insert(0, dict(title='Read', text='\n'.join(body), images=lead_imgs, choices=[]))
        instruction = head + instruction
    else:
        instruction = body + instruction
    flush(cards, bank)
    # a numbered item that ended up inside another card becomes its own card
    have = {c['title'] for c in cards}
    fixed = []
    for c in cards:
        parts = re.split(r'(?:^|\n)\s*(\d{1,2})\s*[.)]\s+', c['text'])
        if len(parts) > 1 and all(n not in have for n in parts[1::2]) and c['title'] != 'Matching':
            c = dict(c, text=parts[0].strip())
            if c['text'] or c['images'] or c['choices'] or c['title'].isdigit():
                fixed.append(c)
            for n, body in zip(parts[1::2], parts[2::2]):
                fixed.append(dict(title=n, text=body.strip(), images=[], choices=[])); have.add(n)
        else:
            fixed.append(c)
    cards = []
    for c in fixed:                  # a section heading ("D. Directions: …") stuck under a question starts a new part
        m = re.search(r'\n\s*((?:[A-D]\.\s*)?(?:Directions?|Instructions?|Part\b|PART\b|Based on Lesson|Lesson \d+\s*[–-]|Teacher reads)[^\n]*(?:\n[^\n]*)*)$', c['text'])
        if m and c['title'].isdigit():
            cards.append(dict(c, text=c['text'][:m.start()].strip()))
            cards.append(dict(title='Read', text=m.group(1).strip(), images=[], choices=[]))
        else:
            cards.append(c)
    ins = ' '.join(instruction).strip()
    # keep numbered items in number order within each section (two-column pages)
    out, sec = [], []
    def put(sec):
        runs, run = [], []
        for x in sec:                       # numbering restarts -> a new list
            if any(y['title'] == x['title'] for y in run):
                runs.append(run); run = []
            run.append(x)
        runs.append(run)
        for r in runs:
            out.extend(sorted(r, key=lambda x: int(x['title'])))
    for c in cards + [None]:
        if c is None or not c['title'].isdigit():
            if sec:
                put(sec)
            sec = []
            if c is not None:
                out.append(c)
        else:
            sec.append(c)
    cards = keep_matching_together(out, ins)
    for c in cards:
        c['text'] = re.sub(r'[ \t]+', ' ', c['text']).strip()
        c['narration'] = ' '.join(x for x in [c['text']] + c['choices'] if x)
    return dict(instruction=ins, cards=cards)

WORDBANK = {
    '4:8': ('Pre- Assessment (Activities 1–10) · Instructions: Choose the best word from the word bank to complete each sentence. Write the word on the line.',
            ['Huge', 'friend', 'gift', 'Clean', 'desk', 'learn', 'dead', 'Country', 'pie', 'above']),
    '4:30': ('Prep Paired · Activity 10 · Directions: Complete the sentences using words from the word bank that makes it correct. A letter/s were given.',
             ['above', 'began', 'almost', 'beside', 'bank', 'outside', 'east', 'ago', 'across', 'between', 'center', 'front', 'under']),
    '4:31': ('Post-assessment (Activity 1-10) · A. Vocabulary Development (1–10) · Instructions: Choose the best word from the word bank to complete each sentence. Write the word on the line.',
             ['Huge', 'friend', 'gift', 'Clean', 'desk', 'learn', 'dead', 'Country', 'pie', 'above']),
    '4:33': ('Fill in the blank · Activity 11 · Directions: Read and complete the sentences below by choosing the correct words found inside the box.',
             ['body', 'eye', 'bone', 'feather', 'bill', 'chin', 'foot', 'hair', 'Lap', 'meat', 'mouth', 'Finger', 'skin']),
    '5:14': ('The Opposite Meanings · Activity 4- Antonym · Directions: Select the correct antonym of the given word from the box below and write your answer on the blank provided.',
             ['weak', 'mountain', 'calm', 'rejoice', 'men', 'beautiful', 'yesterday', 'thin', 'winter', 'healthy', 'ma’am', 'fork', 'gather', 'plenty', 'hen']),
    '5:17': ('Guess the Word · Activity 7- Fill in the Missing Letters · Directions: Read each item and supply the missing letters to form the correct word. Select the words from the box below.',
             ['blood', 'chance', 'expect', 'dentist', 'fellow', 'either', 'captain', 'except']),
    '5:18': ('Guess the Word · Activity 8- Fill in the Missing Letters · Directions: Read each item and supply the missing letters to form the correct word.',
             ['rather', 'ship', 'reason', 'point', 'everything', 'nor', 'overalls', 'shape', 'several', 'pants']),
    '6:19': ('Prep Paired · Activity 10 · Directions: Complete the words by choosing your answer from the words inside the box. Complete the sentences using the words from the word bank that make it correct. The signal letter/s were given.',
             ['perhaps', 'ready', 'members', 'paint', 'rain', 'blue', 'window', 'bill', 'bank', 'matter', 'mind', 'exercise', 'return', 'wish', 'count', 'difference', 'energy', 'direction', 'anything']),
    '6:20': ('Fill in the blank · Activity 11 · Directions: Complete the sentences below by choosing the correct words inside the box. Answer the worksheet activity by filling in the blank.',
             ['sum', 'summer', 'wall', 'probably', 'main', 'winter', 'wide', 'written', 'length', 'reason', 'kept', 'interest', 'present', 'beautiful', 'ability', 'part']),
}
INSTRUCTION = {
    '6:24': 'Activity 15 Arrange a Word · Directions: Unscramble the letters to form a word. Write the words in the blank, based on the words listed in the box.',
    '4:32': 'B. Listening Comprehension (11–20) · Instructions: Listen carefully as the teacher reads each sentence or passage. Choose the best answer and write the letter on the line.',
    '6:28': 'Sentence Complete · Activity 19 · Directions: Select the correct word from the list to complete each sentence. Write the correct answer in the blank.',
}
TEACHER_ONLY = {'3:13'}       # a whole page of answers


def tidy_decks(out):
    """Last checks on every deck against the module (instructions, word banks, answer keys)."""
    import re
    for k in TEACHER_ONLY:
        out.pop(k, None)
    if '3:20' in out:            # questions 46–50 sit above the PRE-TEST ANSWER KEY box: keep only the questions
        out['3:20']['cards'] = [c for c in out['3:20']['cards'] if c['title'] in ('46', '47', '48', '49', '50') and c['choices']]
    for k, d in out.items():
        ins = d['instruction'].strip()
        # answer choices that belong to the last question of the page before
        ins = re.sub(r'^(?:[a-dA-D]\s*[.)]\s+.+?\s*)+(?=\(Read Aloud\)|Teacher reads|Directions?\b|Instructions?\b|$)', '', ins).strip()
        passage = re.match(r'^((?:\(Read Aloud\):?|Teacher reads:)\s*.+?)(?:\s+(Instructions?\b.*|Directions?\b.*))?$', ins, re.S)
        if passage:                # a read-aloud passage printed above the first question: its own card
            qs = d['cards']
            if qs and qs[0]['title'].isdigit() and not re.match(r'\(Read Aloud\)|Teacher reads', qs[0]['text']):
                text = passage.group(1)
                head = re.match(r'^(\(Read Aloud\):?|Teacher reads:)\s*(.*)$', text, re.S)
                d['cards'].insert(0, dict(title='Read', heading=head.group(1).rstrip(':'), text=head.group(2), images=[], choices=[]))
            ins = passage.group(2) or ''
        d['instruction'] = ins
    for k, (ins, words) in WORDBANK.items():
        if k in out:
            out[k]['instruction'] = ins
            out[k]['cards'].insert(0, dict(title='Read', text='', lead='Word bank', words=words, images=[], choices=[]))
    for k, ins in INSTRUCTION.items():
        if k in out:
            out[k]['instruction'] = ins
    if '4:32' in out:
        for c in out['4:32']['cards']:
            if c['title'] == '11' and not c['text'].startswith('(Read Aloud)'):
                c['text'] = '(Read Aloud): “The field was full of yellow flowers.”\n' + c['text']
    # numbered direction lines read as questions (Grade 4 Activities 15–20, Grade 6 Activity 19)
    for k in ('4:37', '4:38', '4:39', '4:41', '4:42'):
        d = out.get(k)
        if not d:
            continue
        steps, words, rest = [], [], []
        cs = d['cards']
        i = 0
        while i < len(cs) and cs[i]['title'].isdigit() and int(cs[i]['title']) == len(steps) + 1 and not cs[i]['choices'] and '____' not in cs[i]['text'].split('\n')[0]:
            lines = cs[i]['text'].split('\n')
            steps.append(lines[0]); words += [w.strip() for w in lines[1:] if w.strip()]
            i += 1
        rest = cs[i:]
        if rest and rest[0]['title'] == 'Read' and not rest[0]['choices']:
            t = rest[0]['text']
            ws = [w.strip() for w in re.split(r'_{2,}|\n', t) if w.strip() and len(w.strip()) > 1]
            if k == '4:41':
                ws = list(dict.fromkeys(w for w in ws if w not in ('B', 'I', 'N', 'G', 'O')))
            words += ws; rest = rest[1:]
        head = re.sub(r'\s*Directions?:?\s*$', '', d['instruction']).strip()
        d['instruction'] = head + ' · Directions: ' + ' '.join(f'{n}. {s}' for n, s in enumerate(steps, 1))
        d['cards'] = ([dict(title='Read', text='', lead='Words', words=words, images=[], choices=[])] if words else []) + rest
    # "Directions: … from the / box below / … / words": the box is a word bank card
    for k, d in out.items():
        cs = d['cards']
        if cs and cs[0]['title'] == 'Read' and '\nbox below\n' in cs[0]['text'] and d['instruction'].rstrip().endswith('from the'):
            lines = [l.strip() for l in cs[0]['text'].split('\n') if l.strip()]
            i = lines.index('box below')
            head, rest = lines[:i], lines[i + 1:]
            tail = rest.pop(0) if rest and rest[0].lower().startswith('and write') else ''
            words = []
            for w in rest:
                words += w.split() if w == 'morning remember' else [w]
            d['instruction'] = ' · '.join(head) + ' · ' + d['instruction'].rstrip() + ' box below ' + tail
            cs[0] = dict(title='Read', text='', lead='Word bank', words=words, images=[], choices=[])
    if '6:28' in out:
        cs = out['6:28']['cards']
        if cs and cs[0]['title'] == 'Read':
            lines = [l.strip() for l in cs[0]['text'].split('\n') if l.strip()]
            words = [l for l in lines if re.fullmatch(r"[A-Za-z’'-]+", l) and l not in ('Sentence', 'Complete')]
            cs[0] = dict(title='Read', text='', lead='Words', words=words, images=[], choices=[])


def main():
    pages = json.load(open(os.path.join(ROOT, 'database', 'level5-pages.json'), encoding='utf-8'))
    out, n = {}, 0
    for key, p in pages.items():
        deck = build_page(p['rows'])
        if deck:
            out[key] = deck
            n += len(deck['cards'])
    def tidy(c):
        # choices written on one line: "a. X b. Y c. Z" -> separate choices
        ch = []
        for x in c['choices']:
            ch += [y.strip() for y in re.split(r'\s+(?=[a-eA-E]\s*[.)]\s)', x) if y.strip()]
        # choices left inside the question text (and bare "a)" lines) move to the choice list
        lines, keep = [l.strip() for l in c['text'].split('\n')], []
        i = 0
        while i < len(lines):
            l = lines[i]
            if re.fullmatch(r'[a-eA-E]\s*[.)]', l) and i + 1 < len(lines) and lines[i + 1] and not re.match(r'[a-eA-E]\s*[.)]', lines[i + 1]) and c['title'].isdigit():
                ch.append(l + ' ' + lines[i + 1]); i += 2; continue
            if re.fullmatch(r'[a-eA-E]\s*[.)]', l):
                i += 1; continue
            if c['title'].isdigit() and keep and re.match(r'[a-eA-E]\s*[.)]\s+\S', l):
                ch += [y.strip() for y in re.split(r'\s+(?=[a-eA-E]\s*[.)]\s)', l) if y.strip()]; i += 1; continue
            keep.append(l); i += 1
        seen, uniq = set(), []
        for x in ch:
            if x not in seen:
                seen.add(x); uniq.append(x)
        c['text'] = '\n'.join(keep).strip(); c['choices'] = uniq
    for d in out.values():
        for c in d['cards']:
            if c['title'] != 'Matching':
                tidy(c)
    FIX = [(r'\bA(cat|cow|dog|kind)\b', r'A \1'), (r'\ba(cat|cow|dog)\b', r'a \1'), (r'\bFillin\b', 'Fill in'), (r'\bSmal\b', 'Small'),
           (r'\bToschool\b', 'To school'), (r'\bona\b', 'on a'), (r'\bliff\b', 'lift'), (r'\bstor\b', 'story'), (r'\bpetrolcan\b', 'petrol can'),
           (r'\bstringbeans\b', 'string beans')]
    for d in out.values():
        for c in d['cards']:
            for a, b in FIX:
                c['text'] = re.sub(a, b, c['text']); c['choices'] = [re.sub(a, b, x) for x in c['choices']]
        d['cards'] = [c for c in d['cards'] if c['text'].strip() or c['images'] or c['choices']]
        for c in d['cards']:
            c['narration'] = ' '.join(x for x in [c['text']] + c['choices'] if x)
    # choices with leftover blank lines/dashes ("a) blanket —_b) lampshade", "a) lampshade __")
    for d in out.values():
        for c in d['cards']:
            if c['title'] == 'Matching':
                continue
            ch = []
            for x in c['choices']:
                ch += [y.strip(' _—-') for y in re.split(r'[\s_—-]+(?=[a-eA-E]\)\s)', x) if y.strip(' _—-')]
            c['choices'] = ch
    # a question stranded under its "Lesson N – …" heading: heading + passage first, then the question
    for d in out.values():
        cs = d['cards']
        for i in range(len(cs) - 1):
            c, r = cs[i], cs[i + 1]
            if c['title'].isdigit() and r['title'] == 'Read' and re.fullmatch(r'(Based on )?Lesson \d+\s*[–-][^\n]*', c['text'].strip()):
                lines = r['text'].split('\n')
                if len(lines) > 1 and re.search(r'[?:]\s*$', lines[-1]):
                    q = lines[-1]
                    cs[i], cs[i + 1] = dict(r, text=c['text'] + '\n' + '\n'.join(lines[:-1])), dict(c, text=q)
    # items whose choices continue on the next page
    # a passage that followed the last question of a list becomes its own card before the next questions
    for d in out.values():
        cs, fixed = d['cards'], []
        for c in cs:
            if c['title'].isdigit():
                heads = [x for x in c['choices'] if re.match(r'[A-E]\.\s*Directions?', x)]
                lines = c['text'].split('\n')
                if len(lines) > 1 and (heads or (len(' '.join(lines[1:])) > 160 and not re.match(r'(Teacher reads|\(Read Aloud\)|Story)', lines[0]) and not lines[-1].rstrip().endswith('?'))):
                    fixed.append(dict(c, text=lines[0], choices=[x for x in c['choices'] if x not in heads]))
                    fixed.append(dict(title='Read', text='\n'.join(heads + lines[1:]), images=[], choices=[]))
                    continue
            fixed.append(c)
        d['cards'] = fixed
    NEXT = {('2:64', '8'): ['a) To look and listen carefully', 'b) To run away', 'c) To ignore'],
            ('4:46', '32'): ['A. Write', 'B. Read', 'C. Count', 'D. Clean'],
            ('2:66', '25'): ['a) Sick people', 'b) Robots', 'c) Trees']}
    TEXT = {('2:46', '1'): 'We rest our head on a ______ when we sleep.', ('2:46', '2'): 'The ______ keeps us warm at night.',
            ('2:46', '3'): 'We keep our clothes inside the ______.', ('2:46', '4'): 'A ______ gives light when the room is dark.',
            ('2:46', '5'): 'A ______ helps wake us up in the morning.'}
    for (k, t), tx in TEXT.items():
        for c in out.get(k, {}).get('cards', []):
            if c['title'] == t:
                c['text'] = tx
    for (k, t), ch in NEXT.items():
        for c in out.get(k, {}).get('cards', []):
            if c['title'] == t:
                c['choices'] = ch
    over = json.load(open(os.path.join(os.path.dirname(__file__), 'overrides.json'), encoding='utf-8'))
    # an item split across two pages: question on one page, its choices on the next
    for key, add in (('4:32', dict(title='20', text='(Read Aloud): “She felt sad when the bird flew away.”\nHow did she feel?', images=[], choices=['a) happy', 'b) sad', 'c) angry'])),
                     ('4:13', dict(title='32', text='What is full of words?', images=[], choices=['A. The bag', 'B. The room', 'C. The page', 'D. The table'])),
                     ('4:45', None)):
        if add and key in out and not any(c['title'] == add['title'] for c in out[key]['cards']):
            out[key]['cards'].append(add)
    if '4:14' in out:
        out['4:14']['cards'] = [c for c in out['4:14']['cards'] if not (c['title'] == 'Read' and c['text'].startswith('A. The bag'))]
    import sys; sys.path.insert(0, os.path.dirname(__file__))
    from handcards import all_pages
    from build_matching import build as build_match
    # matching sections rebuilt from the page layout (each picture on its own item/letter)
    for key, d in out.items():
        if any(c['title'] == 'Matching' for c in d['cards']):
            g, pn = map(int, key.split(':'))
            m = build_match(g, pn, key) if g in (1, 5) else None
            if m and len(m['choices']) >= 2:
                i = next(i for i, c in enumerate(d['cards']) if c['title'] == 'Matching')
                old_n = len(re.findall(r'(?m)^\d+\.', d['cards'][i]['text']))
                new_n = len(re.findall(r'(?m)^\d+\.', m['text']))
                if new_n >= old_n - 1:
                    d['cards'][i] = m
    from handcards import FULL_MATCH
    # a question whose choices continue at the top of the next page
    import pymupdf
    docs = {}
    for key, d in out.items():
        g, pn = key.split(':')
        for c in d['cards']:
            if not c['title'].isdigit():
                continue
            real = [x for x in c['choices'] if not re.match(r'^[A-E]\.\s*(Context|Direction|Instruction)', x)]
            if len(real) != len(c['choices']):
                c['choices'] = real
            if len(real) == 1 and c is [x for x in d['cards'] if x['title'].isdigit()][-1]:
                doc = docs.setdefault(g, pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf')))
                if int(pn) < len(doc):
                    lines = [l.strip() for l in doc[int(pn)].get_text().split('\n') if l.strip() and 'MERGEFORMAT' not in l and not re.fullmatch(r'\d{1,3}', l.strip())]
                    for l in lines:
                        if re.match(r'^[a-eA-E]\s*[.)]\s', l):
                            c['choices'].append(l)
                        else:
                            break
                    nk = f'{g}:{int(pn) + 1}'
                    if nk in out and len(c['choices']) > 1:     # remove them from the top of the next page
                        nd = out[nk]['cards']
                        while nd and not nd[0]['title'].isdigit() and all(re.match(r'^[a-eA-E]\s*[.)]', x) for x in nd[0]['text'].split('\n') if x.strip()):
                            nd.pop(0)
    # a matching list that continues on the next page(s): join it into one card
    for key in sorted([k for k in out if k.split(':')[0] in ('1', '5')], key=lambda k: (int(k.split(':')[0]), int(k.split(':')[1]))):
        g, pn = map(int, key.split(':'))
        mc = next((c for c in out[key]['cards'] if c['title'] == 'Matching'), None)
        if not mc:
            continue
        nxt = pn + 1
        while f'{g}:{nxt}' in out:
            last = max(int(x) for x in re.findall(r'(?m)^(\d+)\.', mc['text']))
            m = build_match(g, nxt, f'{g}:{nxt}', cont=True)
            if not m:
                break
            nums = [int(x) for x in re.findall(r'(?m)^(\d+)\.', m['text'])]
            if not nums or nums[0] != last + 1:
                break
            mc['text'] += '\n' + '\n'.join(m['text'].split('\n')[1:])
            mc['images'] += m['images']
            for ch in m['choices']:
                if ch[:2] not in [x[:2] for x in mc['choices']]:
                    mc['choices'].append(ch)
            mc.setdefault('choice_images', {}).update(m.get('choice_images', {}))
            nd = out[f'{g}:{nxt}']
            keep = [c for c in nd['cards'] if not (c['title'].isdigit() and int(c['title']) in nums)]
            # drop leading bits that were part of the continued list
            while keep and keep[0]['title'] != 'Read' and not keep[0]['title'].isdigit():
                keep.pop(0)
            if keep and keep[0]['title'] == 'Read' and re.match(r'^[a-j]\.\s', keep[0]['text']):
                keep.pop(0)
            note = dict(title='Read', text=(f'Item {nums[0]} is' if len(nums) == 1 else f'Items {nums[0]}–{nums[-1]} are') + f' part of the matching activity on the previous page, together with items 1–{last}.', images=[], choices=[])
            nd['cards'] = [note] + keep
            nxt += 1
        if key in FULL_MATCH:            # typed from the page (wrapped lines are messy in these tests)
            ins, items, bank = FULL_MATCH[key]
            pics = [im for im in mc['images']]
            mc['text'] = 'Column A\n' + '\n'.join(f'{i}. {w}' for i, w in enumerate(items, 1))
            mc['choices'] = bank
            for im in pics:
                n = re.match(r'(\d+)', im['label'])
                if n:
                    lab = f"{n.group(1)}. {items[int(n.group(1)) - 1]}"
                    im['label'] = im['alt'] = lab
            out[key]['instruction'] = ins
    over.update(all_pages())
    # story/vocabulary pages (pictures cut one by one, see storycards.py) and worksheet pages (worksheets.py)
    over.update(json.load(open(os.path.join(os.path.dirname(__file__), 'storycards.json'), encoding='utf-8')))
    from worksheets import pages as worksheet_pages, COMPLETE, APPEND
    over.update(worksheet_pages())
    for key, deck in over.items():      # pages checked and written by hand against the module
        for c in deck['cards']:
            c['narration'] = ' '.join(x for x in [c['text']] + c['choices'] if x)
        out[key] = deck
    for key, cards in APPEND.items():
        if key in out and not any(c['title'] == cards[-1]['title'] and c['text'] == cards[-1]['text'] for c in out[key]['cards']):
            out[key]['cards'] += [dict(c) for c in cards]
    for (key, t), fix in COMPLETE.items():     # a question split over two pages, made whole
        for c in out.get(key, {}).get('cards', []):
            if c['title'] == t:
                c.update(fix)
    if '1:8' in out:                              # "4. The father of your mother / or father" wraps beside d.
        for c in out['1:8']['cards']:
            if c['title'] == 'Matching':
                c['text'] = c['text'].replace('4. The father of your mother\n', '4. The father of your mother or father\n')
                c['choices'] = [re.sub(r'^d\. or father$', 'd.', x) for x in c['choices']]
    # a story card starts with its section heading and directions: those go on the card's lead line,
    # the story keeps its title as a heading, so the passage reads at normal size and the activity stays in focus
    HEAD = re.compile(r'^([A-E]\.\s*)?[A-Z][A-Za-z ,/&-]{2,60}:\s*$|^(Directions?|Instructions?)\s*:|^(Choose|Write|Circle|Encircle|Listen|Match|Answer|Draw|Read the|Identify|Fill)\b.{0,160}[.:]$')
    for d in out.values():
        for c in d['cards']:
            if c['title'] not in ('Read',) and not c['title'].isdigit() or c.get('heading'):
                continue
            lines = [l.strip() for l in c['text'].split('\n')]
            lead = []
            if c['title'].isdigit():       # directions printed above the first question go on its lead line
                while len(lines) > 1 and re.match(r'^(Instructions?|Directions?)\s*:|^(Circle|Choose|Write) the letter\b', lines[0]):
                    lead.append(lines.pop(0))
                if lead:
                    c['lead'] = ' '.join(([c['lead']] if c.get('lead') else []) + lead)
                c['text'] = '\n'.join(lines).strip()
                continue
            while len(lines) > 1 and lines[0] and HEAD.match(lines[0]):
                lead.append(lines.pop(0))
            m = re.match(r'^(\(Read Aloud\)\s*[“"][^”"]{2,60}[”"])\s*(.*)$', lines[0]) if lines else None
            if m and m.group(2):
                c['heading'] = m.group(1); lines[0] = m.group(2)
            if lead:
                c['lead'] = ' '.join(([c['lead']] if c.get('lead') else []) + lead)
            while len(lines) > 1 and re.fullmatch(r'Questions?:?', lines[-1]):
                lines.pop()
            c['text'] = '\n'.join(lines).strip()
    tidy_decks(out)
    # a section heading printed after the last item of a section belongs to the next card
    SECTION = re.compile(r'^(?:[A-E]\.\s*|Part\s+\w+\s*[—–-]?\s*|[IVX]+\.\s*)?[A-Z][\w &’()–,-]*(Comprehension|Assessment|Vocabulary|Development|Test|Antonyms|Synonyms|Homophones)\b[\w &()–:-]*$')
    moved = 0
    for d in out.values():
        cs = d['cards']
        for i, c in enumerate(cs[:-1]):
            lines = c['text'].split('\n')
            if c['title'].isdigit() and len(lines) > 1 and SECTION.match(lines[-1].strip()) and '?' not in lines[-1] and '__' not in lines[-1]:
                head = lines.pop().strip(); c['text'] = '\n'.join(lines).strip()
                cs[i + 1]['lead'] = (head + ' · ' + cs[i + 1]['lead']) if cs[i + 1].get('lead') else head
                moved += 1
    print('section headings moved to the next card:', moved)
    for d in out.values():
        for c in d['cards']:
            c.setdefault('images', []); c.setdefault('choices', [])
            c['narration'] = ' '.join(x for x in [c.get('lead', ''), c.get('heading', ''), c['text']] + c['choices'] + [im['label'] for im in c['images'] if im.get('label') and c['title'] == 'Read'] + c.get('words', []) if x) or d['instruction']
        d['instruction'] = re.sub(r'\s*\bColumn A\s+Column B\b\s*', ' ', d['instruction']).strip()
        d['instruction'] = re.sub(r'\s{2,}', ' ', d['instruction'])
    json.dump({'version': '2026-10-01 story pictures + worksheets', 'cards': out}, open(os.path.join(ROOT, 'database', 'level5-cards.json'), 'w'), ensure_ascii=False, separators=(',', ':'))
    print(f'{len(out)} pages split into {sum(len(d["cards"]) for d in out.values())} cards')


if __name__ == '__main__':
    main()
