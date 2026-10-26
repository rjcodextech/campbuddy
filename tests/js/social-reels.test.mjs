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

test("words stay inside Instagram's safe zone: clear of its top bar, its caption and the right rail", () => {
  const { SAFE, TEXT_W, badgeY } = _internals;
  // The combined Instagram Reels + YouTube Shorts zone (CAMPBUDDY-BRAND-GUIDELINES.md §9).
  assert.ok(SAFE.top >= 250 && SAFE.bottom <= 1470, 'the top 250 px and the foot below 1470 px are the apps’');
  assert.ok(SAFE.left >= 64 && SAFE.right <= 900, 'the like / comment / share rail sits right of 900 px');
  assert.ok(540 + TEXT_W / 2 <= SAFE.right, 'a centred line ends before the rail');
  for (const below of [600, 1200, 1400, 1700]) {
    const y = badgeY(below);
    assert.ok(y >= SAFE.top && y + 120 <= SAFE.bottom, `the partners badge stays in the zone (${below})`);
  }
});

// What each reel tells (owner, 24 Oct 2026): its own topic, one date-and-venue slide, the end card — nothing from another reel.
const FOREIGN = ['Speakers', 'Sponsors', 'Schedule', 'Talks', 'The team', 'Contributor Day', 'Organizers', 'Volunteers'];
const fullKit = () => ({
  ...kit([
    person('o1', 'organizer', { name: 'Anup Kumar' }), person('o2', 'organizer', { name: 'Devyanshi Wadhwa' }),
    person('s1', 'speaker', { name: 'Amit Singh', talk: 'Closing' }),
    person('t1', 'table_lead', { name: 'Nidhi Jain' }),
  ]),
  event: { name: 'WordCamp Test 2026', dates: '12–13 Dec 2026', place: 'Test Centre, Delhi', hashtags: '#WCTest' },
  speakers: [{ name: 'Amit Singh', photo: null }],
  sponsors: [{ name: 'Acme Hosting', logo: null }],
  tables: [{ name: 'Polyglots', leads: ['Nidhi Jain'] }],
  schedule: { days: [{ key: '2026-12-12', label: 'Sat 12 Dec' }], sessions: [{ id: 1, day: '2026-12-12', time: '10:00 AM', title: 'Closing', track: 'Hall A', speakers: ['Amit Singh', 'Ravi Kumar Sharma'], photos: [] }] },
});
const scenesOf = (spec, k = fullKit()) => _internals.v2Scenes({ kit: k, item: { variant: 0 } }, spec);
const steps = (scenes) => scenes.filter((s) => s.step).map((s) => s.step);

test('a people reel shows only its own people, then the date and venue, then the end card', () => {
  const k = fullKit();
  for (const [role, label] of [['organizer', 'Organizers'], ['table_lead', 'Table leads'], ['speaker', 'Speakers']]) {
    const people = k.people.filter((p) => p.roles.includes(role));
    const scenes = scenesOf({ kind: 'people', role, label, people, first: 0, total: people.length, utm: 'x' }, k);
    const own = steps(scenes).filter((s) => s !== 'When & where');
    assert.ok(own.length >= people.length, `${label}: one cut per person`);
    assert.ok(own.every((s) => s === label), `${label}: ${steps(scenes).join(' > ')}`);
    assert.equal(steps(scenes).filter((s) => s === 'When & where').length, 1, `${label}: one date-and-venue slide`);
    assert.ok(scenes.at(-1).end, 'ends on the CampBuddy card');
    assert.equal(scenes.filter((s) => s.end).length, 1);
  }
});

test('a post reel and a talk spotlight show only their topic', () => {
  const k = fullKit();
  const post = (key, label, body) => scenesOf({ kind: 'post', label, utm: key, content: { key, kicker: label, headline: label, line: '', body } }, k);
  const sponsors = steps(post('sponsors', 'Sponsors', { type: 'logos', logos: k.sponsors }));
  assert.deepEqual([...new Set(sponsors)], ['Sponsors', 'When & where']);
  const app = steps(post('app', 'Get CampBuddy', { type: 'features', items: ['One', 'Two', 'Three'] }));
  assert.deepEqual(app, ['Get CampBuddy', 'Get CampBuddy', 'Get CampBuddy', 'When & where'], 'one point per cut');
  assert.ok(!steps(post('thank-you', 'Thank you', { type: 'features', items: ['A'] })).includes('When & where'), 'no date slide once it is over');
  assert.ok(!steps(post('venue', 'Venue', { type: 'features', items: ['Test Centre', '12–13 Dec'] })).includes('When & where'), 'the venue post already says it');
  const coming = steps(post('save-the-date', 'Coming up', { type: 'none' }));
  assert.equal(coming[0], 'When & where', 'the date is the news of a save-the-date');
  for (const key of ['sponsors', 'app', 'thank-you', 'venue']) {
    const shown = steps(post(key, key, key === 'sponsors' ? { type: 'logos', logos: k.sponsors } : { type: 'features', items: ['A'] }));
    assert.ok(shown.every((s) => s === key || s === 'When & where' || !FOREIGN.includes(s)), `${key}: ${shown.join(' > ')}`);
  }
  const spot = scenesOf({ kind: 'session', session: k.schedule.sessions[0], label: 'Session spotlight', utm: 's' }, k);
  assert.deepEqual(steps(spot), ['On stage', 'On stage', 'When & where'], 'each speaker, then when and where');
});

