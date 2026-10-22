// Text and layout audit at several device widths on a real Chrome: every
// attendee (PWA), admin and manager screen of a running local site. For each
// screen and width it runs the checks in helpers/text-probe.mjs (text cut off,
// tight line height, tiny type, words over words or pictures, text against a
// card edge, cramped columns, low contrast, broken or stretched pictures,
// sideways overflow, fonts that failed) and saves a full-page screenshot.
//
//   AUDIT_BASE=http://127.0.0.1:8011 AUDIT_EVENT=wordcamp-bengaluru-2026 AUDIT_IDS='{"event":35}' \
//   AUDIT_ADMIN=email:password AUDIT_MANAGER=email:password \
//   AUDIT_WIDTHS=320,360,390,414,768,1024,1440 node tests/browser/text-audit.mjs [out-dir]
//
// Read-only apart from signing in.

import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from './helpers/chrome.mjs';
import { TEXT_PROBE } from './helpers/text-probe.mjs';

const BASE = process.env.AUDIT_BASE ?? 'http://campbuddy.test';
const EVENT = process.env.AUDIT_EVENT ?? 'wordcamp-rajasthan-2026';
const OUT = process.argv[2] ?? path.join(process.cwd(), 'storage/text-audit');
const WIDTHS = (process.env.AUDIT_WIDTHS ?? '320,360,390,414,768,1024,1440').split(',').map(Number);
const ONLY = process.env.AUDIT_ONLY ? new RegExp(process.env.AUDIT_ONLY) : null;
const ids = JSON.parse(process.env.AUDIT_IDS ?? '{}');
const e = ids.event ?? 1;

const attendee = [
  ['picker', '/'],
  ['guide-public', '/guide'],
  ['brand-guidelines', '/brand-guidelines'],
  ['privacy', '/privacy'],
  ['terms', '/terms'],
  ['home', `/event/${EVENT}`],
  ['my-day', `/event/${EVENT}/my-day`],
  ['quest', `/event/${EVENT}/quest`],
  ['contribute', `/event/${EVENT}/contribute`],
  ['explore-people', `/event/${EVENT}/explore`],
  ['explore-sponsors', `/event/${EVENT}/explore?tab=sponsors`],
  ['explore-deals', `/event/${EVENT}/explore?tab=deals`],
  ['explore-free-steals', `/event/${EVENT}/explore?tab=free-steals`],
  ['explore-info', `/event/${EVENT}/explore?tab=info`],
  ['camp-card', `/event/${EVENT}/camp-card`],
  ['guide', `/event/${EVENT}/guide`],
  ['roster-removal', `/event/${EVENT}/roster-removal`],
  ['memories', `/event/${EVENT}/memories`],
  ['admin-login', '/admin/login'],
  ['manager-login', '/manager/login'],
];

const admin = [
  ['admin-dashboard', '/admin'],
  ['admin-analytics', '/admin/analytics'],
  ['admin-usage-post', '/admin/analytics/usage-post'],
  ['admin-events', '/admin/events'],
  ['admin-events-table', '/admin/events?view=table'],
  ['admin-event-create', '/admin/events/create'],
  ['admin-event-edit', `/admin/events/${e}/edit`],
  ['admin-event-quests', `/admin/events/${e}/quests`],
  ['admin-event-offers', `/admin/events/${e}/offers`],
  ['admin-event-offer-create', `/admin/events/${e}/offers/create`],
  ['admin-event-roster', `/admin/events/${e}/roster`],
  ['admin-event-deal-leads', `/admin/events/${e}/deal-leads`],
  ['admin-event-tables', `/admin/events/${e}/tables`],
  ['admin-event-delay', `/admin/events/${e}/delay`],
  ['admin-event-social', `/admin/events/${e}/social`],
  ['admin-event-qr', `/admin/events/${e}/qr`],
  ['admin-event-reports', `/admin/events/${e}/reports`],
  ['admin-event-memories', `/admin/events/${e}/memories`],
  ['admin-event-notifications', `/admin/events/${e}/notifications`],
  ['admin-deals', '/admin/deals'],
  ['admin-deal-create', '/admin/deals/create'],
  ...(ids.deal ? [['admin-deal-edit', `/admin/deals/${ids.deal}/edit`], ['admin-deal-leads', `/admin/deals/${ids.deal}/leads`]] : []),
  ['admin-free-steals', '/admin/free-steals'],
  ['admin-free-steal-create', '/admin/free-steals/create'],
  ...(ids.steal ? [['admin-free-steal-edit', `/admin/free-steals/${ids.steal}/edit`]] : []),
  ['admin-managers', '/admin/event-managers'],
  ['admin-manager-create', '/admin/event-managers/create'],
  ['admin-managers-activity', '/admin/event-managers/activity'],
  ...(ids.manager ? [['admin-manager-edit', `/admin/event-managers/${ids.manager}/edit`]] : []),
  ['admin-errors', '/admin/errors'],
  ['admin-feedback', '/admin/feedback'],
  ['admin-commands', '/admin/commands'],
  ['admin-media', '/admin/media'],
  ['admin-mobile-app', '/admin/mobile-app'],
  ['admin-legal', '/admin/legal'],
  ['admin-legal-edit', '/admin/legal/privacy/edit'],
  ['admin-profile', '/admin/profile'],
];

