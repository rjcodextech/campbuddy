// WordCamp 101, per event + Guide Navigation scrollspy across /guide and /event/{slug}/guide.
import { momentMatches } from './moments.js';
import { eventDayKey, formatInEventZone } from './eventtime.js';

export function renderGuide() {
  initGuideEvents();
  initGuideNav();
}

function initGuideEvents() {
  const dataEl = document.getElementById('guide-data');
  if (!dataEl) return;

  const sessions = JSON.parse(dataEl.textContent)
    .map((s) => ({ ...s, startMs: new Date(s.starts_at).getTime() }))
    .filter((s) => Number.isFinite(s.startMs))
    .sort((a, b) => a.startMs - b.startMs);

  if (sessions.length === 0) return;

  const multiDay = new Set(sessions.map((s) => eventDayKey(s.startMs))).size > 1;

  document.querySelectorAll('[data-guide-match]').forEach((step) => {
    const moment = { match: step.dataset.guideMatch.split('|').filter(Boolean), talk: step.dataset.guideTalk === '1' };
    if (moment.match.length === 0) return;

    const found = sessions.find((s) => momentMatches(moment, s));
    const whenEl = step.querySelector('[data-guide-when]');
    if (!found || !whenEl) return;

    const time = formatInEventZone(found.startMs, {
      ...(multiDay ? { weekday: 'short' } : {}),
      hour: 'numeric',
      minute: '2-digit',
    });

    whenEl.textContent = `At this WordCamp: ${time}${found.track ? ` · ${found.track}` : ''}`;
    whenEl.hidden = false;
  });
}

function initGuideNav() {
  // Attach matching anchor IDs for rich/custom legal documents rendered from CMS HTML
  const customContainer = document.querySelector('.legal-custom-content');
  if (customContainer) {
    customContainer.querySelectorAll('h2').forEach((h2, idx) => {
      if (!h2.id) h2.id = `legal-sec-${idx + 1}`;
      h2.style.scrollMarginTop = '112px';
    });
  }
  const grievanceBox = document.querySelector('.guide-doc-content .legal-footer-callout, .guide-doc-content [data-grievance-section]');
  if (grievanceBox && !grievanceBox.id) {
    grievanceBox.id = 'legal-grievance-cta';
  }

  const navChips = [...document.querySelectorAll('.guide-jump .chip[href^="#"]')];
  if (navChips.length === 0) return;

  const scroller = document.querySelector('.guide-doc-sidebar .guide-jump--sidebar');

  const setActive = (hash, scrollChip = true) => {
    let activeChip = null;
    navChips.forEach((chip) => {
      const matched = chip.getAttribute('href') === hash;
      chip.classList.toggle('is-active', matched);
      if (matched) activeChip = chip;
    });

    if (scrollChip && activeChip && scroller && scroller.scrollWidth > scroller.clientWidth + 4) {
      const targetLeft = activeChip.offsetLeft - (scroller.clientWidth - activeChip.offsetWidth) / 2;
      scroller.scrollTo({ left: Math.max(0, targetLeft), behavior: 'smooth' });
    }
  };

  // Highlight initial section (or URL hash) immediately
  const initialHash = window.location.hash && navChips.some((c) => c.getAttribute('href') === window.location.hash)
    ? window.location.hash
    : navChips[0].getAttribute('href');
  if (initialHash) setActive(initialHash, false);

  navChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      const hash = chip.getAttribute('href');
      if (hash) setActive(hash, true);
    });
  });

  if (typeof IntersectionObserver === 'function') {
    const observer = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((e) => e.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (visible?.target?.id) setActive(`#${visible.target.id}`, true);
    }, { rootMargin: '-18% 0px -65% 0px', threshold: 0.01 });

    navChips.forEach((chip) => {
      const el = document.getElementById(chip.getAttribute('href').slice(1));
      if (el) observer.observe(el);
    });
  }
}
