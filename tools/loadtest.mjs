#!/usr/bin/env node
// CampBuddy load test — read-only, no dependencies (Node 18+).
//
// Simulates attendees using the app the way real phones do, and reports how
// the site copes: response times (p50/p90/p95/p99), errors, rate-limit hits,
// how many requests reached the origin (Cloudflare's cf-cache-status), and how
// it behaves over time. It only ever sends GET requests — nothing is
// created, joined, waved at or bookmarked — so it is safe on live data, but
// it IS real traffic: run it on your own site, at a quiet hour, and ramp up.
//
//   node tools/loadtest.mjs --url https://your-site.example --event wordcamp-xyz-2026 \
//        --users 100 --duration 90 --i-own-this-site
//
// Suggested ramp (each run ~2 minutes, watch the host's CPU/"resource usage"
// in cPanel while it runs): 50 → 200 → 500 → 1000 → 2000 users. Ctrl+C stops
// early and still prints the report.
//
// What each virtual user does (weights are the defaults below):
//   - first visit: the event's home page and its /build/ assets
//   - then, every ~--think seconds: opens another tab (My Day, Explore, Quest,
//     Contribute, Camp Card, Guide — HTML pages); on Explore also the roster
//     (every page, like the app does) or the discovery list
//   - every ~--poll seconds: the small "data-version" freshness check
//   Each user has its own device id (UUID), as each phone does, so the API's
//   per-phone rate limits behave realistically. All users share YOUR IP, like a
//   whole venue behind one wifi — so 429s from the per-address limit
//   (RATE_LIMIT_ADDRESS_READS) show up here exactly as they would at a venue.
//
// Safety: a cap on requests in flight and on requests per second, a ramp-up,
// and an automatic stop when the site is clearly struggling (10% errors, 40%
// rate-limited, or a 20 s p95 over the last 200 requests).
//
// Exit code: 0 = PASS/WARN, 1 = FAIL or aborted (usable in CI).

import { randomUUID } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

// ---------------------------------------------------------------- options --

const DEFAULTS = {
  users: 50,
  duration: 60, // seconds of steady traffic after the ramp
  ramp: 15, // seconds over which users arrive
  think: 20, // average seconds between a user's page opens
  poll: 300, // seconds between data-version checks (the app uses ~5 min)
  'max-inflight': 50,
  'max-rps': 100,
  'slo-p95': 2000, // ms — page/API p95 target
  'slo-errors': 1, // % — 5xx + network errors allowed
  assets: 'first', // first | none
};

function parseArgs(argv) {
  const out = { ...DEFAULTS };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (!a.startsWith('--')) continue;
    const key = a.slice(2);
    const next = argv[i + 1];
    if (next === undefined || next.startsWith('--')) {
      out[key] = true;
    } else {
      out[key] = Number.isNaN(Number(next)) || key === 'url' || key === 'event' || key === 'out' || key === 'assets' ? next : Number(next);
      i++;
    }
  }
  return out;
}

const opt = parseArgs(process.argv.slice(2));

function fail(message) {
  console.error(`\n✖ ${message}\n`);
  process.exit(2);
}

if (!opt.url || opt.url === true) {
  fail('Give the site with --url https://your-site.example (see the top of this file).');
}

let base;
try {
  base = new URL(opt.url);
  if (!/^https?:$/.test(base.protocol)) throw new Error('not http(s)');
} catch {
  fail(`"${opt.url}" is not a valid http(s) address.`);
}

const isLocal = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(base.hostname) || base.hostname.endsWith('.test') || base.hostname.endsWith('.local');
if (!isLocal && !opt['i-own-this-site']) {
  fail(`${base.origin} is not a local address. This sends real traffic — add --i-own-this-site to confirm it's your site and you want to test it.`);
}
if (opt.users > 2000 && !opt['force-big']) {
  fail('More than 2000 virtual users from one machine is rarely useful (and one IP hits the per-address limit first). Add --force-big if you really mean it.');
}

const ORIGIN = base.origin;
const USERS = Math.max(1, Math.floor(opt.users));
const DURATION_MS = Math.max(5, opt.duration) * 1000;
const RAMP_MS = Math.max(0, opt.ramp) * 1000;
const THINK_S = Math.max(1, opt.think);
const POLL_S = Math.max(5, opt.poll);

// ---------------------------------------------------------------- helpers --

const now = () => performance.now();
const rand = Math.random;
const jitter = (s) => s * (0.8 + rand() * 0.4); // ±20 %
const exp = (mean) => -Math.log(1 - rand()) * mean; // exponential think time — users never march in step

