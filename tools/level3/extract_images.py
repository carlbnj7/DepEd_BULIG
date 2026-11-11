"""Level 3 (Word Recognition) picture extraction.

Renders each page of the supplied Level 3 PDF and cuts individual pictures out
of it.  Pictures are cropped from the rendered page (not the raw embedded
image) so masks and transparency look exactly as printed.  Every crop is
trimmed to its visible content.

Usage:  python3 tools/level3/extract_images.py path/to/level3.pdf
Writes: public/assets/images/level3/*.webp, storage/3/page-NNN.webp and
        database/level3-images.json (crop name -> file, page, box).
Requires: PyMuPDF, Pillow, NumPy.
"""
import json, os, sys
import numpy as np
import pymupdf
from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = os.path.join(ROOT, 'public', 'assets', 'images', 'level3')
PAGES = os.path.join(ROOT, 'storage', '3')
DPI = 200
FOOTER = (2079, 157)          # repeating page banner image; never a lesson picture

doc = None
_cache = {}


def page_image(pn):
    if pn not in _cache:
        pix = doc[pn - 1].get_pixmap(dpi=DPI)
        _cache[pn] = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
        if len(_cache) > 6:
            _cache.pop(next(iter(_cache)))
    return _cache[pn]


def trim(im, pad=6, thr=242):
    a = np.asarray(im.convert('L'))
    ys, xs = np.where(a < thr)
    if not len(xs):
        return im
    x0, x1, y0, y1 = xs.min(), xs.max(), ys.min(), ys.max()
    return im.crop((max(0, x0 - pad), max(0, y0 - pad), min(im.width, x1 + pad + 1), min(im.height, y1 + pad + 1)))


def main_part(im, thr=235, merge=0.07):
    """Drop slivers of neighbouring pictures: keep the largest content band on each axis."""
    a = np.asarray(im.convert('L')) < thr
    for axis in (1, 0):
        prof = a.any(axis=axis)
        n = len(prof)
        runs, start, last = [], None, None
        for i, v in enumerate(prof):
            if v:
                if start is None:
                    start = i
                elif i - last > n * merge:
                    runs.append((start, last)); start = i
                last = i
        if start is not None:
            runs.append((start, last))
        if len(runs) > 1:
            s, e = max(runs, key=lambda r: r[1] - r[0])
            if axis == 1:
                im = im.crop((0, max(0, s - 4), im.width, min(im.height, e + 5)))
            else:
                im = im.crop((max(0, s - 4), 0, min(im.width, e + 5), im.height))
            a = np.asarray(im.convert('L')) < thr
    return im


def crop_pct(pn, box, inset=0.0, do_trim=True, main=True):
    im = page_image(pn)
    x0, y0, x1, y1 = box
    W, H = im.size
    c = im.crop((int(W * (x0 + inset) / 100), int(H * (y0 + inset) / 100), int(W * (x1 - inset) / 100), int(H * (y1 - inset) / 100)))
    if not do_trim:
        return c
    return trim(main_part(trim(c))) if main else trim(c)


def crop_pt(pn, bbox):
    im = page_image(pn)
    s = DPI / 72
    return im.crop(tuple(int(v * s) for v in bbox))


def raw_image(pn, bbox):
    """The original embedded picture (alpha composited on white), matched by position."""
    for info in doc[pn - 1].get_image_info(xrefs=True):
        if info['xref'] and all(abs(a - b) < 0.6 for a, b in zip(info['bbox'], bbox)):
            x = info['xref']
            data = doc.extract_image(x)
            pix = pymupdf.Pixmap(doc, x)
            if data.get('smask') and not pix.alpha:
                pix = pymupdf.Pixmap(pix, pymupdf.Pixmap(doc, data['smask']))
            if pix.colorspace and pix.colorspace.n not in (1, 3):
                pix = pymupdf.Pixmap(pymupdf.csRGB, pix)
            mode = {1: 'L', 3: 'RGB'}[pix.n - pix.alpha]
            im = Image.frombytes(mode + ('A' if pix.alpha else ''), [pix.width, pix.height], pix.samples)
            if pix.alpha:
                bg = Image.new('RGB', im.size, 'white')
                bg.paste(im.convert('RGBA'), mask=im.split()[-1])
                im = bg
            m = info['transform']
            if m[0] < 0:
                im = im.transpose(Image.FLIP_LEFT_RIGHT)
            if m[3] < 0:
                im = im.transpose(Image.FLIP_TOP_BOTTOM)
            return trim(im.convert('RGB'))
    return crop_pt(pn, bbox)


