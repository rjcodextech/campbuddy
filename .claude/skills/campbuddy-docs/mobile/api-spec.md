# API spec

Base: `https://campbuddy.club/api/v1`. JSON, UTF-8. Naye endpoints `/app/*` ke neeche (website ke HTML routes ko nahi chhuna). Sab GET par `ETag` + `Cache-Control`, `If-None-Match` → 304. Errors ek format mein: `{ "error": { "code": "validation", "message": "...", "fields": {...} } }`.

## Pehle se maujooda (reuse)
| Endpoint | Kaam |
|---|---|
| `GET /health`, `GET /cache-version`, `GET /app-config` | status, cache version, min version + switches (ab config editor se jud jayega) |
| `GET events/{slug}/roster`, `GET events/{slug}/data-version` | attendee list, kya badla (polling) |
| `GET/POST/PATCH/DELETE events/{slug}/discovery…`, waves, messages | discovery (owner-token bearer) |
| `device-transfers…` | naye phone par data |
| `POST events/{slug}/bookmarks`, `push/subscribe`, `push/native-subscribe` | reminders (web push / FCM token) |
| `POST events/{slug}/feedback`, `offers/{id}/leads`, `free-steal-suggestions`, `camp-card-share…`, `discovery/{id}/report` | feedback, leads, share, report |

## Naye (attendee, public, read-only)
| Endpoint | Shape (chhota) |
|---|---|
| `GET app/config` | poora server-driven config ([05](05-server-driven-config.md)) |
| `GET app/events?country=&include=past` | `[{slug,name,short_name,logo,starts_on,ends_on,timezone,country,status,hero{...}}]` |
| `GET app/events/{slug}` | event detail + `phase`, links, theme (`SiteTheme`), memories summary, `data_version` |
| `GET app/events/{slug}/sessions` | `ScheduleDelay` laga hua: `[{id,title,starts_at,ends_at,room,track_names[],speakers[],level,beginner_friendly}]` |
| `GET app/events/{slug}/speakers`, `/sponsors`, `/organizers` | `EventData` se |
| `GET app/events/{slug}/deals`, `/free-steals` | default deals + event deals (`DefaultDeals`, `FreeSteals`) |
| `GET app/events/{slug}/quests` | quests + checklist |
| `GET app/events/{slug}/contributor-tables` | Contributor Day tables |
| `GET app/events/{slug}/guide` | first-timer guide (structured sections, `FirstTimerGuide`) |
| `GET app/events/{slug}/memories` | photos / WordPress.tv links |
| `GET app/events/{slug}/delay` | running-late info (chhota, polling ke liye) |
| `GET app/events/{slug}/bundle?since=<data_version>` | upar ke sab ek call mein, sirf badla hua (pehli sync + offline pack) |

Pagination: lists 2000 tak ek response (jaise abhi roster); bade ke liye `cursor`. Time sab UTC ISO-8601 + event ka `timezone` alag field.

## Naye (organizer)
Auth: **Laravel Sanctum** personal access token. `POST app/auth/login {email,password,device_name,role:admin|manager}` → `{token, user, abilities}`; `POST app/auth/logout`; token expiry + rotation; admin 2FA baad mein. Har request `Authorization: Bearer`. Manager sirf apne events (maujooda `EnsureEventManager` rule), admin sab. Rate limit alag (`throttle:organizer`).

| Area | Endpoints |
|---|---|
| Dashboard | `GET app/admin/dashboard` (countdown, drafts, 3 numbers, maintenance) |
| Events | `GET/POST/PATCH app/admin/events`, `…/{id}`, status approve (admin), manager ke liye `app/manager/events` |
| Reports | `GET …/events/{id}/reports`, `POST …/reports/{discoveryId}/remove|dismiss` |
| Notifications / Memories | `GET …/notifications`, `GET/POST …/memories`, `POST …/memories/notify/{kind}` |
| Running late | `GET/POST …/delay`, `POST …/delay/clear` |
| Quests, Tables, Roster, Deals, Leads | CRUD jaise panel (leads CSV = file download) |
| QR kit | `GET …/qr` (links + utm; QR app mein banta hai) |
| Analytics, Errors, Feedback | read-only JSON (`AnalyticsReport`) |
| Push token | `POST/DELETE app/organizer/push-token` |
| **Mobile config** | `GET/PUT app/admin/config/draft`, `POST …/publish`, `POST …/rollback`, `GET …/versions` (editor ke liye) |

Har endpoint ka **contract test** (PHP): response JSON schema file (`schema/api/*.json`) se match; wahi schema se Kotlin models bante hain. Jab server badle aur schema na badle (ya ulta), `check:sync` fail.

## Versioning / compatibility
- Additive badlav (naya field) — purani app ignore karti hai.
- Breaking = naya `app/v2` path; purani app min-version se ruk jati hai (update screen).
- App har request mein `X-App-Version`, `X-Platform`, `X-Schema` bhejti hai (logs + config targeting).
