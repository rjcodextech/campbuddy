// Admin → Analytics → Usage post: "CampBuddy at {WordCamp}", up to five
// 4:5 images at 600 DPI (laid out on 1080 × 1350, drawn 3× = 3240 × 4050,
// 5.4 × 6.75 in) and a caption each for LinkedIn, Instagram and X, from the
// WordCamp's real numbers
// (App\Support\UsagePost, 3 days before it to 3 days after it):
//   1. the cover — how many people used CampBuddy there;
//   2. in numbers — the biggest of the rest, as tiles;
//   3. day by day — people per day, the WordCamp's own days marked;
//   4. the most-saved talks (when there are enough);
//   5. thank you — the attendees' rating (5 or more), time in the app, and
//      where to get CampBuddy (WPSimplified's credit).
// A card without enough real data to say something is left out.

import { mix, reelTools, textOn } from './social-kit.js';
import { zipStore } from './zip-store.js';
import { withPngDpi } from './attendee/png-dpi.js';
import { FONT_TEXT, loadBrandFonts } from './brand.js';

const W = 1080;
const H = 1350;
// Laid out at 200 DPI (1080 px = 5.4 in), drawn 3×: 3240 × 4050 at 600 DPI.
const SCALE = 3;
export const DPI = 200 * SCALE;
export const SIZE = { width: W * SCALE, height: H * SCALE };
const FONT = FONT_TEXT;

function load(url) {
  return new Promise((resolve) => {
    if (!url) return resolve(null);
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = url;
  });
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

function contain(ctx, img, x, y, w, h) {
  const k = Math.min(w / img.width, h / img.height);
  ctx.drawImage(img, x + (w - img.width * k) / 2, y + (h - img.height * k) / 2, img.width * k, img.height * k);
}

function wrap(ctx, text, max) {
  const lines = [];
  let line = '';
  for (const word of String(text).split(/\s+/)) {
    const next = line ? `${line} ${word}` : word;
    if (ctx.measureText(next).width <= max || !line) line = next;
    else { lines.push(line); line = word; }
  }
  if (line) lines.push(line);
  return lines;
}

/** Lines at the biggest size (down to min) that fit maxLines; smaller still rather than cut, never under 24 px (docs spec/25). */
function fit(ctx, text, max, size, min, maxLines, weight) {
  for (let s = size; s >= 24; s -= 2) {
    ctx.font = `${weight} ${s}px ${FONT}`;
    const lines = wrap(ctx, text, max);
    if ((lines.length <= maxLines || s < min) && lines.every((l) => ctx.measureText(l).width <= max)) return { lines, size: s };
  }
  ctx.font = `${weight} 24px ${FONT}`;
  return { lines: wrap(ctx, text, max), size: 24 };
}

function lines(ctx, f, x, y, color, weight, align = 'left', lh = 1.15) {
  ctx.fillStyle = color;
  ctx.textAlign = align;
  ctx.font = `${weight} ${f.size}px ${FONT}`;
  f.lines.forEach((l, i) => ctx.fillText(l, x, y + (i + 1) * f.size * lh));
  ctx.textAlign = 'left';
  return y + f.lines.length * f.size * lh;
}

function big(n) {
  if (n >= 10000) return `${(n / 1000).toFixed(n >= 100000 ? 0 : 1).replace(/\.0$/, '')}K`;
  return Number(n).toLocaleString('en-IN');
}

/** The scale the card is being drawn at (shadows don't follow the canvas scale). */
let RES = SCALE;

/** The colours a template draws with: Classic and Daylight on the light colour, Bold on the main colour. */
export function usagePalette(c, tpl = 'classic') {
  if (tpl !== 'bold') return { primary: c.primary, secondary: c.secondary, ink: c.ink, paper: c.paper, onPrimary: textOn(c.primary, c.ink, c.paper) };
  const ground = c.primary;
  const text = textOn(ground, c.ink, '#ffffff');
  const accent = reelTools.readableOn([c.secondary, mix(c.secondary, '#ffffff', 0.45), text], [ground]);
  return { primary: accent, secondary: mix(c.secondary, '#ffffff', 0.3), ink: text, paper: ground, onPrimary: textOn(accent, c.ink, '#ffffff') };
}

/** Two white pills at the foot: CampBuddy × WPSimplified, and campbuddy.club. */
function footPills(ctx, d, assets, y) {
  const { pal } = d;
  const h = 84;
  const pill = (x, w) => {
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.14)';
    ctx.shadowBlur = 24 * RES;
    ctx.shadowOffsetY = 6 * RES;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, x, y, w, h, h / 2);
    ctx.fill();
    ctx.restore();
  };
  const lock = assets.lockup ?? assets.campbuddy;
  const lh = h * 0.56;
  const lw = lock ? lock.width * (lh / lock.height) : 220;
  pill(80, lw + h * 0.8);
  if (lock) ctx.drawImage(lock, 80 + h * 0.4, y + (h - lh) / 2, lw, lh);
  ctx.font = `700 30px ${FONT}`;
  const url = 'campbuddy.club';
  const uw = ctx.measureText(url).width + h * 0.8;
  pill(W - 80 - uw, uw);
  ctx.fillStyle = '#231f20';
  ctx.textAlign = 'center';
  ctx.fillText(url, W - 80 - uw / 2, y + h / 2 + 11);
  ctx.textAlign = 'left';
}

