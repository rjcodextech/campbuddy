// Prints the CampBuddy ID cards (id-card.html): one front per name, one back.
//
//   node marketing/id-card/render.mjs                        # every name in names.txt
//   node marketing/id-card/render.mjs "Name" ["Role"]         # just one person
//
// names.txt: one person per line, "Name" or "Name | Role".
// Card 86 × 54 mm, landscape, 3 mm bleed (92 × 60 mm). Writes to print/:
//   CampBuddy-ID-cards.pdf            every card, front then back, with bleed (for the printer)
//   CampBuddy-ID-cards-cropmarks.pdf  the same, on a larger page with crop marks
//   pdf/<name>.pdf                    one person: front + back
//   png/<name>-front.png, png/back.png, png/blank-front.png   300 dpi, with bleed
// Needs Chrome (same finder as the browser tests) and internet for the Inter font.

import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath, pathToFileURL } from 'node:url';
import qrcode from 'qrcode-generator';
import { launchChrome, sleep } from '../../tests/browser/helpers/chrome.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(here, 'print');
const QR_URL = 'https://campbuddy.club/?utm_source=id-card&utm_medium=print';
const DPI = 300;

const people = process.argv[2]
  ? [{ name: process.argv[2], role: process.argv[3] ?? '' }]
  : fs.readFileSync(path.join(here, 'names.txt'), 'utf8').split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
    .map((line) => { const [name, role = ''] = line.split('|').map((part) => part.trim()); return { name, role }; });

const slug = (name) => name.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const escape = (s) => s.replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch]);

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

const crops = ['h l t', 'h r t', 'h l b', 'h r b', 'v l t', 'v r t', 'v l b', 'v r b']
  .map((c) => `<i class="crop ${c}"></i>`).join('');

const front = ({ name, role }) => `
  <div class="page">${crops}
    <section class="card front" aria-label="Front">
      <div class="side"><div class="pattern"></div></div>
      <div class="mark"><img src="../../public/media/favicon.png" alt=""></div>
      <p class="team">Team<b>CampBuddy</b></p>
      <div class="main">
        <img class="logo" src="../../public/media/logo.png" alt="CampBuddy">
        <div class="who">
          <p class="name">${escape(name)}</p>
          ${name ? '<div class="rule"></div>' : ''}
          <p class="role">${escape(role)}</p>
        </div>
        <p class="ask"><span>Ask me about <strong>CampBuddy</strong></span><span class="site">campbuddy.club</span></p>
      </div>
    </section>
  </div>`;

const back = `
  <div class="page">${crops}
    <section class="card back" aria-label="Back">
      <div class="pattern"></div>
      <div class="glow"></div>
      <div class="text">
        <p class="kicker">Free for every attendee</p>
        <h1>Your whole WordCamp<br>in one app</h1>
        <ul>
          <li>Your schedule, with reminders</li>
          <li>Find people worth meeting</li>
          <li>A digital Camp Card to share</li>
          <li>Quests, deals and event info</li>
          <li>Works offline at the venue</li>
        </ul>
        <p class="url">campbuddy.club</p>
      </div>
      <div class="qrbox">
        <div class="qr">${qrSvg(QR_URL)}</div>
        <p class="scan">Scan to open CampBuddy<span>No download, no sign-up</span></p>
      </div>
    </section>
  </div>`;

// Every person's front then the shared back; the blank front last.
const pages = [...people.flatMap((person) => [front(person), back]), front({ name: '', role: '' })].join('\n');

function page(mode) {
  const size = mode === 'marks' ? '@page { size: 104mm 72mm; margin: 0; }' : '@page { size: 92mm 60mm; margin: 0; }';
  return fs.readFileSync(path.join(here, 'id-card.html'), 'utf8')
    .replaceAll('{{PAGE}}', size)
    .replaceAll('{{MODE}}', mode)
    .replaceAll('{{PAGES}}', () => pages);
}

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
const chrome = await launchChrome();
const watchdog = setTimeout(() => { chrome.close(); process.exit(2); }, 180000);
const pdf = async (ranges) => Buffer.from((await chrome.send('Page.printToPDF', {
  printBackground: true, preferCSSPageSize: true, marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0, ...(ranges ? { pageRanges: ranges } : {}),
})).result.data, 'base64');
const open = async (mode) => {
  fs.writeFileSync(temp, page(mode));
  await chrome.goto(pathToFileURL(temp).href);
  await chrome.waitFor('document.body.dataset.ready === "1"');
  await sleep(300);
};

try {
  await open('plain');

  // PNGs: each card on screen, at 300 dpi.
  const cards = await chrome.evaluate(`[...document.querySelectorAll('.card')].map((el) => { const r = el.getBoundingClientRect(); return { x: r.x + scrollX, y: r.y + scrollY, width: r.width, height: r.height }; })`);
  const shoot = async (i, file) => {
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { ...cards[i], scale: DPI / 96 } });
    await save(path.join(out, 'png', file), withDpi(Buffer.from(shot.result.data, 'base64'), DPI));
  };
  for (const [i, person] of people.entries()) await shoot(i * 2, `${slug(person.name)}-front.png`);
  await shoot(1, 'back.png');
  await shoot(cards.length - 1, 'blank-front.png');

  // PDFs: all cards, and one per person (its front and back pages).
  const last = people.length * 2;
  await save(path.join(out, 'CampBuddy-ID-cards.pdf'), await pdf(`1-${last}`));
  for (const [i, person] of people.entries()) {
    await save(path.join(out, 'pdf', `${slug(person.name)}.pdf`), await pdf(`${i * 2 + 1}-${i * 2 + 2}`));
  }
  await save(path.join(out, 'pdf', 'blank-front.pdf'), await pdf(`${last + 1}`));

  await open('marks');
  await save(path.join(out, 'CampBuddy-ID-cards-cropmarks.pdf'), await pdf(`1-${last}`));

  console.log(`${people.length} cards: print/CampBuddy-ID-cards.pdf, print/CampBuddy-ID-cards-cropmarks.pdf, print/pdf/, print/png/`);
} finally {
  clearTimeout(watchdog);
  await chrome.close();
  fs.rmSync(temp, { force: true });
}
