<?php

namespace App\Console\Commands;

use App\Support\SocialImageProxy;
use Illuminate\Console\Command;

/**
 * Removes the Social media page's kept copies of sponsor logos, site artwork
 * and fonts (storage/app/private/social-cache) older than a day; they are fetched
 * again when a page needs them. The scheduler runs it every night at 00:15.
 *
 *   php artisan campbuddy:clear-social-cache            # do it
 *   php artisan campbuddy:clear-social-cache --dry-run  # only count
 */
class ClearSocialCacheCommand extends Command
{
    protected $signature = 'campbuddy:clear-social-cache {--dry-run : Only count what would be removed}';

    protected $description = "Remove the Social media page's cached logos, artwork and fonts older than a day (they are fetched again when needed)";

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        ['files' => $files, 'bytes' => $bytes] = SocialImageProxy::prune(SocialImageProxy::KEEP_HOURS, $dry);
        $size = number_format($bytes / 1048576, 1).' MB';

        $this->info($dry
            ? "Would remove {$files} cached file(s), {$size}. Dry run — nothing removed."
            : "Removed {$files} cached file(s), {$size}.");

        return self::SUCCESS;
    }
}
