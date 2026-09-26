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
import { getMeetings, kvGet, kvSet } from './db.js';
import { windowCards } from './list-window.js';
import { openMeetSheet } from './meet-sheet.js';
import { discoveryPersonKey, peopleSignature, personState } from './people-state.js';
import { peopleStatus, stateOfMatch } from './people-status.js';
import { createRosterStore, sameRoster, savedWhen } from './roster-store.js';
import { render, renderFragment } from './template.js';
import { showToast } from './toast.js';

const TAGS = [
  'developer', 'designer', 'content creator', 'site builder',
  'community organizer', 'marketer', 'business owner', 'blogger',
  'translator', 'speaker', 'student', 'mentor',
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

  await loadMeetings(eventId);

  await Promise.all([
    renderDiscoveryCard(discoveryEl, eventSlug, eventId, discoveryKey),
    renderRoster(eventSlug, eventId),
  ]);
}

// People this device plans to meet, keyed by personKey — the Meet buttons
// on the attendee list and match cards show whether someone's already on it.
const meetingsByKey = new Map();

async function loadMeetings(eventId) {
  meetingsByKey.clear();
  try {
    (await getMeetings(eventId)).forEach((m) => meetingsByKey.set(m.personKey, m));
  } catch {
    // Private mode / storage blocked: the buttons still work for this visit.
  }
}

// What the Meet button says for each state of the person (people-state.js).
const MEET_LABEL = { planned: '✓ To meet', met: '✓ Met', missed: "Couldn't meet" };

// Every Meet button on the page, so all of them can be repainted when the
// people records change somewhere else (My Day, the other tab, a hide).
const meetPaints = new Map();

function repaintMeetButtons() {
  meetPaints.forEach((paint, btn) => {
    if (btn.isConnected) paint();
    else meetPaints.delete(btn);
  });
}

/** Fills a Meet button for a person and opens the sheet on tap. */
function wireMeetButton(btn, eventId, person) {
  const paint = () => {
    const state = personState(meetingsByKey.get(person.personKey));
    const saved = state in MEET_LABEL;
    btn.textContent = MEET_LABEL[state] ?? '+ Meet';
    btn.classList.toggle('meet-btn--saved', saved);
    btn.setAttribute('aria-label', saved ? `Edit your note about meeting ${person.name}` : `Plan to meet ${person.name}`);
  };
  paint();
  meetPaints.set(btn, paint);

  btn.addEventListener('click', () => {
    const meeting = meetingsByKey.get(person.personKey);

    openMeetSheet({
      eventId,
      person,
      existing: personState(meeting) === 'none' ? null : meeting,
      onChange: (row) => {
        if (row) meetingsByKey.set(person.personKey, row);
        else meetingsByKey.delete(person.personKey);
        paint();

        // "Hide from plan" on a match: it leaves the lists now, not on the next visit.
        if (row?.status === 'skipped') watching?.rerender();
      },
    });
  });
}

// The list is kept on the phone too (roster-store.js): it opens at once from
// what was saved, the fresh list is swapped in when it arrives, and with no
// connection the saved one still works.
const rosterStore = createRosterStore({ kvGet, kvSet });

