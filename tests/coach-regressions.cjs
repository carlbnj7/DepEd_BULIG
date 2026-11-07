/* Deterministic recognition and recording lifecycle regressions; no network or real pupil data. */
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/assets/coach.js','utf8');
let clock=0,id=0,timers=new Map();const later=(fn,ms)=>{timers.set(++id,{fn,at:clock+ms});return id;};
const tick=ms=>{const end=clock+ms;for(;;){const entry=[...timers].filter(([,v])=>v.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];if(!entry)break;timers.delete(entry[0]);clock=entry[1].at;entry[1].fn();}clock=end;};
let instances=[];class Rec{constructor(){instances.push(this)}start(){this.onstart?.()}abort(){this.onend?.()}}
const ctx={microphoneSession(){},Rec,APPLE:true,setTimeout:later,clearTimeout:i=>timers.delete(i),queueMicrotask:fn=>fn()};vm.createContext(ctx);
vm.runInContext(source.slice(source.indexOf(' const listenAll='),source.indexOf('\n const words='))+';this.listenAll=listenAll;',ctx);
let words=[],stops=[],previews=[];let listener=ctx.listenAll(t=>words.push(t),()=>{},r=>stops.push(r),t=>previews.push(t));
const result=(r,values)=>r.onresult({results:values.map(([t,final])=>Object.assign([{transcript:t}],{isFinal:final}))});
let r=instances.at(-1);result(r,[['wrong',false]]);assert.deepEqual(words,[]);assert.equal(previews.at(-1),'wrong');result(r,[['cat',false]]);assert.equal(previews.at(-1),'cat');assert.deepEqual(words,[]);
result(r,[['cat',true],['wrong',false]]);result(r,[['cat',true],['hat',true]]);assert.deepEqual(words,['cat','hat']);
r.onend();assert.equal(previews.at(-1),'');tick(400);assert.equal(instances.length,2);result(r,[['stale',true]]);assert.deepEqual(words,['cat','hat']);
r=instances.at(-1);r.onerror({error:'network'});tick(1000);assert.equal(instances.length,3);assert.deepEqual(stops,[]);
tick(23000);assert.ok(instances.length>=4,'silent engine restarts');listener.stop();const count=instances.length;tick(60000);assert.equal(instances.length,count);
listener=ctx.listenAll(t=>words.push(t),()=>{},r=>stops.push(r));r=instances.at(-1);result(r,[['second',true]]);assert.equal(words.at(-1),'second');r.onerror({error:'not-allowed'});tick(60000);assert.deepEqual(stops,['not-allowed']);
function matching(mode='',vowel=false){const c={document:{querySelector:q=>q.includes('vowel')?(vowel?{}:null):{content:mode}}};vm.createContext(c);vm.runInContext(source.slice(source.indexOf(' const lev='),source.indexOf('\n /* One listen:'))+';this.near=near;',c);return c.near;}
assert.equal(matching()('10','ten'),true);assert.equal(matching()('den','then'),true);assert.equal(matching()('bad','bed'),false);assert.equal(matching('',true)('ship','sheep'),false);assert.equal(matching('accent')('bery','very'),true);assert.equal(matching()('','cat'),false);
// Name aliases are scoped to the story and disabled for vowel-focused lessons.
assert.equal(matching()('team','tim',true),true);
for(const heard of ['tims','teams',"team's",'themes',"theme's"])assert.equal(matching()(heard,"tim's",true),true);
assert.equal(matching()('team','tim'),false);
assert.equal(matching()('themes',"tim's"),false);
assert.equal(matching('',true)('team','tim',true),false);
assert.equal(matching()('theme','tim',true),false);
assert.equal(matching()('team',"tim's",true),false);
assert.equal(matching()('tim','team',true),false);
assert.equal(matching()('ship','sheep',true),false);
function align(expected,heard,timStory=false){const c={timStory,T:expected,N:expected.length,S:[],p:0,sessBase:0,alive:true,ended:false,clearPreview(){},previewWords:[],W:[],norm:w=>w.toLowerCase(),near:matching(),Date,st:{},hint:{},show(){},finish(){},set(i,v){c.st[i]=v}};vm.createContext(c);vm.runInContext('let last=0;'+source.slice(source.indexOf('    const align='),source.indexOf('    const startListening='))+';this.align=align;',c);for(const h of heard)Array.isArray(h)?c.align(h[0],true,h):c.align(h);return c.st;}
assert.deepEqual(align(['cat','hat','mat'],['cat cat hat mat']),{0:'ok',1:'ok',2:'ok'});
assert.deepEqual(align(['cat','hat','mat'],['cat mat']),{0:'ok',1:'miss',2:'ok'});
assert.deepEqual(align(['the','cat','and','the','dog'],['the cat','and the dog']),{0:'ok',1:'ok',2:'ok',3:'ok',4:'ok'});
assert.deepEqual(align(["tim's",'ball','tim','has','a','red','ball'],['themes ball team has a red ball'],true),{0:'ok',1:'ok',2:'ok',3:'ok',4:'ok',5:'ok',6:'ok'});
console.log('PASS: final-only incremental results, stale events, restarts/watchdog, permission stop, second run, accents/vowels, repeats/skips/order.');

