<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Support\ConcurrentWebPush;
use App\Support\EventCountry;
use App\Support\EventTime;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends, right now, one push to every phone with reminders on about the next
 * WordCamp in its own country. A phone's country is the country of the
 * WordCamp it last turned reminders on for (EventCountry). "Next" is the
 * soonest live WordCamp that hasn't started yet at its venue, within --within
 * days, and that the phone isn't already following. Nothing is stored: each
 * run sends again.
 *
 *   php artisan campbuddy:upcoming-push --dry-run          # who would get what
 *   php artisan campbuddy:upcoming-push --yes              # send, no prompt
 *   php artisan campbuddy:upcoming-push --country=IN
 *   php artisan campbuddy:upcoming-push --event=wordcamp-delhi-2026   # this one, to its country
 */
class SendUpcomingPushCommand extends Command
{
    protected $signature = 'campbuddy:upcoming-push
        {--country= : Only phones in this country (2-letter code)}
        {--event= : Tell about this WordCamp (slug) instead of each country\'s next one}
        {--within=90 : Only WordCamps starting in the next N days}
        {--dry-run : List what would be sent, send nothing}
        {--yes : Send without asking}';

    protected $description = 'Push every phone about the next WordCamp in its own country';

    public const TTL_SECONDS = 86400;

    public function handle(): int
    {
        $country = $this->option('country') ? strtoupper($this->option('country')) : null;
        $until = CarbonImmutable::today()->addDays(max(0, (int) $this->option('within')))->toDateString();

        // Live WordCamps that haven't started at their venue, soonest first.
        $upcoming = Event::where('status', 'active')
            ->where('is_visible', true)
            ->when($this->option('event'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get()
            ->tap(fn ($found) => EventTime::primeSessionDays($found))
            ->map(fn (Event $event) => ['event' => $event, 'first' => EventTime::firstDay($event), 'country' => EventCountry::code($event)])
            ->filter(fn ($row) => $row['first'] !== null && $row['country'] !== null
                && $row['first'] > EventTime::today($row['event'])
                && ($this->option('event') || $row['first'] <= $until))
            ->sortBy(fn ($row) => $row['first'].'|'.str_pad((string) $row['event']->id, 10, '0', STR_PAD_LEFT))
            ->groupBy('country');

        if ($upcoming->isEmpty()) {
            $this->info('No upcoming WordCamp found.');

            return self::SUCCESS;
        }

        // Each phone: its country, the WordCamps it already follows, its endpoints.
        $targets = [];
        PushSubscription::with('event')->orderBy('updated_at')->orderBy('id')->get()
            ->groupBy('device_id')
            ->each(function ($subs) use ($upcoming, $country, &$targets) {
                $home = $subs->reverse()->map(fn ($s) => $s->event ? EventCountry::code($s->event) : null)->filter()->first();

                if ($home === null || ($country && $home !== $country) || ! $upcoming->has($home)) {
                    return;
                }

                $following = $subs->pluck('event_id')->all();
                $next = $upcoming[$home]->first(fn ($row) => ! in_array($row['event']->id, $following, true));

                if ($next) {
                    foreach ($subs as $sub) {
                        $targets[$next['event']->id][] = $sub;
                    }
                }
            });

        if ($targets === []) {
            $this->info('No phones to tell (none in those countries, or they already follow it).');

            return self::SUCCESS;
        }

        $events = $upcoming->flatten(1)->keyBy(fn ($row) => $row['event']->id);
        $total = 0;

        foreach ($targets as $eventId => $subs) {
            $row = $events[$eventId];
            $this->line(sprintf('  %s  %-40s starts %s  %d phone(s)', $row['country'], $row['event']->slug, $row['first'], count($subs)));
            $total += count($subs);
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

        foreach ($targets as $eventId => $subs) {
            $event = $events[$eventId]['event'];
            $payload = json_encode([
                'title' => "{$event->display_name} is coming up",
                'body' => 'On '.$this->dates($event).'. See the schedule and plan your day.',
                'url' => route('event.home', $event),
            ]);
            $options = ['topic' => substr(hash('sha256', 'upcoming-'.$event->id), 0, 24)];

            foreach (array_chunk($subs, 200) as $chunk) {
                foreach ($chunk as $sub) {
                    try {
                        $webPush->queueNotification(
                            Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key]]),
                            $payload,
                            $options
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Upcoming push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
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
                    Log::warning('Upcoming push batch failed', ['error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Upcoming push sent', ['sent' => $sent, 'expired' => $expired, 'targets' => $total]);
        $this->info("{$sent} of {$total} delivered, {$expired} expired subscription(s) removed.");

        return self::SUCCESS;
    }

    /** "3 Oct" or "3–4 Oct" or "31 Oct – 1 Nov". */
    private function dates(Event $event): string
    {
        $first = CarbonImmutable::parse(EventTime::firstDay($event));
        $last = CarbonImmutable::parse(EventTime::lastDay($event) ?? $first->toDateString());

        if ($first->isSameDay($last)) {
            return $first->format('j M');
        }

        return $first->isSameMonth($last)
            ? $first->format('j').'–'.$last->format('j M')
            : $first->format('j M').' – '.$last->format('j M');
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
