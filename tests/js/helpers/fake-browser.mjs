// A just-enough stand-in for the browser, so the attendee app's own modules can
// be exercised in Node (node --test tests/js) without a real page: a document
// and window that record listeners, storage, a scripted fetch, a fake Cache
// Storage, and a location whose reload() is a spy.
//
// Import a module fresh for each test (`await freshImport(path)`), since the
// modules keep state at file level.

let counter = 0;

/** The module again, evaluated anew (its own imports stay shared and stateless). */
export function freshImport(url) {
  return import(`${url}?fresh=${++counter}`);
}

/** A Response-like object for the scripted fetch. */
export function reply(body, { status = 200, headers = {} } = {}) {
  const lower = Object.fromEntries(Object.entries(headers).map(([k, v]) => [k.toLowerCase(), v]));

  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: (name) => lower[name.toLowerCase()] ?? null },
    json: async () => (typeof body === 'string' ? JSON.parse(body) : body),
    text: async () => (typeof body === 'string' ? body : JSON.stringify(body)),
    clone() { return reply(body, { status, headers }); },
    body,
    type: 'basic',
    redirected: false,
    url: '',
  };
}

/** An in-memory Cache Storage (the parts the app uses). */
export function fakeCaches(initial = {}) {
  const stores = new Map(Object.entries(initial).map(([name, entries]) => [name, new Map(Object.entries(entries))]));
  const deleted = [];

  const cacheFor = (name) => {
    if (!stores.has(name)) stores.set(name, new Map());
    const map = stores.get(name);

    return {
      async keys() { return [...map.keys()].map((url) => ({ url })); },
      async match(request) { return map.get(typeof request === 'string' ? request : request.url); },
      async put(request, response) { map.set(typeof request === 'string' ? request : request.url, response); },
      async add(request) { map.set(typeof request === 'string' ? request : request.url, reply('added')); },
      async delete(request) { deleted.push(typeof request === 'string' ? request : request.url); return map.delete(typeof request === 'string' ? request : request.url); },
    };
  };

  return {
    stores,
    deleted,
    async keys() { return [...stores.keys()]; },
    async open(name) { return cacheFor(name); },
    async has(name) { return stores.has(name); },
    async delete(name) { deleted.push(`cache:${name}`); return stores.delete(name); },
    async match(request) {
      const url = typeof request === 'string' ? request : request.url;
      for (const map of stores.values()) if (map.has(url)) return map.get(url);
      return undefined;
    },
  };
}

/**
 * Installs the fakes as globals and returns handles to drive and inspect them.
 * Call `restore()` when the test is done.
 */
export function installFakeBrowser({
  slug = 'wc-test',
  version = 'v1',
  start = '2026-10-10',
  end = '2026-10-11',
  timezone = 'UTC',
  visible = true,
  online = true,
  origin = 'https://campbuddy.test',
  caches = fakeCaches(),
} = {}) {
  const saved = {};
  const set = (name, value) => {
    saved[name] = Object.getOwnPropertyDescriptor(globalThis, name);
    Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });
  };

  const listeners = { document: {}, window: {} };
  const add = (bucket) => (type, fn) => { (listeners[bucket][type] ??= []).push(fn); };
  const fire = (bucket, type, ...args) => (listeners[bucket][type] ?? []).forEach((fn) => fn(...args));

  const meta = { content: version };
  const app = { dataset: { eventSlug: slug, eventStart: start, eventEnd: end, eventTimezone: timezone } };
  const body = { children: [], appendChild(el) { this.children.push(el); } };
  const doc = {
    visibilityState: visible ? 'visible' : 'hidden',
    activeElement: null,
    body,
    getElementById: (id) => (id === 'app' ? app : body.children.find((c) => c.id === id) ?? null),
    querySelector: (sel) => (sel.includes('campbuddy-data-version') ? meta : null),
    createElement: (tag) => ({ tag, addEventListener() {}, remove() {} }),
    addEventListener: add('document'),
  };
  const store = new Map();
  const storage = { getItem: (k) => (store.has(k) ? store.get(k) : null), setItem: (k, v) => store.set(k, String(v)), removeItem: (k) => store.delete(k) };
  const location = { pathname: `/event/${slug}`, origin, reloads: 0, reload() { this.reloads++; } };

  const win = { addEventListener: add('window'), scrollY: 0, scrollTo() {}, sessionStorage: storage, localStorage: storage, location };
  const fetches = [];
  const responses = [];
  const fetchSpy = async (url, options = {}) => {
    fetches.push({ url: String(url), options });
    const next = responses.length > 1 ? responses.shift() : responses[0];
    if (typeof next === 'function') return next(String(url), options);
    if (next instanceof Error) throw next;
    return next ?? reply({ version });
  };

  set('document', doc);
  set('window', win);
  set('navigator', { onLine: online, connection: undefined });
  set('sessionStorage', storage);
  set('localStorage', storage);
  set('location', location);
  set('caches', caches);
  set('fetch', fetchSpy);
  Object.defineProperty(win, 'caches', { value: caches });
  win.fetch = fetchSpy;

  return {
    doc, win, meta, app, store, location, caches, fetches, responses,
    fire: (type, ...args) => fire('window', type, ...args),
    fireDocument: (type, ...args) => fire('document', type, ...args),
    setVisible(v) { doc.visibilityState = v ? 'visible' : 'hidden'; },
    setOnline(v) { globalThis.navigator.onLine = v; },
    restore() {
      for (const [name, descriptor] of Object.entries(saved)) {
        if (descriptor) Object.defineProperty(globalThis, name, descriptor);
        else delete globalThis[name];
      }
    },
  };
}

/** Lets pending promise callbacks run (fetch replies, awaited cache calls). */
export async function flush(times = 10) {
  for (let i = 0; i < times; i++) await Promise.resolve();
  await new Promise((resolve) => setImmediate(resolve));
  for (let i = 0; i < times; i++) await Promise.resolve();
}
