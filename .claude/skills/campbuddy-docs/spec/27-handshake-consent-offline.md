# 27 — API handshake, DPDP consent, offline promise (30 Oct 2026)

Owner asked (30 Oct 2026, confirmed twice): verify DPDP Act 2023 compliance, make "100% offline" true and
explained, secure every channel with a 2–3 step handshake, regression-test hard. This page is how it works.

## API handshake (`App\Http\Middleware\VerifyApiHandshake`, on every `/api/v1` route)

1. **Client hello** — `X-CampBuddy-Client` (`pwa` | `native` | `web`), `X-CampBuddy-Device` (the anonymous
   phone id, UUID shape; the app sends its 32-hex id as 8-4-4-4-12), `X-CampBuddy-Timestamp` (unix seconds).
   A present but malformed client or timestamp → 400. A malformed device id is **not** refused — it only gets
   the anonymous rate limit (`ApiClient::key`), as before.
2. **Freshness** — a write more than `campbuddy.handshake.max_skew_seconds` (300) off the server clock →
   401 `HANDSHAKE_TIME_SKEW`, `X-CampBuddy-Handshake-Error: time-skew`, `X-CampBuddy-Server-Time`, `no-store`.
   PWA (`api.js fetchWithRetry`) and app (`ApiClient` `HttpSend` interceptor) learn the offset and retry once;
   later calls carry the corrected time. Reads are never refused for the clock. The PWA outbox never drops a
   queued reminder on this answer (`error.transient`).
3. **Server proof** — every answer: `X-CampBuddy-Handshake: v1`, `X-CampBuddy-Server-Time`. The PWA treats a
   200 that is not JSON (venue wifi sign-in page) as "no connection" (status 0) and keeps the saved copy.

No secret is involved: a public app cannot hide one. Authorisation stays with owner tokens / organizer tokens
over HTTPS (HSTS) behind the rate limits. `API_HANDSHAKE_STRICT=false` (default) lets requests without the
headers through (installed app versions, cached pages); set it true once every phone runs ≥ this release —
then writes without the hello get 400 `HANDSHAKE_REQUIRED` and consent becomes required for every client.

Tests: `tests/Feature/ApiHandshakeTest.php`, `tests/js/api-handshake.test.mjs`, native `HandshakeTest.kt`.

## Consent (DPDP Act s.6; s.9 under-18) — `App\Support\Consent`

- Discovery join and first Camp Card share need `consent: true` from every client that sends the hello
  (website/PWA checkbox `attendee/partials/consent-check.blade.php`, app `ui/ConsentCheck.kt`). Words: "I agree
  to share … as the Privacy notice explains, and I'm 18 or older (or my parent or guardian agrees)."
  Editing a profile / updating a shared card asks nothing new.
- Every consent goes to `consent_logs` with `notice_version` = `campbuddy.legal.notice_version`; clients
  without the box are logged `legacy`. A deal form's Continue (under its notice) is logged as consent.
  A failing log write is reported, never breaks the person's action. UA kept to 255 chars.
- Google Analytics loads only after "Allow" (`localStorage cb-analytics-consent`); "No thanks" sets
  `ga-disable-<id>`; DNT/GPC never asked. "Analytics choice" (footer, privacy notice) asks again.
  Browser test: `tests/browser/analytics-consent.e2e.mjs` (in `npm run test:browser`).
- Erasure (`PersonalDataErasure`, ~90 days after the event) now also covers `free_steal_suggestions` and
  `sponsor_deal_submissions` (their uploaded logo too, unless an approved deal's media asset uses it).
- The privacy notice (Blade **and** `LegalDocument::defaultContent`) lists both forms and consent records.
  **Production shows the admin-saved copy** (`legal_documents`), so after deploy: Admin → Legal → Privacy →
  reset to default (or paste the new rows).

## Offline

- Service worker never stores `no-store` answers, roster-removal searches or the sponsor deal form, and
  deletes any an older worker saved (on activate). Those routes send `Cache-Control: no-store, private`.
- The app saves every tab of an event when it opens (`Repository.warmUp`, at most every 30 min, only counted
  when the server answered) and, with a saved copy, waits at most 4 s on slow wifi before showing it (the
  answer is still saved when it arrives).
- What it means to people: `/guide#offline` (`attendee/partials/offline-explainer.blade.php`); the SW's
  offline page links there.

## Scraping accuracy

- `SchedulePageProbe::parse` reads a page's clock format first: 24-hour pages and pages that print am/pm are
  read as written; only a 12-hour page without am/pm reads 1:00–7:59 as afternoon. Date headings are whole
  numbers ("October 2026" is not 20 October) and a page without a year gives `--MM-DD`.
- `WordCampNormalizer::shownOnPage/slotDate` time a session the site left untimed only when the page shows it
  at exactly one time on one of the event's own days; otherwise "Time to be announced".
- `HtmlText` decodes text a site escaped twice; `FetchLog` messages are cut by characters (Hindi titles once
  produced invalid UTF-8 that MySQL refused). Roster names lose invisible characters (`PersonName`), the
  content hash still uses the site's raw name so suppressed entries stay suppressed.
- Verified 30 Oct 2026 against live data: Bengaluru 2026 25/25 and Delhi 2026 21/21 sessions identical to
  production (title + start); Asia 2027 has no sessions published yet (2 sponsors, 3 organizers).
