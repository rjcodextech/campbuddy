# 3.5 Schedule & notifications

[← Index](00-index.md) · Previous: [3.4 Interest-based matching](04-matching.md) · Next: [3.6 Quest →](06-quest.md)

| ID | Requirement |
|---|---|
| N1 | Bookmarking a session stores it locally. Notification permission is **never requested on page load or app open** — it's asked right after a bookmark, at the moment the benefit is obvious: *"Want CampBuddy to remind you before this session starts?"* If granted, a scheduled Web Push registers for 5–10 minutes before start, including track/hall. |
| N2 | Every device also gets an in-app "starting soon" banner/badge for bookmarked sessions regardless of push support — this is the guaranteed path, push is a bonus. |
| N3 | iOS: push requires iOS 16.4+ **and** the PWA installed to the home screen first; the app explicitly detects this and shows an iOS-specific instruction instead of a silently-failing permission prompt. |
| N4 | A denied permission is **respected permanently** — CampBuddy never re-prompts automatically. Re-enabling push is only ever a deliberate action the attendee takes from a settings surface, never a repeated browser prompt. |

> **Current implementation note (N3 now actually shows, and only once):** `push.js` checked "is Web Push supported?" *before* "is this iOS outside the installed app?" — but in an ordinary iOS Safari tab `Notification`/`PushManager` don't exist, so the answer was always "unsupported" and the iOS instruction never appeared. The iOS check now comes first, uses the shared `platform.js` (`isIos()` also recognises iPadOS, which sends a Mac user agent), and the instruction is shown **once per device** (`notificationState.iosInstallPromptShown`), not on every saved session (N1's "never nag").
>
> **Current implementation note (Oct 2026, iPhone hint in My schedule):** after that one dialog every later save on an iPhone browser tab stayed silent, so people took their reminders to be set (GA, WordCamp Rajasthan 2026: 13 of 15 `ios_needs_install` offers). `ios-reminder-hint.js` now keeps a quiet card above *Talks & sessions* in My Day → My schedule — only on iPhone/iPad, in a browser tab (not the installed app), with ≥1 saved session — saying reminders need the Home Screen and that until then the Home tab shows "starts in … min" (N2). **Show me how** opens the same install steps as the header's Install app button (`install.js` `openInstallSteps()`); **✕** hides it for good on that device (`localStorage` `campbuddy:ios-reminder-hint-dismissed`). It asks nothing and touches no permission. The N3 dialog gained the same "until then" line. GA: `reminder_ios_hint` with `result` = view / open / dismiss.
