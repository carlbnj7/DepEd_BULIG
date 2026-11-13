/* v216 Content Studio: upload the module for a new level (app/studio.php).
   The PDF is read here in the browser with pdf.js (assets/pdfjs, Apache 2.0): for each page, its text (in reading
   order) and a picture of the page are sent to BULIG. Then BULIG makes the draft lessons and activities. */
(()=>{
 'use strict';
 const me=document.currentScript,form=document.querySelector('[data-st-import]');if(!form)return;
 const csrf=(document.querySelector('meta[name="csrf"]')||{}).content||'';
 /* the text of a page as lines: pieces at the same height are one line, read left to right, top to bottom */
 function lines(items){
  const rows=[];
  for(const it of items){if(!it.str||!it.str.trim())continue;const y=it.transform[5],x=it.transform[4],h=Math.abs(it.transform[3])||10;
   let r=rows.find(r=>Math.abs(r.y-y)<h*.55);if(!r){r={y,parts:[]};rows.push(r);}r.parts.push({x,s:it.str});}
  rows.sort((a,b)=>b.y-a.y);
  return rows.map(r=>r.parts.sort((a,b)=>a.x-b.x).map(p=>p.s).join(' ').replace(/\s+/g,' ').trim()).filter(Boolean).join('\n');
 }
 async function post(fd){
  const r=await fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});
  let j=null;try{j=await r.json();}catch(e){}
  if(!r.ok||!j||j.ok===false||j.saved===false)throw new Error((j&&j.error)||('The server answered '+r.status+'.'));
  return j;
 }
 form.addEventListener('submit',async e=>{
  e.preventDefault();const file=form.querySelector('[data-st-pdf]').files[0];if(!file)return;
  const prog=form.querySelector('[data-st-prog]'),bar=prog.querySelector('progress'),msg=form.querySelector('[data-st-msg]'),btn=form.querySelector('button');
  prog.hidden=false;btn.disabled=true;bar.value=0;
  try{
   if(!window.pdfjsLib)throw new Error('The PDF reader did not load. Reload the page and try again.');
   pdfjsLib.GlobalWorkerOptions.workerSrc=me.dataset.worker;
   const pdf=await pdfjsLib.getDocument({data:await file.arrayBuffer(),isEvalSupported:false}).promise,N=pdf.numPages;
   if(N>400)throw new Error('The module has '+N+' pages. Up to 400 pages can be uploaded.');
   for(let i=1;i<=N;i++){
    msg.textContent='Reading page '+i+' of '+N+'…';
    const page=await pdf.getPage(i),text=lines((await page.getTextContent()).items);
    const base=page.getViewport({scale:1}),vp=page.getViewport({scale:Math.min(2,1100/base.width)});
    const c=document.createElement('canvas');c.width=Math.round(vp.width);c.height=Math.round(vp.height);
    const ctx=c.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,c.width,c.height);
    await page.render({canvasContext:ctx,viewport:vp}).promise;
    const blob=await new Promise(r=>c.toBlob(r,'image/jpeg',.82));
    const fd=new FormData();fd.append('action','studio_import_page');fd.append('csrf',csrf);fd.append('level',form.dataset.level);
    fd.append('n',String(i));fd.append('total',String(N));fd.append('text',text);fd.append('page',blob,'page-'+i+'.jpg');
    await post(fd);bar.value=Math.round(i/N*90);page.cleanup();
   }
   msg.textContent='Making the lessons and activities…';
   const fd=new FormData();fd.append('action','studio_import_build');fd.append('csrf',csrf);fd.append('level',form.dataset.level);
   fd.append('replace',form.querySelector('[data-st-replace]').checked?'1':'');
   const j=await post(fd);bar.value=100;msg.textContent=j.msg||'Done.';setTimeout(()=>{location.href=j.url||location.href;},900);
  }catch(err){msg.textContent='The module could not be read: '+err.message;btn.disabled=false;}
 });
})();
