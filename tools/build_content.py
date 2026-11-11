from pathlib import Path
import json,re,hashlib
R=Path(__file__).resolve().parents[1]
pages=json.loads((R/'database/source-pages.json').read_text())
def text(n):return pages[n-1]['text']
def pic(n):return f'assets/module/page-{n:03}.jpg'
def images(n,ymin=0,ymax=770):return [x['path'] for x in pages[n-1]['images'] if x['bbox'][1]>=ymin and x['bbox'][3]<=ymax]
def clean(s):
 s=re.sub(r'[\U0001F300-\U0001FAFF\u2600-\u27BF\uFE0F]','',s)
 return re.sub(r'\s+',' ',s).strip()
titles=['Talking About One’s Self and One’s Family','Follow One-to-Two-Step Physical Directions','Give and Follow One-to-Two-Step Verbal Directions','Recognize Describing Words','Talk About Topics of Interest','Describe and Draw','Using Story Chain','Using Picture Talk','Using Poems','Talk, Play and Share','Using Word Association','Using Tongue Twister']
short=['Meet & Greet','Listen & Move','Kind Directions','Describing Words','Picture Thoughts','Describe & Draw','Story Chain','Picture Talk','Recite, Clap & Smile','Talk, Play & Share','Word Friends','Tongue Twisters']
starts=[47,50,52,56,60,63,66,72,75,78,81,83];ends=[49,52,55,59,62,65,71,74,77,80,83,85]
visuals=[[16],[16,17], [17],list(range(18,23)),[23,24,25],[25,26],[26,67,68,69,70],[27],list(range(28,32)),[32],[33],[33,34]]
extra=[[35],[35],[35],[36],[36,37,38],[39,40,41],[43,44,45],[45],[],[],[],[42]]
lessons=[]; acts=[]; assessments=[]
def add(l,title,prompt,page,type='open',imgs=None,phase='learn',assessment_id=None,expected=None,instructions=None):
 pos=1+sum(a['lesson_id']==l and a['phase']==phase for a in acts)
 aid=len(acts)+1
 acts.append(dict(id=aid,lesson_id=l,assessment_id=assessment_id,phase=phase,position=pos,title=title,type=type,instructions=instructions or ('Work with your teacher. Share your answer aloud, then type it or use the microphone. Your teacher will review your work.' if type not in ('physical','group','reference','drawing') else 'Work with your teacher or partner. Record what you did; your teacher will review your work.'),prompt=prompt,image_path=(imgs or [None])[0],image_paths=imgs or [],narration=prompt,expected_text=expected,xp_reward=10,source_page=page,source_excerpt=prompt))
 return aid
for l in range(1,13):
 guide='\n'.join(text(n) for n in range(starts[l-1],ends[l-1]+1))
 if l in (3,12):guide=guide[guide.index(f'LESSON PLAN {l}:'):]
 if l in (2,11):guide=guide[:guide.index(f'LESSON PLAN {l+1}:')]
 m=re.search(r'I\.\s*OBJECTIVES?(.*?)(?:II\.|II\s*\.)',guide,re.S)
 objectives=clean(m.group(1)) if m else ''
 source=sorted(set(visuals[l-1]+extra[l-1]+list(range(starts[l-1],ends[l-1]+1))))
 hero=images(visuals[l-1][0])
 if l==1:hero=['assets/module/image-97.jpeg'] if False else [x['path'] for x in pages[15]['images'] if x['xref']==97]
 lessons.append(dict(id=l,module_id=1,position=l,title=titles[l-1],subtitle=short[l-1],objectives=objectives,teacher_guide=guide,source_pages=source,image_path=hero[0] if hero else None))
 # All original assessments, with explicit competency mapping for swapped headings.
 for phase,n in [('pre',l+6 if l<=6 else (13 if l<=10 else 14)),('post',l+86 if l<=6 else (93 if l<=10 else (95 if l==11 else 94)))]:
  block=text(n)
  matchnum=l if not (phase=='post' and l>=11) else (12 if l==11 else 11)
  match=re.search(r'LESSON\s+'+str(matchnum)+r'\s*:',block)
  if match:block=block[match.start():]
  block=re.split(r'\n\s*LESSON\s+\d+\s*:',block,maxsplit=1)[0]
  start=re.search(r'(?:PRE-TEST|POST-TEST|Pre-Test|Post-Test|Pre-test|Post-test)',block)
  section=block[start.end():] if start else block
  # Stop before rubric headings, whose extracted column order differs from print.
  section=re.split(r'\n[^\n]*(?:Needs|Criteria|Excellent|Satisfactory)',section,1)[0]
  matches=list(re.finditer(r'(?:^|\n)\s*(\d+)\.\s+',section))
  qs=[]
  for k,m in enumerate(matches):
   q=clean(section[m.end():matches[k+1].start() if k+1<len(matches) else len(section)])
   qs.append(q)
  assid=len(assessments)+1
  note='The printed post-test headings reverse Lessons 11 and 12. Mapped by competency; original page is preserved.' if phase=='post' and l>=11 else ''
  assessments.append(dict(id=assid,lesson_id=l,kind=phase,title=f'{short[l-1]} · {phase}-assessment',rubric=block,source_page=n,source_note=note))
  for k,q in enumerate(qs,1):add(l,f'{phase.title()}-assessment · {k}',q,n,'physical' if l==2 else 'open',phase=phase,assessment_id=assid)
