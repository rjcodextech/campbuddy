// node --test tests/js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { OUTBOX_KEY, createOutbox, isPermanentFailure } from '../../resources/js/attendee/outbox.js';
import { ROSTER_KEY, createRosterStore, sameRoster, savedWhen } from '../../resources/js/attendee/roster-store.js';

/** An in-memory stand-in for the IndexedDB key/value store. */
function memoryKv({ failWrites = false, failReads = false } = {}) {
  const map = new Map();

  return {
    map,
    kvGet: async (key) => { if (failReads) throw new Error('blocked'); return map.has(key) ? structuredClone(map.get(key)) : undefined; },
    kvSet: async (key, value) => { if (failWrites) throw new Error('quota'); map.set(key, structuredClone(value)); },
  };
}

const outboxFor = (kv, now = () => 1000) => createOutbox({ kvGet: kv.kvGet, kvSet: kv.kvSet, now });

// ------------------------------------------------------------------ outbox

test('a wish made offline is kept until it can be sent', async () => {
  const kv = memoryKv();
  const outbox = outboxFor(kv);

  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 7, eventId: 1 });

  assert.deepEqual(await outbox.pending('wc-test'), [{ kind: 'reminder-on', sessionId: 7, eventId: 1, at: 1000 }]);
  assert.ok(kv.map.has(OUTBOX_KEY('wc-test')));
  assert.deepEqual(await outbox.pending('another-event'), []);
});

test('the latest wish about a session replaces an earlier one', async () => {
  const outbox = outboxFor(memoryKv());

  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 7 });
  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 8 });
  await outbox.add('wc-test', { kind: 'reminder-off', sessionId: 7 });

  assert.deepEqual((await outbox.pending('wc-test')).map((i) => [i.kind, i.sessionId]), [['reminder-on', 8], ['reminder-off', 7]]);
});

test('taking a session off the day forgets what was queued for it', async () => {
  const outbox = outboxFor(memoryKv());
  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 7 });
  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 8 });

  await outbox.drop('wc-test', 7);

  assert.deepEqual((await outbox.pending('wc-test')).map((i) => i.sessionId), [8]);
  assert.equal(await outbox.drop('wc-test', 99), true, 'nothing queued: fine');
});

test('flushing sends everything that can go, keeps what cannot, and drops what can no longer mean anything', async () => {
  const outbox = outboxFor(memoryKv());
  for (const sessionId of [1, 2, 3, 4]) await outbox.add('wc-test', { kind: 'reminder-on', sessionId });
  const sentIds = [];

  const result = await outbox.flush('wc-test', async (item) => {
    if (item.sessionId === 1) { sentIds.push(1); return undefined; }
    if (item.sessionId === 2) return 'drop'; // session no longer on their day
    if (item.sessionId === 3) throw Object.assign(new Error('refused'), { permanent: true });
    throw new TypeError('offline');
  });

  assert.deepEqual(result, { sent: 1, dropped: 2, remaining: 1 });
  assert.deepEqual(sentIds, [1]);
  assert.deepEqual((await outbox.pending('wc-test')).map((i) => [i.sessionId, i.attempts]), [[4, 1]]);
});

test('an item that keeps failing while online is eventually given up on (8 tries), not retried forever', async () => {
  const outbox = outboxFor(memoryKv());
  await outbox.add('wc-test', { kind: 'reminder-on', sessionId: 1 });

  for (let i = 1; i < 8; i++) {
    assert.equal((await outbox.flush('wc-test', async () => { throw new TypeError('server down'); })).remaining, 1, `try ${i}`);
  }
  assert.deepEqual(await outbox.flush('wc-test', async () => { throw new TypeError('server down'); }), { sent: 0, dropped: 1, remaining: 0 });
  assert.deepEqual(await outbox.pending('wc-test'), []);
});

