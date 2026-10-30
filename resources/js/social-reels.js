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
import { contrastRatio, daysToGo, drawEventPost, planPost, postContent, qrLink, reelTools as T, socialContext as C, spotlightCaption } from './social-kit.js';
import { loadBrandFonts } from './brand.js';
import { zipStore } from './zip-store.js';

const W = 1080;
const H = 1920;
const FPS = 30;
const RATE = 48000;
const SPEAKERS_PER_REEL = 5;
// No reel is shorter than this; one with more to show runs longer.
export const MIN_SECONDS = 20;
// No reel runs longer than this: a long one drops facts from its end, then takes people four at a time.
export const MAX_SECONDS = 120;
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
      header(ctx, reel, t, 'CampBuddy');
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
      const sub = fitAll(ctx, `${kit.event.name}: the schedule, people to meet and reminders.`, TEXT_W, 40, 34, 2, 600, text);
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

// ---- Reel templates (the brand's reel guideline, 22 Oct 2026) ---------------
//
// With the Daylight post design picked, every reel is built from the brand's
// reel template (CAMPBUDDY-BRAND-GUIDELINES.md §9): a two-second hook, the
// reel's own topic and nothing else (v2Scenes), one date-and-venue slide, and
// one CampBuddy end card; every cut under a "2 OF 7 · Schedule" step pill.
// Every frame carries the brand strip: the event's logo at the top,
// CampBuddy × WPSimplified at the foot.
//
// Five templates give the reels their look and feel — Daylight, Stage, Poster,
// Photo and Minimal: their grounds, how words come in, the cut between scenes,
// the effects and the music. Each reel takes its own template, the next facts
// and picks and its own music by its place in the list, so no two reels of an
// event look or sound alike; the Social media studio can change any of it.
// Every cut falls on a beat, lines come in on half beats and the highlighted
// word, the big numbers and the effects pulse on the beat.

const V2_BEATS = { hook: 4, fact: 5, end: 8 };
// Every frame but the end card: the event's logo on a white tile at the top centre of the safe
// zone, CampBuddy × WPSimplified on a white pill at its foot; the words live between the two.
const V2_LOGO_H = 104;
const V2_STRIP = 92;
const V2_TOP = SAFE.top + V2_LOGO_H + 28;
const V2_BOTTOM = SAFE.bottom - V2_STRIP - 28;

export const REEL_TEMPLATES = {
  pulse: {
    label: 'Pulse',
    ground: (kind) => kind,
    align: 'left',
    anim: 'rise',
    cut: 'wave',
    effects: { glow: true, dots: true, waves: true, motif: true, eq: false, sparks: false },
    features: { karaoke: true, markerWave: true, countUp: true, ghostReveal: true, loopEnd: true },
    music: {},
  },
  daylight: {
    label: 'Daylight',
    ground: (kind) => kind,
    align: 'left',
    anim: 'rise',
    cut: 'cut',
    effects: { glow: true, dots: true, waves: true, eq: false, sparks: false },
    music: {},
  },
  stage: {
    label: 'Stage',
    ground: (kind) => (kind === 'gradient' ? 'gradient' : 'ink'),
    align: 'center',
    anim: 'pop',
    cut: 'flash',
    effects: { glow: true, dots: true, waves: false, eq: true, sparks: true },
    mood: 'drive',
    music: { lead: 'saw', groove: 'straight', pad: 'saw' },
  },
  poster: {
    label: 'Poster',
    ground: (kind, i) => (kind === 'gradient' ? 'bold-b' : i % 2 ? 'bold-a' : 'bold-b'),
    align: 'left',
    anim: 'slide',
    cut: 'wipe',
    effects: { glow: false, dots: true, waves: false, eq: false, sparks: false, bands: true },
    mood: 'bright',
    music: { lead: 'square', groove: 'offbeat', pad: 'saw' },
  },
  photo: {
    label: 'Photo',
    ground: (kind) => (kind === 'gradient' ? 'gradient' : 'photo'),
    align: 'left',
    anim: 'rise',
    cut: 'zoom',
    effects: { glow: true, dots: false, waves: true, eq: false, sparks: true },
    mood: 'warm',
    music: { lead: 'triangle', groove: 'shuffle', pad: 'soft' },
  },
  minimal: {
    label: 'Minimal',
    ground: (kind) => (kind === 'gradient' ? 'gradient' : 'mono'),
    align: 'center',
    anim: 'type',
    cut: 'slide',
    effects: { glow: false, dots: false, waves: false, eq: false, sparks: false, grid: true },
    mood: 'warm',
    music: { lead: 'sine', groove: 'break', pad: 'soft' },
  },
};
const TEMPLATE_KEYS = Object.keys(REEL_TEMPLATES);
export const REEL_ANIMS = { rise: 'Rise', slide: 'Slide in', pop: 'Pop', type: 'Type on' };

/** A 1 → 0 pulse: strong on each beat, gone by the next. */
function onBeat(reel, t) {
  const b = reel.beat ?? 0.5;
  return Math.exp(-((((t % b) + b) % b) / b) * 6);
}

/** When the k-th element of a scene comes in: on half beats, after a short settle. */
function cue(reel, k) {
  return 0.12 + (k * (reel.beat ?? 0.5)) / 2;
}

const inkGround = (pal) => T.mix(pal.ink, '#000000', 0.15);

/** The flat colour behind the words on a ground (for contrast). */
function v2GroundColor(reel, kind) {
  const { pal } = reel;
  if (kind === 'paper' || kind === 'mono') return pal.paper;
  if (kind === 'gradient') return T.mix(pal.primary, pal.ink, 0.2);
  if (kind === 'bold-a') return pal.secondary;
  if (kind === 'bold-b') return pal.primary;
  return inkGround(pal);
}

/** The colours on a ground: words, quiet words, the highlight (always readable on it) and whether it is dark. */
function v2Ink(reel, kind = reel.ground ?? 'paper') {
  const { pal } = reel;
  const bg = v2GroundColor(reel, kind);
  const dark = T.textOn(bg, pal.ink, '#ffffff') === '#ffffff';
  if (!dark) {
    const hl = kind === 'paper' || kind === 'mono'
      ? (highlightOf(reel) ?? [pal.primary])[0]
      : T.readableOn([pal.primary, T.mix(pal.primary, pal.ink, 0.45), pal.ink], [bg]);
    return { ink: pal.ink, quiet: T.mix(pal.ink, bg, 0.3), hl, light: false, bg };
  }
  let hl = T.readableOn([pal.secondary, T.mix(pal.secondary, '#ffffff', 0.45), '#ffffff'], [bg]);
  // A tinted highlight on the main colour only where it still reads as large text (3:1); else white.
  if (hl === '#ffffff' && kind === 'bold-b' && contrastRatio(T.mix(pal.secondary, '#ffffff', 0.55), bg) >= 3) hl = T.mix(pal.secondary, '#ffffff', 0.55);
  return { ink: '#ffffff', quiet: 'rgba(255,255,255,0.82)', hl, light: true, bg };
}

function v2Ground(ctx, time, reel, kind) {
  const { pal } = reel;
  const fx = reel.fx ?? {};
  const pulse = 1 + 0.12 * onBeat(reel, time);
  const glow = (x, y, r, color, a) => {
    const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
    rg.addColorStop(0, T.alpha(color, a));
    rg.addColorStop(1, T.alpha(color, 0));
    ctx.fillStyle = rg;
    ctx.fillRect(0, 0, W, H);
  };
  if (kind === 'paper') {
    daylightGround(ctx, time, reel, reel.look?.backdrop ?? 'pattern');
  } else if (kind === 'mono') {
    ctx.fillStyle = T.mix(pal.paper, '#ffffff', 0.5);
    ctx.fillRect(0, 0, W, H);
    if (fx.grid !== false) {
      ctx.strokeStyle = T.alpha(pal.ink, 0.06);
      ctx.lineWidth = 2;
      for (let x = 60; x < W; x += 120) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
      for (let y = 60; y < H; y += 120) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
    }
  } else if (kind === 'bold-a' || kind === 'bold-b') {
    ctx.fillStyle = v2GroundColor(reel, kind);
    ctx.fillRect(0, 0, W, H);
    if (fx.bands !== false) {
      ctx.save();
      ctx.translate(W / 2, H / 2);
      ctx.rotate(-Math.PI / 6);
      const gap = 150;
      const shift = (time * 30) % (gap * 2);
      ctx.fillStyle = T.alpha(kind === 'bold-a' ? pal.primary : '#ffffff', 0.08);
      for (let x = -H - shift; x < H; x += gap * 2) ctx.fillRect(x, -H, gap, H * 2);
      ctx.restore();
    }
  } else if (kind === 'photo' && reel.art.photo) {
    const img = reel.art.photo;
    const k = Math.max(W / img.width, H / img.height) * (1.06 + 0.02 * Math.sin(time * 0.3));
    ctx.drawImage(img, (W - img.width * k) / 2, (H - img.height * k) / 2, img.width * k, img.height * k);
    const veil = ctx.createLinearGradient(0, 0, 0, H);
    veil.addColorStop(0, T.alpha(inkGround(pal), 0.72));
    veil.addColorStop(0.5, T.alpha(inkGround(pal), 0.62));
    veil.addColorStop(1, T.alpha(inkGround(pal), 0.88));
    ctx.fillStyle = veil;
    ctx.fillRect(0, 0, W, H);
  } else if (kind === 'gradient') {
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, pal.primary);
    g.addColorStop(1, T.mix(pal.primary, pal.ink, 0.45));
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
    if (fx.glow !== false) glow(W * 0.85, H * 0.12, W * 0.8 * pulse, pal.secondary, 0.28);
  } else {
    ctx.fillStyle = inkGround(pal);
    ctx.fillRect(0, 0, W, H);
    if (fx.glow !== false) {
      glow(W * 0.15 + Math.sin(time * 0.3) * 60, H * 0.32 + Math.cos(time * 0.25) * 50, W * 0.9 * pulse, pal.primary, 0.45);
      glow(W * 0.9 + Math.cos(time * 0.27) * 50, H * 0.78, W * 0.7, pal.secondary, 0.22);
    }
  }
  const dark = v2Ink(reel, kind).light;
  if (fx.dots !== false && kind !== 'paper') {
    ctx.fillStyle = dark ? 'rgba(255,255,255,0.08)' : T.alpha(pal.ink, 0.08);
    for (let y = 13; y < H; y += 26) {
      for (let x = kind === 'mono' ? W / 2 + 13 : 13; x < W; x += 26) ctx.fillRect(x - 1, y - 1, 2, 2);
    }
  }
  if (fx.waves !== false && kind !== 'paper') {
    ctx.save();
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    for (let i = 0; i < 4; i++) {
      const y0 = H * 0.86 - i * 24;
      ctx.strokeStyle = T.alpha(dark ? pal.secondary : pal.primary, 0.3 * (1 - i * 0.22));
      ctx.beginPath();
      for (let x = 0; x <= W; x += 12) {
        const y = y0 + Math.sin((x / (W / 1.4)) * Math.PI * 2 + i * 0.9 - time * 0.8) * (14 + i * 3) * pulse;
        if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      }
      ctx.stroke();
    }
    ctx.restore();
  }
}

