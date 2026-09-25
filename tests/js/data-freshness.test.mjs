// node --test tests/js
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { flush, freshImport, installFakeBrowser, reply } from './helpers/fake-browser.mjs';

const MODULE = new URL('../../resources/js/attendee/data-freshness.js', import.meta.url).href;

let browser;

/**
 * Starts the module at a given moment; timers are mocked so minutes pass
 * instantly. Randomness is fixed at 0.5 (no spread: a 5-minute wait is exactly
 * 5 minutes) unless `random: null` asks for the real thing.
 */
async function start({ now = '2026-10-10T08:00:00Z', random = 0.5, ...options } = {}) {
  mock.timers.enable({ apis: ['setInterval', 'setTimeout', 'Date'], now: Date.parse(now) });
  if (random !== null) mock.method(Math, 'random', () => random);
  browser = installFakeBrowser(options);
  const { initDataFreshness } = await freshImport(MODULE);
  initDataFreshness();
}

/** Lets `minutes` pass in 30-second steps, letting each step's network work finish. */
async function pass(minutes) {
  for (let i = 0; i < minutes * 2; i++) {
    mock.timers.tick(30 * 1000);
    await flush();
  }
}

afterEach(() => {
  browser?.restore();
  mock.restoreAll();
  mock.timers.reset();
});

const asks = () => browser.fetches.filter((f) => f.url.includes('/data-version'));

test('around the event days it asks about every 5 minutes, without a cache-busting query string', async () => {
  await start();

  await pass(4.5);
  assert.equal(asks().length, 0, 'not yet');

  await pass(1);
  assert.equal(asks().length, 1);

  const [{ url, options }] = asks();
  assert.equal(url, '/api/v1/events/wc-test/data-version', 'no ?t=... so the server, browser and any CDN can validate it');
  assert.equal(options.cache, 'no-cache', 'always validate, never trust a stored copy');
  assert.match(options.headers['X-CampBuddy-Device'], /^[0-9a-f-]{36}$/);

  await pass(5);
  assert.equal(asks().length, 2, 'and again 5 minutes later');
});

test('outside the event days it asks about every 15 minutes', async () => {
  await start({ now: '2026-09-20T08:00:00Z' });

  await pass(14.5);
  assert.equal(asks().length, 0);

  await pass(1);
  assert.equal(asks().length, 1);
});

test('the wait is spread, so phones opened at the same moment do not keep asking in step', async () => {
  const firstAsk = [];

  for (let phone = 0; phone < 8; phone++) {
    await start({ random: null });
    let minutes = 0;
    while (asks().length === 0 && minutes < 8) {
      await pass(0.5);
      minutes += 0.5;
    }
    firstAsk.push(minutes);
    browser.restore();
    mock.timers.reset();
  }

  assert.ok(new Set(firstAsk).size > 1, `first asks were all at the same minute: ${firstAsk}`);
  assert.ok(firstAsk.every((m) => m >= 4 && m <= 6), `each within 5 min +-20%: ${firstAsk}`);
});

test('a busy server is given room: 429 with Retry-After waits at least that long', async () => {
  await start();
  browser.responses.push(reply({}, { status: 429, headers: { 'Retry-After': '720' } }), reply({ version: 'v1' }));

  await pass(5.5);
  assert.equal(asks().length, 1, 'asked at 5:00, answered 429');

  await pass(11);
  assert.equal(asks().length, 1, '16:30 - still inside the 12 minutes it asked for (5:00 + 12:00 = 17:00)');

  await pass(1);
  assert.equal(asks().length, 2);
});

