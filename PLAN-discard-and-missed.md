# Plan: ✕ Discard aur "Kin logon se nahi mil paye" list

*27 Sep 2026. Sirf plan hai, kuch implement nahi hua.* Ye do kaam ek saath isliye soche gaye hain ki dono ek hi sawal ka jawab hain: **"is insaan ka mere liye abhi kya haal hai?"**. Kuch bhi badalne se pehle aapse do baar popup se poochha jayega (freeze niyam), har phase ke liye alag.

## 1. Kya chahiye (aapki baat)

1. **✕ Discard:** kisi match card ke corner me chhota ✕. Dabane par wo insaan discovery se hat jaye, aur schedule se bhi. (Swipe baad me, shortcut ki tarah.)
2. **"Nahi mil paye" list:** jinse milna tay kiya tha par mil nahi paye, unki alag list, karan ke saath (optional). Aur ise behtar dikhana.

**Jaan-boojhkar nahi karenge:** server par kuch nahi (koi API, database ya migration nahi), saamne wale ko kuch pata nahi chalega, kuch delete nahi hoga (retention niyam), asli "block" (wo server ka kaam hai, baad me).

## 2. Ab kya hai (code padhkar)

| Cheez | Haal |
| --- | --- |
| My Day → My schedule → "People to meet" | Har insaan par pehle se **✓ Met** aur **✗ Couldn't** button (`meetings.status = 'met' / 'missed'`). Bas grouped list aur karan nahi hai. |
| Explore ka **"I met them"** | Alag jagah save hota hai (`metHistory`). **My Day ke Met/Couldn't se juda hi nahi.** Ek me tick karo, doosre me nahi dikhta. |
| Insaan ki pehchan (`personKey`) | Roster se `r:<id>`, match card se `d:<id>`. Ek hi insaan do baar add ho sakta hai. |
| `meetings` record | `{personKey, name, avatarUrl, links, note, at, status, createdAt, updatedAt}`. `saveMeeting` koi bhi naya field bina schema badle jodne deta hai. |
| Kaun `meetings` padhta hai | `my-day.js`, `people.js`, `plan.js`, `plan-reminder.js` (aur calendar export, `my-day.js:234`). |
| Data controls (export/clear) | **Saare** IndexedDB stores apne aap shamil (`exportAll`/`clearAll`). Naya kuch nahi karna. |
| Toast | Sirf message dikhata hai, "Undo" button nahi. |
| Template me naya slot | Purana JS + naya HTML: slot khali reh jata hai. Naya JS + purana HTML: slot chupchap chhoot jata hai (error nahi). |

## 3. Faisla: ek hi sach ka source, `meetings.status`

Har insaan ke liye ek record, aur 4 haal:

| Haal | `status` | Kahan dikhta hai |
| --- | --- | --- |
| Milna hai (planned) | `null` | Explore card ("✓ To meet"), My Day "To meet" |
| **Mil liye** | `'met'` | Explore "People you've met", My Day "Met" |
| **Nahi mil paye** | `'missed'` + `reason` | My Day "Couldn't meet", Explore me chhota section |
| **Hataya (✕)** | `'skipped'` (**naya**) | Explore me sirf "Hidden (N)" me, My Day me kahin nahi |

- Koi naya IndexedDB store nahi, koi version bump nahi. Purane records waise ke waise sahi rehte hain.
- `metHistory` band nahi karte. "I met them" dono jagah likhega (purana + naya), aur padhte waqt "met" = `metHistory` ya `status == 'met'`. Isse purana data aur purana build dono theek rehte hain.
- ✕ ke liye wo insaan jiske paas abhi `meetings` record nahi hai, uske liye ek record banta hai (`status: 'skipped'`, naam/photo/links card se). Note aur time jo pehle likha tha, wo **rehta hai**; sirf status badalta hai. "Show again" par `status` wapas `null`.
- Karan (`reason`): sirf fixed chhoti list: `no-time`, `not-found`, `not-there`, `changed-mind`. Free text nahi (privacy aur simple).

## 4. Feature A: ✕ Discard

**Kaise dikhega (Explore → People)**
```
[photo] Naam ✓ ...                  [✕]
        Profession · You both: SEO
[tag] [tag]
[👋 Wave] [+ Meet] [I met them]
```
- ✕ card ke upar-daaye corner me, chhota, halka rang, `aria-label="Hide {naam}"`. Screen reader aur keyboard se chalta hai.
- Dabane par: card turant gayab, toast "Hidden. They're under Hidden (N) below." Koi confirm nahi.
- Neeche band section **"Hidden (N) ▸"**. Kholne par har insaan ek chhoti row me, aur **"Show again"** button.
- **Mutual wave wale (jinse chat chal rahi hai)** ke ✕ par ek baar `confirm("Hide? You'll stop seeing your chat with them.")`. Baaki me nahi.

