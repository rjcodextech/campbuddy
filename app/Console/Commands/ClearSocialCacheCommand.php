<?php

namespace App\Console\Commands;

use App\Support\SocialImageProxy;
use Illuminate\Console\Command;

/**
 * Removes the Social media page's copies of sponsor logos, site artwork and
 * fonts (storage/app/private/social-cache) that no listed event uses any more
 * and that are older than a day. Every copy still in use stays. The
 * scheduler runs it every night at 00:15.
 *
 *   php artisan campbuddy:clear-social-cache            # do it
 *   php artisan campbuddy:clear-social-cache --dry-run  # only count
 */
class ClearSocialCacheCommand extends Command
{
    protected $signature = 'campbuddy:clear-social-cache {--dry-run : Only count what would be removed}';

    protected $description = "Remove the Social media page's copies of logos, artwork and fonts that no event uses any more (copies in use stay)";

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
