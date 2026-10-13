// CampBuddy inside the Android / iOS app (Capacitor shell, mobile-app/app).
// Loaded ONLY when the user agent says CampBuddyApp (platform.js isNativeApp),
// through a dynamic import — so the website and the installed PWA never
// download or run a byte of it.
//
// The website is the app: this file only swaps the few things a browser does
// badly for what the phone does well:
//   - session reminders: local notifications, scheduled on the phone (no
//     server, no Home Screen trick on iPhone, works with no signal)
//   - Camp Card / PDF: the phone's share sheet instead of a browser download
//   - the Android back button, links that open the app, and the "update the
//     app" screen for builds older than the server allows
//
// Native plugins come from the shell (window.Capacitor.Plugins): the page
// does not bundle any Capacitor code.

import { apiMutate } from './api.js';
import { track } from './analytics.js';
import { getDeviceId } from './device.js';
import { getBookmarks, kvGet, kvSet, setBookmark } from './db.js';
import { nativePlatform, appVersion } from './platform.js';

const CHANNEL_ID = 'session-reminders';
const LEAD_MS = 10 * 60 * 1000;
// iOS keeps only the 64 soonest local notifications; asking for more drops the rest silently.
const MAX_SCHEDULED = 60;

// --- pure helpers (unit tested) --------------------------------------------

/** Stable positive 31-bit id for a (event, session) pair — the notification's identity. */
export function reminderId(eventId, sessionId) {
  let hash = 2166136261;
  for (const ch of `${eventId}:${sessionId}`) {
    hash = Math.imul(hash ^ ch.charCodeAt(0), 16777619);
  }

  return (hash >>> 0) & 0x7fffffff;
}

/**
 * What to schedule for one saved session, or null when it is too late to
 * remind (the session has started, or starts within the lead time).
 */
export function reminderFor(bookmark, slug, now = Date.now()) {
  if (!bookmark || !bookmark.reminderEnabled || !Number.isFinite(bookmark.startMs)) return null;

  const at = bookmark.startMs - LEAD_MS;
  if (at <= now) return null;

  return {
    id: reminderId(bookmark.eventId, bookmark.sessionId),
    title: bookmark.title ? `Starting soon: ${bookmark.title}` : 'A session you saved starts soon',
    body: 'Starts in 10 minutes. Open My Day for the room.',
    at,
    extra: { url: `/event/${slug}/my-day` },
  };
}

/** The soonest reminders that fit the OS limit. */
export function pickSoonest(reminders, limit = MAX_SCHEDULED) {
  return reminders.filter(Boolean).sort((a, b) => a.at - b.at).slice(0, limit);
}

/** "1.2.3" compared to "1.10.0": negative, 0 or positive. */
export function compareVersions(a, b) {
  const pa = String(a).split('.').map(Number);
  const pb = String(b).split('.').map(Number);
  for (let i = 0; i < 3; i++) {
    const diff = (pa[i] || 0) - (pb[i] || 0);
    if (diff !== 0) return diff;
  }

  return 0;
}

/** Same-site links only: a notification or App Link may never send the page elsewhere. */
export function safeInternalPath(url, origin) {
  try {
    const parsed = new URL(url, origin);

    return parsed.origin === origin ? parsed.pathname + parsed.search + parsed.hash : null;
  } catch {
    return null;
  }
}

// --- plugins ----------------------------------------------------------------

function plugin(name) {
  const cap = window.Capacitor;

  return cap?.Plugins?.[name] ?? cap?.registerPlugin?.(name) ?? null;
}

// --- reminders --------------------------------------------------------------

const SLUGS_KEY = 'nativeReminderSlugs';

async function rememberSlug(eventId, slug) {
  const map = (await kvGet(SLUGS_KEY)) ?? {};
  if (map[eventId] !== slug) await kvSet(SLUGS_KEY, { ...map, [eventId]: slug });
}

async function ensureChannel(notifications) {
  // Android 8+ groups notifications into channels; iOS ignores this.
  try {
    await notifications.createChannel({ id: CHANNEL_ID, name: 'Session reminders', description: 'A few minutes before a session you saved', importance: 4 });
  } catch {
    // iOS, or the channel already exists.
  }
}

