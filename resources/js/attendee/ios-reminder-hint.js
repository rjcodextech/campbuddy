// My Day → My schedule: on an iPhone/iPad browser tab, saved sessions can't
// get a push reminder until CampBuddy is on the Home Screen (N3). push.js
// says so once, on the first save; every later save stayed silent, so people
// took their reminders to be set (GA, Rajasthan 2026: 13 of 15
// `ios_needs_install` offers went that way). This keeps a quiet card above
// the saved sessions instead — with the install steps one tap away — until
// it is closed. Nothing is asked and no permission is touched.

import { track } from './analytics.js';
import { openInstallSteps } from './install.js';
import { isIos, isStandalone } from './platform.js';
import { render } from './template.js';

export const DISMISS_KEY = 'campbuddy:ios-reminder-hint-dismissed';

const HINT_ID = 'ios-reminder-hint';

/** iPhone/iPad, in a browser tab, with something saved, not closed before. */
export function shouldShowIosHint({ ios, standalone, savedCount, dismissed }) {
  return Boolean(ios) && !standalone && savedCount > 0 && !dismissed;
}

/**
 * Adds, keeps or removes the card right before `beforeEl`. Safe to call on
 * every redraw of My schedule: the card is built once per page.
 */
export function syncIosReminderHint(beforeEl, savedCount) {
  if (!beforeEl) return;

  const existing = document.getElementById(HINT_ID);
  const show = shouldShowIosHint({ ios: isIos(), standalone: isStandalone(), savedCount, dismissed: isDismissed() });

  if (!show) {
    existing?.remove();
    return;
  }
  if (existing) return;

  const card = render('tpl-ios-reminder-hint');
  card.id = HINT_ID;

  card.querySelector('[data-action="how"]').addEventListener('click', () => {
    track('reminder_ios_hint', { result: 'open' });
    openInstallSteps();
  });
  card.querySelector('[data-action="dismiss"]').addEventListener('click', () => {
    track('reminder_ios_hint', { result: 'dismiss' });
    try {
      localStorage.setItem(DISMISS_KEY, '1');
    } catch {
      // No storage: it's gone for this page, and back on the next — still fine.
    }
    card.remove();
  });

  beforeEl.before(card);
  track('reminder_ios_hint', { result: 'view' });
}

function isDismissed() {
  try {
    return localStorage.getItem(DISMISS_KEY) === '1';
  } catch {
    return false;
  }
}
