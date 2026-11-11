"""Print a compact one-line-per-card proof of a grade (for checking against the module pages)."""
import json, sys, os
D = json.load(open(os.path.join(os.path.dirname(__file__), '..', '..', 'database', 'level7-cards.json')))['cards']
for k, d in D.items():
    if not k.startswith(sys.argv[1] + ':'):
        continue
    print(f'##### {k} | {d["instruction"]}')
    for c in d['cards']:
        if c['title'] == 'Read':
            print(f'  [Read] «{c.get("heading","")}» img{len(c["images"])} {("<"+c["lead"]+"> ") if c.get("lead") else ""}{("words:"+",".join(c["words"])+" ") if c.get("words") else ""}{("speed "+str(c["speed"])+" ") if c.get("speed") else ""}| {c["text"][:90]} … {c["text"][-60:]} ({len(c["text"])}ch)')
        else:
            print(f'  [{c["title"]}] {("<"+c["lead"]+"> ") if c.get("lead") else ""}{c["text"]}  ‖ ' + ' ‖ '.join(c['choices']))
