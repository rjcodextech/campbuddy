import { launchChrome } from '../tests/browser/helpers/chrome.mjs';

const c = await launchChrome();
try {
  await c.goto('http://campbuddy.test');
  const sheets = await c.evaluate(`
    Array.from(document.querySelectorAll('link[rel=stylesheet]')).map(l => ({
      href: l.href,
      loaded: l.sheet != null,
      rules: l.sheet ? l.sheet.cssRules.length : 0
    }))
  `);
  console.log('Stylesheets:', JSON.stringify(sheets, null, 2));

  const scripts = await c.evaluate(`
    Array.from(document.querySelectorAll('script')).map(s => s.src || s.id || 'inline')
  `);
  console.log('Scripts:', JSON.stringify(scripts, null, 2));

  const errors = await c.evaluate(`window.__errors || []`);
  console.log('Errors:', errors);
} catch (e) {
  console.error(e);
} finally {
  await c.close();
}
