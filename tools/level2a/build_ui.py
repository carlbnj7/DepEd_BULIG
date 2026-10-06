"""Level 2A screens: the module's own titles, lesson goals, "My turn" examples, and how each card is answered.
Writes database/level2a-ui.json (read by app/level2a.php). Answer kinds:
 tiles  - build letters/words with big tiles (module: "write")
 chip   - tap one word/sound (encircle, box, underline, color, check one)
 chips  - tap several words (check/box the words that ...)
 pick   - tap one picture        picks - tap several pictures
 num    - tap a number 1-6 (count the sounds)
 say    - speak (record), "My teacher heard me" fallback (module: "say", "tell me")
 pairs  - tap two pictures that go together
 paper  - done on paper / with the class (no answer in BULIG)
 look   - read or listen only"""
import json,re
d=json.load(open('database/level2-cards.json'))['pages']
LET=list('abcdefghijklmnopqrstuvwxyz')
VOW=['/a/','/e/','/i/','/o/','/u/']
def cards(n):return d[f'2a:{n}']['cards']
P={}
def page(n,title,kinds,**kw):
    cs=cards(n);out=[]
    for i,c in enumerate(cs):
        k=kinds(i,c) if callable(kinds) else kinds
        if isinstance(k,str):k={'answer':[k]}
        elif isinstance(k,list):k={'answer':k}
        out.append(k)
    P[str(n)]={'title':title,'cards':out,**kw}
dot=lambda c:[w.strip() for w in re.split(r'·|→',c['text']) if w.strip()]
def ch(i,c,kind='chip'):
    if c['choices']:return {'answer':[kind]}
    return {'answer':[kind],'choices':dot(c)}
# ---- Pretest ----
page(6,'Phoneme Isolation Pretest · A','tiles');page(7,'Phoneme Isolation Pretest · B','tiles');page(8,'Phoneme Isolation Pretest · C','tiles')
page(9,'Phoneme Identification Pretest',lambda i,c:'tiles' if i<5 else 'chip')
page(10,'Phoneme Identification Pretest',lambda i,c:{'answer':['chip'],'choices':VOW} if i<2 else {'answer':['pick'],'ref':0})
page(11,'Phoneme Categorization Pretest',lambda i,c:'chip' if c['choices'] else 'pick')
page(12,'Phoneme Categorization Pretest','pick')
page(13,'Phoneme Blending Pretest',lambda i,c:'look' if i==0 else 'tiles')
page(14,'Phoneme Segmentation Pretest · A',{'answer':['num'],'text':''});page(15,'Phoneme Segmentation Pretest · B','pick')
page(16,'Phoneme Segmentation Pretest · C','pick')
page(17,'Phoneme Deletion Pretest · A',{'answer':['pick'],'ref':0});page(18,'Phoneme Deletion Pretest · B','tiles');page(19,'Phoneme Deletion Pretest · C','say')
page(20,'Phoneme Addition Pretest',lambda i,c:'look' if i==0 else ['chip','tiles'])
page(21,'Phoneme Substitution Pretest · Test I',{'answer':['chip'],'choices':['✓ initial sound','x ending sound']})
# ---- Lesson 1: Phoneme Isolation ----
page(29,'Guided Practice: Clear the Board','picks');page(30,'Worksheet · Beginning Sound Isolation · Activity 1','chip')
page(31,'Games: Scavenger Hunt',lambda i,c:'picks' if i==0 else 'paper')
page(34,'Middle Sound Isolation · Generalization','say');page(35,'Worksheet · Middle Sound Isolation · A','chip')
page(36,'Worksheet · Middle Sound Isolation · B',lambda i,c:'chip' if i==0 else 'tiles');page(37,'Agreement','paper')
page(40,'Guided Practice: Toy Thief',lambda i,c:'picks' if i==0 else 'look');page(41,'Worksheet · Ending Sound Isolation',lambda i,c:'paper' if i==5 else 'tiles')
# ---- Phoneme Identification ----
page(47,'Activity 1. Connect Me',{'answer':['pairs'],'text':''});page(48,'Activity 2. Identify Me',lambda i,c:'paper' if i==5 else 'tiles')
page(51,'Activity 3. Circle Me','pick');page(52,'Activity 4. Check Me','chips');page(53,'Agreement','tiles')
page(56,'Activity 5. Check Me',lambda i,c:ch(i,c,'chips'));page(57,'Activity 6. Color Me','chip')
# ---- Phoneme Categorization ----
page(62,'Activity 7 · Initial Phoneme Categorization · Activity 1',ch);page(63,'Activity 7 · Activity 2',lambda i,c:'pick' if i<5 else 'chip')
page(66,'Activity 8 · Medial Phoneme Categorization · Activity 1','pick');page(67,'Activity 8 · Activity 2 and Agreement',ch)
page(70,'Activity 9 · Final Phoneme Categorization · Activity 1',ch);page(71,'Activity 9 · Activity 2','pick')
page(72,'Activity 9 · Agreement',lambda i,c:'tiles' if i==0 else ch(i,c))
# ---- Phoneme Blending ----
def yp(i,c):
    m=re.match(r'(.*?)\s*—\s*(\w+) or (\w+)\?',c['text']);return {'answer':['chip'],'choices':[m.group(2),m.group(3)],'text':m.group(1)}
