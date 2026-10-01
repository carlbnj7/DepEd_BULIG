"""Build Level 3 (Word Recognition) content from the official module.

Reads storage/level3-original.pdf and database/level3-images.json (from
extract_images.py).  Writes:
  database/level3-cards.json               lesson manifest + native card decks
  database/migrations/009_level3_content.sql  additive, re-runnable content migration

Wording, word lists, activity order and pictures follow the module.  Paper tasks
(circle, connect, colour, write) become typed or spoken answers that the
teacher reviews; group games stay teacher-led ("Done" when finished).
Level 3 is bulig_levels id 4.
"""
import json, os
import pymupdf

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LEVEL_ID = 4
IMG = json.load(open(os.path.join(ROOT, 'database', 'level3-images.json')))['images']
PDF = pymupdf.open(os.path.join(ROOT, 'storage', 'level3-original.pdf'))


def src(name):
    if name not in IMG:
        raise SystemExit('missing picture ' + name)
    return IMG[name]['src']


def pics(prefix, n):
    return [f'{prefix}-{i:02d}' for i in range(1, n + 1)]


def one_level(text):
    """Level 3 has no A/B parts; the module's toolkit headings are shown as plain Level 3."""
    return text.replace('LEVEL 3A', 'LEVEL 3').replace('LEVEL 3B', 'LEVEL 3')


def page_text(pages):
    out = []
    for p in pages:
        t = one_level(PDF[p - 1].get_text().strip())
        out.append(f'PDF page {p}\n{t}' if t else f'PDF page {p}\n(picture page — see the original page)')
    return '\n\n'.join(out)


# ---------------------------------------------------------------- activity helpers
def intro(title, text, images=(), page=0, narration=None):
    return dict(type='reference', mode='none', title=title, instructions='Read or listen, then choose Next.',
                prompt=text, narration=narration or text, images=list(images), xp=0, page=page)


def reading(title, words, page, instructions='Read each word aloud. Tap Speak Answer and read, or type the words you read.', images=(), sep='   ', check=True):
    text = sep.join(words)
    return dict(type='reading', mode='answer', title=title, instructions=instructions, prompt=text,
                narration=instructions, images=list(images), xp=10, page=page, expected=text if check else None)


def game(title, direction, procedure, images, page):
    steps = '\n'.join(f'{i}. {s}' for i, s in enumerate(procedure, 1))
    return dict(type='group', mode='perform', title=title,
                instructions='Play this with your teacher and classmates. Choose Done when your turn is finished.',
                prompt=f'{direction}\n\nHow to play:\n{steps}', narration=direction, images=list(images), xp=10, page=page)


def open_task(title, direction, prompt, page, images=()):
    return dict(type='open', mode='answer', title=title, instructions=direction, prompt=prompt,
                narration=direction + ' ' + prompt.replace('\n', '. '), images=list(images), xp=10, page=page)


def card(title, text='', images=(), choices=(), alt='Picture to name', say=None):
    return dict(title=str(title), text=text, images=[dict(src=src(i), alt=alt, label='') for i in images],
                choices=list(choices), say=say)


def deck(title, instruction, cards, page):
    for c in cards:
        spoken = c.pop('say')
        c['narration'] = instruction + ' ' + (spoken if spoken is not None else c['text'].replace('_', ' ')).strip()
    return dict(type='open', mode='answer', title=title, instructions=instruction,
                prompt=instruction, narration=instruction, images=[], xp=10, page=page,
                deck=dict(title=title, instruction=instruction, cards=cards))


# ---------------------------------------------------------------- pre / post toolkits
PRE_TASKS = [
    ('Task 1: Short Vowels CVC Pattern', 'bat den kid fog run tap hen lip box gum', 10, 8),
    ('Task 2: Long Vowels Word Family', 'meal food sack ball mask pail goat neck bell tank', 10, 8),
    ('Task 3: Consonant Blends', 'blue clap dress flower glass bread creep drop friend grape plan pray storm string ship sleep spoon spring split trash', 11, 15),
    ('Task 4: Sight Words', 'they people together beautiful sentence come because about enough usually', 11, 8),
]
POST_TASKS = [
    ('Task 1: Short Vowels CVC Pattern', 'kid run bat fog den lip gum tap box hen', 142, 8),
    ('Task 2: Long Vowels Word Family', 'pail goat neck bell tank meal food sack ball mask', 142, 8),
    ('Task 3: Consonant Blends', 'bread drop creep glass friend blue dress clap grape flower pray spoon spring split trash plan sleep storm string ship', 143, 15),
    ('Task 4: Sight Words', 'come because about enough usually they people together beautiful sentence', 143, 8),
]
TOOLKIT_NOTE = 'Here are some words I would like you to read. (Do not spell the words, just read them.)'


def toolkit(tasks, banner, kind):
    acts = [intro('Let’s get ready',
                  ('Before we begin: read each group of words aloud. Do not spell the words, just read them. It is okay to be unsure. Your teacher will listen and check the words you sound out correctly.'
                   if kind == 'pre' else
                   'Show what you learned: read each group of words aloud. Do not spell the words, just read them. Your teacher will check the words you sound out correctly.'),
                  [banner], 9 if kind == 'pre' else 141)]
    for title, words, page, passing in tasks:
        total = len(words.split())
        a = reading(title, words.split(), page, instructions=TOOLKIT_NOTE)
        a['rubric'] = f'Passing score: at least {passing} out of {total} words sounded out correctly.'
        acts.append(a)
    return acts


