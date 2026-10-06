<?php
/* Level 1 (oral language). Pupils answer by speaking, never by typing:
   - one clean screen per activity: the question, its picture, one big button;
   - voice recordings are kept in storage/recordings (outside the public folder) and only the pupil and their teacher can play them;
   - teachers score each answer with the module's own rubric (lessons 7–11 have no rubric, so their lesson-plan checklist is used);
   - the Level 1 Class Demo shows the same screens as big projector slides. */

const L1_SCALE=[4=>'Excellent',3=>'Good',2=>'Satisfactory',1=>'Needs Improvement'];

/** The module's scoring guide for a Level 1 lesson, by lesson number. */
function l1_rubric(int $position):?array{
 $R=[
  1=>['Rubric (module page 1)',[
   ['Completeness',[4=>'Cover self (name, age, likes) +3 details',3=>'Covers self + 2 details',2=>'Covers self + 1 detail',1=>'Only shares 1-2 small facts about self']],
   ['Clarity',[4=>'Speech is clear, loud, and easy to understand',3=>'Mostly clear with minor pauses',2=>'Somewhat unclear or quiet',1=>'Hard to understand; mumbles']],
   ['Organization',[4=>'Shares info in a logical order (self-first, then family)',3=>'Mostly logical order',2=>'Somewhat disorganized',1=>'No clear order']]]],
  2=>['Rubric (module page 2)',[
   ['Correctness of Steps',[4=>'Performs all steps exactly as instructed',3=>'Performs all steps with 1 minor error',2=>'Performs 1-2 steps correctly',1=>'Performs few or no steps correctly']],
   ['Order of Steps',[4=>'Follows the exact order given',3=>'Follows order with 1 small mix-up',2=>'Mixes up most steps',1=>'No sense of order']],
   ['Timeliness',[4=>'Completes steps quickly and confidently',3=>'Completes steps at steady pace',2=>'Takes time to start/finish',1=>'Hesitates heavily or refuses to try']]]],
  3=>['Rubric (module page 3)',[
   ['Number of Instructions',[4=>'Gives 2+ clear, distinct instructions',3=>'Gives 2 instructions (1 slightly unclear)',2=>'Gives a clear instruction',1=>'Gives 0-1 vague/unclear instruction']],
   ['Clarity & Tone',[4=>'Language is simple, friendly, and easy for a child to understand',3=>'Mostly simple/friendly with minor formality',2=>'A bit too complex or stern',1=>'Confusing, too formal, or rude']],
   ['Relevance',[4=>'Instructions directly relate to “keeping my toys away”',3=>'Mostly relevant to the task',2=>'Slightly off-topic',1=>'Unrelated to the task']]]],
  4=>['Rubric (module page 4)',[
   ['Relevance',[4=>'All words are exactly describing the item/place/person',3=>'Most words are relevant',2=>'Some words are relevant',1=>'No relevant describing words']],
   ['Variety',[4=>'Uses 4+ different types of descriptors (e.g. color + shape + size)',3=>'Uses 2-3 different types',2=>'Uses 1 type of descriptor',1=>'Repeats the same word or uses none']],
   ['Accuracy',[4=>'Descriptors are 100% correct',3=>'Mostly correct with 1 minor mistake',2=>'Partially correct',1=>'Mostly incorrect']]]],
  5=>['Rubric (module page 5)',[
   ['Relevance',[4=>'Talks only about details/ideas from the picture',3=>'Mostly relevant to the picture',2=>'Somewhat off-topic at times',1=>'Unrelated to the picture']],
   ['Depth of thought',[4=>'Shares 3+ ideas/opinions',3=>'Shares 2 ideas/opinions',2=>'Shares 1 simple idea/observation',1=>'Only says 1-2 words (e.g., “It’s nice”)']],
   ['Clarity',[4=>'Speech is clear, with good eye contact',3=>'Mostly clear with minor pauses',2=>'Somewhat unclear or quiet',1=>'Hard to understand']]]],
  6=>['Rubric (module page 6)',[
   ['Detail',[4=>'Describes 5+ key elements (people, objects, colors)',3=>'Describes 3-4 key elements',2=>'Describes 1-2 key elements',1=>'Describes no key elements']],
   ['Clarity',[4=>'Description is so clear, someone could draw it easily',3=>'Mostly clear enough to follow',2=>'Somewhat confusing',1=>'Impossible to understand what’s being described']],
   ['Organization',[4=>'Describes in a logical order (e.g., left to right, top to bottom)',3=>'Mostly logical order',2=>'Somewhat disorganized',1=>'No clear order']]]],
  12=>['Rubric (module page 8)',[
   ['Pronunciation',[4=>'Pronounces all words correctly (even at speed)',3=>'Most correct pronunciation',2=>'Some mispronunciations but still clear',1=>'Many mispronunciations; hard to follow']],
   ['Fluency',[4=>'Says the tongue twister smoothly 2+ times',3=>'Says it smoothly once, with 1 pause',2=>'Says it with 2-3 pauses',1=>'Stumbles heavily or can’t finish']],
   ['Effort & Confidence',[4=>'Tries enthusiastically, speaks loudly',3=>'Tries willingly, mostly loud',2=>'Tries but is quiet/hesitant',1=>'Reluctant to try or gives up']]]],
 ];
 if(isset($R[$position]))return ['kind'=>'rubric','title'=>$R[$position][0],'criteria'=>$R[$position][1]];
 $C=[
  7=>['Lesson plan checklist (Evaluation)',['Contributes at least one sentence.','Speaks clearly.','Listens to others.']],
  8=>['Lesson plan smiley face checklist (Evaluation)',['Spoke clearly.','Participated.']],
  9=>['Smile & Shine Checklist (lesson plan)',['Clear, audible voice','Tone matches the poem’s feeling (happy, silly, etc.)','Simple gestures/actions to support key words']],
  10=>['Talk & Connect Checklist (lesson plan)',['Greets peers appropriately.','Asks simple questions.','Shares ideas clearly (even if words are simple).','Listens when others talk.']],
 ];
 if(isset($C[$position]))return ['kind'=>'check','title'=>$C[$position][0],'items'=>$C[$position][1]];
 if($position===11)return ['kind'=>'scale','title'=>'Lesson plan observation (Evaluation)','options'=>[3=>['Star','Shared 3+ words and explained.'],2=>['Thumbs up','Shared 1-2 words.'],1=>['Smile','Tried their best!']]];
 return null;
}

