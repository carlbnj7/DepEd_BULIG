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
