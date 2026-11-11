<?php
/* Builds database/answer-keys-2.json from database/keys2/*.txt and checks every answer against the real card.
   These are the answer keys BULIG judged itself (the modules of Levels 2A, 2B and 3 have no answer key, and Levels 5-7
   leave many typed answers out). Run: php tools/keys/build2.php   (needs the database, like the app)

   Line format:  level:grade:lesson position:phase:position | n=answer n=answer ...   (# starts a note)
   One answer per card. "a|b" or "a~b" = either is right. "a;b" = one answer for each part of the card (first part, second part).
     word or choice     the chip with that text, or the typed letters (spaces and marks are ignored)
     #2                 the second chip
     P2                 picture 2 (one picture);  P1+P3+P4?  several pictures, "?" = allowed but not needed;  ...^5 = at least 5 of them
     a+b+c              several chips (all of them, nothing else)
     1-7,2-6            pictures that go together (pairs)
     onset              the cut between onset and rime (b | at)
     tax/i              syllables: any cut with the same number of syllables, each with a vowel, is right
     build              the Build a Word word of the card
     has:a,b  has2:a,b,c  typed answer that contains a and b / at least two of a, b, c
     -                  this part is not marked (for example tapping each word while reading)
     seg:mat sound:mat words:a,b,c   typed sounds of "mat" / the words a, b and c */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../app/bootstrap.php';
