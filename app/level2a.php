<?php
/* Level 2A (Phonological Awareness): the same clean screens as Level 1.
   - Each card is answered the way the module asks (database/level2a-ui.json, made by tools/level2a/build_ui.py):
     write → letter tiles, encircle/check a picture → tap it, count → numbers, check words → word chips, say/tell → microphone.
   - Words, pictures and directions come from database/level2-cards.json (checked against the module).
   - Class Demo: a lesson start slide, then one slide per card. */

/* Level 2A (level id 2) and Level 2B (level id 3) share these screens. */
function l2a_ui():array{static $u=null;if($u===null){$u=['lessons'=>[],'pages'=>[]];foreach(['2a','2b'] as $k){$f=__DIR__.'/../database/level'.$k.'-ui.json';$j=is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[];$u['lessons']+=$j['lessons']??[];foreach($j['pages']??[] as $pk=>$pv)$u['pages'][$k.':'.$pk]=$pv;}}return $u;}
function l2a_is(int $lid):bool{return in_array(lesson_level($lid),[2,3,4],true);}
function l2a_lesson_info(int $lid):?array{return l2a_ui()['lessons'][(string)$lid]??null;}
function l2a_code(int $level):string{return $level===8?'7':($level===7?'6':($level===6?'5':($level===5?'4':($level===4?'3':($level===3?'2B':'2A')))));}
function l2a_key(array $a):string{$k=(lesson_level((int)$a['lesson_id'])===3?'2b':'2a').':'.(int)$a['source_page'];if($k==='2b:42'&&level2_activity_context($a)&&(int)level2_activity_context($a)['position']===12)$k.=':continuation';return $k;}
function l2a_set(array $a):?array{if(lesson_level((int)$a['lesson_id'])===4)return l3_set($a);if(lesson_level((int)$a['lesson_id'])===6)return l5_set($a);if(in_array(lesson_level((int)$a['lesson_id']),[7,8],true))return l6_set($a);$s=level2_card_set($a);if(!$s)return null;$u=l2a_ui()['pages'][l2a_key($a)]??['title'=>$s['title'],'cards'=>[]];$s['module_title']=$u['title'];foreach($s['cards'] as $i=>&$c){$c['ui']=($u['cards'][$i]??[])+['answer'=>['tiles']];if(isset($c['ui']['text']))$c['text']=$c['ui']['text'];if(isset($c['ui']['choices']))$c['choices']=$c['ui']['choices'];}unset($c);return $s;}
/* ---------------- Level 3 (Word Recognition, level id 4): the same card screens ----------------
   Titles and directions come from database/level3-ui.json (the module's own words, made by tools/level3/build_ui.py).
   Word lists and sentences are read aloud into the microphone; pictures are named, words are chosen, letters are written. */
function l3_ui():array{static $u=null;if($u===null){$f=__DIR__.'/../database/level3-ui.json';$u=is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[];}return $u;}
function l3_key(array $a):string{static $p=[];$lid=(int)$a['lesson_id'];if(!isset($p[$lid]))$p[$lid]=(int)val('SELECT position FROM lessons WHERE id=?',[$lid]);return $p[$lid].':'.$a['phase'].':'.(int)$a['position'];}
/** Words or lines of a list: "bag   rag   fan" or one line each. */
function l3_list(string $t):array{$out=[];foreach(preg_split('~\R~u',$t) as $line){$line=trim(preg_replace('~^\s*Words:\s*~i','',$line));if($line==='')continue;foreach(preg_split('~\s{2,}~u',$line) as $w)if(trim($w)!=='')$out[]=trim($w);}return $out;}
/** How a Level 3 card is answered when the ui file does not say. */
function l3_card_ui(array $s,array $c):array{
 $t=trim((string)$c['text']);$ch=$c['choices']??[];$n=count($c['images']??[]);$first=trim((string)strtok($t,"\n"));
 if(str_contains($t,'Which picture shows'))return ['answer'=>['pick'],'text'=>$first];
 if(str_contains($t,'Write the word'))return ['answer'=>['tiles'],'text'=>$first];
 if(str_contains($t,'Which number matches'))return ['answer'=>['num'],'max'=>10,'text'=>''];
 if($ch){$row=preg_split('~\s+~u',trim(preg_replace('~\bor\b~u',' ',$t)),-1,PREG_SPLIT_NO_EMPTY);$same=$row===array_values($ch);
  return ['answer'=>['chip']]+($same||str_starts_with($t,'Which word matches')?['text'=>'']:[]);}
 if(str_contains($t,'_'))return ['answer'=>['tiles']];
 if($n&&$t==='')return ['answer'=>[stripos((string)$s['title'],'build')!==false?'tiles':'say']];
 return ['answer'=>['say']];
}
function l3_set(array $a):?array{
 $u=l3_ui()['pages'][l3_key($a)]??[];$s=level2_card_set($a);$type=(string)$a['type'];
 if(!$s){$img=[];foreach(activity_images($a) as $i)$img[]=['src'=>$i,'alt'=>'Picture for '.$a['title'],'label'=>''];
  if(!empty($u['sort'])){$cards=[];foreach($u['sort']['words'] as $i=>$w)$cards[]=['title'=>(string)($i+1),'text'=>$w,'images'=>[],'choices'=>$u['sort']['cols'],'ui'=>['answer'=>['chip']]];$s=['title'=>$a['title'],'instruction'=>'','cards'=>$cards];}
  elseif(!empty($u['grid'])){$s=['title'=>$a['title'],'instruction'=>'','cards'=>[['title'=>'1','text'=>implode("\n",$u['grid']),'images'=>$img,'choices'=>[],'ui'=>['answer'=>['say'],'grid'=>$u['grid']]]]];}
  elseif($type==='reading'&&!preg_match('~\s{2,}|\R~u',trim((string)$a['prompt']))&&$img){$s=['title'=>$a['title'],'instruction'=>(string)$a['prompt'],'cards'=>[['title'=>'1','text'=>'','images'=>$img,'choices'=>[],'ui'=>['answer'=>['say']]]]];}
  elseif($type==='reading'){$w=l3_list((string)$a['prompt']);$s=['title'=>$a['title'],'instruction'=>'Read the following words correctly.','cards'=>[['title'=>'1','text'=>implode("\n",$w),'images'=>$img,'choices'=>[],'ui'=>['answer'=>['say'],'grid'=>$w]]]];}
  else $s=['title'=>$a['title'],'instruction'=>(string)$a['prompt'],'cards'=>[['title'=>'1','text'=>'','images'=>$img,'choices'=>[],'ui'=>['answer'=>[$type==='reference'?($u['say']??false?'say':'look'):'paper']]]]];}
 if(isset($u['instruction']))$s['instruction']=$u['instruction'];
 $bank=[];if(preg_match('~^(.*?)\s*Words:\s*(.+)$~su',(string)$s['instruction'],$m)&&preg_match_all('~\d+\.\s*[^\d]+?(?=\s+\d+\.|$)~u',trim($m[2]),$mm)){$s['instruction']=trim($m[1]);$bank=array_map('trim',$mm[0]);}
 $s['module_title']=$u['title']??$s['title'];
 foreach($s['cards'] as $i=>&$c){$c['ui']=($u['cards'][$i]??[])+($c['ui']??[])+(!empty($u['kind'])?['answer'=>[$u['kind']]]:[])+l3_card_ui($s,$c);
  if(!empty($u['notext'])&&!empty($c['images']))$c['ui']['text']='';
  if(isset($c['ui']['text']))$c['text']=$c['ui']['text'];if(isset($c['ui']['choices']))$c['choices']=$c['ui']['choices'];
  if($bank&&in_array('num',$c['ui']['answer'],true)&&empty($c['words'])){$c['words']=$bank;$c['words_label']='Words';}}
 unset($c);return $s;
}
/* ---------------- Level 5 (Listening Comprehension and Vocabulary, level id 6; one module per grade) ----------------
   The cards come from database/level5-cards.json (every numbered item of each module page). How each card is answered:
   a), b), c) choices -> tap one; (True/False) or (word, word) -> tap one; matching Column A to B -> tap an item, then its match: a line joins them;
   blanks and words to write -> letter tiles; stories and teacher notes -> read or listen; puzzles -> done on paper. */
