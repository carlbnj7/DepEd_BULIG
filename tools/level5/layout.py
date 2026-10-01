"""Turn one Level 5 module page into native content: text lines plus separately cut pictures,
arranged in rows the way the page prints them (so "1. Ball [picture]  a. A toy with wheels"
stay side by side).  Crossword-style grids, which cannot be retyped, are kept as one picture.
"""
import os, re
import numpy as np
import pymupdf
from PIL import Image

NOISE = re.compile(r'^(Name|Date|Score|Section|Teacher|Grade and Section)\s*:?\s*_*\s*$|^PAGE\s|MERGEFORMAT|^[ivx]+$', re.I)


def _trim(im, pad=6, thr=244):
    a = np.asarray(im.convert('L'))
    ys, xs = np.where(a < thr)
    if not len(xs):
        return None
    return im.crop((max(0, xs.min() - pad), max(0, ys.min() - pad), min(im.width, xs.max() + pad + 1), min(im.height, ys.max() + pad + 1)))


def raw_image(doc, xref):
    """The embedded picture at its own resolution, transparency flattened onto white."""
    pix = pymupdf.Pixmap(doc, xref)
    if pix.colorspace and pix.colorspace.n not in (1, 3):
        pix = pymupdf.Pixmap(pymupdf.csRGB, pix)
    if pix.alpha:
        im = Image.frombytes('RGBA', [pix.width, pix.height], pix.samples)
        bg = Image.new('RGB', im.size, 'white'); bg.paste(im, mask=im.split()[3]); im = bg
    else:
        im = Image.frombytes('RGB' if pix.n == 3 else 'L', [pix.width, pix.height], pix.samples)
    sm = doc.xref_get_key(xref, 'SMask')
    if sm[0] == 'xref':
        m = pymupdf.Pixmap(doc, int(sm[1].split()[0]))
        mi = Image.frombytes('L', [m.width, m.height], m.samples).resize(im.size)
        bg = Image.new('RGB', im.size, 'white'); bg.paste(im.convert('RGB'), mask=mi); im = bg
    return im


def strip_cover(p, s_):
    return (s_[2] - s_[0]) * (s_[3] - s_[1]) >= 0.9 * (p[2] - p[0]) * (p[3] - p[1])


def _overlap(a, b, gap=0):
    return a[0] < b[2] + gap and b[0] < a[2] + gap and a[1] < b[3] + gap and b[1] < a[3] + gap


def _merge(boxes, gap=0):
    boxes = [list(b) for b in boxes]
    changed = True
    while changed:
        changed = False
        for i in range(len(boxes)):
            for j in range(i + 1, len(boxes)):
                if _overlap(boxes[i], boxes[j], gap):
                    a, b = boxes[i], boxes[j]
                    boxes[i] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]
                    boxes.pop(j); changed = True
                    break
            if changed:
                break
    return boxes


def grid_regions(page, y0, y1):
    """Areas drawn as many small square cells (crosswords, letter grids)."""
    cells = []
    for d in page.get_drawings():
        for it in d['items']:
            if it[0] == 're':
                r = it[1]
                if 8 < r.width < 45 and 8 < r.height < 45 and abs(r.width - r.height) < 8 and r.y0 >= y0 and r.y1 <= y1:
                    cells.append([r.x0, r.y0, r.x1, r.y1])
    regions = _merge(cells, gap=3)
    out = []
    for g in regions:
        n = sum(1 for c in cells if _overlap(c, g))
        if n >= 20:
            out.append(g)
    return out


