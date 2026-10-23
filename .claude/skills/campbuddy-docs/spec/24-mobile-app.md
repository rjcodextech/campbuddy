# §24 Mobile app (Android + iOS)

**APK auto-update (23 Oct 2026):** the Android app has two flavors: `direct` (the APK from campbuddy.club, updates itself) and `play` (Play Store AAB, no install permission). Admin → Mobile app → **App updates** uploads a release APK (checked on the server by `App\Support\ApkInspector`: package, newer build, same signing key), publishes it (silent FCM push `kind=app-update`), withdraws it. API: `GET /api/v1/app/update?package=&code=`, file `GET /api/v1/app/releases/{package}/{code}.apk` (Range, immutable), share link `/app/android`. Table `app_releases`. The phone verifies size, SHA-256, package, build number and signing key before PackageInstaller (silent on Android 12+ after the one-time "Install unknown apps" OK). Steps for the owner: `../mobile/SETUP.md` §6b-2.

**Status (22 Oct 2026):** the store app is the native Kotlin app (`native-app/`, Android + iOS); the Capacitor shell described below (`mobile-app/app/`) was retired and deleted on 22 Oct 2026 — the website keeps `native-bridge.js` so phones that still have that shell keep working. Setup, deployment, iOS and API docs: [`../mobile/`](../mobile/) (`SETUP.md`, `DEPLOYMENT.md`, `ios-build-on-mac.md`, `native-status.md`, `api-spec.md`, `server-driven-config.md`).

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

## Native app on iPhone (`native-app/iosApp`, 22 Oct 2026)
Same shared Kotlin/Compose app as Android; Swift shell only (`iOSApp.swift`, `AppDelegate.swift`). iOS parts in `shared/src/iosMain`: deep links (`openLink` from Swift: universal links, `campbuddy://`, push tap `data.url`), Safari sheet for in-app pages, organizer sign-in (`organizer.*`) in the Keychain (cleared on a fresh install), FCM push via Firebase iOS SDK (on only when `GoogleService-Info.plist` is in the build), day PDF with UIKit, share/save/PNG. XcodeGen `project.yml` writes Info.plist + entitlements (push, `applinks:campbuddy.club`); privacy manifest; iPhone only, light mode. Mac build: `bash native-app/build-ios.sh check|sim|open|archive|upload` (bumps `version.properties` like `build-native.sh`), steps in `mobile-app/native/14-ios-build-on-mac.md`. On Windows the iOS Kotlin type-checks with `./gradlew :shared:compileKotlinIosArm64 -Pkotlin.native.enableKlibsCrossCompilation=true`; Swift/Xcode only on a Mac.

## Not built / later
Home-screen widget, Wallet pass, bundled (offline-first) shell. See `mobile-app/02-hybrid-app/04-features-by-phase.md` and `12-one-codebase-offline-speed.md`.
