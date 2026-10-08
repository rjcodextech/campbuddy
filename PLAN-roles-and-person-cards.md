# Plan: attendee list mein roles + person card + shared Camp Card

Owner ki request (9 Oct 2026): organizer, speaker, volunteer, microsponsor, sponsor ko attendee list mein highlight karo; tap karne par unka card download ho sake; jinhone apna Camp Card banaya hai unka card bhi list mein ho.

Owner ke faisle (popup, 9 Oct):
- Doosron ka card **public data se** banega, card par line: "Made from public WordCamp info".
- Attendee ka apna Camp Card list mein **naye opt-in toggle** se jayega.
- **Sponsor (log) skip** — WordCamp sirf sponsor companies deta hai, logon ka data nahi.

## Data check (Rajasthan 2026, live)

| Role | Source | Match |
|---|---|---|
| Organizer | `wp/v2/organizers` (pahale se cache: `organizers`) | 15/16 Gravatar hash se |
| Speaker | `wp/v2/speakers` (pahale se cache: `speakers`) | 25/29 Gravatar hash se |
| Volunteer | `wp/v2/wcb_volunteer` — **naya fetch**, sirf naam | 18/24 naam se |
| Microsponsor | Attendees page par alag `tix-attendee-list`, jiske upar wale block ki class/heading mein "microsponsor" | 17 log |

Matching: pahale Gravatar hash (pakka, same email); jiska hash list mein nahi mila, uska exact naam (case/space ignore). Roster ka naam hi dikhega.

---

## Package 1 — Roles: badge + highlight + filter

**Server**
1. `WordCampRestClient::fetchVolunteers()` (`wcb_volunteer`), `WordCampNormalizer::normalizeVolunteers()` (id, name). `FetchSpeakersSponsorsSessionsJob` mein `volunteers` optional list. **404 = site par volunteers hi nahi → chupchaap skip** (har 15 min warning nahi).
2. Migration: `attendee_roster.is_microsponsor` (bool, default false).
3. `AttendeeRosterScraper::parse()` har entry ke saath `microsponsor` flag (list kis block mein hai). Parse job upsert mein flag (same insaan dono list mein = true).
4. `App\Support\RosterRoles`: speakers/organizers/volunteers cache + microsponsor flag se `roster_id → roles[]`; speaker ke liye talk titles.
5. Roster API (`/api/v1/events/{slug}/roster`) har entry mein `roles` (aur speaker ke `talks`). Wahi 60 s cache, ETag.

**App (Explore → People)**
- Row par chhote badges: Organizer · Speaker · Volunteer · Microsponsor; role wali row halki highlight.
- List ke upar chips: **All 319 · Organizers 15 · Speakers 25 · Volunteers 18 · Microsponsors 17** (khaali chip nahi dikhega). Search ke saath chalega.
- GA: `roster_filter` (`filter_value` reuse).

## Package 2 — Person sheet + Download card

- Row tap (link/Meet button chhod ke) → bottom sheet: photo, naam, badges, links, speaker ke talks, **+ Meet**, **Download card**, **Share**.
- Card = Camp Card ka **Ticket** design, 900 DPI PNG. Naam; role line ("Speaker · Organizer"); talks/tags; QR pahale link par (LinkedIn → website → X); footer line **"Made from public WordCamp info"**. Koi link nahi → QR nahi.
- Agar us insaan ne apna Camp Card share kiya hai (Package 3) → **unka hi card** (unki chuni fields, unka QR link), line nahi.
- Code: Camp Card page ka card markup ek Blade partial mein (output same), card banane ka code `camp-card.js` se ek shared module mein (Camp Card page ka behaviour same). Explore usi ko use karega.
- GA: `person_card` (`result`: open / download / share). Naam GA mein nahi.

## Package 3 — "Show my Camp Card on the attendee list" (opt-in)

- Camp Card page par toggle (default **off**). On karne par: attendee list se apna naam chuno (discovery jaisa picker; discovery mein naam pahale se chuna hai to wahi).
- Server par sirf **card par dikhne wali fields** (CC2 ki chuni hui) + QR link: naya table `shared_camp_cards` (event, roster entry unique, owner token hash, fields, expires_at = discovery jaisa retention).
- API: `POST/PUT/DELETE /api/v1/events/{slug}/camp-card-share` owner token ke saath (discovery jaisa). Card save karne par shared copy apne aap update; toggle off = server se delete.
- Naam ka claim: ek naam = ek card. Agar us naam par kisi aur ka discovery profile hai to share mana (same phone ka discovery token ho to OK).
- Roster API mein `camp_card` (shared fields); list mein "Camp Card" badge; sheet mein unka card.
- Admin → Roster: shared card hatane ka button (abuse ke liye).
- Camp Card page ki line "Saved on this device only. Nothing you type here is sent to CampBuddy." badlegi: "...unless you turn on Show on the attendee list."
- Spec CC5 + privacy page update.

## Risk

- P1: roster API response thoda bada (roles); parser change — test fixtures se check, purana HTML (bina microsponsor) same result.
- P2: Camp Card page code ka refactor — Camp Card ka E2E + PNG size check pahale/baad.
- P3: naya personal data server par (sirf opt-in, retention ke saath delete).

Har package: JS tests, related PHPUnit, build, real Chrome check, alag commit `stable` par. Push/deploy nahi.
