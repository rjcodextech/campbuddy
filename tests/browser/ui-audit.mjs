// UI audit on a real Chrome at phone size (390×844, touch): every attendee,
// admin and manager screen of a running local site. For each page it checks
// horizontal overflow (and which elements cause it), emoji left where line
// icons belong, tap targets under 40px, controls whose look differs from the
// rest (buttons, inputs), uncaught JS errors and the HTTP status; it saves a
// full-page screenshot next to its JSON report.
//
//   AUDIT_BASE=http://campbuddy.test AUDIT_EVENT=wordcamp-rajasthan-2026 \
//   AUDIT_ADMIN=email:password AUDIT_MANAGER=email:password node tests/browser/ui-audit.mjs [out-dir]
//
// Read-only apart from signing in: it follows no forms and taps nothing that writes.

import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from './helpers/chrome.mjs';

const BASE = process.env.AUDIT_BASE ?? 'http://campbuddy.test';
const EVENT = process.env.AUDIT_EVENT ?? 'wordcamp-rajasthan-2026';
const OUT = process.argv[2] ?? path.join(process.cwd(), 'storage/ui-audit');
const WIDTH = Number(process.env.AUDIT_WIDTH ?? 390);
const ids = JSON.parse(process.env.AUDIT_IDS ?? '{}');

const attendee = [
  ['picker', '/'],
  ['guide-public', '/guide'],
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
  ['admin-login', '/admin/login'],
  ['manager-login', '/manager/login'],
];

const e = ids.event ?? 1;
const admin = [
  ['admin-dashboard', '/admin'],
  ['admin-events', '/admin/events'],
  ['admin-events-table', '/admin/events?view=table'],
  ['admin-event-create', '/admin/events/create'],
  ['admin-event-edit', `/admin/events/${e}/edit`],
  ['admin-event-quests', `/admin/events/${e}/quests`],
  ['admin-event-offers', `/admin/events/${e}/offers`],
  ['admin-event-offer-create', `/admin/events/${e}/offers/create`],
  ...(ids.offer ? [['admin-event-offer-edit', `/admin/events/${e}/offers/${ids.offer}/edit`]] : []),
  ['admin-event-roster', `/admin/events/${e}/roster`],
  ['admin-event-deal-leads', `/admin/events/${e}/deal-leads`],
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
  ['admin-media', '/admin/media'],
  ['admin-profile', '/admin/profile'],
];

const manager = [
  ['manager-dashboard', '/manager'],
  ['manager-event', `/manager/events/${e}`],
  ['manager-information', `/manager/events/${e}/information`],
  ['manager-quests', `/manager/events/${e}/quests`],
];

// Runs in the page: everything the audit looks at.
const PROBE = `(() => {
  const vw = window.innerWidth;
  const visible = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && !el.closest('[hidden], template, dialog:not([open])'); };
  const name = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\\s+/).slice(0, 3).join('.') : '');
  const text = (el) => (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\\s+/g, ' ').slice(0, 40);

  // Overflow: what sticks out past the right edge (ignoring things inside a horizontal scroller on purpose).
  const scrollers = [...document.querySelectorAll('*')].filter((el) => { const s = getComputedStyle(el); return /(auto|scroll)/.test(s.overflowX) && el.scrollWidth > el.clientWidth; });
  const overflow = [...document.querySelectorAll('body *')].filter((el) => visible(el) && !scrollers.some((s) => s !== el && s.contains(el)))
    .filter((el) => el.getBoundingClientRect().right > vw + 1).map(name).slice(0, 8);

  // Emoji in visible text (line icons are SVG).
  const emojiRe = /(?![\\u00a9\\u00ae\\u2122])\\p{Extended_Pictographic}/u; // © ® ™ are type, not emoji
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const emoji = [];
  while (walker.nextNode()) {
    const node = walker.currentNode;
    if (!emojiRe.test(node.textContent) || !node.parentElement || !visible(node.parentElement) || node.parentElement.closest('script, style, textarea, input')) continue;
    emoji.push(name(node.parentElement) + ': ' + node.textContent.trim().slice(0, 30));
  }

  // Tap targets.
  const interactive = [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [role=tab], summary')].filter(visible);
  const small = interactive.filter((el) => { const r = el.getBoundingClientRect(); return (r.height < 40 || r.width < 24) && !el.closest('p, li > span, .footer-note') ; })
    .map((el) => { const r = el.getBoundingClientRect(); return name(el) + ' "' + text(el) + '" ' + Math.round(r.width) + 'x' + Math.round(r.height); }).slice(0, 12);

  // Looks: buttons and inputs grouped by their visual signature.
  const sig = (el, keys) => { const s = getComputedStyle(el); return keys.map((k) => s[k]).join(' | '); };
  const group = (els, keys) => { const m = {}; els.forEach((el) => { const k = sig(el, keys); (m[k] ||= []).push(name(el) + ' "' + text(el) + '"'); }); return Object.entries(m).map(([k, v]) => ({ look: k, count: v.length, e: v.slice(0, 3) })); };
  const buttons = [...document.querySelectorAll('button, .btn, .cb-btn, a[class*=btn], input[type=submit]')].filter(visible);
  const inputs = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea')].filter(visible);

  // Icons: inline SVG that isn't a sprite line icon, and <img> icons.
  const svgs = [...document.querySelectorAll('svg')].filter(visible);
  const iconKinds = { lineSprite: svgs.filter((s) => s.querySelector('use[href^="#li-"]')).length, inlineSvg: svgs.filter((s) => !s.querySelector('use')).length };

  return {
    title: document.title,
    width: vw,
    scrollWidth: document.documentElement.scrollWidth,
    overflow,
    emoji: emoji.slice(0, 12),
    smallTargets: small,
    buttonLooks: group(buttons, ['borderRadius', 'fontSize', 'fontWeight', 'height', 'fontFamily']).sort((a, b) => b.count - a.count),
    inputLooks: group(inputs, ['borderRadius', 'fontSize', 'height', 'borderTopWidth', 'borderTopColor']).sort((a, b) => b.count - a.count),
    iconKinds,
    errors: window.__auditErrors || [],
  };
})()`;

