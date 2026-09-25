// node --test tests/js  — the real public/sw.js, run in a sandbox.
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { ORIGIN, fakeCacheStorage, loadServiceWorker, page, settle } from './helpers/load-sw.mjs';

const HOME = `${ORIGIN}/event/wc-test`;
const MY_DAY = `${ORIGIN}/event/wc-test/my-day`;
const CACHE = 'campbuddy-v3';

afterEach(() => mock.timers.reset());

const bodyOf = async (result) => (result.response ? result.response.text() : null);

// ---------------------------------------------------------------- what the worker must leave alone

test('it ignores what it must not touch: non-GET, other origins, /api, /admin, /up', async () => {
  const sw = loadServiceWorker({ network: () => page('x') });

  for (const [url, options] of [
    ['/event/wc-test', { method: 'POST' }],
    ['https://fonts.gstatic.com/font.woff2', {}],
    ['/api/v1/events/wc-test/roster', {}],
    ['/admin', {}],
    ['/admin/events', {}],
    ['/up', {}],
  ]) {
    assert.equal((await sw.request(url, options)).handled, false, `${options.method ?? 'GET'} ${url}`);
  }
  assert.equal(sw.fetches.length, 0);
});

// ---------------------------------------------------------------- hashed build files: cache-first

test('a build file is fetched once and then served from the saved copy', async () => {
  const sw = loadServiceWorker({ network: () => page('console.log(1)') });

  assert.equal(await bodyOf(await sw.request('/build/assets/app-abc123.js')), 'console.log(1)');
  await settle();
  const before = sw.fetches.length;

  assert.equal(await bodyOf(await sw.request('/build/assets/app-abc123.js')), 'console.log(1)');
  assert.equal(sw.fetches.length, before, 'no second network request');
});

// ---------------------------------------------------------------- pages: network-first with a saved fallback

test('a page comes from the network when it can, and is saved for later', async () => {
  const sw = loadServiceWorker({ network: () => page('fresh home') });

  assert.equal(await bodyOf(await sw.navigate('/event/wc-test')), 'fresh home');
  await Promise.all((await sw.navigate('/event/wc-test')).waits);
  await settle();

  assert.equal(await (await sw.caches.match(HOME)).text(), 'fresh home');
});

test('offline, a saved page opens; a page never saved gets the friendly offline page (503)', async () => {
  const caches = fakeCacheStorage({ [CACHE]: { [HOME]: page('saved home') } });
  const sw = loadServiceWorker({ caches });

  assert.equal(await bodyOf(await sw.navigate('/event/wc-test')), 'saved home');

  const never = await sw.navigate('/event/wc-test/quest');
  assert.equal(never.response.status, 503);
  assert.match(await never.response.text(), /You're offline/);

  const script = await sw.request('/media/logo.png'); // not a page: fails as it would have
  assert.ok(script.error, 'a missing image just fails');
});

test('a server error falls back to the saved copy; a 404 is a real answer and is not masked', async () => {
  const caches = fakeCacheStorage({ [CACHE]: { [HOME]: page('saved home') } });
  let answer = page('error page', { status: 503 });
  const sw = loadServiceWorker({ caches, network: () => answer });

  assert.equal(await bodyOf(await sw.navigate('/event/wc-test')), 'saved home');

  answer = page('not found', { status: 404 });
  const result = await sw.navigate('/event/wc-test');
  assert.equal(result.response.status, 404);
});

test('only a plain, complete 200 is ever saved (never a 404/500, a redirect or an opaque response)', async () => {
  for (const [name, response] of [
    ['404', page('nope', { status: 404 })],
    ['500', page('boom', { status: 500 })],
    ['redirect', page('moved', { redirected: true })],
    ['opaque', page('cross', { type: 'opaque' })],
  ]) {
    const sw = loadServiceWorker({ network: () => response });
    const result = await sw.navigate('/event/wc-test/quest');
    await Promise.all(result.waits);
    await settle();

    assert.equal(await sw.caches.match(`${ORIGIN}/event/wc-test/quest`), undefined, `${name} must not be saved`);
  }
});

test('with a saved copy, a stalled network is given 4 seconds and then the saved copy is shown', async () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  const caches = fakeCacheStorage({ [CACHE]: { [HOME]: page('saved home') } });
  const sw = loadServiceWorker({ caches, network: () => new Promise(() => {}) }); // never answers

  const pending = sw.navigate('/event/wc-test');
  await settle();
  mock.timers.tick(4000);

  assert.equal(await bodyOf(await pending), 'saved home');
});

