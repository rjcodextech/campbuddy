// Admin / manager → Event → Social media (partials/social-media.blade.php).
// CampBuddy's own posts about an event (CampBuddy talking to the people going
// to it), drawn on canvases from App\Support\SocialKit::data().
//
// Rules the drawing keeps:
//   - only the event's brand colours (main, accent, text, light) and mixes of
//     them — no other colour, not even pure white: light text is the "light"
//     colour, the QR is "text" on "light";
//   - every card carries the event's logo, CampBuddy's mark and a QR to the
//     event in CampBuddy, tagged with the event and the post or role
//     (utm_content);
//   - every event looks different: its slug seeds the shapes, their corner
//     and the starting style;
//   - nothing overlaps: content that doesn't fit is split over several cards
//     (1/3, 2/3…), shapes stay out of the text column;
//   - print ready: a 6× export (6480 px wide) stamped 600 DPI, with every
//     word and code at least 80 px (at 1080) inside the edge;
//   - the social design guideline (campbuddy-docs spec/25): the headline
//     anchored under the header, the site's art never under words, no word
//     under 24 px, "Swipe" on the first card of a carousel.

import QRCode from 'qrcode-generator';
import { zipStore } from './zip-store.js';
import { withPngDpi } from './attendee/png-dpi.js';

const SIZES = { '1080x1350': [1080, 1350], '1080x1080': [1080, 1080], '1080x1920': [1080, 1920] };
// Print: six times the pixels (6480 px wide), stamped 600 DPI — 10.8 inches wide, sharp enough for a standee.
const QUALITY = { social: { scale: 1, dpi: 72 }, print: { scale: 6, dpi: 600 } };
// The scale being drawn at: artwork is prepared at this many times its card size, so print stays sharp.
let RES = 1;
const DEFAULT_FONT = '"Inter", "Segoe UI", system-ui, -apple-system, sans-serif';
const DEFAULT_SERIF = '"Playfair Display", Georgia, "Times New Roman", serif';
// Text and headline fonts: CampBuddy's, or the WordCamp site's own (useSiteFonts()).
let FONT = DEFAULT_FONT;
let SERIF = DEFAULT_SERIF;
let HEAD = null;

// The WordCamp site's artwork (setSiteArt()): every ticked piece, by kind…
const SITE_ART = { pattern: [], illustration: [], photo: [] };
// …and the pieces the card being drawn uses (useArt()): each post and each
// person takes the next one in turn, so every piece of the site's artwork is used.
const ART = { pattern: null, illustration: null, photo: null };

/** Sets the artwork for card number `index` (a post's place on the page, a person's in the list). */
export function useArt(index = 0) {
  for (const kind of ['pattern', 'illustration', 'photo']) {
    const list = SITE_ART[kind];
    ART[kind] = list.length ? list[Math.abs(index) % list.length] : null;
  }
}
const PEOPLE_DESIGNS = ['classic', 'polaroid', 'badge', 'split', 'minimal', 'spotlight', 'arch'];
const MOTIFS = ['rings', 'dots', 'stripes', 'triangles', 'waves', 'arcs', 'blocks', 'plus'];
const TEXTURES = ['circle', 'diamond', 'arc', 'bars'];
// The side margin for any word, QR or logo (the templates keep 80–100 px).
const SAFE = 80;
const PEOPLE_PAGE = 8;

// ---- Colours --------------------------------------------------------------

function hexToRgb(hex) {
  const n = parseInt(String(hex).slice(1), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function rgbToHex([r, g, b]) {
  return `#${[r, g, b].map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('')}`;
}

function rgbToHsl([r, g, b]) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const l = (max + min) / 2;
  if (max === min) return [0, 0, l];
  const d = max - min;
  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
  let h;
  if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
  else if (max === g) h = (b - r) / d + 2;
  else h = (r - g) / d + 4;
  return [h * 60, s, l];
}

function luminance(rgb) {
  const [r, g, b] = rgb.map((v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a, b) {
  const [la, lb] = [luminance(hexToRgb(a)), luminance(hexToRgb(b))].sort((x, y) => y - x);
  return (la + 0.05) / (lb + 0.05);
}

/** The light or the dark brand colour, whichever reads better on `background`. */
export function textOn(background, dark = '#231f20', light = '#ffffff') {
  return contrast(background, dark) >= contrast(background, light) ? dark : light;
}

/** `hex` mixed toward `toward` by `amount` (0–1) — still the brand's colours. */
export function mix(hex, toward, amount) {
  const a = hexToRgb(hex);
  const b = hexToRgb(toward);
  return rgbToHex(a.map((v, i) => v + (b[i] - v) * amount));
}

function alpha(hex, a) {
  const [r, g, b] = hexToRgb(hex);
  return `rgba(${r},${g},${b},${a})`;
}

/**
 * A full palette from the logo's pixels: main (its strongest real colour,
 * dark enough for light text), accent (the next distinct hue, or a lighter
 * tone of the main one), text (the logo's darkest colour, or the main one
 * taken near black) and light (the main one taken near white).
 * Null when the logo has no real colour.
 */
export function paletteFromPixels(data) {
  const buckets = new Map();
  let darkest = null;
  for (let i = 0; i < data.length; i += 4) {
    if (data[i + 3] < 200) continue;
    const rgb = [data[i], data[i + 1], data[i + 2]];
    const [h, s, l] = rgbToHsl(rgb);
    if (l < 0.22 && s > 0.15 && (!darkest || l < darkest.l)) darkest = { rgb, l };
    if (s < 0.3 || l < 0.12 || l > 0.9) continue;
    const key = Math.round(h / 30) % 12;
    const b = buckets.get(key) ?? { w: 0, r: 0, g: 0, bl: 0 };
    b.w += s;
    b.r += rgb[0] * s; b.g += rgb[1] * s; b.bl += rgb[2] * s;
    buckets.set(key, b);
  }
  const ranked = [...buckets.entries()].sort((a, b) => b[1].w - a[1].w);
  if (ranked.length === 0) return null;
  const avg = (b) => [b.r / b.w, b.g / b.w, b.bl / b.w];

  let main = avg(ranked[0][1]);
  for (let i = 0; i < 6 && luminance(main) > 0.2; i++) main = main.map((v) => v * 0.82);
  const primary = rgbToHex(main);
  const second = ranked.find(([key]) => Math.min(Math.abs(key - ranked[0][0]), 12 - Math.abs(key - ranked[0][0])) >= 2);
  const secondary = second ? rgbToHex(avg(second[1])) : mix(primary, '#ffffff', 0.45);
  const ink = darkest ? rgbToHex(darkest.rgb) : mix(primary, '#000000', 0.78);

  return readable({ primary, secondary, ink: luminance(hexToRgb(ink)) < 0.05 ? ink : mix(primary, '#000000', 0.8), paper: mix(primary, '#ffffff', 0.94) });
}

/** WCAG contrast ratio between two colours (1–21). */
export function contrastRatio(a, b) {
  return contrast(a, b);
}

/**
 * Keeps a palette readable: the text colour on the light one at least 7:1,
 * the light colour on the main one at least 4.5:1 — by taking the text
 * colour darker or the main colour deeper, never by adding a colour.
 */
export function readable(palette) {
  const p = { ...palette };
  for (let i = 0; i < 12 && contrast(p.ink, p.paper) < 7; i++) p.ink = mix(p.ink, '#000000', 0.2);
  for (let i = 0; i < 12 && contrast(p.paper, p.primary) < 4.5; i++) p.primary = mix(p.primary, p.ink, 0.12);
  // Labels sit on the accent colour in the text or the light colour, whichever reads better.
  const label = () => Math.max(contrast(p.ink, p.secondary), contrast(p.paper, p.secondary));
  const towards = luminance(hexToRgb(p.secondary)) > 0.18 ? p.paper : p.ink;
  for (let i = 0; i < 12 && label() < 4.5; i++) p.secondary = mix(p.secondary, towards, 0.12);
  return p;
}

/**
 * A palette from the WordCamp site's own colours: main = its strongest
 * colour dark enough for light text, accent = the next distinct hue, text =
 * its darkest colour, light = its lightest tinted colour. Null when the site
 * has fewer than two real colours.
 */
export function paletteFromSite(colors) {
  const list = (colors ?? []).map((c) => {
    const rgb = hexToRgb(c.hex);
    const [h, s, l] = rgbToHsl(rgb);
    return { hex: c.hex.toLowerCase(), h, s, l, lum: luminance(rgb) };
  });
  const chroma = list.filter((c) => c.s >= 0.35 && c.l >= 0.15 && c.l <= 0.75);
  if (chroma.length < 2 && list.length < 3) return null;
  if (chroma.length === 0) return null;

  const primaryPick = [...chroma].filter((c) => c.lum <= 0.25).sort((a, b) => b.s - a.s)[0] ?? [...chroma].sort((a, b) => b.s - a.s)[0];
  let primary = primaryPick.hex;
  for (let i = 0; i < 6 && luminance(hexToRgb(primary)) > 0.2; i++) primary = mix(primary, '#000000', 0.15);
  const hueGap = (a, b) => Math.min(Math.abs(a - b), 360 - Math.abs(a - b));
  const second = chroma.filter((c) => c.hex !== primaryPick.hex && hueGap(c.h, primaryPick.h) >= 35).sort((a, b) => b.s - a.s)[0];
  const darks = list.filter((c) => c.lum < 0.06).sort((a, b) => (b.s > 0.1) - (a.s > 0.1) || a.lum - b.lum);
  const lights = list.filter((c) => c.lum > 0.8).sort((a, b) => (b.s > 0.05) - (a.s > 0.05) || b.lum - a.lum);

  const palette = readable({
    primary,
    secondary: second ? second.hex : mix(primary, '#ffffff', 0.45),
    ink: darks[0]?.hex ?? mix(primary, '#000000', 0.8),
    paper: lights[0]?.hex ?? mix(primary, '#ffffff', 0.94),
  });
  // Every other real colour of the site, for decoration (most saturated first).
  const used = new Set([primaryPick.hex, second?.hex, palette.primary, palette.secondary]);
  palette.extras = [...chroma].filter((c) => !used.has(c.hex)).sort((a, b) => b.s - a.s).slice(0, 4).map((c) => c.hex);
  return palette;
}

/** The colours a card may use: the four brand colours, the site's other colours, and tones between them. */
function paletteOf(colors) {
  const { primary, secondary, ink, paper } = colors;
  const extras = (colors.extras ?? []).filter(Boolean);
  return {
    primary, secondary, ink, paper,
    // The site's other colours (saffron, indigo…), for shapes and role tones; the brand's when there are none.
    extras: extras.length ? extras : [secondary, mix(primary, secondary, 0.5)],
    deep: mix(primary, ink, 0.4),
    soft: mix(primary, paper, 0.86),
    accentSoft: mix(secondary, paper, 0.7),
    onPrimary: textOn(primary, ink, paper),
    onSecondary: textOn(secondary, ink, paper),
  };
}

// ---- Every event, its own look ---------------------------------------------

/** A small, seeded random: the same event always draws the same shapes. */
export function seeded(seed) {
  let a = (Number(seed) >>> 0) || 1;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** What differs between events: the shapes, their corner, the starting style. */
export function eventLook(seed) {
  const r = seeded(seed);
  const pick = (list) => list[Math.floor(r() * list.length)];
  return {
    motif: pick(MOTIFS),
    texture: pick(TEXTURES),
    corner: r() < 0.5 ? 'right' : 'left',
    tilt: r() < 0.5 ? -1 : 1,
    style: pick(POST_DESIGNS),
    design: pick(PEOPLE_DESIGNS),
    rnd: r,
  };
}

// ---- Drawing helpers ------------------------------------------------------

const images = new Map();

function loadImage(url) {
  if (!url) return Promise.resolve(null);
  if (!images.has(url)) {
    images.set(url, new Promise((resolve) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      // A failed image isn't remembered: the next redraw tries again.
      // (A Gravatar asked with d=404 that fails simply has no picture: that answer is kept.)
      img.onerror = () => { if (!url.includes('d=404')) images.delete(url); resolve(null); };
      img.src = url;
    }));
  }
  return images.get(url);
}

const lightness = new WeakMap();

/**
 * Whether a logo is mostly light (white lettering on a transparent
 * background): such a logo sits on a dark tile, or it would vanish.
 */
export function isLightImage(img) {
  if (!img) return false;
  if (lightness.has(img)) return lightness.get(img);
  let light = false;
  try {
    const c = document.createElement('canvas');
    c.width = 60; c.height = 60;
    const ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0, 60, 60);
    const d = ctx.getImageData(0, 0, 60, 60).data;
    let sum = 0;
    let n = 0;
    for (let i = 0; i < d.length; i += 4) {
      if (d[i + 3] < 128) continue;
      sum += luminance([d[i], d[i + 1], d[i + 2]]);
      n++;
    }
    light = n > 0 && sum / n > 0.6;
  } catch {
    light = false;
  }
  lightness.set(img, light);
  return light;
}

const trims = new WeakMap();

/**
 * The image without its see-through margins (a logo or a sprig drawn small on
 * a big empty canvas), so it fills the box it is given. Solid images and ones
 * with little margin come back as they are.
 */
export function trimmed(img) {
  if (!img) return img;
  if (trims.has(img)) return trims.get(img);
  let out = img;
  try {
    const w = img.naturalWidth || img.width;
    const h = img.naturalHeight || img.height;
    const k = Math.min(1, 400 / Math.max(w, h));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * k));
    c.height = Math.max(1, Math.round(h * k));
    const ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0, c.width, c.height);
    const d = ctx.getImageData(0, 0, c.width, c.height).data;
    let [x0, y0, x1, y1] = [c.width, c.height, -1, -1];
    for (let y = 0; y < c.height; y++) {
      for (let x = 0; x < c.width; x++) {
        if (d[(y * c.width + x) * 4 + 3] <= 12) continue;
        if (x < x0) x0 = x;
        if (x > x1) x1 = x;
        if (y < y0) y0 = y;
        if (y > y1) y1 = y;
      }
    }
    // Pad by one sample so nothing is clipped by the rounding.
    const [sx, sy] = [Math.max(0, (x0 - 1) / k), Math.max(0, (y0 - 1) / k)];
    const [sw, sh] = [Math.min(w - sx, (x1 - x0 + 3) / k), Math.min(h - sy, (y1 - y0 + 3) / k)];
    if (x1 >= 0 && sw * sh < w * h * 0.85) {
      const t = document.createElement('canvas');
      t.width = Math.max(1, Math.round(sw));
      t.height = Math.max(1, Math.round(sh));
      t.getContext('2d').drawImage(img, sx, sy, sw, sh, 0, 0, t.width, t.height);
      // recolour() and duotone() key their caches by src.
      t.src = `${img.src}#trimmed`;
      out = t;
    }
  } catch {
    out = img;
  }
  trims.set(img, out);
  return out;
}

/** A tile for a logo: the light colour, or the dark one when the logo itself is light. */
function logoTile(ctx, img, x, y, w, h, pal, r = 24) {
  ctx.fillStyle = isLightImage(img) ? pal.ink : pal.paper;
  roundRect(ctx, x, y, w, h, r);
  ctx.fill();
  if (img) drawContain(ctx, trimmed(img), x + w * 0.07, y + h * 0.1, w * 0.86, h * 0.8);
}