page(77,'Activity 10: You Put Me Together',yp);page(78,'Activity 10: Blend and Read','say');page(79,'Agreement','tiles')
page(82,'Activity 11: Match Me','pick')
page(83,'Activity 11: Blend Hunt',lambda i,c:{'answer':['tiles'],'tiles':['cr','dr','gl','sh','gr','fr','sn','fl','sp']} if i<10 else 'paper')
page(86,'Activity 12: Help Me Up!',lambda i,c:{'answer':['chips'],'choices':[w for w in dot(c) if w not in ('start','end')]})
page(87,'Conclusion','look')
page(88,'Activity 12: You Complete Me',lambda i,c:{'answer':['tiles'],'tiles':['ck','rl','rd','lk','rn','nd','sk','lt','nt']} if i<10 else 'paper')
# ---- Phoneme Segmentation ----
page(94,'Activity 13.1: Color Me',lambda i,c:'look' if i==0 else 'num');page(95,'Activity 13.1: Color Me','num')
page(96,'Activity 13.2: Tell Me','say');page(97,'Activity 14: Phoneme Jumping',lambda i,c:'paper' if i<2 else 'say')
page(100,'Activity 14.1: Circle Me','chip');page(101,'Activity 14.1: Circle Me','chip');page(102,'Activity 14.2: Break Me','tiles')
# ---- Phoneme Deletion ----
page(107,'Activity 15 · Worksheet · Exercise A','tiles');page(108,'Activity 15 · Exercise B and C','tiles')
page(111,'Activity 16 · Guided Practice','look');page(112,'Activity 16 · Worksheet · Exercise A','say')
page(113,'Activity 16 · Exercise B','tiles');page(114,'Activity 16 · Exercise C','tiles')
# ---- Phoneme Addition ----
page(121,'Activity 17: You Add Me','tiles');page(122,'Conclusion','look');page(123,'Activity 17: You Let Me In','chip');page(124,'Agreement','tiles')
page(130,'Activity 18: You Transform Me','tiles');page(131,'Agreement','tiles')
# ---- Phoneme Substitution ----
page(138,'Activity 19: Phoneme Trade','tiles')
page(139,'Activity 19.1: Phoneme Hunt',lambda i,c:{'answer':['tiles'],'tiles':['f','d','n','h','t','w','p','b','c']} if i<5 else 'tiles')
page(142,'Activity 20: I Can Change It','tiles');page(143,'Activity 2: Phoneme Swap','tiles')
# ---- Posttest ----
page(145,'Phoneme Identification Posttest · A and B','tiles');page(146,'Phoneme Identification Posttest · B and C','tiles')
page(147,'Phoneme Categorization Posttest',lambda i,c:'chip' if c['choices'] else 'pick');page(148,'Phoneme Categorization Posttest','pick')
page(149,'Phoneme Blending Posttest','tiles');page(150,'Phoneme Segmentation Posttest · A','num')
page(151,'Phoneme Segmentation Posttest · B and C',lambda i,c:'chip' if c['choices'] else {'answer':['tiles'],'tiles':['/a/','/i/','/n/','/m/','/k/','/e/','/r/','/p/','/d/','/t/','/j/','/b/']})
page(152,'Phoneme Segmentation Posttest · C',{'answer':['tiles'],'tiles':['/a/','/i/','/n/','/m/','/k/','/e/','/r/','/p/','/d/','/t/','/j/','/b/']})
page(153,'Phoneme Addition Post Test',lambda i,c:'look' if i==0 else ['chip','tiles'])
page(154,'Post Test: Phoneme Substitution · Test I',{'answer':['chip'],'choices':['B','E']});page(155,'Post Test: Phoneme Substitution · Test II','tiles')
missing=[k for k in d if k.startswith('2a:') and k[3:] not in P]
assert not missing,missing
# ---- lessons (DB lesson id -> module lesson, activity, goals, "My turn") ----
G={'iso':('Phoneme Isolation',['Recognize the beginning/initial sounds within a word.','Recognize the middle/medial sounds within a word.','Recognize the ending/final sounds within a word.'],None),
 'ide':('Phoneme Identification',['Identify the phonemes within each word.','Recognize the common sound in different words.','Identify the beginning, middle and final phoneme in the given name of the picture.'],None),
 'cat':('Phoneme Categorization',['Identify the words and pictures that have the odd or different initial sounds.','Identify the words and pictures that have the odd or different medial sounds.','Identify the words and pictures that have the odd or different final sounds.'],None),
 'ble':('Phoneme Blending',[],'Phoneme blending is the ability to hear the individual sounds in a word, put the sounds together, and say the word that is made.'),
 'seg':('Phoneme Segmentation',['Segment words into individual phonemes (sounds).','Recognize the sound/s in the given word.','Count the sound in each word.'],None),
 'del':('Phoneme Deletion',[],'Phoneme Deletion is the ability to identify how a word would sound if one sound were omitted.'),
 'add':('Phoneme Addition',['Add phoneme to a word to form a new word.','Create different words by adding phoneme to the given word.'],None),
 'sub':('Phoneme Substitution',['Change the different letter sound to form a new word.','Change consonant phoneme at the beginning in forming new word.','Change consonant phoneme at the end of a word to form a new word.'],None)}
