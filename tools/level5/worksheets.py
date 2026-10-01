"""Level 5 worksheet pages (Grades 3–6 puzzles, word lists, paragraph fill-ins), the Grade 2 assessment on
p26 and the questions that run over from the page before — typed from the module pages.
Letter puzzles are rebuilt as tappable letter grids (read from the PDF text positions with grids.py);
puzzles that cannot be retyped (linked-letter boxes, spiral clue boxes, crossword frames, the spin wheel)
are cut out as one worksheet picture without the page heading or directions.
"""
import os, sys
import pymupdf
from PIL import Image

sys.path.insert(0, os.path.dirname(__file__))
from handcards import match, lettered

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
PUB = os.path.join(ROOT, 'public', 'assets', 'images', 'level5')


def Q(title, text, choices=(), lead='', **kw):
    return dict(title=str(title), text=text, images=[], choices=list(choices), lead=lead, **kw)


def R(text='', lead='', heading='', **kw):
    return dict(title='Read', text=text, images=[], choices=[], lead=lead, heading=heading, **kw)


def numbered(lines, lead='', start=1):
    return [Q(i, t, lead=lead) for i, t in enumerate(lines, start)]


# worksheet pictures: (grade, page, clip in points)
FIGURES = {
    '4:21': (4, 21, (48, 175, 560, 748)),
    '4:23': (4, 23, (50, 220, 560, 685)),
    '4:24': (4, 24, (50, 45, 235, 180)),
    '5:50': (5, 50, (118, 375, 450, 750)),
    '6:13': (6, 13, (50, 148, 592, 698)),
    '6:15': (6, 15, (50, 170, 560, 700)),
    '6:16': (6, 16, (95, 220, 545, 610)),
}


def figure(key, label='Worksheet from the module'):
    g, pn, clip = FIGURES[key]
    doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf'))
    pix = doc[pn - 1].get_pixmap(dpi=170, clip=pymupdf.Rect(*clip))
    im = Image.frombytes('RGB', (pix.width, pix.height), pix.samples)
    name = f'w{pn:03d}.webp'
    os.makedirs(os.path.join(PUB, f'g{g}'), exist_ok=True)
    im.save(os.path.join(PUB, f'g{g}', name), 'WEBP', quality=85)
    return dict(src=f'assets/images/level5/g{g}/{name}', alt=label, label='')


def fig_card(key, lead='', words=(), label='Worksheet from the module'):
    return dict(title='Puzzle', text='', images=[figure(key, label)], choices=[], lead=lead, figure=True, words=list(words))


def grid_card(rows, words=(), lead=''):
    return dict(title='Puzzle', text='', images=[], choices=[], lead=lead, grid=rows, words=list(words))


