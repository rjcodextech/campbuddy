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
        $differs = [];

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
                // An edit the site now disagrees with may have gone stale
                // (the admin form shows what the site says under the field).
                if ($fetched[$field] !== null && $this->normalize($fetched[$field]) !== $value) {
                    $differs[] = $field;
                }

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

        // Facts from the central record: the time zone when the event lacks one
        // (unless an admin set one), and the dates.
        $filled = [];
        if (! $this->event->timezone_locked && ! EventTime::known($this->event) && $fetcher->timezone) {
            $filled['timezone'] = $fetcher->timezone;
        }
        $filled += $this->datesFrom($fetcher->dates);
        if ($filled !== []) {
            $this->event->update($filled);
        }

        $found = count(array_filter($fetched, fn ($v) => $v !== null));

        $this->log('ok', sprintf(
            '%d of %d fields found%s (%s)',
            $found,
            count(EventInfoFetcher::FIELDS),
            ($kept === [] ? '' : '; kept your edits to: '.implode(', ', $kept))
                .($differs === [] ? '' : '; the site now says otherwise for: '.implode(', ', $differs))
                .($held === [] ? '' : '; not found this time, kept until the next run: '.implode(', ', $held))
                .($filled === [] ? '' : '; also set from central.wordcamp.org: '.implode(', ', array_keys($filled))),
            $fetcher->sources
        ));
    }

    /**
     * The date changes to make, from the dates organizers registered on
     * central.wordcamp.org. Dates nobody typed by hand follow central, so a
     * discovered date that was a day off, or a date the organizers moved, is
     * put right by the next daily run. Dates typed by an admin or manager
     * (dates_locked) are only ever filled in where they are blank.
     *
     * Central's end date may simply not be filled in yet, so a missing one
     * never clears ours, unless ours would now fall before the start.
     *
     * @param  array{starts_on: ?string, ends_on: ?string}  $central
     * @return array<string, ?string>
     */
    private function datesFrom(array $central): array
    {
        $current = ['starts_on' => $this->event->starts_on?->toDateString(), 'ends_on' => $this->event->ends_on?->toDateString()];
        $wanted = $current;

        if ($this->event->dates_locked) {
            foreach ($wanted as $date => $value) {
                $wanted[$date] = $value ?? $central[$date] ?? null;
            }
        } elseif ($central['starts_on'] !== null) {
            $wanted['starts_on'] = $central['starts_on'];
            $wanted['ends_on'] = $central['ends_on'] ?? $current['ends_on'];
        }

        if ($wanted['ends_on'] !== null && $wanted['starts_on'] !== null && $wanted['ends_on'] < $wanted['starts_on']) {
            $wanted['ends_on'] = null;
        }

        return array_filter($wanted, fn (?string $value, string $date) => $value !== $current[$date], ARRAY_FILTER_USE_BOTH);
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
