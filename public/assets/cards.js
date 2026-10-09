/* BULIG sign-in tickets: draws each QR code as a small SVG, the show/hide options and the Print button,
   and the "Account created!" window shown right after a teacher adds a pupil. */
(function(){
 'use strict';
 var NS='http://www.w3.org/2000/svg',base=location.origin+location.pathname;
 function draw(box){
  if(typeof qrcode!=='function')return;
  var q=qrcode(0,'M');q.addData(base+box.getAttribute('data-qr'));q.make();
  var n=q.getModuleCount(),m=2,svg=document.createElementNS(NS,'svg'),d='';
  svg.setAttribute('viewBox','0 0 '+(n+m*2)+' '+(n+m*2));svg.setAttribute('shape-rendering','crispEdges');svg.setAttribute('focusable','false');
  for(var r=0;r<n;r++)for(var c=0;c<n;c++)if(q.isDark(r,c))d+='M'+(c+m)+' '+(r+m)+'h1v1h-1z';
  var bg=document.createElementNS(NS,'rect');bg.setAttribute('width','100%');bg.setAttribute('height','100%');bg.setAttribute('fill','#fff');
  var p=document.createElementNS(NS,'path');p.setAttribute('d',d);p.setAttribute('fill','#10281b');
  svg.append(bg,p);box.replaceChildren(svg);
 }
 document.querySelectorAll('[data-qr]').forEach(draw);

 /* Tickets page: show/hide options and Print. */
 var sheet=document.querySelector('[data-lc-sheet]');
 if(sheet){
  document.querySelectorAll('[data-lc-toggle]').forEach(function(t){
   var cls=t.getAttribute('data-lc-toggle'),key='bulig-'+cls;
   try{if(localStorage.getItem(key)==='1')t.checked=false;}catch(e){}
   function apply(){sheet.classList.toggle(cls,!t.checked);try{localStorage.setItem(key,t.checked?'0':'1');}catch(e){}}
   t.addEventListener('change',apply);apply();
  });
  /* Choose which pupils to print. */
  var W1=sheet.getAttribute('data-tk-word')||'ticket',W2=W1==='pass'?'passes':W1+'s';
  var wraps=[].slice.call(sheet.querySelectorAll('[data-tk]')),count=document.querySelector('[data-tk-count]'),label=document.querySelector('[data-lc-print-label]'),pr=document.querySelector('[data-lc-print]');
  function picked(){return wraps.filter(function(w){var c=w.querySelector('[data-tk-pick]');return !c||c.checked;});}
  function sync(){var n=picked().length;wraps.forEach(function(w){var c=w.querySelector('[data-tk-pick]');w.classList.toggle('off',!!c&&!c.checked);});
   if(count)count.textContent=n+' of '+wraps.length+' '+(wraps.length===1?W1:W2)+' selected';
   if(label)label.textContent=n?'Print '+n+' '+(n===1?W1:W2):'Choose '+W2+' to print';if(pr)pr.disabled=!n;}
  wraps.forEach(function(w){var c=w.querySelector('[data-tk-pick]');if(c)c.addEventListener('change',sync);});
  function all(on){wraps.forEach(function(w){var c=w.querySelector('[data-tk-pick]');if(c)c.checked=on;});sync();}
  var ba=document.querySelector('[data-tk-all]'),bn=document.querySelector('[data-tk-none]');if(ba)ba.addEventListener('click',function(){all(true);});if(bn)bn.addEventListener('click',function(){all(false);});
  /* Ticket size: Small 21 (3 columns), Medium 10, Large 8 per A4 page. Remembered on this device. */
  var PER={s:21,m:10,l:8},size='m';try{size=localStorage.getItem('bulig-tk-size')||'m';}catch(e){}if(!PER[size])size='m';
  function setSize(z){size=z;sheet.classList.remove('sz-s','sz-m','sz-l');sheet.classList.add('sz-'+z);try{localStorage.setItem('bulig-tk-size',z);}catch(e){}
   document.querySelectorAll('[data-tk-size]').forEach(function(b){var on=b.getAttribute('data-tk-size')===z;b.classList.toggle('on',on);b.setAttribute('aria-checked',on?'true':'false');});}
  document.querySelectorAll('[data-tk-size]').forEach(function(b){b.addEventListener('click',function(){setSize(b.getAttribute('data-tk-size'));});});setSize(size);
  /* A new page after every full page of the chosen tickets. */
  function breaks(){var i=0;wraps.forEach(function(w){w.classList.remove('pb');});picked().forEach(function(w,k,a){i++;if(i%PER[size]===0&&k<a.length-1)w.classList.add('pb');});}
  window.addEventListener('beforeprint',breaks);
  if(pr)pr.addEventListener('click',function(){if(!picked().length)return;breaks();window.print();});
  sync();
 }

 /* "Account created!" window. */
 var ov=document.querySelector('[data-np]');
 if(ov){
  document.body.appendChild(ov);document.body.classList.add('np-open');
  var still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches,last=document.activeElement;
  requestAnimationFrame(function(){ov.classList.add('on');var f=ov.querySelector('[data-np-print]');if(f)f.focus();});
  function close(then){ov.classList.remove('on');document.body.classList.remove('np-open');setTimeout(function(){ov.remove();if(then)then();else if(last&&last.focus)last.focus();},still?0:220);}
  ov.querySelectorAll('[data-np-close]').forEach(function(b){b.addEventListener('click',function(){close();});});
  ov.addEventListener('click',function(e){if(e.target===ov)close();});
  document.addEventListener('keydown',function k(e){if(e.key==='Escape'&&ov.isConnected){close();document.removeEventListener('keydown',k);}});
  var add=ov.querySelector('[data-np-add]');
  if(add)add.addEventListener('click',function(){close(function(){var n=document.querySelector('form input[name="name"]');if(n){n.scrollIntoView({block:'center',behavior:still?'auto':'smooth'});n.focus();}});});
  var pb=ov.querySelector('[data-np-print]');
  if(pb)pb.addEventListener('click',function(){document.body.classList.add('np-printing');window.print();setTimeout(function(){document.body.classList.remove('np-printing');},500);});
  window.addEventListener('afterprint',function(){document.body.classList.remove('np-printing');});
 }
})();
