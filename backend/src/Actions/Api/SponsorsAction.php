<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Api;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Convenience endpoint exposing the server-derived sponsor tiers
 * (grouped/labelled the same way app.js's own sponsorsFromLive() does).
 * Not required by the current frontend, which derives this itself from
 * /api/v1/event — provided for the admin dashboard and any future
 * server-rendered use.
 */
final class SponsorsAction
{
    use JsonResponder;

    public function __construct(private readonly EventRepository $events)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonCached($response, ['sponsors' => $this->events->getSponsors()]);
    }
}
