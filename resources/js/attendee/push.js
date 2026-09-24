// Web Push opt-in. Permission is asked ONLY right
// after a bookmark — never on page load — and a denial is respected
// permanently: no automatic re-prompt, ever (N4).

import { apiMutate } from './api.js';
import { track } from './analytics.js';
import { kvGet, kvSet } from './db.js';
import { isIos, isStandalone } from './platform.js';
import { render } from './template.js';

function getDeviceId() {
  let id = localStorage.getItem('campbuddy-device-id');
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem('campbuddy-device-id', id);
  }
  return id;
}

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

  const wantsReminder = confirm('Want CampBuddy to remind you before this session starts?');
  if (!wantsReminder) {
    track('reminder_offer', { result: 'declined' });
    return false;
  }

  const permission = await Notification.requestPermission();

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

    await apiMutate(eventSlug, '/bookmarks', 'POST', {
      device_id: getDeviceId(),
      session_id: sessionId,
      reminder_enabled: true,
    });

    track('reminder_offer', { result: 'enabled' });
    return true;
  } catch {
    track('reminder_offer', { result: 'error' });
    return false;
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
