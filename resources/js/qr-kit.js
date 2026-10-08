// The organizer QR kit (partials/qr-kit.blade.php): draws each code from its
// link in the browser and saves it as PNG (1200 px, for print) or SVG. The
// link is the only input; nothing is stored or sent anywhere.

import QRCode from 'qrcode-generator';

/** PNG side in pixels: sharp at badge size and still fine on a standee. */
export const PNG_SIZE = 1200;

/** Quiet zone around the code, in modules (the QR spec asks for 4). */
const MARGIN = 4;

export function makeQr(url) {
  const qr = QRCode(0, 'M');
  qr.addData(url);
  qr.make();

  return qr;
}

/** A plain, self-contained SVG of the code: black modules on white, one path. */
export function qrSvg(url) {
  const qr = makeQr(url);
  const count = qr.getModuleCount();
  const size = count + MARGIN * 2;
  let d = '';
  for (let row = 0; row < count; row++) {
    for (let col = 0; col < count; col++) {
      if (qr.isDark(row, col)) d += `M${col + MARGIN} ${row + MARGIN}h1v1h-1z`;
    }
  }

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" shape-rendering="crispEdges"><rect width="${size}" height="${size}" fill="#fff"/><path d="${d}" fill="#000"/></svg>`;
}

function qrPngBlob(url) {
  const qr = makeQr(url);
  const count = qr.getModuleCount();
  const cell = Math.floor(PNG_SIZE / (count + MARGIN * 2));
  const offset = Math.floor((PNG_SIZE - cell * count) / 2);
  const canvas = document.createElement('canvas');
  canvas.width = PNG_SIZE;
  canvas.height = PNG_SIZE;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, PNG_SIZE, PNG_SIZE);
  ctx.fillStyle = '#000';
  for (let row = 0; row < count; row++) {
    for (let col = 0; col < count; col++) {
      if (qr.isDark(row, col)) ctx.fillRect(offset + col * cell, offset + row * cell, cell, cell);
    }
  }

  return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}

function save(blob, filename) {
  const href = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = href;
  a.download = filename;
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(href), 1000);
}

export function initQrKit(root = document) {
  root.querySelectorAll('[data-qr-kit]').forEach((card) => {
    const url = card.dataset.qrUrl;
    const name = card.dataset.qrName;
    const svg = qrSvg(url);

    // Parsed from our own generated markup (only digits and fixed tags), not from any input.
    card.querySelector('[data-qr-image]').innerHTML = svg;

    card.querySelector('[data-qr-download="svg"]')?.addEventListener('click', () => {
      save(new Blob([svg], { type: 'image/svg+xml' }), `${name}.svg`);
    });
    card.querySelector('[data-qr-download="png"]')?.addEventListener('click', async () => {
      save(await qrPngBlob(url), `${name}.png`);
    });

    const link = card.querySelector('[data-qr-link]');
    link?.addEventListener('focus', () => link.select());

    const copy = card.querySelector('[data-qr-copy]');
    copy?.addEventListener('click', async () => {
      const label = copy.textContent;
      try {
        await navigator.clipboard.writeText(url);
        copy.textContent = 'Copied ✓';
      } catch {
        link?.select();
        copy.textContent = 'Press Ctrl+C';
      }
      setTimeout(() => { copy.textContent = label; }, 2000);
    });
  });
}
