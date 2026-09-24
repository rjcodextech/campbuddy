# 3.5 Schedule & notifications

[← Index](00-index.md) · Previous: [3.4 Interest-based matching](04-matching.md) · Next: [3.6 Quest →](06-quest.md)

| ID | Requirement |
|---|---|
| N1 | Bookmarking a session stores it locally. Notification permission is **never requested on page load or app open** — it's asked right after a bookmark, at the moment the benefit is obvious: *"Want CampBuddy to remind you before this session starts?"* If granted, a scheduled Web Push registers for 5–10 minutes before start, including track/hall. |
| N2 | Every device also gets an in-app "starting soon" banner/badge for bookmarked sessions regardless of push support — this is the guaranteed path, push is a bonus. |
| N3 | iOS: push requires iOS 16.4+ **and** the PWA installed to the home screen first; the app explicitly detects this and shows an iOS-specific instruction instead of a silently-failing permission prompt. |
| N4 | A denied permission is **respected permanently** — CampBuddy never re-prompts automatically. Re-enabling push is only ever a deliberate action the attendee takes from a settings surface, never a repeated browser prompt. |

> **Current implementation note (N3 now actually shows, and only once):** `push.js` checked "is Web Push supported?" *before* "is this iOS outside the installed app?" — but in an ordinary iOS Safari tab `Notification`/`PushManager` don't exist, so the answer was always "unsupported" and the iOS instruction never appeared. The iOS check now comes first, uses the shared `platform.js` (`isIos()` also recognises iPadOS, which sends a Mac user agent), and the instruction is shown **once per device** (`notificationState.iosInstallPromptShown`), not on every saved session (N1's "never nag").
