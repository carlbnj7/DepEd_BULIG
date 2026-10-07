/* BULIG guided tours (v1)
   Every pupil and teacher page has a short tour. The first time a page opens, the user's
   character offers to show them around; they can say yes or skip. A "?" button replays it. */
(function(){
 const metaEl=document.querySelector('meta[name="bulig-tour"]');if(!metaEl)return;
 let me;try{me=JSON.parse(metaEl.content);}catch(e){return;}
 const pupil=me.role==='pupil',q=new URLSearchParams(location.search),page=q.get('page')||'dashboard';
 const $=s=>document.querySelector(s);
 const store=(k,v)=>{try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v);}catch(e){return null;}};
 const visible=el=>{if(!el)return false;const r=el.getBoundingClientRect();if(r.width<4||r.height<4)return false;const cs=getComputedStyle(el);if(cs.visibility==='hidden'||cs.display==='none'||el.closest('[hidden]'))return false;
  /* Inside a closed fold (details) only the summary line shows. */
  for(let d=el.parentElement&&el.parentElement.closest('details:not([open])');d;d=d.parentElement&&d.parentElement.closest('details:not([open])')){const sm=d.querySelector(':scope>summary');if(!sm||!sm.contains(el))return false;}
  return true;};
 const find=sel=>{if(!sel)return null;for(const s of [].concat(sel)){for(const el of document.querySelectorAll(s))if(visible(el))return el;}return null;};
 const offline='serviceWorker' in navigator&&!!document.querySelector('script[src*="assets/offline"]');

 /* ---------- which tour belongs to this page ---------- */
 let key=page;
 if(page==='lessons'&&q.get('level'))key='map';
 if(page==='lesson')key=$('[data-l1-start]')?'l1-start':$('.l2-app')?'l2':$('.l1-app')?'l1':$('#activity-form')?'activity':($('.completion-card')?'lesson-done':'');
 if(page==='class_demo')key=$('#l1-demo')?'l1-demo':$('#class-demo')?'demo-slide':($('.demo-catalog')?'class_demo':'');
 if(page==='pupil')key='pupil-detail';

 /* Steps: [target selector(s) or null for a centred card, title, text] */
 const P={
  dashboard:[
   ['.identity-banner','This is you','Your name and grade are here. Tap your picture to change it.'],
   ['.level-stat','Your starting level','This is your starting level. Your teacher chose it for you, and your lessons begin here.'],
   ['.xp-stat','Your XP','You earn XP for every activity you finish. Collect XP to get rewards!'],
   ['.streak-stat','Your learning streak','Learn every day to make your flame grow. Tap here to see your calendar.'],
   ['.badge-stat','Your badges','Badges are prizes for learning. Tap to see the ones you earned.'],
   ['.goal-card','Today’s goal','Finish 3 activities a day. The ring fills up as you go!'],
   [['.next-adventure .btn.primary','.next-adventure'],'Start learning','Tap this button to go to your next lesson. BULIG remembers where you stopped.'],
   [['.pupil-tabbar .tab-play','.sidebar nav a[href="?page=lessons"]'],'All your lessons','Here you find all your levels and the road map of each level.'],
   [['.pupil-tabbar a[href="?page=calendar"]','.sidebar nav a[href="?page=calendar"]'],'Your calendar','See every day you learned.'],
   [['.pupil-tabbar a[href="?page=profile"]','.sidebar nav a[href="?page=profile"]'],'Your profile','Change your picture and see this week. Settings are there too.'],
   [['.notif-m','.notif-top'],'Your notifications','Tap the bell to see notes from your teacher, new badges and certificates. The yellow number shows how many are new.'],
   [['.mode-mobile','.mode-top'],'Light or dark','Tap the moon to make BULIG dark, easy on your eyes at night. Tap the sun to make it bright again.'],
   [['.mobile-signout button','.sidebar .signout'],'Signing out','Tap here when you are done. BULIG asks first, so you never leave by accident.'],
   [null,'Need help again?','Tap the yellow ? button on any page and I will show you how it works. Have fun learning!']],
  notifications:[
   ['.pageheading','Notifications','Everything new for you, newest first: notes from your teacher, badges and certificates.'],
   [['.nt-row'],'Open one','Tap a notification to go straight to it. A yellow dot means it is new.'],
   [['.pageheading form'],'Mark all as read','Clears the yellow number on the bell when you have seen everything.']],
  lessons:[
   ['.level-catalog .section-heading','Your levels','These are all the BULIG levels. Your teacher chose your starting level.'],
   [['.level-card.pf-current','.level-card.available'],'Your level','Your level is always at the top. Tap "Continue learning" to open your next lesson, or "See my learning path" for the road map.'],
   [['.level-fold>summary'],'Optional practice','Levels your teacher skipped are folded here. Tap to open them and practise whenever you like.'],
   [['.level-card.unavailable'],'Locked levels','A lock means you need to finish the level before it first. Tap a locked level to see what to do.'],
   offline?[['.level-card.pf-current .off-slot','.off-slot'],'Save for offline','No internet at home? Tap "Save for offline" while you have internet. The level is kept on this device so you can learn without internet.']:null,
   offline?[null,'Learning offline','When there is no internet, open a saved level and answer as usual. Your answers wait on this device and upload by themselves when the internet comes back.']:null],
  map:[
   ['.back-levels','Back to all levels','Tap here to go back to the list of levels.'],
   ['.level-banner','This level','The name of the level and how many activities you finished.'],
   [['.pfm-node.pfm-cur','.pf-mapcard'],'You are here','The big yellow stop is your next lesson. Tap it to start.'],
   [['.pfm-node.pfm-done','.pf-mapcard'],'Finished lessons','Green stops with a check are done. You can open them again to practise.'],
   [['.pfm-node.pfm-lock','.pf-mapcard'],'Coming next','Grey stops open one by one as you finish each lesson. Tap one to see which lesson to finish first.']],
  activity:[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   ['.narration','Listen','Tap Listen to hear the instructions. Tap again to pause. The dots button has Start again, Stop, Mute and the voice.'],
   [['.activity-title','.activity-instruction'],'What to do','Read the activity name and what you need to do.'],
   [['.native-deck','.visual-gallery','.demo-fluency','.activity-visual'],'Look at the pictures','Look carefully at the pictures and words. Tap a picture to see it big.'],
   [['.native-card:not([hidden]) .tap-choices'],'Tap your answer','Tap the answer you choose. It turns green and fills your answer box. Tap it again to undo.'],
   [['.native-card:not([hidden]) .native-answer-label','.answer-card','#response','.drawing-wrap','#drawing-canvas','.match-board','.choice-list'],'Your answer','Type or choose your answer here. Take your time.'],
   [['.native-card:not([hidden]) .ab-mic','.ab-mic'],'Speak your answer','Tap the red microphone and say your answer. The words you say appear in the box. Speaking needs internet.'],
   ['.speak-row','Speak or type','Tap "Speak Answer" and say your answer, or tap "Type Answer" to type it. Speaking needs internet.'],
   [['.native-card:not([hidden]) .lt-keys'],'Letter tiles','Tap a letter to fill in the missing letter. Tap Erase to undo, or abc to use the keyboard.'],
   [['.cd-dots'],'Your cards','Each number is a card. Green cards are answered. Tap a number to jump to that card.'],
   [['.rh-bar'],'Reading helpers','Tap Aa to make the words bigger. Tap Reading ruler to see one line at a time.'],
   [['.draw-choice'],'Draw or upload','Draw your answer here, or draw on paper and tap Upload a photo to send a picture of it.'],
   [['.draw-kit'],'Drawing tools','Pick a crayon colour and pen size. Undo removes your last line. Full screen gives you more room.'],
   [['.native-card-nav.deck-bar','#submit-answer'],'Next and Submit','Tap Next card to move on. On the last card, the yellow button sends your activity. Your work saves by itself.'],
   offline?[null,'No internet?','If this level is saved for offline, you can still answer. Your answer is kept on this device and uploads by itself later.']:null,
   ['.lesson-outline','All activities','Open this to see every activity in this lesson and what you already finished.']],
  'l1-start':[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   ['.l1-start h1','Your lesson','This is the lesson you will learn today.'],
   ['.l1-goals','Today you will…','These are the things you will learn. Tap the yellow speaker to hear them.'],
   ['.l1-steps','Three parts','First a few questions, then practice, then show what you learned.'],
   ['.l1-go','Start','Tap Let’s start! when you are ready.']],
  l1:[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   [['.l1-q .l1-spk','.l1-mission .l1-spk','.l1-listen .l1-spk','.l1-speed'],'Listen','Tap the yellow speaker to hear it again.'],
   [['.l1-mic'],'Say your answer','Tap the red microphone and say your answer. Tap it again to stop. Then listen to it, and tap Send.'],
   [['.l1-howto'],'Do it!','Listen, do the action, then tap I did it!'],
   [['.l1-pair'],'With a friend','Do this together with your partner or group, then tap We did it!'],
   [['.l1-dchoice'],'Your drawing','Draw on paper and take a photo, or draw here in BULIG. Check it, then send it.'],
   offline?[null,'No internet?','If this level is saved for offline, you can still answer. Your answer is kept on this device and uploads by itself later.']:null],
  l2:[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   ['.l2-dir','What to do','These are the directions from your book. Tap the yellow speaker to hear them.'],
   [['.l2-cdots'],'Your cards','Each number is a card. Green cards are answered. Tap a number to jump to it.'],
   [['.l2-card:not([hidden]) .l2-keys'],'Letter tiles','Tap the letters to write your answer. The red arrow erases.'],
   [['.l2-card:not([hidden]) .l2-chips'],'Tap a word','Tap the word or sound you choose. It turns green.'],
   [['.l2-card:not([hidden]) button.l2-pic'],'Tap a picture','Tap the picture you choose. It gets a green check.'],
   [['.l2-card:not([hidden]) .l2-nums'],'Count','Tap the number of sounds you hear.'],
   [['.l2-card:not([hidden]) .l1-mic'],'Say it','Tap the red microphone and say your answer. Tap again to stop.'],
   [['[data-l2-next]','#submit-answer'],'Next and Submit','Tap Next card to move on. On the last card, tap Submit.']],
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
   ['.cert-card','My certificates','Finish every lesson of a level to get a certificate. Tap it to print.'],
   ['.badgegrid','Rewards','Collect XP to reach each reward.']],
  offline:[
   ['.off-sum','Your saved lessons','See how many levels are saved on this device, and how much space they use.'],
   [['.op-all'],'Download everything','One tap saves every level you can open. Use Wi-Fi for big downloads.'],
   [['.op-row'],'Each level','Download, update or remove one level. “Needs update” means BULIG has new things.']],
  settings:[
   ['.st-sfx','Sound effects','Turn the little sounds on or off on this device.'],
   [['.st-seg'],'How BULIG looks','Choose Light, Dark, or Auto. Auto changes by itself when your device goes dark at night.'],
   [['a.st-row[href="?page=offline"]'],'Offline lessons','Save lessons on this device so they open even without internet.'],
   ['.st-pw','Your password','Tap here when you want to change your password.'],
   ['.st-about','About BULIG','See who made BULIG.']],
  profile:pupil?[
   ['.me-hero','Your profile','Your picture, your name and your class.'],
   [['.me-week .weekdays'],'This week','The days you learned this week have a check.'],
   ['.me-pic','Your picture','Choose a character, or upload your own photo.'],
   [['.me-setbtn','.sidebar nav a[href="?page=settings"]'],'Settings','Sound, colours, offline lessons, your password and About BULIG are in Settings.']]:[
   ['.char-pick','Your teacher character','Choose the character that greets you after you sign in and guides your tours.'],
   [['.card form.stack'],'Your photo','Upload your own photo for your account.'],
   [['.formgrid input[name="current_password"]'],'Password','Change your password here whenever you need to.'],
   ['.mode-card','How BULIG looks','Choose Light, Dark, or Auto. Auto follows your device setting.']]
 };
 const T={
  dashboard:[
   ['.sidebar nav a[href="?page=dashboard"]','Overview','Your class at a glance: pupils, activity this week, and who needs help.'],
   ['.class-tabs','Grades and sections','Choose a grade or section to see only those pupils.'],
   ['.tf-alert','Check-ins','Pupils who have not learned for a while appear here so you can follow up.'],
   ['.tdash-stats','Quick numbers','Total pupils, finished activities and learning steps.'],
   ['.mobile-menu-toggle','Menu','Tap here to open the menu: Class Demo, My pupils, My sections, Activity history and more. Each page has its own short tour.'],
   ['.sidebar nav a[href="?page=class_demo"]','Class Demo','Show any level on your TV or projector. Use "Mark as done" after you teach a lesson together.'],
   ['.sidebar nav a[href="?page=manage"]','My pupils','Three tabs: Learning (starting levels, lessons done in class), Accounts (add, import, edit, sign-in tickets) and Progress (the whole class at a glance).'],
   ['.sidebar nav a[href="?page=sections"]','My sections','Add the sections you handle.'],
   ['.sidebar nav a[href="?page=review"]','Activity history','Read your pupils’ answers and leave short feedback.'],
   [['.notif-top','.notif-m'],'Notifications','Tap the bell for answers waiting for your feedback, pupils who finished a level, and pupils who need a check-in. The yellow number shows what is new.'],
   [['.mode-top','.mode-mobile'],'Light or dark','Switch BULIG between light and dark colours. Choose Auto in My profile to follow your device.'],
   [['.sidebar .signout','.mobile-menu-toggle'],'Signing out','Sign out at the bottom of the menu when you are done. On a shared computer, untick "Keep me saved" when BULIG asks.'],
   [null,'You are all set!','Every page has its own short tour. Press the ? button anytime to see it.']],
  notifications:[
   ['.pageheading','Notifications','Work that needs you now is at the top. Notes about finished levels and new accounts come after.'],
   [['.nt-row'],'Open one','Tap one to go straight to the right page. Items under Needs you now go away by themselves once the work is done.'],
   [['.pageheading form'],'Mark all as read','Clears the yellow number on the bell when you have seen everything.']],
  class_demo:[
   ['.pageheading','Class Demo','Present lessons to the whole class on a TV or projector. Nothing is saved for pupils here.'],
   [['.demo-catalog .level-card.available'],'Choose a level','Tap "Start class demo". Levels 4 to 7 ask you to choose a grade first.']],
  'l1-demo':[
   ['.l1d-ln','The lesson','The lesson and the part you are on.'],
   ['[data-d-stage]','The slide','Ask the question. Pupils answer out loud. No typing.'],
   ['[data-d-listen]','Listen','BULIG reads the slide aloud.'],
   ['[data-d-next]','Next','Tap Next (or press the right arrow key) to move on.'],
   ['[data-d-lessons]','Jump to a lesson','Open any of the 12 lessons.'],
   ['[data-plan-open]','Lesson plan','The goals and the official module pages for this lesson.'],
   ['[data-cd-open]','Mark as done','Mark an activity or lesson done for the pupils who did it in class.']],
  'demo-slide':[
   ['.demo-header','Demo controls','"Mark as done" saves the lesson for the pupils in class. "Full screen" fills the TV.'],
   ['[data-plan-open]','Lesson plan','Open the goals, the official module pages and the PDF for the lesson on screen.'],
   ['.demo-controls','Jump around','Pick any lesson or slide. "Enlarge pictures" makes pictures bigger for the back row.'],
   [['.narration'],'Listen together','Read the instructions aloud with the same voice pupils hear.'],
   ['#demo-stage','The slide','Discuss the activity together. Pupils answer out loud or on paper.'],
   ['.demo-footer','Move between slides','Use Previous and Next, or the arrow keys. Press F for full screen.'],
   ['[data-cd-open]','Mark as done','After teaching, tap here and tick the pupils who were present. Their next lesson opens right away.']],
  accounts:[
   ['.pupil-tabs','Three tabs','Learning: starting levels and lessons done in class. Accounts: this tab, to add and edit pupils. Progress: the whole class at a glance.'],
   [['form.formgrid:not(.import-form)'],'Add one pupil','Fill in the name, section, sex and starting level. BULIG makes the Pupil ID and an easy starter password for you.'],
   [['form.formgrid:not(.import-form) .btn.primary','form.formgrid:not(.import-form)'],'Their ticket, right away','After you save, a window shows the new pupil’s sign-in ticket with the QR code. Print it there, or add the next pupil.'],
   ['#import','Import a class','Download the template, fill it in Excel, then upload it to add a whole class at once.'],
   [['details.account-detail'],'Pupil details','Open a pupil to edit details or reset the password.'],
   [['.acc-head a[href="?page=cards"]'],'Sign-in tickets','Print a ticket for each pupil with their Pupil ID, starter password and a QR code that signs them in with one scan.']],
  cards:[
   ['.lc-upgrade','Starter passwords','Pupils still on the old password 12345678 can each get their own easy starter password here. Print their new tickets right away.'],
   ['.lc-filter','Choose a section','Print tickets for one section or for all your pupils.'],
   [['.lc-opt'],'What to show','Turn the QR code or the starter password on or off. BULIG remembers your choice.'],
   ['.lc-pick','Choose and size','Untick the tickets you do not need, and choose Small (21 per page, 3 columns), Medium (10) or Large (8).'],
   ['[data-lc-print]','Print','Print on A4 paper and cut along the dashed lines.'],
   [['.tk'],'One ticket per pupil','One scan of the QR code signs the pupil in. The starter password shows until the pupil makes their own.'],
   [['.tk-new button'],'New ticket','Lost a ticket? Make a new one: the old QR code stops working and the pupil gets a new starter password.']],
  manage:[
   ['.pupil-tabs','Three tabs','You are on Learning. Open Accounts to add pupils or print sign-in tickets, and Progress to see the whole class.'],
   ['.mp-list','Your pupils','Search or filter by section, then tap a pupil to manage them.'],
   ['.mp-bulkbar','Many pupils at once','Tick pupils in the list and set one starting level for all of them.'],
   ['.mp-start','Starting level','Change where this pupil begins. Earlier levels become optional practice.'],
   ['.cd-path','Learning path','Every level and lesson with its status. Open "Activities" to tick single activities.'],
   [['.cd-actions'],'Mark as done in class','Tick lessons or activities you did together, then save. Use "Undo" if you made a mistake.']],
  progress:[
   ['.pupil-tabs','Progress tab','Every pupil and every lesson of one level, on one screen.'],
   ['.pg-filter','Choose a section and level','The grid changes as soon as you pick.'],
   [['.pg-table'],'The grid','Green is done, light green is started, yellow means no work for 7+ days. Tap a name to open that pupil.'],
   [['a[href*="page=certificates"]'],'Certificates','Print certificates for every pupil who finished this level.']],
  sections:[
   ['.section-add','Add a section','Type the section name, choose the grade and add it.'],
   ['.section-board','Your sections','Sections grouped by grade. Tap one to rename or archive it.']],
  review:[
   ['.pageheading','Activity history','Read what your pupils answered and leave short notes.'],
   ['.rv-tabs','Needs feedback','New answers wait here. Once you save feedback, an answer moves to Reviewed.'],
   ['.rv-filter','One pupil at a time','Choose a pupil to see only their answers.'],
   [['.rv-item'],'Open an answer','Tap a line to read the answer, drawing or reading result.'],
   [['.rv-phrases'],'Quick feedback','Tap a ready-made phrase, or write your own note. Then press Save.']],
  guide:[
   [['form.card'],'Choose a lesson','The full text of each lesson plan. In Class Demo, tap "Lesson plan" for a quick view.'],
   [['.source-pages'],'Original pages','Open the matching pages of the official module.'],
   [['a.btn.secondary[href*="download_source"]'],'Download the module','Get the original module file for printing.']],
  'pupil-detail':[
   ['.pageheading','Pupil progress','XP, finished lessons and streak for this pupil.'],
   ['#learning-path','Learning path','Mark lessons as done in class here, or use My pupils for activities too.']],
  profile:P.profile
 };
 const A={
  dashboard:[
   ['.ws-kpis','The school at a glance','Pupils, teachers, grades and learning this week, for the whole school.'],
   [['.ws-g2 section.card'],'Pupils at each level','How many pupils are working in each BULIG level.'],
   [['.ws-g2 section.card:nth-child(2)'],'Needs attention','Teachers who have not signed in lately and other things to follow up.'],
   ['.ws-btns','Quick actions','Post an announcement for teachers, or open the school reports.'],
   ['.sidebar nav a[href="?page=accounts"]','Teachers','Add teachers, reset passwords and move pupils between teachers.'],
   ['.sidebar nav a[href="?page=reports"]','Reports','Progress by grade and section. Download it for division reports.'],
   ['.sidebar nav a[href="?page=content"]','Lesson studio','Show, hide or edit the activities of each level.'],
   ['.sidebar nav a[href="?page=activity_log"]','Activity log','Who signed in and what changed, with wrong passwords and PINs.'],
   ['.sidebar nav a[href="?page=health"]','System health','Install checks, pictures, database updates and backups.'],
   ['.sidebar nav a[href="?page=settings"]','Settings','Your PIN, school details, announcements, badges and the school year.'],
   ['.mobile-menu-toggle','Menu','Tap here to open the menu: Teachers, Reports, Lesson studio, Activity log, System health and Settings. Each page has its own short tour.'],
   [['.notif-top','.notif-m'],'Notifications','Tap the bell for System health alerts and new pupil accounts. The yellow number shows what is new.'],
   [['.mode-top','.mode-mobile'],'Light or dark','Switch BULIG between light and dark colours. Choose Auto in My profile to follow your device.'],
   [['.sidebar .signout','.mobile-menu-toggle'],'Signing out','Sign out at the bottom of the menu when you are done. Admin accounts are never saved on a device.'],
   [null,'You are all set!','Every page has its own short tour. Press the ? button anytime to see it.']],
  notifications:[
   ['.pageheading','Notifications','System health alerts and new pupil accounts, newest first.'],
   [['.nt-row'],'Open one','Tap one to open the page where you can fix it.'],
   [['.pageheading form'],'Mark all as read','Clears the yellow number on the bell when you have seen everything.']],
  accounts:[
   [['#add-teacher','.ws-btns'],'Add a teacher','Enter the name and sex. BULIG makes the teacher ID and a first password.'],
   ['.ws-filters','Find a teacher','Search by name, or filter by grade and status.'],
   ['table.ws-table','Your teaching team','Sections, pupils and last sign-in for each teacher. Edit names, reset passwords or turn an account off here.'],
   ['#move','Move pupils','Move pupils to another teacher. They keep their progress, XP and badges.']],
  reports:[
   ['.ws-filters','Choose what to see','Pick the period, grade and section, then choose Show.'],
   ['.ws-kpis','Key numbers','Pupils, average score, finished lessons and pupils who need a check-in.'],
   ['table.ws-table','Progress by section','Each grade and section with its teacher, pupils and activities.'],
   ['.ws-btns','Download or print','Download the report for Excel, or print it and save it as a PDF.']],
  content:[
   ['nav.ws-levels','Choose a level','Pick the level you want to look at.'],
   ['form.ws-filters','Choose a lesson','Pick a grade and lesson, then choose Open.'],
   ['table.ws-table','Activities','Every activity in the lesson. Show or hide it for pupils, or edit its text and pictures.'],
   [['details.card'],'Assessments','Open an assessment to update its rubric and scoring.']],
  activity_log:[
   ['nav.ws-tabs','Filter by kind','See all events, or only sign-ins, wrong passwords and PINs, account changes or content changes.'],
   ['.ws-filters','Search','Search for a name or ID and pick the dates.'],
   ['table.ws-table','What happened','When, who, what happened and the details.'],
   ['.ws-btns','Download','Download the log as a CSV file.']],
  health:[
   [['.ws-g2 section.card'],'Checks','Green means fine. Red shows what needs attention, with the fix next to it.'],
   ['.ws-btns','Run all checks','Check everything again after you upload an update.'],
   ['#backup','Backup','Download a copy of the database. Keep it somewhere safe.']],
  settings:[
   ['nav.ws-tabs','Settings sections','Security, school details, announcements, badges and rewards, and the school year.'],
   ['#admin-pin','Admin PIN','Change the 4-digit PIN you enter after your password.'],
   [['.ws-g2 section.card:nth-child(2)'],'Sign-in rules','How BULIG protects accounts from wrong passwords.']],
  profile:[
   [['.char-pick'],'Your character','Choose a female or male admin character. It greets you after you sign in and guides your tours.'],
    ['.mode-card','How BULIG looks','Choose Light, Dark or Auto.'],
   [['.card form.stack'],'Your photo','Upload your own photo for your account.'],
   [['.formgrid input[name="current_password"]'],'Password','Change your password here whenever you need to.']]
 };
 const raw=(pupil?P:me.role==='admin'?A:T)[key];if(!raw)return;
 const steps=raw.filter(Boolean);
 const seenKey='bulig-tour-'+me.id+'-dashboard';
 /* The automatic offer appears once per account, on the dashboard only; the server remembers it. */
 function markDone(){if(key!=='dashboard'||me.seen)return;me.seen=true;store(seenKey,'1');
  try{const fd=new FormData();fd.set('csrf',(document.querySelector('meta[name="csrf"]')||{}).content||'');fd.set('action','tour_seen');fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'},keepalive:true}).catch(()=>{});}catch(e){}}

 /* ---------- UI ---------- */
 const charImg=()=>{const i=document.createElement('img');i.src=me.char;i.alt='';i.width=62;i.height=84;return i;};
 let layer=null,idx=-1,cur=[],raf=0;
 function close(markSeen){if(markSeen)markDone();if(layer){layer.remove();layer=null;}document.removeEventListener('keydown',keys,true);removeEventListener('resize',place);removeEventListener('scroll',place,true);document.body.classList.remove('tour-on');}
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
 function place(){if(!layer)return;cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{if(!layer)return;
  const spot=layer.querySelector('.tour-spot'),bub=layer.querySelector('.tour-bub');const el=cur[idx]&&find(cur[idx][0]);
  if(!el){spot.style.cssText='left:50%;top:50%;width:0;height:0';bub.style.left=Math.max(12,(innerWidth-bub.offsetWidth)/2)+'px';bub.style.top=Math.max(12,(innerHeight-bub.offsetHeight)/2)+'px';return;}
  const r0=el.getBoundingClientRect(),r={left:r0.left,top:r0.top,right:r0.right,bottom:r0.bottom};
  if(el.querySelectorAll('*').length<40)el.querySelectorAll('*').forEach(c=>{const q=c.getBoundingClientRect();if(!q.width||!q.height)return;r.left=Math.min(r.left,q.left);r.top=Math.min(r.top,q.top);r.right=Math.max(r.right,q.right);r.bottom=Math.max(r.bottom,q.bottom);});
  r.width=r.right-r.left;r.height=r.bottom-r.top;const pad=8,big=r.height>innerHeight*.7;spot.classList.toggle('tour-pill',el.matches('.tab-play'));
  const t=Math.max(6,r.top-pad),b=Math.min(innerHeight-6,r.bottom+pad);
  spot.style.cssText='left:'+(r.left-pad)+'px;top:'+t+'px;width:'+(r.width+pad*2)+'px;height:'+Math.max(10,b-t)+'px';
  const bw=bub.offsetWidth,bh=bub.offsetHeight,gap=14,bar=find('.pupil-tabbar'),floor=innerHeight-(bar?bar.getBoundingClientRect().height+24:16);let x,y;
  if(innerWidth>900&&r.right+gap+bw<innerWidth-12&&r.width<innerWidth*.45){x=r.right+gap;y=Math.min(Math.max(12,r.top),innerHeight-bh-12);}
  else{x=Math.min(Math.max(12,r.left+r.width/2-bw/2),innerWidth-bw-12);
   if(big)y=floor-bh;else if(b+gap+bh<floor)y=b+gap;else if(t-gap-bh>12)y=t-gap-bh;else y=floor-bh;}
  bub.style.left=x+'px';bub.style.top=y+'px';});}

 /* First visit: the character offers the tour. */
 function offer(){if(document.querySelector('.tour-offer'))return;if(window.buligDlPending){document.addEventListener('bulig:dl-done',()=>setTimeout(offer,600),{once:true});return;}if(document.querySelector('[data-save-offer]')&&!window.buligSaveDone){document.addEventListener('bulig:save-done',()=>setTimeout(offer,500),{once:true});return;}const big=key==='dashboard';
  const o=document.createElement('div');o.className='tour-offer'+(big?' tour-offer-big':'');o.setAttribute('role','dialog');o.setAttribute('aria-label','Guided tour');
  o.innerHTML='<div class="tour-offer-card"><div class="tour-offer-txt"><b></b><span></span></div><div class="tour-offer-btns"><button type="button" class="btn primary tour-yes"></button><button type="button" class="tour-no">Skip for now</button></div></div>';
  o.querySelector('.tour-offer-card').prepend(charImg());
  o.querySelector('b').textContent=big?(pupil?'Hi, '+me.name+'! I am your reading buddy.':'Welcome to BULIG, Teacher '+me.name+'!'):(pupil?'New here?':'New page');
  o.querySelector('.tour-offer-txt span').textContent=big?(pupil?'Do you want me to show you around BULIG?':'Would you like a quick tour of your workspace?'):(pupil?'I can show you how this page works.':'Want a quick tour of this page?');
  o.querySelector('.tour-yes').textContent=big?'Yes, show me!':'Show me';
  o.querySelector('.tour-yes').onclick=()=>{o.remove();markDone();start();};
  o.querySelector('.tour-no').onclick=()=>{o.remove();markDone();toast(pupil?'Okay! Tap the yellow ? anytime to see the tour.':'You can open the tour anytime with the ? button.');};
  document.body.append(o);setTimeout(()=>o.querySelector('.tour-yes').focus({preventScroll:true}),80);}
 function toast(t){document.querySelectorAll('.tour-toast').forEach(x=>x.remove());const d=document.createElement('div');d.className='tour-toast';d.setAttribute('role','status');d.textContent=t;document.body.append(d);
  /* Sit right next to the ? button, with a little tail pointing at it. */
  const r=help.getBoundingClientRect(),w=Math.min(280,innerWidth-24),left=r.left+r.width/2<innerWidth/2;d.style.width=w+'px';
  d.style.left=(left?Math.max(12,r.left):Math.min(innerWidth-w-12,r.right-w+4))+'px';d.style.bottom=(innerHeight-r.top+14)+'px';d.classList.add(left?'tail-left':'tail-right');
  help.classList.add('tour-help-ping');setTimeout(()=>help.classList.remove('tour-help-ping'),3600);setTimeout(()=>d.classList.add('out'),4200);setTimeout(()=>d.remove(),4700);}

 /* The ? button on every page that has a tour. */
 const help=document.createElement('button');help.type='button';help.className='tour-help'+(pupil?' tour-help-pupil':'')+(key==='demo-slide'?' tour-help-demo':'')+(key==='activity'||key==='lesson-done'?' tour-help-activity':'');help.setAttribute('aria-label','Show me how this page works');help.title='Show me how this page works';help.textContent='?';
 help.addEventListener('click',()=>{document.querySelector('.tour-offer')?.remove();start();});document.body.append(help);

 /* Someone who already answered the offer on this device (older BULIG kept it per page) is told to the server, not asked again. */
 const answeredHere=()=>{try{for(let i=0;i<localStorage.length;i++)if((localStorage.key(i)||'').indexOf('bulig-tour-'+me.id+'-')===0)return true;}catch(e){}return false;};
 const ready=()=>{if(key!=='dashboard'||me.seen)return;if(answeredHere())markDone();else setTimeout(offer,900);};
 if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',ready,{once:true});else ready();
})();
