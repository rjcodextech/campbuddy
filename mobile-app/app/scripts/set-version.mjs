// node scripts/set-version.mjs 1.2.0 7
//
// One command to move every place that carries the app version, so the app,
// the user agent the website reads and the store builds never disagree
// (tests/sync.test.mjs checks the result).
//   1.2.0  the version people see (semver)
//   7      the build number: Android versionCode and iOS CURRENT_PROJECT_VERSION

import { readFileSync, writeFileSync } from 'node:fs';

const [version, build] = process.argv.slice(2);
if (!/^\d+\.\d+\.\d+$/.test(version ?? '') || !/^\d+$/.test(build ?? '')) {
  console.error('usage: node scripts/set-version.mjs <major.minor.patch> <build number>');
  process.exit(1);
}

const root = new URL('../', import.meta.url);
const edit = (path, change) => {
  const url = new URL(path, root);
  writeFileSync(url, change(readFileSync(url, 'utf8')));
};

edit('package.json', (s) => s.replace(/"version": "[^"]+"/, `"version": "${version}"`));
edit('capacitor.config.json', (s) => s.replace(/"appendUserAgent": "CampBuddyApp\/[^"]+"/, `"appendUserAgent": "CampBuddyApp/${version}"`));
edit('android/app/build.gradle', (s) => s.replace(/versionCode \d+/, `versionCode ${build}`).replace(/versionName "[^"]+"/, `versionName "${version}"`));
edit('ios/App/App.xcodeproj/project.pbxproj', (s) => s.replace(/MARKETING_VERSION = [^;]+;/g, `MARKETING_VERSION = ${version};`).replace(/CURRENT_PROJECT_VERSION = [^;]+;/g, `CURRENT_PROJECT_VERSION = ${build};`));

console.log(`App version set to ${version} (build ${build}). Run npm test, then commit.`);
