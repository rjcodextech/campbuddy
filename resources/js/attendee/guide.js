// WordCamp 101, per event: next to each step of "How the day usually goes"
// (registration, keynote, lunch…), the real time it happens at THIS event,
// found by matching the step's keywords against the schedule's titles.
// Times are formatted here, on the device, so they're in the attendee's own
// timezone and locale — the server doesn't know either.

import { momentMatches } from './moments.js';
import { eventDayKey, formatInEventZone } from './eventtime.js';

export function renderGuide() {
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

  const navChips = [...document.querySelectorAll('.guide-jump .chip[href^="#"]')];
  if (navChips.length > 0) {
    const setActive = (hash) => {
      navChips.forEach((chip) => chip.classList.toggle('is-active', chip.getAttribute('href') === hash));
    };
    navChips.forEach((chip) => {
      chip.addEventListener('click', () => {
        const hash = chip.getAttribute('href');
        if (hash) setActive(hash);
      });
    });
    if (typeof IntersectionObserver === 'function') {
      const observer = new IntersectionObserver((entries) => {
        const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (visible?.target?.id) setActive(`#${visible.target.id}`);
      }, { rootMargin: '-20% 0px -65% 0px', threshold: 0.01 });
      navChips.forEach((chip) => {
        const el = document.getElementById(chip.getAttribute('href').slice(1));
        if (el) observer.observe(el);
      });
    }
  }
}
