/* BULIG motion (v1): buttons that squish with a soft ripple, feedback that flies off to the pupil,
   and overview numbers that count up. Page changes fade smoothly through CSS (theme.css).
   Everything stays still on devices set to reduce motion. */
(function(){
 'use strict';
 if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;
 const $$=(s,r)=>[...(r||document).querySelectorAll(s)];
 /* ---------- ripple where the finger touched ---------- */
 document.addEventListener('pointerdown',e=>{const b=e.target.closest&&e.target.closest('.btn,.dl-go,.cd-dot,.lt-k,.ec-row button,.dk-t');if(!b||b.disabled)return;
  const r=b.getBoundingClientRect(),s=Math.max(r.width,r.height)*1.2,w=document.createElement('span');w.className='rp';w.setAttribute('aria-hidden','true');
  w.style.setProperty('--x',(e.clientX-r.left-s/2)+'px');w.style.setProperty('--y',(e.clientY-r.top-s/2)+'px');w.style.setProperty('--s',s+'px');
  b.classList.add('rp-host');b.append(w);setTimeout(()=>w.remove(),650);},{passive:true});
 /* ---------- feedback flies off to the pupil ---------- */
 document.addEventListener('submit',e=>{const f=e.target;if(!f.closest||!f.closest('.rv-item')||f.dataset.flown)return;const t=f.querySelector('textarea[name=feedback]');if(!t||!t.value.trim())return;
  e.preventDefault();f.dataset.flown='1';const sb=e.submitter;if(sb&&sb.name){const h=document.createElement('input');h.type='hidden';h.name=sb.name;h.value=sb.value;f.append(h);}const item=f.closest('.rv-item'),btn=sb&&sb.classList.contains('rv-redo')?sb:f.querySelector('button.btn.primary');
  if(btn){const r=btn.getBoundingClientRect(),p=document.createElement('span');p.className='fb-plane';p.setAttribute('aria-hidden','true');p.innerHTML='<svg viewBox="0 0 24 24"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></svg>';p.style.setProperty('--x',(r.left+r.width/2-14)+'px');p.style.setProperty('--y',(r.top-10)+'px');document.body.append(p);setTimeout(()=>p.remove(),1100);}
  const c=document.querySelector('.rv-tab.on .rv-count');if(c&&!item.classList.contains('rv-done')&&/^\d+$/.test(c.textContent))c.textContent=Math.max(0,+c.textContent-1);
  item.classList.add('rv-fly');setTimeout(()=>f.submit(),650);},true);
 /* ---------- numbers count up the first time a page opens in this visit ---------- */
 const key='bulig-cu-'+location.search;let seen=false;try{seen=!!sessionStorage.getItem(key);sessionStorage.setItem(key,'1');}catch(e){}
 if(!seen){$$('.ws-kpi b,.ws-bar b,.ws-num,.stat strong').forEach(n=>{const m=n.textContent.trim().match(/^(\d{1,3}(?:,\d{3})*|\d+)(%?)$/);if(!m)return;const to=+m[1].replace(/,/g,'');if(to<2)return;
   const fmt=v=>(to>=1000?v.toLocaleString():String(v))+m[2],t0=performance.now(),dur=Math.min(1200,500+to*4);n.textContent=fmt(0);
   const step=t=>{const k=Math.min(1,(t-t0)/dur),v=Math.round(to*(1-Math.pow(1-k,3)));n.textContent=fmt(v);if(k<1)requestAnimationFrame(step);};requestAnimationFrame(step);});
  document.documentElement.classList.add('cu-grow');}
})();

/* Click effects (v132). A soft ring where the finger or mouse touches, on everything that can be tapped.
   Pupils (and the pupil sign-in page) also get a small burst of stars and dots on big buttons, tabs and cards.
   The icon on a tapped button, tab or menu item gives a little bounce. Teachers and admins get only the ring and the bounce.
   No stars inside lessons, so an effect never looks like a "correct" answer. Off when the device asks for less motion. */
(function(){
 'use strict';
 if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;
 const b=document.body;if(!b)return;
 const kid=b.classList.contains('role-pupil')||b.classList.contains('key-page')||(b.classList.contains('login-page')&&!b.classList.contains('admin-mode'));
 const lesson=b.matches('.view-lesson,.l1-page,.demo-page');
 const TAP='a[href],button,[role=button],summary,label,input[type=checkbox],input[type=radio],select,.tap-choices li';
 const BIG='.btn.primary,.btn.secondary,.pupil-tabbar>a,.lf-go,.level-card a,.navitem,a.st-row,label.st-row,a.pupil-stat,.pupil-stat[role=button],.tour-help,.role-tabs a,.lgn-role,.lgn-scan,.key-go,.notif-bell,.mode-btn,.mobile-signout button,.ql-acct,.np-row';
 const BOUNCE='.btn,.pupil-tabbar>a,.navitem,.st-row,.notif-bell,.mode-btn,.mobile-signout button,.mobile-menu-toggle,.role-tabs a,.lgn-role,.lgn-forgot-link,.ws-tabs a,.rv-tab,.np-row,.admin-pill,.show-password';
 const COLORS=['#ffc629','#1c7a50','#ff8a7a','#7cc4ff','#ffd966','#5db585'];
 const at=(cls,x,y)=>{const e=document.createElement('span');e.className=cls;e.setAttribute('aria-hidden','true');e.style.setProperty('--x',x+'px');e.style.setProperty('--y',y+'px');b.appendChild(e);return e;};
 document.addEventListener('pointerdown',e=>{
  if(e.button>0||!e.target.closest)return;const t=e.target.closest(TAP);if(!t||t.disabled||t.closest('[aria-disabled=true],.tour-layer'))return;
  const r=t.getBoundingClientRect(),x=e.clientX,y=e.clientY;
  /* 1. Ring */
  const ring=at('ck-ring'+(kid?'':' ck-soft'),x,y);ring.style.setProperty('--s',Math.round(Math.min(150,Math.max(54,Math.max(r.width,r.height)*.9)))+'px');setTimeout(()=>ring.remove(),600);
  /* 2. Stars and dots for pupils */
  if(kid&&!lesson&&t.matches(BIG)){const box=at('ck-burst',x,y),n=8;
   for(let i=0;i<n;i++){const p=document.createElement('i'),a=(i/n)*Math.PI*2+Math.random()*.5,d=38+Math.random()*30;p.className=i%2?'dt':'st';
    p.style.setProperty('--dx',(Math.cos(a)*d).toFixed(1)+'px');p.style.setProperty('--dy',(Math.sin(a)*d-8).toFixed(1)+'px');p.style.setProperty('--r',Math.round(Math.random()*240-120)+'deg');p.style.setProperty('--c',COLORS[i%COLORS.length]);box.appendChild(p);}
   setTimeout(()=>box.remove(),750);}
  /* 3. Icon bounce */
  const host=t.closest(BOUNCE);if(host){const ic=host.querySelector('.icon:not(.flip)');if(ic){ic.classList.remove('ck-bounce');void ic.getBoundingClientRect();ic.classList.add('ck-bounce');setTimeout(()=>ic.classList.remove('ck-bounce'),560);}}
 },{passive:true});
})();
