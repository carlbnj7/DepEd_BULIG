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
