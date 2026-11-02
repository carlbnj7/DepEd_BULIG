/* Word Coach (v184, app/coach.php). Every reading recorder with data-wc gets Max the coach first:
   1) Max reads the whole text while the words light up (Skip goes straight on);
   2) the pupil reads it all aloud (Max does not say it first); each word turns green when BULIG hears it said right
      (this is the pupil's reading: "Save my reading" sends it, no need to record it again);
   3) the words not said right, one at a time, each with 5 hearts (v207): a wrong try takes a heart; the more hearts kept, the
      more points (5 hearts 5/6 of a point ... 1 heart 1/6); with no hearts left the word goes on the practise list (0).
      A word right the first time is worth 1 point;
   4) the stars and the words to practise, then the microphone for the real reading opens.
   The result goes in the hidden field wc[n] as "percent|word word" ("na|" when the device cannot listen: no score). */
(()=>{
 'use strict';
 const recs=[...document.querySelectorAll('[data-wc]')];if(!recs.length)return;
 // Browser identity never disables recognition. Apple browsers get an explicit listening gesture.
 const APPLE=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1)||(/Safari\//.test(navigator.userAgent)&&!/Chrome|Chromium|CriOS|FxiOS|Edg|OPR|Android/.test(navigator.userAgent));
 const Rec=window.SpeechRecognition||window.webkitSpeechRecognition,synth=window.speechSynthesis||null;
 // Record only the pupil phase. Never record Max's opening demonstration or continue after closing.
 async function captureReading(live){
  let media=null,mr=null,chunks=[],timer=null,expired=false,ended=false,failure='';
  const cleanup=()=>{clearTimeout(timer);if(media)media.getTracks().forEach(t=>t.stop());};
  try{
   if(!navigator.mediaDevices||!window.MediaRecorder)return null;
   media=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});
   // Some microphone implementations mute capture when recognition starts. Never save that as evidence.
   media.getAudioTracks().forEach(t=>{t.addEventListener('mute',()=>{if(!ended&&mr&&mr.state==='recording'){expired=true;failure='The microphone was interrupted while Word Coach was listening. This attempt has no complete recording.';}});t.addEventListener('ended',()=>{if(!ended)expired=true;});});
   const type=(APPLE?['audio/mp4','audio/webm;codecs=opus','audio/webm']:['audio/webm;codecs=opus','audio/mp4','audio/webm','audio/ogg;codecs=opus']).find(t=>MediaRecorder.isTypeSupported(t));
   mr=new MediaRecorder(media,type?{mimeType:type,audioBitsPerSecond:24000}:{audioBitsPerSecond:24000});
   let resolve;const result=new Promise(r=>resolve=r);
   mr.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data);};
   mr.onerror=()=>{expired=true;failure='The browser could not finish the audio recording.';cleanup();resolve('');};
   mr.onstop=()=>{cleanup();const blob=new Blob(chunks,{type:(mr.mimeType||'audio/webm').split(';')[0]});if(expired||blob.size<200||blob.size>6000000){resolve('');return;}const f=new FileReader();f.onload=()=>resolve(String(f.result));f.onerror=()=>resolve('');f.readAsDataURL(blob);};
   mr.start(500);if(!live)mr.pause();timer=setTimeout(()=>{expired=true;if(mr.state!=='inactive')mr.stop();},600000);
   return {error(){return failure;},valid(){return !expired&&media.getAudioTracks().every(t=>t.readyState==='live'&&!t.muted);},pause(){if(mr.state==='recording')mr.pause();},resume(){if(mr.state==='paused')mr.resume();},stop(){if(!ended){ended=true;if(mr.state!=='inactive')mr.stop();else cleanup();}return result;},cancel(){expired=true;this.stop();cleanup();}};
  }catch(e){cleanup();return null;}
 }

 const IMG=p=>'assets/images/characters/max-'+p+'.webp?v=2';
 /* v205 sending: 'tap' = one Send to my teacher button; 'auto' = sends by itself after 5 seconds */
 const SENDMODE=new URLSearchParams(location.search).get('wcsend')==='tap'?'tap':'auto';
 const PTS=[1,2/3,1/3],TRIES=3,HEARTS=5,RATE=.65;   // Max reads slowly
 const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const norm=w=>String(w).toLowerCase().replace(/[’‘`]/g,"'").replace(/[^\p{L}\p{N}']+/gu,'').replace(/^'+|'+$/g,'');
 const store={get(k){try{return sessionStorage.getItem(k);}catch(e){return null;}},set(k,v){try{sessionStorage.setItem(k,v);}catch(e){}}};
 const actId=(document.querySelector('#activity-form [name="activity_id"]')||{}).value||location.search;
 const svg={mic:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>',
  spk:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg>',
  slow:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/></svg>',
  skip:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 5l8 7-8 7zM16 5v14"/></svg>',
  close:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
  ok:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>',redo:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>'};

 /* Max's voice: the voice the pupil chose in Settings, else a clear English voice */
 let voice=null;const pickVoice=()=>{if(!synth)return;const vs=synth.getVoices().filter(v=>/^en/i.test(v.lang));let mine=null;try{mine=localStorage.getItem('bulig-voice');}catch(e){}
  voice=vs.find(v=>v.voiceURI===mine)||vs.find(v=>/david|daniel|guy|alex|fred|male/i.test(v.name))||vs.find(v=>/en-US/i.test(v.lang))||vs[0]||null;};
 if(synth){pickVoice();synth.addEventListener&&synth.addEventListener('voiceschanged',pickVoice);}
 const speak=(text,rate,onEnd,onBound)=>{if(!synth){setTimeout(()=>onEnd&&onEnd(),400);return null;}const u=new SpeechSynthesisUtterance(text);if(voice)u.voice=voice;u.lang='en-US';u.rate=rate;u.pitch=1.05;
  let done=false;const fin=()=>{if(done)return;done=true;onEnd&&onEnd();};u.onend=fin;u.onerror=fin;if(onBound)u.onboundary=onBound;synth.speak(u);
  setTimeout(fin,Math.max(2500,text.length*140/rate));return u;};   // some phones never fire "end"
 const hush=()=>{try{synth&&synth.cancel();}catch(e){}};

 /* Is what BULIG heard close enough? One word: the same word, or one letter off for longer words. A line: 80% of its words. */
 const lev=(a,b)=>{const m=a.length,n=b.length;if(Math.abs(m-n)>1)return 2;const d=Array.from({length:m+1},(_,i)=>[i]);for(let j=1;j<=n;j++)d[0][j]=j;
  for(let i=1;i<=m;i++)for(let j=1;j<=n;j++)d[i][j]=Math.min(d[i-1][j]+1,d[i][j-1]+1,d[i-1][j-1]+(a[i-1]===b[j-1]?0:1));return d[m][n];};
 /* v207: the phone's listening often writes a short word as a word that sounds the same ("then" for "den", "10" for
    "ten", "wet" for "wed"): compare how the words sound, not only their letters */
 const NUMW=['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty'];
 const sound=w=>{w=String(w).toLowerCase();if(/^\d+$/.test(w)&&+w<NUMW.length)w=NUMW[+w];
  return w.replace(/^th/,'d').replace(/th/g,'d').replace(/^wh/,'w').replace(/ph/g,'f').replace(/ck/g,'k').replace(/c(?=[aou]|$)/g,'k').replace(/q/g,'k').replace(/x/g,'ks')
   .replace(/(.)\1+/g,'$1').replace(/([^aeiou])e$/,'$1').replace(/[dt]$/,'T').replace(/[gk]$/,'K').replace(/[bp]$/,'P').replace(/[sz]$/,'S');};
 const near=(h,t)=>h===t||(t.length>=5&&lev(h,t)<=1)||(h.length>1&&sound(h)===sound(t));
 const heardOk=(alts,unit)=>{const want=unit.map(norm).filter(Boolean);if(!want.length)return true;
  for(const alt of alts){const got=alt.split(/\s+/).map(norm).filter(Boolean);
   if(want.length===1){if(got.some(g=>near(g,want[0])))return true;continue;}
   const pool=[...got];let hit=0;for(const w of want){const i=pool.findIndex(g=>near(g,w));if(i>=0){hit++;pool.splice(i,1);}}
   if(hit/want.length>=.8)return true;}
  return false;};

 /* One listen: resolves with the transcripts, '' when nothing was heard, or null when this device cannot listen */
 const listen=signal=>new Promise(res=>{if(!Rec){res(null);return;}let r;try{r=new Rec();}catch(e){res(null);return;}
  r.lang='en-US';r.interimResults=false;r.maxAlternatives=5;r.continuous=false;let out=[],done=false,timer=null;
  const cancel=()=>fin(null);
  const fin=v=>{if(done)return;done=true;clearTimeout(timer);signal.removeEventListener('abort',cancel);try{r.abort();}catch(e){}res(v);};
  if(signal.aborted){fin(null);return;}signal.addEventListener('abort',cancel,{once:true});
  r.onresult=e=>{for(const x of e.results)for(const a of x)out.push(a.transcript);};
  r.onerror=e=>{fin(['not-allowed','service-not-allowed','network','audio-capture','language-not-supported'].includes(e.error)?null:'');};
  r.onend=()=>fin(out.length?out:'');
  try{r.start();}catch(e){fin(null);}if(!done)timer=setTimeout(()=>fin(out.length?out:''),9000);});


 /* Listen while the pupil reads: onText(all words heard in this listening session), onNew() when a new session starts
    (Chrome stops after a pause, so BULIG starts again), onStop() when this device cannot listen any more. */
 const listenAll=(onText,onNew,onStop)=>{let on=true,r=null,restart=null;
  const fail=reason=>{if(!on)return;on=false;clearTimeout(restart);try{r&&r.abort();}catch(e){}queueMicrotask(()=>onStop(reason));};
  const start=()=>{if(!on)return;try{r=new Rec();}catch(e){fail('unavailable');return;}r.lang='en-US';r.continuous=true;r.interimResults=true;r.maxAlternatives=1;onNew();
   r.onresult=e=>{if(!on)return;let t='';for(let i=0;i<e.results.length;i++)t+=' '+e.results[i][0].transcript;onText(t);};
   r.onerror=e=>{if(['not-allowed','service-not-allowed','audio-capture','language-not-supported','network'].includes(e.error))fail(e.error);};
   r.onend=()=>{if(on)restart=setTimeout(start,300);};try{r.start();}catch(e){fail(e.name==='NotAllowedError'?'not-allowed':'unavailable');}};
  start();return {stop(){on=false;clearTimeout(restart);try{r&&r.abort();}catch(e){}}};};
 const words=t=>String(t).split(/\s+/).filter(w=>/[\p{L}\p{N}]/u.test(w));
 const lines=t=>{const out=[];let cur=[];for(const w of words(t)){cur.push(w);if(/[.!?]["”’)]*$/.test(w)||cur.length>=12){out.push(cur);cur=[];}}if(cur.length)out.push(cur);return out;};
 const stars=p=>p>=95?3:p>=80?2:p>=50?1:0;
 const starHtml=(n,of)=>'<span class="ac-stars st'+of+'" role="img" aria-label="'+n+' of '+of+' stars">'+'<i></i>'.repeat(n)+'<i class="off"></i>'.repeat(of-n)+'</span>';

 recs.forEach((rec,ix)=>{
  /* v208: Level 4 stories: the title is read too (it comes first, on its own line), so reading it is not a miss */
  const tw=words(rec.dataset.wcTitle||'').length,text=(tw?rec.dataset.wcTitle+' ':'')+(rec.dataset.wc||'');if(!words(rec.dataset.wc||'').length)return;
  const val=rec.querySelector('[data-wc-val]');const card=rec.closest('.l2-card');const key='wc:'+actId+':'+(card?card.dataset.n:0);
  const gate=document.createElement('div');gate.className='wc-gate';rec.before(gate);
  const lock=on=>{rec.classList.toggle('wc-locked',on);rec.querySelectorAll('button').forEach(b=>{if(on)b.setAttribute('aria-disabled','true');else b.removeAttribute('aria-disabled');});};
  rec.addEventListener('click',e=>{if(!rec.classList.contains('wc-locked'))return;e.preventDefault();e.stopImmediatePropagation();gate.classList.remove('wc-nudge');void gate.offsetWidth;gate.classList.add('wc-nudge');},true);
  const drawGate=res=>{
   if(!res){gate.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><b>First, practise with Max!</b><p>Max reads it to you slowly. Then you read it, and each word turns green when you say it right. Fewer tries, more points! Your reading will be recorded for your teacher.</p></div><button type="button" class="btn wc-go">'+svg.mic+'Start</button>';
    gate.querySelector('.wc-go').addEventListener('click',()=>open(false));lock(true);return;}
   const [p,w]=res.split('|');gate.classList.add('wc-done');
   gate.innerHTML='<img src="'+IMG('thumbs')+'" alt=""><div><b>'+(p==='na'?'You practised with Max!':'Word Coach: '+p+'%')+'</b>'+(p==='na'?'':starHtml(stars(+p),3))+(w?'<p>Practise: '+esc(w.split(' ').join(', '))+'</p>':(p==='na'?'<p>Now tap the microphone and read it all.</p>':'<p>Your reading is saved. Great work!</p>'))+'</div><button type="button" class="btn wc-again">Do it again</button>';
   gate.querySelector('.wc-again').addEventListener('click',()=>open(false));lock(false);};   // a new run gives a new score
  // A cached percentage is not a recording: every unsaved page visit starts a fresh reading.
  const saved=null;drawGate(saved);
  /* The coach run is the reading (v184): no need to record it again. The recorder shows it as done. */
  const saveReading=(said,secs,audio)=>{rec.dispatchEvent(new CustomEvent('wc:done',{detail:{said,secs,audio}}));rec.scrollIntoView({block:'center',behavior:'smooth'});};
  /* v205: "Send to my teacher": after saving, go on to the next card that needs an answer, or send the activity */
  let sendAfter=false;const sendNow=()=>{const f=rec.closest('form');if(!f)return;const vis=b=>b&&!b.hidden&&b.offsetParent!==null;
   const nx=f.querySelector('[data-l2-next]'),sb=f.querySelector('#submit-answer')||f.querySelector('button[type=submit]:not([data-l2-prev])');
   if(vis(nx)){nx.click();return;}if(vis(sb)){sb.click();return;}if(sb&&f.requestSubmit)f.requestSubmit(sb);};
  rec.addEventListener('wc:done',()=>{if(sendAfter){sendAfter=false;setTimeout(sendNow,450);}});

  function open(practice){let hlTotal=null;
   hush();const attemptAbort=new AbortController();const all=words(text);let recording=null,audio='',recordingResult=null,recordingError='';
   const audioField=()=>{let f=rec.querySelector('[data-coach-audio]');if(!f){f=document.createElement('input');f.type='hidden';f.name='coach_audio['+(val?val.name.match(/\d+/)[0]:0)+']';f.dataset.coachAudio='';rec.append(f);}return f;};
   if(!practice){audioField().value='';if(val)val.value='';}
   const ov=document.createElement('div');ov.className='wc-ov';ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-label','Word Coach with Max');
   ov.innerHTML='<div class="wc-panel"><button type="button" class="wc-x" aria-label="Close">'+svg.close+'</button><div class="wc-top"><span class="wc-eb">WORD COACH</span><div class="wc-prog"><span data-c></span><div class="wc-bar"><i data-bar></i></div></div></div><div class="wc-txt" data-txt></div><div class="wc-box" data-box></div></div>';
   document.body.appendChild(ov);document.body.classList.add('wc-open');
   const $=s=>ov.querySelector(s),txt=$('[data-txt]'),box=$('[data-box]');
   /* v207: Max stands next to a speech bubble with the text to read; his pose follows the coach box */
   const scene=document.createElement('div');scene.className='wc-scene';const pal=document.createElement('img');pal.className='wc-pal';pal.alt='';pal.src=IMG('hello');
   txt.before(scene);scene.append(pal,txt);txt.classList.add('wc-bubble');
   new MutationObserver(()=>{const im=box.querySelector(':scope > img');if(im&&im.getAttribute('src')!==pal.getAttribute('src'))pal.src=im.getAttribute('src');}).observe(box,{childList:true});
   txt.innerHTML=all.map((w,i)=>'<span class="wc-w'+(i<tw?' wc-tw':'')+'" data-i="'+i+'">'+esc(w)+'</span>'+(tw&&i===tw-1?'<br>':'')).join(' ');
   const W=[...txt.querySelectorAll('.wc-w')];let alive=true;
   let gen=0;const say=(t,r,cb,b)=>{const g=++gen;hush();if(recording)recording.pause();speak(t,r,()=>{if(g===gen&&alive){if(recording)recording.resume();if(cb)cb();}},b);};   // only the newest voice line moves the coach on
   const close=()=>{alive=false;gen++;attemptAbort.abort();hush();if(recording)recording.cancel();window.removeEventListener('pagehide',close);if(stream)stream.stop();ov.remove();document.body.classList.remove('wc-open');};
   $('.wc-x').addEventListener('click',close);window.addEventListener('pagehide',close,{once:true});
   const mark=(i0,i1,cls)=>{for(let i=i0;i<=i1;i++){W[i].classList.remove('cur','dim');if(cls)W[i].classList.add(cls);}};
   const focus=(i0,i1)=>{W.forEach(x=>x.classList.remove('cur'));for(let i=i0;i<=i1;i++)W[i].classList.add('cur');W[i0].scrollIntoView({block:'nearest',behavior:'smooth'});};
   const prog=(n,of,label)=>{$('[data-c]').textContent=label+' '+Math.min(n+1,of)+' of '+of;$('[data-bar]').style.width=Math.round(n/of*100)+'%';};

   /* 1) Max reads it all, slowly (sentence by sentence; the words light up). Skip goes on. */
   const readAll=()=>{W.forEach(x=>x.classList.add('dim'));
    box.innerHTML='<img src="'+IMG('hello')+'" alt=""><div><div class="wc-step"><span class="on">1 Max reads</span><span>2 You read</span><span>3 Fix the missed words</span></div><p class="wc-say">Listen and follow the words. Max reads it first.</p><div class="wc-row"><button type="button" class="btn primary wc-skip">'+svg.skip+'Skip, I am ready</button></div></div>';
    $('[data-c]').textContent='Listen first';$('[data-bar]').style.width='0%';
    const parts=lines(text);let at=0,base=0,timer=null,over=false;
    const next=()=>{clearTimeout(timer);if(!alive||over)return;if(at>=parts.length){over=true;W.forEach(x=>x.classList.remove('cur','dim'));setTimeout(()=>alive&&pass(),700);return;}
     const p=parts[at],start=base;let k=0,got=false;base+=p.length;at++;
     const offs=[];let pos=0;const line=p.join(' ');p.forEach(w=>{offs.push(pos);pos+=w.length+1;});
     const show=j=>{if(j>=p.length)return;focus(start+j,start+j);for(let q=0;q<j;q++)W[start+q].classList.remove('dim');};show(0);
     const tick=()=>{if(got||k+1>=p.length)return;k++;show(k);timer=setTimeout(tick,Math.max(450,p[k].length*95+330));};timer=setTimeout(tick,Math.max(450,p[0].length*95+330));   // when the voice gives no word timing
     say(line,RATE,()=>{clearTimeout(timer);for(let q=0;q<p.length;q++)W[start+q].classList.remove('dim');setTimeout(next,650);},e=>{if(e.name&&e.name!=='word')return;got=true;let j=0;while(j+1<offs.length&&offs[j+1]<=e.charIndex)j++;show(j);});};
    box.querySelector('.wc-skip').addEventListener('click',()=>{if(over)return;over=true;at=parts.length;clearTimeout(timer);gen++;hush();W.forEach(x=>x.classList.remove('cur','dim'));pass();});
    next();};

   /* 2) The pupil reads it all aloud; each word turns green when BULIG hears it said right. */
   const N=all.length,T=all.map(norm),S=all.map(()=>null),fails=all.map(()=>0),got=all.map(()=>null);hlTotal=0;let stream=null,t0=0,secs=0;
   const paint=(i,cls)=>{W[i].classList.remove('cur','dim','ok','mid','bad','miss','nx');if(cls)W[i].classList.add(cls);};
   async function pass(){
    box.innerHTML='<p class="wc-say">Preparing your reading recording… Your teacher can listen to this attempt.</p>';
    if(!practice)recording=await captureReading();if(!alive){if(recording)recording.cancel();return;}
    if(!Rec){noListen('unavailable');return;}
    if(APPLE){
     if(recording)recording.pause();
     $('[data-c]').textContent='Your turn';
     box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><p class="wc-say"><b>Your turn!</b> Tap below, then read aloud. Your reading will be scored and recorded for your teacher.</p><button type="button" class="btn primary wc-start-reading">'+svg.mic+'Start my reading</button></div>';
     box.querySelector('.wc-start-reading').onclick=()=>{if(recording)recording.resume();beginListening();};
    }else{if(recording)recording.resume();beginListening();}
   }
   function beginListening(){
    if(!alive)return;
    let p=0,sessBase=0,last=Date.now(),hintT=null,ended=false;t0=Date.now();W.forEach((x,i)=>paint(i,''));ov.classList.add('wc-reading');
    box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><div class="wc-step"><span>1 Max reads</span><span class="on">2 You read</span><span>3 Fix the missed words</span></div><p class="wc-say"><b>Your turn!</b> Read it out loud. Each word turns green when you say it right.</p><div class="wc-row wc-dockrow"><span class="wc-mic">'+svg.mic+'</span><span class="wc-dockst"><b data-st>0:00</b><span data-recording-note></span></span><span class="wc-wave"><i></i><i></i><i></i><i></i><i></i><i></i></span></div><p class="wc-hint" data-hint hidden></p><div class="wc-row wc-dockbtns"><button type="button" class="btn ghost wc-skipw">'+svg.skip+'Skip this word</button><button type="button" class="btn primary wc-done">'+svg.ok+'I am done</button></div><p class="wc-auto">Read to the last word and BULIG finishes by itself.</p></div>';
    box.querySelector('[data-recording-note]').textContent=recording?'Recording for your teacher':'Audio unavailable — teacher review needed';
    const hint=box.querySelector('[data-hint]');
    const show=()=>{for(let i=0;i<N;i++)if(!S[i])paint(i,i===p?'nx':'');if(p<N)W[p].scrollIntoView({block:'nearest',behavior:'smooth'});prog(Math.min(p,N-1),N,'Word');$('[data-bar]').style.width=Math.round(p/N*100)+'%';};
    const set=(i,v)=>{S[i]=v;paint(i,v==='ok'?'ok':'miss');};
    const finish=()=>{if(ended)return;ended=true;ov.classList.remove('wc-reading');secs=Math.round((Date.now()-t0)/1000);clearInterval(hintT);if(stream)stream.stop();for(let i=0;i<N;i++)if(S[i]!=='ok')set(i,'miss');
     for(let i=0;i<N;i++)if(S[i]==='ok')got[i]=1;else fails[i]=1;setTimeout(()=>alive&&fix(),700);};
    const align=heard=>{if(ended)return;const toks=heard.split(/\s+/).map(norm).filter(Boolean);let q=sessBase;const st={};
     /* the next 4 words, or (when the pupil read on) the same word further on: the reading always goes on */
     for(const t of toks){let hit=-1;for(let k=0;k<=3&&q+k<N;k++)if(near(t,T[q+k])){hit=k;break;}
      if(hit<0)for(let k=4;q+k<N&&k<=12;k++)if(t===T[q+k]&&T.indexOf(t,q)===q+k){hit=k;break;}
      if(hit>=0){for(let m=q;m<q+hit;m++)st[m]='miss';st[q+hit]='ok';q=q+hit+1;}}
     if(q<p)return;for(let i=sessBase;i<q;i++)if(st[i]&&S[i]!=='ok')set(i,st[i]);else if(st[i]==='ok')set(i,'ok');
     if(q>p){p=q;last=Date.now();hint.hidden=true;}show();if(p>=N)finish();};
    const startListening=()=>{stream=listenAll(align,()=>{sessBase=p;},reason=>{if(!alive||ended)return;ended=true;clearInterval(hintT);stream=null;noListen(reason);});};
    box.querySelector('.wc-skipw').onclick=()=>{if(p<N){set(p,'miss');p++;sessBase=p;last=Date.now();hint.hidden=true;show();if(p>=N)finish();}};
    box.querySelector('.wc-done').onclick=finish;
    hintT=setInterval(()=>{const stl=box.querySelector('[data-st]'),el=Math.round((Date.now()-t0)/1000);if(stl)stl.textContent=Math.floor(el/60)+':'+String(el%60).padStart(2,'0');const note=box.querySelector('[data-recording-note]');if(note&&recording&&!recording.valid())note.textContent='Recording interrupted — please retry this reading';if(!live()||ended){clearInterval(hintT);return;}if(Date.now()-last>12000&&p<N){set(p,'miss');p++;sessBase=p;last=Date.now();hint.hidden=true;show();if(p>=N)finish();return;}if(Date.now()-last>7000&&p<N){hint.hidden=false;hint.innerHTML='Stuck on <b>“'+esc(all[p])+'”</b>? Tap it to hear it, or tap <b>Skip this word</b>.';}},1000);
    W.forEach((x,i)=>{x.onclick=()=>{if(ended)return;say(all[i],.6);if(S[i]!=='ok'&&i>=p){set(i,'miss');if(i===p){p++;sessBase=p;show();if(p>=N)finish();}}};});
    show();startListening();}
   const live=()=>alive;

   /* 3) The missed words, one at a time: the pupil says it again (no help first). 2nd try 2/3, 3rd try 1/3, then 0. */
   function fix(list){
    W.forEach(x=>x.onclick=null);
    const todo=list||all.map((w,i)=>i).filter(i=>S[i]!=='ok');let at=0;
    const step='<div class="wc-step"><span>1 Max reads</span><span>2 You read</span><span class="on">3 Fix the missed words</span></div>';
    const big=i=>'<div class="wc-big">'+esc(all[i])+'</div>';
    const HS='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.5-9.2C1 8.2 3.3 4.5 7 4.5c2.1 0 3.6 1.2 5 3 1.4-1.8 2.9-3 5-3 3.7 0 6 3.7 4.5 7.3C19.5 16.4 12 21 12 21z"/></svg>';
    const hl=all.map(()=>0);   // hearts lost on each word
    const tries=(i,pop)=>'<span class="wc-hearts" role="img" aria-label="'+(HEARTS-hl[i])+' hearts left">'+Array.from({length:HEARTS},(_,k)=>'<i class="'+(k<HEARTS-hl[i]?'on':'off')+(pop&&k===HEARTS-hl[i]?' lost':'')+'">'+HS+'</i>').join('')+'</span>'+(pop?'<span class="wc-hpop">−1 '+HS+'</span>':'');
    const nextWord=()=>{if(!alive)return;if(at>=todo.length){summary();return;}const i=todo[at];focus(i,i);prog(at,todo.length,'Missed word');ask(i);};
    const ask=i=>{if(!Rec){got[i]=null;at++;nextWord();return;}
     box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<p class="wc-say">Read this word again.</p><div class="wc-row"><span class="wc-mic">'+svg.mic+'</span><span class="wc-wave"><i></i><i></i><i></i><i></i><i></i><i></i></span><b>Listening…</b></div><div class="wc-row wc-hrow">'+tries(i)+'</div></div>';
     listen(attemptAbort.signal).then(alts=>{if(!alive)return;
      if(alts===null||alts===''){box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<div class="wc-res y">I did not hear you. Say it a little louder. (No heart lost.)</div><div class="wc-row">'+tries(i)+'<button type="button" class="btn primary wc-again">'+svg.mic+'Say it</button></div></div>';box.querySelector('.wc-again').onclick=()=>ask(i);return;}
      if(heardOk(alts,[all[i]])){got[i]=practice?1:Math.max(0,HEARTS-hl[i])/(HEARTS+1);paint(i,'mid');box.innerHTML='<img src="'+IMG('thumbs')+'" alt=""><div>'+step+big(i)+'<div class="wc-res g">Yes! You got it.</div></div>';at++;setTimeout(nextWord,1000);return;}
      fails[i]++;hl[i]++;hlTotal=(hlTotal||0)+1;const heard=String(alts[0]||'').trim();
      if(hl[i]>=HEARTS){got[i]=0;paint(i,'bad');box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<div class="wc-row wc-hrow">'+tries(i)+'</div><div class="wc-res y">No hearts left for this word. Listen: <b>'+esc(all[i])+'</b>. It goes to My practice words.</div></div>';say(all[i],.55,()=>{at++;setTimeout(nextWord,500);});return;}
      box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<div class="wc-row wc-hrow">'+tries(i,true)+'</div><div class="wc-res y">Not yet!'+(heard?' You said “'+esc(heard)+'”.':'')+' '+(HEARTS-hl[i])+' '+(HEARTS-hl[i]===1?'heart':'hearts')+' left for this word.</div><div class="wc-row"><button type="button" class="btn ghost wc-slow">'+svg.spk+'Hear it</button><button type="button" class="btn primary wc-again">'+svg.mic+'Say it again</button></div></div>';
      box.querySelector('.wc-slow').onclick=()=>say(all[i],.5);box.querySelector('.wc-again').onclick=()=>{gen++;hush();ask(i);};});};
    nextWord();}

   /* Only an absent API or a real recognition error opens this choice; never browser identity. */
   function noListen(reason){if(!alive)return;const recordStart=Date.now();W.forEach((x,i)=>{paint(i,'');x.onclick=null;});
    if(recording)recording.pause();
    $('[data-c]').textContent='Listening paused';
    const why=reason==='network'?'The speech service could not connect. Check your connection and try again.':(['not-allowed','service-not-allowed'].includes(reason)?'Please allow microphone and speech recognition access in your browser, then try again.':'Word Coach could not start listening. Please try again.');
    box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><p class="wc-say">'+why+'</p><div class="wc-row"><button type="button" class="btn primary wc-retry-listen">'+svg.mic+'Try listening again</button><button type="button" class="btn ghost wc-audio-only">Let my teacher review</button></div><p class="wc-note">Teacher review alone does not give an automatic pronunciation score.</p></div>';
    box.querySelector('.wc-retry-listen').onclick=()=>{close();open(false);};
    box.querySelector('.wc-audio-only').onclick=()=>{
     if(recording)recording.resume();
     $('[data-c]').textContent='Read for your teacher';
     box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><p class="wc-say"><b>Your turn!</b> Read it all out loud for your teacher.</p><button type="button" class="btn primary wc-fin">'+svg.ok+'I finished reading</button><p class="wc-note">'+(recording&&recording.valid()?'Recording for your teacher.':'Audio is unavailable. Please retry with microphone access before submitting.')+'</p></div>';
     box.querySelector('.wc-fin').onclick=()=>{secs=Math.round((Date.now()-recordStart)/1000);all.forEach((w,i)=>{got[i]=-1;fails[i]=0;});summary();};
    };
   }

   /* 4) Stars and the words to practise */
   function summary(){if(recording){const clip=recording;recordingResult=clip.stop().then(x=>{audio=x;recordingError=clip.error();return x;});recording=null;}
    $('[data-bar]').style.width='100%';$('[data-c]').textContent='Done';W.forEach(x=>{x.classList.remove('cur','nx');x.onclick=null;});
    const scored=got.filter(g=>g!==-1&&g!==null),na=!scored.length,pct=na?null:Math.round(scored.reduce((a,b)=>a+b,0)/scored.length*100);
    const first=got.filter((g,i)=>g!==-1&&g!==null&&!fails[i]).length,after=got.filter((g,i)=>g>0&&fails[i]).length,miss=got.filter(g=>g===0).length;
    const hard=[...new Set(all.filter((w,i)=>fails[i]>0).map(w=>w.replace(/[^\p{L}\p{N}'-]/gu,'')))].filter(Boolean).slice(0,30);
    const resultValue=(na?'na':pct)+'|'+hard.join(' ');
    /* v205: the same "Great reading!" sheet as the BULIG reading check, with stars and the right / wrong tally */
    const tm=Math.floor(secs/60)+':'+String(secs%60).padStart(2,'0'),readable=!((na&&rec.dataset.kind!=='read')||practice),right=first+after;
    if(!practice)ov.classList.add('wc-sheetmode');
    box.innerHTML='<div class="wc-sum rk-sh-in">'+(practice?'<img src="'+IMG('cheer')+'" alt="">':'<span class="rk-sh-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg></span>')
     +'<h2>'+(practice?'Practice done!':(na?'Great reading!':(pct>=80?'Great reading!':'Good reading!')))+'</h2>'
     +(na||practice?'':'<div class="wc-stars">'+starHtml(stars(pct),3)+'<b class="wc-pct">'+pct+'%</b></div>')
     +(na||practice||typeof hlTotal!=='number'?'':'<p class="wc-hsum'+(hlTotal?'':' ok')+'">'+(hlTotal?'<b>'+hlTotal+'</b> '+(hlTotal===1?'heart':'hearts')+' lost on the missed words':'No hearts lost!')+'</p>')
     +(practice?'':'<div class="rk-sh-stats wc-tally"><div><b>'+tm+'</b><small>reading time</small></div>'+(na?'':'<div class="g"><b>'+right+'</b><small>words right</small></div><div class="r"><b>'+miss+'</b><small>words missed</small></div>')+'</div>')
     +(hard.length?'<p class="wc-hl">Words to practise (tap to hear):</p><div class="wc-chips">'+hard.map(h=>'<button type="button" class="wc-chip">'+svg.spk+esc(h)+'</button>').join('')+'</div>':'')
     +(practice?'':'<button type="button" class="rk-sh-play wc-listen" hidden><span class="rk-sh-pi">'+svg.spk+'</span>Listen to my reading</button><p class="rk-sh-note">Your teacher will listen to your reading.</p>')
     +(SENDMODE==='auto'&&readable?'<p class="wc-count rk-sh-count" aria-live="polite">Sending to your teacher in <b>5</b>…</p>':'')
     +'<div class="rk-sh-btns">'+(readable?'<button type="button" class="rk-sh-again wc-again2">'+svg.redo+'Read again</button>':'')+(hard.length&&!practice?'<button type="button" class="rk-sh-again wc-redo">'+svg.mic+'Practise missed words</button>':'')
     +'<button type="button" class="l1-big rk-send wc-read">'+(readable?'Send to my teacher '+svg.ok:svg.mic+'Now read it all')+'</button></div></div>';
    {const ra=box.querySelector('.wc-again2');if(ra)ra.onclick=()=>{close();open(false);};}
    box.querySelectorAll('.wc-chip').forEach(c=>c.onclick=()=>say(c.textContent,.55));
    const r=box.querySelector('.wc-redo');if(r)r.onclick=()=>{close();open(true);};
    let cd=null;const stopCount=()=>{if(cd){clearInterval(cd);cd=null;}const c=box.querySelector('.wc-count');if(c)c.remove();};
    box.querySelector('.wc-read').onclick=async()=>{stopCount();if(!practice&&!(na&&rec.dataset.kind!=='read'))sendAfter=true;const b=box.querySelector('.wc-read');b.disabled=true;b.textContent='Sending…';if(recordingResult)await recordingResult;if(!alive)return;if(!practice&&!audio){recordOnly(resultValue,recordingError);return;}if(!practice){audioField().value=audio;if(val)val.value=resultValue;store.set(key,resultValue);drawGate(resultValue);}close();if(!practice&&(audio||!na||rec.dataset.kind==='read')){saveReading(all.filter((w,i)=>got[i]>0).join(' '),secs,audio);return;}const m=rec.querySelector('[data-rec-go]');if(m)m.focus();rec.scrollIntoView({block:'center',behavior:'smooth'});};
    if(!na&&!practice)Promise.resolve(recordingResult).then(()=>{if(alive)say(pct>=80?'Great job! Your reading is done.':'Good work! Your reading is done.',.85);});
    /* listen back to the recording */
    const lb=box.querySelector('.wc-listen');if(lb)Promise.resolve(recordingResult).then(()=>{if(!alive||!audio)return;lb.hidden=false;let au=null;lb.onclick=()=>{hush();if(!au){au=new Audio(audio);au.onended=()=>lb.classList.remove('on');}if(!au.paused){au.pause();lb.classList.remove('on');return;}au.currentTime=0;au.play().then(()=>lb.classList.add('on')).catch(()=>{});};});
    /* A: send by itself after a short countdown (any tap on the sheet stops it) */
    const cEl=box.querySelector('.wc-count');if(cEl){let n=5;cd=setInterval(()=>{if(!alive){stopCount();return;}n--;const b=cEl.querySelector('b');if(b)b.textContent=String(n);if(n<=0){stopCount();const r=box.querySelector('.wc-read');if(r&&!r.disabled)r.click();}},1000);
     box.querySelectorAll('.wc-redo,.wc-chip,.wc-listen,.wc-again2').forEach(x=>x.addEventListener('click',stopCount));}}

   /* v203: the Word Coach score is kept, but the recording for the teacher was lost. iPhones and iPads use one microphone:
      while the speech checker listens, iOS can cut the recording. So the pupil reads it once more with only the recorder on
      (no speech checker, no Max voice), and that recording goes to the teacher with the score. */
   function recordOnly(resultValue,why){if(!alive)return;hush();gen++;
    $('[data-c]').textContent='Record for your teacher';$('[data-bar]').style.width='100%';W.forEach(x=>{x.classList.remove('cur','nx','dim');x.onclick=null;});
    box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><p class="wc-say"><b>Your score is saved!</b> Now read it all once more so your teacher can hear you.</p><p class="wc-note" data-audio-error></p><div class="wc-row"><button type="button" class="btn primary wc-rec-start">'+svg.mic+'Start recording</button></div></div>';
    box.querySelector('[data-audio-error]').textContent=(why||'The recording stopped while Word Coach was listening.')+' Your score is kept.';
    box.querySelector('.wc-rec-start').onclick=async()=>{const st=box.querySelector('.wc-rec-start');st.disabled=true;st.textContent='Starting the microphone…';
     const clip=await captureReading(true);if(!alive){if(clip)clip.cancel();return;}
     if(!clip){st.disabled=false;st.innerHTML=svg.mic+'Try again';box.querySelector('[data-audio-error]').textContent='BULIG could not open the microphone. In Settings, allow the microphone for this website, then try again.';return;}
     recording=clip;const t1=Date.now();
     box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><p class="wc-say"><b>Recording…</b> Read all the words out loud.</p><div class="wc-row"><span class="wc-mic">'+svg.mic+'</span><span class="wc-wave"><i></i><i></i><i></i><i></i><i></i><i></i></span><b>Recording for your teacher</b></div><div class="wc-row"><button type="button" class="btn primary wc-rec-stop">'+svg.ok+'I finished reading</button></div></div>';
     box.querySelector('.wc-rec-stop').onclick=async()=>{const b=box.querySelector('.wc-rec-stop');b.disabled=true;b.textContent='Saving recording…';recording=null;
      const a=await clip.stop();const err=clip.error();if(!alive)return;
      if(!a){recordOnly(resultValue,err||'The recording was empty.');return;}
      secs=secs||Math.round((Date.now()-t1)/1000);audio=a;audioField().value=a;if(val)val.value=resultValue;store.set(key,resultValue);drawGate(resultValue);close();saveReading(all.filter((w,i)=>got[i]>0).join(' '),secs,a);};};
   }

   if(practice){const prev=(store.get(key)||'').split('|')[1]||'';const hard=prev.split(' ').filter(Boolean);
    if(hard.length){all.splice(0,all.length,...hard);txt.innerHTML=all.map((w,i)=>'<span class="wc-w">'+esc(w)+'</span>').join(' ');W.splice(0,W.length,...txt.querySelectorAll('.wc-w'));
     all.forEach((w,i)=>{fails[i]=1;got[i]=null;});fix(all.map((w,i)=>i));return;}}
   readAll();
  }
 });
})();

