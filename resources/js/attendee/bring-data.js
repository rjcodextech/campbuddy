// "Bring it from your last WordCamp" (Home): a phone that holds a profile or
// people met at a WordCamp that ended in the last month (PastEvents::forHome)
// is offered, once per event, to bring them here —
//   - the "Meet people" answers (interests, role, who to meet, WordPress.org,
//     a typed name) fill this event's join form (people.js reads
//     kv discoveryCarry:<eventId>); the attendee-list name is picked again;
//   - people met there who are on this event's attendee list too are added to
//     "People to meet", with a note saying where they met.
// Everything stays on the phone; nothing is sent until they press Join.

import { track } from './analytics.js';
import { apiGet } from './api.js';
import { getMeetings, kvGet, kvSet, saveMeeting } from './db.js';
import { showToast } from './toast.js';

export const BRING_KEY = (eventId) => `bring:${eventId}`;
export const CARRY_KEY = (eventId) => `discoveryCarry:${eventId}`;

/** The join-form answers worth bringing (never the attendee-list entry: it is another event's). */
export function carriedFields(fields) {
  if (!fields || typeof fields !== 'object') return null;
  const out = {
    tags: Array.isArray(fields.tags) ? fields.tags.slice(0, 10) : [],
    profession: fields.profession || null,
    who_to_meet: fields.who_to_meet || null,
    wporg_username: fields.wporg_username || null,
    display_name: fields.display_name || null,
  };
  return out.tags.length || out.profession || out.who_to_meet || out.wporg_username || out.display_name ? out : null;
}

/** The newest past WordCamp this phone holds something worth bringing for. Pure. */
export function pickSource(pastEvents, local) {
  for (const event of pastEvents ?? []) {
    const held = local[event.id];
    if (!held) continue;
    const people = (held.meetings ?? []).filter((m) => m.name && !m.mergedInto && !m.hidden);
    const fields = carriedFields(held.fields);
    if (fields || people.length) return { event, fields, people };
  }
  return null;
}

const normalName = (name) => String(name ?? '').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
const gravatarHash = (url) => /gravatar\.com\/avatar\/([0-9a-f]{32,64})/i.exec(String(url ?? ''))?.[1]?.toLowerCase() ?? null;

/** People met there who are on this attendee list too: the same Gravatar, else the same full name. Pure. */
export function matchPeople(people, roster) {
  const byHash = new Map();
  const byName = new Map();
  for (const entry of roster ?? []) {
    const hash = gravatarHash(entry.gravatar_url);
    if (hash && !byHash.has(hash)) byHash.set(hash, entry);
    const name = normalName(entry.name);
    // A full name only: "Amit" alone would match the wrong Amit.
    if (name.includes(' ') && !byName.has(name)) byName.set(name, entry);
  }
  const seen = new Set();
  const out = [];
  for (const person of people ?? []) {
    const hash = gravatarHash(person.avatarUrl);
    const entry = (hash && byHash.get(hash)) || byName.get(normalName(person.name));
    if (!entry || seen.has(entry.id)) continue;
    seen.add(entry.id);
    out.push({ entry, person });
  }
  return out;
}

async function fullRoster(slug) {
  const saved = await kvGet(`roster:${slug}`).catch(() => null);
  if (Array.isArray(saved?.entries) && saved.entries.length) return saved.entries;
  const first = await apiGet(slug, '/roster');
  const last = Math.min(first?.last_page ?? 1, 20);
  const rest = last > 1 ? await Promise.all(Array.from({ length: last - 1 }, (_, i) => apiGet(slug, `/roster?page=${i + 2}`))) : [];
  return [first, ...rest].flatMap((p) => p?.data ?? []);
}

