<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverWordCampsJob;
use App\Models\FetchLog;
use Illuminate\Console\Command;

class DiscoverWordCampsCommand extends Command
{
    protected $signature = 'campbuddy:discover-wordcamps';

    protected $description = "Read events.wordpress.org's upcoming in-person WordCamps listing and add any new ones as draft events";

    public function handle(): int
    {
        $this->info('Searching for upcoming WordCamps…');

        DiscoverWordCampsJob::dispatchSync();

        $log = FetchLog::where('job_type', 'discovery')->latest('fetched_at')->first();
        $this->info($log?->message ?? 'Done.');
        $this->line('New events are drafts — review and approve them at /admin/events.');

        return self::SUCCESS;
    }
}
