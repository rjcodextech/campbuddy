// Inline "Make CampBuddy yours" onboarding section — lives on the
// WordCamp picker page ("/"), right after the intro, before any event is
// even selected (OB1/OB3 in the product spec), replacing the old
// blocking <dialog> popup. Never blocks the rest of the page from
// rendering. Runs once per device; the profile it collects stays
// local-only until the attendee separately opts into discovery.
//
// The form's markup is #onboarding-welcome in welcome.blade.php (hidden
// until this finds the device hasn't completed it) — this only wires it.

import { track } from './analytics.js';
import { kvGet, kvSet } from './db.js';

export async function renderOnboardingSection() {
  const section = document.getElementById('onboarding-welcome');
  if (!section) return;

  const existing = await kvGet('onboarding');
  if (existing && existing.completedAt) {
    section.hidden = true;
    return;
  }

  const answers = { interests: [] };

  section.hidden = false;

  section.querySelectorAll('[data-tag]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tag = btn.dataset.tag;
      const idx = answers.interests.indexOf(tag);
      if (idx === -1) {
        answers.interests.push(tag);
      } else {
        answers.interests.splice(idx, 1);
      }

      const on = idx === -1;
      btn.classList.toggle('chip--selected', on);
      btn.setAttribute('aria-pressed', String(on));
    });
  });

  section.querySelectorAll('[data-field]').forEach((field) => {
    field.addEventListener('change', () => {
      answers[field.dataset.field] = field.value || null;
    });
  });

  // Skip vs. Continue is the only thing reported — never the answers, which
  // stay on the device (spec §8.5).
  const finish = async (outcome) => {
    track(outcome === 'skip' ? 'onboarding_skip' : 'onboarding_complete');

    const profile = {
      firstWordCamp: answers.firstWordCamp ?? null,
      role: answers.role ?? null,
      interests: answers.interests ?? [],
      whyAttending: answers.whyAttending ?? null,
      wantToLearn: answers.wantToLearn ?? null,
      whoToMeet: answers.whoToMeet ?? null,
      attendingContributorDay: answers.attendingContributorDay ?? null,
      completedAt: Date.now(),
    };

    await kvSet('onboarding', profile);
    section.hidden = true;

    // The next step is choosing an event, which sits right below.
    document.getElementById('find-your-camp')?.scrollIntoView({
      behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
      block: 'start',
    });
  };

  section.querySelector('[data-action="skip"]').addEventListener('click', () => finish('skip'));
  section.querySelector('[data-action="save"]').addEventListener('click', () => finish('save'));
}
