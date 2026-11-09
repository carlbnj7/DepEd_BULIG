"""List the choice and matching cards that still have no answer, from the built cards (run tools/jhs/build.py --no-pages first)."""
import json, os, sys
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
cards = json.load(open(os.path.join(ROOT, 'database', 'jhs-cards.json')))['cards']
keys = json.load(open(os.path.join(ROOT, 'database', 'answer-keys-jhs.json')))['keys']
mids = set(sys.argv[1:])
seen = set()
for cid, s in cards.items():
    lv, g, pos, ap = cid.split(':')
    kk = next((v for k, v in keys.items() if k.startswith(f'{lv}:{g}:{pos}:') and k.endswith(f':{ap}')), {})
    shown = False
    for n, c in enumerate(s['cards'], 1):
        ref = c.get('ref', '')
        if not ref or ref.split()[0] not in mids or str(n) in kk or ref in seen: continue
        seen.add(ref)
        if not shown:
            read = next((x['text'] for x in s['cards'] if x['title'] == 'Read'), '')
            print(f"\n[{ref.rsplit('.', 1)[0]}] {s['instruction'][:160]}")
            if read: print('   READ: ' + ' '.join(read.split())[:int(os.environ.get('RL', '400'))])
            shown = True
        if c['title'] == 'Matching':
            print(f"  {ref.split()[1]}  {' / '.join(c['text'].split(chr(10))[1:])}  ||  {' / '.join(c['choices'])}")
        else:
            print(f"  {ref.split()[1]} {c['text'][:220]}  ||  {'  '.join(x[:70] for x in c['choices'])}")
