/* Bug report updates: private, visible-tab polling and an explicit "Got it". v209: Bob the Worker's popup (the same
   construction sign as What's new), one look per status: Fixed, Working on it, Not a bug, Received. */
(()=>{
 const marker=document.querySelector('[data-bug-updates]');if(!marker)return;
 let busy=false,shown=null;const csrf=document.querySelector('meta[name="csrf"]')?.content||'';
 const el=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text)n.textContent=text;return n;};
 const svg=d=>{const n=document.createElementNS('http://www.w3.org/2000/svg','svg');n.setAttribute('viewBox','0 0 24 24');n.setAttribute('fill','none');n.setAttribute('stroke','currentColor');n.setAttribute('stroke-width','2.2');n.setAttribute('stroke-linecap','round');n.setAttribute('stroke-linejoin','round');n.setAttribute('aria-hidden','true');n.innerHTML=d;return n;};
 const IC={check:'<path d="M5 12l4 4 10-10"/>',tool:'<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',inbox:'<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5 5h14l2 8v6H3v-6z"/>',chat:'<path d="M4 5h16v11H9l-5 4z"/>',arrow:'<path d="M4 12h16m-6-6 6 6-6 6"/>'};
 const LOOK={
  fixed:{c:'fixed',ic:'check',badge:'FIXED',pose:'thumbs',title:'Bob Fixed It!',say:'Thank you for telling us. You helped make BULIG better for everyone!'},
  processing:{c:'work',ic:'tool',badge:'WORKING ON IT',pose:'blueprint',title:'Bob Is Working On It',say:'We are checking your report now. We will tell you when it is fixed.'},
  rejected:{c:'no',ic:'info',badge:'NOT A BUG',pose:'point',title:'We Checked Your Report',say:'We looked carefully, but we did not find a problem this time.'},
  open:{c:'open',ic:'inbox',badge:'REPORT RECEIVED',pose:'hello',title:'We Got Your Report',say:'Thank you! The BULIG team will check it soon.'}};
 const busyNow=()=>document.documentElement.classList.contains('bulig-boot')||document.querySelector('.wn-shade:not([hidden]):not(.bu-shade),[data-hello]:not(.out),[data-welcome]:not([hidden]),.fr-ov:not([hidden])')||(window.pfOverlayOpen&&window.pfOverlayOpen());
 function show(u){
  const L=LOOK[u.status]||LOOK.open,xp=parseInt(u.deducted_xp,10)||0;
  const reply=String(u.body||'').replace(/\s*XP deducted:\s*\d+\.?\s*$/,'').trim();
  const shade=el('div','wn-shade bu-shade bu-'+L.c);shade.setAttribute('role','dialog');shade.setAttribute('aria-modal','true');shade.setAttribute('aria-labelledby','bu-title');
  const box=el('div','wn-box bu-box');const tape=el('div','wn-tape');tape.setAttribute('aria-hidden','true');
  const bob=el('img','wn-bob');bob.src='assets/images/characters/bob-'+L.pose+'.webp?v=2';bob.alt='Bob the Worker';bob.width=150;bob.height=326;
  const body=el('div','wn-body');const badge=el('span','wn-badge bu-badge');badge.append(svg(IC[L.ic]),document.createTextNode(L.badge));
  const h=el('h2','',L.title);h.id='bu-title';const say=el('p','wn-say');const b=el('b','','Bob the Worker: ');say.append(b,document.createTextNode(L.say));
  const rep=el('div','bu-report');rep.append(el('small','','REPORT #'+u.report_id),el('b','',u.summary?String(u.summary):'Your bug report'));
  body.append(badge,h,say,rep);
  if(reply){const m=el('div','bu-msg');const hd=el('small','');hd.append(svg(IC.chat),document.createTextNode('MESSAGE FROM THE BULIG TEAM'));m.append(hd,el('p','',reply));body.append(m);}
  if(u.status==='rejected'&&xp>0)body.append(el('p','bu-xp','−'+xp+' XP'+' · Please send a report only when something is really not working.'));
  const acts=el('div','bu-acts');const ok=el('button','btn primary wn-ok');ok.type='button';ok.append(svg(IC.check),document.createTextNode(u.status==='fixed'?'Yay, Thank You Bob!':'Got It, Bob!'));
  const link=el('a','btn secondary bu-link');link.href='?page=bug_report&id='+encodeURIComponent(u.report_id);link.append(svg(IC.arrow),document.createTextNode('See the Report and Replies'));
  const status=el('p','bu-status');status.setAttribute('role','status');acts.append(ok,link);body.append(acts,status);
  const tape2=el('div','wn-tape wn-tape-b');tape2.setAttribute('aria-hidden','true');box.append(tape,bob,body,tape2);shade.append(box);document.body.append(shade);shown=shade;
  if(u.status==='fixed')try{window.buligSfx&&window.buligSfx.play('correct');}catch(e){}
  setTimeout(()=>ok.focus({preventScroll:true}),300);
  const ack=async()=>{ok.disabled=true;const fd=new FormData();fd.set('action','bug_popup_ack');fd.set('csrf',csrf);fd.set('event',u.id);
   try{const r=await fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});const j=await r.json();if(!r.ok||!j.ok)throw Error();return true;}
   catch(e){status.textContent='Could not save this yet. Please try again when you are connected.';ok.disabled=false;return false;}};
  ok.addEventListener('click',async()=>{if(await ack()){shade.remove();shown=null;setTimeout(poll,800);}});
  link.addEventListener('click',async e=>{e.preventDefault();await ack();location.href=link.href;});
 }
 async function poll(){
  if(busy||shown||document.hidden||navigator.onLine===false)return;if(busyNow()){setTimeout(poll,4000);return;}busy=true;
  try{const res=await fetch('?page=bug_updates',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(!res.ok)return;const data=await res.json();if(data.update&&!busyNow())show(data.update);}
  catch(e){}finally{busy=false;}
 }
 poll();setInterval(poll,45000);document.addEventListener('visibilitychange',poll);window.addEventListener('online',poll);
})();