/** Turn posted rubric values into what is saved, or null when nothing valid was chosen. */
function l1_rubric_values(array $r,array $in):?array{
 $v=[];
 if($r['kind']==='rubric'){foreach($r['criteria'] as $i=>$c){$x=(int)($in[$i]??0);if($x<1||$x>4)return null;$v[]=$x;}}
 elseif($r['kind']==='check'){foreach($r['items'] as $i=>$c)$v[]=!empty($in[$i])?1:0;}
 else{$x=(int)($in[0]??0);if(!isset($r['options'][$x]))return null;$v[]=$x;}
 return $v;
}

/** Short text of a saved score, for lists ("10 / 12", "2 of 3 checked", "Star"). */
function l1_score_text(?string $json,?array $r):string{
 $s=json_decode((string)$json,true);if(!$r||!is_array($s)||!isset($s['v']))return '';$v=array_map('intval',$s['v']);
 if($r['kind']==='rubric')return array_sum($v).' / '.(4*count($r['criteria']));
 if($r['kind']==='check')return array_sum($v).' of '.count($r['items']).' checked';
 return $r['options'][$v[0]??0][0]??'';
}

/** The lesson number of a Level 1 lesson (1–12), or 0 for other levels. */
function l1_position(int $lid):int{static $c=[];if(!isset($c[$lid]))$c[$lid]=lesson_level($lid)===1?(int)val('SELECT position FROM lessons WHERE id=?',[$lid]):0;return $c[$lid];}

/* ---------------- voice recordings ---------------- */

function l1_recordings_dir():string{return __DIR__.'/../storage/recordings';}

/** Check a recording sent as a data URL and keep it on the server. Returns the stored path (relative to storage/recordings). */
function l1_save_audio(string $data,int $pid,int $aid):string{
 if(!preg_match('~^data:audio/(webm|ogg|mp4|mpeg|aac|x-m4a)(?:;codecs=[a-z0-9.,"\- ]+)?;base64,([A-Za-z0-9+/=]+)$~i',$data,$m))fail('This recording could not be read. Please record again.');
 $bin=base64_decode($m[2],true);if($bin===false||strlen($bin)<200)fail('This recording is empty. Please record again.');
 if(strlen($bin)>9000000)fail('This recording is too long. Please record again.');
 $head=substr($bin,0,12);
 if(str_starts_with($head,"\x1A\x45\xDF\xA3"))$ext='webm';elseif(str_starts_with($head,'OggS'))$ext='ogg';elseif(substr($head,4,4)==='ftyp')$ext='m4a';
 elseif(str_starts_with($head,'ID3')||(ord($head[0])===0xFF&&(ord($head[1])&0xE0)===0xE0))$ext=strtolower($m[1])==='aac'?'aac':'mp3';else fail('This recording could not be read. Please record again.');
 $dir=l1_recordings_dir().'/'.$pid;if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))fail('Recordings cannot be saved on the server right now. Please tell your teacher.',500);
 $name=$aid.'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$ext;
 if(@file_put_contents($dir.'/'.$name,$bin,LOCK_EX)===false)fail('Recordings cannot be saved on the server right now. Please tell your teacher.',500);
 return $pid.'/'.$name;
}

/** Play a recording: the pupil who made it, or a teacher of that pupil. */
function l1_recording(array $u):never{
 $c=one('SELECT * FROM activity_completion WHERE id=?',[(int)($_GET['id']??0)]);
 /* Level 2A keeps one recording per card: ?n=card number. */
 if($c&&isset($_GET['n'])){$m=json_decode((string)($c['audio_paths']??''),true)?:[];$c['audio_path']=$m[(string)(int)$_GET['n']]??null;}
 if(!$c||!$c['audio_path'])fail('Recording not found.',404);
 if($u['role']==='teacher')own_pupil((int)$c['pupil_id']);elseif((int)$u['id']!==(int)$c['pupil_id'])fail('Recording not found.',404);
 $path=(string)$c['audio_path'];if(!preg_match('~^\d+/[0-9]+-\d{14}-[0-9a-f]{8}\.(webm|ogg|m4a|mp3|aac)$~',$path,$m))fail('Recording not found.',404);
 $f=l1_recordings_dir().'/'.$path;if(!is_file($f))fail('This recording is no longer on the server.',404);
 $type=['webm'=>'audio/webm','ogg'=>'audio/ogg','m4a'=>'audio/mp4','mp3'=>'audio/mpeg','aac'=>'audio/aac'][$m[1]];
 while(ob_get_level())ob_end_clean();
 $size=filesize($f);$start=0;$end=$size-1;
 header('Content-Type: '.$type);header('Accept-Ranges: bytes');header('Cache-Control: private, max-age=3600');header('X-Content-Type-Options: nosniff');
 /* Safari plays audio only when byte ranges work. */
 if(preg_match('~^bytes=(\d*)-(\d*)$~',(string)($_SERVER['HTTP_RANGE']??''),$r)){
  if($r[1]===''&&$r[2]!==''){$start=max(0,$size-(int)$r[2]);}else{$start=(int)$r[1];if($r[2]!=='')$end=min($end,(int)$r[2]);}
  if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
  http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}
 header('Content-Length: '.($end-$start+1));
 $h=fopen($f,'rb');fseek($h,$start);$left=$end-$start+1;while($left>0&&!feof($h)){$chunk=fread($h,min(65536,$left));echo $chunk;$left-=strlen($chunk);}fclose($h);exit;
}