function roundRect(ctx, x, y, w, h, r) {
  const rr = Math.min(r, w / 2, h / 2);
  ctx.beginPath();
  ctx.moveTo(x + rr, y);
  ctx.arcTo(x + w, y, x + w, y + h, rr);
  ctx.arcTo(x + w, y + h, x, y + h, rr);
  ctx.arcTo(x, y + h, x, y, rr);
  ctx.arcTo(x, y, x + w, y, rr);
  ctx.closePath();
}

/** Word-wrapped lines at the largest size (down to `min`) where every line fits. */
function fitText(ctx, text, maxWidth, size, min, maxLines, weight = 800, family = FONT) {
  for (let s = size; s >= min; s -= 2) {
    ctx.font = `${weight} ${s}px ${family}`;
    const lines = wrap(ctx, text, maxWidth);
    if (lines.length <= maxLines && lines.every((l) => ctx.measureText(l).width <= maxWidth)) return { lines, size: s };
  }
  ctx.font = `${weight} ${min}px ${family}`;
  const lines = wrap(ctx, text, maxWidth);
  const kept = lines.slice(0, maxLines);
  if (lines.length > maxLines) kept[maxLines - 1] = `${kept[maxLines - 1]}…`;
  return { lines: kept.map((l) => ellipsize(ctx, l, maxWidth)), size: min };
}

function ellipsize(ctx, text, maxWidth) {
  if (ctx.measureText(text).width <= maxWidth) return text;
  let t = text.replace(/…$/, '');
  while (t.length > 1 && ctx.measureText(`${t}…`).width > maxWidth) t = t.slice(0, -1);
  return `${t}…`;
}

function wrap(ctx, text, maxWidth) {
  const lines = [];
  for (const paragraph of String(text ?? '').split('\n')) {
    let line = '';
    for (const word of paragraph.split(/\s+/).filter(Boolean)) {
      const next = line ? `${line} ${word}` : word;
      if (ctx.measureText(next).width <= maxWidth || !line) line = next;
      else {
        lines.push(line);
        line = word;
      }
    }
    if (line) lines.push(line);
  }
  return lines;
}

function drawContain(ctx, img, x, y, w, h) {
  const ratio = Math.min(w / img.width, h / img.height);
  const dw = img.width * ratio;
  const dh = img.height * ratio;
  ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
}

/** Fills the box, cropping the rest; `focusY` 0 keeps the top, 0.5 the middle (faces sit high in a photo). */
function drawCover(ctx, img, x, y, w, h, focusY = 0.5) {
  const ratio = Math.max(w / img.width, h / img.height);
  const dw = img.width * ratio;
  const dh = img.height * ratio;
  ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) * focusY, dw, dh);
}

function circlePhoto(ctx, img, cx, cy, r, ring, ringColor, fill, ink, name) {
  if (ring) {
    ctx.fillStyle = ringColor;
    ctx.beginPath(); ctx.arc(cx, cy, r + ring, 0, Math.PI * 2); ctx.fill();
  }
  ctx.save();
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.clip();
  if (img) drawCover(ctx, img, cx - r, cy - r, 2 * r, 2 * r);
  else {
    ctx.fillStyle = fill;
    ctx.fillRect(cx - r, cy - r, 2 * r, 2 * r);
    ctx.fillStyle = ink;
    ctx.font = `800 ${Math.round(r * 0.8)}px ${FONT}`;
    ctx.textAlign = 'center';
    ctx.fillText(initials(name), cx, cy + r * 0.3);
    ctx.textAlign = 'left';
  }
  ctx.restore();
}

function initials(name) {
  return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('') || '?';
}

/** A QR in the brand's text colour on its light colour (dark on light, as scanners need). */
function drawQr(ctx, url, x, y, size, dark, light) {
  const qr = QRCode(0, 'M');
  qr.addData(url);
  qr.make();
  const count = qr.getModuleCount();
  const cell = size / (count + 4);
  ctx.fillStyle = light;
  roundRect(ctx, x, y, size, size, size * 0.08);
  ctx.fill();
  ctx.fillStyle = dark;
  for (let r = 0; r < count; r++) {
    for (let c = 0; c < count; c++) {
      if (qr.isDark(r, c)) ctx.fillRect(x + (c + 2) * cell, y + (r + 2) * cell, Math.ceil(cell), Math.ceil(cell));
    }
  }
}

/** The card's QR link: the event in CampBuddy, tagged with this post or role. */
export function qrLink(base, content) {
  const url = new URL(base, 'https://campbuddy.club');
  url.searchParams.set('utm_content', content);
  return url.toString();
}

// The event's mark, small and tilted, repeated very faintly across a background.
function watermark(ctx, mark, W, H, opacity = 0.06, step = 220) {
  if (!mark) return;
  ctx.save();
  ctx.globalAlpha = opacity;
  for (let row = 0, y = -40; y < H + step; row++, y += step) {
    for (let x = row % 2 ? step / 2 - 40 : -40; x < W + step; x += step) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(-0.26);
      drawContain(ctx, mark, -42, -42, 84, 84);
      ctx.restore();
    }
  }
  ctx.restore();
}

const recolours = new Map();

/**
 * The site's artwork in one brand colour, keeping only its shape: a
 * transparent image keeps its alpha; an opaque one (a JPEG tile) keeps its
 * lines (dark lines on light, or light on dark). So artwork never brings in
 * a colour of its own.
 */
export function recolour(img, color) {
  const max = Math.min(4000, 900 * RES);
  const key = `${img.src}|${color}|${max}`;
  if (recolours.has(key)) return recolours.get(key);
  const scale = Math.min(1, max / Math.max(img.width, img.height));
  const c = document.createElement('canvas');
  c.width = Math.max(1, Math.round(img.width * scale));
  c.height = Math.max(1, Math.round(img.height * scale));
  const ctx = c.getContext('2d');
  ctx.drawImage(img, 0, 0, c.width, c.height);
  try {
    const data = ctx.getImageData(0, 0, c.width, c.height);
    const d = data.data;
    let opaque = 0;
    let sum = 0;
    for (let i = 0; i < d.length; i += 4) {
      if (d[i + 3] > 240) opaque++;
      sum += luminance([d[i], d[i + 1], d[i + 2]]);
    }
    const n = d.length / 4;
    const solid = opaque / n > 0.9;
    const lightImage = sum / n > 0.5;
    const [r, g, b] = hexToRgb(color);
    const covers = new Float32Array(n);
    for (let i = 0, k = 0; i < d.length; i += 4, k++) {
      const lum = luminance([d[i], d[i + 1], d[i + 2]]);
      covers[k] = solid ? (lightImage ? 1 - lum : lum) : d[i + 3] / 255;
    }
    // Faint line art (a pale skyline) is stretched to full strength: its strongest strokes become solid.
    const sorted = Float32Array.from(covers).sort();
    const top = Math.max(0.05, sorted[Math.floor(n * 0.98)]);
    const floor = solid ? sorted[Math.floor(n * 0.5)] : 0;
    for (let i = 0, k = 0; i < d.length; i += 4, k++) {
      d[i] = r; d[i + 1] = g; d[i + 2] = b;
      d[i + 3] = Math.round(Math.max(0, Math.min(1, (covers[k] - floor) / Math.max(0.05, top - floor))) * 255);
    }
    ctx.putImageData(data, 0, 0);
  } catch {
    // Not readable (shouldn't happen through the proxy): no artwork rather than a foreign colour.
    return null;
  }
  recolours.set(key, c);
  return c;
}

/** A photo in two brand tones (dark to light), so it reads as the brand. */
export function duotone(img, dark, light, maxSide = 1400) {
  const max = Math.min(4000, maxSide * RES);
  const key = `${img.src}|duo|${dark}|${light}|${max}`;
  if (recolours.has(key)) return recolours.get(key);
  const scale = Math.min(1, max / Math.max(img.width, img.height));
  const c = document.createElement('canvas');
  c.width = Math.round(img.width * scale);
  c.height = Math.round(img.height * scale);
  const ctx = c.getContext('2d');
  ctx.drawImage(img, 0, 0, c.width, c.height);
  try {
    const data = ctx.getImageData(0, 0, c.width, c.height);
    const d = data.data;
    const a = hexToRgb(dark);
    const b = hexToRgb(light);
    for (let i = 0; i < d.length; i += 4) {
      const t = (0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]) / 255;
      d[i] = a[0] + (b[0] - a[0]) * t; d[i + 1] = a[1] + (b[1] - a[1]) * t; d[i + 2] = a[2] + (b[2] - a[2]) * t;
    }
    ctx.putImageData(data, 0, 0);
  } catch {
    return null;
  }
  recolours.set(key, c);
  return c;
}

const artInfos = new WeakMap();

/**
 * What a piece of artwork is: `mono` (one colour, like a white skyline or a
 * black sprig: it is drawn in a brand colour) or in colours of its own (the
 * site's terracotta skyline, a sepia collage: drawn as it is), `opaque` (no
 * transparency) and its `aspect`.
 */
export function artInfo(img) {
  if (artInfos.has(img)) return artInfos.get(img);
  const info = { mono: false, opaque: false, aspect: img.width / img.height, lum: 0.5 };
  try {
    const c = document.createElement('canvas');
    c.width = c.height = 96;
    const ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0, 96, 96);
    const d = ctx.getImageData(0, 0, 96, 96).data;
    let n = 0;
    let solid = 0;
    let sat = 0;
    let lum = 0;
    let lum2 = 0;
    for (let i = 0; i < d.length; i += 4) {
      if (d[i + 3] > 240) solid++;
      if (d[i + 3] < 128) continue;
      const rgb = [d[i], d[i + 1], d[i + 2]];
      const l = luminance(rgb);
      sat += rgbToHsl(rgb)[1];
      lum += l;
      lum2 += l * l;
      n++;
    }
    info.opaque = solid / (d.length / 4) > 0.9;
    if (n) {
      info.lum = lum / n;
      const spread = Math.sqrt(Math.max(0, lum2 / n - (lum / n) ** 2));
      info.mono = !info.opaque && sat / n < 0.15 && spread < 0.12;
    }
  } catch {
    info.mono = true;
  }
  artInfos.set(img, info);
  return info;
}

/** The artwork as it is drawn on `under`: its own colours, or (one-colour art) the brand colour that reads there. */
function artFor(img, pal, under) {
  const info = artInfo(img);
  // Its own colours, unless they'd vanish on this ground (a cream skyline on a cream band).
  if (!info.mono && Math.abs(info.lum - luminance(hexToRgb(under))) >= 0.3) return img;
  return recolour(img, textOn(under, pal.primary, pal.paper) === pal.primary ? pal.primary : pal.paper);
}

/**
 * The site's pattern as a crisp strip (a border, a divider): in its own
 * colours on light ground, or one-colour art in the brand colour that reads.
 */
function patternStrip(ctx, img, pal, under, x, y, w, h) {
  const art = artFor(img, pal, under);
  if (!art) return;
  const t = document.createElement('canvas');
  const scale = ctx.getTransform().a || 1;
  const k = (h * scale) / art.height;
  t.width = Math.max(1, Math.round(art.width * k));
  t.height = Math.max(1, Math.round(h * scale));
  const tc = t.getContext('2d');
  tc.imageSmoothingQuality = 'high';
  tc.drawImage(art, 0, 0, t.width, t.height);
  ctx.save();
  ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
  ctx.translate(x, y);
  ctx.scale(1 / scale, 1 / scale);
  ctx.fillStyle = ctx.createPattern(t, 'repeat-x');
  ctx.fillRect(0, 0, w * scale, h * scale);
  ctx.restore();
}

/** The site's pattern, recoloured and tiled at `tile` px tall, filling a box. */
function fillWithPattern(ctx, img, color, x, y, w, h, tile = 130, opacity = 1) {
  const art = recolour(img, color);
  if (!art) return false;
  const t = document.createElement('canvas');
  const k = tile / art.height;
  t.width = Math.max(1, Math.round(art.width * k));
  t.height = Math.max(1, Math.round(tile));
  t.getContext('2d').drawImage(art, 0, 0, t.width, t.height);
  ctx.save();
  ctx.globalAlpha = opacity;
  ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
  ctx.translate(x, y);
  ctx.fillStyle = ctx.createPattern(t, 'repeat');
  ctx.fillRect(0, 0, w, h);
  ctx.restore();
  return true;
}

/**
 * The event's motif, inside its own box (top corner, beside the logo, never
 * behind words): rings, dots, stripes, triangles, waves, arcs, blocks or
 * plus signs, in the accent and light colours.
 */
function drawMotif(ctx, kind, x, y, w, h, c1, c2, rnd) {
  ctx.save();
  ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
  ctx.lineWidth = 6;
  const cx = x + w * 0.62;
  const cy = y + h * 0.5;
  switch (kind) {
    case 'rings':
      for (let i = 0; i < 5; i++) { ctx.strokeStyle = i % 2 ? c2 : c1; ctx.beginPath(); ctx.arc(cx, cy, 30 + i * 26, 0, Math.PI * 2); ctx.stroke(); }
      break;
    case 'dots':
      for (let i = 0; i < 7; i++) for (let j = 0; j < 5; j++) { ctx.fillStyle = (i + j) % 3 ? c1 : c2; ctx.beginPath(); ctx.arc(x + 30 + i * 34, y + 24 + j * 30, 6, 0, Math.PI * 2); ctx.fill(); }
      break;
    case 'stripes':
      for (let i = -h; i < w; i += 30) { ctx.strokeStyle = (i / 30) % 2 ? c1 : c2; ctx.beginPath(); ctx.moveTo(x + i, y + h); ctx.lineTo(x + i + h, y); ctx.stroke(); }
      break;
    case 'triangles':
      for (let i = 0; i < 6; i++) {
        const tx = x + 20 + (i % 3) * (w / 3);
        const ty = y + 20 + Math.floor(i / 3) * (h / 2);
        const s = 40 + rnd() * 30;
        ctx.fillStyle = i % 2 ? c1 : c2;
        ctx.beginPath(); ctx.moveTo(tx, ty + s); ctx.lineTo(tx + s / 2, ty); ctx.lineTo(tx + s, ty + s); ctx.closePath(); ctx.fill();
      }
      break;
    case 'waves':
      for (let k = 0; k < 4; k++) {
        ctx.strokeStyle = k % 2 ? c1 : c2;
        ctx.beginPath();
        for (let i = 0; i <= w; i += 6) { const yy = y + 30 + k * 34 + Math.sin(i / 26) * 12; if (i === 0) ctx.moveTo(x + i, yy); else ctx.lineTo(x + i, yy); }
        ctx.stroke();
      }
      break;
    case 'arcs':
      for (let i = 0; i < 4; i++) { ctx.strokeStyle = i % 2 ? c2 : c1; ctx.lineWidth = 14; ctx.beginPath(); ctx.arc(x + w, y + h, 50 + i * 40, Math.PI, Math.PI * 1.5); ctx.stroke(); }
      break;
    case 'blocks':
      for (let i = 0; i < 4; i++) for (let j = 0; j < 3; j++) { if ((i + j) % 2) continue; ctx.fillStyle = (i + j) % 4 ? c1 : c2; roundRect(ctx, x + 20 + i * 54, y + 16 + j * 54, 44, 44, 8); ctx.fill(); }
      break;
    default: // plus
      ctx.lineWidth = 8;
      for (let i = 0; i < 5; i++) for (let j = 0; j < 3; j++) {
        if ((i + j) % 2) continue;
        const px = x + 30 + i * 46;
        const py = y + 30 + j * 46;
        ctx.strokeStyle = (i + j) % 4 ? c1 : c2;
        ctx.beginPath(); ctx.moveTo(px - 12, py); ctx.lineTo(px + 12, py); ctx.moveTo(px, py - 12); ctx.lineTo(px, py + 12); ctx.stroke();
      }
  }
  ctx.restore();
}

