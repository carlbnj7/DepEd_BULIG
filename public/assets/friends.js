/* "Meet your BULIG friends" (v163): once, the first time a pupil opens Home. George first, then each friend pops in and says
   hello in their own voice (girls a female voice, boys a male voice, each with their own pitch and speed), the words lighting
   up as they speak, and their special pose comes out. Last, everyone together. It waits for the welcome and celebration
   panels (app.js calls window.buligFriendsStart), and the tour offer, the save-login offer and the rest wait for it.
   Offline it waits for another day (the pictures come from the server). The server remembers it (action friends_met). */
(function(){
 'use strict';
 const meta=document.querySelector('meta[name="bulig-friends"]');if(!meta)return;
 const page=new URLSearchParams(location.search).get('page')||'dashboard';if(page!=='dashboard'||!navigator.onLine)return;
 let D;try{D=JSON.parse(meta.content);}catch(e){return;}const F=D.friends||[];if(!F.length)return;
 F.forEach(f=>[f.hello,f.special,f.last].forEach(s=>{const i=new Image();i.src=s;}));
 const SVG={next:'<path d="M5 12h14M13 6l6 6-6 6"/>',again:'<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>',on:'<path d="m11 4-5 4H3v8h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>',off:'<path d="m11 4-5 4H3v8h3l5 4z"/><path d="m16 9 6 6m0-6-6 6"/>',star:'<path d="m12 2 3 6.5 7 .8-5.2 4.8 1.5 7L12 17.6 5.7 21l1.5-7L2 9.3l7-.8z" fill="currentColor" stroke="none"/>'};
 const svg=k=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+SVG[k]+'</svg>';
 const el=(t,c,h)=>{const e=document.createElement(t);if(c)e.className=c;if(h!=null)e.innerHTML=h;return e;};

 /* ---------- voices ---------- */
 const synth=window.speechSynthesis||null;let talkOn=true;try{talkOn=localStorage.getItem('bulig-tour-voice')!=='off';}catch(e){}
 const FEM=/female|woman|zira|aria|jenny|ava|emma|michelle|libby|sonia|samantha|karen|moira|tessa|serena|fiona|allison|susan|hazel|natasha|clara|joanna|salli|kimberly|ivy|kendra|amy|olivia|google us english/i;
 const MAL=/\bmale\b|guy|davis|tony|jason|ryan|thomas|daniel|alex|aaron|fred|arthur|oliver|matthew|justin|joey|brian|eric|christopher|andrew|brandon|william|liam|steffan|roger|james|david|mark|george/i;
 const ROBOT=/espeak|zarvox|whisper|bad news|bells|boing|bubbles|cellos|jester|organ|trinoids|robot|superstar|wobble|bahh|albert/i;
 function pool(){if(!synth)return{f:[],m:[],all:[]};let v=synth.getVoices().filter(x=>/^en/i.test(x.lang)&&!ROBOT.test(x.name));
  const good=x=>(/natural|neural|premium|enhanced|online/i.test(x.name)?2:0)+(/google/i.test(x.name)?1:0);v.sort((a,b)=>good(b)-good(a));
  const isF=x=>FEM.test(x.name)&&!/\bmale\b/i.test(x.name.replace(/female/ig,''));
  return{f:v.filter(isF),m:v.filter(x=>!isF(x)&&MAL.test(x.name)),all:v};}
 function voiceFor(f){const V=pool(),list=f.girl?V.f:V.m,same=F.filter(x=>x.girl===f.girl);if(list.length)return list[same.indexOf(f)%list.length];return V.all[0]||null;}
 let words=[],timer=0,box=null;
 function lightAll(){words.forEach(w=>w.el.classList.add('on'));}
 function hush(){if(synth)synth.cancel();clearInterval(timer);if(box)box.classList.remove('fr-talking');}
 function say(f,text){hush();
  if(!talkOn||!synth){let i=0;box.classList.add('fr-talking');timer=setInterval(()=>{if(words[i])words[i++].el.classList.add('on');else{clearInterval(timer);box&&box.classList.remove('fr-talking');}},210);return;}
  const run=()=>{const u=new SpeechSynthesisUtterance(text),v=voiceFor(f);if(v){u.voice=v;u.lang=v.lang;}else u.lang='en-US';u.pitch=f.pitch||1;u.rate=f.rate||.95;
   u.onstart=()=>box&&box.classList.add('fr-talking');
   u.onboundary=e=>{if(e.name&&e.name!=='word')return;words.forEach(w=>w.el.classList.toggle('on',w.at<=e.charIndex));};
   u.onend=u.onerror=()=>{if(box)box.classList.remove('fr-talking');lightAll();};synth.speak(u);};
  if(synth.getVoices().length)setTimeout(run,140);else{let done=false;const go=()=>{if(done)return;done=true;run();};synth.addEventListener('voiceschanged',go,{once:true});setTimeout(go,700);}}

 /* ---------- screens ---------- */
 let layer=null,idx=-1,finish=null,swap=0;
 const tint=(node,c)=>{node.style.setProperty('--fr-c',c);};
 function frame(cls){layer.innerHTML='';const p=el('div','fr-panel '+(cls||''));layer.append(p);box=p;return p;}
 function keys(e){if(!layer)return;if(e.key==='Escape'){e.preventDefault();end();}}
 function intro(){const g=F[0],p=frame('fr-start');tint(p,g.c);
  p.append(el('div','fr-stage',''),el('div','fr-side',''));
  const img=el('img','fr-kid fr-in');img.src=g.hello;img.alt='';p.firstChild.append(el('div','fr-rays'),img);
  const s=p.lastChild;s.append(el('span','fr-eyebrow','NEW FRIENDS'));const h=el('h2');h.textContent='Hi, '+D.name+'!';s.append(h);
  const t=el('p','fr-lead');t.textContent='George and her friends want to meet you. Each one will help you in BULIG.';s.append(t);
  const team=el('div','fr-dots');F.forEach(f=>{const i=el('i');const m=new Image();m.src=f.hello;m.alt='';i.append(m);team.append(i);});s.append(team);
  const a=el('div','fr-actions');const go=el('button','btn primary fr-next','Meet my friends'+svg('next'));go.type='button';go.onclick=()=>show(0);a.append(go);s.append(a);
  const sk=el('button','fr-skip','Skip');sk.type='button';sk.onclick=end;s.append(sk);setTimeout(()=>go.focus({preventScroll:true}),80);}
 function show(n){hush();clearTimeout(swap);idx=n;if(n>=F.length)return last();const f=F[n],text=f.line.replace('{n}',D.name),p=frame();tint(p,f.c);
  const stage=el('div','fr-stage');const img=el('img','fr-kid fr-in');img.src=f.hello;img.alt=f.name+' '+f.title;
  stage.append(el('div','fr-rays'),el('span','fr-count','FRIEND '+(n+1)+' OF '+F.length),img);
  const s=el('div','fr-side');s.append(el('span','fr-eyebrow','MEET YOUR BULIG FRIENDS'));
  const plate=el('div','fr-plate');plate.append(el('i','fr-dot'));const h=el('h2');h.textContent=f.name;const sub=el('span');sub.textContent=f.title;h.append(sub);plate.append(h);s.append(plate);
  const b=el('div','fr-bubble');b.setAttribute('aria-live','polite');words=[];let at=0;text.split(' ').forEach(w=>{const sp=el('span','fr-w');sp.textContent=w+' ';b.append(sp);words.push({el:sp,at});at+=w.length+1;});s.append(b);
  const job=el('div','fr-job',svg('star'));const js=el('span');js.textContent=f.job;job.append(js);s.append(job);
  const dots=el('div','fr-dots');F.forEach((x,k)=>{const i=el('i',k===n?'on':k<n?'done':'');const m=new Image();m.src=x.hello;m.alt='';i.append(m);dots.append(i);});s.append(dots);
  const a=el('div','fr-actions');const v=el('button','fr-round',svg(talkOn?'on':'off'));v.type='button';v.setAttribute('aria-label',talkOn?'Voice on':'Voice off');
  v.onclick=()=>{talkOn=!talkOn;try{localStorage.setItem('bulig-tour-voice',talkOn?'on':'off');}catch(e){}v.innerHTML=svg(talkOn?'on':'off');v.setAttribute('aria-label',talkOn?'Voice on':'Voice off');if(talkOn)say(f,text);else{hush();lightAll();}};
  const r=el('button','fr-round',svg('again'));r.type='button';r.setAttribute('aria-label','Hear '+f.name+' again');r.onclick=()=>{words.forEach(w=>w.el.classList.remove('on'));say(f,text);};
  const nx=el('button','btn primary fr-next',(n===F.length-1?'Meet everyone':'Next friend')+svg('next'));nx.type='button';nx.onclick=()=>show(n+1);a.append(v,r,nx);s.append(a);
  const foot=el('div','fr-foot');foot.append(el('span','','Each friend has their own voice.'));const sk=el('button','fr-skip','Skip');sk.type='button';sk.onclick=end;foot.append(sk);s.append(foot);
  p.append(stage,s);swap=setTimeout(()=>{if(idx===n)img.src=f.special;},1400);say(f,text);setTimeout(()=>nx.focus({preventScroll:true}),80);}
 function last(){hush();const p=frame('fr-final');tint(p,'#16842f');const stage=el('div','fr-stage');stage.append(el('div','fr-rays'));const team=el('div','fr-team');
  F.forEach((f,k)=>{const m=new Image();m.src=f.last;m.alt=f.name;m.className='fr-t'+k;team.append(m);});stage.append(team);
  const s=el('div','fr-side');s.append(el('span','fr-eyebrow','YOUR BULIG FRIENDS'));const h=el('h2');h.textContent='We are all here to help you read, '+D.name+'!';s.append(h);
  s.append(el('p','fr-lead',D.tour?'Look for us on every page. George will show you around next.':'Look for us on every page. Tap the yellow ? anytime and George will show you around.'));
  const a=el('div','fr-actions');const ok=el('button','btn primary fr-next','Let’s read!'+svg('next'));ok.type='button';ok.onclick=end;a.append(ok);s.append(a);p.append(stage,s);
  const g=F[0];words=[];say({girl:true,pitch:g.pitch,rate:g.rate},'We are all here to help you read, '+D.name+'! Let’s read together!');setTimeout(()=>ok.focus({preventScroll:true}),80);}
 function saved(){window.buligFriendsMet=true;try{const fd=new FormData();fd.set('csrf',(document.querySelector('meta[name="csrf"]')||{}).content||'');fd.set('action','friends_met');fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'},keepalive:true}).catch(()=>{});}catch(e){}}
 function end(){hush();clearTimeout(swap);saved();document.removeEventListener('keydown',keys,true);document.body.classList.remove('fr-on');
  if(layer){const l=layer;layer=null;l.classList.add('fr-out');setTimeout(()=>l.remove(),300);}box=null;const f=finish;finish=null;if(f)setTimeout(f,320);}
 /* app.js opens it after the welcome and celebration panels */
 window.buligFriendsStart=function(done){finish=done;layer=el('div','fr-layer');layer.setAttribute('role','dialog');layer.setAttribute('aria-modal','true');layer.setAttribute('aria-label','Meet your BULIG friends');
  document.body.append(layer);document.body.classList.add('fr-on');document.addEventListener('keydown',keys,true);setTimeout(intro,500);};
})();

/* Roxanne the Timekeeper: after 30 minutes of learning in one sitting, a gentle "time for a short break" note (v164).
   The sitting starts at the first page opened in this browser tab session; the note comes again after another 30 minutes. */
(function(){
 'use strict';
 let me=null;try{me=JSON.parse((document.querySelector('meta[name="bulig-tour"]')||{}).content||'null');}catch(e){}
 if(!me||me.role!=='pupil'||!me.pals||!me.pals.rest)return;
 const MIN=30*60*1000,K='bulig-rest-'+me.id;let s=null;try{s=JSON.parse(sessionStorage.getItem(K)||'null');}catch(e){}
 if(!s||typeof s.from!=='number'){s={from:Date.now()};try{sessionStorage.setItem(K,JSON.stringify(s));}catch(e){}}
 const busy=()=>document.querySelector('.fr-layer,.tour-layer,.tour-offer,.pw-overlay:not([hidden]),.dl-ov,.rm-ov,.rx-rest');
 function show(){if(busy())return;const d=document.createElement('div');d.className='rx-rest';d.setAttribute('role','status');
  const im=new Image();im.src=me.pals.rest;im.alt='';const tx=document.createElement('div');const b=document.createElement('b');b.textContent='Time for a short break, '+me.name+'!';
  const p=document.createElement('span');p.textContent='You have been learning for 30 minutes. Great work! Rest your eyes, stretch, and drink some water.';tx.append(b,p);
  const ok=document.createElement('button');ok.type='button';ok.className='btn primary';ok.textContent='Okay, Roxanne!';ok.onclick=()=>{d.classList.add('out');setTimeout(()=>d.remove(),300);};
  d.append(im,tx,ok);document.body.append(d);s.from=Date.now();try{sessionStorage.setItem(K,JSON.stringify(s));}catch(e){}}
 const check=()=>{if(Date.now()-s.from>=MIN)show();};
 setTimeout(check,4000);setInterval(check,60000);
})();

/* A friend standing on a page (v168): says the message when the page opens, then again when tapped. */
(function(){
 const f=document.querySelector('[data-fs]');if(!f)return;let t=0;
 const say=()=>{f.classList.add('on');clearTimeout(t);t=setTimeout(()=>f.classList.remove('on'),4000);};
 setTimeout(say,900);f.addEventListener('click',say);
})();
