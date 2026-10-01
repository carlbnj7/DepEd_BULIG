"""Hand-checked Level 5 cards for pages whose text is printed as images (read from the page, not OCR).
Format per page: instruction, then blocks. 'R: text' = Read card; 'N. question | a) x | b) y' = numbered card."""
import json, os, re

PAGES = {
'2:28': ("Activity 5 Assessment · Part 1: Vocabulary & Listening Comprehension. Direction: Choose the correct picture.", """
1. Which picture shows a “herb”? | a) big tree | b) small plant with leaves | c) dog
2. Which picture shows “cure”? | a) medicine | b) gift | c) house
3. Which picture shows “magical”? | a) sparkles | b) apple | c) car
4. What can the magical herb do? | a) helps sick people | b) makes art | c) makes you run fast
5. Who received the magical herb? | a) the world | b) a cat | c) a king
6. Do you think herbs are helpful? Point to a happy face if yes, or a sad face if no.
R: Instructions for the Teacher: Read the poem/story aloud slowly and clearly. Ask the students to listen carefully because they will answer some questions after.
R: Part 2: Vocabulary · Choose the correct answer.
1. What is a “herb”? | a) A kind of magic wand | b) A plant we can use for medicine or cooking | c) A type of animal
2. What does “cure” mean in the story? | a) To make someone feel better from illness | b) To give someone a gift | c) To make something disappear
3. The story says the herb is “magical.” What does “magical” mean? | a) Ordinary | b) Special and amazing | c) Small
"""),
'2:33': ("Activity 7: Assessment · Part 1: Vocabulary Matching. Directions: Match the animal with the correct picture or word.", """
1. tarsier | owl | toad | tarsier
2. woodpecker | owl | woodpecker | beetle
3. squirrel | wood mouse | squirrel | moth
4. beetle | beetle | stag beetle | toad
R: Part 2: Listening Comprehension Questions · Directions: Listen to the story and answer the questions.
1. Who does the story ask to come first? | a) Owl | b) Squirrel | c) Woodpecker
2. What animal is hiding under the tree? | a) Tarsier | b) Squirrel | c) Beetle
3. Which animal is coming as prey? | a) Woodpecker | b) Owl | c) Toad
4. Which animals did you hear in the story? Circle all that apply. | squirrel | tarsier | woodpecker | owl | moth | toad
R: Part 3: Fill in the Blanks · Directions: Fill in the missing words from the story.
1. Come little ________.
2. Meet baby ________.
3. Hide under the ________.
4. ________ is coming as prey.
"""),
'2:35': ("Activity 8 Assessment · Part 1: Vocabulary Matching. Directions: Match the animal with the correct picture or word.", """
1. butterfly | ladybug | butterfly | bee | grasshopper
2. grasshopper | grasshopper | ladybug | mosquito
3. ladybug | ladybug | housefly | butterfly
4. bee | bee | praying mantis | butterfly
5. mosquito | mosquito | bee | ladybug
R: Part 2: Listening Comprehension Questions · Directions: Listen to the story and answer the questions.
1. What flies in the meadow? | a) Butterfly | b) Grasshopper | c) Ladybug
2. Which animal is trying to get attention? | a) Dragonfly | b) Butterfly | c) Mosquito
3. Which animals did you hear in the story? Circle all that apply. | butterfly | dragonfly | grasshopper | ladybug | praying mantis | housefly | gold pinch | mosquito | bee
R: Part 3: Fill in the Blanks · Directions: Fill in the missing words from the story.
1. The birds in the ________, go fly and fly.
2. Here comes the ________, flying in the meadow.
3. To get the attention of ________ and you.
"""),
'2:42': ("Formative Assessment – Grade 2 · Activity 11: Things Found in the Kitchen · Part I: Vocabulary. Directions: Read the sentence carefully. Choose the correct meaning or word that fits.", """
1. We use a blender to: | a) Wash plates | b) Make juice or mix fruits | c) Cook rice
2. A refrigerator is used to: | a) Keep food cold | b) Fry vegetables | c) Hold water
3. We use a fork to: | a) Eat food | b) Cook food on the stove | c) Drink juice
4. A kettle is used to: | a) Boil water | b) Blend fruits | c) Wash dishes
5. A gas stove is used to: | a) Cook food with heat | b) Store vegetables | c) Eat food
R: Part II: Listening Comprehension · Directions: Listen carefully as your teacher reads the passage below. Then choose the correct answer.\nTeacher reads: “In the kitchen, we must keep everything clean. Wipe spills and drain water. Put fruits in the blender to make juice. Use the rice cooker to cook rice and the stove to cook vegetables. Plates, spoons, and forks are for eating.”
6. What should we do if water spills in the kitchen? | a) Ignore it | b) Wipe it and drain | c) Pour more water
7. What do we put in the blender? | a) Vegetables only | b) Fruits | c) Plates
8. Which kitchen tool is used to cook rice? | a) Blender | b) Rice cooker | c) Kettle
9. What do we use to eat food? | a) Plate, fork, spoon | b) Blender | c) Stove
10. What should we do in the kitchen to stay safe and clean? | a) Keep it clean and organized | b) Leave food everywhere | c) Only cook, no cleaning
"""),
'2:48': ("Formative Assessment – Grade 2 · Lesson 14: Things Found in the Bathroom · Part I: Vocabulary. Directions: Read each sentence. Choose the correct word that fits. Words to use: bath mat, toilet paper, shampoo, soap, comb, brush, bucket, bath cup, soap dish", """
1. We use ________ to wash our hair. | a) shampoo | b) comb | c) bucket
2. We place the soap on a ________. | a) brush | b) soap dish | c) bath mat
3. After taking a bath, we stand on the ________ so we will not slip. | a) bath mat | b) toilet paper | c) soap
4. We use a ________ to comb and fix our hair. | a) brush | b) comb | c) bath cup
5. When we need water for bathing, we can fill a ________. | a) bucket | b) shampoo | c) toilet paper
R: Part II: Listening Comprehension · Directions: Listen carefully as your teacher reads the short passage. Then choose the correct answer.\nTeacher reads: “If you want to shower, don’t forget to bring your towel. Use soap and shampoo to clean your body and hair. The soap is kept in the soap dish. Water is scooped using a bath cup and poured into the bucket. After bathing, you may step on the bath mat so the floor will not get wet. You can brush your hair using a brush or a comb. Toilet paper is also found in the bathroom for keeping yourself clean.”
6. Where do we keep the soap? | a) In the bucket | b) On the soap dish | c) In the bath cup
7. What do we use to clean our hair? | a) shampoo | b) brush | c) toilet paper
8. What helps keep the floor from getting wet? | a) bath cup | b) bath mat | c) comb
9. What can we use to fix our hair after bathing? | a) blanket | b) towel | c) brush or comb
10. What do we use for cleaning ourselves in the bathroom? | a) toilet paper | b) bath mat | c) bucket
"""),
'2:50': ("Formative Assessment – Grade 2 · Lesson 15: Things Found in the Garage · Part I: Vocabulary. Directions: Read each sentence. Choose the correct word that fits. Words to use: car, engine oil, wheel, petrol can, pliers, screwdriver, trolley jack, funnel, hammer", """
1. A ________ is used for fixing or turning screws. | a) wheel | b) screwdriver | c) funnel
2. The ________ helps the car move on the road. | a) wheel | b) hammer | c) pliers
3. A ________ holds fuel that can be used for a car. | a) petrol can | b) trolley jack | c) engine oil
4. We use a ________ to make pouring liquid easier. | a) funnel | b) car | c) hammer
5. A ________ helps lift a car when changing a tire. | a) pliers | b) trolley jack | c) engine oil
R: Part II: Listening Comprehension · Directions: Listen carefully as your teacher reads the short passage. Then choose the correct answer.\nTeacher reads: “In the garage, we can find many tools and things for the car. A car is parked safely inside the garage. Wheels help the car move, while engine oil keeps the engine running smoothly. There is a petrol can for storing fuel. Tools like a hammer, pliers, and a screwdriver are used for fixing small parts. A funnel helps pour oil or petrol without spilling. A trolley jack is used to lift the car when changing a wheel. The garage is a safe place for both the car and the people who use it.”
6. What keeps the engine running smoothly? | a) engine oil | b) petrol can | c) funnel
7. What is used to fix small parts? | a) wheel | b) hammer, pliers, and screwdriver | c) car
8. What helps lift the car? | a) petrol can | b) trolley jack | c) engine oil
9. What is used to store fuel? | a) hammer | b) petrol can | c) funnel
10. Where is the car parked safely? | a) on the road | b) in the garage | c) in the classroom
"""),
'2:53': ("Formative Assessment – Grade 2 · Lesson 16: Landforms · Part I: Vocabulary. Directions: Read each sentence. Choose the correct word that fits. Words to use: mountain, valley, plateau, glacier, hill, desert, basin, shoreline, plain", """
1. A ________ is a high landform with steep sides that is taller than a hill. | a) mountain | b) plain | c) shoreline
2. A ________ is a wide, flat area of land. | a) hill | b) plain | c) glacier
3. A ________ is a low area between mountains or hills. | a) valley | b) plateau | c) desert
4. A ________ is a large area of flat land that is higher than the land around it. | a) shoreline | b) plateau | c) basin
5. A ________ is a very dry place with lots of sand. | a) desert | b) glacier | c) hill
R: Part II: Listening Comprehension · Directions: Listen carefully as your teacher reads the short passage. Then choose the correct answer.\nTeacher reads: “The Earth has many different landforms. A mountain is tall and reaches high into the sky, while a hill is smaller and gently rounded. A valley lies between mountains and hills. A plain is a wide, flat area where many people build houses and farms. A plateau is also flat but stands higher than the land around it. Some places have glaciers, which are large, slow-moving ice. A desert is hot and dry with very little water. The shoreline is the place where land meets the water. A basin is a low area that can hold water. These landforms make the Earth beautiful and exciting to explore.”
6. Which landform is tall and reaches high into the sky? | a) hill | b) mountain | c) basin
7. What landform is a low area between mountains or hills? | a) valley | b) shoreline | c) plateau
8. Which landform is flat and good for farms and houses? | a) glacier | b) plain | c) desert
9. What landform is made of slow-moving ice? | a) basin | b) glacier | c) hill
10. Where does the land meet the water? | a) shoreline | b) desert | c) plateau
"""),
}


