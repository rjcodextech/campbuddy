// npm run check:sync
//
// One command that proves the admin panel, the website, its front end (JS/CSS),
// the PWA, the Android project and the iOS project still agree. Run it before
// every deploy and every store build. CI runs the same checks.

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';

const env = { ...process.env };
const wamp = 'C:/wamp64/bin/php/php8.3.28/extras/ssl/openssl.cnf';
if (!env.OPENSSL_CONF && existsSync(wamp)) env.OPENSSL_CONF = wamp; // local Windows PHP needs it for key tests

const checks = [
  { layer: 'Brand', name: 'Colours, fonts and corners match CAMPBUDDY-BRAND-GUIDELINES.md (_variables.scss mirrors)', cmd: 'node', args: ['tools/brand-check.mjs'] },
  { layer: 'Front end + PWA', name: 'JS unit tests (service worker, bridge, panel tabs, downloads, report)', cmd: 'npm', args: ['run', 'test:js', '--silent'] },
  { layer: 'Admin panel + Web app', name: 'PHP: routes, service-worker private paths, app config, panel tabs, push, reports', cmd: 'php', args: ['artisan', 'test', '--filter=AppSyncTest|NativeKotlinSyncTest|MobileAppConfigTest|AppEventApiTest|OrganizerApiTest|NativeAppBackendTest|NativePushTest|DiscoveryReportTest|OrganizerAppTest|EveryPageAndAccessTest'] },
];

let failed = 0;
const rows = [];

for (const check of checks) {
  process.stdout.write(`... ${check.layer}: ${check.name}\n`);
  const quote = (a) => (process.platform === 'win32' && /[|&<>]/.test(a) ? `"${a}"` : a);
  let run = spawnSync(check.cmd, check.args.map(quote), { cwd: check.cwd, env, shell: process.platform === 'win32', encoding: 'utf8' });
  if (run.status === 0 && check.then) run = spawnSync(check.then.cmd, check.then.args, { env, shell: process.platform === 'win32', encoding: 'utf8' });

  const ok = run.status === 0;
  if (!ok) {
    failed++;
    console.log((run.stdout ?? '').split('\n').slice(-40).join('\n'));
    console.log((run.stderr ?? '').split('\n').slice(-10).join('\n'));
  }
  rows.push([ok ? 'OK  ' : 'FAIL', check.layer, check.name]);
}

console.log('\nSync report');
for (const [status, layer, name] of rows) console.log(`  ${status}  ${layer.padEnd(24)} ${name}`);
console.log(failed ? `\n${failed} check(s) failed: one side moved without the others.\n` : '\nEverything is in sync.\n');
process.exit(failed ? 1 : 0);
