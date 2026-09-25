<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Models\SessionBookmark;
use App\Support\ConcurrentWebPush;
use App\Support\EventData;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * A scheduled Web Push 5-10 minutes before a bookmarked
 * session's start. Runs every minute and is idempotent per bookmark
 * via reminder_sent_at.
 *
 * Built for a crowd: bookmarks are handled in batches of CHUNK, each batch is
 * sent with many requests in flight at once (ConcurrentWebPush), and a batch's
 * bookmarks are marked sent as soon as it is done — so a very popular session
 * gets through in seconds, and a run cut short by the host loses nothing.
 * Each push also carries a short TTL: "starting soon" is worthless once the
 * session has started, so a phone that was offline must not get it hours later
 * (the library's default TTL is four weeks).
 */
class SendSessionRemindersJob implements ShouldQueue
{
    use Queueable;

    /** How long a push service may hold an undelivered reminder. */
    public const TTL_SECONDS = 600;

    /** Bookmarks per batch: bounds memory and how many could repeat if a run is killed mid-batch. */
    private const CHUNK = 200;

    public function handle(): void
    {
        Event::where('status', 'active')->each(function (Event $event) {
            $this->remindForEvent($event);
        });
    }

    private function remindForEvent(Event $event): void
    {
        $sessions = collect(EventData::get($event->id, 'sessions') ?? [])
            ->filter(fn ($s) => $s['starts_at'])
            ->keyBy('id');

        $windowStart = now()->addMinutes(5);
        $windowEnd = now()->addMinutes(10);

        $upcomingIds = $sessions
            ->filter(function ($s) use ($windowStart, $windowEnd) {
                $startsAt = Carbon::parse($s['starts_at']);

                return $startsAt->between($windowStart, $windowEnd);
            })
            ->keys();

        if ($upcomingIds->isEmpty()) {
            return;
        }

        SessionBookmark::where('event_id', $event->id)
            ->where('reminder_enabled', true)
            ->whereNull('reminder_sent_at')
            ->whereIn('session_id', $upcomingIds)
            ->chunkById(self::CHUNK, fn (Collection $bookmarks) => $this->sendBatch($event, $bookmarks, $sessions));
    }

    /**
     * @param  Collection<int, SessionBookmark>  $bookmarks
     * @param  Collection<int|string, array<string, mixed>>  $sessions
     */
    private function sendBatch(Event $event, Collection $bookmarks, Collection $sessions): void
    {
        $subscriptions = PushSubscription::where('event_id', $event->id)
            ->whereIn('device_id', $bookmarks->pluck('device_id')->unique()->all())
            ->get()
            ->groupBy('device_id');

        $webPush = $this->webPush();
        $url = route('event.my-day', $event);
        $handled = [];

        foreach ($bookmarks as $bookmark) {
            $forDevice = $subscriptions->get($bookmark->device_id);

            // Nowhere to send it: left alone, as before.
            if ($forDevice === null || $forDevice->isEmpty()) {
                continue;
            }

            $session = $sessions[$bookmark->session_id];
            $payload = json_encode([
                'title' => $session['title'],
                'body' => 'Starting soon'.($session['track_names'][0] ?? '' ? ' in '.$session['track_names'][0] : '').'.',
                'url' => $url,
            ]);
            // Same topic = a reminder still waiting at the push service is replaced, not doubled.
            $options = ['topic' => substr(hash('sha256', 'session-'.$bookmark->session_id), 0, 24)];

            // Queued one by one so a subscription with malformed/stale keys
            // can't throw and take every other legitimate subscriber down with it.
            foreach ($forDevice as $sub) {
                try {
                    $webPush->queueNotification(
                        Subscription::create([
                            'endpoint' => $sub->endpoint,
                            'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key],
                        ]),
                        $payload,
                        $options
                    );
                } catch (\Throwable $e) {
                    Log::warning('Web Push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                }
            }

            $handled[] = $bookmark->id;
        }

        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                } else {
                    Log::warning('Web Push send failed', ['reason' => $report->getReason()]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Web Push batch failed', ['error' => $e->getMessage()]);
        }

        if ($handled !== []) {
            SessionBookmark::whereIn('id', $handled)->update(['reminder_sent_at' => now()]);
        }
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
            ['TTL' => self::TTL_SECONDS, 'urgency' => 'high'],
            app()->bound(Client::class) ? app(Client::class) : null
        );

        // One signed VAPID header per push service for the whole batch, not one per push.
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
