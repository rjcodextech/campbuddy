#!/usr/bin/env node
// Run after `vite build` (see the "build" script in package.json).
//
// public/hot is written by `npm run dev` and tells Laravel "load the styles and
// scripts from the dev server on this computer". A build is finished, real
// files: if the hot file is left behind and gets uploaded with them, every
// visitor's browser is sent to a dev server that only exists on the developer's
// machine and the whole site loses its styling and scripts.
//
// So a build removes it. `npm run dev` creates it again when it starts (and
// removes it when it stops cleanly).
//
//   node tools/remove-hot.mjs            removes public/hot
//   node tools/remove-hot.mjs <path>     removes that file instead (used by the test)

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const hot = process.argv[2] ? path.resolve(process.argv[2]) : path.join(root, 'public', 'hot');

if (fs.existsSync(hot)) {
  fs.rmSync(hot, { force: true });
  console.log('Removed public/hot: the site now uses the built files. Run `npm run dev` again for hot reload (it creates the file again).');
}
