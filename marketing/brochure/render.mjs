// Prints the CampBuddy one-page brochure (brochure.html).
//
//   node marketing/brochure/render.mjs
//
// A4 portrait (210 × 297 mm) with 3 mm bleed (216 × 303 mm). Writes to print/:
//   CampBuddy-brochure-A4.pdf   for the printer (vector text)
//   CampBuddy-brochure-A4.png   900 dpi, with bleed
// Needs Chrome (same finder as the browser tests) and internet for the Inter font.

import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath, pathToFileURL } from 'node:url';
import qrcode from 'qrcode-generator';
import { launchChrome, sleep } from '../../tests/browser/helpers/chrome.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(here, 'print');
const QR_URL = 'https://campbuddy.club/?utm_source=brochure&utm_medium=print';
const DPI = 900;

function qrSvg(text) {
  const qr = qrcode(0, 'Q');
  qr.addData(text);
  qr.make();
  const n = qr.getModuleCount();
  let d = '';
  for (let r = 0; r < n; r++) {
    for (let c = 0; c < n; c++) {
      if (qr.isDark(r, c)) d += `M${c} ${r}h1v1h-1z`;
    }
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${n} ${n}" shape-rendering="crispEdges"><path fill="#231f20" d="${d}"/></svg>`;
}

// What the app does (line icons from the app, App\Support\LineIcons).
const FEATURES = [
  ['My schedule', 'The full schedule. Save the talks you want and get a reminder before they start.', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
  ['People', 'See who is coming, find people who share your interests and wave to say hi.', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
  ['Camp Card', 'Your digital name card with your links and a QR code. Share it in one tap.', '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2M15 12h2M7 16h6"/>'],
  ['Quests', 'Small, friendly challenges that help you meet people and explore the event.', '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>'],
  ['Guide', 'First WordCamp? A simple guide to what happens, when, and how to join in.', '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
  ['Deals', 'Offers for attendees from sponsors and friends of the community.', '<path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1"/>'],
];
const features = FEATURES.map(([title, text, paths]) => `
      <div class="feat"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${paths}</svg></span><b>${title}</b><p>${text}</p></div>`).join('');

// Marks a PNG with its dpi (a pHYs chunk after IHDR), so print software opens it at A4.
function withDpi(png, dpi) {
  const perMetre = Math.round(dpi / 0.0254);
  const body = Buffer.alloc(13);
  body.write('pHYs', 0, 'ascii');
  body.writeUInt32BE(perMetre, 4);
  body.writeUInt32BE(perMetre, 8);
  body.writeUInt8(1, 12);
  const chunk = Buffer.alloc(21);
  chunk.writeUInt32BE(9, 0);
  body.copy(chunk, 4);
  chunk.writeUInt32BE(zlib.crc32(body), 17);
  return Buffer.concat([png.subarray(0, 33), chunk, png.subarray(33)]);
}

// A file just written can be held for a moment (antivirus scanning it): try again.
async function save(file, data) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  for (let attempt = 1; ; attempt++) {
    try {
      return fs.writeFileSync(file, data);
    } catch (error) {
      if (attempt >= 20) throw error;
      await sleep(500);
    }
  }
}

const temp = path.join(here, '.render.html');
fs.writeFileSync(temp, fs.readFileSync(path.join(here, 'brochure.html'), 'utf8')
  .replaceAll('{{QR}}', () => qrSvg(QR_URL))
  .replaceAll('{{FEATURES}}', () => features));

const chrome = await launchChrome();
const watchdog = setTimeout(() => { chrome.close(); process.exit(2); }, 240000);
try {
  await chrome.goto(pathToFileURL(temp).href);
  await chrome.waitFor('document.body.dataset.ready === "1"');
  await sleep(500);

  const pdf = await chrome.send('Page.printToPDF', { printBackground: true, preferCSSPageSize: true, marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0 });
  await save(path.join(out, 'CampBuddy-brochure-A4.pdf'), Buffer.from(pdf.result.data, 'base64'));

  const box = await chrome.evaluate(`(() => { const r = document.querySelector('.sheet').getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; })()`);
  const shot = await chrome.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { ...box, scale: DPI / 96 } });
  await save(path.join(out, 'CampBuddy-brochure-A4.png'), withDpi(Buffer.from(shot.result.data, 'base64'), DPI));

  console.log('print/CampBuddy-brochure-A4.pdf, print/CampBuddy-brochure-A4.png');
} finally {
  clearTimeout(watchdog);
  await chrome.close();
  fs.rmSync(temp, { force: true });
}