def embedded(pn, skip_wide=True, min_pt=40):
    """Picture boxes on a page in reading order (rows, then left to right)."""
    boxes = []
    for info in doc[pn - 1].get_image_info():
        if (info['width'], info['height']) == FOOTER:
            continue
        x0, y0, x1, y1 = info['bbox']
        w, h = x1 - x0, y1 - y0
        if w < min_pt or h < min_pt * 0.6:
            continue
        if skip_wide and w / max(h, 1) > 4:      # printed "Consonant Blends" banners
            continue
        boxes.append((x0, y0, x1, y1))
    boxes.sort(key=lambda b: (b[1], b[0]))
    rows = []
    for b in boxes:
        if rows and abs(b[1] - rows[-1][0][1]) < 45:
            rows[-1].append(b)
        else:
            rows.append([b])
    return [b for r in rows for b in sorted(r, key=lambda b: b[0])]


def column_major(boxes):
    return sorted(boxes, key=lambda b: (round(b[0] / 120), b[1]))


def bands(pn, box, axis, expect=None, gap_pct=0.9, thr=235):
    """Split box into content bands along axis ('y' rows, 'x' columns)."""
    im = page_image(pn)
    W, H = im.size
    x0, y0, x1, y1 = [int(v / 100 * (W if i % 2 == 0 else H)) for i, v in enumerate(box)]
    a = np.asarray(im.crop((x0, y0, x1, y1)).convert('L')) < thr
    prof = a.any(axis=1) if axis == 'y' else a.any(axis=0)
    gap = int((H if axis == 'y' else W) * gap_pct / 100)
    runs, start, last = [], None, None
    for i, v in enumerate(prof):
        if v:
            if start is None:
                start = i
            elif i - last > gap:
                runs.append((start, last))
                start = i
            last = i
    if start is not None:
        runs.append((start, last))
    runs = [r for r in runs if r[1] - r[0] > gap]
    if expect and len(runs) != expect:
        # Touching pictures: fall back to equal slots across the box.
        print(f'  page {pn}: {len(runs)} bands on {axis}, using {expect} equal slots')
        lo, hi = (box[1], box[3]) if axis == 'y' else (box[0], box[2])
        step = (hi - lo) / expect
        slots = [(lo + i * step, lo + (i + 1) * step) for i in range(expect)]
        return [(box[0], a, box[2], b) if axis == 'y' else (a, box[1], b, box[3]) for a, b in slots]
    out = []
    for s, e in runs:
        if axis == 'y':
            out.append((box[0], (y0 + s) / H * 100 - 0.3, box[2], (y0 + e) / H * 100 + 0.3))
        else:
            out.append(((x0 + s) / W * 100 - 0.3, box[1], (x0 + e) / W * 100 + 0.3, box[3]))
    return out


def grid(rows, cols, inset=0.7):
    return [(cols[c], rows[r], cols[c + 1], rows[r + 1]) for r in range(len(rows) - 1) for c in range(len(cols) - 1)]


manifest = {}


def save(name, im, pn, box):
    im = im.convert('RGB')
    if max(im.size) > 900:
        im.thumbnail((900, 900), Image.LANCZOS)
    path = os.path.join(OUT, name + '.webp')
    im.save(path, 'WEBP', quality=84, method=6)
    manifest[name] = {'src': 'assets/images/level3/' + name + '.webp', 'page': pn, 'box': [round(v, 2) for v in box], 'size': list(im.size)}


def from_embedded(pn, names, order=None, prefix=None):
    boxes = embedded(pn)
    if order == 'columns':
        boxes = column_major(boxes)
    if len(boxes) != len(names):
        raise SystemExit(f'page {pn}: {len(boxes)} pictures, {len(names)} names')
    for n, b in zip(names, boxes):
        save(n, raw_image(pn, b), pn, b)


