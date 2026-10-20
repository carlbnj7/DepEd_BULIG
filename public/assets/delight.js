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
 /* A message waits until the greeting and the welcome card after signing in are closed, so it is not hidden behind them. */
 const afterWelcome=async fn=>{const wait=n=>new Promise(r=>document.addEventListener(n,r,{once:true}));
  if(window.buligHelloOpen&&window.buligHelloOpen())await wait('bulig:hello-done');await new Promise(r=>setTimeout(r,80));
  if(window.pfOverlayOpen&&window.pfOverlayOpen())await wait('pf:overlays-done');fn();};
 $$('div.notice[role=status]').forEach(n=>{if(n.closest('.tst-stack'))return;const txt=(n.querySelector('span')||n).textContent.trim();if(!txt)return;n.remove();setTimeout(()=>afterWelcome(()=>toast(txt,/could not|error|failed|not allowed|incorrect|invalid/i.test(txt)?'err':'ok',/ID is now/.test(txt)?12000:0)),still?0:350);});

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
    else if(fb.classList.contains('success')&&fb.textContent.trim()){clearTimeout(idle);say(['Great answer!','Well done!','You did it!','Super reading!'][Math.floor(Math.random()*4)],'yay',4500,'cheer');wrap.classList.add('show');setTimeout(()=>wrap.classList.remove('show'),4500);}}).observe(fb,{attributes:true,attributeFilter:['class'],childList:true});}

 /* ---------- small helpers: print buttons and filters that apply on change ---------- */
 $$('[data-print]').forEach(b=>b.addEventListener('click',()=>window.print()));
 /* My profile: "Upload a photo" saves right after a photo is picked. */
 $$('[data-upload-auto]').forEach(i=>{const f=i.form;if(!f)return;f.classList.add('auto-up');i.addEventListener('change',()=>{if(i.files&&i.files.length&&!i.hasAttribute('data-crop')){const b=f.querySelector('.me-file');if(b)b.classList.add('busy');f.requestSubmit?f.requestSubmit():f.submit();}});});
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

/* v170: crop your own photo before it is saved. Pick a photo, move and zoom it inside the circle, see how it will look
   (profile and the small picture at the top), then "Use this photo". The cropped square is sent instead of the big file.
   Without JavaScript the plain upload still works. */
