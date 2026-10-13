# Native app (Kotlin, bina WebView) — plan

**Status:** sirf plan (13 Oct 2026). Code abhi nahi. Maujooda Capacitor app (`mobile-app/app/`) chalti rahegi jab tak native app parity tak na pahunche.

## Faisla (aapke jawab se)
| Sawal | Jawab |
|---|---|
| Technology | **Kotlin** — ek hi code se Android + iOS = **Kotlin Multiplatform + Compose Multiplatform** |
| Content kaise aaye | **API se**; jo fix hai wo app mein hardcoded; baaki **Admin → Mobile app config editor** se badle (store release ke bina) |
| Scope v1 | **Attendee + Organizer (Admin / Event manager) dono native** |
| WebView | Nahi. Har screen native UI |

## Padhne ka order
1. [01-shape-and-decisions.md](01-shape-and-decisions.md) — kya banega, purani app ka kya hoga
2. [02-tech-stack.md](02-tech-stack.md) — libraries aur expect/actual (platform) hisse
3. [03-architecture.md](03-architecture.md) — modules, offline-first, data flow
4. [04-api-spec.md](04-api-spec.md) — maujooda aur naye endpoints
5. [05-server-driven-config.md](05-server-driven-config.md) — config JSON, components, strings, theme
6. [06-admin-config-editor.md](06-admin-config-editor.md) — admin ka "Mobile app" editor
7. [07-attendee-screens.md](07-attendee-screens.md) — web ke har page ka native screen
8. [08-organizer-screens.md](08-organizer-screens.md) — admin / manager screens, kya native, kya browser
9. [09-offline-push-security.md](09-offline-push-security.md)
10. [10-build-ci-windows.md](10-build-ci-windows.md) — Windows par kaise, iOS cloud se
11. [11-phases-risks.md](11-phases-risks.md) — milestones, waqt, risks
12. [12-open-decisions.md](12-open-decisions.md) — shuru karne se pehle ke faisle
