# Shuru karne se pehle ke faisle

1. **Compose Multiplatform UI (recommended) ya SwiftUI alag?** Sabhi screens ek hi code (Compose) = aapka "ek code" goal. SwiftUI alag = iOS ka alag UI code.
2. **Social media kit / Reels / Commands browser par?** (recommended) ya native mein dobara banana (+6–8 hafte).
3. **Languages:** abhi EN + HI? aur kaun si baad mein?
4. **Config per-event override** v1 mein chahiye ya baad mein?
5. **Mac:** cloud macOS CI kaafi ya ek Mac mini bhi lena hai (iOS debugging ke liye)?
6. **Capacitor app kab tak:** native v2.0 ke staged rollout tak, phir band.
7. **Analytics:** GA4 Measurement Protocol (server-side, jaise ab) ya Firebase Analytics SDK?
8. **Organizer 2FA** v1 mein ya baad mein?
9. **Pehla milestone kya:** M0 (API + config editor web par) se shuru — ye native app se pehle hi kaam ka hai aur kisi bhi technology mein chahiye.

## Agla kadam (aapki ha ke baad)
M0: `schema/app-config.schema.json`, `mobile_app_configs` migration, `GET /api/v1/app/config`, admin "Mobile app" editor (Navigation, Home, Strings, Look, Banner, Switches, Publish/Rollback), pehle 6 attendee read endpoints + contract tests. Purani app bhi turant `flags`, banner aur strings is config se padh sakti hai.