# ---------------------------------------------------------------- lessons 1-5: CVC short vowels
CVC = {
    1: dict(v='a', intro=['bat', 'cat'], blend=['b + a + t = bat', 'c + a + t = cat'],
            words='cab fan tap mad man cat bag van fat rag lap hat jam map mat',
            odd=['bag rag fan ten', 'map hit tap lap', 'cab bed mad mat', 'hen hat fat cat', 'mad man van den',
                 'sit jam map cab', 'fan man tap pit', 'set mat hat fat', 'map tap hip lap', 'man van jam men'],
            speed='bag rag fan map tap lap cab mad mat hat fat cat man van jam',
            assess='cab tap mad man bag van rag fat jam map', base=13),
    2: dict(v='e', intro=['net', 'ten'], blend=['n + e + t = net', 't + e + n = ten'],
            words='bed men set red pen vet wed ten wet den jet keg hen pet leg',
            odd=['fan pen ten jet', 'wet mat keg leg', 'bed red mad wed', 'den man hen men', 'pet set van vet',
                 'hit keg leg web', 'wed dot den hen', 'red ten pin leg', 'jet pet set cat', 'vet wet tap keg'],
            speed='bed red wed den hen men pen ten jet pet set vet wet keg leg',
            assess='bed pet red vet den wet hen keg jet leg', base=19),
    3: dict(v='i', intro=['zip', 'bin'], blend=['z + i + p = zip', 'b + i + n = bin'],
            words='hid tin pit kid hip sit lid lip dig bin fit pig pin hit wig',
            odd=['hip hat kid lid', 'bin pin pan tin', 'hop hip lip fit', 'dig fog pig wig', 'pit sit sat dig',
                 'van lid bin fit', 'wig son bin kid', 'hit pit hat kid', 'fat dig lid sit', 'bin man tin pit'],
            speed='hip kid lid bin pin tin sim lip fit hit pit sit dig pig wig',
            assess='hid hip kid fit lid sit bin dig pin wig', base=25),
    4: dict(v='o', intro=['dog', 'jog'], blend=['d + o + g = dog', 'j + o + g = jog'],
            words='cob hog cop job jog son rob log hop nod mom dot fog box hot',
            odd=['jog pig log mom', 'hip nod fog hog', 'cop son men hop', 'box pin cop son', 'box cop nod lid',
                 'leg jog log mom', 'son pin hop dot', 'cob job rob pit', 'mom hit son hot', 'pig nod fog hog'],
            speed='cob job rob nod fog hog jog log mom box cop son hop dot hot',
            assess='cob hot job log mom son nod fog rob cop', base=31),
    5: dict(v='u', intro=['mug', 'bun'], blend=['m + u + g = mug', 'b + u + n = bun'],
            words='cub bug run tub mug sun mud tug nut gum bun cup yum fun bus',
            odd=['bug beg mug tug', 'gum yum gem bug', 'fin bun fun run', 'sun nut sit cup', 'fun fat run sun',
                 'tub mud gum van', 'gum yum sin sun', 'tug jog cup bus', 'can sun nut mud', 'cup tug mug map'],
            speed='cub tub mud gum yum bug mug tug bun fun run sun nut cup bus',
            assess='tug mud cub sun bun mug gum cup nut bus', base=37),
}


def cvc_lesson(n):
    d = CVC[n]
    v, b, L = d['v'], d['base'], f'l{n:02d}'
    return [
        intro(f'Meet the short “{v}” sound',
              f'Look at the pictures. Their names have the short “{v}” sound.\nName each picture.\n\nListen as we blend the sounds:\n' + '\n'.join(d['blend']),
              [f'{L}-intro-{w}' for w in d['intro']], b),
        reading(f'Read the short “{v}” words', d['words'].split(), b,
                instructions=f'Listen to your teacher read the words. Then sound out each word with the short “{v}” sound.'),
        deck('Activity 1: Build a Word', 'Build a word out from the picture. Say it. Connect the letters. Write the word.',
             [card(i, images=[p], alt='Picture with letter tiles') for i, p in enumerate(pics(f'{L}-build', 10), 1)], b + 1),
        deck('Activity 2: Odd Word Out', 'Listen to the words. Which word does not belong?',
             [card(i, '   '.join(row.split()), choices=row.split()) for i, row in enumerate(d['odd'], 1)], b + 2),
        deck('Activity 3: Tell Me the Word', 'Look at the picture. Say the CVC word that matches the picture.',
             [card(i, images=[p], say='') for i, p in enumerate(pics(f'{L}-tell', 10), 1)], b + 3),
        reading('Activity 4: Speed Read', d['speed'].split(), b + 5,
                instructions='Your teacher flashes the word cards. Read each word quickly.'),
        dict(reading('Assessment: Individual Reading', d['assess'].split(), b + 5,
                     instructions='Read the following words correctly.'), phase='post'),
    ]


# ---------------------------------------------------------------- lessons 6-10: word families
FAM = {
    6: dict(team='“ea” and “ai”', intro=['meal', 'pail'], tp=43, cols=('/ai/', '/ea/'),
            words='meal hear meat seat peak bait nail pail tail rain',
            sort='meal rain bait pail tail hear nail meat peak seat',
            match='nail rain tail pail hair meal meat seat peak hear', mp=44,
            blend=['p + ail', 'r + ain', 'h + air', 't + ail', 'n + ail', 's + eat', 'h + ear', 'm + eal', 'm + eat', 'p + eak'],
            example='m + aid = maid',
            spell=[('n', 'l', 'nail'), ('t', 'l', 'tail'), ('h', 'r', 'hair'), ('p', 'l', 'pail'), ('r', 'n', 'rain'),
                   ('h', 'r', 'hear'), ('m', 'l', 'meal'), ('m', 't', 'meat'), ('p', 'k', 'peak'), ('s', 't', 'seat')], sp=46,
            assess='nail meal rain meat tail seat pail peak hair head'),
    7: dict(team='“oa” and “oo”', intro=['goat', 'book'], tp=47, cols=('/oa/', '/oo/'),
            words='goat coat boat road foam food moon roof pool spoon',
            sort='goat food moon coat foam boat road roof pool spoon',
            match='boat book goat road pool roof moon soap coat boot', mp=48,
            blend=['b + oot', 'b + oat', 'g + oat', 'r + oof', 'f + oam', 'c + oat', 'm + oon', 'r + oad', 'p + ool', 'f + ood'],
            example='b + ook = book',
            spell=[('b', 't', 'boat'), ('r', 'd', 'road'), ('c', 't', 'coat'), ('g', 't', 'goat'), ('f', 'm', 'foam'),
                   ('b', 't', 'boot'), ('r', 'f', 'roof'), ('f', 'd', 'food'), ('m', 'n', 'moon'), ('p', 'l', 'pool')], sp=50,
            assess='boat roof foam moon goat food road pool coat book'),
    8: dict(team='“ack” and “eck”', intro=['sack', 'neck'], tp=51, cols=('/ack/', '/eck/'),
            words='pack back sack hack black beck deck heck neck peck',
            sort='pack back beck deck peck sack hack heck neck black',
            circle=[('pack', 'back'), ('peck', 'sack'), ('neck', 'black'), ('hack', 'beck'), ('deck', 'heck')], mp=52,
            blend=['b + ack', 'b + eck', 'h + ack', 's + ack', 'n + eck', 'p + eck', 'bl + ack', 'h + eck', 'p + ack', 'd + eck'],
            example='l + ack = lack', bp=52,
            spell=[('l', '', 'lack'), ('r', '', 'rack'), ('j', '', 'jack'), ('tr', '', 'track'), ('sn', '', 'snack'),
                   ('ch', '', 'check'), ('wr', '', 'wreck'), ('h', '', 'heck'), ('d', '', 'deck'), ('n', '', 'neck')], sp=53,
            assess='pack sack back hack beck heck deck neck peck black'),
    9: dict(team='“all” and “ell”', intro=['ball', 'bell'], tp=54, cols=('/all/', '/ell/'),
            words='ball call fall tall wall bell sell tell well shell',
            sort='ball bell fall sell shell call sell tall wall tell',
            match='ball call wall tall small bell well sell shell smell', mp=55,
            blend=['b + all', 'b + ell', 'c + all', 'sm + ell', 'f + all', 't + ell', 't + all', 's + ell', 'w + all', 'sh + ell'],
            example='h + all = hall',
            spell=[('w', '', 'well'), ('s', '', 'sell'), ('b', '', 'ball'), ('c', '', 'call'), ('sm', '', 'smell'),
                   ('sh', '', 'shell'), ('f', '', 'fall'), ('t', '', 'tall'), ('t', '', 'tell'), ('b', '', 'ball')], sp=57,
            assess='ball tall call sell bell wall smell shell fall tell'),
    10: dict(team='“-nk” and “-sk”', intro=['tank', 'mask'], tp=58, cols=('-nk', '-sk'),
             words='tank trunk plank wink link mask desk tusk flask whisk',
             sort='link flask tusk tank trunk mask wink plank desk whisk',
             match='mask pink tank wink flask ask drink desk bank whisk', mp=59,
             blend=['p + ink', 'm + ask', 'fl + ask', 'w + ink', 't + ask', 'pl + ank', 't + ank', 'd + esk', 'tr + unk', 'wh + isk'],
             example='m + ask = mask',
             spell=[('wh', '', 'whisk'), ('tr', '', 'trunk'), ('d', '', 'desk'), ('t', '', 'tank'), ('pl', '', 'plank'),
                    ('t', '', 'task'), ('w', '', 'wink'), ('fl', '', 'flask'), ('m', '', 'mask'), ('l', '', 'link')], sp=61,
             assess='pink mask flask wink task drink tank desk bank flask'),
}


