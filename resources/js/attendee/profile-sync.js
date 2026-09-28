// One person, three forms: the picker's "Make CampBuddy yours" answers
// (kv 'onboarding'), the Camp Card (kv 'campCard') and an event's attendee
// discovery profile (kv 'discovery:<eventId>'). The onboarding form asks the
// same things discovery does (same tags, same limit, profession, who to meet,
// WordPress.org), so what someone told one of them fills the empty fields of
// the others, and nobody types the same thing twice.
//
// Only ever a pre-fill: nothing here is sent anywhere. A discovery profile is
// public, so it changes only when the person presses Join or Save on its own
// form, and a field they already filled in is never replaced. Pure functions,
// no DOM and no storage, so the rules are tested on their own
// (tests/js/profile-sync.test.mjs).

/** "What describes you?" — the one list the onboarding form and discovery both offer (welcome.blade.php repeats it; a test keeps them equal). */
export const DESCRIBE_TAGS = [
  'developer', 'designer', 'content creator', 'site builder',
  'community organizer', 'marketer', 'business owner', 'blogger',
  'translator', 'speaker', 'student', 'mentor',
];

// The discovery API takes at most 5 tags (StoreDiscoveryRequest); both forms
// stop there instead of failing on save.
export const MAX_DESCRIBE_TAGS = 5;

/** "Interested in Contributor Day?": the onboarding answer's values (null = not sure yet). */
export const CONTRIBUTOR_DAY = ['yes', 'no'];

const text = (value) => (typeof value === 'string' && value.trim() ? value.trim() : null);

/** Older onboarding answers were saved as "Content creator"; everything now uses the lower-case tag. */
export function describeTags(values) {
  const chosen = new Set((values ?? []).map((t) => String(t).trim().toLowerCase()));

  return DESCRIBE_TAGS.filter((t) => chosen.has(t)).slice(0, MAX_DESCRIBE_TAGS);
}

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
 * What a new discovery profile starts with: the onboarding answers first, the
 * Camp Card for whatever they don't say (and for the name, which onboarding
 * doesn't ask: at the picker no event, and so no attendee list, is chosen yet).
 *
 * @param {{ onboarding?: object|null, campCard?: object|null }} from
 */
export function discoveryPrefill({ onboarding = null, campCard = null } = {}) {
  return {
    tags: describeTags(onboarding?.interests),
    who_to_meet: text(onboarding?.whoToMeet),
    display_name: text(campCard?.name),
    profession: text(onboarding?.profession) ?? text(campCard?.role),
    wporg_username: wporgUsername(onboarding?.wporg) ?? wporgUsername(campCard?.wordpressOrg),
  };
}

/**
 * What an empty Camp Card starts with: this event's discovery profile (name,
 * profession as the role, WordPress.org), then the onboarding answers. Only
 * for fields the card doesn't have yet.
 *
 * @param {object|null} card  the saved Camp Card, if any
 * @param {object|null} discovery  the saved kv 'discovery:<eventId>' record
 * @param {object|null} onboarding
 */
export function campCardPrefill(card, discovery, onboarding = null) {
  const fields = discovery?.fields ?? {};
  const found = {
    name: text(fields.display_name) ?? text(discovery?.card?.name),
    role: text(fields.profession) ?? text(onboarding?.profession),
    wordpressOrg: wporgProfileUrl(fields.wporg_username) ?? wporgProfileUrl(wporgUsername(onboarding?.wporg)),
  };

  return Object.fromEntries(Object.entries(found).filter(([key, value]) => value && !text(card?.[key])));
}

/**
 * The onboarding answers after a discovery profile was saved: its tags,
 * profession, "who to meet" and WordPress.org are the person's latest word,
 * so Home's suggestions and the next pre-fills follow them. The rest (first
 * WordCamp, Contributor Day, whether the card was finished) stays.
 *
 * @param {object|null} onboarding
 * @param {{ tags?: string[], profession?: string|null, who_to_meet?: string|null, wporg_username?: string|null }} saved  what the discovery form sent
 */
export function onboardingAfterDiscovery(onboarding, saved) {
  return {
    ...(onboarding ?? {}),
    interests: describeTags(saved.tags),
    profession: text(saved.profession),
    whoToMeet: text(saved.who_to_meet),
    wporg: wporgUsername(saved.wporg_username),
  };
}

/**
 * The onboarding answers after the Camp Card was saved: its role is the
 * profession, its WordPress.org link the username. A blank card field leaves
 * the answer as it was (a card without a role isn't saying "no profession").
 *
 * @param {object|null} onboarding
 * @param {object} card  the Camp Card as saved
 */
export function onboardingAfterCampCard(onboarding, card) {
  return {
    ...(onboarding ?? {}),
    profession: text(card?.role) ?? text(onboarding?.profession),
    wporg: wporgUsername(card?.wordpressOrg) ?? wporgUsername(onboarding?.wporg),
  };
}

// "What describes you?" → the Contribute questions' answers (contrib-teams.js keys).
const CONTRIBUTE_FOR = {
  developer: ['development'],
  designer: ['design'],
  'content creator': ['writing'],
  'site builder': ['testing'],
  'community organizer': ['organizing'],
  marketer: ['writing'],
  'business owner': ['people'],
  blogger: ['writing'],
  translator: ['translation'],
  speaker: ['documentation'],
  student: ['curious'],
  mentor: ['support'],
};

/**
 * The Contribute questions, answered in advance from "What describes you?".
 * The person can still change every one before seeing their matches.
 *
 * @param {object|null} onboarding
 * @param {string[]} offered  the question keys the Contribute page offers
 */
export function contributePrefill(onboarding, offered) {
  const keys = new Set(describeTags(onboarding?.interests).flatMap((t) => CONTRIBUTE_FOR[t] ?? []));

  return offered.filter((k) => keys.has(k));
}

/** The onboarding answers with a new Contributor Day answer ('yes' | 'no' | null for not sure). */
export function onboardingWithContributorDay(onboarding, answer) {
  return { ...(onboarding ?? {}), attendingContributorDay: CONTRIBUTOR_DAY.includes(answer) ? answer : null };
}
