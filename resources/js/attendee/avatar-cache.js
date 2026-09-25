// Saves the attendee-list photos on the phone, so Explore -> People still shows
// them with no connection (public/sw.js shows the saved copy in place of the
// network; without this, a photo is only saved after it has scrolled into view
// once). Photos come from Gravatar — public, about 2 KB each, and Gravatar allows
// reading them from other sites, so a normal (not opaque) copy can be kept.
//
// Polite by design: idle time only, three requests at a time, at most 1000 a
// visit, skipped offline / on Data Saver / on a 2G-class connection, and it gives
// up after three failures in a row. Only ever adds (never deletes); a photo
// already saved is not fetched again. Best-effort: nothing here can throw.

const AVATAR_CACHE = 'campbuddy-avatars';
const HOSTS = ['secure.gravatar.com'];
const MAX_PER_VISIT = 1000;
const CONCURRENCY = 3;
const GIVE_UP_AFTER = 3;

/** The distinct photo addresses in a roster: https, from a known photo host, nothing else. */
export function avatarUrls(entries) {
  const found = new Set();

  for (const entry of entries ?? []) {
    try {
      const url = new URL(entry?.gravatar_url ?? '');

      if (url.protocol === 'https:' && HOSTS.includes(url.host)) found.add(url.href);
    } catch {
      // No photo, or not an address.
    }
  }

  return [...found];
}

function holdOff(nav) {
  return nav?.onLine === false || Boolean(nav?.connection?.saveData) || ['slow-2g', '2g'].includes(nav?.connection?.effectiveType);
}

/**
 * @returns {Promise<{ saved: number, alreadySaved: number, failed: number, skipped?: string }>}
 */
export async function warmAvatars(entries, { cachesImpl = globalThis.caches, fetchImpl = globalThis.fetch, nav = globalThis.navigator } = {}) {
  const result = { saved: 0, alreadySaved: 0, failed: 0 };

  try {
    if (!cachesImpl) return { ...result, skipped: 'no-cache-storage' };
    if (holdOff(nav)) return { ...result, skipped: 'held-off' };

    const cache = await cachesImpl.open(AVATAR_CACHE);
    const queue = avatarUrls(entries).slice(0, MAX_PER_VISIT);
    let inARow = 0;

    const worker = async () => {
      while (queue.length > 0 && inARow < GIVE_UP_AFTER) {
        const url = queue.shift();

        if (await cache.match(url)) {
          result.alreadySaved++;
          continue;
        }

        try {
          const response = await fetchImpl(url, { mode: 'cors', credentials: 'omit' });

          if (!response.ok || response.type !== 'cors') throw new Error('not a photo');

          await cache.put(url, response);
          result.saved++;
          inARow = 0;
        } catch {
          result.failed++;
          inARow++;
        }
      }
    };

    await Promise.all(Array.from({ length: CONCURRENCY }, worker));
  } catch {
    // Storage blocked or anything unforeseen: the photos just load as they always did.
  }

  return result;
}
