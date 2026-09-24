<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Models\SessionBookmark;
use App\Support\EventData;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * A scheduled Web Push 5-10 minutes before a bookmarked
 * session's start. Runs every minute and is idempotent per bookmark
 * via reminder_sent_at.
 */
class SendSessionRemindersJob implements ShouldQueue
{
    use Queueable;

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

        $bookmarks = SessionBookmark::where('event_id', $event->id)
            ->where('reminder_enabled', true)
            ->whereNull('reminder_sent_at')
            ->whereIn('session_id', $upcomingIds)
            ->get();

        foreach ($bookmarks as $bookmark) {
            $this->sendFor($event, $bookmark, $sessions[$bookmark->session_id]);
        }
    }

    private function sendFor(Event $event, SessionBookmark $bookmark, array $session): void
    {
        $subscriptions = PushSubscription::where('event_id', $event->id)
            ->where('device_id', $bookmark->device_id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => $session['title'],
            'body' => 'Starting soon'.($session['track_names'][0] ?? '' ? ' in '.$session['track_names'][0] : '').'.',
            'url' => route('event.my-day', $event),
        ]);

        // Sent one at a time (not queueNotification + flush) so that one
        // subscription with malformed/stale keys can't throw mid-batch
        // and take every other legitimate subscriber down with it.
        foreach ($subscriptions as $sub) {
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key],
                    ]),
                    $payload
                );

                if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                    $sub->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('Web Push send failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $bookmark->update(['reminder_sent_at' => now()]);
    }
}
