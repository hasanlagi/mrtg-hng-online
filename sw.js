const CACHE = 'hng-pppoe-v5-history-20261103';
const ASSETS = ['./', './index.php', './manifest.webmanifest', './icons/icon-192.png', './icons/icon-512.png'];
const ASSET_URLS = new Set(ASSETS.map(path => new URL(path, self.registration.scope).href));

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(async cache => {
    await cache.addAll(ASSETS.map(path => new Request(path, { cache: 'reload' })));
    await self.skipWaiting();
  }));
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(key => key.startsWith('hng-pppoe-') && key !== CACHE).map(key => caches.delete(key)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin) return;
  // Live monitoring endpoints must never be cached or receive HTML fallbacks.
  const assetUrl = url.origin + url.pathname;
  if (!ASSET_URLS.has(assetUrl)) return;
  event.respondWith((async () => {
    const cache = await caches.open(CACHE);
    try {
      const response = await fetch(request, { cache: 'no-cache' });
      if (response.ok) await cache.put(assetUrl, response.clone());
      return response;
    } catch (error) {
      const cached = await cache.match(assetUrl);
      if (cached) return cached;
      throw error;
    }
  })());
});
