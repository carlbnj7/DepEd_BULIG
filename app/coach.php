<?php
/* Word Coach (v184). On every lesson activity where the pupil reads aloud (Level 1 reading, the "say" cards of Levels 2A
   to 7, the Level 4 passages), Max first reads the whole text slowly while the words light up (the pupil may skip it);
   then the pupil reads it all aloud and each word turns green when BULIG hears it said right; then the pupil says each
   missed word again. A word is worth 1 point when read right the first time, 2/3 on the second try, 1/3 on the third and
   0 after that (the word goes on the practise list). "Save my reading" then sends it
   as the answer (public/assets/coach.js); the microphone stays there for a pupil who wants to record too.
   The result comes back as wc[n] = "percent|word word" (n = card number, 0 = the whole activity), or "na|" when the
   device cannot listen (no score then). Card activities keep it in the answer as a line "Coach: 1=83|sleep yard; 2=100|"
   (app/scoring.php); Levels 1 and 4 keep it in rubric_scores['wc']. The Word Coach run is the pupil's reading (no need to
   record it again): its score is the reading's score, in lessons, pre-tests and post-tests alike. */

/** True when this activity gets the coach: every reading activity, pre-tests and post-tests too (v184). */
function wc_on(array $a):bool{return in_array($a['phase']??'learn',['pre','learn','post'],true);}

/** The text the coach reads for a "say" card, or '' (no coach: sounds, pictures to name, open answers). */
function wc_card_text(array $c,string $dir):string{
 if(!function_exists('say_expected')||!say_expected($c,$dir))return '';
 if(!empty($c['ui']['grid']))return implode("\n",$c['ui']['grid']);
 $t=trim((string)($c['text']??''));
 if(preg_match('~→\s*([\p{L}\']+)~u',$t,$m))return $m[1];
 if(empty($c['ui']['max'])&&($i=mb_strrpos($t,':'))!==false)$t=trim(mb_substr($t,$i+1));
 return $t;
}

/** Attributes and the hidden field for a recorder that gets the coach. */
function wc_attr(string $text):string{return trim($text)===''?'':' data-wc="'.e($text).'"';}
function wc_input(int $n):string{return '<input type="hidden" name="wc['.$n.']" value="" data-wc-val>';}

/* The card activity being drawn (set by level2a.php), so each "say" card knows its text. */
function wc_context(?array $a=null,?array $set=null,bool $set_it=false):?array{static $c=null;if($set_it)$c=$a&&$set&&wc_on($a)?['a'=>$a,'set'=>$set]:null;return $c;}

