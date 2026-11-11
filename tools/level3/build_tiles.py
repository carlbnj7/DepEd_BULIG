#!/usr/bin/env python3
"""Level 3 "Activity 1: Build a Word" (Lessons 1-5): the module's letter boxes become tappable tiles.

    python3 tools/level3/build_tiles.py

Run it after build_content.py. For each of the 50 cards it
  1. cuts the picture just above the printed letter boxes -> assets/images/level3/l0N-build-NN-pic.webp
  2. writes the card's tiles and answer into database/level3-cards.json:
     "build": {"rows": ["car", "rat"], "word": "car"}  (the two rows of boxes as printed, the word the picture shows)
The tiles and answers were read from the module pages (PDF pages 14, 20, 26, 32 and 38) and checked by hand.
Every answer takes one letter from each column of the boxes. Safe to run again.
"""
import json, os
import numpy as np
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
IMG = os.path.join(ROOT, 'public', 'assets', 'images', 'level3')
CARDS = os.path.join(ROOT, 'database', 'level3-cards.json')

# lesson -> 10 cards: (top row, bottom row, answer)
TILES = {
    1: [('car', 'rat', 'car'), ('cad', 'sat', 'sad'), ('bad', 'mag', 'bag'), ('fam', 'ban', 'fan'), ('map', 'can', 'man'),
        ('cat', 'bap', 'cap'), ('lap', 'mat', 'map'), ('can', 'bat', 'bat'), ('cat', 'bad', 'cat'), ('far', 'sat', 'fat')],
    2: [('ber', 'bad', 'bed'), ('nap', 'met', 'net'), ('wed', 'lab', 'web'), ('den', 'pat', 'pen'), ('len', 'tan', 'ten'),
        ('jan', 'bet', 'jet'), ('rep', 'cad', 'red'), ('wep', 'bat', 'wet'), ('vat', 'ten', 'vet'), ('lad', 'beg', 'leg')],
    3: [('cin', 'bab', 'bib'), ('cap', 'zit', 'zip'), ('sin', 'bmt', 'bin'), ('sin', 'pat', 'pin'), ('len', 'tip', 'lip'),
        ('cin', 'sat', 'sit'), ('ten', 'bip', 'tip'), ('dip', 'bag', 'dig'), ('sap', 'bit', 'sip'), ('sin', 'bam', 'sim')],
    4: [('fex', 'lot', 'fox'), ('dig', 'hog', 'dog'), ('jon', 'mag', 'jog'), ('pon', 'lig', 'log'), ('com', 'man', 'mom'),
        ('not', 'bix', 'box'), ('con', 'map', 'mop'), ('bot', 'hip', 'hop'), ('con', 'pat', 'pot'), ('lot', 'hab', 'hot')],
    5: [('tug', 'hat', 'hug'), ('gut', 'hog', 'hut'), ('got', 'bum', 'gum'), ('pun', 'big', 'bug'), ('mig', 'tup', 'mug'),
        ('nun', 'bit', 'bun'), ('rot', 'lun', 'run'), ('sin', 'hut', 'sun'), ('nit', 'wup', 'nut'), ('lut', 'bas', 'bus')],
}


def tiles_top(gray):
    """Top edge of the printed letter boxes: the 5th long horizontal line from the bottom."""
    a = np.asarray(gray) < 120
    h, w = a.shape
    rows = [y for y in range(int(h * .35), h) if a[y].sum() > w * 0.3]
    lines = []
    for y in rows:
        if lines and y - lines[-1][-1] <= 2:
            lines[-1].append(y)
        else:
            lines.append([y])
    mids = [(g[0] + g[-1]) // 2 for g in lines]
    if len(mids) < 5:
        raise SystemExit('letter boxes not found')
    return mids[-5]


def picture_only(src, dst):
    im = Image.open(src).convert('RGB')
    top = tiles_top(im.convert('L')) - 6
    pic = im.crop((0, 0, im.width, top))
    a = np.asarray(pic.convert('L')) < 245
    ys, xs = np.where(a)
    if len(xs):
        pad = 10
        pic = pic.crop((max(0, xs.min() - pad), max(0, ys.min() - pad), min(pic.width, xs.max() + pad + 1), min(pic.height, ys.max() + pad + 1)))
    pic.save(dst, 'WEBP', quality=82, method=6)
    return pic.size


def main():
    data = json.load(open(CARDS, encoding='utf-8'))
    for lesson, cards in TILES.items():
        key = f'{lesson + 1}:learn:3'
        deck = data['cards'][key]
        assert 'Build a Word' in deck['title'], key
        assert len(deck['cards']) == len(cards), key
        for n, (r1, r2, word) in enumerate(cards, 1):
            assert all(word[i] in (r1[i], r2[i]) for i in range(len(word))), (lesson, n, word)
            name = f'l0{lesson}-build-{n:02d}'
            size = picture_only(os.path.join(IMG, name + '.webp'), os.path.join(IMG, name + '-pic.webp'))
            card = deck['cards'][n - 1]
            card['images'] = [{'src': f'assets/images/level3/{name}-pic.webp', 'alt': 'Picture to build a word for', 'label': ''}]
            card['build'] = {'rows': [r1, r2], 'word': word}
            print(name, size)
    with open(CARDS, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=1)


if __name__ == '__main__':
    main()
