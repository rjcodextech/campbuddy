# App setup — ek saath poori configuration

Code (branch `app`) taiyaar hai. Neeche wo sab hai jo **aapko khud** karna hai (accounts, keys, files). Order mein karo; har step ke aage "kya milega" likha hai jo agle step mein lagta hai. Jab tak koi step nahi hota, uska feature chupchaap band rehta hai aur website pehle jaisa chalti hai.

## 0. Dependencies ek nazar mein

| # | Cheez | Kis feature ke liye | Kharcha | Bina iske kya hota hai |
|---|---|---|---|---|
| 1 | Apple Developer Program | iOS app, push, Universal Links | $99 / saal | iOS app store par nahi aa sakti |
| 2 | Google Play Console | Android app | $25 ek baar | Android store par nahi |
| 3 | Firebase project (free) | Remote push (FCM) dono platform | free | Sirf local session reminders; thank-you / Memories / next WordCamp push app tak nahi |
| 4 | `google-services.json` | Android push | free | Android par push nahi |
| 5 | `GoogleService-Info.plist` + Firebase iOS SDK (SPM) | iOS push | free | iOS par push nahi |
| 6 | APNs Auth Key (.p8) | iOS push (Firebase ke through) | Apple account mein free | iOS push nahi |
| 7 | Firebase service-account JSON | Server se push bhejna | free | Server `NativePush` kuch nahi bhejta |
| 8 | Android signing key + SHA-256 | Play upload + App Links | free | Link app mein nahi khulte, release nahi |
| 9 | Apple Team ID + signing certs | Universal Links + iOS release | account ke saath | Link app mein nahi khulte |
| 10 | Mac ya cloud build (Codemagic / GitHub macOS) | iOS build | Codemagic free tier | iOS build nahi |
| 11 | Server `.env` values (neeche) | sab | — | endpoints khali jawab dete hain |
| 12 | Store listing: screenshots, text, privacy answers | review | — | submit nahi |

Jo **nahi** chahiye: Google Maps key, payment SDK, login/SSO, analytics SDK (GA website wala hi chalta hai).

---

## 1. Accounts

1. **Apple Developer Program** → https://developer.apple.com/programs/ (individual ya organization). Approval 1–2 din.
2. **Google Play Console** → https://play.google.com/console ($25). Faisla: personal account par production se pehle **12 testers × 14 din closed testing** lagti hai; organization (D-U-N-S) par nahi.
3. **Firebase** → https://console.firebase.google.com → *Add project* → naam `CampBuddy` (Google Analytics for Firebase **off** rakho; GA website wala hi hai).

## 2. Firebase (push ke liye)

1. **Android app jodo:** Project settings → *Add app* → Android → package `club.campbuddy.app` → `google-services.json` download.
   - File rakho: `mobile-app/app/android/app/google-services.json` (git mein nahi jaati, `.gitignore` mein hai).
   - CI ke liye: `base64 -w0 google-services.json` ko GitHub secret `GOOGLE_SERVICES_JSON` mein daalo.
2. **iOS app jodo:** *Add app* → iOS → bundle id `club.campbuddy.app` → `GoogleService-Info.plist` download.
   - Xcode mein `mobile-app/app/ios/App/App/` mein drag karo, *Copy items* + target **App** tick.
   - Git mein nahi jaati; CI ke liye secret `GOOGLE_SERVICE_INFO_PLIST` (base64).
3. **APNs key (iOS push):** Apple Developer → Certificates, IDs & Profiles → **Keys** → `+` → *Apple Push Notifications service (APNs)* → download `.p8` (ek hi baar milti hai, sambhal ke rakho) + **Key ID** note karo.
   Firebase → Project settings → **Cloud Messaging** → *Apple app configuration* → APNs Authentication Key upload (.p8, Key ID, **Team ID**).
4. **Cloud Messaging API (V1)** enabled hona chahiye (Cloud Messaging tab mein "Enabled" dikhe; nahi to Google Cloud console → APIs → *Firebase Cloud Messaging API* → Enable).
5. **Server key:** Project settings → **Service accounts** → *Generate new private key* → JSON download.
   - Isko server par **web root ke bahar** rakho, jaise `/home/<user>/secrets/campbuddy-fcm.json`, permission `600`.
   - `.env`: `FCM_CREDENTIALS_PATH=/home/<user>/secrets/campbuddy-fcm.json` (project id file se aa jata hai; chaho to `FCM_PROJECT_ID=` bhi).