/** One big, faint shape across the background (behind everything, ≤ 10 % strength). */
function drawTexture(ctx, kind, W, H, color, corner) {
  ctx.save();
  ctx.fillStyle = color;
  ctx.strokeStyle = color;
  const left = corner === 'left';
  const x = left ? W * 0.05 : W * 0.95;
  switch (kind) {
    case 'diamond':
      ctx.translate(x, H * 0.62); ctx.rotate(Math.PI / 4); ctx.fillRect(-W * 0.32, -W * 0.32, W * 0.64, W * 0.64);
      break;
    case 'arc':
      ctx.lineWidth = 70; ctx.beginPath(); ctx.arc(x, H * 0.7, W * 0.42, 0, Math.PI * 2); ctx.stroke();
      break;
    case 'bars':
      for (let i = 0; i < 5; i++) ctx.fillRect(left ? -40 + i * 46 : W - 220 + i * 46, H * 0.35, 22, H * 0.4);
      break;
    default:
      ctx.beginPath(); ctx.arc(x, H * 0.66, W * 0.38, 0, Math.PI * 2); ctx.fill();
  }
  ctx.restore();
}

function pill(ctx, text, x, y, { bg, fg, size = 30, pad = 26, height = 60, align = 'left' }) {
  ctx.font = `800 ${size}px ${FONT}`;
  const w = ctx.measureText(text).width + pad * 2;
  const left = align === 'center' ? x - w / 2 : (align === 'right' ? x - w : x);
  ctx.fillStyle = bg;
  roundRect(ctx, left, y, w, height, height / 2);
  ctx.fill();
  ctx.fillStyle = fg;
  ctx.textAlign = 'left';
  ctx.fillText(text, left + pad, y + height / 2 + size * 0.36);
  return w;
}

// ---- What a post shows ----------------------------------------------------

/** That day's talks (or all its sessions if there are few talks). */
export function sessionsOfDay(sessions, day) {
  const ofDay = (sessions ?? []).filter((s) => s.day === day);
  const talks = ofDay.filter((s) => s.speakers?.length);
  return talks.length >= 3 ? talks : ofDay;
}

export function todayCaption(kit, day, list) {
  const label = kit.schedule.days.find((d) => d.key === day)?.label ?? '';
  const lines = list.map((s) => `${s.time}  ${s.title}${s.speakers?.length ? ` (${s.speakers.join(', ')})` : ''}`);
  return `${label} at ${kit.event.name}:\n\n${lines.join('\n')}\n\nSave your picks and get a reminder before each talk in CampBuddy: ${qrLink(kit.event.qr, 'today')}\n\n${kit.event.hashtags} #CampBuddy`;
}

export function spotlightCaption(kit, s) {
  if (!s) return '';
  const who = s.speakers?.length ? ` with ${s.speakers.join(', ')}` : '';
  const where = s.track ? ` in ${s.track}` : '';
  return `Up next at ${kit.event.name}: "${s.title}"${who}, ${s.time}${where}.\n\nSave it in CampBuddy and get a reminder before it starts: ${qrLink(kit.event.qr, 'spotlight')}\n\n${kit.event.hashtags} #CampBuddy`;
}

const FEATURES = ['The full schedule, with reminders', 'People who share your interests', 'Your Contributor Day team', 'Deals and free tools for you'];

/**
 * Days until the event's first day, counted now at the venue (so a card
 * drawn or downloaded tomorrow says one day less); null once it has started.
 */
