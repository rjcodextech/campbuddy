<?php

declare(strict_types=1);

namespace CampBuddy\Repository;

use PDO;

/**
 * Small key/value store for admin-managed settings (currently just the
 * event slug) so they can be changed from /backend/admin instead of
 * requiring a .env edit + deploy.
 */
final class AppSettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM app_settings WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function set(string $name, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (name, value, updated_at) VALUES (:name, :value, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
        );
        $stmt->execute(['name' => $name, 'value' => $value]);
    }
}