function previewAlignment(){
 const marks=[new Set(),new Set(),new Set()];
 const c={timStory:false,T:['cat','hat','mat'],N:3,S:[],p:0,alive:true,ended:false,norm:w=>w.toLowerCase(),near:matching(),Date,hint:{},show(){},finish(){c.finished=true;},set(i,v){c.S[i]=v;marks[i].delete('wc-heard');},W:marks.map(m=>({classList:{add:v=>m.add(v),remove:v=>m.delete(v)},scrollIntoView(){}}))};
 vm.createContext(c);vm.runInContext('let last=0;'+source.slice(source.indexOf('    let previewWords='),source.indexOf('    const startListening='))+';this.align=align;',c);
 c.align('cat hat mat',false);assert.deepEqual(marks.map(m=>m.has('wc-heard')),[true,true,true]);assert.equal(c.p,0);assert.equal(c.S.length,0);assert.equal(c.finished,undefined);
 c.align('cat',false);assert.deepEqual(marks.map(m=>m.has('wc-heard')),[true,false,false]);
 c.align('cat');assert.equal(c.p,1);assert.equal(c.S[0],'ok');assert.equal(marks[0].has('wc-heard'),false);
 c.align('hat',false);assert.equal(c.S[1],undefined);c.align('',false);assert.equal(marks[1].has('wc-heard'),false);
 c.ended=true;c.align('hat mat',false);assert.equal(marks[1].has('wc-heard'),false);
}
previewAlignment();
console.log('PASS: immediate provisional highlights, corrected hypotheses, final-only score/progress and cleared previews.');

async function recordingCase(mode){
 let track={readyState:'live',muted:false,addEventListener(){},stop(){this.readyState='ended';}},closed=0;
 class Recorder{static isTypeSupported(t){return t==='audio/mp4'}constructor(){this.state='inactive';this.mimeType='audio/mp4'}start(){this.state='recording'}pause(){this.state='paused'}resume(){this.state='recording'}stop(){this.state='inactive';if(mode==='stalled')return;queueMicrotask(()=>{this.ondataavailable({data:new Blob([mode==='empty'?'':new Uint8Array(800)])});this.onstop();});}}
 class Decoder{decodeAudioData(){if(mode==='corrupt')return Promise.reject(Error('corrupt'));return Promise.resolve({duration:mode==='short'?0.1:2,numberOfChannels:1,getChannelData:()=>new Float32Array(1000).fill(mode==='silent'?0:0.2)})}close(){closed++;return Promise.resolve()}}
 class Reader{readAsDataURL(){this.result='data:audio/mp4;base64,VALIDATED';this.onload()}}
 const c={navigator:{audioSession:{type:'ambient'},mediaDevices:{getUserMedia:async()=>{assert.equal(c.navigator.audioSession.type,'auto','capture must release playback-only audio mode');return {getTracks:()=>[track],getAudioTracks:()=>[track]};}}},window:{MediaRecorder:Recorder,AudioContext:Decoder},MediaRecorder:Recorder,APPLE:true,Blob,FileReader:Reader,Uint8Array,Number,Math,setTimeout:later,clearTimeout:i=>timers.delete(i)};
 vm.createContext(c);vm.runInContext(source.slice(source.indexOf(' const microphoneSession='),source.indexOf(' // Record only'))+source.slice(source.indexOf(' async function captureReading'),source.indexOf('\n /* v208 the red bars'))+';this.captureReading=captureReading;',c);
 const capture=await c.captureReading(true),pending=capture.stop();if(mode==='stalled')tick(15001);const value=await pending;
 assert.equal(!!value,mode==='valid');assert.equal(track.readyState,'ended');if(['valid','silent','short','corrupt'].includes(mode))assert.equal(closed,1);return value;
}
(async()=>{for(const mode of ['valid','empty','silent','short','corrupt','stalled'])await recordingCase(mode);console.log('PASS: final audio chunks, decoded signal/duration, empty/silent/corrupt rejection, stopped tracks and bounded finalization.');})().catch(e=>{console.error(e);process.exitCode=1;});