function pick(weighted) {
  let r = rand() * weighted.reduce((t, [, w]) => t + w, 0);
  for (const [value, w] of weighted) {
    if ((r -= w) <= 0) return value;
  }
  return weighted[0][0];
}

function percentile(sorted, p) {
  if (sorted.length === 0) return 0;
  return sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))];
}

// A cap on concurrent requests and on requests started per second.
let inflight = 0;
const waiting = [];
async function acquire() {
  if (inflight < opt['max-inflight']) {
    inflight++;
    return;
  }
  await new Promise((resolve) => waiting.push(resolve));
}
function release() {
  const next = waiting.shift();
  if (next) next();
  else inflight--;
}
let nextSlot = 0;
async function rateGate() {
  const gap = 1000 / opt['max-rps'];
  const t = now();
  nextSlot = Math.max(nextSlot, t) + gap;
  const wait = nextSlot - gap - t;
  if (wait > 1) await nap(wait);
}

// ---------------------------------------------------------------- results --

const stats = new Map(); // kind -> { lat: number[], bytes: number, codes: Map }
const cache = new Map(); // cf-cache-status -> count
const recent = []; // last 200 outcomes for the auto-stop check
const timeline = new Map(); // 10 s bucket -> { n, err, lat: number[] }
let started = 0;
let aborted = null;
const stop = new AbortController();

/** Ends the run early: users stop at their next wake-up and the report is printed. */
function halt(reason) {
  if (aborted) return;
  aborted = reason;
  stop.abort();
}

/** A pause that ends immediately when the run is halted. */
const nap = (ms) => sleep(Math.max(0, ms), undefined, { signal: stop.signal }).catch(() => {});

function record(kind, status, ms, bytes, cfStatus) {
  const s = stats.get(kind) ?? { lat: [], bytes: 0, codes: new Map() };
  s.lat.push(ms);
  s.bytes += bytes;
  s.codes.set(status, (s.codes.get(status) ?? 0) + 1);
  stats.set(kind, s);

  cache.set(cfStatus ?? '(no Cloudflare header)', (cache.get(cfStatus ?? '(no Cloudflare header)') ?? 0) + 1);

  const isErr = status === 0 || status >= 500;
  const bucket = Math.floor((now() - started) / 10000);
  const t = timeline.get(bucket) ?? { n: 0, err: 0, lat: [] };
  t.n++;
  if (isErr) t.err++;
  t.lat.push(ms);
  timeline.set(bucket, t);

  recent.push({ status, ms });
  if (recent.length > 200) recent.shift();
  if (recent.length === 200 && !aborted) {
    const errs = recent.filter((r) => r.status === 0 || r.status >= 500).length / 200;
    const limited = recent.filter((r) => r.status === 429).length / 200;
    const p95 = percentile(recent.map((r) => r.ms).sort((a, b) => a - b), 95);
    if (errs > 0.1) halt(`Stopped: ${(errs * 100).toFixed(0)}% of the last 200 requests failed (5xx / network).`);
    else if (limited > 0.4) halt(`Stopped: ${(limited * 100).toFixed(0)}% of the last 200 requests were rate-limited (429) — a limit was reached, not a crash.`);
    else if (p95 > 20000) halt(`Stopped: p95 over the last 200 requests is ${(p95 / 1000).toFixed(1)} s — the site is overloaded.`);
  }
}

async function request(kind, path, { api = false, device = null, wantBody = false } = {}) {
  if (aborted) return null;
  await rateGate();
  await acquire();
  const t0 = now();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 30000);
  let status = 0;
  let bytes = 0;
  let cf = null;
  let body = null;
  try {
    const res = await fetch(ORIGIN + path, {
      signal: controller.signal,
      redirect: 'manual',
      headers: {
        'User-Agent': 'CampBuddy-LoadTest/1.0 (+read-only)',
        Accept: api ? 'application/json' : 'text/html,*/*',
        ...(device ? { 'X-CampBuddy-Device': device } : {}),
      },
    });
    status = res.status;
    cf = res.headers.get('cf-cache-status');
    const buf = await res.arrayBuffer();
    bytes = buf.byteLength;
    if (wantBody) body = new TextDecoder().decode(buf);
  } catch {
    status = 0; // timeout / connection error
  } finally {
    clearTimeout(timer);
    release();
  }
  record(kind, status, now() - t0, bytes, cf);
  return { status, body };
}

// ------------------------------------------------------------ user model --

const PAGES = [
  ['', 20], // home
  ['/my-day', 30],
  ['/explore', 20],
  ['/quest', 10],
  ['/contribute', 5],
  ['/camp-card', 5],
  ['/guide', 10],
];

let EVENT = typeof opt.event === 'string' ? opt.event : null;