/** What every card shares, in the chosen template: the ground, the event's logo and title, the page, the foot. */
function frame(ctx, d, assets, page, total, title) {
  const { pal } = d;
  const tpl = d.tpl ?? 'classic';
  if (tpl === 'classic') {
    ctx.fillStyle = pal.paper;
    ctx.fillRect(0, 0, W, H);
    // Soft circles in the event's colours, the same on every card.
    [[W - 120, 140, 260, pal.secondary, 0.12], [80, H - 260, 220, pal.primary, 0.08], [W - 60, H - 520, 140, pal.primary, 0.06]].forEach(([x, y, r, c, a]) => {
      ctx.globalAlpha = a;
      ctx.fillStyle = c;
      ctx.beginPath();
      ctx.arc(x, y, r, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.globalAlpha = 1;
  } else {
    // Daylight: paper to white, two glows, a dot grid, waves above the foot. Bold: the main colour, deepening.
    const g = ctx.createLinearGradient(0, 0, tpl === 'bold' ? W : 0, H);
    g.addColorStop(0, tpl === 'bold' ? pal.paper : mix(pal.paper, '#ffffff', 0.2));
    g.addColorStop(1, tpl === 'bold' ? mix(pal.paper, '#000000', 0.35) : mix(pal.paper, '#ffffff', 0.85));
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
    const glow = (x, y, r, color, a) => {
      const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
      rg.addColorStop(0, reelTools.alpha(color, a));
      rg.addColorStop(1, reelTools.alpha(color, 0));
      ctx.fillStyle = rg;
      ctx.fillRect(0, 0, W, H);
    };
    glow(W * 0.94, H * 0.02, W * 0.75, pal.secondary, tpl === 'bold' ? 0.3 : 0.22);
    glow(W * 0.02, H * 0.75, W * 0.6, pal.primary, 0.1);
    ctx.fillStyle = tpl === 'bold' ? 'rgba(255,255,255,0.08)' : reelTools.alpha(pal.primary, 0.12);
    for (let y = 13; y < H - 170; y += 26) {
      for (let x = tpl === 'bold' ? 13 : W / 2 + 13; x < W; x += 26) ctx.fillRect(x - 1, y - 1, 2, 2);
    }
    ctx.save();
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    for (let i = 0; i < 4; i++) {
      const y0 = H - 190 - i * 22;
      ctx.strokeStyle = reelTools.alpha(pal.secondary, 0.3 * (1 - i * 0.22));
      ctx.beginPath();
      for (let x = 0; x <= W; x += 10) {
        const y = y0 + Math.sin((x / (W / 1.4)) * Math.PI * 2 + i * 0.9) * (12 + i * 3);
        if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      }
      ctx.stroke();
    }
    ctx.restore();
  }

  // Header: the event's logo on its tile, the title, the page.
  ctx.save();
  ctx.shadowColor = 'rgba(0,0,0,0.12)';
  ctx.shadowBlur = 24 * RES;
  ctx.fillStyle = '#ffffff';
  roundRect(ctx, 80, 72, 150, 150, 28);
  ctx.fill();
  ctx.restore();
  if (assets.logo) contain(ctx, assets.logo, 90, 90, 114, 114);
  else {
    ctx.fillStyle = tpl === 'bold' ? '#231f20' : pal.primary;
    ctx.font = `900 56px ${FONT}`;
    ctx.textAlign = 'center';
    ctx.fillText(d.event.short.replace(/^WordCamp\s+/i, '').split(/\s+/).map((w) => w[0]).join('').slice(0, 3).toUpperCase() || 'WC', 147, 168);
    ctx.textAlign = 'left';
  }
  if (tpl !== 'classic') reelTools.waveMark(ctx, 250, 118, 34, pal.primary);
  ctx.fillStyle = pal.primary;
  ctx.font = `800 30px ${FONT}`;
  ctx.fillText(title.toUpperCase(), tpl === 'classic' ? 250 : 296, 130, W - 250 - 200);
  ctx.fillStyle = mix(pal.ink, pal.paper, 0.3);
  ctx.font = `600 30px ${FONT}`;
  ctx.fillText(`${d.event.short} · ${d.event.dates}`, 250, 176, W - 250 - 200);
  if (total > 1) {
    ctx.font = `800 28px ${FONT}`;
    const label = `${page} / ${total}`;
    const w = ctx.measureText(label).width + 40;
    ctx.fillStyle = pal.primary;
    roundRect(ctx, W - 80 - w, 108, w, 56, 28);
    ctx.fill();
    ctx.fillStyle = pal.onPrimary;
    ctx.textAlign = 'center';
    ctx.fillText(label, W - 80 - w / 2, 146);
    ctx.textAlign = 'left';
  }

  if (tpl === 'classic') {
    // Foot: a band of the main colour with CampBuddy × WPSimplified and campbuddy.club.
    const footY = H - 150;
    ctx.fillStyle = pal.primary;
    ctx.fillRect(0, footY, W, 150);
    const lock = assets.lockup ?? assets.campbuddy;
    const lh = 58;
    const lw = lock ? Math.min(420, lock.width * (lh / lock.height)) : 0;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, 80, footY + 34, lw + 48, 82, 18);
    ctx.fill();
    if (lock) contain(ctx, lock, 104, footY + 46, lw, lh);
    ctx.fillStyle = pal.onPrimary;
    ctx.font = `700 32px ${FONT}`;
    ctx.fillText('campbuddy.club', 80 + lw + 80, footY + 86, W - (80 + lw + 80) - 80);
  } else {
    footPills(ctx, d, assets, H - 130);
  }
}

function cover(ctx, d) {
  const { pal } = d;
  const users = d.stats.find((s) => s.key === 'users');
  let y = 330;
  y = lines(ctx, fit(ctx, d.headline ?? `CampBuddy at ${d.event.name}`, W - 160, 84, 52, 3, 900), 80, y, pal.ink, 900);
  // The number, huge, in the main colour.
  ctx.fillStyle = pal.primary;
  ctx.font = `900 ${users.value >= 10000 ? 300 : 340}px ${FONT}`;
  ctx.fillText(big(users.value), 80, y + 330, W - 160);
  y = lines(ctx, fit(ctx, users.label, W - 160, 64, 40, 2, 800), 80, y + 350, pal.ink, 800);
  const pages = d.stats.find((s) => s.key === 'pages');
  if (pages && pages.value >= 3) {
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.25);
    ctx.font = `600 40px ${FONT}`;
    ctx.fillText(`and opened ${big(pages.value)} pages`, 80, y + 64, W - 160);
  }
  if (d.showRange !== false) {
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.4);
    ctx.font = `600 28px ${FONT}`;
    ctx.fillText(`From 3 days before the WordCamp to 3 days after (${d.range.from} – ${d.range.to})`, 80, H - 172, W - 160);
  }
}

