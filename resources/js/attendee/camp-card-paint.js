// Drawing a Camp Card (components/_camp-card.scss markup) from its content,
// and turning it into the print PNG — shared by the Camp Card page
// (camp-card.js) and a person's card on the attendee list (person-card.js),
// so both are the same design at the same 900 DPI.

import { withPngDpi } from './png-dpi.js';
import { render } from './template.js';

// Share/Download export a 3 × 5 in card at 900 DPI (2700 × 4500 px) —
// print-shop quality. The card is laid out at 300 DPI size (900 px wide)
// and captured at 3×, so its design is identical to the on-screen one;
// only the pixel density triples. The height follows from the 3:5 shape.
const PRINT_WIDTH_IN = 3;
const LAYOUT_DPI = 300;
export const EXPORT_SCALE = 3;
export const PRINT_DPI = LAYOUT_DPI * EXPORT_SCALE;
const EXPORT_WIDTH_PX = PRINT_WIDTH_IN * LAYOUT_DPI;

// Pixel budget for the QR canvas: plenty for its ~80px on-screen size;
// in the export it covers ~230 layout px, i.e. ~690 real pixels at 3× —
// drawn at that size directly so it's never scaled (and blurred) up.
export const QR_PREVIEW_PX = 320;
export const QR_EXPORT_PX = 260 * EXPORT_SCALE;

// Renders one card to a canvas at print size via html2canvas — shared by
// Download and Share (and by a person's card in Explore, person-card.js) so
// every export is the same PNG.
//
// Not a screenshot of the on-screen card: a clone of it is laid out on an
// off-screen stage EXPORT_WIDTH_PX wide (its type/spacing scale with the
// card, so it's the same design, just bigger) and repainted there from
// the same content — which also re-trims the tag list at this size and
// redraws the QR at print resolution, since a cloned <canvas> comes
// across blank.
async function renderCardToCanvas(cardEl, content, qr) {
  // Web fonts have to be in before anything is measured or drawn, or the
  // capture is laid out with fallback-font metrics.
  await document.fonts?.ready;

  const stage = document.createElement('div');
  stage.className = 'camp-card-export';
  stage.style.width = `${EXPORT_WIDTH_PX}px`;

  const clone = cardEl.cloneNode(true);
  stage.appendChild(clone);
  document.body.appendChild(stage);

  try {
    await paintCard(clone, content, qr, QR_EXPORT_PX);
    forExport(clone);

    const { default: html2canvas } = await import('html2canvas');
    return await html2canvas(clone, { backgroundColor: null, scale: EXPORT_SCALE, useCORS: true, logging: false });
  } finally {
    stage.remove();
  }
}

// On-screen hints never reach a file or a printer: the "Nothing chosen to
// show yet" pill is for the person editing, not for the people they meet.
function forExport(card) {
  card.querySelectorAll('.camp-card__tag--placeholder').forEach((el) => el.remove());
}

// The PNG itself, stamped with its real DPI: canvas.toBlob() records none,
// so without this an editor or print dialog would treat the 2700 × 4500 px
// image as 72 DPI (a 25 × 41.7 in print) instead of 3 × 5 in.
export async function cardPng(cardEl, content, qr) {
  const canvas = await renderCardToCanvas(cardEl, content, qr);
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
  return withPngDpi(blob, PRINT_DPI);
}

export function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.download = filename;
  link.href = url;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 10000);
}

// Does the tap that started this still let us download / open the share sheet?
export function tapStillCounts() {
  return navigator.userActivation ? navigator.userActivation.isActive : false;
}

// What a card displays, independent of which layout is showing it.
export function cardContent(display) {
  const visibleFields = display.visibleFields ?? [];

  // Role and company get their own lines — shown only if chosen (CC2),
  // and left out of the tag pills below so they're never shown twice on
  // the same card. A line each, so a long pair never wraps with a "·"
  // left hanging at the end of a line.
  const roleLines = [
    visibleFields.includes('role') && display.role,
    visibleFields.includes('company') && display.company,
  ].filter(Boolean);
  const roleLine = roleLines.join(' · ');

  // "Interests" expands into one pill per tag rather than a single
  // combined blob — the rest of visibleFields (excluding role/company,
  // already on their own line above) render as one pill each.
  const tags = [];
  const tagsBesideAsk = [];
  visibleFields.forEach((f) => {
    if (f === 'interests') {
      (display.interests ?? []).forEach((tag) => { tags.push(tag); tagsBesideAsk.push(tag); });
    } else if (f !== 'role' && f !== 'company' && display[f]) {
      tags.push(display[f]);
      if (f !== 'askMeAbout') tagsBesideAsk.push(display[f]);
    }
  });

  // Ticket shows "Ask me about" in its own bubble (data-ask-bubble), so
  // there it is left out of the pills; every other layout keeps it a pill.
  const askMe = visibleFields.includes('askMeAbout') ? (display.askMeAbout ?? '') : '';

  return { name: display.name, roleLine, roleLines, tags, askMe, tagsBesideAsk };
}

