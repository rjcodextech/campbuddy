// node tests/browser/offline.e2e.mjs   (npm run test:browser)
//
// The offline promise, checked in a REAL browser against the REAL service worker
// and app modules (a fake site stands in for the server):
//
//   A. Opening one event page online saves the rest of the event (pages, scripts,
//      styles, images) for offline use.
//   B. With the connection dropped, every screen still opens — including ones
//      never visited — with its images; a screen that was never saved gets the
//      friendly offline page.
//   C. When the event's data changes, the saved pages are REPLACED in place and
//      the page reloads with the new data; not one saved copy is ever deleted.
//   D. If the server is failing while that happens, every saved page is still there.
//   E. Offline again afterwards: still browsable.
//   F. The (off-by-default) switch: with it on, page opens inside the freshness
//      window don't reach the server at all.
//
// Takes about two minutes (the app waits 45 s between freshness checks).

import assert from 'node:assert/strict';
import { launchChrome, sleep } from './helpers/chrome.mjs';
import { SLUG, startSite } from './helpers/site.mjs';

const results = [];
const step = async (name, fn) => {
  const started = Date.now();
  try {
    await fn();
    results.push({ name, ok: true });
    console.log(`  PASS  ${name}  (${((Date.now() - started) / 1000).toFixed(1)}s)`);
  } catch (error) {
    results.push({ name, ok: false, error });
    console.log(`  FAIL  ${name}\n        ${String(error.message).split('\n').join('\n        ')}`);
  }
};

const SCREENS = ['', '/my-day', '/quest', '/contribute', '/explore', '/camp-card', '/guide'];
const CACHE_KEYS = `(async () => { const out = []; for (const name of await caches.keys()) { const c = await caches.open(name); for (const r of await c.keys()) out.push(new URL(r.url).pathname); } return out; })()`;
const bodyOfCopy = (path) => `(async () => { const r = await caches.match('${path}'); return r ? await r.text() : null; })()`;

const site = await startSite();
const chrome = await launchChrome();
const page = (screen = '') => `${site.base}/event/${SLUG}${screen}`;
const marker = () => chrome.evaluate('document.getElementById("marker")?.textContent ?? document.body?.innerText?.slice(0, 80)');
console.log(`Fake site on ${site.base}\n`);

/** Watches the saved copies while `during` runs; returns every copy that was ever seen but later went missing. */
async function watchForDeletions(during) {
  const everSeen = new Set();
  let lost = new Set();
  let running = true;
  const watcher = (async () => {
    while (running) {
      const keys = await chrome.evaluate(CACHE_KEYS).catch(() => undefined);
      if (Array.isArray(keys)) {
        keys.forEach((k) => everSeen.add(k));
        for (const seen of everSeen) if (!keys.includes(seen)) lost.add(seen);
      }
      await sleep(120);
    }
  })();
  try {
    await during();
  } finally {
    running = false;
    await watcher;
  }

  return { everSeen, lost: [...lost] };
}

