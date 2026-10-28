import { launchChrome } from '../tests/browser/helpers/chrome.mjs';
import fs from 'node:fs';

const c = await launchChrome();
try {
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 1000, deviceScaleFactor: 1, mobile: false });
  
  // 1. Capture About page
  await c.goto('http://campbuddy.test/about');
  await new Promise(r => setTimeout(r, 600));
  await c.evaluate("document.querySelector('#desktop-notice')?.close();");
  const hAbout = await c.evaluate("document.documentElement.scrollHeight");
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: hAbout, deviceScaleFactor: 1, mobile: false });
  await new Promise(r => setTimeout(r, 600));
  const shotAbout = await c.send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('C:/Users/sunil/.gemini/antigravity/brain/e88dbdd8-b1e7-42ea-905a-5c04e888d196/about-footer-verify.png', Buffer.from(shotAbout.result.data, 'base64'));
  console.log('Saved about-footer-verify.png');

  // 2. Capture Home page footer
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 1000, deviceScaleFactor: 1, mobile: false });
  await c.goto('http://campbuddy.test/');
  await new Promise(r => setTimeout(r, 600));
  await c.evaluate("document.querySelector('#desktop-notice')?.close();");
  const hHome = await c.evaluate("document.documentElement.scrollHeight");
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: hHome, deviceScaleFactor: 1, mobile: false });
  await new Promise(r => setTimeout(r, 600));
  const shotHome = await c.send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('C:/Users/sunil/.gemini/antigravity/brain/e88dbdd8-b1e7-42ea-905a-5c04e888d196/home-footer-verify.png', Buffer.from(shotHome.result.data, 'base64'));
  console.log('Saved home-footer-verify.png');
} finally {
  await c.close();
}
