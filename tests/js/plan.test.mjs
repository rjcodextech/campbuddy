// node --test tests/js  — the plan: what counts towards "N of M done", and where hidden people go.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { computePlan } from '../../resources/js/attendee/plan.js';

const NOW = Date.UTC(2026, 9, 3, 10, 0);
const HOUR = 60 * 60 * 1000;
const bookmark = (sessionId, extra = {}) => ({ sessionId, startMs: NOW + HOUR, endMs: NOW + 2 * HOUR, ...extra });
const meeting = (personKey, extra = {}) => ({ personKey, name: personKey, status: null, ...extra });

test('sessions and planned people are counted; done means attended / met / couldn\'t', () => {
  const plan = computePlan(
    [bookmark(1), bookmark(2, { status: 'attended' })],
    [meeting('d:a'), meeting('d:b', { status: 'met' }), meeting('d:c', { status: 'missed' })],
    new Map(),
    NOW
  );

  assert.equal(plan.total, 5);
  assert.equal(plan.done, 3);
  assert.equal(plan.left, 2);
  assert.equal(plan.people.length, 3);
  assert.equal(plan.hidden.length, 0);
});

test('a person hidden with ✕ is kept, listed apart, and not counted', () => {
  const plan = computePlan([bookmark(1)], [meeting('d:a'), meeting('d:z', { status: 'skipped', statusBeforeSkip: null, note: 'Coffee?' })], new Map(), NOW);

  assert.deepEqual(plan.people.map((p) => p.id), ['d:a']);
  assert.deepEqual(plan.hidden.map((p) => p.id), ['d:z']);
  assert.equal(plan.hidden[0].meeting.note, 'Coffee?', 'the note is still there');
  assert.equal(plan.total, 2, 'one session and one planned person');
});

test('someone marked "I met them" but never planned is listed as met, and is not part of N of M', () => {
  const plan = computePlan([bookmark(1)], [meeting('d:a'), meeting('d:new', { status: 'met', unplanned: true })], new Map(), NOW);

  assert.deepEqual(plan.people.map((p) => [p.id, p.status]), [['d:a', null], ['d:new', 'met']]);
  assert.equal(plan.total, 2);
  assert.equal(plan.done, 0);
  assert.equal(plan.left, 2);
});

test('an unplanned record with no status (undone) is not on the plan at all', () => {
  const plan = computePlan([], [meeting('d:x', { status: null, unplanned: true })], new Map(), NOW);

  assert.equal(plan.people.length, 0);
  assert.equal(plan.hidden.length, 0);
  assert.equal(plan.total, 0);
});

test('once someone marked "I met them" is planned through the Meet sheet (unplanned: false) they count', () => {
  const plan = computePlan([], [meeting('d:new', { status: 'met', unplanned: false })], new Map(), NOW);

  assert.equal(plan.total, 1);
  assert.equal(plan.done, 1);
});

test('an empty plan is all zeros', () => {
  const plan = computePlan([], [], new Map(), NOW);

  assert.deepEqual([plan.total, plan.done, plan.left, plan.people.length, plan.hidden.length], [0, 0, 0, 0, 0]);
});

test('a person kept under two keys is listed once: the record folded into the other is left out', () => {
  const plan = computePlan([], [meeting('r:15', { status: 'met' }), meeting('d:cara', { mergedInto: 'r:15', status: 'missed' })], new Map(), NOW);

  assert.deepEqual(plan.people.map((p) => p.id), ['r:15']);
  assert.equal(plan.hidden.length, 0);
  assert.equal(plan.total, 1);
});