function tiles(ctx, d) {
  const { pal } = d;
  const list = d.stats.filter((t) => t.key !== 'users' && t.value >= 3).sort((a, b) => b.value - a.value).slice(0, 6);
  lines(ctx, fit(ctx, 'What people did with it', W - 160, 72, 48, 2, 900), 80, 270, pal.ink, 900);
  const cols = 2;
  const gap = 32;
  const tw = (W - 160 - gap) / cols;
  const th = 228;
  const top = 400;
  list.forEach((t, i) => {
    const x = 80 + (i % cols) * (tw + gap);
    const y = top + Math.floor(i / cols) * (th + gap);
    const bg = i % 3 === 0 ? pal.primary : i % 3 === 1 ? pal.secondary : '#ffffff';
    // Words on a tile always read on that tile, whatever the template's ground.
    const fg = textOn(bg, '#231f20', '#ffffff');
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.10)';
    ctx.shadowBlur = 24 * RES;
    ctx.fillStyle = bg;
    roundRect(ctx, x, y, tw, th, 32);
    ctx.fill();
    ctx.restore();
    ctx.fillStyle = bg === '#ffffff' ? reelTools.readableOn([pal.primary, d.colors?.primary ?? '#c33a19', '#231f20'], ['#ffffff']) : fg;
    ctx.font = `900 92px ${FONT}`;
    ctx.fillText(big(t.value), x + 34, y + 106, tw - 68);
    const label = fit(ctx, t.label, tw - 68, 32, 22, 2, 700);
    lines(ctx, label, x + 34, y + 116, fg, 700, 'left', 1.2);
  });
}