6. **iOS Firebase SDK (ek baar, Mac / Codemagic par):** Xcode → File → *Add Package Dependencies* → `https://github.com/firebase/firebase-ios-sdk` → products **FirebaseMessaging** (FirebaseCore saath aa jata hai) → target **App**. Commit karo (`project.pbxproj` + `Package.resolved`). `AppDelegate.swift` ka code pehle se `#if canImport(FirebaseMessaging)` ke andar hai, isliye package jodte hi chalu.

## 3. Android signing aur App Links

1. **Upload key banao** (apne laptop par, file kahin safe rakho, git mein kabhi nahi):
   ```
   keytool -genkeypair -v -keystore campbuddy-upload.jks -alias campbuddy -keyalg RSA -keysize 2048 -validity 10000
   ```
   Password dono jagah note karo. Backup lo (khoyi to update nahi de sakte; Play ke through reset possible hai par jhanjhat).
2. Play Console → *Create app* → naam `CampBuddy`, package `club.campbuddy.app` → **Play App Signing** on (default).
3. Fingerprints:
   - Upload key: `keytool -list -v -keystore campbuddy-upload.jks -alias campbuddy` → **SHA-256**.
   - Play ki asli signing key: Play Console → *Test and release → App integrity → App signing* → **SHA-256 certificate fingerprint**.
4. Server `.env`: `MOBILE_ANDROID_SHA256=AA:BB:...:upload,CC:DD:...:playsigning` (comma se dono).
5. Check: `https://campbuddy.club/.well-known/assetlinks.json` JSON dikhe (redirect nahi, Cloudflare block nahi). Google ka tool: https://developers.google.com/digital-asset-links/tools/generator
6. CI release ke liye GitHub secrets: `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`.

## 4. Apple: App ID, capabilities, Universal Links

1. Developer → Identifiers → **App IDs** → `+` → App → bundle id `club.campbuddy.app` → capabilities: **Push Notifications**, **Associated Domains**.
2. **Team ID** (Membership page, 10 akshar) → server `.env`: `MOBILE_APPLE_TEAM_ID=ABCDE12345`.
3. Check: `https://campbuddy.club/.well-known/apple-app-site-association` JSON dikhe (Content-Type `application/json`, redirect nahi).
4. `ios/App/App/App.entitlements` mein `aps-environment` = `production`, `applinks:campbuddy.club` pehle se hai. TestFlight/dev builds ke liye Xcode automatic signing sahi value laga deta hai.
5. **App Store Connect** → My Apps → `+` → New App → naam `CampBuddy`, bundle id upar wali, SKU `campbuddy-ios`.
6. Signing for CI: Codemagic mein *Apple Developer Portal integration* (App Store Connect API key: Users and Access → Keys → App Store Connect API) — Codemagic certificates/profiles khud banata hai.

## 5. Server `.env` (live site)

```
# Native app
MOBILE_APP_ID=club.campbuddy.app
MOBILE_ANDROID_SHA256=AA:BB:...,CC:DD:...
MOBILE_APPLE_TEAM_ID=ABCDE12345
MOBILE_STORE_URL_ANDROID=https://play.google.com/store/apps/details?id=club.campbuddy.app
MOBILE_STORE_URL_IOS=https://apps.apple.com/app/id<AppStoreID>
MOBILE_MIN_VERSION_ANDROID=1.0.0
MOBILE_MIN_VERSION_IOS=1.0.0
MOBILE_FEATURE_NATIVE_PUSH=true
MOBILE_FEATURE_LOCAL_REMINDERS=true

# FCM
FCM_CREDENTIALS_PATH=/home/<user>/secrets/campbuddy-fcm.json
```
Deploy ke baad:
1. Naye files upload (DEPLOYMENT.md jaisa) + `public/build/`.
2. `php artisan migrate --force` — teen naye table: `native_push_tokens`, `discovery_reports`, `organizer_push_tokens`.
3. `php artisan config:clear` (agar config cached ho).
4. Test: `curl https://campbuddy.club/api/v1/app-config` → JSON; dono `.well-known` URL.
5. Push test (ek event par, kam logon par): `php artisan campbuddy:upcoming-push --dry-run`.

**Kill switch:** `MOBILE_MIN_VERSION_*` badhao to purani app "Please update" screen dikhati hai; `MOBILE_FEATURE_NATIVE_PUSH=false` se native push band.

## 6. Build aur test

