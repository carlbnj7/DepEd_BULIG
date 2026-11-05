(()=>{
 'use strict';
 const start=document.getElementById('start'),stop=document.getElementById('stop'),heard=document.getElementById('heard'),log=document.getElementById('log');
 let current=null,timer=null;document.getElementById('ready').textContent='Check script loaded. Ready.';start.disabled=false;
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

(()=>{
 const go=document.getElementById('record'),end=document.getElementById('end-record'),status=document.getElementById('record-status'),player=document.getElementById('playback');let stream=null,mr=null,url='',timer=null;
 const cleanup=()=>{clearTimeout(timer);if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;go.disabled=false;end.disabled=true;document.getElementById('start').disabled=false;};
 go.onclick=async()=>{if(!document.getElementById('stop').disabled){status.textContent='Stop the speech check first.';return;}go.disabled=true;document.getElementById('start').disabled=true;status.textContent='Requesting microphone access…';
  try{if(!navigator.mediaDevices||!window.MediaRecorder)throw Error('Recording API unavailable');stream=await new Promise((resolve,reject)=>{let timed=false;const wait=setTimeout(()=>{timed=true;reject(Error('Microphone permission timed out. Check the browser permission prompt.'));},15000);navigator.mediaDevices.getUserMedia({audio:true}).then(s=>{if(timed){s.getTracks().forEach(t=>t.stop());return;}clearTimeout(wait);resolve(s);},e=>{clearTimeout(wait);reject(e);});});
   const type=['audio/mp4','audio/webm;codecs=opus','audio/webm'].find(t=>typeof MediaRecorder.isTypeSupported==='function'&&MediaRecorder.isTypeSupported(t));let parts=[];mr=new MediaRecorder(stream,type?{mimeType:type}:undefined);
   mr.ondataavailable=e=>{if(e.data.size)parts.push(e.data);};mr.onerror=()=>{status.textContent='Recorder error';cleanup();};mr.onstop=()=>{const b=new Blob(parts,{type:mr.mimeType});cleanup();if(url)URL.revokeObjectURL(url);url=URL.createObjectURL(b);player.src=url;status.textContent='Recorded '+b.size+' bytes ('+b.type+'). Press Play to check your voice.';};
   mr.start();end.disabled=false;status.textContent='Recording. Say a sentence, then tap Finish recording.';timer=setTimeout(()=>{if(mr.state!=='inactive')mr.stop();},30000);
  }catch(e){status.textContent=e.name+': '+e.message;cleanup();}
 };
 end.onclick=()=>{end.disabled=true;if(mr&&mr.state!=='inactive')mr.stop();};player.onerror=()=>{status.textContent+=' Playback failed: '+(player.error?.message||'unknown error');};window.addEventListener('pagehide',()=>{if(mr&&mr.state!=='inactive')mr.stop();cleanup();if(url)URL.revokeObjectURL(url);});
})();
