<?php

namespace App\Console\Commands;

use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Bootstraps one event's data (§11.2, §12.5): runs each job synchronously
 * so the very first `campbuddy:ingest` gives immediate feedback instead
 * of silently queuing.
 */
class CampbuddyIngestCommand extends Command
{
    protected $signature = 'campbuddy:ingest {slug : The event slug to bootstrap}';

    protected $description = "Bootstrap one event's data: branding, sessions/speakers/sponsors, and the attendee roster";

    public function handle(): int
    {
        $event = Event::where('slug', $this->argument('slug'))->first();

        if (! $event) {
            $this->error("No event found with slug \"{$this->argument('slug')}\". Create it first via /admin/events.");

            return self::FAILURE;
        }

        $ok = true;

        $this->info('Fetching branding assets...');
        FetchBrandingAssetsJob::dispatchSync($event);
        $ok = $this->reportLastLog($event, 'branding') && $ok;

        $this->info("Fetching sessions, speakers, and sponsors for \"{$event->display_name}\"...");
        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);
        $ok = $this->reportLastLog($event, 'sessions_speakers_sponsors') && $ok;

        $this->info('Fetching the attendee roster...');
        ParseAttendeeRosterJob::dispatchSync($event);
        $ok = $this->reportLastLog($event, 'roster') && $ok;

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function reportLastLog(Event $event, string $jobType): bool
    {
        $log = $event->fetchLogs()->where('job_type', $jobType)->latest('fetched_at')->first();

        if ($log?->status === 'ok') {
            $this->info("  Done: {$log->message}");

            return true;
        }

        $this->error('  Failed: '.($log?->message ?? 'unknown error — check the logs.'));

        return false;
    }
}
