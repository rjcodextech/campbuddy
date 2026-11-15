// Local-first storage: IndexedDB for everything beyond
// trivial flags, so state survives reloads/restarts/network loss. This is
// the ONLY place that talks to IndexedDB directly — every other module
// goes through the functions exported here.

const DB_NAME = 'campbuddy';
// 2: 'meetings' (people to meet, with a note) for the day planner.
const DB_VERSION = 2;

/** @type {Promise<IDBDatabase>|null} */
let dbPromise = null;

// How long to wait for another open tab of an older version to let go of the
// database before carrying on without it (the screens still draw; saving waits
// for the next try).
const BLOCKED_WAIT_MS = 3000;

function openDb() {
  if (dbPromise) return dbPromise;

  const opening = new Promise((resolve, reject) => {
    let settled = false;
    let blockedTimer = null;
    const finish = (fn, value) => {
      if (settled) return;
      settled = true;
      clearTimeout(blockedTimer);
      fn(value);
    };

    let request;
    try {
      // Storage switched off (blocked site data, some private modes): no IndexedDB, or open() throws.
      request = indexedDB.open(DB_NAME, DB_VERSION);
    } catch (error) {
      finish(reject, error);
      return;
    }

    request.onupgradeneeded = () => {
      const db = request.result;

      // Single-record JSON blobs keyed by name: 'onboarding', 'campCard',
      // 'discovery:{eventId}', 'settings'.
      if (!db.objectStoreNames.contains('kv')) {
        db.createObjectStore('kv');
      }

      if (!db.objectStoreNames.contains('bookmarks')) {
        const store = db.createObjectStore('bookmarks', { keyPath: 'key' });
        store.createIndex('eventId', 'eventId');
      }

      if (!db.objectStoreNames.contains('questProgress')) {
        const store = db.createObjectStore('questProgress', { keyPath: 'key' });
        store.createIndex('eventId', 'eventId');
      }

      if (!db.objectStoreNames.contains('metHistory')) {
        const store = db.createObjectStore('metHistory', { keyPath: 'discoveryId' });
        store.createIndex('eventId', 'eventId');
      }

      // People this attendee wants to meet, with their own note: one row per
      // (eventId, personKey). Never leaves the device.
      if (!db.objectStoreNames.contains('meetings')) {
        const store = db.createObjectStore('meetings', { keyPath: 'key' });
        store.createIndex('eventId', 'eventId');
      }
    };

    request.onsuccess = () => {
      const db = request.result;

      // We stopped waiting (blocked too long): let this late connection go; the next call opens afresh.
      if (settled) {
        db.close();
        return;
      }

      // A newer version of the app opened in another tab needs the database:
      // let go at once, or that tab would wait for this one to be closed.
      db.onversionchange = () => {
        db.close();
        if (dbPromise === opening) dbPromise = null;
      };
      // The browser closed it (site data cleared): the next call opens it again.
      db.onclose = () => {
        if (dbPromise === opening) dbPromise = null;
      };
      finish(resolve, db);
    };
    request.onerror = () => finish(reject, request.error);
    // An older tab still holds the database and doesn't let go: don't wait for ever.
    request.onblocked = () => {
      blockedTimer = setTimeout(() => finish(reject, Object.assign(new Error('The database is held by another tab.'), { name: 'BlockedError' })), BLOCKED_WAIT_MS);
    };
  });

  dbPromise = opening;
  // A failed open is not remembered: the next call tries again.
  opening.catch(() => {
    if (dbPromise === opening) dbPromise = null;
  });

  return opening;
}

/**
 * Wraps an IDBRequest in a Promise — the one bit of boilerplate every
 * call below needs.
 */
function promisify(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function withStore(storeName, mode, fn, retried = false) {
  const db = await openDb();
  let tx;

  try {
    tx = db.transaction(storeName, mode);
  } catch (error) {
    // The connection was closed under us (site data cleared, a newer tab took over): open again, once.
    if (!retried && error?.name === 'InvalidStateError') {
      dbPromise = null;
      return withStore(storeName, mode, fn, true);
    }
    throw error;
  }

  // Listening before any request runs, so an abort at any point is heard.
  const done = transactionDone(tx);
  done.catch(() => {});
  const result = await fn(tx.objectStore(storeName));
  await done;
  return result;
}

/** Settles when the transaction is over: committed, failed, or aborted (a full disk aborts at commit, firing only `abort`). */
function transactionDone(tx) {
  return new Promise((resolve, reject) => {
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error ?? Object.assign(new Error('The save was cancelled.'), { name: 'AbortError' }));
  });
}

// --- kv: single-record blobs (onboarding profile, camp card, settings) ---

export async function kvGet(key) {
  return withStore('kv', 'readonly', (store) => promisify(store.get(key)));
}

export async function kvSet(key, value) {
  return withStore('kv', 'readwrite', (store) => promisify(store.put(value, key)));
}

export async function kvDelete(key) {
  return withStore('kv', 'readwrite', (store) => promisify(store.delete(key)));
}

// --- bookmarks: one row per (eventId, sessionId) ---

export async function getBookmarks(eventId) {
  return withStore('bookmarks', 'readonly', async (store) => {
    const index = store.index('eventId');
    return promisify(index.getAll(eventId));
  });
}

