// node --test tests/js  — where each person stands, decided in one place.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  PLAN_FILTERS, discoveryPersonKey, effectiveFilter, filterCounts, groupOf, hiddenDiscoveryIds, isBlank, isPlanned,
  matchKeys, matchesFilter, peopleSignature, personState, recordFor, startingFilter, visibleFilters,
} from '../../resources/js/attendee/people-state.js';

const person = (status = null, extra = {}) => ({ kind: 'person', id: `p${Math.random()}`, status, ...extra });
const session = (status = null, over = false) => ({ kind: 'session', id: Math.random(), status, over });

test('a discovery match is keyed d:<id>', () => {
  assert.equal(discoveryPersonKey('abc'), 'd:abc');
});

test('the state comes from the record when there is one, else from the old "I met them" list', () => {
  assert.equal(personState(undefined, false), 'none');
  assert.equal(personState(undefined, true), 'met', 'marked only in the old list: still met');
  assert.equal(personState({ status: null }), 'planned');
  assert.equal(personState({ status: 'met' }), 'met');
  assert.equal(personState({ status: 'missed' }), 'missed');
  assert.equal(personState({ status: 'skipped' }), 'skipped');
});

test('once a record exists it rules: an undone "I met them" is not met again because of the old list', () => {
  assert.equal(personState({ status: null, unplanned: true }, true), 'none');
  assert.equal(personState({ status: null }, true), 'planned');
  assert.equal(personState({ status: 'missed' }, true), 'missed');
});

test('a record made by an action on an unplanned person is on the plan only while it has a status', () => {
  assert.equal(isBlank({ unplanned: true, status: null }), true);
  assert.equal(isBlank({ unplanned: true, status: 'met' }), false);
  assert.equal(isBlank({ status: null }), false);

  assert.equal(isPlanned({ status: null }), true);
  assert.equal(isPlanned({ status: 'met' }), true);
  assert.equal(isPlanned({ status: 'missed' }), true);
  assert.equal(isPlanned({ status: 'skipped' }), false);
  assert.equal(isPlanned({ status: 'met', unplanned: true }), false, 'listed, but not part of N of M');
});

test('every item is in exactly one group', () => {
  assert.equal(groupOf(person(null)), 'todo');
  assert.equal(groupOf(person('met')), 'done');
  assert.equal(groupOf(person('missed')), 'missed');
  assert.equal(groupOf(person('skipped')), 'hidden');

  assert.equal(groupOf(session(null, false)), 'todo');
  assert.equal(groupOf(session(null, true)), 'done', 'time over: done, as the plan counts it');
  assert.equal(groupOf(session('attended')), 'done');
  assert.equal(groupOf(session('missed')), 'missed');
  assert.equal(groupOf(session('missed', true)), 'missed', 'marked missed even though the time is over');
});

test('a chip shows exactly the items of its group; "All" is everything but hidden', () => {
  const items = [person(), person('met'), person('missed'), person('skipped'), session(), session('attended'), session('missed')];

  for (const [filter, expected] of [['all', 6], ['todo', 2], ['done', 2], ['missed', 2], ['hidden', 1]]) {
    assert.equal(items.filter((i) => matchesFilter(i, filter)).length, expected, filter);
  }
});

test('the numbers on the chips are the number of cards, and add up', () => {
  const items = [person(), person(), person('met'), person('missed'), person('skipped'), session(), session('attended'), session(null, true)];
  const counts = filterCounts(items);

  assert.deepEqual(counts, { all: 7, todo: 3, done: 3, missed: 1, hidden: 1 });
  assert.equal(counts.all, counts.todo + counts.done + counts.missed, 'no item is in two groups, none is lost');
  for (const f of PLAN_FILTERS) assert.equal(counts[f.id], items.filter((i) => matchesFilter(i, f.id)).length, f.id);
});

test('only chips that have something in them are shown ("All" and "To do" always)', () => {
  assert.deepEqual(visibleFilters({ all: 0, todo: 0, done: 0, missed: 0, hidden: 0 }).map((f) => f.id), ['all', 'todo']);
  assert.deepEqual(visibleFilters({ all: 5, todo: 2, done: 3, missed: 0, hidden: 0 }).map((f) => f.id), ['all', 'todo', 'done']);
  assert.deepEqual(visibleFilters({ all: 5, todo: 2, done: 1, missed: 1, hidden: 1 }).map((f) => f.id), ['all', 'todo', 'done', 'missed', 'hidden']);
});

