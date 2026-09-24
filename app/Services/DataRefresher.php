<?php

namespace App\Services;

use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\FetchLog;
use Throwable;

/**
 * The admin "Refresh event data" action: fetch every live event's schedule,
 * speakers, sponsors and event information from its WordCamp site now,
 * instead of waiting for the scheduler. It clears no cache.
 *
 * Each fetch merges into what's stored (EventData::sync, SafeSync): new items
 * added, changed ones updated, gone ones removed — never cleared first — so
 * a site that's down or half-loaded leaves the last good data in place.
 * Open and installed apps notice the new data by themselves
 * (DataVersion, data-freshness.js).
 *
 * The attendee roster isn't included: it's a scrape of someone else's site,
 * limited to once a day (IN2) — each event's page has its own button for it.
 */
class DataRefresher
{
    /** Kept generous, but well inside Cloudflare's ~100 s origin timeout. */
    private const SYNC_BUDGET_SECONDS = 30;

    /**
     * @return array{message: string, events: array<string, array<int, string>>}
     */
    public function refresh(): array
    {
        @set_time_limit(180);

        $events = $this->refreshEvents(microtime(true), now()->subSecond());

        return ['message' => $this->summarise($events), 'events' => $events];
    }

    /**
     * Refreshes each live event now, while the time budget lasts; whatever's
     * left over goes to the queue (or waits for the next scheduled run if
     * there is no real queue).
     *
     * @return array<string, array<int, string>>
     */
    protected function refreshEvents(float $startedAt, \DateTimeInterface $runStartedAt): array
    {
        $result = ['refreshed' => [], 'queued' => [], 'failed' => [], 'skipped' => []];
        $hasQueue = config('queue.default') !== 'sync';

        $events = Event::whereIn('status', ['approved', 'active'])->orderBy('starts_on')->orderBy('id')->get();

        foreach ($events as $event) {
            $jobs = $event->status === 'active'
                ? [FetchSpeakersSponsorsSessionsJob::class, FetchEventInfoJob::class]
                : [FetchEventInfoJob::class];

            if (microtime(true) - $startedAt < self::SYNC_BUDGET_SECONDS) {
                foreach ($jobs as $job) {
                    try {
                        $job::dispatchSync($event);
                    } catch (Throwable) {
                        // The job logged its own failure; the log check below reports it.
                    }
                }

                $failed = FetchLog::where('event_id', $event->id)
                    ->where('status', 'error')
                    ->where('fetched_at', '>=', $runStartedAt)
                    ->exists();

                $result[$failed ? 'failed' : 'refreshed'][] = $event->display_name;
            } elseif ($hasQueue) {
                foreach ($jobs as $job) {
                    $job::dispatch($event);
                }

                $result['queued'][] = $event->display_name;
            } else {
                $result['skipped'][] = $event->display_name;
            }
        }

        return $result;
    }

    /** @param  array<string, array<int, string>>  $events */
    private function summarise(array $events): string
    {
        if (array_sum(array_map('count', $events)) === 0) {
            return 'No live events to refresh.';
        }

        $refreshed = count($events['refreshed']);
        $parts = ["Fresh data fetched for {$refreshed} ".str('event')->plural($refreshed)];

        if ($events['queued'] !== []) {
            $parts[] = count($events['queued']).' more queued (ready within a minute or two)';
        }

        if ($events['skipped'] !== []) {
            $parts[] = count($events['skipped']).' left for the next scheduled refresh';
        }

        if ($events['failed'] !== []) {
            $parts[] = 'couldn\'t fully refresh '.implode(', ', $events['failed']).' (its WordCamp site didn\'t answer as expected) — the last good data is still showing';
        }

        $parts[] = 'open apps update by themselves';

        return implode(' · ', $parts).'.';
    }
}
