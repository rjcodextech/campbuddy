// "Move my CampBuddy to this device" (DeviceTransferController).
//
// New device: picking your own name that's already linked (on your laptop,
// say) offers to ask that device for everything — Camp Card, discovery
// profile, saved sessions, quests, people to meet. It shows a 6-digit code
// and waits. The request (with its private key, which can't be exported) is
// kept on the device, so a reload or a closed tab picks up where it was.
//
// Old device (holds the profile): while the app is open it checks every half
// minute whether another device is asking. The person types the code from
// the new device, the data is sealed to the new device's key
// (transfer-crypto.js) and, once the new device has it, cleared from here —
// even if the popup was closed meanwhile (the next check finishes the job).

import { apiGet, apiMutate, apiHeaders, fetchWithRetry } from './api.js';
import { track } from './analytics.js';
import { clearAll, exportAll, kvDelete, kvGet, kvSet, putRows } from './db.js';
import { LOCAL_KEYS, bundleForThisDevice, bundleFrom, codeFor, newKeyPair, open, seal } from './transfer-crypto.js';
import { render } from './template.js';

const WATCH_MS = 30_000;
const WAIT_MS = 3_000;
const STORES = ['bookmarks', 'questProgress', 'metHistory', 'meetings'];
const REQUEST_KEY = (eventId) => `transferRequest:${eventId}`;
const SENT_KEY = (eventId) => `transferSent:${eventId}`;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const formatCode = (code) => `${code.slice(0, 3)} ${code.slice(3)}`;

/** GET with a secret that isn't an owner token — null means expired, replaced or gone. */
async function getWithSecret(eventSlug, path, secret) {
  const res = await fetchWithRetry(`/api/v1/events/${eventSlug}${path}`, { headers: apiHeaders({ Authorization: `Bearer ${secret}` }), cache: 'no-store' });
  if (res.status === 404) return null;
  if (!res.ok) throw Object.assign(new Error(`GET ${path} failed: ${res.status}`), { status: res.status });
  return res.json();
}

function sheet(id, slots) {
  const dialog = render(id, slots);
  const close = () => {
    dialog.close();
    dialog.remove();
  };
  document.body.appendChild(dialog);
  dialog.showModal();
  return { dialog, close };
}

function show(dialog, step) {
  dialog.querySelectorAll('[data-step]').forEach((el) => { el.hidden = el.dataset.step !== step; });
}

// ---- New device --------------------------------------------------------------

/**
 * Opened when the name picked in "Pick my name" is already linked elsewhere.
 * @param {{ eventSlug: string, eventId: number, entry: { id: number, name: string, gravatar_url?: string } }} opts
 */
export function openTransferRequest({ eventSlug, eventId, entry }) {
  if (document.querySelector('dialog[data-transfer-request][open]')) return;

  const ui = requestSheet(entry);
  const { dialog } = ui;
  const errorEl = dialog.querySelector('[data-transfer-error]');

  dialog.querySelector('[data-transfer-start]').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    errorEl.hidden = true;

    let request;
    try {
      const keys = await newKeyPair();
      const made = await apiMutate(eventSlug, '/device-transfers', 'POST', { attendee_roster_id: entry.id, public_key: keys.publicKey });
      request = {
        transferId: made.transfer_id,
        secret: made.secret,
        privateKey: keys.privateKey,
        code: await codeFor(keys.publicKey),
        entry: { id: entry.id, name: entry.name ?? '', gravatar_url: entry.gravatar_url ?? null },
        expiresAt: Date.parse(made.expires_at),
      };
      await kvSet(REQUEST_KEY(eventId), request).catch(() => {});
      track('device_transfer_request');
    } catch (error) {
      btn.disabled = false;
      errorEl.textContent = error.userMessage ?? (window.isSecureContext ? "Couldn't send the request. Check your connection and try again." : 'This needs a secure (https) connection.');
      errorEl.hidden = false;
      return;
    }

    waitForAnswer({ eventSlug, eventId, request, ui });
  });
}

function requestSheet(entry) {
  const { dialog, close } = sheet('tpl-transfer-request', {
    name: entry.name ?? '',
    avatar: { attrs: { src: entry.gravatar_url || '/media/illustrations/avatar.svg' } },
  });
  dialog.dataset.transferRequest = '';

  const ui = { dialog, close, onStop: () => {} };
  const stop = () => {
    ui.stopped = true;
    ui.onStop();
    close();
  };
  dialog.querySelectorAll('[data-action="close"]').forEach((b) => b.addEventListener('click', stop));
  dialog.addEventListener('cancel', (e) => {
    e.preventDefault();
    stop();
  });

  return ui;
}

