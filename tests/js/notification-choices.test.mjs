// node --test tests/js  — notifications that were turned down (notification-choices.js): when the
// "Notifications are off" notice shows, what it says is off, and the "Your choices" answers.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { blockedSteps, choiceLabel, notificationStatus, offItems, showOffNotice } = await import('../../resources/js/attendee/notification-choices.js');

const day = { today: '2026-11-20', lastDay: '2026-11-22' };

test('the notice shows only after a no, never to someone who was not asked or said yes', () => {
  const never = notificationStatus({ permission: 'default', state: {} });
  assert.equal(never.turnedDown, false);
  assert.equal(showOffNotice(never, day), false, 'never asked: nothing to tell');
  const yes = notificationStatus({ permission: 'granted', state: { enabled: true } });
  assert.ok(yes.allOn);
  assert.equal(showOffNotice(yes, day), false);

  for (const [why, status] of [
    ['"No thanks" to reminders', notificationStatus({ permission: 'default', state: { declined: true } })],
    ['the browser blocked it', notificationStatus({ permission: 'denied', state: {} })],
    ['"Not now" on live updates', notificationStatus({ permission: 'default', state: {}, liveAnswer: 'no' })],
    ['allowed, then taken back in the browser', notificationStatus({ permission: 'default', state: { enabled: true } })],
  ]) {
    assert.ok(status.turnedDown, why);
    assert.equal(showOffNotice(status, day), true, why);
  }
});

test('"Not now" hides it for a day; right after a no it shows anyway; never after the event or where it cannot work', () => {
  const off = notificationStatus({ permission: 'default', state: { declined: true } });
  const now = Date.parse('2026-11-20T10:00:00Z');
  assert.equal(showOffNotice(off, { ...day, hiddenAt: now - 60_000, now }), false, 'hidden a minute ago');
  assert.equal(showOffNotice(off, { ...day, hiddenAt: now - 25 * 3600_000, now }), true, 'a day later');
  assert.equal(showOffNotice(off, { ...day, hiddenAt: now - 60_000, now, justNow: true }), true, 'just said no');
  assert.equal(showOffNotice(off, { today: '2026-11-23', lastDay: '2026-11-22' }), false, 'the event is over');
  assert.equal(showOffNotice(notificationStatus({ permission: 'unsupported', state: { declined: true } }), day), false);
  assert.equal(showOffNotice(notificationStatus({ permission: 'install', state: { declined: true } }), day), false, 'iPhone not on the Home Screen: the card explains instead');
});

test('the notice lists only what is really off', () => {
  const remindersOnly = notificationStatus({ permission: 'granted', state: { declined: true } });
  assert.equal(remindersOnly.updates, true, 'the browser allows them, so the organizers’ updates arrive');
  assert.deepEqual(offItems(remindersOnly), ['A reminder before the talks you save']);
  assert.equal(showOffNotice(remindersOnly, day), true);
  assert.deepEqual(offItems(notificationStatus({ permission: 'denied', state: {} })).length, 2);
});

test('"Your choices" says where each stands; a blocked browser gets the steps to allow', () => {
  assert.equal(choiceLabel(true, 'granted'), 'On');
  assert.equal(choiceLabel(false, 'default'), 'Off');
  assert.equal(choiceLabel(false, 'denied'), 'Blocked in the browser');
  assert.equal(choiceLabel(false, 'install'), 'Add CampBuddy to the Home Screen first');
  assert.equal(choiceLabel(false, 'unsupported'), 'Not available in this browser');
  assert.match(blockedSteps(true)[0], /Long-press the CampBuddy icon/);
  assert.match(blockedSteps(false)[0], /next to the web address/);
  assert.ok(blockedSteps(false).every((s) => !/…/.test(s)));
});

test('analytics has no part in it: saying no to it is not a notification choice', () => {
  const status = notificationStatus({ permission: 'granted', state: { enabled: true } });
  assert.ok(status.allOn);
  assert.equal(Object.keys(status).some((k) => /analytic/i.test(k)), false);
});
