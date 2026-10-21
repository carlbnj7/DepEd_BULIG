/* BULIG Level 2A cards: one card at a time, answered the way the module asks.
   Letter tiles (write), word chips (encircle / box / check words), pictures (encircle / color a picture),
   numbers (count the sounds), microphone (say / tell me), "done on paper".
   Every answer is written into the form ("BULIG card answers / Card n: ..."), so saving, offline saving
   and Activity history work as before. Recordings go in audio_card[n]. */
(function(){
 'use strict';
 const $=(s,r)=>(r||document).querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 /* iPhone, iPad and Safari on a Mac: one microphone job at a time (v173) */
 const APPLE=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1)||(/Safari\//.test(navigator.userAgent)&&!/Chrome|Chromium|CriOS|FxiOS|Edg|OPR|Android/.test(navigator.userAgent));
 /* Build a Word (Level 3): tap the module's letter boxes; each letter flies onto the next blank line.
    A full word is checked: right turns green, wrong shakes and clears. The answer keeps the earlier tries for the teacher.
    The same boxes work on Class Demo slides (data-l3-demo): they are wired when the slide is shown. */
 const still=matchMedia('(prefers-reduced-motion: reduce)').matches,sfx=n=>{try{window.buligSfx&&window.buligSfx.play(n);}catch(e){}};
 const OK='<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
 const STAR='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7-6.2-3.7-6.2 3.7 1.6-7L2 9.2l7.1-.6z"/></svg>';
 function wireBuild(b,keep,hook){b.dataset.wired='1';const word=b.dataset.word||'',tiles=$$('.l3-bt',b),lines=$$('.l3-bl',b),msg=$('.l3-bmsg',b);
  const put=lines.map(()=>null),tries=[];let right=false,busy=false;
  const nextLine=()=>put.indexOf(null);
  const paint=()=>{const n=nextLine();lines.forEach((l,i)=>{l.textContent=put[i]===null?'':tiles[put[i]].textContent;l.classList.toggle('f',put[i]!==null);l.classList.toggle('nx',!right&&i===n);});
   tiles.forEach((t,i)=>{const on=put.includes(i);t.classList.toggle('on',on);t.setAttribute('aria-pressed',on?'true':'false');});};
  const save=w=>keep(w?w+(tries.length?' (tries: '+tries.join(', ')+')':''):(tries.length?'tries: '+tries.join(', '):''));
  const say=(k,t)=>{msg.className='l3-bmsg'+(k?' '+k:'');msg.textContent=t;if(k==='ok')msg.insertAdjacentHTML('afterbegin',OK);};
  const stars=()=>{if(still)return;const r=b.getBoundingClientRect(),l=$('.l3-blines',b).getBoundingClientRect();
   for(let k=0;k<12;k++){const s=document.createElement('span');s.className='l3-star';s.innerHTML=STAR;const a=k/12*6.283;
    s.style.left=(l.left-r.left+l.width/2)+'px';s.style.top=(l.top-r.top+l.height/2)+'px';s.style.setProperty('--dx',Math.round(Math.cos(a)*120)+'px');s.style.setProperty('--dy',Math.round(Math.sin(a)*70)+'px');b.append(s);setTimeout(()=>s.remove(),950);}};
  const check=()=>{const w=put.map(i=>tiles[i].textContent).join('');
   if(w===word){right=true;b.classList.add('ok');say('ok','Great job! '+w);stars();sfx('correct');save(w);paint();if(hook)hook(true,w);return;}
   tries.push(w);save('');b.classList.add('no');sfx('tryagain');busy=true;
   if(hook&&hook(false,w)){right=true;b.classList.add('out');say('no','The word is '+word+'.');}else say('no','Not yet. Look at the picture and try again.');
   setTimeout(()=>{b.classList.remove('no');put.fill(null);busy=false;paint();},1300);};
  const land=(n,i)=>{put[n]=i;paint();if(nextLine()<0)setTimeout(check,250);};
  b.addEventListener('click',e=>{if(right||busy)return;const t=e.target.closest('.l3-bt'),l=e.target.closest('.l3-bl');
   if(t){const i=+t.dataset.i,n=nextLine();if(put.includes(i)||n<0)return;if(msg.textContent)say('','');sfx('pop');
    t.classList.remove('press');void t.offsetWidth;t.classList.add('press');
    if(still||!t.animate){land(n,i);return;}
    const o=b.getBoundingClientRect(),a=t.getBoundingClientRect(),d=lines[n].getBoundingClientRect(),f=document.createElement('span');f.className='l3-fly';f.textContent=t.textContent;b.append(f);
    const x0=a.left-o.left+a.width/2,y0=a.top-o.top+a.height/2,x1=d.left-o.left+d.width/2,y1=d.top-o.top+d.height/2;f.style.left=x0+'px';f.style.top=y0+'px';
    put[n]=i;t.classList.add('on');
    f.animate([{transform:'translate(-50%,-50%)'},{transform:'translate(calc(-50% + '+(x1-x0)/2+'px),calc(-50% + '+((y1-y0)/2-30)+'px)) scale(1.25)'},{transform:'translate(calc(-50% + '+(x1-x0)+'px),calc(-50% + '+(y1-y0)+'px)) scale(1.1)'}],{duration:380,easing:'ease-in-out'}).onfinish=()=>{f.remove();land(n,i);};
    return;}
   if(l){const n=+l.dataset.l;if(put[n]===null)return;put[n]=null;paint();}});
  paint();}
 const stage=$('#l1-demo [data-d-stage]');
 if(stage){const wire=()=>$$('.l3-build[data-l3-demo]:not([data-wired])',stage).forEach(b=>wireBuild(b,()=>{}));new MutationObserver(wire).observe(stage,{childList:true});wire();}
 const deck=$('[data-l2-deck]');if(!deck)return;
 const form=$('#activity-form'),out=$('#response'),cards=$$('.l2-card',deck),ro=deck.hasAttribute('data-readonly');
 const prev=$('[data-l2-prev]'),next=$('[data-l2-next]'),send=$('#submit-answer'),done=$('[data-l2-done]'),fb=$('#answer-feedback');
 let at=0;
 /* ---------- one value per answer part ---------- */
 const val=new Map();
 const key=(c,p)=>c.dataset.n+':'+p;
 const skip=new Set();   // pages skipped with "Something is not working" (v176): no score
 function cardAnswer(c){if(skip.has(c.dataset.n))return '(skipped)';const kinds=(c.dataset.kinds||'').split(' ').filter(Boolean);if(!kinds.length||kinds.every(k=>k==='look'))return '(seen)';
  return kinds.map((k,p)=>k==='look'?'':(val.get(key(c,p))||'')).filter(Boolean).join(' · ');}
 function needs(c){return (c.dataset.kinds||'').split(' ').some(k=>k&&k!=='look');}
 function collect(){if(ro)return;out.value='BULIG card answers\n'+cards.map(c=>'Card '+c.dataset.n+(c.dataset.t?' (no. '+c.dataset.t+')':'')+': '+cardAnswer(c)).join('\n')+triesLine();paintDots();}
 function set(c,p,v){val.set(key(c,p),v);collect();}

 /* ---------- BULIG checks each card right away (v172) ----------
    Lesson activities with an answer key (data-ck): a Check button on each marked card (letter blanks and Build a Word
    check themselves). Right: the card turns green and stays, and Bruno cheers. Wrong: Bruno says "Not yet, try again";
    the pupil may try as many times as they like, and each wrong try takes away a third of the card's points (down to 0).
    Offline, the cards are checked at Done. */
 const CK=!ro&&deck.dataset.ck==='1',tries={},cst={},aid=form?((form.querySelector('[name=activity_id]')||{}).value||''):'';
 function triesLine(){const t=Object.keys(tries).filter(n=>tries[n]>0).sort((a,b)=>a-b).map(n=>n+'='+tries[n]);return t.length?'\nTries: '+t.join(', '):'';}
 const keyed=c=>CK&&c.dataset.keyed==='1';
 const autoOnly=c=>{const k=(c.dataset.kinds||'').split(' ').filter(x=>x&&x!=='look');return k.length>0&&(k.every(x=>x==='build')||(k.every(x=>x==='tiles')&&!!$('[data-gap][data-n]',c)));};
 function ckRow(c){let r=$('.ck-row',c);if(r)return r;r=document.createElement('div');r.className='ck-row';
  const b=document.createElement('button');b.type='button';b.className='l1-btn ck-go';b.textContent='Check';b.hidden=autoOnly(c);b.addEventListener('click',()=>checkCard(c));
  const m=document.createElement('p');m.className='ck-msg';m.setAttribute('role','status');m.setAttribute('aria-live','polite');r.append(b,m);c.append(r);return r;}
 /* Bruno the Helper says it: cheers when right, "try again" when not */
 const BRUNO={ok:'cheer',no:'tryagain',out:'thumbs'};
 function ckSay(c,k,t){const m=$('.ck-msg',ckRow(c));m.className='ck-msg'+(k?' '+k:'');m.textContent='';
  if(k&&BRUNO[k]){const i=document.createElement('img');i.className='ck-pal';i.alt='';i.src='assets/images/characters/bruno-'+BRUNO[k]+'.webp?v=2';
   const tx=document.createElement('span');tx.className='ck-bub';const who=document.createElement('small');who.textContent='Bruno the Helper';const w=document.createElement('b');w.textContent=t;tx.append(who,w);m.append(i,tx);}
  else m.textContent=t;}
 function lock(c,how){cst[c.dataset.n]=how;c.classList.add(how==='ok'?'ck-ok':'ck-out');$$('.l2-ans',c).forEach(x=>x.classList.add('locked'));const b=$('.ck-go',c);if(b)b.hidden=true;paintDots();}
 function ask(n,g){const fd=new FormData();fd.set('action','card_check');fd.set('csrf',(form.querySelector('[name=csrf]')||{}).value||'');fd.set('activity_id',aid);fd.set('card',n);fd.set('answer',g);
  return fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():null).catch(()=>null);}
 async function checkCard(c){const n=c.dataset.n;if(!keyed(c)||cst[n])return true;const g=cardAnswer(c);if(!g){ckSay(c,'no','Answer first, then tap Check.');return false;}
  const b=$('.ck-go',c);if(b)b.disabled=true;const j=await ask(n,g);if(b)b.disabled=false;
  if(!j){cst[n]='later';ckSay(c,'','Saved. BULIG will check it when you finish.');paintDots();return true;}
  if(!j.keyed||j.ok){lock(c,'ok');ckSay(c,'ok',['Great job! You got it!','Yes! That is right!','Correct! Well done!','You did it! Great work!'][Math.floor(Math.random()*4)]);sfx('correct');return true;}
  tries[n]=j.tries;collect();sfx('tryagain');
  ckSay(c,'no',['Not yet. Try again! You can do it!','Oops! Look again and try once more.','Almost! Try again, I know you can.'][Math.min(2,(j.tries||1)-1)]);c.classList.remove('ck-shake');void c.offsetWidth;c.classList.add('ck-shake');
  if(c._ckClear)c._ckClear();return false;}
 /* Build a Word: right or one more try (the server counts it too) */
 function buildTry(c,ok,w){const n=c.dataset.n;if(ok){lock(c,'ok');return false;}tries[n]=Math.min(3,(tries[n]||0)+1);collect();ask(n,w);
  ckSay(c,'no','Not yet. Try again! You can do it!');return false;}
 if(CK)cards.forEach(c=>{if(keyed(c))ckRow(c);});
 /* letter tiles */
 $$('[data-kind="tiles"]',deck).forEach(t=>{const c=t.closest('.l2-card'),p=t.dataset.part,box=$('[data-gap]',c)||$('[data-out]',t),tok=[];
  /* "n _ t": one line per missing letter (data-n); the letters go on the lines in order (a blend tile like "fr" fills two),
     extra letters get a line of their own */
  const show=q=>{if(box.dataset.n){const ch=[...tok.join('')],want=Math.max(+box.dataset.n,ch.length);while(box.children.length<want)box.append(document.createElement('i'));while(box.children.length>want)box.lastElementChild.remove();[...box.children].forEach((x,i)=>{x.textContent=ch[i]||'';});}else box.textContent=tok.join('');
   box.classList.toggle('f',tok.length>0);set(c,p,tok.join('').trim());};
  const cap=+(box.dataset.n||0),len=()=>[...tok.join('')].length;/* v172: a blank takes only its missing letters */
  c._ckClear=()=>{tok.length=0;show(true);};
  t.addEventListener('click',e=>{if(cst[c.dataset.n])return;const k=e.target.closest('[data-key]'),er=e.target.closest('[data-erase]');
   if(k){if(cap&&len()+[...k.dataset.key].length>cap){box.classList.remove('full');void box.offsetWidth;box.classList.add('full');return;}if(tok.length<40)tok.push(k.dataset.key);show();
    if(cap&&len()===cap&&keyed(c)&&autoOnly(c))setTimeout(()=>checkCard(c),300);}
   else if(er){tok.pop();show();}});});
 $$('[data-kind="build"]',deck).forEach(b=>{const c=b.closest('.l2-card'),p=b.dataset.part;wireBuild(b,v=>set(c,p,v),keyed(c)?(ok,w)=>buildTry(c,ok,w):null);});
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
 /* reading for speed: Start / Done, then words per minute (the module's speed rate) */
 const mmss=x=>Math.floor(x/60)+':'+String(Math.floor(x%60)).padStart(2,'0');
 $$('[data-kind="speed"]',deck).forEach(g=>{const c=g.closest('.l2-card'),w=+g.dataset.words||0,go=$('[data-sp-start]',g),dn=$('[data-sp-done]',g),clk=$('[data-sp-clock]',g),res=$('[data-sp-res]',g);let t0=0,tk=null;
  go.addEventListener('click',()=>{t0=Date.now();clearInterval(tk);tk=setInterval(()=>{clk.textContent=mmss((Date.now()-t0)/1000);},250);go.hidden=true;dn.hidden=false;res.textContent='';set(c,g.dataset.part,'');});
  dn.addEventListener('click',()=>{clearInterval(tk);const sec=Math.max(1,(Date.now()-t0)/1000),wpm=Math.round(w*60/sec);clk.textContent=mmss(sec);dn.hidden=true;go.hidden=false;go.lastChild.textContent='Again';res.textContent='Time '+mmss(sec)+' · '+wpm+' words per minute';set(c,g.dataset.part,'reading time '+mmss(sec)+' · '+wpm+' words per minute');});});
 /* letter puzzle (app.js marks the letters): the marked letters, row by row */
 $$('[data-kind="lgrid"]',deck).forEach(g=>{const c=g.closest('.l2-card');g.addEventListener('click',()=>setTimeout(()=>{const v=$$('tr',g).map((tr,i)=>{const m=$$('.grid-cell.found',tr).map(b=>b.textContent).join('');return m?'Row '+(i+1)+': '+m:'';}).filter(Boolean).join('; ');set(c,g.dataset.part,v);},0));});
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
  let mr=null,st=null,ch=[],t0=0,tick=null,secs=0,url='',pl=null;const rd=!!r.dataset.max;
  /* v172: BULIG listens while the pupil speaks (the browser's speech recognition) and keeps what it heard, so the
     words can be checked (pronunciation). Without it (some phones, no internet) only the recording is kept. */
  const SRC=window.SpeechRecognition||window.webkitSpeechRecognition;let sr=null,srOn=false,said=[],part='',srErr='';
  /* v173: iPhones, iPads and Safari cannot record and listen at the same time, so there BULIG only listens and the
     words it hears are the answer (no voice recording). What it hears shows under the microphone as the pupil speaks. */
  let solo=!!SRC&&APPLE;const heardText=()=>(said.join(' ')+' '+part).replace(/\s+/g,' ').trim();
  function srStart(){if(!SRC)return;said=[];part='';srErr='';srOn=true;const make=()=>{sr=new SRC();sr.lang='en-US';sr.continuous=true;sr.interimResults=true;
    sr.onresult=e=>{part='';for(let i=e.resultIndex;i<e.results.length;i++){if(e.results[i].isFinal)said.push(e.results[i][0].transcript);else part+=' '+e.results[i][0].transcript;}const t=heardText();if(t&&r.dataset.state==='rec')sub.textContent='BULIG is listening: \u201c'+t+'\u2026\u201d';};
    sr.onerror=e=>{srErr=e.error||'';if(['not-allowed','service-not-allowed','audio-capture','network'].includes(e.error))srOn=false;};
    sr.onend=()=>{if(part){said.push(part);part='';}if(srOn){try{make();sr.start();}catch(e){srOn=false;}}};};
   make();try{sr.start();}catch(e){srOn=false;}}
  function srStop(){srOn=false;try{sr&&sr.stop();}catch(e){}}const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  const ui=s=>{r.dataset.state=s;go.classList.toggle('on',s==='rec');go.hidden=s==='done';play.hidden=s!=='done';
   label.textContent=s==='rec'?'0:00':s==='done'?(rd?'Your reading':'Your answer'):(rd?'Tap and read the story aloud':'Tap and say it');sub.textContent=s==='rec'?'Speak now. Tap again to stop.':'';};
  if(!canRec&&!solo){go.hidden=true;label.textContent='Say it out loud to your teacher.';}
  async function start(){if(r.dataset.state==='starting'||r.dataset.state==='rec')return;r.dataset.state='starting';window.speechSynthesis&&speechSynthesis.cancel();if(url){URL.revokeObjectURL(url);url='';}inp.value='';
   if(solo){srStart();if(srOn){t0=Date.now();ui('rec');tick=setInterval(()=>{secs=(Date.now()-t0)/1000;label.textContent=fmt(secs);if(secs>=(+r.dataset.max||45))stop();
     if(!srOn&&srErr){clearInterval(tick);if(srErr==='not-allowed'){ui('idle');label.textContent='Tap the microphone to try again.';sub.textContent=micMsg({name:'NotAllowedError'});if(heard)heard.hidden=false;}else if(!heardText()){solo=false;r.dataset.state='idle';start();}else stop();}},250);return;}solo=false;}
   try{st=await getMic();}catch(e){r.dataset.state='idle';label.textContent='Tap the microphone to try again.';sub.textContent=micMsg(e);if(heard)heard.hidden=false;return;}
   const type=['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/ogg;codecs=opus'].find(t=>MediaRecorder.isTypeSupported&&MediaRecorder.isTypeSupported(t));
   try{mr=new MediaRecorder(st,type?{mimeType:type,audioBitsPerSecond:32000}:undefined);}catch(e){mr=new MediaRecorder(st);}
   ch=[];mr.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data);};mr.onstop=fin;mr.start(500);t0=Date.now();ui('rec');srStart();tick=setInterval(()=>{secs=(Date.now()-t0)/1000;label.textContent=fmt(secs);if(secs>=(+r.dataset.max||45))stop();},250);}
  function stop(){clearInterval(tick);secs=(Date.now()-t0)/1000;srStop();if(solo){r.dataset.state='starting';setTimeout(finSolo,900);return;}if(mr&&mr.state!=='inactive')mr.stop();if(st)st.getTracks().forEach(t=>t.stop());}
  /* listening only (iPhone): the words are the answer */
  function finSolo(){const w=heardText();if(!w||secs<.6){ui('idle');sub.textContent='I could not hear anything. Try again.';return;}
   inp.value='';ui('done');play.hidden=true;label.textContent=rd?'BULIG heard your reading':'BULIG heard your answer';sub.textContent='BULIG heard: \u201c'+w+'\u201d';
   set(c,p,(rd?'(read aloud · time '+fmt(secs)+(r.dataset.words?' · '+Math.round(+r.dataset.words*60/Math.max(1,secs))+' words per minute':'')+')':'(spoken answer)')+' said: '+w);}
  function fin(){const blob=new Blob(ch,{type:((mr&&mr.mimeType)||'audio/webm').split(';')[0].replace('video/','audio/')});
   if(blob.size<800||secs<.6){ui('idle');sub.textContent='I could not hear anything. Try again.';return;}
   url=URL.createObjectURL(blob);len.textContent=fmt(secs);const fr=new FileReader();fr.onload=()=>{inp.value=String(fr.result);ui('done');const base=rd?'(voice recording · time '+fmt(secs)+(r.dataset.words?' · '+Math.round(+r.dataset.words*60/Math.max(1,secs))+' words per minute':'')+')':'(voice recording)';set(c,p,base);
    /* the last words BULIG heard arrive a moment after the recording stops */
    setTimeout(()=>{const w=heardText();if(w&&r.dataset.state==='done'){set(c,p,base+' said: '+w);sub.textContent='BULIG heard: \u201c'+w+'\u201d';}},800);};fr.readAsDataURL(blob);}
  go.addEventListener('click',()=>{if(r.dataset.state==='rec'){if(Date.now()-t0<700)return;stop();}else if(r.dataset.state!=='starting')start();});
  again.addEventListener('click',()=>{if(pl){pl.pause();pl=null;}set(c,p,'');start();});
  lis.addEventListener('click',()=>{if(!url)return;if(pl&&!pl.paused){pl.pause();return;}if(!pl){pl=new Audio(url);pl.addEventListener('timeupdate',()=>bar.style.setProperty('--p',String(Math.min(1,pl.currentTime/(secs||1)))));pl.addEventListener('ended',()=>lis.classList.remove('on'));}pl.currentTime=0;pl.play().then(()=>lis.classList.add('on')).catch(()=>{});});
  heard.addEventListener('click',()=>{const on=heard.getAttribute('aria-pressed')!=='true';heard.setAttribute('aria-pressed',on?'true':'false');if(on){inp.value='';if(url){URL.revokeObjectURL(url);url='';}ui('idle');}set(c,p,on?'(done)':'');});});
 /* ---------- moving between cards ---------- */
 const dots=document.createElement('div');dots.className='l2-cdots';
 if(cards.length>1){cards.forEach((c,i)=>{const b=document.createElement('button');b.type='button';b.textContent=String(i+1);b.setAttribute('aria-label','Card '+(i+1));b.addEventListener('click',()=>show(i));dots.append(b);});deck.before(dots);}
 function paintDots(){$$('button',dots).forEach((b,i)=>{b.classList.toggle('cur',i===at);b.classList.toggle('ok',!ro&&needs(cards[i])&&cardAnswer(cards[i])!=='');b.classList.toggle('ck-out',cst[cards[i].dataset.n]==='out');});}
 function show(i){at=Math.max(0,Math.min(cards.length-1,i));cards.forEach((c,k)=>c.hidden=k!==at);window.speechSynthesis&&speechSynthesis.cancel();
  const last=at===cards.length-1,saved=fb&&fb.classList.contains('success');prev.hidden=at===0||saved;
  if(next)next.hidden=last||saved;if(send&&!saved)send.hidden=!last;if(done&&ro)done.hidden=!last;paintDots();
  const top=$('.l2-dir');if(top&&i!==undefined&&top.getBoundingClientRect().top<0)top.scrollIntoView({block:'start',behavior:'smooth'});}
 prev.addEventListener('click',()=>show(at-1));
 /* "I need help › Something is not working": report it and skip only this page; the other pages keep their answers (v176). */
 const hf=$('.hp-sheet form');
 if(hf&&!ro)hf.addEventListener('submit',async e=>{if(!hf.querySelector('input[name="reason"][value="bug"]:checked'))return;e.preventDefault();
  const c=cards[at],fd=new FormData(hf);fd.set('card',c.dataset.n);fd.set('ajax','1');
  try{await fetch(location.href,{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json'}});}catch(_){}
  skip.add(c.dataset.n);cst[c.dataset.n]='skip';c.classList.add('l2-skipped');collect();const d=hf.closest('dialog');if(d&&d.close)d.close();
  const t=document.createElement('div');t.className='hp-toast';t.setAttribute('role','status');t.textContent=at<cards.length-1?'Thank you! You told the BULIG team. This page is skipped. Here is the next one.':'Thank you! You told the BULIG team. This page is skipped. Tap Done to finish.';
  document.body.appendChild(t);setTimeout(()=>t.classList.add('hp-gone'),6000);if(at<cards.length-1)show(at+1);else show(at);},true);
 if(next)next.addEventListener('click',async()=>{const c=cards[at];if(keyed(c)&&!autoOnly(c)&&!cst[c.dataset.n]&&cardAnswer(c)!==''&&!await checkCard(c))return;show(at+1);});
 /* Submit: say which cards are still empty first. */
 let sure=false;
 form.addEventListener('submit',e=>{if(ro||sure)return;collect();
  const unchecked=cards.find(c=>keyed(c)&&!autoOnly(c)&&!cst[c.dataset.n]&&cardAnswer(c)!=='');
  if(unchecked){e.preventDefault();e.stopImmediatePropagation();show(cards.indexOf(unchecked));checkCard(unchecked).then(ok=>{if(ok)form.requestSubmit();});return;}const empty=cards.filter(c=>needs(c)&&cardAnswer(c)==='');if(!empty.length)return;
  e.preventDefault();e.stopImmediatePropagation();const n=empty[0].dataset.n;fb.className='answer-feedback l1-fb tryagain';fb.replaceChildren();
  const t=document.createElement('strong');t.textContent=empty.length===1?'Card '+n+' has no answer yet.':empty.length+' cards have no answer yet.';
  const row=document.createElement('div');row.className='l2-row';const g=document.createElement('button'),a=document.createElement('button');g.type=a.type='button';g.className='l1-btn2';a.className='l1-btn2';g.textContent='Go to card '+n;a.textContent='Submit anyway';
  g.addEventListener('click',()=>{fb.replaceChildren();fb.className='answer-feedback l1-fb';show(+n-1);});a.addEventListener('click',()=>{fb.replaceChildren();fb.className='answer-feedback l1-fb';sure=true;form.requestSubmit();sure=false;});row.append(g,a);fb.append(t,row);},true);
 if(fb)new MutationObserver(()=>{if(fb.classList.contains('success')){prev.hidden=true;if(next)next.hidden=true;dots.remove();$$('.l2-ans',deck).forEach(x=>x.classList.add('locked'));}}).observe(fb,{attributes:true,attributeFilter:['class']});
 collect();show(0);
})();
