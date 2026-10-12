// node --test tests/js  — "Bring it from your last WordCamp" (bring-data.js): what comes along, from where, and who is matched.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.window = {};
globalThis.document = { querySelector: () => null, querySelectorAll: () => [], getElementById: () => null };
const { carriedFields, pickSource, matchPeople } = await import('../../resources/js/attendee/bring-data.js');

test('the join answers come along, never the other event\'s attendee-list entry', () => {
  const fields = carriedFields({ tags: ['developer', 'designer'], profession: 'Dev', who_to_meet: 'Plugin folks', wporg_username: 'asha', display_name: null, attendee_roster_id: 42 });
  assert.deepEqual(fields, { tags: ['developer', 'designer'], profession: 'Dev', who_to_meet: 'Plugin folks', wporg_username: 'asha', display_name: null });
  assert.equal(carriedFields({ tags: [], attendee_roster_id: 3 }), null, 'nothing worth bringing');
  assert.equal(carriedFields(null), null);
});

test('the newest past WordCamp with something to bring is offered', () => {
  const past = [{ id: 2, name: 'Newer', daysAgo: 3 }, { id: 1, name: 'Older', daysAgo: 20 }];
  assert.equal(pickSource(past, { 1: { fields: { tags: ['developer'] }, meetings: [] }, 2: { fields: null, meetings: [] } }).event.name, 'Older', 'the newer one holds nothing');
  const both = pickSource(past, { 2: { fields: null, meetings: [{ name: 'Asha Rao' }, { name: 'Merged', mergedInto: 'r:1' }] }, 1: { fields: { tags: ['x'] } } });
  assert.equal(both.event.name, 'Newer');
  assert.equal(both.people.length, 1, 'merged rows are not people');
  assert.equal(pickSource(past, {}), null);
});

test('people are matched by Gravatar, else by full name, once each', () => {
  const roster = [
    { id: 10, name: 'Asha Rao', gravatar_url: 'https://secure.gravatar.com/avatar/aaaabbbbccccddddeeeeffff00001111?s=96' },
    { id: 11, name: 'Ravi Kumar', gravatar_url: null },
    { id: 12, name: 'Amit', gravatar_url: null },
  ];
  const people = [
    { name: 'A. Rao', avatarUrl: 'https://gravatar.com/avatar/AAAABBBBCCCCDDDDEEEEFFFF00001111?d=404' },
    { name: 'ravi  kumar', avatarUrl: null },
    { name: 'Amit', avatarUrl: null },
    { name: 'Asha Rao', avatarUrl: null },
  ];
  assert.deepEqual(matchPeople(people, roster).map((m) => m.entry.id), [10, 11], 'one-word names are too risky to match; nobody twice');
});
