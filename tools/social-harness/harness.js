// The social harness page (index.html): draws every post, people card and reel of the sample events
// (fixtures.js) with the REAL modules, the way the Social media studio does (initSocialKit / initSocialReels),
// for tools/audit-social.mjs and tools/audit-reels.mjs in --harness mode — no login, no database.
// Everything is on window.__h; each drawing lands on a new canvas (window.__card, window.__reelCanvas) so the
// canvas probe (tests/browser/helpers/canvas-probe.mjs) sees only that picture.

import * as K from '/resources/js/social-kit.js';
import * as R from '/resources/js/social-reels.js';
import { qrSvg } from '/resources/js/qr-kit.js';
import { loadBrandFonts } from '/resources/js/brand.js';
import { makeSamples } from '/harness/fixtures.js';

const SIZES = { '1080x1350': [1080, 1350], '1080x1080': [1080, 1080], '1080x1920': [1080, 1920], '1600x900': [1600, 900], '1200x627': [1200, 627] };
const SIDE = { '1600x900': 520, '1200x627': 400 };
const ROLE_ART = ['organizer', 'speaker', 'volunteer', 'sponsor', 'media_partner', 'microsponsor', 'table_lead'];
const EFFECTS = { dots: true, glow: true, waves: true };
let SAMPLES = null;
let current = null;

const loaded = (url) => new Promise((resolve) => {
  if (!url) return resolve(null);
  const img = new Image();
  img.onload = () => resolve(img);
  img.onerror = () => resolve(null);
  img.src = url;
});

/** The sample's site artwork in social-kit's own store (what setSiteArt() fills on the page). */
async function useSample(id) {
  const s = SAMPLES[id];
  if (current === id) return s;
  const art = K.reelTools.siteArt();
  const [photo, illustration] = await Promise.all([loaded(s.art.photo), loaded(s.art.illustration)]);
  art.photo = photo ? [photo] : [];
  art.illustration = illustration ? [K.trimmed(illustration)] : [];
  art.pattern = [];
  K.useArt(0);
  current = id;
  return s;
}

/** Where a card's foot (QR card / block + brand bar) starts, and the top of its words, for the density checks. */
function layoutOf(size, design) {
  const [FW, H] = SIZES[size];
  const side = SIDE[size] ?? 0;
  const W = FW - side;
  const L = K._layout;
  if (design === 'daylight') {
    const g = L ? L.daylightGrid(W, H, side) : fallbackDaylight(W, H, side);
    return { W: FW, H, footTop: side ? H : g.cardTop, footLeft: side ? W : FW, areaTop: g.headerTop + g.logo.h };
  }
  const g = L ? L.grid(W, H, side) : fallbackGrid(W, H, side);
  return { W: FW, H, footTop: side ? H : g.blockTop, footLeft: side ? W : FW, areaTop: g.headerTop + g.logo.h };
}

// The grids as social-kit.js had them before it exported _layout (for renders of an older copy).
function fallbackDaylight(W, H, side) {
  const square = H < 1100;
  const tall = H >= 1900;
  const barH = side ? 0 : Math.round(H * (square ? 0.13 : (tall ? 0.1 : 0.12)));
  const cardH = side ? 0 : Math.round(H * (square ? 0.175 : (tall ? 0.125 : 0.15)));
  const barTop = H - barH;
  return { cardTop: side ? H - 80 + 26 : barTop - cardH - (square ? 6 : 12), headerTop: 68, logo: H < 700 ? { h: 82 } : (square ? { h: 104 } : { h: 126 }) };
}
function fallbackGrid(W, H, side) {
  const square = H < 1100;
  const tall = H >= 1900;
  const blockH = side ? 56 : Math.round(H * (square ? 0.25 : (tall ? 0.2 : 0.235)));
  return { blockTop: H - blockH, headerTop: 68, logo: H < 700 ? { h: 82 } : (square ? { h: 104 } : { h: 126 }) };
}

/** A post's content as the studio makes it (postContent + its place's artwork and the effects). */
function contentOf(s, key) {
  const { kit } = s;
  const index = kit.posts.findIndex((p) => p.key === key);
  const post = kit.posts[index];
  const pick = key === 'today' ? kit.schedule.days[0]?.key : (key === 'spotlight' ? kit.schedule.next : undefined);
  const fields = { kicker: post.kicker ?? '', headline: key === 'spotlight' ? '' : post.headline, line: key === 'spotlight' ? '' : post.line };
  return { ...K.postContent(post, fields, kit, pick), artIndex: index, effects: { ...EFFECTS } };
}

