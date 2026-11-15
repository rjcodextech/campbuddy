// Audits Social media posts and people cards in every size and design: draws them the way the admin page
// does, then checks each finished picture (tests/browser/helpers/canvas-probe.mjs) for text off the edge or
// crowding it, text under the minimum size, text running into text, over a face or the brand, text hidden
// under a picture, ellipses and names cut or split, contrast against the pixels really behind each word,
// density (words and text sizes above the foot), empty cards, and logos (official files, clear space, a
// light ground). Saves contact sheets (and every picture with an issue). Exits 1 on any issue.
//
//   npm run audit:social                      — no login: the sample events of tools/social-harness (CI)
//   node tools/audit-social.mjs --harness [--out dir] [--src copy-of-repo] [--sheets dir]
//   node tools/audit-social.mjs <base-url> <event-id> <out-dir> <email> <password>   — a real event, signed in
// Env: SIZES, POSTS (designs), PEOPLE_DESIGNS (comma lists); harness: SAMPLES, KEYS (post keys),
// PEOPLE (cards per sample and design, default all), SAVE=all (every picture, not only those with issues) or none.
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';
import { CANVAS_HOOK } from '../tests/browser/helpers/canvas-probe.mjs';

const argv = process.argv.slice(2);
const SIZES = (process.env.SIZES ?? '1080x1350,1080x1080,1080x1920,1600x900,1200x627').split(',');
const POSTS = (process.env.POSTS ?? 'daylight,classic,poster,frame,ticket,magazine,split,minimal').split(',');
const PEOPLE = (process.env.PEOPLE_DESIGNS ?? 'daylight,classic,polaroid,badge,split,minimal,spotlight,arch').split(',');
// The checks for a 1080-wide post (CAMPBUDDY-BRAND-GUIDELINES.md §8, creative brief §3).
const POST_CHECKS = { minSize: 24, margin: 40, largeSize: 24, maxWords: 25, maxLevels: 3, emptyGap: 0.45 };

if (argv.includes('--harness')) {
  const { flag, list, openHarness, saveDataUrl, summarise } = await import('./social-harness/run.mjs');
  const out = path.resolve(flag(argv, 'out', 'storage/social-audit'));
  const sheetsDir = path.resolve(flag(argv, 'sheets', path.join(out, 'sheets')));
  const h = await openHarness({ src: flag(argv, 'src', undefined) });
  const report = [];
  const saveAll = process.env.SAVE === 'all';
  const saveNone = process.env.SAVE === 'none';
  try {
    const samples = list(process.env.SAMPLES, Object.keys(h.samples));
    const check = async (kind, file, info, sheet, label, extra = {}) => {
      // The full brief for Daylight (the default, §8); the older designs are held to §8 rules 4–7, their density reported.
      const opts = { ...POST_CHECKS, density: extra.design === 'daylight' ? 'fail' : 'warn', names: info.names, footTop: info.footTop, footLeft: info.footLeft, areaTop: info.areaTop, ...extra };
      const res = JSON.parse(await h.js(`JSON.stringify(window.__canvasIssues(window.__card, ${info.W}, ${info.H}, ${JSON.stringify(opts)}))`));
      await h.call('sheetAdd', sheet, `${label}${res.issues.length ? ` ✗${res.issues.length}` : ''}`, 300);
      if (saveAll || (res.issues.length && !saveNone)) saveDataUrl(path.join(out, file), await h.call('png'));
      report.push({ kind, file, issues: res.issues, warnings: res.warnings, stats: res.stats });
    };
    for (const sample of samples) {
      const { posts, people } = h.samples[sample];
      const keys = list(process.env.KEYS, posts).filter((k) => posts.includes(k));
      const who = people.map((p, i) => i).slice(0, Number(process.env.PEOPLE ?? people.length));
      for (const design of POSTS) {
        for (const size of SIZES) {
          const sheet = `posts-${design}-${size}-${sample}`;
          for (const key of keys) {
            const total = await h.call('pages', sample, key, size, design);
            for (let page = 0; page < total; page++) {
              const info = await h.call('post', sample, key, size, design, page);
              await check('post', `posts/${sample}/${design}/${size}/${key}${total > 1 ? `-${page + 1}` : ''}.png`, info, sheet, `${key}${total > 1 ? ` ${page + 1}/${total}` : ''}`, { design });
            }
          }
          saveDataUrl(path.join(sheetsDir, `${sheet}.png`), await h.call('sheetPng', sheet, { title: `${sample} · posts · ${design} · ${size}` }));
        }
        console.log('posts', sample, design);
      }
      for (const design of PEOPLE) {
        for (const size of SIZES) {
          const sheet = `people-${design}-${size}-${sample}`;
          for (const i of who) {
            const info = await h.call('person', sample, i, size, design);
            const slug = people[i].name.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 30);
            // One person per card: their whole name on one line (§8 rule 5).
            await check('person', `people/${sample}/${design}/${size}/${slug}.png`, info, sheet, `${people[i].name.split(' ')[0]} (${people[i].role})`, { splitNames: false, design });
          }
          saveDataUrl(path.join(sheetsDir, `${sheet}.png`), await h.call('sheetPng', sheet, { title: `${sample} · people · ${design} · ${size}` }));
        }
        console.log('people', sample, design);
      }
    }
  } finally {
    await h.close();
  }
  const bad = summarise(report, out, 'Social posts and people cards');
  process.exit(bad ? 1 : 0);
}

const [base, eventId, out = 'storage/social-audit', email, password] = argv;
if (!base || !eventId || !email) throw new Error('usage: node tools/audit-social.mjs --harness | <base-url> <event-id> <out-dir> <email> <password>');
const PER_DESIGN = Number(process.env.PEOPLE ?? 6);

const chrome = await launchChrome();
const js = (expr) => chrome.evaluate(expr);
const report = [];
try {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
  await chrome.send('Security.setIgnoreCertificateErrors', { ignore: true });
  await chrome.send('Page.addScriptToEvaluateOnNewDocument', { source: `${CANVAS_HOOK}\nwindow.__probeContrast = true;` });
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
    report.push({ kind, file: path.relative(out, file), texts: res.texts, pictures: res.pictures, issues: res.issues, warnings: res.warnings });
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
process.exit(bad.length ? 1 : 0);
