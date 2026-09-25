// node --test tests/js
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { fakeCaches, flush, installFakeBrowser, reply } from './helpers/fake-browser.mjs';
import { buildAssets, mediaAddresses, pageAddresses, warmOfflineCache } from '../../resources/js/attendee/offline-warmup.js';

const O = 'https://campbuddy.test';
const SLUG = 'wc-test';
const PAGE_HTML = '<html><img src="/media/logo-wordmark.png"><link href="/media/icons/favicon-32.png"><img data-fallback="/media/illustrations/avatar.svg"></html>';

const MANIFEST = {
  'resources/js/attendee/app.js': { file: 'assets/app-A1.js', css: ['assets/main-M1.css'], imports: ['_shared.js'], dynamicImports: ['resources/js/attendee/home.js', 'resources/js/attendee/my-day.js'] },
  'resources/scss/main.scss': { file: 'assets/main-M1.css' },
  '_shared.js': { file: 'assets/shared-S1.js' },
  'resources/js/attendee/home.js': { file: 'assets/home-H1.js', imports: ['_shared.js'] },
  'resources/js/attendee/my-day.js': { file: 'assets/my-day-D1.js' },
  'resources/js/admin.js': { file: 'assets/admin-Z9.js' },
};

let browser;
let caches;

afterEach(() => {
  browser?.restore();
  mock.restoreAll();
});

/** A site that answers every address the warm-up may ask for. */
function siteAnswers({ failing = [], dynamic = {} } = {}) {
  const asked = [];
  const handler = async (url) => {
    const { pathname } = new URL(url);
    asked.push(pathname);

    if (failing.includes(pathname)) throw new TypeError('connection dropped');
    if (pathname in dynamic) return dynamic[pathname];
    if (pathname === '/build/manifest.json') return reply(MANIFEST);
    if (pathname.endsWith('.css')) return reply('body{background:url(/media/pattern-people.svg)}', { headers: { 'content-type': 'text/css' } });
    if (pathname.startsWith('/build/')) return reply(`js ${pathname}`);
    if (pathname.startsWith('/media/')) return reply(`image ${pathname}`);

    return reply(PAGE_HTML, { headers: { 'content-type': 'text/html; charset=utf-8' } });
  };

  return { handler, asked };
}

function setup({ version = 'v1', cachesInit = {}, anchors = [`/event/${SLUG}/explore`, `/event/${SLUG}/roster-removal`, 'https://elsewhere.test/event/wc-test/x', '/event/other-camp'], nav } = {}) {
  caches = fakeCaches(cachesInit);
  browser = installFakeBrowser({ slug: SLUG, version, caches, anchors, html: PAGE_HTML });
  browser.location.pathname = `/event/${SLUG}/my-day`; // the page on screen (already saved by the worker)
  const persisted = [];
  const navigatorLike = nav ?? { onLine: true, connection: undefined, storage: { persist: async () => (persisted.push(1), true) } };

  return { persisted, navigatorLike, run: (site, extra = {}) => warmOfflineCache({ doc: browser.doc, nav: navigatorLike, storage: browser.win.localStorage, cachesImpl: caches, fetchImpl: site.handler, origin: O, ...extra }) };
}

const has = async (path) => Boolean(await caches.match(`${O}${path}`));

// ------------------------------------------------------------------ what it collects

test('the event pages are the known screens plus every event link on the page (not forms, other events or other sites)', () => {
  const doc = { querySelectorAll: () => [`/event/${SLUG}/explore`, `/event/${SLUG}/some-new-screen?x=1#top`, `/event/${SLUG}/roster-removal`, `/event/${SLUG}/roster-removal/search`, '/event/other-camp/my-day', 'https://elsewhere.test/event/wc-test/y', 'not a url', '#'].map((href) => ({ getAttribute: () => href })) };

  const paths = pageAddresses(SLUG, doc, O);

  for (const known of ['', '/my-day', '/quest', '/contribute', '/explore', '/camp-card', '/guide', '/manifest.json']) assert.ok(paths.includes(`/event/${SLUG}${known}`), known);
  assert.ok(paths.includes('/') && paths.includes('/guide'));
  assert.ok(paths.includes(`/event/${SLUG}/some-new-screen`), 'a screen added later is found from the links, query and hash dropped');
  assert.ok(!paths.some((p) => p.includes('roster-removal')));
  assert.ok(!paths.some((p) => p.includes('other-camp')));
  assert.ok(!paths.some((p) => p.includes('elsewhere')));
});

