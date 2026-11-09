"""List the write cards (and unkeyed choice cards) of a module with their worksheet text, to add word banks and keys."""
import json, os, sys
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
cards = json.load(open(os.path.join(ROOT, 'database', 'jhs-cards.json')))['cards']
keys = json.load(open(os.path.join(ROOT, 'database', 'answer-keys-jhs.json')))['keys']
mid = sys.argv[1]; seen = set()
for cid, s in cards.items():
    lv, g, pos, ap = cid.split(':')
    kk = next((v for k, v in keys.items() if k.startswith(f'{lv}:{g}:{pos}:') and k.endswith(f':{ap}')), {})
    rows = [(n, c) for n, c in enumerate(s['cards'], 1) if c.get('ref', '').startswith(mid + ' ') and str(n) not in kk]
    if not rows or rows[0][1]['ref'] in seen: continue
    for _, c in rows: seen.add(c['ref'])
    read = next((x['text'] for x in s['cards'] if x['title'] == 'Read'), '')
    print(f"\n## {rows[0][1]['ref'].split()[1].rsplit('.', 1)[0]} | {s['instruction'][:140]}")
    if read: print('   R: ' + ' '.join(read.split())[-int(os.environ.get('RL', '350')):])
    for n, c in rows:
        print(f"  {c['ref'].split()[1]} {' '.join(c['text'].split())[:150]}" + (f"  || {' | '.join(c['choices'])}" if c['choices'] else ''))