/* ---------------- what kind of screen ---------------- */

/** speak · read (poem/tongue twister to say) · do · pair · group · draw · look */
function l1_kind(array $a):string{
 $t=$a['type'];$m=$a['response_mode'];
 if($m==='drawing')return 'draw';
 if($t==='reading')return $m==='answer'?'read':'look';
 if($t==='physical')return 'do';
 if($t==='group'){$txt=$a['title'].' '.$a['prompt'];return preg_match('~\b(partner|pairs?|buddy|buddies|Student A|Ask a friend|your friend)\b~i',$txt)?'pair':'group';}
 if($m==='answer')return 'speak';
 return 'look';
}

function l1_phase_label(string $p):string{return ['pre'=>'Before we begin','learn'=>'Let’s practice','post'=>'Show what you learned'][$p]??'';}

/** "By the end of the lesson, pupils can: 1. … 2. …" becomes a list. */
function l1_goals(string $obj):array{
 $obj=trim($obj);if($obj==='')return [];
 if(preg_match_all('~(?:^|\s)\d{1,2}\.\s+~',$obj)>=2){$pos=strpos($obj,':');return array_values(array_filter(array_map(fn($x)=>rtrim(trim($x),' ;.'),preg_split('~(?:^|\s)\d{1,2}\.\s+~',$pos!==false&&$pos<160?substr($obj,$pos+1):$obj,-1,PREG_SPLIT_NO_EMPTY))));}
 return [$obj];
}

/** Lesson header: level and lesson name, "n / N complete" and one dot per activity. */
function lesson_head(array $lesson,array $all,array $a,string $back):string{
 $lv=lesson_level((int)$lesson['id']);$n=count($all);$done=count(array_filter($all,'completion_ok'));
 $bar='';if($n<=30){$bar='<div class="ap-seg" aria-hidden="true">';foreach($all as $x)$bar.='<i class="'.((int)$x['id']===(int)$a['id']?'c':(completion_ok($x)?'d':'')).'"></i>';$bar.='</div>';}else $bar='<progress max="'.$n.'" value="'.$done.'"></progress>';
 return '<header class="activity-header'.($n<=30?' has-seg':'').'"><a class="iconbutton" href="'.e($back).'" aria-label="Back to my lessons">'.icon('close').'</a><div><strong>'.e(level_label($lv).' · '.$lesson['subtitle']).'</strong><span>'.e($lesson['title']).'</span></div><div class="activity-progress"><span>'.$done.' / '.$n.' complete</span>'.$bar.'</div></header>';
}
function l1_icon(string $n):string{
 $p=['spk'=>'<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7"/><path d="M19 6a8.5 8.5 0 0 1 0 12"/>','mic'=>'<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
  'play'=>'<path d="M8 5v14l11-7z"/>','stop'=>'<rect x="7" y="7" width="10" height="10" rx="2"/>','x'=>'<path d="M6 6l12 12M18 6 6 18"/>','next'=>'<path d="M5 12h14M13 6l6 6-6 6"/>','prev'=>'<path d="M19 12H5M11 6l-6 6 6 6"/>',
  'ok'=>'<path d="M20 6 9 17l-5-5"/>','again'=>'<path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.6-7.4L4 10"/>','cam'=>'<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>','img'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/>',
  'pen'=>'<path d="M4 20h4L19 9l-4-4L4 16v4z"/>','ear'=>'<path d="M7 9a5 5 0 0 1 10 0c0 3-3 4-3 7a3 3 0 0 1-5 2"/><path d="M10 9a2 2 0 0 1 4 0"/>','teacher'=>'<circle cx="9" cy="7" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M15 4h6v6h-4"/>',
  'star'=>'<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>','thumb'=>'<path d="M7 10v10H4V10zM7 10l4-7a2 2 0 0 1 3 2l-1 5h6a2 2 0 0 1 2 2l-2 7a2 2 0 0 1-2 1H7"/>','smile'=>'<circle cx="12" cy="12" r="9"/><path d="M8 14a5 5 0 0 0 8 0M9 9h.01M15 9h.01"/>',
  'list'=>'<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>','full'=>'<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>','book'=>'<path d="M4 19V5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2zM8 7h6"/>'];
 return '<svg class="l1i" viewBox="0 0 24 24" aria-hidden="true">'.($p[$n]??'').'</svg>';
}

