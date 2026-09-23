// Thin fetch wrapper for /api/v1/*. Every call here is either
// public GET or a discovery mutation carrying the caller's own owner
// token as a bearer credential — never a session/cookie, since
// attendees never log in.

export async function apiGet(eventSlug, path) {
  const res = await fetch(`/api/v1/events/${eventSlug}${path}`, {
    headers: { Accept: 'application/json' },
  });

  if (!res.ok) throw new Error(`GET ${path} failed: ${res.status}`);
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
    const error = new Error(`${method} ${path} failed: ${res.status}`);
    error.status = res.status;
    throw error;
  }

  return res.status === 204 ? null : res.json();
}
