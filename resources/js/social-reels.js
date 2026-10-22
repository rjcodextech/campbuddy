// Reels for an event's social media (Social media page → Reels): short
// 1080 × 1920 videos with animation and music, made in the browser.
//
//   - one reel per post (the post's cards, as they are on the page),
//   - one reel each for the organizers, volunteers, media partners and table
//     leads, and one per five speakers.
//
// Since 21 Oct 2026 every reel stands on the Daylight ground (spec/25): paper
// to white, soft glows, a dot grid, thin waves rolling at the foot; the event's
// name ends on a highlighted word; the end card ends on the brand bar.
//
// Every reel: an intro (the event, "CampBuddy × WPSimplified"), its scenes and
// an end card (the event's QR in CampBuddy, WPSimplified's credit). It follows
// the event's look: its colours, fonts, pattern and skyline (social-kit.js).
// Nothing is ever cropped: photos, cards and artwork are drawn whole.
// Every word, QR and logo stays inside Instagram's safe zone (SAFE), the first
// frame already shows the event, and no word is under 34 px (docs spec/25).
//
// No two reels of an event look or sound alike: each gets its own look from
// its place in the list (lookFor): background, particles, transitions, how
// cards and photos come in, photo frame, header side, and its own music
// (key, tempo, chords, arpeggio, lead sound, groove).
//
// Music is made here too (no copyright claims): a short track whose beats
// land on the cuts, its mood picked per reel; or the admin's own track.
//
// The video is encoded with WebCodecs into an MP4 (H.264 + AAC, mp4-muxer),
// faster than real time and frame exact. Where the browser can't, it is
// recorded in real time instead (MediaRecorder).

import { ArrayBufferTarget, Muxer } from 'mp4-muxer';
import { drawEventPost, planPost, postContent, qrLink, reelTools as T, socialContext as C } from './social-kit.js';
import { loadBrandFonts } from './brand.js';
import { zipStore } from './zip-store.js';

const W = 1080;
const H = 1920;
const FPS = 30;
const RATE = 48000;
const SPEAKERS_PER_REEL = 5;
// No reel is shorter than this; one with more to show runs longer.
export const MIN_SECONDS = 30;
// More people than this and a reel shows them six at a time instead of one by one.
const ONE_BY_ONE = 6;
const GRID = 6;
// Instagram's own buttons and words cover the top, the foot and a rail on the
// right (the Reels mock-ups the owner collected): every word, QR and logo stays
// in this box — the combined Instagram Reels + YouTube Shorts zone of the brand
// guideline (§9): clear of the top 250, bottom 450, right 180 and left 64 px.
// Backgrounds, art and particles still fill the whole frame.
const SAFE = { top: 250, bottom: 1470, left: 64, right: 900 };
// Centred lines stay clear of the right rail: as wide as the box allows either side of the middle.
const TEXT_W = 2 * (SAFE.right - W / 2);

const GROUPS = [
  ['organizer', 'Organizers', 'warm'],
  ['speaker', 'Speakers', 'drive'],
  ['volunteer', 'Volunteers', 'warm'],
  ['media_partner', 'Media partners', 'bright'],
  ['table_lead', 'Table leads', 'warm'],
];

const POST_MOODS = { countdown: 'drive', speakers: 'drive', 'thank-you': 'soft', meet: 'warm', contribute: 'warm', tables: 'warm' };

// ---- Timing ----------------------------------------------------------------

const clamp = (v, lo = 0, hi = 1) => Math.min(hi, Math.max(lo, v));
const ease = (t) => 1 - (1 - clamp(t)) ** 3;
const back = (t) => {
  const x = clamp(t);
  const c1 = 1.5;
  return 1 + (c1 + 1) * (x - 1) ** 3 + c1 * (x - 1) ** 2;
};
const span = (t, start, dur) => clamp((t - start) / dur);

// ---- Drawing helpers ---------------------------------------------------------

function canvas(w = W, h = H) {
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  return c;
}

/**
 * Lines of text at the largest size where all of it fits in `maxLines`; when
 * even `min` is too big it may take two more lines and get smaller still —
 * never cut short, so every detail shows.
 */
function fitAll(ctx, text, maxWidth, size, min, maxLines, weight, family) {
  const at = (s, most) => {
    ctx.font = `${weight} ${s}px ${family}`;
    const lines = T.wrap(ctx, text, maxWidth);
    return lines.length <= most && lines.every((l) => ctx.measureText(l).width <= maxWidth) ? lines : null;
  };
  for (let s = size; s >= min; s -= 2) {
    const lines = at(s, maxLines);
    if (lines) return { lines, size: s };
  }
  for (let s = min; s >= 18; s -= 2) {
    const lines = at(s, maxLines + 2);
    if (lines) return { lines, size: s };
  }
  ctx.font = `${weight} 18px ${family}`;
  return { lines: T.wrap(ctx, text, maxWidth), size: 18 };
}

/** Centred lines; each rises into place a little after the one before. Returns the y under the last line. */
function risingLines(ctx, fit, y, color, t, start, { weight = 800, family, lh = 1.12, align = 'center', x = W / 2, stagger = 0.08, maxWidth = TEXT_W, highlight = null } = {}) {
  ctx.textAlign = align;
  ctx.fillStyle = color;
  fit.lines.forEach((line, i) => {
    const p = ease(span(t, start + i * stagger, 0.5));
    ctx.save();
    ctx.globalAlpha *= p;
    ctx.font = `${weight} ${fit.size}px ${family}`;
    const ly = y + (i + 1) * fit.size * lh + (1 - p) * 40;
    const words = line.split(' ');
    if (highlight && i === fit.lines.length - 1 && words.length > 1) {
      // Daylight: the name's last word in the main-to-deep gradient.
      const last = words.pop();
      const head = `${words.join(' ')} `;
      const hw = ctx.measureText(head).width;
      const lw = ctx.measureText(last).width;
      const left = align === 'center' ? x - (hw + lw) / 2 : x;
      ctx.textAlign = 'left';
      ctx.fillText(head, left, ly);
      const gr = ctx.createLinearGradient(left + hw, ly - fit.size, left + hw + lw, ly);
      gr.addColorStop(0, highlight[0]);
      gr.addColorStop(1, highlight[1]);
      ctx.fillStyle = gr;
      ctx.fillText(last, left + hw, ly);
    } else if (highlight && i === fit.lines.length - 1) {
      ctx.fillStyle = highlight[0];
      ctx.fillText(line, x, ly, maxWidth);
    } else ctx.fillText(line, x, ly, maxWidth);
    ctx.restore();
  });
  ctx.textAlign = 'left';
  return y + fit.lines.length * fit.size * lh;
}

function pillAt(ctx, text, cx, y, bg, fg, size = 34, p = 1) {
  const { text: font } = T.fonts();
  ctx.save();
  ctx.font = `800 ${size}px ${font}`;
  const w = Math.min(TEXT_W, ctx.measureText(text).width + size * 1.4);
  const h = size * 1.9;
  ctx.globalAlpha *= p;
  ctx.translate(cx, y + h / 2);
  ctx.scale(0.7 + 0.3 * back(p), 0.7 + 0.3 * back(p));
  ctx.fillStyle = bg;
  T.roundRect(ctx, -w / 2, -h / 2, w, h, h / 2);
  ctx.fill();
  ctx.fillStyle = fg;
  ctx.textAlign = 'center';
  ctx.fillText(text, 0, size * 0.36, w - size);
  ctx.restore();
  return y + h;
}

/** A photo whole in a rounded frame (contain, never cropped); initials when there is none. */
function photoFrame(ctx, img, name, x, y, size, ring, fill, ink, reveal = 1, shape = 'rounded') {
  // The reel's frame: rounded (the default), softer corners, or a ticket with a second ring.
  const r = shape === 'soft' ? size * 0.2 : shape === 'ticket' ? 18 : 44;
  ctx.save();
  if (shape === 'ticket') {
    ctx.strokeStyle = ring;
    ctx.lineWidth = 6;
    T.roundRect(ctx, x - 26, y - 26, size + 52, size + 52, r + 22);
    ctx.stroke();
  }
  ctx.fillStyle = ring;
  T.roundRect(ctx, x - 10, y - 10, size + 20, size + 20, r + 8);
  ctx.fill();
  ctx.fillStyle = fill;
  T.roundRect(ctx, x, y, size, size, r);
  ctx.fill();
  T.roundRect(ctx, x, y, size, size, r);
  ctx.clip();
  if (reveal < 1) {
    ctx.beginPath();
    ctx.arc(x + size / 2, y + size / 2, (size * 0.75) * ease(reveal), 0, Math.PI * 2);
    ctx.clip();
  }
  if (img) T.drawContain(ctx, img, x, y, size, size);
  else {
    const { head } = T.fonts();
    ctx.fillStyle = ink;
    ctx.font = `800 ${Math.round(size * 0.36)}px ${head}`;
    ctx.textAlign = 'center';
    ctx.fillText(T.initials(name), x + size / 2, y + size * 0.62);
  }
  ctx.restore();
}

/** The event's logo on its tile, with a hairline and a soft shadow so a light tile still shows on the light ground. */
function logoCard(ctx, reel, x, y, size, r) {
  ctx.save();
  ctx.shadowColor = 'rgba(0,0,0,0.16)';
  ctx.shadowBlur = size * 0.12;
  ctx.shadowOffsetY = size * 0.03;
  T.logoTile(ctx, reel.logo, x, y, size, size, reel.pal, r);
  ctx.restore();
  ctx.save();
  ctx.strokeStyle = T.mix(reel.pal.ink, reel.pal.paper, 0.82);
  ctx.lineWidth = Math.max(2, size * 0.008);
  T.roundRect(ctx, x, y, size, size, r);
  ctx.stroke();
  ctx.restore();
}

// ---- The event's look, moving ------------------------------------------------

