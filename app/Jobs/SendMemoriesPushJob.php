<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Support\ConcurrentWebPush;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * One Memories push (App\Support\EventMemories) — "the photos are up" or "the
 * talks are on WordPress.tv" — to every phone that turned reminders on for the
 * event. Queued by EventMemories::notify, which has already marked it sent.
 * Opens the event's Memories page, not the raw link.
 */
class SendMemoriesPushJob implements ShouldQueue
{
    use Queueable;

    /** A phone that's off still gets it within two days. */
    public const TTL_SECONDS = 86400 * 2;

    public function __construct(public int $eventId, public string $kind) {}

    public function handle(): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            return;
        }

        $payload = json_encode($this->kind === 'photos'
            ? [
                'title' => "Photos from {$event->display_name} are up",
                'body' => 'Find yourself in the official photo album.',
                'url' => route('event.memories', $event).'?from=photos',
            ]
            : [
                'title' => "{$event->display_name} talks are on WordPress.tv",
                'body' => 'Missed a session, or want to watch one again? The recordings are up.',
                'url' => route('event.memories', $event).'?from=videos',
            ]);
        $options = ['topic' => substr(hash('sha256', 'memories-'.$this->kind.'-'.$event->id), 0, 24)];
        $webPush = $this->webPush();
        $sent = 0;

        PushSubscription::where('event_id', $event->id)->orderBy('id')->chunkById(200, function ($subs) use ($webPush, $payload, $options, &$sent) {
            foreach ($subs as $sub) {
                try {
                    $webPush->queueNotification(
                        Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key]]),
                        $payload,
                        $options
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Memories push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                }
            }

            try {
                foreach ($webPush->flush() as $report) {
                    if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Memories push batch failed', ['error' => $e->getMessage()]);
            }
        });

        Log::info('Memories push sent', ['event' => $event->slug, 'kind' => $this->kind, 'subscriptions' => $sent]);
    }

    protected function webPush(): WebPush
    {
        $webPush = new ConcurrentWebPush(
            [
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ],
            ],
            ['TTL' => self::TTL_SECONDS],
            app()->bound(Client::class) ? app(Client::class) : null
        );
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
