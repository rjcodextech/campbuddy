// Notifications that were turned down, and the way back (owner, 31 Oct 2026):
//
//   1. Someone who said no — "No thanks" to the reminder question (push.js), "Not now" on the live-updates
//      card (live-updates.js), or the browser's own Block — is told in two lines what that turns off and
//      that everything else still works, with one button to turn notifications on again. Right after the
//      no, then on Home and My Day at most once a day ("Not now"), and only until the event is over.
//   2. The "Your choices" card (next to "Your data") always shows where things stand, with "Turn on all"
//      and the Google Analytics choice. Analytics is never tied to a feature: saying no to it changes
//      nothing in the app, so nothing here asks for it.
//
// A browser that has blocked notifications can't be asked again by the page (the browser's rule), so the
// button then shows where to allow them, and CampBuddy finishes turning them on when the person comes back.
// The store app has its own version of all this (native-app … ui/NotificationChoices.kt).

import { apiMutate } from './api.js';
import { getBookmarks, kvGet, kvSet, updateBookmark } from './db.js';
import { getDeviceId } from './device.js';
import { isPermanentFailure } from './outbox.js';
import { inAskWindow } from './live-updates.js';
import { isIos, isNativeApp, isStandalone } from './platform.js';

const HIDE_FOR_MS = 24 * 60 * 60 * 1000;

const store = {
  get(key) { try { return localStorage.getItem(key); } catch { return null; } },
  set(key, value) { try { localStorage.setItem(key, value); } catch { /* private mode */ } },
};

/**
 * Where notifications stand on this browser for one event: `permission` is the browser's answer
 * ('granted' | 'default' | 'denied'), or 'install' (iPhone, not added to the Home Screen yet) or
 * 'unsupported'; `reminders` and `updates` whether each reaches the phone; `turnedDown` whether the
 * person said no to something (or took a yes back) — the only case the notice shows.
 */
export function notificationStatus({ permission, state = {}, liveAnswer = null }) {
  const granted = permission === 'granted';
  const reminders = granted && Boolean(state.enabled);
  const updates = granted;
  const turnedDown = Boolean(state.declined || state.permanentlyDenied || permission === 'denied' || liveAnswer === 'no' || (state.enabled && !granted));
  return { permission, reminders, updates, turnedDown, allOn: reminders && updates };
}

/** Whether to show "Notifications are off": something was turned down, it can be turned on here, the event isn't over, and "Not now" wasn't tapped in the last day. */
export function showOffNotice(status, { today, lastDay, hiddenAt = null, now = Date.now(), justNow = false }) {
  if (!status.turnedDown || status.allOn || status.permission === 'unsupported' || status.permission === 'install') return false;
  if (lastDay && today > lastDay) return false;
  if (justNow) return true;
  const at = Number(hiddenAt);
  return !(Number.isFinite(at) && at > 0 && now - at < HIDE_FOR_MS);
}

/** What the notice says is off: only what really is (organizer updates can be on while talk reminders are off). */
export function offItems(status) {
  return [
    !status.reminders && 'A reminder before the talks you save',
    !status.updates && 'Live updates from the organizers (a session starting, lunch served)',
  ].filter(Boolean);
}

/** The one-line answer for a row of "Your choices". */
export function choiceLabel(on, permission) {
  if (on) return 'On';
  if (permission === 'denied') return 'Blocked in the browser';
  if (permission === 'install') return 'Add CampBuddy to the Home Screen first';
  if (permission === 'unsupported') return 'Not available in this browser';
  return 'Off';
}

/** Where to allow notifications once the browser has blocked them: the installed app, or a browser tab. */
export function blockedSteps(installed) {
  return installed
    ? ['Long-press the CampBuddy icon on your phone, then App info.', 'Notifications → Allow.', 'Come back here: CampBuddy turns them on by itself.']
    : ['Tap the icon next to the web address, then Permissions or Site settings.', 'Notifications → Allow.', 'Come back here: CampBuddy turns them on by itself (or reload this page).'];
}

const analyticsLabel = (answer) => (answer === 'granted' ? 'Allowed' : answer === 'denied' ? 'Not allowed' : 'Not answered yet');