/** The names a picture must show whole: the people it draws. */
function namesIn(content) {
  const b = content.body ?? {};
  if (b.type === 'photos') return b.people.map((p) => p.name);
  if (b.type === 'session') return b.speakers;
  return [];
}

// ---- Contact sheets ----------------------------------------------------------

const sheets = new Map();
function sheetAdd(name, label, source, height = 300) {
  const k = height / source.height;
  const t = document.createElement('canvas');
  t.width = Math.round(source.width * k);
  t.height = height;
  t.getContext('2d').drawImage(source, 0, 0, t.width, t.height);
  if (!sheets.has(name)) sheets.set(name, []);
  sheets.get(name).push({ label, thumb: t });
}
function sheetPng(name, { maxWidth = 3200, title = name } = {}) {
  const items = sheets.get(name) ?? [];
  if (!items.length) return null;
  const gap = 16;
  const labelH = 30;
  const rows = [];
  let row = [];
  let w = gap;
  for (const it of items) {
    if (row.length && w + it.thumb.width + gap > maxWidth) { rows.push(row); row = []; w = gap; }
    row.push(it);
    w += it.thumb.width + gap;
  }
  if (row.length) rows.push(row);
  const width = Math.min(maxWidth, Math.max(...rows.map((r) => r.reduce((n, it) => n + it.thumb.width + gap, gap))));
  const rowH = Math.max(...items.map((it) => it.thumb.height)) + labelH + gap;
  const c = document.createElement('canvas');
  c.width = width;
  c.height = 60 + rows.length * rowH + gap;
  const x = c.getContext('2d');
  x.fillStyle = '#e9edf2';
  x.fillRect(0, 0, c.width, c.height);
  x.fillStyle = '#0f172a';
  x.font = '700 28px Poppins, sans-serif';
  x.fillText(title, gap, 40);
  rows.forEach((r, ri) => {
    let px = gap;
    const py = 60 + ri * rowH;
    for (const it of r) {
      x.drawImage(it.thumb, px, py);
      x.font = '500 17px Poppins, sans-serif';
      x.fillStyle = '#334155';
      x.fillText(it.label, px, py + it.thumb.height + 22, it.thumb.width);
      px += it.thumb.width + gap;
    }
  });
  sheets.delete(name);
  return c.toDataURL('image/png');
}

/** Two pictures side by side (a BEFORE | AFTER sheet), each under its label. */
async function compare(leftUrl, rightUrl, title) {
  const [a, b] = await Promise.all([loaded(leftUrl), loaded(rightUrl)]);
  if (!a && !b) return null;
  const w = Math.max(a?.width ?? 0, b?.width ?? 0);
  const k = Math.min(1, 2400 / w);
  const aw = (a?.width ?? 0) * k;
  const bw = (b?.width ?? 0) * k;
  const h = Math.max(a?.height ?? 0, b?.height ?? 0) * k;
  const c = document.createElement('canvas');
  c.width = Math.round(aw + bw + 72);
  c.height = Math.round(h + 90);
  const x = c.getContext('2d');
  x.fillStyle = '#ffffff';
  x.fillRect(0, 0, c.width, c.height);
  x.fillStyle = '#0f172a';
  x.font = '700 30px Poppins, sans-serif';
  x.fillText(`BEFORE  ·  ${title}`, 24, 44);
  x.fillText('AFTER', aw + 48, 44);
  if (a) x.drawImage(a, 24, 66, aw, a.height * k);
  if (b) x.drawImage(b, aw + 48, 66, bw, b.height * k);
  x.fillStyle = '#ff4d2d';
  x.fillRect(aw + 34, 60, 4, h);
  return c.toDataURL('image/png');
}

// ---- The API -----------------------------------------------------------------

