"""Level 4 (Fluency) picture extraction, one module per grade.

For every reading passage in database/level4-passages.json, cuts the pictures
printed with it from the rendered page (so masks and transparency look as
printed), and renders every page of each grade's PDF for teachers.

Usage:  python3 tools/level4/extract_images.py [grade ...]
Reads:  storage/level4/grade-N.pdf, database/level4-passages.json
Writes: public/assets/images/level4/gN-pPP-K.webp, storage/level4/gN/page-NNN.webp,
        database/level4-images.json (grade -> passage index -> picture files).
Requires: PyMuPDF, Pillow, NumPy.
"""
import json, os, sys
import numpy as np
import pymupdf
from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = os.path.join(ROOT, 'public', 'assets', 'images', 'level4')
DPI = 200
FOOTER = (1448, 193)          # repeating page banner image; never a passage picture


def trim(im, pad=6, thr=242):
    a = np.asarray(im.convert('L'))
    ys, xs = np.where(a < thr)
    if not len(xs):
        return im
    return im.crop((max(0, xs.min() - pad), max(0, ys.min() - pad), min(im.width, xs.max() + pad + 1), min(im.height, ys.max() + pad + 1)))


def boxes(page):
    """Picture boxes on a page: large images, overlapping ones merged (a picture and its mask or frame)."""
    out, strips = [], []
    for info in page.get_image_info():
        x0, y0, x1, y1 = info['bbox']
        if (info['width'], info['height']) == FOOTER or y0 > page.rect.height - 95:
            continue
        x0, y0, x1, y1 = max(x0, 0), max(y0, 0), min(x1, page.rect.width), min(y1, page.rect.height)
        if (x1 - x0) > 3 * (y1 - y0) and y1 - y0 < 60:
            strips.append([x0, y0, x1, y1])   # a line of lettering (Grades 1-2 print titles and sentences as pictures)
            continue
        if x1 - x0 < 50 or y1 - y0 < 50:
            continue
        out.append([x0, y0, x1, y1])
    merged = True
    while merged:
        merged = False
        for i in range(len(out)):
            for j in range(i + 1, len(out)):
                a, b = out[i], out[j]
                if a[0] < b[2] and b[0] < a[2] and a[1] < b[3] and b[1] < a[3]:
                    out[i] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]
                    out.pop(j); merged = True
                    break
            if merged:
                break
    for b in out:          # keep lettering out of the crop
        for t in strips:
            if t[0] < b[2] and b[0] < t[2] and t[1] < b[3] and b[1] < t[3]:
                if (t[1] + t[3]) / 2 < (b[1] + b[3]) / 2:
                    b[1] = max(b[1], t[3])
                else:
                    b[3] = min(b[3], t[1])
    return sorted(out, key=lambda b: (round(b[1] / 40), b[0]))


def build(grade, passages):
    doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level4', f'grade-{grade}.pdf'))
    pages = os.path.join(ROOT, 'storage', 'level4', f'g{grade}')
    os.makedirs(pages, exist_ok=True)
    for i, page in enumerate(doc, 1):
        pix = page.get_pixmap(dpi=110)
        Image.frombytes('RGB', [pix.width, pix.height], pix.samples).save(os.path.join(pages, f'page-{i:03d}.webp'), 'WEBP', quality=70)
    result, done = [], {}
    for p in passages:
        files = []
        for pn in p['pages']:
            if pn not in done:
                page = doc[pn - 1]
                # Titles, captions and wrapped text are printed over picture areas; leave only the art.
                page.add_redact_annot(page.rect)
                page.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_NONE, text=pymupdf.PDF_REDACT_TEXT_REMOVE)
                names = []
                for k, b in enumerate(boxes(page), 1):
                    pix = page.get_pixmap(dpi=DPI, clip=pymupdf.Rect(b))
                    im = trim(Image.frombytes('RGB', [pix.width, pix.height], pix.samples))
                    g = np.asarray(im.convert('L')); c = np.asarray(im).astype(int); sat = c.max(2) - c.min(2)
                    if im.width < 60 or im.height < 60 or (((g > 60) & (g < 120)).mean() > 0.6 and sat.mean() < 8):
                        continue      # empty or a stray drop-shadow box
                    im.thumbnail((900, 900))
                    name = f'g{grade}-p{pn:02d}-{k}.webp'
                    im.save(os.path.join(OUT, name), 'WEBP', quality=82)
                    names.append(dict(file='level4/' + name, page=pn, box=[round(v, 1) for v in b], w=im.width, h=im.height))
                done[pn] = names
            files += done[pn]
        result.append(files)
    return result


if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    data = json.load(open(os.path.join(ROOT, 'database', 'level4-passages.json'), encoding='utf-8'))
    path = os.path.join(ROOT, 'database', 'level4-images.json')
    out = json.load(open(path)) if os.path.exists(path) else {}
    for g in [int(x) for x in sys.argv[1:]] or range(1, 7):
        out[str(g)] = build(g, data[str(g)])
        print(f'G{g}', [len(x) for x in out[str(g)]])
    json.dump(out, open(path, 'w'), indent=1)
