/* BULIG reading check: reading aloud.
   - Word parts (blending): say the word on the card.
   - Read the words: one word at a time; BULIG listens, keeps what it heard, and moves on.
     A part stops after 5 wrong words in a row (Level 2B module, Annex A).
   - Read a story: the whole passage, then Send.
   BULIG listens with the browser's speech recognition and records at the same time, so the teacher can listen later.
   Without speech recognition (or without internet) it only records, and the teacher listens. */
(function(){
 'use strict';
 const $=(s,r)=>(r||document).querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 if(!document.body.classList.contains('rk-page'))return;
 const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
 const canRec=!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia&&window.MediaRecorder);
 /* iPhone, iPad and Safari on a Mac: one microphone job at a time (v173) */
 const APPLE=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1)||(/Safari\//.test(navigator.userAgent)&&!/Chrome|Chromium|CriOS|FxiOS|Edg|OPR|Android/.test(navigator.userAgent));
 let together=!APPLE;/* false once a phone shows it cannot listen and record at the same time (iPhones and Safari: from the start, v173) */
 const norm=t=>String(t||'').toLowerCase().replace(/[’‘]/g,"'").replace(/[^a-z0-9' ]+/g,' ').replace(/\s+/g,' ').trim();
 const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
 const hush=()=>{try{window.speechSynthesis&&speechSynthesis.cancel();}catch(e){}};

 async function getMic(){const md=navigator.mediaDevices;try{return await md.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});}catch(e){if(e&&(e.name==='NotAllowedError'||e.name==='SecurityError'))throw e;return await md.getUserMedia({audio:true});}}
 /* A recording: start() then stop() gives a data URL (or '' when nothing was kept). */
 function recorder(){let st=null,mr=null,ch=[],t0=0;
  return {async start(){if(!canRec)return false;try{st=await getMic();}catch(e){return false;}
    const type=['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/ogg;codecs=opus'].find(t=>MediaRecorder.isTypeSupported&&MediaRecorder.isTypeSupported(t));
    try{mr=new MediaRecorder(st,type?{mimeType:type,audioBitsPerSecond:32000}:undefined);}catch(e){try{mr=new MediaRecorder(st);}catch(x){st.getTracks().forEach(t=>t.stop());return false;}}
    ch=[];mr.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data);};mr.start(500);t0=Date.now();return true;},
   secs(){return t0?(Date.now()-t0)/1000:0;},
   stop(){return new Promise(res=>{if(!mr||mr.state==='inactive'){if(st)st.getTracks().forEach(t=>t.stop());res('');return;}
    mr.onstop=()=>{if(st)st.getTracks().forEach(t=>t.stop());const blob=new Blob(ch,{type:((mr.mimeType)||'audio/webm').split(';')[0]});if(blob.size<800){res('');return;}const fr=new FileReader();fr.onload=()=>res(String(fr.result));fr.onerror=()=>res('');fr.readAsDataURL(blob);};mr.stop();});}};}
 /* Speech recognition. once: one word or phrase (up to 5 guesses). long: keeps listening until stop(). */
 function listener(long,onText){if(!SR)return null;let r=null,on=false,final=[],alts=[],err='',got=false;
  const make=()=>{r=new SR();r.lang='en-US';r.interimResults=!!long;r.maxAlternatives=long?1:5;r.continuous=!!long;
   r.onresult=e=>{got=true;if(long){let interim='';for(let i=e.resultIndex;i<e.results.length;i++){const t=e.results[i][0].transcript;if(e.results[i].isFinal)final.push(t);else interim+=t;}onText&&onText(final.join(' ')+' '+interim);}
    else{const res=e.results[0];for(let i=0;i<res.length;i++)alts.push(res[i].transcript);}};
   r.onerror=e=>{err=e.error||'error';};
   r.onend=()=>{if(long&&on&&!['not-allowed','service-not-allowed','audio-capture','network'].includes(err)){err='';try{make();r.start();}catch(e){}}else{on=false;done&&done();}};};
  let done=null;
  return {start(){on=true;make();try{r.start();return true;}catch(e){on=false;return false;}},
   stop(){return new Promise(res=>{done=()=>res({text:long?final.join(' '):'',alts,err,got});if(!on){res({text:final.join(' '),alts,err,got});return;}on=false;try{r.stop();}catch(e){res({text:final.join(' '),alts,err,got});}});},
   wait(){return new Promise(res=>{done=()=>res({text:final.join(' '),alts,err,got});if(!on)res({text:final.join(' '),alts,err,got});});},
   get busy(){return on;}};}

 /* ---------- Word parts: say the word on the card ---------- */
 $$('[data-rc-say][data-n]').forEach(box=>{const card=box.closest('.l2-card'),go=$('[data-rc-go]',box),label=$('[data-rc-label]',box),sub=$('[data-rc-sub]',box),heard=$('[data-rc-heard]',box),audio=$('[data-rc-audio]',box),ta=$('textarea',card);
  let busy=false,L=null,R=null;
  const put=v=>{ta.value=v;ta.dispatchEvent(new Event('input',{bubbles:true}));};
  async function finish(){busy=false;go.classList.remove('on');const res=L?await L.stop():{alts:[],err:'none'};const data=R?await R.stop():'';
   if(res.err==='audio-capture'&&R)together=false;
   heard.value=res.alts.join('|');audio.value=data;
   if(res.alts.length){put('said: '+res.alts[0]);label.textContent='Thank you!';sub.textContent='Tap the microphone to say it again.';}
   else if(data){put('(voice recording)');label.textContent='Thank you!';sub.textContent='BULIG saved your answer.';}
   else{label.textContent='Tap and say the word';sub.textContent='I could not hear you. Try again in a quiet place.';}}
  go.addEventListener('click',async()=>{if(busy){finish();return;}busy=true;hush();go.classList.add('on');label.textContent='Listening…';sub.textContent='Say the word, then wait.';
   L=SR?listener(false):null;const ok=L&&L.start();if(!ok)L=null;R=(!L||together)?recorder():null;if(R&&!(await R.start()))R=null;
   if(!L&&!R){busy=false;go.classList.remove('on');label.textContent='Say it to your teacher';sub.textContent='This device cannot listen here.';put('(said to the teacher)');return;}
   if(L){L.wait().then(()=>{if(busy)finish();});}else setTimeout(()=>{if(busy)finish();},4000);});
 });

 /* ---------- Read the words ---------- */
 const wf=$('form[data-rc-words]');
 if(wf){const words=$$('[data-rc-w]',wf),go=$('[data-rc-go]',wf),label=$('[data-rc-label]',wf),sub=$('[data-rc-sub]',wf),count=$('[data-rc-count]',wf),skip=$('[data-rc-skip]',wf),send=$('[data-rc-send]',wf),srIn=$('[data-rc-sr]',wf);
  let i=0,wrong=0,busy=false,hold=false,L=null,R=null,mode=SR?'ok':'off';srIn.value=mode;/* hold: between "Thank you!" and the next word */
  const show=()=>{hold=false;words.forEach((w,k)=>w.hidden=k!==i);count.textContent='Word '+(i+1)+' of '+words.length;label.textContent=mode==='ok'?'Tap and read the word':'Tap, read the word, then tap again';sub.textContent='';};
  const end=msg=>{go.hidden=true;skip.hidden=true;words.forEach(w=>w.hidden=true);count.textContent=msg;label.textContent='Great reading!';sub.textContent='Sending your words…';send.hidden=false;setTimeout(()=>wf.requestSubmit?wf.requestSubmit(send):wf.submit(),900);};
  const keep=(text,data)=>{const w=words[i],h=$('input[name^="heard"]',w),a=$('input[name^="audio"]',w);h.disabled=false;h.value=text;if(data){a.disabled=false;a.value=data;}};
  const next=ok=>{wrong=ok===false?wrong+1:(ok?0:wrong);i++;if(wrong>=5){end('Thank you!');return;}if(i>=words.length){end('All done!');return;}show();};
  async function finish(){busy=false;hold=true;go.classList.remove('on');const res=L?await L.stop():{alts:[],err:''};const data=R?await R.stop():'';if(!words[i])return;
   if(L&&(res.err==='network'||res.err==='service-not-allowed'||res.err==='not-allowed')&&!res.alts.length){mode='off';srIn.value='off';}
   if(res.err==='audio-capture'&&R)together=false;
   if(L&&res.err==='no-speech'&&!res.alts.length&&!data){hold=false;label.textContent='Tap and read the word';sub.textContent='I did not hear you. Tap the microphone and read the word.';return;}
   const w=norm(words[i].firstChild.textContent);const hit=res.alts.some(a=>norm(a).split(' ').includes(w));
   if(mode==='ok'&&L){keep(res.alts.join('|'),hit?'':data);label.textContent='Thank you!';setTimeout(()=>next(hit),450);}
   else{keep('',data);setTimeout(()=>next(data?null:false),300);}}
  go.addEventListener('click',async()=>{if(hold||!words[i])return;if(busy){finish();return;}busy=true;hush();go.classList.add('on');label.textContent='Listening…';sub.textContent=mode==='ok'?'Read the word, then wait.':'Read the word, then tap the microphone.';
   L=mode==='ok'?listener(false):null;if(L&&!L.start())L=null;R=(!L||together)?recorder():null;if(R&&!(await R.start()))R=null;
   if(!L&&!R){busy=false;go.classList.remove('on');mode='off';srIn.value='off';keep('','');next(null);return;}
   if(L)L.wait().then(()=>{if(busy)finish();});else setTimeout(()=>{if(busy)finish();},5000);});
  skip.addEventListener('click',()=>{if(busy||hold||!words[i])return;hold=true;keep('-','');next(false);});
  show();}

 const MIC='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg>',PLAY='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>',AGAIN='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.6-7.4L4 10"/></svg>';
 /* ---------- Read a story ---------- */
 const sf=$('form[data-rc-story]');
 if(sf){const go=$('[data-rc-go]',sf),label=$('[data-rc-label]',sf),sub=$('[data-rc-sub]',sf),send=$('[data-rc-send]',sf),heard=$('[data-rc-heard]',sf),audio=$('[data-rc-audio]',sf),secs=$('[data-rc-secs]',sf),srIn=$('[data-rc-sr]',sf);
  let state='idle',L=null,R=null,t0=0,tick=null,autoStop=false;
  /* v205 Reading Coach: the words of the story light up as BULIG hears them (green) and the next word glows, so the pupil
     can follow. It is a reading check, so the coach never reads the story first or helps with a word. */
  const card=$('.l4-card',sf),W=[];
  if(card){const tw=document.createTreeWalker(card,NodeFilter.SHOW_TEXT,{acceptNode:n=>n.nodeValue.trim()&&!n.parentElement.closest('button,script,style,.l1-spk')?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT});const nodes=[];while(tw.nextNode())nodes.push(tw.currentNode);
   nodes.forEach(n=>{const f=document.createDocumentFragment();n.nodeValue.split(/(\s+)/).forEach(part=>{if(!part)return;if(/^\s+$/.test(part)){f.append(part);return;}const sp=document.createElement('span');sp.className='rk-sw';sp.textContent=part;const k=norm(part);if(k){sp.dataset.k=k;W.push(sp);}f.append(sp);});n.replaceWith(f);});}
  const near=(a,b)=>a===b||(b.length>=5&&Math.abs(a.length-b.length)<=1&&[...b].filter((c,i)=>a[i]===c).length>=b.length-1);
  const follow=t=>{if(!W.length)return;const toks=String(t).split(/\s+/).map(norm).filter(Boolean);let p=0;const ok=new Set();
   for(const x of toks){for(let k=0;k<=3&&p+k<W.length;k++){if(near(x,W[p+k].dataset.k)){ok.add(p+k);p=p+k+1;break;}}}
   W.forEach((w,i)=>{w.classList.toggle('ok',ok.has(i));w.classList.toggle('nx',i===p);w.classList.toggle('sk',i<p&&!ok.has(i));});
   if(pn)pn.textContent=String(ok.size);if(pb)pb.style.width=Math.min(100,Math.round(p/W.length*100))+'%';
   if(p>=W.length&&state==='rec'&&!autoStop){autoStop=true;setTimeout(()=>{if(state==='rec')go.click();},700);}
   if(W[p]){const r=W[p].getBoundingClientRect();if(r.top<120||r.bottom>innerHeight-140)W[p].scrollIntoView({block:'center',behavior:'smooth'});}};
  const pn=$('[data-rc-pn]',sf),pb=$('[data-rc-pb]',sf),prog=$('[data-rc-prog]',sf),steps=$$('[data-rc-steps] li',sf);
  if(prog&&W.length){const t=prog.querySelector('span');if(t)t.textContent=' of '+W.length+' words';}
  const step=n=>steps.forEach((li,i)=>{li.classList.toggle('on',i===n);li.classList.toggle('done',i<n);});
  /* the "Great reading!" sheet: listen back, read again, or send to the teacher */
  let sheet=null;const closeSheet=()=>{if(sheet){sheet.remove();sheet=null;}};
  const openSheet=(s,data)=>{closeSheet();const n=$$('.rk-sw.ok',sf).length;sheet=document.createElement('div');sheet.className='rk-sheet';sheet.setAttribute('role','dialog');sheet.setAttribute('aria-modal','true');sheet.setAttribute('aria-labelledby','rk-sh-t');
   const miss=W.length&&n?W.length-n:0,pc=W.length&&n?Math.round(n/W.length*100):0,st=pc>=95?3:pc>=80?2:pc>=50?1:0;
   sheet.innerHTML='<div class="rk-sh-in"><span class="rk-sh-ic">'+MIC+'</span><h2 id="rk-sh-t">'+(n&&pc<80?'Good reading!':'Great reading!')+'</h2>'
    +(W.length&&n?'<div class="wc-stars"><span class="ac-stars st3" role="img" aria-label="'+st+' of 3 stars">'+'<i></i>'.repeat(st)+'<i class="off"></i>'.repeat(3-st)+'</span><b class="wc-pct">'+pc+'%</b></div>':'')
    +'<div class="rk-sh-stats wc-tally"><div><b>'+fmt(s)+'</b><small>reading time</small></div>'+(W.length&&n?'<div class="g"><b>'+n+'</b><small>words right</small></div><div class="r"><b>'+miss+'</b><small>words missed</small></div>':'')+'</div>'
    +(data?'<button type="button" class="rk-sh-play" data-sh-play><span class="rk-sh-pi">'+PLAY+'</span>Listen to my reading</button>':'')
    +'<p class="rk-sh-note">Your teacher will listen to your reading and check your level.</p><div class="rk-sh-btns"><button type="button" class="rk-sh-again" data-sh-again>'+AGAIN+'Read again</button></div></div>';
   const btns=sheet.querySelector('.rk-sh-btns');btns.append(send);send.hidden=false;sf.append(sheet);setTimeout(()=>send.focus({preventScroll:true}),200);
   /* sends to the teacher by itself after 5 seconds; Read again or Listen stops the countdown */
   const cnt=document.createElement('p');cnt.className='rk-sh-count';cnt.setAttribute('aria-live','polite');cnt.innerHTML='Sending to your teacher in <b>5</b>…';btns.before(cnt);let left=5;
   const cd=setInterval(()=>{if(!sheet){clearInterval(cd);return;}left--;cnt.querySelector('b').textContent=String(left);if(left<=0){clearInterval(cd);cnt.textContent='Sending…';sf.requestSubmit?sf.requestSubmit(send):send.click();}},1000);
   const stopCd=()=>{clearInterval(cd);cnt.remove();};sheet.addEventListener('click',e=>{if(e.target.closest('[data-sh-again],[data-sh-play]'))stopCd();});
   const pl=sheet.querySelector('[data-sh-play]');if(pl){let au=null;pl.addEventListener('click',()=>{if(!au){au=new Audio(data);au.addEventListener('ended',()=>pl.classList.remove('on'));}if(!au.paused){au.pause();pl.classList.remove('on');return;}au.currentTime=0;au.play().then(()=>pl.classList.add('on')).catch(()=>{});});}
   sheet.querySelector('[data-sh-again]').addEventListener('click',()=>{const bar=$('.rk-storybar',sf);bar.append(send);send.hidden=true;closeSheet();step(0);go.click();});};
  const coachReset=on=>{W.forEach((w,i)=>{w.classList.remove('ok','sk','nx');if(on&&i===0)w.classList.add('nx');});if(card)card.classList.toggle('rk-coach-on',!!on);};
  go.addEventListener('click',async()=>{
   if(state==='rec'){if(Date.now()-t0<1500)return;state='busy';clearInterval(tick);go.classList.remove('on');label.textContent='Saving…';
    const s=(Date.now()-t0)/1000;const res=L?await L.stop():{text:'',err:'none',got:false};const data=R?await R.stop():'';
    heard.value=res.text.trim();audio.value=data;secs.value=String(Math.round(s));srIn.value=L&&(res.got||!res.err)?'ok':'off';
    if(!heard.value&&!data){state='idle';label.textContent='Tap and read the title and the story';sub.textContent='I could not hear you. Try again in a quiet place.';return;}
    if(card)card.classList.remove('rk-coach-on');W.forEach(w=>w.classList.remove('nx'));state='done';label.textContent='Your reading · '+fmt(s);sub.textContent='Tap Send to my teacher, or read again.';step(2);openSheet(s,data);return;}
   if(state==='busy')return;state='busy';hush();send.hidden=true;heard.value='';audio.value='';
   L=SR?listener(true,follow):null;if(L&&!L.start())L=null;R=(!L||together)?recorder():null;if(R&&!(await R.start()))R=null;autoStop=false;coachReset(!!L);if(prog){prog.hidden=!L||!W.length;}if(pn)pn.textContent='0';if(pb)pb.style.width='0%';step(1);closeSheet();
   if(!L&&!R){state='idle';label.textContent='Read the story to your teacher';sub.textContent='This device cannot listen here. Tap Send when you finish.';srIn.value='off';send.hidden=false;return;}
   state='rec';t0=Date.now();go.classList.add('on');label.textContent='0:00';sub.textContent='Read to the last word. BULIG finishes by itself.';tick=setInterval(()=>{label.textContent=fmt((Date.now()-t0)/1000);if(Date.now()-t0>600000)go.click();},250);});}

 /* ---------- cards: read the question aloud when a new card opens (Part 3 is an oral test) ---------- */
 const deck=$('[data-l2-deck]');
 if(deck&&deck.dataset.autosay){let first=true;new MutationObserver(()=>{const c=$$('.l2-card',deck).find(x=>!x.hidden);if(!c)return;if(first){first=false;return;}const b=$('.l2-cardtop .l1-spk',c);if(b)setTimeout(()=>b.click(),250);}).observe(deck,{subtree:true,attributes:true,attributeFilter:['hidden']});}
})();