A=[('iso','Activity 1. Recognizing Individual Sounds within a Word · Beginning/Initial Sound','The word ant has three different sounds. /a/ /n/ /t/. The beginning sound of the word ant is /a/.'),
 ('iso','Activity 2. Phoneme Isolation · Middle/Medial Sound','The word car has three different sounds. /c/ /a/ /r/. The middle sound of the word car is /a/.'),
 ('iso','Activity 3. Phoneme Isolation · Ending/Final Sounds Isolation','The word tap has three different sounds. /t/ /a/ /p/. The ending sound of the word tap is /p/.'),
 ('ide','Activity 4: Initial Phoneme Identification','This is a mat. This is a milk. This is a moon. What sound do they all have the same?'),
 ('ide','Activity 5: Medial Phoneme Identification','This is a can. This is a bag. This is a map. What sound do you hear at the middle?'),
 ('ide','Activity 6: Final Phoneme Identification','This is a wax. This is a box. This is a six. Final sound means the last sound you hear.'),
 ('cat','Activity 7: Initial Phoneme Categorization','Initial Phoneme Categorization is identifying the words and pictures that have the different beginning sound. Example: ball bat can'),
 ('cat','Activity 8: Medial Phoneme Categorization','can hen mat. Which word does not belong to the group?'),
 ('cat','Activity 9: Final Phoneme Categorization','Final phoneme categorization is identifying words and pictures in the group which have the different final sounds.'),
 ('ble','Activity 10: You Put Me Together','/c/ /a/ /t/ cat. The word cat is composed of three letters with different sounds. They blend together to form a word.'),
 ('ble','Activity 11: Beginning Consonant Phoneme Blending','/cl/ /a/ /p/ clap · /st/ /a/ /m/ /p/ stamp'),
 ('ble','Activity 12: Ending Consonant Phoneme Blending','/f/ /i/ /sh/ fish'),
 ('seg','Activity 13: Phoneme Segmenting','Listen and watch. I’ll say the sounds in the word “cat.” /c/ /a/ /t/. “Cat” has three sounds.'),
 ('seg','Activity 14: Phoneme Segmenting','door /d/ /o/ /r/ · work /w/ /o/ /r/ /k/'),
 ('del','Activity 15. Phoneme Deletion · Beginning Sound Deletion','meat take away /m/ → eat'),
 ('del','Activity 16. Phoneme Deletion · Ending/Medial Sound Deletion','band take away /d/ → ban · cart take away /t/ → car · tent take away /t/ → ten'),
 ('add','Activity 17: Adding Phoneme to the Word','/s/ nail → snail · pear /l/ → pearl'),
 ('add','Activity 18: Adding Phoneme at the Beginning','/c/ lap → clap · /t/ wig → twig'),
 ('sub','Activity 19: Phoneme Trade and Hunt','We change the beginning sound /c/ to /r/: cat → rat. We change the beginning sound /m/ to /c/: man → can.'),
 ('sub','Activity 20: I Can Change It','We change the ending or final sound /g/ to /n/: bug → bun.')]
L={'13':{'lesson':'Pretest','activity':'Phoneme Pretest','goals':[],'what':None,'model':None,'covers':['Phoneme Isolation','Phoneme Identification','Phoneme Categorization','Phoneme Blending','Phoneme Segmentation','Phoneme Deletion','Phoneme Addition','Phoneme Substitution']},
   '34':{'lesson':'Posttest','activity':'Phoneme Posttest','goals':[],'what':None,'model':None,'covers':['Phoneme Identification','Phoneme Categorization','Phoneme Blending','Phoneme Segmentation','Phoneme Addition','Phoneme Substitution']}}
for i,(g,act,model) in enumerate(A):
    name,goals,what=G[g];L[str(14+i)]={'lesson':name,'activity':act,'goals':goals,'what':what,'model':model}
json.dump({'version':2,'lessons':L,'pages':P},open('database/level2a-ui.json','w'),ensure_ascii=False,indent=1)
print(len(P),'pages',sum(len(v['cards']) for v in P.values()),'cards')
