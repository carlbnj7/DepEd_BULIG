/* BULIG offline mode (v1)
   - "Save for offline" stores a level's pages and pictures on this device.
   - Answers given without internet wait on the device (IndexedDB) and are sent when it is back online.
   - Answers are sent in the order they were given, with the real time they were given. */
(function(){
 if(!('caches' in window)||!('indexedDB' in window))return;
 const uid=(document.querySelector('meta[name="bulig-uid"]')||{}).content||'0';
 const base=location.origin+location.pathname,abs=u=>new URL(u,base).href,$=s=>document.querySelector(s);
 const online=()=>navigator.onLine!==false;
 const store=(k,v)=>{try{if(v===undefined)return JSON.parse(localStorage.getItem(k)||'null');localStorage.setItem(k,JSON.stringify(v));}catch(e){return null;}};
 /* Tell the service worker who is signed in, so saved pages only open for that pupil. */
 if(online())caches.open('bulig-who').then(c=>c.put('who',new Response(uid==='0'?'':uid))).catch(()=>{});
 if(uid==='0')return;
 const META='bulig-off-'+uid,DONE='bulig-off-done-'+uid;
 const meta=()=>store(META)||{levels:{}},saveMeta=m=>store(META,m);
 const localDone=()=>new Set(store(DONE)||[]),addDone=id=>{const s=localDone();s.add(id);store(DONE,[...s]);},dropDone=id=>{const s=localDone();s.delete(id);store(DONE,[...s]);};
 const mb=b=>b>=1048576?(b/1048576).toFixed(b>=10485760?0:1)+' MB':Math.max(1,Math.round(b/1024))+' KB';
 const day=t=>new Date(t).toLocaleDateString(undefined,{month:'short',day:'numeric'});

 /* ---------- waiting answers (IndexedDB) ---------- */
 const idb=()=>new Promise((res,rej)=>{const r=indexedDB.open('bulig-offline',1);r.onupgradeneeded=()=>r.result.createObjectStore('answers',{keyPath:'key'});r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});
 const tx=(mode,fn)=>idb().then(db=>new Promise((res,rej)=>{const t=db.transaction('answers',mode),q=fn(t.objectStore('answers'));t.oncomplete=()=>res(q&&'result' in q?q.result:undefined);t.onerror=()=>rej(t.error);}));
 const waiting=()=>tx('readonly',s=>s.getAll()).then(a=>(a||[]).filter(i=>i.uid===uid).sort((x,y)=>x.at<y.at?-1:x.at>y.at?1:0)).catch(()=>[]);
 const put=i=>tx('readwrite',s=>s.put(i)),del=k=>tx('readwrite',s=>s.delete(k));

 /* ---------- saved levels ---------- */
 function lessonInfo(lid){const m=meta();for(const n in m.levels){const lv=m.levels[n];const i=(lv.lessons||[]).findIndex(l=>l.id===lid);if(i>=0)return {level:+n,lv,lesson:lv.lessons[i],next:lv.lessons[i+1]||null};}return null;}
 const doneSet=info=>{const s=localDone();if(info)info.lesson.acts.forEach(a=>{if(a.done)s.add(a.id);});return s;};
 async function saveLevel(level,ui){
  const r=await fetch('?page=offline_manifest&level='+level,{credentials:'same-origin',cache:'no-store'});
  const m=await r.json().catch(()=>({error:'Could not reach BULIG.'}));if(!r.ok||m.error)throw new Error(m.error||'Could not save this level.');
  const name='bulig-off-'+uid+'-L'+level;await caches.delete(name);const cache=await caches.open(name);
  const pages=[...m.pages];m.lessons.forEach(l=>{pages.push('?page=lesson&id='+l.id);l.acts.forEach(a=>pages.push('?page=lesson&id='+l.id+'&activity='+a.id));});
  const assets=new Set();let bytes=0,done=0;const total=pages.length;
  const collect=root=>root.querySelectorAll('img[src],img[data-src],script[src],link[rel="stylesheet"][href],link[rel="icon"][href],source[src],image[href]').forEach(n=>{const v=n.getAttribute('src')||n.getAttribute('data-src')||n.getAttribute('href');if(v&&!v.startsWith('data:')){const u=new URL(v,base);if(u.origin===location.origin)assets.add(u.href);}});
  async function page(p){if(ui.cancelled)return;
   try{const res=await fetch(p+'&off=1',{credentials:'same-origin',cache:'no-store'});if(res.ok&&!res.url.includes('page=login')){const html=await res.text();bytes+=html.length;
    await cache.put(abs(p),new Response(html,{headers:{'Content-Type':'text/html; charset=utf-8'}}));
    const doc=new DOMParser().parseFromString(html,'text/html');collect(doc);doc.querySelectorAll('template').forEach(t=>collect(t.content));}}catch(e){}
   done++;ui.progress(done,total);}
  const queue=[...pages];await Promise.all(Array.from({length:4},async()=>{while(queue.length&&!ui.cancelled)await page(queue.shift());}));
  if(ui.cancelled){await caches.delete(name);return null;}
  /* Pictures used by the style sheets (level covers and backgrounds). */
  for(const css of [...assets].filter(a=>/\.css(\?|$)/.test(a))){try{const t=await (await fetch(css)).text();for(const mt of t.matchAll(/url\(["']?([^"')]+)["']?\)/g)){if(mt[1].startsWith('data:'))continue;const u=new URL(mt[1],css);if(u.origin===location.origin)assets.add(u.href);}}catch(e){}}
  const list=[...assets];let ai=0;ui.progress(done,total,'pictures');
  await Promise.all(Array.from({length:4},async()=>{while(list.length&&!ui.cancelled){const a=list.shift();try{const res=await fetch(a);if(res.ok){const b=await res.clone().blob();bytes+=b.size;await cache.put(a,res);}}catch(e){}ai++;}}));
  if(ui.cancelled){await caches.delete(name);return null;}
  const all=meta();all.levels[level]={title:m.title,bytes,at:Date.now(),lessons:m.lessons};saveMeta(all);return all.levels[level];
 }
 async function removeLevel(level){await caches.delete('bulig-off-'+uid+'-L'+level);const m=meta();delete m.levels[level];saveMeta(m);}

 /* ---------- banner ---------- */
 let state={syncing:false,sent:0,total:0,blocked:false,done:null};
 const bar=document.createElement('div');bar.className='off-banner';bar.setAttribute('role','status');bar.setAttribute('aria-live','polite');bar.hidden=true;
 const host=$('main.main')||$('main.activity-main');if(host)host.prepend(bar);
 const ICON={off:'<path d="M2 8.8a15 15 0 0 1 20 0M5.5 12.3a10 10 0 0 1 13 0M9 15.8a5 5 0 0 1 6 0M12 19.5h.01M3 3l18 18"/>',wait:'<path d="M12 8v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"/>',ok:'<path d="M20 6 9 17l-5-5"/>'};
 const svg=k=>'<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">'+ICON[k]+'</svg>';
 function show(kind,title,text,btn){bar.hidden=false;bar.className='off-banner off-'+kind;bar.innerHTML=(kind==='up'?'<span class="off-spin" aria-hidden="true"></span>':svg(kind==='off'?'off':kind==='ok'?'ok':'wait'))+'<div><b></b><span></span></div>';bar.querySelector('b').textContent=title;bar.querySelector('div span').textContent=text;
  if(btn){const b=document.createElement('button');b.type='button';b.className='btn secondary';b.textContent=btn;b.addEventListener('click',()=>sync(true));bar.append(b);}}
 async function render(){const q=await waiting(),n=q.filter(i=>i.kind==='answer').length||q.length;
  if(!online())show('off','You are offline',n?n+' '+(n===1?'answer is':'answers are')+' kept on this device. Saved lessons still work.':'Your saved lessons still work. Answers will upload later.');
  else if(state.syncing)show('up','Uploading your answers…',state.sent+' of '+state.total+' sent');
  else if(state.done&&!n)show('ok','All '+state.done.sent+' '+(state.done.sent===1?'answer':'answers')+' uploaded!',(state.done.xp?'+'+state.done.xp+' XP · ':'')+'Your teacher can see them now.');
  else if(n&&state.blocked)show('wait',n+' '+(n===1?'answer is':'answers are')+' still waiting','Finish the earlier activity first, then they will upload.','Try again');
  else if(n)show('wait',n+' '+(n===1?'answer':'answers')+' waiting to upload','They will send when you are online.','Upload now');
  else bar.hidden=true;
  renderProfile(n);}

 /* ---------- upload ---------- */
 async function sync(manual){
  if(state.syncing||!online())return;const items=await waiting();if(!items.length){render();return;}
  let info;try{const r=await fetch('?page=offline_sync',{credentials:'same-origin',cache:'no-store'});info=await r.json();}catch(e){render();return;}
  if(String(info.uid)!==uid){render();return;}
  state={syncing:true,sent:0,total:items.filter(i=>i.kind==='answer').length,blocked:false,done:null};render();let xp=0;
  for(const it of items){
   const fd=new FormData();it.fields.forEach(([k,v])=>fd.append(k,v));fd.set('csrf',info.csrf);fd.set('offline_at',it.at);
   let res;try{res=await fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});}catch(e){break;}
   if(res.url.includes('page=login')){state.blocked=true;break;}
   const data=await res.clone().json().catch(()=>({}));
   if(res.ok){await del(it.key);if(it.kind==='answer')state.sent++;if(data.xp)xp+=data.xp;if(it.kind==='answer'&&data.saved===false)dropDone(it.aid);}
   else if(res.status===403){state.blocked=true;break;}
   else await del(it.key);
   render();
  }
  const sent=state.sent;state.syncing=false;
  if(sent&&!(await waiting()).length){try{sessionStorage.setItem('bulig-off-done',JSON.stringify({sent,xp}));}catch(e){}location.reload();return;}
  render();
 }

 /* ---------- answering offline (called from the activity form) ---------- */
 function nextHref(lid,aid){const info=lessonInfo(lid);if(!info)return '?page=lesson&id='+lid;const s=doneSet(info);s.add(aid);const n=info.lesson.acts.find(a=>!s.has(a.id));return n?'?page=lesson&id='+lid+'&activity='+n.id:'?page=lesson&id='+lid;}
 window.buligOffline={
  pending(){return waiting().then(a=>a.filter(i=>i.kind==='answer').length);},
  async queue(form){const fields=[...new FormData(form)].filter(([k,v])=>k!=='csrf'&&typeof v==='string');const aid=+(form.querySelector('[name=activity_id]')||{}).value||0;
   const lid=+new URLSearchParams(location.search).get('id')||0;
   await put({key:uid+'-a-'+aid,uid,kind:'answer',aid,lid,at:new Date().toISOString(),fields:fields.filter(([k])=>k!=='action').concat([['action','submit']])});
   addDone(aid);render();return nextHref(lid,aid);}
 };

 /* Lesson pages opened offline: skip ahead past answers already kept on this device. */
 const qs=new URLSearchParams(location.search);
 if(qs.get('page')==='lesson'&&!online()){
  const lid=+qs.get('id'),info=lessonInfo(lid);
  if(info){const s=doneSet(info),aid=+qs.get('activity')||0;const tot=info.lesson.acts.length,dn=info.lesson.acts.filter(a=>s.has(a.id)).length,ps=$('.activity-progress span'),pg=$('.activity-progress progress');if(ps)ps.textContent=dn+' / '+tot+' complete';if(pg){pg.max=tot;pg.value=dn;}
   if(!aid){const n=info.lesson.acts.find(a=>!s.has(a.id));const cur=+(($('#activity-form [name=activity_id]')||{}).value||0);
    if(n&&n.id!==cur)location.replace('?page=lesson&id='+lid+'&activity='+n.id);
    else if(!n)lessonDoneOffline(info);}
   else if(s.has(aid)){const f=$('#activity-form');if(f){f.dataset.draft='off';const b=$('#submit-answer'),nx=$('#next-activity'),fb=$('#answer-feedback');if(b)b.hidden=true;if(nx){nx.hidden=false;nx.href=nextHref(lid,aid);}if(fb){fb.className='answer-feedback success';fb.textContent='Saved on this device. It will upload when you are back online.';}}}}
 }
 async function lessonDoneOffline(info){
  if(!info.lesson.complete){const k=uid+'-f-'+info.lesson.id;await put({key:k,uid,kind:'finish',lid:info.lesson.id,at:new Date(Date.now()+1000).toISOString(),fields:[['action','finish_lesson'],['lesson_id',String(info.lesson.id)]]});}
  const main=$('main.activity-main');if(!main)return;main.querySelectorAll(':scope>*:not(.off-banner)').forEach(n=>n.remove());
  const c=document.createElement('section');c.className='completion-card off-done';
  c.innerHTML='<span class="eyebrow"></span><h1>You finished this lesson!</h1><p>Great work. It will be marked done when you are back online.</p><div class="buttonrow"></div>';
  c.querySelector('.eyebrow').textContent=info.lesson.title;const row=c.querySelector('.buttonrow');
  if(info.next){const a=document.createElement('a');a.className='btn primary';a.href='?page=lesson&id='+info.next.id;a.textContent='Next lesson: '+info.next.title;row.append(a);}
  const back=document.createElement('a');back.className='btn secondary';back.href='?page=lessons&level='+info.level;back.textContent='Back to my map';row.append(back);main.append(c);render();
 }

 /* ---------- level cards: Save for offline ---------- */
 function slot(el){const level=+el.dataset.offLevel,saved=meta().levels[level];el.replaceChildren();const card=el.closest('.level-card');
  if(!online()){if(card)card.classList.toggle('off-dim',!saved);const chip=document.createElement('span');chip.className=saved?'off-chip saved':'off-chip need';chip.innerHTML=saved?svg('ok')+'Saved on this device':'Needs internet';el.append(chip);return;}
  if(card)card.classList.remove('off-dim');
  if(saved){const chip=document.createElement('span');chip.className='off-chip saved';chip.innerHTML=svg('ok')+'Saved on this device';const sm=document.createElement('small');sm.className='off-small';sm.textContent=mb(saved.bytes)+' · saved '+day(saved.at)+' · ';
   const up=document.createElement('button');up.type='button';up.className='linkbutton';up.textContent='Update';up.addEventListener('click',()=>start(el));const rm=document.createElement('button');rm.type='button';rm.className='linkbutton';rm.textContent='Remove';rm.addEventListener('click',async()=>{await removeLevel(level);slot(el);});
   sm.append(up,' · ',rm);el.append(chip,sm);return;}
  const b=document.createElement('button');b.type='button';b.className='btn off-save';b.innerHTML='<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>Save for offline';b.addEventListener('click',()=>start(el));el.append(b);}
 async function start(el){const level=+el.dataset.offLevel;el.replaceChildren();
  const box=document.createElement('div');box.className='off-saving';box.innerHTML='<small><b>Saving for offline…</b> <span></span></small><div class="off-prog"><i></i></div>';const cancel=document.createElement('button');cancel.type='button';cancel.className='linkbutton';cancel.textContent='Cancel';box.append(cancel);el.append(box);
  const ui={cancelled:false,progress(d,t,what){box.querySelector('span').textContent=what?'pictures and sounds':d+' of '+t+' pages';box.querySelector('i').style.width=Math.round((what?.9:.9*d/t)*100)+'%';}};
  cancel.addEventListener('click',()=>{ui.cancelled=true;});
  try{await saveLevel(level,ui);}catch(e){const er=document.createElement('small');er.className='off-small off-err';er.textContent=e.message||'Could not save. Check your connection.';slot(el);el.append(er);return;}
  slot(el);}
 const slots=()=>document.querySelectorAll('[data-off-level]').forEach(slot);

 /* ---------- My profile: Offline lessons ---------- */
 async function renderProfile(n){const box=$('[data-off-profile]');if(!box)return;const head=$('.pageheading');if(head&&box.previousElementSibling!==head)head.after(box);
  const m=meta(),levels=Object.entries(m.levels);box.hidden=false;box.replaceChildren();
  const h=document.createElement('h2');h.textContent='Offline lessons';const p=document.createElement('p');p.className='muted';p.textContent=levels.length?'Saved on this device. They open even without internet.':'No levels saved yet. Open My lessons and choose “Save for offline” on a level.';box.append(h,p);
  let used=levels.reduce((t,[,l])=>t+(l.bytes||0),0);
  if(levels.length){const meter=document.createElement('div');meter.className='off-meter';const i=document.createElement('i');meter.append(i);const sm=document.createElement('small');sm.className='off-small';sm.textContent=mb(used)+' used';box.append(meter,sm);
   try{const est=await navigator.storage.estimate();if(est.quota){i.style.width=Math.max(3,Math.min(100,est.usage/est.quota*100))+'%';sm.textContent=mb(used)+' used · '+mb(Math.max(0,est.quota-est.usage))+' free';}}catch(e){i.style.width='10%';}
   levels.forEach(([lv,l])=>{const row=document.createElement('div');row.className='off-row';const t=document.createElement('div');t.innerHTML='<b></b><small></small>';t.querySelector('b').textContent=l.title;t.querySelector('small').textContent=mb(l.bytes)+' · saved '+day(l.at);
    const rm=document.createElement('button');rm.type='button';rm.className='linkbutton';rm.textContent='Remove';rm.addEventListener('click',async()=>{await removeLevel(lv);render();});row.append(t,rm);box.append(row);});}
  if(n){const row=document.createElement('div');row.className='off-row';const chip=document.createElement('span');chip.className='off-chip wait';chip.innerHTML=svg('wait');chip.append(n+' '+(n===1?'answer':'answers')+' waiting');const b=document.createElement('button');b.type='button';b.className='btn secondary';b.textContent='Upload now';b.disabled=!online();b.addEventListener('click',()=>sync(true));row.append(chip,b);box.append(row);}}

 try{const d=JSON.parse(sessionStorage.getItem('bulig-off-done')||'null');if(d){sessionStorage.removeItem('bulig-off-done');state.done=d;setTimeout(()=>{state.done=null;render();},9000);}}catch(e){}
 window.addEventListener('online',()=>{slots();render();sync();});
 window.addEventListener('offline',()=>{slots();render();});
 slots();render();if(online())setTimeout(sync,800);
 setInterval(async()=>{if(online()&&!state.syncing&&(await waiting()).length)sync();},60000);
})();
