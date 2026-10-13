# Phases, waqt, risks

Andaaza ek developer + AI madad ke saath; asli numbers pehle milestone ke baad pakke honge.

| Phase | Kya | Hafte |
|---|---|---|
| **M0 Backend foundation** | `/app/*` read APIs + contract tests + JSON schema; Sanctum organizer auth; `mobile_app_configs` + **config editor v1** (Navigation, Home, Strings, Look, Banner, Switches, Publish/Rollback) | 4–5 |
| **M1 Skeleton** | KMP project, design system (Inter, tokens), network + DB + config loader, CI (Android + iOS cloud), picker + event Home + schedule (read-only) | 4 |
| **M2 Attendee core** | My Day, reminders, onboarding, Camp Card, Guide, Memories, offline outbox, FCM, deep links, analytics | 6–8 |
| **M3 Attendee full** | People/discovery + report/block, Explore (sponsors/deals/free steals), Quest, Contribute, device transfer, settings | 5–6 |
| **M4 Organizer v1** | Login, lock, Dashboard, Events, Reports, Notifications, Memories, Delay, Quests, Tables, QR | 5–6 |
| **M5 Organizer v1.1 + polish** | Roster, Deals/Leads, Analytics/Errors/Feedback, config editor (mobile), accessibility pass, perf, soft launch | 4–5 |
| **Store** | review, staged rollout, Capacitor se switch | 2 |
| **Total** | | **~30–36 hafte** (7–9 mahine) |

Mahine ke andar dikhne wala: M0 ke baad admin config editor **web par live** (purani Capacitor app bhi use kar sakti hai: banner/flags). M1–M2 ke baad internal TestFlight/APK.

## Risks
| Risk | Bachaav |
|---|---|
| iOS Windows par test nahi | cloud macOS CI + TestFlight beta; ek Mac/Mac mini kiraye par jab iOS-specific bug aaye |
| Compose iOS ke chhote farak (scroll, keyboard, accessibility) | pehle milestone mein hi iOS par saari 5 screen types chalao |
| Web ke logic ka port galat | web ke test cases Kotlin mein copy, same expected values |
| Scope bahut bada (organizer native) | M4/M5 alag release; Social/Reels/Commands browser par |
| Config editor se galti | schema validation, preview, rollback, audit |
| Do app (Capacitor + native) ka sync | ek API, ek config; Capacitor sirf tab tak jab tak parity |
| Library churn (KMP) | versions pinned, `libs.versions.toml`, saal mein ek upgrade |
| Apple review (4.2, 1.2) | native UI + report/block + demo account |

## "Ye karna chahiye ya nahi" ki sacchai
Fayda: asli native speed/feel, config se turant badlav, ek API se teen client. Keemat: 7–9 mahine, aur web UI + native UI **do jagah** ka kaam (jab bhi naya feature: web + app). Agar WordCamp ke time se pehle chahiye to M0 + purani Capacitor app + config-driven banner/flags pehle karna sabse jaldi value dega.
