// Inline "Tell us a little about you" onboarding section — lives on the
// WordCamp picker page ("/"), right after the intro, before any event is
// even selected (OB1/OB3 in the product spec), replacing the old
// blocking <dialog> popup. Never blocks the rest of the page from
// rendering. Runs once per device; the profile it collects stays
// local-only until the attendee separately opts into discovery.

import { kvGet, kvSet } from './db.js';

const INTEREST_TAGS = [
  'Developer',
  'Designer',
  'Content creator',
  'Site builder',
  'Community organizer',
  'Marketer',
  'Business owner',
];

export async function renderOnboardingSection() {
  const section = document.getElementById('onboarding-welcome');
  if (!section) return;

  const existing = await kvGet('onboarding');
  if (existing && existing.completedAt) {
    section.hidden = true;
    return;
  }

  const answers = { interests: [] };

  const tagButtons = INTEREST_TAGS.map(
    (tag) => `<button type="button" class="pill" data-tag="${escapeHtml(tag)}">${escapeHtml(tag)}</button>`
  ).join('');

  section.innerHTML = `
    <div class="onboarding-card">
      <p class="section-head__title" id="onboarding-welcome-heading" style="margin-bottom:2px">Tell us a little about you</p>
      <p class="footer-note" style="text-align:left;margin-bottom:16px">Every question here is skippable.</p>

      <label class="field">
        <span>Is this your first WordCamp?</span>
        <select data-field="firstWordCamp">
          <option value="">Prefer not to say</option>
          <option value="yes">Yes, first one!</option>
          <option value="no">No, I've been before</option>
        </select>
      </label>

      <div class="field">
        <span>What are you into?</span>
        <div class="onboarding-card__tags">${tagButtons}</div>
      </div>

      <label class="field">
        <span>Who would you like to meet?</span>
        <input type="text" data-field="whoToMeet" placeholder="e.g. other plugin developers">
      </label>

      <label class="field">
        <span>Interested in Contributor Day?</span>
        <select data-field="attendingContributorDay">
          <option value="">Not sure yet</option>
          <option value="yes">Yes</option>
          <option value="no">Not this time</option>
        </select>
      </label>

      <div class="onboarding-card__actions">
        <button type="button" class="btn btn--ghost" data-action="skip">Skip for now</button>
        <button type="button" class="btn btn--primary" data-action="save">Save &amp; continue</button>
      </div>
    </div>
  `;

  section.hidden = false;

  section.querySelectorAll('[data-tag]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tag = btn.dataset.tag;
      const idx = answers.interests.indexOf(tag);
      if (idx === -1) {
        answers.interests.push(tag);
        btn.classList.add('pill--hashtag');
      } else {
        answers.interests.splice(idx, 1);
        btn.classList.remove('pill--hashtag');
      }
    });
  });

  section.querySelectorAll('[data-field]').forEach((field) => {
    field.addEventListener('change', () => {
      answers[field.dataset.field] = field.value || null;
    });
  });

  const finish = async () => {
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
    section.innerHTML = '';
  };

  section.querySelector('[data-action="skip"]').addEventListener('click', finish);
  section.querySelector('[data-action="save"]').addEventListener('click', finish);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