$acts=[];foreach(rows('SELECT a.*,l.position lpos,IFNULL(m.grade_level,0) g,m.level_id lv FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE a.published=1 AND l.published=1') as $a)$acts[$a['lv'].':'.$a['g'].':'.$a['lpos'].':'.$a['phase'].':'.$a['position']]=$a;
$keys=[];$notes=[];$errors=[];$count=0;
function k2_text(string $s):string{return preg_replace('~[^\p{L}\p{N}\']+~u','',mb_strtolower(str_replace(['’','‘'],"'",$s)));}
function k2_choice(array $ch,string $one):?string{
 if($one!==''&&$one[0]==='#')return $ch[(int)substr($one,1)-1]??null;
 foreach($ch as $x)if(key_norm($x)===key_norm($one))return $x;
 foreach($ch as $x)if(preg_match('~^\s*'.preg_quote($one,'~').'(?:[.)\s–-]|$)~iu',$x))return $x;
 return null;
}
/* One part of a card: [part, shown answer] or an error message. */
function k2_part(string $v,string $kind,array $c,int $p){
 $ch=array_values($c['ui']['opts'][$p]??$c['choices']??[]);$imgs=$c['images']??[];$ref=$c['ui']['ref']??-1;
 if($v==='-')return [['k'=>'any'],''];
 if($kind==='build'){if($v!=='build'||empty($c['build']['word']))return 'not a Build a Word card';return [['k'=>'build','v'=>k2_text($c['build']['word'])],$c['build']['word']];}
 if($kind==='split'){$w=(string)($c['ui']['word']??$c['text']);
  if($v==='onset'){if(!preg_match('~^([^aeiou]+)(?=[aeiou])~ui',$w,$m))return "no onset in '$w'";$on=$m[1];return [['k'=>'onset','v'=>mb_strtolower($on.'|'.mb_substr($w,mb_strlen($on)))],$on.' | '.mb_substr($w,mb_strlen($on))];}
  if(strpos($v,'/')===false)return "split '$v' needs slashes";if(k2_text($v)!==k2_text($w))return "split '$v' does not spell '$w'";return [['k'=>'syl','n'=>count(explode('/',$v))],$v];}
 if($kind==='pick'||$kind==='picks'){
  if(!preg_match('~^(P\d+\??(?:\+P\d+\??)*)(?:\^(\d+))?$~',$v,$m))return "picture answer '$v' should look like P2 or P1+P3";$req=[];$opt=[];
  foreach(explode('+',$m[1]) as $t){$n=(int)substr($t,1);if($n<1||$n>count($imgs))return "picture $n does not exist";if($n-1===$ref)return "picture $n is the example picture";str_ends_with($t,'?')?$opt[]=$n:$req[]=$n;}
  return [['k'=>'set','req'=>$req,'opt'=>$opt,'min'=>isset($m[2])?(int)$m[2]:0,'pics'=>1],($m[2]??'')?'any '.$m[2].' of pictures '.implode(', ',array_merge($req,$opt)):'Picture '.implode(', ',$req)];}
 if($kind==='pairs'){if(!preg_match_all('~(\d+)-(\d+)~',$v,$mm,PREG_SET_ORDER))return "pairs '$v'";$pr=[];foreach($mm as $m){$a=[(int)$m[1],(int)$m[2]];sort($a);if(max($a)>count($imgs))return "picture ".max($a)." does not exist";$pr[]=$a;}return [['k'=>'pairs','v'=>$pr],implode(', ',array_map(fn($x)=>$x[0].'–'.$x[1],$pr))];}
 if($kind==='num'){if(!ctype_digit($v))return "number '$v'";return [['k'=>'eq','v'=>$v],$v];}
 if($kind==='chips'){$set=[];$opt=[];foreach(explode('+',$v) as $t){$o=str_ends_with($t,'?');$t=rtrim($t,'?');$x=k2_choice($ch,$t);if($x===null)return "'$t' is not one of: ".implode(' | ',$ch);$o?$opt[]=key_norm($x):$set[]=key_norm($x);}return [['k'=>'set','req'=>$set,'opt'=>$opt,'min'=>0],str_replace('+',', ',str_replace('?','',$v))];}
 if($kind==='chip'){if(!$ch)return 'chip card without choices';$x=k2_choice($ch,$v);if($x===null)return "'$v' is not one of: ".implode(' | ',$ch);$lab=$c['ui']['labels'][$p]??'';return [['k'=>'eq','v'=>key_norm($lab!==''?$lab.' = '.$x:$x)],$lab!==''?$lab.' = '.$x:$x];}   // a labelled row (v180): "hen = 3"
 if(in_array($kind,['tiles','write','xcells'],true)){   // xcells (v180): the word box or crossword answers, typed letter by letter
  if(preg_match('~^has(2?):(.+)$~',$v,$m)){$w=array_map('k2_text',explode(',',$m[2]));return [['k'=>'has','v'=>$w,'n'=>$m[1]==='2'?2:count($w)],($m[1]==='2'?'two of: ':'').implode(', ',explode(',',$m[2]))];}
  if(preg_match('~^words:(.+)$~',$v,$m)){$w=array_map('k2_text',explode(',',$m[1]));return [['k'=>'has','v'=>$w,'n'=>count($w)],str_replace(',',', ',$m[1])];}
  $v=preg_replace('~^(seg|sound):~','',$v);return [['k'=>'eq','v'=>k2_text($v)],$v];}
 return "card kind '$kind' cannot be marked";
}
foreach(glob(__DIR__.'/../../database/keys2/*.txt') as $file)foreach(file($file) as $ln=>$line){
 $line=rtrim($line);if($line===''||$line[0]==='#')continue;
 $note='';if(preg_match('~\s#\s~',$line,$mm,PREG_OFFSET_CAPTURE)){$note=trim(substr($line,$mm[0][1]+2));$line=trim(substr($line,0,$mm[0][1]));}
 [$k,$rest]=array_map('trim',explode('|',$line,2)+[1=>'']);$where=basename($file).':'.($ln+1)." $k";
 if(!isset($acts[$k])){$errors[]="$where: no such activity";continue;}
 $set=l2a_set($acts[$k]);if(!$set){$errors[]="$where: no cards";continue;}
 foreach(preg_split('~\s+(?=\d+=)~',trim($rest),-1,PREG_SPLIT_NO_EMPTY) as $tok){
  if(!preg_match('~^(\d+)=(.+)$~s',$tok,$m)){$errors[]="$where: cannot read '$tok'";continue;}
  $n=(int)$m[1];$c=$set['cards'][$n-1]??null;if(!$c){$errors[]="$where: card $n does not exist";continue;}
  $kinds=array_values($c['ui']['answer']??[]);$alts=[];$shown=[];
  foreach(preg_split('/[|~]/',trim($m[2])) as $alt){$parts=explode(';',$alt);
   if(count($parts)!==count($kinds)){$errors[]="$where: card $n needs ".count($kinds)." part(s) (".implode('+',$kinds).") but '$alt' has ".count($parts);continue 2;}
   $one=[];$txt=[];foreach($parts as $p=>$v){$r=k2_part(trim($v),$kinds[$p],$c,$p);if(is_string($r)){$errors[]="$where: card $n: $r";continue 3;}$one[]=$r[0];if($r[1]!=='')$txt[]=$r[1];}
   $alts[]=$one;$shown[]=implode(' · ',$txt);}
  $keys[$k][$n]=['t'=>'card','alts'=>$alts,'w'=>implode(' or ',array_unique($shown))];$count++;
 }
 if($note!=='')$notes[$k][]=$note;
}
ksort($keys,SORT_NATURAL);
file_put_contents(__DIR__.'/../../database/answer-keys-2.json',json_encode(['version'=>date('Y-m-d'),'keys'=>$keys,'notes'=>$notes],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo "answers $count, activities ".count($keys).", errors ".count($errors)."\n";foreach($errors as $e)echo "  ! $e\n";