async function signIn(chrome, url, credentials) {
  const [email, password] = credentials.split(':');
  await chrome.goto(BASE + url);
  await chrome.evaluate(`(() => {
    const f = document.querySelector('form');
    f.querySelector('[name=email]').value = ${JSON.stringify(email)};
    f.querySelector('[name=password]').value = ${JSON.stringify(password)};
    f.submit();
  })()`);
  await sleep(2500);
}

async function audit(chrome, name, url) {
  const status = await chrome.goto(BASE + url);
  await sleep(1800);
  const report = await chrome.evaluate(PROBE);
  const { result } = await chrome.send('Page.getLayoutMetrics').then((r) => ({ result: r.result }));
  const height = Math.min(Math.ceil(result?.cssContentSize?.height ?? 2000), 9000);
  const shot = await chrome.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: WIDTH, height, scale: 1 } });
  if (shot.result?.data) fs.writeFileSync(path.join(OUT, `${name}.png`), Buffer.from(shot.result.data, 'base64'));

  return { name, url, status, ...report };
}

fs.mkdirSync(OUT, { recursive: true });
const chrome = await launchChrome();
await chrome.send('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: 844, deviceScaleFactor: 1, mobile: true });
await chrome.send('Emulation.setTouchEmulationEnabled', { enabled: true });
await chrome.send('Page.addScriptToEvaluateOnNewDocument', {
  source: "window.__auditErrors=[];addEventListener('error',e=>__auditErrors.push(String(e.message).slice(0,160)));addEventListener('unhandledrejection',e=>__auditErrors.push('promise: '+String(e.reason).slice(0,160)));",
});

const results = [];
try {
  for (const [name, url] of attendee) results.push(await audit(chrome, name, url));
  if (process.env.AUDIT_ADMIN) {
    await signIn(chrome, '/admin/login', process.env.AUDIT_ADMIN);
    for (const [name, url] of admin) results.push(await audit(chrome, name, url));
  }
  if (process.env.AUDIT_MANAGER) {
    await signIn(chrome, '/manager/login', process.env.AUDIT_MANAGER);
    for (const [name, url] of manager) results.push(await audit(chrome, name, url));
  }
} finally {
  await chrome.close();
}

fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(results, null, 2));
for (const r of results) {
  const flags = [
    r.status !== 200 && `HTTP ${r.status}`,
    r.scrollWidth > r.width && `overflow +${r.scrollWidth - r.width}px`,
    r.overflow?.length && `sticks out: ${r.overflow.length}`,
    r.emoji?.length && `emoji: ${r.emoji.length}`,
    r.smallTargets?.length && `small taps: ${r.smallTargets.length}`,
    r.errors?.length && `JS errors: ${r.errors.length}`,
  ].filter(Boolean);
  console.log(`${flags.length ? '!!' : 'ok'}  ${r.name.padEnd(26)} ${flags.join(' · ')}`);
}

// Fails the run (exit 1) on what must never ship: a page that doesn't open, sideways
// scroll on a phone, an uncaught JS error, or an emoji where a line icon belongs.
// Small tap targets are listed for a look, not failed: labels, inline links and
// ::after-extended hit areas can't be judged from the box alone.
const broken = results.filter((r) => r.status !== 200 || r.scrollWidth > r.width || r.errors?.length || r.emoji?.length);
console.log(`\n${results.length} screens at ${WIDTH}px — ${broken.length ? `${broken.length} broken: ${broken.map((r) => r.name).join(', ')}` : 'none broken'}. Report + screenshots: ${OUT}`);
process.exitCode = broken.length ? 1 : 0;