async function mobileStart(separate, support=false, available=true){
 let handler,captures=0,listens=0,fallback=0;
 const c={SEPARATE:separate,practice:false,recording:null,alive:true,Rec:available?{}:null,SPM:support?'support':'',IMG:()=>'',svg:{mic:''},hush(){},noListen(){fallback++},beginListening(){listens++},captureReading:async()=>{captures++;return {resume(){}}},$:()=>({}),box:{innerHTML:'',querySelector:()=>({set onclick(fn){handler=fn}})}};
 vm.createContext(c);vm.runInContext(source.slice(source.indexOf('   async function pass()'),source.indexOf('   function beginListening()'))+';this.pass=pass;',c);
 await c.pass();assert.equal(captures,0);assert.equal(listens,0);const pending=handler();if(separate&&!support&&available)assert.equal(listens,1,'mobile starts synchronously from the tap');await pending;assert.equal(captures,!separate&&!support&&available?1:0);assert.equal(listens,support||!available?0:1);assert.equal(fallback,support||!available?1:0);
}
(async()=>{await mobileStart(true);await mobileStart(true,true);await mobileStart(true,false,false);await mobileStart(false);console.log('PASS: mobile gesture starts speech synchronously without opening recorder; desktop retains simultaneous capture.');})().catch(e=>{console.error(e);process.exitCode=1;});




assert.equal(matching()('mothers', "mother's"),true);
assert.equal(matching()('mother', "mother's"),false);
assert.deepEqual(align(['cat','sat'],[['cap sat','cat sat']]),{0:'ok',1:'ok'});
assert.deepEqual(align(['one','two','three','four','five','cat','sat'],['cat sat']),{0:'miss',1:'miss',2:'miss',3:'miss',4:'miss',5:'ok',6:'ok'});
assert.deepEqual(align(['one','two','three','four','five','cat','sat'],['cat']),{});
console.log('PASS: complete alternate hypotheses, apostrophes, anchored recovery and isolated-word rejection.');
async function preserveRecording(){
 let cancelled=0,paused=0,resumed=0,captures=0,finished=0;
 const buttons={};const c={alive:true,stream:{stop(){}},recording:{valid:()=>true,cancel(){cancelled++},pause(){paused++},resume(){resumed++}},ov:{classList:{remove(){}}},t0:1000,Date:{now:()=>5000},W:[],paint(){},$:()=>({}),IMG:()=>'',svg:{mic:'',ok:''},box:{innerHTML:'',querySelector:s=>buttons[s]||(buttons[s]={})},close(){},open(){},captureReading:async()=>{captures++},all:['cat'],got:[],fails:[],summary(){finished++}};
 vm.createContext(c);vm.runInContext(source.slice(source.indexOf('   function noListen('),source.indexOf('   /* 4) Stars'))+';this.noListen=noListen;',c);
 c.noListen('network');assert.equal(cancelled,0);assert.equal(paused,1);
 await buttons['.wc-audio-only'].onclick();assert.equal(captures,0);assert.equal(resumed,1);assert.match(c.box.innerHTML,/earlier audio is kept/);
 buttons['.wc-fin'].onclick();assert.equal(finished,1);assert.equal(c.secs,4);assert.equal(c.got[0],-1);
}
preserveRecording().then(()=>console.log('PASS: recognition failure preserves and resumes recording without an automatic score.')).catch(e=>{console.error(e);process.exitCode=1;});