/** Shows the code and waits for the old device; brings the data in when it's approved. */
async function waitForAnswer({ eventSlug, eventId, request, ui }) {
  const { dialog } = ui;
  const forget = () => kvDelete(REQUEST_KEY(eventId)).catch(() => {});
  let finished = false;

  ui.onStop = () => {
    if (finished) return;
    forget();
    apiMutate(eventSlug, `/device-transfers/${request.transferId}`, 'DELETE', null, request.secret).catch(() => {});
  };

  dialog.querySelector('[data-transfer-code]').textContent = formatCode(request.code);
  show(dialog, 'code');

  const end = (step) => {
    finished = true;
    forget();
    show(dialog, step);
  };

  while (!ui.stopped) {
    if (Date.now() > request.expiresAt + 5_000) return end('expired');

    let state;
    try {
      state = await getWithSecret(eventSlug, `/device-transfers/${request.transferId}`, request.secret);
    } catch {
      await sleep(WAIT_MS);
      continue; // a network blip: keep waiting
    }
    if (ui.stopped) return;

    if (!state || state.status === 'cancelled') return end('expired');
    if (state.status === 'declined') return end('declined');
    if (state.status === 'completed') return end('done'); // finished before a reload
    if (state.status !== 'approved') {
      await sleep(WAIT_MS);
      continue;
    }

    show(dialog, 'moving');
    try {
      // Saved here first — only then is the old device told to let go of it.
      const bundle = await open(state, request.privateKey, request.transferId);
      await importBundle(bundleForThisDevice(bundle, { eventId, discoveryId: null, ownerToken: null }));
      const done = await apiMutate(eventSlug, `/device-transfers/${request.transferId}/complete`, 'POST', null, request.secret);
      const mine = await kvGet(`discovery:${eventId}`);
      if (mine?.discoveryId === done.discovery_id) await kvSet(`discovery:${eventId}`, { ...mine, ownerToken: done.owner_token });
      track('device_transfer_done');
      end('done');
    } catch {
      end('failed');
    }
    return;
  }
}

async function importBundle(bundle) {
  for (const [key, value] of Object.entries(bundle.kv ?? {})) await kvSet(key, value);
  for (const name of STORES) {
    if (Array.isArray(bundle[name]) && bundle[name].length) await putRows(name, bundle[name]);
  }
  for (const [key, value] of Object.entries(bundle.local ?? {})) {
    try {
      localStorage.setItem(key, value);
    } catch {
      // Private mode: only the remembered wave name is lost.
    }
  }
}

/** A request still waiting from before a reload: show it again and keep waiting. */
async function resumeRequest(eventSlug, eventId) {
  const request = await kvGet(REQUEST_KEY(eventId)).catch(() => null);
  if (!request) return;
  if (!request.transferId || !request.privateKey || Date.now() > request.expiresAt) {
    await kvDelete(REQUEST_KEY(eventId)).catch(() => {});
    return;
  }
  if (document.querySelector('dialog[data-transfer-request][open]')) return;

  waitForAnswer({ eventSlug, eventId, request, ui: requestSheet(request.entry ?? {}) });
}

// ---- Old device ------------------------------------------------------------------

/** On every event page: pick up a waiting request of this device's own, and listen for other devices asking. */
export async function initDeviceTransferWatch(root) {
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  if (!eventId || !eventSlug || !window.crypto?.subtle) return;

  resumeRequest(eventSlug, eventId).catch(() => {});

  let busy = false;
  const seen = new Set();

  const check = async () => {
    if (busy || document.hidden) return;
    busy = true;
    try {
      if (await finishSent(eventSlug, eventId)) return;

      const mine = await kvGet(`discovery:${eventId}`).catch(() => null);
      if (!mine?.discoveryId || !mine?.ownerToken) return;

      let found;
      try {
        found = (await apiGet(eventSlug, `/discovery/${mine.discoveryId}/transfer`, mine.ownerToken))?.transfer;
      } catch {
        return;
      }
      if (!found || seen.has(found.transfer_id)) return;

      seen.add(found.transfer_id);
      await askToApprove({ eventSlug, eventId, mine, transfer: found });
    } finally {
      busy = false;
    }
  };

  check();
  setInterval(check, WATCH_MS);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) check();
  });
}