export function daysToGo(kit, now = new Date()) {
  const start = kit?.event?.starts;
  if (!start) return null;
  let today;
  try {
    today = new Intl.DateTimeFormat('en-CA', { timeZone: kit.event.timezone || undefined, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
  } catch {
    today = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
  }
  const days = Math.round((Date.parse(`${start}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)) / 86400000);
  return days > 0 ? days : null;
}

/** The drawable content of a post: kicker, headline, line, and a body. */
export function postContent(post, fields, kit, pick) {
  const base = { key: post.key, kicker: post.kicker ?? '', headline: fields.headline, line: fields.line, body: { type: 'none' } };

  switch (post.key) {
    case 'countdown': {
      const days = daysToGo(kit) ?? post.days;
      return { ...base, body: days ? { type: 'countdown', days } : { type: 'none' } };
    }
    case 'app':
      return { ...base, body: { type: 'features', items: FEATURES } };
    case 'speakers':
      return { ...base, body: { type: 'photos', people: kit.speakers.filter((p) => p.photo) } };
    case 'sponsors':
      return { ...base, body: { type: 'logos', logos: kit.sponsors } };
    case 'today': {
      const list = sessionsOfDay(kit.schedule.sessions, pick);
      const label = kit.schedule.days.find((d) => d.key === pick)?.label;
      return { ...base, kicker: label ?? base.kicker, body: { type: 'list', items: list.map((s) => ({ time: s.time, title: s.title, meta: [s.speakers?.join(', '), s.track].filter(Boolean).join(' · ') })) } };
    }
    case 'spotlight': {
      const s = kit.schedule.sessions.find((x) => String(x.id) === String(pick));
      return {
        ...base,
        headline: fields.headline || s?.title || '',
        line: fields.line || [s?.time, s?.track].filter(Boolean).join(' · '),
        body: s?.speakers?.length ? { type: 'session', photos: s.photos ?? [], speakers: s.speakers } : { type: 'none' },
      };
    }
    case 'contributor':
      return { ...base, body: kit.tables.length ? { type: 'tables', tables: kit.tables } : { type: 'none' } };
    default:
      return base;
  }
}

// ---- Layout ---------------------------------------------------------------
//
// Seven designs for the posts (10 Oct 2026), one system underneath: one
// message and one hero per card, the site's artwork large in the background
// (as wide as the card when it is a skyline or a banner, fitted when it is
// smaller), and a block at the foot with what the reader gets from scanning,
// the event, the QR and CampBuddy's mark. Faces and photos are never under
// words: a picture gets its own box and the words sit below it.

const POST_DESIGNS = ['classic', 'poster', 'frame', 'ticket', 'magazine', 'split', 'minimal'];

/** The grid every card shares: the block at the foot, the header, the column. */
function grid(W, H) {
  const square = H < 1100;
  const tall = H >= 1900;
  const blockH = Math.round(H * (square ? 0.25 : (tall ? 0.2 : 0.235)));
  return {
    W, H, square, tall, blockH,
    blockTop: H - blockH,
    x: SAFE,
    w: W - 2 * SAFE,
    headerTop: SAFE - 12,
    logo: square ? { w: 190, h: 104 } : { w: 230, h: 126 },
  };
}

/** What scanning the card gets the reader: the line beside the QR. */
const CTA = {
  'save-the-date': 'Plan your WordCamp in CampBuddy',
  countdown: 'Save your talks before the day',
  schedule: 'Scan to save your schedule',
  app: 'Scan to open CampBuddy',
  speakers: 'Save their talks, get a reminder',
  sponsors: 'See their deals for attendees',
  today: 'Save your picks, get reminders',
  spotlight: 'Get a reminder before it starts',
  contributor: 'Find your table in 3 questions',
  meet: 'Find people who share your interests',
  'thank-you': 'Keep your day as a PDF',
};

/** Posts that show the site's own photo of the place, when it has one. */
const PHOTO_POSTS = ['save-the-date', 'meet', 'thank-you'];

function isPicture(content) {
  if (['photos', 'session'].includes(content.body.type)) return true;
  return Boolean(ART.photo && PHOTO_POSTS.includes(content.key));
}

const ROWS = {
  list: (H) => (H >= 1900 ? 140 : 108),
  tables: (H) => (H >= 1900 ? 120 : 98),
  logos: (H) => (H >= 1900 ? 170 : 132),
  photos: (H) => (H >= 1900 ? 250 : 212),
};

function columns(type, H) {
  if (type === 'logos') return 3;
  if (type === 'photos') return H < 1100 ? 5 : 4;
  return 1;
}

function itemsKey(type) {
  return { list: 'items', tables: 'tables', logos: 'logos', photos: 'people' }[type];
}

/**
 * The cards a post becomes: lists, tables, photo and logo grids are split so
 * every card holds only what fits (1/3, 2/3…); anything else is one card.
 */
export function paginate(content, room, H) {
  const key = itemsKey(content.body.type);
  if (!key) return [content];
  const all = content.body[key] ?? [];
  if (!all.length) return [{ ...content, body: { type: 'none' } }];
  const cols = columns(content.body.type, H);
  const most = Math.max(1, Math.floor(Math.max(room, 0) / ROWS[content.body.type](H)) * cols);
  // As even as the grid allows, so the last card is never one lonely item.
  const perPage = Math.min(most, Math.ceil(Math.ceil(all.length / Math.ceil(all.length / most)) / cols) * cols);
  const pages = [];
  for (let i = 0; i < all.length; i += perPage) pages.push({ ...content, body: { ...content.body, [key]: all.slice(i, i + perPage) } });
  return pages;
}

/**
 * Where each design puts things: the background, an optional panel or ticket,
 * the area for the words (`area`), the colours of the words, and the block.
 */
function layoutFor(design, g, pal) {
  const { W, H, blockTop } = g;
  const base = {
    bg: pal.paper, ink: pal.ink, muted: alpha(pal.ink, 0.72), accent: pal.primary, bar: pal.secondary,
    art: { x: 0, y: 0, w: W, h: blockTop, on: pal.paper },
    head: { x: g.x, y: g.headerTop, w: g.w, onPicture: false },
    area: { x: g.x, y: g.headerTop + g.logo.h + (g.square ? 36 : 56), w: g.w, bottom: blockTop - (g.square ? 24 : 40) },
    block: { color: pal.primary, x0: 0, x1: W },
    panel: null, ticket: null, split: null, frameLine: false,
  };
  switch (design) {
    case 'poster':
      return {
        ...base, bg: pal.primary, ink: pal.paper, muted: alpha(pal.paper, 0.86), accent: pal.paper, bar: pal.secondary,
        art: { ...base.art, on: pal.primary },
        head: { ...base.head, onPicture: true },
        block: { color: pal.paper, x0: 0, x1: W },
      };
    case 'frame': {
      const m = g.square ? 28 : 40;
      const panel = { x: m, y: m, w: W - 2 * m, h: blockTop - 2 * m + 10 };
      return {
        ...base, bg: pal.primary,
        art: { ...base.art, on: pal.primary },
        panel,
        head: { x: panel.x + 36, y: panel.y + 30, w: panel.w - 72, onPicture: false },
        area: { x: panel.x + 36, y: panel.y + 30 + g.logo.h + (g.square ? 26 : 44), w: panel.w - 72, bottom: panel.y + panel.h - 34 },
        block: { color: mix(pal.primary, pal.ink, 0.35), x0: 0, x1: W },
      };
    }
    case 'ticket': {
      const m = g.square ? 30 : 44;
      const ticket = { x: m, y: m, w: W - 2 * m, h: H - 2 * m };
      return {
        ...base, bg: pal.deep,
        art: { x: 0, y: 0, w: W, h: H, on: pal.deep },
        ticket,
        head: { x: ticket.x + 40, y: ticket.y + 30, w: ticket.w - 80, onPicture: false },
        area: { x: ticket.x + 40, y: ticket.y + 30 + g.logo.h + (g.square ? 26 : 44), w: ticket.w - 80, bottom: blockTop - 34 },
        block: { color: pal.primary, x0: ticket.x, x1: ticket.x + ticket.w, bottom: ticket.y + ticket.h, ticket: true },
      };
    }
    case 'magazine': {
      // The artwork is the hero of the top: shown big, in its own colours, on a soft ground.
      const show = Math.round(blockTop * (g.square ? 0.4 : 0.44));
      return {
        ...base,
        art: { x: 0, y: 0, w: W, h: show, on: pal.soft, showcase: true },
        head: { ...base.head, onPicture: true },
        area: { x: g.x, y: show + (g.square ? 30 : 50), w: g.w, bottom: blockTop - (g.square ? 24 : 40) },
      };
    }
    case 'split': {
      const side = Math.round(W * 0.34);
      return {
        ...base,
        art: { x: 0, y: 0, w: side, h: blockTop, on: pal.primary },
        split: side,
        head: { x: side + 44, y: g.headerTop, w: W - side - 44 - SAFE, onPicture: false, logoInSide: true },
        area: { x: side + 44, y: g.headerTop + (g.square ? 96 : 120), w: W - side - 44 - SAFE, bottom: blockTop - (g.square ? 24 : 40) },
      };
    }
    case 'minimal':
      return {
        ...base, frameLine: true,
        art: { x: W * 0.4, y: blockTop * 0.45, w: W * 0.6, h: blockTop * 0.55, on: pal.paper, corner: true },
        block: { color: pal.paper, x0: 0, x1: W, line: true },
      };
    default:
      return base;
  }
}

/**
 * The site's artwork in the background of `box`: a wide piece (a skyline, a
 * banner) as wide as the box and standing on its foot; a smaller one fitted
 * into the corner; in two soft tones of the ground so the words always read.
 * `showcase` shows it in its own colours instead (Magazine). Without an
 * illustration, the pattern (or the photo) fills the box faintly. `free`
 * marks a box that is only the room left below the words: the art may fill it.
 */
function paintArtBackground(ctx, box, pal) {
  const on = box.on;
  const darkGround = textOn(on, pal.ink, pal.paper) === pal.paper;
  const strong = darkGround ? mix(on, pal.paper, 0.22) : mix(on, pal.primary, 0.3);
  const faint = darkGround ? mix(on, pal.ink, 0.12) : mix(on, pal.primary, 0.08);
  ctx.save();
  ctx.beginPath(); ctx.rect(box.x, box.y, box.w, box.h); ctx.clip();
  if (box.showcase) {
    ctx.fillStyle = on;
    ctx.fillRect(box.x, box.y, box.w, box.h);
  }
  const img = ART.illustration;
  if (img) {
    const info = artInfo(img);
    const art = box.showcase ? artFor(img, pal, on) : (info.mono || info.opaque ? recolour(img, strong) : duotone(img, strong, faint));
    if (art) {
      if (info.aspect >= 1.4 && !box.corner) {
        // Wide: the full width of the box, standing on its foot (or fitted when it would be too tall).
        const k = Math.min(box.w / art.width, (box.h * (box.showcase ? 0.86 : (box.free ? 0.95 : 0.7))) / art.height);
        const w = art.width * k;
        const h = art.height * k;
        ctx.drawImage(art, box.x + (box.w - w) / 2, box.showcase ? box.y + (box.h - h) / 2 + 30 : box.y + box.h - h, w, h);
      } else {
        // Smaller or tall: as big as fits, in the corner.
        const k = Math.min(box.w / art.width, (box.h * 0.9) / art.height);
        const w = art.width * k;
        const h = art.height * k;
        ctx.drawImage(art, box.x + box.w - w, box.y + box.h - h, w, h);
      }
    }
  } else if (ART.pattern) {
    fillWithPattern(ctx, ART.pattern, darkGround ? pal.paper : pal.primary, box.x, box.y, box.w, box.h, 200, box.showcase ? 0.18 : 0.07);
  } else if (ART.photo && !box.showcase) {
    const photo = duotone(ART.photo, mix(on, pal.ink, 0.2), mix(on, pal.paper, 0.2));
    if (photo) {
      ctx.globalAlpha = 0.35;
      drawCover(ctx, photo, box.x, box.y, box.w, box.h);
    }
  }
  ctx.restore();
}

/**
 * The block at the foot of every card: the reader's reason to scan, the event
 * (name · dates · hashtag) and, on the right, the QR with CampBuddy's mark.
 */
function paintBlock(ctx, g, block, pal, kit, brand, qrContent, cta, label = '') {
  const color = block.color;
  const x0 = block.x0 ?? 0;
  const x1 = block.x1 ?? g.W;
  const bottom = block.bottom ?? g.H;
  const top = g.blockTop;
  const h = bottom - top;
  const on = textOn(color, pal.ink, pal.paper);
  ctx.save();
  if (block.ticket) {
    // The ticket's stub: below a perforation, notches on both edges.
    ctx.beginPath(); roundRect(ctx, x0, top - 40, x1 - x0, h + 40, 36); ctx.clip();
  }
  ctx.fillStyle = color;
  ctx.fillRect(x0, top, x1 - x0, h);
  if (ART.pattern && !block.line) fillWithPattern(ctx, ART.pattern, on, x0, top, x1 - x0, h, 120, 0.07);
  ctx.restore();
  if (block.line) {
    ctx.fillStyle = alpha(pal.ink, 0.2);
    ctx.fillRect(g.x, top, g.w, 2);
  }
  if (block.ticket) {
    ctx.fillStyle = pal.deep;
    for (const x of [x0, x1]) { ctx.beginPath(); ctx.arc(x, top, 26, 0, Math.PI * 2); ctx.fill(); }
    ctx.strokeStyle = alpha(pal.paper, 0.6);
    ctx.setLineDash([14, 12]);
    ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(x0 + 40, top); ctx.lineTo(x1 - 40, top); ctx.stroke();
    ctx.setLineDash([]);
  }

  const inner = block.ticket ? 36 : 0;
  const left = (block.ticket ? x0 + 40 : SAFE);
  const right = (block.ticket ? x1 - 40 : g.W - SAFE);
  const pad = g.square ? 28 : (g.tall ? 50 : 38);
  const chipH = g.square ? 44 : (g.tall ? 64 : 52);
  const qr = Math.min(h - 2 * pad - chipH - 10, g.tall ? 250 : 180);
  const qx = right - qr;
  const qy = top + (h - qr - chipH - 10) / 2;
  drawQr(ctx, qrLink(kit.event.qr, qrContent), qx, qy, qr, pal.ink, color === pal.paper || block.line ? mix(pal.paper, '#ffffff', 0.5) : pal.paper);
  ctx.fillStyle = block.line || color === pal.paper ? mix(pal.paper, pal.ink, 0.06) : pal.paper;
  roundRect(ctx, qx, qy + qr + 10, qr, chipH, 12);
  ctx.fill();
  if (brand) drawContain(ctx, brand, qx + 8, qy + qr + 16, qr - 16, chipH - 12);

  const tw = qx - 36 - left;
  let ty = top + pad;
  if (label) {
    // Who this is, first: "Organizer at WordCamp Delhi".
    const lf = fitText(ctx, label, tw, g.tall ? 34 : (g.square ? 24 : 26), 24, 1, 800, FONT);
    ctx.font = `800 ${lf.size}px ${FONT}`;
    ctx.fillStyle = alpha(on, 0.82);
    ctx.fillText(lf.lines[0], left, ty + lf.size * 0.9);
    ty += lf.size * 1.5;
  }
  ctx.fillStyle = on;
  // The site's text font here, not its display font: this line must read at a glance.
  const c = fitText(ctx, cta, tw, label ? (g.tall ? 44 : (g.square ? 28 : 34)) : (g.tall ? 52 : (g.square ? 32 : 40)), 24, 2, 800, FONT);
  ctx.font = `800 ${c.size}px ${FONT}`;
  c.lines.forEach((l, i) => ctx.fillText(l, left, ty + c.size * (0.9 + i * 1.1)));
  // Nothing on a card under 24 px: it has to read on a phone.
  const small = g.tall ? 28 : 24;
  ctx.font = `600 ${small}px ${FONT}`;
  ctx.fillStyle = alpha(on, 0.78);
  ctx.fillText('Free · no sign-up · works offline', left, bottom - pad - small * 1.6 - inner * 0);
  ctx.font = `800 ${small}px ${FONT}`;
  ctx.fillStyle = on;
  const tag = (kit.event.hashtags ?? '').split(' ')[2];
  ctx.fillText(ellipsize(ctx, [kit.event.name, kit.event.dates, tag].filter(Boolean).join('  ·  '), tw), left, bottom - pad);
}

/** The header: the event's logo, and the kicker (and page number) on the right. */
function paintTop(ctx, g, L, pal, logo, kicker, page) {
  const head = L.head;
  const lw = g.logo.w * (head.logoInSide ? 0.8 : 1);
  const lh = g.logo.h * (head.logoInSide ? 0.8 : 1);
  if (head.logoInSide) logoTile(ctx, logo, (L.split - lw) / 2, g.headerTop, lw, lh, pal, 18);
  else logoTile(ctx, logo, head.x, head.y, lw, lh, pal, 20);
  let right = head.x + head.w;
  const h = g.square ? 48 : 56;
  const size = g.square ? 24 : 26;
  const colors = head.onPicture ? { bg: pal.paper, fg: pal.ink } : { bg: pal.primary, fg: pal.onPrimary };
  const y = head.y + (lh - h) / 2;
  const leftEdge = head.logoInSide ? head.x : head.x + lw + 24;
  // The first card of a carousel says there is more.
  const count = page.index === 1 ? `${page.index}/${page.total} · Swipe →` : `${page.index}/${page.total}`;
  if (page.total > 1) right -= pill(ctx, count, right, y, { ...colors, size, height: h, pad: 18, align: 'right' }) + 12;
  if (kicker) {
    ctx.font = `800 ${size}px ${FONT}`;
    const text = ellipsize(ctx, String(kicker).toUpperCase(), right - leftEdge - 44);
    if (head.logoInSide) pill(ctx, text, leftEdge, y, { ...colors, size, height: h, pad: 22 });
    else pill(ctx, text, right, y, { ...colors, size, height: h, pad: 22, align: 'right' });
  }
}

/** The big date of a save-the-date: "12–13" and "DEC 2026" (or the whole dates when they span months). */
function dateParts(dates) {
  const m = String(dates ?? '').match(/^(\d{1,2}(?:\s*[–-]\s*\d{1,2})?)\s+([A-Za-z]{3,})\s+(\d{4})$/);
  return m ? { big: m[1].replace(/\s+/g, ''), small: `${m[2]} ${m[3]}`.toUpperCase() } : { big: '', small: String(dates ?? '').toUpperCase() };
}

/** The words of a card, measured for `w`: headline (or the date / the number) and line. */
function measureHero(ctx, content, g, kit, w, top, bottom, picture = false) {
  const body = content.body.type;
  const hasBody = !picture && !['none', 'countdown'].includes(body);
  const scale = Math.min(1, w / g.w);
  const S = (n) => Math.round(n * (0.6 + 0.4 * scale));
  const parts = [];
  if (picture) {
    parts.push({ kind: 'head', fit: fitText(ctx, content.headline, w, S(g.tall ? 100 : (g.square ? 60 : 84)), 36, 2, 800, HEAD ?? FONT), gap: 16 });
    const line = content.key === 'spotlight' && content.body.speakers?.length ? [content.line, content.body.speakers.join(', ')].filter(Boolean).join(' · ') : content.line;
    if (line) parts.push({ kind: 'line', fit: fitText(ctx, line, w, S(g.tall ? 40 : 32), 24, 2, 600), gap: 0 });
  } else if (content.key === 'save-the-date') {
    const d = dateParts(kit?.event?.dates);
    parts.push({ kind: 'head', fit: fitText(ctx, content.headline, w, S(g.tall ? 96 : (g.square ? 60 : 80)), 36, 2, 800, HEAD ?? FONT), gap: 34 });
    if (d.big) parts.push({ kind: 'big', fit: fitText(ctx, d.big, w, S(g.tall ? 300 : (g.square ? 190 : 240)), 80, 1, 900, HEAD ?? FONT), gap: 22 });
    parts.push({ kind: 'month', text: d.small, size: S(g.tall ? 64 : (g.square ? 42 : 52)), gap: 26 });
    const dates = kit?.event?.dates ?? '';
    const where = (dates ? String(content.line ?? '').replace(dates, '') : String(content.line ?? '')).replace(/^[\s·]+/, '');
    if (where) parts.push({ kind: 'line', fit: fitText(ctx, where, w, S(g.tall ? 40 : 32), 24, 2, 600), gap: 0 });
  } else if (body === 'countdown' && content.body.days) {
    parts.push({ kind: 'big', fit: fitText(ctx, String(content.body.days), w, S(g.tall ? 440 : (g.square ? 260 : 340)), 110, 1, 900, HEAD ?? FONT), gap: 22 });
    parts.push({ kind: 'month', text: content.body.days === 1 ? 'DAY TO GO' : 'DAYS TO GO', size: S(g.tall ? 72 : (g.square ? 46 : 58)), gap: 26 });
    if (content.line) parts.push({ kind: 'line', fit: fitText(ctx, content.line, w, S(g.tall ? 40 : 32), 24, 2, 600), gap: 0 });
  } else {
    parts.push({ kind: 'head', fit: fitText(ctx, content.headline, w, S(g.tall ? 120 : (g.square ? (hasBody ? 64 : 84) : (hasBody ? 84 : 104))), 36, 3, 800, HEAD ?? FONT), gap: 22 });
    parts.push({ kind: 'bar', gap: 22 });
    if (content.line) parts.push({ kind: 'line', fit: fitText(ctx, content.line, w, S(g.tall ? 40 : 32), 24, 2, 600), gap: 0 });
  }
  const heightOf = (p) => {
    if (p.kind === 'bar') return 10;
    if (p.kind === 'month') return p.size;
    if (p.kind === 'big') return p.fit.size * 0.78;
    if (p.kind === 'line') return p.fit.lines.length * p.fit.size * 1.25;
    return p.fit.lines.length * p.fit.size * 1.04;
  };
  const textH = parts.reduce((sum, p) => sum + heightOf(p) + p.gap, 0);
  const gap = hasBody ? (g.tall ? 64 : 40) : 0;
  return { parts, heightOf, textH, gap, room: bottom - top - textH - gap };
}

function bodyHeight(body, H, room) {
  const rows = (n, type) => Math.ceil(n / columns(type, H)) * ROWS[type](H);
  switch (body.type) {
    case 'list': return rows(body.items.length, 'list');
    case 'tables': return rows(body.tables.length, 'tables');
    case 'logos': return rows(body.logos.length, 'logos');
    case 'features': return Math.min(room, H >= 1900 ? 520 : (H < 1100 ? 240 : 340));
    default: return 0;
  }
}

/** The picture box of a picture-led card (above its words), in the design's area. */
function pictureBox(g, L) {
  const share = g.square ? 0.5 : (g.tall ? 0.62 : 0.56);
  const full = !L.panel && !L.ticket && !L.split && !L.frameLine;
  const x = full ? 0 : L.area.x;
  const w = full ? g.W : L.area.w;
  const y = L.area.y - (g.square ? 10 : 16);
  const h = Math.round((L.area.bottom - y) * share);
  return { x, y, w, h };
}

/** Faces on a speakers card: a grid whose cells are about as tall as wide. */
function mosaicGrid(n, w, h) {
  let best = { cols: 1, rows: n };
  let score = Infinity;
  for (let cols = 1; cols <= Math.min(n, 6); cols++) {
    const rows = Math.ceil(n / cols);
    const cell = (w / cols) / (h / rows);
    const waste = rows * cols - n;
    const sc = Math.abs(Math.log(cell / 0.9)) + waste * 0.15;
    if (sc < score) { score = sc; best = { cols, rows }; }
  }
  return best;
}

/** Faces per speakers card: three, side by side, so every face is big. */
function mosaicPage() {
  return 3;
}

/** Every card of a post at this size and design (measured with fonts loaded). */
export function planPost(content, size, styleName, colors) {
  const [W, H] = SIZES[size];
  const g = grid(W, H);
  const design = POST_DESIGNS.includes(styleName) ? styleName : 'classic';
  const L = layoutFor(design, g, paletteOf(colors));
  const ctx = document.createElement('canvas').getContext('2d');
  useArt(content.artIndex ?? 0);

  if (content.body.type === 'photos') {
    const all = content.body.people ?? [];
    if (!all.length) return [{ ...content, body: { type: 'none' } }];
    const per = mosaicPage(H);
    const pages = [];
    for (let i = 0; i < all.length; i += per) pages.push({ ...content, body: { ...content.body, people: all.slice(i, i + per) } });
    return pages;
  }
  if (isPicture(content)) return [content];
  const m = measureHero(ctx, content, g, null, L.area.w, L.area.y, L.area.bottom);
  return paginate(content, m.room, H);
}

// ---- Painting a post --------------------------------------------------------

async function paintBody(ctx, body, x, y, w, h, pal, kit, H, ink) {
  const tall = H >= 1900;
  // On a light card times are in the main colour; on a coloured one (Poster) in the accent, or the light colour when the accent wouldn't read.
  const onColour = ink !== pal.ink;
  const lineColor = alpha(ink, 0.16);
  const muted = alpha(ink, 0.68);
  const accent = !onColour ? pal.primary : (contrast(pal.secondary, pal.primary) >= 3 ? pal.secondary : pal.paper);

  if (body.type === 'features') {
    const rowH = h / body.items.length;
    // Everything scales with the row, so a short area still has clear rows.
    const r = Math.min(24, rowH * 0.32);
    body.items.forEach((item, i) => {
      const ry = y + i * rowH + rowH / 2;
      ctx.fillStyle = pal.secondary;
      ctx.beginPath(); ctx.arc(x + r, ry, r, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = pal.onSecondary;
      ctx.lineWidth = Math.max(3, r / 5);
      ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(x + r * 0.55, ry); ctx.lineTo(x + r * 0.88, ry + r * 0.33); ctx.lineTo(x + r * 1.45, ry - r * 0.33); ctx.stroke();
      ctx.lineCap = 'butt';
      ctx.fillStyle = ink;
      const t = fitText(ctx, item, w - r * 3, Math.max(24, Math.min(tall ? 44 : 34, rowH * 0.42)), 24, rowH > 70 ? 2 : 1, 700);
      t.lines.forEach((l, k) => ctx.fillText(l, x + r * 3, ry + t.size * 0.35 + (k - (t.lines.length - 1) / 2) * t.size * 1.15));
    });
    return;
  }

  if (body.type === 'logos') {
    const cols = 3;
    const gap = 16;
    const cellW = (w - gap * (cols - 1)) / cols;
    const rowH = ROWS.logos(H);
    const imgs = await Promise.all(body.logos.map((l) => loadImage(proxied(kit, l.logo))));
    body.logos.forEach((l, i) => {
      const bx = x + (cellW + gap) * (i % cols);
      const by = y + rowH * Math.floor(i / cols);
      const light = imgs[i] && isLightImage(imgs[i]);
      ctx.fillStyle = light ? pal.ink : mix(pal.paper, '#ffffff', 0.6);
      roundRect(ctx, bx, by, cellW, rowH - gap, 18);
      ctx.fill();
      ctx.strokeStyle = alpha(pal.ink, 0.1);
      ctx.lineWidth = 2;
      ctx.stroke();
      if (imgs[i]) drawContain(ctx, imgs[i], bx + cellW * 0.12, by + (rowH - gap) * 0.18, cellW * 0.76, (rowH - gap) * 0.64);
      else {
        ctx.fillStyle = pal.ink;
        ctx.textAlign = 'center';
        const t = fitText(ctx, l.name, cellW - 30, 30, 24, 2, 800);
        t.lines.forEach((line, k) => ctx.fillText(line, bx + cellW / 2, by + (rowH - gap) / 2 + (k - (t.lines.length - 1) / 2) * t.size * 1.1 + t.size * 0.35));
        ctx.textAlign = 'left';
      }
    });
    return;
  }

  if (body.type === 'list') {
    const rowH = ROWS.list(H);
    ctx.font = `800 ${tall ? 30 : 26}px ${FONT}`;
    const timeW = Math.max(...body.items.map((it) => ctx.measureText(it.time).width)) + 28;
    body.items.forEach((it, i) => {
      const ry = y + i * rowH;
      if (i) {
        ctx.fillStyle = lineColor;
        ctx.fillRect(x, ry, w, 2);
      }
      ctx.fillStyle = accent;
      ctx.font = `800 ${tall ? 30 : 26}px ${FONT}`;
      ctx.fillText(it.time, x, ry + rowH * 0.42);
      ctx.fillStyle = ink;
      const t = fitText(ctx, it.title, w - timeW, tall ? 36 : 30, 24, 1, 800);
      ctx.fillText(t.lines[0], x + timeW, ry + rowH * 0.42);
      if (it.meta) {
        ctx.fillStyle = muted;
        const m = fitText(ctx, it.meta, w - timeW, tall ? 26 : 24, 24, 1, 600);
        ctx.fillText(m.lines[0], x + timeW, ry + rowH * 0.74);
      }
    });
    return;
  }

  if (body.type === 'tables') {
    const rowH = ROWS.tables(H);
    body.tables.forEach((t, i) => {
      const ry = y + i * rowH;
      if (i) {
        ctx.fillStyle = lineColor;
        ctx.fillRect(x, ry, w, 2);
      }
      ctx.fillStyle = pal.secondary;
      ctx.beginPath(); ctx.arc(x + 12, ry + rowH / 2, 12, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = ink;
      const n = fitText(ctx, t.name, w * 0.46, tall ? 36 : 32, 24, 1, 800);
      ctx.fillText(n.lines[0], x + 40, ry + rowH / 2 + n.size * 0.35);
      ctx.fillStyle = muted;
      ctx.textAlign = 'right';
      const p = fitText(ctx, t.place || (t.leads?.length ? `Leads: ${t.leads.join(', ')}` : ''), w * 0.48, 26, 24, 1, 600);
      ctx.fillText(p.lines[0] ?? '', x + w, ry + rowH / 2 + p.size * 0.35);
      ctx.textAlign = 'left';
    });
  }
}

function proxied(kit, url) {
  if (!url) return null;
  if (!kit.imageUrl || /gravatar\.com\//.test(url) || url.startsWith('/')) return url;
  return `${kit.imageUrl}?u=${encodeURIComponent(url)}`;
}

/** The picture of a picture-led card, in its own box: speakers' faces, a session's speakers, or the site's photo of the place. */
async function paintPicture(ctx, box, content, pal, round) {
  const body = content.body;
  const tones = [pal.primary, pal.deep, ...pal.extras];
  ctx.save();
  if (round) roundRect(ctx, box.x, box.y, box.w, box.h, 28);
  else { ctx.beginPath(); ctx.rect(box.x, box.y, box.w, box.h); }
  ctx.clip();
  if (body.type === 'photos' || body.type === 'session') {
    const people = body.type === 'photos'
      ? body.people
      : body.speakers.map((name, i) => ({ name, photo: body.photos[i] ?? null }));
    const list = people.length ? people : [{ name: content.headline, photo: null }];
    // One or two faces left for the last card: the same size as on the others, centred, space either side.
    const few = body.type === 'photos' && list.length < 3;
    const { cols, rows } = few ? { cols: list.length, rows: 1 } : mosaicGrid(list.length, box.w, box.h);
    const cw = few ? box.w / 3 : box.w / cols;
    const ch = box.h / rows;
    const x0 = box.x + (box.w - cw * cols) / 2;
    const imgs = await Promise.all(list.map((p) => loadImage(p.photo)));
    for (let i = 0; i < rows * cols; i++) {
      const cx = x0 + (i % cols) * cw;
      const cy = box.y + Math.floor(i / cols) * ch;
      ctx.save();
      ctx.beginPath(); ctx.rect(cx, cy, cw + 1, ch + 1); ctx.clip();
      const tone = tones[i % tones.length];
      if (imgs[i]) drawCover(ctx, imgs[i], cx, cy, cw + 1, ch + 1, 0.25);
      else {
        // No photo (or an empty place): a tile in a brand tone, the site's pattern, initials.
        ctx.fillStyle = tone;
        ctx.fillRect(cx, cy, cw + 1, ch + 1);
        if (ART.pattern) fillWithPattern(ctx, ART.pattern, textOn(tone, pal.ink, pal.paper), cx, cy, cw + 1, ch + 1, 110, 0.12);
        if (list[i]) {
          ctx.fillStyle = textOn(tone, pal.ink, pal.paper);
          ctx.textAlign = 'center';
          ctx.font = `900 ${Math.round(Math.min(cw, ch) * 0.34)}px ${HEAD ?? FONT}`;
          ctx.fillText(initials(list[i].name), cx + cw / 2, cy + ch / 2 + Math.min(cw, ch) * 0.12);
          ctx.textAlign = 'left';
        }
      }
      if (list[i] && body.type === 'photos') {
        // The name on its own face, on a short shade at the bottom of the tile.
        const nh = Math.min(70, ch * 0.24);
        const grad = ctx.createLinearGradient(0, cy + ch - nh, 0, cy + ch);
        grad.addColorStop(0, alpha(pal.ink, 0));
        grad.addColorStop(1, alpha(pal.ink, 0.72));
        ctx.fillStyle = grad;
        ctx.fillRect(cx, cy + ch - nh, cw, nh);
        ctx.fillStyle = pal.paper;
        const t = fitText(ctx, list[i].name, cw - 24, Math.min(28, Math.max(24, cw / 12)), 24, 1, 700);
        ctx.fillText(t.lines[0], cx + 12, cy + ch - 12);
      }
      ctx.restore();
    }
    ctx.fillStyle = pal.paper;
    for (let c = 1; c < cols; c++) ctx.fillRect(x0 + c * cw - 2, box.y, 4, box.h);
    for (let r = 1; r < rows; r++) ctx.fillRect(x0, box.y + r * ch - 2, cw * cols, 4);
  } else if (ART.photo) {
    drawCover(ctx, ART.photo, box.x, box.y, box.w, box.h, 0.5);
  }
  ctx.restore();
}

/** The parts of the words, drawn from `y` in the area. */
function paintWords(ctx, m, x, y, L) {
  for (const p of m.parts) {
    if (p.kind === 'head') {
      ctx.font = `800 ${p.fit.size}px ${HEAD ?? FONT}`;
      ctx.fillStyle = L.ink;
      p.fit.lines.forEach((l, i) => ctx.fillText(l, x, y + p.fit.size * (0.86 + i * 1.04)));
    } else if (p.kind === 'big') {
      // Placed by the glyphs' real height: display fonts draw digits very differently.
      ctx.font = `900 ${p.fit.size}px ${HEAD ?? FONT}`;
      ctx.fillStyle = L.accent;
      const mt = ctx.measureText(p.fit.lines[0]);
      const asc = mt.actualBoundingBoxAscent || p.fit.size * 0.72;
      const desc = mt.actualBoundingBoxDescent || 0;
      ctx.fillText(p.fit.lines[0], x - p.fit.size * 0.04, y + asc);
      y += asc + desc + p.gap;
      continue;
    } else if (p.kind === 'month') {
      ctx.font = `800 ${p.size}px ${FONT}`;
      ctx.fillStyle = L.ink;
      ctx.fillText(p.text, x, y + p.size * 0.86);
    } else if (p.kind === 'bar') {
      ctx.fillStyle = L.bar;
      roundRect(ctx, x, y, 120, 10, 5);
      ctx.fill();
    } else if (p.kind === 'line') {
      ctx.font = `600 ${p.fit.size}px ${FONT}`;
      ctx.fillStyle = L.muted;
      p.fit.lines.forEach((l, i) => ctx.fillText(l, x, y + p.fit.size * (0.9 + i * 1.25)));
    }
    y += m.heightOf(p) + p.gap;
  }
  return y;
}

/**
 * Draws one card of a post in one of the seven designs (`styleName`). `page`
 * = {index, total}; `scale` 1 for social, 6 for print (the same design, six
 * times the pixels).
 */
export async function drawEventPost(canvas, size, styleName, content, kit, colors, page = { index: 1, total: 1 }, scale = 1) {
  const [W, H] = SIZES[size];
  canvas.width = W * scale;
  canvas.height = H * scale;
  RES = scale;
  const ctx = canvas.getContext('2d');
  ctx.setTransform(scale, 0, 0, scale, 0, 0);
  const pal = paletteOf(colors);
  const g = grid(W, H);
  const design = POST_DESIGNS.includes(styleName) ? styleName : 'classic';
  const L = layoutFor(design, g, pal);
  const [logo, brand] = await Promise.all([loadImage(kit.event.logo || kit.event.mark), loadImage('/media/logo-wordmark.png')]);
  useArt(content.artIndex ?? 0);
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';
  const cta = CTA[content.key] ?? 'Scan to open CampBuddy';

  // Where the words (and the body) go, measured first, anchored under the
  // header (or under the picture) the same way on every card: the site's
  // artwork then takes only the room left below them, never the room under words.
  const picture = isPicture(content);
  const box = picture ? pictureBox(g, L) : null;
  const top = picture ? box.y + box.h + (g.square ? 24 : 40) : L.area.y;
  const m = measureHero(ctx, content, g, kit, L.area.w, top, L.area.bottom, picture);
  const bH = picture ? 0 : bodyHeight(content.body, H, m.room);
  const withBody = bH > 0 && m.room >= 100;
  const wordsBottom = top + m.textH + (withBody ? m.gap + bH : 0);
  const below = (art) => {
    if (art.showcase || art.x >= L.area.x + L.area.w || art.x + art.w <= L.area.x) return art;
    const y = Math.max(art.y, wordsBottom + (g.square ? 20 : 32));
    return { ...art, y, h: art.y + art.h - y, free: true };
  };

  // Ground, the artwork, then the panel / ticket / side (a panel or ticket covers the art behind it).
  ctx.fillStyle = L.bg;
  ctx.fillRect(0, 0, W, H);
  if (L.split) {
    ctx.fillStyle = pal.primary;
    ctx.fillRect(0, 0, L.split, g.blockTop);
  }
  const artBox = L.panel || L.ticket ? L.art : below(L.art);
  if (artBox.h > 40) paintArtBackground(ctx, artBox, pal);
  if (L.panel) {
    ctx.save();
    ctx.shadowColor = alpha(pal.ink, 0.3); ctx.shadowBlur = 30; ctx.shadowOffsetY = 10;
    ctx.fillStyle = pal.paper;
    roundRect(ctx, L.panel.x, L.panel.y, L.panel.w, L.panel.h, 30);
    ctx.fill();
    ctx.restore();
    // The site's artwork on the panel too, below its words.
    const inner = below({ x: L.panel.x, y: L.panel.y, w: L.panel.w, h: L.panel.h, on: pal.paper });
    if (inner.h > 40) {
      ctx.save();
      roundRect(ctx, L.panel.x, L.panel.y, L.panel.w, L.panel.h, 30);
      ctx.clip();
      paintArtBackground(ctx, inner, pal);
      ctx.restore();
    }
  }
  if (L.ticket) {
    ctx.save();
    ctx.shadowColor = alpha(pal.ink, 0.4); ctx.shadowBlur = 36; ctx.shadowOffsetY = 12;
    ctx.fillStyle = pal.paper;
    roundRect(ctx, L.ticket.x, L.ticket.y, L.ticket.w, L.ticket.h, 36);
    ctx.fill();
    ctx.restore();
    // The site's artwork on the ticket too, below its words.
    const inner = below({ x: L.ticket.x, y: L.ticket.y, w: L.ticket.w, h: g.blockTop - L.ticket.y, on: pal.paper });
    if (inner.h > 40) {
      ctx.save();
      roundRect(ctx, L.ticket.x, L.ticket.y, L.ticket.w, L.ticket.h, 36);
      ctx.clip();
      paintArtBackground(ctx, inner, pal);
      ctx.restore();
    }
  }
  if (L.frameLine) {
    ctx.strokeStyle = alpha(pal.ink, 0.2);
    ctx.lineWidth = 2;
    ctx.strokeRect(34, 34, W - 68, g.blockTop - 34);
    ctx.fillStyle = pal.primary;
    ctx.fillRect(34, 34, 12, g.blockTop - 34);
  }
  paintTop(ctx, g, L, pal, logo, content.kicker, page);

  if (picture) {
    // The picture in its own box; the words below it, never on it.
    await paintPicture(ctx, box, content, pal, Boolean(L.panel || L.ticket || L.split || L.frameLine));
    useArt(content.artIndex ?? 0);
    paintWords(ctx, m, L.area.x, top, L);
  } else {
    const y = paintWords(ctx, m, L.area.x, top, L);
    if (withBody) await paintBody(ctx, content.body, L.area.x, y + m.gap, L.area.w, bH, pal, kit, H, L.ink);
    useArt(content.artIndex ?? 0);
  }
  paintBlock(ctx, g, L.block, pal, kit, brand, content.key, cta);
}

// ---- People cards (social posts, not the attendee app's Camp Card) --------

/** The footer line of a people card: why the reader would look for this person, by role. */
export function personCta(person, kit) {
  const first = String(person.name ?? '').trim().split(/\s+/)[0] || 'them';
  const event = kit?.short ?? kit?.event?.name ?? 'WordCamp';
  switch (person.roles?.[0]) {
    case 'organizer': return `Say hi to ${first}, one of the people making ${event} happen`;
    case 'speaker': return person.talk ? `Save ${first}'s talk and get a reminder` : `Catch ${first} on stage at ${event}`;
    case 'volunteer': return `Thank ${first}, a volunteer at ${event}`;
    case 'sponsor': return `Visit ${first} at the sponsor booth`;
    case 'media_partner': return `Follow ${first}'s coverage of ${event}`;
    case 'microsponsor': return `Thank ${first} for backing ${event}`;
    case 'table_lead': return `Join ${first}'s table on Contributor Day`;
    default: return `Meet ${first} at ${event}`;
  }
}

export function personHeadline(person, kit) {
  const event = kit?.short ?? 'WordCamp';
  if (person.roles?.length) return `${person.role_labels[0]} at ${event}`;
  return `See you at ${event}`;
}

/** A tone of the brand for someone's first role (organizer: main, speaker: accent…). */
export function roleColor(person, colors) {
  const { primary, secondary, ink } = colors;
  const extras = colors.extras ?? [];
  // With the site's own colours, each role takes one of them.
  const fromSite = { volunteer: extras[0], sponsor: extras[1], media_partner: extras[2], microsponsor: extras[3], table_lead: extras[0] }[person.roles?.[0]];
  if (fromSite) return fromSite;
  switch (person.roles?.[0]) {
    case 'speaker': return secondary;
    case 'volunteer': return mix(primary, ink, 0.45);
    case 'sponsor': return mix(secondary, ink, 0.5);
    case 'media_partner': return mix(primary, secondary, 0.5);
    case 'microsponsor': return mix(secondary, primary, 0.3);
    case 'table_lead': return mix(primary, ink, 0.25);
    default: return primary;
  }
}

function personLines(person) {
  return {
    roles: person.role_labels?.length ? person.role_labels.join(' · ') : (person.card?.role ?? ''),
    talk: person.talk ? `“${person.talk}”` : (person.card?.askMeAbout ? `Ask me about ${person.card.askMeAbout}` : ''),
  };
}

export async function drawPersonCard(canvas, size, design, person, kit, colors, scale = 1) {
  const [W, H] = SIZES[size];
  canvas.width = W * scale;
  canvas.height = H * scale;
  RES = scale;
  const ctx = canvas.getContext('2d');
  ctx.setTransform(scale, 0, 0, scale, 0, 0);
  const [photo, mark, brand, eventLogo] = await Promise.all([loadImage(person.photo), loadImage(kit.event.mark), loadImage('/media/logo-wordmark.png'), loadImage(kit.event.logo || kit.event.mark)]);
  useArt(person.artIndex ?? 0);
  // The WordCamp's logo, on every people card.
  const LW = H < 1100 ? 150 : 180;
  const LH = H < 1100 ? 84 : 100;
  const topLogo = (x) => { if (eventLogo) logoTile(ctx, eventLogo, x, SAFE - 22, LW, LH, paletteOf(colors), 18); };
  const pal = paletteOf(colors);
  const look = eventLook(kit.event.seed);
  const rc = roleColor(person, colors);
  const onRc = textOn(rc, pal.ink, pal.paper);
  const { roles, talk } = personLines(person);
  const tall = H >= 1900;
  const square = H < 1100;
  // The posts' grid: a block at the foot in the role's tone (why scan, the
  // event, the QR); the site's artwork large in each design's background.
  const g = grid(W, H);
  const cta = personCta(person, kit);
  const footLabel = personHeadline(person, kit);
  const qrContent = `people-${String(person.roles?.[0] ?? 'camp-card').replace(/_/g, '-')}`;
  const fTop = g.blockTop - 20;
  // The foot of every design: the block (a deeper tone where the design is already in the role's tone).
  const foot = (under) => {
    const color = under === rc ? mix(rc, pal.ink, 0.3) : rc;
    paintBlock(ctx, g, { color }, pal, kit, brand, qrContent, cta, footLabel);
  };
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  if (design === 'polaroid') {
    // A tilted instant photo taped onto a wall in the role's tone.
    ctx.fillStyle = rc;
    ctx.fillRect(0, 0, W, H);
    const room = fTop - (SAFE + 80) - (talk && !square ? (tall ? 220 : 120) : 30);
    const ph = Math.min(room, tall ? 1000 : 900);
    const pw = ph - 150;
    const cx = W / 2;
    const cy = SAFE + 80 + 20 + ph / 2;
    // The site's artwork behind the photo, ending above the talk's words.
    paintArtBackground(ctx, { x: 0, y: 0, w: W, h: talk && !square ? cy + ph / 2 + 10 : g.blockTop, on: rc }, pal);
    topLogo(SAFE);
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(-0.04 * look.tilt);
    ctx.shadowColor = alpha(pal.ink, 0.4); ctx.shadowBlur = 36; ctx.shadowOffsetY = 16;
    ctx.fillStyle = pal.paper;
    ctx.fillRect(-pw / 2, -ph / 2, pw, ph);
    ctx.shadowColor = 'transparent';
    const inner = pw - 50;
    ctx.save(); ctx.beginPath(); ctx.rect(-inner / 2, -ph / 2 + 25, inner, inner); ctx.clip();
    if (photo) drawCover(ctx, photo, -inner / 2, -ph / 2 + 25, inner, inner, 0.3);
    else { ctx.fillStyle = pal.soft; ctx.fillRect(-inner / 2, -ph / 2 + 25, inner, inner); ctx.fillStyle = pal.primary; ctx.textAlign = 'center'; ctx.font = `900 ${Math.round(inner * 0.4)}px ${FONT}`; ctx.fillText(initials(person.name), 0, -ph / 2 + 25 + inner * 0.64); }
    ctx.restore();
    ctx.fillStyle = pal.ink;
    ctx.textAlign = 'center';
    const n = fitText(ctx, person.name, inner, 56, 28, 1, 700, SERIF);
    ctx.font = `italic 700 ${n.size}px ${SERIF}`;
    ctx.fillText(n.lines[0], 0, ph / 2 - 66);
    ctx.font = `700 24px ${FONT}`;
    ctx.fillStyle = rc;
    ctx.fillText(ellipsize(ctx, roles, inner), 0, ph / 2 - 28);
    ctx.fillStyle = alpha(pal.secondary, 0.75);
    ctx.save(); ctx.rotate(0.07 * look.tilt); ctx.fillRect(-90, -ph / 2 - 24, 180, 50); ctx.restore();
    ctx.restore();
    if (talk && !square) {
      ctx.fillStyle = onRc;
      ctx.textAlign = 'center';
      const t = fitText(ctx, talk, W - 2 * SAFE, tall ? 42 : 30, 24, tall ? 3 : 2, 700);
      const ty = cy + ph / 2 + (tall ? 90 : 60);
      t.lines.forEach((l, i) => ctx.fillText(l, W / 2, ty + i * t.size * 1.25));
      ctx.textAlign = 'left';
    }
    foot(rc);
    return;
  }

  if (design === 'badge') {
    // A conference badge on a lanyard.
    const wall = pal.soft;
    ctx.fillStyle = wall;
    ctx.fillRect(0, 0, W, H);
    paintArtBackground(ctx, { x: 0, y: 0, w: W, h: g.blockTop, on: wall }, pal);
    const bw = W * (square ? 0.56 : 0.66);
    const bh = (fTop - 30) - (SAFE + 70);
    const bx = (W - bw) / 2;
    const by = SAFE + 70;
    ctx.fillStyle = rc;
    ctx.beginPath(); ctx.moveTo(W / 2 - 60, 0); ctx.lineTo(W / 2 + 60, 0); ctx.lineTo(W / 2 + 30, by + 20); ctx.lineTo(W / 2 - 30, by + 20); ctx.closePath(); ctx.fill();
    if (ART.pattern) {
      ctx.save();
      ctx.beginPath(); ctx.moveTo(W / 2 - 60, 0); ctx.lineTo(W / 2 + 60, 0); ctx.lineTo(W / 2 + 30, by + 20); ctx.lineTo(W / 2 - 30, by + 20); ctx.closePath(); ctx.clip();
      fillWithPattern(ctx, ART.pattern, onRc, W / 2 - 60, 0, 120, by + 20, 60, 0.35);
      ctx.restore();
    }
    ctx.fillStyle = alpha(pal.ink, 0.45);
    roundRect(ctx, W / 2 - 42, by - 16, 84, 46, 12); ctx.fill();
    ctx.save();
    ctx.shadowColor = alpha(pal.ink, 0.25); ctx.shadowBlur = 30; ctx.shadowOffsetY = 12;
    ctx.fillStyle = pal.paper;
    roundRect(ctx, bx, by, bw, bh, 32); ctx.fill();
    ctx.restore();
    ctx.save(); roundRect(ctx, bx, by, bw, bh, 32); ctx.clip();
    ctx.fillStyle = pal.primary; ctx.fillRect(bx, by, bw, square ? 100 : 130);
    const hh = square ? 100 : 130;
    const lw = Math.min(bw * 0.36, 170);
    if (eventLogo) logoTile(ctx, eventLogo, bx + 22, by + (hh - (hh - 36)) / 2, lw, hh - 36, pal, 14);
    ctx.fillStyle = pal.onPrimary;
    const ev = fitText(ctx, kit.event.name, bw - lw - 66, 30, 24, 2, 800);
    ev.lines.forEach((l, i) => ctx.fillText(l, bx + lw + 44, by + hh / 2 + ev.size * 0.35 + (i - (ev.lines.length - 1) / 2) * ev.size * 1.1));
    const bandH = square ? 80 : 110;
    ctx.fillStyle = rc; ctx.fillRect(bx, by + bh - bandH, bw, bandH);
    ctx.fillStyle = onRc; ctx.textAlign = 'center';
    const rl = fitText(ctx, (person.role_labels?.[0] ?? 'Attendee').toUpperCase(), bw - 50, tall ? 56 : (square ? 36 : 46), 24, 1, 900);
    ctx.fillText(rl.lines[0], bx + bw / 2, by + bh - bandH / 2 + rl.size * 0.35);
    ctx.restore();
    ctx.fillStyle = wall;
    roundRect(ctx, W / 2 - 46, by + 16, 92, 20, 10); ctx.fill();
    const inner = bh - (square ? 100 : 130) - bandH;
    // The name is measured first, so a two-line name never runs into the role band: the photo gives way.
    const n = fitText(ctx, person.name, bw - 70, tall ? 84 : (square ? 46 : 64), 26, 2, 900);
    const nameH = n.lines.length * n.size * 1.06 + 14;
    const r = Math.max(40, Math.min(bw * (tall ? 0.34 : 0.26), inner * (talk ? 0.26 : 0.3), (inner - 26 - nameH - 22 - 8) / 2));
    const cy = by + (square ? 100 : 130) + 26 + r;
    circlePhoto(ctx, photo, W / 2, cy, r, 8, rc, pal.soft, pal.primary, person.name);
    ctx.fillStyle = pal.ink; ctx.textAlign = 'center';
    ctx.font = `900 ${n.size}px ${FONT}`;
    let y = cy + r + 14;
    n.lines.forEach((l) => { y += n.size; ctx.fillText(l, W / 2, y); y += n.size * 0.06; });
    const room = by + bh - bandH - 16 - y;
    if (talk && room > 40) {
      ctx.fillStyle = alpha(pal.ink, 0.75);
      const t = fitText(ctx, talk, bw - 90, tall ? 36 : 26, 24, Math.max(1, Math.min(3, Math.floor(room / 36))), 600);
      y += 8;
      t.lines.forEach((l) => { y += t.size * 1.2; ctx.fillText(l, W / 2, y); });
    }
    ctx.textAlign = 'left';
    foot(wall);
    return;
  }

  if (design === 'spotlight') {
    // The photo, big, in two tones of the role's colour; a band in that colour
    // with the name; the site's artwork faint across the foot of the photo.
    const bandH = square ? 150 : (tall ? 270 : 210);
    const bandTop = g.blockTop - bandH;
    const dark = mix(rc, pal.ink, 0.6);
    const tone = photo ? duotone(photo, dark, mix(pal.paper, rc, 0.08)) : null;
    ctx.fillStyle = dark;
    ctx.fillRect(0, 0, W, bandTop);
    if (tone) drawCover(ctx, tone, 0, 0, W, bandTop, 0.25);
    else {
      ctx.fillStyle = pal.paper; ctx.textAlign = 'center';
      ctx.font = `900 ${Math.round(bandTop * 0.3)}px ${HEAD ?? FONT}`;
      ctx.fillText(initials(person.name), W / 2, bandTop * 0.58);
      ctx.textAlign = 'left';
    }
    paintArtBackground(ctx, { x: 0, y: bandTop * 0.64, w: W, h: bandTop * 0.36, on: dark }, pal);
    const logo = await loadImage(kit.event.logo || kit.event.mark);
    if (logo) logoTile(ctx, logo, W - SAFE - (square ? 150 : 180), SAFE - 22, square ? 150 : 180, square ? 84 : 100, pal, 18);
    ctx.fillStyle = rc;
    ctx.fillRect(0, bandTop, W, bandH);
    ctx.fillStyle = onRc;
    const n = fitText(ctx, person.name, W - 2 * SAFE, tall ? 110 : (square ? 60 : 80), 32, 1, 900, HEAD ?? FONT);
    ctx.font = `900 ${n.size}px ${HEAD ?? FONT}`;
    let y = bandTop + (square ? 28 : 44) + n.size * 0.86;
    ctx.fillText(n.lines[0], SAFE, y);
    const small = tall ? 34 : (square ? 24 : 28);
    ctx.font = `700 ${small}px ${FONT}`;
    ctx.fillStyle = alpha(onRc, 0.88);
    const sub = [roles, talk].filter(Boolean).join('  ·  ');
    if (sub) ctx.fillText(ellipsize(ctx, sub, W - 2 * SAFE), SAFE, y + small + (square ? 12 : 20));
    foot(rc);
    return;
  }

  if (design === 'arch') {
    // The photo in an arch (a jharokha, a doorway), outlined in the role's
    // colour, on the light colour with the site's artwork behind.
    ctx.fillStyle = pal.paper;
    ctx.fillRect(0, 0, W, H);
    const logo = await loadImage(kit.event.logo || kit.event.mark);
    const n = fitText(ctx, person.name, W - 2 * SAFE, tall ? 104 : (square ? 56 : 76), 30, 2, 800, HEAD ?? FONT);
    const nameH = n.lines.length * n.size * 1.04;
    const small = tall ? 34 : (square ? 24 : 28);
    const textH = nameH + small * 1.8 + (talk ? small * 1.6 : 0) + 30;
    const top = SAFE + (square ? 90 : 120);
    const room = fTop - top - textH - 24;
    const aw = Math.min(W * (square ? 0.44 : 0.58), room / 1.3);
    const ah = aw * 1.3;
    const ax = (W - aw) / 2;
    const arch = (inset) => {
      const x = ax - inset;
      const w = aw + 2 * inset;
      const yTop = top - inset;
      const r = w / 2;
      ctx.beginPath();
      ctx.moveTo(x, top + ah + inset);
      ctx.lineTo(x, yTop + r);
      ctx.arc(x + r, yTop + r, r, Math.PI, 0);
      ctx.lineTo(x + w, top + ah + inset);
      ctx.closePath();
    };
    // The site's artwork behind the arch, standing on its foot (it shows either side), never under the name.
    paintArtBackground(ctx, { x: 0, y: 0, w: W, h: top + ah + 22, on: pal.paper }, pal);
    if (logo) logoTile(ctx, logo, SAFE, SAFE - 22, square ? 150 : 180, square ? 84 : 100, pal, 18);
    ctx.fillStyle = rc;
    arch(22); ctx.fill();
    ctx.fillStyle = pal.paper;
    arch(12); ctx.fill();
    ctx.save();
    arch(0); ctx.clip();
    if (photo) drawCover(ctx, photo, ax, top, aw, ah, 0.3);
    else {
      ctx.fillStyle = pal.soft; ctx.fillRect(ax, top, aw, ah);
      ctx.fillStyle = rc; ctx.textAlign = 'center';
      ctx.font = `900 ${Math.round(aw * 0.36)}px ${HEAD ?? FONT}`;
      ctx.fillText(initials(person.name), W / 2, top + ah * 0.6);
      ctx.textAlign = 'left';
    }
    ctx.restore();
    let y = top + ah + 22 + (square ? 30 : 50);
    ctx.textAlign = 'center';
    ctx.fillStyle = pal.ink;
    ctx.font = `800 ${n.size}px ${HEAD ?? FONT}`;
    n.lines.forEach((l) => { y += n.size * 0.86; ctx.fillText(l, W / 2, y); y += n.size * 0.18; });
    ctx.font = `800 ${small}px ${FONT}`;
    ctx.fillStyle = rc;
    if (roles) { y += small * 1.3; ctx.fillText(ellipsize(ctx, roles, W - 2 * SAFE), W / 2, y); }
    if (talk) {
      ctx.font = `600 ${Math.max(24, small - 2)}px ${FONT}`;
      ctx.fillStyle = alpha(pal.ink, 0.75);
      y += small * 1.4;
      ctx.fillText(ellipsize(ctx, talk, W - 2 * SAFE), W / 2, y);
    }
    ctx.textAlign = 'left';
    foot(pal.paper);
    return;
  }

  if (design === 'split') {
    // The photo fills the top three quarters; the bottom quarter, in the role's
    // tone, holds the words, the event and the QR. The site's artwork lives in
    // the picture: its skyline rises from the colour block into the photo (in
    // that tone), tall art (a sprig) grows from the corner, the pattern lines
    // the block faintly.
    const ph = Math.round(H * 0.75);
    const logo = await loadImage(kit.event.logo || kit.event.mark);
    if (photo) drawCover(ctx, photo, 0, 0, W, ph, 0.25);
    else {
      ctx.fillStyle = pal.soft; ctx.fillRect(0, 0, W, ph);
      if (ART.pattern) fillWithPattern(ctx, ART.pattern, rc, 0, 0, W, ph, 180, 0.12);
      ctx.fillStyle = rc; ctx.textAlign = 'center';
      ctx.font = `900 ${Math.round(ph * 0.34)}px ${HEAD ?? FONT}`;
      ctx.fillText(initials(person.name), W / 2, ph * 0.5);
      ctx.textAlign = 'left';
    }
    // The photo darkens into the role's tone where it meets the block.
    const g = ctx.createLinearGradient(0, ph * 0.55, 0, ph);
    g.addColorStop(0, alpha(rc, 0));
    g.addColorStop(1, alpha(rc, 0.75));
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, ph);

    paintArtBackground(ctx, { x: 0, y: ph * 0.62, w: W, h: ph * 0.38, on: mix(rc, pal.ink, 0.2) }, pal);

    // The block.
    ctx.fillStyle = rc;
    ctx.fillRect(0, ph, W, H - ph);
    if (ART.pattern) fillWithPattern(ctx, ART.pattern, onRc, 0, ph, W, H - ph, 120, 0.08);

    // On the photo: the event's logo (who this is goes in the block).
    if (logo) logoTile(ctx, logo, W - SAFE - (square ? 150 : 180), SAFE - 22, square ? 150 : 180, square ? 84 : 100, pal, 18);

    // In the block: the QR (and CampBuddy's mark) on the right, the words on the left.
    const pad = square ? 30 : (tall ? 56 : 40);
    const blockH = H - ph;
    const chipH = square ? 44 : (tall ? 70 : 54);
    const qr = Math.min(blockH - 2 * pad - chipH - 10, tall ? 260 : 190);
    const qx = W - SAFE - qr;
    const qy = ph + (blockH - qr - chipH - 10) / 2;
    drawQr(ctx, qrLink(kit.event.qr, `people-${String(person.roles?.[0] ?? 'camp-card').replace(/_/g, '-')}`), qx, qy, qr, pal.ink, pal.paper);
    ctx.fillStyle = pal.paper;
    roundRect(ctx, qx, qy + qr + 10, qr, chipH, 12);
    ctx.fill();
    if (brand) drawContain(ctx, brand, qx + 8, qy + qr + 16, qr - 16, chipH - 12);

    const tw = qx - 36 - SAFE;
    let y = ph + pad;
    ctx.fillStyle = onRc;
    const n = fitText(ctx, person.name, tw, tall ? 96 : (square ? 50 : 66), 30, 1, 900, HEAD ?? FONT);
    ctx.font = `900 ${n.size}px ${HEAD ?? FONT}`;
    y += n.size * 0.9;
    ctx.fillText(n.lines[0], SAFE, y);
    y += square ? 14 : 20;
    const small = tall ? 34 : (square ? 24 : 26);
    const eventLine = [kit.event.name, kit.event.dates].filter(Boolean).join(' · ');
    const bottom = ph + blockH - pad;
    // Who, then why: "Organizer at WordCamp Delhi", then the role's line.
    ctx.font = `800 ${small}px ${FONT}`;
    ctx.fillStyle = alpha(onRc, 0.9);
    y += small;
    ctx.fillText(ellipsize(ctx, footLabel, tw), SAFE, y);
    if (!talk) {
      // The role's line, on two lines if it needs them.
      ctx.fillStyle = onRc;
      const c = fitText(ctx, cta, tw, small, 24, 2, 600);
      c.lines.forEach((l) => { y += c.size * 1.3; ctx.fillText(l, SAFE, y); });
    }
    const room = bottom - small - 16 - (y + 10);
    if (talk && room > small) {
      ctx.fillStyle = onRc;
      const t = fitText(ctx, talk, tw, small + 2, 24, Math.max(1, Math.min(2, Math.floor(room / ((small + 2) * 1.25)))), 600);
      y += 10;
      t.lines.forEach((l) => { y += t.size * 1.25; ctx.fillText(l, SAFE, y); });
    }
    ctx.font = `700 ${Math.max(24, small - 4)}px ${FONT}`;
    ctx.fillStyle = alpha(onRc, 0.8);
    ctx.fillText(ellipsize(ctx, eventLine, tw), SAFE, bottom);
    return;
  }

  if (design === 'minimal') {
    // Light colour, thin lines, lots of air: the name does the talking.
    ctx.fillStyle = pal.paper;
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = alpha(pal.ink, 0.2);
    ctx.lineWidth = 2;
    ctx.strokeRect(40, 40, W - 80, H - 80);
    ctx.fillStyle = rc;
    ctx.fillRect(look.corner === 'left' ? 40 : W - 56, 40, 16, fTop - 60);
    if (ART.pattern) fillWithPattern(ctx, ART.pattern, rc, look.corner === 'left' ? 64 : W - 140, 40, 76, fTop - 60, 76, 0.35);
    const r = tall ? 210 : (square ? 100 : 130);
    const photoLeft = look.corner === 'left';
    circlePhoto(ctx, photo, photoLeft ? W - SAFE - 30 - r : SAFE + 30 + r, SAFE + 40 + r, r, 0, rc, pal.soft, pal.primary, person.name);
    topLogo(photoLeft ? SAFE + 30 : W - SAFE - 30 - LW);
    let y = SAFE + 40 + 2 * r + (tall ? 200 : (square ? 70 : 110));
    ctx.fillStyle = pal.ink;
    const n = fitText(ctx, person.name, W - 2 * SAFE - 60, tall ? 140 : (square ? 76 : 100), 40, 2, 700, SERIF);
    ctx.font = `700 ${n.size}px ${SERIF}`;
    n.lines.forEach((l) => { ctx.fillText(l, SAFE + 30, y); y += n.size * 1.02; });
    ctx.fillStyle = rc; ctx.fillRect(SAFE + 30, y + 4, 120, 8);
    y += 58;
    ctx.font = `700 28px ${FONT}`; ctx.fillStyle = alpha(pal.ink, 0.8);
    if (roles) { ctx.fillText(ellipsize(ctx, roles, W - 2 * SAFE - 60), SAFE + 30, y); y += 52; }
    const room = fTop - 30 - y;
    if (talk && room > 40) {
      const t = fitText(ctx, talk, W - 2 * SAFE - 60, tall ? 44 : 32, 24, Math.max(1, Math.min(4, Math.floor(room / 42))), 500, SERIF);
      ctx.font = `italic 500 ${t.size}px ${SERIF}`;
      t.lines.forEach((l) => { ctx.fillText(l, SAFE + 30, y); y += t.size * 1.3; });
    }
    // The site's artwork in the corner left under the words.
    if (fTop - 20 - y > 100) paintArtBackground(ctx, { x: W * 0.25, y, w: W * 0.75 - 64, h: fTop - 20 - y, on: pal.paper, corner: true, free: true }, pal);
    foot(pal.paper);
    return;
  }

  // Classic: the role's tone on top with a slanted edge, round photo, name below.
  const top = fTop * (square ? 0.5 : 0.48);
  ctx.fillStyle = pal.paper;
  ctx.fillRect(0, 0, W, H);
  const s = look.tilt * 60;
  ctx.save();
  ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(W, 0); ctx.lineTo(W, top - s); ctx.lineTo(0, top + s); ctx.closePath();
  ctx.fillStyle = rc; ctx.fill(); ctx.clip();
  if (ART.illustration || ART.pattern) paintArtBackground(ctx, { x: 0, y: 0, w: W, h: top + Math.abs(s), on: rc }, pal);
  else drawMotif(ctx, look.motif, SAFE, SAFE + 70, 260, 150, alpha(pal.paper, 0.3), alpha(pal.secondary, 0.8), seeded(kit.event.seed));
  ctx.restore();
  topLogo(SAFE);
  ctx.strokeStyle = rc === pal.secondary ? pal.primary : pal.secondary;
  ctx.lineWidth = 14;
  ctx.beginPath(); ctx.moveTo(0, top + s + 16); ctx.lineTo(W, top - s + 16); ctx.stroke();
  const r = tall ? 290 : (square ? 160 : 215);
  const cy = top - (square ? 30 : 20);
  circlePhoto(ctx, photo, W / 2, cy, r, 14, pal.paper, pal.soft, pal.primary, person.name);
  let y = cy + r + (square ? 70 : (tall ? 150 : 100));
  ctx.fillStyle = pal.ink; ctx.textAlign = 'center';
  const name = fitText(ctx, person.name, W - 2 * SAFE, square ? 60 : (tall ? 100 : 80), 34, 2, 900);
  name.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += name.size * 1.1; });
  ctx.fillStyle = mix(rc, pal.ink, 0.2);
  ctx.font = `800 32px ${FONT}`;
  if (roles) { ctx.fillText(ellipsize(ctx, roles, W - 2 * SAFE), W / 2, y + 2); y += 54; }
  const room = fTop - 30 - y;
  if (talk && room > 40) {
    ctx.fillStyle = pal.ink;
    const t = fitText(ctx, talk, W - 2 * SAFE - 40, tall ? 48 : 36, 24, Math.max(1, Math.min(4, Math.floor(room / 46))), 500);
    t.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += t.size * 1.3; });
  }
  ctx.textAlign = 'left';
  // The site's artwork in the room left under the words.
  if (g.blockTop - y > 80) paintArtBackground(ctx, { x: 0, y: y + 10, w: W, h: g.blockTop - y - 10, on: pal.paper, free: true }, pal);
  foot(pal.paper);
}

