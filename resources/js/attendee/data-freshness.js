// Keeps an open or installed app's event data up to date on its own.
//
// Every event page carries a fingerprint of its data (<meta
// name="campbuddy-data-version">). While the app is open this asks the server
// for the current fingerprint every few minutes (about 5 around the event's
// days, about 15 otherwise, spread so phones never ask in step, backing off
// when the server is struggling — see polling.js), when the app comes back to
// the foreground, and when the phone gets its connection back. If it has
// changed — a schedule update, a new sponsor, a room change — the page is
// refreshed:
//   - straight away if the person isn't in the middle of something (just
//     came back to the app, or hasn't touched it for a little while, and no
//     form field is being typed in), keeping their scroll position;
//   - otherwise a small "Updated — tap to refresh" pill appears, and the
//     refresh happens by itself as soon as they're idle.
//
// Before reloading, the saved copies of this event's pages are REPLACED by
// fresh ones (saved-copies.js) — never deleted first — so a phone whose
// connection drops half-way still has every page to open offline. The
// attendee's own data (saved sessions, Quest progress, Camp Card) lives in
// IndexedDB and is never touched.

import { apiHeaders } from './api.js';
import { inEventWindow, nextDelay, retryAfterMs } from './polling.js';
import { refreshCopies } from './saved-copies.js';

const MIN_GAP_MS = 45 * 1000;
const IDLE_MS = 30 * 1000;
const SCROLL_KEY = 'campbuddy:restore-scroll';

let lastCheck = 0;
let lastInteraction = Date.now();
let pendingReload = false;
// When the next regular check is due, and how many asks in a row have failed.
let nextCheckAt = 0;
let failures = 0;

/** The event's dates and zone as the page states them (see layouts/attendee.blade.php). */
function eventFacts() {
  const d = document.getElementById('app')?.dataset ?? {};

  return { start: d.eventStart || null, end: d.eventEnd || null, timezone: d.eventTimezone || null };
}

function scheduleNext(retryAfter = 0) {
  nextCheckAt = Date.now() + nextDelay({ inWindow: inEventWindow(eventFacts()), failures, retryAfter });
}

function pageVersion() {
  return document.querySelector('meta[name="campbuddy-data-version"]')?.content ?? null;
}

function busy() {
  const el = document.activeElement;
  const typing = el && (el.matches?.('input, textarea, select, [contenteditable="true"]') ?? false);
  const dialogOpen = !!document.querySelector('dialog[open]');

  return typing || dialogOpen || Date.now() - lastInteraction < IDLE_MS;
}

/** This event's saved pages, each swapped for a fresh copy in place. Nothing is deleted, whatever the network does. */
async function refreshSavedPages(slug) {
  await refreshCopies((url) => url.pathname === `/event/${slug}` || url.pathname.startsWith(`/event/${slug}/`));
}

async function refresh(slug) {
  await refreshSavedPages(slug);

  try {
    sessionStorage.setItem(SCROLL_KEY, JSON.stringify({ path: location.pathname, y: window.scrollY }));
  } catch (err) {
    // Just lands at the top.
  }

  location.reload();
}

function showPill(slug) {
  if (document.getElementById('data-updated-pill')) return;

  const pill = document.createElement('button');
  pill.type = 'button';
  pill.id = 'data-updated-pill';
  pill.className = 'data-updated-pill';
  pill.textContent = 'Updated info — tap to refresh';
  pill.addEventListener('click', () => refresh(slug));
  document.body.appendChild(pill);
}

async function check(slug, { returning = false, minGap = MIN_GAP_MS } = {}) {
  if (!navigator.onLine || document.visibilityState !== 'visible') return;
  if (Date.now() - lastCheck < minGap) return;
  lastCheck = Date.now();

  let latest;
  try {
    // No cache-busting query string: the server answers with an ETag (an empty
    // 304 when nothing has changed) and a CDN may hold the answer for a few
    // seconds, which is what keeps a crowd of phones cheap. `no-cache` = always
    // check with the server, never trust a stored copy.
    const response = await fetch(`/api/v1/events/${encodeURIComponent(slug)}/data-version`, {
      cache: 'no-cache',
      headers: apiHeaders(),
    });

    if (!response.ok) {
      // A busy or struggling server (429, 5xx): give it room.
      failures++;
      scheduleNext(retryAfterMs(response.headers.get('Retry-After')));
      return;
    }

    latest = (await response.json()).version;
  } catch (err) {
    // Offline or flaky wifi — back off, and try again later.
    failures++;
    scheduleNext();
    return;
  }

  failures = 0;
  scheduleNext();

  if (!latest || latest === pageVersion()) return;

  // Coming back to the app is the natural moment: nothing is in progress yet.
  if (returning && !document.querySelector('dialog[open]')) {
    refresh(slug);
    return;
  }

  if (busy()) {
    pendingReload = true;
    showPill(slug);
    return;
  }

  refresh(slug);
}

function restoreScroll() {
  try {
    const saved = JSON.parse(sessionStorage.getItem(SCROLL_KEY) ?? 'null');
    sessionStorage.removeItem(SCROLL_KEY);
    if (saved && saved.path === location.pathname) {
      // After the page's own rendering has had a moment to lay things out.
      requestAnimationFrame(() => setTimeout(() => window.scrollTo(0, saved.y), 150));
    }
  } catch (err) {
    // Nothing saved.
  }
}

export function initDataFreshness() {
  const slug = document.getElementById('app')?.dataset.eventSlug;
  if (!slug || !pageVersion()) return;

  restoreScroll();

  const touched = () => {
    lastInteraction = Date.now();
  };
  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((type) =>
    window.addEventListener(type, touched, { passive: true })
  );

  let hiddenAt = 0;
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      hiddenAt = Date.now();
      return;
    }
    // Only a real return counts (not a quick glance at a notification).
    check(slug, { returning: hiddenAt > 0 && Date.now() - hiddenAt > MIN_GAP_MS });
  });

  window.addEventListener('online', () => check(slug));

  // The page was just built, so the first regular check is a while away.
  lastCheck = Date.now();
  scheduleNext();

  setInterval(() => {
    if (pendingReload && !busy()) {
      refresh(slug);
      return;
    }
    if (Date.now() >= nextCheckAt) check(slug, { minGap: 0 });
  }, 30 * 1000);
}
