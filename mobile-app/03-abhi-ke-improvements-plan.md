# Abhi karne wale improvements (P0) — implementation plan

[01-ga-report-improvements.md](01-ga-report-improvements.md) ke P0 points ka code-level plan. Code dekhne ke baad kuch points pahale se bane mile, unhe yahan se hata diya hai.

## Code check mein kya mila

| P0 point | Code mein abhi | Faisla |
|---|---|---|
| P0-1 iPhone reminders | `push.js`: iPhone Safari par pahali save par ek dialog aata hai (`tpl-reminder-ios-dialog`), uske baad **har save par chupchaap kuch nahi hota**. GA ke 15 `ios_needs_install` mein se 13 isi chupchaap raaste ke the. | **Package A — banana hai** |
| P0-2 "Agla WordCamp aapke desh mein" | Picker pahale se time zone se desh nikal ke usi desh ke WordCamp dikhata hai (`picker-filter.js`). Thank-you card aur `campbuddy:upcoming-push` `stable` par bane hue hain, sirf deploy baaki hain. | **Code nahi chahiye — deploy karna hai (owner)** |
| P0-3 ID-card QR har event par | GA mein source `id-card / print` organizer ke chhape QR se aaya tha. CampBuddy mein organizer ke liye tayyar QR dene ki koi jagah nahi hai. | **Package B — banana hai** |
| GA setup | Key events (`session_save`, `discovery_join`, `generate_lead`, `install_complete`, `camp_card_download`) `campbuddy:ga-setup` pahale se mark karta hai. | **Owner ke GA steps (neeche)** |
| P1-2 clash warning | Save karte waqt overlap ka toast pahale se aata hai ("This overlaps with …"). | 01 file mein theek kiya |

---

## Package A — iPhone par reminder ka raasta saaf

**Problem:** iPhone Safari mein Web Push nahi chalta jab tak CampBuddy Home Screen par na ho (browser ki limit). Pahali baar dialog aata hai, phir dobara kabhi nahi, isliye log samajhte hain ki reminder lag gaya.

**Kya badlega:**
1. **My Day → My schedule mein ek chhota notice card**, sirf tab jab: iPhone/iPad + Safari tab (installed nahi) + kam se kam 1 session saved + notice band nahi kiya.
   - Text: "Reminders on iPhone need CampBuddy on your Home Screen. Until then, the CampBuddy Home tab shows "starts in … min" for your saved sessions."
   - Button **"Show me how"** → wahi install steps wala dialog jo header ka "Install app" button kholta hai.
   - **✕** se band → is phone par dobara nahi.
2. Pahali save wale dialog (`tpl-reminder-ios-dialog`) mein ek line aur: "Until then, the CampBuddy Home tab shows "starts in … min" for your saved sessions." (wahi banner N2 jo pahale se hai.)
3. GA: `reminder_ios_hint` event, `result` = `view` / `open` / `dismiss` (existing `result` dimension reuse, naya dimension nahi).

**Nahi badlega:** `push.js` ka permission flow, Android/desktop, deny ka rule (N4), install button.
**Files:** `my-day.js` (notice dikhana), `install.js` (steps dialog export), `shared.blade.php` / `my-day.blade.php` (template), `_*.scss` (card style), JS test.
**Risk:** kam. Sirf iPhone Safari par ek naya card; baaki sab par code chalta hi nahi.

## Package B — Organizer QR kit (Admin + Event manager)

**Problem:** ID-card QR sabse achha channel tha, par organizer ko QR banana khud padta hai aur kaunsa QR kahan laga, pata nahi chalta.

**Kya banega (naya, additive):**
- Admin → Event → naya tab **"QR codes"**; Event manager panel mein bhi wahi page (sirf apne events).
- 4 QR, har ek ka apna link:

| QR | Link |
|---|---|
| Badge / ID card | `/event/{slug}?utm_source=id-card&utm_medium=print&utm_campaign={slug}` (GA mein jo pahale se aa raha hai, wahi naam) |
| Standee | `utm_source=standee&utm_medium=print` |
| Slide (screen) | `utm_source=slide&utm_medium=screen` |
| Social post | `utm_source=social&utm_medium=post` |

- Har QR: **PNG download** (print ke liye bada, 1200 px) + **SVG download** + link copy.
- Admin → Analytics ke "Where people came from" mein ye naam apne aap dikhenge (koi change nahi).

**Files:** naye — controller method(s), Blade view, admin JS mein QR (existing `qrcode-generator`), feature test. Existing — `event-nav` tab strip mein ek tab, manager event nav mein ek link.
**Risk:** kam. Naya page; attendee app, API, service worker ko nahi chhuta.

---

## Owner ke kaam (code nahi)

1. **Deploy:** `stable` ke pending commits (thank-you card, upcoming-push, Analytics page) — DEPLOYMENT.md ke "6 Oct 2026" aur upcoming-push blocks.
2. **Deploy ke baad:** `php artisan campbuddy:ga-setup` (rating metric + key events).
3. **GA → Admin → Data streams → CampBuddy → Configure tag settings → Define internal traffic:** apna IP; phir **Data filters → Internal traffic → Active**.
4. **wpsimplified.in** ke liye nayi GA property banao aur us site ka tag badlo (ya stream hata do).
5. Agle WordCamp organizers ko QR kit (Package B ke baad) bhejo.

## Order

A → test → commit → B → test → commit. Har package: `npm run test:js`, related PHPUnit files, `npm run build`. Branch `stable`, push/deploy nahi.
