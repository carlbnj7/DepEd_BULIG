/* BULIG sign-in page (v133): the "try again" popup and the short-break countdown, "Forgot your password?",
   and "Scan my Reading Pass" on phones. The scanner uses the phone's built-in QR reader (BarcodeDetector) when there is one,
   and jsqr.js (loaded only when needed) when there is not. Nothing here is needed to sign in with an ID and password. */
(function(){
 'use strict';
 const $=(s,r)=>(r||document).querySelector(s);
 const card=$('.login-card');if(!card)return;
 const pw=$('#password'),idIn=$('input[name="public_id"]');

 /* ---------- Forgot your password? ---------- */
 const sheet=$('[data-lgn-sheet]');
 document.addEventListener('click',e=>{const b=e.target.closest('[data-lgn-forgot]');if(!b||!sheet)return;closePop();if(sheet.showModal&&!sheet.open)sheet.showModal();else sheet.setAttribute('open','');});
 if(sheet)sheet.addEventListener('click',e=>{if(e.target===sheet)sheet.close();});

 /* ---------- Try again popup and short-break countdown ---------- */
 const pop=$('[data-lgn-pop]');
 function closePop(){if(!pop||!pop.isConnected)return;pop.classList.add('lgn-out');setTimeout(()=>pop.remove(),220);if(pw){pw.value='';setTimeout(()=>pw.focus(),60);}}
 if(pop){
  const again=$('[data-lgn-close]',pop);if(again)again.addEventListener('click',e=>{e.preventDefault();closePop();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&pop.isConnected&&!pop.classList.contains('lgn-locked'))closePop();});
  if(again)setTimeout(()=>again.focus(),350);
  const clock=$('[data-lgn-secs]',pop);
  if(clock){const total=+clock.dataset.lgnTotal||900,end=Date.now()+(+clock.dataset.lgnSecs)*1000,txt=$('[data-lgn-clock]',clock),ring=$('.lgn-clock-fill',clock),wait=$('[data-lgn-wait]',pop),C=2*Math.PI*64;
   const tick=()=>{const s=Math.max(0,Math.round((end-Date.now())/1000));txt.textContent=Math.floor(s/60)+':'+String(s%60).padStart(2,'0');ring.setAttribute('stroke-dasharray',(C*s/total).toFixed(1)+' '+C.toFixed(1));
    if(s<=0){clearInterval(t);if(wait){wait.disabled=false;wait.textContent='Try again';wait.addEventListener('click',()=>{location.href=wait.dataset.lgnHref;});}}};
   const t=setInterval(tick,1000);tick();}
 }

 /* ---------- Scan my Reading Pass (phones with a camera) ---------- */
 const wrap=$('[data-lgn-scan-wrap]'),btn=$('[data-lgn-scan]');
 const phone=window.matchMedia&&matchMedia('(max-width: 740px)').matches;
 const camOK=!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia)&&window.isSecureContext!==false;
 if(!wrap||!btn||!phone||!camOK)return;
 wrap.hidden=false;
 const still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const svg=(d,w)=>'<svg viewBox="0 0 24 24" width="'+(w||24)+'" height="'+(w||24)+'" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+d+'</svg>';
 let ov=null,stream=null,timer=0,busy=false,done=false,detector=null,canvas=null,ctx=null;
 function loadJsQR(){return new Promise((ok,no)=>{if(window.jsQR)return ok();const s=document.createElement('script');s.src=btn.dataset.jsqr||'assets/jsqr.js?v=1';s.onload=()=>window.jsQR?ok():no();s.onerror=no;document.head.appendChild(s);});}
 function say(t,sub){$('.lgn-cam-msg',ov).textContent=t;$('.lgn-cam-sub',ov).textContent=sub||'';}
 function stop(){clearTimeout(timer);timer=0;if(stream){stream.getTracks().forEach(t=>t.stop());stream=null;}}
 function close(){stop();if(ov){ov.classList.add('lgn-out');const o=ov;setTimeout(()=>o.remove(),220);ov=null;}document.documentElement.classList.remove('lgn-cam-on');btn.focus();}
 function typeInstead(){close();if(idIn)setTimeout(()=>idIn.focus(),80);}
 async function open(){
  if(ov)return;done=false;busy=false;
  ov=document.createElement('div');ov.className='lgn-cam';ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-label','Scan my Reading Pass');
  ov.innerHTML='<video class="lgn-cam-video" playsinline muted></video><div class="lgn-cam-shade"></div>'
   +'<div class="lgn-cam-top"><button type="button" class="lgn-cam-x" aria-label="Close">'+svg('<path d="M6 6l12 12M18 6 6 18"/>',22)+'</button><b>Scan my Reading Pass</b><span></span></div>'
   +'<div class="lgn-cam-frame"><i></i><i></i><i></i><i></i><span class="lgn-cam-line"></span></div>'
   +'<div class="lgn-cam-msg" role="status">Starting the camera…</div><div class="lgn-cam-sub"></div><div class="lgn-cam-found" hidden></div>'
   +'<button type="button" class="lgn-cam-alt">Type my Pupil ID instead</button>';
  document.body.appendChild(ov);document.documentElement.classList.add('lgn-cam-on');
  $('.lgn-cam-x',ov).addEventListener('click',close);$('.lgn-cam-alt',ov).addEventListener('click',typeInstead);
  ov.addEventListener('keydown',e=>{if(e.key==='Escape')close();});$('.lgn-cam-x',ov).focus();
  try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});}
  catch(e){if(!ov)return;ov.classList.add('lgn-cam-err');say('BULIG needs the camera to scan.',(e&&e.name==='NotAllowedError')?'Tap “Allow” when the phone asks. Or type your Pupil ID instead.':'The camera did not start. Type your Pupil ID instead.');return;}
  if(!ov){stop();return;}
  const v=$('.lgn-cam-video',ov);v.srcObject=stream;try{await v.play();}catch(e){}
  say('Hold your Reading Pass inside the box','The QR code is on the right side of the pass.');
  try{if('BarcodeDetector' in window){const f=await BarcodeDetector.getSupportedFormats();if(f.includes('qr_code'))detector=new BarcodeDetector({formats:['qr_code']});}}catch(e){detector=null;}
  if(!detector){try{await loadJsQR();}catch(e){say('Scanning does not work on this phone.','Type your Pupil ID instead.');return;}canvas=document.createElement('canvas');ctx=canvas.getContext('2d',{willReadFrequently:true});}
  loop(v);
 }
 async function loop(v){
  if(!ov||done)return;
  let text='';
  try{if(v.readyState>=2&&v.videoWidth){
   if(detector){const r=await detector.detect(v);if(r&&r[0])text=r[0].rawValue||'';}
   else{const w=Math.min(640,v.videoWidth),h=Math.round(v.videoHeight*w/v.videoWidth);canvas.width=w;canvas.height=h;ctx.drawImage(v,0,0,w,h);const img=ctx.getImageData(0,0,w,h),r=window.jsQR(img.data,w,h,{inversionAttempts:'dontInvert'});if(r)text=r.data||'';}}}catch(e){}
  if(text&&!busy)await found(text);
  if(ov&&!done)timer=setTimeout(()=>loop(v),detector?120:180);
 }
 async function found(text){
  busy=true;let u=null;try{u=new URL(text,location.href);}catch(e){}
  const ours=u&&u.origin===location.origin&&u.searchParams.get('page')==='login';
  if(!ours){say('This is not a BULIG Reading Pass.','Try the QR code on your BULIG Reading Pass.');setTimeout(()=>{busy=false;},1600);return;}
  const key=u.searchParams.get('key'),id=u.searchParams.get('id');
  if(navigator.vibrate)try{navigator.vibrate(60);}catch(e){}
  if(!key&&id&&/^[A-Za-z0-9-]{1,30}$/.test(id)){done=true;stop();if(idIn)idIn.value=id;close();if(pw)setTimeout(()=>pw.focus(),120);return;}
  if(!key){say('This pass cannot sign you in.','Ask your teacher for a new Reading Pass.');setTimeout(()=>{busy=false;},1800);return;}
  let who=null;try{const r=await fetch('?page=login&key='+encodeURIComponent(key)+'&peek=1',{credentials:'same-origin',headers:{Accept:'application/json'}});who=await r.json();}catch(e){who=null;}
  if(!ov)return;
  if(!who||!who.ok){say('This Reading Pass no longer works.','Ask your teacher for a new one, or type your Pupil ID.');setTimeout(()=>{busy=false;},2200);return;}
  done=true;clearTimeout(timer);const vv=$('.lgn-cam-video',ov);if(vv)try{vv.pause();}catch(e){}ov.classList.add('lgn-cam-ok');
  const fd=$('.lgn-cam-found',ov);fd.innerHTML='<img alt="" src=""><span><b></b><small>Signing you in…</small></span><i>'+svg('<path d="M5 12l5 5 9-10"/>',18)+'</i>';$('img',fd).src=who.img;$('b',fd).textContent='Found you, '+who.first+'!';fd.hidden=false;say('','');
  if(window.buligSfx)try{window.buligSfx.play('correct');}catch(e){}
  const csrf=$('input[name="csrf"]',card);const f=document.createElement('form');f.method='post';f.action=location.pathname;f.hidden=true;
  [['csrf',csrf?csrf.value:''],['action','key_login'],['key',key]].forEach(([n,val])=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=val;f.appendChild(i);});
  document.body.appendChild(f);setTimeout(()=>{if(window.buligLoading)try{window.buligLoading.boot();}catch(e){}f.submit();},still?200:1100);
 }
 btn.addEventListener('click',open);
})();
