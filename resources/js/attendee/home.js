// Home's guidance logic. Deliberately reads from the
// server-embedded schedule JSON plus local IndexedDB state — no network
// call between page load and a populated Home screen.

import { getBookmarks, getQuestProgress, kvGet } from './db.js';
import { renderDiscoveryCard } from './people.js';
import { render, renderFragment } from './template.js';

export async function renderHome(root) {
  const dataEl = document.getElementById('home-data');
  if (!dataEl) return;

  const { sessions, quests, now } = JSON.parse(dataEl.textContent);
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  const nowMs = new Date(now).getTime();

  const [bookmarks, questProgress, onboarding] = await Promise.all([
    getBookmarks(eventId),
    getQuestProgress(eventId),
    kvGet('onboarding'),
  ]);

  const bookmarkedIds = new Set(bookmarks.map((b) => b.sessionId));
  const completedQuestIds = new Set(questProgress.map((q) => q.questId));

  renderHappeningNow(sessions, nowMs);
  renderUpNext(sessions, nowMs, bookmarkedIds, onboarding);
  renderStartingSoonBanner(sessions, nowMs, bookmarkedIds);
  renderSuggestedAction(quests, completedQuestIds, bookmarks.length);
  renderProgress(quests, completedQuestIds, bookmarks.length);

  const discoveryEl = document.getElementById('people-discovery-home');
  if (discoveryEl) {
    renderDiscoveryCard(discoveryEl, eventSlug, eventId, `discovery:${eventId}`, {
      compact: true,
      exploreUrl: discoveryEl.dataset.exploreUrl,
    });
  }
}

function timedSessions(sessions) {
  return sessions
    .filter((s) => s.starts_at)
    .map((s) => ({ ...s, startMs: new Date(s.starts_at).getTime() }));
}

function renderHappeningNow(sessions, nowMs) {
  const el = document.getElementById('happening-now');
  const current = timedSessions(sessions).find(
    (s) => s.startMs <= nowMs && nowMs < s.startMs + (s.duration_seconds ?? 0) * 1000
  );

  if (!current) {
    el.replaceChildren(render('tpl-home-happening-none'));
    return;
  }

  const track = current.track_names?.[0];
  el.replaceChildren(
    renderFragment('tpl-home-happening-now', {
      title: current.title,
      'track-part': Boolean(track),
      track,
    })
  );
}

function renderUpNext(sessions, nowMs, bookmarkedIds, onboarding) {
  const el = document.getElementById('up-next');
  const upcoming = timedSessions(sessions)
    .filter((s) => s.startMs > nowMs)
    .sort((a, b) => a.startMs - b.startMs);

  if (upcoming.length === 0) {
    el.replaceChildren(render('tpl-home-up-next-none'));
    return;
  }

  // Priority order: the attendee's own saved sessions first.
  const savedUpcoming = upcoming.filter((s) => bookmarkedIds.has(s.id));
  const interests = (onboarding?.interests ?? []).map((i) => i.toLowerCase());
  const matchingInterest = upcoming.find((s) =>
    interests.some((interest) => s.title.toLowerCase().includes(interest.split(' ')[0]))
  );

  const next = savedUpcoming[0] ?? matchingInterest ?? upcoming[0];
  const reason = savedUpcoming[0]
    ? "You've saved this one"
    : matchingInterest
      ? 'Matches something you said you were into'
      : 'Next on the schedule';

  const minutesAway = Math.round((next.startMs - nowMs) / 60000);
  const whenText = minutesAway < 60 ? `in ${minutesAway} min` : `at ${new Date(next.startMs).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;

  el.replaceChildren(
    renderFragment('tpl-home-up-next', { title: next.title, reason, when: whenText })
  );
}

// The guaranteed path, regardless of push support — a
// bookmarked session starting in the next 15 minutes gets an in-app
// banner right on Home, where an open device is most likely to see it.
function renderStartingSoonBanner(sessions, nowMs, bookmarkedIds) {
  const banner = document.getElementById('starting-soon-banner');
  const soon = timedSessions(sessions).find(
    (s) => bookmarkedIds.has(s.id) && s.startMs > nowMs && s.startMs - nowMs <= 15 * 60000
  );

  if (!soon) {
    banner.hidden = true;
    return;
  }

  const minutes = Math.round((soon.startMs - nowMs) / 60000);
  const track = soon.track_names?.[0];
  banner.hidden = false;
  banner.replaceChildren(
    render('tpl-home-starting-soon', {
      title: soon.title,
      minutes,
      'track-part': Boolean(track),
      track,
    })
  );
}

const DEFAULT_SUGGESTIONS = [
  { title: 'Visit the sponsor area', description: 'Sponsors make WordCamp free — say hello and see what they build.' },
  { title: 'Introduce yourself to someone new', description: 'Ask what brought them to this WordCamp.' },
  { title: 'Add a link to your Camp Card', description: 'So people you meet have a way to stay in touch.' },
];

function renderSuggestedAction(quests, completedQuestIds, bookmarkCount) {
  const titleEl = document.getElementById('suggested-action-title');
  const descEl = document.getElementById('suggested-action-desc');

  const incomplete = quests.filter((q) => !completedQuestIds.has(q.id));
  const pool = incomplete.length > 0 ? incomplete : DEFAULT_SUGGESTIONS;

  // A single rotating suggestion ("one suggestion at a time"),
  // picked deterministically per hour so it doesn't flicker on re-render.
  const index = Math.floor(Date.now() / 3600000) % pool.length;
  const pick = pool[index];

  titleEl.textContent = pick.title;
  descEl.textContent = pick.description ?? '';
}

function renderProgress(quests, completedQuestIds, bookmarkCount) {
  const bar = document.getElementById('progress-bar');
  const summary = document.getElementById('progress-summary');

  const total = quests.length;
  const done = quests.filter((q) => completedQuestIds.has(q.id)).length;
  const pct = total > 0 ? Math.round((done / total) * 100) : 0;

  bar.style.width = `${pct}%`;
  summary.textContent = `${done}/${total} quests · ${bookmarkCount} saved sessions`;
}
