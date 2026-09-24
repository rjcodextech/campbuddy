// Keeps an open or installed app's event data up to date on its own.
//
// Every event page carries a fingerprint of its data (<meta
// name="campbuddy-data-version">). While the app is open this asks the server
// for the current fingerprint every few minutes, when the app comes back to
// the foreground, and when the phone gets its connection back. If it has
// changed — a schedule update, a new sponsor, a room change — the page is
// refreshed:
//   - straight away if the person isn't in the middle of something (just
//     came back to the app, or hasn't touched it for a little while, and no
//     form field is being typed in), keeping their scroll position;
//   - otherwise a small "Updated — tap to refresh" pill appears, and the
//     refresh happens by itself as soon as they're idle.
//
// Before reloading, the saved copies of this event's pages are dropped, so
// the service worker fetches fresh ones rather than serving a saved copy.
// The attendee's own data (saved sessions, Quest progress, Camp Card) lives
// in IndexedDB and is never touched.

import { apiHeaders } from './api.js';

const CHECK_EVERY_MS = 5 * 60 * 1000;
const MIN_GAP_MS = 45 * 1000;
const IDLE_MS = 30 * 1000;
const SCROLL_KEY = 'campbuddy:restore-scroll';

let lastCheck = 0;
let lastInteraction = Date.now();
let pendingReload = false;

function pageVersion() {
  return document.querySelector('meta[name="campbuddy-data-version"]')?.content ?? null;
}

function busy() {
  const el = document.activeElement;
  const typing = el && (el.matches?.('input, textarea, select, [contenteditable="true"]') ?? false);
  const dialogOpen = !!document.querySelector('dialog[open]');

  return typing || dialogOpen || Date.now() - lastInteraction < IDLE_MS;
}

async function dropSavedPages(slug) {
  if (!('caches' in window)) return;

  try {
    for (const name of await caches.keys()) {
      const cache = await caches.open(name);
      for (const request of await cache.keys()) {
        if (new URL(request.url).pathname.startsWith(`/event/${slug}`)) {
          await cache.delete(request);
        }
      }
    }
  } catch (err) {
    // Blocked or unavailable — the network-first worker still tries the network first.
  }
}

async function refresh(slug) {
  await dropSavedPages(slug);

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
    const response = await fetch(`/api/v1/events/${encodeURIComponent(slug)}/data-version?t=${Date.now()}`, {
      cache: 'no-store',
      headers: apiHeaders(),
    });
    if (!response.ok) return;
    latest = (await response.json()).version;
  } catch (err) {
    return; // Offline or flaky wifi — try again next time.
  }

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

  // The page was just built, so the first regular check is CHECK_EVERY_MS away.
  lastCheck = Date.now();

  setInterval(() => {
    if (pendingReload && !busy()) {
      refresh(slug);
      return;
    }
    check(slug, { minGap: CHECK_EVERY_MS });
  }, 30 * 1000);
}
