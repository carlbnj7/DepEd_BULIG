<?php
/* Story time: the comic "How the BULIG Friends Met" (cover + 17 pages, one big scene per page; Bob the Worker came in v178, Jeepy the Jeepney in v199).
   The pictures are drawn by tools/comic_build.py into app/story_pages.json (one SVG and the lines for each page).
   - ?page=story&p=N shows page N. Back / Next are real links, so it works without JavaScript; story.js turns pages
     without reloading (it loads ?page=story_data once), reads each page aloud with a voice for every friend
     (Jarvis in his robot voice) and lights up each word, and shows the story full screen for the class.
   - ?page=story&print=1 lists every page for printing.
   - Pupils earn XP once: +3 for each page the first time they look at it for a few seconds, and +12 more when they
     have seen every page (63 XP in all with Bob's page). Which pages a pupil has seen is kept in settings (story_seen_<id>).
   On phones the speech bubbles are hidden in the picture and the same lines show under it, big enough to read. */

function story_pages():array{static $p=null;if($p===null){$f=__DIR__.'/story_pages.json';$p=is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[];}return $p;}

const STORY_XP_PAGE=3,STORY_XP_END=12;
const STORY_GIRLS=['George','Dracs','Mia','Arah','Roxanne','Sofie','Teacher','Pupil'];

function story_icon(string $k):string{
 $d=['play'=>'<path d="m8 5 11 7-11 7z"/>','stop'=>'<rect x="6" y="6" width="12" height="12" rx="2"/>','prev'=>'<path d="M19 12H5M11 6l-6 6 6 6"/>','next'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
  'full'=>'<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>','print'=>'<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>'][$k]??'';
 return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
}
/** The lines under the picture: each word in its own span so story.js can light it up while it is read. */
function story_script(array $pg):string{
 if(!$pg['script'])return '';
 $h='<div class="cm-script">';
 foreach($pg['script'] as $i=>$l){$w=implode(' ',array_map(fn($x)=>'<span class="w">'.e($x).'</span>',preg_split('/\s+/',trim($l['text']))));
  $h.=$l['who']==='Narrator'?'<p class="cm-n" data-line="'.$i.'">'.$w.'</p>':'<p class="cm-l" data-line="'.$i.'" data-who="'.e($l['who']).'"><b>'.e($l['who']).'</b><span>'.$w.'</span></p>';}
 return $h.'</div>';
}
function story_page_html(array $pg,int $count,bool $print=false):string{
 $n=(int)$pg['n'];$label=$n===0?'Cover':'Page '.$n.' · '.$pg['title'];
 $h='<section class="cm-page" data-n="'.$n.'"><header class="cm-head"><span class="cm-chip">HOW THE BULIG FRIENDS MET</span><h2 class="cm-title">'.e($label).'</h2>';
 if(!$print)$h.='<div class="cm-tools"><button type="button" class="cm-listen" data-story-listen><img src="assets/images/characters/jarvis-head.webp?v=2" alt=""><span>Listen</span></button>'
  .'<button type="button" class="cm-ico" data-story-full aria-label="Full screen" title="Full screen">'.story_icon('full').'</button>'
  .'<a class="cm-ico" href="?page=story&amp;print=1" target="_blank" rel="noopener" aria-label="Print the story" title="Print the story">'.story_icon('print').'</a></div>';
 $h.='</header><figure class="cm-fig">'.$pg['svg'].'</figure>'.story_script($pg);
 if(!$print){
  $h.='<footer class="cm-foot">'.($n>0?'<a class="cm-btn" data-story-go="'.($n-1).'" href="?page=story&amp;p='.($n-1).'">'.story_icon('prev').'Back</a>':'<span class="cm-btn off">'.story_icon('prev').'Back</span>');
  $h.='<span class="cm-dots">';for($i=0;$i<$count;$i++)$h.='<a class="'.($i===$n?'on':'').'" data-story-go="'.$i.'" href="?page=story&amp;p='.$i.'" aria-label="'.($i?'Page '.$i:'Cover').'"></a>';$h.='</span>';
  $h.=$n<$count-1?'<a class="cm-btn cm-next" data-story-go="'.($n+1).'" href="?page=story&amp;p='.($n+1).'">'.($n===0?'Start reading':'Next page').story_icon('next').'</a>':'<a class="cm-btn cm-next" data-story-go="0" href="?page=story&amp;p=0">Read again'.story_icon('next').'</a>';
  $h.='</footer>';}
 return $h.'</section>';
}
function story_view(array $u):void{
 $pages=story_pages();$count=count($pages);
 echo '<link rel="stylesheet" href="'.asset_url('story.css').'">';
 if(!$pages){echo '<section class="card"><p class="muted">The story is not installed yet.</p></section>';return;}
 if(isset($_GET['print'])){
  echo '<div class="cm-print-bar"><button type="button" class="btn primary" data-story-print>'.story_icon('print').'Print the story</button><span class="muted">'.$count.' pages · one page per sheet</span></div><div class="cm-book cm-printing">';
  foreach($pages as $pg)echo story_page_html($pg,$count,true);
  echo '</div><script src="'.asset_url('story.js').'" defer></script>';return;}
 $n=max(0,min($count-1,(int)($_GET['p']??0)));
 echo '<div class="pageheading"><div><span class="eyebrow">STORY TIME</span><h1>How the BULIG Friends Met</h1><p class="muted">A comic about Jarvis and the 14 BULIG friends. Tap <b>Listen</b> and Jarvis reads it with you.</p></div></div>';
 echo story_xp_bar($u);
 echo '<div class="cm-book" data-story data-pages="'.$count.'" data-girls="'.e(json_encode(STORY_GIRLS)).'"'.($u['role']==='pupil'?' data-xp="'.STORY_XP_PAGE.'" data-csrf="'.e(csrf()).'"':'').'>'.story_page_html($pages[$n],$count).'</div>';
 echo '<script src="'.asset_url('story.js').'" defer></script>';
}
function story_seen(int $pid):array{try{$v=(string)val('SELECT setting_value FROM settings WHERE setting_key=?',['story_seen_'.$pid]);}catch(Throwable $e){$v='';}
 if($v==='')return [];if(str_starts_with($v,'v3:'))return array_map('intval',explode(',',substr($v,3)));
 /* v199: Jeepy the Jeepney's page came in as page 16, so the lamp is page 17 now (same as Bob's page in v178) */
 if(str_starts_with($v,'v2:')){$old=array_map('intval',explode(',',substr($v,3)));$new=array_map(fn($n)=>$n>=16?$n+1:$n,$old);
  try{if(count($old)>=17)q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',['story_done_'.$pid,date('Y-m-d H:i:s')]);
   q('UPDATE settings SET setting_value=? WHERE setting_key=?',['v3:'.implode(',',$new),'story_seen_'.$pid]);}catch(Throwable $e){}
  return $new;}
 /* v178: Bob the Worker's page came in as page 15, so the old page 15 (the lamp) is page 16 now. A pupil who had read the
    whole story keeps the "finished" bonus they already got (story_done_<id>); Bob's page still gives +3 XP. */
 $old=array_map('intval',explode(',',$v));$new=array_map(fn($n)=>$n>=16?$n+2:($n>=15?$n+1:$n),$old);
 try{if(count($old)>=16)q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',['story_done_'.$pid,date('Y-m-d H:i:s')]);
  q('UPDATE settings SET setting_value=? WHERE setting_key=?',['v3:'.implode(',',$new),'story_seen_'.$pid]);}catch(Throwable $e){}
 return $new;}
