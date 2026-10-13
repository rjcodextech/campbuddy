# Backend changes (sab additive)

| Change | Detail |
|---|---|
| `/.well-known/assetlinks.json`, `apple-app-site-association` | route ya static; Cloudflare cache OK |
| `native_push_tokens` table | event_id, platform, fcm_token, discovery_id nullable, timestamps. `push_subscriptions` ko nahi chhuna |
| `POST /api/v1/events/{slug}/push/native-subscribe` | naya, `throttle:api-writes` |
| `NativePushSender` | FCM HTTP v1, service-account JSON `.env` mein |
| Existing push jobs mein native bhi | `SendThankYouPushJob`, `SendMemoriesPushJob`, `SendAfterEventPushesJob` (day 7/15), `SendUpcomingPushCommand` — existing code change → do popup |
| Notifications tab | `EventNotifications` mein native count bhi |
| Retention | native tokens = event ki retention (**30 din**) |
| Report / block | Discovery ke liye naya endpoint + admin queue (alag package) |
| Ek device = ek channel | app mein web push register nahi hoga |

FCM v1: ek request = ek token → 10K users ko chhote queue chunks + rate limit; cron ka `queue:work` hi.
