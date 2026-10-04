/* BULIG quick sign-in and friendly overlays (v1)
   - Login page: "Who's learning today?" with up to 3 accounts saved on this device; one tap signs in.
   - After a password sign-in: offers to save the account on this device.
   - Locked levels and lesson stops: a card explains what to finish first.
   - Sign out: asks "Are you sure?" first.
   The device keeps only a random sign-in key per account (never the password). */
(function(){
 const $=s=>document.querySelector(s);
 const K='bulig-saved',MAX=3;
 const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 let me=null;try{me=JSON.parse(($('meta[name="bulig-tour"]')||{}).content||'null');}catch(e){}
 const csrf=()=>($('meta[name="csrf"]')||{}).content||'';
 const load=()=>{try{const a=JSON.parse(localStorage.getItem(K)||'[]');return Array.isArray(a)?a.filter(x=>x&&x.token&&x.uid).slice(0,MAX):[];}catch(e){return [];}};
 const store=a=>{try{localStorage.setItem(K,JSON.stringify(a.slice(0,MAX)));}catch(e){}};
 const post=data=>{const fd=new FormData();fd.set('csrf',csrf());Object.keys(data).forEach(k=>fd.set(k,data[k]));
  return fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.json());};
 const device=()=>innerWidth<741?'phone':(window.matchMedia&&matchMedia('(pointer: coarse)').matches?'tablet':'computer');
 const ago=t=>{if(!t)return '';const d=new Date(t),n=new Date(),day=86400000,a=new Date(d.getFullYear(),d.getMonth(),d.getDate()),b=new Date(n.getFullYear(),n.getMonth(),n.getDate()),k=Math.round((b-a)/day);
  return k<=0?'Today':k===1?'Yesterday':d.toLocaleDateString(undefined,{month:'short',day:'numeric'});};
 const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!=null)e.textContent=text;return e;};
 const face=p=>{const f=el('span','qp-face'),i=el('img',p.char?'char':'');i.src=p.img;i.alt='';f.append(i);return f;};
 const bubble=(p,n)=>{const b=el('span','qp-bub g'+((n%3)+1));b.append(face(p));const t=el('span','qp-tag'+(p.role==='teacher'?' t':''),p.role==='teacher'?'TEACHER':'PUPIL');b.append(t);return b;};
