// node --test tests/js  — GA reports from the 26 Sep–5 Oct data: where a
// rejected promise came from (file:line:column, never the message), and the
// discovery Join's blocked reasons as an allowed event.
import assert from 'node:assert/strict';
import { test } from 'node:test';

const sent = [];
globalThis.window = { gtag: (_kind, name, params) => sent.push([name, params]) };
globalThis.location = { href: 'https://campbuddy.club/event/x', origin: 'https://campbuddy.club', search: '' };

const { track, rejectionPlace } = await import('../../resources/js/attendee/analytics.js');

test('a Chrome stack gives the first frame in our own scripts', () => {
  const stack = `InvalidStateError: Failed to execute 'subscribe' on 'PushManager': secret detail
    at https://cdn.example.com/lib.js:1:99
    at registerSubscription (https://campbuddy.club/build/assets/push-AbC123.js?v=2:1:4521)
    at async https://campbuddy.club/build/assets/app-Xy9.js:1:200`;
  assert.equal(rejectionPlace(stack), 'push-AbC123.js:1:4521');
});

test('a Safari / Firefox stack works too', () => {
  assert.equal(rejectionPlace('registerSubscription@https://campbuddy.club/build/assets/push-AbC123.js:1:4521\n'), 'push-AbC123.js:1:4521');
});

test('no stack, or only other sites, gives no place', () => {
  assert.equal(rejectionPlace(undefined), null);
  assert.equal(rejectionPlace('at https://evil.example/x.js:3:4'), null);
  assert.equal(rejectionPlace('just a message'), null);
});

test('discovery_join_blocked sends only the surface and the reason', () => {
  sent.length = 0;
  track('discovery_join_blocked', { surface: 'home', result: 'no_tags', display_name: 'Asha' });
  assert.deepEqual(sent, [['discovery_join_blocked', { surface: 'home', result: 'no_tags' }]]);
});
