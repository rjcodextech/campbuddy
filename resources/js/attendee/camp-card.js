// Camp Card: local-only (CC5), attendee chooses which filled
// fields actually show (CC2), QR points at whichever link they designate
// primary (CC3), and it goes fullscreen with one tap (CC4) — the QR now
// lives inside the card itself (components/_camp-card.scss), so
// fullscreen actually shows the whole scannable badge, not just the
// name/tags above it. Always shows a preview — a sample card until the
// attendee has real data — and lets them pick from 5 layouts.

import QRCode from 'qrcode-generator';
import { kvGet, kvSet } from './db.js';

const LINK_FIELDS = ['linkedin', 'website', 'wordpressOrg', 'twitter'];
const LAYOUTS = ['classic', 'minimal', 'bold', 'split', 'badge'];
const DEFAULT_LAYOUT = 'classic';
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
  setupLayoutPicker(card?.layout ?? DEFAULT_LAYOUT);
  renderPreview(card ? { ...card, interests: normalizeInterests(card.interests) } : card);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = Object.fromEntries(new FormData(form).entries());
    data.interests = getInterests();
    data.visibleFields = [...document.querySelectorAll('[data-visible-field].chip--selected')].map(
      (chip) => chip.dataset.visibleField
    );
    data.primaryLink = document.querySelector('[data-qr-target].chip--selected')?.dataset.qrTarget ?? DEFAULT_QR_TARGET;
    data.layout = document.querySelector('[data-layout].chip--selected')?.dataset.layout ?? DEFAULT_LAYOUT;

    await kvSet('campCard', data);
    renderPreview(data);
    document.getElementById('cc-edit-details').open = false;
  });

  document.getElementById('fullscreen-btn')?.addEventListener('click', () => {
    const el = document.getElementById('camp-card-preview');
    if (el.requestFullscreen) el.requestFullscreen();
  });

  document.getElementById('save-image-btn')?.addEventListener('click', saveAsImage);
  document.getElementById('print-btn')?.addEventListener('click', () => window.print());
}

// html2canvas is dynamically imported so its ~50KB only ever loads for
// an attendee who actually taps "Save as image" — never on page load,
// and never on any other screen (app.js only imports camp-card.js at
// all when #camp-card-form exists). A rasterized screenshot is the only
// practical way to turn this card's gradients/custom fonts/pseudo-
// element frames into a downloadable file; there's no reasonable native
// alternative that doesn't amount to reimplementing a renderer.
async function saveAsImage() {
  const btn = document.getElementById('save-image-btn');
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Preparing…';

  try {
    const { default: html2canvas } = await import('html2canvas');
    const el = document.getElementById('camp-card-preview');
    const canvas = await html2canvas(el, { backgroundColor: null, scale: 2 });

    const link = document.createElement('a');
    link.download = 'campbuddy-camp-card.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  } catch {
    alert("Couldn't create the image — try again, or use View fullscreen and a screenshot instead.");
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
    tagsContainer.innerHTML = tags
      .map(
        (tag, i) => `
          <span class="tag-input__tag">
            ${escapeHtml(tag)}
            <button type="button" class="tag-input__remove" data-remove-tag="${i}" aria-label="Remove ${escapeAttr(tag)}">×</button>
          </span>
        `
      )
      .join('');

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

function setupLayoutPicker(activeLayout) {
  const chips = document.querySelectorAll('[data-layout]');

  chips.forEach((chip) => {
    chip.classList.toggle('chip--selected', chip.dataset.layout === activeLayout);

    chip.addEventListener('click', async () => {
      chips.forEach((c) => c.classList.toggle('chip--selected', c === chip));
      applyLayoutClass(chip.dataset.layout);

      // A display preference, not form data — applies and saves
      // immediately rather than waiting for "Save Camp Card".
      const existing = (await kvGet('campCard')) ?? {};
      await kvSet('campCard', { ...existing, layout: chip.dataset.layout });
    });
  });

  applyLayoutClass(activeLayout);
}

function applyLayoutClass(layout) {
  const el = document.getElementById('camp-card-preview');
  const safeLayout = LAYOUTS.includes(layout) ? layout : DEFAULT_LAYOUT;
  el.className = `camp-card camp-card--${safeLayout}`;
}

function renderPreview(card) {
  const sampleNoteEl = document.getElementById('camp-card-sample-note');
  const qrSection = document.getElementById('qr-section');

  const hasPrimaryLink = card && LINK_FIELDS.some((f) => resolveLink(f, card[f]));
  const isSample = !card?.name || !hasPrimaryLink;
  const display = isSample ? SAMPLE_CARD : card;
  const visibleFields = display.visibleFields ?? [];

  sampleNoteEl.hidden = !isSample;

  document.getElementById('cc-name').textContent = display.name;
  document.getElementById('cc-role').textContent = [display.role, display.company].filter(Boolean).join(' · ');

  // "Interests" expands into one pill per tag rather than a single
  // combined blob — the rest of visibleFields render as one pill each.
  const tagValues = [];
  visibleFields.forEach((f) => {
    if (f === 'interests') {
      (display.interests ?? []).forEach((tag) => tagValues.push(tag));
    } else if (display[f]) {
      tagValues.push(display[f]);
    }
  });

  document.getElementById('cc-tags').innerHTML = tagValues.length
    ? tagValues.map((t) => `<span class="camp-card__tag">${escapeHtml(t)}</span>`).join('')
    : `<span class="camp-card__tag camp-card__tag--placeholder">Nothing chosen to show yet</span>`;

  if (isSample) {
    qrSection.hidden = true;
    return;
  }

  const primaryUrl = resolveLink(card.primaryLink, card[card.primaryLink])
    ?? LINK_FIELDS.map((f) => resolveLink(f, card[f])).find(Boolean);

  if (primaryUrl) {
    qrSection.hidden = false;
    const qr = QRCode(0, 'M');
    qr.addData(primaryUrl);
    qr.make();
    const canvas = document.getElementById('qr-canvas');
    drawQrToCanvas(qr, canvas);
  } else {
    qrSection.hidden = true;
  }
}

function drawQrToCanvas(qr, canvas) {
  const count = qr.getModuleCount();
  const size = 240;
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
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function escapeAttr(str) {
  return escapeHtml(str);
}
