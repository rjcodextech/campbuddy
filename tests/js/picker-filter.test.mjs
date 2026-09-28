// node --test tests/js  — the WordCamp picker's country filter and "Load more".
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  ALL, FIRST, STEP, STORAGE_KEY,
  countByCountry, detectCountry, initPickerFilter, initialChoice, layout, statusText,
} from '../../resources/js/attendee/picker-filter.js';

// ---- pure logic --------------------------------------------------------------

test('the visitor country comes from the server zones first, then the old-name aliases', () => {
  assert.equal(detectCountry('Asia/Kolkata', { 'Asia/Kolkata': 'IN' }), 'IN');
  assert.equal(detectCountry('Asia/Calcutta', {}), 'IN', 'Chrome still reports the old name');
  assert.equal(detectCountry('Europe/Sofia', { 'Europe/Sofia': 'BG' }), 'BG');
  assert.equal(detectCountry('Mars/Base', {}), null);
  assert.equal(detectCountry(null, {}), null);
});

test('the start: saved choice, else own country with WordCamps, else all', () => {
  const counts = { IN: 2, GB: 1 };
  assert.equal(initialChoice({ saved: null, detected: 'IN', counts }), 'IN');
  assert.equal(initialChoice({ saved: 'GB', detected: 'IN', counts }), 'GB');
  assert.equal(initialChoice({ saved: ALL, detected: 'IN', counts }), ALL, 'a removed filter stays removed');
  assert.equal(initialChoice({ saved: 'FR', detected: 'IN', counts }), 'IN', 'a saved country with nothing listed is ignored');
  assert.equal(initialChoice({ saved: null, detected: 'FR', counts }), ALL, 'no WordCamp in my country: show all');
  assert.equal(initialChoice({ saved: null, detected: null, counts }), ALL);
});

test('layout shows the first N matching cards in page order, each once', () => {
  const countries = ['IN', 'GB', 'IN', null, 'IN', 'IN', 'IN', 'IN', 'IN'];
  const view = layout(countries, 'IN', FIRST);
  assert.equal(view.total, 7);
  assert.equal(view.shown, 5);
  assert.equal(view.next, 2);
  assert.deepEqual(view.visible, [true, false, true, false, true, true, true, false, false]);

  const all = layout(countries, ALL, 100);
  assert.equal(all.shown, countries.length);
  assert.equal(all.next, 0);
  assert.ok(all.visible.every(Boolean));
});

test('counts ignore cards with no country', () => {
  assert.deepEqual(countByCountry(['IN', null, 'IN', 'GB']), { IN: 2, GB: 1 });
});

test('status text is exact and singular for one', () => {
  assert.equal(statusText({ shown: 5, total: 12 }, null), 'Showing 5 of 12 WordCamps');
  assert.equal(statusText({ shown: 1, total: 1 }, 'India'), 'Showing 1 WordCamp in India');
  assert.equal(statusText({ shown: 3, total: 3 }, 'India'), 'Showing all 3 WordCamps in India');
});

// ---- wired to a page ---------------------------------------------------------

function el(extra = {}) {
  const handlers = {};
  return {
    hidden: false,
    textContent: '',
    value: '',
    dataset: {},
    children: [],
    addEventListener: (name, fn) => { handlers[name] = fn; },
    fire: (name) => handlers[name]?.(),
    replaceChildren(...kids) { this.children = kids; },
    focused: 0,
    focus() { this.focused++; },
    classList: { on: new Set(), toggle(name, force) { force ? this.on.add(name) : this.on.delete(name); } },
    ...extra,
  };
}

function page(countries, { names = {}, zones = {} } = {}) {
  const cards = countries.map((country, i) => el({ id: i, dataset: { country: country ?? '' } }));
  const parts = {
    list: el({ querySelectorAll: () => cards }),
    bar: el({ hidden: true }),
    select: el(),
    status: el(),
    toggle: el({ hidden: true }),
    more: el({ hidden: true }),
    data: el({ textContent: JSON.stringify({ names, zones }) }),
  };
  const selectors = {
    '[data-picker-list]': parts.list,
    '[data-picker-filter]': parts.bar,
    '[data-picker-country]': parts.select,
    '[data-picker-status]': parts.status,
    '[data-picker-toggle]': parts.toggle,
    '[data-picker-more]': parts.more,
  };
  const doc = {
    querySelector: (s) => selectors[s] ?? null,
    getElementById: (id) => (id === 'picker-countries' ? parts.data : null),
    createElement: () => ({ value: '', textContent: '' }),
  };
  const winHandlers = {};
  const win = { addEventListener: (name, fn) => { winHandlers[name] = fn; } };
  const store = new Map();
  const storage = { getItem: (k) => store.get(k) ?? null, setItem: (k, v) => store.set(k, String(v)) };

  return { cards, parts, doc, win, storage, store, winHandlers };
}

const shownIds = (cards) => cards.filter((c) => !c.hidden).map((c) => c.id);

