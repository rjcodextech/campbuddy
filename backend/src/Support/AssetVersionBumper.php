<?php

declare(strict_types=1);

namespace CampBuddy\Support;

use RuntimeException;

/**
 * Bumps the `?v=N` cache-busting suffix used on styles.css/app.js in
 * index.html, and the matching service worker cache name/asset URLs in
 * sw.js, so a purge forces every visitor's browser and service worker to
 * fetch fresh static assets on next load — there's no way to reach into a
 * visitor's browser cache directly, so this is the actual lever available.
 */
final class AssetVersionBumper
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function bump(): int
    {
        $indexPath = $this->projectRoot . '/index.html';
        $swPath = $this->projectRoot . '/sw.js';

        $index = @file_get_contents($indexPath);
        if ($index === false) {
            throw new RuntimeException("Could not read {$indexPath}");
        }
        if (!preg_match('/\?v=(\d+)/', $index, $m)) {
            throw new RuntimeException('Could not find a "?v=N" version marker in index.html');
        }

        $next = ((int) $m[1]) + 1;

        $newIndex = preg_replace('/\?v=\d+/', "?v={$next}", $index);
        if ($newIndex === null || @file_put_contents($indexPath, $newIndex) === false) {
            throw new RuntimeException("Could not write {$indexPath} (check file permissions)");
        }

        $sw = @file_get_contents($swPath);
        if ($sw !== false) {
            $newSw = preg_replace('/\?v=\d+/', "?v={$next}", $sw);
            $newSw = preg_replace('/campbuddy-v\d+/', "campbuddy-v{$next}", $newSw);
            if ($newSw !== null) {
                @file_put_contents($swPath, $newSw);
            }
        }

        return $next;
    }
}
