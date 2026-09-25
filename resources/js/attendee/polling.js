// When an open app asks the server "has anything changed?" (data-freshness.js).
// Pure functions — no page, no network — so the rules can be tested on their own.
//
// The rules, and why:
//   - around the event's days (the day before through the day after) an
//     announced room change should reach a phone in minutes: every ~5 minutes;
//   - any other time nothing is urgent: every ~15 minutes;
//   - every wait is spread ±20 % so thousands of phones never ask in the same
//     second (a crowd that opened the app together would otherwise stay in step);
//   - a failed ask (offline, 429, 5xx) backs off — twice as long each time, up
//     to 15 minutes — and honours the server's Retry-After, so a struggling
//     server is given room instead of being asked harder.

export const EVENT_WINDOW_MS = 5 * 60 * 1000;
export const QUIET_MS = 15 * 60 * 1000;
export const MAX_BACKOFF_MS = 15 * 60 * 1000;

/** "Y-m-d" of a moment in a time zone (the device's own when none, or an unknown one, is given). */
export function dayAt(moment, timezone) {
  const format = (timeZone) => new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(moment);

  try {
    return format(timezone || undefined);
  } catch {
    return format(undefined);
  }
}

function shiftDay(ymd, days) {
  const date = new Date(`${ymd}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);

  return date.toISOString().slice(0, 10);
}

/**
 * Whether it is around the event's days: the day before the start through the
 * day after the end, at the venue. `facts` is what the page states about the
 * event: { start, end, timezone } (dates as "Y-m-d"; end and timezone optional).
 */
export function inEventWindow(facts, moment = new Date()) {
  if (!facts?.start) return false;

  const today = dayAt(moment, facts.timezone);

  return today >= shiftDay(facts.start, -1) && today <= shiftDay(facts.end || facts.start, 1);
}

/** The server's Retry-After (seconds, or a date) in milliseconds; 0 when absent or unusable. Never more than MAX_BACKOFF_MS. */
export function retryAfterMs(header, now = Date.now()) {
  if (header == null || header === '') return 0;

  const seconds = Number(header);
  const ms = Number.isFinite(seconds) ? seconds * 1000 : Date.parse(header) - now;

  return Number.isFinite(ms) && ms > 0 ? Math.min(ms, MAX_BACKOFF_MS) : 0;
}

/**
 * Milliseconds until the next regular check.
 *
 * @param {{ inWindow: boolean, failures?: number, retryAfter?: number, random?: () => number }} state
 */
export function nextDelay({ inWindow, failures = 0, retryAfter = 0, random = Math.random }) {
  const base = inWindow ? EVENT_WINDOW_MS : QUIET_MS;
  const spread = base * (0.8 + random() * 0.4);
  const backedOff = failures > 0 ? Math.min(MAX_BACKOFF_MS, spread * 2 ** failures) : spread;

  return Math.max(backedOff, retryAfter);
}
