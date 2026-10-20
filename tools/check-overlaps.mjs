// Finds cards that touch or sit on top of each other on the attendee pages, at phone and desktop width.
//   node tools/check-overlaps.mjs [base-url] [event-slug]
// A "card" is any visible box with a border or a background of its own. Two cards that are not inside
// one another must not overlap, and one stacked under another must keep at least MIN_GAP px between them.
// Left out on purpose: decoration (aria-hidden), the inside of a closed <details>, and input groups
// (a prefix joined to its field) and table cells, which are meant to touch.
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

const BASE = process.argv[2] ?? 'http://127.0.0.1:8099';
const SLUG = process.argv[3] ?? 'wordcamp-demo-2026';
const MIN_GAP = 4;
const EVENT_PAGES = ['', '/my-day', '/quest', '/contribute', '/explore', '/camp-card', '/guide', '/memories'];
const PAGES = ['/', '/guide', '/privacy', '/terms', ...EVENT_PAGES.map((p) => `/event/${SLUG}${p}`)];
const WIDTHS = [[390, 844, true], [1280, 900, false]];

const SCAN = `(() => {
  const MIN_GAP = ${MIN_GAP};
  const fixedUp = (el) => { for (let e = el; e; e = e.parentElement) { const p = getComputedStyle(e).position; if (p === 'fixed' || p === 'sticky') return true; } return false; };
  const isBox = (el) => {
    const s = getComputedStyle(el);
    if (s.visibility === 'hidden' || s.display === 'none' || Number(s.opacity) === 0) return false;
    const r = el.getBoundingClientRect();
    if (r.width < 40 || r.height < 24) return false;
    const border = ['Top', 'Right', 'Bottom', 'Left'].some((k) => parseFloat(s['border' + k + 'Width']) > 0 && s['border' + k + 'Style'] !== 'none');
    const bg = s.backgroundColor !== 'rgba(0, 0, 0, 0)' && s.backgroundColor !== 'transparent' || s.backgroundImage !== 'none';
    return (border && bg) || (border && parseFloat(s.borderRadius) > 0);
  };
  const root = document.querySelector('main') || document.body;
  const boxes = [...root.querySelectorAll('*')].filter((el) => isBox(el) && !fixedUp(el) && !el.closest('dialog,[role=dialog],.sheet,[hidden],[aria-hidden=true]') && !(el.closest('details:not([open])') && !el.closest('summary')) && !el.closest('.form-input-wrap') && !['TR', 'TD', 'TH', 'THEAD', 'TBODY'].includes(el.tagName));
  const label = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '');
  const out = [];
  for (let i = 0; i < boxes.length; i++) {
    for (let j = i + 1; j < boxes.length; j++) {
      const a = boxes[i], b = boxes[j];
      if (a.contains(b) || b.contains(a)) continue;
      const A = a.getBoundingClientRect(), B = b.getBoundingClientRect();
      const ox = Math.min(A.right, B.right) - Math.max(A.left, B.left);
      const oy = Math.min(A.bottom, B.bottom) - Math.max(A.top, B.top);
      if (ox > 2 && oy > 2) { out.push({ kind: 'overlap', a: label(a), b: label(b), px: Math.round(Math.min(ox, oy)) }); continue; }
      // Two parts of one card (an event card and its footer button in one framed wrap) may touch.
      let common = a.parentElement;
      while (common && !common.contains(b)) common = common.parentElement;
      let framed = false;
      for (let e = common; e && e !== root && !framed; e = e.parentElement) framed = isBox(e) || getComputedStyle(e).overflow === 'hidden' && parseFloat(getComputedStyle(e).borderRadius) > 0;
      // The picker's finished-event card has its "Save your WordCamp Data" button joined on by design.
      if (framed || common?.classList.contains('event-card-wrap')) continue;
      // Stacked: they share width, and the gap between them is smaller than MIN_GAP.
      if (ox > Math.min(A.width, B.width) * 0.5) {
        const gap = B.top >= A.bottom ? B.top - A.bottom : A.top - B.bottom;
        if (gap >= 0 && gap < MIN_GAP) out.push({ kind: 'touching', a: label(a), b: label(b), px: Math.round(gap) });
      }
      // Side by side with no room between them.
      if (oy > Math.min(A.height, B.height) * 0.5) {
        const gap = B.left >= A.right ? B.left - A.right : A.left - B.right;
        if (gap >= 0 && gap < MIN_GAP) out.push({ kind: 'touching-side', a: label(a), b: label(b), px: Math.round(gap) });
      }
    }
  }
  // Scrolled to the very end, nothing may stay hidden under a bar fixed to the bottom (the tab bar).
  window.scrollTo(0, document.documentElement.scrollHeight);
  const bars = [...document.querySelectorAll('body *')].filter((e) => getComputedStyle(e).position === 'fixed' && e.getBoundingClientRect().bottom >= innerHeight - 2 && e.getBoundingClientRect().height > 30 && e.getBoundingClientRect().width > innerWidth * 0.6);
  if (bars.length) {
    const barTop = Math.min(...bars.map((b) => b.getBoundingClientRect().top));
    const lastBottom = Math.max(...boxes.map((b) => b.getBoundingClientRect().bottom));
    if (lastBottom > barTop + 1) out.push({ kind: 'under-bottom-bar', a: 'page end', b: label(bars[0]), px: Math.round(lastBottom - barTop) });
  }
  window.scrollTo(0, 0);
  return out;
})()`;

const chrome = await launchChrome();
let problems = 0;
try {
  for (const [width, height, mobile] of WIDTHS) {
    await chrome.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
    for (const page of PAGES) {
      await chrome.send('Page.navigate', { url: `${BASE}${page}` });
      await sleep(3500);
      // Close first-run popups so the page itself is measured.
      await chrome.evaluate(`document.querySelectorAll('dialog[open]').forEach((d) => d.close()); 1`);
      const found = (await chrome.evaluate(SCAN)) ?? [];
      const seen = new Set();
      const unique = found.filter((f) => { const k = f.kind + f.a + f.b; if (seen.has(k)) return false; seen.add(k); return true; });
      console.log(`${width}px ${page}:${unique.length ? unique.length + ' problem(s)' : 'ok'}`);
      unique.slice(0, 15).forEach((f) => console.log(`   ${f.kind} ${f.px}px  ${f.a}  <->  ${f.b}`));
      problems += unique.length;
    }
  }
} finally {
  chrome.close?.();
}
console.log(problems ? `\n${problems} problem(s).` : '\nNo touching or overlapping cards.');
process.exit(problems ? 1 : 0);
