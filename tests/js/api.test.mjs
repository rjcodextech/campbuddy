// node --test tests/js  — the API wrapper: reads always check with the server.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.localStorage ??= { getItem: () => null, setItem: () => {}, removeItem: () => {} };
globalThis.crypto ??= await import('node:crypto').then((m) => m.webcrypto);

const calls = [];
globalThis.fetch = async (url, options = {}) => {
  calls.push({ url, options });
  return new Response(JSON.stringify({ data: [] }), { status: 200, headers: { 'Content-Type': 'application/json' } });
};

const { apiGet } = await import('../../resources/js/attendee/api.js');

test('a read revalidates with the server instead of using a stale browser copy', async () => {
  await apiGet('wc-test', '/discovery');

  assert.equal(calls.at(-1).url, '/api/v1/events/wc-test/discovery');
  assert.equal(calls.at(-1).options.cache, 'no-cache');
});

test('the owner token still goes along', async () => {
  await apiGet('wc-test', '/discovery/abc/waves', 'secret-token');

  assert.equal(calls.at(-1).options.headers.Authorization, 'Bearer secret-token');
  assert.equal(calls.at(-1).options.cache, 'no-cache');
});
