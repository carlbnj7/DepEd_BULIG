<?php
/** Display-only labels and reviewed picture mappings; no database writes. */
function lesson_display_label(int $level,int $position):string{
 if($level===5)return $position===1?'Pre-test':($position>=100?'Post-test':'Passage '.($position-1));
 if($level===7){$kind=intdiv($position,100)%10;$n=$position%100;return [1=>'Pre-test',3=>'Post-test',5=>'Formative assessment'][$kind]??'Activity '.$n;}
 if($level===6){$kind=intdiv($position,100)%10;$n=$position%100;return [1=>'Pre-test',3=>'Post-test',4=>'Activity '.$n.' · second set'][$kind]??'Activity '.$n;}
 if(in_array($level,[2,3,4],true)){
  $lessons=module_source_meta($level)['lessons']??[];
  foreach($lessons as $lesson)if((int)$lesson['position']===$position){
   if($lesson['phase']==='pre')return 'Pre-assessment';
   if($lesson['phase']==='post')return 'Post-assessment';
   $number=0;foreach($lessons as $item)if($item['phase']==='learn'&&(int)$item['position']<=$position)$number++;
   return 'Lesson '.$number;
  }
 }
 return 'Lesson '.$position;
}
function lesson_count_label(int $level,int $total):string{
 if($level===5&&$total>2)return ($total-2).' passages + pre/post tests';
 if($level===6)return $total.' activities and tests';
 if($level===7)return $total.' stories and tests';
 return in_array($level,[2,3,4],true)&&$total>2?($total-2).' lessons + pre/post assessments':$total.' lessons';
}
function level1_visual_manifest():array{
 static $m=null;if($m===null){$f=__DIR__.'/../database/level1-visuals-v11.json';$m=is_file($f)?json_decode(file_get_contents($f),true):[];}return $m??[];
}
function level1_visual_override(array $a):?array{
 $m=level1_visual_manifest()['activities'][(string)($a['id']??0)]??null;
 if(!$m||(int)($a['lesson_id']??0)!==(int)$m['lesson_id']||(int)($a['revision']??1)>1)return null;
 // Do not attach a stock picture to changed/custom text.
 if(!hash_equals($m['prompt_sha256'],hash('sha256',(string)($a['prompt']??''))))return null;
 return $m;
}
/** Level 3 reading lists shown as a table of word boxes, like the printed module. */
function level3_reading_grid(array $a):?string{
 if(($a['type']??'')!=='reading'||lesson_level((int)($a['lesson_id']??0))!==4)return null;
 $prompt=trim(str_replace(["\r\n","\r"],"\n",(string)$a['prompt']));
 if(str_contains($prompt,"\n"))$items=array_values(array_filter(array_map('trim',explode("\n",$prompt)),'strlen'));
 else $items=preg_split('/\s{2,}/',$prompt,-1,PREG_SPLIT_NO_EMPTY);
 if(count($items)<2)return null;
 $long=max(array_map(fn($w)=>mb_strlen($w),$items))>14;
 $html='<div class="word-grid'.($long?' word-grid-lines':'').'" role="list">';
 foreach($items as $w)$html.='<span class="word-box" role="listitem">'.e($w).'</span>';
 return $html.'</div>';
}
/** Level 4 fluency passage: "Title:", "Author:" and "Words:" header lines, a blank line, then the text (blank lines between paragraphs or stanzas). */
function level4_passage_parts(array $a):?array{
 if(($a['type']??'')!=='reading'||lesson_level((int)($a['lesson_id']??0))!==5)return null;
 $text=str_replace("\r\n","\n",(string)$a['prompt']);$head=[];
 while(preg_match('/^(Title|Author|Words):[ \t]*(.*)\n/',$text,$m)){$head[strtolower($m[1])]=trim($m[2]);$text=substr($text,strlen($m[0]));}
 $blocks=array_values(array_filter(array_map('trim',preg_split("/\n\s*\n/",$text)),'strlen'));
 $words=(int)($head['words']??0)?:count(preg_split('/\s+/',trim(implode(' ',$blocks))));
 return ['title'=>$head['title']??'','author'=>$head['author']??'','words'=>$words,'blocks'=>$blocks];
}
function level4_passage(array $a,bool $pictures=true):?string{
 $p=level4_passage_parts($a);if(!$p)return null;
 $verse=count(array_filter($p['blocks'],fn($b)=>str_contains($b,"\n")))>0;
 $h='<article class="fluency-passage'.($verse?' is-verse':'').'" data-words="'.$p['words'].'">';
 if($pictures){$imgs=activity_images($a);if($imgs){$h.='<div class="fluency-pictures">';foreach($imgs as $i)$h.='<img src="'.e($i).'" alt="Picture from the module for this passage" loading="lazy">';$h.='</div>';}}
 $h.='<h2 class="fluency-title">'.e($p['title']).'</h2>'.($p['author']!==''?'<p class="fluency-author">'.e($p['author']).'</p>':'').'<div class="fluency-text">';
 foreach($p['blocks'] as $b)$h.='<p>'.nl2br(e($b),false).'</p>';
 return $h.'</div><p class="fluency-count">Number of words in the passage: <strong>'.$p['words'].'</strong></p></article>';
}
/** Level 5 module page as native content: text lines and separately cut pictures in the page's rows. */
function level5_pages():array{static $m;if($m===null){$f=__DIR__.'/../database/level5-pages.json';$m=is_file($f)?(json_decode(file_get_contents($f),true)??[]):[];}return $m;}
function level5_sheet(array $a):?string{
 $lid=(int)($a['lesson_id']??0);if(lesson_level($lid)!==6)return null;
 $p=level5_pages()[lesson_grade($lid).':'.(int)$a['source_page']]??null;
 if(!$p||!hash_equals($p['sha'],hash('sha256',(string)$a['prompt'])))return null;   // edited text: show the plain prompt
 $h='<div class="l5-sheet">';
 foreach($p['rows'] as $row){
  $h.='<div class="l5-row'.(count($row)>1?' l5-cols':'').'">';
  foreach($row as $col){
   $h.='<div class="l5-col">';
   foreach($col as $it){
    if($it['t']==='img'){$src=resolved_image_path($it['src']);if(!$src)continue;$h.='<figure class="l5-pic'.(!empty($it['grid'])?' l5-grid':'').'"><img src="'.e($src).'" alt="'.e($it['cap']!==''?$it['cap']:'Picture from the module').'" loading="lazy">'.($it['cap']!==''?'<figcaption>'.e($it['cap']).'</figcaption>':'').'</figure>';}
    else $h.='<p class="l5-t'.($it['k']?' l5-'.$it['k']:'').'">'.e($it['s']).'</p>';
   }
   $h.='</div>';
  }
  $h.='</div>';
 }
 return $h.'</div>';
}
/** Level 5 text: join lines the PDF wrapped mid-sentence; keep numbered items, choices and headings on their own lines. */
function level5_reflow(string $t):string{
 $out=[];
 foreach(preg_split('/\R/',$t) as $l){$l=trim($l);if($l==='')continue;
  $prev=$out?end($out):'';
  if($out&&!preg_match('/[.?!:;”"’)]$/u',$prev)&&!preg_match('/^(\d{1,2}[.)]|[a-dA-D][.)]|[IVX]+\.|Part\b|Activity\b|Direction|Instruction|Question|Column)/u',$l)&&preg_match('/^[a-z“"‘(,]/u',$l)){$out[count($out)-1].=' '.$l;continue;}
  $out[]=$l;}
 return implode("\n",$out);
}
