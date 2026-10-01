"""Cut the separate pictures out of a module page and find their captions (for pages printed as one big
picture, like the Grade 1 story pages). Text lines found by OCR (or the PDF text layer) are split into the
story text above the pictures and the caption printed under each picture; the story lettering is erased so
it is never shown twice (once in a picture and once as text)."""
import hashlib, json, os, re, subprocess, tempfile
from collections import deque
import numpy as np
from PIL import Image

CACHE_FILE = os.path.join(os.path.dirname(__file__), 'ocr-words.json')
CACHE = json.load(open(CACHE_FILE)) if os.path.exists(CACHE_FILE) else {}


def ocr_words(im):
    """[(text, [x0,y0,x1,y1], line_key)] for every word tesseract reads with some confidence."""
    key = hashlib.sha1(im.tobytes()).hexdigest()
    if key not in CACHE:
        with tempfile.TemporaryDirectory() as d:
            f = os.path.join(d, 'p.png'); im.save(f)
            r = subprocess.run(['tesseract', f, '-', '--psm', '11', 'tsv'], capture_output=True, text=True).stdout
        out = []
        for row in r.splitlines()[1:]:
            c = row.split('\t')
            if len(c) < 12 or not c[11].strip() or float(c[10]) < 35 or not re.search(r'[A-Za-z]', c[11]):
                continue
            x, y, w, h = map(int, c[6:10])
            out.append((c[11], [x, y, x + w, y + h]))
        CACHE[key] = out
        json.dump(CACHE, open(CACHE_FILE, 'w'))
    return CACHE[key]


def lines_of(words, gap=1.2):
    """Group words into lines (same baseline, small horizontal gaps)."""
    ws = sorted(words, key=lambda w: (w[1][1] + w[1][3]) / 2)
    rows = []
    for w in ws:
        cy, hgt = (w[1][1] + w[1][3]) / 2, w[1][3] - w[1][1]
        for r in rows:
            if abs(r['cy'] - cy) < max(hgt, r['h']) * 0.5:
                r['w'].append(w); break
        else:
            rows.append(dict(cy=cy, h=hgt, w=[w]))
    out = []
    for r in rows:      # split a row at wide gaps: separate captions side by side
        ws = sorted(r['w'], key=lambda w: w[1][0])
        cur = [ws[0]]
        for w in ws[1:]:
            if w[1][0] - cur[-1][1][2] > (cur[-1][1][3] - cur[-1][1][1]) * gap * 2:
                out.append(cur); cur = [w]
            else:
                cur.append(w)
        out.append(cur)
    res = []
    for ws in out:
        bx = [min(w[1][0] for w in ws), min(w[1][1] for w in ws), max(w[1][2] for w in ws), max(w[1][3] for w in ws)]
        res.append(dict(text=' '.join(w[0] for w in ws), box=bx))
    return sorted(res, key=lambda l: (l['box'][1], l['box'][0]))


def components(mask, cell=6, grow=2, min_side=50):
    """Bounding boxes of the ink blobs in a boolean mask (coarse grid + dilation, pure numpy/BFS)."""
    H, W = mask.shape
    gh, gw = H // cell, W // cell
    m = mask[:gh * cell, :gw * cell].reshape(gh, cell, gw, cell).sum(axis=(1, 3)) >= 3
    g = m.copy()
    for _ in range(grow):
        n = g.copy()
        n[1:, :] |= g[:-1, :]; n[:-1, :] |= g[1:, :]; n[:, 1:] |= g[:, :-1]; n[:, :-1] |= g[:, 1:]
        g = n
    seen = np.zeros_like(g)
    boxes = []
    for y in range(gh):
        for x in range(gw):
            if g[y, x] and not seen[y, x]:
                q = deque([(y, x)]); seen[y, x] = True
                y0 = y1 = y; x0 = x1 = x; cnt = 0
                while q:
                    cy, cx = q.popleft(); cnt += m[cy, cx]
                    y0, y1, x0, x1 = min(y0, cy), max(y1, cy), min(x0, cx), max(x1, cx)
                    for ny, nx in ((cy - 1, cx), (cy + 1, cx), (cy, cx - 1), (cy, cx + 1)):
                        if 0 <= ny < gh and 0 <= nx < gw and g[ny, nx] and not seen[ny, nx]:
                            seen[ny, nx] = True; q.append((ny, nx))
                b = [(x0 + grow) * cell, (y0 + grow) * cell, (x1 + 1 - grow) * cell, (y1 + 1 - grow) * cell]
                if b[2] - b[0] >= min_side and b[3] - b[1] >= min_side and cnt > 30:
                    boxes.append(b)
    return boxes


def inside(a, b, tol=4):
    return a[0] >= b[0] - tol and a[1] >= b[1] - tol and a[2] <= b[2] + tol and a[3] <= b[3] + tol


