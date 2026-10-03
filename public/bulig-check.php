<?php
/* BULIG update checker. Open https://your-site/public/bulig-check.php while signed in as a pupil
   (for the offline checks). Delete this file when you are done. */
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, max-age=0');
$opc=function_exists('opcache_reset')?(@opcache_reset()?'cleared now':'not allowed'):'not used';
$root=dirname(__DIR__);
$expected=['app/views.php'=>'bab6c86710b5b64f72f73daa73f8d3247716e76d','app/actions.php'=>'c1dc48339f6e62ee272e3b219e4d0fe29520a512','app/admin.php'=>'246fbebef1abaf5dfd32248155157bddca3ebee1','app/pupil_fun.php'=>'7762390b8e4087191645d5724ce4e138d0b04787','app/pupil_welcome.php'=>'b4cdee1ce7f35edfcdda0f2bebfd3da9f012bc24','app/teacher_fun.php'=>'cc5d8a355276cc78fc6e8a27df12fdd86ec345a4','public/index.php'=>'bf448f83bdfe8e77123c1181de45b78400a7c86a','public/assets/app.js'=>'48969729f9bd3b17a644eea9ab1381530c55af3e','public/assets/theme.css'=>'1639deca9270189c7fdb525bbb2be489f7156334','public/assets/images/characters/teacher-female.webp'=>'e5c47d1a06040fceb90d636e4db3f709d5fe1853','public/assets/images/characters/boy.webp'=>'beeed8c97b0449b509c1b2fdbac9953c557abc79','public/assets/images/characters/girl.webp'=>'a0df4465a7db129e18b6152111a1da096803ad87','public/assets/images/characters/teacher-male.webp'=>'8d03935a73f19d1df42c307f93f2f20f4657cf77','app/class_done.php'=>'b4e7769493c195102ac90d316c81502052189859','app/bootstrap.php'=>'9de736b8c3fb89ba7194fca8f5d7e17213718fcf','app/teacher_demo.php'=>'2ff4cc946d8cdd3644c32c18c8460ebfeac715fb','app/offline.php'=>'4da33291a3868d836ce25a7352bb4461edceab13','public/sw.js'=>'b1318927d66d9dd53085f044f3ad2083bd71167b','public/assets/offline.js'=>'849fa8fd31935242d6905fa627c8d7de15244716','public/assets/tour.js'=>'3cf6cdc7f26d657cd03b396dc5723eabe94ace59','public/assets/dark.css'=>'8a3755cf4de942e9846b4dca5500706aa11faea3','public/assets/mode.js'=>'a72d3c0de437a638d68b4d7ca4b1253d60d4d991','app/saved_login.php'=>'09a7929455892de770db4a6d53ceacf6c303cc55','public/assets/quick.js'=>'beba8050da7eb9f0b608f9683999269771ec1f79','public/assets/images/characters/boy-cheer.webp'=>'c9ce069688cadb9ac42357124b0f5b9929cfd5cd','public/assets/images/characters/girl-cheer.webp'=>'2e57eea5fe3df4af503455c31f440338cc250a64','public/assets/images/characters/teacher-female-wave.webp'=>'e2d5c072ab350bdc480c2b08c46104cae63080e1','public/assets/images/characters/teacher-male-tip.webp'=>'604325ef0a72db4b37984c55621af9ba0526b5eb'];
$ver=['app.js'=>'38','theme.css'=>'65','offline.js'=>'2','tour.js'=>'1'];
$rows='';$bad=0;
$sl='not checked';try{require_once $root.'/app/bootstrap.php';header_remove('Content-Security-Policy');db()->query('SELECT 1 FROM saved_logins LIMIT 1');$sl='OK';}catch(Throwable $e){$sl='MISSING: import live-update/016_saved_logins.sql in phpMyAdmin';}
foreach($expected as $f=>$h){
 $p=$root.'/'.$f;
 if(!is_file($p)){$s='MISSING';$bad++;}
 else{$got=sha1_file($p);if($got===$h)$s='OK';else{$s='OLD / DIFFERENT';$bad++;}}
 $rows.='<tr class="'.($s==='OK'?'ok':'bad').'"><td>'.htmlspecialchars($f).'</td><td>'.$s.'</td></tr>';
}
$fp=function($u)use($root){$src=$root.'/public/'.$u;if(!preg_match('~^assets/[a-z_]+\.(css|js)$~',$u)||!is_file($src))return $u;$i=pathinfo($u);$n=$i['dirname'].'/'.$i['filename'].'-'.substr(md5(filemtime($src).'-'.filesize($src)),0,10).'.'.$i['extension'];return is_file($root.'/public/'.$n)?$n:$u.'  (fingerprint copy not made yet: open BULIG once)';};
$pub=[];foreach($expected as $f=>$h)if(strpos($f,'public/')===0&&!preg_match('~\.php$~',$f)){$u=substr($f,7);if(preg_match('~^assets/[a-z_]+\.(css|js)$~',$u)){$pub[$fp($u)]=$h;continue;}if($u==='assets/app.js')$u.='?v='.$ver['app.js'];elseif($u==='assets/theme.css')$u.='?v='.$ver['theme.css'];elseif($u==='assets/offline.js')$u.='?v='.$ver['offline.js'];elseif($u==='assets/tour.js')$u.='?v='.$ver['tour.js'];elseif(strpos($u,'images/characters/')!==false)$u.='?v=1';$pub[$u]=$h;}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BULIG update check</title>
<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;background:#f6f1e2;color:#17331f}main{max-width:900px;margin:auto;padding:20px 16px 60px}h1{color:#176444;margin:0 0 4px}h2{margin:28px 0 8px;font-size:1.15rem}
.box{background:#fff;border-radius:16px;padding:14px 16px;box-shadow:0 4px 14px rgba(0,0,0,.06)}table{border-collapse:collapse;width:100%;font-size:.88rem}td{padding:7px 8px;border-top:1px solid #eee;word-break:break-all}
tr.ok td:last-child{color:#176444;font-weight:700}tr.bad td:last-child{color:#b3261e;font-weight:800}tr.wait td:last-child{color:#8a6200}
.sum{font-size:1.05rem;font-weight:800;padding:12px 16px;border-radius:14px;margin-top:10px}.sum.ok{background:#e6f4ec;color:#176444}.sum.bad{background:#fde8e6;color:#b3261e}.muted{color:#5f6f63;font-size:.9rem}li{margin:4px 0}</style></head><body><main>
<h1>BULIG update check</h1><p class="muted">PHP code cache: <?=$opc?> · <?=date('M j, Y g:i A')?></p>
<h2>1. Files on the server</h2><div class="box"><div class="sum <?=$bad?'bad':'ok'?>"><?=$bad?$bad.' file(s) are missing or old. Upload the ZIP again (the newest one that has the file).':'All '.count($expected).' files on the server are the new versions.'?></div><table><?=$rows?></table></div>
<h2>Database</h2><div class="box"><table><tr class="<?=$sl==='OK'?'ok':'bad'?>"><td>Saved sign-ins table (016_saved_logins.sql)</td><td><?=htmlspecialchars($sl)?></td></tr></table></div>
<h2>2. What your browser really gets (through the CDN)</h2><div class="box"><div class="sum" id="cdn-sum">Checking…</div><table id="cdn"></table></div>
<h2>3. Offline mode in this browser</h2><div class="box"><div class="sum" id="off-sum">Checking…</div><table id="off"></table></div>
<h2>If something says OLD or MISSING</h2><div class="box"><ul>
<li><b>Section 1 OLD/MISSING:</b> the file on the server is old. Extract the ZIP again inside <b>public_html</b> and choose <b>Replace</b> for every file.</li>
<li>Style and script files now get a new file name each time they change (for example theme-1a2b3c4d5e.css), so the CDN cannot keep old copies of them.</li><li><b>Section 1 OK but Section 2 OLD:</b> the CDN still gives the old copy. In Hostinger, go to Websites → your site → Performance → <b>CDN → Flush cache</b>, and <b>Cache Manager → Purge all</b>. Wait 2 minutes, then reload this page.</li>
<li><b>Service worker OLD:</b> after flushing the CDN, close all BULIG tabs, open BULIG again and reload once (Ctrl+F5). On phones, close the app fully and open it again.</li>
<li>Delete <b>public/bulig-check.php</b> when you are finished.</li></ul></div>
</main><script>
const PUB=<?=json_encode($pub)?>;
const row=(t,u,s,c)=>{const tr=document.createElement('tr');tr.className=c;tr.innerHTML='<td></td><td></td>';tr.cells[0].textContent=u;tr.cells[1].textContent=s;document.getElementById(t).append(tr);};
async function sha1(buf){const h=await crypto.subtle.digest('SHA-1',buf);return [...new Uint8Array(h)].map(b=>b.toString(16).padStart(2,'0')).join('');}
(async()=>{let bad=0;
 for(const [u,h] of Object.entries(PUB)){try{const r=await fetch(u,{cache:'no-store'});if(!r.ok){row('cdn',u,'MISSING ('+r.status+')','bad');bad++;continue;}const got=await sha1(await r.arrayBuffer());if(got===h)row('cdn',u,'OK','ok');else{row('cdn',u,'OLD COPY','bad');bad++;}}catch(e){row('cdn',u,'Could not load','bad');bad++;}}
 const s=document.getElementById('cdn-sum');s.className='sum '+(bad?'bad':'ok');s.textContent=bad?bad+' file(s) come back old or missing. Flush the CDN and Cache Manager, then reload.':'Your browser gets the new version of every file.';
 let ob=0;const add=(n,ok,txt)=>{row('off',n,txt,ok===null?'wait':ok?'ok':'bad');if(ok===false)ob++;};
 add('Secure site (https)',location.protocol==='https:'||location.hostname==='localhost'||location.hostname==='127.0.0.1',location.protocol==='https:'||location.hostname==='localhost'||location.hostname==='127.0.0.1'?'OK':'NOT https: offline mode cannot work');
 add('Browser supports offline','serviceWorker' in navigator&&'caches' in window&&'indexedDB' in window,'serviceWorker' in navigator?'OK':'NO: use Chrome, Edge, Firefox or Safari (not a private window)');
 try{const t=await (await fetch('sw.js',{cache:'no-store'})).text();add('Service worker file (sw.js)',t.includes('bulig-off-'),t.includes('bulig-off-')?'OK (new)':'OLD: offline pages cannot open');}catch(e){add('Service worker file (sw.js)',false,'Could not load');}
 if('serviceWorker' in navigator){const reg=await navigator.serviceWorker.getRegistration();if(!reg)add('Service worker running',null,'Not yet. Open BULIG once, then reload this page');else{const sw=reg.active||reg.waiting||reg.installing;let txt='Running';try{const t=await (await fetch(sw.scriptURL,{cache:'no-store'})).text();txt=t.includes('bulig-off-')?'Running (new)':'Running OLD version: close all BULIG tabs and open again';}catch(e){}add('Service worker running',!txt.includes('OLD'),txt);if(reg.waiting)add('Update waiting',null,'A new version is waiting. Close all BULIG tabs, then open again.');}}
 try{const r=await fetch('index.php?page=offline_sync',{cache:'no-store',credentials:'same-origin'});const t=await r.text();let j=null;try{j=JSON.parse(t);}catch(e){}
  if(!j)add('Offline upload address',false,'OLD index.php or not reachable');else{add('Offline upload address',true,'OK');add('Signed in as a pupil',j.uid>0?true:null,j.uid>0?'Yes (pupil '+j.uid+')':'No. Sign in as a pupil in this browser to use and test offline mode');}}catch(e){add('Offline upload address',false,'Could not reach');}
 if('caches' in window){const keys=(await caches.keys()).filter(k=>k.startsWith('bulig-off-'));add('Levels saved in this browser',keys.length?true:null,keys.length?keys.length+' saved':'None yet. Open My lessons and choose "Save for offline"');}
 const os=document.getElementById('off-sum');os.className='sum '+(ob?'bad':'ok');os.textContent=ob?ob+' problem(s) found. See the list below.':'Offline mode is ready in this browser.';
})();
</script></body></html>
