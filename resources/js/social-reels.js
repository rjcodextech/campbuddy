// Reels for an event's social media (Social media page → Reels): short
// 1080 × 1920 videos with animation and music, made in the browser.
//
//   - one reel per post (the post's cards, as they are on the page),
//   - one reel each for the organizers, volunteers, media partners and table
//     leads, and one per five speakers.
//
// Every reel: an intro (the event, "CampBuddy × WPSimplified"), its scenes and
// an end card (the event's QR in CampBuddy, WPSimplified's credit). It follows
// the event's look: its colours, fonts, pattern and skyline (social-kit.js).
// Nothing is ever cropped: photos, cards and artwork are drawn whole.
//
// Music is made here too (no copyright claims): a short track whose beats
// land on the cuts, its mood picked per reel; or the admin's own track.
//
// The video is encoded with WebCodecs into an MP4 (H.264 + AAC, mp4-muxer),
// faster than real time and frame exact. Where the browser can't, it is
// recorded in real time instead (MediaRecorder).

import { ArrayBufferTarget, Muxer } from 'mp4-muxer';
import { drawEventPost, planPost, postContent, qrLink, reelTools as T, socialContext as C } from './social-kit.js';
import { zipStore } from './zip-store.js';

const W = 1080;
const H = 1920;
const FPS = 30;
const RATE = 48000;
const SPEAKERS_PER_REEL = 5;
// More people than this and a reel shows them six at a time instead of one by one.
const ONE_BY_ONE = 6;
const GRID = 6;

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
function risingLines(ctx, fit, y, color, t, start, { weight = 800, family, lh = 1.12, align = 'center', x = W / 2, stagger = 0.08, maxWidth = W - 120 } = {}) {
  ctx.textAlign = align;
  ctx.fillStyle = color;
  fit.lines.forEach((line, i) => {
    const p = ease(span(t, start + i * stagger, 0.5));
    ctx.save();
    ctx.globalAlpha *= p;
    ctx.font = `${weight} ${fit.size}px ${family}`;
    ctx.fillText(line, x, y + (i + 1) * fit.size * lh + (1 - p) * 40, maxWidth);
    ctx.restore();
  });
  ctx.textAlign = 'left';
  return y + fit.lines.length * fit.size * lh;
}

