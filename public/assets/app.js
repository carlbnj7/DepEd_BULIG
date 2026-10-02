'use strict';
const $=(s)=>document.querySelector(s);
for(const button of document.querySelectorAll('[data-password]'))button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.password);input.type=input.type==='password'?'text':'password';button.textContent=input.type==='password'?'Show':'Hide';});
const narration=$('.narration');let muted=false;
if(narration){
 const synth=window.speechSynthesis,select=$('#voice-select'),info=$('#voice-info');
 function voices(){if(!synth)return;const list=synth.getVoices().filter(v=>v.lang.toLowerCase().startsWith('en'));select.replaceChildren();for(const v of list){const o=document.createElement('option');o.value=v.voiceURI;o.textContent=v.name;select.append(o);}const remembered=localStorage.getItem('bulig-voice');const preferred=list.find(v=>v.voiceURI===remembered)||list.find(v=>/female|samantha|zira|susan|karen|aria|jenny|ava|hazel/i.test(v.name));if(preferred)select.value=preferred.voiceURI;info.textContent=preferred?'Selected voice: '+preferred.name:'Choose a friendly female voice if your device provides one. Voice gender is not supplied by browsers.';}
 function speak(){if(!synth){info.textContent='Narration is unavailable in this browser. Ask your teacher to read along.';return;}synth.cancel();if(muted)return;const text=narration.dataset.text||'';const chunks=text.match(/[^.!?]+[.!?]+|[^.!?]+$/g)||[text];for(const chunk of chunks){const utterance=new SpeechSynthesisUtterance(chunk);utterance.voice=synth.getVoices().find(v=>v.voiceURI===select.value)||null;utterance.lang='en-US';utterance.rate=.85;utterance.pitch=1.05;utterance.onerror=()=>{info.textContent='The voice could not play. Choose another voice or ask your teacher.';};synth.speak(utterance);}}
 voices();if(synth)synth.addEventListener('voiceschanged',voices);select.addEventListener('change',()=>localStorage.setItem('bulig-voice',select.value));
 document.querySelectorAll('[data-speech]').forEach(b=>b.addEventListener('click',()=>{switch(b.dataset.speech){case 'play':case 'replay':speak();break;case 'pause':if(synth?.paused)synth.resume();else synth?.pause();break;case 'stop':synth?.cancel();break;case 'mute':muted=!muted;b.setAttribute('aria-pressed',String(muted));b.setAttribute('aria-label',muted?'Unmute narration':'Mute narration');if(muted)synth?.cancel();break;}}));window.addEventListener('pagehide',()=>synth?.cancel());
}
const form=$('#activity-form');let draftTimer,submitting=false,drawingDirty=false;
function draft(){if(!form||form.dataset.draft!=='on'||submitting)return;clearTimeout(draftTimer);draftTimer=setTimeout(saveDraft,650);}
async function saveDraft(){if(submitting||form.dataset.draft!=='on')return;const data=new FormData(form);data.set('action','draft');const status=$('#draft-status');status.textContent='Saving your draft…';try{const res=await fetch('index.php',{method:'POST',body:data,credentials:'same-origin'});const result=await res.json();if(!res.ok||!result.saved)throw new Error(result.error||'Could not save');status.textContent='Draft saved';}catch(error){status.textContent=navigator.onLine===false?'Offline · choose Submit to keep your answer on this device':'Draft not saved. Check your connection before leaving.';}}
if(form){
 form.addEventListener('input',draft);
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(submitting)return;submitting=true;clearTimeout(draftTimer);
  const button=$('#submit-answer'),feedback=$('#answer-feedback'),next=$('#next-activity');const original=button?.innerHTML;
  if(button){button.disabled=true;button.textContent='Saving…';}
  const keepOffline=async()=>{const href=await window.buligOffline.queue(form);feedback.replaceChildren();feedback.className='answer-feedback success';const t=document.createElement('strong');t.textContent='Saved on this device!';const sp=document.createElement('span');sp.textContent=' It will upload when you are back online.';feedback.append(t,sp);form.dataset.draft='off';if(button)button.hidden=true;$('#draft-status').textContent='Kept on this device';next.href=href;next.hidden=false;next.focus();if(['none','perform'].includes(form.dataset.mode))location.href=href;};
  if(window.buligOffline&&navigator.onLine===false){try{await keepOffline();}catch(e){feedback.className='answer-feedback tryagain';feedback.textContent='This answer could not be kept on this device.';if(button){button.disabled=false;button.innerHTML=original;}}submitting=false;return;}
  try{
   let res;try{res=await fetch('index.php',{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json'}});}catch(netError){if(window.buligOffline){await keepOffline();return;}throw netError;}
   const data=await res.json();if(!res.ok)throw new Error(data.error||'Your work could not be saved.');
   feedback.replaceChildren();feedback.className='answer-feedback '+(data.saved?'success':'tryagain');
   const title=document.createElement('strong');title.textContent=data.message;feedback.append(title);
   if(data.saved){
    form.dataset.draft='off';if(button)button.hidden=true;next.hidden=false;next.focus();
    $('#draft-status').textContent='Saved';
    if(data.xp){const earned=document.createElement('span');earned.className='earned-xp';earned.textContent='+'+data.xp+' XP';feedback.append(earned);}
    if(['none','perform'].includes(form.dataset.mode))location.href=next.href;
   }else{if(button){button.disabled=false;button.innerHTML=original;}$('#response')?.focus();}
  }catch(error){feedback.className='answer-feedback tryagain';feedback.textContent=error.message||'Check your connection and try again.';if(button){button.disabled=false;button.innerHTML=original;}}
  finally{submitting=false;}
 });
}
$('#type-answer')?.addEventListener('click',()=>{(document.querySelector('.native-card:not([hidden]) .native-answer')||$('#response'))?.focus();});
for(const select of document.querySelectorAll('[data-section-filter]'))select.addEventListener('change',()=>select.form.requestSubmit());
const canvas=$('#drawing-canvas');
if(canvas){const ctx=canvas.getContext('2d');ctx.lineCap='round';ctx.lineJoin='round';ctx.lineWidth=5;let drawing=false;const output=$('#drawing-data');if(output.value){const image=new Image();image.onload=()=>ctx.drawImage(image,0,0,canvas.width,canvas.height);image.src=output.value;}function point(event){const r=canvas.getBoundingClientRect();return [(event.clientX-r.left)*canvas.width/r.width,(event.clientY-r.top)*canvas.height/r.height];}canvas.addEventListener('pointerdown',event=>{if(form.dataset.draft!=='on')return;drawing=true;canvas.setPointerCapture(event.pointerId);ctx.strokeStyle=$('#pen-color').value;ctx.beginPath();ctx.moveTo(...point(event));});canvas.addEventListener('pointermove',event=>{if(!drawing)return;ctx.lineTo(...point(event));ctx.stroke();});function finish(){if(!drawing)return;drawing=false;output.value=canvas.toDataURL('image/png');drawingDirty=true;draft();}canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);$('#clear-drawing').addEventListener('click',()=>{if(form.dataset.draft!=='on')return;ctx.clearRect(0,0,canvas.width,canvas.height);output.value='';draft();});}
const mic=$('#recognize');
if(mic){const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;const status=$('#speech-status');let recognition=null,active=false;if(!Recognition){mic.disabled=true;status.textContent='Speech recognition is unavailable here. Type your answer or ask your teacher.';}else{mic.addEventListener('click',()=>{if(active){recognition.stop();return;}recognition=new Recognition();recognition.lang='en-US';recognition.continuous=false;recognition.interimResults=false;recognition.onstart=()=>{active=true;mic.textContent='Stop listening';status.textContent='Listening…';window.speechSynthesis?.cancel();};recognition.onresult=event=>{const heard=event.results[0][0].transcript;const target=document.querySelector('.native-card:not([hidden]) .native-answer')||$('#response');target.value=canvas&&$('.worksheet-panel')?[target.value,heard].filter(Boolean).join('\n'):heard;target.dispatchEvent(new Event('input',{bubbles:true}));$('#transcript').value=heard;status.textContent='Check the words — the microphone can mishear.';showWords($('#expected-text').value,heard);draft();};recognition.onerror=event=>{status.textContent=event.error==='not-allowed'?'Microphone permission was not granted. You can type your answer.':'We could not hear clearly. Try again or type your answer.';};recognition.onend=()=>{active=false;mic.textContent='Speak Answer';};try{recognition.start();}catch{status.textContent='Microphone could not start. Try typing your answer.';}});window.addEventListener('pagehide',()=>recognition?.abort());}}
function showWords(expected,heard){const el=$('#word-feedback');el.replaceChildren();if(!expected)return;const words=s=>s.toLowerCase().replace(/[^\p{L}\p{N}]+/gu,' ').trim().split(/\s+/).filter(Boolean);const a=words(expected),b=words(heard),d=Array.from({length:a.length+1},(_,i)=>[i]);for(let j=0;j<=b.length;j++)d[0][j]=j;for(let i=1;i<=a.length;i++)for(let j=1;j<=b.length;j++)d[i][j]=Math.min(d[i-1][j]+1,d[i][j-1]+1,d[i-1][j-1]+(a[i-1]===b[j-1]?0:1));const result=[];let i=a.length,j=b.length;while(i||j){if(i&&j&&d[i][j]===d[i-1][j-1]+(a[i-1]===b[j-1]?0:1)){result.push({word:a[i-1],ok:a[i-1]===b[j-1]});i--;j--;}else if(i&&d[i][j]===d[i-1][j]+1){result.push({word:a[i-1],ok:false});i--;}else{result.push({word:'+'+b[j-1],ok:false});j--;}}const p=document.createElement('p');p.textContent='Recognized word match: '+Math.round(Math.max(0,1-d[a.length][b.length]/Math.max(1,a.length))*100)+'% · Your teacher reviews pronunciation.';el.append(p);for(const item of result.reverse()){const span=document.createElement('span');span.className='word-chip'+(item.ok?'':' missed');span.textContent=item.word;el.append(span);}}