**Kya hota hai jab hatate ho**
- Best matches, Also open to meet aur Mutual, teeno se gayab. `windowCards` (Show N more) hatane ke **baad** ki list par chalta hai, isliye count sahi rehta hai.
- Agar schedule me wo insaan tha, wo My Day se gayab (status `skipped`, note/time bache rehte hain), aur progress bar ke "N of M done" me nahi ginta, aur calendar export me nahi jaata.
- Home ka "👋 N matches want to meet you" count me hataye hue log **nahi** ginte.
- Sabhi hata diye to "No one else has joined yet" nahi, balki "You've hidden everyone. Hidden (N) below." dikhega.
- Wo agar aapko wave/message kare: aapko dikhega nahi (card chhupa hai). Unhe kuch pata nahi. Ye sirf aapko chhupata hai, rokta nahi.
- Wo insaan discovery chhod kar wapas aaye to naya `discovery_id` milta hai aur dobara dikhega. Iska koi upaay bina server ke nahi (limit likhni hai).

**Swipe:** Phase 3. ✕ ke upar shortcut ki tarah, sirf tab jab logon ko chahiye.

## 5. Feature B: "Nahi mil paye" list

**My Day → People to meet** me chips (gine hue):
```
To meet (3) · Met (5) · Couldn't meet (2)
```
- Ek waqt me ek group dikhta hai (default: To meet; agar sab ho gaya to Couldn't meet). "Hide done" chip jo abhi hai, uska kaam ye chips le lete hain (purana chip wahi rehta hai).
- **✗ Couldn't dabane par:** turant status `missed`. Card ke neeche ek chhoti row: "Why? [Ran out of time] [Couldn't find them] [They weren't there] [Changed my mind]". Kuch na dabao to bhi theek. Ek tap me karan jud jata hai.
- "Couldn't meet" card par: naam, jo note tha, karan, aur uske **links** (LinkedIn/X/website, jo record me pehle se hain) ke saath **"Stay in touch"**. Aur **"Try again"** (status wapas `null`).
- Explore me jinka status `missed` hai wo "Your best matches" me nahi rahenge, ek band section **"Couldn't meet (N) ▸"** me, "Try again" ke saath.
- **"I met them" ka jod:** Explore me dabao to My Day me "Met", aur My Day me Met dabao to Explore ke "People you've met" me. Ek hi insaan ek hi group me.
- (Phase 3, optional) Event ke aakhri din ke baad My Day par naram banner: "4 logon se milna tha, 2 abhi baaki: Met / Couldn't tay karein."

## 6. Kaun si files badlengi

| File | Kya | Naya/purana |
| --- | --- | --- |
| `resources/js/attendee/people-state.js` | Sirf logic: haal ka nakshha, gine hue groups, karan ki list, "met" ka jod. DOM nahi, isliye poora unit test | **Naya** |
| `people.js` | ✕ button (JS se banta hai), filter, "Hidden" aur "Couldn't meet" sections, "I met them" jod, Meet button ka label (`skipped` par "✓ To meet" nahi) | Purana (chhote badlav) |
| `my-day.js` | Chips, karan ki row, "Try again", calendar export se `skipped` bahar | Purana |
| `plan.js` | `skipped` ko `people` aur totals se bahar | Purana (2 line) |
| `plan-reminder.js` | Apne aap theek (`computePlan` se) | Koi badlav nahi |
| `db.js`, `data-controls.js` | Koi badlav nahi (`saveMeeting` me naya field bina badlav) | Koi badlav nahi |
| `resources/scss/components/_people.scss` (+ plan ka scss) | ✕ ka chhota style, chips | Purana |
| `analytics.js` + `config/analytics.php` | Naye events: `discovery_hide`, `discovery_unhide`, `meet_missed_reason` (sirf `reason` ka naam, kisi ka naam nahi). Test dono ka mel dekhta hai | Purana |
| Blade templates | **Koi badlav nahi**: naye tukde JS se banenge, taaki purani saved HTML aur nayi JS ke beech mel ki dikkat na ho. Deploy sirf `public/build` | Koi badlav nahi |
| Docs | `spec/03-functional-requirements/04-matching.md`, `08-my-day.md`, `spec/15`, `TODO.md` | |

## 7. Phase (har phase alag commit, alag popup)

| Phase | Kya | Risk | Kimat |
| --- | --- | --- | --- |
| **0** | `people-state.js` aur uske tests, koi UI nahi, kahin jura nahi | **Zero** | Chhota |
| **1** | Explore me ✕, "Hidden (N)" aur "Show again", filter, Home count, `skipped` ka `plan.js` me hona | Madhyam | Madhyam |
| **2** | My Day ke chips, "Couldn't meet" list, karan, "Try again", "I met them" ka jod, Explore me "Couldn't meet" section | Madhyam | Madhyam |
| **3** | Swipe, roster row me ✕, `r:`/`d:` ek karna, aakhri din ka banner, server ka block | Baad me | Bada |

## 8. "Pura hua" ki kasauti (jo aapne pehle maangi thi wahi)

- **Koi card dohrata nahi.** Har insaan ek hi group me, kabhi do me nahi. Har badlav ke baad check (jaise "Show more" me kiya tha).
- **Gine hue number sahi.** Chip me "Couldn't meet (2)" ho to card bhi theek 2 (Hidden (N), To meet (N), Met (N) bhi).
- ✕ dabane par sirf wahi insaan gayab, baaki list waisi ki waisi, "Show N more" ka count sahi.
- Page dobara kholne par bhi ✕ wala insaan hata hi rahe (IndexedDB me save).
- "Show again" karo to wo insaan wapas usi jagah, note/time ke saath.
- Progress bar ("N of M done") aur calendar export me `skipped` nahi.
- Purane records (bina `skipped`/`reason` wale) bina kisi badlav ke wahi dikhte hain.
- Export me naya data aata hai, "Clear my data" se sab hat jata hai.

## 9. Jaanch ka tareeka

- **Unit tests (Phase 0):** har haal ka badlav (`null → met / missed / skipped → null`), groups ki ginti, "koi insaan do group me nahi", `metHistory` ka jod, purane record, khali list.
- **Asli Chrome, 390 px, alag SQLite copy me 40 nakli profiles** (jaise "Show more" me kiya): ✕, Hidden, Show again, dobara kholna, mutual ka confirm, Home count, chips, karan, Try again, "I met them" ka jod, export/clear. Purane tarah ke records pehle se daalkar bhi.
- Purani suites: JS (abhi 135), asli-Chrome offline (7), `npm run build`. PHP sirf `config/analytics.php` me badlega; wo test aapke Windows par OpenSSL ki wajah se pehle se fail hota hai (Linux par theek).

## 10. Jokhim aur upaay

| Jokhim | Upaay |
| --- | --- |
| Galti se ✕ dab gaya | "Hidden (N)" me "Show again", note/time bache rehte hain. Mutual me confirm. |
| Purane code ke rasta (`plan.js`, calendar, reminder) `skipped` ko nahi jaante | Wahi 3 jagah badlengi aur unke tests; ye plan me pehle se likhe hain |
| Purani saved HTML + nayi JS | Blade nahi badlega, naye tukde JS se |
| IndexedDB me kuch bigde | Koi schema badlav nahi, koi record delete nahi |
| Wapas jaana ho | Purana `public/build` wapas (sirf JS/CSS). Ek kami: rollback ke baad `skipped` wale records purane code me "done" dikh sakte hain (data safe rehta hai, bas dikhna galat). |
| Ek insaan `r:` aur `d:` se do baar | Phase 3 me. Tab tak jo card se hataya wahi us card me hata |

## 11. Samay ki salah

- **Phase 0 abhi kar sakte hain** (kahin jura nahi, event par koi asar nahi).
- **Phase 1 aur 2 event ke baad** (Sylhet 1 Oct, Rajasthan 3–4 Oct paas hain): asli feedback dekhkar, jaisa aapne khud kaha. Agar phir bhi event se pehle chahiye to sirf Phase 1, aur wo bhi 29 Sep tak upload, uske baad freeze.

## 12. Aapse tay karna hai

1. Mutual wave wale par ✕: confirm (meri salah) ya seedha hat jaye?
2. Karan ki list ke shabd theek hain? ("Ran out of time", "Couldn't find them", "They weren't there", "Changed my mind")
3. "Couldn't meet" me "Stay in touch" (unke links) chahiye?
4. Phase 0 abhi, baaki event ke baad: theek?
