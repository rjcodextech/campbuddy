// Recognising the moments of a WordCamp day (registration, lunch, keynote…)
// in a real schedule. Shared by Home (what to do right now) and the Guide
// (what time each moment happens at this event).

function escapeRegExp(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * True when the title contains one of the words as a whole word — plurals
 * allowed ("breaks"), longer words not ("breakfast" is not a "break").
 */
export function titleHasWord(title, words) {
  const lower = (title ?? '').toLowerCase();
  return words.some((w) => new RegExp(`(^|[^a-z])${escapeRegExp(w.toLowerCase())}(s|es)?([^a-z]|$)`).test(lower));
}

/**
 * Is this schedule item a talk, rather than a break, meal or activity?
 * WordCamp marks those "custom"; a site that doesn't set the type at all
 * gets the next best signal — talks have speakers.
 */
export function isTalk(session) {
  if (session.session_type === 'session') return true;
  if (session.session_type) return false;
  return (session.speaker_ids ?? []).length > 0;
}

/**
 * Whether a moment ({ match, talk }) describes this schedule item. A
 * moment that isn't a talk ("break", "lunch") never matches a talk, so
 * "Don't Break Your Site" isn't mistaken for a coffee break.
 */
export function momentMatches(moment, session) {
  if (!moment.match?.length) return false;
  if (!moment.talk && isTalk(session)) return false;
  return titleHasWord(session.title, moment.match);
}
