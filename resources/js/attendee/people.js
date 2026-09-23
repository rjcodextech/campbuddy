// Explore → People: two distinct, clearly-labeled sections
// — the ingested roster ("see who's attending") and CampBuddy discovery
// matching ("see who matches your interests") — never blended into one
// list, since the roster has no interest data and never opted into
// CampBuddy matching at all.

import { apiGet, apiMutate } from './api.js';
import { getMetHistory, kvGet, kvSet, markMet } from './db.js';

const TAGS = [
  'developer', 'designer', 'content creator', 'site builder',
  'community organizer', 'marketer', 'business owner', 'blogger',
  'translator', 'speaker',
];

// Simple, recognizable glyphs rather than literal brand logos — swapped
// in for the old plain-text "twitter"/"linkedin" chips.
const SOCIAL_ICON = {
  twitter: '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.1l-4.8-6.3L5.6 21H3.5l7-8-7-10h6.2l4.3 5.8L18.9 3z"/></svg>',
  linkedin: '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.83-2.05 3.76-2.05 4.02 0 4.76 2.65 4.76 6.1V21h-4v-5.6c0-1.34-.02-3.05-1.86-3.05-1.87 0-2.16 1.46-2.16 2.96V21H9z"/></svg>',
  website: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/></svg>',
};

const SOCIAL_LABEL = { twitter: 'X / Twitter', linkedin: 'LinkedIn', website: 'Website' };

export async function renderPeople(root) {
  const container = document.getElementById('people-root');
  if (!container) return;

  const eventSlug = root.dataset.eventSlug;
  const eventId = Number(root.dataset.eventId);
  const discoveryKey = `discovery:${eventId}`;

  container.innerHTML = `
    <div id="people-discovery"></div>
    <div class="section-head" style="margin-top:24px">
      <h2 class="section-head__title">Who's attending</h2>
      <span class="section-head__desc">From the event's own Attendees page</span>
    </div>
    <input type="search" id="roster-search" class="search-input" placeholder="Search attendees…">
    <div id="people-roster" class="card">Loading…</div>
  `;

  await Promise.all([
    renderDiscoveryCard(document.getElementById('people-discovery'), eventSlug, eventId, discoveryKey),
    renderRoster(eventSlug),
  ]);
}

async function renderRoster(eventSlug) {
  const el = document.getElementById('people-roster');
  const searchEl = document.getElementById('roster-search');
  let entries = [];

  try {
    const page = await apiGet(eventSlug, '/roster');
    entries = page.data ?? [];
  } catch {
    el.innerHTML = `<p style="margin:0">You're offline. The attendee list will refresh when you're connected again.</p>`;
    return;
  }

  const draw = (list) => {
    el.innerHTML = list.length === 0
      ? `<p style="margin:0">${entries.length === 0 ? 'No public attendee listing yet.' : 'No attendees match your search.'}</p>`
      : list.map(rosterRowHtml).join('');
  };

  draw(entries);

  searchEl.addEventListener('input', () => {
    const q = searchEl.value.trim().toLowerCase();
    draw(q ? entries.filter((a) => a.name.toLowerCase().includes(q)) : entries);
  });
}

function rosterRowHtml(a) {
  const initial = escapeHtml((a.name ?? '?').trim().charAt(0).toUpperCase() || '?');
  const avatar = a.gravatar_url
    ? `<img class="roster-row__avatar" src="${escapeAttr(a.gravatar_url)}" alt="">`
    : `<span class="roster-row__avatar roster-row__avatar--initial">${initial}</span>`;

  const links = (a.links ?? [])
    .map((l) => `<a href="${escapeAttr(l.url)}" target="_blank" rel="noopener" class="social-icon" aria-label="${escapeAttr(SOCIAL_LABEL[l.type] ?? l.type)}">${SOCIAL_ICON[l.type] ?? SOCIAL_ICON.website}</a>`)
    .join('');

  return `
    <div class="roster-row">
      ${avatar}
      <span class="roster-row__name">${escapeHtml(a.name)}</span>
      ${links ? `<div class="roster-row__links">${links}</div>` : ''}
    </div>
  `;
}