# Pupil-facing exact prompts and activity sequences from the module.
add(1,'Hello Circle','Hello, my name is [name]',47,'sentence')
add(1,'Look and share','Who do you see in this picture? What do you like to do with your own family?',48,imgs=images(16,120,320))
for s in ['My name is …','I am _____ years old.','My family has …','We like to …']:add(1,'Sentence starter',s,16,'sentence',images(16,120,320))
add(1,'Pair-and-Share','Student A uses the sentence starters to share about themselves and their family. Student B listens, then shares their own information.',48,'group',images(16,120,320))
add(2,'Action Song','Head, Shoulders, Knees, and Toes',50,'physical')
for s in ['Go to the chair','Go to the door, then close it.','Pick up the book, then give it to me','When I said ‘Go to the door and close it,’ what was the first thing you did? The second thing?','Why is it important to listen to all the direction before you start?','Go to the window and tap it twice.','Pick up one pencil and put it on the table.','Run to the whiteboard, then sit down in your chair.']:add(2,'Secret Agent Mission Trail',s,51,'physical' if not s.endswith('?') else 'open',images(17,0,310))
for s in ['Run to the door, then close it.','Go to your chair, then sit down.','Pick-up the book, then give it to me.','Sit down and keep quiet.']:add(2,'Mission card',s,16,'physical')
add(3,'Follow My Lead','Pick up one block and put it on your desk',53,'physical')
add(3,'Look and share','Have you ever seen a room like this? If your younger sister made this mess, what would you say to ask her to clean it up?',53,imgs=images(17,310,520))
for s in ['Can you please keep your toys away and put them in the toy box?','Can you pick up your cars and put them on the shelf?','What polite words did I use when asking my sister?','What was the first step I asked her to do? The second step? Why is order important?','How do you think your sister would feel if you asked nicely vs. yelling?']:add(3,'Kind directions',s,54)
add(3,'Role-Play Activity','Your younger sister left her dolls on the bed. What 2 things will you ask her to do?',55,'group',images(17,310,520))
add(3,'Scenario card 2','Your younger brother throws his trash anywhere. What 2 things will you ask him to do?',17,'group')
for s in ['I’m thinking of something in this room that’s red. Can you guess what it is?','Which drawing tells you more about the cat? Why?']:add(4,'Word Clue',s,56 if s.startswith('I’m') else 57,imgs=images(57))
for n,label in [(18,'Sizes and color'),(19,'Color and shapes'),(20,'Shapes and how things feel'),(21,'How things look and feelings'),(22,'Feelings and numbers')]:add(4,label,'Point to each word and its picture, and say the word aloud.',n,'reference',[pic(n)])
for s in ['What describing words can we use for this?','Why do we use describing words when we talk?','Can we use more than one describing word for something? Let’s try with the box – is it square, blue, or both?','What describing words can we use for places, like a park?']:add(4,'Describing words',s,58)
add(4,'Describe It! Share It!','One student looks at the picture and thinks of 2-3 describing words for it. They say the describing words aloud and their partner guesses what’s in the picture. Pairs switch roles after 5 minutes.',59,'group')
add(5,'Quick Share','Say one sentence about something you love.',60)
add(5,'Picture discussion','Who likes dogs? What do you see in this picture? What do you think the dog is feeling?',61,imgs=images(23)[:1])
for s in ['I see…','I think…','I like…','It makes me feel…']:add(5,'Sentence starter',s,61,'sentence',images(23)[:1])
for n in [23,24,25]:add(5,'Picture Card Pack','One student picks a picture from the pack and shares their thoughts using at least 2 sentence starters. The other students listen, then one peer asks a follow-up question. Everyone in the group takes a turn picking a picture and sharing.',n,'group',[pic(n)])
add(6,'Quick Draw','a small, blue square',63,'drawing')
add(6,'Listen and draw','First, there’s a big, green tree branch in the middle. On top of the branch, there’s a small, red bird with a yellow beak. Next to the bird, there’s a bright yellow flower with five petals. The sky behind is light blue.',64,'drawing')
add(6,'Check and discuss','What details helped you draw it right?',64,imgs=images(25,450,770))
for s in ['What would happen if I forgot to say ‘small’ about the bird? Or ‘next to the bird’ about the flower?','Why is it important to describe things in order (e.g., ‘first the branch, then the bird’)?']:add(6,'Talk about details',s,64)
add(6,'Pair Describe and Draw','The Describer looks at their picture and describes it using key details and the visual aid words. The Drawer listens carefully and draws what they hear—they can ask clarifying questions. After 5 minutes, have pairs switch roles with a new secret picture.',65,'drawing',images(26,0,400))
add(7,'Guess the Next Picture','What do you see in this picture? What do you think will happen next?',66,imgs=images(26,400,770)[:2])
add(7,'Picture Story Chain','Once upon a time, there was a little cat named Mimi. She woke up early in her cozy bed. Mimi jumped out of bed and walked outside to the garden.',67,'reading',images(26,400,770))
for n in [68,69,70]:add(7,'Group Picture Story Chain','The first student looks at the first picture and says one sentence. The next student looks at the second picture and says a sentence that connects to the first. Continue until all pictures are used and the story has an ending. One student from each group will share the complete story with the class.',n,'group',[pic(n)])
add(7,'Share your story','How did working together help us make the story?',70)
add(8,'Mystery Picture Peek','What do you think this is? What do you think is in the rest of the picture?',72,imgs=images(27)[:1])
add(8,'See & Say','Look closely at the picture, and when you see something, stand up and say what it is.',73,imgs=images(27))
for s in ['Who is in the picture?','What are the children doing?','Where are they?','When do you think this is happening?','Why do you think they’re happy?']:add(8,'Picture Question Circle',s,73,imgs=images(27))
add(8,'My Connection Share','Now, think about a time you did something like what’s in the picture. Can you share 1 or 2 sentences about it?',73,imgs=images(27))
add(8,'Let’s talk','What did we learn today about pictures?',74)
add(9,'Rhyme & Clap Dance','cat – hat; dog – frog; bird – word',75,'physical')
poem='Itsy bitsy spider climbed up the waterspout. down came the rain and washed the spider out; out came the sun and dried up all the rain; and the itsy-bitsy spider climbed up the spout again.'
add(9,'The Itsy-Bitsy Spider',poem,76,'reading',images(31),expected=poem)
add(9,'Talk about the poem','Who knows this poem? What does the spider do? How did the voice sound? Was it happy? Silly?',76)
add(9,'Pick & Explore','Students choose a poem they know or like (based on the pictures). The teacher helps each student point to the words as they say the poem aloud once.',76,'group',[pic(n) for n in range(28,32)])
add(9,'Practice with a Buddy & Props','Buddies take turns reciting: Speak loud! Smile! Add a dance!',77,'group',[pic(n) for n in range(28,32)])
add(9,'Polish with the Teacher','The teacher circulates to listen to 1–2 lines from each student, giving quick feedback.',77,'group')
add(9,'Little Voices Showcase','What was fun about reciting your poem?',77)
add(10,'Hello, Friend! Circle','Hi! My name is [Teacher’s name]. How are you, [Student’s name]?',78,'group')
add(10,'Our Toys, Our Stories','Who has a toy at home? What’s your toy like?',79,imgs=images(32))
for name,prompt in [('Toy Show & Tell Chat','Students take turns showing their toy (or picture) and sharing 1–2 things about it. After each share, friends ask a simple question: “What color is it?” or “Do you like it?”'),('Find a Toy Match','Do you have a [car/doll/teddy]? We both have a [toy]!'),('Make a Toy Story Together','Pairs pick a toy from the Chat Box. They create a 2-sentence story together. Add simple actions while saying the story.')]:add(10,name,prompt,79 if name!='Make a Toy Story Together' else 80,'group',images(32))
add(10,'Share Our Toy Stories','What was fun about talking to your friend today?',80)
add(11,'Word Catch!','Teacher says a word (e.g., “red”). Student catches the ball and says a word friend (e.g., “apple”).',81,'group')
add(11,'Guess My Word Friend!','What words do you think of? Meow? Milk? Paws?',81,imgs=images(33,0,590))
add(11,'Word Friends on the Board','What words go with cat?',82)
add(11,'How words connect','“Milk” → goes with cat. “Small” → opposite of big. “Happy” → same as glad.',82,'reading')
add(11,'Word Chain Game','Teacher gives a starting word (e.g., “sun”). Each student adds a word friend (e.g., “sun” → “hot” → “ice cream” → “sprinkles”).',82,'group')
for s in ['Why is ‘cat’ friends with ‘milk’?','How do word friends help us talk?']:add(11,'Why Word Friends?',s,82)
add(12,'Copy the Sound!','/p/ like popping popcorn! Repeat with /b/ (bounce like a ball) and /s/ (slither like a snake).',84,'physical')
for s in ['Peter Piper picked pickled peppers.','Betty Botter bought butter.','Six snails slide slowly.']:add(12,'Tongue twister',s,83,'reading',images(33,600,770),expected=s)
for s in ['Why is ‘Peter Piper’ tricky to say?','How do tongue twisters help us?']:add(12,'Let’s talk',s,84)
add(12,'Tongue Twister Game','In small groups, students pick a tongue twister. Say it slowly, then fast. Friends give a thumbs up if they hear the sounds clearly! Add fun: Hop or clap while saying it!',84,'group',images(33,600,770))
# Preserve every procedure, evaluation, homework, and supplemental passage without inventing missing content.
for l in range(1,13):
 guide=lessons[l-1]['teacher_guide']
 add(l,'Teacher-guided lesson checklist',clean(guide),starts[l-1],'reference',[pic(n) for n in range(starts[l-1],ends[l-1]+1)],instructions='With your teacher, check all lesson procedures, evaluation, and home practice in the official guide. Your teacher confirms completion using the original checklist.')
 if extra[l-1]:add(l,'Additional intervention materials','\n\n'.join(clean(text(n)) for n in extra[l-1]),extra[l-1][0],'reference',[pic(n) for n in extra[l-1]],instructions='Use the original additional intervention material with your teacher. Your teacher will record which practice you completed.')
