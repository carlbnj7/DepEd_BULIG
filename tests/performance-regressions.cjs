const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const app=fs.readFileSync('public/assets/app.js','utf8');
const fragment=app.slice(app.indexOf('function scan(root){'),app.indexOf("document.addEventListener('click',e=>{if(e.target.closest('[data-native-next]"));
let observe,whole=0,subtree=0,setupCount=0;
const document={nodeType:9,body:{},querySelectorAll(){whole++;return [];}};
vm.runInNewContext(fragment,{document,Set,setup(){setupCount++;},MutationObserver:class{constructor(fn){observe=fn;}observe(){}}});
for(let i=0;i<100;i++)observe([{addedNodes:[{nodeType:3}]}]);
assert.equal(whole,1,'text updates do not rescan document');
const board={nodeType:1,isConnected:true,parentElement:null,matches:()=>true,closest(){return this;},querySelectorAll(){subtree++;return [];}};
observe([{addedNodes:[board]}]);assert.equal(setupCount,1,'directly added board initialized');
const svg={nodeType:1,isConnected:true,matches:()=>false,closest:()=>board};observe([{addedNodes:[svg]}]);assert.equal(subtree,0,'line drawing does not scan board');
const container={nodeType:1,isConnected:true,parentElement:null,matches:()=>false,closest:()=>null,querySelectorAll(){subtree++;return [board];}};
observe([{addedNodes:[container]}]);assert.equal(setupCount,2,'nested board initialized');assert.equal(whole,1);
const offline=fs.readFileSync('public/assets/offline.js','utf8');
const fragments=[...offline.matchAll(/const tx=\(mode,fn\)=>idb\(\)\.then\(db=>new Promise\(\(res,rej\)=>\{[\s\S]*?\n \}\)\);/g)];assert.equal(fragments.length,2);
(async()=>{
 for(const [i,{0:code}] of fragments.entries())for(const result of ['complete','error','abort','throw','callbackThrow']){
  let closed=0,t={error:null,objectStore(){return {};},abort(){if(this.onabort)this.onabort();}};
  const db={close(){closed++;},transaction(){if(result==='throw')throw Error('transaction');return t;}};
  const ctx={idb:()=>Promise.resolve(db),Error};vm.runInNewContext(code+';globalThis.run=tx;',ctx);
  const pending=ctx.run('readonly',()=>{if(result==='callbackThrow')throw Error('callback');return {result:42};});
  await Promise.resolve();
  if(result==='complete')t.oncomplete();else if(result==='error'){t.error=Error('storage');t.onerror();}else if(result==='abort')t.onabort();
  if(result==='complete')assert.equal(await pending,42);else await assert.rejects(pending);
  assert.equal(closed,1,`connection ${i} closed once on ${result}`);
 }
 const bug=fs.readFileSync('public/assets/bug-updates.js','utf8');
 const poll=bug.slice(bug.indexOf(' async function poll(){'),bug.indexOf('\n poll();setInterval'));
 let timers=new Map(),id=0,fetches=0,blocking=true;
 const ctx={document:{hidden:false},navigator:{onLine:true},busyNow:()=>blocking,setTimeout:fn=>{timers.set(++id,fn);return id;},clearTimeout:n=>timers.delete(n),fetch:async()=>{fetches++;return {ok:true,json:async()=>({})};},show(){}};
 vm.runInNewContext('let busy=false,shown=null,retryTimer=null;'+poll+';globalThis.run=poll;',ctx);
 for(let i=0;i<20;i++)await ctx.run();assert.equal(timers.size,1,'deferred poll deduplicated');
 blocking=false;await ctx.run();assert.equal(timers.size,0);assert.equal(fetches,1);
 console.log('PASS: incremental DOM scanning, pupil/teacher IndexedDB cleanup, single deferred poll');
})().catch(e=>{console.error(e);process.exitCode=1;});