/** What every frame stands on: the light colour, the site's pattern drifting faintly, its skyline rising at the foot. */
function backdrop(ctx, time, reel) {
  const { pal, art } = reel;
  ctx.fillStyle = pal.paper;
  ctx.fillRect(0, 0, W, H);

  const kind = reel.look?.backdrop ?? 'pattern';
  if (reel.daylight !== false) daylightGround(ctx, time, reel, kind);
  else if (kind !== 'pattern') backdropShapes(ctx, time, reel, kind);
  else if (art.patternTile) {
    const pattern = ctx.createPattern(art.patternTile, 'repeat');
    pattern.setTransform(new DOMMatrix().translate(-(time * 8) % art.patternTile.width, (time * 14) % art.patternTile.height));
    ctx.save();
    ctx.globalAlpha = 0.055;
    ctx.fillStyle = pattern;
    ctx.fillRect(0, 0, W, H);
    ctx.restore();
  } else {
    // No pattern on the site: the event's own soft shapes, drifting.
    ctx.save();
    reel.dots.forEach((d) => {
      ctx.globalAlpha = d.a;
      ctx.fillStyle = d.c;
      ctx.beginPath();
      ctx.arc(d.x + Math.sin(time * d.s + d.p) * 24, (d.y - time * d.v * 20 + H * 2) % (H + 200) - 100, d.r, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();
  }

  if (art.skyline) {
    // Whole, as wide as fits, standing on the foot; it rises in at the start and breathes after.
    const img = art.skyline;
    const k = Math.min((W - 40) / img.width, (H * 0.3) / img.height);
    const w = img.width * k;
    const h = img.height * k;
    const rise = (1 - ease(span(time, 0, 1.4))) * 160;
    ctx.save();
    ctx.globalAlpha = 0.2;
    ctx.drawImage(img, (W - w) / 2, H - h - 10 + rise + Math.sin(time * 0.8) * 4, w, h);
    ctx.restore();
  }
}

/**
 * The Daylight ground (spec/25): paper fading to white, two soft glows that
 * drift (where they sit follows the reel's look, so reels still differ), a
 * faint dot grid on the right half and four thin waves rolling at the foot.
 */
function daylightGround(ctx, time, reel, kind) {
  const { pal } = reel;
  const white = T.mix(pal.paper, '#ffffff', 0.85);
  const g = ctx.createLinearGradient(0, 0, 0, H);
  g.addColorStop(0, T.mix(pal.paper, '#ffffff', 0.25));
  g.addColorStop(1, white);
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, W, H);
  const corners = { pattern: [0.94, 0.04, 0.04, 0.72], aurora: [0.06, 0.06, 0.94, 0.7], stripes: [0.9, 0.3, 0.1, 0.8], rings: [0.5, 0.02, 0.5, 0.84], dotwave: [0.96, 0.5, 0.04, 0.2] }[kind] ?? [0.94, 0.04, 0.04, 0.72];
  const glow = (x, y, r, color, a) => {
    const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
    rg.addColorStop(0, T.alpha(color, a));
    rg.addColorStop(1, T.alpha(color, 0));
    ctx.fillStyle = rg;
    ctx.fillRect(0, 0, W, H);
  };
  glow(W * corners[0] + Math.sin(time * 0.3) * 40, H * corners[1] + Math.cos(time * 0.25) * 40, W * 0.8, pal.secondary, 0.22);
  glow(W * corners[2] + Math.cos(time * 0.27) * 40, H * corners[3] + Math.sin(time * 0.2) * 40, W * 0.7, pal.primary, 0.1);
  ctx.fillStyle = T.alpha(pal.primary, 0.12);
  for (let y = 13; y < H; y += 26) {
    for (let x = W / 2 + 13; x < W; x += 26) ctx.fillRect(x - 1, y - 1, 2, 2);
  }
  ctx.save();
  ctx.lineWidth = 3;
  ctx.lineCap = 'round';
  for (let i = 0; i < 4; i++) {
    const y0 = H * 0.9 - i * 24;
    ctx.strokeStyle = T.alpha(pal.primary, 0.26 * (1 - i * 0.22));
    ctx.beginPath();
    for (let x = 0; x <= W; x += 12) {
      const y = y0 + Math.sin((x / (W / 1.4)) * Math.PI * 2 + i * 0.9 - time * 0.8) * (14 + i * 3);
      if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    }
    ctx.stroke();
  }
  ctx.restore();
}

/** The backgrounds a reel can have besides the site's own pattern: soft, in the event's colours, always moving. */
function backdropShapes(ctx, time, reel, kind) {
  const { pal } = reel;
  const tones = [pal.primary, pal.secondary, ...(pal.extras ?? [])];
  ctx.save();
  if (kind === 'aurora') {
    // Three large glows wandering slowly.
    tones.slice(0, 3).forEach((c, i) => {
      const x = W * (0.2 + 0.6 * (0.5 + 0.5 * Math.sin(time * 0.23 + i * 2.1)));
      const y = H * (0.2 + 0.3 * i + Math.cos(time * 0.17 + i) * 0.08);
      const r = 520 + i * 90;
      const g = ctx.createRadialGradient(x, y, 0, x, y, r);
      g.addColorStop(0, T.mix(c, pal.paper, 0.55));
      g.addColorStop(1, pal.paper);
      ctx.globalAlpha = 0.35;
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, W, H);
    });
  } else if (kind === 'stripes') {
    // Wide diagonal bands sliding across.
    ctx.translate(W / 2, H / 2);
    ctx.rotate(-Math.PI / 5);
    const gap = 180;
    const shift = (time * 40) % (gap * 2);
    ctx.globalAlpha = 0.06;
    ctx.fillStyle = pal.primary;
    for (let x = -H * 1.2 - shift; x < H * 1.2; x += gap * 2) ctx.fillRect(x, -H * 1.2, gap, H * 2.4);
  } else if (kind === 'rings') {
    // Rings breathing out from behind the middle, one after another.
    ctx.lineWidth = 18;
    for (let i = 0; i < 6; i++) {
      const q = (time * 0.18 + i / 6) % 1;
      ctx.globalAlpha = 0.09 * (1 - q);
      ctx.strokeStyle = tones[i % tones.length];
      ctx.beginPath();
      ctx.arc(W / 2, H * 0.42, 120 + q * 1100, 0, Math.PI * 2);
      ctx.stroke();
    }
  } else if (kind === 'dotwave') {
    // A dot grid with a wave rolling through it.
    const step = 72;
    ctx.fillStyle = pal.primary;
    ctx.globalAlpha = 0.1;
    for (let y = step / 2; y < H; y += step) {
      for (let x = step / 2; x < W; x += step) {
        const r = 4 + 7 * (0.5 + 0.5 * Math.sin(x * 0.006 + y * 0.004 - time * 1.6));
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fill();
      }
    }
  }
  ctx.restore();
}

// The header row (logo, label), just under Instagram's top bar; a scene's content starts under it.
const HEADER_Y = SAFE.top - 20;
const CONTENT_Y = HEADER_Y + 190;

function header(ctx, reel, t, label) {
  const p = ease(span(t, 0, 0.5));
  // Some reels keep the logo on the right and the label on the left.
  const mirror = reel.look?.mirror ?? false;
  ctx.save();
  ctx.globalAlpha *= p;
  if (reel.logo) logoCard(ctx, reel, mirror ? SAFE.right - 150 : SAFE.left, HEADER_Y, 150, 28);
  if (label) {
    const { text } = T.fonts();
    ctx.font = `800 34px ${text}`;
    const w = ctx.measureText(label).width + 52;
    const x = mirror ? SAFE.left : SAFE.right - w;
    ctx.fillStyle = reel.pal.primary;
    T.roundRect(ctx, x, HEADER_Y + 42, w, 66, 33);
    ctx.fill();
    ctx.fillStyle = reel.pal.onPrimary;
    ctx.textAlign = 'center';
    ctx.fillText(label, x + w / 2, HEADER_Y + 87);
    ctx.textAlign = 'left';
  }
  ctx.restore();
}

/**
 * How a card or photo comes in, about the point it is drawn around. p runs
 * 0 → 1; dir is the side it comes from. Every kind ends at rest (identity).
 */
function entrance(ctx, kind, p, dir = 1) {
  const e = ease(p);
  if (kind === 'slide') {
    ctx.translate((1 - e) * 760 * dir, 0);
    ctx.rotate((1 - e) * 0.06 * dir);
  } else if (kind === 'flip') {
    // Turns to face the viewer, like a card flipped over.
    ctx.scale(Math.max(0.02, e), 1);
    ctx.rotate((1 - e) * -0.05);
  } else if (kind === 'zoom') {
    const k = 1 + (1 - e) * 0.5;
    ctx.scale(k, k);
  } else if (kind === 'rise') {
    const b = back(p);
    ctx.translate(0, (1 - b) * 280);
    ctx.rotate((1 - b) * -4 * (Math.PI / 180));
    const k = 0.72 + 0.28 * b;
    ctx.scale(k, k);
  } else {
    // pop
    const b = back(p);
    const k = 0.6 + 0.4 * b;
    ctx.scale(k, k);
  }
}

/** The scene's words leave in its last moments (the backdrop stays: it runs through the whole reel). */
function leaving(ctx, t, dur) {
  const out = span(t, dur - 0.32, 0.32);
  ctx.globalAlpha = 1 - out;
  ctx.translate(0, -60 * ease(out));
}

// ---- Scenes ------------------------------------------------------------------

function introScene(kicker) {
  return {
    beats: 6,
    draw(ctx, t, dur, reel) {
      const layout = reel.look?.intro ?? 'center';
      if (layout !== 'center') return introLayout(ctx, t, dur, reel, kicker, layout);
      const { pal, kit } = reel;
      const { head, text } = T.fonts();
      ctx.save();
      leaving(ctx, t, dur);

      // The event's logo settles (already there on the first frame: it is the reel's cover).
      const pop = back(span(t, -0.4, 0.6));
      ctx.save();
      ctx.globalAlpha *= clamp(pop);
      ctx.translate(W / 2, 500);
      ctx.scale(0.4 + 0.6 * pop, 0.4 + 0.6 * pop);
      if (reel.logo) logoCard(ctx, reel, -180, -180, 360, 54);
      ctx.restore();

      let y = pillAt(ctx, kicker, W / 2, 740, pal.primary, pal.onPrimary, 38, ease(span(t, -0.2, 0.4)));
      const line = [kit.event.dates, kit.event.place].filter(Boolean).join(' · ');
      const { n: name, s: sub } = introWords(ctx, kit.event.name, line, y + 20, TEXT_W, 124, 3, 2, 800, head, text, 70);
      y = risingLines(ctx, name, y + 20, pal.ink, t, -0.35, { family: head, highlight: highlightOf(reel) });
      ctx.save();
      ctx.globalAlpha *= ease(span(t, 0.25, 0.5));
      ctx.fillStyle = T.mix(pal.ink, pal.paper, 0.3);
      ctx.textAlign = 'center';
      ctx.font = `600 ${sub.size}px ${text}`;
      sub.lines.forEach((l, i) => ctx.fillText(l, W / 2, y + 70 + i * sub.size * 1.25));
      ctx.restore();

      // "CampBuddy × WPSimplified", under the words, inside the safe zone.
      partnersBadge(ctx, reel, badgeY(y + 70 + sub.lines.length * sub.size * 1.25), ease(span(t, 0.5, 0.5)));
      ctx.restore();
    },
  };
}

/**
 * The intro's other layouts: 'left' (logo and words down the left, like a
 * poster) and 'band' (a colour band drops in with the logo on it, the name
 * beneath).
 */
function introLayout(ctx, t, dur, reel, kicker, layout) {
  const { pal, kit } = reel;
  const { head, text } = T.fonts();
  const line = [kit.event.dates, kit.event.place].filter(Boolean).join(' · ');
  ctx.save();
  leaving(ctx, t, dur);

  let bottom;
  if (layout === 'band') {
    // The band is already down on the first frame; the logo settles on it.
    const drop = ease(span(t, -0.4, 0.6));
    ctx.fillStyle = pal.primary;
    ctx.fillRect(0, 0, W, 720 * drop);
    ctx.fillStyle = pal.secondary;
    ctx.fillRect(0, 720 * drop, W * ease(span(t, -0.2, 0.5)), 18);
    const pop = back(span(t, -0.3, 0.6));
    ctx.save();
    ctx.globalAlpha *= clamp(pop);
    ctx.translate(W / 2, 450);
    ctx.rotate((1 - clamp(pop)) * -0.2);
    ctx.scale(0.4 + 0.6 * pop, 0.4 + 0.6 * pop);
    if (reel.logo) logoCard(ctx, reel, -160, -160, 320, 48);
    ctx.restore();
    let y = pillAt(ctx, kicker, W / 2, 780, pal.secondary, pal.onSecondary, 38, ease(span(t, -0.1, 0.4)));
    const { n: name, s: sub } = introWords(ctx, kit.event.name, line, y + 20, TEXT_W, 124, 3, 2, 900, head, text, 30);
    y = risingLines(ctx, name, y + 20, pal.ink, t, -0.3, { weight: 900, family: head, highlight: highlightOf(reel) });
    bottom = risingLines(ctx, sub, y + 30, T.mix(pal.ink, pal.paper, 0.3), t, 0.3, { weight: 600, family: text, lh: 1.25 });
  } else {
    // left
    const x = SAFE.left + 10;
    const width = SAFE.right - (x + 44);
    const slide = ease(span(t, -0.45, 0.6));
    ctx.save();
    ctx.globalAlpha *= slide;
    ctx.translate(-(1 - slide) * 300, 0);
    if (reel.logo) logoCard(ctx, reel, x, 300, 300, 46);
    ctx.fillStyle = pal.primary;
    ctx.fillRect(x, 660, 14, 640 * ease(span(t, 0, 0.8)));
    ctx.restore();
    const { text: font } = T.fonts();
    ctx.font = `800 38px ${font}`;
    const pw = Math.min(width, ctx.measureText(kicker).width + 38 * 1.4);
    let y = pillAt(ctx, kicker, x + 44 + pw / 2, 680, pal.primary, pal.onPrimary, 38, ease(span(t, -0.1, 0.4)));
    const { n: name, s: sub } = introWords(ctx, kit.event.name, line, y + 10, width, 132, 4, 3, 900, head, text, 30);
    y = risingLines(ctx, name, y + 10, pal.ink, t, -0.3, { weight: 900, family: head, align: 'left', x: x + 44, maxWidth: width, highlight: highlightOf(reel) });
    bottom = risingLines(ctx, sub, y + 30, T.mix(pal.ink, pal.paper, 0.3), t, 0.3, { weight: 600, family: text, lh: 1.25, align: 'left', x: x + 44, maxWidth: width });
  }

  partnersBadge(ctx, reel, badgeY(bottom), ease(span(t, 0.5, 0.5)));
  ctx.restore();
}

/** The highlight of the event's name (main to deep, both readable on the light ground), or none off Daylight. */
function highlightOf(reel) {
  if (reel.daylight === false) return null;
  const { pal } = reel;
  const white = T.mix(pal.paper, '#ffffff', 0.85);
  const from = T.readableOn([pal.primary, T.mix(pal.primary, pal.ink, 0.3), pal.ink], [white, pal.paper]);
  return [from, T.readableOn([T.mix(pal.primary, pal.ink, 0.5), pal.ink], [white, pal.paper])];
}

/** The brand bar (spec/25): CampBuddy and campbuddy.club, two pills of one height, centred as a pair at `y`. */
function brandBar(ctx, reel, y, p = 1) {
  if (p <= 0) return;
  const { text } = T.fonts();
  const h = 96;
  const gap = 24;
  const white = T.alpha(T.mix(reel.pal.paper, '#ffffff', 0.85), 0.94);
  const mh = h * 0.56;
  const mw = reel.campbuddy ? mh * (reel.campbuddy.width / reel.campbuddy.height) : 0;
  const lw = mw + h * 0.8;
  ctx.save();
  ctx.globalAlpha *= p;
  ctx.font = `600 ${Math.round(h * 0.36)}px ${text}`;
  const url = 'campbuddy.club';
  const uw = ctx.measureText(url).width;
  const icon = h * 0.36;
  const rw = h * 0.4 + icon + 14 + uw + h * 0.4;
  const x0 = (W - (lw + gap + rw)) / 2;
  const pillShape = (x, w) => {
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.14)';
    ctx.shadowBlur = 30;
    ctx.shadowOffsetY = 8;
    ctx.fillStyle = white;
    T.roundRect(ctx, x, y, w, h, h / 2);
    ctx.fill();
    ctx.restore();
  };
  pillShape(x0, lw);
  if (reel.campbuddy) ctx.drawImage(reel.campbuddy, x0 + (lw - mw) / 2, y + (h - mh) / 2, mw, mh);
  const rx = x0 + lw + gap;
  pillShape(rx, rw);
  T.globe(ctx, rx + h * 0.4 + icon / 2, y + h / 2, icon / 2, reel.pal.ink);
  ctx.fillStyle = reel.pal.ink;
  ctx.textAlign = 'left';
  ctx.fillText(url, rx + h * 0.4 + icon + 14, y + h / 2 + h * 0.13);
  ctx.restore();
  return y + h;
}

