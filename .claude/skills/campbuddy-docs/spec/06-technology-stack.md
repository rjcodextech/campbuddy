# 6. Technology stack

[← Back to index](../SKILL.md) · Previous: [5. System architecture](05-system-architecture.md) · Next: [7. Data model →](07-data-model.md)

| Layer | Technology |
|---|---|
| App structure | **One Laravel app** — no separate frontend/backend repos or mount paths ([5](05-system-architecture.md)) |
| Templating | **Blade** — attendee pages rendered server-side ([5.1](05-system-architecture.md#51-request-flow-public-pages--updated-for-server-rendering)) |
| Frontend assets | Vite ([5.5](05-system-architecture.md#55-asset-build--laravels-default-vite-pipeline)) — attendee app keeps vanilla JS + hand-written BEM/SCSS; admin panel uses Breeze's Tailwind + Alpine, scoped to `/admin` only |
| Frontend libraries | `qrcode-generator`; Web Push client via the browser's native Push API |
| Backend framework | **Laravel 12** |
| Auth scaffolding | **Laravel Breeze** for `/admin` — session-based, Argon2id-capable hasher |
| Backend language | PHP 8.2+ |
| Queues/Scheduler | Laravel's built-in Queue (database driver, no Redis dependency added) + Scheduler for cron-equivalent jobs |
| REST ingestion | Laravel's HTTP client against each event's own `/wp-json/wp/v2/{sessions,speakers,sponsors,organizers,session_track,sponsor_level}` — the primary, structured ingestion path ([finding 0.1](00-findings.md)) |
| HTML parsing | `symfony/dom-crawler` — scoped narrowly to the Attendees-page scraper ([finding 0.3](00-findings.md)), the speaker-bio social-link extraction, and (added post-launch) the `events.wordpress.org` discovery listing — not general-purpose scraping |
| Web Push | `minishlink/web-push` (VAPID) |
| Database | MySQL/MariaDB (utf8mb4) |
| Web server | Apache 2.4 + `mod_rewrite`, shared-hosting target, behind Cloudflare ([12](12-deployment.md) documents the docroot workaround) |
| Local dev | WAMP (Apache + MySQL + PHP) — vhost `DocumentRoot` points at Laravel's `public/` folder ([11.4](11-installation-setup.md#114-local-wamp-setup)) |
| Testing | Pest/PHPUnit — see [15](15-testing.md) |
| CDN | Cloudflare (or equivalent) — mandatory |
| Analytics | GA4, PII-excluded (see [8.5](08-security-privacy.md#85-analytics-guardrail)) |
