// The copies of pages and files this phone keeps for offline use (the service
// worker's Cache Storage, see public/sw.js) — how they are refreshed.
//
// THE RULE (docs spec/23-data-retention.md): our code never deletes them. A
// copy is only ever REPLACED by a fresh one, and only once the fresh one has
// arrived whole; if the network is flaky, slow or down, the old copy stays and
// the app keeps working offline. (Clearing them is the person's own business —
// the browser's "clear site data", or the browser running low on space.)

/** The service worker's cache; copies made by page code go in the same one, so the worker finds them. */
export const PAGES_CACHE = 'campbuddy-v3';

/** Only a plain, complete, non-redirected 200 is worth keeping — the same test the service worker applies. */
export function isCacheable(response) {
  return Boolean(response) && response.status === 200 && response.type === 'basic' && !response.redirected;
}

/**
 * Fetches a fresh copy and puts it in place of the old one. Returns whether it
 * did; on any failure the old copy is untouched.
 */
export async function replaceCopy(cache, url, { fetchImpl = fetch } = {}) {
  try {
    const response = await fetchImpl(url, { cache: 'reload', credentials: 'same-origin' });

    if (!isCacheable(response)) return false;

    await cache.put(url, response);
    return true;
  } catch {
    return false;
  }
}

/**
 * Every saved copy whose address `match`es, as { name, url }.
 *
 * @param {(url: URL) => boolean} match
 */
export async function listCopies(match, { cachesImpl = globalThis.caches } = {}) {
  const found = [];

  if (!cachesImpl) return found;

  for (const name of await cachesImpl.keys()) {
    const cache = await cachesImpl.open(name);

    for (const request of await cache.keys()) {
      if (match(new URL(request.url, globalThis.location?.origin))) found.push({ name, url: request.url });
    }
  }

  return found;
}

/**
 * Refreshes the matching saved copies IN PLACE, a few at a time, waiting at
 * most `timeoutMs` (whatever is still on its way carries on in the background
 * and lands when it arrives). Nothing is deleted, whatever happens.
 *
 * @param {(url: URL) => boolean} match
 * @returns {Promise<{ found: number, replaced: number }>}
 */
export async function refreshCopies(match, { timeoutMs = 8000, concurrency = 3, fetchImpl = fetch, cachesImpl = globalThis.caches } = {}) {
  let found = [];

  try {
    found = await listCopies(match, { cachesImpl });
  } catch {
    // Storage blocked or unavailable: nothing saved that we can reach.
    return { found: 0, replaced: 0 };
  }

  let replaced = 0;
  const queue = [...found];

  const worker = async () => {
    while (queue.length > 0) {
      const { name, url } = queue.shift();

      try {
        const cache = await cachesImpl.open(name);
        if (await replaceCopy(cache, url, { fetchImpl })) replaced++;
      } catch {
        // Leave that one as it is.
      }
    }
  };

  const work = Promise.all(Array.from({ length: Math.min(concurrency, found.length) }, worker));
  let timer;
  const timeout = new Promise((resolve) => {
    timer = setTimeout(resolve, timeoutMs);
  });

  await Promise.race([work, timeout]);
  clearTimeout(timer);

  return { found: found.length, replaced };
}
