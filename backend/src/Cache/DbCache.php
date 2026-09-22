<?php

declare(strict_types=1);

namespace CampBuddy\Cache;

use PDO;

/**
 * "Quick data store" cache backed by the cache_store MySQL table. Kept
 * behind CacheInterface so it can be swapped for Redis/APCu later (e.g. on
 * a VPS) without touching any call site.
 */
final class DbCache implements CacheInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT payload FROM cache_store WHERE cache_key = :key');
        $stmt->execute(['key' => $key]);
        $payload = $stmt->fetchColumn();

        if ($payload === false) {
            return null;
        }

        $decoded = json_decode((string) $payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function isStale(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT expires_at FROM cache_store WHERE cache_key = :key');
        $stmt->execute(['key' => $key]);
        $expiresAt = $stmt->fetchColumn();

        if ($expiresAt === false) {
            return true;
        }

        return strtotime((string) $expiresAt) < time();
    }

    public function set(string $key, array $payload, int $ttlSeconds): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cache_store (cache_key, payload, expires_at, locked_until, updated_at)
             VALUES (:key, :payload, :expires_at, NULL, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                payload = VALUES(payload),
                expires_at = VALUES(expires_at),
                locked_until = NULL,
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            'key' => $key,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
    }

    public function acquireRefreshLock(string $key, int $lockSeconds): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $lockedUntil = gmdate('Y-m-d H:i:s', time() + $lockSeconds);

        // Ensure a row exists to lock (first-ever fetch for this key).
        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO cache_store (cache_key, payload, expires_at, locked_until, updated_at)
             VALUES (:key, :empty_payload, :past, :locked_until, UTC_TIMESTAMP())'
        );
        $insert->execute([
            'key' => $key,
            'empty_payload' => '[]',
            'past' => $now,
            'locked_until' => $lockedUntil,
        ]);
        if ($insert->rowCount() > 0) {
            return true;
        }

        // Row already existed: only acquire if nobody else's lock is active.
        $update = $this->pdo->prepare(
            'UPDATE cache_store
             SET locked_until = :locked_until
             WHERE cache_key = :key AND (locked_until IS NULL OR locked_until < :now)'
        );
        $update->execute([
            'key' => $key,
            'locked_until' => $lockedUntil,
            'now' => $now,
        ]);

        return $update->rowCount() > 0;
    }
}
