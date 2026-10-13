# Organizer (Admin / Event manager) — native screens

Login screen (email + password → Sanctum token, Keychain/Keystore), Face ID / fingerprint lock, token expiry par dobara login. Bottom tabs config se (`organizer.tabs`).

| Admin / Manager page | Native? | Note |
|---|---|---|
| Dashboard (countdown, drafts, 3 numbers) | ✅ v1 | admin |
| Events list + details + edit | ✅ v1 | status approve admin; manager sirf details/information |
| Reports (remove / dismiss) | ✅ v1 | push alert pehle se |
| Notifications (push log) | ✅ v1 | read-only |
| Memories (links + notify) | ✅ v1 | |
| Running late (delay) | ✅ v1 | event-day ke liye sabse kaam ka |
| Quests & checklist | ✅ v1 | CRUD |
| Contributor Day tables | ✅ v1 | CRUD |
| Roster / attendees (roles, suppress) | ✅ v1.1 | bade lists, search |
| Deals, Free steals, deal leads (+ CSV share) | ✅ v1.1 | |
| QR codes kit | ✅ v1 | QR native banta hai, PNG/SVG share |
| Analytics (GA cards), Errors, Feedback | ✅ v1.1 | read-only charts (native chart lib) |
| Mobile app config editor | ✅ v1.1 (basic) | full editor web par; app mein banner, switches, strings search |
| Commands (artisan runner) | ❌ browser | risky, admin web par |
| Social media kit, Reels, Usage post | ❌ browser | HTML canvas + MP4 encoding web-specific; native mein dobara banana bahut bhari. Link "Open in browser" (Custom Tab / SFSafariViewController) |
| Media library, Default deals, Event managers (create/audit) | ✅ v1.2 | |

## Principle
Roz ke aur event-day ke kaam native (delay, reports, notifications, quests, leads, QR); bhaari design/analysis wale browser par. Ye line aapse alag chahiye to batao — "sab native" ka matlab Social/Reels ko Kotlin mein dobara likhna (alag 6–8 hafte).

## Security
- Admin token short expiry (e.g. 12 h) + refresh; manager 7 din; "sign out everywhere" admin se.
- Har request server par authorize (policy) — app ka hide kiya hua button security nahi.
- Screenshot / recents blur, jailbreak/root detection **nahi** (false positives), certificate pinning **nahi** (Cloudflare rotation) — pehle jaisa faisla.
- Review ke liye demo manager account.
