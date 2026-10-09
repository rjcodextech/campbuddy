// Admin / manager → Event → Social media (partials/social-media.blade.php).
// Draws the event's posts and people cards on canvases, in the event's brand
// colours, from App\Support\SocialKit::data(): four post styles (Bold,
// Poster, Ticket, Gradient), five people-card designs, three sizes (feed
// portrait, square, story). Downloads PNGs, a ZIP of the people cards,
// copies captions, opens share links, and — if a webhook is set — hands a
// post to Publish. Nothing here talks to a social network itself.

import QRCode from 'qrcode-generator';
import { zipStore } from './zip-store.js';

const SIZES = { '1080x1350': [1080, 1350], '1080x1080': [1080, 1080], '1080x1920': [1080, 1920] };
const FONT = '"Inter", "Segoe UI", system-ui, -apple-system, sans-serif';
const SERIF = '"Playfair Display", Georgia, "Times New Roman", serif';
const PEOPLE_PAGE = 8;

/** A colour per role, so a card says at a glance who someone is. */
export const ROLE_COLORS = {
  organizer: null, // the event's main colour
  speaker: null, // the event's accent colour
  volunteer: '#1f8a4c',
  sponsor: '#1d4f91',
  media_partner: '#7a2d9c',
  microsponsor: '#b77900',
  table_lead: '#0e7c7b',
};

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

/** White or the dark text colour, whichever reads better on `background`. */
export function textOn(background, dark = '#231f20') {
  return luminance(hexToRgb(background)) > 0.4 ? dark : '#ffffff';
}

/** `hex` mixed toward `toward` by `amount` (0–1). */
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
 * The logo's two strongest colours: pixels that are clearly coloured (not
 * white, black or grey), grouped by hue, weighted by how saturated they are.
 * The main colour is darkened if needed so white text on it stays readable.
 * Returns null when the logo has no real colour (then the defaults stay).
 */
export function paletteFromPixels(data) {
  const buckets = new Map();
  for (let i = 0; i < data.length; i += 4) {
    if (data[i + 3] < 200) continue;
    const rgb = [data[i], data[i + 1], data[i + 2]];
    const [h, s, l] = rgbToHsl(rgb);
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

  let primary = avg(ranked[0][1]);
  for (let i = 0; i < 6 && luminance(primary) > 0.25; i++) primary = primary.map((v) => v * 0.8);
  const second = ranked.find(([key]) => Math.min(Math.abs(key - ranked[0][0]), 12 - Math.abs(key - ranked[0][0])) >= 2);

  return { primary: rgbToHex(primary), secondary: second ? rgbToHex(avg(second[1])) : null };
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
      // A failed image isn't remembered: the next redraw tries again (a busy server, a slow logo).
      img.onerror = () => { images.delete(url); resolve(null); };
      img.src = url;
    }));
  }
  return images.get(url);
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
  const lines = wrap(ctx, text, maxWidth).slice(0, maxLines);
  return { lines: lines.map((l) => ellipsize(ctx, l, maxWidth)), size: min };
}

function ellipsize(ctx, text, maxWidth) {
  if (ctx.measureText(text).width <= maxWidth) return text;
  let t = text;
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

function circlePhoto(ctx, img, cx, cy, r, ring, ringColor, fallback, name) {
  if (ring) {
    ctx.fillStyle = ringColor;
    ctx.beginPath(); ctx.arc(cx, cy, r + ring, 0, Math.PI * 2); ctx.fill();
  }
  ctx.save();
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.clip();
  if (img) drawCover(ctx, img, cx - r, cy - r, 2 * r, 2 * r);
  else {
    ctx.fillStyle = fallback;
    ctx.fillRect(cx - r, cy - r, 2 * r, 2 * r);
    ctx.fillStyle = textOn(fallback);
    ctx.font = `800 ${Math.round(r * 0.8)}px ${FONT}`;
    ctx.textAlign = 'center';
    ctx.fillText(initials(name), cx, cy + r * 0.3);
  }
  ctx.restore();
}

function initials(name) {
  return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('') || '?';
}

function qrCanvas(url, size) {
  const qr = QRCode(0, 'M');
  qr.addData(url);
  qr.make();
  const count = qr.getModuleCount();
  const cell = Math.max(1, Math.floor(size / (count + 4)));
  const c = document.createElement('canvas');
  c.width = c.height = cell * (count + 4);
  const ctx = c.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, c.width, c.height);
  ctx.fillStyle = '#000';
  for (let r = 0; r < count; r++) for (let col = 0; col < count; col++) if (qr.isDark(r, col)) ctx.fillRect((col + 2) * cell, (r + 2) * cell, cell, cell);
  return c;
}

// A fine grain over the whole image, so flat colour looks printed, not plastic.
let grain = null;
function addGrain(ctx, W, H, strength = 0.05) {
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

// The event's mark, small and tilted, repeated across the background.
function watermark(ctx, mark, W, H, opacity = 0.07, step = 210) {
  if (!mark) return;
  ctx.save();
  ctx.globalAlpha = opacity;
  for (let row = 0, y = -40; y < H + step; row++, y += step) {
    for (let x = row % 2 ? step / 2 - 40 : -40; x < W + step; x += step) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(-0.26);
      drawContain(ctx, mark, -45, -45, 90, 90);
      ctx.restore();
    }
  }
  ctx.restore();
}

function dotGrid(ctx, x, y, cols, rows, gap, r, color) {
  ctx.fillStyle = color;
  for (let i = 0; i < cols; i++) for (let j = 0; j < rows; j++) {
    ctx.beginPath(); ctx.arc(x + i * gap, y + j * gap, r, 0, Math.PI * 2); ctx.fill();
  }
}

function rings(ctx, cx, cy, from, count, gap, color, width = 3) {
  ctx.strokeStyle = color;
  ctx.lineWidth = width;
  for (let i = 0; i < count; i++) {
    ctx.beginPath(); ctx.arc(cx, cy, from + i * gap, 0, Math.PI * 2); ctx.stroke();
  }
}

function pill(ctx, text, x, y, { bg, fg, size = 30, pad = 26, height = 60, align = 'left' }) {
  ctx.font = `800 ${size}px ${FONT}`;
  const w = ctx.measureText(text).width + pad * 2;
  const left = align === 'center' ? x - w / 2 : x;
  ctx.fillStyle = bg;
  roundRect(ctx, left, y, w, height, height / 2);
  ctx.fill();
  ctx.fillStyle = fg;
  ctx.textAlign = 'left';
  ctx.fillText(text, left + pad, y + height / 2 + size * 0.36);
  return w;
}

// ---- What a post shows ----------------------------------------------------

/** Session list for the Today post: that day's talks (or all its sessions if few). */
export function sessionsOfDay(sessions, day, max = 7) {
  const ofDay = (sessions ?? []).filter((s) => s.day === day);
  const talks = ofDay.filter((s) => s.speakers?.length);
  return (talks.length >= 3 ? talks : ofDay).slice(0, max);
}

