// node --test tests/js  — the canvas probe's visibility checks (tests/browser/helpers/canvas-probe.mjs), used by
// tools/audit-social.mjs and tools/audit-reels.mjs: contrast on the real pixels, faces, the brand, the reel safe
// box, whole names, density, empty cards, official logos and reading time.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { analyse, CANVAS_HOOK, inkContrast, readingIssues } from '../../tests/browser/helpers/canvas-probe.mjs';

/** A box of `n` pixels: each `ground` before, and `ink` on the first `inked` of them after. */
const pixels = (n, ground, ink, inked) => {
  const before = new Uint8ClampedArray(n * 4);
  const after = new Uint8ClampedArray(n * 4);
  for (let i = 0; i < n; i++) {
    const g = typeof ground === 'function' ? ground(i) : ground;
    before.set([...g, 255], i * 4);
    after.set([...(i < inked ? ink : g), 255], i * 4);
  }
  return [before, after];
};
const kinds = (r) => r.issues.map(([k]) => k);
const text = (t, b, extra = {}) => ({ k: 'text', text: t, b, s: 40, base: b[3], alpha: 1, ...extra });

test('contrast is read off the real pixels: the ink against the worst of its ground', () => {
  const black = inkContrast(...pixels(100, [255, 255, 255], [0, 0, 0], 30));
  assert.equal(black.ratio, 21, 'black on white');
  assert.deepEqual(black.ink, [0, 0, 0]);
  assert.equal(inkContrast(...pixels(100, [200, 30, 30], [200, 30, 30], 30)).ratio, 1, 'the colour of its ground: invisible');
  // White words on a busy photo: one pixel in ten of the ground is nearly white, so the worst 5% decide.
  const busy = inkContrast(...pixels(200, (i) => (i % 10 === 0 ? [240, 240, 240] : [20, 20, 20]), [255, 255, 255], 40));
  assert.ok(busy.ratio < 1.2, `the bright tenth of the photo wins (${busy.ratio})`);
  const clear = new Uint8ClampedArray(400);
  assert.equal(inkContrast(clear, clear), null, 'a see-through layer has no ground to judge');
});

test('contrast fails under 4.5:1, or 3:1 from 24 px; faded-in words are not judged yet', () => {
  const r = analyse([
    text('Big and pale', [100, 100, 500, 160], { s: 60, contrast: { ratio: 2.6 } }),
    text('Big enough', [100, 200, 500, 260], { s: 60, contrast: { ratio: 3.2 } }),
    text('small and pale', [100, 300, 300, 318], { s: 18, contrast: { ratio: 4 } }),
    text('fading in', [100, 400, 300, 440], { contrast: { ratio: 1.1 }, alpha: 0.5 }),
  ], 1080, 1350, { minSize: 12 });
  assert.deepEqual(r.issues.filter(([k]) => k === 'contrast').map(([, d]) => d.split('"')[1]), ['Big and pale', 'small and pale']);
  assert.equal(r.stats.minContrast, 2.6);
});

test('words never over a face or the brand; a reel keeps words, faces and the brand in its safe box', () => {
  const photo = { k: 'image', b: [300, 300, 700, 700], src: 'face.png', alpha: 1, faces: [[420, 330, 580, 520]], i: 0 };
  const lockup = { k: 'image', b: [400, 1380, 680, 1440], src: '/media/logo-wordmark.png', brand: 'CampBuddy wordmark', alpha: 1, i: 1 };
  const r = analyse([photo, lockup, text('On her face', [400, 400, 700, 450], { i: 2 }), text('On the brand', [420, 1390, 600, 1430], { i: 3 })], 1080, 1920, { safe: [64, 250, 900, 1470], minSize: 34 });
  assert.ok(kinds(r).includes('text-on-face'));
  assert.ok(kinds(r).includes('text-on-brand'));
  const out = analyse([{ ...photo, faces: [[20, 330, 180, 520]] }, text('Too far right', [700, 600, 960, 650])], 1080, 1920, { safe: [64, 250, 900, 1470], minSize: 34 });
  assert.ok(kinds(out).includes('outside-safe'));
  assert.ok(kinds(out).includes('face-outside-safe'));
  assert.ok(kinds(analyse([], 1080, 1920, { requireBrand: true })).includes('no-branding'), 'every reel frame carries the lockup');
});

