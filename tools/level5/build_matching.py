"""Rebuild Level 5 matching cards from the page geometry: numbered items (Column A) and lettered
choices (Column B), each with the picture printed on its own line. Pictures are cut from the page
with text removed. Output merged into database/level5-cards.json by build_cards.py."""
import json, os, re
import numpy as np
import pymupdf
from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = os.path.join(ROOT, 'public', 'assets', 'images', 'level5')


def trim(im, pad=6):
    a = np.asarray(im.convert('L')); ys, xs = np.where(a < 238)
    if not len(xs):
        return None
    return im.crop((max(0, xs.min() - pad), max(0, ys.min() - pad), min(im.width, xs.max() + pad), min(im.height, ys.max() + pad)))


def labels(page, y0, y1):
    """Printed pieces of text: words on one line, split where a wide gap separates columns."""
    ws = sorted(page.get_text('words'), key=lambda w: (w[5], w[6], w[0]))
    lines = {}
    for w in ws:
        lines.setdefault((w[5], w[6]), []).append(w)
    out = []
    for ln in lines.values():
        ln.sort(key=lambda w: w[0])
        seg = [ln[0]]
        for w in ln[1:]:
            gap = w[0] - seg[-1][2]
            if gap > 22 or (gap > 6 and re.fullmatch(r'[a-jA-J][.)]', w[4]) and seg[-1][4][-1:] not in ('.', ',')) :
                out.append(seg); seg = [w]
            else:
                seg.append(w)
        out.append(seg)
    res = []
    for seg in out:
        t = re.sub(r'\s+', ' ', ' '.join(w[4] for w in seg)).strip()
        y = (seg[0][1] + seg[0][3]) / 2
        if t and y0 <= y <= y1:
            res.append(dict(x=seg[0][0], x1=seg[-1][2], y=y, top=min(w[1] for w in seg), bot=max(w[3] for w in seg), t=t))
    return res


