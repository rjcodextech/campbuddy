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
  picker_cta: ['target'], // picker hero: choose
  nav_tab_click: ['tab'], // bottom nav
  switch_event_click: [], // topbar "WordCamp's" link back to the picker

  // Install / PWA
  install_prompt_open: ['platform'], // native | ios | android_firefox | in_app_browser
  install_prompt_result: ['outcome'], // accepted | dismissed
  install_complete: [],
  desktop_notice_view: ['via'], // auto | button
  desktop_notice_dismiss: [],

  // Page context — sent once per page load (sendPageContext below)
  page_context: ['page_type', 'event_phase', 'is_online'],

  // Home
  home_link_click: ['target'], // full_schedule | all_quests | prep_guide | prep_my_day | prep_quest

  // First-timer guide
  guide_open: ['surface'], // home_start_here | topbar | picker (hero) | picker_who_first | picker_who_student
  tour_view: [], // the picker's "See it in action" tour came on screen
  guide_next_click: ['target'], // my_day | quest | camp_card
  guide_section_jump: ['section'], // here | what | day | words | tips | bring | faq
  guide_section_view: ['section'], // how far people read — once per section per page load
  glossary_open: ['term'], // the guide's own public content
  faq_open: ['question'],
  start_here_dismiss: [],

  // Onboarding — outcome only, never the answers
  onboarding_complete: [],
  onboarding_skip: [],

  // My Day
  schedule_view_switch: ['view'], // full | mine
  schedule_filter: ['filter_type', 'filter_value'], // day | track | type | topic | status (My schedule's All / To do / Done / Couldn't / Hidden)
  schedule_filters_open: [],
  schedule_search: ['query_length', 'results_count'], // never the text
  session_expand: ['schedule_session_id', 'session_title'],
  session_save: ['schedule_session_id', 'session_title', 'overlap'],
  session_unsave: ['schedule_session_id', 'session_title'],
  session_link_click: ['schedule_session_id', 'link_type'], // slides | video
  reminder_offer: ['result'],
  reminder_cancel: [],

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
  // identity = which way they chose to be shown (attendee_list | typed_name |
  // anonymous) — the kind of choice, never the name or the entry picked.
  discovery_join: ['surface', 'identity', 'tag_count'],
  discovery_update: ['surface', 'identity', 'tag_count'],
  discovery_name_taken: [], // the "pick my name" search hit a name already linked
  discovery_profile_link_click: ['link_type'], // wporg | linkedin | twitter | website — never the address
  roster_link_click: ['link_type'], // same, on "Who's attending"
  discovery_leave: ['surface'],
  discovery_met_mark: [],
  discovery_wave: ['surface'], // waved at a match (never who)
  discovery_wave_undo: [],
  discovery_message: ['message_number'], // 1-3 — never the text or who
  discovery_mutual_view: [], // a mutual wave revealed names on this device

  // Day planner (My schedule): people to meet, ticking things off, calendar
  meet_add: ['source', 'timed'], // source: roster | discovery — never who or the note
  meet_update: ['source', 'timed'],
  meet_remove: ['source'],
  meet_hide: ['source'], // hidden from the plan (kept, never deleted)
  meet_unhide: ['source'], // shown again
  meet_status: ['plan_status'], // met | missed | cleared
  session_status: ['plan_status'], // attended | missed | cleared
  calendar_export: ['scope', 'method'], // scope: meeting | plan; method: ics | google
  plan_reminder_view: ['left_count'],
  plan_reminder_click: ['left_count'],
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
  web_vitals: ['metric_name', 'metric_value', 'metric_rating'], // LCP | CLS | INP, from real phones
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
    if (!key.startsWith('track') || ['track', 'trackOn', 'trackOpen', 'trackSectionView'].includes(key)) continue;

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

// The kind of page and — on an event page — where the event is in time
// (before / during / after, from Home's status line), so every report can
// be split by "how many used it during the event itself".
function sendPageContext() {
  const pageType = document.body?.dataset.pageType;
  if (!pageType) return;

  track('page_context', {
    page_type: pageType,
    event_phase: document.body.dataset.eventPhase,
    is_online: navigator.onLine,
  });
}

// Core Web Vitals as real phones on venue wifi experience them: Largest
// Contentful Paint, Cumulative Layout Shift and (roughly) Interaction to
// Next Paint, each sent once when the page is hidden. Rated with Google's
// own thresholds, so GA can report "% of good page loads".
function observeWebVitals() {
  if (typeof PerformanceObserver !== 'function') return;

  const metrics = { LCP: null, CLS: 0, INP: null };
  const watch = (type, fn) => {
    try {
      new PerformanceObserver((list) => list.getEntries().forEach(fn)).observe({ type, buffered: true });
    } catch {
      // Not supported in this browser — that metric just isn't sent.
    }
  };

  watch('largest-contentful-paint', (e) => { metrics.LCP = e.startTime; });
  watch('layout-shift', (e) => { if (!e.hadRecentInput) metrics.CLS += e.value; });
  watch('event', (e) => { if (e.interactionId) metrics.INP = Math.max(metrics.INP ?? 0, e.duration); });

  const thresholds = { LCP: [2500, 4000], CLS: [0.1, 0.25], INP: [200, 500] };
  let sent = false;

  document.addEventListener('visibilitychange', () => {
    if (sent || document.visibilityState !== 'hidden') return;
    sent = true;

    for (const [name, value] of Object.entries(metrics)) {
      if (value === null) continue;
      const [good, poor] = thresholds[name];
      track('web_vitals', {
        metric_name: name,
        metric_value: name === 'CLS' ? Math.round(value * 1000) / 1000 : Math.round(value),
        metric_rating: value <= good ? 'good' : value <= poor ? 'needs_improvement' : 'poor',
      });
    }
  });
}

// <details data-track-open="glossary_open" data-track-term="…"> — reported
// when opened, not when closed again.
function onToggle(e) {
  const el = e.target;
  if (!(el instanceof HTMLDetailsElement) || !el.open || !el.dataset.trackOpen) return;
  track(el.dataset.trackOpen, paramsFrom(el));
}

// Sections marked data-track-section-view="name": reported once each, the
// first time its top reaches the middle of the screen — how far people
// actually read. (Not "half of it visible": a section taller than two
// screens, like the day timeline, would never count.)
function observeSectionViews() {
  const sections = document.querySelectorAll('[data-track-section-view]');
  if (sections.length === 0 || typeof IntersectionObserver !== 'function') return;

  const seen = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      seen.unobserve(entry.target);
      track('guide_section_view', { section: entry.target.dataset.trackSectionView });
    });
  }, { rootMargin: '0px 0px -50% 0px' });

  sections.forEach((s) => seen.observe(s));
}

export function initAnalytics() {
  document.addEventListener('click', onClick, true);
  document.addEventListener('submit', onSubmit);
  document.addEventListener('toggle', onToggle, true);
  window.addEventListener('error', onError);
  window.addEventListener('unhandledrejection', onRejection);

  sendPageContext();
  observeWebVitals();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeSectionViews);
  } else {
    observeSectionViews();
  }
}
