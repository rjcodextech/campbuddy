// node --test tests/js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { RELOAD_GUARD_KEY, RELOAD_GUARD_MS, installPreloadRecovery } from '../../resources/js/attendee/preload-recovery.js';

/** A stand-in for `window`: events + sessionStorage. */
function fakeWindow({ storage = new Map(), storageThrows = false } = {}) {
  const target = new EventTarget();

  return Object.assign(target, {
    sessionStorage: storageThrows
      ? { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } }
      : { getItem: (k) => (storage.has(k) ? storage.get(k) : null), setItem: (k, v) => storage.set(k, v) },
    location: { reload() {} },
    storage,
  });
}

const preloadError = () => Object.assign(new Event('vite:preloadError', { cancelable: true }), { payload: new Error('Failed to fetch dynamically imported module') });

test('a missing chunk reloads the page once and the error is swallowed', () => {
  const win = fakeWindow();
  let reloads = 0;
  installPreloadRecovery({ win, reload: () => reloads++, now: () => 1_000_000 });

  const event = preloadError();
  win.dispatchEvent(event);

  assert.equal(reloads, 1);
  assert.equal(event.defaultPrevented, true);
  assert.equal(win.storage.get(RELOAD_GUARD_KEY), '1000000');
});

test('it never loops: a second failure within a minute leaves the error alone', () => {
  const win = fakeWindow();
  let reloads = 0;
  let time = 1_000_000;
  installPreloadRecovery({ win, reload: () => reloads++, now: () => time });

  win.dispatchEvent(preloadError());
  time += RELOAD_GUARD_MS - 1;
  const second = preloadError();
  win.dispatchEvent(second);

  assert.equal(reloads, 1);
  assert.equal(second.defaultPrevented, false, 'the real error still surfaces');
});

test('after the grace minute a later stale page may reload again', () => {
  const win = fakeWindow();
  let reloads = 0;
  let time = 1_000_000;
  installPreloadRecovery({ win, reload: () => reloads++, now: () => time });

  win.dispatchEvent(preloadError());
  time += RELOAD_GUARD_MS + 1;
  win.dispatchEvent(preloadError());

  assert.equal(reloads, 2);
});

test('where session storage is blocked nothing is reloaded (the loop guard could not be kept)', () => {
  const win = fakeWindow({ storageThrows: true });
  let reloads = 0;
  installPreloadRecovery({ win, reload: () => reloads++ });

  const event = preloadError();
  win.dispatchEvent(event);

  assert.equal(reloads, 0);
  assert.equal(event.defaultPrevented, false);
});

test('a garbage stored value counts as never reloaded', () => {
  const win = fakeWindow({ storage: new Map([[RELOAD_GUARD_KEY, 'not-a-number']]) });
  let reloads = 0;
  installPreloadRecovery({ win, reload: () => reloads++, now: () => 5_000_000 });

  win.dispatchEvent(preloadError());

  assert.equal(reloads, 1);
});