def parse(ins, body):
    cards = []
    for line in body.strip().split('\n'):
        line = line.strip()
        if not line:
            continue
        if line.startswith('R:'):
            cards.append(dict(title='Read', text=line[2:].strip().replace('\\n', '\n'), images=[], choices=[]))
            continue
        m = re.match(r'(\d+)\.\s*(.*)', line)
        if not m:                       # continuation of the card above (e.g. a Teacher reads passage)
            cards[-1]['text'] += '\n' + line
            continue
        parts = [p.strip() for p in m.group(2).split('|')]
        cards.append(dict(title=m.group(1), text=parts[0], images=[], choices=parts[1:]))
    return dict(instruction=ins, cards=cards)


IMG = 'assets/images/level5/'


def match(items, choices, pics=None):
    """A two-column matching card. items: Column A words; pics: picture file per item (optional)."""
    labels = [f'{i}. {w}'.strip() for i, w in items]
    images = [dict(src=IMG + f, alt=l, label=l) for l, f in zip(labels, pics)] if pics else []
    return dict(title='Matching', text='Column A\n' + '\n'.join(labels), images=images, choices=choices)


MATCH = {
'2:20': [(0, match([(1, 'Beauty'), (2, 'Bloom'), (3, 'Color'), (4, 'Fragrant'), (5, 'Wonderful')],
                   ['a. Smells good', 'b. Something nice to see', 'c. To open like a flower', 'd. A shade like red, blue, or yellow', 'e. Very nice or amazing']))],
'2:22': [(0, match([(1, ''), (2, ''), (3, ''), (4, '')] + [(5, '')], ['a. carrots', 'b. stringbeans', 'c. tomato', 'd. potato', 'e. cabbage'],
                   ['g2/p022-2.webp', 'g2/p022-3.webp', 'g2/p022-4.webp', 'g2/p022-5.webp', 'g2/p022-1.webp']))],
'2:59': [(7, match([(i, '') for i in range(1, 10)], ['A. Truck', 'B. Van', 'C. Ambulance', 'D. Bicycle', 'E. Train', 'F. Taxi', 'G. Boat', 'H. Ship', 'I. Airplane'],
                   [f'g2/icon59-{i}.webp' for i in range(1, 10)]))],
'2:57': [(0, match([(i, '') for i in range(1, 10)], ['A. Coal', 'B. Silver', 'C. Sand & Gravel', 'D. Limestone', 'E. Aluminium', 'F. Gold', 'G. Salt', 'H. Phosphate', 'I. Copper'],
                   [f'g2/icon57-{i}.webp' for i in range(1, 10)]))],
'2:61': [(0, match([(i, '') for i in range(2, 11)], ['A. Bulkang Mayon', 'B. Banaue Rice Terraces', 'C. Maria Cristina Falls', 'D. Dakak Beach', 'E. Pagsanjan Falls',
                                                      'F. Sohoton Cave', 'G. Hundred Islands', 'H. Coron Reef', 'I. Boracay Island'],
                   [f'g2/icon61-{i}.webp' for i in range(2, 11)]))],
}
PAGES.update({
'2:20': ("Activity 1A: Assessment · A. Vocabulary. Directions: Match the word with its meaning. (Teacher may read aloud the words and meanings.)", """
R: Lesson 1B: Assessment · B. Listening Comprehension. Directions: Listen carefully to the teacher as the story is read aloud. Then answer the questions.
1. What is the beauty of nature in the story? | a. Trees | b. flowers | c. clouds
2. What do flowers do? | a. They sleep | b. They grow and bloom | c. They jump
3. Flowers bloom in different ____. | a. Colors | b. sizes | c. sounds
4. What do flowers give us? | a. A fragrant odor | b. A loud noise | c. A bright light
5. How does the story describe flowers? | a. Ugly | b. wonderful | c. scary
"""),
'2:22': ("Activity 2 A: Assessment · A. Matching Type: In column A is the picture of vegetables. In column B is the name of the vegetables. Match column A and B by connecting it with a line.", """
R: Activity 2B: Assessment · Story for the teacher to read: Vegetables\nVegetables in our garden. Legumes, leafy and grains. They give nutrients to our body and brains, to keep us active and healthy mind.\nDirections: Listen carefully to the story. Then answer the questions.
1. Where can we find the vegetables in the story? | a. in the garden | b. in the store | c. in the forest
2. Which of the following is a kind of vegetable mentioned in the story? | a. Fruits | b. legumes | c. candies
3. What do vegetables give to our body and brain? | a. Toys | b. nutrients | c. noise
4. Why are nutrients important for us? | a. They help us stay active | b. They help us sleep all day. | c. They make us feel hungry.
5. What does the story say vegetables help us have? | a. A healthy mind | b. A messy room | c. A loud voice
"""),
'2:59': ("Grade 2 Formative Assessment · Lesson 19 – Transportation · Part A — Listening Comprehension", """
R: Teacher reads the short story aloud. Story: Transportation\nIn the street you can see. Buses, cars and taxi. In the air there is a plane. On the rails there is a train.\nDirections: Listen carefully to the story and answer the questions.
1. Where can you see buses, cars, and taxis? | a. In the street | b. In the air | c. On the rails
2. What travels in the air? | a. Train | b. Plane | c. Boat
3. What moves on the rails? | a. Car | b. Train | c. Truck
4. Which of these is NOT mentioned in the story? | a. Bicycle | b. Taxi | c. Plane
5. Name one vehicle you heard in the story. | a. Car | b. Tree | c. Flower | d. Sand
R: Part B — Vocabulary with Pictures · Directions: Match the picture to the correct word. Write the letter of the correct answer.
R: Part C — Short Written Task · Directions: Use the word bank to complete each sentence.\nWord Bank: bicycle • train • airplane • truck
1. People ride a ________ to move around short distances.
2. A ________ travels on tracks.
3. An ________ flies in the sky.
"""),
'2:61': ("Part A — Vocabulary with Pictures · Directions: Match the picture to the correct word. Write the letter of the correct answer.", """
R: Part B — Listening Comprehension · Teacher reads the short story aloud. Story: Famous Location\nWhere there is water, life is better. Where there is greenness, air is fresh. Relax and refresh, Keep away from stress.\nDirections: Listen carefully to the story and answer the questions.
1. What makes life better according to the story? | a. Water | b. Cars | c. Mountains
2. What makes the air fresh? | a. Greenness | b. Buildings | c. Roads
3. What should we do to keep away from stress? | a. Relax and refresh | b. Sleep in class | c. Run fast
4. Which of these is a famous location mentioned in the lesson? | a. Banaue Rice Terraces | b. Eiffel Tower | c. Mount Everest
5. Name one famous location you heard from the story. | a. Boracay Island | b. School | c. Street
"""),
'2:57': ("Part A — Vocabulary with Pictures · Directions: Match the picture to the correct word. Write the letter of the correct answer.", """
R: Part B — Listening Comprehension · Teacher reads the short story aloud. Story: Mineral Resources\nEarth is rich of treasure. More than we could ever measure. Coal, copper, gold and silver. Elements on Earth layer upon layer.\nDirections: Listen to the story. Then answer the questions.
1. What is the story talking about? | a. Animals | b. Mineral resources | c. Weather
2. Which of these is NOT mentioned in the story? | a. Silver | b. Coal | c. Plastic
3. Name one mineral you heard from the story. | a. Gold | b. Tree | c. Water
4. What word in the poem means “precious things found on Earth”? | a. Treasure | b. Rock | c. Soil | d. Sand
5. What does the poem say Earth is full of? | a. Treasure | b. Food | c. Water only
R: Part C — Short Written Task · Directions: Use the word bank to complete each sentence.\nWord Bank: gold • coal • silver • copper
1. People use ________ to make jewelry.
2. Many machines use ________ wires.
3. ________ is black and used for fuel.
"""),
'2:31': ("Activity 6 Assessment · Part 1: Vocabulary Recognition. Instruction: Look at the picture and choose the word that matches it.", """
1. Which animal is this? | a) Dog | b) Horse | c) Cat | d) Turkey
2. Which animal is this? | a) Cow | b) Horse | c) Dog | d) Carabao
3. Which animal is this? | a) Turkey | b) Cat | c) Cow | d) Dog
4. What sound does this animal make? | a) Meow! | b) Aww! | c) Quack! | d) Neigh!
5. What sound does this animal make? | a) Meow! | b) Aww! | c) Moo! | d) Gobble!
R: Part 2: Listening Comprehension · Instruction: Listen carefully to the story and answer the questions.
1. Where were the horse, cow, and carabao going? | a) To the river | b) To the barn | c) To the forest | d) To school
2. Who did the animals meet and play with? | a) A dog | b) A turkey | c) A cat | d) A cow
3. Which animal says “aww! aww! aww!”? | a) Cat | b) Dog | c) Horse | d) Carabao
4. Which animal says “meow! meow! meow!”? | a) Dog | b) Cat | c) Horse | d) Turkey
5. What do the animals do at the end of the story? | a) Sleep | b) Eat | c) Play | d) Run away
"""),
'2:62': ("Part C — Short Written Task · Directions: Use the word bank to complete each sentence. Word Bank: Boracay Island • Pagsanjan Falls • Bulkang Mayon • Banaue Rice Terraces", """
1. People visit ________ to see beautiful beaches.
2. ________ is a famous volcano in the Philippines.
3. You can ride a boat at ________.
4. Farmers grow rice at ________.
"""),
})