def segment(im, words, top=0, bottom=None, ink_thr=225, cell=6, grow=2, min_side=50):
    """-> story lines, [(picture box, caption)], erase boxes."""
    W, H = im.size
    bottom = bottom or H
    a = np.asarray(im.convert('RGB')).astype(int)
    gray = a.mean(axis=2)
    sat = a.max(axis=2) - a.min(axis=2)
    ink = (gray < ink_thr) | (sat > 40)
    ink[:top, :] = False; ink[bottom:, :] = False
    lines = [l for l in lines_of(words) if l['box'][1] >= top and l['box'][3] <= bottom]
    txt = np.zeros_like(ink)
    for l in lines:
        x0, y0, x1, y1 = l['box']; txt[max(0, y0 - 3):y1 + 3, max(0, x0 - 3):x1 + 3] = True
    boxes = components(ink & ~txt, cell, grow, min_side)
    # merge boxes that overlap
    changed = True
    while changed:
        changed = False
        for i in range(len(boxes)):
            for j in range(i + 1, len(boxes)):
                p, q = boxes[i], boxes[j]
                if p[0] < q[2] and q[0] < p[2] and p[1] < q[3] and q[1] < p[3]:
                    boxes[i] = [min(p[0], q[0]), min(p[1], q[1]), max(p[2], q[2]), max(p[3], q[3])]
                    del boxes[j]; changed = True; break
            if changed:
                break
    return lines, sorted(boxes, key=lambda b: (b[1] // 40, b[0]))


def _norm(s):
    return re.sub(r'[^a-z0-9]', '', s.lower().replace('|', 'i'))


def _sim(a, b):
    import difflib
    return difflib.SequenceMatcher(None, _norm(a), _norm(b)).ratio()


def find_text(words, phrase, used, below=0):
    """Box of the words that spell `phrase` (fuzzy; may wrap to the next line), or None."""
    toks = phrase.split()
    best = None
    for i, w in enumerate(words):
        if i in used or w[1][1] < below or _sim(w[0], toks[0]) < 0.7 and not (len(toks) > 1 and _sim(w[0], toks[0] + toks[1]) > 0.8):
            continue
        idx, box = [i], list(w[1])
        joined = _norm(w[0])
        for t in toks[1:]:
            if _norm(t) in joined and len(joined) >= len(_norm(phrase)):
                break
            h = box[3] - box[1]
            cand = [(j, v) for j, v in enumerate(words) if j not in idx and j not in used and _sim(v[0], t) >= 0.7
                    and ((abs(v[1][1] - w[1][1]) < h * 0.8 and 0 <= v[1][0] - box[2] < h * 3)          # same line, to the right
                         or (0 < v[1][1] - box[3] < h * 1.2 and abs((v[1][0] + v[1][2]) / 2 - (box[0] + box[2]) / 2) < h * 6))]   # wrapped
            if not cand:
                break
            j, v = min(cand, key=lambda c: abs(c[1][1][0] - box[2]) + abs(c[1][1][1] - box[1]))
            idx.append(j); joined += _norm(v[0])
            box = [min(box[0], v[1][0]), min(box[1], v[1][1]), max(box[2], v[1][2]), max(box[3], v[1][3])]
        score = _sim(joined, phrase)
        if score >= 0.7 and (best is None or score > best[0] + 0.05 or (abs(score - best[0]) <= 0.05 and box[1] < best[2][1])):
            best = (score, idx, box)
    if best:
        used.update(best[1])
        return best[2]
    return None


def ink_box(im, box, erase=(), thr=215, pad=10):
    """Trim `box` to the picture ink inside it (light ruled lines and erased text ignored)."""
    x0, y0, x1, y1 = [int(v) for v in box]
    a = np.asarray(im.crop((x0, y0, x1, y1)).convert('RGB')).astype(int)
    m = (a.mean(axis=2) < thr) | ((a.max(axis=2) - a.min(axis=2)) > 45)
    for e in erase:
        ex0, ey0, ex1, ey1 = [int(v) for v in e]
        m[max(0, ey0 - y0 - 3):max(0, ey1 - y0 + 3), max(0, ex0 - x0 - 3):max(0, ex1 - x0 + 3)] = False
    if m.size == 0:
        return None
    # ruled lines across the whole cell are not picture
    def thin(flags, width=6):     # only thin runs of full rows/columns are ruled lines (not photos)
        out = np.zeros_like(flags); i = 0
        while i < len(flags):
            if flags[i]:
                j = i
                while j < len(flags) and flags[j]:
                    j += 1
                if j - i <= width:
                    out[i:j] = True
                i = j
            else:
                i += 1
        return out
    rows = thin(m.mean(axis=1) > 0.85); cols = thin(m.mean(axis=0) > 0.85)
    m[rows, :] = False; m[:, cols] = False
    # thin dotted borders: drop rows/cols with very little ink at the edges
    ys, xs = np.where(m)
    if len(xs) < 200:
        return None
    rc = m.sum(axis=1); cc = m.sum(axis=0)
    ys_ok = np.where(rc > max(2, rc.max() * 0.02))[0]; xs_ok = np.where(cc > max(2, cc.max() * 0.02))[0]
    return [max(x0, x0 + xs_ok.min() - pad), max(y0, y0 + ys_ok.min() - pad), min(x1, x0 + xs_ok.max() + pad), min(y1, y0 + ys_ok.max() + pad)]
