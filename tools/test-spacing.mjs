import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

async function run() {
  const chrome = await launchChrome();

  const artifactDir = 'C:/Users/sunil/.gemini/antigravity/brain/837e7372-ddd0-44c6-8221-41fc000a4c42';
  const BASE = 'http://localhost:8099';

  await chrome.send('Emulation.setDeviceMetricsOverride', {
    width: 412,
    height: 915,
    deviceScaleFactor: 2,
    mobile: true,
  });

  const screenshot = async (filename) => {
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png' });
    if (shot.result?.data) {
      fs.writeFileSync(path.join(artifactDir, filename), Buffer.from(shot.result.data, 'base64'));
      console.log(`Saved screenshot: ${filename}`);
    }
  };

  // 1. Visit Welcome picker page
  console.log('Navigating to Welcome page...');
  await chrome.send('Page.navigate', { url: BASE });
  await sleep(2000);

  // Mark onboarding hidden (e.g. user already completed or skipped it, exactly like user's Image 1)
  await chrome.send('Runtime.evaluate', {
    expression: `
      const ob = document.querySelector('#onboarding-welcome');
      if (ob) ob.hidden = true;
      const target = document.querySelector('.event-card-wrap') || document.querySelector('.about-steps');
      if (target) target.scrollIntoView({ block: 'center' });
    `
  });
  await sleep(600);
  await screenshot('spacing_fixed_welcome_cards_to_about.png');

  // 2. Visit Rajasthan event home (/event/wordcamp-rajasthan-2026)
  console.log('Navigating to Rajasthan Event Home...');
  await chrome.send('Page.navigate', { url: `${BASE}/event/wordcamp-rajasthan-2026` });
  await sleep(2000);

  // Scroll to happening now / memories
  await chrome.send('Runtime.evaluate', {
    expression: `
      const target = document.querySelector('#happening-now-heading') || document.querySelector('.memories');
      if (target) target.scrollIntoView({ block: 'center' });
    `
  });
  await sleep(600);
  await screenshot('spacing_fixed_home_happening_now.png');

  await chrome.close();
  console.log('Done!');
}

run().catch(console.error);