test('starts filtered to my country, "Show all" removes it, the toggle puts it back', () => {
  const countries = ['IN', 'BG', 'IN', 'GB', 'IN', 'BG', 'GB', 'IN'];
  const p = page(countries, { names: { IN: 'India', BG: 'Bulgaria', GB: 'United Kingdom' }, zones: { 'Asia/Kolkata': 'IN' } });
  const f = initPickerFilter({ doc: p.doc, win: p.win, storage: p.storage, timeZone: 'Asia/Kolkata' });

  assert.equal(f.choice, 'IN');
  assert.equal(p.parts.bar.hidden, false);
  assert.deepEqual(shownIds(p.cards), [0, 2, 4, 7]);
  assert.equal(p.parts.status.textContent, 'Showing all 4 WordCamps in India');
  assert.equal(p.parts.toggle.textContent, 'Show all');
  assert.equal(p.parts.more.hidden, true);
  assert.equal(p.parts.select.value, 'IN');
  assert.ok(p.parts.bar.classList.on.has('is-filtered'));
  assert.deepEqual(p.parts.select.children.map((o) => o.textContent),
    ['All countries (8)', 'Bulgaria (2)', 'India (4)', 'United Kingdom (2)']);

  p.parts.toggle.fire('click');
  assert.equal(f.choice, ALL);
  assert.deepEqual(shownIds(p.cards), [0, 1, 2, 3, 4], 'first 5 of all, in page order');
  assert.equal(p.parts.status.textContent, 'Showing 5 of 8 WordCamps');
  assert.equal(p.parts.toggle.textContent, 'Only India');
  assert.equal(p.parts.more.textContent, 'Load 3 more');
  assert.equal(p.store.get(STORAGE_KEY), ALL, 'removal is remembered');
  assert.ok(!p.parts.bar.classList.on.has('is-filtered'));

  p.parts.toggle.fire('click');
  assert.equal(f.choice, 'IN');
  assert.equal(p.store.get(STORAGE_KEY), 'IN');
});

test('"Load more" opens STEP at a time, never repeats a card, and resets on a filter change', () => {
  const countries = Array.from({ length: 13 }, () => 'IN').concat(['GB']);
  const p = page(countries);
  initPickerFilter({ doc: p.doc, win: p.win, storage: p.storage, timeZone: 'Asia/Kolkata' });

  assert.equal(shownIds(p.cards).length, FIRST);
  assert.equal(p.parts.more.textContent, `Load ${STEP} more`);

  p.parts.more.fire('click');
  assert.deepEqual(shownIds(p.cards), [...Array(10).keys()]);
  assert.equal(p.cards[5].focused, 1, 'focus moves to the first new card');
  assert.equal(p.parts.more.textContent, 'Load 3 more');

  p.parts.more.fire('click');
  assert.equal(shownIds(p.cards).length, 13);
  assert.equal(p.parts.more.hidden, true);
  assert.equal(p.parts.status.textContent, 'Showing all 13 WordCamps in India');
  assert.equal(new Set(shownIds(p.cards)).size, 13);

  p.parts.select.value = ALL;
  p.parts.select.fire('change');
  assert.equal(shownIds(p.cards).length, FIRST, 'a new filter starts short again');
  assert.equal(p.parts.status.textContent, 'Showing 5 of 14 WordCamps');
});

test('no WordCamp in my country: everything, and no "Show only" button', () => {
  const p = page(['GB', 'BG', 'GB']);
  const f = initPickerFilter({ doc: p.doc, win: p.win, storage: p.storage, timeZone: 'Asia/Kolkata' });

  assert.equal(f.choice, ALL);
  assert.deepEqual(shownIds(p.cards), [0, 1, 2]);
  assert.equal(p.parts.toggle.hidden, true);
  assert.equal(p.parts.more.hidden, true);
});

test('one country only: no filter bar, "Load more" still works', () => {
  const p = page(Array.from({ length: 7 }, () => 'IN'));
  const f = initPickerFilter({ doc: p.doc, win: p.win, storage: p.storage, timeZone: 'Asia/Kolkata' });

  assert.equal(p.parts.bar.hidden, true);
  assert.equal(f.choice, ALL);
  assert.equal(shownIds(p.cards).length, FIRST);
  assert.equal(p.parts.toggle.hidden, true);
  assert.equal(p.parts.more.hidden, false);
});

test('another tab changing the filter changes this one too', () => {
  const p = page(['IN', 'GB', 'IN']);
  const f = initPickerFilter({ doc: p.doc, win: p.win, storage: p.storage, timeZone: 'Europe/London' });
  assert.equal(f.choice, 'GB');

  p.winHandlers.storage({ key: STORAGE_KEY, newValue: 'IN' });
  assert.equal(f.choice, 'IN');
  assert.deepEqual(shownIds(p.cards), [0, 2]);

  p.winHandlers.storage({ key: 'other', newValue: 'GB' });
  assert.equal(f.choice, 'IN');
});

test('broken storage or data never breaks the list', () => {
  const p = page(['IN', 'GB']);
  p.parts.data.textContent = '{not json';
  const storage = { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } };
  const f = initPickerFilter({ doc: p.doc, win: p.win, storage, timeZone: 'Asia/Calcutta' });

  assert.equal(f.choice, 'IN');
  p.parts.toggle.fire('click');
  assert.equal(f.choice, ALL);
  assert.deepEqual(shownIds(p.cards), [0, 1]);
});

test('no list on the page: nothing happens', () => {
  const doc = { querySelector: () => null };
  assert.equal(initPickerFilter({ doc, win: {}, storage: null, timeZone: 'Asia/Kolkata' }), null);
});
