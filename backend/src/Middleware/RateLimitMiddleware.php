<?php

declare(strict_types=1);

namespace CampBuddy\Middleware;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Simple fixed-window (per-minute) rate limiter backed by the cache_store's
 * sibling table, rate_limit_hits. This is defense-in-depth: the actual
 * protection against 1M-user-scale traffic is the Cache-Control/ETag
 * headers on API responses plus a CDN in front of the app, which absorbs
 * nearly all repeat requests before they ever reach PHP.
 */
final class RateLimitMiddleware
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $scope,
        private readonly int $limitPerMinute,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, Handler $handler): ResponseInterface
    {
        $ip = $this->clientIp($request);
        $windowMinute = gmdate('YmdHi');
        $bucketKey = "{$this->scope}:{$ip}:{$windowMinute}";

        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limit_hits (bucket_key, window_start, count)
             VALUES (:key, UTC_TIMESTAMP(), 1)
             ON DUPLICATE KEY UPDATE count = count + 1'
        );
        $stmt->execute(['key' => $bucketKey]);

        $select = $this->pdo->prepare('SELECT count FROM rate_limit_hits WHERE bucket_key = :key');
        $select->execute(['key' => $bucketKey]);
        $count = (int) $select->fetchColumn();

        // Occasionally sweep old windows so the table doesn't grow forever.
        if (random_int(1, 200) === 1) {
            $this->pdo->exec("DELETE FROM rate_limit_hits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 1 HOUR");
        }

        if ($count > $this->limitPerMinute) {
            $response = new Response(429);
            $response->getBody()->write(json_encode(['error' => 'Too many requests, please slow down.']));

            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withHeader('Retry-After', '60');
        }

        return $handler->handle($request);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        return $server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
