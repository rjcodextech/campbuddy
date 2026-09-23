// My Day: Full Schedule + My Schedule, grouped by calendar day so
// multi-day events never blur into one long list (MD1), and the overlap
// warning (MD4) — bookmarking two overlapping sessions is always
// allowed, this only ever warns, never blocks.
//
// Session detail is an inline accordion under the tapped session, not a
// popup — no <dialog> anywhere in this module. Only one session's detail
// is open at a time; expandedSessionId lives in this closure and is
// threaded through every render so it survives a re-render triggered by
// a filter/search/bookmark change instead of silently closing.

import { getBookmarks, setBookmark, removeBookmark } from './db.js';
import { offerReminder } from './push.js';

export async function renderMyDay(root) {
  const dataEl = document.getElementById('my-day-data');
  if (!dataEl) return;

  const { sessions, speakers } = JSON.parse(dataEl.textContent);
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  const speakersById = new Map(speakers.map((s) => [s.id, s]));

  let bookmarkedIds = new Set((await getBookmarks(eventId)).map((b) => b.sessionId));
  let expandedSessionId = null;

  const timed = sessions
    .filter((s) => s.starts_at)
    .map((s) => ({ ...s, startMs: new Date(s.starts_at).getTime(), dayKey: dayKeyOf(new Date(s.starts_at).getTime()) }))
    .sort((a, b) => a.startMs - b.startMs);

  setupTabs();
  setupDayFilters(timed);
  const trackNames = setupChipFilter('track-filters', timed, (s) => s.track_names ?? []);
  const typeNames = setupChipFilter('type-filters', timed, (s) => (s.session_type ? [s.session_type] : []));
  let activeDay = null;
  let activeTrack = null;
  let activeType = null;
  let query = '';

  const fullListEl = document.getElementById('full-schedule-list');
  const mineListEl = document.getElementById('my-schedule-list');
  const searchEl = document.getElementById('session-search');

  function overlapsWithBookmarked(session, excludeId = null) {
    return timed.some(
      (other) =>
        other.id !== session.id &&
        other.id !== excludeId &&
        bookmarkedIds.has(other.id) &&
        rangesOverlap(session, other)
    );
  }

  function rangesOverlap(a, b) {
    const aEnd = a.startMs + (a.duration_seconds ?? 0) * 1000;
    const bEnd = b.startMs + (b.duration_seconds ?? 0) * 1000;
    return a.startMs < bEnd && b.startMs < aEnd;
  }

  async function toggleBookmark(session, starButton) {
    if (bookmarkedIds.has(session.id)) {
      bookmarkedIds.delete(session.id);
      await removeBookmark(eventId, session.id);
      starButton.classList.remove('schedule-item__star--saved');
    } else {
      const conflict = overlapsWithBookmarked(session);
      await setBookmark(eventId, session.id, false);
      bookmarkedIds.add(session.id);
      starButton.classList.add('schedule-item__star--saved');

      if (conflict) {
        showToast(`Heads up — this overlaps with ${escapeHtml(conflict.title ?? 'another saved session')}. Both are saved.`);
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
    renderFull();
    renderMine();
  }

  function renderFull() {
    const filtered = timed.filter((s) => {
      const matchesDay = !activeDay || s.dayKey === activeDay;
      const matchesTrack = !activeTrack || (s.track_names ?? []).includes(activeTrack);
      const matchesType = !activeType || s.session_type === activeType;
      const haystack = `${s.title} ${(s.speaker_ids ?? []).map((id) => speakersById.get(id)?.name ?? '').join(' ')}`.toLowerCase();
      const matchesQuery = !query || haystack.includes(query);
      return matchesDay && matchesTrack && matchesType && matchesQuery;
    });

    renderGroupedByDay(fullListEl, filtered, 'No sessions match.');
    wireItemInteractions(fullListEl, timed, toggleBookmark, toggleExpand);
  }

  function renderMine() {
    const mine = timed.filter((s) => bookmarkedIds.has(s.id));
    renderGroupedByDay(
      mineListEl,
      mine,
      'Nothing saved yet — star a session in Full Schedule to add it here.',
      (s) => overlapsWithBookmarked(s, null)
    );
    wireItemInteractions(mineListEl, timed, toggleBookmark, toggleExpand);
  }

  function renderGroupedByDay(container, list, emptyMessage, overlapFn) {
    if (list.length === 0) {
      container.innerHTML = `<p style="margin:0;padding:15px 0">${emptyMessage}</p>`;
      return;
    }

    const days = [...new Set(list.map((s) => s.dayKey))];
    const showHeadings = days.length > 1;

    container.innerHTML = days
      .map((day) => {
        const dayItems = list.filter((s) => s.dayKey === day);
        return `
          <div class="schedule-day">
            ${showHeadings ? `<p class="schedule-day-heading">${escapeHtml(dayLabelOf(dayItems[0].startMs))}</p>` : ''}
            ${dayItems.map((s) => sessionItemHtml(s, speakersById, bookmarkedIds, overlapFn?.(s), expandedSessionId)).join('')}
          </div>
        `;
      })
      .join('');
  }

  searchEl.addEventListener('input', () => {
    query = searchEl.value.trim().toLowerCase();
    renderFull();
  });

  document.getElementById('day-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-day]');
    if (!btn) return;
    activeDay = btn.dataset.day === '__all' ? null : (btn.dataset.day === activeDay ? null : btn.dataset.day);
    document.querySelectorAll('#day-filters [data-day]').forEach((b) => b.classList.toggle('btn--primary', b.dataset.day === (activeDay ?? '__all')));
    document.querySelectorAll('#day-filters [data-day]').forEach((b) => b.classList.toggle('btn--outline', b.dataset.day !== (activeDay ?? '__all')));
    renderFull();
  });

  document.getElementById('track-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chip]');
    if (!btn) return;
    activeTrack = btn.dataset.chip === activeTrack ? null : btn.dataset.chip;
    document.querySelectorAll('#track-filters [data-chip]').forEach((b) => b.classList.toggle('btn--primary', b.dataset.chip === activeTrack));
    renderFull();
  });

  document.getElementById('type-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chip]');
    if (!btn) return;
    activeType = btn.dataset.chip === activeType ? null : btn.dataset.chip;
    document.querySelectorAll('#type-filters [data-chip]').forEach((b) => b.classList.toggle('btn--primary', b.dataset.chip === activeType));
    renderFull();
  });

  renderFull();
  renderMine();
}

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
    });
  });
}

