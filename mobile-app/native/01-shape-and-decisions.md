# Shape aur faisle

## Kya banega
Ek **Kotlin Multiplatform** project: ek shared module jisme data, logic aur **Compose Multiplatform UI** hai, aur do patle app shells (`androidApp`, `iosApp`). Dono platform par asli native app (WebView nahi): Android par Compose, iOS par Compose Multiplatform ka UIKit canvas (iOS support Compose Multiplatform 1.8 se stable).

```
Phone (Android / iOS)
  └── CampBuddy native app (Kotlin, Compose)
        ├── Screens: sab native, fixed layout hardcoded
        ├── Content + labels + tabs + rang: GET /api/v1/app/config  (admin editor se)
        ├── Data: REST API → local database (offline-first)
        └── Platform: notifications, push, biometric, share, deep links (expect/actual)
Laravel (wahi server) ── naya /api/v1/app/*  (additive; website + PWA jaise the waise)
```

## Kya server-driven hai aur kya hardcoded
| Hardcoded (app ke andar, release se badlega) | Config editor se (turant) |
|---|---|
| Screens ka layout aur component catalog (Card, List, Hero, Tabs…) | Kaun se tabs, kis order mein, on/off, icon, title |
| Navigation ka tarika, gestures, animations | Event Home ke sections: order, on/off, title |
| Offline/sync rules, reminders ka logic | Saari text/copy, EN/HI translations |
| Security aur permissions ke flows | Rang (theme tokens), logo, splash text |
| Form validation | Banner/announcement, "update karo" message, feature switches, min version |

Rule: config **component choose karta hai, naya component nahi banata**. Isse app hamesha valid rehti hai aur store review ke liye "downloaded code" jaisa kuch nahi (Apple 2.5.2 safe: sirf data, koi code nahi).

## Purani Capacitor app ka kya
- Chalti rahegi (attendee + organizer sign-in link), taaki WordCamp ke time koi gap na ho.
- Native app **alag listing nahi**: wahi store entry (`club.campbuddy.app`) ka major update (2.0) hogi. Staged rollout 5% → 100%.
- Web, PWA, admin panel website par jaise hain waise. API sab ke beech shared hai: ek hi data, teen front-end.

## Sync ki guarantee (aapka niyam)
`npm run check:sync` mein naye checks jodenge: API contract tests (server ka JSON shape = app ke models), config schema ek jagah (`schema/app-config.schema.json`) jisse server validate kare aur Kotlin models bane, version/ids parity, aur har screen ka API route asli ho.
