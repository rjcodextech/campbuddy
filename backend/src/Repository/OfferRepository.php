<?php

declare(strict_types=1);

namespace CampBuddy\Repository;

use PDO;

/**
 * Admin-managed affiliate/promotional offers shown on the frontend's
 * "Offer" tab. Directly DB-backed (no external fetch involved), so reads
 * are a single small indexed query — no extra caching layer needed beyond
 * the HTTP Cache-Control header the API sets.
 */
final class OfferRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, title, description, url, icon FROM offers WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, title, description, url, icon, sort_order, is_active FROM offers ORDER BY sort_order ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    public function create(string $title, string $description, string $url, string $icon, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO offers (title, description, url, icon, sort_order, is_active, created_at, updated_at)
             VALUES (:title, :description, :url, :icon, :sort_order, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'icon' => $icon,
            'sort_order' => $sortOrder,
        ]);
    }

    public function update(int $id, string $title, string $description, string $url, string $icon, int $sortOrder, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE offers SET title = :title, description = :description, url = :url, icon = :icon,
                sort_order = :sort_order, is_active = :is_active, updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM offers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, title, description, url, icon, sort_order, is_active FROM offers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
