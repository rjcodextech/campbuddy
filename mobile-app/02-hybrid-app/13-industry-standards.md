# Industry standards — app ke liye checklist

Ye sab plan ka hissa hain (implement ke waqt "done" ki shart). Jahan version/number hai, store submit ke din official docs se dobara check karo — Apple/Google har saal badalte hain.

## 1. Security (OWASP MASVS / MASTG)
| Standard | App mein kaise |
|---|---|
| TLS only | `cleartext` band, iOS ATS default, HSTS (Cloudflare pe pehle se) |
| WebView hardening | `allowNavigation` sirf `campbuddy.club`; baaki system browser; release build mein WebView debugging band; sirf zaroori Capacitor plugins expose |
| Local data | koi secret/token app mein nahi (no login). Discovery owner token OS storage mein (Keychain / Keystore via secure-storage plugin), localStorage mein nahi |
| Secrets | FCM service-account JSON sirf server `.env`; signing keys CI secrets mein, repo mein kabhi nahi |
| Certificate pinning | **nahi** — Cloudflare cert rotate karta hai, pinning se app toot sakti hai. Risk-based faisla, documented |
| Dependencies | `npm audit` + Dependabot, Capacitor plugin versions pinned, SBOM CI se |
| Input / XSS | wahi web ke controls (CSP, escaping); app naya attack surface nahi jodti |
| Pre-release | MASVS-L1 checklist self-review; ek baar external pentest ya MobSF scan |

## 2. Privacy aur compliance
- **India DPDP Act 2023 + GDPR (EU attendees):** no-login model, data minimization, 30-din retention, delete/leave-discovery aasaani se — spec 8 / 17 / 23 ke saath align. Privacy policy mein app aur push tokens ka zikr.
- **Apple:** `PrivacyInfo.xcprivacy` (privacy manifest + required-reason APIs), privacy nutrition label, ATT prompt **nahi** (cross-app tracking nahi). Third-party SDK (Firebase) ke manifests bhi.
- **Google Play:** Data safety form, Families policy lagu nahi (general audience).
- **Notifications:** opt-in pehle save ke baad, deny ka respect (N1/N4), har push ka unsubscribe/mute raasta.
- **UGC (Apple 1.2 / Play UGC policy):** report, block, abuse contact, admin moderation queue, response time likha hua.
- GA mein PII kabhi nahi (spec 8.5); app ke crash reports mein bhi nahi.

## 3. Accessibility (WCAG 2.2 AA + platform)
- Contrast 4.5:1, tap target ≥ 44 pt iOS / 48 dp Android (web ne pehle bade tap areas kiye hain).
- Screen reader: VoiceOver aur TalkBack se har main flow (picker → save → My Day → Camp Card).
- Dynamic Type / font scale 200% par layout na toote; reduced-motion respect (haal ke motion pass mein bhi).
- Focus order, labels, alt text; native dialogs/sheets ke labels.

## 4. Performance aur reliability
- Android vitals "bad behavior" se door raho: user-perceived crash ≈ 1.09% aur ANR ≈ 0.47% se niche (Play ise search mein penalize karta hai); apna target crash-free sessions ≥ 99.5%.
- Budget: LCP < 2.5 s, INP < 200 ms, cold start < 2 s (low-end Android 9–10 par naapo). Detail [12](12-one-codebase-offline-speed.md).
- Crash/ANR reporting: Firebase Crashlytics (ya Sentry), PII scrubbed; web ke `Errors` page se alag, dono dikhein.
- Graceful degrade: offline screen, retry with backoff, outbox sync.

## 5. Platform requirements (har submit se pehle verify)
- **Android:** AAB format, Play App Signing, target API Play ki current requirement (har August badhti hai), 64-bit, **16 KB page size** compatibility (naye targets), edge-to-edge insets, Android 13+ runtime `POST_NOTIFICATIONS`, exact alarms ka sahi permission (local reminders ke liye `SCHEDULE_EXACT_ALARM` ya inexact + clearly documented), Doze/OEM battery-saver (Xiaomi/OPPO/Vivo) par reminder test.
- **iOS:** current Xcode/iOS SDK jo App Store maangta hai, privacy manifest, Universal Links, iOS 64 pending local notifications ki limit (soonest 64 hi schedule hote hain — reminder scheduler ko ye handle karna hai), App Store screenshots sahi device sizes mein.
- Store listing: EN + HI, age rating questionnaire, support URL, privacy URL.

## 6. Engineering process
- **Versioning:** semver (`1.0.0`), build number CI se auto, changelog har release.
- **CI/CD:** lint → unit/JS tests → PHP tests → build Android AAB + iOS IPA → upload (Play internal track / TestFlight) — haath se nahi. Secrets CI vault mein.
- **Branching:** `main` protected, PR + review, release tags.
- **Testing pyramid:** unit (bridge logic) → integration (API native-subscribe, report/block) → E2E on real/cloud devices (Firebase Test Lab / BrowserStack) → manual airplane-mode + accessibility checklist.
- **Staged rollout:** Play 5% → 20% → 100%; iOS phased release; kill-switch = server feature flag + min-version gate ([12](12-one-codebase-offline-speed.md)).
- **Observability:** crash rate, push delivery %, GA `platform = app_*`, admin Notifications tab mein native counts.
- **Docs:** runbook (release, rollback, rotate FCM key), ADR (architecture decisions) — yahi `mobile-app/` folder.

## 7. Store-review readiness
- Review notes mein demo event + native features (4.2), report/block flow dikhao, no-login clear likho.
- Support email aur abuse contact working; privacy URL live.
- Event se **2 hafte pehle** submit (buffer reject-resubmit ke liye).

## 8. Jo jaanbujh kar nahi
Certificate pinning, root/jailbreak detection, obfuscation-heavy hardening — koi sensitive data/login nahi, aur ye false positives aur support bojh badhate hain. Agar kabhi login/payments aaye to dobara socho.