/* Offline: which pupil is using this device (the service worker opens only that pupil's saved pages). */
 const setWho=v=>('caches' in window?caches.open('bulig-who').then(c=>c.put('who',new Response(String(v)))):Promise.resolve()).catch(()=>{});
 const offLevels=p=>{try{const m=JSON.parse(localStorage.getItem('bulig-off-'+p.uid)||'null');return m&&m.levels?Object.keys(m.levels).length:0;}catch(e){return 0;}};
 const isOffline=()=>navigator.onLine===false;
 const svgLock='<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';

 /* ---------- generic overlay ---------- */
 function overlay(o){
  const ov=el('div','qo-ov '+(o.cls||''));ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');
  const box=el('div','qo-box');ov.append(box);if(o.art)box.append(o.art);
  const h=el('h2','qo-title',o.title);h.id='qo-t-'+Date.now();ov.setAttribute('aria-labelledby',h.id);box.append(h);
  if(o.text){const p=el('p','qo-text');p.textContent=o.text;box.append(p);}
  (o.extra||[]).forEach(x=>box.append(x));
  const last=document.activeElement;
  const close=()=>{ov.classList.remove('on');document.removeEventListener('keydown',key,true);setTimeout(()=>ov.remove(),still?0:260);if(last&&last.focus)last.focus({preventScroll:true});if(o.onClose)o.onClose();};
  const btn=(b,cls)=>{const x=b.href?el('a',cls,b.label):el('button',cls,b.label);if(b.href)x.href=b.href;else x.type='button';x.addEventListener('click',e=>{if(b.run){e.preventDefault();b.run(close);}else if(!b.href)close();});return x;};
  if(o.primary)box.append(btn(o.primary,'btn primary qo-b1'));
  if(o.secondary)box.append(btn(o.secondary,'qo-b2'+(o.secondary.danger?' danger':'')));
  const key=e=>{if(e.key==='Escape'){e.preventDefault();close();}else if(e.key==='Tab'){const f=[...box.querySelectorAll('a,button,input')];if(!f.length)return;const i=f.indexOf(document.activeElement);if(e.shiftKey&&i<=0){e.preventDefault();f[f.length-1].focus();}else if(!e.shiftKey&&i===f.length-1){e.preventDefault();f[0].focus();}}};
  ov.addEventListener('click',e=>{if(e.target===ov)close();});document.addEventListener('keydown',key,true);
  document.body.append(ov);requestAnimationFrame(()=>requestAnimationFrame(()=>ov.classList.add('on')));
  setTimeout(()=>{const f=box.querySelector('.qo-b1');if(f)f.focus({preventScroll:true});},60);
  return {close,box};
 }

 /* ---------- locked level / lesson ---------- */
 const padlock='<svg class="qo-padlock" viewBox="0 0 64 72" aria-hidden="true"><path class="qo-shackle" d="M18 32V22a14 14 0 0 1 28 0v10" fill="none" stroke="#9aa395" stroke-width="7" stroke-linecap="round"/><rect x="8" y="30" width="48" height="38" rx="10" fill="#ffc629"/><rect x="8" y="30" width="48" height="12" rx="6" fill="#ffd966"/><circle cx="32" cy="50" r="6" fill="#7d5a00"/><rect x="29.5" y="52" width="5" height="9" rx="2" fill="#7d5a00"/></svg>';
 window.buligLockOverlay=function(o){
  const art=el('div','qo-lockart');art.innerHTML=padlock;
  [[-64,-30],[64,-24],[-50,30],[54,34],[0,-46]].forEach(([x,y])=>{const k=el('i','qo-spark');k.style.setProperty('--dx',x+'px');k.style.setProperty('--dy',y+'px');art.append(k);});
  if(me&&me.char){const k=el('img','qo-kid');k.src=me.char;k.alt='';art.append(k);}
  const extra=[];
  if(o.pct!=null&&o.need){const bar=el('div','qo-prog'),i=el('i');bar.append(i);bar.style.setProperty('--w',Math.max(3,Math.min(100,+o.pct))+'%');extra.push(bar,el('p','qo-small',o.need+' · '+o.pct+'% done'));}
  overlay({cls:'qo-lock',art,title:o.title,text:o.text,extra,primary:o.go?{label:o.go.label,href:o.go.href}:{label:'Okay'},secondary:o.go?{label:'Okay'}:null});
 };
 document.addEventListener('click',e=>{const c=e.target.closest&&e.target.closest('[data-lock]');if(!c||e.target.closest('a'))return;const d=c.dataset;
  if(d.lockSoon)window.buligLockOverlay({title:d.lockTitle+' is coming soon',text:'Your teacher is getting it ready. Check back soon!'});
  else window.buligLockOverlay({title:d.lockTitle+' is still locked',text:'Finish '+d.lockNeed+' first to unlock it. You can do it!',need:d.lockNeed,pct:d.lockPct,go:{label:'Go to '+d.lockNeed,href:d.lockHref}});});
 document.addEventListener('keydown',e=>{if(e.key==='Enter'){const c=e.target.closest&&e.target.closest('[data-lock]');if(c&&e.target===c)c.click();}});
 document.querySelectorAll('[data-lock]').forEach(c=>{c.tabIndex=0;c.setAttribute('role','button');c.setAttribute('aria-label',c.dataset.lockTitle+' is locked. Tap to see how to unlock it.');});

 /* ---------- sign out ---------- */
 document.addEventListener('submit',async e=>{const f=e.target;if(!f.querySelector||!f.querySelector('input[name="action"][value="logout"]')||f.dataset.ok||!me)return;e.preventDefault();
  const saved=load().find(x=>String(x.uid)===String(me.id));let pending=0;
  try{if(window.buligOffline&&window.buligOffline.pending)pending=await Promise.race([window.buligOffline.pending(),new Promise(r=>setTimeout(()=>r(0),800))]);}catch(x){}
  const art=el('div','qo-byeart');if(me.hi||me.char){const i=el('img');i.src=me.hi||me.char;i.alt='';art.append(i);}
  const extra=[];let box=null;
  if(saved){const l=el('label','qo-check');box=el('input');box.type='checkbox';box.checked=true;l.append(box,document.createTextNode(' Keep me saved on this device'));extra.push(l);}
  if(pending)extra.push(el('p','qo-warn',pending+' '+(pending===1?'answer is':'answers are')+' still waiting to upload. They will upload the next time you sign in on this device.'));
  overlay({cls:'qo-bye',art,title:'Leaving already, '+(me.role==='teacher'?'Teacher ':'')+me.name+'?',text:'Are you sure you want to sign out? Your work is saved.',extra,
   primary:{label:me.role==='pupil'?'Stay and learn':'Stay'},
   secondary:{label:'Yes, sign out',danger:true,run:close=>{if(isOffline()){close();setWho('').then(()=>{location.href='?page=login';});return;}if(saved&&box&&!box.checked){store(load().filter(x=>x.token!==saved.token));post({action:'forget_login',token:saved.token}).catch(()=>{});}f.dataset.ok='1';close();setTimeout(()=>f.submit(),still?0:150);}}});
 },true);

 /* ---------- offer to save the account (first page after a password sign-in) ---------- */
 const offerEl=$('[data-save-offer]');
 const done=()=>{window.buligSaveDone=true;document.dispatchEvent(new Event('bulig:save-done'));};
 if(offerEl&&!load().some(x=>String(x.uid)===offerEl.dataset.uid)){
  const p={uid:+offerEl.dataset.uid,name:offerEl.dataset.name,first:offerEl.dataset.first,role:offerEl.dataset.role,pid:offerEl.dataset.pid,img:offerEl.dataset.img,char:offerEl.dataset.char==='1'};
  const show=()=>setTimeout(()=>sheet(p),700);
  if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',show,{once:true});else show();
 }else done();
 function sheet(p){
  const list=load(),full=list.length>=MAX;let replace=null;
  const sh=el('div','qs-sheet');sh.setAttribute('role','dialog');sh.setAttribute('aria-label','Save your account on this device');
  const b=bubble(p,list.length);b.classList.add('qs-bub');b.querySelector('.qp-tag').remove();b.append(el('span','qs-plus','+'));sh.append(b);
  const t=el('b','qs-title','Save '+p.first+' on this '+device()+'?');sh.append(t);
  const s=el('span','qs-text','Next time, just tap your picture. No ID or password needed.');sh.append(s);
  const mini=el('div','qs-mini');
  if(!full){list.forEach((x,i)=>mini.append(el('i','m g'+((i%3)+1))));mini.append(el('i','m new'));for(let i=list.length+1;i<MAX;i++)mini.append(el('i','m qs-empty'));const left=MAX-list.length-1;mini.append(el('small','',left?left+' more '+(left===1?'space':'spaces'):'Last space'));}
  else{const q=el('small','qs-full','This '+device()+' already has 3 saved accounts. Tap one to replace:');sh.append(q);
   list.forEach((x,i)=>{const r=el('button','qs-pick');r.type='button';r.append(bubble(x,i),el('span','',x.first));r.setAttribute('aria-label','Replace '+x.first);
    r.addEventListener('click',()=>{replace=x;mini.querySelectorAll('.qs-pick').forEach(k=>k.classList.toggle('on',k===r));yes.disabled=false;});mini.append(r);});}
  sh.append(mini);
  const row=el('div','qs-btns'),yes=el('button','btn primary','Yes, save me'),no=el('button','btn secondary','Not now');yes.type=no.type='button';if(full)yes.disabled=true;row.append(yes,no);sh.append(row);
  const note=el('small','qs-note','Saved only on this device. You can remove it anytime.');sh.append(note);
  const msg=el('small','qs-msg');msg.setAttribute('role','status');sh.append(msg);
  const close=()=>{sh.classList.remove('on');setTimeout(()=>{sh.remove();done();},still?0:400);};
  no.addEventListener('click',close);
  yes.addEventListener('click',()=>{yes.disabled=true;yes.textContent='Saving…';
   post({action:'save_login',replace:replace?replace.token:''}).then(r=>{if(!r.ok)throw new Error(r.error||'Could not save.');
    const n=Object.assign({},r.profile,{token:r.token,last:Date.now()});store([n].concat(load().filter(x=>String(x.uid)!==String(n.uid)&&(!replace||x.token!==replace.token))));
    sh.classList.add('saved');t.textContent='Saved!';s.textContent='Next time, just tap your picture on the sign-in page.';mini.remove();row.remove();note.remove();setTimeout(close,2200);})
   .catch(err=>{msg.textContent=err.message||'Could not save. Try again later.';yes.disabled=false;yes.textContent='Yes, save me';});});
  document.body.append(sh);requestAnimationFrame(()=>requestAnimationFrame(()=>sh.classList.add('on')));
 }

 /* ---------- login page: Who's learning today? ---------- */
 /* After "Sign in with another account", switching Pupil / Teacher keeps the form. The choice lasts until someone signs in. */
 const QF='bulig-qp-form',ss=(v)=>{try{if(v===undefined)return sessionStorage.getItem(QF);if(v)sessionStorage.setItem(QF,'1');else sessionStorage.removeItem(QF);}catch(e){return null;}};
 const card=$('body.login-page:not(.admin-mode) .login-card');
 if(!$('body.login-page'))ss(false);
 if(card&&!new URLSearchParams(location.search).has('form')){
  const alert=el('p','qp-alert');alert.setAttribute('role','alert');alert.hidden=true;card.prepend(alert);
  const back=el('button','qp-back');back.type='button';back.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>';back.append('Back to saved accounts');back.hidden=true;card.prepend(back);
  const wrap=el('div','qp');card.append(wrap);let managing=false;
  back.addEventListener('click',()=>{ss(false);card.classList.add('qp-on');back.hidden=true;render();});
  function render(){
   const list=load();wrap.replaceChildren();if(!list.length){card.classList.remove('qp-on','qp-manage');back.hidden=true;return;}
   card.classList.add('qp-on');card.classList.toggle('qp-manage',managing);const off=isOffline();card.classList.toggle('qp-offline',off);
   if(off&&!managing){const pill=el('div','qp-offpill');pill.innerHTML='<i></i>';pill.append('Offline · saved accounts only');wrap.append(pill);}
   const head=el('div','qp-head');head.append(el('span','eyebrow','WELCOME BACK'),el('h2','','Who’s learning today?'),el('p','muted',managing?'Tap a picture to remove it from this device.':'Tap your picture to continue.'));wrap.append(head);
   const row=el('div','qp-row');
   list.forEach((p,i)=>{const b=el('button','qp-p');b.type='button';b.setAttribute('aria-label',(managing?'Remove ':'Sign in as ')+p.first+' ('+(p.role==='teacher'?'Teacher':'Pupil')+')');
    const bb=bubble(p,i);if(managing)bb.append(el('span','qp-x','×'));b.append(bb,el('span','qp-name',p.first));
    if(off&&!managing){const n=p.role==='pupil'?offLevels(p):0;b.classList.toggle('qp-dimmed',p.role!=='pupil');b.append(el('span','qp-ready'+(p.role!=='pupil'?' need':n?'':' none'),p.role!=='pupil'?'Needs internet':n?'✓ '+n+' '+(n===1?'level':'levels')+' saved':'No levels saved'));}
    else b.append(el('span','qp-meta',ago(p.last)));
    b.addEventListener('click',()=>managing?remove(p):(isOffline()?openOffline(p):signin(p,b,row)));row.append(b);});
   wrap.append(row);
   if(off&&!managing){const n=el('p','qp-offnote','Signing in with an ID and password needs the internet. Saved pupils can learn offline with the levels they saved.');wrap.append(n);}
   else if(!managing){wrap.append(el('div','qp-or','OR'));const o=el('button','qp-other');o.type='button';o.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></svg>';o.append('Sign in with another account');
    o.addEventListener('click',()=>{ss(true);card.classList.remove('qp-on');back.hidden=false;alert.hidden=true;const f=card.querySelector('input[name="public_id"]');if(f)f.focus();});wrap.append(o);}
   const foot=el('div','qp-foot'),lk=el('span');lk.innerHTML=svgLock;lk.append('Saved only on this device');const m=el('button','linkbutton qp-manage-btn',managing?'Done':'Manage');m.type='button';
   m.addEventListener('click',()=>{managing=!managing;render();});foot.append(lk,m);wrap.append(foot);
  }
  function remove(p){store(load().filter(x=>x.token!==p.token));post({action:'forget_login',token:p.token}).catch(()=>{});if(!load().length)managing=false;render();}
  function signin(p,b,row){if(row.classList.contains('busy'))return;row.classList.add('busy');b.classList.add('qp-busy');row.querySelectorAll('.qp-p').forEach(x=>{if(x!==b)x.classList.add('qp-dim');});b.querySelector('.qp-meta').textContent='Signing in…';alert.hidden=true;
   post({action:'quick_login',token:p.token}).then(r=>{
    if(r.ok){store(load().map(x=>x.token===p.token?Object.assign({},x,r.profile,{token:p.token,last:Date.now()}):x));if(window.buligLoading)window.buligLoading.boot();location.href=r.url||'?page=dashboard';return;}
    store(load().filter(x=>x.token!==p.token));alert.textContent=r.error||'This saved sign-in no longer works. Please sign in with your ID and password.';alert.hidden=false;render();
   }).catch(()=>{if(p.role==='pupil'&&offLevels(p)){render();openOffline(p);return;}alert.textContent=navigator.onLine===false?'You are offline. Connect to the internet to sign in.':'Could not sign in. Please try again.';alert.hidden=false;render();});}
  /* No internet: a saved pupil opens the levels saved on this device. Answers wait here and upload later with the saved key. */
  function openOffline(p){
   const art=el('div','qo-byeart'),img=el('img');img.alt='';img.src='assets/images/characters/'+(p.img&&/girl/.test(p.img)?'girl':'boy')+'-cheer.webp';
   if(p.role!=='pupil'){overlay({cls:'qo-bye',title:'Teachers need the internet',text:'Your class pages show live information, so please connect to the internet to sign in.',primary:{label:'Okay'}});return;}
   if(!offLevels(p)){img.src='assets/images/characters/'+(p.img&&/girl/.test(p.img)?'girl':'boy')+'.webp';art.append(img);overlay({cls:'qo-bye',art,title:'No saved lessons yet, '+p.first,text:'Next time you have internet, open My lessons and tap “Save for offline” on your level. Then you can learn here without internet.',primary:{label:'Okay'}});return;}
   art.append(img);const dots=el('div','qo-dots');dots.innerHTML='<i></i><i></i><i></i>';
   overlay({cls:'qo-bye qo-offgo',art,title:'Hi, '+p.first+'!',text:'Opening your saved lessons. You can learn without internet.',extra:[dots]});
   setWho(p.uid).then(()=>setTimeout(()=>{location.href='?page=dashboard';},still?100:1100));}
  render();
  if(ss()&&card.classList.contains('qp-on')&&!isOffline()){card.classList.remove('qp-on');back.hidden=false;}
  addEventListener('online',()=>{if(card.classList.contains('qp-on'))render();});addEventListener('offline',()=>{card.classList.add('qp-on');back.hidden=true;render();});
 }
})();