export function todayCaption(kit, day, list) {
  const label = kit.schedule.days.find((d) => d.key === day)?.label ?? '';
  const lines = list.map((s) => `${s.time}  ${s.title}${s.speakers?.length ? ` (${s.speakers.join(', ')})` : ''}`);
  return `${label} at ${kit.event.name}:\n\n${lines.join('\n')}\n\nThe full schedule, with reminders: ${kit.event.app}\n\n${kit.event.hashtags}`;
}

export function spotlightCaption(kit, s) {
  if (!s) return '';
  const who = s.speakers?.length ? ` with ${s.speakers.join(', ')}` : '';
  const where = s.track ? ` in ${s.track}` : '';
  return `Up next at ${kit.event.name}: "${s.title}"${who}, ${s.time}${where}.\n\nSave it in CampBuddy and get a reminder: ${kit.event.app}\n\n${kit.event.hashtags}`;
}

/** The drawable content of a post: kicker, headline, line, and a body. */
export function postContent(post, fields, kit, pick) {
  const base = { key: post.key, kicker: post.kicker ?? '', headline: fields.headline, line: fields.line, body: { type: 'none' } };

  switch (post.key) {
    case 'countdown':
      return { ...base, body: post.days ? { type: 'countdown', days: post.days } : { type: 'none' } };
    case 'app':
      return { ...base, body: { type: 'qr', url: kit.event.app } };
    case 'speakers':
      return { ...base, body: { type: 'photos', people: kit.speakers.filter((p) => p.photo).slice(0, 12) } };
    case 'sponsors':
      return { ...base, body: { type: 'logos', logos: kit.sponsors.slice(0, 12) } };
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
      return { ...base, body: kit.tables.length ? { type: 'tables', tables: kit.tables.slice(0, 6) } : { type: 'none' } };
    default:
      return base;
  }
}

// ---- Styles ---------------------------------------------------------------
// Each style paints the background and says which colours sit on it; the
// header, text block, body and footer are laid out the same way for all.

function styleOf(name, colors) {
  const { primary, secondary, ink, paper } = colors;
  switch (name) {
    case 'poster':
      return { name, fg: ink, muted: alpha(ink, 0.75), accent: primary, kicker: { bg: primary, fg: textOn(primary) }, card: { bg: '#ffffff', fg: ink }, headlineFamily: SERIF, headlineWeight: 900, logoTile: false };
    case 'ticket':
      return { name, fg: ink, muted: alpha(ink, 0.72), accent: primary, kicker: { bg: secondary, fg: textOn(secondary) }, card: { bg: mix(paper, primary, 0.08), fg: ink }, cardBg: mix(paper, primary, 0.03), headlineFamily: FONT, headlineWeight: 900, logoTile: true };
    case 'gradient':
      return { name, fg: '#ffffff', muted: 'rgba(255,255,255,0.86)', accent: '#ffffff', kicker: { bg: 'rgba(255,255,255,0.2)', fg: '#ffffff' }, card: { bg: 'rgba(255,255,255,0.14)', fg: '#ffffff' }, headlineFamily: FONT, headlineWeight: 900, logoTile: true };
    default:
      return { name: 'bold', fg: textOn(primary), muted: alpha(textOn(primary), 0.86), accent: secondary, kicker: { bg: secondary, fg: textOn(secondary) }, card: { bg: 'rgba(255,255,255,0.12)', fg: textOn(primary) }, headlineFamily: FONT, headlineWeight: 900, logoTile: true };
  }
}

const TICKET_MARGIN = 54;

/** Paints the background; returns the content box {x, y, w, footer}. */
function paintBackground(ctx, W, H, style, colors, mark) {
  const { primary, secondary, ink, paper } = colors;

  if (style.name === 'poster') {
    ctx.fillStyle = paper;
    ctx.fillRect(0, 0, W, H);
    // A big disc and a half-disc in the brand colours, a dot grid, a corner triangle.
    // A disc peeking in at the bottom right (beside the footer, not under it),
    // a half-disc at the top, rings and a dot grid in the corner.
    ctx.fillStyle = primary;
    ctx.beginPath(); ctx.arc(W + 40, H + 40, 300, 0, Math.PI * 2); ctx.fill();
    rings(ctx, W + 40, H + 40, 340, 3, 28, alpha(primary, 0.35), 3);
    ctx.fillStyle = secondary;
    ctx.beginPath(); ctx.arc(W * 0.84, 0, W * 0.18, 0, Math.PI); ctx.fill();
    dotGrid(ctx, W - 250, 230, 6, 4, 28, 4, alpha(primary, 0.45));
    ctx.fillStyle = ink;
    ctx.fillRect(0, 0, 18, H);
    return { x: 80, y: 80, w: W - 160, footer: H - 200 };
  }

  if (style.name === 'ticket') {
    const m = TICKET_MARGIN;
    const stub = Math.round(H * (H >= 1900 ? 0.18 : (H < 1100 ? 0.17 : 0.22)));
    ctx.fillStyle = mix(primary, '#000000', 0.3);
    ctx.fillRect(0, 0, W, H);
    watermark(ctx, mark, W, H, 0.09);
    // The ticket: rounded card, a coloured stub on top, notches and a perforation.
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.4)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 14;
    ctx.fillStyle = style.cardBg;
    roundRect(ctx, m, m, W - 2 * m, H - 2 * m, 40);
    ctx.fill();
    ctx.restore();
    ctx.save();
    roundRect(ctx, m, m, W - 2 * m, H - 2 * m, 40);
    ctx.clip();
    ctx.fillStyle = primary;
    ctx.fillRect(m, m, W - 2 * m, stub);
    rings(ctx, W - m - 80, m + stub / 2, 50, 5, 34, alpha('#ffffff', 0.16), 3);
    ctx.restore();
    ctx.fillStyle = mix(primary, '#000000', 0.3);
    for (const x of [m, W - m]) { ctx.beginPath(); ctx.arc(x, m + stub, 32, 0, Math.PI * 2); ctx.fill(); }
    ctx.strokeStyle = alpha(ink, 0.3);
    ctx.setLineDash([16, 14]);
    ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(m + 50, m + stub); ctx.lineTo(W - m - 50, m + stub); ctx.stroke();
    ctx.setLineDash([]);
    return { x: m + 56, y: m + stub + 50, w: W - 2 * m - 112, footer: H - m - 150, stub: { top: m, height: stub } };
  }

  if (style.name === 'gradient') {
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, mix(primary, '#000000', 0.2));
    g.addColorStop(0.55, primary);
    g.addColorStop(1, mix(primary, secondary, 0.75));
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
    for (const [x, y, r, c, a] of [[W * 0.95, H * 0.1, W * 0.6, secondary, 0.55], [0, H * 0.7, W * 0.55, mix(secondary, '#ffffff', 0.35), 0.35], [W * 0.6, H * 1.05, W * 0.5, ink, 0.4]]) {
      const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
      rg.addColorStop(0, alpha(c, a));
      rg.addColorStop(1, alpha(c, 0));
      ctx.fillStyle = rg;
      ctx.fillRect(0, 0, W, H);
    }
    watermark(ctx, mark, W, H, 0.05, 240);
    return { x: 80, y: 80, w: W - 160, footer: H - 210 };
  }

  // Bold
  ctx.fillStyle = primary;
  ctx.fillRect(0, 0, W, H);
  watermark(ctx, mark, W, H, 0.07);
  ctx.globalAlpha = 0.28;
  ctx.fillStyle = secondary;
  ctx.beginPath(); ctx.arc(W * 0.98, H * 0.04, W * 0.4, 0, Math.PI * 2); ctx.fill();
  ctx.globalAlpha = 1;
  rings(ctx, W * 0.98, H * 0.04, W * 0.46, 3, 30, alpha('#ffffff', 0.18), 3);
  ctx.fillStyle = paper;
  ctx.fillRect(0, H - 210, W, 210);
  ctx.fillStyle = secondary;
  ctx.fillRect(0, H - 222, W, 12);
  return { x: 80, y: 80, w: W - 160, footer: H - 222 };
}

