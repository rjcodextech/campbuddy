// Home's guidance logic. Deliberately reads from the
// server-embedded schedule JSON plus local IndexedDB state — no network
// call between page load and a populated Home screen (H6).
//
// Every schedule fact comes with what to do about it (H5): the "moments"
// copy (FirstTimerGuide::moments) turns "Lunch" into "Lunch — sit with
// people you haven't met".

import { getBookmarks, getQuestProgress, kvGet, kvSet } from './db.js';
import { daysBetween, eventDayKey, eventTimeNote, formatDayTime, formatTime } from './eventtime.js';
import { momentMatches } from './moments.js';
import { renderDiscoveryCard } from './people.js';
import { render } from './template.js';


// Onboarding's "What are you into?" answers → words that suggest a session
// is for them. Matched against title, categories and description.
const INTEREST_WORDS = {
  developer: ['develop', 'code', 'coding', 'block', 'plugin', 'api', 'php', 'javascript', 'react', 'performance', 'headless', 'git'],
  designer: ['design', 'theme', 'ux', 'ui', 'accessib', 'css', 'typograph', 'colour', 'color'],
  'content creator': ['content', 'writ', 'blog', 'seo', 'video', 'podcast', 'story', 'newsletter'],
  'site builder': ['site', 'builder', 'no-code', 'page', 'theme', 'woocommerce', 'template', 'pattern'],
  'community organizer': ['community', 'meetup', 'contribut', 'volunteer', 'organiz', 'diversity', 'open source'],
  marketer: ['marketing', 'seo', 'social', 'growth', 'brand', 'analytics', 'email', 'conversion'],
  // Students: the sessions that assume nothing, and the ones about getting started in a career.
  student: ['beginner', 'introduct', 'getting started', '101', 'first', 'career', 'learn', 'student', 'freelanc', 'job'],
  'business owner': ['business', 'freelanc', 'agency', 'client', 'ecommerce', 'woocommerce', 'pricing', 'sales', 'career'],
};

export async function renderHome(root) {
  const dataEl = document.getElementById('home-data');
  if (!dataEl) return;

  const data = JSON.parse(dataEl.textContent);
  const { quests } = data;
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  // The device clock, not the moment the server rendered the page: with no
  // signal this page comes from the saved copy, which can be hours old — and
  // "Happening now" / "Up next" from then would be wrong.
  const nowMs = Date.now();
  const sessions = timedSessions(data.sessions ?? []);

  const [bookmarks, questProgress, onboarding, startHereDismissed] = await Promise.all([
    getBookmarks(eventId),
    getQuestProgress(eventId),
    kvGet('onboarding'),
    kvGet(`startHereDismissed:${eventId}`),
  ]);

  const bookmarkedIds = new Set(bookmarks.map((b) => b.sessionId));
  const completedQuestIds = new Set(questProgress.map((q) => q.questId));
  // Talks already announced but not yet given a time on the WordCamp site.
  const untimedCount = (data.sessions ?? []).filter((s) => s && !s.starts_at).length;
  const ctx = { ...data, sessions, untimedCount, nowMs, bookmarkedIds, onboarding };

  renderStartHere(eventId, onboarding, startHereDismissed);

  // Someone whose phone isn't on event time is told the times are the venue's.
  const note = eventTimeNote();
  if (note && !document.getElementById('event-time-note')) {
    const p = document.createElement('p');
    p.id = 'event-time-note';
    p.className = 'notice';
    p.textContent = `🕒 ${note}`;
    document.querySelector('.home-hero')?.after(p);
  }
  renderEventStatus(ctx);
  renderHappeningNow(ctx);
  renderUpNext(ctx);
  renderStartingSoonBanner(ctx);
  renderSuggestedAction(quests, completedQuestIds);
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
    .filter((s) => s && s.starts_at)
    .map((s) => {
      const startMs = new Date(s.starts_at).getTime();
      return { ...s, startMs, endMs: startMs + (s.duration_seconds ?? 0) * 1000 };
    })
    .filter((s) => Number.isFinite(s.startMs))
    .sort((a, b) => a.startMs - b.startMs);
}

// --- "New to WordCamp? Start here" -------------------------------------

function renderStartHere(eventId, onboarding, dismissed) {
  const card = document.getElementById('start-here');
  if (!card) return;

  // Rendered visible, so a first-timer on a slow phone never sees it pop in
  // late; hidden only for someone who said it isn't their first WordCamp.
  if (dismissed || onboarding?.firstWordCamp === 'no') {
    card.hidden = true;
    return;
  }

  document.getElementById('start-here-dismiss')?.addEventListener('click', async () => {
    card.hidden = true;
    await kvSet(`startHereDismissed:${eventId}`, true);
  });
}

