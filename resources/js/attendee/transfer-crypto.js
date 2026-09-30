// The sealed envelope for "Move my CampBuddy to this device"
// (device-transfer.js). The two devices agree a key between themselves —
// ECDH on P-256, then HKDF-SHA-256 bound to the request's id — and the data
// travels as AES-GCM ciphertext, so the server that relays it can't read it.
//
// The 6-digit code comes from the new device's public key. The person types
// the code shown on the new device into the old one before it approves:
// that proves the request is theirs, and that the key the old device is
// about to seal to is the one the new device made (a swapped key would give
// a different code).

const CURVE = { name: 'ECDH', namedCurve: 'P-256' };
const INFO = new TextEncoder().encode('campbuddy-device-transfer-v1');

export function toB64url(bytes) {
  const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
  let bin = '';
  for (let i = 0; i < view.length; i += 0x8000) bin += String.fromCharCode(...view.subarray(i, i + 0x8000));
  return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function fromB64url(text) {
  const b64 = text.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((text.length + 3) % 4);
  const bin = atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

/** A fresh key pair; the private half can't be exported, even by this page. */
export async function newKeyPair() {
  const pair = await crypto.subtle.generateKey(CURVE, false, ['deriveBits']);
  const raw = await crypto.subtle.exportKey('raw', pair.publicKey);

  return { privateKey: pair.privateKey, publicKey: toB64url(raw) };
}

/** The 6-digit code both screens show for a public key. */
export async function codeFor(publicKey) {
  const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', fromB64url(publicKey)));
  const n = ((digest[0] << 24) >>> 0) + (digest[1] << 16) + (digest[2] << 8) + digest[3];

  return String(n % 1_000_000).padStart(6, '0');
}

async function sharedKey(privateKey, peerPublicKey, transferId) {
  const peer = await crypto.subtle.importKey('raw', fromB64url(peerPublicKey), CURVE, false, []);
  const bits = await crypto.subtle.deriveBits({ name: 'ECDH', public: peer }, privateKey, 256);
  const hkdf = await crypto.subtle.importKey('raw', bits, 'HKDF', false, ['deriveKey']);

  return crypto.subtle.deriveKey(
    { name: 'HKDF', hash: 'SHA-256', salt: new TextEncoder().encode(transferId), info: INFO },
    hkdf,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt']
  );
}

/** The old device: seals `data` so only the holder of `peerPublicKey`'s private key can open it. */
export async function seal(data, peerPublicKey, transferId) {
  const mine = await newKeyPair();
  const key = await sharedKey(mine.privateKey, peerPublicKey, transferId);
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const plain = new TextEncoder().encode(JSON.stringify(data));
  const sealed = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, plain);

  return { sender_key: mine.publicKey, iv: toB64url(iv), payload: toB64url(sealed) };
}

/** The new device: opens what the old one sealed. Throws if it was altered or isn't for this key. */
export async function open({ sender_key: senderKey, iv, payload }, privateKey, transferId) {
  const key = await sharedKey(privateKey, senderKey, transferId);
  const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: fromB64url(iv) }, key, fromB64url(payload));

  return JSON.parse(new TextDecoder().decode(plain));
}

// ---- What moves --------------------------------------------------------------

// Saved things that belong to this device, not the person: its push
// subscription and notification choices, the not-yet-sent queue, cached
// copies, and its own side of a move in progress.
const DEVICE_ONLY = [/^notificationState$/, /^pushEndpoint:/, /^outbox:/, /^roster:/, /^transferRequest:/, /^transferSent:/];

export const LOCAL_KEYS = ['campbuddy:wave-name'];

/**
 * Everything the person made on this device, from db.js's exportAll() dump
 * (and the few bits kept in localStorage).
 */
export function bundleFrom(dump, local = {}) {
  const kv = Object.fromEntries(Object.entries(dump.kv ?? {}).filter(([key]) => !DEVICE_ONLY.some((re) => re.test(key))));
  const rows = (name) => (Array.isArray(dump[name]) ? dump[name] : []);

  return {
    v: 1,
    kv,
    bookmarks: rows('bookmarks'),
    questProgress: rows('questProgress'),
    metHistory: rows('metHistory'),
    meetings: rows('meetings'),
    local: Object.fromEntries(LOCAL_KEYS.filter((k) => typeof local[k] === 'string').map((k) => [k, local[k]])),
  };
}

/**
 * The bundle as this device should store it. Reminders are switched off: they
 * were set up for the old device's notifications, so they're turned on again
 * here. The profile's new owner token replaces the one that travelled.
 */
export function bundleForThisDevice(bundle, { eventId, discoveryId, ownerToken }) {
  const kv = { ...(bundle.kv ?? {}) };
  const key = `discovery:${eventId}`;

  if (kv[key] && kv[key].discoveryId === discoveryId) {
    kv[key] = { ...kv[key], ownerToken };
  }

  return {
    ...bundle,
    kv,
    bookmarks: (bundle.bookmarks ?? []).map((b) => ({ ...b, reminderEnabled: false })),
  };
}
