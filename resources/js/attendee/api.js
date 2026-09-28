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

/** Headers every API call sends. */
export function apiHeaders(extra = {}) {
  return { Accept: 'application/json', 'X-CampBuddy-Device': getDeviceId(), ...extra };
}

/** fetch() that waits and retries when the server says it's busy (429). */
export async function fetchWithRetry(url, options = {}) {
  for (let attempt = 0; ; attempt++) {
    const res = await fetch(url, options);
    if (res.status !== 429 || attempt >= RETRIES_ON_BUSY) return res;

    const retryAfter = Number(res.headers.get('Retry-After'));
    const base = Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter * 1000 : 1500 * (attempt + 1);
    // A little randomness, so a room full of phones doesn't retry in step.
    await new Promise((resolve) => setTimeout(resolve, Math.min(MAX_WAIT_MS, base) + Math.random() * 700));
  }
}

const BUSY_MESSAGE = 'Lots of people are online right now — please try again in a few seconds.';

export async function apiGet(eventSlug, path, ownerToken = null) {
  const headers = apiHeaders();
  if (ownerToken) headers.Authorization = `Bearer ${ownerToken}`;

  const res = await fetchWithRetry(`/api/v1/events/${eventSlug}${path}`, { headers });

  if (!res.ok) {
    reportFailure(path, 'GET', res.status);
    const error = new Error(`GET ${path} failed: ${res.status}`);
    error.status = res.status;
    error.userMessage = res.status === 429 ? BUSY_MESSAGE : null;
    throw error;
  }

  return res.json();
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

  return res.status === 204 ? null : res.json();
}
