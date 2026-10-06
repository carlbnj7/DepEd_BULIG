/* BULIG Level 2A cards: one card at a time, answered the way the module asks.
   Letter tiles (write), word chips (encircle / box / check words), pictures (encircle / color a picture),
   numbers (count the sounds), microphone (say / tell me), "done on paper".
   Every answer is written into the form ("BULIG card answers / Card n: ..."), so saving, offline saving
   and Activity history work as before. Recordings go in audio_card[n]. */
(function(){
 'use strict';
 const $=(s,r)=>(r||document).querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 const deck=$('[data-l2-deck]');if(!deck)return;
 const form=$('#activity-form'),out=$('#response'),cards=$$('.l2-card',deck),ro=deck.hasAttribute('data-readonly');
 const prev=$('[data-l2-prev]'),next=$('[data-l2-next]'),send=$('#submit-answer'),done=$('[data-l2-done]'),fb=$('#answer-feedback');
 let at=0;
 /* ---------- one value per answer part ---------- */
 const val=new Map();
 const key=(c,p)=>c.dataset.n+':'+p;
 function cardAnswer(c){const kinds=(c.dataset.kinds||'').split(' ').filter(Boolean);if(!kinds.length||kinds.every(k=>k==='look'))return '(seen)';
  return kinds.map((k,p)=>k==='look'?'':(val.get(key(c,p))||'')).filter(Boolean).join(' · ');}
 function needs(c){return (c.dataset.kinds||'').split(' ').some(k=>k&&k!=='look');}
 function collect(){if(ro)return;out.value='BULIG card answers\n'+cards.map(c=>'Card '+c.dataset.n+(c.dataset.t?' (no. '+c.dataset.t+')':'')+': '+cardAnswer(c)).join('\n');paintDots();}
 function set(c,p,v){val.set(key(c,p),v);collect();}
 /* letter tiles */
 $$('[data-kind="tiles"]',deck).forEach(t=>{const c=t.closest('.l2-card'),p=t.dataset.part,box=$('[data-gap]',c)||$('[data-out]',t),tok=[];
  const show=()=>{box.textContent=tok.join('');box.classList.toggle('f',tok.length>0);set(c,p,tok.join('').trim());};
  t.addEventListener('click',e=>{const k=e.target.closest('[data-key]'),er=e.target.closest('[data-erase]');if(k){if(tok.length<40)tok.push(k.dataset.key);show();}else if(er){tok.pop();show();}});});
 /* chips and numbers */
 $$('[data-kind="chip"],[data-kind="chips"],[data-kind="num"]',deck).forEach(g=>{const c=g.closest('.l2-card'),p=g.dataset.part,multi=g.dataset.kind==='chips';
  g.addEventListener('click',e=>{const b=e.target.closest('button[data-val]');if(!b)return;const on=b.getAttribute('aria-pressed')!=='true';
   if(!multi)$$('button[data-val]',g).forEach(x=>x.setAttribute('aria-pressed','false'));b.setAttribute('aria-pressed',on?'true':'false');
   const v=$$('button[aria-pressed="true"]',g).map(x=>x.dataset.val).join(', ');set(c,p,v&&g.dataset.lab?g.dataset.lab+' = '+v:v);});});
 /* pictures: one, several, or pairs */
 $$('[data-kind="pick"],[data-kind="picks"],[data-kind="pairs"]',deck).forEach(h=>{const c=h.closest('.l2-card'),p=h.dataset.part,kind=h.dataset.kind,pics=$$('.l2-pic[data-pick]',c);let open=null,pairNo=0;
  const name=b=>'Picture '+b.dataset.pick+(b.querySelector('.l2-lab')?' ('+b.querySelector('.l2-lab').textContent+')':'');
  const save=()=>{if(kind==='pairs'){const g={};pics.forEach(b=>{if(b.dataset.pair)(g[b.dataset.pair]=g[b.dataset.pair]||[]).push(b.dataset.pick);});set(c,p,Object.values(g).filter(x=>x.length===2).map(x=>x.join('–')).join(', '));}
   else set(c,p,pics.filter(b=>b.getAttribute('aria-pressed')==='true').map(name).join(', '));};
  pics.forEach(b=>b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();
   if(kind==='pairs'){if(b.dataset.pair){const n=b.dataset.pair;pics.filter(x=>x.dataset.pair===n).forEach(x=>{delete x.dataset.pair;x.removeAttribute('data-tag');x.setAttribute('aria-pressed','false');});open=null;save();return;}
    if(open&&open!==b){pairNo++;[open,b].forEach(x=>{x.dataset.pair=pairNo;x.setAttribute('data-tag',pairNo);x.setAttribute('aria-pressed','true');x.classList.remove('wait');});open=null;}else{open=b;b.classList.add('wait');}save();return;}
   const on=b.getAttribute('aria-pressed')!=='true';if(kind==='pick')pics.forEach(x=>x.setAttribute('aria-pressed','false'));b.setAttribute('aria-pressed',on?'true':'false');save();},true));});
 /* split a word: onset | rime (one cut), or syllables (several slashes) */
 $$('[data-kind="split"]',deck).forEach(g=>{const c=g.closest('.l2-card'),p=g.dataset.part,one=g.dataset.mode==='onset',out=$('[data-sout]',g),chs=$$('.l2-ch',g),cuts=$$('.l2-cut',g);
  const show=()=>{const on=cuts.map(b=>b.getAttribute('aria-pressed')==='true');let txt='',k=0,first=on.indexOf(true);chs.forEach((ch,i)=>{txt+=ch.textContent;ch.classList.toggle('ons',one&&first>=0&&i<=first);if(i<on.length&&on[i])txt+=one?' | ':'/';});
   out.textContent=on.some(Boolean)?txt:'';set(c,p,on.some(Boolean)?txt:'');};
  cuts.forEach(b=>b.addEventListener('click',()=>{const on=b.getAttribute('aria-pressed')!=='true';if(one)cuts.forEach(x=>x.setAttribute('aria-pressed','false'));b.setAttribute('aria-pressed',on?'true':'false');show();}));});
 /* tap each word as you read it (hearts, stars, dots, moons) */
 $$('[data-kind="words"]',deck).forEach(g=>{const c=g.closest('.l2-card'),p=g.dataset.part,ws=$$('.l2-wd',g);
  ws.forEach(b=>b.addEventListener('click',()=>{b.setAttribute('aria-pressed',b.getAttribute('aria-pressed')==='true'?'false':'true');const n=ws.filter(x=>x.getAttribute('aria-pressed')==='true').length;set(c,p,n?'read '+n+' of '+ws.length+' words':'');}));});
 /* done on paper */
 /* typed answer (Level 6: "write the name...", "copy the word...") */
 $$('[data-kind="write"]',deck).forEach(g=>{const c=g.closest('.l2-card'),t=$('textarea',g);t.addEventListener('input',()=>set(c,g.dataset.part,t.value.replace(/\s+/g,' ').trim()));});
 /* matching board (app.js draws the lines and writes "1-c, 2-a" in the box) */
 $$('[data-kind="match"]',deck).forEach(g=>{const c=g.closest('.l2-card'),box=$('.native-answer',g);box.addEventListener('input',()=>set(c,g.dataset.part,box.value));});
 $$('[data-kind="paper"]',deck).forEach(b=>{const c=b.closest('.l2-card');b.addEventListener('click',()=>{const on=b.getAttribute('aria-pressed')!=='true';b.setAttribute('aria-pressed',on?'true':'false');set(c,b.dataset.part,on?'done with my teacher or on paper':'');});});
 /* microphone (one recording per card) */
