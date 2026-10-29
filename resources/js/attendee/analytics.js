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
  picker_cta: ['target', 'event_slug'], // picker hero: choose
  nav_tab_click: ['tab', 'event_slug'], // bottom nav
  switch_event_click: ['event_slug'], // topbar "WordCamp's" link back to the picker

  // Install / PWA
  install_prompt_open: ['platform', 'event_slug'], // native | ios | android_firefox | in_app_browser
  install_prompt_result: ['outcome', 'event_slug'], // accepted | dismissed
  install_complete: ['event_slug'],
  app_open: ['platform', 'event_slug'], // the store app was opened: app_ios | app_android
  desktop_notice_view: ['via', 'event_slug'], // auto | button
  desktop_notice_dismiss: ['event_slug'],

  // Page context — sent once per page load (sendPageContext below)
  page_context: ['page_type', 'event_phase', 'is_online', 'event_slug'],

  // Home
  home_link_click: ['target', 'event_slug'], // full_schedule | all_quests | prep_guide | prep_my_day | prep_quest

  // First-timer guide
  guide_open: ['surface', 'event_slug'], // home_start_here | topbar | picker (hero) | picker_who_first | picker_who_student
  tour_view: ['event_slug'], // the picker's "See it in action" tour came on screen
  guide_next_click: ['target', 'event_slug'], // my_day | quest | camp_card
  guide_section_jump: ['section', 'event_slug'], // here | what | day | words | tips | bring | faq
  guide_section_view: ['section', 'event_slug'], // how far people read — once per section per page load
  glossary_open: ['term', 'event_slug'], // the guide's own public content
  faq_open: ['question', 'event_slug'],
  start_here_dismiss: ['event_slug'],

  // Onboarding — outcome only, never the answers
  onboarding_complete: ['event_slug'],
  onboarding_start: ['event_slug'], // "Let's go" on the Make CampBuddy yours card
  onboarding_skip: ['event_slug'],

  // My Day
  schedule_view_switch: ['view', 'event_slug'], // full | mine
  schedule_filter: ['filter_type', 'filter_value', 'event_slug'], // day | track | type | topic | status (My schedule's All / To do / Done / Couldn't / Hidden)
  schedule_filters_open: ['event_slug'],
  schedule_search: ['query_length', 'results_count', 'event_slug'], // never the text
  session_expand: ['schedule_session_id', 'session_title', 'event_slug'],
  session_save: ['schedule_session_id', 'session_title', 'overlap', 'event_slug'],
  session_unsave: ['schedule_session_id', 'session_title', 'event_slug'],
  session_link_click: ['schedule_session_id', 'link_type', 'event_slug'], // slides | video
  reminder_offer: ['result', 'event_slug'],
  reminder_ios_hint: ['result', 'event_slug'],
  reminder_cancel: ['event_slug'],

  // Quest
  quest_complete: ['quest_id', 'quest_title', 'quest_group', 'event_slug'], // things_to_do | checklist
  quest_undo: ['quest_id', 'quest_title', 'quest_group', 'event_slug'],
  quest_nav_click: ['quest_title', 'destination', 'event_slug'],

  // Contribute
  contribute_matches_view: ['answer_count', 'answers', 'event_slug'],
  contribute_retry: ['event_slug'],
  contribute_team_view: ['team_id', 'team_name', 'source', 'event_slug'], // matches | all

  // Explore
  explore_tab_view: ['tab', 'event_slug'], // people | sponsors | deals | free-steals | info
  sponsor_open: ['sponsor_name', 'link_domain', 'target', 'event_slug'],
  deal_open: ['offer_title', 'link_domain', 'lead_capture', 'target', 'event_slug'],
  deal_lead_form_open: ['offer_id', 'offer_title', 'target', 'event_slug'],
  deal_lead_form_cancel: ['offer_id', 'target', 'event_slug'],
  generate_lead: ['offer_id', 'offer_title', 'target', 'event_slug'], // GA4 recommended event; no lead fields
  in_app_browser_fallback: ['link_domain', 'event_slug'], // the site refused to be framed
  in_app_browser_external_open: ['link_domain', 'via', 'event_slug'], // header | fallback
  useful_link_click: ['link_type', 'event_slug'], // Event Info: emergency | code_of_conduct | important
  // GA4's 50 custom dimensions are all used: offer_title carries the Free Steal's name here.
  free_steal_open: ['offer_title', 'link_domain', 'target', 'event_slug'], // "Get it free" on a Free Steal (new tab)
  free_steal_suggest: ['event_slug'], // a suggestion was sent — never what was typed
  free_steal_suggest_open: ['target', 'event_slug'],
  free_steal_suggest_cancel: ['event_slug'],
  deal_code_copy: ['offer_id', 'offer_title', 'target', 'event_slug'], // a deal's coupon code copied
  // GA4's own ecommerce events, whose fields GA reports without any custom
  // dimension (ITEM_KEYS): Deals as promotions, Free Steals as an item list.
  // A card is "viewed" once per page load when half of it is on screen, and
  // "selected" when its button is tapped — so views, clicks, click-through
  // and position per card. Only the event's own public content.
  view_promotion: ['items', 'event_slug'],
  select_promotion: ['items', 'target', 'event_slug'],
  view_item_list: ['item_list_id', 'item_list_name', 'items', 'event_slug'],
  select_item: ['item_list_id', 'item_list_name', 'items', 'target', 'event_slug'],

  // People / discovery — actions only, no profile or match data
  discovery_join_start: ['surface', 'event_slug'], // home | explore
  // identity = which way they chose to be shown (attendee_list | typed_name |
  // anonymous) — the kind of choice, never the name or the entry picked.
  discovery_join: ['surface', 'identity', 'tag_count', 'event_slug'],
  discovery_join_blocked: ['surface', 'result', 'event_slug'], // why Join didn't go through: no_name_picked | name_too_short | no_tags | save_failed
  discovery_update: ['surface', 'identity', 'tag_count', 'event_slug'],
  discovery_name_taken: ['event_slug'], // the "pick my name" search hit a name already linked
  // Moving everything to another device (device-transfer.js) — only that it happened.
  device_transfer_request: ['event_slug'],
  device_transfer_approve: ['event_slug'],
  device_transfer_decline: ['event_slug'],
  device_transfer_done: ['event_slug'],
  discovery_profile_link_click: ['link_type', 'event_slug'], // wporg | linkedin | twitter | website — never the address
  roster_link_click: ['link_type', 'event_slug'], // same, on "Who's attending"
  discovery_leave: ['surface', 'event_slug'],
  discovery_met_mark: ['event_slug'],
  discovery_wave: ['surface', 'event_slug'], // waved at a match (never who)
  discovery_wave_undo: ['event_slug'],
  discovery_message: ['message_number', 'event_slug'], // 1-3 — never the text or who
  discovery_mutual_view: ['event_slug'], // a mutual wave revealed names on this device

  // Day planner (My schedule): people to meet, ticking things off, calendar
  meet_add: ['source', 'timed', 'event_slug'], // source: roster | discovery — never who or the note
  meet_update: ['source', 'timed', 'event_slug'],
  meet_remove: ['source', 'event_slug'],
  meet_hide: ['source', 'event_slug'], // hidden from the plan (kept, never deleted)
  meet_unhide: ['source', 'event_slug'], // shown again
  meet_status: ['plan_status', 'source', 'event_slug'], // met | missed | cleared; source: roster | discovery — never who
  session_status: ['plan_status', 'schedule_session_id', 'session_title', 'event_slug'], // attended | missed | cleared — which session (public), never who
  calendar_export: ['scope', 'method', 'event_slug'], // scope: meeting | plan; method: ics | google
  plan_reminder_view: ['left_count', 'event_slug'],
  plan_reminder_click: ['left_count', 'event_slug'],
  home_discovery_explore_click: ['event_slug'],
  roster_search_use: ['event_slug'],
  roster_filter: ['filter_value', 'event_slug'],
  person_card: ['result', 'event_slug'],
  camp_card_share: ['result', 'event_slug'], // once per page load; never the query
  roster_removal_search: ['event_slug'], // never the name
  roster_removal_confirm: ['event_slug'],

  // Camp Card — never its content
  camp_card_save: ['event_slug'],
  camp_card_download: ['layout', 'event_slug'],
  share: ['method', 'content_type', 'item_id', 'event_slug'], // GA4 recommended event
  camp_card_export_error: ['action', 'layout', 'event_slug'],

  // Data controls
  data_export: ['method', 'event_slug'], // pdf (Save my day as PDF) | json (raw data)

  // After the event: the thank-you card (thank-you.js)
  thank_you_view: ['surface', 'event_slug'], // event | home
  event_feedback: ['surface', 'rating', 'event_slug'], // rating 1-5 as a number; the comment never goes to GA
  thank_you_next_click: ['surface', 'event_slug'], // "your next WordCamp" link
  move_sheet_view: ['surface', 'event_slug'], // "Take it with you": push | thank_you
  move_sheet_action: ['method', 'event_slug'], // next_wordcamp | pdf
  bring_data_view: ['event_slug'], // "Bring it from your last WordCamp" shown on Home
  bring_data: ['result', 'event_slug'], // done | dismissed
  memories_open: ['target', 'event_slug'], // photos | videos (picker card, thank-you card, Home, Memories page)
  picker_filter: ['target', 'event_slug'], // picker country chosen: a country code, or all
  picker_more: ['event_slug'], // picker "Load more"
  list_more: ['surface', 'event_slug'], // a long list's "Show N more": matches | others
  data_refresh_tap: ['event_slug'], // the "New info. Tap to refresh" pill
  tour_control: ['via', 'event_slug'], // picker tour: prev | next | dot | pause | play
  camp_card_customize: ['target', 'event_slug'], // qr_<link> | field_<name> | interest_add | interest_suggestion | interest_remove
  data_clear: ['event_slug'],

  // Health
  exception: ['description', 'event_slug'], // GA4 recommended event
  api_error: ['endpoint', 'method', 'status', 'event_slug'],
  web_vitals: ['metric_name', 'metric_value', 'metric_rating', 'event_slug'], // LCP | CLS | INP, from real phones
};

