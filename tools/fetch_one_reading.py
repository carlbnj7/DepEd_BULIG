#!/usr/bin/env python3
"""Fetch pinned local runtime assets. No runtime CDN or paid speech API.
Run from any directory: python3 tools/fetch_one_reading.py
The deployment ZIP already includes these assets.
"""
import hashlib, io, json, pathlib, tarfile, urllib.request
root=pathlib.Path(__file__).resolve().parents[1]/'public/assets/one-reading'
manifest=json.loads((root/'assets-manifest.json').read_text())
bundle=None
for name,expected in manifest['files'].items():
    dest=root/name
    if dest.exists() and hashlib.sha256(dest.read_bytes()).hexdigest()==expected['sha256']:
        continue
    if name.startswith('vendor/'):
        if bundle is None:
            url='https://registry.npmjs.org/@xenova/transformers/-/transformers-2.17.2.tgz'
            with urllib.request.urlopen(url,timeout=180) as response:
                bundle=tarfile.open(fileobj=io.BytesIO(response.read()))
        data=bundle.extractfile('package/dist/'+dest.name).read()
    else:
        rel=name.removeprefix('models/whisper-tiny.en/')
        url='https://huggingface.co/Xenova/whisper-tiny.en/resolve/'+manifest['model_revision']+'/'+rel
        with urllib.request.urlopen(url,timeout=180) as response:
            data=response.read()
    if len(data)!=expected['bytes'] or hashlib.sha256(data).hexdigest()!=expected['sha256']:
        raise SystemExit('Checksum mismatch: '+name)
    dest.parent.mkdir(parents=True,exist_ok=True)
    temp=dest.with_name(dest.name+'.tmp');temp.write_bytes(data);temp.replace(dest)
    print('Verified',name)
print('Local word checker assets ready.')