async function renderRoster(eventSlug, eventId) {
  const el = document.getElementById('people-roster');
  const searchEl = document.getElementById('roster-search');
  const saved = await rosterStore.read(eventSlug);
  let entries = saved?.entries ?? [];
  let noteEl = null;

  const showNote = (text) => {
    if (!noteEl) {
      noteEl = render('tpl-roster-note', { text });
      el.before(noteEl);
    } else {
      noteEl.textContent = text;
    }
  };
  const hideNote = () => {
    noteEl?.remove();
    noteEl = null;
  };

  const draw = () => {
    const q = searchEl.value.trim().toLowerCase();
    const list = q ? entries.filter((a) => a.name.toLowerCase().includes(q)) : entries;

    if (list.length === 0) {
      el.replaceChildren(render(entries.length === 0 ? 'tpl-roster-empty' : 'tpl-roster-no-match'));
      return;
    }

    const rows = document.createDocumentFragment();
    list.forEach((a) => rows.appendChild(rosterRow(a, eventId)));
    el.replaceChildren(rows);
  };

  // Roster data is other people's data (§8.4) — only "the search was used"
  // is reported, once, never what was typed.
  let searchReported = false;
  const wireSearch = () => {
    searchEl.addEventListener('input', () => {
      if (!searchReported) {
        searchReported = true;
        track('roster_search_use');
      }

      draw();
    });
  };

  let drawn = false;

  // The fresh list from the server, swapped in when it differs from what is showing.
  const applyFresh = async () => {
    const fresh = await fetchFullRoster(eventSlug);

    hideNote();

    if (!drawn || !sameRoster(fresh, entries)) {
      entries = fresh;
      draw();
      drawn = true;
    }
  };

  if (saved) {
    // What the phone already has, straight away — no waiting on the network.
    draw();
    drawn = true;
    wireSearch();
  }

  try {
    await applyFresh();

    // The photos too, in idle time, so the list looks right with no connection (avatar-cache.js).
    const saveThem = () => import('./avatar-cache.js').then(({ warmAvatars }) => warmAvatars(entries)).catch(() => {});
    if ('requestIdleCallback' in window) window.requestIdleCallback(saveThem, { timeout: 8000 });
    else setTimeout(saveThem, 2000);
  } catch {
    if (saved) {
      showNote(`Showing the attendee list saved on your phone (${savedWhen(saved.savedAt) || 'earlier'}). It updates when you're back online.`);
      window.addEventListener('online', () => applyFresh().catch(() => {}), { once: true });
      return;
    }

    // Offline is one thing; the server having a problem is another — say
    // which, and let them try again rather than leaving an empty list.
    el.replaceChildren(render(navigator.onLine === false ? 'tpl-roster-offline' : 'tpl-roster-error'));
    el.querySelector('[data-roster-retry]')?.addEventListener('click', () => {
      el.replaceChildren(document.createTextNode('Loading…'));
      renderRoster(eventSlug, eventId);
    });
    return;
  }

  if (!saved) wireSearch();
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

  // Kept on the phone for the next visit, and for when there is no connection.
  rosterStore.write(eventSlug, entries);

  return entries;
}

/** The list for the "pick my name" search: fresh when the network allows, else the one saved on the phone. */
async function rosterForPicker(eventSlug) {
  try {
    return await fetchFullRoster(eventSlug);
  } catch (error) {
    const saved = await rosterStore.read(eventSlug);

    if (saved) return saved.entries;
    throw error;
  }
}