/**
 * The intro's name and dates line, sized so they end above the "CampBuddy × WPSimplified" badge: a long name
 * or venue address used to run under it (the second line of "St. Joseph's Boys' High School" was hidden).
 * `gap` is the room between the name and the dates line.
 */
function introWords(ctx, name, line, top, width, nameMax, nameLines, subLines, weight, head, text, gap) {
  const limit = SAFE.bottom - 120 - 50;
  let fit = null;
  for (const k of [1, 0.88, 0.76, 0.66, 0.58]) {
    const n = fitAll(ctx, name, width, Math.round(nameMax * k), Math.min(60, Math.round(nameMax * k)), nameLines, weight, head);
    const s = fitAll(ctx, line, width, Math.round(46 * Math.max(k, 0.8)), 30, subLines, 600, text);
    fit = { n, s };
    if (top + n.lines.length * n.size * 1.12 + gap + s.lines.length * s.size * 1.25 <= limit) break;
  }
  return fit;
}

/** Where the "CampBuddy × WPSimplified" badge goes: under the words, never below the safe zone. */
function badgeY(below) {
  return Math.min(SAFE.bottom - 120, Math.max(below + 50, SAFE.bottom - 260));
}

function partnersBadge(ctx, reel, y, p) {
  if (p <= 0) return;
  const { text } = T.fonts();
  ctx.save();
  ctx.globalAlpha *= p;
  const h = 120;
  const cbW = reel.campbuddy ? (reel.campbuddy.width / reel.campbuddy.height) * 64 : 0;
  const wpW = reel.wps ? (reel.wps.width / reel.wps.height) * 64 : 0;
  const w = cbW + wpW + 150;
  const x = (W - w) / 2;
  ctx.shadowColor = 'rgba(0,0,0,0.12)';
  ctx.shadowBlur = 30;
  ctx.fillStyle = '#ffffff';
  T.roundRect(ctx, x, y + (1 - p) * 30, w, h, 30);
  ctx.fill();
  ctx.shadowBlur = 0;
  if (reel.campbuddy) ctx.drawImage(reel.campbuddy, x + 40, y + 28 + (1 - p) * 30, cbW, 64);
  ctx.fillStyle = '#7a7a7a';
  ctx.font = `700 40px ${text}`;
  ctx.textAlign = 'center';
  ctx.fillText('×', x + 40 + cbW + 35, y + 76 + (1 - p) * 30);
  ctx.textAlign = 'left';
  if (reel.wps) ctx.drawImage(reel.wps, x + 110 + cbW, y + 28 + (1 - p) * 30, wpW, 64);
  ctx.restore();
}

/** One of a post's cards, whole, settling into the frame; the next one pushes it out. */
function cardScene(card, index, total, last) {
  return {
    beats: 8,
    stretch: true,
    draw(ctx, t, dur, reel) {
      // As big as the safe zone allows, so the card's own QR stays clear of Instagram's caption.
      const s = (SAFE.bottom - SAFE.top) / H / 1.03;
      const w = W * s;
      const h = H * s;
      const look = reel.look ?? {};
      const dir = index % 2 ? -(look.side ?? 1) : (look.side ?? 1);
      const out = last ? 0 : ease(span(t, dur - 0.4, 0.4));
      // Held for a while: it drifts a touch closer over the hold (never past the safe zone).
      const scale = 1 + 0.03 * ease(span(t, 0.8, Math.max(1, dur - 1.2)));
      ctx.save();
      ctx.globalAlpha = clamp(span(t, 0, 0.25)) * (last ? 1 - span(t, dur - 0.3, 0.3) : 1);
      // It leaves to one side; the next card comes in from the other.
      ctx.translate(W / 2 + out * 1300 * dir, (SAFE.top + SAFE.bottom) / 2 + Math.sin(t * 1.6) * 4);
      ctx.rotate(out * 6 * dir * (Math.PI / 180));
      entrance(ctx, (look.motion ?? 'pop') === 'pop' ? 'rise' : look.motion, span(t, 0, 0.75), dir);
      ctx.scale(scale, scale);
      if (look.mat === 'tilt' || look.mat === 'stack') {
        // A colour panel behind the card (two, for a stack), turned a little.
        const turns = look.mat === 'stack' ? [5, -3] : [-4];
        turns.forEach((deg, k) => {
          ctx.save();
          ctx.rotate(deg * dir * (Math.PI / 180));
          ctx.fillStyle = k ? reel.pal.secondary : reel.pal.primary;
          ctx.globalAlpha *= 0.9;
          T.roundRect(ctx, -w / 2 - 6, -h / 2 - 6, w + 12, h + 12, 34);
          ctx.fill();
          ctx.restore();
        });
      }
      ctx.shadowColor = 'rgba(0,0,0,0.28)';
      ctx.shadowBlur = 60;
      ctx.shadowOffsetY = 24;
      ctx.fillStyle = reel.pal.paper;
      T.roundRect(ctx, -w / 2, -h / 2, w, h, 30);
      ctx.fill();
      ctx.shadowColor = 'transparent';
      T.roundRect(ctx, -w / 2, -h / 2, w, h, 30);
      ctx.clip();
      ctx.drawImage(card, -w / 2, -h / 2, w, h);
      ctx.restore();

      if (total > 1) {
        ctx.save();
        for (let i = 0; i < total; i++) {
          ctx.fillStyle = i === index ? reel.pal.primary : T.mix(reel.pal.primary, reel.pal.paper, 0.7);
          ctx.beginPath();
          ctx.arc(W / 2 + (i - (total - 1) / 2) * 34, SAFE.bottom + 40, i === index ? 11 : 8, 0, Math.PI * 2);
          ctx.fill();
        }
        ctx.restore();
      }
    },
  };
}

function groupIntroScene(role, title, line, count) {
  return {
    beats: 5,
    draw(ctx, t, dur, reel) {
      const { pal } = reel;
      const { head, text } = T.fonts();
      const tone = T.roleColor({ roles: [role] }, reel.colors);
      ctx.save();
      header(ctx, reel, t, '');
      leaving(ctx, t, dur);
      // A band of the role's colour sweeps in behind the title.
      const sweep = ease(span(t, 0.05, 0.55));
      ctx.fillStyle = tone;
      ctx.fillRect(0, 640, W * sweep, 520);
      const titleFit = fitAll(ctx, title, TEXT_W, 140, 72, 2, 900, head);
      let y = risingLines(ctx, titleFit, 640 + (520 - titleFit.lines.length * titleFit.size * 1.1) / 2 - 20, T.textOn(tone, pal.ink, pal.paper), t, 0.3, { weight: 900, family: head, lh: 1.1 });
      y = 1200;
      const lineFit = fitAll(ctx, line, TEXT_W, 54, 34, 3, 700, text);
      y = risingLines(ctx, lineFit, y, pal.ink, t, 0.7, { weight: 700, family: text, lh: 1.25 });
      pillAt(ctx, count, W / 2, Math.min(y + 50, SAFE.bottom - 72), pal.secondary, pal.onSecondary, 36, ease(span(t, 1.0, 0.4)));
      ctx.restore();
    },
  };
}

