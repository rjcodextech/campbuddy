import fs from 'node:fs';
import path from 'node:path';
import { launchChrome } from 'file:///E:/Sites/InHouse/campbuddy/V2/tests/browser/helpers/chrome.mjs';

const c = await launchChrome();
try {
  // Desktop 1280x800
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 2, mobile: false });
  await c.goto('http://127.0.0.1:8000');
  await new Promise(r => setTimeout(r, 1000));
  const shotDesktop = await c.send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('E:/Sites/InHouse/campbuddy/marketing/hero-desktop-preview.png', Buffer.from(shotDesktop.result.data, 'base64'));
  console.log('Desktop screenshot saved!');

  // Mobile 390x844
  await c.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await c.goto('http://127.0.0.1:8000');
  await new Promise(r => setTimeout(r, 1000));
  const shotMobile = await c.send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('E:/Sites/InHouse/campbuddy/marketing/hero-mobile-preview.png', Buffer.from(shotMobile.result.data, 'base64'));
  console.log('Mobile screenshot saved!');
} finally {
  await c.close();
}