def lettered(words):
    return [f'{chr(97 + i)}. {w}' for i, w in enumerate(words)]


def pair_card(left, right):
    return match(list(enumerate(left, 1)), lettered(right))


WORDS = {
'4:34': ("Synonyms · Activity 12. Directions: Read the given words in the worksheet. Choose the synonym of the word in the left column with the words in the right column. Connect the words with a line.",
         [pair_card(['belt', 'picture', 'cover', 'pull', 'money', 'paint', 'movie', 'try', 'supper', 'cause', 'when', 'crop'],
                    ['shield', 'cash', 'image', 'strap', 'source', 'attempt', 'dinner', 'time', 'tug', 'show', 'tint', 'yield'])]),
'4:35': ("Antonyms · Activity 13. Directions: Read the words below. Connect the words in Set A to its antonym in Set B by drawing a line.",
         [pair_card(['always', 'sometimes', 'morning', 'afternoon', 'everyday', 'finally', 'tomorrow', 'start', 'yesterday', 'later', 'morning', 'during'],
                    ['through', 'constantly', 'the past', 'sunrise', 'by-and-by', 'daily', 'lastly', 'occasionally', 'after lunch', 'future', 'advanced', 'begin'])]),
'6:21': ("Synonyms · Activity 12. Directions: Read the words given in the worksheet. Choose the synonym/s of the words on the left with the words on the right. Connect the words with a line.",
         [pair_card(['third', 'months', 'represents', 'clothes', 'flowers', 'shall', 'drive', 'admire', 'light', 'sigh', 'system', 'fright', 'dough', 'picture', 'paint', 'supper', 'cause', 'whether'],
                    ['symbolize', 'dresses', 'flora', 'will', 'effort', 'exhale', 'tertiary', 'educator', 'weeks', 'like', 'structure', 'if', 'dainty', 'dye', 'scare', 'bread', 'root', 'dinner', 'image'])]),
'4:40': ("Pair a Word · Activity 18. Directions: Read the words and pair it with words in the card. Do it again and again until all words are paired.",
         [dict(title='Read', text='Words in the card:\npath • cloud • air • cool • FRESH • early • airplane • breakfast • chimney • building • hall • lake • creek', images=[], choices=[]),
          dict(title='1', text='Write the pairs of words that go together (for example: path – creek).', images=[], choices=[])]),
'6:27': ("Word Match · Activity 18. Directions: Read the words and pair it with words in the card. Do it again and again until all words are paired.",
         [dict(title='Read', text='Words in the card:\nscanty • business • invisible • grim • discussed • fright • behaved • chimney • changeable • building • acquainted • government • embarrass • opinion • splendid • early • escaped • develop • breakfast • considered', images=[], choices=[]),
          dict(title='1', text='Write the pairs of words that go together.', images=[], choices=[])]),
'6:22': ("Replace a Word · Activity 13. Directions: Match each word with its synonym and write the answer in the blank provided for.",
         [dict(title='Read', text='Words in flashcards:\ncyclone • rhythm • hydrogen • cyst • admirable • movable • later • thought • bought • although • lovable • fight • thigh • hydrant • crystal • always • yesterday • during • instrument • paragraph\n\nWords in activity sheet:\nbeat • typhoon • node • good • adorable • believe • all • flexible • even if • gem • subscribe • combat • ahead • constantly • device • gas • passage • the past • through • discharge pipe • upper leg', images=[], choices=[]),
          dict(title='1', text='Write each word with its synonym (Words – Synonyms).', images=[], choices=[])]),
}
PAGES.update({
'2:24': ("Activity 3 A: Assessment · A. Vocabulary Test. Directions: Match the word with its meaning. (Teacher may read aloud.)", """
R: B. Listening Comprehension · Directions: Listen carefully as the story is read aloud. Then answer the questions.
1. What do fruits make us? | a. Weak | b. Strong | c. Sleepy
2. What do fruits help keep us? | a. Busy | b. Healthy | c. Sad
3. How do fruits taste? | a. Sour and sweet | b. Bitter | c. Salty
4. Who likes to eat fruits? | a. Everyone | b. Only children | c. No one
5. What is the poem mostly talking about? | a. Games | b. Fruits | c. Weather
"""),
'6:8': ("Post Test (continued)", """
R: Part I — Word Pairing (19)
R: Part J — Matching Meaning (20)
"""),
})
# Grade 6 pre-test items 19–20: two small matching sets
MATCH['6:8'] = [(1, dict(match([(1, 'hot'), (2, 'cold')], ['a. milk', 'b. tea']), lead='19. Match the words that go together.')),
                (3, dict(match([(1, 'run'), (2, 'blue')], ['a. to move fast', 'b. a color']), lead='20. Match the word with its meaning.'))]
