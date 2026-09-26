// Changing a person's status — "Met", "Couldn't meet", hide, show again — in one
// place, so every screen writes the same record the same way (see people-state.js
// for what the record is).
//
// Nothing is ever deleted here: a status changes, the note, the time and
// everything else stay. Only "Clear my data" removes anything.

import { getMeetings, getMetHistory, markMet, saveMeeting } from './db.js';
import { discoveryPersonKey, matchKeys, personState, recordFor } from './people-state.js';

const DISCOVERY_PREFIX = 'd:';

/** The parts of a person that are kept with the record the first time it is made. */
const identityOf = (person) => ({
  name: person.name ?? null,
  avatarUrl: person.avatarUrl ?? null,
  sub: person.sub ?? null,
  source: person.source ?? 'discovery',
  links: person.links ?? [],
  ...(person.discoveryId ? { discoveryId: person.discoveryId } : {}),
});

/** What is copied from an older record into the main one. */
const CARRIED = ['name', 'avatarUrl', 'sub', 'source', 'links', 'note', 'at', 'status', 'statusBeforeSkip', 'unplanned', 'createdAt', 'discoveryId'];
const carried = (row) => Object.fromEntries(CARRIED.filter((k) => row[k] !== undefined).map((k) => [k, row[k]]));

/**
 * @param {object} db  { getMeetings, getMetHistory, markMet, saveMeeting } — the real ones by default
 */
export function createPeopleStatus(db) {
  /**
   * The person's record under their main key. If the only record is under an
   * older key (`d:<id>`, from before an attendee-list match was one person with
   * its entry), it is copied to the main key and the old one marked `mergedInto`
   * — so the person is listed once. Neither is deleted.
   */
  async function canonical(eventId, person) {
    const byKey = new Map((await db.getMeetings(eventId)).map((m) => [m.personKey, m]));
    const main = byKey.get(person.personKey);
    const olds = (person.aliasKeys ?? []).map((k) => byKey.get(k)).filter((r) => r && r.mergedInto !== person.personKey);
    const newest = [main, ...olds].filter(Boolean).sort((a, b) => (b.updatedAt ?? 0) - (a.updatedAt ?? 0))[0];

    if (!newest) return null;

    const row = newest === main ? main : await db.saveMeeting(eventId, person.personKey, carried(newest));
    for (const old of olds) await db.saveMeeting(eventId, old.personKey, { mergedInto: person.personKey });

    return row;
  }

  const linkOf = (person) => (person.discoveryId ? { discoveryId: person.discoveryId } : {});

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

    /** The person's record under their main key, folding an older one in first; null if they have none. */
    adopt: canonical,

    /**
     * met / missed / null (back to "not yet"). A person with no record yet gets
     * one, marked unplanned; one who has a record keeps its note and time.
     * Marking a discovery match met also goes in the old list, so a build that
     * still reads only that list agrees.
     */
    async setStatus(eventId, person, status) {
      const existing = await canonical(eventId, person);
      const row = await db.saveMeeting(eventId, person.personKey, existing
        ? { status, ...linkOf(person) }
        : { ...identityOf(person), status, unplanned: true });

      const legacyId = person.discoveryId ?? (person.personKey.startsWith(DISCOVERY_PREFIX) ? person.personKey.slice(DISCOVERY_PREFIX.length) : null);
      if (status === 'met' && legacyId) await db.markMet(eventId, legacyId);

      return row;
    },

    /** ✕ / "Hide": out of sight, everything kept, what it was is remembered. */
    async hide(eventId, person, { wasMet = false } = {}) {
      const existing = await canonical(eventId, person);

      if (existing?.status === 'skipped') return existing;

      const before = existing ? (existing.status ?? null) : (wasMet ? 'met' : null);

      return db.saveMeeting(eventId, person.personKey, existing
        ? { status: 'skipped', statusBeforeSkip: before, ...linkOf(person) }
        : { ...identityOf(person), status: 'skipped', statusBeforeSkip: before, unplanned: true });
    },

    /** "Show again": back to what it was before it was hidden. */
    async unhide(eventId, person) {
      const existing = await canonical(eventId, person);

      if (!existing || existing.status !== 'skipped') return existing ?? null;

      return db.saveMeeting(eventId, person.personKey, { status: existing.statusBeforeSkip ?? null, statusBeforeSkip: null, ...linkOf(person) });
    },
  };
}

export const peopleStatus = createPeopleStatus({ getMeetings, getMetHistory, markMet, saveMeeting });

/** The state of one discovery match, from what `load()` returned. */
export function stateOfMatch(loaded, discoveryId, rosterId = null) {
  const { key, aliases } = matchKeys(discoveryId, rosterId);

  return personState(recordFor(loaded.meetingsByKey, { personKey: key, aliasKeys: aliases }), loaded.metIds.has(discoveryId));
}

export { discoveryPersonKey };
