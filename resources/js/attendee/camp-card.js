// Camp Card: local-only (CC5), attendee chooses which filled
// fields actually show (CC2), QR points at whichever link they designate
// primary (CC3). All 6 layouts render at once in a gallery
// (resources/views/attendee/camp-card.blade.php's #camp-card-scroll)
// rather than one preview behind a layout picker — each card is its own
// self-contained subtree (data-layout-card="…"), with its own
// Share/Download buttons, so nothing here relies on a single
// #camp-card-preview id.
//
// Every card is the same fixed 3:5 shape whose contents scale with its
// width (components/_camp-card.scss), so Share/Download can repaint a
// clone of it at print size and get exactly the on-screen layout.

import QRCode from 'qrcode-generator';
import { kvGet, kvSet } from './db.js';
import { withPngDpi } from './png-dpi.js';
import { render } from './template.js';

const LINK_FIELDS = ['linkedin', 'website', 'wordpressOrg', 'twitter'];
const LAYOUTS = ['classic', 'minimal', 'bold', 'split', 'badge', 'pass'];
const DEFAULT_QR_TARGET = 'linkedin';

// Share/Download export a 3 × 5 in card at 300 DPI (900 × 1500 px). The
// height follows from the card's 3:5 aspect ratio.
const PRINT_WIDTH_IN = 3;
const PRINT_DPI = 300;
const EXPORT_WIDTH_PX = PRINT_WIDTH_IN * PRINT_DPI;

// Pixel budget for the QR canvas: plenty for its ~104px on-screen size,
// and enough to stay crisp across the ~310px it covers in the export.
const QR_PREVIEW_PX = 320;
const QR_EXPORT_PX = 720;

// What every card on screen currently shows — kept so an export can
// repaint a clone of any card from the same content.
let shown = null;

const SAMPLE_CARD = {
  name: 'Jamie Rivera',
  role: 'WordPress Developer',
  company: 'Acme Studio',
  interests: ['Gutenberg', 'WooCommerce'],
  visibleFields: ['role', 'interests'],
};

// Twitter is asked as a bare handle (CC1 wording), not a full URL —
// resolved to a real link only when actually needed (QR/visible tags).
function resolveLink(field, value) {
  if (!value) return null;
  if (field === 'twitter') {
    const handle = value.trim().replace(/^@/, '');
    return handle ? `https://x.com/${handle}` : null;
  }
  return value;
}

// Interests used to be saved as a single comma-separated string before
// the tag-input UI existed — split it into tags once, on load, so an
// attendee's existing Camp Card doesn't lose its interests when this
// shipped.
function normalizeInterests(value) {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string' && value.trim()) {
    return value.split(',').map((s) => s.trim()).filter(Boolean);
  }
  return [];
}

export async function renderCampCard() {
  const form = document.getElementById('camp-card-form');
  if (!form) return;

  const card = await kvGet('campCard');
  const visibleFields = new Set(card?.visibleFields ?? []);

  if (card) {
    Object.entries(card).forEach(([key, value]) => {
      const input = form.elements.namedItem(key);
      if (input && typeof value === 'string') input.value = value;
    });
  }

  const getInterests = setupTagInput(normalizeInterests(card?.interests));

  document.querySelectorAll('[data-visible-field]').forEach((chip) => {
    if (visibleFields.has(chip.dataset.visibleField)) chip.classList.add('chip--selected');

    chip.addEventListener('click', () => {
      chip.classList.toggle('chip--selected');
    });
  });

  setupQrTargetPicker(card?.primaryLink ?? DEFAULT_QR_TARGET);
  renderAllPreviews(card ? { ...card, interests: normalizeInterests(card.interests) } : card);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = Object.fromEntries(new FormData(form).entries());
    data.interests = getInterests();
    data.visibleFields = [...document.querySelectorAll('[data-visible-field].chip--selected')].map(
      (chip) => chip.dataset.visibleField
    );
    data.primaryLink = document.querySelector('[data-qr-target].chip--selected')?.dataset.qrTarget ?? DEFAULT_QR_TARGET;

    await kvSet('campCard', data);
    renderAllPreviews(data);
    document.getElementById('cc-edit-details').open = false;
  });

  document.querySelectorAll('[data-download-card]').forEach((btn) => {
    btn.addEventListener('click', () => downloadCard(btn.dataset.downloadCard));
  });

  document.querySelectorAll('[data-share-card]').forEach((btn) => {
    btn.addEventListener('click', () => shareCard(btn.dataset.shareCard));
  });
}

function cardElement(layout) {
  return document.querySelector(`[data-layout-card="${layout}"] .camp-card`);
}