/** Logo and kicker; returns where the text block may start. */
function paintHeader(ctx, box, style, colors, logo, content) {
  if (style.name === 'ticket') {
    // On the ticket's coloured stub.
    const { top, height } = box.stub;
    const tileH = Math.min(150, height - 60);
    const tileW = tileH * 1.75;
    ctx.fillStyle = '#fff';
    roundRect(ctx, box.x, top + (height - tileH) / 2, tileW, tileH, 24);
    ctx.fill();
    if (logo) drawContain(ctx, logo, box.x + 16, top + (height - tileH) / 2 + 14, tileW - 32, tileH - 28);
    if (content.kicker) {
      ctx.font = `800 30px ${FONT}`;
      ctx.fillStyle = textOn(colors.primary);
      ctx.textAlign = 'right';
      ctx.fillText(String(content.kicker).toUpperCase(), box.x + box.w, top + height / 2 + 10);
      ctx.textAlign = 'left';
    }
    return box.y;
  }

  let y = box.y;
  const compact = box.compact;
  const tw = compact ? 220 : 290;
  const th = compact ? 128 : 170;
  if (style.logoTile) {
    ctx.fillStyle = '#fff';
    roundRect(ctx, box.x, y, tw, th, compact ? 22 : 28);
    ctx.fill();
    if (logo) drawContain(ctx, logo, box.x + 16, y + 14, tw - 32, th - 28);
  } else if (logo) {
    drawContain(ctx, logo, box.x, y, tw, th);
  }
  if (compact && content.kicker) {
    // On a square the kicker sits beside the logo.
    pill(ctx, String(content.kicker).toUpperCase(), box.x + tw + 24, y + (th - 54) / 2, { bg: style.kicker.bg, fg: style.kicker.fg, size: 26, height: 54 });
    return y + th + 30;
  }
  y += th + 40;
  if (content.kicker) {
    pill(ctx, String(content.kicker).toUpperCase(), box.x, y, { bg: style.kicker.bg, fg: style.kicker.fg, size: 28, height: 58 });
    y += 82;
  }
  return y;
}

/**
 * The body made to fit `room` pixels: lists, tables, photo and logo grids
 * lose whole rows; the QR, countdown and session shrink; below 150 px there
 * is no body at all. Returns {body, h}.
 */
export function fitBody(body, H, room) {
  let h = bodyHeight(body, H);
  if (h <= room) return { body, h };
  const minimum = body.type === 'list' || body.type === 'tables' ? (H >= 1900 ? 140 : 108) : 150;
  if (room < minimum) return { body: { type: 'none' }, h: 0 };
  const tall = H >= 1900;
  const trim = (key, rowH, perRow) => {
    const rows = Math.max(1, Math.floor(room / rowH));
    const next = { ...body, [key]: body[key].slice(0, rows * perRow) };
    return { body: next, h: bodyHeight(next, H) };
  };
  switch (body.type) {
    case 'list': return trim('items', tall ? 140 : 108, 1);
    case 'tables': return trim('tables', tall ? 120 : 98, 1);
    case 'logos': return trim('logos', tall ? 170 : 130, 3);
    case 'photos': {
      const n = Math.min(body.people.length, tall ? 12 : (H > 1100 ? 8 : 4));
      const cols = Math.max(1, n > 6 || (n > 4 && !tall) ? 4 : Math.min(4, n));
      return trim('people', tall ? 250 : 210, cols);
    }
    default:
      return { body, h: room };
  }
}

function bodyHeight(body, H) {
  const tall = H >= 1900;
  switch (body.type) {
    case 'countdown': return tall ? 400 : (H > 1100 ? 300 : 240);
    case 'qr': return tall ? 400 : (H > 1100 ? 330 : 270);
    case 'photos': {
      const n = Math.min(body.people.length, tall ? 12 : (H > 1100 ? 8 : 4));
      const cols = n > 6 || (n > 4 && !tall) ? 4 : Math.min(4, n);
      return n ? Math.ceil(n / Math.max(cols, 1)) * (tall ? 250 : 210) : 0;
    }
    case 'logos': {
      const n = Math.min(body.logos.length, tall ? 12 : (H > 1100 ? 9 : 6));
      return n ? Math.ceil(n / 3) * (tall ? 170 : 130) : 0;
    }
    case 'list': return Math.min(body.items.length, tall ? 7 : (H > 1100 ? 5 : 3)) * (tall ? 140 : 108);
    case 'tables': return Math.min(body.tables.length, tall ? 6 : (H > 1100 ? 4 : 3)) * (tall ? 120 : 98);
    case 'session': return body.photos.length ? (tall ? 340 : (H > 1100 ? 270 : 220)) : 0;
    default: return 0;
  }
}

