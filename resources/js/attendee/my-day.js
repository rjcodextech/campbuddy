// My Day: Full schedule + My schedule, grouped by calendar day so
// multi-day events never blur into one long list (MD1), and the overlap
// warning (MD4) — bookmarking two overlapping sessions is always
// allowed, this only ever warns, never blocks.
//
// Session detail is an inline accordion under the tapped session, not a
// popup — no <dialog> anywhere in this module. Only one session's detail
// is open at a time; expandedSessionId lives in this closure and is
// threaded through every render so it survives a re-render triggered by
// a filter/search/bookmark change instead of silently closing.

import { track } from './analytics.js';
import { buildIcs, deliverIcs, eventFacts } from './calendar.js';
import { eventDayKey, eventTimeNote, formatDayKey, formatDayTime, formatTime } from './eventtime.js';
import { getBookmarks, getMeetings, removeBookmark, setBookmark, updateBookmark } from './db.js';
import { meetingCalendarItem, openMeetSheet } from './meet-sheet.js';
import { computePlan } from './plan.js';
import { LABELS, effectiveFilter, filterCounts, isPlanned, matchesFilter, startingFilter, visibleFilters } from './people-state.js';
import { peopleStatus } from './people-status.js';
import { onPeopleChanged } from './people-sync.js';
import { setSectionTitle } from './page-title.js';
import { cancelReminder, offerReminder } from './push.js';
import { render, renderFragment } from './template.js';
import { showToast } from './toast.js';