// ---- Files ----------------------------------------------------------------

async function canvasBlob(canvas, quality = 'social') {
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
  return quality === 'print' ? withPngDpi(blob, QUALITY.print.dpi) : blob;
}

function save(blob, filename) {
  const href = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = href;
  a.download = filename;
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(href), 2000);
}

export function slugify(text) {
  return String(text ?? '').toLowerCase().normalize('NFKD').replace(/\p{M}/gu, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50) || 'post';
}

export function shareLinks(caption, site) {
  const text = caption.length > 270 ? `${caption.slice(0, 267)}…` : caption;
  return [
    ['LinkedIn', `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(site)}`],
    ['X', `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}`],
    ['Facebook', `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(site)}`],
    ['WhatsApp', `https://wa.me/?text=${encodeURIComponent(caption)}`],
  ];
}

async function copy(text, btn) {
  const label = btn.textContent;
  try {
    await navigator.clipboard.writeText(text);
    btn.textContent = 'Copied ✓';
  } catch {
    btn.textContent = 'Select and copy';
  }
  setTimeout(() => { btn.textContent = label; }, 2000);
}

/** A row of buttons acting as one choice; `initial` selects one at start. */
function choiceGroup(attr, onChange, initial = null) {
  const buttons = [...document.querySelectorAll(`[data-${attr}]`)];
  const key = attr.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
  let value = initial ?? buttons.find((b) => b.getAttribute('aria-pressed') === 'true')?.dataset[key] ?? buttons[0]?.dataset[key];
  const paint = () => buttons.forEach((o) => {
    const on = o.dataset[key] === value;
    o.setAttribute('aria-pressed', String(on));
    o.classList.toggle('cb-btn-primary', on);
    o.classList.toggle('cb-btn-secondary', !on);
  });
  paint();
  buttons.forEach((b) => b.addEventListener('click', () => { value = b.dataset[key]; paint(); onChange(value); }));
  return () => value;
}