function personScene(person, index, total, long, role) {
  return {
    beats: long ? 8 : 5,
    stretch: true,
    draw(ctx, t, dur, reel) {
      const { pal, colors, kit } = reel;
      const { head, text } = T.fonts();
      const tone = T.roleColor({ ...person, roles: [role] }, colors);
      const img = reel.photos.get(person.id);
      ctx.save();
      header(ctx, reel, t, `${index + 1} / ${total}`);
      leaving(ctx, t, dur);

      const look = reel.look ?? {};
      // The words first, measured, so the photo takes what's left of the safe zone.
      const name = fitAll(ctx, person.name, TEXT_W, 96, 54, 2, 800, head);
      const roles = (person.role_labels ?? []).join(' · ');
      const detail = person.talk ? `“${person.talk}”` : [person.card?.role, person.card?.company].filter(Boolean).join(', ');
      const fit = detail ? fitAll(ctx, detail, TEXT_W, person.talk ? 56 : 46, 34, person.talk ? 4 : 2, person.talk ? 700 : 600, text) : null;
      const cta = fitAll(ctx, T.personCta(person, kit), TEXT_W, 40, 34, 2, 700, text);
      const wordsH = name.lines.length * name.size * 1.12 + (roles ? 30 + 34 * 1.9 : 0) + (fit ? 30 + fit.lines.length * fit.size * 1.25 : 0) + 50 + cta.lines.length * cta.size * 1.25;
      const size = Math.max(320, Math.min(long ? 600 : 640, SAFE.bottom - CONTENT_Y - 100 - wordsH));
      ctx.save();
      ctx.globalAlpha *= clamp(span(t, 0, 0.2));
      ctx.translate(W / 2, CONTENT_Y + size / 2);
      entrance(ctx, look.motion, span(t, 0, 0.6), index % 2 ? -1 : 1);
      photoFrame(ctx, img, person.name, -size / 2, -size / 2, size, tone, T.mix(tone, pal.paper, 0.82), tone, span(t, 0.1, 0.6), look.frame);
      ctx.restore();

      let y = CONTENT_Y + size + 40;
      y = risingLines(ctx, name, y, pal.ink, t, 0.35, { family: head });
      if (roles) y = pillAt(ctx, roles, W / 2, y + 30, tone, T.textOn(tone, pal.ink, pal.paper), 34, ease(span(t, 0.6, 0.4)));
      if (fit) y = risingLines(ctx, fit, y + 30, T.mix(pal.ink, pal.paper, person.talk ? 0 : 0.3), t, 0.8, { weight: person.talk ? 700 : 600, family: text, lh: 1.25, stagger: 0.06 });

      ctx.save();
      ctx.globalAlpha *= ease(span(t, 1.1, 0.4));
      ctx.fillStyle = tone;
      ctx.textAlign = 'center';
      ctx.font = `700 ${cta.size}px ${text}`;
      cta.lines.forEach((l, i) => ctx.fillText(l, W / 2, y + 50 + (i + 0.8) * cta.size * 1.25, TEXT_W));
      ctx.restore();
      ctx.restore();
    },
  };
}

function gridScene(people, first, total, role) {
  return {
    beats: 7,
    stretch: true,
    draw(ctx, t, dur, reel) {
      const { pal, colors } = reel;
      const { head } = T.fonts();
      ctx.save();
      header(ctx, reel, t, `${first + 1}–${first + people.length} of ${total}`);
      leaving(ctx, t, dur);
      const cols = 2;
      const cellW = 360;
      const rows = Math.ceil(people.length / cols);
      // Names measured first: a two-line name gets its room, so it never runs into the photo below it,
      // and the grid starts under the header (the logo and the count pill), never behind it.
      const fits = people.map((person) => fitAll(ctx, person.name, cellW - 20, 42, 28, 2, 800, head));
      const nameH = Math.max(...fits.map((f) => f.lines.length * f.size * 1.1));
      const room = SAFE.bottom - CONTENT_Y;
      const photo = Math.max(160, Math.min(240, room / rows - 28 - nameH - 30));
      const rowH = photo + 28 + nameH + 30;
      const top = CONTENT_Y + Math.max(0, (room - rows * rowH) / 2);
      people.forEach((person, i) => {
        const col = i % cols;
        const row = Math.floor(i / cols);
        // An odd one out on the last row stands in the middle.
        const alone = row === rows - 1 && people.length % cols === 1;
        const cx = alone ? W / 2 : W / 2 + (col - 0.5) * (cellW + 40);
        const y = top + row * rowH;
        const tone = T.roleColor({ ...person, roles: [role] }, colors);
        ctx.save();
        ctx.globalAlpha *= clamp(span(t, 0.1 + i * 0.12, 0.2));
        ctx.translate(cx, y + photo / 2);
        entrance(ctx, reel.look?.motion, span(t, 0.1 + i * 0.12, 0.55), col ? 1 : -1);
        photoFrame(ctx, reel.photos.get(person.id), person.name, -photo / 2, -photo / 2, photo, tone, T.mix(tone, pal.paper, 0.82), tone, 1, reel.look?.frame);
        ctx.restore();
        ctx.save();
        ctx.globalAlpha *= ease(span(t, 0.3 + i * 0.12, 0.4));
        ctx.fillStyle = pal.ink;
        ctx.textAlign = 'center';
        const fit = fits[i];
        ctx.font = `800 ${fit.size}px ${head}`;
        fit.lines.forEach((l, k) => ctx.fillText(l, cx, y + photo + 28 + (k + 1) * fit.size * 1.1, cellW - 20));
        ctx.restore();
      });
      ctx.restore();
    },
  };
}