export async function renderMyDay(root) {
  const dataEl = document.getElementById('my-day-data');
  if (!dataEl) return;

  const { sessions, speakers } = JSON.parse(dataEl.textContent);
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  const speakersById = new Map(speakers.map((s) => [s.id, s]));

  let bookmarks = await getBookmarks(eventId);
  let bookmarkedIds = new Set(bookmarks.map((b) => b.sessionId));
  let meetings = await safeMeetings(eventId);
  let expandedSessionId = null;
  let planFilter = startingFilter(readPref('campbuddy:plan-filter'), readPref('campbuddy:plan-hide-done') === '1');

  // Every session is listed — including ones the WordCamp site hasn't given
  // a time yet (common weeks before the event, when talks are announced
  // before the timetable). Those sort last, in a "Time to be announced"
  // group, instead of the whole schedule looking empty.
  const timed = sessions
    .map((s) => {
      const ms = s.starts_at ? new Date(s.starts_at).getTime() : NaN;
      const known = Number.isFinite(ms);
      return { ...s, startMs: known ? ms : Number.POSITIVE_INFINITY, dayKey: known ? dayKeyOf(ms) : TBA };
    })
    .sort((a, b) => (a.startMs === b.startMs ? (a.title ?? '').localeCompare(b.title ?? '') : a.startMs < b.startMs ? -1 : 1));

  const sessionsById = new Map(timed.map((s) => [s.id, s]));

  // Saved sessions carry their title and times, so the "things left today"
  // reminder on other screens knows when each one is over.
  for (const b of bookmarks) {
    const s = sessionsById.get(b.sessionId);
    if (s && Number.isFinite(s.startMs) && (b.startMs !== s.startMs || b.title !== s.title)) {
      updateBookmark(eventId, b.sessionId, sessionMeta(s)).catch(() => {});
    }
  }

  // Someone whose phone isn't on event time is told the times are the venue's.
  const note = eventTimeNote();
  if (note && !document.getElementById('event-time-note')) {
    const p = document.createElement('p');
    p.id = 'event-time-note';
    p.className = 'notice';
    p.textContent = `🕒 ${note}`;
    document.querySelector('#main-content .section-head')?.after(p);
  }

  setupTabs();
  setupDayFilters(timed);
  const trackNames = setupChipFilter('track-filters', timed, (s) => s.track_names ?? []);
  const typeNames = setupChipFilter('type-filters', timed, (s) => (s.session_type ? [s.session_type] : []), typeFilterLabel);
  setupChipFilter('topic-filters', timed, (s) => s.category_names ?? []);
  let activeDay = null;
  let activeTrack = null;
  let activeType = null;
  let activeTopic = null;
  let query = '';

  const fullListEl = document.getElementById('full-schedule-list');
  const mineListEl = document.getElementById('my-schedule-list');
  const searchEl = document.getElementById('session-search');

  // The first bookmarked session this one overlaps (or undefined) — callers
  // use it both as a yes/no and to name the clash in the toast/notice.
  function bookmarkedOverlap(session, excludeId = null) {
    return timed.find(
      (other) =>
        other.id !== session.id &&
        other.id !== excludeId &&
        bookmarkedIds.has(other.id) &&
        rangesOverlap(session, other)
    );
  }

  function rangesOverlap(a, b) {
    if (!Number.isFinite(a.startMs) || !Number.isFinite(b.startMs)) return false;
    const aEnd = a.startMs + (a.duration_seconds ?? 0) * 1000;
    const bEnd = b.startMs + (b.duration_seconds ?? 0) * 1000;
    return a.startMs < bEnd && b.startMs < aEnd;
  }

  async function toggleBookmark(session, starButton) {
    if (bookmarkedIds.has(session.id)) {
      bookmarkedIds.delete(session.id);
      const saved = (await getBookmarks(eventId)).find((b) => b.sessionId === session.id);
      await removeBookmark(eventId, session.id);
      cancelReminder(eventSlug, saved);
      starButton.classList.remove('schedule-item__star--saved');
      track('session_unsave', { schedule_session_id: session.id, session_title: session.title });
    } else {
      const conflict = bookmarkedOverlap(session);
      await setBookmark(eventId, session.id, false, sessionMeta(session));
      bookmarkedIds.add(session.id);
      starButton.classList.add('schedule-item__star--saved');
      track('session_save', { schedule_session_id: session.id, session_title: session.title, overlap: Boolean(conflict) });

      if (conflict) {
        showToast(`Heads up — this overlaps with ${conflict.title ?? 'another saved session'}. Both are saved.`);
      }

      // N1: ask right after the bookmark, at the moment the benefit is
      // obvious — never on page load.
      offerReminder(eventSlug, eventId, session.id);
    }

    bookmarks = await getBookmarks(eventId);
    renderFull();
    renderMine();
  }

  function toggleExpand(sessionId) {
    expandedSessionId = expandedSessionId === sessionId ? null : sessionId;

    if (expandedSessionId !== null) {
      track('session_expand', { schedule_session_id: sessionId, session_title: timed.find((s) => s.id === sessionId)?.title });
    }

    renderFull();
    renderMine();
  }

  // Returns how many sessions matched, for schedule_search's results_count.
  function renderFull() {
    const filtered = timed.filter((s) => {
      const matchesDay = !activeDay || s.dayKey === activeDay;
      const matchesTrack = !activeTrack || (s.track_names ?? []).includes(activeTrack);
      const matchesType = !activeType || s.session_type === activeType;
      const matchesTopic = !activeTopic || (s.category_names ?? []).includes(activeTopic);
      const haystack = `${s.title} ${(s.speaker_ids ?? []).map((id) => speakersById.get(id)?.name ?? '').join(' ')}`.toLowerCase();
      const matchesQuery = !query || haystack.includes(query);
      return matchesDay && matchesTrack && matchesType && matchesTopic && matchesQuery;
    });

    renderGroupedByDay(fullListEl, filtered, sessions.length === 0 ? 'tpl-my-day-no-schedule' : 'tpl-my-day-empty-full');
    updateMoreFiltersCount([activeTrack, activeType, activeTopic].filter(Boolean).length);
    wireItemInteractions(fullListEl, timed, toggleBookmark, toggleExpand);
    return filtered.length;
  }

  function renderMine() {
    const plan = computePlan(bookmarks, meetings, sessionsById);
    const statusById = new Map(bookmarks.map((b) => [b.sessionId, b.status ?? null]));

    // The chips count exactly the cards each one shows (people-state.js). A chip
    // that has just been emptied is no longer shown, so the page falls back to "All".
    const counts = filterCounts([...plan.sessions, ...plan.people, ...plan.hidden]);
    planFilter = effectiveFilter(planFilter, counts);

    renderPlanSummary(plan, counts);
    renderPeople(plan);

    const itemOf = new Map(plan.sessions.map((i) => [i.id, i]));
    const mine = planFilter === 'hidden'
      ? []
      : timed.filter((s) => bookmarkedIds.has(s.id) && matchesFilter(itemOf.get(s.id), planFilter));

    if (planFilter === 'hidden') {
      mineListEl.replaceChildren();
    } else if (mine.length === 0 && bookmarkedIds.size > 0) {
      mineListEl.replaceChildren(filterEmptyNote(planFilter === 'todo' ? 'Nothing left to do here: all your saved sessions are done ✓' : 'No saved sessions in this filter.'));
    } else {
      renderGroupedByDay(mineListEl, mine, 'tpl-my-day-empty-mine', (s) => bookmarkedOverlap(s, null), statusById);
    }
    wireItemInteractions(mineListEl, timed, toggleBookmark, toggleExpand);

    mineListEl.querySelectorAll('[data-status]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = Number(btn.closest('.schedule-item').dataset.sessionId);
        const next = statusById.get(id) === btn.dataset.status ? null : btn.dataset.status;
        await updateBookmark(eventId, id, { status: next });
        bookmarks = await getBookmarks(eventId);
        track('session_status', { plan_status: next ?? 'cleared' });
        renderMine();
      });
    });
  }

  // "All 5 · To do 2 · Done 2 · Couldn't 1": only chips that have something, the chosen one ticked.
  function filterChips(counts) {
    const group = document.createElement('div');
    group.className = 'chip-group plan-filters';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', 'Show in My schedule');

    visibleFilters(counts).forEach((f) => {
      const on = f.id === planFilter;
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = `chip${on ? ' chip--selected' : ''}`;
      chip.setAttribute('aria-pressed', String(on));
      chip.dataset.planFilter = f.id;
      chip.textContent = `${f.label} ${counts[f.id]}`;
      chip.addEventListener('click', () => {
        planFilter = f.id;
        writePref('campbuddy:plan-filter', f.id);
        track('schedule_filter', { filter_type: 'status', filter_value: f.id });
        renderMine();
      });
      group.append(chip);
    });

    return group;
  }

  function renderPlanSummary(plan, counts) {
    const el = document.getElementById('plan-summary');
    if (!el) return;

    if (counts.all + counts.hidden === 0) {
      el.replaceChildren();
      return;
    }

    // Only people (met on Explore, or hidden) and nothing planned: no progress to show, just the chips.
    if (plan.total === 0) {
      el.replaceChildren(filterChips(counts));
      return;
    }

    const pct = Math.round((plan.done / plan.total) * 100);
    const card = render('tpl-plan-summary', {
      count: plan.left === 0
        ? `All ${plan.total} done — nice work! 🎉`
        : `${plan.done} of ${plan.total} done · ${plan.left} left`,
      ring: `${pct}%`,
      bar: { attrs: { 'aria-valuemax': String(plan.total), 'aria-valuenow': String(plan.done), 'aria-label': 'Plan progress' } },
      fill: { attrs: { style: `width:${pct}%` } },
    });

    // The chips take the place of the old "Hide done" chip ("To do" does what it did).
    card.querySelector('[data-plan-hide-done]')?.remove();
    card.querySelector('.plan-summary__actions')?.before(filterChips(counts));

    card.querySelector('[data-plan-calendar]').addEventListener('click', () => {
      const facts = eventFacts();
      const items = [
        ...timed
          .filter((s) => bookmarkedIds.has(s.id) && Number.isFinite(s.startMs))
          .map((s) => ({
            uid: `session-${facts.slug}-${s.id}`,
            title: s.title,
            startMs: s.startMs,
            endMs: s.startMs + (s.duration_seconds ? s.duration_seconds * 1000 : 30 * 60 * 1000),
            description: [(s.speaker_ids ?? []).map((id) => speakersById.get(id)?.name).filter(Boolean).join(', '), s.link].filter(Boolean).join('\n'),
            location: [s.track_names?.[0], facts.venue].filter(Boolean).join(', ') || undefined,
            url: s.link || undefined,
            alarmMinutes: 10,
          })),
        ...meetings.filter(isPlanned).map((m) => meetingCalendarItem(m, facts)),
      ];

      if (items.length === 0) {
        showToast('Nothing with a time yet — save a session or add someone to meet first.');
        return;
      }

      deliverIcs(`${facts.slug}-my-plan`, buildIcs(items));
      track('calendar_export', { scope: 'plan', method: 'ics' });
    });

    el.replaceChildren(card);
  }

  function renderPeople(plan) {
    const el = document.getElementById('plan-people');
    if (!el) return;

    const byTime = (a, b) => Number(a.done) - Number(b.done)
      || (Number.isFinite(a.startMs) ? a.startMs : Infinity) - (Number.isFinite(b.startMs) ? b.startMs : Infinity)
      || a.title.localeCompare(b.title);
    const hiddenView = planFilter === 'hidden';
    const people = hiddenView
      ? [...plan.hidden].sort((a, b) => a.title.localeCompare(b.title))
      : plan.people.filter((p) => matchesFilter(p, planFilter)).sort(byTime);

    const section = render('tpl-plan-people', {
      items: people.map((p) => (hiddenView ? hiddenPersonCard(p.meeting) : personCard(p.meeting))),
      empty: plan.people.length === 0 && plan.hidden.length === 0,
      explore: { attrs: { href: `/event/${eventSlug}/explore` } },
    });
    if (hiddenView) section.querySelector('#plan-people-heading').textContent = `${LABELS.hidden} people`;
    if (people.length === 0 && (plan.people.length > 0 || plan.hidden.length > 0)) {
      section.append(filterEmptyNote('No one in this filter.'));
    }
    el.replaceChildren(section);

    // "Show again": back to what it was, with the note and time it had.
    el.querySelectorAll('[data-person-show]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const meeting = meetings.find((m) => m.personKey === btn.closest('[data-hidden-key]').dataset.hiddenKey);
        if (!meeting) return;

        await peopleStatus.unhide(eventId, meeting);
        track('meet_unhide', { source: meeting.source ?? 'roster' });
        meetings = await safeMeetings(eventId);
        renderMine();
      });
    });

    el.querySelectorAll('[data-person-key]').forEach((card) => {
      const meeting = meetings.find((m) => m.personKey === card.dataset.personKey);
      if (!meeting) return;

      card.querySelectorAll('[data-person-status]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const next = meeting.status === btn.dataset.personStatus ? null : btn.dataset.personStatus;
          // The same record Explore reads, so the match card there says the same.
          await peopleStatus.setStatus(eventId, meeting, next);
          meetings = await safeMeetings(eventId);
          track('meet_status', { plan_status: next ?? 'cleared' });
          renderMine();
        });
      });

      card.querySelector('[data-person-edit]').addEventListener('click', () => {
        openMeetSheet({
          eventId,
          person: { personKey: meeting.personKey, name: meeting.name, avatarUrl: meeting.avatarUrl, sub: meeting.sub, source: meeting.source, links: meeting.links },
          existing: meeting,
          onChange: async () => {
            meetings = await safeMeetings(eventId);
            renderMine();
          },
        });
      });
    });
  }

  function renderGroupedByDay(container, list, emptyTemplateId, overlapFn, statusById = null) {
    if (list.length === 0) {
      container.replaceChildren(render(emptyTemplateId));
      return;
    }

    const days = [...new Set(list.map((s) => s.dayKey))];
    // A "Time to be announced" group always gets its heading — it explains
    // why those sessions have no time.
    const showHeadings = days.length > 1 || days.includes(TBA);

    container.replaceChildren(
      ...days.map((day) => {
        const dayItems = list.filter((s) => s.dayKey === day);
        return render('tpl-schedule-day', {
          heading: showHeadings ? (day === TBA ? 'Time to be announced' : dayLabelOf(dayItems[0].startMs)) : null,
          items: dayItems.map((s) => sessionItem(s, speakersById, bookmarkedIds, overlapFn?.(s), expandedSessionId, statusById)),
        });
      })
    );
  }

  // Reported once typing pauses, and only the length + hit count: what an
  // attendee types could be anyone's name, so the text itself stays local.
  let searchTimer;

  searchEl.addEventListener('input', () => {
    query = searchEl.value.trim().toLowerCase();
    const resultsCount = renderFull();

    clearTimeout(searchTimer);
    if (query.length >= 2) {
      searchTimer = setTimeout(() => track('schedule_search', { query_length: query.length, results_count: resultsCount }), 1000);
    }
  });

  document.getElementById('day-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-day]');
    if (!btn) return;
    activeDay = btn.dataset.day === '__all' ? null : (btn.dataset.day === activeDay ? null : btn.dataset.day);
    document.querySelectorAll('#day-filters [data-day]').forEach((b) => setPressed(b, b.dataset.day === (activeDay ?? '__all')));
    track('schedule_filter', { filter_type: 'day', filter_value: activeDay ? btn.textContent : 'all' });
    renderFull();
  });

  document.getElementById('track-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chip]');
    if (!btn) return;
    activeTrack = btn.dataset.chip === activeTrack ? null : btn.dataset.chip;
    document.querySelectorAll('#track-filters [data-chip]').forEach((b) => setPressed(b, b.dataset.chip === activeTrack));
    track('schedule_filter', { filter_type: 'track', filter_value: activeTrack ?? 'all' });
    renderFull();
  });

  document.getElementById('type-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chip]');
    if (!btn) return;
    activeType = btn.dataset.chip === activeType ? null : btn.dataset.chip;
    document.querySelectorAll('#type-filters [data-chip]').forEach((b) => setPressed(b, b.dataset.chip === activeType));
    track('schedule_filter', { filter_type: 'type', filter_value: activeType ?? 'all' });
    renderFull();
  });

  document.getElementById('topic-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chip]');
    if (!btn) return;
    activeTopic = btn.dataset.chip === activeTopic ? null : btn.dataset.chip;
    document.querySelectorAll('#topic-filters [data-chip]').forEach((b) => setPressed(b, b.dataset.chip === activeTopic));
    track('schedule_filter', { filter_type: 'topic', filter_value: activeTopic ?? 'all' });
    renderFull();
  });

  renderFull();
  renderMine();

  // Coming back to the app (or from the meet sheet elsewhere): the clock has
  // moved on and more may be done — refresh the plan.
  document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState !== 'visible') return;
    bookmarks = await getBookmarks(eventId);
    meetings = await safeMeetings(eventId);
    renderMine();
  });

  // Someone marked met / hidden / planned in another window (a second tab, the installed app): follow now.
  onPeopleChanged(async (changed) => {
    if (changed !== eventId) return;
    meetings = await safeMeetings(eventId);
    renderMine();
  });

  // "#mine" (the reminder's link) opens straight on My schedule.
  if (location.hash === '#mine') {
    document.querySelector('[data-view-tab="mine"]')?.click();
  }
}

