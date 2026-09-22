<?php

declare(strict_types=1);

namespace CampBuddy\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around the outbound call to the upstream WPSimplified API.
 * Strict timeouts and defensive JSON parsing — this is the only place in
 * the whole app that talks to a third party, and it must never hang a
 * request or trust an unexpected response shape.
 */
final class UpstreamClient
{
    private readonly Client $client;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->client = new Client([
            'connect_timeout' => 5,
            'timeout' => 8,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'CampBuddy-Backend/1.0 (+event data auto-fetch)',
            ],
        ]);
    }

    /**
     * @return array<mixed>|null
     */
    public function getJson(string $url): ?array
    {
        try {
            $response = $this->client->get($url);
        } catch (GuzzleException $e) {
            $this->logger->warning('Upstream request failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('Upstream returned non-200', ['url' => $url, 'status' => $response->getStatusCode()]);
            return null;
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            $this->logger->warning('Upstream returned non-JSON or unexpected shape', ['url' => $url]);
            return null;
        }

        return $decoded;
    }
}
