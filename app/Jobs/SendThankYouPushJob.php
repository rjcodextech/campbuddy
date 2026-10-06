<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Support\ConcurrentWebPush;
use App\Support\EventTime;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * THANK_YOU_AFTER_DAYS after a WordCamp's last day (at the venue): one push to
 * every phone that turned reminders on for it — thanks, a one-tap rating, and
 * "save your day as a PDF". Sent once per event (events.thank_you_sent_at,
 * set before sending so a crash can't double it). An event that is already
 * past the retention window when this first sees it gets nothing.
 */
class SendThankYouPushJob implements ShouldQueue
{
    use Queueable;

    /** A thank-you can wait for a phone that's off, but not past the week. */
    public const TTL_SECONDS = 86400 * 2;

    /** Sent between these hours, venue time. */
    public const FROM_HOUR = 11;

    public const UNTIL_HOUR = 19;

    public function handle(): void
    {
        Event::where('status', 'active')
            ->whereNull('thank_you_sent_at')
            ->each(function (Event $event) {
                $days = EventTime::daysSinceEnd($event);

                if ($days === null || $days < EventTime::THANK_YOU_AFTER_DAYS || $days > EventTime::RETENTION_DAYS) {
                    return;
                }

                // Daytime at the venue only: never a buzz at 3 a.m.
                $hour = (int) now()->setTimezone(EventTime::zone($event))->format('G');
                if ($hour < self::FROM_HOUR || $hour >= self::UNTIL_HOUR) {
                    return;
                }

                $event->forceFill(['thank_you_sent_at' => now()])->save();
                $this->send($event);
            });
    }

    private function send(Event $event): void
    {
        $payload = json_encode([
            'title' => "Thank you for {$event->display_name}!",
            'body' => 'How was it? Rate it in one tap, and save your day as a PDF before it goes.',
            'url' => route('event.home', $event).'?thanks=1',
        ]);
        $options = ['topic' => substr(hash('sha256', 'thank-you-'.$event->id), 0, 24)];
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
                    Log::warning('Thank-you push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                }
            }

            try {
                foreach ($webPush->flush() as $report) {
                    if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Thank-you push batch failed', ['error' => $e->getMessage()]);
            }
        });

        Log::info('Thank-you push sent', ['event' => $event->slug, 'subscriptions' => $sent]);
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
