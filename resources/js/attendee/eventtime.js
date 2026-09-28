// Times in the event's own time zone. A WordCamp happens in one place: "Keynote
// 10:00" means 10:00 at the venue, whatever zone the phone happens to be set
// to (a traveller, a phone that never switched, someone following remotely).
// Every time and "which day" on screen goes through here.
//
// The zone comes from the page (#app data-event-timezone): an IANA name
// ("Asia/Kolkata") or a fixed offset ("+05:30"). Without one, the phone's
// own zone is used, as before.

const DAY_MS = 86400000;

let cached;

/** { iana: string|null, offsetMinutes: number|null } */
function zone() {
  if (cached !== undefined) return cached;

  const raw = document.getElementById('app')?.dataset.eventTimezone || '';
  const offset = /^([+-])(\d{2}):(\d{2})$/.exec(raw);

  if (offset) {
    cached = { iana: null, offsetMinutes: (offset[1] === '-' ? -1 : 1) * (Number(offset[2]) * 60 + Number(offset[3])) };
  } else if (raw && isValidIana(raw)) {
    cached = { iana: raw, offsetMinutes: null };
  } else {
    cached = { iana: null, offsetMinutes: null };
  }

  return cached;
}

function isValidIana(name) {
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: name });
    return true;
  } catch {
    return false;
  }
}

/** For tests and pages that set the zone later. */
export function resetEventZone() {
  cached = undefined;
}

/** Intl formatting in the event's zone. */
export function formatInEventZone(ms, options) {
  const z = zone();

  if (z.iana) {
    return new Intl.DateTimeFormat([], { ...options, timeZone: z.iana }).format(ms);
  }
  if (z.offsetMinutes !== null) {
    // A fixed offset: shift the moment and read it as UTC.
    return new Intl.DateTimeFormat([], { ...options, timeZone: 'UTC' }).format(ms + z.offsetMinutes * 60000);
  }
  return new Intl.DateTimeFormat([], options).format(ms);
}

export function formatTime(ms) {
  return formatInEventZone(ms, { hour: 'numeric', minute: '2-digit' });
}

export function formatDayTime(ms) {
  return formatInEventZone(ms, { weekday: 'short', hour: 'numeric', minute: '2-digit' });
}

/** Wall-clock parts of a moment in the event's zone. */
export function partsInEventZone(ms) {
  const z = zone();

  if (z.offsetMinutes !== null) {
    const d = new Date(ms + z.offsetMinutes * 60000);
    return { year: d.getUTCFullYear(), month: d.getUTCMonth() + 1, day: d.getUTCDate(), hour: d.getUTCHours(), minute: d.getUTCMinutes() };
  }

  if (!z.iana) {
    const d = new Date(ms);
    return { year: d.getFullYear(), month: d.getMonth() + 1, day: d.getDate(), hour: d.getHours(), minute: d.getMinutes() };
  }

  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-US', {
      timeZone: z.iana,
      hourCycle: 'h23',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    })
      .formatToParts(ms)
      .filter((p) => p.type !== 'literal')
      .map((p) => [p.type, Number(p.value)])
  );

  return { year: parts.year, month: parts.month, day: parts.day, hour: parts.hour % 24, minute: parts.minute };
}

const pad = (n) => String(n).padStart(2, '0');

/** "YYYY-MM-DD" — the event day a moment falls on. */
export function eventDayKey(ms) {
  const p = partsInEventZone(ms);
  return `${p.year}-${pad(p.month)}-${pad(p.day)}`;
}

/** Whole days between two "YYYY-MM-DD" keys (b − a). */
export function daysBetween(a, b) {
  const toUtc = (key) => {
    const [y, m, d] = key.split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  };
  return Math.round((toUtc(b) - toUtc(a)) / DAY_MS);
}

/** A "YYYY-MM-DD" key as a readable date (no zone involved — it's already a local date). */
export function formatDayKey(key, options = { weekday: 'long', day: 'numeric', month: 'long' }) {
  const [y, m, d] = key.split('-').map(Number);
  return new Intl.DateTimeFormat([], { ...options, timeZone: 'UTC' }).format(Date.UTC(y, m - 1, d));
}

/** The moment it is `hh:mm` on `YYYY-MM-DD` at the venue. */
export function eventWallToMs(dateKey, hh, mm) {
  const [y, m, d] = dateKey.split('-').map(Number);
  const wallAsUtc = Date.UTC(y, m - 1, d, hh, mm);
  const z = zone();

  if (z.offsetMinutes !== null) return wallAsUtc - z.offsetMinutes * 60000;
  if (!z.iana) return new Date(y, m - 1, d, hh, mm).getTime();

  // Two passes settle the zone's offset at that moment (DST included).
  let guess = wallAsUtc;
  for (let i = 0; i < 2; i++) {
    const p = partsInEventZone(guess);
    const shownAsUtc = Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute);
    guess += wallAsUtc - shownAsUtc;
  }
  return guess;
}

/** "YYYY-MM-DDTHH:MM" at the venue, for <input type="datetime-local">. */
export function toEventInput(ms) {
  const p = partsInEventZone(ms);
  return `${p.year}-${pad(p.month)}-${pad(p.day)}T${pad(p.hour)}:${pad(p.minute)}`;
}

/** The moment an <input type="datetime-local"> value means at the venue. */
export function fromEventInput(value) {
  const m = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/.exec(value ?? '');
  return m ? eventWallToMs(m[1], Number(m[2]), Number(m[3])) : NaN;
}

/**
 * The phone isn't on event time (a traveller, or following remotely): a short
 * note for screens with times, e.g. "Times are event time (GMT+5:30)".
 */
export function eventTimeNote(nowMs = Date.now()) {
  const z = zone();
  if (!z.iana && z.offsetMinutes === null) return null;

  const venue = partsInEventZone(nowMs);
  const here = new Date(nowMs);
  const same = venue.hour === here.getHours() && venue.minute === here.getMinutes() && venue.day === here.getDate();
  if (same) return null;

  const name = z.iana
    ? new Intl.DateTimeFormat('en-US', { timeZone: z.iana, timeZoneName: 'short' }).formatToParts(nowMs).find((p) => p.type === 'timeZoneName')?.value
    : `GMT${document.getElementById('app')?.dataset.eventTimezone ?? ''}`;

  return `Times are event time${name ? ` (${name})` : ''} — your phone is set to a different time zone.`;
}
