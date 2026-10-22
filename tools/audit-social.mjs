// Audits an event's Social media posts and people cards in every size and design: draws them the way the
// admin page does, then checks each finished picture for text off the edge or crowding it, text under the
// minimum size, text running into text, text hidden under a picture, and ellipses. Saves every picture too.
//   node tools/audit-social.mjs <base-url> <event-id> <out-dir> <email> <password>
// Env: SIZES, POSTS, PEOPLE_DESIGNS (comma lists), PEOPLE (cards per design, default 6).
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';
import { CANVAS_HOOK } from '../tests/browser/helpers/canvas-probe.mjs';

const [base, eventId, out = 'storage/social-audit', email, password] = process.argv.slice(2);
if (!base || !eventId || !email) throw new Error('usage: node tools/audit-social.mjs <base-url> <event-id> <out-dir> <email> <password>');
const SIZES = (process.env.SIZES ?? '1080x1350,1080x1080,1080x1920,1600x900,1200x627').split(',');
const POSTS = (process.env.POSTS ?? 'daylight,classic,poster,frame,ticket,magazine,split,minimal').split(',');
const PEOPLE = (process.env.PEOPLE_DESIGNS ?? 'daylight,classic,polaroid,badge,split,minimal,spotlight,arch').split(',');
const PER_DESIGN = Number(process.env.PEOPLE ?? 6);

const chrome = await launchChrome();
const js = (expr) => chrome.evaluate(expr);
const report = [];
try {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
  await chrome.send('Security.setIgnoreCertificateErrors', { ignore: true });
  await chrome.send('Page.addScriptToEvaluateOnNewDocument', { source: CANVAS_HOOK });
  await chrome.goto(`${base}/admin/login`);
  await sleep(1500);
  await js(`(() => { document.querySelector('input[name=email]').value = ${JSON.stringify(email)}; document.querySelector('input[name=password]').value = ${JSON.stringify(password)}; document.querySelector('form').submit(); return 1; })()`);
  await sleep(3500);
  await chrome.goto(`${base}/admin/events/${eventId}/social`);
  await sleep(10000);
  if (!(await js('Boolean(document.querySelector("[data-social-post]"))'))) throw new Error('The Social media page did not open (signed in?)');

  const check = async (file, canvasExpr, W, H, kind, minSize) => {
    const res = await js(`(() => { const c = ${canvasExpr}; if (!c) return null; const r = window.__canvasIssues(c, ${W}, ${H}, { minSize: ${minSize} }); r.png = c.toDataURL('image/png'); return r; })()`);
    if (!res) return;
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, Buffer.from(res.png.split(',')[1], 'base64'));
    report.push({ kind, file: path.relative(out, file), texts: res.texts, pictures: res.pictures, issues: res.issues });
  };

  for (const size of SIZES) {
    const [W, H] = size.split('x').map(Number);
    await js(`(() => { const s = document.querySelector('[data-social-size]'); s.value = ${JSON.stringify(size)}; s.dispatchEvent(new Event('change', { bubbles: true })); return 1; })()`);
    await sleep(2500);
    for (const design of POSTS) {
      await js(`document.querySelector('[data-social-style="${design}"]').click(), 1`);
      await sleep(5000);
      const keys = await js(`[...document.querySelectorAll('[data-social-post]')].map((c) => c.dataset.socialPost)`);
      for (const key of keys) {
        await check(path.join(out, 'posts', size, design, `${key}.png`), `document.querySelector('[data-social-post="${key}"] canvas[data-preview]')`, W, H, 'post', 24);
      }
    }
    // People: one card at a time in the studio — a spread of roles plus the longest names.
    const rail = await js(`[...document.querySelectorAll('[data-people-rail] li button')].map((b) => [...b.querySelectorAll('span')].map((s) => s.textContent))`);
    const pick = [];
    const byLength = rail.map((r, i) => [r[0].length, i]).sort((a, b) => b[0] - a[0]);
    for (const [, i] of byLength.slice(0, 2)) pick.push(i);
    const roles = new Set();
    rail.forEach(([, role], i) => { if (!roles.has(role)) { roles.add(role); pick.push(i); } });
    const people = [...new Set(pick)].slice(0, PER_DESIGN);
    for (const design of PEOPLE) {
      await js(`document.querySelector('[data-people-design="${design}"]').click(), 1`);
      await sleep(1500);
      for (const i of people) {
        await js(`document.querySelectorAll('[data-people-rail] li button')[${i}].click(), 1`);
        await sleep(2500);
        const who = String(rail[i][0]).toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 30);
        await check(path.join(out, 'people', size, design, `${who}.png`), `document.querySelector('[data-people-grid] canvas')`, W, H, 'person', 24);
      }
    }
    console.log('checked', size);
  }
} finally {
  await chrome.close();
}
fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
const bad = report.filter((r) => r.issues.length);
const tally = {};
for (const r of bad) for (const [kind] of r.issues) tally[kind] = (tally[kind] ?? 0) + 1;
console.log(`${report.length} pictures, ${bad.length} with issues`, tally);
