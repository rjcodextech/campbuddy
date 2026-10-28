import { launchChrome } from '../tests/browser/helpers/chrome.mjs';

const c = await launchChrome();
try {
  await c.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 2, mobile: false });
  await c.goto('http://campbuddy.test/');
  await new Promise(r => setTimeout(r, 600));
  await c.evaluate('document.querySelector("#desktop-notice")?.close();');
  const cards = await c.evaluate(`
    Array.from(document.querySelectorAll('.card-grid > *')).map(el => ({
      class: el.className,
      text: el.innerText.split('\\n')[0],
      width: el.getBoundingClientRect().width,
      height: el.getBoundingClientRect().height,
      html: el.outerHTML.slice(0, 300)
    }))
  `);
  console.log('Cards:', JSON.stringify(cards, null, 2));
} finally {
  await c.close();
}
