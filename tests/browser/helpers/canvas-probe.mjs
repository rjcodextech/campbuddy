// Records what a page draws on its <canvas> elements (text with its real ink
// box, pictures, and canvases copied onto other canvases), so a test can ask
// of a finished post, people card or reel frame: does any text leave the
// picture or crowd its edge, is any text too small, do two lines of text run
// into each other, is any text covered by a picture drawn on top of it, is
// anything cut short with an ellipsis. Install CANVAS_HOOK before the page
// loads (Page.addScriptToEvaluateOnNewDocument), then call
// window.__canvasIssues(canvas, nominalWidth, nominalHeight, options).
//
// The visibility checks of the creative brief (§3), added 7 Nov 2026:
//   - contrast: with window.__probeContrast = true, every text run's pixels are read just before and just
//     after it is drawn — the ground it lands on (a render without that text) and its own ink — and its
//     ratio is the worst 5th percentile of the ground's pixels inside the glyph box against the ink
//     (inkContrast). 4.5:1, or 3:1 for text of 24 px or more at 1×;
//   - words over a face (the face boxes a picture carries in window.__faceBoxes[src]) or over the brand
//     lockup; words, faces or the brand outside a reel's safe box (options.safe);
//   - names: each of options.names is shown whole on one line (merged word by word), never cut;
//   - density: at most options.maxWords words and options.maxLevels text sizes above options.footTop
//     (the foot — QR card or block, and the brand bar — is the card's signature, counted apart);
//   - never an empty card: no gap of more than options.emptyGap of the message area without words or a picture;
//   - logos: only the official CampBuddy / WPSimplified files, with clear space of 28% of their height,
//     on a light ground or plate (never on a dark or busy one), centred in their plate.
// analyse() and inkContrast() are plain functions (unit-tested in tests/js/canvas-probe.test.mjs) and
// are written into the hook as they are.

/**
 * The contrast of a text run: `before` and `after` are the RGBA pixels of its glyph box just before and
 * just after it was drawn. Its ink is the mean colour of the pixels that changed most (the glyphs' cores);
 * its ground is every pixel of the box before; the result is the worst 5th percentile of the ground's
 * contrast with the ink. Null when the ground is see-through (an off-screen layer) or nothing was drawn.
 */
