"""Level 2B screens (same kinds as Level 2A, see build_ui.py) plus:
 split - tap between letters: one gap = onset | rime; several gaps = syllables (slash)
 words - tap each word of a sentence as you read it (hearts, stars, dots, moons)
 'opts' gives each answer part its own choices (for example onset row and rime row).
Writes database/level2b-ui.json."""
import json,re
d=json.load(open('database/level2-cards.json'))['pages']
def cards(k):return d['2b:'+k]['cards']
P={}
def page(k,title,kinds):
    out=[]
    for i,c in enumerate(cards(k)):
        x=kinds(i,c) if callable(kinds) else kinds
        if isinstance(x,str):x={'answer':[x]}
        elif isinstance(x,list):x={'answer':x}
        out.append(x)
    P[k]={'title':title,'cards':out}
def oc(i,c):
    m=re.match(r'Beginning choices: (.*)\s*\n\s*Ending choices: (.*)',c['text'])
    return {'answer':['chip','chip'],'opts':[[w.strip() for w in m.group(1).split(',')],[w.strip() for w in m.group(2).split(',')]],'text':''}
def word(c):return re.sub(r'^\s*\d+\.\s*','',c['text']).strip()
def split1(i,c):return {'answer':['split'],'split':'onset','word':word(c),'text':''}
def syl(i,c):return {'answer':['split'],'split':'syllable','word':word(c),'text':''}
def cols(i,c):
    rows=[r.split() for r in c['text'].split('/')]
    return {'answer':['chip','chip','chip'],'opts':[[rows[0][j],rows[1][j]] for j in range(3)],'text':''}
