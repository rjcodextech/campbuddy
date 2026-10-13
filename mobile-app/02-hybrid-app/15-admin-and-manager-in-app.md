# Admin aur Event manager ko app mein laana — plan

**Status:** sirf plan (13 Oct 2026). Code abhi nahi.

## Faisla (recommendation)
**Ek hi app**, jisme chhoti si "Organizer sign in" entry ho. Alag "CampBuddy Organizer" app tabhi jab store review sawal kare ya organizers ko alag icon chahiye.

| Raasta | Fayda | Nuksaan |
|---|---|---|
| **A. Same app, hidden entry (recommended)** | Ek build, ek review, ek codebase; admin website jaisa hi chalta hai | Review notes mein demo login dena padega |
| B. Alag Organizer app | Attendee app saaf; alag push/permissions | Do store listings, do review, do CI — kaam double |
| C. Sirf browser mein admin | Kuch nahi karna | App ke andar organizer ko bahar jaana padta hai, push alerts nahi |

## Kaise kaam karega (A)
1. **Entry:** attendee app ke footer/About mein ek chhota link "Organizers · sign in" (sirf app mein dikhe, UA `CampBuddyApp`; web par pehle jaisa). Do raaste: `/manager/login` (event manager) aur `/admin/login` (admin) — ek screen "I am an… Admin / Event manager" se.
2. **Session:** WebView ki cookie wahi website wali Laravel session hai; app band-khol karne par bani rehti hai (SESSION_LIFETIME 120 min idle ke baad dobara login — organizers ke liye "remember me" tick default on kar sakte hain).
3. **Pages:** admin/manager panel responsive Tailwind hai, WebView mein khulta hai (allowNavigation `campbuddy.club` hi hai, naya kuch nahi). Phone par jo page tang dikhe unhe alag se polish karna padega (Events list, Reports, Notifications, Memories pehle — organizer ka roz ka kaam).
4. **Service worker:** `/admin` pehle se cache se bahar hai (sw.js). `/manager` ke liye bhi wahi rule verify karna hai (signed-in pages kabhi cache nahi).
5. **Back/exit:** Android back = history back; login page par back = attendee app. Logout ke baad attendee Home par wapas.
6. **Deep link:** `/admin` aur `/manager` App Links mein **nahi** jodna (email ke link browser mein khulein, jaise ab). Sirf app ke andar navigation.

## Organizer ke liye app-only fayde (baad ke packages)
| Package | Kya | Kaam |
|---|---|---|
| O1 | **Entry + login screen** (upar 1–2) | chhota, web hook 1 (`isNativeApp` par link) |
| O2 | **Mobile polish** — admin/manager ke 5 roz-ke-pages phone par saaf | CSS, no logic |
| O3 | **Organizer push:** naya report aaye, thank-you push chala, event live hua | `NativePushToken` mein `user_type`/`manager_id` ya alag table + `NativePush::toOrganizers()`; permission login ke baad pooche |
| O4 | **Biometric lock** (Face ID / fingerprint) app wapas khulne par | Capacitor biometric plugin; sirf organizer session par; web par kuch nahi |
| O5 | **Quick actions** — "Running late" delay, Reports review ek tap se | existing endpoints, naye chhote screens |

## Security (organizer area)
- Admin panel pe sirf HTTPS, same CSRF/session cookies; `HttpOnly`, `Secure`, `SameSite=Lax` check karna (production `.env`: `SESSION_SECURE_COOKIE=true`).
- Login par throttle pehle se (`throttle:30,1` manager); admin login ke liye bhi confirm.
- App mein screenshot/recents blur (iOS: privacy screen) organizer session mein — O4 ke saath.
- Logout par server session invalidate + WebView cookie clear.
- Demo account review ke liye: **alag manager account**, sirf ek demo event ka, review ke baad password badlo. Admin ka asli account kabhi nahi dena.
- Event manager ko sirf apne events (pehle se enforced); app mein koi extra permission nahi milti.

## Store-review dhyan
- Apple 5.1.1(v) / Play: login wala hissa ho to **demo credentials** review notes mein.
- Organizer area ko "staff only" likho; attendee app ka no-login claim sach rehta hai (login optional, sirf staff ke liye).
- Account deletion rule (Apple 5.1.1(v)) sirf tab jab app mein account **banta** ho — yahan accounts admin banata hai, isliye lagu nahi; fir bhi privacy policy mein likho.

## Kab
Pehle store release (attendee) → fir O1+O2 (1 hafta) → O3 (push) jab real use dikhe → O4/O5 maang par.