function pillAt(ctx, text, cx, y, bg, fg, size = 34, p = 1) {
  const { text: font } = T.fonts();
  ctx.save();
  ctx.font = `800 ${size}px ${font}`;
  const w = Math.min(W - 120, ctx.measureText(text).width + size * 1.4);
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
function photoFrame(ctx, img, name, x, y, size, ring, fill, ink, reveal = 1) {
  ctx.save();
  ctx.fillStyle = ring;
  T.roundRect(ctx, x - 10, y - 10, size + 20, size + 20, 52);
  ctx.fill();
  ctx.fillStyle = fill;
  T.roundRect(ctx, x, y, size, size, 44);
  ctx.fill();
  T.roundRect(ctx, x, y, size, size, 44);
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

  if (art.patternTile) {
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

function header(ctx, reel, t, label) {
  const p = ease(span(t, 0, 0.5));
  ctx.save();
  ctx.globalAlpha *= p;
  if (reel.logo) logoCard(ctx, reel, 64, 72, 170, 30);
  if (label) {
    const { text } = T.fonts();
    ctx.font = `800 30px ${text}`;
    const w = ctx.measureText(label).width + 48;
    ctx.fillStyle = reel.pal.primary;
    T.roundRect(ctx, W - 64 - w, 126, w, 62, 31);
    ctx.fill();
    ctx.fillStyle = reel.pal.onPrimary;
    ctx.textAlign = 'right';
    ctx.fillText(label, W - 88, 168);
    ctx.textAlign = 'left';
  }
  ctx.restore();
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
      const { pal, kit } = reel;
      const { head, text } = T.fonts();
      ctx.save();
      leaving(ctx, t, dur);

      // The event's logo pops in.
      const pop = back(span(t, 0.05, 0.6));
      ctx.save();
      ctx.globalAlpha *= clamp(pop);
      ctx.translate(W / 2, 520);
      ctx.scale(0.4 + 0.6 * pop, 0.4 + 0.6 * pop);
      if (reel.logo) logoCard(ctx, reel, -190, -190, 380, 56);
      ctx.restore();

      let y = pillAt(ctx, kicker, W / 2, 780, pal.primary, pal.onPrimary, 36, ease(span(t, 0.3, 0.4)));
      const name = fitAll(ctx, kit.event.name, W - 160, 124, 70, 3, 800, head);
      y = risingLines(ctx, name, y + 20, pal.ink, t, 0.45, { family: head });
      ctx.save();
      ctx.globalAlpha *= ease(span(t, 0.9, 0.5));
      ctx.fillStyle = T.mix(pal.ink, pal.paper, 0.3);
      ctx.textAlign = 'center';
      const line = [kit.event.dates, kit.event.place].filter(Boolean).join(' · ');
      const sub = fitAll(ctx, line, W - 200, 46, 30, 2, 600, text);
      ctx.font = `600 ${sub.size}px ${text}`;
      sub.lines.forEach((l, i) => ctx.fillText(l, W / 2, y + 70 + i * sub.size * 1.25));
      ctx.restore();

      // "CampBuddy × WPSimplified", small, at the foot.
      partnersBadge(ctx, reel, H - 330, ease(span(t, 1.1, 0.5)));
      ctx.restore();
    },
  };
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
    draw(ctx, t, dur, reel) {
      const s = 0.84;
      const w = W * s;
      const h = H * s;
      const enter = back(span(t, 0, 0.75));
      const out = last ? 0 : ease(span(t, dur - 0.4, 0.4));
      const scale = 0.72 + (1 - 0.72) * enter;
      ctx.save();
      ctx.globalAlpha = clamp(span(t, 0, 0.25)) * (last ? 1 - span(t, dur - 0.3, 0.3) : 1);
      ctx.translate(W / 2 - out * 1300, H / 2 + 34 + (1 - enter) * 280 + Math.sin(t * 1.6) * 6);
      ctx.rotate(((1 - enter) * -4 + out * 6) * (Math.PI / 180));
      ctx.scale(scale, scale);
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
          ctx.arc(W / 2 + (i - (total - 1) / 2) * 34, H - 52, i === index ? 11 : 8, 0, Math.PI * 2);
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
      const titleFit = fitAll(ctx, title, W - 160, 140, 72, 2, 900, head);
      let y = risingLines(ctx, titleFit, 640 + (520 - titleFit.lines.length * titleFit.size * 1.1) / 2 - 20, T.textOn(tone, pal.ink, pal.paper), t, 0.3, { weight: 900, family: head, lh: 1.1 });
      y = 1200;
      const lineFit = fitAll(ctx, line, W - 200, 54, 32, 3, 700, text);
      y = risingLines(ctx, lineFit, y, pal.ink, t, 0.7, { weight: 700, family: text, lh: 1.25 });
      pillAt(ctx, count, W / 2, y + 50, pal.secondary, pal.onSecondary, 36, ease(span(t, 1.0, 0.4)));
      ctx.restore();
    },
  };
}

