// How an admin's "Purge cache & refresh data" reaches phones.
//
// Every page carries the current cache version in a <meta> tag (a number the
// server bumps on each purge). This device remembers the newest version it has
// seen; when it meets a newer one it REPLACES the browser's saved copies of
// pages with fresh ones (the service worker's Cache Storage — saved-copies.js),
// so nothing stale lingers. It never deletes them first: if the connection is
// poor the old copy stays and the app keeps working offline (docs
// spec/23-data-retention.md). Files with a hash in their name (/build/) never
// go stale, so they are left alone. An app left open in the background can't
// see a new page, so when it returns to the foreground it also asks the server
// for the version and reloads if a purge happened meanwhile.
//
// What is never touched: the attendee's own data (saved sessions, quest
// progress, Camp Card) lives in IndexedDB / localStorage under other keys.

import { refreshCopies } from './saved-copies.js';

const STORAGE_KEY = 'campbuddy:cache-version';

// Coming back to the app a few times a minute shouldn't mean a request each time.
const MIN_CHECK_INTERVAL_MS = 60 * 1000;

// null = this device has never recorded a version (first visit, or storage is
// blocked); otherwise the newest version it has seen, which may legitimately be 0.
function storedVersion() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw === null ? null : Number(raw) || 0;
  } catch (err) {
    return null;
  }
}

function rememberVersion(version) {
  try {
    localStorage.setItem(STORAGE_KEY, String(version));
  } catch (err) {
    // Not persisted: worst case the next page asks again.
  }
}

/** Every saved page (not the hashed /build/ files) swapped for a fresh copy in place. Nothing is deleted. */
async function refreshSavedCopies() {
  await refreshCopies((url) => !url.pathname.startsWith('/build/'), { timeoutMs: 10000 });
}

async function checkForPurge() {
  try {
    // A throwaway query string as well as no-store, in case a CDN rule ignores headers.
    const response = await fetch(`/api/v1/cache-version?t=${Date.now()}`, {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) return;

    const latest = Number((await response.json()).version) || 0;

    if (latest > (storedVersion() ?? 0)) {
      // Remember first, so the reload can't trigger this again.
      rememberVersion(latest);
      await refreshSavedCopies();
      location.reload();
    }
  } catch (err) {
    // Offline — nothing to learn. Try again next time the app is opened.
  }
}

export function initCacheVersion() {
  const served = Number(document.querySelector('meta[name="campbuddy-cache-version"]')?.content) || 0;
  const known = storedVersion();

  if (known === null) {
    // First visit on this device: nothing saved yet, just remember where we are.
    rememberVersion(served);
  } else if (served > known) {
    // This page came from the server already carrying a newer version than the
    // device last saw: a purge has happened. The page in front of us is fresh;
    // refresh the saved copies of every other page (in place — never deleted).
    refreshSavedCopies();
    rememberVersion(served);
  }

  let lastCheck = Date.now();

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return;
    if (Date.now() - lastCheck < MIN_CHECK_INTERVAL_MS) return;

    lastCheck = Date.now();
    checkForPurge();
  });
}
