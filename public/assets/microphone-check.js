(()=>{
 'use strict';
 const start=document.getElementById('start'),stop=document.getElementById('stop'),heard=document.getElementById('heard'),log=document.getElementById('log');
 let current=null,timer=null;
 const note=s=>{log.textContent+=s+'\n';};
 const reset=()=>{clearTimeout(timer);start.disabled=false;stop.disabled=true;};
 const finish=()=>{const old=current;current=null;try{if(old)old.abort();}catch(e){}reset();};
 start.onclick=()=>{
  finish();log.textContent='';heard.textContent='Waiting for speech…';
  note('Secure page: '+window.isSecureContext);note('Browser: '+navigator.userAgent);
  const API=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!API){note('Speech recognition API is unavailable in this browser.');return;}
  let r;try{r=new API();}catch(e){note('Cannot create speech recognition: '+e.name);return;}
  current=r;r.lang='en-US';r.continuous=false;r.interimResults=true;r.maxAlternatives=1;
  r.onstart=()=>{if(current===r)note('Recognition started.');};
  r.onaudiostart=()=>{if(current===r)note('Microphone audio started.');};
  r.onspeechstart=()=>{if(current===r)note('Speech detected.');};
  r.onresult=e=>{if(current!==r)return;let text='';for(let i=0;i<e.results.length;i++)text+=e.results[i][0].transcript+' ';heard.textContent=text.trim();note('Received '+(e.results[e.results.length-1].isFinal?'final':'interim')+' words.');};
  r.onerror=e=>{if(current===r)note('Speech error: '+e.error+(e.message?' — '+e.message:''));};
  r.onend=()=>{if(current===r){note('Recognition ended. Tap Start to repeat.');current=null;reset();}};
  try{start.disabled=true;stop.disabled=false;r.start();timer=setTimeout(()=>{if(current===r){note('Stopped after 30 seconds.');finish();}},30000);}catch(e){note('Start failed: '+e.name+' — '+e.message);finish();}
 };
 stop.onclick=()=>{note('Stopped by you.');finish();};
 window.addEventListener('pagehide',finish);
})();
