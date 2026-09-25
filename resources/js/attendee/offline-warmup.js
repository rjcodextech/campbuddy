// Keeps the rest of the event available with no connection.
//
// The service worker (public/sw.js) saves a page the first time it is opened,
// so a screen nobody has visited yet would say "You're offline". Once an event
// page has loaded and the phone is idle, this saves the event's other screens
// too — plus the scripts, styles and images they need — into the same cache the
// worker reads, so opening My Day, Explore, Quest, the Guide… works underground
// or on dead venue wifi. It only adds and replaces; it never deletes
// (saved-copies.js, docs spec/23-data-retention.md).
//
// Polite by design: idle time only, a few requests at a time, a minute's budget,
// skipped when the phone is offline, on Data Saver, or on a 2G-class connection;
// at most once every 6 hours per event unless the event's data has changed.
// Everything is best-effort — any failure is swallowed and the app carries on.

import { PAGES_CACHE, isCacheable, replaceCopy } from './saved-copies.js';

const KEY = (slug) => `campbuddy:warm:${slug}`;
const PERSIST_KEY = 'campbuddy:persist-asked';
const MIN_GAP_MS = 6 * 60 * 60 * 1000;
// After an incomplete run, try again sooner.
const RETRY_GAP_MS = 30 * 60 * 1000;
const BUDGET_MS = 60 * 1000;
const CONCURRENCY = 3;
const MAX_MEDIA = 60;
// The entries of the Vite manifest the attendee pages start from.
const ENTRIES = ['resources/js/attendee/app.js', 'resources/scss/main.scss'];
// Pages that are forms/actions rather than content; nothing to keep for offline.
const SKIP_PATHS = /\/roster-removal(\/|$)/;

/** Why the phone should be left alone right now, or null when it is fine to go ahead. */
function holdOff(nav) {
  if (nav.onLine === false) return 'offline';
  if (nav.connection?.saveData) return 'data-saver';
  if (['slow-2g', '2g'].includes(nav.connection?.effectiveType)) return 'slow-connection';

  return null;
}

/** The addresses of the event's pages: the known screens plus every event link the page shows. */
export function pageAddresses(slug, doc, origin) {
  const base = `/event/${slug}`;
  const paths = new Set([base, `${base}/my-day`, `${base}/quest`, `${base}/contribute`, `${base}/explore`, `${base}/camp-card`, `${base}/guide`, `${base}/manifest.json`, '/', '/guide']);

  for (const anchor of doc.querySelectorAll('a[href]')) {
    try {
      const url = new URL(anchor.getAttribute('href'), origin);

      if (url.origin === origin && (url.pathname === base || url.pathname.startsWith(`${base}/`)) && !SKIP_PATHS.test(url.pathname)) {
        paths.add(url.pathname);
      }
    } catch {
      // An odd href: ignore it.
    }
  }

  return [...paths];
}

/** Every /build/ file the attendee pages can ask for (start scripts and styles, and each screen's own chunk), from the Vite manifest. */
export function buildAssets(manifest) {
  const files = new Set();
  const seen = new Set();

  const visit = (key) => {
    const chunk = manifest[key];

    if (!chunk || seen.has(key)) return;
    seen.add(key);

    if (chunk.file) files.add(`/build/${chunk.file}`);
    (chunk.css ?? []).forEach((file) => files.add(`/build/${file}`));
    (chunk.assets ?? []).forEach((file) => files.add(`/build/${file}`));
    (chunk.imports ?? []).forEach(visit);
    (chunk.dynamicImports ?? []).forEach(visit);
  };

  ENTRIES.forEach(visit);

  return [...files];
}

