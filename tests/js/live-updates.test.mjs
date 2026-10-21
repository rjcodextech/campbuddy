import { test } from 'node:test';
import assert from 'node:assert/strict';

const { seenDue, inAskWindow } = await import('../../resources/js/attendee/live-updates.js');

test('the "event open" ping goes at most every six hours', () => {
  const now = Date.parse('2026-11-21T10:00:00Z');
  assert.equal(seenDue(null, now), true);
  assert.equal(seenDue(String(now - 60_000), now), false);
  assert.equal(seenDue(String(now - 6 * 3600_000), now), true);
  assert.equal(seenDue('garbage', now), true);
});

test('the ask to allow notifications shows from three days before the first day to the last day', () => {
  const at = (d) => new Date(`${d}T12:00:00Z`);
  assert.equal(inAskWindow('2026-11-21', '2026-11-22', at('2026-11-17')), false);
  assert.equal(inAskWindow('2026-11-21', '2026-11-22', at('2026-11-18')), true);
  assert.equal(inAskWindow('2026-11-21', '2026-11-22', at('2026-11-22')), true);
  assert.equal(inAskWindow('2026-11-21', '2026-11-22', at('2026-11-23')), false);
  assert.equal(inAskWindow('', '', at('2026-11-21')), false);
});