function daily(ctx, d) {
  const { pal } = d;
  lines(ctx, fit(ctx, 'Day by day', W - 160, 72, 48, 1, 900), 80, 270, pal.ink, 900);
  ctx.fillStyle = mix(pal.ink, pal.paper, 0.3);
  ctx.font = `600 32px ${FONT}`;
  ctx.fillText('People who opened CampBuddy for this WordCamp each day', 80, 400, W - 160);
  const days = d.daily;
  const max = Math.max(1, ...days.map((x) => x.users));
  const left = 80;
  const right = W - 80;
  const base = H - 300;
  const top = 500;
  const step = (right - left) / days.length;
  const bw = Math.min(90, step * 0.7);
  days.forEach((day, i) => {
    const on = day.date >= d.firstDay && day.date <= d.lastDay;
    const h = ((base - top - 60) * day.users) / max;
    const x = left + i * step + (step - bw) / 2;
    ctx.fillStyle = on ? pal.primary : mix(pal.primary, pal.paper, 0.6);
    roundRect(ctx, x, base - h, bw, Math.max(h, 4), Math.min(16, bw / 2));
    ctx.fill();
    if (day.users > 0) {
      ctx.fillStyle = pal.ink;
      ctx.font = `800 ${bw < 50 ? 24 : 28}px ${FONT}`;
      ctx.textAlign = 'center';
      ctx.fillText(big(day.users), x + bw / 2, base - h - 14);
    }
    ctx.fillStyle = mix(pal.ink, pal.paper, on ? 0 : 0.35);
    ctx.font = `${on ? 800 : 600} 24px ${FONT}`;
    ctx.textAlign = 'center';
    const date = new Date(`${day.date}T00:00:00`);
    ctx.fillText(String(date.getDate()), x + bw / 2, base + 34);
    ctx.fillText(date.toLocaleString('en', { month: 'short' }), x + bw / 2, base + 64);
    ctx.textAlign = 'left';
  });
  // Key.
  ctx.fillStyle = pal.primary;
  roundRect(ctx, 80, H - 220, 30, 30, 6);
  ctx.fill();
  ctx.fillStyle = pal.ink;
  ctx.font = `600 26px ${FONT}`;
  ctx.fillText('The WordCamp\'s own days', 124, H - 196);
}

function talks(ctx, d) {
  const { pal } = d;
  lines(ctx, fit(ctx, 'The talks people saved most', W - 160, 72, 48, 2, 900), 80, 270, pal.ink, 900);
  const list = d.talks.slice(0, 5);
  const max = Math.max(1, ...list.map((t) => t.saves));
  let y = 470;
  list.forEach((t, i) => {
    if (y > H - 300) return;
    const titleFit = fit(ctx, t.title, W - 160 - 98 - 210, 36, 24, 2, 700);
    const h = Math.max(120, titleFit.lines.length * titleFit.size * 1.2 + 70);
    if (y + h > H - 180) return;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, 80, y, W - 160, h, 26);
    ctx.fill();
    // How many, as a bar along the foot of the row.
    ctx.fillStyle = mix(pal.primary, pal.paper, 0.75);
    roundRect(ctx, 80, y + h - 14, ((W - 160) * t.saves) / max, 14, 7);
    ctx.fill();
    ctx.fillStyle = pal.primary;
    ctx.font = `900 46px ${FONT}`;
    ctx.fillText(String(i + 1), 104, y + 70);
    lines(ctx, titleFit, 170, y + 14, pal.ink, 700, 'left', 1.2);
    ctx.fillStyle = pal.primary;
    ctx.font = `800 30px ${FONT}`;
    ctx.textAlign = 'right';
    ctx.fillText(`${big(t.saves)} saves`, W - 104, y + 58);
    ctx.textAlign = 'left';
    y += h + 22;
  });
}