(function(){
 const ins=document.querySelectorAll('input[type=file][data-crop]');if(!ins.length||!window.HTMLCanvasElement)return;
 const NS='http://www.w3.org/2000/svg',OUT=512;
 const ic=d=>{const s=document.createElementNS(NS,'svg');s.setAttribute('viewBox','0 0 24 24');s.setAttribute('aria-hidden','true');s.innerHTML=d;return s;};
 const el=(t,c,x)=>{const e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;};
 const btn=(c,x,d)=>{const b=el('button','btn '+c);b.type='button';if(d)b.append(ic(d));b.append(document.createTextNode(x));return b;};
 function open(input){
  const file=input.files&&input.files[0];if(!file)return;const form=input.form;
  if(!/^image\/(png|jpeg|webp)$/.test(file.type)){alert('Please choose a PNG, JPEG or WebP photo.');input.value='';return;}
  const img=new Image();let rot=0,z=1,x=0,y=0,S=300,base=1;
  const ov=el('div','crop-ov'),box=el('div','crop-box');box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');box.setAttribute('aria-labelledby','crop-h');
  const h=el('h2',null,'Fit your photo');h.id='crop-h';const tip=el('p','crop-tip','Drag the photo to move it. Use the slider to zoom.');
  const stage=el('div','crop-stage'),cv=el('canvas','crop-cv'),ring=el('div','crop-ring');stage.append(cv,ring);stage.tabIndex=0;stage.setAttribute('aria-label','Photo. Use the arrow keys to move it, plus and minus to zoom.');
  const zl=el('label','crop-zoom'),zr=el('input');zr.type='range';zr.min='1';zr.max='4';zr.step='0.01';zr.value='1';zr.setAttribute('aria-label','Zoom');
  zl.append(ic('<circle cx="11" cy="11" r="6"/><path d="M8 11h6M20 20l-4.5-4.5"/>'),zr,ic('<circle cx="11" cy="11" r="6"/><path d="M8 11h6M11 8v6M20 20l-4.5-4.5"/>'));
  const turn=btn('secondary crop-turn','Turn','<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>');
  const look=el('div','crop-look'),pv1=el('canvas','crop-pv big'),pv2=el('canvas','crop-pv small'),lb=el('div','crop-lk');
  lb.append(el('b',null,'How it will look'),el('span',null,'On your profile and at the top of every page'));const pair=el('div','crop-pair');pair.append(pv1,pv2);look.append(lb,pair);
  const acts=el('div','crop-acts'),again=btn('secondary','Choose another'),cancel=btn('quiet','Cancel'),ok=btn('primary crop-ok','Use this photo','<path d="M5 12l4 4 10-10"/>');
  acts.append(cancel,again,ok);const tools=el('div','crop-tools');tools.append(zl,turn);
  box.append(h,tip,stage,tools,look,acts);ov.append(box);
  const dims=()=>rot%2?[img.naturalHeight,img.naturalWidth]:[img.naturalWidth,img.naturalHeight];
  const clamp=()=>{const [w,hh]=dims(),k=base*z,mx=Math.max(0,(w*k-S)/2),my=Math.max(0,(hh*k-S)/2);x=Math.min(mx,Math.max(-mx,x));y=Math.min(my,Math.max(-my,y));};
  const paint=(c,size,round)=>{const g=c.getContext('2d'),q=size/S;g.setTransform(1,0,0,1,0,0);g.clearRect(0,0,c.width,c.height);g.fillStyle='#fff';g.fillRect(0,0,c.width,c.height);
   g.save();if(round){g.beginPath();g.arc(c.width/2,c.height/2,c.width/2,0,Math.PI*2);g.clip();}const r=c.width/size;
   g.translate(c.width/2+x*q*r,c.height/2+y*q*r);g.rotate(rot*Math.PI/2);const k=base*z*q*r;g.scale(k,k);g.drawImage(img,-img.naturalWidth/2,-img.naturalHeight/2);g.restore();};
  const dpr=Math.min(3,window.devicePixelRatio||1);
  const draw=()=>{clamp();paint(cv,S,false);paint(pv1,96,true);paint(pv2,40,true);};
  const size=()=>{S=Math.round(stage.getBoundingClientRect().width)||300;cv.width=S*dpr;cv.height=S*dpr;pv1.width=pv1.height=96*dpr;pv2.width=pv2.height=40*dpr;const [w,hh]=dims();base=S/Math.min(w,hh);draw();};
  const close=()=>{ov.remove();document.removeEventListener('keydown',key);window.removeEventListener('resize',size);document.documentElement.classList.remove('crop-open');};
  const zoomTo=v=>{z=Math.min(4,Math.max(1,v));zr.value=String(z);draw();};
  const key=e=>{if(e.key==='Escape'){e.preventDefault();input.value='';close();}
   else if(document.activeElement===stage){const m={ArrowLeft:[8,0],ArrowRight:[-8,0],ArrowUp:[0,8],ArrowDown:[0,-8]}[e.key];if(m){e.preventDefault();x+=m[0];y+=m[1];draw();}else if(e.key==='+'||e.key==='='){zoomTo(z+.1);}else if(e.key==='-'){zoomTo(z-.1);}}};
  zr.addEventListener('input',()=>zoomTo(+zr.value));turn.onclick=()=>{rot=(rot+1)%4;x=y=0;size();};
  stage.addEventListener('wheel',e=>{e.preventDefault();zoomTo(z*(e.deltaY<0?1.08:1/1.08));},{passive:false});
  const pts=new Map();let pinch=0,pz=1;
  stage.addEventListener('pointerdown',e=>{stage.setPointerCapture(e.pointerId);pts.set(e.pointerId,[e.clientX,e.clientY]);if(pts.size===2){const [a,b]=[...pts.values()];pinch=Math.hypot(a[0]-b[0],a[1]-b[1]);pz=z;}stage.classList.add('grab');});
  stage.addEventListener('pointermove',e=>{if(!pts.has(e.pointerId))return;const [px,py]=pts.get(e.pointerId);pts.set(e.pointerId,[e.clientX,e.clientY]);
   if(pts.size===2){const [a,b]=[...pts.values()];const d=Math.hypot(a[0]-b[0],a[1]-b[1]);if(pinch)zoomTo(pz*d/pinch);}else{x+=e.clientX-px;y+=e.clientY-py;draw();}});
  const up=e=>{pts.delete(e.pointerId);if(pts.size<2)pinch=0;if(!pts.size)stage.classList.remove('grab');};stage.addEventListener('pointerup',up);stage.addEventListener('pointercancel',up);
  cancel.onclick=()=>{input.value='';close();};
  again.onclick=()=>{close();input.value='';input.click();};
  ok.onclick=()=>{const c=document.createElement('canvas');c.width=c.height=OUT;const keepS=S;paint(c,S,false);S=keepS;ok.disabled=true;ok.classList.add('busy');
   const send=b=>{if(!b){ok.disabled=false;alert('This photo could not be prepared. Please try another one.');return;}
    const name='photo.'+(b.type==='image/webp'?'webp':'jpg');let done=false;
    try{const dt=new DataTransfer();dt.items.add(new File([b],name,{type:b.type}));input.files=dt.files;done=input.files.length===1;}catch(e){}
    if(done){const lab=form.querySelector('.me-file');if(lab)lab.classList.add('busy');close();form.submit();return;}
    const fd=new FormData(form);fd.set(input.name,b,name);fetch(form.action||location.href,{method:'POST',body:fd,credentials:'same-origin'}).then(()=>{location.href='?page=profile';}).catch(()=>{ok.disabled=false;alert('The photo could not be sent. Please check the internet and try again.');});};
   c.toBlob(b=>{if(b&&b.type==='image/webp')send(b);else c.toBlob(send,'image/jpeg',.9);},'image/webp',.9);};
  img.onload=()=>{document.body.append(ov);document.documentElement.classList.add('crop-open');size();requestAnimationFrame(()=>ov.classList.add('on'));stage.focus();document.addEventListener('keydown',key);window.addEventListener('resize',size);};
  img.onerror=()=>{input.value='';alert('This photo could not be opened. Please choose another one.');};
  const rd=new FileReader();rd.onload=()=>{img.src=String(rd.result);};rd.onerror=img.onerror;rd.readAsDataURL(file);   // a data: picture (the page's rules allow no blob: pictures)
 }
 ins.forEach(i=>i.addEventListener('change',()=>open(i)));
})();

