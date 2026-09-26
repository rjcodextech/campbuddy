// Changing a person's status — "I met them", Met, Couldn't, hide, show again —
// in one place, so every screen writes the same record the same way (see
// people-state.js for what the record is).
//
// Nothing is ever deleted here: a status changes, the note, the time and
// everything else stay. Only "Clear my data" removes anything.

import { getMeetings, getMetHistory, markMet, saveMeeting } from './db.js';
import { discoveryPersonKey, personState } from './people-state.js';

const DISCOVERY_PREFIX = 'd:';

/** The parts of a person that are kept with the record the first time it is made. */
const identityOf = (person) => ({
  name: person.name ?? null,
  avatarUrl: person.avatarUrl ?? null,
  sub: person.sub ?? null,
  source: person.source ?? 'discovery',
  links: person.links ?? [],
});

/**
 * @param {object} db  { getMeetings, getMetHistory, markMet, saveMeeting } — the real ones by default
 */
export function createPeopleStatus(db) {
  const recordOf = async (eventId, personKey) => (await db.getMeetings(eventId)).find((m) => m.personKey === personKey);

  return {
    /** Everything a screen needs to place people: the records by key, and the old "I met them" ids. */
    async load(eventId) {
      const [meetings, metHistory] = await Promise.all([db.getMeetings(eventId), db.getMetHistory(eventId)]);

      return {
        meetings,
        meetingsByKey: new Map(meetings.map((m) => [m.personKey, m])),
        metIds: new Set(metHistory.map((m) => m.discoveryId)),
      };
    },

    /**
     * met / missed / null (back to "not yet"). A person with no record yet gets
     * one, marked unplanned; one who has a record keeps its note and time.
     * Marking a discovery match met also goes in the old list, so a build that
     * still reads only that list agrees.
     */
    async setStatus(eventId, person, status) {
      const existing = await recordOf(eventId, person.personKey);
      const row = await db.saveMeeting(eventId, person.personKey, existing ? { status } : { ...identityOf(person), status, unplanned: true });

      if (status === 'met' && person.personKey.startsWith(DISCOVERY_PREFIX)) {
        await db.markMet(eventId, person.personKey.slice(DISCOVERY_PREFIX.length));
      }

      return row;
    },

    /** ✕ / "Hide from plan": out of sight, everything kept, what it was is remembered. */
    async hide(eventId, person, { wasMet = false } = {}) {
      const existing = await recordOf(eventId, person.personKey);

      if (existing?.status === 'skipped') return existing;

      const before = existing ? (existing.status ?? null) : (wasMet ? 'met' : null);

      return db.saveMeeting(eventId, person.personKey, existing
        ? { status: 'skipped', statusBeforeSkip: before }
        : { ...identityOf(person), status: 'skipped', statusBeforeSkip: before, unplanned: true });
    },

    /** "Show again": back to what it was before it was hidden. */
    async unhide(eventId, person) {
      const existing = await recordOf(eventId, person.personKey);

      if (!existing || existing.status !== 'skipped') return existing ?? null;

      return db.saveMeeting(eventId, person.personKey, { status: existing.statusBeforeSkip ?? null, statusBeforeSkip: null });
    },
  };
}

export const peopleStatus = createPeopleStatus({ getMeetings, getMetHistory, markMet, saveMeeting });

/** The state of one discovery match, from what `load()` returned. */
export function stateOfMatch(loaded, discoveryId) {
  return personState(loaded.meetingsByKey.get(discoveryPersonKey(discoveryId)), loaded.metIds.has(discoveryId));
}
