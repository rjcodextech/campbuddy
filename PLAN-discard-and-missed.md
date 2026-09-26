# Plan: ✕ Discard, "nahi mil paye" list, schedule ka status filter, aur har jagah sync

*27 Sep 2026 (v2, aapki teen shartein jodkar).* Sirf plan hai, kuch implement nahi hua. Kuch bhi badalne se pehle aapse do baar popup se poochha jayega (freeze niyam), har phase ke liye alag.

## Aapki teen shartein (ye plan inhi par tika hai)

1. **Koi data delete nahi.** Jo bhi user ne likha ya chuna (note, time, status), wo hamare code se kabhi nahi hatega. Hatana sirf ek jagah: user ka apna "Clear my CampBuddy data" button.
2. **Schedule ko status ke hisaab se filter kar sakein.** Proper status ke saath: kya baaki hai, kya ho gaya, kya nahi ho paya, kya hataya.
3. **"I met them" aur uske saare buttons har jagah sync me.** Ek jagah dabao, doosri jagah wahi dikhe.

## 1. Ab kya hai (code padhkar)

| Cheez | Haal |
| --- | --- |
| Insaan ke buttons | Explore me: roster row par **+ Meet**, match card par **Wave**, **+ Meet**, **I met them**. My Day me: **✓ Met**, **✗ Couldn't**, **Edit · Calendar**. |
| Kahan save hota hai | "+ Meet", "✓ Met", "✗ Couldn't" → `meetings` (status `null/met/missed`). **"I met them" → `metHistory`, alag.** |
| Insaan ki pehchan | Roster se `r:<id>`, match card se `d:<id>`. **Ek hi insaan do alag record.** |
| Session ka status | `attended` / `missed` (`bookmarks.status`). Time nikal jaye to plan use "done" ginta hai chahe aapne kuch mark na kiya ho. |
| Filter | Sirf ek chip **"Hide done"** (sessions aur people dono ke liye). Status ke hisaab se koi filter nahi. |
| Jo data **delete** karta hai (aaj) | Meet sheet ka **Remove** (`removeMeeting`), session ko un-save karna (`removeBookmark` + server `DELETE /bookmarks`), Data controls ka Clear. |
| Same page par sync | Explore par roster row aur match card alag DOM hain, ek doosre ko nahi jaante. |
| Data controls | Saare IndexedDB stores apne aap export aur clear. Naya kuch nahi karna. |
| Template | Missing slot par error nahi aata, khali reh jata hai. |

## 2. Ek hi sach ka source: `meetings.status`

Har insaan ke liye ek record. Chaar haal:

| Haal | `status` | Matlab |
| --- | --- | --- |
| Milna hai | `null` | Planned. Explore me "✓ To meet", My Day "To do" |
| Mil liye | `met` | Explore "✓ Met", My Day "Done" |
| Nahi mil paye | `missed` + `reason` (optional) | My Day "Couldn't meet" |
| Hataya (✕) | `skipped` (**naya**) | Explore ke "Hidden (N)" me, My Day ke "Hidden" filter me |

- Koi naya store nahi, koi IndexedDB version bump nahi. Purane records sahi rehte hain.
- `metHistory` band nahi karte: "I met them" dono jagah likhta hai, aur padhte waqt "met" = `metHistory` **ya** `status == 'met'`. Purana data aur rollback dono safe.
- `reason` sirf fixed list: `no-time`, `not-found`, `not-there`, `changed-mind`.

## 3. Shart 1: koi data delete nahi