try {
  // ------------------------------------------------------------------- A
  await step('A. opening one page online saves the rest of the event for offline use', async () => {
    await chrome.goto(page('/my-day'));
    await chrome.waitFor('navigator.serviceWorker.ready.then(() => true)');
    const warm = await chrome.waitFor('window.__warm', { timeout: 40000 });

    assert.equal(warm.status, 'warmed', JSON.stringify(warm));
    const keys = await chrome.evaluate(CACHE_KEYS);
    for (const screen of SCREENS.filter((s) => s !== '/my-day')) assert.ok(keys.includes(`/event/${SLUG}${screen}`), `saved: ${screen || 'home'}`);
    for (const file of ['/build/assets/boot.js', '/build/assets/data-freshness.js', '/build/assets/offline-warmup.js', '/build/assets/saved-copies.js', '/media/logo.png']) assert.ok(keys.includes(file), `saved: ${file}`);
    assert.ok(!keys.some((k) => k.includes('roster-removal')), 'forms are not saved');
  });

  // ------------------------------------------------------------------- B
  await step('B. with the connection gone, every screen still opens — never-visited ones too — with its images', async () => {
    await site.control({ down: '1' });

    for (const screen of SCREENS) {
      await chrome.goto(page(screen));
      assert.equal(await marker(), `PAGE ${screen.replace('/', '') || 'home'} v1`, `screen ${screen || 'home'} offline`);
    }

    await chrome.goto(page('/quest'));
    assert.equal(await chrome.evaluate('(() => { const i = document.getElementById("logo"); return i.complete && i.naturalWidth > 0; })()'), true, 'the image shows from the saved copy');

    const status = await chrome.goto(page('/never-saved-screen'));
    assert.equal(status, 503);
    assert.match(await chrome.evaluate('document.body.innerText'), /You're offline/);
  });

  // ------------------------------------------------------------------- C
  await step('C. when the event data changes, saved pages are replaced in place — none ever deleted — and the page reloads with the new data', async () => {
    await site.control({ down: '0' });
    await chrome.goto(page('/quest'));
    assert.equal(await marker(), 'PAGE quest v1');
    console.log('        (waiting 46 s: the app checks for new data at most once per 45 s)');
    await sleep(46000);

    const { everSeen, lost } = await watchForDeletions(async () => {
      await site.control({ version: 'v2' });
      await chrome.evaluate('window.dispatchEvent(new Event("online"))');
      await chrome.waitFor(`document.getElementById('marker')?.textContent === 'PAGE quest v2'`, { timeout: 30000 });
      await sleep(500);
    });

    assert.deepEqual(lost, [], 'no saved copy was ever missing during the refresh');
    assert.ok(everSeen.size > 10);
    for (const screen of SCREENS) {
      const body = await chrome.evaluate(bodyOfCopy(`/event/${SLUG}${screen}`));
      assert.match(body ?? '', /v2/, `the saved copy of ${screen || 'home'} is the new one`);
    }
  });

  // ------------------------------------------------------------------- D
  await step('D. if the server is failing while data changes, every saved page is still there — and still opens', async () => {
    await chrome.goto(page('/quest'));
    assert.equal(await marker(), 'PAGE quest v2');
    console.log('        (waiting 46 s)');
    await sleep(46000);

    const { lost } = await watchForDeletions(async () => {
      await site.control({ version: 'v3', pagesFail: '1' });
      await chrome.evaluate('window.dispatchEvent(new Event("online"))');
      await sleep(6000);
    });

    assert.deepEqual(lost, []);
    for (const screen of SCREENS) {
      const body = await chrome.evaluate(bodyOfCopy(`/event/${SLUG}${screen}`));
      assert.match(body ?? '', /PAGE .* v2/, `${screen || 'home'} kept its last good copy (an error page never replaces it)`);
    }

    await chrome.goto(page('/explore'));
    assert.equal(await marker(), 'PAGE explore v2', 'a failing server still shows the saved page');
    await site.control({ pagesFail: '0' });
  });

  // ------------------------------------------------------------------- E
  await step('E. offline again afterwards: still browsable', async () => {
    await site.control({ down: '1' });

    for (const screen of ['/my-day', '/camp-card', '/guide']) {
      await chrome.goto(page(screen));
      assert.match(await marker(), /^PAGE .* v2$/, screen);
    }
    await site.control({ down: '0' });
  });

  // ------------------------------------------------------------------- F
  await step('F. the switch (off by default): switched on, a page open inside the freshness window does not reach the server', async () => {
    await site.control({ version: 'v3' });
    await chrome.goto(page('/quest'));
    assert.match(await marker(), /v3$/, 'switched off (the default): every open fetches from the server');

    // The worker re-reads the switch file at most every 10 minutes (and on each restart); stop it, as the browser does when idle.
    await site.control({ flags: '{"swrPages": true}' });
    await chrome.send('ServiceWorker.enable');
    await chrome.send('ServiceWorker.stopAllWorkers');
    await chrome.goto(page('/quest')); // the worker reads the switch in the background on this open…
    await sleep(1500);
    await chrome.goto(page('/quest')); // …and this open is the last network one, refreshing the saved copy

    await site.control({ reset: '1' });
    await chrome.goto(page('/quest'));
    await chrome.goto(page('/quest'));
    const hits = (await site.control({})).hits[`/event/${SLUG}/quest`] ?? 0;

    assert.equal(hits, 0, 'saved copy is fresh: opened instantly, the server was not asked');
    assert.match(await marker(), /v3$/);
  });
} finally {
  await chrome.close();
  await site.close();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} scenarios passed`);
process.exit(failed.length ? 1 : 0);