test('the queue is bounded, and blocked storage is never an error', async () => {
  const outbox = outboxFor(memoryKv());
  for (let i = 0; i < 130; i++) await outbox.add('wc-test', { kind: 'reminder-on', sessionId: i });
  const pending = await outbox.pending('wc-test');

  assert.equal(pending.length, 100);
  assert.equal(pending.at(-1).sessionId, 129, 'the newest are kept');

  const broken = outboxFor(memoryKv({ failWrites: true, failReads: true }));
  assert.equal(await broken.add('wc-test', { kind: 'reminder-on', sessionId: 1 }), false);
  assert.deepEqual(await broken.pending('wc-test'), []);
  assert.deepEqual(await broken.flush('wc-test', async () => assert.fail('nothing to send')), { sent: 0, dropped: 0, remaining: 0 });
});

test('only a final answer from the server is permanent: a 4xx, but not "busy" or "timed out", and never a network error', () => {
  assert.equal(isPermanentFailure({ status: 422 }), true);
  assert.equal(isPermanentFailure({ status: 403 }), true);
  assert.equal(isPermanentFailure({ status: 404 }), true);
  assert.equal(isPermanentFailure({ status: 429 }), false);
  assert.equal(isPermanentFailure({ status: 408 }), false);
  assert.equal(isPermanentFailure({ status: 500 }), false);
  assert.equal(isPermanentFailure({ status: 503 }), false);
  assert.equal(isPermanentFailure(new TypeError('Failed to fetch')), false);
  assert.equal(isPermanentFailure(null), false);
});

// ------------------------------------------------------------------ saved attendee list

const PEOPLE = [{ id: 1, name: 'Asha Rao', open_to_meet: true, links: [] }, { id: 2, name: 'Ben Lee', open_to_meet: false, links: [] }];

test('the attendee list is kept on the phone and read back', async () => {
  const kv = memoryKv();
  const store = createRosterStore({ kvGet: kv.kvGet, kvSet: kv.kvSet, now: () => 555 });

  assert.equal(await store.read('wc-test'), null, 'nothing saved yet');
  assert.equal(await store.write('wc-test', PEOPLE), true);

  assert.deepEqual(await store.read('wc-test'), { entries: PEOPLE, savedAt: 555 });
  assert.ok(kv.map.has(ROSTER_KEY('wc-test')));
  assert.equal(await store.read('other-event'), null);
});

test('an empty or broken answer never erases the list the phone already has', async () => {
  const kv = memoryKv();
  const store = createRosterStore({ kvGet: kv.kvGet, kvSet: kv.kvSet });
  await store.write('wc-test', PEOPLE);

  assert.equal(await store.write('wc-test', []), false);
  assert.equal(await store.write('wc-test', null), false);
  assert.equal((await store.read('wc-test')).entries.length, 2);
});

test('blocked or corrupt storage reads as "nothing saved", and never throws', async () => {
  const blocked = createRosterStore(memoryKv({ failReads: true, failWrites: true }));
  assert.equal(await blocked.read('wc-test'), null);
  assert.equal(await blocked.write('wc-test', PEOPLE), false);

  const kv = memoryKv();
  kv.map.set(ROSTER_KEY('wc-test'), { entries: 'garbage' });
  assert.equal(await createRosterStore(kv).read('wc-test'), null);
});

test('two lists are the same only when they show the same people in the same order', () => {
  assert.equal(sameRoster(PEOPLE, structuredClone(PEOPLE)), true);
  assert.equal(sameRoster(PEOPLE, [...PEOPLE].reverse()), false);
  assert.equal(sameRoster(PEOPLE, [{ ...PEOPLE[0], open_to_meet: false }, PEOPLE[1]]), false);
});

test('the saved-at time reads in the phone own clock and is empty when unknown', () => {
  assert.equal(savedWhen(null), '');
  assert.match(savedWhen(Date.parse('2026-10-10T15:20:00Z'), 'en-US'), /\d{1,2}:\d{2}/);
});
