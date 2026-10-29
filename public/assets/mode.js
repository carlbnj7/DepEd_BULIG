/* BULIG light / dark / auto. Loaded in <head> without defer so the right colours show
   before the page draws. The choice is kept on this device; "auto" follows the device setting. */
(function(){
 var d=document.documentElement,K='bulig-theme',mode='auto';
 try{mode=localStorage.getItem(K)||'auto';}catch(e){}
 var mq=window.matchMedia?matchMedia('(prefers-color-scheme: dark)'):{matches:false};
 function isDark(){return mode==='dark'||(mode==='auto'&&mq.matches);}
 function apply(){d.classList.toggle('dark',isDark());d.setAttribute('data-mode',mode);sync();}
 function sync(){if(!document.body)return;var dark=isDark();
  document.querySelectorAll('[data-mode-toggle]').forEach(function(b){b.setAttribute('aria-pressed',dark?'true':'false');b.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');b.title=dark?'Light mode':'Dark mode';});
  document.querySelectorAll('[data-mode-set]').forEach(function(b){var on=b.getAttribute('data-mode-set')===mode;b.classList.toggle('on',on);b.setAttribute('aria-checked',on?'true':'false');});}
 function set(m,from){var was=isDark();mode=m;try{localStorage.setItem(K,m);}catch(e){}
  var still=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(isDark()===was||still||!from){apply();return;}
  /* Light: a warm sunburst with turning rays grows from the button. Dark: a calm starry night. */
  var r=from.getBoundingClientRect(),x=r.left+r.width/2,y=r.top+r.height/2,R=Math.ceil(Math.hypot(Math.max(x,innerWidth-x),Math.max(y,innerHeight-y)))+40,light=!isDark();
  from.classList.remove('mode-spin');void from.offsetWidth;from.classList.add('mode-spin');
  var fx=document.createElement('div');fx.className='mode-fx '+(light?'mode-fx-sun':'mode-fx-night');fx.setAttribute('aria-hidden','true');
  fx.style.left=(x-R)+'px';fx.style.top=(y-R)+'px';fx.style.width=fx.style.height=(2*R)+'px';
  fx.innerHTML=light?'<i class="fx-rays"></i><i class="fx-glow"></i><i class="fx-core"></i>':'<i class="fx-sky"></i><i class="fx-stars"></i><i class="fx-moon"></i>';
  document.body.appendChild(fx);requestAnimationFrame(function(){requestAnimationFrame(function(){fx.classList.add('go');});});
  setTimeout(apply,1050);setTimeout(function(){fx.classList.add('out');},1150);setTimeout(function(){fx.remove();},2100);}
 apply();
 /* First page of a visit: show the BULIG opening screen while it loads (loading.js hides it). */
 try{if(!sessionStorage.getItem('bulig-open'))d.classList.add('bulig-boot');}catch(e){}
 if(mq.addEventListener)mq.addEventListener('change',apply);else if(mq.addListener)mq.addListener(apply);
 document.addEventListener('DOMContentLoaded',function(){sync();
  document.addEventListener('click',function(e){var t=e.target.closest&&e.target.closest('[data-mode-toggle],[data-mode-set]');if(!t)return;
   if(t.hasAttribute('data-mode-toggle'))set(isDark()?'light':'dark',t);else set(t.getAttribute('data-mode-set'),t);});});
 window.buligMode={get:function(){return mode;},set:set};
})();
/* Liquid Glass (v130): see-through menus and buttons. "lg-lite" keeps the solid look on slow phones (2 GB memory or less),
   when the device asks for less transparency, or when the pupil turns Glass look off in Settings. */
(function(){
 var d=document.documentElement,g=null;try{g=localStorage.getItem('bulig-glass');}catch(e){}
 var slow=(navigator.deviceMemory&&navigator.deviceMemory<=2)||(navigator.hardwareConcurrency&&navigator.hardwareConcurrency<=2);
 var less=window.matchMedia&&matchMedia('(prefers-reduced-transparency: reduce)').matches;
 if(g==='off'||(g!=='on'&&(slow||less)))d.classList.add('lg-lite');
})();

/* v170: dark mode safety net. Dark mode makes text light; a box that keeps a white or cream background (a popup, a
   note, a chip) would then hide its words. After the page draws, and whenever something new appears, light words on a
   light box get dark ink (class dk-ink). Only plain background colours are checked, never pictures or gradients. */
(function(){
 var d=document.documentElement;if(!window.MutationObserver||!window.getComputedStyle)return;
 function rgb(s){var m=/rgba?\(([^)]+)\)/.exec(s||'');if(!m)return null;var v=m[1].split(/[ ,\/]+/).filter(Boolean).map(Number);return {r:v[0],g:v[1],b:v[2],a:v.length>3?v[3]:1};}
 function lum(c){function f(x){x/=255;return x<=.03928?x/12.92:Math.pow((x+.055)/1.055,2.4);}return .2126*f(c.r)+.7152*f(c.g)+.0722*f(c.b);}
 function boxBg(el){for(var e=el;e&&e!==document.body;e=e.parentElement){var cs=getComputedStyle(e);if(cs.backgroundImage&&cs.backgroundImage!=='none')return null;var c=rgb(cs.backgroundColor);if(c&&c.a>=.85)return c;if(c&&c.a>.15)return null;}return null;}
 function scan(root){
  if(!d.classList.contains('dark')||!document.body||document.body.classList.contains('demo-page'))return;
  var w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT),n,seen=new Set(),k=0;
  while((n=w.nextNode())&&k<1500){k++;var el=n.parentElement;if(!el||seen.has(el)||!/\S/.test(n.nodeValue))continue;seen.add(el);
   if(el.closest('svg,.dk-ink,.demo-page'))continue;var fg=rgb(getComputedStyle(el).color);if(!fg||lum(fg)<.55)continue;
   var bg=boxBg(el);if(bg&&lum(bg)>.7)el.classList.add('dk-ink');}
 }
 var q=[],t=0;function later(){if(t)return;t=setTimeout(function(){t=0;var l=q.splice(0);for(var i=0;i<l.length;i++)if(l[i].isConnected)scan(l[i]);},120);}
 function go(){scan(document.body);new MutationObserver(function(ms){for(var i=0;i<ms.length;i++){var m=ms[i];if(m.type==='childList'){for(var j=0;j<m.addedNodes.length;j++){var a=m.addedNodes[j];if(a.nodeType===1){q.push(a);}}}else if(m.target.nodeType===1&&m.target!==document.body&&!(m.attributeName==='class'&&m.target.classList.contains('dk-ink')))q.push(m.target);}if(q.length)later();}).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','open','class']});}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',go);else go();
 new MutationObserver(function(){if(d.classList.contains('dark')&&document.body)scan(document.body);}).observe(d,{attributes:true,attributeFilter:['class']});
})();
/* Every voice says BULIG as one word, "buh-league", not B-U-L-I-G (v172). The word highlighting still follows the
   text on screen: the voice's word positions are moved back to where they are in the original text. */
(function(){
 var s=window.speechSynthesis;if(!s||!s.speak||s.__bulig)return;s.__bulig=1;
 var say='Buh-league',speak=s.speak.bind(s);
 s.speak=function(u){
  try{var t=String(u.text||''),re=/\bbulig\b/gi,m,out='',at=0,cut=[];
   while((m=re.exec(t))){out+=t.slice(at,m.index);cut.push([out.length,m.index]);out+=say;at=m.index+m[0].length;}
   if(cut.length){out+=t.slice(at);u.text=out;
    var back=function(i){var d=0;for(var k=0;k<cut.length;k++){if(i<cut[k][0])break;if(i<cut[k][0]+say.length)return cut[k][1];d=cut[k][0]+say.length-(cut[k][1]+5);}return i-d;};
    var ob=u.onboundary;if(ob)u.onboundary=function(e){ob.call(u,{name:e.name,charIndex:back(e.charIndex),charLength:e.charLength,elapsedTime:e.elapsedTime,utterance:u,target:u});};}
  }catch(e){}
  return speak(u);
 };
})();
