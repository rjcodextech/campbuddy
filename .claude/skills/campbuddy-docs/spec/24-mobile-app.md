# §24 Mobile app (Android + iOS)

**Status (13 Oct 2026, branch `app`):** built, not yet released. Planning and setup live in `mobile-app/` (start at `mobile-app/README.md`; the owner's one-time configuration is `mobile-app/SETUP.md`).

## What it is
A Capacitor 8 shell (`mobile-app/app/`, app id `club.campbuddy.app`) that opens the live website (`server.url = https://campbuddy.club`). Same product, same no-login rule; the website and PWA are unchanged. Navigation is limited to `campbuddy.club`; other links open in the system browser. A bundled `offline.html` (Inter + wordmark) is the error page.

## What is native (only when the user agent contains `CampBuddyApp/<version>`)
`resources/js/attendee/native-bridge.js`, loaded by a dynamic import from `app.js` (`platform.js` `isNativeApp()`):
- **Session reminders** are local notifications (10 min before, soonest 60 for iOS' 64 limit), re-synced on start, on returning to the app and every 2 minutes while open. Permission rules are the website's (N1, N4).
- **Camp Card / PDF** go through the share sheet (`saveFile`).
- Android back button, App Links / Universal Links, notification taps (same-site paths only, `safeInternalPath`).
- **Update screen** when the build is older than `/api/v1/app-config` `min_version`.
- Install popup, iPhone notice card hidden. GA: `app_open` with `platform = app_ios|app_android` (existing dimension).

## Server side (all additive)
| Piece | Where |
|---|---|
| Settings | `config/mobile_app.php` (`MOBILE_*` env), `services.fcm` (`FCM_CREDENTIALS_PATH`) |
| App start-up check | `GET /api/v1/app-config` |
| Link verification | `/.well-known/assetlinks.json`, `/.well-known/apple-app-site-association` (`WellKnownController`) |
| Attendee push tokens | `native_push_tokens`, `POST/DELETE /api/v1/events/{slug}/push/native-subscribe` |
| FCM sender | `App\Support\NativePush` (HTTP v1, service-account JWT, no extra package); off until credentials exist; hooks in `EventPush::send`, thank-you, Memories, upcoming and export pushes. The day-15 push (`needs: bring`) stays Web-Push-only |
| Organizer tokens + alerts | `organizer_push_tokens`, `POST/DELETE /admin|manager/app-push`, `NativePush::toOrganizers` (admins + the event's active managers) |

## Report and block (Apple 1.2 / Play UGC)
"Report" on a discovery match (`report.js`) posts to `POST /api/v1/events/{slug}/discovery/{id}/report`, hides the person on that phone (the existing hide) and stores `discovery_reports` with a copy of the profile text. Admin and event manager: Event → **Reports** tab (`DiscoveryReports`): remove the profile or dismiss. A new report sends one alert per profile per hour to organizers' phones.

## Admin and event manager in the app
Staff sign-in links on the picker (shown only in the app). `resources/js/organizer-native.js` (app + `/admin|/manager` only): safe-area fit, one-time alerts opt-in, sign-out removes the token, Face ID / fingerprint lock (cold start and after 30 s away). `public/sw.js` never caches `/admin`, `/manager` or sign-in pages.

## Not built / later
Home-screen widget, Wallet pass, bundled (offline-first) shell. See `mobile-app/02-hybrid-app/04-features-by-phase.md` and `12-one-codebase-offline-speed.md`.
