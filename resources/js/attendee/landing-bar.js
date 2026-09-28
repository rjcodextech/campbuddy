// The picker page's sticky bottom bar ("Choose your WordCamp ↓" / "Open …").
//
// The hero at the top already has a "Choose your WordCamp" button, so the bar
// stays tucked away while that button is on screen and slides up once it has
// scrolled out of view (and away again on the way back up).
//
// The bar is rendered tucked (`is-tucked` in welcome.blade.php) so it doesn't
// flash in and out on load; without the script a <noscript> style and, if this
// module fails, app.js show it as before.

export const TUCKED = 'is-tucked';

/**
 * @returns {{ disconnect(): void } | null}  null when there's nothing to watch
 *   (then the bar is simply shown)
 */
export function initLandingBar({ doc = globalThis.document, win = globalThis.window } = {}) {
  const bar = doc.querySelector('.landing-bottom-bar');
  if (!bar) return null;

  const hero = doc.querySelector('.landing-hero__actions');
  const set = (tucked) => {
    bar.classList.toggle(TUCKED, tucked);
    // Not reachable by keyboard or screen reader while it's off screen.
    bar.inert = tucked;
  };

  if (!hero || typeof win?.IntersectionObserver !== 'function') {
    set(false);

    return null;
  }

  const observer = new win.IntersectionObserver((entries) => {
    const entry = entries[entries.length - 1];
    set(entry.isIntersecting);
  });
  observer.observe(hero);

  return observer;
}
