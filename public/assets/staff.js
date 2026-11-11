/* BULIG "Find a page" (teachers and the administrator): type what you need, for example "print" or "backup".
   The list of pages and tasks comes with the page (data-find on .nv-find), so it also works without internet. */
(function(){
 'use strict';
 const box=document.querySelector('.nv-find[data-find]');if(!box)return;
 const inp=box.querySelector('[data-find-input]'),out=box.querySelector('[data-find-out]');
 let items=[];try{items=JSON.parse(box.dataset.find||'[]');}catch(e){return;}
 const esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const mark=(t,q)=>{const i=t.toLowerCase().indexOf(q);return i<0?esc(t):esc(t.slice(0,i))+'<mark>'+esc(t.slice(i,i+q.length))+'</mark>'+esc(t.slice(i+q.length));};
 let found=[],at=0;
 function place(){const r=inp.getBoundingClientRect();out.style.left=Math.max(8,Math.min(r.left,innerWidth-Math.min(360,innerWidth-32)-8))+'px';out.style.top=(r.bottom+8)+'px';}
 function run(){const q=inp.value.trim().toLowerCase();if(!q){out.hidden=true;found=[];return;}
  const words=q.split(/\s+/);
  found=items.map(it=>{const a=it[0].toLowerCase(),b=it[1].toLowerCase();let s=0;
    if(a.startsWith(q))s=3;else if(a.includes(q))s=2;else if(b.includes(q))s=1;else if(words.every(w=>a.includes(w)||b.includes(w)))s=.5;return [s,it];})
   .filter(x=>x[0]>0).sort((x,y)=>y[0]-x[0]).slice(0,8).map(x=>x[1]);
  at=0;out.innerHTML=found.length?'<p>'+found.length+' FOUND</p>'+found.map((it,i)=>'<a href="'+esc(it[2])+'" role="option" class="'+(i===0?'on':'')+'"><i>'+it[3]+'</i><span>'+mark(it[0],q)+'<small>'+mark(it[1],q)+'</small></span></a>').join(''):'<p>NOTHING FOUND</p><a href="?page=moved" role="option"><i>?</i><span>See where things are now<small>The list of what moved</small></span></a>';
  out.hidden=false;place();}
 function move(d){const as=[...out.querySelectorAll('a')];if(!as.length)return;at=(at+d+as.length)%as.length;as.forEach((a,i)=>a.classList.toggle('on',i===at));as[at].scrollIntoView({block:'nearest'});}
 inp.addEventListener('input',run);inp.addEventListener('focus',()=>{if(inp.value.trim())run();});
 inp.addEventListener('keydown',e=>{if(e.key==='ArrowDown'){e.preventDefault();move(1);}else if(e.key==='ArrowUp'){e.preventDefault();move(-1);}
  else if(e.key==='Enter'){const a=out.querySelectorAll('a')[at];if(a&&!out.hidden){e.preventDefault();location.href=a.getAttribute('href');}}
  else if(e.key==='Escape'){out.hidden=true;inp.blur();}});
 document.addEventListener('click',e=>{if(!box.contains(e.target))out.hidden=true;});
 addEventListener('resize',()=>{if(!out.hidden)place();});
 const sb=box.closest('.sidebar');if(sb)sb.addEventListener('scroll',()=>{if(!out.hidden)place();},{passive:true});
})();

