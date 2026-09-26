// A long list of people, kept short at first.
//
// On Explore → People every match is a tall card; with dozens of matches the
// attendee list below is pushed far down the page. This shows the first few
// cards and puts the rest behind a "Show N more" button.
//
// The cards are all built and wired by the caller exactly as before — this
// only sets `hidden` on the ones beyond the first few (the site's global
// `[hidden]` rule makes that mean "not on screen"). So nothing a card does
// (Wave, + Meet, I met them) changes, nothing is stored, and no request is made.

/** Cards shown before anyone taps "Show more". */
export const FIRST = 3;

/** Cards revealed by each tap. */
export const STEP = 10;

// How many extra cards each list has been opened to, kept for this page visit
// so a re-render after a wave doesn't shut the list again.
const opened = new Map();

/**
 * @param {string} key    which list this is ('matches', 'others') — for the remembered state
 * @param {Element[]} cards  every card, already built and wired
 * @param {object} [options]
 * @param {number} [options.min]  cards that always show (e.g. people waiting for a wave back)
 * @param {Document} [options.doc]
 * @returns {Element[]} the cards, plus a "Show more" button when some are hidden
 */
export function windowCards(key, cards, { min = FIRST, doc = globalThis.document } = {}) {
  const first = Math.max(FIRST, min);

  // One card over the limit would only trade one card for a button.
  if (cards.length <= first + 1) return cards;

  let shown = Math.min(cards.length, first + (opened.get(key) ?? 0));
  cards.forEach((card, i) => {
    card.hidden = i >= shown;
  });

  const button = doc.createElement('button');
  button.type = 'button';
  button.className = 'btn btn--outline btn--full';

  // The one number on the button is exactly how many cards a tap opens.
  const nextCount = () => Math.min(STEP, cards.length - shown);
  const label = () => {
    button.textContent = `Show ${nextCount()} more`;
  };
  label();

  button.addEventListener('click', () => {
    // Reveals the next `nextCount()` cards that are still hidden: each card is
    // opened once, none is added or repeated, so the label and the result agree.
    const upTo = shown + nextCount();
    for (let i = shown; i < upTo; i++) cards[i].hidden = false;
    shown = upTo;
    opened.set(key, shown - first);

    if (shown >= cards.length) button.remove();
    else label();
  });

  return [...cards, button];
}

/** Forgets what was opened (a new page visit starts short; used by tests). */
export function resetWindows() {
  opened.clear();
}