test('the build files are every script and style the attendee pages can ask for, from the manifest', () => {
  const files = buildAssets(MANIFEST);

  assert.deepEqual(files.sort(), ['/build/assets/app-A1.js', '/build/assets/main-M1.css', '/build/assets/shared-S1.js', '/build/assets/home-H1.js', '/build/assets/my-day-D1.js'].sort());
  assert.ok(!files.some((f) => f.includes('admin')), 'the admin panel bundle is not the attendee app');
  assert.deepEqual(buildAssets({}), []);
});

test('images are found in page markup and in CSS url(...)', () => {
  assert.deepEqual(
    mediaAddresses('<img src="/media/a.png"><a href="/storage/branding/1/logo.png?v=9"></a><img src="https://cdn.example/x.png"><div style="background:url(\'/media/pattern.svg\')"></div><img data-fallback="/media/illustrations/avatar.svg">').sort(),
    ['/media/a.png', '/media/illustrations/avatar.svg', '/media/pattern.svg', '/storage/branding/1/logo.png?v=9']
  );
});

// ------------------------------------------------------------------ warming

test('the rest of the event is saved: pages, scripts and styles, and the images they need', async () => {
  const world = setup();
  const site = siteAnswers();

  const result = await world.run(site);

  assert.equal(result.status, 'warmed');
  for (const path of ['', '/explore', '/quest', '/contribute', '/camp-card', '/guide', '/manifest.json']) assert.ok(await has(`/event/${SLUG}${path}`), path);
  assert.ok(await has('/') && await has('/guide'));
  assert.ok(await has(`/event/${SLUG}/my-day`), 'the page on screen is saved too when the worker has not (a very first visit)');
  assert.ok(!(await has(`/event/${SLUG}/roster-removal`)));
  for (const file of ['app-A1.js', 'main-M1.css', 'shared-S1.js', 'home-H1.js', 'my-day-D1.js']) assert.ok(await has(`/build/assets/${file}`), file);
  for (const image of ['/media/logo-wordmark.png', '/media/icons/favicon-32.png', '/media/illustrations/avatar.svg', '/media/pattern-people.svg']) assert.ok(await has(image), image);
  assert.deepEqual(caches.deleted, []);
});

test('the page on screen is not fetched again when the worker already saved it', async () => {
  const world = setup({ cachesInit: { 'campbuddy-v3': { [`${O}/event/${SLUG}/my-day`]: reply('saved by the worker') } } });
  const site = siteAnswers();

  await world.run(site);

  assert.ok(!site.asked.includes(`/event/${SLUG}/my-day`), 'not asked for again');
  assert.equal((await caches.match(`${O}/event/${SLUG}/my-day`)).body, 'saved by the worker');
});

test('everything goes into the service worker cache, under absolute addresses the worker will look up', async () => {
  const world = setup();
  await world.run(siteAnswers());

  assert.deepEqual([...caches.stores.keys()], ['campbuddy-v3']);
  assert.ok([...caches.stores.get('campbuddy-v3').keys()].every((key) => key.startsWith(`${O}/`)));
});

test('a second visit soon after does nothing; a changed schedule saves the pages again, but not the build files', async () => {
  const world = setup();
  const first = siteAnswers();
  await world.run(first);

  const second = siteAnswers();
  assert.deepEqual(await world.run(second), { status: 'skipped', reason: 'recent' });
  assert.equal(second.asked.length, 0);

  browser.meta.content = 'v2'; // the event's data changed
  const third = siteAnswers({ dynamic: { [`/event/${SLUG}/explore`]: reply('<html>newer explore</html>', { headers: { 'content-type': 'text/html' } }) } });
  assert.equal((await world.run(third)).status, 'warmed');
  assert.equal((await caches.match(`${O}/event/${SLUG}/explore`)).body, '<html>newer explore</html>', 'the saved page was replaced');
  assert.ok(!third.asked.some((p) => p.startsWith('/build/assets/')), 'hashed files are already kept');
});

test('what was saved before is never removed', async () => {
  const world = setup({ cachesInit: { 'campbuddy-v3': { [`${O}/event/${SLUG}/my-day`]: reply('saved my day'), [`${O}/event/old-camp`]: reply('an event visited last month') }, 'campbuddy-v2': { [`${O}/x`]: reply('older worker') } } });

  await world.run(siteAnswers());

  assert.equal((await caches.match(`${O}/event/${SLUG}/my-day`)).body, 'saved my day');
  assert.equal((await caches.match(`${O}/event/old-camp`)).body, 'an event visited last month');
  assert.equal((await caches.match(`${O}/x`)).body, 'older worker');
  assert.deepEqual(caches.deleted, []);
});

