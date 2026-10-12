// node --test tests/js  — Analytics → Usage post (usage-post.js): which cards a WordCamp's numbers can fill.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { plan } = await import('../../resources/js/usage-post.js');

const data = (over = {}) => ({
  stats: [{ key: 'users', value: 61 }, { key: 'pages', value: 583 }, { key: 'session_save', value: 59 }],
  tiles: [{ key: 'users', value: 61 }, { key: 'pages', value: 583 }, { key: 'session_save', value: 59 }],
  daily: [{ date: '2026-10-03', users: 27 }, { date: '2026-10-04', users: 25 }],
  talks: [{ title: 'A', saves: 5 }, { title: 'B', saves: 4 }],
  ...over,
});

test('a WordCamp with enough of everything gets all five cards', () => {
  assert.deepEqual(plan(data()).map((c) => c[0]), ['cover', 'tiles', 'daily', 'talks', 'thanks']);
});

test('a card without enough real data is left out', () => {
  assert.deepEqual(plan(data({ talks: [{ title: 'A', saves: 9 }], daily: [{ date: '2026-10-03', users: 5 }] })).map((c) => c[0]), ['cover', 'tiles', 'thanks']);
  assert.deepEqual(plan(data({ stats: [{ key: 'users', value: 2 }] })), [], 'two people is not a post');
});
