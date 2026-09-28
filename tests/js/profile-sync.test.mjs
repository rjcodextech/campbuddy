// node --test tests/js  — onboarding, Camp Card, discovery and Contribute fill each other's empty fields.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { test } from 'node:test';
import {
  DESCRIBE_TAGS, MAX_DESCRIBE_TAGS, campCardPrefill, contributePrefill, describeTags, discoveryPrefill,
  onboardingAfterCampCard, onboardingAfterDiscovery, onboardingWithContributorDay, wporgProfileUrl, wporgUsername,
} from '../../resources/js/attendee/profile-sync.js';
import { CONTRIB_QUESTION_TAGS } from '../../resources/js/attendee/contrib-teams.js';

const read = (path) => fs.readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const onboarding = {
  firstWordCamp: 'yes',
  interests: ['student', 'content creator', 'developer'],
  profession: 'Plugin developer',
  whoToMeet: '  other plugin developers ',
  wporg: 'sunilkumar',
  attendingContributorDay: 'yes',
  completedAt: 1,
};

const campCard = {
  name: 'Sunil Kumar',
  role: 'WordPress Engineer',
  wordpressOrg: 'https://profiles.wordpress.org/sunil-card/',
  city: 'Jaipur',
};

test('the onboarding form offers exactly the tags discovery offers, in the same order', () => {
  const welcome = read('resources/views/welcome.blade.php');
  const block = /id="ob-interests-label"[\s\S]*?@foreach \(\[([^\]]+)\] as \$tag\)/.exec(welcome);
  assert.ok(block, 'the onboarding chip list was found');
  assert.deepEqual([...block[1].matchAll(/'([^']+)'/g)].map((m) => m[1]), DESCRIBE_TAGS);

  const people = read('resources/js/attendee/people.js');
  assert.ok(people.includes('const TAGS = DESCRIBE_TAGS;'), 'discovery reads the same list');
  assert.equal(MAX_DESCRIBE_TAGS, 5, 'the API limit (StoreDiscoveryRequest)');
});

test('every onboarding field is read somewhere', () => {
  const welcome = read('resources/views/welcome.blade.php');
  const onboardingSection = welcome.slice(welcome.indexOf('id="onboarding-welcome"'), welcome.indexOf('id="find-your-camp"'));
  const fields = [...onboardingSection.matchAll(/data-field="([a-zA-Z]+)"|'dataField' => '([a-zA-Z]+)'/g)].map((m) => m[1] ?? m[2]);
  assert.deepEqual(fields.sort(), ['attendingContributorDay', 'firstWordCamp', 'profession', 'whoToMeet', 'wporg'].sort());

  const readers = ['home.js', 'profile-sync.js', 'contribute.js'].map((f) => read(`resources/js/attendee/${f}`)).join('\n');
  for (const field of [...fields, 'interests']) {
    assert.ok(new RegExp(`\\.${field}\\b|${field}\\)`).test(readers), `"${field}" is read by Home, profile-sync or Contribute`);
  }
});

test('old onboarding answers ("Content creator") still count, in the list order, never more than 5', () => {
  assert.deepEqual(describeTags(['Student', 'Content creator', 'Astronaut']), ['content creator', 'student']);
  assert.equal(describeTags(DESCRIBE_TAGS).length, 5);
  assert.deepEqual(describeTags(null), []);
});

test('a new discovery profile starts from the onboarding answers, then the Camp Card', () => {
  assert.deepEqual(discoveryPrefill({ onboarding, campCard }), {
    tags: ['developer', 'content creator', 'student'],
    who_to_meet: 'other plugin developers',
    display_name: 'Sunil Kumar',
    profession: 'Plugin developer',
    wporg_username: 'sunilkumar',
  });

  const bare = { interests: [], whoToMeet: null };
  const fromCard = discoveryPrefill({ onboarding: bare, campCard });
  assert.equal(fromCard.profession, 'WordPress Engineer', 'the card fills what onboarding left blank');
  assert.equal(fromCard.wporg_username, 'sunil-card');
});

test('nothing to go on: empty forms, never a crash', () => {
  assert.deepEqual(discoveryPrefill(), { tags: [], who_to_meet: null, display_name: null, profession: null, wporg_username: null });
  assert.deepEqual(campCardPrefill(null, null, null), {});
  assert.deepEqual(contributePrefill(null, ['development']), []);
});

