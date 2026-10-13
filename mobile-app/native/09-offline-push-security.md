# Offline, push, security

## Offline
- **Pehli baar** online chahiye (config + event bundle); uske baad poora attendee flow offline: schedule, My Day, People (cached roster), Camp Card, Guide, Quest, Memories.
- Event follow karte hi `bundle` download (`since` se sirf badla). Background refresh: Android WorkManager, iOS BGTaskScheduler + app khulte hi + `data-version` polling (event ke din 30–60 s).
- Writes outbox mein (retry/backoff, idempotent keys). Conflict rule: server last-write-wins, discovery waves server-authoritative.
- Storage limit: ek event ~ few MB; purane events 30 din baad (retention parity) auto-clean; discovery data server retention ke saath.
- Web ki "nothing disappears while the event is on" niyam (spec §23) native mein bhi.

## Reminders
Local, 10 min pehle; "running late" delay aane par WorkManager/BG refresh + FCM data-push se resync. iOS 64-limit: sabse nazdeeki 60. Permission pehle save ke baad, deny hamesha maano (N1/N4).

## Push (FCM)
Server `NativePush` ab bhi wahi; token `native_push_tokens`. Native app mein **topics nahi**, token event-wise. Data-only push se silent resync (delay, schedule change). Organizer: `organizer_push_tokens`.

## Security / privacy
- TLS only; Android `usesCleartextTraffic=false`, iOS ATS.
- Tokens Keystore/Keychain; discovery owner token bhi wahi (web mein localStorage tha — native mein behtar).
- Koi secret app mein nahi; Firebase config file public-safe, server key server par.
- Privacy: wahi labels (analytics aggregate, push token), Apple privacy manifest, Play data safety. DPDP/GDPR: "Leave discovery / delete my data" Settings mein.
- Backup: Android `allowBackup=false` (token phone par hi), iOS backup se Keychain item exclude.
- Dependencies: Gradle dependency locking + Dependabot, `./gradlew dependencyCheck`.
- Config injection: https-only actions, allowlisted domains, no HTML/JS rendering (plain text + markdown subset).
