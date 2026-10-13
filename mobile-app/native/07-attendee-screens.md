# Attendee screens (web → native)

| Web page | Native screen | API / data | Offline |
|---|---|---|---|
| Picker `/` | **Picker**: hero, events list (country filter + Load more), personalise card, how-it-helps | `app/events`, config | last list cached |
| Onboarding stepper | **Onboarding** (5 steps, skip/next) | local only (`ProfileSync`) | full |
| Event Home | **Home** (config-driven sections) | `app/events/{slug}` + sessions | full |
| My Day (full + my schedule) | **Schedule**: tabs, search, filters, star, overlap warning, expand | `sessions`, local bookmarks | full |
| Add to calendar | share `.ics` (`IcsBuilder`) | local | full |
| Reminders | local notifications (+ FCM for announcements) | local, `native-subscribe` | full |
| Quest | **Quest**: checklist/quests, progress | `quests`, local progress | full |
| Contribute | **Contribute**: Contributor Day tables, teams suggestion | `contributor-tables`, `ProfileSync` | full |
| Explore → People | **People**: roster list, role filter, discovery join, matches, waves, chat, Report/Block | `roster`, `discovery…`, `report` | roster cached; writes outbox |
| Explore → Sponsors / Deals / Free steals / Info | **Explore** sub-tabs; deal form (lead) | `sponsors`, `deals`, `free-steals`, leads | cached; lead via outbox |
| Camp Card | **Camp Card**: 5 layouts, QR, export PNG/PDF, share | local + `camp-card-share` | full |
| Guide (general + event) | **Guide** (sections, search) | `guide` | full |
| Memories | **Memories** | `memories` | cached |
| Thank-you card, feedback | **After event** card, rating | `feedback` | outbox |
| Bring / move data (device transfer) | **My data**: export PDF, move to new phone, bring from last WordCamp | `device-transfers…`, local | partial |
| Settings (new) | language, notifications, about, privacy, delete my data, organizer sign in | — | full |
| Install / desktop notice | gayab (native) | — | — |

## Standard states
Har list: loading skeleton, empty (config string), error + retry, pull-to-refresh, offline banner. "Updated 2 min ago" pill (web `data-freshness` jaisa).

## Parity checklist
Web ke har feature ka ek test case "native mein same" — `native/parity.md` table (feature, web test, Kotlin test). Jab tak sab ✔ nahi, purani Capacitor app default rahegi.

## Analytics
Wahi GA4 events (same names, same allowlist, koi PII nahi) — Firebase Analytics ya GA Measurement Protocol server-side; `platform = app_ios|app_android`. Naya dimension nahi.
