# Admin → "Mobile app" config editor

Admin panel (web) mein naya section **Mobile app**; native organizer app mein bhi wahi (read + chhote badlav). Sirf admin (manager nahi).

## Screens
| Tab | Kya badalta hai | UI |
|---|---|---|
| **Overview** | Live version, kaun si app versions chal rahi, draft hai ya nahi | status card, Publish / Rollback |
| **Navigation** | Tabs: order (drag), on/off, icon, title | list + drag handle, icon picker (catalog) |
| **Home** | Sections ka order / on-off / title / `visible_if`, custom cards | list + preview |
| **Text & languages** | Har string, EN / HI (aur languages) | table: key, default, current, search, "missing" filter |
| **Look** | Rang, logo, splash text (contrast check AA, auto warning) | colour pickers + live phone mockup |
| **Banner** | Announcement: text, style, action, start/end time | form |
| **Switches** | Feature flags, min app version per platform, "update karo" message | toggles |
| **Onboarding** | Questions ke labels / "why" lines (fields fixed: 5) | text fields |
| **Preview** | Draft ko test-phone par dekhna: QR scan → app draft config le | QR + JSON viewer |
| **History** | Versions, diff, restore | table |

## Workflow
1. **Draft** (sab badlav draft mein; live nahi).
2. Validate (schema + contrast + missing strings + broken links) → errors line-wise.
3. **Preview**: draft ek `preview token` se; app mein Settings → "Preview config" (dev/organizer build) ya QR.
4. **Publish** → naya `version`, live, audit log (kisne, kya diff).
5. **Rollback** kisi bhi purane version par.
Do admin ek saath edit: draft par optimistic lock (`updated_at`), conflict par merge screen.

## Data model (Laravel)
```
mobile_app_configs: id, version (unique), status draft|published|archived, json (longtext), schema int,
                    note, created_by, published_by, published_at, timestamps
mobile_app_strings:  (optional, editor ki search ke liye) key, locale, value  — publish par config JSON mein bante hain
mobile_app_config_audit: id, config_id, user_id, action, diff (json), created_at
```
Publish ek DB transaction: purana `archived`, naya `published`, cache bust (`CacheVersion` jaise), CDN purge.

## Permissions aur safety
- Gate `manage-mobile-config` (admin). Har badlav audit mein. Event manager ko nahi.
- Server hamesha schema se validate; editor kabhi invalid publish nahi karne deta.
- **Per-event override** (baad mein): kisi ek WordCamp ke liye alag banner/strings (`event_overrides`), global config par overlay.
- Targeting (baad mein): platform / min version / locale ke hisaab se alag config.

## Kya editor se nahi badlega (jaanbujhkar)
Naye screens/components, logic, permissions, API shape — wo app release chahiye. Isse "admin ne galti se app tod di" nahi hota.
