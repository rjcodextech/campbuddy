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
    <div id="people-roster" class="card">Loading…</div>
  `;

  await Promise.all([renderDiscoverySection(eventSlug, eventId, discoveryKey), renderRoster(eventSlug)]);
}

async function renderRoster(eventSlug) {
  const el = document.getElementById('people-roster');

  try {
    const page = await apiGet(eventSlug, '/roster');
    const entries = page.data ?? [];

    el.innerHTML = entries.length === 0
      ? `<p style="margin:0">No public attendee listing yet.</p>`
      : entries.map((a) => `
          <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--line)">
            ${a.gravatar_url ? `<img src="${escapeAttr(a.gravatar_url)}" alt="" style="width:32px;height:32px;border-radius:50%">` : ''}
            <span style="font-weight:600">${escapeHtml(a.name)}</span>
            ${(a.links ?? []).map((l) => `<a href="${escapeAttr(l.url)}" target="_blank" rel="noopener" class="social-chip">${escapeHtml(l.type)}</a>`).join('')}
          </div>
        `).join('');
  } catch {
    el.innerHTML = `<p style="margin:0">You're offline. The attendee list will refresh when you're connected again.</p>`;
  }
}

async function renderDiscoverySection(eventSlug, eventId, discoveryKey) {
  const el = document.getElementById('people-discovery');
  const mine = await kvGet(discoveryKey);

  if (!mine) {
    el.innerHTML = joinPromptHtml();
    document.getElementById('join-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey));
    return;
  }

  await renderMatches(el, eventSlug, eventId, discoveryKey, mine);
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

function showJoinForm(el, eventSlug, eventId, discoveryKey, existing = null) {
  const chips = TAGS.map(
    (t) => `<button type="button" class="chip ${existing?.fields?.tags?.includes(t) ? 'chip--selected' : ''}" data-tag="${t}">${t}</button>`
  ).join('');

  el.innerHTML = `
    <div class="card">
      <p style="font-weight:700;margin:0 0 8px">${existing ? 'Update' : 'Join'} attendee discovery</p>
      <div class="chip-group" id="join-tags">${chips}</div>
      <label class="field"><span>Profession (optional)</span><input type="text" id="join-profession" value="${escapeAttr(existing?.fields?.profession ?? '')}"></label>
      <label class="field"><span>Who would you like to meet? (optional)</span><input type="text" id="join-who" value="${escapeAttr(existing?.fields?.who_to_meet ?? '')}"></label>
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

  document.getElementById('join-submit').addEventListener('click', async () => {
    const body = {
      tags: [...selected],
      profession: document.getElementById('join-profession').value || null,
      who_to_meet: document.getElementById('join-who').value || null,
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
      await renderMatches(el, eventSlug, eventId, discoveryKey, mine);
    } catch {
      showToast("Couldn't save — check your connection and try again.");
    }
  });
}

async function renderMatches(el, eventSlug, eventId, discoveryKey, mine) {
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
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div>
          <p style="font-weight:700;margin:0">You're discoverable</p>
          <p class="footer-note" style="text-align:left;margin:2px 0 0">${mine.fields.tags.map(escapeHtml).join(', ')}</p>
        </div>
        <div style="display:flex;gap:6px">
          <button type="button" class="btn btn--compact btn--ghost" id="edit-discovery-btn">Edit</button>
          <button type="button" class="btn btn--compact btn--ghost" id="leave-discovery-btn">Leave</button>
        </div>
      </div>
    </div>

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
      renderMatches(el, eventSlug, eventId, discoveryKey, mine);
    });
  });

  document.getElementById('edit-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, mine));

  document.getElementById('leave-discovery-btn').addEventListener('click', async () => {
    if (!confirm('Leave attendee discovery? Your local Camp Card and progress are unaffected.')) return;

    try {
      await apiMutate(eventSlug, `/discovery/${mine.discoveryId}`, 'DELETE', null, mine.ownerToken);
    } catch {
      // Already gone server-side (e.g. expired) — still clear locally.
    }

    await kvSet(discoveryKey, null);
    el.innerHTML = joinPromptHtml();
    document.getElementById('join-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey));
  });
}

function matchCardHtml(profile, isMet) {
  const tags = (profile.fields.tags ?? []).map((t) => `<span class="camp-card__tag" style="color:var(--ink);background:var(--peach)">${escapeHtml(t)}</span>`).join('');

  return `
    <div class="card" style="margin-bottom:10px">
      <div class="camp-card__tags" style="margin-top:0">${tags}</div>
      ${profile.fields.profession ? `<p style="margin:8px 0 0;font-weight:600">${escapeHtml(profile.fields.profession)}</p>` : ''}
      ${profile.fields.who_to_meet ? `<p class="footer-note" style="text-align:left;margin:4px 0 0">Wants to meet: ${escapeHtml(profile.fields.who_to_meet)}</p>` : ''}
      ${!isMet ? `<button type="button" class="btn btn--ghost btn--compact" style="margin-top:10px" data-met-id="${escapeAttr(profile.discovery_id)}">I met them</button>` : `<p class="footer-note" style="text-align:left;margin-top:8px">✓ Met</p>`}
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
