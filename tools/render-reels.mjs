// Plays every reel of an event's Social media page in headless Chrome and saves a frame every few seconds,
// to look for words, logos and photos that run into each other (no MP4 is made).
//   node tools/render-reels.mjs <base-url> <event-id> <out-dir> [email] [password]
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

const [base, eventId, out = 'reels-out', email = 'hello@wpsimplified.in', password = 'Hello@123'] = process.argv.slice(2);
if (!base || !eventId) throw new Error('usage: node tools/render-reels.mjs <base-url> <event-id> <out-dir> [email] [password]');
const EVERY_MS = Number(process.env.EVERY ?? 2500);

const chrome = await launchChrome();
const js = (expr) => chrome.evaluate(expr);
try {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  await chrome.send('Page.navigate', { url: `${base}/admin/login` });
  await sleep(2500);
  await js(`(() => { document.querySelector('input[name=email]').value = ${JSON.stringify(email)}; document.querySelector('input[name=password]').value = ${JSON.stringify(password)}; document.querySelector('form').submit(); return 1; })()`);
  await sleep(3500);
  await chrome.send('Page.navigate', { url: `${base}/admin/events/${eventId}/social` });
  await sleep(10000);
  const titles = await js(`[...document.querySelectorAll('[data-reel-list] li')].map((li) => li.querySelector('span').textContent)`);
  if (!titles?.length) throw new Error('No reels on the page (signed in?)');

  const only = process.env.REELS ? process.env.REELS.split(",").map(Number) : null;
  for (let i = 0; i < titles.length; i++) {
    if (only && !only.includes(i)) continue;
    const slug = titles[i].toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40);
    await js(`[...document.querySelectorAll('[data-reel-list] li')][${i}].querySelector('button').click(), 1`);
    await sleep(1500);
    for (let n = 0; n < 60; n++) {
      const data = await js(`(() => { const c = document.querySelector('[data-reel-preview]'); const s = document.createElement('canvas'); s.width = 432; s.height = 768; s.getContext('2d').drawImage(c, 0, 0, 432, 768); return s.toDataURL('image/jpeg', 0.85); })()`);
      if (data) {
        fs.mkdirSync(path.join(out, slug), { recursive: true });
        fs.writeFileSync(path.join(out, slug, `${String(n).padStart(2, '0')}.jpg`), Buffer.from(data.split(',')[1], 'base64'));
      }
      const done = await js(`/Make the MP4/.test(document.querySelector('[data-reel-status]')?.textContent ?? '')`);
      if (done) break;
      await sleep(EVERY_MS);
    }
    console.log('reel', i + 1, titles[i]);
  }
} finally {
  await chrome.close();
}
