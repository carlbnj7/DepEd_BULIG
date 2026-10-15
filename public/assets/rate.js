/* BULIG app ratings (v152): the pupil's stars and the teacher's 5-question evaluation (app/ratings.php).
   Tapping a star fills it and the stars before it; Send posts the rating and shows the thank-you in place.
   "Not now" (or ×, or Escape) asks again later. The popup waits until other popups (celebrations, the welcome
   panel, a guided tour) are closed. Reports › App ratings: the bars get their width here (no inline styles). */
(function(){
 'use strict';
 const $=(s,r)=>(r||document).querySelector(s),$$=(s,r)=>[...(r||document).querySelectorAll(s)];
 const WORDS=['','Not really','A little','It’s okay','I like it!','I love it!'];
 $$('.rt-bar i[data-w]').forEach(i=>{i.style.width=Math.max(0,Math.min(100,+i.dataset.w||0))+'%';});
 const forms=$$('form[data-rt-form]');if(!forms.length)return;
 const starRow=n=>{const d=document.createElement('div');d.className='rt-stars rt-show';d.setAttribute('aria-hidden','true');for(let i=1;i<=5;i++){const s=document.createElement('i');if(i<=n)s.className='on';d.append(s);}return d;};
 const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!=null)e.textContent=text;return e;};
 function close(f){const ov=f.closest('[data-rt]');if(!ov)return;ov.classList.add('rt-out');setTimeout(()=>ov.remove(),260);}
 async function later(f){const fd=new FormData();const c=f.querySelector('input[name=csrf]');if(c)fd.set('csrf',c.value);fd.set('action','rate_later');
  try{await fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});}catch(e){}close(f);}
 function thanks(f,j){const teacher=f.hasAttribute('data-rt-teacher'),inline=!f.closest('[data-rt]');
  const card=el('div',f.className+' rt-done');card.setAttribute('role','status');
  const hero=el('div','rt-hero');hero.setAttribute('aria-hidden','true');hero.append(el('i'));card.append(hero);
  card.append(el('h2','',teacher?'Thank you!':'Thank you, '+(f.dataset.rtName||'friend')+'!'));
  card.append(el('p','rt-sub',teacher?'Your evaluation was sent. It helps the Division improve BULIG.':'Your stars help us make BULIG better.'));
  card.append(starRow(+j.stars||0));
  card.append(el('div','rt-word',teacher?'Your overall rating: '+String(j.overall).replace(/\.0$/,'')+' ★':(j.word||WORDS[+j.stars]||'')));
  if(inline){const a=el('a','rt-btn','Back to Home');a.href='?page=dashboard';card.append(a);}
  else{const b=el('button','rt-btn',teacher?'Close':'Back to learning');b.type='button';b.addEventListener('click',()=>close(card));card.append(b);}
  f.replaceWith(card);const b=card.querySelector('.rt-btn');if(b)b.focus({preventScroll:true});}
 forms.forEach(f=>{
  const groups=$$('[data-rt-stars]',f),send=$('[data-rt-send]',f),word=$('[data-rt-word]',f),avg=$('[data-rt-avg]',f);
  const val=g=>{const c=$('input:checked',g);return c?+c.value:0;};
  const paint=()=>{groups.forEach(g=>{const v=val(g);$$('.rt-star',g).forEach((l,i)=>l.classList.toggle('on',i<v));});
   const vs=groups.map(val);if(word)word.textContent=WORDS[vs[0]]||'';
   if(avg){const done=vs.filter(Boolean);avg.textContent=done.length?(done.reduce((a,b)=>a+b,0)/done.length).toFixed(1)+' ★':'–';}
   if(send)send.disabled=vs.some(v=>!v);};
  groups.forEach(g=>g.addEventListener('change',e=>{const l=e.target.closest('.rt-star');if(l){l.classList.remove('pop');void l.offsetWidth;l.classList.add('pop');}paint();}));
  paint();
  f.addEventListener('submit',async e=>{e.preventDefault();if(send&&send.disabled)return;if(send)send.disabled=true;
   try{const r=await fetch('index.php',{method:'POST',body:new FormData(f),credentials:'same-origin',headers:{Accept:'application/json'}});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'');thanks(f,j);}
   catch(err){if(send)send.disabled=false;HTMLFormElement.prototype.submit.call(f);}});
  $$('[data-rt-later]',f).forEach(b=>b.addEventListener('click',()=>later(f)));
  const spk=$('[data-rt-say]',f);if(spk)spk.addEventListener('click',()=>{try{const s=window.speechSynthesis;s.cancel();const u=new SpeechSynthesisUtterance(spk.dataset.rtSay);u.lang='en-US';u.rate=.92;u.pitch=1.05;s.speak(u);}catch(err){}});
 });
 /* The popup opens a moment after other popups are closed */
 const ov=$('[data-rt]');if(!ov)return;
 const busy=()=>$$('.pw-overlay,.tour-offer,.tour-layer,.lv-overlay,.hello,.boot').some(x=>x!==ov&&x.isConnected&&!x.hidden&&getComputedStyle(x).display!=='none'&&getComputedStyle(x).visibility!=='hidden');
 const open=()=>{if(busy()){setTimeout(open,1500);return;}ov.hidden=false;const card=$('.rt-card',ov);if(card){card.tabIndex=-1;card.focus({preventScroll:true});}};
 setTimeout(open,1400);
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!ov.hidden&&ov.isConnected){const f=$('form[data-rt-form]',ov);if(f)later(f);}});
})();
