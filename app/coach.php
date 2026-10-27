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
function wc_story(?array $s):?array{
 if(!$s)return $s;
 foreach($s['cards'] as &$c){$k=$c['ui']['answer']??[];if(!in_array($k,[['look'],['speed']],true))continue;
  $t=trim(preg_replace('~\s+~u',' ',(string)($c['text']??'')));if(str_word_count($t)<20)continue;
  $c['ui']['answer'][]='read';}
 unset($c);return $s;
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

