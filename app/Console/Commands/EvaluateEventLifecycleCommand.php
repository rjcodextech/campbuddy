<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateEventLifecycleJob;
use Illuminate\Console\Command;

class EvaluateEventLifecycleCommand extends Command
{
    protected $signature = 'campbuddy:evaluate-event-lifecycle';

    protected $description = 'Archive events whose dates have passed, and auto-publish drafts with a reachable website and at least one attendee';

    public function handle(): int
    {
        $this->info('Evaluating event lifecycle…');

        EvaluateEventLifecycleJob::dispatchSync();

        $this->info('Done — see the fetch log (source: lifecycle) for what changed.');

        return self::SUCCESS;
    }
}
