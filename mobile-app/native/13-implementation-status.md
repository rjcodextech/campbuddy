# Native app: kya ban gaya (14 Oct 2026, branch `app`)

Native = Kotlin Multiplatform + Compose (koi WebView nahi). Android pura chalta hai (emulator par 45 checks pass); iOS ka code aur CI hai, par Windows par compile nahi ho sakta (macOS CI chahiye).

## Server (M0) — ho gaya, tests ke saath
| Cheez | Kahan | Tests |
|---|---|---|
| Config API `GET /api/v1/app/config` (ETag, 304, banner expiry) | `Api\App\ConfigController` | `MobileAppConfigTest` |
| Admin → **Mobile app** editor: Navigation, Home, Text (EN/HI), Look (10 rang), Banner, Switches, History; draft → validate → publish → rollback, audit | `Admin\MobileAppController`, `admin/mobile/index.blade.php`, `MobileAppConfig`, `MobileAppDefaults` | same |
| Contrast / unknown tab / unknown text key / non-https link / HTML in text = refuse | `MobileAppConfig::validate` | same |
| Read APIs: events, event, sessions (delay laga), speakers, sponsors, deals, free steals, quests, tables, guide, delay, memories, bundle | `Api\App\EventController`, `AppEventApi` | `AppEventApiTest` |
| Organizer API: login (hashed bearer token), events, reports, running late | `Api\App\OrganizerController` | `OrganizerApiTest` |
| Native app jo website ke discovery/push endpoints bhi use karta hai (`/events/{slug}/discovery…`, `/push/native-subscribe`) | `Api\Discovery*`, `NativePushTokenController` | `NativeKotlinSyncTest` (har path ek asli route) |

## Android app — ye sab ban gaya
- **Website jaisa UI** (PWA ke rang, 5dp corners, border+shadow cards, rang wale tab icons peach pill ke saath): Picker (orbit pills), Home (hero, Day N of M pill, peach Happening-now, Up next, Running late), My Day (switch, search, day pills, LIVE NOW, speakers), Quest (Things to do + Checklist), Explore (tiles, deals Details/Code/Copy, free steals), Contribute (Contributor Day, questions, team matches, tables, team detail → quest tick), Camp Card (Ticket + Classic, QR, Share, Download), Explore Info (venue, help, links), Guide (hero, pills).
- **People / discovery**: join, edit, leave, best matches (shared tags), wave / wave back / take back, chats (3 messages, turn-taking, window), report, hide.
- My Day: save + reminders (AlarmManager), overlap warning; onboarding stepper; "Your data" (clear everything, discovery chhodkar).
- Organizer: sign-in (admin/manager), events, reports, running late.
- Push (FCM) Android store build ke liye: settings `storage/app/analytics/google-services.json` se aati hain (git mein nahi), token server ko tab jaata hai jab notifications on hon.
- Deep links: `https://campbuddy.club/event/<slug>[/<tab>]` aur `campbuddy://event/<slug>[/<tab>]`.
- Offline-first (cache), bundled default config (server defaults se generate; website icons aur Contribute teams bhi generate + test se check).

## Artifacts (`mobile-app/app/release/`)
`CampBuddy-native-2.0.0-release.apk` / `.aab` (upload key se signed), `CampBuddy-native-2.0.0-debug.apk` (id `club.campbuddy.app.native`, store app ke saath chalta hai).

## Abhi baaki
- Deals ka FREE badge aur logos (Coil). Camp Card: 900 DPI export, link chunne ka option.
- Contribute: onboarding interests se pre-fill.
- Outbox (offline writes), device transfer, organizer extras (dashboard, roster, quests/tables CRUD, QR kit, analytics), Hindi UI ke baaki texts, accessibility pass (TalkBack), iOS compile + APNs.
- **Owner steps**: Play Console par app listing, `assetlinks.json` (https deep links verify ke liye, upload key nahi Play signing key ka SHA-256), server par `/api/v1/app/*` deploy (abhi production par nahi hai), phone par test (USB debugging dobara allow).

## Chalane ke commands
- Server tests: `npm run check:sync`
- Android: `bash native-app/build-native.sh [test|debug|release]`
- Emulator smoke (dev server `php -S 127.0.0.1:8099` ke saath): `python native-app/scripts/emulator_smoke.py --serial emulator-5554`
- Generated files dobara banana: `php artisan campbuddy:app-config-defaults`, `php artisan campbuddy:app-icons`, `node native-app/scripts/gen-contrib-teams.mjs`, `python native-app/scripts/sync-web-assets.py`
