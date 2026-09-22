<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Repository\MediaRepository;
use CampBuddy\Settings;
use CampBuddy\Support\AssetVersionBumper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * One button, two things purged:
 *  - the server-side event/media data cache (forces an immediate re-fetch,
 *    same mechanism as "Refresh now")
 *  - visitors' cached CSS/JS (by bumping the ?v=N cache-busting suffix in
 *    index.html/sw.js — there's no way to reach into a browser's cache
 *    directly, so a version bump is what actually forces a fresh fetch)
 */
final class PurgeCacheAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly MediaRepository $media,
        private readonly AssetVersionBumper $assetVersion,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $eventOk = $this->events->refreshNow() !== null;
        $mediaOk = $this->media->refreshNow() !== null;

        try {
            $newVersion = $this->assetVersion->bump();
            $assetMessage = "Static assets bumped to v{$newVersion} — browsers and the service worker will fetch fresh CSS/JS on next load.";
        } catch (Throwable $e) {
            $assetMessage = 'Event/media cache cleared, but the CSS/JS version bump failed: ' . $e->getMessage();
        }

        $dataMessage = $eventOk && $mediaOk
            ? 'Event data cache cleared and refreshed.'
            : 'Event data cache clear had failures — check the status below.';

        $_SESSION['flash'] = "{$dataMessage} {$assetMessage}";

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
    }
}
