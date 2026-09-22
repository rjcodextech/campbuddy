// 4-step onboarding (§3.11), rendered as a <dialog> overlay rather than a
// route — it's explicitly "not a tab" (§1.2). Runs once per device; the
// profile it collects stays local-only (§8.3) until the attendee
// separately opts into discovery (§3.4 M2, built in a later phase).

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

export async function runOnboardingIfNeeded(eventName) {
  const existing = await kvGet('onboarding');

  if (existing && existing.completedAt) {
    return existing;
  }

  return new Promise((resolve) => {
    const dialog = buildDialog(eventName, resolve);
    document.body.appendChild(dialog);
    dialog.showModal();
  });
}

function buildDialog(eventName, resolve) {
  const dialog = document.createElement('dialog');
  let step = 0;
  const answers = { interests: [] };

  const steps = [welcomeStep(eventName), aboutYouStep(), readyStep()];

  function render() {
    dialog.innerHTML = '';
    dialog.appendChild(steps[step](answers, advance, skip));
  }

  function advance() {
    if (step < steps.length - 1) {
      step += 1;
      render();
    } else {
      finish();
    }
  }

  function skip() {
    finish();
  }

  async function finish() {
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
    dialog.close();
    dialog.remove();
    resolve(profile);
  }

  render();
  return dialog;
}

function welcomeStep(eventName) {
  return (_answers, advance) => {
    const el = document.createElement('div');
    el.className = 'dialog-card';
    el.innerHTML = `
      <p class="mission-big">Welcome to CampBuddy.</p>
      <p>Your guide to ${escapeHtml(eventName)} — what's happening, who's here, and what to do next.</p>
      <div style="margin-top:20px;display:flex;justify-content:flex-end">
        <button type="button" class="btn btn--primary" data-action="next">Get started</button>
      </div>
    `;
    el.querySelector('[data-action="next"]').addEventListener('click', advance);
    return el;
  };
}

function aboutYouStep() {
  return (answers, advance, skip) => {
    const el = document.createElement('div');
    el.className = 'dialog-card';

    const tagButtons = INTEREST_TAGS.map(
      (tag) => `<button type="button" class="pill" data-tag="${escapeHtml(tag)}">${escapeHtml(tag)}</button>`
    ).join(' ');

    el.innerHTML = `
      <p class="section-head__title" style="margin:0 0 4px">Tell us a little about you</p>
      <p class="footer-note" style="margin:0 0 16px;text-align:left">Every question here is skippable.</p>

      <label class="field">
        <span class="footer-note" style="text-align:left;display:block;margin-bottom:4px">Is this your first WordCamp?</span>
        <select data-field="firstWordCamp" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line)">
          <option value="">Prefer not to say</option>
          <option value="yes">Yes, first one!</option>
          <option value="no">No, I've been before</option>
        </select>
      </label>

      <div style="margin-bottom:12px">
        <span class="footer-note" style="text-align:left;display:block;margin-bottom:6px">What are you into?</span>
        <div style="display:flex;flex-wrap:wrap;gap:6px">${tagButtons}</div>
      </div>

      <label class="field">
        <span class="footer-note" style="text-align:left;display:block;margin-bottom:4px">Who would you like to meet?</span>
        <input type="text" data-field="whoToMeet" placeholder="e.g. other plugin developers" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line)">
      </label>

      <label class="field">
        <span class="footer-note" style="text-align:left;display:block;margin-bottom:4px">Interested in Contributor Day?</span>
        <select data-field="attendingContributorDay" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line)">
          <option value="">Not sure yet</option>
          <option value="yes">Yes</option>
          <option value="no">Not this time</option>
        </select>
      </label>

      <div style="display:flex;justify-content:space-between">
        <button type="button" class="btn btn--ghost" data-action="skip">Skip</button>
        <button type="button" class="btn btn--primary" data-action="next">Continue</button>
      </div>
    `;

    el.querySelectorAll('[data-tag]').forEach((btn) => {
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

    el.querySelectorAll('[data-field]').forEach((field) => {
      field.addEventListener('change', () => {
        answers[field.dataset.field] = field.value || null;
      });
    });

    el.querySelector('[data-action="skip"]').addEventListener('click', skip);
    el.querySelector('[data-action="next"]').addEventListener('click', advance);

    return el;
  };
}

function readyStep() {
  return (_answers, advance) => {
    const el = document.createElement('div');
    el.className = 'dialog-card';
    el.innerHTML = `
      <p class="mission-big">You're all set.</p>
      <p>Head to Home for what's happening right now.</p>
      <div style="margin-top:20px;display:flex;justify-content:flex-end">
        <button type="button" class="btn btn--primary" data-action="done">Go to Home</button>
      </div>
    `;
    el.querySelector('[data-action="done"]').addEventListener('click', advance);
    return el;
  };
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
