/* Deterministic recognition and recording lifecycle regressions; no network or real pupil data. */
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/assets/coach.js','utf8');
let clock=0,id=0,timers=new Map();const later=(fn,ms)=>{timers.set(++id,{fn,at:clock+ms});return id;};
const tick=ms=>{const end=clock+ms;for(;;){const entry=[...timers].filter(([,v])=>v.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];if(!entry)break;timers.delete(entry[0]);clock=entry[1].at;entry[1].fn();}clock=end;};
let instances=[];class Rec{constructor(){instances.push(this)}start(){this.onstart?.()}abort(){this.onend?.()}}
const ctx={Rec,APPLE:true,setTimeout:later,clearTimeout:i=>timers.delete(i),queueMicrotask:fn=>fn()};vm.createContext(ctx);
vm.runInContext(source.slice(source.indexOf(' const listenAll='),source.indexOf('\n const words='))+';this.listenAll=listenAll;',ctx);
let words=[],stops=[];let listener=ctx.listenAll(t=>words.push(t),()=>{},r=>stops.push(r));
const result=(r,values)=>r.onresult({results:values.map(([t,final])=>Object.assign([{transcript:t}],{isFinal:final}))});
let r=instances.at(-1);result(r,[['wrong',false]]);assert.deepEqual(words,[]);
result(r,[['cat',true],['wrong',false]]);result(r,[['cat',true],['hat',true]]);assert.deepEqual(words,['cat','hat']);
r.onend();tick(400);assert.equal(instances.length,2);result(r,[['stale',true]]);assert.deepEqual(words,['cat','hat']);
r=instances.at(-1);r.onerror({error:'network'});tick(1000);assert.equal(instances.length,3);assert.deepEqual(stops,[]);
tick(21000);assert.ok(instances.length>=4,'silent engine restarts');listener.stop();const count=instances.length;tick(60000);assert.equal(instances.length,count);
listener=ctx.listenAll(t=>words.push(t),()=>{},r=>stops.push(r));r=instances.at(-1);result(r,[['second',true]]);assert.equal(words.at(-1),'second');r.onerror({error:'not-allowed'});tick(60000);assert.deepEqual(stops,['not-allowed']);
function matching(mode='',vowel=false){const c={document:{querySelector:q=>q.includes('vowel')?(vowel?{}:null):{content:mode}}};vm.createContext(c);vm.runInContext(source.slice(source.indexOf(' const lev='),source.indexOf('\n /* One listen:'))+';this.near=near;',c);return c.near;}
assert.equal(matching()('10','ten'),true);assert.equal(matching()('den','then'),true);assert.equal(matching()('bad','bed'),false);assert.equal(matching('',true)('ship','sheep'),false);assert.equal(matching('accent')('bery','very'),true);assert.equal(matching()('','cat'),false);
function align(expected,heard){const c={T:expected,N:expected.length,S:[],p:0,sessBase:0,ended:false,norm:w=>w.toLowerCase(),near:matching(),Date,st:{},hint:{},show(){},finish(){},set(i,v){c.st[i]=v}};vm.createContext(c);vm.runInContext('let last=0;'+source.slice(source.indexOf('    const align='),source.indexOf('    const startListening='))+';this.align=align;',c);for(const h of heard)c.align(h);return c.st;}
assert.deepEqual(align(['cat','hat','mat'],['cat cat hat mat']),{0:'ok',1:'ok',2:'ok'});
assert.deepEqual(align(['cat','hat','mat'],['cat mat']),{0:'ok',1:'miss',2:'ok'});
assert.deepEqual(align(['the','cat','and','the','dog'],['the cat','and the dog']),{0:'ok',1:'ok',2:'ok',3:'ok',4:'ok'});
console.log('PASS: final-only incremental results, stale events, restarts/watchdog, permission stop, second run, accents/vowels, repeats/skips/order.');
