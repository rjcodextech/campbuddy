// node --test tests/js  — the app-only layer (native-bridge.js) and the proof
// that the website (no CampBuddyApp in the user agent) is left alone.
import assert from 'node:assert/strict';
import { test } from 'node:test';

function setUserAgent(userAgent, extra = {}) {
  Object.defineProperty(globalThis, 'navigator', { value: { userAgent, platform: 'Linux', maxTouchPoints: 5, ...extra }, configurable: true });
}

globalThis.window = {};
globalThis.location = { href: 'https://campbuddy.club/', origin: 'https://campbuddy.club', pathname: '/', search: '', hash: '' };
globalThis.document = { getElementById: () => null, querySelectorAll: () => [], addEventListener() {} };

setUserAgent('Mozilla/5.0 (Linux; Android 14) Chrome/120');
const platform = await import('../../resources/js/attendee/platform.js');
const bridge = await import('../../resources/js/attendee/native-bridge.js');

test('the website and the installed PWA are not the app', () => {
  setUserAgent('Mozilla/5.0 (Linux; Android 14) Chrome/120');
  assert.equal(platform.isNativeApp(), false);
  assert.equal(platform.nativePlatform(), null);
  assert.equal(platform.appVersion(), null);

  setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/605.1');
  assert.equal(platform.isNativeApp(), false);
});

test('the Android and iOS shells are recognised, with their version', () => {
  setUserAgent('Mozilla/5.0 (Linux; Android 14; wv) Chrome/120 CampBuddyApp/1.2.3');
  assert.equal(platform.isNativeApp(), true);
  assert.equal(platform.nativePlatform(), 'android');
  assert.equal(platform.appVersion(), '1.2.3');

  setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit CampBuddyApp/1.0.0');
  assert.equal(platform.nativePlatform(), 'ios');
});

test('a reminder id is stable, positive and different per session', () => {
  const a = bridge.reminderId(3, 101);
  assert.equal(a, bridge.reminderId(3, 101));
  assert.notEqual(a, bridge.reminderId(3, 102));
  assert.notEqual(a, bridge.reminderId(4, 101));
  assert.ok(a > 0 && a <= 0x7fffffff);
});

test('a reminder comes 10 minutes before the start, and never for the past', () => {
  const now = Date.parse('2026-11-01T09:00:00Z');
  const row = { eventId: 3, sessionId: 7, reminderEnabled: true, title: 'Blocks 101', startMs: now + 30 * 60000 };

  const r = bridge.reminderFor(row, 'wordcamp-x', now);
  assert.equal(r.at, row.startMs - 10 * 60000);
  assert.match(r.title, /Blocks 101/);
  assert.equal(r.extra.url, '/event/wordcamp-x/my-day');

  assert.equal(bridge.reminderFor({ ...row, startMs: now + 5 * 60000 }, 'x', now), null, 'starts in 5 min: too late');
  assert.equal(bridge.reminderFor({ ...row, startMs: now - 1 }, 'x', now), null);
  assert.equal(bridge.reminderFor({ ...row, reminderEnabled: false }, 'x', now), null);
  assert.equal(bridge.reminderFor({ ...row, startMs: undefined }, 'x', now), null);
  assert.equal(bridge.reminderFor(null, 'x', now), null);
});

test('only the soonest reminders are scheduled (iOS keeps 64)', () => {
  const list = Array.from({ length: 100 }, (_, i) => ({ id: i, at: 1000 - i }));
  const picked = bridge.pickSoonest(list);
  assert.equal(picked.length, 60);
  assert.equal(picked[0].at, 901);
  assert.deepEqual(bridge.pickSoonest([null, { id: 1, at: 5 }]).map((r) => r.id), [1]);
});

test('versions compare as numbers, not text', () => {
  assert.ok(bridge.compareVersions('1.10.0', '1.9.0') > 0);
  assert.ok(bridge.compareVersions('1.0.0', '1.0.1') < 0);
  assert.equal(bridge.compareVersions('2.0.0', '2.0.0'), 0);
});

test('a link or notification can only open pages of this site', () => {
  const origin = 'https://campbuddy.club';
  assert.equal(bridge.safeInternalPath('https://campbuddy.club/event/x?a=1#b', origin), '/event/x?a=1#b');
  assert.equal(bridge.safeInternalPath('/event/x/my-day', origin), '/event/x/my-day');
  assert.equal(bridge.safeInternalPath('https://evil.example/event/x', origin), null);
  assert.equal(bridge.safeInternalPath('javascript:alert(1)', origin), null);
  assert.equal(bridge.safeInternalPath('//evil.example/x', origin), null);
});

test('the event being viewed is read from the page path', () => {
  assert.equal(bridge.eventSlugFromPath('/event/wordcamp-delhi-2026/my-day'), 'wordcamp-delhi-2026');
  assert.equal(bridge.eventSlugFromPath('/'), null);
  assert.equal(bridge.eventSlugFromPath('/guide'), null);
});
