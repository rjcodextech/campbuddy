<?php

namespace App\Console\Commands;

use App\Support\SocialImageProxy;
use Illuminate\Console\Command;

/**
 * Removes the Social media page's copies of sponsor logos, site artwork and
 * fonts (storage/app/private/social-cache) that no retained event uses any more
 * and that are older than a day, and automatically deletes cached branding and
 * event data for events finished more than 30 days ago.
 *
 *   php artisan campbuddy:clear-social-cache            # do it
 *   php artisan campbuddy:clear-social-cache --dry-run  # only count
 */
class ClearSocialCacheCommand extends Command
{
    protected $signature = 'campbuddy:clear-social-cache {--dry-run : Only count what would be removed}';

    protected $description = "Remove copies of logos, artwork and fonts no event uses, and delete cache for events ended 30+ days ago";

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        ['files' => $files, 'bytes' => $bytes, 'kept' => $kept] = SocialImageProxy::prune(SocialImageProxy::KEEP_HOURS, $dry);
        $size = number_format($bytes / 1048576, 1).' MB';

        $this->info($dry
            ? "Would remove {$files} unused file(s), {$size}; {$kept} in use stay. Dry run — nothing removed."
            : "Removed {$files} unused file(s), {$size}; {$kept} in use stay.");

        return self::SUCCESS;
    }
}