function thanks(ctx, d) {
  const { pal } = d;
  let y = lines(ctx, fit(ctx, `Thank you, ${d.event.short}!`, W - 160, 88, 52, 2, 900), 80, 270, pal.ink, 900);
  if (d.rating) {
    y += 60;
    ctx.fillStyle = pal.primary;
    ctx.font = `900 200px ${FONT}`;
    ctx.fillText(String(d.rating.average), 80, y + 180);
    // Five stars, filled to the average.
    const sx = 80;
    const sy = y + 230;
    for (let i = 0; i < 5; i++) {
      const fill = Math.max(0, Math.min(1, d.rating.average - i));
      star(ctx, sx + i * 96 + 40, sy + 40, 38, mix(pal.secondary, pal.paper, 0.6), 1);
      if (fill > 0) star(ctx, sx + i * 96 + 40, sy + 40, 38, pal.secondary, fill);
    }
    ctx.fillStyle = pal.ink;
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(`from ${d.rating.count} attendee ratings`, 80, sy + 150, W - 160);
    y = sy + 170;
  }
  if (d.minutes && d.minutes >= 1) {
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.2);
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(`${d.minutes} minutes in CampBuddy per person, on average`, 80, y + 90, W - 160);
    y += 90;
  }
  const peak = [...d.daily].sort((a, b) => b.users - a.users)[0];
  if (peak && peak.users >= 3) {
    const when = new Date(`${peak.date}T00:00:00`).toLocaleString('en', { day: 'numeric', month: 'short' });
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.2);
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(`Busiest day: ${when}, ${big(peak.users)} people`, 80, y + 90, W - 160);
    y += 90;
  }
  y = lines(ctx, fit(ctx, 'CampBuddy is there for every WordCamp: the schedule with reminders, people to meet and your Camp Card.', W - 460, 40, 28, 5, 600), 80, y + 60, mix(pal.ink, pal.paper, 0.15), 600, 'left', 1.3);
  // A QR to CampBuddy, for the next one.
  const qs = 300;
  const qx = W - 80 - qs;
  const qy = H - 150 - 60 - qs;
  reelTools.drawQr(ctx, 'https://campbuddy.club/?utm_source=campbuddy&utm_medium=social&utm_campaign=usage-post', qx, qy, qs, pal.ink, '#ffffff');
  ctx.fillStyle = pal.ink;
  ctx.font = `700 26px ${FONT}`;
  ctx.textAlign = 'center';
  ctx.fillText('Your next WordCamp', qx + qs / 2, qy - 16);
  ctx.textAlign = 'left';
}

function star(ctx, cx, cy, r, color, fill) {
  ctx.save();
  ctx.beginPath();
  for (let i = 0; i < 10; i++) {
    const a = -Math.PI / 2 + (i * Math.PI) / 5;
    const rr = i % 2 ? r * 0.45 : r;
    ctx.lineTo(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr);
  }
  ctx.closePath();
  ctx.clip();
  ctx.fillStyle = color;
  ctx.fillRect(cx - r, cy - r, 2 * r * fill, 2 * r);
  ctx.restore();
}

/** Which cards this WordCamp's numbers can fill, in order. Pure, for tests. */
export function plan(d) {
  const cards = [];
  const users = d.stats.find((s) => s.key === 'users');
  if (!users || users.value < 3) return cards;
  cards.push(['cover', 'CampBuddy at the WordCamp']);
  if (d.tiles.filter((t) => t.key !== 'users').length >= 2) cards.push(['tiles', 'In numbers']);
  if (d.daily.filter((x) => x.users > 0).length >= 2) cards.push(['daily', 'Day by day']);
  if (d.talks.length >= 2) cards.push(['talks', 'Most-saved talks']);
  cards.push(['thanks', 'Thank you']);
  return cards;
}

