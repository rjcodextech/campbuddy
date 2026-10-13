// node --test tests/  — guards the settings that keep the shell safe and in
// step with the website. They fail loudly in CI before a bad build ships.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const root = new URL('../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
const config = JSON.parse(read('capacitor.config.json'));
const pkg = JSON.parse(read('package.json'));

test('the shell opens the live site over https only', () => {
  assert.equal(new URL(config.server.url).protocol, 'https:');
  assert.equal(config.server.cleartext, false);
  assert.deepEqual(config.server.allowNavigation, [new URL(config.server.url).hostname]);
  assert.equal(config.android.allowMixedContent, false);
  assert.equal(config.android.webContentsDebuggingEnabled, false, 'no WebView debugging in a store build');
  assert.equal(config.ios.limitsNavigationsToAppBoundDomains, true);
});

test('the user agent the website looks for carries this build version', () => {
  assert.equal(config.appendUserAgent, `CampBuddyApp/${pkg.version}`);
});

test('an offline page is bundled and named as the error page', () => {
  assert.equal(config.server.errorPath, 'offline.html');
  assert.match(read('www/offline.html'), /Try again/);
});

test('the app id is the same everywhere', () => {
  assert.equal(config.appId, 'club.campbuddy.app');
  assert.match(read('android/app/build.gradle'), /applicationId "club\.campbuddy\.app"/);
  assert.match(read('ios/App/App.xcodeproj/project.pbxproj'), /PRODUCT_BUNDLE_IDENTIFIER = club\.campbuddy\.app;/);
});

test('Android: verified App Links, no cleartext, no backup', () => {
  const manifest = read('android/app/src/main/AndroidManifest.xml');
  assert.match(manifest, /android:autoVerify="true"/);
  assert.match(manifest, /android:host="campbuddy\.club"/);
  assert.match(manifest, /android:usesCleartextTraffic="false"/);
  assert.match(manifest, /android:allowBackup="false"/);
  assert.match(manifest, /POST_NOTIFICATIONS/);
  assert.match(manifest, /SCHEDULE_EXACT_ALARM" tools:node="remove"/, 'exact alarms need a Play justification we do not have: removed from the merged manifest');
  assert.doesNotMatch(manifest, /USE_EXACT_ALARM/);
});

test('iOS: app-bound domain, Universal Links, privacy manifest, no tracking', () => {
  const plist = read('ios/App/App/Info.plist');
  assert.match(plist, /WKAppBoundDomains[\s\S]*campbuddy\.club/);
  assert.match(plist, /remote-notification/);
  assert.match(plist, /NSFaceIDUsageDescription/);
  assert.match(read('ios/App/App/App.entitlements'), /applinks:campbuddy\.club/);
  assert.match(read('ios/App/App/PrivacyInfo.xcprivacy'), /<key>NSPrivacyTracking<\/key>\s*<false\/>/);
  assert.match(read('ios/App/App.xcodeproj/project.pbxproj'), /PrivacyInfo\.xcprivacy in Resources/);
});

test('every plugin is pinned to an exact version', () => {
  for (const [name, version] of Object.entries({ ...pkg.dependencies, ...pkg.devDependencies })) {
    assert.match(version, /^\d+\.\d+\.\d+$/, `${name} must be pinned, got ${version}`);
  }
});