// MD1/day filter: only shown when the event actually spans more than one
// day — a single-day WordCamp has nothing to filter by.
function setupDayFilters(sessions) {
  const el = document.getElementById('day-filters');
  const days = [...new Set(sessions.map((s) => s.dayKey))].sort((a, b) => {
    const sa = sessions.find((s) => s.dayKey === a).startMs;
    const sb = sessions.find((s) => s.dayKey === b).startMs;
    return sa - sb;
  });

  if (days.length < 2) {
    el.hidden = true;
    return;
  }

  el.hidden = false;
  const allChip = `<button type="button" class="btn btn--compact btn--primary" data-day="__all">All days</button>`;
  const dayChips = days
    .map((day) => {
      const label = new Date(sessions.find((s) => s.dayKey === day).startMs).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
      return `<button type="button" class="btn btn--compact btn--outline" data-day="${day}">${escapeHtml(label)}</button>`;
    })
    .join('');

  el.innerHTML = allChip + dayChips;
}

// Shared by the Track and Session Type filter rows — same chip-row
// pattern, different field. Hidden entirely when the event's data has
// nothing to filter by (e.g. no session_type set upstream).
function setupChipFilter(elId, sessions, valuesOf) {
  const names = [...new Set(sessions.flatMap(valuesOf))].filter(Boolean).sort();
  const el = document.getElementById(elId);

  if (names.length === 0) {
    el.hidden = true;
    return names;
  }

  el.hidden = false;
  el.innerHTML = names.map((name) => `<button type="button" class="btn btn--compact btn--outline" data-chip="${escapeAttr(name)}">${escapeHtml(name)}</button>`).join('');
  return names;
}

