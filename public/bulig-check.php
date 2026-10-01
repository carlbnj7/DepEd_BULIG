<?php
/* BULIG installation check. Read-only; shows no passwords. Delete this file after use. */
require __DIR__.'/../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$nonce=base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self'; frame-ancestors 'none'");
$rows=[];
function row(string $label,bool $ok,string $detail,string $fix=''){global $rows;$rows[]=[$label,$ok,$detail,$fix];}
$root=realpath(__DIR__.'/..');
row('Site folder in use',true,$root);
$views=@file_get_contents(__DIR__.'/../app/views.php')?:'';
preg_match('/theme\.css\?v=(\d+)/',$views,$m);
row('New app/views.php',isset($m[1])&&(int)$m[1]>=23,isset($m[1])?'theme version v'.$m[1]:'theme.css not linked (old views.php)','Upload Part 1 again into '.$root.' so app/views.php is replaced.');
$pres=is_file(__DIR__.'/../app/presentation.php');
row('app/presentation.php',$pres,$pres?'present':'missing','Part 1 was not extracted into '.$root.'.');
$css=@file_get_contents(__DIR__.'/assets/theme.css')?:'';
row('New design file (public/assets/theme.css)',str_contains($css,'poster palette'),$css===''?'missing':(str_contains($css,'poster palette')?'current':'old version'),'Extract BULIG-Fix-A-design-and-level3-pictures.zip into '.$root.'.');
$font=is_file(__DIR__.'/assets/fonts/poppins-latin-400-normal.woff2');
row('Poppins font files',$font,$font?'present':'missing','Copy public/assets/fonts from Part 1.');
$pics=glob(__DIR__.'/assets/images/level3/*.webp')?:[];
row('Level 3 pictures',count($pics)>=480,count($pics).' of 484','Extract BULIG-Fix-A-design-and-level3-pictures.zip into '.$root.'.');
$cards=is_file(__DIR__.'/../database/level3-cards.json');
row('Level 3 card file',$cards,$cards?'present':'missing','Copy database/level3-cards.json from Part 1.');
$pages=glob(__DIR__.'/../storage/3/page-*.webp')?:[];
row('Level 3 page images (storage/3)',count($pages)>=144,count($pages).' of 144','Extract BULIG-Fix-B-level3-pages.zip into '.$root.'.');
$l2=glob(__DIR__.'/assets/images/level2a/art-*.webp')?:[];
row('Level 2A pictures (visuals update)',count($l2)>=300,count($l2).' picture files','Extract BULIG-Fix-D1 and D2 ZIPs into '.$root.'.');
$pdf=is_file(__DIR__.'/../storage/level3-original.pdf');
row('Level 3 original PDF',$pdf,$pdf?'present':'missing','Extract BULIG-Fix-C-level3-pdf.zip into '.$root.'.');
$l4pics=glob(__DIR__.'/assets/images/level4/*.webp')?:[];
row('Level 4 pictures',count($l4pics)>=80,count($l4pics).' of 80','Extract the Level 4 Part 1 ZIP into '.$root.'.');
row('Level 4 grade list (database/level4-meta.json)',is_file(__DIR__.'/../database/level4-meta.json'),is_file(__DIR__.'/../database/level4-meta.json')?'present':'missing','Extract the Level 4 Part 1 ZIP into '.$root.'.');
$l4pdf=count(glob(__DIR__.'/../storage/level4/grade-*.pdf')?:[]);$l4pages=count(glob(__DIR__.'/../storage/level4/g*/page-*.webp')?:[]);
row('Level 4 original PDFs (Grades 1–6)',$l4pdf===6,$l4pdf.' of 6','Extract the Level 4 Part 2 and Part 3 ZIPs into '.$root.'.');
row('Level 4 page images',$l4pages>=138,$l4pages.' page images','Extract the Level 4 Part 2 and Part 3 ZIPs into '.$root.'.');
$l5pics=count(glob(__DIR__.'/assets/images/level5/g*/*.webp')?:[]);
row('Level 5 pictures',$l5pics>=413,$l5pics.' of 413 pictures','Extract the Level 5 Part 1 ZIPs into '.$root.'.');
$l5pdf=count(glob(__DIR__.'/../storage/level5/grade-*.pdf')?:[]);
row('Level 5 original PDFs (Grades 1–6)',$l5pdf===6,$l5pdf.' of 6','Extract the Level 5 PDF ZIPs into '.$root.'.');
row('Level 5 grade list (database/level5-meta.json)',is_file(__DIR__.'/../database/level5-meta.json'),is_file(__DIR__.'/../database/level5-meta.json')?'present':'missing','Extract the Level 5 Part 1 ZIP into '.$root.'.');
$l5c=@json_decode((string)@file_get_contents(__DIR__.'/../database/level5-cards.json'),true);
row('Level 5 cards (newest: story pictures cut one by one, puzzles, matching pictures)',($l5c['version']??'')>='2026-10-01'&&!empty($l5c['cards']['1:15']['cards'][0]['heading'])&&!empty($l5c['cards']['1:8']['cards'][0]['choice_images']),'version: '.($l5c['version']??'old (no version)'),'Extract the NEWEST Level 5 Part 1 ZIP into '.$root.' (allow overwrite), then purge the cache.');
$l5s=count(glob(__DIR__.'/assets/images/level5/g*/s*.webp')?:[]);$l5m=count(glob(__DIR__.'/assets/images/level5/g1/m*.webp')?:[]);
row('Level 5 story pictures and Column B pictures',$l5s>=316&&$l5m>=100,$l5s.' story pictures, '.$l5m.' Grade 1 matching pictures','Extract the NEWEST Level 5 Part 1 ZIP into '.$root.'.');
$css=(string)@file_get_contents(__DIR__.'/assets/theme.css');
row('Level 5 card styles (reading-size stories, letter grids)',str_contains($css,'.native-passage')&&str_contains($css,'.letter-grid'),str_contains($css,'.native-passage')?'present':'old theme.css','Extract the NEWEST Level 5 Part 1 ZIP into '.$root.', then purge the cache.');
try{
 $l5=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=6 AND m.grade_level IS NOT NULL');
 row('Level 5 in database (6 grade modules)',$l5>=157,'lessons='.$l5,'Import database/migrations/011_level5_content.sql in phpMyAdmin (select the BULIG database first).');
 $keys=(int)val('SELECT COUNT(*) FROM activities a JOIN lessons l ON l.id=a.lesson_id JOIN modules m ON m.id=l.module_id WHERE m.level_id=6 AND m.grade_level=3 AND a.source_page IN (13,53,58) AND a.published=1');
 row('Level 5 Grade 3 answer keys hidden from pupils',$keys===0,$keys===0?'hidden':$keys.' answer-key page(s) still visible to pupils','Import 011c_level5_fixes.sql in phpMyAdmin (select the BULIG database first).');
}catch(Throwable $e){}
$l6c=@json_decode((string)@file_get_contents(__DIR__.'/../database/level6-cards.json'),true);
row('Level 6 cards (stories and questions)',count($l6c['cards']??[])>=197,count($l6c['cards']??[]).' of 197 activities','Extract the Level 6 Part 1 ZIP into '.$root.'.');
$l6pics=count(glob(__DIR__.'/assets/images/level6/g*/*.webp')?:[]);
row('Level 6 pictures',$l6pics>=150,$l6pics.' pictures','Extract the Level 6 Part 1 ZIP into '.$root.'.');
$l6pdf=count(glob(__DIR__.'/../storage/level6/grade-*.pdf')?:[]);$l6pages=count(glob(__DIR__.'/../storage/level6/g*/page-*.webp')?:[]);
row('Level 6 original PDFs (Grades 1–6)',$l6pdf===6,$l6pdf.' of 6','Upload grade-1.pdf … grade-6.pdf into '.$root.'/storage/level6.');
row('Level 6 page images (teachers)',$l6pages>=330,$l6pages.' of 330','Extract the Level 6 Part 2, 3 and 4 ZIPs into '.$root.'.');
try{
 $l6=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=7 AND m.grade_level IS NOT NULL');
 row('Level 6 in database (6 grade modules)',$l6>=133,'lessons='.$l6,'Import 012_level6_content.sql in phpMyAdmin (select the BULIG database first).');
}catch(Throwable $e){}
$l7c=@json_decode((string)@file_get_contents(__DIR__.'/../database/level7-cards.json'),true);
row('Level 7 cards (readings, tasks and questions)',count($l7c['cards']??[])>=154,count($l7c['cards']??[]).' of 154 activities','Extract the Level 7 ZIP into '.$root.'.');
$l7pics=count(glob(__DIR__.'/assets/images/level7/g*/*.webp')?:[]);
row('Level 7 pictures',$l7pics>=35,$l7pics.' pictures','Extract the Level 7 ZIP into '.$root.'.');
$l7pdf=count(glob(__DIR__.'/../storage/level7/grade-*.pdf')?:[]);$l7pages=count(glob(__DIR__.'/../storage/level7/g*/page-*.webp')?:[]);
row('Level 7 original PDFs (Grades 1–6)',$l7pdf===6,$l7pdf.' of 6','Extract the Level 7 ZIP into '.$root.'.');
row('Level 7 page images',$l7pages>=213,$l7pages.' of 213','Extract the Level 7 ZIP into '.$root.'.');
try{
 $l7=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=8 AND m.grade_level IS NOT NULL');
 row('Level 7 in database (6 grade modules)',$l7>=154,'lessons='.$l7,'Import 013_level7_content.sql in phpMyAdmin (select the BULIG database first).');
}catch(Throwable $e){}
try{
 $l4=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=5 AND m.grade_level IS NOT NULL');
 row('Level 4 in database (6 grade modules)',$l4>=69,'lessons='.$l4,'Import database/migrations/010_level4_content.sql in phpMyAdmin (select the BULIG database first).');
 $l=one('SELECT title,published FROM bulig_levels WHERE id=4');
 $n=(int)val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id=l.module_id WHERE m.level_id=4');
 row('Level 3 in database',$l&&$l['published']&&$n>=27,$l?('"'.$l['title'].'", published='.$l['published'].', lessons='.$n):'no level row','Import database/migrations/009_level3_content.sql in phpMyAdmin (select the BULIG database first).');
 $mig=array_column(rows('SELECT version FROM schema_migrations'),'version');
 row('Admin PIN update',in_array('014_admin_pin',$mig,true),in_array('014_admin_pin',$mig,true)?'applied':'not yet','Import 014_admin_pin.sql in phpMyAdmin (select the BULIG database first).');
 row('Admin tools update',in_array('015_admin_tools',$mig,true),in_array('015_admin_tools',$mig,true)?'applied':'not yet','Import 015_admin_tools.sql in phpMyAdmin (select the BULIG database first).');
 row('Database updates applied',in_array('009_level3_content',$mig,true),implode(', ',array_slice($mig,-4)),'Import 008 then 009 SQL files.');
}catch(Throwable $e){row('Database',false,'Could not read the database: '.get_class($e),'Check config/database.php.');}
$bad=count(array_filter($rows,fn($r)=>!$r[1]));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BULIG check</title>
<style>body{font:16px/1.5 system-ui,Arial;margin:0;padding:24px;background:#fdf8e6;color:#10311a}h1{margin:0 0 6px}table{border-collapse:collapse;width:100%;max-width:1000px;background:#fff}td,th{border:1px solid #ecdfae;padding:10px;text-align:left;vertical-align:top}.ok{color:#0a6a20;font-weight:700}.no{color:#d80006;font-weight:700}small{color:#5b6b4f}</style></head><body>
<h1>BULIG installation check</h1><p><?=$bad?'<span class="no">'.$bad.' problem(s) found.</span> Follow the “How to fix” column.':'<span class="ok">Everything is installed.</span> If you still see the old look, clear the Hostinger cache (hPanel → Website → Cache / LiteSpeed Cache → Purge all) and press Ctrl+F5.'?></p>
<table><tr><th>Check</th><th>Result</th><th>Details</th><th>How to fix</th></tr>
<?php foreach($rows as [$a,$ok,$d,$f])echo '<tr><td>'.e($a).'</td><td class="'.($ok?'ok':'no').'">'.($ok?'OK':'PROBLEM').'</td><td><small>'.e($d).'</small></td><td>'.($ok?'':e($f)).'</td></tr>';?>
</table><h2>What your browser receives</h2><table id="live"><tr><th>Check</th><th>Result</th><th>Details</th><th>How to fix</th></tr></table>
<script nonce="<?=$nonce?>">
(async()=>{const t=document.getElementById('live');const add=(a,ok,d,f)=>{const r=t.insertRow();r.innerHTML='<td></td><td class="'+(ok?'ok':'no')+'">'+(ok?'OK':'PROBLEM')+'</td><td><small></small></td><td></td>';r.cells[0].textContent=a;r.cells[2].firstChild.textContent=d;r.cells[3].textContent=ok?'':f;};
try{const r=await fetch('./?page=login&check='+Date.now(),{cache:'no-store',credentials:'omit'});const h=await r.text();const m=h.match(/theme\.css\?v=(\d+)/);const hdr=['x-litespeed-cache','x-hcdn-cache-status','cf-cache-status','age'].map(k=>r.headers.get(k)?k+': '+r.headers.get(k):'').filter(Boolean).join(', ');
add('Sign-in page links the new design',!!m&&+m[1]>=23,m?'page asks for theme.css v'+m[1]+(hdr?' | '+hdr:''):'page does not link theme.css'+(hdr?' | '+hdr:''),'The site is serving an old cached page. hPanel → Websites → Dashboard → Cache Manager / LiteSpeed → Purge all, and turn off CDN cache (hPanel → CDN → Purge cache).');}catch(e){add('Sign-in page',false,String(e),'');}
try{const r=await fetch('assets/theme.css?v=23&check='+Date.now(),{cache:'no-store'});const c=await r.text();add('Design file the browser downloads',r.ok&&c.includes('poster palette'),'HTTP '+r.status+', '+c.length+' bytes','theme.css on the server is old or blocked; re-extract Fix A, then purge the cache.');}catch(e){add('Design file',false,String(e),'');}
try{const r=await fetch('assets/fonts/poppins-latin-400-normal.woff2',{cache:'no-store'});add('Font file download',r.ok,'HTTP '+r.status,'Fonts folder missing under public/assets/fonts.');}catch(e){add('Font',false,String(e),'');}
})();
</script><p><small>Delete public/bulig-check.php when you are done.</small></p></body></html>
