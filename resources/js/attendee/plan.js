// The attendee's plan for the event — saved sessions plus people to meet —
// and what's done vs. still to do. Shared by My Day → My schedule and the
// "things left today" reminder, so both always agree.
//
// Done means:
//   a session  — marked attended or missed, or its time is over;
//   a person   — marked met, or marked "couldn't meet".
// Everything else is still to do.

import { eventDayKey } from './eventtime.js';

const DEFAULT_SESSION_MS = 30 * 60 * 1000;

/**
 * @param {Array} bookmarks  rows from db.getBookmarks (may carry title/startMs/endMs/status)
 * @param {Array} meetings   rows from db.getMeetings
 * @param {Map}   [sessionsById] the schedule, when the page has it (fills in times)
 * @param {number} [nowMs]
 */
export function computePlan(bookmarks, meetings, sessionsById = new Map(), nowMs = Date.now()) {
  const sessions = bookmarks.map((b) => {
    const s = sessionsById.get(b.sessionId);
    const startMs = s?.startMs ?? b.startMs ?? NaN;
    const endMs = Number.isFinite(startMs)
      ? (s ? startMs + (s.duration_seconds ? s.duration_seconds * 1000 : DEFAULT_SESSION_MS) : b.endMs ?? startMs + DEFAULT_SESSION_MS)
      : NaN;
    const over = Number.isFinite(endMs) && endMs <= nowMs;
    const started = Number.isFinite(startMs) && startMs <= nowMs;
    const done = Boolean(b.status) || over;

    return { kind: 'session', id: b.sessionId, title: s?.title ?? b.title ?? '', startMs, endMs, status: b.status ?? null, over, started, done };
  });

  const people = meetings.map((m) => ({
    kind: 'person',
    id: m.personKey,
    title: m.name ?? '',
    startMs: m.at ? new Date(m.at).getTime() : NaN,
    status: m.status ?? null,
    done: Boolean(m.status),
    meeting: m,
  }));

  const all = [...sessions, ...people];
  const done = all.filter((i) => i.done).length;

  return { sessions, people, total: all.length, done, left: all.length - done };
}

/** Whether today, at the venue, is one of the event's days. */
export function isEventDay(facts, nowMs = Date.now()) {
  if (!facts.start) return false;
  const today = eventDayKey(nowMs);
  return today >= facts.start && today <= (facts.end ?? facts.start);
}
