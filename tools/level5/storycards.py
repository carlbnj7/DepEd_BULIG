"""Level 5 story and vocabulary pages (Grade 1 and 2): the poem/story as text and every picture cut out
on its own with the word printed under it — the page lettering is never kept inside a picture, so nothing
shows twice. Texts and captions are typed from the module pages; picture boxes are found from the
position of each caption on the page (tools/level5/segment.py).
Usage: python3 tools/level5/storycards.py  (writes tools/level5/storycards.json and the pictures)
"""
import json, os, sys
import pymupdf
from PIL import Image

sys.path.insert(0, os.path.dirname(__file__))
from segment import ocr_words, find_text, ink_box, _sim

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
PUB = os.path.join(ROOT, 'public', 'assets', 'images', 'level5')
DPI = 200

# key: (title, story lines, captions in reading order — None = one picture with its own labels)
STORIES = {
    '1:15': ('Me and Myself', ['I am happy', 'I am glad', 'I truly feel', 'That I am loved'], None),
    '1:16': ('My Body', ['This is my body.', 'God give this to me!', 'I can name the parts of my body.'], None),
    '1:19': ('My Toys', ['These are my toys.', 'My mother and father give it to me!', 'I can name my favorite toys.'],
             ['car', 'doll', 'kite', 'Teddy Bear', 'train', 'boat', 'yoyo', 'ball']),
    '1:23': ('My Pets', ['These are my pets.', 'I will take care of my pets.', 'I will give them food.', 'I love my pets.'],
             ['dog', 'cat', 'rabbit', 'fish', 'bird', 'turtle']),
    '1:26': ('My Things', ['These are my things.', 'I will take care of my things.', 'I will keep it clean.'],
             ['book', 'eraser', 'sharpener', 'pencil', 'bag', 'ruler']),
    '1:29': ('My Food', ['These are the foods I eat.', 'My father buys them for me.', 'My mother cook these for me.'],
             ['rice', 'banana', 'fish', 'soup', 'egg', 'meat', 'chicken', 'corn', 'mango', 'cake', 'bread', 'vegetables']),
    '1:32': ('Me and My Family', ['We are happy', 'We are glad', 'We truly feel good', 'We are loved'], None),
    '1:33': ('My Family', ['This is my family.', 'My mother and my father.', 'My brother and my sister.', 'And our baby boy.'], None),
    '1:36': ('My Relatives', ['These are my relatives.', 'My uncle and my aunts.', 'My grandfather and grandmother.', 'My nephew and my niece.'], None),
    '1:39': ('My Home', ['This is my home.', 'We have tables and chairs in our home.', 'We have rooms and beds in our home.',
                         'We have the kitchen and the comfort rooms too.'],
             ['comfort room', 'kitchen', 'bed', 'bedroom', 'table', 'chair']),
    '1:42': ('Our Tools', ['This is our tools.', 'We have shovel and rake.', 'We have to take care of our tools.', 'Tools are very important for us.'], None),
    '1:45': ('Our Kitchen', ['This is our kitchen.', 'We cook our food in our kitchen.', 'We have plates, forks and spoons.', 'We have pans, bowls, and cups too.'], None),
    '1:49': ('Me and My School', ['In my school.', 'I can learn.', 'To read and write.', 'I can learn.', 'To dance and sing.'], None),
    '1:53': ('In The Classroom', ['This is our classroom.', 'I can name the things in our classroom.'], None),
    '1:57': ('In The Garden', ['This is our school garden.', 'We have vegetables in our garden.', 'I plant vegetables in our garden.'],
             ['squash', 'tomato', 'pechay', 'eggplant', 'carrots', 'bitter gourd', 'pepper', 'cabbage']),
    '1:61': ('In The Canteen', ['This is our school canteen.', 'I buy food in our school canteen.'],
             ['sales lady', 'ice drop', 'banana Cue', 'sandwich', 'biscuits', 'juice', 'sweet potato']),
    '1:65': ('In The Library', ['This is our school library.', 'I read books in the library.'],
             ['dictionary', 'magazine rock', 'books', 'librarian', 'book shelves', 'newspaper', 'magazine']),
    '1:69': ('Keeping Our School Clean', ['We take care of our school.', 'We keep our school clean.'],
             ['coconut husk', 'brooms', 'cleaning mop', 'trash can', 'janitor', 'rug', 'brush']),
    '1:73': ('Me and My Community', ['I stay in a place.', 'Safe and with peace.', 'Surrounded with love.', 'A gift from above.'], None),
    '1:74': ('The Community Helpers', ['In our community there are many helpers.', 'We love our community helpers.'],
             ['teacher', 'policeman', 'security guard', 'carpenter', 'storekeeper', 'farmer', 'fisherman', 'doctor',
              'laborer', 'driver', 'fireman', 'nurse']),
    '1:78': ('In The Plaza', ['This is our plaza.', 'We play in our plaza.', 'Our plaza is very clean and beautiful.'],
             ['seesaw', 'monkey bar', 'swing', 'slide', 'statue', 'flowers', 'tree']),
    '1:82': ('In The Market', ['This is our market.', 'We can buy our food in the market.'],
             ['vegetables', 'fishes', 'fruits', 'butchers knife', 'weighing scale', 'chopping board', 'dried fish', 'grains',
              'butcher', 'meat', 'root crops']),
    '1:86': ('In The Farm', ['This is our farm.', 'We grow plants on our farm.', 'We have animals on our farm too.'],
             ['carabao', 'cow', 'horse', 'bird', 'goat', 'pig', 'chicken', 'duck', 'coconut', 'banana', 'cassava', 'guava',
              'pineapple', 'taro', 'bottle guard', 'cashew']),
    '1:90': ('Buildings Around Me', ['There are many building in our place.', 'Each building is very useful for us.', 'We keep our building safe and clean.'],
             ['house', 'church', 'market', 'barangay hall', 'bakery', 'restaurant', 'hospital', 'school', 'bank', 'pharmacy', 'store', 'fire station']),
    '2:18': ('Plants around Me', ['This is my garden.', 'I’ll plant flowers, vegetables, and grains.', 'Here are the seeds of trees and herbs.',
                                 'The sun will shine; the rain will fall.', 'The seeds will sprout, up tall.'], None),
    '2:19': ('Activity 1- Flowers', ['Flowers', 'Flowers are the beauty of nature.', 'They grow and bloom in different colors.',
                                     'They give us a fragrant odor.', 'Flowers are wonderful.'],
             ['Orchids', 'Dahlia', 'Rose', 'Sunflower', 'Sampaguita', 'Tulips', 'Aster', 'Chrysanthemum', 'Yellow Plumeria']),
    '2:21': ('Activity 2- Vegetables', ['Vegetables in our garden.', 'Legumes, leafy vegetables and grains', 'They give nutrients to our body and brains,',
                                        'To keep us active and healthy mind.'],
             ['lettuce', 'broccoli', 'cauliflower', 'potatoes', 'tomato', 'string bean']),
    '2:23': ('Activity 3- Fruits', ['Fruits', 'Fruit makes us very strong.', 'And keep us healthy for long.', 'They taste sour and sweet.', 'That everyone likes to eat.'],
             ['tiesa', 'cashew', 'guyabano', 'atis', 'star apple', 'santol', 'mangosteen', 'macopa', 'siniguelas']),
    '2:25': ('Activity 4- Trees', ['Trees are everywhere.', 'They give us clean and fresh air.', 'Little birds live up there.', 'Trees are their shelter.'],
             ['narra', 'acacia', 'golden tree', 'kamagong', 'mabolo', 'Pine tree', 'falcata', 'gemilina', 'balete']),
    '2:27': ('Activity 5- Herbs', ['Herbs', 'Magical herbs,', 'Given to this world.', 'Can cure so much illness,', 'You’d never believe it would.'],
             ['lemongrass', 'dill', 'peppermint', 'oregano', 'eucalyptus', 'aloe vera', 'Turmeric', 'Red basil', 'sage']),
    '2:29': ('Animals that I Know', ['Animals on land.', 'Help farmers in the farm.', 'Creations in the sea, woodland and in the meadow', 'Made by God for me and you.'], None),
    '2:30': ('Activity 6- Animals in the Land', ['The horse, cow and carabao.', 'Go walk and walk going to the barn.', 'The dog says aww! aww! aww!',
                                                 'And the cat says meow! meow! meow!', 'Until they meet little turkey', 'and together, they play.'],
             ['cow', 'dog', 'Cat', 'carabao', 'horse', 'goat', 'pig', 'turkey', 'Chicken']),
    '2:32': ('Activity 7- Animals in the Woodland', ['Come little squirrel.', 'Meet baby tarsier.', 'Hide under the tree.', 'Woodpecker is coming to prey.'],
             ['tarsier', 'owl', 'toad', 'wood mouse', 'squirrel', 'moth', 'beetle', 'woodpecker', 'Stag beetle']),
    '2:34': ('Activity 8- Animals in the Meadow', ['Animals in the Meadow', 'The birds in the sky go fly and fly.', 'Here comes the butterfly, flying in the meadow.',
                                                   'To get the attention of me and you.'],
             ['butterfly', 'Dragon fly', 'grasshopper', 'praying mantis', 'ladybug', 'housefly', 'gold pinch', 'mosquito', 'bee']),
    '2:36': ('Activity 9- Animals in the Forest', ['Animals in the Forest', 'Look at the monkey.', 'Teasing you and me.', 'Look at the deer and the kangaroo.', 'They are hiding on you.'],
             ['deer', 'wild boar', 'monkey', 'snake', 'kangaroo', 'bat', 'eagle', 'lion', 'fox']),
    '2:38': ('Activity 10- Animals in the Sea', ['Animals in the Sea', 'The fish in the sea.', 'Go swimming and swimming.', 'Under the deep blue sea.', 'Dolphins and whales are friends of me.'],
             ['shark', 'dolphin', 'whale', 'lobster', 'squid', 'crab', 'starfish', 'shrimps', 'shells']),
    '2:40': ('Me and Our House', ['Come and go with me to our house and see.', 'Here is our kitchen where mama cooks for me.', 'Here is our living room where I watch television.',
                                  'Here is our bathroom inside my room.', 'Here is the garage where my daddy fixes the car for our family.'], None),
    '2:41': ('Activity 11- Things Found in the Kitchen', ['Things Found in the Kitchen', 'Keep our kitchen clean.', 'Wipe the spill water and drain.', 'Put fruit on your blender.', 'Prepare juice and share.'],
             ['wash basin', 'refrigerator', 'kettle', 'gas stove', 'rice cooker', 'blender', 'plate', 'fork', 'Spoon']),
    '2:43': ('Activity 12- Things Found in the Living Room', ['Things Found in the Living Room', 'Set beside me.', 'Let’s watch TV.', 'Sip the morning coffee.', 'Served to you for free.'],
             ['flower vase', 'frames', 'bookshelf', 'sofa', 'television', 'curtains', 'carpet', 'electric fan', 'Clock']),
    '2:45': ('Activity 13- Things Found in the Bedroom', ['Things Found in the Bedroom', 'Come inside my room.', 'Keep the light turning on.', 'Mama is coming home.', 'I will never be alone.'],
             ['bed', 'bed sheet', 'blanket', 'pillow', 'mosquito net', 'wardrobe', 'lampshade', 'mirror', 'alarm clock']),
    '2:47': ('Lesson 14: Things Found in the Bath Room', ['If you want to shower', 'Don’t forget to bring towel.', 'Use soap and shampoo', 'Brush and comb are set for you.'],
             ['bath mat', 'toilet paper', 'shampoo', 'soap dish', 'soap', 'bucket', 'comb', 'bath cup', 'brush']),
    '2:49': ('Lesson 15- Things Found in the Garage', ['Things Found in the Garage', 'Do you really want to shop?', 'On the garage you can stop.', 'Park there safely.', 'To keep you and your car safety.'],
             ['car', 'engine oil', 'wheel', 'petrol can', 'pliers', 'screw driver', 'trolley jack', 'funnel', 'hammer']),
    '2:51': ('Me and My Surroundings', ['God created heaven and Earth.', 'Beautiful surrounding with fresh air to breath.', 'Keep our environment clean and green.', 'Make our place safe to live in.'], None),
    '2:52': ('Lesson 16- Landforms', ['Landforms', 'Beyond your imagination.', 'Land has different forms.', 'Go outside and explore.', 'Experience the beauty of nature.'],
             ['mountain', 'valley', 'plateau', 'glacier', 'hill', 'desert', 'basin', 'shoreline', 'plain']),
    '2:54': ('Lesson 17- Body of Water', ['Body of Water', 'The spring, falls, pond, lake and river.', 'Enhancing the beauty on Earth forever.', 'Ocean and sea are nice to see.', 'Keep them clean and garbage free.'],
             ['ocean', 'sea', 'river', 'stream', 'spring', 'falls', 'lake', 'pond', 'ditch']),
    '2:56': ('Lesson 18- Mineral Resources', ['Mineral Resources', 'Earth is rich in treasure.', 'More than we could ever measure.', 'Coal, copper, gold and silver.', 'Elements on Earth layer upon layer.'],
             ['gold', 'silver', 'copper', 'coal', 'salt', 'limestone', 'phosphate', 'aluminum', 'sand and gravel']),
    '2:58': ('Lesson 19- Transportation', ['In the street you can see.', 'Buses, cars and taxi.', 'In the air there is a plane.', 'On the rails there is a train.'],
             ['truck', 'van', 'ambulance', 'bicycle', 'train', 'taxi', 'boat', 'ship', 'airplane']),
    '2:60': ('Lesson 20- Famous Location', ['Famous Location', 'Where there is water, life is better.', 'Where there is greenness, air is fresh.', 'Relax and refresh, Keep away from stress.'],
             ['Banaue Rice Terraces', 'Bulkang Mayon', 'Boracay Island', 'Coron Reef', 'Maria Cristina Falls', 'Dakak Beach', 'Pagsangjan Falls', 'Sohoton Cave', 'Hundred Islands']),
}
# words printed on the page without a picture above them
EXTRA = {'2:21': 'carrots · radish · cabbage'}
# Grade 1 pages are one flat picture: each picture's box (points on the PDF page, read from the page
# with a coordinate overlay), trimmed to the picture inside. Grid pages give row and column bands.
def _grid(caps, rows, cols):
    out, k = {}, 0
    for r, (y0, y1) in enumerate(rows):
        cs = cols[r] if isinstance(cols[0][0], (list, tuple)) else cols
        for x0, x1 in cs:
            if k < len(caps):
                out[caps[k]] = (x0, y0, x1, y1); k += 1
    return out