async function paintBody(ctx, body, x, y, w, h, style, colors, kit, H) {
  const tall = H >= 1900;
  const card = style.card;
  const light = style.name === 'poster' || style.name === 'ticket';

  if (body.type === 'countdown') {
    const r = h / 2 - 8;
    const cx = x + r + 8;
    const cy = y + h / 2;
    ctx.fillStyle = light ? colors.primary : 'rgba(255,255,255,0.16)';
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = light ? colors.secondary : alpha('#ffffff', 0.6);
    ctx.lineWidth = 12;
    ctx.lineCap = 'round';
    ctx.beginPath(); ctx.arc(cx, cy, r - 16, -Math.PI / 2, Math.PI * 1.15); ctx.stroke();
    ctx.lineCap = 'butt';
    ctx.fillStyle = light ? textOn(colors.primary) : '#ffffff';
    ctx.textAlign = 'center';
    const digits = String(body.days);
    ctx.font = `900 ${Math.round(r * (digits.length > 2 ? 0.7 : 0.95))}px ${FONT}`;
    ctx.fillText(digits, cx, cy + r * 0.32);
    ctx.textAlign = 'left';
    ctx.fillStyle = style.fg;
    ctx.font = `900 ${tall ? 72 : 60}px ${FONT}`;
    ctx.fillText(body.days === 1 ? 'day' : 'days', cx + r + 40, cy);
    ctx.font = `700 ${tall ? 42 : 36}px ${FONT}`;
    ctx.fillStyle = style.muted;
    ctx.fillText('to go', cx + r + 40, cy + 54);
    return;
  }

  if (body.type === 'qr') {
    const side = h - 40;
    const qr = qrCanvas(body.url, side);
    ctx.fillStyle = '#fff';
    roundRect(ctx, x, y, side + 40, side + 40, 28);
    ctx.fill();
    ctx.drawImage(qr, x + 20, y + 20, side, side);
    const tx = x + side + 90;
    ctx.fillStyle = style.fg;
    ctx.font = `900 ${tall ? 56 : 46}px ${FONT}`;
    ctx.fillText('Scan me', tx, y + side / 2 - 10);
    ctx.font = `700 ${tall ? 34 : 28}px ${FONT}`;
    ctx.fillStyle = style.muted;
    ['Free · no sign-up', 'Works offline'].forEach((t, i) => ctx.fillText(t, tx, y + side / 2 + 44 + i * 42));
    return;
  }

  if (body.type === 'photos') {
    const people = body.people.slice(0, tall ? 12 : (H > 1100 ? 8 : 4));
    const cols = Math.max(1, people.length > 6 || (people.length > 4 && !tall) ? 4 : Math.min(4, people.length));
    const cellW = w / cols;
    const rowH = tall ? 250 : 210;
    const r = Math.min(cellW * 0.36, rowH * 0.34);
    const imgs = await Promise.all(people.map((p) => loadImage(p.photo)));
    people.forEach((p, i) => {
      const cx = x + cellW * (i % cols) + cellW / 2;
      const cy = y + rowH * Math.floor(i / cols) + r + 6;
      circlePhoto(ctx, imgs[i], cx, cy, r, 6, light ? colors.secondary : '#ffffff', colors.secondary, p.name);
      ctx.fillStyle = style.fg;
      ctx.textAlign = 'center';
      const name = fitText(ctx, p.name, cellW - 16, tall ? 26 : 22, 16, 1, 700);
      ctx.fillText(name.lines[0], cx, cy + r + 36);
      ctx.textAlign = 'left';
    });
    return;
  }

  if (body.type === 'logos') {
    const logos = body.logos.slice(0, tall ? 12 : (H > 1100 ? 9 : 6));
    const cols = 3;
    const gap = 18;
    const cellW = (w - gap * (cols - 1)) / cols;
    const rowH = tall ? 170 : 130;
    const imgs = await Promise.all(logos.map((l) => loadImage(proxied(kit, l.logo))));
    logos.forEach((l, i) => {
      const bx = x + (cellW + gap) * (i % cols);
      const by = y + rowH * Math.floor(i / cols);
      ctx.fillStyle = '#fff';
      roundRect(ctx, bx, by, cellW, rowH - gap, 20);
      ctx.fill();
      if (imgs[i]) drawContain(ctx, imgs[i], bx + 22, by + 16, cellW - 44, rowH - gap - 32);
      else {
        ctx.fillStyle = colors.ink;
        ctx.textAlign = 'center';
        const t = fitText(ctx, l.name, cellW - 30, 30, 18, 2, 800);
        t.lines.forEach((line, k) => ctx.fillText(line, bx + cellW / 2, by + (rowH - gap) / 2 + (k - (t.lines.length - 1) / 2) * t.size * 1.1 + t.size * 0.35));
        ctx.textAlign = 'left';
      }
    });
    return;
  }

  if (body.type === 'list') {
    const items = body.items.slice(0, tall ? 7 : (H > 1100 ? 5 : 3));
    const rowH = tall ? 140 : 108;
    items.forEach((it, i) => {
      const ry = y + i * rowH;
      ctx.fillStyle = card.bg;
      roundRect(ctx, x, ry, w, rowH - 14, 22);
      ctx.fill();
      const pw = pill(ctx, it.time, x + 18, ry + (rowH - 14 - 54) / 2, { bg: style.kicker.bg, fg: style.kicker.fg, size: 24, height: 54, pad: 16 });
      ctx.fillStyle = card.fg;
      const tx = x + 18 + pw + 22;
      const t = fitText(ctx, it.title, x + w - tx - 20, tall ? 34 : 29, 20, 1, 800);
      ctx.fillText(t.lines[0], tx, ry + (it.meta ? (rowH - 14) * 0.45 : (rowH - 14) * 0.5 + t.size * 0.35));
      if (it.meta) {
        ctx.fillStyle = style.name === 'gradient' || style.name === 'bold' ? 'rgba(255,255,255,0.8)' : alpha(colors.ink, 0.7);
        const m = fitText(ctx, it.meta, x + w - tx - 20, tall ? 26 : 22, 16, 1, 600);
        ctx.fillText(m.lines[0], tx, ry + (rowH - 14) * 0.78);
      }
    });
    return;
  }

  if (body.type === 'tables') {
    const tables = body.tables.slice(0, tall ? 6 : (H > 1100 ? 4 : 3));
    const rowH = tall ? 120 : 98;
    tables.forEach((t, i) => {
      const ry = y + i * rowH;
      ctx.fillStyle = card.bg;
      roundRect(ctx, x, ry, w, rowH - 14, 22);
      ctx.fill();
      ctx.fillStyle = card.fg;
      const n = fitText(ctx, t.name, w * 0.45, 32, 20, 1, 800);
      ctx.fillText(n.lines[0], x + 28, ry + (rowH - 14) / 2 + n.size * 0.35);
      ctx.fillStyle = style.muted;
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
    photos.forEach((_, i) => {
      circlePhoto(ctx, imgs[i], x + r + 10 + i * (r * 1.45), y + h / 2, r, 8, light ? colors.secondary : '#ffffff', colors.secondary, body.speakers[i]);
    });
    const tx = x + r * 2 + 50 + (Math.max(photos.length, 1) - 1) * r * 1.45;
    ctx.fillStyle = style.fg;
    const names = fitText(ctx, body.speakers.join(', '), x + w - tx, tall ? 44 : 36, 22, 3, 800);
    names.lines.forEach((l, k) => ctx.fillText(l, tx, y + h / 2 - ((names.lines.length - 1) * names.size * 1.2) / 2 + k * names.size * 1.2 + names.size * 0.35));
  }
}

function proxied(kit, url) {
  if (!url) return null;
  if (!kit.imageUrl || /gravatar\.com\//.test(url) || url.startsWith('/')) return url;
  return `${kit.imageUrl}?u=${encodeURIComponent(url)}`;
}

function paintFooter(ctx, W, H, box, style, colors, kit, brand) {
  const { primary, ink } = colors;
  const tags = kit.event.hashtags.split(' ').slice(2).join('  ');
  const when = [kit.event.dates, tags].filter(Boolean).join('   ');

  if (style.name === 'bold') {
    const fy = H - 210;
    ctx.fillStyle = ink;
    const name = fitText(ctx, kit.event.name, W - 420, 50, 30, 1, 800);
    ctx.fillText(name.lines[0], 80, fy + 88);
    ctx.font = `700 28px ${FONT}`;
    ctx.fillStyle = primary;
    ctx.fillText(ellipsize(ctx, when, W - 420), 80, fy + 144);
    if (brand) drawContain(ctx, brand, W - 80 - 220, fy + 60, 220, 90);
    return;
  }

  if (style.name === 'ticket') {
    const fy = box.footer;
    ctx.strokeStyle = alpha(ink, 0.15);
    ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(box.x, fy); ctx.lineTo(box.x + box.w, fy); ctx.stroke();
    ctx.fillStyle = ink;
    const name = fitText(ctx, kit.event.name, box.w - 280, 40, 24, 1, 800);
    ctx.fillText(name.lines[0], box.x, fy + 58);
    ctx.font = `700 26px ${FONT}`;
    ctx.fillStyle = primary;
    ctx.fillText(ellipsize(ctx, when, box.w - 280), box.x, fy + 100);
    // A barcode, ticket-style.
    let bx = box.x + box.w - 230;
    for (let i = 0; i < 34; i++) {
      const bw = [3, 6, 2, 8, 3, 4][i % 6];
      ctx.fillStyle = ink;
      ctx.fillRect(bx, fy + 28, bw, 80);
      bx += bw + 3;
    }
    return;
  }

  if (style.name === 'poster') {
    const fy = box.footer;
    ctx.fillStyle = ink;
    ctx.fillRect(box.x, fy, 120, 8);
    const name = fitText(ctx, kit.event.name, W - 480, 44, 26, 1, 800);
    ctx.fillText(name.lines[0], box.x, fy + 66);
    ctx.font = `700 28px ${FONT}`;
    ctx.fillStyle = alpha(ink, 0.75);
    ctx.fillText(ellipsize(ctx, when, W - 480), box.x, fy + 110);
    return;
  }

  // Gradient: white on colour, the CampBuddy mark on a white chip.
  const fy = box.footer;
  ctx.fillStyle = 'rgba(255,255,255,0.14)';
  roundRect(ctx, box.x, fy, box.w, 134, 30);
  ctx.fill();
  ctx.fillStyle = '#fff';
  const name = fitText(ctx, kit.event.name, box.w - 320, 42, 26, 1, 800);
  ctx.fillText(name.lines[0], box.x + 34, fy + 60);
  ctx.font = `600 26px ${FONT}`;
  ctx.fillStyle = 'rgba(255,255,255,0.86)';
  ctx.fillText(ellipsize(ctx, when, box.w - 320), box.x + 34, fy + 102);
  if (brand) {
    ctx.fillStyle = '#fff';
    roundRect(ctx, box.x + box.w - 250, fy + 30, 220, 74, 18);
    ctx.fill();
    drawContain(ctx, brand, box.x + box.w - 240, fy + 38, 200, 58);
  }
}

// ---- Event post -----------------------------------------------------------

export async function drawEventPost(canvas, size, styleName, content, kit, colors) {
  const [W, H] = SIZES[size];
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  const style = styleOf(styleName, colors);
  const [logo, mark, brand] = await Promise.all([loadImage(kit.event.logo), loadImage(kit.event.mark), loadImage('/media/logo-wordmark.png')]);
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  const box = paintBackground(ctx, W, H, style, colors, mark);
  box.compact = H < 1100;
  const top = paintHeader(ctx, box, style, colors, logo, content);

  // Text and body share the space between the header and the footer.
  const bottom = box.footer - 50;
  const wanted = bodyHeight(content.body, H);
  // With a body, the headline gets two lines and a little less size, so the body has room.
  const head = fitText(ctx, content.headline, box.w, H >= 1900 ? 132 : (H > 1100 ? (wanted ? 100 : 112) : (wanted ? 70 : 92)), 44, wanted ? 2 : 3, style.headlineWeight, style.headlineFamily);
  const line = fitText(ctx, content.line, box.w, H >= 1900 ? 46 : 38, 24, 2, 600);
  const textH = head.lines.length * head.size * 1.06 + (content.line ? 50 + line.lines.length * line.size * 1.3 : 0);
  const gap = wanted ? (H >= 1900 ? 80 : 40) : 0;
  const fitted = fitBody(content.body, H, bottom - top - textH - gap);
  const bH = fitted.h;
  let y = top + Math.max(0, (bottom - top - (textH + (bH ? gap : 0) + bH)) / 2);

  // Headline (poster: a marker stroke under the first line).
  ctx.font = `${style.headlineWeight} ${head.size}px ${style.headlineFamily}`;
  head.lines.forEach((l, i) => {
    y += head.size * 0.92;
    if (style.name === 'poster' && i === head.lines.length - 1) {
      // A highlighter stroke along the bottom of the last line.
      ctx.fillStyle = alpha(colors.primary, 0.28);
      ctx.fillRect(box.x - 6, y - head.size * 0.22, Math.min(ctx.measureText(l).width + 12, box.w), head.size * 0.3);
    }
    ctx.fillStyle = style.fg;
    ctx.fillText(l, box.x, y);
    y += head.size * 0.14;
  });

  if (content.line) {
    y += 20;
    ctx.fillStyle = style.accent;
    roundRect(ctx, box.x, y, 120, 12, 6);
    ctx.fill();
    y += 18;
    ctx.fillStyle = style.muted;
    ctx.font = `600 ${line.size}px ${FONT}`;
    line.lines.forEach((l) => { y += line.size * 1.1; ctx.fillText(l, box.x, y); y += line.size * 0.2; });
  }

  if (bH) await paintBody(ctx, fitted.body, box.x, y + gap, box.w, bH, style, colors, kit, H);

  paintFooter(ctx, W, H, box, style, colors, kit, brand);
  addGrain(ctx, W, H, style.name === 'poster' ? 0.06 : 0.05);
}

// ---- People cards (social posts, not the attendee app's Camp Card) --------

export function personHeadline(person) {
  if (person.roles?.length) return `Meet our ${person.role_labels[0]}`;
  return 'Meet me at WordCamp';
}

/** The colour of someone's first role (organizer: main colour, speaker: accent). */
export function roleColor(person, colors) {
  const role = person.roles?.[0];
  if (role === 'organizer' || !role) return colors.primary;
  if (role === 'speaker') return colors.secondary;
  return ROLE_COLORS[role] ?? colors.primary;
}

function personLines(person) {
  return {
    roles: person.role_labels?.length ? person.role_labels.join(' · ') : (person.card?.role ?? ''),
    talk: person.talk ? `“${person.talk}”` : (person.card?.askMeAbout ? `Ask me about ${person.card.askMeAbout}` : ''),
  };
}

function footerStrip(ctx, W, y, h, kit, mark, bg) {
  const fg = textOn(bg);
  ctx.fillStyle = bg;
  ctx.fillRect(0, y, W, h);
  ctx.textAlign = 'left';
  if (mark) {
    ctx.fillStyle = '#fff';
    roundRect(ctx, 60, y + (h - 80) / 2, 80, 80, 16);
    ctx.fill();
    drawContain(ctx, mark, 68, y + (h - 80) / 2 + 8, 64, 64);
  }
  ctx.fillStyle = fg;
  const left = mark ? 165 : 60;
  const ev = fitText(ctx, kit.event.name, W - left - 280, 36, 22, 1, 800);
  ctx.fillText(ev.lines[0], left, y + h / 2 - 4);
  ctx.font = `600 26px ${FONT}`;
  ctx.fillStyle = alpha(fg, 0.82);
  ctx.fillText(kit.event.dates, left, y + h / 2 + 34);
  ctx.textAlign = 'right';
  ctx.font = `800 26px ${FONT}`;
  ctx.fillStyle = fg;
  ctx.fillText(kit.event.hashtags.split(' ').slice(2, 3).join(' '), W - 60, y + h / 2 + 10);
  ctx.textAlign = 'left';
}

export async function drawPersonCard(canvas, size, design, person, kit, colors) {
  const [W, H] = SIZES[size];
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  const [photo, mark] = await Promise.all([loadImage(person.photo), loadImage(kit.event.mark)]);
  const { primary, secondary, ink, paper } = colors;
  const rc = roleColor(person, colors);
  const { roles, talk } = personLines(person);
  const label = personHeadline(person).toUpperCase();
  const tall = H >= 1900;
  const square = H < 1100;
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  if (design === 'polaroid') {
    // A tilted instant photo taped onto a coloured wall.
    ctx.fillStyle = rc;
    ctx.fillRect(0, 0, W, H);
    watermark(ctx, mark, W, H, 0.08);
    dotGrid(ctx, 70, H - 330, 8, 3, 30, 4, alpha(textOn(rc), 0.25));
    pill(ctx, label, W / 2, 60, { bg: '#fff', fg: ink, size: 32, height: 66, align: 'center' });
    const pw = tall ? W * 0.76 : Math.min(W * 0.66, (H - 420) - 150);
    const ph = pw + 160;
    const cx = W / 2;
    const below = 130 + (talk && !square ? (tall ? 260 : 150) : 40);
    const cy = Math.max(150 + ph / 2, 150 + (H - below - 150) / 2);
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(-0.045);
    ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 18;
    ctx.fillStyle = '#fffdf8';
    ctx.fillRect(-pw / 2, -ph / 2, pw, ph);
    ctx.shadowColor = 'transparent';
    const inner = pw - 56;
    if (photo) {
      ctx.save(); ctx.beginPath(); ctx.rect(-inner / 2, -ph / 2 + 28, inner, inner); ctx.clip();
      drawCover(ctx, photo, -inner / 2, -ph / 2 + 28, inner, inner);
      ctx.restore();
    } else {
      ctx.fillStyle = secondary; ctx.fillRect(-inner / 2, -ph / 2 + 28, inner, inner);
      ctx.fillStyle = textOn(secondary); ctx.textAlign = 'center'; ctx.font = `900 ${Math.round(inner * 0.4)}px ${FONT}`;
      ctx.fillText(initials(person.name), 0, -ph / 2 + 28 + inner * 0.64);
    }
    ctx.fillStyle = ink;
    ctx.textAlign = 'center';
    const n = fitText(ctx, person.name, inner, 60, 32, 1, 700, SERIF);
    ctx.font = `italic 700 ${n.size}px ${SERIF}`;
    ctx.fillText(n.lines[0], 0, ph / 2 - 76);
    ctx.font = `700 26px ${FONT}`;
    ctx.fillStyle = mix(rc, '#000000', 0.2);
    ctx.fillText(ellipsize(ctx, roles, inner), 0, ph / 2 - 34);
    ctx.fillStyle = alpha(mix(secondary, '#ffffff', 0.55), 0.8);
    ctx.save(); ctx.rotate(0.07); ctx.fillRect(-95, -ph / 2 - 26, 190, 54); ctx.restore();
    ctx.restore();
    if (talk && !square) {
      ctx.fillStyle = textOn(rc);
      ctx.textAlign = 'center';
      const t = fitText(ctx, talk, W - 160, tall ? 44 : 32, 22, tall ? 3 : 2, 700);
      const ty = cy + ph / 2 + (tall ? 110 : 70);
      t.lines.forEach((l, i) => ctx.fillText(l, W / 2, ty + i * t.size * 1.25));
      ctx.textAlign = 'left';
    }
    footerStrip(ctx, W, H - 130, 130, kit, mark, mix(rc, '#000000', 0.35));
    addGrain(ctx, W, H);
    return;
  }

  if (design === 'badge') {
    // A conference badge on a lanyard.
    const wall = mix(paper, rc, 0.14);
    ctx.fillStyle = wall;
    ctx.fillRect(0, 0, W, H);
    dotGrid(ctx, 30, 30, 34, Math.ceil(H / 32), 32, 2.4, alpha(rc, 0.18));
    const bw = W * (square ? 0.62 : 0.72);
    const bh = H * (tall ? 0.72 : (square ? 0.8 : 0.78));
    const bx = (W - bw) / 2;
    const by = H - bh - 50;
    ctx.fillStyle = rc;
    ctx.beginPath(); ctx.moveTo(W / 2 - 70, 0); ctx.lineTo(W / 2 + 70, 0); ctx.lineTo(W / 2 + 34, by + 20); ctx.lineTo(W / 2 - 34, by + 20); ctx.closePath(); ctx.fill();
    ctx.fillStyle = '#9aa0a6';
    roundRect(ctx, W / 2 - 46, by - 16, 92, 50, 12); ctx.fill();
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.25)'; ctx.shadowBlur = 36; ctx.shadowOffsetY = 14;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, bx, by, bw, bh, 36); ctx.fill();
    ctx.restore();
    ctx.save(); roundRect(ctx, bx, by, bw, bh, 36); ctx.clip();
    ctx.fillStyle = primary; ctx.fillRect(bx, by, bw, 150);
    if (mark) { ctx.fillStyle = '#fff'; roundRect(ctx, bx + 36, by + 44, 80, 80, 16); ctx.fill(); drawContain(ctx, mark, bx + 44, by + 52, 64, 64); }
    ctx.fillStyle = textOn(primary);
    const ev = fitText(ctx, kit.event.name, bw - 180, 34, 20, 1, 800);
    ctx.fillText(ev.lines[0], bx + 136, by + 96);
    ctx.fillStyle = rc; ctx.fillRect(bx, by + bh - 120, bw, 120);
    ctx.fillStyle = textOn(rc); ctx.textAlign = 'center';
    const rl = fitText(ctx, (person.role_labels?.[0] ?? 'Attendee').toUpperCase(), bw - 60, tall ? 58 : 50, 28, 1, 900);
    ctx.fillText(rl.lines[0], bx + bw / 2, by + bh - 44);
    ctx.restore();
    ctx.fillStyle = wall;
    roundRect(ctx, W / 2 - 50, by + 18, 100, 22, 11); ctx.fill();
    const room = bh - 150 - 120;
    const r = Math.min(bw * (tall ? 0.36 : 0.27), room * (talk ? 0.27 : 0.3));
    const cy = by + 150 + 30 + r;
    circlePhoto(ctx, photo, W / 2, cy, r, 10, rc, secondary, person.name);
    ctx.fillStyle = ink; ctx.textAlign = 'center';
    const n = fitText(ctx, person.name, bw - 80, tall ? 90 : 70, 34, 2, 900);
    let y = cy + r + (tall ? 50 : 20);
    n.lines.forEach((l) => { y += n.size; ctx.fillText(l, W / 2, y); y += n.size * 0.08; });
    if (talk) {
      ctx.fillStyle = alpha(ink, 0.75);
      const t = fitText(ctx, talk, bw - 100, tall ? 38 : 28, 18, square ? 1 : (tall ? 3 : 2), 600);
      y += tall ? 30 : 10;
      t.lines.forEach((l) => { y += t.size * 1.2; ctx.fillText(l, W / 2, y); });
    }
    ctx.textAlign = 'left';
    addGrain(ctx, W, H, 0.04);
    return;
  }

  if (design === 'split') {
    // Photo across the top, a coloured block with the words below.
    const ph = Math.round(H * (tall ? 0.56 : 0.52));
    if (photo) drawCover(ctx, photo, 0, 0, W, ph);
    else { ctx.fillStyle = secondary; ctx.fillRect(0, 0, W, ph); ctx.fillStyle = textOn(secondary); ctx.textAlign = 'center'; ctx.font = `900 ${Math.round(ph * 0.4)}px ${FONT}`; ctx.fillText(initials(person.name), W / 2, ph * 0.64); ctx.textAlign = 'left'; }
    const g = ctx.createLinearGradient(0, ph * 0.5, 0, ph);
    g.addColorStop(0, 'rgba(0,0,0,0)');
    g.addColorStop(1, alpha(rc, 0.9));
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, ph);
    ctx.fillStyle = rc;
    ctx.beginPath(); ctx.moveTo(0, ph - 70); ctx.lineTo(W, ph + 10); ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();
    const fg = textOn(rc);
    pill(ctx, label, 60, 60, { bg: '#fff', fg: ink, size: 30, height: 62 });
    let y = ph + (square ? 80 : 110);
    ctx.fillStyle = fg;
    const n = fitText(ctx, person.name, W - 140, tall ? 100 : (square ? 70 : 86), 40, 2, 900);
    n.lines.forEach((l) => { ctx.fillText(l, 70, y); y += n.size * 1.04; });
    ctx.font = `800 32px ${FONT}`;
    ctx.fillStyle = alpha(fg, 0.85);
    if (roles) { ctx.fillText(ellipsize(ctx, roles, W - 140), 70, y + 8); y += 64; }
    if (talk) {
      ctx.fillStyle = fg;
      const t = fitText(ctx, talk, W - 140, 36, 22, tall ? 4 : (square ? 1 : 2), 600);
      t.lines.forEach((l) => { ctx.fillText(l, 70, y); y += t.size * 1.3; });
    }
    footerStrip(ctx, W, H - 120, 120, kit, mark, mix(rc, '#000000', 0.35));
    addGrain(ctx, W, H);
    return;
  }

  if (design === 'minimal') {
    // Paper, thin lines, lots of air: the name does the talking.
    ctx.fillStyle = paper;
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = alpha(ink, 0.2);
    ctx.lineWidth = 2;
    ctx.strokeRect(40, 40, W - 80, H - 80);
    ctx.fillStyle = rc;
    ctx.fillRect(40, 40, 16, H - 80);
    ctx.font = `800 28px ${FONT}`;
    ctx.fillStyle = mix(rc, '#000000', 0.1);
    ctx.fillText(label, 110, 130);
    const r = tall ? 230 : (square ? 110 : 130);
    circlePhoto(ctx, photo, W - 110 - r, 100 + r, r, 0, rc, secondary, person.name);
    let y = 100 + 2 * r + (tall ? 330 : (square ? 90 : 130));
    ctx.fillStyle = ink;
    const n = fitText(ctx, person.name, W - 220, tall ? 150 : (square ? 84 : 104), 46, 2, 700, SERIF);
    ctx.font = `700 ${n.size}px ${SERIF}`;
    n.lines.forEach((l) => { ctx.fillText(l, 110, y); y += n.size * 1.02; });
    ctx.fillStyle = rc; ctx.fillRect(110, y + 6, 120, 8);
    y += 64;
    ctx.font = `700 30px ${FONT}`; ctx.fillStyle = alpha(ink, 0.8);
    if (roles) { ctx.fillText(ellipsize(ctx, roles, W - 220), 110, y); y += 56; }
    if (talk) {
      const t = fitText(ctx, talk, W - 220, tall ? 46 : 34, 22, square ? 2 : (tall ? 4 : 3), 500, SERIF);
      ctx.font = `italic 500 ${t.size}px ${SERIF}`;
      t.lines.forEach((l) => { ctx.fillText(l, 110, y); y += t.size * 1.3; });
    }
    ctx.fillStyle = ink;
    const ev = fitText(ctx, kit.event.name, W - 400, 34, 22, 1, 800);
    ctx.fillText(ev.lines[0], 110, H - 120);
    ctx.font = `600 26px ${FONT}`; ctx.fillStyle = alpha(ink, 0.7);
    ctx.fillText(`${kit.event.dates}   ${kit.event.hashtags.split(' ').slice(2, 3).join(' ')}`, 110, H - 80);
    if (mark) drawContain(ctx, mark, W - 110 - 90, H - 175, 90, 90);
    addGrain(ctx, W, H, 0.04);
    return;
  }

  // Classic: colour on top with a slanted edge, round photo, name below.
  const top = H * (square ? 0.5 : (tall ? 0.42 : 0.46));
  ctx.fillStyle = paper;
  ctx.fillRect(0, 0, W, H);
  ctx.save();
  ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(W, 0); ctx.lineTo(W, top - 60); ctx.lineTo(0, top + 60); ctx.closePath();
  ctx.fillStyle = rc; ctx.fill(); ctx.clip();
  watermark(ctx, mark, W, H, 0.09);
  ctx.restore();
  ctx.strokeStyle = rc === secondary ? primary : secondary;
  ctx.lineWidth = 16;
  ctx.beginPath(); ctx.moveTo(0, top + 76); ctx.lineTo(W, top - 44); ctx.stroke();
  pill(ctx, label, W / 2, 60, { bg: '#fff', fg: ink, size: 34, height: 70, align: 'center' });
  const r = tall ? 300 : (square ? 175 : 225);
  const cy = top - (square ? 50 : 30);
  circlePhoto(ctx, photo, W / 2, cy, r, 16, '#ffffff', secondary, person.name);
  let y = cy + r + (square ? 90 : (tall ? 170 : 120));
  ctx.fillStyle = ink; ctx.textAlign = 'center';
  const name = fitText(ctx, person.name, W - 160, square ? 70 : (tall ? 104 : 84), 44, 2, 900);
  name.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += name.size * 1.1; });
  ctx.fillStyle = mix(rc, '#000000', 0.2);
  ctx.font = `800 34px ${FONT}`;
  if (roles) { ctx.fillText(ellipsize(ctx, roles, W - 160), W / 2, y + 4); y += 60; }
  if (talk) {
    ctx.fillStyle = ink;
    const t = fitText(ctx, talk, W - 200, tall ? 50 : 38, 24, square ? 1 : (tall ? 4 : 3), 500);
    if (tall) y += 20;
    t.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += t.size * 1.3; });
  }
  ctx.textAlign = 'left';
  footerStrip(ctx, W, H - 120, 120, kit, mark, primary);
  addGrain(ctx, W, H, 0.04);
}

