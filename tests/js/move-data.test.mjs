// node --test tests/js  — "Take it with you" (move-data.js): opens from the day-15 push only.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.document = { querySelector: () => null, querySelectorAll: () => [], getElementById: () => null };
const { shouldOpenMove } = await import('../../resources/js/attendee/move-data.js');

test('the day-15 push link opens the sheet; other visits do not', () => {
  assert.equal(shouldOpenMove('?move=1'), true);
  assert.equal(shouldOpenMove('?move=1&utm_source=push'), true);
  assert.equal(shouldOpenMove('?thanks=1'), false);
  assert.equal(shouldOpenMove(''), false);
  assert.equal(shouldOpenMove('?move=0'), false);
});
