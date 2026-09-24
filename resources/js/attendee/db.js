// Local-first storage: IndexedDB for everything beyond
// trivial flags, so state survives reloads/restarts/network loss. This is
// the ONLY place that talks to IndexedDB directly — every other module
// goes through the functions exported here.

const DB_NAME = 'campbuddy';
// 2: 'meetings' (people to meet, with a note) for the day planner.
const DB_VERSION = 2;

/** @type {Promise<IDBDatabase>|null} */
let dbPromise = null;

function openDb() {
  if (dbPromise) return dbPromise;

  dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

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

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });

  return dbPromise;
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

async function withStore(storeName, mode, fn) {
  const db = await openDb();
  const tx = db.transaction(storeName, mode);
  const store = tx.objectStore(storeName);
  const result = await fn(store);
  await promisify2(tx);
  return result;
}

function promisify2(tx) {
  return new Promise((resolve, reject) => {
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
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
 * Wipes every store — the "Clear My CampBuddy Data" action.
 */
export async function clearAll() {
  const db = await openDb();
  const storeNames = Array.from(db.objectStoreNames);
  const tx = db.transaction(storeNames, 'readwrite');
  storeNames.forEach((name) => tx.objectStore(name).clear());
  return promisify2(tx);
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
