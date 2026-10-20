import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

async function run() {
  const chrome = await launchChrome();

  const artifactDir = 'C:/Users/sunil/.gemini/antigravity/brain/837e7372-ddd0-44c6-8221-41fc000a4c42';
  const BASE = 'http://localhost:8099';

  await chrome.send('Emulation.setDeviceMetricsOverride', {
    width: 1440,
    height: 960,
    deviceScaleFactor: 1,
    mobile: false,
  });

  const screenshot = async (filename) => {
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png' });
    if (shot.result?.data) {
      fs.writeFileSync(path.join(artifactDir, filename), Buffer.from(shot.result.data, 'base64'));
      console.log(`Saved screenshot: ${filename}`);
    }
  };

  // 1. Visit Login
  console.log('Navigating to admin login...');
  await chrome.send('Page.navigate', { url: `${BASE}/admin/login` });
  await sleep(1500);

  // Fill login
  await chrome.send('Runtime.evaluate', {
    expression: `
      document.querySelector('input[name="email"]').value = 'hello@wpsimplified.in';
      document.querySelector('input[name="password"]').value = 'Hello@123';
      document.querySelector('button[type="submit"]').click();
    `
  });
  await sleep(2500);

  // 2. Visit /admin/mobile-app
  console.log('Navigating to /admin/mobile-app...');
  await chrome.send('Page.navigate', { url: `${BASE}/admin/mobile-app` });
  await sleep(1500);
  await screenshot('admin_mobile_app_overview.png');

  // 3. Visit History tab
  console.log('Navigating to History tab...');
  await chrome.send('Page.navigate', { url: `${BASE}/admin/mobile-app?tab=history` });
  await sleep(1500);
  await screenshot('admin_mobile_app_history.png');

  // 4. Visit Navigation tab
  console.log('Navigating to Navigation tab...');
  await chrome.send('Page.navigate', { url: `${BASE}/admin/mobile-app?tab=navigation` });
  await sleep(1500);
  await screenshot('admin_mobile_app_navigation.png');

  // 5. Visit Banner tab
  console.log('Navigating to Banner tab...');
  await chrome.send('Page.navigate', { url: `${BASE}/admin/mobile-app?tab=banner` });
  await sleep(1500);
  await screenshot('admin_mobile_app_banner_upgraded.png');

  // 6. Test Interactive Banner Controls: Turn ON, click Preset, change style to warning
  console.log('Testing banner interactions...');
  await chrome.send('Runtime.evaluate', {
    expression: `
      const chk = document.getElementById('banner_on');
      if (chk && !chk.checked) { chk.checked = true; chk.dispatchEvent(new Event('change')); }
      const presetBtn = document.querySelector('.preset-btn[data-for="en"]');
      if (presetBtn) presetBtn.click();
      const presetHi = document.querySelector('.preset-btn[data-for="hi"]');
      if (presetHi) presetHi.click();
      const warnCard = document.querySelector('.style-card[data-style="warning"]');
      if (warnCard) warnCard.click();
      const eventPill = document.querySelector('.action-type-pill[data-type="event"]');
      if (eventPill) eventPill.click();
    `
  });
  await sleep(1000);
  const shotFull = await chrome.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
  if (shotFull.result?.data) {
    fs.writeFileSync(path.join(artifactDir, 'admin_mobile_app_banner_interactive.png'), Buffer.from(shotFull.result.data, 'base64'));
    console.log('Saved admin_mobile_app_banner_interactive.png (fullpage)');
  }

  // 7. Test Hindi Language Toggle in Preview
  console.log('Testing Hindi language preview toggle...');
  await chrome.send('Runtime.evaluate', {
    expression: `
      const langHi = document.getElementById('lang-btn-hi');
      if (langHi) langHi.click();
    `
  });
  await sleep(800);
  await screenshot('admin_mobile_app_banner_hindi.png');

  await chrome.close();
  console.log('Done testing admin mobile app views!');
}

run().catch(console.error);
