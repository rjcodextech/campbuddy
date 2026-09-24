# CampBuddy V2

CampBuddy is a mobile-first, local-first companion app for WordCamp attendees — a Laravel-backed PWA that turns a busy schedule, a venue full of strangers, and a list of sponsors into simple, timely guidance for what to do next.

**Full documentation has moved** — it's now a set of small, cross-linked, topic-focused files instead of one long document:

- **Product overview:** [`.claude/skills/campbuddy-docs/about/`](.claude/skills/campbuddy-docs/about/01-overview.md) — what CampBuddy is, who it's for, why it's different.
- **Technical spec:** [`.claude/skills/campbuddy-docs/spec/`](.claude/skills/campbuddy-docs/spec/00-findings.md) — functional/non-functional requirements, architecture, data model, security, admin panel, API, deployment.
- **Start here:** [`.claude/skills/campbuddy-docs/SKILL.md`](.claude/skills/campbuddy-docs/SKILL.md) — the index for everything above.

## Quick start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
npm run build
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=DemoEventSeeder   # optional, local only: a full demo WordCamp at /event/demo-wordcamp
```

`migrate` also creates the default Quest "Things to do". The demo seeder builds a realistic event (schedule timed around *now*, speakers, sponsors, attendees, deals) through the real ingestion normalizer — handy for design review and walkthrough testing without network access. Never run it on production: it creates a public event.

See [installation & setup](.claude/skills/campbuddy-docs/spec/11-installation-setup.md) for the full walkthrough (WAMP config, `.env` values, first event ingest) and [deployment](.claude/skills/campbuddy-docs/spec/12-deployment.md) for shared/cPanel hosting behind Cloudflare.

**License:** GPL-2.0-or-later.