function personScene(person, index, total, long, role) {
  return {
    beats: long ? 8 : 5,
    draw(ctx, t, dur, reel) {
      const { pal, colors, kit } = reel;
      const { head, text } = T.fonts();
      const tone = T.roleColor({ ...person, roles: [role] }, colors);
      const img = reel.photos.get(person.id);
      ctx.save();
      header(ctx, reel, t, `${index + 1} / ${total}`);
      leaving(ctx, t, dur);

      const size = long ? 600 : 640;
      const pop = back(span(t, 0, 0.6));
      ctx.save();
      ctx.globalAlpha *= clamp(span(t, 0, 0.2));
      ctx.translate(W / 2, 320 + size / 2);
      ctx.scale(0.6 + 0.4 * pop, 0.6 + 0.4 * pop);
      photoFrame(ctx, img, person.name, -size / 2, -size / 2, size, tone, T.mix(tone, pal.paper, 0.82), tone, span(t, 0.1, 0.6));
      ctx.restore();

      let y = 320 + size + 40;
      const name = fitAll(ctx, person.name, W - 140, 96, 54, 2, 800, head);
      y = risingLines(ctx, name, y, pal.ink, t, 0.35, { family: head });
      const roles = (person.role_labels ?? []).join(' · ');
      if (roles) y = pillAt(ctx, roles, W / 2, y + 30, tone, T.textOn(tone, pal.ink, pal.paper), 34, ease(span(t, 0.6, 0.4)));

      const detail = person.talk ? `“${person.talk}”` : [person.card?.role, person.card?.company].filter(Boolean).join(', ');
      if (detail) {
        const fit = fitAll(ctx, detail, W - 160, person.talk ? 60 : 46, 30, person.talk ? 5 : 2, person.talk ? 700 : 600, text);
        y = risingLines(ctx, fit, y + 30, T.mix(pal.ink, pal.paper, person.talk ? 0 : 0.3), t, 0.8, { weight: person.talk ? 700 : 600, family: text, lh: 1.25, stagger: 0.06 });
      }

      ctx.save();
      ctx.globalAlpha *= ease(span(t, 1.1, 0.4));
      ctx.fillStyle = tone;
      ctx.textAlign = 'center';
      const cta = fitAll(ctx, T.personCta(person, kit), W - 200, 40, 26, 2, 700, text);
      ctx.font = `700 ${cta.size}px ${text}`;
      cta.lines.forEach((l, i) => ctx.fillText(l, W / 2, Math.max(y + 90, H - 300) + i * cta.size * 1.25));
      ctx.restore();
      ctx.restore();
    },
  };
}

function gridScene(people, first, total, role) {
  return {
    beats: 7,
    draw(ctx, t, dur, reel) {
      const { pal, colors } = reel;
      const { head } = T.fonts();
      ctx.save();
      header(ctx, reel, t, `${first + 1}–${first + people.length} of ${total}`);
      leaving(ctx, t, dur);
      const cols = 2;
      const cellW = 460;
      const photo = 290;
      const rowH = 500;
      const rows = Math.ceil(people.length / cols);
      const top = 300 + ((3 - rows) * rowH) / 2;
      people.forEach((person, i) => {
        const col = i % cols;
        const row = Math.floor(i / cols);
        // An odd one out on the last row stands in the middle.
        const alone = row === rows - 1 && people.length % cols === 1;
        const cx = alone ? W / 2 : W / 2 + (col - 0.5) * (cellW + 40);
        const y = top + row * rowH;
        const p = back(span(t, 0.1 + i * 0.12, 0.55));
        const tone = T.roleColor({ ...person, roles: [role] }, colors);
        ctx.save();
        ctx.globalAlpha *= clamp(span(t, 0.1 + i * 0.12, 0.2));
        ctx.translate(cx, y + photo / 2);
        ctx.scale(0.5 + 0.5 * p, 0.5 + 0.5 * p);
        photoFrame(ctx, reel.photos.get(person.id), person.name, -photo / 2, -photo / 2, photo, tone, T.mix(tone, pal.paper, 0.82), tone);
        ctx.restore();
        ctx.save();
        ctx.globalAlpha *= ease(span(t, 0.3 + i * 0.12, 0.4));
        ctx.fillStyle = pal.ink;
        ctx.textAlign = 'center';
        const fit = fitAll(ctx, person.name, cellW - 20, 42, 26, 2, 800, head);
        ctx.font = `800 ${fit.size}px ${head}`;
        fit.lines.forEach((l, k) => ctx.fillText(l, cx, y + photo + 28 + (k + 1) * fit.size * 1.1, cellW - 20));
        ctx.restore();
      });
      ctx.restore();
    },
  };
}

