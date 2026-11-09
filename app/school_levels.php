<?php
declare(strict_types=1);
/** v212 Elementary (Grades 1–6) and Junior High School (Grades 7–10).
    A school is Elementary, Junior High or Integrated (both). BULIG finds this from the DepEd School ID
    (1xxxxx elementary, 3xxxxx secondary, 5xxxxx integrated) or from the school name, and the Division can change it.
    Kept in settings (school_type_<id>), so no SQL is needed. */
const GRADES_ELEM=[1,2,3,4,5,6];
const GRADES_JHS=[7,8,9,10];
const GRADE_MAX=10;
const SCHOOL_TYPES=['elementary'=>'Elementary','jhs'=>'Junior High School','integrated'=>'Integrated (Elementary and Junior High)',
 'als_elem'=>'ALS Elementary','als_jhs'=>'ALS Junior High School','als_integrated'=>'ALS (Elementary and Junior High)'];   // v212 ALS: Alternative Learning System
function grade_ok(int $g):bool{return $g>=1&&$g<=GRADE_MAX;}
function grade_stage(int $g):string{return $g>=7?'jhs':'elementary';}
function grade_stage_name(int $g):string{return $g>=7?'Junior High School':'Elementary';}
/** Detect the type from the DepEd School ID first, then from the name. */
function school_type_detect(string $name,string $no='',string $district=''):string{
 /* ALS (Alternative Learning System): a learning center in an ALS district, or "ALS" / "Community Learning Center" in the name */
 $als=(bool)preg_match('/\bALS\b/u',$district)||(bool)preg_match('/\b(ALS|CLC|community learning cent(er|re)|alternative learning)\b/iu',$name);
 if($als){$n=' '.mb_strtolower($name).' ';if(preg_match('/ (junior high|secondary|jhs|high school) /',$n))return 'als_jhs';if(preg_match('/ (elementary|basic literacy|blp) /',$n))return 'als_elem';return 'als_integrated';}
 $no=preg_replace('/\D/','',$no);
 if(strlen($no)===6){$d=$no[0];if($d==='1')return 'elementary';if($d==='3')return 'jhs';if($d==='5')return 'integrated';}
 $n=' '.mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u',' ',$name)).' ';
 if(preg_match('/ (integrated|is) /',$n))return 'integrated';
 if(preg_match('/ (high school|national high|nhs|hs|secondary|academy|science high|shs|jhs) /',$n))return 'jhs';
 return 'elementary';
}
function school_type(int $sid):string{
 if(!$sid)return 'elementary';static $memo=[];if(isset($memo[$sid]))return $memo[$sid];
 $set=function_exists('admin_setting')?(string)admin_setting('school_type_'.$sid,''):'';
 if(isset(SCHOOL_TYPES[$set]))return $memo[$sid]=$set;
 $r=one('SELECT name,school_no,district FROM schools WHERE id=?',[$sid]);
 return $memo[$sid]=$r?school_type_detect((string)$r['name'],(string)($r['school_no']??''),(string)($r['district']??'')):'elementary';
}
function school_type_label(string $t):string{return ['elementary'=>'Elementary','jhs'=>'Junior High','integrated'=>'Integrated','als_elem'=>'ALS Elementary','als_jhs'=>'ALS Junior High','als_integrated'=>'ALS Integrated'][$t]??'Elementary';}
function school_is_als(string $t):bool{return str_starts_with($t,'als_');}
function school_grades(int $sid):array{$t=school_type($sid);return in_array($t,['jhs','als_jhs'],true)?GRADES_JHS:(in_array($t,['integrated','als_integrated'],true)?array_merge(GRADES_ELEM,GRADES_JHS):GRADES_ELEM);}
function teacher_grades(int $tid):array{$sid=(int)(val('SELECT COALESCE(school_id,0) FROM users WHERE id=?',[$tid])?:0);return $sid?school_grades($sid):array_merge(GRADES_ELEM,GRADES_JHS);}
/** Grade <option>s, grouped under Elementary and Junior High School when both are offered. */
function grade_options(int $sel,?array $grades=null,string $all=''):string{
 $grades=$grades??array_merge(GRADES_ELEM,GRADES_JHS);if($sel&&!in_array($sel,$grades,true))$grades[]=$sel;sort($grades);
 $o=$all!==''?'<option value="0"'.($sel===0?' selected':'').'>'.e($all).'</option>':'';
 $el=array_values(array_filter($grades,fn($g)=>$g<=6));$jh=array_values(array_filter($grades,fn($g)=>$g>=7));
 $opt=fn($g)=>'<option value="'.$g.'"'.($g===$sel?' selected':'').'>Grade '.$g.'</option>';
 if($el&&$jh)return $o.'<optgroup label="Elementary">'.implode('',array_map($opt,$el)).'</optgroup><optgroup label="Junior High School">'.implode('',array_map($opt,$jh)).'</optgroup>';
 return $o.implode('',array_map($opt,$grades));
}
/** A school type picker for the school form (Auto = detect). */
function school_type_field(int $sid=0):string{
 $set=$sid&&function_exists('admin_setting')?(string)admin_setting('school_type_'.$sid,''):'';
 $o='<option value="">Detect automatically</option>';foreach(['Schools'=>['elementary','jhs','integrated'],'ALS (Alternative Learning System)'=>['als_elem','als_jhs','als_integrated']] as $g=>$ks){$o.='<optgroup label="'.e($g).'">';foreach($ks as $k)$o.='<option value="'.$k.'"'.($set===$k?' selected':'').'>'.e(SCHOOL_TYPES[$k]).'</option>';$o.='</optgroup>';}
 return '<label class="sc-type">School type<select name="school_type">'.$o.'</select><small class="muted">'.($sid?'Now: '.e(SCHOOL_TYPES[school_type($sid)]):'BULIG finds it from the School ID or the name').'</small></label>';
}
function school_type_save(int $sid):void{
 if(!$sid||!function_exists('admin_set'))return;$t=(string)($_POST['school_type']??'');
 if(isset(SCHOOL_TYPES[$t]))admin_set('school_type_'.$sid,$t);elseif(function_exists('admin_setting')&&admin_setting('school_type_'.$sid,'')!=='')admin_set('school_type_'.$sid,'');
}