/* Microphone: ask with clean-sound settings, then plain audio (some iPhones refuse the first). */
 async function getMic(){const md=navigator.mediaDevices;try{return await md.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});}
  catch(e){if(e&&(e.name==='NotAllowedError'||e.name==='SecurityError'))throw e;await new Promise(r=>setTimeout(r,350));return await md.getUserMedia({audio:true});}}
 function micMsg(e){const n=e&&e.name||'';
  if(n==='NotAllowedError'||n==='SecurityError')return 'The microphone is blocked. Allow it in the browser settings (iPhone: tap aA, then Website Settings, then Microphone: Allow), then tap the microphone again.';
  if(n==='NotReadableError'||n==='AbortError'||n==='TrackStartError')return 'The microphone is busy. Close other apps using it (calls, video, voice notes), then tap the microphone again.';
  if(n==='NotFoundError'||n==='DevicesNotFoundError')return 'No microphone was found. Tap the microphone to try again.';
  return 'The microphone could not start. Tap it to try again.';}
 const canRec=!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia&&window.MediaRecorder);
 $$('[data-l2-rec]',deck).forEach(r=>{const c=r.closest('.l2-card'),p=r.dataset.part,go=$('[data-rec-go]',r),label=$('[data-rec-label]',r),sub=$('[data-rec-sub]',r),play=$('[data-rec-play]',r),lis=$('[data-rec-listen]',r),bar=$('.l1-bar i',r),len=$('[data-rec-len]',r),again=$('[data-rec-again]',r),heard=$('[data-rec-heard]',r),inp=$('[data-audio]',r);
  let mr=null,st=null,ch=[],t0=0,tick=null,secs=0,url='',pl=null;const rd=!!r.dataset.max;const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  const ui=s=>{r.dataset.state=s;go.classList.toggle('on',s==='rec');go.hidden=s==='done';play.hidden=s!=='done';
   label.textContent=s==='rec'?'0:00':s==='done'?(rd?'Your reading':'Your answer'):(rd?'Tap and read the story aloud':'Tap and say it');sub.textContent=s==='rec'?'Speak now. Tap again to stop.':'';};
  if(!canRec){go.hidden=true;label.textContent='Say it out loud to your teacher.';}
  async function start(){if(r.dataset.state==='starting'||r.dataset.state==='rec')return;r.dataset.state='starting';window.speechSynthesis&&speechSynthesis.cancel();if(url){URL.revokeObjectURL(url);url='';}inp.value='';
   try{st=await getMic();}catch(e){r.dataset.state='idle';label.textContent='Tap the microphone to try again.';sub.textContent=micMsg(e);if(heard)heard.hidden=false;return;}
   const type=['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/ogg;codecs=opus'].find(t=>MediaRecorder.isTypeSupported&&MediaRecorder.isTypeSupported(t));
   try{mr=new MediaRecorder(st,type?{mimeType:type,audioBitsPerSecond:32000}:undefined);}catch(e){mr=new MediaRecorder(st);}
   ch=[];mr.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data);};mr.onstop=fin;mr.start(500);t0=Date.now();ui('rec');tick=setInterval(()=>{secs=(Date.now()-t0)/1000;label.textContent=fmt(secs);if(secs>=(+r.dataset.max||45))stop();},250);}
  function stop(){clearInterval(tick);secs=(Date.now()-t0)/1000;if(mr&&mr.state!=='inactive')mr.stop();if(st)st.getTracks().forEach(t=>t.stop());}
  function fin(){const blob=new Blob(ch,{type:((mr&&mr.mimeType)||'audio/webm').split(';')[0].replace('video/','audio/')});
   if(blob.size<800||secs<.6){ui('idle');sub.textContent='I could not hear anything. Try again.';return;}
   url=URL.createObjectURL(blob);len.textContent=fmt(secs);const fr=new FileReader();fr.onload=()=>{inp.value=String(fr.result);ui('done');set(c,p,rd?'(voice recording · time '+fmt(secs)+')':'(voice recording)');};fr.readAsDataURL(blob);}
  go.addEventListener('click',()=>{if(r.dataset.state==='rec'){if(Date.now()-t0<700)return;stop();}else if(r.dataset.state!=='starting')start();});
  again.addEventListener('click',()=>{if(pl){pl.pause();pl=null;}set(c,p,'');start();});
  lis.addEventListener('click',()=>{if(!url)return;if(pl&&!pl.paused){pl.pause();return;}if(!pl){pl=new Audio(url);pl.addEventListener('timeupdate',()=>bar.style.setProperty('--p',String(Math.min(1,pl.currentTime/(secs||1)))));pl.addEventListener('ended',()=>lis.classList.remove('on'));}pl.currentTime=0;pl.play().then(()=>lis.classList.add('on')).catch(()=>{});});
  heard.addEventListener('click',()=>{const on=heard.getAttribute('aria-pressed')!=='true';heard.setAttribute('aria-pressed',on?'true':'false');if(on){inp.value='';if(url){URL.revokeObjectURL(url);url='';}ui('idle');}set(c,p,on?'(done)':'');});});
 /* ---------- moving between cards ---------- */
 const dots=document.createElement('div');dots.className='l2-cdots';
 if(cards.length>1){cards.forEach((c,i)=>{const b=document.createElement('button');b.type='button';b.textContent=String(i+1);b.setAttribute('aria-label','Card '+(i+1));b.addEventListener('click',()=>show(i));dots.append(b);});deck.before(dots);}
 function paintDots(){$$('button',dots).forEach((b,i)=>{b.classList.toggle('cur',i===at);b.classList.toggle('ok',!ro&&needs(cards[i])&&cardAnswer(cards[i])!=='');});}
 function show(i){at=Math.max(0,Math.min(cards.length-1,i));cards.forEach((c,k)=>c.hidden=k!==at);window.speechSynthesis&&speechSynthesis.cancel();
  const last=at===cards.length-1,saved=fb&&fb.classList.contains('success');prev.hidden=at===0||saved;
  if(next)next.hidden=last||saved;if(send&&!saved)send.hidden=!last;if(done&&ro)done.hidden=!last;paintDots();
  const top=$('.l2-dir');if(top&&i!==undefined&&top.getBoundingClientRect().top<0)top.scrollIntoView({block:'start',behavior:'smooth'});}
 prev.addEventListener('click',()=>show(at-1));if(next)next.addEventListener('click',()=>show(at+1));
 /* Submit: say which cards are still empty first. */
 let sure=false;
 form.addEventListener('submit',e=>{if(ro||sure)return;collect();const empty=cards.filter(c=>needs(c)&&cardAnswer(c)==='');if(!empty.length)return;
  e.preventDefault();e.stopImmediatePropagation();const n=empty[0].dataset.n;fb.className='answer-feedback l1-fb tryagain';fb.replaceChildren();
  const t=document.createElement('strong');t.textContent=empty.length===1?'Card '+n+' has no answer yet.':empty.length+' cards have no answer yet.';
  const row=document.createElement('div');row.className='l2-row';const g=document.createElement('button'),a=document.createElement('button');g.type=a.type='button';g.className='l1-btn2';a.className='l1-btn2';g.textContent='Go to card '+n;a.textContent='Submit anyway';
  g.addEventListener('click',()=>{fb.replaceChildren();fb.className='answer-feedback l1-fb';show(+n-1);});a.addEventListener('click',()=>{fb.replaceChildren();fb.className='answer-feedback l1-fb';sure=true;form.requestSubmit();sure=false;});row.append(g,a);fb.append(t,row);},true);
 if(fb)new MutationObserver(()=>{if(fb.classList.contains('success')){prev.hidden=true;if(next)next.hidden=true;dots.remove();$$('.l2-ans',deck).forEach(x=>x.classList.add('locked'));}}).observe(fb,{attributes:true,attributeFilter:['class']});
 collect();show(0);
})();