/**
 * Renders the join/status card only — no matches list. Used both here
 * (full experience) and on Home (a lighter entry point, §"Find people
 * who match your interests"), so joining/leaving/editing stays one
 * implementation with one source of truth (the same IndexedDB key),
 * whichever screen the attendee acts from.
 */
export async function renderDiscoveryCard(el, eventSlug, eventId, discoveryKey, options = {}) {
  const mine = await kvGet(discoveryKey);

  if (!mine) {
    el.innerHTML = joinPromptHtml();
    el.querySelector('#join-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, null, options));
    return;
  }

  await renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
}

function joinPromptHtml() {
  return `
    <div class="card">
      <p style="font-weight:700;margin:0 0 4px">Find people who match your interests</p>
      <p class="footer-note" style="text-align:left;margin:0 0 12px">
        Opt in to share a few tags under a random ID — never your name — and see who else at this event opted in too.
        You can leave any time.
      </p>
      <button type="button" class="btn btn--primary" id="join-discovery-btn">Join attendee discovery</button>
    </div>
  `;
}

function showJoinForm(el, eventSlug, eventId, discoveryKey, existing = null, options = {}) {
  const chips = TAGS.map(
    (t) => `<button type="button" class="chip ${existing?.fields?.tags?.includes(t) ? 'chip--selected' : ''}" data-tag="${t}">${t}</button>`
  ).join('');

  el.innerHTML = `
    <div class="card">
      <p style="font-weight:700;margin:0 0 8px">${existing ? 'Update' : 'Join'} attendee discovery</p>
      <div class="chip-group" id="join-tags">${chips}</div>
      <label class="field"><span>Profession (optional)</span><input type="text" id="join-profession" placeholder="e.g. Plugin developer" value="${escapeAttr(existing?.fields?.profession ?? '')}"></label>
      <label class="field"><span>Who would you like to meet? (optional)</span><input type="text" id="join-who" placeholder="e.g. other agency owners" value="${escapeAttr(existing?.fields?.who_to_meet ?? '')}"></label>
      <button type="button" class="btn btn--primary btn--full" id="join-submit">${existing ? 'Save' : 'Join'}</button>
    </div>
  `;

  const selected = new Set(existing?.fields?.tags ?? []);
  el.querySelectorAll('[data-tag]').forEach((chip) => {
    chip.addEventListener('click', () => {
      chip.classList.toggle('chip--selected');
      selected.has(chip.dataset.tag) ? selected.delete(chip.dataset.tag) : selected.add(chip.dataset.tag);
    });
  });

  el.querySelector('#join-submit').addEventListener('click', async () => {
    const body = {
      tags: [...selected],
      profession: el.querySelector('#join-profession').value || null,
      who_to_meet: el.querySelector('#join-who').value || null,
    };

    if (body.tags.length === 0) {
      showToast('Pick at least one tag.');
      return;
    }

    try {
      if (existing) {
        await apiMutate(eventSlug, `/discovery/${existing.discoveryId}`, 'PATCH', body, existing.ownerToken);
        await kvSet(discoveryKey, { ...existing, fields: body });
      } else {
        const res = await apiMutate(eventSlug, '/discovery', 'POST', body);
        await kvSet(discoveryKey, { discoveryId: res.discovery_id, ownerToken: res.owner_token, fields: res.fields });
      }

      const mine = await kvGet(discoveryKey);
      await renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
    } catch {
      showToast("Couldn't save — check your connection and try again.");
    }
  });
}

async function renderMatches(el, eventSlug, eventId, discoveryKey, mine, options = {}) {
  const statusCard = `
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div>
          <p style="font-weight:700;margin:0">You're discoverable</p>
          <p class="footer-note" style="text-align:left;margin:2px 0 0">${mine.fields.tags.map(escapeHtml).join(', ')}</p>
        </div>
        <div style="display:flex;gap:6px">
          <button type="button" class="btn btn--compact btn--outline" id="edit-discovery-btn">Edit</button>
          <button type="button" class="btn btn--compact btn--outline" id="leave-discovery-btn">Leave</button>
        </div>
      </div>
    </div>
  `;

  if (options.compact) {
    el.innerHTML = `
      ${statusCard}
      <a href="${escapeAttr(options.exploreUrl ?? '#')}" class="btn btn--outline btn--full" style="margin-top:10px">See who matches your interests →</a>
    `;
    el.querySelector('#edit-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, mine, options));
    el.querySelector('#leave-discovery-btn').addEventListener('click', () => leaveDiscovery(el, eventSlug, eventId, discoveryKey, mine, options));
    return;
  }

  let profiles = [];
  let offline = false;

  try {
    const res = await apiGet(eventSlug, '/discovery');
    profiles = res.data ?? [];
  } catch {
    offline = true;
  }

  const metHistory = await getMetHistory(eventId);
  const metIds = new Set(metHistory.map((m) => m.discoveryId));
  const myTags = new Set(mine.fields.tags);

  const others = profiles.filter((p) => p.discovery_id !== mine.discoveryId);
  const matches = others
    .map((p) => ({ ...p, overlap: (p.fields.tags ?? []).filter((t) => myTags.has(t)).length }))
    .filter((p) => p.overlap > 0 && !metIds.has(p.discovery_id))
    .sort((a, b) => b.overlap - a.overlap);
  const met = others.filter((p) => metIds.has(p.discovery_id));

  el.innerHTML = `
    ${statusCard}

    ${offline ? `<p class="notice" style="margin-top:10px">You're offline — matches will refresh when you're connected again.</p>` : ''}

    <div style="margin-top:12px">
      ${matches.length === 0
        ? `<p class="footer-note" style="text-align:left">No matches yet — check back as more people join.</p>`
        : matches.map((p) => matchCardHtml(p, false)).join('')}
    </div>

    ${met.length > 0 ? `
      <p class="u-eyebrow" style="margin-top:20px">People you've met</p>
      ${met.map((p) => matchCardHtml(p, true)).join('')}
    ` : ''}
  `;

  el.querySelectorAll('[data-met-id]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      await markMet(eventId, btn.dataset.metId);
      renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
    });
  });

  el.querySelector('#edit-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, mine, options));
  el.querySelector('#leave-discovery-btn').addEventListener('click', () => leaveDiscovery(el, eventSlug, eventId, discoveryKey, mine, options));
}

