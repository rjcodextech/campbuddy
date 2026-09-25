// Things the person asked for while the phone had no connection, kept on the
// phone until they can be sent — so an offline tap on "remind me" is not
// silently lost. Today that is one kind: turning a session reminder on or off
// (push.js). Stored in IndexedDB with the rest of the person's own data, per
// event, and never removed by us except when sent (or when it can no longer
// mean anything: a permanent refusal, or the session was taken off their day).
//
// Pure logic with the storage passed in, so it can be tested on its own.

export const OUTBOX_KEY = (slug) => `outbox:${slug}`;
/** A safety bound: a phone that never gets a connection can't grow this without limit. */
const MAX_ITEMS = 100;
/** An item that has failed this many real attempts (online, but it keeps failing) is given up on. */
const MAX_ATTEMPTS = 8;

/**
 * @param {{ kvGet: (key: string) => Promise<any>, kvSet: (key: string, value: any) => Promise<any>, now?: () => number }} deps
 */
export function createOutbox({ kvGet, kvSet, now = Date.now }) {
  const load = async (slug) => {
    try {
      const saved = await kvGet(OUTBOX_KEY(slug));

      return Array.isArray(saved) ? saved : [];
    } catch {
      return [];
    }
  };

  const save = async (slug, items) => {
    try {
      await kvSet(OUTBOX_KEY(slug), items.slice(-MAX_ITEMS));

      return true;
    } catch {
      return false;
    }
  };

  return {
    /** Queues `{ kind, sessionId }`. The latest wish about a session replaces any earlier one (on then off = nothing to send but the off). */
    async add(slug, item) {
      const items = (await load(slug)).filter((queued) => queued.sessionId !== item.sessionId);
      items.push({ ...item, at: now() });

      return save(slug, items);
    },

    /** Forgets whatever is queued for a session (it was taken off the person's day). */
    async drop(slug, sessionId) {
      const items = await load(slug);
      const kept = items.filter((queued) => queued.sessionId !== sessionId);

      return kept.length === items.length ? true : save(slug, kept);
    },

    pending: (slug) => load(slug),

    /**
     * Sends each queued item with `send(item)`. `send` resolves when it went
     * through, resolves `'drop'` when it can no longer mean anything (drop it),
     * and throws when it should be tried again later (a failure with
     * `error.permanent` is dropped instead). Items that can't be sent stay —
     * up to MAX_ATTEMPTS tries.
     *
     * @returns {Promise<{ sent: number, dropped: number, remaining: number }>}
     */
    async flush(slug, send) {
      const items = await load(slug);
      const remaining = [];
      let sent = 0;
      let dropped = 0;

      for (const item of items) {
        try {
          if ((await send(item)) === 'drop') dropped++;
          else sent++;
        } catch (error) {
          const attempts = (item.attempts ?? 0) + 1;

          if (error?.permanent || attempts >= MAX_ATTEMPTS) dropped++;
          else remaining.push({ ...item, attempts });
        }
      }

      // Rewritten whenever anything changed (an item went, or another try was counted).
      if (sent + dropped > 0 || remaining.length > 0) await save(slug, remaining);

      return { sent, dropped, remaining: remaining.length };
    },
  };
}

/** Whether a failed request is the server's final answer (retrying can't help): a 4xx, except "busy" and "timed out". */
export function isPermanentFailure(error) {
  const status = error?.status;

  return Number.isInteger(status) && status >= 400 && status < 500 && status !== 408 && status !== 429;
}
