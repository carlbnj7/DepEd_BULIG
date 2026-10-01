<?php
/** Bulk pupil import: read a class list (CSV or Excel .xlsx), check every row, then create the accounts. */
const IMPORT_MAX_ROWS=300;

function create_pupil_account(array $teacher,string $name,array $section,int $level,array $details):string{
 $next=(int)val('SELECT next_value FROM id_sequences WHERE kind=? FOR UPDATE',['pupil']);q('UPDATE id_sequences SET next_value=next_value+1 WHERE kind=?',['pupil']);$public=(string)$next;
 q('INSERT INTO users(public_id,role,name,password_hash) VALUES(?,?,?,?)',[$public,'pupil',$name,password_hash('12345678',PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();
 q('INSERT INTO pupils(user_id,grade_level,section) VALUES(?,?,?)',[$id,(int)$section['grade_level'],$section['name']]);save_pupil_details($id,$details);
 q('INSERT INTO pupil_sections VALUES(?,?)',[$id,$section['id']]);q('INSERT INTO teacher_pupils VALUES(?,?)',[$teacher['id'],$id]);
 q('INSERT INTO pupil_level_assignments(pupil_id,level_id,assigned_by) VALUES(?,?,?)',[$id,$level,$teacher['id']]);
 return $public;
}

/** Rows of cell strings from an uploaded .csv or .xlsx file. */
function import_read_file(string $path,string $original):array{
 $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
 if($ext==='xls')fail('This is an old Excel file (.xls). In Excel choose File → Save As → Excel Workbook (.xlsx) or CSV, then upload again.');
 if($ext==='xlsx')return import_read_xlsx($path);
 if(!in_array($ext,['csv','txt'],true))fail('Upload a CSV file or an Excel file (.xlsx).');
 $text=(string)file_get_contents($path);
 if(str_starts_with($text,"\xFF\xFE")||str_starts_with($text,"\xFE\xFF"))$text=mb_convert_encoding(substr($text,2),'UTF-8',str_starts_with($text,"\xFF\xFE")?'UTF-16LE':'UTF-16BE');
 $text=preg_replace('/^\xEF\xBB\xBF/','',$text);
 if(!mb_check_encoding($text,'UTF-8'))$text=mb_convert_encoding($text,'UTF-8','Windows-1252');
 $first=strtok($text,"\n")?:'';$delims=[','=>substr_count($first,','),';'=>substr_count($first,';'),"\t"=>substr_count($first,"\t")];arsort($delims);$d=array_key_first($delims);
 $h=fopen('php://memory','r+');fwrite($h,$text);rewind($h);$rows=[];
 while(($r=fgetcsv($h,0,$d,'"','\\'))!==false){$rows[]=array_map(fn($c)=>trim((string)$c),$r);if(count($rows)>IMPORT_MAX_ROWS+20)break;}
 fclose($h);return $rows;
}
function import_read_xlsx(string $path):array{
 if(!class_exists('ZipArchive'))fail('This server cannot read Excel files. In Excel choose File → Save As → CSV, then upload the CSV.');
 $z=new ZipArchive();if($z->open($path)!==true)fail('The Excel file could not be opened. Save it again as .xlsx or CSV.');
 $strings=[];
 if(($x=$z->getFromName('xl/sharedStrings.xml'))!==false){$doc=import_xml($x);foreach($doc->si as $si){$t='';if(isset($si->t))$t=(string)$si->t;foreach($si->r as $r)$t.=(string)$r->t;$strings[]=$t;}}
 $sheet='xl/worksheets/sheet1.xml';
 if(($wb=$z->getFromName('xl/workbook.xml'))!==false&&($rels=$z->getFromName('xl/_rels/workbook.xml.rels'))!==false){
  $w=import_xml($wb);$first=$w->sheets->sheet[0]??null;
  if($first){$rid=(string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
   foreach(import_xml($rels)->Relationship as $rel)if((string)$rel['Id']===$rid){$t=ltrim((string)$rel['Target'],'/');$sheet=str_starts_with($t,'xl/')?$t:'xl/'.$t;}}
 }
 $x=$z->getFromName($sheet);$z->close();if($x===false)fail('The Excel file has no readable sheet. Save it again as .xlsx or CSV.');
 $rows=[];
 foreach(import_xml($x)->sheetData->row as $row){$cells=[];$rn=(int)$row['r'];while($rn>0&&count($rows)<$rn-1)$rows[]=[];   // keep Excel row numbers
  foreach($row->c as $c){preg_match('/^([A-Z]+)/',(string)$c['r'],$m);$col=0;foreach(str_split($m[1]??'A') as $ch)$col=$col*26+ord($ch)-64;$type=(string)$c['t'];
   $v=$type==='s'?($strings[(int)$c->v]??''):($type==='inlineStr'?(string)$c->is->t:(string)$c->v);
   if(($type===''||$type==='n')&&preg_match('/^-?\d+(\.\d+)?E\+?\d+$/i',$v))$v=sprintf('%.0f',(float)$v);
   if(($type===''||$type==='n')&&preg_match('/^\d+\.0+$/',$v))$v=preg_replace('/\.0+$/','',$v);
   $cells[$col-1]=trim($v);}
  $r=[];if($cells){$max=max(array_keys($cells));for($i=0;$i<=$max;$i++)$r[]=$cells[$i]??'';}$rows[]=$r;
  if(count($rows)>IMPORT_MAX_ROWS+20)break;}
 return $rows;
}
function import_xml(string $x):SimpleXMLElement{$prev=libxml_use_internal_errors(true);$d=simplexml_load_string($x,'SimpleXMLElement',LIBXML_NONET);libxml_use_internal_errors($prev);if(!$d)fail('The Excel file could not be read. Save it again as .xlsx or CSV.');return $d;}

/** Which column holds what: header words people use on class lists (and DepEd SF1). */
function import_columns(array $header):array{
 $alias=['name'=>['name','full name','fullname','learner name','learners name','pupil name','name of learner','name of pupil','student name','complete name'],
  'last'=>['last name','lastname','surname','family name','apelyido'],'first'=>['first name','firstname','given name','pangalan'],'middle'=>['middle name','middlename','middle initial','mi'],
  'lrn'=>['lrn','learner reference number','lrn no','lrn number','learner reference no'],'sex'=>['sex','gender','m f','kasarian'],'section'=>['section','class','seksyon'],
  'grade'=>['grade','grade level','gr'],'level'=>['starting level','bulig level','level','start level']];
 $cols=[];
 foreach($header as $i=>$h){$k=trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9]+/',' ',strtolower($h))));
  foreach($alias as $key=>$words)if(!isset($cols[$key])&&in_array($k,$words,true)){$cols[$key]=$i;continue 2;}
  // looser headers, e.g. SF1 "NAME (Last Name, First Name, Middle Name)" or "LRN No."
  if(!isset($cols['lrn'])&&preg_match('/\blrn\b/',$k))$cols['lrn']=$i;
  elseif(!isset($cols['name'])&&!isset($cols['last'])&&str_starts_with($k,'name'))$cols['name']=$i;
  elseif(!isset($cols['sex'])&&preg_match('/^(sex|gender)\b/',$k))$cols['sex']=$i;}
 return $cols;
}
function import_name_case(string $s):string{$s=trim(preg_replace('/\s+/',' ',$s));return $s!==''&&$s===mb_strtoupper($s)?mb_convert_case(mb_strtolower($s),MB_CASE_TITLE,'UTF-8'):$s;}
function import_level(string $v,int $default):?int{
 $v=strtoupper(trim(str_ireplace('level','',$v)));if($v==='')return $default;
 $map=['1'=>1,'2A'=>2,'2B'=>3,'3'=>4,'4'=>5,'5'=>6,'6'=>7,'7'=>8];return $map[$v]??null;
}

/** Check every row; nothing is saved yet. */
function import_check(array $rows,array $teacher,int $defaultSection,int $defaultLevel):array{
 $hi=null;foreach(array_slice($rows,0,10,true) as $i=>$r){$c=import_columns($r);if(isset($c['name'])||isset($c['last'])||isset($c['first'])){$hi=$i;$cols=$c;break;}}
 if($hi===null)fail('No header row found. The first row must have column names such as Name (or Last name and First name), LRN, Sex and Section. Download the template to see the format.');
 $sections=teacher_sections((int)$teacher['id']);$default=null;foreach($sections as $s)if((int)$s['id']===$defaultSection)$default=$s;
 $out=[];$seenLrn=[];$seenName=[];
 foreach(array_slice($rows,$hi+1) as $n=>$r){
  $get=fn($k)=>isset($cols[$k])?trim((string)($r[$cols[$k]]??'')):'';
  if(!array_filter($r,fn($c)=>trim((string)$c)!==''))continue;
  if(count($out)>=IMPORT_MAX_ROWS)fail('A class list can have up to '.IMPORT_MAX_ROWS.' pupils per upload. Split the file and upload it in parts.');
  $name=$get('name');
  if($name!==''&&substr_count($name,',')===1){[$l,$f]=array_map('trim',explode(',',$name));if($l!==''&&$f!=='')$name=$f.' '.$l;}   // "DELA CRUZ, JUAN S." -> "JUAN S. DELA CRUZ"
  if($name===''){$name=trim(import_name_case($get('first')).' '.import_name_case($get('middle')).' '.import_name_case($get('last')));}else $name=import_name_case($name);
  $name=trim(preg_replace('/\s+/',' ',$name));
  $lrn=preg_replace('/[\s\-\'’]/','',$get('lrn'));$sexRaw=strtolower($get('sex'));
  $sex=in_array($sexRaw,['m','male','boy','lalaki','l'],true)?'male':(in_array($sexRaw,['f','female','girl','babae','b'],true)?'female':null);
  $row=['line'=>$hi+$n+2,'name'=>$name,'lrn'=>$lrn,'sex'=>$sex,'section'=>null,'new_section'=>null,'grade'=>null,'level'=>$defaultLevel,'status'=>'ok','notes'=>[]];
  $err=function(string $m)use(&$row){$row['status']='error';$row['notes'][]=$m;};
  if(mb_strlen($name)<2||mb_strlen($name)>150)$err('Name is missing or too long.');
  if(!$sex)$err($sexRaw===''?'Sex is missing (M or F).':'Sex must be M or F.');
  if($lrn!==''&&!preg_match('/^[0-9]{12}$/D',$lrn))$err('LRN must be 12 digits (or leave it blank).');
  $grade=$get('grade');$grade=$grade!==''?(int)preg_replace('/\D/','',$grade):0;
  $secName=$get('section');
  if($secName!==''){
   $match=null;foreach($sections as $s)if(strcasecmp(trim($s['name']),$secName)===0&&(!$grade||(int)$s['grade_level']===$grade))$match=$s;
   if($match)$row['section']=$match;
   elseif($grade>=1&&$grade<=6){$row['new_section']=['name'=>mb_substr(import_name_case($secName),0,80),'grade_level'=>$grade];$row['notes'][]='New section “'.import_name_case($secName).'” (Grade '.$grade.') will be added.';}
   elseif($default)$err('Section “'.$secName.'” is not one of your sections. Add a Grade column or create the section first.');
   else $err('Section “'.$secName.'” is not one of your sections. Add a Grade column or create the section first.');
  }elseif($default)$row['section']=$default;
  else $err('Section is missing. Add a Section column or choose a section for the whole list.');
  $sec=$row['section']??$row['new_section'];if($sec)$row['grade']=(int)$sec['grade_level'];
  $lv=import_level($get('level'),$defaultLevel);if($lv===null||!val('SELECT id FROM bulig_levels WHERE id=?',[$lv]))$err('Starting level “'.$get('level').'” is not a BULIG level (use 1, 2A, 2B, 3, 4, 5, 6 or 7).');else $row['level']=$lv;
  if($row['status']==='ok'){
   if($lrn!==''&&isset($seenLrn[$lrn]))$err('Same LRN as line '.$seenLrn[$lrn].' in this file.');
   elseif($lrn!==''&&val('SELECT pupil_id FROM pupil_details WHERE lrn=?',[$lrn])){$row['status']='skip';$row['notes'][]='Already in BULIG (same LRN).';}
   else{$key=mb_strtolower($name).'|'.($sec['name']??'').'|'.$row['grade'];
    if(isset($seenName[$key]))$err('Same name and section as line '.$seenName[$key].' in this file.');
    elseif($row['section']&&val('SELECT 1 FROM users u JOIN pupil_sections ps ON ps.pupil_id=u.id JOIN teacher_pupils t ON t.pupil_id=u.id WHERE t.teacher_id=? AND ps.section_id=? AND LOWER(u.name)=LOWER(?)',[$teacher['id'],$row['section']['id'],$name])){$row['status']='skip';$row['notes'][]='Already in this section (same name).';}
    $seenName[$key]=$row['line'];}
   if($lrn!=='')$seenLrn[$lrn]=$row['line'];
  }
  $out[]=$row;
 }
 if(!$out)fail('The file has a header row but no pupils under it.');
 return $out;
}

function import_actions(string $action):void{
 if($action==='import_preview'){
  $u=require_role('teacher');$f=$_FILES['class_list']??null;
  if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)fail(($f['error']??0)===UPLOAD_ERR_INI_SIZE||($f['error']??0)===UPLOAD_ERR_FORM_SIZE?'The file is too big. Keep it under 2 MB.':'Choose a CSV or Excel file to upload.');
  if($f['size']>2*1024*1024)fail('The file is too big. Keep it under 2 MB.');
  $level=selected_start_level();$rows=import_read_file($f['tmp_name'],(string)$f['name']);
  $checked=import_check($rows,$u,(int)($_POST['section_id']??0),$level);
  $_SESSION['pupil_import']=['token'=>bin2hex(random_bytes(16)),'file'=>mb_substr(basename((string)$f['name']),0,120),'rows'=>$checked,'teacher'=>(int)$u['id']];unset($_SESSION['pupil_import_done']);
  go('?page=accounts&import=preview#import');
 }
 if($action==='import_cancel'){require_role('teacher');unset($_SESSION['pupil_import']);flash('Import cancelled. No accounts were created.');go('?page=accounts#import');}
 if($action==='import_confirm'){
  $u=require_role('teacher');$p=$_SESSION['pupil_import']??null;
  if(!$p||!hash_equals($p['token'],(string)($_POST['token']??''))||(int)$p['teacher']!==(int)$u['id'])fail('This import has expired. Upload the class list again.');
  $done=[];db()->beginTransaction();try{
   $made=[];
   foreach($p['rows'] as $r){if($r['status']!=='ok')continue;
    $section=$r['section']?owned_section((int)$r['section']['id'],(int)$u['id']):null;
    if(!$section){$key=mb_strtolower($r['new_section']['name']).'|'.$r['new_section']['grade_level'];
     if(!isset($made[$key])){$id=(int)val('SELECT id FROM sections WHERE teacher_id=? AND grade_level=? AND LOWER(name)=LOWER(?)',[$u['id'],$r['new_section']['grade_level'],$r['new_section']['name']]);
      if(!$id){q('INSERT INTO sections(teacher_id,grade_level,name) VALUES(?,?,?)',[$u['id'],$r['new_section']['grade_level'],$r['new_section']['name']]);$id=(int)db()->lastInsertId();}
      $made[$key]=owned_section($id,(int)$u['id']);}
     $section=$made[$key];}
    if($r['lrn']!==''&&val('SELECT pupil_id FROM pupil_details WHERE lrn=?',[$r['lrn']]))continue;
    $public=create_pupil_account($u,$r['name'],$section,(int)$r['level'],[$r['sex'],$r['lrn']?:null]);
    $done[]=['id'=>$public,'name'=>$r['name'],'lrn'=>$r['lrn'],'section'=>'Grade '.$section['grade_level'].' · '.$section['name']];}
   audit('import_pupils',count($done).' from '.$p['file']);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  unset($_SESSION['pupil_import']);$_SESSION['pupil_import_done']=$done;
  flash(count($done).' pupil account'.(count($done)===1?'':'s').' created. Default password: 12345678.');go('?page=accounts&import=done#import');
 }
}

function import_download(string $what):never{
 require_role('teacher');header('Content-Type: text/csv; charset=utf-8');
 if($what==='template'){header('Content-Disposition: attachment; filename="BULIG-class-list-template.csv"');
  echo "\xEF\xBB\xBF".'Last name,First name,Middle name,LRN,Sex,Grade,Section,Starting level'."\r\n".'Dela Cruz,Juan,Santos,123456789012,M,1,Sampaguita,1'."\r\n".'Reyes,Ana,Lopez,,F,1,Sampaguita,1'."\r\n";exit;}
 $done=$_SESSION['pupil_import_done']??[];if(!$done)fail('There is no finished import to download.',404);
 header('Content-Disposition: attachment; filename="BULIG-new-pupil-logins.csv"');$h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");
 fputcsv($h,['Pupil ID','Name','LRN','Section','Default password'],',','"','\\');foreach($done as $d)fputcsv($h,[$d['id'],$d['name'],$d['lrn']!==''?'="'.$d['lrn'].'"':'',$d['section'],'12345678'],',','"','\\');exit;
}

function import_card(array $u):void{
 $sections=teacher_sections((int)$u['id']);$p=$_SESSION['pupil_import']??null;$done=$_SESSION['pupil_import_done']??null;$mode=(string)($_GET['import']??'');
 echo '<section class="card import-card" id="import"><div class="section-heading"><div><span class="eyebrow">ADD A WHOLE CLASS AT ONCE</span><h2>Import a class list</h2></div><a class="btn quiet small" href="?page=import_template">'.icon('arrow').'Download template</a></div>';
 if($mode==='preview'&&$p){
  $ok=count(array_filter($p['rows'],fn($r)=>$r['status']==='ok'));$skip=count(array_filter($p['rows'],fn($r)=>$r['status']==='skip'));$bad=count(array_filter($p['rows'],fn($r)=>$r['status']==='error'));
  echo '<p class="import-summary"><strong>'.e($p['file']).'</strong> · <span class="pill ok">'.$ok.' ready</span> <span class="pill skip">'.$skip.' already in BULIG</span> <span class="pill bad">'.$bad.' need fixing</span></p>';
  if($bad)echo '<p class="muted">Rows that need fixing will be left out. Fix them in your file and upload again, or import the ready rows now and add the rest later.</p>';
  echo '<div class="tablewrap import-preview"><table><thead><tr><th>Line</th><th>Name</th><th>LRN</th><th>Sex</th><th>Section</th><th>Start</th><th>Check</th></tr></thead><tbody>';
  foreach($p['rows'] as $r){$sec=$r['section']??$r['new_section'];echo '<tr class="row-'.$r['status'].'"><td>'.$r['line'].'</td><td>'.e($r['name']?:'—').'</td><td>'.e($r['lrn']?:'—').'</td><td>'.e($r['sex']?ucfirst($r['sex']):'—').'</td><td>'.e($sec?'Grade '.$sec['grade_level'].' · '.$sec['name']:'—').'</td><td>'.e(level_label((int)$r['level'])).'</td><td><span class="pill '.($r['status']==='ok'?'ok':($r['status']==='skip'?'skip':'bad')).'">'.($r['status']==='ok'?'Ready':($r['status']==='skip'?'Skip':'Fix')).'</span>'.($r['notes']?'<small>'.e(implode(' ',$r['notes'])).'</small>':'').'</td></tr>';}
  echo '</tbody></table></div><div class="import-actions">';
  if($ok)echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="import_confirm"><input type="hidden" name="token" value="'.e($p['token']).'"><button class="btn primary">'.icon('check').'Create '.$ok.' pupil account'.($ok===1?'':'s').'</button></form>';
  echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="import_cancel"><button class="btn secondary">Cancel</button></form></div></section>';return;
 }
 if($mode==='done'&&$done){
  echo '<p class="import-summary"><span class="pill ok">'.count($done).' accounts created</span> Every new pupil signs in with their Pupil ID and the password <strong>12345678</strong>.</p><div class="import-actions"><a class="btn primary" href="?page=import_logins">'.icon('arrow').'Download login list (CSV)</a><a class="btn secondary" href="?page=accounts#import">Import another list</a></div>';
  echo '<div class="tablewrap import-preview"><table><thead><tr><th>Pupil ID</th><th>Name</th><th>LRN</th><th>Section</th></tr></thead><tbody>';foreach($done as $d)echo '<tr><td><strong>'.e($d['id']).'</strong></td><td>'.e($d['name']).'</td><td>'.e($d['lrn']?:'—').'</td><td>'.e($d['section']).'</td></tr>';echo '</tbody></table></div></section>';return;
 }
 echo '<ol class="import-steps"><li>Open your class list in Excel (or the DepEd SF1). Keep a header row with <strong>Name</strong> (or Last name, First name, Middle name), <strong>LRN</strong>, <strong>Sex</strong> and, if you like, <strong>Grade</strong>, <strong>Section</strong> and <strong>Starting level</strong>.</li><li>Upload the .xlsx or .csv file. Nothing is saved yet — you will see every row first.</li><li>Press <strong>Create accounts</strong>, then download the login list to give each pupil their ID.</li></ol>';
 echo '<form method="post" enctype="multipart/form-data" class="formgrid import-form">'.csrf_field().'<input type="hidden" name="action" value="import_preview"><input type="hidden" name="MAX_FILE_SIZE" value="2097152"><label class="import-file">Class list file (.xlsx or .csv)<input type="file" name="class_list" accept=".csv,.xlsx,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>';
 echo '<label>Section for pupils without one<select name="section_id"><option value="0">Use the Section column</option>';foreach($sections as $s)echo '<option value="'.$s['id'].'">Grade '.$s['grade_level'].' · '.e($s['name']).'</option>';echo '</select></label>'.starting_level_field(1).'<div class="formend"><small>Up to '.IMPORT_MAX_ROWS.' pupils, 2 MB. Sections not found are added when the file has a Grade column. Pupils already in BULIG (same LRN) are skipped.</small><button class="btn primary">'.icon('arrow').'Check the list</button></div></form></section>';
}
