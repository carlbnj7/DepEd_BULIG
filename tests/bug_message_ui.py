"""Isolated browser checks using generated test accounts, never live accounts."""
import os,re,subprocess,time,urllib.parse
from pathlib import Path
import run_tests as t
from playwright.sync_api import sync_playwright
os.chdir(t.ROOT)
assert t.test_database()
acc=t.php_cli('seed.php')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{t.PORT}','-t','public'],env=t.env(),stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 assert t.wait_for_server()
 a,p=t.Client(),t.Client()
 a.login('admin',acc['admin']['id'],acc['admin']['pw'])
 a.req('index.php',{'action':'admin_pin','pin':acc['admin']['pin'],'csrf':a.token()})
 p.login('pupil',acc['pupil1']['id'],acc['pupil1']['pw'])
 _,_,preview=a.req('index.php',{'action':'message_preview','scope':'person','person':acc['pupil1']['uid'],'subject':'A little reading every day','body':'Hello! Please read your next story today. Your teacher is here to help.','csrf':a.token('?page=messages')})
 token=re.search(r'name="token" value="([0-9a-f]+)"',preview).group(1)
 _,url,_=a.req('index.php',{'action':'message_send','token':token,'csrf':a.token('?page=messages')})
 mid=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)['id'][0]
 Path('tests/ui-preview').mkdir(exist_ok=True)
 def cookies(client):
  jar=next(h.cookiejar for h in client.op.handlers if hasattr(h,'cookiejar'))
  return [{'name':c.name,'value':c.value,'url':t.BASE} for c in jar]
 with sync_playwright() as pw:
  browser=pw.chromium.launch()
  desktop=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
  desktop.add_cookies(cookies(a));page=desktop.new_page();page.goto(t.BASE+'?page=messages&id='+mid);page.wait_for_load_state('networkidle')
  left=page.locator('.msg-list').bounding_box();right=page.locator('.msg-panel').bounding_box();assert left and right and left['x']+left['width']<=right['x']+2
  assert page.locator('.msg-item').count()==1
  page.screenshot(path='tests/ui-preview/messages-desktop.png',full_page=True)
  phone=browser.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True,reduced_motion='reduce');phone.add_cookies(cookies(p));m=phone.new_page();m.goto(t.BASE+'?page=messages');m.wait_for_load_state('networkidle')
  assert m.locator('.msg-list').is_visible() and not m.locator('.msg-panel').is_visible()
  m.locator('.msg-item').first.click();m.wait_for_load_state('networkidle')
  assert m.locator('.msg-panel').is_visible() and not m.locator('.msg-list').is_visible()
  assert m.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
  m.screenshot(path='tests/ui-preview/messages-phone.png',full_page=True)
  # Verify a real pending report update opens an accessible dialog and can be acknowledged.
  _,reporturl,_=p.req('index.php',{'action':'bug_report_submit','note':'The speaker did not start.','csrf':p.token('?page=bug_report')})
  rid=urllib.parse.parse_qs(urllib.parse.urlsplit(reporturl).query)['id'][0]
  a.req('index.php',{'action':'bug_report_update','id':rid,'status':'processing','reply':'We are checking the sound. Thank you for telling us.','version':'0','csrf':a.token('?page=bugs')})
  page.goto(t.BASE+'?page=bug_report&id='+rid);page.wait_for_load_state('networkidle')
  conversation=page.locator('.bug-conversation').bounding_box();moderation=page.locator('.bug-moderation').bounding_box()
  assert conversation and moderation and conversation['x']+conversation['width']<=moderation['x']+2
  assert page.locator('input[name=deduct_xp]').count()==0
  assert page.get_by_text('Automatic false-report deduction: 10 XP').is_visible()
  page.screenshot(path='tests/ui-preview/bug-panels-desktop.png',full_page=True)
  admin_phone=browser.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True,reduced_motion='reduce');admin_phone.add_cookies(cookies(a));ap=admin_phone.new_page();ap.goto(t.BASE+'?page=bug_report&id='+rid);ap.wait_for_load_state('networkidle')
  conversation=ap.locator('.bug-conversation').bounding_box();moderation=ap.locator('.bug-moderation').bounding_box()
  assert conversation and moderation and moderation['y']>=conversation['y']+conversation['height']-2
  assert ap.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
  ap.screenshot(path='tests/ui-preview/bug-panels-phone.png',full_page=True)
  m.goto(t.BASE+'?page=bug_report&id='+rid);m.locator('.bug-update-dialog').wait_for(state='visible');assert m.locator('.bug-update-dialog').get_by_text('We are checking the sound. Thank you for telling us.').is_visible()
  m.locator('.bug-update-dialog').get_by_role('button',name='Got it').click();m.locator('.bug-update-dialog').wait_for(state='detached')
  pid=int(acc['pupil1']['uid']);t.mysql('-e',f'INSERT INTO pupil_xp(pupil_id,total) VALUES({pid},25) ON DUPLICATE KEY UPDATE total=25',db=t.DB['name'])
  ap.select_option('[name=status]','rejected');ap.fill('[name=reply]','Reviewed with the teacher: this was a confirmed false report.');ap.check('[name=confirm_false]');ap.on('dialog',lambda dialog:dialog.accept());ap.get_by_role('button',name='Save and notify').click();ap.wait_for_load_state('networkidle')
  balance=t.mysql('-N','-e',f'SELECT total FROM pupil_xp WHERE pupil_id={pid}',db=t.DB['name']).stdout.strip();assert balance=='15',balance
  m.reload();m.locator('.bug-update-dialog').wait_for(state='visible');assert 'XP deducted: 10.' in m.locator('.bug-update-dialog').inner_text()
  m.locator('.bug-update-dialog').get_by_role('button',name='Got it').click();m.locator('.bug-update-dialog').wait_for(state='detached')
  print('PASS: desktop/mobile bug panels, fixed server-side XP deduction through the real form, and rejection popup.')
  print('PASS: desktop panels, phone list/detail layout, no horizontal overflow, and actual report-update popup acknowledgment.')
  browser.close()
finally:
 server.terminate();server.wait(timeout=10)
 t.mysql('-e',f"DROP DATABASE IF EXISTS `{t.DB['name']}`")