function sessionMeta(s) {
  return {
    title: s.title ?? '',
    startMs: Number.isFinite(s.startMs) ? s.startMs : null,
    endMs: Number.isFinite(s.startMs) ? s.startMs + (s.duration_seconds ? s.duration_seconds * 1000 : 30 * 60 * 1000) : null,
  };
}

async function safeMeetings(eventId) {
  try {
    return await getMeetings(eventId);
  } catch {
    return [];
  }
}

function readPref(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function writePref(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Just not remembered.
  }
}

/** One line saying why a list is empty under the chosen chip. */
function filterEmptyNote(text) {
  const p = document.createElement('p');
  p.className = 'plan-empty';
  p.textContent = text;

  return p;
}

/** A person hidden with ✕: name, their note is kept, and one button to bring them back. */
function hiddenPersonCard(m) {
  const card = document.createElement('article');
  card.className = 'plan-person';
  // Not data-person-key: the Met / Couldn't / Edit wiring of the normal cards must not pick this one up.
  card.dataset.hiddenKey = m.personKey;

  const avatar = document.createElement('img');
  avatar.className = 'plan-person__avatar';
  avatar.alt = '';
  avatar.width = 44;
  avatar.height = 44;
  avatar.loading = 'lazy';
  avatar.dataset.fallback = '/media/illustrations/avatar.svg';
  avatar.src = /^https?:\/\//i.test(m.avatarUrl ?? '') ? m.avatarUrl : '/media/illustrations/avatar.svg';

  const body = document.createElement('div');
  body.className = 'plan-person__body';

  const name = document.createElement('p');
  name.className = 'plan-person__name';
  name.textContent = m.name || 'Anonymous attendee';
  body.append(name);

  if (m.note) {
    const note = document.createElement('p');
    note.className = 'plan-person__note';
    note.textContent = m.note;
    body.append(note);
  }

  const actions = document.createElement('div');
  actions.className = 'plan-person__actions';
  const show = document.createElement('button');
  show.type = 'button';
  show.className = 'btn btn--compact btn--outline';
  show.dataset.personShow = '';
  show.textContent = LABELS.showAgain;
  show.setAttribute('aria-label', `${LABELS.showAgain}: ${m.name || 'this person'}`);
  actions.append(show);
  body.append(actions);

  card.append(avatar, body);

  return card;
}