def from_boxes(pn, names, boxes, inset=0.0, do_trim=True, main=True):
    if len(boxes) != len(names):
        raise SystemExit(f'page {pn}: {len(boxes)} boxes, {len(names)} names')
    for n, b in zip(names, boxes):
        save(n, crop_pct(pn, b, inset, do_trim, main), pn, b)


def whole(pn, name):
    boxes = embedded(pn, skip_wide=False, min_pt=150)
    big = max(boxes, key=lambda b: (b[2] - b[0]) * (b[3] - b[1]))
    save(name, crop_pt(pn, big), pn, big)


def seq(prefix, n):
    return [f'{prefix}-{i:02d}' for i in range(1, n + 1)]


def two_column_list(pn, prefix, left, right, top, bottom, per_col=6):
    """Picture beside a blank, in two columns (read left then right per row)."""
    lrows = marker_rows(pn, (left[1] + 10, top, left[1] + 20, bottom), per_col)
    rrows = marker_rows(pn, (right[1] + 10, top, right[1] + 20, bottom), per_col)
    lb = [(left[0], a, left[1], b) for a, b in lrows]
    rb = [(right[0], a, right[1], b) for a, b in rrows]
    boxes = [b for pair in zip(lb, rb) for b in pair]
    from_boxes(pn, seq(prefix, len(boxes)), boxes)


def marker_rows(pn, marker_box, n, gap_pct=0.8):
    """Row slots from a column of clearly separated markers (answer boxes or bullets)."""
    marks = bands(pn, marker_box, 'y', n, gap_pct=gap_pct)
    centres = [(b[1] + b[3]) / 2 for b in marks]
    step = (centres[-1] - centres[0]) / (n - 1)
    edges = [centres[0] - step / 2] + [(a + b) / 2 for a, b in zip(centres, centres[1:])] + [centres[-1] + step / 2]
    return list(zip(edges, edges[1:]))


def picture_rows(pn, prefix, rows, x0, x1, per_row=3):
    """Rows of several pictures (Read and Choose)."""
    boxes = []
    for (t, b) in rows:
        boxes += bands(pn, (x0, t + 0.8, x1, b - 0.8), 'x', per_row, gap_pct=1.2)
    from_boxes(pn, seq(prefix, len(boxes)), boxes)


