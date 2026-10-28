import fs from 'node:fs';
import { launchChrome } from '../tests/browser/helpers/chrome.mjs';

const c = await launchChrome();
try {
  // Desktop 1280x1000
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 1000, deviceScaleFactor: 1, mobile: false });
  await c.goto('http://campbuddy.test');
  await new Promise(r => setTimeout(r, 1000));
  await c.evaluate(`document.querySelector('#desktop-notice')?.close();`);
  await new Promise(r => setTimeout(r, 500));
  
  // Capture full page screenshot
  const shotFull = await c.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
  fs.writeFileSync('C:/Users/sunil/.gemini/antigravity/brain/e88dbdd8-b1e7-42ea-905a-5c04e888d196/homepage-desktop.png', Buffer.from(shotFull.result.data, 'base64'));
  console.log('Desktop screenshot saved!');

  // Mobile 390x844
  await c.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await c.goto('http://campbuddy.test');
  await new Promise(r => setTimeout(r, 1500));
  const shotMobile = await c.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
  fs.writeFileSync('C:/Users/sunil/.gemini/antigravity/brain/e88dbdd8-b1e7-42ea-905a-5c04e888d196/homepage-mobile.png', Buffer.from(shotMobile.result.data, 'base64'));
  console.log('Mobile screenshot saved!');
} catch (e) {
  console.error(e);
} finally {
  await c.close();
}
