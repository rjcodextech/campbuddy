# Timeline aur risks

| Hafta | Kaam |
|---|---|
| 0 | Faisle, accounts, Firebase |
| 1 | Shell, deep links, offline screen, external links |
| 2 | Local notifications, Camp Card share, install UI hide, GA platform |
| 3 | **Report/block**, store listing, privacy forms; Android closed testing (14 din) |
| 4–5 | Device testing; TestFlight |
| 5–6 | Submit → live; Phase 2 FCM |

Target: bade India WordCamp se kam se kam 3 hafte pehle dono stores mein.

| Risk | Bachaav |
|---|---|
| Apple 4.2 reject | native features pehle din se; Phase 3 bundled |
| Apple 1.2 reject | report/block pehle |
| Play 14-din testing | organization account |
| Web change app tode | `native-bridge.js` alag; app smoke test har deploy se pehle |
| Double notification | ek device = ek channel |
| Shared host push load | chunked jobs |
| iOS WebView mein service worker | `WKAppBoundDomains`; `sw.js` badle to dobara test |