GRIDS = {
    '4:19': ['qkxheavys', 'fulllgrow', 'oheysbkua', 'different', 'mbtgsidep', 'wenoughta', 'counthoRg', 'cleenttse'],
    '4:20': ['alpwhidj', 'dcountry', 'dinlobik', 'rtdatevs', 'eyaneces', 'sandgare', 'smfielda', 'ovcampft'],
    '4:25': ['lnapsfd', 'iohslip', 'sksnosr', 'tcetwht', 'eonucig', 'nndhdnf', 'mkissen', 'eybohfk', 'tulearn', 'pmarkdl', 'quiteaj'],
    '4:28': ['lflewk', 'koohse', 'hungap', 'ngrewt', 'ehidnn', 'etlhfe', 'sabits', 'bdeadg'],
    '4:36': ['PCWANSWER', 'ARHAMOUNT', 'RMOSQUARE', 'TELBXTZWV', 'LDEGLHUGE', 'CIRCLEJIQ', 'OUCOEHMKU', 'IMFSLODSA', 'NOITCARFL'],
    '5:32': ['SUGARRTYUUISH', 'PTYUOTEARMRAA', 'OSCISSORSBTNN', 'ORTYEUIOARHDD', 'NQASDFGHLEJWK', 'SOLDIERJTLKIE', 'RUBBERERTLLCR',
             'GHJKLOIUYAMHC', 'ZXCVBNVISITGH', 'ZIPPERQWTHWFI', 'RTYUIOSTOVEDE', 'NOONQWERTJASF'],
    '5:34': ['MEDICINELIPSL', 'IJOFFICEGATEE', 'RUQWERTYRUIOS', 'RIASDFGHOJKLS', 'OCZXCVBNCMHYO', 'REQAWSFTECYUN', 'HAMMERGHROWFH',
             'CURTAINBYCHJK', 'AEFGHTYNYODFG', 'CUPBOARDRAFRY', 'NAPKINEDGJKLT', 'CROWNCRAYONSD'],
    '5:56': ['GARAGE', 'AZXWEN', 'CAPTAI', 'TBVJOP', 'IFLWTM', 'NFSHCY', 'MUGAMA', 'DHCIOA', 'OHKFLN'],
    '5:58': ['BEAUTIFUL', 'WQIXLOAEN', 'CALCULATE', 'ZIOMZRGTY', 'ENVIRONME', 'NHHLKJEFY', 'TKUKMLQNN'],
    '6:11': ['atlUciffiD', 'yelLowskin', 'vm.Partygt', 'aptwforcen', 'eesamnshne', 'hrgIigageR', 'rasTdlnure', 'iteStedoaf',
             'auaHeadnlf', 'praCticeji', 'tesUbjectD'],
    '6:12': ['materialc', 'bcguessto', 'lthngr.hr', 'onugiinon', 'ougiocoue', 'doesnhisr', 'yminorgai', 'gardenend', 'nforwarde', 'teamhleds'],
    '6:17': ['neppahatm', 'dgicostca', 'ercisabnj', 'sakrcfuro', 'cynraewar', 'rincludee', 'iarmesold', 'believean', 'egrownxco', 'kitchensw'],
    '6:23': ['wcirclessk', 'hamountqpn', 'omnaorgura', 'lpadaoraih', 'ealwaysrnt', 'bigger.egh', 'nginebtwsg', 'engiserori', 'todayewhen', 'awhenplive'],
}
TAP = 'Tap the letters of each word you find to mark them.'