/** Images the saved pages and styles point at (/media, /storage): src/href/data-fallback attributes and CSS url(...). */
export function mediaAddresses(text) {
  const found = new Set();

  for (const match of text.matchAll(/(?:src|href|data-fallback|content)=["'](\/(?:media|storage)\/[^"']+)["']/g)) found.add(match[1]);
  for (const match of text.matchAll(/url\(\s*["']?(\/(?:media|storage)\/[^)"'\s]+)/g)) found.add(match[1]);

  return [...found];
}

/** Runs `worker(item)` over `items`, `size` at a time, and stops taking new items once `deadline` has passed. */
async function pool(items, size, deadline, worker) {
  const queue = [...items];

  await Promise.all(
    Array.from({ length: Math.min(size, queue.length) }, async () => {
      while (queue.length > 0 && Date.now() < deadline) await worker(queue.shift());
    })
  );
}

/**
 * @returns {Promise<{ status: 'skipped'|'warmed'|'partial', reason?: string, pages?: number, assets?: number, media?: number, failed?: number }>}
 */
export async function warmOfflineCache({
  doc = globalThis.document,
  nav = globalThis.navigator,
  storage = globalThis.localStorage,
  cachesImpl = globalThis.caches,
  fetchImpl = globalThis.fetch,
  origin = globalThis.location?.origin,
} = {}) {
  try {
    const app = doc?.getElementById('app');
    const slug = app?.dataset.eventSlug;

    if (!slug) return { status: 'skipped', reason: 'not-an-event-page' };
    if (!cachesImpl) return { status: 'skipped', reason: 'no-cache-storage' };

    const hold = holdOff(nav);
    if (hold) return { status: 'skipped', reason: hold };

    const version = doc.querySelector('meta[name="campbuddy-data-version"]')?.content ?? '';
    const last = readState(storage, slug);

    if (last && last.version === version && Date.now() - last.at < (last.complete ? MIN_GAP_MS : RETRY_GAP_MS)) return { status: 'skipped', reason: 'recent' };

    const cache = await cachesImpl.open(PAGES_CACHE);
    const deadline = Date.now() + BUDGET_MS;
    const counts = { pages: 0, assets: 0, media: 0, failed: 0 };
    const media = new Set();
    const here = globalThis.location?.pathname;
    const abs = (path) => new URL(path, origin).href;
    const fetchText = async (response) => {
      try {
        return await response.clone().text();
      } catch {
        return '';
      }
    };

    // 1. The pages. Always replaced in place (their content follows the event's data). The one on screen is
    // fresh already — and normally saved by the worker — but on the very first visit the worker is not in
    // control yet, so it is only skipped when it really is saved.
    await pool(pageAddresses(slug, doc, origin), CONCURRENCY, deadline, async (path) => {
      try {
        if (path === here && (await cache.match(abs(path)))) return;

        const response = await fetchImpl(abs(path), { cache: 'reload', credentials: 'same-origin' });

        if (!isCacheable(response)) {
          counts.failed++;
          return;
        }

        if (/html/.test(response.headers.get('content-type') ?? '')) mediaAddresses(await fetchText(response)).forEach((m) => media.add(m));
        await cache.put(abs(path), response);
        counts.pages++;
      } catch {
        counts.failed++;
      }
    });

    // The page on screen was saved by the service worker; read its images too.
    mediaAddresses(doc.documentElement?.innerHTML ?? '').forEach((m) => media.add(m));

    // 2. The scripts, styles and images the pages need (hashed names: only what isn't already kept).
    let assets = [];
    try {
      const manifestResponse = await fetchImpl(abs('/build/manifest.json'), { cache: 'no-cache', credentials: 'same-origin' });
      if (manifestResponse.ok) assets = buildAssets(await manifestResponse.json());
    } catch {
      // No manifest (development, or a hiccup): the pages are saved; assets follow the next time.
    }

    await pool(assets, CONCURRENCY, deadline, async (path) => {
      if (await cache.match(abs(path))) return;

      try {
        const response = await fetchImpl(abs(path), { credentials: 'same-origin' });

        if (!isCacheable(response)) {
          counts.failed++;
          return;
        }

        if (path.endsWith('.css')) mediaAddresses(await fetchText(response)).forEach((m) => media.add(m));
        await cache.put(abs(path), response);
        counts.assets++;
      } catch {
        counts.failed++;
      }
    });

    await pool([...media].slice(0, MAX_MEDIA), CONCURRENCY, deadline, async (path) => {
      if (await cache.match(abs(path))) return;

      if (await replaceCopy(cache, abs(path), { fetchImpl })) counts.media++;
      else counts.failed++;
    });

    const complete = counts.failed === 0 && Date.now() < deadline;
    writeState(storage, slug, { version, at: Date.now(), complete });

    askForPersistentStorage(nav, storage);

    return { status: complete ? 'warmed' : 'partial', ...counts };
  } catch {
    return { status: 'skipped', reason: 'error' };
  }
}

function readState(storage, slug) {
  try {
    return JSON.parse(storage.getItem(KEY(slug)) ?? 'null');
  } catch {
    return null;
  }
}

function writeState(storage, slug, state) {
  try {
    storage.setItem(KEY(slug), JSON.stringify(state));
  } catch {
    // Storage blocked: it will simply run again next time.
  }
}

/**
 * Asks the browser not to evict this site's saved data when the phone runs low
 * on space (an installed app usually gets a yes without any prompt). Asked
 * once per device; the answer changes nothing visible.
 */
async function askForPersistentStorage(nav, storage) {
  try {
    if (!nav.storage?.persist || storage.getItem(PERSIST_KEY)) return;

    storage.setItem(PERSIST_KEY, String(await nav.storage.persist()));
  } catch {
    // Not supported or blocked.
  }
}
