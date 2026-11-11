/* Reveal enhanced lesson sections as soon as deferred setup finishes.
   No overlay, animation, asset wait or minimum display time. */
(function(){
 'use strict';
 const root=document.documentElement;
 root.classList.add('lesson-enhancing');
 let timer;
 function reveal(){clearTimeout(timer);root.classList.remove('lesson-enhancing');}
 // A failed or stalled script must leave the original HTML usable.
 timer=setTimeout(reveal,4000);
 document.addEventListener('DOMContentLoaded',reveal,{once:true});
 window.addEventListener('pageshow',reveal);
})();
