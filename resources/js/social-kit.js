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
//   - print ready: a 3× export (3240 px wide) stamped 300 DPI, with every
//     word and code at least 72 px (at 1080) inside the edge.

import QRCode from 'qrcode-generator';
import { zipStore } from './zip-store.js';
import { withPngDpi } from './attendee/png-dpi.js';

const SIZES = { '1080x1350': [1080, 1350], '1080x1080': [1080, 1080], '1080x1920': [1080, 1920] };
const QUALITY = { social: { scale: 1, dpi: 72 }, print: { scale: 3, dpi: 300 } };
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
const STYLES = ['bold', 'poster', 'ticket', 'gradient'];
const MOTIFS = ['rings', 'dots', 'stripes', 'triangles', 'waves', 'arcs', 'blocks', 'plus'];
const TEXTURES = ['circle', 'diamond', 'arc', 'bars'];
const SAFE = 72;
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
    style: pick(STYLES),
    design: pick(['classic', 'polaroid', 'badge', 'split', 'minimal']),
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

/** A tile for a logo: the light colour, or the dark one when the logo itself is light. */
function logoTile(ctx, img, x, y, w, h, pal, r = 24) {
  ctx.fillStyle = isLightImage(img) ? pal.ink : pal.paper;
  roundRect(ctx, x, y, w, h, r);
  ctx.fill();
  if (img) drawContain(ctx, img, x + w * 0.07, y + h * 0.1, w * 0.86, h * 0.8);
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

function drawCover(ctx, img, x, y, w, h) {
  const ratio = Math.max(w / img.width, h / img.height);
  const dw = img.width * ratio;
  const dh = img.height * ratio;
  ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
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

// A fine grain, so flat colour looks printed (neutral: it only lightens and darkens).
let grain = null;
function addGrain(ctx, W, H, strength = 0.05) {
  // Not on a print file: at 3x the noise would make every PNG many times bigger, and paper has its own grain.
  if (ctx.getTransform().a > 1) return;
  if (!grain) {
    grain = document.createElement('canvas');
    grain.width = grain.height = 220;
    const g = grain.getContext('2d');
    const data = g.createImageData(220, 220);
    for (let i = 0; i < data.data.length; i += 4) {
      const v = Math.random() * 255;
      data.data[i] = data.data[i + 1] = data.data[i + 2] = v;
      data.data[i + 3] = 255;
    }
    g.putImageData(data, 0, 0);
  }
  ctx.save();
  ctx.globalAlpha = strength;
  ctx.globalCompositeOperation = 'overlay';
  ctx.fillStyle = ctx.createPattern(grain, 'repeat');
  ctx.fillRect(0, 0, W, H);
  ctx.restore();
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
  const key = `${img.src}|${color}`;
  if (recolours.has(key)) return recolours.get(key);
  const scale = Math.min(1, 900 / Math.max(img.width, img.height));
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
  const key = `${img.src}|duo|${dark}|${light}`;
  if (recolours.has(key)) return recolours.get(key);
  const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
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
  const info = { mono: false, opaque: false, aspect: img.width / img.height };
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
  if (!artInfo(img).mono) return img;
  return recolour(img, textOn(under, pal.primary, pal.paper) === pal.primary ? pal.primary : pal.paper);
}

/**
 * Height of the band at the foot of a card where the site's illustration
 * stands (0 = none): wide art (a skyline) up to the width of the card, tall
 * art (a sprig) as a frieze. `amount` 1 = full, less on a card whose list needs room.
 */
function artBand(W, H, img, amount = 1) {
  if (!img || !amount) return 0;
  const square = H < 1100;
  const tall = H >= 1900;
  const aspect = img.width / img.height;
  const band = aspect < 1.4
    ? (square ? 150 : (tall ? 280 : 210))
    : Math.max(square ? 120 : 170, Math.min(square ? 220 : (tall ? 520 : 340), (W - 40) / aspect));
  return Math.round(band * amount);
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

/**
 * The foot of a card: a light ground from `top` to the bottom, the site's
 * pattern as a strip along its top edge, and the site's illustration standing
 * on the footer — a skyline in its own colours, as wide as the card; tall art
 * repeated (and mirrored) as a frieze. Clipped to `clip` (the ticket) if given.
 */
function paintArtBand(ctx, W, H, top, footerTop, pal, { ground = true, strip = true, clip = null } = {}) {
  ctx.save();
  if (clip) { clip(); ctx.clip(); }
  if (ground) {
    ctx.fillStyle = pal.paper;
    ctx.fillRect(0, top, W, H - top);
  }
  const stripH = strip && ART.pattern ? 34 : 0;
  if (stripH) patternStrip(ctx, ART.pattern, pal, pal.paper, 0, top, W, stripH);
  else if (strip && ground) {
    ctx.fillStyle = pal.secondary;
    ctx.fillRect(0, top, W, 10);
  }
  const img = ART.illustration;
  if (img) {
    const art = artFor(img, pal, pal.paper);
    const room = footerTop - top - stripH - 14;
    if (art && room > 40) {
      const info = artInfo(img);
      if (info.aspect < 1.4) {
        const h = room;
        const w = h * info.aspect;
        const n = Math.max(1, Math.floor((W + w * 0.25) / (w * 0.92)));
        const step = W / n;
        for (let i = 0; i < n; i++) {
          ctx.save();
          ctx.translate(step * i + step / 2, footerTop - h);
          if (i % 2) ctx.scale(-1, 1);
          ctx.drawImage(art, -w / 2, 0, w, h);
          ctx.restore();
        }
      } else {
        const k = Math.min((W - 40) / art.width, room / art.height);
        const w = art.width * k;
        const h = art.height * k;
        if (info.opaque) {
          // A picture with its own ground: full width, faded into the light colour at the top.
          ctx.save();
          ctx.beginPath(); ctx.rect(0, footerTop - room, W, room); ctx.clip();
          drawCover(ctx, art, 0, footerTop - room, W, room);
          const g = ctx.createLinearGradient(0, footerTop - room, 0, footerTop - room * 0.55);
          g.addColorStop(0, pal.paper);
          g.addColorStop(1, alpha(pal.paper, 0));
          ctx.fillStyle = g;
          ctx.fillRect(0, footerTop - room, W, room);
          ctx.restore();
        } else {
          ctx.drawImage(art, (W - w) / 2, footerTop - h, w, h);
        }
      }
    }
  }
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

/** The drawable content of a post: kicker, headline, line, and a body. */
export function postContent(post, fields, kit, pick) {
  const base = { key: post.key, kicker: post.kicker ?? '', headline: fields.headline, line: fields.line, body: { type: 'none' } };

  switch (post.key) {
    case 'countdown':
      return { ...base, body: post.days ? { type: 'countdown', days: post.days } : { type: 'none' } };
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

function styleOf(name, pal) {
  switch (name) {
    case 'poster':
      return { name, bg: pal.paper, fg: pal.ink, muted: alpha(pal.ink, 0.72), accent: pal.primary, kicker: { bg: pal.primary, fg: pal.onPrimary }, card: { bg: pal.soft, fg: pal.ink, muted: alpha(pal.ink, 0.7) }, family: HEAD ?? SERIF, footer: { bg: pal.paper, fg: pal.ink, accent: pal.primary } };
    case 'ticket':
      return { name, bg: pal.paper, fg: pal.ink, muted: alpha(pal.ink, 0.72), accent: pal.primary, kicker: { bg: pal.secondary, fg: pal.onSecondary }, card: { bg: pal.soft, fg: pal.ink, muted: alpha(pal.ink, 0.7) }, family: HEAD ?? FONT, footer: { bg: pal.paper, fg: pal.ink, accent: pal.primary } };
    case 'gradient':
      return { name, bg: pal.primary, fg: pal.paper, muted: alpha(pal.paper, 0.86), accent: pal.secondary, kicker: { bg: alpha(pal.paper, 0.18), fg: pal.paper }, card: { bg: alpha(pal.paper, 0.14), fg: pal.paper, muted: alpha(pal.paper, 0.8) }, family: HEAD ?? FONT, footer: { bg: alpha(pal.paper, 0.14), fg: pal.paper, accent: pal.secondary } };
    default:
      return { name: 'bold', bg: pal.primary, fg: pal.onPrimary, muted: alpha(pal.onPrimary, 0.86), accent: pal.secondary, kicker: { bg: pal.secondary, fg: pal.onSecondary }, card: { bg: alpha(pal.onPrimary, 0.12), fg: pal.onPrimary, muted: alpha(pal.onPrimary, 0.8) }, family: HEAD ?? FONT, footer: { bg: pal.paper, fg: pal.ink, accent: pal.primary } };
  }
}

/** The fixed parts of a card: content column, where the text may start, the footer. */
function frame(W, H, styleName, withArt = true) {
  const square = H < 1100;
  const tall = H >= 1900;
  const footerH = square ? 200 : (tall ? 260 : 230);
  const inset = styleName === 'ticket' ? 46 : 0;
  // The site's illustration (a skyline) stands in a light band on the footer;
  // `withArt` 1 = full height, less when a list needs the room, 0 = none.
  const artH = artBand(W - 2 * inset, H, ART.illustration, withArt === true ? 1 : Number(withArt) || 0);
  const stub = styleName === 'ticket' ? Math.round(H * (tall ? 0.15 : (square ? 0.17 : 0.19))) : 0;
  const x = SAFE + inset;
  const w = W - 2 * x;
  const headerTop = SAFE + inset;
  const logo = square ? { w: 200, h: 112 } : { w: 260, h: 146 };
  // The kicker sits beside the logo, so the words start just under it.
  const top = styleName === 'ticket' ? inset + stub + 50 : headerTop + logo.h + (square ? 34 : (tall ? 110 : 64));
  const footer = H - inset - footerH;
  return { x, w, headerTop, logo, top, footer, footerH, inset, stub, square, tall, artH, contentBottom: footer - artH };
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

/** Headline and line sized for this card; how much room the body has under them. */
function measureText(ctx, content, f, style) {
  const hasBody = content.body.type !== 'none';
  const head = fitText(ctx, content.headline, f.w, f.tall ? 128 : (f.square ? (hasBody ? 68 : 88) : (hasBody ? 96 : 112)), 40, hasBody ? 2 : 3, 900, style.family);
  const line = fitText(ctx, content.line, f.w, f.tall ? 44 : 36, 22, 2, 600);
  const textH = head.lines.length * head.size * 1.06 + (content.line ? 46 + line.lines.length * line.size * 1.3 : 0);
  const gap = hasBody ? (f.tall ? 70 : 36) : 0;
  const room = f.contentBottom - 40 - f.top - textH - gap;
  return { head, line, textH, gap, room };
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
  const perPage = Math.max(1, Math.floor(Math.max(room, 0) / ROWS[content.body.type](H)) * columns(content.body.type, H));
  const pages = [];
  for (let i = 0; i < all.length; i += perPage) pages.push({ ...content, body: { ...content.body, [key]: all.slice(i, i + perPage) } });
  return pages;
}

function bodyHeight(body, H, room) {
  const rows = (n, type) => Math.ceil(n / columns(type, H)) * ROWS[type](H);
  switch (body.type) {
    case 'list': return rows(body.items.length, 'list');
    case 'tables': return rows(body.tables.length, 'tables');
    case 'logos': return rows(body.logos.length, 'logos');
    case 'photos': return rows(body.people.length, 'photos');
    case 'countdown': return Math.min(room, H >= 1900 ? 400 : (H < 1100 ? 220 : 300));
    case 'session': return Math.min(room, H >= 1900 ? 340 : (H < 1100 ? 200 : 270));
    case 'features': return Math.min(room, H >= 1900 ? 520 : (H < 1100 ? 250 : 360));
    default: return 0;
  }
}

/** Every card of a post at this size and style (measured with fonts loaded). */
export function planPost(content, size, styleName, colors) {
  const [W, H] = SIZES[size];
  const c = document.createElement('canvas');
  const ctx = c.getContext('2d');
  const style = styleOf(styleName, paletteOf(colors));
  useArt(content.artIndex ?? 0);
  // The site's illustration gets smaller, then gives way, when the body would otherwise not fit two rows.
  const needs = itemsKey(content.body.type) ? ROWS[content.body.type](H) * 2 : (content.body.type !== 'none' ? 220 : 0);
  let artAmount = 1;
  let m = measureText(ctx, content, frame(W, H, styleName, artAmount), style);
  for (const amount of [0.6, 0]) {
    if (!ART.illustration || !needs || m.room >= needs) break;
    artAmount = amount;
    m = measureText(ctx, content, frame(W, H, styleName, artAmount), style);
  }
  return paginate({ ...content, artAmount }, m.room, H);
}

// ---- Painting a post --------------------------------------------------------

function paintBackground(ctx, W, H, f, style, pal, look, mark) {
  ctx.fillStyle = style.bg;
  ctx.fillRect(0, 0, W, H);

  if (style.name === 'gradient') {
    // The site's photo, in two tones of the brand, under the brand gradient.
    const photo = ART.photo ? duotone(ART.photo, pal.deep, pal.primary) : null;
    if (photo) drawCover(ctx, photo, 0, 0, W, H);
    const g = ctx.createLinearGradient(0, 0, W * (look.corner === 'left' ? -0.2 : 1.2), H);
    g.addColorStop(0, alpha(pal.deep, photo ? 0.75 : 1));
    g.addColorStop(0.55, alpha(pal.primary, photo ? 0.55 : 1));
    g.addColorStop(1, alpha(mix(pal.primary, pal.secondary, 0.65), photo ? 0.8 : 1));
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
    for (const [x, y, r, c, a] of [[look.corner === 'left' ? 0 : W, H * 0.08, W * 0.6, pal.secondary, 0.4], [look.corner === 'left' ? W : 0, H * 0.75, W * 0.5, pal.paper, 0.14]]) {
      const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
      rg.addColorStop(0, alpha(c, a));
      rg.addColorStop(1, alpha(c, 0));
      ctx.fillStyle = rg;
      ctx.fillRect(0, 0, W, H);
    }
    if (!(ART.pattern && fillWithPattern(ctx, ART.pattern, pal.paper, 0, 0, W, H, 240, 0.06))) watermark(ctx, mark, W, H, 0.05);
  } else if (style.name === 'bold') {
    if (!(ART.pattern && fillWithPattern(ctx, ART.pattern, pal.paper, 0, 0, W, H, 220, 0.07))) watermark(ctx, mark, W, H, 0.06);
    drawTexture(ctx, look.texture, W, H, alpha(pal.extras[0], 0.16), look.corner);
  } else if (style.name === 'poster') {
    if (ART.pattern) fillWithPattern(ctx, ART.pattern, pal.primary, 0, 0, W, H, 220, 0.05);
    drawTexture(ctx, look.texture, W, H, alpha(pal.extras[0], 0.12), look.corner);
    ctx.fillStyle = pal.primary;
    ctx.fillRect(look.corner === 'left' ? 0 : W - 18, 0, 18, H);
  } else if (style.name === 'ticket') {
    ctx.fillStyle = pal.deep;
    ctx.fillRect(0, 0, W, H);
    // Around the ticket: the site's photo in two dark tones, or its pattern, or the event's mark.
    const photo = ART.photo ? duotone(ART.photo, mix(pal.deep, pal.ink, 0.5), pal.deep) : null;
    if (photo) drawCover(ctx, photo, 0, 0, W, H);
    else if (!(ART.pattern && fillWithPattern(ctx, ART.pattern, pal.paper, 0, 0, W, H, 200, 0.1))) watermark(ctx, mark, W, H, 0.08);
    const m = f.inset;
    ctx.save();
    ctx.shadowColor = alpha(pal.ink, 0.45); ctx.shadowBlur = 40; ctx.shadowOffsetY = 14;
    ctx.fillStyle = pal.paper;
    roundRect(ctx, m, m, W - 2 * m, H - 2 * m, 40);
    ctx.fill();
    ctx.restore();
    ctx.save();
    roundRect(ctx, m, m, W - 2 * m, H - 2 * m, 40);
    ctx.clip();
    ctx.fillStyle = pal.primary;
    ctx.fillRect(m, m, W - 2 * m, f.stub);
    ctx.restore();
    ctx.fillStyle = pal.deep;
    for (const x of [m, W - m]) { ctx.beginPath(); ctx.arc(x, m + f.stub, 30, 0, Math.PI * 2); ctx.fill(); }
    ctx.strokeStyle = alpha(pal.ink, 0.3);
    ctx.setLineDash([16, 14]);
    ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(m + 46, m + f.stub); ctx.lineTo(W - m - 46, m + f.stub); ctx.stroke();
    ctx.setLineDash([]);
  }
}

function paintHeader(ctx, W, f, style, pal, look, logo, content, page) {
  const onStub = style.name === 'ticket';
  const tileY = onStub ? f.inset + (f.stub - f.logo.h) / 2 : f.headerTop;
  if (onStub && ART.pattern) {
    // The site's pattern, faintly, across the ticket's stub.
    ctx.save();
    roundRect(ctx, f.inset, f.inset, W - 2 * f.inset, f.stub, 40);
    ctx.clip();
    fillWithPattern(ctx, ART.pattern, pal.onPrimary, f.inset, f.inset, W - 2 * f.inset, f.stub, 110, 0.16);
    ctx.restore();
  }
  logoTile(ctx, logo, f.x, tileY, f.logo.w, f.logo.h, pal);

  // Right of the logo: the page number (1/3), the kicker before it.
  let right = W - f.inset - SAFE;
  const pillH = f.square ? 48 : 56;
  if (page.total > 1) {
    right -= pill(ctx, `${page.index}/${page.total}`, right, tileY + (f.logo.h - pillH) / 2, { bg: style.kicker.bg, fg: style.kicker.fg, size: f.square ? 22 : 26, height: pillH, pad: 18, align: 'right' }) + 14;
  }
  if (content.kicker && onStub) {
    // On the stub the kicker sits on the perforation.
    pill(ctx, String(content.kicker).toUpperCase(), f.x, f.inset + f.stub - 27, { bg: style.kicker.bg, fg: style.kicker.fg, size: 24, height: 54 });
  } else if (content.kicker) {
    ctx.font = `800 ${f.square ? 22 : 26}px ${FONT}`;
    const room = right - (f.x + f.logo.w + 30);
    const text = ellipsize(ctx, String(content.kicker).toUpperCase(), room - 40);
    right -= pill(ctx, text, right, tileY + (f.logo.h - pillH) / 2, { bg: style.kicker.bg, fg: style.kicker.fg, size: f.square ? 22 : 26, height: pillH, pad: 20, align: 'right' }) + 24;
  }

  // An event without artwork of its own: its motif fills the space between, inside its own box.
  const mx = f.x + f.logo.w + 30;
  if (!ART.pattern && !ART.illustration && right - mx > 120) {
    const motifColors = [alpha(pal.extras[0], 0.75), alpha(pal.secondary, 0.85)];
    drawMotif(ctx, look.motif, mx, tileY, right - mx, f.logo.h, ...motifColors, seeded(look.seedValue));
  }
}

async function paintBody(ctx, body, x, y, w, h, style, pal, kit, H) {
  const tall = H >= 1900;
  const light = style.name === 'poster' || style.name === 'ticket';

  if (body.type === 'countdown') {
    const r = h / 2 - 6;
    const cx = x + r + 6;
    const cy = y + h / 2;
    ctx.fillStyle = light ? pal.primary : alpha(pal.paper, 0.16);
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = pal.secondary;
    ctx.lineWidth = 12;
    ctx.lineCap = 'round';
    ctx.beginPath(); ctx.arc(cx, cy, r - 16, -Math.PI / 2, Math.PI * 1.15); ctx.stroke();
    ctx.lineCap = 'butt';
    ctx.fillStyle = light ? pal.onPrimary : pal.paper;
    ctx.textAlign = 'center';
    const digits = String(body.days);
    ctx.font = `900 ${Math.round(r * (digits.length > 2 ? 0.7 : 0.95))}px ${FONT}`;
    ctx.fillText(digits, cx, cy + r * 0.32);
    ctx.textAlign = 'left';
    ctx.fillStyle = style.fg;
    ctx.font = `900 ${tall ? 72 : 58}px ${FONT}`;
    ctx.fillText(body.days === 1 ? 'day' : 'days', cx + r + 40, cy);
    ctx.font = `700 ${tall ? 40 : 34}px ${FONT}`;
    ctx.fillStyle = style.muted;
    ctx.fillText('to go', cx + r + 40, cy + 52);
    return;
  }

  if (body.type === 'features') {
    // What CampBuddy does, beside a big QR to it.
    const side = Math.min(h, w * 0.42);
    drawQr(ctx, qrLink(kit.event.qr, 'app'), x + w - side, y + (h - side) / 2, side, pal.ink, pal.paper);
    const tw = w - side - 40;
    const rowH = h / body.items.length;
    body.items.forEach((item, i) => {
      const ry = y + i * rowH + rowH / 2;
      ctx.fillStyle = pal.secondary;
      ctx.beginPath(); ctx.arc(x + 22, ry, 22, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = pal.onSecondary;
      ctx.lineWidth = 5;
      ctx.beginPath(); ctx.moveTo(x + 12, ry); ctx.lineTo(x + 20, ry + 8); ctx.lineTo(x + 33, ry - 8); ctx.stroke();
      ctx.fillStyle = style.fg;
      const t = fitText(ctx, item, tw - 64, tall ? 40 : 32, 20, 2, 700);
      t.lines.forEach((l, k) => ctx.fillText(l, x + 64, ry + t.size * 0.35 + (k - (t.lines.length - 1) / 2) * t.size * 1.15));
    });
    return;
  }

  if (body.type === 'photos') {
    const cols = columns('photos', H);
    const cellW = w / cols;
    const rowH = ROWS.photos(H);
    const r = Math.min(cellW * 0.36, rowH * 0.32);
    const imgs = await Promise.all(body.people.map((p) => loadImage(p.photo)));
    body.people.forEach((p, i) => {
      const cx = x + cellW * (i % cols) + cellW / 2;
      const cy = y + rowH * Math.floor(i / cols) + r + 6;
      circlePhoto(ctx, imgs[i], cx, cy, r, 6, light ? pal.secondary : pal.paper, pal.soft, pal.primary, p.name);
      ctx.fillStyle = style.fg;
      ctx.textAlign = 'center';
      const name = fitText(ctx, p.name, cellW - 12, tall ? 26 : 22, 15, 2, 700);
      name.lines.forEach((l, k) => ctx.fillText(l, cx, cy + r + 34 + k * name.size * 1.1));
      ctx.textAlign = 'left';
    });
    return;
  }

  if (body.type === 'logos') {
    const cols = 3;
    const gap = 18;
    const cellW = (w - gap * (cols - 1)) / cols;
    const rowH = ROWS.logos(H);
    const imgs = await Promise.all(body.logos.map((l) => loadImage(proxied(kit, l.logo))));
    body.logos.forEach((l, i) => {
      const bx = x + (cellW + gap) * (i % cols);
      const by = y + rowH * Math.floor(i / cols);
      if (imgs[i]) logoTile(ctx, imgs[i], bx, by, cellW, rowH - gap, pal, 20);
      else {
        ctx.fillStyle = style.name === 'poster' || style.name === 'ticket' ? pal.soft : pal.paper;
        roundRect(ctx, bx, by, cellW, rowH - gap, 20);
        ctx.fill();
        ctx.fillStyle = pal.ink;
        ctx.textAlign = 'center';
        const t = fitText(ctx, l.name, cellW - 30, 30, 16, 2, 800);
        t.lines.forEach((line, k) => ctx.fillText(line, bx + cellW / 2, by + (rowH - gap) / 2 + (k - (t.lines.length - 1) / 2) * t.size * 1.1 + t.size * 0.35));
        ctx.textAlign = 'left';
      }
    });
    return;
  }

  if (body.type === 'list') {
    const rowH = ROWS.list(H);
    body.items.forEach((it, i) => {
      const ry = y + i * rowH;
      ctx.fillStyle = style.card.bg;
      roundRect(ctx, x, ry, w, rowH - 14, 22);
      ctx.fill();
      const pw = pill(ctx, it.time, x + 18, ry + (rowH - 14 - 54) / 2, { bg: style.kicker.bg, fg: style.kicker.fg, size: 24, height: 54, pad: 16 });
      ctx.fillStyle = style.card.fg;
      const tx = x + 18 + pw + 22;
      const t = fitText(ctx, it.title, x + w - tx - 20, tall ? 34 : 29, 18, 1, 800);
      ctx.fillText(t.lines[0], tx, ry + (it.meta ? (rowH - 14) * 0.45 : (rowH - 14) * 0.5 + t.size * 0.35));
      if (it.meta) {
        ctx.fillStyle = style.card.muted;
        const m = fitText(ctx, it.meta, x + w - tx - 20, tall ? 26 : 22, 16, 1, 600);
        ctx.fillText(m.lines[0], tx, ry + (rowH - 14) * 0.78);
      }
    });
    return;
  }

  if (body.type === 'tables') {
    const rowH = ROWS.tables(H);
    body.tables.forEach((t, i) => {
      const ry = y + i * rowH;
      ctx.fillStyle = style.card.bg;
      roundRect(ctx, x, ry, w, rowH - 14, 22);
      ctx.fill();
      ctx.fillStyle = style.card.fg;
      const n = fitText(ctx, t.name, w * 0.45, 32, 18, 1, 800);
      ctx.fillText(n.lines[0], x + 28, ry + (rowH - 14) / 2 + n.size * 0.35);
      ctx.fillStyle = style.card.muted;
      ctx.textAlign = 'right';
      const p = fitText(ctx, t.place || (t.leads?.length ? `Leads: ${t.leads.join(', ')}` : ''), w * 0.48, 26, 16, 1, 600);
      ctx.fillText(p.lines[0] ?? '', x + w - 28, ry + (rowH - 14) / 2 + p.size * 0.35);
      ctx.textAlign = 'left';
    });
    return;
  }

  if (body.type === 'session') {
    const photos = body.photos.slice(0, 3);
    const imgs = await Promise.all(photos.map((u) => loadImage(u)));
    const r = Math.min(h / 2 - 12, photos.length > 1 ? 110 : 140);
    photos.forEach((_, i) => circlePhoto(ctx, imgs[i], x + r + 10 + i * (r * 1.45), y + h / 2, r, 8, light ? pal.secondary : pal.paper, pal.soft, pal.primary, body.speakers[i]));
    const tx = x + r * 2 + 50 + (Math.max(photos.length, 1) - 1) * r * 1.45;
    ctx.fillStyle = style.fg;
    const names = fitText(ctx, body.speakers.join(', '), x + w - tx, tall ? 44 : 36, 20, 3, 800);
    names.lines.forEach((l, k) => ctx.fillText(l, tx, y + h / 2 - ((names.lines.length - 1) * names.size * 1.2) / 2 + k * names.size * 1.2 + names.size * 0.35));
  }
}

function proxied(kit, url) {
  if (!url) return null;
  if (!kit.imageUrl || /gravatar\.com\//.test(url) || url.startsWith('/')) return url;
  return `${kit.imageUrl}?u=${encodeURIComponent(url)}`;
}

/**
 * Footer, the same on every card: the event (name, dates, hashtag) on the
 * left, then CampBuddy's mark and a QR to the event in CampBuddy.
 */
function paintFooter(ctx, W, f, style, pal, kit, brand, qrContent, withQr = true) {
  const y = f.footer;
  const h = f.footerH - (f.inset ? 24 : 0);
  const fs = style.footer;
  const x0 = f.inset;
  const x1 = W - f.inset;

  // On the light band under the site's illustration the footer is light too.
  const dark = style.name === 'gradient' && !f.artH;
  if (style.name === 'bold' && !f.artH) {
    ctx.fillStyle = pal.paper;
    ctx.fillRect(0, y, W, h + 40);
    ctx.fillStyle = pal.secondary;
    ctx.fillRect(0, y - 12, W, 12);
  } else if (f.artH) {
    // Nothing to draw: the illustration stands on the footer.
  } else if (dark) {
    ctx.fillStyle = fs.bg;
    roundRect(ctx, f.x - 24, y + 10, f.w + 48, h - 30, 30);
    ctx.fill();
  } else {
    ctx.strokeStyle = alpha(pal.ink, 0.18);
    ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(f.x, y + 6); ctx.lineTo(x1 - SAFE, y + 6); ctx.stroke();
  }

  const qr = withQr ? Math.min(h - 70, f.square ? 120 : 150) : 0;
  const qx = x1 - SAFE - qr;
  const qy = y + (h - qr) / 2 - 8;
  if (qr) {
    drawQr(ctx, qrLink(kit.event.qr, qrContent), qx, qy, qr, pal.ink, pal.paper);
    ctx.font = `700 ${f.square ? 16 : 18}px ${FONT}`;
    ctx.fillStyle = dark ? pal.paper : pal.ink;
    ctx.textAlign = 'center';
    ctx.fillText('Scan · open in CampBuddy', qx + qr / 2, qy + qr + 26);
    ctx.textAlign = 'left';
  }

  // CampBuddy's mark on a light chip, beside the QR.
  const bw = f.square ? 170 : 210;
  const bh = f.square ? 62 : 74;
  const bx = (qr ? qx - 24 : x1 - SAFE) - bw;
  const by = y + (h - bh) / 2 - 8;
  ctx.fillStyle = pal.paper;
  roundRect(ctx, bx, by, bw, bh, 18);
  ctx.fill();
  if (brand) drawContain(ctx, brand, bx + 12, by + 8, bw - 24, bh - 16);

  const tw = bx - 30 - f.x;
  ctx.fillStyle = dark ? pal.paper : pal.ink;
  const name = fitText(ctx, kit.event.name, tw, f.square ? 34 : 42, 20, 2, 800);
  let ty = y + h / 2 - (name.lines.length * name.size * 1.1 + 40) / 2 + name.size * 0.9 - 8;
  name.lines.forEach((l) => { ctx.fillText(l, f.x, ty); ty += name.size * 1.1; });
  ctx.font = `700 ${f.square ? 22 : 26}px ${FONT}`;
  ctx.fillStyle = dark ? pal.secondary : pal.primary;
  ctx.fillText(ellipsize(ctx, [kit.event.dates, kit.event.hashtags.split(' ')[2]].filter(Boolean).join('  ·  '), tw), f.x, ty + 12);
  void x0;
}

/**
 * Draws one card of a post. `page` = {index, total}; `scale` 1 for social,
 * 3 for print (the same design, three times the pixels).
 */
export async function drawEventPost(canvas, size, styleName, content, kit, colors, page = { index: 1, total: 1 }, scale = 1) {
  const [W, H] = SIZES[size];
  canvas.width = W * scale;
  canvas.height = H * scale;
  const ctx = canvas.getContext('2d');
  ctx.setTransform(scale, 0, 0, scale, 0, 0);
  const pal = paletteOf(colors);
  const style = styleOf(styleName, pal);
  const look = { ...eventLook(kit.event.seed), seedValue: kit.event.seed };
  const [logo, mark, brand] = await Promise.all([loadImage(kit.event.logo), loadImage(kit.event.mark), loadImage('/media/logo-wordmark.png')]);
  useArt(content.artIndex ?? 0);
  const f = frame(W, H, styleName, content.artAmount ?? 1);
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  paintBackground(ctx, W, H, f, style, pal, look, mark);
  paintHeader(ctx, W, f, style, pal, look, logo, content, page);

  const m = measureText(ctx, content, f, style);
  const bH = bodyHeight(content.body, H, m.room);
  let y = f.top + Math.max(0, (f.contentBottom - 40 - f.top - (m.textH + (bH ? m.gap : 0) + bH)) / 2);

  ctx.font = `900 ${m.head.size}px ${style.family}`;
  m.head.lines.forEach((l, i) => {
    y += m.head.size * 0.92;
    if (style.name === 'poster' && i === m.head.lines.length - 1) {
      ctx.fillStyle = alpha(pal.secondary, 0.55);
      ctx.fillRect(f.x - 4, y + m.head.size * 0.08, Math.min(ctx.measureText(l).width + 8, f.w), m.head.size * 0.14);
    }
    ctx.fillStyle = style.fg;
    ctx.fillText(l, f.x, y);
    y += m.head.size * 0.14;
  });

  if (content.line) {
    y += 18;
    ctx.fillStyle = style.accent;
    roundRect(ctx, f.x, y, 120, 12, 6);
    ctx.fill();
    y += 16;
    ctx.fillStyle = style.muted;
    ctx.font = `600 ${m.line.size}px ${FONT}`;
    m.line.lines.forEach((l) => { y += m.line.size * 1.1; ctx.fillText(l, f.x, y); y += m.line.size * 0.2; });
  }

  if (bH > 0 && m.room >= 120) await paintBody(ctx, content.body, f.x, y + m.gap, f.w, bH, style, pal, kit, H);
  useArt(content.artIndex ?? 0);

  if (f.artH) {
    // The site's illustration on a light band at the foot, the pattern along its edge.
    const m = f.inset;
    paintArtBand(ctx, W, H, f.contentBottom, f.footer, pal, { clip: m ? () => roundRect(ctx, m, m, W - 2 * m, H - 2 * m, 40) : null });
  }

  paintFooter(ctx, W, f, style, pal, kit, brand, content.key, content.key !== 'app');
  addGrain(ctx, W, H, style.name === 'poster' ? 0.05 : 0.045);
}

// ---- People cards (social posts, not the attendee app's Camp Card) --------

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

/** People cards share the post footer: event, CampBuddy mark, QR (tagged with the role). */
function personFooter(ctx, W, H, pal, kit, brand, person, under) {
  const dark = textOn(under, pal.ink, pal.paper) === pal.paper;
  const f = { ...frame(W, H, 'bold'), inset: 0 };
  const style = dark
    ? { name: 'gradient', footer: { bg: alpha(pal.paper, 0.14) } }
    : { name: 'poster', footer: {} };
  paintFooter(ctx, W, f, style, pal, kit, brand, `people-${String(person.roles?.[0] ?? 'camp-card').replace(/_/g, '-')}`);
  return f.footer;
}

export async function drawPersonCard(canvas, size, design, person, kit, colors, scale = 1) {
  const [W, H] = SIZES[size];
  canvas.width = W * scale;
  canvas.height = H * scale;
  const ctx = canvas.getContext('2d');
  ctx.setTransform(scale, 0, 0, scale, 0, 0);
  const [photo, mark, brand] = await Promise.all([loadImage(person.photo), loadImage(kit.event.mark), loadImage('/media/logo-wordmark.png')]);
  useArt(person.artIndex ?? 0);
  const pal = paletteOf(colors);
  const look = eventLook(kit.event.seed);
  const rc = roleColor(person, colors);
  const onRc = textOn(rc, pal.ink, pal.paper);
  const { roles, talk } = personLines(person);
  const label = personHeadline(person, kit).toUpperCase();
  const tall = H >= 1900;
  const square = H < 1100;
  const footerTop = H - (square ? 200 : (tall ? 260 : 230));
  // The site's illustration (a skyline…) stands on the footer in a light band of its own.
  const band = artBand(W, H, ART.illustration, square ? 0.7 : (tall ? 1 : 0.8));
  const fTop = footerTop - band;
  // The foot of the card: the band and footer, or (no illustration) the footer on `under`.
  const foot = (under) => {
    if (band) paintArtBand(ctx, W, H, fTop, footerTop, pal);
    personFooter(ctx, W, H, pal, kit, brand, person, band ? pal.paper : under);
  };
  const wallPattern = (color, opacity) => ART.pattern && fillWithPattern(ctx, ART.pattern, color, 0, 0, W, H, 200, opacity);
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  if (design === 'polaroid') {
    // A tilted instant photo taped onto a wall in the role's tone.
    ctx.fillStyle = rc;
    ctx.fillRect(0, 0, W, H);
    if (!wallPattern(onRc, 0.14)) watermark(ctx, mark, W, H, 0.07);
    pill(ctx, label, W / 2, SAFE - 10, { bg: pal.paper, fg: pal.ink, size: 28, height: 60, align: 'center' });
    const room = fTop - (SAFE + 80) - (talk && !square ? (tall ? 220 : 120) : 30);
    const ph = Math.min(room, tall ? 1000 : 900);
    const pw = ph - 150;
    const cx = W / 2;
    const cy = SAFE + 80 + 20 + ph / 2;
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(-0.04 * look.tilt);
    ctx.shadowColor = alpha(pal.ink, 0.4); ctx.shadowBlur = 36; ctx.shadowOffsetY = 16;
    ctx.fillStyle = pal.paper;
    ctx.fillRect(-pw / 2, -ph / 2, pw, ph);
    ctx.shadowColor = 'transparent';
    const inner = pw - 50;
    ctx.save(); ctx.beginPath(); ctx.rect(-inner / 2, -ph / 2 + 25, inner, inner); ctx.clip();
    if (photo) drawCover(ctx, photo, -inner / 2, -ph / 2 + 25, inner, inner);
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
      const t = fitText(ctx, talk, W - 2 * SAFE, tall ? 42 : 30, 20, tall ? 3 : 2, 700);
      const ty = cy + ph / 2 + (tall ? 90 : 60);
      t.lines.forEach((l, i) => ctx.fillText(l, W / 2, ty + i * t.size * 1.25));
      ctx.textAlign = 'left';
    }
    foot(rc);
    addGrain(ctx, W, H);
    return;
  }

  if (design === 'badge') {
    // A conference badge on a lanyard.
    const wall = pal.soft;
    ctx.fillStyle = wall;
    ctx.fillRect(0, 0, W, H);
    wallPattern(pal.primary, 0.1);
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
    if (mark) logoTile(ctx, mark, bx + 28, by + (square ? 18 : 30), 70, 70, pal, 14);
    ctx.fillStyle = pal.onPrimary;
    const ev = fitText(ctx, kit.event.name, bw - 150, 30, 18, 1, 800);
    ctx.fillText(ev.lines[0], bx + 116, by + (square ? 62 : 76));
    const bandH = square ? 80 : 110;
    ctx.fillStyle = rc; ctx.fillRect(bx, by + bh - bandH, bw, bandH);
    ctx.fillStyle = onRc; ctx.textAlign = 'center';
    const rl = fitText(ctx, (person.role_labels?.[0] ?? 'Attendee').toUpperCase(), bw - 50, tall ? 56 : (square ? 36 : 46), 22, 1, 900);
    ctx.fillText(rl.lines[0], bx + bw / 2, by + bh - bandH / 2 + rl.size * 0.35);
    ctx.restore();
    ctx.fillStyle = wall;
    roundRect(ctx, W / 2 - 46, by + 16, 92, 20, 10); ctx.fill();
    const inner = bh - (square ? 100 : 130) - bandH;
    const r = Math.min(bw * (tall ? 0.34 : 0.26), inner * (talk ? 0.26 : 0.3));
    const cy = by + (square ? 100 : 130) + 26 + r;
    circlePhoto(ctx, photo, W / 2, cy, r, 8, rc, pal.soft, pal.primary, person.name);
    ctx.fillStyle = pal.ink; ctx.textAlign = 'center';
    const n = fitText(ctx, person.name, bw - 70, tall ? 84 : (square ? 46 : 64), 26, 2, 900);
    let y = cy + r + 14;
    n.lines.forEach((l) => { y += n.size; ctx.fillText(l, W / 2, y); y += n.size * 0.06; });
    const room = by + bh - bandH - 16 - y;
    if (talk && room > 40) {
      ctx.fillStyle = alpha(pal.ink, 0.75);
      const t = fitText(ctx, talk, bw - 90, tall ? 36 : 26, 16, Math.max(1, Math.min(3, Math.floor(room / 36))), 600);
      y += 8;
      t.lines.forEach((l) => { y += t.size * 1.2; ctx.fillText(l, W / 2, y); });
    }
    ctx.textAlign = 'left';
    foot(wall);
    addGrain(ctx, W, H, 0.04);
    return;
  }

  if (design === 'split') {
    // Photo across the top, a block in the role's tone with the words below.
    const ph = Math.round(fTop * (square ? 0.5 : 0.56));
    if (photo) drawCover(ctx, photo, 0, 0, W, ph);
    else { ctx.fillStyle = pal.soft; ctx.fillRect(0, 0, W, ph); ctx.fillStyle = pal.primary; ctx.textAlign = 'center'; ctx.font = `900 ${Math.round(ph * 0.4)}px ${FONT}`; ctx.fillText(initials(person.name), W / 2, ph * 0.64); ctx.textAlign = 'left'; }
    const g = ctx.createLinearGradient(0, ph * 0.5, 0, ph);
    g.addColorStop(0, alpha(rc, 0));
    g.addColorStop(1, alpha(rc, 0.9));
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, ph);
    ctx.fillStyle = rc;
    const s = look.tilt * 40;
    ctx.beginPath(); ctx.moveTo(0, ph - 40 - s); ctx.lineTo(W, ph - 40 + s); ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();
    if (ART.pattern) {
      ctx.save();
      ctx.beginPath(); ctx.moveTo(0, ph - 40 - s); ctx.lineTo(W, ph - 40 + s); ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.clip();
      fillWithPattern(ctx, ART.pattern, onRc, 0, ph - 120, W, H, 200, 0.08);
      ctx.restore();
    }
    pill(ctx, label, SAFE, SAFE - 10, { bg: pal.paper, fg: pal.ink, size: 26, height: 58 });
    let y = ph + (square ? 50 : 90);
    ctx.fillStyle = onRc;
    const n = fitText(ctx, person.name, W - 2 * SAFE, tall ? 100 : (square ? 60 : 84), 36, 2, 900);
    n.lines.forEach((l) => { ctx.fillText(l, SAFE, y); y += n.size * 1.04; });
    ctx.font = `800 30px ${FONT}`;
    ctx.fillStyle = alpha(onRc, 0.85);
    if (roles) { ctx.fillText(ellipsize(ctx, roles, W - 2 * SAFE), SAFE, y + 6); y += 56; }
    const room = fTop - 30 - y;
    if (talk && room > 40) {
      ctx.fillStyle = onRc;
      const t = fitText(ctx, talk, W - 2 * SAFE, tall ? 42 : 34, 18, Math.max(1, Math.min(4, Math.floor(room / 44))), 600);
      t.lines.forEach((l) => { ctx.fillText(l, SAFE, y); y += t.size * 1.3; });
    }
    if (!band) {
      ctx.fillStyle = mix(rc, pal.ink, 0.35);
      ctx.fillRect(0, footerTop - 20, W, H - footerTop + 20);
    }
    foot(mix(rc, pal.ink, 0.35));
    addGrain(ctx, W, H);
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
    ctx.font = `800 26px ${FONT}`;
    ctx.fillStyle = rc;
    ctx.textAlign = photoLeft ? 'left' : 'right';
    ctx.fillText(label, photoLeft ? SAFE + 30 : W - SAFE - 30, SAFE + 50);
    ctx.textAlign = 'left';
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
      const t = fitText(ctx, talk, W - 2 * SAFE - 60, tall ? 44 : 32, 18, Math.max(1, Math.min(4, Math.floor(room / 42))), 500, SERIF);
      ctx.font = `italic 500 ${t.size}px ${SERIF}`;
      t.lines.forEach((l) => { ctx.fillText(l, SAFE + 30, y); y += t.size * 1.3; });
    }
    foot(pal.paper);
    addGrain(ctx, W, H, 0.035);
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
  watermark(ctx, mark, W, H, 0.08);
  if (!(ART.pattern && fillWithPattern(ctx, ART.pattern, pal.paper, 0, 0, W, top + 80, 200, 0.12))) {
    drawMotif(ctx, look.motif, SAFE, SAFE + 70, 260, 150, alpha(pal.paper, 0.3), alpha(pal.secondary, 0.8), seeded(kit.event.seed));
  }
  ctx.restore();
  ctx.strokeStyle = rc === pal.secondary ? pal.primary : pal.secondary;
  ctx.lineWidth = 14;
  ctx.beginPath(); ctx.moveTo(0, top + s + 16); ctx.lineTo(W, top - s + 16); ctx.stroke();
  pill(ctx, label, W / 2, SAFE - 10, { bg: pal.paper, fg: pal.ink, size: 30, height: 64, align: 'center' });
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
    const t = fitText(ctx, talk, W - 2 * SAFE - 40, tall ? 48 : 36, 18, Math.max(1, Math.min(4, Math.floor(room / 46))), 500);
    t.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += t.size * 1.3; });
  }
  ctx.textAlign = 'left';
  foot(pal.paper);
  addGrain(ctx, W, H, 0.035);
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
    SITE_ART[kind] = (await Promise.all(pieces.map((a) => loadImage(proxied(kit, a.url))))).filter(Boolean);
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
  kit.people.forEach((person, i) => { person.artIndex = i; });
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

    const replan = () => {
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

    return redraw;
  });

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
