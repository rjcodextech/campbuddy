// Audits reels without playing them in real time: draws each reel's frames at a fixed step (the same
// drawFrame the preview and the MP4 use) and checks each frame (tests/browser/helpers/canvas-probe.mjs) for
// text outside the safe box (x 64–900, y 250–1470) or within 60 px of the edge, text under the reel minimum
// (34 px), text running into text or over a face, contrast against the pixels behind each word, the brand
// lockup on every frame, ellipses; and each scene for reading time (at least 0.3 s a word plus 0.5 s; the
// hook 3 s at most), and neighbouring reels for different templates. Exits 1 on any issue.
//
//   npm run audit:reels                       — no login: the sample events of tools/social-harness (CI)
//   node tools/audit-reels.mjs --harness [--out dir] [--src copy-of-repo]
//   node tools/audit-reels.mjs <base-url> <event-id> <out-dir> <email> <password>   — a real event, signed in
// Env: STEP (seconds between checked frames, default 0.5), SAVE_EVERY (logged-in mode: seconds between saved
// frames, default 3); harness: SAMPLES, REELS (a regex on reel keys), SPOTLIGHTS (talk spotlights per sample,
// default 2), MATRIX (reel keys drawn in every template too), TEMPLATES, MATRIX_SAMPLES.
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';
import { CANVAS_HOOK, readingIssues } from '../tests/browser/helpers/canvas-probe.mjs';

const argv = process.argv.slice(2);
const STEP = Number(process.env.STEP ?? 0.5);
// The reel checks (CAMPBUDDY-BRAND-GUIDELINES.md §9, creative brief §3): the safe box, 34 px, 60 px off the edge.
const SAFE = [64, 250, 900, 1470];
const FRAME_CHECKS = { minSize: 34, margin: 60, minAlpha: 0.6, safe: SAFE, largeSize: 24 };
// The words a scene says (above the brand strip), without the step pill ("2", "OF 7 · Schedule").
const WORD_COUNT = { footTop: 1470 - 92 - 4, areaTop: 250, skip: '^(\\d{1,2}|OF \\d+ · .*)$' };