function rosterRow(a, eventId) {
  const initial = (a.name ?? '?').trim().charAt(0).toUpperCase() || '?';

  // Only web addresses become links (the server filters too) — a javascript:
  // href would run in the app itself.
  const links = (a.links ?? []).filter((l) => /^https?:\/\//i.test(l.url ?? '')).map((l) =>
    render('tpl-roster-link', {
      link: {
        attrs: {
          href: l.url,
          'aria-label': SOCIAL_LABEL[l.type] ?? l.type,
          'data-track': 'roster_link_click',
          'data-track-link-type': SOCIAL_LABEL[l.type] ? l.type : 'website',
        },
        children: [render(`tpl-social-icon-${SOCIAL_LABEL[l.type] ? l.type : 'website'}`)],
      },
    })
  );

  const row = render('tpl-roster-row', {
    'avatar-img': a.gravatar_url ? { attrs: { src: a.gravatar_url } } : null,
    'avatar-initial': a.gravatar_url ? null : initial,
    name: a.name ?? '',
    'open-badge': Boolean(a.open_to_meet),
    links: links.length > 0 ? links : null,
  });

  wireMeetButton(row.querySelector('.meet-btn'), eventId, {
    personKey: `r:${a.id}`,
    name: a.name ?? '',
    avatarUrl: a.gravatar_url || null,
    sub: 'On the attendee list',
    source: 'roster',
    links: (a.links ?? []).filter((l) => /^https?:\/\//i.test(l.url ?? '')),
  });

  return row;
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
        showToast(`Pick up to ${MAX_DISCOVERY_TAGS} interests.`);
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

      track(existing ? 'discovery_update' : 'discovery_join', {
        surface: surfaceOf(options),
        // Which way they chose to appear — never the name or the entry itself.
        identity: { roster: 'attendee_list', typed: 'typed_name', anonymous: 'anonymous' }[identity],
        tag_count: body.tags.length,
      });

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
            track('discovery_name_taken');
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
  rosterForPicker(eventSlug)
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

    // Home: say so when matches have waved and are waiting for a wave back.
    Promise.all([apiGet(eventSlug, `/discovery/${mine.discoveryId}/waves`, mine.ownerToken), peopleStatus.load(eventId).catch(() => null)])
      .then(([state, loaded]) => {
        const n = (state?.received ?? []).filter((id) => !loaded || stateOfMatch(loaded, id) !== 'skipped').length;
        const link = el.querySelector('[data-track="home_discovery_explore_click"]');
        if (n > 0 && link) link.textContent = `👋 ${n} ${n === 1 ? 'match wants' : 'matches want'} to meet you →`;
      })
      .catch(() => {});
    return;
  }

  let profiles = [];
  let offline = false;
  let waves = { sent: [], received: [], mutual: [] };

  try {
    const [res, waveState] = await Promise.all([
      apiGet(eventSlug, '/discovery'),
      apiGet(eventSlug, `/discovery/${mine.discoveryId}/waves`, mine.ownerToken).catch(() => waves),
    ]);
    profiles = res.data ?? [];
    waves = waveState ?? waves;
  } catch {
    offline = true;
  }

  // Everyone's state comes from the one record My Day reads too (people-state.js),
  // so "I met them" here and Met there are the same thing.
  const loaded = await peopleStatus.load(eventId);
  meetingsByKey.clear();
  loaded.meetings.forEach((m) => meetingsByKey.set(m.personKey, m));
  repaintMeetButtons();
  const myTags = new Set(mine.fields.tags);
  const sent = new Set(waves.sent ?? []);
  const received = new Set(waves.received ?? []);
  const receivedWithMessage = new Set(waves.received_with_message ?? []);
  const mutualById = new Map((waves.mutual ?? []).map((m) => [m.discovery_id, m]));
  const pendingById = new Map((waves.pending ?? []).map((p) => [p.discovery_id, p]));
  const maxMessages = waves.max_messages ?? 3;
  const chat = waves.chat ?? { open: false };

  const others = profiles
    .filter((p) => p.discovery_id !== mine.discoveryId)
    .map((p) => {
      const mutual = mutualById.get(p.discovery_id);
      return {
        ...p,
        common: (p.fields.tags ?? []).filter((t) => myTags.has(t)),
        wave: mutual ? 'mutual' : sent.has(p.discovery_id) ? 'sent' : received.has(p.discovery_id) ? 'received' : null,
        revealed_name: mutual?.name ?? null,
        // The short thread (mutual), my unanswered first message (pending),
        // or just "they wrote something" (waiting for my wave back).
        convo: mutual
          ? { kind: 'mutual', ...mutual }
          : pendingById.get(p.discovery_id)?.messages?.length
            ? { kind: 'pending', messages: pendingById.get(p.discovery_id).messages }
            : receivedWithMessage.has(p.discovery_id)
              ? { kind: 'waiting' }
              : null,
      };
    });
  const stateOf = (p) => stateOfMatch(loaded, p.discovery_id);
  const notMet = others.filter((p) => ['none', 'planned'].includes(stateOf(p)));
  // Whoever waved at you first, then named people — they're the ones you can find.
  const byStrength = (a, b) => Number(b.wave === 'received') - Number(a.wave === 'received')
    || b.common.length - a.common.length
    || Number(Boolean(b.name)) - Number(Boolean(a.name));
  const mutual = notMet.filter((p) => p.wave === 'mutual');
  const matches = notMet.filter((p) => p.wave !== 'mutual' && (p.common.length > 0 || p.wave === 'received')).sort(byStrength);
  const rest = notMet.filter((p) => p.wave !== 'mutual' && p.common.length === 0 && p.wave !== 'received').sort(byStrength);
  const met = others.filter((p) => stateOf(p) === 'met');
  const missed = others.filter((p) => stateOf(p) === 'missed');
  const hidden = others.filter((p) => stateOf(p) === 'skipped');

  if (mutual.length > 0) track('discovery_mutual_view');

  const rerender = () => renderMatches(el, eventSlug, eventId, discoveryKey, mine, options);
  const tryAgain = async (p) => {
    await peopleStatus.setStatus(eventId, cardPerson(p), null);
    track('meet_status', { plan_status: 'cleared' });
    rerender();
  };
  const showAgain = async (p) => {
    await peopleStatus.unhide(eventId, cardPerson(p));
    track('discovery_unhide', { surface: surfaceOf(options) });
    rerender();
  };
  // ✕: out of the lists, everything kept (note, time, what they were) — "Show again" brings them back.
  const hideCard = async (p) => {
    if (p.wave === 'mutual' && !confirm("Hide? You'll stop seeing your chat with them.")) return;

    await peopleStatus.hide(eventId, cardPerson(p));
    track('discovery_hide', { surface: surfaceOf(options) });
    showToast("Hidden. You'll find them under Hidden below.");
    rerender();
  };
  const card = (p, isMet) => matchCard(p, isMet, eventId, {
    onHide: () => hideCard(p),
    onUndoMet: () => tryAgain(p),
    onWave: () => waveAt(p, mine, eventSlug, rerender, chat),
    convo: p.convo
      ? buildConvo(p.convo, {
          max: maxMessages,
          chat,
          cardUrl: `/event/${eventSlug}/camp-card`,
          onSend: async (body) => {
            await apiMutate(eventSlug, `/discovery/${mine.discoveryId}/messages`, 'POST', { to: p.discovery_id, body }, mine.ownerToken);
            track('discovery_message', { message_number: (p.convo.mine ?? 0) + 1 });
            rerender();
          },
        })
      : null,
  });

  el.replaceChildren(
    statusCard(mine),
    renderFragment('tpl-discovery-matches', {
      offline,
      empty: others.length === 0 && !offline,
      'mutual-section': mutual.length > 0,
      mutual: mutual.map((p) => card(p, false)),
      'matches-section': matches.length > 0,
      // Long lists start short (list-window.js); anyone waiting for a wave back always shows.
      matches: windowCards('matches', matches.map((p) => card(p, false)), { min: matches.filter((p) => p.wave === 'received').length }),
      'others-section': rest.length > 0,
      others: windowCards('others', rest.map((p) => card(p, false))),
      'met-section': met.length > 0,
      met: met.map((p) => card(p, true)),
    })
  );

  // People you planned and couldn't meet, folded away below the rest.
  if (missed.length > 0) {
    el.append(foldSection('missed', "Couldn't meet", missed.map((p) => personRow(p, 'Try again', () => tryAgain(p)))));
  }

  if (hidden.length > 0) {
    el.append(foldSection('hidden', 'Hidden', hidden.map((p) => personRow(p, 'Show again', () => showAgain(p)))));
  }

  scheduleChatFlip(chat, rerender);
  watchForChanges(eventId, rerender, peopleSignature(loaded.meetings, loaded.metIds));

  el.querySelectorAll('[data-met-id]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const profile = others.find((p) => p.discovery_id === btn.dataset.metId);
      if (!profile) return;

      await peopleStatus.setStatus(eventId, cardPerson(profile), 'met');
      track('discovery_met_mark');
      rerender();
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

function matchCard(profile, isMet, eventId, { onWave = null, onUndoMet = null, onHide = null, convo = null } = {}) {
  const { tags, profession, who_to_meet: whoToMeet } = profile.fields;
  const common = new Set(profile.common ?? []);
  const isWeb = (url) => /^https?:\/\//i.test(url ?? '');

  const links = (profile.links ?? []).filter((l) => isWeb(l.url)).map((l) =>
    render('tpl-roster-link', {
      link: {
        attrs: {
          href: l.url,
          'aria-label': `${SOCIAL_LABEL[l.type] ?? l.type}${profile.name ? ` — ${profile.name}` : ''}`,
          // Only the kind of link is reported, never the address (someone's profile).
          'data-track': 'discovery_profile_link_click',
          'data-track-link-type': SOCIAL_LABEL[l.type] ? l.type : 'website',
        },
        children: [render(`tpl-social-icon-${SOCIAL_LABEL[l.type] ? l.type : 'website'}`)],
      },
    })
  );

  const wave = profile.wave ?? null;
  const card = render('tpl-discovery-match', {
    card: { class: { 'person-card--mutual': wave === 'mutual', 'person-card--waved-you': wave === 'received' } },
    'waved-you': wave === 'received' && !isMet,
    convo: convo && !isMet ? [convo] : null,
    wave: isMet || wave === 'mutual' || !onWave
      ? null
      : {
          text: { sent: '👋 Waved', received: '👋 Wave back' }[wave] ?? '👋 Wave',
          class: { 'wave-btn--sent': wave === 'sent', 'wave-btn--back': wave === 'received' },
          attrs: { 'aria-pressed': String(wave === 'sent'), title: wave === 'sent' ? 'Tap to take your wave back' : null },
        },
    avatar: { attrs: { src: isWeb(profile.avatar_url) ? profile.avatar_url : '/media/illustrations/avatar.svg' } },
    name: profile.revealed_name || profile.name || 'Anonymous attendee',
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

  card.querySelector('.wave-btn')?.addEventListener('click', () => onWave?.());

  const meetBtn = card.querySelector('.meet-btn');
  if (isMet) {
    meetBtn.remove();

    // Marked by mistake, or met after all? One tap back — same as My Day's Met button.
    const label = card.querySelector('.person-card__met-label');
    if (label && onUndoMet) {
      const undo = document.createElement('button');
      undo.type = 'button';
      undo.className = 'person-card__undo';
      undo.textContent = 'Undo';
      undo.addEventListener('click', onUndoMet);
      label.after(undo);
    }
  } else {
    wireMeetButton(meetBtn, eventId, cardPerson(profile));
  }

  if (onHide && !isMet) {
    const hide = document.createElement('button');
    hide.type = 'button';
    hide.className = 'person-card__hide';
    hide.textContent = '✕';
    hide.title = 'Hide';
    hide.setAttribute('aria-label', `Hide ${profile.revealed_name || profile.name || 'this person'}`);
    hide.addEventListener('click', onHide);
    card.classList.add('person-card--hideable');
    card.append(hide);
  }

  return card;
}

/** A discovery match as the record that My Day and the Meet sheet keep about a person. */
function cardPerson(profile) {
  const isWeb = (url) => /^https?:\/\//i.test(url ?? '');
  const { tags, profession } = profile.fields;

  return {
    personKey: discoveryPersonKey(profile.discovery_id),
    name: profile.revealed_name || profile.name || 'Anonymous attendee',
    avatarUrl: isWeb(profile.avatar_url) ? profile.avatar_url : null,
    sub: [profession, (tags ?? []).join(', ')].filter(Boolean).join(' · ') || null,
    source: 'discovery',
    links: [...(profile.links ?? []), ...(isWeb(profile.wporg_url) ? [{ type: 'wporg', url: profile.wporg_url }] : [])].filter((l) => isWeb(l.url)),
  };
}

// Which folded sections are open, kept for this page visit so a re-render
// (after "Try again", say) doesn't shut them.
const foldOpen = {};

/** A section that starts closed: "Couldn't meet (2) ▸". Built here, not in a template. */
function foldSection(key, title, rows) {
  const wrap = document.createElement('div');
  wrap.className = 'people-fold';

  const head = document.createElement('button');
  head.type = 'button';
  head.className = 'people-fold__head';

  const body = document.createElement('div');
  body.className = 'people-fold__body';
  body.append(...rows);

  const paint = () => {
    const open = Boolean(foldOpen[key]);
    head.textContent = `${title} (${rows.length}) ${open ? '▾' : '▸'}`;
    head.setAttribute('aria-expanded', String(open));
    body.hidden = !open;
  };
  head.addEventListener('click', () => {
    foldOpen[key] = !foldOpen[key];
    paint();
  });
  paint();

  wrap.append(head, body);

  return wrap;
}

/** One person as a compact row with a single button ("Try again", "Show again"). */
function personRow(profile, buttonLabel, onClick) {
  const person = cardPerson(profile);
  const row = document.createElement('div');
  row.className = 'people-row';

  const avatar = document.createElement('img');
  avatar.className = 'people-row__avatar';
  avatar.alt = '';
  avatar.width = 40;
  avatar.height = 40;
  avatar.loading = 'lazy';
  avatar.dataset.fallback = '/media/illustrations/avatar.svg';
  avatar.src = person.avatarUrl ?? '/media/illustrations/avatar.svg';

  const text = document.createElement('div');
  text.className = 'people-row__text';
  const name = document.createElement('p');
  name.className = 'people-row__name';
  name.textContent = person.name;
  text.append(name);
  if (profile.fields.profession) {
    const sub = document.createElement('p');
    sub.className = 'people-row__sub';
    sub.textContent = profile.fields.profession;
    text.append(sub);
  }

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'btn btn--compact btn--outline';
  button.textContent = buttonLabel;
  button.setAttribute('aria-label', `${buttonLabel}: ${person.name}`);
  button.addEventListener('click', onClick);

  row.append(avatar, text, button);

  return row;
}

// The lists are drawn once when the page opens. If the people records change
// somewhere else (My Day, another tab) while this page is in the background,
// coming back redraws them, only when something really changed, so nothing
// jumps for no reason.
let watching = null;
let lastSignature = '';
let watchBound = false;

function watchForChanges(eventId, rerender, signature) {
  watching = { eventId, rerender };
  lastSignature = signature;

  if (watchBound) return;
  watchBound = true;

  const check = async () => {
    if (document.visibilityState !== 'visible' || !watching) return;

    try {
      const loaded = await peopleStatus.load(watching.eventId);
      if (peopleSignature(loaded.meetings, loaded.metIds) !== lastSignature) watching.rerender();
    } catch {
      // Storage blocked: nothing to compare, leave the page as it is.
    }
  };

  document.addEventListener('visibilitychange', check);
  window.addEventListener('pageshow', (e) => {
    if (e.persisted) check();
  });
}

/**
 * 👋 Wave (or take a wave back). Waving asks for a first name only when the
 * profile is anonymous — it's shown to the other person only if they wave back.
 */
async function waveAt(profile, mine, eventSlug, rerender, chat = { open: false }) {
  const base = `/discovery/${mine.discoveryId}/waves`;

  if (profile.wave === 'sent') {
    try {
      await apiMutate(eventSlug, `${base}/${profile.discovery_id}`, 'DELETE', null, mine.ownerToken);
      track('discovery_wave_undo');
      showToast('Wave taken back.');
    } catch {
      showToast("Couldn't reach the server — try again in a moment.");
    }
    rerender();
    return;
  }

  const myName = mine.card?.name ?? mine.fields?.display_name ?? null;
  const dialog = render('tpl-wave-sheet', {
    title: profile.name || `A match who's into ${(profile.common?.length ? profile.common : profile.fields.tags ?? []).slice(0, 2).join(' & ') || 'WordPress'}`,
    'name-field': !myName,
    // Words only while the chat is open; a wave on its own works any time.
    'message-field': Boolean(chat.open),
    'closed-note': chat.open ? null : chatClosedText(chat),
  });
  document.body.appendChild(dialog);

  const nameEl = dialog.querySelector('#wave-name');
  const messageEl = dialog.querySelector('#wave-message'); // absent while the chat is closed
  const errorEl = dialog.querySelector('[data-wave-error]');
  if (nameEl) nameEl.value = readSavedWaveName();

  const close = () => {
    dialog.close();
    dialog.remove();
  };
  dialog.querySelector('[data-wave-close]').addEventListener('click', close);
  dialog.addEventListener('cancel', (e) => {
    e.preventDefault();
    close();
  });
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) close();
  });

  const sendBtn = dialog.querySelector('[data-wave-send]');
  sendBtn.addEventListener('click', async () => {
    const name = nameEl?.value.trim() ?? '';
    if (!myName && name.length < 2) {
      errorEl.textContent = 'Add your first name — they only see it if they wave back.';
      errorEl.hidden = false;
      nameEl.focus();
      return;
    }

    sendBtn.disabled = true;
    try {
      const state = await apiMutate(eventSlug, base, 'POST', { to: profile.discovery_id, name: name || null, message: messageEl?.value.trim() || null }, mine.ownerToken);
      if (name) saveWaveName(name);
      track('discovery_wave', { surface: 'explore' });
      close();
      const nowMutual = (state?.mutual ?? []).some((m) => m.discovery_id === profile.discovery_id);
      showToast(nowMutual ? '🎉 You both waved — names revealed!' : 'Wave sent. If they wave back, you\'ll both see names.');
      rerender();
    } catch (error) {
      sendBtn.disabled = false;
      errorEl.textContent = error.userMessage ?? "Couldn't send — check your connection and try again.";
      errorEl.hidden = false;
    }
  });

  dialog.showModal();
  (nameEl && !nameEl.value ? nameEl : messageEl ?? dialog.querySelector('[data-wave-send]')).focus();
}

// The name given with a wave, remembered on this device so it isn't retyped.
function readSavedWaveName() {
  try {
    return localStorage.getItem('campbuddy:wave-name') ?? '';
  } catch {
    return '';
  }
}

function saveWaveName(name) {
  try {
    localStorage.setItem('campbuddy:wave-name', name);
  } catch {
    // Retyped next time.
  }
}

/**
 * The short thread two matches may exchange: three messages each, taking
 * turns. The server enforces the rules; this shows where things stand —
 * "Message 2 of 3 sent", waiting for their reply, or the limit reached with
 * a nudge to swap Camp Cards instead.
 */
function buildConvo(convo, { max, chat, cardUrl, onSend }) {
  const messages = convo.messages ?? [];
  const days = [...new Set(messages.map((m) => m.day).filter(Boolean))].sort();
  const multiDay = days.length > 1;
  const counters = new Map();

  const bubbles = [];
  let lastDay = null;
  for (const m of messages) {
    if (multiDay && m.day !== lastDay) {
      bubbles.push(render('tpl-convo-day', { label: dayLabel(m.day) }));
      lastDay = m.day;
    }
    const key = `${m.day}|${m.mine ? 'me' : 'them'}`;
    const n = (counters.get(key) ?? 0) + 1;
    counters.set(key, n);
    bubbles.push(
      render('tpl-convo-bubble', {
        bubble: { class: { 'convo__bubble--mine': m.mine } },
        text: m.body,
        meta: m.mine ? `Message ${n} of ${max} · sent ✓` : `Their message ${n} of ${max}`,
      })
    );
  }

  const moreDaysLeft = chat.day_number && chat.days && chat.day_number < chat.days;
  let status = null;
  if (convo.kind === 'waiting') {
    status = '💬 They sent you a message — wave back to read it.';
  } else if (convo.kind === 'pending') {
    status = 'Waiting for them to wave back — then you can both see names and reply.';
  } else if (!chat.open) {
    status = chatClosedText(chat);
  } else if (convo.reason === 'waiting') {
    status = `⏳ Sent. You can write again after they reply (${convo.mine} of ${max} used today).`;
  } else if (convo.reason === 'limit') {
    status = `That's all ${max} of today's messages.${moreDaysLeft ? ' You get 3 more tomorrow.' : ''} To keep in touch, share your Camp Card — or add them to your plan with + Meet.`;
  }

  const canSend = convo.kind === 'mutual' && chat.open && convo.can_send;
  const next = (convo.mine ?? 0) + 1;
  const el = render('tpl-convo', {
    open: convo.kind === 'mutual' && chat.open
      ? `🟢 Chat open until ${chat.closes_label} (event time)${chat.days > 1 ? ` · Day ${chat.day_number} of ${chat.days}` : ''} · ${max} messages each today`
      : null,
    hint: convo.kind === 'mutual' && chat.open && messages.length === 0 ? 'Agree where to meet — take turns, short and sweet.' : null,
    list: bubbles.length ? bubbles : null,
    composer: canSend,
    label: canSend ? `Message ${next} of ${max} today` : null,
    input: canSend ? { attrs: { placeholder: `Message ${next} of ${max} — e.g. Meet at the sponsor hall?` } } : null,
    status,
    'card-link': convo.kind === 'mutual' && (convo.reason === 'limit' || chat.ended) ? { attrs: { href: cardUrl } } : null,
  });

  const form = el.querySelector('form');
  if (form) {
    const input = form.querySelector('input');
    const button = form.querySelector('button');
    const statusEl = el.querySelector('.convo__status') ?? el.appendChild(Object.assign(document.createElement('p'), { className: 'convo__status' }));
    input.id = `convo-${Math.random().toString(36).slice(2)}`;
    form.querySelector('label').htmlFor = input.id;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const body = input.value.trim();
      if (!body) {
        input.focus();
        return;
      }
      button.disabled = true;
      input.disabled = true;
      try {
        await onSend(body);
      } catch (error) {
        button.disabled = false;
        input.disabled = false;
        statusEl.textContent = error.userMessage ?? "Couldn't send — check your connection and try again.";
      }
    });
  }

  return el;
}

/** Why the chat is closed and when it opens — in the event's own time. */
function chatClosedText(chat) {
  if (chat.opens_label) return `🌙 Chat is closed now. It opens ${chat.opens_label} (event time) — an hour before the first session.`;
  if (chat.ended) return 'The event is over, so the chat is closed. Use Camp Cards to stay in touch.';
  return 'The chat opens during the event, once its schedule is published.';
}

function dayLabel(ymd) {
  const [y, m, d] = String(ymd).split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'short' });
}

// Re-draw at the moment the chat opens or closes, so an app left open
// never shows a send box after closing time (the server refuses it anyway).
let chatFlipTimer = null;

function scheduleChatFlip(chat, rerender) {
  clearTimeout(chatFlipTimer);
  const at = Date.parse(chat.open ? chat.closes_at : chat.opens_at ?? '');
  if (!Number.isFinite(at)) return;

  const wait = at - Date.now() + 1500;
  // Long waits are left to the next visit (and the app's own refresh).
  if (wait > 0 && wait < 12 * 60 * 60 * 1000) {
    chatFlipTimer = setTimeout(rerender, wait);
  }
}
