# mobile-app

Planning docs + `app/` (Capacitor shell, branch `app`; last sync: **13 Oct 2026**, branch `v2_dev`). App ka koi code abhi nahi bana. Har file chhoti hai — ek file, ek topic.

## Padhne ka order

| Folder | Kya hai |
|---|---|
| [02-hybrid-app/](02-hybrid-app/README.md) | Android + iOS hybrid app (Capacitor) ka plan, 13 chhoti files (ek code, offline, speed, industry standards bhi) |

Purane planning folders (00-web-today, 01-ga-report, 03-p0-packages) aur mockups ab `E:\Sites\InHouse\campbuddyackup6-10-14-cleanup\` mein hain.

## Ek line mein status

- Web: P0 A + B ban chuke; ab 30-din retention, day 7/15 push, Memories, Notifications tab, GA tracking bhi (sab `v2_dev`, deploy baaki).
- App: plan taiyaar, **owner ke 7 faisle baaki** → [02-hybrid-app/11-owner-decisions.md](02-hybrid-app/11-owner-decisions.md).
- Naya blocker mila: Discovery mein **report/block web mein hai hi nahi** → Apple 1.2 ke liye zaroori.
- **[SETUP.md](SETUP.md) — accounts, Firebase, signing, server `.env`: aapki ek saath configuration list.**
- **[native/README.md](native/README.md) — Kotlin native app (bina WebView) ka plan: KMP + Compose, API + server-driven config + admin config editor, attendee + organizer.**
