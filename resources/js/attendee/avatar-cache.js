// Saves attendee-list photos, speakers and event/sponsor logos on the phone,
// so Explore -> People still shows them with no connection (public/sw.js shows
// the saved copy in place of the network).
//
// Retries images that had transient fetch failures. Automatically drops images older than 30 days.

const AVATAR_CACHE = 'campbuddy-avatars';
const MAX_PER_VISIT = 1000;
const CONCURRENCY = 3;
const GIVE_UP_AFTER = 3;
export const THIRTY_DAYS_MS = 30 * 24 * 60 * 60 * 1000;

export function isAllowedPhotoHost(host) {
  if (!host) return false;
  const h = host.toLowerCase();
  if (['secure.gravatar.com', 'gravatar.com', 'profiles.wordpress.org', 'wordpress.org', 'w.org', 'wp.com', 'i0.wp.com', 'i1.wp.com', 'i2.wp.com'].includes(h)) {
    return true;
  }
  if (h.endsWith('.wordcamp.org') || h.endsWith('.wordpress.org') || h.endsWith('.wordpress.com') || h.endsWith('.wp.com')) {
    return true;
  }
  if (typeof location !== 'undefined' && h === location.host) {
    return true;
  }
  return false;
}

/** The distinct photo addresses in a roster: https, from known photo/logo hosts, nothing else. */
export function avatarUrls(entries, extraUrls = []) {
  const found = new Set();

  for (const entry of entries ?? []) {
    try {
      const url = new URL(entry?.photo_url || entry?.gravatar_url || '');
      if (url.protocol === 'https:' && isAllowedPhotoHost(url.host)) found.add(url.href);
    } catch {
      // No photo, or not an address.
    }
  }

  for (const extra of extraUrls ?? []) {
    try {
      const url = new URL(extra || '', typeof location !== 'undefined' ? location.origin : 'https://campbuddy.club');
      if (['https:', 'http:'].includes(url.protocol) && isAllowedPhotoHost(url.host)) found.add(url.href);
    } catch {
      // Not a valid URL.
    }
  }

  return [...found];
}

function holdOff(nav) {
  return nav?.onLine === false || Boolean(nav?.connection?.saveData) || ['slow-2g', '2g'].includes(nav?.connection?.effectiveType);
}

/**
 * Prunes cached avatars and logos that are older than maxAgeMs (default 30 days).
 */
export async function pruneExpiredAvatars(maxAgeMs = THIRTY_DAYS_MS, { cachesImpl = globalThis.caches } = {}) {
  try {
    if (!cachesImpl) return 0;
    const cache = await cachesImpl.open(AVATAR_CACHE);
    const requests = await cache.keys();
    const now = Date.now();
    let deleted = 0;

    for (const req of requests) {
      const res = await cache.match(req);
      if (!res) continue;
      const dateHeader = res.headers.get('date');
      if (dateHeader) {
        const age = now - new Date(dateHeader).getTime();
        if (age > maxAgeMs) {
          await cache.delete(req);
          deleted++;
        }
      }
    }
    return deleted;
  } catch {
    return 0;
  }
}

/**
 * @returns {Promise<{ saved: number, alreadySaved: number, failed: number, skipped?: string }>}
 */
export async function warmAvatars(entries, { cachesImpl = globalThis.caches, fetchImpl = globalThis.fetch, nav = globalThis.navigator, extraUrls = [] } = {}) {
  const result = { saved: 0, alreadySaved: 0, failed: 0 };

  try {
    if (!cachesImpl) return { ...result, skipped: 'no-cache-storage' };
    if (holdOff(nav)) return { ...result, skipped: 'held-off' };

    const cache = await cachesImpl.open(AVATAR_CACHE);
    const queue = avatarUrls(entries, extraUrls).slice(0, MAX_PER_VISIT);
    const failedToRetry = [];
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

          if (!response.ok || (response.type !== 'cors' && response.type !== 'basic')) {
            throw new Error('not a valid photo response');
          }

          await cache.put(url, response);
          result.saved++;
          inARow = 0;
        } catch {
          result.failed++;
          inARow++;
          failedToRetry.push(url);
        }
      }
    };

    await Promise.all(Array.from({ length: CONCURRENCY }, worker));

    // Retry once for transient failed items only when the connection wasn't completely dead (inARow < GIVE_UP_AFTER)
    if (failedToRetry.length > 0 && inARow < GIVE_UP_AFTER && nav?.onLine !== false) {
      let retryConsecutive = 0;
      for (const retryUrl of failedToRetry.slice(0, 10)) {
        if (retryConsecutive >= GIVE_UP_AFTER) break;
        try {
          const res = await fetchImpl(retryUrl, { mode: 'cors', credentials: 'omit' });
          if (res.ok && (res.type === 'cors' || res.type === 'basic')) {
            await cache.put(retryUrl, res);
            result.saved++;
            result.failed = Math.max(0, result.failed - 1);
            retryConsecutive = 0;
          } else {
            retryConsecutive++;
          }
        } catch {
          retryConsecutive++;
        }
      }
    }
  } catch {
    // Storage blocked or anything unforeseen: the photos just load as they always did.
  }

  return result;
}