/** The coaching results sent with the answer: [n => ['p' => 0-100 or null, 'w' => words to practise]]. */
function wc_post():array{
 $out=[];foreach((array)($_POST['wc']??[]) as $n=>$v){$v=(string)$v;if(!ctype_digit((string)$n)||$v===''||!preg_match('~^(na|\d{1,3})\|(.{0,600})$~su',$v,$m))continue;
  $w=array_slice(array_values(array_filter(array_map(fn($x)=>mb_substr(trim($x),0,40),preg_split('~\s+~u',$m[2])),'strlen')),0,40);
  $out[(int)$n]=['p'=>$m[1]==='na'?null:min(100,(int)$m[1]),'w'=>$w];}
 return $out;
}
/** "Coach: 1=83|sleep yard; 2=100|" (one line; card answers only). */
function wc_line(array $wc):string{
 if(!$wc)return '';ksort($wc);return "\nCoach: ".implode('; ',array_map(fn($n,$x)=>$n.'='.($x['p']===null?'na':$x['p']).'|'.implode(' ',array_map(fn($w)=>str_replace([';','|',"\n"],'',$w),$x['w'])),array_keys($wc),$wc));
}
/** Read the Coach line of a card answer. */
function card_coach(string $r):array{
 if(!preg_match('~\nCoach: ([^\n]*)~u',$r,$m))return [];$out=[];
 foreach(explode(';',$m[1]) as $p)if(preg_match('~^\s*(\d+)=(na|\d{1,3})\|(.*)$~u',$p,$x))$out[(int)$x[1]]=['p'=>$x[2]==='na'?null:min(100,(int)$x[2]),'w'=>array_values(array_filter(preg_split('~\s+~u',trim($x[3])),'strlen'))];
 return $out;
}
/** At "Done" (card activities): put the Coach line in the answer, before the Tries line. */
function wc_merge(string $answer):string{
 $wc=wc_post();if(!$wc||!str_starts_with($answer,"BULIG card answers\n"))return $answer;
 $t=card_tries($answer);return card_strip_tries($answer).wc_line($wc).card_tries_line($t);
}
/** At "Done" (Levels 1 and 4, one reading): the Word Coach score is the reading's score. */
function wc_apply_single(int $cid):void{
 $x=wc_post()[0]??null;if(!$x)return;$c=one('SELECT score,max_score,rubric_scores FROM activity_completion WHERE id=?',[$cid]);if(!$c)return;
 $j=json_decode((string)($c['rubric_scores']??''),true);$j=is_array($j)?$j:[];$j['wc']=$x;q('UPDATE activity_completion SET rubric_scores=? WHERE id=?',[json_encode($j,JSON_UNESCAPED_UNICODE),$cid]);
 if($x['p']===null)return;
 $mx=$c['max_score']!==null&&(float)$c['max_score']>0?(float)$c['max_score']:1.0;$sc=$c['score']!==null?(float)$c['score']:$mx;
 q('UPDATE activity_completion SET score=?,max_score=? WHERE id=?',[round($mx*$x['p']/100,2),$mx,$cid]);
}
/** The coaching result of a saved answer: [n => ['p','w']] (n = 0 for Levels 1 and 4). */
function wc_result(array $c):array{
 $j=json_decode((string)($c['rubric_scores']??''),true);if(is_array($j)&&isset($j['wc']['w']))return [0=>['p'=>$j['wc']['p']??null,'w'=>(array)$j['wc']['w']]];
 return card_coach(str_replace(["\r\n","\r"],"\n",(string)($c['response']??'')));
}
/** Teacher review: what the coaching showed (score and words to practise). */
function wc_review(array $c):string{
 $r=wc_result($c);if(!$r)return '';$h='';
 foreach($r as $n=>$x)$h.='<li>'.($n?'<b>Card '.$n.'</b> · ':'').($x['p']===null?'Practised (the device could not listen)':'Word Coach '.(int)$x['p'].'%').($x['w']?' · practise: <span class="wc-rw">'.e(implode(', ',$x['w'])).'</span>':' · every word said well').'</li>';
 return '<div class="wc-review"><span class="eyebrow">WORD COACH</span><ul>'.$h.'</ul></div>';
}

/** v185: Levels 5 to 7: every card that holds a story or a passage (20 words or more, nothing else to answer, or the
    reading-speed timer) gets the Reading Coach, as in Level 4: the pupil reads it aloud with Max before going on. */
