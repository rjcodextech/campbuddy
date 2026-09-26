# CampBuddy — Poore project ki TODO list

*26 Sep 2026.* Kya-kya bana hai, abhi output kaisa hai, kya karna baaki hai, aur kya plan me hai hi nahi. Live site: https://campbuddy.club. Pehla asli event: **Sylhet 1 Oct**, **Rajasthan 3–4 Oct 2026**. Deploy aur Cloudflare ki poori list alag file `DEPLOYMENT.md` me hai.

**Kaise padhein:** "Ho gaya" = bana hai aur jaancha gaya. "Asli test baaki" = bana hai, par asli phone/asli bheed par abhi dekha nahi. "Nahi hai" = bana hi nahi. "Meri nazar se" = spec me nahi likha, mujhe ye kami dikhi.

## 1. Ek nazar me

| Cheez | Haal |
| --- | --- |
| Attendee app (sabhi feature) | Ho gaya, live |
| Admin panel | Ho gaya, live |
| Offline, push reminders, caching, retention (Sept 2026 ka kaam) | Ho gaya, live (`dev` ke 5 code commits FileZilla se deploy hue) |
| Load test (host + Cloudflare) | 500 users tak PASS, p95 48 ms |
| Phone par asli test (reminder, offline, install) | **Baaki** |
| Event ke dauran caching me badlaav | **Roka hua** (owner ka faisla: pehle event dekho, phir badlo) |
| Backup, alert, staging, EU privacy | **Plan me nahi** (section 5) |

## 2. Kya-kya features hain

### Attendee app (phone par, bina login)

| Feature | Kya karta hai | Haal |
| --- | --- | --- |
| WordCamp chunna (`/`) | Kai events ki list, "Tell us about yourself" (ek baar), Install app, first-timer guide ka link | Ho gaya |
| Home | Event ka hero (logo, tareekh, "starts in 7 days"), "New to WordCamp? Start here", Happening now, Up next, Suggested action, progress | Ho gaya |
| My Day | Poora schedule aur My Schedule (har din alag), session detail, save/bookmark, overlap warning, calendar file (.ics), offline | Ho gaya |
| Session reminder | Session se 5–10 min pehle push notification, app ke andar "starting soon" banner | Ho gaya, **asli test baaki** |
| Quest | 8 "Things to do" quests, 9-item pre-trip checklist, admin ke apne quests, progress phone me | Ho gaya |
| Contribute | Contributor Day samjhana, sawalon se team ki salah | Ho gaya |
| Explore → People | Event ki attendee list (search, photos), "Join attendee discovery" (interest match, naam kaise dikhe ye apna faisla), waves/messages, kabhi bhi leave. Lambi match list pehle 3 cards dikhati hai, baaki "Show N more" par (button par jo number, utne hi card khulte hain, koi card dohrata nahi) (27 Sep) | Ho gaya, **bheed par asli test baaki** |
| Explore → Sponsors, Deals, Event Info | Sponsors, discount deals (kuch me naam/email form), venue/links/emergency contact (apne aap bhare jate hain, guess nahi) | Ho gaya |
| Camp Card | Kai design, apne chune hue fields, LinkedIn/website QR, fullscreen, save/print | Ho gaya |
| Guide | General first-timer guide aur event ka guide | Ho gaya |
| Data controls | Apna data export ya clear | Ho gaya |
| "Mujhe list se hatao" | Public takedown page (CSRF ke saath) | Ho gaya |
| PWA | Install (Android/iPhone ke alag tareeke), offline (pages, attendee list, photos phone me), push, "refresh" button, data-version polling | Ho gaya |
| SEO aur analytics | sitemap, robots, llms.txt, IndexNow; Google Analytics 4 (privacy guardrail ke saath), live par tag chalu (`G-1YHQ19XV0P`) | Ho gaya |

### Admin panel (`/admin`)

| Feature | Haal |
| --- | --- |
| Dashboard + health (scheduler, queue, migrations, failed jobs) | Ho gaya |
| Events: khoj, publish/archive, data refresh, branding (logo/favicon) fetch aur upload, event info edit | Ho gaya |
| Quests, Offers (Deals), Deal leads (dekhna + CSV export), Media library | Ho gaya |
| Attendee list moderation (hide/unhide, claim chhodna) | Ho gaya |
| "Purge cache & refresh data" (server + Cloudflare, `.env` me token ho to) | Ho gaya, **Cloudflare token wala hissa jaancha nahi** |

