// The attendee's plan for the event — saved sessions plus people to meet —
// and what's done vs. still to do. Shared by My Day → My schedule and the
// "things left today" reminder, so both always agree.
//
// Done means:
//   a session  — marked attended or missed, or its time is over;
//   a person   — marked met, or marked "couldn't meet".
// Everything else is still to do. Hidden people (kept, but out of sight) and
// people who were never planned don't count in the totals (see people-state.js).

import { eventDayKey } from './eventtime.js';
import { isBlank } from './people-state.js';

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

  const personItem = (m) => ({
    kind: 'person',
    id: m.personKey,
    title: m.name ?? '',
    startMs: m.at ? new Date(m.at).getTime() : NaN,
    status: m.status ?? null,
    done: Boolean(m.status),
    meeting: m,
  });

  // Hidden people are kept but not on the plan; someone who was never planned
  // (marked "I met them" on a match) is listed but not counted towards "N of M".
  // A record folded into another one (same person under two keys) is listed once, under the main key.
  const records = meetings.filter((m) => !m.mergedInto);
  const people = records.filter((m) => m.status !== 'skipped' && !isBlank(m)).map(personItem);
  const hidden = records.filter((m) => m.status === 'skipped').map(personItem);

  const counted = [...sessions, ...people.filter((p) => !p.meeting.unplanned)];
  const done = counted.filter((i) => i.done).length;

  return { sessions, people, hidden, total: counted.length, done, left: counted.length - done };
}

/** Whether today, at the venue, is one of the event's days. */
export function isEventDay(facts, nowMs = Date.now()) {
  if (!facts.start) return false;
  const today = eventDayKey(nowMs);
  return today >= facts.start && today <= (facts.end ?? facts.start);
}
