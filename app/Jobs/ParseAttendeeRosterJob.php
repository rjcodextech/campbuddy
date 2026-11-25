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
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $baseUrl = rtrim($this->event->source_site_url, '/').'/attendees/';

        try {
            $scraper = new AttendeeRosterScraper;
            $entries = [];
            $visited = [];
            $nextUrl = $baseUrl;
            $maxPages = 20;

            for ($page = 1; $nextUrl !== null && $page <= $maxPages; $page++) {
                if (isset($visited[$nextUrl])) {
                    break;
                }
                $visited[$nextUrl] = true;

                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'CampBuddy/1.0 (+https://campbuddy.club)'])
                    ->accept('text/html')
                    ->retry(2, 1000, fn ($e) => $e instanceof ConnectionException || ($e instanceof RequestException && ($e->response?->serverError() || $e->response?->status() === 429)), throw: true)
                    ->get($nextUrl)
                    ->throw();
                $body = $response->body();

                if (trim($body) === '') {
                    if ($page === 1) {
                        $this->log('error', 'Attendees page returned an empty response — keeping last known roster intact.');

                        return;
                    }
                    break;
                }

                $pageEntries = $scraper->parse($body);
                if ($pageEntries === null) {
                    if ($page === 1) {
                        // The .tix-attendee-list markup wasn't found at all —
                        // treat as a parse failure, not an empty roster (IN4).
                        $this->log('error', 'Attendees page structure not recognized — the site markup may have changed, or the page is not public yet.');

                        return;
                    }
                    break;
                }

                array_push($entries, ...$pageEntries);
                $nextUrl = $this->nextRosterPageUrl($body, $baseUrl);
            }

            // Keyed by hash, so the same person listed twice is stored once — a
            // microsponsor if either listing says so (their block and the main list).
            $rows = [];
            foreach ($entries as $entry) {
                $hash = $scraper->contentHash($entry['raw_name'] ?? $entry['name'], $entry['links']);
                $entry['microsponsor'] = ($entry['microsponsor'] ?? false) || ($rows[$hash]['microsponsor'] ?? false);
                $rows[$hash] = $entry;
            }
            $seenHashes = array_keys($rows);

            // One query for what's already stored instead of one per attendee.
            $existing = AttendeeRoster::where('event_id', $this->event->id)
                ->pluck('is_suppressed', 'content_hash');

            $now = now();
            $upserts = [];
            // Before `php artisan migrate` has added the column, the roster still refreshes, without the mark.
            $withMicrosponsor = Schema::hasColumn('attendee_roster', 'is_microsponsor');

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
                ] + ($withMicrosponsor ? ['is_microsponsor' => $entry['microsponsor']] : []);
            }

            $updated = ['name', 'gravatar_url', 'links', 'updated_at', ...($withMicrosponsor ? ['is_microsponsor'] : [])];
            foreach (array_chunk($upserts, 200) as $chunk) {
                AttendeeRoster::upsert($chunk, ['event_id', 'content_hash'], $updated);
            }

            $new = collect($seenHashes)->reject(fn ($hash) => $existing->has($hash))->count();
            $pruned = $this->pruneDepartedAttendees($seenHashes);

            if ($upserts !== [] || $pruned['removed'] > 0) {
                \App\Http\Controllers\Api\RosterController::forget($this->event);
                \App\Support\RosterRoles::forget($this->event);
                \App\Support\DataVersion::forget($this->event->id);
            }

            $this->log($pruned['held'] > 0 ? 'partial' : 'ok', sprintf(
                '%d attendees parsed%s%s%s',
                count($rows),
                $new > 0 ? ", {$new} new" : '',
                $pruned['removed'] > 0 ? ", {$pruned['removed']} no longer listed and removed" : '',
                $pruned['held'] > 0 ? ", {$pruned['held']} no longer listed — kept until the next run confirms it" : ''
            ));
        } catch (Throwable $e) {
            Log::warning('ParseAttendeeRosterJob failed', ['event_id' => $this->event->id, 'error' => $e->getMessage()]);
            $this->log('error', mb_substr($e->getMessage(), 0, 500));

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

    private function nextRosterPageUrl(string $html, string $fallbackBase): ?string
    {
        try {
            $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
            $next = $crawler->filter('a.next.page-numbers, link[rel="next"], .nav-next a, a[rel="next"]')->first();
            if ($next->count() === 0) {
                return null;
            }
            $href = trim((string) $next->attr('href'));
            if ($href === '' || str_starts_with($href, '#')) {
                return null;
            }
            if (str_starts_with($href, '/')) {
                $parts = parse_url($fallbackBase);

                return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$href;
            }

            return preg_match('#^https?://#i', $href) === 1 ? $href : null;
        } catch (\Throwable) {
            return null;
        }
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
