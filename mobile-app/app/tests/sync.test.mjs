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