test('a chip that has just been emptied falls back to "All"', () => {
  const empty = { all: 4, todo: 4, done: 0, missed: 0, hidden: 0 };

  assert.equal(effectiveFilter('hidden', empty), 'all');
  assert.equal(effectiveFilter('done', empty), 'all');
  assert.equal(effectiveFilter('todo', empty), 'todo');
  assert.equal(effectiveFilter('all', empty), 'all');
  assert.equal(effectiveFilter('hidden', { ...empty, hidden: 2 }), 'hidden');
});

test('the starting chip is the saved one; the old "Hide done" becomes "To do"', () => {
  assert.equal(startingFilter('done'), 'done');
  assert.equal(startingFilter(null, false), 'all');
  assert.equal(startingFilter(null, true), 'todo');
  assert.equal(startingFilter('nonsense', true), 'todo');
  assert.equal(startingFilter('done', true), 'done', 'a saved choice wins over the old flag');
  assert.equal(startingFilter('hidden'), 'all', 'the Hidden view is never the starting page');
  assert.equal(startingFilter('hidden', true), 'todo');
});

test('the signature changes when any record changes, and not otherwise', () => {
  const a = [{ personKey: 'd:1', status: null, updatedAt: 1 }, { personKey: 'r:2', status: 'met', updatedAt: 2 }];
  const same = [a[1], a[0]];

  assert.equal(peopleSignature(a, ['x']), peopleSignature(same, ['x']), 'order does not matter');
  assert.notEqual(peopleSignature(a, ['x']), peopleSignature([{ ...a[0], status: 'met', updatedAt: 3 }, a[1]], ['x']));
  assert.notEqual(peopleSignature(a, ['x']), peopleSignature(a, ['x', 'y']));
  assert.notEqual(peopleSignature(a, []), peopleSignature([a[0]], []));
});

test('a match that is an attendee-list entry is that entry: same key as "+ Meet" on the list; otherwise d:<id>', () => {
  assert.deepEqual(matchKeys('abc', 15), { key: 'r:15', aliases: ['d:abc'] });
  assert.deepEqual(matchKeys('abc', null), { key: 'd:abc', aliases: [] });
  assert.deepEqual(matchKeys('abc'), { key: 'd:abc', aliases: [] });
});

test("a person's record: the main key, else an older one, else the one changed last; a folded-in one is ignored", () => {
  const rec = (personKey, updatedAt, extra = {}) => ({ personKey, updatedAt, status: null, ...extra });
  const person = { personKey: 'r:15', aliasKeys: ['d:abc'] };

  assert.equal(recordFor(new Map(), person), undefined);
  assert.equal(recordFor(new Map([['r:15', rec('r:15', 5)]]), person).personKey, 'r:15');
  assert.equal(recordFor(new Map([['d:abc', rec('d:abc', 5)]]), person).personKey, 'd:abc', 'only an older record: still found');
  assert.equal(recordFor(new Map([['r:15', rec('r:15', 5)], ['d:abc', rec('d:abc', 9)]]), person).personKey, 'd:abc', 'the one changed last');
  assert.equal(recordFor(new Map([['r:15', rec('r:15', 5)], ['d:abc', rec('d:abc', 9)]]), { personKey: 'r:15' }).personKey, 'r:15', 'no older key given: only the main one');
  assert.equal(recordFor(new Map([['r:15', rec('r:15', 5)], ['d:abc', rec('d:abc', 9, { mergedInto: 'r:15' })]]), person).personKey, 'r:15', 'already folded in');
});

test('a record folded into another is not part of the plan', () => {
  assert.equal(isPlanned({ status: null, mergedInto: 'r:15' }), false);
});

test('everyone hidden, by discovery id: from the record, or from an old d: key; folded-in records are left out', () => {
  const ids = hiddenDiscoveryIds([
    { personKey: 'r:1', status: 'skipped', discoveryId: 'aaa' },
    { personKey: 'd:bbb', status: 'skipped' },
    { personKey: 'r:2', status: 'skipped' },
    { personKey: 'd:ccc', status: 'skipped', mergedInto: 'r:3' },
    { personKey: 'd:ddd', status: 'met' },
  ]);

  assert.deepEqual([...ids].sort(), ['aaa', 'bbb']);
});
