# Package A — iPhone reminder (commit `e8f5d15`)

**Problem:** iPhone Safari mein Web Push tab tak nahi jab tak Home Screen par install na ho. Pehli baar dialog, phir chupchaap (15 mein se 13 isi raaste ke).

**Bana:**
1. My Day → My schedule mein notice card (iPhone/iPad Safari, ≥1 saved session, band nahi kiya). "Show me how" → install steps; ✕ → dobara nahi.
2. Pehli save dialog mein line: Home tab "starts in … min".
3. GA `reminder_ios_hint` (view / open / dismiss).

**Nahi badla:** `push.js` flow, Android/desktop, deny rule (N4), install button.
**Files:** `my-day.js`, `install.js`, `ios-reminder-hint.js`, templates, scss, JS test.
**App mein:** ye card chhupana hai.