def family_lesson(n):
    d = FAM[n]
    L, tp = f'l{n:02d}', d['tp']
    a, b = d['cols']
    acts = [
        intro(f'Meet the word family {d["team"]}',
              f'Look at the pictures and read their names: {d["intro"][0]} and {d["intro"][1]}.\nThey have the word family {d["team"]}.',
              [f'{L}-intro-{w}' for w in d['intro']], tp),
        reading('Read and repeat', d['words'].split(), tp,
                instructions='Your teacher reads each word aloud. Repeat after your teacher, then read the words on your own.'),
        open_task('Station 1: Word Sorting', 'Sort the words into the correct word family columns.',
                  f'Words: {"   ".join(d["sort"].split())}\n\nWrite the {a} words, then the {b} words.', tp),
    ]
    if 'circle' in d:
        acts.append(deck('Station 2: Listen & Match', 'Your teacher reads a word aloud twice. Which word did you hear?',
                         [card(i, f'{x}   or   {y}', choices=[x, y], say='') for i, (x, y) in enumerate(d['circle'], 1)], d['mp']))
    else:
        words = d['match'].split()
        listing = '   '.join(f'{i}. {w}' for i, w in enumerate(words, 1))
        acts.append(deck('Station 2: Listen & Match', f'Your teacher reads each word aloud twice. Write the number of the word that matches the picture. Words: {listing}',
                         [card(i, 'Which number matches this picture?', images=[p]) for i, p in enumerate(pics(f'{L}-match', 10), 1)], d['mp']))
    acts.append(deck('Station 3: Blend & Say', f'Build, blend and say each word aloud. Complete the word. Example: {d["example"]}',
                     [card(i, f'{x} = ____', say=x.replace('+', 'plus')) for i, x in enumerate(d['blend'], 1)], d.get('bp', d['sp'])))
    acts.append(deck('Station 4: Spell & Complete', 'Write the missing letters.',
                     [card(i, f'{s}{" _ _ " if e else " _ _ _ "}{e} = {w}'.replace('  ', ' ').strip(), say=w)
                      for i, (s, e, w) in enumerate(d['spell'], 1)], d['sp']))
    acts.append(dict(reading('Assessment: Individual Reading', d['assess'].split(), d['sp'],
                             instructions='Read the following words correctly.'), phase='post'))
    return acts


# ---------------------------------------------------------------- lessons 11-20: consonant blends
def blend_intro(n, blends, formulas, examples, images, page, charts=()):
    text = (f'Look at the pictures. Their names begin with the consonant blend {" and ".join(blends)}.\n\n'
            f'Blend the sounds:\n{"   ".join(formulas)}\n\nNow read the words:\n{"   ".join(examples)}')
    acts = [intro(f'Meet the blends {", ".join(blends)}', text, images, page)]
    if charts:
        acts.append(dict(type='reading', mode='answer', title='Read more blend words',
                         instructions='Read the words on the chart aloud. Tap Speak Answer and read, or type the words you read.',
                         prompt=f'Read more words with the {", ".join(blends)} blends.', narration='Read the words on the chart aloud.',
                         images=list(charts), xp=10, page=IMG[charts[0]]['page'], expected=None))
    return acts


def fill(title, direction, prefix, endings, choices, page):
    return deck(title, direction, [card(i, f'_ _ {e}', images=[p], choices=choices, alt='Picture with the word ending', say=f'Blank, {e}.')
                                   for i, (p, e) in enumerate(zip(pics(prefix, len(endings)), endings), 1)], page)


def pick(title, direction, prefix, n, choices, page, text='Which blend does this picture begin with?'):
    return deck(title, direction, [card(i, text, images=[p], choices=choices) for i, p in enumerate(pics(prefix, n), 1)], page)


def associate(title, prefix, words, page, direction='Match each picture with the correct word.'):
    return deck(title, direction, [card(i, 'Which word matches this picture?', images=[p], choices=words)
                                   for i, p in enumerate(pics(prefix, len(words)), 1)], page)


def assessment(words, page):
    return dict(reading('Assessment: Individual Reading', words.split(), page,
                        instructions='Read the following words correctly.'), phase='post')


def supp(a):
    a = dict(a)
    a['phase'] = 'post'
    a['title'] = 'Extra practice · ' + a['title']
    if a.get('deck'):
        a['deck'] = dict(a['deck'], title=a['title'])
    return a


def read_choose(title, prefix, words, page):
    cards = []
    for i, w in enumerate(words):
        imgs = [f'{prefix}-{3 * i + k:02d}' for k in (1, 2, 3)]
        cards.append(card(i + 1, f'{w}\nWhich picture shows the word?', images=imgs, choices=['Picture 1', 'Picture 2', 'Picture 3'], alt='Picture choice', say=w))
    return deck(title, 'Read the word. Choose the picture that shows the word.', cards, page)


DART_PROC = ['The teacher prepares a dart board labeled with different words.',
             'The learners form a line facing the dart board and wait for their designated turn.',
             'Each learner throws a pin toward the dart board and reads aloud the word indicated by the point where the pin lands.']


