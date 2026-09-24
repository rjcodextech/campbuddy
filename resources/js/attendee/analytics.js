// The attendee app's only route to GA4 (tag bootstrap: attendee/partials/
// analytics.blade.php). Nothing else in the codebase calls gtag().
//
// PRIVACY (spec §8.5): this is an ALLOWLIST, enforced here rather than left
// to whoever writes the next call site. An event that isn't in EVENTS is
// dropped, and so is any parameter not listed for that event — so a stray
// track('x', { email }) is a no-op, not a leak. Never add a param carrying:
//   - a discovery profile field, discovery ID or owner token
//   - onboarding answers, or anything read from a Camp Card
//   - a roster field (attendee name, links, avatar) or roster search text
//   - deal-lead Name / Email / Mobile
// Counts and outcomes ("a lead was submitted") are fine; the values
// themselves never are. Session/sponsor/deal/team titles are the event's own
// public content, not attendee data.
//
// Reporting notes: every param below must be registered as an event-scoped
// custom dimension in GA (Admin → Custom definitions) before it shows up in
// reports — see .claude/skills/campbuddy-docs/spec/22-analytics.md.

const EVENTS = {
  // Navigation & acquisition
  select_event: ['event_slug'], // picker: an event card / "Open …" button
  nav_tab_click: ['tab'], // bottom nav
  switch_event_click: [], // topbar "WordCamp's" link back to the picker

  // Install / PWA
  install_prompt_open: ['platform'], // native | ios | android_firefox | in_app_browser
  install_prompt_result: ['outcome'], // accepted | dismissed
  install_complete: [],
  desktop_notice_dismiss: [],

  // First-timer guide
  guide_open: ['surface'], // home_start_here | topbar | picker
  guide_next_click: ['target'], // my_day | quest | camp_card
  start_here_dismiss: [],

  // Onboarding — outcome only, never the answers
  onboarding_complete: [],
  onboarding_skip: [],

  // My Day
  schedule_view_switch: ['view'], // full | mine
  schedule_filter: ['filter_type', 'filter_value'], // day | track | type
  schedule_search: ['query_length', 'results_count'], // never the text
  session_expand: ['session_id', 'session_title'],
  session_save: ['session_id', 'session_title', 'overlap'],
  session_unsave: ['session_id', 'session_title'],
  session_link_click: ['session_id', 'link_type'], // slides | video
  reminder_offer: ['result'],

  // Quest
  quest_complete: ['quest_id', 'quest_title', 'quest_group'], // things_to_do | checklist
  quest_undo: ['quest_id', 'quest_title', 'quest_group'],
  quest_nav_click: ['quest_title', 'destination'],

  // Contribute
  contribute_matches_view: ['answer_count', 'answers'],
  contribute_retry: [],
  contribute_team_view: ['team_id', 'team_name', 'source'], // matches | all

  // Explore
  explore_tab_view: ['tab'], // people | sponsors | deals | info
  sponsor_open: ['sponsor_name', 'link_domain'],
  deal_open: ['offer_title', 'link_domain', 'lead_capture'],
  deal_lead_form_open: ['offer_id', 'offer_title'],
  deal_lead_form_cancel: ['offer_id'],
  generate_lead: ['offer_id', 'offer_title'], // GA4 recommended event; no lead fields
  in_app_browser_fallback: ['link_domain'], // the site refused to be framed
  in_app_browser_external_open: ['link_domain', 'via'], // header | fallback
  useful_link_click: ['link_type'], // Event Info: emergency | code_of_conduct | important

  // People / discovery — actions only, no profile or match data
  discovery_join_start: ['surface'], // home | explore
  discovery_join: ['surface'],
  discovery_update: ['surface'],
  discovery_leave: ['surface'],
  discovery_met_mark: [],
  home_discovery_explore_click: [],
  roster_search_use: [], // once per page load; never the query
  roster_removal_search: [], // never the name
  roster_removal_confirm: [],

  // Camp Card — never its content
  camp_card_save: [],
  camp_card_download: ['layout'],
  share: ['method', 'content_type', 'item_id'], // GA4 recommended event
  camp_card_export_error: ['action', 'layout'],

  // Data controls
  data_export: [],
  data_clear: [],

  // Health
  exception: ['description'], // GA4 recommended event
  api_error: ['endpoint', 'method', 'status'],
};