if (argv.includes('--harness')) {
  const { flag, list, openHarness, saveDataUrl, summarise } = await import('./social-harness/run.mjs');
  const out = path.resolve(flag(argv, 'out', 'storage/reels-audit'));
  const h = await openHarness({ src: flag(argv, 'src', undefined) });
  const report = [];
  const only = process.env.REELS ? new RegExp(process.env.REELS) : null;
  const spotlights = Number(process.env.SPOTLIGHTS ?? 2);
  const TEMPLATES = list(process.env.TEMPLATES, ['daylight', 'stage', 'poster', 'photo', 'minimal', 'pulse']);
  const MATRIX = list(process.env.MATRIX, ['post-speakers', 'post-schedule', 'organizer', 'spotlight-1']);
  const audit = async (sample, index, template, label) => {
    const meta = await h.call('prepareReel', sample, index, template);
    const slug = `${sample}/${String(index + 1).padStart(2, '0')}-${meta.key}${template ? `@${template}` : ''}`;
    const issues = {};
    const warnings = {};
    const note = (bag, at) => ([kind, detail]) => (bag[`${kind} | ${detail}`] ??= []).push(Math.round(at * 10) / 10);
    const sheet = `reel-${slug}`;
    for (let t = 0; t < meta.duration; t += STEP) {
      await h.call('reelFrame', t);
      const scene = meta.scenes.findLast((s) => s.start <= t) ?? meta.scenes[0];
      const res = JSON.parse(await h.js(`JSON.stringify(window.__canvasIssues(window.__reelCanvas, 1080, 1920, ${JSON.stringify({ ...FRAME_CHECKS, requireBrand: !scene.end })}))`));
      res.issues.forEach(note(issues, t));
      res.warnings.forEach(note(warnings, t));
      await h.call('sheetAddReel', sheet, `${t.toFixed(1)}s`, 280);
    }
    // Each scene once it has settled: its words against its time on screen, its names whole.
    for (const scene of meta.scenes) {
      // Settled: after its words are in, before it starts to leave.
      const at = scene.start + Math.max(scene.dur * 0.6, scene.dur - 0.4);
      await h.call('reelFrame', at);
      const res = JSON.parse(await h.js(`JSON.stringify(window.__canvasIssues(window.__reelCanvas, 1080, 1920, ${JSON.stringify({ ...FRAME_CHECKS, ...WORD_COUNT, names: scene.names, logos: false })}))`));
      res.issues.filter(([k]) => k.startsWith('name')).forEach(note(issues, at));
      res.warnings.filter(([k]) => k.startsWith('name')).forEach(note(warnings, at));
      readingIssues(scene, res.stats.words ?? 0).forEach(note(issues, scene.start));
      if ((res.stats.words ?? 0) > 8 && !scene.end) note(warnings, scene.start)(['beat-words', `scene ${scene.i + 1}: ${res.stats.words} words (8 or fewer reads best)`]);
    }
    saveDataUrl(path.join(out, 'sheets', `${slug.replace('/', '-')}.png`), await h.call('sheetPng', sheet, { title: `${label} · ${meta.template} · ${meta.duration.toFixed(1)}s`, maxWidth: 3000 }));
    const list2 = (bag) => Object.entries(bag).map(([k, at]) => [k.split(' | ')[0], `${k.split(' | ').slice(1).join(' | ')} (${at.length} frames from ${at[0]}s)`]);
    report.push({ reel: slug, title: meta.title, template: meta.template, duration: meta.duration, issues: list2(issues), warnings: list2(warnings) });
    console.log(slug, meta.template, `${meta.duration.toFixed(1)}s`, Object.keys(issues).length, 'issue kinds');
    return meta;
  };
  try {
    for (const sample of list(process.env.SAMPLES, Object.keys(h.samples))) {
      const reels = await h.call('reels', sample);
      let spot = 0;
      const own = [];
      for (const [i, r] of reels.entries()) {
        if (only && !only.test(r.key)) continue;
        if (r.key.startsWith('spotlight-') && spot++ >= spotlights) continue;
        own.push(await audit(sample, i, null, `${sample} · ${r.title}`));
      }
      // Every reel is unique: neighbours never share a template (the reels' own picks).
      if (!only) {
        const all = [];
        for (const [i] of reels.entries()) all.push((await h.call('prepareReel', sample, i, null)).template);
        const same = all.map((t, i) => (i && t === all[i - 1] ? `${reels[i - 1].key} and ${reels[i].key} are both ${t}` : null)).filter(Boolean);
        report.push({ reel: `${sample}/neighbours`, issues: same.slice(0, 5).map((s) => ['neighbour-template', s]), warnings: [] });
      }
      if (list(process.env.MATRIX_SAMPLES, ['typical', 'long']).includes(sample)) {
        for (const key of MATRIX) {
          const i = reels.findIndex((r) => r.key === key || (key === 'spotlight-1' && r.key.startsWith('spotlight-')));
          if (i < 0) continue;
          for (const template of TEMPLATES) await audit(sample, i, template, `${sample} · ${reels[i].title}`);
        }
      }
    }
  } finally {
    await h.close();
  }
  const bad = summarise(report, out, 'Reels');
  process.exit(bad ? 1 : 0);
}

const SAVE_EVERY = Number(process.env.SAVE_EVERY ?? 3);
const [base, eventId, out = 'storage/reels-audit', email, password] = argv;
if (!base || !eventId || !email) throw new Error('usage: node tools/audit-reels.mjs --harness | <base-url> <event-id> <out-dir> <email> <password>');
const manifest = JSON.parse(fs.readFileSync(new URL('../public/build/manifest.json', import.meta.url), 'utf8'));
const reelsFile = manifest['resources/js/social-reels.js']?.file;
if (!reelsFile) throw new Error('No social-reels chunk in public/build/manifest.json: run npm run build');

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
        const r = window.__canvasIssues(c, 1080, 1920, ${JSON.stringify(FRAME_CHECKS)});
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
process.exit(report.some((r) => r.issues.length) ? 1 : 0);