// Fills one .camp-card element (an on-screen card, or the clone being
// exported) from `content`; resolves once its QR — including the event
// logo drawn over its centre — is fully drawn.
export async function paintCard(card, content, qr, qrPixels = QR_PREVIEW_PX) {
  card.querySelector('.camp-card__name').textContent = content.name;
  card.querySelector('.camp-card__role').replaceChildren(...(content.roleLines ?? []).map((text) => {
    const line = document.createElement('span');
    line.textContent = text;
    return line;
  }));
  card.querySelector('.camp-card__scan').textContent = content.scan ?? '';
  // Size the name to the card's content (see .camp-card__name's --name-scale).
  card.classList.toggle('camp-card--long-name', (content.name ?? '').length > 20);
  card.classList.toggle('camp-card--name-only', !content.roleLine && content.tags.length === 0 && (content.name ?? '').length <= 20);
  card.querySelector('.camp-card__footer').hidden = !qr;

  const bubble = card.hasAttribute('data-ask-bubble') && content.askMe ? content.askMe : '';
  const tags = card.hasAttribute('data-ask-bubble') ? (content.tagsBesideAsk ?? content.tags) : content.tags;
  paintAsk(card, bubble);
  paintTags(card, tags, !bubble);

  // The logos above the tags change how much room they have once they
  // load (Pass's is sized by its own aspect ratio), so trim again then —
  // otherwise a card can end up with pills spilling past its footer.
  await imagesLoaded(card);
  paintTags(card, tags, !bubble);

  if (qr) {
    await drawQrToCanvas(qr, card.querySelector('.camp-card__qr-frame canvas'), card.dataset.eventIcon, qrPixels);
  }
}

// "Ask me about Block themes" in Ticket's speech bubble; empty (and hidden) elsewhere.
function paintAsk(card, askMe) {
  const ask = card.querySelector('.camp-card__ask');
  if (!ask) return;
  // Hidden outright, not only by CSS :empty — html2canvas turns the bubble's
  // ::after tail into a real element in its copy, so an "empty" bubble
  // wasn't empty there and came out as a blank wine blob in the PNG.
  ask.hidden = !askMe;
  if (!askMe) {
    ask.replaceChildren();
    return;
  }
  const topic = document.createElement('strong');
  topic.textContent = askMe;
  ask.replaceChildren('Ask me about ', topic);
}

function imagesLoaded(root) {
  return Promise.all(
    [...root.querySelectorAll('img')].map((img) =>
      img.complete
        ? null
        : new Promise((resolve) => {
            img.addEventListener('load', resolve, { once: true });
            img.addEventListener('error', resolve, { once: true });
          })
    )
  );
}

// How far a busy card steps its type down to fit, pills first, then the
// role and the name: [pills, role/name] scales, tried in order until the
// content fits the card (_camp-card.scss reads --fit-tags / --fit-text).
const FIT_STEPS = [
  [1, 1], [0.92, 1], [0.84, 1], [0.76, 1],
  [0.76, 0.92], [0.7, 0.86], [0.66, 0.8], [0.62, 0.74],
];

// The card is a fixed size, so a long tag list can't be allowed to grow
// it: every pill shows, and the type steps down until it all fits.
// withPlaceholder: false when the card has something else to show instead
// (Ticket's "Ask me about" bubble), so no "Nothing chosen" pill sits beside it.
function paintTags(card, tags, withPlaceholder = true) {
  const body = card.querySelector('.camp-card__body');
  const tagsEl = card.querySelector('.camp-card__tags');
  const pill = (text) => render('tpl-camp-card-tag', { tag: text });

  tagsEl.replaceChildren(...(tags.length ? tags.map(pill) : withPlaceholder ? [render('tpl-camp-card-tag-empty')] : []));

  const overflowing = () => body.scrollHeight > body.clientHeight + 1;
  for (const [pills, text] of FIT_STEPS) {
    card.style.setProperty('--fit-tags', pills);
    card.style.setProperty('--fit-text', text);
    if (!overflowing()) return;
  }

  // Past the smallest size (every field at its longest): drop pills from
  // the end rather than let a half-cut row run into the QR.
  const shown = [...tags];
  while (shown.length && overflowing()) {
    shown.pop();
    tagsEl.replaceChildren(...shown.map(pill));
  }
}

// Draws at whole pixels per module (so every module edge is crisp at any
// display size) as close to `targetPixels` as fits. When eventIconUrl is
// given, overlays it in a small white plate at dead center — safe at
// error-correction level 'H' since that plate covers well under the ~30%
// of modules 'H' can lose and still decode.
function drawQrToCanvas(qr, canvas, eventIconUrl, targetPixels) {
  const count = qr.getModuleCount();
  const cell = Math.max(1, Math.floor(targetPixels / count));
  const size = cell * count;
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = '#000';

  for (let row = 0; row < count; row++) {
    for (let col = 0; col < count; col++) {
      if (qr.isDark(row, col)) {
        ctx.fillRect(col * cell, row * cell, cell, cell);
      }
    }
  }

  if (!eventIconUrl) return Promise.resolve();

  return new Promise((resolve) => {
    const logo = new Image();
    // CORS-clean, or drawing it would taint the canvas and make the
    // export fail outright — if it can't load that way the QR just goes
    // without its logo and stays perfectly scannable.
    logo.crossOrigin = 'anonymous';
    logo.onerror = () => resolve();
    logo.onload = () => {
      const unit = size / 320;
      const plate = size * 0.24;
      const inset = (size - plate) / 2;
      const plateRadius = 10 * unit;

      ctx.fillStyle = '#fff';
      roundedRectPath(ctx, inset - 6 * unit, inset - 6 * unit, plate + 12 * unit, plate + 12 * unit, plateRadius);
      ctx.fill();

      ctx.save();
      roundedRectPath(ctx, inset, inset, plate, plate, plateRadius - 3 * unit);
      ctx.clip();
      ctx.drawImage(logo, inset, inset, plate, plate);
      ctx.restore();
      resolve();
    };
    logo.src = eventIconUrl;
  });
}

function roundedRectPath(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}