/**
 * This device approved a move earlier and the popup was closed before it
 * finished: if the new device has the data now, clear it here. True if so.
 */
async function finishSent(eventSlug, eventId) {
  const sent = await kvGet(SENT_KEY(eventId)).catch(() => null);
  if (!sent) return false;

  let state;
  try {
    state = await getWithSecret(eventSlug, `/device-transfers/${sent.transferId}/sender`, sent.secret);
  } catch {
    return false;
  }

  if (state?.status === 'completed') {
    await wipeThisDevice();
    const { dialog } = sheet('tpl-transfer-approve', { device: null });
    show(dialog, 'moved');
    dialog.querySelector('[data-transfer-reload]').addEventListener('click', () => window.location.reload());
    return true;
  }
  if (!state || state.status !== 'approved') await kvDelete(SENT_KEY(eventId)).catch(() => {});
  return false;
}

async function wipeThisDevice() {
  await clearAll().catch(() => {});
  LOCAL_KEYS.forEach((k) => {
    try {
      localStorage.removeItem(k);
    } catch {
      // nothing kept there
    }
  });
}

function askToApprove({ eventSlug, eventId, mine, transfer }) {
  // The laptop's "best on your phone" notice would sit behind this one — the person is busy moving to their phone anyway.
  const notice = document.getElementById('desktop-notice');
  if (notice?.open) notice.close();

  return new Promise((resolve) => {
    const { dialog, close } = sheet('tpl-transfer-approve', {
      device: transfer.requester_label ? `Asked from ${transfer.requester_label}` : 'Asked from another device',
    });
    const input = dialog.querySelector('[data-transfer-input]');
    const errorEl = dialog.querySelector('[data-transfer-error]');
    const fail = (message) => {
      errorEl.textContent = message;
      errorEl.hidden = !message;
    };
    let waiting = true;
    const finish = () => {
      waiting = false;
      close();
      resolve();
    };

    dialog.addEventListener('cancel', (e) => e.preventDefault());
    dialog.querySelectorAll('[data-action="close"]').forEach((b) => b.addEventListener('click', finish));

    dialog.querySelector('[data-transfer-decline]').addEventListener('click', async () => {
      await apiMutate(eventSlug, `/discovery/${mine.discoveryId}/transfer/${transfer.transfer_id}/decline`, 'POST', null, mine.ownerToken).catch(() => {});
      track('device_transfer_decline');
      finish();
    });

    dialog.querySelector('[data-transfer-form]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = dialog.querySelector('[data-transfer-approve]');
      const typed = input.value.replace(/\D/g, '');

      if (typed !== (await codeFor(transfer.requester_key))) {
        fail("That code doesn't match. Type the 6 digits shown on your new device — if you didn't ask, tap Not me.");
        input.focus();
        return;
      }

      fail('');
      btn.disabled = true;
      let senderSecret;
      try {
        const dump = await exportAll();
        const local = Object.fromEntries(LOCAL_KEYS.map((k) => [k, safeLocalGet(k)]));
        const sealed = await seal(bundleFrom(dump, local), transfer.requester_key, transfer.transfer_id);
        senderSecret = (await apiMutate(eventSlug, `/discovery/${mine.discoveryId}/transfer/${transfer.transfer_id}/approve`, 'POST', sealed, mine.ownerToken)).secret;
        await kvSet(SENT_KEY(eventId), { transferId: transfer.transfer_id, secret: senderSecret }).catch(() => {});
        track('device_transfer_approve');
      } catch (error) {
        btn.disabled = false;
        fail(error.userMessage ?? "Couldn't send. Check your connection and try again.");
        return;
      }

      show(dialog, 'sending');

      // Wait until the new device has it, then clear this one.
      const until = Date.now() + 10 * 60_000;
      while (waiting && Date.now() < until) {
        await sleep(WAIT_MS);
        let state;
        try {
          state = await getWithSecret(eventSlug, `/device-transfers/${transfer.transfer_id}/sender`, senderSecret);
        } catch {
          continue;
        }
        if (state?.status === 'completed') {
          await wipeThisDevice();
          show(dialog, 'moved');
          dialog.querySelector('[data-transfer-reload]').addEventListener('click', () => window.location.reload());
          return;
        }
        if (!state) break; // expired before the new device picked it up
      }

      if (!waiting) return;
      await kvDelete(SENT_KEY(eventId)).catch(() => {});
      show(dialog, 'not-moved');
    });

    input.focus();
  });
}

function safeLocalGet(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}