MATCH['2:24'] = [(0, match([(1, 'Strong'), (2, 'Healthy'), (3, 'Sour'), (4, 'Sweet'), (5, 'Eat')],
                           ['a. Tastes like candy', 'b. To take food into the mouth', 'c. Has a sharp taste like lemon', 'd. Having power or energy', 'e. Not sick; well']))]

PAGES.update({
'5:41': ("Activity 1: Multiple Choice Vocabulary Review (Items 1–10). Directions: Choose the letter of the word that best completes the sentence or matches the definition.", """
1. A sweet substance used to flavor food and drinks: | a. Salt | b. Sugar | c. Tear | d. Blood
2. An object you carry to protect yourself from rain: | a. Mirror | b. Handkerchief | c. Umbrella | d. Drawer
3. The opposite of 'to start' or 'to begin': | a. Copy | b. Serve | c. Finish | d. Visit
4. A short, pleated garment worn by a girl or woman: | a. Stocking | b. Skirt | c. Cloth | d. Zipper
5. A person whose profession is to treat the sick: | a. Soldier | b. Butcher | c. Doctor | d. Pilot
6. An eating utensil with prongs used to pick up food: | a. Spoon | b. Fork | c. Scissors | d. Stove
7. A drop of salty liquid that comes from the eye, often when crying: | a. Juice | b. Cocoa | c. Tear | d. Drug
8. A type of public outdoor area where goods are bought and sold: | a. Church | b. Bank | c. Market | d. Bridge
"""),
'5:42': ("Activity 1: Multiple Choice Vocabulary Review (continued)", """
9. A train is a type of transport that travels on tracks. The name for the vehicle itself is: | a. Skirt | b. Train | c. Grades | d. Month
10. A fabric material made from the skin of an animal: | a. Rubber | b. Cloth | c. Leather | d. Cotton
"""),
})
WORDS['5:21'] = ("Matching Meanings · Activity 11 – Match to Complete the Sentence. Direction: In column A are the phrases. In column B are the words. Match column A with column B by drawing a line.",
    [match(list(enumerate(['I worship and pray in the ______.', 'You are hurt and you feel the ______.', 'The ________ assists the doctor.', 'When you study in a dark room, you use ______.',
                           'When I sleep, I love to hug my big ______.', 'Mother uses a colorful floor covering called ___.', 'Use ______ in measuring the length of your pen.',
                           'A ______ and arrow can be used for hunting.', 'Sweet potato is an example of a ______ crop.', 'In the garden, you can see some ______.',
                           'My favorite color is ______.', "Don't ____, tell the truth.", 'A ______ is produced by silkworms.', 'Get my jewelry in the ______.', '________ is the number after ten.'], 1)),
           ['a. eleven', 'b. peach', 'c. root', 'd. church', 'e. pain', 'f. lamp', 'g. rug', 'h. silk', 'i. drawer', 'j. butterflies', 'k. lie', 'l. ruler', 'm. pillow', 'n. nurse', 'o. bow'])])
