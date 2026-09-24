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
import { getBookmarks, setBookmark, removeBookmark } from './db.js';
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

  let bookmarkedIds = new Set((await getBookmarks(eventId)).map((b) => b.sessionId));
  let expandedSessionId = null;

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
      await setBookmark(eventId, session.id, false);
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
    const mine = timed.filter((s) => bookmarkedIds.has(s.id));
    renderGroupedByDay(
      mineListEl,
      mine,
      'tpl-my-day-empty-mine',
      (s) => bookmarkedOverlap(s, null)
    );
    wireItemInteractions(mineListEl, timed, toggleBookmark, toggleExpand);
  }

  function renderGroupedByDay(container, list, emptyTemplateId, overlapFn) {
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
          items: dayItems.map((s) => sessionItem(s, speakersById, bookmarkedIds, overlapFn?.(s), expandedSessionId)),
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

function dayKeyOf(ms) {
  const d = new Date(ms);
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

function dayLabelOf(ms) {
  return new Date(ms).toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' });
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
      : new Date(firstStart(day)).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
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

function sessionItem(session, speakersById, bookmarkedIds, overlapWarning, expandedSessionId) {
  const speakerNames = (session.speaker_ids ?? []).map((id) => speakersById.get(id)?.name).filter(Boolean).join(', ');
  const time = Number.isFinite(session.startMs)
    ? new Date(session.startMs).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    : 'TBA';
  const metaLine = [session.track_names?.[0], typeLabel(session.session_type)].filter(Boolean).join(' · ');
  const saved = bookmarkedIds.has(session.id);
  const isOpen = expandedSessionId === session.id;
  const nowMs = Date.now();
  const endMs = Number.isFinite(session.startMs) ? session.startMs + (session.duration_seconds ?? 1800) * 1000 : Number.POSITIVE_INFINITY;
  const isLive = session.startMs <= nowMs && nowMs < endMs;
  const isPast = endMs <= nowMs;
  const tags = isBeginnerFriendly(session) ? [render('tpl-schedule-tag', { tag: { text: 'Beginner friendly', class: { 'schedule-tag--beginner': true } } })] : [];

  return render('tpl-schedule-session', {
    item: { attrs: { 'data-session-id': session.id }, class: { 'schedule-item--live': isLive, 'schedule-item--past': isPast } },
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
    ? new Date(session.starts_at).toLocaleString([], { weekday: 'short', hour: 'numeric', minute: '2-digit' })
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