function l5_set(array $a):?array{
 $s=level2_card_set($a);if(!$s)return null;$s['module_title']=$s['title'];$out=[];
 /* No module direction for this page: show none (not the app-written default). */
 if(trim((string)$s['instruction'])===trim((string)$a['instructions']))$s['instruction']='';
 /* A word box on the page: its blanks are answered by tapping a word from the box. */
 $bank=[];foreach($s['cards'] as $c)if(!empty($c['words'])&&($c['title']??'')==='Read')$bank=array_merge($bank,$c['words']);
 foreach($s['cards'] as $c){$t=trim((string)($c['text']??''));$title=(string)($c['title']??'');$ch=$c['choices']??[];
  if($title==='Matching'){
   /* The module's matching board (as before): tap an item in Column A, then its match in Column B, and a line joins them. */
   $say="Column A. ".preg_replace('~^\s*Column A\s*~u','',$t)."\nColumn B. ".implode('. ',$ch);
   $c['match']=$c;$c['text']='';$c['images']=[];$c['words']=[];$c['choices']=[];$c['speak']=$say;$c['ui']=['answer'=>['match']];
  }elseif($ch){$c['ui']=['answer'=>['chip']];}
  elseif($title==='Read'||$title==='Puzzle'&&$t===''){$c['ui']=['answer'=>[$title==='Puzzle'?'paper':'look']];}
  elseif(preg_match('~\((True/False)\)~i',$t)){$c['choices']=['True','False'];$c['ui']=['answer'=>['chip']];}
  elseif(preg_match('~happy face.*sad face~i',$t)){$c['choices']=['😊 Yes','☹ No'];$c['ui']=['answer'=>['chip']];}
  elseif(preg_match('~\(([a-z][a-z ]{0,20}),\s*([a-z][a-z ]{0,20})\)~i',$t,$m)&&!str_contains($t,'_')){$c['choices']=[trim($m[1]),trim($m[2])];$c['ui']=['answer'=>['chip']];}
  elseif($title==='Puzzle'||preg_match('~^(Write each|Find |Draw )~i',$t)){$c['ui']=['answer'=>['paper']];}
  elseif($bank&&str_contains($t,'__')){$c['choices']=array_values(array_unique($bank));$c['ui']=['answer'=>['chip']];}
  else{$c['ui']=['answer'=>['tiles']];}
  $out[]=$c;}
 $s['cards']=$out;return $s;
}
/* ---------------- Level 6 (Graded Reading Comprehension, level id 7; one module per grade) ----------------
   The cards come from database/level6-cards.json (each story and every numbered item, in the module's words). How each card is answered:
   a. b. c. choices, check-box conclusions, Reality or Fantasy -> tap one; "write 1, 2, 3, 4, 5 before each event" -> a number for each event;
   cause and effect matching -> tap a cause, then its effect: a line joins them; "draw ..." -> done on paper; "write / copy ..." -> type it;
   the story -> read (reading-speed exercises: read it aloud into the microphone). The page's "Vocabulary" or "Key words" sit under the story. */
