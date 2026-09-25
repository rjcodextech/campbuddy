// node --test tests/js  — the admin's "Purge cache" reaching a phone.
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { fakeCaches, flush, freshImport, installFakeBrowser, reply } from './helpers/fake-browser.mjs';

const MODULE = new URL('../../resources/js/attendee/cache-version.js', import.meta.url).href;
const O = 'https://campbuddy.test';

let browser;

const saved = () => fakeCaches({
  'campbuddy-v3': {
    [`${O}/event/wc-test`]: reply('old home'),
    [`${O}/event/other-camp/quest`]: reply('old other'),
    [`${O}/build/assets/app-abc.js`]: reply('hashed js'),
    [`${O}/media/logo.png`]: reply('logo'),
  },
});
const bodyIn = async (url) => (await browser.caches.match(url))?.body;

/** Boots the module on a page carrying `served` as its cache version, on a phone that last saw `known` (null = first visit). */
async function boot({ served = '5', known = null, ...options } = {}) {
  mock.timers.enable({ apis: ['setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  browser = installFakeBrowser({ cacheVersion: served, caches: saved(), ...options });
  if (known !== null) browser.store.set('campbuddy:cache-version', String(known));

  const { initCacheVersion } = await freshImport(MODULE);
  initCacheVersion();
  await flush();
}

afterEach(() => {
  browser?.restore();
  mock.timers.reset();
});

test('a first visit just remembers the version and touches nothing', async () => {
  await boot({ served: '5', known: null });

  assert.equal(browser.store.get('campbuddy:cache-version'), '5');
  assert.equal(browser.fetches.length, 0);
  assert.equal(await bodyIn(`${O}/event/wc-test`), 'old home');
});

test('a purge seen on a fresh page: saved pages other than build files are re-fetched and replaced in place', async () => {
  mock.timers.enable({ apis: ['setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  browser = installFakeBrowser({ cacheVersion: '7', caches: saved() });
  browser.store.set('campbuddy:cache-version', '5');
  browser.responses.push((url) => reply(`fresh ${new URL(url).pathname}`));

  const { initCacheVersion } = await freshImport(MODULE);
  initCacheVersion();
  await flush(30);

  assert.equal(await bodyIn(`${O}/event/wc-test`), 'fresh /event/wc-test');
  assert.equal(await bodyIn(`${O}/event/other-camp/quest`), 'fresh /event/other-camp/quest', 'a purge covers every saved page');
  assert.equal(await bodyIn(`${O}/media/logo.png`), 'fresh /media/logo.png');
  assert.equal(await bodyIn(`${O}/build/assets/app-abc.js`), 'hashed js', 'hashed build files never go stale, so they are left alone');
  assert.deepEqual(browser.caches.deleted, [], 'nothing deleted');
  assert.equal(browser.store.get('campbuddy:cache-version'), '7');
});

test('a purge while the phone has a poor connection leaves every saved page in place', async () => {
  mock.timers.enable({ apis: ['setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  browser = installFakeBrowser({ cacheVersion: '7', caches: saved() });
  browser.store.set('campbuddy:cache-version', '5');
  browser.responses.push(() => { throw new TypeError('offline'); });

  const { initCacheVersion } = await freshImport(MODULE);
  initCacheVersion();
  await flush(30);

  assert.equal(await bodyIn(`${O}/event/wc-test`), 'old home');
  assert.equal(await bodyIn(`${O}/event/other-camp/quest`), 'old other');
  assert.deepEqual(browser.caches.deleted, []);
});

test('an app left open in the background learns of a purge on coming back, refreshes in place, then reloads', async () => {
  await boot({ served: '5', known: 5 });
  browser.responses.push((url) => (url.includes('/cache-version') ? reply({ version: '9' }) : reply(`fresh ${new URL(url).pathname}`)));

  mock.timers.tick(2 * 60 * 1000); // away for two minutes
  browser.setVisible(true);
  browser.fireDocument('visibilitychange');
  await flush(40);

  assert.ok(browser.fetches.some((f) => f.url.startsWith('/api/v1/cache-version')));
  assert.equal(browser.store.get('campbuddy:cache-version'), '9');
  assert.equal(await bodyIn(`${O}/event/wc-test`), 'fresh /event/wc-test');
  assert.deepEqual(browser.caches.deleted, []);
  assert.equal(browser.location.reloads, 1);
});

test('coming back with the same version does nothing', async () => {
  await boot({ served: '5', known: 5 });
  browser.responses.push(reply({ version: '5' }));

  mock.timers.tick(2 * 60 * 1000);
  browser.fireDocument('visibilitychange');
  await flush(20);

  assert.equal(browser.location.reloads, 0);
  assert.equal(await bodyIn(`${O}/event/wc-test`), 'old home');
});
