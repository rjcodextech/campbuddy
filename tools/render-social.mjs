// Renders an event's Social media posts and people cards the way the admin page draws them, every size and
// design, into PNG files — to look for words, logos and pictures that run into each other.
//   node tools/render-social.mjs <base-url> <event-id> <out-dir> [email] [password]
// Signs in to the admin panel in headless Chrome, opens Admin → Event → Social media and saves each canvas.
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

const [base = 'http://campbuddy.test', eventId, out = 'social-out', email = 'hello@wpsimplified.in', password = 'Hello@123'] = process.argv.slice(2);
if (!eventId) throw new Error('usage: node tools/render-social.mjs <base-url> <event-id> <out-dir> [email] [password]');
const SIZES = ['1080x1350', '1080x1080', '1080x1920'];
const POSTS = ['classic', 'poster', 'frame', 'ticket', 'magazine', 'split', 'minimal'];
const PEOPLE = ['classic', 'polaroid', 'badge', 'split', 'minimal', 'spotlight', 'arch'];
const PEOPLE_PER_DESIGN = Number(process.env.PEOPLE ?? 4);

const chrome = await launchChrome();
const js = (expr) => chrome.evaluate(expr);
try {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  // A local site (campbuddy.test) often has a self-signed certificate.
  await chrome.send('Security.setIgnoreCertificateErrors', { ignore: true });
  await chrome.send('Page.navigate', { url: `${base}/admin/login` });
  await sleep(2500);
  await js(`(() => { document.querySelector('input[name=email]').value = ${JSON.stringify(email)}; document.querySelector('input[name=password]').value = ${JSON.stringify(password)}; document.querySelector('form').submit(); return 1; })()`);
  await sleep(3500);
  await chrome.send('Page.navigate', { url: `${base}/admin/events/${eventId}/social` });
  await sleep(9000);
  if (!(await js('Boolean(document.querySelector("[data-social-post]"))'))) throw new Error('The Social media page did not open (signed in?)');

  const save = async (file, canvasExpr) => {
    const data = await js(`(() => { const c = ${canvasExpr}; return c ? c.toDataURL('image/png') : null; })()`);
    if (!data) return false;
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, Buffer.from(data.split(',')[1], 'base64'));
    return true;
  };

  for (const size of SIZES) {
    await js(`(() => { const s = document.querySelector('[data-social-size]'); s.value = ${JSON.stringify(size)}; s.dispatchEvent(new Event('change', { bubbles: true })); return 1; })()`);
    await sleep(2500);
    for (const design of POSTS) {
      await js(`document.querySelector('[data-social-style="${design}"]').click(), 1`);
      await sleep(4500);
      const keys = await js(`[...document.querySelectorAll('[data-social-post]')].map((c) => c.dataset.socialPost)`);
      for (const key of keys) {
        await save(path.join(out, 'posts', size, design, `${key}.png`), `document.querySelector('[data-social-post="${key}"] canvas[data-preview]')`);
      }
    }
    for (const design of PEOPLE) {
      await js(`document.querySelector('[data-people-design="${design}"]').click(), 1`);
      await sleep(5000);
      const n = await js(`document.querySelectorAll('[data-people-grid] canvas').length`);
      for (let i = 0; i < Math.min(n, PEOPLE_PER_DESIGN); i++) {
        await save(path.join(out, 'people', size, design, `${i + 1}.png`), `document.querySelectorAll('[data-people-grid] canvas')[${i}]`);
      }
    }
    console.log('rendered', size);
  }
} finally {
  await chrome.close();
}