issues=["Printed post-tests on PDF pages 94–95 reverse Lessons 11 and 12 compared with the lesson plans and pre-tests. They are linked by competency: Word Association → Lesson 11; Tongue Twisters → Lesson 12. The original headings remain visible.","Lesson 2 pre-test calls ‘Stand up’ a two-step direction. Preserved verbatim; teacher judges the actual action.","The additional asking-questions game mentions both 20 and 10 questions. Both instructions are preserved; teacher selects the intended stopping rule.","Most answers are personal responses, actions, drawings, or collaborative speech. No fixed correct answers are invented. Teacher observation and official rubrics determine completion.","Some lesson-plan example objects/pictures are requested teaching props, not supplied as separate assets. Teachers supply those objects; unrelated images are not substituted."]
content={'lessons':lessons,'assessments':assessments,'activities':acts,'issues':issues,'source_sha256':hashlib.sha256((R/'storage/level1-original.pdf').read_bytes()).hexdigest()}
(R/'database/content.json').write_text(json.dumps(content,ensure_ascii=False,indent=2))
def sql(v):
 if v is None:return 'NULL'
 if isinstance(v,(int,float)):return str(v)
 if isinstance(v,(list,dict)):v=json.dumps(v,ensure_ascii=False)
 return "'"+v.replace('\\','\\\\').replace("'","''")+"'"
