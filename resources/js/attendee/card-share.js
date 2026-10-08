// "Show my Camp Card on the attendee list" (Camp Card page). Off until the
// attendee turns it on. Then — and only then — the fields that are ON their
// card (role, company, city, interests, "ask me about", each only if chosen
// under "Show on my card") and the link their QR opens go to the server,
// next to their own name on the event's attendee list
// (SharedCampCardController). Everything else stays on the phone (CC5).
//
// Like discovery: the server returns an owner token once, kept here, and only
// it can change or remove the card. A later Save updates the shared copy;
// turning the switch off removes it.

import { apiGet, apiMutate } from './api.js';
import { track } from './analytics.js';
import { kvDelete, kvGet, kvSet } from './db.js';
import { render } from './template.js';
import { showToast } from './toast.js';

export const shareKey = (eventId) => `campCardShare:${eventId}`;

const CLAIMED = "Someone has already linked this name. If that wasn't you, ask an organizer.";
const VISIBLE = ['role', 'company', 'city', 'interests', 'askMeAbout'];

/**
 * What goes to the server for a saved card: only the fields shown on it,
 * and the link its QR opens (`resolveLink` from camp-card.js; `qrField` is
 * the field that QR uses). Nothing else from the form.
 */
export function sharedFields(card, qrField, resolveLink) {
  const shown = new Set(card?.visibleFields ?? []);
  const fields = {};

  VISIBLE.forEach((key) => {
    if (!shown.has(key)) return;
    if (key === 'interests') {
      const tags = (card.interests ?? []).filter((t) => typeof t === 'string' && t.trim() !== '');
      if (tags.length) fields.interests = tags;
    } else if (typeof card[key] === 'string' && card[key].trim() !== '') {
      fields[key] = card[key].trim();
    }
  });

  const url = qrField ? resolveLink(qrField, card?.[qrField]) : null;
  if (url) fields.qr = { type: qrField, url };

  return fields;
}

/** A card that can be shown: a name and a link for its QR (the Camp Card page's own rule). */
export function canShare(card, qrField, resolveLink) {
  return Boolean(card?.name?.trim()) && Boolean(qrField && resolveLink(qrField, card[qrField]));
}

/** Up to `max` attendee-list names containing what was typed, as typed order. */
export function matchNames(entries, query, max = 8) {
  const q = query.trim().toLowerCase();
  if (q.length < 2) return [];

  return entries.filter((e) => (e.name ?? '').toLowerCase().includes(q)).slice(0, max);
}

async function loadRoster(eventSlug) {
  const first = await apiGet(eventSlug, '/roster');
  const entries = [...(first.data ?? [])];
  const pages = first.last_page ?? 1;
  if (pages > 1) {
    const rest = await Promise.all(Array.from({ length: pages - 1 }, (_, i) => apiGet(eventSlug, `/roster?page=${i + 2}`)));
    rest.forEach((page) => entries.push(...(page.data ?? [])));
  }

  return entries;
}

/**
 * Wires the section up. `current()` returns { card, qrField } for what is
 * saved now; `resolveLink` is camp-card.js's. Returns `onCardSaved()` for the
 * form's Save to call.
 */
