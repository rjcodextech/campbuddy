// Renders the website's tab drawings (public/media/*.svg) to PNG for the native app, because Android cannot
// draw SVG files. Used by sync-web-assets.py; the PNGs are committed, the SVG sources are recorded by hash.
import { readFileSync, writeFileSync } from 'node:fs';
import { launchChrome } from 'file:///E:/Sites/InHouse/campbuddy/V2/tests/browser/helpers/chrome.mjs';

const [media, out] = process.argv.slice(2);
const icons = { home: 'nav_home', 'my-day': 'nav_my_day', quest: 'nav_quest', contribute: 'nav_contribute', explore: 'nav_explore', 'camp-card': 'nav_camp_card' };
const SIZE = 144;
// Illustrations: web file in public/media/illustrations -> [app name, width, height] (rendered at 3x the website's display size)
const art = { people: ['art_people', 288, 216], 'first-badge': ['art_first_badge', 520, 600], avatar: ['art_avatar', 132, 132] };

const chrome = await launchChrome();
try {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: SIZE, height: SIZE, deviceScaleFactor: 1, mobile: false });
  await chrome.send('Emulation.setDefaultBackgroundColorOverride', { color: { r: 0, g: 0, b: 0, a: 0 } });
  for (const [web, app] of Object.entries(icons)) {
    const svg = readFileSync(`${media}/${web}.svg`, 'utf8');
    const html = `<html><body style="margin:0;background:transparent"><img src="data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}" style="width:${SIZE}px;height:${SIZE}px;display:block"></body></html>`;
    await chrome.goto('data:text/html;base64,' + Buffer.from(html).toString('base64'));
    await new Promise((r) => setTimeout(r, 400));
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png', omitBackground: true });
    writeFileSync(`${out}/${app}.png`, Buffer.from(shot.result.data, 'base64'));
  }
  for (const [web, [app, w, h]] of Object.entries(art)) {
    await chrome.send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile: false });
    const svg = readFileSync(`${media}/illustrations/${web}.svg`, 'utf8');
    const html = `<html><body style="margin:0;background:transparent"><img src="data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}" style="width:${w}px;height:${h}px;display:block;object-fit:contain"></body></html>`;
    await chrome.goto('data:text/html;base64,' + Buffer.from(html).toString('base64'));
    await new Promise((r) => setTimeout(r, 400));
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png', omitBackground: true });
    writeFileSync(`${out}/${app}.png`, Buffer.from(shot.result.data, 'base64'));
  }
} finally {
  await chrome.close();
}