// Accessible mobile navigation drawer with focus containment and Escape dismissal.
{
 const toggle=document.querySelector('.mobile-menu-toggle'),panel=document.querySelector('.sidebar'),shade=document.querySelector('.sidebar-shade'),close=document.querySelector('.sidebar-close');
 if(toggle&&panel&&shade&&close){
  const mobile=window.matchMedia('(max-width:740px)');
  function setOpen(open,restore=true){document.body.classList.toggle('sidebar-open',open);toggle.setAttribute('aria-expanded',String(open));shade.hidden=!open;panel.inert=mobile.matches&&!open;if(open)requestAnimationFrame(()=>close.focus());else if(restore)toggle.focus();}
  toggle.addEventListener('click',()=>setOpen(true));close.addEventListener('click',()=>setOpen(false));shade.addEventListener('click',()=>setOpen(false));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('sidebar-open')){e.preventDefault();setOpen(false);}});
  panel.addEventListener('keydown',e=>{if(!mobile.matches||!document.body.classList.contains('sidebar-open'))return;if(e.key==='Escape'){e.preventDefault();setOpen(false);}if(e.key==='Tab'){const nodes=[...panel.querySelectorAll('a,button,input,select')].filter(x=>!x.disabled&&x.getClientRects().length);const first=nodes[0],last=nodes[nodes.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
  mobile.addEventListener('change',()=>setOpen(false,false));setOpen(false,false);
 }
}
function updateImageAudit(){const box=document.querySelector('.image-audit-results');if(!box)return;const imgs=[...document.querySelectorAll('[data-activity-image]')];const loaded=imgs.filter(i=>i.complete&&i.naturalWidth>0).length,failed=imgs.filter(i=>i.complete&&!i.naturalWidth).length;box.textContent=`${loaded} / ${imgs.length} pictures loaded · ${failed} failed`;}
for(const img of document.querySelectorAll('[data-activity-image]')){
 function failure(){const caption=img.closest('figure')?.querySelector('.image-load-error');if(caption)caption.hidden=false;updateImageAudit();}
 img.addEventListener('error',failure);img.addEventListener('load',updateImageAudit);if(img.complete&&!img.naturalWidth)failure();
}
updateImageAudit();

for(const grade of document.querySelectorAll('[data-grade-filter]'))grade.addEventListener('change',()=>{grade.form.querySelector('[name=section_id]').value='0';grade.form.requestSubmit();});

$('#worksheet-zoom')?.addEventListener('click',event=>{const panel=$('.worksheet-panel');const enlarged=panel.classList.toggle('enlarged');event.currentTarget.setAttribute('aria-pressed',String(enlarged));event.currentTarget.textContent=enlarged?'Fit sheet':'Enlarge sheet';});

$('#worksheet-pen')?.addEventListener('click',event=>{const pan=$('.worksheet-panel').classList.toggle('pan-mode');event.currentTarget.setAttribute('aria-pressed',String(!pan));event.currentTarget.textContent=pan?'Use pen':'Move sheet';});

// Teacher Class Demo: local slide navigation only; never submits learning actions.
const demo=document.querySelector('#class-demo');
if(demo){
 const slides=[...demo.querySelectorAll('.demo-template')],stage=$('#demo-stage'),jump=$('#demo-jump'),lesson=$('#demo-lesson');
 let current=Number(demo.dataset.start)||0;
 function showSlide(index){
  if(index<0||index>=slides.length)return;
  window.speechSynthesis?.cancel();current=index;
  const slide=slides[index];stage.replaceChildren(slide.content.cloneNode(true));stage.scrollTop=0;
  for(const img of stage.querySelectorAll('img[data-src]')){
   img.addEventListener('error',()=>{const note=img.closest('figure')?.querySelector('.image-load-error');if(note)note.hidden=false;});
   img.src=img.dataset.src;img.removeAttribute('data-src');
  }
  narration.dataset.text=slide.dataset.narration||'';
  jump.value=String(index);
  const first=slides.findIndex(s=>s.dataset.lesson===slide.dataset.lesson);lesson.value=String(first);
  $('#demo-counter').textContent='Slide '+(index+1)+' of '+slides.length;
  $('#demo-prev').disabled=index===0;$('#demo-next').disabled=index===slides.length-1;
  $('#demo-help').textContent=index===slides.length-1?'End of this level · Review any slide or exit demo':'Arrow keys move between slides · F for full screen';
  const url=new URL(location.href);url.searchParams.set('activity',slide.dataset.id);url.searchParams.set('card',slide.dataset.card||'0');history.replaceState(null,'',url);
 }
 $('#demo-prev').addEventListener('click',()=>showSlide(current-1));$('#demo-next').addEventListener('click',()=>showSlide(current+1));
 jump.addEventListener('change',()=>showSlide(Number(jump.value)));lesson.addEventListener('change',()=>showSlide(Number(lesson.value)));
 $('#demo-zoom').addEventListener('click',event=>{const enlarged=stage.classList.toggle('pictures-enlarged');event.currentTarget.setAttribute('aria-pressed',String(enlarged));event.currentTarget.textContent=enlarged?'Fit pictures':'Enlarge pictures';});
 async function fullscreen(){try{if(document.fullscreenElement)await document.exitFullscreen();else if(demo.requestFullscreen)await demo.requestFullscreen();else $('#demo-help').textContent='Full screen is unavailable here. Use your browser’s full-screen command.';}catch{$('#demo-help').textContent='Full screen could not open. Use your browser’s full-screen command.';}}
 $('#demo-fullscreen').addEventListener('click',fullscreen);
 document.addEventListener('fullscreenchange',()=>{$('#demo-fullscreen').textContent=document.fullscreenElement?'Exit full screen':'Full screen';});
 document.addEventListener('keydown',event=>{
  if(event.altKey||event.ctrlKey||event.metaKey||event.target.closest('input,textarea,select,[contenteditable="true"]'))return;
  if(event.key==='ArrowRight'){event.preventDefault();showSlide(current+1);}else if(event.key==='ArrowLeft'){event.preventDefault();showSlide(current-1);}else if(event.key==='Home'){event.preventDefault();showSlide(0);}else if(event.key==='End'){event.preventDefault();showSlide(slides.length-1);}else if(event.key.toLowerCase()==='f'){event.preventDefault();fullscreen();}
 });
 showSlide(current);
}

// Individual picture/question cards. Stable parent activity IDs keep saved progress intact.
const nativeDeck=document.querySelector('[data-native-deck]');
if(nativeDeck){
 const cards=[...nativeDeck.querySelectorAll('.native-card')],prev=nativeDeck.querySelector('[data-native-prev]'),next=nativeDeck.querySelector('[data-native-next]'),all=document.querySelector('#response');let at=0;
 const original=nativeDeck.dataset.earlierAnswer||'';
 function collect(){if(nativeDeck.dataset.readonly==='yes'||!all)return;const lines=[...nativeDeck.querySelectorAll('.native-answer')].map(el=>'Card '+el.dataset.number+': '+el.value.trim());const hasAnswer=[...nativeDeck.querySelectorAll('.native-answer')].some(el=>el.value.trim());all.value=hasAnswer?'BULIG card answers\n'+lines.join('\n')+(original?'\nEarlier answer: '+original:''):original;all.dispatchEvent(new Event('input',{bubbles:true}));}
 for(const input of nativeDeck.querySelectorAll('.native-answer'))input.addEventListener('input',collect);
 function show(n){at=Math.max(0,Math.min(cards.length-1,n));cards.forEach((c,i)=>{c.hidden=i!==at;});prev.disabled=at===0;next.disabled=at===cards.length-1;next.textContent=at===cards.length-1?'Last card':'Next picture / question →';nativeDeck.querySelector('.native-counter').textContent='Card '+(at+1)+' of '+cards.length;if(narration)narration.dataset.text=cards[at].dataset.narration;window.speechSynthesis?.cancel();}
 prev.addEventListener('click',()=>show(at-1));next.addEventListener('click',()=>show(at+1));show(0);
}

// Level 4 fluency: time the read-aloud, listen continuously, and record words per minute with the answer.
const fluency=$('#fluency-timer');
if(fluency){const start=$('#fluency-start'),stop=$('#fluency-stop'),clock=$('#fluency-clock'),status=$('#fluency-status'),box=$('#response'),words=+fluency.dataset.words||0;const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;let began=0,tick=null,rec=null,heard=[],listening=false;
const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
const listen=()=>{if(!Recognition)return;rec=new Recognition();rec.lang='en-US';rec.continuous=true;rec.interimResults=false;rec.onresult=e=>{for(let i=e.resultIndex;i<e.results.length;i++)if(e.results[i].isFinal)heard.push(e.results[i][0].transcript.trim());};rec.onerror=e=>{if(e.error==='not-allowed')status.textContent='Microphone permission was not granted. Keep reading — your time is still recorded.';};rec.onend=()=>{if(listening)try{rec.start();}catch{}};listening=true;try{rec.start();}catch{listening=false;}};
start.addEventListener('click',()=>{window.speechSynthesis?.cancel();heard=[];began=Date.now();clock.value=clock.textContent='0:00';tick=setInterval(()=>{clock.textContent=fmt((Date.now()-began)/1000);},250);start.hidden=true;stop.hidden=false;stop.focus();status.textContent=Recognition?'Listening… read the whole passage aloud.':'Timing… read the whole passage aloud.';listen();});
stop.addEventListener('click',()=>{const secs=Math.max(1,Math.round((Date.now()-began)/1000));clearInterval(tick);listening=false;rec?.stop();start.hidden=false;stop.hidden=true;start.lastChild.textContent='Read again';clock.textContent=fmt(secs);const wpm=words?Math.round(words/secs*60):0;const line='Reading time: '+fmt(secs)+' ('+secs+' seconds)'+(wpm?' · '+wpm+' words per minute':'');status.textContent=line+'.';
 setTimeout(()=>{const text=heard.join(' ');box.value=(text?text+'\n\n':'')+line;box.dispatchEvent(new Event('input',{bubbles:true}));$('#transcript').value=text;if(text)showWords($('#expected-text').value,text);draft();},400);});
window.addEventListener('pagehide',()=>{listening=false;rec?.abort();});}

// Class Demo scoring helper for fluency passages (Phil-IRI). Display only; nothing is saved.
document.addEventListener('input',event=>{const box=event.target.closest('.fluency-score');if(!box)return;const words=+box.dataset.words||0,get=k=>box.querySelector('[data-score="'+k+'"]');const m=get('miscues').value,s=+get('seconds').value;
 if(m!==''&&words){const p=Math.max(0,(words-+m)/words*100);get('percent').textContent=p.toFixed(1)+'%';get('level').textContent=p>=97?'Independent':p>=90?'Instructional':'Frustration';}else{get('percent').textContent=get('level').textContent='—';}
 get('wpm').textContent=s>0&&words?Math.round(words/s*60)+' words per minute':'—';});

// Matching cards: the letter chosen for each Column A item fills that card's answer ("1-b, 2-a").
document.addEventListener('change',event=>{const sel=event.target.closest('select[data-match]');if(!sel)return;const card=sel.closest('.native-card');const box=card&&card.querySelector('.native-answer');if(!box)return;box.value=[...card.querySelectorAll('select[data-match]')].filter(s=>s.value).map(s=>s.dataset.match+'-'+s.value).join(', ');box.dispatchEvent(new Event('input',{bubbles:true}));});

// Matching boards: tap (or drag) from a Column A item to a Column B choice to draw a connecting line.
(()=>{const NS='http://www.w3.org/2000/svg',COLORS=['#1b7a3a','#d80006','#001db5','#c77700','#7b2cbf','#0b8a8a','#b5179e','#5a5a00','#e85d04','#3a5a40','#9d0208','#023e8a','#6a4c93','#2b9348','#bc6c25'];
function setup(board){if(board.dataset.ready)return;board.dataset.ready='1';const svg=board.querySelector('.match-lines'),pairs=new Map();let pick=null,drag=null;
 const card=board.closest('.native-card'),box=card&&card.querySelector('.native-answer'),readonly=board.dataset.answer!=='on'&&!!box;
 const item=n=>board.querySelector('.match-item[data-num="'+n+'"]'),choice=l=>board.querySelector('.match-choice[data-letter="'+l+'"]');
 function pt(el,side){const b=board.getBoundingClientRect(),r=el.getBoundingClientRect();return side==='r'?[r.right-b.left,r.top+r.height/2-b.top]:[r.left-b.left,r.top+r.height/2-b.top];}
 function draw(){const b=board.getBoundingClientRect();svg.setAttribute('width',b.width);svg.setAttribute('height',b.height);svg.replaceChildren();let i=0;
  for(const [n,l] of pairs){const a=item(n),c=choice(l);if(!a||!c)continue;const [x1,y1]=pt(a,'r'),[x2,y2]=pt(c,'l'),col=COLORS[i++%COLORS.length];
   const line=document.createElementNS(NS,'path');line.setAttribute('d',`M${x1} ${y1} C ${(x1+x2)/2} ${y1}, ${(x1+x2)/2} ${y2}, ${x2} ${y2}`);line.setAttribute('stroke',col);line.setAttribute('class','match-line');svg.append(line);
   for(const [x,y] of [[x1,y1],[x2,y2]]){const d=document.createElementNS(NS,'circle');d.setAttribute('cx',x);d.setAttribute('cy',y);d.setAttribute('r',7);d.setAttribute('fill',col);svg.append(d);}
   a.style.setProperty('--pair',col);c.style.setProperty('--pair',col);}
  board.querySelectorAll('.match-item,.match-choice').forEach(el=>el.classList.toggle('paired',[...pairs.keys()].includes(el.dataset.num)||[...pairs.values()].includes(el.dataset.letter)));
  if(drag){const l=document.createElementNS(NS,'line');l.setAttribute('x1',drag.x1);l.setAttribute('y1',drag.y1);l.setAttribute('x2',drag.x2);l.setAttribute('y2',drag.y2);l.setAttribute('class','match-line temp');svg.append(l);}}
 function save(){if(box&&board.dataset.answer==='on'){box.value=[...pairs].sort((a,b)=>a[0]-b[0]).map(([n,l])=>n+'-'+l).join(', ');box.dispatchEvent(new Event('input',{bubbles:true}));board.querySelectorAll('select[data-match]').forEach(s=>{s.value=pairs.get(s.dataset.match)||'';});}}
 function connect(n,l){for(const [k,v] of pairs)if(v===l)pairs.delete(k);pairs.set(n,l);save();draw();}
 function clearPick(){pick=null;board.querySelectorAll('.picking').forEach(e=>e.classList.remove('picking'));}
 function tap(el){if(readonly)return;const isA=el.classList.contains('match-item'),key=isA?el.dataset.num:el.dataset.letter;
  if(!pick){if(isA&&pairs.has(key)){pairs.delete(key);save();draw();return;}if(!isA){for(const [k,v] of pairs)if(v===key){pairs.delete(k);save();draw();return;}}pick={isA,key};el.classList.add('picking');return;}
  if(pick.isA===isA){clearPick();pick={isA,key};el.classList.add('picking');return;}
  const n=isA?key:pick.key,l=isA?pick.key:key;clearPick();connect(n,l);}
 board.addEventListener('click',e=>{const el=e.target.closest('.match-item,.match-choice');if(el&&!board.dataset.dragged)tap(el);delete board.dataset.dragged;});
 board.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&e.target.closest('.match-item,.match-choice')){e.preventDefault();tap(e.target);}});
 board.addEventListener('pointerdown',e=>{const el=e.target.closest('.match-item,.match-choice');if(!el||readonly||e.target.closest('select'))return;const [x,y]=pt(el,el.classList.contains('match-item')?'r':'l');drag={from:el,x1:x,y1:y,x2:x,y2:y,moved:false};});
 window.addEventListener('pointermove',e=>{if(!drag)return;const b=board.getBoundingClientRect();drag.x2=e.clientX-b.left;drag.y2=e.clientY-b.top;if(Math.hypot(drag.x2-drag.x1,drag.y2-drag.y1)>25){drag.moved=true;e.preventDefault();draw();}});
 const end=e=>{if(!drag)return;const d=drag;drag=null;if(d.moved){board.dataset.dragged='1';const t=document.elementFromPoint(e.clientX,e.clientY),to=t&&t.closest('.match-item,.match-choice');
  if(to&&to.parentElement!==d.from.parentElement&&board.contains(to)){const a=d.from.classList.contains('match-item')?d.from:to,c=a===d.from?to:d.from;clearPick();connect(a.dataset.num,c.dataset.letter);}}draw();};
 window.addEventListener('pointerup',end);window.addEventListener('pointercancel',()=>{if(drag){drag=null;draw();}});
 if(box&&box.value)for(const m of box.value.matchAll(/(\d+)\s*-\s*([a-z])/gi))pairs.set(m[1],m[2].toLowerCase());
 board.addEventListener('redraw',draw);board.addEventListener('dragstart',e=>e.preventDefault());board.querySelectorAll('img').forEach(i=>i.draggable=false);new ResizeObserver(draw).observe(board);board.querySelectorAll('img').forEach(i=>i.addEventListener('load',draw));draw();}
function scan(root){(root||document).querySelectorAll('[data-match-board]').forEach(setup);}
scan();new MutationObserver(()=>scan()).observe(document.body,{childList:true,subtree:true});
document.addEventListener('click',e=>{if(e.target.closest('[data-native-next],[data-native-prev],#demo-next,#demo-prev'))setTimeout(()=>document.querySelectorAll('[data-match-board]').forEach(b=>b.dispatchEvent(new Event('redraw'))),50);});})();

