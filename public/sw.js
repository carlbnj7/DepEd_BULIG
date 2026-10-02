/* BULIG service worker: lets the app be installed, shows a friendly page when offline,
   and opens levels a pupil saved for offline use (caches named bulig-off-<pupil>-L<level>). */
const CACHE='bulig-offline-v6';
const FILES=['offline.html','assets/offline.css','assets/brand/bulig-tab.png','assets/brand/bulig-app-medium.png','assets/bulig-logo.png','assets/fonts/poppins-latin-600-normal.woff2'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.addAll(FILES)).then(()=>self.skipWaiting()));});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE&&!k.startsWith('bulig-off-')&&k!=='bulig-who').map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});

/* The pupil signed in on this device; saved pages are only shown to that pupil. */
async function who(){try{const c=await caches.open('bulig-who');const r=await c.match('who');return r?(await r.text()):'';}catch(e){return '';}}
async function saved(req,onlyPage){
 const uid=await who();if(onlyPage&&!uid)return null;
 const names=(await caches.keys()).filter(k=>k.startsWith('bulig-off-')&&(!onlyPage||k.startsWith('bulig-off-'+uid+'-')));
 const u=new URL(req.url);const tries=[u.href];
 if(onlyPage&&u.searchParams.has('feedback')){u.searchParams.delete('feedback');tries.push(u.href);}
 for(const t of tries)for(const n of names){const r=await (await caches.open(n)).match(t);if(r)return r;}
 return null;
}
self.addEventListener('fetch',e=>{
 const r=e.request;
 if(r.method!=='GET'||new URL(r.url).origin!==location.origin)return;
 const url=new URL(r.url);
 if(url.searchParams.get('page')==='offline_sync'||url.searchParams.get('page')==='offline_manifest'||url.searchParams.get('off')==='1')return;
 if(r.mode==='navigate'){e.respondWith(fetch(r).catch(async()=>(await saved(r,true))||caches.match('offline.html')));return;}
 if(FILES.some(f=>url.pathname.endsWith('/'+f))){e.respondWith(fetch(r).catch(()=>caches.match(r,{ignoreSearch:true})));return;}
 if(/\.(css|js|png|jpe?g|webp|gif|svg|woff2?|ico)$/i.test(url.pathname))e.respondWith(fetch(r).catch(async()=>(await saved(r,false))||Response.error()));
});
