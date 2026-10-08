# GA report se kya seekha, aur kya improve karna hai

**Data:** GA4 property 472202231, CampBuddy stream, **26 Sep – 5 Oct 2026** (WordCamp Rajasthan 3–4 Oct wala period).
Pura report: https://claude.ai/code/artifact/5483d5a0-58ad-4203-8c52-2708e5fb5e2d

> **Dhyan rahe:** sirf ~154 asli users aur zyaadatar ek hi event (Rajasthan) ka data hai. Ye numbers **direction** batate hain, final faisla nahi. Har improvement ko Delhi / Bengaluru ke baad dobara naapna hai.
>
> **Frozen-code rule:** neeche ka har point existing behaviour badalta hai. Implement karne se pahale har package par do confirmation popup chahiye. Ye document sirf plan hai, isme code nahi badla gaya.

---

## 1. Numbers ek nazar mein

| Kya | Number | Matlab |
|---|---|---|
| Asli users | 154 (GA raw 238) | 84 users bots + wpsimplified.in ke the |
| Mobile : Desktop | 119 : 36 | 77% phone par |
| iPhone : Android | 64 : 54 | **iPhone users zyada hain**, aur iPhone par push sabse mushkil hai |
| Return karne wale | 47 | event ke din ke baad lagbhag sab chale gaye (5 Oct ko sirf 3 users) |
| Sabse achha source | ID-card QR: 31 users / 57 sessions | print QR sabse kaam ka channel nikla |
| Install popup | 22 logon ko dikha → 7 ne "haan" | 32% install |
| iPhone reminder blocked | 15 baar "pahale install karo" | reminders iPhone par fail ho rahe hain |
| Discovery | 28 Join dabaya → 13 join → 2 wave → 0 message | aadhe log join mein hi ruk gaye |
| Free Steals | har item 27–45 baar dikha, click sirf 1 item par | dikhte hain, khulte nahi |
| Deals | 102 card views → 0 lead, 0 coupon copy | sponsors ko dikhane layak result abhi nahi |
| Plan reminder | 47 baar dikha → 3 tap | 6% — kaam nahi kar raha |
| Speed | LCP/CLS ~89% good, INP 73% good | theek, INP par kaam chahiye |

---

## 2. Improvements — priority ke hisaab se

**P0 = agle event se pahale. P1 = agle 1–2 mahine. P2 = jab time ho.**

### P0-1. iPhone par reminders — sabse badi kami
- **Data:** iPhone users 64 (sabse zyada). Reminder ke liye iPhone par 15 baar "pahale home screen par install karo" aaya, sirf 5 logon ne reminder on kiya.
- **Kyun:** iOS Safari mein Web Push sirf installed PWA mein chalta hai. Ye browser ki limit hai, code ki galti nahi.
- **Kya karein:**
  1. Abhi ke liye: iPhone install steps ko 3 screenshots wale chhote sheet mein dikhao (Share → Add to Home Screen → Open).
  2. Asli hal: **native app** (dekho [02-hybrid-app-plan.md](02-hybrid-app-plan.md)) — wahan local notifications bina server aur bina install-trick ke chalte hain.
- **Naapna:** `reminder on` / `reminder: iPhone pe install chahiye` ka ratio.

### P0-2. Event ke baad log gaayab — retention
- **Data:** 151 naye users, 47 hi lautaye. Event khatam hote hi traffic ~0.
- **Kya karein:**
  1. Thank-you card + `campbuddy:upcoming-push` (already stable par hain, deploy baaki) ko deploy karo — ye isi problem ka pahala jawab hai.
  2. Picker par "Aapke desh mein agla WordCamp" card sabse upar.
  3. Event ke baad "Session recordings aa gayi" push (jab WordPress.tv par aaye) — log wapas aane ka ek asli reason.
- **Naapna:** 7-day / 30-day returning users (GA Retention report), push open rate.

### P0-3. ID-card QR ko har event par le jao
- **Data:** print QR se 31 users, 57 sessions — direct link ke baad sabse bada source, aur organizer ke sath ka channel hai.
- **Kya karein:** har upcoming WordCamp organizer ko QR kit bhejo (badge, standee, slide). Har QR par apna `utm_source` (`badge`, `standee`, `slide`) taaki pata chale kaunsa kaam karta hai.
- **Naapna:** source = id-card / standee / slide wise users.

### P1-1. Discovery funnel — aadhe log join mein ruk gaye
- **Data:** 28 → 13 → 2 → 0. "Naam pahale se liya hua" 5 baar (2 log). "Milna hai" list mein 71 baar daala (7 log), lekin "Mil liya" sirf 5.
- **Kya karein:**
  1. Join ko 1 screen ka banao: naam roster se auto-suggest, interests baad mein (optional).
  2. "Naam pahale se liya" par seedha Device transfer ka raasta dikhao, error nahi.
  3. Wave ke baad ek ready icebreaker line ("Hi, main bhi AI sessions dekh raha hoon") — message 0 se upar laane ke liye.
  4. "Milna hai" list ko event ke din ek reminder banao ("aapki list mein 5 log yahin hain").
