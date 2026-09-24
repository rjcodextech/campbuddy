// Explore → People: two distinct, clearly-labeled sections
// — the ingested roster ("see who's attending") and CampBuddy discovery
// matching ("see who matches your interests") — never blended into one
// list, since the roster has no interest data and never opted into
// CampBuddy matching at all.
//
// Markup: the page shell is in attendee/explore.blade.php; everything
// rendered from here is a <template> in attendee/templates/{people,discovery}.blade.php.

import { apiGet, apiMutate } from './api.js';
import { track } from './analytics.js';
import { getMetHistory, kvGet, kvSet, markMet } from './db.js';
import { render, renderFragment } from './template.js';
import { showToast } from './toast.js';

const TAGS = [
  'developer', 'designer', 'content creator', 'site builder',
  'community organizer', 'marketer', 'business owner', 'blogger',
  'translator', 'speaker',
];

// The discovery API takes at most 5 tags (StoreDiscoveryRequest); the form
// stops there instead of failing on save with a misleading network error.
const MAX_DISCOVERY_TAGS = 5;

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

  // Roster data is other people's data (§8.4) — only "the search was used"
  // is reported, once, never what was typed.
  let searchReported = false;

  searchEl.addEventListener('input', () => {
    if (!searchReported) {
      searchReported = true;
      track('roster_search_use');
    }

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
// One fetch per page view, shared by the roster list and the discovery
// form's "pick your name" search.
const rosterRequests = new Map();

function fetchFullRoster(eventSlug) {
  if (!rosterRequests.has(eventSlug)) {
    const request = loadFullRoster(eventSlug);
    request.catch(() => rosterRequests.delete(eventSlug));
    rosterRequests.set(eventSlug, request);
  }
  return rosterRequests.get(eventSlug);
}

async function loadFullRoster(eventSlug) {
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

  // Only web addresses become links (the server filters too) — a javascript:
  // href would run in the app itself.
  const links = (a.links ?? []).filter((l) => /^https?:\/\//i.test(l.url ?? '')).map((l) =>
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
    'open-badge': Boolean(a.open_to_meet),
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
    showJoinPrompt(el, eventSlug, eventId, discoveryKey, options);
    return;
  }

  await renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
}

// Discovery events carry only which screen the action happened on — none of
// the profile, the matches or the IDs (spec §8.3/§8.5).
const surfaceOf = (options) => (options.compact ? 'home' : 'explore');

function showJoinPrompt(el, eventSlug, eventId, discoveryKey, options) {
  el.replaceChildren(render('tpl-discovery-join-prompt'));
  el.querySelector('#join-discovery-btn').addEventListener('click', () => {
    track('discovery_join_start', { surface: surfaceOf(options) });
    showJoinForm(el, eventSlug, eventId, discoveryKey, null, options);
  });
}

function showJoinForm(el, eventSlug, eventId, discoveryKey, existing = null, options = {}) {
  const fields = existing?.fields ?? {};
  const selected = new Set(fields.tags ?? []);
  let identity = fields.attendee_roster_id ? 'roster' : fields.display_name ? 'typed' : existing ? 'anonymous' : 'roster';
  let picked = fields.attendee_roster_id
    ? { id: fields.attendee_roster_id, name: existing.card?.name, gravatar_url: existing.card?.avatar_url }
    : null;

  el.replaceChildren(
    render('tpl-discovery-join-form', {
      verb: existing ? 'Update' : 'Join',
      tags: TAGS.map((t) =>
        render('tpl-discovery-tag-chip', {
          chip: { text: t, attrs: { 'data-tag': t, 'aria-pressed': String(selected.has(t)) }, class: { 'chip--selected': selected.has(t) } },
        })
      ),
      'display-name': { attrs: { value: fields.display_name ?? '' } },
      profession: { attrs: { value: fields.profession ?? '' } },
      who: { attrs: { value: fields.who_to_meet ?? '' } },
      wporg: { attrs: { value: fields.wporg_username ?? '', autocapitalize: 'none', spellcheck: 'false' } },
      submit: existing ? 'Save' : 'Join',
    })
  );

  const errorEl = el.querySelector('[data-join-error]');
  const showError = (message) => {
    errorEl.textContent = message;
    errorEl.hidden = !message;
  };

  // --- Who are you? ----------------------------------------------------
  const panels = el.querySelectorAll('[data-identity-panel]');
  const setIdentity = (value) => {
    identity = value;
    el.querySelectorAll('input[name="identity"]').forEach((r) => { r.checked = r.value === value; });
    panels.forEach((p) => { p.hidden = p.dataset.identityPanel !== value; });
  };
  el.querySelectorAll('input[name="identity"]').forEach((radio) => {
    radio.addEventListener('change', () => setIdentity(radio.value));
  });
  setIdentity(identity);

  mountRosterPicker(el, eventSlug, () => picked, (entry) => { picked = entry; showError(''); }, fields.attendee_roster_id ?? null);

  // --- Tags ------------------------------------------------------------
  el.querySelectorAll('[data-tag]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const tag = chip.dataset.tag;

      if (selected.has(tag)) {
        selected.delete(tag);
      } else if (selected.size >= MAX_DISCOVERY_TAGS) {
        showToast(`Pick up to ${MAX_DISCOVERY_TAGS} tags.`);
        return;
      } else {
        selected.add(tag);
      }

      const on = selected.has(tag);
      chip.classList.toggle('chip--selected', on);
      chip.setAttribute('aria-pressed', String(on));
    });
  });

  // --- Save ------------------------------------------------------------
  const submitBtn = el.querySelector('#join-submit');
  submitBtn.addEventListener('click', async () => {
    const displayName = el.querySelector('#join-display-name').value.trim();
    const body = {
      tags: [...selected],
      profession: el.querySelector('#join-profession').value.trim() || null,
      who_to_meet: el.querySelector('#join-who').value.trim() || null,
      wporg_username: el.querySelector('#join-wporg').value.trim() || null,
      attendee_roster_id: identity === 'roster' ? picked?.id ?? null : null,
      display_name: identity === 'typed' ? displayName || null : null,
    };

    if (identity === 'roster' && !picked) {
      showError('Find and tap your name in the list — or choose "Type my name".');
      return;
    }
    if (identity === 'typed' && displayName.length < 2) {
      showError('Type the name people know you by — or choose "Stay anonymous".');
      return;
    }
    if (body.tags.length === 0) {
      showError('Pick at least one tag that describes you.');
      return;
    }

    showError('');
    submitBtn.disabled = true;

    try {
      let card;
      if (existing) {
        card = await apiMutate(eventSlug, `/discovery/${existing.discoveryId}`, 'PATCH', body, existing.ownerToken);
        await kvSet(discoveryKey, { ...existing, fields: savedFields(body, card), card });
      } else {
        card = await apiMutate(eventSlug, '/discovery', 'POST', body);
        const { owner_token: ownerToken, ...publicCard } = card;
        await kvSet(discoveryKey, { discoveryId: card.discovery_id, ownerToken, fields: savedFields(body, publicCard), card: publicCard });
      }

      track(existing ? 'discovery_update' : 'discovery_join', { surface: surfaceOf(options) });

      const mine = await kvGet(discoveryKey);
      await renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
    } catch (error) {
      submitBtn.disabled = false;
      showError(error.userMessage ?? "Couldn't save — check your connection and try again.");
    }
  });
}

