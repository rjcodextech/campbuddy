<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Api;

use CampBuddy\Repository\MediaRepository;
use CampBuddy\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MediaAction
{
    use JsonResponder;

    public function __construct(private readonly MediaRepository $media)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Mirrors the upstream /media shape exactly, so the frontend's
        // existing normalizeVideos()/youtubeIdFromLink() need no changes.
        return $this->jsonCached($response, $this->media->getRawMedia());
    }
}
