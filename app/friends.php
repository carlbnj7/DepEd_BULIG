<?php
/** George's friends (v163). Each friend has a job in BULIG and five poses in assets/images/characters (name-pose.webp).
   "Meet your BULIG friends" shows once, the first time a pupil opens Home: George first, then each friend says hello
   in their own voice (assets/friends.js). Claude the Protector also gives a safety tip on every Home page
   (pupil, teacher, administrator), and looks worried or sad in the sign-in popups. */

/* id, name, title, girl, colour, special pose, job, hello line ({n} = the pupil's first name), voice pitch, voice speed, last-screen pose */
const BULIG_FRIENDS=[
 ['george','George','the Explorer',true,'#0f8a83','map','Your guide on every page',"Hi, {n}! I am George the Explorer, your reading buddy. I want you to meet my friends. They will help you on every page of BULIG!",1.12,.95,'cheer'],
 ['max','Max','the Listener',false,'#1e7fd6','mic','Listening and speaking',"Hello! I am Max the Listener. When you see me, tap the microphone and read to me. I love to listen!",1.25,1.02,'cheer'],
 ['dracs','Dracs','the Storyteller',true,'#7a4fc9','book','Stories, Levels 4 to 7',"Hi there! I am Dracs the Storyteller. I will open every story with you. Stories are full of magic!",1.0,.9,'cheer'],
 ['bruno','Bruno','the Helper',false,'#4d9f86','tryagain','Try again after a mistake',"Hey, friend! I am Bruno the Helper. Made a mistake? That is okay! We will try again together.",1.15,.88,'cheer'],
 ['mia','Mia','the Builder',true,'#ef7d14','blocks','Spelling and sounds',"Hi! I am Mia the Builder. Let's build words, one sound at a time!",1.35,1.05,'cheer'],
 ['toby','Toby','the Trailblazer',false,'#2f8f3a','flag','Progress, badges and level-ups',"Hello! I am Toby the Trailblazer. Every lesson you finish, we climb higher together!",.95,1.0,'cheer'],
 ['zion','Zion','the Rank Keeper',false,'#c62828','emblem','Ranks and stars',"Hello! I am Zion the Rank Keeper. Earn your stars, and I will give you a new rank emblem!",.85,.93,'cheer'],
 ['arah','Arah','the Streak Keeper',true,'#e2508f','calendar','Your reading streak and calendar',"Hi! I am Arah the Streak Keeper. Read a little every day, and I will keep your flame burning!",1.22,1.0,'cheer'],
 ['claude','Claude','the Protector',false,'#1d2f5c','shield','Keeping your account safe',"Hello! I am Claude the Protector. I keep your account safe. Never share your password with anyone!",.78,.9,'thumbs'],
];

function friend_img(string $id,string $pose):string{return $id==='george'?char_src('george',$pose):'assets/images/characters/'.$id.'-'.$pose.'.webp?v=1';}
function friends_met(int $uid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',['friends_met_'.$uid]);}catch(Throwable $e){return true;}}
/** Met: the friends, and George too (she says hello first). */
function friends_mark_met(int $uid):void{
 foreach(['friends_met_','george_met_'] as $k)q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$k.$uid,date('Y-m-d H:i:s')]);
}

/** <meta name="bulig-friends"> for assets/friends.js: only for a pupil who has not met the friends yet. */
function friends_meta(?array $cu):string{
 if(!$cu||$cu['role']!=='pupil'||friends_met((int)$cu['id']))return '';
 $list=array_map(fn($f)=>['id'=>$f[0],'name'=>$f[1],'title'=>$f[2],'girl'=>$f[3],'c'=>$f[4],'job'=>$f[6],'line'=>$f[7],'pitch'=>$f[8],'rate'=>$f[9],
  'hello'=>friend_img($f[0],'hello'),'special'=>friend_img($f[0],$f[5]),'last'=>friend_img($f[0],$f[10])],BULIG_FRIENDS);
 return '<meta name="bulig-friends" content="'.e(json_encode(['name'=>explode(' ',trim((string)$cu['name']))[0],'tour'=>!tour_seen((int)$cu['id']),'friends'=>$list])).'">';
}

/** Claude the Protector's safety tip on each Home page: a different tip and pose each day. */
function claude_tip(array $u):string{
 $tips=['pupil'=>['Keep your password a secret. Only you, your teacher and your family should know it.','Using a shared computer or phone? Tap Sign out when you are done.','Never tell your password to a friend, even a best friend.','Someone knows your password? Tell your teacher. They can make you a new one.'],
  'teacher'=>['Never share your password, even with another teacher.','Using a school computer? Sign out before you leave it.','Keep printed sign-in tickets in a safe place, away from other pupils.','A pupil forgot their password? Open the pupil, then Account, and make a new ticket.'],
  'admin'=>['Your PIN protects BULIG. Never write it down near the computer.','Open Account check every week: it finds turned-off and doubled accounts.','A teacher left the school? Move their pupils, then turn the account off.','Download a backup before every update.']][$u['role']]??[];
 if(!$tips)return '';$d=(int)date('z');$pose=['hello','shield','thumbs'][$d%3];
 return '<aside class="cl-tip" aria-label="Safety tip from Claude the Protector"><img src="'.e(friend_img('claude',$pose)).'" alt="" width="60" height="100" loading="lazy"><div><b>Claude the Protector</b><span>'.e($tips[$d%count($tips)]).'</span></div></aside>';
}
/** Claude in the sign-in popups: worried after a wrong password, sad when the account must wait. */
function claude_pop(string $pose):string{return '<img class="lgn-claude lgn-claude-'.$pose.'" src="'.e(friend_img('claude',$pose)).'" alt="" width="92" height="160">';}
