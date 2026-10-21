<?php
/** Read the first sheet of an old Excel file (.xls, Excel 97-2003, the format LIS gives the DepEd SF1 in).
   Returns rows of cell strings, keeping Excel row and column positions, like import_read_xlsx(). */

function xls_u16(string $s,int $o):int{return ord($s[$o])|ord($s[$o+1])<<8;}
function xls_u32(string $s,int $o):int{return unpack('V',substr($s,$o,4))[1];}

/** The "Workbook" stream from the OLE2 container. */
function xls_workbook_stream(string $d):string{
 if(strlen($d)<512||substr($d,0,8)!=="\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")fail('This .xls file could not be read. In Excel choose File → Save As → Excel Workbook (.xlsx), then upload again.');
 $ss=1<<xls_u16($d,0x1E);$mss=1<<xls_u16($d,0x20);$cutoff=xls_u32($d,0x38);
 $sector=fn(int $id)=>substr($d,($id+1)*$ss,$ss);
 $difat=[];for($i=0;$i<109;$i++)$difat[]=xls_u32($d,0x4C+$i*4);
 for($id=xls_u32($d,0x44),$n=0;$id<0xFFFFFFFA&&$n<xls_u32($d,0x48)+1&&$n<10000;$n++){$s=$sector($id);for($i=0;$i<$ss/4-1;$i++)$difat[]=xls_u32($s,$i*4);$id=xls_u32($s,$ss-4);}
 $fat=[];foreach($difat as $id){if($id>=0xFFFFFFFA)continue;$s=$sector($id);for($i=0;$i<$ss/4;$i++)$fat[]=xls_u32($s,$i*4);}
 $chain=function(int $id,array $table,callable $get)use($ss):string{$out='';$seen=0;while($id<0xFFFFFFFA&&isset($table[$id])&&$seen++<200000){$out.=$get($id);$id=$table[$id];}return $out;};
 $dir=$chain(xls_u32($d,0x30),$fat,$sector);$root=null;$book=null;
 for($o=0;$o+128<=strlen($dir);$o+=128){$len=xls_u16($dir,$o+0x40);$name=$len>2?mb_convert_encoding(substr($dir,$o,$len-2),'UTF-8','UTF-16LE'):'';
  $e=['type'=>ord($dir[$o+0x42]),'start'=>xls_u32($dir,$o+0x74),'size'=>xls_u32($dir,$o+0x78)];
  if($e['type']===5)$root=$e;elseif($e['type']===2&&($name==='Workbook'||$name==='Book')&&!$book)$book=$e;}
 if(!$book)fail('This .xls file has no workbook inside. Save it again as .xlsx, then upload.');
 if($book['size']>=$cutoff)return substr($chain($book['start'],$fat,$sector),0,$book['size']);
 $mini=$chain($root['start']??0xFFFFFFFE,$fat,$sector);$mfat=[];$m=$chain(xls_u32($d,0x3C),$fat,$sector);for($i=0;$i+4<=strlen($m);$i+=4)$mfat[]=xls_u32($m,$i);
 return substr($chain($book['start'],$mfat,fn($id)=>substr($mini,$id*$mss,$mss)),0,$book['size']);
}

function xls_chars(string $raw,bool $wide):string{return $wide?mb_convert_encoding($raw,'UTF-8','UTF-16LE'):mb_convert_encoding($raw,'UTF-8','Windows-1252');}
function xls_num(float $v):string{return floor($v)==$v&&abs($v)<1e15?sprintf('%.0f',$v):rtrim(rtrim(sprintf('%.10F',$v),'0'),'.');}
function xls_rk(int $rk):float{$v=($rk&2)?(float)($rk>>2|(($rk&0x80000000)?~0x3FFFFFFF:0)):unpack('d',"\0\0\0\0".pack('V',$rk&0xFFFFFFFC))[1];return ($rk&1)?$v/100:$v;}

