<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Support\ConcurrentWebPush;
use App\Support\EventTime;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends, right now, a "save your data" push to every phone with reminders on,
 * about the last WordCamp it was at that ended in the past RETENTION_DAYS.
 * A phone (device_id) subscribed to more than one gets only the latest one.
 * Unlike the day-3 thank-you push, nothing is checked or stored: no venue
 * hours, no "sent once" — each run sends again.
 * The link (?export=1) opens the thank-you card with its PDF button even on
 * a phone that already closed it (thank-you.js).
 *
 *   php artisan campbuddy:export-push --dry-run          # who would get what
 *   php artisan campbuddy:export-push --yes              # send, no prompt
 *   php artisan campbuddy:export-push --event=wordcamp-delhi-2026
 */
class SendExportPushCommand extends Command
{
    protected $signature = 'campbuddy:export-push
        {--event= : Only this WordCamp (slug)}
        {--dry-run : List what would be sent, send nothing}
        {--yes : Send without asking}';

    protected $description = 'Push every phone to save its data from the last WordCamp it was at (past 7 days)';

    /** Data goes at the end of the week, so the push need not wait longer than a day. */
    public const TTL_SECONDS = 86400;

    public function handle(): int
    {
        $events = Event::where('status', 'active')
            ->when($this->option('event'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get()
            ->mapWithKeys(fn (Event $event) => [$event->id => [$event, EventTime::daysSinceEnd($event)]])
            ->filter(fn ($row) => $row[1] !== null && $row[1] >= 1 && $row[1] <= EventTime::RETENTION_DAYS);

        if ($events->isEmpty()) {
            $this->info('No WordCamp ended in the past '.EventTime::RETENTION_DAYS.' days.');

            return self::SUCCESS;
        }

        // Each phone's latest WordCamp: fewest days since it ended.
        $subs = PushSubscription::whereIn('event_id', $events->keys())->orderBy('id')->get();
        $latest = $subs->groupBy('device_id')->map(fn ($rows) => $rows->min(fn ($s) => $events[$s->event_id][1]));
        $targets = $subs
            ->filter(fn ($s) => $events[$s->event_id][1] === $latest[$s->device_id])
            ->groupBy('event_id');

        foreach ($targets as $eventId => $rows) {
            [$event, $days] = $events[$eventId];
            $this->line(sprintf('  %-40s ended %d day(s) ago  %d phone(s)', $event->slug, $days, $rows->count()));
        }

        $total = $targets->flatten()->count();

        if ($total === 0) {
            $this->info('No phones with reminders on for those WordCamps.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$total} push(es) would be sent.");

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm("Send {$total} push(es) now?")) {
            $this->warn('Nothing sent.');

            return self::FAILURE;
        }

        $webPush = $this->webPush();
        $sent = 0;
        $expired = 0;

        foreach ($targets as $eventId => $rows) {
            $event = $events[$eventId][0];
            $payload = json_encode([
                'title' => "Save your {$event->display_name} data",
                'body' => 'Your schedule, notes and people stay on this phone for a few more days. Save them as a PDF.',
                'url' => route('event.home', $event).'?export=1',
            ]);
            $options = ['topic' => substr(hash('sha256', 'export-'.$event->id), 0, 24)];

            foreach ($rows->chunk(200) as $chunk) {
                foreach ($chunk as $sub) {
                    try {
                        $webPush->queueNotification(
                            Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key]]),
                            $payload,
                            $options
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Export push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                    }
                }

                try {
                    foreach ($webPush->flush() as $report) {
                        if ($report->isSuccess()) {
                            $sent++;
                        } elseif ($report->isSubscriptionExpired()) {
                            PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                            $expired++;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Export push batch failed', ['error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Export push sent', ['sent' => $sent, 'expired' => $expired, 'targets' => $total]);
        $this->info("{$sent} of {$total} delivered, {$expired} expired subscription(s) removed.");

        return self::SUCCESS;
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
