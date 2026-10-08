// node --test tests/js  — the iPhone "reminders need the Home Screen" card in
// My Day → My schedule (ios-reminder-hint.js): who sees it.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFileSync } from 'node:fs';

globalThis.window = {};
globalThis.location = { href: 'https://campbuddy.club/event/x/my-day', origin: 'https://campbuddy.club', search: '' };
globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };

const { shouldShowIosHint } = await import('../../resources/js/attendee/ios-reminder-hint.js');

const base = { ios: true, standalone: false, savedCount: 2, dismissed: false };

test('shown on an iPhone browser tab with saved sessions', () => {
  assert.equal(shouldShowIosHint(base), true);
});

test('never on Android/desktop, in the installed app, with nothing saved, or once closed', () => {
  assert.equal(shouldShowIosHint({ ...base, ios: false }), false);
  assert.equal(shouldShowIosHint({ ...base, standalone: true }), false, 'installed: push works there');
  assert.equal(shouldShowIosHint({ ...base, savedCount: 0 }), false);
  assert.equal(shouldShowIosHint({ ...base, dismissed: true }), false);
});

test('the card template has the two buttons the module wires up', () => {
  const blade = readFileSync(new URL('../../resources/views/attendee/templates/my-day.blade.php', import.meta.url), 'utf8');
  const tpl = blade.match(/<template id="tpl-ios-reminder-hint">([\s\S]*?)<\/template>/)?.[1] ?? '';
  assert.match(tpl, /data-action="how"/);
  assert.match(tpl, /data-action="dismiss"/);
});