def blend_lessons():
    L = {}
    L[11] = blend_intro(11, ['bl-', 'br-'], ['b + l = bl', 'b + r = br'], ['bl + ue = blue', 'br + ead = bread'],
                        ['l11-intro-blue', 'l11-intro-bread'], 62) + [
        fill('Activity 1: Fill-in-the-blank Challenge', 'Fill in the blanks with the missing blend (bl-, br-).', 'l11-fill',
             ['ender', 'ick', 'ack', 'anket', 'ind', 'oken', 'oom', 'ock', 'ood', 'ush'], ['bl', 'br'], 63),
        game('Activity 2: Word Dart Board', 'Each learner will take turns throwing a pin at the dart board and read aloud the word where the pin lands.',
             DART_PROC, ['l11-dartboard'], 64),
        assessment('bread blue broke blind brush blood broom blade brick blanket', 65),
        supp(fill('Supplementary Activity Material 1', 'Fill in the blanks with the missing blends (bl or br).', 'l11-supp',
                  ['ender', 'ush', 'ass', 'usher', 'ouse', 'ead', 'ue', 'ain', 'ide', 'idge', 'anket', 'ow'], ['bl', 'br'], 66)),
        supp(pick('Supplementary Activity Material 3', 'Say the name of the picture. Circle the starting sound.', 'l11-sort', 9, ['bl', 'br'], 67)),
    ]
    L[12] = blend_intro(12, ['cl-', 'cr-'], ['c + l = cl', 'c + r = cr'], ['cl + ip = clip', 'cr + y = cry'],
                        ['l12-intro-clip', 'l12-intro-cry'], 68, ['l12-cr-chart', 'l12-cl-chart']) + [
        game('Activity 1: Picture Dart Board', 'Throw a pin at the dart board, name the picture you hit, and tell whether it begins with the consonant blend cl- or cr-.',
             ['The teacher prepares a dart board containing different pictures that begin with consonant blends cl- and cr-.',
              'The learners fall in line facing the dart board.',
              'One learner at a time comes forward and throws a pin or dart at any picture on the board.',
              'After hitting a picture, the learner identifies the picture, says its name aloud, and tells whether the word begins with cl- or cr-.',
              'The teacher checks the answer and provides guidance or correction when necessary.'], ['l12-dartboard'], 69),
        associate('Activity 2: Image-Word Association', 'l12-assoc',
                  ['clock', 'crown', 'crab', 'crocodile', 'climb', 'crow', 'cloud', 'clown', 'crawl', 'clap'], 70),
        assessment('clock crown climb crab cloud crow clash creep clap crocodile', 71),
        supp(read_choose('Supplementary Activity Material 1', 'l12-choose', ['clap', 'close', 'clean', 'class', 'clock'], 72)),
        supp(pick('Supplementary Activity Material 2', 'Say the name of the picture. Circle the starting sound.', 'l12-circle', 9, ['cl', 'cr'], 73)),
        supp(fill('Supplementary Activity Material 3', 'Fill in the blanks with the missing blends (cl or cr).', 'l12-supp',
                  ['ab', 'ap', 'ayon', 'oud', 'iff', 'own', 'radle', 'ock', 'imb', 'ow', 'ane', 'over'], ['cl', 'cr'], 74)),
        supp(pick('Supplementary Activity Material 4', 'Match the pictures with the correct cl- and cr- blend. The first one has been done as an example: crab — cr.',
                  'l12-match', 12, ['cl', 'cr'], 75)),
        supp(reading('Supplementary Activity Material 4 · Practice reading',
                     'classroom cream club claw crack crown cloak cross cling clear clothes crisp'.split(), 75,
                     instructions='Now practice reading these words.')),
    ]
    L[13] = blend_intro(13, ['dr-'], ['d + r = dr'], ['dr + one = drone'], ['l13-intro-drone'], 78, ['l13-dr-chart']) + [
        game('Activity 1: Map-Based Word Search', 'Act as a tourist guide. Lead the teacher to the dr- “country” on the word map.',
             ['Place the large “Our Word Map” poster on the floor so everyone can see the regions named after dr- words (e.g., Dragon, Drum, Dress).',
              'Each illustrated section of the island is a country the class will “visit” today.',
              'The teacher is a tourist who needs a student tourist guide.',
              'The teacher says, for example: “I want to explore the beautiful Dragon country; please bring me there!”',
              'The student guide finds the correct dr- country and leads the teacher to that spot.',
              'Take turns being the guide and choose different destinations like “Drum Land” or “Drink Province”.'], ['l13-map'], 79),
        associate('Activity 2: Word-Image Association', 'l13-assoc',
                  ['drum', 'dragon', 'dress', 'drive', 'drink', 'drill', 'draw', 'dribble', 'drop', 'dry'], 80),
        assessment('dry dress drop drum drill dragon draw drink drive dribble', 81),
        supp(associate('Supplementary Activity Material 1', 'l13-dr', ['draw', 'dragon', 'drink', 'drive', 'drum', 'dress'], 82,
                       direction='Match the dr words to the correct picture.')),
        supp(pick('Supplementary Activity Material 2', 'Say the name of each picture. Does it begin with dr? Put it in the correct box.',
                  'l13-sort', 8, ['dr', 'not dr'], 83, text='Does this picture begin with dr?')),
    ]
    color = ['flag bag rag', 'frog log from', 'cute mute flute', 'free tree bee', 'slash cash flash',
             'frame game name', 'tip sip flip', 'fresh mesh flesh', 'flower tower power', 'lost cost frost']
    L[14] = blend_intro(14, ['fl-', 'fr-'], ['f + l = fl', 'f + r = fr'], ['fl + ag = flag', 'fr + og = frog'],
                        ['l14-intro-flag', 'l14-intro-frog'], 85, ['l14-fr-chart', 'l14-fl-chart']) + [
        deck('Activity 1: Color My Word and Read Me!', 'Choose the word or words in each row that begin with the fl- or fr- sound, and read them.',
             [card(i, '   '.join(r.split()), choices=r.split()) for i, r in enumerate(color, 1)], 86),
        fill('Activity 2: Complete My Name', 'Fill in the correct beginning blend (fl- or fr-) of the picture.', 'l14-name',
             ['ashlight', 'uits', 'ame', 'ower', 'ies', 'iends', 'ute', 'idge', 'og', 'y', 'ame', 'ag'], ['fl', 'fr'], 87),
        game('Activity 3: Word Pick and Tell', 'Pick a word from the basket, sound out the beginning blend (fl- or fr-), and read the whole word aloud.',
             ['The teacher displays a basket containing word cards with fl- and fr- blends (e.g., fruits, frost, fries, floor, flower, flame).',
              'Learners form a line and take turns selecting one word card from the basket.',
              'Each learner sounds out the blend (fl- or fr-) and then blends it with the rest of the word.',
              'A learner may ask a classmate for help; if no one can help, the teacher guides them.'], ['l14-basket'], 88),
        assessment('fly fries floor frost flower fruits flame fridge flashlight frame', 89),
        supp(fill('Supplementary Activity Material 1', 'Fill in the blanks with the missing blends (fl or fr).', 'l14-supp',
                  ['ag', 'og', 'iends', 'ame', 'ies', 'ower', 'ute', 'uits', 'ame', 'our', 'action', 'y'], ['fl', 'fr'], 90)),
        supp(pick('Supplementary Activity Material 2', 'Circle the correct blend for each picture. Print the blend on the lines.', 'l14-circle', 6, ['fl', 'fr'], 91)),
        supp(associate('Supplementary Activity Material 3', 'l14-match', ['fridge', 'frog', 'friends', 'fruits', 'frost'], 92)),
        supp(deck('Supplementary Activity Material 4', 'Read each word. Match the number to the picture. Words: 1. flesh 2. flashlight 3. flatbed 4. flag 5. floor lamp 6. flat 7. flamingo 8. flowers 9. flood 10. flash',
                  [card(i, 'Which number matches this picture?', images=[p]) for i, p in enumerate(pics('l14-flmatch', 10), 1)], 93)),
    ]
    tune15 = [('globe', 'grobe'), ('glapes', 'grapes'), ('gloves', 'groves'), ('gleen', 'green'), ('glass', 'grass'),
              ('graph', 'glaph'), ('grow', 'glow'), ('grill', 'glill'), ('glad', 'grad'), ('graduate', 'gladuate')]
    L[15] = blend_intro(15, ['gl-', 'gr-'], ['g + l = gl', 'g + r = gr'], ['gl + ue = glue', 'gr + ass = grass'],
                        ['l15-intro-glue', 'l15-intro-grass'], 96, ['l15-gr-chart', 'l15-gl-chart']) + [
        deck('Activity 1: Fine-Tuning Vocabulary', 'Choose the word that is correctly associated with the picture.',
             [card(i, '   or   '.join(p), images=[img], choices=list(p), say='') for i, (img, p) in enumerate(zip(pics('l15-tune', 10), tune15), 1)], 97),
        game('Activity 2: Grab and Read!', 'Choose your favorite shirt from the clothesline, say “I love this shirt!”, and read the word on it loudly for the class to hear.',
             ['Display the clothesline and review the g sound combined with l and r.',
              'Learners form a line and one by one “walk the runway” to the board, choose a shirt, and announce, “I love this shirt!”',
              'The teacher asks the learner to read the word on the shirt loudly.',
              'A learner can use a “Lifeline” by choosing a classmate to help; the teacher gives a gentle prompt if needed.',
              'When the word is read correctly, the class gives a quick “sparkle clap”.'], ['l15-shirts'], 98),
        assessment('glad grapes globe green gloves graph glass grill glue graduate', 99),
        supp(fill('Supplementary Activity Material 1', 'Fill in the blanks with the missing blends (gl or gr).', 'l15-supp',
                  ['obe', 'apes', 'ass', 'oves', 'ue', 'asses', 'ill', 'een', 'aph', 'am', 'ass', 'ider'], ['gl', 'gr'], 100)),
        supp(deck('Supplementary Activity Material 2', 'Trace and rewrite the word.',
                  [card(i, f'{w}\nWrite the word.', images=[p], say=w) for i, (p, w) in
                   enumerate(zip(pics('l15-trace', 5), ['glue', 'glass', 'globe', 'glad', 'gloves']), 1)], 101)),
        supp(pick('Supplementary Activity Material 3', 'Match the pictures with the correct gl- and gr- blend. The first one has been done as an example: glue — gl.',
                  'l15-match', 12, ['gl', 'gr'], 102)),
        supp(reading('Supplementary Activity Material 3 · Practice reading',
                     'grammar grocery glad grade group greetings glow glory gladiator green globe grey'.split(), 102,
                     instructions='Now practice reading these words.')),
    ]
    L[16] = blend_intro(16, ['pl-', 'pr-'], ['p + l = pl', 'p + r = pr'], ['pl + ane = plane', 'pr + ay = pray'],
                        ['l16-intro-plane', 'l16-intro-pray'], 105) + [
        fill('Activity 1: Fill-in-the-blank Challenge', 'Fill in the blanks with the missing blend (pl-, pr-).', 'l16-fill',
             ['ay', 'ince', 'ate', 'int', 'ane', 'ay', 'esent', 'ice', 'ier', 'us'], ['pl', 'pr'], 106),
        game('Activity 2: Word Dart Board', 'Throw a dart at the board, read the word it lands on, and say it clearly.',
             ['The teacher prepares a dartboard with different words on it.', 'The learners line up in front of the dartboard.',
              'Each learner takes a turn to throw a dart.', 'The learner reads the word where the dart lands.',
              'The class listens and gives a clap after each turn.'], ['l16-dartboard'], 107),
        assessment('plate pray plane print plus prince plier present play price', 108),
        supp(dict(reading('Supplementary Activity Material 1', ['Read', 'the', 'pl-', 'and', 'pr-', 'words', 'on', 'the', 'chart.'], 109,
                          instructions='Read the words on the chart aloud. Tap Speak Answer and read, or type the words you read.',
                          images=['l16-chart'], check=False), prompt='Read the pl- and pr- words on the chart.')),
        supp(fill('Supplementary Activity Material 2', 'Fill in the blanks with the missing blends (pl or pr).', 'l16-supp',
                  ['esent', 'ums', 'ant', 'inter', 'us', 'ier', 'opeller', 'ay', 'ize', 'ince', 'umber', 'uck'], ['pl', 'pr'], 110)),
    ]
    L[17] = blend_intro(17, ['st-', 'str-'], ['s + t = st', 'st + r = str'], ['st + ar = star', 'str + aw = straw'],
                        ['l17-intro-star', 'l17-intro-straw'], 111) + [
        game('Activity 1: Picture Dart Board', 'Throw a dart, name the picture, and say if it starts with st- or str-.',
             ['The teacher prepares a dartboard with pictures on it.', 'The learners line up in front of the dartboard.',
              'Each learner takes a turn to throw a dart.', 'The learner looks at the picture and says its name.',
              'The learner tells if the word starts with st- or str-.', 'The class listens and claps after each turn.'], ['l17-dartboard'], 112),
        associate('Activity 2: Word-Image Association', 'l17-assoc',
                  ['storm', 'strap', 'stop', 'street', 'stone', 'string', 'stick', 'stripes', 'steel', 'strawberry'], 113),
        assessment('storm strap stop street stone string steel stripes stick strawberry', 114),
    ]
    L[18] = blend_intro(18, ['sh-', 'sl-'], ['s + h = sh', 's + l = sl'], ['sh + ip = ship', 'sl + eep = sleep'],
                        ['l18-intro-ship', 'l18-intro-sleep'], 115) + [
        game('Activity 1: Map-Based Word Search', 'Act as a local scout and lead the curious traveler to the correct “country” on the Word Map based on the clues about each sh- or sl- destination.',
             ['Lay the Word Map on the floor. Every “country” on this island is named after a special sound (sh- or sl-).',
              'One student is the Local Scout (tourist guide); the teacher or another student is the Curious Traveler.',
              'The Traveler describes a country with clues, e.g., “I want to visit the country where the sun is always bright!” (Shine).',
              'The Scout leads the Traveler to the correct spot. The class “unlocks” the border by chanting the word with a gesture.',
              'The Scout becomes the Traveler for the next round.'], ['l18-map'], 116),
        associate('Activity 2: Word-Image Association', 'l18-assoc',
                  ['shower', 'slide', 'shine', 'slug', 'shake', 'shovel', 'slice', 'slipped', 'shock', 'slap'], 117),
        assessment('shower slide shine slug shake slice shovel slipped shock slap', 118),
        supp(dict(reading('Supplementary Activity Materials 1 and 2', ['Read', 'the', 'sl-', 'and', 'sh-', 'words.'], 119,
                          instructions='Read the words on the charts aloud. Tap Speak Answer and read, or type the words you read.',
                          images=['l18-sl-chart', 'l18-sh-chart'], check=False), prompt='Read the sl- and sh- words on the two charts.')),
        supp(read_choose('Supplementary Activity Material 3', 'l18-choose', ['sleeve', 'slime', 'slow', 'slouch', 'sled'], 121)),
    ]
    L[19] = blend_intro(19, ['sp-', 'spr-', 'spl-'], ['s + p = sp', 's + p + r = spr', 's + p + l = spl'],
                        ['sp + ider = spider', 'spr + ay = spray', 'spl + ash = splash'],
                        ['l19-intro-spider', 'l19-intro-spray', 'l19-intro-splash'], 122) + [
        fill('Activity 1: Fill-in-the-blank Challenge', 'Fill in the blanks with the missing blend (sp-, spr- or spl-).', 'l19-fill',
             ['ade', 'ite', 'ash', 'oon', 'ain', 'iral', 'aghetti', 'ay', 'onge', 'inkler', 'ider', 'lit'], ['sp', 'spr', 'spl'], 123),
        game('Activity 2: Word Pick and Tell', 'Pick a word, say the beginning blend, and read the whole word.',
             ['The teacher prepares a basket with words that have sp-, spr- and spl- blends.', 'The learners line up in front of the board.',
              'Each learner takes a turn to pick one word from the basket.', 'The learner sounds out the beginning blend and reads the whole word.',
              'If the learner needs help, a classmate or the teacher can assist.', 'The class listens and claps after each turn.'], ['l19-basket'], 124),
        assessment('spear sprite spoon sprain spider spring sponge spray spaghetti sprinkle splash split', 125),
    ]
    tune20 = [('tractor', 'fractor'), ('priangle', 'triangle'), ('crumpet', 'trumpet'), ('traffic', 'craffic'), ('trash', 'crash'),
              ('trophy', 'crophy'), ('creasure', 'treasure'), ('tripod', 'cripod'), ('tree', 'free'), ('trolley', 'prolley')]
    L[20] = blend_intro(20, ['tr-'], ['t + r = tr'], ['tr + ain = train'], ['l20-intro-train'], 126) + [
        deck('Activity 1: Fine-Tuning Vocabulary', 'Choose the word that is correctly associated with the picture.',
             [card(i, '   or   '.join(p), images=[img], choices=list(p), say='') for i, (img, p) in enumerate(zip(pics('l20-tune', 10), tune20), 1)], 127),
        game('Activity 2: Grab and Read!', 'Pick a shirt, say “I love this shirt,” and read the word on it aloud.',
             ['The teacher posts a clothesline with shirts on the board.', 'The learners line up in front of the board.',
              'Each learner picks a shirt and says, “I love this shirt!”', 'The teacher asks the learner to read the word on the shirt aloud.',
              'If the learner needs help, a classmate or the teacher can assist.', 'The class listens and claps after each turn.'], ['l20-shirts'], 128),
        assessment('tractor triangle trumpet traffic trash trophy treasure tripod tree trolley', 129),
    ]
    return L