- **Naapna:** join start → join complete %, wave per joined user.

### P1-2. My Day — save to hua, plan reminder bekaar
- **Data:** 110 session opens, 63 saves (12 log). 63 mein se 14 saves time clash wale. Plan reminder 47 baar dikha, 3 tap.
- **Kya karein:**
  1. Clash ka toast pahale se aata hai ("This overlaps with … Both are saved.") — phir bhi 14 clash. Agla kadam: toast mein "Keep this one" button, taaki ek chuna ja sake.
  2. Plan reminder ka text aur jagah badlo, ya event ke din se pahale hi dikhao (raat 9–11 baje — data mein raat 10–12 baje bhi traffic tha).
  3. Top saved sessions (AI wale) ko "Popular" badge — naye logon ko chunne mein madad.

### P1-3. Free Steals aur Deals — dikhte hain, kaam nahi karte
- **Data:** Free Steals 60 baar khule (20 log), click sirf AcrossAI Pro par. Deals 102 views, lead 0, coupon copy 0.
- **Kya karein:**
  1. Card par ek saaf button ("Get it free →") — abhi row/sheet ke andar ka CTA kam dikh raha hai.
  2. GA mein `view_item_list` → `select_item` → outbound click ka funnel bana ke har item ka CTR dekho.
  3. Sponsors ko deal bechne se pahale 2–3 events ka data chahiye; abhi "0 leads" dikhaana nuksaan karega.

### P1-4. First-timer guide chhota karo
- **Data:** guide ke sections padhne wale 26 → 14 tak gire. Sabse zyada khule FAQ: "What is CampBuddy?", "Is it free?", "Sign up / app download chahiye?"
- **Kya karein:** ye 3 jawab landing / picker par hi ek line mein ("Free. No sign-up. No download.") — guide tak jaane ki zaroorat hi na pade. Guide ke beech wale sections fold karo.

### P1-5. Desktop users ko phone par bhejo
- **Data:** 36 desktop users, desktop notice 49 baar dikha.
- **Kya karein:** desktop notice mein QR: "Phone se scan karo, yahi event khul jayega". Native app aane ke baad wahi QR store link bhi de.

### P1-6. Quest aur Contribute — kam log
- **Data:** Quest page 27 log, quest pure sirf 7 logon ne. Contribute 29 log dekha, matches sirf 6 ne kholi.
- **Kya karein:** Home par "Aaj ke 3 quest" (sirf 3, poori list nahi). Contribute par interests se seedha top-2 teams dikhao.

### P2-1. Speed: INP aur JS errors
- INP 73% good (8 poor). Kaunse page/tap par slow hai, `web_vitals` event mein page ke hisaab se dekho.
- Errors: `InvalidStateError` (5 baar, 1 user) aur `TypeError` (4 baar, 2 users) — Admin → Errors page mein file:line dekh ke fix.

### P2-2. Google search aur LinkedIn
- Google se 17, LinkedIn se 10 users. Har event page par event-specific title/description (SEO) aur LinkedIn ke liye event-wise share image.

---

## 3. GA setup khud bhi theek karna hai (measurement)

| Kaam | Kyun |
|---|---|
| wpsimplified.in ke liye alag GA property | abhi har report mein mix hota hai; Admin → Analytics stream filter karta hai, par GA UI mein mix dikhta hai |
| Internal traffic filter (owner IP / cPanel referral) | apni testing asli users mein na gine |
| Key events mark karo: `session_save`, `discovery_join`, `camp_card_save`, `install_complete`, `push_enable` | GA mein conversion rate seedha dikhe |
| Har print/social link par UTM | channel-wise ROI |
| `campbuddy:ga-setup` deploy ke baad chalao (`rating` metric) | feedback rating GA mein aaye |
| Native app aane par `platform` dimension mein naye values: `app_ios`, `app_android` | 50/50 custom dimensions bhare hain — naya dimension nahi, purana reuse |

GA mein naam, message, ya kaun kisse mila — ye kabhi nahi bhejna (PII policy, spec 8.5). Sirf ginti.

---

## 4. Agle event ke baad yahi table bharo

| Metric | Rajasthan (baseline) | Agla event | Target |
|---|---|---|---|
| Install popup → install | 32% | | 45% |
| iPhone reminder on | 5 log | | 3× |
| Discovery join start → complete | 46% | | 70% |
| Wave per joined user | 0.15 | | 1 |
| Free Steals CTR | ~1 item | | 5+ items |
| Plan reminder tap | 6% | | 20% |
| 7-day returning users | — | | 25% |
