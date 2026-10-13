# Implementation status (branch `app`, 13 Oct 2026)

Kuch bhi push/deploy nahi hua. Har package ek alag commit.

## Ho gaya
| # | Package | Commit | Kya |
|---|---|---|---|
| 0 | Docs | `cb9ab81` | mobile-app docs split + sync |
| 1 | Backend | `69db76a` | `native_push_tokens` table, `POST/DELETE push/native-subscribe`, `GET api/v1/app-config` (min version, store link, feature switches), `/.well-known/assetlinks.json` + `apple-app-site-association`, `config/mobile_app.php`. Sab additive, 8 naye tests |
| 2 | Shell | `a0596b0` | `mobile-app/app/`: Capacitor 8 (pinned), live site, offline.html, allowNavigation sirf campbuddy.club, Android App Links + no backup + no cleartext, iOS app-bound domain + Universal Links entitlement + privacy manifest |
| 3 | Web bridge | `381e83b` | `native-bridge.js` (sirf app UA par, dynamic import): local reminders (10 min pehle, iOS 64 limit handle), Camp Card/PDF share sheet, Android back, App Link / notification tap, update screen. Existing files mein app-guarded hooks (push, install, iPhone card, camp-card, person-card, export-pdf) |
| 4 | CI | `9a36bd2` | `.github/workflows/app-build.yml`: web JS tests, shell config tests + `npm audit`, Android lint + debug APK, iOS simulator build |

| 5 | FCM push | `1533ad8` | `NativePush` (FCM HTTP v1, bina extra package), thank-you / Memories / day-7 / upcoming / export push app installs ko bhi; day-15 (`needs` profile) web-only; bridge mein token register; iOS Firebase hook (`#if canImport`) |
| 6 | Report + block | `732f89b` | Match par Report sheet (reason + note) + hide, `discovery_reports`, Admin → Event → **Reports** tab (Remove profile / Dismiss), 7 tests |
| 7 | Brand assets | is commit | Icons (Android adaptive + legacy, iOS 1024), splash (Android all densities, iOS), offline page with bundled Inter + wordmark, store icon/feature image; `scripts/make-assets.py` se dobara ban sakte hain |

**Aapke karne ka sab kuch ek jagah:** [../SETUP.md](../SETUP.md). **Admin / Event manager app mein:** [15-admin-and-manager-in-app.md](15-admin-and-manager-in-app.md) (plan).

## Abhi nahi (aage ke packages)
1. Organizer entry + organizer push (O1–O5) — plan ready, build nahi.
3. **Server-side per-session delay:** "running late" delay ke baad local reminder ka time purana rehta hai; bridge app khulne par resync karta hai, par live delay ke liye FCM ya schedule data se resync chahiye.
4. **Store screenshots + listing text** (icons/splash ban chuke).
5. **Signing + release workflow:** Play App Signing, Apple certificates, TestFlight — accounts ke baad.
6. **Real device testing:** is machine par JDK/Xcode nahi; Android/iOS build CI par.

## Local par chalane ke liye
- `cd mobile-app/app && npm ci && npx cap sync` phir Android Studio / Xcode mein kholo.
- Server `.env` mein: `MOBILE_ANDROID_SHA256`, `MOBILE_APPLE_TEAM_ID`, store URLs (optional).
- Deploy par `php artisan migrate --force` (naya table).