function personCard(m) {
  // Someone marked "I met them" on a match was never planned: no time to show.
  const when = m.at
    ? formatDayTime(new Date(m.at).getTime())
    : (m.unplanned ? 'Met at the event' : 'Any time');
  const state = { met: LABELS.met, missed: LABELS.missed }[m.status] ?? null;

  return render('tpl-plan-person', {
    card: { attrs: { 'data-person-key': m.personKey }, class: { 'plan-person--done': Boolean(m.status) } },
    avatar: { attrs: { src: /^https?:\/\//i.test(m.avatarUrl ?? '') ? m.avatarUrl : '/media/illustrations/avatar.svg' } },
    name: m.name || 'Anonymous attendee',
    state,
    when: m.unplanned && !m.at ? when : `🕒 ${when}`,
    note: m.note || null,
    met: { text: LABELS.met, attrs: { 'aria-pressed': String(m.status === 'met') }, class: { 'plan-status__btn--on': m.status === 'met' } },
    missed: { text: LABELS.missedButton, attrs: { 'aria-pressed': String(m.status === 'missed') }, class: { 'plan-status__btn--on': m.status === 'missed' } },
  });
}

// The "More filters" toggle says how many of its filters are on, so a
// folded-away filter never silently hides sessions.
function updateMoreFiltersCount(count) {
  const badge = document.getElementById('more-filters-count');
  const panel = document.getElementById('more-filters');
  if (!badge || !panel) return;

  badge.textContent = String(count);
  badge.hidden = count === 0;
  // Nothing to filter by (e.g. a one-track event with no types or topics).
  panel.hidden = ['track-filters', 'type-filters', 'topic-filters'].every((id) => document.getElementById(id).hidden);
}

// WordPress's own session types, in words a first-timer understands.
const TYPE_LABELS = { session: 'Talk', custom: 'Activity' };
const TYPE_FILTER_LABELS = { session: 'Talks', custom: 'Breaks & activities' };

function typeLabel(type) {
  return TYPE_LABELS[type] ?? (type ? type.charAt(0).toUpperCase() + type.slice(1) : null);
}

function typeFilterLabel(type) {
  return TYPE_FILTER_LABELS[type] ?? typeLabel(type);
}

function isBeginnerFriendly(session) {
  return (session.category_names ?? []).some((c) => /beginner|introduct|getting started|101/i.test(c));
}

// A filter chip's on/off state, told to the eye (class) and to assistive tech (aria-pressed).
function setPressed(chip, on) {
  chip.classList.toggle('chip--selected', on);
  chip.setAttribute('aria-pressed', String(on));
}

// The day key of sessions with no time yet.
const TBA = 'tba';

// Days and times are the event's own (eventtime.js), not the phone's.
function dayKeyOf(ms) {
  return eventDayKey(ms);
}

function dayLabelOf(ms) {
  return formatDayKey(eventDayKey(ms));
}

function setupTabs() {
  document.querySelectorAll('[data-view-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-view-tab]').forEach((b) => {
        b.setAttribute('aria-selected', String(b === btn));
        b.classList.toggle('btn--outline', b !== btn);
      });
      document.querySelectorAll('[data-view-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.viewPanel !== btn.dataset.viewTab;
      });
      setSectionTitle(btn.textContent.trim());
      track('schedule_view_switch', { view: btn.dataset.viewTab });
    });
  });
}