export async function initBringData({ slot, eventId, eventSlug, eventName, pastEvents }) {
  if (!slot || !pastEvents?.length) return;
  try {
    if (await kvGet(BRING_KEY(eventId))) return;
    // Already in "Meet people" here: the answers are in, only people are worth bringing.
    const joinedHere = Boolean(await kvGet(`discovery:${eventId}`));

    const local = {};
    for (const past of pastEvents) {
      const [discovery, meetings] = await Promise.all([kvGet(`discovery:${past.id}`).catch(() => null), getMeetings(past.id).catch(() => [])]);
      local[past.id] = { fields: joinedHere ? null : discovery?.fields, meetings };
    }
    const source = pickSource(pastEvents, local);
    if (!source) return;
    render(slot, { eventId, eventSlug, eventName, source });
  } catch {
    // Storage unavailable: nothing to offer.
  }
}

function render(slot, { eventId, eventSlug, eventName, source }) {
  const { event, fields, people } = source;
  const what = [fields && 'your Meet people profile', people.length && `the ${people.length} ${people.length === 1 ? 'person' : 'people'} you met`].filter(Boolean).join(' and ');

  slot.replaceChildren();
  const card = document.createElement('div');
  card.className = 'card bring-data';
  card.innerHTML = '<p class="u-eyebrow bring-data__eyebrow">Welcome back</p><p class="bring-data__title"></p><p class="bring-data__text"></p>'
    + '<div class="bring-data__actions"><button type="button" class="btn btn--primary" data-bring-yes>Bring it here</button>'
    + '<button type="button" class="btn btn--outline" data-bring-no>Not now</button></div>';
  card.querySelector('.bring-data__title').textContent = `Bring ${what} from ${event.name}?`;
  card.querySelector('.bring-data__text').textContent = `Your interests fill in when you join Meet people at ${eventName}, and anyone you met there who is coming here too goes straight into your People to meet. It all stays on this phone.`;
  slot.append(card);
  slot.hidden = false;
  track('bring_data_view');

  card.querySelector('[data-bring-no]').addEventListener('click', async () => {
    await kvSet(BRING_KEY(eventId), { dismissed: true, from: event.id, at: Date.now() }).catch(() => {});
    track('bring_data', { result: 'dismissed' });
    slot.hidden = true;
  });

  card.querySelector('[data-bring-yes]').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    let added = 0;
    try {
      if (fields) await kvSet(CARRY_KEY(eventId), { from: event.id, fromName: event.name, fields });
      if (people.length) {
        const roster = await fullRoster(eventSlug).catch(() => []);
        const existing = new Set((await getMeetings(eventId).catch(() => [])).map((m) => m.personKey));
        for (const { entry, person } of matchPeople(people, roster)) {
          const personKey = `r:${entry.id}`;
          if (existing.has(personKey)) continue;
          await saveMeeting(eventId, personKey, {
            name: entry.name,
            avatarUrl: entry.gravatar_url || person.avatarUrl || null,
            sub: 'On the attendee list',
            source: 'roster',
            links: (entry.links ?? []).filter((l) => /^https?:\/\//i.test(l.url ?? '')),
            note: [`You met at ${event.name}.`, person.note].filter(Boolean).join(' '),
            at: null,
            status: null,
          });
          added++;
        }
      }
      await kvSet(BRING_KEY(eventId), { done: true, from: event.id, at: Date.now(), people: added });
      track('bring_data', { result: 'done' });
      showDone(slot, { fields, added, eventName, eventSlug });
    } catch {
      btn.disabled = false;
      showToast("Couldn't bring it just now. Try again.");
    }
  });
}

function showDone(slot, { fields, added, eventName, eventSlug }) {
  const lines = [];
  if (fields) lines.push('Your interests are ready: join Meet people and they are already filled in.');
  if (added) lines.push(`${added} ${added === 1 ? 'person you met is' : 'people you met are'} at ${eventName} too, now in your People to meet.`);
  else lines.push(`Nobody you met there is on ${eventName}'s attendee list yet. Your notes from there stay safe on this phone.`);
  slot.replaceChildren();
  const card = document.createElement('div');
  card.className = 'card bring-data bring-data--done';
  card.innerHTML = '<p class="bring-data__title">Brought across</p><p class="bring-data__text"></p><a class="btn btn--primary">Meet people here</a>';
  card.querySelector('.bring-data__text').textContent = lines.join(' ');
  card.querySelector('a').href = `/event/${encodeURIComponent(eventSlug)}/explore`;
  slot.append(card);
}
