<?php

declare(strict_types=1);

namespace CampBuddy\Repository;

use CampBuddy\Cache\CacheInterface;
use CampBuddy\Domain\Media\MediaFetcher;
use CampBuddy\Settings;

/**
 * Same stale-while-revalidate + single-flight-lock read path as
 * EventRepository, for the raw media/Explore-videos payload (cached
 * verbatim, same shape as upstream's /media).
 */
final class MediaRepository
{
    private const CACHE_KEY = 'media:raw';
    private const LOCK_SECONDS = 15;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly MediaFetcher $fetcher,
        private readonly FetchLogRepository $fetchLog,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawMedia(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);

        if ($cached === null) {
            return $this->refreshNow() ?? ['items' => [], 'total' => 0, 'total_pages' => 0, 'current_page' => 1];
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
        $raw = $this->fetcher->fetchRaw();

        if ($raw === null) {
            $this->fetchLog->record('media', 'failure', 'Upstream fetch failed or returned an unexpected shape');
            return null;
        }

        $this->cache->set(self::CACHE_KEY, $raw, $this->settings->cacheTtlSeconds);
        $this->fetchLog->record('media', 'success', null);

        return $raw;
    }
}