// `meta` (title, startMs, endMs) lets screens without the schedule — the
// "things left today" reminder — know when a saved session is over.
export async function setBookmark(eventId, sessionId, reminderEnabled, meta = {}) {
  const key = `${eventId}:${sessionId}`;
  return withStore('bookmarks', 'readwrite', (store) =>
    promisify(store.put({ key, eventId, sessionId, reminderEnabled, savedAt: Date.now(), status: null, ...meta }))
  );
}

/** Merges fields into a saved session (status 'attended' | 'missed' | null, or meta). */
export async function updateBookmark(eventId, sessionId, changes) {
  const key = `${eventId}:${sessionId}`;
  return withStore('bookmarks', 'readwrite', async (store) => {
    const row = await promisify(store.get(key));
    if (!row) return null;
    const next = { ...row, ...changes };
    await promisify(store.put(next));
    return next;
  });
}

export async function removeBookmark(eventId, sessionId) {
  const key = `${eventId}:${sessionId}`;
  return withStore('bookmarks', 'readwrite', (store) => promisify(store.delete(key)));
}

// --- quest progress: one row per (eventId, questId) ---

export async function getQuestProgress(eventId) {
  return withStore('questProgress', 'readonly', async (store) => {
    const index = store.index('eventId');
    return promisify(index.getAll(eventId));
  });
}

export async function setQuestComplete(eventId, questId, complete) {
  const key = `${eventId}:${questId}`;

  if (!complete) {
    return withStore('questProgress', 'readwrite', (store) => promisify(store.delete(key)));
  }

  return withStore('questProgress', 'readwrite', (store) =>
    promisify(store.put({ key, eventId, questId, completedAt: Date.now() }))
  );
}

// --- met history: people this device has marked "I met them" ---

export async function getMetHistory(eventId) {
  return withStore('metHistory', 'readonly', async (store) => {
    const index = store.index('eventId');
    return promisify(index.getAll(eventId));
  });
}

export async function markMet(eventId, discoveryId) {
  return withStore('metHistory', 'readwrite', (store) =>
    promisify(store.put({ discoveryId, eventId, metAt: Date.now() }))
  );
}

// --- meetings: people to meet, each with a note and an optional time ---
//
// { key, eventId, personKey, name, avatarUrl, source: 'roster'|'discovery'|'match',
//   links, note, at (ISO string or null = any time), status: null|'met'|'missed',
//   createdAt, updatedAt }

export async function getMeetings(eventId) {
  return withStore('meetings', 'readonly', async (store) => {
    const index = store.index('eventId');
    return promisify(index.getAll(eventId));
  });
}

export async function saveMeeting(eventId, personKey, fields) {
  const key = `${eventId}:${personKey}`;
  return withStore('meetings', 'readwrite', async (store) => {
    const existing = await promisify(store.get(key));
    const row = {
      status: null,
      createdAt: Date.now(),
      ...existing,
      ...fields,
      key,
      eventId,
      personKey,
      updatedAt: Date.now(),
    };
    await promisify(store.put(row));
    return row;
  });
}

export async function removeMeeting(eventId, personKey) {
  return withStore('meetings', 'readwrite', (store) => promisify(store.delete(`${eventId}:${personKey}`)));
}

/**
 * Writes rows into one of the keyed stores as they are (each row carries its
 * own key) — used to bring in data moved from another device (device-transfer.js).
 */
export async function putRows(storeName, rows) {
  return withStore(storeName, 'readwrite', async (store) => {
    for (const row of rows) await promisify(store.put(row));
  });
}

/**
 * Erases everything this phone holds for a specific WordCamp:
 * saved sessions (bookmarks), quest progress, meetings (notes on people to meet),
 * met history, and event-scoped discovery and camp-card-share entries from kv.
 * Leaves the global onboarding profile and Camp Card intact.
 */
export async function eraseEventData(eventId) {
  const numId = Number(eventId);
  const strId = String(eventId);
  const stores = ['bookmarks', 'questProgress', 'meetings', 'metHistory'];

  for (const name of stores) {
    await withStore(name, 'readwrite', async (store) => {
      const allRows = await promisify(store.getAll());
      for (const row of allRows) {
        if (Number(row.eventId) === numId || String(row.eventId) === strId) {
          const key = row.key ?? row.discoveryId;
          if (key !== undefined) {
            store.delete(key);
          }
        }
      }
    });
  }

  await kvDelete(`discovery:${numId}`).catch(() => {});
  await kvDelete(`discovery:${strId}`).catch(() => {});
  await kvDelete(`campCardShare:${numId}`).catch(() => {});
  await kvDelete(`campCardShare:${strId}`).catch(() => {});
}

/**
 * Wipes every store — the "Clear My CampBuddy Data" action.
 */
export async function clearAll() {
  const db = await openDb();
  const storeNames = Array.from(db.objectStoreNames);
  const tx = db.transaction(storeNames, 'readwrite');
  const done = transactionDone(tx);
  storeNames.forEach((name) => tx.objectStore(name).clear());
  return done;
}

/**
 * Dumps every store's contents — the "Export My CampBuddy Data" action.
 */
export async function exportAll() {
  const db = await openDb();
  const storeNames = Array.from(db.objectStoreNames);
  const dump = {};

  for (const name of storeNames) {
    dump[name] = await withStore(name, 'readonly', (store) => promisify(store.getAll()));
  }

  // kv's getAll() loses keys, so it needs its own key-aware pass.
  dump.kv = await withStore('kv', 'readonly', async (store) => {
    const keys = await promisify(store.getAllKeys());
    const values = await promisify(store.getAll());
    return Object.fromEntries(keys.map((k, i) => [k, values[i]]));
  });

  return dump;
}