function endScene(content) {
  return {
    beats: 8,
    draw(ctx, t, dur, reel) {
      const { pal, kit } = reel;
      const { head, text } = T.fonts();
      // The main colour washes over the frame.
      const wash = ease(span(t, 0, 0.45));
      ctx.save();
      ctx.fillStyle = pal.primary;
      ctx.beginPath();
      ctx.arc(W / 2, H / 2, Math.hypot(W, H) * 0.6 * wash, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();

      const on = pal.onPrimary;
      ctx.save();
      if (reel.campbuddy) {
        const p = ease(span(t, 0.25, 0.4));
        const h = 92;
        const w = (reel.campbuddy.width / reel.campbuddy.height) * h;
        ctx.globalAlpha = p;
        ctx.fillStyle = '#ffffff';
        T.roundRect(ctx, (W - w - 60) / 2, 150, w + 60, h + 40, 28);
        ctx.fill();
        ctx.drawImage(reel.campbuddy, (W - w) / 2, 170, w, h);
        ctx.globalAlpha = 1;
      }
      const head1 = fitAll(ctx, 'Find it all in CampBuddy', W - 160, 92, 56, 2, 900, head);
      let y = risingLines(ctx, head1, 340, on, t, 0.35, { weight: 900, family: head });
      const sub = fitAll(ctx, `${kit.event.name}: the schedule, people to meet and reminders. Free, no sign-up.`, W - 200, 44, 28, 3, 600, text);
      y = risingLines(ctx, sub, y + 10, on, t, 0.55, { weight: 600, family: text, lh: 1.3 });

      // The QR pops in, whole, on its light tile.
      const q = back(span(t, 0.6, 0.6));
      const qs = 440;
      ctx.save();
      ctx.globalAlpha *= clamp(span(t, 0.6, 0.2));
      ctx.translate(W / 2, y + 60 + qs / 2);
      ctx.scale(0.5 + 0.5 * q, 0.5 + 0.5 * q);
      T.drawQr(ctx, qrLink(kit.event.qr, `reel-${content}`), -qs / 2, -qs / 2, qs, pal.ink, pal.paper);
      ctx.restore();
      y += 60 + qs + 30;
      ctx.save();
      ctx.globalAlpha *= ease(span(t, 0.9, 0.4));
      ctx.fillStyle = on;
      ctx.textAlign = 'center';
      ctx.font = `800 44px ${text}`;
      ctx.fillText('Scan, or open campbuddy.club', W / 2, y + 30);
      const tags = (kit.event.hashtags ?? '').trim();
      if (tags) {
        ctx.font = `600 34px ${text}`;
        ctx.globalAlpha *= 0.85;
        ctx.fillText(tags, W / 2, y + 86, W - 160);
      }
      ctx.restore();

      // WPSimplified's credit.
      const c = ease(span(t, 1.2, 0.5));
      if (c > 0) {
        ctx.save();
        ctx.globalAlpha = c;
        const bw = 760;
        const bh = 230;
        const bx = (W - bw) / 2;
        const by = H - 330 + (1 - c) * 30;
        ctx.fillStyle = '#ffffff';
        T.roundRect(ctx, bx, by, bw, bh, 34);
        ctx.fill();
        ctx.fillStyle = '#3a3a3a';
        ctx.textAlign = 'center';
        ctx.font = `600 32px ${text}`;
        ctx.fillText('Made with ♥ for the WordPress community by', W / 2, by + 62);
        if (reel.wps) {
          const lh = 104;
          const lw = (reel.wps.width / reel.wps.height) * lh;
          ctx.drawImage(reel.wps, (W - lw) / 2, by + 92, lw, lh);
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
  return list;
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
  const reel = { item, kit, colors, pal, scenes: [], photos: new Map(), art: {}, dots: [] };

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
  const rnd = T.seeded(kit.event.seed);
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
  reel.scenes.push(endScene(spec.utm));

  // Every scene lasts whole beats, so the cuts fall on the music.
  const mood = MOODS[item.mood] ?? MOODS.bright;
  const beat = 60 / mood.bpm;
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
  const scene = reel.scenes.findLast((s) => s.start <= time) ?? reel.scenes[0];
  ctx.save();
  scene.draw(ctx, time - scene.start, scene.dur, reel);
  ctx.restore();
}

// ---- Music -------------------------------------------------------------------

const MOODS = {
  bright: { bpm: 112, chords: [[0, 'maj'], [7, 'maj'], [9, 'min'], [5, 'maj']], kick: 'half', snare: true, hats: 8 },
  drive: { bpm: 120, chords: [[9, 'min'], [5, 'maj'], [0, 'maj'], [7, 'maj']], kick: 'four', snare: true, hats: 8 },
  warm: { bpm: 98, chords: [[0, 'maj'], [9, 'min'], [5, 'maj'], [7, 'maj']], kick: 'half', snare: true, hats: 4 },
  soft: { bpm: 84, chords: [[0, 'maj'], [4, 'min'], [5, 'maj'], [7, 'maj']], kick: 'one', snare: false, hats: 0 },
};

const KEYS = [57, 55, 53, 60, 62, 58];

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
  const beat = 60 / mood.bpm;
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
  const rnd = T.seeded(reel.kit.event.seed);
  for (let i = 0; i < nd.length; i++) nd[i] = rnd() * 2 - 1;

  const key = KEYS[(reel.kit.event.seed >>> 0) % KEYS.length] - 12;
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
    const [step, kind] = mood.chords[bar % mood.chords.length];
    const notes = chordNotes(key + 12 + step, kind);
    const playing = at >= intro - 0.01 && at < end - 0.01;

    // Pad: the chord, held for the bar.
    if (inBar === 0) {
      notes.forEach((n) => {
        tone('sawtooth', hz(n), at, beat * 4.2, 0.022, { attack: 0.35, cutoff: 1300, detune: -7 });
        tone('sawtooth', hz(n), at, beat * 4.2, 0.022, { attack: 0.35, cutoff: 1300, detune: 7 });
      });
    }
    // Bass on the beat (on every beat when it drives), from the drop on.
    if (playing && (mood.kick === 'four' || inBar % 2 === 0)) tone('triangle', hz(key + step - 12 + 12), at, beat * 0.9, 0.2, { cutoff: 500 });
    // A plucked arpeggio in eighths.
    for (let e = 0; e < 2; e++) {
      const n = notes[(inBar * 2 + e) % 3] + 12 + (e && inBar === 3 ? 12 : 0);
      tone('triangle', hz(n), at + e * beat / 2, 0.28, at < intro ? 0.05 : 0.065);
    }
    if (playing) {
      if (mood.kick === 'four' || (mood.kick === 'half' && inBar % 2 === 0) || (mood.kick === 'one' && inBar === 0)) kick(at, mood.kick === 'one' ? 0.5 : 0.85);
      if (mood.snare && inBar % 2 === 1) hit(at, 0.18, 0.3, 'bandpass', 1800, 0.8);
      for (let h = 0; h < mood.hats / 4; h++) hit(at + (h * beat * 4) / mood.hats, 0.05, 0.08, 'highpass', 7000);
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
  chordNotes(key + 12, 'maj').forEach((n) => tone('sine', hz(n + 12), end, Math.max(1, reel.duration - end), 0.05, { attack: 0.05 }));

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
export const _internals = { prepare, drawFrame, makeMusic, fitAll, MOODS, context: C, tools: T, postContent };
