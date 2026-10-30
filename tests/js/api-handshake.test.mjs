// node --test tests/js  — the API handshake from the PWA's side (VerifyApiHandshake on the server):
// who/when on every call, one retry with the server's time when the phone's clock is wrong, and an
// answer that is not the API's JSON (a venue wifi sign-in page) treated as "no connection".
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

globalThis.localStorage ??= { getItem: () => null, setItem: () => {}, removeItem: () => {} };
globalThis.crypto ??= await import('node:crypto').then((m) => m.webcrypto);

let replies = [];
const calls = [];
globalThis.fetch = async (url, options = {}) => {
  calls.push({ url, options });
  const next = replies.shift();
  return typeof next === 'function' ? next(options) : next;
};

const json = (body, status = 200, headers = {}) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });
const now = () => Math.floor(Date.now() / 1000);

const { apiGet, apiMutate, apiHeaders } = await import('../../resources/js/attendee/api.js');
const { createOutbox, isPermanentFailure } = await import('../../resources/js/attendee/outbox.js');

beforeEach(() => {
  replies = [];
  calls.length = 0;
});

test('every call says it is the PWA, which phone, and when', () => {
  const headers = apiHeaders();

  assert.equal(headers['X-CampBuddy-Client'], 'pwa');
  assert.match(headers['X-CampBuddy-Device'], /^[0-9a-f-]{36}$/);
  assert.ok(Math.abs(Number(headers['X-CampBuddy-Timestamp']) - now()) <= 2);
});

test('a phone whose clock is off retries once with the server time, and later calls carry it', async () => {
  const server = now() + 3600; // the phone is an hour behind
  replies = [
    json({ error: { code: 'HANDSHAKE_TIME_SKEW', server_time: server } }, 401, { 'X-CampBuddy-Handshake-Error': 'time-skew', 'X-CampBuddy-Server-Time': String(server) }),
    json({ ok: true }, 201),
    json({ ok: true }, 201),
  ];

  assert.deepEqual(await apiMutate('wc-test', '/bookmarks', 'POST', { session_id: 1 }), { ok: true });
  assert.equal(calls.length, 2, 'exactly one retry');
  const retried = Number(calls[1].options.headers['X-CampBuddy-Timestamp']);
  assert.ok(Math.abs(retried - server) <= 2, `retry carries the server's time (${retried} vs ${server})`);

  await apiMutate('wc-test', '/bookmarks', 'POST', { session_id: 2 });
  assert.ok(Math.abs(Number(calls[2].options.headers['X-CampBuddy-Timestamp']) - server) <= 2, 'the correction is remembered');
});

test('a clock refusal is never treated as a final "no": the outbox keeps the wish for later', async () => {
  const skew = () => json({ error: { code: 'HANDSHAKE_TIME_SKEW' } }, 401, { 'X-CampBuddy-Handshake-Error': 'time-skew', 'X-CampBuddy-Server-Time': String(now()) });
  replies = [skew, skew];

  const error = await apiMutate('wc-test', '/bookmarks', 'POST', { session_id: 3 }).catch((e) => e);
  assert.equal(error.status, 401);
  assert.equal(error.transient, true);
  assert.equal(isPermanentFailure(error), false);

  const store = new Map();
  const outbox = createOutbox({ kvGet: async (k) => store.get(k), kvSet: async (k, v) => store.set(k, v) });
  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 3 });
  const result = await outbox.flush('wc-test', async () => { throw error; });
  assert.deepEqual(result, { sent: 0, dropped: 0, remaining: 1 });
});

test('a real refusal (403) is still final', () => {
  assert.equal(isPermanentFailure({ status: 403 }), true);
  assert.equal(isPermanentFailure({ status: 401 }), true);
});

test('a 200 that is not the API (a wifi sign-in page) is "no connection", not a crash on bad JSON', async () => {
  replies = [new Response('<html><body>Login to venue wifi</body></html>', { status: 200, headers: { 'Content-Type': 'text/html' } })];

  const error = await apiGet('wc-test', '/roster').catch((e) => e);
  assert.ok(error instanceof Error);
  assert.equal(error.status, 0);
  assert.equal(isPermanentFailure(error), false);
});

test('a JSON answer with a charset still reads', async () => {
  replies = [new Response('{"data":[1]}', { status: 200, headers: { 'Content-Type': 'application/json; charset=utf-8' } })];

  assert.deepEqual(await apiGet('wc-test', '/roster'), { data: [1] });
});
