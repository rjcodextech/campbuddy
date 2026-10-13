// node --test "tests/*.test.mjs"  — the app, the website and the PWA must agree.
// These fail in CI the moment one side changes without the other.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const app = new URL('../', import.meta.url);
const repo = new URL('../../../', import.meta.url);
const read = (base, path) => readFileSync(new URL(path, base), 'utf8');

const pkg = JSON.parse(read(app, 'package.json'));
const config = JSON.parse(read(app, 'capacitor.config.json'));

test('one version everywhere (set it with scripts/set-version.mjs)', () => {
  assert.equal(config.appendUserAgent, `CampBuddyApp/${pkg.version}`);
  assert.match(read(app, 'android/app/build.gradle'), new RegExp(`versionName "${pkg.version.replace(/\./g, '\\.')}"`));
  assert.match(read(app, 'ios/App/App.xcodeproj/project.pbxproj'), new RegExp(`MARKETING_VERSION = ${pkg.version.replace(/\./g, '\\.')};`));
});

test('the user agent the website recognises is the one the shell sends', () => {
  const platform = read(repo, 'resources/js/attendee/platform.js');
  assert.match(platform, /CampBuddyApp\\\//);
  assert.match(config.appendUserAgent, /^CampBuddyApp\/\d+\.\d+\.\d+$/);
  // The admin bundle checks the same marker before loading organizer-native.js.
  assert.match(read(repo, 'resources/js/app.js'), /CampBuddyApp\\\//);
});

test('the app id matches the server default, the stores and the signing entitlements', () => {
  const server = read(repo, 'config/mobile_app.php');
  assert.match(server, new RegExp(`'app_id' => env\\('MOBILE_APP_ID', '${config.appId.replace(/\./g, '\\.')}'\\)`));
  assert.match(read(app, 'android/app/build.gradle'), new RegExp(`applicationId "${config.appId.replace(/\./g, '\\.')}"`));
});

test('the site the shell opens is the one App Links and Universal Links verify', () => {
  const host = new URL(config.server.url).hostname;
  assert.match(read(app, 'android/app/src/main/AndroidManifest.xml'), new RegExp(`android:host="${host.replace(/\./g, '\\.')}"`));
  assert.match(read(app, 'ios/App/App/App.entitlements'), new RegExp(`applinks:${host.replace(/\./g, '\\.')}`));
  assert.deepEqual(config.server.allowNavigation, [host]);
});

test('Android link paths and the iOS association file cover the same pages', () => {
  const manifest = read(app, 'android/app/src/main/AndroidManifest.xml');
  const prefixes = [...manifest.matchAll(/android:pathPrefix="([^"]+)"/g)].map((m) => m[1]);
  const exact = [...manifest.matchAll(/android:path="([^"]+)"/g)].map((m) => m[1]);
  const wellKnown = read(repo, 'app/Http/Controllers/WellKnownController.php');

  assert.deepEqual(prefixes, ['/event']);
  assert.deepEqual(exact, ['/']);
  assert.match(wellKnown, /'\/' => '\/event\/\*'/);
  assert.match(wellKnown, /'\/' => '\/'\]/);
});

test('the notification colour and icon names agree between the shell and the server', () => {
  const push = read(repo, 'app/Support/NativePush.php');
  assert.match(push, new RegExp(`'color' => '${config.plugins.LocalNotifications.iconColor}'`));
  assert.match(push, new RegExp(`'icon' => '${config.plugins.LocalNotifications.smallIcon}'`));
  assert.ok(readFileSync(new URL(`android/app/src/main/res/drawable/${config.plugins.LocalNotifications.smallIcon}.xml`, app)));
});

test('every icon the stores need exists', () => {
  for (const file of [
    'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png',
    'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png',
    'android/app/src/main/res/drawable-port-xxxhdpi/splash.png',
    'ios/App/App/Assets.xcassets/AppIcon.appiconset/AppIcon-512@2x.png',
    'ios/App/App/Assets.xcassets/Splash.imageset/splash-2732x2732.png',
    'store/play-icon-512.png',
    'www/wordmark.png',
    'www/fonts/inter-400.woff2',
  ]) {
    assert.ok(readFileSync(new URL(file, app)).length > 500, file);
  }
});

// ---- Android and iOS must offer the same things ---------------------------------------------

const pluginsIn = (text, pattern) => [...text.matchAll(pattern)].map((m) => m[1].toLowerCase().replace(/[^a-z]/g, '')).sort();

test('Android and iOS carry the same native plugins as package.json', () => {
  const declared = Object.keys(pkg.dependencies)
    .filter((n) => !/@capacitor\/(android|ios|core)$/.test(n))
    .map((n) => n.split('/').pop().toLowerCase().replace(/[^a-z]/g, ''))
    .sort();
  const android = pluginsIn(read(app, 'android/capacitor.settings.gradle'), /^include ':([a-z-]+)'$/gm).filter((n) => n !== 'capacitorandroid');
  const ios = pluginsIn(read(app, 'ios/App/CapApp-SPM/Package.swift'), /\.package\(name: "([A-Za-z]+)", path/g);

  const bare = (list) => list.map((n) => n.replace(/^capgo/, '').replace(/^capacitor/, '')).sort();
  assert.deepEqual(bare(android), bare(declared));
  assert.deepEqual(bare(ios), bare(declared));
});

test('both stores get the same permissions and capabilities', () => {
  const manifest = read(app, 'android/app/src/main/AndroidManifest.xml');
  const plist = read(app, 'ios/App/App/Info.plist');
  const entitlements = read(app, 'ios/App/App/App.entitlements');

  // Notifications and background push
  assert.match(manifest, /POST_NOTIFICATIONS/);
  assert.match(plist, /remote-notification/);
  assert.match(entitlements, /aps-environment/);
  // Face ID / fingerprint for the organizer lock
  assert.match(plist, /NSFaceIDUsageDescription/);
  // Links that open in the app
  assert.match(manifest, /autoVerify="true"/);
  assert.match(entitlements, /associated-domains/);
  // Only the live site, over https, in both
  assert.match(plist, /WKAppBoundDomains/);
  assert.match(manifest, /usesCleartextTraffic="false"/);
  // Nothing tracks across apps
  assert.match(read(app, 'ios/App/App/PrivacyInfo.xcprivacy'), /NSPrivacyTracking<\/key>\s*<false\/>/);
});

test('both projects use the same brand colours, and the offline page exists once for both', () => {
  assert.match(read(app, 'android/app/src/main/res/values/ic_launcher_background.xml'), /#60121C/i);
  assert.equal(config.plugins.LocalNotifications.iconColor, '#60121c');
  assert.match(read(app, 'www/offline.html'), /--maroon: #60121c/);
});

test('both Firebase hooks are in place (the files themselves are never committed)', () => {
  assert.match(read(app, 'android/app/build.gradle'), /google-services/);
  assert.match(read(app, 'ios/App/App/AppDelegate.swift'), /FirebaseMessaging/);
  const ignore = read(app, '.gitignore');
  assert.match(ignore, /google-services\.json/);
  assert.match(ignore, /GoogleService-Info\.plist/);
});
