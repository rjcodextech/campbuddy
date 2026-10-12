// node --test tests/js  — the Social media page's reels (social-reels.js): which reels an event gets, and text that always shows whole.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { reelList, lookFor, _internals } = await import('../../resources/js/social-reels.js');

const person = (id, role, extra = {}) => ({ id, name: `Person ${id}`, photo: null, roles: [role], role_labels: [role], talk: null, card: null, ...extra });
const kit = (people) => ({ event: { name: 'WordCamp Test 2026', hashtags: '#WCTest' }, short: 'WordCamp Test', people });
const posts = [
  { key: 'save-the-date', label: 'Coming up', content: () => ({}), caption: () => 'c1' },
  { key: 'countdown', label: 'Countdown', content: () => ({}), caption: () => 'c2' },
];

test('one reel per post, then one per group, and one per five speakers', () => {
  const people = [
    ...Array.from({ length: 12 }, (_, i) => person(`s${i}`, 'speaker')),
    ...Array.from({ length: 3 }, (_, i) => person(`o${i}`, 'organizer')),
    person('v1', 'volunteer'),
    person('t1', 'table_lead'),
    person('m1', 'media_partner'),
    person('a1', 'sponsor'),
  ];
  const list = reelList(kit(people), posts);
  assert.deepEqual(list.map((r) => r.key), ['post-save-the-date', 'post-countdown', 'organizer', 'speaker-1', 'speaker-2', 'speaker-3', 'volunteer', 'media-partner', 'table-lead']);
  assert.deepEqual(list.filter((r) => r.key.startsWith('speaker')).map((r) => r.title), ['Speakers 1–5 (5)', 'Speakers 6–10 (5)', 'Speakers 11–12 (2)']);
  const speakers2 = list.find((r) => r.key === 'speaker-2').build();
  assert.equal(speakers2.people.length, 5);
  assert.equal(speakers2.first, 5);
  assert.equal(speakers2.total, 12);
  assert.equal(list.find((r) => r.key === 'organizer').build().people.length, 3, 'all organizers in one reel');
});

test('someone with two roles is in both reels; a group with nobody has no reel', () => {
  const list = reelList(kit([person('x', 'organizer', { roles: ['organizer', 'speaker'] })]), []);
  assert.deepEqual(list.map((r) => r.key), ['organizer', 'speaker']);
  assert.equal(reelList(kit([]), []).length, 0);
});

test('a group caption names everyone and credits CampBuddy and WPSimplified', () => {
  const list = reelList(kit([person('a', 'volunteer'), person('b', 'volunteer')]), []);
  const caption = list[0].caption();
  assert.match(caption, /Person a, Person b/);
  assert.match(caption, /#WCTest #CampBuddy #WPSimplified/);
});

test('text is never cut short: it gets smaller, or takes more lines, instead', () => {
  const ctx = { font: '12px x', measureText(t) { return { width: t.length * Number(this.font.split(' ')[1].replace('px', '')) * 0.5 }; } };
  const long = 'Performance budgets with the Interactivity API and a very long talk title that keeps going on and on';
  const fit = _internals.fitAll(ctx, long, 600, 60, 30, 3, 700, 'x');
  assert.equal(fit.lines.join(' '), long, 'every word is there');
  assert.ok(fit.lines.every((l) => l.length * fit.size * 0.5 <= 600), 'every line fits the width');
  assert.ok(!fit.lines.join(' ').includes('…'));
});

test('every mood keeps a steady beat the scenes can be cut to', () => {
  for (const [name, mood] of Object.entries(_internals.MOODS)) {
    assert.ok(mood.bpm >= 80 && mood.bpm <= 130, name);
    assert.equal(mood.chords.length, 4, name);
  }
});

test('no reel is shorter than 30 seconds', () => {
  assert.equal(_internals.MIN_SECONDS, 30);
});


test('no two reels of an event look or sound the same, and neighbours share no part', () => {
  const people = Array.from({ length: 40 }, (_, i) => person(i, ['organizer', 'speaker', 'volunteer'][i % 3]));
  const list = reelList(kit(people), posts);
  assert.deepEqual(list.map((r) => r.variant), list.map((_, i) => i), 'each reel knows its place');
  for (const seed of [0, 7, 123456]) {
    const looks = Array.from({ length: 60 }, (_, i) => lookFor(i, seed));
    const whole = new Set(looks.map((l) => JSON.stringify(l)));
    assert.equal(whole.size, 60, `seed ${seed}: sixty different reels`);
    looks.slice(1).forEach((l, i) => {
      const prev = looks[i];
      for (const part of ['backdrop', 'particles', 'motion', 'frame']) assert.notEqual(l[part], prev[part], part);
      assert.notEqual(l.music.key, prev.music.key, 'key');
      assert.notEqual(l.music.progression, prev.music.progression, 'chords');
      assert.notEqual(l.music.groove, prev.music.groove, 'groove');
    });
  }
});

test('each reel keeps its own tempo, still on a steady beat', () => {
  for (const mood of Object.values(_internals.MOODS)) {
    for (let i = 0; i < 12; i++) {
      const bpm = _internals.reelBpm(mood, lookFor(i));
      assert.ok(bpm >= 80 && bpm <= 130, String(bpm));
    }
  }
  assert.ok(_internals.PROGRESSIONS.every((p) => p.length === 4));
});
