<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Api;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EventAction
{
    use JsonResponder;

    public function __construct(private readonly EventRepository $events)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Mirrors the upstream /events?slug=... shape (events:[{...}]) so
        // the frontend's existing buildEventPatch() needs no changes.
        return $this->jsonCached($response, ['events' => [$this->events->getRawEvent()]]);
    }
}