MANUAL = {
    '1:19': _grid(['car', 'doll', 'kite', 'Teddy Bear', 'train', 'boat', 'yoyo', 'ball'], [(305, 416), (495, 600)],
                  [(100, 201), (203, 303), (305, 405), (408, 505)]),
    '1:23': _grid(['dog', 'cat', 'rabbit', 'fish', 'bird', 'turtle'], [(232, 350), (365, 494), (515, 628)], [(120, 292), (295, 450)]),
    '1:26': _grid(['book', 'eraser', 'sharpener', 'pencil', 'bag', 'ruler'], [(205, 292), (340, 440), (490, 622)], [(110, 295), (300, 500)]),
    '1:29': _grid(['rice', 'banana', 'fish', 'soup', 'egg', 'meat', 'chicken', 'corn', 'mango', 'cake', 'bread', 'vegetables'],
                  [(175, 248), (300, 372), (420, 505)], [(85, 196), (198, 298), (300, 427), (430, 510)]),
    '1:39': {'comfort room': (74, 266, 229, 407), 'kitchen': (296, 270, 508, 400), 'bed': (70, 460, 244, 553),
             'bedroom': (302, 428, 506, 560), 'table': (84, 595, 288, 709), 'chair': (347, 604, 413, 709)},
    '1:57': _grid(['squash', 'tomato', 'pechay', 'eggplant', 'carrots', 'bitter gourd', 'pepper', 'cabbage'], [(315, 440), (530, 643)],
                  [(60, 170), (172, 278), (280, 402), (405, 515)]),
    '1:61': {'sales lady': (148, 187, 415, 343), 'ice drop': (92, 387, 182, 471), 'banana Cue': (205, 378, 348, 468),
             'sandwich': (371, 375, 488, 462), 'biscuits': (95, 551, 244, 614), 'juice': (274, 521, 336, 605), 'sweet potato': (369, 540, 497, 605)},
    '1:65': {'dictionary': (104, 274, 226, 385), 'magazine rock': (271, 268, 328, 382), 'books': (407, 268, 497, 373),
             'librarian': (125, 434, 223, 703), 'book shelves': (238, 426, 396, 715), 'newspaper': (408, 423, 506, 519), 'magazine': (421, 567, 497, 703)},
    '1:69': {'coconut husk': (286, 258, 357, 316), 'brooms': (371, 220, 479, 325), 'cleaning mop': (282, 366, 357, 486),
             'trash can': (378, 365, 458, 483), 'janitor': (68, 262, 271, 611), 'rug': (272, 540, 364, 599), 'brush': (390, 546, 484, 588)},
    '1:74': _grid(['teacher', 'policeman', 'security guard', 'carpenter', 'storekeeper', 'farmer', 'fisherman', 'doctor',
                   'laborer', 'driver', 'fireman', 'nurse'], [(170, 305), (320, 451), (468, 611)],
                  [[(70, 166), (166, 276), (278, 384), (386, 490)], [(70, 177), (180, 276), (278, 384), (386, 490)], [(70, 186), (186, 279), (280, 384), (386, 490)]]),
    '1:78': {'seesaw': (246, 292, 351, 394), 'monkey bar': (353, 277, 473, 397), 'swing': (250, 445, 353, 537), 'slide': (369, 440, 467, 537),
             'statue': (71, 270, 247, 668), 'flowers': (246, 592, 377, 668), 'tree': (381, 565, 476, 659)},
    '1:82': {'vegetables': (91, 265, 226, 395), 'fishes': (229, 265, 359, 395), 'fruits': (357, 256, 496, 403), 'butchers knife': (79, 423, 157, 495),
             'weighing scale': (158, 421, 226, 489), 'chopping board': (116, 506, 217, 563), 'dried fish': (226, 427, 358, 559),
             'grains': (360, 427, 494, 556), 'butcher': (92, 586, 217, 724), 'meat': (229, 582, 359, 721), 'root crops': (358, 582, 494, 717)},
    '1:86': _grid(['carabao', 'cow', 'horse', 'bird', 'goat', 'pig', 'chicken', 'duck', 'coconut', 'banana', 'cassava', 'guava',
                   'pineapple', 'taro', 'bottle guard', 'cashew'], [(240, 349), (395, 482), (525, 606), (650, 738)],
                  [(70, 190), (192, 305), (307, 406), (406, 490)]),
    '1:90': _grid(['house', 'church', 'market', 'barangay hall', 'bakery', 'restaurant', 'hospital', 'school', 'bank', 'pharmacy', 'store', 'fire station'],
                  [(272, 349), (375, 455), (475, 556), (583, 672)], [(100, 220), (225, 362), (366, 485)]),
}
# words printed inside a picture box that are not part of the picture (points)
ERASE = {'1:39': [(145, 668, 196, 691)], '1:82': [(135, 488, 223, 505)]}
# single-picture pages printed as one flat picture: where the picture is (below the poem)
ONE = {'1:15': (40, 320, 560, 700), '1:16': (40, 280, 560, 650), '1:53': (40, 200, 560, 690)}


