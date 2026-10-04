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
