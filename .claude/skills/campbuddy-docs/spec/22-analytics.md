# 22. Analytics (GA4)

[← Back to index](../SKILL.md) · Previous: [21. Performance & code hygiene](21-performance-hygiene.md)

How the attendee app reports usage to Google Analytics 4, and the rules that keep it inside the privacy posture in [8.5](08-security-privacy.md#85-analytics-guardrail) and [17](17-privacy.md). **The admin panel is deliberately not tracked** — organizers' own clicks would drown the attendee numbers.

## 22.1 How it's wired

| Piece | Where | Job |
|---|---|---|
| Config | `GA_MEASUREMENT_ID` in `.env` → `config('services.google_analytics.measurement_id')` | **Empty = no tag at all**, no requests to Google. Set it on production only, so local/staging never touch the live property. |
| Tag bootstrap | `resources/views/attendee/partials/analytics.blade.php`, included in `layouts/attendee.blade.php` and `welcome.blade.php` | Loads gtag, sends the page view, sets page-level defaults (below). |
| `track()` wrapper | `resources/js/attendee/analytics.js` | The **only** code that calls `gtag('event', …)`. Enforces the allowlist. |
| Declarative tracking | `data-track="event_name"` (+ `data-track-<param>`) on any element; `data-track-on="submit"` for forms | Lets server-rendered Blade and `<template>`s report without JS. Still goes through `track()`, so the allowlist applies. |

**Page-level behaviour, decided in the partial so no later hit can undo it:**

- `page_location` / `page_referrer` are sent with the query string stripped, except `utm_*`, `gclid`, `dclid`, `fbclid`, `msclkid` and `tab`. Without this, GA4 would ship whatever is in the URL to Google on every hit — e.g. an attendee's name from the roster-removal search page.
- Google signals and ad personalisation are off.
- Browsers sending **Global Privacy Control** or **Do Not Track** get no tag (and `track()` silently no-ops).
- Default params on every hit: `event_slug` (when on an event page) and `display_mode` (`standalone` = installed PWA, or `browser`).
- `transport_type: beacon`, so a hit fired as the page unloads (nav taps, "Clear my data") isn't cancelled.
- `debug_mode` is on when `APP_DEBUG=true`, so hits appear in GA's **DebugView**.

## 22.2 The allowlist (the guardrail)

`track()` drops any event not in its `EVENTS` map and any parameter not listed for that event — a stray `track('x', { email })` is a no-op, not a leak. **Never add a param that carries:** a discovery profile field / ID / owner token · onboarding answers · anything from a Camp Card · a roster field or roster search text · deal-lead Name/Email/Mobile. Counts and outcomes are fine; the values never are. Session/sponsor/deal/team titles are the event's own public content, not attendee data.

Uncaught JS errors are reported as **error type + file + line only** (never the message, which can carry IDs), same-origin scripts only, max 5 per page load. API failures report only the first path segment (`discovery`, not `discovery/<id>`).

## 22.3 Event catalogue

Names are `snake_case`; `share`, `generate_lead` and `exception` are GA4 recommended events.

| Area | Event | Params |
|---|---|---|
| Navigation | `select_event` (picker card / "Open …"), `nav_tab_click`, `switch_event_click` | `event_slug` / `tab` |
| Install / PWA | `install_prompt_open`, `install_prompt_result`, `install_complete`, `desktop_notice_dismiss` | `platform` (native\|ios\|android_firefox\|in_app_browser), `outcome` |
| Onboarding | `onboarding_complete`, `onboarding_skip` | — (never the answers) |
| My Day | `schedule_view_switch`, `schedule_filter`, `schedule_search`, `session_expand`, `session_save`, `session_unsave`, `session_link_click`, `reminder_offer` | `view`; `filter_type`, `filter_value`; `query_length`, `results_count` (never the text); `session_id`, `session_title`, `overlap`, `link_type`; `result` |
| Quest | `quest_complete`, `quest_undo`, `quest_nav_click` | `quest_id`, `quest_title`, `quest_group`, `destination` |
| Contribute | `contribute_matches_view`, `contribute_retry`, `contribute_team_view` | `answer_count`, `answers`; `team_id`, `team_name`, `source` |
| Explore | `explore_tab_view`, `sponsor_open`, `deal_open`, `useful_link_click`, `in_app_browser_fallback`, `in_app_browser_external_open` | `tab`; `sponsor_name`, `offer_title`, `link_domain`, `lead_capture`; `link_type`; `via` |
| Deals / leads | `deal_lead_form_open`, `deal_lead_form_cancel`, `generate_lead` | `offer_id`, `offer_title` — **no lead fields** |
| Discovery / roster | `discovery_join_start`, `discovery_join`, `discovery_update`, `discovery_leave`, `discovery_met_mark`, `home_discovery_explore_click`, `roster_search_use`, `roster_removal_search`, `roster_removal_confirm` | `surface` (home\|explore) only — no profile, match or roster data |
| Camp Card | `camp_card_save`, `camp_card_download`, `share`, `camp_card_export_error` | `layout`; `method`, `content_type`, `item_id`; `action` — never its content |
| Data controls | `data_export`, `data_clear` | — |
| Health | `exception`, `api_error` | `description`; `endpoint`, `method`, `status` |

> **Privacy-statement wording:** [17](17-privacy.md) says Quest progress "stays on your phone." `quest_complete`/`quest_undo` and `session_save` are anonymous engagement events, not the progress data itself, and neither is on the [8.5](08-security-privacy.md#85-analytics-guardrail) exclusion list — but if that wording should be literal, delete those entries from `EVENTS` and they stop being sent.

## 22.4 One-time setup in the GA console

1. **Custom definitions → Custom dimensions (event-scoped):** register `event_slug`, `display_mode`, `tab`, `session_id`, `session_title`, `quest_title`, `quest_group`, `team_name`, `sponsor_name`, `offer_title`, `link_domain`, `surface`, `result`, `layout` — a param doesn't show in reports until it's registered. Register `query_length`/`results_count`/`answer_count` as custom *metrics* if you want sums/averages.
2. **Data streams → Enhanced measurement (gear icon):** turn **Outbound clicks OFF**. Enhanced measurement can't be controlled from code, and it would send the destination URL of Explore → People attendee links (personal LinkedIn/X profiles) — a roster field. Also recommended off: **Form interactions** and **Site search** (redundant with the events above).
3. Mark `generate_lead` as a **key event** if you want deal-lead conversions.

## 22.5 Adding or changing an event

Add it to `EVENTS` in `analytics.js` with only the params it needs, then call `track('name', { … })` or put `data-track` on the markup. In `npm run dev`, a call to an unlisted event or param logs a console warning instead of failing silently. Document it in the table above and register any new param in GA.

Tests: `tests/Feature/AnalyticsTagTest.php` covers the tag partial (off without an ID, URL scrubbing, opt-out handling, slug escaping, admin layouts excluded).
