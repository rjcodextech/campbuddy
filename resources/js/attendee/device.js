// A random, anonymous id for this browser — nothing about the person. It lets
// the server count requests per phone instead of per internet connection, so
// a whole venue sharing one wifi (or a mobile network sharing one address)
// isn't throttled together. Also what reminders are tied to (push.js).

const KEY = 'campbuddy-device-id';
let memoryId = null;

export function getDeviceId() {
  try {
    let id = localStorage.getItem(KEY);
    if (!id) {
      id = newId();
      localStorage.setItem(KEY, id);
    }
    return id;
  } catch {
    // Storage blocked (private mode): stable for this page at least.
    memoryId ??= newId();
    return memoryId;
  }
}

function newId() {
  if (crypto.randomUUID) return crypto.randomUUID();
  // Older browsers: the same shape from random bytes.
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}
