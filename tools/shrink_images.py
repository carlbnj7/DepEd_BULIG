#!/usr/bin/env python3
"""Makes BULIG's PNG and JPG pictures smaller without changing how they look or their file names.

    python3 tools/shrink_images.py          shrink and report
    python3 tools/shrink_images.py --dry    only report what it would save

Rules (a file is replaced only when it gets at least 10% smaller):
  avatars        400 x 400 (shown at most 120 px wide), 256 colours
  bulig-logo.png 820 px wide (shown about 200 px wide; enough for printed certificates), 256 colours
  JPG pictures   at most 1600 px wide, quality 80, progressive
  other PNGs     same size, saved again with better compression (no colour change)
App icons in assets/brand are left alone. Needs Python 3 with Pillow.
"""
import glob, io, os, sys
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ASSETS = os.path.join(ROOT, 'public', 'assets')
DRY = '--dry' in sys.argv


def png_quantized(im):
    b = io.BytesIO()
    im.quantize(colors=256, method=Image.Quantize.FASTOCTREE if im.mode == 'RGBA' else Image.Quantize.MEDIANCUT,
                dither=Image.Dither.FLOYDSTEINBERG).save(b, 'PNG', optimize=True)
    return b.getvalue()


def shrink(path):
    rel = os.path.relpath(path, ROOT)
    im = Image.open(path)
    im.load()
    if rel.startswith('public/assets/avatars/'):
        data = png_quantized(im.convert('RGB').resize((400, 400), Image.LANCZOS))
    elif rel == 'public/assets/bulig-logo.png':
        w = min(820, im.width)
        data = png_quantized(im.convert('RGBA').resize((w, round(im.height * w / im.width)), Image.LANCZOS))
    elif rel.lower().endswith(('.jpg', '.jpeg')):
        if im.width > 1600:
            im = im.resize((1600, round(im.height * 1600 / im.width)), Image.LANCZOS)
        b = io.BytesIO()
        im.convert('RGB').save(b, 'JPEG', quality=80, optimize=True, progressive=True)
        data = b.getvalue()
    else:
        b = io.BytesIO()
        im.save(b, 'PNG', optimize=True)
        data = b.getvalue()
    return data


def main():
    files = [f for f in glob.glob(os.path.join(ASSETS, '**', '*'), recursive=True)
             if f.lower().endswith(('.png', '.jpg', '.jpeg')) and '/brand/' not in f and '/uploads/' not in f]
    before = after = changed = 0
    for f in sorted(files):
        old = os.path.getsize(f)
        try:
            data = shrink(f)
        except Exception as e:
            print('skipped', os.path.relpath(f, ROOT), e)
            continue
        before += old
        if len(data) < old * 0.9:
            after += len(data)
            changed += 1
            if not DRY:
                with open(f, 'wb') as fh:
                    fh.write(data)
        else:
            after += old
    print(f'{changed} of {len(files)} pictures {"would shrink" if DRY else "shrunk"}: {before / 1048576:.1f} MB -> {after / 1048576:.1f} MB')


if __name__ == '__main__':
    main()
