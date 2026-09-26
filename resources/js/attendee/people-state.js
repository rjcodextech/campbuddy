// Where each person stands for this attendee — decided here, in one place,
// so Explore → People and My Day → My schedule always say the same thing.
//
// A person is one record in the device's `meetings` store (db.js), keyed
// `${eventId}:${personKey}` — `r:<id>` for someone picked from the attendee
// list, `d:<discoveryId>` for a discovery match. Its `status` is:
//
//   null       planned — "+ Meet": they're on the plan, not met yet
//   'met'      met
//   'missed'   couldn't meet
//   'skipped'  hidden (✕) — kept, never deleted, brought back with "Show again";
//              `statusBeforeSkip` remembers what it was
//
// `unplanned: true` marks a record made by an action on someone who was never
// planned ("I met them" on a match, or hiding one). They show up wherever
// their status is listed, but are not part of the plan's "N of M done" — and
// while such a record has no status (undone or brought back) it is as if the
// person had no record at all.
//
// Before this record existed "I met them" was kept in a separate list
// (`metHistory`, by discovery id). Someone marked there and never given a
// record still counts as met; once a record exists it rules.
//
// Nothing in this file touches the page or the database.

export const PLAN_FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'todo', label: 'To do' },
  { id: 'done', label: 'Done' },
  { id: 'missed', label: "Couldn't" },
  { id: 'hidden', label: 'Hidden' },
];

export const DEFAULT_FILTER = 'all';

/** The record key of a discovery match. */
export const discoveryPersonKey = (discoveryId) => `d:${discoveryId}`;

/** Whether a record is part of the plan (planned, or planned and since met / missed), i.e. counts as "N of M". */
export const isPlanned = (meeting) => !meeting.unplanned && meeting.status !== 'skipped';

/** A record that says nothing yet: made by an action on an unplanned person and since undone. */
export const isBlank = (meeting) => Boolean(meeting.unplanned) && !meeting.status;

/**
 * 'planned' | 'met' | 'missed' | 'skipped' | 'none'
 *
 * @param {object|undefined} meeting  the person's record, if they have one
 * @param {boolean} legacyMet         marked "I met them" in the old list
 */
export function personState(meeting, legacyMet = false) {
  if (!meeting) return legacyMet ? 'met' : 'none';

  if (meeting.status === 'met') return 'met';
  if (meeting.status === 'missed') return 'missed';
  if (meeting.status === 'skipped') return 'skipped';

  return meeting.unplanned ? 'none' : 'planned';
}

/**
 * Which filter group a plan item is in — every item is in exactly one:
 * 'todo' | 'done' | 'missed' | 'hidden'. Sessions (kind 'session') are done
 * when attended or once their time is over, like the plan's own progress.
 */
export function groupOf(item) {
  if (item.kind === 'person') {
    if (item.status === 'skipped') return 'hidden';
    if (item.status === 'met') return 'done';
    if (item.status === 'missed') return 'missed';

    return 'todo';
  }

  if (item.status === 'attended') return 'done';
  if (item.status === 'missed') return 'missed';

  return item.over ? 'done' : 'todo';
}

/** Whether an item shows under a filter chip. "All" is everything that isn't hidden. */
export function matchesFilter(item, filter) {
  const group = groupOf(item);

  return filter === 'all' ? group !== 'hidden' : group === filter;
}

/** The numbers on the chips: all = todo + done + missed; hidden is counted apart. */
export function filterCounts(items) {
  const counts = { all: 0, todo: 0, done: 0, missed: 0, hidden: 0 };

  for (const item of items) {
    const group = groupOf(item);
    counts[group]++;
    if (group !== 'hidden') counts.all++;
  }

  return counts;
}

/** The chips worth showing: "All" and "To do" always, the rest only when they have something in them. */
export function visibleFilters(counts) {
  return PLAN_FILTERS.filter((f) => f.id === 'all' || f.id === 'todo' || counts[f.id] > 0);
}

/** The selected chip, or "All" when it has just been emptied (and so is no longer shown). */
export function effectiveFilter(selected, counts) {
  return visibleFilters(counts).some((f) => f.id === selected) ? selected : DEFAULT_FILTER;
}

/** The filter to start on: the saved one, or "To do" for someone who used the old "Hide done" chip. */
export function startingFilter(savedFilter, oldHideDone = false) {
  if (PLAN_FILTERS.some((f) => f.id === savedFilter)) return savedFilter;

  return oldHideDone ? 'todo' : DEFAULT_FILTER;
}

/** A short text that changes whenever any person's record does — to tell if a screen is out of date. */
export function peopleSignature(meetings, metIds = []) {
  const rows = meetings.map((m) => `${m.personKey}:${m.status ?? ''}:${m.updatedAt ?? 0}`).sort();

  return `${rows.join('|')}#${[...metIds].map(String).sort().join(',')}`;
}
