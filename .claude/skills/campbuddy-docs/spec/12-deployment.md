# 12. Deployment (shared/cPanel hosting, behind Cloudflare)

[← Back to index](../SKILL.md) · Previous: [11. Installation & setup](11-installation-setup.md) · Next: [13. Pre-launch blockers →](13-prelaunch-blockers.md)

**Docroot workaround:** Laravel expects `public/` to *be* the docroot, but many cPanel accounts can't point a domain's document root at a subfolder. Check first — some hosts allow changing it under cPanel → Domains. If not: upload the whole app **above** the public web root, then copy `public/index.php` and `public/.htaccess` into `public_html` and edit the two `require`/`bootstrap` paths in `index.php` to point up to the real `vendor/autoload.php` and `bootstrap/app.php`. This keeps `.env`, `app/`, `database/`, etc. outside the web-servable directory entirely.

**…and tell Laravel where the web root now is.** In that `public_html/index.php`, right after the app is created (`$app = require_once …/bootstrap/app.php;`), add:

```php
$app->usePublicPath(__DIR__);
```

Without it Laravel still thinks `public/` (inside the app folder) is the public path, so the `storage` link ends up *there* — not in `public_html` — and every uploaded or auto-fetched event logo 404s. (This was the production logo bug: the link existed in the local `public/` folder but never on the live site.) With the line in place, the web side — including the Purge-cache button's best-effort attempt — creates `public_html/storage`.

`index.php` is only used by web requests, so **`php artisan storage:link` run from SSH still targets the app's own `public/`**. From a shell, create the link yourself, pointing at the app's `storage/app/public` (adjust the path to where the app sits):

```
ln -s ../campbuddy/storage/app/public public_html/storage
```

> **Current implementation note — logos work even with no link.** `/storage/{path}` is also a Laravel route (`PublicStorageController`): when the web server can't find the file itself (no symlink, or `symlink()` disabled by the host) the request falls through to the app, which serves the image from `storage/app/public` — images only (png/jpg/gif/webp/avif/ico/svg), path-traversal safe, `nosniff`, SVGs with a script-blocking CSP. So a missing link costs a little speed, never the logos. Event image URLs are root-relative (`/storage/branding/1/logo.png?v=<mtime>`), independent of `APP_URL`.

1. Provision Cloudflare (or equivalent) in front of the domain — **do this before go-live, not after** ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)).
2. Upload the app per the docroot approach decided above (including `usePublicPath`).
3. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, then the storage link (see above — `php artisan storage:link` on a standard docroot, the `ln -s` for the `public_html` workaround; optional, the app serves logos without it).
4. Configure `.env`: DB credentials, fresh `APP_KEY`, VAPID keypair for push, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain` (an `https://` URL also forces every generated link to https), `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=database`. Optional: `APP_TIMEZONE=…` (the scheduler's "midnight" — the nightly attendee-roster refresh — is midnight in this zone; default UTC) and `CLOUDFLARE_ZONE_ID` + `CLOUDFLARE_API_TOKEN` (lets the admin's Purge-cache button purge Cloudflare too; token scope: *Zone → Cache Purge → Purge* on this one zone, nothing else).
5. `php artisan migrate --force`, seed the admin user, then `php artisan campbuddy:ingest wordcamp-rajasthan-2026` to pull in branding, speakers/sponsors/sessions, and the attendee roster for the first time. (Any event an admin approves/activates afterwards is fetched automatically — [5.2](05-system-architecture.md#52-ingestion-flow).)
6. cPanel Cron needs **exactly one entry**, running every minute — Laravel's scheduler dispatches each job on its own configured cadence internally, **and drains the queue itself** (its last task, `work-queue`), so no separate queue worker is needed:
   ```
   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
   ```
   **If this cron isn't running, nothing is ever fetched** — no branding, no schedule refreshes, no reminders — because every job is only queued until the scheduler works the queue. See [5.2](05-system-architecture.md#52-ingestion-flow) for each job's actual cadence (discovery every 2 days, lifecycle sweep 01:00, roster midnight, event info 02:00, branding backfill 02:30, sessions every 15 minutes).
7. Confirm Cloudflare is actually caching page and API responses (check `cf-cache-status` header) before calling this done. Re-check the CSRF/cacheability caveat from [5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional) while doing this. `/api/v1/cache-version` sends `no-store` and must not be cached by any Cloudflare rule. The app trusts proxy headers (`trustProxies(at: '*')`), so per-IP rate limits see each visitor's real IP rather than Cloudflare's edge — keep the origin reachable only through Cloudflare if you can.
8. Confirm `.env`, `composer.json`, `*.sql`, and everything outside `public/` returns 404/403 (or isn't reachable at all, if using the docroot workaround); `/api/v1/health` returns OK.
9. Smoke test after go-live: the picker shows each event's logo/favicon (not CampBuddy's icon); the tab title reads *"CampBuddy | Your WordCamp companion"* on `/` and *"{Tab} | {Event} | CampBuddy"* inside an event; `/manifest.webmanifest` returns 200 with `name` *"CampBuddy | Your WordCamp Companion"* and `short_name` *"CampBuddy"*; Dashboard → **Purge cache & refresh data** completes and reports "fresh data fetched for N events".

**Stale data or a stale logo after a deploy or an upstream change?** Dashboard → *Cache & fresh data* → **Purge cache & refresh data** ([9](09-admin-panel.md)) — it's the supported equivalent of `optimize:clear` + re-ingest + a Cloudflare purge + telling every installed PWA to reload.
