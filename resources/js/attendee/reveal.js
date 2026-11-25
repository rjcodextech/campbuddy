// Scroll reveal: sections below the fold fade in cleanly as they enter view,
// and immediately reveal on anchor jumps (#section), fast scrolling, or fallback timeout
// so handbook/home content is never left invisible.

const SELECTOR = '.home-section, .guide-section';

export function initReveal(root = document) {
  const targets = [...root.querySelectorAll(SELECTOR)];
  if (!targets.length) return;

  const showAllThrough = (limitY) => {
    targets.forEach((el) => {
      if (!el.classList.contains('reveal') || el.classList.contains('reveal--visible')) return;
      const rect = el.getBoundingClientRect();
      if (rect.top <= limitY || rect.bottom <= 0) {
        el.classList.add('reveal--visible');
      }
    });
  };

  const revealTargetHash = () => {
    const id = window.location.hash.replace(/^#/, '');
    if (id) {
      const target = document.getElementById(id);
      if (target) target.classList.add('reveal--visible');
    }
    showAllThrough(window.innerHeight * 1.25);
  };

  if (typeof IntersectionObserver !== 'function' || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    targets.forEach((el) => el.classList.add('reveal--visible'));
    return;
  }

  const fold = window.innerHeight;
  const below = targets.filter((el) => el.getBoundingClientRect().top > fold * 1.1);
  if (!below.length) return;

  const seen = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting && entry.boundingClientRect.top > window.innerHeight) continue;
      entry.target.classList.add('reveal--visible');
      seen.unobserve(entry.target);
    }
  }, { rootMargin: '80px 0px 80px 0px', threshold: 0.01 });

  for (const el of below) {
    el.classList.add('reveal');
    seen.observe(el);
  }

  // Anchor clicks, hashchange, and fast jumps immediately reveal visible/passed targets.
  window.addEventListener('hashchange', revealTargetHash);
  window.addEventListener('scroll', () => showAllThrough(window.innerHeight * 1.2), { passive: true });
  root.addEventListener('click', (e) => {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;
    const id = link.getAttribute('href').slice(1);
    const el = id ? document.getElementById(id) : null;
    if (el) el.classList.add('reveal--visible');
    setTimeout(() => showAllThrough(window.innerHeight * 1.4), 60);
  });

  // Safety net: after 900ms, never leave elements near/above current scroll hidden.
  setTimeout(revealTargetHash, 150);
  setTimeout(() => targets.forEach((el) => el.classList.add('reveal--visible')), 1800);
}
