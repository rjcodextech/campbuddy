// Local-first storage: IndexedDB for everything beyond
// trivial flags, so state survives reloads/restarts/network loss. This is
// the ONLY place that talks to IndexedDB directly — every other module
// goes through the functions exported here.

const DB_NAME = 'campbuddy';
const DB_VERSION = 1;

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

export async function setBookmark(eventId, sessionId, reminderEnabled) {
  const key = `${eventId}:${sessionId}`;
  return withStore('bookmarks', 'readwrite', (store) =>
    promisify(store.put({ key, eventId, sessionId, reminderEnabled, savedAt: Date.now() }))
  );
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