def pages():
    P = {}
    # ---- questions that run over from the page before -------------------------------------------
    P['1:11'] = dict(instruction='Listen carefully as your teacher reads the sentences/passage. Then, answer the questions below.', cards=[
        R('This is our tools. We have shovel and rake. We have to take care of our tools. Tools are very important for us.', heading='(Read Aloud) “Our Tools”'),
        Q(8, 'What is the main idea of the story?', ['a. Tools are fun', 'b. Tools are useful and we should take care of them', 'c. Tools are expensive'])])
    P['1:22'] = dict(instruction='Listen to each question. Write the letter of the correct answer.', cards=[
        Q(5, '“I have a toy that I can make go up in the sky when the wind blows. What is it?”', ['a. Car', 'b. Yoyo', 'c. Kite', 'd. Doll'])])
    P['1:48'] = dict(instruction='Circle the letter of the correct answer.', cards=[
        Q(5, 'What is Not mentioned as being in the kitchen?', ['a. Spoons', 'b. Bowls', 'c. Knives', 'd. Cups'])])
    P['1:52'] = dict(instruction='Circle the letter of the correct answer.', cards=[
        Q(5, 'What is something you CANNOT do according to the sentences?', ['a. Read', 'b. Write', 'c. Play', 'd. Sing'])])
    P['3:12'] = dict(instruction='Choose the letter of the correct answer.', cards=[
        Q(50, 'In "The Little Seed\'s Journey," what does the word sprouted mean?', ['a) to be blown by the wind', 'b) to be planted in a field', 'c) to start to grow', 'd) to collect food'])])
    P['3:28'] = dict(instruction='Activity 6: SET A SUMMER FUN', cards=[
        Q(6, 'Which of these is the best example of being helpful?', ['a. picking flowers', 'b. cleaning up', 'c. swimming'])])
    P['3:56'] = dict(instruction='Choose the letter of the correct answer.', cards=[
        Q(44, 'From where can we get oil?', ['a) coconut husk', 'b) ripe coconut milk', 'c) young coconut meat'])])
    P['4:10'] = dict(instruction='Listen to the sentence and choose the correct answer.', cards=[
        Q(20, '(Read Aloud): "The book is under the table."\nWhere is the book? ______', ['a) above the table', 'b) beside the table', 'c) under the table.'])])
    P['4:16'] = dict(instruction='Choose the letter of the correct answer.', cards=[
        Q(40, 'What do the children dream of at night?', ['A. Playing games', 'A. A better future', 'B. Going to school', 'C. Traveling far'])])
    P['5:26'] = dict(instruction='Read the context clues to identify the meaning of the underlined word.', cards=[
        Q(10, 'Listening to the music inspires me to do better in class.', ['a. poem', 'b. speech', 'c. story', 'd. tune'], lead='What does the word “music” mean?')])
    # ---- Grade 2 p26 -----------------------------------------------------------------------------
    P['2:26'] = dict(instruction='Activity 4- Assessment · A. Vocabulary. Directions: Match the word to its meaning. (Teacher may read aloud.)', cards=[
        match([(1, 'Everywhere'), (2, 'Fresh'), (3, 'Air'), (4, 'Shelter'), (5, 'Live')],
              ['a. To stay or make a home in', 'b. Protection or a place to stay', 'c. All around us; in many places', 'd. What we breathe', 'e. Clean or new']),
        R(lead='B. Listening Comprehension', text='Directions: Listen carefully as the story is read aloud. Then answer the questions.'),
        Q(1, 'Where are trees found according to the story?', ['a. In one place only', 'b. Everywhere', 'c. Only in the city']),
        Q(2, 'What do trees give us?', ['a. Dirty air', 'b. Clean and fresh air', 'c. No air at all']),
        Q(3, 'Who lives up in the trees?', ['a. Cats', 'b. Little birds', 'c. Fish']),
        Q(4, 'What is a shelter for the birds in the story?', ['a. Trees', 'b. Cars', 'c. Houses']),
        Q(5, 'What is the poem mostly talking about?', ['a. Games', 'b. Trees', 'c. Weather'])])
    # ---- Grade 4 worksheets --------------------------------------------------------------------
    P['4:18'] = dict(instruction='Point Act Game · Activity 1. Directions: Pick a word card inside the box or bag. Read the word. Find the words inside the classroom. Read, point, act and write them on the board (e.g. half ½, 9, 11, etc.) Do it again and again.', cards=[
        R(words=['color', 'eight', 'half', 'hundred', 'even', 'nine', 'white', 'gray', 'eleven', 'huge', 'large', 'number', 'piece'], lead='Word cards')])
    P['4:19'] = dict(instruction='Find and Color It · Activity 2. Directions: Search the words inside the box below. The words may appear vertically, diagonally or horizontally. Encircle the words with pencil or color the words with crayons.', cards=[
        grid_card(GRIDS['4:19'], ['left', 'else', 'gone', 'full', 'count', 'clean', 'right', 'heavy', 'side', 'enough', 'different', 'page'], TAP)])
    P['4:20'] = dict(instruction='Naming Me · Activity 3. Directions: Find and encircle the words in the worksheet. The words appear horizontally, diagonally or vertically. Choose from the given letters below.', cards=[
        grid_card(GRIDS['4:20'], ['place', 'city', 'country', 'camp', 'river', 'field', 'land', 'pond', 'sand', 'sea', 'sky', 'address'], TAP)])
    P['4:21'] = dict(instruction='Hidden Letters · Activity 4. Directions: Fill in the missing letter in the repetitive words in the activity sheet. Words to write can be seen below.', cards=[
        fig_card('4:21', 'Write the missing letters in each word box.', ['people', 'family', 'children', 'friend', 'king', 'Miss', 'queen', 'neighbour', 'brother', 'mother', 'himself'])])
    P['4:23'] = dict(instruction='Link the Letters · Activity 6. Directions: For each question, a grid letter is presented. Locate the words within the grid and draw a line from square to square to link the letters of the words. Use the definition of words as clues.', cards=[
        fig_card('4:23', 'Use the clue under each box to find its word.')])
    P['4:24'] = dict(instruction='Link the Letters · Activity 6 (continued)', cards=[
        fig_card('4:24', 'Clue: A large stone')])
    P['4:25'] = dict(instruction='Spiral Puzzle · Activity 7. Directions: Use the word inside the box below clues to solve the spiral puzzle in different direction of crossword. Fill in the puzzle by using a line to connect and make a word.', cards=[
        grid_card(GRIDS['4:25'], ['Rock', 'Gold', 'Gift', 'Peas', 'Plants', 'Leaves', 'Banana', 'Leaf', 'Beans', 'Radio'], TAP)])
    P['4:28'] = dict(instruction='Crosswords · Activity 8 (continued). Find the words from the list in the puzzle.', cards=[
        grid_card(GRIDS['4:28'], lead=TAP)])
    P['4:29'] = dict(instruction='Antonym · Activity 9. Directions: Give the antonyms of the following words. Match column A with column B. Write your answer in the blank.', cards=[
        match(list(enumerate(['plant', 'pay', 'kill', 'kick', 'born', 'care', 'dream', 'press', 'march', 'lead', 'hang', 'dry', 'busy'], 1)),
              lettered(['repudiate', 'die', 'neglect', 'loosen', 'halt', 'rise', 'uproot', 'accept', 'start', 'idle', 'moist', 'dislike', 'follow']))])
    P['4:36'] = dict(instruction='Word Puzzle · Activity 14. Directions: 1. Encircle the words found in the puzzle with word clues in the box below. Words may appear horizontally, vertically or diagonally.', cards=[
        grid_card(GRIDS['4:36'], ['south', 'area', 'problem', 'medium', 'huge', 'fraction', 'equal', 'cost', 'answer', 'circle', 'whole', 'amount', 'square'], TAP)])
    # ---- Grade 5 worksheets --------------------------------------------------------------------
    P['5:15'] = dict(instruction='Read and Classify · Activity 5 - Classify as Noun or Verb. Directions: Identify the following words in the box below as noun or verb and write your answer on the proper column.', cards=[
        R(lead='Write each word under Verb or Noun.', words=['touch', 'spoke', 'sold', 'fell', 'tongue', 'porch', 'toe', 'arm', 'chalk', 'furniture', 'heart', 'scooter', 'shirt', 'shadow', 'knee'])])
    P['5:16'] = dict(instruction='Read and Classify · Activity 6 - Classify as Noun or Verb. Direction: Identify the following words in the box below as noun or verb and write your answer on the proper column.', cards=[
        R(lead='Write each word under Verb or Noun.', words=['throat', 'thumb', 'hot iron', 'fill in', 'chain', 'change', 'company', 'coughing', 'grain', 'garage', 'follow', 'guess', 'laid', 'blind', 'Facebook posts'])])
    P['5:29'] = dict(instruction='Developing Understanding Through Paragraphs · Activity 17 – Short Passage. Direction: Supply the missing word to complete the sentence in the paragraph. Choose your answers from the box below.', cards=[
        R('Mother goes to the ______ to prepare for their __________ party. ____ daughter will receive an award in her graduation ______________. '
          'She passes through a __________ in going to the market. She buys the ingredients for the gathering like ________, ______, ______, ________ and _________ cake. '
          'Then, she goes to the grocery store to buy ________ spoons and forks. She wants to surprise her ______ and so, she buys a blouse, a small ____ floral ____ and a ___________ which she almost ______. '
          'In going home, she stops at a _____ store to buy medicine.',
          words=['lettuce', 'potatoes', 'size', 'skirt', 'stocking', 'tomorrow', 'whose', 'golden', 'forgot', 'drug', 'child', 'chocolate', 'bridge', 'thanksgiving', 'forks'])])
    P['5:30'] = dict(instruction='Developing Understanding Through Paragraphs · Activity 18 – Short Passage. Direction: Supply the missing word to complete the sentence in the paragraph. Choose your answers from the box below.', cards=[
        R('Ben wants to ____ his father. He likes to be a ____ and ____ his community. He never gets ______ of studying. Every ____, he buys books about medicine. '
          'His father ______ him so that Ben will be top in his class. He receives award every ________________ because he has good ______. '
          'And so, his father gives him a shirt with a soft ______ and a ______ jacket because he does ______ in his studies. '
          'Ben never stops ____ he achieve his dream to _____ and become like his dad ____ he admire so much.',
          words=['copy', 'cloth', 'class', 'doctor', 'quarter', 'serve', 'trains', 'whom', 'great', 'tired', 'month', 'leather', 'finish', 'grades', 'until'])])
    P['5:32'] = dict(instruction='Directions: Find and encircle the word in the puzzle.', cards=[
        grid_card(GRIDS['5:32'], ['sugar', 'tear', 'umbrella', 'visit', 'zipper', 'stove', 'spoon', 'soldier', 'scissors', 'sandwich', 'salt', 'rubber', 'rose', 'handkerchief', 'noon'], TAP)])
    P['5:34'] = dict(instruction='Directions: Find and encircle the word in the puzzle.', cards=[
        grid_card(GRIDS['5:34'], ['napkin', 'mirror', 'medicine', 'lips', 'lesson', 'juice', 'office', 'grocery', 'hammer', 'gate', 'curtain', 'cupboard', 'crown', 'crayons', 'cocoa'], TAP)])
    P['5:50'] = dict(instruction='Activity 9: Crossword Puzzle. Directions: Complete the crossword puzzle using the clues below.', cards=[
        R('Across: A dark shape made when something blocks light (6 letters).\nDown: The place where you park your car (6 letters).\nDown: The thickest finger (5 letters).\n'
          'Down: To move in the same direction (6 letters).\nAcross: A person in charge of a ship (7 letters)', heading='Clues'),
        fig_card('5:50', 'Write the answers in the crossword.', label='Crossword puzzle')])
    P['5:51'] = dict(instruction='Activity 10: True or False. Directions: Determine if each statement is true or false.', cards=numbered([
        'A garage is a place to keep your car. (True/False)', 'The thumb is the smallest finger. (True/False)', 'A captain is in charge of a ship. (True/False)',
        'A shadow is a bright spot created by light. (True/False)', 'To follow means to go in the opposite direction. (True/False)']))
    P['5:52'] = dict(instruction='Activity 11: Short Answer Questions. Directions: Answer the following questions in one or two sentences.', cards=numbered([
        'What is a garage used for?', 'Describe the function of a captain.', 'What causes a shadow?', 'Explain what it means to follow someone.',
        'Why is the thumb considered the thickest finger?']))
    P['5:53'] = dict(instruction='Activity 12: Create a Sentence. Directions: Use each of the following words in a complete sentence.', cards=numbered(
        ['Garage', 'Thumb', 'Captain', 'Shadow', 'Follow'], lead='Use this word in a complete sentence.'))
    P['5:54'] = dict(instruction='Activity 13: Vocabulary Riddles. Directions: Solve the following riddles based on the vocabulary terms.', cards=numbered([
        'I protect your vehicle from the rain and snow. What am I?', 'I’m the thickest finger on your hand. What am I?',
        'I’m the dark outline you see when the light is behind you. What am I?', 'I lead the crew as we sail across the sea. Who am I?',
        'I’m a term for moving behind someone. What am I?']))
    P['5:55'] = dict(instruction='Activity 14: Sorting Activity. Directions: Sort the following words into two categories: People and Objects.', cards=[
        R(lead='Write each word under People or Objects.', words=['Captain', 'Garage', 'Thumb', 'Shadow', 'Follow'])])
    P['5:56'] = dict(instruction='Activity 15: Word Search. Directions: Find and circle the vocabulary words in the puzzle below.', cards=[
        grid_card(GRIDS['5:56'], ['Garage', 'Thumb', 'Captain', 'Shadow', 'Follow'], 'Words to find — ' + TAP.lower())])
    P['5:57'] = dict(instruction='Activity 16: Vocabulary Riddles. Directions: Solve the following riddles based on the vocabulary terms.', cards=numbered([
        'I am often praised for being appealing and lovely. What am I?', 'I will help you find the answer to math problems. What am I?',
        'I encompass all the air, water, and living things around you. What am I?', 'I can be a long trip or a quick outing. What am I?',
        'I help spark your creativity and push you to achieve more. What am I?']))
    P['5:58'] = dict(instruction='Activity 17: Word Search. Directions: Find and circle the vocabulary words in the puzzle below.', cards=[
        grid_card(GRIDS['5:58'], ['Beautiful', 'Calculate', 'Environment', 'Journey', 'Inspire'], 'Words to find — ' + TAP.lower())])
    P['5:59'] = dict(instruction='Activity 18: Fill in the Blank Definitions. Directions: Complete each sentence with the appropriate vocabulary word.', cards=numbered([
        'Something that is pleasing to the senses can be described as __________.', 'To find an answer through mathematical methods is to ____________.',
        'The world around us, including air, water, and land, is our __________.', 'A trip from one place to another is called a __________.',
        'To motivate someone to do something positive is to ____________.', 'Something that is hard to understand or unclear is __________.',
        'Showing admiration for someone is to give them ____________.', 'To find something new or unknown is to ___________.',
        'A difficult task that requires effort is a __________.', 'Being inventive or original is described as being __________.']))
    bank = ['Mysterious', 'Respect', 'Discover', 'Challenge', 'Creative']
    P['5:60'] = dict(instruction='Activity 19: Fill in the Blanks (2 points each). Directions: Complete each sentence using the appropriate vocabulary word from the box below. Use each word only once.', cards=[
        Q(i, t, words=bank) for i, t in enumerate([
            'The universe is full of __________ phenomena that scientists are still trying to understand.',
            'It is important to show ___________ to others, regardless of their background or beliefs.',
            'When you go on an adventure, you often __________ new places and experiences.',
            'Completing a difficult task can be a(n) __________ that tests your abilities.',
            'Being __________ allows you to think outside the box and come up with new ideas.'], 1)])
    P['5:61'] = dict(instruction='Activity 20: Multiple Choice (1 point each). Directions: Choose the letter of the correct answer.', cards=[
        Q(1, 'Which word describes someone who enjoys taking risks and trying new things?', ['a. Cautious', 'b. Adventurous', 'c. Indifferent', 'd. Passive']),
        Q(2, 'To improve or make something better is to:', ['a. Decrease', 'b. Enhance', 'c. Neglect', 'd. Ignore']),
        Q(3, 'When someone feels upset or annoyed because of inability to achieve something, they feel:', ['a. Cheerful', 'b. Frustrated', 'c. Content', 'd. Relaxed'])])
    P['5:62'] = dict(instruction='Activity 20: Multiple Choice (continued). Directions: Choose the letter of the correct answer.', cards=[
        Q(4, 'Feeling thankful for what you have means you are:', ['a. Discontent', 'b. Grateful', 'c. Unappreciative', 'd. Jealous']),
        Q(5, 'An idea or method that is new and creative is called:', ['a. Conventional', 'b. Innovative', 'c. Traditional', 'd. Standard'])])
    # ---- Grade 6 worksheets --------------------------------------------------------------------
    P['6:10'] = dict(instruction='Act Game · Activity 1. Directions: Pick a square word card inside a box or bag. Read the word. Find the word inside the classroom. Write them on the board (e.g. half ½, 9, 11, etc.) Do it repeatedly.', cards=[
        R(lead='Word cards', words=['shape', 'record', 'south', 'object', 'minute', 'whole', 'unit', 'bigger', 'today', 'night', 'spring', 'how', 'when', 'always',
                                    'live', 'time', 'road', 'draw', 'pair', 'dress', 'counter', 'flow', 'measure'])])
    P['6:11'] = dict(instruction='Match and Color · Activity 2. Directions: Find or search the words inside the box below in the worksheet. The words may appear vertically and horizontally. Color the words with crayons.', cards=[
        grid_card(GRIDS['6:11'], ['subject', 'yellow', 'force', 'pair', 'wrong', 'sand', 'wait', 'general', 'skin', 'party', 'test', 'ahead', 'practice', 'side',
                                  'difficult', 'heavy', 'tail', 'enough', 'different', 'temperature'], TAP)])
    P['6:12'] = dict(instruction='Word Search · Activity 3. Directions: Find and circle the words in the worksheet. The words appear horizontally, vertically or diagonally. The given words are listed below.', cards=[
        grid_card(GRIDS['6:12'], ['does', 'sign', 'guess', 'ride', 'minor', 'blood', 'rich', 'cat', 'led', 'amount', 'note', 'material', 'thousand', 'forward',
                                  'huge', 'region', 'period', 'team', 'corner', 'garden'], TAP)])
    P['6:13'] = dict(instruction='Hidden Letters · Activity 4. Directions: Fill in the missing letters in the given repetitive words. Use the provided words below.', cards=[
        fig_card('6:13', 'Write the missing letters in each word box.', ['race', 'brother', 'dead', 'born', 'cattle', 'various', 'kind', 'addition', 'doesn’t',
                                                                         'weight', 'island', 'opposite', 'sense', 'million'])])
    jumbled = ['ridao', 'veninge', 'kngi', 'cron', 'ghoutb', 'rilimas', 'hodmet', 'verco', 'nurter', 'noitisop', 'cideed', 'reba', 'ngos', 'rabod', 'redaps']
    clues = ['Transistor of magnetic waves', 'Nighttime, sundown', 'Leader/ruler in the palace', 'Plant with kernels and cob', 'It is buying',
             'Means the same or alike', 'Strategy, technique or way', 'Shield, protection', 'Coming back', 'Spot, site', 'Choose, select', 'An animal',
             'With tune and lyrics', 'A panel', 'To extend surface']
    words = ['radio', 'evening', 'king', 'corn', 'bought', 'similar', 'method', 'cover', 'return', 'position', 'decide', 'bear', 'song', 'board', 'spread']
    P['6:14'] = dict(instruction='Jumbled Letters · Activity 5. Directions: Unscramble the letters below. Refer your answer on the word definition and words below. Write your answer in the blank provided.', cards=[
        R(lead='Words', words=words)] + [Q(i, f'{j} → ________', lead=f'Clue: {c}') for i, (j, c) in enumerate(zip(jumbled, clues), 1)])
    P['6:15'] = dict(instruction='Link the Letters · Activity 6. Directions: For each box, a letter grid is presented. Locate the words within the grid and draw a line from square to square to link the letters to form the words. Use the definition of words below as clues.', cards=[
        fig_card('6:15', 'Use the clue under each box to find its word.', ['pass', 'deal', 'beyond', 'love', 'cause', 'meat', 'west', 'action', 'type', 'attention'])])
    P['6:16'] = dict(instruction='Spin Act · Activity 7. Directions: Use a spinner and spin the words. As the spin stops, read the words it points to. Define the words using context clues, pictures, actions and etc.', cards=[
        fig_card('6:16', 'Spin, read the word, then tell what it means.', label='Spin wheel of words')])
    P['6:17'] = dict(instruction='Crossword · Activity 8. Directions: Find the words inside the box in the crossword puzzle. Solve the crossword puzzle. Use color pens or crayons to answer. Answer sheets are provided.', cards=[
        grid_card(GRIDS['6:17'], ['pick', 'basic', 'safe', 'cost', 'act', 'hat', 'major', 'gray', 'wear', 'sold', 'arm', 'learn', 'kitchen', 'scale', 'happen',
                                  'grown', 'believe', 'wonder', 'describe', 'include'], TAP)])
    P['6:18'] = dict(instruction='Opposite Words · Activity 9. Directions: Give the antonyms of the following words. Match column A with column B. Write your answer in the blank.', cards=[
        match(list(enumerate(['drop', 'heart', 'suddenly', 'cells', 'cause', 'developed', 'trained', 'dance', 'farmers', 'distance', 'syllables', 'simplify',
                              'friendship', 'eggs', 'divided', 'center', 'felt', 'heard', 'written', 'square'], 1)),
              lettered(['establish', 'disco', 'chambers', 'planters', 'letter in pair', 'quickly', 'space', 'fall', 'middle', 'soul', 'reason', 'skill',
                        'distributed', 'Make simpler', 'sense', 'offspring', 'printed', 'companionship', 'four-sided', 'overheard']))])
    P['6:23'] = dict(instruction='Word Puzzle · Activity 14. Directions: Read the words in the box. Search for each word in the puzzle and encircle it, if it appears horizontally, vertically and diagonally.', cards=[
        grid_card(GRIDS['6:23'], ['circle', 'whole', 'amount', 'square', 'sign', 'resign', 'when', 'benign', 'today', 'campaign', 'design', 'groan', 'road', 'live',
                                  'thank', 'bigger', 'how', 'always', 'night', 'spring'], TAP)])
    P['6:25'] = dict(instruction='Correctly Spelled · Activity 16. Directions: Write the correct spelling of words as dictated by the teacher.', cards=[
        R(lead='Spelling words', words=['everybody', 'company', 'absence', 'ache', 'accomplish', 'athlete', 'occasion', 'restaurant', 'city', 'middle', 'moment',
                                        'frightened', 'exclaimed', 'several', 'lonely', 'drew', 'since', 'straight'])])
    P['6:26'] = dict(instruction='Roll Word Game · Activity 17. Directions: Look at the words shown in the flashcards, read it. Find and cross it out on your worksheet.', cards=[
        R(lead='Flashcard words', words=['however', 'because', 'practice', 'condition', 'admit', 'solution', 'endurance', 'eligible', 'parallel', 'grateful',
                                         'decided', 'served', 'amazed', 'silent', 'wrecked', 'improved', 'certainly', 'entered', 'realized', 'interrupted'])])
    P['6:29'] = dict(instruction='Paragraph Filling · Activity 20. Directions: Complete the paragraph by filling on the blank. The first and last letters of each word are given. Use/Select the words inside the box.', cards=[
        R('An a______e used of technolog__ is very w__d but c__________y things happened nowadays. It gives us good d_______n in c________l and g_______y of a______s.\n\n'
          'B_____e were a________h in very i________e reasons and in easy way that t________r had no c______s.\n\n'
          'An e_________y learners has lots of n___________y needed that were b__________l and their f________e.\n\n'
          'In g_______l these things needed to i________t and p___________s required r___________y.',
          words=['advance', 'direction', 'chilly', 'weird', 'gallery', 'impossible', 'bridge', 'general', 'abolish', 'trucker', 'apparatus', 'comment',
                 'elementary', 'perhaps', 'necessity', 'relativity', 'commercial', 'favorite', 'beautiful', 'imprint'])])
    for d in P.values():
        for c in d['cards']:
            c.setdefault('lead', ''); c.setdefault('images', []); c.setdefault('choices', [])
    return P