function sessionItemHtml(session, speakersById, bookmarkedIds, overlapWarning, expandedSessionId) {
  const speakerNames = (session.speaker_ids ?? []).map((id) => speakersById.get(id)?.name).filter(Boolean).join(', ');
  const time = new Date(session.startMs).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  const metaLine = [session.track_names?.[0], session.session_type].filter(Boolean).join(' · ');
  const saved = bookmarkedIds.has(session.id);
  const isOpen = expandedSessionId === session.id;

  return `
    <div class="schedule-item-row">
      <div class="schedule-item" data-session-id="${session.id}">
        <div class="schedule-item__time">${time}</div>
        <div>
          <button type="button" class="schedule-item__title" data-open-detail aria-expanded="${isOpen}">
            <span>${escapeHtml(session.title)}</span>
            <svg class="schedule-item__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          ${speakerNames ? `<p class="schedule-item__speakers">${escapeHtml(speakerNames)}</p>` : ''}
          ${metaLine ? `<span class="schedule-item__meta">${escapeHtml(metaLine)}</span>` : ''}
          ${overlapWarning ? `<div class="notice" style="margin-top:6px">Overlaps with ${escapeHtml(overlapWarning.title)}</div>` : ''}
        </div>
        <button type="button" class="schedule-item__star ${saved ? 'schedule-item__star--saved' : ''}" aria-label="${saved ? 'Remove from My Day' : 'Save to My Day'}" aria-pressed="${saved}">★</button>
      </div>
      <div class="schedule-item-detail" ${isOpen ? '' : 'hidden'}>
        ${isOpen ? sessionDetailHtml(session, speakersById, saved) : ''}
      </div>
    </div>
  `;
}

// The inline replacement for the old session-detail dialog — same
// content (time, speakers + bios, slides/video links, save toggle),
// just rendered under the session instead of over the whole screen.
function sessionDetailHtml(session, speakersById, isSaved) {
  const speakerList = (session.speaker_ids ?? []).map((id) => speakersById.get(id)).filter(Boolean);
  const time = session.starts_at
    ? new Date(session.starts_at).toLocaleString([], { weekday: 'short', hour: 'numeric', minute: '2-digit' })
    : 'Time TBA';

  return `
    <p class="schedule-item-detail__meta">${escapeHtml(time)}</p>

    ${speakerList
      .map((sp) => {
        const initial = escapeHtml((sp.name ?? '?').trim().charAt(0).toUpperCase() || '?');
        const avatar = sp.avatar_url
          ? `<img src="${escapeAttr(sp.avatar_url)}" alt="" class="schedule-item-detail__speaker-avatar">`
          : `<span class="schedule-item-detail__speaker-avatar schedule-item-detail__speaker-avatar--initial">${initial}</span>`;

        return `
          <div class="schedule-item-detail__speaker-block">
            <div class="schedule-item-detail__speaker">
              ${avatar}
              <p class="schedule-item-detail__speaker-name">${escapeHtml(sp.name)}</p>
            </div>
            ${sp.bio_html ? `<div class="schedule-item-detail__bio">${sanitizeBio(sp.bio_html)}</div>` : ''}
          </div>
        `;
      })
      .join('')}

    ${
      session.slides_url || session.video_url
        ? `
          <div class="schedule-item-detail__links">
            ${session.slides_url ? `<a class="btn--link" href="${escapeAttr(session.slides_url)}" target="_blank" rel="noopener">Slides</a>` : ''}
            ${session.video_url ? `<a class="btn--link" href="${escapeAttr(session.video_url)}" target="_blank" rel="noopener">Video</a>` : ''}
          </div>
        `
        : ''
    }

    <div class="schedule-item-detail__actions">
      <button type="button" class="btn btn--compact ${isSaved ? 'btn--outline' : 'btn--primary'}" data-toggle-save>${isSaved ? 'Remove from My Day' : 'Save to My Day'}</button>
    </div>
  `;
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

function showToast(message) {
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// Speaker bios come from the event's own WordPress content — real
// but still third-party HTML, so it's stripped to text-with-line-breaks
// rather than injected raw ("never {!! !!} on untrusted content",
// applied here in the JS layer since this never touches Blade).
function sanitizeBio(html) {
  const div = document.createElement('div');
  div.innerHTML = html;
  return escapeHtmlText(div.textContent ?? '').replace(/\n+/g, '<br>');
}

function escapeHtmlText(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function escapeHtml(str) {
  return escapeHtmlText(str);
}

function escapeAttr(str) {
  return escapeHtmlText(str);
}
