<?php

namespace App\Console\Commands;

use App\Support\FetchLogRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes fetch-log rows older than 7 days (the newest of each event and kind
 * of fetch stay). The scheduler runs it every night; run it by hand once after
 * a deploy to clear a long backlog at once:
 *
 *   php artisan campbuddy:prune-fetch-log            # do it
 *   php artisan campbuddy:prune-fetch-log --dry-run  # only count
 */
class PruneFetchLogCommand extends Command
{
    protected $signature = 'campbuddy:prune-fetch-log {--dry-run : Only count what would be removed}';

    protected $description = 'Remove fetch-log rows older than 7 days (the latest of each event and kind of fetch is kept)';

    public function handle(): int
    {
        $total = DB::table('fetch_log')->count();
        $expired = FetchLogRetention::expired()->count();
        $days = FetchLogRetention::KEEP_DAYS;

        $this->line("fetch_log: {$total} row(s), {$expired} older than {$days} days (and not the latest of their kind).");

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing removed.');

            return self::SUCCESS;
        }

        $removed = FetchLogRetention::prune();

        $this->info("Removed {$removed} row(s); ".DB::table('fetch_log')->count().' left.');

        return self::SUCCESS;
    }
}
