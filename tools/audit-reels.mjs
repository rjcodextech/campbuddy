// Audits every reel of an event's Social media page without playing them in real time: draws each reel's
// frames at a fixed step (the same drawFrame the preview and the MP4 use) and checks each frame for text off
// the safe area or the edge, text under the reel minimum (34 px), text running into text, and text hidden
// under a picture. Saves a frame every few seconds for a look.
//   node tools/audit-reels.mjs <base-url> <event-id> <out-dir> <email> <password>
// Env: STEP (seconds between checked frames, default 0.5), SAVE_EVERY (seconds between saved frames, default 3).
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';
import { CANVAS_HOOK } from '../tests/browser/helpers/canvas-probe.mjs';

const [base, eventId, out = 'storage/reels-audit', email, password] = process.argv.slice(2);
if (!base || !eventId || !email) throw new Error('usage: node tools/audit-reels.mjs <base-url> <event-id> <out-dir> <email> <password>');
const STEP = Number(process.env.STEP ?? 0.5);
const SAVE_EVERY = Number(process.env.SAVE_EVERY ?? 3);
const manifest = JSON.parse(fs.readFileSync(new URL('../public/build/manifest.json', import.meta.url), 'utf8'));
const reelsFile = manifest['resources/js/social-reels.js']?.file;
if (!reelsFile) throw new Error('No social-reels chunk in public/build/manifest.json: run npm run build');

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
  await chrome.goto(`${base}/admin/events/${eventId}/social#studio-reels`);
  await sleep(12000);

  const count = await js(`(async () => {
    const m = await import('/build/${reelsFile}');
    window.__reels = m;
    const C = m._internals.context;
    window.__reelItems = m.reelList(C.kit, C.posts);
    return window.__reelItems.length;
  })()`);
  if (!count) throw new Error('No reels (signed in? kit loaded?)');

  for (let i = 0; i < count; i++) {
    const meta = await js(`(async () => {
      const { _internals: I } = window.__reels;
      const C = I.context;
      const item = window.__reelItems[${i}];
      const own = typeof item.colors === 'function' ? item.colors() : item.colors;
      window.__reel = await I.prepare(item, C.kit, own ? { ...C.colors, ...own } : C.colors, C.style());
      const c = document.createElement('canvas');
      c.width = 1080; c.height = 1920;
      window.__reelCanvas = c;
      return { title: item.title, key: item.key, duration: window.__reel.duration };
    })()`);
    const slug = `${String(i + 1).padStart(2, '0')}-${String(meta.key ?? meta.title).toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 40)}`;
    const issues = {};
    let lastSaved = -SAVE_EVERY;
    for (let t = 0; t < meta.duration; t += STEP) {
      const res = await js(`(() => {
        const c = window.__reelCanvas;
        const ctx = c.getContext('2d');
        window.__canvasOps.set(c, []);
        window.__reels._internals.drawFrame(ctx, window.__reel, ${t});
        const r = window.__canvasIssues(c, 1080, 1920, { minSize: 34, margin: 60, minAlpha: 0.6 });
        ${'r.jpg = ' + `(${t} - ${lastSaved} >= ${SAVE_EVERY}) ? c.toDataURL('image/jpeg', 0.8) : null;`}
        return r;
      })()`);
      for (const [kind, detail] of res.issues) (issues[`${kind} | ${detail}`] ??= []).push(Math.round(t * 10) / 10);
      if (res.jpg) {
        lastSaved = t;
        fs.mkdirSync(path.join(out, slug), { recursive: true });
        fs.writeFileSync(path.join(out, slug, `${String(Math.round(t * 10)).padStart(4, '0')}.jpg`), Buffer.from(res.jpg.split(',')[1], 'base64'));
      }
    }
    report.push({ reel: slug, title: meta.title, duration: meta.duration, issues: Object.entries(issues).map(([k, at]) => ({ issue: k, frames: at.length, first: at[0] })) });
    console.log(slug, meta.duration + 's', Object.keys(issues).length, 'issue kinds');
  }
} finally {
  await chrome.close();
}
fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