// ---- Page -----------------------------------------------------------------

/**
 * Loads the WordCamp site's heading and text fonts (through CampBuddy's
 * proxy, so the canvas may use them) under names of their own; resolves to
 * the font stacks, or null for a role that didn't load.
 */
async function loadSiteFonts(kit) {
  const out = {};
  for (const [role, font] of Object.entries(kit.site?.fonts ?? {})) {
    const family = `CB Site ${role}`;
    const faces = (font.faces ?? []).map((f) => new FontFace(family, `url(${proxied(kit, f.url)})`, {
      weight: f.weight || '400',
      style: f.style || 'normal',
      ...(f.range ? { unicodeRange: f.range } : {}),
    }));
    const loaded = (await Promise.allSettled(faces.map((face) => face.load()))).filter((r) => r.status === 'fulfilled').map((r) => r.value);
    loaded.forEach((face) => document.fonts.add(face));
    if (loaded.length) out[role] = `"${family}", ${role === 'heading' ? DEFAULT_SERIF : DEFAULT_FONT}`;
  }
  return out;
}

function useSiteFonts(fonts, on) {
  FONT = on && fonts.body ? fonts.body : DEFAULT_FONT;
  SERIF = on && fonts.heading ? fonts.heading : DEFAULT_SERIF;
  HEAD = on && fonts.heading ? fonts.heading : null;
}

