// node --test tests/js  — a long list of people starts short, "Show more" opens it.
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { FIRST, STEP, resetWindows, windowCards } from '../../resources/js/attendee/list-window.js';

// The smallest stand-ins the module touches: cards with `hidden`, and a button.
const doc = {
  createElement: () => {
    const button = { type: '', className: '', textContent: '', removed: false, handlers: {} };
    button.addEventListener = (name, fn) => { button.handlers[name] = fn; };
    button.remove = () => { button.removed = true; };
    button.click = () => button.handlers.click();

    return button;
  },
};
const cards = (n) => Array.from({ length: n }, (_, i) => ({ id: i, hidden: false }));
const visible = (list) => list.filter((c) => c.id !== undefined && !c.hidden).length;
const split = (out) => ({ shown: out.filter((c) => c.id !== undefined), button: out.find((c) => c.id === undefined) });

beforeEach(() => resetWindows());

test('a short list is left exactly as it is: nothing hidden, no button', () => {
  for (const n of [0, 1, 2, 3, 4]) {
    const list = cards(n);
    const out = windowCards('matches', list, { doc });

    assert.equal(out, list, 'the same array back');
    assert.equal(visible(out), n);
  }
});

test('a longer list shows the first three cards and a button for the rest', () => {
  const { shown, button } = split(windowCards('matches', cards(12), { doc }));

  assert.equal(shown.length, 12, 'every card is still there — only hidden');
  assert.equal(visible(shown), FIRST);
  assert.deepEqual(shown.slice(0, 3).map((c) => c.hidden), [false, false, false]);
  assert.ok(shown.slice(3).every((c) => c.hidden));
  assert.equal(button.textContent, 'Show 9 more');
  assert.equal(button.type, 'button');
  assert.match(button.className, /btn--outline/);
});

test('each tap opens ten more, and the button goes when nothing is left', () => {
  const { shown, button } = split(windowCards('matches', cards(30), { doc }));
  assert.equal(button.textContent, 'Show 10 more (27 left)');

  button.click();
  assert.equal(visible(shown), 13);
  assert.equal(button.textContent, 'Show 10 more (17 left)');

  button.click();
  button.click();
  assert.equal(visible(shown), 30);
  assert.equal(button.removed, true);
});

test('the last tap says how many it opens, not ten', () => {
  const { shown, button } = split(windowCards('others', cards(8), { doc }));
  assert.equal(button.textContent, 'Show 5 more');

  button.click();
  assert.equal(visible(shown), 8);
  assert.equal(button.removed, true);
});

test('one card over the limit is not worth a button', () => {
  const out = windowCards('matches', cards(FIRST + 1), { doc });

  assert.equal(visible(out), FIRST + 1);
  assert.equal(out.length, FIRST + 1);
});

test('people waiting for a wave back always show, even past the first three', () => {
  const { shown, button } = split(windowCards('matches', cards(20), { min: 6, doc }));

  assert.equal(visible(shown), 6);
  assert.equal(button.textContent, 'Show 10 more (14 left)');
});

test('after a re-render (a wave was sent) an opened list stays open', () => {
  const first = split(windowCards('matches', cards(30), { doc }));
  first.button.click();
  first.button.click();
  assert.equal(visible(first.shown), 23);

  const again = split(windowCards('matches', cards(30), { doc }));
  assert.equal(visible(again.shown), 23, 'not shut again');
  assert.equal(again.button.textContent, 'Show 7 more');
});

test('the two lists open independently', () => {
  const matches = split(windowCards('matches', cards(30), { doc }));
  matches.button.click();
  const others = split(windowCards('others', cards(30), { doc }));

  assert.equal(visible(matches.shown), 13);
  assert.equal(visible(others.shown), 3);
  assert.equal(STEP, 10);
});
