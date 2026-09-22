<?php

declare(strict_types=1);

namespace CampBuddy\Cache;

interface CacheInterface
{
    /**
     * Returns the cached payload for $key, or null if there is no row at
     * all. Does NOT check freshness — callers use isStale() for that, since
     * a stale-but-present payload is still useful to serve immediately.
     */
    public function get(string $key): ?array;

    public function isStale(string $key): bool;

    /**
     * @param array<mixed> $payload
     */
    public function set(string $key, array $payload, int $ttlSeconds): void;

    /**
     * Attempts to acquire a short single-flight lock for $key so that only
     * one process refetches an expired key at a time. Returns true if the
     * lock was acquired (caller should refetch), false if someone else
     * already holds it (caller should just serve the stale payload).
     */
    public function acquireRefreshLock(string $key, int $lockSeconds): bool;
}
