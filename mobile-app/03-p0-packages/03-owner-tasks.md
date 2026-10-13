# Owner ke kaam

1. **Deploy** `stable` + `v2_dev` (DEPLOYMENT.md). Migrations: `2026_10_10_090000_add_memories_to_events`, `2026_10_12_090000_add_after_event_pushes_to_events`. `public/sw.js` upload zaroor.
2. `php artisan campbuddy:ga-setup`.
3. GA: Define internal traffic (apna IP) → Data filter Active.
4. wpsimplified.in ki alag GA property.
5. Organizers ko QR kit bhejo.
6. App ke [7 faisle](../02-hybrid-app/11-owner-decisions.md).

Har package: `npm run test:js`, related PHPUnit, `npm run build`; push/deploy nahi.
