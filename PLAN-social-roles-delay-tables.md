# Plan: Social Media page, role marking, bulk social cards, schedule delay, Contributor tables

Owner ki request (9 Oct 2026). Owner ke faisle (popup):
- **Publish:** Copy + Share abhi, saath mein optional webhook (Make.com / Zapier) se ek click "Publish".
- **Captions:** templates se (event ke data se), admin/manager edit kar sake. AI nahi.
- **Delay:** banner + times shift + reminders bhi utne late.
- **Social card size:** dono — 1080 × 1350 aur 1080 × 1080, download karte waqt chuno.

Event manager ko bhi: role marking, bulk cards, delay, Contributor tables, Social Media page (sirf apne events).

---

## Package 1 — Attendees ko haath se mark karna (admin + manager)

- Roles: **Organizer, Speaker, Volunteer, Media Partner, Sponsor, Table Lead** (+ auto wale Microsponsor).
- Admin → Event → Roster mein har naam par "Roles" (chips). Manager panel mein naya tab **Attendees** (wahi list + roles).
- Galat auto-mark hataane ka bhi option (jaise kisi ko galti se Speaker mila).
- Storage: naya table `roster_marks` (event, person key, role, add/remove, kisne). Person key = Gravatar hash, warna naam — taaki roster dobara fetch hone par mark na khoye.
- `RosterRoles` auto + haath wale marks milata hai; attendee list par naye badges aur filter chips.
- Manager ke har change activity log mein.

## Package 2 — Contributor Day tables + Table Leads (admin + manager)

- Naya tab **Contributor Day**: har table = team (Core, Polyglots, … wahi list jo Contribute tab mein hai), track/room, floor, table number, leads (attendee list se chuno, ya naam likho), note.
- List se chune gaye leads apne aap **Table Lead** badge paate hain.
- Contribute tab: jis team ki table hai uske card par "Floor 2 · Hall B · Table 5 · Leads: A, B"; upar "Tables today" list. Koi table nahi to Contribute tab pahale jaisa.

## Package 3 — Schedule delay (admin + manager)

- Event par **"Running late"** form: kitne minute, poora event ya ek track, kis time ke baad wale sessions, chhota note. Hatana = 0 / Clear.
- Server sessions dete waqt delay jodta hai (`ScheduleDelay`), isliye Home, My Day, calendar export sab naye time dikhate hain; purana time kata hua. Home aur My Day par banner: "Track 1 is running 20 min late".
- `SendSessionRemindersJob` bhi naye time se reminder bhejta hai.
- Khule apps DataVersion se refresh hote hain; Cloudflare ke page cache ki wajah se ~1 minute lag sakta hai.

## Package 4 — "QR codes" → **Social Media** page (admin + manager)

- Tab ka naam Social Media. QR codes wahi page par ek section ke roop mein rahenge.
- **Brand colors:** event logo se apne aap nikle (browser mein), admin/manager badal sakein, save hon.
- **Event posts** (template): Save the date, N days to go, Schedule is live, Get CampBuddy (QR ke saath), Thank you. Har post: image (1080 × 1350 / 1080 × 1080), title, caption + hashtags (edit kar sakte hain), **Copy caption**, **Download**, **Share** (LinkedIn / X / Facebook / WhatsApp links; phone par system share sheet).

## Package 5 — Bulk social cards (admin + manager)

- Social Media page par **People cards**: role chuno (Speakers, Organizers, Volunteers, Sponsors, Media Partners, Table Leads, ya jinhone Camp Card share kiya) → har insaan ka "Meet our Speaker" post: photo, naam, role, talk, event logo, brand colors. **Frontend Camp Card se alag design.**
- Ek click mein **ZIP** download (sab images + har ek ka caption `captions.txt` mein). Size chuno: 1080 × 1350 ya 1080 × 1080.
- Naya JS dependency: JSZip (sirf is page par load).

## Package 6 — Webhook se ek-click Publish (optional)

- Social Media page par setting: **Webhook URL** (Make.com / Zapier "Custom webhook"). Khaali = Publish button nahi dikhega.
- **Publish** dabane par image server par save hoti hai (public link), aur server webhook ko `{title, caption, image_url, event}` bhejta hai; wahan ka scenario LinkedIn / Facebook / Instagram / X par post karta hai. Kab, kisne bheja — log.
- Setup guide page par hi (3 kadam). Webhook URL sirf server par, browser ko nahi dikhta.

---

**Risk:** P1, P2, P3 existing code chhoote hain (RosterRoles, roster API, Contribute tab, Home/My Day times, reminder job). P4–P6 zyaadatar naye pages. 3 nayi migrations (`roster_marks`, `contributor_tables`, events par delay + brand colors + webhook).

Har package: JS + PHP tests, build, real Chrome check, alag commit `stable` par. Push/deploy nahi.
