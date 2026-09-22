# CampBuddy — WordCamp Rajasthan 2026

A local-first, mobile-first PWA for first-time WordCamp attendees, backed by a small
[Slim Framework](https://www.slimframework.com/) PHP API that auto-fetches and caches event data
server-side so the app scales to a large audience without hammering a third-party API.

## Included in v1

- First-time attendee onboarding
- Event countdown and context-aware "Right now" panel
- Pre-WordCamp checklist
- "I have free time" micro networking missions
- Camp Quest / social nudges
- Contributor Day team finder
- Camp Card with a branded, downloadable and shareable QR code
- Explore — latest WordCamp Rajasthan videos from the WPSimplified YouTube channel
- Sponsor list and official social links
- My Day / saved schedule anchors
- Useful venue and official event links
- Local-only device storage (checklist, quests, Camp Card) — no accounts
- Offline service worker
- Installable PWA manifest
- Slim Framework backend: server-side auto-fetch, MySQL cache, admin panel, rate limiting, security headers

## Architecture at a glance

```
/                    static PWA (index.html, app.js, styles.css, sw.js, manifest, wcr/) — unchanged, served as-is
/backend/            Slim Framework app, mounted under /backend by the root .htaccess rewrite
  /backend/api/v1/*  public, read-only JSON API the PWA calls (same-origin, no CORS needed)
  /backend/admin     password-protected panel: fetch status, manual refresh, event slug, field overrides
```

The frontend still fetches "live" event/media data exactly like before, just from `/backend/api/v1/*`
instead of a third-party host. The backend fetches from the upstream WPSimplified API on a schedule
(cron) and caches it in MySQL, so **all visitor traffic is served from the local cache** — the upstream
API gets hit once per refresh interval, not once per visitor. This is what makes the app viable at
large scale (see "Scaling to ~1M users" below).

## Run locally

### Frontend only (no backend, original behavior)

Because service workers require HTTP(S), do not open `index.html` with `file://`.

    python3 -m http.server 8080

This serves the static files fine, but `/backend/api/v1/*` calls will fail (falls back to the bundled
static data — see "Live data" below), and the admin panel won't exist. For full functionality, run
the backend too (next section).

### Full app (frontend + backend)

Requires PHP 8.1+, Composer, and MySQL/MariaDB, plus an Apache vhost with `mod_rewrite` and
`AllowOverride All` pointed at the repo root (this project was built and tested against WAMP).

```
cd backend
composer install
cp .env.example .env        # then edit DB_*, SESSION_SECRET, etc.
php bin/migrate.php         # creates all tables
php bin/create-admin.php youradminusername   # prompts for a password
php bin/refresh-event-data.php               # first fetch, so the app isn't empty on first load
```

Point your webserver's docroot at the repo root (not `backend/public/`) so both the static files and
the root `.htaccess` rewrite are in effect. Example WAMP vhost:

```apache
<VirtualHost *:80>
    ServerName campbuddy.test
    DocumentRoot "path/to/campbuddy"
    <Directory "path/to/campbuddy/">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Then visit `http://campbuddy.test/` (frontend) and `http://campbuddy.test/backend/admin` (admin panel).

`BACKEND_MOUNT_PATH` in `.env` (default `/backend`) must match wherever the root `.htaccess` rewrite
sends `/backend/*` — change both together if you want a different mount point.

### Local HTTPS (optional)

This repo's `.htaccess` only forces HTTPS for non-local hostnames (see the comment in `.htaccess`), so
plain `http://campbuddy.test/` works out of the box with no certificate. If you want a real
`https://campbuddy.test/` locally too:

1. Install [mkcert](https://github.com/FiloSottile/mkcert) and run `mkcert -install` once.
2. `mkcert campbuddy.test` in this repo to generate `campbuddy.test.pem` / `campbuddy.test-key.pem`
   (both are gitignored — never commit local certs).
3. In WAMP: uncomment `LoadModule ssl_module modules/mod_ssl.so` and
   `Include conf/extra/httpd-ssl.conf` in `httpd.conf`, add a `<VirtualHost *:443>` block for
   `campbuddy.test` with `SSLEngine on` and the two file paths from step 2, then restart Apache.
4. `.htaccess`'s HTTPS-redirect skip only applies to `localhost`/`127.0.0.1`/`*.test`/`*.local` — once
   you're actually serving HTTPS, requests will simply arrive as HTTPS and nothing else needs to change.

This isn't done automatically because it requires editing Apache's core config and restarting a shared
system service, which isn't something to do unattended on a dev machine.

## Deploy (shared/cPanel hosting)

1. Upload the whole repo so the site's docroot (e.g. `public_html`) *is* the repo root.
2. `composer install --no-dev --optimize-autoloader` in `backend/` (or upload `backend/vendor/` from a
   build step if the host has no SSH/Composer).
3. Create a MySQL database + user in cPanel, copy `backend/.env.example` to `backend/.env`, fill in
   real `DB_*` credentials, a fresh `SESSION_SECRET` (`php -r "echo bin2hex(random_bytes(32));"`), and
   set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://campbuddy.club`.
4. Run `php backend/bin/migrate.php` and `php backend/bin/create-admin.php <username>` once via SSH or
   cPanel's "Terminal"/cron-once trick.
5. In cPanel → **Cron Jobs**, add (every 15 minutes, matching `CACHE_TTL_SECONDS`):
   ```
   */15 * * * * php /home/USER/public_html/backend/bin/refresh-event-data.php >> /home/USER/public_html/backend/storage/logs/cron.log 2>&1
   ```
   If cron is ever late or misconfigured, the app still self-heals on the next request (see
   "Server-side auto-fetch" below) — nobody sees a broken page, just a slightly stale one until cron
   catches up.
6. Confirm `.env`, `composer.json`, `*.sql` and dotfiles return 403 when requested directly, and that
   `/backend/api/v1/health` returns `{"status":"ok",...}`.

No build step is required to deploy the frontend — `styles.css` is committed and served as-is. A build
step is only needed if you're *editing styles*; see "Styling (SCSS + BEM)" below.

## Live data (server-side auto-fetch)

Event details, sponsors, agenda and Explore videos are fetched **server-side** by the backend from:
- `https://wpsimplified.in/wp-json/wpsimplified/v1/media`
- `https://wpsimplified.in/wp-json/wpsimplified/v1/events?slug=<event slug>`

on a schedule (cron, see "Deploy" above), and cached verbatim (same JSON shapes) in the `cache_store`
MySQL table. The frontend calls the backend's own `/backend/api/v1/event` and `/backend/api/v1/media`
— same-origin, no third-party call from the browser at all anymore, and no secret key ever needs to
exist client-side.

**Server-side self-healing:** every read also checks whether the cache is stale. If it is, the stale
data is still served immediately (a visitor is never blocked waiting on an outbound call), while at
most one concurrent request refetches in the background (a short single-flight lock in `cache_store`
prevents a stale-cache moment from causing a thundering herd of simultaneous outbound calls). This
means the app stays correct even if the cron job hasn't run yet on a fresh install, or is briefly
unavailable.

Confirmed response shapes (`app.js` parses these exactly, unchanged from before — the backend mirrors
them verbatim, no field-name guessing):
- `/media` → `{items:[{id, title, thumbnail, youtube_link, type}], total, total_pages, current_page}`.
- `/events?slug=...` → `{events:[{title, event_tagline, event_start_date, ..., event_social:[...],
  event_ticket_types:[...], event_sponsors:[...], event_agenda:[...], …many more}]}`.

`EXPLORE_VIDEOS_FALLBACK`/`SPONSORS_FALLBACK`/`DEMO_SCHEDULE` in `app.js` remain the client-side
backups used only if `/backend/api/v1/*` is unreachable (offline, backend down). Responses are also
still cached in `localStorage` for 6 hours as an extra layer on top of the server cache, same as
before.

### Managing the event from the admin panel

`/backend/admin` (password-protected) lets you:
- see the last auto-fetch time/status for event data and Explore videos,
- trigger an immediate refresh,
- change which WordCamp.org event slug is being pulled — **this now lives in the database, not
  `.env`**, so switching events (e.g. reusing CampBuddy for a different WordCamp) needs no deploy,
- override specific event fields (title, dates, venue, ticket URL, etc.) on top of whatever the live
  API returns, for quick manual corrections — overrides persist across auto-refreshes until cleared.

### Keeping frontend fallback data fresh

`python scripts/build-wcr-assets.py` still does two things: rebuilds `wcr/web/` images, and — network
permitting — refreshes `app.js`'s client-side fallback blocks (`EXPLORE_VIDEOS_FALLBACK`,
`SPONSORS_FALLBACK`, and select `EVENT` fields) directly from the upstream API, same as before. These
are purely the "before the backend responds for the first time, or backend is unreachable" fallbacks;
they're independent of the backend's own server-side cache.

## Privacy

Checklist, quests, interests and Camp Card data are saved to browser `localStorage` and never leave
the device except via explicit Export.

The Camp Card QR code is generated fully client-side (via the `qrcode-generator` library, loaded from
a CDN) with the WordCamp Rajasthan mark drawn into the center — nothing is uploaded to render it.
Download saves a PNG; Share uses the Web Share API where supported (falling back to download).

### Analytics

CampBuddy sends anonymous, aggregate usage events to Google Analytics 4 (`G-1YHQ19XV0P`) — route
views, feature usage (checklist/quest toggles, QR generated/downloaded/shared, mission views,
onboarding funnel, outbound link clicks), install prompt outcomes. See `track()` calls throughout
`app.js` for the full event list.

**Hard rule: Camp Card field values (name, role, "ask me about," "meet," link) are never sent to
analytics.** Those are user-typed personal info, stay in `localStorage` only, and only leave the
device if the person explicitly taps Export. This isn't just a privacy courtesy — Google Analytics'
own terms of service prohibit sending PII, so this is a requirement for using GA at all, not an
optional nicety. If you add new `track()` calls, don't pass raw user-input field values as params.

## Security

- All backend DB access uses PDO prepared statements — no string-built SQL anywhere.
- `.env` (DB creds, session secret) is never committed; `.env.example` is the template.
- `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy` and (in production, over HTTPS) `Strict-Transport-Security` are set on every
  response, both by the Slim middleware (`/backend/*`) and the root `.htaccess` (static files).
- Rate limiting: ~60 req/min/IP on the public API, 5 req/min/IP on admin login (both DB-backed), plus
  account lockout after repeated failed logins. The real scale protection is the `Cache-Control`/`ETag`
  headers on API responses (see below) — rate limiting is defense-in-depth, not the primary control.
- Admin sessions: `HttpOnly` + `SameSite=Strict` cookies (`Secure` too once served over HTTPS), session
  ID regenerated on login, idle + absolute timeouts, CSRF tokens on every state-changing admin request.
- Passwords hashed with Argon2id (`password_hash(..., PASSWORD_ARGON2ID)`).
- Errors never leak stack traces or file paths to clients in production (`APP_DEBUG=false`) — full
  detail is logged server-side instead (`backend/storage/logs/app.log`).
- Outbound calls to the upstream API have strict timeouts (5s connect / 8s total) and defensively
  validate the response shape before trusting it.
- `.env`, `composer.json/.lock`, `*.sql` and dotfiles are denied directly; every other path under
  `backend/` is swallowed by the root rewrite into the Slim app (which 404s unknown routes) before it
  can ever resolve to a raw file on disk.
- `/backend/` is disallowed in `robots.txt` and never linked from public pages.

## Scaling to ~1M users

The core idea: **the upstream third-party API is called on a cron schedule, not per-visitor.** Today's
static-only version had every browser call `wpsimplified.in` directly — at 1M visitors that's 1M
outbound calls the app has no control over. With the backend, it's one fetch every `CACHE_TTL_SECONDS`
(default 15 min) regardless of traffic; everyone else is served from MySQL.

On top of that:
- Every `/backend/api/v1/*` response sets `Cache-Control: public, max-age=300,
  stale-while-revalidate=600` and an `ETag`, so a CDN (e.g. Cloudflare's free tier, commonly available
  even on shared hosting) can absorb nearly all repeat traffic before it ever reaches PHP.
- The public API is fully stateless (only the small admin area uses sessions), so it can be moved
  behind a load balancer / multiple app servers later with no code changes.
- Reads are single indexed lookups (`cache_store.cache_key` is a unique-indexed key), no joins on the
  hot path.
- gzip compression (`mod_deflate`) and browser caching hints (`mod_expires`) are configured in the root
  `.htaccess` for static assets; enable `opcache.enable=1` in production PHP for a meaningful latency
  win at effectively zero cost.
- If you outgrow shared hosting, the cache layer sits behind a `CacheInterface` (see
  `backend/src/Cache/`) specifically so it can be swapped for Redis/APCu on a VPS without touching any
  call site.

## WordCamp Rajasthan branding

Official brand assets live in `wcr/` (source originals) and `wcr/web/` (resized, web-ready derivatives
used by the app). Regenerate the web versions with:

    python scripts/build-wcr-assets.py

The same run also refreshes app.js's live-data fallbacks (network permitting) — see "Keeping frontend
fallback data fresh" above.

The CSS palette (`--maroon`, `--navy`, `--gold`, `--teal`, `--pink`, in
`scss/abstracts/_variables.scss`) is sampled from the official WordCamp Rajasthan mark. CampBuddy's own
logo/favicon (`logo.png`, `favicon.png`) remain the app's primary identity — WCR branding is applied
through color, the Camp Card, the QR mark and the onboarding mascot rather than replacing CampBuddy's
own logo.

## Styling (SCSS + BEM)

Styles are authored in `scss/` and compiled to `styles.css` (the file `index.html` actually loads).
Don't hand-edit `styles.css` — it's a build artifact and will be overwritten.

    npm install        # once, installs the sass compiler (dev-only, not shipped)
    npm run watch       # recompiles styles.css on every save while you work
    npm run build       # one-off compressed build (commit the resulting styles.css)

Source layout:
- `scss/abstracts/` — design tokens (`:root` custom properties) and responsive mixins, no CSS output of their own.
- `scss/base/` — the reset/normalize layer.
- `scss/layout/` — page chrome: app shell, topbar, bottom nav.
- `scss/components/` — one file per BEM block (e.g. `_camp-card.scss` owns `.camp-card` and all its `__element`/`--modifier` rules), including that block's own responsive overrides nested inline rather than in a separate global media-query file.
- `scss/utilities/` — plain `u-*` helper classes (`u-row`, `u-muted`, `u-section`, …). These are a deliberate exception to BEM: generic spacing/flex/typography helpers with no component meaning of their own. Every other class in the project is `block`, `block__element`, or `block--modifier`.

Example from `_camp-card.scss`:

```scss
.camp-card {
  // block-level styles
  &__name { /* .camp-card__name */ }
  &__label {
    // element styles
    &:first-child { margin-top: 0; }
  }
}
```

## Backend tests

    cd backend
    php vendor/bin/phpunit

Covers the PHP port of `buildEventPatch()`/`sponsorsFromLive()`/`agendaFromLive()`/
`youtubeIdFromLink()`/`normalizeVideos()` against known response shapes, so the API's output stays a
faithful mirror of what `app.js` already expects.

## Sources used for the Rajasthan prototype

Organizer website:
- https://rajasthan.wordcamp.org/2026/
- https://rajasthan.wordcamp.org/2026/schedule/
- https://rajasthan.wordcamp.org/2026/contribute-learn-connect/
- https://rajasthan.wordcamp.org/2026/contact/
- https://rajasthan.wordcamp.org/2026/tickets/
- https://rajasthan.wordcamp.org/2026/sponsors/
- https://rajasthan.wordcamp.org/2026/code-of-conduct/

Verified details incorporated:
- 3–4 October 2026
- Contributor Day on 3 October
- Conference Day on 4 October
- Rajasthan International Centre, Jhalana Doongri, Jaipur
- two parallel tracks
- 20 talks & workshops
- Sponsor Hall open through Conference Day
- lunch listed for 11:30 AM–1:30 PM in Convention Hall
- Contributor Day welcomes different kinds of contributors and recommends bringing a laptop
- Ticket price ₹1,000 (both days) / ₹1,400 with after-party
- Sponsor list and tiers (Platinum/Silver/Bronze)
- Official social links (X, Instagram, Facebook, LinkedIn — all `@wprajasthan`)
- Hashtag `#WordCampRajasthan`

## License suggestion

GPL-2.0-or-later, to align naturally with the WordPress ecosystem.