function wc_story(?array $s,?array $a=null):?array{
 if(!$s)return $s;
 foreach($s['cards'] as &$c){$k=$c['ui']['answer']??[];
  /* v186: "(Read Aloud) “My Food” These are the foods I eat. … Who cooks the food in the story?": the story and the question
     share one card (Level 5 assessments). The story is shown first with the Reading Coach to practise (no score: the
     card's answer is what counts), then the question. */
  if(preg_match('~^\(Read Aloud\)\s*[“"]([^”"]{2,40})[”"]\s*(.+)$~su',trim((string)($c['text']??'')),$m)){$rest=$m[2];$q=null;
   foreach(preg_split('~(?<=[.!?])\s+~u',$rest,-1,PREG_SPLIT_OFFSET_CAPTURE) as [$x,$o])if(str_ends_with(trim($x),'?')||preg_match('~^([“"]|(Write|Circle|Choose|Draw|Tell)\b)~u',$x)){$q=$o;break;}
   if($q&&str_word_count(substr($rest,0,$q))>=5)$c['ui']['practice']=['title'=>trim($m[1]),'story'=>trim(preg_replace('~\s+~u',' ',substr($rest,0,$q))),'q'=>trim(substr($rest,$q))];}
  if(!in_array($k,[['look'],['speed']],true))continue;
  $ttl=null;$t=array_key_exists('module_reading',$c['ui'])?trim((string)$c['ui']['module_reading']):wc_story_text((string)($c['text']??''),$ttl);if($ttl&&empty($c['story']))$c['story']=$ttl;
  /* v186: "Listen carefully as your teacher reads the story “My Food”": the story is in the lesson's "Listen:" activity; put it here. */
  if(str_word_count($t)<10&&$a&&$k===['look']&&preg_match('~\blisten~iu',(string)($c['text']??''))&&preg_match('~\b(story|passage|poem)\b~iu',(string)($c['text']??''))&&($t=wc_listen_story($a,implode(' ',array_column($s['cards'],'text'))))!==''){
   $c['story']=preg_match('~\b(?:story|passage|poem)\s*[“"]([^”"]{2,60})[”"]~iu',(string)$c['text'],$m)?trim($m[1]):'';$c['text']=$t;}
  /* "Lesson 18 – Mineral Resources Teacher reads the poem." (tests): the poem of that lesson. */
  if(str_word_count($t)<10&&$a&&$k===['look']&&preg_match('~Lesson\s+(\d+)\s*[–-]\s*(.+?)\s+Teacher reads the (?:story|passage|poem)\.?\s*$~u',trim((string)($c['text']??'')),$m)
   &&($lid=(int)val("SELECT l.id FROM lessons l JOIN lessons me ON me.module_id=l.module_id WHERE me.id=? AND l.title IN (?,?) LIMIT 1",[(int)$a['lesson_id'],'Activity '.$m[1],'Lesson '.$m[1]]))
   &&($t=wc_listen_story(['lesson_id'=>$lid,'id'=>0]))!==''){$c['story']=trim($m[2]);$c['text']=$t;}
  if(str_word_count($t)<10)continue;
  $c['ui']['answer'][]='read';$c['ui']['wct']=$t;
  /* v186: a story with no pictures here gets the pictures of the same story on the lesson's "Listen:" page. */
  if(empty($c['images'])&&$a&&($x=wc_listen_card($a,$t))&&$x['images']&&$x['share']>=.6)$c['images']=$x['images'];}
 unset($c);return $s;
}
/** v186: the story of the lesson's "Listen: …" activity (Level 5), or ''. */
function wc_listen_story(array $a,string $hint=''):string{return wc_listen_card($a,$hint)['t']??'';}
/** The lesson's "Listen:" story that best fits $hint: ['t' => story, 'images' => its pictures, 'share' => 0-1 words of $hint in it]. */
function wc_listen_card(array $a,string $hint=''):?array{
 $wd=fn($x)=>array_unique(array_filter(preg_split('~[^\p{L}]+~u',mb_strtolower($x)),fn($w)=>mb_strlen($w)>3));$q=$wd($hint);$best=null;$top=-1;
 foreach(rows("SELECT * FROM activities WHERE lesson_id=? AND title LIKE 'Listen:%' AND id<>? ORDER BY position",[(int)$a['lesson_id'],(int)$a['id']]) as $x){
  $set=lesson_level((int)$x['lesson_id'])===6?l5_set($x):l6_set($x);if(!$set)continue;
  $t=trim(implode(' ',array_filter(array_map(fn($c)=>wc_story_text((string)($c['text']??'')),$set['cards']))));if($t==='')continue;
  /* A lesson with two "Listen" stories: the one the questions talk about. */
  $sc=count(array_intersect($wd($t),$q));if($sc>$top){$top=$sc;$best=['t'=>$t,'images'=>array_merge(...array_map(fn($c)=>(array)($c['images']??[]),$set['cards'])),'share'=>$q?$sc/count($q):0];}}
 return $best;
}
/** v186: the part of a card's text that is the story (line breaks kept), without the directions around it. */
function wc_display(string $raw,string $story):string{
 $w=preg_split('~\s+~u',trim($story),-1,PREG_SPLIT_NO_EMPTY);if(count($w)<3)return $story;
 $pat=fn($ws)=>implode('[\s·•,;:–-]+',array_map(fn($x)=>preg_quote($x,'~'),$ws));
 if(!preg_match('~'.$pat(array_slice($w,0,3)).'~u',$raw,$a,PREG_OFFSET_CAPTURE)||!preg_match_all('~'.$pat(array_slice($w,-3)).'~u',$raw,$b,PREG_OFFSET_CAPTURE))return $story;
 $st=$a[0][1];$z=end($b[0]);$en=$z[1]+strlen($z[0]);return $en>$st?trim(substr($raw,$st,$en-$st)):$story;
}
/** v186: only the story or passage of a card, never its directions ("Listen carefully as your teacher reads…",
    "Part II: Listening Comprehension", "Then, answer the questions below"). '' when no story is left. */
