<?php

declare(strict_types=1);

namespace CampBuddy\Repository;

use CampBuddy\Cache\CacheInterface;
use CampBuddy\Domain\Event\EventFetcher;
use CampBuddy\Settings;
use PDO;

/**
 * Read path for the raw event object (cached verbatim, same shape as the
 * upstream /events?slug=... events[0]) plus admin overrides on top. Serves
 * from the "quick data store" (cache_store table) and self-heals on the
 * read path: if the cache is stale, it serves the stale payload
 * immediately (never blocks a visitor on an outbound call) while at most
 * one concurrent request refetches in the background via a short
 * single-flight lock. This is what keeps the app correct even if the cron
 * job hasn't run yet or is briefly unavailable.
 */
final class EventRepository
{
    private const CACHE_KEY = 'event:raw';
    private const LOCK_SECONDS = 15;

    /**
     * Raw upstream field names an admin is allowed to override, mapped to
     * a human label for the dashboard form. Matches EventFetcher's own
     * buildEventPatch() inputs.
     */
    public const OVERRIDABLE_FIELDS = [
        'title' => 'Event title',
        'event_tagline' => 'Tagline',
        'event_start_date' => 'Start date (YYYY-MM-DD)',
        'event_end_date' => 'End date (YYYY-MM-DD)',
        'event_venue_name' => 'Venue name',
        'event_venue_address' => 'Venue address',
        'event_hashtag' => 'Hashtag',
        'event_home_url' => 'Official site URL',
        'event_tickets_url' => 'Ticket URL',
        'event_venue_directions_url' => 'Directions URL',
        'event_email' => 'Contact email',
    ];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly EventFetcher $fetcher,
        private readonly FetchLogRepository $fetchLog,
        private readonly PDO $pdo,
        private readonly Settings $settings,
        private readonly AppSettingsRepository $appSettings,
    ) {
    }

    /**
     * The live event slug: DB-managed (editable from /backend/admin) with
     * .env's EVENT_SLUG only as the first-run bootstrap default.
     */
    public function getEventSlug(): string
    {
        return $this->appSettings->get('event_slug', $this->settings->eventSlug) ?: $this->settings->eventSlug;
    }

    public function setEventSlug(string $slug): void
    {
        $this->appSettings->set('event_slug', $slug);
    }

    /**
     * Raw event object, same field names as the upstream API, with any
     * admin overrides merged on top. This is what /api/v1/event exposes.
     *
     * @return array<string, mixed>
     */
    public function getRawEvent(): array
    {
        return array_merge($this->getCachedRaw(), $this->getOverrides());
    }

    /**
     * @return array<int, mixed>
     */
    public function getSponsors(): array
    {
        return EventFetcher::sponsorsFromLive($this->getCachedRaw()) ?? [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getAgenda(): array
    {
        return EventFetcher::agendaFromLive($this->getCachedRaw()) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCachedRaw(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);

        if ($cached === null) {
            return $this->refreshNow() ?? [];
        }

        if ($this->cache->isStale(self::CACHE_KEY) && $this->cache->acquireRefreshLock(self::CACHE_KEY, self::LOCK_SECONDS)) {
            $fresh = $this->refreshNow();
            if ($fresh !== null) {
                return $fresh;
            }
        }

        return $cached;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function refreshNow(): ?array
    {
        $raw = $this->fetcher->fetchRaw($this->getEventSlug());

        if ($raw === null) {
            $this->fetchLog->record('event', 'failure', 'Upstream fetch failed or returned an unexpected shape');
            return null;
        }

        $this->cache->set(self::CACHE_KEY, $raw, $this->settings->cacheTtlSeconds);
        $this->fetchLog->record('event', 'success', null);

        return $raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverrides(): array
    {
        $row = $this->pdo->query('SELECT data FROM event_overrides WHERE id = 1')->fetchColumn();
        $decoded = $row !== false ? json_decode((string) $row, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function setOverrides(array $overrides, ?string $updatedBy): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE event_overrides SET data = :data, updated_at = UTC_TIMESTAMP(), updated_by = :by WHERE id = 1'
        );
        $stmt->execute([
            'data' => json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'by' => $updatedBy,
        ]);
    }
}
