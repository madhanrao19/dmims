const CACHE_VERSION = 'v3';
const CACHE_PREFIX = 'dmims-core-';
const CACHE_NAME = CACHE_PREFIX + CACHE_VERSION;
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [
  OFFLINE_URL,
  '/icons/icon-192.png',
  '/manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => {
          if (!key.startsWith(CACHE_PREFIX) || key === CACHE_NAME) return;
          return caches.delete(key);
        })
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const requestUrl = new URL(event.request.url);

  // Avoid interfering with admin/admin-like routes
  try {
    const pathname = requestUrl.pathname || '';
    if (pathname.startsWith('/admin') || pathname.startsWith('/_ign')) {
      event.respondWith(fetch(event.request));
      return;
    }
  } catch (e) {
    // ignore
  }

  // Always try network first for HTML navigation requests, fallback to cache
  if (event.request.mode === 'navigate' || (event.request.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Put a copy in the cache
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          return response;
        })
        .catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // For other requests, use cache-first strategy then network
    // If the request is for built assets (Vite) or common static extensions, use cache-first
    const staticExtensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.svg', '.webp', '.woff2', '.woff', '.ttf'];
    const pathnameLower = requestUrl.pathname.toLowerCase();

    const isBuiltAsset = pathnameLower.includes('/build/') || pathnameLower.includes('/assets/');
    const hasStaticExt = staticExtensions.some((ext) => pathnameLower.endsWith(ext));

    if (isBuiltAsset || hasStaticExt) {
      // Stale-while-revalidate: respond with cache immediately if available,
      // then fetch and update cache in background.
      event.respondWith(
        caches.match(event.request).then((cached) => {
          const networkFetch = fetch(event.request).then((response) => {
            if (response && response.status === 200) {
              const copy = response.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
            }
            return response;
          }).catch(() => null);

          // Return cached if present, else wait for network
          return cached || networkFetch;
        })
      );
      return;
    }

    // Default cache-first fallback
    event.respondWith(
      caches.match(event.request).then((cached) => cached || fetch(event.request).catch(() => cached))
    );
});
