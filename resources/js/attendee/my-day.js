// My Day (§3.8): Full Schedule + My Schedule, session detail, and the
// overlap warning (MD4) — bookmarking two overlapping sessions is always
// allowed, this only ever warns, never blocks.

import { getBookmarks, setBookmark, removeBookmark } from './db.js';

export async function renderMyDay(root) {
  const dataEl = document.getElementById('my-day-data');
  if (!dataEl) return;

  const { sessions, speakers } = JSON.parse(dataEl.textContent);
  const eventId = Number(root.dataset.eventId);
  const speakersById = new Map(speakers.map((s) => [s.id, s]));

  let bookmarkedIds = new Set((await getBookmarks(eventId)).map((b) => b.sessionId));

  const timed = sessions
    .filter((s) => s.starts_at)
    .map((s) => ({ ...s, startMs: new Date(s.starts_at).getTime() }))
    .sort((a, b) => a.startMs - b.startMs);

  setupTabs();
  const trackNames = setupTrackFilters(timed);
  let activeTrack = null;
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
    }

    renderFull();
    renderMine();
  }

  function renderFull() {
    const filtered = timed.filter((s) => {
      const matchesTrack = !activeTrack || (s.track_names ?? []).includes(activeTrack);
      const haystack = `${s.title} ${(s.speaker_ids ?? []).map((id) => speakersById.get(id)?.name ?? '').join(' ')}`.toLowerCase();
      const matchesQuery = !query || haystack.includes(query);
      return matchesTrack && matchesQuery;
    });

    fullListEl.innerHTML =
      filtered.length === 0
        ? `<p style="margin:0;padding:15px 0">No sessions match.</p>`
        : filtered.map((s) => sessionItemHtml(s, speakersById, bookmarkedIds)).join('');

    wireItemInteractions(fullListEl, timed, speakersById, toggleBookmark, showDetail);
  }

  function renderMine() {
    const mine = timed.filter((s) => bookmarkedIds.has(s.id));
    mineListEl.innerHTML =
      mine.length === 0
        ? `<p style="margin:0;padding:15px 0">Nothing saved yet — star a session in Full Schedule to add it here.</p>`
        : mine.map((s) => sessionItemHtml(s, speakersById, bookmarkedIds, overlapsWithBookmarked(s, null))).join('');

    wireItemInteractions(mineListEl, timed, speakersById, toggleBookmark, showDetail);
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

  document.getElementById('track-filters').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-track]');
    if (!btn) return;
    activeTrack = btn.dataset.track === activeTrack ? null : btn.dataset.track;
    document.querySelectorAll('#track-filters [data-track]').forEach((b) => b.classList.toggle('btn--primary', b.dataset.track === activeTrack));
    renderFull();
  });

  renderFull();
  renderMine();
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

function setupTrackFilters(sessions) {
  const names = [...new Set(sessions.flatMap((s) => s.track_names ?? []))].sort();
  const el = document.getElementById('track-filters');
  el.innerHTML = names.map((name) => `<button type="button" class="btn btn--compact btn--ghost" data-track="${escapeHtml(name)}">${escapeHtml(name)}</button>`).join('');
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
        <button type="button" class="btn--link" data-open-detail style="padding:0;text-align:left;font-weight:700">${escapeHtml(session.title)}</button>
        ${speakerNames ? `<div class="footer-note" style="margin:2px 0 0;text-align:left">${escapeHtml(speakerNames)}</div>` : ''}
        <span class="schedule-item__track">${escapeHtml(track)}</span>
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
        ${sp.bio_html ? `<div style="margin-top:8px;font-size:13px;color:var(--muted)">${sanitizeBio(sp.bio_html)}</div>` : ''}
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

// Speaker bios come from the event's own WordPress content (§0.1) — real
// but still third-party HTML, so it's stripped to text-with-line-breaks
// rather than injected raw (§21.3's "never {!! !!} on untrusted content",
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
