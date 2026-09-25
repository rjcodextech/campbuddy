// A small fake CampBuddy site for the browser tests: the REAL service worker
// (public/sw.js) and the REAL app modules (resources/js/attendee), serving pages
// shaped like the app's own (data-version meta, #app facts, screen links,
// images) — and a control endpoint to change the data, or take the site "down"
// (connections are dropped, as on dead wifi).

import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';

const ROOT = new URL('../../../', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const MODULES = path.join(ROOT, 'resources/js/attendee');
export const SLUG = 'wc-test';
const SCREENS = ['', '/my-day', '/quest', '/contribute', '/explore', '/camp-card', '/guide'];

// 1x1 PNG.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

/** The modules the page loads (real files), each served at /build/assets/<name>.js like the hashed build files. */
function moduleNames() {
  return fs.readdirSync(MODULES).filter((f) => f.endsWith('.js')).map((f) => f.replace(/\.js$/, ''));
}

// Some antivirus web shields block a URL called "analytics.js" on plain http; the test serves that one as telemetry.
const served = (name) => (name === 'analytics' ? 'telemetry' : name);
const original = (name) => (name === 'telemetry' ? 'analytics' : name);

export async function startSite() {
  const state = { version: 'v1', down: false, pagesFail: false, hits: {}, flags: '{\n    "swrPages": false\n}\n' };
  const count = (key) => { state.hits[key] = (state.hits[key] ?? 0) + 1; };

  // A second server on another port plays Gravatar: another origin, photos with
  // an open CORS header. It drops connections when the site is "down" too.
  const avatarServer = http.createServer((req, res) => {
    if (state.down) {
      req.socket.destroy();
      return;
    }
    count('avatar');
    res.writeHead(200, { 'Content-Type': 'image/png', 'Access-Control-Allow-Origin': '*', 'Cache-Control': 'max-age=300' });
    res.end(PNG);
  });
  await new Promise((resolve) => avatarServer.listen(0, '127.0.0.1', resolve));
  const avatarHost = `127.0.0.1:${avatarServer.address().port}`;
  const avatarBase = `http://${avatarHost}`;

  const page = (screen) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="campbuddy-data-version" content="${state.version}">
<title>${screen || 'home'} ${state.version}</title></head>
<body data-page-type="test" data-event-phase="during">
<div id="app" data-event-slug="${SLUG}" data-event-id="1" data-event-name="WordCamp Test" data-event-start="2026-10-10" data-event-end="2026-10-11" data-event-timezone="UTC"></div>
<h1 id="marker">PAGE ${screen || 'home'} ${state.version}</h1>
<nav>${SCREENS.map((s) => `<a href="/event/${SLUG}${s}">${s || 'home'}</a>`).join(' ')} <a href="/">picker</a> <a href="/event/${SLUG}/roster-removal">remove me</a></nav>
<img id="logo" src="/media/logo.png" alt="">
<img id="face1" src="${avatarBase}/avatar/one?s=192" alt="">
<img id="face2" src="${avatarBase}/avatar/two?s=192" alt="">
<script type="module" src="/build/assets/boot.js"></script>
</body></html>`;

  const BOOT = `
import { initDataFreshness } from './data-freshness.js';
import { warmOfflineCache } from './offline-warmup.js';
initDataFreshness();
window.addEventListener('load', async () => {
  try { await navigator.serviceWorker.register('/sw.js'); } catch (e) { window.__swError = String(e); }
  window.__warm = await warmOfflineCache();
});
`;

  const manifest = () => {
    const names = moduleNames().map(served);
    const entry = { 'resources/js/attendee/app.js': { file: 'assets/boot.js', imports: names.map((n) => `m:${n}`) } };
    for (const n of names) entry[`m:${n}`] = { file: `assets/${n}.js` };

    return entry;
  };

  const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://x');
    const route = url.pathname;

    if (route === '/control') {
      for (const [key, value] of url.searchParams) {
        if (key === 'version') state.version = value;
        if (key === 'down') state.down = value === '1';
        if (key === 'pagesFail') state.pagesFail = value === '1';
        if (key === 'flags') state.flags = value;
        if (key === 'reset') state.hits = {};
      }
      res.writeHead(200, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
      res.end(JSON.stringify({ ...state }));
      return;
    }

    if (state.down) {
      req.socket.destroy(); // dead wifi: the connection just drops
      return;
    }

    const send = (status, type, body, headers = {}) => {
      res.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-cache', ...headers });
      res.end(body);
    };

    // The real worker, with the photo host pointed at the fake one (the real one is secure.gravatar.com).
    if (route === '/sw.js') return send(200, 'text/javascript', fs.readFileSync(path.join(ROOT, 'public/sw.js'), 'utf8').replace("['secure.gravatar.com']", `['${avatarHost}']`), { 'Cache-Control': 'no-cache' });
    if (route === '/sw-flags.json') return send(200, 'application/json', state.flags, { 'Cache-Control': 'no-store' });
    if (route === '/build/manifest.json') return send(200, 'application/json', JSON.stringify(manifest()));
    if (route === '/build/assets/boot.js') return send(200, 'text/javascript', BOOT, { 'Cache-Control': 'public, max-age=31536000, immutable' });

    const moduleMatch = route.match(/^\/build\/assets\/([a-z-]+)\.js$/);
    if (moduleMatch) {
      const file = path.join(MODULES, `${original(moduleMatch[1])}.js`);
      if (!fs.existsSync(file)) return send(404, 'text/plain', 'not found');
      const code = fs.readFileSync(file, 'utf8').replaceAll('./analytics.js', './telemetry.js');

      return send(200, 'text/javascript', code, { 'Cache-Control': 'public, max-age=31536000, immutable' });
    }

    if (route === '/media/logo.png') return send(200, 'image/png', PNG, { 'Cache-Control': 'public, max-age=86400' });

    if (route.startsWith('/api/v1/events/') && route.endsWith('/data-version')) {
      count('data-version');
      return send(200, 'application/json', JSON.stringify({ version: state.version }), { ETag: `W/"${state.version}"` });
    }
    if (route === '/api/v1/cache-version') return send(200, 'application/json', JSON.stringify({ version: '0' }), { 'Cache-Control': 'no-store' });

    if (route === `/event/${SLUG}/manifest.json`) return send(200, 'application/manifest+json', JSON.stringify({ name: 'WordCamp Test', start_url: `/event/${SLUG}` }));

    if (route === '/' || route === '/guide' || route.startsWith(`/event/${SLUG}`)) {
      count(route);
      if (route.endsWith('/roster-removal')) return send(200, 'text/html', page('roster-removal'));
      if (route === '/guide') return send(200, 'text/html', page('general-guide'));
      const screen = route === '/' ? 'picker' : route.replace(`/event/${SLUG}`, '');
      if (route !== '/' && route !== '/guide' && !SCREENS.includes(screen)) return send(404, 'text/html', '<h1>Not found</h1>');
      if (state.pagesFail) return send(500, 'text/html', '<h1>Server error</h1>');

      return send(200, 'text/html; charset=utf-8', page(screen.replace('/', '')));
    }

    return send(404, 'text/plain', 'not found');
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const { port } = server.address();
  const base = `http://127.0.0.1:${port}`;
  const control = async (params) => (await fetch(`${base}/control?${new URLSearchParams(params)}`)).json();

  return {
    base, port, avatarBase, control, state,
    close: () => Promise.all([server, avatarServer].map((s) => new Promise((resolve) => { s.closeAllConnections?.(); s.close(resolve); }))),
  };
}