function l6_set(array $a):?array{
 $s=level2_card_set($a);if(!$s)return null;$s['module_title']=$s['title'];$out=[];$l7=lesson_level((int)$a['lesson_id'])===8;
 /* Every Level 6 direction is the module's own: always shown. Level 7 Grades 1-2 print no direction: the app's default is not shown. */
 if($l7&&trim((string)$s['instruction'])==='Read the story carefully, then answer the questions.')$s['instruction']='';
 $aloud=str_contains((string)$s['instruction'],'aloud as quickly and correctly');$vocab=$s['vocab']??null;
 foreach($s['cards'] as $c){$t=trim((string)($c['text']??''));$title=(string)($c['title']??'');$ch=$c['choices']??[];
  if($title==='Read'&&$t===''&&empty($c['images']))continue;
  $h=trim((string)($c['heading']??''));if($h!==''&&!preg_match('~^(Pre|Post)-?test$~i',$h))$c['story']=$h;
  if(trim((string)($c['lead']??''))!=='')$c['text']=trim($c['lead'])."\n".$c['text'];
  if($title==='Matching'){
   $say="Column A. ".preg_replace('~^\s*Column A\s*~u','',$t)."\nColumn B. ".implode('. ',$ch);
   $m=$c;$m['text']=$t;$c['match']=$m;$c['text']=trim((string)($c['lead']??''));$c['images']=[];$c['words']=[];$c['choices']=[];$c['speak']=$say;$c['ui']=['answer'=>['match']];
  }elseif($title==='Order'){
   /* Each event gets the numbers 1 to 5 (the module: "write 1, 2, 3, 4, 5 before each event"). */
   $n=range(1,max(2,count($ch)));$n=array_map('strval',$n);
   $c['ui']=['answer'=>array_fill(0,count($ch),'chip'),'opts'=>array_fill(0,count($ch),$n),'labels'=>$ch];$c['choices']=[];
  }elseif($ch){
   $c['choices']=array_map(fn($x)=>trim(preg_replace('~^\s*☐\s*~u','',$x)),$ch);$c['ui']=['answer'=>['chip']];
  }elseif($title==='Read'){
   /* Reading for speed (the module's word count): aloud -> record it; silently -> Start / Done timer. Both give words per minute. */
   $sp=(int)($c['speed']??0);$c['ui']=['answer'=>[$aloud?'say':($sp?'speed':'look')]]+($aloud?['max'=>300]:[])+($sp?['words'=>$sp]:[]);}
  elseif(!empty($c['grid'])){$c['text']='';$c['ui']=['answer'=>['lgrid']];}
  elseif($title==='Puzzle'){$c['ui']=['answer'=>['paper']];}
  elseif($l7?preg_match('~^\s*draw\b~i',$t)&&!preg_match('~describe it here~i',$t):preg_match('~\b(draw|shade|encircle|underline|box the|in a square|square around|rectangle around|label the picture)\b~i',$t)){$c['ui']=['answer'=>['paper']];}
  /* Level 7 writing tasks (news, editorial, feature, column...): a big box. */
  else{$c['ui']=['answer'=>['write']]+($title==='Write'&&preg_match('~\b(article|editorial|story|column|report|coverage|paragraphs?|news|feature|opinion|summary|corrections?|copyread)\b~i',$t)?['long'=>true]:[]);}
  $out[]=$c;}
 /* The page's word list, under the story (or on the first card). */
 if($vocab&&$out){$i=0;foreach($out as $k=>$c)if(($c['title']??'')==='Read'){$i=$k;break;}$out[$i]['words']=$vocab['words'];$out[$i]['words_label']=$vocab['label'];}
 $s['cards']=$out;return $s;
}
function l2a_header(array $lesson):string{$i=l2a_lesson_info((int)$lesson['id']);if(!$i)return (string)$lesson['subtitle'];return !empty($i['covers'])?$i['activity']:$i['lesson'].' · '.preg_replace('~\s*·.*$~u','',$i['activity']);}

