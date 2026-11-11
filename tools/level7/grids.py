"""Read a printed letter puzzle (word search / crossword grid) from the PDF text positions:
one string per row, '.' for an empty square. Used once to type the grids into worksheets.py."""
import sys, pymupdf


def chars(page, clip):
    out = []
    for b in page.get_text('rawdict', clip=clip)['blocks']:
        for l in b.get('lines', []):
            for sp in l['spans']:
                for c in sp['chars']:
                    if c['c'].strip():
                        x0, y0, x1, y1 = c['bbox']
                        out.append((c['c'], (x0 + x1) / 2, (y0 + y1) / 2))
    return out


def grid(page, clip, cols=None):
    cs = chars(page, clip)
    ys = sorted(set(round(c[2]) for c in cs))
    rows = []
    for y in ys:
        if not rows or y - rows[-1][-1] > 6:
            rows.append([y])
        else:
            rows[-1].append(y)
    xs = sorted(c[1] for c in cs)
    colc = []
    for x in xs:
        if not colc or x - colc[-1][-1] > 8:
            colc.append([x])
        else:
            colc[-1].append(x)
    cx = [sum(c) / len(c) for c in colc]
    out = []
    for r in rows:
        line = ['.'] * len(cx)
        for ch, x, y in cs:
            if min(r) - 1 <= round(y) <= max(r) + 1:
                k = min(range(len(cx)), key=lambda i: abs(cx[i] - x))
                line[k] = ch
        out.append(''.join(line))
    # a column that is empty in most rows is only wide letter spacing
    keep = [k for k in range(len(cx)) if sum(r[k] == '.' for r in out) < 0.6 * len(out)]
    return [''.join(r[k] for k in keep) for r in out]


if __name__ == '__main__':
    g, pn, x0, y0, x1, y1 = sys.argv[1:7]
    page = pymupdf.open(f'storage/level7/grade-{g}.pdf')[int(pn) - 1]
    for r in grid(page, pymupdf.Rect(*map(float, (x0, y0, x1, y1)))):
        print(r)
