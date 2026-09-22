<?php

declare(strict_types=1);

namespace CampBuddy\Repository;

use PDO;

final class AdminUserRepository
{
    private const MAX_FAILED_LOGINS = 10;
    private const LOCKOUT_SECONDS = 900;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{id: int, username: string, password_hash: string, failed_logins: int, locked_until: ?string}|null
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, password_hash, failed_logins, locked_until FROM admin_users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $username, string $plainPassword): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, created_at, updated_at)
             VALUES (:u, :h, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), failed_logins = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP()'
        );
        $stmt->execute([
            'u' => $username,
            'h' => password_hash($plainPassword, PASSWORD_ARGON2ID),
        ]);
    }

    public function isLocked(array $user): bool
    {
        return $user['locked_until'] !== null && strtotime($user['locked_until']) > time();
    }

    public function recordFailedLogin(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET
                failed_logins = failed_logins + 1,
                locked_until = CASE WHEN failed_logins + 1 >= :max THEN DATE_ADD(UTC_TIMESTAMP(), INTERVAL :lockout SECOND) ELSE locked_until END,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'max' => self::MAX_FAILED_LOGINS, 'lockout' => self::LOCKOUT_SECONDS]);
    }

    public function resetFailedLogins(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