// --- Where the event is in time ----------------------------------------

// Days are the event's own (its time zone), never the phone's: "Day 2" and
// "starts tomorrow" must mean the same thing to everyone at the venue.
function eventPhase({ sessions, nowMs, startsOn, endsOn }) {
  const first = sessions[0];
  const last = sessions.reduce((max, s) => Math.max(max, s.endMs), 0);
  const startDay = startsOn || (first ? eventDayKey(first.startMs) : null);
  const endDay = endsOn || (last ? eventDayKey(last) : startDay);
  const today = eventDayKey(nowMs);

  if (startDay && today < startDay) {
    return { phase: 'before', daysAway: daysBetween(today, startDay), first };
  }

  if (first && nowMs < first.startMs && today <= eventDayKey(first.startMs)) {
    return { phase: 'before', daysAway: 0, first };
  }

  if ((last && nowMs > last && !sessions.some((s) => s.startMs > nowMs)) || (endDay && today > endDay)) {
    return { phase: 'after' };
  }

  if (startDay && endDay && endDay > startDay) {
    return {
      phase: 'during',
      day: daysBetween(startDay, today) + 1,
      days: daysBetween(startDay, endDay) + 1,
    };
  }

  return { phase: 'during', day: 1, days: 1 };
}

function countdownText(p) {
  if (p.daysAway > 1) return `WordCamp starts in ${p.daysAway} days`;
  if (p.daysAway === 1) return 'WordCamp starts tomorrow';
  return p.first ? `WordCamp starts today at ${formatTime(p.first.startMs)}` : 'WordCamp starts today';
}

function renderEventStatus(ctx) {
  const el = document.getElementById('event-status');
  if (!el) return;

  const p = eventPhase(ctx);
  const text = {
    before: () => countdownText(p),
    during: () => (p.days > 1 ? `Day ${p.day} of ${p.days} · happening now` : 'Happening today'),
    after: () => 'This WordCamp has finished',
  }[p.phase]();

  el.textContent = text;
  el.dataset.phase = p.phase;
  el.hidden = false;
}

// --- Guidance copy ------------------------------------------------------

function momentFor(session, moments) {
  return moments.find((m) => momentMatches(m, session)) ?? null;
}

function guidanceFor(sessionsNow, { moments, sessionNow }) {
  for (const s of sessionsNow) {
    const m = momentFor(s, moments);
    if (m) return m.now;
  }
  return sessionsNow.length > 1 ? sessionNow.several : sessionNow.single;
}

// --- Happening now ------------------------------------------------------

function renderHappeningNow(ctx) {
  const el = document.getElementById('happening-now');
  const { sessions, nowMs, urls } = ctx;
  const p = eventPhase(ctx);

  if (p.phase === 'before') {
    el.replaceChildren(
      render('tpl-home-happening-before', {
        countdown: countdownText(p),
        guide: { attrs: { href: urls.guide } },
        'my-day': { attrs: { href: urls.myDay } },
        quest: { attrs: { href: urls.quest } },
      })
    );
    return;
  }

  // Sessions with no duration count as "now" for their first 30 minutes,
  // rather than never.
  const current = sessions.filter((s) => s.startMs <= nowMs && nowMs < Math.max(s.endMs, s.startMs + 30 * 60000));

  if (current.length === 0) {
    if (p.phase === 'after') {
      el.replaceChildren(render('tpl-home-happening-after'));
      return;
    }

    const next = sessions.find((s) => s.startMs > nowMs);
    el.replaceChildren(
      render('tpl-home-happening-none', {
        'next-part': Boolean(next),
        next: next?.title,
        'next-when': next ? whenText(next.startMs, nowMs) : '',
      })
    );
    return;
  }

  el.replaceChildren(
    render('tpl-home-happening-now', {
      items: current.map((s) =>
        render('tpl-home-now-item', {
          title: s.title,
          meta: [s.track_names?.[0], `until ${formatTime(s.endMs > s.startMs ? s.endMs : s.startMs + 30 * 60000)}`].filter(Boolean).join(' · '),
        })
      ),
      guidance: guidanceFor(current, ctx),
    })
  );
}

// --- Up next (H2) -------------------------------------------------------

function interestScore(session, interests) {
  if (interests.length === 0) return 0;
  const haystack = [session.title, ...(session.category_names ?? []), session.description ?? ''].join(' ').toLowerCase();
  return interests.reduce((score, interest) => {
    const words = INTEREST_WORDS[interest] ?? [interest.split(' ')[0]];
    return score + (words.some((w) => haystack.includes(w)) ? 1 : 0);
  }, 0);
}