const TOPIC_ICONS = {
  calendar: [
    'M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z',
    'M16 2v4',
    'M8 2v4',
    'M3 10h18',
  ],
  mic: [
    'M9 5a3 3 0 0 1 6 0v6a3 3 0 0 1-6 0V5z',
    'M19 10v1a7 7 0 0 1-14 0v-1',
    'M12 18v4',
    'M8 22h8',
  ],
  users: [
    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
    'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
    'M22 21v-2a4 4 0 0 0-3-3.87',
    'M16 3.13a4 4 0 0 1 0 7.75',
  ],
  handshake: [
    'm11 17 2 2a1 1 0 0 0 1.4 0l4.6-4.6a2 2 0 0 0 0-2.8l-3.2-3.2a2 2 0 0 0-2.8 0L11 11',
    'm18 11 3-3a2 2 0 0 0 0-2.8l-1.4-1.4a2 2 0 0 0-2.8 0L12 8',
    'm3 11 3-3a2 2 0 0 1 2.8 0l1.4 1.4a2 2 0 0 1 0 2.8L8 14',
    'm7 17-2 2a1 1 0 0 1-1.4 0l-1.6-1.6a2 2 0 0 1 0-2.8l3.2-3.2a2 2 0 0 1 2.8 0L11 14',
  ],
  'map-pin': [
    'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z',
    'M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
  ],
};

function topicIconFor(reel) {
  const k = (reel.item?.key || '').toLowerCase();
  const utm = (reel.item?.utm || '').toLowerCase();
  const title = (reel.item?.title || '').toLowerCase();
  if (k.includes('speaker') || k.includes('spotlight') || utm.includes('speaker') || title.includes('speaker') || title.includes('talk')) return 'mic';
  if (k.includes('countdown') || k.includes('date') || k.includes('schedule') || utm.includes('save-the-date') || title.includes('schedule') || title.includes('date')) return 'calendar';
  if (k.includes('sponsor') || k.includes('table') || utm.includes('sponsor') || title.includes('table') || title.includes('sponsor') || title.includes('contribute')) return 'handshake';
  if (k.includes('venue') || k.includes('place') || utm.includes('venue') || title.includes('venue')) return 'map-pin';
  return 'users';
}

function drawTopicMotif(ctx, time, reel) {
  const iconName = topicIconFor(reel);
  const paths = TOPIC_ICONS[iconName];
  if (!paths || typeof Path2D === 'undefined') return;
  const dark = v2Ink(reel).light;
  const { pal } = reel;
  const size = 560;
  const driftX = Math.sin(time * 0.35) * 40;
  const driftY = Math.cos(time * 0.28) * 35;
  const rot = Math.sin(time * 0.2) * 0.05;
  const cx = W / 2 + driftX;
  const cy = H * 0.44 + driftY;

  ctx.save();
  ctx.translate(cx, cy);
  ctx.rotate(rot);
  ctx.scale(size / 24, size / 24);
  ctx.translate(-12, -12);

  ctx.lineWidth = 1.4;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = dark ? T.alpha(pal.secondary, 0.08) : T.alpha(pal.primary, 0.06);

  paths.forEach((d) => {
    try {
      ctx.stroke(new Path2D(d));
    } catch {}
  });
  ctx.restore();
}

