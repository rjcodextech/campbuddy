// The attendee list, kept on the phone so Explore -> People can be browsed with
// no connection (and opens at once, with the fresh list swapped in when it
// arrives). It is public data — the same list anyone can see — kept in
// IndexedDB with the rest of what the app saves; "Delete my data" clears it
// with everything else, and we never remove it ourselves.
//
// Pure logic with the storage passed in, so it can be tested on its own.

export const ROSTER_KEY = (slug) => `roster:${slug}`;

/**
 * @param {{ kvGet: (key: string) => Promise<any>, kvSet: (key: string, value: any) => Promise<any>, now?: () => number }} deps
 */
export function createRosterStore({ kvGet, kvSet, now = Date.now }) {
  return {
    /** The saved list, or null when there is none (or storage is unavailable). */
    async read(slug) {
      try {
        const saved = await kvGet(ROSTER_KEY(slug));

        return saved && Array.isArray(saved.entries) && saved.entries.length > 0 ? { entries: saved.entries, savedAt: saved.savedAt ?? null } : null;
      } catch {
        return null;
      }
    },

    /** Saves the list. An empty list is not saved over a good one (a hiccup must not erase what the phone already has). */
    async write(slug, entries) {
      if (!Array.isArray(entries) || entries.length === 0) return false;

      try {
        await kvSet(ROSTER_KEY(slug), { savedAt: now(), entries });

        return true;
      } catch {
        return false;
      }
    },
  };
}

/** Whether two lists show the same people, in the same order, with the same details. */
export function sameRoster(a, b) {
  return JSON.stringify(a) === JSON.stringify(b);
}

/** "Sat 3:20 PM" — when the saved list was fetched, in the phone's own time. */
export function savedWhen(savedAt, locale = undefined) {
  if (!savedAt) return '';

  return new Date(savedAt).toLocaleString(locale, { weekday: 'short', hour: 'numeric', minute: '2-digit' });
}
