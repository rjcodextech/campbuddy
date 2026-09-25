// node --test tests/js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  EVENT_WINDOW_MS, MAX_BACKOFF_MS, QUIET_MS, dayAt, inEventWindow, nextDelay, retryAfterMs,
} from '../../resources/js/attendee/polling.js';

const at = (iso) => new Date(iso);

test('around the event days phones ask about every 5 minutes, otherwise about every 15', () => {
  for (const random of [() => 0, () => 0.5, () => 0.999]) {
    const busy = nextDelay({ inWindow: true, random });
    const quiet = nextDelay({ inWindow: false, random });

    assert.ok(busy >= EVENT_WINDOW_MS * 0.8 && busy <= EVENT_WINDOW_MS * 1.2, `event window ${busy}`);
    assert.ok(quiet >= QUIET_MS * 0.8 && quiet <= QUIET_MS * 1.2, `quiet ${quiet}`);
  }
});

test('the wait is spread +-20% so a crowd never asks in step', () => {
  const waits = new Set(Array.from({ length: 200 }, () => Math.round(nextDelay({ inWindow: true }))));
  assert.ok(waits.size > 100, 'delays differ from phone to phone');
});

test('a failed ask backs off, doubling, up to 15 minutes', () => {
  const half = () => 0.5; // no spread: exactly the base
  const base = EVENT_WINDOW_MS;

  assert.equal(nextDelay({ inWindow: true, failures: 0, random: half }), base);
  assert.equal(nextDelay({ inWindow: true, failures: 1, random: half }), base * 2);
  assert.equal(nextDelay({ inWindow: true, failures: 2, random: half }), MAX_BACKOFF_MS); // 20 min, capped
  assert.equal(nextDelay({ inWindow: true, failures: 9, random: half }), MAX_BACKOFF_MS);
  assert.equal(nextDelay({ inWindow: false, failures: 5, random: half }), MAX_BACKOFF_MS);
});

test('the server Retry-After is honoured, in seconds or as a date, and never trusted beyond 15 minutes', () => {
  const now = Date.parse('2026-10-10T10:00:00Z');

  assert.equal(retryAfterMs('120', now), 120_000);
  assert.equal(retryAfterMs('Sat, 10 Oct 2026 10:03:00 GMT', now), 180_000);
  assert.equal(retryAfterMs('999999', now), MAX_BACKOFF_MS);
  assert.equal(retryAfterMs('Sat, 10 Oct 2026 09:00:00 GMT', now), 0, 'a date in the past');
  assert.equal(retryAfterMs('soon', now), 0);
  assert.equal(retryAfterMs(null, now), 0);
  assert.equal(retryAfterMs('', now), 0);
  assert.equal(retryAfterMs('-5', now), 0);

  assert.equal(nextDelay({ inWindow: true, retryAfter: 600_000, random: () => 0.5 }), 600_000, 'waits at least as long as asked');
  assert.equal(nextDelay({ inWindow: true, retryAfter: 1_000, random: () => 0.5 }), EVENT_WINDOW_MS, 'a short Retry-After does not shorten the normal wait');
});

test('the event window runs from the day before the start to the day after the end, at the venue', () => {
  const facts = { start: '2026-10-10', end: '2026-10-11', timezone: 'Asia/Kolkata' };

  assert.equal(inEventWindow(facts, at('2026-10-08T12:00:00Z')), false);
  assert.equal(inEventWindow(facts, at('2026-10-09T12:00:00Z')), true, 'the day before');
  assert.equal(inEventWindow(facts, at('2026-10-11T12:00:00Z')), true);
  assert.equal(inEventWindow(facts, at('2026-10-12T12:00:00Z')), true, 'the day after');
  assert.equal(inEventWindow(facts, at('2026-10-13T12:00:00Z')), false);
});

test('the venue clock decides the day, not the phone', () => {
  // 20:00 UTC on the 9th is already 01:30 on the 10th in Kolkata (and 13:00 on the 9th in LA).
  assert.equal(dayAt(at('2026-10-09T20:00:00Z'), 'Asia/Kolkata'), '2026-10-10');
  assert.equal(dayAt(at('2026-10-09T20:00:00Z'), 'America/Los_Angeles'), '2026-10-09');

  const facts = { start: '2026-10-12', end: '2026-10-12', timezone: 'Pacific/Auckland' };
  // 10:30 UTC on the 10th is 23:30 on the 10th in Auckland (UTC+13): two days before the start, outside the window.
  assert.equal(inEventWindow(facts, at('2026-10-10T10:30:00Z')), false);
  assert.equal(inEventWindow(facts, at('2026-10-11T10:30:00Z')), true);
});

test('with no end date the start date stands in for it; with no start date there is no window', () => {
  assert.equal(inEventWindow({ start: '2026-10-10', end: null, timezone: 'UTC' }, at('2026-10-11T12:00:00Z')), true);
  assert.equal(inEventWindow({ start: '2026-10-10', end: null, timezone: 'UTC' }, at('2026-10-12T12:00:00Z')), false);
  assert.equal(inEventWindow({ start: null, end: null, timezone: null }, at('2026-10-10T12:00:00Z')), false);
  assert.equal(inEventWindow(undefined, at('2026-10-10T12:00:00Z')), false);
});

test('an unknown or invalid time zone falls back to the device own instead of throwing', () => {
  assert.match(dayAt(at('2026-10-10T12:00:00Z'), 'Not/AZone'), /^\d{4}-\d{2}-\d{2}$/);
  assert.match(dayAt(at('2026-10-10T12:00:00Z'), null), /^\d{4}-\d{2}-\d{2}$/);
  assert.equal(inEventWindow({ start: '2026-10-10', end: '2026-10-10', timezone: 'Not/AZone' }, at('2026-10-10T12:00:00Z')), true);
});
