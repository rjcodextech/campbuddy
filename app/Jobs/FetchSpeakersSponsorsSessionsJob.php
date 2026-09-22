<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The primary ingestion job (§0.1, §5.2): pulls sessions/speakers/
 * sponsors/organizers straight from the event's own wp-json REST API and
 * caches the normalized result. Runs every 15 minutes per active event
 * (§12), plus on-demand via the admin "Refresh now" action (§9).
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

    private function fetchAndCache(): void
    {
        $client = new WordCampRestClient($this->event->source_site_url);
        $normalizer = new WordCampNormalizer($client);

        try {
            $trackNames = $client->fetchTaxonomyNames('session_track');
            $tierNames = $client->fetchTaxonomyNames('sponsor_level');

            $sessions = $normalizer->normalizeSessions($client->fetchSessions(), $trackNames);
            $speakers = $normalizer->normalizeSpeakers($client->fetchSpeakers());
            $sponsors = $normalizer->normalizeSponsors($client->fetchSponsors(), $tierNames);
            $organizers = $normalizer->normalizeOrganizers($client->fetchOrganizers());

            // Long TTL: this is "last known good" data served under a
            // stale-while-revalidate posture (§5.1) — reads never block
            // on the upstream site, so a broken feed degrades slowly
            // rather than blanking the event the moment one fetch fails.
            $ttl = now()->addDays(2);
            Cache::put("event:{$this->event->id}:sessions", $sessions, $ttl);
            Cache::put("event:{$this->event->id}:speakers", $speakers, $ttl);
            Cache::put("event:{$this->event->id}:sponsors", $sponsors, $ttl);
            Cache::put("event:{$this->event->id}:organizers", $organizers, $ttl);

            $this->log('ok', sprintf(
                '%d sessions, %d speakers, %d sponsors, %d organizers',
                count($sessions),
                count($speakers),
                count($sponsors),
                count($organizers)
            ));
        } catch (Throwable $e) {
            Log::warning('FetchSpeakersSponsorsSessionsJob failed', [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
            ]);

            $this->log('error', substr($e->getMessage(), 0, 500));

            throw $e;
        }
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
