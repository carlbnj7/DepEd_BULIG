/* BULIG printable sign-in cards (v1): draws each QR code as a small SVG, the show/hide options and the Print button. */
(function(){
 'use strict';
 var sheet=document.querySelector('[data-lc-sheet]');if(!sheet)return;
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
 sheet.querySelectorAll('[data-qr]').forEach(draw);
 document.querySelectorAll('[data-lc-toggle]').forEach(function(t){
  var cls=t.getAttribute('data-lc-toggle'),key='bulig-'+cls;
  try{if(localStorage.getItem(key)==='1')t.checked=false;}catch(e){}
  function apply(){sheet.classList.toggle(cls,!t.checked);try{localStorage.setItem(key,t.checked?'0':'1');}catch(e){}}
  t.addEventListener('change',apply);apply();
 });
 var pr=document.querySelector('[data-lc-print]');if(pr)pr.addEventListener('click',function(){window.print();});
})();
