import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

const BASE = process.env.AUDIT_BASE ?? 'http://campbuddy.test';
const OUT = path.join(process.cwd(), 'storage/ui-audit-compare');
const WIDTH = 390;

const pages = [
  // 1. Root / Picker
  { name: '01_picker_home', url: '/' },
  { name: '02_legal_privacy', url: '/privacy' },
  { name: '03_legal_terms', url: '/terms' },

  // 2. WordCamp Rajasthan (Completed)
  { name: 'rajasthan_01_home', url: '/event/wordcamp-rajasthan-2026' },
  { name: 'rajasthan_02_my_day', url: '/event/wordcamp-rajasthan-2026/my-day' },
  { name: 'rajasthan_03_quest', url: '/event/wordcamp-rajasthan-2026/quest' },
  { name: 'rajasthan_04_contribute', url: '/event/wordcamp-rajasthan-2026/contribute' },
  { name: 'rajasthan_05_people', url: '/event/wordcamp-rajasthan-2026/explore' },
  { name: 'rajasthan_06_sponsors', url: '/event/wordcamp-rajasthan-2026/explore?tab=sponsors' },
  { name: 'rajasthan_07_deals', url: '/event/wordcamp-rajasthan-2026/explore?tab=deals' },
  { name: 'rajasthan_08_free_steals', url: '/event/wordcamp-rajasthan-2026/explore?tab=free-steals' },
  { name: 'rajasthan_09_info', url: '/event/wordcamp-rajasthan-2026/explore?tab=info' },
  { name: 'rajasthan_10_camp_card', url: '/event/wordcamp-rajasthan-2026/camp-card' },
  { name: 'rajasthan_11_guide', url: '/event/wordcamp-rajasthan-2026/guide' },
  { name: 'rajasthan_12_memories', url: '/event/wordcamp-rajasthan-2026/memories' },

  // 3. WordCamp Bengaluru (Upcoming)
  { name: 'bengaluru_01_home', url: '/event/wordcamp-bengaluru-2026' },
  { name: 'bengaluru_02_my_day', url: '/event/wordcamp-bengaluru-2026/my-day' },
  { name: 'bengaluru_03_quest', url: '/event/wordcamp-bengaluru-2026/quest' },
  { name: 'bengaluru_04_contribute', url: '/event/wordcamp-bengaluru-2026/contribute' },
  { name: 'bengaluru_05_people', url: '/event/wordcamp-bengaluru-2026/explore' },
  { name: 'bengaluru_06_sponsors', url: '/event/wordcamp-bengaluru-2026/explore?tab=sponsors' },
  { name: 'bengaluru_07_deals', url: '/event/wordcamp-bengaluru-2026/explore?tab=deals' },
  { name: 'bengaluru_08_free_steals', url: '/event/wordcamp-bengaluru-2026/explore?tab=free-steals' },
  { name: 'bengaluru_09_info', url: '/event/wordcamp-bengaluru-2026/explore?tab=info' },
  { name: 'bengaluru_10_camp_card', url: '/event/wordcamp-bengaluru-2026/camp-card' },
  { name: 'bengaluru_11_guide', url: '/event/wordcamp-bengaluru-2026/guide' },
];