function browserPermission() {
  if ('Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window) return Notification.permission;
  return isIos() && !isStandalone() ? 'install' : 'unsupported';
}

async function currentStatus(slug) {
  return notificationStatus({
    permission: browserPermission(),
    state: (await kvGet('notificationState')) ?? {},
    liveAnswer: store.get(`campbuddy-live-ask:${slug}`),
  });
}

/**
 * "Turn on all": asks the browser (when it still may), then turns on talk reminders for every saved talk
 * still ahead and the organizers' updates. Resolves 'on', 'blocked' (the browser says no, now or before),
 * 'dismissed' (the question was closed), 'install' or 'unsupported'.
 */
export async function turnOnAll(slug, eventId) {
  const permission = browserPermission();
  if (permission === 'install' || permission === 'unsupported') return permission;
  const state = (await kvGet('notificationState')) ?? {};
  const answer = permission === 'granted' ? 'granted' : permission === 'denied' ? 'denied' : await Notification.requestPermission();
  if (answer === 'denied') {
    await kvSet('notificationState', { ...state, permanentlyDenied: true });
    return 'blocked';
  }
  if (answer !== 'granted') return 'dismissed';

  await kvSet('notificationState', { ...state, enabled: true, declined: false, permanentlyDenied: false });
  store.set(`campbuddy-live-ask:${slug}`, 'asked');
  // The server side in the background: the screen says "on" now, whatever the connection.
  syncAll(slug, eventId).catch(() => {});

  return 'on';
}

/** The subscription, a reminder for every saved talk still ahead, and "this phone has the event open". */
async function syncAll(slug, eventId) {
  const { outbox, registerSubscription } = await import('./push.js');
  const now = Date.now();
  const ahead = (await getBookmarks(eventId)).filter((b) => !b.reminderEnabled && !(Number.isFinite(b.startMs) && b.startMs < now));
  try {
    await registerSubscription(slug);
    for (const b of ahead) {
      try {
        await apiMutate(slug, '/bookmarks', 'POST', { device_id: getDeviceId(), session_id: b.sessionId, reminder_enabled: true });
        await updateBookmark(eventId, b.sessionId, { reminderEnabled: true });
      } catch (error) {
        if (!isPermanentFailure(error)) await outbox.add(slug, { kind: 'reminder-on', sessionId: b.sessionId, eventId });
      }
    }
  } catch {
    // No connection: the subscription is sent by push-sync.js when it's back, and the reminders with it.
    for (const b of ahead) await outbox.add(slug, { kind: 'reminder-on', sessionId: b.sessionId, eventId });
  }
  const { markSeen } = await import('./live-updates.js');
  markSeen(slug, { allowed: true, force: true }).catch(() => {});
}

// ---- On the page --------------------------------------------------------------

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function listOf(items, ordered = false) {
  const list = el(ordered ? 'ol' : 'ul', 'live-ask__list');
  items.forEach((item) => list.append(el('li', '', item)));
  return list;
}

/** Runs `then` once the browser's notification answer changes to "allowed" (after the person allowed it in settings). */
function whenAllowed(then) {
  const done = () => { if (Notification.permission === 'granted') { then(); return true; } return false; };
  navigator.permissions?.query({ name: 'notifications' }).then((p) => { p.addEventListener('change', () => done()); }).catch(() => {});
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') done(); });
}

/** The card's body after "Turn on": on, or where to allow them in a browser that blocked them. */
function afterTurnOn(card, result, slug, eventId, onChange) {
  const body = card.querySelector('[data-off-body]');
  if (result === 'on') {
    body.replaceChildren(el('p', 'live-ask__title', "Notifications are on. You'll get a reminder before your saved talks and the organizers' updates."));
    onChange();
    setTimeout(() => card.remove(), 5000);
    return;
  }
  if (result === 'blocked') {
    body.replaceChildren(
      el('p', 'live-ask__title', 'Your browser is blocking notifications for CampBuddy'),
      el('p', 'live-ask__desc', 'This page is not allowed to ask again. To allow them:'),
      listOf(blockedSteps(isStandalone()), true),
    );
    whenAllowed(async () => afterTurnOn(card, await turnOnAll(slug, eventId), slug, eventId, onChange));
  }
}

/** "Notifications are off": what that turns off, what still works, and the way back on. */
async function renderOffNotice(root, { justNow = false } = {}) {
  const slug = root.dataset.eventSlug;
  const eventId = Number(root.dataset.eventId);
  root.querySelector('[data-notifications-off]')?.remove();
  const status = await currentStatus(slug);
  const today = new Date().toISOString().slice(0, 10);
  if (!showOffNotice(status, { today, lastDay: root.dataset.eventEnd || root.dataset.eventStart, hiddenAt: store.get(`campbuddy-off-hidden:${slug}`), justNow })) return;
  // On Home, while the live-updates card is still asking (live-updates.js), only it asks.
  const askCard = document.getElementById('home-data') && status.permission === 'default' && store.get(`campbuddy-live-ask:${slug}`) === null && inAskWindow(root.dataset.eventStart, root.dataset.eventEnd);
  if (askCard && !justNow) return;

  const title = status.updates ? 'Talk reminders are off' : 'Notifications are off';
  const card = el('section', 'live-ask live-ask--off');
  card.dataset.notificationsOff = '';
  card.setAttribute('aria-label', title);
  const body = el('div');
  body.dataset.offBody = '';
  body.append(
    el('p', 'live-ask__title', title),
    el('p', 'live-ask__desc', "So you won't get:"),
    listOf(offItems(status)),
    el('p', 'live-ask__desc', 'Everything else still works: the schedule, your saved talks, People, your Camp Card, even offline.'),
  );
  const actions = el('div', 'live-ask__actions');
  const on = el('button', 'btn btn--primary btn--compact', status.updates ? 'Turn on reminders' : 'Turn on notifications');
  on.type = 'button';
  const later = el('button', 'btn btn--secondary btn--compact', 'Not now');
  later.type = 'button';
  later.addEventListener('click', () => { store.set(`campbuddy-off-hidden:${slug}`, String(Date.now())); card.remove(); });
  on.addEventListener('click', async () => {
    on.disabled = true;
    const result = await turnOnAll(slug, eventId).catch(() => 'dismissed');
    on.disabled = false;
    if (result === 'on' || result === 'blocked') actions.remove();
    afterTurnOn(card, result, slug, eventId, () => fillChoices(root));
  });
  actions.append(on, later);
  card.append(body, actions);
  const main = document.getElementById('main-content');
  if (main) main.prepend(card);
  else root.prepend(card);
}

/** "Your choices": each row's answer, and "Turn on all" while something is off. */
async function fillChoices(root) {
  const slug = root.dataset.eventSlug;
  const eventId = Number(root.dataset.eventId);
  const status = await currentStatus(slug);
  document.querySelectorAll('[data-notification-choices]').forEach((card) => {
    card.querySelector('[data-choice="reminders"]').textContent = choiceLabel(status.reminders, status.permission);
    card.querySelector('[data-choice="updates"]').textContent = choiceLabel(status.updates, status.permission);
    const analytics = card.querySelector('[data-choice="analytics"]');
    if (analytics) analytics.textContent = analyticsLabel(store.get('cb-analytics-consent'));

    const button = card.querySelector('[data-choice-turn-on]');
    const note = card.querySelector('[data-choice-note]');
    // Blocked by the browser: the page can't ask, so no button, only the steps (and it finishes by itself once allowed).
    const canTurnOn = !status.allOn && status.permission !== 'unsupported' && status.permission !== 'denied';
    button.hidden = !canTurnOn;
    button.textContent = status.permission === 'install' ? 'How to get notifications' : 'Turn on all';
    note.hidden = true;
    if (status.permission === 'denied') {
      note.replaceChildren(el('span', '', 'Your browser is blocking them. To allow: '), el('span', '', blockedSteps(isStandalone()).join(' ')));
      note.hidden = false;
      if (!card.dataset.waiting) {
        card.dataset.waiting = '1';
        whenAllowed(async () => { await turnOnAll(slug, eventId); root.querySelector('[data-notifications-off]')?.remove(); fillChoices(root); });
      }
    }
    if (button.dataset.wired) return;
    button.dataset.wired = '1';
    button.addEventListener('click', async () => {
      button.disabled = true;
      const result = await turnOnAll(slug, eventId).catch(() => 'dismissed');
      button.disabled = false;
      if (result === 'install') (await import('./push.js')).showIosInstallPrompt();
      if (result === 'blocked') whenAllowed(async () => { await turnOnAll(slug, eventId); fillChoices(root); root.querySelector('[data-notifications-off]')?.remove(); });
      if (result === 'on') root.querySelector('[data-notifications-off]')?.remove();
      fillChoices(root);
    });
  });
}

export function initNotificationChoices(root = document.getElementById('app')) {
  if (!root?.dataset.eventSlug || isNativeApp()) return;

  if (document.getElementById('home-data') || document.getElementById('my-day-data')) renderOffNotice(root).catch(() => {});
  // Right after a no (push.js, live-updates.js): what it turns off, on whatever page this is.
  document.addEventListener('campbuddy:notifications-off', () => { renderOffNotice(root, { justNow: true }).catch(() => {}); fillChoices(root).catch(() => {}); });

  if (!document.querySelector('[data-notification-choices]')) return;
  fillChoices(root).catch(() => {});
  // The analytics answer changes when its bar closes ([data-analytics-choice] in the card opens it).
  let asking = Boolean(document.getElementById('cb-consent'));
  new MutationObserver(() => {
    const open = Boolean(document.getElementById('cb-consent'));
    if (asking && !open) fillChoices(root).catch(() => {});
    asking = open;
  }).observe(document.body, { childList: true });
}