/** The shared strings (SST), whose text can run on into CONTINUE records. */
function xls_sst(array $pieces):array{
 $p=0;$pos=0;$out=[];
 $take=function(int $n)use(&$p,&$pos,$pieces):string{$s='';while($n>0&&isset($pieces[$p])){if($pos>=strlen($pieces[$p])){$p++;$pos=0;continue;}$k=min($n,strlen($pieces[$p])-$pos);$s.=substr($pieces[$p],$pos,$k);$pos+=$k;$n-=$k;}return $s;};
 $count=xls_u32($take(8),4);
 for($i=0;$i<$count&&isset($pieces[$p]);$i++){
  $h=$take(3);if(strlen($h)<3)break;$cch=xls_u16($h,0);$f=ord($h[2]);$rt=($f&8)?xls_u16($take(2),0):0;$sz=($f&4)?xls_u32($take(4),0):0;
  $wide=(bool)($f&1);$s='';
  while($cch>0&&isset($pieces[$p])){
   if($pos>=strlen($pieces[$p])){$p++;$pos=0;if(!isset($pieces[$p]))break;$wide=(bool)(ord($pieces[$p][0])&1);$pos=1;continue;}   // a CONTINUE starts with a new flags byte
   $n=min($cch,intdiv(strlen($pieces[$p])-$pos,$wide?2:1));if($n<1){$pos=strlen($pieces[$p]);continue;}
   $s.=xls_chars(substr($pieces[$p],$pos,$n*($wide?2:1)),$wide);$pos+=$n*($wide?2:1);$cch-=$n;}
  $take($rt*4+$sz);$out[]=$s;}
 return $out;
}

function xls_read(string $path,int $maxRows):array{
 $w=xls_workbook_stream((string)file_get_contents($path));$len=strlen($w);
 $rec=function(int $o)use($w,$len):?array{if($o+4>$len)return null;$t=xls_u16($w,$o);$l=xls_u16($w,$o+2);return [$t,substr($w,$o+4,$l),$o+4+$l];};
 if(!($r=$rec(0))||$r[0]!==0x0809||xls_u16($r[1],0)!==0x0600)fail('This .xls file is from a very old Excel. Save it again as .xlsx, then upload.');
 $sst=[];$sheetAt=null;$o=0;$pieces=null;
 while($r=$rec($o)){[$t,$b,$o]=$r;
  if($t===0x00FC)$pieces=[$b];elseif($t===0x003C&&$pieces!==null)$pieces[]=$b;else{if($pieces!==null){$sst=xls_sst($pieces);$pieces=null;}}
  if($t===0x0085&&$sheetAt===null&&ord($b[5])===0)$sheetAt=xls_u32($b,0);   // the first worksheet
  if($t===0x000A)break;}
 if($pieces!==null)$sst=xls_sst($pieces);
 if($sheetAt===null)fail('This .xls file has no sheet. Save it again as .xlsx, then upload.');
 $cells=[];$o=$sheetAt;$formula=null;
 $put=function(int $row,int $col,string $v)use(&$cells){$v=trim($v);if($v!=='')$cells[$row][$col]=$v;};
 while($r=$rec($o)){[$t,$b,$o]=$r;if($t===0x000A)break;
  if(count($cells)>$maxRows+60)break;
  switch($t){
   case 0x00FD:$put(xls_u16($b,0),xls_u16($b,2),$sst[xls_u32($b,6)]??'');break;   // text from the SST
   case 0x0204:$n=xls_u16($b,6);$wide=(bool)(ord($b[8])&1);$put(xls_u16($b,0),xls_u16($b,2),xls_chars(substr($b,9,$n*($wide?2:1)),$wide));break;
   case 0x0203:$put(xls_u16($b,0),xls_u16($b,2),xls_num(unpack('d',substr($b,6,8))[1]));break;
   case 0x027E:$put(xls_u16($b,0),xls_u16($b,2),xls_num(xls_rk(xls_u32($b,6))));break;
   case 0x00BD:$row=xls_u16($b,0);$c=xls_u16($b,2);for($i=4;$i+6<=strlen($b)-2;$i+=6)$put($row,$c++,xls_num(xls_rk(xls_u32($b,$i+2))));break;
   case 0x0006:$row=xls_u16($b,0);$c=xls_u16($b,2);
    if(substr($b,12,2)==="\xFF\xFF"){if(ord($b[6])===0)$formula=[$row,$c];}else $put($row,$c,xls_num(unpack('d',substr($b,6,8))[1]));break;
   case 0x0207:if($formula){$n=xls_u16($b,0);$wide=(bool)(ord($b[2])&1);$put($formula[0],$formula[1],xls_chars(substr($b,3,$n*($wide?2:1)),$wide));$formula=null;}break;
  }}
 if(!$cells)return [];
 $rows=[];$last=max(array_keys($cells));
 for($i=0;$i<=$last;$i++){$r=[];if(isset($cells[$i])){$max=max(array_keys($cells[$i]));for($c=0;$c<=$max;$c++)$r[]=$cells[$i][$c]??'';}$rows[]=$r;}
 return $rows;
}
