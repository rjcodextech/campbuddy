// node --test tests/js  — Contributor Day tables on the Contribute tab (contribute.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.window = {};
globalThis.location = { href: 'https://campbuddy.club/event/x/contribute', origin: 'https://campbuddy.club', search: '' };
globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };

const { groupTables, whereText } = await import('../../resources/js/attendee/contribute.js');

const tables = [
  { team: 'polyglots', name: 'Polyglots', place: 'Floor 2 · Table 5', leads: ['Asha', 'Ravi'] },
  { team: 'polyglots', name: 'Polyglots: Hindi', place: 'Table 6', leads: [] },
  { team: 'other', name: 'Hackathon', place: 'Hall C', leads: ['Mia'] },
  { team: 'core', name: 'Core', place: '', leads: [] },
];

test('tables are grouped by team; "other" tables are not tied to a team card', () => {
  const byTeam = groupTables(tables);
  assert.equal(byTeam.get('polyglots').length, 2);
  assert.equal(byTeam.has('other'), false);
  assert.deepEqual(groupTables(null), new Map());
});

test('a team card says where its tables are and who leads them', () => {
  const byTeam = groupTables(tables);
  assert.equal(whereText(byTeam.get('polyglots')), 'Floor 2 · Table 5 · Leads: Asha, Ravi | Table 6');
  assert.equal(whereText(byTeam.get('core')), '', 'a table with nothing filled in adds no line');
  assert.equal(whereText(undefined), '');
});