function wc_story_text(string $t,?string &$title=null):string{
 /* "Story for the teacher to read: Vegetables" on its own line: the story's title, then the story. */
 if(preg_match('~\b(?:story|passage|poem)\b[^:\n]{0,40}:[ \t]*([^\n]{1,40})\n(.+)$~isu',$t,$m)){$title=trim($m[1]);$t=$m[2];}
 $t=trim(preg_replace('~\s+~u',' ',$t));if($t==='')return '';
 $how='~\b(listen|teacher|direction|instruction|answer|circle|encircle|choose|write|match|underline|questions?|part\s+[ivx]+|activity\s+\d|lesson\s+\d|assessment|items?|pupils?|learners?|students?|word bank|based on|pre-?test|post-?test|comprehension|fill in|blanks?|missing words?|missing letters?|supply)s?\b~iu';
 /* A story in quotation marks ("Teacher reads: “…”"): that is the text. */
 if(preg_match_all('~(?:reads\s*:?\s*["“]([^"”]{60,})["”])|(?:“([^”]{60,})”)~u',$t,$m,PREG_SET_ORDER))foreach($m as $x){$q=trim($x[2]??''?:$x[1]);$pre=mb_substr($t,0,(int)mb_strpos($t,$q));
  if(str_word_count($q)>=20&&preg_match($how,$pre))return $q;}
 /* "Story: …" / "Passage: …": the text after the label. */
 if(preg_match('~\b(?:story|passage|poem)\s*:\s*(.+)$~iu',$t,$m))$t=$m[1];
 /* Not a story: blanks to fill, word lists, crossword clues, sentences to listen to. */
 if(preg_match('~__|\s•\s|\bAcross\s*:|\bSentence\s+\d|\bwords? in the box\b|\bword clues\b|\s\d+\.\s+\p{L}+\s+[–-]\s~iu',$t))return '';
 /* A heading before a title in quotation marks ("Activity 1: “The Lost Lunchbox” Sarah …"); "9: Set B". */
 if(preg_match('~^([^“"]{0,120})([“"][^”"]{3,60}[”"].*)$~u',$t,$m)&&preg_match($how,$m[1]))$t=$m[2];
 $t=preg_replace('~^\d+\s*:\s*(Set\s+[A-Z]\b\s*)?~u','',$t);
 /* Drop the directions before the story, and stop where the questions start. */
 $end='~\b(answer the|answer these|questions?\s+(below|that follow)|circle the|choose the|directions?\s*:|instructions?\s*:)|\bpart\s+[ivx]+\b|^\(?\d+[.)]\s|\b(vocabulary focus|aral connection|connection|focus|skills?)\s*:~iu';
 $ss=preg_split('~(?<=[.!?])\s+|\s+·\s+~u',$t);$out=[];$in=false;
 foreach($ss as $x){
  if(!$in){if(preg_match($how,$x)||preg_match('~:\s*$~u',$x))continue;$in=true;}elseif(preg_match($end,$x))break;$out[]=$x;}
 return trim(implode(' ',$out));
}

/** v185: Voice check. A "say it" card with no text to read (a picture to name, a word with an answer key) gets the words
    BULIG listens for: while the pupil speaks the words heard show live, and the card turns green with a check when the
    right word is said. Each "try again" counts as a wrong try (a third of the card's points). */
function vc_words(array $a,array $c,int $n):array{
 if(!function_exists('say_words'))return [];$id=activity_key_id($a);
 $w=say_words()[$id.':'.$n]??[];if($w)return array_values(array_map('strval',$w));
 $k=(activity_key($a)??[])[$n]??null;if(!$k)return [];
 if(($k['t']??'')==='choice')return [implode('|',array_map('key_letters',array_merge([(string)$k['a']],(array)($k['alt']??[]))))];
 $alts=[];foreach((array)($k['alts']??[]) as $alt)if(count($alt)===1&&($alt[0]['k']??'')==='eq'&&preg_match('~^[\p{L}\']+( [\p{L}\']+){0,3}$~u',(string)$alt[0]['v']))$alts[]=(string)$alt[0]['v'];
 return $alts?[implode('|',$alts)]:[];
}