- **✕ delete nahi karta.** Sirf `status = 'skipped'`. Note aur time bache rehte hain. "Show again" par status wapas `null` ya jo pehle tha.
- **Meet sheet ka "Remove" badalta hai** "Hide from plan" me (wahi `skipped`, wapas laa sakte hain). Ye ek existing button ka behaviour badalna hai, isliye alag se popup se poochhenge.
- Har status badalna **wapas** ho sakta hai (Met ↔ To do, Couldn't → "Try again").
- Dono records (purana `d:` aur naya) me se koi delete nahi hota; padhte waqt mila lete hain (naya wala jeetega).
- Sessions ko un-save karna abhi delete hai. **Aap batao**: isko bhi reversible karna hai (hide) ya waisa hi rakhein? Default: waisa hi, kyunki wo server ka bhi record hai.
- Data controls ka "Clear my data" hi ekmatra delete raasta rahega.

## 4. Shart 2: schedule ka status filter

My Day → My schedule ke upar chips (sessions **aur** people dono par lagte hain):

| Chip | Kya dikhata hai |
| --- | --- |
| **All (N)** | Sab jo hataya nahi |
| **To do (N)** | Baaki: koi status nahi aur time nahi nikla |
| **Done (N)** | Attended (session) ya Met (insaan) |
| **Couldn't (N)** | Missed (session) ya Couldn't meet (insaan) |
| **Hidden (N)** | Sirf hataye hue (✕), "Show again" ke saath |

- Gine hue number bilkul card ki ginti ke barabar (aapki pehli wali shart).
- Purana **"Hide done"** chip waisa hi kaam karta rahega (ya "To do" chip use le lega; ye aap chuno).
- **Ek baarik baat:** abhi jis session ka time nikal gaya par aapne "attended/missed" mark nahi kiya, use plan "done" ginta hai. Proper status ke liye ye alag dikhna chahiye: **"Time over, not marked"**, taaki aap use Attended ya Missed kar sakein. Aap batao: alag chip chahiye ya "To do" me hi rahe?
- "Couldn't" ke saath **karan** (optional chips: "Ran out of time", "Couldn't find them", "They weren't there", "Changed my mind") aur insaan par **"Stay in touch"** (unke links) aur **"Try again"**.

## 5. Shart 3: sync ka nakshha (kis jagah kya, aaj aur baad me)

| Jagah | Button / label | Aaj | Baad me |
| --- | --- | --- | --- |
| Explore → Who's attending (roster row) | + Meet / ✓ To meet | `meetings` (`r:<id>`) | Wahi record, canonical pehchan se |
| Explore → match card | + Meet / ✓ To meet | `meetings` (`d:<id>`, **roster wale se alag**) | Wahi record (roster wale insaan ke liye) |
| Explore → match card | I met them / ✓ Met | Sirf `metHistory` | `meetings.status='met'` **aur** `metHistory` |
| Explore → "People you've met" | Section | Sirf `metHistory` | `metHistory` ∪ `status=='met'` |
| Explore → naya "Couldn't meet (N)" | Section | Nahi hai | `status=='missed'` (Try again ke saath) |
| Explore → naya "Hidden (N)" | Section | Nahi hai | `status=='skipped'` (Show again) |
| My Day → People | ✓ Met / ✗ Couldn't | `meetings` | Wahi, aur Explore turant dikhata hai |
| My Day → Meet sheet | Save / Remove | `meetings` / delete | Save / **Hide** |
| My Day → progress ("N of M done") | | `computePlan` | `skipped` bahar |
| "Kitna baaki" reminder banner | | `computePlan` | wahi |
| Calendar export | | sab `meetings` | `skipped` bahar |
| Home → "N matches want to meet you" | | server ki waves | hataye hue bahar |

**Kaise sync hoga (3 cheezein):**
1. **Ek store, ek pehchan.** Sab screens `meetings` se padhti hain. Insaan ki pehchan: `r:<rosterId>` jab roster wala pata ho, warna `d:<discoveryId>`.
2. **Same page par turant.** Explore par ek button dabate hi (jaise roster row par "+ Meet") usi insaan ka match card bhi badle. Ek chhoti in-page ghoshna (`campbuddy:people-changed`) jise saare buttons sunte hain.
3. **Alag page par.** My Day kholte hi taaza `meetings` padhta hai (jaise abhi Explore karta hai). Do tab ek saath khule ho to `BroadcastChannel` se (optional, baad me).

**Ek zaroori server badlav (chhota):** roster wala insaan aur uska discovery card ko jodne ke liye card ko roster ka `id` batana padega. Abhi discovery ka public card roster id nahi deta, isliye "Rahul Sharma" naam ke do log hon to sirf naam se milana galat sync kar dega. Plan: `DiscoveryProfile::publicCard()` me `roster_id` (sirf jab profile roster se juda ho) jodna, ek PHP test ke saath. Isse nayi jaankari public nahi hoti: card me roster wala naam/photo pehle se dikhta hai. Anonymous ya likhe hue naam wale profile ka roster jodidar hota hi nahi, unke liye sirf `d:`.

## 6. Kaun si files

| File | Kya |
| --- | --- |
| `people-state.js` (**naya**, sirf logic, DOM nahi) | Pehchan, status ka milan (agar do records ek hi insaan ke alag baat kahein to jiska `updatedAt` naya ho wahi jeetega, purana delete nahi hota), groups aur chips ki ginti, karan ki list |
| `people.js` | ✕ (JS se banta), Hidden / Couldn't sections, "I met them" ka jod, roster row ke labels, in-page ghoshna |
| `my-day.js` | Filter chips, karan, Try again, Hide, calendar export se `skipped` bahar |
| `plan.js` | `skipped` totals se bahar (2 line) |
| `app/Models/DiscoveryProfile.php` + ek PHP test | `roster_id` |
| `analytics.js`, `config/analytics.php` | Naye events (`discovery_hide/unhide`, `meet_missed_reason`) |
| SCSS | ✕, chips |
| Blade | **Koi badlav nahi** (naye tukde JS se; deploy = `public/build` + ek PHP file) |
| `db.js`, `data-controls.js`, `plan-reminder.js` | Koi badlav nahi |

## 7. Phase (har ek alag commit aur alag popup)

| Phase | Kya | Risk |
| --- | --- | --- |
| **0** | `people-state.js` + tests. Kahin juda nahi. | **Zero** |
| **1** | `roster_id` (PHP, chhota) | Kam |
| **2** | **Sync**: "I met them" ↔ Met, roster row ↔ match card, same-page ghoshna, ek pehchan | Madhyam (ye ek asli kami ka fix bhi hai) |
| **3** | ✕ / Hidden / "Remove" → "Hide" | Madhyam |
| **4** | My Day ke filter chips, Couldn't list, karan, Try again | Madhyam |
| **5** | Swipe, aakhri din ka banner, server ka block | Baad me |

## 8. "Pura hua" ki kasauti

- **Sync:** ek jagah dabao, doosri jagah wahi. Har button jodi ke liye jaanch (roster ↔ card ↔ My Day, dono disha).
- **Koi delete nahi:** Hide/Skip ke baad note aur time wahi. Har badlav wapas ho sakta hai.
- **Gine hue number sahi:** chip par jo likha, card bhi utne hi. Koi insaan do group me nahi, koi card dohrata nahi.
- Page dobara kholne par status wahi.
- `skipped` progress bar, calendar aur reminder me nahi.
- Purane records bina badlav ke wahi dikhte hain (purane `d:` aur `r:` dono).
- Export me naya data aata hai, Clear se sab jata hai.

## 9. Jaanch

- **Unit tests (Phase 0):** pehchan ka mel, purane `d:`/`r:` ka milan, status ki har chaal (aage-peeche), chips ki ginti, "do group me nahi".
- **PHP test:** `roster_id` sirf roster se jude profile me.
- **Asli Chrome, 390 px, alag SQLite copy, 40 nakli profiles + roster:** har sync jodi dono disha me, ✕/Hide/Show again, filter chips, karan, Try again, dobara kholna, export/clear. Purane records daalkar bhi.
- Purani suites: JS (abhi 135), asli-Chrome offline (7), `npm run build`.

## 10. Jokhim

| Jokhim | Upaay |
| --- | --- |
| "I met them" ab `meetings` bhi likhta hai: jo insaan pehle sirf card par tha, ab My Day ki list me bhi dikhega | Ye aapka sync hi hai; "Met" group me dikhega, "To do" me nahi |
| Do records ek insaan ke, alag baat kahein | Naya wala jeetega, purana delete nahi hota |
| Naya PHP field | Sirf ek key jodna, purana JS use ignore karta hai |
| Rollback | Purana `public/build` (aur PHP file). `skipped` wale records purane code me "done" dikh sakte hain (data safe) |
| Sabse zyada badlav Phase 2 me | Isliye alag, sabse pehle aur poori jaanch ke saath |

## 11. Samay ki salah

- **Phase 0 abhi** (koi asar nahi).
- **Baaki event ke baad**, asli feedback dekhkar (Sylhet 1 Oct, Rajasthan 3–4 Oct paas hain). Agar sync ka bug event se pehle hi kharab lag raha ho to sirf Phase 1–2, 29 Sep tak, uske baad freeze.

## 12. Aapse tay karna hai

1. "Remove" ko "Hide" banana theek hai? (Ye existing button ka badlav hai.)
2. Time nikale hue par unmarked session ke liye alag "Time over, not marked" chahiye?
3. "Hide done" chip rakhein ya "To do" chip use le le?
4. Session un-save reversible ho ya waisa hi?
5. ✕ mutual wave wale par confirm chahiye?
6. Karan ke chaar shabd aur "Stay in touch" theek hain?
