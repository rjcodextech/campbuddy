// One person, three forms: the picker's "Make CampBuddy yours" answers
// (kv 'onboarding'), the Camp Card (kv 'campCard') and an event's attendee
// discovery profile (kv 'discovery:<eventId>'). What someone already told one
// of them fills the empty fields of the others, so nobody types the same
// thing twice.
//
// Only ever a pre-fill: nothing here is sent anywhere. A discovery profile is
// public, so it changes only when the person presses Join or Save on its own
// form, and a field they already filled in is never replaced. Pure functions,
// no DOM and no storage, so the rules are tested on their own
// (tests/js/profile-sync.test.mjs).

const text = (value) => (typeof value === 'string' && value.trim() ? value.trim() : null);

/** "profiles.wordpress.org/jane" or a full profile link → "jane"; a bare username stays as it is. */
export function wporgUsername(value) {
  const v = text(value);
  if (!v) return null;
  const m = /profiles\.wordpress\.org\/([^/?#\s]+)/i.exec(v);
  if (m) return decodeURIComponent(m[1]);
  return /^[\w.@-]+$/.test(v) ? v : null;
}

/** A WordPress.org username → its profile link, as the Camp Card stores links. */
export function wporgProfileUrl(username) {
  const u = text(username);
  return u ? `https://profiles.wordpress.org/${encodeURIComponent(u)}/` : null;
}

/**
 * What a new discovery profile starts with: the onboarding answers ("What
 * describes you?" and "Who would you like to meet?") and the Camp Card (name,
 * role, WordPress.org profile). Tags are kept only when discovery offers them,
 * in the order it offers them, up to its limit.
 *
 * @param {{ onboarding?: object|null, campCard?: object|null, tags: string[], maxTags: number }} from
 */
export function discoveryPrefill({ onboarding = null, campCard = null, tags, maxTags }) {
  const chosen = new Set((onboarding?.interests ?? []).map((t) => String(t).trim().toLowerCase()));

  return {
    tags: tags.filter((t) => chosen.has(t)).slice(0, maxTags),
    who_to_meet: text(onboarding?.whoToMeet),
    display_name: text(campCard?.name),
    profession: text(campCard?.role),
    wporg_username: wporgUsername(campCard?.wordpressOrg),
  };
}

/**
 * What an empty Camp Card starts with, from this event's discovery profile:
 * the name (typed, or from the attendee list), the profession as the role, and
 * the WordPress.org profile. Only for fields the card doesn't have yet.
 *
 * @param {object|null} card  the saved Camp Card, if any
 * @param {object|null} discovery  the saved kv 'discovery:<eventId>' record
 */
export function campCardPrefill(card, discovery) {
  if (!discovery) return {};

  const fields = discovery.fields ?? {};
  const found = {
    name: text(fields.display_name) ?? text(discovery.card?.name),
    role: text(fields.profession),
    wordpressOrg: wporgProfileUrl(fields.wporg_username),
  };

  return Object.fromEntries(Object.entries(found).filter(([key, value]) => value && !text(card?.[key])));
}

/** "content creator" → "Content creator", the way the onboarding chips are written. */
const onboardingTag = (tag) => tag.charAt(0).toUpperCase() + tag.slice(1);

/**
 * The onboarding answers after a discovery profile was saved: its tags and
 * "who to meet" are the person's latest word, so Home's suggestions follow
 * them. Everything else (first WordCamp, Contributor Day, whether the
 * onboarding card was finished) stays as it was.
 *
 * @param {object|null} onboarding
 * @param {{ tags?: string[], who_to_meet?: string|null }} saved  what the discovery form sent
 */
export function onboardingAfterDiscovery(onboarding, saved) {
  return {
    ...(onboarding ?? {}),
    interests: (saved.tags ?? []).map(onboardingTag),
    whoToMeet: text(saved.who_to_meet),
  };
}