test('names are whole everywhere in a reel: never only the first name', () => {
  const k = fullKit();
  const plain = (w) => String(w ?? '').replace(/\*/g, '');
  const spot = scenesOf({ kind: 'session', session: k.schedule.sessions[0], label: 'Session spotlight', utm: 's' }, k);
  assert.deepEqual(spot.filter((s) => s.step === 'On stage').map((s) => plain(s.words.headline)), ['Amit Singh', 'Ravi Kumar Sharma']);
  const organizers = k.people.filter((p) => p.roles.includes('organizer'));
  const reel = scenesOf({ kind: 'people', role: 'organizer', label: 'Organizers', people: organizers, first: 0, total: 2, utm: 'x' }, k);
  assert.equal(plain(reel[0].words.headline), 'Meet the 2 organizers', 'the hook says how many');
  assert.deepEqual(reel.filter((s) => s.words?.kicker === 'Organizer').map((s) => plain(s.words.headline)), ['Anup Kumar', 'Devyanshi Wadhwa']);
  assert.deepEqual(reel.find((s) => s.words?.kicker === 'All together').words.names, ['Anup Kumar', 'Devyanshi Wadhwa'], 'then all together, whole names');
  const leads = scenesOf({ kind: 'people', role: 'table_lead', label: 'Table leads', people: k.people.filter((p) => p.roles.includes('table_lead')), first: 0, total: 1, utm: 't' }, k);
  assert.equal(plain(leads[1].words.headline), 'Nidhi Jain');
});

test('reels run from 20 seconds to 2 minutes', () => {
  assert.equal(_internals.MIN_SECONDS, 20);
  assert.equal(_internals.MAX_SECONDS, 120);
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

test('Pulse template is default for unpicked reels, built on Daylight with wave cut and creative features', () => {
  const { REEL_TEMPLATES, v2TemplateOf, topicIconFor } = _internals;
  assert.ok(REEL_TEMPLATES.pulse, 'Pulse template exists');
  assert.equal(REEL_TEMPLATES.pulse.label, 'Pulse');
  assert.equal(REEL_TEMPLATES.pulse.cut, 'wave', 'Pulse uses wave cut transition');
  assert.deepEqual(REEL_TEMPLATES.pulse.features, {
    karaoke: true,
    markerWave: true,
    countUp: true,
    ghostReveal: true,
    loopEnd: true,
  });

  // Default is Pulse when nothing chosen in studio
  assert.equal(v2TemplateOf({ variant: 0 }, 123), 'pulse');
  assert.equal(v2TemplateOf({ variant: 1 }, 456), 'pulse');
  assert.equal(v2TemplateOf({ template: null }, 0), 'pulse');
  assert.equal(v2TemplateOf({}, 0), 'pulse');

  // Explicit studio pick is respected
  assert.equal(v2TemplateOf({ template: 'stage' }, 0), 'stage');
  assert.equal(v2TemplateOf({ template: 'daylight' }, 0), 'daylight');
  assert.equal(v2TemplateOf({ template: 'poster' }, 0), 'poster');
  assert.equal(v2TemplateOf({ template: 'photo' }, 0), 'photo');
  assert.equal(v2TemplateOf({ template: 'minimal' }, 0), 'minimal');

  // Topic motif maps correctly to CampBuddy line icons
  assert.equal(topicIconFor({ item: { key: 'speaker-1' } }), 'mic');
  assert.equal(topicIconFor({ item: { key: 'spotlight-12' } }), 'mic');
  assert.equal(topicIconFor({ item: { key: 'post-save-the-date' } }), 'calendar');
  assert.equal(topicIconFor({ item: { key: 'post-countdown' } }), 'calendar');
  assert.equal(topicIconFor({ item: { key: 'post-sponsors' } }), 'handshake');
  assert.equal(topicIconFor({ item: { key: 'table-lead' } }), 'handshake');
  assert.equal(topicIconFor({ item: { key: 'post-venue' } }), 'map-pin');
  assert.equal(topicIconFor({ item: { key: 'organizer' } }), 'users');
  assert.equal(topicIconFor({ item: { key: 'volunteer' } }), 'users');
});
