<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
use App\Support\DataVersion;
use App\Support\EventData;
use App\Support\EventTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The primary ingestion job: pulls sessions/speakers/
 * sponsors/organizers straight from the event's own wp-json REST API and
 * caches the normalized result. Runs every 15 minutes per active event,
 * plus on-demand via the admin "Refresh now" action.
 */
class FetchSpeakersSponsorsSessionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly Event $event) {}

    public function handle(): void
    {
        Cache::lock("ingest:sessions:{$this->event->id}", 300)->block(10, function () {
            $this->fetchAndCache();
        });
    }

    /**
     * Each list is fetched on its own. Sessions are what the app can't do
     * without, so their failure fails the run (and the queue retries it); a
     * failing sponsors or organizers endpoint — some sites switch one off —
     * only costs that one list, which keeps its last good copy. The fetch
     * log records exactly which list failed, so an admin isn't left guessing.
     */
    private function fetchAndCache(): void
    {
        $client = new WordCampRestClient($this->event->source_site_url);
        $normalizer = new WordCampNormalizer($client);
        $problems = [];
        $counts = [];

        // Taxonomy names only label things; without them the lists still work.
        $trackNames = $this->labels($client, 'session_track', 'tracks', $problems);
        $tierNames = $this->labels($client, 'sponsor_level', 'sponsor levels', $problems);
        $categoryNames = $this->labels($client, 'session_category', 'session categories', $problems);

        $notes = [];
        $zone = $this->resolveTimezone($client, $notes);

        try {
            $sessions = $normalizer->normalizeSessions($client->fetchSessions(), $trackNames, $categoryNames, $zone);
        } catch (Throwable $e) {
            Log::warning('FetchSpeakersSponsorsSessionsJob failed', [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
            ]);

            $this->log('error', substr('Sessions: '.$this->describe($e), 0, 500));

            throw $e;
        }

        $changes = [];
        $counts['sessions'] = $this->store('sessions', $sessions, $problems, $changes);

        $lists = [
            'speakers' => fn () => $normalizer->normalizeSpeakers($client->fetchSpeakers()),
            'sponsors' => fn () => $normalizer->normalizeSponsors($client->fetchSponsors(), $tierNames),
            'organizers' => fn () => $normalizer->normalizeOrganizers($client->fetchOrganizers()),
        ];

        foreach ($lists as $key => $fetch) {
            $items = $this->optional($fetch, $key, $problems);
            $counts[$key] = $items === null ? $this->cachedCount($key) : $this->store($key, $items, $problems, $changes);
        }

        $summary = sprintf(
            '%d sessions, %d speakers, %d sponsors, %d organizers',
            $counts['sessions'],
            $counts['speakers'],
            $counts['sponsors'],
            $counts['organizers']
        ).($changes === [] ? ' (no changes)' : ' ('.implode('; ', $changes).')');

        // When the data was last refreshed — pages use it to notice the
        // scheduler has stopped and refresh on their own (EventPageController).
        Cache::put("event:{$this->event->id}:fetched-at", now(), now()->addDays(14));

        $this->log(
            $problems === [] ? 'ok' : 'partial',
            substr($summary.($problems === [] ? '' : ' — '.implode('; ', $problems)).($notes === [] ? '' : ' · Note: '.implode('; ', $notes)), 0, 500)
        );
    }

    /**
     * The event's time zone, which gives session times their meaning. Kept
     * as the admin set it (timezone_locked); otherwise read from the site's
     * own REST index each run. If the site doesn't say, the last known zone
     * stays — and if none was ever known, the app's (noted for the admin).
     *
     * @param  array<int, string>  $notes
     */
    private function resolveTimezone(WordCampRestClient $client, array &$notes): \DateTimeZone
    {
        if (! $this->event->timezone_locked) {
            try {
                $found = $client->fetchTimezone();

                if ($found !== null && $found !== $this->event->timezone) {
                    $this->event->forceFill(['timezone' => $found])->saveQuietly();
                    DataVersion::forget($this->event->id);
                }
            } catch (Throwable) {
                // Keep what we had; sessions still come through.
            }
        }

        if (! EventTime::known($this->event)) {
            $notes[] = 'time zone unknown — set it on the event page so session times are right';
        }

        return EventTime::zone($this->event);
    }

    /**
     * Runs one optional fetch; on failure notes why and returns null, so the
     * caller keeps what it had.
     *
     * @param  array<int, string>  $problems
     */
    private function optional(callable $fetch, string $label, array &$problems): ?array
    {
        try {
            return $fetch();
        } catch (Throwable $e) {
            $problems[] = "{$label} not updated ({$this->describe($e)})";

            return null;
        }
    }

    /**
     * A taxonomy's term names. A 404 just means this site doesn't use that
     * taxonomy — not worth flagging on every run; anything else is noted.
     *
     * @param  array<int, string>  $problems
     * @return array<int, string>
     */
    private function labels(WordCampRestClient $client, string $taxonomy, string $label, array &$problems): array
    {
        try {
            return $client->fetchTaxonomyNames($taxonomy);
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                $problems[] = "{$label} not updated ({$this->describe($e)})";
            }
        } catch (Throwable $e) {
            $problems[] = "{$label} not updated ({$this->describe($e)})";
        }

        return [];
    }

    /**
     * Merges a freshly fetched list into the stored one (EventData::sync):
     * adds what's new, updates what changed, removes what the site no longer
     * lists — never clearing first. An empty answer or a sudden big drop is
     * held back (SafeSync) and noted here, so a site mid-edit can't blank a
     * live event.
     *
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $problems
     * @param  array<int, string>  $changes
     */
    private function store(string $key, array $items, array &$problems, array &$changes = []): int
    {
        $result = EventData::sync($this->event->id, $key, $items);

        $delta = array_filter([
            $result['added'] ? "+{$result['added']} new" : null,
            $result['updated'] ? "{$result['updated']} updated" : null,
            $result['removed'] ? "{$result['removed']} removed" : null,
        ]);
        if ($delta !== []) {
            $changes[] = "{$key}: ".implode(', ', $delta);
        }

        if ($result['held'] > 0) {
            $problems[] = $items === []
                ? "{$key}: the site returned none, kept the previous {$result['held']}"
                : "{$key}: {$result['held']} no longer listed — kept until the next refresh confirms it";
        }

        return $result['total'];
    }

    private function cachedCount(string $key): int
    {
        return EventData::count($this->event->id, $key) ?? 0;
    }

    /** A short, admin-readable reason — an HTTP status beats a stack of Guzzle text. */
    private function describe(Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return 'the site answered HTTP '.$e->response->status();
        }

        if ($e instanceof ConnectionException) {
            return 'the site could not be reached';
        }

        return $e->getMessage();
    }

    private function log(string $status, string $message): void
    {
        FetchLog::create([
            'event_id' => $this->event->id,
            'source' => 'wordcamp_rest',
            'job_type' => 'sessions_speakers_sponsors',
            'status' => $status,
            'message' => $message,
            'fetched_at' => now(),
        ]);
    }
}
