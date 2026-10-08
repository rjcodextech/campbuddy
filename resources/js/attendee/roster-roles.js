// The attendee list's role marks (Explore → People): who is also an
// organizer, speaker, volunteer or microsponsor of this WordCamp — worked out
// on the server (App\Support\RosterRoles) and sent with each roster entry.
// Pure helpers here; people.js draws the badges and the filter chips.

export const ROLE_ORDER = ['organizer', 'speaker', 'volunteer', 'microsponsor'];

export const ROLE_LABELS = {
  organizer: 'Organizer',
  speaker: 'Speaker',
  volunteer: 'Volunteer',
  microsponsor: 'Microsponsor',
};

/** Plural chip labels: "Organizers 15". */
export const ROLE_FILTER_LABELS = {
  all: 'All',
  organizer: 'Organizers',
  speaker: 'Speakers',
  volunteer: 'Volunteers',
  microsponsor: 'Microsponsors',
};

export const ALL = 'all';

/** The known roles of an entry, in badge order (anything unknown is dropped). */
export function rolesOf(entry) {
  const roles = Array.isArray(entry?.roles) ? entry.roles : [];

  return ROLE_ORDER.filter((role) => roles.includes(role));
}

/** { all, organizer, speaker, … } — how many entries each chip would show. */
export function roleCounts(entries) {
  const counts = { [ALL]: entries.length };
  ROLE_ORDER.forEach((role) => { counts[role] = 0; });
  entries.forEach((entry) => rolesOf(entry).forEach((role) => { counts[role] += 1; }));

  return counts;
}

/** Chips worth showing: none at all when nobody has a role, else All plus each role someone has. */
export function visibleRoleFilters(counts) {
  const roles = ROLE_ORDER.filter((role) => counts[role] > 0);

  return roles.length === 0 ? [] : [ALL, ...roles];
}

export function matchesRole(entry, filter) {
  return !filter || filter === ALL || rolesOf(entry).includes(filter);
}

/** A remembered filter that no longer has anyone falls back to All. */
export function effectiveRoleFilter(filter, counts) {
  return filter && filter !== ALL && counts[filter] > 0 ? filter : ALL;
}