/** Replaces every pending reminder with the soonest ones from what is saved right now. */
export async function resyncReminders() {
  const notifications = plugin('LocalNotifications');
  if (!notifications) return;

  const { display } = await notifications.checkPermissions();
  if (display !== 'granted') return;

  const slugs = (await kvGet(SLUGS_KEY)) ?? {};
  const wanted = [];
  for (const [eventId, slug] of Object.entries(slugs)) {
    for (const bookmark of await getBookmarks(Number(eventId))) {
      wanted.push(reminderFor(bookmark, slug));
    }
  }

  const soonest = pickSoonest(wanted);
  const pending = await notifications.getPending();
  if (pending.notifications.length) await notifications.cancel({ notifications: pending.notifications.map((n) => ({ id: n.id })) });
  if (!soonest.length) return;

  await ensureChannel(notifications);
  await notifications.schedule({
    notifications: soonest.map((r) => ({
      id: r.id,
      title: r.title,
      body: r.body,
      channelId: CHANNEL_ID,
      extra: r.extra,
      schedule: { at: new Date(r.at), allowWhileIdle: true },
    })),
  });
}

/**
 * push.js offerReminder, for the app. Same rules as the website: asked only
 * right after a save, once per phone for a "no thanks", and a denial is
 * final (N1, N4). Returns true when a reminder is set.
 */
export async function offerReminder(eventSlug, eventId, sessionId) {
  const notifications = plugin('LocalNotifications');
  if (!notifications) return false;

  const state = (await kvGet('notificationState')) ?? {};
  if (state.permanentlyDenied) return false;

  const current = (await notifications.checkPermissions()).display;
  const alreadyOn = state.enabled && current === 'granted';

  if (!alreadyOn) {
    if (state.declined) return false;

    if (!confirm('Want CampBuddy to remind you a few minutes before the sessions you save start?')) {
      await kvSet('notificationState', { ...state, declined: true });
      track('reminder_offer', { result: 'declined' });

      return false;
    }

    const asked = current === 'granted' ? 'granted' : (await notifications.requestPermissions()).display;
    if (asked === 'denied') {
      await kvSet('notificationState', { ...state, permanentlyDenied: true });
      track('reminder_offer', { result: 'permission_denied' });

      return false;
    }
    if (asked !== 'granted') {
      track('reminder_offer', { result: 'permission_dismissed' });

      return false;
    }
  }

  const row = (await getBookmarks(eventId)).find((b) => b.sessionId === sessionId);
  if (!row) return false;

  // The saved row now says "reminder on", so un-saving knows to cancel it.
  await setBookmark(eventId, sessionId, true, { title: row.title, startMs: row.startMs, endMs: row.endMs });
  await kvSet('notificationState', { ...((await kvGet('notificationState')) ?? {}), enabled: true });
  await rememberSlug(eventId, eventSlug);
  await resyncReminders();
  registerPush(eventSlug);

  if (!alreadyOn) track('reminder_offer', { result: 'enabled' });

  return true;
}

/** push.js cancelReminder, for the app: the session was un-saved. */
export async function cancelReminder(eventId, sessionId) {
  const notifications = plugin('LocalNotifications');
  if (!notifications) return;

  try {
    await notifications.cancel({ notifications: [{ id: reminderId(eventId, sessionId) }] });
    track('reminder_cancel');
  } catch {
    // Nothing was pending.
  }
}

// --- announcements (FCM) ----------------------------------------------------

let registering = null;

/** Slug of the event page being viewed, or null (picker, guide…). */
export function eventSlugFromPath(pathname) {
  return pathname.match(/^\/event\/([a-z0-9-]+)/)?.[1] ?? null;
}

/**
 * Tells the server this install's push token for the event, so thank-you,
 * Memories and next-WordCamp pushes reach the app. Only after the person said
 * yes to notifications (offerReminder) — never on launch — and only when the
 * server has native push switched on.
 */
export async function registerPush(eventSlug) {
  const push = plugin('PushNotifications');
  if (!push || !eventSlug || registering) return registering;

  registering = (async () => {
    try {
      const config = await appConfig();
      if (config && config.features?.native_push === false) return;

      const permission = await push.checkPermissions();
      if (permission.receive !== 'granted') return;

      await push.createChannel?.({ id: 'announcements', name: 'WordCamp news', description: 'Thank-you, photos and talks, the next WordCamp', importance: 3 });

      const token = await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('push token timeout')), 15000);
        push.addListener('registration', (result) => {
          clearTimeout(timer);
          resolve(result.value);
        });
        push.addListener('registrationError', (error) => {
          clearTimeout(timer);
          reject(new Error(error?.error ?? 'push registration failed'));
        });
        push.register();
      });

      await apiMutate(eventSlug, '/push/native-subscribe', 'POST', {
        device_id: getDeviceId(),
        platform: nativePlatform(),
        token,
        app_version: appVersion(),
      });
    } catch {
      // No Firebase set up, offline, or refused: reminders still work locally.
    } finally {
      registering = null;
    }
  })();

  return registering;
}