async function detectEvent() {
  const res = await fetch(ORIGIN + '/', { headers: { 'User-Agent': 'CampBuddy-LoadTest/1.0 (+read-only)' } }).catch(() => null);
  if (!res || !res.ok) fail(`Could not open ${ORIGIN}/ (status ${res?.status ?? 'no response'}). Is the address right?`);
  const html = await res.text();
  const m = html.match(/\/event\/([a-z0-9-]+)/i);
  if (!m) fail('No event found on the home page — pass one with --event <slug>.');
  return m[1];
}

async function fetchAssets(html) {
  if (opt.assets === 'none') return;
  const urls = [...new Set([...html.matchAll(/(?:href|src)="(\/build\/[^"]+)"/g)].map((m) => m[1]))];
  await Promise.all(urls.map((u) => request('asset', u)));
}

async function navigate(device, first) {
  const suffix = first ? '' : pick(PAGES);
  const path = `/event/${EVENT}${suffix}`;
  const page = await request(first ? 'page:home (first visit)' : `page:${suffix || 'home'}`, path, { wantBody: first });
  if (first && page?.body) await fetchAssets(page.body);

  if (suffix === '/explore') {
    if (rand() < 0.6) {
      const r = await request('api:roster', `/api/v1/events/${EVENT}/roster`, { api: true, device, wantBody: true });
      let last = 1;
      try {
        last = JSON.parse(r?.body ?? '{}').last_page ?? 1;
      } catch {
        // not JSON (error page) — nothing more to fetch
      }
      if (last > 1) {
        await Promise.all(Array.from({ length: last - 1 }, (_, i) => request('api:roster', `/api/v1/events/${EVENT}/roster?page=${i + 2}`, { api: true, device })));
      }
    } else {
      await request('api:discovery', `/api/v1/events/${EVENT}/discovery`, { api: true, device });
    }
  }
}

async function virtualUser(i, endAt) {
  const device = randomUUID();
  await nap((RAMP_MS * i) / USERS);
  if (aborted || now() > endAt) return;

  await navigate(device, true);
  let nextPoll = now() + jitter(POLL_S) * 1000;

  while (!aborted && now() < endAt) {
    const wait = Math.min(exp(THINK_S) * 1000, Math.max(0, nextPoll - now()), Math.max(0, endAt - now()));
    await nap(wait);
    if (aborted || now() >= endAt) break;
    if (now() >= nextPoll) {
      await request('api:data-version', `/api/v1/events/${EVENT}/data-version`, { api: true, device });
      nextPoll = now() + jitter(POLL_S) * 1000;
    } else {
      await navigate(device, false);
    }
  }
}

// ----------------------------------------------------------------- report --

function fmt(n, d = 0) {
  return n.toFixed(d);
}

function report(elapsedS) {
  const rows = [];
  let total = 0;
  let errs = 0;
  let limited = 0;
  const allLat = [];
  let bytes = 0;
  for (const [kind, s] of [...stats.entries()].sort()) {
    const lat = [...s.lat].sort((a, b) => a - b);
    const n = lat.length;
    const c = (pred) => [...s.codes].filter(([code]) => pred(code)).reduce((t, [, k]) => t + k, 0);
    const e5 = c((code) => code >= 500);
    const net = c((code) => code === 0);
    const l429 = c((code) => code === 429);
    const other4 = c((code) => code >= 400 && code < 500 && code !== 429);
    total += n;
    errs += e5 + net;
    limited += l429;
    bytes += s.bytes;
    allLat.push(...lat);
    rows.push({ kind, n, rps: n / elapsedS, ok: n - e5 - net - l429 - other4, e5, net, l429, other4, p50: percentile(lat, 50), p90: percentile(lat, 90), p95: percentile(lat, 95), p99: percentile(lat, 99), max: lat[n - 1] ?? 0, kb: s.bytes / n / 1024 });
  }

  allLat.sort((a, b) => a - b);
  const pad = (s, n) => String(s).padEnd(n);
  const num = (s, n) => String(s).padStart(n);
  console.log(`\n${'='.repeat(100)}\nCampBuddy load test — ${ORIGIN}  event=${EVENT}\n${USERS} virtual users · ${fmt(elapsedS)} s · think ${THINK_S} s · poll ${POLL_S} s · caps ${opt['max-inflight']} in flight / ${opt['max-rps']} req/s\n${'='.repeat(100)}`);
  console.log(`${pad('request', 26)}${num('count', 7)}${num('req/s', 7)}${num('ok', 7)}${num('5xx', 6)}${num('net', 5)}${num('429', 6)}${num('4xx', 5)}${num('p50', 7)}${num('p90', 7)}${num('p95', 7)}${num('p99', 7)}${num('max', 8)}${num('KB', 7)}`);
  for (const r of rows) {
    console.log(`${pad(r.kind, 26)}${num(r.n, 7)}${num(fmt(r.rps, 1), 7)}${num(r.ok, 7)}${num(r.e5, 6)}${num(r.net, 5)}${num(r.l429, 6)}${num(r.other4, 5)}${num(fmt(r.p50), 7)}${num(fmt(r.p90), 7)}${num(fmt(r.p95), 7)}${num(fmt(r.p99), 7)}${num(fmt(r.max), 8)}${num(fmt(r.kb, 1), 7)}`);
  }
  console.log('(times in ms; "net" = timeout or connection error; "429" = rate-limited)\n');

  console.log('Over time (10 s buckets):  req/s   p95 ms   errors');
  for (const [bucket, t] of [...timeline.entries()].sort((a, b) => a[0] - b[0])) {
    const lat = t.lat.sort((a, b) => a - b);
    console.log(`  t+${String(bucket * 10).padStart(4)} s               ${num(fmt(t.n / 10, 1), 6)}   ${num(fmt(percentile(lat, 95)), 6)}   ${t.err ? `${t.err} (${fmt((t.err / t.n) * 100, 1)}%)` : '-'}`);
  }

  const cacheLine = [...cache.entries()].map(([k, v]) => `${k}: ${v}`).join('   ');
  console.log(`\nCloudflare cache status of responses:  ${cacheLine}`);
  console.log('  (HIT = served by Cloudflare without touching your host; DYNAMIC/BYPASS/none = your host did the work)');

  const p95 = percentile(allLat, 95);
  const errRate = total ? (errs / total) * 100 : 0;
  const limitedRate = total ? (limited / total) * 100 : 0;
  console.log(`\nTotals: ${total} requests · ${fmt(total / elapsedS, 1)} req/s · ${fmt(bytes / 1024 / 1024, 1)} MB · p95 ${fmt(p95)} ms · errors ${fmt(errRate, 2)}% · rate-limited ${fmt(limitedRate, 1)}%`);

  let verdict = 'PASS';
  const notes = [];
  if (aborted) {
    verdict = 'FAIL';
    notes.push(aborted);
  }
  if (errRate > opt['slo-errors']) {
    verdict = 'FAIL';
    notes.push(`error rate ${fmt(errRate, 2)}% is above the ${opt['slo-errors']}% target`);
  }
  if (p95 > opt['slo-p95']) {
    verdict = verdict === 'FAIL' ? 'FAIL' : 'WARN';
    notes.push(`p95 ${fmt(p95)} ms is above the ${opt['slo-p95']} ms target`);
  }
  if (limitedRate > 1) {
    if (verdict === 'PASS') verdict = 'WARN';
    notes.push(`${fmt(limitedRate, 1)}% of requests were rate-limited: all users share this machine's IP (like a venue behind one wifi), so the per-address limit RATE_LIMIT_ADDRESS_READS was reached — raise it in .env for big venues`);
  }
  console.log(`\nVerdict: ${verdict}${notes.length ? '\n  - ' + notes.join('\n  - ') : ''}\n`);

  if (typeof opt.out === 'string') {
    writeFileSync(opt.out, JSON.stringify({ url: ORIGIN, event: EVENT, users: USERS, elapsedS, total, p95, errRate, limitedRate, verdict, notes, rows, cache: Object.fromEntries(cache) }, null, 2));
    console.log(`Report saved to ${opt.out}\n`);
  }
  return verdict === 'FAIL' ? 1 : 0;
}

// ------------------------------------------------------------------- main --

async function main() {
  EVENT ??= await detectEvent();

  const perUserPerSec = 1 / THINK_S + 1 / POLL_S;
  const expected = USERS * perUserPerSec * 1.25; // a bit more for roster/discovery/assets
  console.log(`Plan: ${USERS} users over a ${opt.ramp} s ramp + ${opt.duration} s, about ${fmt(expected, 0)} requests/s at full load (cap ${opt['max-rps']}/s).`);
  if (expected > opt['max-rps']) console.log(`  Note: that is above --max-rps, so the test will be capped at ${opt['max-rps']} req/s (raise --max-rps to push harder).`);
  if (opt['dry-run']) return 0;

  started = now();
  nextSlot = started;
  const endAt = started + RAMP_MS + DURATION_MS;
  let stopped = false;
  process.on('SIGINT', () => {
    if (stopped) process.exit(1);
    stopped = true;
    halt('Stopped by you (Ctrl+C) — partial results below.');
  });

  await Promise.all(Array.from({ length: USERS }, (_, i) => virtualUser(i, endAt)));
  return report((now() - started) / 1000);
}

main().then((code) => process.exit(code));
