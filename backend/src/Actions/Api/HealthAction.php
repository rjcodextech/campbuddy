<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Api;

use CampBuddy\Support\JsonResponder;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class HealthAction
{
    use JsonResponder;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->pdo->query('SELECT 1');
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }

        $response = $this->json($response, ['status' => $dbOk ? 'ok' : 'degraded', 'database' => $dbOk], $dbOk ? 200 : 503);

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