test('the brand: clear space round it, and only the official files', () => {
  const lockup = { k: 'image', b: [100, 1200, 400, 1260], src: '/media/logo-wordmark.png', brand: 'CampBuddy wordmark', alpha: 1, i: 0 };
  const r = analyse([lockup, text('Too close', [410, 1210, 600, 1250], { s: 30 })], 1080, 1350);
  assert.ok(kinds(r).includes('logo-clearspace'));
  assert.ok(!kinds(analyse([lockup, text('Far enough', [460, 1210, 600, 1250], { s: 30 })], 1080, 1350)).includes('logo-clearspace'));
  assert.ok(kinds(analyse([{ k: 'image', b: [0, 0, 10, 10], src: 'http://x/media/campbuddy-mark-clean.png', alpha: 1 }], 1080, 1350)).includes('unofficial-logo'));
});

test('a name is whole: drawn word by word on one line is fine, on two lines a warning (or an issue), cut an issue', () => {
  const words = ['Venkata', 'Subrahmanya', 'Lakshmi', 'Narasimha', 'Rao'];
  let x = 100;
  const oneLine = words.map((w) => { const t = text(w, [x, 500, x + w.length * 20, 540]); x += w.length * 20 + 12; return t; });
  assert.deepEqual(kinds(analyse(oneLine, 1080, 1350, { names: ['Venkata Subrahmanya Lakshmi Narasimha Rao'] })), []);
  const twoLines = [text('Venkata Subrahmanya Lakshmi', [100, 500, 700, 540]), text('Narasimha Rao', [100, 560, 400, 600], { base: 600 })];
  const split = analyse(twoLines, 1080, 1350, { names: ['Venkata Subrahmanya Lakshmi Narasimha Rao'] });
  assert.deepEqual(split.warnings.map(([k]) => k), ['name-split']);
  assert.ok(kinds(analyse(twoLines, 1080, 1350, { names: ['Venkata Subrahmanya Lakshmi Narasimha Rao'], splitNames: false })).includes('name-split'));
  assert.ok(kinds(analyse([text('Venkata Subrahmanya…', [100, 500, 700, 540])], 1080, 1350, { names: ['Venkata Subrahmanya Lakshmi Narasimha Rao'] })).includes('name-cut'));
});

test('density: words and text sizes above the foot, an empty card; the older designs only warned', () => {
  const crowded = Array.from({ length: 8 }, (_, i) => text('four words right here', [100, 200 + i * 60, 700, 240 + i * 60], { s: [24, 30, 48, 90][i % 4] }));
  const r = analyse(crowded, 1080, 1350, { footTop: 1000, maxWords: 25, maxLevels: 3 });
  assert.ok(kinds(r).includes('words'));
  assert.ok(kinds(r).includes('levels'));
  assert.equal(r.stats.words, 32);
  assert.equal(r.stats.levels, 4, '24, 30, 48, 90: 24→30 is a step of 1.25');
  const legacy = analyse(crowded, 1080, 1350, { footTop: 1000, maxWords: 25, maxLevels: 3, density: 'warn' });
  assert.deepEqual(kinds(legacy), []);
  assert.ok(legacy.warnings.some(([k]) => k === 'words'));
  const empty = analyse([text('Hi', [100, 220, 200, 260], { s: 30 })], 1080, 1350, { footTop: 1000, areaTop: 200 });
  assert.ok(kinds(empty).includes('empty-card'), 'never an empty card');
  const portrait = analyse([text('Hi', [100, 220, 200, 260], { s: 30 }), text('AK', [400, 300, 700, 900], { s: 200 })], 1080, 1350, { footTop: 1000, areaTop: 200 });
  assert.ok(!kinds(portrait).includes('empty-card'), 'initials stand in for a photo');
  assert.equal(portrait.stats.words, 1, 'initials are not words');
});

test('a reel scene stays long enough to read; the hook stays short', () => {
  assert.deepEqual(readingIssues({ i: 1, dur: 2.9, hook: false }, 8), []);
  assert.equal(readingIssues({ i: 1, dur: 2.5, hook: false }, 8)[0][0], 'reading-time');
  assert.deepEqual(readingIssues({ i: 0, dur: 3.5, hook: true }, 6).map(([k]) => k), ['hook-length']);
});

test('the hook carries the checks into the page as they are', () => {
  assert.ok(CANVAS_HOOK.includes(analyse.toString()));
  assert.ok(CANVAS_HOOK.includes(inkContrast.toString()));
  assert.ok(CANVAS_HOOK.includes('window.__probeContrast'));
  // The hook is a script on its own: it parses.
  assert.doesNotThrow(() => new Function(CANVAS_HOOK));
});