- Local (Android Studio hoga to): `cd mobile-app/app && npm ci && npx cap sync android && npx cap open android`.
- CI: GitHub → Actions → **App build** (debug APK artifact download karke phone mein install). iOS simulator build bhi wahi.
- Icons/splash dobara banane ho: `pip install pillow && python mobile-app/app/scripts/make-assets.py`.
- Version badhana ho: `mobile-app/app/package.json` + `capacitor.config.json` ka `appendUserAgent` + `android/app/build.gradle` (`versionCode`, `versionName`) + Xcode `MARKETING_VERSION`/`CURRENT_PROJECT_VERSION` — sab ek saath.

## 6b. Release builds

- **Android:** GitHub → Actions → **App release (Android)** → Run workflow → `version_code` (pichhle upload se bada number). Secrets chahiye: `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`, `GOOGLE_SERVICES_JSON`. Artifact `.aab` Play Console → Internal testing mein upload karo.
- **iOS:** `codemagic.yaml` (repo root). Codemagic mein App Store Connect integration `campbuddy_asc` aur env group `campbuddy_ios` (`GOOGLE_SERVICE_INFO_PLIST` base64) banao; workflow **iOS to TestFlight** manual start karo.
- Dono se pehle: `npm test` (shell config) chalta hai; version number sab jagah ek jaisa rakho (neeche 6).

## 6c. Organizer (admin / event manager) app mein

Code ready hai; aapko kuch alag configure nahi karna (wahi Firebase). Dhyan:
- **Face ID:** iOS permission text pehle se Info.plist mein hai.
- **Alerts:** organizer pehli baar sign-in karke "Get an alert…" popup par Allow kare; naye report par ek push aata hai (admin ko sab events, manager ko apne events ke).
- **Review demo account:** ek alag **event manager** account banao (sirf demo event assigned), credentials review notes mein do, review ke baad password badlo. Asli admin account kabhi nahi.
- Production `.env` mein `SESSION_SECURE_COOKIE=true` hona chahiye.

## 7. Store listing

| Chahiye | Kahan se |
|---|---|
| App icon 512 (Play) | `mobile-app/app/store/play-icon-512.png` (bana hua) |
| Feature graphic 1024×500 | `mobile-app/app/store/play-feature-1024x500.png` (bana hua, chaho to design badlo) |
| Screenshots | phone se: Android 2–8 (min 2), iPhone 6.9" aur 6.5" ke set — Home, Schedule, My Day, People, Camp Card |
| Description (EN + HI) | `mobile-app/app/store/listing-en.md` aur `listing-hi.md` (bana hua) |
| Privacy policy URL | `https://campbuddy.club/privacy` |
| Support email / abuse contact | owner decide kare (report/block ke liye zaroori) |
| Play **Data safety** | Collected: device/app IDs (push token), app interactions (analytics); sab "not linked to identity", encrypted in transit, delete request: in-app "Leave discovery / delete my data" |
| Apple **Privacy label** | Identifiers (device ID for push), Usage data (analytics) — "not linked to you", tracking: **No** |
| Age rating | 12+ (user-generated content: short messages, report/block hai) |
| **Review notes** | Neeche paragraph |

**Apple/Play review notes (copy karo):**
> CampBuddy is the companion app for WordCamp events. No login is needed: open the app, pick an event (e.g. "<demo event>"), save sessions to My Day (local reminders), and browse People. Attendee discovery has Report and Block on every profile; reports go to the organizers within a day. Organizer sign-in (Settings link at the bottom of the app) is for event staff only — demo account: <email> / <password>.

(Admin/manager demo account tab banana jab organizer entry release mein ho — [02-hybrid-app/15-admin-and-manager-in-app.md](02-hybrid-app/15-admin-and-manager-in-app.md).)

## 8. Release se pehle checklist

- [ ] `app-config`, dono `.well-known` URL sahi
- [ ] Android real phone par: save session → notification aati hai (phone lock, app band)
- [ ] iPhone par: wahi; Settings se notification band → dobara prompt nahi aata
- [ ] Airplane mode: pehle khola hua event dikhe, save session kaam kare, online aate hi sync
- [ ] QR / link `https://campbuddy.club/event/...` app install hone par app mein khule
- [ ] Camp Card share sheet aur PDF save
- [ ] Report → admin Reports tab mein dikhe → Remove/Dismiss
- [ ] Ek test push FCM console se (Firebase → Messaging → Send test message, token `native_push_tokens` table se)
- [ ] Purana version (min version badha ke) "Please update" dikhaye
- [ ] 2 hafte pehle submit (WordCamp se)
