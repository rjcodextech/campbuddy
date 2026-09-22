<?php

declare(strict_types=1);

namespace CampBuddy;

use Dotenv\Dotenv;

final class Settings
{
    private function __construct(
        public readonly string $appEnv,
        public readonly bool $appDebug,
        public readonly string $appUrl,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbDatabase,
        public readonly string $dbUsername,
        public readonly string $dbPassword,
        public readonly string $dbCharset,
        public readonly string $upstreamApiBase,
        public readonly string $eventSlug,
        public readonly int $cacheTtlSeconds,
        public readonly string $sessionSecret,
        public readonly int $sessionIdleTimeoutSeconds,
        public readonly int $sessionAbsoluteTimeoutSeconds,
        public readonly int $rateLimitApiPerMinute,
        public readonly int $rateLimitLoginPerMinute,
        public readonly string $logPath,
        public readonly string $basePath,
        public readonly string $backendMountPath,
    ) {
    }

    public static function fromEnv(string $basePath): self
    {
        if (is_file($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->load();
        }

        $env = static fn (string $key, ?string $default = null): ?string =>
            $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;

        return new self(
            appEnv: $env('APP_ENV', 'production'),
            appDebug: filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
            appUrl: rtrim((string) $env('APP_URL', ''), '/'),
            dbHost: (string) $env('DB_HOST', '127.0.0.1'),
            dbPort: (int) $env('DB_PORT', '3306'),
            dbDatabase: (string) $env('DB_DATABASE', ''),
            dbUsername: (string) $env('DB_USERNAME', ''),
            dbPassword: (string) $env('DB_PASSWORD', ''),
            dbCharset: (string) $env('DB_CHARSET', 'utf8mb4'),
            upstreamApiBase: rtrim((string) $env('UPSTREAM_API_BASE', ''), '/'),
            eventSlug: (string) $env('EVENT_SLUG', ''),
            cacheTtlSeconds: (int) $env('CACHE_TTL_SECONDS', '900'),
            sessionSecret: (string) $env('SESSION_SECRET', ''),
            sessionIdleTimeoutSeconds: (int) $env('SESSION_IDLE_TIMEOUT_SECONDS', '1800'),
            sessionAbsoluteTimeoutSeconds: (int) $env('SESSION_ABSOLUTE_TIMEOUT_SECONDS', '7200'),
            rateLimitApiPerMinute: (int) $env('RATE_LIMIT_API_PER_MINUTE', '60'),
            rateLimitLoginPerMinute: (int) $env('RATE_LIMIT_LOGIN_PER_MINUTE', '5'),
            logPath: (string) $env('LOG_PATH', 'backend/storage/logs/app.log'),
            basePath: $basePath,
            backendMountPath: '/' . trim((string) $env('BACKEND_MOUNT_PATH', '/backend'), '/'),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }

    public function adminUrl(string $suffix = ''): string
    {
        return $this->backendMountPath . '/admin' . $suffix;
    }
}
