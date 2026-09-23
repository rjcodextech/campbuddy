// Web Push opt-in. Permission is asked ONLY right
// after a bookmark — never on page load — and a denial is respected
// permanently: no automatic re-prompt, ever (N4).

import { apiMutate } from './api.js';
import { kvGet, kvSet } from './db.js';

function getDeviceId() {
  let id = localStorage.getItem('campbuddy-device-id');
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem('campbuddy-device-id', id);
  }
  return id;
}

function isIosSafari() {
  const ua = navigator.userAgent;
  return /iP(hone|ad|od)/.test(ua) && /WebKit/.test(ua) && !/CriOS|FxiOS/.test(ua);
}

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
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

  if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    return false;
  }

  // N3: iOS needs 16.4+ AND the PWA installed to the home screen first.
  if (isIosSafari() && !isStandalone()) {
    showIosInstallPrompt();
    return false;
  }

  const wantsReminder = confirm('Want CampBuddy to remind you before this session starts?');
  if (!wantsReminder) return false;

  const permission = await Notification.requestPermission();

  if (permission === 'denied') {
    await kvSet('notificationState', { ...state, permanentlyDenied: true });
    return false;
  }

  if (permission !== 'granted') return false;

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

    return true;
  } catch {
    return false;
  }
}

function showIosInstallPrompt() {
  const dialog = document.createElement('dialog');
  dialog.innerHTML = `
    <div class="dialog-card">
      <p style="font-weight:700;margin:0 0 8px">Get reminders on iPhone/iPad</p>
      <p class="footer-note" style="text-align:left">Add CampBuddy to your home screen first, then reminders can work:</p>
      <ol class="ios-install-steps">
        <li>Tap the Share button in Safari</li>
        <li>Choose "Add to Home Screen"</li>
        <li>Open CampBuddy from your home screen and bookmark again</li>
      </ol>
      <button type="button" class="btn btn--primary btn--full" data-action="close">Got it</button>
    </div>
  `;
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
