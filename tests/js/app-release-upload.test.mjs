// node --test tests/js  — the APK upload in Admin → Mobile app → App updates (app-release-upload.js).
import assert from 'node:assert/strict';
import { createHash, webcrypto } from 'node:crypto';
import { test } from 'node:test';

const { pieceCount, sha256Fallback, sha256Hex, uploadRelease, UploadError, RETRIES } = await import('../../resources/js/app-release-upload.js');

const bytes = (n) => Uint8Array.from({ length: n }, (_, i) => (i * 31 + 7) & 255).buffer;
const nodeSha = (buffer) => createHash('sha256').update(Buffer.from(buffer)).digest('hex');
const json = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });

test('the plain-JS SHA-256 matches Node for every padding case', () => {
  for (const n of [0, 1, 55, 56, 63, 64, 65, 119, 1000, 70000]) {
    assert.equal(sha256Fallback(bytes(n)), nodeSha(bytes(n)), `length ${n}`);
  }
});

test('crypto.subtle is used when the page has it, the fallback otherwise', async () => {
  const data = bytes(5000);
  assert.equal(await sha256Hex(data, webcrypto.subtle), nodeSha(data));
  assert.equal(await sha256Hex(data, null), nodeSha(data));
});

test('pieces: at least one, last one partial', () => {
  assert.equal(pieceCount(0, 1024), 1);
  assert.equal(pieceCount(1024, 1024), 1);
  assert.equal(pieceCount(1025, 1024), 2);
});

test('sends every piece in order, then the checksum and the fields', async () => {
  const buffer = bytes(2500);
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, options });
    if (url === '/chunk') return json(200, { next: Number(options.headers['X-Chunk-Index']) + 1 });
    return json(200, { redirect: '/admin/mobile-app?tab=updates' });
  };
  const progress = [];
  const answer = await uploadRelease({ buffer, chunkUrl: '/chunk', storeUrl: '/store', chunkSize: 1000, csrf: 't', id: 'u-1', fields: { notes_en: 'Hi', required: true }, fetchImpl, onProgress: (p) => progress.push(p), subtle: webcrypto.subtle });

  assert.equal(answer.redirect, '/admin/mobile-app?tab=updates');
  const chunks = calls.filter((c) => c.url === '/chunk');
  assert.deepEqual(chunks.map((c) => c.options.headers['X-Chunk-Index']), ['0', '1', '2']);
  assert.deepEqual(chunks.map((c) => c.options.body.byteLength), [1000, 1000, 500]);
  assert.ok(chunks.every((c) => c.options.headers['X-Upload-Id'] === 'u-1' && c.options.headers['X-CSRF-TOKEN'] === 't'));
  const store = JSON.parse(calls.at(-1).options.body);
  assert.deepEqual(store, { upload: 'u-1', sha256: nodeSha(buffer), notes_en: 'Hi', required: true });
  assert.equal(progress.at(-1), 100);
});

test('a piece that fails on the way (network or 5xx) is sent again', async () => {
  let failures = 2;
  const sent = [];
  const fetchImpl = async (url, options) => {
    if (url === '/store') return json(200, { redirect: '/' });
    if (failures-- > 0) {
      if (failures === 1) throw new TypeError('offline');
      return json(503, {});
    }
    sent.push(options.headers['X-Chunk-Index']);
    return json(200, { next: Number(options.headers['X-Chunk-Index']) + 1 });
  };
  const waits = [];
  await uploadRelease({ buffer: bytes(10), chunkUrl: '/chunk', storeUrl: '/store', chunkSize: 100, csrf: '', id: 'x', fetchImpl, wait: async (ms) => waits.push(ms), subtle: webcrypto.subtle });
  assert.deepEqual(sent, ['0']);
  assert.deepEqual(waits, [1000, 2000]);
});

test('gives up after the retries with a message that says what to do', async () => {
  const fetchImpl = async () => json(500, {});
  await assert.rejects(
    uploadRelease({ buffer: bytes(10), chunkUrl: '/c', storeUrl: '/s', chunkSize: 100, csrf: '', id: 'x', fetchImpl, wait: async () => {}, subtle: webcrypto.subtle }),
    (e) => e instanceof UploadError && /upload again/.test(e.message),
  );
  let calls = 0;
  await assert.rejects(uploadRelease({ buffer: bytes(10), chunkUrl: '/c', storeUrl: '/s', chunkSize: 100, csrf: '', id: 'x', fetchImpl: async () => (calls++, json(500, {})), wait: async () => {}, subtle: webcrypto.subtle }));
  assert.equal(calls, RETRIES + 1);
});

test("the server's refusal is shown as it is, and not retried", async () => {
  let calls = 0;
  const refusal = 'Version 0.0.12 (build 15) is not newer than 0.0.13 (build 16), which is already uploaded.';
  const fetchImpl = async (url) => (calls++, url === '/c' ? json(200, { next: 1 }) : json(422, { message: refusal }));
  await assert.rejects(uploadRelease({ buffer: bytes(10), chunkUrl: '/c', storeUrl: '/s', chunkSize: 100, csrf: '', id: 'x', fetchImpl, subtle: webcrypto.subtle }), { message: refusal });
  assert.equal(calls, 2);

  await assert.rejects(
    uploadRelease({ buffer: bytes(10), chunkUrl: '/c', storeUrl: '/s', chunkSize: 100, csrf: '', id: 'x', fetchImpl: async () => json(419, {}), subtle: webcrypto.subtle }),
    /session has expired/,
  );
});

test('the next piece comes from the server (a retried piece it already had is not sent twice)', async () => {
  const sent = [];
  const fetchImpl = async (url, options) => {
    if (url === '/s') return json(200, {});
    const index = Number(options.headers['X-Chunk-Index']);
    sent.push(String(index));
    return json(200, { next: index === 0 ? 2 : index + 1 }); // the server already had piece 1
  };
  await uploadRelease({ buffer: bytes(250), chunkUrl: '/c', storeUrl: '/s', chunkSize: 100, csrf: '', id: 'x', fetchImpl, subtle: webcrypto.subtle });
  assert.deepEqual(sent, ['0', '2']);
});