/** Speaker button that reads the text aloud (l1.js). */
function l1_speaker(string $text,string $label='Hear it again',string $cls=''):string{return '<button type="button" class="l1-spk '.$cls.'" data-say="'.e($text).'" aria-label="'.e($label).'" title="'.e($label).'">'.l1_icon('spk').'</button>';}

/** The activity's pictures (the same reviewed module pictures every level uses). */
function l1_pictures(array $a,bool $demo=false):string{
 $alts=($r=level1_visual_override($a))?array_column($r['images'],'alt','src'):[];$h='';$n=0;
 foreach(activity_images($a) as $img){$p=resolved_image_path((string)$img);if(!$p)continue;$n++;$h.='<figure class="l1-pic"><img '.($demo?'data-src':'src').'="'.e($p).'" alt="'.e($alts[$img]??('Picture for '.$a['title'])).'"'.($demo?'':' loading="eager"').'></figure>';}
 return $n?'<div class="l1-pics n'.min($n,3).($n>3?' many':'').'">'.$h.'</div>':'';
}

/** The question text, with sentence-starter blanks shown as a yellow gap. */
function l1_question_html(array $a):string{
 $t=e(trim((string)$a['prompt']));
 if($a['type']==='sentence'){$gap='<span class="l1-gap" aria-label="your words">?</span>';
  $t=preg_replace('~\[[^\]]{1,30}\]|_{3,}|…|\.\.\.~u',$gap,$t,1,$n);if(!$n)$t.=' '.$gap;}
 return nl2br($t);
}

/** Words of a poem or tongue twister, each in its own span so it can light up while it is read. */
function l1_words_html(string $text):string{
 $out='';foreach(preg_split('~(\s+)~u',trim($text),-1,PREG_SPLIT_DELIM_CAPTURE) as $p){if($p==='')continue;$out.=preg_match('~^\s+$~u',$p)?(str_contains($p,"\n")?'<br>':' '):'<span class="w">'.e($p).'</span>';}return $out;
}

/** Steps under a "do it" card. Lessons 2 and 3 are about directions, so the pupil says them back first. */
function l1_steps(int $position):array{return in_array($position,[2,3],true)?['Listen','Say it back','Do it!']:['Listen','Do it!','Show your teacher'];}

function l1_tag(array $a,string $kind):string{
 if($kind==='do')return '';/* the mission card has its own label */
 if($kind==='pair')return 'WORK WITH A PARTNER';if($kind==='group')return 'WORK WITH YOUR GROUP';
 if($a['type']==='sentence')return 'FINISH THE SENTENCE';
 if($a['phase']!=='learn')return $kind==='do'?'LISTEN AND DO':($kind==='draw'?'LISTEN AND DRAW':'');
 return mb_strtoupper(preg_replace('~\s*·.*$~u','',(string)$a['title']));
}

/* ---------------- pupil screens ---------------- */

/** Lesson start: what we will learn today, the number of activities in each part, then one big Start button. */
/** The same opening screen for Levels 3 to 7: picture, lesson name, goals, parts and Let's start. */
function lesson_start_page(array $lesson,array $all,array $first):void{
 $lid=(int)$lesson['id'];$lv=lesson_level($lid);$count=['pre'=>0,'learn'=>0,'post'=>0];foreach($all as $x)if(isset($count[$x['phase']]))$count[$x['phase']]++;
 head(level_label($lv).' · '.$lesson['subtitle'],'activity-page l1-page');
 echo '<div class="l1-app l1-start-app" data-l1-start>'.lesson_head($lesson,$all,$first,'?page=lessons&level='.$lv.'#level'.$lv.'-path').'<main class="l1-main l1-start activity-main">';
 echo '<div class="l1-start-in"><div class="l1-start-top"><img class="l1-cover" src="assets/images/covers/level-'.min(8,max(1,$lv)).'.webp" alt=""><div><span class="l1-tag">LESSON '.(int)$lesson['position'].' · '.e(mb_strtoupper((string)$lesson['subtitle'])).'</span><h1>'.e($lesson['title']).'</h1></div></div>';
 $goals=l1_goals((string)$lesson['objectives']);
 if($goals){echo '<div class="l1-goals"><p class="l1-lab">TODAY YOU WILL…'.l1_speaker(implode('. ',$goals),'Hear what you will learn','sm').'</p><ul>';foreach($goals as $g)echo '<li>'.e($g).'</li>';echo '</ul></div>';}
 echo '<div class="l1-steps">';foreach($count as $p=>$n)if($n)echo '<span><b>'.$n.'</b>'.e(l1_phase_label($p)).'</span>';echo '</div>';
 echo '<a class="l1-big l1-go" href="?page=lesson&amp;id='.$lid.'&amp;activity='.(int)$first['id'].'">Let’s start! '.l1_icon('next').'</a></div></main></div>';foot();
}
function l1_start_page(array $lesson,array $all,array $first):void{
 $lid=(int)$lesson['id'];$count=['pre'=>0,'learn'=>0,'post'=>0];foreach($all as $x)$count[$x['phase']]++;
 head('Level 1 · '.$lesson['subtitle'],'activity-page l1-page');
 echo '<div class="l1-app l1-start-app" data-l1-start>'.lesson_head($lesson,$all,$first,'?page=lessons&level=1#level1-path').'<main class="l1-main l1-start activity-main">';
 echo '<div class="l1-start-in"><div class="l1-start-top"><img class="l1-cover" src="assets/images/covers/level-1.webp" alt=""><div><span class="l1-tag">LESSON '.(int)$lesson['position'].' · '.e(mb_strtoupper((string)$lesson['subtitle'])).'</span><h1>'.e($lesson['title']).'</h1></div></div>';
 $goals=l1_goals((string)$lesson['objectives']);
 if($goals){echo '<div class="l1-goals"><p class="l1-lab">TODAY YOU WILL…'.l1_speaker(implode('. ',$goals),'Hear what you will learn','sm').'</p><ul>';foreach($goals as $g)echo '<li>'.e($g).'</li>';echo '</ul></div>';}
 echo '<div class="l1-steps">';foreach($count as $p=>$n)if($n)echo '<span><b>'.$n.'</b>'.e(l1_phase_label($p)).'</span>';echo '</div>';
 echo '<a class="l1-big l1-go" href="?page=lesson&amp;id='.$lid.'&amp;activity='.(int)$first['id'].'">Let’s start! '.l1_icon('next').'</a></div></main></div>';foot();
}

