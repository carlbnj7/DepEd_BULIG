const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const source=fs.readFileSync('public/assets/coach.js','utf8');
(async()=>{
 for(const mode of ['final','stalled','cancel']){
  let r,now=0,id=0;const timers=new Map(),words=[];
  const later=(fn,ms)=>{timers.set(++id,{fn,at:now+ms});return id;};
  const tick=ms=>{now+=ms;for(const [id,t]of [...timers].sort((a,b)=>a[1].at-b[1].at))if(t.at<=now&&timers.has(id)){timers.delete(id);t.fn();}};
  class Rec{constructor(){r=this;}start(){this.onstart?.();}abort(){this.aborted=true;this.onend?.();}stop(){this.stopped=true;if(mode==='final')later(()=>{this.onresult({results:[Object.assign([{transcript:'last words'}],{isFinal:true})]});this.onend();},100);}}
  const c={microphoneSession(){},Rec,APPLE:false,setTimeout:later,clearTimeout:id=>timers.delete(id),queueMicrotask};vm.createContext(c);
  vm.runInContext(source.slice(source.indexOf(' const listenAll='),source.indexOf('\n const words='))+';this.listenAll=listenAll;',c);
  const listener=c.listenAll(t=>words.push(t),()=>{},()=>{});const done=listener.finish();
  assert.equal(r.stopped,true);if(mode==='cancel')listener.stop();tick(2100);await done;
  assert.deepEqual(words,mode==='final'?['last words']:[]);assert.equal(timers.size,0);
 }
 console.log('PASS: Finish accepts late final words, does not restart, bounds stalled engines, and cancellation releases pending finish.');
})().catch(e=>{console.error(e);process.exitCode=1;});
