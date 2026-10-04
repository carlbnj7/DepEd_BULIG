/* BULIG activity screen (v1)
   - Tidy header: one progress piece per activity and a small "Saved" sign.
   - Answer in one place: microphone inside the answer box, numbered card dots, buttons kept at the bottom,
     and a friendly check before submitting with an empty card.
   - Read-along: words light up while Listen reads them. The microphone shows that it is listening.
   - Cards slide like pages, XP coins fly to the XP chip after an activity.
   - Letter tiles for missing letters, pictures open big inside BULIG, reading helpers (bigger text, reading ruler). */
(function(){
 'use strict';
 const $=s=>document.querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 const form=$('#activity-form');if(!form)return;
 const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const el=(t,c,x)=>{const e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;};
 const store=(k,v)=>{try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v);}catch(e){return null;}};
 const deck=$('[data-native-deck]'),mode=form.dataset.mode,readonly=form.dataset.draft!=='on';
 document.body.classList.add('ux2');
 const MIC='<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>';

 /* ---------- tidy header ---------- */
 const head=$('.activity-header'),prog=$('.activity-progress progress');
 if(head&&prog){const max=+prog.max||0,val=+prog.value||0;if(max>0&&max<=30){const seg=el('div','ap-seg');seg.setAttribute('aria-hidden','true');for(let i=0;i<max;i++)seg.append(el('i',i<val?'d':(i===val&&!readonly?'c':'')));prog.after(seg);head.classList.add('has-seg');}}
 const ds=$('#draft-status');
 if(head&&ds&&mode!=='none'){const chip=el('span','ap-saved');chip.setAttribute('aria-hidden','true');head.append(chip);
  const paint=()=>{const t=ds.textContent||'';let k='',x='';if(/saving/i.test(t)){k='busy';x='Saving…';}else if(/not saved/i.test(t)){k='bad';x='Not saved';}else if(/device|offline/i.test(t)){k='dev';x='On this device';}else if(/saved|restored/i.test(t)){k='ok';x='Saved';}chip.className='ap-saved'+(k?' on '+k:'');chip.textContent=x;};
  paint();new MutationObserver(paint).observe(ds,{childList:true,characterData:true,subtree:true});}

 /* ---------- microphone inside the answer box ---------- */
 const mic=$('#recognize');
 function micButton(box){if(!mic||mic.disabled||readonly||!box||box.dataset.mic)return;box.dataset.mic='1';const wrap=el('div','ab-wrap');box.parentNode.insertBefore(wrap,box);wrap.append(box);
  const b=el('button','ab-mic');b.type='button';b.innerHTML=MIC;b.setAttribute('aria-label','Speak your answer');b.title='Speak your answer';
  b.addEventListener('click',()=>{box.focus({preventScroll:true});mic.click();});wrap.append(b);}
 if(deck)$$('.native-answer',deck).forEach(micButton);else{const r=$('#response');if(r&&r.tagName==='TEXTAREA'&&!$('#fluency-timer'))micButton(r);}
 if(mic&&!mic.disabled&&$('.ab-mic'))document.body.classList.add('ux2-mic');
 /* Listening pill: glowing microphone, moving bars and the words heard so far. */
 let pill=null;
 document.addEventListener('bulig:mic',e=>{const d=e.detail||{};
  $$('.ab-mic').forEach(b=>b.classList.toggle('on',!!d.on||(!!pill&&d.on!==false&&!d.heard&&!d.error)));
  if(d.on){pill=el('div','mic-pill');pill.setAttribute('role','status');pill.innerHTML='<span class="mp-dot">'+MIC+'</span><span class="mp-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>';pill.append(el('span','mp-txt','Listening… say your answer'));document.body.append(pill);requestAnimationFrame(()=>pill&&pill.classList.add('on'));return;}
  if(!pill)return;const t=pill.querySelector('.mp-txt');
  if(d.words){t.textContent='“'+d.words.trim()+'”';}
  if(d.heard){pill.classList.add('done');t.textContent='I heard: “'+d.heard+'” ✓ Added to your answer';}
  if(d.error){pill.classList.add('err');t.textContent=d.error==='not-allowed'?'The microphone is not allowed. You can type instead.':'I could not hear clearly. Try again or type.';}
  if(d.on===false){const p=pill;pill=null;setTimeout(()=>{p.classList.remove('on');setTimeout(()=>p.remove(),300);},p.classList.contains('done')||p.classList.contains('err')?2200:300);$$('.ab-mic').forEach(b=>b.classList.remove('on'));}});

 /* ---------- card dots, slide and empty check ---------- */
 const answers=deck?$$('.native-answer',deck):[];
 if(deck&&window.buligDeck){const D=window.buligDeck,nav=deck.querySelector('.native-card-nav'),n=D.cards.length;
  if(n>1&&nav){const dots=el('div','cd-dots');dots.setAttribute('role','tablist');dots.setAttribute('aria-label','Cards');
   for(let i=0;i<n;i++){const b=el('button','cd-dot',String(i+1));b.type='button';b.setAttribute('aria-label','Card '+(i+1));b.addEventListener('click',()=>D.show(i));dots.append(b);}
   nav.before(dots);if(answers.length)dots.after(el('p','cd-hint','Green cards are answered. Tap a number to jump.'));
   const paint=()=>{const seen=D.at;$$('.cd-dot',dots).forEach((b,i)=>{const a=answers[i];b.classList.toggle('ok',a?!!a.value.trim():i<seen);b.classList.toggle('cur',i===seen);b.setAttribute('aria-current',i===seen?'step':'false');});};
   answers.forEach(a=>a.addEventListener('input',paint));document.addEventListener('bulig:card',paint);paint();}
  if(nav)nav.classList.add('deck-bar');
  document.addEventListener('bulig:card',e=>{const {at,from}=e.detail;const c=D.cards[at];if(!c||still)return;c.classList.remove('nc-next','nc-prev');void c.offsetWidth;c.classList.add(at>from?'nc-next':'nc-prev');});
  /* After saving, the "Next" link takes the place of the card buttons. */
  const fb=$('#answer-feedback'),nx=$('#next-activity');
  if(fb&&nx&&nav&&!readonly)new MutationObserver(()=>{if(!nx.hidden&&fb.classList.contains('success')){nav.replaceChildren(nx);nx.classList.add('deck-next');}}).observe(nx,{attributes:true,attributeFilter:['hidden']});}
 let submitAnyway=false;
 document.addEventListener('submit',e=>{if(e.target!==form||submitAnyway||readonly||mode!=='answer'||!deck)return;
  const empty=answers.map((a,i)=>a.value.trim()?-1:i).filter(i=>i>=0);if(!empty.length)return;
  e.preventDefault();e.stopImmediatePropagation();const first=empty[0];$('.empty-check')?.remove();
  const t=el('div','empty-check');t.setAttribute('role','alertdialog');t.append(el('b','',empty.length===1?'Card '+(first+1)+' is still empty.':empty.length+' cards are still empty.'),el('span','','Go back and answer '+(empty.length===1?'it':'them')+', or submit anyway?'));
  const row=el('div','ec-row'),go=el('button','ec-go','Go to card '+(first+1)),any=el('button','ec-any','Submit anyway');go.type=any.type='button';row.append(go,any);t.append(row);document.body.append(t);requestAnimationFrame(()=>t.classList.add('on'));go.focus();
  const close=()=>{t.classList.remove('on');setTimeout(()=>t.remove(),250);};
  go.addEventListener('click',()=>{close();window.buligDeck&&window.buligDeck.show(first);setTimeout(()=>answers[first]&&answers[first].focus(),200);});
  any.addEventListener('click',()=>{close();submitAnyway=true;form.requestSubmit();submitAnyway=false;});},true);

 /* ---------- XP coins fly to the XP chip ---------- */
 const fbx=$('#answer-feedback'),xpc=$('.activity-meta .xpchip');
 if(fbx&&xpc)new MutationObserver(()=>{const got=fbx.querySelector('.earned-xp');if(!got||got.dataset.flown)return;got.dataset.flown='1';const amt=(got.textContent.match(/\d+/)||['0'])[0];
  const done=()=>{xpc.classList.add('xp-got');xpc.lastChild.textContent='+'+amt+' XP earned';};
  if(still){done();return;}const a=got.getBoundingClientRect(),b=xpc.getBoundingClientRect();
  for(let i=0;i<6;i++){const c=el('span','xp-coin');c.style.setProperty('--x0',(a.left+a.width/2-9+(i%3-1)*14)+'px');c.style.setProperty('--y0',(a.top+a.height/2-9)+'px');c.style.setProperty('--dx',(b.left+b.width/2-(a.left+a.width/2))+'px');c.style.setProperty('--dy',(b.top+b.height/2-(a.top+a.height/2))+'px');c.style.setProperty('--d',(i*70)+'ms');document.body.append(c);setTimeout(()=>c.remove(),1400);}
  setTimeout(()=>{done();xpc.classList.remove('xp-bump');void xpc.offsetWidth;xpc.classList.add('xp-bump');},850);}).observe(fbx,{childList:true,subtree:true});

 /* ---------- read-along: words light up while Listen reads ---------- */
 const norm=w=>w.toLowerCase().replace(/[’']/g,"'").replace(/[^\p{L}\p{N}']/gu,'');
 function wrapWords(root){if(!root||root.dataset.ra)return;root.dataset.ra='1';const walk=document.createTreeWalker(root,NodeFilter.SHOW_TEXT,{acceptNode:n=>n.nodeValue.trim()&&!n.parentNode.closest('button,textarea,script,.ra-skip')?1:2});const nodes=[];while(walk.nextNode())nodes.push(walk.currentNode);
  nodes.forEach(n=>{const parts=n.nodeValue.split(/(\s+)/);const f=document.createDocumentFragment();parts.forEach(p=>{if(!p)return;if(/^\s+$/.test(p)||!/[\p{L}\p{N}]/u.test(p)){f.append(p);return;}const s=el('span','rw',p);s.dataset.w=norm(p);f.append(s);});n.parentNode.replaceChild(f,n);});}
 function targets(){const card=$('.native-card:not([hidden])');const t=[];const ti=$('.activity-title'),ins=$('.activity-instruction');if(ti)t.push(ti);if(ins)t.push(ins);
  if(card)card.querySelectorAll('.native-lead,.native-heading,.native-question,.native-passage,.native-choices li,.word-bank li').forEach(x=>t.push(x));
  else document.querySelectorAll('.answer-card .prompt,.fluency-title,.fluency-text,.sentence-card label').forEach(x=>t.push(x));return t;}
 let words=[],ptr=0;
 document.addEventListener('bulig:speak',e=>{const d=e.detail||{};
  if(d.type==='start'){if(!d.from||!words.length){targets().forEach(wrapWords);$$('.rw.on,.rw.done').forEach(x=>x.classList.remove('on','done'));words=$$('.rw').filter(x=>x.offsetParent!==null);ptr=0;}return;}
  if(d.type==='word'){const w=norm(d.word);if(!w)return;for(let i=ptr;i<Math.min(words.length,ptr+10);i++){if(words[i].dataset.w===w||words[i].dataset.w.replace(/'s$/,'')===w){for(let j=0;j<i;j++)if(!words[j].classList.contains('done')){words[j].classList.remove('on');words[j].classList.add('done');}words[i].classList.add('on');ptr=i+1;break;}}return;}
  if(d.type==='end'){setTimeout(()=>$$('.rw.on,.rw.done').forEach(x=>x.classList.remove('on','done')),600);words=[];ptr=0;}});
 document.addEventListener('bulig:card',()=>{$$('.rw.on,.rw.done').forEach(x=>x.classList.remove('on','done'));words=[];ptr=0;});

 /* ---------- letter tiles for missing letters (cards like “___og”) ---------- */
 if(deck&&!readonly)$$('.native-card',deck).forEach(card=>{const q=card.querySelector('.native-question'),a=card.querySelector('.native-answer');if(!q||!a||card.querySelector('.native-choices,[data-match-board],[data-letter-grid]'))return;
  const txt=q.textContent;if(!/_{2,}/.test(txt)||txt.length>160||(txt.match(/_{2,}/g)||[]).length>1)return;
  /* the blank becomes a slot that shows the letters */
  const walk=document.createTreeWalker(q,NodeFilter.SHOW_TEXT);let slot=null;while(walk.nextNode()){const n=walk.currentNode,m=n.nodeValue.match(/_{2,}/);if(!m)continue;const after=n.splitText(m.index);after.nodeValue=after.nodeValue.slice(m[0].length);slot=el('span','lt-slot ra-skip');slot.setAttribute('aria-hidden','true');n.parentNode.insertBefore(slot,after);break;}
  const sync=()=>{if(slot){slot.textContent=a.value.trim();slot.classList.toggle('f',!!a.value.trim());}};a.addEventListener('input',sync);sync();
  const keys=el('div','lt-keys');keys.setAttribute('aria-label','Letter tiles');'abcdefghijklmnopqrstuvwxyz'.split('').forEach(ch=>{const b=el('button','lt-k',ch);b.type='button';b.addEventListener('click',()=>{a.value=(a.value||'')+ch;a.dispatchEvent(new Event('input',{bubbles:true}));b.classList.remove('hit');void b.offsetWidth;b.classList.add('hit');});keys.append(b);});
  const er=el('button','lt-k lt-w','⌫ Erase');er.type='button';er.addEventListener('click',()=>{a.value=(a.value||'').slice(0,-1);a.dispatchEvent(new Event('input',{bubbles:true}));});
  const kb=el('button','lt-k lt-w','abc');kb.type='button';kb.setAttribute('aria-label','Use the phone keyboard');kb.addEventListener('click',()=>a.focus());keys.append(er,kb);
  (card.querySelector('.native-pictures')||q).after(keys);card.classList.add('has-tiles');});

 /* ---------- pictures open big inside BULIG ---------- */
 const PICS='.native-pictures img,.activity-visual img,.visual-gallery img,.fluency-pictures img,.answer-card .prompt img';
 function openViewer(list,start){let i=start,z=1,tx=0,ty=0;const v=el('div','pv');v.setAttribute('role','dialog');v.setAttribute('aria-modal','true');v.setAttribute('aria-label','Picture');
  const top=el('div','pv-top'),cnt=el('span','pv-cnt'),x=el('button','pv-x','×');x.type='button';x.setAttribute('aria-label','Close picture');top.append(cnt,x);
  const stage=el('div','pv-stage'),img=el('img','pv-img');img.alt='';stage.append(img);const tip=el('p','pv-tip',list.length>1?'Pinch or double-tap to zoom · swipe for the next picture':'Pinch or double-tap to zoom');
  const dots=el('div','pv-dots');list.forEach(()=>dots.append(el('i')));const pv=el('button','pv-nav pv-prev','‹'),nv=el('button','pv-nav pv-next','›');pv.type=nv.type='button';pv.setAttribute('aria-label','Previous picture');nv.setAttribute('aria-label','Next picture');
  v.append(top,stage,tip,dots);if(list.length>1)v.append(pv,nv);document.body.append(v);document.body.classList.add('pv-lock');requestAnimationFrame(()=>v.classList.add('on'));
  const apply=()=>{img.style.setProperty('--z',z);img.style.setProperty('--tx',tx+'px');img.style.setProperty('--ty',ty+'px');};
  const show=k=>{i=(k+list.length)%list.length;z=1;tx=ty=0;apply();img.src=list[i].currentSrc||list[i].src||list[i].dataset.src;img.alt=list[i].alt||'';cnt.textContent='Picture '+(i+1)+' of '+list.length;$$('i',dots).forEach((d,j)=>d.classList.toggle('on',j===i));};
  const close=()=>{v.classList.remove('on');document.body.classList.remove('pv-lock');document.removeEventListener('keydown',key,true);setTimeout(()=>v.remove(),250);};
  const key=e=>{if(e.key==='Escape'){e.preventDefault();close();}else if(e.key==='ArrowRight')show(i+1);else if(e.key==='ArrowLeft')show(i-1);};
  x.addEventListener('click',close);pv.addEventListener('click',()=>show(i-1));nv.addEventListener('click',()=>show(i+1));document.addEventListener('keydown',key,true);
  v.addEventListener('click',e=>{if(e.target===v||e.target===stage)close();});
  const pts=new Map();let d0=0,z0=1,sx=0,sy=0,tx0=0,ty0=0,lastTap=0;
  stage.addEventListener('pointerdown',e=>{stage.setPointerCapture(e.pointerId);pts.set(e.pointerId,[e.clientX,e.clientY]);if(pts.size===2){const [a,b]=[...pts.values()];d0=Math.hypot(a[0]-b[0],a[1]-b[1]);z0=z;}else{sx=e.clientX;sy=e.clientY;tx0=tx;ty0=ty;}});
  stage.addEventListener('pointermove',e=>{if(!pts.has(e.pointerId))return;pts.set(e.pointerId,[e.clientX,e.clientY]);if(pts.size===2){const [a,b]=[...pts.values()];z=Math.max(1,Math.min(4,z0*Math.hypot(a[0]-b[0],a[1]-b[1])/(d0||1)));apply();}else if(z>1){tx=tx0+e.clientX-sx;ty=ty0+e.clientY-sy;apply();}});
  stage.addEventListener('pointerup',e=>{pts.delete(e.pointerId);if(pts.size)return;const dx=e.clientX-sx,dy=e.clientY-sy;
   if(z===1&&Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy)&&list.length>1){show(i+(dx<0?1:-1));return;}
   const now=Date.now();if(Math.abs(dx)<8&&Math.abs(dy)<8){if(now-lastTap<320){z=z>1?1:2.2;tx=ty=0;apply();lastTap=0;}else lastTap=now;}});
  stage.addEventListener('pointercancel',e=>pts.delete(e.pointerId));
  stage.addEventListener('wheel',e=>{e.preventDefault();z=Math.max(1,Math.min(4,z*(e.deltaY<0?1.15:1/1.15)));if(z===1)tx=ty=0;apply();},{passive:false});
  show(start);x.focus();}
 document.addEventListener('click',e=>{const im=e.target.closest&&e.target.closest(PICS);if(!im||im.closest('.match-board,.drawing-panel,.worksheet-surface'))return;
  e.preventDefault();e.stopPropagation();const group=im.closest('.native-card,.native-pictures,.visual-gallery,.fluency-pictures,.answer-card,.activity-main')||document;
  const list=$$(PICS,group).filter(x=>!x.closest('.match-board,.drawing-panel')&&(x.offsetParent!==null||x===im));openViewer(list.length?list:[im],Math.max(0,list.indexOf(im)));},true);

 /* ---------- reading helpers: bigger text and a reading ruler ---------- */
 const passages=$$('.fluency-text,.native-passage');
 if(passages.length){const SZ=['','rh-m','rh-l'];let sz=+store('bulig-read-size')||0;
  passages.forEach(p=>{if(p.dataset.rh)return;p.dataset.rh='1';
   /* one line (or one sentence) per piece, so the ruler can show one at a time */
   const blocks=p.matches('.native-passage')?[...p.querySelectorAll('p')].concat(p.querySelector('p')?[]:[p]):[...p.querySelectorAll('p')];
   blocks.forEach(b=>{const verse=!!b.querySelector('br'),out=[];let line=[];const flush=()=>{if(line.length){out.push(line);line=[];}};
    [...b.childNodes].forEach(n=>{if(n.nodeName==='BR'){flush();return;}
     if(n.nodeType===3&&!verse){const parts=n.nodeValue.split(/(?<=[.!?])\s+/);parts.forEach((t,k)=>{if(!t)return;line.push(document.createTextNode(t+(k<parts.length-1?' ':'')));if(k<parts.length-1)flush();});return;}
     line.push(n);});flush();
    if(out.length<2)return;b.replaceChildren();out.forEach(nodes=>{const s=el('span','rl');nodes.forEach(n=>s.append(n));b.append(s);});b.classList.add('rl-block',verse?'rl-lines':'rl-prose');});
   const bar=el('div','rh-bar ra-skip'),aa=el('button','rh-aa','Aa'),ru=el('button','rh-ru','Reading ruler');aa.type=ru.type='button';aa.setAttribute('aria-label','Make the text bigger');ru.setAttribute('aria-pressed','false');
   const apply=()=>{SZ.forEach(c=>c&&p.classList.remove(c));if(SZ[sz])p.classList.add(SZ[sz]);aa.dataset.s=sz;};apply();
   aa.addEventListener('click',()=>{sz=(sz+1)%SZ.length;store('bulig-read-size',String(sz));passages.forEach(x=>{SZ.forEach(c=>c&&x.classList.remove(c));if(SZ[sz])x.classList.add(SZ[sz]);});});
   const lines=()=>$$('.rl',p);let cur=0;
   const move=k=>{const L=lines();if(!L.length)return;cur=Math.max(0,Math.min(L.length-1,k));L.forEach((x,j)=>x.classList.toggle('rl-on',j===cur));L[cur].scrollIntoView({block:'nearest',behavior:still?'auto':'smooth'});};
   ru.addEventListener('click',()=>{const on=!p.classList.contains('rh-ruler');p.classList.toggle('rh-ruler',on);ru.setAttribute('aria-pressed',String(on));ru.classList.toggle('on',on);if(on)move(cur);else lines().forEach(x=>x.classList.remove('rl-on'));});
   p.addEventListener('click',e=>{if(!p.classList.contains('rh-ruler'))return;const l=e.target.closest('.rl');const L=lines();if(l&&L.indexOf(l)!==cur)move(L.indexOf(l));else move(cur+1);});
   bar.append(aa,ru);p.before(bar);});}
})();