def build():
    os.makedirs(OUT, exist_ok=True)
    # ---- Lessons 1-5: CVC short vowels -------------------------------------------------
    teacher = {1: ['bat', 'cat'], 2: ['net', 'ten'], 3: ['zip', 'bin'], 4: ['dog', 'jog'], 5: ['mug', 'bun']}
    for n in range(1, 6):
        b = 13 + 6 * (n - 1)
        from_embedded(b, [f'l{n:02d}-intro-{w}' for w in teacher[n]])
        from_embedded(b + 1, seq(f'l{n:02d}-build', 10))
        tell = embedded(b + 3) + embedded(b + 4)
        for i, box in enumerate(tell, 1):
            save(f'l{n:02d}-tell-{i:02d}', raw_image(b + 3 if i <= 4 else b + 4, box), b + 3 if i <= 4 else b + 4, box)
        if len(tell) != 10:
            raise SystemExit(f'lesson {n}: {len(tell)} Tell Me pictures')
    # ---- Lessons 6-10: word families -------------------------------------------------------
    fam = {6: (43, ['meal', 'pail'], 44), 7: (47, ['goat', 'book'], 48), 8: (51, ['sack', 'neck'], None),
           9: (54, ['ball', 'bell'], 55), 10: (58, ['tank', 'mask'], 59)}
    for n, (tp, words, lm) in fam.items():
        from_embedded(tp, [f'l{n:02d}-intro-{w}' for w in words])
        if lm:
            pics = embedded(lm) + embedded(lm + 1)
            if len(pics) != 10:
                raise SystemExit(f'lesson {n}: {len(pics)} listen-and-match pictures')
            for i, box in enumerate(pics, 1):
                pn = lm if i <= 4 else lm + 1
                save(f'l{n:02d}-match-{i:02d}', raw_image(pn, box), pn, box)
    # ---- Lessons 11-20: consonant blends ------------------------------------------------------
    intro = {11: (62, ['blue', 'bread']), 12: (68, ['clip', 'cry']), 13: (78, ['drone']), 14: (85, ['flag', 'frog']),
             15: (96, ['glue', 'grass']), 16: (105, ['plane', 'pray']), 17: (111, ['star', 'straw']),
             18: (115, ['ship', 'sleep']), 19: (122, ['spider', 'spray', 'splash']), 20: (126, ['train'])}
    for n, (pn, words) in intro.items():
        from_embedded(pn, [f'l{n:02d}-intro-{w}' for w in words])
    # Fill-in-the-blank grids: the whole cell, so the printed ending stays with its picture.
    from_boxes(63, seq('l11-fill', 10), grid([17.7, 31.6, 46.5, 60.3, 74.2, 88.1], [18.2, 54.0, 90.0]), inset=0.5, main=False)
    from_boxes(87, seq('l14-name', 12), grid([19.1, 36.4, 53.6, 70.9, 88.1], [19.5, 43.9, 68.2, 92.6]), inset=0.5, main=False)
    from_boxes(106, seq('l16-fill', 10), grid([16.5, 30.6, 45.7, 59.8, 73.9, 88.0], [15.9, 52.4, 88.9]), inset=0.5, main=False)
    from_boxes(123, seq('l19-fill', 12), grid([19.2, 36.5, 53.7, 70.9, 88.2], [16.1, 41.8, 67.5, 93.2]), inset=0.5, main=False)
    # Picture sort / circle grids: picture part of each cell only.
    from_boxes(67, seq('l11-sort', 9), [(c[0], c[1], c[0] + (c[2] - c[0]) * 0.72, c[3]) for c in grid([39.6, 53.3, 67.2, 81.0], [19.9, 43.0, 65.5, 87.9])], inset=0.6)
    from_boxes(73, seq('l12-circle', 9), [(c[0], c[1], c[0] + (c[2] - c[0]) * 0.74, c[3]) for c in grid([34.6, 52.1, 69.7, 88.0], [16.2, 41.6, 66.8, 92.1])], inset=0.6)
    from_boxes(75, seq('l12-match', 12), [(c[0], c[1], c[0] + (c[2] - c[0]) * 0.62, c[3]) for c in grid([22.3, 34.2, 45.2, 56.5, 66.5], [22.9, 45.2, 67.4, 89.6])], inset=0.5)
    from_boxes(102, seq('l15-match', 12), [(c[0], c[1], c[0] + (c[2] - c[0]) * 0.52, c[3]) for c in grid([25.7, 36.3, 47.4, 58.3, 69.0], [17.6, 41.7, 66.0, 90.0])], inset=0.5)
    from_boxes(91, seq('l14-circle', 6), [(c[0], c[1] + 1.2, c[0] + (c[2] - c[0]) * 0.54, c[1] + (c[3] - c[1]) * 0.62) for c in grid([27.0, 43.8, 62.4, 81.8], [20.5, 55.0, 89.1])], inset=0.6)
    # Supplementary fill-in lists (picture, box, word ending) in two columns.
    two_column_list(66, 'l11-supp', (17, 31), (57, 71), 36, 90)
    two_column_list(74, 'l12-supp', (20.5, 33.5), (54, 68), 39.5, 91)
    two_column_list(90, 'l14-supp', (19, 33.8), (55, 68.5), 34.5, 87.5)
    two_column_list(100, 'l15-supp', (17.5, 30), (51, 65.5), 34.7, 85.2)
    two_column_list(110, 'l16-supp', (17.5, 30.2), (56.5, 69.3), 35.7, 86.7)
    # Picture columns for matching pages.
    for pn, prefix, pic, dots in [(70, 'l12-assoc', (22, 43), (45.5, 49)), (80, 'l13-assoc', (25, 41), (45, 48.5)),
                                  (113, 'l17-assoc', (25, 43), (45.5, 49.5)), (117, 'l18-assoc', (23, 41), (43.5, 47.5))]:
        slots = marker_rows(pn, (dots[0], 19, dots[1], 93), 10)
        from_boxes(pn, seq(prefix, 10), [(pic[0], a, pic[1], b) for a, b in slots])
    from_boxes(92, seq('l14-match', 5), bands(92, (18, 31, 47, 90), 'y', 5, gap_pct=0.4))
    left = bands(93, (28, 29, 40.5, 91), 'y', 5, gap_pct=1.0)
    right = bands(93, (55, 29, 67, 91), 'y', 5, gap_pct=1.0)
    from_boxes(93, seq('l14-flmatch', 10), [b for pair in zip(left, right) for b in pair])
    top = bands(82, (18, 25, 88, 35.4), 'x', 3, gap_pct=1.5)
    bottom = bands(82, (18, 44, 88, 54.9), 'x', 3, gap_pct=1.5)
    from_boxes(82, seq('l13-dr', 6), top + bottom)
    r1 = bands(83, (22, 64, 85, 73.5), 'x', 4, gap_pct=1.5)
    r2 = bands(83, (22, 74, 85, 84), 'x', 4, gap_pct=1.5)
    from_boxes(83, seq('l13-sort', 8), [(b[0] + 1.4, b[1] + 1.2, b[2] - 1.4, b[3] - 1.0) for b in r1 + r2])
    from_boxes(101, seq('l15-trace', 5), bands(101, (47.5, 32, 65.5, 90), 'y', 5, gap_pct=0.6), inset=0.6)
    picture_rows(72, 'l12-choose', [(30.0, 40.7), (41.7, 52.4), (53.3, 64.1), (65.0, 75.7), (76.6, 87.4)], 42, 85.5)
    picture_rows(121, 'l18-choose', [(28.9, 39.4), (40.6, 51.1), (52.4, 62.9), (64.1, 74.5), (75.8, 85.4)], 37, 87)
    from_embedded(97, seq('l15-tune', 10))
    from_embedded(127, seq('l20-tune', 10))
    # Whole boards, charts and game pictures.
    for pn, name in [(64, 'l11-dartboard'), (69, 'l12-dartboard'), (76, 'l12-cr-chart'), (77, 'l12-cl-chart'),
                     (79, 'l13-map'), (84, 'l13-dr-chart'), (88, 'l14-basket'), (94, 'l14-fr-chart'),
                     (95, 'l14-fl-chart'), (98, 'l15-shirts'), (103, 'l15-gr-chart'), (104, 'l15-gl-chart'),
                     (107, 'l16-dartboard'), (109, 'l16-chart'), (112, 'l17-dartboard'), (116, 'l18-map'),
                     (119, 'l18-sl-chart'), (120, 'l18-sh-chart'), (124, 'l19-basket'), (128, 'l20-shirts'),
                     (130, 'l21-fish'), (132, 'l22-snake-ladder'), (134, 'l23-wheel'), (136, 'l24-mystery-box'),
                     (139, 'l25-leaf')]:
        whole(pn, name)
    from_boxes(138, ['l25-word-hunt'], [(14.5, 49.5, 86.5, 91)], main=False)
    from_embedded(9, ['pre-assessment'])
    from_embedded(141, ['post-assessment'])
    from_embedded(12, ['intervention-materials'])


def render_pages():
    os.makedirs(PAGES, exist_ok=True)
    for i, p in enumerate(doc, 1):
        pix = p.get_pixmap(dpi=110)
        im = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
        im.save(os.path.join(PAGES, f'page-{i:03d}.webp'), 'WEBP', quality=80, method=6)


if __name__ == '__main__':
    doc = pymupdf.open(sys.argv[1])
    build()
    render_pages()
    with open(os.path.join(ROOT, 'database', 'level3-images.json'), 'w') as f:
        json.dump({'source': 'LEVEL 3 WORD RECOGNITION Version 1.pdf', 'pages': doc.page_count, 'images': manifest}, f, indent=1)
    print(len(manifest), 'pictures;', doc.page_count, 'page renders')
