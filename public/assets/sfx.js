/* BULIG sound effects (pupils only)
   - Every sound is made here with the Web Audio API: no sound files to download, so they also work offline.
   - Quiet by default. Turned on or off in Settings, and remembered on this device.
   - Silent while BULIG is reading aloud (Listen), so a sound never talks over the narration.
   - Sounds: tap an answer, correct, try again, XP, badge, level complete, daily goal, streak flame, download ready, opening a lesson. */
(function(){
 'use strict';
 const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
 let me=null;try{me=JSON.parse(($('meta[name="bulig-tour"]')||{}).content||'null');}catch(e){}
 if(!me||me.role!=='pupil')return;
 const AC=window.AudioContext||window.webkitAudioContext;if(!AC)return;
 const KEY='bulig-sfx',get=()=>{try{return localStorage.getItem(KEY);}catch(e){return null;}},put=v=>{try{localStorage.setItem(KEY,v);}catch(e){}};
 const isOn=()=>get()!=='0';
 /* iPhone and iPad: follow the silent switch. */
 try{if(navigator.audioSession)navigator.audioSession.type='ambient';}catch(e){}
 let ctx=null,out=null,noiseBuf=null,pending=null;
 function audio(){if(!ctx){try{ctx=new AC();out=ctx.createGain();out.gain.value=.32;out.connect(ctx.destination);}catch(e){ctx=null;}}return ctx;}
 function noise(){if(!noiseBuf){const n=ctx.sampleRate*.6;noiseBuf=ctx.createBuffer(1,n,ctx.sampleRate);const d=noiseBuf.getChannelData(0);for(let i=0;i<n;i++)d[i]=Math.random()*2-1;}return noiseBuf;}
 /* One note: wave type, start and end pitch, when (seconds from now), length, loudness. */
 function tone(type,f1,f2,at,len,vol){const t=ctx.currentTime+at,o=ctx.createOscillator(),g=ctx.createGain();o.type=type;o.frequency.setValueAtTime(f1,t);if(f2&&f2!==f1)o.frequency.exponentialRampToValueAtTime(f2,t+len);
  g.gain.setValueAtTime(0,t);g.gain.linearRampToValueAtTime(vol,t+.012);g.gain.exponentialRampToValueAtTime(.0008,t+len);o.connect(g);g.connect(out);o.start(t);o.stop(t+len+.03);}
 function hiss(at,len,from,to,vol,kind){const t=ctx.currentTime+at,s=ctx.createBufferSource(),f=ctx.createBiquadFilter(),g=ctx.createGain();s.buffer=noise();f.type=kind||'bandpass';f.Q.value=1.4;
  f.frequency.setValueAtTime(from,t);f.frequency.exponentialRampToValueAtTime(to,t+len);g.gain.setValueAtTime(0,t);g.gain.linearRampToValueAtTime(vol,t+len*.3);g.gain.exponentialRampToValueAtTime(.0008,t+len);
  s.connect(f);f.connect(g);g.connect(out);s.start(t);s.stop(t+len+.03);}
 const sparkle=(at,n)=>{for(let i=0;i<n;i++)tone('sine',2000+Math.random()*1800,0,at+i*.05,.12,.08);};
 const NOTES={C5:523.25,E5:659.25,G5:783.99,A5:880,B5:987.77,C6:1046.5,E6:1318.5,G4:392,C4:261.63,E4:329.63,G6:1568};
 const SOUNDS={
  pop(){tone('sine',720,300,0,.08,.55);},
  correct(){tone('triangle',NOTES.E5,0,0,.22,.5);tone('triangle',NOTES.C6,0,.11,.38,.5);sparkle(.16,3);},
  tryagain(){tone('sine',330,262,0,.26,.45);tone('sine',294,220,.16,.3,.35);},
  coin(){tone('square',NOTES.B5,0,0,.08,.16);tone('square',NOTES.E6,0,.07,.32,.16);sparkle(.12,2);},
  badge(){[NOTES.C5,NOTES.E5,NOTES.G5].forEach((f,i)=>tone('triangle',f,0,i*.09,.2,.45));tone('triangle',NOTES.C6,0,.27,.55,.5);tone('sine',NOTES.E6,0,.27,.5,.12);sparkle(.35,5);},
  level(){[NOTES.C5,NOTES.E5,NOTES.G5,NOTES.C6].forEach((f,i)=>tone('triangle',f,0,i*.1,.22,.45));
   [NOTES.C5,NOTES.E5,NOTES.G5,NOTES.C6].forEach(f=>tone('triangle',f,0,.42,.95,.26));tone('sine',NOTES.C4,0,.42,.95,.25);sparkle(.5,9);hiss(.4,.5,3000,9000,.06,'highpass');},
  goal(){[NOTES.G4,NOTES.C5,NOTES.E5,NOTES.G5].forEach((f,i)=>tone('triangle',f,0,i*.08,.18,.42));tone('triangle',NOTES.C6,0,.34,.5,.45);tone('triangle',NOTES.G5,0,.34,.5,.2);sparkle(.4,4);},
  flame(){hiss(0,.42,350,2400,.5);hiss(.05,.3,900,3500,.18,'highpass');},
  ding(){tone('sine',NOTES.E6,0,0,.9,.4);tone('sine',NOTES.E6*2.01,0,0,.4,.08);tone('sine',NOTES.B5,0,.1,.7,.18);},
  page(){hiss(0,.2,5000,1400,.35,'highpass');tone('sine',500,900,.02,.08,.06);}
 };
 const busy=()=>{try{return window.speechSynthesis&&(speechSynthesis.speaking||speechSynthesis.pending);}catch(e){return false;}};
 /* Browsers allow sound only after a tap on the page. A sound asked for before that waits for the first tap (only a moment). */
 function play(name,force){if(!SOUNDS[name]||(!force&&(!isOn()||busy())))return;if(!audio())return;
  const go=()=>{try{SOUNDS[name]();document.dispatchEvent(new CustomEvent('bulig:sfx',{detail:name}));}catch(e){}};
  if(ctx.state==='running')return go();
  ctx.resume().then(()=>{if(ctx.state==='running')go();else pending={name,at:Date.now()};}).catch(()=>{pending={name,at:Date.now()};});
  setTimeout(()=>{if(ctx.state!=='running')pending={name,at:Date.now()};},120);}
 const unlock=()=>{if(!audio())return;if(ctx.state!=='running')ctx.resume().then(()=>{if(pending&&Date.now()-pending.at<1500&&isOn())try{SOUNDS[pending.name]();}catch(e){}pending=null;}).catch(()=>{});};
 ['pointerdown','keydown','touchstart'].forEach(ev=>document.addEventListener(ev,unlock,{capture:true,passive:true}));
 window.buligSfx={play:n=>play(n),on:isOn};

 /* 1. Tapping an answer */
 document.addEventListener('click',e=>{const t=e.target;if(!t.closest)return;
  if(t.closest('.tap-choices li,.native-choice,.choice-option,.sentence-card input[type=radio],.native-card input[type=radio],.native-card input[type=checkbox]'))play('pop');},true);
 /* 2-4. Correct, try again, and XP */
 const fb=$('#answer-feedback');
 if(fb){let last='';new MutationObserver(()=>{const txt=fb.textContent.trim(),now=fb.className.replace(/\s*gentle/,'')+'|'+txt;if(now===last||!txt)return;last=now;
  if(fb.classList.contains('success')){play('correct');if(fb.querySelector('.earned-xp'))setTimeout(()=>play('coin'),520);}
  else if(fb.classList.contains('tryagain'))play('tryagain');}).observe(fb,{attributes:true,attributeFilter:['class'],childList:true,subtree:true});}
 /* 5-6. Celebration windows: badge, new level open, level complete */
 $$('[data-pf-overlay]').forEach(o=>{const cel=()=>{if(o.hidden||o.dataset.sfx||!o.isConnected)return;o.dataset.sfx='1';play(o.classList.contains('lc-done')?'level':'badge');};
  if(!o.hidden)setTimeout(cel,450);new MutationObserver(cel).observe(o,{attributes:true,attributeFilter:['hidden']});});
 /* New badge flying onto the shelf, XP going up on the home page, download finished, daily goal reached */
 new MutationObserver(list=>{for(const m of list)for(const n of m.addedNodes){if(n.nodeType!==1)continue;
   if(n.classList.contains('bdg-ov'))play('badge');
   else if(n.classList.contains('pfx-plus'))play('coin');
   else if(n.classList.contains('dl-ov')&&n.classList.contains('dl-done'))play('ding');}}).observe(document.body,{childList:true,subtree:true});
 const goal=$('.goal-card');if(goal)new MutationObserver(()=>{if(goal.classList.contains('goal-pop')&&!goal.dataset.sfx){goal.dataset.sfx='1';play('goal');}}).observe(goal,{attributes:true,attributeFilter:['class']});
 /* 7. Streak flame: when the streak grows, and when the flame is tapped */
 const fl=$('.pf-flame');
 if(fl){fl.addEventListener('click',()=>play('flame'));
  const card=fl.closest('.pupil-stat'),cnt=card&&card.querySelector('[data-count]'),n=cnt?parseInt(cnt.getAttribute('data-count')||cnt.textContent,10):NaN,k='bulig-sfx-streak-'+me.id;
  if(!isNaN(n)){let old=null;try{old=localStorage.getItem(k);localStorage.setItem(k,n);}catch(e){}
   if(old!==null&&n>+old){const go=()=>setTimeout(()=>play('flame'),900);if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',go,{once:true});else go();}}}
 /* 9. Download finished quietly in the background */
 const tst=window.buligToast;if(tst)window.buligToast=(t,k,ms)=>{if(/ready offline/i.test(t||''))play('ding');return tst(t,k,ms);};
 /* 10. Opening a lesson */
 if(new URLSearchParams(location.search).get('page')==='lesson'&&!new URLSearchParams(location.search).get('activity'))setTimeout(()=>play('page'),250);

 /* ---------- Settings: Sound effects switch ---------- */
 const sw=$('[data-sfx-switch]');
 if(sw){const row=sw.closest('.st-sfx');if(row)row.hidden=false;sw.checked=isOn();
  sw.addEventListener('change',()=>{put(sw.checked?'1':'0');if(sw.checked)play('correct',true);if(tst)tst(sw.checked?'Sound effects are on.':'Sound effects are off.',sw.checked?'ok':'in');});}
})();
