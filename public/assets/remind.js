/* BULIG reminders on the phone (Web Push)
   - After BULIG is added to the home screen, a friendly box asks first. Only "Yes, remind me" opens the phone's own question.
   - "Not now" is remembered on this device; reminders can be turned on later in My profile.
   - My profile: turn each reminder on or off, pick the reading-time hour, send a test, or turn reminders off. */
(function(){
 'use strict';
 const $=s=>document.querySelector(s),key=($('meta[name="bulig-vapid"]')||{}).content||'';
 let me=null;try{me=JSON.parse(($('meta[name="bulig-tour"]')||{}).content||'null');}catch(e){}
 if(!me||me.role!=='pupil')return;
 const can='serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window&&!!key;
 const homeScreen=matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 const iOS=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 const ASK='bulig-push-asked-'+me.id,still=matchMedia('(prefers-reduced-motion: reduce)').matches;
 const get=k=>{try{return localStorage.getItem(k);}catch(e){return null;}},set=(k,v)=>{try{localStorage.setItem(k,v);}catch(e){}};
 const el=(t,c,x)=>{const e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;};
 const toast=(t,k)=>{if(window.buligToast)window.buligToast(t,k||'ok');};
 const keyBytes=()=>{const s=(key+'='.repeat((4-key.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/'),r=atob(s),a=new Uint8Array(r.length);for(let i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a;};
 async function post(action,data){
  const info=await (await fetch('?page=offline_sync',{credentials:'same-origin',cache:'no-store'})).json();
  const fd=new FormData();fd.set('csrf',info.csrf);fd.set('action',action);Object.entries(data||{}).forEach(([k,v])=>fd.set(k,v));
  return (await fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}})).json();}
 const reg=()=>navigator.serviceWorker.ready;
 async function current(){try{return await (await reg()).pushManager.getSubscription();}catch(e){return null;}}
 /* Must start inside a tap: the phone only asks after a real tap. */
 async function turnOn(){
  let perm;try{perm=await Notification.requestPermission();}catch(e){perm='denied';}
  if(perm!=='granted'){toast(perm==='denied'?'Reminders are blocked. You can allow them in this phone’s settings.':'Okay! You can turn reminders on later in My profile.','in');return false;}
  try{const sub=await (await reg()).pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes()}),j=sub.toJSON();
   const r=await post('push_subscribe',{endpoint:j.endpoint,p256dh:j.keys.p256dh,auth:j.keys.auth});if(!r.ok)throw new Error(r.error||'');
   toast('Reminders are on! See you at reading time.');return true;}
  catch(e){toast('Reminders could not be turned on. Check the internet and try again.','err');return false;}}

 /* ---------- the friendly question (once, from the home screen) ---------- */
 const wait=n=>new Promise(r=>document.addEventListener(n,r,{once:true}));
 async function ask(){
  if(!can||!homeScreen||Notification.permission!=='default'||get(ASK)||!navigator.onLine)return;
  if(new URLSearchParams(location.search).get('page')!=='dashboard')return;
  if(window.buligHelloOpen&&window.buligHelloOpen())await wait('bulig:hello-done');
  if(window.pfOverlayOpen&&window.pfOverlayOpen())await wait('pf:overlays-done');
  if(window.buligDlPending)await wait('bulig:dl-done');
  /* Wait until nothing else is on screen (tour offer, download box, celebrations), then ask. */
  const busy=()=>document.querySelector('.tour-offer,.tour-layer,.tour-toast,.dl-ov,.dl-panel.on,.pw-overlay:not([hidden]),.qs-sheet.on,.bdg-ov');
  const nap=ms=>new Promise(r=>setTimeout(r,ms));let calm=0;for(let i=0;i<360&&calm<3;i++){await nap(500);calm=busy()?0:calm+1;}if(busy()||get(ASK))return;
  const ov=el('div','rm-ov'),box=el('div','rm-box');ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-labelledby','rm-title');
  if(me.hi||me.char){const im=el('img','rm-art');im.src=me.hi||me.char;im.alt='';box.append(im);}
  const h=el('h3','','Can BULIG remind you?');h.id='rm-title';box.append(h,el('p','','We’ll send a short, friendly note. Never more than one a day.'));
  const ex=el('div','rm-ex');[['fire','Time to read! Keep your streak'],['msg','Your teacher left you a note'],['key','A new lesson is open']].forEach(([i,t])=>{const d=el('div','rm-x rm-'+i);d.append(el('span','rm-ic'),el('span','',t));ex.append(d);});box.append(ex);
  const yes=el('button','btn primary rm-yes','Yes, remind me'),no=el('button','btn secondary rm-no','Not now');yes.type=no.type='button';box.append(yes,no);ov.append(box);document.body.append(ov);
  requestAnimationFrame(()=>ov.classList.add('on'));yes.focus();
  const close=()=>{ov.classList.remove('on');setTimeout(()=>ov.remove(),still?0:250);};
  no.addEventListener('click',()=>{set(ASK,'later');close();});
  yes.addEventListener('click',()=>{set(ASK,'yes');close();turnOn().then(paintCard);});}
 ask();

 /* ---------- My profile: Reminders ---------- */
 const card=$('[data-push-card]');
 const HOURS=[];for(let h=6;h<=20;h++)HOURS.push([h,(h%12||12)+':00 '+(h<12?'AM':'PM')]);
 function sw(label,sub,on,name){const l=el('label','rm-tg'),t=el('span','');t.append(el('b','',label),el('small','',sub));const i=el('input');i.type='checkbox';i.checked=!!on;i.name=name;i.className='rm-sw';l.append(t,i);return l;}
 async function paintCard(){if(!card)return;card.replaceChildren();
  const head=el('h2','rm-h','Reminders on this phone');card.append(head);
  if(!can){if(iOS&&!homeScreen){card.hidden=false;card.append(el('p','muted','To get reminders on an iPhone or iPad, first add BULIG to your Home Screen: tap Share, then “Add to Home Screen”. Then open BULIG from there.'));}else card.hidden=true;return;}
  card.hidden=false;
  if(Notification.permission==='denied'){card.append(el('p','muted','Reminders are blocked on this phone. To allow them, open this phone’s settings for BULIG and turn on notifications.'));return;}
  const sub=await current();
  if(!sub){card.append(el('p','muted','Get a short, friendly note at reading time. Never more than one a day.'));const b=el('button','btn primary','Turn on reminders');b.type='button';b.addEventListener('click',()=>{set(ASK,'yes');turnOn().then(paintCard);});card.append(b);return;}
  let r;try{r=await post('push_prefs',{endpoint:sub.endpoint});}catch(e){card.append(el('p','muted','Connect to the internet to change your reminders.'));return;}
  if(!r.ok){card.append(el('p','muted',r.error||'Reminders are not available right now.'));return;}
  const p=r.prefs;card.append(el('p','muted','BULIG sends at most one note a day, between 7 AM and 8 PM.'));
  const daily=sw('Daily reading time','When you have not read yet that day',p.daily,'daily');
  const sel=el('select','rm-hour');sel.setAttribute('aria-label','Reading time');HOURS.forEach(([v,t])=>{const o=el('option','',t);o.value=v;if(+p.hour===v)o.selected=true;sel.append(o);});
  const hr=el('div','rm-hr');hr.append(el('span','','Remind me at'),sel);
  card.append(daily,hr,sw('Notes from my teacher','When your teacher writes to you',p.teacher,'teacher'),sw('New lessons and levels','When something new opens',p.lessons,'lessons'),sw('Weekends','Also send on Saturday and Sunday',p.weekends,'weekends'));
  const save=async(k,v)=>{try{const x=await post('push_prefs',{endpoint:sub.endpoint,[k]:v});if(!x.ok)throw 0;toast('Saved.');}catch(e){toast('Could not save. Check the internet.','err');}};
  card.querySelectorAll('.rm-sw').forEach(i=>i.addEventListener('change',()=>save(i.name,i.checked?'1':'0')));sel.addEventListener('change',()=>save('hour',sel.value));
  const row=el('div','rm-row'),test=el('button','btn secondary','Send a test reminder'),off=el('button','btn secondary rm-off','Turn off reminders');test.type=off.type='button';row.append(test,off);card.append(row);
  test.addEventListener('click',async()=>{test.disabled=true;try{const x=await post('push_test',{endpoint:sub.endpoint});toast(x.ok?'Sent! It should appear in a moment.':'Could not send. Try again later.',x.ok?'ok':'err');}catch(e){toast('Could not send. Check the internet.','err');}test.disabled=false;});
  off.addEventListener('click',async()=>{try{await post('push_off',{endpoint:sub.endpoint});}catch(e){}try{await sub.unsubscribe();}catch(e){}toast('Reminders are off on this phone.','in');paintCard();});}
 paintCard();
})();
