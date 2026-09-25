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
//
// NOTHING IS EVER DELETED by this worker (docs spec/23-data-retention.md):
// what a phone has saved for an event stays until the person clears it or the
// browser runs short of space — activating a new version drops no cache, and
// saved pages are only ever replaced by fresh ones (saved-copies.js does that
// from the page; offline-warmup.js adds the pages nobody opened yet). The one
// exception is a signed-in /admin page an older worker may have saved, removed
// on activation because it should never have been stored.
//
// Stale-while-revalidate for page opens is built in but OFF: the worker reads
// /sw-flags.json (a tiny file next to this one) and only when it says
// {"swrPages": true} does a page open show the saved copy at once (no request
// at all when it is under 5 minutes old) and refresh it in the background. Set
// it back to false, or delete the file, and the very next check returns to
// network-first — no new deploy, no new worker version.

// Shared with the page code that saves copies (resources/js/attendee/saved-copies.js).
const CACHE_NAME = 'campbuddy-v3';

// The switch file, and the copy of it this worker keeps so a restart while
// offline still knows what it said.
const FLAGS_URL = '/sw-flags.json';
const FLAGS_CACHE = 'campbuddy-flags';
const FLAGS_CHECK_MS = 10 * 60 * 1000;
const FLAGS_RETRY_MS = 60 * 1000;
const FLAGS_TIMEOUT_MS = 3000;

// Stale-while-revalidate: a saved page younger than this is shown without
// asking the server at all (the app's own data-version check replaces saved
// pages the moment the event's data changes).
const SWR_FRESH_MS = 5 * 60 * 1000;

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
  event.waitUntil(removeSavedAdminPages().then(() => self.clients.claim()));
});

// An older worker could save signed-in /admin pages; they must not stay on a
// shared device. Only those entries go — never a cache, never event content.
async function removeSavedAdminPages() {
  try {
    for (const name of await caches.keys()) {
      const cache = await caches.open(name);

      for (const request of await cache.keys()) {
        const path = new URL(request.url).pathname;

        if (path === '/admin' || path.startsWith('/admin/')) await cache.delete(request);
      }
    }
  } catch (err) {
    // Storage unavailable — nothing to tidy.
  }
}

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
    url.pathname === '/up' ||
    url.pathname === FLAGS_URL
  ) {
    return;
  }

  const isBuildAsset = url.pathname.startsWith('/build/');

  if (isBuildAsset) {
    event.respondWith(cacheFirst(request));
  } else if (request.mode === 'navigate') {
    event.respondWith(openPage(request, event));
  } else {
    // Anything the app fetches for itself (e.g. refreshing a saved page) always asks the network.
    event.respondWith(networkFirst(request, event));
  }
});

// ---- the switch ------------------------------------------------------------

let flags = null;
let flagsCheckedAt = 0;

/** What the switch file last said (nothing on = off). Never waits on the network: a stale answer now beats a slow one. */
async function currentFlags(event) {
  if (flags === null) {
    flags = await savedFlags();
  }

  if (Date.now() - flagsCheckedAt > FLAGS_CHECK_MS) {
    flagsCheckedAt = Date.now();
    event?.waitUntil(refreshFlags());
  }

  return flags;
}

async function savedFlags() {
  try {
    const cache = await caches.open(FLAGS_CACHE);
    const saved = await cache.match(FLAGS_URL);

    return saved ? await saved.json() : {};
  } catch (err) {
    return {};
  }
}

async function refreshFlags() {
  try {
    // Never holds the worker up: a stalled connection gives up after a few seconds.
    const response = await withTimeout(fetch(FLAGS_URL, { cache: 'no-store' }), FLAGS_TIMEOUT_MS);

    // No file at all means everything is off.
    if (response.status === 404) {
      flags = {};
      await (await caches.open(FLAGS_CACHE)).put(FLAGS_URL, new Response('{}', { headers: { 'content-type': 'application/json' } }));
      return;
    }

    if (!response.ok) throw new Error('flags unavailable');

    const fresh = await response.clone().json();

    if (fresh && typeof fresh === 'object') {
      flags = fresh;

      const cache = await caches.open(FLAGS_CACHE);
      await cache.put(FLAGS_URL, response);
    }
  } catch (err) {
    // Offline, erroring or a bad file: keep what we last knew, and look again soon rather than in ten minutes.
    flagsCheckedAt = Date.now() - FLAGS_CHECK_MS + FLAGS_RETRY_MS;
  }
}

/** A page open: network-first, or (when switched on) stale-while-revalidate. */
async function openPage(request, event) {
  try {
    if ((await currentFlags(event)).swrPages === true) {
      return await staleWhileRevalidate(request, event);
    }
  } catch (err) {
    // Whatever went wrong, the proven path below still answers.
  }

  return networkFirst(request, event);
}

/** How old a saved response is, from the server's own Date header (Infinity when it can't be told). */
function ageOf(response) {
  const saved = Date.parse(response.headers.get('date') ?? '');

  return Number.isFinite(saved) ? Date.now() - saved : Infinity;
}

async function staleWhileRevalidate(request, event) {
  const cached = await caches.match(request);

  // Nothing saved yet: the normal path fetches it and saves it.
  if (!cached) return networkFirst(request, event);

  // Recent enough: no request at all.
  if (ageOf(cached) < SWR_FRESH_MS) return cached;

  // Older: show it now, refresh it for next time. A failed or non-200 answer changes nothing.
  event.waitUntil(
    fetch(request)
      .then((response) => store(request, response))
      .catch(() => {})
  );

  return cached;
}

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
