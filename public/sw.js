/* BULIG service worker: lets the app be installed, shows a friendly page when offline,
   opens levels a pupil saved for offline use (caches named bulig-off-<pupil>-L<level>),
   and keeps the sign-in page (cache bulig-login) so saved accounts can open their lessons offline. */
const CACHE='bulig-offline-v8';
const FILES=['offline.html','assets/offline.css','assets/brand/bulig-tab.png','assets/brand/bulig-app-medium.png','assets/bulig-logo.png','assets/fonts/poppins-latin-600-normal.woff2'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.addAll(FILES)).then(()=>self.skipWaiting()));});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE&&!k.startsWith('bulig-off-')&&k!=='bulig-who'&&k!=='bulig-login').map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});

/* The pupil using BULIG on this device; saved pages are only shown to that pupil. */
async function who(){try{const c=await caches.open('bulig-who');const r=await c.match('who');return r?(await r.text()):'';}catch(e){return '';}}
async function saved(req,onlyPage){
 const uid=await who();if(onlyPage&&!uid)return null;
 const names=(await caches.keys()).filter(k=>k.startsWith('bulig-off-')&&(!onlyPage||k.startsWith('bulig-off-'+uid+'-')));
 if(!onlyPage)names.push('bulig-login');
 const u=new URL(req.url);
 /* The installed app opens "?page=dashboard&source=app"; the opening page may have no "page" at all. */
 u.searchParams.delete('source');if(onlyPage&&!u.searchParams.get('page'))u.searchParams.set('page','dashboard');
 const tries=[u.href];
 if(onlyPage&&u.searchParams.has('feedback')){u.searchParams.delete('feedback');tries.push(u.href);}
 for(const t of tries)for(const n of names){const r=await (await caches.open(n)).match(t);if(r)return r;}
 return null;
}
async function loginPage(req){try{const c=await caches.open('bulig-login');return await c.match(new URL('?page=login',req.url).href);}catch(e){return null;}}
async function offlinePage(req){
 const page=new URL(req.url).searchParams.get('page')||'';
 if(page!=='login'){const s=await saved(req,true);if(s)return s;}
 /* Signed out, or the sign-in page itself: show the saved sign-in page so a saved pupil can tap their picture. */
 if(page==='login'||!(await who())){const l=await loginPage(req);if(l)return l;}
 return caches.match('offline.html');
}
self.addEventListener('fetch',e=>{
 const r=e.request;
 if(r.method!=='GET'||new URL(r.url).origin!==location.origin)return;
 const url=new URL(r.url);
 if(['offline_sync','offline_manifest','offline_levels'].includes(url.searchParams.get('page'))||url.searchParams.get('off')==='1')return;
 if(r.mode==='navigate'){e.respondWith(fetch(r).catch(()=>offlinePage(r)));return;}
 if(FILES.some(f=>url.pathname.endsWith('/'+f))){e.respondWith(fetch(r).catch(()=>caches.match(r,{ignoreSearch:true})));return;}
 if(/\.(css|js|png|jpe?g|webp|gif|svg|woff2?|ico)$/i.test(url.pathname))e.respondWith(fetch(r).catch(async()=>(await saved(r,false))||Response.error()));
});