/** Loads every ticked piece of the site's artwork, by kind. */
async function setSiteArt(kit, enabled) {
  for (const kind of ['pattern', 'illustration', 'photo']) {
    const pieces = (kit.site?.art ?? []).filter((a, i) => a.kind === kind && enabled.has(i));
    // A site piece too small to stay sharp at card size (an old 20 px sprite, a thumbnail) is left out;
    // a pattern repeats, so it may be small; an upload was chosen by someone, so it stays.
    const minSide = kind === 'pattern' ? 48 : 400;
    const loaded = await Promise.all(pieces.map((a) => loadImage(proxied(kit, a.url))));
    const kept = loaded.filter((img, i) => img && (pieces[i].uploaded || Math.max(img.naturalWidth || img.width, img.naturalHeight || img.height) >= minSide));
    // An illustration without its empty margins, so it fills the room it gets (a pattern keeps its spacing, a photo is solid).
    SITE_ART[kind] = kind === 'illustration' ? kept.map(trimmed) : kept;
  }
  useArt(0);
}

export function initSocialKit() {
  const dataEl = document.getElementById('social-kit-data');
  if (!dataEl) return;
  const kit = JSON.parse(dataEl.textContent);
  const colors = { ...kit.colors };
  const look = eventLook(kit.event.seed);
  const sizeEl = document.querySelector('[data-social-size]');
  const size = () => sizeEl?.value ?? '1080x1350';
  let people = null;
  let redrawAll = () => {};
  const style = choiceGroup('social-style', () => redrawAll(), look.style);
  const design = choiceGroup('people-design', () => people?.redraw(), look.design);
  const quality = choiceGroup('social-quality', () => {}, 'social');

  // Each post and each person takes the next piece of the site's artwork.
  // Same role, same artwork: every organizer's card has one piece, every speaker's the next…
  const ROLE_ART = ['organizer', 'speaker', 'volunteer', 'sponsor', 'media_partner', 'microsponsor', 'table_lead'];
  kit.people.forEach((person) => { const i = ROLE_ART.indexOf(person.roles?.[0]); person.artIndex = i < 0 ? ROLE_ART.length : i; });
  const posts = [...document.querySelectorAll('[data-social-post]')].map((card, cardIndex) => {
    const post = kit.posts.find((p) => p.key === card.dataset.socialPost);
    const field = (name) => card.querySelector(`[data-field="${name}"]`);
    const pickEl = card.querySelector('[data-pick]');
    const canvas = card.querySelector('[data-preview]');
    const pager = card.querySelector('[data-pager]');
    const pageLabel = card.querySelector('[data-page-label]');
    const fields = () => ({ headline: field('headline').value, line: field('line').value, caption: field('caption').value });
    let pageIndex = 0;
    let pages = [];
    let drawing = Promise.resolve();

    // The countdown's headline and caption follow the number of days as it changes.
    let shownDays = post.key === 'countdown' ? post.days : null;
    const syncDays = () => {
      if (post.key !== 'countdown') return;
      const now = daysToGo(kit);
      if (!now || !shownDays || now === shownDays) return;
      for (const name of ['headline', 'caption']) {
        const el = field(name);
        if (el) el.value = el.value.replace(new RegExp(`\\b${shownDays}\\b`, 'g'), String(now));
      }
      shownDays = now;
    };
    const replan = () => {
      syncDays();
      pages = planPost({ ...postContent(post, fields(), kit, pickEl?.value), artIndex: cardIndex }, size(), style(), colors);
      pageIndex = Math.min(pageIndex, pages.length - 1);
      // A class, not `hidden`: the row's own `flex` would win over the attribute.
      if (pager) pager.classList.toggle('hidden', pages.length < 2);
      if (pageLabel) pageLabel.textContent = `Card ${pageIndex + 1} of ${pages.length}`;
    };
    const redraw = () => {
      drawing = drawing.then(() => {
        replan();
        return drawEventPost(canvas, size(), style(), pages[pageIndex], kit, colors, { index: pageIndex + 1, total: pages.length });
      }).catch(() => {});
      return drawing;
    };

    pager?.querySelector('[data-prev]')?.addEventListener('click', () => { pageIndex = Math.max(0, pageIndex - 1); redraw(); });
    pager?.querySelector('[data-next]')?.addEventListener('click', () => { pageIndex = Math.min(pages.length - 1, pageIndex + 1); redraw(); });

    // Today / Spotlight: the caption follows the chosen day or session.
    const refreshCaption = () => {
      if (post.key === 'today') {
        field('caption').value = todayCaption(kit, pickEl.value, sessionsOfDay(kit.schedule.sessions, pickEl.value));
      } else if (post.key === 'spotlight') {
        const s = kit.schedule.sessions.find((x) => String(x.id) === String(pickEl.value));
        field('caption').value = spotlightCaption(kit, s);
        field('headline').value = '';
        field('line').value = '';
        field('headline').placeholder = s?.title ?? '';
        field('line').placeholder = [s?.time, s?.track].filter(Boolean).join(' · ');
      }
    };
    if (pickEl) {
      refreshCaption();
      pickEl.addEventListener('change', () => { pageIndex = 0; refreshCaption(); redraw(); });
    }

    field('headline').addEventListener('input', redraw);
    field('line').addEventListener('input', redraw);

    // Every card of the post, at the chosen quality: one PNG, or a ZIP of them.
    const renderAll = async () => {
      await drawing;
      const q = QUALITY[quality()];
      const out = document.createElement('canvas');
      const files = [];
      for (const [i, page] of pages.entries()) {
        await drawEventPost(out, size(), style(), page, kit, colors, { index: i + 1, total: pages.length }, q.scale);
        files.push({ name: `${kit.event.slug}-${post.key}${pages.length > 1 ? `-${i + 1}` : ''}-${quality()}.png`, blob: await canvasBlob(out, quality()) });
      }
      return files;
    };

    card.querySelector('[data-action="download"]').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      const files = await renderAll();
      if (files.length === 1) save(files[0].blob, files[0].name);
      else {
        const entries = await Promise.all(files.map(async (f) => ({ name: f.name, data: new Uint8Array(await f.blob.arrayBuffer()) })));
        entries.push({ name: 'caption.txt', data: new TextEncoder().encode(field('caption').value) });
        save(new Blob([zipStore(entries)], { type: 'application/zip' }), `${kit.event.slug}-${post.key}-${quality()}.zip`);
      }
      btn.disabled = false;
    });
    card.querySelector('[data-action="copy"]').addEventListener('click', (e) => copy(field('caption').value, e.currentTarget));
    card.querySelector('[data-action="share"]').addEventListener('click', async () => {
      const caption = field('caption').value;
      const files = (await renderAll()).map((f) => new File([f.blob], f.name, { type: 'image/png' }));
      if (navigator.canShare?.({ files })) {
        try {
          await navigator.share({ files, text: caption });
          return;
        } catch {
          // Cancelled or refused: fall back to the links below.
        }
      }
      const linksEl = card.querySelector('[data-share-links]');
      linksEl.replaceChildren('Copy the caption and download the image, then: ', ...shareLinks(caption, qrLink(kit.event.qr, post.key)).flatMap(([label, href], i) => {
        const a = document.createElement('a');
        a.href = href;
        a.target = '_blank';
        a.rel = 'noopener';
        a.className = 'font-medium underline';
        a.textContent = label;
        return i ? [' · ', a] : [a];
      }));
    });
    card.querySelector('[data-action="publish"]')?.addEventListener('click', (e) => publish(e.currentTarget, kit, canvas, redraw, () => ({ ...post, ...fields() })));

    // For the reels (social-reels.js): this post as it stands now, its words included.
    socialContext.posts.push({
      key: post.key,
      label: post.label,
      content: () => { syncDays(); return { ...postContent(post, fields(), kit, pickEl?.value), artIndex: cardIndex }; },
      caption: () => field('caption').value,
    });

    return redraw;
  });
  Object.assign(socialContext, { kit, colors, style });

  people = setUpPeople(kit, colors, size, design, quality);
  redrawAll = () => { posts.forEach((r) => r()); people?.redraw(); };
  sizeEl?.addEventListener('change', redrawAll);

  const status = document.querySelector('[data-colors-status]');
  const contrastEl = document.querySelector('[data-contrast]');
  const showContrast = () => {
    if (!contrastEl) return;
    const checks = [
      ['Text on light', contrast(colors.ink, colors.paper), 7],
      ['Light on main', contrast(colors.paper, colors.primary), 4.5],
      ['Label on accent', Math.max(contrast(colors.ink, colors.secondary), contrast(colors.paper, colors.secondary)), 4.5],
    ];
    contrastEl.textContent = checks.map(([label, ratio, min]) => `${label} ${ratio.toFixed(1)}:1 ${ratio >= min ? '✓' : `✗ (needs ${min}:1)`}`).join('  ·  ');
    contrastEl.className = `mt-1 text-xs ${checks.every(([, r, m]) => r >= m) ? 'text-teal' : 'text-danger'}`;
  };
  const setColors = (palette, message) => {
    Object.assign(colors, palette);
    Object.entries(palette).forEach(([k, v]) => {
      const input = document.querySelector(`[data-color="${k}"]`);
      if (input) input.value = v;
    });
    if (message && status) status.textContent = message;
    showContrast();
    redrawAll();
  };
  document.querySelectorAll('[data-color]').forEach((input) => {
    input.addEventListener('input', () => { colors[input.dataset.color] = input.value; if (status) status.textContent = 'Not saved yet.'; showContrast(); redrawAll(); });
  });
  const sitePalette = paletteFromSite(kit.site?.colors);
  document.querySelector('[data-colors-from-site]')?.addEventListener('click', () => {
    if (sitePalette) setColors(sitePalette, 'Picked from the WordCamp site ✓ Save to keep them.');
    else if (status) status.textContent = 'The site has too few colours of its own: try Pick from logo.';
  });

  // The site's fonts: on by default when the site has them.
  let siteFonts = {};
  const fontsBox = document.querySelector('[data-site-fonts]');
  const fontsStatus = document.querySelector('[data-site-fonts-status]');
  fontsBox?.addEventListener('change', () => { useSiteFonts(siteFonts, fontsBox.checked); redrawAll(); });

  // The site's artwork: every piece ticked starts in use.
  const artBoxes = [...document.querySelectorAll('[data-site-art]')];
  const enabledArt = () => new Set(artBoxes.filter((b) => b.checked).map((b) => Number(b.dataset.siteArt)));
  artBoxes.forEach((b) => b.addEventListener('change', async () => { await setSiteArt(kit, enabledArt()); redrawAll(); }));
  const fromLogo = async (announce = true) => {
    const logo = await loadImage(kit.event.logo || kit.event.mark);
    let palette = null;
    if (logo) {
      const c = document.createElement('canvas');
      c.width = 120; c.height = 120;
      const ctx = c.getContext('2d');
      drawContain(ctx, logo, 0, 0, 120, 120);
      try {
        palette = paletteFromPixels(ctx.getImageData(0, 0, 120, 120).data);
      } catch {
        palette = null;
      }
    }
    if (!palette) {
      if (announce && status) status.textContent = logo ? 'The logo has no clear colours: keeping these.' : 'No logo for this event yet.';
      return false;
    }
    setColors({ ...palette, extras: [] }, announce ? 'Picked from the logo ✓ Save to keep them.' : null);
    return true;
  };
  document.querySelector('[data-colors-from-logo]')?.addEventListener('click', () => fromLogo(true));

  // Start: the site's fonts and artwork, and — until colours are saved — the
  // site's colours (or the logo's, when the site names none).
  (async () => {
    try {
      siteFonts = await loadSiteFonts(kit);
      useSiteFonts(siteFonts, fontsBox?.checked ?? false);
      if (fontsStatus && kit.site?.fonts && Object.keys(kit.site.fonts).length && !Object.keys(siteFonts).length) fontsStatus.textContent = '(could not load them)';
    } catch {
      // CampBuddy's fonts then.
    }
    await setSiteArt(kit, enabledArt()).catch(() => {});
    await document.fonts?.ready;
    // The site's own colours come first; saved colours are for a site that names none.
    if (sitePalette) setColors(sitePalette, 'From the WordCamp site.');
    else if (!kit.colorsSaved) await fromLogo(false);
    showContrast();
    redrawAll();
  })();
  showContrast();
  redrawAll();
}