function story_done_before(int $pid):bool{try{return (bool)val('SELECT 1 FROM settings WHERE setting_key=?',['story_done_'.$pid]);}catch(Throwable $e){return false;}}
/** A pupil looked at page $n: XP the first time, and the bonus once every page is seen. */
function story_mark(int $pid,int $n):array{
 $count=count(story_pages());if($n<0||$n>=$count)return ['gained'=>0,'seen'=>count(story_seen($pid)),'count'=>$count,'done'=>false];
 db()->beginTransaction();
 try{q('SELECT id FROM users WHERE id=? FOR UPDATE',[$pid]);$seen=story_seen($pid);$gain=0;$done=false;
  if(!in_array($n,$seen,true)){$seen[]=$n;sort($seen);$gain=STORY_XP_PAGE;
   if(count($seen)>=$count){if(!story_done_before($pid)){$gain+=STORY_XP_END;q('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)',['story_done_'.$pid,date('Y-m-d H:i:s')]);}$done=true;}
   q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['story_seen_'.$pid,'v3:'.implode(',',$seen)]);
   q('INSERT INTO pupil_xp(pupil_id,total) VALUES(?,?) ON DUPLICATE KEY UPDATE total=total+VALUES(total)',[$pid,$gain]);}
  db()->commit();}
 catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 return ['gained'=>$gain,'seen'=>count($seen),'count'=>$count,'done'=>$done];
}
function story_xp_bar(array $u):string{
 if($u['role']!=='pupil')return '';$count=count(story_pages());$seen=count(story_seen((int)$u['id']));$left=($count-$seen)*STORY_XP_PAGE+($seen<$count?STORY_XP_END:0);
 return '<div class="cm-xp'.($seen>=$count?' done':'').'" data-story-xp><b data-story-seen>'.$seen.'</b><span>of '.$count.' pages read</span><progress max="'.$count.'" value="'.$seen.'" data-story-bar></progress><small data-story-left>'.($seen>=$count?'Story finished! You earned all '.($count*STORY_XP_PAGE+STORY_XP_END).' XP.':'+'.STORY_XP_PAGE.' XP for each new page, +'.STORY_XP_END.' XP when you finish · '.$left.' XP left').'</small></div>';
}
/** On the pupil Overview (phones have no side menu): open the story, with the XP still to earn. */
function story_card(array $u):string{
 $count=count(story_pages());if(!$count)return '';$seen=count(story_seen((int)$u['id']));$fin=$seen>=$count;
 $left=($count-$seen)*STORY_XP_PAGE+($fin?0:STORY_XP_END);
 return '<link rel="stylesheet" href="'.asset_url('story.css').'"><a class="cm-card'.($fin?' done':'').'" href="?page=story'.($seen&&!$fin?'&amp;p='.min($count-1,$seen):'').'"><img src="assets/images/characters/jarvis-cheer.webp?v=2" alt="">'
  .'<span><small>STORY TIME</small><b>How the BULIG Friends Met</b><em>'.($fin?'You read the whole story. Read it again anytime!':($seen?'Keep reading: '.$seen.' of '.$count.' pages read · '.$left.' XP left':'A comic about Jarvis and the 14 friends · earn '.$left.' XP')).'</em></span>'
  .'<i class="cm-card-go">'.story_icon('next').'</i></a>';
}
/** Every page for story.js, so pages turn without loading the whole screen again. */
function story_data():never{
 header('Content-Type: application/json');header('Cache-Control: private, max-age=3600');
 $pages=story_pages();$count=count($pages);
 echo json_encode(array_map(fn($pg)=>['n'=>$pg['n'],'html'=>story_page_html($pg,$count)],$pages),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