// MD1/day filter: only shown when the event actually spans more than one
// day — a single-day WordCamp has nothing to filter by.
function setupDayFilters(sessions) {
  const el = document.getElementById('day-filters');
  const firstStart = (day) => sessions.find((s) => s.dayKey === day).startMs;
  const days = [...new Set(sessions.map((s) => s.dayKey))].sort((a, b) => (firstStart(a) < firstStart(b) ? -1 : 1));

  if (days.length < 2) {
    el.hidden = true;
    return;
  }

  el.hidden = false;
  const allChip = render('tpl-my-day-filter-chip', {
    chip: { text: 'All days', attrs: { 'data-day': '__all', 'aria-pressed': 'true' }, class: { 'chip--selected': true } },
  });
  const dayChips = days.map((day) => {
    const label = day === TBA
      ? 'Time TBA'
      : formatDayKey(day, { weekday: 'short', day: 'numeric', month: 'short' });
    return render('tpl-my-day-filter-chip', { chip: { text: label, attrs: { 'data-day': day } } });
  });

  el.replaceChildren(allChip, ...dayChips);
}

// Shared by the Track and Session Type filter rows — same chip-row
// pattern, different field. Hidden entirely when the event's data has
// nothing to filter by (e.g. no session_type set upstream).
function setupChipFilter(elId, sessions, valuesOf, labelOf = (name) => name) {
  const names = [...new Set(sessions.flatMap(valuesOf))].filter(Boolean).sort();
  const el = document.getElementById(elId);

  if (names.length === 0) {
    el.hidden = true;
    return names;
  }

  el.hidden = false;
  el.replaceChildren(...names.map((name) => render('tpl-my-day-filter-chip', { chip: { text: labelOf(name), attrs: { 'data-chip': name } } })));
  return names;
}