function setUpPeople(kit, colors, size, design, quality) {
  const grid = document.querySelector('[data-people-grid]');
  if (!grid) return null;
  const roleEl = document.querySelector('[data-people-role]');
  const more = document.querySelector('[data-people-more]');
  const status = document.querySelector('[data-people-status]');
  let shown = PEOPLE_PAGE;

  const chosen = () => {
    const role = roleEl?.value ?? '';
    return kit.people.filter((p) => !role || (role === 'card' ? Boolean(p.card) : p.roles.includes(role)));
  };

  const redraw = () => {
    const list = chosen();
    grid.replaceChildren(...list.slice(0, shown).map((person) => {
      const box = document.createElement('div');
      box.className = 'space-y-2 rounded-xl border border-line bg-white p-3 shadow-sm';
      const canvas = document.createElement('canvas');
      canvas.className = 'mx-auto block h-64 w-auto max-w-full rounded-lg';
      const name = document.createElement('p');
      name.className = 'text-sm font-medium';
      name.textContent = person.name;
      const actions = document.createElement('div');
      actions.className = 'flex flex-wrap gap-2';
      const dl = document.createElement('button');
      dl.type = 'button';
      dl.className = 'cb-btn cb-btn-secondary cb-btn-sm';
      dl.textContent = 'Download';
      dl.addEventListener('click', async () => {
        const out = document.createElement('canvas');
        await drawPersonCard(out, size(), design(), person, kit, colors, QUALITY[quality()].scale);
        save(await canvasBlob(out, quality()), `${slugify(person.name)}-${design()}-${quality()}.png`);
      });
      const cp = document.createElement('button');
      cp.type = 'button';
      cp.className = 'cb-btn cb-btn-secondary cb-btn-sm';
      cp.textContent = 'Copy caption';
      cp.addEventListener('click', () => copy(person.caption, cp));
      actions.append(dl, cp);
      box.append(canvas, name, actions);
      drawPersonCard(canvas, size(), design(), person, kit, colors);
      return box;
    }));
    if (more) more.hidden = list.length <= shown;
    if (status) status.textContent = `${list.length} ${list.length === 1 ? 'card' : 'cards'}`;
  };

  roleEl?.addEventListener('change', () => { shown = PEOPLE_PAGE; redraw(); });
  more?.addEventListener('click', () => { shown += PEOPLE_PAGE; redraw(); });

  document.querySelector('[data-people-zip]')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const list = chosen();
    if (!list.length || btn.disabled) return;
    btn.disabled = true;
    const canvas = document.createElement('canvas');
    const files = [];
    const captions = [];
    const used = new Set();
    for (const [i, person] of list.entries()) {
      if (status) status.textContent = `Making ${i + 1} of ${list.length}…`;
      await drawPersonCard(canvas, size(), design(), person, kit, colors, QUALITY[quality()].scale);
      let name = `${slugify(person.name)}.png`;
      for (let n = 2; used.has(name); n++) name = `${slugify(person.name)}-${n}.png`;
      used.add(name);
      files.push({ name, data: new Uint8Array(await (await canvasBlob(canvas, quality())).arrayBuffer()) });
      captions.push(`${name}\n${person.caption}\n`);
    }
    files.push({ name: 'captions.txt', data: new TextEncoder().encode(captions.join('\n')) });
    save(new Blob([zipStore(files)], { type: 'application/zip' }), `${kit.event.slug}-people-${roleEl?.value || 'all'}-${design()}-${quality()}.zip`);
    if (status) status.textContent = `${list.length} cards saved in one ZIP, with captions.txt.`;
    btn.disabled = false;
  });

  redraw();
  return { redraw };
}

// ---- Publish (webhook) ------------------------------------------------------

async function publish(btn, kit, canvas, redraw, current) {
  if (!kit.publishUrl || btn.disabled) return;
  const post = current();
  btn.disabled = true;
  const label = btn.textContent;
  btn.textContent = 'Publishing…';
  try {
    await redraw();
    const body = new FormData();
    body.append('image', await canvasBlob(canvas), `${post.key}.png`);
    body.append('title', post.headline || post.label);
    body.append('caption', post.caption);
    body.append('kind', post.key);
    const res = await fetch(kit.publishUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' },
      body,
    });
    const reply = await res.json().catch(() => ({}));
    btn.textContent = res.ok ? 'Sent ✓' : (reply.message ?? 'Failed');
  } catch {
    btn.textContent = 'Failed';
  }
  setTimeout(() => { btn.textContent = label; btn.disabled = false; }, 4000);
}

// ---- For the reels (social-reels.js) ----------------------------------------
// The page's live state (filled by initSocialKit) and the drawing helpers the
// reels share with the cards, so a reel looks like the event's posts.
export const socialContext = { kit: null, colors: null, style: null, posts: [] };

export const reelTools = {
  loadImage, drawContain, drawQr, roundRect, fitText, wrap, initials, paletteOf, recolour, duotone, artInfo,
  proxied, logoTile, isLightImage, roleColor, trimmed, personCta, mix, textOn, seeded, alpha,
  fonts: () => ({ text: FONT, head: HEAD ?? FONT }),
  siteArt: () => SITE_ART,
  features: FEATURES,
};
