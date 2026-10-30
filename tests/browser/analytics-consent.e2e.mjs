// node tests/browser/analytics-consent.e2e.mjs   (part of npm run test:browser)
//
// DPDP Act 2023 consent for Google Analytics, in a REAL browser with the REAL tag
// (attendee/partials/analytics.blade.php rendered by Laravel):
//   A. A first visit shows the question and loads nothing from Google.
//   B. "Allow" loads the tag at once and is remembered on the next page.
//   C. "Analytics choice" asks again; "No thanks" stops hits now and on every later page.
//   D. A browser that sends Global Privacy Control is never asked and never tracked.

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { launchChrome, sleep } from './helpers/chrome.mjs';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '../..');
const php = process.env.PHP_BINARY || (fs.existsSync('C:/wamp64/bin/php/php8.3.35/php.exe') ? 'C:/wamp64/bin/php/php8.3.35/php.exe' : 'php');

const render = spawnSync(php, ['-r', `
  require 'vendor/autoload.php';
  $app = require 'bootstrap/app.php';
  $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
  config(['services.google_analytics.measurement_id' => 'G-CONSENT1', 'app.debug' => false]);
  app('request')->headers->set('HOST', 'campbuddy.club');
  echo view('attendee.partials.analytics')->render();
`], { cwd: root, encoding: 'utf8' });
assert.equal(render.status, 0, render.stderr);
assert.match(render.stdout, /G-CONSENT1/, 'the tag rendered');

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cb-consent-'));
const page = (name, extraHead = '') => {
  const file = path.join(dir, `${name}.html`);
  fs.writeFileSync(file, `<!doctype html><html><head><meta charset="utf-8">${extraHead}${render.stdout}</head><body><main>Page ${name}</main><footer><button type="button" data-analytics-choice hidden>Analytics choice</button></footer></body></html>`);
  return pathToFileURL(file).href;
};
const first = page('one');
const second = page('two');
const gpc = page('gpc', '<script>Object.defineProperty(navigator, "globalPrivacyControl", { value: true });</script>');

const state = `(() => ({
  bar: Boolean(document.getElementById('cb-consent')),
  tag: Boolean(document.querySelector('script[src*="googletagmanager.com/gtag/js"]')),
  gtag: typeof window.gtag === 'function',
  choiceShown: !document.querySelector('[data-analytics-choice]').hidden,
  stored: (() => { try { return localStorage.getItem('cb-analytics-consent'); } catch (e) { return 'no-storage'; } })(),
  disabled: window['ga-disable-G-CONSENT1'] === true,
}))()`;
const click = (selector) => `(() => { const el = ${selector}; el.click(); return true; })()`;

const chrome = await launchChrome();
let failed = 0;
const step = async (name, fn) => {
  try {
    await fn();
    console.log(`ok   ${name}`);
  } catch (error) {
    failed++;
    console.log(`FAIL ${name}\n     ${error.message}`);
  }
};

try {
  // Block the real Google script: the test is about whether it is asked for, not what it does.
  await chrome.send('Network.enable');
  await chrome.send('Network.setBlockedURLs', { urls: ['*googletagmanager.com*', '*google-analytics.com*'] });

  await step('A. a first visit asks, and loads nothing from Google', async () => {
    await chrome.goto(first);
    await sleep(200);
    const s = await chrome.evaluate(state);
    assert.equal(s.stored, null);
    assert.equal(s.bar, true, 'the question is shown');
    assert.equal(s.tag, false, 'no Google script');
    assert.equal(s.gtag, false, 'no gtag');
    assert.equal(s.choiceShown, true, 'the footer "Analytics choice" button is shown');
  });

  await step('B. "Allow" loads the tag at once, and the next page loads it without asking', async () => {
    await chrome.evaluate(click(`[...document.querySelectorAll('#cb-consent button')].find((b) => b.textContent === 'Allow')`));
    let s = await chrome.evaluate(state);
    assert.equal(s.stored, 'granted');
    assert.equal(s.bar, false);
    assert.equal(s.tag, true);
    assert.equal(s.gtag, true);

    await chrome.goto(second);
    await sleep(200);
    s = await chrome.evaluate(state);
    assert.equal(s.bar, false, 'not asked again');
    assert.equal(s.tag, true);
  });

  await step('C. "Analytics choice" asks again; "No thanks" stops it now and later', async () => {
    await chrome.evaluate(click(`document.querySelector('[data-analytics-choice]')`));
    assert.equal((await chrome.evaluate(state)).bar, true);
    await chrome.evaluate(click(`[...document.querySelectorAll('#cb-consent button')].find((b) => b.textContent === 'No thanks')`));
    let s = await chrome.evaluate(state);
    assert.equal(s.stored, 'denied');
    assert.equal(s.disabled, true, 'hits stop on this page');

    await chrome.goto(first);
    await sleep(200);
    s = await chrome.evaluate(state);
    assert.equal(s.bar, false, 'a "no" is not asked again');
    assert.equal(s.tag, false, 'no Google script after "No thanks"');
    assert.equal(s.gtag, false);
  });

  await step('D. Global Privacy Control: never asked, never tracked', async () => {
    await chrome.evaluate(`localStorage.clear()`);
    await chrome.goto(gpc);
    await sleep(200);
    const s = await chrome.evaluate(state);
    assert.equal(s.bar, false);
    assert.equal(s.tag, false);
    assert.equal(s.choiceShown, false, 'nothing to choose');
  });
} finally {
  await chrome.close();
  fs.rmSync(dir, { recursive: true, force: true });
}

if (failed) {
  console.log(`\n${failed} step(s) failed.`);
  process.exit(1);
}
console.log('\nAnalytics consent: all steps passed.');