# the first part of a question split over two pages, completed on the earlier page
COMPLETE = {
    ('1:10', '7'): dict(text='(Read Aloud) “My Home” This is my home. We have tables and chairs in our home. We have rooms and beds in our home. '
                             'We have the kitchen and the comfort rooms too. What is the story about?', choices=['a. My Garden', 'b. My Home', 'c. My Kitchen']),
    ('3:55', '43'): dict(choices=['a) trunk', 'b) roots', 'c) fruits']),
    ('4:15', '39'): dict(choices=['A. Feed them', 'B. Watch them', 'C. Kill them', 'D. Care for them']),
    ('1:37', '1'): dict(choices=['a. Friends', 'b. Neighbors', 'c. Uncle, Aunt, Grandfather, Grandmother, Nephew, Niece']),
    ('3:16', '20'): dict(choices=['a. Honest and kind', 'b. Shy and quiet', 'c. Clever and tricky', 'd. Strong and brave']),
    ('4:8', '11'): dict(choices=['a) White', 'b) Black', 'c) Gray']),
    ('4:47', '37'): dict(choices=['A. April', 'B. January', 'C. February', 'D. March']),
}
# cards missing at the end of a page (the question starts there, its choices are on the next page)
APPEND = {
    '1:30': [R('Listen carefully as your teacher reads the story “My Food”. Then, answer the questions below. Circle the letter of the correct answer.', lead='B. Listening Comprehension'),
             Q(1, 'Who buys the food in the story?', ['a. Mother', 'b. Father', 'c. Sibling'])],
}
