import {join,resample,rms,wav} from './core.mjs';
const bounded=async(promise,ms,message)=>{let timer;try{return await Promise.race([promise,new Promise((_,reject)=>timer=setTimeout(()=>reject(Error(message)),ms))]);}finally{clearTimeout(timer);}};
const dataURL=blob=>new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(r.result);r.onerror=()=>reject(Error('Audio could not be prepared.'));r.readAsDataURL(blob);});
export class ReadingEngine {
 constructor(){this.worker=null;this.ready=false;this.busy=null;this.pending=null;this.serial=0;this.capture=null;this.dead=false;}
 async load(progress=()=>{}){
  if(this.ready)return;
  if(this.loading)return this.loading;
  this.loading=new Promise((resolve,reject)=>{
   let timer=setTimeout(()=>this.fail(Error('The word checker took too long to load. Please retry.')),240000);
   this.loadResolve=()=>{clearTimeout(timer);resolve();};this.loadReject=e=>{clearTimeout(timer);reject(e);};
   try{
    this.worker=new Worker(new URL('./recognizer-worker.js',import.meta.url),{type:'module'});
    this.worker.onerror=e=>{e.preventDefault();this.fail(Error('The word checker could not run on this device.'));};
    this.worker.onmessage=({data})=>{
     if(data.type==='progress')progress('Preparing word checker: '+Math.round(data.progress||0)+'%');
     if(data.type==='ready'){this.ready=true;this.loadResolve();this.loadReject=null;}
     if(data.type==='error')this.fail(Error(data.message));
     if(data.type==='result'&&this.busy&&data.id===this.busy.id){const job=this.busy;this.busy=null;clearTimeout(job.timer);job.resolve(data.text);this.pump();}
    };
    this.worker.postMessage({type:'load'});
   }catch(e){this.fail(e);}
  });return this.loading;
 }
 fail(error){
  this.ready=false;this.worker?.terminate();this.worker=null;this.loadReject?.(error);this.loadReject=null;this.loading=null;
  if(this.busy){clearTimeout(this.busy.timer);this.busy.reject(error);this.busy=null;}
  if(this.pending){this.pending.reject(error);this.pending=null;}
 }
 transcribe(audio,final=false){
  if(!this.ready)return Promise.reject(Error('Word checking is unavailable. Your recording is kept.'));
  return new Promise((resolve,reject)=>{
   // A draft snapshot may be replaced; resolve the obsolete draft without changing the UI.
   if(this.pending)this.pending.resolve(null);
   this.pending={audio,final,id:++this.serial,resolve,reject};this.pump();
  });
 }
 pump(){if(this.busy||!this.pending||!this.worker)return;const job=this.pending;this.pending=null;this.busy=job;
  job.timer=setTimeout(()=>this.fail(Error('Word checking took too long. Your teacher can review the recording.')),180000);
  this.worker.postMessage({type:'transcribe',id:job.id,audio:job.audio,final:job.final},[job.audio.buffer]);
 }
 async start({onDraft=()=>{},onStatus=()=>{},onLimit=()=>{},onInterrupt=()=>{},recognize=true,maxSeconds=300}={}){
  if(this.dead)throw Error('Reading was cancelled.');
  if(this.capture)throw Error('A reading is already active.');
  const AC=window.AudioContext||window.webkitAudioContext;
  if(!AC||!navigator.mediaDevices?.getUserMedia)throw Error('Microphone access requires a supported browser and HTTPS.');
  try{if(navigator.audioSession)navigator.audioSession.type='auto';}catch{}
  let context=new AC(),media=null,node=null,source=null,gain=null,recorder=null,recorded=[],recorderDone=null,finishRecorder=null;
  let chunks=[],samples=0,stopped=false,ending=null,discard=false,flush=null,tick=null,lastPreview=0,lastCheck=0,interrupted='',wavMode=true;
  const resumed=context.resume();resumed.catch(()=>{});
  const release=()=>{clearInterval(tick);media?.getTracks().forEach(t=>t.stop());try{source?.disconnect();node?.disconnect();gain?.disconnect();}catch{}context.close().catch(()=>{});};
  const cancel=()=>{discard=true;stopped=true;try{if(recorder?.state!=='inactive')recorder?.stop();}catch{}release();flush?.();chunks=[];recorded=[];this.capture=null;};
  this.capture={cancel};
  try{
   media=await navigator.mediaDevices.getUserMedia({audio:{channelCount:1,echoCancellation:true,noiseSuppression:true,autoGainControl:true},video:false});
   if(discard||this.dead){release();throw Error('Reading was cancelled.');}
   await resumed;await context.resume();
   if(!context.audioWorklet)throw Error('Please update your browser to use this reading microphone.');
   await context.audioWorklet.addModule(new URL('./capture-worklet.js',import.meta.url));
   if(discard||this.dead)throw Error('Reading was cancelled.');
   node=new AudioWorkletNode(context,'bulig-capture');source=context.createMediaStreamSource(media);gain=context.createGain();gain.gain.value=0;
   node.port.onmessage=({data})=>{if(discard)return;if(data.type==='pcm'){chunks.push(data.data);samples+=data.data.length;}else if(data.type==='flushed'){flush?.();flush=null;}};
   // Compressed recording and PCM analysis share the SAME getUserMedia stream.
   if(window.MediaRecorder){try{
    const type=['audio/mp4','audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus'].find(t=>MediaRecorder.isTypeSupported(t));
    recorder=new MediaRecorder(media,type?{mimeType:type,audioBitsPerSecond:24000}:{audioBitsPerSecond:24000});
    recorderDone=new Promise(resolve=>finishRecorder=resolve);
    recorder.ondataavailable=e=>{if(!discard&&e.data?.size)recorded.push(e.data);};
    recorder.onstop=()=>finishRecorder();recorder.onerror=()=>{wavMode=true;finishRecorder();};recorder.start(1000);wavMode=false;
   }catch{recorder=null;wavMode=true;}}
   source.connect(node);node.connect(gain);gain.connect(context.destination);
   const seconds=()=>samples/context.sampleRate;
   const snapshot=()=>resample(join(chunks),context.sampleRate);
   const started=performance.now();
   const stop=()=>{
    if(ending)return ending;
    ending=(async()=>{
     stopped=true;clearInterval(tick);
     await new Promise(resolve=>{const timeout=setTimeout(()=>{interrupted=interrupted||'The microphone was interrupted; the last sound may be incomplete.';resolve();},1500);flush=()=>{clearTimeout(timeout);resolve();};node.port.postMessage('finish');});
     const pcm=snapshot();chunks=[];
     if(recorder&&recorder.state!=='inactive')try{recorder.stop();}catch{}
     if(recorderDone)try{await bounded(recorderDone,5000,'Recorder did not finish');}catch{recorded=[];}
     release();this.capture=null;
     if(discard||this.dead)return {audio:'',text:null,seconds:0,error:'Reading was cancelled.'};
     const secs=pcm.length/16000;
     let blob=recorded.length?new Blob(recorded,{type:recorder.mimeType.split(';')[0]}):null;recorded=[];
     // Validate actual decoded audio, not only the existence of a container header.
     if(blob&&blob.size>200){let decoder;try{decoder=new AC();const decoded=await bounded(decoder.decodeAudioData(await blob.arrayBuffer()),10000,'Decode timed out');if(decoded.duration<secs*.8||decoded.duration>secs+3||rms(decoded.getChannelData(0))<.0001)blob=null;}catch{blob=null;}finally{decoder?.close().catch(()=>{});}}else blob=null;
     if(!blob&&pcm.length<=16000*180)blob=new Blob([wav(pcm)],{type:'audio/wav'});
     if(!blob||blob.size>6000000||secs<.25||rms(pcm)<.001)return {audio:'',text:null,seconds:secs,error:'No complete audible recording was captured. Please retry with microphone access.'};
     const encoded=await dataURL(blob);
     // Audio is available even when the local recognizer cannot finish.
     let text=null,error=interrupted;
     if(recognize&&!interrupted){try{text=await this.transcribe(pcm,true);}catch(e){error=e.message;}}
     return {audio:encoded,text,seconds:secs,error};
    })();return ending;
   };
   const interrupt=message=>{if(!stopped&&!discard){interrupted=message;onInterrupt(message);}};
   media.getAudioTracks().forEach(t=>{t.addEventListener('ended',()=>interrupt('Microphone disconnected. Your captured audio is kept for review.'));t.addEventListener('mute',()=>interrupt('Microphone interrupted. Your captured audio is kept for review.'));});
   context.onstatechange=()=>{if(context.state!=='running')interrupt('Reading was interrupted. Your captured audio is kept for review.');};
   tick=setInterval(()=>{
    if(stopped||discard)return;
    const elapsed=(performance.now()-started)/1000;
    onStatus(Math.floor(elapsed));
    if(elapsed>=Math.min(maxSeconds,wavMode?180:300)){interrupted='The recording time limit was reached. Your teacher will review the captured reading.';onLimit();return;}
    const duration=seconds();
    if(recognize&&this.ready&&duration-lastPreview>=8&&performance.now()-lastCheck>8000&&!this.busy){lastPreview=duration;lastCheck=performance.now();this.transcribe(snapshot()).then(text=>{if(!stopped&&!discard&&text!==null)onDraft(text);}).catch(()=>{});}
   },250);
   const result={stop,cancel};this.capture=result;return result;
  }catch(e){cancel();throw e;}
 }
 dispose(){this.dead=true;this.capture?.cancel();this.fail(Error('Reading closed.'));}
}