test('a slow answer that arrives after the timeout still refreshes the saved copy', async () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  const caches = fakeCacheStorage({ [CACHE]: { [HOME]: page('old home') } });
  let arrive;
  const sw = loadServiceWorker({ caches, network: () => new Promise((resolve) => { arrive = () => resolve(page('new home')); }) });

  const pending = sw.navigate('/event/wc-test');
  await settle();
  mock.timers.tick(4000);
  const served = await pending;
  assert.equal(await bodyOf(served), 'old home');

  arrive();
  await Promise.all(served.waits);
  await settle();

  assert.equal(await (await sw.caches.match(HOME)).text(), 'new home');
});

// ---------------------------------------------------------------- lifecycle: nothing of the event is ever deleted

test('installing takes over at once, activating claims the open pages', async () => {
  const sw = loadServiceWorker();

  await sw.lifecycle('install');
  await sw.lifecycle('activate');

  assert.equal(sw.state.skipped, true);
  assert.equal(sw.state.claimed, true);
});

test('activating deletes no cache and no saved page of the event', async () => {
  const caches = fakeCacheStorage({
    [CACHE]: { [HOME]: page('home'), [MY_DAY]: page('my day') },
    'campbuddy-v2': { [`${ORIGIN}/event/wc-old`]: page('an older worker saved this') },
    'some-other-cache': { [`${ORIGIN}/x`]: page('x') },
  });
  const sw = loadServiceWorker({ caches });

  await sw.lifecycle('activate');

  assert.deepEqual(caches.log.deleted, [], 'no cache is dropped');
  assert.deepEqual([...caches.stores.keys()].sort(), [CACHE, 'campbuddy-v2', 'some-other-cache'].sort());
  assert.equal((await caches.match(HOME)).status, 200);
  assert.equal((await caches.match(`${ORIGIN}/event/wc-old`)).status, 200);
});

test('the only thing activating removes is a signed-in /admin page an older worker may have saved', async () => {
  const caches = fakeCacheStorage({
    [CACHE]: { [HOME]: page('home'), [`${ORIGIN}/admin`]: page('admin'), [`${ORIGIN}/admin/events/1/edit`]: page('admin edit') },
    'campbuddy-v2': { [`${ORIGIN}/admin/dashboard`]: page('old admin') },
  });
  const sw = loadServiceWorker({ caches });

  await sw.lifecycle('activate');

  assert.deepEqual(caches.log.deletedEntries.sort(), [
    `${CACHE}:${ORIGIN}/admin`,
    `${CACHE}:${ORIGIN}/admin/events/1/edit`,
    `campbuddy-v2:${ORIGIN}/admin/dashboard`,
  ].sort());
  assert.equal((await caches.match(HOME)).status, 200);
});

// ---------------------------------------------------------------- push (unchanged)

test('a push shows a notification; a payload that is not JSON is ignored', async () => {
  const sw = loadServiceWorker();

  const waits = [];
  sw.handlers.push({ data: { json: () => ({ title: 'Keynote', body: 'Starting soon.', url: '/event/wc-test/my-day' }) }, waitUntil: (p) => waits.push(p) });
  await Promise.all(waits);
  assert.equal(sw.state.notifications[0].title, 'Keynote');
  assert.equal(sw.state.notifications[0].options.data.url, '/event/wc-test/my-day');

  sw.handlers.push({ data: { json: () => { throw new Error('not json'); } }, waitUntil: () => assert.fail('nothing to show') });
  sw.handlers.push({ data: null, waitUntil: () => assert.fail('no data') });
});

test('tapping a notification opens the target', async () => {
  const sw = loadServiceWorker();
  const waits = [];

  sw.handlers.notificationclick({ notification: { close() {}, data: { url: '/event/wc-test/my-day' } }, waitUntil: (p) => waits.push(p) });
  await Promise.all(waits);

  assert.deepEqual(sw.state.opened, [`${ORIGIN}/event/wc-test/my-day`]);
});

// ---------------------------------------------------------------- the switch: stale-while-revalidate for page opens (off unless sw-flags.json says so)

const FLAGS = `${ORIGIN}/sw-flags.json`;
const FIVE_MIN = 5 * 60 * 1000;
const httpDate = (agoMs) => new Date(Date.now() - agoMs).toUTCString();
const json = (value) => page(JSON.stringify(value), { headers: { 'content-type': 'application/json' } });

/**
 * A world where the flags file says `flags` and each page is answered by `pages[url]` (a function or Response).
 * `known: true` = this worker has run before and already saved what the file says (an object = what it saved);
 * `known: false` = a brand-new install that has never read it.
 */