// ---- Files ----------------------------------------------------------------

function canvasBlob(canvas) {
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
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

/** A row of buttons acting as one choice (style, card design); calls onChange with the value. */
function choiceGroup(attr, onChange) {
  const buttons = [...document.querySelectorAll(`[data-${attr}]`)];
  const key = attr.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
  let value = buttons.find((b) => b.getAttribute('aria-pressed') === 'true')?.dataset[key] ?? buttons[0]?.dataset[key];
  buttons.forEach((b) => b.addEventListener('click', () => {
    value = b.dataset[key];
    buttons.forEach((o) => {
      const on = o === b;
      o.setAttribute('aria-pressed', String(on));
      o.classList.toggle('cb-btn-primary', on);
      o.classList.toggle('cb-btn-secondary', !on);
    });
    onChange(value);
  }));
  return () => value;
}

// ---- Page -----------------------------------------------------------------

export function initSocialKit() {
  const dataEl = document.getElementById('social-kit-data');
  if (!dataEl) return;
  const kit = JSON.parse(dataEl.textContent);
  const colors = { ...kit.colors };
  const sizeEl = document.querySelector('[data-social-size]');
  const size = () => sizeEl?.value ?? '1080x1350';
  let people = null;
  let redrawAll = () => {};
  const style = choiceGroup('social-style', () => redrawAll());
  const design = choiceGroup('people-design', () => people?.redraw());

  const posts = [...document.querySelectorAll('[data-social-post]')].map((card) => {
    const post = kit.posts.find((p) => p.key === card.dataset.socialPost);
    const field = (name) => card.querySelector(`[data-field="${name}"]`);
    const pickEl = card.querySelector('[data-pick]');
    const canvas = card.querySelector('[data-preview]');
    const fields = () => ({ headline: field('headline').value, line: field('line').value, caption: field('caption').value });
    let drawing = Promise.resolve();
    const redraw = () => {
      drawing = drawing.then(() => drawEventPost(canvas, size(), style(), postContent(post, fields(), kit, pickEl?.value), kit, colors)).catch(() => {});
      return drawing;
    };

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
      pickEl.addEventListener('change', () => { refreshCaption(); redraw(); });
    }

    field('headline').addEventListener('input', redraw);
    field('line').addEventListener('input', redraw);
    card.querySelector('[data-action="download"]').addEventListener('click', async () => {
      await redraw();
      save(await canvasBlob(canvas), `${kit.event.slug}-${post.key}-${style()}-${size()}.png`);
    });
    card.querySelector('[data-action="copy"]').addEventListener('click', (e) => copy(field('caption').value, e.currentTarget));
    card.querySelector('[data-action="share"]').addEventListener('click', async () => {
      const caption = field('caption').value;
      await redraw();
      const blob = await canvasBlob(canvas);
      const file = new File([blob], `${kit.event.slug}-${post.key}.png`, { type: 'image/png' });
      if (navigator.canShare?.({ files: [file] })) {
        try {
          await navigator.share({ files: [file], text: caption });
          return;
        } catch {
          // Cancelled or refused: fall back to the links below.
        }
      }
      const linksEl = card.querySelector('[data-share-links]');
      linksEl.replaceChildren('Copy the caption and download the image, then: ', ...shareLinks(caption, kit.event.site).flatMap(([label, href], i) => {
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

  people = setUpPeople(kit, colors, size, design);
  redrawAll = () => { posts.forEach((r) => r()); people?.redraw(); };
  sizeEl?.addEventListener('change', redrawAll);

  document.querySelectorAll('[data-color]').forEach((input) => {
    input.addEventListener('input', () => { colors[input.dataset.color] = input.value; redrawAll(); });
  });
  const fromLogo = async () => {
    const logo = await loadImage(kit.event.logo || kit.event.mark);
    if (!logo) return false;
    const c = document.createElement('canvas');
    c.width = 80; c.height = 80;
    const ctx = c.getContext('2d');
    drawContain(ctx, logo, 0, 0, 80, 80);
    let palette = null;
    try {
      palette = paletteFromPixels(ctx.getImageData(0, 0, 80, 80).data);
    } catch {
      return false;
    }
    if (!palette) return false;
    colors.primary = palette.primary;
    if (palette.secondary) colors.secondary = palette.secondary;
    document.querySelector('[data-color="primary"]').value = colors.primary;
    document.querySelector('[data-color="secondary"]').value = colors.secondary;
    redrawAll();
    return true;
  };
  document.querySelector('[data-colors-from-logo]')?.addEventListener('click', fromLogo);

  document.fonts?.ready.then(async () => {
    if (!kit.colorsSaved) await fromLogo();
    redrawAll();
  });
  redrawAll();
}

function setUpPeople(kit, colors, size, design) {
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
      canvas.className = 'w-full rounded-lg';
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
        await drawPersonCard(canvas, size(), design(), person, kit, colors);
        save(await canvasBlob(canvas), `${slugify(person.name)}-${design()}-${size()}.png`);
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
      await drawPersonCard(canvas, size(), design(), person, kit, colors);
      let name = `${slugify(person.name)}.png`;
      for (let n = 2; used.has(name); n++) name = `${slugify(person.name)}-${n}.png`;
      used.add(name);
      files.push({ name, data: new Uint8Array(await (await canvasBlob(canvas)).arrayBuffer()) });
      captions.push(`${name}\n${person.caption}\n`);
    }
    files.push({ name: 'captions.txt', data: new TextEncoder().encode(captions.join('\n')) });
    save(new Blob([zipStore(files)], { type: 'application/zip' }), `${kit.event.slug}-people-${roleEl?.value || 'all'}-${design()}-${size()}.zip`);
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
