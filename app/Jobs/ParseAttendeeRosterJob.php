<?php

namespace App\Jobs;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Services\AttendeeRosterScraper;
use App\Support\SafeSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
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
            $response = Http::timeout(15)
                ->accept('text/html')
                ->retry(2, 1000, fn ($e) => $e instanceof ConnectionException, throw: true)
                ->get($url)
                ->throw();
            $scraper = new AttendeeRosterScraper;
            $entries = $scraper->parse($response->body());

            if ($entries === null) {
                // The .tix-attendee-list markup wasn't found at all —
                // treat as a parse failure, not an empty roster (IN4).
                $this->log('error', 'Attendees page structure not recognized — the site markup may have changed, or the page is not public yet.');

                return;
            }

            // Keyed by hash, so the same person listed twice is stored once.
            $rows = [];
            foreach ($entries as $entry) {
                $rows[$scraper->contentHash($entry['name'], $entry['links'])] = $entry;
            }
            $seenHashes = array_keys($rows);

            // One query for what's already stored instead of one per attendee.
            $existing = AttendeeRoster::where('event_id', $this->event->id)
                ->pluck('is_suppressed', 'content_hash');

            $now = now();
            $upserts = [];

            foreach ($rows as $hash => $entry) {
                // IN5: a suppressed entry stays suppressed across re-runs
                // even though its content_hash still matches — never
                // silently reactivated by the next scrape.
                if ($existing[$hash] ?? false) {
                    continue;
                }

                $upserts[] = [
                    'event_id' => $this->event->id,
                    'content_hash' => $hash,
                    'name' => mb_substr($entry['name'], 0, 191),
                    'gravatar_url' => $entry['gravatar_url'],
                    'links' => json_encode($entry['links']),
                    'is_suppressed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($upserts, 200) as $chunk) {
                AttendeeRoster::upsert($chunk, ['event_id', 'content_hash'], ['name', 'gravatar_url', 'links', 'updated_at']);
            }

            $new = collect($seenHashes)->reject(fn ($hash) => $existing->has($hash))->count();
            $pruned = $this->pruneDepartedAttendees($seenHashes);

            $this->log($pruned['held'] > 0 ? 'partial' : 'ok', sprintf(
                '%d attendees parsed%s%s%s',
                count($rows),
                $new > 0 ? ", {$new} new" : '',
                $pruned['removed'] > 0 ? ", {$pruned['removed']} no longer listed and removed" : '',
                $pruned['held'] > 0 ? ", {$pruned['held']} no longer listed — kept until the next run confirms it" : ''
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
     * the organizers) must not linger here. New and changed attendees were
     * already upserted above — nothing is deleted first. Safeguards:
     *   - an empty scrape never prunes, and a sudden big drop waits one run
     *     for confirmation (SafeSync) — a half-loaded page can't empty it;
     *   - suppressed rows are never deleted — the suppression list is what
     *     stops a removed attendee from being re-added (IN5).
     *
     * @param  array<int, string>  $seenHashes
     * @return array{removed: int, held: int}
     */
    private function pruneDepartedAttendees(array $seenHashes): array
    {
        $stored = AttendeeRoster::where('event_id', $this->event->id)
            ->where('is_suppressed', false)
            ->pluck('id', 'content_hash');

        $decision = SafeSync::removals("roster:{$this->event->id}", $stored->keys()->all(), $seenHashes);
        $staleIds = collect($decision['remove'])->map(fn ($hash) => $stored[$hash]);

        foreach ($staleIds->chunk(500) as $chunk) {
            AttendeeRoster::whereIn('id', $chunk->all())->delete();
        }

        return ['removed' => $staleIds->count(), 'held' => count($decision['held'])];
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