function world({ flags = { swrPages: true }, known = true, pages = {}, cached = {}, caches } = {}) {
  const savedValue = typeof known === 'object' ? known : known && flags && typeof flags === 'object' ? flags : null;
  const initial = {
    ...(Object.keys(cached).length ? { [CACHE]: cached } : {}),
    ...(savedValue ? { 'campbuddy-flags': { '/sw-flags.json': json(savedValue) } } : {}),
  };
  caches ??= fakeCacheStorage(initial);

  const calls = [];
  const sw = loadServiceWorker({
    caches,
    network: (url) => {
      calls.push(url);
      if (url === FLAGS) {
        if (flags === null) throw new TypeError('offline');
        return typeof flags === 'function' ? flags() : json(flags);
      }
      const answer = pages[url];
      if (!answer) throw new TypeError('offline');
      return typeof answer === 'function' ? answer() : answer;
    },
  });

  return { sw, calls, caches, pageCalls: () => calls.filter((u) => u !== FLAGS) };
}

test('the switch file is never served by the worker itself', async () => {
  const { sw } = world();

  assert.equal((await sw.request('/sw-flags.json')).handled, false);
});

test('switched off (the default): every page open goes to the network first, as always', async () => {
  const { sw, pageCalls } = world({
    flags: { swrPages: false },
    cached: { [HOME]: page('saved', { headers: { date: httpDate(1000) } }) },
    pages: { [HOME]: page('network home') },
  });

  assert.equal(await bodyOf(await sw.navigate('/event/wc-test')), 'network home');
  assert.deepEqual(pageCalls(), [HOME]);
});

test('no switch file at all (offline, or never deployed) behaves as switched off', async () => {
  const { sw, pageCalls } = world({ flags: null, cached: { [HOME]: page('saved', { headers: { date: httpDate(1000) } }) }, pages: { [HOME]: page('network home') } });

  assert.equal(await bodyOf(await sw.navigate('/event/wc-test')), 'network home');
  assert.equal(pageCalls().length, 1);
});

test('switched on: a copy saved a minute ago opens instantly with no request to the server', async () => {
  const { sw, pageCalls } = world({ cached: { [HOME]: page('saved home', { headers: { date: httpDate(60 * 1000) } }) }, pages: { [HOME]: page('network home') } });

  const result = await sw.navigate('/event/wc-test');
  await Promise.all(result.waits);

  assert.equal(await bodyOf(result), 'saved home');
  assert.deepEqual(pageCalls(), [], 'the origin was not asked at all');
});

test('switched on: an older copy opens instantly and is refreshed in the background', async () => {
  const { sw, pageCalls, caches } = world({
    cached: { [HOME]: page('saved home', { headers: { date: httpDate(FIVE_MIN + 60 * 1000) } }) },
    pages: { [HOME]: page('newer home', { headers: { date: httpDate(0) } }) },
  });

  const result = await sw.navigate('/event/wc-test');
  assert.equal(await bodyOf(result), 'saved home', 'no waiting for the network');
  await Promise.all(result.waits);
  await settle();

  assert.deepEqual(pageCalls(), [HOME]);
  assert.equal(await (await caches.match(HOME)).text(), 'newer home', 'the next open shows the new one');
});

test('switched on: a page with no saved copy is fetched and saved, as before', async () => {
  const { sw, caches } = world({ pages: { [HOME]: page('network home') } });

  const result = await sw.navigate('/event/wc-test');
  await Promise.all(result.waits);
  await settle();

  assert.equal(await bodyOf(result), 'network home');
  assert.equal(await (await caches.match(HOME)).text(), 'network home');
});

test('switched on: a background refresh that fails or is not a plain 200 leaves the saved copy alone', async () => {
  for (const answer of [() => { throw new TypeError('offline'); }, () => page('boom', { status: 500 }), () => page('gone', { status: 404 }), () => page('moved', { redirected: true })]) {
    const { sw, caches } = world({
      cached: { [HOME]: page('saved home', { headers: { date: httpDate(FIVE_MIN * 3) } }) },
      pages: { [HOME]: answer },
    });

    const result = await sw.navigate('/event/wc-test');
    await Promise.all(result.waits);
    await settle();

    assert.equal(await bodyOf(result), 'saved home');
    assert.equal(await (await caches.match(HOME)).text(), 'saved home', 'kept');
  }
});

test('switched on: only real page opens use it — a fetch by the app itself still goes to the network, so a refresh gets a fresh copy', async () => {
  const { sw, pageCalls } = world({
    cached: { [HOME]: page('saved home', { headers: { date: httpDate(1000) } }) },
    pages: { [HOME]: page('network home') },
  });

  const result = await sw.request('/event/wc-test', { mode: 'cors' }); // e.g. saved-copies.js refreshing a page

  assert.equal(await bodyOf(result), 'network home');
  assert.deepEqual(pageCalls(), [HOME]);
});

