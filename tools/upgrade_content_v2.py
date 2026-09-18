"""Reviewed Level 1 interaction and image corrections; keeps original activity IDs."""
import json,shutil
from pathlib import Path
R=Path(__file__).resolve().parents[1]
C=json.loads((R/'database/content.json').read_text());P=json.loads((R/'database/source-pages.json').read_text())
def imgs(n,y0=0,y1=776):
 arr=[i for i in P[n-1]['images'] if i['bbox'][1]>=y0 and i['bbox'][3]<=y1 and i['xref']!=12]
 return [i['path'] for i in sorted(arr,key=lambda i:(round(i['bbox'][1]/12),i['bbox'][0]))]
# Correct bounding-box omissions; do not substitute visuals for unsupplied props.
fix={128:imgs(17,350,560),134:imgs(17,350,560),135:imgs(17,350,560),136:[],137:imgs(57),149:[],150:[],151:[],152:[],153:[],186:imgs(32),187:imgs(32),188:imgs(32),189:imgs(32),180:imgs(31),199:['assets/module/image-291.jpeg'],200:['assets/module/image-289.jpeg'],201:['assets/module/image-290.jpeg']}
for i in range(115,123):fix[i]=imgs(17,0,340)
for i in range(129,134):fix[i]=imgs(17,350,560)
# Actual teaching prompt on page 61 references a dog picture not supplied as that exact visual.
# Retain the statement and name the missing prop; never display an unrelated child image.
for a in C['activities']:
 a['response_mode']='none' if a['type']=='reference' or (a['type']=='reading' and not a['expected_text']) else ('perform' if a['type'] in ['physical','group'] else ('drawing' if a['type']=='drawing' else 'answer'))
 a['instructions']={'none':'Read or listen, then choose Next when you are ready.','perform':'Try the activity with your partner or teacher, then choose Done when you have finished.','drawing':'Draw your answer, or describe your paper drawing. Then submit your work.','answer':'Share your answer by speaking or typing. Then choose Submit Answer.'}[a['response_mode']]
 if a['response_mode']=='none':a['xp_reward']=0
 if a['id'] in fix:a['image_paths']=fix[a['id']]
 if a['id'] in [149,150,151,152,153]:a['instructions']+=' Use the dog-in-a-park picture described in the module with your teacher; that exact example image is not supplied.'
 # Keep original page-specific visual text intact in scalable images.
 new=[]
 for source in a['image_paths']:
  target=f"assets/images/level1/lesson{a['lesson_id']:02d}/{Path(source).name}"
  path=R/'public'/target;path.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(R/'public'/source,path);new.append(target)
 a['image_paths']=new;a['image_path']=new[0] if new else None
# Stable lesson covers chosen by actual topic, not the PDF image object's insertion order.
covers={1:'image-97.jpeg',2:'image-104.jpeg',3:'image-102.jpeg',4:'page-018.jpg',5:'page-023.jpg',6:'image-215.jpeg',7:'image-227.jpeg',8:'image-236.jpeg',9:'page-028.jpg',10:'image-285.jpeg',11:'page-033.jpg',12:'image-291.jpeg'}
for l in C['lessons']:
 source=R/'public/assets/module'/covers[l['id']];target=f"assets/images/level1/lesson{l['id']:02d}/{source.name}";p=R/'public'/target;p.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,p);l['image_path']=target
# Definite describing-word answers explicitly supplied by the source questions (PDF page 10).
keys={31:['red'],32:['big'],33:['round'],34:['tall'],35:['short']}
C['verified_answers']=keys
(R/'database/content-v2.json').write_text(json.dumps(C,ensure_ascii=False,indent=2))
def sql(v):
 if v is None:return 'NULL'
 if isinstance(v,int):return str(v)
 if isinstance(v,(list,dict)):v=json.dumps(v,ensure_ascii=False)
 return "'"+v.replace('\\','\\\\').replace("'","''")+"'"
s=['-- Run in the selected BULIG database. Keeps accounts, responses, XP and activity IDs.','START TRANSACTION;']
for a in C['activities']:
 # Do not overwrite administrator-edited content (revision > 1).
 cols=['response_mode','instructions','image_path','image_paths','xp_reward']
 s.append('UPDATE activities SET '+','.join(k+'='+sql(a[k]) for k in cols)+' WHERE id='+str(a['id'])+' AND revision=1;')
for l in C['lessons']:s.append('UPDATE lessons SET image_path='+sql(l['image_path'])+' WHERE id='+str(l['id'])+' AND image_path LIKE \'assets/module/%\';')
for aid,ans in keys.items():
 s.append(f"UPDATE questions q JOIN activities a ON a.id=q.activity_id SET q.grading='exact' WHERE a.id={aid} AND a.revision=1;")
 for answer in ans:s.append(f"INSERT INTO answers(question_id,content,correct) SELECT q.id,{sql(answer)},1 FROM questions q JOIN activities a ON a.id=q.activity_id WHERE a.id={aid} AND a.revision=1 AND NOT EXISTS(SELECT 1 FROM answers an WHERE an.question_id=q.id AND an.content={sql(answer)});")
s.append('COMMIT;');(R/'database/migrations/002_content.sql').write_text('\n'.join(s))
print('Mapped',len(C['activities']),'activities and corrected image sets. Fixed-answer IDs:',list(keys))
