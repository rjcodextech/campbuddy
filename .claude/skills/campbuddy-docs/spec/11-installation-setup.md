# 11. Installation & setup

[← Back to index](../SKILL.md) · Previous: [10. API specification](10-api-specification.md) · Next: [12. Deployment →](12-deployment.md)

One Laravel app — no "frontend-only" mode without the backend, since pages are Blade-rendered.

## 11.1 Prerequisites

PHP 8.2+, Composer, Node.js (for Vite), MySQL/MariaDB, Apache with `mod_rewrite` + `AllowOverride All`.

## 11.2 First-time setup

```bash
composer install
composer require laravel/breeze --dev
php artisan breeze:install blade      # scaffolds /admin auth views (Tailwind + Alpine)
cp .env.example .env
php artisan key:generate
npm install
npm run build                          # or `npm run dev` while developing (Vite HMR)
php artisan migrate
php artisan db:seed --class=AdminUserSeeder   # creates the first admin login
php artisan db:seed --class=DemoEventSeeder   # optional, local only: demo event at /event/demo-wordcamp
php artisan campbuddy:ingest wordcamp-rajasthan-2026   # bootstraps one event: runs
                                                          # FetchBrandingAssetsJob once,
                                                          # then FetchSpeakersSponsorsSessionsJob
                                                          # and ParseAttendeeRosterJob (§5.2)
```

`.env` additions specific to V2: VAPID keypair for Web Push ([3.5](03-functional-requirements/05-notifications.md)).

## 11.3 Styling & assets

Don't hand-edit built files in `public/build/` — they're Vite output. During development, `npm run dev` watches and hot-reloads both the attendee SCSS/JS entry point and the admin Tailwind entry point ([5.5](05-system-architecture.md#55-asset-build--laravels-default-vite-pipeline)). `npm run build` produces the hashed production bundle Blade's `@vite()` directive resolves against.

## 11.4 Local WAMP setup

```apache
<VirtualHost *:80>
    ServerName campbuddy.test
    DocumentRoot "path/to/campbuddy/V2/public"
    <Directory "path/to/campbuddy/V2/public">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Visit `http://campbuddy.test/` for the attendee app and `http://campbuddy.test/admin` for the admin panel (Breeze-scaffolded login).
