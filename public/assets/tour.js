/* BULIG guided tours (v1)
   Every pupil and teacher page has a short tour. The first time a page opens, the user's
   character offers to show them around; they can say yes or skip. A "?" button replays it. */
(function(){
 const metaEl=document.querySelector('meta[name="bulig-tour"]');if(!metaEl)return;
 let me;try{me=JSON.parse(metaEl.content);}catch(e){return;}
 const pupil=me.role==='pupil',q=new URLSearchParams(location.search),page=q.get('page')||'dashboard';
 const $=s=>document.querySelector(s);
 const store=(k,v)=>{try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v);}catch(e){return null;}};
 const visible=el=>{if(!el)return false;const r=el.getBoundingClientRect();if(r.width<4||r.height<4)return false;const cs=getComputedStyle(el);return cs.visibility!=='hidden'&&cs.display!=='none'&&!el.closest('[hidden]');};
 const find=sel=>{if(!sel)return null;for(const s of [].concat(sel)){for(const el of document.querySelectorAll(s))if(visible(el))return el;}return null;};
 const offline='serviceWorker' in navigator&&!!document.querySelector('script[src*="assets/offline"]');

 /* ---------- which tour belongs to this page ---------- */
 let key=page;
 if(page==='lessons'&&q.get('level'))key='map';
 if(page==='lesson')key=$('#activity-form')?'activity':($('.completion-card')?'lesson-done':'');
 if(page==='class_demo')key=$('#class-demo')?'demo-slide':($('.demo-catalog')?'class_demo':'');
 if(page==='pupil')key='pupil-detail';

 /* Steps: [target selector(s) or null for a centred card, title, text] */
 const P={
  dashboard:[
   ['.identity-banner','This is you','Your name and grade are here. Tap your picture to change it.'],
   ['.xp-stat','Your XP','You earn XP for every activity you finish. Collect XP to get rewards!'],
   ['.streak-stat','Your learning streak','Learn every day to make your flame grow. Tap here to see your calendar.'],
   ['.badge-stat','Your badges','Badges are prizes for learning. Tap to see the ones you earned.'],
   [['.next-adventure .btn.primary','.next-adventure'],'Start learning','Tap this button to go to your next lesson. BULIG remembers where you stopped.'],
   [['.pupil-tabbar .tab-play','.sidebar nav a[href="?page=lessons"]'],'All your lessons','Here you find all your levels and the road map of each level.'],
   [['.pupil-tabbar a[href="?page=calendar"]','.sidebar nav a[href="?page=calendar"]'],'Your calendar','See every day you learned.'],
   [['.pupil-tabbar a[href="?page=profile"]','.sidebar nav a[href="?page=profile"]'],'Your profile','Change your picture, see this week, and find your offline lessons.'],
   [null,'Need help again?','Tap the yellow ? button on any page and I will show you how it works. Have fun learning!']],
  lessons:[
   ['.level-catalog .section-heading','Your levels','These are all the BULIG levels. Your teacher chose your starting level.'],
   [['.level-card.pf-current','.level-card.available'],'Open a level','Tap "Open lessons" to see the road map of a level and start learning.'],
   [['.level-card.unavailable'],'Locked levels','A lock means you need to finish the level before it first. Keep going!'],
   offline?[['.level-card.pf-current .off-slot','.off-slot'],'Save for offline','No internet at home? Tap "Save for offline" while you have internet. The level is kept on this device so you can learn without internet.']:null,
   offline?[null,'Learning offline','When there is no internet, open a saved level and answer as usual. Your answers wait on this device and upload by themselves when the internet comes back.']:null],
  map:[
   ['.back-levels','Back to all levels','Tap here to go back to the list of levels.'],
   ['.level-banner','This level','The name of the level and how many activities you finished.'],
   [['.pfm-node.pfm-cur','.pf-mapcard'],'You are here','The big yellow stop is your next lesson. Tap it to start.'],
   [['.pfm-node.pfm-done','.pf-mapcard'],'Finished lessons','Green stops with a check are done. You can open them again to practise.'],
   [['.pfm-node.pfm-lock','.pf-mapcard'],'Coming next','Grey stops open one by one as you finish each lesson.']],
  activity:[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   ['.narration','Listen','Tap Listen to hear the instructions read aloud. You can pause, replay or stop.'],
   [['.activity-title','.activity-instruction'],'What to do','Read the activity name and what you need to do.'],
   [['.native-deck','.visual-gallery','.demo-fluency','.activity-visual'],'Look at the pictures','Look carefully at the pictures and words. If there are cards, use "Next picture" to see each one.'],
   [['.native-card:not([hidden]) .native-answer-label','.answer-card','#response','.drawing-wrap','#drawing-canvas','.match-board','.choice-list'],'Your answer','Type or choose your answer here. Take your time.'],
   ['.speak-row','Speak or type','Tap "Speak Answer" and say your answer, or tap "Type Answer" to type it. Speaking needs internet.'],
   ['#submit-answer','Submit','When you are done, tap Submit. Your work saves by itself while you type, so nothing is lost.'],
   offline?[null,'No internet?','If this level is saved for offline, you can still answer. Your answer is kept on this device and uploads by itself later.']:null,
   ['.lesson-outline','All activities','Open this to see every activity in this lesson and what you already finished.']],
  'lesson-done':[
   ['.completion-card','Lesson finished!','Great job! Tap the button to save the lesson and go back to your map.']],
  calendar:[
   ['.pf-cal-sum','Your streak','How many days in a row you learned, and your best streak ever.'],
   ['.pf-cal-grid','Your learning days','Days with a flame are days you learned. Try to learn a little every day!'],
   ['.pf-cal-mon','Other months','Use the arrows to look at other months.']],
  achievements:[
   ['.pf-shelf','Your badge shelf','All the badges you can earn in BULIG.'],
   [['.pf-shelf-item.pf-got'],'Badges you earned','Shiny badges are yours! The date shows when you earned it.'],
   [['.pf-shelf-item.pf-locked'],'Badges to earn','Read what to do to unlock each badge.'],
   ['.badgegrid','Rewards','Collect XP to reach each reward.']],
  profile:pupil?[
   ['.avatar-picker','Your picture','Choose a character for your profile picture.'],
   ['.off-profile','Offline lessons','Levels saved on this device are listed here. If answers are waiting to upload, tap "Upload now".'],
   [['.weekdays'],'This week','The days you learned this week have a check.']]:[
   ['.char-pick','Your teacher character','Choose the character that greets you after you sign in.'],
   [['.card form.stack'],'Your photo','Upload your own photo for your account.'],
   [['.formgrid input[name="current_password"]'],'Password','Change your password here whenever you need to.']]
 };
 const T={
  dashboard:[
   ['.sidebar nav a[href="?page=dashboard"]','Overview','Your class at a glance: pupils, activity this week, and who needs help.'],
   ['.class-tabs','Grades and sections','Choose a grade or section to see only those pupils.'],
   ['.tf-alert','Check-ins','Pupils who have not learned for a while appear here so you can follow up.'],
   ['.tdash-stats','Quick numbers','Total pupils, finished activities and learning steps.'],
   ['.mobile-menu-toggle','Menu','Tap here to open the menu: Class Demo, My pupils, Manage pupils, Activity history and more. Each page has its own short tour.'],
   ['.sidebar nav a[href="?page=class_demo"]','Class Demo','Show any level on your TV or projector. Use "Mark as done" after you teach a lesson together.'],
   ['.sidebar nav a[href="?page=accounts"]','My pupils','Add pupils one by one or import a whole class list.'],
   ['.sidebar nav a[href="?page=manage"]','Manage pupils','Change starting levels and mark levels, lessons or activities as done in class.'],
   ['.sidebar nav a[href="?page=sections"]','My sections','Add the sections you handle.'],
   ['.sidebar nav a[href="?page=review"]','Activity history','Read your pupils’ answers and leave short feedback.'],
   ['.sidebar nav a[href="?page=guide"]','Teaching guide','Lesson plans and the original module pages for each lesson.'],
   [null,'You are all set!','Every page has its own short tour. Press the ? button anytime to see it.']],
  class_demo:[
   ['.pageheading','Class Demo','Present lessons to the whole class on a TV or projector. Nothing is saved for pupils here.'],
   [['.demo-catalog .level-card.available'],'Choose a level','Tap "Start class demo". Levels 4 to 7 ask you to choose a grade first.']],
  'demo-slide':[
   ['.demo-header','Demo controls','"Mark as done" saves the lesson for the pupils in class. "Full screen" fills the TV.'],
   ['.demo-controls','Jump around','Pick any lesson or slide. "Enlarge pictures" makes pictures bigger for the back row.'],
   [['.narration'],'Listen together','Read the instructions aloud with the same voice pupils hear.'],
   ['#demo-stage','The slide','Discuss the activity together. Pupils answer out loud or on paper.'],
   ['.demo-footer','Move between slides','Use Previous and Next, or the arrow keys. Press F for full screen.'],
   ['[data-cd-open]','Mark as done','After teaching, tap here and tick the pupils who were present. Their next lesson opens right away.']],
  accounts:[
   ['.pageheading','Your pupils','Create pupil accounts and keep their details up to date.'],
   [['form.formgrid:not(.import-form)'],'Add one pupil','Fill in the name, section, sex and starting level. BULIG makes the Pupil ID for you.'],
   ['#import','Import a class','Download the template, fill it in Excel, then upload it to add a whole class at once.'],
   [['details.account-detail'],'Pupil details','Open a pupil to edit details, reset the password, or print sign-in cards.']],
  manage:[
   ['.mp-list','Your pupils','Search or filter by section, then tap a pupil to manage them.'],
   ['.mp-bulkbar','Many pupils at once','Tick pupils in the list and set one starting level for all of them.'],
   ['.mp-start','Starting level','Change where this pupil begins. Earlier levels become optional practice.'],
   ['.cd-path','Learning path','Every level and lesson with its status. Open "Activities" to tick single activities.'],
   [['.cd-actions'],'Mark as done in class','Tick lessons or activities you did together, then save. Use "Undo" if you made a mistake.']],
  sections:[
   ['.section-add','Add a section','Type the section name, choose the grade and add it.'],
   ['.section-board','Your sections','Sections grouped by grade. Tap one to rename or archive it.']],
  review:[
   ['.pageheading','Activity history','Read what your pupils answered.'],
   [['.assessment-review'],'Assessment feedback','For assessments, read the original rubric and give an optional score.'],
   [['.review-card'],'Pupil answers','Each card shows the activity, the pupil’s answer and drawings. Answers made offline have a blue tag.'],
   [['.review-card form','.assessment-review form'],'Leave feedback','Write a short note. The pupil sees it in their lesson.']],
  guide:[
   [['form.card'],'Choose a lesson','Pick a level and lesson, then open its guide.'],
   [['.source-pages'],'Original pages','Open the matching pages of the official module.'],
   [['a.btn.secondary[href*="download_source"]'],'Download the module','Get the original module file for printing.']],
  'pupil-detail':[
   ['.pageheading','Pupil progress','XP, finished lessons and streak for this pupil.'],
   ['#learning-path','Learning path','Mark lessons as done in class here, or use Manage pupils for activities too.']],
  profile:P.profile
 };
 const raw=(pupil?P:T)[key];if(!raw)return;
 const steps=raw.filter(Boolean);
 const seenKey='bulig-tour-'+me.id+'-'+key;

 /* ---------- UI ---------- */
 const charImg=()=>{const i=document.createElement('img');i.src=me.char;i.alt='';i.width=62;i.height=84;return i;};
 let layer=null,idx=-1,cur=[],raf=0;
 function close(markSeen){if(markSeen)store(seenKey,'1');if(layer){layer.remove();layer=null;}document.removeEventListener('keydown',keys,true);removeEventListener('resize',place);removeEventListener('scroll',place,true);document.body.classList.remove('tour-on');}
 function keys(e){if(!layer)return;if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close(true);}else if(e.key==='ArrowRight'){e.preventDefault();e.stopPropagation();go(idx+1);}else if(e.key==='ArrowLeft'){e.preventDefault();e.stopPropagation();if(idx>0)go(idx-1);}}
 function start(){close(false);cur=steps.filter(s=>!s[0]||find(s[0]));if(!cur.length)return;
  layer=document.createElement('div');layer.className='tour-layer';layer.setAttribute('role','dialog');layer.setAttribute('aria-modal','true');layer.setAttribute('aria-label','Guided tour');
  layer.innerHTML='<div class="tour-block"></div><div class="tour-spot" aria-hidden="true"></div><div class="tour-bub"><div class="tour-bub-in"><div class="tour-txt"><h3></h3><p></p></div></div><div class="tour-nav"><span class="tour-dots" aria-hidden="true"></span><button type="button" class="tour-skip">Skip</button><button type="button" class="tour-back">Back</button><button type="button" class="tour-next">Next</button></div></div>';
  layer.querySelector('.tour-bub-in').prepend(charImg());
  layer.querySelector('.tour-skip').onclick=()=>close(true);layer.querySelector('.tour-back').onclick=()=>{if(idx>0)go(idx-1);};layer.querySelector('.tour-next').onclick=()=>go(idx+1);
  document.body.append(layer);document.body.classList.add('tour-on');document.addEventListener('keydown',keys,true);addEventListener('resize',place);addEventListener('scroll',place,true);go(0);}
 function go(n){if(!layer)return;if(n>=cur.length){close(true);return;}idx=n;const [sel,title,text]=cur[n];
  layer.querySelector('h3').textContent=title;layer.querySelector('.tour-txt p').textContent=text;
  layer.querySelector('.tour-dots').innerHTML=cur.map((_,k)=>'<i'+(k===n?' class="on"':'')+'></i>').join('');
  layer.querySelector('.tour-back').style.visibility=n?'visible':'hidden';const nx=layer.querySelector('.tour-next');nx.textContent=n===cur.length-1?'Finish':'Next';
  const el=find(sel);layer.classList.toggle('tour-center',!el);
  if(el){const r=el.getBoundingClientRect();if(r.top<70||r.bottom>innerHeight-(find('.pupil-tabbar')?240:150))el.scrollIntoView({block:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});}
  place();setTimeout(place,380);setTimeout(()=>nx.focus({preventScroll:true}),60);}
 function place(){if(!layer)return;cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{
  const spot=layer.querySelector('.tour-spot'),bub=layer.querySelector('.tour-bub');const el=cur[idx]&&find(cur[idx][0]);
  if(!el){spot.style.cssText='left:50%;top:50%;width:0;height:0';bub.style.left=Math.max(12,(innerWidth-bub.offsetWidth)/2)+'px';bub.style.top=Math.max(12,(innerHeight-bub.offsetHeight)/2)+'px';return;}
  const r=el.getBoundingClientRect(),pad=8,big=r.height>innerHeight*.7;
  const t=Math.max(6,r.top-pad),b=Math.min(innerHeight-6,r.bottom+pad);
  spot.style.cssText='left:'+(r.left-pad)+'px;top:'+t+'px;width:'+(r.width+pad*2)+'px;height:'+Math.max(10,b-t)+'px';
  const bw=bub.offsetWidth,bh=bub.offsetHeight,gap=14,bar=find('.pupil-tabbar'),floor=innerHeight-(bar?bar.getBoundingClientRect().height+24:16);let x,y;
  if(innerWidth>900&&r.right+gap+bw<innerWidth-12&&r.width<innerWidth*.45){x=r.right+gap;y=Math.min(Math.max(12,r.top),innerHeight-bh-12);}
  else{x=Math.min(Math.max(12,r.left+r.width/2-bw/2),innerWidth-bw-12);
   if(big)y=floor-bh;else if(b+gap+bh<floor)y=b+gap;else if(t-gap-bh>12)y=t-gap-bh;else y=floor-bh;}
  bub.style.left=x+'px';bub.style.top=y+'px';});}

 /* First visit: the character offers the tour. */
 function offer(){if(document.querySelector('.tour-offer'))return;const big=key==='dashboard';
  const o=document.createElement('div');o.className='tour-offer'+(big?' tour-offer-big':'');o.setAttribute('role','dialog');o.setAttribute('aria-label','Guided tour');
  o.innerHTML='<div class="tour-offer-card"><div class="tour-offer-txt"><b></b><span></span></div><div class="tour-offer-btns"><button type="button" class="btn primary tour-yes"></button><button type="button" class="tour-no">Skip for now</button></div></div>';
  o.querySelector('.tour-offer-card').prepend(charImg());
  o.querySelector('b').textContent=big?(pupil?'Hi, '+me.name+'! I am your reading buddy.':'Welcome to BULIG, Teacher '+me.name+'!'):(pupil?'New here?':'New page');
  o.querySelector('.tour-offer-txt span').textContent=big?(pupil?'Do you want me to show you around BULIG?':'Would you like a quick tour of your workspace?'):(pupil?'I can show you how this page works.':'Want a quick tour of this page?');
  o.querySelector('.tour-yes').textContent=big?'Yes, show me!':'Show me';
  o.querySelector('.tour-yes').onclick=()=>{o.remove();start();};
  o.querySelector('.tour-no').onclick=()=>{o.remove();store(seenKey,'1');toast(pupil?'Okay! Tap the yellow ? anytime to see the tour.':'You can open the tour anytime with the ? button.');};
  document.body.append(o);setTimeout(()=>o.querySelector('.tour-yes').focus({preventScroll:true}),80);}
 function toast(t){const d=document.createElement('div');d.className='tour-toast';d.setAttribute('role','status');d.textContent=t;document.body.append(d);setTimeout(()=>d.remove(),4200);}

 /* The ? button on every page that has a tour. */
 const help=document.createElement('button');help.type='button';help.className='tour-help'+(pupil?' tour-help-pupil':'')+(key==='demo-slide'?' tour-help-demo':'')+(key==='activity'||key==='lesson-done'?' tour-help-activity':'');help.setAttribute('aria-label','Show me how this page works');help.title='Show me how this page works';help.textContent='?';
 help.addEventListener('click',()=>{document.querySelector('.tour-offer')?.remove();start();});document.body.append(help);

 const ready=()=>{if(!store(seenKey))setTimeout(offer,key==='dashboard'?900:1200);};
 if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',ready,{once:true});else ready();
})();