export async function initCardShare({ eventSlug, eventId, current, resolveLink }) {
  const section = document.getElementById('cc-share');
  const toggle = document.getElementById('cc-share-toggle');
  const body = document.getElementById('cc-share-body');
  if (!section || !toggle || !body) return { onCardSaved: () => {} };

  let state = await kvGet(shareKey(eventId)).catch(() => null);
  let busy = false;

  const discoveryProof = async () => {
    const mine = await kvGet(`discovery:${eventId}`).catch(() => null);
    return mine ? { discovery_id: mine.discoveryId, discovery_token: mine.ownerToken, rosterId: mine.card?.roster_id ?? null, name: mine.card?.name ?? null } : null;
  };

  const showStatus = () => {
    if (!state) {
      body.replaceChildren();
      return;
    }
    body.replaceChildren(render('tpl-cc-share-status', { name: state.name ?? '' }));
  };

  const payload = async (rosterId) => {
    const { card, qrField } = await current();
    const proof = await discoveryProof();

    return {
      attendee_roster_id: rosterId,
      discovery_id: proof?.discovery_id ?? null,
      discovery_token: proof?.discovery_token ?? null,
      card: sharedFields(card, qrField, resolveLink),
    };
  };

  const share = async (rosterId, name) => {
    busy = true;
    try {
      const reply = await apiMutate(eventSlug, '/camp-card-share', 'POST', await payload(rosterId));
      state = { shareId: reply.share_id, ownerToken: reply.owner_token, rosterId, name };
      await kvSet(shareKey(eventId), state);
      toggle.checked = true;
      track('camp_card_share', { result: 'on' });
      showToast('Your Camp Card is on the attendee list.');
    } catch (error) {
      toggle.checked = false;
      showToast(error?.status === 422 ? CLAIMED : "Couldn't share it right now. Try again.");
    } finally {
      busy = false;
      showStatus();
    }
  };

  const showPicker = async () => {
    const picker = render('tpl-cc-share-picker');
    body.replaceChildren(picker);
    const input = picker.querySelector('[data-share-search]');
    const list = picker.querySelector('[data-share-results]');
    let entries = [];
    try {
      entries = await loadRoster(eventSlug);
    } catch {
      body.replaceChildren();
      toggle.checked = false;
      showToast("Couldn't load the attendee list. Check your connection and try again.");
      return;
    }

    input.addEventListener('input', () => {
      list.replaceChildren(...matchNames(entries, input.value).map((entry) => {
        const row = render('tpl-cc-share-result', { name: entry.name });
        row.addEventListener('click', () => share(entry.id, entry.name));
        return row;
      }));
    });
    picker.querySelector('[data-share-cancel]').addEventListener('click', () => {
      toggle.checked = false;
      body.replaceChildren();
    });
    input.focus();
  };

  toggle.checked = Boolean(state);
  showStatus();

  toggle.addEventListener('change', async () => {
    if (busy) return;

    if (toggle.checked) {
      const { card, qrField } = await current();
      if (!canShare(card, qrField, resolveLink)) {
        toggle.checked = false;
        showToast('Save your Camp Card first: your name and at least one link.');
        return;
      }

      // Already "that's me" in discovery on this phone: the same name, no picking.
      const proof = await discoveryProof();
      if (proof?.rosterId) {
        await share(proof.rosterId, proof.name);
      } else {
        await showPicker();
      }
      return;
    }

    if (!state) {
      body.replaceChildren();
      return;
    }

    busy = true;
    try {
      await apiMutate(eventSlug, `/camp-card-share/${state.shareId}`, 'DELETE', null, state.ownerToken);
    } catch (error) {
      // Already gone (an organizer removed it): nothing left to take off.
      if (error?.status !== 403) {
        toggle.checked = true;
        busy = false;
        showToast("Couldn't take it off right now. Try again.");
        return;
      }
    }
    state = null;
    await kvDelete(shareKey(eventId)).catch(() => {});
    busy = false;
    track('camp_card_share', { result: 'off' });
    showToast('Your Camp Card is off the attendee list.');
    showStatus();
  });

  return {
    // After Save: the shared copy follows the card.
    async onCardSaved() {
      if (!state) return;
      try {
        await apiMutate(eventSlug, `/camp-card-share/${state.shareId}`, 'PUT', await payload(state.rosterId), state.ownerToken);
      } catch (error) {
        if (error?.status === 403) {
          state = null;
          await kvDelete(shareKey(eventId)).catch(() => {});
          toggle.checked = false;
          showStatus();
        } else {
          showToast("Saved on this phone. The attendee list copy updates next time you save.");
        }
      }
    },
  };
}
