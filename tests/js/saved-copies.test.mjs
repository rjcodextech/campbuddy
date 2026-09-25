// node --test tests/js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { fakeCaches, reply } from './helpers/fake-browser.mjs';
import { PAGES_CACHE, isCacheable, listCopies, refreshCopies, replaceCopy } from '../../resources/js/attendee/saved-copies.js';

const O = 'https://campbuddy.test';
const HOME = `${O}/event/wc-test`;
const MY_DAY = `${O}/event/wc-test/my-day`;
const OTHER = `${O}/event/other-camp`;
const JS = `${O}/build/assets/app-abc.js`;

const bodyIn = async (caches, url) => (await caches.match(url))?.body;
const everything = () => true;

test('only a plain, complete, non-redirected 200 counts as a copy worth keeping', () => {
  assert.equal(isCacheable(reply('x')), true);
  assert.equal(isCacheable(reply('x', { status: 404 })), false);
  assert.equal(isCacheable(reply('x', { status: 500 })), false);
  assert.equal(isCacheable({ ...reply('x'), redirected: true }), false);
  assert.equal(isCacheable({ ...reply('x'), type: 'opaque' }), false);
  assert.equal(isCacheable(null), false);
});

test('a fresh copy takes the old one place', async () => {
  const caches = fakeCaches({ [PAGES_CACHE]: { [HOME]: reply('old') } });
  const calls = [];

  const done = await replaceCopy(await caches.open(PAGES_CACHE), HOME, { fetchImpl: async (url, options) => (calls.push([url, options.cache]), reply('new')) });

  assert.equal(done, true);
  assert.equal(await bodyIn(caches, HOME), 'new');
  assert.deepEqual(calls, [[HOME, 'reload']], 'asked the server, not the browser HTTP cache');
});

test('the old copy stays when the fresh one cannot be had: network error, 404, 500, redirect', async () => {
  for (const attempt of [
    async () => { throw new TypeError('offline'); },
    async () => reply('gone', { status: 404 }),
    async () => reply('boom', { status: 500 }),
    async () => ({ ...reply('moved'), redirected: true }),
  ]) {
    const caches = fakeCaches({ [PAGES_CACHE]: { [HOME]: reply('old') } });

    assert.equal(await replaceCopy(await caches.open(PAGES_CACHE), HOME, { fetchImpl: attempt }), false);
    assert.equal(await bodyIn(caches, HOME), 'old');
    assert.deepEqual(caches.deleted, []);
  }
});

test('refreshing replaces the matching copies in place and touches nothing else', async () => {
  const caches = fakeCaches({ [PAGES_CACHE]: { [HOME]: reply('old home'), [MY_DAY]: reply('old my day'), [OTHER]: reply('other camp'), [JS]: reply('js') } });

  const result = await refreshCopies((url) => url.pathname.startsWith('/event/wc-test'), { cachesImpl: caches, fetchImpl: async (url) => reply(`new ${new URL(url).pathname}`) });

  assert.deepEqual(result, { found: 2, replaced: 2 });
  assert.equal(await bodyIn(caches, HOME), 'new /event/wc-test');
  assert.equal(await bodyIn(caches, MY_DAY), 'new /event/wc-test/my-day');
  assert.equal(await bodyIn(caches, OTHER), 'other camp', 'another event is left alone');
  assert.equal(await bodyIn(caches, JS), 'js');
  assert.deepEqual(caches.deleted, [], 'nothing is ever deleted');
});

test('a flaky connection never costs a saved page: what fails keeps its old copy, what works is replaced', async () => {
  const caches = fakeCaches({ [PAGES_CACHE]: { [HOME]: reply('old home'), [MY_DAY]: reply('old my day') } });

  const result = await refreshCopies(everything, {
    cachesImpl: caches,
    fetchImpl: async (url) => {
      if (url === MY_DAY) throw new TypeError('connection dropped');
      return reply('new home');
    },
  });

  assert.deepEqual(result, { found: 2, replaced: 1 });
  assert.equal(await bodyIn(caches, HOME), 'new home');
  assert.equal(await bodyIn(caches, MY_DAY), 'old my day');
  assert.deepEqual(caches.deleted, []);
});

test('a copy saved by an older worker is replaced in the cache it lives in, not moved or dropped', async () => {
  const caches = fakeCaches({ 'campbuddy-v2': { [HOME]: reply('legacy') } });

  await refreshCopies(everything, { cachesImpl: caches, fetchImpl: async () => reply('new') });

  assert.equal((await caches.stores.get('campbuddy-v2').get(HOME)).body, 'new');
  assert.deepEqual(caches.deleted, []);
});

test('no more than 3 requests are in flight at once', async () => {
  const entries = Object.fromEntries(Array.from({ length: 12 }, (_, i) => [`${O}/event/wc-test/p${i}`, reply('old')]));
  const caches = fakeCaches({ [PAGES_CACHE]: entries });
  let inFlight = 0;
  let peak = 0;

  await refreshCopies(everything, {
    cachesImpl: caches,
    fetchImpl: async () => {
      peak = Math.max(peak, ++inFlight);
      await new Promise((resolve) => setTimeout(resolve, 5));
      inFlight--;
      return reply('new');
    },
  });

  assert.equal(peak, 3);
});

test('a network that never answers cannot hold the app up: it moves on after the time limit, deleting nothing', async () => {
  const caches = fakeCaches({ [PAGES_CACHE]: { [HOME]: reply('old') } });
  const started = Date.now();

  const result = await refreshCopies(everything, { cachesImpl: caches, timeoutMs: 40, fetchImpl: () => new Promise(() => {}) });

  assert.ok(Date.now() - started < 1000);
  assert.deepEqual(result, { found: 1, replaced: 0 });
  assert.equal(await bodyIn(caches, HOME), 'old');
  assert.deepEqual(caches.deleted, []);
});

test('unavailable storage is not an error', async () => {
  const broken = { keys: async () => { throw new Error('blocked'); } };

  assert.deepEqual(await refreshCopies(everything, { cachesImpl: broken }), { found: 0, replaced: 0 });
  assert.deepEqual(await listCopies(everything, { cachesImpl: undefined }), []);
});
