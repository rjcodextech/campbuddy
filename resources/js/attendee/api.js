// Thin fetch wrapper for /api/v1/*. Every call here is either
// public GET or a discovery mutation carrying the caller's own owner
// token as a bearer credential — never a session/cookie, since
// attendees never log in.
//
// Every call names this phone (an anonymous device id, device.js), so the
// server's rate limits count per phone, not per internet connection. And a
// "busy, slow down" answer (429) at a packed moment is retried quietly a
// couple of times — honouring the server's Retry-After — before anything
// is shown to the person.

import { track } from './analytics.js';
import { getDeviceId } from './device.js';

const RETRIES_ON_BUSY = 2;
const MAX_WAIT_MS = 6000;

// Only the first path segment is reported ("discovery", not
// "discovery/<id>") — the rest can carry IDs that must stay out of analytics.
function reportFailure(path, method, status) {
  track('api_error', { endpoint: path.split('?')[0].split('/')[1], method, status });
}

// The handshake (VerifyApiHandshake on the server): every call says it is the
// PWA, which phone, and when. A phone whose clock is wrong gets the server's
// time back once (401 + X-CampBuddy-Handshake-Error: time-skew); from then on
// this page's calls carry the corrected time.
let clockOffsetSeconds = 0;
const TIMESTAMP = 'X-CampBuddy-Timestamp';

function handshakeTime() {
  return String(Math.floor(Date.now() / 1000) + clockOffsetSeconds);
}

/** Headers every API call sends. */
export function apiHeaders(extra = {}) {
  return {
    Accept: 'application/json',
    'X-CampBuddy-Device': getDeviceId(),
    'X-CampBuddy-Client': 'pwa',
    [TIMESTAMP]: handshakeTime(),
    ...extra,
  };
}

/** The server said this phone's clock is off: learn its time, and put it on the request for the one retry. */
function correctedClock(res, options) {
  const server = Number(res.headers.get('X-CampBuddy-Server-Time'));
  if (!Number.isFinite(server) || server <= 0) return null;

  clockOffsetSeconds = server - Math.floor(Date.now() / 1000);
  const headers = options.headers instanceof Headers ? Object.fromEntries(options.headers.entries()) : { ...(options.headers ?? {}) };
  for (const name of Object.keys(headers)) if (name.toLowerCase() === TIMESTAMP.toLowerCase()) delete headers[name];

  return { ...options, headers: { ...headers, [TIMESTAMP]: handshakeTime() } };
}

/** Whether a failed answer is the handshake asking for the server's time (never a final "no"). */
export function isClockSkew(res) {
  return res?.status === 401 && res.headers?.get('X-CampBuddy-Handshake-Error') === 'time-skew';
}

/** fetch() that waits and retries when the server says it's busy (429), and once more with the server's time when the phone's clock is off. */
export async function fetchWithRetry(url, options = {}) {
  let clockRetried = false;
  for (let attempt = 0; ; attempt++) {
    const res = await fetch(url, options);
    if (isClockSkew(res) && !clockRetried) {
      clockRetried = true;
      const retry = correctedClock(res, options);
      if (retry) {
        options = retry;
        attempt--;
        continue;
      }
    }
    if (res.status !== 429 || attempt >= RETRIES_ON_BUSY) return res;

    const retryAfter = Number(res.headers.get('Retry-After'));
    const base = Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter * 1000 : 1500 * (attempt + 1);
    // A little randomness, so a room full of phones doesn't retry in step.
    await new Promise((resolve) => setTimeout(resolve, Math.min(MAX_WAIT_MS, base) + Math.random() * 700));
  }
}

const BUSY_MESSAGE = 'Lots of people are online right now. Please try again in a few seconds.';

/**
 * The answer's JSON — only when it really is the API's JSON. A 200 that is a web
 * page (a venue wifi sign-in page, a proxy's error page) is treated like no
 * connection, so the saved copy is used instead of crashing on it.
 */
async function apiJson(res, path, method) {
  if (!/\bjson\b/i.test(res.headers.get('Content-Type') ?? '')) {
    reportFailure(path, method, 0);
    const error = new Error(`${method} ${path}: the answer was not CampBuddy's`);
    error.status = 0;
    error.userMessage = null;
    throw error;
  }

  return res.json();
}

export async function apiGet(eventSlug, path, ownerToken = null) {
  const headers = apiHeaders();
  if (ownerToken) headers.Authorization = `Bearer ${ownerToken}`;

  // 'no-cache': always ask the server (an unchanged list costs an empty 304).
  // Without it the browser honours the lists' stale-while-revalidate and shows
  // a copy up to a minute old first — someone who just joined wouldn't appear.
  const res = await fetchWithRetry(`/api/v1/events/${eventSlug}${path}`, { headers, cache: 'no-cache' });

  if (!res.ok) {
    reportFailure(path, 'GET', res.status);
    const error = new Error(`GET ${path} failed: ${res.status}`);
    error.status = res.status;
    error.userMessage = res.status === 429 ? BUSY_MESSAGE : null;
    error.transient = isClockSkew(res);
    throw error;
  }

  return apiJson(res, path, 'GET');
}

export async function apiMutate(eventSlug, path, method, body, ownerToken) {
  const headers = apiHeaders({ 'Content-Type': 'application/json' });
  if (ownerToken) headers.Authorization = `Bearer ${ownerToken}`;

  const res = await fetchWithRetry(`/api/v1/events/${eventSlug}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!res.ok) {
    reportFailure(path, method, res.status);
    const error = new Error(`${method} ${path} failed: ${res.status}`);
    error.status = res.status;
    // The clock still disagreed after the retry: try again later, never a final "no".
    error.transient = isClockSkew(res);

    if (res.status === 429) {
      error.userMessage = BUSY_MESSAGE;
      throw error;
    }

    // A validation failure (422) says what to fix — keep the first message
    // so the form can show it instead of a generic "try again".
    try {
      const body = await res.json();
      error.message = Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? error.message;
      error.userMessage = res.status === 422 ? error.message : null;
    } catch {
      error.userMessage = null;
    }
    throw error;
  }

  return res.status === 204 ? null : apiJson(res, path, method);
}