const MAX_VALUE_LENGTH = 100; // GA4's limit for an event parameter value

function normalize(value) {
  if (typeof value === 'number') return Number.isFinite(value) ? value : undefined;
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (typeof value === 'string') return value.trim().slice(0, MAX_VALUE_LENGTH) || undefined;
  return undefined;
}

// The fields an `items` entry may carry: GA4's built-in, item-scoped ones.
// Anything else in an item is dropped, like any unlisted param.
export const ITEM_KEYS = ['item_id', 'item_name', 'item_brand', 'item_category', 'item_list_id', 'item_list_name', 'index', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot'];

export function normalizeItems(value) {
  if (!Array.isArray(value)) return undefined;

  const items = value.slice(0, 25)
    .map((item) => Object.fromEntries(Object.entries(item ?? {})
      .filter(([key]) => ITEM_KEYS.includes(key))
      .map(([key, raw]) => [key, normalize(raw)])
      .filter(([, clean]) => clean !== undefined)))
    .filter((item) => Object.keys(item).length > 0);

  return items.length > 0 ? items : undefined;
}

export const EXCLUDED_HOSTS = [
  'campbuddy.club.rjcodex.com',
  'campbuddy.test',
  '127.0.0.1',
  'localhost',
];

export function currentHostname() {
  if (typeof window !== 'undefined' && window.location?.hostname) {
    return window.location.hostname.toLowerCase();
  }
  if (typeof location !== 'undefined') {
    if (location.hostname) return location.hostname.toLowerCase();
    if (location.href) {
      try {
        return new URL(location.href).hostname.toLowerCase();
      } catch {
        return '';
      }
    }
  }
  return '';
}

export function isTrackingDisabled() {
  const host = currentHostname();
  if (!host) return false;
  return EXCLUDED_HOSTS.includes(host) || host.endsWith('.test') || host.endsWith('.local');
}

export function currentEventSlug() {
  if (typeof document === 'undefined') return undefined;
  return document.body?.dataset?.eventSlug
    || (typeof document.getElementById === 'function' ? document.getElementById('app')?.dataset?.eventSlug : undefined)
    || (typeof window !== 'undefined' ? window.__CB_EVENT_SLUG : undefined);
}

export function track(name, params = {}) {
  if (isTrackingDisabled()) return;

  const allowed = EVENTS[name];

  if (!allowed) {
    if (import.meta.env?.DEV) console.warn(`[analytics] "${name}" is not in the allowlist — dropped.`);
    return;
  }

  const clean = {};

  if (allowed.includes('event_slug') && params.event_slug === undefined) {
    const autoSlug = currentEventSlug();
    if (autoSlug) {
      const cleanSlug = normalize(autoSlug);
      if (cleanSlug !== undefined) clean.event_slug = cleanSlug;
    }
  }

  for (const [key, raw] of Object.entries(params)) {
    if (!allowed.includes(key)) {
      if (import.meta.env?.DEV) console.warn(`[analytics] "${key}" is not allowed on "${name}" — dropped.`);
      continue;
    }

    const value = key === 'items' ? normalizeItems(raw) : normalize(raw);
    if (value !== undefined) clean[key] = value;
  }

  // gtag only exists once the page's tag snippet ran — not on local/staging
  // (no measurement ID) and not for visitors sending GPC / Do Not Track.
  if (typeof window === 'undefined' || typeof window.gtag !== 'function') return;

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
  reportError(e.reason?.name ? `Unhandled${e.reason.name}` : 'UnhandledRejection', rejectionPlace(e.reason?.stack));
}

// A rejected promise has no filename/lineno, only a stack: take its first
// frame in our own scripts, as file:line:column (built files are one line, so
// the column is what finds the code). Never the message.
export function rejectionPlace(stack) {
  if (typeof stack !== 'string') return null;

  for (const m of stack.matchAll(/(https?:\/\/[^\s()@]+?):(\d+):(\d+)/g)) {
    if (!m[1].startsWith(location.origin)) continue;
    return `${m[1].split('?')[0].split('/').pop()}:${m[2]}:${m[3]}`;
  }

  return null;
}

// The kind of page and — on an event page — where the event is in time
// (before / during / after, from Home's status line), so every report can
// be split by "how many used it during the event itself".
function sendPageContext() {
  const pageType = document.body?.dataset.pageType;
  if (!pageType) return;

  const eventSlug = currentEventSlug();
  track('page_context', {
    page_type: pageType,
    event_phase: document.body.dataset.eventPhase,
    is_online: navigator.onLine,
    ...(eventSlug ? { event_slug: eventSlug } : {}),
  });

  // …and every later event on the page carries the screen and the phase too,
  // so any event can be split by them (both are registered dimensions).
  try {
    if (typeof window.gtag === 'function') {
      window.gtag('set', { page_type: pageType, ...(document.body.dataset.eventPhase ? { event_phase: document.body.dataset.eventPhase } : {}) });
    }
  } catch {
    // Analytics must never break the page.
  }
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
  if (isTrackingDisabled()) return;
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
