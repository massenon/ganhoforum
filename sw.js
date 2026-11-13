/* Service worker du forum Ganho (v1).
   - Fichiers de la charte (/themes/default/statics/ganho/*) : cache d'abord (versionnés par ?v=).
   - Pages : TOUJOURS le réseau (contenu connecté, jamais mis en cache) ; hors ligne, repli sur /offline.html.
   - Jamais touchés : POST, API (native_api, api.php), upload, admin_panel. */
const VERSION = 'forum-v1';
const STATIC = VERSION + '-static';
const PRECACHE = [
  '/offline.html',
  '/themes/default/statics/ganho/img/icone-appli-192.png',
  '/themes/default/statics/ganho/fonts/Montserrat-latin-normal.woff2'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(STATIC).then(c => Promise.all(PRECACHE.map(u => c.add(new Request(u, {cache: 'reload'})).catch(() => null)))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  const p = url.pathname;

  if (p.startsWith('/themes/default/statics/ganho/')) {
    e.respondWith(caches.open(STATIC).then(async c => {
      const hit = await c.match(req);
      if (hit) return hit;
      const res = await fetch(req);
      if (res.ok) c.put(req, res.clone());
      return res;
    }));
    return;
  }

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(async () => (await caches.match('/offline.html')) || Response.error()));
  }
});
