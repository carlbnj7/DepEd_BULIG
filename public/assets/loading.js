/* BULIG loading screens (v1)
   - Opening screen: the logo and a loading bar on the first page of a visit, until the page has loaded.
   - Hello after signing in: the person's own character, until the dashboard has loaded.
   - Opening a lesson: if a lesson takes longer than about 0.3 seconds to open, the level picture and the pupil's character show.
   Each one only covers loading time; none of them makes anyone wait longer. */
(function(){
 'use strict';
 var d=document.documentElement,still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 var since=function(){return performance&&performance.now?performance.now():0;};
 /* Time the page started arriving, so each screen is seen for a moment before it fades. */
 var arrived=(function(){try{var n=performance.getEntriesByType('navigation')[0];return n?n.responseStart:0;}catch(e){return 0;}})();
 function whenLoaded(fn,min,max){var done=false;function go(){if(done)return;done=true;var wait=Math.max(0,min-since());setTimeout(fn,still?0:wait);}
  if(document.readyState==='complete')go();else{window.addEventListener('load',go,{once:true});setTimeout(go,max);}}
 /* ---------- opening screen ---------- */
 function hideBoot(){if(!d.classList.contains('bulig-boot'))return;d.classList.add('boot-out');try{sessionStorage.setItem('bulig-open','1');}catch(e){}setTimeout(function(){d.classList.remove('bulig-boot','boot-out');},still?0:450);}
 if(d.classList.contains('bulig-boot'))whenLoaded(hideBoot,Math.max(1100,arrived+800),4000);
 /* ---------- hello after signing in ---------- */
 var hello=document.querySelector('[data-hello]');
 if(hello)whenLoaded(function(){hello.classList.add('out');setTimeout(function(){hello.remove();document.dispatchEvent(new Event('bulig:hello-done'));},still?0:450);},Math.max(1500,arrived+1100),4500);
 window.buligHelloOpen=function(){return !!document.querySelector('[data-hello]:not(.out)');};
 /* Signing in: the opening screen covers the wait until the dashboard arrives. */
 function showBoot(){d.classList.remove('boot-out');d.classList.add('bulig-boot','boot-quick');}
 document.addEventListener('submit',function(e){var f=e.target;if(f&&f.querySelector&&f.querySelector('input[name=action][value=login]')&&!e.defaultPrevented&&navigator.onLine!==false)setTimeout(function(){if(!e.defaultPrevented)showBoot();},0);});
 window.buligLoading={boot:showBoot};
 /* Coming back with the Back button: never leave a loading screen on top. */
 window.addEventListener('pageshow',function(e){if(e.persisted){d.classList.remove('boot-quick');if(d.classList.contains('bulig-boot'))hideBoot();}});
 /* ---------- opening a lesson (pupils) ---------- */
 var me=null;try{me=JSON.parse((document.querySelector('meta[name="bulig-tour"]')||{}).content||'null');}catch(e){}
 if(!me||me.role!=='pupil')return;
 var LV={1:'Level 1',2:'Level 2A',3:'Level 2B',4:'Level 3',5:'Level 4',6:'Level 5',7:'Level 6',8:'Level 7'},timer=null,ov=null;
 var GENERIC=/^(start|open|continue|play|next|go|let.s|review|again|begin|keep|see)/i;
 function titleOf(a){if(a.dataset.title)return a.dataset.title;var t=(a.getAttribute('aria-label')||'').replace(/\s*\((completed|you are here|open|locked)\)\s*$/,'').trim();
  if(!t){t=(a.textContent||'').replace(/\s+/g,' ').trim();if(GENERIC.test(t)){var box=a.closest('.pf-mapcard,.level-card,.completion-card,section,article');var h=box&&box.querySelector('h1,h2,h3,b,strong');t=h&&h!==a?h.textContent.trim():'';}}
  return t.replace(/^Next lesson:\s*/i,'').slice(0,60);}
 function levelOf(a){var el=a.closest('[data-level]');if(a.dataset.level)return +a.dataset.level;if(el)return +el.getAttribute('data-level');var q=new URLSearchParams(location.search).get('level');return q?+q:0;}
 function show(a){var lv=levelOf(a),t=titleOf(a);ov=document.createElement('div');ov.className='lesson-load'+(lv?' cv-'+lv:'');ov.setAttribute('role','status');ov.setAttribute('aria-live','polite');
  var card=document.createElement('div');card.className='ll-card';var img=document.createElement('img');img.alt='';img.src=me.char;var tx=document.createElement('div');
  var sm=document.createElement('small');sm.textContent=lv?LV[lv]||'':'YOUR LESSON';var b=document.createElement('b');b.textContent=t||'Your lesson';var sp=document.createElement('span');sp.textContent='Opening your lesson…';
  var dots=document.createElement('span');dots.className='ll-dots';dots.innerHTML='<i></i><i></i><i></i>';tx.append(sm,b,sp,dots);card.append(img,tx);ov.append(card);document.body.append(ov);requestAnimationFrame(function(){ov.classList.add('on');});}
 document.addEventListener('click',function(e){if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
  var a=e.target.closest&&e.target.closest('a[href]');if(!a||a.target==='_blank')return;var u;try{u=new URL(a.getAttribute('href'),location.href);}catch(x){return;}
  if(u.origin!==location.origin||u.searchParams.get('page')!=='lesson'||!u.searchParams.get('id'))return;
  if(location.search.indexOf('page=lesson')>=0&&new URLSearchParams(location.search).get('id')===u.searchParams.get('id'))return;
  clearTimeout(timer);timer=setTimeout(function(){show(a);},300);});
 window.addEventListener('pageshow',function(){clearTimeout(timer);if(ov){ov.remove();ov=null;}});
 window.addEventListener('pagehide',function(){clearTimeout(timer);});
})();
