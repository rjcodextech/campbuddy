// Quiet upkeep for reminders, run when an event page opens and whenever the
// phone gets its connection back (never asks the person anything):
//
//   1. Reminder requests made offline (push.js queued them in the outbox) are
//      sent now — including cancellations, so a session they dropped doesn't
//      still buzz their phone.
//   2. A push subscription the browser replaced (they happen — a browser
//      update, a push-service rotation) is told to the server again;
//      otherwise the server keeps pushing to an address that no longer works
//      and the reminders just stop, silently. Re-sent at least weekly too.
//
// Only touches people who already said yes to reminders (permission granted).

import { apiMutate } from './api.js';
import { getBookmarks, kvGet } from './db.js';
import { getDeviceId } from './device.js';
import { isPermanentFailure } from './outbox.js';
import { outbox, registerSubscription, syncReminder } from './push.js';

const RESEND_AFTER_MS = 7 * 24 * 60 * 60 * 1000;

function canPush() {
  return 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window && Notification.permission === 'granted';
}

/** Sends what is waiting in the outbox. */
export async function flushOutbox(eventSlug) {
  return outbox.flush(eventSlug, async (item) => {
    try {
      if (item.kind === 'reminder-off') {
        await apiMutate(eventSlug, '/bookmarks', 'DELETE', { device_id: getDeviceId(), session_id: item.sessionId });

        return undefined;
      }

      // "Remind me" — only if it still means something: the session is still on their day and they haven't since switched notifications off.
      const stillSaved = (await getBookmarks(item.eventId)).some((bookmark) => bookmark.sessionId === item.sessionId);
      if (!stillSaved || !canPush()) return 'drop';

      await syncReminder(eventSlug, item.eventId, item.sessionId);

      return undefined;
    } catch (error) {
      error.permanent = isPermanentFailure(error);
      throw error;
    }
  });
}

/** Tells the server about this phone's current push subscription when it changed (or hasn't been told for a week). */
export async function syncPushSubscription(eventSlug) {
  const state = (await kvGet('notificationState')) ?? {};

  if (!state.enabled || !canPush()) return 'skipped';

  const registration = await navigator.serviceWorker.ready;
  const current = await registration.pushManager.getSubscription();
  const told = await kvGet(`pushEndpoint:${eventSlug}`);

  if (current && told && told.endpoint === current.endpoint && Date.now() - told.at < RESEND_AFTER_MS) return 'current';

  await registerSubscription(eventSlug);

  return 'sent';
}

/** Everything above, for the event page on screen. Never throws. */
export async function runPushSync() {
  try {
    const eventSlug = document.getElementById('app')?.dataset.eventSlug;

    if (!eventSlug || navigator.onLine === false) return;

    await flushOutbox(eventSlug);
    await syncPushSubscription(eventSlug);
  } catch {
    // Best effort: the next visit or reconnection tries again.
  }
}
