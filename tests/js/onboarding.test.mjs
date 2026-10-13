// node --test tests/js  — "Make CampBuddy yours" as a stepper (onboarding.js).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.window = {};
globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };
globalThis.localStorage = { getItem: () => null, setItem() {} };

const { summaryFor, stepAfter } = await import('../../resources/js/attendee/onboarding.js');
const welcome = readFileSync(new URL('../../resources/views/welcome.blade.php', import.meta.url), 'utf8');
const section = welcome.slice(welcome.indexOf('id="onboarding-welcome"'), welcome.indexOf("about-campbuddy', ['part' => 'steps']"));

test('the closing screen says what each answer will do, and is never empty', () => {
  const all = summaryFor({ interests: ['developer', 'speaker'], firstWordCamp: 'yes', attendingContributorDay: 'yes', whoToMeet: 'x' });
  assert.equal(all.length, 4);
  assert.match(all[0], /developer, speaker/);
  assert.match(all[1], /Beginner-friendly/);

  assert.match(summaryFor({ interests: [], firstWordCamp: 'no' })[0], /stays out of your way/);
  assert.match(summaryFor({ interests: [] })[0], /add these any time/, 'skipping everything still gets an answer');

  const many = summaryFor({ interests: ['a', 'b', 'c', 'd', 'e'] })[0];
  assert.match(many, /for a, b, c come first/, 'only the first three tags are named');
});

test('Next and Back stay inside the steps', () => {
  assert.equal(stepAfter(0, -1, 5), 0);
  assert.equal(stepAfter(0, 1, 5), 1);
  assert.equal(stepAfter(4, 1, 5), 4);
  assert.equal(stepAfter(2, -1, 5), 1);
});

test('the markup has five steps and five progress dots, each step with a question to focus', () => {
  const stepStarts = [...section.matchAll(/<div class="ob__step" data-step hidden>/g)].length;
  assert.equal(stepStarts, 5);
  assert.equal([...section.matchAll(/class="ob__q"/g)].length, stepStarts + 1, 'one per step, and one on the closing screen');
  assert.match(section, /range\(1, 5\)/, 'five dots for five steps');
});

test('every step can be skipped, and the whole thing can be left at the start', () => {
  assert.match(section, /data-action="skip"/);
  assert.match(section, /data-action="next"/);
  assert.match(section, /data-action="back"/);
  assert.equal([...section.matchAll(/data-action="skip-all"/g)].length, 2, 'Not now at the start, Skip all during the steps');
});

test('the answers keep the same keys, so Home, Contribute, discovery and the Camp Card read them unchanged', () => {
  const js = readFileSync(new URL('../../resources/js/attendee/onboarding.js', import.meta.url), 'utf8');
  for (const key of ['firstWordCamp', 'interests', 'profession', 'whoToMeet', 'wporg', 'attendingContributorDay', 'completedAt']) {
    assert.ok(js.includes(key), key);
  }
});

test('on the picker, the events come straight after the hero and personalising follows them', () => {
  const order = ['landing-hero"', 'id="find-your-camp"', 'id="onboarding-welcome"', "part' => 'steps'", "part' => 'more'"].map((marker) => welcome.indexOf(marker));
  assert.ok(order.every((at) => at > 0), 'every block is on the page');
  assert.deepEqual([...order].sort((a, b) => a - b), order, 'hero, events, personalise, how it helps, the rest');
});
