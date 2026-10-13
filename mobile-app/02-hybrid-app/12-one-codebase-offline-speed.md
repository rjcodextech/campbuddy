# Ek code, dono platform, offline, fast — kaise

## 1. Ek code ka matlab
| Layer | Kitne codebase |
|---|---|
| Web (Laravel + Blade + JS) | 1 — jaisa abhi hai |
| App shell (Capacitor) | 1 config + 1 `native-bridge.js`; Android/iOS folders generated + chhoti settings |
| Native plugins | official Capacitor plugins (Local Notifications, Push, Share, Filesystem, App, Haptics) — khud ka Kotlin/Swift nahi |
| Platform farak | `native-bridge.js` ke andar chhota `if (ios)`; features mein farak nahi |

Rules jo "bina issue" rakhte hain:
1. **Native code nahi likhna** — sirf plugins. Custom plugin tabhi jab zaroorat ho (widget, Phase 3).
2. **Web parity test:** har web deploy se pehle app UA ke saath smoke test (home, save, offline, share).
3. **Plugin versions pin**; Capacitor major upgrade saal mein ek baar.
4. **CI se build:** GitHub Actions / Codemagic — ek push = Android AAB + iOS IPA; haath se build nahi.
5. **Server feature flag:** `sw.js` ka flags mechanism reuse — app update ke bina feature band/chalu.
6. **Min app version** server se: bahut purani app ko "update karo" screen.

## 2. Offline — web mein abhi kya hai
`sw.js` (network-first + stale-while-revalidate, flags/avatar caches), `db.js` (IndexedDB), `outbox.js` (offline actions baad mein bhejna), `offline-warmup.js` (event pages pehle se cache), `data-freshness.js` (pill).
App mein ye sab chalega **agar** WebView service worker allow kare (iOS: `WKAppBoundDomains`).

## 3. Offline — do raaste (faisla chahiye)
| | A. Pre-cache (Phase 1, recommended) | B. Bundled shell (Phase 3) |
|---|---|---|
| Kaise | live site + SW + pehli baar khulte hi event warm-up; native offline screen fallback | `www/` mein JS/CSS/fonts/icons phone par; data API + IndexedDB se |
| Pehli baar bina internet | ❌ (ek baar online chahiye) | ✅ |
| Dobara bina internet | ✅ | ✅ |
| Naya web deploy turant | ✅ | ❌ bundle update chahiye |
| Kaam | kam (warm-up pehle se hai) | bahut: Blade pages client-rendered banane padenge = **frozen code rewrite** |
| Apple 4.2 | thoda risk | kam risk |

**Recommendation:** A se shuru. Attendee app install ke baad ek baar to online hota hi hai. B tabhi jab data dikhaye ki "pehli baar offline" asli problem hai, ya Apple reject kare.

## 4. Offline ko properly banane ke liye (A mein)
- App pehli baar khulte hi event ka poora schedule, speakers, sponsors, logos warm-up (`offline-warmup.js` — audit karo ki sab pages cover hain).
- Deploy par `CACHE_NAME` version bump → purana cache saaf; cache size limit.
- Offline likhna (save session, wave, feedback) → `outbox.js` → online aate hi sync; "pending" pill.
- Reminders **local notification** → bilkul offline chalte hain (app ka sabse bada offline faayda).
- Camp Card PNG/QR offline banta hai; share sheet offline.
- Offline par jo nahi chalega, saaf bolo: live discovery roster, naye waves, bahar ke links.
- **Test:** airplane mode checklist (install → event kholo → plane mode → har tab → save → wapas online → sync).

## 5. Fast
| Kahan | Kya |
|---|---|
| Build size | `public/build` abhi ~865 KB (52 files) — chhota; images WebP/AVIF, fonts subset |
| Pehla paint | critical CSS, JS `defer`, per-page chunks (vite) |
| Repeat open | SW cache se network ke bina khulna; splash 1 s se kam |
| Lists | `list-window.js` windowing pehle se |
| Images | lazy, size attr, `image-fallback.js`, Cloudflare cache |
| API | ETag/304, chhoti JSON, `DataVersion` se sirf badla hua |
| Low-end Android | Android 9–10 phone par INP/LCP naapo (GA `web_vitals` + Lighthouse) |
| Budget | LCP < 2.5 s (4G mid phone), INP < 200 ms, cold start < 2 s |

## 6. Kaam ka order
1. Owner faisle (1–7).
2. Web par pehle: report/block, offline warm-up audit, perf budget check.
3. Shell + deep links + offline screen + `WKAppBoundDomains`.
4. Local notifications + share/save + UI hide.
5. CI builds, device test (purana Android + iPhone), airplane-mode checklist.
6. Store submit → soft launch → FCM Phase 2.