/** The pictures of one card. A picture can be tapped when the card is answered by choosing a picture. */
function l2a_pictures(array $c,int $n,bool $demo=false):string{
 $ans=$c['ui']['answer']??[];$tap=!$demo&&array_intersect($ans,['pick','picks','pairs']);$ref=$c['ui']['ref']??null;$h='';$k=0;
 foreach($c['images'] as $i=>$im){$src=resolved_image_path((string)$im['src']);if(!$src)continue;$k++;$isRef=$ref!==null&&$i===(int)$ref;
  $inner='<img '.($demo?'data-src':'src').'="'.e($src).'" alt="'.e($im['label']?:$im['alt']).'">'.($im['label']!==''?'<span class="l2-lab">'.e($im['label']).'</span>':'');
  $h.=$tap&&!$isRef?'<button type="button" class="l2-pic" data-pick="'.($i+1).'" aria-pressed="false" aria-label="Picture '.($i+1).($im['label']!==''?': '.e($im['label']):'').'">'.$inner.'</button>':'<figure class="l2-pic'.($isRef?' ref':'').'">'.$inner.($isRef?'<span class="l2-refnote">This one</span>':'').'</figure>';}
 return $k?'<div class="l2-pics n'.min($k,4).($k>4?' many':'').'">'.$h.'</div>':'';
}
/** Matching card: the board from render_matching_card. Its answer ("1-c, 2-a") goes in the hidden box; a saved answer shows its lines. */
function l2a_match_board(array $c,bool $readonly,string $saved=''):string{
 ob_start();render_matching_card($c['match'],false,!$readonly);$b=(string)ob_get_clean();
 return '<div class="l2-match"'.($readonly?'':' data-part="0" data-kind="match"').'><input type="hidden" class="native-answer" value="'.e($saved).'">'.$b.'</div>';
}
/** Card words: "___ og" shows the blank as a box the letter tiles fill. */
function l2a_text(string $t,bool $tiles,string $under=''):string{
 if($t==='')return '';$h=nl2br(e($t));if($under!=='')$h=preg_replace('~\b('.preg_quote(e($under),'~').')\b~u','<u>$1</u>',$h,1);
 if($tiles&&preg_match('~_{2,}~',$t))$h=preg_replace('~_{2,}~','<output class="l2-gap" data-gap></output>',$h,1);
 return '<div class="l2-word'.(mb_strlen($t)>40?' long':'').'">'.$h.'</div>';
}
function l2a_answer(array $c,int $n,bool $readonly):string{
 if($readonly)return '';$h='';
 foreach($c['ui']['answer']??[] as $p=>$kind){
  if($kind==='tiles'){$t=$c['ui']['tiles']??str_split('abcdefghijklmnopqrstuvwxyz');
   $h.='<div class="l2-tiles'.(count($t)<=15?' big':'').'" data-part="'.$p.'" data-kind="tiles">'.(preg_match('~_{2,}~',$c['text'])?'':'<output class="l2-out" data-out aria-live="polite"></output>').'<div class="l2-keys">';foreach($t as $k)$h.='<button type="button" class="l2-key" data-key="'.e($k).'">'.e($k).'</button>';
   $h.='<button type="button" class="l2-key sp" data-key=" " aria-label="Space">space</button><button type="button" class="l2-key er" data-erase aria-label="Erase">'.l1_icon('prev').'</button></div></div>';}
  elseif($kind==='chip'||$kind==='chips'){$h.=(!empty($c['ui']['labels'][$p])?'<p class="l2-plab">'.e($c['ui']['labels'][$p]).'</p>':'').'<div class="l2-chips'.(!empty($c['ui']['labels'][$p])?' row':'').(max(array_map('mb_strlen',array_map('strval',$c['ui']['opts'][$p]??$c['choices']))+[0])>22?' long':'').'" data-part="'.$p.'" data-kind="'.$kind.'"'.(!empty($c['ui']['labels'][$p])?' data-lab="'.e($c['ui']['labels'][$p]).'"':'').' role="group">';foreach(($c['ui']['opts'][$p]??$c['choices']) as $ch)$h.='<button type="button" class="l2-chip" data-val="'.e($ch).'" aria-pressed="false">'.e($ch).'</button>';$h.='</div>'.($kind==='chips'?'<p class="l2-how">Tap every word that fits. Tap again to undo.</p>':'');}
  elseif($kind==='split'){$w=(string)($c['ui']['word']??$c['text']);$on=($c['ui']['split']??'')==='onset';$h.='<div class="l2-split'.($on?' onset':'').(mb_strlen($w)>9?' xl':(mb_strlen($w)>6?' l':'')).'" data-part="'.$p.'" data-kind="split" data-mode="'.($on?'onset':'syllable').'"><span class="l2-sw">';
   $L=preg_split('//u',$w,-1,PREG_SPLIT_NO_EMPTY);foreach($L as $i=>$ch){$h.='<span class="l2-ch">'.e($ch).'</span>';if($i<count($L)-1)$h.='<button type="button" class="l2-cut" data-cut="'.($i+1).'" aria-pressed="false" aria-label="Cut after '.e($ch).'"></button>';}
   $h.='</span><output class="l2-sout" data-sout></output></div><p class="l2-how">'.($on?'Tap where the rime starts. The onset turns blue.':'Tap between letters to put a slash (/) between syllables.').'</p>';}
  elseif($kind==='words'){$mk=$c['ui']['mark']??'dot';$h.='<div class="l2-words mk-'.e($mk).'" data-part="'.$p.'" data-kind="words">';foreach(preg_split('/\s+/u',trim((string)$c['text'])) as $i=>$w)if($w!=='')$h.='<button type="button" class="l2-wd" aria-pressed="false">'.e($w).'</button>';$h.='</div><p class="l2-how">Tap each word as you read it.</p>';}
  elseif($kind==='num'){$mx=(int)($c['ui']['max']??6);$h.='<div class="l2-nums'.($mx>6?' wide':'').'" data-part="'.$p.'" data-kind="num" role="group" aria-label="Number">';for($i=1;$i<=$mx;$i++)$h.='<button type="button" class="l2-num" data-val="'.$i.'" aria-pressed="false">'.$i.'</button>';$h.='</div>';}
  elseif($kind==='pick'||$kind==='picks'||$kind==='pairs')$h.='<div data-part="'.$p.'" data-kind="'.$kind.'" hidden></div><p class="l2-how">'.($kind==='pick'?'Tap the picture to choose it.':($kind==='picks'?'Tap every picture that fits. Tap again to undo.':'Tap two pictures that go together. They get the same number.')).'</p>';
  elseif($kind==='say')$h.='<div class="l2-say l1-rec" data-part="'.$p.'" data-kind="say" data-l2-rec'.(!empty($c['ui']['max'])?' data-max="'.(int)$c['ui']['max'].'"':'').(!empty($c['ui']['words'])?' data-words="'.(int)$c['ui']['words'].'"':'').'><button type="button" class="l1-mic" data-rec-go aria-label="'.(!empty($c['ui']['max'])?'Tap and read the story aloud':'Tap and say your answer').'">'.l1_icon('mic').'</button><b class="l1-rl" data-rec-label>'.(!empty($c['ui']['max'])?'Tap and read the story aloud':'Tap and say it').'</b><small class="l1-rs" data-rec-sub role="status"></small><div class="l1-play" data-rec-play hidden><button type="button" class="l1-pbtn" data-rec-listen aria-label="Play my answer">'.l1_icon('play').'</button><span class="l1-bar"><i></i></span><span class="l1-len" data-rec-len></span><button type="button" class="l2-again" data-rec-again aria-label="Record again">'.l1_icon('again').'</button></div><button type="button" class="l1-heard" data-rec-heard>'.l1_icon('ok').'Done</button><input type="hidden" name="audio_card['.$n.']" data-audio value=""></div>';
  elseif($kind==='lgrid'){$h.='<div class="letter-grid-wrap l2-lgrid" data-part="'.$p.'" data-kind="lgrid"><table class="letter-grid" data-letter-grid aria-label="Letter puzzle: tap letters to mark a word">';
   foreach($c['grid'] as $row){$h.='<tr>';foreach(preg_split('//u',(string)$row,-1,PREG_SPLIT_NO_EMPTY) as $ch)$h.=$ch===' '||$ch==='.'?'<td class="blank"></td>':'<td><button type="button" class="grid-cell" aria-pressed="false">'.e($ch).'</button></td>';$h.='</tr>';}
   $h.='</table></div><p class="l2-how">Tap the letters of each word you find to mark them. Tap again to undo.</p>';}
  elseif($kind==='speed'){$w=(int)$c['ui']['words'];$h.='<div class="l2-speed" data-part="'.$p.'" data-kind="speed" data-words="'.$w.'"><p class="l2-how">Reading for speed · '.$w.' words. Tap <b>Start</b> when your teacher says “Go”, read the story, then tap <b>Done</b>.</p><div class="l2-speed-row"><button type="button" class="l1-btn2" data-sp-start>'.l1_icon('play').'Start</button><output class="l2-speed-clock" data-sp-clock aria-live="off">0:00</output><button type="button" class="l1-btn2" data-sp-done hidden>'.l1_icon('ok').'Done</button></div><p class="l2-speed-res" data-sp-res role="status"></p></div>';}
  elseif($kind==='write')$h.='<label class="l2-write'.(!empty($c['ui']['long'])?' long':'').'" data-part="'.$p.'" data-kind="write"><span class="sr-only">Your answer</span><textarea rows="'.(!empty($c['ui']['long'])?8:2).'" maxlength="'.(!empty($c['ui']['long'])?8000:300).'" autocomplete="off" autocapitalize="sentences" placeholder="Type your answer"></textarea></label>';
  elseif($kind==='paper')$h.='<button type="button" class="l2-paper" data-part="'.$p.'" data-kind="paper" aria-pressed="false">'.l1_icon('ok').'Done with my teacher or on paper</button>';
 }
 return $h?'<div class="l2-ans">'.$h.'</div>':'';
}

