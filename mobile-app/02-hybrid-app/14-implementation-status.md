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
| 6 | Report + block | `a619592` | Match par Report sheet (reason + note) + hide, `discovery_reports`, Admin → Event → **Reports** tab (Remove profile / Dismiss), 7 tests |
| 7 | Brand assets | `30dc005` | Icons (Android adaptive + legacy, iOS 1024), splash (Android all densities, iOS), offline page with bundled Inter + wordmark, store icon/feature image; `scripts/make-assets.py` se dobara ban sakte hain |
| 8 | Admin + event manager in app | `d6f9a85` | Staff sign-in links (app only), `/manager` + sign-in pages ab SW cache se bahar (security fix), organizer push tokens + report alert, manager Reports tab, Face ID / fingerprint lock, phone fit |
| 9 | Release plumbing + store text | is commit | Android signed AAB workflow, `codemagic.yaml` (iOS TestFlight), listing EN/HI, spec §24, reminder resync har 2 min |

**Aapke karne ka sab kuch ek jagah:** [../SETUP.md](../SETUP.md). **Admin / Event manager app mein:** [15-admin-and-manager-in-app.md](15-admin-and-manager-in-app.md) (plan).

## Abhi nahi (aage ke packages)
1. Organizer quick actions (O5) — chhota, maang par.
2. **Server-side per-session delay:** "running late" delay ke baad local reminder ka time purana rehta hai; bridge app khulne par resync karta hai, par live delay ke liye FCM ya schedule data se resync chahiye.
3. **Store screenshots + listing text** (icons/splash ban chuke).
4. **Signing + release workflow:** Play App Signing, Apple certificates, TestFlight — accounts ke baad.
5. **Real device testing:** is machine par JDK/Xcode nahi; Android/iOS build CI par.

## Local par chalane ke liye
- `cd mobile-app/app && npm ci && npx cap sync` phir Android Studio / Xcode mein kholo.
- Server `.env` mein: `MOBILE_ANDROID_SHA256`, `MOBILE_APPLE_TEAM_ID`, store URLs (optional).
- Deploy par `php artisan migrate --force` (do naye table: native_push_tokens, discovery_reports).
