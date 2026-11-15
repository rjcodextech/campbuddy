// A tiny static server for the social harness (tools/social-harness/index.html): the REAL drawing modules
// (resources/js/social-kit.js, social-reels.js, qr-kit.js…) served as they are, with the brand fonts and
// logos, so posts, people cards and reels can be drawn and audited without the admin panel — no login, no
// database, no build. Bare imports (qrcode-generator, mp4-muxer) map to node_modules through an import map.
//
//   import { startHarness } from './tools/social-harness/server.mjs';
//   const h = await startHarness({ src: 'path/to/a/copy' });   // src: another checkout's resources/ + public/media
//   … open `${h.base}/` in Chrome …
//   await h.close();
//
// Routes: /  (the page) · /harness/* (this folder) · /resources/* and /media/* (from `src`, else the repo)
// · /node_modules/<allowed package>/* · /files/* (read-only, from `files`, for contact sheets).

import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';

const HERE = path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
export const REPO = path.resolve(HERE, '../..');
const PACKAGES = ['qrcode-generator', 'mp4-muxer', 'html2canvas'];
const TYPES = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.webp': 'image/webp',
};

/** A file under `root` for a URL path, or null (never outside `root`). */
function inside(root, rel) {
  const file = path.resolve(root, `.${path.sep}${decodeURIComponent(rel)}`);
  return file.startsWith(path.resolve(root) + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile() ? file : null;
}

export async function startHarness({ src, files = null, port = 0 } = {}) {
  const srcRoot = path.resolve(src ?? REPO);
  const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://x');
    const route = url.pathname;
    const send = (status, file, type) => {
      res.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store', 'Access-Control-Allow-Origin': '*' });
      res.end(file);
    };
    let file = null;
    if (route === '/' || route === '/index.html') file = path.join(HERE, 'index.html');
    else if (route.startsWith('/harness/')) file = inside(HERE, route.slice('/harness/'.length));
    else if (route.startsWith('/resources/')) file = inside(path.join(srcRoot, 'resources'), route.slice('/resources/'.length)) ?? inside(path.join(REPO, 'resources'), route.slice('/resources/'.length));
    else if (route.startsWith('/media/')) file = inside(path.join(srcRoot, 'public/media'), route.slice('/media/'.length)) ?? inside(path.join(REPO, 'public/media'), route.slice('/media/'.length));
    else if (route.startsWith('/node_modules/')) {
      const rel = route.slice('/node_modules/'.length);
      if (PACKAGES.some((p) => rel.startsWith(`${p}/`))) file = inside(path.join(REPO, 'node_modules'), rel);
    } else if (route.startsWith('/files/') && files) file = inside(files, route.slice('/files/'.length));
    if (!file) return send(404, 'not found', 'text/plain');
    return send(200, fs.readFileSync(file), TYPES[path.extname(file).toLowerCase()] ?? 'application/octet-stream');
  });
  await new Promise((resolve) => server.listen(port, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  return { base, close: () => new Promise((resolve) => { server.closeAllConnections?.(); server.close(resolve); }) };
}

// `node tools/social-harness/server.mjs [src]`: serve it for a look in a browser.
if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(HERE, 'server.mjs')) {
  const h = await startHarness({ src: process.argv[2] ?? REPO, port: Number(process.env.PORT ?? 0) });
  console.log(`Social harness: ${h.base}/  (Ctrl+C to stop)`);
}