test('a WordPress.org profile link and a username turn into each other', () => {
  assert.equal(wporgUsername('profiles.wordpress.org/jane'), 'jane');
  assert.equal(wporgUsername('https://profiles.wordpress.org/jane/'), 'jane');
  assert.equal(wporgUsername('jane'), 'jane');
  assert.equal(wporgUsername('not a username'), null);
  assert.equal(wporgProfileUrl('jane'), 'https://profiles.wordpress.org/jane/');
  assert.equal(wporgProfileUrl(''), null);
});

test('an empty Camp Card starts from the discovery profile, then the onboarding answers', () => {
  const discovery = { fields: { display_name: null, profession: null, wporg_username: 'jane' }, card: { name: 'Jane Doe' } };
  assert.deepEqual(campCardPrefill(null, discovery, onboarding), {
    name: 'Jane Doe',
    role: 'Plugin developer',
    wordpressOrg: 'https://profiles.wordpress.org/jane/',
  });
  assert.deepEqual(campCardPrefill(null, null, onboarding), { role: 'Plugin developer', wordpressOrg: 'https://profiles.wordpress.org/sunilkumar/' });
});

test('what the Camp Card already has is never replaced', () => {
  const discovery = { fields: { display_name: 'Someone Else', profession: 'Designer', wporg_username: 'other' } };
  assert.deepEqual(campCardPrefill(campCard, discovery, onboarding), {});
});

test('saving discovery updates the onboarding answers it shares, and keeps the rest', () => {
  const after = onboardingAfterDiscovery(onboarding, {
    tags: ['designer', 'content creator'], profession: 'Designer', who_to_meet: 'agency owners', wporg_username: 'jane',
  });
  assert.deepEqual(after.interests, ['designer', 'content creator']);
  assert.equal(after.profession, 'Designer');
  assert.equal(after.whoToMeet, 'agency owners');
  assert.equal(after.wporg, 'jane');
  assert.equal(after.firstWordCamp, 'yes');
  assert.equal(after.attendingContributorDay, 'yes');
  assert.equal(after.completedAt, 1);
});

test('saving the Camp Card updates profession and WordPress.org, but a blank card field changes nothing', () => {
  const after = onboardingAfterCampCard(onboarding, { role: 'WordPress Engineer', wordpressOrg: 'profiles.wordpress.org/new' });
  assert.equal(after.profession, 'WordPress Engineer');
  assert.equal(after.wporg, 'new');
  assert.deepEqual(after.interests, onboarding.interests);

  const blank = onboardingAfterCampCard(onboarding, { role: '', wordpressOrg: '' });
  assert.equal(blank.profession, 'Plugin developer');
  assert.equal(blank.wporg, 'sunilkumar');
});

test('"What describes you?" answers the Contribute questions in advance, only with keys it offers', () => {
  const offered = CONTRIB_QUESTION_TAGS.map((t) => t.key);
  assert.deepEqual(contributePrefill(onboarding, offered), ['development', 'writing', 'curious']);
  assert.deepEqual(contributePrefill({ interests: ['translator', 'mentor'] }, offered), ['support', 'translation']);
  assert.deepEqual(contributePrefill({ interests: ['developer'] }, ['design']), []);

  for (const tag of DESCRIBE_TAGS) {
    assert.ok(contributePrefill({ interests: [tag] }, offered).length > 0, `"${tag}" maps to a Contribute answer`);
  }
});

test('the Contributor Day answer changes only that answer', () => {
  assert.equal(onboardingWithContributorDay(onboarding, 'no').attendingContributorDay, 'no');
  assert.equal(onboardingWithContributorDay(onboarding, null).attendingContributorDay, null);
  assert.equal(onboardingWithContributorDay(onboarding, 'maybe').attendingContributorDay, null);
  assert.equal(onboardingWithContributorDay(onboarding, 'yes').profession, 'Plugin developer');
  assert.deepEqual(onboardingWithContributorDay(null, 'yes'), { attendingContributorDay: 'yes' });
});

test('every "What describes you?" tag helps Home suggest talks', () => {
  const home = read('resources/js/attendee/home.js');
  const block = /const INTEREST_WORDS = \{([\s\S]*?)\n\};/.exec(home)[1];
  for (const tag of DESCRIBE_TAGS) {
    assert.ok(block.includes(`'${tag}':`) || new RegExp(`\\n\\s+${tag}:`).test(block), `Home has words for "${tag}"`);
  }
});