### Peeche ka kaam (apne aap chalta hai, cron har minute)

| Kaam | Kab |
| --- | --- |
| Schedule, speakers, sponsors fetch | har 15 min |
| Reminders bhejna | har minute |
| Attendee list refresh | roz 00:00 (UTC) |
| Event info, branding backfill, lifecycle (publish/archive), IndexNow | roz |
| Nayi WordCamps ki khoj (draft banti hain, admin approve karta hai) | har 2 din |

### Sept 2026 me jo joda gaya (sab live hai)

Retention (event ka asli aakhri din + 3 din tak kuch delete nahi), shared lists ek baar banke sabko (ETag/304), shaant polling, reminders chunk me tez, poora event phone me offline save, offline reminder queue, Gravatar photos offline, LiteSpeed `.htaccess`, Cloudflare Cache Rule (event pages 60 sec), load-test script `tools/loadtest.mjs`.

## 3. Abhi output kaisa hai (26 Sep ko jaancha)

| Cheez | Nateeja |
| --- | --- |
| Live pages (asli Chrome, 390 px phone size): picker, event home, My Day, Explore, Camp Card, Quest | Sab 200, styling theek, koi horizontal scroll nahi, koi JavaScript error nahi, Service Worker chalu |
| Page ka wazan (compressed) | HTML 11–30 KB, CSS 14 KB, pehli JS 8 KB |
| Explore → People (286 attendees) | Pehli baar ~4–5 s me list aati hai ("Loading…" tab tak). Chalta hai, par sudharne layak. |
| Load test 150 / 200 / 300 / 500 users | Pehle (cache se pehle): 150 PASS, 200 PASS, 300 FAIL. Ab: 300 aur 500 PASS, p95 55 / 48 ms, 0 errors, 99% Cloudflare HIT |
| Host ki apni seema | ~20 uncached page/s. Cache ke bharose 10,000 log **sambhav lagta hai, par sabit nahi** |
| Tests | JS 124/124, asli-Chrome offline 7/7, PHP 28/29 files (bacha hua `AnalyticsRegistryTest` sirf aapke Windows par fail) |
| Security jaanch | `composer audit`: saaf. `npm audit`: 0 kamzori. `.env`, `.git`, logs, `artisan` bahar se band (403/404). LICENSE (GPL-2.0) maujood |
| Health | `/api/v1/health` sab true, cron har minute chal raha hai, `hot` file hat chuki |

## 4. Karna baaki hai

### A. Event se pehle (must)

- [ ] **Reminder ka asli test:** ek session star karo jo 15 min me shuru ho, reminder "Yes"; 5–10 min pehle notification aana chahiye. (Android aur iPhone dono, iPhone par app Home Screen se install ho.)
- [ ] **Phone par offline test:** ek event page kholo, 1 minute ruko, airplane mode: Home, My Day, Quest, Contribute, Explore, Camp Card khulein; Explore me list aur photos dikhein.
- [ ] **App install** Android aur iPhone dono par, aur install kiye hue app me upar wale 2 test.
- [ ] **Admin:** `/admin` login (`SESSION_SECURE_COOKIE` ke baad), dashboard me koi laal error nahi, "Purge cache & refresh data" dabakar dekho ki Cloudflare wala hissa chala.
- [ ] **Server `.env` ki bachi cheezein** (`DEPLOYMENT.md`, "Server ka .env"): `LOG_LEVEL=warning`, `CLOUDFLARE_ZONE_ID` + `CLOUDFLARE_API_TOKEN`, `SESSION_SECURE_COOKIE=true`, GA credentials file hatana. Phir `optimize:clear && optimize`.
- [ ] **Har live event ka data ek nazar me** (Sylhet aur Rajasthan): sessions, speakers, sponsors, attendee list, logo, tareekh, venue. Kal ya parso, event se ek din pehle nahi.
- [ ] **Database ka ek backup** (phpMyAdmin → Export) event se pehle.
- [ ] Server se `.htaccess.bak-0923` hatana.
- [ ] `dev` ko `main` me merge karna (aap tay karein kab).
- [ ] **Upload band** (freeze): sirf emergency me. Upload karna ho to `DEPLOYMENT.md` ki FileZilla list dekhein (`hot`, `.env`, `storage/` kabhi nahi).
- [ ] Event-day ka chhota runbook (kaun kya dekhega, kisse kya poochhna hai, rollback ka ek line): `DEPLOYMENT.md` ke "Kuch bigde to wapas kaise jayein" me hai.

