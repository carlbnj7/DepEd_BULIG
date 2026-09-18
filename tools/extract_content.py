"""Reproducible source extraction. Requires PyMuPDF. No generated replacement images."""
from pathlib import Path
import fitz,json,re,hashlib,shutil
ROOT=Path(__file__).resolve().parents[1]
SOURCE=ROOT/'storage/level1-original.pdf'
doc=fitz.open(SOURCE)
asset=ROOT/'public/assets/module'; asset.mkdir(parents=True,exist_ok=True)
pages=[];seen={}
for n,p in enumerate(doc,1):
    images=[]
    for info in p.get_image_info(xrefs=True):
        x=info['xref']
        if not x:continue
        if x not in seen:
            raw=doc.extract_image(x); name=f'image-{x}.{raw["ext"]}';(asset/name).write_bytes(raw['image']);seen[x]=name
        images.append({'path':'assets/module/'+seen[x],'bbox':list(info['bbox']),'xref':x,'width':info['width'],'height':info['height']})
    p.get_pixmap(matrix=fitz.Matrix(1.25,1.25)).save(str(asset/f'page-{n:03}.jpg'))
    pages.append({'page':n,'text':p.get_text(sort=True),'image_path':f'assets/module/page-{n:03}.jpg','images':images})
(ROOT/'database/source-pages.json').write_text(json.dumps(pages,ensure_ascii=False,indent=2))
print(f'Preserved {len(pages)} pages and {len(seen)} original embedded images.')
