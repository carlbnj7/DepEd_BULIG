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
async function saveDraft(){if(submitting||form.dataset.draft!=='on')return;const data=new FormData(form);data.set('action','draft');const status=$('#draft-status');status.textContent='Saving your draft…';try{const res=await fetch('index.php',{method:'POST',body:data,credentials:'same-origin'});const result=await res.json();if(!res.ok||!result.saved)throw new Error(result.error||'Could not save');status.textContent='Draft saved';}catch(error){status.textContent='Draft not saved. Check your connection before leaving.';}}
if(form){
 form.addEventListener('input',draft);
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(submitting)return;submitting=true;clearTimeout(draftTimer);
  const button=$('#submit-answer'),feedback=$('#answer-feedback'),next=$('#next-activity');const original=button?.innerHTML;
  if(button){button.disabled=true;button.textContent='Saving…';}
  try{
   const res=await fetch('index.php',{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json'}});
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
