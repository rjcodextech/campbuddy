<?php

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Models\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled ingestion (§5.2, §12)
|--------------------------------------------------------------------------
| One cPanel cron entry (`schedule:run` every minute, §12.6) dispatches
| each job on its own cadence from here. FetchSpeakersSponsorsSessionsJob
| runs every 15 minutes per active event, matching V1's old refresh
| cadence (§12); ParseAttendeeRosterJob runs once daily (§3.3 IN2) since
| it's a scrape of someone else's site, not an API with a rate limit.
| FetchBrandingAssetsJob is on-demand only (§5.2), not on this schedule.
|
| Jobs run sequentially with a small stagger, never concurrently against
| multiple upstream WordCamp sites at once (§5.2, §3.3 IN3).
*/
Schedule::call(function () {
    Event::where('status', 'active')->each(function (Event $event, int $index) {
        FetchSpeakersSponsorsSessionsJob::dispatch($event)->delay(now()->addSeconds($index * 5));
    });
})->everyFifteenMinutes()->name('ingest-sessions-speakers-sponsors');

Schedule::call(function () {
    Event::where('status', 'active')->each(function (Event $event, int $index) {
        ParseAttendeeRosterJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->daily()->name('ingest-attendee-roster');
