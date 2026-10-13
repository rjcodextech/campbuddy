# Approach: kaunsa hybrid?

| Option | Faisla |
|---|---|
| **Capacitor shell + live website** | ✅ Recommended |
| Android TWA (PWABuilder) | ❌ iOS ka hal nahi, do raste |
| React Native / Flutter rewrite | ❌ Do codebase, mahino ka kaam, Blade reuse nahi |
| Ionic/Cordova | ❌ Capacitor hi naya version |

**Kyun Capacitor:** app Laravel + Blade + vanilla JS, pehle se mobile-first PWA. 90% code wahi, ek codebase dono stores ke liye, web deploy = app update.

## Content kahan se
**Phase 1: live site** — `https://campbuddy.club` WebView mein.
- Capacitor `server.url` ko "production ke liye nahi" kehta hai, isliye: bundled offline screen, iOS par `WKAppBoundDomains`, `allowNavigation` sirf `campbuddy.club`, baaki links system browser.
- **Phase 3:** bundled shell + API, agar Apple reject kare ya offline aur strong chahiye. Detail: [12-one-codebase-offline-speed.md](12-one-codebase-offline-speed.md).
