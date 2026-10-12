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

const W = 1080;
const H = 1350;
// Laid out at 200 DPI (1080 px = 5.4 in), drawn 3×: 3240 × 4050 at 600 DPI.
const SCALE = 3;
export const DPI = 200 * SCALE;
export const SIZE = { width: W * SCALE, height: H * SCALE };
const FONT = '"Inter", "Segoe UI", system-ui, -apple-system, sans-serif';

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

/** Lines at the biggest size (down to min) that fit maxLines; smaller still rather than cut. */
function fit(ctx, text, max, size, min, maxLines, weight) {
  for (let s = size; s >= 16; s -= 2) {
    ctx.font = `${weight} ${s}px ${FONT}`;
    const lines = wrap(ctx, text, max);
    if ((lines.length <= maxLines || s < min) && lines.every((l) => ctx.measureText(l).width <= max)) return { lines, size: s };
  }
  ctx.font = `${weight} 16px ${FONT}`;
  return { lines: wrap(ctx, text, max), size: 16 };
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

/** What every card shares: the light ground, a band of the main colour, the event's logo, the page, the foot. */
function frame(ctx, d, assets, page, total, title) {
  const { pal } = d;
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

  // Header: the event's logo on its tile, the title, the page.
  ctx.save();
  ctx.shadowColor = 'rgba(0,0,0,0.12)';
  ctx.shadowBlur = 24 * SCALE; // shadows don't follow the canvas scale
  ctx.fillStyle = '#ffffff';
  roundRect(ctx, 72, 72, 150, 150, 28);
  ctx.fill();
  ctx.restore();
  if (assets.logo) contain(ctx, assets.logo, 90, 90, 114, 114);
  else {
    ctx.fillStyle = pal.primary;
    ctx.font = `900 56px ${FONT}`;
    ctx.textAlign = 'center';
    ctx.fillText(d.event.short.replace(/^WordCamp\s+/i, '').split(/\s+/).map((w) => w[0]).join('').slice(0, 3).toUpperCase() || 'WC', 147, 168);
    ctx.textAlign = 'left';
  }
  ctx.fillStyle = pal.primary;
  ctx.font = `800 30px ${FONT}`;
  ctx.fillText(title.toUpperCase(), 250, 130, W - 250 - 200);
  ctx.fillStyle = mix(pal.ink, pal.paper, 0.3);
  ctx.font = `600 30px ${FONT}`;
  ctx.fillText(`${d.event.short} · ${d.event.dates}`, 250, 176, W - 250 - 200);
  if (total > 1) {
    ctx.font = `800 28px ${FONT}`;
    const label = `${page} / ${total}`;
    const w = ctx.measureText(label).width + 40;
    ctx.fillStyle = pal.primary;
    roundRect(ctx, W - 72 - w, 108, w, 56, 28);
    ctx.fill();
    ctx.fillStyle = pal.onPrimary;
    ctx.textAlign = 'center';
    ctx.fillText(label, W - 72 - w / 2, 146);
    ctx.textAlign = 'left';
  }

  // Foot: a band of the main colour with CampBuddy and WPSimplified.
  const footY = H - 150;
  ctx.fillStyle = pal.primary;
  ctx.fillRect(0, footY, W, 150);
  ctx.fillStyle = '#ffffff';
  roundRect(ctx, 72, footY + 34, 300, 82, 18);
  ctx.fill();
  if (assets.campbuddy) contain(ctx, assets.campbuddy, 86, footY + 44, 272, 62);
  ctx.fillStyle = pal.onPrimary;
  ctx.font = `700 30px ${FONT}`;
  ctx.fillText('campbuddy.club · free, no sign-up', 400, footY + 70, W - 400 - 72);
  ctx.globalAlpha = 0.85;
  ctx.font = `600 24px ${FONT}`;
  ctx.fillText('Made with ♥ by WPSimplified', 400, footY + 108, W - 400 - 72);
  ctx.globalAlpha = 1;
}

function cover(ctx, d) {
  const { pal } = d;
  const users = d.stats.find((s) => s.key === 'users');
  let y = 330;
  y = lines(ctx, fit(ctx, `CampBuddy at ${d.event.name}`, W - 160, 84, 52, 3, 900), 80, y, pal.ink, 900);
  // The number, huge, in the main colour.
  ctx.fillStyle = pal.primary;
  ctx.font = `900 ${users.value >= 10000 ? 300 : 340}px ${FONT}`;
  ctx.fillText(big(users.value), 72, y + 330, W - 144);
  y = lines(ctx, fit(ctx, users.label, W - 160, 64, 40, 2, 800), 80, y + 350, pal.ink, 800);
  const pages = d.stats.find((s) => s.key === 'pages');
  if (pages && pages.value >= 3) {
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.25);
    ctx.font = `600 40px ${FONT}`;
    ctx.fillText(`and opened ${big(pages.value)} pages`, 80, y + 64, W - 160);
  }
  ctx.fillStyle = mix(pal.ink, pal.paper, 0.4);
  ctx.font = `600 28px ${FONT}`;
  ctx.fillText(`From 3 days before the WordCamp to 3 days after (${d.range.from} – ${d.range.to})`, 80, H - 200, W - 160);
}