function isBeginnerFriendly(session) {
  return (session.category_names ?? []).some((c) => /beginner|introduct|getting started|101/i.test(c));
}

function renderUpNext(ctx) {
  const el = document.getElementById('up-next');
  const { sessions, nowMs, bookmarkedIds, onboarding, moments } = ctx;
  const upcoming = sessions.filter((s) => s.startMs > nowMs);

  if (upcoming.length === 0) {
    el.replaceChildren(
      ctx.untimedCount > 0
        ? render('tpl-home-up-next-tba', { count: ctx.untimedCount, link: { attrs: { href: ctx.urls.myDay } } })
        : render('tpl-home-up-next-none')
    );
    return;
  }

  // Priority (H2): (1) the attendee's own saved sessions, (2) event-wide
  // moments like the keynote or lunch, (3) sessions matching their stated
  // interests — each only among what starts soon (saved: 2 hours, event-wide:
  // 30 minutes, interests: 1 hour), so lunch in an hour doesn't hide the
  // talks starting in ten minutes, and a saved session this evening doesn't
  // hide the keynote.
  const soonest = upcoming[0].startMs;
  const within = (minutes) => upcoming.filter((s) => s.startMs <= soonest + minutes * 60000);
  const interests = (onboarding?.interests ?? []).map((i) => i.toLowerCase());
  const firstTimer = onboarding?.firstWordCamp === 'yes';

  const saved = within(120).find((s) => bookmarkedIds.has(s.id));
  const keyMoment = within(30).find((s) => momentFor(s, moments)?.key);
  const interesting = within(60)
    .map((s) => ({ s, score: interestScore(s, interests) + (firstTimer && isBeginnerFriendly(s) ? 1 : 0) }))
    .filter((x) => x.score > 0)
    .sort((a, b) => b.score - a.score || a.s.startMs - b.s.startMs)[0]?.s;

  const next = saved ?? keyMoment ?? interesting ?? upcoming[0];
  const reason = saved
    ? "You've saved this one"
    : keyMoment === next
      ? 'For everyone'
      : interesting === next
        ? isBeginnerFriendly(next) && firstTimer ? 'Beginner-friendly' : 'Matches what you said you\'re into'
        : 'Next on the schedule';

  const moment = momentFor(next, moments);
  const alternatives = upcoming.filter((s) => s.startMs === next.startMs && s.id !== next.id).length;
  const guidance = moment?.now
    ?? (alternatives > 0 ? `${alternatives === 1 ? 'Another session runs' : `${alternatives} other sessions run`} at the same time — compare them in My Day.` : null);

  const track = next.track_names?.[0];
  el.replaceChildren(
    render('tpl-home-up-next', {
      title: next.title,
      reason,
      when: whenText(next.startMs, nowMs),
      'track-part': Boolean(track),
      track,
      guidance: guidance ?? false,
    })
  );
}

function whenText(startMs, nowMs) {
  const minutesAway = Math.round((startMs - nowMs) / 60000);
  if (minutesAway < 60) return `in ${Math.max(minutesAway, 1)} min`;

  const sameDay = eventDayKey(startMs) === eventDayKey(nowMs);
  return sameDay ? `at ${formatTime(startMs)}` : formatDayTime(startMs);
}

// The guaranteed path, regardless of push support — a
// bookmarked session starting in the next 15 minutes gets an in-app
// banner right on Home, where an open device is most likely to see it.
function renderStartingSoonBanner({ sessions, nowMs, bookmarkedIds }) {
  const banner = document.getElementById('starting-soon-banner');
  const soon = sessions.find(
    (s) => bookmarkedIds.has(s.id) && s.startMs > nowMs && s.startMs - nowMs <= 15 * 60000
  );

  if (!soon) {
    banner.hidden = true;
    return;
  }

  const minutes = Math.max(1, Math.round((soon.startMs - nowMs) / 60000));
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

function renderSuggestedAction(quests, completedQuestIds) {
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
  const track = document.getElementById('progress-track');
  const summary = document.getElementById('progress-summary');

  const total = quests.length;
  const done = quests.filter((q) => completedQuestIds.has(q.id)).length;
  const pct = total > 0 ? Math.round((done / total) * 100) : 0;

  bar.style.width = `${pct}%`;
  track?.setAttribute('aria-valuenow', String(pct));
  summary.textContent = `${done}/${total} quests · ${bookmarkCount} saved session${bookmarkCount === 1 ? '' : 's'}`;
}

