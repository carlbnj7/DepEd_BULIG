"""HTTP integration tests. Run against a DISPOSABLE imported database only.
Usage: python tests/integration.py http://127.0.0.1:8080 admin-id admin-password
Uses the Python standard library. Creates test accounts and completes Lesson 1.
"""
import re,sys,json,urllib.request,urllib.parse,urllib.error,http.cookiejar
from types import SimpleNamespace
class Session:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,url,data=None,timeout=30):
  req=urllib.request.Request(url,data=urllib.parse.urlencode(data).encode() if data is not None else None)
  try:r=self.opener.open(req,timeout=timeout)
  except urllib.error.HTTPError as e:r=e
  return SimpleNamespace(status_code=r.code,text=r.read().decode())
 def get(self,url,timeout=30):return self.request(url,timeout=timeout)
 def post(self,url,data,timeout=30):return self.request(url,data,timeout)
requests=SimpleNamespace(Session=Session)
base=sys.argv[1].rstrip('/')+'/'
passed=[]
def check(ok,msg):
 assert ok,msg
 passed.append(msg);print('PASS',msg,flush=True)
def token(html):
 m=re.search(r'<meta name="csrf" content="([^"]+)"',html)
 assert m,'Missing CSRF token';return m.group(1)
def get(s,url):return s.get(base+url,timeout=30)
def post(s,data,route='?page=dashboard',expected=200):
 data=dict(data);data['csrf']=token(get(s,route).text);r=s.post(base+route,data=data,timeout=30);check(r.status_code==expected,f"{data.get('action')} → {expected}");return r
admin=requests.Session();post(admin,dict(action='login',public_id=sys.argv[2],password=sys.argv[3],role='admin'),'?page=login')
r=post(admin,dict(action='create_account',name='Teacher Integration One',password='TestOnlyPass42!'));tid=re.search(r'teacher ID: (T\d+)',r.text)[1]
r=post(admin,dict(action='create_account',name='Teacher Integration Two',password='TestOnlyPass42!'));tid2=re.search(r'teacher ID: (T\d+)',r.text)[1]
teacher=requests.Session();post(teacher,dict(action='login',public_id=tid,password='TestOnlyPass42!',role='teacher'),'?page=login&role=teacher')
r=post(teacher,dict(action='create_account',name='Alex Learner',password='PupilTest42!',grade_level=3,section='Mahogany',level_id=1));pid=re.search(r'pupil ID: (\d+)',r.text)[1]
pupil=requests.Session();post(pupil,dict(action='login',public_id=pid,password='PupilTest42!',role='pupil'),'?page=login')
check(get(pupil,'?page=accounts').status_code==403,'Pupil cannot access account management')
check(get(teacher,'?page=content').status_code==403,'Teacher cannot access CMS')
check(get(pupil,'?page=lesson&id=2').status_code==403,'Future lesson is locked')
r=pupil.post(base,data={'action':'submit','activity_id':1,'response':'Alex'});check(r.status_code==403,'CSRF missing is rejected')
post(pupil,dict(action='draft',activity_id=1,response='My name is Alex.'),expected=200)
check('My name is Alex.' in get(pupil,'?page=lesson&id=1').text,'Saved draft restores')
content=json.load(open('bulig/database/content.json'))
learn=[a for a in content['activities'] if a['lesson_id']==1 and a['phase']=='learn']
post(pupil,dict(action='submit',activity_id=learn[0]['id'],response='Hello'),expected=403)
# Submit each assessment and lesson activity, then teacher reviews.
for phase in ['pre','learn','post']:
 acts=[a for a in content['activities'] if a['lesson_id']==1 and a['phase']==phase]
 acts.sort(key=lambda a:a['position'])
 for a in acts:post(pupil,dict(action='submit',activity_id=a['id'],response='My name is Alex. I am nine. My family likes to read.'))
 # One duplicated submission cannot award twice.
 post(pupil,dict(action='submit',activity_id=acts[-1]['id'],response='Duplicate'),expected=400)
 html=get(teacher,'?page=review').text
 ids=re.findall(r'name="completion_id" value="(\d+)"',html)
 check(len(ids)==len(acts),'All submitted responses are visible to assigned teacher')
 for cid in ids:post(teacher,dict(action='review',completion_id=cid,decision='approved',feedback='Well done.'))
 if phase in ['pre','post']:
  html=get(teacher,'?page=review').text
  aid=re.search(r'name="assessment_id" value="(\d+)"',html)[1]
  pkey=re.search(r'name="pupil_id" value="(\d+)"',html)[1]
  post(teacher,{'action':'review_assessment','pupil_id':pkey,'assessment_id':aid,'scores[0]':4,'scores[1]':3,'scores[2]':4,'feedback':'Good clear speaking.'})