/** One Level 1 activity screen. Uses the same form, saving and offline queue as every other level. */
function l1_activity_page(array $u,array $lesson,array $all,array $a):void{
 $pid=(int)$u['id'];$lid=(int)$lesson['id'];$kind=l1_kind($a);$mode=$a['response_mode'];if($mode==='drawing'&&!draw_allowed($a))$mode='answer';$readonly=completion_ok($a);
 $c=$a['completion_id']?one('SELECT id,audio_path,drawing FROM activity_completion WHERE id=?',[(int)$a['completion_id']]):null;
 $phase=array_values(array_filter($all,fn($x)=>$x['phase']===$a['phase']));$at=0;foreach($phase as $i=>$x)if((int)$x['id']===(int)$a['id'])$at=$i+1;
 $doneAll=count(array_filter($all,fn($x)=>completion_ok($x)));$say=trim((string)($a['narration']?:$a['prompt']));$tag=l1_tag($a,$kind);$pics=l1_pictures($a);
 head('Level 1 · '.$lesson['subtitle'],'activity-page l1-page');
 echo '<div class="l1-app l1-k-'.$kind.'">'.lesson_head($lesson,$all,$a,'?page=lessons&level=1#level1-path');
 echo '<main class="l1-main activity-main">';notice();
 echo '<form method="post" id="activity-form" class="activity-form l1-form" data-mode="'.e($mode).'" data-kind="'.$kind.'" data-draft="'.(!$readonly&&$kind==='draw'?'on':'off').'">'.csrf_field().'<input type="hidden" name="action" value="submit"><input type="hidden" name="activity_id" value="'.$a['id'].'"><input type="hidden" name="transcript" id="transcript" value=""><input type="hidden" name="drawing" id="drawing-data" value="'.e($kind==='draw'?(string)($a['drawing']??''):'').'"><input type="hidden" name="audio" id="l1-audio" value=""><input type="hidden" name="heard" id="l1-heard" value=""><span id="draft-status" hidden></span>';
 if($a['feedback'])echo '<div class="l1-note">'.l1_icon('teacher').'<div><b>A note from your teacher</b><p>'.e($a['feedback']).'</p></div></div>';
 if($tag!=='')echo '<span class="l1-tag">'.e($tag).'</span>';
 if($kind==='do'){
  echo '<div class="l1-mission"><span class="l1-mtag">'.(preg_match('~mission~i',$a['title'])?'MISSION CARD':e(mb_strtoupper($a['phase']==='learn'?(string)$a['title']:'Listen and do'))).'</span><p>'.nl2br(e($a['prompt'])).'</p>'.l1_speaker($say).'</div>'.$pics;
  echo '<div class="l1-howto">';foreach(l1_steps((int)$lesson['position']) as $i=>$s)echo '<span><b>'.($i+1).'</b>'.e($s).'</span>';echo '</div>';
 }elseif($kind==='pair'||$kind==='group'){
  echo '<div class="l1-pair">'.($kind==='pair'?'<div><img src="'.e(char_src('girl')).'" alt=""><b>A</b><small>Takes a turn first</small></div><div><img src="'.e(char_src('boy')).'" alt=""><b>B</b><small>Listens, then takes a turn</small></div>':'<div><img src="'.e(char_src('girl')).'" alt=""><small>Take turns</small></div><div><img src="'.e(char_src('boy')).'" alt=""><small>Listen to your friends</small></div>').'</div>';
  echo '<div class="l1-q sm"><p>'.nl2br(e($a['prompt'])).'</p>'.l1_speaker($say).'</div>'.$pics;
 }elseif($kind==='draw'){
  echo '<div class="l1-listen">'.l1_speaker($say,'Listen to the description','big').'<div><b>Listen to the description</b><small>Tap to hear it again</small></div></div><details class="l1-words"><summary>Show the words</summary><p>'.nl2br(e($a['prompt'])).'</p></details>'.$pics;
  $photo=str_starts_with((string)($a['drawing']??''),'data:image/jpeg');$drawn=str_starts_with((string)($a['drawing']??''),'data:image/png');
  if($readonly){if($a['drawing'])echo '<div class="l1-prev"><img src="'.e($a['drawing']).'" alt="Your drawing"><span class="l1-ok">'.l1_icon('ok').'Your drawing</span></div>';}
  else{
   echo '<div class="draw-choice l1-dchoice" role="group" aria-label="How do you want to send your drawing?"><button type="button" class="dc-tab'.($drawn?'':' on').'" data-dc="photo" aria-pressed="'.($drawn?'false':'true').'">'.l1_icon('cam').'Upload a photo</button><button type="button" class="dc-tab'.($drawn?' on':'').'" data-dc="draw" aria-pressed="'.($drawn?'true':'false').'">'.l1_icon('pen').'Draw in BULIG</button></div>';
   echo '<div class="photo-panel l1-up"'.($drawn?' hidden':'').'><div class="pp-empty"'.($photo?' hidden':'').'><span class="l1-camb">'.l1_icon('cam').'</span><b>Upload your drawing</b><small>Draw on paper, then take a photo of it or choose a picture.</small><label class="l1-big pp-btn">'.l1_icon('cam').'Take a photo<input type="file" accept="image/*" capture="environment" class="pp-input"></label><label class="l1-btn2 pp-btn">'.l1_icon('img').'Choose a picture<input type="file" accept="image/*" class="pp-input"></label></div>';
   echo '<div class="pp-done"'.($photo?'':' hidden').'><div class="l1-prev pp-photo"><img class="pp-img" src="'.($photo?e($a['drawing']):'').'" alt="Photo of your drawing"><span class="l1-ok">'.l1_icon('ok').'Your drawing</span></div><label class="l1-btn2 pp-btn">'.l1_icon('cam').'Change photo<input type="file" accept="image/*" class="pp-input"></label></div><p class="pp-status" role="status"></p></div>';
   echo '<div class="drawing-panel l1-canvas"'.($drawn?'':' hidden').'><div class="drawing-toolbar"><strong>Your drawing</strong><label>Color <input type="color" id="pen-color" value="#217c4b"></label><button class="btn quiet" type="button" id="clear-drawing">Clear my marks</button></div><div class="worksheet-scroll"><div class="worksheet-surface"><canvas id="drawing-canvas" width="900" height="600" aria-label="Drawing area"></canvas></div></div></div>';
  }
 }elseif($kind==='read'){
  echo $pics.'<div class="l1-tw" data-words>'.l1_words_html((string)$a['prompt']).'</div><div class="l1-speed"><button type="button" class="l1-chip" data-say-words="0.6">'.l1_icon('spk').'Slow</button><button type="button" class="l1-chip" data-say-words="1.05">'.l1_icon('spk').'Fast</button></div>';
 }else{
  $q=$kind==='look'&&mb_strlen((string)$a['prompt'])>140;
  echo '<div class="l1-q'.($q?' sm':'').($a['type']==='sentence'?' st':'').'"><p>'.l1_question_html($a).'</p>'.l1_speaker($say).'</div>';
  echo $pics!==''?$pics:($kind==='speak'?'<img class="l1-buddy" src="'.e(char_src(pf_is_girl()?'girl':'boy')).'" alt="">':'');
 }
 /* bottom: recorder, or one big button */
 $speakHere=in_array($kind,['speak','read'],true)&&$mode==='answer';
 echo '<div class="l1-end">';
 if($readonly){
  echo '<div class="l1-done">'.l1_icon('ok').'<b>'.($kind==='look'?'You saw this page.':($kind==='draw'?'Your drawing is saved.':($speakHere?'Your answer is saved.':'Well done!'))).'</b></div>';
  if($speakHere&&$c&&$c['audio_path'])echo '<div class="l1-mine"><span>Your answer</span><audio controls preload="none" src="?page=recording&amp;id='.(int)$c['id'].'"></audio></div>';
 }elseif($speakHere){
  echo '<div class="l1-rec" data-l1-rec><button type="button" class="l1-mic" data-rec-go aria-label="Tap and say your answer">'.l1_icon('mic').'</button><b class="l1-rl" data-rec-label>'.($kind==='read'?'Now you say it!':'Tap and say your answer').'</b><small class="l1-rs" data-rec-sub role="status"></small>'
   .'<div class="l1-play" data-rec-play hidden><button type="button" class="l1-pbtn" data-rec-listen aria-label="Play my answer">'.l1_icon('play').'</button><span class="l1-bar"><i></i></span><span class="l1-len" data-rec-len></span></div>'
   .'<button type="button" class="l1-heard" data-rec-heard hidden>'.l1_icon('ok').'Done</button></div>';
 }
 echo '<div id="answer-feedback" role="status" aria-live="polite" class="answer-feedback l1-fb"></div><div class="l1-btns">';
 if(!$readonly){
  if($speakHere)echo '<button type="button" class="l1-btn2" data-rec-again hidden>'.l1_icon('again').'Try again</button><button id="submit-answer" class="l1-big" type="submit" hidden>Send '.l1_icon('next').'</button>';
  else echo '<button id="submit-answer" class="l1-big" type="submit">'.($kind==='do'?'I did it! '.l1_icon('ok'):(in_array($kind,['pair','group'],true)?'We did it! '.l1_icon('ok'):($kind==='draw'?'Send my drawing '.l1_icon('ok'):'Next '.l1_icon('next')))).'</button>';
 }
 $last=$doneAll===count($all);
 echo '<a id="next-activity" class="l1-big" href="?page=lesson&amp;id='.$lid.'"'.($readonly?'':' hidden').'>'.($last?'Finish lesson':'Next').' '.l1_icon('next').'</a></div></div></form></main></div>';
 foot();
}