test('a brand-new worker uses network-first for its very first open, and reads the switch for the next', async () => {
  const { sw, pageCalls } = world({ known: false, pages: { [HOME]: page('network home', { headers: { date: httpDate(0) } }) }, cached: { [HOME]: page('saved home', { headers: { date: httpDate(1000) } }) } });

  const first = await sw.navigate('/event/wc-test');
  await Promise.all(first.waits);
  await settle();
  assert.equal(await bodyOf(first), 'network home', 'the switch was not known yet: the proven path');
  assert.equal(pageCalls().length, 1);

  const second = await sw.navigate('/event/wc-test');
  await Promise.all(second.waits);
  assert.equal(await bodyOf(second), 'network home', 'the saved copy is now the fresh one just stored');
  assert.equal(pageCalls().length, 1, 'no second request: switched on, and the copy is fresh');
});

test('the switch is remembered across a worker restart, offline', async () => {
  const caches = fakeCacheStorage({ [CACHE]: { [HOME]: page('saved home', { headers: { date: httpDate(1000) } }) } });
  const first = world({ caches, known: false, pages: { [HOME]: page('network home', { headers: { date: httpDate(0) } }) } });
  await first.sw.navigate('/event/wc-test'); // reads the switch (on) and saves it
  await settle();

  // The worker is stopped and started again with no connection at all.
  const second = world({ caches, known: false, flags: null, pages: {} });
  const result = await second.sw.navigate('/event/wc-test');
  await Promise.all(result.waits);

  assert.equal(await bodyOf(result), 'network home', 'the copy the first run saved');
  assert.deepEqual(second.pageCalls(), [], 'still switched on: no request for a fresh copy');
});

test('turning it off takes effect on the next check, and the switch is not re-read on every page open', async () => {
  let current = { swrPages: true };
  const { sw, calls, pageCalls } = world({
    flags: () => json(current),
    known: { swrPages: true },
    cached: { [HOME]: page('saved home', { headers: { date: httpDate(1000) } }) },
    pages: { [HOME]: () => page('network home') },
  });

  await sw.navigate('/event/wc-test');
  await settle();
  await sw.navigate('/event/wc-test');
  await settle();
  assert.equal(calls.filter((u) => u === FLAGS).length, 1, 'read once, then remembered for a while');
  assert.deepEqual(pageCalls(), []);

  current = { swrPages: false };
  const realNow = Date.now;
  Date.now = () => realNow() + 11 * 60 * 1000; // ten minutes and a bit later
  try {
    await sw.navigate('/event/wc-test'); // re-reads the switch in the background
    await settle();
    const result = await sw.navigate('/event/wc-test');
    assert.equal(await bodyOf(result), 'network home', 'now switched off: back to network-first');
  } finally {
    Date.now = realNow;
  }
});

test('a missing switch file means off, and is remembered', async () => {
  const caches = fakeCacheStorage({ [CACHE]: { [HOME]: page('saved home', { headers: { date: httpDate(1000) } }) }, 'campbuddy-flags': { '/sw-flags.json': json({ swrPages: true }) } });
  const { sw, pageCalls } = world({ caches, flags: () => page('missing', { status: 404 }), pages: { [HOME]: page('network home') } });

  await sw.navigate('/event/wc-test'); // switched on as last saved; reads the file in the background: gone
  await settle();
  const after = await sw.navigate('/event/wc-test');

  assert.equal(await bodyOf(after), 'network home', 'now off: network-first');
  assert.equal(pageCalls().length, 1);
  assert.deepEqual(await (await caches.match('/sw-flags.json')).json(), {}, 'the "off" is saved for restarts');
});

test('a switch file that could not be read is looked at again within a minute, not ten', async () => {
  const realNow = Date.now;
  let clock = realNow();
  Date.now = () => clock;
  try {
    let answer = () => { throw new TypeError('offline'); };
    const { sw, calls } = world({ known: false, flags: () => answer(), pages: { [HOME]: page('network home') } });
    const flagReads = () => calls.filter((u) => u === FLAGS).length;

    await sw.navigate('/event/wc-test');
    await settle();
    assert.equal(flagReads(), 1);

    clock += 30 * 1000;
    await sw.navigate('/event/wc-test');
    await settle();
    assert.equal(flagReads(), 1, 'not yet: 30 s later');

    clock += 40 * 1000; // 70 s after the failure
    answer = () => json({ swrPages: true });
    await sw.navigate('/event/wc-test');
    await settle();
    assert.equal(flagReads(), 2, 'looked again after a minute');
  } finally {
    Date.now = realNow;
  }
});