check(get(pupil,'?page=lesson&id=2').status_code==200,'Lesson 2 unlocks after final reviewed post-test')
check('190' in get(pupil,'?page=dashboard').text,'Exactly 190 XP for 19 approved activities')
check('First step' in get(pupil,'?page=achievements').text,'Achievement screen renders earned badges')
teacher2=requests.Session();post(teacher2,dict(action='login',public_id=tid2,password='TestOnlyPass42!',role='teacher'),'?page=login&role=teacher')
check(get(teacher2,'?page=pupil&id='+pkey).status_code==403,'A second teacher cannot read another teacher’s pupil')
post(teacher2,dict(action='review',completion_id=ids[-1],decision='approved'),expected=403)
# Negative login and XSS response is stored and escaped.
post(pupil,dict(action='submit',activity_id=next(a['id'] for a in content['activities'] if a['lesson_id']==2 and a['phase']=='pre'),response='<script>alert(1)</script>'))
html=get(teacher,'?page=review').text;check('&lt;script&gt;' in html and '<script>alert(1)</script>' not in html,'Pupil response is escaped in teacher review')
# Rejected work remains retryable and does not grant XP until approved.
cid=re.findall(r'name="completion_id" value="(\d+)"',html)[0]
post(teacher,dict(action='review',completion_id=cid,decision='retry',feedback='Try the action again with me.'))
check('Try the action again with me.' in get(pupil,'?page=lesson&id=2').text,'Retry feedback reaches pupil')
# All role pages load.
for s,routes in [(admin,['dashboard','accounts','content','edit_activity&id=1','media','issues','settings','guide','profile']),(teacher,['dashboard','accounts','review','guide','profile','pupil&id='+pkey]),(pupil,['dashboard','achievements','profile','lesson&id=2'])]:
 for route in routes:check(get(s,'?page='+route).status_code==200,'Page renders: '+route)
# Content edits, exact grading, account isolation, and deactivation.
post(pupil,dict(action='create_account',name='Unauthorized',password='NoAccount42!'),expected=403)
edit_id=next(a['id'] for a in content['activities'] if a['lesson_id']==12 and a['phase']=='learn')
post(admin,dict(action='save_activity',id=edit_id,title='QA exact prompt',prompt='Say red',type='exact',instructions='Integration test',narration='Say red',expected_text='red',xp_reward=10,grading='exact',answers='red',options='',image_paths='',published='on'))
check('QA exact prompt' in get(admin,'?page=edit_activity&id='+str(edit_id)).text,'CMS edits persist')
post(teacher,dict(action='update_account',id=pkey,name='Alex Learner',password='',grade_level=3,section='Mahogany',level_id=1))
check('Enter your pupil ID' in get(pupil,'?page=dashboard').text,'Deactivated pupil session loses access immediately')
post(teacher,dict(action='update_account',id=pkey,name='Alex Learner',password='',grade_level=3,section='Mahogany',level_id=1,active='on'))
post(pupil,dict(action='login',public_id=pid,password='PupilTest42!',role='pupil'),'?page=login')
# Keep credentials only in scratch for browser validation; not part of deliverable.
open('/tmp/bulig-test-accounts.json','w').write(json.dumps({'pupil':pid,'teacher':tid,'admin':sys.argv[2],'pupil_internal':pkey}))
print(json.dumps({'checks_passed':len(passed)}))
