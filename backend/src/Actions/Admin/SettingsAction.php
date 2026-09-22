<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Repository\MediaRepository;
use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SettingsAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly MediaRepository $media,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $slug = trim((string) ($body['event_slug'] ?? ''));

        if ($slug === '' || !preg_match('/^[a-z0-9-]{1,190}$/', $slug)) {
            $_SESSION['flash'] = 'Event slug must be lowercase letters, numbers and hyphens only.';
            return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
        }

        $this->events->setEventSlug($slug);
        // Pull the new event immediately so the dashboard/API reflect it
        // without waiting for the next cron tick.
        $eventOk = $this->events->refreshNow() !== null;
        $this->media->refreshNow();

        $_SESSION['flash'] = $eventOk
            ? "Event source updated to '{$slug}' and refreshed."
            : "Event source saved as '{$slug}', but the refresh failed — check the slug is correct.";

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
    }
}