// Renders one card to a canvas at print size via html2canvas — shared by
// Download and Share so both produce the exact same PNG.
//
// Not a screenshot of the on-screen card: a clone of it is laid out on an
// off-screen stage EXPORT_WIDTH_PX wide (its type/spacing scale with the
// card, so it's the same design, just bigger) and repainted there from
// the same content — which also re-trims the tag list at this size and
// redraws the QR at print resolution, since a cloned <canvas> comes
// across blank.
async function renderCardToCanvas(layout) {
  // Web fonts have to be in before anything is measured or drawn, or the
  // capture is laid out with fallback-font metrics.
  await document.fonts?.ready;

  const stage = document.createElement('div');
  stage.className = 'camp-card-export';
  stage.style.width = `${EXPORT_WIDTH_PX}px`;

  const clone = cardElement(layout).cloneNode(true);
  stage.appendChild(clone);
  document.body.appendChild(stage);

  try {
    await paintCard(clone, shown.content, shown.qr, QR_EXPORT_PX);

    const { default: html2canvas } = await import('html2canvas');
    return await html2canvas(clone, { backgroundColor: null, scale: 1, useCORS: true, logging: false });
  } finally {
    stage.remove();
  }
}

// The PNG itself, stamped with its real DPI: canvas.toBlob() records none,
// so without this an editor or print dialog would treat the 900 × 1500 px
// image as 72 DPI (a 12.5 × 20.8 in print) instead of 3 × 5 in.
async function cardPngBlob(layout) {
  const canvas = await renderCardToCanvas(layout);
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
  return withPngDpi(blob, PRINT_DPI);
}

function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.download = filename;
  link.href = url;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 10000);
}

async function shareCard(layout) {
  const btn = document.querySelector(`[data-share-card="${layout}"]`);
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Preparing…';

  try {
    const blob = await cardPngBlob(layout);
    const filename = `campbuddy-camp-card-${layout}.png`;
    const file = new File([blob], filename, { type: 'image/png' });

    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file], title: 'My Camp Card' });
    } else if (navigator.share) {
      // Some browsers support navigator.share but not file sharing —
      // share a link instead of failing silently.
      await navigator.share({ title: 'My Camp Card', url: location.href });
    } else {
      // No Web Share support at all (most desktop browsers) — fall back
      // to a download so the button still does something useful.
      saveBlob(blob, filename);
    }
  } catch (err) {
    if (err?.name !== 'AbortError') {
      alert("Couldn't share the card — try Download instead.");
    }
  } finally {
    btn.disabled = false;
    btn.textContent = original;
  }
}

// html2canvas is dynamically imported (inside renderCardToCanvas) so its
// ~50KB only ever loads for an attendee who actually taps Download or
// Share — never on page load, and never on any other screen (app.js
// only imports camp-card.js at all when #camp-card-form exists). A
// rasterized screenshot is the only practical way to turn this card's
// gradients/custom fonts/pseudo-element frames into a downloadable or
// shareable file; there's no reasonable native alternative that doesn't
// amount to reimplementing a renderer.
async function downloadCard(layout) {
  const btn = document.querySelector(`[data-download-card="${layout}"]`);
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Preparing…';

  try {
    saveBlob(await cardPngBlob(layout), `campbuddy-camp-card-${layout}.png`);
  } catch {
    alert("Couldn't create the image — try again.");
  } finally {
    btn.disabled = false;
    btn.textContent = original;
  }
}

// A minimal type-and-Enter tag input — no library, matches the app's
// "vanilla JS by default" posture (§4.2/§5.5). Returns a getter so the
// form's submit handler can read the current tag list at save time.
function setupTagInput(initialTags) {
  const textInput = document.getElementById('interests-text');
  const tagsContainer = document.getElementById('interests-tags');
  let tags = [...initialTags];

  function renderTags() {
    tagsContainer.replaceChildren(
      ...tags.map((tag, i) =>
        render('tpl-tag-input-tag', {
          text: tag,
          remove: { attrs: { 'data-remove-tag': i, 'aria-label': `Remove ${tag}` } },
        })
      )
    );

    tagsContainer.querySelectorAll('[data-remove-tag]').forEach((btn) => {
      btn.addEventListener('click', () => {
        tags.splice(Number(btn.dataset.removeTag), 1);
        renderTags();
      });
    });
  }

  function addTag(value) {
    const tag = value.trim();
    if (!tag || tags.includes(tag)) return;
    tags.push(tag);
    renderTags();
  }

  textInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addTag(textInput.value);
      textInput.value = '';
    } else if (e.key === 'Backspace' && textInput.value === '' && tags.length > 0) {
      tags.pop();
      renderTags();
    }
  });

  textInput.addEventListener('blur', () => {
    if (textInput.value.trim()) {
      addTag(textInput.value);
      textInput.value = '';
    }
  });

  renderTags();

  return () => tags;
}