# ---------------------------------------------------------------- lessons 21-25: sight words
SIGHT = {
    21: dict(fry='First', words='said were one come does two they their what where', page=130,
             game=('Activity 1: Fishing Sight Words', 'Pick a fish from the basin and read the word on it aloud.',
                   ['The teacher prepares a big basin with fish cutouts that have words on them (e.g., said, were, one, come, does, two, they, their, what, where, when, why).',
                    'The learners gather around the basin.', 'Each learner takes a turn to pick one fish.',
                    'The learner reads the word written on the fish aloud.', 'The class listens and claps after each turn.'], 'l21-fish'),
             phrases=['what they said', 'where they were', 'does it come', 'one or two', 'why they went'],
             sentences=['They said it was fun.', 'Where were you yesterday?', 'Does she come every day?', 'I saw one dog and two cats.', 'Why is their bag on the floor?'],
             assess='said two were they one their come what does where', ap=131),
    22: dict(fry='2nd', words='again because around every know only people right their would', page=132,
             game=('Activity 1: Snake and Ladder Sight Words', 'Roll the dice, move to the number, and read the word on that space aloud.',
                   ['The teacher prepares a snake and ladder board with words and a dice.', 'The learners line up and take turns rolling the dice.',
                    'The learner moves to the correct number on the board.', 'The learner reads the word on that space aloud.',
                    'The class listens and claps after each turn.'], 'l22-snake-ladder'),
             phrases=['again and again', 'because they know', 'around the corner', 'only the right answer', 'every people’s story'],
             sentences=['I would go again tomorrow.', 'They walked around the park.', 'She knows every word.', 'People only want the truth.', 'Their answer was right.'],
             assess='again only because people around right every their know would', ap=133),
    23: dict(fry='3rd', words='above enough through though young laugh once both always together', page=134,
             game=('Activity 1: Spin the Wheel of Sight Words', 'Spin the wheel and read the word it points to aloud.',
                   ['The teacher prepares a Spin Wheel with sight words.', 'The learners line up and take turns spinning the wheel.',
                    'The learner reads the word the arrow points to aloud.', 'The class listens and claps after each turn.'], 'l23-wheel'),
             phrases=['above the clouds', 'enough for everyone', 'through the door', 'young and strong', 'always together'],
             sentences=['She climbed above the hill.', 'I have enough food for lunch.', 'We walked through the park.', 'The young boy can laugh loudly.', 'They always work together.'],
             assess='above laugh enough once through both though always young together', ap=135),
    24: dict(fry='4th', words='beautiful country different important against enough write without second water', page=136,
             game=('Activity 1: Mystery Box of Sight Words', 'Take turns drawing a word card from the decorated Mystery Box and read it aloud.',
                   ['The teacher prepares a decorated Mystery Box.', 'The teacher prints the selected sight words and places them inside the box.',
                    'Learners form a line, take turns drawing one word card from the Mystery Box, and read the word aloud.'], 'l24-mystery-box'),
             phrases=['beautiful country', 'different people', 'important work', 'against the wall', 'second water bottle'],
             sentences=['The beautiful flower is in the garden.', 'Our country is big and strong.', 'She has a different idea.', 'It is important to read every day.', 'He drank enough water after the game.'],
             assess='beautiful enough country write different without important second against water', ap=137),
    25: dict(fry='5th', words='already certain early family mountain sentence special sure toward usually', page=138,
             game=('Activity 1: Sight Words Hunting', 'Take turns selecting a leaf from the Word Hunt Table and read aloud the word written on it. You can also hunt for the words in the letter grid.',
                   ['The teacher prepares the Word Hunt Table and posts it on the board, with the target words written on the leaf.',
                    'Learners form a line in front of the board and take turns finding or hunting a word from the leaf.',
                    'After selecting, the learner reads aloud the word he or she has found.'], 'l25-word-hunt'),
             phrases=['already finished work', 'certain answer', 'early morning', 'family dinner', 'special gift'],
             phrases2=['already sure', 'towards the mountain', 'special family', 'early today', 'usually certain'],
             sentences=['She already knows the way.', 'I am certain this is right.', 'We woke up early today.', 'The family went to the park.', 'He climbed the mountain with friends.'],
             assess='already special certain toward mountain sure early usually family sentence', ap=140),
}


