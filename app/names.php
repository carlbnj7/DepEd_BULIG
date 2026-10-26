<?php
/** Names in parts: First name, Middle name, Last name and an Extension (Jr., Sr., II …).
   users.name keeps the name BULIG shows everywhere ("Juan S. Dela Cruz Jr."); table user_names (030_name_parts.sql) keeps the parts.
   Without that table the forms still work: only the parts are not kept. */
const NAME_EXTS=['Jr.','Sr.','II','III','IV','V'];

function names_ready():bool{static $r=null;if($r===null){try{db()->query('SELECT 1 FROM user_names LIMIT 1');$r=true;}catch(Throwable $e){$r=false;}}return $r;}

/** "jr", "JR.", "3rd" … to Jr., III …; '' when blank, null when it is not an extension. */
function name_ext_norm(string $x):?string{
 $k=strtolower(trim(str_replace('.','',$x)));if($k==='')return '';
 return ['jr'=>'Jr.','sr'=>'Sr.','ii'=>'II','iii'=>'III','iv'=>'IV','v'=>'V','2nd'=>'II','3rd'=>'III','4th'=>'IV','5th'=>'V'][$k]??null;
}
/** The name BULIG shows: first name, middle initial, last name, extension. */
function name_compose(string $first,string $middle,string $last,string $ext=''):string{
 $mi=$middle!==''?mb_strtoupper(mb_substr($middle,0,1)).'.':'';
 return trim(preg_replace('/\s+/',' ',$first.' '.$mi.' '.$last.' '.$ext));
}

/** The name typed in the "Add one" forms: ['name'=>shown name,'parts'=>[first,middle,last,ext]].
   A form that still sends one Full name box (an older page) gives parts null. */
function name_input():array{
 [$f,$m,$l]=array_map(fn($k)=>import_name_case((string)($_POST[$k]??'')),['first_name','middle_name','last_name']);
 if($f===''&&$m===''&&$l===''&&isset($_POST['name']))return ['name'=>trim((string)$_POST['name']),'parts'=>null];
 if($f===''||$l==='')fail('Type the first name and the last name.');
 foreach([$f,$m,$l] as $s)if(mb_strlen($s)>60)fail('Each part of the name can have up to 60 letters.');
 $x=name_ext_norm((string)($_POST['name_ext']??''));if($x===null)fail('Choose an extension from the list, or None.');
 return ['name'=>name_compose($f,$m,$l,$x),'parts'=>[$f,$m,$l,$x]];
}
function name_parts_save(int $id,?array $p):void{
 if(!$p||!names_ready())return;
 q('INSERT INTO user_names(user_id,first_name,middle_name,last_name,name_ext) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE first_name=VALUES(first_name),middle_name=VALUES(middle_name),last_name=VALUES(last_name),name_ext=VALUES(name_ext)',[$id,$p[0],$p[1]?:null,$p[2],$p[3]?:null]);
}
/** The name was typed again as one line (Edit account): the old parts no longer match. */
function name_parts_forget(int $id):void{if(names_ready())q('DELETE FROM user_names WHERE user_id=?',[$id]);}

/** The parts of a saved name [first,middle,last,ext]: from user_names, or guessed from the shown name
   ("Maria S. Santos Jr." → Maria · S · Santos · Jr.) for accounts made before the parts were kept. */
function name_parts_of(int $id,string $name):array{
 if(names_ready()&&($r=one('SELECT first_name,middle_name,last_name,name_ext FROM user_names WHERE user_id=?',[$id])))return [(string)$r['first_name'],(string)$r['middle_name'],(string)$r['last_name'],(string)$r['name_ext']];
 $w=preg_split('/\s+/',trim($name),-1,PREG_SPLIT_NO_EMPTY);$x='';
 if(count($w)>2&&($n=name_ext_norm(end($w)))){$x=$n;array_pop($w);}
 if(count($w)<2)return [implode(' ',$w),'','',$x];
 $last=array_pop($w);$mid='';
 if(count($w)>1&&preg_match('/^\p{L}\.$/u',end($w)))$mid=rtrim(array_pop($w),'.');
 return [implode(' ',$w),$mid,$last,$x];
}

/** First, Middle, Last and Extension boxes, with "Shows as …" filled in while typing (assets/names.js).
   $v [first,middle,last,ext] fills them in (editing a saved name). */
function name_fields(?array $v=null):string{
 [$f,$m,$l,$xv]=$v??['','','',''];
 $o='';foreach(NAME_EXTS as $x)$o.='<option value="'.$x.'"'.($x===$xv?' selected':'').'>'.$x.'</option>';
 return '<div class="nm-row" data-nm><label>First name<input name="first_name" required maxlength="60" autocomplete="off" autocapitalize="words" value="'.e($f).'"></label>'
  .'<label>Middle name<input name="middle_name" maxlength="60" autocomplete="off" autocapitalize="words" value="'.e($m).'"><small>Leave blank if none</small></label>'
  .'<label>Last name<input name="last_name" required maxlength="60" autocomplete="off" autocapitalize="words" value="'.e($l).'"></label>'
  .'<label>Extension<select name="name_ext"><option value="">None</option>'.$o.'</select></label></div>'
  .'<p class="nm-prev" data-nm-prev hidden>Shows as <b data-nm-out></b></p>';
}
function names_script():string{return '<script defer src="'.asset_url('names.js').'"></script>';}
