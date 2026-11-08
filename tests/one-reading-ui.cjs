const fs=require('node:fs'),assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
async function run(text){
 const dom=new JSDOM('<form><input name="activity_id" value="1"><div class="l2-card" data-n="0"><div data-wc="cat sat" data-kind="read"><input data-wc-val name="wc[0]"><button data-rec-go>Record</button></div></div></form>',{url:'https://bulig.test/public/?wcsend=tap',runScripts:'outside-only'});
 const w=dom.window,d=w.document;let starts=0,done;
 Object.defineProperty(d,'currentScript',{value:{src:'https://bulig.test/public/assets/coach.js'}});
 w.HTMLElement.prototype.scrollIntoView=function(){};
 w.FakeEngine=class {async load(){this.ready=true}async start(o){starts++;o.onDraft?.('cat sat');return {stop:async()=>({audio:'data:audio/wav;base64,YXVkaW8=',text,seconds:2}),cancel(){}}}dispose(){}};
 d.querySelector('[data-wc]').addEventListener('wc:done',e=>done=e.detail);
 const source=fs.readFileSync(require('node:path').join(__dirname,'../public/assets/coach.js'),'utf8').replace('await import(ENGINE_URL)','await Promise.resolve({ReadingEngine:window.FakeEngine})');w.eval(source);
 const tick=()=>new Promise(r=>setTimeout(r,20));
 d.querySelector('.wc-go').click();await tick();assert.match(d.body.textContent,/Ready to read with Max/);
 d.querySelector('.wc-guide-go').click();d.querySelector('.wc-skip').click();await tick();
 d.querySelector('.wc-start-reading').click();await tick();d.querySelector('.wc-local-finish').click();await tick();
 assert.equal(starts,1);assert.equal(d.querySelector('.wc-listen').hidden,false);
 d.querySelector('.wc-read').click();await tick();
 assert.equal(done.audio,'data:audio/wav;base64,YXVkaW8=');assert.equal(d.querySelector('[data-coach-audio]').value,done.audio);
 assert.equal(d.querySelector('[data-wc-val]').value,text===null?'na|':'100|');
 d.querySelector('.wc-again').click();await tick();assert.match(d.body.textContent,/Ready to read with Max/);
 dom.window.close();
}
(async()=>{await run('cat sat');await run(null);console.log('PASS: guide each Start; one capture; teacher hidden field and done event; score; audio preserved without automatic score.');})().catch(e=>{console.error(e);process.exitCode=1});