def build(g, pn, key, cont=False):
    doc = pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf'))
    page = doc[pn - 1]
    W, H = page.rect.width, page.rect.height
    ls = labels(page, 0, H - 60)
    nums = [l for l in ls if re.match(r'^\d{1,2}\s*[.)]', l['t'])]
    lets = [l for l in ls if re.match(r'^[a-jA-J]\s*[.)]', l['t'])]
    if len(nums) < (1 if cont else 2):
        return None
    # restrict to the block where both columns sit side by side
    xa = np.median([l['x'] for l in nums])
    ny0, ny1 = min(l['top'] for l in nums) - 25, max(l['bot'] for l in nums) + 90
    lets = [l for l in lets if l['x'] > xa + 60 and ny0 <= l['y'] <= ny1 and not re.match(r'^[A-J]\.\s+(Write|Listen|Direction|Instruction)', l['t'])]
    seq = []                                   # Column B letters run a, b, c … in order
    for l in sorted(lets, key=lambda l: l['y']):
        c = l['t'][0].lower()
        if (not seq and c == 'a') or (seq and ord(c) == ord(seq[-1]['t'][0].lower()) + 1):
            seq.append(l)
    lets = seq if len(seq) >= 2 else lets
    top = min(l['top'] for l in nums + lets) - 40
    bot = max(l['bot'] for l in nums + lets) + 60
    xb = min(l['x'] for l in lets) if len(lets) >= 2 else None      # Column B starts at its left-most letter
    def side(x):          # text: B starts at the letters' column
        return 'B' if xb is not None and x >= xb - 12 else 'A'
    def pic_side(p):      # pictures: B only when they start at or right of the letters (or right half when B is pictures only)
        if xb is not None:
            return 'B' if p[0] >= xb + 8 else 'A'
        return 'B' if p[0] > W * 0.5 and not any(abs((p[1] + p[3]) / 2 - (r['top'] + r['bot']) / 2) < 10 and r['x1'] > p[0] for r in nums) else 'A'
    rows = {'A': [], 'B': []}
    for l in sorted(ls, key=lambda l: (l['y'] - (4 if re.match(r'^(\d{1,2}|[a-jA-J])\s*[.)]', l['t']) else 0), l['x'])):
        if not (top <= l['y'] <= bot):
            continue
        s = side(l['x'])
        pat = r'^\d{1,2}\s*[.)]' if s == 'A' else r'^[a-jA-J]\s*[.)]'
        if re.match(pat, l['t']):
            rows[s].append(dict(l, parts=[l['t']], tx=None))
        elif rows[s] and abs(l['y'] - rows[s][-1]['y']) < 4 and s == 'A':
            rows[s][-1]['parts'].append(l['t']); rows[s][-1]['tx'] = rows[s][-1]['tx'] or l['x']   # the word after "1. ____"
        elif rows[s] and 0 < l['y'] - rows[s][-1]['y'] and l['top'] - rows[s][-1]['bot'] < 20 and not re.match(r'^(Column|Instruction|Direction)', l['t'], re.I) \
                and (s == 'B' or abs(l['x'] - (rows[s][-1]['tx'] or rows[s][-1]['x'] + 15)) < 45):
            rows[s][-1]['parts'].append(l['t']); rows[s][-1]['bot'] = l['bot']
        elif s == 'A' and rows['B'] and 0 < l['y'] - rows['B'][-1]['y'] and l['top'] - rows['B'][-1]['bot'] < 20 and not re.match(r'^(Column|Instruction|Direction)', l['t'], re.I):
            rows['B'][-1]['parts'].append(l['t']); rows['B'][-1]['bot'] = l['bot']      # a Column B line that wrapped leftwards
    # pictures with text removed
    clean = pymupdf.open(os.path.join(ROOT, 'storage', 'level5', f'grade-{g}.pdf'))
    cp = clean[pn - 1]; cp.add_redact_annot(cp.rect)
    cp.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_NONE, text=pymupdf.PDF_REDACT_TEXT_REMOVE)
    pics = []
    for info in page.get_image_info():
        x0, y0, x1, y1 = info['bbox']
        if y1 - y0 < 12 or x1 - x0 < 12 or (x1 - x0) > W * .8 or y0 < top - 30 or y1 > bot + 40:
            continue
        pics.append([x0, y0, x1, y1])
    # merge overlapping boxes
    merged = True
    while merged:
        merged = False
        for i in range(len(pics)):
            for j in range(i + 1, len(pics)):
                a, b = pics[i], pics[j]
                ix = min(a[2], b[2]) - max(a[0], b[0]); iy = min(a[3], b[3]) - max(a[1], b[1])
                small = min((a[2] - a[0]) * (a[3] - a[1]), (b[2] - b[0]) * (b[3] - b[1]))
                if ix > 0 and iy > 0 and ix * iy > 0.3 * small:
                    pics[i] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]; pics.pop(j); merged = True; break
            if merged:
                break
    def owner(p):
        cy = (p[1] + p[3]) / 2
        s = pic_side(p)
        if s == 'B' and not rows['B']:
            return 'NEWB'
        cand = rows[s] or rows['A']
        if not cand:
            return None
        return min(cand, key=lambda r: abs(cy - (r['top'] + r['bot']) / 2))
    nob = []
    apics = sorted([p for p in pics if pic_side(p) == 'A'], key=lambda p: p[1])
    in_order = {}
    if rows['A'] and len(apics) == len(rows['A']):      # one picture per item: pair them in order
        in_order = {tuple(p): r for p, r in zip(apics, sorted(rows['A'], key=lambda r: r['y']))}
    for k, p in enumerate(sorted(pics, key=lambda p: (p[1], p[0])), 1):
        r = in_order.get(tuple(p)) or owner(p)
        if r is None:
            continue
        if r == 'NEWB':
            r = dict(t=chr(97 + len(nob)) + '.', parts=[chr(97 + len(nob)) + '.'], top=p[1], bot=p[3]); nob.append(r)
        pix = cp.get_pixmap(dpi=200, clip=pymupdf.Rect(p))
        im = trim(Image.frombytes('RGB', [pix.width, pix.height], pix.samples))
        if im is None or np.asarray(im.convert('L')).std() < 8:
            continue
        im.thumbnail((500, 500))
        name = f'g{g}/m{pn:03d}-{k}.webp'
        im.save(os.path.join(OUT, name), 'WEBP', quality=84)
        r.setdefault('pics', []).append('assets/images/level5/' + name)
    rows['B'] = rows['B'] or nob
    # Column A stops where numbering starts again (questions of a story follow)
    keep = []
    for r in rows['A']:
        n = int(re.match(r'^(\d{1,2})', r['t']).group(1))
        if keep and n != int(re.match(r'^(\d{1,2})', keep[-1]['t']).group(1)) + 1:
            break
        keep.append(r)
    rows['A'] = keep
    if keep:
        limit = max(r['bot'] for r in keep) + 45
        rows['B'] = [r for r in rows['B'] if r['top'] <= limit]
    # an A line that also holds a B choice ("5. Teddy Bear e. A soft toy you can hug")
    for r in rows['A']:
        t = ' '.join(r['parts'])
        m = re.search(r'\s([a-j])\.\s+(.+)$', t)
        if m and not any(b['t'].startswith(m.group(1) + '.') for b in rows['B']):
            r['parts'] = [t[:m.start()]]
            rows['B'].append(dict(t=m.group(1) + '. ' + m.group(2), parts=[m.group(1) + '. ' + m.group(2)], top=r['top'], bot=r['bot']))
    rows['B'].sort(key=lambda r: r['t'][:1].lower())
    def text_of(r, pat):
        t = re.sub(r'_{2,}', '', ' '.join(r['parts']))
        return re.sub(pat, '', t).strip()
    A = [dict(n=re.match(r'^(\d{1,2})', r['t']).group(1), t=text_of(r, r'^\d{1,2}\s*[.)]\s*').strip(' _'), pics=r.get('pics', [])) for r in rows['A']]
    B = [dict(n=re.match(r'^([a-jA-J])', r['t']).group(1), t=text_of(r, r'^[a-jA-J]\s*[.)]\s*'), pics=r.get('pics', [])) for r in rows['B']]
    if len(A) < (1 if cont else 2) or len(B) < (1 if cont else 2):
        return None
    card = dict(title='Matching', text='Column A\n' + '\n'.join(f"{a['n']}. {a['t']}".strip() for a in A),
                images=[dict(src=p, alt=f"{a['n']}. {a['t']}".strip(), label=f"{a['n']}. {a['t']}".strip()) for a in A for p in a['pics']],
                choices=[f"{b['n']}. {b['t']}".strip() for b in B],
                choice_images={f"{b['n']}.": b['pics'][0] for b in B if b['pics']})
    return card


if __name__ == '__main__':
    import sys
    g, pn = int(sys.argv[1]), int(sys.argv[2])
    c = build(g, pn, f'{g}:{pn}')
    print(json.dumps(c, indent=1, ensure_ascii=False)[:1500])
