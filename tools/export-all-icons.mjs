import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

const iconsJson = JSON.parse(fs.readFileSync('scratch_icons.json', 'utf8'));
const outDir = path.resolve('docs/icons');
const svgDir = path.join(outDir, 'svg');
const pngDir = path.join(outDir, 'png');

fs.mkdirSync(svgDir, { recursive: true });
fs.mkdirSync(pngDir, { recursive: true });

console.log(`Writing ${Object.keys(iconsJson).length} SVG files to ${svgDir}...`);

for (const [name, markup] of Object.entries(iconsJson)) {
  const svgContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#1E293B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
  ${markup}
</svg>
`;
  fs.writeFileSync(path.join(svgDir, `${name}.svg`), svgContent, 'utf8');
}

console.log('SVGs written. Launching headless browser for PNG rendering...');

const chrome = await launchChrome();
try {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: 1000, height: 1000, deviceScaleFactor: 1, mobile: false });
  await chrome.send('Page.navigate', { url: 'about:blank' });
  await sleep(500);

  const results = await chrome.evaluate(`(async () => {
    const icons = ${JSON.stringify(iconsJson)};
    const rendered = {};
    const size = 512;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    for (const [name, markup] of Object.entries(icons)) {
      ctx.clearRect(0, 0, size, size);
      const svg = \`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="\${size}" height="\${size}" fill="none" stroke="#1E293B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">\${markup}</svg>\`;
      const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const img = new Image();
      await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
        img.src = url;
      });
      const pad = 32;
      ctx.drawImage(img, pad, pad, size - pad * 2, size - pad * 2);
      URL.revokeObjectURL(url);
      rendered[name] = canvas.toDataURL('image/png').split(',')[1];
    }
    return rendered;
  })()`);

  console.log(`Writing ${Object.keys(results).length} PNG files to ${pngDir}...`);
  for (const [name, b64] of Object.entries(results)) {
    fs.writeFileSync(path.join(pngDir, `${name}.png`), Buffer.from(b64, 'base64'));
  }

  console.log('All PNG files written successfully!');
} finally {
  await chrome.close();
}