/* v172: "Why are you leaving?" On an activity that is not finished yet (the form has data-open), the close (X) button,
   the menu, the bottom tabs and the top bar always ask first. Bruno the Helper gives choices; each one gets a kind
   answer, and "It is too hard" / "Something is not working" can open "I need help" for the teacher. A finished activity
   never asks ("Try it again" is a new, open try). Links inside the activity (Next, Try again) are not stopped. */
(function(){
 const main=document.querySelector('body.activity-page .activity-main');if(!main)return;
 if(!main.querySelector('#activity-form[data-open]'))return;let ok=false;
 document.addEventListener('submit',e=>{if(main.contains(e.target))ok=true;},true);
 const el=(t,c,x)=>{const e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;};
 const btn=(c,x)=>{const b=el('button','btn '+c,x);b.type='button';return b;};
 const pic=p=>'assets/images/characters/bruno-'+p+'.webp?v=2';
 const WHY=[
  ['hard','It is too hard','tryagain','That is okay! Hard things get easier when we practise. Your teacher can help you.',true],
  ['tired','I am tired','thumbs','Rest a little, then come back. You can finish it anytime.'],
  ['later','I will finish it later','thumbs','Okay! Your activity will wait for you. Come back soon.'],
  ['broken','Something is not working','tryagain','Sorry about that! Tell your teacher so they can fix it.',true],
  ['oops','I tapped it by mistake','cheer','No problem! Let’s keep going.']];
 function ask(go){
  const help=document.querySelector('[data-help-open]');
  const ov=el('div','leave-ov'),box=el('div','leave-box');box.setAttribute('role','alertdialog');box.setAttribute('aria-modal','true');box.setAttribute('aria-labelledby','leave-h');
  const img=el('img','leave-pal');img.src=pic('hello');img.alt='';img.width=110;img.height=150;
  const who=el('small','leave-who','Bruno the Helper'),h=el('h2',null,'Why are you leaving?');h.id='leave-h';
  const p=el('p',null,'You have not finished this activity yet. Tell me why:');
  const list=el('div','leave-why'),acts=el('div','leave-acts'),stay=btn('primary','Keep going');acts.append(stay);
  const tx=el('div','leave-tx');tx.append(who,h,p,list,acts);box.append(img,tx);ov.append(box);
  const close=()=>{ov.remove();document.removeEventListener('keydown',key);};const key=e=>{if(e.key==='Escape')close();};
  const leave=()=>{ok=true;close();go();};
  WHY.forEach(([id,label,pose,reply,needHelp])=>{const b=btn('leave-pick',label);b.dataset.why=id;list.append(b);
   b.onclick=()=>{img.src=pic(pose);h.textContent=label;p.textContent=reply;list.remove();acts.textContent='';
    if(id==='oops'){const go2=btn('primary','Keep going');go2.onclick=close;acts.append(go2);go2.focus();return;}
    if(needHelp&&help){const hb=btn('primary','Ask my teacher');hb.onclick=()=>{close();help.click();};acts.append(hb);}
    else{const kb=btn('primary','Keep going');kb.onclick=close;acts.append(kb);}
    const bye=btn('secondary','Leave');bye.onclick=leave;acts.append(bye);(acts.firstChild).focus();};});
  stay.onclick=close;ov.addEventListener('click',e=>{if(e.target===ov)close();});
  document.body.append(ov);document.addEventListener('keydown',key);requestAnimationFrame(()=>ov.classList.add('on'));stay.focus();
 }
 document.addEventListener('click',e=>{
  if(ok||e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey)return;
  const a=e.target.closest('a[href]');if(!a||main.contains(a)||a.target==='_blank'||a.hasAttribute('download')||a.hasAttribute('data-no-leave-check'))return;
  const h=a.getAttribute('href');if(!h||h.charAt(0)==='#'||/^(javascript|mailto|tel):/i.test(h))return;
  e.preventDefault();e.stopImmediatePropagation();ask(()=>{location.href=a.href;});
 },true);
})();
