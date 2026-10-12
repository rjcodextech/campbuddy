<?php

namespace App\Support;

use App\Models\Event;
use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * One push to every phone that turned reminders on for an event, and the
 * event's push log (`events.push_log`: kind => when, how many phones) that the
 * admin's Notifications tab reads. Expired subscriptions are removed as the
 * push service reports them.
 */
class EventPush
{
    /** @return int phones the push was handed to */
    public static function send(Event $event, array $payload, string $topic, int $ttl): int
    {
        $webPush = self::webPush($ttl);
        $body = json_encode($payload);
        $options = ['topic' => substr(hash('sha256', $topic.'-'.$event->id), 0, 24)];
        $sent = 0;

        PushSubscription::where('event_id', $event->id)->orderBy('id')->chunkById(200, function ($subs) use ($webPush, $body, $options, &$sent) {
            foreach ($subs as $sub) {
                try {
                    $webPush->queueNotification(
                        Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key]]),
                        $body,
                        $options
                    );
                    $sent++;
                } catch (Throwable $e) {
                    Log::warning('Push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                }
            }

            try {
                foreach ($webPush->flush() as $report) {
                    if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Push batch failed', ['error' => $e->getMessage()]);
            }
        });

        return $sent;
    }

    /** Notes a push sent about the event, for the Notifications tab. */
    public static function log(Event $event, string $kind, int $phones): void
    {
        try {
            $log = is_array($event->fresh()?->push_log) ? $event->fresh()->push_log : [];
            $log[$kind] = ['at' => now()->toIso8601String(), 'phones' => $phones];
            $event->forceFill(['push_log' => $log])->saveQuietly();
        } catch (Throwable $e) {
            // Before the migration: the push still went.
            report($e);
        }
    }

    private static function webPush(int $ttl): WebPush
    {
        $webPush = new ConcurrentWebPush(
            [
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ],
            ],
            ['TTL' => $ttl],
            app()->bound(Client::class) ? app(Client::class) : null
        );
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
