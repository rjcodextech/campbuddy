// Admin / manager → Event → Social media (partials/social-media.blade.php).
// Draws the event's posts and people cards on canvases, in the event's brand
// colours, from App\Support\SocialKit::data(); downloads PNGs, a ZIP of the
// people cards, copies captions, opens share links, and (if a webhook is set)
// sends a post to Publish. Nothing here talks to a social network itself.

import QRCode from 'qrcode-generator';
import { zipStore } from './zip-store.js';

const SIZES = { '1080x1350': [1080, 1350], '1080x1080': [1080, 1080] };
const FONT = '"Inter", "Segoe UI", system-ui, -apple-system, sans-serif';
const PEOPLE_PAGE = 8;

// ---- Colours --------------------------------------------------------------

function hexToRgb(hex) {
  const n = parseInt(hex.slice(1), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function rgbToHex([r, g, b]) {
  return `#${[r, g, b].map((v) => Math.round(v).toString(16).padStart(2, '0')).join('')}`;
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
  // Dark enough for white text (relative luminance under ~0.25).
  for (let i = 0; i < 6 && luminance(primary) > 0.25; i++) primary = primary.map((v) => v * 0.8);
  const second = ranked.find(([key]) => Math.min(Math.abs(key - ranked[0][0]), 12 - Math.abs(key - ranked[0][0])) >= 2);

  return { primary: rgbToHex(primary), secondary: second ? rgbToHex(avg(second[1])) : null };
}

/** White or the dark text colour, whichever reads better on `background`. */
export function textOn(background, dark = '#231f20') {
  return luminance(hexToRgb(background)) > 0.4 ? dark : '#ffffff';
}

function luminance(rgb) {
  const [r, g, b] = rgb.map((v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
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
      img.onerror = () => resolve(null);
      img.src = url;
    }));
  }
  return images.get(url);
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

/** Word-wrapped lines at the largest size (down to `min`) that fits `maxLines`. */
function fitText(ctx, text, maxWidth, size, min, maxLines, weight = 800) {
  for (let s = size; s >= min; s -= 2) {
    ctx.font = `${weight} ${s}px ${FONT}`;
    const lines = wrap(ctx, text, maxWidth);
    if (lines.length <= maxLines && lines.every((l) => ctx.measureText(l).width <= maxWidth)) return { lines, size: s };
  }
  ctx.font = `${weight} ${min}px ${FONT}`;
  const lines = wrap(ctx, text, maxWidth).slice(0, maxLines);
  return { lines, size: min };
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

function qrCanvas(url, size) {
  const qr = QRCode(0, 'M');
  qr.addData(url);
  qr.make();
  const count = qr.getModuleCount();
  const cell = Math.floor(size / (count + 4));
  const c = document.createElement('canvas');
  c.width = c.height = cell * (count + 4);
  const ctx = c.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, c.width, c.height);
  ctx.fillStyle = '#000';
  for (let r = 0; r < count; r++) for (let col = 0; col < count; col++) if (qr.isDark(r, col)) ctx.fillRect((col + 2) * cell, (r + 2) * cell, cell, cell);
  return c;
}

// ---- Event post -----------------------------------------------------------

export async function drawEventPost(canvas, size, post, kit, colors) {
  const [W, H] = SIZES[size];
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  const logo = await loadImage(kit.event.logo);
  const band = 210;

  ctx.fillStyle = colors.primary;
  ctx.fillRect(0, 0, W, H);

  // Two soft circles in the accent and text colours.
  ctx.globalAlpha = 0.22;
  ctx.fillStyle = colors.secondary;
  ctx.beginPath(); ctx.arc(W * 0.92, H * 0.08, W * 0.42, 0, Math.PI * 2); ctx.fill();
  ctx.globalAlpha = 0.14;
  ctx.fillStyle = colors.ink;
  ctx.beginPath(); ctx.arc(W * 0.05, H - band - 40, W * 0.3, 0, Math.PI * 2); ctx.fill();
  ctx.globalAlpha = 1;

  // Logo on a white tile.
  ctx.fillStyle = '#fff';
  roundRect(ctx, 80, 80, 300, 180, 28);
  ctx.fill();
  if (logo) drawContain(ctx, logo, 100, 100, 260, 140);

  // Headline + line, as one block centred in the space between the logo and
  // the bottom band (above the QR on the "Get CampBuddy" post).
  const qrSide = post.key === 'app' ? 300 : 0;
  const areaTop = 300;
  const areaBottom = H - band - 50;
  const textWidth = W - 160 - (qrSide ? qrSide + 60 : 0);
  ctx.textBaseline = 'alphabetic';
  const head = fitText(ctx, post.headline, textWidth, H > 1100 ? 124 : 104, 56, 3);
  const headSize = head.size;
  const line = fitText(ctx, post.line, textWidth, 44, 28, 3, 600);
  const lineSize = line.size;
  const blockHeight = head.lines.length * headSize * 1.05 + 40 + line.lines.length * lineSize * 1.3;
  let y = areaTop + Math.max(0, (areaBottom - areaTop - blockHeight) / 2) + headSize * 0.9;

  ctx.fillStyle = '#fff';
  ctx.font = `800 ${headSize}px ${FONT}`;
  head.lines.forEach((l) => { ctx.fillText(l, 80, y); y += headSize * 1.05; });

  ctx.fillStyle = colors.secondary;
  roundRect(ctx, 80, y - headSize * 0.62, 140, 14, 7);
  ctx.fill();
  y += 30;

  ctx.fillStyle = 'rgba(255,255,255,0.92)';
  ctx.font = `600 ${lineSize}px ${FONT}`;
  line.lines.forEach((l) => { ctx.fillText(l, 80, y); y += lineSize * 1.3; });

  if (qrSide) {
    const qr = qrCanvas(kit.event.app, qrSide);
    ctx.fillStyle = '#fff';
    const qy = Math.round((areaTop + areaBottom - qrSide) / 2);
    roundRect(ctx, W - 80 - qrSide - 20, qy - 20, qrSide + 40, qrSide + 40, 24);
    ctx.fill();
    ctx.drawImage(qr, W - 80 - qrSide, qy, qrSide, qrSide);
  }

  // Bottom band: event name, dates, hashtags.
  ctx.fillStyle = colors.paper;
  ctx.fillRect(0, H - band, W, band);
  ctx.fillStyle = colors.ink;
  const name = fitText(ctx, kit.event.name, W - 160, 50, 34, 1, 800);
  ctx.fillText(name.lines[0], 80, H - band + 78);
  ctx.font = `600 32px ${FONT}`;
  ctx.fillStyle = colors.primary;
  ctx.fillText([kit.event.dates, kit.event.hashtags.split(' ').slice(2).join(' ')].filter(Boolean).join('   '), 80, H - band + 140);
}

// ---- Person card (a social post, not the attendee app's Camp Card) --------

export function personHeadline(person) {
  if (person.roles?.length) return `Meet our ${person.role_labels[0]}`;
  return 'Meet me at WordCamp';
}

export async function drawPersonCard(canvas, size, person, kit, colors) {
  const [W, H] = SIZES[size];
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  const [photo, mark] = await Promise.all([loadImage(person.photo), loadImage(kit.event.mark)]);
  const top = H * 0.46;

  ctx.fillStyle = colors.paper;
  ctx.fillRect(0, 0, W, H);

  // Main colour on top with a slanted edge, accent stripe along it.
  ctx.fillStyle = colors.primary;
  ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(W, 0); ctx.lineTo(W, top - 60); ctx.lineTo(0, top + 60); ctx.closePath(); ctx.fill();
  ctx.strokeStyle = colors.secondary;
  ctx.lineWidth = 16;
  ctx.beginPath(); ctx.moveTo(0, top + 76); ctx.lineTo(W, top - 44); ctx.stroke();

  // "MEET OUR SPEAKER" pill.
  const label = personHeadline(person).toUpperCase();
  ctx.font = `800 36px ${FONT}`;
  const lw = ctx.measureText(label).width + 64;
  ctx.fillStyle = colors.secondary;
  roundRect(ctx, (W - lw) / 2, 70, lw, 70, 35);
  ctx.fill();
  ctx.fillStyle = textOn(colors.secondary, colors.ink);
  ctx.textAlign = 'center';
  ctx.fillText(label, W / 2, 118);

  // Round photo with a white ring.
  const r = H > 1100 ? 230 : 190;
  const cy = top - (H > 1100 ? 30 : 60);
  ctx.fillStyle = '#fff';
  ctx.beginPath(); ctx.arc(W / 2, cy, r + 16, 0, Math.PI * 2); ctx.fill();
  ctx.save();
  ctx.beginPath(); ctx.arc(W / 2, cy, r, 0, Math.PI * 2); ctx.clip();
  if (photo) {
    const s = Math.max((2 * r) / photo.width, (2 * r) / photo.height);
    ctx.drawImage(photo, W / 2 - (photo.width * s) / 2, cy - (photo.height * s) / 2, photo.width * s, photo.height * s);
  } else {
    ctx.fillStyle = colors.secondary;
    ctx.fillRect(W / 2 - r, cy - r, 2 * r, 2 * r);
    ctx.fillStyle = colors.ink;
    ctx.font = `800 ${r}px ${FONT}`;
    ctx.fillText((person.name || '?').trim().charAt(0).toUpperCase(), W / 2, cy + r * 0.35);
  }
  ctx.restore();

  // Name, roles, talk.
  let y = cy + r + (H > 1100 ? 120 : 100);
  ctx.fillStyle = colors.ink;
  const name = fitText(ctx, person.name, W - 160, 84, 48, 2, 800);
  name.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += name.size * 1.1; });

  ctx.fillStyle = colors.primary;
  ctx.font = `700 36px ${FONT}`;
  const roleLine = person.role_labels?.length ? person.role_labels.join(' · ') : (person.card?.role ?? '');
  if (roleLine) { ctx.fillText(roleLine, W / 2, y + 6); y += 64; }

  const talk = person.talk ?? person.card?.askMeAbout ?? '';
  if (talk) {
    ctx.fillStyle = colors.ink;
    const t = fitText(ctx, person.talk ? `“${talk}”` : `Ask me about ${talk}`, W - 200, 40, 28, 3, 500);
    t.lines.forEach((l) => { ctx.fillText(l, W / 2, y); y += t.size * 1.3; });
  }

  // Footer: event mark, name and dates.
  const fy = H - 120;
  ctx.fillStyle = colors.primary;
  ctx.fillRect(0, fy, W, 120);
  ctx.textAlign = 'left';
  if (mark) {
    ctx.fillStyle = '#fff';
    roundRect(ctx, 60, fy + 20, 80, 80, 16);
    ctx.fill();
    drawContain(ctx, mark, 68, fy + 28, 64, 64);
  }
  ctx.fillStyle = '#fff';
  const ev = fitText(ctx, kit.event.name, W - 400, 36, 26, 1, 800);
  ctx.fillText(ev.lines[0], mark ? 165 : 60, fy + 58);
  ctx.font = `600 26px ${FONT}`;
  ctx.fillStyle = 'rgba(255,255,255,0.85)';
  ctx.fillText(kit.event.dates, mark ? 165 : 60, fy + 96);
  ctx.textAlign = 'right';
  ctx.fillText(kit.event.hashtags.split(' ').slice(2, 3).join(' '), W - 60, fy + 76);
  ctx.textAlign = 'left';
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

