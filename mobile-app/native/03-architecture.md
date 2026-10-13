# Architecture

## Modules (Gradle)
```
native/
  shared/
    core-model/        data classes (API ke mirror), kotlinx.serialization
    core-network/      Ktor client, auth, ETag cache, error mapping
    core-db/           SQLDelight schema + queries
    core-config/       app-config model, parser, defaults (bundled), cache
    core-domain/       reminders, schedule/delay, people-state, profile-sync (web logic ka Kotlin port)
    design-system/     theme tokens, typography (Inter), components (Card, Chip, Tab bar…)
    feature-picker/ feature-home/ feature-schedule/ feature-people/ feature-camp-card/
    feature-memories/ feature-quest/ feature-contribute/ feature-explore/ feature-guide/ feature-onboarding/
    feature-organizer-*/   (admin + manager screens)
  androidApp/          Activity, FCM service, AlarmReceiver, manifest
  iosApp/              SwiftUI wrapper, AppDelegate (APNs/Firebase), Info.plist
  schema/              app-config.schema.json (server + app dono isi se)
```

## Data flow (offline-first)
1. Screen sirf **local DB** padhta hai (Flow). Network kabhi UI ko seedha nahi bharta.
2. **Repository** server se fetch karke DB update karta hai (ETag / `data-version` se sirf badla hua).
3. Likhna (save session, wave, feedback, report): DB mein turant, phir **outbox** queue → server; offline mein jama, online par bhejta hai (web ke `outbox.js` ka Kotlin port).
4. Config: bundled default → cached last-good → fresh fetch. Kharab config (schema mismatch) = last-good par wapas.
5. Event ka "ab kya" (phase, countdown, Up next) phone ki clock + event ke time zone se, web wale `EventTime` jaise.

## Web se port hone wala logic (ek-ek ka test web test se match)
| Web file | Kotlin |
|---|---|
| `eventtime.js`, home phase/up-next | `core-domain/EventClock` |
| `native-bridge.js` reminders, `ScheduleDelay` | `ReminderScheduler` (expect/actual) |
| `people-state.js`, `people-status.js` | `PeopleState` |
| `profile-sync.js` | `ProfileSync` |
| `outbox.js`, `push-sync.js` | `Outbox` |
| `calendar.js` (.ics) | `IcsBuilder` |
| `camp-card-paint.js` (PNG) | Compose canvas → bitmap export |

Tests: web ke JS test cases ko Kotlin tests mein copy karke same expected values (parity).

## Error / empty / loading
Har screen ke teen state (loading skeleton, empty, error + retry) design system mein standard; offline banner ek jagah.

## Accessibility + typography
Dynamic type (font scale 200%), TalkBack/VoiceOver labels har control par, tap target ≥ 48dp/44pt, contrast AA, reduced motion respect. Inter font bundled, scale tokens config mein nahi (design hardcoded).
