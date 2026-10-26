<?php
/* The Reading Castle (v182), as in the BULIG comic: its great lamp is dim until a True Reader comes. When a pupil finishes Level 7 (every lesson, post-test at 80% or more), a full-screen scene
   opens once on the next page: night sky, fireworks and the castle. The pupil taps "Open the gates", the doors swing open,
   golden light pours out, their character walks in and they are welcomed as a True Reader. Seen is kept in settings
   (castle_seen_<id>), no SQL. ?castle=1 opens it again (the castle on the Level 7 road map links there). */
const CASTLE_LEVEL=8;   // Level 7
function castle_key(int $uid):string{return 'castle_seen_'.$uid;}
function castle_open(int $pid):bool{return function_exists('level_finished_at')&&level_finished_at($pid,CASTLE_LEVEL)!==null;}
/** The castle picture (v182b): stone bricks, round shaded towers with tiled cone roofs and gold tips, a tall middle tower with
    a book window, hanging banners, lit arched windows, a ribbon sign, a wooden gate that opens, steps, ivy and flowers.
    Class names drive the animation in theme.css (rc-win, rc-flag, rc-door-l/r, rc-rays, rc-gate). */
function castle_svg():string{
 $win=fn($x,$y,$w=24,$h=34)=>'<g><path d="M'.($x-4).' '.($y+$h+3).'V'.($y+$w/2).'a'.($w/2+4).' '.($w/2+4).' 0 0 1 '.($w+8).' 0V'.($y+$h+3).'Z" fill="#b89a6a"/>'
  .'<path class="rc-win" d="M'.$x.' '.($y+$h).'V'.($y+$w/2).'a'.($w/2).' '.($w/2).' 0 0 1 '.$w.' 0V'.($y+$h).'Z"/>'
  .'<path d="M'.($x+$w/2).' '.($y+2).'V'.($y+$h).'M'.$x.' '.($y+$h*.58).'H'.($x+$w).'" stroke="#8a6a3a" stroke-width="2"/><rect x="'.($x-6).'" y="'.($y+$h).'" width="'.($w+12).'" height="5" rx="2" fill="#a88a5a"/></g>';
 $cone=fn($x1,$x2,$base,$apex,$id)=>'<path d="M'.$x1.' '.$base.'Q'.(($x1+$x2)/2).' '.($base+12).' '.$x2.' '.$base.'L'.(($x1+$x2)/2).' '.$apex.'Z" fill="url(#'.$id.')"/>'
  .'<path d="M'.(($x1+$x2)/2).' '.$apex.'L'.($x1+($x2-$x1)*.3).' '.($base+4).'M'.(($x1+$x2)/2).' '.$apex.'L'.($x1+($x2-$x1)*.7).' '.($base+4).'M'.(($x1+$x2)/2).' '.$apex.'V'.($base+6).'" stroke="rgba(0,0,0,.16)" stroke-width="1.5"/>'
  .'<path d="M'.($x1+6).' '.($base-14).'Q'.(($x1+$x2)/2).' '.($base-4).' '.($x2-6).' '.($base-14).'M'.($x1+14).' '.($base-34).'Q'.(($x1+$x2)/2).' '.($base-26).' '.($x2-14).' '.($base-34).'" stroke="rgba(255,255,255,.28)" stroke-width="2" fill="none"/>'
  .'<path d="M'.($x1-2).' '.$base.'Q'.(($x1+$x2)/2).' '.($base+14).' '.($x2+2).' '.$base.'" stroke="#e0a91c" stroke-width="5" fill="none" stroke-linecap="round"/>'
  .'<circle cx="'.(($x1+$x2)/2).'" cy="'.($apex-3).'" r="5" fill="url(#rc-gold)"/>';
 $merlons=fn($x1,$x2,$y)=>implode('',array_map(fn($x)=>'<rect x="'.$x.'" y="'.$y.'" width="14" height="13" rx="2" fill="url(#rc-wall)" stroke="#c4a877" stroke-width="1.5"/>',range($x1,$x2-14,22)));
 $banner=fn($x)=>'<g><rect x="'.($x-2).'" y="153" width="22" height="4" rx="2" fill="#7a4a22"/><path d="M'.$x.' 156h18v46l-9-7-9 7Z" fill="#17713a"/><path d="M'.($x+2).' 158h14" stroke="#e0a91c" stroke-width="2"/>'
  .'<path d="M'.($x+3).' 172q3-2 6 0q3-2 6 0v9q-3-2-6 0q-3-2-6 0Z" fill="#ffd23f"/><path d="M'.($x+9).' 172v9" stroke="#17713a" stroke-width="1"/></g>';
 return '<svg class="rc-castle" viewBox="0 0 400 330" aria-hidden="true"><defs>'
  .'<linearGradient id="rc-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff8e6"/><stop offset="1" stop-color="#ead2a0"/></linearGradient>'
  .'<linearGradient id="rc-round" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#d9bd86"/><stop offset=".35" stop-color="#fff6df"/><stop offset=".6" stop-color="#f6e6c2"/><stop offset="1" stop-color="#c9a970"/></linearGradient>'
  .'<linearGradient id="rc-back" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#cdb98f"/><stop offset=".4" stop-color="#efe2c2"/><stop offset="1" stop-color="#bba57a"/></linearGradient>'
  .'<linearGradient id="rc-blue" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1c4b9c"/><stop offset=".4" stop-color="#4a8be0"/><stop offset="1" stop-color="#173f86"/></linearGradient>'
  .'<linearGradient id="rc-red" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#a5291f"/><stop offset=".42" stop-color="#ee5a46"/><stop offset="1" stop-color="#8f2219"/></linearGradient>'
  .'<linearGradient id="rc-gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff3a6"/><stop offset="1" stop-color="#e0a000"/></linearGradient>'
  .'<linearGradient id="rc-wood" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#6e3f18"/><stop offset=".5" stop-color="#a0662e"/><stop offset="1" stop-color="#6e3f18"/></linearGradient>'
  .'<radialGradient id="rc-glow" cx="50%" cy="65%" r="65%"><stop offset="0" stop-color="#fffbe0"/><stop offset=".5" stop-color="#ffd23f"/><stop offset="1" stop-color="#ff9f1c"/></radialGradient>'
  .'<radialGradient id="rc-lampg"><stop offset="0" stop-color="#fffbe0"/><stop offset=".35" stop-color="#ffe066" stop-opacity=".9"/><stop offset="1" stop-color="#ffd23f" stop-opacity="0"/></radialGradient>'
  .'<pattern id="rc-brick" width="24" height="12" patternUnits="userSpaceOnUse"><path d="M0 12H24M0 6H24M6 0V6M18 6V12" stroke="#9c7a45" stroke-opacity=".22" stroke-width="1" fill="none"/></pattern></defs>'
  /* far towers (hazy, for depth) */
  .'<g opacity=".9"><rect x="122" y="96" width="30" height="80" fill="url(#rc-back)"/><rect x="248" y="96" width="30" height="80" fill="url(#rc-back)"/>'.$cone(116,158,98,52,'rc-blue').$cone(242,284,98,52,'rc-blue').'</g>'
  /* the tall middle tower with the book window and the main flag */
  .'<rect x="168" y="66" width="64" height="100" fill="url(#rc-round)"/><rect x="168" y="66" width="64" height="100" fill="url(#rc-brick)"/>'.$cone(158,242,70,4,'rc-red')
  .'<path d="M200 2V-26" stroke="#6b5a45" stroke-width="3"/><path class="rc-flag" d="M201 -26h34l-8 8 8 8h-34z" fill="#f6c02c"/>'
  .'<circle class="rc-lampglow" cx="200" cy="106" r="58" fill="url(#rc-lampg)"/><circle cx="200" cy="106" r="20" fill="#b89a6a"/><circle class="rc-win rc-lamp" cx="200" cy="106" r="16"/><path d="M190 101q5-3 10 0q5-3 10 0v12q-5-3-10 0q-5-3-10 0Z" fill="#e0a91c"/><path d="M200 101v12" stroke="#8a5a10" stroke-width="1.2"/><circle class="rc-lampdim" cx="200" cy="106" r="16" fill="#3a3550" opacity=".45"/>'
  /* side round towers */
  .'<rect x="34" y="112" width="72" height="208" fill="url(#rc-round)"/><rect x="34" y="112" width="72" height="208" fill="url(#rc-brick)"/>'
  .'<rect x="294" y="112" width="72" height="208" fill="url(#rc-round)"/><rect x="294" y="112" width="72" height="208" fill="url(#rc-brick)"/>'
  .$cone(26,114,114,36,'rc-blue').$cone(286,374,114,36,'rc-blue')
  .'<path d="M70 33V8M330 33V8" stroke="#6b5a45" stroke-width="3"/><path class="rc-flag" d="M71 8h28l-7 7 7 7H71z" fill="#d6452f"/><path class="rc-flag" d="M331 8h28l-7 7 7 7h-28z" fill="#2a6cc0"/>'
  .$win(58,146).$win(58,222).$win(318,146).$win(318,222)
  /* the keep: brick wall, battlements, banners, windows */
  .'<rect x="96" y="150" width="208" height="170" fill="url(#rc-wall)"/><rect x="96" y="150" width="208" height="170" fill="url(#rc-brick)"/>'
  .$merlons(96,304,138).'<rect x="94" y="149" width="212" height="6" rx="2" fill="#d9bd86"/>'
  .$win(116,172).$win(260,172).$banner(150).$banner(232)
  /* the gate: stone arch, glow, rays, wooden doors that open */
  .'<path d="M156 320V286a44 44 0 0 1 88 0V320Z" fill="#a88a5a"/><path d="M196 238h8l-1 9h-6Z" fill="#8a6a3a"/>'
  .'<g class="rc-gate"><path d="M164 320V290a36 36 0 0 1 72 0V320Z" fill="url(#rc-glow)"/>'
  .'<path class="rc-rays" d="M200 300 128 330M200 300 162 330M200 300 238 330M200 300 272 330" stroke="#fff3a6" stroke-width="7" stroke-linecap="round" opacity=".85"/>'
  .'<g class="rc-door rc-door-l"><path d="M164 320V290a36 36 0 0 1 36-36V320Z" fill="url(#rc-wood)" stroke="#4e2a0e" stroke-width="2.5"/><path d="M176 262V320M188 256V320" stroke="#4e2a0e" stroke-opacity=".45" stroke-width="1.5"/><path d="M164 278h36M164 306h36" stroke="#3a3a3a" stroke-width="3"/><circle cx="192" cy="292" r="4" fill="none" stroke="url(#rc-gold)" stroke-width="2.5"/></g>'
  .'<g class="rc-door rc-door-r"><path d="M236 320V290a36 36 0 0 0-36-36V320Z" fill="url(#rc-wood)" stroke="#4e2a0e" stroke-width="2.5"/><path d="M224 262V320M212 256V320" stroke="#4e2a0e" stroke-opacity=".45" stroke-width="1.5"/><path d="M200 278h36M200 306h36" stroke="#3a3a3a" stroke-width="3"/><circle cx="208" cy="292" r="4" fill="none" stroke="url(#rc-gold)" stroke-width="2.5"/></g></g>'
  /* ribbon sign */
  .'<path d="M112 226h18v-4h140v4h18l-10 11 10 11h-28v4H130v-4h-28l10-11Z" fill="#0b4a26"/><path d="M130 222h140v26H130Z" fill="#17713a"/><path d="M130 225h140M130 245h140" stroke="#e0a91c" stroke-width="1.5"/>'
  .'<text x="200" y="240" text-anchor="middle" font-weight="800" font-size="13" letter-spacing="1.5" fill="#fff">READING CASTLE</text>'
  /* steps, ivy and flowers */
  .'<path d="M150 320h100l8 5H142Z M138 325h124l8 5H130Z" fill="#cdb48a"/>'
  .'<g fill="#3e9a4a"><circle cx="40" cy="300" r="7"/><circle cx="48" cy="290" r="6"/><circle cx="42" cy="278" r="5"/><circle cx="52" cy="268" r="4"/><circle cx="362" cy="302" r="7"/><circle cx="354" cy="292" r="5"/><circle cx="360" cy="281" r="4"/></g>'
  .'<g><circle cx="110" cy="318" r="9" fill="#4cae5e"/><circle cx="124" cy="320" r="7" fill="#3e9a4a"/><circle cx="108" cy="314" r="2.5" fill="#ff8fab"/><circle cx="118" cy="316" r="2.5" fill="#ffd23f"/>'
  .'<circle cx="290" cy="318" r="9" fill="#4cae5e"/><circle cx="276" cy="320" r="7" fill="#3e9a4a"/><circle cx="292" cy="313" r="2.5" fill="#9ad0ff"/><circle cx="282" cy="317" r="2.5" fill="#ff8fab"/></g>'
  .'</svg>';
}
/** The castle scene, or '' when it is not due. */
function castle_pop(?array $u):string{
 if(!$u||$u['role']!=='pupil')return '';$pid=(int)$u['id'];$again=($_GET['castle']??'')==='1';
 if(!castle_open($pid))return '';
 if(!$again&&function_exists('friends_meta')&&friends_meta($u)!=='')return '';   // the friends say hello first; the castle opens on the next page
 if(!$again){try{if(val('SELECT 1 FROM settings WHERE setting_key=?',[castle_key($pid)]))return '';q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',[castle_key($pid),date('Y-m-d H:i:s')]);}catch(Throwable $e){return '';}
  if(function_exists('notify'))notify($pid,'certificate','You entered the Reading Castle!','You finished Level 7. Tap to visit the Reading Castle again.','?page=lessons&level='.CASTLE_LEVEL.'&castle=1');}
 $first=explode(' ',trim((string)$u['name']))[0];$girl=function_exists('pf_is_girl')&&pf_is_girl();
 $kid=fn($p)=>e(function_exists('char_src')?char_src($girl?'girl':'boy',$p):'');
 $castle=castle_svg();
 /* the BULIG friends from the story cheer when the lamp is lit (as on the comic's last page) */
 $pals='';foreach(['jarvis','george','mia','bob','dracs','max'] as $k=>$id)$pals.='<img class="rc-pal rc-pal'.$k.'" src="'.e(function_exists('friend_img')?friend_img($id,'cheer'):'assets/images/characters/'.$id.'-cheer.webp').'" alt="" width="90" height="130" hidden>';
 $stars='';for($i=0;$i<28;$i++)$stars.='<i class="rc-twinkle rc-t'.($i%6).' rc-p'.$i.'"></i>';
 return '<div class="rc-shade" data-rc role="dialog" aria-modal="true" aria-labelledby="rc-title" data-rc-say1="'.e('Congratulations, '.$first.'! You finished Level 7. The great lamp of the Reading Castle is waiting for you. Tap Open the gates.').'" data-rc-say2="'.e('You lit the great lamp, '.$first.'! Welcome to the Reading Castle. The BULIG friends cheer for you. You are a True Reader. Keep reading every day!').'">'
  .'<div class="rc-sky" aria-hidden="true">'.$stars.'<span class="rc-moon"></span><span class="rc-hill rc-hill-a"></span><span class="rc-hill rc-hill-b"></span></div>'
  .'<div class="rc-fx" aria-hidden="true" data-rc-fx></div>'
  .'<button type="button" class="rc-x" data-rc-close aria-label="Close">'.icon('close').'</button>'
  .'<div class="rc-stage">'
  .'<div class="rc-text rc-text1"><span class="rc-badge">'.icon('star').'LEVEL 7 COMPLETE</span><h2 id="rc-title">You did it, '.e($first).'!</h2><p>You finished every level of BULIG. The great lamp of the Reading Castle is waiting for a True Reader to light it.</p></div>'
  .'<div class="rc-text rc-text2" hidden><span class="rc-badge rc-badge-gold">'.icon('star').'TRUE READER</span><h2>Welcome to the Reading Castle!</h2><p>You lit the great lamp, '.e($first).'! The BULIG friends cheer for you, a True Reader. Keep reading every day!</p></div>'
  .'<div class="rc-scene">'.$castle.'<img class="rc-kid rc-kid-a" src="'.$kid('hello').'" alt="" width="120" height="170"><img class="rc-kid rc-kid-b" src="'.$kid('cheer').'" alt="" width="120" height="170" hidden>'.$pals.'</div>'
  .'<div class="rc-actions rc-act1"><button type="button" class="btn primary rc-go" data-rc-open>'.icon('arrow').'Open the gates</button></div>'
  .'<div class="rc-actions rc-act2" hidden><a class="btn primary" href="?page=certificate&amp;level='.CASTLE_LEVEL.'">'.icon('download').'See my certificate</a><button type="button" class="btn secondary" data-rc-say>'.icon('replay').'Listen again</button><button type="button" class="btn quiet" data-rc-close>Stay in the castle</button></div>'
  .'</div></div>';
}
