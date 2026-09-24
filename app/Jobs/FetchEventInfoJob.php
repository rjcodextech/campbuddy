<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Support\EventTime;
use App\Support\SafeSync;
use App\Services\EventInfoFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps an event's "Event Information" up to date from the event's own
 * WordCamp site (EventInfoFetcher: central.wordcamp.org + wp-json pages,
 * falling back to scraping the site's pages). Runs daily for approved and
 * active events, when an event is approved, and on demand from the admin.
 *
 * What it will and won't touch — the point of the snapshot in
 * events.info_fetched:
 *   - A field an admin typed (its value differs from what this job last
 *     wrote) is never overwritten.
 *   - A field still holding what this job wrote is refreshed — or blanked,
 *     if the source no longer has it. "Blank rather than wrong."
 *   - If no source answered at all, nothing changes: an outage must not
 *     wipe good data.
 */
class FetchEventInfoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public readonly Event $event) {}

    public function handle(): void
    {
        Cache::lock("ingest:event-info:{$this->event->id}", 180)->block(10, function () {
            $this->fetchAndStore();
        });
    }

    private function fetchAndStore(): void
    {
        $fetcher = new EventInfoFetcher($this->event->source_site_url, $this->event->display_name);

        try {
            $fetched = $fetcher->fetch();
        } catch (Throwable $e) {
            Log::warning('FetchEventInfoJob failed', ['event_id' => $this->event->id, 'error' => $e->getMessage()]);
            $this->log('error', substr($e->getMessage(), 0, 500));

            throw $e;
        }

        if (! $fetcher->reachable) {
            $this->log('error', 'Neither central.wordcamp.org nor the event site could be read — nothing changed. ('.$fetcher->sources.')');

            return;
        }

        $current = $this->event->info ?? [];
        $previous = $this->event->info_fetched ?? [];
        $info = [];
        $kept = [];

        // A field the site listed before but didn't return this time is kept
        // until a second run confirms it's gone (SafeSync) — one flaky page
        // must not wipe the venue or wifi details.
        $held = SafeSync::removals(
            "event-info:{$this->event->id}",
            array_keys(array_filter($previous, fn ($v) => filled($v))),
            array_keys(array_filter($fetched, fn ($v) => $v !== null)),
            confirmAll: true,
        )['held'];
        foreach ($held as $field) {
            $fetched[$field] = $previous[$field];
        }

        foreach (EventInfoFetcher::FIELDS as $field) {
            $value = $this->normalize($current[$field] ?? null);

            // Differs from what we last wrote → an admin put it there. Theirs to keep.
            if ($value !== null && $value !== $this->normalize($previous[$field] ?? null)) {
                $info[$field] = $value;
                $kept[] = $field;

                continue;
            }

            if ($fetched[$field] !== null) {
                $info[$field] = $fetched[$field];
            }
        }

        $this->event->update([
            'info' => $info === [] ? null : $info,
            'info_fetched' => array_filter($fetched, fn ($v) => $v !== null) ?: null,
            'info_fetched_at' => now(),
        ]);

        // Facts the central record has and the event lacks: its time zone
        // (unless an admin set one) and missing dates. Never overwrites.
        $filled = [];
        if (! $this->event->timezone_locked && ! EventTime::known($this->event) && $fetcher->timezone) {
            $filled['timezone'] = $fetcher->timezone;
        }
        foreach (['starts_on', 'ends_on'] as $date) {
            if ($this->event->{$date} === null && ($fetcher->dates[$date] ?? null)) {
                $filled[$date] = $fetcher->dates[$date];
            }
        }
        if (isset($filled['ends_on']) && ($filled['starts_on'] ?? $this->event->starts_on?->toDateString()) > $filled['ends_on']) {
            unset($filled['ends_on']);
        }
        if ($filled !== []) {
            $this->event->update($filled);
        }

        $found = count(array_filter($fetched, fn ($v) => $v !== null));

        $this->log('ok', sprintf(
            '%d of %d fields found%s (%s)',
            $found,
            count(EventInfoFetcher::FIELDS),
            ($kept === [] ? '' : '; kept your edits to: '.implode(', ', $kept))
                .($held === [] ? '' : '; not found this time, kept until the next run: '.implode(', ', $held))
                .($filled === [] ? '' : '; also set from central.wordcamp.org: '.implode(', ', array_keys($filled))),
            $fetcher->sources
        ));
    }

    private function normalize(?string $value): ?string
    {
        $value = trim(str_replace("\r\n", "\n", (string) $value));

        return $value === '' ? null : $value;
    }

    private function log(string $status, string $message): void
    {
        FetchLog::create([
            'event_id' => $this->event->id,
            'source' => 'event_info',
            'job_type' => 'event_info',
            'status' => $status,
            'message' => substr($message, 0, 500),
            'fetched_at' => now(),
        ]);
    }
}
