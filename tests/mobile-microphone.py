"""Real Chromium capture with simulated mobile identity and deterministic speech."""
from pathlib import Path
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from threading import Thread
from playwright.sync_api import sync_playwright
import os
os.chdir(Path(__file__).resolve().parents[1])
class Quiet(SimpleHTTPRequestHandler):
 def log_message(self,*a):pass
server=ThreadingHTTPServer(('127.0.0.1',0),Quiet)
Thread(target=server.serve_forever,daemon=True).start()
url=f'http://127.0.0.1:{server.server_port}'
try:
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH'),args=['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream'])
  context=browser.new_context(permissions=['microphone'])
  page=context.new_page()
  for agent in ['iPhone Safari/605.1','Android Chrome/130','iPhone Safari/605.1']:
   page.goto(url)
   page.set_content('<form><div data-wc="cat hat" data-kind="read"><input name="wc[1]" data-wc-val></div></form>')
   page.evaluate('''agent=>{
    Object.defineProperty(navigator,'userAgent',{value:agent,configurable:true});
    Object.defineProperty(window,'speechSynthesis',{value:null,configurable:true});
    window.tracks=[];window.done=null;
    const gum=navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getUserMedia=async(...a)=>{const s=await gum(...a);tracks.push(...s.getTracks());return s};
    window.SpeechRecognition=class{start(){if(tracks.some(t=>t.readyState==='live'))throw Error('microphone conflict');this.onstart?.();this.t=setTimeout(()=>this.onresult?.({results:[Object.assign([{transcript:'cat hat'}],{isFinal:true})]}),300)}abort(){clearTimeout(this.t)}};
    document.querySelector('[data-wc]').addEventListener('wc:done',e=>window.done=e.detail);
   }''',agent)
   page.add_script_tag(url=url+'/public/assets/coach.js?wcsend=tap')
   page.locator('.wc-go').click();page.locator('.wc-guide-go:not([disabled])').click();page.locator('.wc-skip').click()
   assert page.evaluate('tracks.length===0')
   page.locator('.wc-start-reading').click()
   page.locator('.wc-read').click()
   page.locator('.wc-rec-start').click()
   page.locator('.wc-rec-stop').wait_for()
   page.wait_for_timeout(1800)
   page.locator('.wc-rec-stop').click()
   page.locator('.wc-ov').wait_for(state='detached')
   assert page.locator('[data-wc-val]').input_value()=='100|'
   assert page.evaluate('done.audio.length>200 && tracks.every(t=>t.readyState==="ended")')
   print('PASS',agent,'speech isolation, valid recorded audio, saved score and released microphone')
  browser.close()
finally:server.shutdown()
