// "Make CampBuddy yours" on the WordCamp picker page ("/"), as a short stepper:
// one question at a time, every step skippable (OB1/OB3 in the product spec).
// It never blocks the rest of the page, runs once per device, and the profile
// it collects stays local-only until the attendee separately opts into
// discovery.
//
// The markup is #onboarding-welcome in welcome.blade.php (hidden until this
// finds the device hasn't completed it) — this only wires it. What is saved
// (kv 'onboarding') is the same record as ever, so Home, Contribute,
// discovery and the Camp Card read it unchanged (profile-sync.js).

import { track } from './analytics.js';
import { kvGet, kvSet } from './db.js';
import { MAX_DESCRIBE_TAGS, describeTags, wporgUsername } from './profile-sync.js';
import { showToast } from './toast.js';
import { initWhoToMeetInput } from './who-to-meet.js';


/** What the answers will do, in plain words, for the closing screen. Pure: tested on its own. */
export function summaryFor(profile) {
  const lines = [];

  if (profile.interests?.length) {
    lines.push(`Talks, Contributor Day teams and people for ${profile.interests.slice(0, 3).join(', ')} come first.`);
  }
  if (profile.firstWordCamp === 'yes') {
    lines.push('Beginner-friendly talks are flagged and the start-here guide is ready.');
  } else if (profile.firstWordCamp === 'no') {
    lines.push('The first-timer guide stays out of your way.');
  }
  if (profile.attendingContributorDay === 'yes') {
    lines.push('The Contribute page starts with Contributor Day set up.');
  }
  if (profile.whoToMeet || profile.profession || profile.wporg) {
    lines.push('Your attendee profile and Camp Card start pre-filled. Nothing is shared until you choose to join.');
  }
  if (!lines.length) {
    lines.push('No problem. You can add these any time in your Camp Card.');
  }

  return lines;
}

/** Next step index when moving by `delta`, kept inside 0..last. */
export function stepAfter(index, delta, count) {
  return Math.min(Math.max(index + delta, 0), count - 1);
}

export async function renderOnboardingSection() {
  const section = document.getElementById('onboarding-welcome');
  if (!section) return;

  const existing = await kvGet('onboarding');
  if (existing && existing.completedAt) {
    section.hidden = true;
    return;
  }

  const answers = { interests: [] };
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const intro = section.querySelector('[data-ob-intro]');
  const flow = section.querySelector('[data-ob-flow]');
  const done = section.querySelector('[data-ob-done]');
  const steps = [...section.querySelectorAll('[data-step]')];
  const dots = [...section.querySelectorAll('[data-ob-dots] .ob__dot')];
  const count = section.querySelector('[data-ob-count]');
  const back = section.querySelector('[data-action="back"]');
  const skip = section.querySelector('[data-action="skip"]');
  const next = section.querySelector('[data-action="next"]');
  let index = 0;

  section.hidden = false;
  const whoController = initWhoToMeetInput(section);

  // ---- the steps -------------------------------------------------------------

  const show = (i, { focus = true } = {}) => {
    index = i;
    steps.forEach((step, n) => {
      step.hidden = n !== i;
    });
    dots.forEach((dot, n) => {
      dot.classList.toggle('ob__dot--done', n < i);
      dot.classList.toggle('ob__dot--now', n === i);
    });
    count.textContent = `Step ${i + 1} of ${steps.length}`;
    back.hidden = i === 0;

    const last = i === steps.length - 1;
    next.firstChild.textContent = last ? 'Finish ' : 'Next ';
    skip.textContent = last ? 'Skip and finish' : 'Skip';

    if (focus) steps[i].querySelector('.ob__q')?.focus({ preventScroll: true });
  };

  // Chips: the same tags and the same limit as attendee discovery.
  const picked = section.querySelector('[data-ob-picked]');
  section.querySelectorAll('[data-tag]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tag = btn.dataset.tag;
      const idx = answers.interests.indexOf(tag);
      if (idx === -1) {
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
      if (picked) picked.textContent = String(answers.interests.length);
    });
  });

  // Big yes / no cards: tap again to take the answer back. A tap moves on by itself.
  section.querySelectorAll('.ob__step').forEach((step) => {
    const hidden = step.querySelector('input[type="hidden"][data-field]');
    if (!hidden) return;

    step.querySelectorAll('[data-choice]').forEach((card) => {
      card.addEventListener('click', () => {
        const on = hidden.value !== card.dataset.choice;
        hidden.value = on ? card.dataset.choice : '';
        answers[hidden.dataset.field] = hidden.value || null;
        step.querySelectorAll('[data-choice]').forEach((c) => c.setAttribute('aria-pressed', String(on && c === card)));
        if (on) setTimeout(go, reduceMotion ? 0 : 260, 1);
      });
    });
  });

  section.querySelectorAll('input[type="text"][data-field]').forEach((field) => {
    field.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        go(1);
      }
    });
  });

  // ---- finishing ---------------------------------------------------------------

  // Skip vs. Continue is the only thing reported — never the answers, which
  // stay on the device (spec §8.5).
  const save = async (outcome) => {
    track(outcome === 'skip' ? 'onboarding_skip' : 'onboarding_complete');

    whoController.flush();

    // Read the fields as they are now: a text field someone is still typing
    // in hasn't fired "change" yet.
    section.querySelectorAll('input[data-field]').forEach((field) => {
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

    return profile;
  };

  const leave = () => {
    section.hidden = true;

    // The next step is choosing an event.
    document.getElementById('find-your-camp')?.scrollIntoView({
      behavior: reduceMotion ? 'auto' : 'smooth',
      block: 'start',
    });
  };

  const showDone = (profile) => {
    flow.hidden = true;
    intro.hidden = true;
    done.hidden = false;
    section.querySelector('[data-ob-summary]').replaceChildren(
      ...summaryFor(profile).map((line) => {
        const li = document.createElement('li');
        li.textContent = line;
        return li;
      })
    );
    done.querySelector('.ob__q')?.focus({ preventScroll: true });
  };

  const go = async (delta) => {
    if (flow.hidden) return;

    if (delta > 0 && index === steps.length - 1) {
      showDone(await save('save'));
      return;
    }
    show(stepAfter(index, delta, steps.length));
  };

  // ---- buttons ---------------------------------------------------------------------

  section.querySelector('[data-action="start"]').addEventListener('click', () => {
    track('onboarding_start');
    intro.hidden = true;
    flow.hidden = false;
    show(0);
  });
  next.addEventListener('click', () => go(1));
  back.addEventListener('click', () => go(-1));
  skip.addEventListener('click', () => go(1));
  section.querySelectorAll('[data-action="skip-all"]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      await save('skip');
      leave();
    })
  );
  section.querySelector('[data-action="done"]').addEventListener('click', leave);
}