WORDS['5:22'] = ("Matching Meanings · Activity 12 – Match to Complete the Sentence. Direction: In column A are the phrases. In column B are the words. Match column A with column B by drawing a line.",
    [match(list(enumerate(['Save your money and deposit it in your _____.', 'Every _______ I take a nap.', 'I _______ have my vitamins.', 'I ran five kilometres, and now I am ______.',
                           'Give me one _______ pesos to buy a cake.', '____ is the number after nineteen.', 'Please get water from the ______.', 'You ____ to try it sometimes.',
                           'A ______ is a silvery white metal.', 'Kindly take _____ of the important events.', 'He used a knife _____ of chopsticks.', 'My ______ Ana is the only relative I have.',
                           'Let us climb the ______ to have an adventure.', 'Maria goes to the _______ to buy fruits.', '____ are glossy red edible fruit.'], 1)),
           ['a. market', 'b. ought', 'c. cousin', 'd. tub', 'e. thirsty', 'f. already', 'g. note', 'h. afternoon', 'i. tomatoes', 'j. thousand', 'k. nickel', 'l. twenty', 'm. instead', 'n. mountain', 'o. bank'])])

def _g1_pre_post(pics, items, choices):
    return match(list(enumerate(items, 1)), choices, None) | {'images': [dict(src=IMG + f, alt=f'{i}. {w}', label=f'{i}. {w}') for i, w, f in pics]}


