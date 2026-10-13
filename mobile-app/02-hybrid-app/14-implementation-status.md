# Implementation status (branch `app`, 13 Oct 2026)

Kuch bhi push/deploy nahi hua. Har package ek alag commit.

## Ho gaya
| # | Package | Commit | Kya |
|---|---|---|---|
| 0 | Docs | `cb9ab81` | mobile-app docs split + sync |
| 1 | Backend | `69db76a` | `native_push_tokens` table, `POST/DELETE push/native-subscribe`, `GET api/v1/app-config` (min version, store link, feature switches), `/.well-known/assetlinks.json` + `apple-app-site-association`, `config/mobile_app.php`. Sab additive, 8 naye tests |
| 2 | Shell | `a0596b0` | `mobile-app/app/`: Capacitor 8 (pinned), live site, offline.html, allowNavigation sirf campbuddy.club, Android App Links + no backup + no cleartext, iOS app-bound domain + Universal Links entitlement + privacy manifest |
| 3 | Web bridge | `381e83b` | `native-bridge.js` (sirf app UA par, dynamic import): local reminders (10 min pehle, iOS 64 limit handle), Camp Card/PDF share sheet, Android back, App Link / notification tap, update screen. Existing files mein app-guarded hooks (push, install, iPhone card, camp-card, person-card, export-pdf) |
| 4 | CI | is commit | `.github/workflows/app-build.yml`: web JS tests, shell config tests + `npm audit`, Android lint + debug APK, iOS simulator build |

## Abhi nahi (aage ke packages)
1. **Discovery report + block** (Apple 1.2 blocker) — web mein existing people UI badlega → alag confirmation.
2. **FCM push (Phase 2):** Firebase project, `google-services.json` / `GoogleService-Info.plist` (git mein nahi), `NativePushSender`, existing jobs mein native bhejna (existing code → confirmation), bridge mein token register.
3. **Server-side per-session delay:** "running late" delay ke baad local reminder ka time purana rehta hai; bridge app khulne par resync karta hai, par live delay ke liye FCM ya schedule data se resync chahiye.
4. **Store assets:** icons/splash sizes, screenshots, listing text, privacy answers.
5. **Signing + release workflow:** Play App Signing, Apple certificates, TestFlight — accounts ke baad.
6. **Real device testing:** is machine par JDK/Xcode nahi; Android/iOS build CI par.

## Local par chalane ke liye
- `cd mobile-app/app && npm ci && npx cap sync` phir Android Studio / Xcode mein kholo.
- Server `.env` mein: `MOBILE_ANDROID_SHA256`, `MOBILE_APPLE_TEAM_ID`, store URLs (optional).
- Deploy par `php artisan migrate --force` (naya table).