test('failures back off, and a success goes back to the normal pace', async () => {
  await start();
  browser.responses.push(new Error('offline'), new Error('offline'), reply({ version: 'v1' }));

  await pass(5.5);
  assert.equal(asks().length, 1, '5:00 fails');

  await pass(8);
  assert.equal(asks().length, 1, '13:30 - backing off (next at 15:00), not retrying every 5 minutes');
  await pass(2);
  assert.equal(asks().length, 2, '15:30 - fails again');

  await pass(13);
  assert.equal(asks().length, 2, '28:30 - backing off to the 15-minute cap (next at 30:00)');
  await pass(2);
  assert.equal(asks().length, 3, '30:30 - succeeds');

  await pass(4);
  assert.equal(asks().length, 3, '34:30');
  await pass(1.5);
  assert.equal(asks().length, 4, '36:00 - back to every 5 minutes');
});

test('nothing is asked while offline or while the app is in the background', async () => {
  await start({ online: false });
  await pass(20);
  assert.equal(asks().length, 0, 'offline');
  browser.restore();
  mock.restoreAll();
  mock.timers.reset();

  await start({ visible: false });
  await pass(20);
  assert.equal(asks().length, 0, 'hidden');
});

test('coming back to the app after a while checks straight away, not at the next scheduled time', async () => {
  await start();

  browser.setVisible(false);
  browser.fireDocument('visibilitychange'); // sent to the background
  await pass(2); // two quiet minutes: nothing asked while hidden
  assert.equal(asks().length, 0);

  browser.setVisible(true);
  browser.fireDocument('visibilitychange'); // back again
  await flush();

  assert.equal(asks().length, 1, 'asked at once (the regular ask is still 3 minutes away)');
});

// ------------------------------------------------------------------ when the event's data changes: replace, never delete

import { fakeCaches } from './helpers/fake-browser.mjs';

const O = 'https://campbuddy.test';
const savedPages = () => fakeCaches({
  'campbuddy-v3': {
    [`${O}/event/wc-test`]: reply('old home'),
    [`${O}/event/wc-test/my-day`]: reply('old my day'),
    [`${O}/event/other-camp`]: reply('another event'),
    [`${O}/build/assets/app-abc.js`]: reply('js'),
  },
});
const bodyIn = async (url) => (await browser.caches.match(url))?.body;

test('when the data changes, this event saved pages are swapped for fresh ones and only then the page reloads; nothing is deleted', async () => {
  await start({ caches: savedPages() });
  browser.responses.push((url) => (url.includes('/data-version') ? reply({ version: 'v2' }) : reply(`fresh ${new URL(url).pathname}`)));

  await pass(5.5);

  assert.equal(await bodyIn(`${O}/event/wc-test`), 'fresh /event/wc-test');
  assert.equal(await bodyIn(`${O}/event/wc-test/my-day`), 'fresh /event/wc-test/my-day');
  assert.equal(await bodyIn(`${O}/event/other-camp`), 'another event', 'another event is left alone');
  assert.equal(await bodyIn(`${O}/build/assets/app-abc.js`), 'js');
  assert.deepEqual(browser.caches.deleted, [], 'no saved copy was ever deleted');
  assert.equal(browser.location.reloads, 1);

  const pageFetches = browser.fetches.filter((f) => f.url.includes('/event/wc-test'));
  assert.ok(pageFetches.length >= 2 && pageFetches.every((f) => f.options.cache === 'reload'), 'fresh from the server, not the browser cache');
});

test('if the connection drops while refreshing, the saved pages are all still there', async () => {
  await start({ caches: savedPages() });
  browser.responses.push((url) => {
    if (url.includes('/data-version')) return reply({ version: 'v2' });
    throw new TypeError('connection dropped');
  });

  await pass(5.5);

  assert.equal(await bodyIn(`${O}/event/wc-test`), 'old home');
  assert.equal(await bodyIn(`${O}/event/wc-test/my-day`), 'old my day');
  assert.deepEqual(browser.caches.deleted, []);
});

test('an unchanged version leaves the saved pages and the page alone', async () => {
  await start({ caches: savedPages() });

  await pass(11);

  assert.equal(browser.location.reloads, 0);
  assert.equal(await bodyIn(`${O}/event/wc-test`), 'old home');
  assert.ok(!browser.fetches.some((f) => f.url.includes('/event/wc-test')), 'no page was fetched');
});
