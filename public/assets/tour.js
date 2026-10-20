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
  credits:[
   ['.cr-hero','Credits','BULIG is made from the official DepEd modules of the Schools Division of Bukidnon.'],
   [['.cr-cols .cr-card'],'The BULIG module','The writers, designers and checkers who made the Reading Toolkit.'],
   [['.cr-cols .cr-card:nth-child(2)'],'Management team','The Division leaders who guided the module.'],
   [['.cr-web'],'Web app','The people and tools that built this web app.']],
  dashboard:[
   ['.identity-banner','This is you','Your name and grade are here. Tap your picture to change it.'],
   ['.level-stat','My level now','The level you are on now and how much of it you finished. It also shows the level your teacher started you at. Tap it to go to that level.'],
   [['.rl-stat','.xp-stat'],'Your level and rank','You earn XP for every activity you finish. XP makes your level go up, all the way to Level 100. Every 2 levels give a star, and 5 stars take you to the next rank! Tap to see all the ranks.'],
   ['.streak-stat','Your learning streak','Learn every day to make your flame grow. Tap here to see your calendar.'],
   ['.badge-stat','Your badges','Badges are prizes for learning. Tap to see the ones you earned.'],
   [['.pg-gate'],'Post-test result','You need 80% on a level’s post-test to open the next level. Tap Try again to answer the parts you missed. You can do it!'],
   ['.goal-card','Today’s goal','Finish 3 activities a day. The ring fills up as you go!'],
   [['.next-adventure .btn.primary','.next-adventure'],'Start learning','Tap this button to go to your next lesson. BULIG remembers where you stopped.'],
   [['.lb-mini-art'],'Your reading ladder','This is your reading ladder. You stand on the level you are on now, and every level you finish (or start above) gets a ribbon. Tap it to see your whole reading board.'],
   [['.pupil-tabbar .tab-play','.sidebar nav a[href="?page=lessons"]'],'All your lessons','Here you find all your levels and the road map of each level.'],
   [['.pupil-tabbar a[href="?page=calendar"]','.sidebar nav a[href="?page=calendar"]'],'Your calendar','See every day you learned.'],
   [['.pupil-tabbar a[href="?page=profile"]','.sidebar nav a[href="?page=profile"]'],'Your profile','Change your picture and see this week. Settings are there too.'],
   [['#schedule-wait'],'Next level date','You finished early! The next level opens on this date for your whole class. Practise while you wait.'],
   [['#month-recap'],'Your month','At the start of each month, see how much you did last month. Tap X to hide it.'],
   [['.notif-m','.notif-top'],'Your notifications','Tap the bell to see notes from your teacher, new badges and certificates. The yellow number shows how many are new.'],
   [['.mode-mobile','.mode-top'],'Light or dark','Tap the moon to make BULIG dark, easy on your eyes at night. Tap the sun to make it bright again.'],
   [['.mobile-signout button','.sidebar .signout'],'Signing out','Tap here when you are done. BULIG asks first, so you never leave by accident.'],
   [null,'Need help again?','Tap the yellow ? button on any page and I will show you how it works. Have fun learning!']],
  notifications:[
   ['.pageheading','Notifications','Everything new for you, newest first: notes from your teacher, badges and certificates.'],
   [['.nt-row'],'Open one','Tap a notification to go straight to it. A yellow dot means it is new.'],
   [['.nt-tabs'],'All or Unread','See every notification, or only the ones you have not read yet.'],
   [['.nt-acts'],'Read, unread or delete','Mark one as read or as unread again, or delete it.'],
   [['.nt-tools','.pageheading'],'All at once','Mark all as read clears the yellow number on the bell. Delete all read removes the ones you have already seen.']],
  lessons:[
   [['.pg-gate'],'Post-test result','You need 80% on the post-test to open the next level. Tap Try again to answer the parts you missed.'],
   ['.level-catalog .section-heading','Your levels','These are all the BULIG levels. Your teacher chose your starting level.'],
   [['a.off-pagelink[href="?page=board"]'],'My reading board','See all your levels as a ladder, with a ribbon for every level you finish or start above.'],
   [['.level-card.pf-current','.level-card.available'],'Your level','Your level is always at the top. Tap "Continue learning" to open your next lesson, or "See my learning path" for the road map.'],
   [['.level-fold>summary'],'Optional practice','Levels your teacher skipped are folded here. Tap to open them and practise whenever you like.'],
   [['.level-card.unavailable'],'Locked levels','A lock means you need to finish the level before it first. Tap a locked level to see what to do.'],
   offline?[['.level-card.pf-current .off-slot','.off-slot'],'Save for offline','No internet at home? Tap "Save for offline" while you have internet. The level is kept on this device so you can learn without internet.']:null,
   offline?[null,'Learning offline','When there is no internet, open a saved level and answer as usual. Your answers wait on this device and upload by themselves when the internet comes back.']:null],
  map:[
   [['.sk-note'],'Free practice','Your teacher chose a later starting level. Every lesson here is open, so start with any lesson you like.'],
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
   ['.lesson-outline','All activities','Open this to see every activity in this lesson and what you already finished.'],
   [['.hp-open'],'Need help?','Tap the hand to tell your teacher what is hard. Your teacher gets your message, in class or at home, and will help you as soon as they can.'],
   [['.sk-all'],'All activities','In a skipped level, tap All to pick any activity, or tap a dot to jump to it.']],
  'l1-start':[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   ['.l1-start h1','Your lesson','This is the lesson you will learn today.'],
   ['.l1-goals','Today you will…','These are the things you will learn. Tap the yellow speaker to hear them.'],
   ['.l1-steps','Three parts','First a few questions, then practice, then show what you learned.'],
   ['.l1-go','Start','Tap Let’s start! when you are ready.'],
   [['.hp-open'],'Need help?','Tap the hand to tell your teacher what is hard. Your teacher gets your message, in class or at home, and will help you as soon as they can.'],
   [['.sk-pick'],'Start anywhere','This level was skipped for you, so everything is open. Tap a part to start there, or open Pick any activity.']],
  l1:[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   [['.hp-open'],'Need help?','Tap the hand to tell your teacher what is hard. Your teacher gets your message, in class or at home, and will help you as soon as they can.'],
   [['.sk-all'],'All activities','In a skipped level, tap All to pick any activity, or tap a dot to jump to it.'],
   [['.l1-q .l1-spk','.l1-mission .l1-spk','.l1-listen .l1-spk','.l1-speed'],'Listen','Tap the yellow speaker to hear it again.'],
   [['.l1-mic'],'Say your answer','Tap the red microphone and say your answer. Tap it again to stop. Then listen to it, and tap Send.'],
   [['.l1-howto'],'Do it!','Listen, do the action, then tap I did it!'],
   [['.pk-solo'],'BULIG is your buddy','You do this one on your own. Follow the steps, then tap I did it!'],
   [['.pk-poems'],'Choose a poem','Tap a poem. BULIG reads one line, then you read the next. The words light up as they are read.'],
   [['.pk-wordgame'],'Word game','Tap New word. BULIG says a word, then you say a word friend.'],
   [['.ka-bar'],'Listen and read along','Tap play. BULIG reads the passage and each word lights up, so you can follow. Tap Slow to hear it slower.'],
   [['.l1-dchoice'],'Your drawing','Draw on paper and take a photo, or draw here in BULIG. Check it, then send it.'],
   offline?[null,'No internet?','If this level is saved for offline, you can still answer. Your answer is kept on this device and uploads by itself later.']:null],
  l2:[
   ['.activity-header','Your lesson','This shows the lesson name and how many activities you finished. Tap X to go back to your map.'],
   ['.l2-dir','What to do','These are the directions from your book. Tap the yellow speaker to hear them.'],
   [['.l2-cdots'],'Your cards','Each number is a card. Green cards are answered. Tap a number to jump to it.'],
   [['.l2-card:not([hidden]) .l2-keys'],'Letter tiles','Tap the letters to write your answer. The red arrow erases.'],
   [['.l2-card:not([hidden]) .l3-bgrid'],'Letter boxes','Tap the letters in order. Each one jumps onto the next line. Tap a letter on a line to take it back.'],
   [['.pk-solo'],'BULIG is your buddy','You do this one on your own. Follow the steps, then tap I did it!'],
   [['.pk-poems'],'Choose a poem','Tap a poem. BULIG reads one line, then you read the next. The words light up as they are read.'],
   [['.ka-bar'],'Listen and read along','Tap play. BULIG reads the story and each word lights up, so you can follow. Tap Slow to hear it slower.'],
   [['.l2-card:not([hidden]) .l2-chips'],'Tap a word','Tap the word or sound you choose. It turns green.'],
   [['.l2-card:not([hidden]) button.l2-pic'],'Tap a picture','Tap the picture you choose. It gets a green check.'],
   [['.l2-card:not([hidden]) .l2-nums'],'Count','Tap the number of sounds you hear.'],
   [['.l2-card:not([hidden]) .l1-mic'],'Say it','Tap the red microphone and say your answer. Tap again to stop.'],
   [['[data-l2-next]','#submit-answer'],'Next and Submit','Tap Next card to move on. On the last card, tap Submit.'],
   [['.hp-open'],'Need help?','Tap the hand to tell your teacher what is hard. Your teacher gets your message, in class or at home, and will help you as soon as they can.'],
   [['.sk-all'],'All activities','In a skipped level, tap All to pick any activity, or tap a dot to jump to it.']],
  'lesson-done':[
   ['.completion-card','Lesson finished!','Great job! Tap the button to save the lesson and go back to your map.'],
   [['.pg-gate'],'Your post-test score','This is your post-test score. 80% or more opens the next level. Below that, tap Try again to answer the missed parts.']],
  calendar:[
   ['.pf-cal-sum','Your streak','How many days in a row you learned, and your best streak ever.'],
   ['.pf-cal-grid','Your learning days','Days with a flame are days you learned. Try to learn a little every day!'],
   ['.pf-cal-mon','Other months','Use the arrows to look at other months.']],
  achievements:[
   ['.rl-road','My rank','Your rank goes from Beginner to Mythic. Every 2 levels give a star; fill 5 stars to reach the next rank. The highest level is 100.'],
   [['.rk-ladder'],'All the ranks','Beginner, Learner, Explorer … all the way to Mythic. The one that says YOU ARE HERE is your rank now.'],
   [['.rk-how'],'How to rank up','Finish activities to earn XP. If your teacher moved you to a higher level, you got XP for the levels you skipped.'],
   ['.pf-shelf','Your badge shelf','All the badges you can earn in BULIG.'],
   [['.pf-shelf-item.pf-got'],'Badges you earned','Shiny badges are yours! The date shows when you earned it.'],
   [['.pf-shelf-item.pf-locked'],'Badges to earn','Read what to do to unlock each badge.'],
   ['.cert-card','My certificates','Finish every lesson of a level and pass its post-test (80% or more) to get a certificate. Tap it to print.'],
   ['.badgegrid','Rewards','Collect XP to reach each reward.']],
  board:[
   ['.lb-sum','Your ribbons','How many ribbons you have, the level you are on, and how many levels are left to the Reading Castle.'],
   ['.lb-card','Your reading ladder','Each step is a level. You stand on the step you are on now. Finish a level and its ribbon is yours! Tap a step to open its lessons.'],
   [['.lb-legend'],'What the colours mean','Green is finished, yellow is where you are, and grey is locked.']],
  offline:[
   ['.off-sum','Your saved lessons','See how many levels are saved on this device, and how much space they use.'],
   [['.op-all'],'Download everything','One tap saves every level you can open. Use Wi-Fi for big downloads.'],
   [['.op-row'],'Each level','Download, update or remove one level. “Needs update” means BULIG has new things.']],
  settings:[
   ['.st-sfx','Sound effects','Turn the little sounds on or off on this device.'],
   [['.st-seg'],'How BULIG looks','Choose Light, Dark, or Auto. Auto changes by itself when your device goes dark at night.'],
   ['.st-glass','Glass look','Menus and buttons look like clear glass. Turn it off if this phone feels slow.'],
   [['a.st-row[href="?page=offline"]'],'Offline lessons','Save lessons on this device so they open even without internet.'],
   ['.st-pw','Your password','Tap here when you want to change your password.'],
   ['.st-about','About BULIG','See who made BULIG.']],
  profile:pupil?[
   ['.me-hero','Your profile','Your picture, your name and your class.'],
   [['.me-week .weekdays'],'This week','The days you learned this week have a check.'],
   ['.me-pic','Your picture','Choose a character, or upload your own photo.'],
   [['.me-setbtn','.sidebar nav a[href="?page=settings"]'],'Settings','Sound, colours, offline lessons, your password and About BULIG are in Settings.']]:[
   ['.char-pick','Your teacher character','Choose the character that greets you after you sign in. Jarvis the Robot guides your tours.'],
   [['.card form.stack'],'Your photo','Upload your own photo for your account.'],
   [['.formgrid input[name="current_password"]'],'Password','Change your password here whenever you need to.'],
   ['.mode-card','How BULIG looks','Choose Light, Dark, or Auto. Auto follows your device setting.']]
 };
 const T={
  credits:[
   ['.cr-hero','Credits','BULIG is made from the official DepEd modules of the Schools Division of Bukidnon.'],
   [['.cr-cols .cr-card'],'The BULIG module','The writers, designers and checkers who made the Reading Toolkit.'],
   [['.cr-cols .cr-card:nth-child(2)'],'Management team','The Division leaders who guided the module.'],
   [['.cr-web'],'Web app','The people and tools that built this web app.']],
  dashboard:[
   ['.hm-head','Teacher Home','Things that need you come first. Everything else is one tap away.'],
   ['#tf-todo','Needs you today','Pupils who need a check-in, reading checks to look at and pupils who asked for help. Tap a card to go straight there.'],
   ['.hm-auto','BULIG checks every answer','Pupils see their score and the right answers as soon as they finish. Nothing waits for you. To see one pupil’s answers, change a score or write a note, open the pupil › Answers and scores.'],
   ['#tf-help','Asked for help','When a pupil taps “I need help” in a lesson, at school or at home, it shows here: who, what is hard and where. Tap See pupil, or Mark as helped when you are done.'],
   ['.hm-grid','What do you want to do?','The jobs teachers do most: add pupils, and print tickets, report cards and certificates.'],
   ['.class-tabs','Grades and sections','Choose a grade or section to see only those pupils in My class below.'],
   [['.tf-list','.class-tabs'],'My pupils','Each pupil with their level and last learning day. “Check in” means no learning in 7 days. Tap a pupil to open their page.'],
   ['.nv-find','Search','Type what you need, for example “print” or “password”, then tap the page. Press Enter to open the first one.'],
   ['.sidebar .nv-g','The menu in groups','MY CLASS: your pupils, adding pupils, the reading check and sections. TEACHING: Class Demo and Class results. PRINT: everything you can print.'],
   ['.sidebar nav a[href="?page=rcheck"]','Reading check','Pupils with no starting level take a short reading check the first time they sign in. BULIG places them, and you can change any level.'],
   ['.sidebar nav a[href="?page=print"]','Print centre','Everything you can print, in one place: sign-in tickets, report cards, progress reports, certificates, the class sheet and reading check results.'],
   ['.mobile-menu-toggle','Menu','Tap here to open the menu. It has a Search box at the top. Each page has its own short tour.'],
   [['.notif-top','.notif-m'],'Notifications','Tap the bell for answers waiting for your feedback, pupils who finished a level, and pupils who need a check-in. The yellow number shows what is new.'],
   [['.mode-top','.mode-mobile'],'Light or dark','Switch BULIG between light and dark colours. Choose Auto in My profile to follow your device.'],
   [['.sidebar .signout','.mobile-menu-toggle'],'Signing out','Sign out at the bottom of the menu when you are done. On a shared computer, untick "Keep me saved" when BULIG asks.'],
   [null,'You are all set!','Every page has its own short tour. Press the ? button anytime to see it. Lost? Open “See what moved” from the note on Home.']],
  notifications:[
   ['.pageheading','Notifications','Work that needs you now is at the top. Notes about finished levels and new accounts come after.'],
   [['.nt-row'],'Open one','Tap one to go straight to the right page. Items under Needs you now go away by themselves once the work is done.'],
   [['.nt-tabs'],'All or Unread','See every notification, or only the ones you have not read yet.'],
   [['.nt-acts'],'Read, unread or delete','Mark one as read or as unread again, or delete it.'],
   [['.nt-tools','.pageheading'],'All at once','Mark all as read clears the yellow number on the bell. Delete all read removes the ones you have already seen.']],
  class_demo:[
   ['.pageheading','Class Demo','Present lessons to the whole class on a TV or projector. Nothing is saved for pupils here.'],
   [['.demo-catalog .level-card.available'],'Choose a level','Tap "Start class demo". Levels 4 to 7 ask you to choose a grade first.'],
   [['.dm-off'],'Class Demo without internet','Save the levels you teach on this device. In class they open even with no internet. "Mark as done" waits on the device and is sent when you are back online.']],
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
   ['.pupil-tabs','Add pupils','My pupils has three tabs: Pupil list, Add pupils (this one) and Class progress.'],
   ['.ap-choice','One pupil or a whole class','Add one pupil with a short form, or a whole class at once from an Excel list. The DepEd SF1 list works.'],
   [['form.formgrid:not(.import-form)'],'Add one pupil','Type the first, middle and last name (and Jr., Sr. … if any), then the section, sex and starting level. BULIG makes the Pupil ID and an easy starter password for you.'],
   [['form.formgrid:not(.import-form) select[name="level_id"]','form.formgrid:not(.import-form)'],'Let BULIG find the level','Leave the starting level on “Let BULIG find it”. The pupil takes the reading check at first sign-in and BULIG places them. You can change it later.'],
   [['form.formgrid:not(.import-form) .btn.primary','form.formgrid:not(.import-form)'],'Their ticket, right away','After you save, a window shows the new pupil’s sign-in ticket with the QR code. Print it there, or add the next pupil.'],
   [['#import','.import-card'],'Add a whole class','Type the list: one row per pupil, Enter goes to the next. Choose one section for everyone or a section on each row, and change any pupil’s starting level on their row. Or choose Upload a file to use an Excel list. Nothing is saved until every row is right.']],
  cards:[
   ['.lc-upgrade','Starter passwords','Pupils still on the old password 12345678 can each get their own easy starter password here. Print their new tickets right away.'],
   ['.lc-filter','Choose a section','Print tickets for one section or for all your pupils.'],
   [['.lc-opt'],'What to show','Turn the QR code or the starter password on or off. BULIG remembers your choice.'],
   ['.lc-pick','Choose and size','Untick the tickets you do not need, and choose Small (21 per page, 3 columns), Medium (10) or Large (8).'],
   ['[data-lc-print]','Print','Print on A4 paper and cut along the dashed lines.'],
   [['.tk'],'One ticket per pupil','One scan of the QR code signs the pupil in. The starter password shows until the pupil makes their own.'],
   [['.tk-new button'],'New ticket','Lost a ticket? Make a new one: the old QR code stops working and the pupil gets a new starter password.']],
  manage:[
   ['.pupil-tabs','My pupils','Three tabs: Pupil list (this one), Add pupils, and Class progress.'],
   ['.mp-bar','Find a pupil','Search by name or ID, or choose a section.'],
   ['.mp-tbl','Every pupil in one list','Grouped by section, with each pupil’s starting level, reading check and last learning day. Tap a name to open the pupil’s page.'],
   [['.rk-inl'],'Level and rank','Each pupil’s reader level (1 to 100), rank emblem and stars. They go up as the pupil finishes activities.'],
   ['.mp-bulk','Many pupils at once','Tick pupils, then set one starting level for all of them, move them to another section, or print their sign-in tickets.']],
  progress:[
   ['.pupil-tabs','Progress tab','Every pupil and every lesson of one level, on one screen.'],
   ['.pg-filter','Choose a section and level','The grid changes as soon as you pick.'],
   [['.pg-table'],'The grid','Green is done, light green is started, yellow means no work for 7+ days. Tap a name to open that pupil.'],
   [['a[href*="page=certificates"]'],'Certificates','Print certificates for every pupil who finished this level.']],
  sections:[
   ['.section-add','Add a section','Choose the grade, then type the section name. BULIG shows how it will look, and warns you if the section is already taken.'],
   [['.sx-card','.section-add'],'One card for each section','Add pupils straight into this section, see its pupils, print its report cards or rename it. Remove works only for an empty section.'],
   [['.sx-act a.sx-go','.section-add'],'Add pupils','Opens Add pupils with this section already chosen.']],
  review:[
   [['.hm-head','.pageheading'],'Pupils’ answers','BULIG checks every answer. Open a pupil › Answers and scores to listen, look, change a score or write a note.']],
  report_card:[
   [['.rc-btns .btn.primary'],'Print','Print the cards or save them as a PDF. Each pupil gets one A4 page.'],
   [['.rc-page'],'The report card','Name, LRN, grade and section, school and school year come from BULIG. The ladder and the remarks show each level’s result and date.'],
   [['.rc-tip'],'Logos','Your administrator can upload the Division and school logos in Settings, School info.']],
  guide:[
   [['form.card'],'Choose a lesson','The full text of each lesson plan. In Class Demo, tap "Lesson plan" for a quick view.'],
   [['.source-pages'],'Original pages','Open the matching pages of the official module.'],
   [['a.btn.secondary[href*="download_source"]'],'Download the module','Get the original module file for printing.']],
  'pupil-detail':[
   ['.hm-head','One pupil','The pupil’s name, ID, level and progress. The buttons print this pupil’s report card, progress report and sign-in ticket.'],
   ['.pp-tabs','Everything about this pupil','Learning path, Answers and scores (every answer with BULIG’s score; change a score or write a note there), Reading check, Badges and Account (name, section, password and a new ticket).'],
   ['.mp-start','Starting level','Change where this pupil begins. Earlier levels become optional practice.'],
   ['#learning-path','Learning path','Every level and lesson with its status. Open a level to mark lessons done in class.'],
   [['.cd-actions'],'Mark as done in class','Tick lessons or activities you did together, then save. Use "Undo" if you made a mistake.'],
   [['.pg-teacher'],'Post-tests','The post-test score of each level. At 80% or more the next level opens. Below that, the pupil answers the missed activities again.']],
  profile:P.profile.concat([['.rt-pcard','Rate BULIG','Five quick questions with stars. Your answers help the Division improve BULIG.']]),
  rate:[
   ['.rt-rows','Five quick questions','Tap 1 to 5 stars for each one. Your overall rating is the average.'],
   ['.rt-lab','Tell us more','Optional: write what we should improve.'],
   ['.rt-btn','Send','Send your evaluation. You can rate BULIG again any time.']],
  qcheck:[
   ['.hm-head','Class results','BULIG checks every answer. Here you see which questions your class finds hard, so you can read them again together.'],
   ['.qc-filter','Choose what to see','Pick a level, a grade and a lesson.'],
   [['.qc-act'],'Each activity','How many pupils got each question right, and the wrong answer chosen most. Re-teach the ones in red.'],
   [['#glance'],'Class at a glance','Class activity for the last 7 days, the class reading tree and pupil milestones.'],
   ['.sidebar nav a[href="?page=manage"]','One pupil’s answers','Open My pupils, then a pupil › Answers and scores to listen, look, change a score or write a note.']],
  rcheck:[
   ['.hm-head','Reading check','Pupils with no starting level take this check the first time they sign in. BULIG uses the BULIG Assessment and Grouping guide and the module pre-tests.'],
   ['.rk-sum','At a glance','How many pupils BULIG placed, how many you changed, how many need you to listen, and who has not finished.'],
   ['.rk-tbl','Every pupil','The parts each pupil took, the starting level and the status. “Please listen” means BULIG could not hear the pupil, or Talk and listen needs your rating. Tap a name to open it.'],
   [['.hm-head .buttonrow'],'How BULIG decides, and printing','Read the rules BULIG follows, or print the results for your records.']],
  rcheck_pupil:[
   ['.hm-head','One pupil’s reading check','The starting level BULIG chose and why.'],
   ['.rk-whybox','Why this level','Which parts the pupil passed and where the check stopped.'],
   [['.rk-levelform'],'Keep or change the level','Choose another starting level and write what you noticed, or tap “I checked it” to keep BULIG’s level. Work already done is kept.'],
   [['.rk-cols section.card:nth-child(2)'],'Official result','Type the pupil’s official CRLA or Phil-IRI result. BULIG compares it with its own level.'],
   [['.rk-part audio','.rk-part'],'Listen to the pupil','Every part shows what the pupil answered. Play the recordings to hear the pupil read.'],
   [['.rk-rate'],'Rate Talk and listen','Rate the pupil’s spoken answers with the Level 1 module rubric. Good (9 of 12) passes.']],
  rcheck_rules:[
   ['.hm-head','How BULIG decides','The rules BULIG follows to choose a starting level, and where each rule comes from.'],
   [['.rk-tbl'],'The table','Each part, what it checks, the score needed to pass, and the level a pupil starts at when it is not passed.'],
   [['.rk-src'],'Sources','The BULIG Assessment and Grouping guide, the module pre-tests, and Phil-IRI for reading a story.']],
  print:[
   ['.hm-head','Print centre','Everything you can print, in one place. Each one opens a preview first.'],
   [['.pc-card'],'Choose what and for whom','Pick a section or a pupil on a card, then tap its print button.'],
   [['#pc-reportcards'],'Report cards','One A4 page for each pupil, with the reading ladder. Choose a whole section or one pupil.'],
   [['#pc-rcheck'],'Reading check results','Each pupil’s starting level and the parts of the check, with the official CRLA or Phil-IRI result.'],
   ['.pc-tip','Save as PDF','In the print window, choose “Save as PDF” to keep a copy instead of printing.']],
  moved:[
   ['.hm-head','Where things are now','The menu has a new order. This page lists every old place and where it is now.'],
   [['.wm-tbl'],'Old place, new place','Find the old name on the left. The right side tells you where it is now. Tap it to go there.']]
 };
 const A={
  credits:[
   ['.cr-hero','Credits','BULIG is made from the official DepEd modules of the Schools Division of Bukidnon.'],
   [['.cr-cols .cr-card'],'The BULIG module','The writers, designers and checkers who made the Reading Toolkit.'],
   [['.cr-cols .cr-card:nth-child(2)'],'Management team','The Division leaders who guided the module.'],
   [['.cr-web'],'Web app','The people and tools that built this web app.']],
  dashboard:[
   [['.hm-head','.pageheading'],'Admin Home','What needs you first, then the jobs you do most.'],
   [['.hm-todo','.hm-checkup'],'Needs you','Things to follow up, like locked accounts or teachers who have not signed in.'],
   ['.hm-grid','What do you want to do?','Add teachers, print Teacher Passes, reset a password, move pupils, download a backup, print the school report, post an announcement, edit a lesson.'],
   [['.hm-g3'],'The school at a glance','Pupils at each level, teachers and learning this week.'],
   ['.nv-find','Search','Type what you need, for example “backup” or “passes”, then tap the page.'],
   ['.sidebar .nv-g','The menu in groups','PEOPLE: teachers and accounts. LESSONS: the lesson studio. REPORTS & PRINT: reports, app ratings, passes and backups. SYSTEM: activity log, health and settings.'],
   ['.sidebar nav a[href="?page=print"]','Print & download','Teacher Passes, the school report, backups, the activity log and teacher lists, in one place.'],
   ['.mobile-menu-toggle','Menu','Tap here to open the menu. It has a Search box at the top. Each page has its own short tour.'],
   [['.notif-top','.notif-m'],'Notifications','Tap the bell for System health alerts and new pupil accounts. The yellow number shows what is new.'],
   [['.mode-top','.mode-mobile'],'Light or dark','Switch BULIG between light and dark colours. Choose Auto in My profile to follow your device.'],
   [['.sidebar .signout','.mobile-menu-toggle'],'Signing out','Sign out at the bottom of the menu when you are done. Admin accounts are never saved on a device.'],
   [null,'You are all set!','Every page has its own short tour. Press the ? button anytime to see it.']],
  notifications:[
   ['.pageheading','Notifications','System health alerts and new pupil accounts, newest first.'],
   [['.nt-row'],'Open one','Tap one to open the page where you can fix it.'],
   [['.nt-tabs'],'All or Unread','See every notification, or only the ones you have not read yet.'],
   [['.nt-acts'],'Read, unread or delete','Mark one as read or as unread again, or delete it.'],
   [['.nt-tools','.pageheading'],'All at once','Mark all as read clears the yellow number on the bell. Delete all read removes the ones you have already seen.']],
  accounts:[
   ['.ws-btns','Add teachers','Opens Add teachers: one teacher at a time, or the whole team from an Excel list.'],
   ['.ws-filters','Find a teacher','Search by name, or filter by grade and status.'],
   ['table.ws-table','Your teaching team','Sections, pupils and last sign-in for each teacher. Edit names, reset passwords or turn an account off here.'],
   ['#move','Move pupils','Move pupils to another teacher. They keep their progress, XP and badges.']],
  reports:[
   ['.ws-filters','Choose what to see','Pick the period, grade and section, then choose Show.'],
   ['.ws-kpis','Key numbers','Pupils, average score, finished lessons and pupils who need a check-in.'],
   ['table.ws-table','Progress by section','Each grade and section with its teacher, pupils and activities.'],
   ['.ws-btns','Download or print','Download the report for Excel, or print it and save it as a PDF.']],
  activity_log:[
   ['nav.ws-tabs','Filter by kind','See all events, or only sign-ins, wrong passwords and PINs, account changes or content changes.'],
   ['.ws-filters','Search','Search for a name or ID and pick the dates.'],
   ['table.ws-table','What happened','When, who, what happened and the details.'],
   ['.ws-btns','Download','Download the log as a CSV file.']],
  teacher_cards:[
   [['.lc-tools'],'Print passes','Choose who to print, then press Print. Each pass has the Teacher ID, the starter password and a QR code.'],
   [['.tc-pw'],'Passwords','Who still uses a starter password. A teacher makes their own password the first time they sign in.']],
  teacher_import:[
   [['.import-card'],'Add many teachers','Download the template, fill it in, upload it and check every row before the accounts are made.']],
  checkup:[
   ['.ws-kpis','At a glance','Red needs action now. Yellow is worth a look. Green is all good.'],
   [['.ck-card'],'Things to fix','Each problem has its fix next to it: unlock, compare, place in a section or tell the teacher.'],
   ['.ws-btns','Check again','Run the check again after you fix something.']],
  health:[
   [['.ws-g2 section.card'],'Checks','Green means fine. Red shows what needs attention, with the fix next to it.'],
   ['.ws-btns','Run all checks','Check everything again after you upload an update.'],
   ['#backup','Backup','Download a copy of the database. Keep it somewhere safe.']],
  settings:[
   [['.sch-card'],'Level schedule','Choose the date each level opens. Pupils who finish early wait for that date, so the class moves together. Fill the dates for me sets one level per week.'],
   ['nav.ws-tabs','Settings sections','Security, school details, announcements, badges and rewards, the school year and account IDs.'],
   [['nav.ws-tabs a[href*="tab=ids"]'],'Account IDs','Set your school code (for example PCES). New pupils get IDs like PCES260001 and teachers PCEST0001. Old IDs can be switched here after BULIG saves a backup.'],
   [['#switch-ids'],'Switch to the new IDs','See every old ID next to its new one. BULIG saves a backup first; passwords and progress stay the same, and old IDs still sign in.'],
   [['.rc-logoset'],'Report card logos','Upload the Division and school logos. They print at the top of every Reading Progress Report Card.'],
   ['#admin-pin','Admin PIN','Change the 4-digit PIN you enter after your password.'],
   [['.ws-g2 section.card:nth-child(2)'],'Sign-in rules','How BULIG protects accounts from wrong passwords.']],
  ratings:[
   ['.hm-head','App ratings','How pupils and teachers rate BULIG. Each person counts once: their latest rating.'],
   ['.rt-per','Choose the period','The last 3 months, 12 months or all time.'],
   [['.rt-box'],'Pupils','The average stars, and how many pupils gave 5, 4, 3, 2 or 1 star.'],
   [['.rt-box:nth-child(2)'],'Teachers','The average, each of the five questions, and what teachers said.']],
  profile:[
   [['.char-pick'],'Your character','Choose a female or male admin character. It greets you after you sign in. Jarvis the Robot guides your tours.'],
    ['.mode-card','How BULIG looks','Choose Light, Dark or Auto.'],
   [['.card form.stack'],'Your photo','Upload your own photo for your account.'],
   [['.formgrid input[name="current_password"]'],'Password','Change your password here whenever you need to.']],
  add_teachers:[
   ['.ap-choice','One teacher or many','Add one teacher with a short form, or the whole team at once from an Excel list.'],
   [['.ap-one form','form.formgrid'],'Add one teacher','Enter the first, middle and last name (and extension, if any) and the sex. BULIG makes the Teacher ID and a starter password. The teacher makes their own password at first sign-in.'],
   [['.import-card'],'Add many teachers','Download the template, fill it in, upload it and check every row before the accounts are made.']],
  print:[
   [['.hm-head','.pageheading'],'Print & download','Teacher Passes, the school report, backups, the activity log and teacher lists, in one place.'],
   [['.pc-card'],'Choose and print','Each card opens a preview first, or downloads the file.'],
   [['#pc-backup'],'Backup','Download a copy of the database. Keep it somewhere safe.']],
  moved:[
   ['.hm-head','Where things are now','The menu has a new order. This page lists every old place and where it is now.'],
   [['.wm-tbl'],'Old place, new place','Find the old name on the left. The right side tells you where it is now.']]
 };
 const raw=(pupil?P:me.role==='admin'?A:T)[key];if(!raw)return;
 const steps=raw.filter(Boolean);
 const seenKey='bulig-tour-'+me.id+'-dashboard';
 /* The automatic offer appears once per account, on the dashboard only; the server remembers it. */
 function markDone(){if(key!=='dashboard'||me.seen)return;me.seen=true;store(seenKey,'1');
  try{const fd=new FormData();fd.set('csrf',(document.querySelector('meta[name="csrf"]')||{}).content||'');fd.set('action','tour_seen');fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'},keepalive:true}).catch(()=>{});}catch(e){}}

 /* ---------- UI ---------- */
 /* Jarvis the Robot guides every tour (pupils, teachers and admins, v171). He points, has a bright idea, gives a thumbs up,
    and cheers on the last step. He talks in a jolly robot voice, with a little beep-boop first. */
 const G=me.guide||{},pose=p=>G[p]||me.char;
 const charImg=(p)=>{const i=document.createElement('img');i.src=pose(p||'point');i.alt='';i.className='tour-george';i.width=62;i.height=84;return i;};
 function metGeorge(){if(me.met)return;me.met=true;try{const fd=new FormData();fd.set('csrf',(document.querySelector('meta[name="csrf"]')||{}).content||'');fd.set('action','jarvis_met');fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'},keepalive:true}).catch(()=>{});}catch(e){}}
 let layer=null,idx=-1,cur=[],raf=0;
 /* Voice narration: each step is read aloud in a calm, natural voice (the pupil's chosen voice when there is one). */
 const synth=window.speechSynthesis||null;let talkOn=true;try{talkOn=localStorage.getItem('bulig-tour-voice')!=='off';}catch(e){}
 /* The most natural-sounding English voice on this device. Robotic voices (eSpeak, old Microsoft David/Mark) are used only as a last resort. */
 function voiceScore(v){const n=v.name;let sc=0;
  if(/natural|neural|premium|enhanced|wavenet|studio/i.test(n))sc+=100;
  if(/aria|jenny|ava|emma|michelle|libby|sonia|ana\b|clara|natasha/i.test(n))sc+=40;
  if(/google (us|uk) english/i.test(n))sc+=60;if(/google/i.test(n))sc+=10;
  if(/samantha|karen|moira|tessa|serena|fiona|allison|susan|zira|hazel/i.test(n))sc+=30;
  if(/female/i.test(n))sc+=15;
  if(/en[-_]ph/i.test(v.lang))sc+=8;else if(/en[-_](us|gb|au)/i.test(v.lang))sc+=5;
  if(/espeak|david|mark|george|fred|albert|zarvox|whisper|bad news|bells|boing|bubbles|cellos|jester|organ|trinoids|robot|superstar|wobble|junior|ralph|kathy|bahh/i.test(n))sc-=200;
  return sc;}
 function niceVoice(){if(!synth)return null;let vs=synth.getVoices().filter(v=>/^en/i.test(v.lang));
  /* Offline: only voices stored on the device can speak (online voices such as Google or Edge Natural need internet). */
  if(!navigator.onLine){const local=vs.filter(v=>v.localService);if(local.length)vs=local;}
  if(!vs.length)return null;let pref=null;try{pref=localStorage.getItem('bulig-tour-voice-name')||localStorage.getItem('bulig-voice');}catch(e){}
  const chosen=vs.find(v=>v.voiceURI===pref);if(chosen)return chosen;
  return vs.slice().sort((x,y)=>voiceScore(y)-voiceScore(x))[0];}
 /* His voice: a clear male voice, pitched up to sound like a happy robot. */
 function robotVoice(){if(!synth)return null;let vs=synth.getVoices().filter(v=>/^en/i.test(v.lang)&&!/espeak|zarvox|whisper|bad news|bells|boing|bubbles|cellos|jester|organ|trinoids|superstar|wobble|bahh|albert/i.test(v.name));
  if(!navigator.onLine){const local=vs.filter(v=>v.localService);if(local.length)vs=local;}if(!vs.length)return null;
  const sc=v=>(/guy|davis|ryan|daniel|christopher|andrew|brian|eric|uk english male|\bmale\b|alex|aaron|tom\b|james|william|liam/i.test(v.name)?50:0)+(/natural|neural|online|google/i.test(v.name)?10:0)-(/female|zira|aria|jenny|samantha|karen|moira|tessa|serena|fiona|susan|hazel|libby|sonia/i.test(v.name)?40:0);
  return vs.slice().sort((x,y)=>sc(y)-sc(x))[0];}
 let actx=null;function beep(){try{const C=window.AudioContext||window.webkitAudioContext;if(!C)return;actx=actx||new C();if(actx.state==='suspended')actx.resume();const t=actx.currentTime;
  [[740,0],[988,.09],[1319,.18]].forEach(([f,d])=>{const o=actx.createOscillator(),g=actx.createGain();o.type='square';o.frequency.value=f;g.gain.setValueAtTime(.0001,t+d);g.gain.exponentialRampToValueAtTime(.045,t+d+.012);g.gain.exponentialRampToValueAtTime(.0001,t+d+.08);o.connect(g);g.connect(actx.destination);o.start(t+d);o.stop(t+d+.09);});}catch(e){}}
 function hush(){if(synth)synth.cancel();if(layer)layer.classList.remove('tour-talking');}
 function say(title,text){hush();if(!talkOn||!synth||!layer)return;const t=(title+'. '+text).replace(/\s+/g,' ').trim();
  const run=()=>{if(!layer)return;beep();const u=new SpeechSynthesisUtterance(t);const v=robotVoice()||niceVoice();if(v){u.voice=v;u.lang=v.lang;}else u.lang='en-US';u.rate=1.02;u.pitch=1.6;
   u.onstart=()=>layer&&layer.classList.add('tour-talking');u.onend=u.onerror=()=>layer&&layer.classList.remove('tour-talking');synth.speak(u);};
  if(synth.getVoices().length)setTimeout(run,120);else{let done=false;const go2=()=>{if(done)return;done=true;run();};synth.addEventListener('voiceschanged',go2,{once:true});setTimeout(go2,700);}}
 function voiceBtn(){const b=document.createElement('button');b.type='button';b.className='tour-voice';const paint=()=>{b.classList.toggle('off',!talkOn);b.setAttribute('aria-pressed',talkOn?'true':'false');b.setAttribute('aria-label',talkOn?'Voice on. Tap to turn it off':'Voice off. Tap to turn it on');b.title=talkOn?'Voice on':'Voice off';
   b.innerHTML='<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 4-5 4H3v8h3l5 4z"/>'+(talkOn?'<path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>':'<path d="m16 9 6 6m0-6-6 6"/>')+'</svg>';};
  paint();b.onclick=()=>{talkOn=!talkOn;try{localStorage.setItem('bulig-tour-voice',talkOn?'on':'off');}catch(e){}paint();if(talkOn&&cur[idx])say(cur[idx][1],cur[idx][2]);else hush();};return b;}
 function close(markSeen){if(markSeen)markDone();hush();if(layer){layer.remove();layer=null;}document.removeEventListener('keydown',keys,true);removeEventListener('resize',place);removeEventListener('scroll',place,true);document.body.classList.remove('tour-on');}
 function keys(e){if(!layer)return;if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close(true);}else if(e.key==='ArrowRight'){e.preventDefault();e.stopPropagation();go(idx+1);}else if(e.key==='ArrowLeft'){e.preventDefault();e.stopPropagation();if(idx>0)go(idx-1);}}
 function start(){close(false);cur=steps.filter(s=>!s[0]||find(s[0]));if(!cur.length)return;
  layer=document.createElement('div');layer.className='tour-layer';layer.setAttribute('role','dialog');layer.setAttribute('aria-modal','true');layer.setAttribute('aria-label','Guided tour');
  layer.innerHTML='<div class="tour-block"></div><div class="tour-spot" aria-hidden="true"></div><div class="tour-bub"><div class="tour-bub-in"><div class="tour-txt"><h3></h3><p></p></div></div><div class="tour-nav"><span class="tour-dots" aria-hidden="true"></span><button type="button" class="tour-skip">Skip</button><button type="button" class="tour-back">Back</button><button type="button" class="tour-next">Next</button></div></div>';
  layer.querySelector('.tour-bub-in').prepend(charImg('point'));layer.querySelector('.tour-nav').prepend(voiceBtn());
  layer.querySelector('.tour-skip').onclick=()=>close(true);layer.querySelector('.tour-back').onclick=()=>{if(idx>0)go(idx-1);};layer.querySelector('.tour-next').onclick=()=>go(idx+1);
  document.body.append(layer);document.body.classList.add('tour-on');document.addEventListener('keydown',keys,true);addEventListener('resize',place);addEventListener('scroll',place,true);go(0);}
 function go(n){if(!layer)return;if(n>=cur.length){close(true);return;}idx=n;const [sel,title,text]=cur[n];
  layer.querySelector('h3').textContent=title;layer.querySelector('.tour-txt p').textContent=text;say(title,text);
  const gi=layer.querySelector('.tour-george');if(gi)gi.src=pose(n===cur.length-1&&n>0?'cheer':['point','map','thumbs'][n%3]);
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
 function offer(){if(document.querySelector('.tour-offer'))return;if(document.querySelector('.hm-moved'))return;   // one note at a time: the menu note first
if(window.buligDlPending){document.addEventListener('bulig:dl-done',()=>setTimeout(offer,600),{once:true});return;}if(document.querySelector('[data-save-offer]')&&!window.buligSaveDone){document.addEventListener('bulig:save-done',()=>setTimeout(offer,500),{once:true});return;}const big=key==='dashboard';
  const o=document.createElement('div');o.className='tour-offer'+(big?' tour-offer-big':'');o.setAttribute('role','dialog');o.setAttribute('aria-label','Guided tour');
  o.innerHTML='<div class="tour-offer-card"><div class="tour-offer-txt"><b></b><span></span></div><div class="tour-offer-btns"><button type="button" class="btn primary tour-yes"></button><button type="button" class="tour-no">Skip for now</button></div></div>';
  o.querySelector('.tour-offer-card').prepend(charImg('hello'));if(big)o.classList.add('tour-offer-george');
  const hi=pupil?'Hi, '+me.name+'!':me.role==='admin'?'Hello, '+me.name+'!':'Hello, Teacher '+me.name+'!';
  o.querySelector('b').textContent=big?(window.buligFriendsMet?'Ready, '+me.name+'?':hi+' I am Jarvis the Robot.'):(pupil?'New here?':'New page');
  o.querySelector('.tour-offer-txt span').textContent=big?(pupil?(window.buligFriendsMet?'Now let me show you around BULIG. It only takes a minute!':'Beep boop! I am your tour guide. Do you want me to show you around BULIG?'):'I am BULIG’s guide. Would you like me to show you around your '+(me.role==='admin'?'admin area':'workspace')+'?'):'Jarvis can show you how this page works.';
  o.querySelector('.tour-yes').textContent=big?'Yes, show me!':'Show me';
  o.querySelector('.tour-yes').onclick=()=>{o.remove();markDone();if(big)metGeorge();start();};
  o.querySelector('.tour-no').onclick=()=>{o.remove();markDone();if(big)metGeorge();toast(pupil?'Okay! Tap the yellow ? anytime to see the tour.':'You can open the tour anytime with the ? button.');};
  document.body.append(o);setTimeout(()=>o.querySelector('.tour-yes').focus({preventScroll:true}),80);}
 function toast(t){document.querySelectorAll('.tour-toast').forEach(x=>x.remove());const d=document.createElement('div');d.className='tour-toast';d.setAttribute('role','status');d.textContent=t;document.body.append(d);
  /* Sit right next to the ? button, with a little tail pointing at it. */
  const r=help.getBoundingClientRect(),w=Math.min(280,innerWidth-24),left=r.left+r.width/2<innerWidth/2;d.style.width=w+'px';
  d.style.left=(left?Math.max(12,r.left):Math.min(innerWidth-w-12,r.right-w+4))+'px';d.style.bottom=(innerHeight-r.top+14)+'px';d.classList.add(left?'tail-left':'tail-right');
  help.classList.add('tour-help-ping');setTimeout(()=>help.classList.remove('tour-help-ping'),3600);setTimeout(()=>d.classList.add('out'),4200);setTimeout(()=>d.remove(),4700);}

 /* The ? button on every page that has a tour. */
 const help=document.createElement('button');help.type='button';help.className='tour-help'+(pupil?' tour-help-pupil':'')+(key==='demo-slide'?' tour-help-demo':'')+(key==='activity'||key==='lesson-done'?' tour-help-activity':'');help.setAttribute('aria-label','Show me how this page works');help.title='Show me how this page works';if(me.helpface){help.textContent='';const f=document.createElement('img');f.src=me.helpface;f.alt='';f.className='tour-help-face';const q=document.createElement('b');q.className='tour-help-q';q.textContent='?';help.append(f,q);help.classList.add('tour-help-jarvis');};if(!me.helpface)help.textContent='?';
 help.addEventListener('click',()=>{document.querySelector('.tour-offer')?.remove();start();});document.body.append(help);
 /* On a phone the ? sits in the top bar (beside the hand in a lesson, beside the bell elsewhere), never over an answer or a button. */
 const dock=()=>{help.classList.remove('tour-help-dock','tour-help-dock-g');['top','left','right'].forEach(k=>help.style.removeProperty(k));const at=(k,v)=>help.style.setProperty(k,v+'px','important');
  if(!matchMedia('(max-width:740px)').matches||key==='demo-slide')return;
  const hand=document.querySelector('.hp-open'),bell=document.querySelector('.notif-m');let r;
  if(hand&&(r=hand.getBoundingClientRect()).width){help.classList.add('tour-help-dock','tour-help-dock-g');at('top',Math.round(r.top+scrollY+(r.height-42)/2));at('right',Math.round(document.documentElement.clientWidth-r.left+10));}
  else if(bell&&(r=bell.getBoundingClientRect()).width){help.classList.add('tour-help-dock');at('top',Math.round(r.top+(r.height-42)/2));const menu=document.querySelector('.mobile-menu-toggle'),m=menu&&menu.getBoundingClientRect();if(r.left>=innerWidth/2&&m&&m.width)r=m;at('left',Math.round(r.right+10));}};
 dock();addEventListener('resize',dock);addEventListener('load',dock);

 /* Someone who already answered the offer on this device (older BULIG kept it per page) is told to the server, not asked again. */
 const answeredHere=()=>{try{for(let i=0;i<localStorage.length;i++)if((localStorage.key(i)||'').indexOf('bulig-tour-'+me.id+'-')===0)return true;}catch(e){}return false;};
 /* Already took the tour before Jarvis arrived: he says hello once (on the dashboard). */
 function meet(){if(document.querySelector('.tour-offer')||document.querySelector('.hm-moved'))return;
  const o=document.createElement('div');o.className='tour-offer tour-offer-big tour-offer-george tour-meet';o.setAttribute('role','dialog');o.setAttribute('aria-label','Meet Jarvis the Robot');
  o.innerHTML='<div class="tour-offer-card"><div class="tour-offer-txt"><small class="tour-new">NEW FRIEND</small><b></b><span></span></div><div class="tour-offer-btns"><button type="button" class="btn primary tour-yes"></button><button type="button" class="tour-no">Show me around</button></div></div>';
  o.querySelector('.tour-offer-card').prepend(charImg('hello'));
  o.querySelector('b').textContent=(pupil?'Hi, '+me.name+'!':me.role==='admin'?'Hello, '+me.name+'!':'Hello, Teacher '+me.name+'!')+' I am Jarvis the Robot.';
  o.querySelector('.tour-offer-txt span').textContent='Beep boop! I am BULIG’s new tour guide. Tap the ? button anytime and I will show you around.';
  o.querySelector('.tour-yes').textContent=pupil?'Hi, Jarvis!':'Nice to meet you!';
  o.querySelector('.tour-yes').onclick=()=>{o.remove();metGeorge();};
  o.querySelector('.tour-no').onclick=()=>{o.remove();metGeorge();start();};
  document.body.append(o);setTimeout(()=>o.querySelector('.tour-yes').focus({preventScroll:true}),80);}
 const ready=()=>{if(key!=='dashboard')return;if(window.buligFriendsMet)me.met=true;   // Jarvis already said hello in "Meet your BULIG friends"
 if(me.seen){if(!me.met&&me.guide)setTimeout(meet,900);return;}if(answeredHere())markDone();else setTimeout(offer,900);};
 if(window.pfOverlayOpen&&window.pfOverlayOpen())document.addEventListener('pf:overlays-done',ready,{once:true});else ready();
})();