// --- files and sharing ------------------------------------------------------

function blobToBase64(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '');
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(blob);
  });
}

/**
 * Camp Card PNG / PDF: written to the app cache, then the phone's share sheet
 * (Save to Photos / Files, WhatsApp, LinkedIn…). Resolves to false when the
 * person closed the sheet.
 */
export async function saveFile(blob, filename, title = 'CampBuddy') {
  const files = plugin('Filesystem');
  const share = plugin('Share');
  if (!files || !share) return false;

  const written = await files.writeFile({ path: filename, data: await blobToBase64(blob), directory: 'CACHE' });
  try {
    await share.share({ title, files: [written.uri], dialogTitle: title });

    return true;
  } catch (error) {
    // Closing the sheet rejects with a "canceled" message; anything else is real.
    if (/cancel/i.test(String(error?.message ?? error))) return false;
    throw error;
  }
}

// --- app level --------------------------------------------------------------

let configPromise = null;

/** The server's app settings (once per launch); null when offline. */
function appConfig() {
  configPromise ??= fetch('/api/v1/app-config', { headers: { Accept: 'application/json' } })
    .then((response) => (response.ok ? response.json() : null))
    .catch(() => null);

  return configPromise;
}

async function checkMinimumVersion() {
  const current = appVersion();
  if (!current) return;

  try {
    const config = await appConfig();
    if (!config) return;
    const platform = nativePlatform();
    const minimum = config.min_version?.[platform];
    if (minimum && compareVersions(current, minimum) < 0) showUpdateScreen(config.store_url?.[platform]);
  } catch {
    // Offline: never block someone for failing to reach the server.
  }
}

function showUpdateScreen(storeUrl) {
  if (document.getElementById('app-update-screen')) return;

  const screen = document.createElement('div');
  screen.id = 'app-update-screen';
  screen.setAttribute('role', 'alertdialog');
  screen.setAttribute('aria-modal', 'true');
  screen.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:24px;text-align:center;background:#fcf1db;color:#2a1a14;font:16px system-ui,sans-serif';

  const heading = document.createElement('h1');
  heading.textContent = 'Please update CampBuddy';
  heading.style.cssText = 'margin:0;font-size:1.4rem';
  const text = document.createElement('p');
  text.textContent = 'This version is too old to open. A quick update and you are back.';
  text.style.cssText = 'margin:0;max-width:26rem';
  screen.append(heading, text);

  if (storeUrl) {
    const button = document.createElement('a');
    button.href = storeUrl;
    button.textContent = 'Update';
    button.style.cssText = 'min-height:48px;min-width:160px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;background:#60121c;color:#fff;font-weight:600;text-decoration:none';
    screen.append(button);
  }

  document.body.append(screen);
}

function openInApp(url) {
  const path = safeInternalPath(url, location.origin);
  if (path && path !== location.pathname + location.search + location.hash) location.assign(path);
}

/** Wires the app-only behaviour. Called once, from app.js, when running inside the shell. */
export async function initNativeBridge() {
  track('app_open', { platform: `app_${nativePlatform()}` });

  const app = plugin('App');
  if (app) {
    // Android back: one page back, and leave the app from the first page.
    app.addListener('backButton', ({ canGoBack }) => {
      if (canGoBack) history.back();
      else app.exitApp();
    });
    // A campbuddy.club link opened with the app installed (QR code, shared link).
    app.addListener('appUrlOpen', ({ url }) => openInApp(url));
  }

  const notifications = plugin('LocalNotifications');
  notifications?.addListener('localNotificationActionPerformed', ({ notification }) => {
    const path = safeInternalPath(notification?.extra?.url ?? '', location.origin);
    if (path) location.assign(path);
  });

  // An announcement tapped (app closed or in the background): open its page.
  const push = plugin('PushNotifications');
  push?.addListener('pushNotificationActionPerformed', ({ notification }) => {
    const path = safeInternalPath(notification?.data?.url ?? '', location.origin);
    if (path) location.assign(path);
  });

  // Already said yes before: keep this install's token fresh for the event being viewed.
  kvGet('notificationState').then((state) => {
    if (state?.enabled) registerPush(eventSlugFromPath(location.pathname));
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') resyncReminders().catch(() => {});
  });

  checkMinimumVersion();
  resyncReminders().catch(() => {});
}
