/* Word Coach (v184, app/coach.php). Every reading recorder with data-wc gets Max the coach first:
   1) Max reads the whole text while the words light up (Skip goes straight on);
   2) the pupil reads it all aloud (Max does not say it first); each word turns green when BULIG hears it said right
      (this is the pupil's reading: "Save my reading" sends it, no need to record it again);
   3) the words not said right, one at a time: 2nd try 2/3 point, 3rd try 1/3, then 0 (the word goes on the practise list).
      A word right the first time is worth 1 point;
   4) the stars and the words to practise, then the microphone for the real reading opens.
   The result goes in the hidden field wc[n] as "percent|word word" ("na|" when the device cannot listen: no score). */
(()=>{
 'use strict';
 const recs=[...document.querySelectorAll('[data-wc]')];if(!recs.length)return;
 // Safari uses one microphone job at a time: keep real audio, leave pronunciation to the teacher there.
 const APPLE=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1)||(/Safari\//.test(navigator.userAgent)&&!/Chrome|Chromium|CriOS|FxiOS|Edg|OPR|Android/.test(navigator.userAgent));
 const Rec=APPLE?null:(window.SpeechRecognition||window.webkitSpeechRecognition),synth=window.speechSynthesis||null;
 // Record only the pupil phase. Never record Max's opening demonstration or continue after closing.
 async function captureReading(){
  let media=null,mr=null,chunks=[],timer=null,expired=false,ended=false;
  const cleanup=()=>{clearTimeout(timer);if(media)media.getTracks().forEach(t=>t.stop());};
  try{
   if(!navigator.mediaDevices||!window.MediaRecorder)return null;
   media=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});
   const type=['audio/webm;codecs=opus','audio/mp4','audio/webm','audio/ogg;codecs=opus'].find(t=>MediaRecorder.isTypeSupported(t));
   mr=new MediaRecorder(media,type?{mimeType:type,audioBitsPerSecond:24000}:{audioBitsPerSecond:24000});
   let resolve;const result=new Promise(r=>resolve=r);
   mr.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data);};
   mr.onerror=()=>{expired=true;cleanup();resolve('');};
   mr.onstop=()=>{cleanup();const blob=new Blob(chunks,{type:(mr.mimeType||'audio/webm').split(';')[0]});if(expired||blob.size<200||blob.size>6000000){resolve('');return;}const f=new FileReader();f.onload=()=>resolve(String(f.result));f.onerror=()=>resolve('');f.readAsDataURL(blob);};
   mr.start(500);timer=setTimeout(()=>{expired=true;if(mr.state!=='inactive')mr.stop();},240000);
   return {pause(){if(mr.state==='recording')mr.pause();},resume(){if(mr.state==='paused')mr.resume();},stop(){if(!ended){ended=true;if(mr.state!=='inactive')mr.stop();else cleanup();}return result;},cancel(){expired=true;this.stop();}};
  }catch(e){cleanup();return null;}
 }

 const IMG=p=>'assets/images/characters/max-'+p+'.webp?v=2';
 const PTS=[1,2/3,1/3],TRIES=3,RATE=.65;   // Max reads slowly
 const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const norm=w=>String(w).toLowerCase().replace(/[’‘`]/g,"'").replace(/[^\p{L}\p{N}']+/gu,'').replace(/^'+|'+$/g,'');
 const store={get(k){try{return sessionStorage.getItem(k);}catch(e){return null;}},set(k,v){try{sessionStorage.setItem(k,v);}catch(e){}}};
 const actId=(document.querySelector('#activity-form [name="activity_id"]')||{}).value||location.search;
 const svg={mic:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>',
  spk:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg>',
  slow:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/></svg>',
  skip:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 5l8 7-8 7zM16 5v14"/></svg>',
  close:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
  ok:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>'};

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
 const near=(h,t)=>h===t||(t.length>=5&&lev(h,t)<=1);
 const heardOk=(alts,unit)=>{const want=unit.map(norm).filter(Boolean);if(!want.length)return true;
  for(const alt of alts){const got=alt.split(/\s+/).map(norm).filter(Boolean);
   if(want.length===1){if(got.some(g=>near(g,want[0])))return true;continue;}
   const pool=[...got];let hit=0;for(const w of want){const i=pool.findIndex(g=>near(g,w));if(i>=0){hit++;pool.splice(i,1);}}
   if(hit/want.length>=.8)return true;}
  return false;};

 /* One listen: resolves with the transcripts, '' when nothing was heard, or null when this device cannot listen */
 const listen=()=>new Promise(res=>{if(!Rec){res(null);return;}let r;try{r=new Rec();}catch(e){res(null);return;}
  r.lang='en-US';r.interimResults=false;r.maxAlternatives=5;r.continuous=false;let out=[],done=false;
  const fin=v=>{if(done)return;done=true;try{r.abort();}catch(e){}res(v);};
  r.onresult=e=>{for(const x of e.results)for(const a of x)out.push(a.transcript);};
  r.onerror=e=>{fin(['not-allowed','service-not-allowed','network','audio-capture','language-not-supported'].includes(e.error)?null:'');};
  r.onend=()=>fin(out.length?out:'');
  try{r.start();}catch(e){fin(null);}setTimeout(()=>{try{r.stop();}catch(e){}},9000);});


 /* Listen while the pupil reads: onText(all words heard in this listening session), onNew() when a new session starts
    (Chrome stops after a pause, so BULIG starts again), onStop() when this device cannot listen any more. */
 const listenAll=(onText,onNew,onStop)=>{let on=true,r=null;
  const start=()=>{if(!on)return;try{r=new Rec();}catch(e){on=false;onStop();return;}r.lang='en-US';r.continuous=true;r.interimResults=true;r.maxAlternatives=1;onNew();
   r.onresult=e=>{let t='';for(let i=0;i<e.results.length;i++)t+=' '+e.results[i][0].transcript;onText(t);};
   r.onerror=e=>{if(['not-allowed','service-not-allowed','audio-capture','language-not-supported','network'].includes(e.error)){on=false;onStop();}};
   r.onend=()=>{if(on)setTimeout(start,120);};try{r.start();}catch(e){on=false;onStop();}};
  start();return {stop(){on=false;try{r&&r.abort();}catch(e){}}};};
 const words=t=>String(t).split(/\s+/).filter(w=>/[\p{L}\p{N}]/u.test(w));
 const lines=t=>{const out=[];let cur=[];for(const w of words(t)){cur.push(w);if(/[.!?]["”’)]*$/.test(w)||cur.length>=12){out.push(cur);cur=[];}}if(cur.length)out.push(cur);return out;};
 const stars=p=>p>=95?3:p>=80?2:p>=50?1:0;
 const starHtml=(n,of)=>'<span class="ac-stars st'+of+'" role="img" aria-label="'+n+' of '+of+' stars">'+'<i></i>'.repeat(n)+'<i class="off"></i>'.repeat(of-n)+'</span>';

 recs.forEach((rec,ix)=>{
  const text=rec.dataset.wc||'';if(!words(text).length)return;
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

  function open(practice){
   hush();const all=words(text);let recording=null,audio='',recordingResult=null;
   const audioField=()=>{let f=rec.querySelector('[data-coach-audio]');if(!f){f=document.createElement('input');f.type='hidden';f.name='coach_audio['+(val?val.name.match(/\d+/)[0]:0)+']';f.dataset.coachAudio='';rec.append(f);}return f;};
   if(!practice){audioField().value='';if(val)val.value='';}
   const ov=document.createElement('div');ov.className='wc-ov';ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-label','Word Coach with Max');
   ov.innerHTML='<div class="wc-panel"><button type="button" class="wc-x" aria-label="Close">'+svg.close+'</button><div class="wc-top"><span class="wc-eb">WORD COACH</span><div class="wc-prog"><span data-c></span><div class="wc-bar"><i data-bar></i></div></div></div><div class="wc-txt" data-txt></div><div class="wc-box" data-box></div></div>';
   document.body.appendChild(ov);document.body.classList.add('wc-open');
   const $=s=>ov.querySelector(s),txt=$('[data-txt]'),box=$('[data-box]');
   txt.innerHTML=all.map((w,i)=>'<span class="wc-w" data-i="'+i+'">'+esc(w)+'</span>').join(' ');
   const W=[...txt.querySelectorAll('.wc-w')];let alive=true;
   let gen=0;const say=(t,r,cb,b)=>{const g=++gen;hush();if(recording)recording.pause();speak(t,r,()=>{if(g===gen&&alive){if(recording)recording.resume();if(cb)cb();}},b);};   // only the newest voice line moves the coach on
   const close=()=>{alive=false;gen++;hush();if(recording)recording.cancel();window.removeEventListener('pagehide',close);if(stream)stream.stop();ov.remove();document.body.classList.remove('wc-open');};
   $('.wc-x').addEventListener('click',close);window.addEventListener('pagehide',close,{once:true});
   const mark=(i0,i1,cls)=>{for(let i=i0;i<=i1;i++){W[i].classList.remove('cur','dim');if(cls)W[i].classList.add(cls);}};
   const focus=(i0,i1)=>{W.forEach(x=>x.classList.remove('cur'));for(let i=i0;i<=i1;i++)W[i].classList.add('cur');W[i0].scrollIntoView({block:'nearest',behavior:'smooth'});};
   const prog=(n,of,label)=>{$('[data-c]').textContent=label+' '+Math.min(n+1,of)+' of '+of;$('[data-bar]').style.width=Math.round(n/of*100)+'%';};

   /* 1) Max reads it all, slowly (sentence by sentence; the words light up). Skip goes on. */
   const readAll=()=>{W.forEach(x=>x.classList.add('dim'));
    box.innerHTML='<img src="'+IMG('hello')+'" alt=""><div><div class="wc-step"><span class="on">1 Max reads</span><span>2 You read</span><span>3 Fix the missed words</span></div><p class="wc-say">Listen and follow the words. Max reads it first.</p><div class="wc-row"><button type="button" class="btn wc-skip">'+svg.skip+'Skip, I am ready</button></div></div>';
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
   const N=all.length,T=all.map(norm),S=all.map(()=>null),fails=all.map(()=>0),got=all.map(()=>null);let stream=null,t0=0,secs=0;
   const paint=(i,cls)=>{W[i].classList.remove('cur','dim','ok','mid','bad','miss','nx');if(cls)W[i].classList.add(cls);};
   async function pass(){
    box.innerHTML='<p class="wc-say">Preparing your reading recording… Your teacher can listen to this attempt.</p>';
    if(!practice)recording=await captureReading();if(!alive){if(recording)recording.cancel();return;}
    if(!Rec){noListen();return;}
    let p=0,sessBase=0,last=Date.now(),hintT=null,ended=false;t0=Date.now();W.forEach((x,i)=>paint(i,''));
    box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><div class="wc-step"><span>1 Max reads</span><span class="on">2 You read</span><span>3 Fix the missed words</span></div><p class="wc-say"><b>Your turn!</b> Read it out loud. Each word turns green when you say it right.</p><div class="wc-row"><span class="wc-mic">'+svg.mic+'</span><span class="wc-wave"><i></i><i></i><i></i><i></i><i></i><i></i></span><b data-st>Listening…</b><span data-recording-note></span></div><p class="wc-hint" data-hint hidden></p><div class="wc-row"><button type="button" class="btn ghost wc-skipw">'+svg.skip+'Skip this word</button><button type="button" class="btn primary wc-done">'+svg.ok+'I am done</button></div></div>';
    box.querySelector('[data-recording-note]').textContent=recording?'Recording for your teacher':'Audio unavailable — teacher review needed';
    const hint=box.querySelector('[data-hint]');
    const show=()=>{for(let i=0;i<N;i++)if(!S[i])paint(i,i===p?'nx':'');if(p<N)W[p].scrollIntoView({block:'nearest',behavior:'smooth'});prog(Math.min(p,N-1),N,'Word');$('[data-bar]').style.width=Math.round(p/N*100)+'%';};
    const set=(i,v)=>{S[i]=v;paint(i,v==='ok'?'ok':'miss');};
    const finish=()=>{if(ended)return;ended=true;secs=Math.round((Date.now()-t0)/1000);clearInterval(hintT);if(stream)stream.stop();for(let i=0;i<N;i++)if(S[i]!=='ok')set(i,'miss');
     for(let i=0;i<N;i++)if(S[i]==='ok')got[i]=1;else fails[i]=1;setTimeout(()=>alive&&fix(),700);};
    const align=heard=>{if(ended)return;const toks=heard.split(/\s+/).map(norm).filter(Boolean);let q=sessBase;const st={};
     for(const t of toks){for(let k=0;k<=3&&q+k<N;k++){if(near(t,T[q+k])){for(let m=q;m<q+k;m++)st[m]='miss';st[q+k]='ok';q=q+k+1;break;}}}
     if(q<p)return;for(let i=sessBase;i<q;i++)if(st[i]&&S[i]!=='ok')set(i,st[i]);else if(st[i]==='ok')set(i,'ok');
     if(q>p){p=q;last=Date.now();hint.hidden=true;}show();if(p>=N)finish();};
    stream=listenAll(align,()=>{sessBase=p;},()=>{if(ended)return;stream=null;if(!p){noListen();return;}finish();});
    box.querySelector('.wc-skipw').onclick=()=>{if(p<N){set(p,'miss');p++;sessBase=p;last=Date.now();hint.hidden=true;show();if(p>=N)finish();}};
    box.querySelector('.wc-done').onclick=finish;
    hintT=setInterval(()=>{if(!live()||ended){clearInterval(hintT);return;}if(Date.now()-last>7000&&p<N){hint.hidden=false;hint.innerHTML='Stuck on <b>“'+esc(all[p])+'”</b>? Tap it to hear it, or tap <b>Skip this word</b>.';}},1000);
    W.forEach((x,i)=>{x.onclick=()=>{if(ended)return;say(all[i],.6);if(S[i]!=='ok'&&i>=p){set(i,'miss');if(i===p){p++;sessBase=p;show();if(p>=N)finish();}}};});
    show();}
   const live=()=>alive;

   /* 3) The missed words, one at a time: the pupil says it again (no help first). 2nd try 2/3, 3rd try 1/3, then 0. */
   function fix(list){
    W.forEach(x=>x.onclick=null);
    const todo=list||all.map((w,i)=>i).filter(i=>S[i]!=='ok');let at=0;
    const step='<div class="wc-step"><span>1 Max reads</span><span>2 You read</span><span class="on">3 Fix the missed words</span></div>';
    const big=i=>'<div class="wc-big">'+esc(all[i])+'</div>';
    const tries=i=>'<span class="wc-tries">'+[0,1,2].map(k=>'<i class="'+(k<fails[i]?'x':'')+'"></i>').join('')+'</span>';
    const nextWord=()=>{if(!alive)return;if(at>=todo.length){summary();return;}const i=todo[at];focus(i,i);prog(at,todo.length,'Missed word');ask(i);};
    const ask=i=>{if(!Rec){got[i]=null;at++;nextWord();return;}
     box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<p class="wc-say">Read this word again.</p><div class="wc-row"><span class="wc-mic">'+svg.mic+'</span><span class="wc-wave"><i></i><i></i><i></i><i></i><i></i><i></i></span><b>Listening…</b>'+tries(i)+'</div></div>';
     listen().then(alts=>{if(!alive)return;
      if(alts===null||alts===''){box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<div class="wc-res y">I did not hear you. Say it a little louder.</div><div class="wc-row">'+tries(i)+'<button type="button" class="btn primary wc-again">'+svg.mic+'Say it</button></div></div>';box.querySelector('.wc-again').onclick=()=>ask(i);return;}
      if(heardOk(alts,[all[i]])){got[i]=practice?1:PTS[fails[i]]||0;paint(i,'mid');box.innerHTML='<img src="'+IMG('thumbs')+'" alt=""><div>'+step+big(i)+'<div class="wc-res g">Yes! You got it.</div></div>';at++;setTimeout(nextWord,1000);return;}
      fails[i]++;const heard=String(alts[0]||'').trim();
      if(fails[i]>=TRIES){got[i]=0;paint(i,'bad');box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<div class="wc-res y">Good try! Listen: <b>'+esc(all[i])+'</b>. Practise it later.</div>'+tries(i)+'</div>';say(all[i],.55,()=>{at++;setTimeout(nextWord,500);});return;}
      box.innerHTML='<img src="'+IMG('point')+'" alt=""><div>'+step+big(i)+'<div class="wc-res y">Almost!'+(heard?' I heard “'+esc(heard)+'”.':'')+' Try again.</div><div class="wc-row">'+tries(i)+'<span class="wc-tn">try '+(fails[i]+1)+' of '+TRIES+'</span><button type="button" class="btn ghost wc-slow">'+svg.slow+'Hear it</button><button type="button" class="btn primary wc-again">'+svg.mic+'Say it again</button></div></div>';
      box.querySelector('.wc-slow').onclick=()=>say(all[i],.5);box.querySelector('.wc-again').onclick=()=>{gen++;hush();ask(i);};});};
    nextWord();}

   /* A device that cannot listen: the pupil reads along and taps "I finished" (no score). */
   function noListen(){const recordStart=Date.now();W.forEach((x,i)=>paint(i,''));
    box.innerHTML='<img src="'+IMG('mic')+'" alt=""><div><p class="wc-say"><b>Your turn!</b> Read it all out loud. Tap a word to hear it.</p><div class="wc-row"><button type="button" class="btn primary wc-fin">'+svg.ok+'I finished reading</button></div><p class="wc-note">Your device cannot score pronunciation while recording. Your teacher will review the audio. If recording is unavailable, a supervised rereading is needed.</p></div>';
    W.forEach((x,i)=>x.onclick=()=>say(all[i],.6));box.querySelector('.wc-fin').onclick=()=>{secs=Math.round((Date.now()-recordStart)/1000);W.forEach((x,i)=>paint(i,'ok'));all.forEach((w,i)=>got[i]=-1);summary();};}

   /* 4) Stars and the words to practise */
   function summary(){if(recording){recordingResult=recording.stop().then(x=>{audio=x;return x;});recording=null;}
    $('[data-bar]').style.width='100%';$('[data-c]').textContent='Done';W.forEach(x=>{x.classList.remove('cur','nx');x.onclick=null;});
    const scored=got.filter(g=>g!==-1&&g!==null),na=!scored.length,pct=na?null:Math.round(scored.reduce((a,b)=>a+b,0)/scored.length*100);
    const first=got.filter((g,i)=>g!==-1&&g!==null&&!fails[i]).length,after=got.filter((g,i)=>g>0&&fails[i]).length,miss=got.filter(g=>g===0).length;
    const hard=[...new Set(all.filter((w,i)=>fails[i]>0).map(w=>w.replace(/[^\p{L}\p{N}'-]/gu,'')))].filter(Boolean).slice(0,30);
    if(!practice){const v=(na?'na':pct)+'|'+hard.join(' ');if(val)val.value=v;store.set(key,v);drawGate(v);}
    box.innerHTML='<div class="wc-sum"><img src="'+IMG('cheer')+'" alt=""><h3>'+(practice?'Practice done!':'You read all '+N+' words!')+'</h3>'+(na||practice?'':starHtml(stars(pct),3)+'<p class="wc-pct">'+pct+'%</p>')
     +(na||practice?'':'<div class="wc-cnt"><div class="g"><b>'+first+'</b>right the first time</div><div class="y"><b>'+after+'</b>right after trying again</div><div class="r"><b>'+miss+'</b>to practise</div></div>')
     +(hard.length?'<p class="wc-hl">Words to practise (tap to hear):</p><div class="wc-chips">'+hard.map(h=>'<button type="button" class="wc-chip">'+svg.spk+esc(h)+'</button>').join('')+'</div>':'')
     +'<div class="wc-row c">'+(hard.length&&!practice?'<button type="button" class="btn ghost wc-redo">Practise these again</button>':'')+'<button type="button" class="btn primary wc-read">'+((na&&rec.dataset.kind!=='read')||practice?svg.mic+'Now read it all':svg.ok+'Save my reading')+'</button></div></div>';
    box.querySelectorAll('.wc-chip').forEach(c=>c.onclick=()=>say(c.textContent,.55));
    const r=box.querySelector('.wc-redo');if(r)r.onclick=()=>{close();open(true);};
    box.querySelector('.wc-read').onclick=async()=>{const b=box.querySelector('.wc-read');b.disabled=true;b.textContent='Saving recording…';if(recordingResult)await recordingResult;if(!alive)return;if(!practice)audioField().value=audio;close();if(!practice&&(audio||!na||rec.dataset.kind==='read')){saveReading(all.filter((w,i)=>got[i]>0).join(' '),secs,audio);return;}const m=rec.querySelector('[data-rec-go]');if(m)m.focus();rec.scrollIntoView({block:'center',behavior:'smooth'});};
    if(!na&&!practice)say(pct>=80?'Great job! Your reading is done.':'Good work! Your reading is done.',.85);}

   if(practice){const prev=(store.get(key)||'').split('|')[1]||'';const hard=prev.split(' ').filter(Boolean);
    if(hard.length){all.splice(0,all.length,...hard);txt.innerHTML=all.map((w,i)=>'<span class="wc-w">'+esc(w)+'</span>').join(' ');W.splice(0,W.length,...txt.querySelectorAll('.wc-w'));
     all.forEach((w,i)=>{fails[i]=1;got[i]=null;});fix(all.map((w,i)=>i));return;}}
   readAll();
  }
 });
})();

