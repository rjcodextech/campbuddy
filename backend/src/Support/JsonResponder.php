<?php

declare(strict_types=1);

namespace CampBuddy\Support;

use Psr\Http\Message\ResponseInterface;

trait JsonResponder
{
    /**
     * @param array<mixed> $data
     */
    private function jsonCached(ResponseInterface $response, array $data, int $maxAge = 300, int $staleWhileRevalidate = 600): ResponseInterface
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"' . md5($body) . '"';

        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', "public, max-age={$maxAge}, stale-while-revalidate={$staleWhileRevalidate}")
            ->withHeader('ETag', $etag);
    }

    /**
     * @param array<mixed> $data
     */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
