// node --experimental-test-module-mocks --test tests/js
//
// Reminders that survive a bad connection: the real push.js and push-sync.js,
// with the storage, the API and the browser push machinery replaced by fakes.
import assert from 'node:assert/strict';
import { beforeEach, mock, test } from 'node:test';

const DIR = new URL('../../resources/js/attendee/', import.meta.url).href;

// ---- fakes -------------------------------------------------------------------
const kv = new Map();
let bookmarks = [];
let apiCalls = [];
let tracked = [];
let apiFails = null; // (path, method) => Error | undefined

mock.module(`${DIR}db.js`, {
  exports: {
    kvGet: async (key) => (kv.has(key) ? structuredClone(kv.get(key)) : undefined),
    kvSet: async (key, value) => void kv.set(key, structuredClone(value)),
    getBookmarks: async (eventId) => bookmarks.filter((b) => b.eventId === eventId),
    setBookmark: async (eventId, sessionId, reminderEnabled) => {
      bookmarks = bookmarks.filter((b) => !(b.eventId === eventId && b.sessionId === sessionId));
      bookmarks.push({ eventId, sessionId, reminderEnabled });
    },
  },
});
mock.module(`${DIR}api.js`, {
  exports: {
    apiMutate: async (slug, path, method, body) => {
      const failure = apiFails?.(path, method);
      if (failure) throw failure;
      apiCalls.push({ slug, path, method, body });

      return null;
    },
  },
});
mock.module(`${DIR}analytics.js`, { exports: { track: (name, params) => tracked.push([name, params]) } });
mock.module(`${DIR}device.js`, { exports: { getDeviceId: () => 'device-1' } });
mock.module(`${DIR}platform.js`, { exports: { isIos: () => false, isStandalone: () => false } });
mock.module(`${DIR}template.js`, { exports: { render: () => ({ querySelector: () => ({ addEventListener() {} }), showModal() {} }) } });

const push = await import(`${DIR}push.js`);
const sync = await import(`${DIR}push-sync.js`);

let subscription;
let subscribeCalls;

function installBrowser({ permission = 'granted', existing = null, online = true } = {}) {
  subscription = existing;
  subscribeCalls = 0;
  const registration = {
    pushManager: {
      getSubscription: async () => subscription,
      subscribe: async () => {
        subscribeCalls++;
        subscription = { endpoint: 'https://push.example.test/new', getKey: () => new Uint8Array([1, 2, 3]).buffer };

        return subscription;
      },
    },
  };

  globalThis.Notification = { permission };
  globalThis.window = { Notification: globalThis.Notification, PushManager: class {} };
  Object.defineProperty(globalThis, 'navigator', { value: { onLine: online, serviceWorker: { ready: Promise.resolve(registration) } }, configurable: true, writable: true });
  globalThis.document = {
    querySelector: (selector) => (selector.includes('vapid-public-key') ? { content: 'BM_hD8Ox0zWMrOtvvXHVl2PpzSL_jvcW7c5ixy1SXlwKASmhCr0ayk6GQmfqDcWyKx9YYWkih-SM7mC9kTYt_fo' } : null),
    getElementById: (id) => (id === 'app' ? { dataset: { eventSlug: 'wc-test', eventId: '1' } } : null),
  };
}

const subscriptionOf = (endpoint) => ({ endpoint, getKey: () => new Uint8Array([9, 9, 9]).buffer });

beforeEach(() => {
  kv.clear();
  bookmarks = [];
  apiCalls = [];
  tracked = [];
  apiFails = null;
  kv.set('notificationState', { enabled: true });
  installBrowser();
});

const offline = () => new TypeError('Failed to fetch');
const refused = (status) => Object.assign(new Error(`refused ${status}`), { status });
const queued = async () => push.outbox.pending('wc-test');

// ---- setting a reminder ------------------------------------------------------------

