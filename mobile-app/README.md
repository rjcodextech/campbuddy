# mobile-app

Planning docs + `app/` (Capacitor shell, branch `app`; last sync: **13 Oct 2026**, branch `v2_dev`). App ka koi code abhi nahi bana. Har file chhoti hai — ek file, ek topic.

## Padhne ka order

| Folder | Kya hai |
|---|---|
| [00-web-today/](00-web-today/README.md) | Web app mein abhi kya-kya live/ready hai (app ko isi par baithna hai) |
| [01-ga-report/](01-ga-report/README.md) | GA report (26 Sep – 5 Oct) se seekh, P0/P1/P2 improvements, GA setup |
| [02-hybrid-app/](02-hybrid-app/README.md) | Android + iOS hybrid app (Capacitor) ka plan, 13 chhoti files (ek code, offline, speed, industry standards bhi) |
| [03-p0-packages/](03-p0-packages/README.md) | P0 packages A (iPhone reminder card) aur B (QR kit) — ho chuke |

## Ek line mein status

- Web: P0 A + B ban chuke; ab 30-din retention, day 7/15 push, Memories, Notifications tab, GA tracking bhi (sab `v2_dev`, deploy baaki).
- App: plan taiyaar, **owner ke 7 faisle baaki** → [02-hybrid-app/11-owner-decisions.md](02-hybrid-app/11-owner-decisions.md).
- Naya blocker mila: Discovery mein **report/block web mein hai hi nahi** → Apple 1.2 ke liye zaroori.
