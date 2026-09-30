// node --test tests/js  — "Move my CampBuddy to this device": the sealed envelope and what moves.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { bundleForThisDevice, bundleFrom, codeFor, fromB64url, newKeyPair, open, seal, toB64url } from '../../resources/js/attendee/transfer-crypto.js';

test('base64url round-trips any bytes, with no padding or +/', () => {
  const bytes = new Uint8Array(300).map((_, i) => (i * 37) % 256);
  const text = toB64url(bytes);

  assert.match(text, /^[A-Za-z0-9_-]+$/);
  assert.deepEqual(fromB64url(text), bytes);
});

test('only the new device can open what the old one sealed for it', async () => {
  const phone = await newKeyPair();
  const data = { v: 1, kv: { campCard: { name: 'Sunil Kumar' } }, bookmarks: [{ key: '1:9' }] };

  const sealed = await seal(data, phone.publicKey, 'transfer-1');

  assert.match(sealed.payload, /^[A-Za-z0-9_-]+$/);
  assert.ok(!sealed.payload.includes('Sunil'), 'the relayed payload is ciphertext');
  assert.deepEqual(await open(sealed, phone.privateKey, 'transfer-1'), data);

  const stranger = await newKeyPair();
  await assert.rejects(open(sealed, stranger.privateKey, 'transfer-1'), 'another key cannot open it');
  await assert.rejects(open(sealed, phone.privateKey, 'transfer-2'), 'bound to its own request');

  const tampered = { ...sealed, payload: sealed.payload.slice(0, -2) + (sealed.payload.endsWith('A') ? 'BA' : 'AA') };
  await assert.rejects(open(tampered, phone.privateKey, 'transfer-1'), 'an altered payload is refused');
});

test('the code is 6 digits, the same on both devices, and differs between keys', async () => {
  const a = await newKeyPair();
  const b = await newKeyPair();
  const code = await codeFor(a.publicKey);

  assert.match(code, /^\d{6}$/);
  assert.equal(await codeFor(a.publicKey), code);
  assert.notEqual(await codeFor(b.publicKey), code);
});

test('what moves: the person\'s things, not the device\'s own', () => {
  const dump = {
    kv: {
      onboarding: { firstWordCamp: true },
      campCard: { name: 'Sunil' },
      'discovery:1': { discoveryId: 'd1', ownerToken: 'old' },
      'startHereDismissed:1': true,
      notificationState: { enabled: true },
      'pushEndpoint:wc': { endpoint: 'https://push' },
      'outbox:wc': [],
      'roster:wc': { entries: [] },
      'transferRequest:1': { transferId: 't' },
      'transferSent:1': { transferId: 't' },
    },
    bookmarks: [{ key: '1:5', reminderEnabled: true }],
    questProgress: [{ key: '1:2' }],
    metHistory: [],
    meetings: [{ key: '1:p', note: 'coffee' }],
  };

  const bundle = bundleFrom(dump, { 'campbuddy:wave-name': 'Sunil', other: 'x' });

  assert.deepEqual(Object.keys(bundle.kv).sort(), ['campCard', 'discovery:1', 'onboarding', 'startHereDismissed:1']);
  assert.equal(bundle.bookmarks.length, 1);
  assert.equal(bundle.meetings[0].note, 'coffee');
  assert.deepEqual(bundle.local, { 'campbuddy:wave-name': 'Sunil' });
});

test('on the new device the profile gets its new token and reminders start off', () => {
  const bundle = { kv: { 'discovery:1': { discoveryId: 'd1', ownerToken: 'old' }, 'discovery:2': { discoveryId: 'd2', ownerToken: 'keep' } }, bookmarks: [{ key: '1:5', reminderEnabled: true }] };

  const here = bundleForThisDevice(bundle, { eventId: 1, discoveryId: 'd1', ownerToken: 'new' });

  assert.equal(here.kv['discovery:1'].ownerToken, 'new');
  assert.equal(here.kv['discovery:2'].ownerToken, 'keep');
  assert.equal(here.bookmarks[0].reminderEnabled, false);
  assert.equal(bundle.kv['discovery:1'].ownerToken, 'old', 'the original is not changed');
});
