// CampBuddy service worker (offline-first, push).
//
// Strategy: pages (server-rendered Blade) are network-first with a
// cache fallback, so a returning visitor with no connection still gets
// their last-downloaded Home/My Day/etc. Hashed build assets are
// cache-first since their filename changes whenever their content does —
// safe to cache indefinitely.
//
// What is deliberately NOT cached or intercepted:
//   - /admin — signed-in, per-user pages; they must never be stored in a
//     browser cache, least of all on a shared device.
//   - /api/ — live data, or a deliberate "you're offline" state the page
//     handles itself.
//   - Any response that isn't a plain 200: caching a 404/500 would keep
//     serving that error long after the server recovered (for a hashed
//     build asset, forever), and a redirected response can't be replayed
//     for a navigation.

// Bumped whenever caching behaviour changes: activate() deletes every other
// cache, which also clears anything an older worker wrongly stored.
const CACHE_NAME = 'campbuddy-v3';

// With a cached copy to fall back on, don't make someone on flaky conference
// wifi wait for a stalled request — give the network this long, then serve it.
const NETWORK_TIMEOUT_MS = 4000;

const OFFLINE_HTML = `<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline | CampBuddy</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#fffaf4;color:#2b1a14;text-align:center;padding:24px}
  h1{font-size:1.25rem;margin:0 0 8px} p{margin:0 0 20px;color:#6b5a52}
  button{font:inherit;font-weight:600;padding:12px 20px;border:0;border-radius:6px;background:#c33a19;color:#fff}
</style></head>
<body><main>
  <h1>You're offline</h1>
  <p>This page hasn't been saved on your device yet. Reconnect and try again.</p>
  <button type="button" onclick="location.reload()">Try again</button>
</main></body></html>`;

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  if (
    url.pathname.startsWith('/api/') ||
    url.pathname === '/admin' ||
    url.pathname.startsWith('/admin/') ||
    url.pathname === '/up'
  ) {
    return;
  }

  const isBuildAsset = url.pathname.startsWith('/build/');

  event.respondWith(isBuildAsset ? cacheFirst(request) : networkFirst(request, event));
});

// Only a plain, complete, non-redirected response is worth keeping.
function isCacheable(response) {
  return response.status === 200 && response.type === 'basic' && !response.redirected;
}

async function store(request, response) {
  if (!isCacheable(response)) return;

  try {
    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response);
  } catch (err) {
    // Storage full or blocked — serving the response matters, caching it doesn't.
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  const response = await fetch(request);
  // Not awaited: the caller shouldn't wait on the cache write.
  store(request, response.clone());
  return response;
}

async function networkFirst(request, event) {
  const cached = await caches.match(request);
  const network = fetch(request);

  // However long the network takes, a good answer still replaces the saved
  // copy. (Before, a slow answer was dropped once the timeout had served the
  // saved copy — so a page saved while the event had no data yet kept being
  // shown, empty, to anyone on slow venue wifi.)
  const refreshed = network.then((response) => store(request, response.clone())).catch(() => {});
  event?.waitUntil(refreshed);

  try {
    const response = await (cached ? withTimeout(network, NETWORK_TIMEOUT_MS) : network);

    // The server is up but erroring (deploy in progress, database blip): a
    // good saved copy beats an error page. A 404 is a real answer, so it isn't masked.
    if (cached && response.status >= 500) return cached;

    return response;
  } catch (err) {
    if (cached) return cached;

    // A page that was never visited and can't be reached: a friendly
    // message rather than the browser's raw error page. Anything else
    // (an image, a script) just fails as it would have.
    if (request.mode === 'navigate') {
      return new Response(OFFLINE_HTML, {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }

    throw err;
  }
}

function withTimeout(promise, ms) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('network timeout')), ms);

    promise.then(
      (response) => {
        clearTimeout(timer);
        resolve(response);
      },
      (err) => {
        clearTimeout(timer);
        reject(err);
      }
    );
  });
}

// The scheduled reminder itself.
self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch (err) {
    // Malformed payload — nothing useful to show.
    return;
  }

  event.waitUntil(
    self.registration.showNotification(payload.title || 'CampBuddy', {
      body: payload.body,
      icon: '/media/icons/icon-192.png',
      data: { url: payload.url },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  // Resolved against this origin so a relative url in the payload still
  // matches an already-open tab, whose url is always absolute.
  const target = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then((clients) => {
      const existing = clients.find((c) => c.url === target);
      if (existing) return existing.focus();
      return self.clients.openWindow(target);
    })
  );
});
