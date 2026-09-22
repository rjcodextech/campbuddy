// Camp Card (§3.10): local-only (CC5), attendee chooses which filled
// fields actually show (CC2), QR points at whichever link they designate
// primary (CC3), and it goes fullscreen with one tap (CC4).

import QRCode from 'qrcode-generator';
import { kvGet, kvSet } from './db.js';

const LINK_FIELDS = ['linkedin', 'website', 'wordpressOrg', 'twitter'];

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

  renderPreview(card);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = Object.fromEntries(new FormData(form).entries());
    data.visibleFields = [...document.querySelectorAll('[data-visible-field].chip--selected')].map(
      (chip) => chip.dataset.visibleField
    );

    await kvSet('campCard', data);
    renderPreview(data);
    document.getElementById('cc-edit-details').open = false;
  });

  document.getElementById('fullscreen-btn')?.addEventListener('click', () => {
    const el = document.getElementById('camp-card-preview');
    if (el.requestFullscreen) el.requestFullscreen();
  });
}

function renderPreview(card) {
  const previewEl = document.getElementById('camp-card-preview');
  const emptyEl = document.getElementById('camp-card-empty');
  const qrSection = document.getElementById('qr-section');

  const hasPrimaryLink = card && LINK_FIELDS.some((f) => card[f]);

  if (!card?.name || !hasPrimaryLink) {
    previewEl.hidden = true;
    qrSection.hidden = true;
    emptyEl.hidden = false;
    return;
  }

  emptyEl.hidden = true;
  previewEl.hidden = false;

  document.getElementById('cc-name').textContent = card.name;
  document.getElementById('cc-role').textContent = [card.role, card.company].filter(Boolean).join(' · ');

  const tags = (card.visibleFields ?? [])
    .filter((f) => card[f])
    .map((f) => `<span class="camp-card__tag">${escapeHtml(card[f])}</span>`)
    .join('');
  document.getElementById('cc-tags').innerHTML = tags || `<span class="camp-card__tag camp-card__tag--placeholder">Nothing chosen to show yet</span>`;

  const primaryUrl = card[card.primaryLink] || LINK_FIELDS.map((f) => card[f]).find(Boolean);

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
