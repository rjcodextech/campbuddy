# Server-driven config

Ek JSON document, `GET /api/v1/app/config` (ETag, 60 s cache, app start + resume par fetch). App ke andar ek **bundled default** (offline pehli baar) aur **last-good cache** hota hai.

## Top-level
```json
{
  "schema": 3,
  "version": 41,
  "min_app": { "android": "2.0.0", "ios": "2.0.0" },
  "flags": { "native_push": true, "local_reminders": true, "discovery": true, "organizer_login": true },
  "theme": { "primary": "#60121C", "accent": "#DA7F01", "background": "#FCF1DB", "ink": "#2B1014", "muted": "#6E5A54", "radius": 12, "logo": "https://…/logo.png", "splash_text": "WordCamp companion" },
  "banner": { "id": "b7", "text": {"en":"WordCamp Delhi is live!","hi":"WordCamp Delhi shuru!"}, "style": "info", "action": {"type":"event","slug":"wordcamp-delhi-2026"}, "until": "2026-11-16T00:00:00Z" },
  "tabs": [ … ], "home": { … }, "strings": { … }, "onboarding": { … }, "organizer": { "tabs": [ … ] }
}
```

## Tabs
```json
{ "key": "my-day", "icon": "calendar", "title": "tabs.my_day", "enabled": true, "order": 2, "requires": "event" }
```
`key` fixed set (home, my-day, quest, contribute, explore, camp-card, memories…); `icon` catalog ke naam (web `LineIcons` wale); `title` strings ka key.

## Home layout (event Home)
Sections ki **list**; har ek ka `type` app ke component catalog se:
| type | Component |
|---|---|
| `hero` | event naam, dates, status |
| `starting_soon` | saved session jaldi shuru |
| `happening_now`, `up_next` | live blocks |
| `start_here` | first-timer guide card |
| `suggested_action`, `people_cta`, `progress` | baaki cards |
| `memories`, `bring_data` | after-event |
| `banner` | config ka announcement |
| `custom_card` | title + body + button (action) — admin ka free-form card |

```json
"home": { "sections": [ {"type":"hero"}, {"type":"starting_soon"}, {"type":"happening_now","title":"home.now"}, {"type":"up_next"}, {"type":"start_here","visible_if":"first_timer"} ] }
```
`visible_if`: chhoti fixed list (`first_timer`, `phase:before|during|after`, `has_saved`, `locale:hi`) — koi expression engine nahi.

## Strings / translations
`strings.<locale>.<key>`; app locale = phone ki, fallback `en`. Placeholders `{count}` jaise; plurals `one/other`. Missing key → bundled default. Admin editor key list app ke default se aati hai (aap sirf maujooda keys badal sakte ho, nayi nahi).

## Actions (button/banner par)
`{type: event|tab|url|share|deeplink, …}`; `url` sirf https allowlist domains. Koi script nahi.

## Safety
- Schema JSON Schema se **server par validate** (publish se pehle) aur app par phir se; invalid = ignore, last-good.
- `schema` number badhne par purani app bhi bina crash purana hissa chalaye (unknown section type skip).
- Har publish ka version aur kisne kiya (audit); ek-click rollback.
- Config mein kabhi secret/PII nahi.
