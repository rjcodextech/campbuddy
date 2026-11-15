// Shared by tools/audit-social.mjs and tools/audit-reels.mjs in --harness mode: the harness server
// (server.mjs), headless Chrome with the canvas probe (contrast on), the sample events loaded.
import fs from 'node:fs';
import path from 'node:path';
import { launchChrome } from '../../tests/browser/helpers/chrome.mjs';
import { CANVAS_HOOK } from '../../tests/browser/helpers/canvas-probe.mjs';
import { startHarness } from './server.mjs';

/** `--name value` / `--name=value` from argv, else `fallback`. */
export function flag(argv, name, fallback = null) {
  const i = argv.findIndex((a) => a === `--${name}` || a.startsWith(`--${name}=`));
  if (i < 0) return fallback;
  return argv[i].includes('=') ? argv[i].split('=').slice(1).join('=') : (argv[i + 1] ?? fallback);
}

export const list = (value, all) => (value ? String(value).split(',').map((s) => s.trim()).filter(Boolean) : all);

export async function openHarness({ src } = {}) {
  const server = await startHarness({ src });
  const chrome = await launchChrome();
  const js = async (expr) => {
    const v = await chrome.evaluate(expr);
    if (typeof v === 'string' && v.startsWith('HARNESS ERROR')) throw new Error(v);
    return v;
  };
  await chrome.send('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  await chrome.send('Page.addScriptToEvaluateOnNewDocument', { source: `${CANVAS_HOOK}\nwindow.__probeContrast = true;` });
  await chrome.goto(`${server.base}/`);
  await chrome.waitFor('window.__ready === true', { timeout: 30000 });
  const samples = JSON.parse(await js('window.__h.init().then(JSON.stringify).catch((e) => "HARNESS ERROR " + e.stack)'));
  /** Calls window.__h.<method>(...args) and returns its value (an error in the page throws here). */
  const call = async (method, ...args) => {
    const v = await js(`Promise.resolve(window.__h.${method}(${args.map((a) => JSON.stringify(a)).join(', ')})).then((v) => JSON.stringify(v ?? null)).catch((e) => "HARNESS ERROR " + e.stack)`);
    return JSON.parse(v);
  };
  const close = async () => { await chrome.close(); await server.close(); };
  return { chrome, js, call, samples, close };
}

export function saveDataUrl(file, data) {
  if (!data) return;
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, Buffer.from(data.split(',')[1], 'base64'));
}

/** The report: every picture with an issue, the tally by kind; the warnings apart. */
export function summarise(report, out, label) {
  fs.mkdirSync(out, { recursive: true });
  fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
  const bad = report.filter((r) => r.issues.length);
  const tally = {};
  const warned = {};
  for (const r of report) {
    for (const [kind] of r.issues) tally[kind] = (tally[kind] ?? 0) + 1;
    for (const [kind] of r.warnings ?? []) warned[kind] = (warned[kind] ?? 0) + 1;
  }
  console.log(`${label}: ${report.length} checked, ${bad.length} with issues`, tally, 'warnings:', warned);
  for (const r of bad.slice(0, Number(process.env.SHOW ?? 25))) console.log(' ✗', r.file ?? r.reel, r.issues.slice(0, 4).map((i) => i.join(': ')).join(' | '));
  return bad.length;
}