const manager = [
  ['manager-dashboard', '/manager'],
  ['manager-event', `/manager/events/${e}`],
  ['manager-information', `/manager/events/${e}/information`],
  ['manager-quests', `/manager/events/${e}/quests`],
  ['manager-attendees', `/manager/events/${e}/attendees`],
  ['manager-tables', `/manager/events/${e}/tables`],
  ['manager-delay', `/manager/events/${e}/delay`],
  ['manager-social', `/manager/events/${e}/social`],
  ['manager-qr', `/manager/events/${e}/qr`],
  ['manager-reports', `/manager/events/${e}/reports`],
  ['manager-memories', `/manager/events/${e}/memories`],
  ['manager-notifications', `/manager/events/${e}/notifications`],
];

async function signIn(chrome, url, credentials) {
  const at = credentials.indexOf(':');
  const [email, password] = [credentials.slice(0, at), credentials.slice(at + 1)];
  await chrome.goto(BASE + url);
  await chrome.evaluate(`(() => {
    const f = document.querySelector('form');
    f.querySelector('[name=email]').value = ${JSON.stringify(email)};
    f.querySelector('[name=password]').value = ${JSON.stringify(password)};
    f.submit();
  })()`);
  await sleep(3000);
}

async function audit(chrome, name, url, width) {
  await chrome.send('Emulation.setDeviceMetricsOverride', { width, height: width < 768 ? 844 : 900, deviceScaleFactor: 1, mobile: width < 1024 });
  const status = await chrome.goto(BASE + url);
  await sleep(2200);
  // Let reveal-on-scroll and lazy pictures show: walk to the foot of the page and back.
  await chrome.evaluate(`(async () => { const h = document.documentElement.scrollHeight; for (let y = 0; y < h; y += 600) { scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); } scrollTo(0, 0); await new Promise((r) => setTimeout(r, 500)); document.querySelectorAll('.reveal, [data-reveal]').forEach((el) => el.classList.add('is-visible', 'is-revealed')); return 1; })()`);
  const report = await chrome.evaluate(TEXT_PROBE);
  const metrics = await chrome.send('Page.getLayoutMetrics');
  const height = Math.min(Math.ceil(metrics.result?.cssContentSize?.height ?? 2000), 12000);
  const shot = await chrome.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width, height, scale: 1 } });
  fs.mkdirSync(path.join(OUT, String(width)), { recursive: true });
  if (shot.result?.data) fs.writeFileSync(path.join(OUT, String(width), `${name}.png`), Buffer.from(shot.result.data, 'base64'));

  return { name, url, width, status, ...report };
}

fs.mkdirSync(OUT, { recursive: true });
const chrome = await launchChrome();
await chrome.send('Security.setIgnoreCertificateErrors', { ignore: true });
await chrome.send('Emulation.setTouchEmulationEnabled', { enabled: true });
await chrome.send('Page.addScriptToEvaluateOnNewDocument', {
  source: "window.__auditErrors=[];addEventListener('error',e=>__auditErrors.push(String(e.message).slice(0,160)));addEventListener('unhandledrejection',e=>__auditErrors.push('promise: '+String(e.reason).slice(0,160)));",
});

const results = [];
const run = async (list) => {
  for (const [name, url] of list) {
    if (ONLY && !ONLY.test(name)) continue;
    for (const width of WIDTHS) {
      try {
        results.push(await audit(chrome, name, url, width));
      } catch (error) {
        results.push({ name, url, width, status: 'error', errors: [String(error.message)] });
      }
    }
    process.stdout.write('.');
  }
};
try {
  await run(attendee);
  if (process.env.AUDIT_ADMIN) {
    await signIn(chrome, '/admin/login', process.env.AUDIT_ADMIN);
    await run(admin);
  }
  if (process.env.AUDIT_MANAGER) {
    await signIn(chrome, '/manager/login', process.env.AUDIT_MANAGER);
    await run(manager);
  }
} finally {
  await chrome.close();
}

fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(results, null, 2));
const KEYS = ['clipped', 'ellipsis', 'tight', 'tiny', 'overlap', 'textOnImage', 'edge', 'cramped', 'contrast', 'images', 'overflow', 'fonts', 'errors'];
console.log('');
for (const r of results) {
  const flags = [r.status !== 200 && `HTTP ${r.status}`, r.scrollWidth > r.width && `sideways +${r.scrollWidth - r.width}px`, ...KEYS.map((k) => r[k]?.length && `${k} ${r[k].length}`)].filter(Boolean);
  console.log(`${flags.length ? '!!' : 'ok'}  ${String(r.width).padEnd(5)} ${r.name.padEnd(28)} ${flags.join(' · ')}`);
}
// What must never ship: a screen that does not open, sideways scroll, a JS error,
// words cut off, words over words, a broken picture or a font that did not load.
const broken = results.filter((r) => r.status !== 200 || r.scrollWidth > r.width || r.errors?.length || r.clipped?.length || r.overlap?.length || r.images?.length || r.fonts?.length);
console.log(`\n${results.length} screen×width checks — ${broken.length ? `${broken.length} need a fix` : 'none broken'}. Report + screenshots: ${OUT}`);
process.exitCode = broken.length ? 1 : 0;