/* Level 5 letter puzzles: tap letters to mark the words you find */
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.letter-grid .grid-cell');if(!b)return;b.classList.toggle('found');b.setAttribute('aria-pressed',b.classList.contains('found')?'true':'false');});

/* Level 6 reading for speed: time the reading, show words per minute, save it as the card's answer */
document.addEventListener('click',function(e){
 var start=e.target.closest&&e.target.closest('[data-speed-start]'),stop=e.target.closest&&e.target.closest('[data-speed-stop]');
 if(!start&&!stop)return;var box=(start||stop).closest('[data-speed-timer]'),clock=box.querySelector('.speed-clock'),res=box.querySelector('.speed-result'),words=+box.dataset.words||0;
 function fmt(s){return Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');}
 if(start){box.dataset.began=Date.now();res.textContent='Reading…';start.hidden=true;box.querySelector('[data-speed-stop]').hidden=false;clearInterval(box._t);box._t=setInterval(function(){clock.textContent=fmt((Date.now()-box.dataset.began)/1000);},250);return;}
 clearInterval(box._t);var secs=Math.max(1,(Date.now()-box.dataset.began)/1000),wpm=Math.round(words/(secs/60));clock.textContent=fmt(secs);
 var msg='Reading time '+fmt(secs)+' · '+words+' words · '+wpm+' words per minute';res.textContent=msg;stop.hidden=true;var again=box.querySelector('[data-speed-start]');again.hidden=false;again.textContent='Read again';
 var card=box.closest('.native-card'),ans=card&&card.querySelector('.native-answer');if(ans){ans.value=msg;ans.dispatchEvent(new Event('input',{bubbles:true}));}
});
/* Admin PIN number pad (v19) */
(function(){const f=document.querySelector('[data-pin-form]');if(!f)return;const fields=[...f.querySelectorAll('[data-pin-field]')].map(w=>({input:w.querySelector('[data-pin-input]'),boxes:[...w.querySelectorAll('[data-pin-boxes] b')],wrap:w}));
 let active=fields[0];
 const draw=()=>{fields.forEach(x=>{const v=x.input.value.replace(/\D/g,'').slice(0,4);x.input.value=v;x.boxes.forEach((b,i)=>{b.textContent=i<v.length?'\u2022':'';b.classList.toggle('f',i<v.length);b.classList.toggle('cur',x===active&&i===v.length);});});};
 fields.forEach(x=>{x.input.addEventListener('input',()=>{active=x;draw();});x.input.addEventListener('focus',()=>{active=x;draw();});x.wrap.querySelector('[data-pin-boxes]').addEventListener('click',()=>{active=x;x.input.focus();draw();});});
 f.querySelectorAll('[data-pin-pad] button').forEach(b=>b.addEventListener('click',()=>{const d=b.dataset.digit,x=active;
  if(d==='back'){if(!x.input.value&&fields.indexOf(x)>0){active=fields[fields.indexOf(x)-1];}active.input.value=active.input.value.slice(0,-1);}
  else if(d==='clear'){fields.forEach(y=>y.input.value='');active=fields[0];}
  else if(x.input.value.length<4){x.input.value+=d;if(x.input.value.length===4&&fields.indexOf(x)<fields.length-1)active=fields[fields.indexOf(x)+1];}
  draw();if(fields.every(y=>y.input.value.length===4))f.requestSubmit();}));
 document.addEventListener('keydown',e=>{if(e.ctrlKey||e.metaKey||e.altKey)return;if(fields.some(y=>y.input===e.target))return;const k=e.key;const btn=/^[0-9]$/.test(k)?f.querySelector('[data-digit="'+k+'"]'):k==='Backspace'?f.querySelector('[data-digit="back"]'):k==='Escape'?f.querySelector('[data-digit="clear"]'):null;if(btn){e.preventDefault();btn.click();}else if(k==='Enter'&&e.target.tagName!=='BUTTON'){e.preventDefault();f.requestSubmit();}});
 fields.forEach(x=>x.input.addEventListener('input',()=>{if(x.input.value.length===4){const i=fields.indexOf(x);if(i<fields.length-1){active=fields[i+1];active.input.focus();draw();}else if(fields.every(y=>y.input.value.length===4))f.requestSubmit();}}));
 draw();})();
/* Admin pages: confirm prompts, print button, filters that submit on change (v21) */
document.addEventListener('click',e=>{const c=e.target.closest('[data-confirm]');if(c&&!confirm(c.dataset.confirm)){e.preventDefault();e.stopImmediatePropagation();}
 if(e.target.closest('[data-print]'))window.print();},true);
document.addEventListener('change',e=>{const s=e.target.closest('[data-autosubmit]');if(s&&s.form){if(s.name==='grade'){const id=s.form.querySelector('[name=id]');if(id)id.disabled=true;}s.form.requestSubmit();}});
/* Admin tables: label each cell so phones can show rows as cards (v21) */
document.querySelectorAll('.ws-table').forEach(t=>{const h=[...t.querySelectorAll('thead th')].map(x=>x.textContent.trim());t.querySelectorAll('tbody tr').forEach(r=>[...r.children].forEach((c,i)=>{if(h[i]&&!c.hasAttribute('colspan'))c.dataset.label=h[i];}));});
/* Pupil welcome and celebration panels, shown one after another (v24) */
(function(){const q=[...document.querySelectorAll('.pw-overlay')];if(!q.length)return;let cur=null;
 const esc=e=>{if(e.key==='Escape'&&cur)close();};
 function show(){cur=q.shift()||null;if(!cur){document.removeEventListener('keydown',esc);document.dispatchEvent(new Event('pf:overlays-done'));return;}cur.hidden=false;const f=cur.querySelector('.pw-actions .btn');if(f)setTimeout(()=>f.focus(),600);}
 function close(){const w=cur;cur=null;w.classList.add('pw-out');setTimeout(()=>{w.remove();show();},320);}
 q.forEach(w=>{w.querySelectorAll('[data-welcome-close]').forEach(b=>b.addEventListener('click',()=>{if(cur===w)close();}));w.addEventListener('click',e=>{if(e.target===w&&cur===w)close();});});
 document.addEventListener('keydown',esc);window.pfOverlayOpen=()=>!!cur;show();})();
/* Pupil dashboard animations (v24) */
(function(){const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const store=(k,v)=>{try{if(v===undefined)return sessionStorage.getItem(k);sessionStorage.setItem(k,v);}catch(e){return null;}};
 function count(el){const n=+el.dataset.count;if(still||!n){el.textContent=n;return;}const t0=performance.now();el.textContent='0';(function f(t){const k=Math.min(1,(t-t0)/1200);el.textContent=Math.round(n*(1-Math.pow(1-k,3)));if(k<1)requestAnimationFrame(f);})(t0);}
 function fill(p){const v=+p.getAttribute('value');if(still||!v)return;const t0=performance.now();p.value=0;(function f(t){const k=Math.min(1,(t-t0)/1400);p.value=v*(1-Math.pow(1-k,3));if(k<1)requestAnimationFrame(f);})(t0);}
 const cols=['#f6c02c','#2f8a4b','#d6452f','#3b82c4','#ff8fab','#9b7bff'];
 document.querySelectorAll('.pf-conf').forEach(c=>{c.style.left=Math.random()*100+'%';c.style.background=cols[Math.floor(Math.random()*cols.length)];c.style.setProperty('--d',(Math.random()*.9).toFixed(2)+'s');c.style.setProperty('--x',Math.round(Math.random()*80-40)+'px');});
 const start=()=>{document.querySelectorAll('[data-count]').forEach(e=>{const late=e.closest('.pf-celebrate');setTimeout(()=>count(e),late?1500:150);});document.querySelectorAll('progress[data-pf-fill]').forEach(fill);
  document.querySelectorAll('[data-pf-plus]').forEach(p=>{const k='pf-plus-'+p.dataset.pfPlus;if(!store(k)){p.hidden=false;store(k,'1');}});};
 if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',start,{once:true});else start();
 document.querySelectorAll('[data-pf-wiggle]').forEach(c=>{const l=c.querySelector('.level-lock .icon');if(!l)return;const go=()=>{l.classList.remove('pf-wig');void l.getBoundingClientRect();l.classList.add('pf-wig');};c.addEventListener('click',e=>{if(!e.target.closest('a'))go();});});
 const h=document.querySelector('[data-pf-helper]');if(h){const b=h.parentNode.querySelector('.pf-bubble');let lines=[];try{lines=JSON.parse(h.dataset.lines);}catch(e){}let i=Math.floor(Math.random()*lines.length),t;
  const say=()=>{if(!lines.length)return;b.textContent=lines[i++%lines.length];b.classList.add('on');h.classList.remove('pf-wave-now');void h.getBoundingClientRect();h.classList.add('pf-wave-now');clearTimeout(t);t=setTimeout(()=>b.classList.remove('on'),3200);};
  h.addEventListener('click',say);const greet=()=>setTimeout(say,1800);if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',greet,{once:true});else greet();}
})();
/* Teacher grade and section tabs: a highlight glides to the chosen tab (v25) */
(function(){const rows=document.querySelectorAll('.class-tabs .tab-row');if(!rows.length)return;const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const store=(k,v)=>{try{if(v===undefined)return sessionStorage.getItem(k);sessionStorage.setItem(k,v);}catch(e){return null;}};
 rows.forEach((row,n)=>{const act=row.querySelector('.tab.active');if(!act)return;const key='tf-tab-'+n;const ind=document.createElement('span');ind.className='tf-ind';ind.setAttribute('aria-hidden','true');row.prepend(ind);row.classList.add('tf-glide');
  const place=(l,w)=>{ind.style.left=l+'px';ind.style.width=w+'px';ind.style.top=act.offsetTop+'px';ind.style.height=act.offsetHeight+'px';};
  let prev=null;try{prev=JSON.parse(store(key)||'null');}catch(e){}
  if(prev&&!still&&(prev.l!==act.offsetLeft)){ind.classList.add('tf-nomove');place(prev.l,prev.w);void ind.offsetWidth;ind.classList.remove('tf-nomove');requestAnimationFrame(()=>place(act.offsetLeft,act.offsetWidth));}else place(act.offsetLeft,act.offsetWidth);
  store(key,'');row.querySelectorAll('a.tab').forEach(a=>a.addEventListener('click',()=>store(key,JSON.stringify({l:act.offsetLeft,w:act.offsetWidth}))));
  window.addEventListener('resize',()=>place(act.offsetLeft,act.offsetWidth));});
})();
document.querySelectorAll('.tf-toast').forEach(t=>setTimeout(()=>t.remove(),4600));
/* Install BULIG as an app (v26) */
(function(){
 if('serviceWorker' in navigator)window.addEventListener('load',()=>navigator.serviceWorker.register('sw.js').catch(()=>{}));
 const standalone=(window.matchMedia&&matchMedia('(display-mode: standalone)').matches)||navigator.standalone===true;if(standalone)return;
 const get=k=>{try{return localStorage.getItem(k);}catch(e){return null;}},set=(k,v)=>{try{localStorage.setItem(k,v);}catch(e){}};
 if(+get('bulig-install-later')>Date.now())return;
 const ua=navigator.userAgent||'';const ios=/iphone|ipad|ipod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 const icon='<svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 21h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
 function bar(text,action){const b=document.createElement('div');b.className='install-bar';b.setAttribute('role','region');b.setAttribute('aria-label','Install BULIG');
  b.innerHTML='<img src="assets/brand/bulig-app-medium.png" alt="" width="40" height="40"><div><strong>Install BULIG</strong><span></span></div>'+(action?'<button type="button" class="btn primary install-go">'+icon+'Install</button>':'')+'<button type="button" class="install-later" aria-label="Not now">Not now</button>';
  b.querySelector('span').textContent=text;b.querySelector('.install-later').addEventListener('click',()=>{set('bulig-install-later',String(Date.now()+7*864e5));b.remove();});
  if(action)b.querySelector('.install-go').addEventListener('click',()=>action(b));document.body.appendChild(b);return b;}
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();const ev=e;if(document.querySelector('.install-bar'))return;
  bar('Add BULIG to your home screen and open it like an app.',b=>{ev.prompt();ev.userChoice.finally(()=>b.remove());});});
 window.addEventListener('appinstalled',()=>{const b=document.querySelector('.install-bar');if(b)b.remove();});
 if(ios&&/safari/i.test(ua)&&!/crios|fxios|edgios/i.test(ua))setTimeout(()=>bar('Tap the Share button, then “Add to Home Screen”.',null),1500);
})();
/* Level road map (v30): draws a winding road with one stop per lesson. The plain list stays underneath as a fallback. */
(function(){const box=document.querySelector('[data-pf-map]');if(!box)return;let data;try{data=JSON.parse(box.dataset.pfMap);}catch(e){return;}const items=data.items||[];if(!items.length)return;
 const NS='http://www.w3.org/2000/svg';const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const card=document.createElement('div');card.className='pf-mapcard';box.prepend(card);box.classList.add('pf-has-map');
 const svg=document.createElementNS(NS,'svg');svg.setAttribute('class','pf-mapsvg');svg.setAttribute('role','group');svg.setAttribute('aria-label',data.level+' road map');card.appendChild(svg);
 let lastWide=null,pop=null;
 function build(){const wide=card.clientWidth>700;if(wide===lastWide&&svg.childNodes.length)return;lastWide=wide;if(pop){pop.remove();pop=null;}
  const W=wide?1000:420,STEP=150,TOP=wide?210:200,N=items.length,H=TOP+STEP*(N-1)+300,XS=wide?[500,760,500,240]:[210,320,210,100];
  const P=items.map((it,i)=>({x:XS[i%4],y:TOP+i*STEP}));let CUR=items.findIndex(it=>it.s==='cur');
  svg.setAttribute('viewBox',`0 0 ${W} ${H}`);
  let s=`<defs><linearGradient id="m-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#bfe8ff"/><stop offset="1" stop-color="#eaf8ff"/></linearGradient><linearGradient id="m-grass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d9f2c4"/><stop offset=".5" stop-color="#c8eab0"/><stop offset="1" stop-color="#b7e09c"/></linearGradient><linearGradient id="m-road" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f7e3b0"/><stop offset=".5" stop-color="#fff1cc"/><stop offset="1" stop-color="#f2d9a0"/></linearGradient><linearGradient id="m-done" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6fdc86"/><stop offset="1" stop-color="#1d7a3c"/></linearGradient><linearGradient id="m-cur" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff07a"/><stop offset="1" stop-color="#f29a0c"/></linearGradient><linearGradient id="m-open" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#cfe9d4"/></linearGradient><linearGradient id="m-lock" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4f5f1"/><stop offset="1" stop-color="#b9c1b4"/></linearGradient><linearGradient id="m-river" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9adcff"/><stop offset="1" stop-color="#3f9be0"/></linearGradient><linearGradient id="m-mtn" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a8d7a0"/><stop offset="1" stop-color="#cde9c0"/></linearGradient><linearGradient id="m-castle" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff8e8"/><stop offset="1" stop-color="#ead9b4"/></linearGradient><radialGradient id="m-shine" cx="50%" cy="30%" r="60%"><stop offset="0" stop-color="#fff" stop-opacity=".55"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient><pattern id="m-tuft" width="60" height="44" patternUnits="userSpaceOnUse"><path d="M10 30q2-8 4 0q2-10 4 0M40 12q2-7 4 0q2-9 4 0" stroke="#9fd284" stroke-width="2" fill="none" stroke-linecap="round"/></pattern></defs>`;
  s+=`<rect width="${W}" height="${H}" fill="url(#m-grass)"/><rect width="${W}" height="${H}" fill="url(#m-tuft)" opacity=".7"/><rect width="${W}" height="150" fill="url(#m-sky)"/>`;
  s+=`<g transform="translate(${W-90} 62)"><g class="pfm-sunr">${Array.from({length:12},(_,k)=>`<rect x="-3" y="-56" width="6" height="16" rx="3" fill="#ffd23f" transform="rotate(${k*30})"/>`).join('')}</g><circle r="32" fill="url(#pf-sun)"/></g>`;
  s+=`<path d="M0 150 L${W*.12} 70 L${W*.24} 130 L${W*.38} 56 L${W*.52} 140 L${W*.66} 80 L${W*.8} 132 L${W*.92} 76 L${W} 120 V170 H0Z" fill="url(#m-mtn)"/><path d="M${W*.38-18} 74 L${W*.38} 56 L${W*.38+18} 74Z M${W*.12-14} 84 L${W*.12} 70 L${W*.12+14} 84Z" fill="#fff"/><path d="M0 160 Q${W*.25} 120 ${W*.5} 150 T${W} 145 V200 H0Z" fill="#c8eab0"/>`;
  s+=`<g class="pfm-cloud">${[[W*.06,26,1.2],[W*.45,46,1],[W*.66,20,.8]].map(([x,y,k])=>`<path transform="translate(${x} ${y}) scale(${k})" d="M0 14a10 10 0 0 1 16-9 13 13 0 0 1 24 3 8 8 0 0 1 2 16H4a9 9 0 0 1-4-10z" fill="#fff"/>`).join('')}</g><g class="pfm-birds">${[[0,40],[22,30],[40,44]].map(([x,y])=>`<path class="pfm-bird" d="M${x} ${y}q5-5 10 0q5-5 10 0" stroke="#3b5d74" stroke-width="2" fill="none" stroke-linecap="round"/>`).join('')}</g>`;
  const zone=f=>TOP+STEP*(Math.max(0,Math.round((N-1)*f))+.5);const riverY=N>2?zone(.3):-999,pondY=N>4?zone(.5):-999,townY=N>3?zone(.68):-999;
  if(riverY>0){s+=`<path d="M0 ${riverY-34} C${W*.25} ${riverY-62} ${W*.45} ${riverY-10} ${W*.7} ${riverY-38} S${W} ${riverY-30} ${W} ${riverY-30} V${riverY+40} C${W*.75} ${riverY+60} ${W*.5} ${riverY+14} ${W*.28} ${riverY+44} S0 ${riverY+34} 0 ${riverY+34}Z" fill="#e8d9a6"/><path d="M0 ${riverY-24} C${W*.25} ${riverY-52} ${W*.45} ${riverY} ${W*.7} ${riverY-28} S${W} ${riverY-20} ${W} ${riverY-20} V${riverY+30} C${W*.75} ${riverY+50} ${W*.5} ${riverY+4} ${W*.28} ${riverY+34} S0 ${riverY+24} 0 ${riverY+24}Z" fill="url(#m-river)"/><g class="pfm-ripple">${Array.from({length:Math.ceil(W/60)+2},(_,k)=>`<path d="M${k*60} ${riverY+2+(k%2)*10} q10 -6 20 0 t20 0" stroke="#fff" stroke-width="2.5" fill="none" opacity=".75"/>`).join('')}</g>`;}
  if(townY>0)s+=`<path d="M0 ${townY-70} Q${W/2} ${townY-110} ${W} ${townY-70} V${townY+80} Q${W/2} ${townY+120} 0 ${townY+80}Z" fill="#efe3c0" opacity=".7"/>`;
  const seg=(a,b)=>` C${a.x} ${a.y+STEP*.58} ${b.x} ${b.y-STEP*.58} ${b.x} ${b.y}`;let d=`M${P[0].x} ${P[0].y-110} L${P[0].x} ${P[0].y}`;for(let i=1;i<N;i++)d+=seg(P[i-1],P[i]);const end={x:P[N-1].x,y:P[N-1].y+120};d+=` L${end.x} ${end.y}`;
  let reach=CUR>=0?CUR:items.reduce((m,it,i)=>it.s==='done'?i:m,-1);let dd=`M${P[0].x} ${P[0].y-110} L${P[0].x} ${P[0].y}`;for(let i=1;i<=reach;i++)dd+=seg(P[i-1],P[i]);if(items.every(it=>it.s==='done'))dd=d;
  s+=`<path d="${d}" fill="none" stroke="#7a5a2a" stroke-opacity=".18" stroke-width="58" stroke-linecap="round" transform="translate(0 6)"/><path d="${d}" fill="none" stroke="#b08a4e" stroke-width="54" stroke-linecap="round"/><path d="${d}" fill="none" stroke="#d9bd84" stroke-width="54" stroke-linecap="round" stroke-dasharray="2 12"/><path d="${d}" fill="none" stroke="url(#m-road)" stroke-width="42" stroke-linecap="round"/>${reach>=0?`<path d="${dd}" fill="none" stroke="#ffd96b" stroke-width="42" stroke-linecap="round" opacity=".85"/>`:''}<path class="pfm-dash" d="${d}" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".9"/>`;
  svg.innerHTML='';const rp=document.createElementNS(NS,'path');rp.setAttribute('d',d);svg.appendChild(rp);const RL=rp.getTotalLength();const pts=[];for(let l=0;l<RL;l+=20)pts.push(rp.getPointAtLength(l));
  if(riverY>0){const c=pts.reduce((a,q)=>Math.abs(q.y-riverY)<Math.abs(a.y-riverY)?q:a,pts[0]);s+=`<g transform="translate(${c.x} ${riverY})"><rect x="-38" y="-44" width="76" height="88" rx="8" fill="#a9703a"/>${[-40,-28,-16,-4,8,20,32].map(y=>`<rect x="-36" y="${y}" width="72" height="9" rx="2" fill="#c48a4f"/>`).join('')}<rect x="-46" y="-50" width="9" height="100" rx="4" fill="#7a4a22"/><rect x="37" y="-50" width="9" height="100" rx="4" fill="#7a4a22"/></g>`;}
  const tree=(x,y,k,c1)=>`<g transform="translate(${x} ${y}) scale(${k})"><ellipse cx="4" cy="20" rx="26" ry="7" fill="#2a5a2a" opacity=".18"/><g class="pfm-sway"><rect x="-6" y="-8" width="12" height="28" rx="4" fill="#8a5527"/><circle cx="0" cy="-30" r="26" fill="#3e9a4a"/><circle cx="-16" cy="-16" r="18" fill="#3e9a4a"/><circle cx="16" cy="-16" r="18" fill="#3e9a4a"/><circle cx="-4" cy="-34" r="20" fill="${c1}"/><circle cx="-14" cy="-20" r="12" fill="${c1}"/><circle cx="-10" cy="-42" r="8" fill="#fff" opacity=".22"/></g></g>`;
  const pine=(x,y,k)=>`<g transform="translate(${x} ${y}) scale(${k})"><ellipse cx="3" cy="16" rx="20" ry="6" fill="#2a5a2a" opacity=".18"/><g class="pfm-sway"><rect x="-4" y="2" width="8" height="14" fill="#7a4a22"/><path d="M0 -58 L-22 -20 H22Z" fill="#2f8a4b"/><path d="M0 -40 L-26 4 H26Z" fill="#267a40"/></g></g>`;
  const flower=(x,y,c)=>`<g transform="translate(${x} ${y})"><g class="pfm-sway"><path d="M0 0v-14" stroke="#3e9a4a" stroke-width="2.5"/>${[0,72,144,216,288].map(a=>`<circle cx="${(Math.cos(a*Math.PI/180)*5).toFixed(1)}" cy="${(-18+Math.sin(a*Math.PI/180)*5).toFixed(1)}" r="4.2" fill="${c}"/>`).join('')}<circle cx="0" cy="-18" r="3" fill="#ffd23f"/></g></g>`;
  const bush=(x,y)=>`<g transform="translate(${x} ${y})"><circle cx="-14" cy="0" r="13" fill="#3e9a4a"/><circle cx="6" cy="-5" r="16" fill="#4cae5e"/><circle cx="23" cy="2" r="11" fill="#3e9a4a"/><circle cx="2" cy="-8" r="3" fill="#e2463a"/><circle cx="14" cy="-2" r="3" fill="#e2463a"/></g>`;
  const rock=(x,y)=>`<g transform="translate(${x} ${y})"><path d="M-16 6q-2-14 10-16q12-4 20 6q4 10-4 10z" fill="#b8bfb3"/></g>`;
  const shroom=(x,y)=>`<g transform="translate(${x} ${y})"><rect x="-3" y="-8" width="6" height="10" rx="2" fill="#fff4dc"/><path d="M-11 -8q11-16 22 0z" fill="#e2463a"/><circle cx="-4" cy="-12" r="2" fill="#fff"/></g>`;
  const books=(x,y)=>`<g transform="translate(${x} ${y})"><rect x="-22" y="-8" width="44" height="10" rx="2" fill="#d6452f"/><rect x="-18" y="-18" width="40" height="10" rx="2" fill="#2a6cc0"/><rect x="-20" y="-28" width="38" height="10" rx="2" fill="#f6c02c"/></g>`;
  const house=(x,y,c)=>`<g transform="translate(${x} ${y})"><rect x="18" y="-66" width="10" height="20" fill="#9a6230"/><circle class="pfm-smoke" cx="23" cy="-72" r="6" fill="#fff"/><rect x="-30" y="-34" width="60" height="46" fill="#fff8e8" stroke="#d9c39a" stroke-width="2"/><path d="M-38 -30 L0 -64 L38 -30Z" fill="${c}"/><rect x="-8" y="-12" width="16" height="24" rx="2" fill="#8a5527"/><rect x="-24" y="-24" width="12" height="11" fill="#8fd3ff" stroke="#fff" stroke-width="2"/><rect x="12" y="-24" width="12" height="11" fill="#8fd3ff" stroke="#fff" stroke-width="2"/></g>`;
  const fence=(x,y,n)=>`<g transform="translate(${x} ${y})"><rect x="0" y="-14" width="${n*18}" height="4" fill="#c48a4f"/><rect x="0" y="-4" width="${n*18}" height="4" fill="#c48a4f"/>${Array.from({length:n+1},(_,k)=>`<rect x="${k*18-3}" y="-22" width="6" height="26" rx="2" fill="#a9703a"/>`).join('')}</g>`;
  const fx=end.x>W/2?end.x-(wide?230:150):end.x+(wide?230:150);
  const far=(x,y)=>pts.every(q=>Math.hypot(q.x-x,q.y-y)>(wide?70:56))&&P.every(q=>Math.hypot(q.x-x,q.y-y)>(wide?80:60)&&!(Math.abs(q.x-x)<(wide?120:95)&&y>q.y+20&&y<q.y+110))&&Math.abs(y-riverY)>70&&Math.abs(y-townY)>100&&Math.abs(y-pondY)>60&&Math.hypot(x-fx,y-end.y)>(wide?170:110)&&y>220;
  const L=[];if(townY>0){L.push(house(W*.16,townY+10,'#d6452f'),house(W*.84,townY+20,'#2a6cc0'),fence(W*.06,townY+60,wide?5:3),fence(W*(wide?.76:.7),townY+70,wide?5:3));if(wide)L.push(house(W*.06,townY+40,'#ff8a2a'));}
  if(pondY>0){const px=P[Math.round((N-1)*.5)].x>W/2?W*.2:W*.8;L.push(`<g transform="translate(${px} ${pondY})"><ellipse rx="${wide?90:62}" ry="26" fill="#e8d9a6"/><ellipse rx="${wide?82:56}" ry="21" fill="url(#m-river)"/><g class="pfm-duck"><g transform="translate(-30 -6)"><ellipse rx="10" ry="6" fill="#ffd23f"/><circle cx="8" cy="-6" r="5" fill="#ffd23f"/><path d="M12 -6l5 1-5 2z" fill="#ff8a2a"/></g></g></g>`);}
  L.push(`<g transform="translate(${P[0].x-(wide?150:120)} ${P[0].y-96})"><rect x="-3" y="0" width="7" height="64" fill="#8a5527"/><rect x="-54" y="-6" width="110" height="36" rx="10" fill="#fff8e8" stroke="#b08a4e" stroke-width="3"/><text x="1" y="18" text-anchor="middle" font-weight="800" font-size="15" fill="#17633a">START HERE</text></g>`);
  L.push(`<g transform="translate(${fx} ${end.y-40})"><path d="M-80 70 Q0 30 80 70Z" fill="#9fd284"/><rect x="-56" y="-10" width="112" height="80" fill="url(#m-castle)" stroke="#c9b48a" stroke-width="2"/><rect x="-70" y="-40" width="34" height="110" fill="url(#m-castle)" stroke="#c9b48a" stroke-width="2"/><rect x="36" y="-40" width="34" height="110" fill="url(#m-castle)" stroke="#c9b48a" stroke-width="2"/><path d="M-74 -40 L-53 -72 L-32 -40Z M32 -40 L53 -72 L74 -40Z" fill="#2a6cc0"/><path d="M-60 -10 L0 -50 L60 -10Z" fill="#d6452f"/><rect x="-14" y="30" width="28" height="40" rx="14" fill="#8a5527"/><path d="M0 -50 V-82" stroke="#6b5a45" stroke-width="3"/><path class="pfm-flagwave" d="M1 -82h24l-6 6 6 6H1z" fill="#f6c02c"/><rect x="-62" y="2" width="124" height="22" rx="11" fill="#0f5a2f"/><text x="0" y="17" text-anchor="middle" font-weight="800" font-size="11" fill="#fff">READING CASTLE</text></g>`);
  let seed=11+N;const rnd=()=>(seed=(seed*9301+49297)%233280)/233280;const deco=[];const target=Math.round((wide?12:5)*N);
  for(let k=0;k<target*25&&deco.length<target;k++){const x=16+rnd()*(W-32),y=220+rnd()*(H-260);if(!far(x,y)||deco.some(o=>Math.hypot(o.x-x,o.y-y)<(wide?46:40)))continue;const r=rnd();
   deco.push({x,y,g:r<.22?tree(x,y,.8+rnd()*.5,rnd()<.5?'#6fcf7b':'#5cbf6a'):r<.34?pine(x,y,.8+rnd()*.4):r<.56?flower(x,y,['#ff8fab','#9ad0ff','#ffd23f','#c3a2ff','#ff9f5a'][Math.floor(rnd()*5)]):r<.66?bush(x,y):r<.74?rock(x,y):r<.82?shroom(x,y):r<.88?books(x,y):flower(x,y,'#ffffff')});}
  deco.sort((a,b)=>a.y-b.y);s+=deco.map(o=>o.g).join('')+L.join('');
  P.forEach((p,i)=>{const it=items[i],st=it.s,R=st==='cur'?40:32,href=it.h;const fillId={done:'m-done',cur:'m-cur',open:'m-open',lock:'m-lock'}[st],base={done:'#13612f',cur:'#c97a00',open:'#8fbf98',lock:'#9aa395'}[st];
   let g=`<a class="pfm-node pfm-${st}" data-i="${i}" ${href?`href="${esc(href)}"`:'tabindex="0"'} role="button" aria-label="${esc(it.l+': '+it.t)} (${st==='done'?'completed':st==='cur'?'you are here':st==='open'?'open':'locked'})"><ellipse cx="${p.x}" cy="${p.y+R+4}" rx="${R}" ry="9" fill="#000" opacity=".16"/>`;
   if(st==='cur')g+=`<circle class="pfm-ring" cx="${p.x}" cy="${p.y}" r="${R+2}" fill="none" stroke="#ffd23f" stroke-width="6"/>`;
   g+=`<g class="pfm-disc"><circle cx="${p.x}" cy="${p.y+7}" r="${R}" fill="${base}"/><circle cx="${p.x}" cy="${p.y}" r="${R}" fill="url(#${fillId})" stroke="#fff" stroke-width="5"/><circle cx="${p.x}" cy="${p.y}" r="${R-9}" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="2"/>`;
   if(st==='done')g+=`<path class="pfm-check" d="M${p.x-13} ${p.y+1}l9 9 18-19" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>`;if(st==='done'&&it.c)g+=`<g class="pfm-inclass" transform="translate(${p.x} ${p.y-R-4})"><rect x="-34" y="-12" width="68" height="22" rx="11" fill="#2b55a8" stroke="#fff" stroke-width="2"/><text y="4" text-anchor="middle" font-size="11" font-weight="800" fill="#fff">In class</text></g>`;
   else if(st==='lock')g+=`<g transform="translate(${p.x-10} ${p.y-14})"><path d="M4 10V7a6 6 0 0 1 12 0v3" stroke="#7f887c" stroke-width="3" fill="none"/><rect x="0" y="10" width="20" height="15" rx="4" fill="#7f887c"/></g>`;
   else g+=`<text x="${p.x}" y="${p.y+(st==='cur'?11:9)}" text-anchor="middle" font-weight="800" font-size="${st==='cur'?(i>98?22:30):(i>98?18:24)}" fill="${st==='cur'?'#fff':'#17633a'}">${i+1}</text>`;
   g+=`<ellipse cx="${p.x-10}" cy="${p.y-R*.45}" rx="${R*.5}" ry="${R*.28}" fill="url(#m-shine)"/></g>`;
   if(st==='cur')g+=`<g class="pfm-flag"><rect x="${p.x-50}" y="${p.y-R-50}" width="100" height="34" rx="17" fill="#0f5a2f" stroke="#fff" stroke-width="3"/><path d="M${p.x-8} ${p.y-R-17}l8 10 8-10z" fill="#0f5a2f"/><text x="${p.x}" y="${p.y-R-28}" text-anchor="middle" font-weight="800" font-size="14" fill="#fff">PLAY ▶</text></g>`;
   const name=it.t.length>26?it.t.slice(0,25)+'…':it.t,lw=Math.max(110,(name.length+String(i+1).length+2)*(wide?7.4:8)+26);
   g+=`<g transform="translate(${p.x} ${p.y+R+30})"><rect x="${-lw/2}" y="-14" width="${lw}" height="26" rx="13" fill="#fff" stroke="${st==='done'?'#9fd8a8':st==='cur'?'#f6c02c':'#e2e6df'}" stroke-width="2"/><text y="4" text-anchor="middle" font-weight="700" font-size="${wide?12:13}" fill="${st==='lock'?'#8a958d':'#17633a'}">${i+1}. ${esc(name)}</text></g></a>`;s+=g;});
  svg.innerHTML=s;
  svg.querySelectorAll('.pfm-check').forEach((c,k)=>{c.style.animationDelay=(.2+k*.08)+'s';});
  const tpl=box.querySelector('template.pf-map-kid');if(CUR>=0&&tpl&&tpl.content.firstElementChild){const k=tpl.content.firstElementChild.cloneNode(true),C=P[CUR];k.setAttribute('x',C.x>W/2?C.x-(wide?175:150):C.x+(wide?60:48));k.setAttribute('y',C.y-132);k.setAttribute('width',104);k.setAttribute('height',148);svg.appendChild(k);}
  svg.querySelectorAll('.pfm-node').forEach(n=>n.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();show(+n.dataset.i,W);}));
  svg.querySelectorAll('.pfm-node').forEach(n=>n.addEventListener('keydown',e=>{if(e.key==='Enter'&&!n.hasAttribute('href')){e.preventDefault();show(+n.dataset.i,W);}}));
  if(CUR>=0){const C=P[CUR];requestAnimationFrame(()=>{const r=svg.getBoundingClientRect(),k=r.width/W,y=window.scrollY+r.top+C.y*k-window.innerHeight*.45;if(y>window.scrollY+80)window.scrollTo({top:y,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});});}
  function show(i,W){if(pop)pop.remove();const it=items[i],p=P[i],k=svg.getBoundingClientRect().width/W;pop=document.createElement('div');pop.className='pfm-pop';pop.setAttribute('role','dialog');pop.setAttribute('aria-label',it.t);pop.style.left=Math.min(Math.max(p.x*k,140),card.clientWidth-140)+'px';pop.style.top=(p.y*k)+'px';if(p.y*k<250)pop.classList.add('pfm-below');
   const st=it.s;pop.innerHTML=`<small>${esc(it.l.toUpperCase())}${st==='cur'?' · YOU ARE HERE':''}</small><b>${esc(it.t)}</b>${st==='done'?'<span class="pfm-done">✓ Completed</span>':''}<p>${st==='done'?'Great job! You can review it anytime.':st==='cur'?'Ready for your next adventure?':st==='open'?'You can try this one anytime.':'Finish the lesson before this to unlock it.'}</p>${it.h?`<a class="btn primary" href="${esc(it.h)}">${st==='done'?'Review':'Start'} <span aria-hidden="true">›</span></a>`:''}`;card.appendChild(pop);const a=pop.querySelector('a');if(a)a.focus({preventScroll:true});}
 }
 build();document.addEventListener('click',e=>{if(pop&&!pop.contains(e.target)){pop.remove();pop=null;}});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&pop){pop.remove();pop=null;}});
 let rt;window.addEventListener('resize',()=>{clearTimeout(rt);rt=setTimeout(build,200);});
})();
/* Streak calendar (v30) */
(function(){const ov=document.querySelector('[data-pf-cal]'),open=document.querySelector('[data-pf-cal-open]');if(!ov)return;const inline=ov.hasAttribute('data-inline');
 let learn,sign;try{learn=new Set(JSON.parse(ov.dataset.learn));sign=new Set(JSON.parse(ov.dataset.sign));}catch(e){return;}
 const today=ov.dataset.today,[ty,tm]=today.split('-').map(Number),[fy,fm]=(ov.dataset.first||today.slice(0,7)).split('-').map(Number);let y=ty,m=tm;const minY=Math.max(fy*12+fm-1,ty*12+tm-13);
 const grid=ov.querySelector('[data-pf-cal-grid]'),mon=ov.querySelector('[data-pf-cal-month]'),cnt=ov.querySelector('[data-pf-cal-count]'),cntl=ov.querySelector('[data-pf-cal-countlabel]'),prev=ov.querySelector('[data-pf-cal-prev]'),next=ov.querySelector('[data-pf-cal-next]');
 const MN=['January','February','March','April','May','June','July','August','September','October','November','December'];const pad=n=>String(n).padStart(2,'0');let last=null;
 function render(){const first=new Date(y,m-1,1).getDay(),days=new Date(y,m,0).getDate();mon.textContent=MN[m-1]+' '+y;grid.textContent='';let c=0;
  for(let i=0;i<first;i++){const e=document.createElement('span');e.className='pf-cd pf-empty';grid.appendChild(e);}
  for(let d=1;d<=days;d++){const key=`${y}-${pad(m)}-${pad(d)}`,wd=(first+d-1)%7,L=learn.has(key),S=sign.has(key),fut=key>today;if(L)c++;
   const e=document.createElement('span');e.className='pf-cd';e.setAttribute('role','gridcell');e.style.animationDelay=(d*12)+'ms';
   if(L){const pv=learn.has(`${y}-${pad(m)}-${pad(d-1)}`)&&wd!==0&&d>1,nx=learn.has(`${y}-${pad(m)}-${pad(d+1)}`)&&wd!==6&&d<days;e.classList.add('pf-learn',pv&&nx?'pf-mid':pv?'pf-end':nx?'pf-start':'pf-solo');}
   else if(S)e.classList.add('pf-sign');if(key===today)e.classList.add('pf-today');if(fut)e.classList.add('pf-future');
   e.setAttribute('aria-label',`${MN[m-1]} ${d}: ${L?'learned':S?'signed in':fut?'not yet':'no activity'}${key===today?', today':''}`);
   const b=document.createElement('b');b.textContent=d;e.appendChild(b);if(L){e.insertAdjacentHTML('beforeend','<svg viewBox="0 0 24 30" class="pf-cd-flame" aria-hidden="true"><path d="M12 1c3 7-6 7-3 13 1-1 2-2 2-4 6 4 6 15-1 15S1 21 6 14c0 3 2 4 3 4-2-5 3-8 3-17z" fill="url(#pf-fo)"/></svg>');}grid.appendChild(e);}
  cnt.textContent=c;cntl.textContent='Days learned in '+MN[m-1];prev.disabled=(y*12+m-1)<=minY;next.disabled=y===ty&&m===tm;}
 const show=()=>{y=ty;m=tm;render();ov.hidden=false;last=document.activeElement;setTimeout(()=>ov.querySelector('[data-pf-cal-close]').focus(),50);document.addEventListener('keydown',esc);};
 const hide=()=>{ov.classList.add('pf-cal-out');setTimeout(()=>{ov.hidden=true;ov.classList.remove('pf-cal-out');if(last)last.focus();},250);document.removeEventListener('keydown',esc);};
 const esc=e=>{if(e.key==='Escape')hide();};
 if(open){open.addEventListener('click',show);open.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();show();}});}
 if(inline){render();}else{ov.querySelector('[data-pf-cal-close]').addEventListener('click',hide);ov.addEventListener('click',e=>{if(e.target===ov)hide();});}
 prev.addEventListener('click',()=>{m--;if(m<1){m=12;y--;}render();});next.addEventListener('click',()=>{m++;if(m>12){m=1;y++;}render();});
})();
/* Dashboard and login effects: cards rise, XP sparkles, floating letters, flame sparks (v35) */
(function(){const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const words=['A','b','c','ma','sa','E','i','o','u','ba','la','R','ka','m','s','pa','Aa','d'];
 const letters=(box,n,cols,o)=>{if(!box||still)return;for(let i=0;i<n;i++){const s=document.createElement('span');s.className='pfx-l';s.textContent=words[i%words.length];
  s.style.left=(3+Math.random()*92)+'%';s.style.fontSize=(14+Math.random()*22)+'px';s.style.animationDuration=(9+Math.random()*8)+'s';s.style.animationDelay=(-Math.random()*14)+'s';
  s.style.setProperty('--o',o);s.style.setProperty('--h',-(box.offsetHeight+60)+'px');if(cols)s.style.color=cols[i%cols.length];box.appendChild(s);}};
 letters(document.querySelector('[data-lfx-letters]'),10,null,.22);
 letters(document.querySelector('[data-pfx-letters]'),12,['#5db585','#f0b400','#9ed2b3','#ffc629'],.3);
 const main=document.querySelector('.pupil-stats');if(!main)return;const root=document.body;
 const burst=(host,x,y,n,cls,cols,spread)=>{for(let i=0;i<n;i++){const e=document.createElement('span');e.className=cls;const a=Math.random()*Math.PI*2,r=spread*(.5+Math.random()*.6);
  e.style.left=x+'px';e.style.top=y+'px';e.style.setProperty('--dx',Math.cos(a)*r+'px');e.style.setProperty('--dy',Math.sin(a)*r-spread*.35+'px');if(cols)e.style.background=cols[i%cols.length];host.appendChild(e);setTimeout(()=>e.remove(),1100);}};
 if(!still){root.classList.add('pfx-rise');}
 const xp=document.querySelector('[data-pfx-xp]');
 const go=()=>{if(!still){root.classList.add('pfx-go');setTimeout(()=>root.classList.remove('pfx-rise','pfx-go'),1500);}
  if(!xp)return;const p=xp.querySelector('progress'),sh=xp.querySelector('.pfx-shine'),pct=Math.min(100,100*(+p.getAttribute('value'))/(+p.getAttribute('max')||1));
  sh.style.width=pct+'%';const key='pfx-xp-'+xp.dataset.pfxXp,now=+xp.dataset.xp;let old=null;try{old=localStorage.getItem(key);localStorage.setItem(key,now);}catch(e){}
  if(old!==null&&now>+old&&!still)setTimeout(()=>{const x=xp.offsetWidth*pct/100;const t=document.createElement('span');t.className='pfx-plus';t.textContent='+'+(now-old)+' XP';t.style.left=Math.max(x+40,xp.offsetWidth-34)+'px';xp.appendChild(t);setTimeout(()=>t.remove(),1900);
   burst(xp,x,xp.offsetHeight/2,10,'pfx-star',null,34);},1700);};
 if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',go,{once:true});else go();
 const fl=main.querySelector('.pf-flame');if(fl)fl.addEventListener('click',e=>{e.stopPropagation();const card=fl.closest('.pupil-stat'),cr=card.getBoundingClientRect(),fr=fl.getBoundingClientRect();
  if(!still)burst(card,fr.left-cr.left+fr.width/2,fr.top-cr.top+fr.height*.45,14,'pfx-ember',['#ffb300','#ff7a1a','#ffd966','#e0491d'],70);
  [fl,card.querySelector('[data-count]')].forEach(el=>{if(!el)return;const c=el===fl?'pfx-puff':'pfx-bounce';el.classList.remove(c);void el.getBoundingClientRect();el.classList.add(c);});});
})();