/** What CampBuddy gives an attendee, one line after another (the same four lines as the "Get CampBuddy" post). */
function featuresScene(features) {
  return {
    beats: 10,
    stretch: true,
    draw(ctx, t, dur, reel) {
      const { pal, kit } = reel;
      const { head, text } = T.fonts();
      ctx.save();
      header(ctx, reel, t, 'Free · no sign-up');
      leaving(ctx, t, dur);
      const title = fitAll(ctx, `Your ${kit.short ?? kit.event.name} companion`, TEXT_W, 104, 60, 3, 800, head);
      const rowH = 190;
      // The title and the four lines as one block, in the middle of the safe zone under the header.
      const block = title.lines.length * title.size * 1.12 + 80 + features.length * rowH;
      let y = risingLines(ctx, title, CONTENT_Y - 60 + Math.max(0, (SAFE.bottom - CONTENT_Y + 60 - block) / 2), pal.ink, t, 0.1, { family: head });
      y += 80;
      features.forEach((line, i) => {
        const p = back(span(t, 0.5 + i * 0.28, 0.5));
        const top = y + i * rowH;
        ctx.save();
        ctx.globalAlpha *= clamp(span(t, 0.5 + i * 0.28, 0.25));
        ctx.translate((1 - p) * -120, 0);
        // A card per line: a tick in the main colour, the words beside it.
        ctx.shadowColor = 'rgba(0,0,0,0.08)';
        ctx.shadowBlur = 24;
        ctx.fillStyle = '#ffffff';
        T.roundRect(ctx, SAFE.left, top, SAFE.right - SAFE.left, rowH - 38, 34);
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.fillStyle = pal.primary;
        ctx.beginPath();
        ctx.arc(162, top + (rowH - 38) / 2, 40, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = pal.onPrimary;
        ctx.lineWidth = 8;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        const tick = clamp(span(t, 0.75 + i * 0.28, 0.35));
        ctx.beginPath();
        const mid = top + (rowH - 38) / 2;
        ctx.moveTo(144, mid);
        ctx.lineTo(144 + 13 * Math.min(1, tick * 2), mid + 14 * Math.min(1, tick * 2));
        if (tick > 0.5) ctx.lineTo(157 + 26 * (tick - 0.5) * 2, mid + 14 - 30 * (tick - 0.5) * 2);
        ctx.stroke();
        const fit = fitAll(ctx, line, SAFE.right - 40 - 232, 50, 34, 2, 700, text);
        ctx.fillStyle = pal.ink;
        ctx.font = `700 ${fit.size}px ${text}`;
        const lines = fit.lines.length * fit.size * 1.2;
        fit.lines.forEach((l, k) => ctx.fillText(l, 232, top + (rowH - 38 - lines) / 2 + (k + 0.8) * fit.size * 1.2));
        ctx.restore();
      });
      ctx.restore();
    },
  };
}

/** How to start, in three steps (the QR on the end card is step one). */
function howScene() {
  return {
    beats: 9,
    stretch: true,
    draw(ctx, t, dur, reel) {
      const { pal, kit } = reel;
      const { head, text } = T.fonts();
      ctx.save();
      header(ctx, reel, t, '3 steps');
      leaving(ctx, t, dur);
      const title = fitAll(ctx, 'Ready in a minute', TEXT_W, 112, 60, 2, 800, head);
      // Up to 250 a step, less when the title, the steps and the pill would run past the safe box.
      const titleH = title.lines.length * title.size * 1.12;
      const stepH = Math.min(250, Math.floor((SAFE.bottom - CONTENT_Y + 60 - titleH - 90 - 120) / 3));
      const block = title.lines.length * title.size * 1.12 + 90 + 3 * stepH + 120;
      let y = risingLines(ctx, title, CONTENT_Y - 60 + Math.max(0, (SAFE.bottom - CONTENT_Y + 60 - block) / 2), pal.ink, t, 0.1, { family: head });
      const steps = [
        'Scan the QR, or open campbuddy.club',
        `Pick ${kit.short ?? kit.event.name}`,
        'Save your talks and find people who share your interests',
      ];
      y += 90;
      steps.forEach((step, i) => {
        const start = 0.45 + i * 0.35;
        const p = back(span(t, start, 0.5));
        const top = y + i * stepH;
        ctx.save();
        ctx.globalAlpha *= clamp(span(t, start, 0.2));
        // The number in a circle that pops; a line joins it to the next one.
        if (i < steps.length - 1) {
          ctx.strokeStyle = T.mix(pal.primary, pal.paper, 0.6);
          ctx.lineWidth = 6;
          ctx.setLineDash([2, 16]);
          ctx.lineCap = 'round';
          ctx.beginPath();
          ctx.moveTo(170, top + 160);
          ctx.lineTo(170, top + 160 + (stepH - 170) * ease(span(t, start + 0.3, 0.4)));
          ctx.stroke();
          ctx.setLineDash([]);
        }
        ctx.save();
        ctx.translate(170, top + 80);
        ctx.scale(0.5 + 0.5 * p, 0.5 + 0.5 * p);
        ctx.fillStyle = i === 2 ? pal.secondary : pal.primary;
        ctx.beginPath();
        ctx.arc(0, 0, 76, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = i === 2 ? pal.onSecondary : pal.onPrimary;
        ctx.font = `900 74px ${head}`;
        ctx.textAlign = 'center';
        ctx.fillText(String(i + 1), 0, 26);
        ctx.restore();
        const fit = fitAll(ctx, step, SAFE.right - 290, 54, 34, 3, 700, text);
        ctx.fillStyle = pal.ink;
        ctx.font = `700 ${fit.size}px ${text}`;
        const lines = fit.lines.length * fit.size * 1.2;
        fit.lines.forEach((l, k) => ctx.fillText(l, 290 + (1 - p) * 60, top + 80 - lines / 2 + (k + 0.8) * fit.size * 1.2));
        ctx.restore();
      });
      // "Free · no sign-up · works offline", last.
      pillAt(ctx, 'Free · no sign-up · works offline', W / 2, y + 3 * stepH + 20, pal.secondary, pal.onSecondary, 40, ease(span(t, 1.6, 0.4)));
      ctx.restore();
    },
  };
}

// ---- Transitions between scenes ------------------------------------------------

const TRANSITION = 0.36;
const TRANSITIONS = ['wipe', 'iris', 'diagonal', 'shutter', 'split', 'stack'];

/**
 * Over every cut: a panel in the event's colours covers the frame and opens
 * on the next scene (the cut itself happens under it). The kind turns with
 * each cut, starting from one picked by the event.
 */
function transition(ctx, reel, time) {
  const cut = reel.scenes.find((s, i) => i > 0 && Math.abs(time - s.start) < TRANSITION);
  if (!cut) return;
  const index = reel.scenes.indexOf(cut);
  // Each reel walks the kinds in its own order (a step coprime to six, from its own start).
  const look = reel.look ?? { transitionStart: reel.kit.event.seed, transitionStep: 1 };
  const kind = TRANSITIONS[(index * look.transitionStep + look.transitionStart) % TRANSITIONS.length];
  const p = (time - (cut.start - TRANSITION)) / (2 * TRANSITION); // 0 → 1 across the cut
  const cover = p < 0.5 ? ease(p * 2) : 1 - ease((p - 0.5) * 2); // in, then out
  const { pal } = reel;
  const colors = [pal.primary, pal.secondary, T.mix(pal.primary, pal.ink, 0.35)];
  ctx.save();
  if (kind === 'wipe') {
    // Three bands, one after another, from the left in and out to the right.
    colors.forEach((c, i) => {
      const lag = i * 0.12;
      const q = clamp((p - lag * 0.5) / (1 - lag));
      const a = q < 0.5 ? ease(q * 2) : 1;
      const b = q < 0.5 ? 0 : ease((q - 0.5) * 2);
      ctx.fillStyle = c;
      ctx.fillRect(W * b, 0, W * (a - b), H);
    });
  } else if (kind === 'iris') {
    ctx.fillStyle = pal.primary;
    ctx.beginPath();
    ctx.arc(W / 2, H / 2, Math.hypot(W, H) * 0.55 * cover, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = pal.secondary;
    ctx.lineWidth = 26;
    ctx.stroke();
  } else if (kind === 'diagonal') {
    ctx.translate(W / 2, H / 2);
    ctx.rotate(-0.35);
    const w = (Math.hypot(W, H) + 200) * cover;
    ctx.fillStyle = pal.secondary;
    ctx.fillRect(-w / 2 - 40, -H, w + 80, H * 2);
    ctx.fillStyle = pal.primary;
    ctx.fillRect(-w / 2, -H, w, H * 2);
  } else if (kind === 'split') {
    // Two halves slide in from the sides, meet in the middle, part again.
    const w = (W / 2) * cover;
    ctx.fillStyle = pal.primary;
    ctx.fillRect(0, 0, w, H);
    ctx.fillStyle = pal.secondary;
    ctx.fillRect(W - w, 0, w, H);
    ctx.fillStyle = T.mix(pal.primary, pal.ink, 0.35);
    ctx.fillRect(Math.max(0, w - 14), 0, 14 * cover, H);
  } else if (kind === 'stack') {
    // Three panels rise from the foot one after another, then lift away.
    colors.forEach((c, i) => {
      const lag = i * 0.1;
      const q = clamp((p - lag * 0.5) / (1 - lag));
      const top = q < 0.5 ? H * (1 - ease(q * 2)) : 0;
      const bottom = q < 0.5 ? H : H * (1 - ease((q - 0.5) * 2));
      ctx.fillStyle = c;
      ctx.fillRect(0, top, W, bottom - top);
    });
  } else {
    // Shutters: horizontal slats closing and opening.
    const slats = 8;
    const h = H / slats;
    ctx.fillStyle = pal.primary;
    for (let i = 0; i < slats; i++) {
      const q = clamp(cover * 1.25 - (i % 2) * 0.15);
      ctx.fillRect(0, i * h + (h * (1 - q)) / 2, W, h * q);
    }
  }
  // The event's logo rides the middle of the panel.
  if (reel.logo && cover > 0.6) {
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalAlpha = clamp((cover - 0.6) / 0.4);
    const size = 220;
    ctx.fillStyle = '#ffffff';
    T.roundRect(ctx, (W - size) / 2, (H - size) / 2, size, size, 36);
    ctx.fill();
    T.drawContain(ctx, reel.logo, (W - size) / 2 + 22, (H - size) / 2 + 22, size - 44, size - 44);
  }
  ctx.restore();
}

/** Small shapes in the event's colours, drifting up across every frame (behind the words). */
function particles(ctx, reel, time) {
  const kind = reel.look?.particles ?? 'sparks';
  if (kind !== 'sparks') return particleShapes(ctx, reel, time, kind);
  ctx.save();
  reel.sparks.forEach((s) => {
    const y = ((s.y - time * s.v * 60) % (H + 120) + H + 120) % (H + 120) - 60;
    const x = s.x + Math.sin(time * s.s + s.p) * 30;
    ctx.globalAlpha = s.a;
    ctx.fillStyle = s.c;
    ctx.strokeStyle = s.c;
    ctx.lineWidth = 4;
    ctx.beginPath();
    if (s.ring) {
      ctx.arc(x, y, s.r, 0, Math.PI * 2);
      ctx.stroke();
    } else {
      ctx.arc(x, y, s.r * 0.45, 0, Math.PI * 2);
      ctx.fill();
    }
  });
  ctx.restore();
}

/** The other particle kinds: confetti falling, stars twinkling, soft bokeh circling. */
function particleShapes(ctx, reel, time, kind) {
  ctx.save();
  reel.sparks.forEach((s, i) => {
    ctx.fillStyle = s.c;
    if (kind === 'confetti') {
      const y = ((s.y + time * s.v * 90) % (H + 120) + H + 120) % (H + 120) - 60;
      const x = s.x + Math.sin(time * s.s + s.p) * 50;
      ctx.save();
      ctx.globalAlpha = s.a + 0.08;
      ctx.translate(x, y);
      ctx.rotate(time * s.s * 2 + s.p);
      ctx.scale(1, Math.cos(time * 3 * s.s + s.p));
      ctx.fillRect(-s.r * 0.5, -s.r * 0.25, s.r, s.r * 0.5);
      ctx.restore();
    } else if (kind === 'twinkle') {
      const glow = 0.5 + 0.5 * Math.sin(time * (1.5 + s.s * 2) + s.p);
      const r = s.r * (0.5 + 0.5 * glow);
      ctx.globalAlpha = (s.a + 0.1) * glow;
      ctx.beginPath();
      // A four-point star.
      for (let k = 0; k < 8; k++) {
        const a = (k * Math.PI) / 4 + time * 0.3;
        const d = k % 2 ? r * 0.28 : r;
        ctx.lineTo(s.x + Math.cos(a) * d, s.y + Math.sin(a) * d);
      }
      ctx.closePath();
      ctx.fill();
    } else {
      // bokeh: large soft circles, each on its own slow orbit.
      const a = time * 0.25 * s.s + s.p;
      ctx.globalAlpha = s.a * 0.6;
      ctx.beginPath();
      ctx.arc(s.x + Math.cos(a) * 60, s.y + Math.sin(a) * 90, s.r * 2.6 + (i % 4) * 10, 0, Math.PI * 2);
      ctx.fill();
    }
  });
  ctx.restore();
}

function endScene(content) {
  return {
    beats: 10,
    draw(ctx, t, dur, reel) {
      const { pal, kit } = reel;
      const { head, text } = T.fonts();
      // The main colour washes over the frame.
      const wash = ease(span(t, 0, 0.45));
      ctx.save();
      // Daylight's Gradient base: the main colour to its deep tone at 135°.
      const grad = ctx.createLinearGradient(0, 0, W, H);
      grad.addColorStop(0, pal.primary);
      grad.addColorStop(1, T.mix(pal.primary, pal.ink, 0.45));
      ctx.fillStyle = reel.daylight === false ? pal.primary : grad;
      ctx.beginPath();
      ctx.arc(W / 2, H / 2, Math.hypot(W, H) * 0.6 * wash, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();

      const on = pal.onPrimary;
      ctx.save();
      if (reel.campbuddy && reel.daylight === false) {
        const p = ease(span(t, 0.25, 0.4));
        const h = 80;
        const w = (reel.campbuddy.width / reel.campbuddy.height) * h;
        ctx.globalAlpha = p;
        ctx.fillStyle = '#ffffff';
        T.roundRect(ctx, (W - w - 60) / 2, SAFE.top, w + 60, h + 40, 28);
        ctx.fill();
        ctx.drawImage(reel.campbuddy, (W - w) / 2, SAFE.top + 20, w, h);
        ctx.globalAlpha = 1;
      }
      const head1 = fitAll(ctx, 'Find it all in CampBuddy', TEXT_W, 88, 56, 2, 900, head);
      // On Daylight CampBuddy's mark moves to the brand bar under the QR, so the words start higher.
      let y = risingLines(ctx, head1, SAFE.top + (reel.daylight === false ? 140 : 30), on, t, 0.35, { weight: 900, family: head });
      const sub = fitAll(ctx, `${kit.event.name}: the schedule, people to meet and reminders. Free, no sign-up.`, TEXT_W, 40, 34, 2, 600, text);
      y = risingLines(ctx, sub, y + 10, on, t, 0.55, { weight: 600, family: text, lh: 1.3 });

      // The QR pops in, whole, on its light tile.
      const q = back(span(t, 0.6, 0.6));
      const qs = 420;
      // A ring breathes around the QR, so the eye goes to it.
      const ring = (Math.sin(t * 4) + 1) / 2;
      ctx.save();
      ctx.globalAlpha *= clamp(span(t, 1.0, 0.3)) * (0.25 + 0.35 * ring);
      ctx.strokeStyle = pal.secondary;
      ctx.lineWidth = 10;
      T.roundRect(ctx, (W - qs) / 2 - 24 - ring * 10, y + 40 - 24 - ring * 10, qs + 48 + ring * 20, qs + 48 + ring * 20, 48);
      ctx.stroke();
      ctx.restore();
      ctx.save();
      ctx.globalAlpha *= clamp(span(t, 0.6, 0.2));
      ctx.translate(W / 2, y + 40 + qs / 2);
      ctx.scale(0.5 + 0.5 * q, 0.5 + 0.5 * q);
      T.drawQr(ctx, qrLink(kit.event.qr, `reel-${content}`), -qs / 2, -qs / 2, qs, pal.ink, pal.paper);
      ctx.restore();
      y += 40 + qs + 50;
      ctx.save();
      ctx.globalAlpha *= ease(span(t, 0.9, 0.4));
      ctx.fillStyle = on;
      ctx.textAlign = 'center';
      ctx.font = `800 44px ${text}`;
      if (reel.daylight === false) {
        ctx.fillText('Scan, or open campbuddy.club', W / 2, y + 30, TEXT_W);
        const tags = (kit.event.hashtags ?? '').trim();
        if (tags) {
          ctx.font = `600 34px ${text}`;
          ctx.globalAlpha *= 0.85;
          ctx.fillText(tags, W / 2, y + 80, TEXT_W);
        }
        ctx.restore();
      } else {
        ctx.restore();
        // The brand bar, the same as on every card: CampBuddy, campbuddy.club.
        brandBar(ctx, reel, y, ease(span(t, 0.9, 0.4)));
      }

      // WPSimplified's credit.
      const c = ease(span(t, 1.2, 0.5));
      if (c > 0) {
        ctx.save();
        ctx.globalAlpha = c;
        const bw = TEXT_W;
        const bh = 170;
        const bx = (W - bw) / 2;
        const by = Math.min(SAFE.bottom - bh, y + (reel.daylight === false ? 110 : 132)) + (1 - c) * 30;
        ctx.fillStyle = '#ffffff';
        T.roundRect(ctx, bx, by, bw, bh, 34);
        ctx.fill();
        ctx.fillStyle = '#3a3a3a';
        ctx.textAlign = 'center';
        ctx.font = `600 34px ${text}`;
        ctx.fillText('Made with ♥ for the WordPress community by', W / 2, by + 52, bw - 48);
        if (reel.wps) {
          const lh = 78;
          const lw = (reel.wps.width / reel.wps.height) * lh;
          ctx.drawImage(reel.wps, (W - lw) / 2, by + 74, lw, lh);
        }
        ctx.restore();
      }
      ctx.restore();
    },
  };
}

// ---- The reels on offer ------------------------------------------------------

/** Every reel this event can have, with what it needs to be made. */
export function reelList(kit, posts) {
  const list = posts.map((post) => ({
    key: `post-${post.key}`,
    title: post.label,
    mood: POST_MOODS[post.key] ?? 'bright',
    caption: post.caption,
    build: (style, colors) => {
      const pages = planPost(post.content(), '1080x1920', style, colors);
      return { kind: 'post', pages, label: post.label, utm: post.key };
    },
  }));

  for (const [role, label, mood] of GROUPS) {
    const people = kit.people.filter((p) => (p.roles ?? []).includes(role));
    if (!people.length) continue;
    const chunks = role === 'speaker' ? chunk(people, SPEAKERS_PER_REEL) : [people];
    chunks.forEach((group, i) => {
      const first = role === 'speaker' ? i * SPEAKERS_PER_REEL : 0;
      const title = chunks.length > 1 ? `${label} ${first + 1}–${first + group.length}` : label;
      list.push({
        key: `${role.replace('_', '-')}${chunks.length > 1 ? `-${i + 1}` : ''}`,
        title: `${title} (${group.length})`,
        mood,
        caption: () => groupCaption(role, kit, group),
        build: () => ({ kind: 'people', role, label, people: group, first, total: role === 'speaker' ? people.length : group.length, utm: `people-${role.replace('_', '-')}` }),
      });
    });
  }
  list.forEach((item, i) => { item.variant = i; });
  return list;
}

const BACKDROPS = ['pattern', 'aurora', 'stripes', 'rings', 'dotwave'];
const PARTICLES = ['sparks', 'confetti', 'twinkle', 'bokeh'];
const MOTIONS = ['pop', 'slide', 'flip', 'zoom'];
const FRAMES = ['rounded', 'soft', 'ticket'];

/**
 * A reel's own look and sound, from its place in the event's list (and the
 * event's seed, so two events differ too). Each part turns at its own rate,
 * so neighbouring reels never share a background, particles, motion or key,
 * and no two of the first sixty share all of them.
 */
export function lookFor(variant, seed = 0) {
  const v = variant + ((Number(seed) >>> 0) % 60);
  return {
    backdrop: BACKDROPS[v % BACKDROPS.length],
    particles: PARTICLES[v % PARTICLES.length],
    motion: MOTIONS[(v + 1) % MOTIONS.length],
    frame: FRAMES[v % FRAMES.length],
    intro: ['center', 'left', 'band'][(v + 1) % 3],
    mat: ['none', 'tilt', 'stack'][v % 3],
    mirror: v % 2 === 1,
    side: v % 2 ? -1 : 1,
    transitionStart: v % 6,
    transitionStep: v % 2 ? 5 : 1,
    music: {
      key: 50 + ((v * 5) % 12),
      tempo: [0, 6, -6, 10, -4, 3][v % 6],
      progression: v % PROGRESSIONS.length,
      arp: ['up', 'down', 'updown', 'skip'][v % 4],
      lead: ['triangle', 'square', 'sine', 'saw'][(v + 2) % 4],
      groove: ['straight', 'offbeat', 'shuffle', 'break'][(v + 1) % 4],
      pad: v % 3 === 2 ? 'soft' : 'saw',
    },
  };
}

function chunk(list, n) {
  const out = [];
  for (let i = 0; i < list.length; i += n) out.push(list.slice(i, i + n));
  return out;
}

const GROUP_WORDS = {
  organizer: (short) => ['Meet the organizers', `The people making ${short} happen`],
  speaker: (short) => ['Meet the speakers', `Save their talks in CampBuddy and get a reminder before each one at ${short}`],
  volunteer: (short) => ['Our volunteers', `Say thanks when you see them at ${short}`],
  media_partner: (short) => ['Media partners', `Follow their coverage of ${short}`],
  table_lead: () => ['Contributor Day table leads', 'Join a table and give back to WordPress'],
};

function groupCaption(role, kit, group) {
  const short = kit.short ?? kit.event.name;
  const names = group.map((p) => p.name).join(', ');
  const [title, line] = GROUP_WORDS[role](short);
  return `${title} of ${kit.event.name}: ${names}.\n\n${line}. Open ${kit.event.name} in CampBuddy, free and without a sign-up.\n\n${kit.event.hashtags ?? ''} #CampBuddy #WPSimplified`.trim();
}

/** The scenes of one reel, its music's mood and its pictures, ready to draw. */
async function prepare(item, kit, colors, style) {
  const spec = item.build(style, colors);
  const pal = T.paletteOf(colors);
  const reel = { item, kit, colors, pal, scenes: [], photos: new Map(), art: {}, dots: [], look: lookFor(item.variant ?? 0, kit.event.seed), daylight: !style || style === 'daylight' };

  const [logo, campbuddy, wps] = await Promise.all([
    T.loadImage(kit.event.logo || kit.event.mark),
    T.loadImage('/media/logo-wordmark.png'),
    T.loadImage('/media/wpsimplified-logo.png'),
  ]);
  Object.assign(reel, { logo, campbuddy, wps });

  // The site's artwork: its pattern as a drifting tile, its skyline at the foot (both in a brand tone, so the words read).
  const site = T.siteArt();
  const pattern = site.pattern[0];
  if (pattern) {
    const tinted = T.recolour(pattern, pal.primary);
    if (tinted) {
      const k = 180 / tinted.height;
      reel.art.patternTile = canvas(Math.max(1, Math.round(tinted.width * k)), 180);
      reel.art.patternTile.getContext('2d').drawImage(tinted, 0, 0, reel.art.patternTile.width, reel.art.patternTile.height);
    }
  }
  const skyline = site.illustration.find((img) => T.artInfo(img).aspect >= 1.4) ?? site.illustration[0];
  if (skyline) {
    const info = T.artInfo(skyline);
    reel.art.skyline = info.mono || info.opaque ? T.recolour(skyline, pal.primary) : skyline;
  }
  // The reel's own scatter of shapes: same event, different per reel.
  const rnd = T.seeded((kit.event.seed >>> 0) + (item.variant ?? 0) * 7919);
  const tones = [pal.primary, pal.secondary, ...(pal.extras ?? [])];
  reel.sparks = Array.from({ length: 18 }, (_, i) => ({ x: rnd() * W, y: rnd() * H, r: 10 + rnd() * 26, a: 0.1 + rnd() * 0.12, c: tones[i % tones.length], s: 0.4 + rnd(), p: rnd() * 6, v: 0.3 + rnd() * 0.8, ring: i % 3 === 0 }));
  for (let i = 0; i < 14; i++) {
    reel.dots.push({ x: rnd() * W, y: rnd() * H, r: 20 + rnd() * 90, a: 0.05 + rnd() * 0.06, c: [pal.primary, pal.secondary, ...(pal.extras ?? [])][i % 3], s: 0.3 + rnd() * 0.6, p: rnd() * 6, v: 0.3 + rnd() });
  }

  if (spec.kind === 'post') {
    reel.scenes.push(introScene(spec.label));
    for (const [i, page] of spec.pages.entries()) {
      const c = canvas();
      await drawEventPost(c, '1080x1920', style, page, kit, colors, { index: i + 1, total: spec.pages.length });
      reel.scenes.push(cardScene(c, i, spec.pages.length, i === spec.pages.length - 1));
    }
  } else {
    const short = kit.short ?? kit.event.name;
    const [title, line] = GROUP_WORDS[spec.role](short);
    const count = spec.role === 'speaker' && spec.total > spec.people.length
      ? `Speakers ${spec.first + 1}–${spec.first + spec.people.length} of ${spec.total}`
      : `${spec.people.length} ${spec.people.length === 1 ? spec.label.replace(/s$/, '').toLowerCase() : spec.label.toLowerCase()}`;
    reel.scenes.push(introScene(spec.label));
    reel.scenes.push(groupIntroScene(spec.role, title, line, count));
    const photos = await Promise.all(spec.people.map((p) => T.loadImage(T.proxied(kit, p.photo))));
    spec.people.forEach((p, i) => reel.photos.set(p.id, photos[i]));
    if (spec.role === 'speaker' || spec.people.length <= ONE_BY_ONE) {
      spec.people.forEach((p, i) => reel.scenes.push(personScene(p, spec.first + i, spec.total, spec.role === 'speaker', spec.role)));
    } else {
      chunk(spec.people, GRID).forEach((group, i) => reel.scenes.push(gridScene(group, i * GRID, spec.people.length, spec.role)));
    }
  }
  // What CampBuddy gives them and how to start, before the QR: posts get both, people reels the steps.
  if (spec.kind === 'post') reel.scenes.push(featuresScene(T.features));
  reel.scenes.push(howScene());
  reel.scenes.push(endScene(spec.utm));

  // Every scene lasts whole beats, so the cuts fall on the music.
  const mood = MOODS[item.mood] ?? MOODS.bright;
  reel.bpm = reelBpm(mood, reel.look);
  const beat = 60 / reel.bpm;
  // At least MIN_SECONDS: a short reel holds its cards and people a little longer, a beat at a time, in turn.
  const stretchable = reel.scenes.filter((s) => s.stretch);
  let beats = reel.scenes.reduce((n, s) => n + s.beats, 0);
  for (let i = 0; beats * beat < MIN_SECONDS && stretchable.length; i++, beats++) stretchable[i % stretchable.length].beats++;
  let at = 0;
  reel.scenes.forEach((s) => {
    s.start = at;
    s.dur = s.beats * beat;
    at += s.dur;
  });
  reel.duration = at;
  reel.mood = item.mood;
  return reel;
}

function drawFrame(ctx, reel, time) {
  ctx.setTransform(1, 0, 0, 1, 0, 0);
  ctx.globalAlpha = 1;
  ctx.textBaseline = 'alphabetic';
  backdrop(ctx, time, reel);
  particles(ctx, reel, time);
  const scene = reel.scenes.findLast((s) => s.start <= time) ?? reel.scenes[0];
  ctx.save();
  scene.draw(ctx, time - scene.start, scene.dur, reel);
  ctx.restore();
  transition(ctx, reel, time);
}

// ---- Music -------------------------------------------------------------------

const MOODS = {
  bright: { bpm: 112, chords: [[0, 'maj'], [7, 'maj'], [9, 'min'], [5, 'maj']], kick: 'half', snare: true, hats: 8 },
  drive: { bpm: 120, chords: [[9, 'min'], [5, 'maj'], [0, 'maj'], [7, 'maj']], kick: 'four', snare: true, hats: 8 },
  warm: { bpm: 98, chords: [[0, 'maj'], [9, 'min'], [5, 'maj'], [7, 'maj']], kick: 'half', snare: true, hats: 4 },
  soft: { bpm: 84, chords: [[0, 'maj'], [4, 'min'], [5, 'maj'], [7, 'maj']], kick: 'one', snare: false, hats: 0 },
};

// Chord progressions a reel can be built on (steps from the key, chord kind).
const PROGRESSIONS = [
  [[0, 'maj'], [7, 'maj'], [9, 'min'], [5, 'maj']],
  [[9, 'min'], [5, 'maj'], [0, 'maj'], [7, 'maj']],
  [[0, 'maj'], [9, 'min'], [5, 'maj'], [7, 'maj']],
  [[0, 'maj'], [4, 'min'], [5, 'maj'], [7, 'maj']],
  [[0, 'maj'], [5, 'maj'], [9, 'min'], [7, 'maj']],
  [[9, 'min'], [7, 'maj'], [5, 'maj'], [7, 'maj']],
  [[0, 'maj'], [2, 'min'], [5, 'maj'], [7, 'maj']],
  [[5, 'maj'], [7, 'maj'], [4, 'min'], [9, 'min']],
];

/** The reel's tempo: its mood's, nudged by its look, kept within 80–130. */
function reelBpm(mood, look) {
  return Math.min(130, Math.max(80, mood.bpm + (look?.music?.tempo ?? 0)));
}

function hz(midi) {
  return 440 * 2 ** ((midi - 69) / 12);
}

function chordNotes(root, kind) {
  return [root, root + (kind === 'min' ? 3 : 4), root + 7];
}

/**
 * The reel's own music: pad, bass, a plucked arpeggio and drums in the reel's
 * mood, in a key picked from the event, with a whoosh into every cut and a
 * soft hit on the end card. Drums come in after the intro and drop for the
 * end card; it fades out on the last beat.
 */
async function makeMusic(reel) {
  const mood = MOODS[reel.mood] ?? MOODS.bright;
  const sound = reel.look?.music ?? lookFor(0, reel.kit.event.seed).music;
  const chords = PROGRESSIONS[sound.progression] ?? mood.chords;
  const beat = 60 / (reel.bpm ?? reelBpm(mood, reel.look));
  const length = Math.ceil(reel.duration * RATE);
  const ctx = new OfflineAudioContext(2, length, RATE);
  const master = ctx.createGain();
  master.gain.setValueAtTime(0, 0);
  master.gain.linearRampToValueAtTime(0.9, 0.3);
  master.gain.setValueAtTime(0.9, Math.max(0.3, reel.duration - 1.2));
  master.gain.linearRampToValueAtTime(0, reel.duration);
  const comp = ctx.createDynamicsCompressor();
  comp.threshold.value = -16;
  comp.ratio.value = 4;
  master.connect(comp).connect(ctx.destination);

  const noise = ctx.createBuffer(1, RATE * 2, RATE);
  const nd = noise.getChannelData(0);
  const rnd = T.seeded((reel.kit.event.seed >>> 0) + (reel.item?.variant ?? 0) * 7919);
  for (let i = 0; i < nd.length; i++) nd[i] = rnd() * 2 - 1;

  const key = sound.key - 12;
  const intro = reel.scenes[0].dur;
  const end = reel.scenes.at(-1).start;
  const beats = Math.ceil(reel.duration / beat);

  const tone = (type, freq, at, dur, level, { attack = 0.01, cutoff = 0, detune = 0, dest = master } = {}) => {
    const o = ctx.createOscillator();
    o.type = type;
    o.frequency.value = freq;
    o.detune.value = detune;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0, at);
    g.gain.linearRampToValueAtTime(level, at + attack);
    g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
    let node = o;
    if (cutoff) {
      const f = ctx.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = cutoff;
      o.connect(f);
      node = f;
    }
    node.connect(g).connect(dest);
    o.start(at);
    o.stop(at + dur + 0.05);
  };
  const hit = (at, dur, level, type, freq, q = 1) => {
    const s = ctx.createBufferSource();
    s.buffer = noise;
    const f = ctx.createBiquadFilter();
    f.type = type;
    f.frequency.value = freq;
    f.Q.value = q;
    const g = ctx.createGain();
    g.gain.setValueAtTime(level, at);
    g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
    s.connect(f).connect(g).connect(master);
    s.start(at, rnd());
    s.stop(at + dur + 0.05);
  };
  const kick = (at, level = 0.9) => {
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.frequency.setValueAtTime(150, at);
    o.frequency.exponentialRampToValueAtTime(45, at + 0.13);
    g.gain.setValueAtTime(level, at);
    g.gain.exponentialRampToValueAtTime(0.0001, at + 0.32);
    o.connect(g).connect(master);
    o.start(at);
    o.stop(at + 0.35);
  };

  for (let b = 0; b < beats; b++) {
    const at = b * beat;
    if (at >= reel.duration) break;
    const bar = Math.floor(b / 4);
    const inBar = b % 4;
    const [step, kind] = chords[bar % chords.length];
    const notes = chordNotes(key + 12 + step, kind);
    const playing = at >= intro - 0.01 && at < end - 0.01;
    // A shuffle plays the second eighth late.
    const half = sound.groove === 'shuffle' ? beat * 0.62 : beat / 2;

    // Pad: the chord, held for the bar (a buzzy pair of saws, or a soft sine pad).
    if (inBar === 0) {
      notes.forEach((n) => {
        if (sound.pad === 'soft') {
          tone('sine', hz(n), at, beat * 4.2, 0.05, { attack: 0.6 });
          tone('triangle', hz(n + 12), at, beat * 4.2, 0.012, { attack: 0.8, cutoff: 2000 });
        } else {
          tone('sawtooth', hz(n), at, beat * 4.2, 0.022, { attack: 0.35, cutoff: 1300, detune: -7 });
          tone('sawtooth', hz(n), at, beat * 4.2, 0.022, { attack: 0.35, cutoff: 1300, detune: 7 });
        }
      });
    }
    // Bass on the beat (on every beat when it drives), from the drop on.
    if (playing && (mood.kick === 'four' || inBar % 2 === 0)) tone('triangle', hz(key + step - 12 + 12), at, beat * 0.9, 0.2, { cutoff: 500 });
    // The arpeggio in eighths, in the reel's own pattern and sound.
    for (let e = 0; e < 2; e++) {
      const i = inBar * 2 + e;
      const order = { up: i % 3, down: 2 - (i % 3), updown: [0, 1, 2, 1][i % 4], skip: [0, 2, 1, 2][i % 4] }[sound.arp] ?? i % 3;
      const n = notes[order] + 12 + (e && inBar === 3 ? 12 : 0);
      const buzzy = sound.lead === 'square' || sound.lead === 'saw';
      const level = (at < intro ? 0.05 : 0.065) * (buzzy ? 0.45 : sound.lead === 'sine' ? 1.3 : 1);
      tone(sound.lead === 'saw' ? 'sawtooth' : sound.lead, hz(n), at + e * half, 0.28, level, buzzy ? { cutoff: 2400 } : {});
    }
    if (playing) {
      if (mood.kick === 'four' || (mood.kick === 'half' && inBar % 2 === 0) || (mood.kick === 'one' && inBar === 0)) kick(at, mood.kick === 'one' ? 0.5 : 0.85);
      // A broken beat adds a kick on the "and" of two.
      if (sound.groove === 'break' && inBar === 1 && mood.kick !== 'one') kick(at + half, 0.6);
      if (mood.snare && inBar % 2 === 1) hit(at, 0.18, 0.3, 'bandpass', sound.groove === 'offbeat' ? 1400 : 1800, 0.8);
      if (sound.groove === 'offbeat') {
        // Open hats on the off-beats instead of the even run.
        if (mood.hats) hit(at + half, 0.12, 0.09, 'highpass', 6000);
      } else {
        for (let h = 0; h < mood.hats / 4; h++) hit(at + (mood.hats === 8 && h % 2 ? half : (h * beat * 4) / mood.hats), 0.05, 0.08, 'highpass', 7000);
      }
    }
  }

  // A whoosh into every cut, a hit on the end card, and the last chord ringing out.
  reel.scenes.slice(1).forEach((s) => {
    const at = Math.max(0, s.start - 0.45);
    const src = ctx.createBufferSource();
    src.buffer = noise;
    const f = ctx.createBiquadFilter();
    f.type = 'bandpass';
    f.Q.value = 1.2;
    f.frequency.setValueAtTime(400, at);
    f.frequency.exponentialRampToValueAtTime(4500, at + 0.45);
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.exponentialRampToValueAtTime(0.14, at + 0.4);
    g.gain.exponentialRampToValueAtTime(0.0001, at + 0.55);
    src.connect(f).connect(g).connect(master);
    src.start(at);
    src.stop(at + 0.6);
  });
  kick(end, 0.8);
  hit(end, 1.4, 0.12, 'highpass', 3000);
  chordNotes(key + 12 + chords[0][0], chords[0][1]).forEach((n) => tone('sine', hz(n + 12), end, Math.max(1, reel.duration - end), 0.05, { attack: 0.05 }));

  return ctx.startRendering();
}

/** The admin's own track, looped when short, faded in and out. */
async function ownTrack(file, duration) {
  const data = await file.arrayBuffer();
  const ctx = new OfflineAudioContext(2, Math.ceil(duration * RATE), RATE);
  const buffer = await ctx.decodeAudioData(data);
  const src = ctx.createBufferSource();
  src.buffer = buffer;
  src.loop = buffer.duration < duration;
  const g = ctx.createGain();
  g.gain.setValueAtTime(0, 0);
  g.gain.linearRampToValueAtTime(1, 0.4);
  g.gain.setValueAtTime(1, Math.max(0.4, duration - 1.5));
  g.gain.linearRampToValueAtTime(0, duration);
  src.connect(g).connect(ctx.destination);
  src.start(0);
  return ctx.startRendering();
}

async function soundtrack(reel, music) {
  let buffer = null;
  if (music.mode === 'auto') buffer = await makeMusic(reel);
  else if (music.mode === 'file' && music.file) buffer = await ownTrack(music.file, reel.duration);
  if (buffer && music.volume !== 1) {
    for (let ch = 0; ch < buffer.numberOfChannels; ch++) {
      const d = buffer.getChannelData(ch);
      for (let i = 0; i < d.length; i++) d[i] *= music.volume;
    }
  }
  return buffer;
}

// ---- Making the video --------------------------------------------------------

export async function canEncode() {
  if (typeof VideoEncoder === 'undefined' || typeof AudioEncoder === 'undefined') return false;
  try {
    const v = await VideoEncoder.isConfigSupported({ codec: 'avc1.640028', width: W, height: H, bitrate: 9e6, framerate: FPS });
    const a = await AudioEncoder.isConfigSupported({ codec: 'mp4a.40.2', sampleRate: RATE, numberOfChannels: 2, bitrate: 192000 });
    return Boolean(v.supported && a.supported);
  } catch {
    return false;
  }
}

/** Frame by frame into an MP4 (H.264 + AAC): faster than real time, never a dropped frame. */
async function encodeMp4(reel, audio, onProgress) {
  const c = canvas();
  const ctx = c.getContext('2d');
  let failure = null;
  const muxer = new Muxer({
    target: new ArrayBufferTarget(),
    video: { codec: 'avc', width: W, height: H, frameRate: FPS },
    audio: audio ? { codec: 'aac', numberOfChannels: 2, sampleRate: RATE } : undefined,
    fastStart: 'in-memory',
    firstTimestampBehavior: 'offset',
  });
  const video = new VideoEncoder({ output: (chunk, meta) => muxer.addVideoChunk(chunk, meta), error: (e) => { failure = e; } });
  video.configure({ codec: 'avc1.640028', width: W, height: H, bitrate: 7_000_000, framerate: FPS });

  const frames = Math.round(reel.duration * FPS);
  for (let i = 0; i < frames; i++) {
    if (failure) throw failure;
    drawFrame(ctx, reel, i / FPS);
    const frame = new VideoFrame(c, { timestamp: Math.round((i * 1e6) / FPS), duration: Math.round(1e6 / FPS) });
    video.encode(frame, { keyFrame: i % (FPS * 2) === 0 });
    frame.close();
    while (video.encodeQueueSize > 6) await new Promise((r) => setTimeout(r, 2));
    if (i % 10 === 0) {
      onProgress?.(i / frames);
      await new Promise((r) => setTimeout(r, 0));
    }
  }
  await video.flush();

  if (audio) {
    const sound = new AudioEncoder({ output: (chunk, meta) => muxer.addAudioChunk(chunk, meta), error: (e) => { failure = e; } });
    sound.configure({ codec: 'mp4a.40.2', sampleRate: RATE, numberOfChannels: 2, bitrate: 192000 });
    const left = audio.getChannelData(0);
    const right = audio.numberOfChannels > 1 ? audio.getChannelData(1) : left;
    for (let at = 0; at < audio.length; at += 1024) {
      const n = Math.min(1024, audio.length - at);
      const data = new Float32Array(n * 2);
      data.set(left.subarray(at, at + n), 0);
      data.set(right.subarray(at, at + n), n);
      sound.encode(new AudioData({ format: 'f32-planar', sampleRate: RATE, numberOfFrames: n, numberOfChannels: 2, timestamp: Math.round((at * 1e6) / RATE), data }));
    }
    await sound.flush();
  }
  if (failure) throw failure;
  muxer.finalize();
  onProgress?.(1);
  return new Blob([muxer.target.buffer], { type: 'video/mp4' });
}

/** Where WebCodecs can't: played once in real time and recorded (keep this tab in front). */
async function recordRealtime(reel, audio, onProgress) {
  const c = canvas();
  const ctx = c.getContext('2d');
  drawFrame(ctx, reel, 0);
  // Frames are handed over one by one (requestFrame), so none is skipped or doubled.
  const stream = c.captureStream(0);
  const [track] = stream.getVideoTracks();
  let ac = null;
  let src = null;
  if (audio) {
    ac = new AudioContext({ sampleRate: RATE });
    // Started from a click, so the browser lets it play; resume() in case it came up suspended.
    // (Never waits long: a browser that wants a tap first would leave resume() pending.)
    await Promise.race([ac.resume().catch(() => {}), new Promise((r) => setTimeout(r, 400))]);
    if (ac.state === 'running') {
      const dest = ac.createMediaStreamDestination();
      src = ac.createBufferSource();
      src.buffer = audio;
      src.connect(dest);
      dest.stream.getAudioTracks().forEach((t) => stream.addTrack(t));
    } else {
      // Sound not allowed here: a silent but whole video beats a broken one.
      ac.close();
      ac = null;
      reel.silent = true;
    }
  }
  const type = ['video/mp4;codecs=avc1,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm'].find((t) => MediaRecorder.isTypeSupported(t));
  const recorder = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: 7_000_000 });
  const parts = [];
  recorder.ondataavailable = (e) => parts.push(e.data);
  const done = new Promise((r) => { recorder.onstop = r; });
  recorder.start(250);
  src?.start();
  const t0 = performance.now();
  // A timer, not requestAnimationFrame: it keeps going when the page isn't painting.
  await new Promise((resolve) => {
    const tick = () => {
      const time = (performance.now() - t0) / 1000;
      if (time >= reel.duration) return resolve();
      drawFrame(ctx, reel, time);
      track.requestFrame?.();
      onProgress?.(time / reel.duration);
      setTimeout(tick, Math.max(0, 1000 / FPS - (performance.now() - t0 - time * 1000)));
    };
    tick();
  });
  recorder.stop();
  await done;
  ac?.close();
  onProgress?.(1);
  return new Blob(parts, { type: type.split(';')[0] });
}

// ---- The page ----------------------------------------------------------------

function save(blob, name) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = name;
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 60_000);
}