async function leaveDiscovery(el, eventSlug, eventId, discoveryKey, mine, options) {
  if (!confirm('Leave attendee discovery? Your local Camp Card and progress are unaffected.')) return;

  try {
    await apiMutate(eventSlug, `/discovery/${mine.discoveryId}`, 'DELETE', null, mine.ownerToken);
  } catch {
    // Already gone server-side (e.g. expired) — still clear locally.
  }

  await kvSet(discoveryKey, null);
  el.innerHTML = joinPromptHtml();
  el.querySelector('#join-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, null, options));
}

function matchCardHtml(profile, isMet) {
  const tags = (profile.fields.tags ?? []).map((t) => `<span class="camp-card__tag" style="color:var(--ink);background:var(--peach)">${escapeHtml(t)}</span>`).join('');

  return `
    <div class="card" style="margin-bottom:10px">
      <div class="camp-card__tags" style="margin-top:0">${tags}</div>
      ${profile.fields.profession ? `<p style="margin:8px 0 0;font-weight:600">${escapeHtml(profile.fields.profession)}</p>` : ''}
      ${profile.fields.who_to_meet ? `<p class="footer-note" style="text-align:left;margin:4px 0 0">Wants to meet: ${escapeHtml(profile.fields.who_to_meet)}</p>` : ''}
      ${!isMet ? `<button type="button" class="btn btn--outline btn--compact" style="margin-top:10px" data-met-id="${escapeAttr(profile.discovery_id)}">I met them</button>` : `<p class="footer-note" style="text-align:left;margin-top:8px">✓ Met</p>`}
    </div>
  `;
}

function showToast(message) {
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function escapeAttr(str) {
  return escapeHtml(str);
}
