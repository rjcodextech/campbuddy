// node --experimental-test-module-mocks --test tests/js
//
// Trying to break the phone's own storage: the real db.js against an
// IndexedDB that is full, switched off, held by an older tab, or closed under
// it; and the modules that read saved state fed garbage — wrong shapes, older
// versions, storage that throws. Nothing may hang, white-screen, or lose the
// rest of the person's data.
import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { freshImport } from './helpers/fake-browser.mjs';
import { fakeIndexedDB } from './helpers/fake-indexeddb.mjs';
import { createOutbox } from '../../resources/js/attendee/outbox.js';
import { createRosterStore } from '../../resources/js/attendee/roster-store.js';

const DB = new URL('../../resources/js/attendee/db.js', import.meta.url).href;

let savedIndexedDB;

function useIndexedDB(fake) {
  savedIndexedDB ??= Object.getOwnPropertyDescriptor(globalThis, 'indexedDB') ?? null;
  Object.defineProperty(globalThis, 'indexedDB', { value: fake, configurable: true, writable: true });

  return fake;
}

afterEach(() => {
  if (savedIndexedDB) Object.defineProperty(globalThis, 'indexedDB', savedIndexedDB);
  else delete globalThis.indexedDB;
  savedIndexedDB = undefined;
});

/** Resolves with the promise's outcome, or 'hung' when it has not settled within `ms`. */
function outcome(promise, ms = 500) {
  return Promise.race([
    promise.then((value) => ({ value }), (error) => ({ error })),
    new Promise((resolve) => setTimeout(() => resolve('hung'), ms)),
  ]);
}

// ---------------------------------------------------------------- db.js

test('saving and reading back works on the fake database (the baseline for everything below)', async () => {
  useIndexedDB(fakeIndexedDB());
  const db = await freshImport(DB);

  await db.kvSet('campCard', { name: 'Asha' });
  await db.setBookmark(7, 101, false, { title: 'Keynote' });

  assert.deepEqual(await db.kvGet('campCard'), { name: 'Asha' });
  assert.deepEqual((await db.getBookmarks(7)).map((b) => b.sessionId), [101]);
});

test('a phone that is out of space makes a save fail, instead of leaving it waiting for ever', async () => {
  const idb = useIndexedDB(fakeIndexedDB());
  const db = await freshImport(DB);
  await db.kvSet('campCard', { name: 'Asha' });

  idb.options.quotaOnCommit = true; // the commit aborts; browsers fire only `abort`, never `error`
  const result = await outcome(db.kvSet('campCard', { name: 'Asha', bio: 'x'.repeat(1000) }));

  assert.notEqual(result, 'hung', 'the save must settle');
  assert.equal(result.error?.name, 'QuotaExceededError');

  idb.options.quotaOnCommit = false;
  assert.deepEqual(await db.kvGet('campCard'), { name: 'Asha' }, 'what was saved before is still there');
});

test('clearing everything on a full phone fails cleanly too', async () => {
  const idb = useIndexedDB(fakeIndexedDB());
  const db = await freshImport(DB);
  await db.kvSet('onboarding', { tags: ['php'] });

  idb.options.quotaOnCommit = true;
  const result = await outcome(db.clearAll());

  assert.notEqual(result, 'hung');
  assert.ok(result.error);
});

test('with storage switched off (open() throws), every read and write rejects at once — none throws synchronously or hangs', async () => {
  useIndexedDB(fakeIndexedDB({ throwOnOpen: true }));
  const db = await freshImport(DB);

  for (const call of [() => db.kvGet('campCard'), () => db.kvSet('x', 1), () => db.getBookmarks(1), () => db.getQuestProgress(1), () => db.getMeetings(1)]) {
    let promise;
    assert.doesNotThrow(() => { promise = call(); });
    const result = await outcome(promise);
    assert.notEqual(result, 'hung');
    assert.equal(result.error?.name, 'SecurityError');
  }
});

test('with no IndexedDB at all, calls reject instead of throwing', async () => {
  useIndexedDB(undefined);
  const db = await freshImport(DB);

  const result = await outcome(db.kvGet('campCard'));
  assert.notEqual(result, 'hung');
  assert.ok(result.error);
});

test('a database that failed to open once is tried again on the next call, not given up on for the whole visit', async () => {
  const idb = useIndexedDB(fakeIndexedDB({ failOpen: 1 }));
  const db = await freshImport(DB);

  assert.ok((await outcome(db.kvGet('campCard'))).error, 'the first open fails');
  await db.kvSet('campCard', { name: 'Ravi' });

  assert.deepEqual(await db.kvGet('campCard'), { name: 'Ravi' });
  assert.equal(idb.opens, 2);
});

test('when a newer version of the app needs the database, this tab lets go of it at once', async () => {
  const idb = useIndexedDB(fakeIndexedDB());
  const db = await freshImport(DB);
  await db.kvSet('campCard', { name: 'Asha' });

  // "Another tab" with a newer app asks for version 3.
  const newer = await outcome(idb.openElsewhere('campbuddy', 3), 1000);

  assert.notEqual(newer, 'hung', 'the newer tab is not blocked by this one');
  assert.equal(newer.value.version, 3);
});

