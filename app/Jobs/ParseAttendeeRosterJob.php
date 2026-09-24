<?php

namespace App\Jobs;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Services\AttendeeRosterScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one recurring scraper in the pipeline — once
 * daily per active event (IN2), never concurrent with another event's run
 * (IN3). Respects the local suppression list on every re-run (IN5) and
 * alerts rather than silently ingesting garbage if the page's structure
 * has changed (IN4).
 */
class ParseAttendeeRosterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 45;

    public function __construct(public readonly Event $event) {}

    public function handle(): void
    {
        Cache::lock("ingest:roster:{$this->event->id}", 120)->block(10, function () {
            $this->fetchAndUpsert();
        });
    }

    private function fetchAndUpsert(): void
    {
        $url = rtrim($this->event->source_site_url, '/').'/attendees/';

        try {
            $response = Http::timeout(15)->get($url)->throw();
            $entries = (new AttendeeRosterScraper)->parse($response->body());

            if ($entries === null) {
                // The .tix-attendee-list markup wasn't found at all —
                // treat as a parse failure, not an empty roster (IN4).
                $this->log('error', 'Attendees page structure not recognized — the site markup may have changed.');

                return;
            }

            $seenHashes = [];

            foreach ($entries as $entry) {
                $hash = (new AttendeeRosterScraper)->contentHash($entry['name'], $entry['links']);
                $seenHashes[] = $hash;

                $existing = AttendeeRoster::where('event_id', $this->event->id)
                    ->where('content_hash', $hash)
                    ->first();

                // IN5: a suppressed entry stays suppressed across re-runs
                // even though its content_hash still matches — never
                // silently reactivated by the next scrape.
                if ($existing?->is_suppressed) {
                    continue;
                }

                AttendeeRoster::updateOrCreate(
                    ['event_id' => $this->event->id, 'content_hash' => $hash],
                    ['name' => $entry['name'], 'gravatar_url' => $entry['gravatar_url'], 'links' => $entry['links']]
                );
            }

            $removed = $this->pruneDepartedAttendees($seenHashes);

            $this->log('ok', sprintf(
                '%d attendees parsed%s',
                count($entries),
                $removed > 0 ? ", {$removed} no longer listed and removed" : ''
            ));
        } catch (Throwable $e) {
            Log::warning('ParseAttendeeRosterJob failed', ['event_id' => $this->event->id, 'error' => $e->getMessage()]);
            $this->log('error', substr($e->getMessage(), 0, 500));

            throw $e;
        }
    }

    /**
     * Keeps CampBuddy's roster a true mirror of the event's Attendees page:
     * someone who has left that page (opted out at the source, or removed by
     * the organizers) must not linger here. Two safeguards:
     *   - an empty scrape never prunes — that's more likely a page hiccup
     *     than every attendee vanishing at once, and the next run recovers;
     *   - suppressed rows are never deleted — the suppression list is what
     *     stops a removed attendee from being re-added (IN5).
     *
     * @param  array<int, string>  $seenHashes
     */
    private function pruneDepartedAttendees(array $seenHashes): int
    {
        if ($seenHashes === []) {
            return 0;
        }

        $seen = array_flip($seenHashes);

        $staleIds = AttendeeRoster::where('event_id', $this->event->id)
            ->where('is_suppressed', false)
            ->get(['id', 'content_hash'])
            ->reject(fn ($row) => isset($seen[$row->content_hash]))
            ->pluck('id');

        foreach ($staleIds->chunk(500) as $chunk) {
            AttendeeRoster::whereIn('id', $chunk->all())->delete();
        }

        return $staleIds->count();
    }

    private function log(string $status, string $message): void
    {
        FetchLog::create([
            'event_id' => $this->event->id,
            'source' => 'attendees_page',
            'job_type' => 'roster',
            'status' => $status,
            'message' => $message,
            'fetched_at' => now(),
        ]);
    }
}