/** Lesson start: the module's lesson, its goals (or what the skill is), and its "My turn" example. */
function l2a_start_page(array $lesson,array $all,array $first):void{
 $lid=(int)$lesson['id'];$i=l2a_lesson_info($lid)??['lesson'=>$lesson['subtitle'],'activity'=>$lesson['title'],'goals'=>[],'what'=>null,'model'=>null];$count=['pre'=>0,'learn'=>0,'post'=>0];foreach($all as $x)$count[$x['phase']]++;
 $lv=lesson_level($lid);head('Level '.l2a_code($lv).' · '.$i['lesson'],'activity-page l1-page');
 echo '<div class="l1-app l1-start-app" data-l1-start>'.lesson_head($lesson,$all,$first,'?page=lessons&level='.$lv.'#level'.$lv.'-path').'<main class="l1-main l1-start activity-main"><div class="l1-start-in"><div class="l1-start-top"><img class="l1-cover" src="assets/images/covers/level-'.$lv.'.webp" alt=""><div><span class="l1-tag">'.e(mb_strtoupper($i['lesson'])).'</span><h1>'.e($i['activity']).'</h1></div></div>';
 if($i['goals'])echo '<div class="l1-goals"><p class="l1-lab">TODAY YOU WILL…'.l1_speaker(implode(' ',$i['goals']),'Hear what you will learn','sm').'</p><ul>'.implode('',array_map(fn($g)=>'<li>'.e($g).'</li>',$i['goals'])).'</ul></div>';
 if(!empty($i['covers']))echo '<div class="l1-goals"><p class="l1-lab">THIS TEST HAS…</p><ul>'.implode('',array_map(fn($g)=>'<li>'.e($g).'</li>',$i['covers'])).'</ul></div>';
 if($i['what'])echo '<div class="l2-model"><b>WHAT IS '.e(mb_strtoupper($i['lesson'])).'?</b><p>'.e($i['what']).'</p></div>';
 if($i['model'])echo '<div class="l2-model"><b>MY TURN'.l1_speaker($i['model'],'Hear the example','sm').'</b><p>'.e($i['model']).'</p></div>';
 echo lesson_parts($lesson,$all,$count).'<a class="l1-big l1-go" href="?page=lesson&amp;id='.$lid.'&amp;activity='.(int)$first['id'].'">Let’s start! '.l1_icon('next').'</a></div></main></div>';foot();
}