window.__h = {
  async init() {
    await loadBrandFonts();
    await document.fonts.ready;
    SAMPLES = await makeSamples();
    return Object.fromEntries(Object.entries(SAMPLES).map(([id, s]) => [id, { posts: s.kit.posts.map((p) => p.key), people: s.kit.people.map((p) => ({ name: p.name, role: p.roles[0] ?? 'card' })) }]));
  },

  /** How many cards a post makes at this size and design. */
  async pages(id, key, size, design) {
    const s = await useSample(id);
    return K.planPost(contentOf(s, key), size, design, s.kit.colors).length;
  },

  /** Draws card `page` of a post on a new canvas (window.__card). */
  async post(id, key, size, design, page = 0) {
    const s = await useSample(id);
    const content = contentOf(s, key);
    const pages = K.planPost(content, size, design, s.kit.colors);
    const c = document.createElement('canvas');
    await K.drawEventPost(c, size, design, pages[page], s.kit, s.kit.colors, { index: page + 1, total: pages.length });
    window.__card = c;
    return { ...layoutOf(size, design), total: pages.length, names: namesIn(pages[page]) };
  },

  /** Draws a person's people card on a new canvas (window.__card). */
  async person(id, index, size, design) {
    const s = await useSample(id);
    const p = s.kit.people[index];
    const i = ROLE_ART.indexOf(p.roles?.[0]);
    const person = { ...p, artIndex: i < 0 ? ROLE_ART.length : i, effects: { ...EFFECTS } };
    const c = document.createElement('canvas');
    await K.drawPersonCard(c, size, design, person, s.kit, s.kit.colors);
    window.__card = c;
    return { ...layoutOf(size, design), names: [p.name] };
  },

  /** The reels of a sample (social-reels.js reelList, fed like the studio feeds it). */
  async reels(id) {
    const s = await useSample(id);
    return R.reelList(s.kit, this.contextPosts(s)).map((it) => ({ key: it.key, title: it.title, variant: it.variant }));
  },

  contextPosts(s) {
    return s.kit.posts.map((post) => ({
      key: post.key,
      label: post.label,
      content: () => contentOf(s, post.key),
      colors: () => s.kit.colors,
      captions: () => post.captions,
    }));
  },

  /** Gets reel `index` ready in `template` (null: the reel's own) and `style` (the posts' design). */
  async prepareReel(id, index, template = null, style = 'daylight') {
    const s = await useSample(id);
    const posts = this.contextPosts(s);
    Object.assign(K.socialContext, { kit: s.kit, colors: s.kit.colors, style: () => style, posts, ready: Promise.resolve() });
    const item = R.reelList(s.kit, posts)[index];
    if (template) item.template = template;
    const reel = await R._internals.prepare(item, s.kit, s.kit.colors, style);
    window.__reel = reel;
    const c = document.createElement('canvas');
    c.width = 1080;
    c.height = 1920;
    window.__reelCanvas = c;
    return {
      key: item.key,
      title: item.title,
      template: reel.template ?? 'legacy',
      duration: reel.duration,
      beat: reel.beat,
      // Names drawn as words: the people under faces (a logo scene's names are its pictures).
      scenes: reel.scenes.map((sc, i) => ({ i, start: sc.start, dur: sc.dur, end: Boolean(sc.end), hook: i === 0, step: sc.step ?? null, names: sc.kind === 'faces' ? (sc.words?.names ?? []).filter(Boolean) : [] })),
    };
  },

  /** One frame of the reel being looked at, on window.__reelCanvas (a fresh record for the probe). */
  reelFrame(t) {
    const c = window.__reelCanvas;
    window.__canvasOps?.set(c, []);
    R._internals.drawFrame(c.getContext('2d'), window.__reel, t);
    return true;
  },

  /** The QR kit's code for a link, drawn from its SVG on window.__card (black on white, 4-module margin). */
  async qr(url, px = 1200) {
    const img = await loaded(`data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrSvg(url))}`);
    const c = document.createElement('canvas');
    c.width = px;
    c.height = px;
    const x = c.getContext('2d');
    x.imageSmoothingEnabled = false;
    x.drawImage(img, 0, 0, px, px);
    window.__card = c;
    return true;
  },

  sheetAdd: (name, label, height) => { sheetAdd(name, label, window.__card, height); return true; },
  sheetAddReel: (name, label, height) => { sheetAdd(name, label, window.__reelCanvas, height); return true; },
  sheetPng,
  compare,
  png: (quality) => (quality ? window.__card.toDataURL('image/jpeg', quality) : window.__card.toDataURL('image/png')),
  reelJpg: (quality = 0.82) => window.__reelCanvas.toDataURL('image/jpeg', quality),
};
window.__ready = true;