const MAX_VALUE_LENGTH = 100; // GA4's limit for an event parameter value

function normalize(value) {
  if (typeof value === 'number') return Number.isFinite(value) ? value : undefined;
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (typeof value === 'string') return value.trim().slice(0, MAX_VALUE_LENGTH) || undefined;
  return undefined;
}

export function track(name, params = {}) {
  const allowed = EVENTS[name];

  if (!allowed) {
    if (import.meta.env?.DEV) console.warn(`[analytics] "${name}" is not in the allowlist — dropped.`);
    return;
  }

  const clean = {};

  for (const [key, raw] of Object.entries(params)) {
    if (!allowed.includes(key)) {
      if (import.meta.env?.DEV) console.warn(`[analytics] "${key}" is not allowed on "${name}" — dropped.`);
      continue;
    }

    const value = normalize(raw);
    if (value !== undefined) clean[key] = value;
  }

  // gtag only exists once the page's tag snippet ran — not on local/staging
  // (no measurement ID) and not for visitors sending GPC / Do Not Track.
  if (typeof window.gtag !== 'function') return;

  try {
    window.gtag('event', name, clean);
  } catch {
    // Analytics must never be the reason something breaks.
  }
}

/** Hostname of a URL, for "which site did they open" without the path/query. */
export function linkDomain(url) {
  try {
    return new URL(url, location.href).hostname;
  } catch {
    return '';
  }
}

// Declarative tracking for server-rendered markup and <template>s:
//
//   <a data-track="nav_tab_click" data-track-tab="home">           on click
//   <form data-track="roster_removal_search" data-track-on="submit"> on submit
//
// data-track-* attributes become params (data-track-link-type → link_type).
// They still go through track(), so the allowlist above applies to them too.
function paramsFrom(el) {
  const params = {};

  for (const [key, value] of Object.entries(el.dataset)) {
    if (!key.startsWith('track') || key === 'track' || key === 'trackOn') continue;

    const name = key.slice('track'.length).replace(/[A-Z]/g, (c, i) => (i ? '_' : '') + c.toLowerCase());
    params[name] = value;
  }

  return params;
}

function onClick(e) {
  const el = e.target.closest?.('[data-track]');
  if (!el || el.dataset.trackOn) return;
  track(el.dataset.track, paramsFrom(el));
}

// Bubbling (not capture) on purpose: the roster-removal form confirm()s in
// an inline onsubmit and cancels the submit — a cancelled one isn't a request.
function onSubmit(e) {
  const el = e.target.closest?.('[data-track-on="submit"]');
  if (!el || e.defaultPrevented) return;
  track(el.dataset.track, paramsFrom(el));
}

// Uncaught errors, as an error *type* + file + line only — never the message,
// which can carry whatever the failing code was holding (a discovery ID in a
// failed API path, say). Same-origin scripts only, and capped per page load
// so one bad loop can't flood the property.
let errorBudget = 5;

function reportError(type, where) {
  if (errorBudget <= 0) return;
  errorBudget -= 1;

  const name = /^[A-Za-z]{1,40}$/.test(type ?? '') ? type : 'Error';
  track('exception', { description: where ? `${name} @ ${where}` : name });
}

function onError(e) {
  if (!e.filename?.startsWith(location.origin)) return;
  reportError(e.error?.name, `${e.filename.split('?')[0].split('/').pop()}:${e.lineno}`);
}

function onRejection(e) {
  reportError(e.reason?.name ? `Unhandled${e.reason.name}` : 'UnhandledRejection');
}

export function initAnalytics() {
  document.addEventListener('click', onClick, true);
  document.addEventListener('submit', onSubmit);
  window.addEventListener('error', onError);
  window.addEventListener('unhandledrejection', onRejection);
}
