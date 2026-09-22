<?php

declare(strict_types=1);

namespace CampBuddy\Tests;

use CampBuddy\Domain\Media\MediaFetcher;
use PHPUnit\Framework\TestCase;

final class MediaFetcherTest extends TestCase
{
    public function testYoutubeIdFromLinkHandlesShortsAndWatchLinks(): void
    {
        self::assertSame('WKtSb8kJCy8', MediaFetcher::youtubeIdFromLink('https://www.youtube.com/shorts/WKtSb8kJCy8'));
        self::assertSame('dQw4w9WgXcQ', MediaFetcher::youtubeIdFromLink('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        self::assertSame('dQw4w9WgXcQ', MediaFetcher::youtubeIdFromLink('https://youtu.be/dQw4w9WgXcQ'));
        self::assertNull(MediaFetcher::youtubeIdFromLink('https://example.com/not-a-video'));
        self::assertNull(MediaFetcher::youtubeIdFromLink(null));
    }

    public function testNormalizeVideosFiltersAndCapsAtTen(): void
    {
        $items = [];
        for ($i = 0; $i < 15; $i++) {
            $items[] = ['title' => "Video {$i}", 'youtube_link' => "https://youtu.be/abcdef{$i}g", 'type' => 'video'];
        }
        // One with no title, one with an unparseable link — both dropped.
        $items[] = ['youtube_link' => 'https://youtu.be/notitleabc'];
        $items[] = ['title' => 'No link'];

        $result = MediaFetcher::normalizeVideos(['items' => $items]);

        self::assertCount(10, $result);
        self::assertSame('abcdef0g', $result[0]['id']);
        self::assertSame('Video 0', $result[0]['title']);
        self::assertSame('video', $result[0]['type']);
    }

    public function testNormalizeVideosReturnsNullForUnusableShape(): void
    {
        self::assertNull(MediaFetcher::normalizeVideos(null));
        self::assertNull(MediaFetcher::normalizeVideos(['items' => 'not-an-array']));
        self::assertNull(MediaFetcher::normalizeVideos(['items' => []]));
    }

    public function testNormalizeVideosMarksShortsCorrectly(): void
    {
        $result = MediaFetcher::normalizeVideos([
            'items' => [
                ['title' => 'A Short', 'youtube_link' => 'https://www.youtube.com/shorts/abcdef123', 'type' => 'short'],
                ['title' => 'Not a short', 'youtube_link' => 'https://www.youtube.com/shorts/ghijkl456', 'type' => 'post'],
            ],
        ]);

        self::assertSame('short', $result[0]['type']);
        self::assertSame('video', $result[1]['type']); // anything not literally "short" -> "video"
    }
}
