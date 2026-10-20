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

 /* ---------- Read a story ---------- */
 const sf=$('form[data-rc-story]');
 if(sf){const go=$('[data-rc-go]',sf),label=$('[data-rc-label]',sf),sub=$('[data-rc-sub]',sf),send=$('[data-rc-send]',sf),heard=$('[data-rc-heard]',sf),audio=$('[data-rc-audio]',sf),secs=$('[data-rc-secs]',sf),srIn=$('[data-rc-sr]',sf);
  let state='idle',L=null,R=null,t0=0,tick=null;
  go.addEventListener('click',async()=>{
   if(state==='rec'){if(Date.now()-t0<1500)return;state='busy';clearInterval(tick);go.classList.remove('on');label.textContent='Saving…';
    const s=(Date.now()-t0)/1000;const res=L?await L.stop():{text:'',err:'none',got:false};const data=R?await R.stop():'';
    heard.value=res.text.trim();audio.value=data;secs.value=String(Math.round(s));srIn.value=L&&(res.got||!res.err)?'ok':'off';
    if(!heard.value&&!data){state='idle';label.textContent='Tap and read the title and the story';sub.textContent='I could not hear you. Try again in a quiet place.';return;}
    state='done';label.textContent='Your reading · '+fmt(s);sub.textContent='Tap Send. Or tap the microphone to read again.';send.hidden=false;send.focus({preventScroll:true});return;}
   if(state==='busy')return;state='busy';hush();send.hidden=true;heard.value='';audio.value='';
   L=SR?listener(true):null;if(L&&!L.start())L=null;R=(!L||together)?recorder():null;if(R&&!(await R.start()))R=null;
   if(!L&&!R){state='idle';label.textContent='Read the story to your teacher';sub.textContent='This device cannot listen here. Tap Send when you finish.';srIn.value='off';send.hidden=false;return;}
   state='rec';t0=Date.now();go.classList.add('on');label.textContent='0:00';sub.textContent='Read now. Tap the microphone when you finish.';tick=setInterval(()=>{label.textContent=fmt((Date.now()-t0)/1000);if(Date.now()-t0>600000)go.click();},250);});}

 /* ---------- cards: read the question aloud when a new card opens (Part 3 is an oral test) ---------- */
 const deck=$('[data-l2-deck]');
 if(deck&&deck.dataset.autosay){let first=true;new MutationObserver(()=>{const c=$$('.l2-card',deck).find(x=>!x.hidden);if(!c)return;if(first){first=false;return;}const b=$('.l2-cardtop .l1-spk',c);if(b)setTimeout(()=>b.click(),250);}).observe(deck,{subtree:true,attributes:true,attributeFilter:['hidden']});}
})();