function sessionItem(session, speakersById, bookmarkedIds, overlapWarning, expandedSessionId, statusById = null) {
  const speakerNames = (session.speaker_ids ?? []).map((id) => speakersById.get(id)?.name).filter(Boolean).join(', ');
  const time = Number.isFinite(session.startMs)
    ? formatTime(session.startMs)
    : 'TBA';
  const metaLine = [session.track_names?.[0], typeLabel(session.session_type)].filter(Boolean).join(' · ');
  const saved = bookmarkedIds.has(session.id);
  const isOpen = expandedSessionId === session.id;
  const nowMs = Date.now();
  const endMs = Number.isFinite(session.startMs) ? session.startMs + (session.duration_seconds ?? 1800) * 1000 : Number.POSITIVE_INFINITY;
  const isLive = session.startMs <= nowMs && nowMs < endMs;
  const isPast = endMs <= nowMs;
  const tags = isBeginnerFriendly(session) ? [render('tpl-schedule-tag', { tag: { text: 'Beginner friendly', class: { 'schedule-tag--beginner': true } } })] : [];
  // My schedule only: tick a session off once it has started.
  const status = statusById?.get(session.id) ?? null;
  const showStatus = statusById !== null && (session.startMs <= nowMs || status !== null);

  return render('tpl-schedule-session', {
    item: {
      attrs: { 'data-session-id': session.id },
      class: { 'schedule-item--live': isLive, 'schedule-item--past': isPast, 'schedule-item--attended': status === 'attended', 'schedule-item--missed': status === 'missed' },
    },
    'status-row': showStatus,
    attended: { attrs: { 'aria-pressed': String(status === 'attended') }, class: { 'plan-status__btn--on': status === 'attended' } },
    missed: { attrs: { 'aria-pressed': String(status === 'missed') }, class: { 'plan-status__btn--on': status === 'missed' } },
    live: isLive,
    tags: tags.length ? tags : false,
    time,
    'title-btn': { attrs: { 'aria-expanded': String(isOpen) } },
    title: session.title,
    speakers: speakerNames || null,
    meta: metaLine || null,
    'overlap-row': Boolean(overlapWarning),
    overlap: overlapWarning?.title ?? '',
    star: {
      attrs: { 'aria-label': saved ? 'Remove from My Day' : 'Save to My Day', 'aria-pressed': String(saved) },
      class: { 'schedule-item__star--saved': saved },
    },
    detail: {
      attrs: { hidden: !isOpen },
      children: isOpen ? [sessionDetail(session, speakersById, saved)] : [],
    },
  });
}

