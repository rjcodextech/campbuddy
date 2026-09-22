<?php

declare(strict_types=1);

namespace CampBuddy\Domain\Media;

use CampBuddy\Settings;
use CampBuddy\Support\UpstreamClient;

/**
 * Server-side port of app.js's youtubeIdFromLink()/normalizeVideos().
 */
final class MediaFetcher
{
    public function __construct(
        private readonly UpstreamClient $client,
        private readonly Settings $settings,
    ) {
    }

    /**
     * Fetches the raw upstream media payload as-is (same shape as
     * /media's {items, total, total_pages, current_page}) so it can be
     * cached and re-served verbatim — the frontend's own
     * normalizeVideos()/youtubeIdFromLink() process it exactly like they
     * process the live upstream response today. Returns null if the
     * upstream call failed or the shape is unusable.
     *
     * @return array<string, mixed>|null
     */
    public function fetchRaw(): ?array
    {
        $json = $this->client->getJson($this->settings->upstreamApiBase . '/media');

        return is_array($json['items'] ?? null) ? $json : null;
    }

    public static function youtubeIdFromLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }
        if (preg_match('/(?:shorts\/|[?&]v=|youtu\.be\/)([a-zA-Z0-9_-]{6,})/', $link, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @param array<mixed>|null $json
     * @return array<int, mixed>|null
     */
    public static function normalizeVideos(?array $json): ?array
    {
        $items = $json['items'] ?? null;
        if (!is_array($items)) {
            return null;
        }

        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $id = self::youtubeIdFromLink($it['youtube_link'] ?? null);
            if ($id === null || empty($it['title'])) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'title' => (string) $it['title'],
                'type' => ($it['type'] ?? null) === 'short' ? 'short' : 'video',
                'url' => $it['youtube_link'],
                'thumb' => $it['thumbnail'] ?? null,
            ];
            if (count($out) >= 10) {
                break;
            }
        }

        return $out === [] ? null : $out;
    }
}
