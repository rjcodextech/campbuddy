// A small in-memory IndexedDB, just enough for resources/js/attendee/db.js:
// versioned open (upgradeneeded / versionchange / blocked), object stores with
// keyPath or out-of-line keys, one-level indexes, and transactions that finish
// on their own once their requests are done. Events are asynchronous, as in a
// browser. It can also misbehave the ways real browsers do:
//
//   failOpen: n          the next n opens fail (request.onerror)
//   throwOnOpen: true    indexedDB.open() itself throws (storage disabled)
//   quotaOnCommit: true  every readwrite transaction aborts with QuotaExceededError
//                        when it tries to commit — only `abort` fires, never `error`

const later = (fn) => setImmediate(fn);

function domError(name, message = name) {
  const error = new Error(message);
  error.name = name;

  return error;
}

class FakeRequest {
  constructor(transaction = null) {
    this.result = undefined;
    this.error = null;
    this.transaction = transaction;
    this.onsuccess = null;
    this.onerror = null;
  }
}

class FakeStore {
  constructor(data, transaction) {
    this.data = data;
    this.tx = transaction;
  }

  get indexNames() {
    return { contains: (name) => this.data.indexes.has(name) };
  }

  createIndex(name, keyPath) {
    this.data.indexes.set(name, keyPath);
  }

  index(name) {
    const keyPath = this.data.indexes.get(name);

    return {
      getAll: (value) => this.tx.request(() => [...this.data.rows.values()].filter((row) => row?.[keyPath] === value).map((row) => structuredClone(row))),
    };
  }

  get(key) {
    return this.tx.request(() => (this.data.rows.has(key) ? structuredClone(this.data.rows.get(key)) : undefined));
  }

  getAll() {
    return this.tx.request(() => [...this.data.rows.values()].map((row) => structuredClone(row)));
  }

  getAllKeys() {
    return this.tx.request(() => [...this.data.rows.keys()]);
  }

  put(value, key) {
    this.tx.mustWrite();
    const id = this.data.keyPath ? value[this.data.keyPath] : key;

    return this.tx.request(() => {
      this.data.rows.set(id, structuredClone(value));

      return id;
    });
  }

  delete(key) {
    this.tx.mustWrite();

    return this.tx.request(() => {
      this.data.rows.delete(key);
    });
  }

  clear() {
    this.tx.mustWrite();

    return this.tx.request(() => {
      this.data.rows.clear();
    });
  }
}

class FakeTransaction {
  constructor(db, names, mode) {
    this.db = db;
    this.names = names;
    this.mode = mode;
    this.error = null;
    this.oncomplete = null;
    this.onerror = null;
    this.onabort = null;
    this.outstanding = 0;
    this.finished = false;
    // Rows as they were, so an abort can put them back.
    this.snapshot = new Map(names.map((name) => [name, new Map(db.schema.stores.get(name).rows)]));
    later(() => this.maybeFinish());
  }

  mustWrite() {
    if (this.mode !== 'readwrite' && this.mode !== 'versionchange') throw domError('ReadOnlyError');
  }

  objectStore(name) {
    if (!this.names.includes(name)) throw domError('NotFoundError');

    return new FakeStore(this.db.schema.stores.get(name), this);
  }

  request(work) {
    if (this.finished) throw domError('TransactionInactiveError');
    const request = new FakeRequest(this);
    this.outstanding++;
    later(() => {
      try {
        request.result = work();
        request.onsuccess?.({ target: request });
      } catch (error) {
        request.error = error;
        request.onerror?.({ target: request });
        this.abort(error);
      }
      this.outstanding--;
      later(() => this.maybeFinish());
    });

    return request;
  }

  abort(error = domError('AbortError')) {
    if (this.finished) return;
    this.finished = true;
    this.error = error;
    for (const [name, rows] of this.snapshot) this.db.schema.stores.get(name).rows = rows;
    later(() => this.onabort?.({ target: this }));
  }

  maybeFinish() {
    if (this.finished || this.outstanding > 0) return;
    if (this.mode === 'readwrite' && this.db.factory.options.quotaOnCommit) {
      this.abort(domError('QuotaExceededError', 'The quota has been exceeded.'));
      return;
    }
    this.finished = true;
    this.oncomplete?.({ target: this });
  }
}