const DRAW = { cover, tiles, daily, talks, thanks };

export function initUsagePost() {
  const root = document.querySelector('[data-usage-post]');
  if (!root) return;
  const status = root.querySelector('[data-usage-status]');
  const result = root.querySelector('[data-usage-result]');
  const rail = root.querySelector('[data-usage-rail]');
  const stage = root.querySelector('[data-usage-stage]');
  const cardsBox = root.querySelector('[data-usage-cards]');
  const titleEl = root.querySelector('[data-usage-title]');
  const headlineEl = root.querySelector('[data-usage-headline]');
  const rangeEl = root.querySelector('[data-usage-range]');
  const colorEls = [...root.querySelectorAll('[data-usage-color]')];
  const tplButtons = [...root.querySelectorAll('[data-usage-template]')];
  const captions = [...root.querySelectorAll('[data-usage-caption]')];
  let d = null;
  let assets = {};
  let cards = [];
  let slug = '';
  let current = 0;
  const titles = {};
  const off = new Set();

  const included = () => cards.filter(([kind]) => !off.has(kind));
  const titleOf = (kind, fallback) => (titles[kind] ?? fallback);
  // The studio's state on the data the cards draw from.
  const apply = () => {
    const c = Object.fromEntries(colorEls.map((el) => [el.dataset.usageColor, el.value]));
    d.tpl = tplButtons.find((b) => b.getAttribute('aria-pressed') === 'true')?.dataset.usageTemplate ?? 'classic';
    d.pal = usagePalette(c, d.tpl);
    d.headline = headlineEl.value.trim() || `CampBuddy at ${d.event.name}`;
    d.showRange = rangeEl.checked;
  };
  const paint = (canvas, i, scale) => {
    const list = included();
    const [kind, title] = list[i];
    canvas.width = Math.round(W * scale);
    canvas.height = Math.round(H * scale);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    RES = scale;
    frame(ctx, d, assets, i + 1, list.length, titleOf(kind, title));
    DRAW[kind](ctx, d);
  };
  let soon = 0;
  const redraw = () => {
    clearTimeout(soon);
    soon = setTimeout(() => {
      if (!d) return;
      apply();
      const list = included();
      current = Math.min(current, list.length - 1);
      rail.replaceChildren(...list.map(([kind, title], i) => {
        const li = document.createElement('li');
        li.className = 'shrink-0';
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `flex w-36 flex-col items-center gap-1 rounded-lg p-2 text-xs font-semibold ${i === current ? 'bg-maroon/10 text-maroon' : 'text-ink hover:bg-paper-soft'}`;
        const thumb = document.createElement('canvas');
        thumb.className = 'h-28 w-auto rounded border border-line';
        paint(thumb, i, 0.1);
        b.append(thumb, titleOf(kind, title));
        b.addEventListener('click', () => { current = i; syncFields(); redraw(); });
        li.append(b);
        return li;
      }));
      if (list.length) paint(stage, current, 1);
    }, 120);
  };
  const syncFields = () => {
    const list = included();
    const [kind, title] = list[current] ?? [];
    titleEl.value = kind ? titleOf(kind, title) : '';
  };

  root.querySelector('[data-usage-make]')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    slug = root.querySelector('[data-usage-event]').value;
    if (!slug) return;
    btn.disabled = true;
    status.textContent = 'Reading the numbers from Google Analytics…';
    result.classList.add('hidden');
    try {
      const url = new URL(root.dataset.url, location.origin);
      url.searchParams.set('event', slug);
      if (root.dataset.stream) url.searchParams.set('stream', root.dataset.stream);
      if (root.querySelector('[data-usage-fresh]')?.checked) url.searchParams.set('fresh', '1');
      const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ready) { status.textContent = data.message ?? 'No numbers for this WordCamp.'; return; }

      cards = plan(data);
      if (!cards.length) { status.textContent = `Too few people used CampBuddy at ${data.event.name} in those dates to post about it.`; return; }
      d = data;
      colorEls.forEach((el) => { el.value = d.colors[el.dataset.usageColor] ?? '#000000'; });
      headlineEl.value = `CampBuddy at ${d.event.name}`;
      off.clear();
      Object.keys(titles).forEach((k) => delete titles[k]);
      current = 0;
      cardsBox.replaceChildren(...cards.map(([kind, title]) => {
        const label = document.createElement('label');
        label.className = 'flex items-center gap-2';
        const box = document.createElement('input');
        box.type = 'checkbox';
        box.className = 'cb-check';
        box.checked = true;
        // The cover and the thank-you card always stay: they open and close the post.
        box.disabled = kind === 'cover' || kind === 'thanks';
        box.addEventListener('change', () => { if (box.checked) off.delete(kind); else off.add(kind); current = 0; syncFields(); redraw(); });
        label.append(box, ` ${title}`);
        return label;
      }));
      await loadBrandFonts();
      await document.fonts?.ready;
      const [logo, campbuddy, lockup] = await Promise.all([load(d.event.logo), load('/media/logo-wordmark.png'), reelTools.brandLockup()]);
      assets = { logo, campbuddy, lockup };
      captions.forEach((box) => { box.value = d.captions?.[box.dataset.usageCaption] ?? d.caption; });
      const count = root.querySelector('[data-usage-x-count]');
      if (count) count.textContent = `${d.x_length ?? ''} / 280`;
      result.classList.remove('hidden');
      syncFields();
      redraw();
      status.textContent = `${cards.length} ${cards.length === 1 ? 'image' : 'images'} for ${d.event.name}, from ${d.range.from} to ${d.range.to}. Change anything on the right.`;
    } catch {
      status.textContent = "Couldn't make the post. Try again.";
    } finally {
      btn.disabled = false;
    }
  });

  // The studio's edits.
  tplButtons.forEach((b) => b.addEventListener('click', () => {
    tplButtons.forEach((o) => {
      const on = o === b;
      o.setAttribute('aria-pressed', String(on));
      o.classList.toggle('cb-btn-primary', on);
      o.classList.toggle('cb-btn-secondary', !on);
    });
    redraw();
  }));
  colorEls.forEach((el) => el.addEventListener('input', redraw));
  root.querySelector('[data-usage-colors-reset]')?.addEventListener('click', () => {
    if (!d) return;
    colorEls.forEach((el) => { el.value = d.colors[el.dataset.usageColor] ?? el.value; });
    redraw();
  });
  headlineEl?.addEventListener('input', redraw);
  rangeEl?.addEventListener('change', redraw);
  titleEl?.addEventListener('input', () => {
    const [kind] = included()[current] ?? [];
    if (kind) titles[kind] = titleEl.value;
    redraw();
  });

  root.querySelector('[data-usage-download]')?.addEventListener('click', async () => {
    if (!d) return;
    const btn = root.querySelector('[data-usage-download]');
    btn.disabled = true;
    status.textContent = 'Making the 600 DPI images…';
    apply();
    const list = included();
    const files = [];
    for (const [i, [kind]] of list.entries()) {
      const canvas = document.createElement('canvas');
      paint(canvas, i, SCALE);
      const png = await withPngDpi(await new Promise((r) => canvas.toBlob(r, 'image/png')), DPI);
      files.push({ name: `${slug}-campbuddy-usage-${i + 1}-${kind}.png`, data: new Uint8Array(await png.arrayBuffer()) });
    }
    captions.forEach((box) => files.push({ name: `caption-${box.dataset.usageCaption}.txt`, data: new TextEncoder().encode(box.value) }));
    btn.disabled = false;
    status.textContent = `${list.length} ${list.length === 1 ? 'image' : 'images'} (3240 × 4050, 600 DPI) and 3 captions in the ZIP.`;
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([zipStore(files)], { type: 'application/zip' }));
    a.download = `${slug}-campbuddy-usage.zip`;
    document.body.append(a);
    a.click();
    a.remove();
  });

  root.querySelectorAll('[data-usage-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    const box = root.querySelector(`[data-usage-caption="${btn.dataset.usageCopy}"]`);
    try {
      await navigator.clipboard.writeText(box.value);
      btn.textContent = 'Copied';
      setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
    } catch {
      box.select();
    }
  }));
}
