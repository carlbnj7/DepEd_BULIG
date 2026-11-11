/* Run: node tests/audit-regressions.cjs. No server or pupil data required. */
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),read=p=>fs.readFileSync(path.join(root,p),'utf8');
const offline=read('public/assets/offline.js'),app=read('public/assets/app.js');
async function upload(status,data={saved:true}){
 const start=offline.indexOf('if(res.ok&&typeof data.saved'),end=offline.indexOf('\n   render();',start);
 const ctx={res:{ok:status>=200&&status<300,status},data,it:{key:'answer',kind:'answer',aid:1},state:{sent:0},xp:0,deleted:false,dropDone(){}};
 ctx.del=async()=>{ctx.deleted=true};vm.createContext(ctx);await vm.runInContext('(async()=>{for(const once of [1]){'+offline.slice(start,end)+'\n}})()',ctx);return ctx;
}
async function download(failure){
 const maps=new Map(),old=new Map([['old-page',new Response('previous download')]]);maps.set('bulig-off-1-L2',old);
 const caches={async open(name){if(!maps.has(name))maps.set(name,new Map());const m=maps.get(name);return {async put(k,v){m.set(k,v)},async match(k){return m.get(k)?.clone()},async keys(){return [...m.keys()]}}},async delete(n){return maps.delete(n)}};
 let saved=false;const ui={cancelled:failure==='cancel',progress(){}};
 const ctx={caches,uid:'1',Date,Math,URL,Response,base:'https://example.test/public/',BUILD:'test',abs:u=>new URL(u,'https://example.test/public/').href,
 meta:()=>({levels:{2:{build:'old'}}}),saveMeta:()=>{saved=true},DOMParser:class{parseFromString(){return {querySelectorAll(){return []}}}},
 fetch:async u=>{if(u.startsWith('?page=offline_manifest'))return new Response(JSON.stringify({pages:['?page=lesson&id=1'],lessons:[],assets:['assets/a.png'],title:'Test'}));
 if(failure==='network')throw Error('network');const fail=(failure==='page'&&u.startsWith('?'))||(failure==='asset'&&u.endsWith('.png'));
 return {ok:!fail,url:failure==='login'?'https://example.test/?page=login':'https://example.test/'+u,text:async()=>'<main></main>',clone:()=>new Response('asset')};}};
 vm.createContext(ctx);vm.runInContext(offline.slice(offline.indexOf(' async function saveLevel'),offline.indexOf(' async function removeLevel')),ctx);
 let rejected=false;try{await ctx.saveLevel(2,ui)}catch(e){rejected=true}
 assert.equal(saved,!failure);assert.equal(rejected,!!failure&&failure!=='cancel');assert.equal(await old.get('old-page').text(),'previous download');assert.equal([...maps.keys()].some(k=>k.startsWith('bulig-stage-')),false);
}
(async()=>{
 for(const status of [403,429,500,503])assert.equal((await upload(status)).deleted,false);
 for(const data of [{},{error:'not saved'}])assert.equal((await upload(200,data)).deleted,false);
 assert.equal((await upload(200,{saved:true})).deleted,true);assert.equal((await upload(200,{saved:false})).deleted,true);
 for(const failure of ['', 'network','page','asset','login','cancel'])await download(failure);
 const start=app.indexOf('function draft(slow)'),end=app.indexOf("document.addEventListener('visibilitychange'",start);
 const ctx={form:{dataset:{draft:'on'},addEventListener(name,fn){ctx.input=fn}},draftTimer:null,submitting:false,draftSlow:false,clearTimeout(){},setTimeout(fn,ms){ctx.delay=ms;return 1},saveDraft(){}};vm.createContext(ctx);
 vm.runInContext(app.slice(start,end)+app.match(/form.addEventListener\('input',.*?;/)[0],ctx);ctx.input({type:'input'});assert.equal(ctx.delay,650);ctx.draftTimer=null;ctx.draft(true);assert.equal(ctx.delay,15000);
 // Readiness: instant reveal on DOM-ready; bounded fallback if scripts stall.
 for(const trigger of ['DOMContentLoaded','pageshow','timeout']){const classes=new Set(),events={};const c={document:{documentElement:{classList:{add:x=>classes.add(x),remove:x=>classes.delete(x)}},addEventListener:(e,f)=>events[e]=f},window:{addEventListener:(e,f)=>events[e]=f},clearTimeout(){},setTimeout(fn){events.timeout=fn;return 1}};vm.runInNewContext(read('public/assets/lesson-ready.js'),c);assert(classes.has('lesson-enhancing'));events[trigger]();assert.equal(classes.size,0);}
 console.log('PASS: failed uploads retained; valid acknowledgments handled; successful, failed and cancelled downloads; old download retained; stage cleanup; typing/drawing delays; readiness and fallback.');
})().catch(e=>{console.error(e);process.exitCode=1});
