// node --test tests/js  — changing a person's status writes one record the same way from every screen,
// and never deletes anything.
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { personState } from '../../resources/js/attendee/people-state.js';
import { createPeopleStatus } from '../../resources/js/attendee/people-status.js';

let rows;
let met;
let tick;
let calls;

// The same four things db.js gives; nothing that deletes exists here, so a delete could not even be tried.
const db = {
  getMeetings: async () => [...rows.values()],
  getMetHistory: async () => met.map((discoveryId) => ({ discoveryId })),
  markMet: async (eventId, discoveryId) => { calls.push(['markMet', discoveryId]); met.push(discoveryId); },
  saveMeeting: async (eventId, personKey, fields) => {
    const key = `${eventId}:${personKey}`;
    const row = { status: null, createdAt: 1, ...rows.get(key), ...fields, key, eventId, personKey, updatedAt: ++tick };
    rows.set(key, row);
    calls.push(['save', personKey, fields]);

    return row;
  },
};

const people = createPeopleStatus(db);
const ann = { personKey: 'd:ann', name: 'Ann', avatarUrl: 'https://x.test/a.png', sub: 'Developer', source: 'discovery', links: [{ type: 'website', url: 'https://ann.test' }] };
const roster = { personKey: 'r:7', name: 'Raj', avatarUrl: null, sub: 'On the attendee list', source: 'roster', links: [] };

beforeEach(() => {
  rows = new Map();
  met = [];
  tick = 0;
  calls = [];
});

test('"I met them" on someone never planned makes a record with who they are, marked unplanned, and goes in the old list too', async () => {
  const row = await people.setStatus(1, ann, 'met');

  assert.equal(row.status, 'met');
  assert.equal(row.unplanned, true);
  assert.deepEqual([row.name, row.avatarUrl, row.sub, row.source], ['Ann', 'https://x.test/a.png', 'Developer', 'discovery']);
  assert.deepEqual(row.links, [{ type: 'website', url: 'https://ann.test' }]);
  assert.deepEqual(met, ['ann']);
});

test('Met on someone already planned changes only the status: the note, the time and "planned" stay', async () => {
  await db.saveMeeting(1, 'd:ann', { ...ann, note: 'Ask about WooCommerce', at: '2026-10-03T11:00:00.000Z' });

  const row = await people.setStatus(1, ann, 'met');

  assert.equal(row.status, 'met');
  assert.equal(row.note, 'Ask about WooCommerce');
  assert.equal(row.at, '2026-10-03T11:00:00.000Z');
  assert.equal(row.unplanned, undefined);
  assert.deepEqual(calls.filter((c) => c[0] === 'save').at(-1)[2], { status: 'met' }, 'nothing else was rewritten');
});

test('only a discovery match goes in the old list; someone from the attendee list does not', async () => {
  await people.setStatus(1, roster, 'met');

  assert.deepEqual(met, []);
  assert.equal(rows.get('1:r:7').status, 'met');
});

test('missed is a status too, and does not touch the old list', async () => {
  await people.setStatus(1, ann, 'missed');

  assert.equal(rows.get('1:d:ann').status, 'missed');
  assert.deepEqual(met, []);
});

test('Undo: back to no status; an unplanned person is then as if there were no record, a planned one is planned again', async () => {
  await people.setStatus(1, ann, 'met');
  await people.setStatus(1, ann, null);
  assert.equal(personState(rows.get('1:d:ann'), met.includes('ann')), 'none', 'the old list still says met, the record rules');

  await db.saveMeeting(1, 'd:bo', { name: 'Bo', personKey: 'd:bo' });
  await people.setStatus(1, { personKey: 'd:bo' }, 'met');
  await people.setStatus(1, { personKey: 'd:bo' }, null);
  assert.equal(personState(rows.get('1:d:bo')), 'planned');
});

test('hide: kept, with everything, and what it was before is remembered', async () => {
  await db.saveMeeting(1, 'd:ann', { ...ann, note: 'Coffee?' });

  const row = await people.hide(1, ann);

  assert.equal(row.status, 'skipped');
  assert.equal(row.statusBeforeSkip, null);
  assert.equal(row.note, 'Coffee?');
  assert.equal(rows.size, 1, 'no record removed');
});

test('hide someone who was never planned: a record is made so they can be brought back; someone met is remembered as met', async () => {
  const fresh = await people.hide(1, ann);
  assert.equal(fresh.status, 'skipped');
  assert.equal(fresh.unplanned, true);
  assert.equal(fresh.name, 'Ann');

  const oldMet = await people.hide(1, { ...ann, personKey: 'd:old' }, { wasMet: true });
  assert.equal(oldMet.statusBeforeSkip, 'met');
});

test('hiding someone who was met, then showing them again, gives back "met" (not "not met")', async () => {
  await people.setStatus(1, ann, 'met');
  await people.hide(1, ann);
  assert.equal(rows.get('1:d:ann').statusBeforeSkip, 'met');

  const back = await people.unhide(1, ann);
  assert.equal(back.status, 'met');
  assert.equal(back.statusBeforeSkip, null);
});

test('show again restores a planned person with their note, and a missed one as missed', async () => {
  await db.saveMeeting(1, 'd:ann', { ...ann, note: 'Coffee?' });
  await people.hide(1, ann);
  const planned = await people.unhide(1, ann);
  assert.equal(planned.status, null);
  assert.equal(planned.note, 'Coffee?');
  assert.equal(personState(planned), 'planned');

  await people.setStatus(1, ann, 'missed');
  await people.hide(1, ann);
  assert.equal((await people.unhide(1, ann)).status, 'missed');
});

test('hiding twice does not lose what it was; showing someone who is not hidden changes nothing', async () => {
  await people.setStatus(1, ann, 'missed');
  await people.hide(1, ann);
  await people.hide(1, ann);
  assert.equal(rows.get('1:d:ann').statusBeforeSkip, 'missed');

  await people.unhide(1, ann);
  const before = rows.get('1:d:ann').updatedAt;
  const same = await people.unhide(1, ann);
  assert.equal(same.updatedAt, before, 'nothing was written');
  assert.equal(await people.unhide(1, { personKey: 'd:nobody' }), null);
});

test('load gives the records by key and the old "I met them" ids', async () => {
  await people.setStatus(1, ann, 'met');
  met.push('legacy');

  const loaded = await people.load(1);

  assert.equal(loaded.meetings.length, 1);
  assert.equal(loaded.meetingsByKey.get('d:ann').status, 'met');
  assert.deepEqual([...loaded.metIds].sort(), ['ann', 'legacy']);
});

test('nothing here ever calls anything but the four db functions, and no delete is possible', async () => {
  await people.setStatus(1, ann, 'met');
  await people.hide(1, roster);
  await people.unhide(1, roster);

  assert.ok(calls.every((c) => c[0] === 'save' || c[0] === 'markMet'));
  assert.equal(rows.size, 2, 'both records are still there');
});
