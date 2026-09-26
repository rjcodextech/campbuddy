// node --test tests/js  — one action, one name: the same words on Explore, My schedule and the Meet sheet.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { test } from 'node:test';
import { LABELS } from '../../resources/js/attendee/people-state.js';

const read = (path) => fs.readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const discovery = read('resources/views/attendee/templates/discovery.blade.php');
const myDay = read('resources/views/attendee/templates/my-day.blade.php');

test('the button that marks someone met says the same on a match card and on My schedule, and so does the label', () => {
  assert.ok(discovery.includes(`data-slot="met-btn">${LABELS.met}<`), 'Explore match card button');
  assert.ok(discovery.includes(`data-slot="met-label">${LABELS.met}<`), 'Explore met label');
  assert.ok(myDay.includes(`data-slot="met">${LABELS.met}<`), 'My schedule button');
});

test('"couldn\'t meet" reads the same everywhere: My schedule\'s button, its label and Explore\'s section', () => {
  assert.ok(myDay.includes(`data-slot="missed">${LABELS.missedButton}<`), 'My schedule button');
  assert.equal(LABELS.missedButton, `✗ ${LABELS.missed}`, 'the button is the label with a ✗');
});

test('the words are the ones we decided on', () => {
  assert.deepEqual(LABELS, {
    toMeet: '+ Meet',
    planned: '✓ To meet',
    met: '✓ Met',
    missed: "Couldn't meet",
    missedButton: "✗ Couldn't meet",
    undo: 'Undo',
    hide: 'Hide',
    hidden: 'Hidden',
    showAgain: 'Show again',
    hiddenToast: "Hidden. You'll find them under Hidden.",
  });
});

test('the screens take their words from the list, and the older names are gone', () => {
  for (const file of ['people.js', 'my-day.js', 'meet-sheet.js']) {
    const source = read(`resources/js/attendee/${file}`);

    for (const old of ["'Try again'", "'Hide from plan'", "'Show again'", "'Undo'", "'Hidden people'"]) {
      assert.equal(source.includes(old), false, `${file} still spells out ${old}`);
    }
    assert.ok(source.includes('LABELS'), `${file} uses LABELS`);
  }
});