### B. Event ke dauran (sirf dekhna)

- [ ] Cloudflare → Analytics → Caching: cache hit ratio.
- [ ] cPanel → Resource Usage: CPU aur Entry Processes.
- [ ] `https://campbuddy.club/api/v1/health` aur admin dashboard.
- [ ] `storage/logs/laravel.log` me naye errors.
- [ ] Reminder aaye ya nahi (2–3 asli logon se poochh lein).
- [ ] Kuch bigde to: Cloudflare Cache Rule `CampBuddy event pages 60s` **Disable** + Purge Everything.

### C. Event ke baad (faisle, abhi roke hue)

- [ ] Caching: Smart Tiered Cache, `/` aur `/guide` ko rule me jodna, Speed Brain aur Always Online rakhna ya nahi. (Data dekhkar.)
- [ ] SWR switch (`public/sw-flags.json`): `true` karna, dheere-dheere, load test ke saath. Sabse zyada host ka load ghatata hai.
- [ ] Load test dobara, aur 500 se upar (apni ijazat ke saath).
- [ ] Behtar server kab aur kaun sa (event ke asli numbers dekhkar).
- [ ] Android background sync (agar late reminders ki shikayat aaye). iPhone par ye possible hi nahi.
- [ ] Explore → People ki pehli load ~4–5 s ko kam karna.
- [ ] **Discard (✕) aur "nahi mil paye" ki list** (poora plan: `PLAN-discard-and-missed.md`; owner ka idea, 27 Sep; pehle asli feedback): card ke corner me chhota ✕ (swipe baad me, shortcut ki tarah) jo us insaan ko discovery se hataye aur schedule se bhi (delete nahi, `status: skipped`, wapas laa sakein). My Day me "✗ Couldn't" pehle se hai, par alag grouped list aur karan nahi. **Mila hua bug/kami:** Explore ka "I met them" (`metHistory`) aur My Day ka Met / Couldn't (`meetings.status`) do alag jagah alag save hote hain, ek doosre ko nahi jaante. Ek hi status (to meet / met / couldn't / skipped) dono jagah dikhna chahiye.
- [ ] **Sirf apni profile chunna** (owner ka sawal, 27 Sep, abhi sirf charcha): L0 badge ka shabd ("✓ On attendee list" verified jaisa lagta hai) aur "Is this you?" confirm step; L1 "ye mera naam hai" dispute + admin queue; L2 Turnstile/claim limit; L3 email OTP roster ke gravatar hash se ("✓ Verified"). Faisla asli event ke baad.
- [ ] Match card ki safai (event ke baad, asli data dekhkar): Wave ko ek bada button rakhna aur "+ Meet" / "I met them" ko "⋯" menu me daalna; **"Not for me"** (list se hatana, sirf phone me save); "Show all" ka full-screen panel search ke saath; roster (`r:`) aur discovery (`d:`) ki key ek karna taaki ek insaan My schedule me do baar na aaye; asli block (server ka kaam).
- [ ] Event ke baad ka data: retention khatam hone ke baad kya hoga (section 5, #9).

## 5. Plan me nahi hai (meri nazar se kamiyan)

Ye spec ya `DEPLOYMENT.md` me nahi thi. Order: pehle sabse zyada jokhim wali.

| # | Kami | Kyon zaroori | Kya karna hoga |
| --- | --- | --- | --- |
| 1 | **Automatic database backup** nahi hai (sirf haath se export ki salah) | Bookmarks, discovery, deal leads ek galti se ja sakte hain | cPanel Backup/cron `mysqldump` roz, aur ek baar restore karke dekhna |
| 2 | **Site/cron band hone par alert nahi** | Dashboard tabhi dikhta hai jab koi dekhe; reminders chupke se band ho sakte hain | Free uptime monitor `/api/v1/health` par, email/phone alert |
| 3 | **Write wale kaam ka load test nahi hua** (discovery join, waves/messages, bookmarks, push subscribe). Meri script sirf padhti hai. | Event shuru hote hi hazaaron log ek saath join/save karenge; ye cache nahi hote, ~20/s ki seema yahi lagegi | Alag staging copy par test, ya event ke pehle ghante me dhyan se dekhna |
| 4 | **Asli push ka bade paimane par test nahi** (sirf nakli push se jaancha) | 10,000 reminders FCM/Apple/Mozilla par asli me kaise jayenge, ye kisi ne dekha nahi | Kuch asli phone se, phir bheed me nigrani |
| 5 | **EU ke events ke liye privacy/consent** (Netherlands, Sofia): Google Analytics bina consent banner ke chalu hai | EU me isse dikkat ho sakti hai. Ye kanooni faisla hai, mera nahi | Tay karein: EU events par GA band, ya consent banner, ya sirf Cloudflare analytics |
| 6 | **Admin ka password reset email kaam nahi karta** (`MAIL_MAILER=log`, mail sirf log me jaati hai); doosra admin ya 2FA bhi nahi | Admin password bhool gaya to seedha database se hi theek hoga | SMTP lagana, ya CLI se reset ka likha hua tareeka, aur ek doosra admin |
| 7 | **Staging (test) site nahi hai**; FileZilla se haath se upload | 26 Sep ko `hot` file se UI toota. Test aur deploy dono live par | Alag subdomain + DB; upload ke liye saaf-folder script; `campbuddy:doctor` me `public/hot` ki chetavni (ye existing code badlega, isliye do baar poochhunga) |
| 8 | **Error tracking nahi** (sirf log file) | Kisi phone par JS error aaye to kisi ko pata nahi | Sentry jaisa free tool, ya log dekhne ki aadat |
| 9 | **Event ke baad data ki policy nahi** | Retention ke baad bookmarks, discovery profiles, push subscriptions, deal leads ka kya? Kaun delete karega? Leads sponsors ko kaise jayengi? | Likh kar tay karna (privacy statement ke saath milakar) |
| 10 | **Asli devices ki list nahi**: kam-kimat Android, iPhone Safari/Home Screen, slow 3G, WhatsApp/LinkedIn ka in-app browser | Offline, install aur push sabse zyada yahi bigadte hain | 5–6 phone par ek baar poora journey |
| 11 | **Accessibility ki jaanch ka record nahi** (screen reader, keyboard, contrast) | Spec kehta hai "implement karna, taalna nahi", par report nahi mili | Chhoti jaanch aur record |
| 12 | **Spec ke pre-launch blockers ka record nahi:** manual OWASP pass aur 17-step acceptance journey (sirf offline wala automatic hai) | Spec inhe "launch se pehle band" kehta hai. `composer audit` aur LICENSE ho chuke | Ek ghante ka checklist, pass/fail likhna (`spec/13`, `spec/15`) |
| 13 | **Cloudflare/origin ki suraksha:** HSTS, origin ko sirf Cloudflare ke IP tak band karna, CSP | `trustProxies '*'` hai, seedha origin par jaakar IP nakli ho sakta hai | Cloudflare me HSTS ON; host se firewall (spec/16 me likha hai) |
| 14 | **Rollback ka abhyas kabhi nahi hua** | Likha hai, par kabhi chalakar dekha nahi | Staging par ek baar `sw.js` kill-worker aur `git revert` |
| 15 | **Attendees ke liye madad ka raasta nahi** (help desk/WhatsApp) aur "kya kare agar..." ki list | Event me sabse pehle log yahi poochhte hain | Ek contact aur 10 line ka runbook |
| 16 | **Organizers ke liye report nahi** (kitne log, kaunsa feature, kitne install) | Analytics chalu hai par report/dashboard bana nahi | GA4 me ek saaf report, event ke baad ek page ka saar |
| 17 | **Tests apne aap nahi chalte** (CI/GitHub Actions nahi), sirf aapke computer par | Koi badlaav tootne par turant pata nahi chalta | Chhota GitHub Actions workflow |
| 18 | **Version tag, changelog, `main` ka process** | Kis deploy me kya gaya, ye git log se hi | `main` merge ke saath tag |
| 19 | Claude Docs wala "Deployment" document purana hai (`.env`, Cache Rule, FileZilla wale hisse sirf `DEPLOYMENT.md` me) | Do jagah alag baatein | Ek hi jagah rakhna |
| 20 | Sirf English (Hindi/anya bhasha nahi) | Bharat ke events me kuch log Hindi chahenge | Baad ka faisla |

## 6. Kya jaan-boojhkar nahi hoga (spec ka hissa)

Login/account, "scan a card", ticketing ka koi data, dark mode, WordCamp ke alawa doosre events. Iski wajah `spec/02-scope.md` me hai.
