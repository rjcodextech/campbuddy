import assert from 'node:assert/strict';
import { test } from 'node:test';

const sent = [];
globalThis.window = {
  gtag: (_kind, name, params) => sent.push([name, params]),
  addEventListener: () => {},
};
globalThis.document = {
  addEventListener: () => {},
  querySelectorAll: () => [],
  readyState: 'complete',
};

const { track, isTrackingDisabled } = await import('../../resources/js/attendee/analytics.js');

test('excluded domains prevent tracking hits from firing', () => {
  const excludedUrls = [
    'https://campbuddy.club.rjcodex.com/event/x',
    'http://campbuddy.test/event/x',
    'http://127.0.0.1:8000/event/x',
    'http://localhost:8000/event/x',
    'https://subdomain.campbuddy.test/event/x',
  ];

  for (const url of excludedUrls) {
    globalThis.location = new URL(url);
    sent.length = 0;
    assert.equal(isTrackingDisabled(), true, `Expected ${url} to have tracking disabled`);
    track('nav_tab_click', { tab: 'home' });
    assert.deepEqual(sent, [], `Expected no events to be tracked on ${url}`);
  }
});

test('production domain allows tracking hits', () => {
  globalThis.location = new URL('https://campbuddy.club/event/x');
  sent.length = 0;
  assert.equal(isTrackingDisabled(), false);
  track('nav_tab_click', { tab: 'home' });
  assert.deepEqual(sent, [['nav_tab_click', { tab: 'home' }]]);
});
