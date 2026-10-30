"""Capture lifecycle with real browser audio; speech transcript mocked only to make scoring deterministic."""
import os,subprocess
from pathlib import Path
import run_tests as t
from playwright.sync_api import sync_playwright
os.chdir(t.ROOT)
assert t.test_database()
acc=t.php_cli('seed.php')
pid=int(acc['pupil1']['uid'])
t.mysql('-e',f"INSERT INTO activity_completion(pupil_id,activity_id,response,prompt_snapshot,activity_revision,status,submitted_at,score,max_score) SELECT {pid},id,'Read cat hat','Read these words: cat hat',1,'completed',NOW(),1,1 FROM activities ORDER BY id LIMIT 1; INSERT INTO reading_verifications(completion_id,pupil_id,activity_id,state,audio_paths,prompt_snapshot,response_snapshot,score,max_score) SELECT id,pupil_id,activity_id,'pending','{{\"0\":\"1/1-20261030000000-aabbccdd.webm\"}}',prompt_snapshot,response,score,max_score FROM activity_completion WHERE pupil_id={pid} ORDER BY id DESC LIMIT 1",db=t.DB['name'])
server=subprocess.Popen(['php','-S',f'127.0.0.1:{t.PORT}','-t','public'],env=t.env(),stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 assert t.wait_for_server()
 teacher=t.Client();teacher.login('teacher',acc['teacher']['id'],acc['teacher']['pw'])
 jar=next(h.cookiejar for h in teacher.op.handlers if hasattr(h,'cookiejar'))
 cookies=[{'name':c.name,'value':c.value,'url':t.BASE} for c in jar]
 Path('tests/ui-preview').mkdir(exist_ok=True)
 with sync_playwright() as pw:
  browser=pw.chromium.launch(args=['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream'])
  ctx=browser.new_context(permissions=['microphone'],viewport={'width':1440,'height':1000});ctx.add_cookies(cookies)
  page=ctx.new_page();page.goto(t.BASE+'?page=reading_reviews');page.wait_for_load_state('networkidle')
  assert page.get_by_role('heading',name='Reading verification').is_visible()
  page.screenshot(path='tests/ui-preview/reading-review-desktop.png',full_page=True)
  phone=browser.new_context(viewport={'width':390,'height':844},is_mobile=True);phone.add_cookies(cookies);m=phone.new_page();m.goto(t.BASE+'?page=reading_reviews');m.wait_for_load_state('networkidle')
  assert m.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
  m.screenshot(path='tests/ui-preview/reading-review-phone.png',full_page=True)
  def harness(apple=False):
   page.goto(t.BASE+'?page=reading_reviews')
   page.set_content('<form id="activity-form"><input name="activity_id" value="1"><div class="l2-card" data-n="1"><div data-wc="cat hat" data-kind="read"><input name="wc[1]" data-wc-val></div></div></form>')
   page.evaluate('''() => {
    window.testTracks=[];window.readingDone=null;
    const gum=navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getUserMedia=async(...a)=>{const s=await gum(...a);testTracks.push(...s.getTracks());return s;};
    Object.defineProperty(window,'speechSynthesis',{value:null,configurable:true});
    window.SpeechRecognition=class {start(){setTimeout(()=>{if(!this.off){this.onresult?.({results:[[{transcript:'cat hat'}]]});}},1600);}stop(){this.off=true;this.onend?.();}abort(){this.off=true;}};
    document.querySelector('[data-wc]').addEventListener('wc:done',e=>{window.readingDone=e.detail;});
   }''')
   if apple:page.evaluate("Object.defineProperty(navigator,'userAgent',{value:'iPhone Safari/605.1',configurable:true})")
   page.add_script_tag(url=t.BASE+'assets/coach.js')
   page.locator('.wc-go').click();page.locator('.wc-skip').click()
  harness()
  page.locator('.wc-read').wait_for(state='visible',timeout=15000);page.locator('.wc-read').click()
  page.locator('.wc-ov').wait_for(state='detached')
  assert page.locator('[data-coach-audio]').input_value().startswith('data:audio/')
  assert page.locator('[data-wc-val]').input_value()=='100|'
  assert page.evaluate('testTracks.every(t=>t.readyState==="ended")')
  assert page.evaluate('readingDone.audio.length>200')
  print('PASS: actual browser recording is saved with scored Word Coach attempt and microphone stops.')
  harness();page.locator('[data-recording-note]').filter(has_text='Recording for your teacher').wait_for(state='visible');page.locator('.wc-x').click();assert page.evaluate('testTracks.every(t=>t.readyState==="ended")')
  assert page.locator('[data-coach-audio]').input_value()==''
  print('PASS: cancel discards recording and releases microphone.')
  harness(True);page.locator('.wc-fin').wait_for(state='visible');page.wait_for_timeout(1100);page.locator('.wc-fin').click();page.locator('.wc-read').click()
  page.locator('.wc-ov').wait_for(state='detached')
  assert page.locator('[data-coach-audio]').input_value().startswith('data:audio/')
  assert page.locator('[data-wc-val]').input_value()=='na|'
  assert page.evaluate('testTracks.every(t=>t.readyState==="ended")')
  print('PASS: Safari path records real audio without inventing a pronunciation percentage (device testing still required).')
  browser.close()
finally:
 server.terminate();server.wait(timeout=10)
 t.mysql('-e',f"DROP DATABASE IF EXISTS `{t.DB['name']}`")
