// "See it in action" on the WordCamp picker: the app's real screens in a
// phone frame, advancing on their own like a short silent video. Pauses
// while it's touched, hovered or focused, and when it's off screen; plays
// nothing for anyone whose device asks for reduced motion (they get the
// arrows and dots instead). Markup: attendee/partials/about-campbuddy.blade.php.

import { track } from './analytics.js';

const SLIDE_MS = 4500;

export function initTour(root) {
  const slides = [...root.querySelectorAll('[data-tour-slide]')];
  if (slides.length === 0) return;

  const caption = root.querySelector('[data-tour-caption]');
  const dotsEl = root.querySelector('[data-tour-dots]');
  const toggle = root.querySelector('[data-tour-toggle]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let index = 0;
  let playing = !reduceMotion;
  let held = false; // touched / hovered / focused
  let visible = false;
  let timer = null;
  let viewed = false;

  const dots = slides.map((_, i) => {
    const dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'tour__dot';
    dot.setAttribute('role', 'tab');
    dot.setAttribute('aria-label', `Screen ${i + 1} of ${slides.length}`);
    dot.addEventListener('click', () => { show(i); stop(); });
    dotsEl.appendChild(dot);
    return dot;
  });

  function show(i) {
    index = (i + slides.length) % slides.length;
    slides.forEach((slide, n) => {
      slide.hidden = n !== index;
      slide.classList.toggle('tour__slide--in', n === index);
    });
    dots.forEach((dot, n) => dot.setAttribute('aria-selected', String(n === index)));
    caption.textContent = slides[index].querySelector('img').alt;
  }

  function schedule() {
    clearTimeout(timer);
    if (playing && !held && visible) {
      timer = setTimeout(() => { show(index + 1); schedule(); }, SLIDE_MS);
    }
  }

  function syncToggle() {
    toggle.textContent = playing ? '❚❚' : '▶';
    toggle.setAttribute('aria-label', playing ? 'Pause' : 'Play');
  }

  function stop() {
    playing = false;
    syncToggle();
    schedule();
  }

  root.querySelector('[data-tour-prev]').addEventListener('click', () => { show(index - 1); stop(); });
  root.querySelector('[data-tour-next]').addEventListener('click', () => { show(index + 1); stop(); });
  toggle.addEventListener('click', () => {
    playing = !playing;
    syncToggle();
    schedule();
  });

  // Hovering with a mouse holds the current screen. (Touch has no "leave",
  // so a tap mustn't count as hovering — it would pause the tour for good.)
  const hold = (on) => (e) => {
    if (e.pointerType && e.pointerType !== 'mouse') return;
    held = on;
    schedule();
  };
  root.addEventListener('pointerenter', hold(true));
  root.addEventListener('pointerleave', hold(false));
  root.addEventListener('focusin', hold(true));
  root.addEventListener('focusout', hold(false));

  // Swipe the phone left/right to change screens, or tap its left/right
  // half — like any photo gallery. Vertical moves are left to the page, so
  // scrolling past the tour still works (touch-action: pan-y in the CSS).
  const phone = root.querySelector('.tour__phone');
  let startX = null;
  let startY = null;

  phone.addEventListener('pointerdown', (e) => {
    startX = e.clientX;
    startY = e.clientY;
  });

  phone.addEventListener('pointerup', (e) => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    startX = null;

    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
      show(index + (dx < 0 ? 1 : -1));
    } else if (Math.abs(dx) < 10 && Math.abs(dy) < 10) {
      const box = phone.getBoundingClientRect();
      show(index + (e.clientX > box.left + box.width / 2 ? 1 : -1));
    } else {
      return;
    }

    // They're driving it now: restart the timer from this screen.
    schedule();
  });

  phone.addEventListener('pointercancel', () => { startX = null; });

  // Only runs (and only counts as watched) while it's on screen.
  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible && !viewed) {
      viewed = true;
      track('tour_view');
    }
    schedule();
  }, { threshold: 0.4 }).observe(root);

  show(0);
  syncToggle();
}