def footer_top(page):
    top = page.rect.height
    for info in page.get_image_info():
        x0, y0, x1, y1 = info['bbox']
        if y0 > page.rect.height * 0.8 and (x1 - x0) > page.rect.width * 0.6:
            top = min(top, y0)
    return top


def page_words(page, im):
    s = DPI / 72
    ws = [(w[4], [w[0] * s, w[1] * s, w[2] * s, w[3] * s]) for w in page.get_text('words')]
    ocr = [w for w in ocr_words(im) if not any(_overlaps(w[1], v[1]) for v in ws)]
    return ws + [(t, b) for t, b in ocr]


def _overlaps(a, b):
    return a[0] < b[2] and b[0] < a[2] and a[1] < b[3] and b[1] < a[3]


def split_part(im, part, n):
    """Part `part` of `n` photos placed side by side in one picture (cut at the strongest edge)."""
    import numpy as np
    a = np.asarray(im).astype(int)
    diff = np.abs(np.diff(a, axis=1)).mean(axis=(0, 2))
    cuts = [0]
    for k in range(1, n):
        c = int(im.width * k / n); lo, hi = c - im.width // (4 * n), c + im.width // (4 * n)
        cuts.append(lo + int(np.argmax(diff[lo:hi])) + 1)
    cuts.append(im.width)
    return im.crop((cuts[part], 0, cuts[part + 1], im.height))


