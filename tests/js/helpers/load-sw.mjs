// Runs the real public/sw.js in a Node sandbox with a fake Cache Storage and a
// scripted fetch, so its behaviour can be tested without a browser.

import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const SW_SOURCE = readFileSync(new URL('../../../public/sw.js', import.meta.url), 'utf8');
export const ORIGIN = 'https://campbuddy.test';

/** Makes a Response report a type/redirected state, and keeps them across clone() as a browser does. */
function mark(response, type, redirected) {
  Object.defineProperty(response, 'type', { value: type, configurable: true });
  Object.defineProperty(response, 'redirected', { value: redirected, configurable: true });

  const realClone = response.clone.bind(response);
  response.clone = () => mark(realClone(), type, redirected);

  return response;
}

/** A Response the service worker will accept as cacheable (a real one is 'basic' when it comes from our own origin). */
export function page(body = '<html>ok</html>', { status = 200, headers = {}, type = 'basic', redirected = false } = {}) {
  return mark(new Response(body, { status, headers: { 'content-type': 'text/html', ...headers } }), type, redirected);
}

/** A Cache Storage holding real Response objects; match() hands out clones, as the real one does. */
export function fakeCacheStorage(initial = {}) {
  const stores = new Map();
  const log = { deleted: [], deletedEntries: [] };

  const urlOf = (request) => (typeof request === 'string' ? request : request.url);
  const make = (name) => {
    if (!stores.has(name)) stores.set(name, new Map());
    const map = stores.get(name);

    return {
      async match(request) { return map.get(urlOf(request))?.clone(); },
      async put(request, response) { map.set(urlOf(request), response); },
      async keys() { return [...map.keys()].map((url) => ({ url })); },
      async delete(request) { log.deletedEntries.push(`${name}:${urlOf(request)}`); return map.delete(urlOf(request)); },
    };
  };

  for (const [name, entries] of Object.entries(initial)) {
    const cache = make(name);
    for (const [url, response] of Object.entries(entries)) stores.get(name).set(url, response);
    void cache;
  }

  return {
    stores,
    log,
    async open(name) { return make(name); },
    async keys() { return [...stores.keys()]; },
    async delete(name) { log.deleted.push(name); return stores.delete(name); },
    async has(name) { return stores.has(name); },
    async match(request) {
      for (const map of stores.values()) {
        const found = map.get(urlOf(request));
        if (found) return found.clone();
      }
      return undefined;
    },
  };
}

/**
 * @param {object} options
 * @param {(url: string, request: object) => Response|Promise<Response>} [options.network] answers fetches; throw to simulate offline
 * @param {ReturnType<typeof fakeCacheStorage>} [options.caches]
 */
export function loadServiceWorker({ network = () => { throw new TypeError('offline'); }, caches = fakeCacheStorage() } = {}) {
  const handlers = {};
  const fetches = [];
  const state = { skipped: false, claimed: false, notifications: [], opened: [] };

  const fetchImpl = async (input, init) => {
    // A worker's relative fetch('/x') is resolved against its own origin.
    const url = new URL(typeof input === 'string' ? input : input.url, ORIGIN).href;
    fetches.push({ url, init });

    return network(url, input);
  };

  const self = {
    location: new URL(ORIGIN),
    addEventListener: (type, fn) => { handlers[type] = fn; },
    skipWaiting: () => { state.skipped = true; },
    clients: {
      claim: async () => { state.claimed = true; },
      matchAll: async () => [],
      openWindow: async (url) => { state.opened.push(url); },
    },
    registration: { showNotification: async (title, options) => { state.notifications.push({ title, options }); } },
  };

  const sandbox = { self, caches, fetch: fetchImpl, Response, Request, URL, Headers, Promise, setTimeout, clearTimeout, console, Date, JSON, Error, TypeError, Object, Array, Number, isNaN };
  vm.createContext(sandbox);
  vm.runInContext(SW_SOURCE, sandbox);

  /** Sends a request through the worker: returns { handled, response, waits } (handled=false when the worker ignores it). */
  async function request(url, { method = 'GET', mode = 'cors' } = {}) {
    const absolute = new URL(url, ORIGIN).href;
    const waits = [];
    let responded;

    handlers.fetch({
      request: { url: absolute, method, mode, headers: new Headers() },
      respondWith: (promise) => { responded = promise; },
      waitUntil: (promise) => { waits.push(promise); },
    });

    if (!responded) return { handled: false, waits };

    try {
      return { handled: true, response: await responded, waits };
    } catch (error) {
      return { handled: true, error, waits };
    }
  }

  async function lifecycle(type) {
    const waits = [];
    handlers[type]({ waitUntil: (promise) => waits.push(promise) });
    await Promise.all(waits);
  }

  return { handlers, fetches, state, caches, request, lifecycle, navigate: (url) => request(url, { mode: 'navigate' }) };
}

/** Lets pending promise callbacks run. */
export async function settle() {
  for (let i = 0; i < 30; i++) await Promise.resolve();
  await new Promise((resolve) => setImmediate(resolve));
  for (let i = 0; i < 30; i++) await Promise.resolve();
}
