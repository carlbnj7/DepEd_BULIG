/* BULIG service worker: lets the app be installed and shows a friendly page when offline.
   Lessons, answers and sign-ins always come from the server; only the offline page is stored. */
const CACHE='bulig-offline-v1';
const FILES=['offline.html','assets/offline.css','assets/icons/icon-192.png','assets/bulig-logo.png','assets/fonts/poppins-latin-600-normal.woff2'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.addAll(FILES)).then(()=>self.skipWaiting()));});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',e=>{
 const r=e.request;
 if(r.method!=='GET')return;
 if(r.mode==='navigate'){e.respondWith(fetch(r).catch(()=>caches.match('offline.html')));return;}
 if(FILES.some(f=>r.url.endsWith('/'+f)))e.respondWith(fetch(r).catch(()=>caches.match(r,{ignoreSearch:true})));
});
