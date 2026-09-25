// node --test tests/js  — saving the attendee-list photos on the phone.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { fakeCaches, reply } from './helpers/fake-browser.mjs';
import { avatarUrls, warmAvatars } from '../../resources/js/attendee/avatar-cache.js';

const A = 'https://secure.gravatar.com/avatar/aaa?s=192&d=mm&r=g';
const B = 'https://secure.gravatar.com/avatar/bbb?s=192&d=mm&r=g';
const C = 'https://secure.gravatar.com/avatar/ccc?s=192&d=mm&r=g';
const people = (...urls) => urls.map((gravatar_url, i) => ({ id: i, name: `P${i}`, gravatar_url }));
const photo = (body = 'png') => ({ ...reply(body), type: 'cors' });
const online = { onLine: true, connection: undefined };

test('only real https photo addresses from the photo host are collected, once each', () => {
  assert.deepEqual(
    avatarUrls(people(A, A, B, null, '', 'not a url', 'http://secure.gravatar.com/avatar/x', 'https://evil.example/avatar/x', 'javascript:alert(1)', undefined)).sort(),
    [A, B]
  );
  assert.deepEqual(avatarUrls(undefined), []);
});

test('each photo is fetched with CORS and saved in the photo cache the worker reads', async () => {
  const caches = fakeCaches();
  const asked = [];

  const result = await warmAvatars(people(A, B, C), { cachesImpl: caches, nav: online, fetchImpl: async (url, options) => (asked.push([url, options]), photo(url)) });

  assert.deepEqual(result, { saved: 3, alreadySaved: 0, failed: 0 });
  assert.ok(asked.every(([, options]) => options.mode === 'cors' && options.credentials === 'omit'));
  assert.deepEqual([...caches.stores.keys()], ['campbuddy-avatars']);
  assert.equal((await caches.match(B)).body, B);
  assert.deepEqual(caches.deleted, []);
});

test('a photo already saved is not fetched again', async () => {
  const caches = fakeCaches({ 'campbuddy-avatars': { [A]: photo('kept') } });
  const asked = [];

  const result = await warmAvatars(people(A, B), { cachesImpl: caches, nav: online, fetchImpl: async (url) => (asked.push(url), photo()) });

  assert.deepEqual(result, { saved: 1, alreadySaved: 1, failed: 0 });
  assert.deepEqual(asked, [B]);
  assert.equal((await caches.match(A)).body, 'kept');
});

test('an error, a missing photo or a non-CORS copy is not saved', async () => {
  const caches = fakeCaches();
  const answers = { [A]: async () => { throw new TypeError('offline'); }, [B]: async () => reply('gone', { status: 404 }), [C]: async () => ({ ...reply('opaque'), type: 'opaque' }) };

  const result = await warmAvatars(people(A, B, C), { cachesImpl: caches, nav: online, fetchImpl: (url) => answers[url]() });

  assert.equal(result.saved, 0);
  assert.equal(result.failed, 3);
  assert.equal(await caches.match(A), undefined);
});

test('it gives up after three failures in a row (a dead connection is not hammered)', async () => {
  const urls = Array.from({ length: 40 }, (_, i) => `https://secure.gravatar.com/avatar/${i}`);
  let asked = 0;

  const result = await warmAvatars(people(...urls), { cachesImpl: fakeCaches(), nav: online, fetchImpl: async () => { asked++; throw new TypeError('offline'); } });

  assert.ok(asked <= 6, `asked ${asked} times`);
  assert.ok(result.failed >= 3 && result.saved === 0);
});

test('at most 3 at a time', async () => {
  const urls = Array.from({ length: 20 }, (_, i) => `https://secure.gravatar.com/avatar/${i}`);
  let inFlight = 0;
  let peak = 0;

  await warmAvatars(people(...urls), {
    cachesImpl: fakeCaches(),
    nav: online,
    fetchImpl: async () => {
      peak = Math.max(peak, ++inFlight);
      await new Promise((resolve) => setTimeout(resolve, 3));
      inFlight--;
      return photo();
    },
  });

  assert.equal(peak, 3);
});

test('at most 1000 photos are fetched in one visit', async () => {
  const urls = Array.from({ length: 1500 }, (_, i) => `https://secure.gravatar.com/avatar/${i}`);
  const caches = fakeCaches();

  const result = await warmAvatars(people(...urls), { cachesImpl: caches, nav: online, fetchImpl: async () => photo() });

  assert.equal(result.saved, 1000);
});

test('offline, on Data Saver, on a 2G-class connection, or without cache storage it does nothing at all', async () => {
  for (const nav of [{ onLine: false }, { onLine: true, connection: { saveData: true } }, { onLine: true, connection: { effectiveType: '2g' } }, { onLine: true, connection: { effectiveType: 'slow-2g' } }]) {
    let asked = 0;
    const result = await warmAvatars(people(A), { cachesImpl: fakeCaches(), nav, fetchImpl: async () => { asked++; return photo(); } });

    assert.equal(result.skipped, 'held-off');
    assert.equal(asked, 0);
  }

  assert.equal((await warmAvatars(people(A), { cachesImpl: null, nav: online })).skipped, 'no-cache-storage');
});

test('nothing it does can throw into the page', async () => {
  const broken = { open: async () => { throw new Error('blocked'); } };

  assert.deepEqual(await warmAvatars(people(A), { cachesImpl: broken, nav: online, fetchImpl: async () => photo() }), { saved: 0, alreadySaved: 0, failed: 0 });
  assert.deepEqual(await warmAvatars(null, { cachesImpl: fakeCaches(), nav: online, fetchImpl: async () => photo() }), { saved: 0, alreadySaved: 0, failed: 0 });
});
