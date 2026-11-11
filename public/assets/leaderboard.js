/* Top 10 pupils board on Teacher Home (v172): "Save as picture" turns the board (an SVG) into a PNG file, and
   "Print" prints only the board. The pictures inside (photos, characters, logo) are put into the SVG first, so the
   saved picture shows them too. */
(function(){
 'use strict';
 const svg=document.getElementById('lb-svg'),save=document.querySelector('[data-lb-save]'),print=document.querySelector('[data-lb-print]');if(!svg)return;
 const asData=u=>fetch(u,{credentials:'same-origin'}).then(r=>r.ok?r.blob():null).then(b=>b?new Promise(ok=>{const f=new FileReader();f.onload=()=>ok(String(f.result));f.onerror=()=>ok(null);f.readAsDataURL(b);}):null).catch(()=>null);
 async function png(){
  const c=svg.cloneNode(true),cache={};
  for(const im of c.querySelectorAll('image')){const u=im.getAttribute('href')||'';if(!u||u.startsWith('data:'))continue;if(!(u in cache))cache[u]=await asData(u);if(cache[u])im.setAttribute('href',cache[u]);else im.remove();}
  const txt=new XMLSerializer().serializeToString(c),img=new Image();
  await new Promise((ok,no)=>{img.onload=ok;img.onerror=no;img.src='data:image/svg+xml;base64,'+btoa(unescape(encodeURIComponent(txt)));});
  const k=2,cv=document.createElement('canvas');cv.width=1200*k;cv.height=820*k;const g=cv.getContext('2d');g.scale(k,k);g.drawImage(img,0,0,1200,820);
  return cv.toDataURL('image/png');}
 if(save)save.addEventListener('click',async()=>{const lab=save.lastChild,was=lab.textContent;save.disabled=true;lab.textContent='Making the picture…';
  try{const u=await png(),a=document.createElement('a');a.href=u;a.download='BULIG-Top-10-'+new Date().toISOString().slice(0,10)+'.png';document.body.append(a);a.click();a.remove();lab.textContent='Saved!';}
  catch(e){lab.textContent='Could not save. Try Print, then Save as PDF.';}
  setTimeout(()=>{lab.textContent=was;save.disabled=false;},2200);});
 /* Print: a copy of the board is the only thing on the page */
 if(print)print.addEventListener('click',()=>{const d=document.documentElement,box=document.createElement('div');box.id='lb-print-box';
  /* the copy gets its own gradient names: the hidden original's would not be drawn */
  box.innerHTML=svg.outerHTML.replace(/id="lb/g,'id="pr-lb').replace(/url\(#lb/g,'url(#pr-lb');document.body.append(box);d.classList.add('lb-print');
  let done=false;const off=()=>{if(done)return;done=true;d.classList.remove('lb-print');box.remove();removeEventListener('afterprint',off);};addEventListener('afterprint',off);window.print();setTimeout(off,60000);});
})();