class FakeDatabase {
  constructor(factory, schema) {
    this.factory = factory;
    this.schema = schema;
    this.name = schema.name;
    this.version = schema.version;
    this.closed = false;
    this.onversionchange = null;
    this.onclose = null;
  }

  get objectStoreNames() {
    const names = [...this.schema.stores.keys()];

    return Object.assign(names, { contains: (name) => this.schema.stores.has(name) });
  }

  createObjectStore(name, { keyPath = null } = {}) {
    const data = { keyPath, rows: new Map(), indexes: new Map() };
    this.schema.stores.set(name, data);

    return new FakeStore(data, this.upgradeTx);
  }

  transaction(names, mode = 'readonly') {
    if (this.closed) throw domError('InvalidStateError', 'The database connection is closing.');
    const list = Array.isArray(names) ? names : [names];
    for (const name of list) if (!this.schema.stores.has(name)) throw domError('NotFoundError');

    return new FakeTransaction(this, list, mode);
  }

  close() {
    this.closed = true;
    this.factory.connections.delete(this);
    this.factory.retryWaiting();
  }
}

export function fakeIndexedDB(options = {}) {
  const factory = {
    options: { failOpen: 0, throwOnOpen: false, quotaOnCommit: false, ...options },
    databases: new Map(),
    connections: new Set(),
    waiting: [],
    opens: 0,

    open(name, version) {
      if (this.options.throwOnOpen) throw domError('SecurityError', 'The operation is insecure.');
      this.opens++;
      const request = new FakeRequest();
      request.onupgradeneeded = null;
      request.onblocked = null;
      later(() => this.tryOpen(name, version, request, false));

      return request;
    },

    tryOpen(name, version, request, alreadyAsked) {
      if (this.options.failOpen > 0) {
        this.options.failOpen--;
        request.error = domError('UnknownError', 'Internal error opening backing store.');
        request.onerror?.({ target: request });
        return;
      }

      const schema = this.databases.get(name) ?? { name, version: 0, stores: new Map() };
      const wanted = version ?? (schema.version || 1);

      if (wanted < schema.version) {
        request.error = domError('VersionError');
        request.onerror?.({ target: request });
        return;
      }

      if (wanted > schema.version) {
        const others = [...this.connections].filter((c) => c.name === name);
        if (!alreadyAsked) for (const other of others) other.onversionchange?.({ target: other, oldVersion: schema.version, newVersion: wanted });
        if ([...this.connections].some((c) => c.name === name)) {
          if (!alreadyAsked) request.onblocked?.({ target: request });
          this.waiting.push(() => this.tryOpen(name, version, request, true));
          return;
        }
        this.databases.set(name, schema);
        const oldVersion = schema.version;
        schema.version = wanted;
        const db = new FakeDatabase(this, schema);
        db.upgradeTx = new FakeTransaction(db, [], 'versionchange');
        request.result = db;
        request.transaction = db.upgradeTx;
        request.onupgradeneeded?.({ target: request, oldVersion, newVersion: wanted });
        db.upgradeTx.names = [...schema.stores.keys()];
      }

      const db = request.result ?? new FakeDatabase(this, schema);
      request.result = db;
      this.connections.add(db);
      request.onsuccess?.({ target: request });
    },

    retryWaiting() {
      const queue = this.waiting.splice(0);
      for (const next of queue) later(next);
    },

    /** A connection opened by "another tab" (which never closes unless told to). */
    async openElsewhere(name, version, { closeOnVersionChange = false } = {}) {
      return new Promise((resolve, reject) => {
        const request = this.open(name, version);
        request.onupgradeneeded = () => {
          const db = request.result;
          for (const store of ['kv']) if (!db.objectStoreNames.contains(store)) db.createObjectStore(store);
        };
        request.onsuccess = () => {
          const db = request.result;
          if (closeOnVersionChange) db.onversionchange = () => db.close();
          resolve(db);
        };
        request.onerror = () => reject(request.error);
      });
    },

    /** Simulates the browser dropping every connection (site data cleared). */
    dropConnections() {
      for (const db of [...this.connections]) {
        db.closed = true;
        this.connections.delete(db);
        db.onclose?.({ target: db });
      }
    },
  };

  return factory;
}