test('with a connection a reminder is set up as before: subscription, then bookmark, then remembered locally', async () => {
  const ok = await push.offerReminder('wc-test', 1, 7);

  assert.equal(ok, true);
  assert.deepEqual(apiCalls.map((c) => c.path), ['/push/subscribe', '/bookmarks']);
  assert.deepEqual(apiCalls[1].body, { device_id: 'device-1', session_id: 7, reminder_enabled: true });
  assert.deepEqual(bookmarks, [{ eventId: 1, sessionId: 7, reminderEnabled: true }]);
  assert.deepEqual(await queued(), []);
});

test('offline, the request is kept — not lost — and reported as queued', async () => {
  apiFails = () => offline();

  const ok = await push.offerReminder('wc-test', 1, 7);

  assert.equal(ok, false);
  assert.deepEqual((await queued()).map((i) => [i.kind, i.sessionId, i.eventId]), [['reminder-on', 7, 1]]);
  assert.deepEqual(tracked.at(-1), ['reminder_offer', { result: 'queued' }]);
});

test('when the server refuses for good (422), it is not queued and is reported as an error', async () => {
  apiFails = () => refused(422);

  await push.offerReminder('wc-test', 1, 7);

  assert.deepEqual(await queued(), []);
  assert.deepEqual(tracked.at(-1), ['reminder_offer', { result: 'error' }]);
});

test('a "no" is still a no: nothing is queued when notifications were never allowed', async () => {
  kv.set('notificationState', { declined: true });
  installBrowser({ permission: 'default' });

  assert.equal(await push.offerReminder('wc-test', 1, 7), false);
  assert.deepEqual(await queued(), []);
  assert.deepEqual(apiCalls, []);
});

// ---- cancelling ------------------------------------------------------------------------

test('cancelling offline is kept too, so a dropped session does not still buzz the phone', async () => {
  apiFails = () => offline();

  await push.cancelReminder('wc-test', { sessionId: 7, reminderEnabled: true });

  assert.deepEqual((await queued()).map((i) => [i.kind, i.sessionId]), [['reminder-off', 7]]);
});

test('taking a session off the day forgets a reminder that was still waiting for it', async () => {
  apiFails = () => offline();
  await push.offerReminder('wc-test', 1, 7);
  assert.equal((await queued()).length, 1);

  await push.cancelReminder('wc-test', { sessionId: 7, reminderEnabled: false }); // the local bookmark never got the reminder flag

  assert.deepEqual(await queued(), []);
  assert.deepEqual(apiCalls, [], 'and nothing was sent about it');
});

// ---- sending what was kept ----------------------------------------------------------------

test('back online, waiting requests are sent; the reminder is confirmed locally', async () => {
  apiFails = () => offline();
  await push.offerReminder('wc-test', 1, 7);
  await push.cancelReminder('wc-test', { sessionId: 9, reminderEnabled: true });
  apiFails = null;
  bookmarks = [{ eventId: 1, sessionId: 7, reminderEnabled: false }]; // saved offline, no reminder flag yet

  const result = await sync.flushOutbox('wc-test');

  assert.deepEqual(result, { sent: 2, dropped: 0, remaining: 0 });
  const paths = apiCalls.map((c) => `${c.method} ${c.path}`);
  assert.ok(paths.includes('POST /push/subscribe') && paths.includes('POST /bookmarks') && paths.includes('DELETE /bookmarks'));
  assert.equal(bookmarks.find((b) => b.sessionId === 7).reminderEnabled, true);
  assert.deepEqual(await queued(), []);
});

test('a reminder for a session that is no longer on their day is dropped, not resurrected', async () => {
  apiFails = () => offline();
  await push.offerReminder('wc-test', 1, 7);
  apiFails = null;
  bookmarks = []; // they took it off their day

  const result = await sync.flushOutbox('wc-test');

  assert.deepEqual(result, { sent: 0, dropped: 1, remaining: 0 });
  assert.deepEqual(apiCalls, []);
  assert.deepEqual(bookmarks, [], 'the bookmark did not come back');
});