function fmt(seconds) {
  return `${Math.round(seconds)} s`;
}

export function initSocialReels() {
  const root = document.querySelector('[data-social-reels]');
  if (!root || !C.kit) return;
  const listEl = root.querySelector('[data-reel-list]');
  const status = root.querySelector('[data-reel-status]');
  const preview = root.querySelector('[data-reel-preview]');
  const musicEl = root.querySelector('[data-reel-music]');
  const fileEl = root.querySelector('[data-reel-file]');
  const volumeEl = root.querySelector('[data-reel-volume]');
  const items = reelList(C.kit, C.posts);
  let busy = false;
  let playing = null;

  fileEl?.closest('[data-reel-file-row]')?.classList.toggle('hidden', musicEl?.value !== 'file');
  musicEl?.addEventListener('change', () => fileEl?.closest('[data-reel-file-row]')?.classList.toggle('hidden', musicEl.value !== 'file'));
  const music = () => ({ mode: musicEl?.value ?? 'auto', file: fileEl?.files?.[0] ?? null, volume: Number(volumeEl?.value ?? 100) / 100 });
  const say = (text) => { if (status) status.textContent = text; };

  const build = async (item) => {
    await loadBrandFonts();
    await document.fonts?.ready;
    return prepare(item, C.kit, C.colors, C.style());
  };

  const stop = () => {
    if (!playing) return;
    playing.cancelled = true;
    playing.source?.stop();
    playing.ac?.close();
    playing = null;
  };

  const play = async (item) => {
    stop();
    const run = { cancelled: false };
    playing = run;
    say(`Getting “${item.title}” ready…`);
    const reel = await build(item);
    const audio = await soundtrack(reel, music());
    if (run.cancelled) return;
    const ctx = preview.getContext('2d');
    preview.width = W;
    preview.height = H;
    if (audio) {
      run.ac = new AudioContext();
      run.source = run.ac.createBufferSource();
      run.source.buffer = audio;
      run.source.connect(run.ac.destination);
      run.source.start();
    }
    const t0 = performance.now();
    say(`Playing “${item.title}” (${fmt(reel.duration)}).`);
    const tick = () => {
      if (run.cancelled) return;
      const time = (performance.now() - t0) / 1000;
      drawFrame(ctx, reel, Math.min(time, reel.duration - 1 / FPS));
      if (time < reel.duration) requestAnimationFrame(tick);
      else { say(`“${item.title}”: ${fmt(reel.duration)}. Make the MP4 to download it.`); playing = null; }
    };
    tick();
  };

  const make = async (item, onProgress) => {
    const reel = await build(item);
    const audio = await soundtrack(reel, music());
    const fast = await canEncode();
    const blob = fast ? await encodeMp4(reel, audio, onProgress) : await recordRealtime(reel, audio, onProgress);
    const ext = blob.type.includes('mp4') ? 'mp4' : 'webm';
    return { blob, silent: Boolean(reel.silent), name: `${C.kit.event.slug}-reel-${item.key}.${ext}`, caption: typeof item.caption === 'function' ? item.caption() : '' };
  };

  const guard = async (btn, work) => {
    if (busy) return;
    busy = true;
    stop();
    root.querySelectorAll('[data-reel-make], [data-reel-all]').forEach((b) => { b.disabled = true; });
    try {
      await work();
    } catch (e) {
      say(`That didn't work: ${e?.message ?? e}. Try again, or in Chrome on a computer.`);
    } finally {
      busy = false;
      root.querySelectorAll('[data-reel-make], [data-reel-all]').forEach((b) => { b.disabled = false; });
    }
  };

  items.forEach((item) => {
    const row = document.createElement('li');
    row.className = 'flex flex-wrap items-center justify-between gap-2 py-2';
    const name = document.createElement('span');
    name.className = 'text-sm font-medium';
    name.textContent = item.title;
    const buttons = document.createElement('span');
    buttons.className = 'flex flex-wrap gap-2';
    const playBtn = document.createElement('button');
    playBtn.type = 'button';
    playBtn.className = 'cb-btn cb-btn-secondary cb-btn-sm';
    playBtn.textContent = 'Preview';
    playBtn.addEventListener('click', () => play(item).catch((e) => say(`Preview failed: ${e?.message ?? e}`)));
    const makeBtn = document.createElement('button');
    makeBtn.type = 'button';
    makeBtn.className = 'cb-btn cb-btn-primary cb-btn-sm';
    makeBtn.textContent = 'Make video';
    makeBtn.dataset.reelMake = item.key;
    makeBtn.addEventListener('click', () => guard(makeBtn, async () => {
      say(`Making “${item.title}”…`);
      const out = await make(item, (p) => say(`Making “${item.title}”… ${Math.round(p * 100)} %`));
      save(out.blob, out.name);
      say(`“${item.title}” downloaded (${(out.blob.size / 1048576).toFixed(1)} MB)${out.silent ? ', without music: this browser would not play sound here' : ''}.`);
    }));
    buttons.append(playBtn, makeBtn);
    row.append(name, buttons);
    listEl.append(row);
  });

  root.querySelector('[data-reel-all]')?.addEventListener('click', (e) => guard(e.currentTarget, async () => {
    const files = [];
    const captions = [];
    for (const [i, item] of items.entries()) {
      const out = await make(item, (p) => say(`Reel ${i + 1} of ${items.length}: “${item.title}”… ${Math.round(p * 100)} %`));
      files.push({ name: out.name, data: new Uint8Array(await out.blob.arrayBuffer()) });
      captions.push(`${out.name}\n${out.caption}\n`);
    }
    files.push({ name: 'captions.txt', data: new TextEncoder().encode(captions.join('\n')) });
    save(new Blob([zipStore(files)], { type: 'application/zip' }), `${C.kit.event.slug}-reels.zip`);
    say(`All ${items.length} reels downloaded as one ZIP.`);
  }));

  root.querySelector('[data-reel-stop]')?.addEventListener('click', () => { stop(); say('Stopped.'); });
  canEncode().then((ok) => {
    if (!ok) say('This browser records reels in real time (as long as each reel): keep this tab in front. Chrome on a computer makes them faster.');
  });
}

// For tests.
export const _internals = { prepare, drawFrame, makeMusic, fitAll, MOODS, PROGRESSIONS, reelBpm, MIN_SECONDS, SAFE, TEXT_W, badgeY, context: C, tools: T, postContent };