function setupQrTargetPicker(activeTarget) {
  const chips = document.querySelectorAll('[data-qr-target]');

  chips.forEach((chip) => {
    chip.classList.toggle('chip--selected', chip.dataset.qrTarget === activeTarget);

    chip.addEventListener('click', () => {
      chips.forEach((c) => c.classList.toggle('chip--selected', c === chip));
    });
  });
}

function renderAllPreviews(card) {
  const sampleNoteEl = document.getElementById('camp-card-sample-note');
  const hasPrimaryLink = card && LINK_FIELDS.some((f) => resolveLink(f, card[f]));
  const isSample = !card?.name || !hasPrimaryLink;
  sampleNoteEl.hidden = !isSample;

  const primaryUrl = !isSample
    ? resolveLink(card.primaryLink, card[card.primaryLink]) ?? LINK_FIELDS.map((f) => resolveLink(f, card[f])).find(Boolean)
    : null;

  // Same QR (same primary link) shared across every layout's canvas — no
  // need to regenerate the module grid per card, just redraw it 6 times.
  const qr = primaryUrl ? QRCode(0, 'H') : null;
  if (qr) {
    qr.addData(primaryUrl);
    qr.make();
  }

  shown = { content: cardContent(isSample ? SAMPLE_CARD : card), qr };
  repaintPreviews();

  // The tag trimming below measures text, so it has to be redone once the
  // web fonts (Inter, Playfair Display…) have swapped in and changed how
  // wide everything is.
  document.fonts?.ready.then(repaintPreviews);
}

function repaintPreviews() {
  LAYOUTS.forEach((layout) => {
    const card = cardElement(layout);
    if (card) paintCard(card, shown.content, shown.qr);
  });
}

// What a card displays, independent of which layout is showing it.
function cardContent(display) {
  const visibleFields = display.visibleFields ?? [];

  // Role/company get their own dedicated line — shown only if chosen
  // (CC2), and left out of the tag pills below so they're never shown
  // twice on the same card.
  const roleLine = [
    visibleFields.includes('role') && display.role,
    visibleFields.includes('company') && display.company,
  ]
    .filter(Boolean)
    .join(' · ');

  // "Interests" expands into one pill per tag rather than a single
  // combined blob — the rest of visibleFields (excluding role/company,
  // already on their own line above) render as one pill each.
  const tags = [];
  visibleFields.forEach((f) => {
    if (f === 'interests') {
      (display.interests ?? []).forEach((tag) => tags.push(tag));
    } else if (f !== 'role' && f !== 'company' && display[f]) {
      tags.push(display[f]);
    }
  });

  return { name: display.name, roleLine, tags };
}

// Fills one .camp-card element (an on-screen card, or the clone being
// exported) from `content`; resolves once its QR — including the event
// logo drawn over its centre — is fully drawn.
async function paintCard(card, content, qr, qrPixels = QR_PREVIEW_PX) {
  card.querySelector('.camp-card__name').textContent = content.name;
  card.querySelector('.camp-card__role').textContent = content.roleLine;
  card.querySelector('.camp-card__footer').hidden = !qr;

  paintTags(card, content.tags);

  // The logos above the tags change how much room they have once they
  // load (Pass's is sized by its own aspect ratio), so trim again then —
  // otherwise a card can end up with pills spilling past its footer.
  await imagesLoaded(card);
  paintTags(card, content.tags);

  if (qr) {
    await drawQrToCanvas(qr, card.querySelector('.camp-card__qr-frame canvas'), card.dataset.eventIcon, qrPixels);
  }
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

// The card is a fixed size, so a long tag list can't be allowed to grow
// it: show as many pills as fit and fold the rest into a "+N" pill.
function paintTags(card, tags) {
  const body = card.querySelector('.camp-card__body');
  const tagsEl = card.querySelector('.camp-card__tags');
  const pill = (text) => render('tpl-camp-card-tag', { tag: text });

  if (tags.length === 0) {
    tagsEl.replaceChildren(render('tpl-camp-card-tag-empty'));
    return;
  }

  tagsEl.replaceChildren(...tags.map(pill));

  let visible = tags.length;
  while (visible > 1 && body.scrollHeight > body.clientHeight + 1) {
    visible -= 1;
    tagsEl.replaceChildren(...tags.slice(0, visible).map(pill), pill(`+${tags.length - visible}`));
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