/* ---------------- Class Demo (projector) ---------------- */

/** Level 1 Class Demo: a lesson start slide, then one slide per activity. Fits 1366×768 with no scrolling; Next only. */
function l1_demo_view(array $u,array $slides):void{
 $by=[];foreach($slides as $a)$by[(int)$a['lesson_id']][]=$a;
 $lessons=[];foreach(rows('SELECT * FROM lessons WHERE id IN ('.implode(',',array_keys($by)).') ORDER BY position') as $l)$lessons[(int)$l['id']]=$l;
 $deck=[];foreach($lessons as $lid=>$l){$deck[]=['start'=>true,'lesson'=>$l,'id'=>0];foreach($by[$lid] as $a)$deck[]=['start'=>false,'lesson'=>$l,'a'=>$a,'id'=>(int)$a['id']];}
 $index=0;$want=(int)($_GET['activity']??0);if($want){foreach($deck as $i=>$d)if($d['id']===$want){$index=$i;break;}}elseif(isset($_GET['lesson'])){foreach($deck as $i=>$d)if($d['start']&&(int)$d['lesson']['id']===(int)$_GET['lesson']){$index=$i;break;}}
 head('Class Demo · Level 1','demo-page l1-demo-page');
 echo '<div id="l1-demo" class="l1-demo" data-start="'.$index.'"><header class="l1d-top"><a class="l1d-logo" href="?page=class_demo"><img src="assets/bulig-logo.png" alt="BULIG"></a><div class="l1d-ln"><b data-d-lesson></b><small data-d-part></small></div><div class="l1d-prog" aria-hidden="true"><i data-d-prog></i></div>';
 echo '<label class="l1d-sel"><span class="sr-only">Go to a lesson</span><select id="demo-lesson" data-d-lessons>';foreach($deck as $i=>$d)if($d['start'])echo '<option value="'.$i.'">Lesson '.(int)$d['lesson']['position'].' · '.e($d['lesson']['subtitle']).'</option>';echo '</select></label><select id="demo-jump" hidden aria-hidden="true" tabindex="-1">';foreach($deck as $i=>$d)echo '<option value="'.$i.'"></option>';echo '</select>';
 echo '<button type="button" class="l1d-tb" data-plan-open aria-haspopup="dialog">'.l1_icon('book').'Lesson plan</button><button type="button" class="l1d-tb" data-cd-open>'.l1_icon('ok').'Mark as done</button><button type="button" class="l1d-tb" data-d-full>'.l1_icon('full').'<span>Full screen</span></button><a class="l1d-tb" href="?page=class_demo">'.l1_icon('x').'Exit</a></header>';
 if(isset($_SESSION['flash'])){echo '<div class="notice demo-notice" role="status">'.icon('check').'<span>'.e($_SESSION['flash']).'</span></div>';unset($_SESSION['flash']);}
 $plain=array_map(fn($d)=>$d['a']??null,$deck);
 echo class_done_demo_panel($u,1,0,'Level 1').demo_lesson_plans(array_values(array_filter($plain)),1,0,'Level 1');
 echo '<main class="l1d-stage" data-d-stage tabindex="-1" aria-live="polite" aria-label="Class slide"></main>';
 echo '<footer class="l1d-bot"><button type="button" class="l1d-nb" data-d-prev>'.l1_icon('prev').'Previous</button><span class="l1d-cnt" data-d-count role="status"></span><button type="button" class="l1d-lis" data-d-listen aria-pressed="false">'.l1_icon('spk').'<span>Listen</span></button><button type="button" class="l1d-nb g" data-d-next>Next '.l1_icon('next').'</button></footer>';
 foreach($deck as $i=>$d){$l=$d['lesson'];
  if($d['start']){$goals=l1_goals((string)$l['objectives']);
   echo '<template class="l1d-t demo-template" data-lesson="'.(int)$l['id'].'" data-id="'.(int)$by[(int)$l['id']][0]['id'].'" data-title="Lesson '.(int)$l['position'].' · '.e($l['subtitle']).'" data-part="Lesson start" data-say="'.e($l['title'].'. Today we will: '.implode('. ',$goals)).'"><span class="demo-slide-meta" hidden>Lesson '.(int)$l['position'].' · '.e($l['subtitle']).' · Lesson start</span><div class="l1d-two l1d-start"><img class="l1d-cover" data-src="assets/images/covers/level-1.webp" alt=""><div><span class="l1-tag">LESSON '.(int)$l['position'].' · '.e(mb_strtoupper((string)$l['subtitle'])).'</span><h1>'.e($l['title']).'</h1>';
   if($goals){echo '<p class="l1-lab">TODAY WE WILL…</p><ul>';foreach($goals as $g)echo '<li>'.e($g).'</li>';echo '</ul>';}
   echo '</div></div></template>';continue;}
  $a=$d['a'];$kind=l1_kind($a);$pics=l1_pictures($a,true);$tag=l1_tag($a,$kind);$say=trim((string)($a['narration']?:$a['prompt']));
  $txt=(string)$a['prompt'];$long=mb_strlen($txt)>260?' xl':(mb_strlen($txt)>130?' l':'');
  echo '<template class="l1d-t demo-template" data-lesson="'.(int)$l['id'].'" data-id="'.(int)$a['id'].'" data-title="Lesson '.(int)$l['position'].' · '.e($l['subtitle']).'" data-part="'.e(l1_phase_label($a['phase']).($a['phase']==='learn'?' · '.$a['title']:'')).'" data-say="'.e($say).'"><span class="demo-slide-meta" hidden>Lesson '.(int)$l['position'].' · '.e($l['subtitle']).' · '.e(l1_phase_label($a['phase'])).'</span><h1 class="sr-only">'.e($a['title']).'</h1><div class="l1d-'.($pics?'two':'one').' l1d-k-'.$kind.$long.'"><div class="l1d-txt">'.($tag!==''?'<span class="l1-tag">'.e($tag).'</span>':'');
  if($kind==='do'){echo '<div class="l1-mission"><span class="l1-mtag">'.(preg_match('~mission~i',$a['title'])?'MISSION CARD':'LISTEN AND DO').'</span><p>'.nl2br(e($txt)).'</p><div class="l1-howto">';foreach(l1_steps((int)$l['position']) as $k=>$st)echo '<span><b>'.($k+1).'</b>'.e($st).'</span>';echo '</div></div>';}
  elseif($kind==='read')echo '<div class="l1-tw" data-words>'.l1_words_html($txt).'</div><small class="l1d-say">Say it together: slowly first, then faster</small>';
  elseif($kind==='pair'||$kind==='group')echo '<p class="l1d-q">'.nl2br(e($txt)).'</p><small class="l1d-say">'.($kind==='pair'?'Pupils work with a partner':'Pupils work in small groups').'</small>';
  elseif($kind==='draw')echo '<p class="l1d-q">'.nl2br(e($txt)).'</p><small class="l1d-say">Pupils listen, then draw</small>';
  else echo '<p class="l1d-q">'.l1_question_html($a).'</p>'.($kind==='speak'?'<small class="l1d-say">Pupils answer out loud</small>':'');
  echo '</div>'.$pics.'</div></template>';
 }
 echo '</div>';foot();
}