/* Done in class: Class Demo panel and pupil learning path (v36) */
(function(){
 document.querySelectorAll('.cd-bar i[data-w]').forEach(i=>{i.style.width=i.dataset.w+'%';});
 const ov=document.getElementById('cd-panel'),openB=document.querySelector('[data-cd-open]');if(!ov||!openB)return;
 const f=ov.querySelector('form'),btn=f.querySelector('[data-cd-submit] span');
 const count=()=>{const n=f.querySelectorAll('input[name="pupils[]"]:checked').length;btn.textContent=n?'Mark done for '+n+' '+(n===1?'pupil':'pupils'):'Choose pupils first';f.querySelector('[data-cd-submit]').disabled=!n;
  f.querySelectorAll('[data-cd-group]').forEach(g=>{const all=[...g.querySelectorAll('input')];g.querySelector('[data-cd-all]').textContent=all.length&&all.every(i=>i.checked)?'Clear all':'Select all';});};
 const sync=()=>{const jump=document.getElementById('demo-jump');const t=document.querySelectorAll('.demo-template')[jump?+jump.value:0];if(!t)return;
  f.querySelector('[data-cd-aid]').value=t.dataset.id;const bk=f.querySelector('input[name=back]');bk.value=bk.dataset.base+'&activity='+t.dataset.id+'&card='+(t.dataset.card||0);f.querySelector('[data-cd-lid]').value=t.dataset.lesson;
  const c=t.content,meta=(c.querySelector('.demo-slide-meta')||{}).textContent||'',title=(c.querySelector('h1')||{}).textContent||'';const lesson=meta.split(' · ').slice(0,2).join(' · ');
  f.querySelector('[data-cd-act]').textContent=lesson+' · '+title;f.querySelector('[data-cd-les]').textContent=lesson+' · every activity';};
 let last=null;const close=()=>{ov.hidden=true;document.body.classList.remove('cd-lock');if(last)last.focus();};
 openB.addEventListener('click',()=>{last=document.activeElement;sync();count();ov.hidden=false;document.body.classList.add('cd-lock');const r=f.querySelector('input[name=scope]:checked');if(r)r.focus();});
 ov.querySelectorAll('[data-cd-close]').forEach(b=>b.addEventListener('click',close));
 ov.addEventListener('click',e=>{if(e.target===ov)close();});
 ov.addEventListener('keydown',e=>{if(e.key==='Escape'){e.stopPropagation();close();}else e.stopPropagation();});
 f.addEventListener('change',count);
 f.querySelectorAll('[data-cd-all]').forEach(b=>b.addEventListener('click',()=>{const g=b.closest('[data-cd-group]'),all=[...g.querySelectorAll('input')],on=!all.every(i=>i.checked);all.forEach(i=>i.checked=on);count();}));
 f.addEventListener('submit',e=>{const sc=f.querySelector('input[name=scope]:checked');if(sc&&sc.value==='level'&&!confirm('Mark the whole level as done in class for the chosen pupils?'))e.preventDefault();});
})();
/* Manage pupils: search, section filter and "select all" (v37) */
(function(){const list=document.querySelector('.mp-list');if(!list)return;
 const q=list.querySelector('[data-mp-search]'),sec=list.querySelector('[data-mp-section]'),empty=list.querySelector('[data-mp-empty]');
 const run=()=>{const t=(q.value||'').trim().toLowerCase(),s=sec.value;let shown=0;
  list.querySelectorAll('[data-mp-group]').forEach(g=>{let n=0;const okG=!s||g.dataset.mpGroup===s;g.querySelectorAll('.mp-item').forEach(i=>{const ok=okG&&(!t||i.dataset.mpName.includes(t));i.hidden=!ok;if(ok)n++;});g.hidden=!n;shown+=n;});empty.hidden=!!shown;};
 q.addEventListener('input',run);sec.addEventListener('change',run);
 list.querySelectorAll('[data-mp-all]').forEach(b=>b.addEventListener('click',()=>{const boxes=[...b.closest('[data-mp-group]').querySelectorAll('.mp-item:not([hidden]) input')],on=!boxes.every(x=>x.checked);boxes.forEach(x=>x.checked=on);b.textContent=on?'Clear all':'Select all';}));
})();