/* Once after signing in, on a phone: Kai the Messenger says BULIG is easier on a laptop or computer (v174). */
(function(){
 const t=document.getElementById('pc-tip');if(!t)return;
 const phone=window.matchMedia('(max-width: 820px)').matches&&window.matchMedia('(pointer: coarse)').matches;if(!phone)return;
 const show=()=>{
  const box=document.createElement('div');box.className='kai-tip';box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');box.setAttribute('aria-labelledby','kai-tip-h');
  box.innerHTML='<div class="kai-tip-card"><img class="kai-tip-kai" alt="" src=""><span class="kai-tip-name">Kai the Messenger</span><h2 id="kai-tip-h"></h2>'
   +'<p>BULIG works best on a <b>laptop or computer</b>. The bigger screen makes reports, printing, Class Demo and adding pupils much easier.</p>'
   +'<ul><li>Use a laptop or computer for reports, printing and Class Demo</li><li>Your phone is fine for quick checks and notifications</li></ul>'
   +'<button type="button" class="btn primary kai-tip-ok">Okay, got it</button></div>';
  box.querySelector('img').src=t.dataset.img;box.querySelector('h2').textContent=t.dataset.hi||'Hi!';
  document.body.appendChild(box);document.documentElement.classList.add('kai-tip-open');
  const ok=box.querySelector('.kai-tip-ok');const close=()=>{box.classList.add('out');document.documentElement.classList.remove('kai-tip-open');setTimeout(()=>box.remove(),250);};
  ok.addEventListener('click',close);box.addEventListener('click',e=>{if(e.target===box)close();});document.addEventListener('keydown',function k(e){if(e.key==='Escape'){close();document.removeEventListener('keydown',k);}});
  setTimeout(()=>ok.focus({preventScroll:true}),60);};
 /* after the sign-in welcome screen (.boot) has gone */
 const wait=(n)=>{if(document.documentElement.classList.contains('bulig-boot')&&n<48){setTimeout(()=>wait(n+1),250);return;}setTimeout(show,n?500:400);};
 if(document.readyState==='complete')wait(0);else addEventListener('load',()=>wait(0));
})();

/* Schools (v174): the IDs a school will give, as its code is typed or chosen. */
(function(){
 const yy=String(new Date().getFullYear()).slice(-2);
 document.querySelectorAll('[data-sc-code]').forEach(inp=>{const form=inp.closest('form'),prev=form&&form.querySelector('[data-sc-prev]');if(!prev)return;
  const upd=()=>{const c=(inp.value||'').toUpperCase().replace(/[^A-Z]/g,'');inp.value=c;prev.innerHTML='';const code=c||'CODE';prev.append('Pupils of this school will get IDs like ');const a=document.createElement('b');a.textContent=code+yy+'0001';prev.append(a,'. Teachers like ');const b=document.createElement('b');b.textContent=code+'T0001';prev.append(b,'.');};
  inp.addEventListener('input',upd);upd();});
 /* v189: Add a school makes the code from the name (same rule as unique_school_code in account_ids.php) */
 const SKIP=['OF','AND','THE','DE','DEL','NG','SA','AT','FOR','IN','ON','INC'];
 const words=n=>n.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toUpperCase().split(/[^A-Z]+/).filter(x=>x&&!SKIP.includes(x));
 const uniq=(n,t)=>{const w=words(n);if(!w.length)return '';let b=w.map(x=>x[0]).join('');if(b.length<3)b=w.join('').slice(0,Math.max(3,b.length));b=b.slice(0,10);if(b.length<3)b=(b+'SCH').slice(0,3);
  const tr=[b];w.forEach((word,i)=>{for(let k=2;k<=word.length;k++){const c=w.map((x,j)=>j===i?x.slice(0,k):x[0]).join('');if(c.length>=3&&c.length<=10)tr.push(c);}});
  for(const l of 'BCDEFGHIJKLMNOPQRSTUVWXYZ')tr.push(b.slice(0,9)+l);return tr.find(c=>!t.includes(c))||b;};
 document.querySelectorAll('[data-sc-auto]').forEach(inp=>{const name=inp.form&&inp.form.querySelector('[data-sc-name]');if(!name)return;const t=(inp.dataset.taken||'').split(',').filter(Boolean);
  const go=()=>{inp.value=uniq(name.value,t);inp.dispatchEvent(new Event('input'));};name.addEventListener('input',go);go();});
 document.querySelectorAll('[data-sc-pick]').forEach(sel=>{const out=sel.parentElement.querySelector('[data-sc-idprev]');if(!out)return;
  const upd=()=>{const c=sel.selectedOptions[0]?sel.selectedOptions[0].dataset.code:'';out.textContent=c?'New teacher IDs here look like '+c+'T0001. Their pupils get IDs like '+c+(out.dataset.yy||yy)+'0001.':'';};
  sel.addEventListener('change',upd);upd();});
})();
/* Batch delete (My pupils, Teachers): count the ticked rows in the confirm message; nothing ticked = no delete. Runs before app.js's confirm (window capture comes before document capture). */
window.addEventListener('click',e=>{const b=e.target.closest('[data-need-tick]');if(!b)return;
 const f=b.form;const n=f?[...document.querySelectorAll('input[type=checkbox][name$="[]"]:checked')].filter(c=>c.form===f).length:0;
 if(!n){e.preventDefault();e.stopImmediatePropagation();alert('Tick at least one first.');return;}
 const who=b.value==='delete_pupils'?(n===1?'pupil':'pupils'):(n===1?'teacher':'teachers');
 if(!b.dataset.base)b.dataset.base=b.dataset.confirm;b.dataset.confirm='Delete '+n+' ticked '+who+'? '+b.dataset.base.replace(/^Delete the ticked [a-z]+ for good\? /,'');},true);
