// Scroll reveal (CAMPBUDDY-BRAND-GUIDELINES.md §5): page sections that start
// below the fold fade up as they scroll in (.reveal → .reveal--visible in
// components/_effects.scss). Only blocks below the fold when the page opens
// are marked, so the first paint is never hidden; reduced motion, a missing
// IntersectionObserver or a section shown later without scrolling all leave
// the page exactly as it was.

const SELECTOR = '.home-section, .guide-section';

export function initReveal(root = document) {
  if (typeof IntersectionObserver !== 'function') return;
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

  const fold = window.innerHeight;
  const below = [...root.querySelectorAll(SELECTOR)].filter((el) => el.getBoundingClientRect().top > fold);
  if (!below.length) return;

  const seen = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      // In view, or already passed (a jump to an anchor or the page's end): show it.
      if (!entry.isIntersecting && entry.boundingClientRect.bottom > 0) continue;
      entry.target.classList.add('reveal--visible');
      seen.unobserve(entry.target);
    }
  }, { rootMargin: '0px 0px -8% 0px' });

  for (const el of below) {
    el.classList.add('reveal');
    seen.observe(el);
  }
}
