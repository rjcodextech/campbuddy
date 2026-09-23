// My Day: Full Schedule + My Schedule, grouped by calendar day so
// multi-day events never blur into one long list (MD1), session detail,
// and the overlap warning (MD4) — bookmarking two overlapping sessions is
// always allowed, this only ever warns, never blocks.

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
    wireItemInteractions(fullListEl, timed, speakersById, toggleBookmark, showDetail);
  }

  function renderMine() {
    const mine = timed.filter((s) => bookmarkedIds.has(s.id));
    renderGroupedByDay(
      mineListEl,
      mine,
      'Nothing saved yet — star a session in Full Schedule to add it here.',
      (s) => overlapsWithBookmarked(s, null)
    );
    wireItemInteractions(mineListEl, timed, speakersById, toggleBookmark, showDetail);
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
            ${dayItems.map((s) => sessionItemHtml(s, speakersById, bookmarkedIds, overlapFn?.(s))).join('')}
          </div>
        `;
      })
      .join('');
  }

  function showDetail(session) {
    renderSessionDetail(session, speakersById, bookmarkedIds.has(session.id), (starred) => {
      const btn = document.querySelector(`[data-session-id="${session.id}"] .schedule-item__star`);
      if (btn) toggleBookmark(session, btn);
    });
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
    document.querySelectorAll('#day-filters [data-day]').forEach((b) => b.classList.toggle('btn--ghost', b.dataset.day !== (activeDay ?? '__all')));
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
        b.classList.toggle('btn--ghost', b !== btn);
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
      return `<button type="button" class="btn btn--compact btn--ghost" data-day="${day}">${escapeHtml(label)}</button>`;
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
  el.innerHTML = names.map((name) => `<button type="button" class="btn btn--compact btn--ghost" data-chip="${escapeAttr(name)}">${escapeHtml(name)}</button>`).join('');
  return names;
}

function sessionItemHtml(session, speakersById, bookmarkedIds, overlapWarning) {
  const speakerNames = (session.speaker_ids ?? []).map((id) => speakersById.get(id)?.name).filter(Boolean).join(', ');
  const time = new Date(session.startMs).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  const track = session.track_names?.[0] ?? '';
  const saved = bookmarkedIds.has(session.id);

  return `
    <div class="schedule-item" data-session-id="${session.id}">
      <div class="schedule-item__time">${time}</div>
      <div>
        <button type="button" class="btn--link schedule-item__title" data-open-detail style="padding:0;text-align:left">${escapeHtml(session.title)}</button>
        ${speakerNames ? `<div class="footer-note u-text-sm" style="margin:2px 0 0;text-align:left">${escapeHtml(speakerNames)}</div>` : ''}
        ${track ? `<span class="schedule-item__track">${escapeHtml(track)}</span>` : ''}
        ${session.session_type ? `<span class="schedule-item__type">${escapeHtml(session.session_type)}</span>` : ''}
        ${overlapWarning ? `<div class="notice" style="margin-top:6px">Overlaps with ${escapeHtml(overlapWarning.title)}</div>` : ''}
      </div>
      <button type="button" class="schedule-item__star ${saved ? 'schedule-item__star--saved' : ''}" aria-label="${saved ? 'Remove from My Day' : 'Save to My Day'}" aria-pressed="${saved}">★</button>
    </div>
  `;
}

function wireItemInteractions(container, allSessions, speakersById, toggleBookmark, showDetail) {
  container.querySelectorAll('.schedule-item').forEach((item) => {
    const session = allSessions.find((s) => s.id === Number(item.dataset.sessionId));
    if (!session) return;

    item.querySelector('.schedule-item__star')?.addEventListener('click', () => toggleBookmark(session, item.querySelector('.schedule-item__star')));
    item.querySelector('[data-open-detail]')?.addEventListener('click', () => showDetail(session));
  });
}

function renderSessionDetail(session, speakersById, isSaved, onToggle) {
  const dialog = document.getElementById('session-detail');
  const speakerList = (session.speaker_ids ?? []).map((id) => speakersById.get(id)).filter(Boolean);
  const time = session.starts_at ? new Date(session.starts_at).toLocaleString([], { weekday: 'short', hour: 'numeric', minute: '2-digit' }) : 'Time TBA';

  dialog.innerHTML = `
    <div class="dialog-card">
      <p class="badge">${escapeHtml(session.track_names?.[0] ?? 'Session')}</p>
      <h2 style="margin:10px 0 4px">${escapeHtml(session.title)}</h2>
      <p class="footer-note" style="margin:0;text-align:left">${escapeHtml(time)}</p>

      ${speakerList.map((sp) => `
        <div style="display:flex;align-items:center;gap:10px;margin-top:14px">
          ${sp.avatar_url ? `<img src="${escapeAttr(sp.avatar_url)}" alt="" style="width:44px;height:44px;border-radius:50%">` : ''}
          <div>
            <p style="margin:0;font-weight:700">${escapeHtml(sp.name)}</p>
          </div>
        </div>
        ${sp.bio_html ? `<div class="u-text-sm" style="margin-top:8px;color:var(--muted)">${sanitizeBio(sp.bio_html)}</div>` : ''}
      `).join('')}

      ${session.slides_url ? `<p style="margin-top:12px"><a class="btn--link" href="${escapeAttr(session.slides_url)}" target="_blank" rel="noopener">Slides</a></p>` : ''}
      ${session.video_url ? `<p style="margin-top:4px"><a class="btn--link" href="${escapeAttr(session.video_url)}" target="_blank" rel="noopener">Video</a></p>` : ''}

      <div style="margin-top:18px;display:flex;justify-content:space-between">
        <button type="button" class="btn btn--ghost" data-action="close">Close</button>
        <button type="button" class="btn btn--primary" data-action="toggle-save">${isSaved ? 'Remove from My Day' : 'Save to My Day'}</button>
      </div>
    </div>
  `;

  dialog.querySelector('[data-action="close"]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-action="toggle-save"]').addEventListener('click', () => {
    onToggle(!isSaved);
    dialog.close();
  });

  dialog.showModal();
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