/** Effects over the ground, under the words: topic motif, an equaliser at the foot and sparks drifting up. */
function v2Effects(ctx, time, reel) {
  const fx = reel.fx ?? {};
  const { pal } = reel;
  const dark = v2Ink(reel).light;
  if (fx.motif) {
    drawTopicMotif(ctx, time, reel);
  }
  if (fx.eq) {
    const bars = 28;
    const bw = (SAFE.right - SAFE.left) / bars;
    const base = V2_BOTTOM + 8;
    const beat = onBeat(reel, time);
    ctx.save();
    ctx.fillStyle = T.alpha(dark ? pal.secondary : pal.primary, 0.32);
    for (let i = 0; i < bars; i++) {
      const wobble = 0.5 + 0.5 * Math.sin(time * (3 + (i % 5)) + i * 1.7);
      const h = 10 + (38 + 60 * beat) * wobble;
      T.roundRect(ctx, SAFE.left + i * bw + 3, base - h, bw - 6, h, 4);
      ctx.fill();
    }
    ctx.restore();
  }
  if (fx.sparks && reel.sparks) {
    ctx.save();
    const beat = onBeat(reel, time);
    reel.sparks.forEach((s) => {
      const y = ((s.y - time * s.v * 60) % (H + 120) + H + 120) % (H + 120) - 60;
      const x = s.x + Math.sin(time * s.s + s.p) * 30;
      ctx.globalAlpha = Math.min(0.5, s.a * (1.4 + beat));
      ctx.fillStyle = dark ? pal.secondary : pal.primary;
      ctx.beginPath();
      ctx.arc(x, y, s.r * 0.3 * (1 + 0.4 * beat), 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();
  }
}

/**
 * The branding on every frame but the end card: the event's logo centred at the top of the safe
 * zone, CampBuddy × WPSimplified centred at its foot (CAMPBUDDY-BRAND-GUIDELINES.md §9).
 */
function v2Brand(ctx, reel, t) {
  // Already there on frame 0 (no fade-in): the first frame is the reel's default cover and what people see
  // while it loads, so it must never be empty (spec/25 "First frame is not empty").
  ctx.save();
  const shadow = () => {
    ctx.shadowColor = 'rgba(0,0,0,0.18)';
    ctx.shadowBlur = 18;
    ctx.shadowOffsetY = 6;
  };
  // The event's logo, whole, on a white tile sized to its shape.
  if (reel.logo) {
    const h = V2_LOGO_H;
    const aspect = reel.logo.width / Math.max(1, reel.logo.height);
    const w = Math.min(SAFE.right - SAFE.left - 200, Math.max(h, (h - 24) * aspect + 32));
    const x = (W - w) / 2;
    ctx.save();
    shadow();
    ctx.fillStyle = '#ffffff';
    T.roundRect(ctx, x, SAFE.top, w, h, 24);
    ctx.fill();
    ctx.restore();
    T.drawContain(ctx, reel.logo, x + 14, SAFE.top + 12, w - 28, h - 24);
  }
  // CampBuddy × WPSimplified.
  const lock = reel.lockup;
  if (lock) {
    const ph = V2_STRIP - 12;
    const lh = ph * 0.62;
    const lw = lock.width * (lh / lock.height);
    const pw = lw + ph * 0.8;
    const y = SAFE.bottom - V2_STRIP + 6;
    ctx.save();
    shadow();
    ctx.fillStyle = '#ffffff';
    T.roundRect(ctx, (W - pw) / 2, y, pw, ph, ph / 2);
    ctx.fill();
    ctx.restore();
    ctx.drawImage(lock, (W - lw) / 2, y + (ph - lh) / 2, lw, lh);
  }
  ctx.restore();
}

/** The progress bar and the step pill ("2 OF 7 · Schedule") at the top of the safe zone; returns where content starts. */
function v2Header(ctx, reel, t, scene) {
  const { pal } = reel;
  const steps = reel.scenes.filter((s) => s.step);
  const index = steps.indexOf(scene);
  if (index < 0) return V2_TOP;
  const { text } = T.fonts();
  const light = v2Ink(reel).light;
  const w = SAFE.right - SAFE.left;
  ctx.save();
  const p = back(span(t, 0, 0.35));
  ctx.globalAlpha *= clamp(p);
  const y = V2_TOP + 6;
  const h = 64;
  // 34px: the smallest text on a reel (brand guideline §8), also in the step pill.
  ctx.font = `700 34px ${text}`;
  const label = `OF ${steps.length} · ${scene.step}`;
  const lw = Math.min(w - h - 40, ctx.measureText(label).width);
  const pw = h + lw + 30;
  const x0 = reel.align === 'center' ? (W - pw) / 2 : SAFE.left;
  ctx.translate(x0, y);
  ctx.scale(0.8 + 0.2 * p, 0.8 + 0.2 * p);
  ctx.fillStyle = light ? '#ffffff' : pal.primary;
  T.roundRect(ctx, 0, 0, pw, h, h / 2);
  ctx.fill();
  ctx.fillStyle = light ? pal.primary : '#ffffff';
  ctx.beginPath();
  ctx.arc(h / 2, h / 2, h / 2 - 9, 0, Math.PI * 2);
  ctx.fill();
  ctx.fillStyle = light ? '#ffffff' : pal.primary;
  ctx.textAlign = 'center';
  ctx.font = `800 34px ${text}`;
  ctx.fillText(String(index + 1), h / 2, h / 2 + 12);
  ctx.fillStyle = light ? pal.ink : '#ffffff';
  ctx.textAlign = 'left';
  ctx.font = `700 34px ${text}`;
  ctx.fillText(label, h + 6, h / 2 + 12, lw);
  ctx.restore();
  return y + h + 54;
}

/** How a line comes in, by the reel's animation: rise, slide in from the side, pop, or type on (a wipe). */
function v2Enter(ctx, reel, p, i, x, y, w) {
  const anim = reel.anim ?? 'rise';
  if (anim === 'slide') ctx.translate((1 - p) * (i % 2 ? 180 : -180), 0);
  else if (anim === 'pop') {
    const b = 0.6 + 0.4 * back(p);
    ctx.translate(x, y);
    ctx.scale(b, b);
    ctx.translate(-x, -y);
  } else if (anim === 'type') {
    ctx.beginPath();
    ctx.rect(x - w, y - 400, w * 2 * clamp(p * 1.15), 800);
    ctx.clip();
  } else ctx.translate(0, (1 - p) * 50);
}

/**
 * A headline whose *marked* words (else its last word) are in the highlight
 * colour; lines come in on half beats and the highlight pulses on the beat.
 */
function v2Headline(ctx, reel, words, x, y, t, k, colors, { size = 112, min = 64, lines = 3, align = 'left', width = SAFE.right - SAFE.left, isHook = false } = {}) {
  const { head } = T.fonts();
  const marked = /\*[^*]+\*/.test(words);
  const plain = words.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
  if (!plain) return y;
  const clean = (w) => w.toLowerCase().replace(/[.,!?:;]+$/, '');
  const hot = new Set((marked ? [...words.matchAll(/\*([^*]+)\*/g)].flatMap((m) => m[1].split(/\s+/)) : [plain.split(' ').at(-1)]).map(clean));
  const fit = fitAll(ctx, plain, width, size, min, lines, 900, head);
  const lh = fit.size * 1.06;
  const pulse = 1 + 0.07 * onBeat(reel, t);
  const isKaraoke = Boolean(reel.features?.karaoke && isHook);
  const hasMarkerWave = Boolean(reel.features?.markerWave);
  let wordCounter = 0;
  const beat = reel.beat ?? 0.5;

  fit.lines.forEach((line, i) => {
    const p = ease(span(t, cue(reel, k + i), 0.4));
    if (p <= 0 && !isKaraoke) return;
    ctx.save();
    if (!isKaraoke) ctx.globalAlpha *= reel.anim === 'type' ? 1 : p;
    const ly = y + (i + 0.82) * lh;
    ctx.font = `900 ${fit.size}px ${head}`;
    ctx.textAlign = 'left';
    const space = ctx.measureText(' ').width;
    const total = ctx.measureText(line).width;
    let cx = align === 'center' ? x - total / 2 : x;
    if (!isKaraoke) {
      v2Enter(ctx, reel, p, i, align === 'center' ? x : x + total / 2, ly - fit.size * 0.3, total / 2 + 20);
    }
    for (const word of line.split(' ')) {
      const ww = ctx.measureText(word).width;
      const on = hot.has(clean(word));
      const wIdx = wordCounter++;
      const wordCue = cue(reel, k) + (wIdx * beat) / 2;
      const wordLitP = isKaraoke ? ease(span(t, wordCue, 0.18)) : 1;
      const wordAlpha = isKaraoke ? (0.28 + 0.72 * wordLitP) : 1;

      ctx.save();
      ctx.globalAlpha *= wordAlpha;
      ctx.fillStyle = on ? colors.hl : colors.ink;
      if (on) {
        if (hasMarkerWave) {
          const markerP = isKaraoke ? wordLitP : ease(span(t, cue(reel, k + i), 0.3));
          if (markerP > 0) {
            ctx.save();
            ctx.fillStyle = T.alpha(colors.hl, colors.light ? 0.35 : 0.22);
            const mw = (ww + 16) * markerP;
            const mh = fit.size * 0.76;
            T.roundRect(ctx, cx - 8, ly - fit.size * 0.68, mw, mh, 8);
            ctx.fill();
            ctx.restore();

            const waveW = ww * markerP;
            if (waveW > 6) {
              ctx.save();
              ctx.beginPath();
              ctx.strokeStyle = colors.hl;
              ctx.lineWidth = Math.max(3.5, fit.size * 0.05);
              ctx.lineCap = 'round';
              ctx.lineJoin = 'round';
              const waveY = ly + 12;
              const waveAmp = Math.max(3, fit.size * 0.05);
              const waveFreq = 0.16;
              for (let wx = 0; wx <= waveW; wx += 2) {
                const wy = waveY + Math.sin(wx * waveFreq + t * 4) * waveAmp;
                if (wx === 0) ctx.moveTo(cx + wx, wy);
                else ctx.lineTo(cx + wx, wy);
              }
              ctx.stroke();
              ctx.restore();
            }
          }
        }
        ctx.save();
        ctx.translate(cx + ww / 2, ly - fit.size * 0.32);
        ctx.scale(pulse, pulse);
        ctx.fillText(word, -ww / 2, fit.size * 0.32);
        ctx.restore();
      } else ctx.fillText(word, cx, ly);
      ctx.restore();
      cx += ww + space;
    }
    ctx.restore();
  });
  return y + fit.lines.length * lh + 10;
}

function v2Line(ctx, reel, words, x, y, t, k, color, { size = 42, lines = 3, align = 'left', weight = 600, width = SAFE.right - SAFE.left, isHook = false } = {}) {
  if (!words) return y;
  const { text } = T.fonts();
  const fit = fitAll(ctx, words, width, size, 34, lines, weight, text);
  const isKaraoke = Boolean(reel.features?.karaoke && isHook);
  const p = ease(span(t, cue(reel, k), 0.4));
  if (p <= 0 && !isKaraoke) return y + fit.lines.length * fit.size * 1.3 + 10;
  ctx.save();
  ctx.globalAlpha *= isKaraoke ? (0.28 + 0.72 * p) : p;
  ctx.fillStyle = color;
  ctx.textAlign = align;
  ctx.font = `${weight} ${fit.size}px ${text}`;
  fit.lines.forEach((l, i) => ctx.fillText(l, x, y + (i + 0.85) * fit.size * 1.3 + (isKaraoke ? 0 : (1 - p) * 30)));
  ctx.restore();
  return y + fit.lines.length * fit.size * 1.3 + 10;
}

/** Numbered white rows (the guideline's list template): a mark in the main colour, the words, an optional meta line. Returns the y under the last row. */
function v2Rows(ctx, reel, rows, y, t, k, bottom = V2_BOTTOM) {
  const { pal } = reel;
  const { head, text } = T.fonts();
  const w = SAFE.right - SAFE.left;
  const gap = 20;
  const pad = 28;
  // Three rows or fewer may grow taller, so a long talk title keeps a readable size over three lines.
  const rowH = Math.max(104, Math.min(rows.length <= 3 ? 220 : 170, (bottom - y - gap * (rows.length - 1)) / Math.max(1, rows.length)));
  // A time ("10:00 AM") is set as the hour large with AM / PM small under it; any other mark (1, 2, 11) large.
  const marks = rows.map((row, i) => {
    const mark = String(row.mark ?? i + 1);
    const time = mark.match(/^(\d{1,2}[:.]\d{2})\s*([AaPp]\.?[Mm]\.?)$/);
    return time ? { main: time[1], sub: time[2].toUpperCase().replace(/\./g, '') } : { main: mark, sub: null };
  });
  // One mark column for the whole list, wide enough for its widest mark plus room on both sides, so the
  // marks line up and never touch the card's edge.
  const sizeFor = (m) => Math.round(Math.min(m.sub || m.main.length > 2 ? 52 : 72, rowH * (m.sub ? 0.4 : 0.5)));
  const widest = Math.max(...marks.map((m) => {
    ctx.font = `900 ${sizeFor(m)}px ${head}`;
    return ctx.measureText(m.main).width;
  }));
  const markW = Math.max(112, Math.min(230, widest + pad * 2));
  const ghost = Boolean(reel.features?.ghostReveal);
  rows.forEach((row, i) => {
    const p = back(span(t, cue(reel, k + i), 0.45));
    if (p <= 0 && !ghost) return;
    const isGhost = p <= 0;
    const effP = isGhost ? 0 : clamp(p);
    const ry = y + i * (rowH + gap);
    const m = marks[i];
    ctx.save();
    ctx.globalAlpha *= isGhost ? 0.22 : effP;
    if (!isGhost) {
      if (reel.anim === 'pop') {
        const s = 0.7 + 0.3 * p;
        ctx.translate(W / 2, ry + rowH / 2);
        ctx.scale(s, s);
        ctx.translate(-W / 2, -(ry + rowH / 2));
      } else ctx.translate((1 - clamp(p)) * (i % 2 && reel.anim === 'slide' ? 160 : -120), 0);
      ctx.shadowColor = 'rgba(0,0,0,0.16)';
      ctx.shadowBlur = 30;
      ctx.shadowOffsetY = 10;
    }
    ctx.fillStyle = '#ffffff';
    T.roundRect(ctx, SAFE.left, ry, w, rowH, 30);
    ctx.fill();
    ctx.shadowColor = 'transparent';
    // The mark, shrunk until it fits its column with `pad` on each side.
    let size = sizeFor(m);
    ctx.font = `900 ${size}px ${head}`;
    while (size > 24 && ctx.measureText(m.main).width > markW - pad * 2) {
      size -= 2;
      ctx.font = `900 ${size}px ${head}`;
    }
    const cx = SAFE.left + markW / 2;
    ctx.fillStyle = pal.primary;
    ctx.textAlign = 'center';
    if (m.sub) {
      const sub = Math.round(size * 0.46);
      const blockH = size * 0.9 + 8 + sub;
      const top = ry + (rowH - blockH) / 2;
      ctx.fillText(m.main, cx, top + size * 0.82);
      ctx.font = `800 ${sub}px ${text}`;
      ctx.fillStyle = T.mix(pal.primary, '#ffffff', 0.25);
      ctx.fillText(m.sub, cx, top + size * 0.9 + 8 + sub * 0.85);
    } else {
      ctx.fillText(m.main, cx, ry + rowH / 2 + size * 0.36);
    }
    // A thin rule between the mark and the words.
    ctx.fillStyle = T.alpha(pal.ink, 0.1);
    ctx.fillRect(SAFE.left + markW, ry + 20, 2, rowH - 40);
    const tx = SAFE.left + markW + 22;
    const tw = w - markW - 22 - pad;
    const title = fitAll(ctx, row.title, tw, 40, 30, row.meta && rowH < 200 ? 2 : 3, 700, text);
    // The meta line at the reel minimum (34 px) where the row is tall enough, else 30 px.
    let meta = row.meta ? fitAll(ctx, row.meta, tw, rowH >= 170 ? 34 : 30, 24, 1, 600, text) : null;
    // A long meta line (speakers' whole names) takes a second line rather than lose a name.
    if (meta && (meta.lines.length > 1 || meta.size < 24)) meta = fitAll(ctx, row.meta, tw, 26, 24, 2, 600, text);
    const metaH = meta ? meta.size * (1.4 + (meta.lines.length - 1) * 1.25) : 0;
    const blockH = title.lines.length * title.size * 1.2 + metaH;
    let ty = ry + (rowH - blockH) / 2;
    ctx.textAlign = 'left';
    ctx.fillStyle = pal.ink;
    ctx.font = `700 ${title.size}px ${text}`;
    title.lines.forEach((l, j) => ctx.fillText(l, tx, ty + (j + 0.85) * title.size * 1.2));
    ty += title.lines.length * title.size * 1.2;
    if (meta) {
      ctx.fillStyle = T.mix(pal.ink, '#ffffff', 0.35);
      ctx.font = `600 ${meta.size}px ${text}`;
      meta.lines.forEach((l, j) => ctx.fillText(l, tx, ty + meta.size * (1.1 + j * 1.25)));
    }
    ctx.restore();
  });
  return y + rows.length * (rowH + gap) - gap;
}

/** Faces in a grid, each in a ring, with the whole name (shrunk, never cut) and an optional line. */
function v2Faces(ctx, reel, people, y, t, k, colors) {
  const { pal } = reel;
  const { text, head } = T.fonts();
  const n = people.length;
  const cols = n <= 1 ? 1 : n <= 4 ? 2 : 3;
  const rows = Math.ceil(n / cols);
  const w = SAFE.right - SAFE.left;
  const cell = Math.min(w / cols, (V2_BOTTOM - y) / rows);
  // The words under each photo are measured first — the whole name (a long one on two lines, never cut;
  // owner, 24 Oct 2026) and the line under it — and the photos take what is left, so nothing runs into
  // the next row or the brand strip.
  const fits = people.map((person) => [
    person.name ? fitAll(ctx, person.name, w / cols - 20, n === 1 ? 60 : 38, 28, 2, 800, text) : null,
    person.meta ? fitAll(ctx, person.meta, w / cols - 20, n === 1 ? 40 : 30, 26, n === 1 ? 3 : 2, 600, text) : null,
  ]);
  const wordsH = Math.max(0, ...fits.map(([nf, mf]) => (nf ? nf.size * (1 + (nf.lines.length - 1) * 1.15) + 14 : 0) + (mf ? mf.lines.length * mf.size * 1.25 + 10 : 0)));
  const size = Math.max(140, Math.min(n === 1 ? 440 : 290, cell - 60 - wordsH));
  const ghost = Boolean(reel.features?.ghostReveal);
  people.forEach((person, i) => {
    const p = back(span(t, cue(reel, k + i), 0.5));
    if (p <= 0 && !ghost) return;
    const isGhost = p <= 0;
    const effP = isGhost ? 0 : clamp(p);
    const col = i % cols;
    const row = Math.floor(i / cols);
    const inRow = Math.min(cols, n - row * cols);
    const cx = SAFE.left + w / 2 + (col - (inRow - 1) / 2) * (w / cols);
    const cy = y + row * cell + size / 2 + 12;
    const beat = isGhost ? 1 : 1 + 0.03 * onBeat(reel, t);
    ctx.save();
    ctx.globalAlpha *= isGhost ? 0.22 : effP;
    ctx.translate(cx, cy);
    const s = isGhost ? 0.7 : (0.6 + 0.4 * p) * beat;
    ctx.scale(s, s);
    ctx.fillStyle = colors.hl;
    ctx.beginPath();
    ctx.arc(0, 0, size / 2 + 10, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(0, 0, size / 2, 0, Math.PI * 2);
    ctx.fillStyle = T.mix(pal.primary, pal.paper, 0.75);
    ctx.fill();
    ctx.clip();
    const img = reel.photos.get(person.key);
    if (img) T.drawContain(ctx, img, -size / 2, -size / 2, size, size);
    else {
      ctx.fillStyle = pal.primary;
      ctx.font = `800 ${Math.round(size * 0.36)}px ${head}`;
      ctx.textAlign = 'center';
      ctx.fillText(T.initials(person.name || person.full || '?'), 0, size * 0.13);
    }
    ctx.restore();
    ctx.save();
    ctx.globalAlpha *= isGhost ? 0.22 : effP;
    ctx.textAlign = 'center';
    let ny = cy + size / 2 + 26;
    const [nameFit, metaFit] = fits[i];
    if (nameFit) {
      ctx.fillStyle = colors.ink;
      ctx.font = `800 ${nameFit.size}px ${text}`;
      nameFit.lines.forEach((l, j) => ctx.fillText(l, cx, ny + nameFit.size + j * nameFit.size * 1.15));
      ny += nameFit.size * (1 + (nameFit.lines.length - 1) * 1.15) + 14;
    }
    if (metaFit) {
      ctx.fillStyle = colors.quiet;
      ctx.font = `600 ${metaFit.size}px ${text}`;
      metaFit.lines.forEach((l, j) => ctx.fillText(l, cx, ny + (j + 1) * metaFit.size * 1.25));
    }
    ctx.restore();
  });
}

/** Logos on white tiles: two across up to six (big enough to read on a phone), three across beyond. */
function v2Logos(ctx, reel, logos, y, t, k) {
  const n = logos.length;
  const cols = n <= 6 ? 2 : 3;
  const rows = Math.ceil(n / cols);
  const w = SAFE.right - SAFE.left;
  const gap = 24;
  const tw = (w - gap * (cols - 1)) / cols;
  const th = Math.min(tw * 0.62, (V2_BOTTOM - y - gap * (rows - 1)) / rows);
  const ghost = Boolean(reel.features?.ghostReveal);
  logos.forEach((logo, i) => {
    const p = back(span(t, cue(reel, k + i * 0.5), 0.45));
    if (p <= 0 && !ghost) return;
    const isGhost = p <= 0;
    const effP = isGhost ? 0 : clamp(p);
    // A short last row stands in the middle.
    const inRow = Math.min(cols, n - Math.floor(i / cols) * cols);
    const x = SAFE.left + (w - inRow * tw - (inRow - 1) * gap) / 2 + (i % cols) * (tw + gap);
    const ty = y + Math.floor(i / cols) * (th + gap);
    ctx.save();
    ctx.globalAlpha *= isGhost ? 0.22 : effP;
    ctx.translate(x + tw / 2, ty + th / 2);
    const s = isGhost ? 0.7 : 0.7 + 0.3 * p;
    ctx.scale(s, s);
    if (!isGhost) {
      ctx.shadowColor = 'rgba(0,0,0,0.14)';
      ctx.shadowBlur = 24;
      ctx.shadowOffsetY = 8;
    }
    ctx.fillStyle = '#ffffff';
    T.roundRect(ctx, -tw / 2, -th / 2, tw, th, 26);
    ctx.fill();
    ctx.shadowColor = 'transparent';
    const img = reel.photos.get(logo.key);
    if (img) T.drawContain(ctx, img, -tw / 2 + 22, -th / 2 + 18, tw - 44, th - 36);
    else {
      // No logo on the WordCamp site: the sponsor's whole name, as large as the tile allows.
      const fit = fitAll(ctx, logo.name, tw - 48, 46, 34, 2, 800, T.fonts().text);
      ctx.fillStyle = reel.pal.ink;
      ctx.textAlign = 'center';
      ctx.font = `800 ${fit.size}px ${T.fonts().text}`;
      fit.lines.forEach((l, j) => ctx.fillText(l, 0, fit.size * 0.36 + (j - (fit.lines.length - 1) / 2) * fit.size * 1.15));
    }
    ctx.restore();
  });
}

/**
 * "Send this to your WordCamp buddy": a pill with a paper plane under the date and venue (owner, 24 Oct
 * 2026), so the reel gets passed on — a send is what takes a reel to people who don't follow the page.
 */
function v2Share(ctx, reel, words, x, y, t, k, colors, align = 'left') {
  const p = back(span(t, cue(reel, k), 0.45));
  if (p <= 0) return;
  const { pal } = reel;
  const { text } = T.fonts();
  const h = 78;
  ctx.save();
  ctx.font = `700 34px ${text}`;
  const tw = Math.min(SAFE.right - SAFE.left - h - 40, ctx.measureText(words).width);
  const w = h + tw + 34;
  const left = align === 'center' ? x - w / 2 : x;
  const bg = colors.light ? '#ffffff' : pal.primary;
  const fg = colors.light ? pal.ink : T.textOn(pal.primary, pal.ink, '#ffffff');
  ctx.globalAlpha *= clamp(p);
  ctx.translate(left + w / 2, y + h / 2);
  const s = (0.7 + 0.3 * p) * (1 + 0.03 * onBeat(reel, t));
  ctx.scale(s, s);
  ctx.translate(-w / 2, -h / 2);
  ctx.shadowColor = 'rgba(0,0,0,0.16)';
  ctx.shadowBlur = 24;
  ctx.shadowOffsetY = 8;
  ctx.fillStyle = bg;
  T.roundRect(ctx, 0, 0, w, h, h / 2);
  ctx.fill();
  ctx.shadowColor = 'transparent';
  // The paper plane, on a round chip.
  const r = h / 2 - 9;
  ctx.fillStyle = fg;
  ctx.beginPath();
  ctx.arc(h / 2, h / 2, r, 0, Math.PI * 2);
  ctx.fill();
  const c = r * 0.55;
  ctx.fillStyle = bg;
  ctx.beginPath();
  ctx.moveTo(h / 2 - c, h / 2 - c * 0.05);
  ctx.lineTo(h / 2 + c, h / 2 - c * 0.85);
  ctx.lineTo(h / 2 + c * 0.25, h / 2 + c * 0.9);
  ctx.lineTo(h / 2 - c * 0.05, h / 2 + c * 0.2);
  ctx.closePath();
  ctx.fill();
  ctx.fillStyle = fg;
  ctx.textAlign = 'left';
  ctx.fillText(words, h + 6, h / 2 + 12, tw);
  ctx.restore();
}

const SHARE = 'Send this to your WordCamp buddy';

/** One fact scene: step header, kicker, headline, line, and its body (rows, faces, logos or a big number); `share` adds the send pill under it. */
function v2Fact({ step, ground = 'paper', kicker, headline, line, body = null, beats = V2_BEATS.fact, share = null }) {
  const pictures = [];
  if (body?.type === 'faces') body.people.forEach((p) => pictures.push([p.key, p.photo]));
  if (body?.type === 'logos') body.logos.forEach((l) => pictures.push([l.key, l.logo]));
  return {
    beats,
    step,
    ground,
    stretch: true,
    pictures,
    // What it says, names whole (the tests read it).
    words: { kicker, headline, line, names: (body?.people ?? body?.logos ?? []).map((p) => p.full || p.name).filter(Boolean) },
    draw(ctx, t, dur, reel) {
      leaving(ctx, t, dur);
      const colors = v2Ink(reel);
      const center = reel.align === 'center';
      const x = center ? W / 2 : SAFE.left;
      const align = center ? 'center' : 'left';
      const { text, head } = T.fonts();
      let y = v2Header(ctx, reel, t, this);
      // Words only: the block sits a third of the way down the safe zone, not under the header.
      if (!body) y = Math.max(y, V2_TOP + (V2_BOTTOM - V2_TOP) * 0.22);
      if (kicker) {
        const p = ease(span(t, cue(reel, 0), 0.4));
        ctx.save();
        ctx.globalAlpha *= p;
        ctx.font = `800 34px ${text}`;
        const kw = Math.min(SAFE.right - SAFE.left - 50, ctx.measureText(kicker.toUpperCase()).width);
        const kx = center ? W / 2 - (kw + 50) / 2 : SAFE.left;
        T.waveMark(ctx, kx, y - 12, 34, colors.hl);
        ctx.fillStyle = colors.hl;
        ctx.textAlign = 'left';
        ctx.fillText(kicker.toUpperCase(), kx + 50, y, SAFE.right - SAFE.left - 50);
        ctx.restore();
        y += 30;
      }
      if (body?.type === 'number') {
        const p = back(span(t, cue(reel, 1), 0.5));
        ctx.save();
        ctx.globalAlpha *= clamp(p);
        ctx.fillStyle = colors.hl;
        const s = (0.7 + 0.3 * p) * (1 + 0.05 * onBeat(reel, t));
        ctx.translate(x, y + 260);
        ctx.scale(s, s);
        ctx.font = `900 300px ${head}`;
        ctx.textAlign = align;
        let numStr = body.value;
        if (reel.features?.countUp) {
          const match = String(body.value).match(/\d+/);
          if (match) {
            const target = parseInt(match[0], 10);
            const countDur = (reel.beat ?? 0.5) * 1.8;
            const prog = ease(span(t, cue(reel, 0), countDur));
            const curr = Math.min(target, Math.round(target * prog));
            numStr = String(body.value).replace(match[0], String(curr));
          }
        }
        ctx.fillText(numStr, center ? 0 : -6, 0, SAFE.right - SAFE.left);
        ctx.restore();
        y += 300;
      }
      if (headline) y = v2Headline(ctx, reel, headline, x, y, t, body?.type === 'number' ? 2 : 1, colors, { size: body ? 84 : 112, min: 56, lines: body ? 3 : 4, align });
      if (line) y = v2Line(ctx, reel, line, x, y, t, 3, colors.quiet, { align });
      y += 30;
      if (body?.type === 'rows') y = v2Rows(ctx, reel, body.rows, y, t, 4, share ? V2_BOTTOM - 130 : V2_BOTTOM) + 40;
      else if (body?.type === 'faces') v2Faces(ctx, reel, body.people, y, t, 4, colors);
      else if (body?.type === 'logos') v2Logos(ctx, reel, body.logos, y, t, 4);
      if (share) v2Share(ctx, reel, share, x, Math.min(y + 10, V2_BOTTOM - 90), t, 4 + (body?.rows?.length ?? 0), colors, align);
    },
  };
}

/** The hook (0–2 s): the reel's promise in big words, no header. */
function v2Hook(words, line) {
  return {
    beats: V2_BEATS.hook,
    ground: 'ink',
    pictures: [],
    words: { headline: words, line },
    draw(ctx, t, dur, reel) {
      leaving(ctx, t, dur);
      const colors = v2Ink(reel);
      const center = reel.align === 'center';
      const x = center ? W / 2 : SAFE.left;
      const align = center ? 'center' : 'left';
      let y = V2_TOP + (V2_BOTTOM - V2_TOP) * 0.16;
      y = v2Headline(ctx, reel, words, x, y, t, 0, colors, { size: 136, min: 80, lines: 4, align, isHook: true });
      v2Line(ctx, reel, line, x, y + 10, t, 3, colors.quiet, { size: 46, align, isHook: true });
    },
  };
}

/**
 * The one CampBuddy end card: CampBuddy's mark at the top, WPSimplified's at the foot, and between
 * them the call, the QR and campbuddy.club. No event logo here: the other frames carry it.
 * The call follows the reel's topic ("Save their talks in CampBuddy"); it may hold a few beats
 * longer than the others, time to scan the QR.
 */
function v2End(content, call = null) {
  return {
    beats: V2_BEATS.end,
    ground: 'gradient',
    end: true,
    stretch: true,
    stretchMax: V2_BEATS.end + 4,
    pictures: [],
    draw(ctx, t, dur, reel) {
      const { pal, kit } = reel;
      const { text } = T.fonts();
      const colors = v2Ink(reel);
      const plate = (img, y, h, k) => {
        if (!img) return y;
        const p = back(span(t, cue(reel, k), 0.5));
        const w = (img.width / img.height) * h;
        ctx.save();
        ctx.globalAlpha *= clamp(p);
        ctx.translate(W / 2, y + (h + 40) / 2);
        ctx.scale(0.7 + 0.3 * p, 0.7 + 0.3 * p);
        ctx.fillStyle = T.mix(pal.paper, '#ffffff', 0.7);
        T.roundRect(ctx, -(w + 80) / 2, -(h + 40) / 2, w + 80, h + 40, (h + 40) / 2);
        ctx.fill();
        ctx.drawImage(img, -w / 2, -h / 2, w, h);
        ctx.restore();
        return y + h + 40;
      };
      // CampBuddy, top centre; WPSimplified, foot centre.
      let y = plate(reel.campbuddy, SAFE.top, 92, 0) + 44;
      const footH = 64;
      plate(reel.wps, SAFE.bottom - footH - 40, footH, 6);
      const bottom = SAFE.bottom - footH - 40 - 36;

      const [words, line] = call ?? [`Plan ${kit.short ?? kit.event.name} in *CampBuddy*`, 'The schedule, reminders and people to meet, for the whole event.'];
      y = v2Headline(ctx, reel, words, W / 2, y, t, 1, colors, { size: 86, min: 56, lines: 3, align: 'center', width: TEXT_W });
      y = v2Line(ctx, reel, line, W / 2, y, t, 3, colors.quiet, { size: 36, lines: 2, align: 'center', width: TEXT_W });
      const pillH = 78;
      const qs = Math.max(220, Math.min(360, bottom - y - pillH - 90));
      const q = back(span(t, cue(reel, 4), 0.5));
      ctx.save();
      ctx.globalAlpha *= clamp(q);
      ctx.translate(W / 2, y + 30 + qs / 2);
      const s = (0.6 + 0.4 * q) * (1 + 0.025 * onBeat(reel, t));
      ctx.scale(s, s);
      ctx.fillStyle = '#ffffff';
      T.roundRect(ctx, -qs / 2 - 20, -qs / 2 - 20, qs + 40, qs + 40, 34);
      ctx.fill();
      T.drawQr(ctx, qrLink(kit.event.qr, `reel-${content}`), -qs / 2, -qs / 2, qs, pal.ink, '#ffffff');
      ctx.restore();
      y += 30 + qs + 44;
      const pp = ease(span(t, cue(reel, 5), 0.4));
      ctx.save();
      ctx.globalAlpha *= pp;
      ctx.font = `700 38px ${text}`;
      const url = 'campbuddy.club';
      const pw = ctx.measureText(url).width + 116;
      const py = Math.min(y, bottom - pillH);
      ctx.fillStyle = '#ffffff';
      T.roundRect(ctx, (W - pw) / 2, py, pw, pillH, pillH / 2);
      ctx.fill();
      T.globe(ctx, (W - pw) / 2 + 46, py + pillH / 2, 15, pal.ink);
      ctx.fillStyle = pal.ink;
      ctx.textAlign = 'left';
      ctx.fillText(url, (W - pw) / 2 + 72, py + pillH / 2 + 13);
      ctx.restore();
    },
  };
}

/** Turns a list by `n` places, so each reel starts its picks somewhere else. */
function rotate(list, n) {
  if (!list.length) return list;
  const k = ((n % list.length) + list.length) % list.length;
  return [...list.slice(k), ...list.slice(0, k)];
}

// ---- What each reel tells (owner, 24 Oct 2026) --------------------------------
//
// A reel tells only its own topic: the organizers' reel the organizers, the
// sponsors' reel the sponsors — never another reel's speakers, sponsors,
// schedule or team. Then one slide with the event's dates and venue (and a
// nudge to send the reel on), and the CampBuddy end card with a call that
// fits the topic. Lists go one point per cut (01, 02 …) and people one by one,
// then all together: one idea every two or three seconds. Every name is whole.

/** The whole name, its first name highlighted (*Anup* Kumar). */
function nameWords(full) {
  const [first = '', ...rest] = String(full ?? '').trim().split(/\s+/);
  return `*${first}* ${rest.join(' ')}`.trim();
}

/** The Contributor Day table a lead runs ("Polyglots table"), found by their name. */
function tableOf(kit, name) {
  const key = String(name ?? '').trim().toLowerCase();
  const table = (kit.tables ?? []).find((tb) => (tb.leads ?? []).some((l) => String(l).trim().toLowerCase() === key));
  return table ? `${table.name} table` : null;
}

/** The one date-and-venue slide: the dates highlighted, the place, and the send pill. */
function v2When(reel) {
  const { event } = reel.kit;
  if (!event.dates && !event.place) return [];
  return [v2Fact({
    step: 'When & where',
    ground: 'paper',
    kicker: daysToGo(reel.kit) !== null ? 'Save the date' : 'When & where',
    headline: event.dates ? `*${event.dates}*` : `*${event.name}*`,
    line: [event.place, event.name].filter(Boolean).join(' · ') || null,
    share: SHARE,
  })];
}

/** A post's points, one per cut, each under its big number (01, 02 …). */
function v2Points(base, items) {
  return items.map((it, i) => v2Fact({
    ...base,
    ground: i % 2 ? 'ink' : 'paper',
    line: null,
    headline: typeof it === 'string' ? it : it.title ?? String(it),
    body: { type: 'number', value: String(i + 1).padStart(2, '0') },
    beats: 4,
  }));
}

/** A big count before a list: "12 sponsors make it happen". */
function v2Count(step, n, words, line = null) {
  return v2Fact({ step, ground: 'gradient', kicker: step, body: { type: 'number', value: String(n) }, headline: words, line });
}

/** Three talks from the schedule (each reel its own three), with their speakers' whole names. */
function v2Talks(reel, step) {
  const sessions = reel.kit.schedule?.sessions ?? [];
  const talks = sessions.filter((s) => (s.speakers ?? []).length);
  const pool = talks.length ? talks : sessions;
  if (!pool.length) return [];
  const rows = rotate(pool, (reel.item?.variant ?? 0) * 3).slice(0, 3).map((s) => ({ mark: s.time || undefined, title: s.title, meta: (s.speakers ?? []).join(', ') || null }));
  return [v2Fact({ step, ground: 'paper', kicker: 'Worth a seat', headline: 'Talks worth *saving*', body: { type: 'rows', rows } })];
}

/**
 * A post reel's own scenes, from the post's content, and where its date-and-venue slide goes:
 * 'first' (the date is the news), 'last', or null (the post already says it, or the event is over).
 */
function v2PostTopic(reel, content, label) {
  const { kit } = reel;
  const body = content.body ?? { type: 'none' };
  const base = { step: label, kicker: content.kicker || label, headline: content.headline || label, line: content.line || null };
  const count = (n, one, many) => `*${n === 1 ? one : many}*`;
  if (body.type === 'countdown') {
    const rows = [
      { title: 'Save the talks you want', meta: 'A reminder before each one' },
      { title: 'Find people to meet', meta: 'Who shares your interests' },
      kit.tables?.length ? { title: 'Pick a Contributor Day table', meta: 'Three quick questions' } : { title: 'Pack the right things', meta: 'The packing list' },
    ];
    return {
      when: 'last',
      scenes: [
        v2Fact({ ...base, ground: 'gradient', line: null, headline: body.days === 1 ? '*day* to go' : '*days* to go', body: { type: 'number', value: String(body.days) } }),
        v2Fact({ step: label, ground: 'paper', kicker: 'Get ready', headline: 'Before the *big day*', body: { type: 'rows', rows } }),
      ],
    };
  }
  if (body.type === 'features') {
    // The venue's points are its place and dates; after the event a date slide says nothing new.
    const when = ['venue', 'thank-you'].includes(content.key) ? null : 'last';
    return { when, scenes: [...v2Points(base, body.items), ...(content.key === 'schedule' ? v2Talks(reel, label) : [])] };
  }
  if (body.type === 'photos') {
    const total = (kit.speakers ?? []).length;
    // Speakers with a photo first (the post's own pick); none has one: everyone, with initials.
    const groups = chunk((body.people.length ? body.people : kit.speakers ?? []).slice(0, 12), 4);
    return {
      when: 'last',
      scenes: [
        ...(total > 4 ? [v2Count(label, total, `${count(total, 'speaker', 'speakers')} on stage`, base.line)] : []),
        // Four at a time under a one-line count ("1–4 of 29"), so the faces and whole names get the room.
        ...groups.map((group, i) => v2Fact({ ...base, line: null, headline: groups.length > 1 || total > group.length ? `*${i * 4 + 1}–${i * 4 + group.length}* of ${Math.max(total, group.length)}` : base.headline, ground: i % 2 ? 'paper' : 'ink', body: { type: 'faces', people: group.map((p, j) => ({ key: `topic-${i}-${j}`, name: p.name, photo: p.photo })) } })),
      ],
    };
  }
  if (body.type === 'logos') {
    const all = body.logos;
    return {
      when: 'last',
      scenes: [
        ...(all.length > 2 ? [v2Count(label, all.length, `${count(all.length, 'sponsor', 'sponsors')} make it happen`, base.line)] : []),
        ...chunk(all.slice(0, 18), 6).map((group, i) => v2Fact({ ...base, line: null, ground: 'paper', body: { type: 'logos', logos: group.map((s, j) => ({ key: `topic-${i}-${j}`, name: s.name, logo: s.logo })) } })),
      ],
    };
  }
  if (body.type === 'list') {
    return { when: 'last', scenes: chunk(body.items.slice(0, 6), 3).map((rows) => v2Fact({ ...base, ground: 'paper', body: { type: 'rows', rows: rows.map((r) => ({ mark: r.time || undefined, title: r.title, meta: r.meta || null })) } })) };
  }
  if (body.type === 'session') {
    return { when: 'last', scenes: [v2Fact({ ...base, ground: 'ink', body: { type: 'faces', people: body.speakers.slice(0, 4).map((name, j) => ({ key: `topic-0-${j}`, name, photo: body.photos?.[j] })) } })] };
  }
  if (body.type === 'tables') {
    const tables = body.tables;
    return {
      when: 'last',
      scenes: [
        ...(tables.length > 3 ? [v2Count(label, tables.length, `${count(tables.length, 'table', 'tables')} to give back`, base.line)] : []),
        ...chunk(tables.slice(0, 6), 3).map((rows) => v2Fact({ ...base, line: null, ground: 'paper', body: { type: 'rows', rows: rows.map((tb) => ({ title: tb.name, meta: (tb.leads ?? []).slice(0, 2).join(', ') || null })) } })),
      ],
    };
  }
  // Words only. Coming up: the date is the news, then how far off it is; any other: the date and venue after.
  if (content.key !== 'save-the-date') return { when: 'last', scenes: [] };
  const days = daysToGo(kit);
  return { when: 'first', scenes: days ? [v2Fact({ step: 'Countdown', ground: 'gradient', kicker: 'Countdown', headline: days === 1 ? '*day* to go' : '*days* to go', body: { type: 'number', value: String(days) } })] : [] };
}

const TALK_CALL = ['Save their talks in *CampBuddy*', 'Get a reminder before each one starts.'];
const DAY_CALL = ['Plan your day in *CampBuddy*', 'Save talks and get a reminder before each one.'];
const TABLE_CALL = ['Find your table in *CampBuddy*', 'Three questions, one Contributor Day team for you.'];

/** The end card's call by topic (a post's key, a people reel's role); any other reel: "Plan {event} in CampBuddy". */
const V2_CALLS = {
  speaker: TALK_CALL,
  speakers: TALK_CALL,
  session: ['Save this talk in *CampBuddy*', 'Get a reminder before it starts.'],
  sponsors: ['See their deals in *CampBuddy*', 'Offers for attendees, all in one place.'],
  table_lead: TABLE_CALL,
  contributor: TABLE_CALL,
  schedule: DAY_CALL,
  today: DAY_CALL,
  countdown: DAY_CALL,
  meet: ['Find your people in *CampBuddy*', 'See who shares your interests, and say hi.'],
  'first-timer': ['Read the 5-minute guide in *CampBuddy*', 'How the day runs, what to bring, how to meet people.'],
  moments: ['Keep your day in *CampBuddy*', 'Your talks, notes and the people you met, in Memories.'],
  'thank-you': ['Your day is saved in *CampBuddy*', 'Your talks, notes and the people you met.'],
};

/** Every scene of a reel: the hook, its own topic, the date and venue, the end card. */
function v2Scenes(reel, spec) {
  const { kit } = reel;
  const short = kit.short ?? kit.event.name;
  const sub = [kit.event.name, kit.event.dates].filter(Boolean).join(' · ');
  // The reel's own hook words, when the studio set them.
  const ownHook = reel.item?.hook?.trim();

  if (spec.kind === 'session') {
    const s = spec.session;
    const speakers = (s.speakers ?? []).map((name, i) => ({ key: `session-${i}`, name, photo: s.photos?.[i] }));
    const day = kit.schedule?.days?.find((d) => d.key === s.day)?.label;
    // Up to four speakers each get their own cut, photo and whole name; more stand together.
    const onStage = speakers.length > 4
      ? [v2Fact({ step: 'On stage', ground: 'ink', kicker: `${speakers.length} speakers`, headline: 'On *stage*', body: { type: 'faces', people: speakers.slice(0, 6) } })]
      : speakers.map((p, i) => v2Fact({ step: 'On stage', ground: i % 2 ? 'paper' : 'ink', kicker: speakers.length === 1 ? 'Speaker' : `Speaker ${i + 1} of ${speakers.length}`, headline: nameWords(p.name), body: { type: 'faces', people: [{ ...p, name: '', full: p.name, meta: speakers.length === 1 && s.title ? `“${s.title}”` : null }] } }));
    // The talk's own when and where: its time and day, its room, the venue.
    const rows = [
      s.track ? { title: s.track, meta: 'Room' } : null,
      kit.event.place ? { title: kit.event.place, meta: kit.event.dates || null } : null,
      { title: 'Save it in CampBuddy', meta: 'A reminder before it starts' },
    ].filter(Boolean).map((r, i) => ({ ...r, mark: String(i + 1) }));
    return [
      v2Hook(ownHook || s.title, [day, s.time, s.track].filter(Boolean).join(' · ')),
      ...onStage,
      v2Fact({ step: 'When & where', ground: 'paper', kicker: 'Save the talk', headline: [s.time ? `*${s.time}*` : null, day].filter(Boolean).join(' · ') || `*${kit.event.dates ?? ''}*`, body: { type: 'rows', rows }, share: SHARE }),
      v2End(spec.utm, V2_CALLS.session),
    ];
  }

  if (spec.kind === 'post') {
    const content = spec.content ?? {};
    const words = ownHook || (content.headline || spec.label || kit.event.name).trim();
    const topic = v2PostTopic(reel, content, spec.label);
    const when = topic.when ? v2When(reel) : [];
    // When the date is the news, the hook keeps it back for the next cut.
    const line = topic.when === 'first' ? kit.event.name : (content.line || sub);
    return [
      v2Hook(words, line),
      ...(topic.when === 'first' ? when : []),
      ...topic.scenes,
      ...(topic.when === 'last' ? when : []),
      v2End(spec.utm, V2_CALLS[content.key] ?? null),
    ];
  }

  // A people reel: the group's own people, one by one (then all together) or four at a time.
  const n = spec.people.length;
  const people = spec.people.map((p, i) => ({ key: `person-${i}`, full: p.name, photo: p.photo, meta: spec.role === 'table_lead' ? tableOf(kit, p.name) : (spec.role === 'speaker' ? p.talk || null : null) }));
  const one = (spec.role === 'speaker' && n <= 24) || n <= ONE_BY_ONE;
  const singular = spec.label.replace(/s$/, '');
  const scenes = one
    ? people.map((p, i) => v2Fact({ step: spec.label, ground: i % 2 ? 'paper' : 'ink', kicker: singular, headline: nameWords(p.full), body: { type: 'faces', people: [{ key: p.key, name: '', full: p.full, photo: p.photo, meta: p.meta }] } }))
    : chunk(people, 4).map((group, i) => v2Fact({ step: spec.label, ground: i % 2 ? 'paper' : 'ink', kicker: `${spec.label} of ${short}`, headline: `*${i * 4 + 1}–${i * 4 + group.length}* of ${n}`, body: { type: 'faces', people: group.map((p) => ({ key: p.key, name: p.full, photo: p.photo, meta: p.meta })) } }));
  if (one && n > 1) {
    scenes.push(v2Fact({ step: spec.label, ground: 'gradient', kicker: 'All together', headline: `The ${spec.label.toLowerCase()} of *${short}*`, body: { type: 'faces', people: people.slice(0, 6).map((p) => ({ key: p.key, name: p.full, photo: p.photo })) } }));
  }
  const [, line] = GROUP_WORDS[spec.role](short);
  return [v2Hook(ownHook || PEOPLE_HOOKS[spec.role](n, spec), line), ...scenes, ...v2When(reel), v2End(spec.utm, V2_CALLS[spec.role] ?? null)];
}

/** The template a reel uses: the studio's pick, else Pulse by default (built on Daylight). */
function v2TemplateOf(item, seed) {
  if (item.template && REEL_TEMPLATES[item.template]) return item.template;
  return 'pulse';
}

/**
 * The reel's progress as a border round the whole frame (owner, 22 Oct 2026): it starts at the top-left
 * corner, runs clockwise along the top, down the right, back along the foot and up the left, and closes
 * at the top-left corner on the last frame. A faint track shows the way; the stroke is thick enough to
 * read on a phone, in the light colour on dark grounds and the main colour on light ones.
 */
function v2Border(ctx, reel, time) {
  const inset = 14;
  const lw = 16;
  const r = 56;
  const x0 = inset + lw / 2;
  const y0 = inset + lw / 2;
  const x1 = W - inset - lw / 2;
  const y1 = H - inset - lw / 2;
  const p = clamp(time / Math.max(0.001, reel.duration));
  // One path from the top-left corner's end of its curve, clockwise, ending where it began.
  const path = new Path2D();
  path.moveTo(x0 + r, y0);
  path.lineTo(x1 - r, y0);
  path.arcTo(x1, y0, x1, y0 + r, r);
  path.lineTo(x1, y1 - r);
  path.arcTo(x1, y1, x1 - r, y1, r);
  path.lineTo(x0 + r, y1);
  path.arcTo(x0, y1, x0, y1 - r, r);
  path.lineTo(x0, y0 + r);
  path.arcTo(x0, y0, x0 + r, y0, r);
  const length = 2 * (x1 - x0 - 2 * r) + 2 * (y1 - y0 - 2 * r) + 2 * Math.PI * r;
  const light = v2Ink(reel).light;
  ctx.save();
  ctx.lineWidth = lw;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = light ? 'rgba(255,255,255,0.18)' : T.alpha(reel.pal.primary, 0.14);
  ctx.stroke(path);
  if (p > 0) {
    ctx.setLineDash([Math.max(0.01, length * p), length + 10]);
    ctx.strokeStyle = light ? T.readableOn([reel.pal.secondary, '#ffffff'], [inkGround(reel.pal)]) : reel.pal.primary;
    // A soft glow on the head of the line, pulsing on the beat.
    ctx.shadowColor = ctx.strokeStyle;
    ctx.shadowBlur = 10 + 14 * onBeat(reel, time);
    ctx.stroke(path);
  }
  ctx.restore();
}

/** Wave cut transition: a dual-band sine wave wipe across the frame. */
function v2WaveCut(ctx, reel, cutIn) {
  const p = ease(cutIn);
  if (p >= 1) return;
  const { pal } = reel;
  const drawWave = (prog, color, alpha) => {
    const edgeX = W * clamp(prog);
    const amp = 48 * Math.sin(clamp(prog) * Math.PI);
    const freq = 0.014;
    ctx.save();
    ctx.fillStyle = color;
    ctx.globalAlpha = alpha;
    ctx.beginPath();
    ctx.moveTo(W, 0);
    ctx.lineTo(edgeX, 0);
    for (let y = 0; y <= H; y += 12) {
      const x = edgeX + amp * Math.sin(y * freq);
      ctx.lineTo(x, y);
    }
    ctx.lineTo(W, H);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
  };
  drawWave(p * 1.15, pal.secondary, 0.7 * (1 - p));
  drawWave(p, pal.primary, 0.88 * (1 - p));
}

/** Frame 0 exact appearance for seamless loop blending. */
function drawFirstFrame(ctx, reel) {
  const scene0 = reel.scenes[0];
  if (!scene0) return;
  const tpl = REEL_TEMPLATES[reel.template] ?? REEL_TEMPLATES.pulse;
  const ground = tpl.ground(scene0.ground ?? 'paper', 0);
  v2Ground(ctx, 0, reel, ground);
  v2Effects(ctx, 0, reel);
  scene0.draw(ctx, 0, scene0.dur, reel);
  v2Brand(ctx, reel, 0);
  v2Border(ctx, reel, 0);
}

/** One frame of a template reel: ground, effects, the scene, its brand strip, and the cut into it. */
function v2Frame(ctx, reel, time) {
  const index = reel.scenes.findLastIndex((s) => s.start <= time);
  const scene = reel.scenes[Math.max(0, index)];
  const t = time - scene.start;
  const tpl = REEL_TEMPLATES[reel.template] ?? REEL_TEMPLATES.pulse;
  reel.ground = tpl.ground(scene.ground ?? 'paper', Math.max(0, index));
  v2Ground(ctx, time, reel, reel.ground);
  v2Effects(ctx, time, reel);
  // The cut, on the beat: some templates move the new scene in.
  const cutIn = clamp(t / 0.28);
  ctx.save();
  if (index > 0 && tpl.cut === 'zoom') {
    const k = 1 + (1 - ease(cutIn)) * 0.08;
    ctx.translate(W / 2, H / 2);
    ctx.scale(k, k);
    ctx.translate(-W / 2, -H / 2);
  } else if (index > 0 && tpl.cut === 'slide') ctx.translate((1 - ease(cutIn)) * 140, 0);
  scene.draw(ctx, t, scene.dur, reel);
  ctx.restore();
  if (!scene.end) v2Brand(ctx, reel, time);
  v2Border(ctx, reel, time);
  if (index > 0 && cutIn < 1) {
    ctx.save();
    if (tpl.cut === 'flash') {
      ctx.globalAlpha = 0.55 * (1 - cutIn);
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, W, H);
    } else if (tpl.cut === 'wipe') {
      ctx.fillStyle = reel.pal.secondary;
      ctx.fillRect(W * ease(cutIn), 0, W * (1 - ease(cutIn)), H);
      ctx.fillStyle = reel.pal.primary;
      ctx.fillRect(W * Math.min(1, ease(cutIn) * 1.25), 0, W, H);
    } else if (tpl.cut === 'wave') {
      v2WaveCut(ctx, reel, cutIn);
    }
    ctx.restore();
  }
  // Loop-friendly end (tpl.features.loopEnd): cross-fade into frame 0 in the last 0.4s
  if (tpl.features?.loopEnd && reel.scenes.length > 0) {
    const loopSpan = 0.4;
    const loopStart = reel.duration - loopSpan;
    if (time >= loopStart) {
      const p = ease(clamp((time - loopStart) / loopSpan));
      if (p > 0) {
        ctx.save();
        ctx.globalAlpha = p;
        drawFirstFrame(ctx, reel);
        ctx.restore();
      }
    }
  }
}

// ---- The reels on offer ------------------------------------------------------

/** Every reel this event can have, with what it needs to be made. */
export function reelList(kit, posts) {
  const talks = (kit.schedule?.sessions ?? []).filter((s) => (s.speakers ?? []).length);
  const list = posts.filter((post) => !(post.key === 'spotlight' && talks.length)).map((post) => ({
    key: `post-${post.key}`,
    title: post.label,
    mood: POST_MOODS[post.key] ?? 'bright',
    caption: post.caption,
    build: (style, colors) => {
      const content = post.content();
      const pages = planPost(content, '1080x1920', style, colors);
      return { kind: 'post', pages, content, label: post.label, utm: post.key };
    },
  }));

  // A spotlight reel for every talk on the schedule.
  talks.forEach((session) => {
    list.push({
      key: `spotlight-${session.id}`,
      title: `Spotlight: ${session.title}`,
      mood: 'drive',
      caption: () => spotlightCaption(kit, session),
      build: () => ({ kind: 'session', session, label: 'Session spotlight', utm: `spotlight-${session.id}` }),
    });
  });

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

// A people reel's hook says how many: "Meet the *11 organizers*".
const PEOPLE_HOOKS = {
  organizer: (n) => (n === 1 ? 'Meet the *organizer*' : `Meet the *${n} organizers*`),
  speaker: (n, spec) => (spec.total > n ? `Meet the *speakers*: ${spec.first + 1}–${spec.first + n} of ${spec.total}` : (n === 1 ? 'Meet the *speaker*' : `Meet the *${n} speakers*`)),
  volunteer: (n) => (n === 1 ? 'Meet our *volunteer*' : `Meet our *${n} volunteers*`),
  media_partner: (n) => (n === 1 ? 'Our *media partner*' : `Our *${n} media partners*`),
  table_lead: (n) => (n === 1 ? 'Meet the *table lead*' : `Meet the *${n} table leads*`),
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

  if (reel.daylight || spec.kind === 'session') {
    // Daylight: the brand's reel templates (v2Scenes) — the hook, the topic, the event's facts, one end card,
    // in the reel's template (its own by its place in the list, or the studio's pick).
    reel.v2 = true;
    reel.template = v2TemplateOf(item, kit.event.seed);
    const tpl = REEL_TEMPLATES[reel.template];
    reel.align = tpl.align;
    reel.anim = item.anim && REEL_ANIMS[item.anim] ? item.anim : tpl.anim;
    reel.fx = { ...tpl.effects, ...(item.effects ?? {}) };
    reel.features = { ...(tpl.features ?? {}) };
    reel.lockup = await T.brandLockup();
    // The Photo template stands on the site's photos (each reel the next one), else its artwork.
    const site = T.siteArt();
    const photos = site.photo.length ? site.photo : site.illustration;
    reel.art.photo = photos.length ? photos[(item.variant ?? 0) % photos.length] : null;
    reel.scenes = v2Scenes(reel, spec);
    // "Photos and logos" off in the reel editor: faces become initials, logos their names.
    const wanted = item.noPictures ? [] : reel.scenes.flatMap((sc) => sc.pictures ?? []);
    const imgs = await Promise.all(wanted.map(([, url]) => (url ? T.loadRemote(kit, url) : null)));
    wanted.forEach(([key], i) => reel.photos.set(key, imgs[i]));
  } else if (spec.kind === 'post') {
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
    const photos = await Promise.all(spec.people.map((p) => T.loadRemote(kit, p.photo)));
    spec.people.forEach((p, i) => reel.photos.set(p.id, photos[i]));
    if (spec.role === 'speaker' || spec.people.length <= ONE_BY_ONE) {
      spec.people.forEach((p, i) => reel.scenes.push(personScene(p, spec.first + i, spec.total, spec.role === 'speaker', spec.role)));
    } else {
      chunk(spec.people, GRID).forEach((group, i) => reel.scenes.push(gridScene(group, i * GRID, spec.people.length, spec.role)));
    }
  }
  if (!reel.v2) {
    // What CampBuddy gives them and how to start, before the QR: posts get both, people reels the steps.
    if (spec.kind === 'post') reel.scenes.push(featuresScene(T.features));
    reel.scenes.push(howScene());
    reel.scenes.push(endScene(spec.utm));
  }

  // Daylight reels each get their own mood too (turning with the reel's place in the list), not one per kind.
  if (item.moodOverride) item = { ...item, mood: item.moodOverride };
  else if (reel.v2) {
    // The template's mood and sound, on top of the reel's own key, tempo, chords and arpeggio (lookFor).
    const tpl = REEL_TEMPLATES[reel.template];
    if (item.mood !== 'soft') item = { ...item, mood: tpl.mood ?? ['bright', 'drive', 'warm'][((item.variant ?? 0) + ((kit.event.seed >>> 0) % 3)) % 3] };
    reel.look = { ...reel.look, music: { ...reel.look.music, ...tpl.music } };
  }

  // Every scene lasts whole beats, so the cuts fall on the music.
  const mood = MOODS[item.mood] ?? MOODS.bright;
  reel.bpm = reelBpm(mood, reel.look);
  const beat = 60 / reel.bpm;
  reel.beat = beat;
  // At least MIN_SECONDS: a short reel holds its cards and people a little longer, a beat at a time, in turn
  // (a scene with a `stretchMax`, the end card, only up to it).
  const stretchable = reel.scenes.filter((s) => s.stretch);
  let beats = reel.scenes.reduce((n, s) => n + s.beats, 0);
  for (let i = 0, full = 0; beats * beat < MIN_SECONDS && full < stretchable.length; i++) {
    const sc = stretchable[i % stretchable.length];
    if (sc.stretchMax && sc.beats >= sc.stretchMax) { full++; continue; }
    full = 0;
    sc.beats++;
    beats++;
  }
  // At most MAX_SECONDS: drop the facts after the topic, last first, then shorten every scene by a beat.
  const facts = reel.scenes.filter((sc) => sc.fact);
  while (beats * beat > MAX_SECONDS && facts.length) {
    const drop = facts.pop();
    reel.scenes.splice(reel.scenes.indexOf(drop), 1);
    beats -= drop.beats;
  }
  for (const sc of reel.scenes) {
    if (beats * beat <= MAX_SECONDS) break;
    if (sc.beats > 3) { sc.beats -= 1; beats -= 1; }
  }
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
  if (reel.v2) {
    v2Frame(ctx, reel, time);
    return;
  }
  backdrop(ctx, time, reel);
  particles(ctx, reel, time);
  const scene = reel.scenes.findLast((s) => s.start <= time) ?? reel.scenes[0];
  ctx.save();
  scene.draw(ctx, time - scene.start, scene.dur, reel);
  ctx.restore();
  progressBar(ctx, reel, time, scene);
  transition(ctx, reel, time);
}

// The reel's progress (the brand's reel template, guideline §9): a thin track
// under the header row filling in the main colour, on every scene but the
// first (the hook stays clean) and the end card.
function progressBar(ctx, reel, time, scene) {
  const scenes = reel.scenes;
  if (scene === scenes[0] || scene === scenes[scenes.length - 1]) return;
  const p = Math.min(1, Math.max(0, time / reel.duration));
  const y = HEADER_Y + 124;
  const w = SAFE.right - SAFE.left;
  ctx.save();
  ctx.globalAlpha = 0.9 * ease(span(time - scene.start, 0, 0.4));
  ctx.fillStyle = T.mix(reel.pal.primary, reel.pal.paper, 0.78);
  T.roundRect(ctx, SAFE.left, y, w, 6, 3);
  ctx.fill();
  ctx.fillStyle = reel.pal.primary;
  T.roundRect(ctx, SAFE.left, y, Math.max(6, w * p), 6, 3);
  ctx.fill();
  ctx.restore();
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

/** A track from the Studio music library CampBuddy shares with WPSimplified: fetched once a visit, so "Make all" doesn't fetch it per reel. */
const libraryTracks = new Map();
function libraryTrack(url) {
  if (!libraryTracks.has(url)) {
    const got = fetch(url, { credentials: 'same-origin' }).then((r) => {
      if (!r.ok) throw new Error(`the Studio track isn't there (${r.status})`);
      return r.blob();
    });
    got.catch(() => libraryTracks.delete(url));
    libraryTracks.set(url, got);
  }
  return libraryTracks.get(url);
}

async function soundtrack(reel, music) {
  let buffer = null;
  if (music.mode === 'auto') buffer = await makeMusic(reel);
  else if (music.mode === 'file' && music.file) buffer = await ownTrack(music.file, reel.duration);
  else if (music.mode === 'library' && music.url) buffer = await ownTrack(await libraryTrack(music.url), reel.duration);
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
  // 1080 × 1920 at 30 fps, 10 Mbps H.264 High: above Instagram's and YouTube Shorts' recommendations for 1080p.
  video.configure({ codec: 'avc1.640028', width: W, height: H, bitrate: 10_000_000, framerate: FPS });

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
  const recorder = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: 10_000_000 });
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
  const trackEl = root.querySelector('[data-reel-track]');
  const volumeEl = root.querySelector('[data-reel-volume]');
  const items = reelList(C.kit, C.posts);
  let busy = false;
  let playing = null;

  const musicRows = () => {
    fileEl?.closest('[data-reel-file-row]')?.classList.toggle('hidden', musicEl?.value !== 'file');
    trackEl?.closest('[data-reel-track-row]')?.classList.toggle('hidden', musicEl?.value !== 'library');
  };
  musicRows();
  musicEl?.addEventListener('change', musicRows);
  const music = () => ({ mode: musicEl?.value ?? 'auto', file: fileEl?.files?.[0] ?? null, url: trackEl?.value ?? '', volume: Number(volumeEl?.value ?? 100) / 100 });
  const say = (text) => { if (status) status.textContent = text; };

  const build = async (item) => {
    // The kit's fonts, artwork and colours first (social-kit.js), so no reel is built without them.
    await C.ready;
    await loadBrandFonts();
    await document.fonts?.ready;
    const own = typeof item.colors === 'function' ? item.colors() : item.colors;
    return prepare(item, C.kit, own ? { ...C.colors, ...own } : C.colors, C.style());
  };

  const stop = () => {
    if (!playing) return;
    playing.cancelled = true;
    playing.source?.stop();
    playing.ac?.close();
    playing = null;
  };

  const play = async (item, canvas = preview) => {
    stop();
    stopLive();
    const run = { cancelled: false, key: item.key };
    playing = run;
    say(`Getting “${item.title}” ready…`);
    const reel = await build(item);
    const audio = await soundtrack(reel, music());
    if (run.cancelled) return;
    const ctx = canvas.getContext('2d');
    canvas.width = W;
    canvas.height = H;
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
      else { say(`“${item.title}”: ${fmt(reel.duration)}. Make the MP4 to download it.`); playing = null; startLive(); }
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
    stopLive();
    root.querySelectorAll('[data-reel-make], [data-reel-all]').forEach((b) => { b.disabled = true; });
    try {
      await work();
    } catch (e) {
      say(`That didn't work: ${e?.message ?? e}. Try again, or in Chrome on a computer.`);
    } finally {
      busy = false;
      root.querySelectorAll('[data-reel-make], [data-reel-all]').forEach((b) => { b.disabled = false; });
      startLive();
    }
  };

  // One reel per row: its preview on the left; its hook words, colours, music mood and pictures
  // on the right, kept in this browser per event and reel (like the posts above).
  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  };
  // The studio's list of reels on the left; the chosen reel's preview and edits on the right.
  const rail = root.querySelector('[data-reel-rail]');
  const rows = [];
  const railButtons = [];
  // The chosen reel plays by itself, silently and on a loop, while the reels tab is open, and is
  // rebuilt a moment after every edit, so a change shows straight away. "Play with music" plays it once with music.
  const byKey = new Map();
  let current = null;
  let live = null;
  const panelOpen = () => !root.hidden && root.closest('dialog')?.open !== false;
  const stopLive = () => {
    if (!live) return;
    live.cancelled = true;
    cancelAnimationFrame(live.raf);
    live = null;
  };
  const startLive = async () => {
    stopLive();
    const entry = current && byKey.get(current);
    if (!entry || !panelOpen() || playing) return;
    const run = { cancelled: false, raf: 0 };
    live = run;
    entry.status.textContent = 'Getting the preview ready…';
    let reel;
    try {
      reel = await build(entry.item);
    } catch (e) {
      entry.status.textContent = `Preview failed: ${e?.message ?? e}`;
      return;
    }
    if (run.cancelled) return;
    entry.status.textContent = `${fmt(reel.duration)} · ${REEL_TEMPLATES[reel.template]?.label ?? 'Reel'} · live preview, no sound`;
    const ctx = entry.canvas.getContext('2d');
    entry.canvas.width = W;
    entry.canvas.height = H;
    const t0 = performance.now();
    const tick = () => {
      if (run.cancelled) return;
      drawFrame(ctx, reel, ((performance.now() - t0) / 1000) % reel.duration);
      run.raf = requestAnimationFrame(tick);
    };
    tick();
  };
  // Every edit replays the chosen reel from its start, with the edit in: with music when the music is
  // what changed (`sound`) or when it was playing with music, else the silent loop. Not while a video is made.
  let soon = 0;
  const replay = (key, sound = false) => {
    if (key !== current || busy) return;
    clearTimeout(soon);
    soon = setTimeout(() => {
      const entry = byKey.get(key);
      if (!entry || key !== current || busy || !panelOpen()) return;
      if (sound || playing?.key === key) play(entry.item, entry.canvas).catch((e) => say(`Preview failed: ${e?.message ?? e}`));
      else startLive();
    }, 350);
  };
  const pickReel = (key) => {
    current = key;
    rows.forEach((row) => { row.hidden = row.dataset.reel !== key; });
    railButtons.forEach((b) => b.setAttribute('aria-current', String(b.dataset.reel === key)));
    startLive();
  };
  document.addEventListener('studio:tab', (e) => { if (e.detail === 'reels') startLive(); else { stopLive(); stop(); } });
  items.forEach((item) => {
    const store = `cb-social:${C.kit.event.slug}:reel:${item.key}`;
    const saved = (() => { try { return JSON.parse(localStorage.getItem(store) ?? 'null') ?? {}; } catch { return {}; } })();
    const row = el('li', 'grid h-full min-h-0 lg:grid-cols-[minmax(0,1fr)_24rem]');
    row.dataset.reel = item.key;
    const canvas = el('canvas', 'block aspect-[9/16] h-[calc(100dvh-11rem)] max-h-full w-auto max-w-full rounded-xl border border-line bg-paper-soft shadow-lg');
    canvas.width = 1080;
    canvas.height = 1920;
    canvas.setAttribute('aria-label', `${item.title} preview`);
    const side = el('div', 'min-h-0 space-y-4 overflow-y-auto border-l border-line bg-white p-4');
    const name = el('span', 'block font-display text-lg font-bold', item.title);

    // Template, animation and effects: the reel's own by default (its place in the list), or picked here.
    const tplSel = el('select', 'cb-input mt-1');
    [['', `Its own (${REEL_TEMPLATES[v2TemplateOf({ ...item, template: null }, C.kit.event.seed)].label})`], ...Object.entries(REEL_TEMPLATES).map(([key, t]) => [key, t.label])].forEach(([value, label]) => {
      const o = el('option', '', label);
      o.value = value;
      tplSel.append(o);
    });
    tplSel.value = saved.template ?? '';
    const tplLabel = el('label', 'cb-label block', 'Template');
    tplLabel.append(tplSel);
    const animSel = el('select', 'cb-input mt-1');
    [['', 'The template\'s'], ...Object.entries(REEL_ANIMS)].forEach(([value, label]) => {
      const o = el('option', '', label);
      o.value = value;
      animSel.append(o);
    });
    animSel.value = saved.anim ?? '';
    const animLabel = el('label', 'cb-label block', 'Animation');
    animLabel.append(animSel);
    const fxRow = el('div', 'flex flex-wrap gap-x-4 gap-y-2 text-sm');
    const fxNames = { glow: 'Glow', dots: 'Dots', waves: 'Waves', motif: 'Topic motif', eq: 'Equaliser', sparks: 'Sparks' };
    const tplNow = () => REEL_TEMPLATES[v2TemplateOf({ ...item, template: tplSel.value || null }, C.kit.event.seed)];
    const fxEls = Object.entries(fxNames).map(([key, label]) => {
      const input = el('input', 'cb-check');
      input.type = 'checkbox';
      input.checked = saved.effects?.[key] ?? Boolean(tplNow().effects[key]);
      input.dataset.key = key;
      const lab = el('label', 'flex items-center gap-2');
      lab.append(input, ` ${label}`);
      fxRow.append(lab);
      return input;
    });
    let ownFx = Boolean(saved.effects);

    const hook = el('input', 'cb-input mt-1');
    hook.type = 'text';
    hook.maxLength = 70;
    hook.placeholder = 'The words of the first two seconds (*word* = highlighted)';
    hook.value = saved.hook ?? '';
    const hookLabel = el('label', 'cb-label block', 'Hook (opening words)');
    hookLabel.append(hook);

    const own = el('input', 'cb-check');
    own.type = 'checkbox';
    own.checked = Boolean(saved.ownColors);
    const ownLabel = el('label', 'flex items-center gap-2 text-sm');
    ownLabel.append(own, ' Own colours for this reel');
    const swatches = el('div', 'flex flex-wrap items-end gap-3');
    const colorEls = ['primary', 'secondary', 'ink', 'paper'].map((key) => {
      const input = el('input', 'h-9 w-14 cursor-pointer rounded border border-line');
      input.type = 'color';
      input.value = saved.colors?.[key] ?? C.colors[key];
      input.dataset.key = key;
      const label = el('label', 'flex flex-col gap-1 text-xs text-muted', { primary: 'Main', secondary: 'Accent', ink: 'Text', paper: 'Light' }[key]);
      label.append(input);
      swatches.append(label);
      return input;
    });

    const mood = el('select', 'cb-input mt-1 w-auto');
    [['', 'Picked for this reel'], ['bright', 'Bright'], ['drive', 'Driving'], ['warm', 'Warm'], ['soft', 'Soft (no drums)']].forEach(([value, label]) => {
      const o = el('option', '', label);
      o.value = value;
      mood.append(o);
    });
    mood.value = saved.mood ?? '';
    const moodLabel = el('label', 'cb-label block', 'Music mood');
    moodLabel.append(mood);

    const pics = el('input', 'cb-check');
    pics.type = 'checkbox';
    pics.checked = saved.pictures ?? true;
    const picsLabel = el('label', 'flex items-center gap-2 text-sm');
    picsLabel.append(pics, ' Photos and logos (off: initials and names)');

    // What the reel is made with: these edits on top of the page's.
    const apply = () => {
      item.hook = hook.value;
      item.colors = own.checked ? Object.fromEntries(colorEls.map((i) => [i.dataset.key, i.value])) : null;
      item.moodOverride = mood.value || null;
      item.noPictures = !pics.checked;
      item.template = tplSel.value || null;
      item.anim = animSel.value || null;
      item.effects = ownFx ? Object.fromEntries(fxEls.map((i) => [i.dataset.key, i.checked])) : null;
    };
    const keep = () => {
      apply();
      try {
        localStorage.setItem(store, JSON.stringify({
          hook: hook.value, ownColors: own.checked, colors: Object.fromEntries(colorEls.map((i) => [i.dataset.key, i.value])), mood: mood.value, pictures: pics.checked,
          template: tplSel.value, anim: animSel.value, effects: ownFx ? item.effects : undefined,
        }));
      } catch {
        // Private mode: the edits last this visit.
      }
    };
    apply();
    [hook, ...colorEls].forEach((i) => i.addEventListener('input', keep));
    [own, mood, pics, animSel].forEach((i) => i.addEventListener('change', keep));
    fxEls.forEach((i) => i.addEventListener('change', () => { ownFx = true; keep(); }));
    // A new template brings its own effects, unless they were set by hand.
    tplSel.addEventListener('change', () => {
      if (!ownFx) fxEls.forEach((i) => { i.checked = Boolean(tplNow().effects[i.dataset.key]); });
      keep();
    });

    const buttons = el('span', 'flex flex-wrap gap-2 border-t border-line pt-4');
    const playBtn = el('button', 'cb-btn cb-btn-secondary cb-btn-sm', 'Play with music');
    playBtn.type = 'button';
    playBtn.addEventListener('click', () => play(item, canvas).catch((e) => say(`Preview failed: ${e?.message ?? e}`)));
    const makeBtn = el('button', 'cb-btn cb-btn-primary cb-btn-sm', 'Make video');
    makeBtn.type = 'button';
    makeBtn.dataset.reelMake = item.key;
    makeBtn.addEventListener('click', () => guard(makeBtn, async () => {
      say(`Making “${item.title}”…`);
      const out = await make(item, (p) => say(`Making “${item.title}”… ${Math.round(p * 100)} %`));
      save(out.blob, out.name);
      say(`“${item.title}” downloaded (${(out.blob.size / 1048576).toFixed(1)} MB)${out.silent ? ', without music: this browser would not play sound here' : ''}.`);
    }));
    buttons.append(playBtn, makeBtn);
    side.append(
      name,
      tplLabel,
      animLabel,
      el('p', 'text-xs font-semibold uppercase tracking-wide text-muted', 'Effects'),
      fxRow,
      hookLabel,
      el('p', 'text-xs font-semibold uppercase tracking-wide text-muted', 'Colours'),
      ownLabel,
      swatches,
      moodLabel,
      picsLabel,
      buttons,
    );
    const left = el('div', 'flex min-h-0 flex-col items-center justify-center gap-2 bg-paper-soft p-4');
    const liveStatus = el('p', 'text-xs text-muted', '');
    left.append(canvas, liveStatus);
    row.append(left, side);
    rows.push(row);
    listEl.append(row);
    byKey.set(item.key, { item, canvas, status: liveStatus });
    [hook, ...colorEls].forEach((i) => i.addEventListener('input', () => replay(item.key)));
    [own, pics, animSel, tplSel, ...fxEls].forEach((i) => i.addEventListener('change', () => replay(item.key)));
    mood.addEventListener('change', () => replay(item.key, true));

    const li = el('li', 'shrink-0');
    const b = el('button', 'flex w-48 flex-col rounded-lg px-3 py-2 text-left hover:bg-paper-soft aria-[current=true]:bg-maroon/10 lg:w-full');
    b.type = 'button';
    b.dataset.reel = item.key;
    b.append(el('span', 'truncate text-sm font-semibold text-ink', item.title), el('span', 'truncate text-xs text-muted', REEL_TEMPLATES[v2TemplateOf(item, C.kit.event.seed)].label));
    b.addEventListener('click', () => { stop(); pickReel(item.key); });
    railButtons.push(b);
    li.append(b);
    rail?.append(li);
  });
  if (items[0]) pickReel(items[0].key);
  // The music: another choice, track, file or volume replays the chosen reel with it (the volume once it
  // is let go; "My own track" once a file is chosen).
  [musicEl, trackEl, fileEl, volumeEl].forEach((input) => input?.addEventListener('change', () => {
    if (musicEl?.value === 'file' && !fileEl?.files?.[0]) return;
    replay(current, true);
  }));
  // Closing the studio stops a preview that is playing, and the live loop.
  document.addEventListener('studio:close', () => { stop(); stopLive(); });


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

  root.querySelector('[data-reel-stop]')?.addEventListener('click', () => { stop(); say('Stopped.'); startLive(); });
  canEncode().then((ok) => {
    if (!ok) say('This browser records reels in real time (as long as each reel): keep this tab in front. Chrome on a computer makes them faster.');
  });
}

// For tests.
export const _internals = { prepare, drawFrame, makeMusic, fitAll, MOODS, PROGRESSIONS, reelBpm, MIN_SECONDS, MAX_SECONDS, SAFE, TEXT_W, badgeY, context: C, tools: T, postContent, v2Scenes, v2TemplateOf, REEL_TEMPLATES, topicIconFor, TOPIC_ICONS };