def sight_lesson(n):
    d = SIGHT[n]
    title, direction, proc, img = d['game']
    images = [img] + (['l25-leaf'] if n == 25 else [])
    acts = [
        intro('Meet the sight words',
              f'These are 10 difficult words to read from Fry’s {d["fry"]} 100 list:\n{"   ".join(d["words"].split())}\n\nFirst, review the words you learned before.', [], d['page']),
        game(title, direction, proc, images, d['page'] + (1 if n == 25 else 0)),
        reading('Activity 2: Reading Phrases', d['phrases'], d['page'] + 1, instructions='Read each phrase aloud.', sep='\n'),
    ]
    if 'phrases2' in d:
        acts.append(reading('Activity 2: Reading Phrases (set 2)', d['phrases2'], d['page'] + 1, instructions='Read each phrase aloud.', sep='\n'))
    acts.append(reading('Activity 3: Reading Simple Sentences', d['sentences'], d['page'] + 1, instructions='Read each sentence aloud.', sep='\n'))
    acts.append(assessment(d['assess'], d['ap']))
    return acts


# ---------------------------------------------------------------- lesson list
LESSONS = []
LESSONS.append(dict(title='Before we begin', subtitle='Pre-assessment', phase='pre', pages=[9, 10, 11],
                    objective='Read short-vowel, word-family, consonant-blend and sight words so your teacher knows where to begin.',
                    acts=[dict(a, phase='pre') for a in toolkit(PRE_TASKS, 'pre-assessment', 'pre')]))
