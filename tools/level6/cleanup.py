"""One-off clean-up of the Grade 4–6 drafts before the hand check (run once after draft.py)."""
import re, sys, os
HERE = os.path.dirname(__file__)
for g in sys.argv[1:]:
    p = os.path.join(HERE, 'script', f'g{g}.txt')
    lines = open(p).read().split('\n')
    out, prev_comment = [], False
    for i, l in enumerate(lines):
        s = l.strip()
        bare = s.lstrip('¶ ').strip()
        # second line of a SKILL heading
        if prev_comment and bare and bare.isupper() and not s.startswith(('q ', 'ins', 'read', '|', '-', '===', 'pics')):
            out.append('# ' + bare); continue
        if re.match(r'^(¶ )?(SKILLS?|SLILL)\b', s) or re.match(r'^read: (Inferences|INFERENCES|Making Inferences|Comprehension|COMPREHENSION|SELECTION)$', s):
            out.append('# ' + bare); prev_comment = True; continue
        prev_comment = s.startswith('#')
        # "put them in his pockets" mistaken for directions
        if s.startswith('ins:') and re.match(r'^[a-z]', s[4:].strip()):
            out.append(s[4:].strip()); continue
        if re.match(r'^-\s*$', s) or re.search(r'TIME STARTED|SPEED RATE|TIME FINISHED', s):
            continue
        m = re.search(r'\((\d{2,4})\s*words\)', s, re.I) or re.match(r'^¶?\s*(\d{2,4})\s+WORDS$', s, re.I)
        if m:
            rest = re.sub(r'\(\d{2,4}\s*words\)|^¶?\s*\d{2,4}\s+WORDS$', '', l, flags=re.I).rstrip()
            if rest.strip():
                out.append(rest)
            out.append(f'speed: {m.group(1)}'); continue
        out.append(l)
    open(p, 'w').write('\n'.join(out))
    print('cleaned', p)
