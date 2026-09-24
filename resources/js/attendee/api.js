// Thin fetch wrapper for /api/v1/*. Every call here is either
// public GET or a discovery mutation carrying the caller's own owner
// token as a bearer credential — never a session/cookie, since
// attendees never log in.

import { track } from './analytics.js';

// Only the first path segment is reported ("discovery", not
// "discovery/<id>") — the rest can carry IDs that must stay out of analytics.
function reportFailure(path, method, status) {
  track('api_error', { endpoint: path.split('?')[0].split('/')[1], method, status });
}

export async function apiGet(eventSlug, path, ownerToken = null) {
  const headers = { Accept: 'application/json' };
  if (ownerToken) headers.Authorization = `Bearer ${ownerToken}`;

  const res = await fetch(`/api/v1/events/${eventSlug}${path}`, { headers });

  if (!res.ok) {
    reportFailure(path, 'GET', res.status);
    throw new Error(`GET ${path} failed: ${res.status}`);
  }

  return res.json();
}

export async function apiMutate(eventSlug, path, method, body, ownerToken) {
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
  if (ownerToken) headers.Authorization = `Bearer ${ownerToken}`;

  const res = await fetch(`/api/v1/events/${eventSlug}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!res.ok) {
    reportFailure(path, method, res.status);
    const error = new Error(`${method} ${path} failed: ${res.status}`);
    error.status = res.status;
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
