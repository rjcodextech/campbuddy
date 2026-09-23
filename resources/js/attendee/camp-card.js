// Camp Card: local-only (CC5), attendee chooses which filled
// fields actually show (CC2), QR points at whichever link they designate
// primary (CC3). All 6 layouts render at once in a horizontally
// scrollable gallery (resources/views/attendee/camp-card.blade.php's
// #camp-card-scroll) rather than one preview behind a layout picker —
// each card is its own self-contained subtree (data-layout-card="…"),
// with its own Share/Download buttons, so nothing here relies on a
// single #camp-card-preview id.

import QRCode from 'qrcode-generator';
import { kvGet, kvSet } from './db.js';
import { render } from './template.js';

const LINK_FIELDS = ['linkedin', 'website', 'wordpressOrg', 'twitter'];
const LAYOUTS = ['classic', 'minimal', 'bold', 'split', 'badge', 'pass'];
const DEFAULT_QR_TARGET = 'linkedin';

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

// Renders one card to a canvas via html2canvas — shared by Download and
// Share so both produce the exact same PNG.
async function renderCardToCanvas(layout) {
  const { default: html2canvas } = await import('html2canvas');
  return html2canvas(cardElement(layout), { backgroundColor: null, scale: 2 });
}

async function shareCard(layout) {
  const btn = document.querySelector(`[data-share-card="${layout}"]`);
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Preparing…';

  try {
    const canvas = await renderCardToCanvas(layout);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    const file = new File([blob], `campbuddy-camp-card-${layout}.png`, { type: 'image/png' });

    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file], title: 'My Camp Card' });
    } else if (navigator.share) {
      // Some browsers support navigator.share but not file sharing —
      // share a link instead of failing silently.
      await navigator.share({ title: 'My Camp Card', url: location.href });
    } else {
      // No Web Share support at all (most desktop browsers) — fall back
      // to a download so the button still does something useful.
      const link = document.createElement('a');
      link.download = `campbuddy-camp-card-${layout}.png`;
      link.href = canvas.toDataURL('image/png');
      link.click();
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
    const canvas = await renderCardToCanvas(layout);

    const link = document.createElement('a');
    link.download = `campbuddy-camp-card-${layout}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
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

  LAYOUTS.forEach((layout) => renderPreview(layout, card, isSample, qr));
}

function renderPreview(layout, card, isSample, qr) {
  const item = document.querySelector(`[data-layout-card="${layout}"]`);
  if (!item) return;

  const qrSection = item.querySelector('.camp-card__footer');
  const display = isSample ? SAMPLE_CARD : card;
  const visibleFields = display.visibleFields ?? [];

  item.querySelector('.camp-card__name').textContent = display.name;

  // Role/company get their own dedicated line — shown only if chosen
  // (CC2), and left out of the tag pills below so they're never shown
  // twice on the same card.
  item.querySelector('.camp-card__role').textContent = [
    visibleFields.includes('role') && display.role,
    visibleFields.includes('company') && display.company,
  ]
    .filter(Boolean)
    .join(' · ');

  // "Interests" expands into one pill per tag rather than a single
  // combined blob — the rest of visibleFields (excluding role/company,
  // already on their own line above) render as one pill each.
  const tagValues = [];
  visibleFields.forEach((f) => {
    if (f === 'interests') {
      (display.interests ?? []).forEach((tag) => tagValues.push(tag));
    } else if (f !== 'role' && f !== 'company' && display[f]) {
      tagValues.push(display[f]);
    }
  });

  item.querySelector('.camp-card__tags').replaceChildren(
    ...(tagValues.length
      ? tagValues.map((t) => render('tpl-camp-card-tag', { tag: t }))
      : [render('tpl-camp-card-tag-empty')])
  );

  qrSection.hidden = !qr;
  if (qr) {
    const canvas = item.querySelector('.camp-card__qr-frame canvas');
    drawQrToCanvas(qr, canvas, item.querySelector('.camp-card')?.dataset.eventIcon);
  }
}

// Renders at 320px internally (well above the ~72-120px CSS display size
// across the 6 layouts, and above the 2x scale html2canvas uses for
// Download/Share) so the QR stays crisp when scaled up or printed. When
// eventIconUrl is given, overlays it in a small white plate at dead
// center — safe at error-correction level 'H' since that plate covers
// well under the ~30% of modules 'H' can lose and still decode.
function drawQrToCanvas(qr, canvas, eventIconUrl) {
  const count = qr.getModuleCount();
  const size = 320;
  const cell = size / count;
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

  if (!eventIconUrl) return;

  const logo = new Image();
  logo.onload = () => {
    const plate = size * 0.24;
    const inset = (size - plate) / 2;
    const plateRadius = 10;

    ctx.fillStyle = '#fff';
    roundedRectPath(ctx, inset - 6, inset - 6, plate + 12, plate + 12, plateRadius);
    ctx.fill();

    ctx.save();
    roundedRectPath(ctx, inset, inset, plate, plate, plateRadius - 3);
    ctx.clip();
    ctx.drawImage(logo, inset, inset, plate, plate);
    ctx.restore();
  };
  logo.src = eventIconUrl;
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
