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
 ['roxanne','Roxanne','the Timekeeper',true,'#e58a00','time','Reading time and breaks',"Tick, tock! I am Roxanne the Timekeeper. I time your reading, I count your goal for today, and I tell you when it is time for a short break.",1.45,1.08,'cheer'],
 ['claude','Claude','the Protector',false,'#1d2f5c','shield','Keeping your account safe',"Hello! I am Claude the Protector. I keep your account safe. Never share your password with anyone!",.78,.9,'thumbs'],
];

function friend_img(string $id,string $pose):string{return $id==='george'?char_src('george',$pose):'assets/images/characters/'.$id.'-'.$pose.'.webp?v=1';}
function friends_met(int $uid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',['friends_met_'.$uid]);}catch(Throwable $e){return true;}}
/** Met: the friends, and George too (she says hello first). */
function friends_mark_met(int $uid):void{
 foreach(['friends_met_','george_met_'] as $k)q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$k.$uid,date('Y-m-d H:i:s')]);
}

/** The pupil's own boy or girl picture is only on the reading ladder and the lesson map. Everywhere else a friend shows up:
   George by default (sign-in hello, loading, the lesson buddy), Toby for saving lessons offline, Claude for
   "Save me on this device" and signing out, Roxanne for reminders and breaks. */
function friends_tour_fix(?array $cu,array $m):array{
 if(!$cu||$cu['role']!=='pupil')return $m;
 $m['char']=friend_img('george','cheer');$m['hi']=friend_img('george','hello');
 $m['pals']=['dl'=>friend_img('toby','thumbs'),'dlDone'=>friend_img('toby','cheer'),'save'=>friend_img('claude','shield'),'bye'=>friend_img('claude','hello'),'remind'=>friend_img('roxanne','time'),'rest'=>friend_img('roxanne','time')];
 return $m;
}
/** The sign-in page: the friends take turns, two at a time (assets/login.js). */
function login_friend_pairs():array{
 $p=[['toby','cheer','george','hello'],['max','hello','dracs','hello'],['bruno','thumbs','mia','hello'],['zion','cheer','arah','hello'],['roxanne','hello','claude','thumbs']];
 return array_map(fn($x)=>['a'=>friend_img($x[0],$x[1]),'b'=>friend_img($x[2],$x[3])],$p);
}
/** A friend says something on their own page (v166): Arah on My calendar, Zion and Toby on My achievements, Toby on
   the reading board, Claude in Settings, Bruno after a wrong answer, and on each lesson's first screen the friend of
   that level (Max: Level 1, Mia: Levels 2A-3, Roxanne: Level 4, Dracs: Levels 5-7). */
function friend_say(string $id,string $pose,string $text):string{
 $f=null;foreach(BULIG_FRIENDS as $x)if($x[0]===$id)$f=$x;if(!$f)return '';
 return '<aside class="fs-say fs-'.$id.'"><img src="'.e(friend_img($id,$pose)).'" alt="" width="64" height="80" loading="lazy"><div><b>'.e($f[1].' '.$f[2]).'</b><span>'.e($text).'</span></div></aside>';
}
function level_friend(int $lv):string{
 if($lv===1)return friend_say('max','mic','Hi! I am Max the Listener. In Level 1 we listen and speak. When you see the microphone, tap it and talk to me!');
 if(in_array($lv,[2,3,4],true))return friend_say('mia','blocks','Hi! I am Mia the Builder. Let’s build words, one sound at a time!');
 if($lv===5)return friend_say('roxanne','time','Tick, tock! I am Roxanne the Timekeeper. Read the passage out loud and I will time your reading.');
 if($lv>=6)return friend_say('dracs','book','Hi there! I am Dracs the Storyteller. Let’s open this story together. Stories are full of magic!');
 return '';
}

function arah_say(int $pid):string{
 $n=(int)(progress_stats($pid)['current_streak']??0);
 return friend_say('arah','calendar',$n>1?'You learned '.$n.' days in a row! Come back tomorrow to keep your flame burning.':($n===1?'You learned today! Come back tomorrow and your flame will grow.':'Let’s start a new streak today! Read a little every day and I will mark your calendar.'));
}
function toby_say(int $pid):string{
 $n=(int)val('SELECT COUNT(*) FROM pupil_badges WHERE pupil_id=?',[$pid]);
 return friend_say('toby','thumbs',$n?'You have '.$n.' '.($n===1?'badge':'badges').' so far! Every badge is a step up the trail.':'Your first badge comes after your first activity. Let’s go, trailblazer!');
}
/** Roxanne on Today's goal: how the day is going. */
function roxanne_goal(int $n,int $goal):string{
 $done=$n>=$goal;$say=$done?'Tick, tock, goal done! See you tomorrow.':($n?'Tick, tock! Only '.($goal-$n).' more today.':'Tick, tock! A little reading every day.');
 return '<div class="rx-goal"><img src="'.e(friend_img('roxanne',$done?'cheer':'time')).'" alt="" width="58" height="64"><span><b>Roxanne the Timekeeper</b>'.e($say).'</span></div>';
}
/** Roxanne beside the Level 4 recorder: she times the reading (l1.js changes what she says). */
function roxanne_timer():string{
 return '<div class="rx-timer" data-rx data-time="'.e(friend_img('roxanne','time')).'" data-go="'.e(friend_img('roxanne','point')).'" data-done="'.e(friend_img('roxanne','cheer')).'"><img src="'.e(friend_img('roxanne','time')).'" alt="" width="64" height="70"><span data-rx-say>I am Roxanne the Timekeeper. I will time your reading. Tap the microphone when you are ready!</span></div>';
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
