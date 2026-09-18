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
$('#type-answer')?.addEventListener('click',()=>{$('#response')?.focus();});
for(const select of document.querySelectorAll('[data-section-filter]'))select.addEventListener('change',()=>select.form.requestSubmit());
const canvas=$('#drawing-canvas');
if(canvas){const ctx=canvas.getContext('2d');ctx.lineCap='round';ctx.lineJoin='round';ctx.lineWidth=5;let drawing=false;const output=$('#drawing-data');if(output.value){const image=new Image();image.onload=()=>ctx.drawImage(image,0,0,canvas.width,canvas.height);image.src=output.value;}function point(event){const r=canvas.getBoundingClientRect();return [(event.clientX-r.left)*canvas.width/r.width,(event.clientY-r.top)*canvas.height/r.height];}canvas.addEventListener('pointerdown',event=>{if(form.dataset.draft!=='on')return;drawing=true;canvas.setPointerCapture(event.pointerId);ctx.strokeStyle=$('#pen-color').value;ctx.beginPath();ctx.moveTo(...point(event));});canvas.addEventListener('pointermove',event=>{if(!drawing)return;ctx.lineTo(...point(event));ctx.stroke();});function finish(){if(!drawing)return;drawing=false;output.value=canvas.toDataURL('image/png');drawingDirty=true;draft();}canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);$('#clear-drawing').addEventListener('click',()=>{if(form.dataset.draft!=='on')return;ctx.clearRect(0,0,canvas.width,canvas.height);output.value='';draft();});}
const mic=$('#recognize');
if(mic){const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;const status=$('#speech-status');let recognition=null,active=false;if(!Recognition){mic.disabled=true;status.textContent='Speech recognition is unavailable here. Type your answer or ask your teacher.';}else{mic.addEventListener('click',()=>{if(active){recognition.stop();return;}recognition=new Recognition();recognition.lang='en-US';recognition.continuous=false;recognition.interimResults=false;recognition.onstart=()=>{active=true;mic.textContent='Stop listening';status.textContent='Listening…';window.speechSynthesis?.cancel();};recognition.onresult=event=>{const heard=event.results[0][0].transcript;$('#response').value=canvas&&$('.worksheet-panel')?[$('#response').value,heard].filter(Boolean).join('\n'):heard;$('#transcript').value=heard;status.textContent='Check the words — the microphone can mishear.';showWords($('#expected-text').value,heard);draft();};recognition.onerror=event=>{status.textContent=event.error==='not-allowed'?'Microphone permission was not granted. You can type your answer.':'We could not hear clearly. Try again or type your answer.';};recognition.onend=()=>{active=false;mic.textContent='Speak Answer';};try{recognition.start();}catch{status.textContent='Microphone could not start. Try typing your answer.';}});window.addEventListener('pagehide',()=>recognition?.abort());}}
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
  const url=new URL(location.href);url.searchParams.set('activity',slide.dataset.id);history.replaceState(null,'',url);
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
