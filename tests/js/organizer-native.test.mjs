// node --test tests/js  — the organizer side of the app (organizer-native.js).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.window = {};
Object.defineProperty(globalThis, 'navigator', { value: { userAgent: 'x', platform: 'Linux', maxTouchPoints: 5 }, configurable: true });

const { roleFromPath, shouldLock, tabsFor, LOCK_AFTER_MS } = await import('../../resources/js/organizer-native.js');

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

test('admin pages get the five main tabs, with the open one marked', () => {
  const tabs = tabsFor('/admin/analytics');
  assert.deepEqual(tabs.map((t) => t.key), ['dashboard', 'events', 'analytics', 'errors', 'feedback']);
  assert.equal(tabs.filter((t) => t.active).map((t) => t.key).join(), 'analytics');
  assert.equal(tabsFor('/admin').find((t) => t.active).key, 'dashboard');
  assert.equal(tabsFor('/admin/events/create').find((t) => t.active).key, 'events');
});

test('inside an event the tabs become the sections of that event', () => {
  const admin = tabsFor('/admin/events/7/reports');
  assert.deepEqual(admin.map((t) => t.key), ['dashboard', 'events', 'details', 'reports', 'notifications']);
  assert.equal(admin.find((t) => t.active).key, 'reports');
  assert.equal(admin.find((t) => t.key === 'details').href, '/admin/events/7/edit');

  const manager = tabsFor('/manager/events/7/notifications');
  assert.deepEqual(manager.map((t) => t.key), ['events', 'details', 'attendees', 'reports', 'notifications']);
  assert.equal(manager.find((t) => t.active).key, 'notifications');
  assert.equal(tabsFor('/manager/events/7').find((t) => t.active).key, 'details');
});

test('no bar where there is only one place, or outside the panel', () => {
  assert.deepEqual(tabsFor('/manager'), []);
  assert.deepEqual(tabsFor('/event/wordcamp-test'), []);
  assert.deepEqual(tabsFor('/'), []);
});