test('a reminder is dropped if notifications were switched off in the meantime', async () => {
  apiFails = () => offline();
  await push.offerReminder('wc-test', 1, 7);
  apiFails = null;
  bookmarks = [{ eventId: 1, sessionId: 7, reminderEnabled: false }];
  installBrowser({ permission: 'denied' });

  assert.deepEqual(await sync.flushOutbox('wc-test'), { sent: 0, dropped: 1, remaining: 0 });
});

test('a server still down keeps the request for the next reconnection; a final refusal drops it', async () => {
  apiFails = () => offline();
  await push.offerReminder('wc-test', 1, 7);
  bookmarks = [{ eventId: 1, sessionId: 7, reminderEnabled: false }];

  assert.deepEqual(await sync.flushOutbox('wc-test'), { sent: 0, dropped: 0, remaining: 1 });

  apiFails = () => refused(422);
  assert.deepEqual(await sync.flushOutbox('wc-test'), { sent: 0, dropped: 1, remaining: 0 });
});

// ---- push subscription upkeep ----------------------------------------------------------------

test('a subscription the server already knows, seen recently, is left alone', async () => {
  installBrowser({ existing: subscriptionOf('https://push.example.test/a') });
  kv.set('pushEndpoint:wc-test', { endpoint: 'https://push.example.test/a', at: Date.now() - 60_000 });

  assert.equal(await sync.syncPushSubscription('wc-test'), 'current');
  assert.deepEqual(apiCalls, []);
});

test('when the browser has replaced the subscription, the server is told the new address', async () => {
  installBrowser({ existing: subscriptionOf('https://push.example.test/b') });
  kv.set('pushEndpoint:wc-test', { endpoint: 'https://push.example.test/a', at: Date.now() - 60_000 });

  assert.equal(await sync.syncPushSubscription('wc-test'), 'sent');
  assert.equal(apiCalls[0].path, '/push/subscribe');
  assert.equal(apiCalls[0].body.endpoint, 'https://push.example.test/b');
  assert.equal(kv.get('pushEndpoint:wc-test').endpoint, 'https://push.example.test/b');
});

test('a subscription the browser dropped altogether is made again and told to the server', async () => {
  installBrowser({ existing: null });

  assert.equal(await sync.syncPushSubscription('wc-test'), 'sent');
  assert.equal(subscribeCalls, 1);
  assert.equal(apiCalls[0].body.endpoint, 'https://push.example.test/new');
});

test('an old, unchanged subscription is reported to the server again after a week', async () => {
  installBrowser({ existing: subscriptionOf('https://push.example.test/a') });
  kv.set('pushEndpoint:wc-test', { endpoint: 'https://push.example.test/a', at: Date.now() - 8 * 24 * 60 * 60 * 1000 });

  assert.equal(await sync.syncPushSubscription('wc-test'), 'sent');
});

test('people who never said yes to reminders are never touched', async () => {
  kv.set('notificationState', { declined: true });
  assert.equal(await sync.syncPushSubscription('wc-test'), 'skipped');

  kv.set('notificationState', { enabled: true });
  installBrowser({ permission: 'default' });
  assert.equal(await sync.syncPushSubscription('wc-test'), 'skipped');
  assert.equal(subscribeCalls, 0);
});

test('the background run does nothing offline or off an event page, and never throws', async () => {
  installBrowser({ online: false });
  await push.outbox.add('wc-test', { kind: 'reminder-off', sessionId: 9 });

  await sync.runPushSync();
  assert.deepEqual(apiCalls, [], 'offline: nothing attempted');

  installBrowser({ online: true });
  globalThis.document.getElementById = () => null;
  await sync.runPushSync();
  assert.deepEqual(apiCalls, [], 'no event on the page');

  installBrowser({ online: true });
  await sync.runPushSync();
  assert.ok(apiCalls.some((c) => c.method === 'DELETE'), 'online on an event page: the waiting cancellation went out');
});