for n in range(1, 6):
    b = CVC[n]['base']
    LESSONS.append(dict(title=f'Build, say and read CVC words with short “{CVC[n]["v"]}”', subtitle=f'CVC Short “{CVC[n]["v"]}”',
                        phase='learn', pages=list(range(b, b + 6)), acts=cvc_lesson(n),
                        objective=f'Sound out, blend and read consonant-vowel-consonant words with the short “{CVC[n]["v"]}” sound.'))
FAM_PAGES = {6: range(43, 47), 7: range(47, 51), 8: range(51, 54), 9: range(54, 58), 10: range(58, 62)}
for n in range(6, 11):
    LESSONS.append(dict(title=f'Read words in the {FAM[n]["team"]} word families', subtitle=f'Word Family {FAM[n]["team"]}',
                        phase='learn', pages=list(FAM_PAGES[n]), acts=family_lesson(n),
                        objective=f'Read, sort, blend and spell words with the {FAM[n]["team"]} word families.'))
BL = blend_lessons()
BLEND_META = {11: ('br-, bl-', range(62, 68)), 12: ('cr-, cl-', range(68, 78)), 13: ('dr-', range(78, 85)),
              14: ('fr-, fl-', range(85, 96)), 15: ('gl-, gr-', range(96, 105)), 16: ('pl-, pr-', range(105, 111)),
              17: ('st-, str-', range(111, 115)), 18: ('sh-, sl-', range(115, 122)), 19: ('sp-, spr-, spl-', range(122, 126)),
              20: ('tr-', range(126, 130))}
for n in range(11, 21):
    name, pages = BLEND_META[n]
    LESSONS.append(dict(title=f'Blend and read words that begin with {name}', subtitle=f'Consonant Blend {name}',
                        phase='learn', pages=list(pages), acts=BL[n],
                        objective=f'Blend the consonant sounds {name} and read words that begin with them.'))
for n in range(21, 26):
    d = SIGHT[n]
    LESSONS.append(dict(title=f'10 difficult words to read from Fry’s {d["fry"]} 100 list', subtitle='Basic Sight Words',
                        phase='learn', pages=list(range(d['page'], d['ap'] + 1)), acts=sight_lesson(n),
                        objective=f'Read the sight words {", ".join(d["words"].split())} in words, phrases and sentences.'))
LESSONS.append(dict(title='Show what you learned', subtitle='Post-assessment', phase='post', pages=[141, 142, 143],
                    objective='Read the Level 3 words again to show how much you have grown.',
                    acts=[dict(a, phase='post') for a in toolkit(POST_TASKS, 'post-assessment', 'post')]))

ISSUES = [
    ('Level 3 · Lesson 3 Speed Read (PDF p30) prints “sim”, which is not in the lesson word list; the assessment uses “hid”.', 'Kept as printed in Speed Read. Teachers may substitute hid.'),
    ('Level 3 · Lesson 6 assessment (PDF p46) lists “head”, which is not an ea/ai word from the lesson; station words list “hair”.', 'Kept as printed. Teacher judgment applies.'),
    ('Level 3 · Lesson 9 Station 1 (PDF p54) lists “sell” twice and omits “well”.', 'Kept as printed; pupils may note the repeated word.'),
    ('Level 3 · Lesson 10 teacher step 2 (PDF p58) says “all” and “ell”; the lesson is about -nk and -sk. Its assessment lists “flask” twice.', 'Intro wording uses -nk and -sk. Assessment kept as printed.'),
    ('Level 3 · Lesson 11 supplementary materials are numbered 1 and 3 (no Material 2).', 'Shown with the printed numbers.'),
    ('Level 3 · Lesson 15 Supplementary Material 3 (PDF p102) direction says “cl- and cr-” but the pictures are gl-/gr- words.', 'Direction changed to gl- and gr- blends.'),
    ('Level 3 · Lesson 25 prints two “Activity 2: Reading Phrases” lists (PDF p139).', 'Both lists are included (the second as “set 2”).'),
    ('Level 3 · Many module pictures are credited to AI image generators and Canva (References, PDF p144).', 'Pictures are reused from the module as supplied; ownership remains with their sources.'),
    ('Level 3 · Online answers are recorded for teacher review. Word reading uses voice-to-text word matching as a guide only, not a pronunciation score.', 'Teachers confirm passing scores (8/10, 15/20) from observation.'),
]


