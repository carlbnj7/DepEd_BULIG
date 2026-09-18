"""V2 regression tests for a disposable upgraded database; standard library only."""
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,sys
from types import SimpleNamespace
base=sys.argv[1].rstrip('/')+'/'
count=0
class Session:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def get(self,path):return self.request(path)
 def request(self,path,data=None,json_accept=False):
  headers={'Accept':'application/json'} if json_accept else {}
  req=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,headers=headers)
  try:r=self.opener.open(req,timeout=30)
  except urllib.error.HTTPError as e:r=e
  return SimpleNamespace(status=r.code,text=r.read().decode())
def check(ok,msg):
 global count
 assert ok,msg
 count+=1;print('PASS',msg,flush=True)
def csrf(s):return re.search('name="csrf" content="([^"]+)"',s.get('?page=dashboard').text)[1]
def post(s,data,expected=200,json_accept=False):
 data=dict(data);data['csrf']=csrf(s);r=s.request('',data,json_accept);check(r.status==expected,str(data['action'])+' HTTP '+str(expected));return r
def login(role,id,password):
 s=Session();r=post(s,dict(action='login',role=role,public_id=id,password=password));return s
admin=login('admin','adminqa','AdminTest42!')
r=post(admin,dict(action='create_account',name='Teacher One',password='TeacherTest42!'));t1=re.search(r'teacher ID: (T\d+)',r.text)[1]
r=post(admin,dict(action='create_account',name='Teacher Two',password='TeacherTest42!'));t2=re.search(r'teacher ID: (T\d+)',r.text)[1]
teacher=login('teacher',t1,'TeacherTest42!');other=login('teacher',t2,'TeacherTest42!')
post(teacher,dict(action='save_section',name='Rizal',grade_level=1));post(teacher,dict(action='save_section',name='Mabini',grade_level=1));post(other,dict(action='save_section',name='Private Section',grade_level=2))
def section_ids(s):return re.findall(r'name="id" type="hidden" value="(\d+)"',s.get('?page=sections').text)
rizal,mabini=section_ids(teacher);private=section_ids(other)[0]
post(teacher,dict(action='save_section',name='Rizal',grade_level=1),400)
post(other,dict(action='save_section',id=rizal,name='Stolen',grade_level=1),403)
post(teacher,dict(action='create_account',name='Alex Learner',password='PupilTest42!',section_id=private),403)
r=post(teacher,dict(action='create_account',name='Alex Learner',password='PupilTest42!',section_id=rizal));pid=re.search(r'pupil ID: (\d+)',r.text)[1]
r=post(teacher,dict(action='create_account',name='Bea Learner',password='PupilTest42!',section_id=mabini));bea=re.search(r'pupil ID: (\d+)',r.text)[1]
html=teacher.get('?page=dashboard&section_id='+rizal).text
check('Alex Learner' in html and 'Bea Learner' not in html,'Section filter selects only assigned pupils in SQL')
check(teacher.get('?page=dashboard&section_id='+private).status==403,'Foreign section filter rejected')
check('Private Section' not in teacher.get('?page=sections').text,'Other teacher sections are not listed')
post(teacher,dict(action='save_section',id=rizal,name='Rizal Updated',grade_level=1))
check('Rizal Updated' in teacher.get('?page=dashboard').text,'Renaming section updates pupil roster')
post(teacher,dict(action='save_section',id=rizal,name='Rizal Updated',grade_level=2),400)
pupil=login('pupil',pid,'PupilTest42!')
check(pupil.get('?page=sections').status==403,'Pupil cannot manage sections')
check(pupil.get('?page=lesson&id=2').status==403,'Lesson 2 starts locked')
check(pupil.request('',dict(action='submit',activity_id=1,response='test')).status==403,'CSRF enforced')
post(pupil,dict(action='finish_lesson',lesson_id=1),400)
content=json.load(open('bulig/database/content-v2.json'))
post(pupil,dict(action='draft',activity_id=1,response='Alex',transcript='Alex'),json_accept=True)
check('value="Alex"' in pupil.get('?page=lesson&id=1').text,'Voice transcript draft restored')
# Complete all 12 lessons without logging into the teacher again.
for lid in range(1,13):
 acts=sorted([a for a in content['activities'] if a['lesson_id']==lid],key=lambda a:(['pre','learn','post'].index(a['phase']),a['position']))
 post(pupil,dict(action='submit',activity_id=acts[-1]['id'],response='Skip'),403,json_accept=True)
 for a in acts:
  data={'action':'submit','activity_id':a['id']}
  if a['response_mode'] in ['answer','drawing']:data['response']='My answer'
  keys=content['verified_answers'].get(str(a['id']))
  if keys:
   bad=json.loads(post(pupil,{**data,'response':'incorrect'},json_accept=True).text);check(not bad['saved'],'Wrong fixed answer does not complete')
   data['response']=keys[0]
  if a['id']==199:data.update(response=a['expected_text'],transcript=a['expected_text'])
  if a['response_mode'] in ['none','perform']:
   html=pupil.get(f"?page=lesson&id={lid}&activity={a['id']}").text
   check('id="response"' not in html,'No forced answer on '+a['response_mode']+' page '+str(a['id']))
  result=json.loads(post(pupil,data,json_accept=True).text);check(result['saved'],'Activity '+str(a['id'])+' completes immediately')
  dup=json.loads(post(pupil,data,json_accept=True).text);check(dup['xp']==0,'Duplicate activity submission awards no XP')
 if lid<12:check(pupil.get('?page=lesson&id='+str(lid+1)).status==403,'Finish button required before next lesson')
 post(pupil,dict(action='finish_lesson',lesson_id=lid))
 check('Lesson complete!' in pupil.get('?page=lesson&id='+str(lid)).text,'Completion screen shown')
 if lid<12:check(pupil.get('?page=lesson&id='+str(lid+1)).status==200,'Next lesson unlocked with no approval')
# Cross teacher ownership and section reassignment.
roster=teacher.get('?page=accounts').text;internal=re.search(r'name="id" value="(\d+)"',roster)[1]
check(other.get('?page=pupil&id='+internal).status==403,'Teacher cannot read another teacher’s pupil')
post(teacher,dict(action='update_account',id=internal,name='Bea Learner',password='',section_id=rizal,active='on'))
check('Bea Learner' in teacher.get('?page=dashboard&section_id='+rizal).text,'Section reassignment works')
for s,pages in [(admin,['content','edit_activity&id=1','media','issues','settings','guide','accounts']), (teacher,['dashboard','accounts','sections','review','guide']), (pupil,['dashboard','achievements','profile'])]:
 for page in pages:check(s.get('?page='+page).status==200,'Page renders '+page)
open('/tmp/bulig-v2-accounts.json','w').write(json.dumps({'pupil':pid,'teacher':t1,'admin':'adminqa','rizal':rizal,'mabini':mabini}))
print(json.dumps({'checks_passed':count}))