/** Older databases (before 020_level1_speak.sql) have no place for recordings or rubric scores. */
function l1_audio_supported():bool{static $s;if($s===null){try{$s=(bool)one("SHOW COLUMNS FROM activity_completion LIKE 'audio_path'");}catch(Throwable $e){$s=false;}}return $s;}

/* ---------------- Activity history (teacher) ---------------- */

/** The pupil's recording, ready to play. */
function l1_review_media(array $r):string{
 $key=array_column(rows("SELECT an.content FROM answers an JOIN questions q ON q.id=an.question_id WHERE q.activity_id=? AND an.correct=1",[(int)$r['activity_id']]),'content');
 $k=$key?'<p class="rv-l1-key">'.l1_icon('ok').'Module answer: <b>'.e(implode(' / ',$key)).'</b></p>':'';
 return $k.l1_review_media_only($r);
}
function l1_review_media_only(array $r):string{
 if(empty($r['audio_path']))return str_starts_with((string)$r['response'],'Answered out loud')?'<p class="rv-l1-heard">'.l1_icon('ear').'Answered out loud, marked Done (no recording).</p>':'';
 return '<div class="rv-l1-audio"><span>'.l1_icon('mic').'Pupil’s recording</span><audio controls preload="metadata" src="?page=recording&amp;id='.(int)$r['id'].'"></audio></div>';
}