/**
 * What was sent, as the server saved it: the WordPress.org username the
 * server tidied (a pasted profile link becomes just the username), so the
 * edit form shows what's really shared.
 */
function savedFields(body, card) {
  const match = /^https:\/\/profiles\.wordpress\.org\/([^/]+)\/$/.exec(card?.wporg_url ?? '');
  return { ...body, wporg_username: match ? decodeURIComponent(match[1]) : null };
}

/**
 * "Pick my name": a search over the event's attendee list. Names someone
 * else already linked are shown but can't be picked — one name, one profile.
 */
function mountRosterPicker(el, eventSlug, getPicked, onPick, myRosterId) {
  const searchWrap = el.querySelector('[data-roster-search]');
  const selectedWrap = el.querySelector('[data-roster-selected]');
  const input = el.querySelector('#join-roster-search');
  const results = el.querySelector('[data-roster-results]');
  let entries = null;

  const showPicked = () => {
    const picked = getPicked();
    selectedWrap.hidden = !picked;
    searchWrap.hidden = Boolean(picked);
    if (!picked) return;

    selectedWrap.querySelector('[data-picked-name]').textContent = picked.name ?? '';
    const avatar = selectedWrap.querySelector('[data-picked-avatar]');
    avatar.src = picked.gravatar_url || '/media/illustrations/avatar.svg';
  };

  const draw = () => {
    const q = input.value.trim().toLowerCase();

    if (entries === null) {
      results.replaceChildren(render('tpl-roster-picker-empty', { text: 'Loading the attendee list…' }));
      return;
    }
    if (entries.length === 0) {
      results.replaceChildren(render('tpl-roster-picker-empty', { text: 'This event has no public attendee list yet — choose "Type my name" instead.' }));
      return;
    }
    if (q.length < 2) {
      results.replaceChildren();
      return;
    }

    const found = entries.filter((a) => (a.name ?? '').toLowerCase().includes(q)).slice(0, 8);
    if (found.length === 0) {
      results.replaceChildren(render('tpl-roster-picker-empty', { text: 'No one by that name on the list — check the spelling, or choose "Type my name".' }));
      return;
    }

    results.replaceChildren(
      ...found.map((a) => {
        // Taken = another profile already uses this name (my own current one isn't).
        const taken = Boolean(a.open_to_meet) && a.id !== myRosterId;
        const row = render('tpl-roster-picker-row', {
          row: { attrs: { 'aria-disabled': taken ? 'true' : null }, class: { 'roster-picker__row--taken': taken } },
          avatar: { attrs: { src: a.gravatar_url || '/media/illustrations/avatar.svg' } },
          name: a.name,
          note: taken,
        });
        row.addEventListener('click', () => {
          if (taken) {
            showToast('Someone already linked this name. If that wasn\'t you, ask an organizer.');
            return;
          }
          onPick(a);
          showPicked();
        });
        return row;
      })
    );
  };

  selectedWrap.querySelector('[data-picked-change]').addEventListener('click', () => {
    onPick(null);
    showPicked();
    input.value = '';
    draw();
    input.focus();
  });
  input.addEventListener('input', draw);

  showPicked();
  draw();
  fetchFullRoster(eventSlug)
    .then((list) => { entries = list; draw(); })
    .catch(() => {
      results.replaceChildren(render('tpl-roster-picker-empty', { text: 'You\'re offline, so the attendee list can\'t load — choose "Type my name" for now.' }));
    });
}