def build_one(key, docs, report):
    g, pn = map(int, key.split(':'))
    title, story, caps = STORIES[key]
    doc = docs.setdefault(g, pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf')))
    page = doc[pn - 1]
    pix = page.get_pixmap(dpi=DPI)
    im = Image.frombytes('RGB', (pix.width, pix.height), pix.samples)
    W, H = im.size
    s = DPI / 72
    top, bottom = int(H * 0.055), int(footer_top(page) * s) - 4
    words = [w for w in page_words(page, im) if top <= w[1][1] and w[1][3] <= bottom]
    used = set()
    tb = find_text(words, title.split('- ')[-1].split(': ')[-1], used)
    text_boxes = [tb] if tb else []
    for line in story:
        b = find_text(words, line, used)
        if b:
            text_boxes.append(b)
        else:
            report.append(f'{key}: story line not found: {line}')
    story_bottom = max([b[3] for b in text_boxes] + [top])
    # other lettering on the page (never kept inside a cut picture of a grid page)
    pics = []
    out_dir = os.path.join(PUB, f'g{g}')
    os.makedirs(out_dir, exist_ok=True)
    if caps is None and g == 2:     # one big embedded picture
        from layout import raw_image
        infos = [(i['xref'], i['bbox']) for i in page.get_image_info(xrefs=True) if i['bbox'][1] * s > story_bottom - 10 and i['bbox'][3] * s < bottom + 10]
        x, b = max(infos, key=lambda t: (t[1][2] - t[1][0]) * (t[1][3] - t[1][1]))
        pics.append((('raw', x, b), ''))
        erase = []
    elif caps is None:
        region = [v * s for v in ONE[key]] if key in ONE else [0, story_bottom + 4, W, bottom]
        box = ink_box(im, region, erase=text_boxes, pad=12)
        if not box:
            report.append(f'{key}: no picture found')
        else:
            pics.append((box, ''))
        erase = text_boxes
    elif g == 2:     # Grade 2: every photo is its own embedded picture; captions are small pictures of words
        from layout import raw_image
        infos = [(i['xref'], [round(v, 1) for v in i['bbox']]) for i in page.get_image_info(xrefs=True)]
        big = [(x, b) for x, b in infos if b[3] - b[1] >= 60 and b[2] - b[0] >= 60 and b[1] * s > story_bottom - 10 and b[3] * s < bottom + 10]
        photos = []
        for x, b in big:
            if any(o is not b and o[0] >= b[0] - 1 and o[1] >= b[1] - 1 and o[2] <= b[2] + 1 and o[3] <= b[3] + 1 and (o[3] - o[1]) * (o[2] - o[0]) < (b[3] - b[1]) * (b[2] - b[0]) for _, o in big):
                continue        # a group holding other photos
            if any(abs(b[0] - q[0]) < 2 and abs(b[1] - q[1]) < 2 for _, q in photos):
                continue
            photos.append((x, b))
        rows = []
        for x, b in sorted(photos, key=lambda t: t[1][1]):
            cy = (b[1] + b[3]) / 2
            for r in rows:
                if abs(r[0] - cy) < 45:
                    r[1].append((x, b)); break
            else:
                rows.append([cy, [(x, b)]])
        ordered = [t for r in rows for t in sorted(r[1], key=lambda t: t[1][0])]
        med = sorted(b[2] - b[0] for _, b in ordered)[len(ordered) // 2] if ordered else 1
        split = []
        for x, b in ordered:       # two photos saved as one picture: cut it in two
            n = max(1, round((b[2] - b[0]) / med))
            split += [((x, k, n), b) for k in range(n)] if n > 1 else [(x, b)]
        ordered = split
        if len(ordered) != len(caps):
            report.append(f'{key}: {len(ordered)} photos for {len(caps)} captions')
        for (x, b), c in zip(ordered, caps):
            pics.append((('raw', x, b), c))
        erase = []
    else:       # Grade 1: boxes from MANUAL, trimmed to the picture with the page lettering removed
        erase = text_boxes + [[v * s for v in e] for e in ERASE.get(key, [])]
        for c in caps:
            if c not in MANUAL.get(key, {}):
                report.append(f'{key}: no box for {c!r}'); continue
            box = [v * s for v in MANUAL[key][c]]
            t = ink_box(im, box, erase=erase, pad=8)
            if not t:
                report.append(f'{key}: empty box for {c!r}'); continue
            pics.append((t, c))
    images = []
    for k, (box, cap) in enumerate(pics, 1):
        name = f's{pn:03d}-{k}.webp'
        if box[0] == 'raw':
            from layout import raw_image
            if isinstance(box[1], tuple):
                xref, part, n = box[1]
                crop = split_part(raw_image(doc, xref).convert('RGB'), part, n)
            else:
                crop = raw_image(doc, box[1])
            if crop.width > 700:
                crop = crop.resize((700, int(crop.height * 700 / crop.width)), Image.LANCZOS)
            crop.convert('RGB').save(os.path.join(out_dir, name), 'WEBP', quality=82)
            images.append(dict(src=f'assets/images/level5/g{g}/{name}', alt=cap or f'Picture: {title}', label=cap))
            continue
        crop = im.copy()
        from PIL import ImageDraw
        d = ImageDraw.Draw(crop)
        for e in erase:            # lettering never stays inside a picture
            if _overlaps(e, box) and not (cap == '' and caps is None and e not in text_boxes):
                d.rectangle([e[0] - 3, e[1] - 3, e[2] + 3, e[3] + 3], fill='white')
        crop = crop.crop([int(v) for v in box])
        if crop.width > 900:
            crop = crop.resize((900, int(crop.height * 900 / crop.width)), Image.LANCZOS)
        crop.save(os.path.join(out_dir, name), 'WEBP', quality=82)
        images.append(dict(src=f'assets/images/level5/g{g}/{name}', alt=cap or f'Picture: {title}', label=cap))
    text = '\n'.join(story)
    if key in EXTRA:
        text += '\n\n' + EXTRA[key]
    heading = title
    return dict(instruction='Listen as the story is read aloud. Look at the pictures and say the words.',
                cards=[dict(title='Read', heading=heading, text=text, images=images, choices=[])])


def main(keys=None):
    docs, report, out = {}, [], {}
    for key in (keys or STORIES):
        out[key] = build_one(key, docs, report)
    path = os.path.join(os.path.dirname(__file__), 'storycards.json')
    old = json.load(open(path)) if os.path.exists(path) and keys else {}
    old.update(out)
    json.dump(old, open(path, 'w'), ensure_ascii=False, indent=0)
    print('\n'.join(report) or 'all captions and pictures found')
    print(len(out), 'story pages')


if __name__ == '__main__':
    main(sys.argv[1:] or None)