def build():
    cards, lessons_meta = {}, []
    pupil_pages = set()
    for pos, L in enumerate(LESSONS, 1):
        counters = {}
        for a in L['acts']:
            ph = a.get('phase', 'learn' if L['phase'] == 'learn' else L['phase'])
            a['phase'] = ph
            counters[ph] = counters.get(ph, 0) + 1
            a['position'] = counters[ph]
            if a.get('deck'):
                cards[f'{pos}:{ph}:{a["position"]}'] = a['deck']
            if a['page']:
                pupil_pages.add(a['page'])
        lessons_meta.append(dict(position=pos, title=L['subtitle'], phase=L['phase'], worksheet_pages=L['pages'],
                                 teacher_pages=L['pages'], activities=len(L['acts'])))
    manifest = dict(version=1, level_id=LEVEL_ID, title='Word Recognition · Level 3', page_count=PDF.page_count,
                    pupil_pages=sorted(pupil_pages), lessons=lessons_meta, cards=cards)
    with open(os.path.join(ROOT, 'database', 'level3-cards.json'), 'w') as f:
        json.dump(manifest, f, ensure_ascii=False, indent=1)
    return manifest


def h(s):
    if s is None:
        return 'NULL'
    return f"CONVERT(0x{str(s).encode('utf-8').hex()} USING utf8mb4)" if s != '' else "''"


def sql(manifest):
    out = ['-- BULIG Level 3 (Word Recognition): additive content migration. Select your EXISTING database first.',
           '-- No accounts, progress, responses or existing lessons are changed. Safe to import again.',
           'SET NAMES utf8mb4;', 'START TRANSACTION;',
           f"INSERT INTO modules (id,level_id,title,grade_level) SELECT (SELECT COALESCE(MAX(m.id),0)+1 FROM modules m),{LEVEL_ID},{h('Word Recognition · Level 3')},NULL WHERE NOT EXISTS (SELECT 1 FROM modules WHERE level_id={LEVEL_ID});",
           f'SET @module_id=(SELECT id FROM modules WHERE level_id={LEVEL_ID} ORDER BY id LIMIT 1);']
    total_acts = 0
    for pos, L in enumerate(LESSONS, 1):
        guide = page_text(L['pages'])
        cover = next((i for a in L['acts'] for i in a['images']), None)
        out.append(f"INSERT INTO lessons (module_id,position,title,subtitle,objectives,teacher_guide,source_pages,image_path,published) SELECT @module_id,{pos},{h(L['title'])},{h(L['subtitle'])},{h(L['objective'])},{h(guide)},{h(json.dumps(L['pages']))},{h(src(cover) if cover else None)},1 WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE module_id=@module_id AND position={pos});")
        out.append(f'SET @lesson_id=(SELECT id FROM lessons WHERE module_id=@module_id AND position={pos});')
        for kind in ('pre', 'post'):
            acts = [a for a in L['acts'] if a['phase'] == kind]
            if not acts:
                continue
            rubric = '\n'.join(f"{a['title']}: {a.get('rubric', 'Read the words aloud; the teacher checks each word sounded out correctly.')}" for a in acts if a['mode'] != 'none')
            t = f"{L['subtitle']} · {'pre-assessment' if kind == 'pre' else 'assessment'}"
            out.append(f"INSERT INTO assessments (lesson_id,kind,title,rubric,source_page,source_note) SELECT @lesson_id,'{kind}',{h(t)},{h(rubric)},{acts[0]['page']},{h('Untimed digital adaptation of the individual reading assessment. Teacher observation decides pass or fail.')} WHERE NOT EXISTS (SELECT 1 FROM assessments WHERE lesson_id=@lesson_id AND kind='{kind}');")
        for a in L['acts']:
            total_acts += 1
            ph, p = a['phase'], a['position']
            assessment = f"(SELECT id FROM assessments WHERE lesson_id=@lesson_id AND kind='{ph}')" if ph in ('pre', 'post') else 'NULL'
            imgs = [src(i) for i in a['images']]
            excerpt = one_level(PDF[a['page'] - 1].get_text().strip())[:1500] if a['page'] else ''
            out.append('INSERT INTO activities (lesson_id,assessment_id,phase,position,title,type,instructions,prompt,image_path,image_paths,narration,expected_text,xp_reward,source_page,source_excerpt,published,revision,response_mode) '
                       f"SELECT @lesson_id,{assessment},'{ph}',{p},{h(a['title'])},'{a['type']}',{h(a['instructions'])},{h(a['prompt'])},{h(imgs[0] if imgs else None)},{h(json.dumps(imgs))},{h(a['narration'])},{h(a.get('expected'))},{a['xp']},{a['page']},{h(excerpt)},1,1,'{a['mode']}' "
                       f"WHERE NOT EXISTS (SELECT 1 FROM activities WHERE lesson_id=@lesson_id AND phase='{ph}' AND position={p});")
            out.append(f"SET @activity_id=(SELECT id FROM activities WHERE lesson_id=@lesson_id AND phase='{ph}' AND position={p});")
            out.append(f"INSERT INTO questions (activity_id,content,grading) SELECT @activity_id,{h(a['prompt'])},'teacher' WHERE NOT EXISTS (SELECT 1 FROM questions WHERE activity_id=@activity_id);")
            if ph in ('pre', 'post'):
                out.append(f"INSERT IGNORE INTO assessment_questions(assessment_id,question_id) SELECT a.assessment_id,q.id FROM activities a JOIN questions q ON q.activity_id=a.id WHERE a.id=@activity_id AND a.assessment_id IS NOT NULL;")
    for d, r in ISSUES:
        out.append(f'INSERT INTO content_issues (description,resolution) SELECT {h(d)},{h(r)} WHERE NOT EXISTS (SELECT 1 FROM content_issues WHERE BINARY description=BINARY {h(d)});')
    out.append(f"UPDATE bulig_levels SET title={h('Word Recognition')},published=1 WHERE id={LEVEL_ID} AND (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id={LEVEL_ID} AND l.published=1)>={len(LESSONS)};")
    out.append("INSERT IGNORE INTO schema_migrations(version) VALUES('009_level3_content');")
    out.append('COMMIT;')
    with open(os.path.join(ROOT, 'database', 'migrations', '009_level3_content.sql'), 'w') as f:
        f.write('\n'.join(out) + '\n')
    return total_acts


if __name__ == '__main__':
    m = build()
    n = sql(m)
    print(len(LESSONS), 'lessons,', n, 'activities,', len(m['cards']), 'card decks,',
          sum(len(d['cards']) for d in m['cards'].values()), 'cards')
