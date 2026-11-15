// node --experimental-test-module-mocks --test tests/js
//
// A hostile network against the real modules: a server that never answers,
// answers late, answers half a JSON document or a web page, says "busy" with
// a date, and a service worker asked to keep (or open) what it shouldn't.
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { flush, freshImport, installFakeBrowser, reply } from './helpers/fake-browser.mjs';
import { ORIGIN, loadServiceWorker, page, settle } from './helpers/load-sw.mjs';
import { isCacheable } from '../../resources/js/attendee/saved-copies.js';

const DIR = new URL('../../resources/js/attendee/', import.meta.url).href;

let browser;
afterEach(() => {
  browser?.restore();
  browser = null;
  mock.restoreAll();
  mock.timers.reset();
});

/** A fetch that never answers on its own, but gives up (AbortError) when its signal is aborted, as a browser's does. */
function hangingFetch(log) {
  return (url, options = {}) => {
    log.push(String(url));
    return new Promise((_, reject) => {
      options.signal?.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' })));
    });
  };
}

// ---------------------------------------------------------------- the "has anything changed?" check

test('a server that never answers is asked once, not again every 30 seconds on top of the stuck request', async () => {
  mock.timers.enable({ apis: ['setInterval', 'setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  mock.method(Math, 'random', () => 0.5);
  browser = installFakeBrowser();
  const asked = [];
  browser.responses.push(hangingFetch(asked));
  globalThis.fetch = (url, options) => browser.responses[0](url, options);
  const { initDataFreshness } = await freshImport(`${DIR}data-freshness.js`);
  initDataFreshness();

  // The first regular check is due after ~5 minutes; then 2 more minutes pass with it stuck.
  for (let i = 0; i < 10 + 4; i++) {
    mock.timers.tick(30 * 1000);
    await flush();
  }
  const during = asked.length;
  assert.equal(during, 1, 'one question in flight at a time');

  // It gave up after 20 s (at 5:20), counted a failure and backs off to ~10 minutes: nothing more by 14:00.
  for (let i = 0; i < 2 * 7; i++) {
    mock.timers.tick(30 * 1000);
    await flush();
  }
  assert.equal(asked.length, 1, 'backed off after the stuck check');
});

test('an answer that is not a real fingerprint (an object, an empty string) never reloads the page', async () => {
  for (const body of [{ version: {} }, { version: '' }, { version: null }, [], 'null']) {
    mock.timers.enable({ apis: ['setInterval', 'setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
    mock.method(Math, 'random', () => 0.5);
    browser = installFakeBrowser({ version: 'v1' });
    browser.responses.push(reply(body, { headers: { 'Content-Type': 'application/json' } }));
    const { initDataFreshness } = await freshImport(`${DIR}data-freshness.js`);
    initDataFreshness();
    for (let i = 0; i < 12; i++) {
      mock.timers.tick(30 * 1000);
      await flush();
    }
    assert.equal(browser.location.reloads, 0, JSON.stringify(body));
    browser.restore();
    mock.timers.reset();
    mock.restoreAll();
  }
  browser = null;
});

test('a numeric fingerprint equal to the page\'s own does not loop reloads', async () => {
  mock.timers.enable({ apis: ['setInterval', 'setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  mock.method(Math, 'random', () => 0.5);
  browser = installFakeBrowser({ version: '123' });
  browser.responses.push(reply({ version: 123 }, { headers: { 'Content-Type': 'application/json' } }));
  const { initDataFreshness } = await freshImport(`${DIR}data-freshness.js`);
  initDataFreshness();
  for (let i = 0; i < 12; i++) {
    mock.timers.tick(30 * 1000);
    await flush();
  }
  assert.equal(browser.location.reloads, 0);
});

// ---------------------------------------------------------------- the API wrapper

async function api() {
  globalThis.crypto ??= (await import('node:crypto')).webcrypto;
  browser = installFakeBrowser();

  return freshImport(`${DIR}api.js`);
}

test('a read the server never answers fails after 15 seconds like no connection (the screen uses its saved copy), instead of spinning for ever', async () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  const { apiGet } = await api();
  const asked = [];
  globalThis.fetch = hangingFetch(asked);

  let settled = null;
  apiGet('wc-test', '/roster').then(() => { settled = 'ok'; }, (error) => { settled = error; });
  await flush();
  mock.timers.tick(14 * 1000);
  await flush();
  assert.equal(settled, null, 'still waiting at 14 s');

  mock.timers.tick(1500);
  await flush();
  assert.equal(settled?.status, 0);
  assert.equal(settled?.transient, true);
});

test('an answer cut off half-way through its JSON fails like no connection, not with a raw SyntaxError', async () => {
  const { apiGet } = await api();
  globalThis.fetch = async () => new Response('{"data":[{"name":"Asha"', { status: 200, headers: { 'Content-Type': 'application/json' } });

  await assert.rejects(apiGet('wc-test', '/roster'), (error) => error.status === 0 && !(error instanceof SyntaxError));
});

test('a wifi sign-in page answering 200 where JSON was expected is treated as no connection', async () => {
  const { apiGet } = await api();
  globalThis.fetch = async () => new Response('<html>Log in to Venue WiFi</html>', { status: 200, headers: { 'Content-Type': 'text/html' } });

  await assert.rejects(apiGet('wc-test', '/roster'), (error) => error.status === 0);
});

test('"busy" with Retry-After as a date is waited out (capped), then tried again', async () => {
  mock.timers.enable({ apis: ['setTimeout', 'Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  mock.method(Math, 'random', () => 0);
  const { fetchWithRetry } = await api();
  const asked = [];
  globalThis.fetch = async (url) => {
    asked.push(url);
    return asked.length === 1
      ? new Response('', { status: 429, headers: { 'Retry-After': new Date(Date.now() + 4000).toUTCString() } })
      : new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
  };

  const pending = fetchWithRetry('/api/v1/events/wc/roster');
  await flush();
  mock.timers.tick(3000);
  await flush();
  assert.equal(asked.length, 1, 'not before the date');
  mock.timers.tick(1100);
  await flush();
  assert.equal((await pending).status, 200);
  assert.equal(asked.length, 2);
});

// ---------------------------------------------------------------- the service worker

test('the build manifest is not served from a stale copy: after a deploy the new one is read', async () => {
  let version = 'old';
  const sw = loadServiceWorker({ network: () => page(JSON.stringify({ v: version }), { headers: { 'content-type': 'application/json' } }) });

  await (await sw.request('/build/manifest.json')).waits;
  await settle();
  version = 'new';
  const after = await sw.request('/build/manifest.json');

  assert.equal(JSON.parse(await after.response.text()).v, 'new');
});

test('offline, the last build manifest is still there for the offline saver', async () => {
  let online = true;
  const sw = loadServiceWorker({ network: () => { if (!online) throw new TypeError('offline'); return page('{"v":1}', { headers: { 'content-type': 'application/json' } }); } });

  await Promise.all((await sw.request('/build/manifest.json')).waits);
  await settle();
  online = false;

  assert.equal(await (await sw.request('/build/manifest.json')).response.text(), '{"v":1}');
});

test('a notification whose address points at another site or a script opens CampBuddy\'s start page instead', async () => {
  for (const url of ['https://evil.example/login', 'javascript:alert(1)', '//evil.example/x', 'data:text/html,hi']) {
    const sw = loadServiceWorker();
    const waits = [];
    sw.handlers.notificationclick({ notification: { close() {}, data: { url } }, waitUntil: (p) => waits.push(p) });
    await Promise.all(waits);

    assert.deepEqual(sw.state.opened, [`${ORIGIN}/`], url);
  }
});

test('page code never saves a copy the server marked no-store (the same rule as the worker)', () => {
  const ok = { status: 200, type: 'basic', redirected: false, headers: new Headers() };
  assert.equal(isCacheable(ok), true);
  assert.equal(isCacheable({ ...ok, headers: new Headers({ 'Cache-Control': 'no-store, private' }) }), false);
  assert.equal(isCacheable({ ...ok, headers: new Headers({ 'Cache-Control': 'private, max-age=0, NO-STORE' }) }), false);
  assert.equal(isCacheable({ ...ok, status: 500 }), false);
  assert.equal(isCacheable({ ...ok, type: 'opaque' }), false);
  assert.equal(isCacheable(null), false);
});