/* Passes: if a long name still pushes the ID or password out of the pass, first tighten the spacing, then shrink the name a little (never below 70%). */
(function(){const over=(i,m)=>i.getBoundingClientRect().bottom>m.getBoundingClientRect().bottom-2;
 const fit=()=>document.querySelectorAll('.tk').forEach(t=>{const m=t.querySelector('.tk-main'),i=t.querySelector('.tk-info'),n=t.querySelector('.tk-name');if(!m||!i||!n)return;
  t.classList.remove('tk-tight');n.style.fontSize='';if(!over(i,m))return;t.classList.add('tk-tight');
  const f0=parseFloat(getComputedStyle(n).fontSize);let f=f0;while(over(i,m)&&f>f0*0.7){f*=0.94;n.style.fontSize=f+'px';}});
 if(document.querySelector('.tk')){fit();window.addEventListener('load',fit);window.addEventListener('beforeprint',fit);document.addEventListener('click',e=>{if(e.target.closest('[data-tk-size],button'))setTimeout(fit,60);});}})();
/* Class list import: "Starting level for every pupil" sets each pupil's level box (each can still be changed). */
document.addEventListener('change',e=>{const a=e.target.closest('[data-lv-all]');if(!a||a.value==='')return;document.querySelectorAll('select[data-lv]').forEach(s=>{s.value=a.value;});});
/* Passes: "Save as image" saves every pass on the page as a PNG named after its ID (v176). */
document.addEventListener('click',async e=>{const b=e.target.closest('[data-tk-save]');if(!b||b.disabled)return;
 const passes=[...document.querySelectorAll('.tk')];if(!passes.length)return;const label=b.innerHTML;b.disabled=true;b.textContent='Saving…';
 try{if(!window.html2canvas)await new Promise((ok,no)=>{const s=document.createElement('script');s.src=b.dataset.src;s.onload=ok;s.onerror=no;document.head.appendChild(s);});
  for(const t of passes){const c=await window.html2canvas(t,{scale:3,backgroundColor:'#ffffff',useCORS:true,logging:false});
   const a=document.createElement('a');a.download=((t.querySelector('.tk-id')||{}).textContent||'pass').trim().replace(/[^A-Za-z0-9_-]+/g,'')+'.png';a.href=c.toDataURL('image/png');document.body.appendChild(a);a.click();a.remove();
   await new Promise(r=>setTimeout(r,350));}
 }catch(_){alert('The pass could not be saved as an image. Please try again.');}
 b.disabled=false;b.innerHTML=label;});