/** The module rubric (or lesson-plan checklist) for scoring one answer. */
function l1_rubric_form(int $position,array $r):string{
 $rub=l1_rubric($position);if(!$rub||!l1_audio_supported())return '';$s=json_decode((string)($r['rubric_scores']??''),true);$v=is_array($s)&&isset($s['v'])?array_map('intval',$s['v']):[];$n='rubric';$id='rb'.(int)$r['id'];
 $h='<fieldset class="rv-rub"><legend>'.l1_icon('list').'Score with the module · '.e($rub['title']).'</legend>';
 if($rub['kind']==='rubric'){
  foreach($rub['criteria'] as $i=>[$name,$cells]){$h.='<div class="rv-rub-row" role="radiogroup" aria-label="'.e($name).'"><b>'.e($name).'</b><div class="rv-rub-cells">';
   foreach($cells as $pts=>$desc)$h.='<label class="rv-rub-cell"><input type="radio" name="'.$n.'['.$i.']" value="'.$pts.'"'.(($v[$i]??0)===$pts?' checked':'').'><span><strong>'.$pts.' · '.e(L1_SCALE[$pts]).'</strong><small>'.e($desc).'</small></span></label>';
   $h.='</div></div>';}
  $h.='<p class="rv-rub-total" data-rub-total="'.(4*count($rub['criteria'])).'">'.($v?'Total: '.array_sum($v).' / '.(4*count($rub['criteria'])):'Choose one box in each row.').'</p>';
 }elseif($rub['kind']==='check'){
  $h.='<div class="rv-rub-checks">';foreach($rub['items'] as $i=>$item)$h.='<label class="rv-rub-check"><input type="checkbox" name="'.$n.'['.$i.']" value="1"'.(!empty($v[$i])?' checked':'').'><span>'.e($item).'</span></label>';
  $h.='</div><p class="rv-rub-note">The module has no number rubric for this lesson. This is the checklist from its lesson plan.</p>';
 }else{
  $h.='<div class="rv-rub-scale" role="radiogroup">';foreach($rub['options'] as $k=>[$label,$desc])$h.='<label class="rv-rub-cell"><input type="radio" name="'.$n.'[0]" value="'.$k.'"'.(($v[0]??0)===$k?' checked':'').'><span>'.l1_icon(['Star'=>'star','Thumbs up'=>'thumb','Smile'=>'smile'][$label]).'<strong>'.e($label).'</strong><small>'.e($desc).'</small></span></label>';
  $h.='</div><p class="rv-rub-note">From the lesson plan’s observation guide (the module has no rubric for this lesson).</p>';
 }
 return $h.'<input type="hidden" name="'.$n.'[_]" value="1"></fieldset>';
}