// ---- Page -----------------------------------------------------------------

export function initSocialKit(root = document) {
  const dataEl = root.getElementById ? root.getElementById('social-kit-data') : document.getElementById('social-kit-data');
  if (!dataEl) return;
  const kit = JSON.parse(dataEl.textContent);
  const colors = { ...kit.colors };
  const sizeEl = document.querySelector('[data-social-size]');
  const size = () => sizeEl?.value ?? '1080x1350';

  const posts = [...document.querySelectorAll('[data-social-post]')].map((card) => {
    const post = kit.posts.find((p) => p.key === card.dataset.socialPost);
    const field = (name) => card.querySelector(`[data-field="${name}"]`);
    const current = () => ({ ...post, headline: field('headline').value, line: field('line').value, caption: field('caption').value });
    const canvas = card.querySelector('[data-preview]');
    const redraw = () => drawEventPost(canvas, size(), current(), kit, colors);

    field('headline').addEventListener('input', redraw);
    field('line').addEventListener('input', redraw);
    card.querySelector('[data-action="download"]').addEventListener('click', async () => {
      await redraw();
      save(await canvasBlob(canvas), `${kit.event.slug}-${post.key}-${size()}.png`);
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
    card.querySelector('[data-action="publish"]')?.addEventListener('click', (e) => publish(e.currentTarget, kit, canvas, redraw, current));

    return redraw;
  });

  const people = setUpPeople(kit, colors, size);
  const redrawAll = () => { posts.forEach((r) => r()); people?.redraw(); };
  sizeEl?.addEventListener('change', redrawAll);

  // Colours: live preview while picking; "Pick from logo" fills them in.
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

function setUpPeople(kit, colors, size) {
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
        await drawPersonCard(canvas, size(), person, kit, colors);
        save(await canvasBlob(canvas), `${slugify(person.name)}-${size()}.png`);
      });
      const cp = document.createElement('button');
      cp.type = 'button';
      cp.className = 'cb-btn cb-btn-secondary cb-btn-sm';
      cp.textContent = 'Copy caption';
      cp.addEventListener('click', () => copy(person.caption, cp));
      actions.append(dl, cp);
      box.append(canvas, name, actions);
      drawPersonCard(canvas, size(), person, kit, colors);
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
      await drawPersonCard(canvas, size(), person, kit, colors);
      let name = `${slugify(person.name)}.png`;
      for (let n = 2; used.has(name); n++) name = `${slugify(person.name)}-${n}.png`;
      used.add(name);
      files.push({ name, data: new Uint8Array(await (await canvasBlob(canvas)).arrayBuffer()) });
      captions.push(`${name}\n${person.caption}\n`);
    }
    files.push({ name: 'captions.txt', data: new TextEncoder().encode(captions.join('\n')) });
    save(new Blob([zipStore(files)], { type: 'application/zip' }), `${kit.event.slug}-people-${roleEl?.value || 'all'}-${size()}.zip`);
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
    body.append('title', post.headline);
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