test('an older tab that never lets go does not leave this tab hanging: calls fail after a short wait, and work once it is gone', async () => {
  const idb = useIndexedDB(fakeIndexedDB());
  // An old tab (version 1, an app without the versionchange handler) holds the database.
  const old = await idb.openElsewhere('campbuddy', 1);
  const db = await freshImport(DB);

  const result = await outcome(db.kvGet('campCard'), 5000);
  assert.notEqual(result, 'hung', 'gave up waiting');
  assert.ok(result.error);

  old.close();
  await new Promise((resolve) => setTimeout(resolve, 20));
  await db.kvSet('campCard', { name: 'Mia' });
  assert.deepEqual(await db.kvGet('campCard'), { name: 'Mia' });
});

test('a connection the browser closed (site data cleared) is opened again instead of failing for the rest of the visit', async () => {
  const idb = useIndexedDB(fakeIndexedDB());
  const db = await freshImport(DB);
  await db.kvSet('campCard', { name: 'Asha' });

  idb.dropConnections();

  const result = await outcome(db.kvGet('campCard'));
  assert.notEqual(result, 'hung');
  assert.deepEqual(result.value, { name: 'Asha' });
});

// ---------------------------------------------------------------- outbox: garbage and races

function memoryKv(initial = {}) {
  const map = new Map(Object.entries(initial));

  return {
    map,
    kvGet: async (key) => (map.has(key) ? structuredClone(map.get(key)) : undefined),
    kvSet: async (key, value) => void map.set(key, structuredClone(value)),
  };
}

test('an outbox saved in an older or broken shape is read as empty, not crashed on', async () => {
  for (const garbage of ['not json', 42, { items: [] }, null, true]) {
    const kv = memoryKv({ 'outbox:wc': garbage });
    const outbox = createOutbox({ ...kv, now: () => 1 });

    assert.deepEqual(await outbox.pending('wc'), [], JSON.stringify(garbage));
    assert.equal(await outbox.add('wc', { kind: 'reminder-on', sessionId: 1 }), true);
    assert.equal((await outbox.pending('wc')).length, 1);
  }
});

test('broken entries inside a saved outbox are skipped; the good wishes around them are kept and sent', async () => {
  const kv = memoryKv({ 'outbox:wc': [null, 'x', 7, { kind: 'reminder-on', sessionId: 5, at: 1 }, [], { kind: 'reminder-off', sessionId: 6, at: 2 }] });
  const outbox = createOutbox({ ...kv, now: () => 3 });

  await outbox.add('wc', { kind: 'reminder-on', sessionId: 9 });
  assert.deepEqual((await outbox.pending('wc')).map((i) => i.sessionId), [5, 6, 9]);

  const sent = [];
  const result = await outbox.flush('wc', async (item) => { sent.push(item.sessionId); });
  assert.deepEqual(sent, [5, 6, 9]);
  assert.deepEqual(result, { sent: 3, dropped: 0, remaining: 0 });
});

test('a wish made while the outbox is being sent is not lost when the send finishes', async () => {
  const kv = memoryKv();
  const outbox = createOutbox({ ...kv, now: () => 1 });
  await outbox.add('wc', { kind: 'reminder-on', sessionId: 1 });

  let release;
  const slow = new Promise((resolve) => { release = resolve; });
  const flushing = outbox.flush('wc', async () => slow);

  await new Promise((resolve) => setImmediate(resolve));
  // Meanwhile the person taps "remind me" on another talk (offline again, say).
  await outbox.add('wc', { kind: 'reminder-on', sessionId: 2 });
  release();
  await flushing;

  assert.deepEqual((await outbox.pending('wc')).map((i) => i.sessionId), [2], 'the new wish waits for the next send');
});

test('a session changed while its earlier wish was being sent keeps the newer wish', async () => {
  const kv = memoryKv();
  let clock = 1;
  const outbox = createOutbox({ ...kv, now: () => clock++ });
  await outbox.add('wc', { kind: 'reminder-on', sessionId: 1 });

  let release;
  const flushing = outbox.flush('wc', () => new Promise((resolve) => { release = resolve; }));
  await new Promise((resolve) => setImmediate(resolve));
  await outbox.add('wc', { kind: 'reminder-off', sessionId: 1 });
  release();
  await flushing;

  assert.deepEqual((await outbox.pending('wc')).map((i) => i.kind), ['reminder-off']);
});

test('two sends started together (two "back online" events) send each wish once', async () => {
  const kv = memoryKv();
  const outbox = createOutbox({ ...kv, now: () => 1 });
  await outbox.add('wc', { kind: 'reminder-on', sessionId: 1 });
  await outbox.add('wc', { kind: 'reminder-on', sessionId: 2 });

  const sent = [];
  const send = async (item) => {
    await new Promise((resolve) => setImmediate(resolve));
    sent.push(item.sessionId);
  };
  await Promise.all([outbox.flush('wc', send), outbox.flush('wc', send)]);

  assert.deepEqual(sent.sort(), [1, 2]);
  assert.deepEqual(await outbox.pending('wc'), []);
});

// ---------------------------------------------------------------- roster: older shapes

test('a saved attendee list in an older or broken shape is treated as none, and a good list can be saved over it', async () => {
  for (const garbage of ['[]', [{ name: 'a' }], { entries: 'x' }, { entries: [] }, 0]) {
    const kv = memoryKv({ 'roster:wc': garbage });
    const store = createRosterStore({ ...kv, now: () => 5 });

    assert.equal(await store.read('wc'), null, JSON.stringify(garbage));
    assert.equal(await store.write('wc', [{ name: 'Asha' }]), true);
    assert.deepEqual((await store.read('wc')).entries, [{ name: 'Asha' }]);
  }
});
