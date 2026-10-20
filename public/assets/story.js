/* Story time (app/story.php): turn pages without reloading, read each page aloud with a voice for every friend
   (Jarvis in his robot voice) while each word lights up, full screen for the class, and the print page.
   Listen reads the whole story: when a page is done it turns to the next one by itself. */
(function(){
 'use strict';
 const root=document.getElementById('cm-print-root')||document.querySelector('.cm-printing');
 if(root){ /* print page: only the comic pages on screen and on paper */
  const box=document.createElement('div');box.id='cm-print-root';const bar=document.querySelector('.cm-print-bar');if(bar)box.appendChild(bar);box.appendChild(root);document.body.appendChild(box);
  document.documentElement.classList.add('cm-print');
  const b=document.querySelector('[data-story-print]');if(b)b.addEventListener('click',()=>window.print());
  return;
 }
 const book=document.querySelector('[data-story]');if(!book)return;
 const count=+book.dataset.pages||0;let girls=[];try{girls=JSON.parse(book.dataset.girls||'[]');}catch(e){}
 let pages=null,loading=null,cur=+(book.querySelector('.cm-page')||{dataset:{n:0}}).dataset.n||0;
 function load(){if(pages)return Promise.resolve(pages);if(!loading)loading=fetch('?page=story_data',{credentials:'same-origin'}).then(r=>r.ok?r.json():Promise.reject()).then(d=>{pages=d;(d||[]).forEach(p=>{const t=document.createElement('template');t.innerHTML=p.html;t.content.querySelectorAll('image').forEach(i=>{const im=new Image();im.src=i.getAttribute('href');});});return d;}).catch(()=>{loading=null;return null;});return loading;}
 function show(n,keepReading){
  n=Math.max(0,Math.min(count-1,n));if(!pages||!pages[n])return false;
  if(!keepReading)stop();
  book.innerHTML=pages[n].html;cur=n;book.classList.remove('turn');void book.offsetWidth;book.classList.add('turn');
  try{history.replaceState(null,'','?page=story&p='+n);}catch(e){}
  seen();
  if(!document.fullscreenElement){const top=book.getBoundingClientRect().top;if(top<0||top>window.innerHeight*.5)book.scrollIntoView({behavior:'smooth',block:'start'});}
  return true;
 }
 book.addEventListener('click',e=>{
  const go=e.target.closest('[data-story-go]');
  if(go){if(pages){e.preventDefault();show(+go.dataset.storyGo);}return;}
  if(e.target.closest('[data-story-listen]')){e.preventDefault();reading?stop():listen();return;}
  if(e.target.closest('[data-story-full]')){e.preventDefault();full();}
 });
 document.addEventListener('keydown',e=>{if(e.target.closest('input,textarea,select'))return;if(e.key==='ArrowRight'&&cur<count-1){if(show(cur+1))e.preventDefault();}else if(e.key==='ArrowLeft'&&cur>0){if(show(cur-1))e.preventDefault();}});
 let sx=null,sy=0;book.addEventListener('touchstart',e=>{if(e.target.closest('.cm-fig')){sx=e.touches[0].clientX;sy=e.touches[0].clientY;}},{passive:true});
 book.addEventListener('touchend',e=>{if(sx===null)return;const dx=e.changedTouches[0].clientX-sx,dy=e.changedTouches[0].clientY-sy;sx=null;if(Math.abs(dx)>60&&Math.abs(dx)>Math.abs(dy)*1.5)show(cur+(dx<0?1:-1));},{passive:true});
 function full(){if(document.fullscreenElement){document.exitFullscreen();return;}if(book.requestFullscreen)book.requestFullscreen().catch(()=>{});}
 setTimeout(load,1200);

 /* ---------- XP for pupils: a page counts after it has been on screen for 4 seconds ---------- */
 let xpTimer=0;const done=new Set();
 function seen(){if(!book.dataset.xp)return;clearTimeout(xpTimer);const n=cur;if(done.has(n))return;
  xpTimer=setTimeout(()=>{if(n!==cur||document.hidden)return;done.add(n);const fd=new FormData();fd.set('action','story_seen');fd.set('csrf',book.dataset.csrf||'');fd.set('p',n);
   fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():null).then(d=>{if(!d)return;
    const bar=document.querySelector('[data-story-bar]'),s=document.querySelector('[data-story-seen]'),left=document.querySelector('[data-story-left]'),box=document.querySelector('[data-story-xp]');
    if(bar)bar.value=d.seen;if(s)s.textContent=d.seen;
    if(left)left.textContent=d.seen>=d.count?'Story finished! You earned all the story XP.':'+'+book.dataset.xp+' XP for each new page · '+(d.count-d.seen)+' pages left';
    if(box)box.classList.toggle('done',d.seen>=d.count);
    if(d.gained>0){const t=document.createElement('div');t.className='cm-gain'+(d.done?' big':'');t.textContent=d.done?'+'+d.gained+' XP · Story finished!':'+'+d.gained+' XP';document.body.appendChild(t);setTimeout(()=>t.remove(),1900);}
   }).catch(()=>done.delete(n));},4000);}
 seen();

 /* ---------- reading aloud ---------- */
 const synth=window.speechSynthesis||null;let reading=false,timer=0,run=0;
 const FEM=/female|woman|zira|aria|jenny|ava|emma|michelle|libby|sonia|samantha|karen|moira|tessa|serena|fiona|allison|susan|hazel|natasha|clara|joanna|salli|kimberly|ivy|kendra|amy|olivia|google us english/i;
 const MAL=/\bmale\b|guy|davis|tony|jason|ryan|thomas|daniel|alex|aaron|fred|arthur|oliver|matthew|justin|joey|brian|eric|christopher|andrew|brandon|william|liam|steffan|roger|james|david|mark|george/i;
 const ODD=/espeak|zarvox|whisper|bad news|bells|boing|bubbles|cellos|jester|organ|trinoids|robot|superstar|wobble|bahh|albert/i;
 function pool(){if(!synth)return{f:[],m:[],all:[]};const v=synth.getVoices().filter(x=>/^en/i.test(x.lang)&&!ODD.test(x.name));
  const good=x=>(/natural|neural|premium|enhanced|online/i.test(x.name)?2:0)+(/google/i.test(x.name)?1:0);v.sort((a,b)=>good(b)-good(a));
  const isF=x=>FEM.test(x.name)&&!/\bmale\b/i.test(x.name.replace(/female/ig,''));return{f:v.filter(isF),m:v.filter(x=>!isF(x)&&MAL.test(x.name)),all:v};}
 const BOYS=['Max','Bruno','Toby','Zion','Kai','Claude'];
 function voice(who){const V=pool();
  if(who==='Jarvis'||who==='Robot')return{v:V.m[0]||V.all[0]||null,pitch:1.6,rate:1.02};
  if(who==='Narrator')return{v:V.f[0]||V.all[0]||null,pitch:1.05,rate:.9};
  if(girls.indexOf(who)>=0){const i=Math.max(0,girls.indexOf(who));return{v:V.f.length?V.f[i%V.f.length]:(V.all[0]||null),pitch:1.25,rate:.95};}
  const i=Math.max(0,BOYS.indexOf(who));return{v:V.m.length?V.m[i%V.m.length]:(V.all[0]||null),pitch:who==='Claude'?.95:1.15,rate:.95};}
 let actx=null;function beep(){try{const C=window.AudioContext||window.webkitAudioContext;if(!C)return;actx=actx||new C();if(actx.state==='suspended')actx.resume();const t=actx.currentTime;
  [[740,0],[988,.09],[1319,.18]].forEach(([f,d])=>{const o=actx.createOscillator(),g=actx.createGain();o.type='square';o.frequency.value=f;g.gain.setValueAtTime(.0001,t+d);g.gain.exponentialRampToValueAtTime(.04,t+d+.012);g.gain.exponentialRampToValueAtTime(.0001,t+d+.08);o.connect(g).connect(actx.destination);o.start(t+d);o.stop(t+d+.09);});}catch(e){}}
 function lines(){const out=[];book.querySelectorAll('.cm-script [data-line]').forEach(p=>{const ws=[...p.querySelectorAll('.w')];out.push({el:p,who:p.dataset.who||'Narrator',words:ws,text:ws.map(w=>w.textContent).join(' '),i:p.dataset.line});});return out;}
 function mark(l,on){if(!l)return;l.el.classList.toggle('on',on);const g=book.querySelector('.cm-say[data-line="'+l.i+'"]');if(g)g.classList.toggle('on',on);if(!on)l.words.forEach(w=>w.classList.remove('on'));}
 function setBtn(){book.classList.toggle('reading',reading);const b=book.querySelector('[data-story-listen] span');if(b)b.textContent=reading?'Stop':'Listen';}
 function stop(){reading=false;run++;clearTimeout(timer);if(synth)synth.cancel();book.querySelectorAll('.on').forEach(x=>{if(!x.closest('.cm-dots'))x.classList.remove('on');});setBtn();}
 function speak(text,who,onWord,done){
  if(!synth){timer=setTimeout(done,Math.max(1500,text.split(' ').length*380));return;}
  const s=voice(who),u=new SpeechSynthesisUtterance(text);if(s.v){u.voice=s.v;u.lang=s.v.lang;}else u.lang='en-US';u.pitch=s.pitch;u.rate=s.rate;
  let ended=false;const fin=()=>{if(ended)return;ended=true;clearTimeout(guard);done();};
  const guard=setTimeout(fin,text.length*140+6000);
  u.onboundary=e=>{if(e.name&&e.name!=='word')return;onWord(e.charIndex||0);};u.onend=fin;u.onerror=fin;
  if(who==='Jarvis'||who==='Robot')beep();synth.speak(u);
 }
 function readPage(id){
  const L=lines();let k=0;
  const next=()=>{if(id!==run||!reading)return;
   if(k>=L.length){if(cur<count-1){timer=setTimeout(()=>{if(id!==run)return;if(pages&&show(cur+1,true))readPage(id);else stop();},1100);}else{stop();}return;}
   const l=L[k++];const starts=[];let pos=0;l.words.forEach(w=>{starts.push(pos);pos+=w.textContent.length+1;});
   mark(l,true);if(window.matchMedia('(max-width:740px)').matches)l.el.scrollIntoView({behavior:'smooth',block:'nearest'});
   speak(l.text,l.who,ci=>{let j=0;while(j+1<starts.length&&starts[j+1]<=ci)j++;l.words.forEach((w,x)=>w.classList.toggle('on',x===j));},()=>{mark(l,false);if(id===run&&reading)timer=setTimeout(next,350);});
  };
  if(!L.length){const t=(book.querySelector('.cm-title')||{}).textContent||'';speak(cur===0?'How the BULIG Friends Met. A BULIG comic story.':t,'Narrator',()=>{},()=>{k=0;next();});return;}
  next();
 }
 function listen(){if(synth)synth.cancel();reading=true;const id=++run;setBtn();load().then(()=>{if(id===run)readPage(id);});}
 window.addEventListener('pagehide',()=>{if(synth)synth.cancel();});
 document.addEventListener('visibilitychange',()=>{if(document.hidden&&reading)stop();});
})();