/** One Level 2A activity: its cards one at a time, answered on the card; Submit on the last card. */
function l2a_activity_page(array $u,array $lesson,array $all,array $a,array $set):void{
 $lid=(int)$lesson['id'];$readonly=completion_ok($a);$cards=$set['cards'];$N=count($cards);
 $phase=array_values(array_filter($all,fn($x)=>$x['phase']===$a['phase']));$at=0;foreach($phase as $i=>$x)if((int)$x['id']===(int)$a['id'])$at=$i+1;$doneAll=count(array_filter($all,fn($x)=>completion_ok($x)));
 $saved=[];$resp=str_replace(["\r\n","\r"],"\n",(string)($a['response']??''));if(str_starts_with($resp,"BULIG card answers\n"))foreach(preg_split('/\n(?=Card [0-9]+(?: \(no\. [0-9]+\))?:)/',substr($resp,19)) as $line)if(preg_match('/^Card ([0-9]+)(?: \(no\. [0-9]+\))?:\s?(.*)$/s',$line,$m))$saved[(int)$m[1]]=$m[2];
 $aud=[];if($a['completion_id']&&l2a_audio_supported()){$aud=json_decode((string)val('SELECT audio_paths FROM activity_completion WHERE id=?',[(int)$a['completion_id']]),true)?:[];}
 $lv=lesson_level($lid);head('Level '.l2a_code($lv).' · '.$set['module_title'],'activity-page l1-page l2-page');
 echo '<div class="l1-app l2-app">'.lesson_head($lesson,$all,$a,'?page=lessons&level='.$lv.'#level'.$lv.'-path');
 echo '<main class="l1-main activity-main">';notice();
 echo '<form method="post" id="activity-form" class="activity-form l1-form l2-form" data-mode="answer" data-kind="cards" data-draft="off">'.csrf_field().'<input type="hidden" name="action" value="submit"><input type="hidden" name="activity_id" value="'.$a['id'].'"><input type="hidden" name="transcript" id="transcript" value=""><input type="hidden" name="drawing" id="drawing-data" value=""><input type="hidden" name="response" id="response" value="'.e($resp).'"><span id="draft-status" hidden></span>';
 if($a['feedback'])echo '<div class="l1-note">'.l1_icon('teacher').'<div><b>A note from your teacher</b><p>'.e($a['feedback']).'</p></div></div>';
 echo '<span class="l1-tag">'.e(mb_strtoupper($set['module_title'])).'</span>'.(trim((string)$set['instruction'])!==''?'<div class="l2-dir"><p>'.nl2br(e($set['instruction'])).'</p>'.l1_speaker($set['instruction']).'</div>':'');
 echo '<div class="l2-deck" data-l2-deck data-total="'.$N.'"'.($readonly?' data-readonly':'').'>';
 foreach($cards as $i=>$c){$n=$i+1;$kinds=$c['ui']['answer']??[];$tiles=in_array('tiles',$kinds,true);
  echo '<section class="l2-card" data-n="'.$n.'" data-t="'.e(ctype_digit((string)$c['title'])&&(string)$c['title']!==(string)$n?(string)$c['title']:'').'" data-kinds="'.e(implode(' ',$kinds)).'"'.($i?' hidden':'').'><div class="l2-cardtop"><span class="l2-cnum">'.($N>1?'Card '.$n.' of '.$N:'').'</span>'.l1_speaker(trim((string)($c['speak']??(($c['text']??'')!==''?$c['text']:$set['instruction']))),'Hear this card','sm').'</div>';
  if(!empty($c['story']))echo '<h3 class="l2-story">'.e($c['story']).'</h3>';
  if(!empty($c['ui']['grid'])){$g=$c['ui']['grid'];$lines=max(array_map('mb_strlen',$g))>14;echo '<div class="l3-grid'.($lines?' lines':'').(count($g)>12?' many':'').(!$lines&&count($g)<=10&&count($g)%2===0?' cols2':'').'">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$g)).'</div>'.l2a_pictures($c,$n);}
  elseif(in_array('match',$kinds,true))echo ((string)$c['text']!==''?l2a_text((string)$c['text'],false):'').l2a_match_board($c,$readonly,$readonly?(string)($saved[$n]??''):'');
  else echo (in_array('words',$kinds,true)&&!$readonly?'':l2a_text((string)$c['text'],$tiles&&!$readonly,(string)($c['underline']??''))).l2a_pictures($c,$n);
  if(!empty($c['words']))echo '<p class="l2-banklab">'.e($c['words_label']??'Words').'</p><div class="l2-bank">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['words'])).'</div>';
  if(in_array('look',$kinds,true)||in_array('paper',$kinds,true)||$readonly){if($c['choices']&&!array_intersect($kinds,['chip','chips']))echo '<div class="l2-bank">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['choices'])).'</div>';}
  echo l2a_answer($c,$n,$readonly);
  if($readonly){$ans=$saved[$n]??'';echo '<div class="l2-saved">'.l1_icon('ok').'<span>'.($ans!==''?e($ans):'Saved').'</span></div>';if(!empty($aud[(string)$n]))echo '<div class="l1-mine"><audio controls preload="none" src="?page=recording&amp;id='.(int)$a['completion_id'].'&amp;n='.$n.'"></audio></div>';}
  echo '</section>';}
 echo '</div><div class="l1-end"><div id="answer-feedback" role="status" aria-live="polite" class="answer-feedback l1-fb"></div><div class="l1-btns"><button type="button" class="l1-btn2" data-l2-prev hidden>'.l1_icon('prev').'Back</button>';
 if(!$readonly)echo '<button type="button" class="l1-big" data-l2-next'.($N<2?' hidden':'').'>Next card '.l1_icon('next').'</button><button id="submit-answer" class="l1-big" type="submit"'.($N>1?' hidden':'').'>Submit '.l1_icon('ok').'</button>';
 else echo '<button type="button" class="l1-big" data-l2-next'.($N<2?' hidden':'').'>Next card '.l1_icon('next').'</button>';
 echo '<a id="next-activity" class="l1-big" href="?page=lesson&amp;id='.$lid.'"'.($readonly&&$N<2?'':' hidden').' data-l2-done>'.($doneAll===count($all)?'Finish lesson':'Next').' '.l1_icon('next').'</a></div></div></form></main></div>';foot();
}

/* ---------------- recordings for several cards ---------------- */
function l2a_audio_supported():bool{static $s;if($s===null){try{$s=(bool)one("SHOW COLUMNS FROM activity_completion LIKE 'audio_paths'");}catch(Throwable $e){$s=false;}}return $s;}
/** Save the recordings sent with a Level 2A activity: audio_card[n] (data URLs). Returns {n: path}. */
function l2a_save_audios(array $in,int $pid,int $aid):array{
 $out=[];$total=0;foreach($in as $n=>$data){$n=(int)$n;$data=(string)$data;if($n<1||$n>60||$data==='')continue;$total+=strlen($data);if($total>9000000)fail('These recordings are too long. Please keep each one short.');$out[(string)$n]=l1_save_audio($data,$pid,$aid);}
 return $out;
}

/* ---------------- Class Demo ---------------- */
function l2a_demo_view(array $u,int $level=2,int $grade=0):void{$code=l2a_code($level);$per=per_grade_level($level);$label=$per?module_label($level,$grade):'Level '.$code;
 $acts=rows("SELECT a.*,l.position lesson_position,l.subtitle,l.title lesson_title,l.objectives lesson_objectives FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=? AND l.published=1 AND a.published=1".($per?GRADE_SQL:'')." ORDER BY l.position,l.id,FIELD(a.phase,'pre','learn','post'),a.position,a.id",$per?[$level,$grade]:[$level]);
 if(!$acts)fail('This level has no published demo content yet.',404);
 $deck=[];$seen=[];foreach($acts as $a){$lid=(int)$a['lesson_id'];if(!isset($seen[$lid])){$seen[$lid]=1;$deck[]=['start'=>true,'a'=>$a];}
  $set=$level===5?null:l2a_set($a);
  if(!$set){if($level>=5)$deck[]=['start'=>false,'a'=>$a,'set'=>null,'c'=>null,'i'=>0];continue;}
  foreach($set['cards'] as $i=>$c)$deck[]=['start'=>false,'a'=>$a,'set'=>$set,'c'=>$c,'i'=>$i];}
 $index=0;$want=(int)($_GET['activity']??0);$wc=(int)($_GET['card']??0);if($want){foreach($deck as $i=>$d)if(!$d['start']&&(int)$d['a']['id']===$want&&$d['i']===$wc){$index=$i;break;}}elseif(isset($_GET['lesson'])){foreach($deck as $i=>$d)if($d['start']&&(int)$d['a']['lesson_id']===(int)$_GET['lesson']){$index=$i;break;}}
 $info=function(array $a)use($per):array{$li=l2a_lesson_info((int)$a['lesson_id']);if($li)return $li;return $per?['lesson'=>$a['lesson_title'],'activity'=>$a['subtitle'],'goals'=>l1_goals((string)$a['lesson_objectives']),'what'=>null,'model'=>null]:['lesson'=>$a['subtitle'],'activity'=>$a['lesson_title'],'goals'=>l1_goals((string)$a['lesson_objectives']),'what'=>null,'model'=>null];};
 $titleOf=function(array $li)use($per):string{return !empty($li['covers'])?$li['activity']:($per?$li['lesson'].' · '.$li['activity']:$li['lesson'].' · '.preg_replace('~\s*[·].*$~u','',$li['activity']));};
 head('Class Demo · '.$label,'demo-page l1-demo-page');
 echo '<div id="l1-demo" class="l1-demo l2-demo'.($level>=5?' l5-demo':'').'" data-start="'.$index.'"><header class="l1d-top"><a class="l1d-logo" href="?page=class_demo"><img src="assets/bulig-logo.png" alt="BULIG"></a><div class="l1d-ln"><b data-d-lesson></b><small data-d-part></small></div><div class="l1d-prog" aria-hidden="true"><i data-d-prog></i></div>';
 echo '<label class="l1d-sel"><span class="sr-only">Go to a lesson</span><select id="demo-lesson" data-d-lessons>';foreach($deck as $i=>$d)if($d['start']){$li=l2a_lesson_info((int)$d['a']['lesson_id']);echo '<option value="'.$i.'">'.e($li?(!empty($li['covers'])?$li['activity']:preg_replace('~\s*[·:.].*$~u','',$li['activity']).' · '.$li['lesson']):($per?$d['a']['lesson_title'].' · '.$d['a']['subtitle']:$d['a']['subtitle'])).'</option>';}echo '</select></label><select id="demo-jump" hidden aria-hidden="true" tabindex="-1">';foreach($deck as $i=>$d)echo '<option value="'.$i.'"></option>';echo '</select>';
 echo '<button type="button" class="l1d-tb" data-plan-open aria-haspopup="dialog" aria-label="Lesson plan">'.l1_icon('book').'Lesson plan</button><button type="button" class="l1d-tb" data-cd-open aria-label="Mark as done">'.l1_icon('ok').'Mark as done</button><button type="button" class="l1d-tb" data-d-full aria-label="Full screen">'.l1_icon('full').'<span>Full screen</span></button><a class="l1d-tb" href="?page=class_demo'.($per?'&amp;level='.$level:'').'" aria-label="Exit">'.l1_icon('x').'Exit</a></header>';
 if(isset($_SESSION['flash'])){echo '<div class="notice demo-notice" role="status">'.icon('check').'<span>'.e($_SESSION['flash']).'</span></div>';unset($_SESSION['flash']);}
 echo class_done_demo_panel($u,$level,$per?$grade:0,$label).demo_lesson_plans($acts,$level,$per?$grade:0,$label);
 echo '<main class="l1d-stage" data-d-stage tabindex="-1" aria-live="polite" aria-label="Class slide"></main><footer class="l1d-bot"><button type="button" class="l1d-nb" data-d-prev>'.l1_icon('prev').'Previous</button><span class="l1d-cnt" data-d-count role="status"></span><button type="button" class="l1d-lis" data-d-listen aria-pressed="false">'.l1_icon('spk').'<span>Listen</span></button><button type="button" class="l1d-nb g" data-d-next>Next '.l1_icon('next').'</button></footer>';
 $firstOf=[];foreach($deck as $d)if(!$d['start']&&!isset($firstOf[(int)$d['a']['lesson_id']]))$firstOf[(int)$d['a']['lesson_id']]=(int)$d['a']['id'];
 foreach($deck as $d){$a=$d['a'];$lid=(int)$a['lesson_id'];$li=$info($a);$title=$titleOf($li);
  if($d['start']){echo '<template class="l1d-t demo-template" data-lesson="'.$lid.'" data-id="'.($firstOf[$lid]??0).'" data-card="0" data-title="'.e($title).'" data-part="Lesson start" data-say="'.e($li['activity'].'. '.implode(' ',$li['goals']).' '.($li['what']??'').' '.($li['model']??'')).'"><span class="demo-slide-meta" hidden>'.e($title).' · Lesson start</span><div class="l1d-two l1d-start"><img class="l1d-cover" data-src="assets/images/covers/level-'.$level.'.webp" alt=""><div><span class="l1-tag">'.e(mb_strtoupper($li['lesson'])).'</span><h1>'.e($li['activity']).'</h1>';
   if($li['goals'])echo '<p class="l1-lab">TODAY WE WILL…</p><ul>'.implode('',array_map(fn($g)=>'<li>'.e($g).'</li>',$li['goals'])).'</ul>';
   if(!empty($li['covers']))echo '<p class="l1-lab">THIS TEST HAS…</p><ul>'.implode('',array_map(fn($g)=>'<li>'.e($g).'</li>',$li['covers'])).'</ul>';
   if($li['what'])echo '<p class="l1-lab">WHAT IS '.e(mb_strtoupper($li['lesson'])).'?</p><p class="l2d-p">'.e($li['what']).'</p>';
   if($li['model'])echo '<p class="l1-lab">MY TURN</p><p class="l2d-p">'.e($li['model']).'</p>';
   echo '</div></div></template>';continue;}
  if($d['set']===null){echo l5_demo_page($a,$title);continue;}
  $set=$d['set'];$c=$d['c'];$N=count($set['cards']);$pics=l2a_pictures($c,$d['i']+1,true);$txt=(string)$c['text'];
  echo '<template class="l1d-t demo-template" data-lesson="'.$lid.'" data-id="'.(int)$a['id'].'" data-card="'.$d['i'].'" data-title="'.e($title).'" data-part="'.e($set['module_title'].($N>1?' · Card '.($d['i']+1).' of '.$N:'')).'" data-say="'.e(trim(($d['i']?'':$set['instruction'].' ').($c['story']??'').' '.($c['speak']??$txt))).'"><span class="demo-slide-meta" hidden>'.e($title).' · '.e(l1_phase_label($a['phase'])).'</span><h1 class="sr-only">'.e($set['module_title']).'</h1>';
  if($level>=6){echo l5_demo_card($a,$set,$c,$d['i']+1,$pics).'</template>';continue;}
  echo '<div class="l1d-'.($pics?'two':'one').' l2d">';
  echo '<div class="l1d-txt"><span class="l1-tag">'.e(mb_strtoupper($set['module_title'])).'</span><p class="l2d-dir">'.nl2br(e($set['instruction'])).'</p>'.(!empty($c['ui']['grid'])?'<div class="l3-grid demo">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['ui']['grid'])).'</div>':'').($txt!==''&&empty($c['ui']['grid'])?'<p class="l1d-q'.(mb_strlen($txt)>60?' l':'').'">'.nl2br(e($txt)).'</p>':'');
  if($txt===''&&!empty($c['ui']['word']))echo '<p class="l1d-q">'.e($c['ui']['word']).'</p>';
  if(!empty($c['ui']['opts'])){foreach($c['ui']['opts'] as $row)echo '<div class="l2-bank big">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$row)).'</div>';}
  else{$bank=$c['choices']?:($c['words']??($c['ui']['tiles']??[]));if($bank)echo '<div class="l2-bank big">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$bank)).'</div>';}
  echo '</div>'.$pics.'</div></template>';}
 echo '</div>';foot();
}

/* Class Demo slides for Levels 4-7 (level ids 5-8): the same big slides as Levels 1-3. */
/** Level 4 (Fluency): the passage to read aloud with the Phil-IRI scoring helper, or the module's page. */
function l5_demo_page(array $a,string $title):string{
 $lid=(int)$a['lesson_id'];$h='<template class="l1d-t demo-template" data-lesson="'.$lid.'" data-id="'.(int)$a['id'].'" data-card="0" data-title="'.e($title).'" data-part="'.e(l1_phase_label($a['phase'])).'"';
 $parts=$a['type']==='reading'?level4_passage_parts($a):null;
 if($parts){$say='Read the following passage aloud.';$h.=' data-say="'.e($say.' '.(string)$a['narration']).'"><span class="demo-slide-meta" hidden>'.e($title).' · '.e(l1_phase_label($a['phase'])).'</span>';
  $w=(int)$parts['words'];
  $h.='<div class="l1d-one l5d"><div class="l1d-txt"><span class="l1-tag">'.e(l4_tag($a,['position'=>(int)$a['lesson_position']])).'</span><p class="l2d-dir">'.e($say).' Read it together, or listen as one pupil reads.</p><div class="l5d-read l4d">'.str_replace('<img src=','<img data-src=',(string)level4_passage($a)).'</div>';
  $h.='<details class="fluency-score l5d-score" data-words="'.$w.'"><summary>Teacher’s scoring helper (Phil-IRI) · not saved</summary><div class="score-grid"><label>Number of miscues<input type="number" min="0" max="'.$w.'" inputmode="numeric" data-score="miscues"></label><label>Reading time (seconds)<input type="number" min="1" inputmode="numeric" data-score="seconds"></label><div>Oral Reading Score<output data-score="percent">—</output></div><div>Reading level<output data-score="level">—</output></div><div>Reading speed<output data-score="wpm">—</output></div></div><p><small>Score = ('.$w.' − miscues) ÷ '.$w.' × 100 · Independent 97–100%, Instructional 90–96%, Frustration 89% and below · Speed = '.$w.' ÷ seconds × 60.</small></p></details></div></div></template>';
  return $h;}
 $pics=l1_pictures($a,true);
 $h.=' data-say="'.e((string)$a['prompt']).'"><span class="demo-slide-meta" hidden>'.e($title).' · '.e(l1_phase_label($a['phase'])).'</span><div class="l1d-'.($pics?'two':'one').' l2d"><div class="l1d-txt"><span class="l1-tag">'.e(mb_strtoupper($a['title'])).'</span><p class="l1d-q'.(mb_strlen((string)$a['prompt'])>60?' l':'').'">'.nl2br(e((string)$a['prompt'])).'</p></div>'.$pics.'</div></template>';
 return $h;
}
/** Levels 5-7: one card as a slide. The story, the question, its choices, the matching board or puzzle, and the answer the teacher can show. */
function l5_demo_card(array $a,array $set,array $c,int $n,string $pics):string{
 $kinds=$c['ui']['answer']??[];$txt=(string)$c['text'];$h='<div class="l1d-txt l5d-txt"><span class="l1-tag">'.e(mb_strtoupper($set['module_title'])).'</span>';
 if($n===1&&trim((string)$set['instruction'])!=='')$h.='<p class="l2d-dir">'.nl2br(e($set['instruction'])).'</p>';
 if(!empty($c['story']))$h.='<h2 class="l5d-story">'.e($c['story']).'</h2>';
 if(in_array('lgrid',$kinds,true)){$h.=($txt!==''?'<p class="l2d-dir">'.nl2br(e($txt)).'</p>':'').'<div class="l5d-scroll"><table class="letter-grid l5d-grid" aria-label="Letter puzzle">';foreach($c['grid'] as $row){$h.='<tr>';foreach(preg_split('//u',(string)$row,-1,PREG_SPLIT_NO_EMPTY) as $ch)$h.=$ch===' '||$ch==='.'?'<td class="blank"></td>':'<td>'.e($ch).'</td>';$h.='</tr>';}$h.='</table></div>';}
 elseif(in_array('match',$kinds,true))$h.=($txt!==''?'<p class="l5d-lead">'.nl2br(e($txt)).'</p>':'').'<div class="l5d-scroll">'.str_replace('<img src=','<img data-src=',l2a_match_board($c,true)).'</div>';
 elseif(!empty($c['ui']['grid']))$h.='<div class="l3-grid demo">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['ui']['grid'])).'</div>';
 elseif($txt!=='')$h.=mb_strlen($txt)>170?'<div class="l5d-read">'.nl2br(e($txt)).'</div>':'<p class="l1d-q'.(mb_strlen($txt)>60?' l':'').'">'.nl2br(e($txt)).'</p>';
 if(!empty($c['words']))$h.='<p class="l2-banklab">'.e($c['words_label']??'Words').'</p><div class="l2-bank big">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['words'])).'</div>';
 if(!empty($c['ui']['labels'])&&count($kinds)>1){$h.='<ol class="l5d-order">';foreach($c['ui']['labels'] as $lab)$h.='<li><span class="l5d-box" aria-hidden="true"></span>'.e($lab).'</li>';$h.='</ol>';}
 elseif($c['choices']){$long=max(array_map('mb_strlen',array_map('strval',$c['choices'])))>24;$h.=$long?'<ul class="l5d-ch">'.implode('',array_map(fn($w)=>'<li>'.e($w).'</li>',$c['choices'])).'</ul>':'<div class="l2-bank big">'.implode('',array_map(fn($w)=>'<span>'.e($w).'</span>',$c['choices'])).'</div>';}
 $how=['write'=>'Pupils write their answers.','paper'=>'Pupils do this with you or on paper.','say'=>'Pupils read this aloud.','speed'=>'Reading for speed: time the reading together.','lgrid'=>'Find the words in the puzzle together.'];
 foreach($kinds as $k)if(isset($how[$k])){$h.='<p class="l5d-how">'.e($how[$k]).'</p>';break;}
 $key=activity_key($a)[$n]??null;
 if($key){$ans=$key['t']==='choice'?implode(' or ',array_merge([$key['a']],$key['alt']??[])):($key['t']==='match'?implode(', ',array_map(fn($i,$l)=>$i.' – '.strtoupper($l),array_keys($key['a']),$key['a'])):implode(' · ',array_map(fn($l,$v)=>$l.' = '.$v,array_keys($key['a']),$key['a'])));
  $h.='<details class="l5d-key"><summary>'.l1_icon('ok').'Show the answer</summary><p>'.e($ans).'</p></details>';}
 return '<div class="l1d-'.($pics?'two':'one').' l2d l5d">'.$h.'</div>'.$pics.'</div>';
}

/** Activity history: the pupil's recordings, one per card. */
function l2a_review_media(array $r):string{
 $m=json_decode((string)($r['audio_paths']??''),true)?:[];if(!$m)return '';ksort($m,SORT_NUMERIC);$h='<div class="rv-l1-audio rv-l2-audio"><span>'.l1_icon('mic').'Pupil’s recordings</span>';
 foreach($m as $n=>$p)$h.='<div class="rv-l2-one"><b>Card '.(int)$n.'</b><audio controls preload="none" src="?page=recording&amp;id='.(int)$r['id'].'&amp;n='.(int)$n.'"></audio></div>';
 return $h.'</div>';
}
