const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/assets/coach.js','utf8');
const effects=fs.readFileSync('public/assets/sfx.js','utf8');
async function main(){
 let open=false,created=0,suspended=0;const events={};
 class AudioContext{constructor(){created++;this.state='running';this.destination={};}createGain(){return {gain:{value:0},connect(){}}}suspend(){suspended++;this.state='suspended';return Promise.resolve();}}
 const c={window:{AudioContext},navigator:{audioSession:{type:'ambient'}},document:{querySelector:q=>q==='meta[name="bulig-tour"]'?{content:'{"role":"pupil","id":1}'}:null,querySelectorAll:()=>[],body:{classList:{contains:()=>open}},addEventListener:(name,fn)=>{events[name]=fn;}},localStorage:{getItem:()=>null},MutationObserver:class{observe(){}},location:{search:''},URLSearchParams,Date,setTimeout};
 vm.runInNewContext(effects,c);
 assert.equal(c.navigator.audioSession.type,'auto','sound script must not disable microphone capture');
 events.pointerdown();assert.equal(created,1);open=true;c.window.buligSfx.quiet();await Promise.resolve();assert.equal(suspended,1);
 events.pointerdown();assert.equal(created,1,'coach gestures do not re-open sound context');
 c.window.buligSfx.play('word');assert.equal(suspended,1);
 // Exercise array-like Safari results and final words arriving after the word timeout.
 let r,id=0;const timers=new Map();
 class Rec{constructor(){r=this;}start(){}abort(){}stop(){this.stopped=true;}}
 const ctx={Rec,microphoneSession(){},setTimeout:(fn,ms)=>{timers.set(++id,{fn,ms});return id;},clearTimeout:id=>timers.delete(id)};
 vm.createContext(ctx);vm.runInContext(source.slice(source.indexOf(' const listen=signal'),source.indexOf(' /* Listen while'))+';this.listen=listen;',ctx);
 const result=ctx.listen(new AbortController().signal);
 const first=[...timers.values()].find(t=>t.ms===9000);first.fn();assert.equal(r.stopped,true);
 r.onresult({results:{length:1,0:{length:1,0:{transcript:'last word'}}}});r.onend();
 assert.deepEqual(Array.from(await result),['last word']);
 console.log('PASS: microphone-compatible audio session, quiet sound context, array-like speech results, late single-word result.');
}
main().catch(e=>{console.error(e);process.exitCode=1;});
