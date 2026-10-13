// node --test tests/js  — the organizer side of the app (organizer-native.js).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.window = {};
Object.defineProperty(globalThis, 'navigator', { value: { userAgent: 'x', platform: 'Linux', maxTouchPoints: 5 }, configurable: true });

const { roleFromPath, shouldLock, LOCK_AFTER_MS } = await import('../../resources/js/organizer-native.js');

test('only the panel counts, and which panel', () => {
  assert.equal(roleFromPath('/admin'), 'admin');
  assert.equal(roleFromPath('/admin/events/3/reports'), 'admin');
  assert.equal(roleFromPath('/manager'), 'manager');
  assert.equal(roleFromPath('/manager/login'), 'manager');
  assert.equal(roleFromPath('/'), null);
  assert.equal(roleFromPath('/event/wordcamp-admin-days'), null);
  assert.equal(roleFromPath('/administrator'), null);
});

test('locked on every cold start, and after the app was away a while', () => {
  const now = 1_000_000;
  assert.equal(shouldLock({ unlockedThisLaunch: false, hiddenAt: null, now }), true);
  assert.equal(shouldLock({ unlockedThisLaunch: true, hiddenAt: null, now }), false);
  assert.equal(shouldLock({ unlockedThisLaunch: true, hiddenAt: now - 5000, now }), false, 'a quick switch away is fine');
  assert.equal(shouldLock({ unlockedThisLaunch: true, hiddenAt: now - LOCK_AFTER_MS, now }), true);
});

test('the admin bundle loads it only in the app and only on panel pages', () => {
  const entry = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
  assert.match(entry, /\(admin\|manager\)/);
  assert.match(entry, /CampBuddyApp/);
  assert.match(entry, /import\('\.\/organizer-native'\)/);
});

test('the picker has the staff links, hidden until the app shows them', () => {
  const blade = readFileSync(new URL('../../resources/views/welcome.blade.php', import.meta.url), 'utf8');
  assert.match(blade, /data-app-only hidden/);
  assert.match(blade, /manager\.login/);
});