function tiles(ctx, d) {
  const { pal } = d;
  const list = d.stats.filter((t) => t.key !== 'users' && t.value >= 3).sort((a, b) => b.value - a.value).slice(0, 6);
  lines(ctx, fit(ctx, 'What people did with it', W - 160, 72, 48, 2, 900), 80, 270, pal.ink, 900);
  const cols = 2;
  const gap = 32;
  const tw = (W - 144 - gap) / cols;
  const th = 228;
  const top = 400;
  list.forEach((t, i) => {
    const x = 72 + (i % cols) * (tw + gap);
    const y = top + Math.floor(i / cols) * (th + gap);
    const bg = i % 3 === 0 ? pal.primary : i % 3 === 1 ? pal.secondary : '#ffffff';
    const fg = bg === '#ffffff' ? pal.ink : textOn(bg, pal.ink, pal.paper);
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.10)';
    ctx.shadowBlur = 24 * SCALE;
    ctx.fillStyle = bg;
    roundRect(ctx, x, y, tw, th, 32);
    ctx.fill();
    ctx.restore();
    ctx.fillStyle = bg === '#ffffff' ? pal.primary : fg;
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
      ctx.font = `800 ${bw < 50 ? 22 : 28}px ${FONT}`;
      ctx.textAlign = 'center';
      ctx.fillText(big(day.users), x + bw / 2, base - h - 14);
    }
    ctx.fillStyle = mix(pal.ink, pal.paper, on ? 0 : 0.35);
    ctx.font = `${on ? 800 : 600} ${bw < 50 ? 18 : 22}px ${FONT}`;
    ctx.textAlign = 'center';
    const date = new Date(`${day.date}T00:00:00`);
    ctx.fillText(String(date.getDate()), x + bw / 2, base + 34);
    ctx.fillText(date.toLocaleString('en', { month: 'short' }), x + bw / 2, base + 60);
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
    const titleFit = fit(ctx, t.title, W - 144 - 98 - 210, 36, 24, 2, 700);
    const h = Math.max(120, titleFit.lines.length * titleFit.size * 1.2 + 70);
    if (y + h > H - 180) return;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, 72, y, W - 144, h, 26);
    ctx.fill();
    // How many, as a bar along the foot of the row.
    ctx.fillStyle = mix(pal.primary, pal.paper, 0.75);
    roundRect(ctx, 72, y + h - 14, ((W - 144) * t.saves) / max, 14, 7);
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
    ctx.fillText(String(d.rating.average), 72, y + 180);
    // Five stars, filled to the average.
    const sx = 72;
    const sy = y + 230;
    for (let i = 0; i < 5; i++) {
      const fill = Math.max(0, Math.min(1, d.rating.average - i));
      star(ctx, sx + i * 96 + 40, sy + 40, 38, mix(pal.secondary, pal.paper, 0.6), 1);
      if (fill > 0) star(ctx, sx + i * 96 + 40, sy + 40, 38, pal.secondary, fill);
    }
    ctx.fillStyle = pal.ink;
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(`from ${d.rating.count} attendee ratings`, 72, sy + 150, W - 144);
    y = sy + 170;
  }
  if (d.minutes && d.minutes >= 1) {
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.2);
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(`${d.minutes} minutes in CampBuddy per person, on average`, 72, y + 90, W - 144);
    y += 90;
  }
  const peak = [...d.daily].sort((a, b) => b.users - a.users)[0];
  if (peak && peak.users >= 3) {
    const when = new Date(`${peak.date}T00:00:00`).toLocaleString('en', { day: 'numeric', month: 'short' });
    ctx.fillStyle = mix(pal.ink, pal.paper, 0.2);
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(`Busiest day: ${when}, ${big(peak.users)} people`, 72, y + 90, W - 144);
    y += 90;
  }
  y = lines(ctx, fit(ctx, 'CampBuddy is free for every WordCamp: the schedule with reminders, people to meet and your Camp Card. No sign-up.', W - 460, 40, 28, 5, 600), 80, y + 60, mix(pal.ink, pal.paper, 0.15), 600, 'left', 1.3);
  // A QR to CampBuddy, for the next one.
  const qs = 300;
  const qx = W - 72 - qs;
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
  const previews = root.querySelector('[data-usage-previews]');
  const captions = [...root.querySelectorAll('[data-usage-caption]')];
  let made = [];
  let slug = '';

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
      const d = await res.json();
      if (!d.ready) { status.textContent = d.message ?? 'No numbers for this WordCamp.'; return; }

      const cards = plan(d);
      if (!cards.length) { status.textContent = `Too few people used CampBuddy at ${d.event.name} in those dates to post about it.`; return; }
      const c = d.colors;
      d.pal = { primary: c.primary, secondary: c.secondary, ink: c.ink, paper: c.paper, onPrimary: textOn(c.primary, c.ink, c.paper) };
      await document.fonts?.ready;
      const [logo, campbuddy] = await Promise.all([load(d.event.logo), load('/media/logo-wordmark.png')]);
      const assets = { logo, campbuddy };

      made = cards.map(([kind, title], i) => {
        const canvas = document.createElement('canvas');
        canvas.width = SIZE.width;
        canvas.height = SIZE.height;
        canvas.className = 'shrink-0 rounded-lg border border-line';
        // The preview: 320 px tall, whatever the canvas size (inline: not left to a class the CSS build may drop).
        canvas.style.height = '20rem';
        canvas.style.width = 'auto';
        const ctx = canvas.getContext('2d');
        ctx.scale(SCALE, SCALE);
        frame(ctx, d, assets, i + 1, cards.length, title);
        DRAW[kind](ctx, d);
        return { canvas, name: `${slug}-campbuddy-usage-${i + 1}-${kind}.png` };
      });
      previews.replaceChildren(...made.map((m) => m.canvas));
      captions.forEach((box) => { box.value = d.captions?.[box.dataset.usageCaption] ?? d.caption; });
      const count = root.querySelector('[data-usage-x-count]');
      if (count) count.textContent = `${d.x_length ?? ''} / 280`;
      result.classList.remove('hidden');
      status.textContent = `${made.length} ${made.length === 1 ? 'image' : 'images'} for ${d.event.name}, from ${d.range.from} to ${d.range.to}.`;
    } catch {
      status.textContent = "Couldn't make the post. Try again.";
    } finally {
      btn.disabled = false;
    }
  });

  root.querySelector('[data-usage-download]')?.addEventListener('click', async () => {
    if (!made.length) return;
    const btn = root.querySelector('[data-usage-download]');
    btn.disabled = true;
    status.textContent = 'Making the 600 DPI images…';
    const files = await Promise.all(made.map(async (m) => {
      const png = await withPngDpi(await new Promise((r) => m.canvas.toBlob(r, 'image/png')), DPI);
      return { name: m.name, data: new Uint8Array(await png.arrayBuffer()) };
    }));
    captions.forEach((box) => files.push({ name: `caption-${box.dataset.usageCaption}.txt`, data: new TextEncoder().encode(box.value) }));
    btn.disabled = false;
    status.textContent = `${made.length} ${made.length === 1 ? 'image' : 'images'} (3240 × 4050, 600 DPI) and 3 captions in the ZIP.`;
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
