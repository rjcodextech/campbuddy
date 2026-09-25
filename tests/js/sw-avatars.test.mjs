// node --test tests/js  — attendee-list photos (Gravatar) in the real public/sw.js.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { fakeCacheStorage, loadServiceWorker, page, settle } from './helpers/load-sw.mjs';

const PHOTO = 'https://secure.gravatar.com/avatar/59a166f8?s=192&d=mm&r=g';
const AVATARS = 'campbuddy-avatars';
const DAY = 24 * 60 * 60 * 1000;

const httpDate = (agoMs) => new Date(Date.now() - agoMs).toUTCString();
const bodyOf = async (result) => (result.response ? result.response.text() : null);
const photo = ({ status = 200, agoMs = 1000, body = 'png-bytes', type = 'cors' } = {}) => page(body, { status, type, headers: { 'content-type': 'image/png', date: httpDate(agoMs) } });

test('a photo is fetched once with CORS (so a normal copy can be kept), shown, and saved for offline', async () => {
  const sw = loadServiceWorker({ network: () => photo() });

  const first = await sw.image(PHOTO);
  await Promise.all(first.waits);
  await settle();

  assert.equal(await bodyOf(first), 'png-bytes');
  assert.equal(JSON.stringify(sw.fetches.map((f) => [f.url, f.init])), JSON.stringify([[PHOTO, { mode: 'cors', credentials: 'omit' }]]));
  assert.equal(await (await (await sw.caches.open(AVATARS)).match(PHOTO)).text(), 'png-bytes');

  const second = await sw.image(PHOTO);
  assert.equal(await bodyOf(second), 'png-bytes');
  assert.equal(sw.fetches.length, 1, 'served from the saved copy: no second request');
});

test('with no connection a saved photo still shows; one never saved fails, so the placeholder avatar shows', async () => {
  const caches = fakeCacheStorage({ [AVATARS]: { [PHOTO]: photo() } });
  const sw = loadServiceWorker({ caches });

  assert.equal(await bodyOf(await sw.image(PHOTO)), 'png-bytes');

  const unseen = await sw.image('https://secure.gravatar.com/avatar/never?s=192');
  assert.equal(unseen.response.type, 'error', 'an error response, exactly like a failed image load');
});

test('if the CORS attempt fails the photo is still loaded the way the page asked for it (and not saved)', async () => {
  let calls = 0;
  const sw = loadServiceWorker({
    network: () => {
      calls++;
      if (calls === 1) throw new TypeError('no CORS header');

      return page('opaque-ish', { type: 'opaque' });
    },
  });

  const result = await sw.image(PHOTO);
  await Promise.all(result.waits);
  await settle();

  assert.equal(calls, 2, 'the CORS try, then the plain request');
  assert.equal(await (await sw.caches.open(AVATARS)).match(PHOTO), undefined, 'an opaque copy is never kept');
});

test('a missing or failing photo is passed on as it is and never saved', async () => {
  for (const status of [404, 500]) {
    const sw = loadServiceWorker({ network: () => photo({ status }) });
    const result = await sw.image(PHOTO);
    await Promise.all(result.waits);
    await settle();

    assert.equal(result.response.status, status);
    assert.equal(await (await sw.caches.open(AVATARS)).match(PHOTO), undefined);
  }
});

test('only image requests to the photo host are handled; everything else is left as before', async () => {
  const sw = loadServiceWorker({ network: () => photo() });

  assert.equal((await sw.request(PHOTO, { destination: '' })).handled, false, 'not an image request');
  assert.equal((await sw.request(PHOTO, { destination: 'script' })).handled, false);
  assert.equal((await sw.image('https://cdn.example.com/avatar.png')).handled, false, 'another host');
  assert.equal((await sw.request(PHOTO, { method: 'POST', destination: 'image' })).handled, false);
  assert.equal(sw.fetches.length, 0);
});

test('a week-old saved photo is shown at once and replaced in the background; never deleted', async () => {
  const caches = fakeCacheStorage({ [AVATARS]: { [PHOTO]: photo({ agoMs: 8 * DAY }) } });
  const sw = loadServiceWorker({ caches, network: () => photo({ body: 'new-photo', agoMs: 0 }) });

  const result = await sw.image(PHOTO);
  assert.equal(await bodyOf(result), 'png-bytes', 'the saved one, instantly');
  await Promise.all(result.waits);
  await settle();

  assert.equal(await (await (await sw.caches.open(AVATARS)).match(PHOTO)).text(), 'new-photo');
  assert.deepEqual(caches.log.deletedEntries, [], 'replaced in place, not deleted');
  assert.equal(sw.fetches.length, 1);
});

test('a recent saved photo is not refreshed, and a failed refresh keeps the copy', async () => {
  const recent = fakeCacheStorage({ [AVATARS]: { [PHOTO]: photo() } });
  const sw1 = loadServiceWorker({ caches: recent });
  await Promise.all((await sw1.image(PHOTO)).waits);
  assert.equal(sw1.fetches.length, 0);

  const old = fakeCacheStorage({ [AVATARS]: { [PHOTO]: photo({ agoMs: 9 * DAY }) } });
  const sw2 = loadServiceWorker({ caches: old }); // offline
  const result = await sw2.image(PHOTO);
  await Promise.all(result.waits);
  await settle();

  assert.equal(await bodyOf(result), 'png-bytes');
  assert.equal(await (await (await sw2.caches.open(AVATARS)).match(PHOTO)).text(), 'png-bytes', 'kept');
});

test('no more than 3000 photos are kept (and nothing is deleted to make room)', async () => {
  const entries = Object.fromEntries(Array.from({ length: 3000 }, (_, i) => [`https://secure.gravatar.com/avatar/${i}`, photo()]));
  const caches = fakeCacheStorage({ [AVATARS]: entries });
  const sw = loadServiceWorker({ caches, network: () => photo() });

  const result = await sw.image('https://secure.gravatar.com/avatar/one-too-many');
  await Promise.all(result.waits);
  await settle();

  assert.equal(await bodyOf(result), 'png-bytes', 'still shown');
  assert.equal((await (await sw.caches.open(AVATARS)).keys()).length, 3000);
  assert.deepEqual(caches.log.deletedEntries, []);
});

test('if storage is unavailable the photo loads exactly as it would without the worker', async () => {
  const broken = { ...fakeCacheStorage(), open: async () => { throw new Error('blocked'); } };
  const sw = loadServiceWorker({ caches: broken, network: () => photo() });

  const result = await sw.image(PHOTO);

  assert.equal(await bodyOf(result), 'png-bytes');
  assert.deepEqual(sw.fetches.map((f) => f.init), [undefined], 'the page request itself, passed through');
});
