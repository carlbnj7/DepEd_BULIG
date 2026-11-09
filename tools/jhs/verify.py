"""Print each keyed choice card as "question -> keyed answer (other choices)" so the keys can be checked by reading."""
import json, os, sys
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
cards = json.load(open(os.path.join(ROOT, 'database', 'jhs-cards.json')))['cards']
keys = json.load(open(os.path.join(ROOT, 'database', 'answer-keys-jhs.json')))['keys']
want = sys.argv[1]   # e.g. 6:7
for kid, kk in keys.items():
    lv, g, pos, ph, ap = kid.split(':')
    if f'{lv}:{g}' != want: continue
    cs = cards.get(f'{lv}:{g}:{pos}:{ap}', {}).get('cards', [])
    for n, k in kk.items():
        c = cs[int(n) - 1]
        if k['t'] == 'choice':
            print(f"{pos}.{ap}.{n} {c['text'][:110]} -> {k['a']}   [{' | '.join(x for x in c['choices'] if x != k['a'])}]")