const PROBE = `(() => {
  const vw = window.innerWidth;
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    const s = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && !el.closest('[hidden], template, dialog:not([open])') && !el.closest('details:not([open]) > :not(summary)');
  };
  const name = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\\s+/).slice(0, 3).join('.') : '');
  const text = (el) => (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\\s+/g, ' ').slice(0, 40);

  const scrollers = [...document.querySelectorAll('*')].filter((el) => {
    const s = getComputedStyle(el);
    return /(auto|scroll)/.test(s.overflowX) && el.scrollWidth > el.clientWidth;
  });
  const overflow = [...document.querySelectorAll('body *')]
    .filter((el) => visible(el) && !scrollers.some((s) => s !== el && s.contains(el)))
    .filter((el) => el.getBoundingClientRect().right > vw + 1).map(name).slice(0, 8);

  const emojiRe = /(?![\\u00a9\\u00ae\\u2122])\\p{Extended_Pictographic}/u;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const emoji = [];
  while (walker.nextNode()) {
    const node = walker.currentNode;
    if (!emojiRe.test(node.textContent) || !node.parentElement || !visible(node.parentElement) || node.parentElement.closest('script, style, textarea, input')) continue;
    emoji.push(name(node.parentElement) + ': ' + node.textContent.trim().slice(0, 30));
  }

  const interactive = [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [role=tab], summary')].filter(visible);
  const hit = (el) => {
    const label = el.matches('input') && el.closest('label');
    const r = (label || el).getBoundingClientRect();
    let w = r.width, h = r.height;
    for (const which of ['::before', '::after']) {
      const s = getComputedStyle(el, which);
      if (s.content === 'none' || s.position !== 'absolute') continue;
      const grow = (side) => Math.max(0, -parseFloat(s[side]) || 0);
      h = Math.max(h, r.height + grow('top') + grow('bottom'));
      w = Math.max(w, r.width + grow('left') + grow('right'));
    }
    return { w, h };
  };
  const small = interactive.filter((el) => {
    const { w, h } = hit(el);
    return (h < 40 || w < 24) && !el.closest('p, li > span, .footer-note') && !el.classList.contains('sr-only');
  }).map((el) => {
    const { w, h } = hit(el);
    return name(el) + ' "' + text(el) + '" ' + Math.round(w) + 'x' + Math.round(h);
  }).slice(0, 10);

  // Key content extract for feature comparison
  const headers = [...document.querySelectorAll('h1, h2, h3, .nav-tab, .tab-btn')].filter(visible).map(text).filter(Boolean);
  const actionButtons = [...document.querySelectorAll('button, a.btn, a[class*=btn], .event-card__memories a, .event-card__export')].filter(visible).map(text).filter(Boolean);

  return {
    title: document.title,
    width: vw,
    scrollWidth: document.documentElement.scrollWidth,
    overflow,
    emoji: emoji.slice(0, 10),
    smallTargets: small,
    headers: headers.slice(0, 20),
    actionButtons: actionButtons.slice(0, 25),
    errors: window.__auditErrors || [],
  };
})()`;

async function auditPage(chrome, page) {
  const status = await chrome.goto(BASE + page.url);
  await sleep(1500);
  const probe = await chrome.evaluate(PROBE);
  const { result } = await chrome.send('Page.getLayoutMetrics').then((r) => ({ result: r.result }));
  const height = Math.min(Math.max(Math.ceil(result?.cssContentSize?.height ?? 1200), 844), 8000);
  const shot = await chrome.send('Page.captureScreenshot', {
    format: 'png',
    captureBeyondViewport: true,
    clip: { x: 0, y: 0, width: WIDTH, height, scale: 1 },
  });
  const shotPath = path.join(OUT, `${page.name}.png`);
  if (shot.result?.data) {
    fs.writeFileSync(shotPath, Buffer.from(shot.result.data, 'base64'));
  }

  return {
    ...page,
    status,
    height,
    shotPath,
    ...probe,
  };
}

fs.mkdirSync(OUT, { recursive: true });
console.log(`Starting PWA Audit on ${BASE} at width ${WIDTH}px...`);

const chrome = await launchChrome();
await chrome.send('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: 844, deviceScaleFactor: 1, mobile: true });
await chrome.send('Emulation.setTouchEmulationEnabled', { enabled: true });
await chrome.send('Page.addScriptToEvaluateOnNewDocument', {
  source: "window.__auditErrors=[];addEventListener('error',e=>__auditErrors.push(String(e.message).slice(0,160)));addEventListener('unhandledrejection',e=>__auditErrors.push('promise: '+String(e.reason).slice(0,160)));",
});

const report = [];
try {
  for (const page of pages) {
    process.stdout.write(`Capturing ${page.name}... `);
    const res = await auditPage(chrome, page);
    report.push(res);
    console.log(`HTTP ${res.status} | Height: ${res.height}px | Overflow: ${res.overflow.length} | Errors: ${res.errors.length}`);
  }
} finally {
  await chrome.close();
}

fs.writeFileSync(path.join(OUT, 'audit-summary.json'), JSON.stringify(report, null, 2));
console.log(`\nAudit complete! ${report.length} screengrabs saved to ${OUT}`);