export function inkContrast(before, after) {
  const n = Math.floor(before.length / 4);
  if (!n) return null;
  const lin = (v) => { const c = v / 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
  const lum = (r, g, b) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
  let clear = 0;
  let max = 0;
  const delta = new Float32Array(n);
  for (let i = 0; i < n; i++) {
    const k = i * 4;
    if (before[k + 3] < 200) clear++;
    const d = Math.abs(after[k] - before[k]) + Math.abs(after[k + 1] - before[k + 1]) + Math.abs(after[k + 2] - before[k + 2]);
    delta[i] = d;
    if (d > max) max = d;
  }
  if (clear > n * 0.5) return null;
  // Nothing changed: the text is the colour of its ground (invisible), 1:1.
  if (max < 6) return { ratio: 1, ink: null };
  let r = 0;
  let g = 0;
  let b = 0;
  let m = 0;
  for (let i = 0; i < n; i++) {
    if (delta[i] < max * 0.7) continue;
    const k = i * 4;
    r += after[k]; g += after[k + 1]; b += after[k + 2]; m++;
  }
  r /= m; g /= m; b /= m;
  const ink = lum(r, g, b);
  const ratios = new Float32Array(n);
  for (let i = 0; i < n; i++) {
    const k = i * 4;
    const l = lum(before[k], before[k + 1], before[k + 2]);
    ratios[i] = (Math.max(l, ink) + 0.05) / (Math.min(l, ink) + 0.05);
  }
  ratios.sort();
  return { ratio: Math.round(ratios[Math.floor(n * 0.05)] * 100) / 100, ink: [Math.round(r), Math.round(g), Math.round(b)] };
}

/**
 * What is wrong with a picture, from what was drawn on it (`ops`, already in the design's own pixels:
 * text {k:'text', text, b:[x0,y0,x1,y1], s, base, alpha, rotated, clipped, squeezed, contrast},
 * picture {k:'image', b, src, alpha, full, copy, brand, faces}). Returns failing `issues`, `warnings`
 * (worth a look, never failing) and `stats`, each issue as [kind, detail].
 */
export function analyse(ops, W, H, o = {}) {
  const minSize = o.minSize ?? 24;
  const margin = o.margin ?? 40;
  const issues = [];
  const warnings = [];
  const put = (list) => (kind, detail) => { if (list.length < 60 && !list.some((x) => x[0] === kind && x[1] === detail)) list.push([kind, detail]); };
  const add = put(issues);
  const warn = put(warnings);
  const short = (s) => String(s).replace(/\s+/g, ' ').slice(0, 40);
  const area = (b) => Math.max(0, b[2] - b[0]) * Math.max(0, b[3] - b[1]);
  const overlap = (a, b) => Math.max(0, Math.min(a[2], b[2]) - Math.max(a[0], b[0])) * Math.max(0, Math.min(a[3], b[3]) - Math.max(a[1], b[1]));
  const grow = (b, d) => [b[0] - d, b[1] - d, b[2] + d, b[3] + d];
  const texts = ops.filter((op) => op.k === 'text' && op.alpha > (o.minAlpha ?? 0.05));
  const images = ops.filter((op) => op.k === 'image');
  const settled = (t) => t.alpha >= 0.98;
  // Initials on a portrait without a photo are a picture, not words.
  const initials = (t) => /^[\p{Lu}\p{Lo}]{1,3}$/u.test(String(t.text).trim()) && t.s >= 40;

  // The brand: the lockup's marks (side by side) as one box each.
  const marks = images.filter((im) => im.brand && im.alpha >= 0.5);
  const lockups = [];
  for (const m of [...marks].sort((a, b) => a.b[0] - b.b[0])) {
    const h = m.b[3] - m.b[1];
    const near = lockups.find((l) => m.b[0] - l.b[2] < h * 0.8 && Math.min(l.b[3], m.b[3]) - Math.max(l.b[1], m.b[1]) > -h * 0.2);
    if (near) near.b = [Math.min(near.b[0], m.b[0]), Math.min(near.b[1], m.b[1]), Math.max(near.b[2], m.b[2]), Math.max(near.b[3], m.b[3])];
    else lockups.push({ b: [...m.b], brand: m.brand });
  }
  const inBrand = (t) => lockups.some((l) => overlap(t.b, grow(l.b, (l.b[3] - l.b[1]) * 0.6)) > 0);

  let minContrast = Infinity;
  for (const t of texts) {
    const [x0, y0, x1, y1] = t.b;
    const edge = Math.min(x0, y0, W - x1, H - y1);
    if (edge < -1) add('off-canvas', `${short(t.text)} by ${Math.round(-edge)}px`);
    else if (edge < margin && !t.clipped && !inBrand(t)) add('near-edge', `${short(t.text)} ${Math.round(edge)}px from edge`);
    if (t.s < minSize - 0.5) add('small', `${short(t.text)} ${Math.round(t.s)}px`);
    if (/…|\.\.\.\s*$/.test(t.text)) add('ellipsis', short(t.text));
    if (t.squeezed && t.squeezed < 0.9) add('squeezed', `${short(t.text)} to ${Math.round(t.squeezed * 100)}%`);
    // A word wiped in behind a clip that has drawn nothing yet is not on screen: not judged.
    if (t.contrast && settled(t) && !initials(t) && !(t.clipped && t.contrast.ink === null)) {
      const need = t.s >= (o.largeSize ?? 24) - 0.5 ? (o.largeContrast ?? 3) : (o.minContrast ?? 4.5);
      minContrast = Math.min(minContrast, t.contrast.ratio);
      if (t.contrast.ratio < need) add('contrast', `"${short(t.text)}" ${t.contrast.ratio}:1 (needs ${need}:1)`);
      else if (t.s < (o.bodySize ?? 32) && t.contrast.ratio < 4.5) warn('contrast-body', `"${short(t.text)}" ${t.contrast.ratio}:1 at ${Math.round(t.s)}px`);
    }
    if (o.safe) {
      const s = o.safe;
      if (x0 < s[0] - 1 || y0 < s[1] - 1 || x1 > s[2] + 1 || y1 > s[3] + 1) add('outside-safe', `${short(t.text)} at ${Math.round(x0)},${Math.round(y0)}–${Math.round(x1)},${Math.round(y1)}`);
    }
  }

  for (let i = 0; i < texts.length; i++) {
    for (let j = i + 1; j < texts.length; j++) {
      const A = texts[i];
      const B = texts[j];
      const same = A.text === B.text && A.b.every((v, n) => Math.abs(v - B.b[n]) < 8);
      // A tilted line's upright box is bigger than its ink: tilted text is left to the eye (screenshots).
      if (same || A.rotated || B.rotated) continue;
      const w = Math.min(A.b[2], B.b[2]) - Math.max(A.b[0], B.b[0]);
      const h = Math.min(A.b[3], B.b[3]) - Math.max(A.b[1], B.b[1]);
      if (w <= 2 || h <= 2) continue;
      const share = (w * h) / Math.min(area(A.b), area(B.b));
      if (share > 0.12) add('text-on-text', `"${short(A.text)}" x "${short(B.text)}" ${Math.round(share * 100)}%`);
    }
  }
  for (const t of texts) {
    for (const im of images) {
      if (im.i < t.i || im.full || im.copy || im.alpha < 0.5) continue;
      if (overlap(t.b, im.b) / Math.max(1, area(t.b)) > 0.2) add('covered-by-picture', `${short(t.text)} under ${im.src}`);
    }
  }

  // Faces: words never over one; a reel keeps them in its safe box.
  const faces = images.filter((im) => im.alpha >= 0.5).flatMap((im) => (im.faces ?? []).map((f) => ({ b: f, src: im.src, i: im.i })));
  for (const f of faces) {
    for (const t of texts) {
      if (initials(t) || t.alpha < 0.5) continue;
      if (overlap(t.b, f.b) / Math.max(1, area(t.b)) > 0.08) add('text-on-face', `"${short(t.text)}" over a face`);
    }
    if (o.safe && overlap(f.b, o.safe) / Math.max(1, area(f.b)) < 0.9) add('face-outside-safe', `a face at ${Math.round(f.b[0])},${Math.round(f.b[1])}`);
  }
  // The brand: never under words, clear space round it, inside a reel's safe box, the official files only.
  for (const l of lockups) {
    for (const t of texts) if (overlap(t.b, l.b) / Math.max(1, area(t.b)) > 0.05) add('text-on-brand', `"${short(t.text)}" over the ${l.brand}`);
    const h = l.b[3] - l.b[1];
    const clear = grow(l.b, h * (o.clearSpace ?? 0.28) - 1);
    for (const op of ops) {
      if (op.brand || op.full || op.alpha < 0.5) continue;
      if (op.k === 'image' && (op.copy || area(op.b) > W * H * 0.5)) continue;
      if (overlap(op.b, clear) > 4) add('logo-clearspace', `${op.k === 'text' ? `"${short(op.text)}"` : 'a picture'} within ${Math.round(h * (o.clearSpace ?? 0.28))}px of the ${l.brand}`);
    }
    const s = o.safe;
    if (s && (l.b[0] < s[0] - 1 || l.b[1] < s[1] - 1 || l.b[2] > s[2] + 1 || l.b[3] > s[3] + 1)) add('brand-outside-safe', `the ${l.brand}`);
  }
  // Reels: the lockup (or the end card's marks) on every frame.
  if (o.requireBrand && !lockups.length) add('no-branding', 'no CampBuddy × WPSimplified mark on this frame');
  const official = o.officialLogos ?? ['logo-wordmark.png', 'logo-wordmark-sm.png', 'logo.png', 'favicon.png', 'wpsimplified-logo.png'];
  for (const im of images) {
    const file = String(im.src).split(/[?#]/)[0].split('/').pop() ?? '';
    if (/\/media\//.test(String(im.src)) && /logo|campbuddy|wpsimplified|mark|favicon/i.test(file) && !official.includes(file)) add('unofficial-logo', file);
  }

  // Names: whole, on one line; on two lines only where the grid is too narrow (a warning), never cut.
  const lines = [];
  for (const t of [...texts].filter((x) => !x.rotated && x.alpha >= 0.5).sort((a, b) => a.b[0] - b.b[0])) {
    const base = t.base ?? t.b[3];
    const line = lines.find((l) => Math.abs(l.base - base) < t.s * 0.2 && Math.abs(l.s - t.s) < t.s * 0.12 && t.b[0] - l.b[2] < t.s * 0.9 && t.b[0] - l.b[2] > -t.s * 0.3);
    if (line) {
      line.text += ` ${t.text}`;
      line.b = [Math.min(line.b[0], t.b[0]), Math.min(line.b[1], t.b[1]), Math.max(line.b[2], t.b[2]), Math.max(line.b[3], t.b[3])];
    } else lines.push({ text: String(t.text), base, s: t.s, b: [...t.b] });
  }
  const norm = (s) => String(s).replace(/[“”"*]/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
  const shown = lines.map((l) => norm(l.text));
  for (const name of o.names ?? []) {
    const n = norm(name);
    if (!n || shown.some((l) => l.includes(n))) continue;
    const all = shown.join(' ');
    if (n.split(' ').every((w) => all.includes(w))) (o.splitNames === false ? add : warn)('name-split', short(name));
    else add('name-cut', short(name));
  }

  // Density and never an empty card: the message area, above the foot (and left of a landscape side panel).
  const stats = { texts: texts.length, pictures: images.length, minContrast: Number.isFinite(minContrast) ? minContrast : null };
  if (o.footTop !== undefined) {
    const footLeft = o.footLeft ?? W;
    const skip = o.skip ? new RegExp(o.skip, 'u') : null;
    const message = texts.filter((t) => !initials(t) && !inBrand(t) && !(skip && skip.test(String(t.text).trim())) && (t.b[1] + t.b[3]) / 2 < o.footTop && (t.b[0] + t.b[2]) / 2 < footLeft);
    const words = message.reduce((n, t) => n + String(t.text).split(/\s+/).filter((w) => /[\p{L}\p{N}]/u.test(w)).length, 0);
    const sizes = [...new Set(message.map((t) => Math.round(t.s)))].sort((a, b) => a - b);
    let levels = 0;
    let lastLevel = 0;
    for (const s of sizes) if (!levels || s > lastLevel * (o.levelStep ?? 1.2)) { levels++; lastLevel = s; }
    Object.assign(stats, { words, levels });
    // Density is failing for the default design; o.density === 'warn' reports it only (the older designs, §8).
    const dense = o.density === 'warn' ? warn : add;
    if (o.maxWords && words > o.maxWords) dense('words', `${words} words above the foot (most ${o.maxWords})`);
    if (o.maxLevels && levels > o.maxLevels) dense('levels', `${levels} text sizes above the foot (${sizes.join(', ')} px; most ${o.maxLevels})`);
    if (o.areaTop !== undefined) {
      const top = o.areaTop;
      const bottom = o.footTop;
      // A portrait's initials stand in for its photo: content, though not words.
      const pictureWords = texts.filter((t) => initials(t) && (t.b[1] + t.b[3]) / 2 < o.footTop);
      const content = [...message, ...pictureWords, ...images.filter((im) => !im.full && !im.brand && !im.copy && im.alpha >= 0.5 && area(im.b) < W * H * 0.5)]
        .map((x) => [Math.max(top, x.b[1]), Math.min(bottom, x.b[3])]).filter(([a, b]) => b > a).sort((a, b) => a[0] - b[0]);
      let y = top;
      let gap = 0;
      let covered = 0;
      for (const [a, b] of content) {
        if (a > y) gap = Math.max(gap, a - y);
        if (b > y) { covered += b - Math.max(a, y); y = b; }
      }
      gap = Math.max(gap, bottom - y);
      const room = Math.max(1, bottom - top);
      Object.assign(stats, { emptiest: Math.round((gap / room) * 100) / 100, filled: Math.round((covered / room) * 100) / 100 });
      if (gap / room > (o.emptyGap ?? 0.45)) add('empty-card', `${Math.round((gap / room) * 100)}% of the message area has nothing in it`);
      if (covered / room > 0.92) warn('crowded', `${Math.round((covered / room) * 100)}% of the message area's height is filled`);
    }
  }
  return { issues, warnings, stats };
}

/**
 * A reel scene's reading time (creative brief §3.6): on screen at least 0.3 s a word plus 0.5 s; the hook
 * (the first scene) no longer than 3 s. `scene`: {i, dur, hook}; `words`: what it says, step pill and brand apart.
 */
export function readingIssues(scene, words) {
  const out = [];
  const need = 0.3 * words + 0.5;
  if (words && scene.dur + 1e-6 < need) out.push(['reading-time', `scene ${scene.i + 1}${scene.hook ? ' (hook)' : ''}: ${words} words for ${scene.dur.toFixed(1)}s (needs ${need.toFixed(1)}s)`]);
  if (scene.hook && scene.dur > 3.05) out.push(['hook-length', `the hook holds ${scene.dur.toFixed(1)}s (3s at most)`]);
  return out;
}

export const CANVAS_HOOK = `(() => {
  if (window.__canvasOps) return;
  const OPS = new Map();
  window.__canvasOps = OPS;
  const inkContrast = ${inkContrast.toString()};
  const analyse = ${analyse.toString()};
  const P = CanvasRenderingContext2D.prototype;
  const list = (c) => { let a = OPS.get(c); if (!a) OPS.set(c, (a = [])); return a; };
  const isEl = (c) => typeof HTMLCanvasElement !== 'undefined' && c instanceof HTMLCanvasElement;
  const corners = (t, x0, y0, x1, y1) => {
    const pts = [[x0, y0], [x1, y0], [x0, y1], [x1, y1]].map(([x, y]) => [t.a * x + t.c * y + t.e, t.b * x + t.d * y + t.f]);
    const xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
    return [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)];
  };
  const det = (t) => Math.sqrt(Math.abs(t.a * t.d - t.b * t.c));
  const px = (font) => { const m = String(font).match(/([\\d.]+)px/); return m ? parseFloat(m[1]) : 10; };
  const opaque = (style) => typeof style === 'string' && !/rgba\\([^)]*,\\s*0?\\.\\d+\\)|hsla|transparent/.test(style) && !/^#[0-9a-f]{8}$/i.test(style);
  const clips = new WeakMap();
  const depth = (ctx) => (clips.get(ctx) || [0]).at(-1);
  const { fillText, strokeText, drawImage, clearRect, fillRect, save, restore, clip, getImageData } = P;
  const BRAND = [[/\\/media\\/logo-wordmark(-sm)?\\.png/, 'CampBuddy wordmark'], [/\\/media\\/logo\\.png/, 'CampBuddy logo'], [/\\/media\\/favicon\\.png/, 'CampBuddy mark'], [/\\/media\\/wpsimplified-logo\\.png/, 'WPSimplified mark']];
  const brandOf = (src) => (BRAND.find(([re]) => re.test(src)) || [])[1] || null;

  P.save = function () { const s = clips.get(this) || [0]; s.push(s.at(-1)); clips.set(this, s); return save.call(this); };
  P.restore = function () { const s = clips.get(this); if (s && s.length > 1) s.pop(); return restore.call(this); };
  P.clip = function (...a) { const s = clips.get(this) || [0]; s[s.length - 1] += 1; clips.set(this, s); return clip.apply(this, a); };

  // The pixels of a box (device px), or null (off the canvas, unreadable).
  const read = (ctx, b) => {
    try {
      const c = ctx.canvas;
      const x0 = Math.max(0, Math.floor(b[0])), y0 = Math.max(0, Math.floor(b[1]));
      const x1 = Math.min(c.width, Math.ceil(b[2])), y1 = Math.min(c.height, Math.ceil(b[3]));
      if (x1 - x0 < 1 || y1 - y0 < 1 || (x1 - x0) * (y1 - y0) > 4e6) return null;
      return getImageData.call(ctx, x0, y0, x1 - x0, y1 - y0).data;
    } catch (e) { return null; }
  };

  const text = (ctx, how, value, x, y, maxWidth) => {
    try {
      const c = ctx.canvas;
      if (!isEl(c) || !String(value).trim()) return null;
      const m = ctx.measureText(value);
      let l = x - m.actualBoundingBoxLeft, r = x + m.actualBoundingBoxRight;
      if (maxWidth !== undefined && m.width > maxWidth) { const k = maxWidth / m.width; l = x + (l - x) * k; r = x + (r - x) * k; }
      const t = ctx.getTransform();
      const op = { k: 'text', how, rotated: Math.abs(t.b) > 1e-6 || Math.abs(t.c) > 1e-6, text: String(value), box: corners(t, l, y - m.actualBoundingBoxAscent, r, y + m.actualBoundingBoxDescent), base: t.b * x + t.d * y + t.f, size: px(ctx.font) * det(t), font: ctx.font, alpha: ctx.globalAlpha, clipped: depth(ctx) > 0, squeezed: maxWidth !== undefined && m.width > maxWidth * 1.001 ? +(maxWidth / m.width).toFixed(2) : 0 };
      list(c).push(op);
      return op;
    } catch (e) { return null; }
  };
  const drawn = (ctx, op, draw) => {
    const before = op && window.__probeContrast && !op.rotated && ctx.globalAlpha >= 0.98 ? read(ctx, op.box) : null;
    const out = draw();
    if (before) { const after = read(ctx, op.box); if (after && after.length === before.length) op.contrast = inkContrast(before, after); }
    return out;
  };
  P.fillText = function (value, x, y, maxWidth) { const op = text(this, 'fill', value, x, y, maxWidth); return drawn(this, op, () => (maxWidth === undefined ? fillText.call(this, value, x, y) : fillText.call(this, value, x, y, maxWidth))); };
  P.strokeText = function (value, x, y, maxWidth) { const op = text(this, 'stroke', value, x, y, maxWidth); return drawn(this, op, () => (maxWidth === undefined ? strokeText.call(this, value, x, y) : strokeText.call(this, value, x, y, maxWidth))); };

  P.drawImage = function (src, ...a) {
    try {
      const c = this.canvas;
      if (isEl(c)) {
        const sw0 = src.width || src.naturalWidth || src.videoWidth || 0, sh0 = src.height || src.naturalHeight || src.videoHeight || 0;
        let sx = 0, sy = 0, sw = sw0, sh = sh0, dx, dy, dw, dh;
        if (a.length === 2) { [dx, dy] = a; dw = sw; dh = sh; } else if (a.length === 4) { [dx, dy, dw, dh] = a; } else { [sx, sy, sw, sh, dx, dy, dw, dh] = a; }
        const t = this.getTransform();
        const box = corners(t, dx, dy, dx + dw, dy + dh);
        const full = box[0] <= 1 && box[1] <= 1 && box[2] >= c.width - 1 && box[3] >= c.height - 1;
        // A blurred copy (the backdrop of a landscape post) is a picture, not text anyone reads.
        const blurred = /blur\\(/.test(String(this.filter || ''));
        const kx = sw ? dw / sw : 1, ky = sh ? dh / sh : 1;
        const map = (b) => corners(t, dx + (b[0] - sx) * kx, dy + (b[1] - sy) * ky, dx + (b[2] - sx) * kx, dy + (b[3] - sy) * ky);
        if (isEl(src) && OPS.has(src) && sw && sh && !blurred) {
          if (full && this.globalAlpha >= 0.99) OPS.set(c, []);
          for (const op of OPS.get(src)) {
            const mapped = map(op.box);
            if (mapped[2] < 0 || mapped[3] < 0 || mapped[0] > c.width || mapped[1] > c.height) continue;
            list(c).push({ ...op, box: mapped, base: op.base !== undefined ? t.d * (dy + (op.base - sy) * ky) + t.f : undefined, faces: op.faces ? op.faces.map(map) : undefined, size: op.size * Math.sqrt(Math.abs(kx * ky)) * det(t), alpha: op.alpha * this.globalAlpha, clipped: op.clipped || depth(this) > 0 });
          }
          list(c).push({ k: 'image', copy: true, box, full, src: 'canvas', alpha: this.globalAlpha, clipped: depth(this) > 0 });
        } else {
          const url = String(src.currentSrc || src.src || '');
          const known = window.__faceBoxes && window.__faceBoxes[url];
          const faces = known && sw0 && sh0 ? known.map((f) => map([f[0] * sw0, f[1] * sh0, f[2] * sw0, f[3] * sh0])) : undefined;
          list(c).push({ k: 'image', box, full, src: (url || String(src.tagName || '')).slice(-70), brand: brandOf(url), faces, alpha: this.globalAlpha, clipped: depth(this) > 0 });
        }
      }
    } catch (e) { /* never break the page */ }
    return drawImage.call(this, src, ...a);
  };

  const wipe = (ctx, x, y, w, h, style) => {
    try {
      const c = ctx.canvas;
      if (!isEl(c)) return;
      const b = corners(ctx.getTransform(), x, y, x + w, y + h);
      if (b[0] <= 1 && b[1] <= 1 && b[2] >= c.width - 1 && b[3] >= c.height - 1 && (style === null || (ctx.globalAlpha >= 0.99 && opaque(style)))) OPS.set(c, []);
    } catch (e) { /* never break the page */ }
  };
  P.clearRect = function (x, y, w, h) { wipe(this, x, y, w, h, null); return clearRect.call(this, x, y, w, h); };
  P.fillRect = function (x, y, w, h) { wipe(this, x, y, w, h, this.fillStyle); return fillRect.call(this, x, y, w, h); };

  for (const prop of ['width', 'height']) {
    const d = Object.getOwnPropertyDescriptor(HTMLCanvasElement.prototype, prop);
    Object.defineProperty(HTMLCanvasElement.prototype, prop, { configurable: true, enumerable: d.enumerable, get() { return d.get.call(this); }, set(v) { OPS.set(this, []); d.set.call(this, v); } });
  }

  // A logo's ground, read off the finished picture just outside its box: light, and calm (a plate).
  const ground = (c, b) => {
    try {
      const ctx = c.getContext('2d');
      const h = b[3] - b[1];
      const pad = Math.max(2, Math.round(h * 0.12));
      const lin = (v) => { const q = v / 255; return q <= 0.03928 ? q / 12.92 : ((q + 0.055) / 1.055) ** 2.4; };
      const ls = [];
      const at = (x, y) => {
        if (x < 0 || y < 0 || x >= c.width || y >= c.height) return;
        const p = getImageData.call(ctx, Math.round(x), Math.round(y), 1, 1).data;
        ls.push(0.2126 * lin(p[0]) + 0.7152 * lin(p[1]) + 0.0722 * lin(p[2]));
      };
      const step = Math.max(1, (b[2] - b[0]) / 16);
      for (let x = b[0]; x <= b[2]; x += step) { at(x, b[1] - pad); at(x, b[3] + pad); }
      for (let y = b[1]; y <= b[3]; y += Math.max(1, h / 6)) { at(b[0] - pad, y); at(b[2] + pad, y); }
      if (!ls.length) return null;
      const mean = ls.reduce((s, v) => s + v, 0) / ls.length;
      return { mean, spread: Math.sqrt(ls.reduce((s, v) => s + (v - mean) ** 2, 0) / ls.length) };
    } catch (e) { return null; }
  };

  // What is wrong with the picture now on canvas c, measured in the design's own pixels
  // (nominal size, e.g. 1080 x 1350, whatever scale it was drawn at).
  window.__canvasIssues = (c, W, H, o = {}) => {
    const k = c.width / W;
    const ops = (OPS.get(c) || []).map((op, i) => ({ ...op, i, b: op.box.map((v) => v / k), s: op.size / k, base: op.base !== undefined ? op.base / k : undefined, faces: op.faces ? op.faces.map((f) => f.map((v) => v / k)) : undefined }));
    const out = analyse(ops, W, H, o);
    if (o.logos !== false) {
      for (const im of ops.filter((x) => x.k === 'image' && x.brand && x.alpha >= 0.98)) {
        const g = ground(c, im.box);
        if (!g) continue;
        if (g.mean < 0.55) out.issues.push(['logo-ground', im.brand + ' on a dark ground (' + Math.round(g.mean * 100) + '% light)']);
        else if (g.spread > 0.12) out.issues.push(['logo-ground', im.brand + ' on a busy ground']);
      }
    }
    return { ...out, texts: out.stats.texts, pictures: out.stats.pictures };
  };
})();`;
