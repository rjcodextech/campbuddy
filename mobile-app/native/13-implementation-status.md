# Native app: kya ban gaya (13 Oct 2026, branch `app`)

## Server (M0) — ho gaya, tests ke saath
| Cheez | Kahan | Tests |
|---|---|---|
| Config API `GET /api/v1/app/config` (ETag, 304, banner expiry) | `Api\App\ConfigController` | `MobileAppConfigTest` (13) |
| Admin → **Mobile app** editor: Navigation, Home, Text (EN/HI), Look, Banner, Switches, History; draft → validate → publish → rollback, audit | `Admin\MobileAppController`, `admin/mobile/index.blade.php`, `MobileAppConfig`, `MobileAppDefaults` | same |
| Contrast / unknown tab / unknown text key / non-https link / HTML in text = refuse | `MobileAppConfig::validate` | same |
| JSON schema (Kotlin models isi se) | `schema/app-config.schema.json` | schema == PHP lists |
| Read APIs: events, event, sessions (delay laga), speakers, sponsors, organizers, deals, free steals, quests, tables, guide, delay, memories, **bundle** (`?since=` 304) | `Api\App\EventController`, `AppEventApi` | `AppEventApiTest` (11), list == picker page |
| Organizer API: login (hashed bearer token, throttled), me, logout, events, reports (read/remove/dismiss), running late (get/set/clear) | `Api\App\OrganizerController`, `AppOrganizer`, `AppOrganizerAuth` | `OrganizerApiTest` (8) |

## Kotlin app (M1 skeleton + kuch asli screens) — ho gaya
`native-app/` (Kotlin 2.1, Compose Multiplatform 1.8, Ktor 3, kotlinx-serialization/datetime):
- Config-driven: tabs, Home blocks, texts, rang, banner, switches, min version server se; bundled default (server defaults se generate, test se check).
- Screens: Picker (events, offline cache), Event Home (hero, happening now, up next), Schedule (day-wise, search), Organizer sign-in, events, reports (remove/dismiss), running late.
- Offline-first repository, `EventClock` (web rules ka port, 8 tests).
- Android debug APK: `mobile-app/app/release/CampBuddy-native-2.0.0-debug.apk` (id `club.campbuddy.app.native`, Capacitor app ke saath chalta hai).
- iOS: `iosApp/project.yml` (XcodeGen) + Swift wrapper + CI job (macOS) — Windows par nahi bana sakte.

## Abhi baaki (plan ke M2–M5)
My Day (save + reminders), onboarding stepper, Camp Card, People/discovery + report/block, Explore (sponsors, deals, free steals), Quest, Contribute, Guide, Memories, outbox (offline writes), FCM, deep links, Inter font, accessibility pass; organizer: dashboard, roster, quests/tables CRUD, QR, analytics, config editor (mobile). Tab jo abhi nahi bana wo "being built" screen dikhata hai (jhooth nahi).

## Chalane ke commands
- Server tests: `npm run check:sync`
- Android: `bash native-app/build-native.sh [test|debug|release]`
- Defaults dobara banana (server defaults badle to): `php artisan campbuddy:app-config-defaults`
