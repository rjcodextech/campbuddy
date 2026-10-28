import fs from 'node:fs';

const p = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/reels/render.mjs';
let code = fs.readFileSync(p, 'utf8').replace(/\r\n/g, '\n');

const oldBlock = `const srv = await serve();
const base = \`http://127.0.0.1:\${srv.address().port}/usp-campaign/reels/reel.html\`;
const c = await launchChrome();
await c.send('Emulation.setDeviceMetricsOverride', { width: 1080, height: 1920, deviceScaleFactor: Number(opt('scale', 1)), mobile: false });
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(PREVIEW, { recursive: true });

const is4K = has('4k') || opt('scale') === '2';
const SCALE = is4K ? 2 : Number(opt('scale', 1));`;

const newBlock = `const is4K = has('4k') || opt('scale') === '2';
const SCALE = is4K ? 2 : Number(opt('scale', 1));

const srv = await serve();
const base = \`http://127.0.0.1:\${srv.address().port}/usp-campaign/reels/reel.html\`;
const c = await launchChrome();
await c.send('Emulation.setDeviceMetricsOverride', { width: 1080, height: 1920, deviceScaleFactor: SCALE, mobile: false });
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(PREVIEW, { recursive: true });`;

if (code.includes(oldBlock)) {
  code = code.replace(oldBlock, newBlock);
  fs.writeFileSync(p, code);
  console.log('Reordered SCALE and deviceScaleFactor successfully');
} else {
  console.warn('Block still not found');
}
