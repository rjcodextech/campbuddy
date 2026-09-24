// "You have 5 things left in your plan today" — on the event's days, the
// first time the app is opened (per visit), anywhere but My Day itself.
// Counts only what's still to do: sessions not over and not ticked off,
// and people not yet marked met / couldn't meet (plan.js).

import { track } from './analytics.js';
import { eventFacts } from './calendar.js';
import { getBookmarks, getMeetings } from './db.js';
import { computePlan, isEventDay } from './plan.js';
import { render } from './template.js';

const SEEN_KEY = 'campbuddy:plan-reminder-seen';

export async function initPlanReminder() {
  const app = document.getElementById('app');
  const main = document.getElementById('main-content');
  if (!app || !main || document.body.dataset.pageType === 'my_day') return;

  const facts = eventFacts();
  if (!isEventDay(facts)) return;

  const eventId = Number(app.dataset.eventId);
  const seenKey = `${SEEN_KEY}:${eventId}`;
  try {
    if (sessionStorage.getItem(seenKey)) return;
  } catch {
    // No sessionStorage: show it; dismissing still hides it for this page.
  }

  let plan;
  try {
    const [bookmarks, meetings] = await Promise.all([getBookmarks(eventId), getMeetings(eventId)]);
    plan = computePlan(bookmarks, meetings);
  } catch {
    return;
  }
  if (plan.left === 0) return;

  const markSeen = () => {
    try {
      sessionStorage.setItem(seenKey, '1');
    } catch {
      // Fine — it's a gentle nudge either way.
    }
  };

  const banner = render('tpl-plan-reminder', {
    count: `${plan.left} ${plan.left === 1 ? 'thing' : 'things'} left today`,
    rest: '— tick off what\'s done.',
    link: { attrs: { href: `${facts.myDayUrl}#mine` } },
  });

  banner.querySelector('a').addEventListener('click', () => {
    markSeen();
    track('plan_reminder_click', { left_count: plan.left });
  });
  banner.querySelector('[data-plan-dismiss]').addEventListener('click', () => {
    markSeen();
    banner.remove();
  });

  main.prepend(banner);
  track('plan_reminder_view', { left_count: plan.left });
}
