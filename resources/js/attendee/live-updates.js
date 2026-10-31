// Live announcements from the people running the WordCamp ("Opening remarks are
// starting", "Tea break", "Lunch is served"…; App\Support\EventBroadcasts):
//
//   1. When notifications are on, tells the server this browser has the event
//      open (only the anonymous device id), at most every six hours —
//      announcements go to the devices seen since three days before the
//      event. With notifications off nothing is sent (a push could not reach
//      it anyway; the banner needs no record).
//   2. Shows the announcement sent in the last half hour as a banner at the
//      top of the event's pages, for everyone (no notification permission
//      needed); asked again when the page comes back into view, and every few
//      minutes while it is open. A closed banner stays closed.
//   3. From three days before the event until it ends, a small card on Home
//      asks to turn notifications on, so announcements reach the phone even
//      when CampBuddy is closed. Asked once: "Not now" is remembered.

import { apiGet, apiMutate } from './api.js';
import { getDeviceId } from './device.js';
import { isStandalone } from './platform.js';
import { lineIcon } from './line-icon.js';

const SEEN_EVERY_MS = 6 * 60 * 60 * 1000;
const CHECK_EVERY_MS = 4 * 60 * 1000;
const DAY_MS = 24 * 60 * 60 * 1000;

const store = {
  get(key) { try { return localStorage.getItem(key); } catch { return null; } },
  set(key, value) { try { localStorage.setItem(key, value); } catch { /* private mode */ } },
};

/** Whether to ping now: never pinged, or the last one is older than six hours. */
export function seenDue(last, now = Date.now()) {
  const at = Number(last);
  return !Number.isFinite(at) || at <= 0 || now - at >= SEEN_EVERY_MS;
}

/** Whether today is inside "three days before the first day … the last day" (dates as YYYY-MM-DD). */
export function inAskWindow(start, end, now = new Date()) {
  if (!start) return false;
  const today = now.toISOString().slice(0, 10);
  const from = new Date(Date.parse(`${start}T00:00:00Z`) - 3 * DAY_MS).toISOString().slice(0, 10);
  return today >= from && today <= (end || start);
}

/** Whether this browser can get a push at all: only then is the visit worth recording. */
export function notificationsOn() {
  return typeof Notification !== 'undefined' && Notification.permission === 'granted';
}

export async function markSeen(slug, { now = Date.now(), mutate = apiMutate, allowed = notificationsOn(), force = false } = {}) {
  const key = `campbuddy-seen:${slug}`;
  if (!allowed || (!force && !seenDue(store.get(key), now))) return false;
  await mutate(slug, '/seen', 'POST', { device_id: getDeviceId(), platform: isStandalone() ? 'pwa' : 'web' });
  store.set(key, String(now));
  return true;
}

function bannerEl(root, notice, slug) {
  const el = document.createElement('div');
  // Its colour and icon follow what is happening: talks, breaks and food, together moments.
  el.className = `live-notice live-notice--${['talk', 'break', 'together'].includes(notice.tone) ? notice.tone : 'talk'}`;
  el.setAttribute('role', 'status');
  el.dataset.liveNotice = String(notice.id);
  const text = document.createElement('div');
  text.className = 'live-notice__text';
  const title = document.createElement('strong');
  title.className = 'live-notice__title';
  title.textContent = notice.title;
  const body = document.createElement('span');
  body.className = 'live-notice__body';
  body.textContent = notice.body;
  text.append(title, body);
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'live-notice__close';
  close.setAttribute('aria-label', 'Close this announcement');
  close.textContent = '×';
  close.addEventListener('click', () => {
    store.set(`campbuddy-notice-closed:${slug}`, String(notice.id));
    el.remove();
  });
  const label = document.createElement('span');
  label.className = 'live-notice__kicker';
  label.textContent = 'From the organizers';
  text.prepend(label);
  const badge = document.createElement('span');
  badge.className = 'live-notice__icon';
  badge.setAttribute('aria-hidden', 'true');
  badge.append(lineIcon(notice.icon || 'megaphone'));
  el.append(badge, text, close);
  return el;
}

/** Draws (or takes away) the banner for the current announcement. */
export function showNotice(root, notice, slug) {
  root.querySelector('[data-live-notice]')?.remove();
  if (!notice || store.get(`campbuddy-notice-closed:${slug}`) === String(notice.id)) return null;
  const el = bannerEl(root, notice, slug);
  // Under the top bar (which stays put when scrolling), above the page.
  const bar = root.querySelector('.topbar');
  if (bar) bar.after(el);
  else root.prepend(el);
  return el;
}

async function check(root, slug) {
  try {
    const reply = await apiGet(slug, '/live-notice');
    showNotice(root, reply?.data ?? null, slug);
  } catch {
    // Offline or busy: the push still reaches the phone; the banner can wait.
  }
}

function askCard(root, slug) {
  const home = document.getElementById('home-data');
  const supported = 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
  if (!home || !supported || Notification.permission !== 'default') return;
  if (!inAskWindow(root.dataset.eventStart, root.dataset.eventEnd)) return;
  if (store.get(`campbuddy-live-ask:${slug}`)) return;

  const card = document.createElement('div');
  card.className = 'live-ask';
  card.innerHTML = '<p class="live-ask__title">Get live updates from the organizers</p>'
    + '<p class="live-ask__desc">"Opening remarks are starting", "Lunch is served", a session about to begin: a notification on this phone, even with CampBuddy closed.</p>'
    + '<div class="live-ask__actions"><button type="button" class="btn btn--primary btn--compact" data-live-yes>Turn on</button><button type="button" class="btn btn--secondary btn--compact" data-live-no>Not now</button></div>';
  card.querySelector('[data-live-no]').addEventListener('click', () => {
    store.set(`campbuddy-live-ask:${slug}`, 'no');
    card.remove();
    // What that turns off, with the way back on (notification-choices.js).
    document.dispatchEvent(new CustomEvent('campbuddy:notifications-off'));
  });
  card.querySelector('[data-live-yes]').addEventListener('click', async () => {
    store.set(`campbuddy-live-ask:${slug}`, 'asked');
    try {
      const answer = await Notification.requestPermission();
      if (answer === 'granted') {
        const { registerSubscription } = await import('./push.js');
        await registerSubscription(slug);
        markSeen(slug, { allowed: true, force: true }).catch(() => {});
        card.innerHTML = '<p class="live-ask__title">You will get the organizers\' updates.</p>';
        setTimeout(() => card.remove(), 4000);
        return;
      }
    } catch {
      // Blocked or not possible here: nothing more to ask.
    }
    card.remove();
    if (Notification.permission === 'denied') document.dispatchEvent(new CustomEvent('campbuddy:notifications-off'));
  });
  const anchor = document.getElementById('start-here') ?? root.querySelector('.home-section');
  if (anchor) anchor.before(card);
  else root.prepend(card);
}

export function initLiveUpdates(root = document.getElementById('app')) {
  const slug = root?.dataset.eventSlug;
  if (!slug) return;

  markSeen(slug).catch(() => {});
  check(root, slug);
  askCard(root, slug);

  const timer = setInterval(() => document.visibilityState === 'visible' && check(root, slug), CHECK_EVERY_MS);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return;
    check(root, slug);
    markSeen(slug).catch(() => {});
  });
  window.addEventListener('pagehide', () => clearInterval(timer), { once: true });
}
