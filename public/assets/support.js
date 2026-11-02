/* v204 Reading support (app/reading_support.php): My practice words (hear a word, then say it to Max)
   and the offline sync report for the teacher. */
(()=>{
 'use strict';
 const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
 const box=$('.rs-practice');if(!box)return;
 const synth=window.speechSynthesis||null,Rec=window.SpeechRecognition||window.webkitSpeechRecognition;
 const voice=()=>{if(!synth)return null;const vs=synth.getVoices();let pref=null;try{pref=localStorage.getItem('bulig-voice');}catch(e){}
  return vs.find(v=>v.voiceURI===pref)||vs.find(v=>/^en/i.test(v.lang)&&/female|samantha|zira|susan|karen|aria|jenny|ava|hazel/i.test(v.name))||vs.find(v=>/^en/i.test(v.lang))||null;};
 const norm=w=>String(w).toLowerCase().replace(/[^a-z0-9']/g,'');
 function say(t,btn){if(!synth)return;synth.cancel();const u=new SpeechSynthesisUtterance(t);const v=voice();if(v)u.voice=v;u.lang=v?v.lang:'en-US';u.rate=.8;
  if(btn){btn.classList.add('on');const off=()=>btn.classList.remove('on');u.onend=off;u.onerror=off;}synth.speak(u);}
 box.addEventListener('click',e=>{const b=e.target.closest('[data-say]');if(b){e.preventDefault();say(b.dataset.say,b);}});
 /* Say it: Max listens for the word (practice only: nothing is saved or sent). */
 let live=null;
 const done=$('[data-rs-done]'),count=()=>{if(done){const n=$$('.rs-pwl li.ok').length,all=$$('.rs-pwl li').length;done.textContent=n+' of '+all+' said right';}};
 $$('.rs-pwl li').forEach(li=>{
  const word=li.dataset.word||'';const b=li.querySelector('.rs-mic');if(!b)return;
  if(!Rec){b.hidden=true;return;}
  b.addEventListener('click',()=>{
   if(live){try{live.abort();}catch(e){}live=null;}
   if(synth)synth.cancel();
   const r=new Rec();live=r;r.lang='en-US';r.interimResults=true;r.maxAlternatives=5;let got=false;
   li.classList.remove('ok','no');li.classList.add('listen');b.setAttribute('aria-pressed','true');
   r.onresult=ev=>{for(const res of ev.results)for(const alt of res)if(alt.transcript.split(/\s+/).some(x=>norm(x)===norm(word))){got=true;try{r.stop();}catch(e){}}};
   const end=()=>{if(live!==r)return;live=null;li.classList.remove('listen');b.setAttribute('aria-pressed','false');li.classList.add(got?'ok':'no');
    const m=li.querySelector('.rs-msg');if(m)m.textContent=got?'Great!':'Try again';count();};
   r.onend=end;r.onerror=end;try{r.start();}catch(e){end();}
   setTimeout(()=>{if(live===r)try{r.stop();}catch(e){}},5000);
  });
 });
 count();
})();
/* Offline status for the teacher: how many answers are still waiting on this phone (sent when online). */
(()=>{
 'use strict';
 const uid=(document.querySelector('meta[name="bulig-uid"]')||{}).content||'0';const csrf=(document.querySelector('meta[name="csrf"]')||{}).content||'';
 if(uid==='0'||!csrf||!navigator.onLine)return;
 const report=()=>{const o=window.buligOffline;if(!o||!o.waitingInfo)return;
  o.waitingInfo().then(i=>{const key='bulig-offrep-'+uid,sig=i.n+'|'+i.why;let last=null;try{last=sessionStorage.getItem(key);}catch(e){}
   if(last===sig)return;
   const fd=new FormData();fd.set('csrf',csrf);fd.set('action','offline_report');fd.set('n',String(i.n));fd.set('oldest',i.oldest||'');fd.set('why',i.why||'');
   fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>{if(r.ok)try{sessionStorage.setItem(key,sig);}catch(e){}}).catch(()=>{});
  }).catch(()=>{});};
 /* after the automatic upload had its chance */
 window.addEventListener('load',()=>setTimeout(report,6000));
})();