G1_PRE = ['Pencil', 'Bag', 'Carrot', 'Banana cue', 'Librarian', 'Swing', 'Trash can', 'Nurse', 'Building', 'Chopping board']
G1_PRE_B = ['a. Used to carrying things.', 'b. Cooked bananas on a stick', 'c. Used for writing.', 'd. A person who works in a library', 'e. Its color is orange',
            'f. A container for waste', 'g. Takes care of sick or injured people in a hospital or clinic', 'h. A seat hanging from ropes or chains that moves back and forth',
            'i. A hard board used for cutting goods', 'j. A structure with a roof and walls, such as a house or School.']
G1_POST = ['Librarian', 'Swing', 'Trash can', 'Nurse', 'Building', 'Chopping board', 'Pencil', 'Bag', 'Carrot', 'Banana cue']
G1_POST_B = ['a. Its color is orange', 'b. A container for waste', 'c. Takes care of sick or injured people in a hospital or clinic', 'd. A seat hanging from ropes or chains that moves back and forth',
             'e. A hard board used for cutting goods', 'f. A structure with a roof and walls, such as a house or School.', 'g. Used to carrying things.', 'h. Cooked bananas on a stick',
             'i. Used for writing.', 'j. A person who works in a library']
FULL_MATCH = {'1:12': ('PRETEST (Activity 1-10) · A. Vocabulary Development. Instructions: Draw a line to match the words in Column A with their meanings/uses/color in Column B.', G1_PRE, G1_PRE_B),
              '1:100': ('POST TEST (Activity 1-15) · A. Vocabulary Development. Instructions: Draw a line to match the words in Column A with their meanings/uses/color in Column B.', G1_POST, G1_POST_B)}


def all_pages():
    out = {k: parse(*v) for k, v in PAGES.items()}
    for k, (ins, cards) in WORDS.items():
        out[k] = dict(instruction=ins, cards=cards)
    for i, c in enumerate(out['2:31']['cards'][:5], 1):     # each question shows its own animal picture
        c['images'] = [dict(src=IMG + f'g2/icon31-{i}.webp', alt='Animal picture', label='')]
    for k, adds in MATCH.items():
        for pos, card in adds:
            out[k]['cards'].insert(pos, card)
    return out