page('12','Pre-Test · Blending Onset to the Rime',oc);page('13','Pre-Test · Blending Onset to the Rime',oc)
page('14','Pre-Test · Blending Onset to the Rime','tiles')
page('15','Pre-Test · Segmenting Onsets and Rimes',lambda i,c:'tiles' if i<2 else ('look' if i==2 else split1(i,c)))
page('16','Pre-Test · Segmenting Onsets and Rimes',split1)
page('17','Pre-Test · Segmenting Words into Syllables',syl)
page('18','Pre-Test · Blending Syllables into Words','chip');page('19','Pre-Test · Blending Syllables into Words','chip')
page('21','Pre-Test · Sentence Segmentation · Activity No. 1',{'answer':['words'],'mark':'heart'})
page('22','Pre-Test · Sentence Segmentation · Activity No. 2',{'answer':['words'],'mark':'star'})
page('23','Pre-test · Rhyming and Rhyming Songs','chip')
page('31','Activity 1 · Blending Onset and Rime','chip')
page('32','Activity 2 · Blending Onset and Rime',{'answer':['tiles'],'tiles':['l','s','k','w','b','r','d','f','p']})
O33=[(['m','r','t','b','d'],['at','am','ap','an','ag']),(['r','s','f','j','m'],['ag','am','at','ad','at'])]
O34=[(['t','p','c','b','h'],['at','am','ag','an','am']),(['h','c','t','n','m'],['ag','am','at','an','ap'])]
dd=lambda l:list(dict.fromkeys(l))
page('33','Activity 3 · Blending Onset and Rime',lambda i,c:{'answer':['chip','chip'],'opts':[dd(O33[i//5][0]),dd(O33[i//5][1])]})
page('34','Activity 4 · Blending Onset and Rime',lambda i,c:{'answer':['chip','chip'],'opts':[dd(O34[i//5][0]),dd(O34[i//5][1])]})
page('35','Activity 5 · Segmenting Onsets and Rimes',{'answer':['tiles'],'tiles':['b','c','h','m','n','p','r','s','v','w']})
page('36','Activity 6 · Segmenting Onsets and Rimes',split1)
page('37','Activity 7 · Segmenting Onsets and Rimes',{'answer':['chip','chip'],'opts':[['p','d','v','l','r','h','st','m','w'],['ip','an','ig','og','am','at','en','ar','op']]})
page('38','Activity 8 · Segmenting Words into Syllables',syl)
page('39','Activity 9 · Segmenting Words into Syllables','num')
page('40','Activity 10 · Segmenting Words into Syllables',lambda i,c:{'answer':['split','num'],'split':'syllable','word':word(c),'text':''})
page('41','Activity 11 · Blending Syllables into Words',cols);page('42:continuation','Activity 11 · Blending Syllables into Words',cols)
page('42','Activity 12 · Blending Syllables into Words · Act. #1',{'answer':['tiles'],'tiles':['dr','fr','tr','br','cr']})
page('43','Activity 13 · Blending Syllables into Words · Act. #2',{'answer':['tiles'],'tiles':['squ','scr','str','thr','spr']})
page('44','Activity 14 · Blending Syllables into Words · Act. #1','chip')
page('45','Activity 15 · Blending Syllables into Words · Act. #2',{'answer':['tiles'],'tiles':['cl','sm','bl','br','sk','st','sc','sp']})
page('46','Activity 16 · Sentence Segmentation',{'answer':['words'],'mark':'dot'})
page('47','Activity 17 · Sentence Segmentation',{'answer':['words','num'],'mark':'moon','max':10})
page('48','Activity 18 · Sentence Segmentation',{'answer':['num','words'],'mark':'dot','max':10})
page('49','Activity 19 · Rhyming and Rhyming Songs','chip')
page('50','Activity 20 · Rhyming and Rhyming Songs','chip');page('51','Activity 21 · Rhyming and Rhyming Songs','chip')
page('52','Activity 22 · Rhyming and Rhyming Songs',lambda i,c:{'answer':['chip'],'choices':[w.strip() for w in c['text'].split('·')],'text':''})
page('54','Post Test · Segmenting Onsets and Rimes',lambda i,c:'look' if i==0 else split1(i,c))
page('55','Post Test · Segmenting Onsets and Rimes',split1)
page('56','Post Test · Segmenting Words into Syllables',syl)
page('57','Post Test · Segmenting Words into Syllables · Blending Syllables into Words',lambda i,c:syl(i,c) if i<2 else 'chip')
page('58','Post Test · Blending Syllables into Words','chip');page('59','Post Test · Blending Syllables into Words','chip')
page('60','Post-test · Rhyming and Rhyming Words',{'answer':['num'],'max':10})
page('61','Post-test · Rhyming and Rhyming Words',lambda i,c:{'answer':['chips'],'choices':dd([w.strip('.,!?’') for w in c['text'].split()])})
missing=[k[3:] for k in d if k.startswith('2b:') and k[3:] not in P];assert not missing,missing
G={'bor':('Blending Onset and Rime',['Identify the onset and rime in simple CVC (consonant-vowel-consonant) words.','Blend onset and rime orally to form whole words.','Produce new words by changing the onset while keeping the rime constant.']),
 'sor':('Segmenting Onsets and Rimes',['Orally identify the onset and rime of a given single-syllable word represented by a picture.','Physically and verbally segment spoken words into onset and rime using hand motions and chants.','Produce the rime of a word when given the onset, and vice versa, using picture supports.']),
 'ssy':('Segmenting Words into Syllables',['Orally segment multisyllabic words into syllables by clapping, tapping, or counting.','Identify and sort picture cards based on the number of syllables in each word.','Write and represent the syllable breakdown of at least five words using visual markers.']),
 'bsy':('Blending Syllables into Words',['Orally blend separately spoken syllables to identify the whole word.','Connect blended words to their corresponding pictures through matching activities.','Create and illustrate a new word by blending given syllables.']),
 'sen':('Sentence Segmentation',['Orally segment a simple spoken sentence into individual words (e.g., "The cat runs." → "The / cat / runs.").','Identify and count the number of words in a spoken or written sentence.','Represent a sentence visually using counters, blocks, or written boxes, one per word.']),
 'rhy':('Rhyming and Rhyming Songs',['Identify rhyming pairs from given picture cues through matching games.','Generate at least one rhyming word for a given prompt using word families.','Participate in and create new verses for rhyming songs and chants.'])}
seq=['bor']*4+['sor']*3+['ssy']*3+['bsy']*5+['sen']*3+['rhy']*4
EX={1:'Example: f, p, b + an → pan',2:'Example: web',3:'Onset = first sound (e.g., /c/ in cat). Rime = rest of the word (e.g., -at in cat). c + at = cat',4:'Onset = first sound (e.g., /c/ in cat). Rime = rest of the word (e.g., -at in cat). c + at = cat',
 5:'Onset = first sound (e.g., /c/ in cat). Rime = rest of the word (e.g., -at in cat).',6:'Example: gap → onset g, rime ap',7:'Onset = first sound (e.g., /c/ in cat). Rime = rest of the word (e.g., -at in cat).',
 8:'Example: ok/ra',9:'Example: parrot = 2',10:'Example: natural = na/tu/ral 3',11:'Example: c a t → cat',12:None,13:None,14:None,15:None,16:None,17:None,18:'What/ a/ fun/ day!',19:None,20:None,21:None,22:None}
L={'35':{'lesson':'Pre-Test','activity':'Phonological Awareness Pre-Test','goals':[],'what':None,'model':None,'covers':['Blending Onsets and Rimes into Words','Segmenting Words into Onsets and Rimes','Segmenting Words into Syllables','Blending Syllables into Words','Sentence Segmentation','Rhymes and Rhyming Songs']},
   '58':{'lesson':'Post Test','activity':'Phonological Awareness Post Test','goals':[],'what':None,'model':None,'covers':['Segmenting Onsets and Rimes','Segmenting Words into Syllables','Blending Syllables into Words','Sentence Segmentation','Rhyming and Rhyming Words']}}
for n in range(1,23):
    name,goals=G[seq[n-1]];L[str(35+n)]={'lesson':name,'activity':f'Activity {n} · {name}','goals':goals,'what':None,'model':EX[n]}
json.dump({'version':1,'lessons':L,'pages':P},open('database/level2b-ui.json','w'),ensure_ascii=False,indent=1)
print(len(P),'pages',sum(len(v['cards']) for v in P.values()),'cards')
