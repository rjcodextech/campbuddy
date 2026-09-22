<?php

declare(strict_types=1);

namespace CampBuddy\Repository;

use PDO;

final class FetchLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(string $source, string $status, ?string $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fetch_log (source, status, message, fetched_at) VALUES (:source, :status, :message, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'source' => $source,
            'status' => $status,
            'message' => $message !== null ? mb_substr($message, 0, 500) : null,
        ]);
    }

    /**
     * @return array{status: string, message: ?string, fetched_at: string}|null
     */
    public function latest(string $source): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, message, fetched_at FROM fetch_log WHERE source = :source ORDER BY fetched_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['source' => $source]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
