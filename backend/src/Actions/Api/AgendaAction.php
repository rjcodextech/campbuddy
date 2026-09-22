<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Api;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Convenience endpoint exposing the server-derived agenda (same shape as
 * app.js's own agendaFromLive()). Not required by the current frontend —
 * provided for the admin dashboard and any future server-rendered use.
 */
final class AgendaAction
{
    use JsonResponder;

    public function __construct(private readonly EventRepository $events)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonCached($response, ['agenda' => $this->events->getAgenda()]);
    }
}
