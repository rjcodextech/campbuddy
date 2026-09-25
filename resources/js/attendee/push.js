// Web Push opt-in. Permission is asked ONLY right
// after a bookmark — never on page load — and a denial is respected
// permanently: no automatic re-prompt, ever (N4).

import { apiMutate } from './api.js';
import { track } from './analytics.js';
import { kvGet, kvSet, setBookmark } from './db.js';
import { createOutbox, isPermanentFailure } from './outbox.js';
import { isIos, isStandalone } from './platform.js';
import { render } from './template.js';
import { getDeviceId } from './device.js';

/**
 * Reminder requests made with no connection (or while the server was down),
 * kept on the phone and sent by push-sync.js when it's back — a tap on
 * "remind me" is never silently lost.
 */
export const outbox = createOutbox({ kvGet, kvSet });


/**
 * Called right after a bookmark is saved. Returns true if a reminder was
 * successfully set up, false otherwise (declined, unsupported, or
 * already permanently denied).
 */
export async function offerReminder(eventSlug, eventId, sessionId) {
  const state = (await kvGet('notificationState')) ?? {};

  // N4: a prior denial is permanent — never re-prompt automatically.
  if (state.permanentlyDenied) {
    return false;
  }

  // N3: iOS needs 16.4+ AND the PWA installed to the home screen first. In an
  // ordinary Safari tab the Notification and PushManager APIs aren't even
  // there, so this has to be checked before the support test below — after it,
  // this instruction was never shown. It's shown once, not on every saved
  // session: a dialog on each star would be nagging (N1).
  if (isIos() && !isStandalone()) {
    track('reminder_offer', { result: 'ios_needs_install' });

    if (!state.iosInstallPromptShown) {
      await kvSet('notificationState', { ...state, iosInstallPromptShown: true });
      showIosInstallPrompt();
    }

    return false;
  }

  if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    track('reminder_offer', { result: 'unsupported' });
    return false;
  }

  // Once reminders are switched on, every later save just gets one — no
  // question on each star (N1: a dialog per bookmark would be nagging).
  const alreadyOn = state.enabled && Notification.permission === 'granted';

  if (!alreadyOn) {
    // A "no thanks" is asked once per device, not on every saved session.
    if (state.declined) {
      return false;
    }

    const wantsReminder = confirm('Want CampBuddy to remind you a few minutes before the sessions you save start?');
    if (!wantsReminder) {
      await kvSet('notificationState', { ...state, declined: true });
      track('reminder_offer', { result: 'declined' });
      return false;
    }
  }

  const permission = alreadyOn ? 'granted' : await Notification.requestPermission();

  if (permission === 'denied') {
    await kvSet('notificationState', { ...state, permanentlyDenied: true });
    track('reminder_offer', { result: 'permission_denied' });
    return false;
  }

  if (permission !== 'granted') {
    track('reminder_offer', { result: 'permission_dismissed' });
    return false;
  }

  try {
    await syncReminder(eventSlug, eventId, sessionId);

    if (!alreadyOn) track('reminder_offer', { result: 'enabled' });
    return true;
  } catch (error) {
    // They said yes and the browser allowed it, so this is the connection or
    // the server, not a "no": keep the wish and send it once the phone can.
    if (isPermanentFailure(error)) {
      track('reminder_offer', { result: 'error' });
    } else {
      await outbox.add(eventSlug, { kind: 'reminder-on', sessionId, eventId });
      track('reminder_offer', { result: 'queued' });
    }

    return false;
  }
}

/**
 * This phone's push subscription — made again if the browser dropped it — and
 * told to the server. Also remembered locally, so push-sync.js can tell when
 * the browser later replaces it.
 */
export async function registerSubscription(eventSlug) {
  const registration = await navigator.serviceWorker.ready;
  let subscription = await registration.pushManager.getSubscription();

  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(document.querySelector('meta[name="vapid-public-key"]').content),
    });
  }

  await apiMutate(eventSlug, '/push/subscribe', 'POST', {
    device_id: getDeviceId(),
    endpoint: subscription.endpoint,
    keys: {
      p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
      auth: arrayBufferToBase64(subscription.getKey('auth')),
    },
  });

  await kvSet(`pushEndpoint:${eventSlug}`, { endpoint: subscription.endpoint, at: Date.now() });

  return subscription;
}

/** The server side of a reminder: the subscription, then the bookmark that asks for it. */
export async function syncReminder(eventSlug, eventId, sessionId) {
  await registerSubscription(eventSlug);

  await apiMutate(eventSlug, '/bookmarks', 'POST', {
    device_id: getDeviceId(),
    session_id: sessionId,
    reminder_enabled: true,
  });

  // Remembered locally, so un-saving the session knows to cancel it.
  await setBookmark(eventId, sessionId, true);
  await kvSet('notificationState', { ...((await kvGet('notificationState')) ?? {}), enabled: true });
}

/**
 * Called when a saved session is un-saved. If the server was holding a
 * reminder for it, that reminder is cancelled — otherwise the attendee
 * would still get a push for a session they took off their day.
 */
export async function cancelReminder(eventSlug, bookmark) {
  // Whatever was still waiting to be sent for this session no longer means anything.
  if (bookmark?.sessionId != null) await outbox.drop(eventSlug, bookmark.sessionId);

  if (!bookmark?.reminderEnabled) return;

  try {
    await apiMutate(eventSlug, '/bookmarks', 'DELETE', {
      device_id: getDeviceId(),
      session_id: bookmark.sessionId,
    });
    track('reminder_cancel');
  } catch (error) {
    // Offline or refused: the local bookmark is already gone, so keep the
    // cancellation and send it once the phone can — otherwise the reminder
    // for a session they dropped would still arrive.
    if (!isPermanentFailure(error)) await outbox.add(eventSlug, { kind: 'reminder-off', sessionId: bookmark.sessionId });
  }
}

function showIosInstallPrompt() {
  const dialog = render('tpl-reminder-ios-dialog');
  dialog.querySelector('[data-action="close"]').addEventListener('click', () => {
    dialog.close();
    dialog.remove();
  });
  document.body.appendChild(dialog);
  dialog.showModal();
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

function arrayBufferToBase64(buffer) {
  return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}
