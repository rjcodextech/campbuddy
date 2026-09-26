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

// Each card counts how many times it is switched from hidden to shown.
const cards = (n) => Array.from({ length: n }, (_, i) => {
  let hidden = false;
  const card = { id: i, reveals: 0 };
  Object.defineProperty(card, 'hidden', {
    get: () => hidden,
    set: (value) => {
      if (hidden && !value) card.reveals++;
      hidden = value;
    },
  });

  return card;
});
const visible = (list) => list.filter((c) => c.id !== undefined && !c.hidden).length;
const split = (out) => ({ shown: out.filter((c) => c.id !== undefined), button: out.find((c) => c.id === undefined) });
const promised = (button) => {
  const match = /^Show (\d+) more$/.exec(button.textContent);
  assert.ok(match, `the button says only "Show N more", got "${button.textContent}"`);

  return Number(match[1]);
};

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

test('every tap opens exactly the number on the button — no more, no fewer — right to the last card', () => {
  for (const total of [5, 6, 12, 13, 14, 23, 24, 30, 47, 200]) {
    resetWindows();
    const out = windowCards('matches', cards(total), { doc });
    const { shown, button } = split(out);
    let expected = FIRST;

    assert.equal(visible(shown), FIRST);

    while (!button.removed) {
      const says = promised(button);
      const before = visible(shown);
      button.click();

      assert.equal(visible(shown) - before, says, `${total} cards: the button said ${says}, ${visible(shown) - before} opened`);
      expected += says;
      assert.equal(visible(shown), expected);
      assert.ok(says >= 1 && says <= STEP);
      assert.equal(out.length, total + 1, 'no card was added or dropped by a tap');
    }

    assert.equal(visible(shown), total, `${total} cards: everything ends up shown`);
  }
});

test('a card is opened once, never twice, and never closed again', () => {
  const { shown, button } = split(windowCards('matches', cards(47), { doc }));
  const seen = new Set(shown.filter((c) => !c.hidden).map((c) => c.id));

  while (!button.removed) {
    button.click();
    const now = shown.filter((c) => !c.hidden).map((c) => c.id);

    assert.ok([...seen].every((id) => now.includes(id)), 'nothing that was open closed again');
    seen.clear();
    now.forEach((id) => seen.add(id));
  }

  assert.ok(shown.every((c) => c.reveals <= 1), 'no card was switched on more than once');
  assert.equal(new Set(shown.map((c) => c.id)).size, 47, 'no card appears twice in the list');
  assert.equal(shown.filter((c) => c.reveals === 1).length, 44, 'exactly the 44 that started hidden were opened');
});

test('the last tap says how many it opens (not ten) and opens exactly that many', () => {
  const { shown, button } = split(windowCards('others', cards(8), { doc }));
  assert.equal(promised(button), 5);

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
  assert.equal(promised(button), 10);
  button.click();
  assert.equal(visible(shown), 16);
});

test('after a re-render (a wave was sent) an opened list stays open, and the button still tells the truth', () => {
  const first = split(windowCards('matches', cards(30), { doc }));
  first.button.click();
  first.button.click();
  assert.equal(visible(first.shown), 23);

  const again = split(windowCards('matches', cards(30), { doc }));
  assert.equal(visible(again.shown), 23, 'not shut again');
  assert.equal(promised(again.button), 7);

  again.button.click();
  assert.equal(visible(again.shown), 30);
  assert.equal(again.button.removed, true);
});

test('if the list changes size between renders (someone joined or left) the button still matches what a tap opens', () => {
  const first = split(windowCards('matches', cards(30), { doc }));
  first.button.click();

  for (const total of [40, 13, 26]) {
    const next = split(windowCards('matches', cards(total), { doc }));
    const says = next.button ? promised(next.button) : 0;
    const before = visible(next.shown);
    next.button?.click();

    assert.equal(visible(next.shown) - before, says, `${total} cards`);
    assert.ok(visible(next.shown) <= total);
  }
});

test('the two lists open independently', () => {
  const matches = split(windowCards('matches', cards(30), { doc }));
  matches.button.click();
  const others = split(windowCards('others', cards(30), { doc }));

  assert.equal(visible(matches.shown), 13);
  assert.equal(visible(others.shown), 3);
  assert.equal(STEP, 10);
});

test('the example: five cards say "Show 2 more" and a tap opens exactly two, not three', () => {
  const { shown, button } = split(windowCards('matches', cards(5), { doc }));

  assert.equal(button.textContent, 'Show 2 more');
  assert.equal(visible(shown), 3);

  button.click();
  assert.equal(visible(shown), 5);
  assert.equal(shown.filter((c) => c.reveals === 1).length, 2, 'exactly two cards were opened');
  assert.equal(button.removed, true);
});