function statusCard(mine) {
  const name = mine.card?.name ?? mine.fields.display_name ?? null;
  const avatar = mine.card?.avatar_url;

  return render('tpl-discovery-status', {
    avatar: avatar ? { attrs: { src: avatar } } : { attrs: { src: '/media/illustrations/avatar.svg' } },
    'as-part': Boolean(name),
    name: name ?? '',
    tags: name ? mine.fields.tags.join(', ') : `Anonymously · ${mine.fields.tags.join(', ')}`,
  });
}

async function renderMatches(el, eventSlug, eventId, discoveryKey, mine, options = {}) {
  if (options.compact) {
    el.replaceChildren(
      statusCard(mine),
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

  const others = profiles
    .filter((p) => p.discovery_id !== mine.discoveryId)
    .map((p) => ({ ...p, common: (p.fields.tags ?? []).filter((t) => myTags.has(t)) }));
  const notMet = others.filter((p) => !metIds.has(p.discovery_id));
  // Named people first within each group — they're the ones you can find.
  const byStrength = (a, b) => b.common.length - a.common.length || Number(Boolean(b.name)) - Number(Boolean(a.name));
  const matches = notMet.filter((p) => p.common.length > 0).sort(byStrength);
  const rest = notMet.filter((p) => p.common.length === 0).sort(byStrength);
  const met = others.filter((p) => metIds.has(p.discovery_id));

  el.replaceChildren(
    statusCard(mine),
    renderFragment('tpl-discovery-matches', {
      offline,
      empty: others.length === 0 && !offline,
      'matches-section': matches.length > 0,
      matches: matches.map((p) => matchCard(p, false)),
      'others-section': rest.length > 0,
      others: rest.map((p) => matchCard(p, false)),
      'met-section': met.length > 0,
      met: met.map((p) => matchCard(p, true)),
    })
  );

  el.querySelectorAll('[data-met-id]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      await markMet(eventId, btn.dataset.metId);
      track('discovery_met_mark');
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
  track('discovery_leave', { surface: surfaceOf(options) });
  showJoinPrompt(el, eventSlug, eventId, discoveryKey, options);
}

function matchCard(profile, isMet) {
  const { tags, profession, who_to_meet: whoToMeet } = profile.fields;
  const common = new Set(profile.common ?? []);
  const isWeb = (url) => /^https?:\/\//i.test(url ?? '');

  const links = (profile.links ?? []).filter((l) => isWeb(l.url)).map((l) =>
    render('tpl-roster-link', {
      link: {
        attrs: { href: l.url, 'aria-label': `${SOCIAL_LABEL[l.type] ?? l.type}${profile.name ? ` — ${profile.name}` : ''}` },
        children: [render(`tpl-social-icon-${SOCIAL_LABEL[l.type] ? l.type : 'website'}`)],
      },
    })
  );

  return render('tpl-discovery-match', {
    avatar: { attrs: { src: isWeb(profile.avatar_url) ? profile.avatar_url : '/media/illustrations/avatar.svg' } },
    name: profile.name || 'Anonymous attendee',
    verified: Boolean(profile.on_attendee_list),
    profession: profession || null,
    'common-row': common.size > 0,
    common: [...common].join(', '),
    tags: (tags ?? []).filter((t) => !common.has(t)).map((t) => render('tpl-discovery-match-tag', { tag: t })),
    'who-row': Boolean(whoToMeet),
    who: whoToMeet,
    wporg: isWeb(profile.wporg_url) ? { attrs: { href: profile.wporg_url } } : null,
    links: links.length ? links : [],
    'met-btn': isMet ? null : { attrs: { 'data-met-id': profile.discovery_id } },
    'met-label': isMet,
  });
}
