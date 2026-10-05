/* BULIG offline mode (v1)
   - "Save for offline" stores a level's pages and pictures on this device.
   - Answers given without internet wait on the device (IndexedDB) and are sent when it is back online.
   - Answers are sent in the order they were given, with the real time they were given. */
(function(){
 if(!('caches' in window)||!('indexedDB' in window))return;
 const uid=(document.querySelector('meta[name="bulig-uid"]')||{}).content||'0';
 const base=location.origin+location.pathname,abs=u=>new URL(u,base).href,$=s=>document.querySelector(s);
 const toast=t=>{if(window.buligToast)window.buligToast(t,'ok');};
 const online=()=>navigator.onLine!==false;
 const store=(k,v)=>{try{if(v===undefined)return JSON.parse(localStorage.getItem(k)||'null');localStorage.setItem(k,JSON.stringify(v));}catch(e){return null;}};
 /* Tell the service worker who is signed in, so saved pages only open for that pupil. */
 if(online())caches.open('bulig-who').then(c=>c.put('who',new Response(uid==='0'?'':uid))).catch(()=>{});
 /* The sign-in page is kept on this device, so pupils with a saved account can open their saved lessons offline. */
 async function cacheLogin(){try{
  const urls=new Set(),add=v=>{if(!v||v.startsWith('data:'))return;const u=new URL(v,base);if(u.origin===location.origin)urls.add(u.href);};
  document.querySelectorAll('link[rel="stylesheet"][href],script[src],img[src],link[rel="icon"][href],link[rel="preload"][href],link[rel="manifest"][href]').forEach(n=>add(n.getAttribute('src')||n.getAttribute('href')));
  ['assets/bulig-logo.png','assets/images/characters/boy.webp','assets/images/characters/girl.webp','assets/images/characters/boy-cheer.webp','assets/images/characters/girl-cheer.webp','assets/fonts/poppins-latin-400-normal.woff2','assets/fonts/poppins-latin-500-normal.woff2','assets/fonts/poppins-latin-600-normal.woff2','assets/fonts/poppins-latin-700-normal.woff2'].forEach(add);
  (store('bulig-saved')||[]).forEach(x=>{if(x&&x.img)add(x.img);});
  const c=await caches.open('bulig-login'),sig=[...urls].sort().join('|');
  if(store('bulig-login-sig')===sig&&await c.match(abs('?page=login')))return;
  const r=await fetch('?page=login',{credentials:'same-origin',cache:'no-store'});if(!r.ok||!r.url.includes('page=login')&&new URL(r.url).search)return;
  await c.put(abs('?page=login'),new Response(await r.text(),{headers:{'Content-Type':'text/html; charset=utf-8'}}));
  for(const u of urls){try{const res=await fetch(u);if(res.ok)await c.put(u,res);}catch(e){}}
  store('bulig-login-sig',sig);}catch(e){}}
 if(uid==='0'){if(online()&&document.body.classList.contains('login-page'))setTimeout(cacheLogin,1500);return;}
 const META='bulig-off-'+uid,DONE='bulig-off-done-'+uid;
 const meta=()=>store(META)||{levels:{}},saveMeta=m=>store(META,m);
 /* A saved level is out of date when BULIG's files changed after it was saved. */
 const BUILD=($('meta[name="bulig-build"]')||{}).content||'',stale=lv=>!!(BUILD&&lv&&lv.build!==BUILD);
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
  const name='bulig-off-'+uid+'-L'+level,was=meta().levels[level];if(!(was&&was.locked))await caches.delete(name);const cache=await caches.open(name);
  const pages=[...m.pages];m.lessons.forEach(l=>{pages.push('?page=lesson&id='+l.id);l.acts.forEach(a=>pages.push('?page=lesson&id='+l.id+'&activity='+a.id));});
  const assets=new Set((m.assets||[]).map(abs));let bytes=0,done=0;const total=pages.length;
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
  await Promise.all(Array.from({length:4},async()=>{while(list.length&&!ui.cancelled){const a=list.shift();try{const had=await cache.match(a);if(had){bytes+=(await had.clone().blob()).size;ai++;continue;}const res=await fetch(a);if(res.ok){const b=await res.clone().blob();bytes+=b.size;await cache.put(a,res);}}catch(e){}ai++;}}));
  if(ui.cancelled){await caches.delete(name);return null;}
  const all=meta();all.levels[level]={title:m.title,bytes,at:Date.now(),lessons:m.lessons,build:BUILD,locked:!!m.locked};saveMeta(all);return all.levels[level];
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
  else if(n&&state.needSignIn)show('wait',n+' '+(n===1?'answer is':'answers are')+' still waiting','Sign in once with your Pupil ID and password, then they will upload.');
  else if(n&&state.blocked)show('wait',n+' '+(n===1?'answer is':'answers are')+' still waiting','Finish the earlier activity first, then they will upload.','Try again');
  else if(n)show('wait',n+' '+(n===1?'answer':'answers')+' waiting to upload','They will send when you are online.','Upload now');
  else bar.hidden=true;
  renderProfile(n);}

 /* ---------- upload ---------- */
 /* Only one upload at a time (the online event and the timer can both start one). */
 let syncBusy=false;
 async function sync(manual){if(syncBusy)return;syncBusy=true;try{await doSync(manual);}finally{syncBusy=false;}}
 async function doSync(manual){
  if(state.syncing||!online())return;const items=await waiting();if(!items.length){render();return;}
  let info;try{const r=await fetch('?page=offline_sync',{credentials:'same-origin',cache:'no-store'});info=await r.json();}catch(e){render();return;}
  if(String(info.uid)!==uid){
   /* Learning happened offline under a saved account: sign that account in again with its saved key, then upload. */
   const acc=(store('bulig-saved')||[]).find(x=>x&&String(x.uid)===uid&&x.token);
   if(!acc){state.blocked=true;state.needSignIn=true;render();return;}
   try{const fd=new FormData();fd.set('csrf',info.csrf);fd.set('action','quick_login');fd.set('token',acc.token);
    const q=await (await fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}})).json();
    if(!q.ok){state.blocked=true;state.needSignIn=true;render();return;}
    info=await (await fetch('?page=offline_sync',{credentials:'same-origin',cache:'no-store'})).json();}catch(e){render();return;}
   if(String(info.uid)!==uid){render();return;}}
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
  if(saved){const old=stale(saved),chip=document.createElement('span');chip.className='off-chip '+(old?'upd':'saved');chip.innerHTML=old?svg('wait')+'Needs update':svg('ok')+'Saved on this device';const sm=document.createElement('small');sm.className='off-small';sm.textContent=mb(saved.bytes)+' · saved '+day(saved.at)+' · ';
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

 /* ---------- Settings: offline lessons count (and the old profile card, if a page still has it) ---------- */
 async function renderProfile(n){const oc=$('[data-off-count]');if(oc){const k=Object.keys(meta().levels).length;oc.textContent=(k?k+' '+(k===1?'level':'levels')+' saved on this device':'No levels saved yet')+(n?' · '+n+' '+(n===1?'answer':'answers')+' waiting to upload':'');}
  const box=$('[data-off-profile]');if(!box)return;const head=$('.pageheading');if(head&&box.previousElementSibling!==head)head.after(box);
  const m=meta(),levels=Object.entries(m.levels);box.hidden=false;box.replaceChildren();
  const h=document.createElement('h2');h.textContent='Offline lessons';const p=document.createElement('p');p.className='muted';p.textContent=levels.length?'Saved on this device. They open even without internet.':'No levels saved yet. Open My lessons and choose “Save for offline” on a level.';box.append(h,p);
  let used=levels.reduce((t,[,l])=>t+(l.bytes||0),0);
  if(levels.length){const meter=document.createElement('div');meter.className='off-meter';const i=document.createElement('i');meter.append(i);const sm=document.createElement('small');sm.className='off-small';sm.textContent=mb(used)+' used';box.append(meter,sm);
   try{const est=await navigator.storage.estimate();if(est.quota){i.style.width=Math.max(3,Math.min(100,est.usage/est.quota*100))+'%';sm.textContent=mb(used)+' used · '+mb(Math.max(0,est.quota-est.usage))+' free';}}catch(e){i.style.width='10%';}
   levels.forEach(([lv,l])=>{const row=document.createElement('div');row.className='off-row';const t=document.createElement('div');t.innerHTML='<b></b><small></small>';t.querySelector('b').textContent=l.title;t.querySelector('small').textContent=mb(l.bytes)+' · saved '+day(l.at);
    const rm=document.createElement('button');rm.type='button';rm.className='linkbutton';rm.textContent='Remove';rm.addEventListener('click',async()=>{await removeLevel(lv);render();});row.append(t,rm);box.append(row);});}
  const all=document.createElement('a');all.className='btn secondary off-all';all.href='?page=offline';all.innerHTML='<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>See all offline lessons';box.append(all);
  if(n){const row=document.createElement('div');row.className='off-row';const chip=document.createElement('span');chip.className='off-chip wait';chip.innerHTML=svg('wait');chip.append(n+' '+(n===1?'answer':'answers')+' waiting');const b=document.createElement('button');b.type='button';b.className='btn secondary';b.textContent='Upload now';b.disabled=!online();b.addEventListener('click',()=>sync(true));row.append(chip,b);box.append(row);}}

 /* ---------- after signing in: download lessons so BULIG works without internet ---------- */
 const DLQ='bulig-dl-q-'+uid,ASKED='bulig-dl-asked-'+uid;let me={};try{me=JSON.parse(($('meta[name="bulig-tour"]')||{}).content||'{}');}catch(e){}
 const ev=n=>new Promise(r=>document.addEventListener(n,r,{once:true}));
 const el=(t,c,x)=>{const e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;};
 const dlDone=()=>{window.buligDlPending=false;document.dispatchEvent(new Event('bulig:dl-done'));};
 let run=null;
 async function settle(){if(window.buligHelloOpen&&window.buligHelloOpen())await ev('bulig:hello-done');
  if(window.pfOverlayOpen&&window.pfOverlayOpen())await ev('pf:overlays-done');
  if($('[data-save-offer]')&&!window.buligSaveDone)await ev('bulig:save-done');}
 async function offerDownload(){
  await settle();let info;try{info=await (await fetch('?page=offline_levels',{credentials:'same-origin',cache:'no-store'})).json();}catch(e){dlDone();return;}
  const have=meta().levels,levels=info.levels||[],opened=autoOpen(levels),todo=levels.filter(l=>!have[l.level]),old=levels.filter(l=>have[l.level]&&stale(have[l.level])&&!opened.includes(l));
  if(!todo.length&&!old.length){if(!run)dlDone();return;}
  const upd=!todo.length,cur=levels.find(l=>l.current&&(!have[l.level]||stale(have[l.level]))),need=todo.concat(old),all=need.reduce((t,l)=>t+l.bytes,0);
  const ov=el('div','dl-ov'),sh=el('div','dl-sheet');ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-label',upd?'Update saved lessons':'Download lessons');
  if(me.char){const im=el('img','dl-art');im.src=me.char;im.alt='';sh.append(im);}
  if(upd){const tag=el('span','dl-new');tag.innerHTML='<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/></svg>BULIG has new things';sh.append(tag,el('h3','','Update your saved lessons'),el('p','','Your offline lessons are from an older BULIG. Update them to get the new things offline too.'));}
  else sh.append(el('h3','','Learn even without internet!'),el('p','','Save your lessons on this '+(innerWidth<741?'phone':'device')+' so they open anytime, even without internet.'));
  const opts=[];
  if(upd)opts.push({t:'Update '+old.length+' saved '+(old.length===1?'level':'levels'),d:old.map(l=>l.title).join(', ')+' · about '+mb(all),levels:old});
  else{if(cur)opts.push({t:'My level · '+cur.title,d:cur.activities+' activities · about '+mb(cur.bytes),levels:[cur],best:true});
   if(need.length>1||!cur)opts.push({t:'Everything',d:need.length+' '+(need.length===1?'level':'levels')+(need.some(l=>l.locked)?', with '+need.filter(l=>l.locked).length+' locked for now':'')+(old.length?' ('+old.length+' to update)':'')+' · about '+mb(all),levels:(cur?[cur]:[]).concat(need.filter(l=>l!==cur))});}
  let pick=opts[0];const list=el('div','dl-opts');list.setAttribute('role','radiogroup');
  opts.forEach(o=>{const b=el('button','dl-opt'+(o===pick?' on':''));b.type='button';b.setAttribute('role','radio');b.setAttribute('aria-checked',o===pick?'true':'false');
   const tx=el('span','dl-opt-t');tx.append(el('b','',o.t),el('small','',o.d));b.append(el('span','dl-rd'),tx);if(o.best)b.append(el('span','dl-best','BEST'));
   b.addEventListener('click',()=>{pick=o;list.querySelectorAll('.dl-opt').forEach(x=>{const on=x===b;x.classList.toggle('on',on);x.setAttribute('aria-checked',on?'true':'false');});});list.append(b);});
  sh.append(list);if(all>15*1048576)sh.append(el('p','dl-tip','Tip: use Wi-Fi for big downloads to save mobile data.'));
  const go=el('button','btn primary dl-go',upd?'Update now':'Download');go.type='button';
  const row=el('div','dl-row2'),see=el('a','btn secondary dl-see','See all offline lessons'),later=el('button','btn secondary dl-later','Not now');see.href='?page=offline';later.type='button';row.append(see,later);
  sh.append(go,row);ov.append(sh);document.body.append(ov);requestAnimationFrame(()=>ov.classList.add('on'));go.focus();
  later.addEventListener('click',()=>{ov.remove();dlDone();});
  go.addEventListener('click',()=>{ov.remove();queueLevels(pick.levels);});}
 /* Add levels to the download list and start (or continue) downloading. */
 function queueLevels(levels,quiet){const q=store(DLQ)||[];levels.forEach(l=>{if(!q.some(x=>x.level===l.level))q.push({level:l.level,title:l.title,bytes:l.bytes});});store(DLQ,q);
  if(run){levels.forEach(l=>{if(!run.q.some(x=>x.level===l.level)){run.q.push({level:l.level,title:l.title,bytes:l.bytes});run.total+=l.bytes||1;run.count++;addRow(l);}});paint();}else startRun(!quiet);if(!quiet&&run)run.loud=true;renderPage();}
 /* A level saved while locked has only its pictures. Once it opens, add its lesson pages quietly. */
 function autoOpen(levels){const have=meta().levels,up=levels.filter(l=>have[l.level]&&have[l.level].locked&&!l.locked);if(up.length&&online())queueLevels(up,true);return up;}
 function startRun(open){if(run)return;const q=store(DLQ)||[];if(!q.length){dlDone();return;}
  run={q,total:q.reduce((t,l)=>t+(l.bytes||1),0),doneBytes:0,frac:0,i:0,count:q.length,ready:0,open,loud:open,panel:null,chip:null,paused:false};build();next();}
 function build(){const p=el('div','dl-panel');p.setAttribute('role','dialog');p.setAttribute('aria-label','Downloading your lessons');
  const h=el('div','dl-ph');h.append(el('h3','','Downloading your lessons'),el('small','dl-sum',''));p.append(h,el('div','dl-big'));p.querySelector('.dl-big').append(el('i'));
  const ul=el('div','dl-list');p.append(ul);run.panel=p;run.q.forEach(addRow);
  const keep=el('button','btn primary dl-keep','Keep learning while it downloads');keep.type='button';keep.addEventListener('click',()=>{run.open=false;paint();dlDone();});p.append(keep,el('small','dl-note','You can close BULIG. It continues next time you open it.'));
  const chip=el('button','dl-chip');chip.type='button';chip.innerHTML='<span class="dl-ring"><i></i></span><span class="dl-ct"><b>Downloading lessons</b><small></small></span>';chip.addEventListener('click',()=>{run.open=true;paint();});
  document.body.append(p,chip);run.panel=p;run.chip=chip;paint();}
 function paint(){if(!run)return;const pct=Math.min(100,Math.round((run.doneBytes+run.frac*(run.q[0]?run.q[0].bytes||1:0))/run.total*100));
  run.panel.classList.toggle('on',run.open);run.chip.classList.toggle('on',!run.open);
  run.panel.querySelector('.dl-big i').style.width=pct+'%';run.panel.querySelector('.dl-sum').textContent=run.paused?'Paused · waiting for the internet':pct+'% · '+run.ready+' of '+run.count+' ready';
  run.chip.querySelector('.dl-ring').style.setProperty('--p',pct);run.chip.querySelector('.dl-ring i').textContent=pct+'%';
  run.chip.querySelector('small').textContent=run.paused?'Paused · no internet':(run.q[0]?run.q[0].title+' · ':'')+run.ready+' of '+run.count+' ready';}
 function addRow(l){const ul=run.panel.querySelector('.dl-list'),r=el('div','dl-row');r.dataset.level=l.level;const t=el('div','');t.append(el('b','',l.title));const pb=el('span','dl-pb');pb.append(el('i'));t.append(pb);r.append(el('span','dl-cv cv-'+l.level),t,el('span','dl-st','Waiting'));ul.append(r);}
 function rowOf(level){return run.panel.querySelector('.dl-row[data-level="'+level+'"]');}
 async function next(){if(!run)return;const l=run.q[0];
  if(!l){run.panel.remove();run.chip.remove();const n=run.count,loud=run.loud,names=run.names||[];run=null;store(DLQ,[]);slots();render();renderPage();if(loud)celebrate(n);else{toast((names.join(', ')||'Your new level')+' is ready offline.');dlDone();}return;}
  if(!online()){run.paused=true;paint();window.addEventListener('online',()=>{if(run){run.paused=false;next();}},{once:true});return;}
  const row=rowOf(l.level);if(row){row.querySelector('.dl-st').textContent='0%';}run.frac=0;
  const ui={cancelled:false,progress(d,t,what){run.frac=what?.92:.9*d/Math.max(1,t);if(row){row.querySelector('.dl-pb i').style.width=Math.round(run.frac*100)+'%';row.querySelector('.dl-st').textContent=Math.round(run.frac*100)+'%';}pageProg(l.level,Math.round(run.frac*100));paint();}};
  try{await saveLevel(l.level,ui);}catch(e){}
  if(!online()){run.paused=true;paint();window.addEventListener('online',()=>{if(run){run.paused=false;next();}},{once:true});return;}
  (run.names=run.names||[]).push(l.title);run.q.shift();store(DLQ,run.q);run.doneBytes+=l.bytes||1;run.frac=0;run.ready++;
  if(row){row.classList.add('ok');row.querySelector('.dl-pb i').style.width='100%';row.querySelector('.dl-st').textContent='Ready';}paint();renderPage();next();}
 function celebrate(n){const ov=el('div','dl-ov dl-done');const c=el('div','dl-sheet');if(me.char){const im=el('img','dl-art');im.src=me.char;im.alt='';c.append(im);}
  c.append(el('span','dl-ok',n+' '+(n===1?'level':'levels')+' ready offline'),el('h3','','All set'+(me.name?', '+me.name:'')+'!'),el('p','','You can learn even without internet. Your answers will upload when you are back online.'));
  const b=el('button','btn primary dl-go','Let’s learn!');b.type='button';b.addEventListener('click',()=>{ov.remove();dlDone();});c.append(b);ov.append(c);document.body.append(ov);requestAnimationFrame(()=>ov.classList.add('on'));}
 if(online()&&me.role==='pupil'){
  if((store(DLQ)||[]).length)setTimeout(()=>startRun(false),1500);
  else if($('meta[name="bulig-dl-offer"]')){window.buligDlPending=true;offerDownload();}
  else if(!$('[data-off-page]')&&Object.values(meta().levels).some(l=>l.locked))fetch('?page=offline_levels',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(j=>autoOpen(j.levels||[])).catch(()=>{});}

 /* ---------- page: Offline lessons ---------- */
 let pageInfo=null;
 function pageProg(level,pct){const c=document.querySelector('[data-pg-level="'+level+'"] .off-chip');if(c){c.className='off-chip go';c.textContent='Downloading '+pct+'%';const b=c.closest('.op-row').querySelector('.op-bar i');if(b)b.style.width=pct+'%';}}
 async function renderPage(){const box=$('[data-off-page]');if(!box)return;
  if(online()){try{pageInfo=await (await fetch('?page=offline_levels',{credentials:'same-origin',cache:'no-store'})).json();if(!run&&autoOpen(pageInfo.levels||[]).length)return;}catch(e){}}
  const have=meta().levels,levels=pageInfo&&pageInfo.levels?pageInfo.levels:Object.keys(have).map(k=>({level:+k,title:have[k].title,bytes:have[k].bytes}));
  const st=l=>!have[l.level]?'no':stale(have[l.level])||(have[l.level].locked&&l.locked===false)?'up':'ok',queued=new Set((store(DLQ)||[]).map(x=>x.level));
  const nOk=levels.filter(l=>st(l)==='ok').length,nUp=levels.filter(l=>st(l)==='up').length,nNo=levels.filter(l=>st(l)==='no').length;
  const need=levels.filter(l=>st(l)!=='ok'&&!queued.has(l.level)),needB=need.reduce((t,l)=>t+(l.bytes||0),0),used=Object.values(have).reduce((t,l)=>t+(l.bytes||0),0);
  box.replaceChildren();const sum=el('div','card off-sum'),top=el('div','op-top'),ring=el('div','op-ring');ring.style.setProperty('--p',levels.length?Math.round(nOk/levels.length*100):0);ring.append(el('b','',nOk+'/'+levels.length));
  const tx=el('div','');tx.append(el('h2','',levels.length?nOk+' of '+levels.length+' levels saved':'No levels to save yet'),el('p','muted',[nUp?nUp+' '+(nUp===1?'needs':'need')+' an update':'',nNo?nNo+' not saved yet':'',!nUp&&!nNo&&levels.length?'Everything is saved and up to date.':''].filter(Boolean).join(' · ')));top.append(ring,tx);sum.append(top);
  const meter=el('div','off-meter'),mi=el('i');meter.append(mi);const ms=el('small','off-small',mb(used)+' used');sum.append(meter,ms);
  try{const est=await navigator.storage.estimate();if(est.quota){mi.style.width=Math.max(2,Math.min(100,est.usage/est.quota*100))+'%';ms.textContent=mb(used)+' used · '+mb(Math.max(0,est.quota-est.usage))+' free on this device';}}catch(e){mi.style.width='5%';}
  if(!online())sum.append(el('p','op-note','You are offline. Connect to the internet to download or update lessons.'));
  else if(run)sum.append(el('p','op-note go','Downloading… You can keep using BULIG while it downloads.'));
  else if(need.length){const b=el('button','btn primary op-all');b.type='button';b.innerHTML='<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>';b.append((nOk?'Download the rest':'Download everything')+' · '+mb(needB));b.addEventListener('click',()=>queueLevels(need));sum.append(b);if(needB>15*1048576)sum.append(el('p','op-note','Use Wi-Fi for big downloads.'));}
  if(levels.some(l=>l.locked))sum.append(el('p','op-note','Locked levels can be saved now. They open when you finish the level before them.'));
  box.append(sum);
  levels.forEach(l=>{const s=queued.has(l.level)?'q':st(l),r=el('div','card op-row');r.dataset.pgLevel=l.level;
   if(l.cover){const im=el('img','op-cover');im.src=l.cover;im.alt='';im.loading='lazy';r.append(im);}
   const t=el('div','op-t');t.append(el('b','',l.title),el('small','',mb(l.bytes||0)+(s==='ok'&&have[l.level]?' · saved '+day(have[l.level].at):'')));
   const chip=el('span','off-chip '+{ok:'saved',up:'upd',no:'need',q:'go'}[s]);chip.innerHTML=s==='ok'?svg('ok')+'Saved':s==='up'?svg('wait')+'Needs update':s==='q'?'Waiting to download':'Not saved';t.append(chip);
   if(l.locked){const lk=el('span','op-lock');lk.innerHTML='<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Locked for now';lk.title='It opens when you finish the level before it.';t.append(lk);}
   if(s==='q'){const bar=el('div','op-bar');bar.append(el('i'));t.append(bar);}r.append(t);
   const acts=el('div','op-acts');
   if(online()&&!run&&(s==='no'||s==='up')){const b=el('button','btn '+(s==='up'?'primary':'secondary'),s==='up'?'Update':'Download');b.type='button';b.addEventListener('click',()=>queueLevels([l]));acts.append(b);}
   if(have[l.level]&&s!=='q'){const b=el('button','btn secondary','Remove');b.type='button';b.addEventListener('click',async()=>{await removeLevel(l.level);slots();render();renderPage();});acts.append(b);}
   r.append(acts);box.append(r);});}
 try{const d=JSON.parse(sessionStorage.getItem('bulig-off-done')||'null');if(d){sessionStorage.removeItem('bulig-off-done');state.done=d;setTimeout(()=>{state.done=null;render();},9000);}}catch(e){}
 window.addEventListener('online',()=>{slots();render();renderPage();sync();});
 window.addEventListener('offline',()=>{slots();render();renderPage();});
 slots();render();renderPage();if(online())setTimeout(sync,800);
 setInterval(async()=>{if(online()&&!state.syncing&&(await waiting()).length)sync();},60000);
})();
