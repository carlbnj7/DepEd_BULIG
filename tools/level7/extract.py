"""Read every Level 6 module page into lines (text with position, bold, size) and picture boxes.
Lines on the same baseline are joined; the private-use check-box glyph becomes "[ ]".
Usage (as a module): from extract import page_lines, page_pictures
"""
import os, re
import pymupdf

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
_docs = {}


def doc(g):
    if g not in _docs:
        _docs[g] = pymupdf.open(os.path.join(ROOT, 'storage', 'level7', f'grade-{g}.pdf'))
    return _docs[g]


def clean(t):
    t = t.replace('', '✓').replace('', '[ ]').replace('', '[ ]').replace(' ', ' ')
    t = re.sub(r'[-]', '[ ]', t)
    return re.sub(r'\s+', ' ', t).strip()


def page_lines(g, pn):
    page = doc(g)[pn - 1]
    H = page.rect.height
    W = page.rect.width
    banners = [i['bbox'] for i in page.get_image_info() if (i['bbox'][2] - i['bbox'][0]) > W * 0.6 and (i['bbox'][3] - i['bbox'][1]) < 90]
    spans = []
    for b in page.get_text('dict')['blocks']:
        if b['type'] != 0:
            continue
        for l in b['lines']:
            for s in l['spans']:
                if not s['text'].strip():
                    continue
                x0, y0, x1, y1 = s['bbox']
                if any(b[0] <= (x0 + x1) / 2 <= b[2] and b[1] <= (y0 + y1) / 2 <= b[3] for b in banners):   # text on the banner strip
                    continue
                spans.append(dict(x0=x0, y0=y0, x1=x1, y1=y1, t=s['text'], bold=bool(s['flags'] & 16), size=s['size']))
    spans.sort(key=lambda s: ((s['y0'] + s['y1']) / 2, s['x0']))
    rows = []
    for s in spans:
        cy = (s['y0'] + s['y1']) / 2
        if rows and abs(rows[-1]['cy'] - cy) < max(3.0, (s['y1'] - s['y0']) * 0.35):
            rows[-1]['spans'].append(s)
        else:
            rows.append(dict(cy=cy, spans=[s]))
    out = []
    for r in rows:
        ss = sorted(r['spans'], key=lambda s: s['x0'])
        # split a row into segments at wide gaps (two columns)
        segs = [[ss[0]]]
        for s in ss[1:]:
            if s['x0'] - segs[-1][-1]['x1'] > 28:
                segs.append([s])
            else:
                segs[-1].append(s)
        for seg in segs:
            t = clean(''.join(x['t'] if i == 0 or x['x0'] - seg[i - 1]['x1'] < 1.5 or x['t'].startswith(' ') or seg[i - 1]['t'].endswith(' ')
                              else ' ' + x['t'] for i, x in enumerate(seg)))
            if not t:
                continue
            out.append(dict(x0=round(seg[0]['x0'], 1), x1=round(seg[-1]['x1'], 1), y0=round(min(x['y0'] for x in seg), 1),
                            y1=round(max(x['y1'] for x in seg), 1), t=t,
                            bold=sum(len(x['t']) for x in seg if x['bold']) > 0.6 * sum(len(x['t']) for x in seg),
                            size=round(max(x['size'] for x in seg), 1)))
    return out


def page_pictures(g, pn, min_side=40):
    """Boxes (points) of the pictures on a page: footer banner, check-box icons and full-page backgrounds left out;
    overlapping boxes (a picture and its mask) merged."""
    page = doc(g)[pn - 1]
    W, H = page.rect.width, page.rect.height
    boxes = []
    for info in page.get_image_info():
        x0, y0, x1, y1 = info['bbox']
        x0, y0, x1, y1 = max(0, x0), max(0, y0), min(W, x1), min(H, y1)
        if (x1 - x0) > W * 0.6 and (y1 - y0) < 90:      # the decorative banner strip (top or bottom)
            continue
        if (x1 - x0) < min_side or (y1 - y0) < min_side:
            continue
        if (x1 - x0) > W * 0.95 and (y1 - y0) > H * 0.9:
            continue
        boxes.append([x0, y0, x1, y1])
    changed = True
    while changed:
        changed = False
        for i in range(len(boxes)):
            for j in range(i + 1, len(boxes)):
                a, b = boxes[i], boxes[j]
                ix = min(a[2], b[2]) - max(a[0], b[0]); iy = min(a[3], b[3]) - max(a[1], b[1])
                if ix > 0 and iy > 0 and ix * iy > 0.3 * min((a[2] - a[0]) * (a[3] - a[1]), (b[2] - b[0]) * (b[3] - b[1])):
                    boxes[i] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]
                    del boxes[j]; changed = True; break
            if changed:
                break
    # a picture saved as a full-page layer (Grade 4): find the drawn parts on the text-free page
    big = [b for b in boxes if (b[2] - b[0]) * (b[3] - b[1]) > 0.35 * W * H]
    if big:
        boxes = [b for b in boxes if b not in big] + [b for b in blobs(g, pn) if not any(_inside(b, o) for o in boxes if o not in big)]
    return sorted(boxes, key=lambda b: (round(b[1] / 40), b[0]))


def _inside(a, b, tol=6):
    return a[0] >= b[0] - tol and a[1] >= b[1] - tol and a[2] <= b[2] + tol and a[3] <= b[3] + tol


_clean = {}


def blobs(g, pn, dpi=60):
    """Picture blobs (points) on the page with text and line art removed, footer banner left out."""
    import numpy as np
    from collections import deque
    if g not in _clean:
        d = pymupdf.open(doc(g).name)
        for p in d:
            p.add_redact_annot(p.rect)
            p.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_REMOVE_IF_TOUCHED,
                               text=pymupdf.PDF_REDACT_TEXT_REMOVE)
        _clean[g] = d
    page = _clean[g][pn - 1]
    pix = page.get_pixmap(dpi=dpi)
    a = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)[:, :, :3].astype(int)
    m = (a.min(axis=2) < 235)
    s = dpi / 72
    m[int(page.rect.height * 0.9 * s):, :] = False
    H, W = m.shape
    g2 = m.copy()
    for _ in range(3):
        n = g2.copy(); n[1:] |= g2[:-1]; n[:-1] |= g2[1:]; n[:, 1:] |= g2[:, :-1]; n[:, :-1] |= g2[:, 1:]; g2 = n
    seen = np.zeros_like(g2); out = []
    for y in range(H):
        for x in range(W):
            if g2[y, x] and not seen[y, x]:
                q = deque([(y, x)]); seen[y, x] = True; y0 = y1 = y; x0 = x1 = x; cnt = 0
                while q:
                    cy, cx = q.popleft(); cnt += 1
                    y0, y1, x0, x1 = min(y0, cy), max(y1, cy), min(x0, cx), max(x1, cx)
                    for ny, nx in ((cy - 1, cx), (cy + 1, cx), (cy, cx - 1), (cy, cx + 1)):
                        if 0 <= ny < H and 0 <= nx < W and g2[ny, nx] and not seen[ny, nx]:
                            seen[ny, nx] = True; q.append((ny, nx))
                if (x1 - x0) / s >= 30 and (y1 - y0) / s >= 30 and cnt > 200:
                    out.append([x0 / s - 2, y0 / s - 2, (x1 + 1) / s + 2, (y1 + 1) / s + 2])
    return out
