// node --test tests/js  — role marks on the attendee list (roster-roles.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ALL, effectiveRoleFilter, matchesRole, roleCounts, rolesOf, visibleRoleFilters } from '../../resources/js/attendee/roster-roles.js';

const list = [
  { name: 'A', roles: ['speaker', 'organizer'] },
  { name: 'B', roles: ['volunteer'] },
  { name: 'C', roles: [] },
  { name: 'D' },
  { name: 'E', roles: ['sponsor-person', 'speaker'] },
];

test('roles come in badge order, unknown ones dropped, missing ones empty', () => {
  assert.deepEqual(rolesOf(list[0]), ['organizer', 'speaker']);
  assert.deepEqual(rolesOf(list[3]), []);
  assert.deepEqual(rolesOf(list[4]), ['speaker']);
});

test('chips count the whole list and show only roles someone has', () => {
  const counts = roleCounts(list);
  assert.equal(counts.all, 5);
  assert.equal(counts.speaker, 2);
  assert.equal(counts.microsponsor, 0);
  assert.deepEqual(visibleRoleFilters(counts), [ALL, 'organizer', 'speaker', 'volunteer']);
  assert.deepEqual(visibleRoleFilters(roleCounts([{ name: 'x' }])), [], 'nobody with a role: no chips at all');
});

test('a filter keeps only its people; an emptied one falls back to All', () => {
  assert.deepEqual(list.filter((e) => matchesRole(e, 'speaker')).map((e) => e.name), ['A', 'E']);
  assert.equal(list.filter((e) => matchesRole(e, ALL)).length, 5);
  assert.equal(effectiveRoleFilter('microsponsor', roleCounts(list)), ALL);
  assert.equal(effectiveRoleFilter('volunteer', roleCounts(list)), 'volunteer');
});