def page_layout(doc, pic_doc, pn, y0, y1, footer, out_dir, rel_dir, stem, ocr=None):
    page = doc[pn - 1]
    W = page.rect.width
    y1 = min(y1, footer)
    # ---- text lines -------------------------------------------------------------
    lines, sizes = [], []
    for b in page.get_text('dict')['blocks']:
        if b['type'] != 0:
            continue
        for l in b['lines']:
            spans = [s for s in l['spans'] if s['text'].strip()]
            if not spans:
                continue
            t = re.sub(r'\s+', ' ', ''.join(s['text'] for s in l['spans'])).strip()
            x0, ly0, x1, ly1 = l['bbox']
            if ly0 < y0 - 1 or ly1 > y1 + 2 or NOISE.match(t) or re.fullmatch(r'\d{1,3}', t) and ly0 < 60:
                continue
            size = max(s['size'] for s in spans)
            bold = any(s['flags'] & 16 or 'Bold' in s['font'] for s in spans)
            lines.append(dict(t=t, box=[x0, ly0, x1, ly1], size=size, bold=bold))
            sizes.append(size)
    body = float(np.median(sizes)) if sizes else 12
    # ---- fill-in blanks drawn as lines: put "______" into the sentence where the module draws them ----
    segs = []
    for d in page.get_drawings():
        for it in d['items']:
            if it[0] == 'l':
                a, b = it[1], it[2]
                if abs(a.y - b.y) < 1.5 and abs(b.x - a.x) > 14:
                    segs.append((min(a.x, b.x), max(a.x, b.x), a.y))
            elif it[0] == 're':
                r = it[1]
                if r.height < 2.2 and r.width > 14:
                    segs.append((r.x0, r.x1, r.y1))
    if segs:
        words = page.get_text('words')
        owner = {}
        for sg in segs:                      # each drawn blank belongs to one line: the nearest on its baseline
            best = None
            for i, l in enumerate(lines):
                x0, ly0, x1, ly1 = l['box']
                if not (ly0 + (ly1 - ly0) * 0.5 <= sg[2] <= ly1 + 4):
                    continue
                if any(w[0] < sg[1] - 2 and sg[0] + 2 < w[2] and w[1] >= ly0 - 1 and w[3] <= ly1 + 1 for w in words):
                    best = None; break   # underline under words
                dist = 0 if x0 - 2 <= sg[0] <= x1 + 2 else min(abs(sg[0] - x1), abs(x0 - sg[1]))
                if dist < 160 and (best is None or dist < best[0] or (dist == best[0] and x0 < lines[best[1]]['box'][0])):
                    best = (dist, i)
            if best:
                owner.setdefault(best[1], []).append(sg)
        for i, mine in owner.items():
            l = lines[i]
            x0, ly0, x1, ly1 = l['box']
            ws = [w for w in words if w[1] >= ly0 - 1 and w[3] <= ly1 + 1 and w[0] >= x0 - 1 and w[2] <= x1 + 1]
            if not ws:
                continue
            toks = sorted([(w[0], w[4]) for w in ws] + [(sg[0], '______') for sg in mine])
            l['t'] = re.sub(r'\s+([.,?!:;])', r'\1', ' '.join(t for _, t in toks))
            l['box'] = [min(x0, min(sg[0] for sg in mine)), ly0, max(x1, max(sg[1] for sg in mine)), ly1]
    # ---- pictures -----------------------------------------------------------------
    pics, xrefs = [], {}
    for info in page.get_image_info(xrefs=True):
        x0, py0, x1, py1 = info['bbox']
        x0, py0, x1, py1 = max(x0, 0), max(py0, y0), min(x1, W), min(py1, y1)
        if x1 - x0 < 8 or py1 - py0 < 8:
            continue
        if (x1 - x0) > W * 0.92 and (py1 - py0) > page.rect.height * 0.75:
            continue                                    # full-page background
        pics.append([x0, py0, x1, py1]); xrefs[tuple(pics[-1])] = info['xref']
    # Some pages print lines of text as pictures ("text strips"): read them as text instead.
    def colourful(p):
        try:
            im = raw_image(doc, xrefs[tuple(p)]).convert('RGB')
        except Exception:
            return True
        im.thumbnail((200, 200))
        c = np.asarray(im).astype(int)
        return (c.max(2) - c.min(2)).mean() > 14 or (np.asarray(im.convert('L')) < 200).mean() > 0.45
    # lines printed as pictures are grey/black lettering on white; real pictures are colourful
    strips = [p for p in pics if (p[3] - p[1]) < 50 and (p[2] - p[0]) >= 14 and not colourful(p)]
    pics = [p for p in pics if p not in strips]
    # a large box that mostly holds text strips is only a frame behind them
    def strip_share(p):
        area = (p[2] - p[0]) * (p[3] - p[1])
        return sum((min(p[2], s_[2]) - max(p[0], s_[0])) * (min(p[3], s_[3]) - max(p[1], s_[1])) for s_ in strips if _overlap(p, s_)) / max(area, 1)
    pics = [p for p in pics if not ((p[2] - p[0]) * (p[3] - p[1]) > 3000 and sum(1 for s_ in strips if _overlap(p, s_)) >= 2 and strip_share(p) > 0.3)]
    if strips and ocr is not None:
        # Read the lines printed as pictures from the rendered page, with real pictures blanked out.
        zoom = 300 / 72
        pix = page.get_pixmap(dpi=300, clip=pymupdf.Rect(0, y0, W, y1))
        im = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
        from PIL import ImageDraw
        dr = ImageDraw.Draw(im)
        for p_ in pics:
            if True:
                dr.rectangle([(p_[0]) * zoom, (p_[1] - y0) * zoom, p_[2] * zoom, (p_[3] - y0) * zoom], fill='white')
        for l in lines:        # real text is already known
            dr.rectangle([l['box'][0] * zoom - 2, (l['box'][1] - y0) * zoom - 2, l['box'][2] * zoom + 2, (l['box'][3] - y0) * zoom + 2], fill='white')
        # clean reading of each wide text picture from its own (sharper) image
        clean = {}
        for s_ in strips:
            if s_[2] - s_[0] < 40:
                continue
            r = raw_image(doc, xrefs[tuple(s_)]).convert('L')
            f = max(1, round(120 / max(r.height, 1)))
            r = r.resize((r.width * f, r.height * f))
            pad = Image.new('L', (r.width + 60, r.height + 60), 255); pad.paste(r, (30, 30))
            t = ' '.join(ocr(pad, 7)).strip()
            if len(re.findall(r'[A-Za-z]{3,}', t)) >= 1 and len(re.sub(r'[^A-Za-z]', '', t)) >= 4:
                clean[tuple(s_)] = t
        used = set()
        for t, bx in ocr(im, 'tsv'):
            box = [bx[0] / zoom, bx[1] / zoom + y0, bx[2] / zoom, bx[3] / zoom + y0]
            if not any(_overlap(box, s_, 2) for s_ in strips):
                continue                                # only where text pictures are printed
            if len(re.findall(r'[A-Za-z0-9]', t)) < 2:
                continue
            cy = (box[1] + box[3]) / 2
            cands = [k for k in clean if k not in used and k[1] - 2 <= cy <= k[3] + 2 and k[0] < box[2] and box[0] < k[2]]
            if cands:
                cands.sort(key=lambda k: k[0])
                better = ' '.join(clean[k] for k in cands)
                m = re.match(r'\s*(\d{1,2}\s*[.)]|[A-Da-d]\s*[.)])\s', t)
                if m and not re.match(r'\s*(\d{1,2}|[A-Da-d])\s*[.)]', better):
                    better = m.group(1).replace(' ', '') + ' ' + better.lstrip('. ')
                t = better; used.update(cands)
                box = [min(box[0], min(k[0] for k in cands)), box[1], max(box[2], max(k[2] for k in cands)), box[3]]
            t = re.sub(r'(?<![A-Za-z])(Cc|cc|CC)\)', 'c)', t)
            lines.append(dict(t=t, box=box, size=body, bold=False, strip=True))
        for k, t in clean.items():
            if k not in used and not any(_overlap(list(k), l['box'], -2) for l in lines):
                lines.append(dict(t=t, box=list(k), size=body, bold=False, strip=True))
    # join pieces of one printed line (e.g. a question number and its question)
    lines.sort(key=lambda l: (l['box'][1], l['box'][0]))
    joined = []
    for l in sorted(lines, key=lambda l: (round((l['box'][1] + l['box'][3]) / 8), l['box'][0])):
        prev = joined[-1] if joined else None
        if prev and abs((prev['box'][1] + prev['box'][3]) - (l['box'][1] + l['box'][3])) < 10 and 0 <= l['box'][0] - prev['box'][2] < 10 and (prev.get('strip') or l.get('strip')):
            prev['t'] = (prev['t'] + ' ' + l['t']).strip(); prev['box'] = [prev['box'][0], min(prev['box'][1], l['box'][1]), l['box'][2], max(prev['box'][3], l['box'][3])]
        else:
            joined.append(l)
    lines = joined
    pics = _merge(pics, gap=1)
    grids = grid_regions(page, y0, y1)
    grids = _merge(grids + [p for p in pics if any(_overlap(p, g) for g in grids)])
    pics = [p for p in pics if not any(_overlap(p, g) for g in grids)]
    items = []
    ppage = pic_doc[pn - 1]
    k = 0
    for box, keep_text in [(p, False) for p in pics] + [(g, True) for g in grids]:
        src_page = page if keep_text else ppage
        pix = src_page.get_pixmap(dpi=200, clip=pymupdf.Rect(box))
        im = _trim(Image.frombytes('RGB', [pix.width, pix.height], pix.samples))
        if im is None or im.width < 20 or im.height < 20:
            continue
        a = np.asarray(im.convert('L'))
        if a.std() < 6:
            continue                                    # blank or plain box
        c = np.asarray(im).astype(int)
        if ((a > 60) & (a < 120)).mean() > 0.6 and (c.max(2) - c.min(2)).mean() < 8:
            continue                                    # stray grey drop-shadow
        im.thumbnail((700, 700))
        k += 1
        name = f'{stem}-{k}.webp'
        im.save(os.path.join(out_dir, name), 'WEBP', quality=82)
        items.append(dict(t='img', src=f'{rel_dir}/{name}', box=box, cap='', grid=keep_text))
    # text inside a kept grid is part of that picture
    lines = [l for l in lines if not any(_overlap(l['box'], g, -1) for g in grids)]
    # captions: a short line just under (or over) a picture, horizontally inside it
    for it in items:
        if it['grid']:
            continue
        x0, py0, x1, py1 = it['box']
        for l in lines:
            if l.get('used') or len(l['t']) > 32 or len(re.findall(r'[A-Za-z]', l['t'])) < 2:
                continue
            lx = (l['box'][0] + l['box'][2]) / 2
            below = 0 <= l['box'][1] - py1 <= 16
            if x0 - 6 <= lx <= x1 + 6 and below and (l['box'][2] - l['box'][0]) <= (x1 - x0) + 40:
                it['cap'] = (it['cap'] + ' ' + l['t']).strip(); l['used'] = True
    for l in lines:
        if not l.get('used'):
            kind = 'h' if l['size'] >= body * 1.25 else ('b' if l['bold'] else '')
            items.append(dict(t='text', s=l['t'], k=kind, box=l['box']))
    # ---- rows: elements that share vertical space; columns inside a row by x ----------
    items.sort(key=lambda i: (i['box'][1], i['box'][0]))
    rows = []
    for it in items:
        b = it['box']
        for r in rows[-3:]:
            top, bot = r['top'], r['bot']
            ov = min(bot, b[3]) - max(top, b[1])
            if ov > min(b[3] - b[1], bot - top) * 0.45:
                r['items'].append(it); r['top'] = min(top, b[1]); r['bot'] = max(bot, b[3]); break
        else:
            rows.append(dict(top=b[1], bot=b[3], items=[it]))
    out_rows = []
    for r in rows:
        cols = []
        for it in sorted(r['items'], key=lambda i: i['box'][0]):
            for c in cols:
                if c['kind'] == it['t'] and it['box'][0] < c['x1'] - 4 and c['x0'] < it['box'][2] - 4:
                    c['items'].append(it); c['x1'] = max(c['x1'], it['box'][2]); break
            else:
                cols.append(dict(x0=it['box'][0], x1=it['box'][2], items=[it], kind=it['t']))
        cols.sort(key=lambda c: (c['x0'] + c['x1']) / 2)
        row = []
        for c in cols:
            row.append([({'t': 'img', 'src': i['src'], 'cap': i['cap'], 'grid': i['grid']} if i['t'] == 'img' else {'t': 'text', 's': i['s'], 'k': i['k']})
                        for i in sorted(c['items'], key=lambda i: i['box'][1])])
        out_rows.append(row)
    # a wrapped continuation ("heavy something is") belongs to the line above it
    merged = []
    for row in out_rows:
        only = row[0][0] if len(row) == 1 and len(row[0]) == 1 else None
        if only and only['t'] == 'text' and re.match(r'[a-z(]', only['s']) and not re.match(r'[a-d][.)]\s', only['s']) and merged:
            last = merged[-1][-1][-1]
            if last['t'] == 'text' and not re.search(r'[.?!:]$', last['s']):
                last['s'] = last['s'] + ' ' + only['s']; continue
        merged.append(row)
    out_rows = merged
    # OCR lines that only repeat picture captions
    caps = {w.lower() for i in items if i['t'] == 'img' for w in re.findall(r'[A-Za-z]+', i['cap'])}
    if caps:
        out_rows = [[[it for it in col if not (it['t'] == 'text' and re.findall(r'[A-Za-z]+', it['s']) and all(w.lower() in caps for w in re.findall(r'[A-Za-z]+', it['s'])))] for col in row] for row in out_rows]
        out_rows = [[c for c in row if c] for row in out_rows]
        out_rows = [r for r in out_rows if r]
    text_lines = [l['t'] for l in lines if not l.get('used')] + [i['cap'] for i in items if i['t'] == 'img' and i['cap']]
    pictures = [i['src'] for i in items if i['t'] == 'img']
    return out_rows, pictures, [l['t'] for l in sorted(lines, key=lambda l: (l['box'][1], l['box'][0]))]
