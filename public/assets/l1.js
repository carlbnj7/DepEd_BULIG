/* BULIG Level 1 (oral language)
   - Speaker buttons read the question aloud; poem and tongue-twister words light up while they are read (Slow / Fast).
   - Speaking answers: tap the big microphone, speak, tap again to stop, listen to it, then Try again or Send.
     The recording goes into the form, so it is saved like any answer (and kept on the device when offline).
     No microphone, or not allowed: "Done".
   - Class Demo (Level 1): projector slides with Previous / Next, arrow keys, Listen, Full screen and lesson jump.
   - Lesson 9 poems: choose a poem, then one picture and one line at a time; the words light up as they are read.
     Pupils: BULIG reads one line, the pupil reads the next. Class Demo: the class reads together (Girls, Boys, Everyone).
   - Lesson 11 word game: BULIG says a word, the pupil says a word friend. */
(function(){
 'use strict';
 const $=(s,r)=>(r||document).querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 if(!$('.l1-app,#l1-demo'))return;
 const synth=window.speechSynthesis;
 const voice=()=>{if(!synth)return null;const vs=synth.getVoices();let pref=null;try{pref=localStorage.getItem('bulig-voice');}catch(e){}
  return vs.find(v=>v.voiceURI===pref)||vs.find(v=>/^en/i.test(v.lang)&&/female|samantha|zira|susan|karen|aria|jenny|ava|hazel/i.test(v.name))||vs.find(v=>/^en/i.test(v.lang))||null;};
 if(synth)synth.addEventListener('voiceschanged',()=>{});
 let run=0,lit=null;
 function stopSay(){run++;if(synth)synth.cancel();$$('.l1-spk.on,.l1-chip.on,.l1d-lis.on').forEach(b=>{b.classList.remove('on');b.setAttribute('aria-pressed','false');});clearWords();}
 function clearWords(){if(lit){clearInterval(lit);lit=null;}$$('.l1-tw .w.on,.l1-tw .w.done').forEach(w=>w.classList.remove('on','done'));}
 /* Read text aloud. If the box has word spans, they light up one by one. */
 function say(text,btn,rate,box){
  if(!synth||!text)return;const was=btn&&btn.classList.contains('on');stopSay();if(was)return;
  const my=++run,u=new SpeechSynthesisUtterance(text),v=voice();if(v)u.voice=v;u.lang='en-US';u.rate=rate||.85;u.pitch=1.05;
  if(btn){btn.classList.add('on');btn.setAttribute('aria-pressed','true');}
  const words=box?$$('.w',box):[];let starts=[],pos=0,heard=false;
  if(words.length){words.forEach(w=>{starts.push(pos);pos+=w.textContent.length+1;});}
  const mark=i=>{words.forEach((w,j)=>{w.classList.toggle('done',j<i);w.classList.toggle('on',j===i);});};
  u.onstart=()=>{if(my!==run||!words.length)return;mark(0);
   /* Some voices never report words: then the words move on a steady beat. */
   setTimeout(()=>{if(my!==run||heard||lit)return;let i=0;lit=setInterval(()=>{if(my!==run){clearInterval(lit);lit=null;return;}i++;if(i>=words.length){clearInterval(lit);lit=null;return;}mark(i);},Math.round(430/(u.rate||1)));},700);};
  u.onboundary=e=>{if(my!==run||!words.length||(e.name&&e.name!=='word'))return;heard=true;let i=0;while(i+1<starts.length&&starts[i+1]<=e.charIndex)i++;mark(i);};
  const end=()=>{if(my!==run)return;if(btn){btn.classList.remove('on');btn.setAttribute('aria-pressed','false');}if(lit){clearInterval(lit);lit=null;}if(words.length){words.forEach(w=>{w.classList.remove('on');w.classList.add('done');});setTimeout(()=>{if(my===run)clearWords();},900);}};
  u.onend=end;u.onerror=end;synth.speak(u);
 }
 window.addEventListener('pagehide',()=>{if(synth)synth.cancel();});
 document.addEventListener('click',e=>{const b=e.target.closest&&e.target.closest('[data-say]');if(b&&!b.closest('#l1-demo')){e.preventDefault();say(b.dataset.say,b,+b.dataset.rate||.85);}
  const c=e.target.closest&&e.target.closest('[data-say-words]');if(c){e.preventDefault();const box=$('.l1-tw');if(box)say($$('.w',box).map(w=>w.textContent).join(' '),c,+c.dataset.sayWords||.85,box);}});

 /* ---------------- speaking answers ---------------- */
 const rec=$('[data-l1-rec]'),form=$('#activity-form');
 if(rec&&form){
  const go=$('[data-rec-go]',rec),label=$('[data-rec-label]',rec),sub=$('[data-rec-sub]',rec),playBox=$('[data-rec-play]',rec),listenB=$('[data-rec-listen]',rec),bar=$('.l1-bar i',rec),len=$('[data-rec-len]',rec),heardB=$('[data-rec-heard]',rec);
  const again=$('[data-rec-again]'),send=$('#submit-answer'),audioIn=$('#l1-audio'),heardIn=$('#l1-heard'),startLabel=label.textContent;
  const MAX=+rec.dataset.max||60,fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  let mr=null,stream=null,chunks=[],t0=0,tick=null,secs=0,url='',player=null,state='idle';
 /* Microphone: ask with clean-sound settings, then plain audio (some iPhones refuse the first). */
 async function getMic(){const md=navigator.mediaDevices;try{return await md.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});}
  catch(e){if(e&&(e.name==='NotAllowedError'||e.name==='SecurityError'))throw e;await new Promise(r=>setTimeout(r,350));return await md.getUserMedia({audio:true});}}
 function micMsg(e){const n=e&&e.name||'';
  if(n==='NotAllowedError'||n==='SecurityError')return 'The microphone is blocked. Allow it in the browser settings (iPhone: tap aA, then Website Settings, then Microphone: Allow), then tap the microphone again.';
  if(n==='NotReadableError'||n==='AbortError'||n==='TrackStartError')return 'The microphone is busy. Close other apps using it (calls, video, voice notes), then tap the microphone again.';
  if(n==='NotFoundError'||n==='DevicesNotFoundError')return 'No microphone was found. Tap the microphone to try again.';
  return 'The microphone could not start. Tap it to try again.';}
  const can=!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia&&window.MediaRecorder);
  function fallback(msg){state='off';go.hidden=true;label.textContent='Say your answer out loud to your teacher.';sub.textContent=msg;heardB.hidden=false;}
  if(!can)fallback('This device cannot record here.');
  function ui(s){state=s;rec.dataset.state=s;go.classList.toggle('on',s==='rec');go.setAttribute('aria-label',s==='rec'?'Stop recording':'Tap and say your answer');
   go.hidden=s==='done';playBox.hidden=s!=='done';if(again)again.hidden=s!=='done';if(send)send.hidden=s!=='done';
   if(s==='idle'){label.textContent=startLabel;sub.textContent='';}
   if(s==='rec'){label.textContent='0:00';sub.textContent=rec.classList.contains('l4-rec')?'Read now. Tap the button when you finish.':'Speak now. Tap the button to stop.';}
   if(s==='done'){label.textContent='Listen to your answer';sub.textContent='Happy with it? Tap Send. Or tap Try again.';}}
  function reset(){if(player){player.pause();player=null;}if(url){URL.revokeObjectURL(url);url='';}audioIn.value='';heardIn.value='';bar.style.setProperty('--p','0');}
  async function start(){if(state==='starting'||state==='rec')return;state='starting';rec.dataset.state='starting';go.classList.add('busy');stopSay();reset();
   try{stream=await getMic();}
   catch(err){go.classList.remove('busy');ui('idle');label.textContent='Tap the microphone to try again.';sub.textContent=micMsg(err);heardB.hidden=false;return;}
   const type=['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/ogg;codecs=opus'].find(t=>MediaRecorder.isTypeSupported&&MediaRecorder.isTypeSupported(t));
   try{mr=new MediaRecorder(stream,type?{mimeType:type,audioBitsPerSecond:32000}:undefined);}catch(e){mr=new MediaRecorder(stream);}
   chunks=[];mr.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data);};mr.onstop=finish;mr.start(500);
   go.classList.remove('busy');t0=Date.now();ui('rec');tick=setInterval(()=>{secs=(Date.now()-t0)/1000;label.textContent=fmt(secs);if(secs>=MAX)stop();},250);}
  function stop(){clearInterval(tick);secs=(Date.now()-t0)/1000;if(mr&&mr.state!=='inactive')mr.stop();if(stream)stream.getTracks().forEach(t=>t.stop());}
  function finish(){const kind=((mr&&mr.mimeType)||'audio/webm').split(';')[0].replace('video/','audio/'),blob=new Blob(chunks,{type:kind});
   if(blob.size<800||secs<.6){ui('idle');sub.textContent='I could not hear anything. Tap the microphone and try again.';return;}
   url=URL.createObjectURL(blob);len.textContent=fmt(secs);const sIn=$('#l1-secs');if(sIn)sIn.value=String(Math.round(secs));
   const r=new FileReader();r.onload=()=>{audioIn.value=String(r.result);ui('done');if(send)send.focus({preventScroll:true});};
   r.onerror=()=>{ui('idle');sub.textContent='The recording could not be kept. Please try again.';};r.readAsDataURL(blob);}
  go.addEventListener('click',()=>{if(state==='rec'){if(Date.now()-t0<700)return;stop();}else if(state==='idle')start();});
  listenB.addEventListener('click',()=>{if(!url)return;stopSay();
   if(player&&!player.paused){player.pause();return;}
   if(!player){player=new Audio(url);player.addEventListener('timeupdate',()=>bar.style.setProperty('--p',String(Math.min(1,player.currentTime/(secs||1)))));
    player.addEventListener('ended',()=>{listenB.classList.remove('on');bar.style.setProperty('--p','1');});player.addEventListener('pause',()=>listenB.classList.remove('on'));}
   player.currentTime=0;player.play().then(()=>listenB.classList.add('on')).catch(()=>{});});
  if(again)again.addEventListener('click',()=>{ui('idle');start();});
  heardB.addEventListener('click',()=>{audioIn.value='';heardIn.value='1';form.requestSubmit();});
  /* After the answer is saved: only Next is left. */
  const fb=$('#answer-feedback');
  if(fb)new MutationObserver(()=>{if(fb.classList.contains('success')){if(again)again.hidden=true;go.hidden=true;heardB.hidden=true;sub.textContent='';label.textContent=state==='done'?'Your answer':'';}}).observe(fb,{attributes:true,attributeFilter:['class']});
 }

 /* ---------------- Lesson 9 poems ---------------- */
 const SV=d=>'<svg class="l1i" viewBox="0 0 24 24" aria-hidden="true">'+d+'</svg>';
 const IC={prev:SV('<path d="M19 12H5M11 6l-6 6 6 6"/>'),next:SV('<path d="M5 12h14M13 6l6 6-6 6"/>'),play:SV('<path d="M8 5v14l11-7z"/>'),pause:SV('<path d="M8 5v14M16 5v14"/>'),mic:SV('<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>'),ok:SV('<path d="M20 6 9 17l-5-5"/>')};
 const esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const PIC=(k,n)=>'assets/images/level1/lesson09/poem-'+k+'-'+n+'.webp';
 const mark=(ws,i)=>ws.forEach((w,j)=>{w.classList.toggle('done',j<i);w.classList.toggle('on',j===i);});
 function initPoems(root){
  $$('[data-pk]',root).forEach(pk=>{if(pk.dataset.ready)return;pk.dataset.ready='1';
   let poems={};try{poems=JSON.parse(pk.dataset.pkPoems||'{}');}catch(e){return;}
   const cls=pk.dataset.pk==='class',pick=$('.pk-pick',pk),pl=$('[data-pk-player]',pk),done=new Set();
   let key=null,idx=0,rate=.85,playing=false,my=0,beat=null,who='Everyone';
   const turn=i=>cls?'class':(i%2===0?'bulig':'you');
   const clearBeat=()=>{if(beat){clearInterval(beat);beat=null;}};
   function stop(){my++;clearBeat();if(synth)synth.cancel();playing=false;paint();}
   function paint(){const b=$('[data-pk-play]',pl);if(!b)return;b.innerHTML=(playing?IC.pause:IC.play)+'<span>'+(playing?'Pause':(cls?'Listen':'Play'))+'</span>';b.setAttribute('aria-pressed',String(playing));
    $$('[data-pk-rate]',pl).forEach(x=>x.classList.toggle('on',+x.dataset.pkRate===rate));$$('[data-pk-who]',pl).forEach(x=>x.classList.toggle('on',x.dataset.pkWho===who));}
   function open(k){stop();key=k;idx=0;pick.hidden=true;pl.hidden=false;build();const f=$('[data-pk-play]',pl);if(f)f.focus({preventScroll:true});pk.scrollIntoView({block:'nearest',behavior:'smooth'});}
   function close(){stop();key=null;pl.hidden=true;pick.hidden=false;$$('.pk-poem',pick).forEach(b=>b.classList.toggle('done',done.has(b.dataset.pkOpen)));const b=$('[data-pk-open]',pick);if(b)b.focus({preventScroll:true});}
   function build(){const p=poems[key];
    pl.innerHTML='<div class="pk-ptop"><button type="button" class="pk-back" data-pk-back>'+IC.prev+'All poems</button><b>'+esc(p.title)+'</b><span data-pk-n></span></div>'
     +'<div class="pk-stage"><img data-pk-img alt=""><button type="button" class="pk-arrow pk-prev" data-pk-go="-1" aria-label="Previous line">'+IC.prev+'</button><button type="button" class="pk-arrow pk-next" data-pk-go="1" aria-label="Next line">'+IC.next+'</button></div>'
     +'<div class="pk-side"><div class="pk-lyric"><p class="pk-line" data-pk-line></p><p class="pk-after" data-pk-after></p></div><div class="pk-dots" data-pk-dots aria-hidden="true"></div>'
     +(cls?'<div class="pk-who" role="group" aria-label="Who reads this line">'+['Girls','Boys','Everyone'].map(w=>'<button type="button" data-pk-who="'+w+'">'+w+'</button>').join('')+'</div>':'<p class="pk-turn" data-pk-turn role="status"></p>')
     +'<div class="pk-ctrl"><button type="button" class="pk-play" data-pk-play aria-pressed="false"></button><span class="pk-speed" role="group" aria-label="Reading speed"><button type="button" data-pk-rate="0.6">Slow</button><button type="button" data-pk-rate="0.85">Normal</button></span>'+(cls?'':'<button type="button" class="pk-mine" data-pk-mine>'+IC.mic+'My turn</button>')+'</div></div>';
    line();paint();}
   function line(){const p=poems[key],n=p.lines.length,img=$('[data-pk-img]',pl);
    img.src=PIC(key,idx+1);img.alt='Picture for: '+p.lines[idx];
    $('[data-pk-n]',pl).textContent='Line '+(idx+1)+' of '+n;
    $('[data-pk-line]',pl).innerHTML=p.lines[idx].split(/\s+/).map(w=>'<span class="pk-w">'+esc(w)+'</span>').join(' ');
    $('[data-pk-after]',pl).textContent=p.lines[idx+1]?(cls?'Next: ':'')+p.lines[idx+1]:'';
    $('[data-pk-dots]',pl).innerHTML=p.lines.map((x,i)=>'<i class="'+(i<idx?'d':i===idx?'n':'')+'"></i>').join('');
    $$('[data-pk-go]',pl).forEach(b=>{b.disabled=(+b.dataset.pkGo<0&&idx===0)||(+b.dataset.pkGo>0&&idx===n-1);});
    const t=$('[data-pk-turn]',pl);if(t){t.className='pk-turn '+turn(idx);t.textContent=turn(idx)==='bulig'?'BULIG reads this line. Listen!':'Your turn! Read this line aloud.';}}
   /* BULIG reads the line: the words light up with the voice (or on a steady beat if the voice does not report words). */
   function sayLine(onend){const text=poems[key].lines[idx],ws=$$('.pk-w',pl),me=++my;clearBeat();
    if(!synth){beatLine(onend,me);return;}
    const u=new SpeechSynthesisUtterance(text),v=voice();if(v)u.voice=v;u.lang='en-US';u.rate=rate;u.pitch=1.05;
    const starts=[];let pos=0,heard=false;ws.forEach(w=>{starts.push(pos);pos+=w.textContent.length+1;});
    u.onstart=()=>{if(me!==my)return;mark(ws,0);setTimeout(()=>{if(me!==my||heard)return;let i=0;beat=setInterval(()=>{if(me!==my){clearBeat();return;}i++;if(i>=ws.length){clearBeat();return;}mark(ws,i);},Math.round(430/rate));},700);};
    u.onboundary=e=>{if(me!==my||(e.name&&e.name!=='word'))return;heard=true;let i=0;while(i+1<starts.length&&starts[i+1]<=e.charIndex)i++;mark(ws,i);};
    const end=()=>{if(me!==my)return;clearBeat();mark(ws,ws.length);setTimeout(()=>{if(me===my&&onend)onend();},450);};
    u.onend=end;u.onerror=end;synth.cancel();synth.speak(u);}
   /* The pupil reads the line: the words light up at a reading pace to follow. */
   function beatLine(onend,me){const ws=$$('.pk-w',pl);me=me||++my;clearBeat();let i=0;mark(ws,0);
    beat=setInterval(()=>{if(me!==my){clearBeat();return;}i++;mark(ws,i);if(i>=ws.length){clearBeat();setTimeout(()=>{if(me===my&&onend)onend();},900);}},Math.round(560/rate));}
   function step(){if(!playing)return;const next=()=>{if(!playing)return;if(idx<poems[key].lines.length-1){idx++;line();step();}else finish();};
    if(turn(idx)==='bulig')sayLine(next);else beatLine(next);}
   function finish(){playing=false;done.add(key);paint();const t=$('[data-pk-turn]',pl);if(t){t.className='pk-turn end';t.innerHTML=IC.ok+'You finished the poem! Now say the whole poem by yourself, with actions!';}
    try{window.buligSfx&&window.buligSfx.play('correct');}catch(e){}}
   pk.addEventListener('click',e=>{const t=e.target.closest('button');if(!t||!pk.contains(t))return;
    if(t.dataset.pkOpen){open(t.dataset.pkOpen);return;}
    if(t.hasAttribute('data-pk-back')){close();return;}
    if(t.dataset.pkGo){stop();idx=Math.max(0,Math.min(poems[key].lines.length-1,idx+ +t.dataset.pkGo));line();return;}
    if(t.dataset.pkRate){rate=+t.dataset.pkRate;paint();return;}
    if(t.dataset.pkWho){who=t.dataset.pkWho;paint();return;}
    if(t.hasAttribute('data-pk-mine')){stop();const tt=$('[data-pk-turn]',pl);if(tt){tt.className='pk-turn you';tt.textContent='Your turn! Read this line aloud.';}beatLine(null);return;}
    if(t.hasAttribute('data-pk-play')){if(playing){stop();return;}playing=true;paint();
     if(cls)sayLine(()=>{playing=false;paint();});else{if(idx===poems[key].lines.length-1&&$('.pk-turn.end',pl)){idx=0;line();}step();}}});
   window.addEventListener('pagehide',stop);
  });
 }
 initPoems(document);
 /* Lesson 11: BULIG says a word, the pupil says a word friend. */
 $$('[data-wordgame]').forEach(g=>{let ws=[];try{ws=JSON.parse(g.dataset.wordgame||'[]');}catch(e){}const b=$('[data-wg-new]',g),o=$('[data-wg-word]',g);let last=-1;
  if(b&&o&&ws.length)b.addEventListener('click',()=>{let i;do{i=Math.floor(Math.random()*ws.length);}while(ws.length>1&&i===last);last=i;o.textContent=ws[i];o.classList.remove('pop');void o.offsetWidth;o.classList.add('pop');say(ws[i],b,.8);});});

 /* ---------------- Stories: listen and read along (Level 4 practice passages, Levels 5 to 7) ----------------
    BULIG reads one sentence at a time: the sentence gets a soft highlight, the word being read pops in yellow,
    read words turn green. Pause keeps the place; Slow reads slower. */
 function readAlong(box){if(!box||box.dataset.ka)return;box.dataset.ka='1';
  const walk=document.createTreeWalker(box,NodeFilter.SHOW_TEXT,{acceptNode:n=>n.nodeValue.trim()&&!n.parentNode.closest('button,.ra-skip,output')?1:2}),nodes=[];while(walk.nextNode())nodes.push(walk.currentNode);
  const sents=[[]];let txt=[''];
  nodes.forEach(n=>{const frag=document.createDocumentFragment();n.nodeValue.split(/(\s+)/).forEach(part=>{if(!part)return;if(/^\s+$/.test(part)){frag.append(part);return;}
    const w=document.createElement('span');w.className='ka-w';w.textContent=part;frag.append(w);const si=sents.length-1;sents[si].push(w);txt[si]+=(txt[si]?' ':'')+part;
    if(/[.!?]["”’)]*$/.test(part)){sents.push([]);txt.push('');}});n.replaceWith(frag);});
  while(sents.length&&!sents[sents.length-1].length){sents.pop();txt.pop();}
  if(sents.length<2)return;
  const bar=document.createElement('div');bar.className='ka-bar';
  bar.innerHTML='<button type="button" class="ka-pp" aria-pressed="false" aria-label="Listen and read along">'+IC.play+'</button><div class="ka-prog"><b>Listen and read along</b><small></small><div class="ka-track"><i></i></div></div><button type="button" class="ka-sp" aria-pressed="false">Slow</button>';
  box.after(bar);
  const pp=$('.ka-pp',bar),sp=$('.ka-sp',bar),small=$('small',bar),fill=$('.ka-track i',bar),all=sents.flat();let at=0,playing=false,rate=.85,me=0,beat=null;
  const clearBeat=()=>{if(beat){clearInterval(beat);beat=null;}};
  function ui(){pp.innerHTML=playing?IC.pause:IC.play;pp.setAttribute('aria-pressed',String(playing));pp.setAttribute('aria-label',playing?'Pause':'Listen and read along');
   small.textContent='Sentence '+(at+1)+' of '+sents.length;fill.style.width=Math.round(100*at/sents.length)+'%';box.classList.toggle('ka-live',playing);}
  function paint(i,w){all.forEach(x=>x.classList.remove('ka-cur','on','done'));sents.forEach((s,k)=>s.forEach((x,j)=>{if(k<i||(k===i&&j<w))x.classList.add('done');if(k===i)x.classList.add('ka-cur');if(k===i&&j===w)x.classList.add('on');}));}
  function stop(keep){me++;clearBeat();if(synth)synth.cancel();playing=false;ui();if(!keep)setTimeout(()=>{if(!playing)all.forEach(x=>x.classList.remove('ka-cur','on','done'));},900);}
  function speak(){if(!playing)return;const my=++me,ws=sents[at];paint(at,0);ui();
   const next=()=>{if(my!==me)return;clearBeat();if(at<sents.length-1){at++;speak();}else{at=0;stop();fill.style.width='100%';small.textContent='The end. Tap play to listen again.';}};
   if(!synth){let i=0;beat=setInterval(()=>{if(my!==me)return clearBeat();i++;if(i>=ws.length){next();return;}paint(at,i);},Math.round(430/rate));return;}
   const u=new SpeechSynthesisUtterance(txt[at]),v=voice();if(v)u.voice=v;u.lang='en-US';u.rate=rate;u.pitch=1.05;
   const starts=[];let pos=0,heard=false;ws.forEach(w=>{starts.push(pos);pos+=w.textContent.length+1;});
   u.onstart=()=>{if(my!==me)return;setTimeout(()=>{if(my!==me||heard)return;let i=0;beat=setInterval(()=>{if(my!==me)return clearBeat();i++;if(i>=ws.length)return clearBeat();paint(at,i);},Math.round(430/rate));},700);};
   u.onboundary=e=>{if(my!==me||(e.name&&e.name!=='word'))return;heard=true;let i=0;while(i+1<starts.length&&starts[i+1]<=e.charIndex)i++;paint(at,i);};
   u.onend=()=>setTimeout(next,250);u.onerror=e=>{if(e.error==='interrupted'||e.error==='canceled')return;next();};synth.speak(u);}
  pp.addEventListener('click',()=>{if(playing){stop(true);return;}stopSay();if(synth)synth.cancel();playing=true;speak();});
  sp.addEventListener('click',()=>{rate=rate<.8?.85:.6;sp.classList.toggle('on',rate<.8);sp.setAttribute('aria-pressed',String(rate<.8));if(playing){stop(true);playing=true;speak();}});
  document.addEventListener('bulig:card',()=>{if(playing)stop();});
  document.addEventListener('click',e=>{if(playing&&e.target.closest&&e.target.closest('[data-say],[data-say-words],.l1-mic,[data-l2-next],[data-l2-prev]'))stop();},true);
  window.addEventListener('pagehide',()=>stop());
  ui();small.textContent='Tap play: BULIG reads, you follow the words.';}
 const raForm=$('#activity-form[data-readalong]');
 if(raForm)$$('.l2-card .l2-word.long',raForm).forEach(w=>{const t=w.textContent.trim();if(t.length>=60&&(t.match(/[.!?]["”’)]*(\s|$)/g)||[]).length>=2)readAlong(w);});
 $$('.l4-card[data-readalong] .fluency-text').forEach(readAlong);

 /* ---------------- Class Demo ---------------- */
 const D=$('#l1-demo');
 if(D){
  const tpls=$$('template.l1d-t',D),stage=$('[data-d-stage]',D),prev=$('[data-d-prev]',D),next=$('[data-d-next]',D),count=$('[data-d-count]',D),lis=$('[data-d-listen]',D),les=$('#demo-lesson'),jump=$('#demo-jump'),full=$('[data-d-full]',D);
  let at=Math.min(tpls.length-1,Math.max(0,+D.dataset.start||0));
  /* A saved (offline) copy always starts at slide 1: go back to the slide in the address. */
  {const q=new URLSearchParams(location.search),qa=q.get('activity'),ql=q.get('lesson');if(!at&&(qa||ql)){const i=qa?tpls.findIndex(t=>t.dataset.id===qa):tpls.findIndex(t=>t.dataset.lesson===ql);if(i>0)at=i;}}
  const startOf=i=>{const l=tpls[i].dataset.lesson;return tpls.findIndex(t=>t.dataset.lesson===l);};
  /* Fit the slide: make the words smaller until nothing needs scrolling. */
  function fit(){const box=stage.querySelector('.l1d-one,.l1d-two');if(!box)return;let f=1;box.style.setProperty('--fit','1');
   for(let k=0;k<12&&(stage.scrollHeight>stage.clientHeight+2||stage.scrollWidth>stage.clientWidth+2);k++){f-=.06;box.style.setProperty('--fit',f.toFixed(2));}}
  function show(i){if(i<0||i>=tpls.length)return;stopSay();at=i;const t=tpls[i];stage.replaceChildren(t.content.cloneNode(true));
   $$('img[data-src]',stage).forEach(im=>{if(im.closest('.pk-imgs'))return;im.addEventListener('load',fit,{once:true});im.src=im.dataset.src;im.removeAttribute('data-src');});initPoems(stage);
   $('[data-d-lesson]',D).textContent=t.dataset.title;$('[data-d-part]',D).textContent=t.dataset.part;
   $('[data-d-prog]',D).style.setProperty('--p',String((i+1)/tpls.length));count.textContent='Slide '+(i+1)+' of '+tpls.length;
   prev.disabled=i===0;next.disabled=i===tpls.length-1;if(les)les.value=String(startOf(i));if(jump)jump.value=String(i);
   const u=new URL(location.href);u.searchParams.delete('lesson');u.searchParams.delete('card');if(t.querySelector('.l1d-start')){u.searchParams.delete('activity');u.searchParams.set('lesson',t.dataset.lesson);}else u.searchParams.set('activity',t.dataset.id);history.replaceState(null,'',u);
   requestAnimationFrame(fit);}
  prev.addEventListener('click',()=>show(at-1));next.addEventListener('click',()=>show(at+1));
  if(les)les.addEventListener('change',()=>show(+les.value));
  lis.addEventListener('click',()=>{const t=tpls[at],box=$('.l1-tw',stage);say(box?$$('.w',box).map(w=>w.textContent).join(' '):t.dataset.say,lis,.85,box);});
  async function fs(){try{if(document.fullscreenElement)await document.exitFullscreen();else if(D.requestFullscreen)await D.requestFullscreen();}catch(e){}}
  full.addEventListener('click',fs);
  document.addEventListener('fullscreenchange',()=>{$('span',full).textContent=document.fullscreenElement?'Exit full screen':'Full screen';setTimeout(fit,120);});
  window.addEventListener('resize',()=>requestAnimationFrame(fit));
  document.addEventListener('keydown',e=>{if(e.altKey||e.ctrlKey||e.metaKey||(e.target.closest&&e.target.closest('input,textarea,select,[contenteditable="true"]')))return;
   if($('.plan-ov:not([hidden])')||$('#cd-panel:not([hidden])'))return;
   const k=e.key;if(k===' '&&e.target.closest&&e.target.closest('.pk'))return;if(k==='ArrowRight'||k==='PageDown'||k===' '){e.preventDefault();show(at+1);}else if(k==='ArrowLeft'||k==='PageUp'){e.preventDefault();show(at-1);}
   else if(k==='Home'){e.preventDefault();show(0);}else if(k==='End'){e.preventDefault();show(tpls.length-1);}else if(k.toLowerCase()==='f'){e.preventDefault();fs();}});
  show(at);
 }
})();
/* Activity history: show the rubric total as boxes are chosen. */
document.querySelectorAll('.rv-rub [data-rub-total]').forEach(t=>{const f=t.closest('.rv-rub'),max=t.dataset.rubTotal,rows=f.querySelectorAll('.rv-rub-row').length;
 f.addEventListener('change',()=>{const v=[...f.querySelectorAll('input[type=radio]:checked')].map(i=>+i.value);t.textContent=v.length===rows?'Total: '+v.reduce((a,b)=>a+b,0)+' / '+max:'Chosen '+v.length+' of '+rows+' rows.';});});
/* Teacher review, Level 4: miscue total, Oral Reading Score, Reading Level and Reading Speed as numbers are typed. */
document.querySelectorAll('.l4-score').forEach(f=>{const words=+f.dataset.words||0,q=s=>f.querySelector(s);
 const calc=()=>{const mis=[...f.querySelectorAll('.l4-mis input')].reduce((a,i)=>a+Math.max(0,+i.value||0),0),tot=Math.min(words,mis);
  const score=words?Math.round((words-tot)/words*1000)/10:0,secs=+q('[data-l4-secs]').value||0;
  q('[data-l4-total]').textContent=tot;q('[data-l4-score]').textContent=score+'%';
  q('[data-l4-level]').textContent=score>=98?'Independent':(score>=90?'Instructional':'Frustration');
  q('[data-l4-wpm]').textContent=secs?Math.round((words-tot)/secs*60)+' words per minute':'Type the reading time';};
 f.addEventListener('input',calc);calc();});