// The inline replacement for the old session-detail dialog — same
// content (time, speakers + bios, slides/video links, save toggle),
// just rendered under the session instead of over the whole screen.
function sessionDetail(session, speakersById, isSaved) {
  const speakerList = (session.speaker_ids ?? []).map((id) => speakersById.get(id)).filter(Boolean);
  const time = session.starts_at
    ? formatDayTime(new Date(session.starts_at).getTime())
    : 'Time TBA';

  const topics = (session.category_names ?? []).join(' · ');

  return renderFragment('tpl-schedule-detail', {
    time: topics ? `${time} · ${topics}` : time,
    description: session.description || false,
    speakers: speakerList.map(speakerBlock),
    links: Boolean(session.slides_url || session.video_url),
    slides: session.slides_url ? { attrs: { href: session.slides_url, ...linkTracking(session, 'slides') } } : null,
    video: session.video_url ? { attrs: { href: session.video_url, ...linkTracking(session, 'video') } } : null,
    save: {
      text: isSaved ? 'Remove from My Day' : 'Save to My Day',
      class: { 'btn--outline': isSaved, 'btn--primary': !isSaved },
    },
  });
}

// Picked up by analytics.js's data-track handler.
function linkTracking(session, linkType) {
  return { 'data-track': 'session_link_click', 'data-track-schedule-session-id': session.id, 'data-track-link-type': linkType };
}

function speakerBlock(sp) {
  const initial = (sp.name ?? '?').trim().charAt(0).toUpperCase() || '?';

  return render('tpl-schedule-speaker', {
    'avatar-img': sp.avatar_url ? { attrs: { src: sp.avatar_url } } : null,
    'avatar-initial': sp.avatar_url ? null : initial,
    name: sp.name ?? '',
    bio: sp.bio_text || null,
  });
}

function wireItemInteractions(container, allSessions, toggleBookmark, toggleExpand) {
  container.querySelectorAll('.schedule-item-row').forEach((row) => {
    const item = row.querySelector('.schedule-item');
    const session = allSessions.find((s) => s.id === Number(item.dataset.sessionId));
    if (!session) return;

    const starBtn = item.querySelector('.schedule-item__star');
    starBtn?.addEventListener('click', () => toggleBookmark(session, starBtn));
    item.querySelector('[data-open-detail]')?.addEventListener('click', () => toggleExpand(session.id));
    row.querySelector('[data-toggle-save]')?.addEventListener('click', () => toggleBookmark(session, starBtn));
  });
}
