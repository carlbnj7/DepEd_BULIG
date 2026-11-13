/* BULIG Defense Panel service worker: keeps the app on the phone so it opens offline.
   Typing answers works offline; answering by voice needs the Internet (the browser's speech service). */
const CACHE = 'dp-v5';
const FILES = ['./', 'index.html', 'manifest.webmanifest', 'assets/app.css?v=4', 'assets/app.js?v=3', 'assets/questions.js?v=2', 'assets/questions-more.js?v=1',
  'assets/fonts/poppins-latin-400-normal.woff2', 'assets/fonts/poppins-latin-600-normal.woff2', 'assets/fonts/poppins-latin-700-normal.woff2',
  'assets/img/bulig-quest.webp', 'assets/img/icon-192.png', 'assets/img/icon-180.png', 'assets/img/teacher-male.webp', 'assets/img/teacher-female.webp', 'assets/img/admin-m-tip.webp',
  'assets/img/jarvis-cheer.webp', 'assets/img/jarvis-hello.webp', 'assets/img/jarvis-idea.webp', 'assets/img/jarvis-thumbs.webp'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(FILES)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request; if (r.method !== 'GET' || new URL(r.url).origin !== location.origin) return;
  if (r.mode === 'navigate') {   // the page: newest when online, the saved copy when offline
    e.respondWith(fetch(r).then(res => { const c = res.clone(); caches.open(CACHE).then(x => x.put('index.html', c)); return res; }).catch(() => caches.match('index.html')));
    return;
  }
  e.respondWith(caches.match(r).then(hit => hit || fetch(r).then(res => { if (res.ok) { const c = res.clone(); caches.open(CACHE).then(x => x.put(r, c)); } return res; })));
});
