<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Repository\MediaRepository;
use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RefreshAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly MediaRepository $media,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $eventOk = $this->events->refreshNow() !== null;
        $mediaOk = $this->media->refreshNow() !== null;

        $_SESSION['flash'] = $eventOk && $mediaOk
            ? 'Refreshed event data and Explore videos.'
            : 'Refresh finished with some failures — check the status below.';

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
    }
}
