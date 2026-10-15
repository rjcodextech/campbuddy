<?php

namespace App\Console\Commands;

use App\Support\PersonalDataErasure;
use Illuminate\Console\Command;

/**
 * Deletes what attendees shared (discovery profiles, shared Camp Cards, push addresses, deal-form leads…) for
 * events whose retention window plus 60 days has passed. The scheduler runs it every night:
 *
 *   php artisan campbuddy:erase-expired-data            # do it
 *   php artisan campbuddy:erase-expired-data --dry-run  # only count
 */
class EraseExpiredDataCommand extends Command
{
    protected $signature = 'campbuddy:erase-expired-data {--dry-run : Only count what would be removed}';

    protected $description = 'Erase attendees\' shared data for events finished more than ~90 days ago (DPDPA: purpose served)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $removed = PersonalDataErasure::run($dry);

        foreach ($removed as $table => $count) {
            $this->line(str_pad($table, 22).$count);
        }
        $this->info($dry ? 'Dry run — nothing removed.' : 'Done: '.array_sum($removed).' row(s) erased.');

        return self::SUCCESS;
    }
}
