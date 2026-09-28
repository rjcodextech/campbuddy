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
import { MAX_DESCRIBE_TAGS, describeTags, wporgUsername } from './profile-sync.js';
import { showToast } from './toast.js';

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
        // The same limit attendee discovery has, so these tags always fit there.
        if (answers.interests.length >= MAX_DESCRIBE_TAGS) {
          showToast(`Pick up to ${MAX_DESCRIBE_TAGS}.`);
          return;
        }
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

    // Read the fields as they are now: a text field someone is still typing
    // in hasn't fired "change" yet.
    section.querySelectorAll('[data-field]').forEach((field) => {
      answers[field.dataset.field] = field.value.trim() || null;
    });

    // Only what something reads: Home (first WordCamp, tags), the discovery
    // form and Camp Card (tags, profession, who to meet, WordPress.org) and
    // Contribute (Contributor Day). See profile-sync.js.
    const profile = {
      firstWordCamp: answers.firstWordCamp ?? null,
      interests: describeTags(answers.interests),
      profession: answers.profession ?? null,
      whoToMeet: answers.whoToMeet ?? null,
      wporg: wporgUsername(answers.wporg),
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
