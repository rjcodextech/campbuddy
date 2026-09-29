// Prints the CampBuddy ID card (id-card.html) for one person, or a blank front.
//
//   node marketing/id-card/render.mjs "Sunil Kumar Sharma" "WordPress Engineer"
//   node marketing/id-card/render.mjs --blank
//
// Writes to marketing/id-card/print/:
//   <slug>-front.png  front, 300 dpi, with 3 mm bleed (709 × 1082 px)
//   back.png          back, same size (one back for everyone)
//   <slug>.pdf        2 pages (front, back), 60 × 91.6 mm, vector text
// Needs Chrome (same finder as the browser tests) and internet for the Inter font.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import zlib from 'node:zlib';
import qrcode from 'qrcode-generator';
import { launchChrome, sleep } from '../../tests/browser/helpers/chrome.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(here, 'print');
const QR_URL = 'https://campbuddy.club/?utm_source=id-card&utm_medium=print';
const DPI = 300;

const blank = process.argv.includes('--blank');
const [name = '', role = ''] = blank ? [] : process.argv.slice(2);
if (!blank && !name) {
  console.error('Usage: node marketing/id-card/render.mjs "Name" ["Role"]   |   --blank');
  process.exit(1);
}
const slug = blank ? 'blank' : name.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

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

const escape = (s) => s.replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch]);
const html = fs.readFileSync(path.join(here, 'id-card.html'), 'utf8')
  .replaceAll('{{NAME}}', escape(name))
  .replaceAll('{{ROLE}}', escape(role))
  .replaceAll('{{BLANK}}', blank ? ' blank' : '')
  .replaceAll('{{QR}}', qrSvg(QR_URL));

// Marks a PNG as 300 dpi (a pHYs chunk after IHDR), so a print shop's
// software opens it at card size instead of guessing 72 or 96 dpi.
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
  const afterIhdr = 8 + 25;
  return Buffer.concat([png.subarray(0, afterIhdr), chunk, png.subarray(afterIhdr)]);
}

// A file just written can be held for a moment (antivirus scanning it): try again.
async function save(file, data) {
  for (let attempt = 1; ; attempt++) {
    try {
      return fs.writeFileSync(file, data);
    } catch (error) {
      if (attempt >= 20) throw error;
      await sleep(500);
    }
  }
}

const temp = path.join(here, `.render-${slug}.html`);
fs.writeFileSync(temp, html);
fs.mkdirSync(out, { recursive: true });

const chrome = await launchChrome();
const watchdog = setTimeout(() => { chrome.close(); process.exit(2); }, 90000);
try {
  await chrome.goto(pathToFileURL(temp).href);
  await chrome.waitFor('document.body.dataset.ready === "1"');
  await sleep(300);

  const scale = DPI / 96;
  const cards = await chrome.evaluate(`[...document.querySelectorAll('.card')].map((el) => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; })`);
  for (const [i, file] of [[0, `${slug}-front.png`], [1, 'back.png']]) {
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png', clip: { ...cards[i], scale } });
    await save(path.join(out, file), withDpi(Buffer.from(shot.result.data, 'base64'), DPI));
  }

  const pdf = await chrome.send('Page.printToPDF', { printBackground: true, preferCSSPageSize: true, marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0 });
  await save(path.join(out, `${slug}.pdf`), Buffer.from(pdf.result.data, 'base64'));

  console.log(`print/${slug}-front.png, print/back.png, print/${slug}.pdf`);
} finally {
  clearTimeout(watchdog);
  await chrome.close();
  fs.rmSync(temp, { force: true });
}
