# 12. Deployment (shared/cPanel hosting, behind Cloudflare)

[← Back to index](../SKILL.md) · Previous: [11. Installation & setup](11-installation-setup.md) · Next: [13. Pre-launch blockers →](13-prelaunch-blockers.md)

**Docroot workaround:** Laravel expects `public/` to *be* the docroot, but many cPanel accounts can't point a domain's document root at a subfolder. Check first — some hosts allow changing it under cPanel → Domains. If not: upload the whole app **above** the public web root, then copy `public/index.php` and `public/.htaccess` into `public_html` and edit the two `require`/`bootstrap` paths in `index.php` to point up to the real `vendor/autoload.php` and `bootstrap/app.php`. This keeps `.env`, `app/`, `database/`, etc. outside the web-servable directory entirely.

1. Provision Cloudflare (or equivalent) in front of the domain — **do this before go-live, not after** ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)).
2. Upload the app per the docroot approach decided above.
3. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
4. Configure `.env`: DB credentials, fresh `APP_KEY`, VAPID keypair for push, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain`.
5. `php artisan migrate --force`, seed the admin user, then `php artisan campbuddy:ingest wordcamp-rajasthan-2026` to pull in branding, speakers/sponsors/sessions, and the attendee roster for the first time.
6. cPanel Cron needs **exactly one entry**, running every minute — Laravel's scheduler dispatches each job on its own configured cadence internally:
   ```
   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
   ```
   See [5.2](05-system-architecture.md#52-ingestion-flow) for each job's actual cadence (post-launch additions included: discovery every 2 days, lifecycle sweep daily).
7. Confirm Cloudflare is actually caching page and API responses (check `cf-cache-status` header) before calling this done. Re-check the CSRF/cacheability caveat from [5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional) while doing this.
8. Confirm `.env`, `composer.json`, `*.sql`, and everything outside `public/` returns 404/403 (or isn't reachable at all, if using the docroot workaround); `/api/v1/health` returns OK.
