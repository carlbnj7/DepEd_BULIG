/* BULIG delight (v1)
   - Messages: "Saved", "Account created" and other notices slide in from the top, then leave by themselves (or swipe them away).
   - Gentle "try again": the answer box wiggles softly and the reading buddy gives a kind hint.
   - Reading buddy: the pupil's character sits beside the activity, thinks while they type and cheers when they submit.
   - Badges: new badges spin in with sun rays; tap a badge to flip it.
   - Shimmer placeholders while a slow page loads.
   - Bigger buttons on every page for Grades 1 and 2. */
(function(){
 'use strict';
 const $=s=>document.querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const el=(t,c,x)=>{const e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;};
 const store=(k,v)=>{try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v);}catch(e){return null;}};
 let me=null;try{me=JSON.parse(($('meta[name="bulig-tour"]')||{}).content||'null');}catch(e){}
 if($('[data-kid-ui]'))document.body.classList.add('kid-ui');

 /* ---------- one style for all messages ---------- */
 let stack=null;
 function toast(text,kind,ms){if(!stack){stack=el('div','tst-stack');stack.setAttribute('aria-live','polite');document.body.append(stack);}
  const t=el('div','tst tst-'+(kind||'ok'));t.setAttribute('role',kind==='err'?'alert':'status');const ic=el('i','tst-ic',kind==='err'||kind==='wa'?'!':kind==='in'?'i':'✓');ic.setAttribute('aria-hidden','true');
  const x=el('button','tst-x','×');x.type='button';x.setAttribute('aria-label','Close message');const bar=el('span','tst-bar');t.append(ic,el('span','tst-t',text),x,bar);stack.append(t);
  requestAnimationFrame(()=>t.classList.add('on'));const life=ms||(kind==='err'?9000:5200);bar.style.setProperty('--life',life+'ms');
  let timer=setTimeout(close,life),sx=null;function close(){clearTimeout(timer);t.classList.add('out');setTimeout(()=>t.remove(),300);}
  x.addEventListener('click',close);t.addEventListener('mouseenter',()=>{clearTimeout(timer);t.classList.add('hold');});t.addEventListener('mouseleave',()=>{t.classList.remove('hold');timer=setTimeout(close,2000);});
  t.addEventListener('pointerdown',e=>{sx=e.clientX;t.setPointerCapture(e.pointerId);});t.addEventListener('pointermove',e=>{if(sx!==null)t.style.setProperty('--dx',(e.clientX-sx)+'px');});
  t.addEventListener('pointerup',e=>{if(sx===null)return;const d=e.clientX-sx;sx=null;if(Math.abs(d)>70)close();else t.style.setProperty('--dx','0px');});return t;}
 window.buligToast=toast;
 $$('div.notice[role=status]').forEach(n=>{if(n.closest('.tst-stack'))return;const txt=(n.querySelector('span')||n).textContent.trim();if(!txt)return;n.remove();setTimeout(()=>toast(txt,/could not|error|failed|not allowed|incorrect|invalid/i.test(txt)?'err':'ok'),still?0:350);});

 /* ---------- shimmer placeholders while a slow page loads ---------- */
 const main=$('main.main');
 if(main){let tm=null;document.addEventListener('click',e=>{if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey)return;const a=e.target.closest&&e.target.closest('a[href]');if(!a||a.target==='_blank'||a.hasAttribute('download'))return;
   let u;try{u=new URL(a.getAttribute('href'),location.href);}catch(x){return;}if(u.origin!==location.origin||u.hash&&u.pathname===location.pathname&&u.search===location.search)return;const pg=u.searchParams.get('page')||'';if(/csv|backup|download|lesson$/.test(pg)||pg==='lesson')return;
   clearTimeout(tm);tm=setTimeout(()=>{const sk=el('div','skel');sk.setAttribute('aria-hidden','true');for(let i=0;i<5;i++)sk.append(el('i',i===0?'sk-h':i===4?'sk-s':''));main.prepend(sk);main.classList.add('skel-on');},450);});
  window.addEventListener('pageshow',()=>{clearTimeout(tm);main.classList.remove('skel-on');$$('.skel',main).forEach(x=>x.remove());});}

 /* ---------- badges: new ones spin in, tap to flip ---------- */
 const shelf=$('.pf-shelf');
 if(shelf&&me){const items=$$('.pf-shelf-item',shelf),key='bulig-badges-'+me.id,got=items.filter(i=>i.classList.contains('pf-got')).map(i=>(i.querySelector('h2')||{}).textContent||'');
  const seen=(()=>{try{return JSON.parse(store(key)||'null');}catch(e){return null;}})();store(key,JSON.stringify(got));
  const fresh=seen?items.filter(i=>i.classList.contains('pf-got')&&!seen.includes((i.querySelector('h2')||{}).textContent||'')):[];
  items.forEach(i=>{i.tabIndex=0;i.setAttribute('role','button');i.setAttribute('aria-label',(i.querySelector('h2')||{}).textContent+'. Tap to flip.');
   const back=el('div','pf-back');back.append(el('b','',(i.querySelector('h2')||{}).textContent||''),el('p','',(i.querySelector('p')||{}).textContent||''),el('small','',(i.querySelector('small')||{}).textContent||''));i.append(back);
   const flip=()=>i.classList.toggle('flipped');i.addEventListener('click',flip);i.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();flip();}});});
  if(fresh.length&&!still){const b=fresh[0],m=b.querySelector('.pf-shelf-medal');const ov=el('div','bdg-ov');ov.setAttribute('role','status');const rays=el('span','bdg-rays');const big=el('div','bdg-big');if(m)big.append(m.cloneNode(true));
   ov.append(rays,big,el('b','bdg-name','New badge: '+((b.querySelector('h2')||{}).textContent||'')));document.body.append(ov);requestAnimationFrame(()=>ov.classList.add('on'));
   setTimeout(()=>{const r=b.getBoundingClientRect(),g=big.getBoundingClientRect();big.style.setProperty('--fx',(r.left+r.width/2-(g.left+g.width/2))+'px');big.style.setProperty('--fy',(r.top+60-(g.top+g.height/2))+'px');ov.classList.add('fly');},1700);
   setTimeout(()=>{ov.remove();b.classList.add('bdg-landed');b.scrollIntoView({block:'nearest',behavior:'smooth'});},2500);ov.addEventListener('click',()=>ov.remove());}}

 /* ---------- activity: reading buddy and gentle "try again" ---------- */
 const form=$('#activity-form');
 if(form&&me&&me.role==='pupil'){
  const readonly=form.dataset.draft!=='on';const wrap=el('div','buddy');wrap.setAttribute('aria-hidden','true');const bub=el('div','buddy-bub');const img=el('img','buddy-img');img.alt='';img.src=me.hi||me.char;wrap.append(bub,img);
  const hide=el('button','buddy-hide','×');hide.type='button';hide.setAttribute('aria-label','Hide the reading buddy');wrap.append(hide);
  if(store('bulig-buddy-off')!=='1')document.body.append(wrap);hide.addEventListener('click',()=>{wrap.remove();store('bulig-buddy-off','1');});
  let bt=null;const say=(html,cls,ms,pose)=>{bub.className='buddy-bub on '+(cls||'');bub.replaceChildren();if(typeof html==='string')bub.textContent=html;else bub.append(html);img.src=pose==='cheer'?me.char:(me.hi||me.char);wrap.classList.remove('hop');void wrap.offsetWidth;if(pose)wrap.classList.add('hop');clearTimeout(bt);if(ms)bt=setTimeout(()=>{bub.classList.remove('on');img.src=me.hi||me.char;},ms);};
  const dots=()=>{const d=el('span','b-dots');d.append(el('i'),el('i'),el('i'));return d;};
  let idle=null,typing=null;const resetIdle=()=>{clearTimeout(idle);if(!readonly)idle=setTimeout(()=>say('You can do it! Tap Listen if you need help.','',7000),60000);};
  if(!readonly){setTimeout(()=>say(['Let’s do this!','You can do it!','Ready? Let’s read!'][Math.floor(Math.random()*3)],'',3500,'cheer'),1600);resetIdle();
   form.addEventListener('input',e=>{if(!e.isTrusted)return;resetIdle();if(!bub.classList.contains('think'))say(dots(),'think');clearTimeout(typing);typing=setTimeout(()=>{if(bub.classList.contains('think'))bub.classList.remove('on');},1500);});}
  const fb=$('#answer-feedback'),HINTS=['Almost! Listen to it again, then try once more.','Good try! Look at the picture carefully.','So close! Read the question again slowly.','Nice effort! Try a different answer.'];let tries=0;
  let last='';if(fb)new MutationObserver(()=>{const now=fb.className.replace(/\s*gentle/,'')+'|'+fb.textContent.trim();if(now===last)return;last=now;if(fb.classList.contains('tryagain')&&fb.textContent.trim()){fb.classList.add('gentle');const box=$('.native-card:not([hidden]) .native-answer')||$('#response')||$('.answer-card');
     if(box&&!still){box.classList.remove('wig');void box.offsetWidth;box.classList.add('wig');}say(HINTS[tries++%HINTS.length],'hint',8000,'cheer');}
    else if(fb.classList.contains('success')&&fb.textContent.trim()){clearTimeout(idle);say(['Great answer!','Well done!','You did it!','Super reading!'][Math.floor(Math.random()*4)],'yay',4500,'cheer');}}).observe(fb,{attributes:true,attributeFilter:['class'],childList:true});}

 /* ---------- small helpers: print buttons and filters that apply on change ---------- */
 $$('[data-print]').forEach(b=>b.addEventListener('click',()=>window.print()));
 /* My profile: "Upload a photo" saves right after a photo is picked. */
 $$('[data-upload-auto]').forEach(i=>{const f=i.form;if(!f)return;f.classList.add('auto-up');i.addEventListener('change',()=>{if(i.files&&i.files.length){const b=f.querySelector('.me-file');if(b)b.classList.add('busy');f.requestSubmit?f.requestSubmit():f.submit();}});});
 /* About BULIG opens in a window over the page. */
 const abd=$('.ab-dialog');
 if(abd){$$('[data-about-open]').forEach(b=>b.addEventListener('click',()=>{if(abd.showModal)abd.showModal();else abd.setAttribute('open','');}));
  abd.addEventListener('click',e=>{if(e.target===abd)abd.close();});}
 $$('select[data-autosubmit]').forEach(sel=>{const f=sel.form;if(!f)return;const go=f.querySelector('.pg-go');if(go)go.hidden=true;sel.addEventListener('change',()=>f.submit());});

 /* ---------- daily goal ring: fills up, and sparkles the first time the goal is reached today ---------- */
 const goal=$('.goal-card');
 if(goal){const ring=goal.querySelector('.goal-ring'),to=+(ring.dataset.p||0),k='bulig-goal-'+(me?me.id:'x');
  if(still){ring.style.setProperty('--p',to);}else{let v=0;const t0=performance.now(),step=t=>{v=Math.min(1,(t-t0)/900);ring.style.setProperty('--p',Math.round(to*(1-Math.pow(1-v,3))));if(v<1)requestAnimationFrame(step);};setTimeout(()=>requestAnimationFrame(step),300);}
  if(goal.classList.contains('goal-done')&&store(k)!==goal.dataset.goal){store(k,goal.dataset.goal);if(!still)setTimeout(()=>goal.classList.add('goal-pop'),1200);}}

 /* ---------- tap any word to hear it (instructions, questions and card text; never in reading tests) ---------- */
 const TAP='.activity-title,.activity-instruction,.answer-card .prompt,.sentence-card label,.native-direction,.native-lead,.native-heading,.native-item-label,.native-answer-label';
 if($('#activity-form')&&window.speechSynthesis&&!$('.word-grid,.fluency-passage')){
  let bub=null,hl=null;const close=()=>{if(bub){bub.remove();bub=null;}if(window.CSS&&CSS.highlights)CSS.highlights.delete('bulig-word');};
  const say=w=>{try{const s=speechSynthesis,u=new SpeechSynthesisUtterance(w),vs=s.getVoices(),pick=vs.find(v=>v.voiceURI===store('bulig-voice'))||vs.find(v=>/^en/i.test(v.lang)&&/female|samantha|zira|susan|karen|aria|jenny|ava|hazel/i.test(v.name))||vs.find(v=>/^en/i.test(v.lang));if(pick)u.voice=pick;u.lang=pick?pick.lang:'en-US';u.rate=.8;s.cancel();s.speak(u);}catch(e){}};
  document.addEventListener('click',e=>{if(bub&&bub.contains(e.target))return;close();
   const host=e.target.closest&&e.target.closest(TAP);if(!host||e.target.closest('a,button,input,textarea,select,label.answer-option,.lt-k,.native-choices'))return;
   if(String(window.getSelection&&getSelection())!=='')return;
   let node=null,off=0;if(document.caretPositionFromPoint){const c=document.caretPositionFromPoint(e.clientX,e.clientY);if(c){node=c.offsetNode;off=c.offset;}}else if(document.caretRangeFromPoint){const r=document.caretRangeFromPoint(e.clientX,e.clientY);if(r){node=r.startContainer;off=r.startOffset;}}
   if(!node||node.nodeType!==3||!host.contains(node))return;const t=node.textContent;let a=off,b=off;const W=/[\p{L}\p{N}'’-]/u;while(a>0&&W.test(t[a-1]))a--;while(b<t.length&&W.test(t[b]))b++;
   const word=t.slice(a,b).replace(/^['’-]+|['’-]+$/g,'');if(!word||word.length>30||!/\p{L}/u.test(word))return;
   const rg=document.createRange();rg.setStart(node,a);rg.setEnd(node,b);const box=rg.getBoundingClientRect();if(!box.width)return;
   if(window.CSS&&CSS.highlights&&window.Highlight)CSS.highlights.set('bulig-word',new Highlight(rg));
   bub=el('div','word-pop');bub.setAttribute('role','status');const big=el('b','',word),again=el('button','word-again','Hear it again');again.type='button';again.addEventListener('click',()=>say(word));bub.append(big,again);document.body.append(bub);
   const bw=bub.offsetWidth,bh=bub.offsetHeight;let x=Math.min(Math.max(8,box.left+box.width/2-bw/2),innerWidth-bw-8),y=box.top-bh-12;if(y<8){y=box.bottom+12;bub.classList.add('below');}
   bub.style.setProperty('--x',x+'px');bub.style.setProperty('--y',y+'px');bub.style.setProperty('--ax',(box.left+box.width/2-x)+'px');requestAnimationFrame(()=>bub.classList.add('on'));say(word);});
  window.addEventListener('scroll',close,{passive:true});document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});}
})();
