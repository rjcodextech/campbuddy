<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
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

        try {
            $sessions = $normalizer->normalizeSessions($client->fetchSessions(), $trackNames, $categoryNames);
        } catch (Throwable $e) {
            Log::warning('FetchSpeakersSponsorsSessionsJob failed', [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
            ]);

            $this->log('error', substr('Sessions: '.$this->describe($e), 0, 500));

            throw $e;
        }

        $counts['sessions'] = $this->store('sessions', $sessions, $problems);

        $lists = [
            'speakers' => fn () => $normalizer->normalizeSpeakers($client->fetchSpeakers()),
            'sponsors' => fn () => $normalizer->normalizeSponsors($client->fetchSponsors(), $tierNames),
            'organizers' => fn () => $normalizer->normalizeOrganizers($client->fetchOrganizers()),
        ];

        foreach ($lists as $key => $fetch) {
            $items = $this->optional($fetch, $key, $problems);
            $counts[$key] = $items === null ? $this->cachedCount($key) : $this->store($key, $items, $problems);
        }

        $summary = sprintf(
            '%d sessions, %d speakers, %d sponsors, %d organizers',
            $counts['sessions'],
            $counts['speakers'],
            $counts['sponsors'],
            $counts['organizers']
        );

        $this->log(
            $problems === [] ? 'ok' : 'partial',
            substr($summary.($problems === [] ? '' : ' — '.implode('; ', $problems)), 0, 500)
        );
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
     * Caches a freshly fetched list — unless it's empty where the last good
     * copy wasn't. A live schedule dropping to nothing between two runs 15
     * minutes apart is far more likely a site mid-edit or a plugin hiccup
     * than every session being cancelled, and attendees would lose the
     * schedule mid-event. An admin's "Refresh now" still shows the note.
     *
     * Long TTL: this is "last known good" data served under a
     * stale-while-revalidate posture — reads never block on the upstream
     * site. Two weeks, not two days: a missed weekend of runs (host outage,
     * a stopped cron) must not wipe a live schedule.
     *
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $problems
     */
    private function store(string $key, array $items, array &$problems): int
    {
        $cacheKey = "event:{$this->event->id}:{$key}";

        if ($items === [] && $this->cachedCount($key) > 0) {
            $problems[] = "{$key}: the site returned none, kept the previous ".$this->cachedCount($key);

            return $this->cachedCount($key);
        }

        Cache::put($cacheKey, $items, now()->addDays(14));

        return count($items);
    }

    private function cachedCount(string $key): int
    {
        $cached = Cache::get("event:{$this->event->id}:{$key}");

        return is_array($cached) ? count($cached) : 0;
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