s=['USE DEPED_BULIG;','SET NAMES utf8mb4;','START TRANSACTION;']
for table,rows in [('lessons',lessons),('assessments',assessments),('activities',acts)]:
 for row in rows:s.append(f'INSERT INTO {table} ('+','.join(row)+') VALUES ('+','.join(map(sql,row.values()))+');')
for a in acts:
 s.append(f"INSERT INTO questions(id,activity_id,content,grading) VALUES ({a['id']},{a['id']},{sql(a['prompt'])},'teacher');")
 if a['assessment_id']:s.append(f"INSERT INTO assessment_questions VALUES ({a['assessment_id']},{a['id']});")
for p in pages:s.append('INSERT INTO source_pages VALUES ('+','.join(map(sql,[p['page'],p['text'],p['image_path'],p['images']]))+');')
for issue in issues:s.append('INSERT INTO content_issues(description) VALUES ('+sql(issue)+');')
criteria={1:['Completeness','Clarity','Organization'],2:['Correctness of Steps','Order of Steps','Timeliness'],3:['Number of Instructions','Clarity & Tone','Relevance'],4:['Relevance','Variety','Accuracy'],5:['Relevance','Depth of thought','Clarity'],6:['Detail','Clarity','Organization'],12:['Pronunciation','Fluency','Effort & Confidence']}
for l,c in criteria.items():s.append('UPDATE assessments SET rubric_criteria='+sql(c)+' WHERE lesson_id='+str(l)+';')
s.append('COMMIT;');(R/'database/content.sql').write_text('\n'.join(s))
print('Lessons:',len(lessons),'Assessments:',len(assessments),'Activities:',len(acts))
for l in range(1,13):print(l,[(phase,sum(a['lesson_id']==l and a['phase']==phase for a in acts)) for phase in ['pre','learn','post']])
