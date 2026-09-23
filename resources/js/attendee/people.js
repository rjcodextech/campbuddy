// Explore → People: two distinct, clearly-labeled sections
// — the ingested roster ("see who's attending") and CampBuddy discovery
// matching ("see who matches your interests") — never blended into one
// list, since the roster has no interest data and never opted into
// CampBuddy matching at all.
//
// Markup: the page shell is in attendee/explore.blade.php; everything
// rendered from here is a <template> in attendee/templates/{people,discovery}.blade.php.

import { apiGet, apiMutate } from './api.js';
import { getMetHistory, kvGet, kvSet, markMet } from './db.js';
import { render, renderFragment } from './template.js';
import { showToast } from './toast.js';

const TAGS = [
  'developer', 'designer', 'content creator', 'site builder',
  'community organizer', 'marketer', 'business owner', 'blogger',
  'translator', 'speaker',
];

// aria-labels for the roster's social icons; the glyphs themselves are
// tpl-social-icon-{type} templates (types without one fall back to "website").
const SOCIAL_LABEL = { twitter: 'X / Twitter', linkedin: 'LinkedIn', website: 'Website' };

export async function renderPeople(root) {
  const discoveryEl = document.getElementById('people-discovery');
  if (!discoveryEl) return;

  const eventSlug = root.dataset.eventSlug;
  const eventId = Number(root.dataset.eventId);
  const discoveryKey = `discovery:${eventId}`;

  await Promise.all([
    renderDiscoveryCard(discoveryEl, eventSlug, eventId, discoveryKey),
    renderRoster(eventSlug),
  ]);
}

async function renderRoster(eventSlug) {
  const el = document.getElementById('people-roster');
  const searchEl = document.getElementById('roster-search');
  let entries = [];

  try {
    entries = await fetchFullRoster(eventSlug);
  } catch {
    el.replaceChildren(render('tpl-roster-offline'));
    return;
  }

  const draw = (list) => {
    if (list.length === 0) {
      el.replaceChildren(render(entries.length === 0 ? 'tpl-roster-empty' : 'tpl-roster-no-match'));
      return;
    }

    const rows = document.createDocumentFragment();
    list.forEach((a) => rows.appendChild(rosterRow(a)));
    el.replaceChildren(rows);
  };

  draw(entries);

  searchEl.addEventListener('input', () => {
    const q = searchEl.value.trim().toLowerCase();
    draw(q ? entries.filter((a) => a.name.toLowerCase().includes(q)) : entries);
  });
}

// The roster API paginates (200/request) to keep any single response
// bounded, but the attendee should see everyone — fetch every page (in
// parallel, once the first page reveals how many there are) and render
// one flat list into #people-roster's normal flow. No inner scroll box:
// this list is exactly as tall as its content, and the page itself
// scrolls, same as every other list in the app.
async function fetchFullRoster(eventSlug) {
  const first = await apiGet(eventSlug, '/roster');
  const entries = [...(first.data ?? [])];
  const lastPage = first.last_page ?? 1;

  if (lastPage > 1) {
    const rest = await Promise.all(
      Array.from({ length: lastPage - 1 }, (_, i) => apiGet(eventSlug, `/roster?page=${i + 2}`))
    );
    rest.forEach((page) => entries.push(...(page.data ?? [])));
  }

  return entries;
}

function rosterRow(a) {
  const initial = (a.name ?? '?').trim().charAt(0).toUpperCase() || '?';

  const links = (a.links ?? []).map((l) =>
    render('tpl-roster-link', {
      link: {
        attrs: { href: l.url, 'aria-label': SOCIAL_LABEL[l.type] ?? l.type },
        children: [render(`tpl-social-icon-${SOCIAL_LABEL[l.type] ? l.type : 'website'}`)],
      },
    })
  );

  return render('tpl-roster-row', {
    'avatar-img': a.gravatar_url ? { attrs: { src: a.gravatar_url } } : null,
    'avatar-initial': a.gravatar_url ? null : initial,
    name: a.name ?? '',
    links: links.length > 0 ? links : null,
  });
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
    el.replaceChildren(render('tpl-discovery-join-prompt'));
    el.querySelector('#join-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, null, options));
    return;
  }

  await renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
}

function showJoinForm(el, eventSlug, eventId, discoveryKey, existing = null, options = {}) {
  const selected = new Set(existing?.fields?.tags ?? []);

  el.replaceChildren(
    render('tpl-discovery-join-form', {
      verb: existing ? 'Update' : 'Join',
      tags: TAGS.map((t) =>
        render('tpl-discovery-tag-chip', {
          chip: { text: t, attrs: { 'data-tag': t }, class: { 'chip--selected': selected.has(t) } },
        })
      ),
      profession: { attrs: { value: existing?.fields?.profession ?? '' } },
      who: { attrs: { value: existing?.fields?.who_to_meet ?? '' } },
      submit: existing ? 'Save' : 'Join',
    })
  );

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
  const statusCard = () => render('tpl-discovery-status', { tags: mine.fields.tags.join(', ') });

  if (options.compact) {
    el.replaceChildren(
      statusCard(),
      render('tpl-discovery-explore-link', { link: { attrs: { href: options.exploreUrl ?? '#' } } })
    );
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

  el.replaceChildren(
    statusCard(),
    renderFragment('tpl-discovery-matches', {
      offline,
      empty: matches.length === 0,
      matches: matches.map((p) => matchCard(p, false)),
      'met-section': met.length > 0,
      met: met.map((p) => matchCard(p, true)),
    })
  );

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
  el.replaceChildren(render('tpl-discovery-join-prompt'));
  el.querySelector('#join-discovery-btn').addEventListener('click', () => showJoinForm(el, eventSlug, eventId, discoveryKey, null, options));
}

function matchCard(profile, isMet) {
  const { tags, profession, who_to_meet: whoToMeet } = profile.fields;

  return render('tpl-discovery-match', {
    tags: (tags ?? []).map((t) => render('tpl-discovery-match-tag', { tag: t })),
    profession: profession || null,
    'who-row': Boolean(whoToMeet),
    who: whoToMeet,
    'met-btn': isMet ? null : { attrs: { 'data-met-id': profile.discovery_id } },
    'met-label': isMet,
  });
}
