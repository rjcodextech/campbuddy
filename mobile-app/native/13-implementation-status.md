# Native app: kya ban gaya (14 Oct 2026, branch `app`)

Native = Kotlin Multiplatform + Compose (koi WebView nahi). Android pura chalta hai (emulator par 46 checks pass); iOS ka code aur CI hai, par Windows par compile nahi ho sakta (macOS CI chahiye).

## Server (M0) — ho gaya, tests ke saath
| Cheez | Kahan | Tests |
|---|---|---|
| Config API `GET /api/v1/app/config` (ETag, 304, banner expiry) | `Api\App\ConfigController` | `MobileAppConfigTest` |
| Admin → **Mobile app** editor: Navigation, Home, Text (EN/HI), Look (10 rang), Banner, Switches, History; draft → validate → publish → rollback, audit | `Admin\MobileAppController`, `admin/mobile/index.blade.php`, `MobileAppConfig`, `MobileAppDefaults` | same |
| Contrast / unknown tab / unknown text key / non-https link / HTML in text = refuse | `MobileAppConfig::validate` | same |
| Read APIs: events, event, sessions (delay laga), speakers, sponsors, deals, free steals, quests, tables, guide, delay, memories, bundle | `Api\App\EventController`, `AppEventApi` | `AppEventApiTest` |
| Organizer API: login (hashed bearer token), events, reports, running late | `Api\App\OrganizerController` | `OrganizerApiTest` |
| Organizer poora (20 Oct): events filters, har event ke 13 tabs (details, information, running late, reports, attendees+roles+admin takedowns, quests, Contributor Day, memories, notifications, deals, deal leads, activity), admin Dashboard/Errors/Feedback/Managers/Activity/Maintenance | `OrganizerManageController`, `OrganizerAdminController` | `OrganizerAppManageTest` |
| Social media tab (20 Oct): posts + people cards app mein draw hote hain (share/save/copy caption/Publish webhook), brand colours, site artwork use/remove/kind, QR kit, webhook | `OrganizerSocialController` | `OrganizerAppSocialTest` |
| Social formats: 3 sizes, Social/Print quality, 7+7 designs, words edit, day/session picker, role filter; Analytics page (GA filters); deal leads CSV export | `OrganizerSocialController`, `OrganizerAdminController@analytics`, `OrganizerManageController@exportLeads` | `OrganizerAppSocialTest`, `OrganizerAppManageTest` |
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

## 14 Oct ke baad jo aur ban gaya
- **Popups**: har popup website jaisa bottom sheet (`ui/Sheet.kt`): paper rang, X button, outline + primary buttons. Meet note, Free Steal sheet, lead form, wave/report/chat, reminders, clear data, Contribute team.
- **My schedule**: People to meet (note, kab, Met / Couldn't meet, hide), session Attended / Missed, plan progress, filters, "Add all to calendar" (.ics), date/time pickers.
- **Save my day as PDF** (My Day → Your data, aur Completed WordCamp card par "Save your WordCamp Data"): phone par hi bannta hai (Hindi bhi theek), share sheet se save. Same sections website jaise.
- Completed WordCamp ka card picker me teal + "Completed" pill. App portrait me locked.
- Production par `/api/v1/app/*` deployed (Free Steal `host`/`made_here`, event `venue` ke saath).
- Accessibility audit: har clickable par role, sirf decoration wali images par `contentDescription = null`.

## Jaan-bujhkar nahi banaya
- **Outbox (offline writes)**: website me ye sirf reminder on/off ko queue karta hai. Native app reminders phone par hi lagata hai (AlarmManager), server ko nahi bhejta, isliye zaroorat nahi.
- **Deals ka FREE badge**: website ke deals me hai hi nahi.

## Abhi baaki
- **Device transfer** (encrypted data ek phone se doosre par) aur **organizer extras** (dashboard, roster, quests/tables CRUD, QR kit, analytics): bade alag kaam hain, web admin se chalte hain.
- Hindi UI ke baaki texts, iOS compile + APNs (macOS CI chahiye; iOS par PDF abhi plain text file banata hai).
- **Owner steps**: Play Console par app listing, `assetlinks.json` (Play signing key ka SHA-256).

## Chalane ke commands
- Server tests: `npm run check:sync`
- Android: `bash native-app/build-native.sh [test|debug|release]`
- Emulator smoke (dev server `php -S 127.0.0.1:8099` ke saath): `python native-app/scripts/emulator_smoke.py --serial emulator-5554`
- Generated files dobara banana: `php artisan campbuddy:app-config-defaults`, `php artisan campbuddy:app-icons`, `node native-app/scripts/gen-contrib-teams.mjs`, `python native-app/scripts/sync-web-assets.py`
