// node --test tests/js  — onboarding, Camp Card and discovery fill each other's empty fields.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { test } from 'node:test';
import {
  campCardPrefill, discoveryPrefill, onboardingAfterDiscovery, wporgProfileUrl, wporgUsername,
} from '../../resources/js/attendee/profile-sync.js';

const TAGS = [
  'developer', 'designer', 'content creator', 'site builder',
  'community organizer', 'marketer', 'business owner', 'blogger',
  'translator', 'speaker', 'student', 'mentor',
];

const onboarding = {
  firstWordCamp: 'yes',
  interests: ['Student', 'Content creator', 'Developer'],
  whoToMeet: '  other plugin developers ',
  attendingContributorDay: 'yes',
  completedAt: 1,
};

const campCard = {
  name: 'Sunil Kumar',
  role: 'WordPress Engineer',
  wordpressOrg: 'https://profiles.wordpress.org/sunilkumar/',
  city: 'Jaipur',
};

test('a new discovery profile starts from the onboarding answers and the Camp Card', () => {
  assert.deepEqual(discoveryPrefill({ onboarding, campCard, tags: TAGS, maxTags: 5 }), {
    tags: ['developer', 'content creator', 'student'],
    who_to_meet: 'other plugin developers',
    display_name: 'Sunil Kumar',
    profession: 'WordPress Engineer',
    wporg_username: 'sunilkumar',
  });
});

test('only tags discovery offers are kept, and never more than it takes', () => {
  const many = { interests: ['Developer', 'Designer', 'Marketer', 'Student', 'Site builder', 'Business owner', 'Astronaut'] };
  const { tags } = discoveryPrefill({ onboarding: many, tags: TAGS, maxTags: 5 });
  assert.equal(tags.length, 5);
  assert.ok(!tags.includes('astronaut'));
});

test('nothing to go on: an empty form, never a crash', () => {
  assert.deepEqual(discoveryPrefill({ tags: TAGS, maxTags: 5 }), {
    tags: [], who_to_meet: null, display_name: null, profession: null, wporg_username: null,
  });
  assert.deepEqual(campCardPrefill(null, null), {});
});

test('a WordPress.org profile link and a username turn into each other', () => {
  assert.equal(wporgUsername('profiles.wordpress.org/jane'), 'jane');
  assert.equal(wporgUsername('https://profiles.wordpress.org/jane/'), 'jane');
  assert.equal(wporgUsername('jane'), 'jane');
  assert.equal(wporgUsername('not a username'), null);
  assert.equal(wporgProfileUrl('jane'), 'https://profiles.wordpress.org/jane/');
  assert.equal(wporgProfileUrl(''), null);
});

test('an empty Camp Card starts from the discovery profile', () => {
  const discovery = { fields: { display_name: null, profession: 'Plugin developer', wporg_username: 'jane' }, card: { name: 'Jane Doe' } };
  assert.deepEqual(campCardPrefill(null, discovery), {
    name: 'Jane Doe',
    role: 'Plugin developer',
    wordpressOrg: 'https://profiles.wordpress.org/jane/',
  });
});

test('what the Camp Card already has is never replaced', () => {
  const discovery = { fields: { display_name: 'Someone Else', profession: 'Designer', wporg_username: 'other' } };
  assert.deepEqual(campCardPrefill(campCard, discovery), {});
  assert.deepEqual(campCardPrefill({ name: 'Sunil Kumar' }, discovery), { role: 'Designer', wordpressOrg: 'https://profiles.wordpress.org/other/' });
});

test('saving discovery updates the onboarding tags and "who to meet", and keeps the rest', () => {
  const after = onboardingAfterDiscovery(onboarding, { tags: ['designer', 'content creator'], who_to_meet: 'agency owners' });
  assert.deepEqual(after.interests, ['Designer', 'Content creator']);
  assert.equal(after.whoToMeet, 'agency owners');
  assert.equal(after.firstWordCamp, 'yes');
  assert.equal(after.attendingContributorDay, 'yes');
  assert.equal(after.completedAt, 1);

  assert.deepEqual(onboardingAfterDiscovery(null, { tags: ['student'] }), { interests: ['Student'], whoToMeet: null });
});

test('the onboarding chips and the discovery tags are the same words, so they line up', () => {
  const welcome = fs.readFileSync(new URL('../../resources/views/welcome.blade.php', import.meta.url), 'utf8');
  const block = /@foreach \(\[([^\]]+)\] as \$tag\)/.exec(welcome);
  assert.ok(block, 'the onboarding chip list was found');
  const chips = [...block[1].matchAll(/'([^']+)'/g)].map((m) => m[1].toLowerCase());
  for (const chip of chips) assert.ok(TAGS.includes(chip), `"${chip}" is also a discovery tag`);

  const people = fs.readFileSync(new URL('../../resources/js/attendee/people.js', import.meta.url), 'utf8');
  for (const tag of TAGS) assert.ok(people.includes(`'${tag}'`), `people.js still offers "${tag}"`);
});
