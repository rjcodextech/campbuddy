// Camp Card: local-only (CC5), attendee chooses which filled
// fields actually show (CC2), QR points at whichever link they designate
// primary (CC3), and it goes fullscreen with one tap (CC4). Always shows
// a preview — a sample card until the attendee has real data, so they
// can see how it looks before filling anything in — and lets them pick
// from 5 layouts (SCSS: components/_camp-card.scss).

import QRCode from 'qrcode-generator';
import { kvGet, kvSet } from './db.js';

const LINK_FIELDS = ['linkedin', 'website', 'wordpressOrg', 'twitter'];
const LAYOUTS = ['classic', 'minimal', 'bold', 'split', 'badge'];
const DEFAULT_LAYOUT = 'classic';

const SAMPLE_CARD = {
  name: 'Jamie Rivera',
  role: 'WordPress Developer',
  company: 'Acme Studio',
  interests: 'Gutenberg, WooCommerce',
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

  document.querySelectorAll('[data-visible-field]').forEach((chip) => {
    if (visibleFields.has(chip.dataset.visibleField)) chip.classList.add('chip--selected');

    chip.addEventListener('click', () => {
      chip.classList.toggle('chip--selected');
    });
  });

  setupLayoutPicker(card?.layout ?? DEFAULT_LAYOUT);
  renderPreview(card);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = Object.fromEntries(new FormData(form).entries());
    data.visibleFields = [...document.querySelectorAll('[data-visible-field].chip--selected')].map(
      (chip) => chip.dataset.visibleField
    );
    data.layout = document.querySelector('[data-layout].chip--selected')?.dataset.layout ?? DEFAULT_LAYOUT;

    await kvSet('campCard', data);
    renderPreview(data);
    document.getElementById('cc-edit-details').open = false;
  });

  document.getElementById('fullscreen-btn')?.addEventListener('click', () => {
    const el = document.getElementById('camp-card-preview');
    if (el.requestFullscreen) el.requestFullscreen();
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

  sampleNoteEl.hidden = !isSample;

  document.getElementById('cc-name').textContent = display.name;
  document.getElementById('cc-role').textContent = [display.role, display.company].filter(Boolean).join(' · ');

  const tags = (display.visibleFields ?? [])
    .filter((f) => display[f])
    .map((f) => `<span class="camp-card__tag">${escapeHtml(display[f])}</span>`)
    .join('');
  document.getElementById('cc-tags').innerHTML = tags || `<span class="camp-card__tag camp-card__tag--placeholder">Nothing chosen to show yet</span>`;

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