// ------------------------------------------------------------------ being polite

test('it leaves the phone alone when offline, on Data Saver, on a 2G-class connection, off an event page, or without cache storage', async () => {
  for (const [name, nav] of [
    ['offline', { onLine: false }],
    ['data saver', { onLine: true, connection: { saveData: true } }],
    ['2g', { onLine: true, connection: { effectiveType: '2g' } }],
    ['slow-2g', { onLine: true, connection: { effectiveType: 'slow-2g' } }],
  ]) {
    const world = setup({ nav });
    const site = siteAnswers();

    const result = await world.run(site);

    assert.equal(result.status, 'skipped', name);
    assert.equal(site.asked.length, 0, `${name}: no request at all`);
    browser.restore();
  }

  const world = setup();
  browser.app.dataset.eventSlug = '';
  assert.deepEqual(await world.run(siteAnswers()), { status: 'skipped', reason: 'not-an-event-page' });
  browser.app.dataset.eventSlug = SLUG;
  assert.deepEqual(await world.run(siteAnswers(), { cachesImpl: null }), { status: 'skipped', reason: 'no-cache-storage' });
});

test('at most 3 requests at a time', async () => {
  const world = setup();
  let inFlight = 0;
  let peak = 0;
  const site = siteAnswers();

  await world.run({ handler: async (url) => { peak = Math.max(peak, ++inFlight); await new Promise((r) => setTimeout(r, 3)); inFlight--; return site.handler(url); } });

  assert.ok(peak <= 3 && peak >= 2, `peak ${peak}`);
});

test('the browser is asked once not to evict what is saved', async () => {
  const world = setup();

  await world.run(siteAnswers());
  await flush();
  browser.meta.content = 'v2';
  await world.run(siteAnswers());
  await flush();

  assert.equal(world.persisted.length, 1);
  assert.equal(browser.win.localStorage.getItem('campbuddy:persist-asked'), 'true');
});

// ------------------------------------------------------------------ trouble

test('a page that fails leaves the rest saved, is reported as partial, and is retried sooner than a full success', async () => {
  mock.timers.enable({ apis: ['Date'], now: Date.parse('2026-10-10T08:00:00Z') });
  const world = setup();

  const result = await world.run(siteAnswers({ failing: [`/event/${SLUG}/quest`] }));

  assert.equal(result.status, 'partial');
  assert.equal(result.failed, 1);
  assert.ok(await has(`/event/${SLUG}/explore`), 'the others were still saved');
  assert.ok(!(await has(`/event/${SLUG}/quest`)));

  mock.timers.tick(10 * 60 * 1000);
  assert.equal((await world.run(siteAnswers())).reason, 'recent', 'not straight away');

  mock.timers.tick(25 * 60 * 1000); // 35 minutes: past the retry gap
  assert.equal((await world.run(siteAnswers())).status, 'warmed', 'retried, and this time it worked');
  assert.ok(await has(`/event/${SLUG}/quest`));
});

test('an error page, a redirect or a missing file is never saved as if it were the page', async () => {
  const world = setup();
  const site = siteAnswers({
    dynamic: {
      [`/event/${SLUG}/quest`]: reply('boom', { status: 500 }),
      [`/event/${SLUG}/guide`]: { ...reply('moved'), redirected: true },
      '/build/assets/my-day-D1.js': reply('nope', { status: 404 }),
    },
  });

  const result = await world.run(site);

  assert.equal(result.status, 'partial');
  assert.ok(!(await has(`/event/${SLUG}/quest`)) && !(await has(`/event/${SLUG}/guide`)) && !(await has('/build/assets/my-day-D1.js')));
  assert.ok(await has(`/event/${SLUG}/explore`));
});

test('with no manifest (development, or a hiccup) the pages are still saved', async () => {
  const world = setup();
  const site = siteAnswers({ failing: ['/build/manifest.json'] });

  const result = await world.run(site);

  assert.ok(await has(`/event/${SLUG}/explore`));
  assert.equal(result.assets, 0);
});

test('nothing it does can throw into the page', async () => {
  const world = setup();

  assert.deepEqual(await world.run({ handler: async () => { throw new Error('everything is on fire'); } }).then((r) => r.status), 'partial');
  assert.deepEqual(await warmOfflineCache({ doc: null }), { status: 'skipped', reason: 'not-an-event-page' });
  assert.equal((await warmOfflineCache({ doc: { getElementById: () => { throw new Error('boom'); } } })).status, 'skipped');
});
